<?php
/**
 * Une espèce du plan, depuis son nom ou sa roue crantée dans la grille : le panneau « Régler une
 * espèce » (lot r22e, planche r22, question 1), qui dit son état en tuiles et règle d'un seul
 * enregistrement son unité au panier et le poids de cette unité (lot r14), son prix (lot M), son pas
 * de vente et sa quantité minimale (planche r1), sa fréquence (lot L9), et la retire des cultures.
 * Le prix se règle aussi au bout du nom, la fréquence depuis son picto, et le poids depuis la
 * synthèse, qui le dit en passant `origin` : la réponse rend le rang d'où il est parti. Le panneau
 * s'ouvre aussi depuis le nom de l'espèce dans l'onglet « Espèces » (r48a), où se règlent sa saison
 * en douze mois et son retrait : `origin` y nomme l'onglet
 * (`CompositionPlanLib::getTab()`), et la réponse rend le rang de l'espèce dans son tableau.
 *
 * L'espèce se charge par l'`id` posté, ou passé à l'adresse du panneau, et son plan doit être celui
 * de la structure connectée, qui se charge avant elle : c'est ce qui borne le geste à ce que la
 * structure possède. La formule postée n'est que celle que l'écran lit, pour que la réponse rende
 * la même.
 */
new \toolbox\CompositionSpeciesPage(function($data) {

	\structure\StructureLib::load($data);

	$data->eStructure->validate('canManage', 'acceptComposition');

})
	->getElement(function($data) {

		return \toolbox\CompositionSpeciesLib::getById(INPUT('id', 'int'), \toolbox\CompositionSpecies::getSelection() + [
			'plan' => \toolbox\CompositionPlan::getSelection(),
			'product' => ['name', 'variety'],
		])->validate();

	})
	->applyElement(function($data, \toolbox\CompositionSpecies $e) {

		if($e['plan']['structure']['id'] !== $data->eStructure['id']) {
			throw new NotExistsAction('CompositionSpecies');
		}

		$e['plan']['structure'] = $data->eStructure;

		\toolbox\CompositionSpeciesLib::applySeasonsUnwritten($e['plan'], new \Collection([$e]));

	})
	// Le panneau dit l'état de l'espèce en tuiles, puis ses réglages : ce que la grille en lit, les
	// unités proposées et le poids de chacune (lot r14), et ce que la taille lue sert de l'espèce,
	// que la fréquence écrit sous « Selon la saison » (lot L9). Une espèce retirée ne règle ni sa
	// saison (r61) ni sa fréquence.
	->update(function($data) {

		\toolbox\CompositionSpeciesLib::loadPriced($data->e);
		\toolbox\CompositionSpeciesLib::loadUnits($data->e);
		\toolbox\CompositionSpeciesLib::loadProducts($data->e);

		if($data->e['status'] !== \toolbox\CompositionSpecies::EXCLUDED) {
			\toolbox\CompositionSpeciesLib::loadSeasonAxis($data->e['plan']);
			\toolbox\CompositionSpeciesLib::loadFrequency($data->e, GET('formula', '?int'));
		}

		$data->formula = GET('formula', '?int');
		$data->origin = GET('origin', '?string');

		throw new ViewAction($data);

	})
	// La recherche du champ « Produit vendu » (r53) : les candidats que le panneau proposait en liste,
	// dans l'unité où l'espèce entre aujourd'hui, et la création de produit en dernier. Elle sert le
	// formulaire du panneau, et se garde comme lui.
	->read('queryProduct', function($data) {

		\toolbox\CompositionSpeciesLib::loadPriced($data->e);
		\toolbox\CompositionSpeciesLib::loadProducts($data->e);

		$data->cProduct = \toolbox\CompositionSpeciesLib::getProductsFromQuery($data->e, POST('query'));

		throw new ViewAction($data);

	}, 'post', validate: ['canUpdate', 'acceptUpdate'])
	// Un seul « Enregistrer » pour tout le panneau, donc une seule recomposition
	// (`CompositionSpeciesLib::update()`), et seulement de ce qui a changé : le panneau poste tous ses
	// champs. L'unité se construit d'abord seule, parce que ce qui se construit ensuite dépend de ce
	// qu'elle change (`CompositionSpeciesLib::getPropertiesPanel()`), y compris les produits que le
	// produit vendu peut désigner, qui se vendent dans l'unité choisie (r39b).
	//
	// La saison réglée aux poignées (r61) se construit comme à l'ajout, à part des réglages, parce que
	// le panneau ne poste pas l'état qu'elle donne : `CUSTOM` si elle a changé, l'état écrit sinon
	// (`CompositionSpeciesLib::getSeasonUpdated()`). Elle s'écrit avant eux (`updatePanel()`).
	//
	// Le panneau d'une variante (r76 lot 2) poste aussi son nom, qui s'y corrige.
	->write('doUpdate', function($data) {

		$fw = new \FailWatch();

		\toolbox\CompositionSpeciesLib::loadPriced($data->e);

		$data->e->build(['unit'], $_POST);

		// Ce qui suit se lit sur l'unité construite : une espèce que la communauté ne compte pas (r48c)
		// n'en a pas d'autre, et sans elle il n'y a ni pas ni produits à lire.
		$fw->validate();

		\toolbox\CompositionSpeciesLib::loadProducts($data->e);

		$properties = \toolbox\CompositionSpeciesLib::getPropertiesPanel($data->e);

		if($data->e['name'] !== NULL) {
			$properties[] = 'name';
		}

		$data->e->build($properties, $_POST);

		$cultivated = ($data->e['status'] !== \toolbox\CompositionSpecies::EXCLUDED);

		if($cultivated) {
			$data->e->build(['status', 'windowWeeks'], \toolbox\CompositionSpeciesLib::getSeasonUpdated($data->e, POST('windowWeeks', '?array')));
		}

		$fw->validate();

		$propertiesChanged = \toolbox\CompositionSpeciesLib::getPropertiesChanged($data->e, array_unique(array_merge(['unit'], $properties)));

		$data->seasoned = ($cultivated and \toolbox\CompositionSpeciesLib::getPropertiesChanged($data->e, ['status', 'windowWeeks']) !== []);

		$data->weeks = \toolbox\CompositionSpeciesLib::updatePanel($data->e, $propertiesChanged, $data->seasoned);

		$data->tab = \toolbox\CompositionPlanLib::getTab(POST('origin', '?string'));
		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, POST('formula', '?int'), tab: $data->tab);

		$data->recomposed = \toolbox\CompositionSpeciesLib::isRecomposing($data->e, $propertiesChanged);
		// Un nom changé se lit sur le rang de l'espèce et sur les bulles et les cartes des semaines où
		// elle entre, que le rendu d'un prix changé reprend tous.
		$data->priced = (array_intersect(['price', 'step', 'minimum', 'product', 'name'], $propertiesChanged) !== []);
		$data->weighed = in_array('weight', $propertiesChanged);

		if($data->recomposed) {
			$data->changes = $data->e['recomposed'][$data->eCompositionPlan['eCompositionFormula']['id']];
		}

		throw new ViewAction($data);

	}, validate: ['canUpdate', 'acceptUpdate'])
	// Le prix au bout du nom (lot r21) et le poids de la synthèse (lot L3) : le champ du prix écrit
	// celui qui s'applique aujourd'hui et d'où il vient, celui du poids le poids des cultures en
	// attente. La fréquence n'a pas de formulaire : le menu de son picto poste directement le choix
	// cliqué (r34c, `CompositionSpeciesUi::getGridFrequency()`).
	->quick(['price', 'weight', 'frequency'], array_fill_keys(['price', 'weight'], fn($data) => \toolbox\CompositionSpeciesLib::loadPriced($data->e)), action: function($data) {

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, POST('formula', '?int'));

		// Un poids ne change que le rang qui le dit : son rang dans la grille, ou ses kilos dans la
		// synthèse. Une fréquence recompose les paniers de toutes les tailles, et seul ce que la
		// taille ouverte a changé se rend (lot r28). Le prix change aussi la valeur des semaines. Le
		// poids d'une unité choisie, qui convertit sa quantité et ses prix, recompose comme une
		// fréquence (`CompositionSpeciesLib::update()`).
		$data->synthesis = (POST('origin') === 'synthesis');

		if(\toolbox\CompositionSpeciesLib::isRecomposing($data->e, [POST('property')])) {
			$data->changes = $data->e['recomposed'][$data->eCompositionPlan['eCompositionFormula']['id']];
		}

		throw new ViewAction($data, match(POST('property')) {
			'weight' => $data->e['unit']->empty() ? 'toolbox/compositionSpecies:doQuickWeight' : 'toolbox/compositionSpecies:doQuickUnit',
			'frequency' => 'toolbox/compositionSpecies:doQuickFrequency',
			'price' => 'toolbox/compositionSpecies:doQuick',
		});

	})
	// La saison de vente étirée, déplacée ou ouverte sur la grille (lot r27) : le navigateur poste la
	// saison entière, en intervalles, et l'espèce passe à `CUSTOM`. Une saison raccourcie refait les
	// semaines qu'elle quitte (`CompositionSpeciesLib::updateSeason()`), et la réponse ne rend que ce
	// qu'elles changent.
	->write('doUpdateSeason', function($data) {

		$fw = new \FailWatch();

		$data->e->build(['status', 'windowWeeks'], [
			'status' => \toolbox\CompositionSpecies::CUSTOM,
			'windowWeeks' => POST('windowWeeks', 'array'),
		]);

		$fw->validate();

		$data->weeks = \toolbox\CompositionSpeciesLib::updateSeason($data->e);

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, POST('formula', '?int'));

		throw new ViewAction($data);

	}, validate: ['canUpdate', 'acceptUpdate'])
	// « Retirer de mes cultures » et la corbeille de l'onglet « Espèces » (r48a) ne lisent aucune
	// valeur postée : l'espèce passe à `EXCLUDED`, par `build()`.
	->write('doExclude', function($data) {

		$fw = new \FailWatch();

		$data->e->build(['status'], ['status' => \toolbox\CompositionSpecies::EXCLUDED]);

		$fw->validate();

		\toolbox\CompositionSpeciesLib::exclude($data->e);

		$data->tab = \toolbox\CompositionPlanLib::getTab(POST('origin', '?string'));
		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, POST('formula', '?int'), tab: $data->tab);
		$data->changes = $data->e['recomposed'][$data->eCompositionPlan['eCompositionFormula']['id']];

		throw new ViewAction($data);

	}, validate: ['canUpdate', 'acceptUpdate']);
?>
