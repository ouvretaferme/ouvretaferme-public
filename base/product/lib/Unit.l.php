<?php
namespace product;

class UnitLib extends UnitCrud {

	use \Notifiable;

	/**
	 * Les unités convertibles entre elles, et **le nombre entier** de la plus petite unité de leur
	 * famille physique qu'elles valent — le gramme pour les poids, le millilitre pour les volumes.
	 *
	 * Des entiers, et la plus petite unité pour référence, pour une raison mesurée : écrite en
	 * fractions du kilo, la table donnait `gram → ton = round(0.000001, 4)` = **0.0**. Un appelant
	 * qui divise par ce facteur rendait alors 500 sur toute une boutique publique, et un demi-million
	 * de grammes se détruisait sous un message de succès. Aucun arrondi ici : le rapport de deux
	 * entiers dit exactement ce qu'il vaut, sur toute l'amplitude de la table — du millionième au
	 * million.
	 *
	 * **Distincte de `quotation\SuggestionLib::UNITS`, et il faut qu'elle le reste, y compris le
	 * jour du rapatriement.** Celle-là sert les relevés de prix, où `pot` vaut une pièce parce
	 * qu'un plant en godet se vend à l'unité : c'est une convention commerciale, vraie pour un prix
	 * et fausse pour une quantité. Les fondre remettrait `pot = pièce` dans un calcul de stock,
	 * l'obstacle même qui a obligé à écrire celle-ci le 2026-09-06. Elle est aussi le seul modèle
	 * de familles physiques du dépôt, et le seul à porter les volumes : conservée par décision du
	 * 2026-09-08 malgré le retrait de son appelant, pour le rapatriement de cette seconde table
	 * sur `product\Unit` (backlog n° 13).
	 *
	 * **Tout ce qui compte des objets en est absent volontairement** — pièce, unité, botte,
	 * barquette, sachet, bouteille, colis, boîte, pot, forfait, heure. Une botte n'a pas de poids,
	 * et deux unités qui comptent ne se convertissent qu'avec elles-mêmes : aucun facteur n'est
	 * jamais inventé.
	 */
	const CONVERSIONS = [
		'ton' => ['weight', 1000000],
		'kg' => ['weight', 1000],
		'gram-500' => ['weight', 500],
		'gram-250' => ['weight', 250],
		'gram-100' => ['weight', 100],
		'gram' => ['weight', 1],
		'liter' => ['volume', 1000],
		'centiliter' => ['volume', 10],
		'milliliter' => ['volume', 1],
	];

	/**
	 * Combien d'unités de $eUnitTo vaut une unité de $eUnitFrom. NULL quand la conversion
	 * n'existe pas : deux unités qui comptent des objets différents, ou deux familles physiques
	 * distinctes.
	 *
	 * La même unité rend 1.0 sans passer par la table : une botte vaut une botte, et une unité
	 * que la structure s'est créée elle-même se convertit avec elle-même. Seule l'identité
	 * l'autorise, jamais une équivalence supposée.
	 */
	public static function getConversion(Unit $eUnitFrom, Unit $eUnitTo): ?float {

		$eUnitFrom->expects(['fqn']);
		$eUnitTo->expects(['fqn']);

		// Une unité s'identifie par son `fqn`. Celle qu'une structure s'est créée elle-même n'en a
		// pas et ne s'identifie alors que par sa ligne — d'où la lecture de l'id sur cette seule
		// branche, la seule où il dise quelque chose que le fqn ne dit pas.
		$sameUnit = ($eUnitFrom['fqn'] !== NULL) ?
			($eUnitFrom['fqn'] === $eUnitTo['fqn']) :
			($eUnitTo['fqn'] === NULL and $eUnitFrom['id'] === $eUnitTo['id']);

		if($sameUnit) {
			return 1.0;
		}

		$from = self::CONVERSIONS[$eUnitFrom['fqn']] ?? NULL;
		$to = self::CONVERSIONS[$eUnitTo['fqn']] ?? NULL;

		if(
			$from === NULL or
			$to === NULL or
			$from[0] !== $to[0]
		) {
			return NULL;
		}

		return $from[1] / $to[1];

	}


	public static function getPropertiesCreate(?string $origin = NULL): array {

		return ['singular', 'plural', 'short', 'type', 'approximate'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return self::getPropertiesCreate();
	}

	public static function duplicateForStructure(\structure\Structure $eStructure): void {

		$cUnit = Unit::model()
			->select(Unit::getSelection())
			->whereStructure(NULL)
			->getCollection();

		$cUnit->map(function(Unit $eUnit) use($eStructure) {
			$eUnit['id'] = NULL;
			$eUnit['structure'] = $eStructure;
		});

		Unit::model()->insert($cUnit);

	}

	public static function getOneByStructure(\structure\Structure $eStructure, string $fqn): Unit {

		return Unit::model()
			->select(Unit::getSelection())
			->whereStructure($eStructure)
			->whereFqn($fqn)
			->get();

	}

	public static function getByStructure(\structure\Structure $eStructure, string|\Sql|null $sort = 'singular', ?string $index = NULL): \Collection {

		return Unit::model()
			->select(Unit::getSelection())
			->whereStructure($eStructure)
			->sort($sort)
			->getCollection(index: $index);

	}

	public static function create(Unit $e): void {

		try {
			parent::create($e);
		} catch(\DuplicateException) {
			Unit::fail('singular.duplicate');
		}

	}

	public static function update(Unit $e, array $properties): void {

		try {
			parent::update($e, $properties);
		} catch(\DuplicateException) {
			Unit::fail('singular.duplicate');
		}

	}

	public static function delete(Unit $e): void {

		$e->expects(['id', 'structure']);

		if(
			\product\Product::model()
				->whereStructure($e['structure'])
				->whereUnit($e)
				->exists() or
			(
				\structure\StructureSetting::hasCommercialisationApp() and
				in_array(TRUE, self::notify('isUnitUsed', $e), TRUE)
			)
		) {
			Unit::fail('deleteUsed');
			return;
		}

		parent::delete($e);

	}

}
?>
