<?php
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructureSource = \structure\StructureLib::getById(INPUT('structure'));
		$data->eStructureCommunity = \structure\StructureLib::getById(INPUT('community'));

		// ⚠️ Le droit se pèse **sur le groupe que l'adresse nomme**, et non sur l'union des groupes :
		// `canExportProduction()` répond oui dès qu'un groupe quelconque accorde, donc elle
		// laisserait passer un identifiant de groupe étranger à l'emport en cours — celui-là même
		// qui décide ensuite des fermes projet proposées à l'arrivée.
		//
		// Le contrôle vit ici et non dans l'écran : l'écran ne montre le menu que si le droit et
		// l'autorisation sont réunis, mais une adresse se tape et un POST se forge.
		if(
			$data->eStructureSource->empty() or
			$data->eStructureCommunity->empty() or
			$data->eStructureSource->canExportProductionByCommunity($data->eStructureCommunity) === FALSE
		) {
			throw new NotAllowedAction();
		}

		$data->mode = INPUT('mode', 'string', \series\CopyAiLib::MINE);

		if(in_array($data->mode, [\series\CopyAiLib::MINE, \series\CopyAiLib::PROJECT, \series\CopyAiLib::CSV], TRUE) === FALSE) {
			throw new NotExpectedAction('Unknown copy mode');
		}

		$data->seasons = \series\CopyAiLib::getSeasons($data->eStructureSource);

		// La sélection cochée sur le plan du pair, relue contre sa ferme et sa saison : ce qui
		// n'appartient pas aux deux disparaît, un identifiant forgé n'emporte donc rien.
		// ⚠️ Vide n'est pas « rien à copier » mais « toute la saison » : c'est le geste d'origine,
		// celui qui part d'un choix de saison et non d'un choix de séries.
		$data->seasonSelection = INPUT('season', 'int', 0);

		// Deux formes pour la même liste, et c'est la barre de lot qui impose la première :
		// `batch.js` pose ses cases cochées en `ids[]`. Le panneau la réémet en une chaîne, seule
		// forme qui traverse une redirection puis un attribut `post-*` d'un lien ajax.
		$selection = INPUT('series', 'string', '');

		if($selection === '') {
			$selection = implode(',', array_map('intval', REQUEST('ids', 'array', [])));
		}

		$data->cSeriesSelection = \series\CopyAiLib::getSeriesSelection(
			$data->eStructureSource,
			$data->seasonSelection,
			$selection
		);

	})
	->get('create', function($data) {

		// ⚠️ Une ferme sans plan de culture n'est pas un refus de droits : elle n'a rien à copier.
		// Cette route levait `NotAllowedAction`, donc « Vous n'êtes pas autorisé à afficher cette
		// page » sur une ferme dont le rattachement autorise la copie et dont le lecteur a le
		// droit — les deux vérifiés en base. Le panneau s'ouvre et dit ce qui manque.

		// Le téléchargement ne va nulle part : il n'a pas de ferme d'arrivée à chercher, et la
		// lecture pèse les droits sur toutes les fermes de la personne.
		$data->cStructureDestination = ($data->mode === \series\CopyAiLib::CSV)
			? new \Collection()
			: \series\CopyAiLib::getDestinations(
				$data->eUserOnline,
				$data->eStructureSource,
				$data->eStructureCommunity,
				$data->mode
			);

		// Les saisons ouvertes par la ferme d'arrivée proposée en premier : le choix se prépare
		// ici, l'écran ne va pas les chercher.
		$data->seasonsDestination = $data->cStructureDestination->empty()
			? []
			: \series\CopyAiLib::getSeasonsDestination($data->cStructureDestination->first());

		throw new ViewAction($data);

	})
	/**
	 * La sortie en fichier, sur un lien et jamais sur un formulaire.
	 *
	 * ⚠️ Une route qui ne rend qu'un fichier, comme les huit exports de `series/csv` et de
	 * `selling/csv`. Elle a vécu un temps dans `doCreate`, en POST : la couche ajax attendait
	 * alors du JSON et donnait le CSV à `JSON.parse`, qui l'affichait en clair dans le bandeau
	 * d'erreur. Une réponse qui n'est pas du JSON ne se demande pas depuis un formulaire ajax.
	 *
	 * Le contrôle d'accès est celui du bloc commun, `canExportProduction()` : c'est bien la même
	 * porte que la copie, sortir des données étant sortir des données.
	 */
	->get('csv', function($data) {

		$season = GET('season', 'int');

		if(in_array($season, $data->seasons, TRUE) === FALSE) {
			throw new NotExpectedAction('Unknown season');
		}

		$maxVarieties = NULL;
		$export = \series\CsvLib::getExportCultivations($data->eStructureSource, $season, $maxVarieties);

		array_unshift($export, new \series\CsvUi()->getExportCultivationsHeader($maxVarieties));

		throw new CsvAction($export, 'plan-'.$data->eStructureSource['id'].'-'.$season.'.csv');

	})
	/**
	 * Le choix fait, on ouvre l'écran de conflits.
	 *
	 * ⚠️ Rien n'est mis en cache entre les deux : le plan se relit depuis la ferme source à chaque
	 * fois. Le téléversement d'un fichier a besoin d'un cache parce que le fichier n'existe plus
	 * après la requête ; une ferme, elle, est toujours là.
	 */
	->post('doCreate', function($data) {

		$season = POST('season', 'int');

		if(in_array($season, $data->seasons, TRUE) === FALSE) {
			throw new NotExpectedAction('Unknown season');
		}

		throw new RedirectAction(\series\CopyAiUi::urlAnalyze(
			$data->eStructureSource,
			$data->eStructureCommunity,
			$data->mode,
			$season,
			POST('destination', 'int'),
			POST('target', 'int'),
			implode(',', $data->cSeriesSelection->getIds())
		));

	})
	->get('analyze', function($data) {

		$data->eStructureDestination = \series\CopyAiLib::getDestinations(
			$data->eUserOnline,
			$data->eStructureSource,
			$data->eStructureCommunity,
			$data->mode
		)[GET('destination', 'int')] ?? new \structure\Structure();

		if($data->eStructureDestination->empty()) {
			throw new NotAllowedAction();
		}

		// ⚠️ Le gabarit de ferme se construit sur `$data->eStructure`, et il le lit avant que la
		// vue tourne : le poser dans la vue rend une page qui a déjà commencé à s'écrire. C'est la
		// ferme **d'arrivée** — c'est chez elle qu'on écrira, et ce sont ses réglages que l'écran
		// de conflits nomme à chaque ligne.
		$data->eStructure = $data->eStructureDestination;

		// ⚠️ La base de la ferme d'arrivée se connecte ici parce que le gabarit de ferme la lit :
		// `Structure::getSelection()` porte `cFinancialYear`, résolu à l'affichage du bandeau.
		// `StructurePage` le fait pour ses routes ; celle-ci n'en est pas une, elle vit dans deux
		// fermes à la fois.
		\structure\StructureLib::connectDatabase($data->eStructure);

		$data->season = GET('season', 'int');
		$data->target = GET('target', 'int');

		if(in_array($data->target, \series\CopyAiLib::getSeasonsDestination($data->eStructureDestination), TRUE) === FALSE) {
			throw new NotExpectedAction('Unknown destination season');
		}

		$import = \series\CsvLib::getImportFromStructure($data->eStructureSource, $data->season, $data->cSeriesSelection);

		if($import === NULL or $import === []) {
			throw new NotAllowedAction();
		}

		$data->data = \series\CsvLib::getCultivationsFromImport($data->eStructureDestination, $import, $data->target, fromStructure: TRUE);

		// ⚠️ La seconde analyse se fait ici et non dans la vue : elle relit les plantes, les
		// variétés, les outils et les saisons de la ferme d'arrivée, et une vue ne va pas chercher
		// sa donnée. Elle dit si ce qui reste après retrait des lignes fautives tient debout.
		$data->partial = \series\CsvLib::getPartialImport($data->eStructureDestination, $data->data['import'], $data->target, fromStructure: TRUE);

		$data->cAction = \work\ActionLib::getByStructure(
			$data->eStructureDestination,
			fqn: [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION, ACTION_RECOLTE],
			index: 'fqn'
		);

		throw new ViewAction($data);

	})
	->post('doCopy', function($data) {

		$fw = new FailWatch();

		$cStructureDestination = \series\CopyAiLib::getDestinations(
			$data->eUserOnline,
			$data->eStructureSource,
			$data->eStructureCommunity,
			$data->mode
		);

		$eStructureDestination = $cStructureDestination[POST('destination', 'int')] ?? new \structure\Structure();

		if($eStructureDestination->empty()) {
			throw new NotAllowedAction();
		}

		$season = POST('season', 'int');
		$target = POST('target', 'int');

		if(in_array($target, \series\CopyAiLib::getSeasonsDestination($eStructureDestination), TRUE) === FALSE) {
			throw new NotExpectedAction('Unknown destination season');
		}

		$import = \series\CsvLib::getImportFromStructure($data->eStructureSource, $season, $data->cSeriesSelection);
		$analysis = \series\CsvLib::getCultivationsFromImport($eStructureDestination, $import, $target, fromStructure: TRUE);

		// ⚠️ L'analyse se rejoue ici et ne se reprend pas de l'écran : ce que le navigateur a
		// affiché n'engage rien, et la ferme source a pu changer entre les deux requêtes. C'est
		// aussi ce qui permet de refuser une copie complète devenue impossible.
		$partial = (bool)POST('partial', 'int', 0);

		if($partial) {

			$list = \series\CsvLib::getPartialImport($eStructureDestination, $analysis['import'], $target, fromStructure: TRUE)['list'];

		} else {

			if($analysis['errorsCount'] > 0) {
				throw new NotAllowedAction();
			}

			$list = $analysis['import'];

		}

		if($list === []) {
			throw new NotAllowedAction();
		}

		\series\CsvLib::importCultivations($eStructureDestination, $list, reset: FALSE);

		$fw->validate();

		// ⚠️ La saison d'arrivée voyage dans la redirection : sans elle, l'écran ouvre la saison par
		// défaut de la ferme et annonce « Vous n'avez pas encore créé de série » sur une autre
		// saison que celle où l'on vient d'écrire.
		throw new RedirectAction(\structure\StructureUi::urlCultivationSeries($eStructureDestination, season: $target).'&copied='.count($list));

	});
?>
