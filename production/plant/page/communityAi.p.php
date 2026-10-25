<?php
new \structure\StructurePage()
	->read('/ferme/{id}/bibliotheque', function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canManage');

		// Deux verrous, dans cet ordre : l'adhésion à l'association d'abord, puis la
		// même réciprocité que sur les variétés, la bibliothèque étant faite des plans
		// de culture des autres, on ne la sert qu'à qui accepte d'y contribuer. Sans
		// l'un ou l'autre, aucune des requêtes ci-dessous n'a lieu d'être.
		$data->ePlant = new \plant\Plant();
		$data->cPlant = new \Collection();
		$data->filters = [];
		$data->cropPlan = [];

		if(
			$data->eStructure['membership'] !== TRUE or
			$data->eStructure['consentData'] !== TRUE
		) {
			throw new \ViewAction($data);
		}

		// La fonctionnalité s'ouvrira sur toutes les espèces. En attendant elle est
		// servie sur la seule carotte, sauf au développement et aux comptes de
		// l'avant-première (PlantSetting::COMMUNITY_PLANT_PREVIEW_USERS).
		$data->cPlant = \plant\CommunityAiLib::canSelectPlant() ?
			\plant\CommunityAiLib::getPlantReference() :
			new \Collection();

		$fqn = \plant\PlantSetting::COMMUNITY_PLANT_PREVIEW;

		if($data->cPlant->notEmpty()) {

			// L'espèce demandée n'est retenue que si elle est dans la liste servie : le
			// paramètre vient de l'URL et sert de pivot à toutes les requêtes.
			$requested = GET('plant', 'string', NULL);

			if(in_array($requested, $data->cPlant->getColumn('fqn'), TRUE)) {
				$fqn = $requested;
			}

		}

		$data->ePlant = \plant\CommunityAiLib::getPlantByFqn($fqn);

		if($data->ePlant->empty()) {
			throw new NotExpectedAction('Espèce de référence introuvable');
		}

		$mode = GET('mode', 'string', NULL);
		$action = GET('action', 'string', NULL);

		$seedling = GET('seedling', 'string', NULL);
		$view = GET('view', 'string', NULL);

		// La largeur de planche demandée, à défaut celle que la structure a réglée pour
		// ses séries : la question « comment les autres montent cette espèce » n'a
		// d'intérêt que sur la planche qu'on monte soi-même. Le Lib la ramène à la
		// largeur gérée la plus proche, et choisit la plus pratiquée quand il n'y en a
		// aucune.
		$bedWidth = GET('bedWidth', '?int') ?? $data->eStructure->conf(\map\Configuration::class)['defaultBedWidth'];

		// La recherche par variété est bornée sur la longueur d'un nom de variété : le
		// paramètre arrive de l'URL et sert à comparer des milliers de noms, autant ne
		// pas laisser passer une chaîne arbitrairement longue.
		$variety = trim(GET('variety', 'string', ''));
		$variety = mb_substr($variety, 0, \plant\Variety::model()->getPropertyRange('name')[1]);

		// Plantation et semis direct ne se regardent jamais ensemble : ce sont deux
		// itinéraires, avec leurs créneaux et leurs variétés. La page en montre donc
		// toujours un seul — celui demandé, ou à défaut celui que l'espèce pratique
		// majoritairement, une valeur en dur ouvrant la carotte sur ses 68 cultures
		// plantées plutôt que sur ses 4 943 semées.
		$action = in_array($action, [\series\Cultivation::PLANTING, \series\Cultivation::SOWING]) ?
			$action :
			\aggregate\CultivationAiLib::getActionDominantByPlant($data->ePlant);

		$data->filters = [
			// Le palier d'altitude restreint l'échantillon aux structures qui cultivent
			// à la même hauteur : c'est un filtre sur la ferme, pas sur la culture.
			'altitude' => array_key_exists(GET('altitude', 'string', ''), \aggregate\CultivationAiSetting::ALTITUDES) ?
				GET('altitude', 'string') :
				NULL,
			'mode' => in_array($mode, [\series\Series::GREENHOUSE, \series\Series::OPEN_FIELD]) ? $mode : NULL,
			'action' => $action,
			// L'origine du plant ne se pose qu'en plantation : un semis direct n'a ni
			// pépinière ni plant acheté. Le test porte sur la conduite résolue, pas sur
			// le paramètre reçu — sans quoi une espèce ouverte par défaut en semis direct
			// se verrait quand même filtrer sur l'origine du plant.
			//
			// Choix exclusif, sans agrégat : un plant acheté n'a pas de pépinière, un
			// plant produit sur place en a une. Les mêler donnerait une étape de
			// pépinière calculée sur la moitié de l'échantillon sans que rien ne le dise.
			// Le plant autoproduit sert de défaut, c'est la conduite majoritaire.
			'seedling' => ($action !== \series\Cultivation::PLANTING) ?
				NULL :
				(in_array($seedling, [\series\Cultivation::YOUNG_PLANT, \series\Cultivation::YOUNG_PLANT_BOUGHT]) ?
					$seedling :
					\series\Cultivation::YOUNG_PLANT),
			'variety' => ($variety !== '') ? $variety : NULL,
			// Une vue ne change pas l'échantillon, seulement ce qu'on en montre : elle
			// voyage avec les filtres parce qu'elle doit survivre à un changement de
			// mode ou d'espèce, pas parce qu'elle filtre quoi que ce soit.
			'view' => in_array($view, [\plant\PlantSetting::COMMUNITY_VIEW_SIMPLE, \plant\PlantSetting::COMMUNITY_VIEW_EXPERT]) ?
				$view :
				\plant\PlantSetting::COMMUNITY_VIEW_DEFAULT,
			// L'espèce voyage avec les filtres pour que cliquer sur un rayon ou un mode
			// ne renvoie pas à la courgette. Nulle sans sélecteur, l'URL restant alors
			// celle d'une page à espèce unique.
			'plant' => $data->cPlant->notEmpty() ? $fqn : NULL,
		];

		$data->cropPlan = \aggregate\CultivationCropPlanAiLib::getCropPlan($data->ePlant, $data->filters, $bedWidth);

		// La largeur voyage avec les filtres pour la même raison que la vue : changer de
		// mode ou d'espèce ne doit pas refermer le groupe de conduites ouvert. Elle ne
		// filtre rien. Les liens portent la largeur résolue, pas celle demandée : sans
		// elle, une structure sans réglage verrait la plus pratiquée recalculée sur un
		// autre échantillon à chaque changement de mode, et changerait de largeur.
		$data->filters['bedWidth'] = $data->cropPlan['conducts']['bedWidth'];

		throw new \ViewAction($data);

	})
	/*
	 * Les suggestions de la recherche, cherchées au fil de la frappe.
	 *
	 * En ajax et non embarquées : les variétés ne se bornent pas à l'espèce ouverte —
	 * il existe une « Black Beauty » d'aubergine et une de courgette — et le référentiel
	 * en compte des dizaines de milliers. Aucune liste de cette taille ne voyage avec
	 * une page.
	 *
	 * Les filtres reçus ne servent qu'à **recomposer des liens**, jamais à calculer :
	 * la page d'arrivée les revalide un par un. C'est ce qui permet de les reprendre
	 * tels quels sans les contrôler ici.
	 */
	->post('query', function($data) {

		$data->eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canManage');

		if($data->eStructure['membership'] !== TRUE) {
			throw new NotExpectedAction('Sans adhésion, pas de bibliothèque');
		}

		if($data->eStructure['consentData'] !== TRUE) {
			throw new NotExpectedAction('Sans consentement, pas de bibliothèque');
		}

		$data->cPlant = \plant\CommunityAiLib::canSelectPlant() ?
			\plant\CommunityAiLib::getPlantReference() :
			new \Collection();

		$fqn = POST('plant', '?string') ?? \plant\PlantSetting::COMMUNITY_PLANT_PREVIEW;

		$data->ePlant = \plant\CommunityAiLib::getPlantByFqn($fqn);

		if($data->ePlant->empty()) {
			throw new NotExpectedAction('Espèce de référence introuvable');
		}

		$data->filters = [];

		foreach(['altitude', 'mode', 'action', 'seedling', 'variety', 'view'] as $name) {
			$data->filters[$name] = POST($name, '?string') ?: NULL;
		}

		$data->filters['bedWidth'] = POST('bedWidth', '?int');

		$data->filters['plant'] = $data->cPlant->notEmpty() ? $data->ePlant['fqn'] : NULL;

		$data->query = trim(POST('query', '?string') ?? '');

		// La recherche par nom ne part qu'à partir de deux caractères : un LIKE '%a%'
		// rendrait tout, et le déroulant n'a rien à apprendre d'une liste entière.
		//
		// Bornée à l'espèce ouverte : chercher un nom de variété ne fait donc pas changer
		// d'espèce, il faut passer par la famille Espèces au-dessus.
		$data->cCultivationVariety = (mb_strlen($data->query) < 2) ?
			new \Collection() :
			// Le déroulant n'affiche que huit entrées par famille et annonce le reste :
			// en rendre cinquante suffit à ce que ce reste soit juste.
			\aggregate\CultivationVarietyAiLib::query($data->ePlant, $data->query, 50);

		throw new \ViewAction($data);

	});
?>
