<?php
namespace dev;

/**
 * Taille en octets de chaque CLAUDE.md que git suit : section `## ` par section, préambule compris,
 * quand SIZES déclare un tableau, d'un seul tenant quand elle déclare un entier.
 *
 * Claude Code charge le CLAUDE.md racine dans toutes les sessions du dépôt, et celui d'un
 * sous-répertoire dans toutes celles qui lisent un fichier de ce répertoire, y compris quand la
 * section ne les concerne pas : ce qu'une section gagne, chaque session le paie. La déclaration ne
 * peut que descendre, sur le modèle de `SentinelTest::assertRatchet()`, qui compte des occurrences
 * par ligne et nommerait ici chaque octet.
 *
 * Un CLAUDE.md déclaré d'un seul tenant a un plancher, FLOOR, et tout fichier plus petit s'y déclare :
 * il grandit librement jusque-là, et la déclaration ne réclame d'être abaissée que pour ce qui dépasse
 * le plancher. Sous FLOOR, ce qu'un CLAUDE.md de package coûte aux seules sessions qui lisent ce
 * package ne justifie pas de le figer à sa taille du jour (décidé le 2026-10-10). Une
 * déclaration écrite sous le plancher n'aurait aucun effet, elle échoue donc comme une déclaration
 * fausse. Un fichier déclaré par sections n'a de plancher ni pour lui ni pour elles : on le découpe
 * pour le suivre au plus près, section par section.
 *
 * La liste des fichiers vient de git, pas de SIZES : un CLAUDE.md suivi et non déclaré échoue, comme
 * une déclaration dont le fichier a disparu. Un CLAUDE.md que git ne suit pas encore n'est pas
 * confronté (`SentinelLib::getFiles()` dit pourquoi), mais sa déclaration se mesure déjà.
 *
 * Lancement : dev/test package=dev file=ClaudeSizeSentinel
 */
class ClaudeSizeSentinelTest extends \Test {

	const PREAMBLE = '(préambule)';
	const WHOLE = '(fichier entier)';
	const FLOOR = 5000;

	const SIZES = [
		'CLAUDE.md' => [
			self::PREAMBLE => 1207,
			'Comportement' => 2360,
			'Environnement de dev' => 2778,
			'Architecture' => 4919,
			'Commentaires' => 2064,
			'Style PHP' => 5519,
			'Validation et `build()`' => 3836,
			'Gestion des erreurs' => 1748,
			'Conventions ORM' => 9168,
			'Schémas `.yml`' => 1244,
			'Sécurité multi-tenant' => 1694,
			'Tests' => 965,
			'Tests-sentinelles' => 857,
			'Pièges techniques connus' => 1824,
			'Rédaction (doc, UI, contenu public)' => 1485,
			'CSS et maquettes' => 1725,
			'C\'est pas comme d\'habitude' => 419,
		],
		'accounting/help/CLAUDE.md' => self::FLOOR,
		'base/block/CLAUDE.md' => 5009,
		'base/dashboard/CLAUDE.md' => 19604,
		'base/notification/CLAUDE.md' => 10345,
		'base/scene/CLAUDE.md' => 5279,
		'base/structure/CLAUDE.md' => 7123,
		'commercialisation/external/CLAUDE.md' => self::FLOOR,
		'commercialisation/selling/CLAUDE.md' => self::FLOOR,
		'commercialisation/shop/CLAUDE.md' => self::FLOOR,
		'framework/dev/test/CLAUDE.md' => 8319,
		'production/toolbox/CLAUDE.md' => 7851,
	];

	public function testDetectors(): void {

		$sections = $this->measure("# Titre\n\nA\n## Un\nBé\n## Deux\n\n## Un\nC\n");

		$this->assertEquals([
			[self::PREAMBLE, 11],
			['Un', 10],
			['Deux', 9],
			['Un', 8],
		], $sections, 'préambule et sections, titre compris, octets et non caractères');

		$result = $this->compareSizes(
			'paquet/CLAUDE.md',
			[self::PREAMBLE => 5, 'Un' => 10, 'Deux' => 10, 'Trois' => 1],
			[self::PREAMBLE => 6, 'Un' => 10, 'Deux' => 8, 'Quatre' => 3]
		);

		$this->assertEquals([
			'grown' => ['paquet/CLAUDE.md « '.self::PREAMBLE.' » (6 mesurés pour 5 déclarés)'],
			'lowered' => ['paquet/CLAUDE.md « Deux » (10 déclarés pour 8 mesurés)'],
			'appeared' => ['paquet/CLAUDE.md « Quatre » (3 mesurés)'],
			'disappeared' => ['paquet/CLAUDE.md « Trois » (1 déclarés)'],
			'belowFloor' => [],
		], $result, 'chaque écart dans son sens, nommé par son fichier, la section égale à sa déclaration tue');

		$none = [
			'grown' => [],
			'lowered' => [],
			'appeared' => [],
			'disappeared' => [],
			'belowFloor' => [],
		];

		$this->assertEquals($none, $this->compareSizes('paquet/CLAUDE.md', [self::WHOLE => 5000], [self::WHOLE => 1200]), 'un fichier d\'un seul tenant sous le plancher ne réclame pas d\'abaisser sa déclaration');
		$this->assertEquals($none, $this->compareSizes('paquet/CLAUDE.md', [self::WHOLE => 5000], [self::WHOLE => 5000]), 'un fichier d\'un seul tenant grandit jusqu\'au plancher compris');

		$this->assertEquals([
			'grown' => ['paquet/CLAUDE.md (5001 mesurés pour 5000 déclarés)'],
			'lowered' => [],
			'appeared' => [],
			'disappeared' => [],
			'belowFloor' => [],
		], $this->compareSizes('paquet/CLAUDE.md', [self::WHOLE => 5000], [self::WHOLE => 5001]), 'un fichier d\'un seul tenant qui dépasse le plancher a grossi, et se nomme sans section');

		$this->assertEquals([
			'grown' => [],
			'lowered' => ['paquet/CLAUDE.md (6000 déclarés pour 5500 mesurés)', 'paquet/CLAUDE.md (6000 déclarés pour 1200 mesurés)'],
			'appeared' => [],
			'disappeared' => [],
			'belowFloor' => [],
		], array_merge_recursive(
			$this->compareSizes('paquet/CLAUDE.md', [self::WHOLE => 6000], [self::WHOLE => 5500]),
			$this->compareSizes('paquet/CLAUDE.md', [self::WHOLE => 6000], [self::WHOLE => 1200])
		), 'un fichier d\'un seul tenant au-dessus du plancher qui maigrit réclame toujours d\'abaisser sa déclaration, jusqu\'au plancher');

		$this->assertEquals([
			'grown' => [],
			'lowered' => [],
			'appeared' => [],
			'disappeared' => [],
			'belowFloor' => ['paquet/CLAUDE.md (1207 déclarés pour un plancher de 5000)'],
		], $this->compareSizes('paquet/CLAUDE.md', [self::WHOLE => 1207], [self::WHOLE => 1207]), 'une déclaration d\'un seul tenant sous le plancher, qui n\'aurait aucun effet, échoue même quand elle égale la mesure');

		$this->assertEquals($none, $this->compareSizes('paquet/CLAUDE.md', ['Un' => 1207], ['Un' => 1207]), 'une section n\'a pas de plancher');

		$this->assertEquals([
			'appeared' => ['paquet/neuf/CLAUDE.md'],
			'missing' => ['paquet/parti/CLAUDE.md'],
		], $this->compareFiles(
			['CLAUDE.md', 'paquet/parti/CLAUDE.md', 'paquet/local/CLAUDE.md'],
			['CLAUDE.md', 'paquet/neuf/CLAUDE.md'],
			['CLAUDE.md', 'paquet/local/CLAUDE.md']
		), 'le fichier suivi et non déclaré, la déclaration sans fichier, et rien pour une déclaration dont le fichier existe sans être encore suivi');

	}

	public function testSize(): void {

		$root = SentinelLib::getRoot();
		$tracked = SentinelLib::getFiles('CLAUDE.md');
		$present = array_values(array_filter(array_keys(self::SIZES), fn(string $file) => is_file($root.'/'.$file)));

		$files = $this->compareFiles(array_keys(self::SIZES), array_keys($tracked), $present);

		$duplicates = [];
		$result = [
			'grown' => [],
			'lowered' => [],
			'appeared' => [],
			'disappeared' => [],
			'belowFloor' => [],
		];

		foreach(self::SIZES as $file => $declared) {

			if(in_array($file, $present, TRUE) === FALSE) {
				continue;
			}

			$content = file_get_contents($root.'/'.$file);

			if(is_int($declared)) {
				$result = array_merge_recursive($result, $this->compareSizes($file, [self::WHOLE => $declared], [self::WHOLE => strlen($content)]));
				continue;
			}

			$sizes = [];

			foreach($this->measure($content) as [$name, $bytes]) {

				if(array_key_exists($name, $sizes)) {
					$duplicates[] = $file.' « '.$name.' »';
				}

				$sizes[$name] = ($sizes[$name] ?? 0) + $bytes;

			}

			$result = array_merge_recursive($result, $this->compareSizes($file, $declared, $sizes));

		}

		$appeared = array_map(fn(string $file) => $file.' ('.filesize($tracked[$file]).' mesurés)', $files['appeared']);

		$this->assertEquals([], $appeared, 'CLAUDE.md suivi par git et absent de SIZES, la déclaration se corrige : l\'y ajouter d\'un seul tenant, à self::FLOOR, ou à sa taille mesurée si elle le dépasse : '.implode(' · ', $appeared));
		$this->assertEquals([], $files['missing'], 'CLAUDE.md déclaré dans SIZES et absent du dépôt, la déclaration se corrige : retirer son entrée, ou la déplacer avec le fichier : '.implode(' · ', $files['missing']));
		$this->assertEquals([], $duplicates, 'titre de section en double, que la déclaration ne peut pas séparer, le CLAUDE.md se corrige en renommant l\'une des deux : '.implode(' · ', $duplicates));

		$this->assertEquals([], $result['grown'], 'CLAUDE.md ou section qui a grossi, le CLAUDE.md se corrige et non la déclaration : le récit va dans .claude/cas-vus/, une règle qui se lit dans le code va dans une sentinelle ; une règle qui doit vivre dans le CLAUDE.md relève la déclaration dans le même diff, soumis à validation : '.implode(' · ', $result['grown']));
		$this->assertEquals([], $result['lowered'], 'CLAUDE.md ou section qui a maigri, la déclaration se corrige : abaisser SIZES au nombre mesuré, ou à self::FLOOR pour un fichier d\'un seul tenant passé dessous : '.implode(' · ', SentinelLib::explainStale($result['lowered'])));
		$this->assertEquals([], $result['appeared'], 'section d\'un CLAUDE.md absente de SIZES, la déclaration se corrige : l\'y ajouter à sa taille mesurée, à sa place dans l\'ordre du fichier, et retirer d\'abord l\'ancien nom si c\'est un renommage : '.implode(' · ', $result['appeared']));
		$this->assertEquals([], $result['belowFloor'], 'CLAUDE.md d\'un seul tenant déclaré sous le plancher, une déclaration sans effet, la déclaration se corrige : la porter à self::FLOOR : '.implode(' · ', $result['belowFloor']));
		$this->assertEquals([], $result['disappeared'], 'section déclarée dans SIZES et absente de son CLAUDE.md, la déclaration se corrige : retirer sa ligne, et si elle est renommée, déclarer le nouveau nom à une taille qui ne dépasse pas l\'ancienne : '.implode(' · ', $result['disappeared']));

	}

	/**
	 * @return array<int, array{0: string, 1: int}>
	 */
	private function measure(string $content): array {

		$sections = [[self::PREAMBLE, 0]];

		foreach(preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY) as $line) {

			if(str_starts_with($line, '## ')) {
				$sections[] = [rtrim(substr($line, 3)), 0];
			}

			$sections[array_key_last($sections)][1] += strlen($line);

		}

		return $sections;

	}

	/**
	 * @param string[] $declared les fichiers de SIZES
	 * @param string[] $tracked les CLAUDE.md que git suit
	 * @param string[] $present les fichiers de SIZES qui existent dans l'arbre
	 * @return array{appeared: string[], missing: string[]}
	 */
	private function compareFiles(array $declared, array $tracked, array $present): array {

		return [
			'appeared' => array_values(array_diff($tracked, $declared)),
			'missing' => array_values(array_diff($declared, $tracked, $present)),
		];

	}

	/**
	 * @param array<string, int> $declared
	 * @param array<string, int> $measured
	 * @return array{grown: string[], lowered: string[], appeared: string[], disappeared: string[], belowFloor: string[]}
	 */
	private function compareSizes(string $file, array $declared, array $measured): array {

		$result = [
			'grown' => [],
			'lowered' => [],
			'appeared' => [],
			'disappeared' => [],
			'belowFloor' => [],
		];

		foreach($measured as $name => $bytes) {

			$section = ($name === self::WHOLE) ? $file : $file.' « '.$name.' »';
			$floor = ($name === self::WHOLE) ? self::FLOOR : 0;

			if(array_key_exists($name, $declared) === FALSE) {
				$result['appeared'][] = $section.' ('.$bytes.' mesurés)';
			} else if($declared[$name] < $floor) {
				$result['belowFloor'][] = $section.' ('.$declared[$name].' déclarés pour un plancher de '.$floor.')';
			} else if($bytes > $declared[$name]) {
				$result['grown'][] = $section.' ('.$bytes.' mesurés pour '.$declared[$name].' déclarés)';
			} else if(max($bytes, $floor) < $declared[$name]) {
				$result['lowered'][] = $section.' ('.$declared[$name].' déclarés pour '.$bytes.' mesurés)';
			}

		}

		foreach($declared as $name => $bytes) {

			if(array_key_exists($name, $measured) === FALSE) {
				$result['disappeared'][] = $file.' « '.$name.' » ('.$bytes.' déclarés)';
			}

		}

		return $result;

	}

}
?>
