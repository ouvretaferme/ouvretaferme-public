<?php
namespace toolbox;

/**
 * Ce que les cinq entités de l'outil « paniers de l'année » partagent, et la règle qui compose
 * leurs paniers.
 *
 * La règle est celle du cadrage (`cadrage-debutant.md`, §6.2 et §6.3), en huit étapes appliquées
 * semaine par semaine dans l'ordre croissant. Elle est écrite ici en deux temps que rien ne
 * mélange : `getSource()` lit une fois tout ce que la règle consulte, et `propose()` compose à
 * partir de ce seul tableau, sans une requête. C'est ce qui rend la règle éprouvable sur des
 * données construites à la main, et c'est ce qui garantit qu'un même relevé rend le même panier :
 * rien ne se relit en cours de route.
 *
 * Deux listes de semaines vivent dans des colonnes `json`, et une colonne `json` n'a aucune borne
 * que le schéma puisse vérifier : `CompositionPlan.weeks` porte les semaines livrées, et
 * `CompositionSpecies.windowWeeks` les intervalles d'une fenêtre de commercialisation réglée à la
 * main. Les deux contrôles sont ici plutôt que recopiés dans chaque `properties()`, parce que
 * c'est la même borne de semaine qui les gouverne et qu'elle se lit une fois.
 *
 * La borne n'est pas écrite : elle est lue sur `CompositionWeek.week`, qui est la colonne où une
 * semaine finit par s'enregistrer. La recopier ici la ferait diverger le jour où la colonne bouge.
 * Toute saison compte 52 semaines, y compris une année ISO longue : sa semaine 53 n'est jamais
 * proposée (tranché le 21/09, à la recette de la planche v11).
 */
class CompositionLib {

	use \Notifiable;

	/**
	 * Les bornes que le cadrage pose en hypothèse (**A**) : un facteur d'ajustement de 0,7 à 1,4
	 * repris trois fois au plus, un plancher d'organes à partir de 4 lignes, et une dispersion de prix
	 * qui interdit de valoriser au-delà d'un rapport de 3 entre le 90e et le 10e centile. Écrites une
	 * fois, elles se règlent en une ligne le jour où un panier réel les contredit.
	 *
	 * Le nombre de lignes d'un panier se borne par formule depuis r11 (`CompositionFormula.linesMin`
	 * et `linesMax`, 4 et 8 par défaut). `LINES_MIN` reste le plus petit panier que le départ sait
	 * remplir : une structure qui a vendu moins d'espèces proposables ne part pas de ses ventes
	 * (`CompositionPlanLib::getForStart()`).
	 */
	const LINES_MIN = 4;
	const FACTOR_LOW = 0.7;
	const FACTOR_HIGH = 1.4;
	const FACTOR_TRIES = 3;
	const FLOOR_FROM = 4;
	const DISPERSION_MAX = 3;

	/**
	 * Le plafond de l'ajustement au pas (`applyPriceSteps()`, lot r23, tranché le 27/09) :
	 * une ligne ne dépasse pas 2 fois sa quantité de référence. Sans lui, l'ajustement rattrape aussi
	 * les semaines de soudure, mais avec 4 kg de poireau au panier (`.claude/state/paniers/r23-cible`).
	 */
	const STEP_CEILING = 2.0;

	/**
	 * Le stock et le besoin en kilos sont des sommes de flottants : un centième de gramme d'écart ne
	 * fait pas une espèce sans stock (`propose()`).
	 */
	const KILOS_EPSILON = 0.00001;

	/**
	 * Le nombre de candidates sur lesquelles se mesure la valeur d'une mise au panier : les 10
	 * lignes qu'un panier peut porter au plus, et 2 de marge pour les espèces que le tirage saute
	 * au plafond d'organe (§6.2, « D'où part le premier nombre de lignes »).
	 */
	const SAMPLE_SIZE = 12;

	/**
	 * L'amplitude du bruit qui départage les dettes (`getOrderByDebt()`), en paniers : à 1 au plus,
	 * il ne peut pas inverser deux dettes qui s'écartent de plus d'un panier. C'est la borne haute
	 * permise, et elle est prise : plus le bruit est faible, plus le retour d'une espèce devient
	 * mécanique (voir `getNoise()`).
	 */
	const NOISE = 1.0;

	/**
	 * Le plus long trou qu'une saison bouche, en semaines (`getWindowWeeksFilled()`, r61). Mesuré le
	 * 03/10/2026 sur la base de dev, hors saisons réglées à la main : 84 saisons sur 160 arrivaient en
	 * plusieurs barres avec les trous d'une semaine bouchés, et les trous restants allaient de 2 à 6
	 * semaines (épinard 3, navet 2, laitue 2 sur la structure 7). Une vraie seconde saison, celle du
	 * radis botte, du chou-fleur ou du brocoli, s'écarte de la première de 9 à 13 semaines : 4 bouche
	 * le bruit sans jamais l'atteindre.
	 */
	const SEASON_GAP_FILLED = 4;

	/**
	 * Le nombre d'espèces que « Conserver plus » propose pour une période de soudure (`getStretch()`),
	 * les plus petites rallonges d'abord. Mesuré le 27/09 sur la structure 7 : pour les semaines 12
	 * et 13, 17 espèces conservées du plan rouvriraient la semaine 13, dont la pastèque (2 semaines
	 * au lieu de 27) et le fenouil (1 au lieu de 19). Les cinq premières sont l'oignon, la
	 * betterave, la patate douce et les deux choux, quatre des cinq espèces de la planche ; au-delà,
	 * la rallonge dépasse sept semaines, et le menu ne propose plus un réglage mais une autre façon
	 * de conserver.
	 */
	const STRETCH_MAX = 5;

	/**
	 * Le pas par défaut d'une espèce servie au kilo (lot r18, demandé le 27/09) : 0,2 kg,
	 * et 0,1 kg au-dessus de `KILO_PRICE_FINE` euros le kilo, où 200 g d'écart pèsent trop sur le
	 * prix du panier. Le prix est celui que la grille écrit à côté du nom (`applyKiloSteps()`).
	 */
	const KILO_STEP = 0.2;
	const KILO_STEP_FINE = 0.1;
	const KILO_PRICE_FINE = 10.0;

	/**
	 * L'id sous lequel la source d'un plan sert une espèce que « Ajouter une espèce » n'a pas encore
	 * écrite (`getSourceByPlan()`, `$ePlantAdded`) : le panneau la chiffre avant qu'elle ait le sien
	 * (r76). Aucune `CompositionSpecies` ne porte l'id 0.
	 */
	const SPECIES_ADDED = 0;

	/**
	 * D'où vient le prix d'une espèce dans la cascade de la structure (`getPrices()`) : le prix
	 * tapé sur l'espèce du plan, à défaut celui qu'elle a déclaré dans sa mercuriale, à défaut
	 * celui de son catalogue rattaché à la mercuriale, à défaut celui de son prévisionnel financier,
	 * à défaut celui de ses propres ventes. Une espèce qu'aucun ne chiffre n'est pas dans la cascade : elle prend la médiane de la
	 * communauté, semaine par semaine, que `PRICE_NATIONAL` nomme quand le panneau de l'espèce dit
	 * d'où vient son prix (`getPriceReference()`).
	 *
	 * `PRICE_PRODUCT` (r39b) passe devant tout : une espèce liée au produit qui la vend
	 * (`CompositionSpecies.product`) prend son prix, celui que la vente composera
	 * (`CompositionSaleLib::getPriceBasket()`), et le prix tapé, que le panneau ne montre plus, se tait.
	 *
	 * `PRICE_CONVERTED` (lot r14) nomme un prix que la structure ou la communauté donnent dans l'unité
	 * de la communauté, converti par le poids dans l'unité que la structure a choisie
	 * (`applyUnits()`) : 12,00 € / kg valent 0,60 € la pièce de 0,05 kg.
	 */
	const PRICE_PRODUCT = 'product';
	const PRICE_TYPED = 'typed';
	const PRICE_DECLARED = 'declared';
	const PRICE_CATALOG = 'catalog';
	const PRICE_FORECAST = 'forecast';
	const PRICE_SALES = 'sales';
	const PRICE_NATIONAL = 'national';
	const PRICE_CONVERTED = 'converted';

	/**
	 * D'où vient le poids d'une unité du panier (lot L3, `getWeightReference()`) : le poids tapé sur
	 * l'espèce du plan, à défaut la médiane de ceux que la structure a posés sur ses cultures de
	 * l'espèce dans la saison du plan (`getCultivatedWeights()`), à défaut le poids courant que
	 * Ouvretaferme pose pour l'espèce dans cette unité (`ToolboxSetting::COMPOSITION_WEIGHTS`). Une
	 * espèce hors de cette liste, que ni la structure ni ses cultures ne pèsent, reste sans poids.
	 *
	 * Le dernier recours est une liste écrite et non une médiane de la communauté : celle-ci n'a pas
	 * de quoi se former, aucune espèce n'étant pesée par plus de quatre structures (planche L3,
	 * relevé du 24/09).
	 */
	const WEIGHT_TYPED = 'typed';
	const WEIGHT_CULTIVATED = 'cultivated';
	const WEIGHT_BASE = 'base';

	/**
	 * Les unités du panier dont une unité a un poids à dire, chacune avec la propriété de
	 * `series\Cultivation` qui le porte : le formulaire de culture le demande à une culture comptée en
	 * kilos et récoltée à la pièce ou à la botte. Une espèce servie au kilo n'en a pas besoin, et une
	 * barquette ou un sachet n'ont de poids nulle part où le lire.
	 */
	const WEIGHED = [
		'one' => 'unitWeight',
		'bunch' => 'bunchWeight',
	];

	/**
	 * Un seul chou par panier (revue v12) : aucune semaine ne sert deux espèces dont le nom
	 * contient ce mot, sans tenir compte de la casse, « Chou rave » et « Choux » compris. Le critère
	 * est le nom et non la famille botanique, tranché : « basé sur la présence du mot
	 * chou dans le nom ».
	 */
	const CABBAGE = 'chou';

	/**
	 * Le rythme de chaque fréquence qu'une espèce peut choisir (lot L9, planche v2 tranchée par
	 * le 26/09), `[semaines servies, sur]` : ce que la génération lui fait tenir
	 * (`applyFrequencies()`), et ce que le formulaire écrit sous chaque choix.
	 */
	const FREQUENCIES = [
		CompositionSpecies::BASE => [3, 4],
		CompositionSpecies::MEDIUM => [1, 2],
		CompositionSpecies::LOW => [1, 3],
		CompositionSpecies::DIVERSIFICATION => [1, 5],
	];

	/**
	 * Les seuils de la fréquence courante d'une espèce qui n'en a pas choisi (`getFrequencyCurrent()`),
	 * sur la part des semaines où elle est servie parmi celles où elle est candidate : les milieux
	 * des rythmes, tels que la planche les a tranchés (62,5, 41,5 et 26,5 %). En dessous du dernier,
	 * l'espèce est en diversification.
	 */
	const FREQUENCY_FLOORS = [
		CompositionSpecies::BASE => 0.625,
		CompositionSpecies::MEDIUM => 0.415,
		CompositionSpecies::LOW => 0.265,
	];

	/**
	 * L'écart minimal, en numéros de semaine, entre deux paniers qui servent une même espèce, selon
	 * sa fréquence (demande du 27/09, « Les faible ne peuvent pas être présent deux
	 * semaines à la suite dans le résultat ; Les diversifications écart minimal de 3 semaines entre
	 * deux paniers ») : une espèce faible servie en semaine 10 ne revient qu'en 12, une espèce de
	 * diversification qu'en 13. La base et la moyenne peuvent revenir la semaine suivante
	 * (`isSpaced()`).
	 *
	 * Ces écarts ne valent que pour une espèce qui n'a pas choisi sa fréquence, rangée sur sa part.
	 * Une fréquence choisie est tenue par son propre plafond, lu sur `FREQUENCIES` (`isCapped()`,
	 * lot r44).
	 */
	const FREQUENCY_GAPS = [
		CompositionSpecies::BASE => 1,
		CompositionSpecies::MEDIUM => 1,
		CompositionSpecies::LOW => 2,
		CompositionSpecies::DIVERSIFICATION => 3,
	];

	/**
	 * Ce que `getSource()` a lu, gardé pour le reste de l'exécution (voir `getSource()`).
	 */
	private static ?array $source = NULL;

	/**
	 * Ce que `getSales()` a lu, par id de structure : une exécution peut en traverser plusieurs.
	 */
	private static array $sales = [];

	/**
	 * Les semaines ISO livrées : une liste non vide d'entiers distincts, tous dans la borne de
	 * `CompositionWeek.week`. L'ordre n'est pas imposé, la lecture trie.
	 *
	 * Une liste vide est refusée ici et non chez l'appelant : un plan sur zéro semaine fait boucler
	 * la règle de composition sur rien, sans rien dire.
	 */
	public static function checkWeeks(array $weeks): bool {

		if($weeks === []) {
			return FALSE;
		}

		[$minimum, $maximum] = CompositionWeek::model()->getPropertyRange('week');

		foreach($weeks as $week) {

			if(is_int($week) === FALSE or $week < $minimum or $week > $maximum) {
				return FALSE;
			}

		}

		return (count(array_unique($weeks)) === count($weeks));

	}

	/**
	 * La saison de vente d'une espèce (lot r27) : une liste non vide de couples `[début, fin]`, chacun
	 * croissant et dans la borne de `CompositionWeek.week`, triés, et séparés d'une semaine au moins.
	 * C'est la forme que `mergeWindowWeeks()` rend, et la seule qui s'écrit : `CompositionSpecies`
	 * normalise avant de contrôler, et le contrôle juge la valeur normalisée.
	 *
	 * Deux barres qui se touchent sur la grille ne font qu'une : sinon la poignée de fin de la
	 * première et celle de début de la seconde se superposent, et l'une des deux ne se saisit plus.
	 */
	public static function checkWindowWeeks(?array $windowWeeks): bool {

		if($windowWeeks === NULL or $windowWeeks === [] or array_is_list($windowWeeks) === FALSE) {
			return FALSE;
		}

		$stopPrevious = NULL;

		foreach($windowWeeks as $window) {

			if(self::isWindow($window) === FALSE) {
				return FALSE;
			}

			[$start, $stop] = $window;

			if($stopPrevious !== NULL and $start <= $stopPrevious + 1) {
				return FALSE;
			}

			$stopPrevious = $stop;

		}

		return TRUE;

	}

	/**
	 * Un couple `[début, fin]` d'entiers, croissant, dans la borne de `CompositionWeek.week`.
	 */
	private static function isWindow(mixed $window): bool {

		if(is_array($window) === FALSE or array_keys($window) !== [0, 1]) {
			return FALSE;
		}

		[$start, $stop] = $window;

		return (
			self::checkWeeks([$start]) and
			self::checkWeeks([$stop]) and
			$start <= $stop
		);

	}

	/**
	 * La saison `$windowWeeks` triée, ses intervalles qui se chevauchent ou se touchent fusionnés :
	 * `[[38, 44], [14, 20], [21, 28]]` devient `[[14, 28], [38, 44]]`. Une liste qui porte un couple
	 * mal formé revient telle quelle, et `checkWindowWeeks()` la refuse.
	 */
	public static function mergeWindowWeeks(array $windowWeeks): array {

		foreach($windowWeeks as $window) {

			if(self::isWindow($window) === FALSE) {
				return $windowWeeks;
			}

		}

		$windowWeeks = array_values($windowWeeks);

		usort($windowWeeks, fn(array $window1, array $window2) => $window1 <=> $window2);

		$merged = [];

		foreach($windowWeeks as [$start, $stop]) {

			$last = array_key_last($merged);

			if($last !== NULL and $start <= $merged[$last][1] + 1) {
				$merged[$last][1] = max($merged[$last][1], $stop);
			} else {
				$merged[] = [$start, $stop];
			}

		}

		return $merged;

	}

	/**
	 * Les semaines d'une saison, dans l'ordre, une fois chacune.
	 */
	public static function getWindowWeeksList(array $windowWeeks): array {

		$weeks = [];

		foreach($windowWeeks as [$start, $stop]) {
			$weeks = array_merge($weeks, range($start, $stop));
		}

		return $weeks;

	}

	/**
	 * La saison qu'ouvrent les semaines `$weeks`, en intervalles, les trous de `SEASON_GAP_FILLED`
	 * semaines au plus bouchés : `[14, 15, 20, 21]` donne `[[14, 21]]`, `[14, 15, 21, 22]` garde deux
	 * barres. Une semaine où 9 structures récoltent au lieu de 10 passe sous le seuil d'anonymat de la
	 * communauté et fait un trou, et quelques semaines creuses du plan de culture en font autant :
	 * sans les boucher, la grille de l'année coupait une saison de semaines grises (le
	 * 03/10/2026, sur la structure 7). Un trou plus long est une vraie seconde saison, celle du radis
	 * ou du chou-fleur. `NULL` pour une liste vide : une espèce sans semaine n'a pas de saison.
	 *
	 * Une saison réglée à la main (`CUSTOM`) n'y passe jamais : son trou est voulu (r30, #93).
	 *
	 * Les trous bouchés, un morceau de moins de `PIECE_MIN` semaines se retire, comme dans le
	 * calendrier type (r54 : « vire les semaines isolées ») : le plan de culture et la
	 * communauté laissent eux aussi des semaines isolées, une récolte précoce ou le débord d'une
	 * conservation sur janvier, que la grille prenait pour une seconde saison (r72, le Chou pé-tsaï de
	 * la 7, `[[1, 1], [14, 40]]`). Une espèce dont tous les morceaux sont courts les garde : ce
	 * sont ses seules semaines, et sans elles elle n'aurait plus de saison du tout.
	 */
	public static function getWindowWeeksFilled(array $weeks): ?array {

		if($weeks === []) {
			return NULL;
		}

		$weeks = array_unique($weeks);
		sort($weeks);

		$filled = [];

		foreach($weeks as $week) {

			$last = array_key_last($filled);

			if($last !== NULL and $week - $filled[$last][1] - 1 <= self::SEASON_GAP_FILLED) {
				$filled[$last][1] = $week;
			} else {
				$filled[] = [$week, $week];
			}

		}

		$filledLong = array_values(array_filter($filled, fn(array $window) => self::isWindowShort($window, $filled) === FALSE));

		return ($filledLong === []) ? $filled : $filledLong;

	}

	/**
	 * Le morceau `$window` de la saison `$windowWeeks` dure-t-il moins de `PIECE_MIN` semaines ? Le
	 * seuil des saisons du calendrier type (r54), qui vaut aussi pour celles du plan (r72) : dans
	 * celles que `getWindowWeeksFilled()` ouvre, et dans celles qu'on règle à la main (étape `short`
	 * de `CompositionSpecies::properties()`).
	 *
	 * Une saison qui passe le nouvel an est d'un seul tenant (r72, décision du coordinateur sous
	 * mandat) : le poireau ou la mâche d'octobre à la mi-janvier, `[[1, 2], [40, 52]]`, est une seule
	 * période de 15 semaines, que le plan coupe en deux parce que sa saison va de la semaine 1 à la
	 * dernière. Un morceau qui touche la première semaine se mesure donc avec celui qui touche la
	 * dernière, et l'inverse. Un `[[1, 1], [14, 40]]` reste court : 40 n'est pas la fin de l'année.
	 */
	public static function isWindowShort(array $window, array $windowWeeks): bool {

		$seasonWeeks = self::getSeasonWeeks();
		[$weekFirst, $weekLast] = [min($seasonWeeks), max($seasonWeeks)];

		$length = $window[1] - $window[0] + 1;

		foreach($windowWeeks as $windowOther) {

			if($windowOther === $window) {
				continue;
			}

			if(
				($window[0] === $weekFirst and $windowOther[1] === $weekLast) or
				($window[1] === $weekLast and $windowOther[0] === $weekFirst)
			) {
				$length += $windowOther[1] - $windowOther[0] + 1;
			}

		}

		return $length < \aggregate\CalendarProjectionAiLib::PIECE_MIN;

	}

	/**
	 * Tout ce que la règle consulte, lu une fois :
	 *
	 * - `availability` : `[semaine => [id d'espèce => structures qui récoltent]]` (étape 1) ;
	 * - `seasons` : `[id d'espèce => [mois]]`, une espèce absente étant de saison toute l'année
	 *   (étape 2) ;
	 * - `units` : `[id d'espèce => ['unit' => id d'unité, 'fqn' => fqn de l'unité, 'quantity' =>
	 *   quantité médiane, 'step' => pas d'arrondi, 'community' => NULL]]` (étape 7). `community` ne se
	 *   remplit que sur la source d'un plan, pour une espèce dont la structure a choisi l'unité, qui
	 *   porte alors aussi `kilos` (`getSourceByPlan()`, `applyUnits()`) ;
	 * - `quantities` : `[id d'espèce => [id d'unité => ['quantity', 'contributors']]]`, les quantités
	 *   médianes de toutes les unités au seuil, dont `units` retient une par espèce, et où une unité
	 *   choisie par la structure prend la sienne ;
	 * - `offered` : `[id d'unité => ['unit', 'fqn', 'step']]`, les unités qu'une structure peut choisir
	 *   pour une espèce, le kilo, la pièce et la botte (lot r14) ;
	 * - `prices` : `[semaine => [id d'espèce => [id d'unité => ['median', 'low', 'high', …]]]]`
	 *   (étape 8) ;
	 * - `frequency` : `['byWeek' => …, 'byYear' => …]` (étape 5) ;
	 * - `organs` : `[id d'espèce => organe ou NULL]` (étape 6) ;
	 * - `weights` : `[id d'espèce => [fqn d'unité => kilos]]`, le poids courant des espèces que
	 *   `ToolboxSetting::COMPOSITION_WEIGHTS` nomme, lu par `getWeightReference()` et non par la règle ;
	 * - `priced`, vide ici : il ne se remplit que sur la source d'un plan
	 *   (`getSourceByPlan()`), comme `storage`, la durée de conservation, qui dépend de la structure.
	 *
	 * **L'unité d'une espèce est celle que le plus de contributeurs emploient** dans les paniers
	 * partagés, et c'est elle seule que la règle propose : la quantité, le prix et l'arrondi s'y
	 * rapportent tous trois. À égalité, la plus petite unité par id, pour que deux lectures du même
	 * relevé choisissent la même.
	 *
	 * ⚠️ Une espèce n'est candidate que si elle porte un couple (espèce, unité) au seuil de
	 * contributeurs dans les paniers partagés : c'est ce couple qui donne son unité. Les deux
	 * replis de l'étape 7 (`aggregate\Price.quantityMedian`, puis une quantité de référence et une
	 * ligne « non fondée ») ne servent donc jamais, et ne sont pas écrits : il faudrait d'abord une
	 * unité à l'espèce, que rien d'autre ne donne. C'est le choix du script de mesure qui a produit
	 * la planche validée.
	 *
	 * **Lu une fois par exécution**, et gardé : une case allumée sur la grille la demande trois fois,
	 * au contrôle de la fenêtre (`CompositionLine::properties()`), à la quantité de référence et à la
	 * réponse. Rien de ce qui est lu ne dépend de la structure connectée (l'agrégé de la communauté,
	 * les saisons de la mercuriale, l'organe des espèces et les unités du référentiel partagé) : une
	 * seule copie vaut pour toutes les structures qu'une exécution traverse.
	 *
	 * **Les quatre lectures de l'agrégé se gardent aussi d'une requête à l'autre**, sous la version
	 * en ligne de ses tables (`aggregate\CompositionSourceAiLib::getVersion()`) : elles coûtaient
	 * 0,6 s à chaque geste sur la grille (mesuré le 23/09/2026 sur la base de dev, dont 0,47 s pour
	 * la disponibilité), pour un résultat qui ne change qu'au cron de 4 h. La clé change avec la
	 * publication, donc une version remplacée n'est jamais servie ; le délai ne sert qu'à ne pas
	 * laisser une clé par nuit s'accumuler. Le reste se relit à chaque exécution : ce sont des tables
	 * du référentiel et de la mercuriale, qu'un administrateur modifie hors de tout cron, et leur
	 * lecture ne coûte que quelques millisecondes.
	 */
	public static function getSource(): array {
		return self::$source ??= self::readSource();
	}

	/**
	 * L'espèce sous laquelle l'id servi `$speciesId` se lit dans la source de la communauté, et dans
	 * tout ce qui se range par espèce du référentiel : les cultures, le calendrier type, le stock, les
	 * ventes et la mercuriale (r76).
	 *
	 * La source d'un plan (`getSourceByPlan()`) sert les espèces du plan sous l'id de leur
	 * `CompositionSpecies`, et `plants` dit pour chacune l'espèce qu'elle lit : `CompositionSpecies.plant`,
	 * l'id du référentiel partagé pour une copie, celui de la structure pour une espèce qui lui est
	 * propre. Une variante (`CompositionSpecies.name`) lit la même espèce que l'espèce nue qu'elle
	 * décline. La source de la communauté (`getSource()`), que le départ parcourt sans plan, sert les
	 * espèces sous leur propre id et n'a pas de `plants` : chaque id s'y lit sous lui-même.
	 *
	 * ⚠️ Les deux ids sont des entiers des mêmes plages : un tableau rangé par l'un et lu par l'autre
	 * rend un résultat faux sans erreur. Les noms le disent, `$speciesId` pour l'id servi et `$plantId`
	 * pour l'id lu.
	 */
	public static function getPlantId(int $speciesId, array $source): int {
		return ($source['plants'] === []) ? $speciesId : $source['plants'][$speciesId];
	}

	/**
	 * L'espèce nue du plan qui lit l'espèce `$plantId`, celle qui n'est pas une variante, NULL quand
	 * le plan n'en porte pas : c'est sous elle que se rangent les cultures et le stock de l'espèce
	 * (`getPlanned()`, `getStock()`). Dans une source sans `plants`, l'espèce elle-même.
	 */
	public static function getSpeciesBare(int $plantId, array $source): ?int {

		if($source['plants'] === []) {
			return $plantId;
		}

		return array_key_exists($plantId, $source['speciesBare']) ? $source['speciesBare'][$plantId] : NULL;

	}

	/**
	 * La source de la communauté `$source` (`getSource()`) projetée sur les espèces d'un plan (r76),
	 * `$plants` étant `[id d'espèce du plan => id d'espèce lue]` et `$variants` les ids des variantes
	 * (`CompositionSpeciesLib::getPlants()`) : chaque table rangée par espèce du référentiel se range
	 * sous l'id de l'espèce du plan qui la lit (`getPlantId()`), et `plants` porte la correspondance.
	 * Deux espèces du plan qui lisent la même espèce, l'espèce nue et sa variante, en reçoivent chacune
	 * une copie.
	 *
	 * Deux choses ne se projettent pas :
	 * - la disponibilité, que chaque espèce du plan lit par `plants` : `getOrder()` la projette
	 *   lui-même, avec ou sans fenêtres ;
	 * - la part de la communauté dans les paniers (`frequency`), pour une variante : elle naît en
	 *   fréquence « base », sans quoi la carotte entrerait deux fois plus souvent dans les paniers
	 *   (r71, repris tel quel).
	 */
	private static function projectSource(array $source, array $plants, array $variants): array {

		ksort($plants);

		$plantsCounted = array_diff_key($plants, array_flip($variants));

		$projected = $source;

		foreach(['units', 'quantities', 'seasons', 'organs', 'cabbages', 'weights'] as $key) {
			$projected[$key] = self::projectByPlants($source[$key], $plants);
		}

		$projected['prices'] = array_map(fn(array $pricesByPlant) => self::projectByPlants($pricesByPlant, $plants), $source['prices']);

		$projected['frequency'] = [
			'byWeek' => array_map(fn(array $frequencyByPlant) => self::projectByPlants($frequencyByPlant, $plantsCounted), $source['frequency']['byWeek']),
			'byYear' => self::projectByPlants($source['frequency']['byYear'], $plantsCounted),
		];

		$projected['plants'] = $plants;
		$projected['speciesBare'] = array_flip($plantsCounted);

		return $projected;

	}

	/**
	 * `$valuesByPlant`, rangé par espèce du référentiel, rangé par espèce du plan selon `$plants`
	 * (`[id d'espèce du plan => id d'espèce lue]`) : une espèce du plan dont l'espèce lue n'y est pas
	 * n'y est pas non plus.
	 */
	public static function projectByPlants(array $valuesByPlant, array $plants): array {

		$values = [];

		foreach($plants as $speciesId => $plantId) {

			if(array_key_exists($plantId, $valuesByPlant)) {
				$values[$speciesId] = $valuesByPlant[$plantId];
			}

		}

		return $values;

	}

	/**
	 * La source d'un plan (lot r9) : `getSource()`, que toutes les structures partagent, plus ce qui
	 * ne dépend que de ce plan et de sa structure.
	 *
	 * Elle sert les espèces du plan sous l'id de leur `CompositionSpecies` (r76, `projectSource()`), et
	 * `plants` dit l'espèce que chacune lit (`getPlantId()`). `$ePlantAdded` est l'espèce que « Ajouter
	 * une espèce » chiffre avant de l'écrire, servie sous `SPECIES_ADDED`.
	 *
	 * - l'unité que la structure a choisie pour une espèce à la place de celle de la communauté
	 *   (`applyUnits()`, lot r14) ;
	 * - `storage` : la durée de conservation de chaque espèce qui a une unité au panier
	 *   (`getStorage()`), que `getStock()` lit ;
	 * - `pricesPlan` : les prix de la structure pour ce plan, la cascade de `getPrices()` telle quelle
	 *   (chantier Performances, P2). L'écran la lisait trois fois par requête, 17 requêtes chacune :
	 *   `CompositionSpeciesLib::getWithPrices()` la reprend d'ici, comme chaque geste qui compose,
	 *   recompose ou chiffre une ligne (P2b). Aucun ne relance la cascade : `PricesOnceSentinel` le
	 *   tient ;
	 * - `priced` : les espèces auxquelles la cascade de la structure donne un prix (`getPrices()`), qui
	 *   entrent au panier sur une semaine de leur saison où la communauté ne relève aucun prix
	 *   (`getOrder()`) ;
	 * - le pas par défaut d'une espèce servie au kilo, 0,2 ou 0,1 kg selon son prix
	 *   (`applyKiloSteps()`, lot r18).
	 *
	 * `$windows` est la saison de chaque espèce du plan (`CompositionSpeciesLib::getWindowsByPlan()`),
	 * lue ici quand l'appelant ne l'a pas déjà. `$cCompositionSpecies` sont les espèces du plan
	 * (`CompositionSpeciesLib::getByPlan()`), que l'écran lit une fois (chantier Performances, P7) :
	 * les unités choisies et les prix tapés s'y lisent au lieu d'être relus. `$cultivations` sont les
	 * cultures de la structure (`getCultivations()`), que l'écran lit une fois (chantier Performances,
	 * P8) : les poids des cultures s'y lisent au lieu d'être relus.
	 *
	 * Rien n'est gardé d'un appel à l'autre : la fiche de l'espèce règle sa conservation, le plan de
	 * culture bouge, et les deux doivent se voir au geste suivant. Les lectures sont bornées par la
	 * structure et coûtent quelques millisecondes, dont un `getPrices()`, qui ne relit que les tables
	 * de la structure (ses ventes sont gardées par `getSales()`). Il se lit une fois, après les
	 * unités : la cascade ne lit de la source que `units`, que rien ne change ensuite.
	 */
	public static function getSourceByPlan(CompositionPlan $eCompositionPlan, ?array $windows = NULL, ?\Collection $cCompositionSpecies = NULL, ?array $cultivations = NULL, \plant\Plant $ePlantAdded = new \plant\Plant()): array {

		$eCompositionPlan->expects(['id', 'structure', 'season', 'weeks']);

		$windows ??= CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan);

		$plants = CompositionSpeciesLib::getPlants($cCompositionSpecies ?? CompositionSpeciesLib::getPlantsByPlan($eCompositionPlan));

		if($ePlantAdded->notEmpty()) {
			$plants['plants'][self::SPECIES_ADDED] = $ePlantAdded['id'];
		}

		$units = ($cCompositionSpecies === NULL) ? CompositionSpeciesLib::getUnitsByPlan($eCompositionPlan) : CompositionSpeciesLib::getUnits($cCompositionSpecies);

		$source = self::applyUnits($eCompositionPlan, self::projectSource(self::getSource(), $plants['plants'], $plants['variants']), $units, $cultivations);
		$source['storage'] = array_intersect_key(self::projectByPlants(self::getStorage($eCompositionPlan['structure'], array_values(array_unique($source['plants']))), $source['plants']), $source['units']);

		$prices = self::getPrices($eCompositionPlan, $source, $cCompositionSpecies);

		$source['pricesPlan'] = $prices;

		foreach(array_keys($prices) as $speciesId) {
			$source['priced'][$speciesId] = TRUE;
		}

		return self::applyKiloSteps($eCompositionPlan, $source, $prices, $windows);

	}

	/**
	 * Les espèces de la structure que désignent les espèces de plan `$plantIds`, de la plus ancienne à
	 * la plus récente, chacune avec `plantPlan`, l'id de l'espèce de plan qu'elle sert.
	 *
	 * Un plan de paniers désigne une espèce du référentiel partagé par l'id du référentiel, que la
	 * structure recopie (`reference`), et une espèce propre à la structure par son id à elle (r48c,
	 * « Ajouter une espèce » accepte toute espèce de la structure). Les produits en vente, la durée de
	 * conservation et la création de produit se lisent sur l'espèce de la structure, dans tous les cas.
	 *
	 * `$plantIds` sont des espèces lues (`getPlantId()`), jamais des espèces du plan : une variante
	 * se vend et se conserve comme l'espèce qu'elle décline.
	 */
	public static function getPlantsStructure(\structure\Structure $eStructure, array $plantIds): \Collection {

		if($plantIds === []) {
			return new \Collection();
		}

		$cPlant = \plant\Plant::model()
			->select(['id', 'reference', 'storage'])
			->whereStructure($eStructure)
			->or(
				fn() => $this->whereReference('IN', $plantIds),
				fn() => $this->whereId('IN', $plantIds)
			)
			->sort(['id' => SORT_ASC])
			->getCollection();

		foreach($cPlant as $ePlant) {
			$ePlant['plantPlan'] = self::getPlantPlan($ePlant);
		}

		return $cPlant;

	}

	/**
	 * L'espèce que lit un plan pour l'espèce `$ePlantStructure` d'une structure
	 * (`CompositionSpecies.plant`) : celle du référentiel partagé qu'elle recopie, elle-même quand
	 * elle n'en recopie aucune.
	 */
	public static function getPlantPlan(\plant\Plant $ePlantStructure): int {

		$ePlantStructure->expects(['id', 'reference']);

		if($ePlantStructure['reference']->empty()) {
			return $ePlantStructure['id'];
		}

		return $ePlantStructure['reference']['id'];

	}

	/**
	 * La durée de conservation des espèces de plan `$plantIds` pour une structure :
	 * `[id d'espèce => ['weeks' => int, 'typed' => bool, 'plant' => Plant]]`.
	 *
	 * - `weeks` : celle que la structure a posée sur sa copie de l'espèce, sinon celle du référentiel,
	 *   toujours renseignée (reprise du référentiel décrite dans
	 *   `migration/2026-09-27-paniers-conservation-et-plan-de-reference.md`). Sur une copie, NULL
	 *   veut dire « suit la référence » (`plant\PlantLib::duplicateForStructure()`) ;
	 * - `typed` : la durée vient de la copie de la structure, et le panneau de l'espèce ne dit plus
	 *   « durée habituelle de l'espèce » ;
	 * - `plant` : la copie de la structure, que « Changer la durée de conservation après récolte »
	 *   ouvre, vide quand la structure n'en a pas.
	 *
	 * Une espèce propre à la structure (r48c) n'a pas de référentiel à suivre : sa durée est celle de
	 * sa fiche, aucune tant que la fiche n'en dit pas (`plant\PlantLib::getStorageDefault()`), et elle
	 * vient toujours de la structure.
	 *
	 * Deux requêtes et non une jointure de la copie sur sa référence : `COALESCE` se fait ici, en
	 * lisant les espèces de la seule structure.
	 */
	public static function getStorage(\structure\Structure $eStructure, array $plantIds): array {

		if($plantIds === []) {
			return [];
		}

		$shared = \plant\Plant::model()
			->select(['id', 'storage'])
			->whereId('IN', $plantIds)
			->whereStructure(NULL)
			->getColumn('storage', index: 'id');

		// Une structure n'a qu'une copie par espèce partagée ; la plus ancienne est gardée si
		// l'historique en a laissé deux.
		$plantsStructure = [];

		foreach(self::getPlantsStructure($eStructure, $plantIds) as $ePlantStructure) {
			$plantsStructure[$ePlantStructure['plantPlan']] ??= $ePlantStructure;
		}

		$storage = [];

		foreach($shared as $plantId => $weeks) {

			$ePlantCopy = array_key_exists($plantId, $plantsStructure) ? $plantsStructure[$plantId] : new \plant\Plant();
			$typed = ($ePlantCopy->notEmpty() and $ePlantCopy['storage'] !== NULL);

			$storage[$plantId] = [
				'weeks' => $typed ? $ePlantCopy['storage'] : $weeks,
				'typed' => $typed,
				'plant' => $ePlantCopy,
			];

		}

		foreach($plantsStructure as $plantId => $ePlantStructure) {

			if(array_key_exists($plantId, $shared)) {
				continue;
			}

			$storage[$plantId] = [
				'weeks' => $ePlantStructure['storage'] ?? 0,
				'typed' => TRUE,
				'plant' => $ePlantStructure,
			];

		}

		return $storage;

	}

	/**
	 * La semaine d'une semaine ISO du plan de culture, `2026-W14`, rapportée à la saison `$season` :
	 * de 1 à 52 dans la saison, 0 et moins l'année d'avant (`2025-W50` vaut -2 pour la saison
	 * 2026), plus de 52 l'année d'après. Une semaine 53 se replie sur la 52, comme les projections de
	 * l'agrégé (`getSeasonWeeks()`) : une récolte d'une année longue ne se perd pas.
	 */
	public static function getRelativeWeek(string $weekIso, int $season): int {

		[$year, $week] = explode('-W', $weekIso);

		return ((int)$year - $season) * 52 + min((int)$week, 52);

	}

	/**
	 * Les cultures des saisons S-1 et S du plan de culture de la structure, telles que les paniers les
	 * lisent : `[['season' => int, 'plant' => id d'espèce, 'mainUnit' => …, 'unitWeight' => ?float,
	 * 'bunchWeight' => ?float, 'harvest' => ?float, 'weeks' => [semaine ISO, …]], …]`, l'espèce par son
	 * id dans le référentiel partagé comme dans `getCultivatedBySeason()`, `harvest` la récolte que la
	 * culture prévoit (`series\Cultivation::getHarvestExpectedOrTarget()`).
	 *
	 * Une seule requête, où l'espèce et la série sont jointes : les délégués les relisaient chacun par
	 * un `IN` sur toutes les cultures, 441 séries sur la 7 (chantier Performances, P8). Les trois tables
	 * vivent dans la base commune.
	 */
	public static function getCultivations(\structure\Structure $eStructure, int $season): array {

		$cCultivation = \series\Cultivation::model()
			->join(\series\Series::model(), 'm1.series = m2.id')
			->join(\plant\Plant::model(), 'm1.plant = m3.id')
			->select([
				'season' => new \Sql('m1.season', 'int'),
				'mainUnit', 'unitWeight', 'bunchWeight', 'yieldExpected', 'harvestWeeksExpected',
				'plantShared' => new \Sql('COALESCE(m3.reference, m3.id)', 'int'),
				'seriesArea' => new \Sql('m2.area', 'int'),
				'seriesAreaTarget' => new \Sql('m2.areaTarget', 'int'),
			])
			->where('m1.structure', $eStructure)
			->where('m1.season', 'IN', [$season - 1, $season])
			->getCollection();

		$cultivations = [];

		foreach($cCultivation as $eCultivation) {

			$eCultivation['series'] = new \series\Series([
				'area' => $eCultivation['seriesArea'],
				'areaTarget' => $eCultivation['seriesAreaTarget'],
			]);

			$cultivations[] = [
				'season' => $eCultivation['season'],
				'plant' => $eCultivation['plantShared'],
				'mainUnit' => $eCultivation['mainUnit'],
				'unitWeight' => $eCultivation['unitWeight'],
				'bunchWeight' => $eCultivation['bunchWeight'],
				'harvest' => $eCultivation->getHarvestExpectedOrTarget(),
				'weeks' => $eCultivation['harvestWeeksExpected'] ?? [],
			];

		}

		return $cultivations;

	}

	/**
	 * Les semaines de récolte que le plan de culture de la structure prévoit, `[id d'espèce =>
	 * [semaine relative, …]]` (`getRelativeWeek()`), l'espèce par son id dans le référentiel partagé
	 * comme dans `getCultivatedBySeason()`. Les cultures des saisons S-1 et S comptent : un poireau
	 * semé l'an passé se récolte en janvier, et une courge récoltée en octobre de l'an passé se
	 * conserve jusqu'aux premières semaines. Les semaines de l'année d'après ne servent aucun panier
	 * de la saison et ne sont pas rendues. Une espèce sans semaine prévue n'apparaît pas.
	 *
	 * `$cultivations` sont les cultures de la structure (`getCultivations()`), lues ici quand
	 * l'appelant ne les a pas déjà (chantier Performances, P8).
	 */
	public static function getHarvestWeeks(\structure\Structure $eStructure, int $season, ?array $cultivations = NULL): array {

		$cultivations ??= self::getCultivations($eStructure, $season);

		$weekLast = max(self::getSeasonWeeks());

		$weeks = [];

		foreach($cultivations as $cultivation) {

			foreach($cultivation['weeks'] as $weekIso) {

				$week = self::getRelativeWeek($weekIso, $season);

				if($week <= $weekLast) {
					$weeks[$cultivation['plant']][$week] = $week;
				}

			}

		}

		return array_map(fn(array $plantWeeks) => array_values($plantWeeks), $weeks);

	}

	/**
	 * Les semaines que le plan de culture de la structure ouvre à chacune des espèces de `$plants`,
	 * `[id servi => id lu]` (`getPlantId()`), rendues `[id servi => [semaine => récoltée]]` : les
	 * semaines de récolte qu'il prévoit pour l'espèce lue (`$harvestWeeks`, `getHarvestWeeks()`),
	 * chacune prolongée de la conservation de l'espèce servie (`$storage`, rangé par id servi,
	 * `getStorage()`), bornées à la saison. Une variante suit donc les cultures de l'espèce qu'elle
	 * décline (r71, décidé le 03/10/2026). Une récolte de la semaine w ouvre les semaines
	 * w à w + conservation : une récolte de l'année d'avant ouvre donc le début de la saison.
	 * `récoltée` est FALSE pour une semaine que la seule conservation ouvre.
	 *
	 * C'est la saison qu'écrit « Synchroniser » (`CompositionPlanLib::synchronize()`), conservation
	 * comprise : la grille passe ces semaines au vert comme les autres (r29, #87). Une espèce qui
	 * n'ouvre aucune semaine de la saison n'y est pas, qu'elle ne soit pas datée ou que sa seule
	 * récolte, l'année d'avant, ne se conserve pas jusque-là : elle garde la saison qu'elle a. Elle est cultivée, seule sa date dans la saison manque.
	 */
	public static function getReferenceWindows(array $harvestWeeks, array $plants, array $storage): array {

		$seasonWeeks = self::getSeasonWeeks();
		[$weekFirst, $weekLast] = [min($seasonWeeks), max($seasonWeeks)];

		$windows = [];

		foreach($plants as $speciesId => $plantId) {

			if(array_key_exists($plantId, $harvestWeeks) === FALSE) {
				continue;
			}

			$weeks = $storage[$speciesId]['weeks'];
			$window = [];

			foreach($harvestWeeks[$plantId] as $week) {

				if($week + $weeks < $weekFirst) {
					continue;
				}

				foreach(range(max($week, $weekFirst), min($week + $weeks, $weekLast)) as $weekOpen) {
					$window[$weekOpen] ??= FALSE;
				}

				if($week >= $weekFirst) {
					$window[$week] = TRUE;
				}

			}

			if($window !== []) {
				ksort($window);
				$windows[$speciesId] = $window;
			}

		}

		return $windows;

	}

	/**
	 * Les semaines que le calendrier type du climat `$climate` ouvre à chacune des espèces de `$plants`
	 * (r48b), dans la forme de `getReferenceWindows()` : `[id servi => [semaine => récoltée]]`, les
	 * semaines où l'espèce se récolte habituellement sous ce climat, prolongées de sa conservation, et
	 * `récoltée` à FALSE pour une semaine que la seule conservation ouvre. Une espèce que le
	 * calendrier ne date pas n'y est pas, et un plan sans climat n'en a aucune.
	 *
	 * Le calendrier compte l'année sur 52 semaines, celles d'une saison de paniers
	 * (`getSeasonWeeks()`) : ses semaines s'emploient telles quelles. Il se lit ici pour tout le
	 * package : `aggregate\AnonymitySentinelAiTest` déclare ce fichier parmi les lecteurs de l'agrégé.
	 */
	public static function getCalendarWindows(?string $climate, array $plants): array {

		if($climate === NULL or $plants === []) {
			return [];
		}

		$cCalendar = \aggregate\CalendarAiLib::getByClimate($climate);

		$windows = [];

		foreach($plants as $speciesId => $plantId) {

			if($cCalendar->offsetExists($plantId) === FALSE) {
				continue;
			}

			$window = array_fill_keys($cCalendar[$plantId]['weeks'], FALSE);

			foreach($cCalendar[$plantId]['harvestWeeks'] as $week) {
				$window[$week] = TRUE;
			}

			ksort($window);

			$windows[$speciesId] = $window;

		}

		return $windows;

	}

	/**
	 * Les espèces que le calendrier type sait dater sous au moins un climat (r48c) : celles que
	 * « Ajouter une espèce » propose en plus de celles que le plan porte déjà
	 * (`CompositionSpeciesLib::getAddable()`).
	 */
	public static function getCalendarPlantIds(): array {
		return \aggregate\CalendarAiLib::getPlantIds();
	}

	/**
	 * Le climat que le départ propose à une structure (r48b) : celui qu'elle a déclaré, sinon celui que
	 * sa position donne en France, sinon aucun, et il se choisit alors à la main
	 * (`aggregate\CalendarAiLib::getClimate()`).
	 */
	public static function getClimate(\structure\Structure $eStructure): ?string {
		return \aggregate\CalendarAiLib::getClimate($eStructure);
	}

	/**
	 * Le pas par défaut des espèces servies au kilo (lot r18) : `KILO_STEP_FINE` quand le prix que la
	 * grille écrit à côté du nom dépasse strictement `KILO_PRICE_FINE`, `KILO_STEP` sinon, y compris
	 * sans prix connu. C'est le prix du panneau de l'espèce (`CompositionSpeciesLib::getWithPrices()`),
	 * celui de la cascade à sa semaine de référence (`getReferenceWeek()`) : la structure lit le pas
	 * à côté du prix qui le décide.
	 *
	 * Le pas se pose sur `units`, là où `getStep()` lit le pas de l'unité : un pas réglé par la
	 * structure passe toujours devant, et une espèce à la pièce, à la botte ou dans une autre unité
	 * décimale garde le pas de son unité (`getUnitStep()`). Le minimum par défaut, celui de la
	 * communauté, s'arrondit à ce pas dans `getStep()`. Les
	 * lignes déjà écrites ne changent pas : le moins et le plus d'une ligne hors de ce pas la
	 * ramènent au pas suivant (`CompositionWeekLib::applySteps()`).
	 */
	private static function applyKiloSteps(CompositionPlan $eCompositionPlan, array $source, array $prices, array $windows): array {

		$weeksDelivered = $eCompositionPlan['weeks'];
		sort($weeksDelivered);

		foreach($source['units'] as $speciesId => $unit) {

			if($unit['fqn'] !== 'kg') {
				continue;
			}

			$week = self::getReferenceWeek(self::getWeeksDeliveredInSeason($weeksDelivered, $windows[$speciesId] ?? []), $eCompositionPlan['season']);
			$unitPrice = ($week === NULL) ? NULL : self::getUnitPrice($week, $speciesId, $source, $prices);

			$source['units'][$speciesId]['step'] = ($unitPrice !== NULL and $unitPrice > self::KILO_PRICE_FINE) ? self::KILO_STEP_FINE : self::KILO_STEP;

		}

		return $source;

	}

	/**
	 * Les semaines livrées `$weeksDelivered` (triées) qui sont dans la saison `$season` d'une espèce,
	 * toutes quand aucune ne l'est : ce sur quoi `getReferenceWeek()` choisit la semaine qui chiffre
	 * l'espèce. Une espèce retirée ou dont la saison ne croise aucune semaine livrée prend donc la
	 * première semaine livrée qui n'est pas passée.
	 */
	public static function getWeeksDeliveredInSeason(array $weeksDelivered, array $season): array {

		$weeks = array_values(array_intersect($weeksDelivered, $season));

		return ($weeks === []) ? $weeksDelivered : $weeks;

	}

	/**
	 * La semaine où se lit le prix d'une espèce de fenêtre `$window` (lot M) : la première qui n'est
	 * pas encore passée, ou la première de la fenêtre quand toutes le sont. `NULL` pour une fenêtre
	 * vide, celle d'un plan qui ne livre encore aucune semaine.
	 */
	public static function getReferenceWeek(array $window, int $season): ?int {

		if($window === []) {
			return NULL;
		}

		$weekCurrent = \Clock::week();

		foreach($window as $week) {
			if($season.'-W'.sprintf('%02d', $week) >= $weekCurrent) {
				return $week;
			}
		}

		return $window[0];

	}

	/**
	 * Les unités que la structure a choisies pour les espèces du plan (lot r14,
	 * `CompositionSpecies.unit`) remplacent celles de la communauté dans `units`, sur la copie de la
	 * source que le plan reçoit : la source gardée par `getSource()` est un tableau, copié à
	 * l'affectation, et les autres plans n'en voient rien. Tout ce qui chiffre une espèce lit son
	 * unité dans `units` (la quantité, le pas, la cascade des prix de la structure, le poids) et suit
	 * donc l'unité choisie sans autre changement.
	 *
	 * Chaque espèce réglée porte `kilos`, ce que pèse une unité choisie, et garde sous `community`
	 * l'unité de la communauté, ce qu'elle pèse (`kilos`) et `ratio`, ce que pèse
	 * une unité choisie rapportée à ce que pèse une unité de la communauté : 0,05 pour l'ail passé
	 * du kilo à la pièce de 0,05 kg. Le poids d'une unité est celui de la cascade de
	 * `getWeightReference()` : le poids tapé sur l'espèce, qui se dit dans l'unité choisie, à défaut
	 * celui des cultures de la saison, à défaut le poids courant ; le kilo pèse 1. `ratio` est nul
	 * quand l'un des deux poids manque.
	 *
	 * - La quantité est la médiane de la communauté dans l'unité choisie quand elle passe le seuil
	 *   (`quantities`), sinon celle de l'unité de la communauté convertie par le poids : 0,1 kg d'ail
	 *   devient 2 pièces. Sans médiane ni poids, elle est nulle, et l'espèce n'entre pas au panier
	 *   (`getOrder()`), comme une espèce sans prix.
	 * - Le prix passe par la cascade de la structure dans l'unité choisie, puis par ses prix dans
	 *   l'unité de la communauté convertis par `ratio` (`getPrices()`, `PRICE_CONVERTED`), puis par
	 *   la médiane nationale dans l'unité choisie, puis par celle de l'unité de la communauté
	 *   convertie (`getUnitPrice()`).
	 *
	 * Le minimum de la communauté (`minimum`, lot r25) ne suit pas : il se dit dans l'unité de la
	 * communauté, et un minimum converti par le poids ne serait plus une quantité qu'on met au panier
	 * (0,5 kg de poireau ne fait pas 2,7 pièces). L'espèce retombe sur un minimum d'un pas
	 * (`getStep()`).
	 *
	 * Une espèce qui n'a plus d'unité dans la communauté (le relevé de la nuit l'a fait passer sous
	 * le seuil) ne se propose plus, quelle que soit l'unité choisie : sans unité de la communauté, il
	 * n'y a ni quantité à convertir ni fenêtre sûre.
	 *
	 * Une espèce que la communauté ne sait pas compter (r48c) entre dans `units` avec ce que la
	 * structure lui a donné en l'ajoutant, `applyUnitsTyped()`.
	 */
	private static function applyUnits(CompositionPlan $eCompositionPlan, array $source, array $units, ?array $cultivations): array {

		if($units === []) {
			return $source;
		}

		$cultivated = self::projectByPlants(self::getCultivatedWeights($eCompositionPlan['structure'], $eCompositionPlan['season'], $cultivations), $source['plants']);

		// Les espèces de la communauté se lisent avant que celles qu'elle ne compte pas n'entrent dans
		// `units` : elles n'ont pas d'unité de la communauté à garder.
		$unitsCommunity = array_intersect_key($units, $source['units']);

		$source = self::applyUnitsTyped($source, array_diff_key($units, $source['units']), $cultivated);

		foreach($unitsCommunity as $speciesId => ['unit' => $unitId, 'weight' => $weight]) {

			$community = $source['units'][$speciesId];
			$offered = $source['offered'][$unitId];

			$kilosChosen = self::getKilos($speciesId, $offered['fqn'], $weight, $source, $cultivated);
			$kilosCommunity = self::getKilos($speciesId, $community['fqn'], NULL, $source, $cultivated);

			$ratio = ($kilosChosen === NULL or $kilosCommunity === NULL) ? NULL : $kilosChosen / $kilosCommunity;

			if(isset($source['quantities'][$speciesId][$unitId])) {
				$quantity = $source['quantities'][$speciesId][$unitId]['quantity'];
			} else if($ratio !== NULL) {
				$quantity = $community['quantity'] / $ratio;
			} else {
				$quantity = NULL;
			}

			$source['units'][$speciesId] = [
				'unit' => $unitId,
				'quantity' => $quantity,
				'minimum' => NULL,
				'fqn' => $offered['fqn'],
				'step' => $offered['step'],
				'kilos' => $kilosChosen,
				'community' => [
					'unit' => $community['unit'],
					'fqn' => $community['fqn'],
					'kilos' => $kilosCommunity,
					'ratio' => $ratio,
				],
			];

		}

		return $source;

	}

	/**
	 * Les espèces du plan que la communauté ne sait pas compter (r48c), ajoutées par « Ajouter une
	 * espèce » avec leur unité et leur quantité au panier (`CompositionSpecies.quantityBasket`) :
	 * chacune entre dans `units` comme une espèce de la communauté, sa quantité étant celle que la
	 * structure a tapée, sans minimum ni unité de la communauté (`community` nul, donc aucun prix
	 * converti). Tout ce qui chiffre une espèce la lit alors sans autre changement, et son prix tapé
	 * la rend candidate sur les semaines de sa saison (`getPrices()`, `priced`, `getOrder()`).
	 *
	 * La source de la communauté ne connait de ces espèces ni l'organe, ni le chou, ni le poids
	 * courant, qu'elle ne lit que sur celles des paniers partagés (`readSource()`) : ils se posent
	 * ici, lus sur le référentiel. Une espèce sans organe compte hors de l'équilibre du panier, et
	 * se range sous « Autres » dans la grille.
	 *
	 * Une espèce sans quantité au panier n'entre pas : c'est une espèce de la communauté qui en est
	 * sortie, que `applyUnits()` laisse hors des paniers.
	 *
	 * Sa part dans les paniers est celle que la communauté lui mesure à l'année quand elle en a une
	 * (`frequency`, 37 des 49 espèces du calendrier hors communauté le 02/10/2026), sinon nulle :
	 * elle n'entre alors que par le bruit du tirage (`getOrderByDebt()`), tant que la structure ne
	 * lui choisit pas de fréquence.
	 */
	private static function applyUnitsTyped(array $source, array $units, array $cultivated): array {

		$units = array_filter($units, fn(array $unit) => $unit['quantityBasket'] !== NULL);

		if($units === []) {
			return $source;
		}

		$cPlant = \plant\Plant::model()
			->select(['id', 'name', 'organ', 'fqn'])
			->whereId('IN', array_intersect_key($source['plants'], $units))
			->getCollection(index: 'id');

		foreach($units as $speciesId => ['unit' => $unitId, 'weight' => $weight, 'quantityBasket' => $quantityBasket]) {

			$ePlant = $cPlant[self::getPlantId($speciesId, $source)];
			$offered = $source['offered'][$unitId];

			$source['organs'][$speciesId] = $ePlant['organ'];

			if(mb_stripos($ePlant['name'], self::CABBAGE) !== FALSE) {
				$source['cabbages'][$speciesId] = TRUE;
			}

			if(isset(ToolboxSetting::COMPOSITION_WEIGHTS[$ePlant['fqn']])) {
				$source['weights'][$speciesId] = ToolboxSetting::COMPOSITION_WEIGHTS[$ePlant['fqn']];
			}

			$source['units'][$speciesId] = [
				'unit' => $unitId,
				'quantity' => $quantityBasket,
				'minimum' => NULL,
				'fqn' => $offered['fqn'],
				'step' => $offered['step'],
				'kilos' => self::getKilos($speciesId, $offered['fqn'], $weight, $source, $cultivated),
				'community' => NULL,
			];

		}

		return $source;

	}

	/**
	 * Ce que pèse une unité `$unitFqn` de l'espèce, en kilos, dans la cascade de
	 * `getWeightReference()` : 1 pour le kilo, sinon `$weight`, le poids tapé, à défaut celui des
	 * cultures, à défaut le poids courant, NULL quand aucun ne le dit.
	 */
	private static function getKilos(int $speciesId, string $unitFqn, ?float $weight, array $source, array $cultivated): ?float {

		if($unitFqn === 'kg') {
			return 1.0;
		}

		return $weight ?? $cultivated[$speciesId][$unitFqn] ?? $source['weights'][$speciesId][$unitFqn] ?? NULL;

	}

	private static function readSource(): array {

		// La version ne suit que les données : le numéro qui la précède suit la forme de ce qui est
		// gardé, et change quand une lecture rend une clé de plus (`minimum`, lot r25), sans quoi la
		// forme d'avant resterait servie jusqu'à la publication suivante de l'agrégé.
		$aggregate = \Cache::redis()->query('toolbox-composition-source-2-'.\aggregate\CompositionSourceAiLib::getVersion(), fn() => [
			'quantities' => \aggregate\CompositionSourceAiLib::getQuantityByPlantUnit(),
			'availability' => \aggregate\CompositionSourceAiLib::getAvailabilityByWeek(),
			'prices' => \aggregate\CompositionSourceAiLib::getPriceByWeek(),
			'frequency' => \aggregate\CompositionSourceAiLib::getFrequency(),
		], 86400);

		$quantities = $aggregate['quantities'];

		$units = [];

		foreach($quantities as $plantId => $quantityByUnit) {

			$unitBest = NULL;

			foreach($quantityByUnit as $unitId => $quantity) {

				if(
					$unitBest === NULL or
					$quantity['contributors'] > $quantityByUnit[$unitBest]['contributors'] or
					($quantity['contributors'] === $quantityByUnit[$unitBest]['contributors'] and $unitId < $unitBest)
				) {
					$unitBest = $unitId;
				}

			}

			$units[$plantId] = [
				'unit' => $unitBest,
				'quantity' => $quantityByUnit[$unitBest]['quantity'],
				'minimum' => $quantityByUnit[$unitBest]['minimum'],
				'community' => NULL,
			];

		}

		// Les unités de l'agrégé et celles qu'une structure peut choisir (lot r14), du référentiel partagé
		// et que le poids sait convertir (`WEIGHED`, et le kilo), se lisent ensemble, comme les espèces
		// de la disponibilité et celles qui ont une unité : ce qui suit l'agrégé est gardé en cache, pas
		// ceci, qui se relit à chaque requête des paniers (chantier Performances, P5).
		$unitIds = array_column($units, 'unit');
		$fqnsOffered = array_merge(['kg'], array_keys(self::WEIGHED));

		// `or()` lie ses fermetures au modèle : `self` y désignerait `UnitModel`.
		$cUnit = \product\Unit::model()
			->select(['id', 'fqn', 'type', 'structure'])
			->or(
				fn() => $this->whereId('IN', $unitIds),
				fn() => $this->whereStructure(NULL)->whereFqn('IN', $fqnsOffered),
			)
			->sort(['id' => SORT_ASC])
			->getCollection(index: 'id');

		foreach($units as $plantId => $unit) {
			$units[$plantId]['fqn'] = $cUnit[$unit['unit']]['fqn'];
			$units[$plantId]['step'] = self::getUnitStep($cUnit[$unit['unit']]['type']);
		}

		$availability = $aggregate['availability'];

		$plantIds = [];

		foreach($availability as $structuresByPlant) {
			$plantIds += array_fill_keys(array_keys($structuresByPlant), TRUE);
		}

		$cPlant = \plant\Plant::model()
			->select(['id', 'name', 'organ', 'fqn'])
			->whereId('IN', array_keys($plantIds + $units))
			->sort(['id' => SORT_ASC])
			->getCollection(index: 'id');

		$organs = [];
		$cabbages = [];

		// Une espèce qui a une unité entre au tirage dès qu'une saison la date, même quand l'agrégé ne
		// la dit disponible dans aucune semaine, et `draw()` lit alors son organe.
		foreach($cPlant as $ePlant) {

			$organs[$ePlant['id']] = $ePlant['organ'];

			if(mb_stripos($ePlant['name'], self::CABBAGE) !== FALSE) {
				$cabbages[$ePlant['id']] = TRUE;
			}

		}

		// Le poids courant se lit sur les espèces qui ont une unité (`units`), la seule chose que
		// `getWeightReference()` interroge : une espèce peut en avoir une sans être disponible
		// dans aucune semaine de l'agrégé.
		$weights = [];

		foreach($cPlant->find(fn(\plant\Plant $ePlant) => isset($units[$ePlant['id']]) and isset(ToolboxSetting::COMPOSITION_WEIGHTS[$ePlant['fqn']])) as $ePlant) {
			$weights[$ePlant['id']] = ToolboxSetting::COMPOSITION_WEIGHTS[$ePlant['fqn']];
		}

		$offered = [];

		foreach($cUnit->find(fn(\product\Unit $eUnit) => $eUnit['structure']->empty() and in_array($eUnit['fqn'], $fqnsOffered, TRUE)) as $eUnit) {

			$offered[$eUnit['id']] = [
				'unit' => $eUnit['id'],
				'fqn' => $eUnit['fqn'],
				'step' => self::getUnitStep($eUnit['type']),
			];

		}

		// Rangées par `fqn` : un ordre écrit, que la lecture ne tient d'aucun index.
		uasort($offered, fn(array $offered1, array $offered2) => strcmp($offered1['fqn'], $offered2['fqn']));

		return [
			'availability' => $availability,
			'seasons' => self::getSeasons(),
			'units' => $units,
			'quantities' => $quantities,
			'offered' => $offered,
			'prices' => $aggregate['prices'],
			'frequency' => $aggregate['frequency'],
			'organs' => $organs,
			'cabbages' => $cabbages,
			'weights' => $weights,
			'priced' => [],
			'plants' => [],
			'speciesBare' => [],
		];

	}

	/**
	 * Le pas d'arrondi d'une unité, selon son type : une unité entière, la botte ou la pièce, ne se
	 * coupe pas, et son arrondi est à l'unité.
	 */
	public static function getUnitStep(string $type): float {

		return match($type) {
			\product\Unit::INTEGER => 1.0,
			\product\Unit::DECIMAL => 0.1,
		};

	}

	/**
	 * Les prix de la structure, ceux qui passent devant la médiane de la communauté dans la règle
	 * comme dans la valeur des paniers : `[id d'espèce => ['from' => PRICE_*, 'weeks' => [semaine =>
	 * prix]]]`, chaque semaine de la saison chiffrée. Une espèce absente n'a aucun prix de la
	 * structure et prend la médiane de la semaine.
	 *
	 * La cascade, dans l'ordre (cadrage des paniers, découpage v12, lots H et M) :
	 *
	 * 0. le prix du produit lié à l'espèce (r39b, `CompositionSaleLib::getRetained()`), tant que ce
	 *    produit porte l'espèce dans l'unité du panier et a un prix sur le canal où il se vend ;
	 * 1. le prix tapé sur l'espèce du plan (`CompositionSpecies.price`), déjà dans l'unité du panier ;
	 * 2. le prix déclaré dans la mercuriale (`quotation\Suggestion`, au particulier), semaine par
	 *    semaine : la déclaration dont la période couvre le jeudi de la semaine, et à défaut la plus
	 *    récente ;
	 * 3. le prix du catalogue rattaché à la mercuriale (`quotation\Product.catalogPricePrivate`), que
	 *    le schéma donne pour le prix déclaré quand la structure n'a rien saisi ;
	 * 4. le prévisionnel financier de la saison (`plant\Forecast.privatePrice`) ;
	 * 5. le prix de ses propres ventes aux particuliers sur douze mois (`getSales()`), qui vient après
	 *    tout prix que la structure a posé elle-même, et avant la médiane de la communauté.
	 *
	 * **Une source de la structure ne compte que dans l'unité où l'espèce entre au panier** (`units`,
	 * l'unité de la communauté ou celle que la structure a choisie). Rien ne se convertit : une
	 * mercuriale au kilo pour un panier à la botte laisse la main à la source suivante. Seule
	 * exception, une espèce dont la structure a choisi l'unité (lot r14) : ses prix dans l'unité de la
	 * communauté viennent après toute la cascade dans l'unité choisie, convertis par le poids
	 * (`PRICE_CONVERTED`, `applyUnits()`), puisque c'est l'unité où la structure les a posés avant de
	 * changer. La mercuriale compare l'unité de sa cotation, une unité du
	 * référentiel comme celle du panier ; le prévisionnel n'a que trois unités, et `UNIT` y vaut
	 * pour la pièce comme pour l'unité.
	 *
	 * Une espèce porte souvent plusieurs cotations dans la même unité (« Courge butternut »,
	 * « Courge potimarron ») : leurs prix se moyennent, comme `quotation\ReferenceLib` moyenne les
	 * produits d'une structure sur une cotation. Les cotations des plants (`Sector::NURSERY`) sont
	 * écartées : « Plant de laitue » porte l'espèce Laitue et la pièce, au prix d'un plant et non
	 * d'une salade.
	 *
	 * Les sources de la structure se lisent une fois (`readPriceSources()`), bornées par la structure,
	 * et servent aux deux cascades : celle de l'unité du panier et celle de l'unité de la communauté,
	 * qui ne diffèrent que par les unités où elles comptent un prix (chantier Performances, P17 : la
	 * seconde cascade relisait les huit mêmes requêtes). Le reste n'est pas gardé d'un appel à l'autre :
	 * un prix tapé ou déclaré doit se voir au geste suivant. Les prix tapés se lisent sur
	 * `$cCompositionSpecies`, les espèces du plan, quand l'appelant les a déjà (P7).
	 */
	public static function getPrices(CompositionPlan $eCompositionPlan, array $source, ?\Collection $cCompositionSpecies = NULL): array {

		$eCompositionPlan->expects(['id', 'structure', 'season']);

		$cCompositionSpecies ??= CompositionSpecies::model()
			->select(['id', 'plant', 'price', 'product'])
			->wherePlan($eCompositionPlan)
			->getCollection();

		$prices = [];

		foreach(CompositionSaleLib::getRetained($eCompositionPlan['structure'], $cCompositionSpecies) as $speciesId => $retained) {

			if(
				$retained['price'] === NULL or
				$retained['unit'] !== $source['units'][$speciesId]['fqn']
			) {
				continue;
			}

			$prices[$speciesId] = [
				'from' => self::PRICE_PRODUCT,
				'weeks' => array_fill_keys(self::getSeasonWeeks(), $retained['price']),
			];

		}

		foreach($cCompositionSpecies->find(fn(CompositionSpecies $e) => $e['price'] !== NULL) as $eCompositionSpecies) {

			if(array_key_exists($eCompositionSpecies['id'], $prices)) {
				continue;
			}

			$prices[$eCompositionSpecies['id']] = [
				'from' => self::PRICE_TYPED,
				'weeks' => array_fill_keys(self::getSeasonWeeks(), (float)$eCompositionSpecies['price']),
			];

		}

		$sources = self::readPriceSources($eCompositionPlan['structure'], $eCompositionPlan['season']);

		$prices += self::getPricesByUnits($sources, $eCompositionPlan['season'], $source['units'], $source);

		// Une espèce dont la structure a choisi l'unité (lot r14) et qu'elle ne chiffre pas dans cette
		// unité prend le prix qu'elle lui donne dans celle de la communauté, converti par le poids : le
		// prix de la structure passe devant la médiane, quelle que soit l'unité où elle l'a posé. Le
		// prix d'origine reste sous `community`, que le formulaire du prix écrit.
		$unitsCommunity = [];

		foreach($source['units'] as $speciesId => $unit) {

			if($unit['community'] !== NULL and $unit['community']['ratio'] !== NULL) {
				$unitsCommunity[$speciesId] = $unit['community'];
			}

		}

		if($unitsCommunity !== []) {

			foreach(self::getPricesByUnits($sources, $eCompositionPlan['season'], $unitsCommunity, $source) as $speciesId => $price) {

				if(array_key_exists($speciesId, $prices)) {
					continue;
				}

				$ratio = $unitsCommunity[$speciesId]['ratio'];

				$prices[$speciesId] = [
					'from' => self::PRICE_CONVERTED,
					'weeks' => array_map(fn(float $unitPrice) => round($unitPrice * $ratio, 2), $price['weeks']),
					'community' => $price,
				];

			}

		}

		ksort($prices);

		return $prices;

	}

	/**
	 * Les sources de la cascade de `getPrices()` après le prix tapé, toutes unités confondues : les
	 * cotations de la mercuriale où la structure a déclaré un prix ou rattaché un produit du catalogue
	 * (`quotation\ToolboxObserverLib`), les prix du prévisionnel de la saison, et les ventes. Aucune ne
	 * dépend des unités du panier, que `getPricesByUnits()` applique ensuite. Sans la mercuriale ni les
	 * ventes, la cascade passe du prix tapé au prévisionnel.
	 */
	private static function readPriceSources(\structure\Structure $eStructure, int $season): array {

		return [
			'quotations' => \structure\StructureSetting::hasCommercialisationApp() ? self::notify('getCompositionQuotations', $eStructure)[0] : [],
			// Le prévisionnel porte l'espèce de la structure, rattachée à l'espèce partagée par
			// `reference` : c'est cette dernière que la communauté et la mercuriale désignent.
			'cForecast' => \plant\Forecast::model()
				->select(['plant' => ['reference'], 'unit', 'privatePrice'])
				->whereStructure($eStructure)
				->whereSeason($season)
				->wherePrivatePrice('!=', NULL)
				->getCollection(),
			'sales' => self::getSales($eStructure),
		];

	}

	/**
	 * La cascade de `getPrices()` après le prix tapé, sur les sources lues par `readPriceSources()`,
	 * dans les unités `$units` (`[id servi => ['unit' => id d'unité, 'fqn' => fqn de l'unité]]`) :
	 * le prix déclaré, puis celui du catalogue, puis celui du prévisionnel, puis celui des ventes.
	 *
	 * Les sources de la structure désignent une espèce lue, et chaque espèce du plan qui la lit
	 * (`getPlantId()`) en prend le prix dans sa propre unité : une variante au kilo de la carotte en
	 * botte lit la mercuriale au kilo de la carotte (r76).
	 */
	private static function getPricesByUnits(array $sources, int $season, array $units, array $source): array {

		$speciesByPlant = [];

		foreach(array_keys($units) as $speciesId) {
			$speciesByPlant[self::getPlantId($speciesId, $source)][] = $speciesId;
		}

		$declared = [];
		$catalog = [];

		foreach($sources['quotations'] as $quotationId => $quotation) {

			$plantId = $quotation['plant']['id'];

			if(array_key_exists($plantId, $speciesByPlant) === FALSE) {
				continue;
			}

			// Les espèces servies qui lisent l'espèce de la cotation, dans son unité.
			foreach($speciesByPlant[$plantId] as $speciesId) {

				if($quotation['unit']['id'] !== $units[$speciesId]['unit']) {
					continue;
				}

				if($quotation['suggestions'] !== []) {
					$declared[$speciesId][$quotationId] = $quotation['suggestions'];
				}

				if($quotation['catalog'] !== []) {
					$catalog[$speciesId] = array_merge($catalog[$speciesId] ?? [], $quotation['catalog']);
				}

			}

		}

		$forecast = [];

		foreach($sources['cForecast'] as $eForecast) {

			$plantId = $eForecast['plant']['reference']->notEmpty() ? $eForecast['plant']['reference']['id'] : $eForecast['plant']['id'];

			if(array_key_exists($plantId, $speciesByPlant) === FALSE) {
				continue;
			}

			$fqns = match($eForecast['unit']) {
				\plant\Forecast::KG => ['kg'],
				\plant\Forecast::BUNCH => ['bunch'],
				\plant\Forecast::UNIT => ['one', 'unit'],
			};

			foreach($speciesByPlant[$plantId] as $speciesId) {

				if(in_array($units[$speciesId]['fqn'], $fqns, TRUE)) {
					$forecast[$speciesId][] = (float)$eForecast['privatePrice'];
				}

			}

		}

		$weeks = self::getSeasonWeeks();
		$prices = [];

		// Le jeudi de chaque semaine, où se lit le prix déclaré, ne dépend que de la semaine : calculé
		// par espèce, il coûtait 52 dates par espèce déclarée (chantier Performances, P12).
		$date = \Clock::now();
		$thursdays = [];

		foreach($weeks as $week) {
			$thursdays[$week] = $date->setISODate($season, $week, 4)->format('Y-m-d');
		}

		foreach($declared as $speciesId => $suggestionsByQuotation) {

			if(array_key_exists($speciesId, $prices)) {
				continue;
			}

			$prices[$speciesId] = [
				'from' => self::PRICE_DECLARED,
				'weeks' => [],
			];

			foreach($thursdays as $week => $thursday) {

				$values = [];

				foreach($suggestionsByQuotation as $suggestions) {
					$values[] = self::getSuggestionForDate($suggestions, $thursday)['price'];
				}

				$prices[$speciesId]['weeks'][$week] = round(array_sum($values) / count($values), 2);

			}

		}

		foreach([self::PRICE_CATALOG => $catalog, self::PRICE_FORECAST => $forecast] as $from => $values) {

			foreach($values as $speciesId => $speciesValues) {

				if(array_key_exists($speciesId, $prices)) {
					continue;
				}

				$prices[$speciesId] = [
					'from' => $from,
					'weeks' => array_fill_keys($weeks, round(array_sum($speciesValues) / count($speciesValues), 2)),
				];

			}

		}

		foreach($sources['sales'] as $plantId => $sales) {

			if(array_key_exists($plantId, $speciesByPlant) === FALSE) {
				continue;
			}

			foreach($speciesByPlant[$plantId] as $speciesId) {

				if(
					array_key_exists($speciesId, $prices) or
					array_key_exists($units[$speciesId]['fqn'], $sales) === FALSE
				) {
					continue;
				}

				$prices[$speciesId] = [
					'from' => self::PRICE_SALES,
					'weeks' => array_fill_keys($weeks, $sales[$units[$speciesId]['fqn']]),
				];

			}

		}

		return $prices;

	}

	/**
	 * Les espèces de référence que la structure a vendues sur douze mois, avec leur prix au
	 * particulier par unité : `[id d'espèce => [fqn de l'unité => prix]]`, lues par selling
	 * (`selling\ToolboxObserverLib`). Sans l'application de commercialisation, le tableau est vide.
	 *
	 * La lecture coûte de 20 à 700 ms selon la structure, et `getPrices()` tourne à chaque geste sur
	 * la grille : elle se garde un jour. Une vente du jour paraît le lendemain, ce qui ne se voit pas
	 * sur douze mois.
	 */
	public static function getSales(\structure\Structure $eStructure): array {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return [];
		}

		return self::$sales[$eStructure['id']] ??= \Cache::redis()->query('toolbox-composition-sales-'.$eStructure['id'].'-'.\Clock::date(), fn() => self::notify('getCompositionSales', $eStructure)[0], 86400);

	}

	/**
	 * La déclaration d'une cotation qui vaut pour la date `$date` (`Y-m-d`) : celle dont la période
	 * la couvre, la plus récemment ouverte si plusieurs se chevauchent ; à défaut, celle qui finit le
	 * plus tard. Le dernier départage par `id` rend le choix indépendant de l'ordre de la lecture.
	 */
	private static function getSuggestionForDate(array $suggestions, string $date): array {

		$covering = array_filter($suggestions, fn(array $suggestion) => ($suggestion['startsAt'] <= $date and $suggestion['endsAt'] >= $date));

		if($covering !== []) {
			usort($covering, fn(array $a, array $b) => [$b['startsAt'], $b['id']] <=> [$a['startsAt'], $a['id']]);
			return $covering[0];
		}

		usort($suggestions, fn(array $a, array $b) => [$b['endsAt'], $b['startsAt'], $b['id']] <=> [$a['endsAt'], $a['startsAt'], $a['id']]);

		return $suggestions[0];

	}

	/**
	 * Les semaines d'une saison, toutes celles que `CompositionWeek.week` admet : 52, quelle que soit
	 * l'année. Une année ISO longue perd sa semaine 53, du 28 décembre au 3 janvier, que les
	 * projections de l'agrégé replient déjà sur la 52.
	 */
	public static function getSeasonWeeks(): array {

		[$minimum, $maximum] = CompositionWeek::model()->getPropertyRange('week');

		return range($minimum, $maximum);

	}

	/**
	 * Les mois où une espèce est de saison, selon la mercuriale (`quotation\ToolboxObserverLib`) :
	 * `[id d'espèce => [mois]]`, où l'absence veut dire « jamais hors saison ». Sans la mercuriale,
	 * aucune espèce n'est hors saison.
	 */
	private static function getSeasons(): array {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return [];
		}

		return self::notify('getCompositionSeasons')[0];

	}

	/**
	 * Le mois d'une semaine ISO, lu sur son jeudi.
	 *
	 * Le jeudi est le jour qui décide à quelle année ISO une semaine appartient, et c'est le seul
	 * choix qui ne rattache jamais une semaine à un mois où elle n'a que un ou deux jours.
	 * ⚠️ `series\CultivationLib::updateHarvestPeriodExpected()` lit le lundi : les deux lectures ne
	 * donnent pas le même mois pour une semaine à cheval, et ce n'est pas la même question.
	 */
	public static function getMonth(int $season, int $week): int {

		$date = \Clock::now();
		$date->setISODate($season, $week, 4);

		return (int)$date->format('n');

	}

	/**
	 * Les espèces qu'un plan peut proposer sur ses semaines : celles qui passent les étapes 1 et 2
	 * au moins une semaine, avec une unité et un prix. Sur toutes les semaines de la saison, ce sont
	 * les espèces que le départ montre et écrit en `CompositionSpecies`
	 * (`CompositionPlanLib::getForStart()`). Triées par id.
	 */
	public static function getProposable(array $weeks, int $season, array $source): array {

		$plantIds = array_keys(self::getNaturalWeeks($weeks, $season, $source));
		sort($plantIds);

		return $plantIds;

	}

	/**
	 * La fenêtre naturelle de chaque espèce, comptée sur les semaines `$weeks` :
	 * `[id d'espèce => nombre de semaines]`, où l'espèce passe les étapes 1 et 2 avec une unité et un
	 * prix. Une espèce qui n'en passe aucune n'apparaît pas.
	 *
	 * C'est ce que le panneau « Espèce par espèce » écrit sur chaque rang. Elle se compte sans
	 * exclusion ni fenêtre réglée à la main, puisqu'elle dit ce que l'espèce apporterait si on la
	 * gardait telle que la communauté la récolte.
	 */
	public static function getNaturalWeeks(array $weeks, int $season, array $source): array {
		return array_map('count', self::getNaturalWindows($weeks, $season, $source));
	}

	/**
	 * La fenêtre naturelle de chaque espèce sur les semaines `$weeks`, semaine par semaine :
	 * `[id servi => [semaine, …]]`, dans l'ordre des semaines, l'id servi étant celui de l'espèce du
	 * plan sur la source d'un plan (`getOrder()`). C'est ce que compte
	 * `getNaturalWeeks()`, et ce que le départ recompte à chaque semaine cochée sans rien demander au
	 * serveur.
	 */
	public static function getNaturalWindows(array $weeks, int $season, array $source): array {

		$natural = [];

		foreach($weeks as $week) {

			foreach(self::getOrder($week, $season, NULL, $source) as $speciesId) {
				$natural[$speciesId][] = $week;
			}

		}

		return $natural;

	}

	/**
	 * Les espèces des plans de culture de la structure, saison par saison, les plus récentes d'abord,
	 * par leur id dans le référentiel partagé : c'est sous cet id que les paniers partagés les
	 * connaissent (`getSource()`), et une espèce propre à la structure y renvoie par `reference`. Une
	 * saison sans culture n'y figure pas.
	 *
	 * Toutes les saisons se lisent d'une requête : le départ les propose dans un menu, et chaque
	 * choix recoche les espèces dans le navigateur, sans aller-retour (revue v12).
	 */
	public static function getCultivatedBySeason(\structure\Structure $eStructure): array {

		$cCultivation = \series\Cultivation::model()
			->select([
				'season',
				'plant' => ['reference']
			])
			->whereStructure($eStructure)
			->group(['season', 'plant'])
			->sort(['season' => SORT_DESC])
			->getCollection();

		$cultivated = [];

		foreach($cCultivation as $eCultivation) {

			$ePlant = $eCultivation['plant'];
			$plantId = $ePlant['reference']->notEmpty() ? $ePlant['reference']['id'] : $ePlant['id'];

			$cultivated[$eCultivation['season']][$plantId] = $plantId;

		}

		return array_map(fn(array $plantIds) => array_values($plantIds), $cultivated);

	}

	/**
	 * L'espèce a-t-elle de quoi entrer au panier : une unité, que la communauté lui connaît, et une
	 * quantité dans cette unité ? Une espèce passée dans une unité où aucun poids ne convertit sa
	 * quantité (lot r14, `applyUnits()`) n'en a pas, et ne se propose ni ne s'ajoute.
	 */
	public static function isSold(int $speciesId, array $source): bool {

		return (
			array_key_exists($speciesId, $source['units']) and
			$source['units'][$speciesId]['quantity'] !== NULL
		);

	}

	/**
	 * L'espèce entre-t-elle au panier dans une unité qui a un poids à dire, la pièce ou la botte
	 * (`WEIGHED`) ?
	 */
	public static function isWeighed(int $speciesId, array $source): bool {
		return in_array($source['units'][$speciesId]['fqn'], array_keys(self::WEIGHED), TRUE);
	}

	/**
	 * Les poids que la structure a posés sur ses cultures de la saison, `[id d'espèce => [fqn d'unité
	 * => poids]]`, l'espèce par son id dans le référentiel partagé comme dans
	 * `getCultivatedBySeason()`. Plusieurs cultures de la même espèce peuvent porter des poids
	 * différents, une par série : l'espèce prend leur médiane, que deux séries pesées l'une à 0,5 kg,
	 * l'autre à 0,7 kg rendent à 0,6 kg.
	 *
	 * `float32` rend 0,1 kg en 0,100000001 : le poids s'arrondit au gramme, la précision de
	 * `CompositionSpecies.weight`.
	 *
	 * `$cultivations` sont les cultures de la structure (`getCultivations()`), que l'écran lit une
	 * fois (chantier Performances, P8). Seules celles de la saison `$season` pèsent. Sans elles, seules
	 * les cultures pesées de la saison se lisent (`getCultivationsWeighed()`) : le menu d'une case
	 * passe par ici à chaque ouverture, et relire tout le plan de culture pour quelques poids lui
	 * coûtait 8 à 14 ms sur la 7.
	 */
	public static function getCultivatedWeights(\structure\Structure $eStructure, int $season, ?array $cultivations = NULL): array {

		$cultivations ??= self::getCultivationsWeighed($eStructure, $season);

		$weights = [];

		foreach($cultivations as $cultivation) {

			if($cultivation['season'] !== $season) {
				continue;
			}

			foreach(self::WEIGHED as $fqn => $property) {

				if($cultivation[$property] !== NULL) {
					$weights[$cultivation['plant']][$fqn][] = round($cultivation[$property], 3);
				}

			}

		}

		return array_map(fn(array $weightsByUnit) => array_map(fn(array $values) => self::getMedianWeight($values), $weightsByUnit), $weights);

	}

	/**
	 * Les cultures pesées de la saison `$season`, dans la forme de `getCultivations()` réduite à ce que
	 * `getCultivatedWeights()` lit : une culture sans poids n'en apporte aucun.
	 */
	private static function getCultivationsWeighed(\structure\Structure $eStructure, int $season): array {

		$cCultivation = \series\Cultivation::model()
			->join(\plant\Plant::model(), 'm1.plant = m2.id')
			->select([
				'season' => new \Sql('m1.season', 'int'),
				'unitWeight', 'bunchWeight',
				'plantShared' => new \Sql('COALESCE(m2.reference, m2.id)', 'int'),
			])
			->where('m1.structure', $eStructure)
			->where('m1.season', $season)
			->where('m1.unitWeight IS NOT NULL OR m1.bunchWeight IS NOT NULL')
			->getCollection();

		$cultivations = [];

		foreach($cCultivation as $eCultivation) {
			$cultivations[] = [
				'season' => $eCultivation['season'],
				'plant' => $eCultivation['plantShared'],
				'unitWeight' => $eCultivation['unitWeight'],
				'bunchWeight' => $eCultivation['bunchWeight'],
			];
		}

		return $cultivations;

	}

	private static function getMedianWeight(array $values): float {

		sort($values);

		$count = count($values);
		$middle = intdiv($count, 2);

		return ($count % 2 === 1) ? $values[$middle] : round(($values[$middle - 1] + $values[$middle]) / 2, 3);

	}

	/**
	 * Le poids d'une unité du panier pour une espèce, et d'où il vient : `['weighed' => bool,
	 * 'weight' => ?float, 'from' => ?WEIGHT_*]`, `$weight` le poids tapé sur l'espèce du plan et
	 * `$cultivated` ce que rend `getCultivatedWeights()`. `weighed` dit si l'espèce a un poids à dire
	 * (`isWeighed()`) : une espèce servie au kilo n'en a pas, une botte que rien ne pèse en attend un,
	 * et toutes deux rendent un poids `NULL`.
	 */
	public static function getWeightReference(int $speciesId, ?float $weight, array $source, array $cultivated): array {

		if(self::isWeighed($speciesId, $source) === FALSE) {
			return ['weighed' => FALSE, 'weight' => NULL, 'from' => NULL];
		}

		return self::getWeightReferenceByUnit($speciesId, $source['units'][$speciesId]['fqn'], $weight, $source, $cultivated);

	}

	/**
	 * Le poids d'une unité pesée `$unitFqn`, la pièce ou la botte, quelle que soit celle où l'espèce
	 * entre au panier : le formulaire de l'unité (lot r14) dit celui de chaque unité qu'il propose,
	 * avant qu'elle soit choisie. Même forme et même cascade que `getWeightReference()`.
	 */
	public static function getWeightReferenceByUnit(int $speciesId, string $unitFqn, ?float $weight, array $source, array $cultivated): array {

		if($weight !== NULL) {
			return ['weighed' => TRUE, 'weight' => $weight, 'from' => self::WEIGHT_TYPED];
		}

		$weightCultivated = $cultivated[$speciesId][$unitFqn] ?? NULL;

		if($weightCultivated !== NULL) {
			return ['weighed' => TRUE, 'weight' => $weightCultivated, 'from' => self::WEIGHT_CULTIVATED];
		}

		$weightBase = $source['weights'][$speciesId][$unitFqn] ?? NULL;

		if($weightBase !== NULL) {
			return ['weighed' => TRUE, 'weight' => $weightBase, 'from' => self::WEIGHT_BASE];
		}

		return ['weighed' => TRUE, 'weight' => NULL, 'from' => NULL];

	}

	/**
	 * Ce que pèse une unité, en kilos, pour une espèce (lot L5) : 1 pour le kilo, le poids de la
	 * pièce ou de la botte sinon, NULL quand rien ne le dit. Dans l'unité où l'espèce entre au panier,
	 * c'est le poids que la Synthèse affiche (`weightReference`, `getWeightReference()`), tapé, tiré des
	 * cultures ou courant ; dans une autre unité, seul le poids courant de `ToolboxSetting` peut le
	 * dire. Une barquette ou un sachet n'ont de poids nulle part.
	 */
	public static function getUnitKilos(int $speciesId, string $unitFqn, array $source, \Collection $cCompositionSpecies): ?float {

		if($unitFqn === 'kg') {
			return 1.0;
		}

		if(array_key_exists($unitFqn, self::WEIGHED) === FALSE) {
			return NULL;
		}

		if(
			$unitFqn === $source['units'][$speciesId]['fqn'] and
			$cCompositionSpecies->offsetExists($speciesId)
		) {
			return $cCompositionSpecies[$speciesId]['weightReference']['weight'];
		}

		return $source['weights'][$speciesId][$unitFqn] ?? NULL;

	}

	/**
	 * La récolte que le plan de culture prévoit dans les semaines de la saison, espèce par espèce et
	 * semaine par semaine, en kilos (lot L5, plan vérifié) : `[id d'espèce => ['cultivations' => int,
	 * 'incomplete' => int, 'harvest' => [semaine => kilos], 'weeks' => [semaine => semaine],
	 * 'weeksIncomplete' => [semaine => semaine], 'carried' => [semaine => kilos],
	 * 'carriedIncomplete' => [semaine => semaine]]]`, l'espèce nue du plan qui lit l'espèce de la
	 * culture (`getSpeciesBare()`) : une variante n'a pas de cultures à elle, elle puise dans le stock
	 * de l'espèce qu'elle décline (`getStock()`).
	 *
	 * La récolte d'une culture est son rendement prévu par la surface de sa série
	 * (`series\Cultivation::getHarvestExpectedOrTarget()`), dans son unité principale, puis en kilos : la
	 * pièce et la botte se pèsent au poids que la culture porte, à défaut à celui de l'espèce
	 * (`getUnitKilos()`). Elle se partage également sur ses semaines de récolte prévues
	 * (`getRelativeWeek()`, qui replie la semaine 53 sur la 52). La part récoltée l'année suivante ne
	 * sert aucun panier de celle-ci.
	 *
	 * Les cultures de la saison précédente comptent aussi, au même prorata (arbitrage L5 du 25/09) :
	 * - pour la part qu'elles récoltent dans les semaines de la saison, un poireau ou une mâche semés
	 *   l'an passé servant les paniers de janvier à mars ;
	 * - pour la part récoltée l'année d'avant que la conservation de l'espèce (`storage`,
	 *   `getSourceByPlan()`) porte jusque dans la saison (lot r9), sous `carried`, par sa semaine
	 *   relative, 0 ou moins : une courge récoltée en octobre et conservée huit semaines sert les
	 *   paniers de janvier. `getStockLots()` la fait entrer au stock avant la première semaine.
	 * Une culture de la saison précédente qui ne récolte ni ne conserve rien dans celle-ci n'est pas
	 * comptée du tout, pas même dans `cultivations`.
	 *
	 * - `weeks` : les semaines où au moins une culture de l'espèce récolte, chiffrée ou non ;
	 * - `incomplete` : les cultures sans rendement ou dont la série n'a pas de surface, dont la
	 *   récolte ne se chiffre pas ;
	 * - `weeksIncomplete` et `carriedIncomplete` : les semaines où récolte une culture dont la récolte
	 *   ne se chiffre pas, qu'elle soit sans rendement ou comptée à la pièce ou à la botte sans aucun
	 *   poids connu.
	 *
	 * Une culture de la saison qui ne récolte aucune de ses semaines ne compte que dans
	 * `cultivations` et, sans rendement, dans `incomplete`.
	 */
	public static function getPlanned(int $season, array $source, \Collection $cCompositionSpecies, array $cultivations): array {

		$seasonWeeks = self::getSeasonWeeks();
		[$weekFirst, $weekLast] = [min($seasonWeeks), max($seasonWeeks)];

		$planned = [];

		foreach($cultivations as $cultivation) {

			$speciesId = self::getSpeciesBare($cultivation['plant'], $source);

			// Une espèce sans unité au panier (`units`) n'entre dans aucun panier : rien ne la compare.
			if($speciesId === NULL or array_key_exists($speciesId, $source['units']) === FALSE) {
				continue;
			}

			$storage = $source['storage'][$speciesId]['weeks'];

			$weeksAll = $cultivation['weeks'];
			$weeksRelative = array_map(fn(string $weekIso) => self::getRelativeWeek($weekIso, $season), $weeksAll);

			$weeksInSeason = array_filter($weeksRelative, fn(int $week) => ($week >= $weekFirst and $week <= $weekLast));
			$weeksCarried = array_filter($weeksRelative, fn(int $week) => ($week < $weekFirst and $week + $storage >= $weekFirst));

			if($cultivation['season'] < $season and $weeksInSeason === [] and $weeksCarried === []) {
				continue;
			}

			$planned[$speciesId] ??= ['cultivations' => 0, 'incomplete' => 0, 'harvest' => [], 'weeks' => [], 'weeksIncomplete' => [], 'carried' => [], 'carriedIncomplete' => []];
			$planned[$speciesId]['cultivations']++;

			$harvest = $cultivation['harvest'];

			if($harvest === NULL) {
				$planned[$speciesId]['incomplete']++;
			}

			if($weeksInSeason === [] and $weeksCarried === []) {
				continue;
			}

			$kilos = match($cultivation['mainUnit']) {
				\series\Cultivation::KG => 1.0,
				\series\Cultivation::UNIT => $cultivation['unitWeight'] ?? self::getUnitKilos($speciesId, 'one', $source, $cCompositionSpecies),
				\series\Cultivation::BUNCH => $cultivation['bunchWeight'] ?? self::getUnitKilos($speciesId, 'bunch', $source, $cCompositionSpecies),
			};

			$share = ($harvest === NULL or $kilos === NULL) ? NULL : $harvest * $kilos / count($weeksAll);

			foreach($weeksInSeason as $week) {

				$planned[$speciesId]['weeks'][$week] = $week;

				if($share === NULL) {
					$planned[$speciesId]['weeksIncomplete'][$week] = $week;
				} else {
					$planned[$speciesId]['harvest'][$week] = ($planned[$speciesId]['harvest'][$week] ?? 0.0) + $share;
				}

			}

			foreach($weeksCarried as $week) {

				if($share === NULL) {
					$planned[$speciesId]['carriedIncomplete'][$week] = $week;
				} else {
					$planned[$speciesId]['carried'][$week] = ($planned[$speciesId]['carried'][$week] ?? 0.0) + $share;
				}

			}

		}

		return $planned;

	}

	/**
	 * Puise `$kilos` dans les lots de réserve du stock (`getStock()`), le plus ancien d'abord, et rend ceux
	 * qui restent. Un lot vidé sort ; l'appelant a vérifié que les lots suffisent.
	 */
	private static function takeFromLots(array $lots, float $kilos): array {

		foreach($lots as $position => $lot) {

			if($kilos <= 0.0) {
				break;
			}

			$taken = min($lot['kilos'], $kilos);
			$kilos -= $taken;

			if($taken >= $lot['kilos']) {
				unset($lots[$position]);
			} else {
				$lots[$position]['kilos'] -= $taken;
			}

		}

		return array_values($lots);

	}

	/**
	 * Le besoin des semaines écrites de `$cCompositionFormula`, chaque ligne par les paniers de sa
	 * taille, en kilos (`getUnitKilos()`) : `['needs' => [id d'espèce => [semaine => kilos]],
	 * 'unknown' => [id d'espèce => [semaine => semaine]]]`, où `unknown` porte les semaines dont une
	 * ligne ne se pèse pas : ce que les autres tailles prennent au stock avant celle que la règle
	 * compose (`getStock()`).
	 * Chaque formule porte ses semaines écrites, lignes comprises, sous `cCompositionWeek`.
	 */
	public static function getNeeds(\Collection $cCompositionFormula, array $source, \Collection $cCompositionSpecies): array {

		$needs = [];
		$unknown = [];

		foreach($cCompositionFormula as $eCompositionFormula) {

			foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

				$week = $eCompositionWeek['week'];

				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

					$speciesId = $eCompositionLine['species']['id'];
					$kilos = self::getUnitKilos($speciesId, $eCompositionLine['unit']['fqn'], $source, $cCompositionSpecies);

					$needs[$speciesId][$week] ??= 0.0;

					if($kilos === NULL) {
						$unknown[$speciesId][$week] = $week;
					} else {
						$needs[$speciesId][$week] += $eCompositionLine['quantity'] * $eCompositionFormula['baskets'] * $kilos;
					}

				}

			}

		}

		return [
			'needs' => $needs,
			'unknown' => $unknown,
		];

	}

	/**
	 * Les lots de réserve d'une espèce avant la première semaine de la saison : les récoltes de l'année
	 * d'avant que sa conservation `$storage` porte dans la saison (`carried`, `getPlanned()`).
	 *
	 * Un lot est `['until' => dernière semaine servie, 'kilos' => float]`, et les lots restent dans
	 * l'ordre où ils sont entrés : la conservation est la même pour tous, le plus ancien est donc aussi
	 * le premier à passer (`takeFromLots()`).
	 */
	private static function getLotsCarried(array $plan, int $storage): array {

		$carried = $plan['carried'];
		ksort($carried);

		$lots = [];

		foreach($carried as $week => $kilos) {
			$lots[] = ['until' => $week + $storage, 'kilos' => $kilos];
		}

		return $lots;

	}

	/**
	 * Les lots de réserve à la semaine `$week` : la récolte prévue de la semaine y entre, et les lots
	 * passés leur conservation en sortent, entamés ou non. Chaque semaine de la saison passe par ici,
	 * livrée ou non : une récolte d'une semaine sans panier est au stock pour les suivantes.
	 */
	private static function advanceLots(array $lots, array $plan, int $storage, int $week): array {

		if(isset($plan['harvest'][$week])) {
			$lots[] = ['until' => $week + $storage, 'kilos' => $plan['harvest'][$week]];
		}

		return array_values(array_filter($lots, fn(array $lot) => $lot['until'] >= $week));

	}

	private static function getLotsKilos(array $lots): float {
		return array_sum(array_column($lots, 'kilos'));
	}

	/**
	 * Le besoin et la récolte sont des sommes de flottants : un centième de gramme ne fait pas une
	 * semaine en manque.
	 */
	private static function isLotsEnough(float $available, float $need): bool {
		return ($available >= $need - self::KILOS_EPSILON);
	}

	/**
	 * Le plan de culture date-t-il l'espèce (`getPlanned()`) : une récolte prévue dans la saison, ou
	 * une récolte de l'année d'avant que la conservation porte jusque dans la saison, chiffrée ou non.
	 * Ce sont les espèces auxquelles `getReferenceWindows()` ouvre une saison. Une culture sans
	 * semaine de récolte ne dit rien de ce qu'elle donne, ni quand.
	 */
	private static function isDated(array $plan): bool {
		return ($plan['weeks'] !== [] or $plan['weeksIncomplete'] !== [] or $plan['carried'] !== [] or $plan['carriedIncomplete'] !== []);
	}

	/**
	 * Une récolte qui ne se chiffre pas (`weeksIncomplete`, `carriedIncomplete`, `getPlanned()`) est
	 * encore dans sa conservation à la semaine `$week` : le stock en porte une part inconnue, et ce
	 * qu'il peut donner ne se dit pas.
	 */
	private static function isStockUnknown(array $plan, int $storage, int $week): bool {

		foreach($plan['carriedIncomplete'] as $weekCarried) {

			if($weekCarried + $storage >= $week) {
				return TRUE;
			}

		}

		foreach($plan['weeksIncomplete'] as $weekIncomplete) {

			if($weekIncomplete <= $week and $weekIncomplete + $storage >= $week) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * L'espèce est-elle dans la fenêtre de la semaine, c'est-à-dire candidate de la règle cette
	 * semaine-là (étapes 1 à 3, avec une unité et un prix) ? C'est la case teintée de la grille, la
	 * seule qu'on allume à la main : hors de sa fenêtre, une espèce n'a ni quantité de référence ni
	 * prix, et sa ligne ne se chiffrerait pas.
	 */
	public static function isCandidate(int $week, int $speciesId, int $season, ?array $windows, array $source): bool {
		return in_array($speciesId, self::getCandidates($week, $season, $windows, $source), TRUE);
	}

	/**
	 * L'espèce est-elle un chou (`CABBAGE`) ? Les choux se lisent sur le relevé (`cabbages`), qui ne
	 * porte que les espèces disponibles dans la communauté : les seules qu'un panier puisse servir.
	 */
	public static function isCabbage(int $speciesId, array $source): bool {
		return array_key_exists($speciesId, $source['cabbages']);
	}

	/**
	 * Le panier qui sert `$speciesIds` refuse-t-il `$speciesId` ? Un second chou (`isCabbageRefused()`),
	 * ou une seconde ligne de la même espèce, l'espèce nue et sa variante (`isTwinRefused()`, r71, r76).
	 */
	public static function isBasketRefused(int $speciesId, array $speciesIds, array $source): bool {
		return (self::isCabbageRefused($speciesId, $speciesIds, $source) or self::isTwinRefused($speciesId, $speciesIds, $source));
	}

	/**
	 * Le panier sert-il déjà une autre espèce du plan qui lit la même espèce que `$speciesId`, la
	 * variante d'une espèce nue qu'il sert ou l'espèce nue d'une variante (r71, décidé le
	 * 03/10/2026 : « non » aux deux carottes dans un même panier, repris en r76 : jamais deux variantes
	 * d'une même espèce) ? Les deux sens se lisent sur l'espèce lue (`getPlantId()`). Une source sans
	 * `plants` ne porte qu'une espèce du plan par espèce lue, et ne refuse rien.
	 */
	public static function isTwinRefused(int $speciesId, array $speciesIds, array $source): bool {

		if($source['plants'] === []) {
			return FALSE;
		}

		$plantId = self::getPlantId($speciesId, $source);

		foreach($speciesIds as $speciesIdServed) {

			if($speciesIdServed !== $speciesId and self::getPlantId($speciesIdServed, $source) === $plantId) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Le panier qui sert `$speciesIds` refuse-t-il `$speciesId`, parce que c'est un chou et qu'il en sert
	 * déjà un autre (`CABBAGE`) ? Le chou lui-même ne se compte pas : une ligne qu'on relit n'entre
	 * pas en conflit avec elle-même.
	 */
	private static function isCabbageRefused(int $speciesId, array $speciesIds, array $source): bool {

		if(self::isCabbage($speciesId, $source) === FALSE) {
			return FALSE;
		}

		foreach($speciesIds as $speciesIdServed) {

			if($speciesIdServed !== $speciesId and self::isCabbage($speciesIdServed, $source)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Les semaines d'un plan qui servent au moins deux choux (`CABBAGE`, `isCabbage()`), toutes tailles
	 * confondues : `unamended` celles que la règle recompose, `amended` celles qu'on a corrigées à la
	 * main et qu'elle laisse telles quelles. Ce que la reprise des compositions écrites avant la règle
	 * compte (`migration/2026-09-23-paniers-toolbox-un-chou.md`, qui dit comment remonter son script),
	 * et qui doit rendre zéro sur `unamended` une fois le plan recomposé.
	 */
	public static function countCabbageWeeks(CompositionPlan $eCompositionPlan, array $source): array {

		$counts = ['unamended' => 0, 'amended' => 0];

		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan);

		if($cCompositionFormula->empty()) {
			return $counts;
		}

		$cCompositionWeek = CompositionWeek::model()
			->select(['id', 'amended'])
			->whereFormula('IN', $cCompositionFormula)
			->getCollection(NULL, NULL, 'id');

		$weekIds = [];

		foreach(CompositionLine::model()
			->select(['week', 'species'])
			->whereWeek('IN', $cCompositionWeek)
			->getCollection() as $eCompositionLine) {

			if(self::isCabbage($eCompositionLine['species']['id'], $source)) {
				$weekIds[] = $eCompositionLine['week']['id'];
			}

		}

		foreach(array_count_values($weekIds) as $weekId => $count) {

			if($count > 1) {
				$counts[$cCompositionWeek[$weekId]['amended'] ? 'amended' : 'unamended']++;
			}

		}

		return $counts;

	}

	/**
	 * Les espèces de la fenêtre de la semaine, celles que `isCandidate()` admet : les cases de saison
	 * de sa colonne dans la grille, avec celles qu'elle sert déjà. Dans l'ordre de la règle, que
	 * l'appelant ne garde pas. C'est aussi la référence de
	 * `CompositionPanelTest::testCandidatesAreSeasonCells()`, qui la compare à la grille.
	 */
	public static function getCandidates(int $week, int $season, ?array $windows, array $source): array {
		return self::getOrder($week, $season, $windows, $source);
	}

	/**
	 * Pose sur chaque rang de la grille la ligne que sa case de la semaine `$week` peut recevoir, que
	 * le menu de la case propose (lot r8b, repris aux lots r24 et r24b,
	 * `CompositionUi::getCellMenu()`) : `eCompositionLineAddable` porte l'espèce, l'unité où elle entre
	 * au panier et la quantité d'un ajout à la main (`getQuantityAdded()`, la quantité par défaut de
	 * l'espèce, r30, #92, à défaut son minimum), pour une espèce que la semaine ne sert pas et
	 * dont la case s'ouvre (`getGrid()`, `open`), de sa saison ou non (r29, #83, retour
	 * du 29/09, « permettre d'ajouter des produits même hors planning »). Le menu en préremplit son
	 * champ (retour du 27/09, « mets directement un champ avec la valeur à ajouter et un
	 * bouton valide »).
	 *
	 * La ligne est vide pour une espèce que la semaine sert, et pour un rang sans unité au panier
	 * dans la communauté, qui ne s'ajoute pas (`CompositionLine::species.unsold`) : c'est le cas d'une
	 * ligne gardée d'un relevé qui ne la vend plus. Les espèces cultivées qu'aucun panier ne sert ne
	 * sont pas des rangs : elles attendent qu'une unité puisse leur être donnée (lot r14).
	 *
	 * Une semaine hors saison, ou de la saison sans prix relevé, s'ouvre comme les autres : la ligne
	 * ajoutée prend le prix que `getLineReference()` lui trouve.
	 *
	 * Un chou s'ajoute aussi dans une semaine qui en sert déjà un (r34d) : la règle d'un seul chou
	 * par panier (`CABBAGE`) ne tient que la proposition. Une espèce dont la semaine sert déjà la
	 * variante, ou l'espèce nue, ne s'ajoute pas (r76, `isTwinRefused()`) : la règle tient aussi le
	 * geste, que `CompositionLine::species.twin` refuse.
	 *
	 * Les unités se lisent en une requête pour toute la semaine, avec ce que le champ en écrit
	 * (`CompositionLine::getSelection()`).
	 */
	public static function applyAddableToGrid(array $grid, int $week, array $source, array $steps): array {

		$speciesIdsServed = [];

		foreach($grid as $group) {

			foreach($group['species'] as $row) {

				if(isset($row['lines'][$week])) {
					$speciesIdsServed[] = $row['species']['id'];
				}

			}

		}

		$addable = [];

		foreach($grid as $position => $group) {

			foreach($group['species'] as $index => $row) {

				$grid[$position]['species'][$index]['eCompositionLineAddable'] = new CompositionLine();

				if(
					isset($row['lines'][$week]) === FALSE and
					isset($row['open'][$week]) and
					self::isTwinRefused($row['species']['id'], $speciesIdsServed, $source) === FALSE
				) {
					$addable[] = [$position, $index, $row['species']];
				}

			}

		}

		$cUnit = \product\UnitLib::getByIds(array_map(fn(array $add) => $source['units'][$add[2]['id']]['unit'], $addable), ['id', 'singular', 'plural', 'short', 'type'], index: 'id');

		foreach($addable as [$position, $index, $eCompositionSpecies]) {

			$grid[$position]['species'][$index]['eCompositionLineAddable'] = new CompositionLine([
				'species' => $eCompositionSpecies,
				'unit' => $cUnit[$source['units'][$eCompositionSpecies['id']]['unit']],
				'quantity' => self::getQuantityAdded($eCompositionSpecies['id'], $source, $steps),
			]);

		}

		return $grid;

	}

	/**
	 * La ligne que reçoit une espèce ajoutée à la main : son unité, sa quantité de référence
	 * (étape 7) arrondie au pas de l'unité comme toute quantité proposée, et le prix de la semaine
	 * dans cette unité, celui de la structure s'il en a un (`getPrices()`), sinon la médiane de la
	 * communauté, `NULL` quand sa dispersion la rend inemployable (`getUnitPrice()`).
	 *
	 * Sans facteur : l'étape 8 ajuste le panier que la règle compose au prix visé, et une ligne posée
	 * par-dessus n'en fait pas partie. La valeur de la semaine monte donc de ce que la ligne vaut,
	 * comme dans la planche.
	 *
	 * Le prix est celui de la règle (`getUnitPrice()`), qui se lit sur la semaine relevée la plus
	 * proche quand la communauté ne relève pas l'espèce cette semaine-là.
	 *
	 * `$steps` sont les pas et les minimums réglés sur les espèces du plan
	 * (`CompositionSpeciesLib::getStepsByPlan()`), comme dans `propose()`.
	 */
	public static function getLineReference(int $week, int $speciesId, array $source, array $prices, array $steps): array {

		$unit = $source['units'][$speciesId];

		// Une quantité par défaut réglée sur l'espèce (r30, #92) passe devant la quantité de
		// référence, comme dans le menu de la case (`applyAddableToGrid()`).
		if(($steps[$speciesId]['quantity'] ?? NULL) === NULL) {
			$quantity = self::getQuantity($unit, 1.0, self::getStep($speciesId, $source, $steps));
		} else {
			$quantity = self::getQuantityAdded($speciesId, $source, $steps);
		}

		return [
			'unit' => $unit['unit'],
			'quantity' => $quantity,
			'unitPrice' => self::getUnitPrice($week, $speciesId, $source, $prices),
		];

	}

	/**
	 * Les semaines de la saison rangées par mois, `[mois => [semaines]]`, chaque semaine au mois de
	 * son jeudi (`getMonth()`). La semaine 1 de 2026, du 29 décembre au 4 janvier, se range donc en
	 * janvier.
	 */
	public static function getSeasonMonths(int $season): array {

		$months = [];

		foreach(self::getSeasonWeeks() as $week) {
			$months[self::getMonth($season, $week)][] = $week;
		}

		return $months;

	}

	/**
	 * La saison d'une espèce en douze mois (r48a, planche r48, question 4), sur les semaines `$weeks`
	 * de sa saison et les mois `$seasonMonths` de `getSeasonMonths()` :
	 * `[mois => ['share' => float, 'stored' => float, 'checked' => bool, 'storage' => bool]]`.
	 *
	 * - `share` : la part des semaines du mois qui sont en saison, la hauteur de sa barre ;
	 * - `stored` : parmi elles, la part que seule la conservation ouvre (`$weeksStored`), le haut clair
	 *   de la barre ;
	 * - `checked` : le mois est coché dans le menu, la moitié de ses semaines au moins étant en
	 *   saison. Un mois de quatre semaines dont deux sont en saison est coché ;
	 * - `storage` : le mois ne tient qu'à la conservation, ce que le menu et la bulle de la barre
	 *   nomment.
	 */
	public static function getSeasonShares(array $seasonMonths, array $weeks, array $weeksStored): array {

		$shares = [];

		foreach($seasonMonths as $month => $weeksMonth) {

			$weeksSeason = array_intersect($weeksMonth, $weeks);

			$share = count($weeksSeason) / count($weeksMonth);
			$stored = ($weeksSeason === []) ? 0.0 : count(array_intersect($weeksSeason, $weeksStored)) / count($weeksSeason);

			$shares[$month] = [
				'share' => $share,
				'stored' => $stored,
				'checked' => ($share >= 0.5),
				// Pas `$stored === 1.0` : une division exacte de deux entiers rend l'entier 1.
				'storage' => ($weeksSeason !== [] and array_diff($weeksSeason, $weeksStored) === []),
			];

		}

		return $shares;

	}

	/**
	 * La proposition d'une formule au prix `$price` et à l'écart maximum `$gap` (en %), semaine par
	 * semaine :
	 * `[semaine => ['lines' => [['species', 'unit', 'quantity', 'unitPrice'], …],
	 * 'relaxedFloor' => [organes]]]`.
	 *
	 * Une semaine sans aucune candidate n'a pas de panier du tout, et n'apparaît pas.
	 *
	 * La dette ne court que sur les semaines livrées : une semaine sans panier n'ouvre aucune part
	 * attendue, et une espèce n'y prend pas de retard qu'elle rattraperait ensuite.
	 *
	 * `$windows` est la saison de chaque espèce du plan, `[id d'espèce => [semaine, …]]`
	 * (`CompositionSpeciesLib::getWindowsByPlan()`), qui dit ses candidates (`getOrder()`), et `NULL`
	 * la saison de la communauté.
	 *
	 * `$version` est le tirage de la formule (`CompositionFormula.version`), que « Rejouer les
	 * paniers » avance d'un cran (`CompositionFormulaLib::replay()`) : il n'entre que dans le bruit
	 * qui départage les dettes (`getNoise()`), et la version 0 rend la proposition d'avant le rejeu.
	 *
	 * `$prices` sont les prix de la structure (`getPrices()`), lus par l'appelant : ils remplacent la
	 * médiane de la communauté dans le premier nombre de lignes, dans la valeur du tirage et dans le
	 * prix figé sur chaque ligne. Sans eux (`[]`), la proposition est celle de la médiane seule. Ils
	 * ne changent pas qui est candidate : la règle compose sur ce que la communauté récolte et vend.
	 *
	 * `$steps` sont les pas et les minimums réglés sur les espèces du plan
	 * (`CompositionSpeciesLib::getStepsByPlan()`, revue v12) : ils ne jouent qu'à l'arrondi de la
	 * quantité (`getQuantity()`), ni sur qui entre au panier ni sur le facteur. Sans eux (`[]`),
	 * chaque espèce prend le pas de son unité et un minimum d'un pas.
	 *
	 * `$frequencies` sont les fréquences choisies sur les espèces du plan, `[id d'espèce => fréquence]`
	 * (`CompositionSpeciesLib::getFrequenciesByPlan()`, lot L9) : chacune de ces espèces tient son
	 * rythme (`applyFrequencies()`), les autres se partagent ce qui reste. Sans elles (`[]`), la
	 * proposition est exactement celle d'avant le lot.
	 *
	 * `$base` sont les lignes écrites de la base de la formule, par semaine (`getBaseLines()`, lot
	 * r17) : une semaine que la base sert se dérive de son panier (`deriveWeek()`), une semaine qu'elle
	 * ne sert pas se compose seule. Les deux passent par la même dette et le même écart : une espèce
	 * héritée a été servie, et elle compte dans le dernier panier qui l'a servie comme dans ce qu'elle
	 * doit encore. Sans base (`[]`), la proposition est celle d'avant le lot.
	 *
	 * `$stock` est le stock que le plan de culture prévoit (`getStock()`, lot r23, tranché
	 * le 27/09 : « le stock passe devant la cible »). Il se suit semaine après semaine : les récoltes
	 * entrent au stock, les lots passés leur conservation en sortent,
	 * les autres tailles y prennent d'abord leurs semaines écrites, puis celle-ci ses paniers. Une
	 * espèce dont le stock ne donne pas son minimum à chaque panier n'est pas candidate cette semaine,
	 * et une ligne ne dépasse pas ce qu'il donne, ni à l'arrondi, ni à l'ajustement au pas
	 * (`getStockCap()`). Une semaine de soudure reste donc hors de l'écart. Sans stock (`[]`), rien
	 * ne plafonne.
	 */
	public static function propose(float $price, int $gap, int $linesMin, int $linesMax, array $weeks, ?array $windows, int $season, array $source, array $prices, array $steps, int $version = 0, array $frequencies = [], array $base = [], array $stock = []): array {

		sort($weeks);

		$source = self::applyFrequencies($price, $linesMin, $linesMax, $weeks, $season, $windows, $source, $prices, $frequencies);

		$proposal = [];
		$debts = [];
		$servedWeeks = [];
		$inheritedWeeks = self::getInheritedWeeks($base, $frequencies);

		$lots = self::getStockLots($stock, $source);
		$weekStock = 0;

		foreach($weeks as $week) {

			// Ce que la règle sait des paniers qui servent chaque espèce : ceux qu'elle a composés, et
			// pour une espèce à la fréquence choisie ceux que la base lui fera hériter plus tard, qu'un
			// plafond doit compter avant qu'ils n'arrivent (`isCapped()`).
			$servedKnown = self::getServedKnown($week, $servedWeeks, $inheritedWeeks);

			$lots = self::advanceStock($stock, $source, $lots, $weekStock, $week);
			$weekStock = $week;

			$caps = self::getStockCaps($stock, $source, $steps, $lots, $week);

			$order = array_values(array_filter(
				self::getOrder($week, $season, $windows, $source),
				fn(int $speciesId) => (array_key_exists($speciesId, $caps) === FALSE or $caps[$speciesId] !== NULL)
			));

			if(array_key_exists($week, $base)) {

				$derived = self::deriveWeek($price, $gap, $linesMin, $linesMax, $week, $season, $order, $base[$week], $source, $prices, $steps, $debts, $servedKnown, $version, $frequencies, $caps);

				$proposal[$week] = [
					'lines' => $derived['lines'],
					'relaxedFloor' => $derived['relaxedFloor'],
				];

				$lots = self::takeStock($stock, $source, $lots, $derived['lines']);

				foreach(self::getShares($week, $order, count($derived['species']), $source) as $speciesId => $share) {
					$debts[$speciesId] = ($debts[$speciesId] ?? 0.0) + $share;
				}

				// La dette ne court que pour les candidates de la semaine : une espèce héritée hors de
				// sa fenêtre, ajoutée à la main sur la base, n'était attendue nulle part et ne solde rien.
				foreach($derived['species'] as $speciesId) {

					if(in_array($speciesId, $order, TRUE)) {
						$debts[$speciesId] -= 1.0;
					}

					$servedWeeks[$speciesId][] = $week;

				}

				continue;

			}

			if($order === []) {
				continue;
			}

			$lines = self::getLinesStart($price, $linesMin, $linesMax, $week, $order, $source, $prices);
			$shares = self::getShares($week, $order, $lines, $source);

			// Une espèce faible ou de diversification servie trop près n'est pas candidate au tirage
			// cette semaine (`isSpaced()`), et la suivante dans l'ordre prend sa place. Elle garde sa
			// part dans la dette (`$orderAll`) : sans elle, sa dette ne courrait que les semaines où
			// elle peut entrer, et elle finirait l'année sous sa fréquence, au profit des espèces de
			// base. Une semaine dont toutes les candidates attendraient garde son ordre entier plutôt
			// que de rester sans panier, sauf les espèces au plafond de la fréquence qu'elles ont choisie
			// (`getOrderSpaced()`).
			$orderAll = $order;
			$order = self::getOrderSpaced($week, $order, $servedKnown, $shares, $frequencies);

			// Toutes les candidates de la semaine sont au plafond de leur fréquence choisie : la semaine
			// n'a pas de panier, comme une semaine sans candidate, et la dette n'y court pas.
			if($order === []) {
				continue;
			}

			$order = self::getOrderByDebt($season, $week, $order, $shares, $debts, $version, $source);

			$basket = self::draw($lines, $linesMin, $week, $order, $source, $prices);

			// Étape 8 : si le facteur sort de ses bornes, une espèce de plus ou de moins, et on
			// reprend le tirage. Trois fois au plus, et jamais hors des bornes de la formule.
			for($try = 0; $try < self::FACTOR_TRIES; $try++) {

				$factor = self::getFactor($price, $basket['value']);

				if($factor >= self::FACTOR_LOW and $factor <= self::FACTOR_HIGH) {
					break;
				}

				$linesNext = ($factor > self::FACTOR_HIGH) ? $lines + 1 : $lines - 1;

				if($linesNext < $linesMin or $linesNext > $linesMax) {
					break;
				}

				$lines = $linesNext;
				$basket = self::draw($lines, $linesMin, $week, $order, $source, $prices);

			}

			// Toujours l'étape 8 : quand le panier arrondi s'écarte encore du prix au-delà de l'écart de
			// la formule, une ligne de moins et une ligne de plus sont essayées, et le panier dont la
			// valeur arrondie tombe le plus près du prix est gardé. Seul le nombre de lignes est
			// relancé : le facteur de chaque essai reste unique, borné et appliqué une fois.
			//
			// L'écart vient presque entièrement de l'arrondi, et surtout des unités au pas de 1 (une
			// botte, une pièce) : à `f = 0,74`, une botte de carottes à 3 € reste une botte et pèse
			// 0,79 € de trop. Sur 2026, à l'écart par défaut de 5 %, les semaines au-delà passent de 15, 17 et 12 à 4, 5 et 6
			// (12, 16 et 20 €) ; une seule semaine en tout doit son écart au facteur borné.
			$valueProposed = self::getValueProposed($price, $basket, $week, $source, $prices, $steps);

			if(self::isGap($price, $valueProposed, $gap)) {

				foreach([$lines - 1, $lines + 1] as $linesOther) {

					if($linesOther < $linesMin or $linesOther > $linesMax) {
						continue;
					}

					$basketOther = self::draw($linesOther, $linesMin, $week, $order, $source, $prices);
					$factorOther = self::getFactor($price, $basketOther['value']);
					$valueOther = self::getValueProposed($price, $basketOther, $week, $source, $prices, $steps);

					if(
						$factorOther >= self::FACTOR_LOW and $factorOther <= self::FACTOR_HIGH and
						abs($valueOther - $price) < abs($valueProposed - $price)
					) {
						$basket = $basketOther;
						$valueProposed = $valueOther;
					}

				}

			}

			// Un facteur unique, borné, appliqué une fois, puis l'arrondi au pas de l'unité. Ce que
			// l'arrondi décale au-delà de l'écart, l'ajustement au pas le reprend ligne à ligne
			// (`applyPriceSteps()`, lot r23, qui revient sur « pas de second passage »).
			$factor = self::getFactorBounded($price, $basket['value']);

			$linesProposed = [];

			foreach($basket['species'] as $speciesId) {

				$unit = $source['units'][$speciesId];

				$linesProposed[] = [
					'species' => $speciesId,
					'unit' => $unit['unit'],
					'quantity' => self::getQuantity($unit, $factor, self::getStep($speciesId, $source, $steps)),
					'unitPrice' => self::getUnitPrice($week, $speciesId, $source, $prices),
				];

			}

			$proposal[$week] = [
				'lines' => self::applyPriceSteps($price, $gap, self::applyStockCaps($linesProposed, $caps), self::getBoundsProposed($linesProposed, $source, $steps, $caps)),
				'relaxedFloor' => $basket['relaxedFloor'],
			];

			$lots = self::takeStock($stock, $source, $lots, $proposal[$week]['lines']);

			// La dette se solde sur le panier réellement composé : l'étape 8 a pu ajouter ou retirer
			// une espèce, et les parts se rapportent alors au nombre de lignes final.
			foreach(self::getShares($week, $orderAll, count($basket['species']), $source) as $speciesId => $share) {
				$debts[$speciesId] = ($debts[$speciesId] ?? 0.0) + $share;
			}

			foreach($basket['species'] as $speciesId) {
				$debts[$speciesId] -= 1.0;
				$servedWeeks[$speciesId][] = $week;
			}

		}

		return $proposal;

	}

	/**
	 * Le panier d'une semaine que la base de la formule sert (lot r17), en quatre temps.
	 *
	 * 1. Héritage. Les lignes écrites de la base, `$inherited`, corrections à la main comprises (Q2
	 *    du cadrage : « pas de tomates, elles ont grêlé » vaut pour le grand panier comme pour le
	 *    petit). Chacune garde son espèce, son unité et son prix figé.
	 * 2. Diversité. Des espèces absentes de la base, tirées dans l'ordre de la dette de la formule,
	 *    après l'écart de leur fréquence (`getOrderSpaced()`, sur un `$servedWeeks` qui compte les
	 *    espèces héritées) : autant que le prix en demande (`getLinesStart()` moins les lignes héritées), au
	 *    moins une s'il reste de la place, et jamais au-delà de `$linesMax`. Une base qui atteint déjà
	 *    ce plafond est héritée entière, sans espèce ajoutée : un panier qui contient le petit ne peut
	 *    pas en retirer.
	 * 3. Quantités. Un facteur unique g, calculé comme celui de l'étape 8 mais tenu à 1 au moins
	 *    (`getFactorDerived()`), multiplie les lignes héritées et les quantités de référence des
	 *    espèces ajoutées, puis l'arrondi au pas de chacune. Une botte à 1 × 1,4 reste une botte et
	 *    un kilo passe à 1,4 kg : c'est ce que font les paniers partagés, où le grand augmente les
	 *    kilos (rapport médian 1,43) et garde les pièces et les bottes (rapport médian 1). Une ligne
	 *    héritée ne descend jamais sous la quantité de la base.
	 * 4. Ajustement. Comme à l'étape 8, un facteur qui tombe sous 1 retire une espèce ajoutée et un
	 *    facteur au-delà de sa borne haute en ajoute une, trois fois au plus, puis une espèce de moins
	 *    et une de plus sont essayées quand le panier arrondi s'écarte du prix au-delà de `$gap`.
	 * 5. Ajustement au pas (lot r23), comme pour une semaine composée seule (`applyPriceSteps()`) :
	 *    une ligne ajoutée entre son minimum et 2 fois sa référence, une ligne héritée entre sa
	 *    quantité de base et 2 fois celle-ci.
	 *
	 * `$order` est l'ordre de la semaine pour la formule (`getOrder()`), vide quand aucune espèce n'y
	 * est candidate : la base est alors héritée seule. `$linesMin` pèse sur le premier nombre de
	 * lignes, et en plancher sur le tirage et ses relances (r70) : une semaine dérivée qui a des
	 * candidates n'ajoute jamais moins d'espèces qu'il n'en faut pour l'atteindre, sauf faute de place
	 * sous `$linesMax`, et le plafond par organe cède devant lui, comme pour une semaine composée seule.
	 */
	private static function deriveWeek(float $price, int $gap, int $linesMin, int $linesMax, int $week, int $season, array $order, array $inherited, array $source, array $prices, array $steps, array $debts, array $servedWeeks, int $version, array $frequencies, array $caps): array {

		$speciesInherited = array_column($inherited, 'species');

		$valueInherited = 0.0;

		foreach($inherited as $line) {

			if($line['unitPrice'] !== NULL) {
				$valueInherited += $line['quantity'] * $line['unitPrice'];
			}

		}

		$room = max(0, $linesMax - count($inherited));
		$orderAdded = array_values(array_diff($order, $speciesInherited));
		$addedMin = min($room, max(0, $linesMin - count($inherited)));

		if($orderAdded === []) {
			$added = 0;
		} else {

			$lines = self::getLinesStart($price, $linesMin, $linesMax, $week, $order, $source, $prices);
			$added = min($room, max(1, $lines - count($inherited)));

			$shares = self::getShares($week, $order, $lines, $source);

			// Une espèce servie trop près attend, comme dans `propose()` ; si toutes attendent, l'ordre
			// reste entier plutôt que de laisser le panier sans diversité, sauf les espèces au plafond
			// de la fréquence qu'elles ont choisie : la base est alors héritée seule.
			$orderAdded = self::getOrderSpaced($week, $orderAdded, $servedWeeks, $shares, $frequencies);

			$orderAdded = self::getOrderByDebt($season, $week, $orderAdded, $shares, $debts, $version, $source);

		}

		$basket = self::drawAdded($added, $addedMin, $week, $orderAdded, $inherited, $source, $prices);

		for($try = 0; $try < self::FACTOR_TRIES; $try++) {

			$factor = self::getFactor($price, $valueInherited + $basket['value']);

			if($factor >= 1.0 and $factor <= self::FACTOR_HIGH) {
				break;
			}

			$addedNext = ($factor > self::FACTOR_HIGH) ? $added + 1 : $added - 1;

			if($addedNext < $addedMin or $addedNext > $room) {
				break;
			}

			$added = $addedNext;
			$basket = self::drawAdded($added, $addedMin, $week, $orderAdded, $inherited, $source, $prices);

		}

		$valueProposed = self::getValueDerived($price, $inherited, $valueInherited, $basket, $week, $source, $prices, $steps);

		if(self::isGap($price, $valueProposed, $gap)) {

			foreach([$added - 1, $added + 1] as $addedOther) {

				if($addedOther < $addedMin or $addedOther > $room) {
					continue;
				}

				$basketOther = self::drawAdded($addedOther, $addedMin, $week, $orderAdded, $inherited, $source, $prices);
				$factorOther = self::getFactor($price, $valueInherited + $basketOther['value']);
				$valueOther = self::getValueDerived($price, $inherited, $valueInherited, $basketOther, $week, $source, $prices, $steps);

				if(
					$factorOther >= 1.0 and $factorOther <= self::FACTOR_HIGH and
					abs($valueOther - $price) < abs($valueProposed - $price)
				) {
					$basket = $basketOther;
					$valueProposed = $valueOther;
				}

			}

		}

		$factor = self::getFactorDerived($price, $valueInherited + $basket['value']);

		$lines = [];

		foreach($inherited as $line) {

			$lines[] = [
				'species' => $line['species'],
				'unit' => $line['unit'],
				'quantity' => min(self::getQuantityInherited($line, $factor), self::getCapInherited($line, $source, $caps)),
				'unitPrice' => $line['unitPrice'],
			];

		}

		$linesAdded = [];

		foreach($basket['species'] as $speciesId) {

			$unit = $source['units'][$speciesId];

			$linesAdded[] = [
				'species' => $speciesId,
				'unit' => $unit['unit'],
				'quantity' => self::getQuantity($unit, $factor, self::getStep($speciesId, $source, $steps)),
				'unitPrice' => self::getUnitPrice($week, $speciesId, $source, $prices),
			];

		}

		// L'ajustement au pas (r23) vaut pour toutes les tailles : il reprend la part ajoutée, les
		// espèces en plus et le surplus au-dessus de la base, jamais une ligne héritée sous sa quantité
		// de base (`getBoundsInherited()`).
		$bounds = array_merge(self::getBoundsInherited($inherited, $source, $caps), self::getBoundsProposed($linesAdded, $source, $steps, $caps));
		$lines = self::applyPriceSteps($price, $gap, array_merge($lines, self::applyStockCaps($linesAdded, $caps)), $bounds);

		return [
			'lines' => $lines,
			'species' => array_merge($speciesInherited, $basket['species']),
			'relaxedFloor' => $basket['relaxedFloor'],
		];

	}

	/**
	 * Le tirage des `$count` espèces qu'une semaine dérivée ajoute à son héritage (lot r17), dans
	 * l'ordre `$order`, qui ne porte aucune espèce héritée. Les règles de `draw()` jouent sur le
	 * panier entier, lignes héritées comprises : aucun organe au-delà de la moitié des lignes, un
	 * seul chou, et le plancher d'organes à partir de quatre lignes. Seule une espèce ajoutée cède sa
	 * place au plancher : une ligne héritée ne part pas.
	 *
	 * Comme dans `draw()`, le plafond par organe cède devant le plancher de lignes (r70) : un tirage
	 * qui ajoute moins de `$countMin` espèces se complète par les candidates suivantes dans l'ordre,
	 * sans plafond par organe, la règle d'un seul chou gardée.
	 *
	 * La variante d'une espèce héritée ou prise, ou l'espèce nue d'une variante, est sautée comme un
	 * second chou (r76, `isTwinRefused()`).
	 *
	 * La valeur est celle des espèces ajoutées, à leur quantité de référence.
	 */
	private static function drawAdded(int $count, int $countMin, int $week, array $order, array $inherited, array $source, array $prices): array {

		$organs = $source['organs'];
		$speciesInherited = array_column($inherited, 'species');
		$ceiling = intdiv(count($inherited) + $count, 2);

		$byOrgan = [\plant\Plant::LEAF => 0, \plant\Plant::ROOT => 0, \plant\Plant::FRUIT => 0];

		foreach($inherited as $line) {

			if($line['organ'] !== NULL) {
				$byOrgan[$line['organ']]++;
			}

		}

		$taken = [];
		$relaxedFloor = [];

		foreach($order as $speciesId) {

			if(count($taken) >= $count) {
				break;
			}

			$organ = $organs[$speciesId];

			if($organ !== NULL and $byOrgan[$organ] >= $ceiling) {
				continue;
			}

			if(self::isBasketRefused($speciesId, array_merge($speciesInherited, $taken), $source)) {
				continue;
			}

			$taken[] = $speciesId;

			if($organ !== NULL) {
				$byOrgan[$organ]++;
			}

		}

		foreach($order as $speciesId) {

			if(count($taken) >= $countMin) {
				break;
			}

			if(in_array($speciesId, $taken, TRUE) or self::isBasketRefused($speciesId, array_merge($speciesInherited, $taken), $source)) {
				continue;
			}

			$taken[] = $speciesId;

			if($organs[$speciesId] !== NULL) {
				$byOrgan[$organs[$speciesId]]++;
			}

		}

		if($taken !== [] and count($inherited) + count($taken) >= self::FLOOR_FROM) {

			foreach(array_keys($byOrgan) as $organ) {

				if($byOrgan[$organ] > 0) {
					continue;
				}

				$position = NULL;

				foreach(array_reverse($taken, TRUE) as $index => $speciesId) {

					$organTaken = $organs[$speciesId];

					if($organTaken !== NULL and $byOrgan[$organTaken] > 1) {
						$position = $index;
						break;
					}

				}

				if($position === NULL) {
					$relaxedFloor[] = $organ;
					continue;
				}

				$remaining = $taken;
				unset($remaining[$position]);

				$found = NULL;

				foreach($order as $speciesId) {

					if(
						in_array($speciesId, $taken, TRUE) === FALSE and
						$organs[$speciesId] === $organ and
						self::isBasketRefused($speciesId, array_merge($speciesInherited, $remaining), $source) === FALSE
					) {
						$found = $speciesId;
						break;
					}

				}

				if($found === NULL) {
					$relaxedFloor[] = $organ;
					continue;
				}

				$byOrgan[$organs[$taken[$position]]]--;
				$byOrgan[$organ] = 1;
				$taken[$position] = $found;

			}

		}

		$value = 0.0;

		foreach($taken as $speciesId) {

			$unitPrice = self::getUnitPrice($week, $speciesId, $source, $prices);

			if($unitPrice !== NULL) {
				$value += $source['units'][$speciesId]['quantity'] * $unitPrice;
			}

		}

		return [
			'species' => array_values($taken),
			'value' => $value,
			'relaxedFloor' => $relaxedFloor,
		];

	}

	/**
	 * La valeur d'une semaine dérivée telle qu'elle sera écrite et affichée (lot r17), comme
	 * `getValueProposed()` pour une semaine composée seule.
	 */
	private static function getValueDerived(float $price, array $inherited, float $valueInherited, array $basket, int $week, array $source, array $prices, array $steps): float {

		$factor = self::getFactorDerived($price, $valueInherited + $basket['value']);
		$values = [];

		foreach($inherited as $line) {
			$values[] = self::getLineValue(self::getQuantityInherited($line, $factor), $line['unitPrice']);
		}

		foreach($basket['species'] as $speciesId) {
			$values[] = self::getLineValue(self::getQuantity($source['units'][$speciesId], $factor, self::getStep($speciesId, $source, $steps)), self::getUnitPrice($week, $speciesId, $source, $prices));
		}

		return self::getWeekValue($values);

	}

	/**
	 * Le facteur d'une semaine dérivée (lot r17) : celui de l'étape 8, tenu entre 1 et sa borne
	 * haute. Sous 1, il réduirait les lignes héritées, et le grand panier ne contiendrait plus le petit.
	 */
	private static function getFactorDerived(float $price, float $value): float {
		return min(self::FACTOR_HIGH, max(1.0, self::getFactor($price, $value)));
	}

	/**
	 * La quantité d'une ligne héritée (lot r17) : celle de la base multipliée par le facteur, arrondie
	 * au pas de la ligne (`getLineStep()`), et jamais sous la quantité de la base, qu'un arrondi vers
	 * le bas ou un pas réglé après coup ferait passer dessous.
	 */
	private static function getQuantityInherited(array $line, float $factor): float {
		return max($line['quantity'], self::roundToStep($line['quantity'] * $factor, $line['step']));
	}

	/**
	 * Les lignes écrites de la base d'une formule, par semaine, telles que la dérivée les reprend
	 * (lot r17, `propose()`) : `[semaine => [['species', 'unit', 'quantity', 'unitPrice', 'organ',
	 * 'step'], …]]`, dans l'ordre où elles sont nées. Une semaine de la base sans ligne n'y figure
	 * pas : la dérivée s'y compose seule. Vide pour une formule sans base.
	 */
	public static function getBaseLines(CompositionFormula $eCompositionFormula, array $source, array $steps): array {

		$eCompositionFormula->expects(['base']);

		if($eCompositionFormula['base']->empty()) {
			return [];
		}

		$base = [];

		foreach(CompositionLineLib::getByFormula($eCompositionFormula['base']) as $eCompositionLine) {

			$speciesId = $eCompositionLine['species']['id'];

			$base[$eCompositionLine['week']['week']][] = [
				'species' => $speciesId,
				'unit' => $eCompositionLine['unit']['id'],
				'quantity' => (float)$eCompositionLine['quantity'],
				'unitPrice' => ($eCompositionLine['unitPrice'] === NULL) ? NULL : (float)$eCompositionLine['unitPrice'],
				'organ' => $eCompositionLine['species']['plant']['organ'],
				'step' => self::getLineStep($speciesId, $eCompositionLine['unit'], $source, $steps),
			];

		}

		return $base;

	}

	/**
	 * Le relevé que `propose()` lit, où chaque espèce qui a choisi sa fréquence (lot L9) reçoit, dans
	 * chaque semaine où elle est candidate, la part brute qui la ramène à son rythme
	 * (`getFrequencyTarget()`) : c'est la dette qui décide qui entre au panier, et elle se nourrit de
	 * ces parts (`getShares()`, `getOrderByDebt()`). Les autres espèces gardent la part que leur
	 * donnent les paniers partagés, et se partagent ce que les espèces réglées laissent.
	 *
	 * `getShares()` ramène les parts brutes d'une semaine au nombre de lignes du panier, au prorata. La
	 * part brute `t × autres / (lignes − Σ t)`, où `autres` est la somme des parts brutes des espèces
	 * non réglées et `Σ t` celle des rythmes des espèces réglées, vaut donc exactement `t` une fois
	 * ramenée : l'espèce est attendue dans la part `t` des paniers. Sauf pour les lignes d'une même
	 * espèce (r76), l'espèce nue et ses variantes : elles n'ont qu'une place à elles toutes, que
	 * `getShares()` leur partage au prorata de leur part brute, la part de la communauté pour une ligne
	 * sans réglage comprise (décidé le 04/10/2026). Une variante en base à côté de son
	 * espèce nue est donc servie sous `t`. Ses lignes réglées comptent ensemble dans `Σ t`, pour 1 au
	 * plus. Le dénominateur est tenu à 0,5 au
	 * moins, pour une semaine où les espèces réglées demanderaient à elles seules tout le panier.
	 * Quand aucune espèce non réglée n'a de part, le rythme sert lui-même de part brute : sans quoi
	 * toutes les espèces réglées de la semaine tomberaient à zéro.
	 *
	 * Le nombre de lignes et l'ordre sont ceux du relevé d'origine : la part se calcule sur le panier
	 * que la semaine aurait sans réglage.
	 *
	 * Sans fréquence choisie dans une semaine, rien ne change pour elle, et sans fréquence du tout le
	 * relevé revient tel quel : c'est ce qui garde la proposition des plans sans réglage identique à
	 * celle d'avant le lot.
	 */
	public static function applyFrequencies(float $price, int $linesMin, int $linesMax, array $weeks, int $season, ?array $windows, array $source, array $prices, array $frequencies): array {

		if($frequencies === []) {
			return $source;
		}

		$sourceFrequency = $source;

		foreach($weeks as $week) {

			$order = self::getOrder($week, $season, $windows, $source);
			$chosen = array_intersect_key($frequencies, array_flip($order));

			if($chosen === []) {
				continue;
			}

			$lines = self::getLinesStart($price, $linesMin, $linesMax, $week, $order, $source, $prices);

			$others = 0.0;
			$targetsByPlant = [];

			foreach($order as $speciesId) {

				if(array_key_exists($speciesId, $chosen)) {
					$plantId = self::getPlantId($speciesId, $source);
					$targetsByPlant[$plantId] = ($targetsByPlant[$plantId] ?? 0.0) + self::getFrequencyTarget($chosen[$speciesId]);
				} else {
					$others += $source['frequency']['byWeek'][$week][$speciesId] ?? $source['frequency']['byYear'][$speciesId] ?? 0.0;
				}

			}

			// Les variantes d'une espèce (r76) n'occupent qu'une place à elles toutes (`getShares()`) :
			// deux variantes en base demandent 1,5 panier sur une place qui n'en donne qu'un.
			$targets = array_sum(array_map(fn(float $target) => min($target, 1.0), $targetsByPlant));

			$scale = ($others > 0) ? $others / max($lines - $targets, 0.5) : 1.0;

			foreach($chosen as $speciesId => $frequency) {
				$sourceFrequency['frequency']['byWeek'][$week][$speciesId] = self::getFrequencyTarget($frequency) * $scale;
			}

		}

		return $sourceFrequency;

	}

	/**
	 * La part des paniers où une fréquence attend l'espèce, 0,75 pour trois semaines sur quatre.
	 */
	public static function getFrequencyTarget(string $frequency): float {

		[$served, $weeks] = self::FREQUENCIES[$frequency];

		return $served / $weeks;

	}

	/**
	 * La fréquence courante d'une espèce qui n'en a pas choisi (lot L9) : la part des semaines où elle
	 * est servie parmi celles où elle est candidate, rangée aux seuils de `FREQUENCY_FLOORS`. Une
	 * espèce qui n'est candidate d'aucune semaine n'est servie nulle part, et elle est en
	 * diversification.
	 */
	public static function getFrequencyCurrent(int $served, int $candidate): string {

		return self::getFrequencyByRate(($candidate > 0) ? $served / $candidate : 0.0);

	}

	/**
	 * Les candidates `$order` qui peuvent entrer au panier de la semaine `$week` (`isSpaced()`), dans
	 * le même ordre : le seul endroit qui décide de ce que le tirage, le plancher d'organes et les
	 * essais à une ligne de plus ou de moins peuvent prendre, pour une semaine composée seule
	 * (`draw()`) comme pour les espèces ajoutées d'une semaine dérivée (`drawAdded()`). Aucun des deux
	 * ne lit d'autre ordre que celui-ci.
	 *
	 * Quand toutes attendent, l'ordre reste entier plutôt que de laisser la semaine sans panier, à
	 * une exception près (lot r44) : une espèce au plafond de la fréquence qu'elle a choisie
	 * (`isCapped()`) n'y revient pas. Un écart tiré de la part est une préférence de la règle, une
	 * fréquence choisie est un engagement de la personne. La liste peut donc être vide, quand toutes
	 * les candidates de la semaine ont choisi leur fréquence et sont à leur plafond.
	 */
	private static function getOrderSpaced(int $week, array $order, array $servedWeeks, array $shares, array $frequencies): array {

		$orderSpaced = array_values(array_filter($order, fn(int $speciesId) => self::isSpaced($week, $speciesId, $servedWeeks, $shares[$speciesId], $frequencies)));

		if($orderSpaced !== []) {
			return $orderSpaced;
		}

		return array_values(array_filter($order, fn(int $speciesId) => self::isCapped($week, $speciesId, $servedWeeks, $frequencies) === FALSE));

	}

	/**
	 * Une espèce peut-elle entrer au panier de la semaine `$week`, compte tenu des paniers qui l'ont
	 * servie (`$servedWeeks`, `[id d'espèce => [semaine, …]]`) ? L'écart se compte en numéros de
	 * semaine : une semaine sans panier le remplit comme une autre.
	 *
	 * Une espèce qui a choisi sa fréquence tient le plafond de celle-ci (`isCapped()`). Les autres
	 * tiennent l'écart minimal (`FREQUENCY_GAPS`) de la fréquence de leur part `$share` dans le panier
	 * de la semaine (`getShares()`), rangée aux seuils de la fréquence courante (`FREQUENCY_FLOORS`).
	 * C'est la part sur laquelle court la dette, donc ce que la proposition servirait de l'espèce sans
	 * écart : ramenée au nombre de lignes, elle vaut en moyenne le double de la part brute des paniers
	 * partagés, et une espèce rangée sur sa part brute recevrait un écart que sa dette ne peut pas
	 * tenir. Elle y resterait endettée, entrerait dès la fin de chaque écart, et reviendrait toutes
	 * les deux ou trois semaines comme une horloge.
	 */
	private static function isSpaced(int $week, int $speciesId, array $servedWeeks, float $share, array $frequencies): bool {

		if(array_key_exists($speciesId, $servedWeeks) === FALSE) {
			return TRUE;
		}

		if(array_key_exists($speciesId, $frequencies)) {
			return (self::isCapped($week, $speciesId, $servedWeeks, $frequencies) === FALSE);
		}

		return ($week - max($servedWeeks[$speciesId]) >= self::FREQUENCY_GAPS[self::getFrequencyByRate($share)]);

	}

	/**
	 * Une espèce est-elle, la semaine `$week`, au plafond de la fréquence qu'elle a choisie (lot r44,
	 * retour du 01/10 : « on ne peut pas balancer des courges toutes les semaines parce que
	 * c'est un fruit alors qu'on a sélectionné 1 semaine sur 2 ») ? Une fréquence `[servies, sur]`
	 * (`FREQUENCIES`) est un plafond autant qu'une cible : aucune suite de `sur` numéros de semaine
	 * ne sert l'espèce plus de `servies` fois. À 1 semaine sur 2, jamais deux semaines de suite ; à
	 * 1 sur 3 et 1 sur 5, de même sur trois et cinq semaines ; à 3 sur 4, jamais quatre semaines de
	 * suite.
	 *
	 * Chaque suite de `sur` semaines qui contient `$week` est donc regardée. Pour une semaine
	 * composée seule, `$servedWeeks` ne porte que des paniers passés, et seule compte la suite qui
	 * finit en `$week` : l'espèce déjà servie `servies` fois dans les `sur - 1` semaines précédentes
	 * n'entre pas. Pour une taille qui s'appuie sur une autre, il porte aussi les semaines où la base
	 * la fera hériter (`getServedKnown()`) : une espèce ajoutée la veille d'un panier hérité ferait
	 * deux semaines de suite sans que le tirage du lendemain puisse rien y changer.
	 *
	 * Une espèce qui n'a pas choisi sa fréquence n'a pas de plafond : sa part ne promet rien à
	 * personne, et son écart reste celui de `FREQUENCY_GAPS`.
	 *
	 * Ne se pose que sur une espèce connue de `$servedWeeks` : `isSpaced()` laisse passer les autres
	 * avant d'arriver ici, et le repli de `getOrderSpaced()` ne joue que quand toutes le sont.
	 */
	private static function isCapped(int $week, int $speciesId, array $servedWeeks, array $frequencies): bool {

		if(array_key_exists($speciesId, $frequencies) === FALSE) {
			return FALSE;
		}

		[$served, $weeks] = self::FREQUENCIES[$frequencies[$speciesId]];

		for($weekFirst = $week - $weeks + 1; $weekFirst <= $week; $weekFirst++) {

			$servedOther = array_filter($servedWeeks[$speciesId], fn(int $weekServed) => ($weekServed !== $week and $weekServed >= $weekFirst and $weekServed < $weekFirst + $weeks));

			if(count($servedOther) >= $served) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Les semaines où la base d'une taille sert chaque espèce à la fréquence choisie,
	 * `[id d'espèce => [semaine, …]]` : ce qu'elle héritera quoi que la règle tire (`deriveWeek()`).
	 * Vide pour une taille sans base, et pour un plan sans fréquence choisie.
	 */
	private static function getInheritedWeeks(array $base, array $frequencies): array {

		$inheritedWeeks = [];

		foreach($base as $week => $lines) {

			foreach(array_column($lines, 'species') as $speciesId) {

				if(array_key_exists($speciesId, $frequencies)) {
					$inheritedWeeks[$speciesId][] = $week;
				}

			}

		}

		return $inheritedWeeks;

	}

	/**
	 * Les paniers composés `$servedWeeks`, et pour chaque espèce de `$inheritedWeeks` les semaines
	 * après `$week` où la base la servira. Seules des espèces à la fréquence choisie y gagnent des
	 * semaines à venir : l'écart des autres se lit sur leur dernier panier (`isSpaced()`), qui doit
	 * rester un panier passé.
	 */
	private static function getServedKnown(int $week, array $servedWeeks, array $inheritedWeeks): array {

		foreach($inheritedWeeks as $speciesId => $weeks) {

			$servedWeeks[$speciesId] = array_merge(
				$servedWeeks[$speciesId] ?? [],
				array_values(array_filter($weeks, fn(int $weekInherited) => $weekInherited > $week))
			);

		}

		return $servedWeeks;

	}

	/**
	 * Une part des paniers rangée aux seuils de `FREQUENCY_FLOORS`, en diversification sous le dernier.
	 */
	private static function getFrequencyByRate(float $rate): string {

		foreach(self::FREQUENCY_FLOORS as $frequency => $floor) {

			if($rate >= $floor) {
				return $frequency;
			}

		}

		return CompositionSpecies::DIVERSIFICATION;

	}

	/**
	 * Ce que la grille sert d'un rang (`getGrid()`), `['served' => int, 'candidate' => int]` : les
	 * semaines où l'espèce est candidate, celles de sa fenêtre qui ont un panier, refusées pour un
	 * autre chou comprises, et parmi elles celles où le panier la sert, correction à la main comprise.
	 * C'est ce que la fréquence courante lit (`getFrequencyCurrent()`).
	 */
	public static function getGridServed(array $row): array {

		$weeks = array_merge(array_keys($row['shares']), $row['refused']);

		return [
			'served' => count(array_intersect(array_keys($row['lines']), $weeks)),
			'candidate' => count($weeks),
		];

	}

	/**
	 * Ce qu'une proposition (`propose()`) sert d'une espèce, `['served' => int, 'candidate' => int]`,
	 * compté comme la grille le compte (`getGridServed()`) : les semaines de la proposition où
	 * l'espèce est candidate, et parmi elles celles dont le panier la porte.
	 */
	public static function getProposalServed(array $proposal, int $speciesId, int $season, ?array $windows, array $source): array {

		$served = 0;
		$candidate = 0;

		foreach($proposal as $week => ['lines' => $lines]) {

			if(in_array($speciesId, self::getOrder($week, $season, $windows, $source), TRUE) === FALSE) {
				continue;
			}

			$candidate++;

			if(in_array($speciesId, array_column($lines, 'species'), TRUE)) {
				$served++;
			}

		}

		return [
			'served' => $served,
			'candidate' => $candidate,
		];

	}

	/**
	 * Les candidates d'une semaine dans l'ordre de l'étape 5.
	 *
	 * `$windows` est la saison de chaque espèce d'un plan, `[id d'espèce => [semaine, …]]`
	 * (`CompositionSpeciesLib::getWindowsByPlan()`, lot r27), sans les espèces retirées, qui n'en ont
	 * pas. **La saison est la porte** : une espèce est candidate sur une semaine de sa saison qu'elle
	 * soit vendue (`isSold()`) et qu'elle ait un prix, celui de la structure (`priced`) ou un relevé de
	 * la communauté, à la semaine ou à la plus proche qui en porte un (`getUnitPrice()`). Ni la
	 * disponibilité de la communauté ni les mois de la mercuriale ne ferment rien : étirer une saison
	 * sur une semaine que la communauté ne récolte pas doit l'ouvrir, sinon le geste mentirait.
	 *
	 * Sans plan (`NULL`), ce sont les étapes 1, 2 et 3 : la disponibilité de la communauté, les mois de
	 * la mercuriale et un relevé de prix à la semaine. C'est la saison de la communauté, qu'un plan
	 * écrit sur ses espèces à son départ (`CompositionPlanLib::create()`) et que le départ compte.
	 *
	 * Une espèce n'est candidate que si elle porte une unité et un prix : une ligne sans prix ne se
	 * valoriserait pas, et sans unité elle n'aurait pas de quantité.
	 *
	 * Le rang a trois étages qui ne se comparent pas entre eux : la part à la semaine, sinon la part
	 * à l'année, sinon le nombre de structures qui récoltent, 0 sur une semaine que la communauté ne
	 * récolte pas. La part à la semaine est celle des paniers partagés quand la paire atteint le seuil
	 * de contributeurs, sinon la part à l'année modulée par le poids du mois dans les ventes de
	 * l'espèce (`aggregate\CompositionSourceAiLib::getFrequency()`) : une espèce qui a une saison de
	 * vente en ligne passe donc au premier étage, à côté des parts mesurées. On classe d'abord par
	 * étage, puis par valeur décroissante, puis par l'espèce lue et par l'espèce du plan : sans ce dernier départage, deux
	 * espèces à rang égal s'ordonneraient selon le hasard de la lecture, et revenir de 14 à 16 € ne
	 * rendrait pas le panier de départ (§6.8).
	 */
	private static function getOrder(int $week, int $season, ?array $windows, array $source): array {

		$ranks = [];

		if($windows === NULL) {

			$month = self::getMonth($season, $week);

			// La disponibilité est rangée par espèce du référentiel : sur la source d'un plan, chacune
			// de ses espèces, variantes comprises (r76), lit celle de l'espèce qu'elle sert.
			$available = $source['availability'][$week] ?? [];

			if($source['plants'] !== []) {

				$availablePlan = [];

				foreach($source['plants'] as $speciesId => $plantId) {

					if(array_key_exists($plantId, $available)) {
						$availablePlan[$speciesId] = $available[$plantId];
					}

				}

				$available = $availablePlan;

			}

			foreach($available as $speciesId => $structures) {

				if(
					(
						array_key_exists($speciesId, $source['seasons']) and
						in_array($month, $source['seasons'][$speciesId], TRUE) === FALSE
					) or
					self::isSold($speciesId, $source) === FALSE or
					self::isSurveyed($week, $speciesId, $source) === FALSE
				) {
					continue;
				}

				$ranks[$speciesId] = self::getRank($week, $speciesId, $structures, $source);

			}

		} else {

			foreach($windows as $speciesId => $weeks) {

				if(
					in_array($week, $weeks, TRUE) === FALSE or
					self::isSold($speciesId, $source) === FALSE or
					(
						isset($source['priced'][$speciesId]) === FALSE and
						self::isSurveyed(self::getWeekSurveyed($week, $speciesId, $source), $speciesId, $source) === FALSE
					)
				) {
					continue;
				}

				// La disponibilité reste rangée par espèce du référentiel : une variante (r76) se range sur
				// les structures qui récoltent l'espèce qu'elle lit, comme son espèce nue.
				$ranks[$speciesId] = self::getRank($week, $speciesId, $source['availability'][$week][self::getPlantId($speciesId, $source)] ?? 0, $source);

			}

		}

		$order = array_keys($ranks);

		// Départagé par l'espèce lue avant l'espèce du plan, pour la raison de `getOrderByDebt()`.
		$plantIds = [];

		foreach($order as $speciesId) {
			$plantIds[$speciesId] = self::getPlantId($speciesId, $source);
		}

		usort($order, fn(int $a, int $b) => [$ranks[$a][0], -$ranks[$a][1], $plantIds[$a], $a] <=> [$ranks[$b][0], -$ranks[$b][1], $plantIds[$b], $b]);

		return $order;

	}

	/**
	 * Le rang d'une candidate (`getOrder()`), `[étage, valeur]`, `$structures` étant le nombre de
	 * structures de la communauté qui la récoltent cette semaine.
	 */
	private static function getRank(int $week, int $speciesId, int $structures, array $source): array {

		if(isset($source['frequency']['byWeek'][$week][$speciesId])) {
			return [0, $source['frequency']['byWeek'][$week][$speciesId]];
		} else if(isset($source['frequency']['byYear'][$speciesId])) {
			return [1, $source['frequency']['byYear'][$speciesId]];
		} else {
			return [2, $structures];
		}

	}

	/**
	 * La part attendue de chaque candidate dans le panier de la semaine, `[id d'espèce => part]`,
	 * rapportée au nombre de lignes : les parts de la semaine somment à `$lines`.
	 *
	 * La part brute est la fréquence de l'espèce dans les paniers, à la semaine (mesurée par les
	 * paniers partagés, ou modulée par la saison des ventes), sinon à l'année, sinon nulle ; ce sont
	 * les deux premiers étages de `getOrder()`. Une part modulée peut dépasser 1 avant la mise à
	 * l'échelle, que le plafond ci-dessous rattrape. Brute, elle ne se compare pas au nombre de
	 * lignes : les parts des candidates d'une semaine somment en moyenne à 3,3 sur 2026 (de 0,9 à 5,7), là où
	 * un panier de 16 € porte 7 lignes. Sans la mise à l'échelle, toutes les dettes descendraient à chaque semaine, et une
	 * espèce sans fréquence, jamais servie, resterait à zéro devant toutes les autres.
	 *
	 * Une semaine où aucune candidate n'a de fréquence partage ses lignes à parts égales. Un panier
	 * qui a plus de lignes que la semaine n'a de candidates met chacune à 1 : la somme reste alors
	 * sous `$lines`, et l'étape 8 ne trouvera de toute façon pas d'espèce à ajouter.
	 */
	private static function getShares(int $week, array $order, int $lines, array $source): array {

		$sharesRaw = [];
		$sharesByPlant = [];
		$speciesByPlant = [];

		foreach($order as $speciesId) {

			$plantId = self::getPlantId($speciesId, $source);

			$sharesRaw[$speciesId] = $source['frequency']['byWeek'][$week][$speciesId] ?? $source['frequency']['byYear'][$speciesId] ?? 0.0;
			$sharesByPlant[$plantId] = ($sharesByPlant[$plantId] ?? 0.0) + $sharesRaw[$speciesId];
			$speciesByPlant[$plantId][] = $speciesId;

		}

		// Une espèce est au panier ou n'y est pas : sa part ne dépasse pas 1, et ce qu'elle
		// laisse se répartit sur les autres au prorata, jusqu'à ce qu'aucune ne déborde plus.
		// L'espèce s'entend ici avec ses variantes (r76) : une seule de ses lignes entre au panier
		// (`isTwinRefused()`), elles se partagent donc une seule place. Chacune recevait sinon une
		// part pleine, et la dette de celles qui perdaient montait sans fin.
		$capped = [];

		do {

			$free = array_diff_key($sharesByPlant, $capped);
			$sum = array_sum($free);
			$left = $lines - count($capped);
			$overflow = FALSE;

			foreach($free as $plantId => $share) {

				$scaled = ($sum > 0) ? $share * $left / $sum : $left / count($free);

				if($scaled > 1.0) {
					$capped[$plantId] = 1.0;
					$overflow = TRUE;
				}

			}

		} while($overflow and count($capped) < count($sharesByPlant));

		$free = array_diff_key($sharesByPlant, $capped);
		$sum = array_sum($free);
		$left = $lines - count($capped);

		$shares = [];

		foreach($order as $speciesId) {

			$plantId = self::getPlantId($speciesId, $source);

			if(isset($capped[$plantId])) {
				$sharePlant = 1.0;
			} else {
				$sharePlant = ($sum > 0) ? $sharesByPlant[$plantId] * $left / $sum : $left / count($free);
			}

			// La place de l'espèce se partage entre ses lignes au prorata de leur part brute, à parts
			// égales quand aucune n'en a. Une espèce sans variante garde sa part telle quelle : le
			// prorata `x × a / a` ne rend pas toujours `x` au dernier bit, et une dette qui bouge d'un
			// bit peut inverser deux candidates à égalité.
			if(count($speciesByPlant[$plantId]) === 1) {
				$shares[$speciesId] = $sharePlant;
			} else if($sharesByPlant[$plantId] > 0) {
				$shares[$speciesId] = $sharePlant * $sharesRaw[$speciesId] / $sharesByPlant[$plantId];
			} else {
				$shares[$speciesId] = $sharePlant / count($speciesByPlant[$plantId]);
			}

		}

		return $shares;

	}

	/**
	 * L'ordre du tirage : les candidates par dette décroissante, la dette d'une espèce étant ce
	 * que la communauté lui attend depuis la première semaine livrée, part de cette semaine
	 * comprise, moins le nombre de paniers où elle est déjà entrée.
	 *
	 * L'ordre fixe par fréquence, joint à l'exclusion des deux semaines précédentes, faisait
	 * tourner la règle sur trois jeux d'espèces : deux paniers à trois semaines d'écart se
	 * recouvraient à 58 % (Jaccard, 16 € sur 2026), contre 11 à 13 % aux écarts qui ne sont pas
	 * des multiples de trois, et 39 % pour deux tirages indépendants. La dette
	 * ramène chaque espèce à sa fréquence sans lui imposer de période.
	 *
	 * À la dette s'ajoute un bruit déterministe, tiré de la saison, de la semaine et de l'espèce :
	 * deux espèces à dette voisine ne se départagent pas toujours dans le même sens, et le retour
	 * reste régulier sans être mécanique. Il est borné à 1 (`NOISE`), soit un panier : deux dettes
	 * qui s'écartent de plus d'un panier ne s'inversent jamais. Le dernier départage, par l'espèce
	 * lue puis par l'espèce du plan, rend l'ordre indépendant de celui de la lecture (§6.8).
	 *
	 * Le bruit et le départage se tirent de l'espèce lue (`getPlantId()`), et non de l'id de l'espèce
	 * du plan (r76) : celui-ci change à chaque « Recommencer » et changeait à la reprise des lignes,
	 * et le tirage d'un même plan doit rester le même. Une variante tire donc le bruit de son espèce
	 * nue, avec laquelle elle n'entre jamais dans un même panier (`isTwinRefused()`).
	 */
	private static function getOrderByDebt(int $season, int $week, array $order, array $shares, array $debts, int $version, array $source): array {

		$priorities = [];
		$plantIds = [];

		foreach($order as $speciesId) {
			$plantIds[$speciesId] = self::getPlantId($speciesId, $source);
			$priorities[$speciesId] = ($debts[$speciesId] ?? 0.0) + $shares[$speciesId] + self::getNoise($season, $week, $plantIds[$speciesId], $version);
		}

		usort($order, fn(int $a, int $b) => [-$priorities[$a], $plantIds[$a], $a] <=> [-$priorities[$b], $plantIds[$b], $b]);

		return $order;

	}

	/**
	 * Un nombre de `[0, NOISE]` qui ne dépend que de la saison, de la semaine, de l'espèce et du
	 * tirage de la formule : recomposer le même plan rend le même panier, et un autre tirage en rend
	 * un autre sans rien changer d'autre à la règle.
	 *
	 * Le tirage 0 garde la chaîne d'avant le rejeu, sans suffixe : les formules que personne n'a
	 * rejouées ne bougent pas. Mesuré sur 20 tirages de la structure 7 (cadrage du rejeu), le tirage
	 * suivant change au moins une espèce dans 95 à 98 % des semaines, et une espèce y reçoit en
	 * moyenne 0,3 panier de plus ou de moins sur la saison : la dette tient la fréquence, le bruit ne
	 * joue que sur la queue. Monter `NOISE` ne renouvellerait presque rien de plus et doublerait cet
	 * écart.
	 *
	 * Le tirage n'est pas uniforme : `(1 - cos(πu)) / 2` porte l'essentiel de sa masse près des
	 * deux bornes. Une espèce à moitié des paniers alterne sinon un panier sur deux, parce que sa
	 * dette oscille d'un demi-panier de part et d'autre de ses voisines et qu'un bruit uniforme
	 * inverse rarement un tel écart. Mesuré sur dix saisons à 12 €, à amplitude égale de 1 et avant
	 * la relance du nombre de lignes à l'étape 8 : en uniforme, deux paniers à deux semaines d'écart
	 * se recouvrent à 28 % contre 25 % d'une semaine à l'autre, soit une période de deux semaines ;
	 * aux bornes, 26 % contre 27 %.
	 */
	private static function getNoise(int $season, int $week, int $plantId, int $version): float {

		$hash = $season.'-'.$week.'-'.$plantId.(($version > 0) ? '-'.$version : '');

		return (1 - cos(M_PI * crc32($hash) / 0xFFFFFFFF)) / 2 * self::NOISE;

	}

	/**
	 * Le premier nombre de lignes, calculé entre l'étape 5 et l'étape 6 : le prix visé divisé par
	 * la valeur médiane (haute) d'une mise au panier sur les douze premières candidates, ramené
	 * entre les bornes de la formule (`linesMin` et `linesMax`, r11).
	 *
	 * Une valeur médiane nulle n'existe pas dans les données (un prix vient d'une vente, une
	 * quantité médiane au seuil vaut au moins 0,1), mais la division n'a pas à le supposer : le
	 * quotient tend alors vers l'infini, et la borne haute est ce qu'il rend.
	 *
	 * Le prix de la structure remplace la médiane quand elle en a un. La médiane, elle, est prise
	 * même dispersée : le filtre de dispersion ne décide que de ce qu'une ligne vaut, et
	 * l'échantillon n'en tire qu'un ordre de grandeur.
	 *
	 * Une espèce dont la structure a choisi l'unité n'a pas toujours de relevé dans cette unité : elle
	 * est candidate dès que la communauté la relève dans la sienne (`isSurveyed()`), et sa médiane est
	 * alors celle de l'unité de la communauté, convertie par le poids (lot r14).
	 */
	private static function getLinesStart(float $price, int $linesMin, int $linesMax, int $week, array $order, array $source, array $prices): int {

		$values = [];

		foreach(array_slice($order, 0, self::SAMPLE_SIZE) as $speciesId) {

			$unit = $source['units'][$speciesId];

			// Une candidate sans relevé à la semaine, sur une saison étirée au-delà de la récolte de la
			// communauté (lot r27), se chiffre au relevé le plus proche, comme sa ligne (`getUnitPrice()`).
			$weekSurveyed = self::getWeekSurveyed($week, $speciesId, $source);

			if(array_key_exists($speciesId, $prices)) {
				$unitPrice = $prices[$speciesId]['weeks'][$week];
			} else if(isset($source['prices'][$weekSurveyed][$speciesId][$unit['unit']])) {
				$unitPrice = $source['prices'][$weekSurveyed][$speciesId][$unit['unit']]['median'];
			} else {
				$unitPrice = $source['prices'][$weekSurveyed][$speciesId][$unit['community']['unit']]['median'] * $unit['community']['ratio'];
			}

			$values[] = $unit['quantity'] * $unitPrice;

		}

		sort($values);

		$value = $values[intdiv(count($values), 2)];

		if($value <= 0) {
			return $linesMax;
		}

		return max($linesMin, min($linesMax, (int)round($price / $value)));

	}

	/**
	 * L'étape 6 : le tirage de `$lines` espèces dans l'ordre de la dette (`getOrderByDebt()`), et
	 * la valeur du panier qui en sort avant ajustement.
	 *
	 * Il n'écarte plus les espèces servies en `w - 1` ou `w - 2` : une espèce qui vient d'entrer
	 * au panier a soldé une part de sa dette et recule d'elle-même. L'exclusion, gardée ne
	 * serait-ce que pour `w - 1`, forçait au contraire une alternance : à 16 € sur 2026, deux
	 * paniers à deux semaines d'écart se recouvraient à 55 %, contre 8 % d'une semaine à l'autre
	 * (mesuré le 21/09, avant que la fréquence ne compte une espèce qu'une fois par panier).
	 *
	 * Le plafond joue pendant le tirage : aucun organe au-delà de la moitié des lignes visées. Il
	 * cède devant le plancher de lignes de la formule (`$linesMin`, r70) : un tirage qui finit en
	 * deçà se complète jusqu'au plancher par les candidates suivantes dans l'ordre de la dette, sans
	 * plafond par organe. Sans cela, une semaine d'avril à deux feuilles et une racine restait à trois
	 * lignes sous un plancher de quatre, la troisième feuille candidate refusée par le plafond de
	 * deux, et la relance à cinq lignes gardait le même plafond. Seul le manque de candidates
	 * laisse encore une semaine sous le plancher.
	 *
	 * Le plancher d'organes joue ensuite, à partir de 4 lignes prises : pour chaque organe absent, la première
	 * candidate de cet organe dans l'ordre remplace la dernière ligne d'un organe qui en
	 * compte plusieurs. Quand aucune candidate ne porte l'organe, ou qu'aucune ligne ne peut lui
	 * céder la place, le plancher est relâché pour cet organe. Une espèce sans organe ne compte ni
	 * au plafond ni au plancher, et ne cède pas sa place : elle n'équilibre rien, elle ne
	 * déséquilibre rien non plus.
	 *
	 * Un seul chou par panier (`CABBAGE`), au tirage comme au plancher : un second chou est sauté
	 * comme une espèce qui dépasserait le plafond, et ne remplace une ligne au plancher que si c'est
	 * le chou déjà pris qui lui cède sa place.
	 *
	 * Une seule ligne par espèce et ses variantes (r76, `isTwinRefused()`) : la variante d'une espèce
	 * déjà prise est sautée comme un second chou, et réciproquement.
	 */
	private static function draw(int $lines, int $linesMin, int $week, array $order, array $source, array $prices): array {

		$organs = $source['organs'];
		$ceiling = intdiv($lines, 2);

		$taken = [];
		$byOrgan = [\plant\Plant::LEAF => 0, \plant\Plant::ROOT => 0, \plant\Plant::FRUIT => 0];
		$relaxedFloor = [];

		foreach($order as $speciesId) {

			if(count($taken) >= $lines) {
				break;
			}

			$organ = $organs[$speciesId];

			if($organ !== NULL and $byOrgan[$organ] >= $ceiling) {
				continue;
			}

			if(self::isBasketRefused($speciesId, $taken, $source)) {
				continue;
			}

			$taken[] = $speciesId;

			if($organ !== NULL) {
				$byOrgan[$organ]++;
			}

		}

		foreach($order as $speciesId) {

			if(count($taken) >= $linesMin) {
				break;
			}

			if(in_array($speciesId, $taken, TRUE) or self::isBasketRefused($speciesId, $taken, $source)) {
				continue;
			}

			$taken[] = $speciesId;

			if($organs[$speciesId] !== NULL) {
				$byOrgan[$organs[$speciesId]]++;
			}

		}

		if(count($taken) >= self::FLOOR_FROM) {

			foreach(array_keys($byOrgan) as $organ) {

				if($byOrgan[$organ] > 0) {
					continue;
				}

				$position = NULL;

				foreach(array_reverse($taken, TRUE) as $index => $speciesId) {

					$organTaken = $organs[$speciesId];

					if($organTaken !== NULL and $byOrgan[$organTaken] > 1) {
						$position = $index;
						break;
					}

				}

				if($position === NULL) {
					$relaxedFloor[] = $organ;
					continue;
				}

				// La candidate se cherche sur le panier sans la ligne qui lui cède sa place : un chou
				// peut en remplacer un autre, pas s'y ajouter.
				$remaining = $taken;
				unset($remaining[$position]);

				$found = NULL;

				foreach($order as $speciesId) {

					if(
						in_array($speciesId, $taken, TRUE) === FALSE and
						$organs[$speciesId] === $organ and
						self::isBasketRefused($speciesId, $remaining, $source) === FALSE
					) {
						$found = $speciesId;
						break;
					}

				}

				if($found === NULL) {
					$relaxedFloor[] = $organ;
					continue;
				}

				$byOrgan[$organs[$taken[$position]]]--;
				$byOrgan[$organ] = 1;
				$taken[$position] = $found;

			}

		}

		$value = 0.0;

		foreach($taken as $speciesId) {

			$unitPrice = self::getUnitPrice($week, $speciesId, $source, $prices);

			if($unitPrice !== NULL) {
				$value += $source['units'][$speciesId]['quantity'] * $unitPrice;
			}

		}

		return [
			'species' => $taken,
			'value' => $value,
			'relaxedFloor' => $relaxedFloor,
		];

	}

	/**
	 * La valeur du panier tel qu'il sera proposé, au centime et telle que l'écran l'affichera :
	 * facteur borné appliqué, quantités arrondies au pas de l'unité, puis `getLineValue()` et
	 * `getWeekValue()`.
	 */
	private static function getValueProposed(float $price, array $basket, int $week, array $source, array $prices, array $steps): float {

		$factor = self::getFactorBounded($price, $basket['value']);
		$values = [];

		foreach($basket['species'] as $speciesId) {

			$values[] = self::getLineValue(self::getQuantity($source['units'][$speciesId], $factor, self::getStep($speciesId, $source, $steps)), self::getUnitPrice($week, $speciesId, $source, $prices));

		}

		return self::getWeekValue($values);

	}

	/**
	 * Le stock que le plan de culture prévoit pour la formule `$eCompositionFormula`, que `propose()`
	 * suit semaine après semaine (lot r23) : `['planned' => getPlanned(), 'needs' => getNeeds() des
	 * autres tailles du plan, 'kilos' => [id d'espèce => ?float], 'baskets' => int, 'shared' => [id
	 * de la variante => id de l'espèce nue]]`, où `kilos` est le poids d'une unité de l'espèce au
	 * panier (`getUnitKilos()`). Une variante du plan puise dans le stock de l'espèce nue qu'elle
	 * décline, dans ses kilos à elle, et son besoin dans les autres tailles s'ajoute à celui de
	 * l'espèce nue (`getStockKey()`).
	 *
	 * Vide tant que le plan n'a pas été synchronisé sur le plan de culture
	 * (`CompositionPlan::COMMUNITY`) : les saisons y viennent de la communauté, et la structure n'a
	 * pas à cultiver ce qu'elle y sert. Une fois synchronisé (`CompositionPlan::CULTIVATION`,
	 * `CompositionPlanLib::synchronize()`), le stock de ce plan est ce qu'une espèce qu'il date
	 * peut donner (`getStockCaps()`).
	 *
	 * Les autres tailles prennent au stock leurs semaines écrites, corrections comprises : la taille
	 * qu'on compose passe après elles. Refaire toutes les tailles d'un plan les compose donc dans
	 * l'ordre de `CompositionFormulaLib::getByPlanForCompose()`, chacune sur ce que les précédentes
	 * ont laissé et sur les semaines que les suivantes portaient encore.
	 *
	 * `$cCompositionSpeciesPriced` sont les espèces du plan chiffrées sur `$source`
	 * (`CompositionSpeciesLib::getWithPrices()`), que l'écran a déjà (chantier Performances, P2) :
	 * le stock ne les relit pas. `$cCompositionFormulaOther` sont les autres tailles du plan, chacune
	 * avec ses semaines écrites (`cCompositionWeek`), que l'écran a lues pour ses onglets (P6) et
	 * qu'une composition lit au moment de composer (`getFormulasOtherWithLines()`), les tailles
	 * précédentes venant d'écrire les leurs. `$cultivations` sont les cultures de la structure
	 * (`getCultivations()`), que l'écran a lues (P8), et qui se lisent ici sinon.
	 */
	public static function getStock(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, array $source, \Collection $cCompositionSpeciesPriced, \Collection $cCompositionFormulaOther, ?array $cultivations = NULL): array {

		$eCompositionPlan->expects(['id', 'structure', 'season', 'reference', 'weeks']);
		$eCompositionFormula->expects(['id', 'baskets']);

		if($eCompositionPlan['reference'] !== CompositionPlan::CULTIVATION) {
			return [];
		}

		$planned = self::getPlanned($eCompositionPlan['season'], $source, $cCompositionSpeciesPriced, $cultivations ?? self::getCultivations($eCompositionPlan['structure'], $eCompositionPlan['season']));

		// Une variante puise dans le stock de l'espèce nue qu'elle décline, sous laquelle les cultures
		// sont rangées (`getPlanned()`) : décidé le 03/10/2026 pour les déclinaisons r71 et repris par
		// r76, « stock commun, sur la clé de la référence, avec les kilos de chaque ligne ». La carotte
		// en botte et la carotte au kilo sont le même légume, et l'une prend ce que l'autre laisse.
		$shared = [];

		foreach($cCompositionSpeciesPriced->getKeys() as $speciesId) {

			$speciesBare = self::getSpeciesBare(self::getPlantId($speciesId, $source), $source);

			if(
				$speciesBare !== NULL and
				$speciesBare !== $speciesId and
				array_key_exists($speciesBare, $planned)
			) {
				$shared[$speciesId] = $speciesBare;
			}

		}

		$kilos = [];

		foreach(array_merge(array_keys($planned), array_keys($shared)) as $speciesId) {
			$kilos[$speciesId] = self::getUnitKilos($speciesId, $source['units'][$speciesId]['fqn'], $source, $cCompositionSpeciesPriced);
		}

		$needs = self::getNeeds($cCompositionFormulaOther, $source, $cCompositionSpeciesPriced);

		foreach($shared as $speciesId => $speciesBare) {

			if(array_key_exists($speciesId, $needs['needs'])) {

				foreach($needs['needs'][$speciesId] as $week => $need) {
					$needs['needs'][$speciesBare][$week] ??= 0.0;
					$needs['needs'][$speciesBare][$week] += $need;
				}

				unset($needs['needs'][$speciesId]);

			}

			if(array_key_exists($speciesId, $needs['unknown'])) {
				$needs['unknown'][$speciesBare] ??= [];
				$needs['unknown'][$speciesBare] += $needs['unknown'][$speciesId];
				unset($needs['unknown'][$speciesId]);
			}

		}

		return [
			'planned' => $planned,
			'shared' => $shared,
			'needs' => $needs,
			'kilos' => $kilos,
			'baskets' => $eCompositionFormula['baskets'],
		];

	}

	/**
	 * Les tailles de `$cCompositionFormula` autres que `$eCompositionFormula` : celles dont le stock
	 * déduit ce qu'elles servent (`getStock()`), jamais la taille qu'on compose ou qu'on affiche. Une
	 * seule définition pour l'écran, qui les tire de ses onglets (`getLean()`), et pour la composition
	 * (`getFormulasOtherWithLines()`).
	 */
	public static function getFormulasOther(\Collection $cCompositionFormula, CompositionFormula $eCompositionFormula): \Collection {
		return $cCompositionFormula->find(fn(CompositionFormula $e) => $e['id'] !== $eCompositionFormula['id']);
	}

	/**
	 * Les tailles du plan autres que `$eCompositionFormula` (`getFormulasOther()`), chacune avec ses
	 * semaines écrites et leurs lignes (`cCompositionWeek`), telles que le stock les lit (`getStock()`),
	 * l'espèce de chaque ligne prise dans `$cCompositionSpecies`, les espèces du plan
	 * (`CompositionSpeciesLib::getByPlan()`).
	 */
	public static function getFormulasOtherWithLines(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, \Collection $cCompositionSpecies): \Collection {

		$cCompositionFormulaOther = self::getFormulasOther(CompositionFormulaLib::getByPlan($eCompositionPlan), $eCompositionFormula);

		foreach($cCompositionFormulaOther as $eCompositionFormulaOther) {
			$eCompositionFormulaOther['cCompositionWeek'] = CompositionWeekLib::getByFormulaWithLines($eCompositionFormulaOther, $cCompositionSpecies);
		}

		return $cCompositionFormulaOther;

	}

	/**
	 * Les lots de réserve de chaque espèce du plan de culture avant la première semaine de la saison
	 * (`getLotsCarried()`), `[id d'espèce => lots]`, vide sans stock.
	 */
	private static function getStockLots(array $stock, array $source): array {

		if($stock === []) {
			return [];
		}

		$lots = [];

		foreach($stock['planned'] as $speciesId => $plan) {
			$lots[$speciesId] = self::getLotsCarried($plan, $source['storage'][$speciesId]['weeks']);
		}

		return $lots;

	}

	/**
	 * Les lots de réserve de la semaine `$weekTo`, depuis ceux de la semaine `$weekFrom` : chaque
	 * semaine de la saison entre les deux, livrée par cette taille ou non, fait entrer sa récolte et
	 * sortir les lots passés, puis les autres tailles y prennent ce que leurs semaines écrites servent.
	 * Un besoin qui ne se pèse pas ne prend rien ; un besoin que le stock ne couvre pas le vide.
	 */
	private static function advanceStock(array $stock, array $source, array $lots, int $weekFrom, int $weekTo): array {

		foreach(self::getSeasonWeeks() as $week) {

			if($week <= $weekFrom or $week > $weekTo) {
				continue;
			}

			foreach($lots as $speciesId => $lotsPlant) {

				$lotsPlant = self::advanceLots($lotsPlant, $stock['planned'][$speciesId], $source['storage'][$speciesId]['weeks'], $week);

				$need = $stock['needs']['needs'][$speciesId][$week] ?? 0.0;

				if(isset($stock['needs']['unknown'][$speciesId][$week]) === FALSE) {
					$lotsPlant = self::takeFromLotsOrEmpty($lotsPlant, $need);
				}

				$lots[$speciesId] = $lotsPlant;

			}

		}

		return $lots;

	}

	/**
	 * Ce que les lignes `$lines` d'une semaine de cette taille prennent au stock, par ses paniers. Une
	 * ligne dans une autre unité que celle de l'espèce au panier (une ligne héritée, lot r14) ne se
	 * pèse pas ici et ne prend rien, comme un besoin qui ne se pèse pas (`advanceStock()`).
	 */
	private static function takeStock(array $stock, array $source, array $lots, array $lines): array {

		// Un plan qui n'est pas synchronisé sur le plan de culture n'a pas de stock (`getStock()`) :
		// rien à prendre, et pas de variante à rapporter à son espèce nue.
		if($stock === []) {
			return $lots;
		}

		foreach($lines as $line) {

			$speciesId = $line['species'];
			$stockKey = self::getStockKey($stock, $speciesId);

			if(
				array_key_exists($stockKey, $lots) === FALSE or
				$line['unit'] !== $source['units'][$speciesId]['unit'] or
				$stock['kilos'][$speciesId] === NULL
			) {
				continue;
			}

			$lots[$stockKey] = self::takeFromLotsOrEmpty($lots[$stockKey], $line['quantity'] * $stock['baskets'] * $stock['kilos'][$speciesId]);

		}

		return $lots;

	}

	/**
	 * Le stock où l'espèce `$speciesId` puise : celui de l'espèce nue pour une variante (`getStock()`),
	 * le sien sinon.
	 */
	private static function getStockKey(array $stock, int $speciesId): int {
		return array_key_exists($speciesId, $stock['shared']) ? $stock['shared'][$speciesId] : $speciesId;
	}

	/**
	 * `takeFromLots()` quand les lots couvrent `$kilos`, sinon plus rien : un besoin qui dépasse le
	 * stock le vide.
	 */
	private static function takeFromLotsOrEmpty(array $lots, float $kilos): array {
		return self::isLotsEnough(self::getLotsKilos($lots), $kilos) ? self::takeFromLots($lots, $kilos) : [];
	}

	/**
	 * Ce que le stock de la semaine `$week` (`$lots`, déjà avancés à cette semaine par
	 * `advanceStock()`) donne à chaque panier, dans l'unité de l'espèce au panier
	 * et arrondi au pas inférieur, `[id d'espèce => ?float]` : NULL quand il ne donne pas le minimum
	 * de l'espèce, qui n'est alors pas candidate. Une espèce absente ne se plafonne pas : sans stock,
	 * sous `CompositionPlan::COMMUNITY`, quand le poids de son unité n'est pas connu, quand une récolte
	 * qui ne se chiffre pas est encore au stock (`isStockUnknown()`), ou quand une autre taille sert
	 * cette semaine une ligne qui ne se pèse pas, ou quand le plan de culture ne la date pas
	 * (`isDated()`) : il ne dit alors rien de ce qu'elle donne, et la plafonner à zéro viderait les
	 * paniers d'un plan synchronisé sur un plan de culture qui ne date rien (arbitrage r27 du 28/09).
	 */
	private static function getStockCaps(array $stock, array $source, array $steps, array $lots, int $week): array {

		if($stock === []) {
			return [];
		}

		$caps = [];

		foreach(array_keys($source['units']) as $speciesId) {

			$stockKey = self::getStockKey($stock, $speciesId);

			if(
				array_key_exists($stockKey, $stock['planned']) === FALSE or
				self::isDated($stock['planned'][$stockKey]) === FALSE or
				$stock['kilos'][$speciesId] === NULL or
				self::isStockUnknown($stock['planned'][$stockKey], $source['storage'][$stockKey]['weeks'], $week) or
				isset($stock['needs']['unknown'][$stockKey][$week])
			) {
				continue;
			}

			$available = self::getLotsKilos($lots[$stockKey]) / $stock['baskets'] / $stock['kilos'][$speciesId];
			$step = self::getStep($speciesId, $source, $steps);

			$caps[$speciesId] = ($available < $step['minimum'] - self::KILOS_EPSILON) ?
				NULL :
				max($step['minimum'], round(floor(round($available / $step['step'], 6)) * $step['step'], 2));

		}

		return $caps;

	}

	/**
	 * Ce que le stock de la semaine (`getStockCaps()`) laisse à une ligne héritée d'une base (lot
	 * r17) : jamais moins que sa quantité de base, le grand panier contenant le petit, donc le stock
	 * ne borne que le surplus, et une espèce qu'il ne permet plus de servir (`NULL`) reste à sa
	 * quantité de base. Une ligne dans une autre unité que celle de l'espèce au panier (lot
	 * r14) ne se compare pas au stock, qui se compte dans celle-ci.
	 */
	private static function getCapInherited(array $line, array $source, array $caps): float {

		if(
			array_key_exists($line['species'], $caps) === FALSE or
			$line['unit'] !== $source['units'][$line['species']]['unit']
		) {
			return INF;
		}

		return max($line['quantity'], $caps[$line['species']] ?? 0.0);

	}

	/**
	 * Les lignes `$lines` ramenées à ce que le stock donne (`getStockCaps()`), avant l'ajustement au
	 * pas, qui ne les dépasse pas non plus (`getBoundsProposed()`).
	 */
	private static function applyStockCaps(array $lines, array $caps): array {

		foreach($lines as $position => $line) {

			if(isset($caps[$line['species']])) {
				$lines[$position]['quantity'] = min($line['quantity'], $caps[$line['species']]);
			}

		}

		return $lines;

	}

	/**
	 * L'ajustement au pas (lot r23, tranché le 27/09 : « tu dois te rapprocher de la
	 * cible à ±5 % ») : tant que le panier arrondi s'écarte du prix au-delà de `$gap`, une ligne gagne
	 * ou perd un pas, celle qui rapproche le plus la valeur du prix, jusqu'à entrer dans l'écart ou
	 * jusqu'à ce qu'aucun pas ne la rapproche plus. Les espèces ne changent pas, donc la dette non plus.
	 *
	 * Chaque ligne reste dans ses bornes, `$bounds[position] = ['step', 'minimum', 'ceiling']` : pour
	 * une ligne que la règle compose, son pas, son minimum et `STEP_CEILING` fois sa quantité de
	 * référence (`getBoundsProposed()`) ; pour une ligne héritée d'une base, sa quantité de base et
	 * `STEP_CEILING` fois celle-ci (`getBoundsInherited()`). Une ligne sans prix ne compte pas dans la
	 * valeur : aucun de ses pas ne rapproche, elle ne bouge donc pas. Les distances se comparent au
	 * centime, comme `isGap()` : un pas qui ne rapproche que d'un reliquat binaire n'est pas pris, et
	 * à égalité la première ligne du panier passe.
	 *
	 * Le passage s'arrête toujours : chaque pas retenu rapproche la valeur du prix d'un centime au
	 * moins. Rejoué sur le plan 13231 de la structure 7 par l'architecte, il ramène dans l'écart 7 des
	 * 10 semaines qui en sortaient à 18 €, 8 des 9 à 12 €, sans en faire sortir aucune.
	 */
	private static function applyPriceSteps(float $price, int $gap, array $lines, array $bounds): array {

		$getValue = fn(array $lines) => self::getWeekValue(array_map(fn(array $line) => self::getLineValue($line['quantity'], $line['unitPrice']), $lines));
		$getDistance = fn(array $lines) => round(abs($getValue($lines) - $price), 2);

		while(self::isGap($price, $getValue($lines), $gap)) {

			$linesBest = NULL;
			$distanceBest = $getDistance($lines);

			foreach($lines as $position => $line) {

				$bound = $bounds[$position];

				foreach([$bound['step'], -$bound['step']] as $move) {

					$quantity = round($line['quantity'] + $move, 2);

					if($quantity < $bound['minimum'] or $quantity > $bound['ceiling']) {
						continue;
					}

					$linesTry = $lines;
					$linesTry[$position]['quantity'] = $quantity;

					$distance = $getDistance($linesTry);

					if($distance < $distanceBest) {
						$linesBest = $linesTry;
						$distanceBest = $distance;
					}

				}

			}

			if($linesBest === NULL) {
				break;
			}

			$lines = $linesBest;

		}

		return $lines;

	}

	/**
	 * Les bornes de l'ajustement au pas (`applyPriceSteps()`) des lignes que la règle compose, dans
	 * leur ordre : le pas et le minimum de l'espèce (`getStep()`), et `STEP_CEILING` fois sa quantité
	 * de référence, ou moins quand le stock de la semaine donne moins (`$caps`, `getStockCaps()`).
	 */
	private static function getBoundsProposed(array $lines, array $source, array $steps, array $caps): array {

		$bounds = [];

		foreach($lines as $line) {

			$step = self::getStep($line['species'], $source, $steps);

			$bounds[] = [
				'step' => $step['step'],
				'minimum' => $step['minimum'],
				'ceiling' => min($source['units'][$line['species']]['quantity'] * self::STEP_CEILING, $caps[$line['species']] ?? INF),
			];

		}

		return $bounds;

	}

	/**
	 * Les bornes de l'ajustement au pas (`applyPriceSteps()`) des lignes héritées d'une base (lot
	 * r17), dans leur ordre : le pas de la ligne, et entre sa quantité de base, sous laquelle le grand
	 * panier ne contiendrait plus le petit, et `STEP_CEILING` fois celle-ci. La quantité de base tient
	 * lieu de référence : elle est dans l'unité de la ligne, qui n'est pas toujours celle de l'espèce
	 * (lot r14). Le stock de la semaine borne aussi le surplus (`getCapInherited()`).
	 */
	private static function getBoundsInherited(array $inherited, array $source, array $caps): array {

		$bounds = [];

		foreach($inherited as $line) {

			$bounds[] = [
				'step' => $line['step']['step'],
				'minimum' => $line['quantity'],
				'ceiling' => min($line['quantity'] * self::STEP_CEILING, self::getCapInherited($line, $source, $caps)),
			];

		}

		return $bounds;

	}

	/**
	 * La valeur d'une ligne au centime, NULL pour une ligne sans prix. La règle et l'écran
	 * (`CompositionWeekLib::getByFormulaWithLines()`) passent tous deux par ici et par les deux fonctions
	 * suivantes : une semaine comptée au-delà de l'écart de la formule par l'une l'est par l'autre.
	 */
	public static function getLineValue(float $quantity, ?float $unitPrice): ?float {

		if($unitPrice === NULL) {
			return NULL;
		}

		return round($quantity * $unitPrice, 2);

	}

	/**
	 * La somme des lignes, une ligne sans prix comptant pour zéro : la règle ne l'a pas valorisée,
	 * l'écran ne doit pas afficher une valeur qu'elle n'a pas visée.
	 */
	public static function getWeekValue(array $values): float {
		return round(array_sum(array_filter($values, fn(?float $value) => $value !== NULL)), 2);
	}

	/**
	 * L'écart se compare en centimes : en flottants, une semaine à 5 % exactement (16,80 € pour
	 * 16 €) rend 0,05000000000000004 et passerait pour « au-delà de 5 % ».
	 */
	public static function isGap(float $price, float $value, int $gap): bool {
		return (round(abs($value - $price), 2) > self::getGapAmount($price, $gap));
	}

	/**
	 * L'écart maximum `$gap` (en %) d'un prix, en euros au centime : ce que `isGap()` tolère, et ce
	 * que le réglage de l'écart annonce sous son champ.
	 */
	public static function getGapAmount(float $price, int $gap): float {
		return round($price * $gap / 100, 2);
	}

	/**
	 * La quantité de référence multipliée par le facteur, arrondie au pas de l'espèce et jamais sous
	 * son minimum (`roundToStep()`).
	 */
	private static function getQuantity(array $unit, float $factor, array $step): float {
		return self::roundToStep($unit['quantity'] * $factor, $step);
	}

	/**
	 * `$quantity` arrondie au pas `$step` (`getStep()`) et relevée à son minimum : ce que la règle
	 * fait d'une quantité qu'elle propose, et ce qu'un pas ou un minimum réglés après coup font des
	 * lignes déjà proposées (`CompositionLineLib::updateStep()`). Le second `round()` n'arrondit rien :
	 * il efface le reliquat binaire d'un multiple de 0,1 (3 × 0,1 = 0,30000000000000004).
	 */
	public static function roundToStep(float $quantity, array $step): float {
		return max($step['minimum'], round(round($quantity / $step['step']) * $step['step'], 2));
	}

	/**
	 * Le pas et la quantité minimale d'une espèce au panier, `['step' => float, 'minimum' => float]`,
	 * dans l'unité où elle y entre : ceux que la structure a réglés sur l'espèce du plan (`$steps`,
	 * `CompositionSpeciesLib::getStepsByPlan()`), et à défaut le pas de l'unité (`getSource()`,
	 * 0,1 ou 1, et au kilo 0,2 ou 0,1 selon le prix sur la source d'un plan, `applyKiloSteps()`) et
	 * le minimum de la communauté (`getMinimumCommunity()`). Une espèce absente de `$steps`, ou dont
	 * la colonne est nulle, n'a rien réglé : c'est ce que dit le `??`, et non une garde.
	 *
	 * Le minimum borne aussi ce que le stock laisse servir (`getStockCaps()`) : un reste en dessous
	 * ne rend plus l'espèce candidate, là où un minimum d'un pas servait 0,1 kg de poireau.
	 */
	public static function getStep(int $speciesId, array $source, array $steps): array {

		$step = $steps[$speciesId]['step'] ?? $source['units'][$speciesId]['step'];

		return [
			'step' => $step,
			'minimum' => $steps[$speciesId]['minimum'] ?? self::getMinimumCommunity($source['units'][$speciesId]['minimum'], $step),
		];

	}

	/**
	 * La quantité que prend une espèce ajoutée à la main au panier d'une semaine : sa quantité par
	 * défaut (`CompositionSpecies.quantity`, r30, #92, retour du 30/09, « choisir une
	 * valeur par défaut qui est sélectionnée quand on ajoute le produit »), à défaut son minimum
	 * (`getStep()`), comme depuis le lot r24b. Une espèce absente de `$steps`, ou dont la colonne est
	 * nulle, n'a rien réglé : c'est ce que dit le `??`.
	 *
	 * La quantité par défaut passe par l'arrondi de la proposition (`roundToStep()`) : `build()` la
	 * garde au-dessus du minimum et en pas entiers, mais le minimum de la communauté, qui s'applique
	 * quand la structure n'en règle pas, suit les paniers d'une nuit à l'autre
	 * (`getMinimumCommunity()`) et peut la dépasser après coup. Le champ du menu ne propose alors
	 * jamais une quantité que `CompositionLine::properties()` refuserait.
	 */
	public static function getQuantityAdded(int $speciesId, array $source, array $steps): float {

		$step = self::getStep($speciesId, $source, $steps);

		return self::roundToStep($steps[$speciesId]['quantity'] ?? $step['minimum'], $step);

	}

	/**
	 * Le minimum de la communauté (`aggregate\CompositionSourceAiLib::getQuantityByPlantUnit()`,
	 * lot r25) au pas effectif `$step`, arrondi au plus proche et jamais sous un pas : 0,5 kg de
	 * poireau reste 0,5 au pas de 0,1 et devient 0,6 au pas de 0,2, 0,05 kg d'ail devient 0,1. NULL
	 * quand la communauté n'en donne pas dans l'unité où l'espèce entre au panier (`applyUnits()`) :
	 * le minimum est alors d'un pas.
	 *
	 * Il n'est jamais enregistré sur l'espèce : il suit la communauté d'une nuit à l'autre, et le
	 * panneau le montre en attente (`CompositionSpeciesUi`).
	 */
	public static function getMinimumCommunity(?float $minimum, float $step): float {

		if($minimum === NULL) {
			return $step;
		}

		// 0,3 / 0,2 vaut 1,4999999999999998 en binaire : sans le premier arrondi, un minimum au milieu
		// de deux pas tomberait au pas du dessous selon le pas.
		return max($step, round(round(round($minimum / $step, 6)) * $step, 2));

	}

	/**
	 * Le pas et la quantité minimale d'une ligne, dans son unité `$eUnit` : ceux de l'espèce
	 * (`getStep()`) quand la ligne est dans l'unité où l'espèce entre au panier, sinon le pas de
	 * l'unité de la ligne et un minimum d'un pas. Une semaine corrigée garde ses lignes dans l'unité
	 * d'avant quand la structure change celle de l'espèce (lot r14) : le pas et le minimum réglés
	 * se disent dans la nouvelle unité, et une ligne de 0,3 kg ne se compte pas en pièces.
	 */
	public static function getLineStep(int $speciesId, \product\Unit $eUnit, array $source, array $steps): array {

		if(
			array_key_exists($speciesId, $source['units']) and
			$source['units'][$speciesId]['unit'] === $eUnit['id']
		) {
			return self::getStep($speciesId, $source, $steps);
		}

		$step = self::getUnitStep($eUnit['type']);

		return [
			'step' => $step,
			'minimum' => $step,
		];

	}

	/**
	 * `$quantity` est-elle un nombre entier de pas ? Au millième près : un multiple de 0,1 écrit en
	 * décimal n'en est jamais un exact en binaire.
	 */
	public static function isStepMultiple(float $quantity, float $step): bool {
		return abs($quantity / $step - round($quantity / $step)) < 0.001;
	}

	private static function getFactorBounded(float $price, float $value): float {
		return min(self::FACTOR_HIGH, max(self::FACTOR_LOW, self::getFactor($price, $value)));
	}

	/**
	 * `f = prix / valeur`. Un panier dont aucune ligne n'est valorisée a une valeur nulle, et ce
	 * n'est pas un panier bon marché : c'est un panier qu'on ne sait pas chiffrer. Le facteur vaut
	 * alors 1, donc aucune espèce n'est ajoutée et les quantités de référence de la communauté
	 * restent telles quelles (tranché le 21/09, à la recette du lot 5).
	 */
	private static function getFactor(float $price, float $value): float {

		if($value <= 0) {
			return 1.0;
		}

		return $price / $value;

	}

	/**
	 * Le prix d'une espèce pour la semaine, dans l'unité où elle entre au panier : celui de la
	 * structure quand la cascade en trouve un (`getPrices()`), sinon la médiane de la communauté
	 * (`getMedian()`), et pour une espèce dont la structure a choisi l'unité, à défaut de médiane
	 * employable dans cette unité, celle de l'unité de la communauté convertie par le poids
	 * (`getMedianConverted()`, lot r14). Le filtre de dispersion ne s'applique qu'à la médiane : un
	 * prix de la structure est le sien, il ne recouvre pas des produits différents.
	 *
	 * La médiane se lit sur la semaine relevée la plus proche (`getWeekSurveyed()`), qui est la
	 * semaine elle-même quand la communauté y relève l'espèce. Le relevé ne se fait que sur les
	 * semaines où la communauté récolte : une saison étirée au-delà (lot r27), une case servie à la
	 * main (lot r8) ou une semaine que seule la conservation ouvre prennent donc le prix au plus près
	 * de la récolte, plutôt que d'entrer sans valeur au panier. Le filtre de dispersion juge la
	 * médiane ainsi retenue comme toute autre : une semaine relevée mais dispersée reste sans valeur.
	 *
	 * Public pour `CompositionSpeciesLib::update()`, qui réécrit le prix des lignes d'une espèce
	 * quand le sien change : une ligne se chiffre alors comme la règle l'aurait chiffrée.
	 */
	public static function getUnitPrice(int $week, int $speciesId, array $source, array $prices): ?float {

		if(array_key_exists($speciesId, $prices)) {
			return $prices[$speciesId]['weeks'][$week];
		}

		$week = self::getWeekSurveyed($week, $speciesId, $source);

		return self::getMedian($week, $speciesId, $source) ?? self::getMedianConverted($week, $speciesId, $source);

	}

	/**
	 * La médiane de la communauté pour la semaine, dans l'unité où l'espèce entre au panier, `NULL`
	 * quand sa dispersion la rend inemployable (`isValued()`) ou que le relevé n'en a pas.
	 *
	 * Le relevé n'est chiffré que sur les semaines où la communauté récolte l'espèce : une espèce
	 * candidate y a toujours son prix, mais une ligne gardée hors de sa fenêtre (une case allumée à
	 * la main, puis une fenêtre que le relevé de la nuit a déplacée) n'en a pas, et son prix se
	 * relit quand celui de l'espèce change.
	 */
	private static function getMedian(int $week, int $speciesId, array $source): ?float {
		return self::getMedianByUnit($week, $speciesId, $source['units'][$speciesId]['unit'], $source);
	}

	private static function getMedianByUnit(int $week, int $speciesId, int $unitId, array $source): ?float {

		if(isset($source['prices'][$week][$speciesId][$unitId]) === FALSE) {
			return NULL;
		}

		$unitPrice = $source['prices'][$week][$speciesId][$unitId];

		return self::isValued($unitPrice) ? $unitPrice['median'] : NULL;

	}

	/**
	 * La médiane de la communauté dans l'unité de la communauté, pour une espèce dont la structure a
	 * choisi une autre unité (lot r14, `applyUnits()`), NULL pour toute autre espèce, ou quand le
	 * poids ne permet pas de convertir.
	 */
	private static function getMedianCommunity(int $week, int $speciesId, array $source): ?float {

		$community = $source['units'][$speciesId]['community'];

		if($community === NULL or $community['ratio'] === NULL) {
			return NULL;
		}

		return self::getMedianByUnit($week, $speciesId, $community['unit'], $source);

	}

	/**
	 * `getMedianCommunity()` convertie dans l'unité choisie : le dernier recours de la cascade, quand
	 * la semaine n'a de médiane employable que dans l'unité de la communauté.
	 */
	private static function getMedianConverted(int $week, int $speciesId, array $source): ?float {

		$median = self::getMedianCommunity($week, $speciesId, $source);

		return ($median === NULL) ? NULL : round($median * $source['units'][$speciesId]['community']['ratio'], 2);

	}

	/**
	 * La communauté relève-t-elle un prix de l'espèce cette semaine, dans son unité au panier, ou dans
	 * l'unité de la communauté quand la structure en a choisi une autre et que le poids la convertit
	 * (lot r14) ? Un relevé dispersé compte : le filtre de dispersion juge la médiane, pas le relevé.
	 */
	public static function isSurveyed(int $week, int $speciesId, array $source): bool {

		$unit = $source['units'][$speciesId];

		return (
			isset($source['prices'][$week][$speciesId][$unit['unit']]) or
			(
				$unit['community'] !== NULL and
				$unit['community']['ratio'] !== NULL and
				isset($source['prices'][$week][$speciesId][$unit['community']['unit']])
			)
		);

	}

	/**
	 * La semaine du relevé la plus proche de `$week` où la communauté chiffre l'espèce (`isSurveyed()`),
	 * `$week` elle-même quand elle l'est, la plus petite des deux à distance égale, et `$week` quand
	 * aucune ne l'est. Le relevé range ses prix par semaine de l'année, toutes années confondues : la
	 * semaine 52 est donc voisine de la semaine 1, et la distance se compte en tournant.
	 *
	 * La semaine se cherche sur la présence d'un relevé, et non sur une médiane employable : chercher
	 * plus loin qu'une semaine relevée mais dispersée contournerait le filtre de dispersion
	 * (`isValued()`), que la proposition applique à la même espèce la même semaine.
	 */
	private static function getWeekSurveyed(int $week, int $speciesId, array $source): int {

		if(self::isSurveyed($week, $speciesId, $source)) {
			return $week;
		}

		$weeks = self::getSeasonWeeks();
		$count = count($weeks);

		// Appelée pour chaque candidate de chaque semaine (`getOrder()`), la recherche avance par
		// distance croissante au lieu de trier les 52 semaines, la plus petite des deux à distance égale.
		foreach(range(1, intdiv($count, 2)) as $distance) {

			$weeksNear = array_map(fn(int $weekNear) => ($weekNear - 1 + $count) % $count + 1, [$week - $distance, $week + $distance]);
			sort($weeksNear);

			foreach($weeksNear as $weekNear) {

				if(self::isSurveyed($weekNear, $speciesId, $source)) {
					return $weekNear;
				}

			}

		}

		return $week;

	}

	/**
	 * Le prix d'une espèce pour la semaine et d'où il vient, tel que son panneau l'écrit
	 * (lot M, r22e) : le prix que la règle fige sur ses lignes (`getUnitPrice()`), la source de la cascade
	 * qui le donne, `PRICE_NATIONAL` pour la médiane de la communauté. L'écran ne met plus cette
	 * médiane en regard du prix (verdict sur la planche r22, aucune mention de la
	 * mercuriale générale dans ce projet) : elle ne sort pas d'ici.
	 *
	 * Un prix converti depuis l'unité de la communauté (`PRICE_CONVERTED`, lot r14) porte sous
	 * `converted` le prix d'origine et d'où il vient, la cascade de la structure ou la médiane
	 * nationale, que le formulaire du prix écrit avec le calcul ; NULL pour tout autre prix.
	 *
	 * Rend `['unitPrice' => ?float, 'from' => PRICE_*, 'week' => int,
	 * 'converted' => ?['unitPrice' => ?float, 'from' => PRICE_*]]`.
	 */
	public static function getPriceReference(int $week, int $speciesId, array $source, array $prices): array {

		$weekSurveyed = self::getWeekSurveyed($week, $speciesId, $source);

		$median = self::getMedian($weekSurveyed, $speciesId, $source);
		$medianCommunity = self::getMedianCommunity($weekSurveyed, $speciesId, $source);

		if(array_key_exists($speciesId, $prices)) {

			$from = $prices[$speciesId]['from'];

			$converted = ($from === self::PRICE_CONVERTED) ?
				['unitPrice' => $prices[$speciesId]['community']['weeks'][$week], 'from' => $prices[$speciesId]['community']['from']] :
				NULL;

		} else if($median === NULL and $medianCommunity !== NULL) {
			$from = self::PRICE_CONVERTED;
			$converted = ['unitPrice' => $medianCommunity, 'from' => self::PRICE_NATIONAL];
		} else {
			$from = self::PRICE_NATIONAL;
			$converted = NULL;
		}

		return [
			'unitPrice' => self::getUnitPrice($week, $speciesId, $source, $prices),
			'from' => $from,
			'week' => $week,
			'converted' => $converted,
		];

	}

	/**
	 * Un prix n'est employé pour valoriser que si son 90e centile reste sous trois fois son 10e :
	 * au-delà, l'unité recouvre des choses différentes (une tomate à la pièce, cerise ou
	 * cœur-de-bœuf), et la médiane ne dit rien. La ligne reste proposée et compte pour zéro.
	 *
	 * La comparaison multiplie `low` au lieu de diviser `high` : un 10e centile nul rend le prix
	 * inemployable sans division par zéro.
	 */
	private static function isValued(array $unitPrice): bool {
		return ($unitPrice['high'] < self::DISPERSION_MAX * $unitPrice['low']);
	}

	/**
	 * Écrit la proposition d'une formule neuve : ses semaines et leurs lignes, sous la formule.
	 *
	 * `quantity` et `quantityProposed` naissent égales, `origin` à `PROPOSED` : c'est l'état d'une
	 * ligne que personne n'a touchée. `unitPrice` fige le prix employé, celui de la structure
	 * (`getPrices()`) ou à défaut la médiane de la communauté, `NULL` quand la dispersion a refusé
	 * cette dernière.
	 *
	 * Le plan retient trois choses de ses espèces : celles qu'on a décochées (`EXCLUDED`), que la
	 * règle ne sert jamais, les fenêtres réglées à la main (`CUSTOM`), hors desquelles elle ne
	 * sert pas l'espèce, et les fréquences choisies (lot L9), que la règle leur fait tenir.
	 *
	 * Une formule qui s'appuie sur une autre (lot r17) lit les semaines écrites de sa base : la base
	 * se compose d'abord (`CompositionFormulaLib::getByPlanForCompose()`).
	 */
	public static function compose(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, array $source, array $prices): void {

		$proposal = self::proposeFormula($eCompositionPlan, $eCompositionFormula, $source, $prices);

		foreach(self::getProposalWeeks($eCompositionFormula, $proposal) as $eCompositionWeek) {

			CompositionWeekLib::create($eCompositionWeek);

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
				CompositionLineLib::create($eCompositionLine);
			}

		}

	}

	/**
	 * La proposition d'une formule écrite (`propose()`), sur les réponses du plan telles qu'elles
	 * sont et sur les semaines écrites de sa base (lot r17) : ce que `compose()` écrit, et ce dont
	 * `recomposeWeeks()` écrit les semaines visées.
	 * Une fois le plan synchronisé sur le plan de culture (`CompositionPlan::CULTIVATION`), le stock
	 * de ce plan la plafonne (`getStock()`, lot r23).
	 *
	 * `$frequencies` remplace les fréquences choisies du plan : l'aperçu du choix « Selon la saison »
	 * (`CompositionSpeciesLib::loadFrequency()`) tire la même proposition sans celle de son espèce,
	 * stock et base compris (lot r23b2). Le stock y coûte 40 à 60 ms, mesurés sur la structure 7.
	 */
	public static function proposeFormula(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, array $source, array $prices, ?array $frequencies = NULL): array {

		$eCompositionPlan->expects(['id', 'structure', 'season', 'reference', 'weeks']);
		$eCompositionFormula->expects(['id', 'base', 'baskets', 'price', 'gap', 'linesMin', 'linesMax', 'version']);

		$steps = CompositionSpeciesLib::getStepsByPlan($eCompositionPlan);

		// Seul le stock lit les espèces chiffrées et les autres tailles, et un plan qui suit la
		// communauté n'en a pas (`getStock()`) : chaque geste qui recompose un tel plan ferait sinon
		// quatre requêtes pour rien, et deux de plus par autre taille.
		if($eCompositionPlan['reference'] === CompositionPlan::CULTIVATION) {
			$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
			$cCompositionSpeciesPriced = CompositionSpeciesLib::getWithPrices($eCompositionPlan, $source, $cCompositionSpecies);
			$cCompositionFormulaOther = self::getFormulasOtherWithLines($eCompositionPlan, $eCompositionFormula, $cCompositionSpecies);
		} else {
			$cCompositionSpeciesPriced = new \Collection();
			$cCompositionFormulaOther = new \Collection();
		}

		return self::propose((float)$eCompositionFormula['price'], $eCompositionFormula['gap'], $eCompositionFormula['linesMin'], $eCompositionFormula['linesMax'], $eCompositionPlan['weeks'], CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan), $eCompositionPlan['season'], $source, $prices, $steps, $eCompositionFormula['version'], $frequencies ?? CompositionSpeciesLib::getFrequenciesByPlan($eCompositionPlan), self::getBaseLines($eCompositionFormula, $source, $steps), self::getStock($eCompositionPlan, $eCompositionFormula, $source, $cCompositionSpeciesPriced, $cCompositionFormulaOther));

	}

	/**
	 * Refait la proposition d'une formule entière : ses semaines partent, la règle en écrit de
	 * nouvelles au prix et sur les réponses du plan tels qu'ils sont maintenant.
	 *
	 * Les corrections faites à la main (É11) partent avec leurs semaines : c'est ce que « Régler les
	 * paniers » fait confirmer avant de s'appliquer (`CompositionFormulaUi::getRecomposeLoss()`).
	 * Les semaines livrées et les exclusions se resserreront selon le §6.5 du cadrage : les
	 * premières n'ajouteront ou ne retireront que des semaines, une exclusion ne refera que les
	 * semaines qui portaient l'espèce.
	 *
	 * Suppression et écriture dans une seule transaction : un échec entre les deux laisserait une
	 * formule sans aucune semaine, que l'écran lirait comme une base sans panier partagé.
	 *
	 * Les semaines non corrigées des dérivées de la formule (lot r17) se refont dans la même
	 * transaction, sur les semaines qu'elle vient d'écrire (`recomposeDerived()`).
	 */
	public static function recompose(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, array $source, array $prices): void {

		CompositionWeek::model()->beginTransaction();

			foreach(CompositionWeekLib::getByFormula($eCompositionFormula) as $eCompositionWeek) {
				CompositionWeekLib::delete($eCompositionWeek);
			}

			self::compose($eCompositionPlan, $eCompositionFormula, $source, $prices);

			self::recomposeDerived($eCompositionPlan, CompositionFormulaLib::getByBase($eCompositionFormula), $eCompositionPlan['weeks'], $source, $prices);

		CompositionWeek::model()->commit();

	}

	/**
	 * « Revenir à la proposition » : une semaine seule reprend ce que la règle lui propose et perd
	 * ses corrections (`recomposeWeeks()`). Les autres semaines de la formule ne bougent pas.
	 */
	public static function recomposeWeek(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, CompositionWeek $eCompositionWeek, array $source, array $prices): void {

		$eCompositionWeek->expects(['week']);

		self::recomposeWeeks($eCompositionPlan, $eCompositionFormula, [$eCompositionWeek['week']], $source, $prices);

	}

	/**
	 * Refait les semaines `$weeks` d'une formule, et elles seules : chacune reprend ce que la règle
	 * lui propose. Ses lignes partent, celles que la proposition de la formule lui donne s'écrivent,
	 * `amended` repasse à `FALSE` et `relaxedFloor` reprend ce que la règle a dû relâcher. C'est
	 * « Revenir à la proposition » sur une semaine (`recomposeWeek()`), les semaines non corrigées qui
	 * portaient une espèce qu'on retire (`CompositionSpeciesLib::exclude()`) et celles de tout le plan après une
	 * réponse commune à toutes les tailles (`recomposePlan()`).
	 *
	 * Une semaine que la formule n'a pas encore et que la proposition sert naît ; une semaine que la
	 * proposition ne sert plus, faute de candidate, disparaît, comme `propose()` la saute.
	 *
	 * La proposition se tire pour toute la formule, comme dans `compose()`, et seules les semaines
	 * visées s'en écrivent : la dette d'une espèce court d'une semaine à l'autre (`propose()`), et
	 * une semaine composée seule ne rendrait pas le panier que la formule lui donne.
	 *
	 * Elle se tire sur le relevé d'aujourd'hui : si le cron de 4 h a déplacé l'agrégé depuis que la
	 * formule a été composée, les semaines reviennent à ce que la règle proposerait maintenant.
	 *
	 * Les mêmes semaines de ses dérivées (lot r17), si elles ne sont pas corrigées, se refont ensuite
	 * dans la même transaction (`recomposeDerived()`), sauf avec `$derived` à `FALSE` : une boucle qui
	 * refait déjà toutes les formules du plan, bases d'abord, ne les referait pas deux fois.
	 *
	 * Rend ce que la formule a changé, `[semaine => [id d'espèce] ou NULL]` (lot r28) : pour chaque
	 * semaine dont le panier n'est plus le même, les espèces dont la ligne est née, partie ou a changé
	 * de quantité, d'unité, de prix ou d'origine, une liste vide quand seuls la correction ou le
	 * plancher relâché ont changé, et `NULL` pour une semaine née ou disparue. Une semaine refaite à
	 * l'identique n'y est pas : c'est ce qui permet à l'écran de ne rendre que les colonnes qui
	 * bougent (`CompositionUi::getRecomposed()`). Les dérivées ne sont pas comptées : l'écran ne
	 * montre qu'une taille, et un geste qui refait les dérivées passe par `recomposePlan()`.
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function recomposeWeeks(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, array $weeks, array $source, array $prices, bool $derived = TRUE): array {

		$proposal = self::proposeFormula($eCompositionPlan, $eCompositionFormula, $source, $prices);

		$cCompositionWeekWritten = CompositionWeekLib::getByFormula($eCompositionFormula);

		$changes = [];

		CompositionWeek::model()->beginTransaction();

			foreach($weeks as $week) {

				$eCompositionWeek = $cCompositionWeekWritten->find(fn(CompositionWeek $e) => $e['week'] === $week, limit: 1, default: new CompositionWeek());
				$eCompositionWeekProposed = self::getProposalWeeks($eCompositionFormula, array_intersect_key($proposal, [$week => TRUE]))->first();

				if($eCompositionWeekProposed === NULL) {

					if($eCompositionWeek->notEmpty()) {
						CompositionWeekLib::delete($eCompositionWeek);
						$changes[$week] = NULL;
					}

				} else if($eCompositionWeek->empty()) {

					CompositionWeekLib::create($eCompositionWeekProposed);

					foreach($eCompositionWeekProposed['cCompositionLine'] as $eCompositionLine) {
						CompositionLineLib::create($eCompositionLine);
					}

					$changes[$week] = NULL;

				} else {

					$cCompositionLineWritten = CompositionLineLib::getByWeek($eCompositionWeek);

					$speciesIds = self::getLinesChanged($cCompositionLineWritten, $eCompositionWeekProposed['cCompositionLine']);

					if(
						$speciesIds !== [] or
						$eCompositionWeek['amended'] or
						$eCompositionWeek['relaxedFloor']->get() !== $eCompositionWeekProposed['relaxedFloor']->get()
					) {
						$changes[$week] = $speciesIds;
					}

					foreach($cCompositionLineWritten as $eCompositionLine) {
						CompositionLineLib::delete($eCompositionLine, amend: FALSE);
					}

					$eCompositionWeek['amended'] = FALSE;
					$eCompositionWeek['relaxedFloor'] = $eCompositionWeekProposed['relaxedFloor'];

					CompositionWeekLib::update($eCompositionWeek, ['amended', 'relaxedFloor']);

					foreach($eCompositionWeekProposed['cCompositionLine'] as $eCompositionLine) {

						$eCompositionLine['week'] = $eCompositionWeek;

						CompositionLineLib::create($eCompositionLine);

					}

				}

			}

			if($derived) {
				self::recomposeDerived($eCompositionPlan, CompositionFormulaLib::getByBase($eCompositionFormula), $weeks, $source, $prices);
			}

		CompositionWeek::model()->commit();

		return $changes;

	}

	/**
	 * Les espèces dont la ligne diffère entre les lignes écrites d'une semaine et celles que la
	 * proposition lui donne : née, partie, ou changée de quantité, d'unité, de prix ou d'origine.
	 * Les valeurs proposées se comparent arrondies comme la colonne les écrit (`toolbox.yml`), sans
	 * quoi une ligne réécrite à l'identique compterait pour changée.
	 */
	protected static function getLinesChanged(\Collection $cCompositionLineWritten, \Collection $cCompositionLineProposed): array {

		$line = fn(CompositionLine $e) => [
			$e['unit']['id'],
			round((float)$e['quantity'], 2),
			$e['quantityProposed'] === NULL ? NULL : round((float)$e['quantityProposed'], 2),
			$e['unitPrice'] === NULL ? NULL : round((float)$e['unitPrice'], 4),
			$e['origin'],
		];

		$written = [];

		foreach($cCompositionLineWritten as $eCompositionLine) {
			$written[$eCompositionLine['species']['id']] = $line($eCompositionLine);
		}

		$proposed = [];

		foreach($cCompositionLineProposed as $eCompositionLine) {
			$proposed[$eCompositionLine['species']['id']] = $line($eCompositionLine);
		}

		$speciesIds = [];

		foreach(array_unique(array_merge(array_keys($written), array_keys($proposed))) as $speciesId) {
			if(
				array_key_exists($speciesId, $written) === FALSE or
				array_key_exists($speciesId, $proposed) === FALSE or
				$written[$speciesId] !== $proposed[$speciesId]
			) {
				$speciesIds[] = $speciesId;
			}
		}

		sort($speciesIds);

		return $speciesIds;

	}

	/**
	 * Refait les semaines `$weeks` des dérivées d'une base (lot r17), hors celles qu'on a corrigées à
	 * la main : une semaine dérivée corrigée ne suit plus la base, comme une semaine corrigée que
	 * `recomposePlan()` épargne. « Revenir à la proposition » la recale sur la base telle qu'elle est.
	 *
	 * Une dérivée ne sert de base à aucune formule (`CompositionFormula::properties()`, `chain`) :
	 * rien ne se propage plus loin.
	 */
	public static function recomposeDerived(CompositionPlan $eCompositionPlan, \Collection $cCompositionFormulaDerived, array $weeks, array $source, array $prices): void {

		foreach($cCompositionFormulaDerived as $eCompositionFormulaDerived) {

			$weeksAmended = CompositionWeekLib::getAmendedByFormula($eCompositionFormulaDerived)->getColumn('week');
			$weeksDerived = array_values(array_diff($weeks, $weeksAmended));

			if($weeksDerived !== []) {
				self::recomposeWeeks($eCompositionPlan, $eCompositionFormulaDerived, $weeksDerived, $source, $prices, derived: FALSE);
			}

		}

	}

	/**
	 * Refait toutes les formules du plan après une réponse commune à toutes, les semaines livrées
	 * (`CompositionPlanLib::update()`) ou une espèce ajoutée aux espèces livrées (`CompositionSpeciesLib::add()`),
	 * en épargnant les semaines corrigées, comme les menus de la grille (lot N) :
	 *
	 * - une semaine qu'on ne livre plus part avec ses paniers, corrigée ou non : c'est la seule
	 *   correction qui se perd, et l'écran la fait confirmer avant l'envoi (`getWeeksPanel()`) ;
	 * - une semaine corrigée qu'on livre encore garde ses lignes, même une espèce qu'on vient de
	 *   retirer : une correction faite à la main ne se défait pas sans qu'on le demande ;
	 * - toutes les autres reprennent la proposition (`recomposeWeeks()`), qui fait aussi naître les
	 *   semaines qu'on se met à livrer.
	 *
	 * « Rejouer les paniers » et un prix ou un écart changé refont, eux, la formule entière
	 * (`recompose()`), et le font confirmer.
	 *
	 * Les bases se refont d'abord et les dérivées ensuite (lot r17) : une dérivée lit les semaines
	 * que sa base vient d'écrire. Chaque formule refait ses propres semaines, sans propagation.
	 *
	 * Rend ce que chaque formule a changé, `[id de formule => [semaine => [id d'espèce] ou NULL]]`
	 * (`recomposeWeeks()`), une semaine qu'on ne livre plus comptant pour disparue.
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function recomposePlan(CompositionPlan $eCompositionPlan, array $source, array $prices): array {

		$eCompositionPlan->expects(['id', 'season', 'weeks']);

		$changes = [];

		CompositionWeek::model()->beginTransaction();

			foreach(CompositionFormulaLib::getByPlanForCompose($eCompositionPlan) as $eCompositionFormula) {

				$weeksAmended = [];
				$weeksGone = [];

				foreach(CompositionWeekLib::getByFormula($eCompositionFormula) as $eCompositionWeek) {

					if(in_array($eCompositionWeek['week'], $eCompositionPlan['weeks'], TRUE) === FALSE) {
						CompositionWeekLib::delete($eCompositionWeek);
						$weeksGone[$eCompositionWeek['week']] = NULL;
					} else if($eCompositionWeek['amended']) {
						$weeksAmended[] = $eCompositionWeek['week'];
					}

				}

				$changes[$eCompositionFormula['id']] = $weeksGone + self::recomposeWeeks($eCompositionPlan, $eCompositionFormula, array_values(array_diff($eCompositionPlan['weeks'], $weeksAmended)), $source, $prices, derived: FALSE);

			}

		CompositionWeek::model()->commit();

		return $changes;

	}

	/**
	 * La proposition de `propose()` sous la forme des éléments qu'elle devient : des semaines
	 * `CompositionWeek`, chacune portant ses lignes `CompositionLine` dans `cCompositionLine`, rien
	 * d'écrit. `compose()` les écrit, et `recomposeWeeks()` y lit la proposition de ses semaines.
	 *
	 * L'espèce et l'unité ne portent que leur `id` : c'est tout ce que l'écriture demande.
	 */
	public static function getProposalWeeks(CompositionFormula $eCompositionFormula, array $proposal): \Collection {

		$cCompositionWeek = new \Collection();

		foreach($proposal as $week => $basket) {

			$eCompositionWeek = new CompositionWeek([
				'formula' => $eCompositionFormula,
				'week' => $week,
				'amended' => FALSE,
				'relaxedFloor' => new \Set(array_map(fn(string $organ) => match($organ) {
					\plant\Plant::LEAF => CompositionWeek::LEAF,
					\plant\Plant::ROOT => CompositionWeek::ROOT,
					\plant\Plant::FRUIT => CompositionWeek::FRUIT,
				}, $basket['relaxedFloor'])),
				'cCompositionLine' => new \Collection(),
			]);

			foreach($basket['lines'] as $line) {

				$eCompositionWeek['cCompositionLine'][] = new CompositionLine([
					'week' => $eCompositionWeek,
					'species' => new CompositionSpecies(['id' => $line['species']]),
					'unit' => new \product\Unit(['id' => $line['unit']]),
					'quantity' => $line['quantity'],
					'quantityProposed' => $line['quantity'],
					'unitPrice' => $line['unitPrice'],
					'origin' => CompositionLine::PROPOSED,
				]);

			}

			$cCompositionWeek[] = $eCompositionWeek;

		}

		return $cCompositionWeek;

	}

	/**
	 * Les semaines livrées en périodes continues, `[[début, fin], …]` dans l'ordre : la frise de
	 * l'année en dessine une par ruban, et une semaine isolée est une période d'une semaine.
	 */
	public static function getBands(array $weeks): array {

		sort($weeks);

		$bands = [];

		foreach($weeks as $week) {

			$last = array_key_last($bands);

			if($last !== NULL and $bands[$last][1] === $week - 1) {
				$bands[$last][1] = $week;
			} else {
				$bands[] = [$week, $week];
			}

		}

		return $bands;

	}

	/**
	 * Le chiffre d'affaires d'une taille sur l'année (r12) : la valeur de chaque semaine livrée, telle
	 * que la grille et les cartes l'affichent (`CompositionWeekLib::getByFormulaWithLines()`), par le
	 * nombre de paniers de la taille. C'est ce que les paniers composés rapportent au prix de leurs
	 * légumes, et non le prix visé multiplié par les semaines : l'écart au prix de chaque semaine y
	 * entre, et une ligne sans prix employable y compte pour zéro, comme dans la valeur de sa semaine.
	 *
	 * Se calcule à chaque lecture, comme la valeur des semaines, pour suivre sans rien écrire un prix
	 * réglé, une quantité, le nombre de paniers ou les semaines livrées.
	 */
	public static function getTurnover(\Collection $cCompositionWeek, int $baskets): float {

		return array_sum($cCompositionWeek->getColumn('value')) * $baskets;

	}

	/**
	 * Les semaines de soudure d'une taille (lot r22, point 4, planche r22 question 4, retenue par
	 * le 27/09 « sur chaque carte et un bloc pour la période ») : les semaines livrées où le
	 * plan de culture ne donne pas de quoi atteindre le prix, `[semaine => [id d'espèce, …]]`, les
	 * espèces étant celles que le stock donne encore cette semaine-là, dans l'ordre de la règle.
	 *
	 * Une semaine est de soudure quand aucun réglage de la taille ne la rattrape :
	 * - elle n'a pas de panier, aucune espèce n'étant candidate (sa liste est alors vide) ;
	 * - ou son panier vaut moins que le prix au-delà de l'écart et sert déjà toutes ses candidates.
	 * Une semaine sous le prix qui laisse une candidate de côté n'en est pas une : la règle ou la
	 * personne peut encore l'y mettre, et c'est l'affaire du rail.
	 *
	 * `$stock` est celui que `propose()` suit (`getStock()`), et les semaines écrites de la taille
	 * `$cCompositionWeek`, livrées sur `$weeks` au prix `$price`, y prennent ce qu'elles servent,
	 * corrections comprises : c'est ce que la règle verrait si elle recomposait. Sans stock (`[]`),
	 * tant que le plan n'est pas synchronisé, une taille n'a jamais de soudure : rien ne plafonne ce
	 * que ses candidates donnent.
	 */
	public static function getLeanWeeks(array $weeks, int $season, float $price, \Collection $cCompositionWeek, ?array $windows, array $source, array $steps, array $stock): array {

		if($stock === []) {
			return [];
		}

		$written = [];

		foreach($cCompositionWeek as $eCompositionWeek) {
			$written[$eCompositionWeek['week']] = $eCompositionWeek;
		}

		sort($weeks);

		$lots = self::getStockLots($stock, $source);
		$weekStock = 0;

		$lean = [];

		foreach($weeks as $week) {

			$lots = self::advanceStock($stock, $source, $lots, $weekStock, $week);
			$weekStock = $week;

			$caps = self::getStockCaps($stock, $source, $steps, $lots, $week);

			$order = array_values(array_filter(
				self::getOrder($week, $season, $windows, $source),
				fn(int $speciesId) => (array_key_exists($speciesId, $caps) === FALSE or $caps[$speciesId] !== NULL)
			));

			if(array_key_exists($week, $written) === FALSE) {
				$lean[$week] = $order;
				continue;
			}

			$eCompositionWeek = $written[$week];

			$lines = [];

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
				$lines[] = [
					'species' => $eCompositionLine['species']['id'],
					'unit' => $eCompositionLine['unit']['id'],
					'quantity' => $eCompositionLine['quantity'],
				];
			}

			if(
				$eCompositionWeek['gap'] and
				$eCompositionWeek['value'] < $price and
				array_diff($order, array_column($lines, 'species')) === []
			) {
				$lean[$week] = $order;
			}

			$lots = self::takeStock($stock, $source, $lots, $lines);

		}

		return $lean;

	}

	/**
	 * Les périodes de soudure (`getLeanWeeks()`), `[[semaine, …], …]` : les semaines de soudure qui se
	 * suivent parmi les semaines livrées `$weeks`. Une semaine non livrée entre deux semaines de
	 * soudure ne coupe pas la période, puisqu'elle n'a rien à livrer ; une semaine livrée qui
	 * atteint le prix la coupe.
	 */
	public static function getLeanPeriods(array $lean, array $weeks): array {

		sort($weeks);

		$periods = [];
		$period = [];

		foreach($weeks as $week) {

			if(array_key_exists($week, $lean)) {
				$period[] = $week;
			} else if($period !== []) {
				$periods[] = $period;
				$period = [];
			}

		}

		if($period !== []) {
			$periods[] = $period;
		}

		return $periods;

	}

	/**
	 * Ce que « Conserver plus » propose pour une période de soudure qui finit en semaine `$weekLast`
	 * (planche r22, question 4) : les espèces conservées du plan dont une conservation plus longue
	 * porterait la dernière récolte jusqu'à cette semaine, `[['species' => id d'espèce du plan,
	 * 'weeks' => conservation d'aujourd'hui, 'needed' => conservation qu'il faudrait], …]`, la plus
	 * petite rallonge d'abord.
	 *
	 * `$harvestWeeks` sont les semaines de récolte du plan de culture (`getHarvestWeeks()`), rangées
	 * par espèce du plan comme `$storage` et `$windows` : la conservation est celle de l'espèce, et
	 * une variante (r76), qui partage le stock de son espèce nue, n'en a pas d'autre à allonger. Une
	 * espèce qui ne se conserve pas n'y est pas : ce n'est pas une durée qu'on allonge, c'est une
	 * culture qu'on ajoute. Une espèce retirée du plan n'y est pas non plus, ni une espèce sans copie
	 * dans la structure, dont la fiche est celle que le lien ouvre
	 * (`CompositionSpeciesUi::update()`, « Changer la durée de conservation »). Une espèce dont la
	 * conservation couvre déjà la semaine manque de quantité et non de durée : elle n'y est pas.
	 */
	public static function getStretch(array $harvestWeeks, array $storage, array $windows, int $weekLast): array {

		$stretch = [];

		foreach($harvestWeeks as $speciesId => $weeks) {

			if(
				array_key_exists($speciesId, $storage) === FALSE or
				array_key_exists($speciesId, $windows) === FALSE or
				$storage[$speciesId]['weeks'] === 0 or
				$storage[$speciesId]['plant']->empty()
			) {
				continue;
			}

			$weeksBefore = array_filter($weeks, fn(int $week) => $week <= $weekLast);

			if($weeksBefore === []) {
				continue;
			}

			$needed = $weekLast - max($weeksBefore);

			if($needed > $storage[$speciesId]['weeks']) {
				$stretch[] = [
					'species' => $speciesId,
					'weeks' => $storage[$speciesId]['weeks'],
					'needed' => $needed,
				];
			}

		}

		usort($stretch, fn(array $first, array $second) => [$first['needed'] - $first['weeks'], $first['species']] <=> [$second['needed'] - $second['weeks'], $second['species']]);

		return array_slice($stretch, 0, self::STRETCH_MAX);

	}

	/**
	 * La soudure de la taille ouverte, telle que ses cartes et le bloc de chaque période la montrent
	 * (`CompositionUi::getLean()`, `getWeekCard()`) :
	 * - `weeks` : `[semaine => ['cCompositionSpecies' => espèces du plan qu'il donne encore, 'period'
	 *   => rang de sa période]]` (`getLeanWeeks()`) ;
	 * - `periods` : `[['weeks' => [semaine, …], 'stretch' => [['species' => espèce du plan, 'copy' =>
	 *   la copie de son espèce dans la structure, 'weeks' => int, 'needed' => int], …]], …]`
	 *   (`getLeanPeriods()`, `getStretch()` jusqu'à la dernière semaine de la période).
	 *
	 * Les semaines de récolte, rangées par espèce lue, se rangent sous l'espèce nue du plan qui la lit
	 * (`getSpeciesBare()`), comme le stock.
	 *
	 * Les semaines de récolte et les noms ne se lisent que s'il y a une soudure. Les espèces chiffrées
	 * (`$cCompositionSpeciesPriced`) sont celles de l'écran, que le stock lit (`getStock()`), comme
	 * les autres tailles du plan, que l'écran a lues avec leurs semaines (`cCompositionFormula`, P6),
	 * et les cultures de la structure (`getCultivations()`, P8), d'où le stock et les semaines de
	 * récolte dérivent.
	 */
	public static function getLean(CompositionPlan $eCompositionPlan, CompositionFormula $eCompositionFormula, array $windows, array $source, array $steps, \Collection $cCompositionSpeciesPriced, array $cultivations): array {

		$eCompositionPlan->expects(['structure', 'season', 'weeks', 'cCompositionFormula']);
		$eCompositionFormula->expects(['price', 'cCompositionWeek']);

		$stock = self::getStock($eCompositionPlan, $eCompositionFormula, $source, $cCompositionSpeciesPriced, self::getFormulasOther($eCompositionPlan['cCompositionFormula'], $eCompositionFormula), $cultivations);

		$lean = self::getLeanWeeks($eCompositionPlan['weeks'], $eCompositionPlan['season'], (float)$eCompositionFormula['price'], $eCompositionFormula['cCompositionWeek'], $windows, $source, $steps, $stock);

		if($lean === []) {
			return ['weeks' => [], 'periods' => []];
		}

		$harvestWeeks = [];

		foreach(self::getHarvestWeeks($eCompositionPlan['structure'], $eCompositionPlan['season'], $cultivations) as $plantId => $weeks) {

			$speciesId = self::getSpeciesBare($plantId, $source);

			if($speciesId !== NULL) {
				$harvestWeeks[$speciesId] = $weeks;
			}

		}

		$periods = [];

		foreach(self::getLeanPeriods($lean, $eCompositionPlan['weeks']) as $weeks) {
			$periods[] = [
				'weeks' => $weeks,
				'stretch' => self::getStretch($harvestWeeks, $source['storage'], $windows, max($weeks)),
			];
		}

		$speciesIds = array_merge(...array_values($lean), ...array_map(fn(array $period) => array_column($period['stretch'], 'species'), $periods));

		$cCompositionSpecies = ($speciesIds === []) ?
			new \Collection() :
			CompositionSpecies::model()
				->select(['id', 'name', 'plant' => ['name']])
				->whereId('IN', array_unique($speciesIds))
				->getCollection(NULL, NULL, 'id');

		$weeksLean = [];

		foreach($periods as $position => $period) {

			foreach($period['stretch'] as $index => $stretch) {
				$periods[$position]['stretch'][$index]['species'] = $cCompositionSpecies[$stretch['species']];
				$periods[$position]['stretch'][$index]['copy'] = $source['storage'][$stretch['species']]['plant'];
			}

			foreach($period['weeks'] as $week) {

				$cCompositionSpeciesLean = new \Collection();

				foreach($lean[$week] as $speciesId) {
					$cCompositionSpeciesLean[$speciesId] = $cCompositionSpecies[$speciesId];
				}

				$weeksLean[$week] = [
					'cCompositionSpecies' => $cCompositionSpeciesLean,
					'period' => $position,
				];

			}

		}

		return [
			'weeks' => $weeksLean,
			'periods' => $periods,
		];

	}

	/**
	 * Ce que la carte des formules dit d'une formule, lu sur ses semaines
	 * (`CompositionWeekLib::getByFormulaWithLines()`) : le nombre de semaines, le nombre de lignes le
	 * plus bas et le plus haut, et le nombre de semaines où la règle a dû relâcher une contrainte.
	 *
	 * Une formule sans semaine rend des bornes nulles : le plan d'une base sans aucun panier partagé
	 * s'ouvre sans proposition, et l'écran le dit au lieu d'afficher « 0 à 0 » légumes.
	 */
	public static function getSummary(\Collection $cCompositionWeek): array {

		$lines = [];
		$short = 0;

		foreach($cCompositionWeek as $eCompositionWeek) {

			$lines[] = $eCompositionWeek['cCompositionLine']->count();

			if($eCompositionWeek['relaxedFloor']->get() !== 0) {
				$short++;
			}

		}

		return [
			'weeks' => $cCompositionWeek->count(),
			'linesMin' => ($lines === []) ? NULL : min($lines),
			'linesMax' => ($lines === []) ? NULL : max($lines),
			'weeksShort' => $short,
		];

	}

	/**
	 * La grille espèces × semaines d'une formule, groupée par organe dans l'ordre feuille, racine,
	 * fruit, puis celles dont l'organe n'est pas qualifié (`plant.organ` à NULL, hors équilibre du
	 * panier, que la règle sert quand même).
	 *
	 * Un rang par espèce servie au moins une fois ou candidate au moins une semaine : une espèce de
	 * saison que la règle n'a jamais retenue a sa place dans la grille, c'est même ce qu'elle montre.
	 * Une espèce cochée dont la saison a été vidée garde aussi le sien (r30, #93b), toutes cases hors
	 * saison.
	 * Dans un groupe, l'espèce à la plus longue saison (`weeksSeason`) vient en tête, et le nom
	 * départage : c'est ce qui fait lire d'un coup d'œil les piliers de l'année avant les apparitions
	 * ponctuelles. Le rang ne se compte plus sur les lignes proposées (r72) : leur nombre change
	 * d'un tirage à l'autre (`CompositionFormulaLib::replay()`), et une ligne verte qui glisse d'un
	 * rang à l'autre fait croire que la saison a changé. La saison, elle, ne dépend que du réglage.
	 *
	 * La teinte suit la proposition, jamais les corrections (planche v11, É11) : elle se compte sur
	 * les seules lignes `PROPOSED`. Une case allumée à la main ne reteinte donc pas sa colonne, et la
	 * réponse du geste ne rend que ce rang (`CompositionUi::getGridRow()`). Une case proposée qu'on
	 * éteint retire une ligne de la proposition, et celle-là peut reteinter sa semaine : la réponse
	 * rend alors la grille entière. Retirer une ligne ne laisse aucune trace (Q2) : ce qui a été
	 * proposé puis éteint ne se retrouve plus, et c'est le seul écart à la planche.
	 *
	 * Chaque rang porte, en plus de la ligne qui sert l'espèce chaque semaine où elle est servie
	 * (`lines`, dont la quantité s'écrit au bout du nom quand l'écran survole la semaine) :
	 * - `shares`, sa part attendue dans chaque semaine où elle est candidate, celle que la dette de
	 *   `propose()` cumule (`getShares()`), sur l'ordre de la semaine que la saison des espèces
	 *   ouvre (`$windows`, comme `propose()`), et sur le nombre de lignes proposées du panier. Les
	 *   clés sont les semaines où l'espèce est candidate : une semaine absente est hors de sa saison,
	 *   sans prix ou refusée pour un autre chou ;
	 * - `weeksSeason`, les semaines livrées de la saison de vente de l'espèce (`$windows`), celles
	 *   dont la case est verte. C'est le réglage des saisons seul qui les donne (r29, #87, retour de
	 *   du 29/09, « c'est vraiment le réglage des saisons qui conditionne l'affichage en
	 *   vert ») : ni la part, ni le prix, ni un autre chou ne l'éteignent, et une semaine que la
	 *   conservation ouvre ne se distingue pas d'une autre. Sans `$windows`, faute de saison réglée,
	 *   ce sont les semaines où l'espèce est candidate ;
	 * - `open`, les semaines livrées dont la case s'ouvre sur l'ajout de l'espèce
	 *   (`applyAddableToGrid()`), de sa saison ou non (r29, #83) : toutes, sauf pour une espèce qui
	 *   ne se vend pas (`isSold()`). Un chou s'ouvre aussi dans une semaine qui en sert déjà un
	 *   (r34d, retour du 30/09, « le user fait ce qu'il veut ») : la règle d'un seul chou
	 *   par panier ne tient que la proposition ;
	 * - `refused`, les semaines de sa fenêtre où la proposition ne le fait pas entrer parce que le
	 *   panier sert déjà un autre chou : pas de part, et son panneau dit pourquoi.
	 *
	 * Rend `[['organ' => ?string, 'served' => int, 'species' => [['species' => CompositionSpecies,
	 * 'lines' => [semaine => CompositionLine], 'shares' => [semaine => float],
	 * 'weeksSeason' => [semaine => semaine], 'open' => [semaine => semaine],
	 * 'refused' => [semaine]]]]]`, sans les groupes vides. Un rang est une espèce du plan, une
	 * variante (r76) en est un comme son espèce nue, rangée juste sous elle (`sortGridRows()`).
	 *
	 * `$cCompositionSpecies` porte les espèces du plan indexées par leur id
	 * (`CompositionSpeciesLib::getByPlan()`) : le rang d'une espèce jamais servie y prend la sienne,
	 * celle que l'écran a déjà lue, plutôt que de la relire (chantier Performances, P7).
	 */
	public static function getGrid(\Collection $cCompositionWeek, int $season, ?array $windows, array $source, \Collection $cCompositionSpecies): array {

		$rows = [];
		$linesProposed = [];
		$speciesIdsServed = [];

		$row = fn(CompositionSpecies $eCompositionSpecies) => ['species' => $eCompositionSpecies, 'lines' => [], 'shares' => [], 'weeksSeason' => [], 'open' => [], 'refused' => []];

		foreach($cCompositionWeek as $eCompositionWeek) {

			$linesProposed[$eCompositionWeek['week']] = 0;
			$speciesIdsServed[$eCompositionWeek['week']] = [];

			foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

				$eCompositionSpecies = $eCompositionLine['species'];

				$rows[$eCompositionSpecies['id']] ??= $row($eCompositionSpecies);
				$rows[$eCompositionSpecies['id']]['lines'][$eCompositionWeek['week']] = $eCompositionLine;
				$speciesIdsServed[$eCompositionWeek['week']][] = $eCompositionSpecies['id'];

				if($eCompositionLine['origin'] === CompositionLine::PROPOSED) {
					$linesProposed[$eCompositionWeek['week']]++;
				}

			}

		}

		// Une espèce candidate qui n'est jamais servie n'a pas de ligne d'où tenir son nom : elle se
		// lit à part.
		$speciesIdsNeverServed = [];

		// Seules les semaines qui ont un panier ont une fenêtre à montrer : une semaine livrée sans
		// panier est une semaine sans aucune candidate (`propose()` la saute).
		foreach($cCompositionWeek as $eCompositionWeek) {

			$week = $eCompositionWeek['week'];
			$order = self::getOrder($week, $season, $windows, $source);

			foreach(self::getShares($week, $order, $linesProposed[$week], $source) as $speciesId => $share) {

				if(isset($rows[$speciesId]) === FALSE) {
					$rows[$speciesId] = $row(new CompositionSpecies(['id' => $speciesId]));
					$speciesIdsNeverServed[] = $speciesId;
				}

				if(self::isBasketRefused($speciesId, $speciesIdsServed[$week], $source)) {
					$rows[$speciesId]['refused'][] = $week;
					continue;
				}

				$rows[$speciesId]['shares'][$week] = $share;

			}

		}

		// Une espèce cochée dont la saison est vide (`CUSTOM` sur `[]`, la seule forme que
		// `CompositionSpecies::properties()` admet) n'est ni servie ni candidate, et garde pourtant son
		// rang (r30, #93b, retour : on éteint un intervalle, l'espèce ne disparaît pas).
		// C'est sur ce rang que le réglage de la saisonnalité en redessine un, et sans lui la réponse du geste
		// redessinerait toute la grille (`CompositionUi::getSeasoned()`). Toutes ses cases sont hors
		// saison, et s'ouvrent comme les autres cases hors saison.
		foreach($windows ?? [] as $speciesId => $weeks) {

			if($weeks === [] and isset($rows[$speciesId]) === FALSE) {
				$rows[$speciesId] = $row(new CompositionSpecies(['id' => $speciesId]));
				$speciesIdsNeverServed[] = $speciesId;
			}

		}

		// Un rang peut n'être candidat qu'une partie de sa saison (une semaine sans prix, un autre
		// chou) : la case verte se lit donc sur la saison elle-même, une fois tous les rangs connus.
		foreach($rows as $speciesId => $rowSpecies) {

			$weeksSeason = ($windows === NULL) ?
				array_merge(array_keys($rowSpecies['shares']), $rowSpecies['refused']) :
				array_intersect($windows[$speciesId] ?? [], array_keys($linesProposed));

			foreach($weeksSeason as $week) {
				$rows[$speciesId]['weeksSeason'][$week] = $week;
			}

			// Toute semaine livrée s'ouvre, de saison ou non (r29, #83, retour du 29/09,
			// « permettre d'ajouter des produits même hors planning ») : `CompositionLine::properties()`
			// ne regarde pas la saison, ni un autre chou servi (r34d). Reste fermée la case dont l'ajout
			// serait refusé, celle de l'espèce sans unité au panier (`unsold`).
			if(self::isSold($speciesId, $source)) {

				foreach(array_keys($linesProposed) as $week) {
					$rows[$speciesId]['open'][$week] = $week;
				}

			}

			ksort($rows[$speciesId]['weeksSeason']);
			ksort($rows[$speciesId]['open']);

		}

		foreach($speciesIdsNeverServed as $speciesId) {
			$rows[$speciesId]['species'] = $cCompositionSpecies[$speciesId];
		}

		$groups = [];

		foreach([\plant\Plant::LEAF, \plant\Plant::ROOT, \plant\Plant::FRUIT, NULL] as $organ) {

			$rowsOrgan = array_values(array_filter($rows, fn(array $row) => $row['species']['plant']['organ'] === $organ));

			if($rowsOrgan === []) {
				continue;
			}

			$rowsOrgan = self::sortGridRows($rowsOrgan);

			$groups[] = [
				'organ' => $organ,
				'served' => array_sum(array_map(fn(array $row) => count($row['lines']), $rowsOrgan)),
				'species' => $rowsOrgan,
			];

		}

		return $groups;

	}

	/**
	 * L'ordre des rangs d'un organe de la grille : la saison la plus longue en tête, puis le nom. Une
	 * variante se range juste sous son espèce nue, les variantes d'une même espèce par leur nom (r76,
	 * arbitrage du 04/10/2026, « la grille range les variantes sous leur espèce, triées par nom ») :
	 * sa saison, qui lui est propre, ne l'éloigne pas de l'espèce qu'elle décline. Une variante dont le
	 * plan ne sert pas l'espèce nue dans cette grille se range comme une espèce.
	 */
	private static function sortGridRows(array $rows): array {

		$plantIdsBare = [];

		foreach($rows as $row) {

			if($row['species']['name'] === NULL) {
				$plantIdsBare[$row['species']['plant']['id']] = TRUE;
			}

		}

		$rowsLead = [];
		$rowsVariant = [];

		foreach($rows as $row) {

			$plantId = $row['species']['plant']['id'];

			if($row['species']['name'] !== NULL and array_key_exists($plantId, $plantIdsBare)) {
				$rowsVariant[$plantId][] = $row;
			} else {
				$rowsLead[] = $row;
			}

		}

		$byLabel = fn(array $row1, array $row2) => \L::getCollator()->compare($row1['species']->getLabel(), $row2['species']->getLabel());

		usort($rowsLead, fn(array $row1, array $row2) => (count($row2['weeksSeason']) <=> count($row1['weeksSeason'])) ?: $byLabel($row1, $row2));

		$rowsSorted = [];

		foreach($rowsLead as $row) {

			$rowsSorted[] = $row;

			$plantId = $row['species']['plant']['id'];

			if($row['species']['name'] === NULL and array_key_exists($plantId, $rowsVariant)) {
				usort($rowsVariant[$plantId], $byLabel);
				array_push($rowsSorted, ...$rowsVariant[$plantId]);
			}

		}

		return $rowsSorted;

	}

	/**
	 * Le contenu de chaque semaine livrée, toutes tailles côte à côte (lot L4, planche du 24/09, B et
	 * A) : `[semaine => [['species' => CompositionSpecies, 'unit' => Unit, 'formulas' => [id de taille => ?float]]]]`,
	 * une entrée par taille du plan, NULL quand la taille ne sert pas l'espèce cette semaine. Une
	 * semaine livrée qu'aucune taille ne sert reste, vide.
	 *
	 * Les espèces se rangent dans l'ordre de la synthèse (`getQuantities()`) : par organe comme la
	 * grille, puis la plus servie de l'année en tête. Chaque semaine suit donc le même ordre, et une
	 * espèce se retrouve à la même hauteur d'une carte à l'autre. Une espèce servie dans deux unités
	 * a deux rangs, comme dans la synthèse.
	 */
	public static function getWeekBaskets(CompositionPlan $eCompositionPlan): array {

		$eCompositionPlan->expects(['weeks', 'cCompositionFormula']);

		$positions = [];

		foreach(self::getQuantities($eCompositionPlan, new \Collection())['groups'] as $group) {
			foreach($group['rows'] as $row) {
				$positions[$row['species']['id'].'-'.$row['unit']['id']] = count($positions);
			}
		}

		$formulas = array_fill_keys($eCompositionPlan['cCompositionFormula']->getIds(), NULL);

		$weeks = $eCompositionPlan['weeks'];
		sort($weeks);

		$baskets = array_fill_keys($weeks, []);

		foreach($eCompositionPlan['cCompositionFormula'] as $eCompositionFormula) {

			foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

					$key = $eCompositionLine['species']['id'].'-'.$eCompositionLine['unit']['id'];

					$baskets[$eCompositionWeek['week']][$key] ??= ['species' => $eCompositionLine['species'], 'unit' => $eCompositionLine['unit'], 'formulas' => $formulas];
					$baskets[$eCompositionWeek['week']][$key]['formulas'][$eCompositionFormula['id']] = (float)$eCompositionLine['quantity'];

				}

			}

		}

		foreach($baskets as $week => $rows) {
			uksort($rows, fn(string $key1, string $key2) => $positions[$key1] <=> $positions[$key2]);
			$baskets[$week] = array_values($rows);
		}

		return $baskets;

	}

	/**
	 * L'année en quantités (Q6, Q7) : ce que le plan sert sur toutes ses semaines livrées, par espèce
	 * et par unité, toutes tailles additionnées. Une ligne compte autant de fois que sa taille a de
	 * paniers chaque semaine, dans l'unité du panier et sans marge ni perte : c'est ce qui part dans
	 * les paniers, pas ce qu'il faut cultiver.
	 *
	 * Le calcul se fait à chaque lecture, sur les semaines de chaque taille (`cCompositionWeek`) et
	 * jamais depuis une colonne : sous la communauté, régler le nombre de paniers ne recompose rien, et
	 * un total écrit en base se désynchroniserait au premier réglage.
	 *
	 * `$cCompositionSpeciesGrown` porte les espèces cochées du plan (`CompositionSpeciesLib::getGrown()`) :
	 * celle que la règle n'a mise dans aucun panier garde son rang, grisée, plutôt que de disparaître
	 * du tableau, ce qui dirait qu'elle n'est pas cultivée.
	 *
	 * Rend :
	 * - `baskets` : les paniers d'une semaine, toutes tailles ;
	 * - `weeks` : les semaines livrées ;
	 * - `basketsYear` : les paniers de l'année ;
	 * - `turnover` : le chiffre d'affaires de l'année, la somme de celui de chaque taille
	 *   (`getTurnover()`), le même que sa tête affiche ;
	 * - `groups` : par organe dans l'ordre de la grille, `[['organ' => ?string, 'rows' => [['species' =>
	 *   CompositionSpecies, 'unit' => Unit, 'formulas' => [id de taille => ?float], 'total' => float]]]]`, une
	 *   entrée par taille du plan, NULL quand elle ne sert pas l'espèce. La plus grosse quantité
	 *   vient en tête, puis les espèces servies dans aucun panier, par ordre alphabétique, avec une
	 *   unité vide et toutes leurs tailles à NULL. Une variante (r76) a ses rangs, sous son nom (`getLabel()`).
	 */
	public static function getQuantities(CompositionPlan $eCompositionPlan, \Collection $cCompositionSpeciesGrown): array {

		$eCompositionPlan->expects(['weeks', 'cCompositionFormula']);

		$weeks = count($eCompositionPlan['weeks']);

		$baskets = 0;
		$turnover = 0.0;
		$rows = [];

		// Chaque rang porte toutes les tailles du plan, à NULL tant que la taille ne sert pas l'espèce.
		$formulas = array_fill_keys($eCompositionPlan['cCompositionFormula']->getIds(), NULL);

		foreach($eCompositionPlan['cCompositionFormula'] as $eCompositionFormula) {

			$baskets += $eCompositionFormula['baskets'];
			$turnover += self::getTurnover($eCompositionFormula['cCompositionWeek'], $eCompositionFormula['baskets']);

			foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

					$key = $eCompositionLine['species']['id'].'-'.$eCompositionLine['unit']['id'];
					$quantity = $eCompositionLine['quantity'] * $eCompositionFormula['baskets'];

					$rows[$key] ??= ['species' => $eCompositionLine['species'], 'unit' => $eCompositionLine['unit'], 'formulas' => $formulas, 'total' => 0.0];
					$rows[$key]['formulas'][$eCompositionFormula['id']] = ($rows[$key]['formulas'][$eCompositionFormula['id']] ?? 0.0) + $quantity;
					$rows[$key]['total'] += $quantity;

				}

			}

		}

		$speciesIdsServed = array_map(fn(array $row) => $row['species']['id'], $rows);

		$rowsNever = [];

		foreach($cCompositionSpeciesGrown as $eCompositionSpecies) {

			if(in_array($eCompositionSpecies['id'], $speciesIdsServed) === FALSE) {
				$rowsNever[] = ['species' => $eCompositionSpecies, 'unit' => new \product\Unit(), 'formulas' => $formulas, 'total' => 0.0];
			}

		}

		$groups = [];

		foreach([\plant\Plant::LEAF, \plant\Plant::ROOT, \plant\Plant::FRUIT, NULL] as $organ) {

			$served = array_values(array_filter($rows, fn(array $row) => $row['species']['plant']['organ'] === $organ));
			$never = array_values(array_filter($rowsNever, fn(array $row) => $row['species']['plant']['organ'] === $organ));

			if($served === [] and $never === []) {
				continue;
			}

			usort($served, fn(array $row1, array $row2) => ($row2['total'] <=> $row1['total']) ?: \L::getCollator()->compare($row1['species']->getLabel(), $row2['species']->getLabel()));
			usort($never, fn(array $row1, array $row2) => \L::getCollator()->compare($row1['species']->getLabel(), $row2['species']->getLabel()));

			$groups[] = [
				'organ' => $organ,
				'rows' => array_merge($served, $never),
			];

		}

		return [
			'baskets' => $baskets,
			'weeks' => $weeks,
			'basketsYear' => $baskets * $weeks,
			'turnover' => $turnover,
			'groups' => $groups,
		];

	}

	/**
	 * Pose sur chaque rang de la Synthèse (`getQuantities()`) ce qu'il sert chaque mois (lot r19,
	 * planche paniers-maquette-r16, question 2, option A) : toutes les tailles, paniers vendus
	 * compris, dans l'unité du rang, comme « Sur l'année » dont ce sont les parts.
	 *
	 * - `months`, `[mois => ['quantity' => float, 'bar' => float]]`, de janvier à décembre. `bar` est
	 *   la hauteur de la barre, de 0 à 1 : le mois le plus chargé de l'espèce prend toute la hauteur,
	 *   puisque les unités ne se comparent pas d'une espèce à l'autre et qu'une échelle commune
	 *   écraserait l'ail sous la courgette ;
	 * - `peak`, la quantité du mois le plus chargé, celle qu'atteint une barre pleine : c'est l'échelle
	 *   que le diagramme écrit au-dessus de lui (lot r19b, `util-bars-max`).
	 *
	 * Une semaine va au mois de son jeudi, comme sur l'axe de la grille (`getSeasonWeekMonths()`).
	 */
	public static function applyMonthsToQuantities(array $quantities, CompositionPlan $eCompositionPlan): array {

		$eCompositionPlan->expects(['season', 'weekMonths', 'cCompositionFormula']);

		$weekly = [];

		foreach($eCompositionPlan['cCompositionFormula'] as $eCompositionFormula) {
			foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {
				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
					$key = $eCompositionLine['species']['id'].'-'.$eCompositionLine['unit']['id'];
					$weekly[$key][$eCompositionWeek['week']] = ($weekly[$key][$eCompositionWeek['week']] ?? 0.0) + $eCompositionLine['quantity'] * $eCompositionFormula['baskets'];
				}
			}
		}

		foreach($quantities['groups'] as $group => ['rows' => $rows]) {

			foreach($rows as $position => $row) {

				$weeks = $row['unit']->empty() ? [] : ($weekly[$row['species']['id'].'-'.$row['unit']['id']] ?? []);

				$values = array_fill(1, 12, 0.0);

				foreach($weeks as $week => $quantity) {
					$values[self::getWeekMonth($eCompositionPlan['weekMonths'], $eCompositionPlan['season'], $week)] += $quantity;
				}

				$peak = max($values);

				$quantities['groups'][$group]['rows'][$position]['peak'] = $peak;
				$quantities['groups'][$group]['rows'][$position]['months'] = array_map(fn(float $quantity) => [
					'quantity' => $quantity,
					'bar' => ($peak > 0.0) ? round($quantity / $peak, 3) : 0.0,
				], $values);

			}

		}

		return $quantities;

	}

	/**
	 * Le mois d'une semaine de la saison, de 1 à 12, celui de son jeudi (`getSeasonWeekMonths()`).
	 */
	public static function getWeekMonth(array $weekMonths, int $season, int $week): int {
		return (int)substr($weekMonths[$season.'-W'.sprintf('%02d', $week)], 5, 2);
	}

	/**
	 * Pose sur chaque rang de la grille (`getGrid()`) et sur chaque groupe ce que la taille sert de
	 * l'espèce sur l'année, sous `sum` (lot r13, planche r9, « la somme en fin de ligne ») :
	 * `['kilos' => ?float, 'value' => float]`, chaque ligne comptée autant de fois que la taille a de
	 * paniers (`$baskets`), comme le chiffre d'affaires (`getTurnover()`) et la Synthèse
	 * (`getQuantities()`).
	 *
	 * La valeur est celle des lignes (`CompositionWeekLib::getByFormulaWithLines()`), une ligne sans
	 * prix employable comptant pour zéro comme dans la valeur de sa semaine : la somme des groupes
	 * retombe ainsi sur le chiffre d'affaires de la taille, au centime près des arrondis par ligne.
	 *
	 * Les kilos convertissent la pièce et la botte par le poids de l'unité (`getUnitKilos()`). Une
	 * espèce dont une ligne n'a pas de poids connu a ses kilos à NULL : un total partiel se lirait
	 * comme juste. Un groupe additionne les kilos de ses espèces pesées, et compte les autres sous
	 * `unweighed`, que l'écran ne montre pas aujourd'hui mais qu'un test peut vérifier.
	 *
	 * Un rang sans ligne (une candidate que la taille ne sert jamais) porte des sommes à zéro, et
	 * `served` à FALSE, que l'écran lit pour laisser sa case vide.
	 *
	 * Le rang d'une espèce porte aussi sa quantité de l'année dans l'unité où elle entre au panier,
	 * celle que la structure a choisie (lot r14) ou celle de la communauté, sous `quantity` avec
	 * l'unité sous `eUnit` (lot r13b, « mettre la qte dans l'unité de ce qui est vendu ») : c'est ce
	 * que la colonne écrit en face de l'espèce. Une ligne restée dans une autre unité (une semaine
	 * corrigée avant un changement d'unité) se convertit par les poids des deux unités, et la quantité
	 * est NULL quand l'un manque, pour la même raison que les kilos. Une espèce que le plan ne règle
	 * plus prend l'unité de ses lignes. Les groupes et le pied gardent les kilos : des pièces et des
	 * bottes ne s'additionnent pas.
	 */
	public static function applySumsToGrid(array $grid, int $baskets, array $source, \Collection $cCompositionSpecies): array {

		foreach($grid as $group => ['species' => $rows]) {

			$sumGroup = ['kilos' => 0.0, 'value' => 0.0, 'unweighed' => 0];

			foreach($rows as $position => $row) {

				$speciesId = $row['species']['id'];

				if($cCompositionSpecies->offsetExists($speciesId)) {
					$eUnit = $cCompositionSpecies[$speciesId]['eUnit'];
				} else if($row['lines'] !== []) {
					$eUnit = $row['lines'][array_key_first($row['lines'])]['unit'];
				} else {
					$eUnit = new \product\Unit();
				}

				$kilos = 0.0;
				$quantity = 0.0;
				$value = 0.0;

				foreach($row['lines'] as $eCompositionLine) {

					$value += ($eCompositionLine['value'] ?? 0.0) * $baskets;

					$unitKilos = self::getUnitKilos($speciesId, $eCompositionLine['unit']['fqn'], $source, $cCompositionSpecies);

					// Une seule ligne sans poids suffit à rendre les kilos de l'année inconnus.
					$kilos = ($kilos === NULL or $unitKilos === NULL) ? NULL : $kilos + $eCompositionLine['quantity'] * $unitKilos * $baskets;

					if($eCompositionLine['unit']['id'] === $eUnit['id']) {
						$ratio = 1.0;
					} else {
						$speciesKilos = self::getUnitKilos($speciesId, $eUnit['fqn'], $source, $cCompositionSpecies);
						$ratio = ($unitKilos === NULL or $speciesKilos === NULL) ? NULL : $unitKilos / $speciesKilos;
					}

					$quantity = ($quantity === NULL or $ratio === NULL) ? NULL : $quantity + $eCompositionLine['quantity'] * $ratio * $baskets;

				}

				$grid[$group]['species'][$position]['sum'] = [
					'served' => ($row['lines'] !== []),
					'kilos' => $kilos,
					'quantity' => $quantity,
					'eUnit' => $eUnit,
					'value' => $value,
				];

				$sumGroup['value'] += $value;

				if($kilos === NULL) {
					$sumGroup['unweighed']++;
				} else {
					$sumGroup['kilos'] += $kilos;
				}

			}

			$grid[$group]['sum'] = $sumGroup;

		}

		return $grid;

	}

	/**
	 * L'année de la taille entière, en pied de la grille : la somme des groupes posés par
	 * `applySumsToGrid()`, sous la même forme.
	 */
	public static function getGridSum(array $grid): array {

		$sum = ['kilos' => 0.0, 'value' => 0.0, 'unweighed' => 0];

		foreach($grid as $group) {
			$sum['kilos'] += $group['sum']['kilos'];
			$sum['value'] += $group['sum']['value'];
			$sum['unweighed'] += $group['sum']['unweighed'];
		}

		return $sum;

	}

	/**
	 * Les semaines de `$weekMonths` qui ouvrent un mois (lot r21), sauf la première, qui n'a pas de
	 * mois avant elle : la grille tire devant chacune le trait qui sépare deux mois
	 * (`CompositionUi::getGridCellAttributes()`), sur les mois de l'axe (`getSeasonWeekMonths()`).
	 */
	public static function getMonthStarts(array $weekMonths): array {

		$starts = [];
		$previous = NULL;

		foreach($weekMonths as $isoWeek => $month) {

			if($previous !== NULL and $month !== $previous) {
				$starts[] = (int)substr($isoWeek, -2);
			}

			$previous = $month;

		}

		return $starts;

	}

	/**
	 * Les semaines de la saison et le mois de chacune, `['2026-W01' => '2026-01', …]` : l'axe de la
	 * grille (`series\CultivationUi::getListSeason()`, `$weekMonths`), une colonne par semaine.
	 * Chaque semaine va au mois qui porte le plus de ses jours, celui de son jeudi
	 * (`\util\DateLib::convertWeeksToMonths()`), comme dans `getSeasonMonths()`.
	 */
	public static function getSeasonWeekMonths(int $season): array {

		$weekMonths = [];

		foreach(self::getSeasonWeeks() as $week) {

			$isoWeek = $season.'-W'.sprintf('%02d', $week);

			$weekMonths[$isoWeek] = \util\DateLib::convertWeeksToMonths([$isoWeek])[0];

		}

		return $weekMonths;

	}

}
?>
