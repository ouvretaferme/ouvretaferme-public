<?php
namespace entry;

/**
 * Le moteur du champ de saisie : du texte vers une réponse, puis de la réponse vers une écriture.
 *
 * `read()` ne prend que du texte, et c'est voulu : les jetons que l'écran pose ne sont qu'une aide à
 * la frappe, la même phrase tapée d'un trait doit rendre la même réponse (`Grammar.t.php`). Une
 * route vocale pourra donc l'appeler telle quelle.
 *
 * La réponse est un tableau aux clés figées par `Response.t.php` :
 * - `intention` : la clé de l'intention trouvée, `NULL` sinon ;
 * - `object` : l'élément que l'intention a reconnu (une espèce pour la récolte), vide s'il n'est pas
 *   encore nommé, `NULL` tant qu'aucune intention n'est trouvée, faute de savoir de quel objet il
 *   s'agirait ;
 * - `named` : les mots de la phrase qui ont nommé l'objet, découpés comme `split()`, `NULL` tant qu'il
 *   n'est pas nommé. L'écran les retire du champ quand un jeton de série prend la place de l'espèce ;
 * - `cultivation` : la culture retenue, vide tant qu'elle ne l'est pas ;
 * - `task` : l'intervention retenue pour un temps seul (`TimeIntentionLib`) ou pour la coche
 *   (`DoneIntentionLib`), vide tant qu'elle ne l'est pas. Une coche qui demande encore (`ASK`) sans poser de
 *   question porte ici l'intervention déjà faite ce jour-là : il n'y a plus rien à cocher ;
 * - `date` : le jour de l'écriture, aujourd'hui ou celui que la phrase dit (`DateComplementLib`) ;
 * - `mode` : `add`, ajouter à ce qui est déjà écrit ;
 * - `complements` : la valeur de chaque complément inscrit, `NULL` s'il n'a rien lu ;
 * - `complementsNamed` : par complément, les mots de la phrase qu'il a lus, découpés comme `split()`, aucun
 *   s'il n'a rien lu. L'écran les retire du champ quand une puce dit le complément à leur place (`EntryField.pick()`) ;
 * - `question` : l'unique question posée, `NULL` sinon ;
 * - `confirmation` : ce que l'écriture fera, en données, `NULL` tant qu'elle ne peut pas se faire ;
 * - `outcome` : `write`, `ask`, `refuse` ou `navigate` ;
 * - `lines` : les lignes que l'intention lit elle-même après son objet, une par article d'une vente,
 *   vide pour une intention qui n'en lit pas ;
 * - `couple` : la variété et le calibre qu'une récolte lit après son espèce (`HarvestIntentionLib::readQualifiers()`),
 *   `NULL` pour une autre intention ou tant que l'espèce n'est pas posée ;
 * - `pending` : les mots que rien n'a réclamés, `NULL` s'il n'en reste aucun. Avant l'objet, ils
 *   servent à le chercher ; après, ils sont refusés, sauf s'ils cherchent encore une ligne ou une
 *   espèce (`IntentionLib::isSearching()`) ;
 * - `typing` : le mot en cours de frappe, découpé comme `split()`, `NULL` quand la phrase finit par une
 *   espace ;
 * - `blank` : la phrase n'a aucun mot, ni tapé ni posé par un jeton ;
 * - `refusal` : la raison du refus, `NULL` sinon, en données (`['reason' => …]`).
 */
class EntryLib {

	use \Notifiable;

	const string WRITE = 'write';
	const string ASK = 'ask';
	const string REFUSE = 'refuse';
	const string NAVIGATE = 'navigate';

	const string ADD = 'add';

	/**
	 * Le numéro de la lecture en cours : une phrase sans verbe se relit par chaque intention, et ce qu'elles
	 * partagent ne vaut que pour cette lecture (`getRead()`).
	 */
	private static int $read = 0;

	/**
	 * Les intentions, par clé : celles de `EntrySetting::INTENTIONS`, puis celles que les packages fermés
	 * déclarent par leur observateur. Sans eux, personne ne répond : la garde est
	 * `hasCommercialisationApp()`, jamais la présence d'un observateur. Une clé publique l'emporte sur une
	 * clé déclarée (`IntentionSentinel.t.php` refuse qu'elles se croisent).
	 */
	public static function getIntentions(): array {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return EntrySetting::INTENTIONS;
		}

		return EntrySetting::INTENTIONS + array_merge(...self::notify('getIntentions'));

	}

	/**
	 * Les intentions que le membre connecté peut écrire dans cette structure, les seules que le champ lit.
	 */
	public static function getAllowed(\structure\Structure $eStructure): array {
		return array_filter(self::getIntentions(), fn(string $intention) => $intention::canWrite($eStructure));
	}

	/**
	 * La case d'un jour de récolte d'où part la phrase (`EntryUi::getHarvestCell()`) : sa culture, son jour et son
	 * couple variété · calibre, `NULL` hors d'une case. Le navigateur les poste : la culture doit être de la
	 * structure, et le couple passe par `build()` sur la sonde de la saisie à la semaine, avec les vérifications
	 * du panneau de récolte (`HarvestWeekLib::getCoupleProbe()`). Les noms du couple sont ceux que la ligne qui écrit dit.
	 * `part` dit la case d'une part, dont le couple est fixé même vide.
	 */
	public static function getCell(\structure\Structure $eStructure, array $cell): ?array {

		if($cell === []) {
			return NULL;
		}

		$cell += ['cultivation' => NULL, 'date' => NULL, 'variety' => NULL, 'harvestSize' => NULL, 'part' => NULL];

		if(
			\Filter::check('int32', $cell['cultivation']) === FALSE or
			\Filter::check('date', $cell['date']) === FALSE or
			in_array($cell['part'], [NULL, '1'], TRUE) === FALSE
		) {
			throw new \NotExpectedAction('Invalid cell');
		}

		$eCultivation = \series\CultivationLib::getById($cell['cultivation'], ['id', 'structure', 'plant'])->validate();
		$eCultivation->validateProperty('structure', $eStructure);

		$fw = new \FailWatch();

		$eTaskCouple = \planning\HarvestWeekLib::getCoupleProbe($eStructure, $eCultivation);
		$eTaskCouple->build(['variety', 'harvestSize'], $cell);

		$fw->validate();

		return [
			'eCultivation' => $eCultivation,
			'date' => $cell['date'],
			'part' => ($cell['part'] === '1'),
			'eVariety' => $eTaskCouple['variety']->empty() ? new \plant\Variety() : \plant\VarietyLib::getById($eTaskCouple['variety']['id'], ['id', 'name']),
			'eSize' => $eTaskCouple['harvestSize']->empty() ? new \plant\Size() : \plant\SizeLib::getById($eTaskCouple['harvestSize']['id'], ['id', 'name']),
		];

	}

	public static function getRead(): int {
		return self::$read;
	}

	/**
	 * Lit une phrase.
	 *
	 * `$answers` porte ce que l'écran a déjà répondu à une question (`cultivation`, l'id de la culture
	 * choisie ; `all`, l'élargissement à toutes les séries ; `task`, l'id de l'intervention choisie pour un
	 * temps ou pour la coche ; `addCultivation`, l'id de la culture sur laquelle ajouter au planning l'intervention qu'un temps
	 * ne trouve pas, et `addNoSeries`, l'id de l'espèce pour son ajout hors série ; `lines`, les ids que portent les
	 * jetons des lignes, dans l'ordre des lignes, l'objet choisi dans la liste compris ; `recap`, le récapitulatif
	 * ouvert avant d'écrire, et `status`, l'état que la vente y a reçu) : une réponse n'est pas un mot de la phrase.
	 * `cell` est la case d'un jour de récolte d'où part la phrase (`getCell()`), qui dit la série, le jour et le
	 * couple sans qu'un mot les nomme.
	 * `$cElement` rend les objets de l'intention, que l'écran propose tant que l'objet n'est pas nommé :
	 * les relire coûterait une seconde fois les requêtes de la lecture.
	 */
	public static function read(\structure\Structure $eStructure, string $text, array $answers = [], \Collection &$cElement = new \Collection()): array {

		self::$read++;

		$answers += ['cell' => NULL];

		return self::readText($eStructure, $text, $answers, $cElement, FALSE);

	}

	/**
	 * `$typingNamed` dit que l'objet est nommé par d'autres mots que celui en cours de frappe, même si
	 * seul un complément le fait trouver : ce mot va alors aux compléments.
	 */
	private static function readText(\structure\Structure $eStructure, string $text, array $answers, \Collection &$cElement, bool $typingNamed): array {

		$answers += ['cultivation' => NULL, 'all' => FALSE, 'task' => NULL, 'addCultivation' => NULL, 'addNoSeries' => NULL, 'lines' => [], 'recap' => FALSE, 'status' => NULL, 'days' => []];

		$reading = self::getEmptyReading();

		$words = self::split($text);
		$typing = self::getTyping($text, $words);

		$reading['typing'] = ($typing === NULL) ? NULL : $words[$typing];
		$reading['blank'] = ($words === []);

		$intentions = self::getAllowed($eStructure);

		$wordsVerb = $words;

		$reading['intention'] = self::readIntention($intentions, $words);
		$reading['verb'] = array_values(array_diff_key($wordsVerb, $words));

		// La case d'un jour de récolte dit le verbe et le jour : la phrase y est toujours une récolte, lue au jour de
		// la case. Un autre verbe se refuse une fois l'objet posé, que l'écran du refus lit.
		$verbOther = FALSE;

		if($answers['cell'] !== NULL) {

			$verbOther = ($reading['intention'] !== NULL and $reading['intention'] !== 'harvest');

			$reading['intention'] = 'harvest';
			$reading['date'] = $answers['cell']['date'];

		}

		$cElement = new \Collection();

		if($reading['intention'] === NULL) {

			$readingImplicit = ($words === []) ? NULL : self::readImplicit($eStructure, $text, $answers, $cElement);

			if($readingImplicit !== NULL) {
				return $readingImplicit;
			}

			$reading['pending'] = self::getPending($words);
			$reading['outcome'] = self::ASK;
			return $reading;

		}

		$intention = $intentions[$reading['intention']];

		// Posté pour une intention sans récapitulatif, `recap` ne dit rien.
		$reading['recap'] = ($answers['recap'] and $intention::hasRecap());

		$cElement = $intention::getObjects($eStructure, $reading);

		$wordsObject = $words;

		$reading['object'] = $intention::readObject($cElement, $words);

		if($reading['object']->notEmpty()) {
			$reading['named'] = array_values(array_diff_key($wordsObject, $words));
		}

		// La case lit l'espèce de sa série, nommée ou non. Une autre espèce nommée ne s'écrirait pas sur cette série.
		if($answers['cell'] !== NULL) {

			$plant = $answers['cell']['eCultivation']['plant']['id'];

			if($verbOther or ($reading['object']->notEmpty() and $reading['object']['id'] !== $plant)) {
				self::refuse($reading, ['reason' => 'cell']);
				return $reading;
			}

			$refusal = $intention::getDateRefusal($reading['date']);

			if($refusal !== NULL) {
				self::refuse($reading, $refusal);
				return $reading;
			}

			if($cElement->offsetExists($plant) === FALSE) {
				self::refuse($reading, ['reason' => 'cellSeries']);
				return $reading;
			}

			$reading['object'] = $cElement[$plant];

		}

		if($reading['object']->notEmpty()) {
			$intention::readQualifiers($eStructure, $reading, $words, $cElement);
		}

		$wordsLinked = $words;

		// Les liaisons ne se retirent qu'après l'objet, dont le nom peut en porter une (« Pomme de terre »).
		// Tant que l'objet n'est pas nommé, le mot en cours de frappe reste le début de son nom, même quand il
		// s'écrit comme une liaison : « des » commence Désherbage.
		$typingObject = $reading['object']->empty() ? $typing : NULL;

		$words = array_filter($words, fn(string $word, int $position) => ($position === $typingObject or in_array($word, EntrySetting::LINKS, TRUE) === FALSE), ARRAY_FILTER_USE_BOTH);

		// Tant que l'objet n'est pas nommé, le mot en cours de frappe en est le début : aucun complément
		// ne le réclame, sinon « b » deviendrait des bottes et ne filtrerait plus les espèces.
		$typingComplement = ($reading['object']->notEmpty() or $typingNamed) ? $typing : NULL;

		$complements = self::getComplements($reading['intention']);

		$wordsRead = $words;

		self::readComplements($reading, $words, $complements, $typingComplement);

		$reading['typingPrefix'] = self::isTypingPrefix($wordsRead, $words, $typingComplement, $complements, $reading['intention']);

		$reading['pending'] = self::getPending($words);

		$date = $reading['date'];

		foreach($complements as $key => $complement) {
			$complement::apply($reading, $reading['complements'][$key]);
		}

		if($reading['refusal'] !== NULL) {
			return $reading;
		}

		if($answers['cell'] !== NULL and $reading['date'] !== $answers['cell']['date']) {
			self::refuse($reading, ['reason' => 'cellDate']);
			return $reading;
		}

		// Les objets ont été lus à la date du jour, avant qu'un complément n'en dise une autre ou ne les
		// restreigne : ce que l'écran propose et ce que l'intention résout (la phase, la saison, le récolté
		// du jour) se relisent à la date dite, avec les compléments qui les restreignent. Les compléments,
		// eux, ne dépendent pas de la date.
		$narrowing = array_filter($complements, fn(string $complement, string $key) => $complement::narrowsObjects($reading['complements'][$key]), ARRAY_FILTER_USE_BOTH);

		if($reading['date'] !== $date or $narrowing !== []) {

			$refusal = ($reading['date'] !== $date) ? $intention::getDateRefusal($reading['date']) : NULL;

			if($refusal !== NULL) {
				self::refuse($reading, $refusal);
				return $reading;
			}

			$cElement = $intention::getObjects($eStructure, $reading, $cElement);

			if($reading['object']->notEmpty()) {

				// Le refus nomme ce qui a écarté l'objet : le complément qui restreint, sinon le jour dit.
				if($cElement->offsetExists($reading['object']['id']) === FALSE) {

					if($narrowing === []) {
						self::refuse($reading, ['reason' => 'dated', 'object' => $reading['object']]);
					} else {
						$key = array_key_first($narrowing);
						self::refuse($reading, ['reason' => $key, 'object' => $reading['object'], 'value' => $reading['complements'][$key]]);
					}

					return $reading;

				}

				$reading['object'] = $cElement[$reading['object']['id']];

			} else {

				// Un nom absent des objets du jour peut désigner un objet du jour dit ou de la saison dite, une
				// série de la saison passée. Les liaisons restent parmi les mots, un nom pouvant en porter une (« Pomme de terre »).
				$wordsObject = array_filter($wordsLinked, fn(string $word, int $position) => (array_key_exists($position, $words) or in_array($word, EntrySetting::LINKS, TRUE)), ARRAY_FILTER_USE_BOTH);
				$wordsLeft = $wordsObject;

				$reading['object'] = $intention::readObject($cElement, $wordsLeft);

				if($reading['object']->notEmpty()) {

					$reading['named'] = array_values(array_diff_key($wordsObject, $wordsLeft));

					// Le mot en cours de frappe a été pris pour le début de l'objet, qui manquait, et aucun complément
					// ne l'a lu (`$typingComplement`). L'objet nommé sans lui par le jour ou la saison dite, la phrase se
					// relit en le donnant aux compléments, par tout le chemin de la lecture : « hi », début de « hier »,
					// change le jour, et « k » l'unité, comme sur « courgette 3 hi » ou « courgette 3 k ».
					if($typing !== NULL and $typingNamed === FALSE and array_key_exists($typing, $wordsLeft)) {
						return self::readText($eStructure, $text, $answers, $cElement, TRUE);
					}

					// Les compléments ont lu la phrase avant que l'objet ne soit nommé, et un nom de variété à chiffre
					// (« Détroit 2 ») leur a donné son nombre. Les qualificatifs se lisent donc sur toute la phrase, les
					// mots de l'objet retirés, et les compléments se relisent sur ce qu'ils laissent, comme quand
					// l'objet est nommé d'emblée.
					$wordsQualified = array_diff_key($wordsLinked, array_diff_key($wordsObject, $wordsLeft));

					$intention::readQualifiers($eStructure, $reading, $wordsQualified, $cElement);

					$wordsQualified = self::withoutLinks($wordsQualified);

					$reading['complements'] = self::getEmptyReading()['complements'];

					$wordsRead = $wordsQualified;

					self::readComplements($reading, $wordsQualified, $complements, $typingComplement);

					$reading['typingPrefix'] = self::isTypingPrefix($wordsRead, $wordsQualified, $typingComplement, $complements, $reading['intention']);

					foreach($complements as $key => $complement) {
						$complement::apply($reading, $reading['complements'][$key]);
					}

					if($reading['refusal'] !== NULL) {
						return $reading;
					}

					$reading['pending'] = self::getPending($wordsQualified);

				}

			}

		}

		if($reading['object']->empty()) {

			$reading['outcome'] = self::ASK;

			if(
				$reading['pending'] !== NULL and
				self::findObjects($cElement, $reading['pending'])->empty()
			) {

				// Le verbe d'une intention qui ne se devine pas peut appartenir au nom d'un objet d'une autre :
				// « vente directe 1 h » est un temps sur l'action Vente directe. Quand la phrase ne nomme aucun
				// de ses objets, la lecture sans verbe dont l'objet porte ce mot l'emporte, si elle lit tous les mots :
				// « vente Riom 10 », d'un client inactif, ne se lit pas en un temps sur l'action Vente qui refuse le
				// reste de la phrase.
				if($intention::isExplicit()) {

					$cElementImplicit = new \Collection();
					$readingImplicit = self::readImplicit($eStructure, $text, $answers, $cElementImplicit);

					if(
						$readingImplicit !== NULL and
						($readingImplicit['refusal']['reason'] ?? NULL) !== 'unclaimed' and
						$readingImplicit['named'] !== NULL and
						array_intersect($readingImplicit['named'], $intention::getWords()) !== []
					) {
						$cElement = $cElementImplicit;
						return $readingImplicit;
					}

				}

				self::refuse($reading, ['reason' => 'object']);

			}

			return $reading;

		}

		$intention::resolve($eStructure, $reading, $answers);

		if($reading['outcome'] === self::WRITE) {

			foreach($complements as $key => $complement) {

				// Le récapitulatif reste sur le jour qu'il montrait pendant qu'un autre se tape (`DateComplementLib`) :
				// la liste dit ce qui s'écrirait, et c'est ce qui s'écrit.
				if($reading['recap'] and $complement === DateComplementLib::class) {
					continue;
				}

				if($complement::isComplete($reading['complements'][$key]) === FALSE) {
					$reading['confirmation'] = NULL;
					$reading['outcome'] = self::ASK;
					break;
				}

			}

		}

		// Un mot que personne n'a réclamé l'emporte sur tout ce que la résolution a décidé : la phrase
		// dit quelque chose que le champ ne comprend pas, rien ne doit s'écrire à sa place.
		if($reading['pending'] !== NULL and $intention::isSearching($reading) === FALSE) {
			self::refuse($reading, ['reason' => 'unclaimed', 'vocabulary' => self::getVocabulary($reading['intention'])]);
		}

		return $reading;

	}

	public static function getEmptyReading(): array {

		return [
			'intention' => NULL,
			// Les mots qui ont dit le verbe, tapés ou portés par son jeton, vides quand il se devine (`readImplicit()`).
			'verb' => [],
			'object' => NULL,
			'named' => NULL,
			// L'espèce qu'une intention lit après son objet, quand l'objet n'en dit pas (« temps amendement carotte »),
			// et les mots qui l'ont nommée.
			'plant' => new \plant\Plant(),
			'plantNamed' => [],
			'cultivation' => new \series\Cultivation(),
			'task' => new \series\Task(),
			'date' => \Clock::date(),
			'mode' => self::ADD,
			'complements' => array_fill_keys(array_keys(EntrySetting::COMPLEMENTS), NULL),
			'complementsNamed' => array_fill_keys(array_keys(EntrySetting::COMPLEMENTS), []),
			'lines' => [],
			'couple' => NULL,
			// Les puces qui précisent ce que la phrase attend, de la question qu'elle pose à la ligne qui écrit :
			// les unités d'une récolte, les jours de la semaine d'un temps.
			'units' => [],
			'days' => [],
			'question' => NULL,
			'confirmation' => NULL,
			'outcome' => self::ASK,
			'pending' => NULL,
			'typing' => NULL,
			'typingPrefix' => FALSE,
			'blank' => TRUE,
			// La lecture est-elle demandée depuis le récapitulatif ? Elle le dit avant d'aboutir, pendant qu'un mot
			// se tape encore et que rien ne s'écrirait.
			'recap' => FALSE,
			'refusal' => NULL,
			// Les lectures sans verbe que la précédence a écartées sur le même objet, et qui ont encore quelque chose
			// à écrire, par intention (`readImplicit()`).
			'discarded' => array_fill_keys(array_keys(EntrySetting::INTENTIONS), NULL),
		];

	}

	/**
	 * Les mots d'une phrase, sans accent et en minuscules, un nombre collé à son unité décollé
	 * (« 12kg »), l'élision détachée (« d'ail »).
	 */
	public static function split(string $text): array {

		$text = mb_strtolower($text);
		$text = \Normalizer::normalize($text, \Normalizer::FORM_D);
		$text = preg_replace('/\p{Mn}/u', '', $text);
		$text = str_replace(['’', '‘'], '\'', $text);
		$text = preg_replace('/\bd\'/u', 'd\' ', $text);
		$text = preg_replace('/([0-9])([a-z])/u', '$1 $2', $text);

		$words = preg_split('/\s+/u', trim($text), flags: PREG_SPLIT_NO_EMPTY);

		return $words;

	}

	/**
	 * La position du mot en cours de frappe : le dernier, tant que la phrase ne se termine pas par
	 * une espace.
	 */
	public static function getTyping(string $text, array $words): ?int {

		if($words === [] or preg_match('/\s$/u', $text) === 1) {
			return NULL;
		}

		return array_key_last($words);

	}

	/**
	 * Les compléments d'une intention, dans l'ordre du registre : ceux qui s'accrochent à elle, et ceux
	 * qu'elle dit lire (`IntentionLib::getComplements()`).
	 */
	public static function getComplements(string $intention): array {

		$complementsRead = self::getIntentions()[$intention]::getComplements();

		return array_filter(
			EntrySetting::COMPLEMENTS,
			fn(string $complement, string $key) => (in_array($intention, $complement::getIntentions(), TRUE) or in_array($key, $complementsRead, TRUE)),
			ARRAY_FILTER_USE_BOTH
		);

	}

	/**
	 * Les objets dont le nom commence par un mot en attente, ou dont un des mots commence par lui :
	 * « courg » trouve Courgette, « terre » trouve Pomme de terre. `$start` ne garde que les premiers.
	 */
	public static function findObjects(\Collection $cElement, ?string $pending, bool $start = FALSE): \Collection {

		if($pending === NULL) {
			return $cElement;
		}

		$search = implode(' ', self::split($pending));

		return $cElement->find(function(\Element $eElement) use($search, $start) {

			$name = implode(' ', self::split($eElement['name']));

			if(str_starts_with($name, $search)) {
				return TRUE;
			}

			if($start) {
				return FALSE;
			}

			foreach(explode(' ', $name) as $word) {
				if(str_starts_with($word, $search)) {
					return TRUE;
				}
			}

			return FALSE;

		});

	}

	/**
	 * La fin du mot en cours de frappe, quand tous les objets proposés le continuent de la même façon :
	 * « fertili » devant « Fertilisation courgette » et « Fertilisation laitue » se complète en
	 * « fertilisation », pour passer au mot suivant. Rien quand ils divergent, ni tant qu'aucun objet
	 * n'est cherché. `$cElement` arrive filtré par les mots en attente (`findObjects()`).
	 *
	 * Rend le mot tel que le nom l'écrit (`word`) et ce qu'il reste à en taper (`suffix`).
	 */
	public static function getCompletion(string $text, array $reading, \Collection $cElement): ?array {

		if(
			$reading['intention'] === NULL or
			$reading['refusal'] !== NULL or
			$reading['object']->notEmpty() or
			$reading['pending'] === NULL
		) {
			return NULL;
		}

		$words = self::split($text);
		$typing = self::getTyping($text, $words);
		$search = self::split($reading['pending']);

		// Le mot en frappe a pu être lu par un complément (« 1 » de « laitue 1 ») : il n'est plus à compléter.
		if($typing === NULL or end($search) !== $words[$typing]) {
			return NULL;
		}

		$intention = self::getIntentions()[$reading['intention']];

		// Une copie : `getListed()` peut rendre la collection reçue, que l'ajout modifierait.
		$cElementCompleted = clone $intention::getListed($cElement);
		$cElementCompleted->appendCollection(self::findObjects($intention::getTyped($cElement), $reading['pending'], start: TRUE));

		$completions = [];

		foreach($cElementCompleted as $eElement) {
			$completions[] = self::getWordCompletion($eElement['name'], $search);
		}

		$completions = array_values(array_unique($completions, SORT_REGULAR));

		if(count($completions) !== 1) {
			return NULL;
		}

		return $completions[0];

	}

	/**
	 * Le mot du nom que le dernier mot en attente commence, et ce qu'il en reste à taper. `NULL` quand ce
	 * mot est déjà entier, ou quand `split()` le coupe (« 12kg ») : sa fin ne se retrouve plus à la même
	 * place dans le nom écrit.
	 */
	private static function getWordCompletion(string $name, array $search): ?array {

		$words = [];
		$written = [];

		foreach(preg_split('/\s+/u', $name, flags: PREG_SPLIT_NO_EMPTY) as $word) {

			$pieces = self::split($word);

			foreach($pieces as $piece) {
				$words[] = $piece;
				$written[] = (count($pieces) === 1) ? $word : NULL;
			}

		}

		$typed = end($search);
		$position = count($search) - 1;

		// Un mot à l'intérieur du nom (« te » dans « Pomme de terre ») le fait proposer, mais complété, il
		// ferait une phrase que la lecture ne lit pas : seul le début du nom se complète.
		if(
			str_starts_with(implode(' ', $words), implode(' ', $search)) === FALSE or
			$written[$position] === NULL or
			$words[$position] === $typed
		) {
			return NULL;
		}

		// Sans accent ni majuscule, un mot que `split()` ne coupe pas garde ses lettres une à une : la fin
		// tapée se lit à la même place dans le mot écrit.
		return [
			'word' => $written[$position],
			'suffix' => mb_strtolower(mb_substr($written[$position], mb_strlen($typed))),
		];

	}

	/**
	 * Les intentions permises dont un mot commence par les mots en attente : « rec » propose la récolte.
	 */
	public static function findIntentions(\structure\Structure $eStructure, ?string $pending): array {

		$search = ($pending === NULL) ? '' : implode(' ', self::split($pending));

		return array_keys(array_filter(
			self::getAllowed($eStructure),
			function(string $intention) use($search) {

				foreach($intention::getWords() as $word) {
					if(str_starts_with($word, $search)) {
						return TRUE;
					}
				}

				return FALSE;

			}
		));

	}

	/**
	 * Le vocabulaire des compléments d'une intention, par clé de complément, pour dire ce que le
	 * champ comprend quand il refuse un mot.
	 */
	public static function getVocabulary(string $intention): array {

		$vocabulary = [];

		foreach(self::getComplements($intention) as $key => $complement) {

			$words = $complement::getVocabulary();

			if($words !== NULL) {
				$vocabulary[$key] = $words;
			}

		}

		return $vocabulary;

	}

	/**
	 * Pose l'unique question de la saisie.
	 *
	 * ⚠️ Une saisie ne pose qu'une question : la seconde lève, au lieu d'écraser la première. Deux
	 * compléments qui en voudraient chacun une (la culture et, plus tard, le poids d'une caisse)
	 * doivent être départagés par l'intention, pas par l'ordre d'appel.
	 */
	public static function ask(array &$reading, array $question): void {

		if($reading['question'] !== NULL) {
			throw new \Exception('A reading asks one question at most');
		}

		$reading['question'] = $question;
		$reading['outcome'] = self::ASK;

	}

	public static function refuse(array &$reading, array $refusal): void {

		$reading['refusal'] = $refusal;
		$reading['outcome'] = self::REFUSE;

	}

	/**
	 * Écrit une lecture complète.
	 *
	 * Tout ce qui peut échouer à la validation est préparé et validé avant la transaction, qui n'écrit
	 * donc que ce qui a déjà été accepté (`EntryAtomicity.t.php`).
	 */
	public static function write(\structure\Structure $eStructure, array &$reading): void {

		if($reading['outcome'] !== self::WRITE) {
			throw new \Exception('Only a complete reading can be written');
		}

		// La lecture ne pose que les intentions permises : une autre dit une lecture faite ailleurs.
		$intention = self::getAllowed($eStructure)[$reading['intention']] ?? throw new \Exception('The reading of a forbidden intention can not be written');

		$fw = new \FailWatch();

		$intention::prepare($eStructure, $reading);

		$fw->validate();

		$model = $intention::getModel();

		$model->beginTransaction();

			$intention::write($eStructure, $reading);

			foreach(self::getComplements($reading['intention']) as $complement) {
				$complement::afterWrite($eStructure, $reading);
			}

		$model->commit();

		$intention::afterCommit($eStructure, $reading);

	}

	/**
	 * Ce que l'annulation d'une saisie défera, lu par l'intention qui a posé le ticket
	 * (`IntentionLib::getCancellation()`). Tout ce qui peut échouer est contrôlé ici, hors transaction,
	 * comme pour l'écriture.
	 */
	public static function prepareCancel(\structure\Structure $eStructure, string $intention, ?string $ticket): array {

		$class = self::getAllowed($eStructure)[$intention] ?? throw new \Exception('A forbidden intention can not be cancelled');

		$fw = new \FailWatch();

		$cancellation = ($ticket === NULL) ? NULL : $class::getCancellation($eStructure, $ticket);

		if($cancellation === NULL) {
			throw new \FailAction($class::getCancelFail());
		}

		$fw->validate();

		return $cancellation;

	}

	/**
	 * Annule ce que `prepareCancel()` a lu. Le seul refus qui reste dans la transaction, une autre
	 * annulation qui a devancé celle-ci, ne s'y lève qu'une fois la transaction refermée : rien n'a été
	 * écrit, et un `rollBack()` viderait aussi les transactions de l'appelant.
	 */
	public static function cancel(\structure\Structure $eStructure, string $intention, array $cancellation): void {

		$class = self::getAllowed($eStructure)[$intention] ?? throw new \Exception('A forbidden intention can not be cancelled');

		$model = $class::getModel();

		$model->beginTransaction();

			$cancelled = $class::cancel($cancellation);

		$model->commit();

		if($cancelled === FALSE) {
			throw new \FailAction($class::getCancelFail());
		}

		$class::afterCancel($eStructure, $cancellation);

	}

	/**
	 * Une phrase sans verbe (« courgette 12 kg », le champ du menu n'en pose pas) se lit avec celui de
	 * l'intention dont elle nomme l'objet, ou en commence le nom : c'est la lecture de la même phrase
	 * précédée du verbe, par tout le chemin de `readText()`.
	 *
	 * Deux intentions peuvent la lire : « désherbage carotte 2 h » nomme une intervention de désherbage, et
	 * aussi la carotte qu'on récolte, « désherbage » restant alors un mot que personne ne lit. La lecture qui
	 * comprend le plus de la phrase l'emporte (`getImplicitRank()`). Si aucune intention ne la lit, ou si
	 * plusieurs la lisent aussi bien, le verbe ne se devine pas et le champ le demande.
	 */
	private static function readImplicit(\structure\Structure $eStructure, string $text, array $answers, \Collection &$cElement): ?array {

		$readings = [];

		foreach(self::getAllowed($eStructure) as $key => $intention) {

			if($intention::isExplicit()) {
				continue;
			}

			$cElementIntention = new \Collection();
			$reading = self::readText($eStructure, $intention::getWords()[0].' '.$text, $answers, $cElementIntention, FALSE);

			$rank = self::getImplicitRank($reading, $intention::getListed($cElementIntention), $intention::getTyped($cElementIntention));

			if($rank > 0) {
				$readings[$rank][] = [$reading, $cElementIntention, $intention, $key];
			}

		}

		if($readings === []) {
			return NULL;
		}

		$rank = max(array_keys($readings));
		$best = $readings[$rank];

		// Deux objets nommés, chacun avec des mots de reste : celle qui en laisse le moins a lu le plus de la
		// phrase. « fertilisation laitue 1 h blabla » ne laisse que « blabla » au temps, et « fertilisation
		// blabla » à la récolte de laitue.
		if($rank === 3 and count($best) > 1) {

			$counts = array_map(fn(array $entry) => count(self::split($entry[0]['pending'])), $best);
			$fewest = array_keys($counts, min($counts), TRUE);

			$best = array_map(fn(int $position) => $best[$position], $fewest);

		}

		// La coche et le temps lisent la même intervention : ce qu'elles y trouvent les départage
		// (`IntentionLib::getImplicitPrecedence()`). Deux objets différents, une espèce et une intervention, restent
		// une phrase dont le verbe ne se devine pas, même nommés par les mêmes mots. Sans objet encore, pendant la
		// frappe, elles proposent la même liste (`DoneIntentionLib::getObjects()`) : c'est encore la même lecture.
		if(
			count($best) > 1 and
			count(array_unique(array_map(fn(array $entry) => self::getObjectKey($entry[0]['object']), $best))) === 1
		) {

			$precedences = array_map(fn(array $entry) => $entry[2]::getImplicitPrecedence($entry[0]), $best);
			$highest = array_keys($precedences, max($precedences), TRUE);

			$discarded = array_diff_key($best, array_flip($highest));
			$best = array_map(fn(int $position) => $best[$position], $highest);

		} else {
			$discarded = [];
		}

		if(count($best) !== 1) {
			return NULL;
		}

		[$reading, $cElement] = $best[0];

		// Le verbe qui a servi à lire la phrase n'a pas été tapé.
		$reading['verb'] = [];

		// Ce que la précédence a écarté sur le même objet, et qui a encore quelque chose à écrire, reste lisible
		// par l'écran de la gagnante : le temps y propose la coche de la même phrase (`TimeIntentionUi::getResults()`).
		foreach($discarded as [$readingDiscarded, , $intention, $key]) {

			if($intention::isOffered($readingDiscarded)) {
				$reading['discarded'][$key] = $readingDiscarded;
			}

		}

		return $reading;

	}

	/**
	 * L'objet d'une lecture, par sa classe et son id : celui d'une intervention du temps et celui de la coche, lus
	 * dans la même liste (`DoneIntentionLib::getObjects()`), sont le même.
	 */
	private static function getObjectKey(\Element $eElement): string {
		return $eElement->empty() ? '' : $eElement::class.'-'.$eElement['id'];
	}

	/**
	 * Ce qu'une intention comprend d'une phrase sans verbe, du mieux au moins bien : son objet nommé sans mot de
	 * reste (4), nommé avec un mot que personne ne lit (3), le début du nom d'un de ses objets (2), le début d'un
	 * mot à l'intérieur d'un nom (1, « carotte » dans « Désherbage carotte »), rien (0). En frappe, « car »
	 * commence Carotte avant de se trouver dans « Désherbage carotte » : la récolte garde la phrase. Le début du
	 * nom d'un objet que l'écran ne propose qu'à la frappe (`getTyped()`, « semi » pour l'action Semis direct) vaut
	 * 1 : une espèce qui commence de même garde la phrase à la récolte.
	 */
	private static function getImplicitRank(array $reading, \Collection $cElement, \Collection $cElementTyped): int {

		if($reading['object']->notEmpty()) {
			return ($reading['pending'] === NULL) ? 4 : 3;
		}

		if(self::findObjects($cElement, $reading['pending'], start: TRUE)->notEmpty()) {
			return 2;
		}

		return (
			self::findObjects($cElement, $reading['pending'])->notEmpty() or
			self::findObjects($cElementTyped, $reading['pending'], start: TRUE)->notEmpty()
		) ? 1 : 0;

	}

	private static function readIntention(array $intentions, array &$words): ?string {

		foreach($words as $position => $word) {

			foreach($intentions as $key => $intention) {

				if(in_array($word, $intention::getWords(), TRUE)) {
					unset($words[$position]);
					return $key;
				}

			}

		}

		return NULL;

	}

	/**
	 * Les compléments lisent la phrase l'un après l'autre, chacun prenant ses mots, et la lecture garde ceux
	 * que chacun a pris (`complementsNamed`).
	 */
	private static function readComplements(array &$reading, array &$words, array $complements, ?int $typing): void {

		foreach($complements as $key => $complement) {

			$wordsBefore = $words;

			$complement::read($words, $reading['complements'][$key], $typing, $reading['intention']);

			$reading['complementsNamed'][$key] = array_values(array_diff_key($wordsBefore, $words));

		}

	}

	/**
	 * Le mot en cours de frappe n'est-il lu par un complément que comme le début d'un mot (« k », « p »), et
	 * non comme un mot entier (« kg », « 4 ») ? Le champ ne pose alors pas d'espace derrière lui quand un choix
	 * réécrit la phrase : elle en ferait un mot fini, qu'aucun complément ne lirait plus. Les compléments se
	 * relisent sur les mêmes mots, le mot tenu pour fini ; leur lecture ne dépend que des mots et du jour.
	 */
	private static function isTypingPrefix(array $words, array $wordsLeft, ?int $typing, array $complements, string $intention): bool {

		if($typing === NULL or array_key_exists($typing, $wordsLeft)) {
			return FALSE;
		}

		foreach($complements as $complement) {
			$value = NULL;
			$complement::read($words, $value, NULL, $intention);
		}

		return array_key_exists($typing, $words);

	}

	public static function withoutLinks(array $words): array {
		return array_filter($words, fn(string $word) => in_array($word, EntrySetting::LINKS, TRUE) === FALSE);
	}

	private static function getPending(array $words): ?string {

		return ($words === []) ? NULL : implode(' ', $words);

	}

}
?>
