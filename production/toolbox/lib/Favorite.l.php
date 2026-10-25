<?php
namespace toolbox;

/**
 * Les outils qu'une personne a mis dans son menu.
 *
 * Trois au plus (arbitrage QO2 A de la planche pf-outils) : l'onglet Boîte à outils les montre en
 * sous-menu, et au-delà le menu de la production s'allongerait pour des outils qu'on ouvre de
 * toute façon depuis la Boîte à outils.
 */
class FavoriteLib extends FavoriteCrud {

	const MAX = 3;

	// La page d'un outil lit les favoris pour son en-tête, puis le menu les relit par `cFavorite?`,
	// propriété de Structure qui ne garde rien d'un appel à l'autre.
	private static array $cachedByStructure = [];

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['tool'];
	}

	/**
	 * Les outils en favori de la personne connectée, dans l'ordre où elle les a ajoutés : c'est
	 * celui du sous-menu. Lu par le menu de chaque écran de la structure
	 * (`Structure::getSelection()`, `cFavorite`).
	 *
	 * Le favori d'un outil fermé depuis (`Favorite::isOpen()`) reste en base et ne se lit plus : il
	 * reviendra au menu si l'outil se rouvre.
	 */
	public static function getByStructure(\structure\Structure $eStructure): \Collection {

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->empty()) {
			return new \Collection();
		}

		$toolsClosed = self::getClosed($eStructure);

		return self::$cachedByStructure[$eStructure['id']][$eUser['id']] ??= Favorite::model()
			->select(Favorite::getSelection())
			->whereStructure($eStructure)
			->whereUser($eUser)
			->whereTool('NOT IN', $toolsClosed, if: $toolsClosed !== [])
			->sort(['id' => SORT_ASC])
			->getCollection(index: 'tool');

	}

	public static function create(Favorite $e): void {

		parent::create($e);

		self::$cachedByStructure = [];

	}

	public static function delete(Favorite $e): void {

		parent::delete($e);

		self::$cachedByStructure = [];

	}

	/**
	 * Les favoris qui comptent dans le plafond sont ceux que le menu montre : le favori d'un outil
	 * fermé n'y prend pas de place.
	 */
	public static function countByStructure(\structure\Structure $eStructure, \user\User $eUser): int {

		$toolsClosed = self::getClosed($eStructure);

		return Favorite::model()
			->whereStructure($eStructure)
			->whereUser($eUser)
			->whereTool('NOT IN', $toolsClosed, if: $toolsClosed !== [])
			->count();

	}

	/**
	 * Les outils fermés à la personne connectée dans cette structure.
	 */
	private static function getClosed(\structure\Structure $eStructure): array {

		return array_values(array_filter(
			Favorite::model()->getPropertyEnum('tool'),
			fn(string $tool) => Favorite::isOpen($eStructure, $tool) === FALSE
		));

	}

	public static function getOne(\structure\Structure $eStructure, string $tool): Favorite {

		return Favorite::model()
			->select(Favorite::getSelection())
			->whereStructure($eStructure)
			->whereUser(\user\ConnectionLib::getOnline())
			->whereTool($tool)
			->get();

	}

}
?>
