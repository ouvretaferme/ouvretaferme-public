<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		// Les gardes de la carte de la Boite (`ToolUi::getList()`) : l'écran lit un plan de culture, que
		// seule une structure productrice tient, et ses réglages écrivent un plan, ce qu'un simple
		// observateur ne doit pas déclencher.
		$data->eStructure->validate('canManage', 'acceptComposition');

		// La formule que l'écran lit : les panneaux la reçoivent et la réémettent, pour que leur réponse
		// rende la même.
		$data->formula = INPUT('formula', '?int');

	})
	// Tant que la structure n'a jamais composé ses paniers, l'outil ouvre son accueil, commun aux
	// outils de la Boîte (planche r2, point 9) : rien ne s'écrit avant l'envoi du départ.
	->get('/ferme/{id}/outils/paniers', function($data) {

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->formula);

		if($data->eCompositionPlan->empty()) {
			throw new ViewAction($data, ':home');
		}

		$data->tab = \toolbox\CompositionPlan::TAB_FORMULA;

		throw new ViewAction($data, ':plan');

	})
	// L'onglet « Synthèse » (planche r2, point 5) : l'année en quantités, toutes tailles additionnées.
	// Sans plan, rien à additionner, et l'adresse mène à l'accueil.
	->get('/ferme/{id}/outils/paniers/synthese', function($data) {

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, NULL, tab: \toolbox\CompositionPlan::TAB_SYNTHESIS);

		if($data->eCompositionPlan->empty()) {
			throw new RedirectAction(\toolbox\CompositionUi::url($data->eStructure));
		}

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);
		$data->tab = \toolbox\CompositionPlan::TAB_SYNTHESIS;

		throw new ViewAction($data, ':plan');

	})
	// L'onglet « Espèces » (r48a, planche r48, question 3, piste B) : les espèces livrées, leur
	// quantité au panier, leur prix et leur saison. Sans plan, l'adresse mène à l'accueil.
	->get('/ferme/{id}/outils/paniers/especes', function($data) {

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, NULL, tab: \toolbox\CompositionPlan::TAB_SPECIES);

		if($data->eCompositionPlan->empty()) {
			throw new RedirectAction(\toolbox\CompositionUi::url($data->eStructure));
		}

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);
		$data->tab = \toolbox\CompositionPlan::TAB_SPECIES;

		throw new ViewAction($data, ':plan');

	})
	// Un onglet ouvert depuis la rangée des onglets (chantier Performances, P14) : la même lecture
	// que les trois adresses au-dessus, dont la réponse ne rend que ce qui dépend de l'onglet, au lieu
	// de la page entière. Les adresses restent celles de la page, qu'un rechargement ou un retour
	// arrière lisent en entier. Seul l'onglet d'une taille nomme sa taille.
	->get('tab', function($data) {

		$data->tab = \toolbox\CompositionPlanLib::getTab(GET('tab', '?string'));
		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, ($data->tab === \toolbox\CompositionPlan::TAB_FORMULA) ? $data->formula : NULL, tab: $data->tab);

		if($data->eCompositionPlan->empty()) {
			throw new RedirectAction(\toolbox\CompositionUi::url($data->eStructure));
		}

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);

		throw new ViewAction($data);

	})
	// « Composer mes paniers » depuis l'accueil, « Recommencer les paniers » depuis la roue crantée
	// ou un autre plan de culture (`season`) depuis le menu des saisons en recommençant : le départ,
	// avec les réponses du plan déjà posées quand il y en a un. L'ouvrir ne touche pas au plan, c'est
	// l'envoi du départ qui l'écrit.
	->get('/ferme/{id}/outils/paniers/depart', function($data) {

		$season = GET('season', '?int');

		if($season !== NULL and $data->eStructure->conf(\map\Configuration::class)->checkSeason($season) === FALSE) {
			throw new NotExistsAction('Season '.$season);
		}

		$data->cFavorite = \toolbox\FavoriteLib::getByStructure($data->eStructure);
		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForStart($data->eStructure, $season);

		throw new ViewAction($data, ':start');

	})
	// Les adresses d'avant la Boîte à outils, dont celles où la saison rangeait les paniers : chacune
	// renvoie à sa place dans l'outil, sa requête avec elle (le plan de culture du départ). `@int`
	// sépare la saison de `synthese` et de `depart`, qui occupent le même rang.
	->get('/ferme/{id}/paniers', function($data) {
		throw new PermanentRedirectAction(\toolbox\CompositionUi::url($data->eStructure).LIME_REQUEST_ARGS);
	})
	->get('/ferme/{id}/paniers/synthese', function($data) {
		throw new PermanentRedirectAction(\toolbox\CompositionUi::urlSynthesis($data->eStructure));
	})
	->get('/ferme/{id}/paniers/depart', function($data) {
		throw new PermanentRedirectAction(\toolbox\CompositionUi::urlStart($data->eStructure).LIME_REQUEST_ARGS);
	})
	->get('/ferme/{id}/paniers/{season@int}', function($data) {
		throw new PermanentRedirectAction(\toolbox\CompositionUi::url($data->eStructure));
	})
	->get('/ferme/{id}/paniers/{season@int}/synthese', function($data) {
		throw new PermanentRedirectAction(\toolbox\CompositionUi::urlSynthesis($data->eStructure));
	})
	// L'envoi du départ : le plan, ses tailles de panier et ses espèces se construisent ensemble, et
	// un refus revient sur le champ qui le porte, dans son étape. Les espèces se lisent sur la saison
	// du plan de culture et sur le plan qu'on recommence (`CompositionPlanLib::getStartPlantIds()`),
	// et non sur ce qui est posté : un index qui ne désigne aucune de ces espèces n'est jamais
	// construit, et une espèce absente de l'envoi est refusée par le contrôle de `status`.
	//
	// Le panneau « Ajouter une espèce » du départ (r48e, `addStartSpecies`) n'écrit rien : les espèces
	// qu'il ajoute voyagent ici (`added`, des espèces de la structure), et chacune se relit sur la
	// structure (`CompositionSpeciesLib::getForStartAdd()`), qui écarte celle qui n'est pas la sienne
	// et celle que le départ construit déjà. Une espèce que la communauté ne compte pas se construit
	// avec son unité, sa quantité au panier et son prix, sous son index.
	//
	// Seule une espèce ajoutée qu'aucune source ne datait poste une saison, tracée aux poignées du
	// panneau (r67, `windowWeeks[<espèce>]`), et elle part avec elle, réglée à la main. Le serveur ne
	// sait plus si l'espèce était datée à l'ajout : toute saison postée pour une espèce ajoutée
	// s'écrit donc réglée à la main, celles de la liste n'en lisant aucune. Elle est refusée quand
	// elle ne croise aucune semaine livrée (r69) : le panneau ne connaît pas encore ces semaines, que
	// seul cet envoi porte, et l'espèce partirait sans qu'aucun panier la serve. Une espèce
	// ajoutée qu'une source datait et que le choix envoyé ne date plus
	// (`CompositionSpeciesLib::isUndatedAtStart()`) est refusée : elle partirait sans saison, et aucun
	// panier ne la servirait. Une espèce retirée n'en a pas.
	->post('doStart', function($data) {

		$eCompositionPlan = new \toolbox\CompositionPlan([
			'structure' => $data->eStructure,
		]);

		$eCompositionPlan->validate('canCreate', 'acceptCreate');

		$fw = new \FailWatch();

		// Les espèces proposables se lisent sur la saison, et le climat sur ce que le plan suit, le
		// calendrier type ou le plan de culture : les deux doivent tenir avant ce qui les lit.
		$eCompositionPlan->build(['season', 'reference'], $_POST);

		$fw->validate();

		$eCompositionPlan->build(['climate', 'weeks'], $_POST);

		$cCompositionFormula = new \Collection();

		foreach(array_keys(POST('name', 'array')) as $index) {

			$eCompositionFormula = new \toolbox\CompositionFormula([
				'plan' => $eCompositionPlan,
				'base' => new \toolbox\CompositionFormula(),
			]);

			$eCompositionFormula->buildIndex(['name', 'price', 'baskets'], $_POST, $index, new \PropertyList('create'));

			$cCompositionFormula[] = $eCompositionFormula;

		}

		if($cCompositionFormula->empty()) {
			\Fail::log('toolbox\CompositionPlan::formulas.check');
		}

		$cCompositionSpecies = new \Collection();

		foreach(\toolbox\CompositionPlanLib::getStartPlantIds($data->eStructure, $eCompositionPlan['season']) as $plantId) {

			$eCompositionSpecies = new \toolbox\CompositionSpecies([
				'plan' => $eCompositionPlan,
				'plant' => new \plant\Plant(['id' => $plantId]),
				'name' => NULL,
				'windowWeeks' => NULL,
			]);

			$eCompositionSpecies->buildIndex(['status'], $_POST, $plantId);

			$cCompositionSpecies[$plantId] = $eCompositionSpecies;

		}

		$cCompositionSpeciesAdded = new \Collection();

		foreach(array_map('intval', POST('added', 'array')) as $plantIdStructure) {

			$eCompositionSpecies = \toolbox\CompositionSpeciesLib::getForStartAdd($eCompositionPlan, $plantIdStructure, $cCompositionSpecies->getKeys());

			if($eCompositionSpecies->empty()) {
				continue;
			}

			$plantId = $eCompositionSpecies['plant']['id'];

			$eCompositionSpecies->buildIndex(['status'], $_POST, $plantId);

			// Ce que le panneau a demandé (`CompositionSpeciesLib::loadStartAdd()`, `typed`) : une
			// espèce que le plan qu'on recommence porte reprend les siens (`restart()`).
			if($eCompositionSpecies['written'] === FALSE and $eCompositionSpecies->isCommunity() === FALSE) {
				$eCompositionSpecies->buildIndex(['unit', 'quantityBasket', 'price'], $_POST, $plantId);
			}

			$cCompositionSpecies[$plantId] = $eCompositionSpecies;
			$cCompositionSpeciesAdded[$plantId] = $eCompositionSpecies;

		}

		// La saison se lit sur le statut construit et sur le climat : les deux doivent tenir avant elle.
		$fw->validate();

		\toolbox\CompositionPlanLib::loadStart($eCompositionPlan);

		$windowWeeksTraced = POST('windowWeeks', 'array');

		$cCompositionSpeciesTraced = new \Collection();

		foreach($cCompositionSpeciesAdded as $plantId => $eCompositionSpecies) {

			if($eCompositionSpecies['status'] === \toolbox\CompositionSpecies::EXCLUDED) {
				continue;
			}

			if(array_key_exists($plantId, $windowWeeksTraced)) {
				\toolbox\CompositionSpeciesLib::buildStartSeason($eCompositionSpecies, (array)$windowWeeksTraced[$plantId]);
				$cCompositionSpeciesTraced[$plantId] = $eCompositionSpecies;
			} else if(\toolbox\CompositionSpeciesLib::isUndatedAtStart($eCompositionSpecies)) {
				\toolbox\CompositionSpecies::fail('windowWeeks.undated', [$eCompositionSpecies['plant']]);
			}

		}

		$fw->validate();

		foreach($cCompositionSpeciesTraced as $eCompositionSpecies) {
			\toolbox\CompositionSpeciesLib::checkStartDelivered($eCompositionSpecies, $eCompositionPlan['weeks']);
		}

		$fw->validate();

		$eCompositionPlan['cCompositionFormula'] = $cCompositionFormula;
		$eCompositionPlan['cCompositionSpecies'] = $cCompositionSpecies;

		if(\toolbox\CompositionPlanLib::getByStructure($data->eStructure)->notEmpty()) {
			\toolbox\CompositionPlanLib::restart($eCompositionPlan);
		} else {
			\toolbox\CompositionPlanLib::create($eCompositionPlan);
		}

		throw new RedirectAction(\toolbox\CompositionUi::url($data->eStructure));

	})
	->get('weeks', function($data) {

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$data->eCompositionPlan['structure'] = $data->eStructure;
		$data->eCompositionPlan['seasonMonths'] = \toolbox\CompositionLib::getSeasonMonths($data->eCompositionPlan['season']);
		$data->eCompositionPlan['formulasAmended'] = \toolbox\CompositionPlanLib::getFormulasAmended($data->eCompositionPlan);

		$data->origin = \toolbox\CompositionPlanLib::getTab(GET('origin', '?string'));

		throw new ViewAction($data);

	})
	->post('doWeeks', function($data) {

		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$eCompositionPlan['structure'] = $data->eStructure;

		$eCompositionPlan->validate('canUpdate', 'acceptUpdate');

		$fw = new \FailWatch();

		$eCompositionPlan->build(['weeks'], $_POST);

		$fw->validate();

		\toolbox\CompositionPlanLib::update($eCompositionPlan, ['weeks']);

		// La carte des semaines est au-dessus des onglets : la réponse rend celui d'où part sa roue.
		$data->tab = \toolbox\CompositionPlanLib::getTab(POST('origin', '?string'));
		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->formula, tab: $data->tab);

		throw new ViewAction($data);

	})
	// « Retirer la semaine » et « Retirer ces semaines » (lot r22, point 4) sur une soudure : les
	// semaines livrées de `from` à `to` sortent du plan, dans toutes les tailles, comme en les
	// décochant dans le panneau des semaines livrées. Les semaines restantes passent par le même
	// contrôle, qui refuse un plan sans semaine.
	->post('doRemoveWeeks', function($data) {

		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$eCompositionPlan['structure'] = $data->eStructure;

		$eCompositionPlan->validate('canUpdate', 'acceptUpdate');

		$from = POST('from', 'int');
		$to = POST('to', 'int');

		$fw = new \FailWatch();

		$eCompositionPlan->build(['weeks'], [
			'weeks' => array_values(array_filter($eCompositionPlan['weeks'], fn(int $week) => ($week < $from or $week > $to))),
		]);

		$fw->validate();

		\toolbox\CompositionPlanLib::update($eCompositionPlan, ['weeks']);

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->formula);

		throw new ViewAction($data);

	})
	// « Modifier les paniers » (r31, #96), ouvert par la roue du titre « Les paniers » : toutes les
	// tailles, et « Recommencer les paniers », dont la confirmation chiffre ce que le départ remplacera.
	// `origin` dit l'onglet d'où part la roue, que la réponse rend.
	->get('settings', function($data) {

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$data->eCompositionPlan['structure'] = $data->eStructure;

		$data->eCompositionPlan->validate('canUpdate', 'acceptUpdate');

		\toolbox\CompositionPlanLib::loadSettings($data->eCompositionPlan);

		$data->origin = GET('origin');

		throw new ViewAction($data);

	})
	// Chaque taille du plan se construit sur ses propres champs (`price[<id>]`), lus par
	// `buildIndex()` : un index qui ne désigne aucune taille du plan n'est jamais construit. La base
	// après le prix et le plafond : ses refus `cheaper` et `lines` comparent les valeurs construites.
	->post('doSettings', function($data) {

		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$eCompositionPlan['structure'] = $data->eStructure;

		$eCompositionPlan->validate('canUpdate', 'acceptUpdate');

		$cCompositionFormula = \toolbox\CompositionFormulaLib::getByPlan($eCompositionPlan);
		$cCompositionFormula->setColumn('plan', $eCompositionPlan);

		$fw = new \FailWatch();

		foreach($cCompositionFormula as $formulaId => $eCompositionFormula) {
			$eCompositionFormula->buildIndex(['name', 'price', 'baskets', 'gap', 'linesMin', 'linesMax', 'base'], $_POST, $formulaId);
		}

		$fw->validate();

		\toolbox\CompositionFormulaLib::updateByPlan($eCompositionPlan, $cCompositionFormula);

		$data->tab = \toolbox\CompositionPlanLib::getTab(POST('origin', '?string'));
		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, $data->formula, tab: $data->tab);

		throw new ViewAction($data);

	})
	// « Appliquer le plan de culture », depuis le deck « Plan de culture » en tête de l'onglet
	// « Espèces » (lot r27, r29 #80, r48a, r51b) : les espèces cochées prennent la saison du plan de
	// culture, les décochées gardent leurs mois, et les semaines non corrigées se recomposent.
	// L'envoi ne porte que des identifiants d'espèces, que le Lib confronte à celles que le deck
	// liste : aucune valeur de propriété n'est postée. L'onglet se redessine, sans le deck, qui n'a
	// plus rien à compter.
	->post('doSynchronize', function($data) {

		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$eCompositionPlan['structure'] = $data->eStructure;

		$eCompositionPlan->validate('canUpdate', 'acceptUpdate');

		$fw = new \FailWatch();

		\toolbox\CompositionPlanLib::synchronize($eCompositionPlan, array_map('intval', POST('species', 'array')));

		$fw->validate();

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, NULL, tab: \toolbox\CompositionPlan::TAB_SPECIES);

		throw new ViewAction($data);

	})
	// « Ajouter une espèce » (r48c, planche r48), depuis le titre de l'onglet « Espèces » : le panneau
	// se rend d'abord avec le seul champ de l'espèce, puis avec ce qu'il lui manque pour l'espèce
	// choisie (`plant`, une espèce de la structure), chaque fois que ce choix ou celui du climat
	// change. Une espèce qui ne s'ajoute pas (`CompositionSpeciesLib::getForAdd()`) rend le panneau
	// sans espèce.
	//
	// Une espèce que le plan livre déjà s'y ajoute en variante (r76 lot 2) : depuis le champ de
	// l'espèce (piste B), ou depuis le « + » du rang de l'espèce nue dans l'onglet (piste C), qui
	// désigne l'espèce du plan (`species`) et non celle de la structure. Le panneau s'ouvre alors sur
	// la copie que la structure en a (`CompositionSpeciesLib::getPlantStructureId()`).
	->get('addSpecies', function($data) {

		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$eCompositionPlan['structure'] = $data->eStructure;

		$eCompositionPlan->validate('canUpdate', 'acceptUpdate');

		$speciesId = GET('species', '?int');

		$plantId = ($speciesId === NULL) ? GET('plant', '?int') : \toolbox\CompositionSpeciesLib::getPlantStructureId($eCompositionPlan, $speciesId);

		$data->eCompositionSpecies = ($plantId === NULL) ? new \toolbox\CompositionSpecies() : \toolbox\CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantId);

		if($data->eCompositionSpecies->empty()) {

			$data->eCompositionSpecies = new \toolbox\CompositionSpecies([
				'plan' => $eCompositionPlan,
				'plant' => new \plant\Plant(),
				'ePlantStructure' => new \plant\Plant(),
				'eCompositionSpeciesBare' => new \toolbox\CompositionSpecies(),
			]);

		} else {

			$climate = GET('climate', '?string');

			\toolbox\CompositionSpeciesLib::loadAdd($data->eCompositionSpecies, in_array($climate, \toolbox\CompositionPlan::model()->getPropertyEnum('climate'), TRUE) ? $climate : NULL);

		}

		$data->eCompositionSpecies['plantIdsAddable'] = \toolbox\CompositionSpeciesLib::getAddable($eCompositionPlan);

		// Le « + » d'un rang (r76 lot 2, piste C) ouvre « Ajouter une variante » sur l'espèce de ce
		// rang, sans choix d'espèce. Une espèce retirée depuis l'ouverture du lien rouvre l'ajout
		// ordinaire, où elle se choisit de nouveau.
		$data->variant = ($speciesId !== NULL and $data->eCompositionSpecies['eCompositionSpeciesBare']->notEmpty());

		throw new ViewAction($data);

	})
	// L'envoi du panneau : l'espèce postée est une espèce en activité de la structure
	// (`CompositionSpeciesLib::getForAdd()`, qui le vérifie sur la ligne lue), sinon rien ne se
	// construit. Le climat ne se construit que lorsque le panneau le demande
	// (`CompositionSpeciesLib::isClimateAsked()`), et avant la saison, qui se lit dessus. Les champs de
	// « Régler une espèce » se construisent ensuite comme dans son panneau (r60,
	// `compositionSpecies:doUpdate`) : l'unité seule d'abord, parce que les produits que le produit
	// vendu peut désigner se vendent dans l'unité choisie, puis le reste
	// (`CompositionSpeciesLib::getPropertiesPanel()`). La saison est celle que les poignées du panneau
	// postent (r59), réglée à la main quand elle diffère de celle de la source de l'espèce
	// (`CompositionSpeciesLib::getSeasonAdded()`), et c'est `CompositionSpeciesLib::add()` qui refuse
	// une espèce sans saison.
	//
	// Une variante (r76 lot 2) construit d'abord son nom, que la construction de l'espèce compare aux
	// autres espèces du plan sur la même fiche (`plant.duplicate`). Le nom posté retrouve aussi une
	// variante que le plan porte retirée sous ce nom, qui revient avec ses réglages. Elle n'a pas de
	// climat à demander, et son unité doit être cochée (`unit.chosen`).
	->post('doAddSpecies', function($data) {

		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$eCompositionPlan['structure'] = $data->eStructure;

		$eCompositionPlan->validate('canUpdate', 'acceptUpdate');

		$fw = new \FailWatch();

		$eCompositionSpecies = \toolbox\CompositionSpeciesLib::getForAdd($eCompositionPlan, POST('plant', 'int'), POST('name', '?string'));

		if($eCompositionSpecies->empty()) {
			\Fail::log('toolbox\CompositionSpecies::plant.addable');
		}

		$fw->validate();

		$variant = $eCompositionSpecies['eCompositionSpeciesBare']->notEmpty();

		if($variant) {

			$eCompositionSpecies->build(['name'], $_POST);

			$fw->validate();

		}

		// L'espèce postée est celle de la structure, l'espèce du plan est celle que `getForAdd()` en
		// déduit : c'est elle qui se construit, pour que deux envois de la même espèce que le plan ne
		// porte pas encore ne l'écrivent pas deux fois (`plant.duplicate`). Deux envois à la même
		// milliseconde passent tous deux, sans verrou (arbitré sous mandat le 04/10/2026).
		$eCompositionSpecies->build(['plant'], ['plant' => $eCompositionSpecies['plant']]);

		$fw->validate();

		$climate = ($variant === FALSE and \toolbox\CompositionSpeciesLib::isClimateAsked($eCompositionSpecies));

		if($climate) {
			$eCompositionPlan->build(['climate'], $_POST, new \PropertyList('update'));
		}

		$fw->validate();

		$eCompositionSpecies->build(['unit'], $_POST, new \PropertyList(origin: $variant ? 'variant' : NULL));

		$fw->validate();

		\toolbox\CompositionSpeciesLib::loadProducts($eCompositionSpecies);

		$properties = \toolbox\CompositionSpeciesLib::getPropertiesPanel($eCompositionSpecies, added: TRUE);

		$eCompositionSpecies->build($properties, $_POST);

		$eCompositionSpecies->build(['status', 'windowWeeks'], \toolbox\CompositionSpeciesLib::getSeasonAdded($eCompositionSpecies, POST('windowWeeks', 'array')));

		$fw->validate();

		\toolbox\CompositionSpeciesLib::add($eCompositionSpecies, $climate, $properties);

		$fw->validate();

		$data->eCompositionSpecies = $eCompositionSpecies;
		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->eStructure, NULL, tab: \toolbox\CompositionPlan::TAB_SPECIES);

		throw new ViewAction($data);

	})
	// La recherche du champ « Produit vendu » du panneau (r60), comme celle de « Régler une espèce »
	// (`compositionSpecies:queryProduct`) pour une espèce que le plan n'a pas encore écrite : elle se
	// relit par l'espèce de la structure, que le panneau renvoie, et se garde comme lui. Elle cherche
	// dans l'unité cochée, que `composition.js` poste avec la requête, et que `build()` contrôle comme
	// à l'envoi : une espèce typée n'en a pas d'autre.
	->post('queryAddProduct', function($data) {

		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->eStructure)->validate();
		$eCompositionPlan['structure'] = $data->eStructure;

		$eCompositionPlan->validate('canUpdate', 'acceptUpdate');

		$data->e = \toolbox\CompositionSpeciesLib::getForAdd($eCompositionPlan, POST('plant', 'int'));

		if($data->e->empty()) {
			throw new NotExistsAction('CompositionSpecies');
		}

		$fw = new \FailWatch();

		$data->e->build(['unit'], $_POST);

		$fw->validate();

		\toolbox\CompositionSpeciesLib::loadAddProducts($data->e);

		$data->cProduct = \toolbox\CompositionSpeciesLib::getProductsFromQuery($data->e, POST('query'));

		throw new ViewAction($data);

	})
	// « Ajouter une espèce » depuis la liste du départ (r48e, planche r48, verdict 2 B) : le même
	// panneau que dans l'onglet « Espèces », pour un plan qui n'est pas encore écrit. Le lien porte
	// ce que le formulaire du départ montre, la saison, l'origine des saisons, le climat et les
	// espèces livrées (`delivered`), et le panneau se rend sur eux : toute espèce de la structure que
	// la liste ne livre pas s'ajoute, celle que la corbeille vient de retirer comprise.
	->get('addStartSpecies', function($data) {

		$eCompositionPlan = new \toolbox\CompositionPlan([
			'structure' => $data->eStructure,
		]);

		$eCompositionPlan->validate('canCreate', 'acceptCreate');

		$fw = new \FailWatch();

		$eCompositionPlan->build(['season', 'reference'], $_GET);

		$fw->validate();

		$eCompositionPlan->build(['climate'], $_GET);

		$fw->validate();

		\toolbox\CompositionPlanLib::loadStart($eCompositionPlan);

		$plantIdsDelivered = array_map('intval', explode(',', GET('delivered')));
		$plantId = GET('plant', '?int');

		$data->eCompositionSpecies = ($plantId === NULL) ? new \toolbox\CompositionSpecies() : \toolbox\CompositionSpeciesLib::getForStartAdd($eCompositionPlan, $plantId, $plantIdsDelivered);

		if($data->eCompositionSpecies->empty()) {

			$data->eCompositionSpecies = new \toolbox\CompositionSpecies([
				'plan' => $eCompositionPlan,
				'plant' => new \plant\Plant(),
				'ePlantStructure' => new \plant\Plant(),
			]);

		} else {
			\toolbox\CompositionSpeciesLib::loadStartAdd($data->eCompositionSpecies);
		}

		$data->eCompositionSpecies['plantIdsAddable'] = \toolbox\CompositionSpeciesLib::getAddableByDelivered($data->eStructure, $plantIdsDelivered);
		$data->delivered = implode(',', $plantIdsDelivered);

		throw new ViewAction($data);

	})
	// L'envoi du panneau au départ : rien ne s'écrit, l'espèce entre dans la liste du départ et part
	// avec son formulaire (`doStart`). Tout ce que `doStart` construira d'elle se construit ici une
	// première fois, pour que le refus tombe dans le panneau, sur son champ : l'unité, la quantité au
	// panier et le prix d'une espèce que la communauté ne compte pas, et la saison tracée aux poignées
	// d'une espèce qu'aucune source ne date (r67). Celle d'une espèce qu'une source date se lit sans
	// se régler (r58).
	->post('doAddStartSpecies', function($data) {

		$eCompositionPlan = new \toolbox\CompositionPlan([
			'structure' => $data->eStructure,
		]);

		$eCompositionPlan->validate('canCreate', 'acceptCreate');

		$fw = new \FailWatch();

		$eCompositionPlan->build(['season', 'reference'], $_POST);

		$fw->validate();

		$eCompositionPlan->build(['climate'], $_POST);

		$fw->validate();

		\toolbox\CompositionPlanLib::loadStart($eCompositionPlan);

		$eCompositionSpecies = \toolbox\CompositionSpeciesLib::getForStartAdd($eCompositionPlan, POST('plant', 'int'), array_map('intval', explode(',', POST('delivered'))));

		if($eCompositionSpecies->empty()) {
			\Fail::log('toolbox\CompositionSpecies::plant.addable');
		}

		$fw->validate();

		\toolbox\CompositionSpeciesLib::loadStartAdd($eCompositionSpecies);

		if($eCompositionSpecies['typed']) {
			$eCompositionSpecies->build(['unit', 'quantityBasket', 'price'], $_POST);
		}

		if($eCompositionSpecies['undated']) {
			\toolbox\CompositionSpeciesLib::buildStartSeason($eCompositionSpecies, POST('windowWeeks', 'array'));
		} else {
			$eCompositionSpecies->build(['status'], ['status' => \toolbox\CompositionSpecies::NATURAL]);
		}

		$fw->validate();

		if($eCompositionSpecies['undated']) {
			\toolbox\CompositionSpeciesLib::loadStartTraced($eCompositionSpecies);
		}

		$data->eCompositionPlan = $eCompositionPlan;
		$data->eCompositionSpecies = $eCompositionSpecies;

		throw new ViewAction($data);

	});

new \structure\StructurePage()
	->remote('getPdf', 'selling', function($data) {

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForPdf($data->e);

		if($data->eCompositionPlan->empty()) {
			throw new NotExistsAction('CompositionPlan');
		}

		throw new ViewAction($data);

	})
	->read('downloadPdf', function($data) {

		$eCompositionPlan = \toolbox\CompositionPlanLib::getByStructure($data->e)->validate();

		$filename = \toolbox\CompositionPdfUi::getFilename($eCompositionPlan['season']);
		$content = \main\PdfLib::build('/toolbox/composition:getPdf?id='.$data->e['id'], $filename, footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, $filename);

	}, validate: ['canManage', 'acceptComposition'])
	// La présentation de l'outil (r36) se lit par tout membre, observateur compris, là où la page
	// au-dessus garde les réglages : elle ne montre que des morceaux de l'écran, sans geste. Sans
	// plan, ses étapes gardent leur phrase seule (`CompositionPlanLib::getNotice()`).
	->read('notice', function($data) {

		$data->eCompositionPlan = \toolbox\CompositionPlanLib::getForScreen($data->e, NULL);
		$data->notice = \toolbox\CompositionPlanLib::getNotice($data->eCompositionPlan);

		throw new ViewAction($data);

	}, addValidate: ['acceptComposition']);
?>
