<?php
namespace toolbox;

/**
 * Une taille de panier, que le code nomme formule : un prix visé, un nombre de paniers par semaine,
 * et la composition qui en découle semaine par semaine.
 *
 * Les tailles naissent au départ (`CompositionPlanLib::create()`), nommées par la personne ; les
 * autres s'ajoutent ensuite depuis l'écran. Rien dans le schéma ne fixe leur nombre.
 *
 * Un plan ne porte pas deux tailles du même nom (lot r40, décidé le 01/10, qui revient
 * sur le choix d'origine de les laisser se distinguer par leur prix). Le refus se dit avant
 * l'écriture (`CompositionFormula::properties()`, `name.duplicate`) ; l'unicité `(plan, name)` du
 * schéma retient ce que ce contrôle ne voit pas, deux envois simultanés et deux tailles du même nom
 * dans un même envoi, et chaque écriture d'un nom rend alors ce même refus (`add()`, `update()`,
 * `updateByPlan()`, `CompositionPlanLib::create()`).
 */
class CompositionFormulaLib extends CompositionFormulaCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return self::getPropertiesUpdate();
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['base', 'name', 'price', 'baskets', 'gap', 'linesMin', 'linesMax', 'position'];
	}

	public static function getByPlan(CompositionPlan $eCompositionPlan): \Collection {

		return CompositionFormula::model()
			->select(CompositionFormula::getSelection())
			->wherePlan($eCompositionPlan)
			->sort(['position' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

	}

	/**
	 * Une formule sans base se compose seule (lot r17) : c'est le défaut d'une formule construite
	 * sans elle.
	 *
	 * Un nom déjà pris dans le plan lève `DuplicateException`, que l'appelant rend en refus : lui
	 * seul sait ce que sa transaction a déjà écrit, et donc s'il la referme ou s'il l'annule.
	 */
	public static function create(CompositionFormula $e): void {

		$e->add(['base' => new CompositionFormula()]);

		parent::create($e);

	}

	/**
	 * Les formules du plan dans l'ordre où elles se composent (lot r17) : les formules sans base
	 * d'abord, dans l'ordre des positions, puis les dérivées, qui lisent les semaines écrites de leur
	 * base (`CompositionLib::getBaseLines()`). C'est l'ordre de toute boucle qui compose ou recompose
	 * plusieurs formules du plan dans une même transaction.
	 */
	public static function getByPlanForCompose(CompositionPlan $eCompositionPlan): \Collection {

		return self::sortForCompose(self::getByPlan($eCompositionPlan));

	}

	/**
	 * `$cCompositionFormula` dans l'ordre où ses formules se composent (`getByPlanForCompose()`), sur
	 * la base que chacune porte, qui peut n'être pas encore écrite (`updateByPlan()`).
	 */
	public static function sortForCompose(\Collection $cCompositionFormula): \Collection {

		return $cCompositionFormula
			->find(fn(CompositionFormula $e) => $e['base']->empty())
			->appendCollection($cCompositionFormula->find(fn(CompositionFormula $e) => $e['base']->notEmpty()));

	}

	/**
	 * Les formules qui s'appuient sur `$e` (lot r17), vide pour une formule qui ne sert de base à
	 * aucune. `$e` est une formule écrite : une formule neuve n'a pas de dérivée.
	 */
	public static function getByBase(CompositionFormula $e): \Collection {

		$e->expects(['id']);

		return CompositionFormula::model()
			->select(CompositionFormula::getSelection())
			->whereBase($e)
			->sort(['position' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

	}

	/**
	 * Les tailles de panier de la structure, ce que la carte des paniers de la Boîte à outils chiffre
	 * (`ToolUi::getFigures()`) ; vide sans composition.
	 */
	public static function getByStructure(\structure\Structure $eStructure): \Collection {

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);

		if($eCompositionPlan->empty()) {
			return new \Collection();
		}

		return self::getByPlan($eCompositionPlan);

	}

	/**
	 * La formule `$id` du plan, vide quand `$id` ne désigne aucune formule du plan. Elle porte le plan
	 * reçu, sur lequel se lit le droit de l'écrire.
	 */
	public static function getByPlanAndId(CompositionPlan $eCompositionPlan, int $id): CompositionFormula {

		$cCompositionFormula = self::getByPlan($eCompositionPlan);
		$cCompositionFormula->setColumn('plan', $eCompositionPlan);

		return $cCompositionFormula->offsetExists($id) ? $cCompositionFormula[$id] : new CompositionFormula();

	}

	/**
	 * Les tailles sur lesquelles `$e` pourrait s'appuyer (lot r17), que le réglage « S'appuie sur » et
	 * le panneau « Ajouter une taille » proposent : toutes les autres tailles du plan, dans l'ordre des
	 * onglets, `[id => ['eCompositionFormula' => …, 'refusal' => ?string, 'cited' => [nom, …]]]`.
	 *
	 * Une taille qu'une règle de `CompositionFormula::properties()` refuserait reste dans la liste
	 * avec la raison du refus, que l'écran écrit sous son nom (`field-radio-group-reason`), et
	 * `cited` porte les noms que cette raison cite :
	 *
	 * - `serving` : `$e` sert déjà d'appui (`base.chain`), à `cited` ;
	 * - `derived` : la taille s'appuie déjà sur `cited` (`base.chain`) ;
	 * - `pricier`, `priceSame` : la taille coûte plus que `$e`, ou autant (`base.cheaper`) ;
	 * - `lines` : la taille va jusqu'à plus de légumes que `$e` (`base.lines`).
	 *
	 * Une taille neuve n'a encore ni prix ni dérivée : seul `derived` s'y lit, le prix se compare à
	 * l'envoi, sous le champ.
	 *
	 * `$cCompositionFormula` sont les tailles du plan de `$e` (`getByPlan()`), que l'écran a déjà
	 * (chantier Performances, P6).
	 */
	public static function getBaseChoices(CompositionFormula $e, \Collection $cCompositionFormula): array {

		$names = [];
		$serving = [];

		foreach($cCompositionFormula as $eCompositionFormula) {

			$names[$eCompositionFormula['id']] = $eCompositionFormula['name'];

			if($e->exists() and $eCompositionFormula['base']->notEmpty() and $eCompositionFormula['base']['id'] === $e['id']) {
				$serving[] = $eCompositionFormula['name'];
			}

		}

		$choices = [];

		foreach($cCompositionFormula as $eCompositionFormula) {

			if($e->exists() and $eCompositionFormula['id'] === $e['id']) {
				continue;
			}

			if($serving !== []) {
				[$refusal, $cited] = ['serving', $serving];
			} else if($eCompositionFormula['base']->notEmpty()) {
				[$refusal, $cited] = ['derived', [$names[$eCompositionFormula['base']['id']]]];
			} else if($e->exists() and (float)$eCompositionFormula['price'] > (float)$e['price']) {
				[$refusal, $cited] = ['pricier', [$e['name']]];
			} else if($e->exists() and (float)$eCompositionFormula['price'] === (float)$e['price']) {
				[$refusal, $cited] = ['priceSame', [$e['name']]];
			} else if($e->exists() and $eCompositionFormula['linesMax'] > $e['linesMax']) {
				[$refusal, $cited] = ['lines', [$e['name']]];
			} else {
				[$refusal, $cited] = [NULL, []];
			}

			$choices[$eCompositionFormula['id']] = [
				'eCompositionFormula' => $eCompositionFormula,
				'refusal' => $refusal,
				'cited' => $cited,
			];

		}

		return $choices;

	}

	/**
	 * Ce qu'une recomposition de `$e` lui fait perdre, pour le message qui la fait confirmer
	 * (`CompositionFormulaUi::getReplayConfirm()`, `getRecomposeLoss()`) : ses semaines corrigées à la
	 * main, et le nom des autres formules du plan, qui ne bougent pas. Les tailles du plan
	 * (`getByPlan()`) et les semaines corrigées sont celles que l'appelant a déjà lues (chantier
	 * Performances, P6).
	 */
	public static function applyRecompose(CompositionFormula $e, \Collection $cCompositionFormula, \Collection $cCompositionWeekAmended): void {

		$e['weeksAmended'] = $cCompositionWeekAmended->getColumn('week');
		$e['formulasOther'] = $cCompositionFormula
			->find(fn(CompositionFormula $eCompositionFormula) => $eCompositionFormula['id'] !== $e['id'])
			->getColumn('name');

	}

	/**
	 * Un prix, un écart ou une borne du nombre de légumes qui change refait toute la proposition de sa
	 * formule (§6.5 du cadrage, « si il change le prix, l'amendement saute ») : l'étape 8 fait dépendre
	 * le nombre de lignes du prix visé, de l'écart toléré et des bornes (r11), et la dette d'une espèce
	 * court d'une semaine à l'autre, donc une semaine ne se refait pas sans les autres.
	 *
	 * La base qui change (lot r17) refait de même toute la formule, qui reprend alors les lignes de sa
	 * nouvelle base, ou se compose seule quand elle la quitte. Une base recomposée refait les semaines
	 * non corrigées de ses dérivées (`CompositionLib::recompose()`).
	 *
	 * Le nombre de paniers (lot r23b2) ne change la composition que sous « Mes semaines de récolte » :
	 * le stock de chaque semaine se partage entre les paniers de la taille (`getStockCaps()`), et ce
	 * que ses paniers prennent manque aux autres tailles (`getNeeds()`). Toutes les tailles refont
	 * donc leurs semaines non corrigées (`CompositionLib::recomposePlan()`), sans rien perdre de ce
	 * qu'on a corrigé à la main, et sans confirmation. Sous la communauté, il ne recompose rien.
	 */
	public static function update(CompositionFormula $e, array $properties): void {

		$e->expects(['plan' => ['id', 'structure', 'season', 'reference', 'weeks']]);

		CompositionFormula::model()->beginTransaction();

			try {
				parent::update($e, $properties);
			} catch(\DuplicateException) {

				CompositionFormula::fail('name.duplicate');

				// L'écriture refusée n'a rien écrit, et `rollBack()` viderait toute la pile, transaction
				// de l'appelant comprise : la transaction ouverte ici se referme par un `commit()`.
				CompositionFormula::model()->commit();

				return;

			}

			$recompose = (array_intersect(self::getPropertiesRecompose(), $properties) !== []);
			$recomposePlan = (in_array('baskets', $properties, TRUE) and $e['plan']['reference'] === CompositionPlan::CULTIVATION);

			if($recompose or $recomposePlan) {

				$source = CompositionLib::getSourceByPlan($e['plan']);
				$prices = $source['pricesPlan'];

				if($recompose) {
					CompositionLib::recompose($e['plan'], $e, $source, $prices);
				}

				if($recomposePlan) {
					CompositionLib::recomposePlan($e['plan'], $source, $prices);
				}

			}

		CompositionFormula::model()->commit();

	}

	/**
	 * « Modifier les paniers » (r31, #96) : toutes les tailles du plan, construites ensemble
	 * (`composition.p.php`, `doSettings`), et chacune n'écrit que ce qui a changé, lu en base avant
	 * l'envoi. Les règles de `update()` valent pour chaque taille : ce qui recompose une taille la
	 * refait entière, et le nombre de paniers sous « Mes semaines de récolte » refait les semaines non
	 * corrigées de toutes les tailles, une seule fois pour tout l'envoi.
	 *
	 * Les tailles s'écrivent et se recomposent dans l'ordre de la composition, sur la base que chacune
	 * vient de recevoir (`sortForCompose()`) : une dérivée lit les semaines que sa base vient d'écrire.
	 * Une taille que rien ne change ne se recompose pas, même quand sa base change : la base refait
	 * elle-même les semaines non corrigées de ses dérivées (`CompositionLib::recompose()`).
	 *
	 * Deux tailles renommées du même nom dans le même envoi passent toutes deux `name.duplicate`, qui
	 * compare à la base (lot r40) : l'unicité `(plan, name)` refuse la seconde. Les tailles déjà
	 * écrites et recomposées par cet envoi ne doivent pas rester, donc la transaction s'annule et le
	 * refus se lève, `rollBack()` vidant toute la pile.
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function updateByPlan(CompositionPlan $eCompositionPlan, \Collection $cCompositionFormula): void {

		$eCompositionPlan->expects(['id', 'structure', 'season', 'reference', 'weeks']);

		$cCompositionFormulaBefore = self::getByPlan($eCompositionPlan);

		$source = NULL;
		$recomposePlan = FALSE;

		CompositionFormula::model()->beginTransaction();

			foreach(self::sortForCompose($cCompositionFormula) as $eCompositionFormula) {

				$properties = self::getPropertiesChanged($cCompositionFormulaBefore[$eCompositionFormula['id']], $eCompositionFormula);

				if($properties === []) {
					continue;
				}

				try {
					parent::update($eCompositionFormula, $properties);
				} catch(\DuplicateException) {

					CompositionFormula::model()->rollBack();

					throw new \FailAction('toolbox\CompositionFormula::name.duplicate');

				}

				if(array_intersect(self::getPropertiesRecompose(), $properties) !== []) {
					$source ??= CompositionLib::getSourceByPlan($eCompositionPlan);
					CompositionLib::recompose($eCompositionPlan, $eCompositionFormula, $source, $source['pricesPlan']);
				}

				if(in_array('baskets', $properties, TRUE) and $eCompositionPlan['reference'] === CompositionPlan::CULTIVATION) {
					$recomposePlan = TRUE;
				}

			}

			if($recomposePlan) {
				$source ??= CompositionLib::getSourceByPlan($eCompositionPlan);
				CompositionLib::recomposePlan($eCompositionPlan, $source, $source['pricesPlan']);
			}

		CompositionFormula::model()->commit();

	}

	/**
	 * Les propriétés dont le changement refait toute la proposition d'une taille (`update()`).
	 */
	public static function getPropertiesRecompose(): array {
		return ['base', 'price', 'gap', 'linesMin', 'linesMax'];
	}

	/**
	 * Les propriétés de « Modifier les paniers » que `$eCompositionFormula` porte autrement que
	 * `$eCompositionFormulaBefore`, la même taille lue avant l'envoi. Le prix se compare en nombre :
	 * la base le rend en chaîne, et « 20 » posté vaut « 20.00 » lu.
	 */
	private static function getPropertiesChanged(CompositionFormula $eCompositionFormulaBefore, CompositionFormula $eCompositionFormula): array {

		$properties = [];

		foreach(['name', 'baskets', 'gap', 'linesMin', 'linesMax'] as $property) {
			if($eCompositionFormula[$property] !== $eCompositionFormulaBefore[$property]) {
				$properties[] = $property;
			}
		}

		if((float)$eCompositionFormula['price'] !== (float)$eCompositionFormulaBefore['price']) {
			$properties[] = 'price';
		}

		if($eCompositionFormula['base']->is($eCompositionFormulaBefore['base']) === FALSE) {
			$properties[] = 'base';
		}

		return $properties;

	}

	/**
	 * « Rejouer les paniers » : la formule passe au tirage suivant de la règle (`version + 1`, qui
	 * n'entre que dans le bruit de `CompositionLib::getNoise()`) et sa proposition se refait, au
	 * prix, à l'écart et sur les réponses du plan tels qu'ils sont. Ses corrections faites à la main
	 * partent avec ses semaines, comme à un changement de prix ; les autres formules ne bougent pas.
	 *
	 * La version ne passe pas par `build()`, ce qui a été validé : l'utilisateur ne
	 * saisit rien, il demande le tirage suivant, et une version postée permettrait de sauter à
	 * n'importe quel numéro sans rien gagner. Elle reste donc hors de `getPropertiesUpdate()`, et
	 * hors de l'API.
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function replay(CompositionFormula $e): void {

		$e->expects(['plan' => ['structure', 'season', 'reference', 'weeks'], 'version']);

		$e['version'] = $e['version'] + 1;

		CompositionFormula::model()->beginTransaction();

			parent::update($e, ['version']);

			$source = CompositionLib::getSourceByPlan($e['plan']);

			CompositionLib::recompose($e['plan'], $e, $source, $source['pricesPlan']);

		CompositionFormula::model()->commit();

	}

	/**
	 * Ajoute une taille de panier au plan et compose sa proposition, en dernière position.
	 *
	 * Une taille qui s'appuie sur une autre (lot r17) en prend les bornes du nombre de légumes : elle
	 * en reprend toutes les lignes, son plafond ne peut donc pas être plus bas
	 * (`CompositionFormula::linesMax.belowBase`), et le panneau d'ajout ne les demande pas. Elles se
	 * règlent ensuite dans « Modifier les paniers ».
	 *
	 * Deux envois simultanés du même panneau passent tous deux `name.duplicate`, et l'unicité
	 * `(plan, name)` du schéma refuse le second (lot r40) : il rend le même refus que le contrôle, et
	 * rien ne se compose.
	 */
	public static function add(CompositionFormula $e): void {

		$e->expects(['plan', 'name', 'price', 'baskets']);
		$e->add(['base' => new CompositionFormula()]);

		$cCompositionFormula = self::getByPlan($e['plan']);

		$e['position'] = max($cCompositionFormula->getColumn('position')) + 1;

		if($e['base']->notEmpty()) {
			$eCompositionFormulaBase = $e->getBase();
			$e['linesMin'] = $eCompositionFormulaBase['linesMin'];
			$e['linesMax'] = $eCompositionFormulaBase['linesMax'];
		}

		CompositionFormula::model()->beginTransaction();

			try {
				self::create($e);
			} catch(\DuplicateException) {

				CompositionFormula::fail('name.duplicate');

				// L'insertion refusée n'a rien écrit, et `rollBack()` viderait toute la pile, transaction
				// de l'appelant comprise : la transaction ouverte ici se referme par un `commit()`.
				CompositionFormula::model()->commit();

				return;

			}

			$source = CompositionLib::getSourceByPlan($e['plan']);

			CompositionLib::compose($e['plan'], $e, $source, $source['pricesPlan']);

		CompositionFormula::model()->commit();

	}

	/**
	 * La formule emporte ses semaines, comme le plan emporte ses formules.
	 *
	 * Ses dérivées (lot r17) sont détachées et gardent leurs semaines telles qu'elles sont : rien ne se
	 * recompose, et personne n'a demandé de perdre ces paniers. Le détachement ne passe pas par
	 * `update()`, qui recomposerait la dérivée sur une base qu'on quitte.
	 *
	 * ⚠️ Aucun `return` entre `beginTransaction()` et `commit()` : le compteur de transactions
	 * resterait à 1 et le COMMIT ne partirait jamais.
	 */
	public static function delete(CompositionFormula $e): void {

		$e->expects(['id']);

		CompositionFormula::model()->beginTransaction();

			foreach(self::getByBase($e) as $eCompositionFormulaDerived) {

				$eCompositionFormulaDerived['base'] = new CompositionFormula();

				parent::update($eCompositionFormulaDerived, ['base']);

			}

			foreach(CompositionWeekLib::getByFormula($e) as $eCompositionWeek) {
				CompositionWeekLib::delete($eCompositionWeek);
			}

			parent::delete($e);

		CompositionFormula::model()->commit();

	}

}
?>
