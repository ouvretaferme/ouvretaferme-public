<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \structure\StructureUi()->create($data->e);

});

new AdaptativeView('start', function($data, BaseTemplate $t) {

	$t->title = s("Bienvenue sur {value}", $data->e['name']);

	$t->header = '<h1 class="text-center">'.encode($data->e['name']).'</h1>';

	echo new \structure\StructureUi()->getStarted($data->e);

});

new AdaptativeView('updateProfile', function($data, DynamicTemplate $t) {

	$t->title = s("Le profil public de {value}", $data->e['name']);

	if($t instanceof StructureTemplate) {
		$t->nav = 'structure';
		$t->subNav = 'profile';
	}

	// Une AMAP n'a pas de fiche publique (`acceptReadProfile()`), donc pas de page Profil dans ses
	// Paramètres : l'adresse ouverte à la main s'y range sans en marquer aucune.
	if($t instanceof AmapTemplate) {
		$t->nav = 'settings';
	}

	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("Profil"),
		create: '<a href="'.\structure\StructureUi::urlStructureProfileEdit($data->e).'" class="btn btn-secondary">'.\Asset::icon('pencil-fill').' '.s("Modifier le profil").'</a>'
	);

	echo new \structure\StructureUi()->updateProfile($data->e, $data->eSystem, $data->cProduction);

});

new AdaptativeView('updateProfileEdit', function($data, DynamicTemplate $t) {

	$t->title = s("Modifier le profil public de {value}", $data->e['name']);

	if($t instanceof StructureTemplate) {
		$t->nav = 'structure';
		$t->subNav = 'profile';
	}

	if($t instanceof AmapTemplate) {
		$t->nav = 'settings';
	}

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Modifier le profil"));

	echo new \structure\StructureUi()->updateProfileForm($data->e);

});

new AdaptativeView('update', function($data, DynamicTemplate $t) {

	if($t instanceof StructureTemplate) {
		$t->nav = 'structure';
		$t->subNav = 'general';
	}

	if($t instanceof CommunityTemplate) {
		$t->tab = 'settings';
	}

	if($t instanceof AmapTemplate) {
		$t->nav = 'settings';
		$t->subNav = 'general';
	}

	if($data->e->isAmap()) {
		$t->title = \structure\StructureUi::getSettingsGeneralName($data->e);
	} else {
		$t->title = s("Le paramétrage de {value}", $data->e['name']);
	}

	// Le profil d'une AMAP s'ouvre depuis sa page Paramètres, et y ramène.
	$back = $data->e->isAmap() ? \structure\StructureUi::urlAmapSettings($data->e) : '';
	$backLabel = $data->e->isAmap() ? s("Paramètres") : '';

	if($data->eStructure->isVerifiedCountry() === FALSE) {

		$t->mainTitle = \main\DesignUi::getPageHeader(\structure\StructureUi::getSettingsGeneralName($data->e), back: $back, backLabel: $backLabel);

		echo new \structure\StructureUi()->getLegalForm($data->e, onlyCountry: TRUE);

	} else if($t instanceof CommunityTemplate) {

		// La bande de l'écran porte le titre, les liens qui qualifient tout le paramétrage et les
		// onglets, dont les panneaux restent dans le corps.
		$update = new \structure\StructureUi()->getUpdateTabs($data->e);

		$t->mainTitle = \main\DesignUi::getPageHeader(\structure\StructureUi::getSettingsGeneralName($data->e), tools: new \structure\StructureUi()->getUpdateTools($data->e));
		$t->mainTabs = $update['items'];
		$t->mainTabsAttributes = $update['attributes'];
		$t->mainTabsFold = TRUE;

		echo $update['panels'];

	} else {

		$t->mainTitle = \main\DesignUi::getPageHeader(\structure\StructureUi::getSettingsGeneralName($data->e), back: $back, backLabel: $backLabel);

		echo new \structure\StructureUi()->update($data->e);

	}

});

new AdaptativeView('updateLegal', function($data, PanelTemplate $t) {

	return new \structure\StructureUi()->updateLegal($data->e);

});

new AdaptativeView('breakClose', function($data, PanelTemplate $t) {

	return new \structure\StructureAmapUi()->breakClose($data->e);

});

new AdaptativeView('export', function($data, StructureTemplate $t) {

	$t->title = s("Les exports de {value}", $data->e['name']);

	$t->nav = 'data';
	$t->subNav = 'export';

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Exporter les données"));
	
	echo new \structure\StructureUi()->export($data->e, $data->year, $data->yearFirst);

});

new AdaptativeView('import', function($data, StructureTemplate $t) {

	$t->title = s("Les imports de {value}", $data->e['name']);

	$t->nav = 'data';
	$t->subNav = 'import';

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Importer des données"));

	echo new \structure\StructureUi()->import($data->e);

});

new JsonView('weatherDay', function($data, AjaxTemplate $t) {

	// `NULL` ne dit qu'une chose : le cache a expiré entre l'affichage et le clic. Le menu reste alors tel
	// quel plutôt que de perdre sa météo sous le geste, comme au choix du modèle (`member.v.php`).
	if($data->weather !== NULL) {

		$selector = '#'.\dashboard\DashboardUi::getBlockId('structure-weather');

		$t->qs($selector.' .structure-weather-forecast')
			->outerHtml(new \structure\DashboardUi()->getForecast($data->e, $data->weather, $data->weather['day']));

		// Au pied du menu, sur un écran peu haut, le jour s'ouvre sous le bord : sans ce défilement, le clic
		// semble sans effet. `nearest` ne bouge rien quand le détail est déjà en vue.
		if($data->weather['day'] !== NULL) {
			$t->qs($selector.' .structure-weather-hours')->scrollTo('nearest', 'smooth');
		}

	}

});
