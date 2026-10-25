<?php
namespace planning;

/**
 * « Ajouter à un stock » sur toute saisie de récolte du nouveau planning (DG-4) : le tiroir, la feuille et
 * « Nouvelle récolte » des Récoltes, la récolte d'une carte, la fiche d'une intervention, « Nouvelle
 * intervention » et « Récolter ». Le champ est celui de l'ancien planning (`\series\TaskUi::getStockField()`) :
 * un produit compatible avec la récolte, et l'étoile qui le retient pour les saisies suivantes.
 *
 * Tout se lit et s'écrit sur une référence (`getReference()`), une récolte dans l'unité de la saisie : la liste
 * des stocks dépend de l'unité, et l'étoile s'enregistre avec elle (`\product\StockLib::remember()`). Une
 * intervention qui n'a encore rien récolté n'a pas d'unité, et une étoile posée sans unité ne se relirait
 * jamais.
 */
class HarvestStockLib {

	/**
	 * La récolte d'une intervention dans l'unité de la saisie, relue en entier : la variété porte son nom, par
	 * lequel `\product\StockLib::getCompatibleProducts()` range les stocks, et l'action le sien, qui commente le
	 * mouvement de stock. Une intervention que la saisie va créer n'a que les identifiants qu'elle a reçus.
	 */
	public static function getReference(\series\Task $eTask, string $unit): \series\Task {

		$eTask->expects(['structure', 'cultivation', 'plant', 'variety', 'harvestSize']);

		return self::getProbe($eTask['structure'], $eTask['plant'], $eTask['cultivation'], $eTask['variety'], $eTask['harvestSize'], $unit);

	}

	/**
	 * Une récolte de l'espèce, de la culture quand il y en a une, dans une unité, qui n'est ni lue ni écrite.
	 */
	public static function getProbe(\structure\Structure $eStructure, \plant\Plant $ePlant, \series\Cultivation $eCultivation, \plant\Variety $eVariety, \plant\Size $eSize, string $unit): \series\Task {

		return new \series\Task([
			'structure' => $eStructure,
			'cultivation' => $eCultivation,
			'plant' => $ePlant,
			'action' => \work\ActionLib::getByStructure($eStructure, fqn: ACTION_RECOLTE),
			'variety' => $eVariety->empty() ? new \plant\Variety() : \plant\VarietyLib::getById($eVariety['id']),
			'harvestSize' => $eSize,
			'harvestUnit' => $unit,
		]);

	}

	/**
	 * La sonde du champ que redessine `harvestWeek:stock` quand l'unité ou la variété d'une saisie change : la
	 * culture bornée à la structure, dont l'espèce découle, ou l'espèce seule d'une « Nouvelle intervention »
	 * (BY-bis), et la variété de cette espèce. Une culture et une espèce postées ensemble ne se croisent pas :
	 * la culture l'emporte.
	 */
	public static function getProbeForField(\structure\Structure $eStructure, ?int $cultivation, ?int $plant, ?int $variety, string $unit): \series\Task {

		if($cultivation !== NULL) {

			$eCultivation = \series\CultivationLib::getById($cultivation)
				->validate('canRead')
				->validateProperty('structure', $eStructure);

			$ePlant = $eCultivation['plant'];

		} else {

			$eCultivation = new \series\Cultivation();

			$ePlant = \plant\PlantLib::getById($plant)
				->validate('canRead')
				->validateProperty('structure', $eStructure);

		}

		if($variety === NULL) {
			$eVariety = new \plant\Variety();
		} else {

			$eVariety = \plant\VarietyLib::getById($variety)->validate('canRead');

			if($eVariety['plant']->is($ePlant) === FALSE) {
				throw new \NotExpectedAction('Variety of another plant');
			}

		}

		return self::getProbe($eStructure, $ePlant, $eCultivation, $eVariety, new \plant\Size(), $unit);

	}

	/**
	 * Les stocks que le champ propose pour une récolte, et celui qu'elle a mémorisé.
	 *
	 * @return array [les produits compatibles, le produit mémorisé]
	 */
	public static function getByReference(\series\Task $eTaskReference): array {

		return [
			\product\StockLib::getCompatibleProducts($eTaskReference),
			\product\StockLib::getBookmark($eTaskReference),
		];

	}

	/**
	 * La référence avec ce que le champ affiche, les stocks qu'il propose (`cProductStock`) et celui qu'elle a
	 * mémorisé (`eProductBookmark`). Elle est rendue, et non seulement remplie : la référence naît dans l'appel
	 * même (`fill(getReference(…))`), et c'est elle que la page fait descendre à l'UI.
	 */
	public static function fill(\series\Task $eTaskReference): \series\Task {

		[$eTaskReference['cProductStock'], $eTaskReference['eProductBookmark']] = self::getByReference($eTaskReference);

		return $eTaskReference;

	}

	/**
	 * Le produit mémorisé, s'il reste de ceux que le champ propose : une saisie sans champ, comme la case de
	 * la grille des Récoltes, verse là où l'étoile a été posée, puisque sa confirmation promet d'« ajouter
	 * automatiquement les quantités récoltées » (`\series\TaskUi::getStockField()`).
	 */
	public static function getBookmarked(\series\Task $eTaskReference): \product\Product {

		[$cProductStock, $eProductBookmark] = self::getByReference($eTaskReference);

		if($eProductBookmark->empty()) {
			return new \product\Product();
		}

		return $cProductStock->findById($eProductBookmark['id'], default: new \product\Product());

	}

	/**
	 * Le stock que la saisie a choisi, refusé (`Task::stock.check`) quand il n'est pas de ceux que le champ
	 * propose pour cette récolte ou qu'il est d'une autre structure. Un champ laissé sur « Pas de mise en
	 * stock » rend un produit vide. Sans champ posté, ce qui arrive quand aucun stock n'est compatible
	 * (`getStockField()` rend alors un cadre vide) ou quand la saisie n'en a pas la place, la récolte va au
	 * produit mémorisé (`getBookmarked()`), et l'étoile ne change pas (`NULL`).
	 *
	 * @return array [le produit, vide quand rien ne va au stock ; l'étoile, `NULL` quand elle ne change pas]
	 */
	public static function build(\structure\Structure $eStructure, \series\Task $eTaskReference, array $input): array {

		if(array_key_exists('stock', $input) === FALSE) {
			return [self::getBookmarked($eTaskReference), NULL];
		}

		$remember = var_filter($input['stockRemember'] ?? NULL, 'bool');
		$product = var_filter($input['stock'], '?int');

		if($product === NULL) {
			return [new \product\Product(), $remember];
		}

		[$cProductStock] = self::getByReference($eTaskReference);

		$eProduct = $cProductStock->findById($product, default: new \product\Product());

		// Les stocks compatibles se lisent par l'espèce, et un produit d'une autre structure peut porter celle-ci :
		// l'étape `plant` de `\product\Product` admet l'espèce de toute structure dont l'auteur est membre.
		if($eProduct->empty() or $eProduct['structure']->is($eStructure) === FALSE) {
			\series\Task::fail('stock.check');
			return [new \product\Product(), NULL];
		}

		return [$eProduct, $remember];

	}

	/**
	 * La quantité de la saisie au stock choisi, signée : un retrait, ou un remplacement qui baisse le jour, le
	 * baisse d'autant, sans le faire passer sous zéro. Puis l'étoile, posée sur le produit choisi ou retirée,
	 * quand la saisie avait le champ.
	 */
	public static function write(\series\Task $eTaskReference, \product\Product $eProduct, ?bool $remember, float $quantity): void {

		\series\Task::model()->beginTransaction();

			if($eProduct->notEmpty() and $quantity !== 0.0) {
				\product\StockLib::increment($eProduct, $quantity, new \product\Stock([
					'comment' => $eTaskReference['action']['name']
				]), zeroIfNegative: TRUE);
			}

			if($remember === TRUE) {
				\product\StockLib::remember($eTaskReference, $eProduct);
			} else if($remember === FALSE) {
				\product\StockLib::forget($eTaskReference);
			}

		\series\Task::model()->commit();

	}

}
?>
