<?php
namespace toolbox;

class CompositionFormula extends CompositionFormulaElement {

	public function canRead(): bool {

		$this->expects(['plan']);

		return $this['plan']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['plan']);

		return $this['plan']->canWrite();

	}

	public function acceptWrite(): bool {

		$this->expects(['plan']);

		return $this['plan']->acceptWrite();

	}

	/**
	 * La base de la taille (lot r17), lue sur le plan de la taille : vide quand la taille se compose
	 * seule, ou quand `base` désigne une formule d'un autre plan, que le refus `plan` écarte.
	 */
	public function getBase(): CompositionFormula {

		$this->expects(['base']);

		if($this['base']->empty()) {
			return new CompositionFormula();
		}

		$this->expects(['plan']);

		return CompositionFormulaLib::getByPlanAndId($this['plan'], $this['base']['id']);

	}

	/**
	 * Chaque borne du nombre de légumes se compare à l'autre telle que la formule la porte. Construites
	 * ensemble, dans « Modifier les paniers » (r31), le plancher laisse la comparaison au plafond,
	 * construit après lui, qui la fait sur le plancher qu'il vient de recevoir.
	 *
	 * Les autres tailles du plan, base et dérivées, se lisent en base : une taille se construit contre
	 * les autres telles qu'elles sont enregistrées, même quand le même envoi les change aussi.
	 */
	public static function properties(\PropertyList $l): void {

		// Un plan ne porte pas deux tailles du même nom (lot r40), à la création comme au renommage :
		// une taille qui garde son nom ne se compare pas à elle-même. La comparaison se fait en base,
		// dans le classement de la colonne, pour refuser exactement ce que l'unicité `(plan, name)` du
		// schéma refuserait : « grand » après « Grand » aussi.
		//
		// Au départ, le plan n'est pas écrit et ses tailles se construisent ensemble : il n'y a rien
		// en base à quoi comparer, et deux tailles du même nom dans un même envoi sont retenues par
		// l'unicité (`CompositionPlanLib::create()`, `CompositionFormulaLib::updateByPlan()`).
		$l->property('name')
			->addAfterCheck('duplicate', function(string $name): bool {

				$this->expects(['plan']);

				if($this['plan']->exists() === FALSE) {
					return TRUE;
				}

				return CompositionFormula::model()
					->wherePlan($this['plan'])
					->whereName($name)
					->whereId('!=', $this, if: $this->exists())
					->exists() === FALSE;

			});

		$l->property('linesMin')
			->addAfterCheck('aboveMax', function(int $linesMin, \PropertyList $l): bool {

				if($l->hasProperty('linesMax')) {
					return TRUE;
				}

				$this->expects(['linesMax']);

				return ($linesMin <= $this['linesMax']);

			});

		$l->property('linesMax')
			->addAfterCheck('belowMin', function(int $linesMax, \PropertyList $l): bool {

				// Un plancher que le schéma vient de refuser n'est pas posé sur la formule : le refus
				// est déjà dit sur son champ, et il n'y a rien à comparer.
				if($l->isInvalid('linesMin')) {
					return TRUE;
				}

				$this->expects(['linesMin']);

				return ($this['linesMin'] <= $linesMax);

			})
			// Une taille qui s'appuie sur une autre en hérite toutes les lignes (lot r17) : son plafond
			// ne descend pas sous celui de sa base, et celui d'une base ne dépasse pas celui de ses
			// dérivées. Une taille neuve n'a pas de dérivée. Une base construite avec le plafond, au
			// départ comme dans « Modifier les paniers » (r31), est comparée par son étape `lines`, sur le
			// plafond qui vient d'être construit : seules les dérivées restent à comparer ici.
			->addAfterCheck('belowBase', function(int $linesMax, \PropertyList $l): bool {

				if($this->exists() === FALSE) {
					return TRUE;
				}

				if($l->hasProperty('base') === FALSE) {

					$eCompositionFormulaBase = $this->getBase();

					if($eCompositionFormulaBase->notEmpty() and $linesMax < $eCompositionFormulaBase['linesMax']) {
						return FALSE;
					}

				}

				return CompositionFormulaLib::getByBase($this)->find(fn(CompositionFormula $e) => $e['linesMax'] < $linesMax)->empty();

			});

		// Le prix d'une taille dérivée dépasse celui de sa base, et celui d'une base reste sous ceux de
		// ses dérivées : un grand panier moins cher que le petit ne peut pas le contenir. Une taille
		// neuve n'a pas de dérivée. Une base construite avec le prix, au départ comme dans « Régler les
		// paniers » (r31), est comparée par son étape `cheaper`, sur le prix qui vient d'être
		// construit : seules les dérivées restent à comparer ici.
		$l->property('price')
			->addAfterCheck('order', function(float $price, \PropertyList $l): bool {

				if($this->exists() === FALSE) {
					return TRUE;
				}

				if($l->hasProperty('base') === FALSE) {

					$eCompositionFormulaBase = $this->getBase();

					if($eCompositionFormulaBase->notEmpty() and (float)$eCompositionFormulaBase['price'] >= $price) {
						return FALSE;
					}

				}

				return CompositionFormulaLib::getByBase($this)->find(fn(CompositionFormula $e) => (float)$e['price'] <= $price)->empty();

			});

		// La taille sur laquelle celle-ci s'appuie (lot r17), un refus par raison. Un seul étage :
		// une base n'a pas de base, et une taille qui sert de base n'en prend pas, ce qui écarte toute
		// chaîne et donc tout cycle sans parcourir de graphe. Une base peut avoir plusieurs dérivées
		// (12, 18 et 25 € : deux dérivées de la taille à 12 €).
		$l->property('base')
			->addAfterCheck('plan', function(CompositionFormula $eCompositionFormulaBase): bool {

				if($eCompositionFormulaBase->empty()) {
					return TRUE;
				}

				$this->expects(['plan']);

				return CompositionFormulaLib::getByPlanAndId($this['plan'], $eCompositionFormulaBase['id'])->notEmpty();

			})
			->addAfterCheck('self', function(CompositionFormula $eCompositionFormulaBase): bool {

				return (
					$eCompositionFormulaBase->empty() or
					$this->exists() === FALSE or
					$eCompositionFormulaBase['id'] !== $this['id']
				);

			})
			->addAfterCheck('chain', function(CompositionFormula $eCompositionFormulaBase): bool {

				if($eCompositionFormulaBase->empty()) {
					return TRUE;
				}

				$this->expects(['plan']);

				if(CompositionFormulaLib::getByPlanAndId($this['plan'], $eCompositionFormulaBase['id'])['base']->notEmpty()) {
					return FALSE;
				}

				return ($this->exists() === FALSE or CompositionFormulaLib::getByBase($this)->empty());

			})
			->addAfterCheck('cheaper', function(CompositionFormula $eCompositionFormulaBase): bool {

				if($eCompositionFormulaBase->empty()) {
					return TRUE;
				}

				$this->expects(['plan', 'price']);

				return ((float)CompositionFormulaLib::getByPlanAndId($this['plan'], $eCompositionFormulaBase['id'])['price'] < (float)$this['price']);

			})
			// Une taille neuve n'a pas encore de bornes à comparer : elle prend celles de sa base
			// (`CompositionFormulaLib::add()`), que le panneau « Ajouter une taille » ne demande pas.
			->addAfterCheck('lines', function(CompositionFormula $eCompositionFormulaBase): bool {

				if($eCompositionFormulaBase->empty() or $this->exists() === FALSE) {
					return TRUE;
				}

				$this->expects(['plan', 'linesMax']);

				return (CompositionFormulaLib::getByPlanAndId($this['plan'], $eCompositionFormulaBase['id'])['linesMax'] <= $this['linesMax']);

			});

	}

}
?>
