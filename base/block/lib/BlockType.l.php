<?php
namespace block;

/**
 * Les types que le moteur porte lui-même. Le texte en est le premier : c'est le bloc de tous les
 * usages, celui qui reprend le contenu d'avant les blocs. Un type propre à un usage vit dans le
 * package de l'usage, sous la même paire (`\<package>\BlockTypeLib`, `\<package>\BlockTypeUi`).
 *
 * Les étapes, l'accordéon, les onglets et les chiffres clés sont des blocs parents : ils n'écrivent
 * rien eux-mêmes, sauf les étapes, leur repère, et portent des enfants `block-item` par
 * `Block.parent`, un titre et un texte chacun.
 * L'arbre n'a qu'un niveau : un enfant ne porte rien (`getChildren()`).
 *
 * Les réponses portent des enfants `block-answer`, à deux variantes, ordinaire ou meilleure, dont une
 * seule réponse d'une fratrie porte la seconde (`getVariantUnique()`).
 *
 * Le tableau et la section écrivent dans `settings`, chacun sous sa forme (`castSettings()`). Le
 * tableau y range sa grille, sous `rows`, la première ligne étant toujours l'en-tête, et ses cellules
 * sont du texte simple, sans mise en forme. La section y range son surtitre, sous `kicker`.
 *
 * Les étiquettes rangent leurs mots dans `settings`, sous `tags`, dans l'ordre où l'auteur les a
 * écrits : quelques mots qui situent le sujet, sans enfant ni variante, lus en pastilles.
 *
 * L'étape range dans `settings`, sous `at`, l'instant de la vidéo de son objet où son geste se
 * montre, en secondes : un clic sur l'instant y lance la vidéo (`BlockTypeUi::renderChild()`). Un
 * objet ne porte donc qu'une vidéo (`Block::url.video`), celle à laquelle tous ses instants se
 * rapportent sans la nommer.
 *
 * La section est un marqueur à plat, sans enfant : son surtitre et son titre ouvrent une partie de
 * l'objet, qui court jusqu'à la section suivante. C'est la liste qui la regroupe à la lecture
 * (`BlockUi::render()`), pas l'arbre : déplacer, copier ou retirer une section ne touche qu'elle.
 *
 * La vidéo et le bouton sont les deux types qui écrivent dans `url`. Pour la vidéo, c'est l'adresse de
 * la page où elle se regarde, chez un hébergeur de la liste de l'éditeur de texte
 * (`BlockSetting::getVideoSource()`) ; sa légende est son `title`, sa disposition sa variante, en
 * pleine largeur, petite, ou à gauche ou à droite d'un texte. Ce texte est son `content`, lu dans ces
 * deux dernières seulement et gardé dans les autres, où il attend qu'on y revienne. Pour le bouton,
 * c'est la page qu'il ouvre, n'importe où sur le web, et son libellé est son `title`.
 */
class BlockTypeLib {

	const TEXT = 'block-text';
	const CALLOUT = 'block-callout';
	const STEPS = 'block-steps';
	const ACCORDION = 'block-accordion';
	const TABS = 'block-tabs';
	const FIGURE = 'block-figure';
	const QUOTE = 'block-quote';
	const TABLE = 'block-table';
	const VIDEO = 'block-video';
	const BUTTON = 'block-button';
	const SECTION = 'block-section';
	const ANSWERS = 'block-answers';
	const TAGS = 'block-tags';
	const ITEM = 'block-item';
	const ANSWER = 'block-answer';

	const INFO = 'info';
	const SUCCESS = 'success';
	const WARNING = 'warning';

	const FULL = 'full';
	const SMALL = 'small';
	const LEFT = 'left';
	const RIGHT = 'right';

	const NUMBERS = 'numbers';
	const CHECK = 'check';

	const REGULAR = 'regular';
	const BEST = 'best';

	/**
	 * Les propriétés qu'un bloc de ce type écrit depuis son formulaire. Tout le reste d'un bloc
	 * (usage, objet, parent, rang) est posé par le moteur et ne se poste jamais. Un chiffre range sa
	 * valeur dans `title` et sa légende dans `content`, la citation son auteur dans `title`.
	 */
	public static function getProperties(string $type): array {

		return match($type) {
			self::TEXT => ['content'],
			self::CALLOUT, self::ANSWER => ['variant', 'title', 'content'],
			self::STEPS => ['variant'],
			self::ACCORDION, self::TABS, self::FIGURE, self::ANSWERS => [],
			self::QUOTE => ['title', 'content'],
			self::ITEM => ['title', 'content', 'settings'],
			self::TABLE, self::TAGS => ['settings'],
			self::VIDEO => ['variant', 'url', 'title', 'content'],
			self::BUTTON => ['title', 'url'],
			self::SECTION => ['settings', 'title'],
		};

	}

	/**
	 * À la création, un parent naît avec sa première étape : il écrit ce qu'il écrit lui-même, le
	 * repère des étapes, et son formulaire porte le titre et le texte que `BlockLib::create()` passe
	 * à l'étape. Rien d'autre de l'enfant ne se construit sur le parent : une variante d'enfant
	 * postée sur lui serait jugée contre les variantes du parent.
	 */
	public static function getPropertiesCreate(string $type): array {

		$children = self::getChildren($type);

		if($children === []) {
			return self::getProperties($type);
		} else {
			return array_merge(self::getProperties($type), ['title', 'content']);
		}

	}

	/**
	 * Les propriétés sans lesquelles le bloc ne se lit pas : un onglet ou un pli d'accordéon sans
	 * titre n'a rien à cliquer, un chiffre sans valeur n'a rien à montrer, une vidéo sans adresse
	 * rien à lire, un bouton sans libellé rien à cliquer et sans adresse nulle part où mener, une section
	 * sans titre rien qui l'annonce. Un parent
	 * les exige de sa première étape, qu'il porte à la création.
	 */
	public static function getPropertiesRequired(string $type): array {

		return match($type) {
			self::TEXT, self::CALLOUT, self::QUOTE, self::TABLE, self::TAGS => [],
			self::ITEM, self::FIGURE, self::STEPS, self::ACCORDION, self::TABS, self::SECTION, self::ANSWERS, self::ANSWER => ['title'],
			self::VIDEO => ['url'],
			self::BUTTON => ['title', 'url'],
		};

	}

	/**
	 * Les types qu'un bloc accepte sous lui. `Block::type.allowed` confronte un enfant à son parent,
	 * et non aux types de l'usage, qui ne sont que ceux du premier niveau.
	 */
	public static function getChildren(string $type): array {

		return match($type) {
			self::STEPS, self::ACCORDION, self::TABS, self::FIGURE => [self::ITEM],
			self::ANSWERS => [self::ANSWER],
			self::TEXT, self::CALLOUT, self::QUOTE, self::ITEM, self::TABLE, self::VIDEO, self::BUTTON, self::SECTION, self::ANSWER, self::TAGS => [],
		};

	}

	/**
	 * Le nombre d'enfants au-delà duquel un parent refuse d'en porter : quatre chiffres tiennent sur
	 * une rangée à la largeur d'une leçon, un cinquième la ferait passer à la ligne. `NULL` ne borne rien.
	 */
	public static function getChildrenMax(string $type): ?int {

		return match($type) {
			self::FIGURE => 4,
			self::TEXT, self::CALLOUT, self::STEPS, self::ACCORDION, self::TABS, self::QUOTE, self::ITEM, self::TABLE, self::VIDEO, self::BUTTON, self::SECTION, self::ANSWERS, self::ANSWER, self::TAGS => NULL,
		};

	}

	public static function getVariants(string $type): array {

		return match($type) {
			self::CALLOUT => [self::INFO, self::SUCCESS, self::WARNING],
			self::VIDEO => [self::FULL, self::SMALL, self::LEFT, self::RIGHT],
			self::STEPS => [self::NUMBERS, self::CHECK],
			self::ANSWER => [self::REGULAR, self::BEST],
			self::TEXT, self::ACCORDION, self::TABS, self::FIGURE, self::QUOTE, self::ITEM, self::TABLE, self::BUTTON, self::SECTION, self::ANSWERS, self::TAGS => [],
		};

	}

	/**
	 * La variante qu'un seul bloc d'une fratrie peut porter : la marquer sur un bloc la retire à ses
	 * frères, qui reprennent la première variante du type (`BlockLib::resetSiblingsVariant()`), et la
	 * copie d'un bloc qui la porte naît sur la première. `NULL` n'en réserve aucune.
	 */
	public static function getVariantUnique(string $type): ?string {

		return match($type) {
			self::ANSWER => self::BEST,
			self::TEXT, self::CALLOUT, self::STEPS, self::ACCORDION, self::TABS, self::FIGURE, self::QUOTE, self::ITEM, self::TABLE, self::VIDEO, self::BUTTON, self::SECTION, self::ANSWERS, self::TAGS => NULL,
		};

	}

	/**
	 * Un type dont chaque variante est une forme à part, choisie au « + » et jamais changée ensuite :
	 * le menu propose une entrée par variante (`BlockLib::getChoices()`), le bloc naît sur celle de
	 * l'entrée, et sa barre n'a pas de réglage de variante. Aucun type du moteur n'en a : leurs variantes
	 * se règlent dans la barre du bloc ouvert.
	 */
	public static function isVariantFixed(string $type): bool {

		return match($type) {
			self::TEXT, self::CALLOUT, self::STEPS, self::ACCORDION, self::TABS, self::FIGURE, self::QUOTE, self::ITEM, self::TABLE, self::VIDEO, self::BUTTON, self::SECTION, self::ANSWERS, self::ANSWER, self::TAGS => FALSE,
		};

	}

	/**
	 * Le réglage d'un bloc neuf, que l'éditeur ouvre avant tout enregistrement (`BlockLib::getNew()`).
	 * Un tableau neuf a trois colonnes, son en-tête et deux lignes, toutes vides : la forme se voit
	 * avant que l'on écrive, et le collage d'un tableur l'agrandit d'un geste. Une section neuve n'a pas
	 * de surtitre, qui est facultatif. Des étiquettes neuves s'ouvrent sur une pastille vide, où le
	 * premier mot s'écrit sans autre geste. `$variant` est celle du bloc neuf, qu'un type à forme fixée
	 * lit pour donner à chaque forme son réglage, et que les types du moteur ignorent.
	 */
	public static function getDefaultSettings(string $type, ?string $variant = NULL): array {

		return match($type) {
			self::TABLE => ['rows' => array_fill(0, 3, array_fill(0, 3, ''))],
			self::SECTION => ['kicker' => NULL],
			self::TAGS => ['tags' => ['']],
			self::TEXT, self::CALLOUT, self::STEPS, self::ACCORDION, self::TABS, self::FIGURE, self::QUOTE, self::ITEM, self::VIDEO, self::BUTTON, self::ANSWERS, self::ANSWER => [],
		};

	}

	/**
	 * Les options d'éditeur du texte d'un bloc de ce type, écrit dans cet usage. Le type les donne parce
	 * qu'un type d'un autre package est seul à savoir ce que son texte porte. Pour les siens, le moteur
	 * s'en remet à l'usage, seule source de ce qu'un texte accepte en son sein (`getEditorOptions()` de
	 * `BlockOwnerLib`), que l'écriture d'un contenu hors des blocs lit aussi.
	 */
	public static function getEditorOptions(string $type, string $owner): array {
		return BlockSetting::resolve($owner, 'BlockOwnerLib')::getEditorOptions($owner, $type);
	}

	/**
	 * Le réglage posté d'un bloc qui écrit dans `settings` (`getProperties()`), remis dans la forme de
	 * son type avant d'être jugé par les bornes de `Block::settings`. Rendre `FALSE` refuse le réglage en
	 * entier sous `Block::settings.format`. Le `match` n'a pas de branche par défaut : un type qui
	 * écrirait dans `settings` sans sa forme lève, au lieu d'enregistrer un POST que rien n'a jugé.
	 * `$wrapper` sert au type d'un autre package, qui nomme ses propres refus par une `\FailException`
	 * et doit la rattacher au champ (`\Fail::log()` reprend le wrapper de l'exception).
	 */
	public static function castSettings(Block $eBlock, mixed &$settings, string $wrapper): bool {

		return match($eBlock['type']) {
			self::TABLE => self::castTable($settings),
			self::SECTION => self::castSection($settings),
			self::TAGS => self::castTags($settings),
			self::ITEM => self::castItem($eBlock, $settings),
		};

	}

	/**
	 * Le réglage d'une étape porte deux clés, chacune facultative. L'instant arrive sous `at`, en
	 * secondes ou tel qu'il se lit, `m:ss` ou `h:mm:ss`. Les repères arrivent sous `marks`, une liste de
	 * cadres (`box` : `x`, `y`, `w`, `h`) et d'anneaux (`ring` : `x`, `y`, leur centre), en pourcentage
	 * de la capture de l'étape (`BlockSetting::STEP_MARKS`). Le réglage est refait de zéro, clé par
	 * clé et repère par repère : rien d'autre de ce qui est posté n'est gardé, la colonne json rendant
	 * par l'API tout ce qu'un POST y aurait mis. Un instant vide ou une liste vide valent l'absence de
	 * la clé. Seule une étape en porte : posé sous un autre parent, ou sans parent, l'un comme l'autre
	 * refuse le réglage en entier, sans lire le type d'un parent qui n'existe pas. Des repères se posent
	 * sur une étape qui n'a pas encore de capture : le réglage et l'image s'écrivent par deux routes,
	 * dans l'ordre que choisit le client, et rien ne se rend sans image (`getStepMedia()`). Le réglage
	 * non posté arrive ici déjà remplacé par celui de la base (`Block::settings.kept`).
	 *
	 * Ici se juge la forme, et les bornes ensuite (`Block::settings.at`, `.marks`, `.mark`). Une liste
	 * plus longue que le plafond passe sans être lue, pour que `Block::settings.marks` la refuse sous
	 * son nom sans qu'un POST démesuré soit parcouru : la chaîne s'arrête à ce refus, rien ne
	 * l'enregistre.
	 */
	private static function castItem(Block $eBlock, mixed &$settings): bool {

		if(is_array($settings) === FALSE) {
			return FALSE;
		}

		$cast = [];

		if(
			array_key_exists('at', $settings) and
			$settings['at'] !== NULL and
			$settings['at'] !== ''
		) {

			$at = self::castAt($settings['at']);

			if($at === NULL) {
				return FALSE;
			}

			$cast['at'] = $at;

		}

		if(
			array_key_exists('marks', $settings) and
			$settings['marks'] !== NULL and
			$settings['marks'] !== []
		) {

			if(
				is_array($settings['marks']) === FALSE or
				array_is_list($settings['marks']) === FALSE
			) {
				return FALSE;
			}

			if(count($settings['marks']) > BlockSetting::STEP_MARKS_MAX) {
				$cast['marks'] = $settings['marks'];
			} else {

				$cast['marks'] = [];

				foreach($settings['marks'] as $mark) {

					$mark = self::castMark($mark);

					if($mark === NULL) {
						return FALSE;
					}

					$cast['marks'][] = $mark;

				}

			}

		}

		if($cast !== []) {

			if($eBlock['parent']->empty()) {
				return FALSE;
			}

			$eBlock['parent']->expects(['type']);

			if($eBlock['parent']['type'] !== self::STEPS) {
				return FALSE;
			}

		}

		$settings = $cast;

		return TRUE;

	}

	/**
	 * Un repère reconstruit de ce qui est posté : son type, puis exactement les coordonnées que ce type
	 * demande, chacune un nombre fini, entier, décimal ou chiffres, lu en décimal arrondi au centième.
	 * `NULL` pour une clé de trop ou qui manque, et pour tout ce qui n'est pas un nombre, tableau
	 * compris. La borne se juge ensuite (`Block::settings.mark`).
	 */
	private static function castMark(mixed $mark): ?array {

		if(
			is_array($mark) === FALSE or
			array_key_exists('type', $mark) === FALSE or
			is_string($mark['type']) === FALSE or
			array_key_exists($mark['type'], BlockSetting::STEP_MARKS) === FALSE
		) {
			return NULL;
		}

		$coordinates = BlockSetting::STEP_MARKS[$mark['type']];

		$keys = array_keys($mark);
		$expected = ['type', ...$coordinates];

		sort($keys);
		sort($expected);

		if($keys !== $expected) {
			return NULL;
		}

		$cast = ['type' => $mark['type']];

		foreach($coordinates as $coordinate) {

			$value = $mark[$coordinate];

			if(
				is_int($value) === FALSE and
				is_float($value) === FALSE and (
					is_string($value) === FALSE or
					is_numeric($value) === FALSE
				)
			) {
				return NULL;
			}

			$value = (float)$value;

			// `1e400` est un nombre pour PHP et vaut l'infini, qui n'est pas une position : il se refuse
			// comme une forme, et non comme une borne franchie
			if(is_finite($value) === FALSE) {
				return NULL;
			}

			$cast[$coordinate] = round($value, 2);

		}

		return $cast;

	}

	/**
	 * Un instant en secondes, entier ou chiffres, ou écrit comme il se lit sous une vidéo : `1:05`,
	 * `1:02:05`. `NULL` pour tout le reste. La borne se juge ensuite (`Block::settings.at`), un
	 * entier négatif compris.
	 */
	private static function castAt(mixed $at): ?int {

		if(is_int($at)) {
			return $at;
		}

		if(is_string($at) === FALSE) {
			return NULL;
		}

		if(preg_match('/^[0-9]{1,6}$/', $at) === 1) {
			return (int)$at;
		}

		if(preg_match('/^([0-9]{1,4}):([0-5][0-9])$/', $at, $match) === 1) {
			return (int)$match[1] * 60 + (int)$match[2];
		}

		if(preg_match('/^([0-9]{1,2}):([0-5][0-9]):([0-5][0-9])$/', $at, $match) === 1) {
			return (int)$match[1] * 3600 + (int)$match[2] * 60 + (int)$match[3];
		}

		return NULL;

	}

	/**
	 * La grille d'un tableau arrive des champs `settings[rows][<ligne>][<colonne>]`, que le script
	 * renumérote à chaque ligne ou colonne ajoutée ou retirée, mais un POST peut tout porter. Elle est
	 * remise en forme avant d'être jugée : lignes et cellules dans l'ordre, une cellule sur une ligne
	 * (un collage de tableur peut apporter des sauts de ligne), les lignes courtes complétées pour que
	 * chaque ligne ait autant de cellules que la plus longue. Ce qui n'est pas une liste de lignes de
	 * texte est refusé.
	 */
	private static function castTable(mixed &$settings): bool {

		if(
			is_array($settings) === FALSE or
			isset($settings['rows']) === FALSE or
			is_array($settings['rows']) === FALSE
		) {
			return FALSE;
		}

		$rows = [];

		ksort($settings['rows'], SORT_NUMERIC);

		foreach($settings['rows'] as $cells) {

			if(is_array($cells) === FALSE) {
				return FALSE;
			}

			ksort($cells, SORT_NUMERIC);

			$row = [];

			foreach($cells as $cell) {

				if(
					$cell !== NULL and
					is_scalar($cell) === FALSE
				) {
					return FALSE;
				}

				$row[] = trim(preg_replace('/[\t\r\n]+/', ' ', (string)$cell));

			}

			$rows[] = $row;

		}

		$columns = max([0, ...array_map('count', $rows)]);

		foreach($rows as $position => $row) {
			$rows[$position] = array_pad($row, $columns, '');
		}

		$settings = ['rows' => $rows];

		return TRUE;

	}

	/**
	 * Le surtitre d'une section arrive du champ `settings[kicker]`, qui poste toujours une chaîne, vide
	 * quand le surtitre l'est. Il s'écrit sur une ligne, comme une case de tableau, et un surtitre vide
	 * est l'absence de surtitre. Rien d'autre de ce qui est posté dans `settings` n'est gardé.
	 * L'absence se reçoit aussi sous la forme qu'elle prend une fois enregistrée, `NULL`, qui est le
	 * réglage d'une section neuve (`getDefaultSettings()`) : la forme ne refuse pas ce qu'elle rend.
	 * Un réglage sans la clé reste refusé.
	 */
	private static function castSection(mixed &$settings): bool {

		if(
			is_array($settings) === FALSE or
			array_key_exists('kicker', $settings) === FALSE or (
				$settings['kicker'] !== NULL and
				is_string($settings['kicker']) === FALSE
			)
		) {
			return FALSE;
		}

		$kicker = $settings['kicker'];

		if($kicker !== NULL) {
			$kicker = trim(preg_replace('/[\t\r\n]+/', ' ', $kicker));
		}

		if($kicker === '') {
			$kicker = NULL;
		}

		$settings = ['kicker' => $kicker];

		return TRUE;

	}

	/**
	 * Les étiquettes arrivent des champs `settings[tags][]`, une pastille chacun, dans l'ordre de
	 * l'écran. Chaque mot s'écrit sur une ligne, bords retirés. Une pastille laissée vide et un mot
	 * déjà écrit, à la casse près, s'écartent sans rien dire : le premier écrit reste, à sa place.
	 * Aucun mot n'est l'absence d'étiquettes, que la lecture écarte (`isEmpty()`). Ce
	 * qui n'est pas une liste de mots est refusé.
	 */
	private static function castTags(mixed &$settings): bool {

		if(
			is_array($settings) === FALSE or
			isset($settings['tags']) === FALSE or
			is_array($settings['tags']) === FALSE
		) {
			return FALSE;
		}

		$tags = [];

		foreach($settings['tags'] as $tag) {

			if(is_string($tag) === FALSE) {
				return FALSE;
			}

			// Un collage depuis une page web apporte des caractères de format invisibles (U+200B, U+FEFF…),
			// qui feraient une pastille vide ou deux écritures d'un même mot. Les liants U+200C et U+200D
			// restent : ils composent les emojis et l'écriture de certaines langues.
			$tag = preg_replace(['/(?![\x{200C}\x{200D}])\p{Cf}/u', '/\s+/u'], ['', ' '], $tag);

			// Un octet qui n'est pas de l'UTF-8 fait échouer le motif, sans rien remplacer.
			if($tag === NULL) {
				return FALSE;
			}

			// Un « é » s'écrit en un caractère ou en « e » suivi de son accent : la forme composée fait
			// d'un même mot une seule étiquette, quelle que soit la saisie.
			$tag = trim(\Normalizer::normalize($tag, \Normalizer::NFC));
			$key = mb_strtolower($tag);

			if(
				$tag !== '' and
				array_key_exists($key, $tags) === FALSE
			) {
				$tags[$key] = $tag;
			}

		}

		$settings = ['tags' => array_values($tags)];

		return TRUE;

	}

	/**
	 * Un bloc qui n'a rien à montrer au lecteur : un texte vidé dans l'éditeur reste un bloc, que
	 * l'auteur retrouve à sa place, mais la lecture l'écarte (`BlockLib::getReadable()`). Un parent
	 * est vide quand toutes ses étapes le sont : il se juge sur les enfants qu'il porte (`cBlock`).
	 * Une vidéo dont l'hébergeur a quitté la liste n'a plus de lecteur à ouvrir : le lecteur ne la voit
	 * pas, l'auteur la retrouve dans l'éditeur avec son adresse.
	 */
	public static function isEmpty(Block $eBlock): bool {

		return match($eBlock['type']) {
			self::TEXT => ($eBlock['content'] === NULL),
			self::CALLOUT, self::QUOTE, self::ITEM, self::ANSWER => (
				$eBlock['title'] === NULL and
				$eBlock['content'] === NULL
			),
			self::STEPS, self::ACCORDION, self::TABS, self::FIGURE, self::ANSWERS => $eBlock['cBlock']->find(fn(Block $eBlockChild) => self::isEmpty($eBlockChild) === FALSE)->empty(),
			self::TABLE => (implode('', array_merge(...$eBlock['settings']['rows'])) === ''),
			self::VIDEO => (
				$eBlock['url'] === NULL or
				BlockSetting::getVideoSource($eBlock['url']) === NULL
			),
			self::BUTTON => (
				$eBlock['title'] === NULL or
				$eBlock['url'] === NULL
			),
			self::SECTION => ($eBlock['title'] === NULL),
			self::TAGS => ($eBlock['settings']['tags'] === []),
		};

	}

}
?>
