<?php
namespace toolbox;

class CompositionLine extends CompositionLineElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'species' => ['name', 'plant' => ['fqn', 'name', 'vignette', 'organ']],
			'unit' => ['singular', 'plural', 'short', 'type'],
		];

	}

	public function canRead(): bool {

		$this->expects(['week']);

		return $this['week']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['week']);

		return $this['week']->canWrite();

	}

	public function acceptWrite(): bool {

		$this->expects(['week']);

		return $this['week']->acceptWrite();

	}

	/**
	 * Le plan de la semaine de la ligne, que les étapes de `build()` lisent pour en chiffrer la source.
	 */
	private function getPlan(): CompositionPlan {

		$this->expects(['week']);

		return CompositionWeek::model()
			->select(['formula' => ['plan' => ['structure', 'season', 'reference', 'weeks']]])
			->whereId($this['week'])
			->get()['formula']['plan'];

	}

	/**
	 * Le pas et la quantité minimale de l'espèce de la ligne dans son plan, dans l'unité de la ligne
	 * (`CompositionLib::getLineStep()`). Le plan se relit par la semaine et l'unité par son `id`, tout
	 * ce qu'un envoi en porte : une ligne dans une autre unité que celle de l'espèce prend le pas de
	 * son type.
	 */
	private function getStep(): array {

		$this->expects(['species', 'unit']);

		$eCompositionPlan = $this->getPlan();

		$eUnit = \product\UnitLib::getById($this['unit'], ['id', 'type']);

		return CompositionLib::getLineStep($this['species']['id'], $eUnit, CompositionLib::getSourceByPlan($eCompositionPlan), CompositionSpeciesLib::getStepsByPlan($eCompositionPlan));

	}

	public static function properties(\PropertyList $l): void {

		// Une quantité saisie à la main tient les bornes de la quantité proposée (revue v12) : jamais
		// sous le minimum de l'espèce, et en pas entiers. Le pas se dit dans l'unité de la ligne (lot
		// r14) : une espèce ou une unité refusées par le même `build()` (la création d'une ligne par
		// le menu d'une case, lot r24b) n'en ont pas, et leur refus suffit à la ligne.
		$l->property('quantity')
			->addAfterCheck('minimum', function(float $quantity, \PropertyList $l): bool {
				return ($l->isInvalid('species') or $l->isInvalid('unit') or $quantity >= $this->getStep()['minimum']);
			})
			->addAfterCheck('step', function(float $quantity, \PropertyList $l): bool {
				return ($l->isInvalid('species') or $l->isInvalid('unit') or CompositionLib::isStepMultiple($quantity, $this->getStep()['step']));
			});

		// La ligne se valorise par aggregate\Price, dont la clé est l'unité du référentiel partagé.
		// Une unité clonée par une structure porte le même nom et un autre identifiant : acceptée
		// ici, elle ne trouverait aucun prix et la ligne compterait pour zéro sans que rien ne le dise.
		$l->property('unit')
			->addAfterCheck('reference', function(\product\Unit $eUnit): bool {

				return \product\Unit::model()
					->whereStructure(NULL)
					->exists($eUnit);

			})
			// Une ligne ajoutée entre au panier dans l'unité de son espèce (lot r24b) : c'est celle où
			// le champ du menu dit sa quantité, et celle dont le pas et le minimum se lisent. Une espèce
			// refusée n'a pas d'unité à comparer, et son refus suffit à la ligne. Une ligne proposée
			// s'écrit sans `build()`, et `unit` n'est pas une propriété qui se modifie : l'étape ne
			// joue qu'à la création d'une ligne par la case.
			->addAfterCheck('species', function(\product\Unit $eUnit, \PropertyList $l): bool {

				if($l->isInvalid('species')) {
					return TRUE;
				}

				return (CompositionLib::getSourceByPlan($this->getPlan())['units'][$this['species']['id']]['unit'] === $eUnit['id']);

			});

		// L'espèce est une espèce du plan de la semaine (r76) : c'est elle, et non l'espèce du
		// référentiel, qui porte l'unité, le prix et la saison de la ligne. Une espèce d'un autre plan
		// se poste aussi bien, et le serveur la refuse avant toute autre étape : un échec arrête la
		// chaîne, et les suivantes la chercheraient en vain dans la source du plan.
		$l->property('species')
			->addAfterCheck('owned', function(CompositionSpecies $eCompositionSpecies): bool {

				return CompositionSpecies::model()
					->wherePlan($this->getPlan())
					->exists($eCompositionSpecies);

			})
			// Une espèce ne se sert que si la communauté lui connaît une unité au panier, sans laquelle
			// sa ligne n'aurait ni quantité de référence ni prix. Sa fenêtre ne compte pas (lot r8,
			// planche r8) : une ligne servie hors de ses semaines de récolte reste valable, et
			// `CompositionLib::getLineReference()` lui trouve alors un prix. Une ligne proposée a
			// toujours son unité, par construction.
			->addAfterCheck('unsold', function(CompositionSpecies $eCompositionSpecies): bool {

				return CompositionLib::isSold($eCompositionSpecies['id'], CompositionLib::getSourceByPlan($this->getPlan()));

			})
			// Une semaine ne sert une espèce qu'une fois : sa case est pleine ou vide, et une seconde
			// ligne doublerait sa quantité sans que la grille puisse le montrer. Deux envois simultanés
			// passent tous deux ce contrôle : c'est l'unicité `(week, species)` du schéma qui retient le
			// second, et `CompositionLineLib::create()` rend alors ce même refus (lot r40).
			->addAfterCheck('duplicate', function(CompositionSpecies $eCompositionSpecies): bool {

				return CompositionLine::model()
					->whereWeek($this['week'])
					->whereSpecies($eCompositionSpecies)
					->exists() === FALSE;

			})
			// Une semaine ne sert pas une espèce sous ses deux noms (r71, décidé le
			// 03/10/2026, repris en r76 pour les variantes) : la carotte au kilo ne s'ajoute pas à côté
			// de la carotte en botte, ni l'inverse (`CompositionLib::isTwinRefused()`). Les deux noms
			// se lisent sur la source du plan, la seule qui sache qu'ils lisent la même espèce.
			->addAfterCheck('twin', function(CompositionSpecies $eCompositionSpecies): bool {

				$speciesIdsServed = CompositionLine::model()
					->whereWeek($this['week'])
					->getColumn('species')
					->getIds();

				return CompositionLib::isTwinRefused($eCompositionSpecies['id'], $speciesIdsServed, CompositionLib::getSourceByPlan($this->getPlan())) === FALSE;

			});

	}

}
?>
