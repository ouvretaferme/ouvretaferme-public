<?php
new Page(function($data) {

		// La garde vit ici et non autour de la route : route.c.php est suivi par git et n'est
		// régénéré qu'en dev, là où le drapeau est vrai — le fichier déployé porterait donc la
		// route de toute façon. Posée dans le start, elle s'exécute dans les deux environnements.
		if(FEATURE_INRAE === FALSE) {
			throw new NotExistsAction();
		}

		// Pas de `checkLogged()` : une fiche publique se lit sans compte, et c'est `canReadProfile()`
		// qui décide. Exiger la connexion ici la refuserait avant même de regarder la colonne.
		// Sélection explicite plutôt que Structure::getSelection() : celle-ci fait descendre
		// siret, legalEmail, subscription, consentData et les adresses légales sur une page lue
		// de l'extérieur, et porte une entrée paresseuse dont la closure ouvre la base de la
		// ferme. Garantir la borne par une omission ne la garantit pas.
		$data->eStructure = \structure\StructureLib::getById(GET('id'), [
				'id', 'name', 'logo', 'banner', 'description', 'legalCity',
				'startedAt', 'organic', 'qualities',
				'type', 'status', 'producerType', 'parent', 'profileVisibility'
			])
			->validate('canReadProfile', 'acceptReadProfile');

		$data->profileShare = $data->eStructure->getProfileShare();

	})
	->get('/ferme/{id}', function($data) {

		$data->eSystem = new \structure\System();
		$data->cProduction = new \Collection();
		$data->cStructureNeighbour = new \Collection();

		$data->cStructureCommunity = $data->eStructure->getProfileCommunities();

		// Qui répond de cette ferme : la première chose qu'on cherche en ouvrant sa fiche, et la
		// seule que ni le nom ni la commune ne disent.
		$data->cUser = \structure\AffiliationAiLib::getUsersByStructure($data->eStructure, $data->cStructureCommunity);

		if($data->profileShare !== \structure\Structure::PROFILE_HIDDEN) {

			$data->eSystem = \structure\SystemLib::getByStructure($data->eStructure);
			$data->cProduction = \structure\ProductionLib::getByStructure($data->eStructure);

			$data->cStructureNeighbour = \structure\ProfileAiLib::getNeighbours($data->eStructure, $data->eSystem, $data->eUserOnline);

		}

		throw new ViewAction($data);

	});
