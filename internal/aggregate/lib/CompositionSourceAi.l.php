<?php
namespace aggregate;

/**
 * Ce que l'agrégé dit à une structure qui compose un panier à venir.
 *
 * ⚠️ **Ce n'est pas `aggregate\Composition`**, qui est un panier déjà vendu et remonté par un
 * contributeur. Cette classe ne décrit rien : elle réunit les lectures de l'agrégé dont la règle
 * de composition de `toolbox` a besoin et les rend dans la même forme, indexées par semaine, espèce
 * et unité. Le sens de la flèche ne s'inverse jamais : l'agrégé alimente le plan, un plan ne
 * remonte jamais dans l'agrégé.
 *
 * Les lectures sont réunies ici plutôt que réparties dans les Lib des modules parce qu'elles ne se
 * lisent jamais séparément : la règle a besoin, pour un même couple (espèce, unité), de savoir si
 * l'espèce est disponible cette semaine-là, à quel rang la placent les paniers partagés et la
 * saison de ses ventes, en quelle quantité elle se met au panier, et ce qu'elle vaut. Un seuil ou un filtre `online` qui bougerait dans l'une
 * sans bouger dans les deux autres rendrait une proposition incohérente sans que rien ne le dise.
 * Précédent : `CultivationCropPlanAiLib`, Lib sans Crud nommée d'après la question qu'elle répond.
 *
 * Tout se lit dans la base commune : aucune requête d'ici ne touche une base `structure_<id>`.
 */
class CompositionSourceAiLib {

	/**
	 * La version en ligne des cinq tables que les lectures d'ici parcourent, sous la forme d'une
	 * chaîne qui change dès que l'une d'elles est republiée, et seulement alors.
	 *
	 * Chaque projection écrit sa nouvelle version à côté de l'ancienne, en `online = FALSE`, puis la
	 * publie en supprimant l'ancienne (`CultivationProjectionAiLib::publish()`) : les lignes publiées
	 * sont toujours insérées après celles qu'elles remplacent, donc le plus grand `id` en ligne de
	 * chaque table change à chaque publication. Les prix et les saisons de vente se publient en place
	 * (`selling\PriceProjectionAiLib::publish()`), en supprimant avant d'insérer : l'auto-incrément ne
	 * revenant pas en arrière, la règle tient pour eux aussi. Quatre lectures sur l'index `online`, qui
	 * s'arrêtent à sa dernière entrée, et une sur la clé primaire de `Sale`, dont toutes les lignes
	 * sont servies : c'est ce qui permet à `toolbox\CompositionLib::getSource()` de garder d'une
	 * requête à l'autre ce qui se lit ici, sans jamais servir une version remplacée.
	 *
	 * `SaleTotal` n'a pas sa lecture : il se publie dans la même transaction que `Sale`, et n'a de
	 * lignes que si `Sale` en a, donc toute publication qui le change change aussi le plus grand `id`
	 * de `Sale`.
	 *
	 * Les cinq maximums se lisent en une requête, les quatre premiers en sous-requêtes de celle de
	 * `Sale` : la version se lit à chaque requête HTTP des paniers, avant même de savoir si le cache
	 * a la source (chantier Performances des paniers, P5). Un `MAX()` sans `GROUP BY` rend toujours
	 * une ligne, `NULL` sur une table vide, comme la lecture séparée.
	 */
	public static function getVersion(): string {

		$max = fn(\ModuleModel $mModule) => new \Sql($mModule->select(['id' => new \Sql('MAX(id)', 'int')])->whereOnline(TRUE)->getSql(), 'int');

		$eSale = Sale::model()
			->select([
				'cultivation' => $max(Cultivation::model()),
				'composition' => $max(Composition::model()),
				'compositionItem' => $max(CompositionItem::model()),
				'price' => $max(Price::model()),
				'sale' => new \Sql('MAX(m1.id)', 'int'),
			])
			->get();

		return implode('-', [
			$eSale['cultivation'],
			$eSale['composition'],
			$eSale['compositionItem'],
			$eSale['price'],
			$eSale['sale'],
		]);

	}

	/**
	 * Les espèces qu'au moins `SLOT_STRUCTURES_MIN` structures consentantes récoltent, semaine par
	 * semaine : `[semaine => [id d'espèce => nombre de structures]]`.
	 *
	 * Le compte porte sur des **structures distinctes** et non sur des cultures : une structure qui
	 * sème la même espèce en quatre séries successives ne vaut qu'une voix, sans quoi le seuil
	 * d'anonymat ne protégerait plus personne.
	 *
	 * `$altitude` est une clé de `CultivationAiSetting::ALTITUDES`, et le palier s'applique ici
	 * comme il s'applique au plan de culture agrégé : une culture dont l'altitude est inconnue
	 * sort de l'échantillon dès qu'un palier est demandé. Deux lectures de la même colonne qui
	 * traiteraient l'inconnu différemment donneraient deux échantillons au même titre.
	 *
	 * Les semaines sont déjà rangées dans 1 à 52 par la projection, qui replie la 53 sur la 52 :
	 * rien n'est à ranger ici.
	 */
	public static function getAvailabilityByWeek(?string $altitude = NULL): array {

		$cCultivation = Cultivation::model()
			->select(['plant', 'structure', 'harvestWeeks', 'altitude'])
			->whereOnline(TRUE)
			->getCollection();

		if($altitude !== NULL) {
			$cCultivation = CultivationCropPlanAiLib::findByAltitude($cCultivation, $altitude);
		}

		$structuresByWeek = [];

		foreach($cCultivation as $eCultivation) {

			$plantId = $eCultivation['plant']['id'];
			$structureId = $eCultivation['structure']['id'];

			foreach($eCultivation['harvestWeeks'] as $week) {
				$structuresByWeek[$week][$plantId][$structureId] = TRUE;
			}

		}

		$availabilityByWeek = [];

		foreach($structuresByWeek as $week => $structuresByPlant) {

			foreach($structuresByPlant as $plantId => $structures) {

				if(count($structures) >= CultivationAiSetting::SLOT_STRUCTURES_MIN) {
					$availabilityByWeek[$week][$plantId] = count($structures);
				}

			}

		}

		ksort($availabilityByWeek);

		return $availabilityByWeek;

	}

	/**
	 * La quantité qui se met au panier, par couple (espèce, unité) :
	 * `[id d'espèce => [id d'unité => ['quantity' => quantité médiane, 'minimum' => 10e centile, 'contributors' => nombre]]]`.
	 *
	 * Le nombre de contributeurs est rendu avec la médiane parce que c'est lui qui choisit l'unité
	 * d'une espèce quand plusieurs couples passent le seuil : la règle retient celle que le plus de
	 * contributeurs emploient.
	 *
	 * À l'année et non à la semaine : découper le relevé par semaine ferait tomber les couples
	 * sous le seuil de contributeurs, et une botte de radis ne change pas de taille en juin.
	 *
	 * Le seuil porte sur des contributeurs distincts et non sur des lignes : une structure qui
	 * compose le même panier chaque semaine ne vaut qu'une voix.
	 *
	 * `minimum` est la plus petite quantité qu'on met couramment au panier, celle en dessous de
	 * laquelle la règle ne sert plus une espèce (lot r25) : le 10e centile des lignes, sous le seuil
	 * de la médiane qu'il accompagne. Le 25e était trop près de la médiane pour les petites quantités
	 * (poireau 0,6 kg pour 1), et une voix par structure (la médiane des plus petites quantités de
	 * chaque contributeur) rendait presque partout la même valeur, donc aucune grosse structure ne
	 * tire le centile (mesures dans `.claude/state/paniers/r25-minimum/cadrage.md`).
	 */
	public static function getQuantityByPlantUnit(): array {

		$cCompositionItem = CompositionItem::model()
			->select([
				'plant', 'unit', 'quantity',
				'composition' => ['contributor'],
			])
			->whereOnline(TRUE)
			->whereQuantity('!=', NULL)
			->whereUnit('!=', NULL)
			->getCollection();

		$quantities = [];
		$contributors = [];

		foreach($cCompositionItem as $eCompositionItem) {

			$plantId = $eCompositionItem['plant']['id'];
			$unitId = $eCompositionItem['unit']['id'];

			$quantities[$plantId][$unitId][] = (float)$eCompositionItem['quantity'];
			$contributors[$plantId][$unitId][$eCompositionItem['composition']['contributor']] = TRUE;

		}

		$quantityByPlantUnit = [];

		foreach($quantities as $plantId => $quantitiesByUnit) {

			foreach($quantitiesByUnit as $unitId => $values) {

				if(count($contributors[$plantId][$unitId]) >= CultivationAiSetting::SLOT_STRUCTURES_MIN) {
					$quantityByPlantUnit[$plantId][$unitId] = [
						'quantity' => self::getMedian($values),
						'minimum' => self::getPercentile($values, 0.1),
						'contributors' => count($contributors[$plantId][$unitId]),
					];
				}

			}

		}

		return $quantityByPlantUnit;

	}

	/**
	 * La place d'une espèce dans les paniers, qui ordonne les candidates de la règle :
	 * `['byWeek' => [semaine => [id d'espèce => part]], 'byYear' => [id d'espèce => part]]`.
	 *
	 * La part des paniers partagés (getCompositionFrequency()), où les semaines que ces paniers ne
	 * mesurent pas reçoivent la part à l'année modulée par la saison des ventes (applySeasonality()).
	 * Une espèce sans saison de vente en ligne garde sa part à l'année, plate.
	 *
	 * Une part modulée peut dépasser 1 : c'est un poids relatif, que le lecteur plafonne après sa
	 * propre mise à l'échelle (`toolbox\CompositionLib::getShares()`, `getHeat()`), et c'est cette
	 * valeur brute qui ordonne les espèces.
	 */
	public static function getFrequency(): array {

		return self::applySeasonality(self::getCompositionFrequency(), self::getSaleSeasonality());

	}

	/**
	 * La place d'une espèce dans les paniers partagés, dans la forme de getFrequency().
	 *
	 * La part est le nombre de paniers qui portent l'espèce, rapporté au nombre de paniers : une
	 * espèce présente dans tous les paniers d'une semaine vaut 1, et aucune ne dépasse 1, un panier
	 * qui la porte sur plusieurs lignes ne la comptant qu'une fois.
	 *
	 * À la semaine, seules les paires qu'au moins `SLOT_STRUCTURES_MIN` contributeurs distincts
	 * composent sont rendues : en deçà, la part dirait ce que font deux ou trois structures, et la
	 * règle retombe alors sur la part à l'année, que la saison des ventes module. À l'année, tout ce
	 * qui est en ligne compte, le relevé agrégé étant déjà publié sous le seuil d'anonymat.
	 */
	public static function getCompositionFrequency(): array {

		$cComposition = Composition::model()
			->select(['id', 'week', 'contributor'])
			->whereOnline(TRUE)
			->getCollection(index: 'id');

		$compositionsByWeek = [];

		foreach($cComposition as $eComposition) {
			$compositionsByWeek[$eComposition['week']] = ($compositionsByWeek[$eComposition['week']] ?? 0) + 1;
		}

		$cCompositionItem = CompositionItem::model()
			->select(['composition', 'plant'])
			->whereOnline(TRUE)
			->getCollection();

		$plantsByComposition = [];
		$itemsByWeek = [];
		$contributorsByWeek = [];
		$itemsByYear = [];

		foreach($cCompositionItem as $eCompositionItem) {

			$compositionId = $eCompositionItem['composition']['id'];

			// Une ligne dont le panier n'est pas en ligne n'appartient à aucune semaine publiée.
			if($cComposition->offsetExists($compositionId) === FALSE) {
				continue;
			}

			$eComposition = $cComposition[$compositionId];
			$week = $eComposition['week'];
			$plantId = $eCompositionItem['plant']['id'];

			// Un panier qui porte l'espèce sur deux lignes (deux variétés, deux unités) ne la porte
			// qu'une fois : sans cela, la part dépasse 1 là où une espèce est au panier ou n'y est pas.
			if(isset($plantsByComposition[$compositionId][$plantId])) {
				continue;
			}

			$plantsByComposition[$compositionId][$plantId] = TRUE;

			$itemsByWeek[$week][$plantId] = ($itemsByWeek[$week][$plantId] ?? 0) + 1;
			$contributorsByWeek[$week][$plantId][$eComposition['contributor']] = TRUE;
			$itemsByYear[$plantId] = ($itemsByYear[$plantId] ?? 0) + 1;

		}

		$byWeek = [];

		foreach($itemsByWeek as $week => $itemsByPlant) {

			foreach($itemsByPlant as $plantId => $items) {

				if(count($contributorsByWeek[$week][$plantId]) >= CultivationAiSetting::SLOT_STRUCTURES_MIN) {
					$byWeek[$week][$plantId] = $items / $compositionsByWeek[$week];
				}

			}

		}

		$byYear = [];

		foreach($itemsByYear as $plantId => $items) {
			$byYear[$plantId] = $items / $cComposition->count();
		}

		return ['byWeek' => $byWeek, 'byYear' => $byYear];

	}

	/**
	 * Le poids d'un mois dans les ventes d'une espèce : `[id d'espèce => [mois => poids]]`.
	 *
	 * Le poids rapporte la part de l'espèce dans le chiffre du mois à sa part dans le chiffre de
	 * l'année. Il vaut 1 pour une espèce vendue au rythme de toutes les autres, plus de 1 dans sa
	 * pleine saison, moins de 1 hors saison, et ne dépend pas de ce que pèse l'espèce dans l'année :
	 * c'est ce qui permet de le multiplier par une part des paniers partagés sans en changer l'échelle.
	 *
	 * Les trois totaux, celui d'un mois, celui d'une espèce sur l'année et celui de l'année, somment
	 * toutes les cases, y compris celles sous le seuil que `Sale` n'écrit pas : les écarter gonflerait
	 * la part des espèces courantes dans un mois où les espèces rares ne passent pas. Ils se lisent
	 * donc dans `SaleTotal` (SaleTotalAiLib).
	 *
	 * Un total que `SaleTotal` n'a pas vaut la somme des cases servies qu'il couvre : la projection
	 * ne l'écrit pas quand son reste, ce qu'il ajoute aux cases servies, réunit trop peu de structures
	 * pour être publié (`selling\SaleProjectionAiLib::getTotals()`). Le poids d'une telle espèce se calcule
	 * alors sur ses seuls mois servis.
	 */
	protected static function getSaleSeasonality(): array {

		$cSale = Sale::model()
			->select(['plant', 'month', 'amount'])
			->getCollection();

		$totalByMonth = [];
		$totalByPlant = [];
		$total = 0.0;

		foreach($cSale as $eSale) {

			$plantId = $eSale['plant']['id'];

			$totalByMonth[$eSale['month']] = ($totalByMonth[$eSale['month']] ?? 0.0) + $eSale['amount'];
			$totalByPlant[$plantId] = ($totalByPlant[$plantId] ?? 0.0) + $eSale['amount'];
			$total += $eSale['amount'];

		}

		$cSaleTotal = SaleTotal::model()
			->select(['plant', 'month', 'amount'])
			->getCollection();

		foreach($cSaleTotal as $eSaleTotal) {

			if($eSaleTotal['plant']->notEmpty()) {
				$totalByPlant[$eSaleTotal['plant']['id']] = $eSaleTotal['amount'];
			} else if($eSaleTotal['month'] !== NULL) {
				$totalByMonth[$eSaleTotal['month']] = $eSaleTotal['amount'];
			} else {
				$total = $eSaleTotal['amount'];
			}

		}

		$seasonality = [];

		foreach($cSale as $eSale) {

			$plantId = $eSale['plant']['id'];

			$seasonality[$plantId][$eSale['month']] = ($eSale['amount'] / $totalByMonth[$eSale['month']]) / ($totalByPlant[$plantId] / $total);

		}

		return $seasonality;

	}

	/**
	 * La fréquence des paniers partagés, où chaque paire (semaine, espèce) que ces paniers ne
	 * mesurent pas reçoit la part à l'année de l'espèce multipliée par le poids de son mois.
	 *
	 * Une part mesurée à la semaine n'est jamais touchée : elle dit ce que les paniers ont porté, la
	 * saison des ventes ne fait que l'approcher. Sans poids pour le mois, la paire reste absente et la
	 * règle retombe sur la part à l'année, comme sans saison.
	 *
	 * Le mois d'une semaine est celui de son jeudi, la table de la bibliothèque de cultures, et ne
	 * dépend pas de la saison du plan : la fréquence est lue une fois pour toutes les saisons.
	 */
	protected static function applySeasonality(array $frequency, array $seasonality): array {

		for($week = 1; $week <= 52; $week++) {

			$month = CultivationProjectionAiLib::getMonth($week);

			foreach($frequency['byYear'] as $plantId => $share) {

				if(
					isset($frequency['byWeek'][$week][$plantId]) or
					isset($seasonality[$plantId][$month]) === FALSE
				) {
					continue;
				}

				$frequency['byWeek'][$week][$plantId] = $share * $seasonality[$plantId][$month];

			}

		}

		return $frequency;

	}

	/**
	 * Le prix médian d'un panier partagé, arrondi à l'euro : le prix visé qu'un plan porte à son
	 * ouverture. `NULL` quand aucun panier en ligne ne porte de valeur.
	 *
	 * L'arrondi à l'euro est celui d'un prix qu'on affiche sur un étal : proposer 15,73 € obligerait
	 * le premier geste de l'utilisateur à le corriger.
	 */
	public static function getBasketPriceMedian(): ?int {

		return self::getBasketPriceQuartiles()['median'] ?? NULL;

	}

	/**
	 * Le prix d'un panier partagé en trois repères, arrondis à l'euro : `['low', 'median', 'high']`,
	 * le premier quartile, la médiane et le troisième quartile. `NULL` quand aucun panier en ligne
	 * ne porte de valeur. L'écran les écrit sous le prix d'une formule qu'on ajoute : c'est tout ce
	 * qu'une structure sans historique a pour le choisir.
	 *
	 * Les quartiles suivent la convention de `getMedian()` : l'élément d'indice `count / 4` et
	 * `3 × count / 4` après tri croissant, sans interpolation.
	 */
	public static function getBasketPriceQuartiles(): ?array {

		$values = Composition::model()
			->whereOnline(TRUE)
			->whereValue('!=', NULL)
			->getColumn('value');

		if($values === []) {
			return NULL;
		}

		$values = array_map('floatval', $values);
		sort($values);

		return [
			'low' => (int)round($values[intdiv(count($values), 4)]),
			'median' => (int)round(self::getMedian($values)),
			'high' => (int)round($values[intdiv(3 * count($values), 4)]),
		];

	}

	/**
	 * Le prix et sa dispersion, semaine par semaine :
	 * `[semaine => [id d'espèce => [id d'unité => ['contributors', 'median', 'low', 'high']]]]`.
	 *
	 * `low` et `high` sont les 10e et 90e centiles du relevé, pas des bornes observées : leur
	 * rapport dit si l'unité est homogène. Une tomate à la pièce peut être une cerise ou une
	 * cœur-de-bœuf, et sa paire franchit pourtant le seuil de contributeurs — celui-ci protège
	 * l'anonymat, il ne garantit pas que deux relevés portent sur la même chose.
	 *
	 * Les deux centiles sont rendus bruts et non leur rapport : la règle qui les emploie compare
	 * `high` à `low` multiplié, ce qui ne suppose pas que `low` soit non nul.
	 *
	 * Le seuil de contributeurs est déjà porté par `online`, que la passe de prix pose : une ligne
	 * en deçà reste en préparation et n'est jamais servie.
	 */
	public static function getPriceByWeek(): array {

		$cPrice = Price::model()
			->select(['week', 'plant', 'unit', 'contributors', 'median', 'low', 'high'])
			->whereOnline(TRUE)
			->getCollection();

		$priceByWeek = [];

		foreach($cPrice as $ePrice) {

			$priceByWeek[$ePrice['week']][$ePrice['plant']['id']][$ePrice['unit']['id']] = [
				'contributors' => $ePrice['contributors'],
				'median' => (float)$ePrice['median'],
				'low' => (float)$ePrice['low'],
				'high' => (float)$ePrice['high'],
			];

		}

		ksort($priceByWeek);

		return $priceByWeek;

	}

	/**
	 * La médiane **haute** : l'élément d'indice `count / 2` après tri croissant.
	 *
	 * Le choix entre médiane haute, médiane basse et moyenne des deux ne se devine pas, et il
	 * change la proposition : sans cette convention écrite une fois, deux implémentations fidèles
	 * au reste de la règle rendent deux paniers différents.
	 */
	private static function getMedian(array $values): float {
		return self::getPercentile($values, 0.5);
	}

	/**
	 * Le centile `$part` d'un relevé, au rang `floor($part × n)` en base 0 après tri croissant, sans
	 * interpolation : la convention de la médiane haute, et celle des centiles de prix
	 * (`selling\PriceProjectionAiLib::getPercentile()`), pour que deux repères d'un même relevé se lisent de
	 * la même façon.
	 */
	private static function getPercentile(array $values, float $part): float {

		sort($values);

		return $values[(int)floor($part * count($values))];

	}

}
?>
