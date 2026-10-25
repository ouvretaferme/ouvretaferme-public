<?php
// Les pages d'un groupe posent leur type dans l'action, donc après la validation de create() : elles
// interrogent acceptCreateType() elles-mêmes, une fois le type posé.
new \structure\StructurePage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->create(function($data) {

		$data->e['type'] = \structure\Structure::PRODUCER;
		$data->e['legalCountry'] = $data->eUserOnline['invoiceCountry'];
		$data->e['isFirst'] = $data->ccStructureUser->empty();
		$data->e['producerType'] = GET('producerType', [\structure\Structure::LEARNER], NULL);
		$data->e['inviteKey'] = GET('inviteKey', '?string');

		// Une invitation à livrer une AMAP mène ici pour créer la structure qui livrera : y créer une
		// AMAP laisserait l'invitation sans rien à accepter.
		$data->e['canCreateAmap'] = (
			$data->e['inviteKey'] === NULL and
			$data->eUserOnline->acceptCreateAmap()
		);

		throw new ViewAction($data, ':create');

	}, page: '/demarrer/ferme')
	->create(function($data) {

		$data->e['type'] = \structure\Structure::SHOP;
		$data->e['legalCountry'] = $data->eUserOnline['invoiceCountry'];

		throw new ViewAction($data, ':create');

	}, page: '/demarrer/groupement')
	->create(function($data) {

		$data->e['type'] = \structure\Structure::COMMUNITY;
		$data->e['communityType'] = \structure\Structure::PRODUCER;
		$data->e['owner'] = $data->eUserOnline;

		$data->e->validate('acceptCreateType');

		throw new ViewAction($data, ':create');

	}, page: '/demarrer/communaute')
	->create(function($data) {

		$data->e['type'] = \structure\Structure::COMMUNITY;
		$data->e['communityType'] = \structure\Structure::EDUCATION;
		$data->e['owner'] = $data->eUserOnline;

		$data->e->validate('acceptCreateType');

		throw new ViewAction($data, ':create');

	}, page: '/demarrer/education')
	->create(function($data) {

		$data->e['type'] = \structure\Structure::COMMUNITY;
		$data->e['communityType'] = \structure\Structure::NETWORK;
		$data->e['owner'] = $data->eUserOnline;

		$data->e->validate('acceptCreateType');

		throw new ViewAction($data, ':create');

	}, page: '/demarrer/reseau');

new \structure\StructurePage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(fn($data) => new \structure\Structure([
		'type' => \structure\Structure::AMAP,
		'owner' => $data->eUserOnline,
	]))
	->create(function($data) {

		$data->e['legalCountry'] = $data->eUserOnline['invoiceCountry'];

		throw new ViewAction($data, ':create');

	}, page: '/demarrer/amap', addValidate: ['acceptCreateType']);

new \structure\StructurePage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$eStructure = new \structure\Structure([
			'type' => \structure\Structure::POST('type', 'type', fn() => throw new NotExpectedAction()),
			'owner' => $data->eUserOnline,
		]);

		// Lu avant build() parce que acceptCreateType() le juge, et doCreate() valide avant de construire.
		// Pour un groupe seulement : build() ne construit cette propriété que pour lui, et une clé posée
		// sur une autre structure partirait en base telle quelle.
		if($eStructure['type'] === \structure\Structure::COMMUNITY) {
			$eStructure['communityType'] = \structure\Structure::POST('communityType', 'communityType');
		}

		return $eStructure;

	})
	->doCreate(function($data) {

		throw new RedirectAction(match($data->e['type']) {
			\structure\Structure::PRODUCER => \structure\SystemUi::urlUpdateProductions($data->e->system(), POST('inviteKey', '?string')),
			\structure\Structure::COMMUNITY => \structure\StructureUi::url($data->e),
			\structure\Structure::AMAP => \structure\StructureUi::urlAmapDashboard($data->e),
			\structure\Structure::SHOP => \structure\StructureUi::urlSellingShop($data->e),
		});

	}, addValidate: ['acceptCreateType']);

new \structure\StructurePage()
	->read('start', function($data) {

		$data->eStructure = $data->e;

		\structure\StructureLib::loadStart($data->e, GET('inviteKey', '?string'));

		throw new ViewAction($data);

	}, validate: ['canManage', 'acceptStartPage']);

new \structure\StructurePage()
	->applyElement(function($data, \structure\Structure $e) {

		$e->validate('canManage');

	})
	->update(function($data) {

		$data->tos = NULL;
		$data->eStructure = $data->e;

		// Le compte des périmètres accordés, préparé ici : une UI ne va pas chercher sa donnée, et
		// celui-ci demande une lecture des rattachements que l'écran ne fait nulle part ailleurs.
		if($data->e->isCommunity()) {
			$data->e['shareCounts'] = \structure\AffiliationAiLib::countSharesByCommunity($data->e);
		}

		$data->e['eCourseCustomize'] = \learning\CommunityLib::getCustomizeCourse($data->e);

		$data->e['customizeSettings'] = \learning\CommunityLib::hasCustomize($data->e, $data->e['eCourseCustomize']) ?
			\learning\CommunityLib::getSettings($data->e->community()) :
			NULL;

		throw new DynamicAction($data);

	})
	->write('doUpdate', function($data) {

		$fw = new \FailWatch();

		$properties = \structure\StructureLib::getPropertiesUpdate()($data->e);
		$data->e->build($properties, $_POST, new \PropertyList('update'));

		$propertiesCommunity = \structure\CommunityLib::getPropertiesAdministrative($data->e);
		$eCommunity = new \structure\Community();

		if($propertiesCommunity !== []) {
			$eCommunity = $data->e->community();
			$eCommunity->build($propertiesCommunity, $_POST, new \PropertyList('update'));
		}

		$propertiesConfiguration = \structure\ConfigurationLib::getPropertiesAdministrative($data->e);
		$eConfiguration = $data->e->conf();

		if($propertiesConfiguration !== []) {
			$eConfiguration->build($propertiesConfiguration, $_POST, new \PropertyList('update'));
		}

		$fw->validate();

		\structure\StructureLib::updateAdministrative($data->e, $properties, $eCommunity, $propertiesCommunity, $eConfiguration, $propertiesConfiguration);

		$fw->validate();

		throw new ReloadAction('structure', 'Structure::updated');

	}, validate: ['canUpdate', 'acceptUpdate'])
	->update(function($data) {

		$data->eStructure = $data->e;

		throw new ViewAction($data);

	}, page: 'updateLegal')
	->update(function($data) {

		$data->eStructure = $data->e;

		// L'aperçu se rend par le code de la fiche, donc il lui faut la même donnée : préparée ici
		// et non dans l'UI, qui ne va pas chercher la sienne. Sans neighbours ni cUser — la
		// première est bornée au lecteur et ne dit rien de ce que la fiche montre d'elle-même, la
		// seconde nomme les gens du groupe et non la ferme.
		$data->eSystem = \structure\SystemLib::getByStructure($data->e);
		$data->cProduction = \structure\ProductionLib::getByStructure($data->e);

		throw new DynamicAction($data);

	}, page: 'updateProfile')
	->update(function($data) {

		$data->eStructure = $data->e;

		throw new DynamicAction($data);

	}, page: 'updateProfileEdit')
	->doUpdateProperties(
		'doUpdateProfile',
		fn(\structure\Structure $e) => \structure\StructureLib::getPropertiesProfile(),
		fn($data) => throw new ReloadAction('structure', 'Structure::updated'),
		addValidate: ['acceptPresentation']
	)
	->write('doUpdateCountry', function($data) {

		$fw = new \FailWatch();

		$properties = ['legalCountry', 'verifiedCountry'];

		$data->e->build($properties, $_POST);

		$fw->validate();

		\structure\StructureLib::update($data->e, $properties);

		throw new ReloadAction('structure', 'Structure::updatedInfo');

	}, addValidate: ['isNotVerifiedCountry'])
	->write('doEnableLegal', function($data) {

		\structure\StructureLib::updateLegal($data->e);

		throw new RedirectAction(\structure\StructureUi::urlStructureGeneral($data->e).'&success=structure\\Structure::updatedLegal');

	}, addValidate: ['acceptLegal', 'isNotLegal'])
	->doUpdateProperties('doUpdateLegal', fn(\structure\Structure $e) => \structure\StructureLib::getPropertiesLegal($e), fn() => throw new ReloadAction('structure', 'Structure::updatedInfo'), for: 'legal')
	->doUpdateProperties('doUpdateConsentData', ['consentData'], fn($data) => throw new ReloadAction('structure', 'Structure::updatedConsentData'), for: 'legal')
	/**
	 * La fermeture d'une AMAP, en panneau plutôt qu'en `data-confirm` : celui-ci passe par le
	 * `confirm()` du navigateur, qui ne rend que du texte brut. Le panneau poste `doClose` : il s'ouvre
	 * à la même équipe (`canManage`, posé par `applyElement()`) et sur le même état (`acceptClose`),
	 * pour ne jamais proposer un geste que la route refuserait.
	 */
	->read('breakClose', function($data) {

		throw new ViewAction($data);

	}, addValidate: ['isAmap', 'acceptClose'])
	->write('doClose', function($data) {

		if(OTF_DEMO) {
			throw new \FailAction('structure\Structure::demo.delete');
		}

		$data->e['status'] = \structure\Structure::CLOSED;

		\structure\StructureLib::close($data->e);

		// L'équipe d'une AMAP fermée la garde : elle revient sur son tableau de bord, qui sert ce qui court.
		if($data->e->isAmap()) {
			throw new RedirectAction(\structure\StructureUi::urlAmapDashboard($data->e).'?success=structure\\Structure::closedAmap');
		}

		throw new RedirectAction('/?success=structure\\Structure::closed');

	}, addValidate: ['acceptClose'])
	->read('export', function($data) {

		if($data->e->isAmap()) {
			throw new RedirectAction(\structure\StructureUi::urlDataCustomers($data->e));
		}

		$data->eStructure = $data->e;
		$data->year = GET('year', default: \Clock::year());

		\structure\StructureLib::connectDatabase($data->eStructure);

		$data->yearFirst = min(
			$data->e->conf(\map\Configuration::class)['seasonFirst'],
			(int)date('Y', strtotime($data->e['createdAt'])) - 1
		);

		throw new \ViewAction($data);

	}, validate: ['canPersonalData'])
	->read('import', function($data) {

		if($data->e->isAmap()) {
			throw new RedirectAction(\structure\StructureUi::urlDataCustomers($data->e));
		}

		$data->eStructure = $data->e;

		throw new \ViewAction($data);

	}, validate: ['canManage']);

new \structure\StructurePage()
	->read('/ferme/{id}/partage-des-donnees', function($data) {

		throw new RedirectAction(\structure\StructureUi::urlSharing($data->e));

	}, validate: ['canManage']);

new \structure\StructurePage()
	/**
	 * Le détail d'un jour de la météo du menu, ou sa fermeture sans `date` (L96). La prévision du menu se
	 * relit avec lui : la route redessine les tuiles en même temps que le détail. Le droit est celui qui
	 * montre la météo, `canRead`.
	 */
	->read('weatherDay', function($data) {

		$data->weather = \structure\WeatherAiLib::getByStructure($data->e, $data->e->getMember(), GET('date'));

		throw new ViewAction($data);

	});
?>
