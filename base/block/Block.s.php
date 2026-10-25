<?php
namespace block;

/**
 * Le moteur des blocs ne connaît ni les usages ni les types : il les résout depuis la valeur
 * stockée, comme `NotificationSetting` résout un type de notification. Un usage `<package>-<usage>`
 * désigne `\<package>\BlockOwnerLib` et `\<package>\BlockOwnerUi`, un type `<package>-<type>`
 * désigne `\<package>\BlockTypeLib` et `\<package>\BlockTypeUi`. Il n'y a donc ni registre ni table
 * à tenir : le contrat est gardé par `test/BlockTypeSentinel.t.php`.
 */
class BlockSetting extends \Settings {

	// Les bornes d'un tableau (`block-table`), celle du surtitre d'une section (`block-section`) et
	// celles des étiquettes (`block-tags`), dont le serveur est la seule source : un collage qui les
	// dépasse est refusé à l'enregistrement sous la borne franchie. Une case se lit dans une colonne, à
	// côté de ses voisines : 250 caractères y tiennent une phrase, et un texte plus long relève d'un
	// bloc de texte, pas d'un tableau. Un surtitre, rangé dans `settings` hors du schéma, n'a que cette
	// borne : il se lit au-dessus d'un titre, et 250 caractères y font déjà trois lignes. Une étiquette
	// est un mot ou deux qui situent le sujet : 40 caractères passent le plus long nom de sous-thème
	// des formations (34) et tiennent sur une ligne à la largeur d'un téléphone, et dix étiquettes y
	// tiennent en quatre rangs. L'éditeur ne lit que le nombre, pour retirer son « + » au plafond.
	const TABLE_ROWS_MAX = 100;
	const TABLE_COLUMNS_MAX = 12;
	const TABLE_CELL_LIMIT = 250;
	const SECTION_KICKER_LIMIT = 250;
	const TAGS_MAX = 10;
	const TAG_LIMIT = 40;

	// L'instant d'une étape dans la vidéo de son objet, en secondes (`settings.at`), borné à une
	// journée : au-delà, ce ne peut être qu'une faute de saisie dans une vidéo de formation.
	const STEP_AT_MAX = 86399;

	// Les repères posés sur la capture d'une étape (`settings.marks`), en pourcentage de l'image : un
	// cadre autour d'une zone, par son coin haut gauche et sa taille, ou un anneau autour d'un point,
	// par son centre. Au-delà de six, le regard ne sait plus lequel suivre.
	const STEP_MARKS = [
		'box' => ['x', 'y', 'w', 'h'],
		'ring' => ['x', 'y'],
	];
	const STEP_MARKS_MAX = 6;

	/**
	 * Le package est le segment qui précède le premier tiret, pour un usage comme pour un type
	 * (`block-text` → `block`).
	 */
	public static function getPackage(string $value): string {
		return explode('-', $value)[0];
	}

	/**
	 * La classe d'une paire, par exemple `resolve($owner, 'BlockOwnerLib')`. Le nom est
	 * calculé à chaque appel, sans mémoïsation : rien ici ne dépend de la structure connectée.
	 */
	public static function resolve(string $value, string $class): string {
		return '\\'.self::getPackage($value).'\\'.$class;
	}

	/**
	 * Ce que la liste des hébergeurs de l'éditeur de texte (`\editor\EditorSetting::VIDEO`) dit d'une
	 * adresse de vidéo : l'hébergeur qui la reconnait (`source`), son nom tel qu'il s'écrit (`name`)
	 * et l'adresse de son lecteur sous trois formes, ou cinq `NULL` si aucun ne la reconnait. La liste
	 * est parcourue et jamais recopiée : un hébergeur qui y entre ou en sort vaut aussitôt pour le bloc
	 * Vidéo, à l'écriture (`Block::url.host`) et à la lecture (`BlockTypeUi::getVideoFrame()`).
	 *
	 * `playerAutoplay` est l'adresse que le clic pose dans le cadre : elle demande le démarrage,
	 * puisque ce lecteur ne se pose qu'au clic de celui qui veut voir la vidéo, jamais à l'ouverture de
	 * la page. `player` est la même sans démarrage, celle du lien du cadre, qu'un navigateur ouvre
	 * comme une page. Ni l'une ni l'autre ne garde de l'adresse collée autre chose que l'identifiant de
	 * la vidéo : ce qui le suit peut désigner celui qui l'a copiée (jeton de partage) et ne se montre
	 * pas à chaque lecteur.
	 *
	 * `playerStart` est le gabarit du démarrage à un instant, où `{s}` attend les secondes : le clic
	 * sur l'instant d'une étape le remplit (`BlockVideo.jump()`, `read.js`).
	 */
	public static function getVideo(string $url): array {

		foreach(\editor\EditorSetting::VIDEO as $source => $video) {

			$player = \editor\EditorSetting::getVideoUrl($url, $source);

			if($player !== NULL) {

				return [
					'source' => $source,
					'name' => $video['name'],
					'player' => $player,
					'playerAutoplay' => \editor\EditorSetting::getVideoUrl($url, $source, TRUE),
					'playerStart' => \editor\EditorSetting::getVideoUrl($url, $source, TRUE, TRUE),
				];

			}

		}

		return ['source' => NULL, 'name' => NULL, 'player' => NULL, 'playerAutoplay' => NULL, 'playerStart' => NULL];

	}

	public static function getVideoSource(string $url): ?string {
		return self::getVideo($url)['source'];
	}

}
?>
