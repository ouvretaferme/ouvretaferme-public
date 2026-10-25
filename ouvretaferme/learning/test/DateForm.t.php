<?php
namespace learning;

/**
 * Le formulaire d'une date (lot formulaires-visio, retours du 01/10, points 17 et 18).
 *
 * Le champ « Durée de formation » montre, vide, la durée que donnent les séances. Deux calculs la
 * rendent : le serveur à l'ouverture du panneau (`Date::getScheduleMinutes()`, puis
 * `DateUi::getHoursPlaceholder()`), le navigateur dès qu'un horaire change
 * (`LearningDate.getScheduleHours()`). Le script de l'écran tourne ici dans node, sur les mêmes
 * séances que le panneau rendu : c'est son code qui s'exécute, et non une relecture du source.
 *
 * Les dates sont construites en mémoire, rien ne s'écrit en base.
 *
 * Lancement : dev/test package=learning file=DateForm
 */
class DateFormTest extends \Test {

	/**
	 * Les cas du retour, passés aux séances (lot date-mixte) : la durée proposée est la somme des
	 * séances, chacune de son début à sa fin. Puis ceux où les deux calculs pourraient se séparer :
	 * une durée qui ne tombe pas sur la demi-heure, le passage à l'heure d'hiver, une journée coupée
	 * en deux. Puis les séances qui ne donnent aucune durée, et donc aucune indication : aucune
	 * séance, une fin qui ne vient pas après le début, que l'envoi refuse (`endAt.consistent`) ; une
	 * telle séance à côté d'une autre ne compte rien, et l'autre compte.
	 *
	 * L'indication est un texte, écrit comme le produit écrit un nombre : la virgule, aucun zéro
	 * derrière un nombre entier, et l'espace fine insécable des milliers, que les deux côtés tirent
	 * chacun de son propre formateur.
	 *
	 * Chaque séance s'écrit `[jour, début, fin]`.
	 */
	const SCHEDULES = [
		'une séance' => [[['2026-11-07', '09:30', '17:00']], '7,5'],
		'deux journées' => [[['2026-11-07', '09:30', '17:00'], ['2026-11-08', '09:30', '17:00']], '15'],
		'sans séance' => [[], NULL],
		'hors de la demi-heure' => [[['2026-11-07', '09:00', '17:20']], '8,33'],
		'à travers le passage à l\'heure d\'hiver' => [[['2026-10-24', '09:00', '12:00'], ['2026-10-25', '09:00', '12:00'], ['2026-10-26', '09:00', '12:00']], '9'],
		'une journée coupée en deux' => [[['2026-11-07', '09:00', '12:30'], ['2026-11-07', '14:00', '17:00']], '6,5'],
		'un nombre entier d\'heures' => [[['2026-11-07', '09:00', '17:00']], '8'],
		'une fin avant le début' => [[['2026-11-07', '09:00', '08:00']], NULL],
		'une fin égale au début' => [[['2026-11-07', '09:00', '09:00']], NULL],
		'une fin avant le début, à côté d\'une séance' => [[['2026-11-07', '09:00', '12:00'], ['2026-11-08', '14:00', '13:00']], '3'],
	];

	/**
	 * Ce que le navigateur lit pendant la saisie et que le serveur ne rend jamais : un champ d'une
	 * séance encore vide, puis l'année en cours de frappe, que le champ rend chiffre après chiffre
	 * (0002, 0020, 0202, 2026) et que ses bornes écartent.
	 */
	const TYPING = [
		'sans le jour' => [[['', '09:30', '17:00']], ''],
		'sans l\'heure du début' => [[['2026-11-07', '', '17:00']], ''],
		'sans l\'heure de la fin' => [[['2026-11-07', '09:30', '']], ''],
		'l\'année à son premier chiffre' => [[['0002-11-07', '09:30', '17:00']], ''],
		'à son deuxième chiffre' => [[['0020-11-07', '09:30', '17:00']], ''],
		'à son troisième chiffre' => [[['0202-11-07', '09:30', '17:00']], ''],
		'l\'année entière' => [[['2026-11-07', '09:30', '17:00']], '7,5'],
		'une seconde séance sans jour' => [[['2026-11-07', '09:30', '17:00'], ['', '09:30', '12:30']], ''],
	];

	public function testSchedulePlaceholder(): void {

		// Au-delà de mille heures, l'espace des milliers : 130 journées de dix heures.
		$schedules = self::SCHEDULES + [
			'au-delà de mille heures' => [
				array_map(fn(int $day) => [date('Y-m-d', strtotime('2026-01-01 + '.$day.' day')), '08:00', '18:00'], range(0, 129)),
				"1\u{202F}300"
			],
		];

		$browser = $this->getBrowserHours(array_map(fn(array $schedule) => $schedule[0], $schedules));

		foreach($schedules as $name => [$sequences, $expected]) {

			$server = $this->getPlaceholder($this->getDate($sequences));

			$this->assertEquals($expected, $server, $name.' : l\'indication du serveur');
			$this->assertEquals($expected ?? '', $browser[$name], $name.' : l\'indication du navigateur');

		}

	}

	public function testScheduleTyping(): void {

		// Les bornes se lisent sur le champ rendu : un cas écrit en dur resterait vert le jour où
		// elles bougent, en éprouvant un jour qui n'est plus à la borne.
		[$min, $max] = $this->getBounds();

		$typing = self::TYPING + [
			'un jour à la borne basse du champ' => [[[$min, '09:30', '17:00']], '7,5'],
			'un jour à la borne haute du champ' => [[[$max, '09:30', '17:00']], '7,5'],
			'un jour après la borne haute du champ' => [[[date('Y-m-d', strtotime($max.' + 1 day')), '09:30', '17:00']], ''],
		];

		$browser = $this->getBrowserHours(array_map(fn(array $case) => $case[0], $typing));

		foreach($typing as $name => [, $expected]) {
			$this->assertEquals($expected, $browser[$name], $name.' : l\'indication du navigateur');
		}

	}

	/**
	 * Toutes les durées d'une séance, à la minute : l'arrondi au centième est le même des deux
	 * côtés, sur une, deux et trois séances.
	 */
	public function testScheduleRounding(): void {

		$cases = [];
		$expected = [];

		foreach([1, 2, 3] as $count) {

			for($minutes = 1; $minutes < 1440; $minutes++) {

				$endTime = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

				$key = $count.' séance(s) '.$endTime;

				$sequences = array_map(fn(int $day) => ['2026-03-0'.$day, '00:00', $endTime], range(1, $count));

				$cases[$key] = $sequences;
				$expected[$key] = DateUi::getHoursPlaceholder($this->getDate($sequences)->getScheduleMinutes());

			}

		}

		$browser = $this->getBrowserHours($cases);

		$different = array_keys(array_diff_assoc($expected, $browser));

		$this->assertEquals(4317, count($expected), 'les 1 439 durées d\'une séance, sur une, deux et trois séances');
		$this->assertEquals([], $different, 'des durées que le navigateur n\'arrondit pas comme le serveur : '.implode(', ', array_slice($different, 0, 10)));

	}

	/**
	 * Chaque horaire recalcule l'indication, que le script retrouve à `data-schedule`.
	 */
	public function testDurationInfo(): void {

		foreach([
			'à l\'ajout' => $this->getFields([]),
			'à la modification' => $this->getFields([['2026-11-07', '09:30', '17:00']]),
		] as $where => $fields) {

			$this->assertEquals(1, preg_match('#<input[^>]*name="minutes"[^>]*>#', $fields, $input), $where.' : le champ de la durée');
			$this->assertTrue(str_contains($input[0] ?? '', ' data-schedule'), $where.' : le script retrouve le champ');
			// Une séance, et le gabarit d'une séance ajoutée.
			$this->assertEquals(6, substr_count($fields, 'oninput="LearningDate.updateSchedule(this)"'), $where.' : le jour, le début et la fin de chaque séance recalculent l\'indication');

		}

	}

	/**
	 * Sous « Site de l'organisme », l'adresse de la page de la formation, échappée ; rien quand la
	 * formation n'a pas de page.
	 */
	public function testExternalUrlInfo(): void {

		$form = new \util\FormUi();
		$after = DateUi::p('externalUrl')->after;

		$eDate = new Date(['course' => new Course(['externalUrl' => 'https://example.org/formation?a=1&b=2'])]);

		$this->assertTrue(str_contains($after($form, $eDate), 'https://example.org/formation?a=1&amp;b=2'), 'l\'adresse, échappée');

		$eDate = new Date(['course' => new Course(['externalUrl' => NULL])]);

		$this->assertEquals('', $after($form, $eDate), 'sans page de la formation, aucune aide');

	}

	/**
	 * La date d'un formulaire, avec ses séances `[jour, début, fin]`. Sans séance, celle qu'une date
	 * neuve propose.
	 */
	private function getDate(array $sequences): Date {

		$eCourse = new Course(['type' => Course::ONSITE]);

		return new Date([
			'course' => $eCourse,
			'cDateSequence' => ($sequences === [])
				? new \Collection([$eCourse->getDefaultSequence()])
				: new \Collection(array_map(fn(array $sequence) => new DateSequence([
					'startAt' => $sequence[0].' '.$sequence[1].':00',
					'endAt' => $sequence[0].' '.$sequence[2].':00',
					'mode' => DateSequence::ONSITE,
					'place' => NULL,
				]), $sequences)),
			'startAt' => NULL,
			'endAt' => NULL,
			'minutes' => NULL,
			'link' => NULL,
			'seats' => NULL
		]);

	}

	private function getFields(array $sequences): string {

		return new DateUi()->getFields(new \util\FormUi(), $this->getDate($sequences));

	}

	/** L'indication du champ de la durée, `NULL` quand les séances n'en donnent pas. */
	private function getPlaceholder(Date $eDate): ?string {

		$minutes = $eDate->getScheduleMinutesProposed();

		return ($minutes === NULL) ? NULL : DateUi::getHoursPlaceholder($minutes);

	}

	/**
	 * Les bornes que le champ du jour porte, `min` et `max`, lues sur le formulaire rendu : le
	 * script les reçoit du champ lui-même, le test ne les recopie pas.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function getBounds(): array {

		$fields = $this->getFields([]);

		$this->assertEquals(1, preg_match('#<input[^>]*name="startAt\[0\]\[date\]"[^>]*>#', $fields, $input), 'le champ du jour de la séance');
		$this->assertEquals(1, preg_match('#\smin="([0-9-]{10})"#', $input[0] ?? '', $min), 'le champ du jour porte une borne basse');
		$this->assertEquals(1, preg_match('#\smax="([0-9-]{10})"#', $input[0] ?? '', $max), 'le champ du jour porte une borne haute');

		return [$min[1] ?? '', $max[1] ?? ''];

	}

	/**
	 * Le script part par l'entrée standard de node, la commande ne se compose que de littéraux
	 * (`ShellCommandSentinel`). Chaque liste de séances part avec les bornes du champ, comme à l'écran.
	 *
	 * @param array<string, array<int, array{0: string, 1: string, 2: string}>> $cases
	 * @return array<string, string>
	 */
	private function getBrowserHours(array $cases): array {

		$script = '
			const vm = require("vm");
			const source = require("fs").readFileSync('.json_encode(__DIR__.'/../static/js/learning.js').', "utf8");
			const context = vm.createContext({});
			vm.runInContext(source + ";globalThis.LearningDate = LearningDate;", context);

			const cases = '.json_encode($cases).';
			const bounds = '.json_encode($this->getBounds()).';
			const result = {};

			Object.entries(cases).forEach(([name, schedule]) => result[name] = context.LearningDate.getScheduleHours(schedule, ...bounds));

			process.stdout.write(JSON.stringify(result));
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

		fwrite($pipes[0], $script);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$result = json_decode($output, TRUE);

		$this->assertTrue(is_array($result), 'node rend son résultat : '.$output.$errors);

		return $result ?? array_fill_keys(array_keys($cases), NULL);

	}

}
?>
