<?php
namespace map;

class Zone extends ZoneElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => \structure\StructureElement::getSelection(),
		];

	}

	public function getArea(): string {

		if($this['area'] > 1000) {
			$area = s("{value} ha", \util\TextUi::number($this['area'] / 10000, 2));
		} else {
			$area = s("{value} m²", $this['area']);
		}

		return $area;

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('use')
			->addAfterCheck('empty', function(?string $use): bool {

				return ($use !== NULL);

			});

		$l->property('area')
			// Un contour posté donne sa surface : celle qu'envoie le navigateur n'est qu'un affichage, et un client de l'API
			// n'a pas à pouvoir en enregistrer une autre. Les listes construisent donc le contour avant la surface.
			->addAfterCast('drawn', function(&$area, \PropertyList $l): bool {

				$l->skipIfInvalid('coordinates');

				if($l->isBuilt('coordinates') and $this['coordinates'] !== NULL) {
					$area = MapLib::getArea($this['coordinates']);
				}

				return TRUE;

			});

		$l->property('coordinates')
			// Le schéma ne juge que des points : deux sommets ou une latitude hors du globe passeraient
			// jusqu'à la base, qui répondrait par une erreur SQL. L'absence de contour reste une valeur,
			// celle d'une parcelle pas encore tracée, sauf sur la route du tracé, qui n'existe que pour en poser un.
			->addAfterCheck('ring', function(?array &$coordinates, \PropertyList $l): bool {

				if($coordinates === NULL) {
					return ($l->for !== 'shape');
				}

				$coordinates = MapLib::castPoints($coordinates);

				return MapLib::isRing($coordinates);

			});

		$l->property('seasonFirst')
			->addAfterCheck('structure', function(?int $season): bool {
				return ($season === NULL or $season >= $this['structure']->conf(Configuration::class)['seasonFirst']);
			});

		$l->property('seasonLast')
			->addAfterCheck('structure', function(?int $season): bool {
				return ($season === NULL or $season <= $this['structure']->conf(Configuration::class)['seasonLast']);
			})
			->addAfterCheck('consistency', function(?int $season): bool {
				return (
					$this['seasonFirst'] === NULL or
					$season === NULL or
					$this['seasonFirst'] <= $season
				);
			});

	}

}
?>