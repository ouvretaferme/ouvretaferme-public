<?php
namespace toolbox;

/**
 * La saison de vente d'une espèce, pour un plan (lot r27).
 *
 * Une ligne par espèce proposable, écrite au départ du plan et pas seulement pour celles qu'on
 * touche : c'est ce qui permet de lire sans défaut, et c'est le « poser le défaut à l'écriture » du
 * `CLAUDE.md`. La saison s'écrit avec elle, depuis la communauté.
 *
 * L'état vit dans `status` et la donnée dans `windowWeeks`, ce qui rend la lecture exhaustive et
 * sans repli silencieux : une valeur inattendue échoue au lieu de rendre une saison par accident, ce
 * qui remettrait au panier une espèce que quelqu'un a décochée.
 */
class CompositionSpeciesLib extends CompositionSpeciesCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return array_merge(['plant'], self::getPropertiesUpdate());
	}

	/**
	 * `status` avant `windowWeeks`, `step` avant `quantityBasket` (r68) et `minimum`, `minimum` avant
	 * `quantity`, et `unit` avant `step`, `quantityBasket` et `weight` : le contrôle du second lit ce
	 * que le même `build()` vient de poser pour le premier, et `PropertyList` les construit dans
	 * l'ordre de cette liste.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['status', 'windowWeeks', 'unit', 'step', 'quantityBasket', 'price', 'minimum', 'quantity', 'weight', 'frequency', 'product'];
	}

	/**
	 * Une espèce que le départ écrit est l'espèce nue (r76) : seuls « Ajouter une espèce », qui
	 * construit le nom d'une variante (r76 lot 2, `getForAdd()`), et la reconduite d'un plan
	 * (`CompositionPlanLib::restart()`) écrivent une variante, nom compris. Le défaut se pose ici
	 * pour que la suite de l'écriture lise le nom sans garde (`getPlants()`).
	 */
	public static function create(CompositionSpecies $e): void {

		$e->add(['name' => NULL]);

		parent::create($e);

	}

	/**
	 * Les espèces du plan, indexées par leur id : la grille et les lignes de chaque taille y prennent
	 * l'espèce qu'elles affichent (`CompositionWeekLib::getByFormulaWithLines()`,
	 * `CompositionLib::getGrid()`).
	 */
	public static function getByPlan(CompositionPlan $eCompositionPlan): \Collection {

		$cCompositionSpecies = CompositionSpecies::model()
			->select(CompositionSpecies::getSelection())
			->wherePlan($eCompositionPlan)
			->getCollection(NULL, NULL, 'id');

		self::applySeasonsUnwritten($eCompositionPlan, $cCompositionSpecies);

		return $cCompositionSpecies;

	}

	/**
	 * Pose en mémoire, sur les espèces `$cCompositionSpecies` du plan qui n'ont pas encore de saison
	 * écrite, celle que la reprise du lot r27 leur écrira (`getSeasonsUnwritten()`), statut compris.
	 *
	 * Le code du lot est en ligne avant que la reprise tourne, puisqu'elle en emploie les fonctions
	 * (`migration/2026-09-28-paniers-saison-de-vente.md`, arbitrage r27 du 28/09) : un plan qu'elle
	 * n'a pas encore traité porte des espèces cochées sans saison, que la lecture ne saurait pas dire.
	 * Il se lit donc comme il se lira une fois repris, et aucune proposition ne change entre les deux.
	 *
	 * Le script de reprise a quitté le dépôt, mais une base de dev en retard le remonte par la ligne
	 * `git show` du fichier de migration, et il appelle cette méthode comme `getSeasonsUnwritten()`.
	 * Toutes deux se retirent avec leurs appels quand plus aucune base n'est en retard sur cette
	 * migration : plus aucune espèce cochée n'y est alors sans saison.
	 */
	public static function applySeasonsUnwritten(CompositionPlan $eCompositionPlan, \Collection $cCompositionSpecies): void {

		$cCompositionSpeciesUnwritten = $cCompositionSpecies->find(fn(CompositionSpecies $e) => ($e['status'] !== CompositionSpecies::EXCLUDED and $e['windowWeeks'] === NULL), clone: FALSE);

		if($cCompositionSpeciesUnwritten->empty()) {
			return;
		}

		$seasons = self::getSeasonsUnwritten($eCompositionPlan, $cCompositionSpeciesUnwritten);

		foreach($cCompositionSpeciesUnwritten as $eCompositionSpecies) {
			$eCompositionSpecies['status'] = $seasons[$eCompositionSpecies['id']]['status'];
			$eCompositionSpecies['windowWeeks'] = $seasons[$eCompositionSpecies['id']]['windowWeeks'];
		}

	}

	/**
	 * La saison que la reprise du lot r27 écrit sur chaque espèce `$cCompositionSpecies` du plan
	 * (`migration/2026-09-28-paniers-saison-de-vente.md`), `[id de l'espèce du plan => ['status' =>
	 * string, 'windowWeeks' => ?array]]`. Se retire avec `applySeasonsUnwritten()`, pour la même
	 * raison et au même moment.
	 *
	 * C'est exactement l'ensemble des semaines où la règle d'avant le lot rendait l'espèce candidate,
	 * trous compris (`CompositionLib::getBands()`, sans combler) : la proposition d'un plan repris est
	 * celle d'avant, et aucun panier ne change.
	 * - une espèce du plan de culture d'un plan en « Mes semaines de récolte » (`reference =
	 *   cultivation`, lot r9) était candidate sur les semaines de récolte prolongées de sa
	 *   conservation (`CompositionLib::getReferenceWindows()`), si elle se vend (`isSold()`) et que
	 *   la communauté relève un prix cette semaine-là ou que la structure en a un. Elle passe à
	 *   `CULTIVATION` ;
	 * - toute autre espèce l'était sur la saison de la communauté
	 *   (`CompositionLib::getNaturalWindows()`), et reste `NATURAL` ;
	 * - une saison réglée à la main (`CUSTOM`) ne faisait que borner l'une ou l'autre : elle devient
	 *   leur intersection, et reste `CUSTOM`.
	 * Une espèce qui n'était candidate sur aucune semaine n'entrait dans aucun panier : sans saison,
	 * elle passe à `EXCLUDED`, ce que le départ d'un plan fait aujourd'hui d'une espèce sans saison
	 * (`CompositionPlanLib::create()`).
	 *
	 * La source se lit sans aucune saison (`getSourceByPlan()` sur `[]`) : c'est ce que la reprise
	 * va poser, et la lire ici bouclerait sur `getWindowsByPlan()`.
	 */
	public static function getSeasonsUnwritten(CompositionPlan $eCompositionPlan, \Collection $cCompositionSpecies): array {

		$eCompositionPlan->expects(['id', 'structure', 'season', 'reference', 'weeks']);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan, []);

		$natural = CompositionLib::getNaturalWindows(CompositionLib::getSeasonWeeks(), $eCompositionPlan['season'], $source);

		$dated = [];

		if($eCompositionPlan['reference'] === CompositionPlan::CULTIVATION) {

			$harvestWeeks = CompositionLib::getHarvestWeeks($eCompositionPlan['structure'], $eCompositionPlan['season']);

			foreach(CompositionLib::getReferenceWindows($harvestWeeks, self::getPlants($cCompositionSpecies)['plants'], $source['storage']) as $speciesId => $window) {

				$dated[$speciesId] = [];

				if(CompositionLib::isSold($speciesId, $source) === FALSE) {
					continue;
				}

				foreach(array_keys($window) as $week) {

					if(CompositionLib::isSurveyed($week, $speciesId, $source) or isset($source['priced'][$speciesId])) {
						$dated[$speciesId][] = $week;
					}

				}

			}

		}

		$seasons = [];

		foreach($cCompositionSpecies as $eCompositionSpecies) {

			$speciesId = $eCompositionSpecies['id'];

			$weeks = array_key_exists($speciesId, $dated) ? $dated[$speciesId] : ($natural[$speciesId] ?? []);

			if($eCompositionSpecies['status'] === CompositionSpecies::CUSTOM) {
				$weeks = array_values(array_intersect($weeks, CompositionLib::getWindowWeeksList($eCompositionSpecies['windowWeeks'])));
			}

			if($weeks === []) {
				$status = CompositionSpecies::EXCLUDED;
			} else if($eCompositionSpecies['status'] === CompositionSpecies::CUSTOM) {
				$status = CompositionSpecies::CUSTOM;
			} else if(array_key_exists($speciesId, $dated)) {
				$status = CompositionSpecies::CULTIVATION;
			} else {
				$status = CompositionSpecies::NATURAL;
			}

			$seasons[$speciesId] = [
				'status' => $status,
				'windowWeeks' => ($weeks === []) ? NULL : CompositionLib::getBands($weeks),
			];

		}

		return $seasons;

	}

	/**
	 * Les espèces décochées du plan, par id d'espèce du référentiel : celles que le départ laisse
	 * décochées quand il repart de ce plan (`CompositionPlanLib::getForStart()`). Le départ ne coche
	 * que des espèces nues, et une variante retirée (r76) ne retire pas son espèce.
	 */
	public static function getExcludedByPlan(CompositionPlan $eCompositionPlan): array {

		return CompositionSpecies::model()
			->wherePlan($eCompositionPlan)
			->whereName(NULL)
			->whereStatus(CompositionSpecies::EXCLUDED)
			->getColumn('plant')
			->getIds();

	}

	/**
	 * La saison de chaque espèce du plan, `[id d'espèce du plan => [semaine, …]]`, sans les espèces retirées,
	 * qui n'en ont pas : ce que `CompositionLib::propose()` et la grille lisent pour dire les
	 * candidates de chaque semaine (`CompositionLib::getOrder()`).
	 */
	public static function getWindowsByPlan(CompositionPlan $eCompositionPlan): array {

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'plant', 'name', 'status', 'windowWeeks'])
			->wherePlan($eCompositionPlan)
			->whereStatus('!=', CompositionSpecies::EXCLUDED)
			->getCollection();

		self::applySeasonsUnwritten($eCompositionPlan, $cCompositionSpecies);

		return self::getWindows($cCompositionSpecies);

	}

	/**
	 * `getWindowsByPlan()` sur des espèces du plan déjà lues, saisons non écrites posées
	 * (`applySeasonsUnwritten()`, que `getByPlan()` appelle) : l'écran les lit une fois pour toutes
	 * ses lectures (chantier Performances, P7).
	 */
	public static function getWindows(\Collection $cCompositionSpecies): array {

		$windows = [];

		// La saison qu'une espèce reçoit sans l'avoir écrite peut la retirer (`applySeasonsUnwritten()`).
		foreach($cCompositionSpecies->find(fn(CompositionSpecies $e) => $e['status'] !== CompositionSpecies::EXCLUDED) as $eCompositionSpecies) {
			$windows[$eCompositionSpecies['id']] = $eCompositionSpecies->getSeasonWeeks();
		}

		return $windows;

	}

	/**
	 * Les prix tapés sur les espèces du plan, `[id d'espèce du plan => prix]`, sans les espèces qui n'en ont
	 * pas : ce que « Recommencer les paniers » reporte sur le plan qui remplace celui-ci
	 * (`CompositionPlanLib::restart()`).
	 */
	public static function getTypedPrices(CompositionPlan $eCompositionPlan): array {

		$prices = [];

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'price'])
			->wherePlan($eCompositionPlan)
			->wherePrice('!=', NULL)
			->getCollection();

		foreach($cCompositionSpecies as $eCompositionSpecies) {
			$prices[$eCompositionSpecies['id']] = $eCompositionSpecies['price'];
		}

		return $prices;

	}

	/**
	 * Les poids tapés sur les espèces du plan, `[id d'espèce du plan => poids]`, sans les espèces qui n'en ont
	 * pas : ce que « Recommencer les paniers » reporte sur le plan qui remplace celui-ci
	 * (`CompositionPlanLib::restart()`), comme les prix.
	 */
	public static function getTypedWeights(CompositionPlan $eCompositionPlan): array {

		$weights = [];

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'weight'])
			->wherePlan($eCompositionPlan)
			->whereWeight('!=', NULL)
			->getCollection();

		foreach($cCompositionSpecies as $eCompositionSpecies) {
			$weights[$eCompositionSpecies['id']] = $eCompositionSpecies['weight'];
		}

		return $weights;

	}

	/**
	 * Les pas, les quantités minimales et les quantités par défaut réglés sur les espèces du plan,
	 * `[id d'espèce du plan => ['step' => ?float, 'minimum' => ?float, 'quantity' => ?float]]`, sans les
	 * espèces qui n'en ont réglé aucun : ce que `CompositionLib::getStep()` fait passer devant le pas
	 * de l'unité, ce qu'un ajout à la main prend (`CompositionLib::getQuantityAdded()`, r30, #92), et
	 * ce que « Recommencer la saison » reporte sur le plan qui remplace celui-ci
	 * (`CompositionPlanLib::restart()`).
	 */
	public static function getStepsByPlan(CompositionPlan $eCompositionPlan): array {

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'step', 'minimum', 'quantity'])
			->wherePlan($eCompositionPlan)
			->where('step IS NOT NULL OR minimum IS NOT NULL OR quantity IS NOT NULL')
			->getCollection();

		return self::getSteps($cCompositionSpecies);

	}

	/**
	 * `getStepsByPlan()` sur des espèces du plan déjà lues, celles qui n'ont rien réglé comprises
	 * (chantier Performances, P7).
	 */
	public static function getSteps(\Collection $cCompositionSpecies): array {

		$steps = [];

		foreach($cCompositionSpecies->find(fn(CompositionSpecies $e) => ($e['step'] !== NULL or $e['minimum'] !== NULL or $e['quantity'] !== NULL)) as $eCompositionSpecies) {
			$steps[$eCompositionSpecies['id']] = [
				'step' => $eCompositionSpecies['step'],
				'minimum' => $eCompositionSpecies['minimum'],
				'quantity' => $eCompositionSpecies['quantity'],
			];
		}

		return $steps;

	}

	/**
	 * Les unités choisies sur les espèces du plan (lot r14), `[id d'espèce du plan => ['unit' => id d'unité,
	 * 'weight' => ?float]]`, avec le poids tapé, qui se dit dans cette unité, sans les espèces qui
	 * entrent au panier dans l'unité de la communauté : ce que `CompositionLib::applyUnits()` fait
	 * passer devant elle, et ce que « Recommencer les paniers » reporte sur le plan qui remplace
	 * celui-ci (`CompositionPlanLib::restart()`).
	 */
	/**
	 * Ce que le panneau « Régler une espèce » et les formulaires rapides de l'espèce écrivent de son
	 * prix, de son unité, de sa vente, de son poids et de sa conservation, tel que la grille le lit
	 * (`getWithPrices()`) : la grille, le panneau et le formulaire disent la même chose.
	 */
	public static function loadPriced(CompositionSpecies $e): void {

		$e->expects(['id', 'plan']);

		self::copyPriced($e, self::getWithPrices($e['plan'], CompositionLib::getSourceByPlan($e['plan']))[$e['id']]);

	}

	private static function copyPriced(CompositionSpecies $e, CompositionSpecies $eCompositionSpeciesPriced): void {

		foreach(['reference', 'conversion', 'eUnit', 'eUnitCommunity', 'minimumDefault', 'quantityDefault', 'stepUnit', 'divisible', 'weightReference', 'storage'] as $property) {
			$e[$property] = $eCompositionSpeciesPriced[$property];
		}

	}

	/**
	 * Les produits que le champ « Produit vendu » du panneau « Régler une espèce » propose (r39b),
	 * `cProductCandidate`, et que le contrôle `candidate` de `product` accepte : ceux que la page
	 * « Mettre en vente la semaine » propose pour l'espèce (`CompositionSaleLib::getProducts()`), de
	 * la même espèce et dans l'unité où elle entre au panier, celle que le même envoi vient de choisir
	 * comprise. Sans produit composé, le panneau n'a pas de canal : les produits vendus aux
	 * particuliers et ceux vendus aux professionnels se proposent ensemble (arbitrage du chef de
	 * projet sous mandat). Chacun porte `price`, le prix que l'espèce en prendrait
	 * (`CompositionSaleLib::getPriceBasket()`), que le libellé de son choix dit et que le prix figé
	 * reprend dès qu'on le choisit (`composition.js`). Un produit revendu qui a un homonyme se dit « en
	 * revente » (`resaleNamed`, `CompositionSaleLib::applyResale()`).
	 *
	 * Pose aussi `eProductLinked`, le produit lié parmi ces candidats, vide quand il n'en est plus un, et
	 * `ePlantCreate` et `eUnitCreate`, l'espèce et l'unité de la structure que « Ajouter un nouveau
	 * produit » passe à la création de produit, vides quand la structure n'en a pas la copie : les
	 * mêmes que la page de vente (`CompositionSaleLib::applyLines()`), la plus ancienne copie de
	 * l'espèce quand la structure en a plusieurs, l'espèce elle-même quand elle est propre à la
	 * structure (`CompositionLib::getPlantsStructure()`).
	 */
	public static function loadProducts(CompositionSpecies $e): void {

		$e->expects(['plan' => ['structure'], 'plant', 'unit', 'product']);

		$plantId = $e['plant']['id'];
		$fqn = $e->getUnitFqn();

		$cPlantStructure = CompositionLib::getPlantsStructure($e['plan']['structure'], [$plantId]);

		$cProduct = CompositionSaleLib::getProducts($e['plan']['structure'], $cPlantStructure, NULL)
			->find(fn(\product\Product $eProduct) => ($eProduct['unit']->notEmpty() and $eProduct['unit']['fqn'] === $fqn));

		foreach($cProduct as $eProduct) {
			$eProduct['price'] = CompositionSaleLib::getPriceBasket($eProduct);
		}

		CompositionSaleLib::applyResale($cProduct);

		$e['cProductCandidate'] = $cProduct;

		$e['eProductLinked'] = ($e['product']->notEmpty() and $cProduct->offsetExists($e['product']['id'])) ? $cProduct[$e['product']['id']] : new \product\Product();

		$e['ePlantCreate'] = $cPlantStructure->empty() ? new \plant\Plant() : $cPlantStructure->first();

		$e['eUnitCreate'] = \product\Unit::model()
			->select(['id'])
			->whereStructure($e['plan']['structure'])
			->whereFqn($fqn)
			->get();

	}

	/**
	 * Les produits candidats (`loadProducts()`) que la recherche du champ « Produit vendu » trouve
	 * (r53) : chaque mot tapé dans le nom ou la variété, sans casse ni accent, dans l'ordre des
	 * candidats. Une recherche vide les rend tous, pour que le champ propose la liste dès qu'on y
	 * entre. Les candidats tiennent en mémoire (ceux d'une espèce dans une unité) : la recherche
	 * plein texte des produits (`ProductLib::getFromQuery()`) n'y ajouterait que ce qui n'en est
	 * pas un.
	 */
	public static function getProductsFromQuery(CompositionSpecies $e, string $query): \Collection {

		$e->expects(['cProductCandidate']);

		$words = array_filter(explode('-', toFqn($query)), fn(string $word) => $word !== '');

		return $e['cProductCandidate']->find(function(\product\Product $eProduct) use($words) {

			$text = toFqn($eProduct['name'].' '.$eProduct['variety']);

			foreach($words as $word) {
				if(str_contains($text, $word) === FALSE) {
					return FALSE;
				}
			}

			return TRUE;

		});

	}

	/**
	 * Ce que le formulaire de l'unité d'une espèce écrit (lot r14) : `cUnitOffered`, les unités que la
	 * structure peut choisir sans celle de la communauté, qui est le choix vide ; `eUnitCommunity` ;
	 * `weightReferences`, le poids de chaque unité pesée, la pièce ou la botte, celle de la communauté
	 * comprise, indexé par l'id de l'unité, dans la cascade de `CompositionLib::getWeightReference()`.
	 * Le poids tapé ne vaut que pour l'unité où l'espèce entre aujourd'hui : dans une autre, il
	 * s'efface au changement d'unité (`update()`), et le champ ne le propose pas. Enfin `stepUnits`,
	 * `[id d'unité => ['step', 'divisible', 'minimum']]`, le pas de chaque unité, que le champ du pas
	 * propose vide quand on la choisit, si elle se coupe (lot r22f), et le minimum que le champ du
	 * minimum propose vide (lot r25) : celui de la communauté dans son unité, un pas dans les autres
	 * (`CompositionLib::getStep()`). Et `unitAsked`, faux ici : l'unité de l'espèce, ou celle de la
	 * communauté, part cochée, sauf pour une variante qu'on ajoute (`loadAddVariant()`).
	 *
	 * Une espèce que la communauté ne compte pas (r48c, `CompositionSpecies::isCommunity()`) n'a pas
	 * d'unité à suivre : `eUnitCommunity` est vide, et toutes les unités que le poids sait convertir se
	 * proposent.
	 */
	public static function loadUnits(CompositionSpecies $e): void {

		$e->expects(['plan' => ['structure', 'season'], 'plant', 'unit', 'weight']);

		$source = CompositionLib::getSource();

		$plantId = $e['plant']['id'];
		$unitCommunityId = $e->isCommunity() ? $source['units'][$plantId]['unit'] : NULL;
		$unitCurrentId = $e['unit']->empty() ? $unitCommunityId : $e['unit']['id'];

		$unitIds = array_keys($source['offered']);

		if($unitCommunityId !== NULL) {
			$unitIds[] = $unitCommunityId;
		}

		$cUnit = \product\Unit::model()
			->select(['id', 'fqn', 'singular', 'plural', 'short'])
			->whereId('IN', $unitIds)
			->getCollection(index: 'id');

		// Le kilo, puis les unités pesées dans l'ordre de `WEIGHED` : l'ordre des choix ne suit pas les
		// identifiants du référentiel, où la botte précède la pièce.
		$order = array_flip(array_merge(['kg'], array_keys(CompositionLib::WEIGHED)));

		$cUnit->sort(fn(\product\Unit $eUnit1, \product\Unit $eUnit2) => $order[$eUnit1['fqn']] <=> $order[$eUnit2['fqn']]);

		$cultivated = CompositionLib::getCultivatedWeights($e['plan']['structure'], $e['plan']['season']);

		$weightReferences = [];
		$stepUnits = [];

		foreach($cUnit as $eUnit) {

			$step = ($eUnit['id'] === $unitCommunityId) ? $source['units'][$plantId]['step'] : $source['offered'][$eUnit['id']]['step'];

			$stepUnits[$eUnit['id']] = [
				'step' => $step,
				'divisible' => ($step !== 1.0),
				'minimum' => ($eUnit['id'] === $unitCommunityId) ? CompositionLib::getMinimumCommunity($source['units'][$plantId]['minimum'], $step) : $step,
			];

			if(array_key_exists($eUnit['fqn'], CompositionLib::WEIGHED)) {
				$weightReferences[$eUnit['id']] = CompositionLib::getWeightReferenceByUnit($plantId, $eUnit['fqn'], ($eUnit['id'] === $unitCurrentId) ? $e['weight'] : NULL, $source, $cultivated);
			}

		}

		$e['eUnitCommunity'] = ($unitCommunityId === NULL) ? new \product\Unit() : $cUnit[$unitCommunityId];
		$e['cUnitOffered'] = $cUnit->find(fn(\product\Unit $eUnit) => $eUnit['id'] !== $unitCommunityId);
		$e['weightReferences'] = $weightReferences;
		$e['stepUnits'] = $stepUnits;
		$e['unitAsked'] = FALSE;

	}

	public static function getUnitsByPlan(CompositionPlan $eCompositionPlan): array {

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'unit', 'weight', 'quantityBasket'])
			->wherePlan($eCompositionPlan)
			->whereUnit('!=', NULL)
			->getCollection();

		return self::getUnits($cCompositionSpecies);

	}

	/**
	 * `getUnitsByPlan()` sur des espèces du plan déjà lues, celles qui entrent au panier dans l'unité
	 * de la communauté comprises (chantier Performances, P7). `quantityBasket` est la quantité au
	 * panier d'une espèce que la communauté ne sait pas compter (r48c), nulle pour toute autre.
	 */
	public static function getUnits(\Collection $cCompositionSpecies): array {

		$units = [];

		foreach($cCompositionSpecies->find(fn(CompositionSpecies $e) => $e['unit']->notEmpty()) as $eCompositionSpecies) {
			$units[$eCompositionSpecies['id']] = [
				'unit' => $eCompositionSpecies['unit']['id'],
				'weight' => $eCompositionSpecies['weight'],
				'quantityBasket' => $eCompositionSpecies['quantityBasket'],
			];
		}

		return $units;

	}

	/**
	 * Les fréquences choisies sur les espèces du plan (lot L9), `[id d'espèce du plan => fréquence]`, sans les
	 * espèces à la fréquence courante : ce que `CompositionLib::propose()` leur fait tenir, et ce que
	 * « Recommencer les paniers » reporte sur le plan qui remplace celui-ci
	 * (`CompositionPlanLib::restart()`), comme les prix.
	 */
	public static function getFrequenciesByPlan(CompositionPlan $eCompositionPlan): array {

		$frequencies = [];

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'frequency'])
			->wherePlan($eCompositionPlan)
			->whereFrequency('!=', NULL)
			->getCollection();

		foreach($cCompositionSpecies as $eCompositionSpecies) {
			$frequencies[$eCompositionSpecies['id']] = $eCompositionSpecies['frequency'];
		}

		return $frequencies;

	}

	/**
	 * Les saisons réglées à la main (`CUSTOM`), prises du plan de culture (`CULTIVATION`) ou du
	 * calendrier type (`CALENDAR`, r48b) sur les espèces du plan (lot r27),
	 * `[id d'espèce du plan => ['status' => …, 'windowWeeks' => …]]` : ce que
	 * « Recommencer les paniers » reporte sur le plan qui remplace celui-ci
	 * (`CompositionPlanLib::restart()`), comme les fréquences. Une saison de la communauté n'y est
	 * pas : le nouveau plan la recalcule sur sa propre saison.
	 */
	public static function getSeasonsByPlan(CompositionPlan $eCompositionPlan): array {

		$seasons = [];

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'status', 'windowWeeks'])
			->wherePlan($eCompositionPlan)
			->whereStatus('IN', [CompositionSpecies::CUSTOM, CompositionSpecies::CULTIVATION, CompositionSpecies::CALENDAR])
			->getCollection();

		foreach($cCompositionSpecies as $eCompositionSpecies) {
			$seasons[$eCompositionSpecies['id']] = $eCompositionSpecies->extracts(['status', 'windowWeeks']);
		}

		return $seasons;

	}

	/**
	 * `getSeasonsByPlan()` sur les seules espèces nues du plan, rangées par id d'espèce du
	 * référentiel : ce que le départ dessine (`CompositionPlanLib::getForStart()`), qui ne coche que
	 * des espèces (r76). La saison d'une variante passe par `restart()`, avec sa variante.
	 */
	public static function getSeasonsByPlant(CompositionPlan $eCompositionPlan): array {

		$seasons = [];

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['plant', 'status', 'windowWeeks'])
			->wherePlan($eCompositionPlan)
			->whereName(NULL)
			->whereStatus('IN', [CompositionSpecies::CUSTOM, CompositionSpecies::CULTIVATION, CompositionSpecies::CALENDAR])
			->getCollection();

		foreach($cCompositionSpecies as $eCompositionSpecies) {
			$seasons[$eCompositionSpecies['plant']['id']] = $eCompositionSpecies->extracts(['status', 'windowWeeks']);
		}

		return $seasons;

	}

	/**
	 * Les espèces nues du plan hors de `$plantIds`, les espèces que la communauté propose pour la
	 * saison, par id d'espèce du référentiel : celles que « Ajouter une espèce » a écrites en plus
	 * (r48c), retirées depuis ou non. Une variante (r76) n'est pas un rang du départ. Le départ rouvert les montre et les renvoie avec les autres
	 * (`CompositionPlanLib::getForStart()`, `getStartPlantIds()`).
	 */
	public static function getAddedByPlan(CompositionPlan $eCompositionPlan, array $plantIds): \Collection {

		return CompositionSpecies::model()
			->select(['id', 'plant', 'status'])
			->wherePlan($eCompositionPlan)
			->wherePlant('NOT IN', $plantIds, if: $plantIds !== [])
			->whereName(NULL)
			->getCollection(index: 'plant');

	}

	/**
	 * Les espèces cochées parmi les espèces du plan `$cCompositionSpecies` (`getByPlan()`), celles que
	 * la règle peut servir, avec ce que l'année en quantités écrit d'elles
	 * (`CompositionLib::getQuantities()`). Le statut se lit saison non écrite posée, comme les fenêtres
	 * de la grille (`getWindows()`) : une espèce que la grille ne sert pas n'est pas comptée cultivée.
	 */
	public static function getGrown(\Collection $cCompositionSpecies): \Collection {
		return $cCompositionSpecies->find(fn(CompositionSpecies $e) => $e['status'] !== CompositionSpecies::EXCLUDED);
	}

	/**
	 * Les espèces du plan, avec ce qu'il faut pour projeter la source sur elles (r76,
	 * `CompositionLib::getSourceByPlan()`, `getPlants()`).
	 */
	public static function getPlantsByPlan(CompositionPlan $eCompositionPlan): \Collection {

		return CompositionSpecies::model()
			->select(['id', 'plant', 'name'])
			->wherePlan($eCompositionPlan)
			->getCollection();

	}

	/**
	 * Ce que la source d'un plan lit de ses espèces `$cCompositionSpecies` (r76,
	 * `CompositionLib::projectSource()`) : `plants`, `[id d'espèce du plan => id d'espèce lue]`, et
	 * `variants`, les ids des variantes, celles qui portent un nom.
	 */
	public static function getPlants(\Collection $cCompositionSpecies): array {

		$plants = [];
		$variants = [];

		foreach($cCompositionSpecies as $eCompositionSpecies) {

			$plants[$eCompositionSpecies['id']] = $eCompositionSpecies['plant']['id'];

			if($eCompositionSpecies['name'] !== NULL) {
				$variants[] = $eCompositionSpecies['id'];
			}

		}

		return ['plants' => $plants, 'variants' => $variants];

	}

	/**
	 * Les espèces du plan par id d'espèce, chacune avec le prix que son panneau écrit
	 * (lot M) : `reference`, le prix de la cascade et d'où il vient
	 * (`CompositionLib::getPriceReference()`), et `eUnit`, l'unité où l'espèce entre au panier, dans
	 * laquelle ce prix se lit et se tape : celle que la structure a choisie (`unit`, lot r14), sinon
	 * celle de la communauté, que `eUnitCommunity` porte dans les deux cas. Chacune porte aussi ce que son panneau dit de sa vente
	 * (planche r1) : `minimumDefault`, le minimum qui s'applique quand la structure n'en règle pas,
	 * celui de la communauté au pas qui s'applique à l'espèce (`CompositionLib::getStep()`), ou un
	 * pas quand la communauté n'en donne pas dans son unité (lot r25), `quantityDefault`, la quantité
	 * qu'un ajout à la main prend quand la structure n'en règle pas, le minimum qui s'applique (r30,
	 * #92, `CompositionLib::getQuantityAdded()`), `stepUnit`, le pas de son unité, que le champ du pas
	 * propose vide, et `divisible`, son unité se coupe : à la pièce ou à la botte, le pas reste d'une
	 * unité et seul le minimum se règle.
	 * Enfin `weightReference`, le poids d'une unité du panier et d'où il vient, pour une espèce qui y
	 * entre à la pièce ou à la botte (lot L3, `CompositionLib::getWeightReference()`), et `storage`, sa
	 * durée de conservation et d'où elle vient (lot r9, `CompositionLib::getStorage()`), lue sur
	 * `$source`, qui est alors celle du plan (`CompositionLib::getSourceByPlan()`).
	 *
	 * Le prix déclaré et la médiane changent d'une semaine à l'autre : le panneau donne celui de la
	 * première semaine livrée de la saison de l'espèce qui n'est pas encore passée, ou de la
	 * première de sa saison quand toutes le sont (`CompositionLib::getReferenceWeek()`). Une espèce
	 * dont la saison ne croise aucune semaine livrée prend la première semaine livrée
	 * (`CompositionLib::getWeeksDeliveredInSeason()`).
	 *
	 * La cascade des prix de la structure se lit sur `$source` (`pricesPlan`), que
	 * `CompositionLib::getSourceByPlan()` vient de calculer : la refaire ici coûtait 17 requêtes par
	 * appel (chantier Performances, P2). `$source` est donc toujours celle d'un plan.
	 *
	 * Elles se rangent par id d'espèce du plan (r76), celui sous lequel `$source` les sert : une
	 * espèce que « Ajouter une espèce » n'a pas encore écrite s'y chiffre sous
	 * `CompositionLib::SPECIES_ADDED` (`loadAddPriced()`).
	 *
	 * `$cCompositionSpecies` sont les espèces du plan (`getByPlan()`) quand l'appelant les a déjà,
	 * comme l'écran (P7), et se lisent ici sinon. Elles sont chiffrées en place. `$cultivations`
	 * sont les cultures de la structure (`CompositionLib::getCultivations()`), d'où les poids des
	 * cultures dérivent, quand l'appelant les a déjà, comme l'écran (P8).
	 */
	public static function getWithPrices(CompositionPlan $eCompositionPlan, array $source, ?\Collection $cCompositionSpecies = NULL, ?array $cultivations = NULL): \Collection {

		$eCompositionPlan->expects(['id', 'structure', 'season', 'weeks']);

		$season = $eCompositionPlan['season'];

		$weeksDelivered = $eCompositionPlan['weeks'];
		sort($weeksDelivered);

		$prices = $source['pricesPlan'];
		$cultivated = CompositionLib::projectByPlants(CompositionLib::getCultivatedWeights($eCompositionPlan['structure'], $season, $cultivations), $source['plants']);

		$cCompositionSpecies ??= self::getByPlan($eCompositionPlan);

		$unitIds = [];

		foreach($cCompositionSpecies->getIds() as $speciesId) {

			$unitIds[] = $source['units'][$speciesId]['unit'];

			if($source['units'][$speciesId]['community'] !== NULL) {
				$unitIds[] = $source['units'][$speciesId]['community']['unit'];
			}

		}

		$cUnit = \product\Unit::model()
			->select(['id', 'fqn', 'singular', 'plural', 'short'])
			->whereId('IN', $unitIds)
			->getCollection(NULL, NULL, 'id');

		// `reindex()` groupe par clé (une collection par espèce) : l'index par espèce se pose à la main.
		$cCompositionSpeciesPriced = new \Collection();

		foreach($cCompositionSpecies as $eCompositionSpecies) {

			$speciesId = $eCompositionSpecies['id'];

			$week = CompositionLib::getReferenceWeek(CompositionLib::getWeeksDeliveredInSeason($weeksDelivered, $eCompositionSpecies->getSeasonWeeks()), $season);

			$eCompositionSpecies['plan'] = $eCompositionPlan;
			$eCompositionSpecies['reference'] = CompositionLib::getPriceReference($week, $speciesId, $source, $prices);
			$eCompositionSpecies['eUnit'] = $cUnit[$source['units'][$speciesId]['unit']];
			$eCompositionSpecies['eUnitCommunity'] = $cUnit[($source['units'][$speciesId]['community'] === NULL) ? $source['units'][$speciesId]['unit'] : $source['units'][$speciesId]['community']['unit']];
			$eCompositionSpecies['conversion'] = ($source['units'][$speciesId]['community'] === NULL) ? NULL : [
				'kilos' => $source['units'][$speciesId]['kilos'],
				'kilosCommunity' => $source['units'][$speciesId]['community']['kilos'],
			];
			$sale = CompositionLib::getStep($speciesId, $source, [$speciesId => ['step' => $eCompositionSpecies['step'], 'minimum' => $eCompositionSpecies['minimum']]]);
			$eCompositionSpecies['minimumDefault'] = CompositionLib::getMinimumCommunity($source['units'][$speciesId]['minimum'], $sale['step']);
			$eCompositionSpecies['quantityDefault'] = $sale['minimum'];
			$eCompositionSpecies['stepUnit'] = $source['units'][$speciesId]['step'];
			$eCompositionSpecies['divisible'] = ($eCompositionSpecies['stepUnit'] !== 1.0);
			$eCompositionSpecies['weightReference'] = CompositionLib::getWeightReference($speciesId, $eCompositionSpecies['weight'], $source, $cultivated);
			$eCompositionSpecies['storage'] = $source['storage'][$speciesId];

			$cCompositionSpeciesPriced[$speciesId] = $eCompositionSpecies;

		}

		return $cCompositionSpeciesPriced;

	}

	/**
	 * Pose sur chaque rang de la grille (`CompositionLib::getGrid()`) son espèce du plan, avec son
	 * prix (`getWithPrices()`), sous `eCompositionSpecies` : c'est elle que le menu du nom règle.
	 *
	 * Un rang peut ne pas avoir d'espèce du plan, et il reçoit alors une espèce vide : c'est une
	 * espèce qu'une semaine corrigée à la main sert sans que le plan la porte. Elle s'affiche sans
	 * menu ni saison.
	 *
	 * L'espèce du plan porte aussi ce que la grille sert d'elle (lot L9) : `served` et `candidate`
	 * (`CompositionLib::getGridServed()`), que son panneau écrit quand elle n'a pas choisi de fréquence,
	 * et `frequencyCurrent`, la fréquence que cette part lui donne
	 * (`CompositionLib::getFrequencyCurrent()`).
	 *
	 * Chaque rang porte enfin sa saison de vente (lot r27) : `season`, ses intervalles, vides pour une
	 * espèce retirée ou hors du plan.
	 */
	public static function applyToGrid(array $grid, \Collection $cCompositionSpecies): array {

		foreach($grid as $group => ['species' => $rows]) {

			foreach($rows as $position => $row) {

				if($cCompositionSpecies->offsetExists($row['species']['id']) === FALSE) {
					$grid[$group]['species'][$position]['eCompositionSpecies'] = new CompositionSpecies();
					$grid[$group]['species'][$position]['season'] = [];
					continue;
				}

				$eCompositionSpecies = $cCompositionSpecies[$row['species']['id']];

				$season = match($eCompositionSpecies['status']) {
					CompositionSpecies::EXCLUDED => [],
					CompositionSpecies::NATURAL, CompositionSpecies::CULTIVATION, CompositionSpecies::CALENDAR, CompositionSpecies::CUSTOM => $eCompositionSpecies['windowWeeks'],
				};

				$grid[$group]['species'][$position]['season'] = $season;

				self::applyServed($eCompositionSpecies, CompositionLib::getGridServed($row));

				$grid[$group]['species'][$position]['eCompositionSpecies'] = $eCompositionSpecies;

			}

		}

		return $grid;

	}

	/**
	 * Ce que le champ de la fréquence dit de l'espèce : ses paniers aujourd'hui, et si « Selon la
	 * saison » lui est proposé (`seasonalOffered`), ce qui n'est jamais le cas d'une variante (r76 lot 2,
	 * `frequency.seasonal`). Une variante qu'on ajoute n'a pas encore de nom :
	 * `loadAddVariant()` le redit pour elle.
	 */
	private static function applyServed(CompositionSpecies $eCompositionSpecies, array $served): void {

		$eCompositionSpecies->expects(['name']);

		$eCompositionSpecies['served'] = $served['served'];
		$eCompositionSpecies['candidate'] = $served['candidate'];
		$eCompositionSpecies['frequencyCurrent'] = CompositionLib::getFrequencyCurrent($served['served'], $served['candidate']);
		$eCompositionSpecies['seasonalOffered'] = ($eCompositionSpecies['name'] === NULL);

	}

	/**
	 * Ce que le formulaire de la fréquence écrit sous « Selon la saison » (lot L9) : `served`,
	 * `candidate` et `frequencyCurrent`, sur la taille que la grille lit (`$formulaId`, la première
	 * du plan à défaut, comme `CompositionPlanLib::getForScreen()`).
	 *
	 * Sans fréquence choisie, c'est ce que la grille sert de l'espèce, comme son panneau le dit
	 * (`applyToGrid()`). Avec une fréquence choisie, la grille sert ce rythme-là, et le choix
	 * « Selon la saison » dit ce qu'on retrouverait en y revenant : la proposition de la taille
	 * tirée sans la fréquence de l'espèce, celles des autres espèces gardées, comme la taille la
	 * tirerait, sur sa base et sous le stock du plan de culture (`CompositionLib::proposeFormula()`).
	 */
	public static function loadFrequency(CompositionSpecies $e, ?int $formulaId): void {

		$e->expects(['id', 'plan' => ['id', 'structure', 'season', 'reference', 'weeks'], 'frequency']);

		$eCompositionPlan = $e['plan'];
		$speciesId = $e['id'];

		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan);

		$eCompositionFormula = $cCompositionFormula->offsetExists($formulaId) ?
			$cCompositionFormula[$formulaId] :
			$cCompositionFormula->first();

		$cCompositionSpecies = self::getByPlan($eCompositionPlan);

		$windows = self::getWindows($cCompositionSpecies);
		$source = CompositionLib::getSourceByPlan($eCompositionPlan, $windows, $cCompositionSpecies);

		if($e['frequency'] === NULL) {

			$grid = CompositionLib::getGrid(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies), $eCompositionPlan['season'], $windows, $source, $cCompositionSpecies);

			$served = ['served' => 0, 'candidate' => 0];

			foreach($grid as ['species' => $rows]) {

				foreach($rows as $row) {

					if($row['species']['id'] === $speciesId) {
						$served = CompositionLib::getGridServed($row);
					}

				}

			}

		} else {

			$frequencies = self::getFrequenciesByPlan($eCompositionPlan);
			unset($frequencies[$speciesId]);

			$proposal = CompositionLib::proposeFormula($eCompositionPlan, $eCompositionFormula, $source, $source['pricesPlan'], $frequencies);

			$served = CompositionLib::getProposalServed($proposal, $speciesId, $eCompositionPlan['season'], $windows, $source);

		}

		self::applyServed($e, $served);

	}

	/**
	 * Pose sur chaque rang de l'année en quantités (`CompositionLib::getQuantities()`) son espèce du
	 * plan (`getWithPrices()`), sous `eCompositionSpecies`, pour qu'il se dise aussi en kilos (lot L3).
	 * Un rang d'une espèce que le plan ne porte pas reçoit une espèce vide, comme dans la grille.
	 */
	public static function applyToQuantities(array $quantities, \Collection $cCompositionSpecies): array {

		foreach($quantities['groups'] as $group => ['rows' => $rows]) {

			foreach($rows as $position => $row) {
				$quantities['groups'][$group]['rows'][$position]['eCompositionSpecies'] = $cCompositionSpecies->offsetExists($row['species']['id']) ? $cCompositionSpecies[$row['species']['id']] : new CompositionSpecies();
			}

		}

		return $quantities;

	}

	/**
	 * Les espèces de l'onglet « Espèces » (r48a, planche r48, question 3, piste B), toutes celles que le
	 * plan porte, retirées comprises, dans l'ordre alphabétique et indexées par espèce. Ce sont les
	 * espèces chiffrées de l'écran (`$cCompositionSpeciesPriced`, `getWithPrices()`), qui portent déjà
	 * leur prix (`reference`) et leur unité (`eUnit`), avec en plus :
	 *
	 * - `basketQuantity`, la quantité que l'espèce met au panier dans son unité. Elle ne se pose ni
	 *   sous `quantity`, la colonne de la quantité d'un ajout à la main, ni sous `quantityBasket`,
	 *   la colonne de la quantité qu'une espèce ajoutée au plan porte elle-même ;
	 * - `months`, sa saison en douze mois (`CompositionLib::getSeasonShares()`) : la part de chaque
	 *   mois en saison, celle qui ne tient qu'à la conservation, et si le mois est coché.
	 *
	 * La conservation ne se sépare de la récolte que sur une saison datée par le plan de culture ou
	 * par le calendrier type (r48b) : ce sont les semaines qu'ils n'ouvrent que par elle
	 * (`CompositionLib::getReferenceWindows()`, `getCalendarWindows()`). La communauté ne date que des
	 * semaines de vente.
	 *
	 * L'ordre est celui d'un dictionnaire (`natural: TRUE` passe par `\L::getCollator()`) : on vient
	 * chercher une espèce par son nom, et un tri sur les octets rangerait « Céleri-rave » après
	 * « Courgette ».
	 *
	 * Une variante (r76) se range sous le nom de son espèce, après l'espèce nue, puis sous le sien : la
	 * « Kg » de carotte tombe juste sous la carotte, et non à la lettre K, et une « Botte d'oignons »
	 * sous l'oignon et non au-dessus.
	 */
	public static function getForTab(CompositionPlan $eCompositionPlan, array $source, \Collection $cCompositionSpeciesPriced, array $cultivations): \Collection {

		$eCompositionPlan->expects(['structure', 'season', 'reference', 'climate']);

		$plants = array_intersect_key($source['plants'], $cCompositionSpeciesPriced->getArrayCopy());

		$reference = match($eCompositionPlan['reference']) {
			CompositionPlan::COMMUNITY, CompositionPlan::CALENDAR => [],
			CompositionPlan::CULTIVATION => CompositionLib::getReferenceWindows(
				CompositionLib::getHarvestWeeks($eCompositionPlan['structure'], $eCompositionPlan['season'], $cultivations),
				$plants,
				$source['storage']
			),
		};

		$calendar = CompositionLib::getCalendarWindows($eCompositionPlan['climate'], $plants);

		$seasonMonths = CompositionLib::getSeasonMonths($eCompositionPlan['season']);

		// Une collection neuve : le tri d'une collection que l'écran parcourt ailleurs en changerait
		// l'ordre pour lui aussi.
		$cCompositionSpeciesTab = new \Collection();

		foreach($cCompositionSpeciesPriced as $speciesId => $eCompositionSpecies) {

			$weeks = $eCompositionSpecies->getSeasonWeeks();

			// Une saison prise du calendrier se sépare sur lui, même quand le plan de culture date
			// l'espèce : il ne la date pour elle qu'une fois synchronisé.
			$windows = ($eCompositionSpecies['status'] === CompositionSpecies::CALENDAR) ?
				($calendar[$speciesId] ?? []) :
				($reference[$speciesId] ?? $calendar[$speciesId] ?? []);

			$weeksStored = array_keys($windows, FALSE, TRUE);

			$eCompositionSpecies['basketQuantity'] = $source['units'][$speciesId]['quantity'];
			$eCompositionSpecies['months'] = CompositionLib::getSeasonShares($seasonMonths, $weeks, $weeksStored);

			$cCompositionSpeciesTab[$speciesId] = $eCompositionSpecies;

		}

		$cCompositionSpeciesTab->sort(fn(CompositionSpecies $e1, CompositionSpecies $e2) => (
			\L::getCollator()->compare($e1['plant']['name'], $e2['plant']['name']) ?:
			(($e1['name'] !== NULL) <=> ($e2['name'] !== NULL)) ?:
			\L::getCollator()->compare($e1->getLabel(), $e2->getLabel())
		));

		return $cCompositionSpeciesTab;

	}

	/**
	 * Une espèce retirée n'a pas de saison : la décocher efface celle qu'elle portait, et le contrôle
	 * de `windowWeeks` exige ce `NULL`. La remettre lui en réécrit une (`add()`).
	 *
	 * Un prix tapé ou vidé se reporte, dans la même transaction, sur les lignes de l'espèce dans
	 * toutes les tailles du plan (Q8) : chaque ligne prend le prix que la cascade donne maintenant
	 * pour sa semaine (`CompositionLib::getUnitPrice()`), le prix tapé quand il y en a un, et sinon
	 * celui que la cascade retrouve, semaine par semaine. Rien ne se recompose (Q9) : la valeur des
	 * semaines où l'espèce entre bouge, pas les paniers.
	 *
	 * Les semaines qui prennent le même prix s'écrivent ensemble : un prix tapé vaut pour toute
	 * l'année, et se reporte en une seule écriture.
	 *
	 * Un pas ou un minimum réglés (planche r1) arrondissent au nouveau pas et relèvent au nouveau
	 * minimum les lignes de l'espèce dans les semaines non corrigées de toutes les tailles
	 * (`CompositionLineLib::updateStep()`), sans recomposer : comme un prix, ils changent ce que les
	 * paniers valent, pas les espèces qu'ils portent. Les semaines corrigées gardent ce qu'on y a
	 * réglé, comme avec les panneaux. « Sans minimum » est le minimum de la communauté au pas de
	 * l'espèce (`getStep()`, lot r25).
	 *
	 * Une fréquence choisie ou rendue (lot L9) change qui entre au panier : toutes les tailles du plan
	 * recomposent leurs semaines non corrigées (`CompositionLib::recomposePlan()`), tout de suite et
	 * sans confirmation, comme « Retirer de mes cultures ». Les semaines corrigées gardent ce qu'on y a
	 * réglé.
	 *
	 * Une unité choisie ou rendue (lot r14) change la quantité, le prix et donc la valeur de chaque
	 * ligne de l'espèce : comme une fréquence, toutes les tailles recomposent leurs semaines non
	 * corrigées, tout de suite et sans confirmation, et les semaines corrigées gardent leurs lignes
	 * dans l'unité d'avant, chacune avec son unité et son prix figé. Le prix, le pas et le minimum
	 * tapés s'exprimaient dans l'ancienne unité : un prix de 12 €/kg lu comme 12 € la pièce serait
	 * faux sans erreur, ils s'effacent donc dans la même écriture quand le même geste n'en pose pas de
	 * nouveaux, et le poids avec eux. Ceux que le geste pose se disent dans la nouvelle unité : le
	 * panneau « Régler une espèce » vide ses champs quand on change d'unité, et ce qu'on y retape part
	 * avec elle (arbitrage du chef de projet sur r22e, lot r22f). Une unité reposée à l'identique
	 * n'efface rien.
	 *
	 * Le poids d'une espèce dont la structure a choisi l'unité convertit sa quantité et ses prix
	 * depuis l'unité de la communauté (`CompositionLib::applyUnits()`) : le changer recompose aussi.
	 *
	 * Le panneau « Régler une espèce » (lot r22e) enregistre plusieurs de ces propriétés dans le même
	 * geste : le plan ne se recompose qu'une fois (`isRecomposing()`).
	 *
	 * Une recomposition pose sur l'espèce ce que chaque taille a changé, `recomposed`
	 * (`CompositionLib::recomposePlan()`), d'où la réponse de l'écran ne rend que les colonnes qui
	 * bougent (lot r28).
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function update(CompositionSpecies $e, array $properties): void {

		if(in_array('status', $properties) and $e['status'] === CompositionSpecies::EXCLUDED) {
			$e['windowWeeks'] = NULL;
			$properties[] = 'windowWeeks';
		}

		if(in_array('unit', $properties) and self::isUnitChanged($e)) {

			// Le produit retenu pour la vente (r37b) se vend dans l'unité d'avant : il ne porte plus
			// l'espèce, et la page « Mettre en vente » en proposera un autre.
			foreach(['quantityBasket', 'price', 'step', 'minimum', 'quantity', 'weight', 'product'] as $property) {

				if(in_array($property, $properties) === FALSE) {
					$e[$property] = NULL;
					$properties[] = $property;
				}

			}

		}

		CompositionSpecies::model()->beginTransaction();

			parent::update($e, array_unique($properties));

			// Une unité convertie recompose le plan, qui relit les prix : les lignes ne se reprennent pas
			// une par une avant. Un produit lié ou délié change le prix de l'espèce comme un prix tapé
			// (`CompositionLib::PRICE_PRODUCT`).
			if(array_intersect(['price', 'product'], $properties) !== [] and self::isConverted($e, $properties) === FALSE) {

				$e->expects(['id', 'plan' => ['id', 'structure', 'season', 'reference', 'weeks']]);

				$source = CompositionLib::getSourceByPlan($e['plan']);
				$prices = $source['pricesPlan'];

				$speciesId = $e['id'];

				$eUnit = new \product\Unit(['id' => $source['units'][$speciesId]['unit']]);

				// Un prix n'est pas une clé de tableau : il y entre écrit, et reste à côté tel quel.
				$weeksByPrice = [];

				foreach(CompositionWeekLib::getByFormulas(CompositionFormulaLib::getByPlan($e['plan'])) as $eCompositionWeek) {

					$unitPrice = CompositionLib::getUnitPrice($eCompositionWeek['week'], $speciesId, $source, $prices);
					$key = var_export($unitPrice, TRUE);

					$weeksByPrice[$key] ??= ['unitPrice' => $unitPrice, 'cCompositionWeek' => new \Collection()];
					$weeksByPrice[$key]['cCompositionWeek'][] = $eCompositionWeek;

				}

				foreach($weeksByPrice as ['unitPrice' => $unitPrice, 'cCompositionWeek' => $cCompositionWeek]) {
					CompositionLineLib::updateUnitPrice($cCompositionWeek, $e, $eUnit, $unitPrice);
				}

			}

			if(
				in_array('unit', $properties) === FALSE and
				array_intersect(['step', 'minimum'], $properties) !== []
			) {

				$e->expects(['id', 'plan' => ['id', 'structure', 'season', 'reference', 'weeks'], 'step', 'minimum']);

				$source = CompositionLib::getSourceByPlan($e['plan']);

				$speciesId = $e['id'];

				$step = CompositionLib::getStep($speciesId, $source, [$speciesId => ['step' => $e['step'], 'minimum' => $e['minimum']]]);

				CompositionLineLib::updateStep(CompositionFormulaLib::getByPlan($e['plan']), $e, new \product\Unit(['id' => $source['units'][$speciesId]['unit']]), $step);

			}

			// Une seule recomposition pour tout ce que le geste change : le panneau « Régler une espèce »
			// enregistre l'unité et la fréquence ensemble, et chacune referait le plan entier.
			if(self::isRecomposing($e, $properties)) {

				$e->expects(['plan' => ['id', 'structure', 'season', 'reference', 'weeks']]);

				$source = CompositionLib::getSourceByPlan($e['plan']);

				// Ce que chaque taille a changé, que la réponse de l'écran lit (lot r28) : les formulaires
				// rapides ne gardent rien de ce que rend l'écriture.
				$e['recomposed'] = CompositionLib::recomposePlan($e['plan'], $source, $source['pricesPlan']);

			}

		CompositionSpecies::model()->commit();

	}

	/**
	 * L'enregistrement de `$properties` recompose-t-il les semaines non corrigées de toutes les
	 * tailles (`update()`) ? Une fréquence change le tirage, et une unité choisie, ou le poids d'une
	 * unité choisie, convertit la quantité et les prix de l'espèce (lot r14). Le prix, le pas et le
	 * minimum se reportent sur les lignes sans rien tirer. La quantité au panier d'une espèce que la
	 * communauté ne compte pas (r48c) change ce que chaque panier en sert. La page du panneau « Régler
	 * une espèce » choisit sa réponse sur la même question.
	 */
	public static function isRecomposing(CompositionSpecies $e, array $properties): bool {
		return (self::isConverted($e, $properties) or in_array('frequency', $properties));
	}

	private static function isConverted(CompositionSpecies $e, array $properties): bool {

		if(array_intersect(['unit', 'quantityBasket'], $properties) !== []) {
			return TRUE;
		}

		if(in_array('weight', $properties) === FALSE) {
			return FALSE;
		}

		$e->expects(['unit']);

		return $e['unit']->notEmpty();

	}

	/**
	 * Les propriétés de `$properties` dont la valeur construite diffère de celle que l'espèce porte en
	 * base. Le panneau « Régler une espèce » poste tous ses champs : sans ce tri, l'enregistrer sans
	 * rien changer recomposerait toutes les tailles, puisqu'il poste la fréquence et l'unité.
	 *
	 * Une unité changée les rend toutes : un prix, un pas ou un minimum retapés à l'identique se
	 * disent dans une autre unité, et `update()` effacerait ceux qui manqueraient à l'écriture.
	 */
	public static function getPropertiesChanged(CompositionSpecies $e, array $properties): array {

		$e->expects(array_merge(['id'], $properties));

		$eCompositionSpeciesWritten = CompositionSpecies::model()
			->select($properties)
			->whereId($e['id'])
			->get();

		$propertiesChanged = array_values(array_filter($properties, function(string $property) use($e, $eCompositionSpeciesWritten): bool {

			$value = $e[$property];
			$valueWritten = $eCompositionSpeciesWritten[$property];

			if($value instanceof \Element) {
				return ($value->empty() ? NULL : $value['id']) !== ($valueWritten->empty() ? NULL : $valueWritten['id']);
			} else {
				return ($value !== $valueWritten);
			}

		}));

		return in_array('unit', $propertiesChanged) ? array_values($properties) : $propertiesChanged;

	}

	/**
	 * Ce que l'enregistrement du panneau « Régler une espèce » construit, l'unité postée déjà construite
	 * sur l'espèce. Le panneau poste tous ses champs. Une unité changée se construit avec le poids de
	 * la nouvelle unité, et avec le prix, le pas, le minimum et la quantité par défaut que le panneau a
	 * vidés au changement (`composition.js`) : ce qu'on y a retapé se dit dans la nouvelle unité, et s'y contrôle ; ce
	 * qui reste vide s'efface (`update()`), ce que la confirmation de l'envoi a annoncé
	 * (`data-loss`). L'unité gardée, le poids se construit sans elle, parce que son contrôle
	 * `missing` n'est exigé que du geste qui choisit l'unité : une espèce sans poids connu ne le
	 * demande pas à chaque enregistrement.
	 *
	 * Le pas ne se règle que sur une unité qui se coupe, celle d'avant ou la nouvelle, et la fréquence
	 * que sur une espèce cultivée, comme le panneau les montre.
	 *
	 * Le produit vendu se construit toujours (r39b), et le prix aussi : pour une espèce liée, son champ
	 * est masqué et poste le prix tapé tel quel, qui se garde ainsi pour le jour où le lien se défait.
	 *
	 * La quantité au panier ne se construit que pour une espèce que la communauté ne compte pas (r48c),
	 * la seule dont le panneau la montre, et son contrôle l'exige alors.
	 *
	 * Le panneau « Ajouter une espèce » pose les mêmes champs (r60, `$added`) et s'enregistre de la même
	 * façon. L'espèce qu'il ajoute est cultivée, quel que soit l'état qu'elle porte avant l'envoi, et
	 * règle donc sa fréquence. Une espèce neuve n'a pas d'unité en base : la sienne change dès qu'elle
	 * en choisit une autre que celle de la communauté, et son poids se demande alors comme au
	 * changement d'unité.
	 */
	public static function getPropertiesPanel(CompositionSpecies $e, bool $added = FALSE): array {

		$e->expects(['unit', 'status']);

		// La quantité au panier se contrôle contre le pas (r68, `CompositionSpecies::properties()`) :
		// elle se construit après lui, pour lire celui que le même envoi règle.
		$properties = array_merge(
			['weight', 'price'],
			$e->isDivisible() ? ['step'] : [],
			$e->isCommunity() ? [] : ['quantityBasket'],
			['minimum', 'quantity', 'product'],
		);

		$unitChanged = $e->exists() ? self::isUnitChanged($e) : $e['unit']->notEmpty();

		if($unitChanged) {
			array_unshift($properties, 'unit');
		}

		if($added or $e['status'] !== CompositionSpecies::EXCLUDED) {
			$properties[] = 'frequency';
		}

		return $properties;

	}

	/**
	 * L'unité construite diffère-t-elle de celle que l'espèce porte en base ? Elle se relit : `build()`
	 * a déjà remplacé celle de l'élément.
	 */
	private static function isUnitChanged(CompositionSpecies $e): bool {

		$e->expects(['id', 'unit']);

		$eUnitWritten = CompositionSpecies::model()
			->select(['unit'])
			->whereId($e['id'])
			->get()['unit'];

		return ($eUnitWritten->empty() ? NULL : $eUnitWritten['id']) !== ($e['unit']->empty() ? NULL : $e['unit']['id']);

	}

	/**
	 * « Retirer de mes cultures », depuis le panneau « Régler une espèce » (lot M, r22e) : l'espèce passe
	 * à `EXCLUDED`, déjà construite par la page, et chaque taille recompose les semaines non
	 * corrigées qui la servaient (`CompositionLib::recomposeWeeks()`). Une semaine corrigée garde
	 * l'espèce : c'est une correction faite à la main, que le retrait ne défait pas. Les autres
	 * semaines ne bougent pas, là où une espèce remise refait toutes les semaines non corrigées
	 * (`add()`).
	 *
	 * Ce que chaque taille a changé se pose sur l'espèce, `recomposed`, comme pour une fréquence
	 * (`update()`) : la réponse de l'écran ne rend que ce qui bouge (chantier Performances, P9).
	 */
	public static function exclude(CompositionSpecies $e): void {

		$e->expects(['id', 'plan' => ['id', 'structure', 'season', 'reference', 'weeks'], 'status']);

		$eCompositionPlan = $e['plan'];

		// Les bases d'abord : une dérivée lit les semaines que sa base vient d'écrire (lot r17).
		$cCompositionFormula = CompositionFormulaLib::getByPlanForCompose($eCompositionPlan);

		CompositionSpecies::model()->beginTransaction();

			self::update($e, ['status']);

			$source = CompositionLib::getSourceByPlan($eCompositionPlan);
			$prices = $source['pricesPlan'];

			foreach($cCompositionFormula as $eCompositionFormula) {

				$weeks = CompositionWeekLib::getUnamendedBySpecies($eCompositionFormula, $e);

				$e['recomposed'][$eCompositionFormula['id']] = CompositionLib::recomposeWeeks($eCompositionPlan, $eCompositionFormula, $weeks, $source, $prices, derived: FALSE);

			}

		CompositionSpecies::model()->commit();

	}

	/**
	 * La saison qu'une espèce du plan prend quand rien ne la règle à la main, pour chacune des espèces
	 * `$plants`, `[id servi => id lu]` (`CompositionLib::getPlantId()`), rendue `[id servi => ['status'
	 * => …, 'windowWeeks' => …]]` : celle que le plan de culture ouvre
	 * (`CompositionLib::getReferenceWindows()`), sous `CULTIVATION`, quand le plan le suit et qu'il
	 * date l'espèce ; sinon celle du calendrier type du climat du plan
	 * (`CompositionLib::getCalendarWindows()`, r48b), sous `CALENDAR`, quand le plan a un climat et que
	 * le calendrier date l'espèce ; sinon celle de la communauté sur toute l'année
	 * (`CompositionLib::getNaturalWindows()`), sous `NATURAL`. Les trous de quatre semaines au plus
	 * sont bouchés (`CompositionLib::getWindowWeeksFilled()`).
	 *
	 * Le calendrier se lit sur le climat et non sur `reference` : un plan parti du calendrier puis
	 * synchronisé sur le plan de culture garde son climat, et une espèce que le plan de culture ne date
	 * pas y reprend la saison du calendrier plutôt que celle de la communauté.
	 *
	 * C'est ce que le départ écrit sur les espèces cochées (`CompositionPlanLib::create()`) et ce qu'une
	 * espèce remise reprend (`getSeasonRestored()`). Une espèce que ni l'un ni l'autre ne date n'y est
	 * pas : elle n'a pas de saison à prendre.
	 *
	 * `$source` est celle de la communauté (`CompositionLib::getSource()`), où l'id servi est l'id lu,
	 * ou celle du plan, rangée par espèce du plan : seules la disponibilité, les mois de la mercuriale,
	 * les unités et le relevé des prix y sont lus, sous l'id servi. Une variante (r76) prend donc la
	 * saison de l'espèce qu'elle lit, dans sa propre unité.
	 */
	public static function getSeasonsDefault(CompositionPlan $eCompositionPlan, array $plants, array $source): array {

		$eCompositionPlan->expects(['structure', 'season', 'reference', 'climate']);

		$natural = CompositionLib::getNaturalWindows(CompositionLib::getSeasonWeeks(), $eCompositionPlan['season'], $source);

		$reference = match($eCompositionPlan['reference']) {
			CompositionPlan::COMMUNITY, CompositionPlan::CALENDAR => [],
			CompositionPlan::CULTIVATION => CompositionLib::getReferenceWindows(
				CompositionLib::getHarvestWeeks($eCompositionPlan['structure'], $eCompositionPlan['season']),
				$plants,
				CompositionLib::projectByPlants(CompositionLib::getStorage($eCompositionPlan['structure'], array_values(array_unique($plants))), $plants)
			),
		};

		$calendar = CompositionLib::getCalendarWindows($eCompositionPlan['climate'], $plants);

		$seasons = [];

		foreach(array_keys($plants) as $speciesId) {

			if(array_key_exists($speciesId, $reference)) {
				$seasons[$speciesId] = [
					'status' => CompositionSpecies::CULTIVATION,
					'windowWeeks' => CompositionLib::getWindowWeeksFilled(array_keys($reference[$speciesId])),
				];
			} else if(array_key_exists($speciesId, $calendar)) {
				$seasons[$speciesId] = [
					'status' => CompositionSpecies::CALENDAR,
					'windowWeeks' => CompositionLib::getWindowWeeksFilled(array_keys($calendar[$speciesId])),
				];
			} else if(array_key_exists($speciesId, $natural)) {
				$seasons[$speciesId] = [
					'status' => CompositionSpecies::NATURAL,
					'windowWeeks' => CompositionLib::getWindowWeeksFilled($natural[$speciesId]),
				];
			}

		}

		return $seasons;

	}

	/**
	 * Écrit la saison que l'espèce porte déjà, construite par la page (lot r27) : étirée ou déplacée
	 * sur la grille, sous `CUSTOM`.
	 *
	 * Une saison raccourcie refait, dans chaque taille, les semaines non corrigées qui servaient
	 * l'espèce et sortent de sa saison (`CompositionLib::recomposeWeeks()`), comme « Retirer de mes
	 * cultures » (`exclude()`) : recomposer toute l'année toucherait presque toutes les semaines, la
	 * dette des espèces se reportant de semaine en semaine (mesuré au cadrage de r27 : raccourcir de
	 * deux semaines le début d'une saison change 20 à 39 semaines sur 41). Une saison allongée ne
	 * refait rien : ses cases s'allument, s'ajoutent par le menu d'une case, et « Rejouer les paniers »
	 * les prend.
	 *
	 * Rend les semaines refaites dans au moins une taille, que la réponse de la grille rend avec la
	 * ligne de l'espèce. Les semaines que la saison gagne sans être refaites ne changent que le rang
	 * de l'espèce : le vert d'une case ne tient qu'à la saison de son espèce (r29, #87).
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function updateSeason(CompositionSpecies $e): array {

		$e->expects(['id', 'plan' => ['id', 'structure', 'season', 'reference', 'weeks'], 'status', 'windowWeeks']);

		$eCompositionSpeciesWritten = CompositionSpecies::model()
			->select(['id', 'plant', 'name', 'status', 'windowWeeks'])
			->whereId($e['id'])
			->get();

		self::applySeasonsUnwritten($e['plan'], new \Collection([$eCompositionSpeciesWritten]));

		$weeksLeft = array_values(array_diff($eCompositionSpeciesWritten->getSeasonWeeks(), $e->getSeasonWeeks()));

		$weeksRecomposed = [];

		CompositionSpecies::model()->beginTransaction();

			self::update($e, ['status', 'windowWeeks']);

			if($weeksLeft !== []) {

				$eCompositionPlan = $e['plan'];

				$source = CompositionLib::getSourceByPlan($eCompositionPlan);
				$prices = $source['pricesPlan'];

				// Les bases d'abord : une dérivée lit les semaines que sa base vient d'écrire (lot r17).
				foreach(CompositionFormulaLib::getByPlanForCompose($eCompositionPlan) as $eCompositionFormula) {

					$weeks = array_values(array_intersect(CompositionWeekLib::getUnamendedBySpecies($eCompositionFormula, $e), $weeksLeft));

					CompositionLib::recomposeWeeks($eCompositionPlan, $eCompositionFormula, $weeks, $source, $prices, derived: FALSE);

					$weeksRecomposed = array_merge($weeksRecomposed, $weeks);

				}

			}

		CompositionSpecies::model()->commit();

		$weeksRecomposed = array_values(array_unique($weeksRecomposed));
		sort($weeksRecomposed);

		return $weeksRecomposed;

	}

	/**
	 * L'id sous lequel la source du plan sert l'espèce `$e`, et cette source (r76) : l'id de l'espèce
	 * quand le plan la porte déjà, retirée ou non, `CompositionLib::SPECIES_ADDED` pour une espèce que
	 * « Ajouter une espèce » n'a pas encore écrite, que la source chiffre alors en plus des siennes.
	 */
	private static function getSourceServing(CompositionSpecies $e): array {

		if($e->exists()) {
			return [$e['id'], CompositionLib::getSourceByPlan($e['plan'])];
		}

		return [CompositionLib::SPECIES_ADDED, CompositionLib::getSourceByPlan($e['plan'], ePlantAdded: $e['plant'])];

	}

	/**
	 * L'état et la saison qu'une espèce prend quand on l'ajoute aux espèces livrées (r48a, r48c),
	 * qu'elle soit neuve ou que le plan la porte retirée, `['status' => …, 'windowWeeks' => …]`, que
	 * la page construit par `build()` avant `add()` :
	 * la saison de sa source (`getSeasonsDefault()`), celle du plan de culture quand le plan le suit et
	 * qu'il date l'espèce, sinon celle du calendrier type quand le plan a un climat, sinon celle de la
	 * communauté. Une espèce qu'aucun des trois ne date revient avec une saison réglée à la main et
	 * vide : le panneau la fait alors tracer aux poignées (`getSeasonAdded()`).
	 */
	public static function getSeasonRestored(CompositionSpecies $e): array {

		$e->expects(['plan' => ['id', 'structure', 'season', 'reference', 'climate', 'weeks'], 'plant']);

		[$speciesId, $source] = self::getSourceServing($e);

		$seasons = self::getSeasonsDefault($e['plan'], [$speciesId => $e['plant']['id']], $source);

		if(array_key_exists($speciesId, $seasons)) {
			return $seasons[$speciesId];
		}

		return [
			'status' => CompositionSpecies::CUSTOM,
			'windowWeeks' => [],
		];

	}

	/**
	 * Les espèces que « Ajouter une espèce » propose pour le plan (r48c), par leur id : toutes les
	 * espèces en activité de la structure, les siennes propres comprises, sans celles que le plan livre
	 * déjà (02/10/2026 : « les utilisateurs peuvent ajouter n'importe laquelle de leur espèce
	 * à cette fonctionnalité »). C'est ce qui borne la recherche du panneau.
	 *
	 * Ce sont des ids d'espèces de la structure, et non d'espèces du plan : une copie du référentiel
	 * partagé sert au plan l'espèce qu'elle recopie (`reference`), une espèce propre se sert elle-même
	 * (`CompositionLib::getPlantPlan()`).
	 *
	 * Une espèce que le plan livre déjà se propose aussi (r76 lot 2, le 04/10/2026, piste B) :
	 * la choisir ajoute une variante, que le panneau nomme (`getForAdd()`). La liste n'écarte donc
	 * aucune espèce du plan, et seul le départ, qui n'a pas de variante à créer, borne la sienne aux
	 * espèces que son formulaire ne livre pas (`getAddableByDelivered()`).
	 */
	public static function getAddable(CompositionPlan $eCompositionPlan): array {

		$eCompositionPlan->expects(['structure']);

		return self::getAddableByDelivered($eCompositionPlan['structure'], []);

	}

	/**
	 * Les espèces en activité de la structure qui ne servent aucune des espèces `$plantIdsDelivered`,
	 * par leur id : ce que « Ajouter une espèce » propose, dans l'onglet (`getAddable()`) comme au
	 * départ, où les espèces livrées sont celles que son formulaire porte et non celles d'un plan écrit
	 * (r48e, `composition:addStartSpecies`).
	 */
	public static function getAddableByDelivered(\structure\Structure $eStructure, array $plantIdsDelivered): array {

		$cPlantStructure = \plant\Plant::model()
			->select(['id', 'reference'])
			->whereStructure($eStructure)
			->whereStatus(\plant\Plant::ACTIVE)
			->getCollection();

		$plantIds = [];

		foreach($cPlantStructure as $ePlantStructure) {

			if(in_array(CompositionLib::getPlantPlan($ePlantStructure), $plantIdsDelivered, TRUE) === FALSE) {
				$plantIds[] = $ePlantStructure['id'];
			}

		}

		return $plantIds;

	}

	/**
	 * L'espèce que « Ajouter une espèce » ajoute au plan pour l'espèce `$plantId` de la structure
	 * (r48c) : celle que le plan porte retirée, ou une espèce neuve, retirée et sans rien de réglé, que
	 * l'envoi construit. Vide quand l'espèce n'est pas une espèce en activité de la structure du plan.
	 *
	 * Quand le plan livre déjà l'espèce qu'elle sert, y compris quand elle en est une copie, c'est une
	 * variante qui s'ajoute (r76 lot 2, `getVariant()`) : `eCompositionSpeciesBare` porte alors
	 * l'espèce nue livrée, vide sur une espèce nue. `$name` est le nom que l'envoi poste, lu tel quel
	 * pour retrouver une variante retirée sous ce nom, que l'envoi construit ensuite.
	 *
	 * Une espèce neuve à qui la communauté ne mesure aucune part dans les paniers naît en fréquence
	 * « base » : sans elle, sa part est nulle et elle n'entrerait dans un panier que par le bruit du
	 * tirage (`CompositionLib::getOrderByDebt()`), une saison entière sans être servie selon son id.
	 * C'est le cas de toute espèce propre à la structure. Une espèce que le plan portait retirée garde
	 * la fréquence qu'elle avait.
	 *
	 * L'appartenance à la structure se vérifie ici, sur la ligne lue, et non sur la liste que la
	 * recherche a reçue : l'envoi se contourne. `ePlantStructure` est l'espèce choisie, que le panneau
	 * montre et renvoie ; `plant` est celle que le plan écrit.
	 */
	public static function getForAdd(CompositionPlan $eCompositionPlan, int $plantId, ?string $name = NULL): CompositionSpecies {

		$eCompositionPlan->expects(['structure']);

		$ePlantStructure = self::getPlantStructure($eCompositionPlan['structure'], $plantId);

		if($ePlantStructure->empty()) {
			return new CompositionSpecies();
		}

		$ePlant = (CompositionLib::getPlantPlan($ePlantStructure) === $ePlantStructure['id']) ? $ePlantStructure : $ePlantStructure['reference'];

		// « Ajouter une espèce » ajoute l'espèce nue : une variante (r76) n'est pas l'espèce qu'on ajoute.
		$e = CompositionSpecies::model()
			->select(CompositionSpecies::getSelection())
			->wherePlan($eCompositionPlan)
			->wherePlant($ePlant)
			->whereName(NULL)
			->get();

		if($e->empty()) {
			$e = self::getNew($ePlant);
		}

		$e['plan'] = $eCompositionPlan;
		$e['ePlantStructure'] = $ePlantStructure;
		$e['eCompositionSpeciesBare'] = new CompositionSpecies();

		// Une espèce cochée dont la saison n'est pas encore écrite peut se lire retirée
		// (`applySeasonsUnwritten()`).
		if($e->exists()) {

			self::applySeasonsUnwritten($eCompositionPlan, new \Collection([$e]));

			if($e['status'] !== CompositionSpecies::EXCLUDED) {
				return self::getVariant($e, $name);
			}

		}

		return $e;

	}

	/**
	 * La variante que « Ajouter une espèce » ajoute à l'espèce nue livrée `$eCompositionSpeciesBare`
	 * (r76 lot 2) : celle que le plan porte retirée sous le nom posté `$name`, qui revient comme une
	 * espèce retirée, sinon une variante neuve, sans nom, que l'envoi nomme.
	 *
	 * La variante neuve part de son espèce (04/10/2026, « ce que la variante reprend ») :
	 * même saison, même fréquence, et le prix, le pas, le minimum, la quantité par défaut et la
	 * quantité au panier qu'on lui a réglés. Ces quatre derniers se disent dans l'unité de l'espèce, que
	 * la variante n'a pas encore choisie : le panneau les rend quand on coche cette unité, et les vide
	 * pour une autre (`loadAddVariant()`, `composition.js`). Le produit vendu ne se reprend jamais : il
	 * se vend dans l'unité de l'espèce, et deux espèces du plan ne retiennent pas le même.
	 *
	 * Une espèce nue « selon la saison » (fréquence `NULL`) lit sa part des paniers sur la
	 * communauté, qu'une variante ne lit pas (`CompositionLib::projectSource()`) : la variante prend
	 * alors la fréquence « base », celle où naît une variante (r76, `CompositionSpeciesAddTest::testVariant()`),
	 * sans quoi elle n'entrerait dans les paniers que par le bruit du tirage.
	 */
	private static function getVariant(CompositionSpecies $eCompositionSpeciesBare, ?string $name): CompositionSpecies {

		$eCompositionPlan = $eCompositionSpeciesBare['plan'];

		$e = new CompositionSpecies();

		if($name !== NULL) {

			$e = CompositionSpecies::model()
				->select(CompositionSpecies::getSelection())
				->wherePlan($eCompositionPlan)
				->wherePlant($eCompositionSpeciesBare['plant'])
				->whereName($name)
				->whereStatus(CompositionSpecies::EXCLUDED)
				->get();

		}

		if($e->empty()) {

			$e = new CompositionSpecies([
				'plant' => $eCompositionSpeciesBare['plant'],
				'name' => NULL,
				'status' => $eCompositionSpeciesBare['status'],
				'windowWeeks' => $eCompositionSpeciesBare['windowWeeks'],
				'unit' => new \product\Unit(),
				'quantityBasket' => $eCompositionSpeciesBare['quantityBasket'],
				'price' => $eCompositionSpeciesBare['price'],
				'step' => $eCompositionSpeciesBare['step'],
				'minimum' => $eCompositionSpeciesBare['minimum'],
				'quantity' => $eCompositionSpeciesBare['quantity'],
				'weight' => NULL,
				'frequency' => ($eCompositionSpeciesBare['frequency'] === NULL) ? CompositionSpecies::BASE : $eCompositionSpeciesBare['frequency'],
				'product' => new \product\Product(),
			]);

		}

		$e['plan'] = $eCompositionPlan;
		$e['ePlantStructure'] = $eCompositionSpeciesBare['ePlantStructure'];
		$e['eCompositionSpeciesBare'] = $eCompositionSpeciesBare;

		return $e;

	}

	/**
	 * L'espèce de la structure que le « + » du rang de l'espèce nue `$speciesId` choisit d'avance dans
	 * « Ajouter une espèce » (r76 lot 2, piste C) : la copie en activité que la structure a de l'espèce
	 * du plan, la plus ancienne quand elle en a plusieurs, comme `CompositionLib::getPlantsStructure()`
	 * la retient, ou l'espèce elle-même quand elle lui est propre. Elle doit être en activité, parce
	 * que `getForAdd()` n'en accepte pas d'autre. `NULL` quand le plan ne porte pas cette espèce nue ou
	 * que la structure n'en a plus de copie en activité.
	 */
	public static function getPlantStructureId(CompositionPlan $eCompositionPlan, int $speciesId): ?int {

		$eCompositionPlan->expects(['structure']);

		$eCompositionSpecies = CompositionSpecies::model()
			->select(['plant'])
			->wherePlan($eCompositionPlan)
			->whereId($speciesId)
			->whereName(NULL)
			->get();

		if($eCompositionSpecies->empty()) {
			return NULL;
		}

		$plantId = $eCompositionSpecies['plant']['id'];

		$ePlantStructure = \plant\Plant::model()
			->select(['id'])
			->whereStructure($eCompositionPlan['structure'])
			->whereStatus(\plant\Plant::ACTIVE)
			->or(
				fn() => $this->whereReference($plantId),
				fn() => $this->whereId($plantId)
			)
			->sort(['id' => SORT_ASC])
			->get();

		return $ePlantStructure->empty() ? NULL : $ePlantStructure['id'];

	}

	/**
	 * L'espèce en activité `$plantId` de la structure, avec celle du référentiel partagé qu'elle
	 * recopie (`reference`), vide quand la structure n'en a pas de telle. C'est ici que l'appartenance
	 * à la structure se vérifie.
	 */
	private static function getPlantStructure(\structure\Structure $eStructure, int $plantId): \plant\Plant {

		$properties = ['fqn', 'name', 'vignette', 'organ'];

		return \plant\Plant::model()
			->select(array_merge(['id'], $properties, ['reference' => $properties]))
			->whereId($plantId)
			->whereStructure($eStructure)
			->whereStatus(\plant\Plant::ACTIVE)
			->get();

	}

	/**
	 * Une espèce du plan neuve pour l'espèce `$ePlant`, retirée et sans rien de réglé, que l'envoi
	 * construit. Sa fréquence est « base » quand la communauté ne lui mesure aucune part dans les
	 * paniers (`getForAdd()`).
	 */
	private static function getNew(\plant\Plant $ePlant): CompositionSpecies {

		return new CompositionSpecies([
			'plant' => $ePlant,
			'name' => NULL,
			'status' => CompositionSpecies::EXCLUDED,
			'windowWeeks' => NULL,
			'unit' => new \product\Unit(),
			'quantityBasket' => NULL,
			'price' => NULL,
			'step' => NULL,
			'minimum' => NULL,
			'quantity' => NULL,
			'weight' => NULL,
			'frequency' => array_key_exists($ePlant['id'], CompositionLib::getSource()['frequency']['byYear']) ? NULL : CompositionSpecies::BASE,
			'product' => new \product\Product(),
		]);

	}

	/**
	 * L'espèce que « Ajouter une espèce » ajoute au départ pour l'espèce `$plantId` de la structure
	 * (r48e) : rien n'est écrit avant « Composer mes paniers », et l'espèce est donc toujours neuve,
	 * portée par le plan que le départ construit (`$eCompositionPlan`, sans id). Vide quand l'espèce ne
	 * s'ajoute pas : elle n'est pas une espèce en activité de la structure, ou le formulaire du départ
	 * livre déjà l'espèce qu'elle sert (`$plantIdsDelivered`).
	 *
	 * `written` dit que le plan qu'on recommence porte déjà l'espèce, livrée ou retirée :
	 * `CompositionPlanLib::restart()` lui rend alors son unité, sa quantité au panier et son prix, et
	 * le panneau ne les redemande pas.
	 */
	public static function getForStartAdd(CompositionPlan $eCompositionPlan, int $plantId, array $plantIdsDelivered): CompositionSpecies {

		$eCompositionPlan->expects(['structure']);

		$ePlantStructure = self::getPlantStructure($eCompositionPlan['structure'], $plantId);

		if($ePlantStructure->empty()) {
			return new CompositionSpecies();
		}

		$ePlant = (CompositionLib::getPlantPlan($ePlantStructure) === $ePlantStructure['id']) ? $ePlantStructure : $ePlantStructure['reference'];

		if(in_array($ePlant['id'], $plantIdsDelivered, TRUE)) {
			return new CompositionSpecies();
		}

		$eCompositionPlanCurrent = CompositionPlanLib::getByStructure($eCompositionPlan['structure']);

		$e = self::getNew($ePlant);

		$e['plan'] = $eCompositionPlan;
		$e['ePlantStructure'] = $ePlantStructure;
		$e['written'] = (
			$eCompositionPlanCurrent->notEmpty() and
			CompositionSpecies::model()
				->wherePlan($eCompositionPlanCurrent)
				->wherePlant($ePlant)
				->whereName(NULL)
				->exists()
		);

		return $e;

	}

	/**
	 * Ce que le panneau « Ajouter une espèce » du départ montre et demande de l'espèce choisie (r48e),
	 * sous les clés de `loadAdd()` qui s'y appliquent :
	 *
	 * - `seasons`, la saison que chaque source lui donnerait (`CompositionPlanLib::getStartSeasonsByPlant()`),
	 *   que son rang dessinera, et `months`, celle du choix en cours du départ, la première des sources
	 *   du plan qui date l'espèce (`getStartSource()`), une saison vide quand aucune ne la date. Elle
	 *   se lit sans se régler (r58, le 03/10/2026) ;
	 * - `undated`, aucune source ne date l'espèce (`isUndatedAtStart()`) : sa saison se trace alors aux
	 *   poignées du panneau (r67, le 03/10/2026, « la saison se demande au départ »), sur le
	 *   rang de l'onglet Espèces, dont le plan porte les semaines et l'axe des mois
	 *   (`loadSeasonAxis()`). `monthsTraced`, la saison tracée que le rang dessinera, ne se pose qu'une
	 *   fois la saison construite (`loadStartTraced()`) ;
	 * - `typed`, l'unité, la quantité au panier et le prix se tapent : la communauté ne compte pas
	 *   l'espèce et le plan qu'on recommence ne la porte pas ;
	 * - `climateAsked`, jamais : le climat est une question du départ lui-même.
	 */
	public static function loadStartAdd(CompositionSpecies $e): void {

		$e->expects(['plan' => ['structure', 'season', 'reference', 'climate', 'seasonEmpty', 'sources'], 'plant', 'written']);

		$eCompositionPlan = $e['plan'];

		$seasons = CompositionPlanLib::getStartSeasonsByPlant($eCompositionPlan, $e['plant']['id']);
		$source = self::getStartSource($eCompositionPlan, $seasons);

		$e['seasons'] = $seasons;
		$e['months'] = ($source === NULL) ?
			$eCompositionPlan['seasonEmpty'] :
			$seasons[$source];

		$e['undated'] = ($source === NULL);
		$e['monthsTraced'] = NULL;

		if($e['undated']) {
			self::loadSeasonAxis($eCompositionPlan);
		}

		$e['climateAsked'] = FALSE;
		$e['typed'] = ($e['written'] === FALSE and $e->isCommunity() === FALSE);

		if($e['typed']) {
			self::loadUnits($e);
		}

	}

	/**
	 * La saison qu'une espèce ajoutée au départ prend des poignées du panneau (r67), quand aucune source
	 * ne la date (`isUndatedAtStart()`) : `$windowWeeks`, les intervalles postés, réglée à la main
	 * (`CUSTOM`), comme à l'ajout dans l'onglet Espèces (`getSeasonAdded()`). Le rang du départ la
	 * dessine (`loadStartTraced()`) et la poste avec le formulaire, que `composition:doStart`
	 * reconstruit ici.
	 *
	 * Une saison vide est refusée avec le message de `add()` : l'espèce ne serait candidate nulle part.
	 * Le refus se pose avant `build()`, parce que le contrôle de `windowWeeks` admet une saison vide en
	 * `CUSTOM`, celle dont on a retiré le dernier intervalle sur la grille (r30).
	 */
	public static function buildStartSeason(CompositionSpecies $e, array $windowWeeks): void {

		if($windowWeeks === []) {
			CompositionSpecies::fail('windowWeeks.months');
			return;
		}

		$e->build(['status', 'windowWeeks'], [
			'status' => CompositionSpecies::CUSTOM,
			'windowWeeks' => $windowWeeks,
		]);

	}

	/**
	 * Une saison tracée au panneau du départ (`buildStartSeason()`) qui ne croise aucune semaine
	 * livrée `$weeksDelivered` est refusée (r69) : `CompositionPlanLib::create()` écrirait l'espèce,
	 * mais aucun panier ne la servirait. Le refus nomme l'espèce, parce que le formulaire du départ
	 * n'a plus le champ qui l'a tracée. L'onglet Espèces garde sa tolérance : on y règle une saison
	 * avant de changer les semaines livrées.
	 */
	public static function checkStartDelivered(CompositionSpecies $e, array $weeksDelivered): void {

		if(array_intersect($e->getSeasonWeeks(), $weeksDelivered) === []) {
			CompositionSpecies::fail('windowWeeks.delivered', [$e['plant']]);
		}

	}

	/**
	 * La saison tracée au panneau du départ (`buildStartSeason()`), en douze mois, que le rang de
	 * l'espèce dessine à la place de celle de ses sources (`monthsTraced`).
	 */
	public static function loadStartTraced(CompositionSpecies $e): void {

		$e->expects(['plan' => ['seasonMonths'], 'status', 'windowWeeks']);

		$e['monthsTraced'] = CompositionLib::getSeasonShares($e['plan']['seasonMonths'], $e->getSeasonWeeks(), []);

	}

	/**
	 * Aucune source du choix envoyé par le départ ne date l'espèce ajoutée `$e` (r58). Sa saison se
	 * trace alors au panneau qui l'ajoute (r67, `buildStartSeason()`) : partie en `NATURAL`, elle
	 * serait retirée par `CompositionPlanLib::create()`, qui retire toute espèce cultivée que ses
	 * sources ne datent pas, et la personne qui vient de l'ajouter ne la retrouverait plus parmi les
	 * espèces livrées.
	 */
	public static function isUndatedAtStart(CompositionSpecies $e): bool {

		$e->expects(['plan' => ['reference', 'climate', 'sources'], 'plant']);

		$eCompositionPlan = $e['plan'];

		return (self::getStartSource($eCompositionPlan, CompositionPlanLib::getStartSeasonsByPlant($eCompositionPlan, $e['plant']['id'])) === NULL);

	}

	/**
	 * La première source du choix en cours du départ qui date l'espèce dont `$seasons` porte les
	 * saisons, dans l'ordre du plan (`sources`, `CompositionPlanLib::loadStart()`), le climat du plan
	 * à la place de celui que seul le champ connait. `NULL` quand aucune ne la date.
	 */
	private static function getStartSource(CompositionPlan $eCompositionPlan, array $seasons): ?string {

		$sources = array_map(fn(?string $source) => $source ?? $eCompositionPlan['climate'], $eCompositionPlan['sources'][$eCompositionPlan['reference']]);

		return array_find($sources, fn(?string $source) => ($source !== NULL and array_key_exists($source, $seasons)));

	}

	/**
	 * Les espèces du plan de paniers de la structure qui désignent l'espèce `$ePlant`, l'espèce nue et
	 * ses variantes (r76), aucune quand aucune ne le fait. Une espèce propre à la structure est
	 * désignée par son id (r48c) : sa suppression (`plant\PlantLib::delete()`) les cherche ici, pour ne
	 * pas laisser au plan une espèce sans fiche. Une copie du référentiel partagé n'est jamais
	 * désignée, le plan pointant le référentiel.
	 */
	public static function getByPlant(\plant\Plant $ePlant): \Collection {

		$ePlant->expects(['id', 'structure']);

		// Une espèce du référentiel partagé n'a pas de structure, donc pas de plan où la chercher.
		if($ePlant['structure']->empty()) {
			return new \Collection();
		}

		$eCompositionPlan = CompositionPlanLib::getByStructure($ePlant['structure']);

		if($eCompositionPlan->empty()) {
			return new \Collection();
		}

		return CompositionSpecies::model()
			->select(['id', 'plan', 'plant', 'status'])
			->wherePlan($eCompositionPlan)
			->wherePlant($ePlant)
			->getCollection();

	}

	/**
	 * L'espèce du plan peut-elle partir avec la fiche de son espèce ? Seulement retirée des espèces
	 * livrées et servie par aucun panier : une semaine corrigée garde ses lignes quand on retire
	 * l'espèce (`exclude()`), et une ligne sans fiche ne saurait plus dire ce qu'elle sert.
	 */
	public static function isDeletable(CompositionSpecies $e): bool {

		$e->expects(['id', 'status']);

		if($e['status'] !== CompositionSpecies::EXCLUDED) {
			return FALSE;
		}

		return (CompositionLine::model()
			->whereSpecies($e)
			->exists() === FALSE);

	}

	/**
	 * Le climat se demande-t-il pour ajouter l'espèce (r48c) ? Seulement à un plan qui n'en a pas,
	 * pour une espèce que le calendrier type date (`CompositionLib::getCalendarPlantIds()`) et que ni
	 * son plan de culture ni la communauté ne datent : sans climat, elle arriverait sans saison
	 * (`getSeasonRestored()`). Le climat choisi s'écrit alors sur le plan avec l'espèce (`add()`), et
	 * ne se redemande plus.
	 *
	 * Une espèce que le calendrier ne date sous aucun climat, propre à la structure ou non, n'a rien à
	 * y lire : le climat ne lui est pas demandé, et sa saison se trace aux poignées (`getSeasonAdded()`).
	 */
	public static function isClimateAsked(CompositionSpecies $e): bool {

		$e->expects(['plan' => ['id', 'structure', 'season', 'reference', 'climate', 'weeks'], 'plant']);

		$plantId = $e['plant']['id'];

		if($e['plan']['climate'] !== NULL or in_array($plantId, CompositionLib::getCalendarPlantIds(), TRUE) === FALSE) {
			return FALSE;
		}

		[$speciesId, $source] = self::getSourceServing($e);

		return (array_key_exists($speciesId, self::getSeasonsDefault($e['plan'], [$speciesId => $plantId], $source)) === FALSE);

	}

	/**
	 * Les semaines du rang de saison que règlent les panneaux « Ajouter une espèce » (r59) et « Régler
	 * une espèce » (r61), `seasonWeeks`, et l'axe de ses mois, `weekMonths`, posés sur le plan comme la
	 * grille les porte (`CompositionPlanLib::getForScreen()`) : `CompositionUi::getSeasonField()` les
	 * lit.
	 */
	public static function loadSeasonAxis(CompositionPlan $eCompositionPlan): void {

		$eCompositionPlan->expects(['season']);

		$eCompositionPlan['seasonWeeks'] = CompositionLib::getSeasonWeeks();
		$eCompositionPlan['weekMonths'] = CompositionLib::getSeasonWeekMonths($eCompositionPlan['season']);

	}

	/**
	 * L'état et la saison que « Régler une espèce » écrit (r61, le 03/10/2026, « permettre de
	 * régler la saisonnalité ici »), sur la saison réglée aux poignées du panneau (`$windowWeeks`, les
	 * intervalles postés) : celle que l'espèce porte quand le panneau la renvoie telle qu'il l'a
	 * montrée, sinon la saison postée, réglée à la main, comme un réglage de la grille
	 * (`compositionSpecies:doUpdateSeason`). Un enregistrement qui ne touche pas à la saison ne fait
	 * donc pas passer en `CUSTOM` une saison que la communauté, le plan de culture ou le calendrier
	 * type ont écrite.
	 *
	 * La comparaison est lâche (`==`), pour la raison de `getSeasonAdded()` : les semaines postées sont
	 * des chaînes, celles de l'espèce des entiers.
	 *
	 * Aucune saison postée (`NULL`) laisse celle de l'espèce : les poignées ne savent pas vider un rang,
	 * seul le menu d'une case de la grille le fait, et un rang vide ne poste aucun champ. Un envoi qui
	 * ne porte pas le rang, comme ceux qui ne règlent que le produit vendu, ne vide donc pas la saison.
	 */
	public static function getSeasonUpdated(CompositionSpecies $e, ?array $windowWeeks): array {

		$e->expects(['status', 'windowWeeks']);

		if($windowWeeks === NULL or $windowWeeks == $e['windowWeeks']) {
			return [
				'status' => $e['status'],
				'windowWeeks' => $e['windowWeeks'],
			];
		}

		return [
			'status' => CompositionSpecies::CUSTOM,
			'windowWeeks' => $windowWeeks,
		];

	}

	/**
	 * L'enregistrement de « Régler une espèce » (r61) : la saison d'abord, quand le panneau l'a
	 * changée (`$seasoned`), par `updateSeason()`, qui refait les semaines qu'une saison raccourcie
	 * quitte et ne refait rien quand elle s'allonge, comme le geste sur la grille ; puis les réglages
	 * changés (`$properties`), par `update()`. Dans cet ordre, parce qu'une fréquence ou une unité
	 * recomposent tout le plan sur les saisons écrites : la nouvelle doit l'être.
	 *
	 * Rend les semaines que la saison a refaites, comme `updateSeason()`.
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function updatePanel(CompositionSpecies $e, array $properties, bool $seasoned): array {

		$weeks = [];

		CompositionSpecies::model()->beginTransaction();

			if($seasoned) {
				$weeks = self::updateSeason($e);
			}

			// Une écriture sans propriété écrirait toutes celles de l'élément.
			if($properties !== []) {
				self::update($e, $properties);
			}

		CompositionSpecies::model()->commit();

		return $weeks;

	}

	/**
	 * L'état et la saison que « Ajouter une espèce » écrit (r48c, r59), sur la saison réglée aux
	 * poignées du panneau (`$windowWeeks`, les intervalles postés) : celle de la source de l'espèce
	 * (`getSeasonRestored()`) quand le panneau la renvoie telle qu'il l'a proposée, sinon la saison
	 * postée, réglée à la main, comme un réglage de la grille (`compositionSpecies:doUpdateSeason`).
	 * Une espèce qu'aucune source ne date part d'une saison vide : il faut l'avoir tracée, et `add()`
	 * refuse une saison vide.
	 *
	 * La comparaison est lâche (`==`) : les semaines postées sont des chaînes et celles de la source
	 * des entiers, et `build()` n'a pas encore converti les premières. Une valeur qui n'est pas un
	 * entier ne vaut aucune semaine (« 12a » != 12) : elle part en réglage à la main, que le contrôle
	 * de `windowWeeks` refuse.
	 *
	 * Se lit une fois le climat demandé posé sur le plan.
	 *
	 * Une variante (r76 lot 2) part de la saison de son espèce nue, celle que le panneau lui a
	 * proposée (`loadAddVariant()`) : la renvoyer telle quelle garde l'état de l'espèce, qui suit
	 * alors la même source qu'elle.
	 */
	public static function getSeasonAdded(CompositionSpecies $e, array $windowWeeks): array {

		$e->expects(['eCompositionSpeciesBare']);

		$season = $e['eCompositionSpeciesBare']->empty() ? self::getSeasonRestored($e) : self::getSeasonVariant($e);

		if($windowWeeks == $season['windowWeeks']) {
			return $season;
		}

		return [
			'status' => CompositionSpecies::CUSTOM,
			'windowWeeks' => $windowWeeks,
		];

	}

	/**
	 * Ce que le panneau « Ajouter une espèce » écrit de l'espèce choisie (r48c) :
	 *
	 * - `climateAsked`, le climat se demande (`isClimateAsked()`). Le plan prend alors en mémoire
	 *   celui que le panneau envoie (`$climate`), à défaut celui de la structure
	 *   (`CompositionLib::getClimate()`) : c'est le choix que le champ montre, et la saison se lit
	 *   dessus ;
	 * - `seasonAdded`, l'état et la saison que l'espèce prendra (`getSeasonRestored()`), que le panneau
	 *   propose sur un rang de la grille, à régler aux poignées (r59), vide quand aucune source ne date
	 *   l'espèce. Le plan porte les semaines de ce rang (`seasonWeeks`) et l'axe des mois
	 *   (`weekMonths`), comme la grille (`CompositionPlanLib::getForScreen()`) ;
	 * - `typed`, l'unité, la quantité au panier et le prix se tapent : l'espèce est neuve et la
	 *   communauté ne la compte pas (`isTyped()`) ;
	 * - ce que les champs de « Régler une espèce » lisent, que le panneau pose aussi (r60, le
	 *   03/10/2026, « les champs devraient être raccord ») : les unités proposées (`loadUnits()`), le
	 *   prix et les quantités que l'outil lui donnerait (`loadAddPriced()`), sa conservation, et ce que
	 *   la grille en sert, rien tant qu'elle n'est pas livrée.
	 *
	 * Une espèce que le plan porte retirée revient avec ce qu'on lui avait réglé, que les champs
	 * montrent. Une variante a son propre chargement (`loadAddVariant()`).
	 */
	public static function loadAdd(CompositionSpecies $e, ?string $climate): void {

		$e->expects(['plan' => ['id', 'structure', 'season', 'reference', 'climate', 'weeks'], 'plant', 'eCompositionSpeciesBare']);

		if($e['eCompositionSpeciesBare']->notEmpty()) {
			self::loadAddVariant($e);
			return;
		}

		$eCompositionPlan = $e['plan'];
		$plantId = $e['plant']['id'];

		$e['climateAsked'] = self::isClimateAsked($e);

		if($e['climateAsked']) {
			$eCompositionPlan['climate'] = $climate ?? CompositionLib::getClimate($eCompositionPlan['structure']);
		}

		self::loadSeasonAxis($eCompositionPlan);

		$e['seasonAdded'] = self::getSeasonRestored($e);

		$e['typed'] = self::isTyped($e);

		self::loadUnits($e);

		if($e['typed']) {
			self::loadAddTyped($e);
		} else {
			self::loadAddPriced($e, $e['seasonAdded']);
		}

		$e['storage'] = CompositionLib::getStorage($eCompositionPlan['structure'], [$plantId])[$plantId];

		self::applyServed($e, ['served' => 0, 'candidate' => 0]);

	}

	/**
	 * Ce que le panneau « Ajouter une espèce » montre et demande d'une variante (r76 lot 2), sous les
	 * clés de `loadAdd()` :
	 *
	 * - `climateAsked`, jamais : l'espèce est livrée, sa saison est connue ;
	 * - `seasonAdded`, la saison de l'espèce nue (`getSeasonVariant()`) ;
	 * - `unitAsked`, aucune unité cochée (04/10/2026, « l'unité reste à choisir ») : une variante est le
	 *   plus souvent un autre conditionnement, et une unité précochée s'enregistrerait sans avoir été
	 *   choisie. Les champs partent donc sans mot d'unité, comme ceux d'une espèce typée
	 *   (`loadAddTyped()`), et prennent ceux de l'unité qu'on coche (`composition.js`) ;
	 * - `eUnitCopied`, l'unité de l'espèce nue, dans laquelle se disent le prix, le pas, le minimum,
	 *   la quantité et la quantité au panier repris d'elle (`getVariant()`) : la cocher les rend,
	 *   en cocher une autre les vide ;
	 * - `pricesUnits`, le prix de la mercuriale de l'espèce dans chaque unité qu'on peut cocher
	 *   (`getPricesUnits()`), que le champ du prix propose en attente dès qu'on la coche, comme le pas
	 *   et le minimum de cette unité (`loadUnits()`, `stepUnits`).
	 */
	public static function loadAddVariant(CompositionSpecies $e): void {

		$e->expects(['plan' => ['structure', 'season'], 'plant', 'eCompositionSpeciesBare' => ['unit']]);

		$e['climateAsked'] = FALSE;

		self::loadSeasonAxis($e['plan']);

		$e['seasonAdded'] = self::getSeasonVariant($e);
		$e['typed'] = FALSE;

		self::loadUnits($e);
		self::loadAddTyped($e);

		$e['unitAsked'] = TRUE;
		$e['eUnitCopied'] = $e['eCompositionSpeciesBare']['unit'];
		$e['pricesUnits'] = self::getPricesUnits($e);

		self::loadProducts($e);

		$e['storage'] = CompositionLib::getStorage($e['plan']['structure'], [$e['plant']['id']])[$e['plant']['id']];

		self::applyServed($e, ['served' => 0, 'candidate' => 0]);

		$e['seasonalOffered'] = FALSE;

	}

	/**
	 * La saison qu'une variante prend à l'ajout (r76 lot 2) : celle de son espèce nue, état compris.
	 */
	private static function getSeasonVariant(CompositionSpecies $e): array {

		$e->expects(['eCompositionSpeciesBare' => ['status', 'windowWeeks']]);

		return [
			'status' => $e['eCompositionSpeciesBare']['status'],
			'windowWeeks' => $e['eCompositionSpeciesBare']['windowWeeks'],
		];

	}

	/**
	 * Le prix de la mercuriale de l'espèce dans chaque unité que le champ propose, par id d'unité, `''`
	 * pour l'unité de la communauté, que le choix vide désigne, et `NULL` quand aucun relevé ne le
	 * donne. Une espèce que la communauté ne compte pas n'en a dans aucune.
	 *
	 * La source d'un plan sert une espèce dans l'unité qu'elle porte (`CompositionLib::applyUnits()`) :
	 * chaque unité se lit donc sur une source où la variante à venir entre dans cette unité, sous
	 * `CompositionLib::SPECIES_ADDED`, à côté des espèces du plan, et sur la saison de l'espèce nue.
	 * Le nom posé n'est là que pour qu'elle compte comme une variante (`getPlants()`), et ne la
	 * fasse pas lire une seconde part de l'espèce dans les paniers.
	 */
	private static function getPricesUnits(CompositionSpecies $e): array {

		$e->expects(['plan', 'plant', 'eUnitCommunity', 'cUnitOffered', 'eCompositionSpeciesBare' => ['status', 'windowWeeks']]);

		$cCompositionSpecies = self::getByPlan($e['plan']);
		$windows = self::getWindows($cCompositionSpecies);

		$units = $e['eUnitCommunity']->empty() ? [] : ['' => new \product\Unit()];

		foreach($e['cUnitOffered'] as $eUnit) {
			$units[$eUnit['id']] = $eUnit;
		}

		$prices = [];

		foreach($units as $key => $eUnit) {

			$eCompositionSpeciesPriced = new CompositionSpecies([
				'id' => CompositionLib::SPECIES_ADDED,
				'plant' => $e['plant'],
				'name' => '',
				'status' => $e['eCompositionSpeciesBare']['status'],
				'windowWeeks' => $e['eCompositionSpeciesBare']['windowWeeks'],
				'unit' => $eUnit,
				'quantityBasket' => NULL,
				'price' => NULL,
				'step' => NULL,
				'minimum' => NULL,
				'weight' => NULL,
				'product' => new \product\Product(),
			]);

			$cCompositionSpeciesSource = new \Collection([CompositionLib::SPECIES_ADDED => $eCompositionSpeciesPriced]);
			$cCompositionSpeciesSource->appendCollection($cCompositionSpecies);

			$source = CompositionLib::getSourceByPlan($e['plan'], $windows, $cCompositionSpeciesSource);

			self::getWithPrices($e['plan'], $source, new \Collection([CompositionLib::SPECIES_ADDED => $eCompositionSpeciesPriced]));

			$prices[$key] = $eCompositionSpeciesPriced['reference']['unitPrice'];

		}

		return $prices;

	}

	/**
	 * L'espèce est neuve et la communauté ne la compte pas (r48c) : son unité, sa quantité au panier
	 * et son prix se tapent, et rien ne les chiffre avant qu'elle les ait.
	 */
	public static function isTyped(CompositionSpecies $e): bool {
		return ($e->exists() === FALSE and $e->isCommunity() === FALSE);
	}

	/**
	 * Ce que `loadPriced()` pose sur une espèce du plan, pour une espèce que « Ajouter une espèce »
	 * n'a pas encore écrite (r60), sur la saison qu'elle prendra (`$season`, `getSeasonRestored()`) :
	 * le prix se lit à la semaine de cette saison, comme dans « Régler une espèce ». Puis les produits
	 * que le champ « Produit vendu » propose (`loadProducts()`).
	 *
	 * L'espèce chiffrée est une copie, qui porte la saison à venir : `getWithPrices()` lit la saison
	 * de l'espèce qu'il reçoit, et l'espèce ajoutée garde son état jusqu'à l'envoi. Elle porte l'id
	 * sous lequel la source la sert (`getSourceServing()`).
	 */
	public static function loadAddPriced(CompositionSpecies $e, array $season): void {

		$e->expects(['plan', 'plant', 'unit', 'step', 'minimum', 'weight', 'product']);

		[$speciesId, $source] = self::getSourceServing($e);

		$eCompositionSpeciesPriced = new CompositionSpecies([
			'id' => $speciesId,
			'plant' => $e['plant'],
			'status' => $season['status'],
			'windowWeeks' => $season['windowWeeks'],
			'step' => $e['step'],
			'minimum' => $e['minimum'],
			'weight' => $e['weight'],
		]);

		self::getWithPrices($e['plan'], $source, new \Collection([$eCompositionSpeciesPriced]));

		self::copyPriced($e, $eCompositionSpeciesPriced);

		self::loadProducts($e);

	}

	/**
	 * Les produits que la recherche du champ « Produit vendu » propose à l'ajout (r60,
	 * `composition:queryAddProduct`), dans l'unité que le panneau a cochée et que la page vient de
	 * construire sur l'espèce : à l'ajout, rien n'est encore lié qu'une autre unité délierait, et la
	 * recherche suit donc l'unité au lieu de se fermer comme dans « Régler une espèce ».
	 *
	 * « Ajouter un nouveau produit » propose le prix de l'espèce (`getProductCreate()`), qui se dit
	 * dans l'unité d'ouverture (`eUnit`) : dans une autre, il serait faux, et la création part sans.
	 */
	public static function loadAddProducts(CompositionSpecies $e): void {

		if(self::isTyped($e)) {
			self::loadAddTyped($e);
			self::loadProducts($e);
		} else {

			self::loadAddPriced($e, self::getSeasonRestored($e));

			if($e['eUnit']->empty() or $e['eUnit']['fqn'] !== $e->getUnitFqn()) {
				$e['reference']['unitPrice'] = NULL;
			}

		}

	}

	/**
	 * Une espèce typée (`isTyped()`) n'a ni unité ni prix avant qu'on les choisisse : les champs de
	 * « Régler une espèce » partent vides, et prennent les mots et les attentes de l'unité qu'on coche
	 * (`composition.js`). Le produit vendu attend aussi l'unité : son champ se montre quand on en coche
	 * une, et sa recherche lit les candidats de cette unité (`loadAddProducts()`).
	 */
	private static function loadAddTyped(CompositionSpecies $e): void {

		$e['eUnit'] = new \product\Unit();
		$e['eProductLinked'] = new \product\Product();
		$e['reference'] = [
			'unitPrice' => NULL,
			'from' => CompositionLib::PRICE_TYPED,
			'week' => NULL,
			'converted' => NULL,
		];
		$e['conversion'] = NULL;
		$e['minimumDefault'] = NULL;
		$e['quantityDefault'] = NULL;
		$e['stepUnit'] = NULL;
		$e['divisible'] = FALSE;

	}

	/**
	 * Ajoute une espèce aux espèces livrées du plan (r48c), construite par la page : une espèce que le
	 * plan portait retirée reprend l'état et la saison de sa source (`getSeasonAdded()`), une espèce
	 * neuve s'écrit avec eux, et avec son unité, sa quantité au panier et son prix quand la communauté
	 * ne la compte pas. Le climat que le panneau a demandé (`$climate`, `isClimateAsked()`) s'écrit sur
	 * le plan dans la même transaction.
	 *
	 * Toutes les tailles du plan se refont ensuite hors des semaines corrigées
	 * (`CompositionLib::recomposePlan()`) : une espèce de plus change les candidates de chaque semaine
	 * de sa saison, donc de chaque taille, mais une correction reste ce qu'on en a fait.
	 *
	 * Une espèce ne s'ajoute pas sans saison (`windowWeeks.months`) : elle ne serait candidate nulle
	 * part, et l'onglet la dirait livrée sans qu'aucun panier la serve. Celle qu'aucune source ne date
	 * arrive avec la saison tracée aux poignées du panneau, et il lui en faut une semaine.
	 *
	 * Le panneau règle aussi ce que règle « Régler une espèce » (r60, `$properties`,
	 * `getPropertiesPanel()`). Une espèce neuve s'écrit avec, et une espèce que le plan portait retirée
	 * les écrit comme son panneau (`update()`), qui reporte un prix ou un pas sur les lignes des
	 * semaines corrigées et recompose quand une fréquence ou une unité le demandent : le plan ne se
	 * recompose alors qu'une fois.
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function add(CompositionSpecies $e, bool $climate, array $properties = []): void {

		$e->expects(['plan' => ['id', 'structure', 'season', 'reference', 'climate', 'weeks'], 'plant', 'status', 'windowWeeks']);

		if($e->getSeasonWeeks() === []) {
			CompositionSpecies::fail('windowWeeks.months');
			return;
		}

		CompositionSpecies::model()->beginTransaction();

			if($climate) {
				CompositionPlanLib::update($e['plan'], ['climate']);
			}

			$properties = array_merge(['status', 'windowWeeks'], $properties);

			if($e->exists()) {
				self::update($e, $properties);
				$recomposed = self::isRecomposing($e, $properties);
			} else {
				self::create($e);
				$recomposed = FALSE;
			}

			// La source du plan porte les saisons (`stored`, pas au kilo) : elle se lit sur ce qui
			// vient d'être écrit.
			if($recomposed === FALSE) {

				$source = CompositionLib::getSourceByPlan($e['plan']);

				CompositionLib::recomposePlan($e['plan'], $source, $source['pricesPlan']);

			}

		CompositionSpecies::model()->commit();

	}

}
?>
