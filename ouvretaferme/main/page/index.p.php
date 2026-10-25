<?php
new Page()
	->get('index', function($data) {

		if($data->eUserOnline->empty()) {
			\main\PageLib::displayAnonymousHome($data);
		}

		$data->hasSurveyAnnouncement = (
			FEATURE_SURVEY and
			$data->ccStructureUser->offsetExists(\structure\Structure::PRODUCER) and
			\structure\SurveyLib::getStructuresToAnswer($data->eUserOnline)->notEmpty()
		);

		$data->cInvite = \structure\InviteLib::getValidByUser($data->eUserOnline);

		// L'accueil d'un producteur qui n'a pas encore de ferme porte le formulaire de création
		// lui-même : son élément se prépare comme le prépare la page /demarrer/ferme pour le panneau.
		$data->eStructureCreate = new \structure\Structure([
			'type' => \structure\Structure::PRODUCER,
			'legalCountry' => $data->eUserOnline['invoiceCountry'],
			'isFirst' => $data->ccStructureUser->empty(),
			'canCreateAmap' => $data->eUserOnline->acceptCreateAmap(),
			'producerType' => NULL,
			'inviteKey' => NULL,
		]);

		\main\CustomerSpaceAiLib::load($data);

		$data->cInvite = \main\CustomerSpaceAiLib::filterInvites($data->cInvite, $data->customerSpace);

		if($data->eUserOnline->isProducer()) {
			$data->eStructureTraining = \main\TrainingAiLib::getCommunity();
			$data->cCourseTraining = \main\TrainingAiLib::getHomeCourses($data->eStructureTraining);
		}

		$data->cNews = \website\NewsLib::getLastForBlog();
		$data->hasNewsletterForm = \main\PageLib::hasNewsletterForm($data->eUserOnline);

		// La carte d'une AMAP n'offre rien à qui n'en est pas gestionnaire : sa porte est l'écran AMAP
		// de la ferme qui la livre, dont l'adresse ne se déduit pas de l'AMAP. Elle se prépare ici, en
		// une requête pour toutes les cartes, et se lit dans `StructureUi::getPanel()`.
		$cStructureAmap = $data->ccStructureUser->get(\structure\Structure::AMAP, new Collection());
		$cStructureProducer = $data->ccStructureUser->get(\structure\Structure::PRODUCER, new Collection());

		foreach(\structure\StructureLib::getFarmByAmap($cStructureAmap, $cStructureProducer) as $amap => $producer) {
			$cStructureAmap[$amap]['urlAmap'] = \structure\StructureUi::getObserverEntries('getCategoryUrls', $cStructureProducer[$producer])['amap-producer']['list'];
		}

		$isProfessional = (
			$data->eUserOnline->isProducer() or
			$data->eUserOnline->isConsultant() or
			$data->eUserOnline->isTeacher()
		);

		if($isProfessional) {
			$data->ccStructureHome = $data->ccStructureUser;
		} else {

			$data->ccStructureHome = new Collection();
			$data->ccStructureHome->setDepth(2);

			if($cStructureAmap->notEmpty()) {
				$data->ccStructureHome[\structure\Structure::AMAP] = $cStructureAmap;
			}

		}

		$data->hasHomeStructures = ($isProfessional or $data->ccStructureHome->notEmpty());

		if(FEATURE_GAME) {
			$data->ePlayer = \game\PlayerLib::getOnline();
		}

		throw new ViewAction($data, path: ':logged');

	})
	->get('/espace-client', function($data) {

		\user\ConnectionLib::checkLogged();

		\main\CustomerSpaceAiLib::load($data);

		throw new ViewAction($data);

	})
	->get('/presentation/invitation', fn($data) => throw new ViewAction($data));
?>
