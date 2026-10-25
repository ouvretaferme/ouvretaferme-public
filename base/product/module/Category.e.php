<?php
namespace product;

class Category extends CategoryElement {

	public function canRead(): bool {
		$this->expects(['structure']);
		return $this['structure']->canSelling();
	}

	public function canWrite(): bool {
		$this->expects(['structure']);
		return $this['structure']->canManage();
	}

	public static function getIcons(): array {

		return [
			'legume', 'tomate', 'champignon',
			'fruit', 'noyau',
			'plant', 'arbre', 'epicea', 'aromatique', 'fleur',
			'pot', 'verre', 'marmite', 'confiture', 'huile', 'cereale', 'farine',
			'jus', 'biere', 'vin', 'bouteille',
			'pain', 'biscuit', 'chocolat', 'glace',
			'oeuf', 'lait', 'fromage', 'viande', 'volaille', 'charcuterie', 'poisson', 'miel', 'vache', 'chevre', 'mouton', 'cochon', 'poule',
			'savon', 'tisane', 'laine',
			'panier', 'restauration', 'cadeau', 'producteur',
			'boite', 'coeur', 'etoile', 'liste', 'plus', 'pourcentage',
		];

	}

	public static function properties(\PropertyList $l): void {

		$l->property('icon')
			->replaceCheck(function(?string $icon) {

				return (
					$icon == NULL or
					in_array($icon, \product\Category::getIcons())
				);

			});

	}

}
?>