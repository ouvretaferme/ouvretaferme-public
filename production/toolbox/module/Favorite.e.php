<?php
namespace toolbox;

/**
 * Un outil de la Boîte à outils qu'une personne a mis dans son menu, sous l'onglet.
 *
 * L'étoile ne fait que ça : tout outil s'ouvre depuis la Boîte à outils sans elle (arbitrage QO3
 * de la planche pf-outils, « les outils peuvent être utilisés sans * »).
 */
class Favorite extends FavoriteElement {

	public function canRead(): bool {

		$this->expects(['structure', 'user']);

		return (
			$this['structure']->canWrite() and
			$this['user']->is(\user\ConnectionLib::getOnline())
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('tool')
			->addAfterCheck('tooMany', function(): bool {

				$this->expects(['structure', 'user']);

				return (FavoriteLib::countByStructure($this['structure'], $this['user']) < FavoriteLib::MAX);

			});

		// Un outil fermé à la personne (`isOpen()`) n'est pas dans la Boîte (`ToolUi::getList()`), et ne
		// doit pas pouvoir se mettre au menu.
		$l->property('tool')
			->addAfterCheck('closed', function(string $tool): bool {

				$this->expects(['structure']);

				return self::isOpen($this['structure'], $tool);

			});

	}

	/**
	 * Un outil est ouvert à la personne connectée, sauf les paniers, qui ne s'ouvrent qu'aux
	 * structures productrices (`Structure::acceptComposition()`) et seulement à leurs gestionnaires,
	 * et les aides agricoles, qui ne s'ouvrent qu'aux structures de leur bêta (`AidSetting::isActive()`).
	 *
	 * La question se pose à l'écriture d'un favori et à sa lecture (`FavoriteLib::getByStructure()`) :
	 * un favori posé quand l'outil était ouvert survit à sa fermeture, que la structure cesse de
	 * produire ou que la personne perde la gestion, et le menu mènerait alors à une page refusée.
	 */
	public static function isOpen(\structure\Structure $eStructure, string $tool): bool {

		return match($tool) {
			Favorite::BASKET => ($eStructure->acceptComposition() and $eStructure->canManage()),
			Favorite::AID => \aid\AidSetting::isActive($eStructure),
			Favorite::RAIN, Favorite::TREATMENT, Favorite::DENSITY, Favorite::CATALOG, Favorite::MAINTENANCE, Favorite::WATER, Favorite::FERTILIZATION => TRUE
		};

	}

}
?>
