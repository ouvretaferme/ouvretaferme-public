<?php
namespace map;

class DecorBuilding extends DecorBuildingElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => \structure\StructureElement::getSelection(),
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Un bâtiment importé n'a pas de composition à retoucher : il se supprime, et un autre s'importe.
	 */
	public function acceptUpdate(): bool {

		$this->expects(['upload']);

		return $this['upload']->empty();

	}

	/**
	 * Les cotes et les choix se confrontent à `choices.json` (`DecorBuildingLib::getChoices()`), par type : le `.yml` borne
	 * ce que tous les types admettent, le fichier ce que chacun admet. Une cote absente du type (`width` d'un hangar, qui garde
	 * la largeur du hangar du catalogue, `height` d'une maison, que ses niveaux font) reste vide, les autres sont dues.
	 *
	 * Le type se construit le premier (`DecorBuildingLib::getPropertiesCreate()`), et une étape qui le lit
	 * s'abandonne quand il est refusé : le refus est déjà dit.
	 */
	public static function properties(\PropertyList $l): void {

		// Un hangar ne se compose pas de rien : il personnalise le hangar posé d'où l'éditeur s'est ouvert, que la page a relu
		// borné par la structure (`eDecor`), et seul un hangar en part
		$l->property('type')
			->addAfterCheck('decor', function(string $type): bool {

				$this->expects(['eDecor']);

				return (($type === DecorBuilding::HANGAR) === $this['eDecor']->notEmpty());

			});

		foreach(['length', 'width', 'height'] as $property) {

			$l->property($property)
				->addAfterCheck('type', function(?float $value, \PropertyList $l) use ($property): bool {

					$l->skipIfInvalid('type');

					$bounds = DecorBuildingLib::getChoices()[$this['type']][$property];

					return (($value === NULL) === ($bounds === NULL));

				})
				->addAfterCheck('bounds', function(?float $value, \PropertyList $l) use ($property): bool {

					$l->skipIfInvalid('type');

					if($value === NULL) {
						return TRUE;
					}

					[$min, $max] = DecorBuildingLib::getChoices()[$this['type']][$property];

					return ($value >= $min and $value <= $max);

				});

		}

		// Les choix se postent en JSON, comme la composition d'un avatar (`user\UserAvatar`) : un `json` est converti en tableau
		// par le cadriciel sans être décodé
		$l->property('settings')
			->replaceCast(function(mixed &$settings): bool {

				if(is_string($settings)) {
					$settings = json_decode($settings, TRUE);
				}

				return TRUE;

			})
			// Le JSON posté se décode en n'importe quelle valeur, un nombre ou une chaîne compris, et en NULL s'il est invalide :
			// la forme se juge ici et non dans la conversion, pour que tout refus porte `settings.check`
			->replaceCheck(function(mixed $settings): bool {
				return (is_array($settings) and array_is_list($settings) === FALSE);
			})
			// Une valeur par choix du type, ni clé de plus ni clé de moins, et chacune dans sa liste
			->addAfterCheck('choices', function(array $settings, \PropertyList $l): bool {

				$l->skipIfInvalid('type');

				$choices = DecorBuildingLib::getChoices()[$this['type']]['settings'];

				$keys = array_keys($settings);
				$expected = array_keys($choices);
				sort($keys);
				sort($expected);

				if($keys !== $expected) {
					return FALSE;
				}

				foreach($choices as $key => $values) {

					if(in_array($settings[$key], $values, TRUE) === FALSE) {
						return FALSE;
					}

				}

				return TRUE;

			})
			// Les combinaisons que le type écarte : une teinte qui n'est pas celle de sa couverture, quatre fenêtres sur deux niveaux
			->addAfterCheck('excluded', function(array $settings, \PropertyList $l): bool {

				$l->skipIfInvalid('type');

				return (DecorBuildingLib::isExcluded($this['type'], $settings) === FALSE);

			});

	}

}
?>
