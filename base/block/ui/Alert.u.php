<?php
namespace block;

class AlertUi {

	/**
	 * Les refus dont les mots dépendent du type du bloc : le moteur dit ceux de ses types, et ne nomme
	 * pas ceux des autres packages. Un type d'ailleurs qui écrit la propriété dit le sien sous la même
	 * clé, dans l'`AlertUi` de son package (`getErrorByType()`).
	 */
	const array ERRORS_BY_TYPE = [
		'Block::variant.check', 'Block::variant.allowed',
		'Block::title.missing',
		'Block::url.missing', 'Block::url.check', 'Block::url.storable', 'Block::url.scheme', 'Block::url.host',
		'Block::settings.check', 'Block::settings.format',
	];

	public static function getError(string $fqn, array $options = []): mixed {

		$error = match($fqn) {

			'Block::type.check', 'Block::type.missing', 'Block::type.allowed' => s("Ce type de bloc n'est pas disponible ici."),
			'Block::type.full' => s("Ce bloc ne peut plus recevoir d'élément."),
			'Block::variant.check', 'Block::variant.allowed' => fn(Block $eBlock) => match($eBlock['type']) {
				BlockTypeLib::CALLOUT => s("Choisissez une des couleurs proposées."),
				BlockTypeLib::VIDEO => s("Choisissez une des dispositions proposées."),
				BlockTypeLib::STEPS => s("Choisissez des numéros ou des coches."),
				BlockTypeLib::ANSWER => s("Choisissez une réponse ordinaire ou la meilleure réponse."),
			},
			'Block::title.check' => s("Ce titre est trop long."),
			'Block::title.missing' => fn(Block $eBlock) => match($eBlock['type']) {
				BlockTypeLib::ANSWERS, BlockTypeLib::ANSWER => s("Donnez un titre à la réponse."),
				BlockTypeLib::SECTION => s("Donnez un titre à la section."),
				BlockTypeLib::STEPS => s("Donnez un titre à l'étape."),
				BlockTypeLib::ACCORDION => s("Donnez un titre au volet."),
				BlockTypeLib::FIGURE => s("Écrivez le chiffre."),
				BlockTypeLib::ITEM => match($eBlock['parent']['type']) {
					BlockTypeLib::STEPS => s("Donnez un titre à l'étape."),
					BlockTypeLib::ACCORDION => s("Donnez un titre au volet."),
					BlockTypeLib::FIGURE => s("Écrivez le chiffre."),
				},
			},
			'Block::title.tabName' => s("Donnez un nom à l'onglet."),
			'Block::title.buttonLabel' => s("Écrivez le texte du bouton."),
			'Block::url.video' => s("Il y a déjà une vidéo ici, et une seule peut être ajoutée."),
			'Block::content.check' => s("Ce texte n'a pas pu être enregistré, merci de le reprendre."),
			'Block::url.missing' => fn(Block $eBlock) => match($eBlock['type']) {
				BlockTypeLib::VIDEO => s("Collez l'adresse de la vidéo."),
				BlockTypeLib::BUTTON => s("Collez l'adresse de la page."),
			},
			'Block::url.check', 'Block::url.storable', 'Block::url.scheme', 'Block::url.host' => fn(Block $eBlock) => match($eBlock['type']) {
				BlockTypeLib::VIDEO => s("Collez l'adresse de la page où la vidéo se regarde, sur {value}.", new \editor\EditorUi()->getVideoNames()),
				BlockTypeLib::BUTTON => s("Collez l'adresse complète de la page, avec https:// ou http://."),
			},
			'Block::settings.check', 'Block::settings.format' => fn(Block $eBlock) => match($eBlock['type']) {
				BlockTypeLib::TABLE => s("Ce tableau n'a pas pu être enregistré, merci de le reprendre."),
				BlockTypeLib::SECTION => s("Ce surtitre n'a pas pu être enregistré, merci de le reprendre."),
				BlockTypeLib::TAGS => s("Ces étiquettes n'ont pas pu être enregistrées, merci de les reprendre."),
				BlockTypeLib::ITEM => s("Un instant dans la vidéo et des repères sur la capture se posent sur une étape. L'instant s'écrit en secondes ou comme 1:30, un repère est un cadre ou un anneau placé en pourcentage de la capture."),
			},
			'Block::settings.at' => s("L'instant d'une étape est compris entre 0:00 et {value}.", new BlockTypeUi()->formatAt(BlockSetting::STEP_AT_MAX)),
			'Block::settings.marks' => s("Une étape porte au plus {value} repères.", BlockSetting::STEP_MARKS_MAX),
			'Block::settings.mark' => s("Un repère se tient dans la capture, entre 0 et 100 % de sa largeur et de sa hauteur."),
			'Block::settings.rows' => s("Un tableau a au plus {value} lignes, en-tête comprise.", BlockSetting::TABLE_ROWS_MAX),
			'Block::settings.columns' => s("Un tableau a au plus {value} colonnes.", BlockSetting::TABLE_COLUMNS_MAX),
			'Block::settings.cell' => s("Une case contient au plus {value} caractères.", BlockSetting::TABLE_CELL_LIMIT),
			'Block::settings.kicker' => s("Un surtitre contient au plus {value} caractères.", BlockSetting::SECTION_KICKER_LIMIT),
			'Block::settings.full' => s("Un bloc porte au plus {value} étiquettes.", BlockSetting::TAGS_MAX),
			'Block::settings.tag' => s("Une étiquette contient au plus {value} caractères.", BlockSetting::TAG_LIMIT),
			'Block::positionsIncomplete' => s("L'ordre des blocs n'a pas pu être enregistré, merci de recharger la page."),

			default => NULL

		};

		if(in_array($fqn, self::ERRORS_BY_TYPE, TRUE)) {
			return self::getErrorByType($fqn, $error);
		}

		return $error;

	}

	/**
	 * `\FailWatch::getMessage()` appelle le message sur lui-même, avec l'élément et la valeur refusés :
	 * `$this` est donc la surveillance, qui lit le message du type sous la même clé dans son package.
	 */
	private static function getErrorByType(string $fqn, \Closure $error): \Closure {

		return function(Block $eBlock, mixed ...$arguments) use($fqn, $error): ?string {

			$package = BlockSetting::getPackage($eBlock['type']);

			if($package === __NAMESPACE__) {
				return $error->call($this, $eBlock, ...$arguments);
			}

			return $this->getMessage($package, $fqn, [$eBlock, ...$arguments]);

		};

	}

}
?>
