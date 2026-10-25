<?php
new AdaptativeView('/ferme/{id}/notifications', function($data, DynamicTemplate $t) {

	$t->title = s("Notifications");
	$t->canonical = \notification\NotificationUi::url($data->eStructure);

	// La section d'origine, sans $t->nav : tout nav allumerait un onglet et un fil d'Ariane qui ne
	// sont pas cette page. StructureTemplate::buildSections() ne déduit la section que d'un nav connu,
	// elle laisse donc celle-ci en place. Le bandeau d'une AMAP n'a pas de section.
	if($t instanceof StructureTemplate) {
		$t->section = $data->section;
		$t->template .= ' structure-'.$data->section.' ';
	}

	$t->mainTitle = new \notification\NotificationUi()->getTitle($data->eStructure, $data->unread);

	echo new \notification\NotificationUi()->getCentre($data->eStructure, $data->days, $data->pilotage);

	// La barre n'est pas redessinée en navigation ajax : la pastille de la cloche s'éteint par
	// instruction. En rendu complet le gabarit lit le compteur que la page vient de recaler, et
	// l'instruction n'est pas poussée.
	if($data->seen) {
		$t->qs('#'.\notification\NotificationUi::getBellId($data->eStructure).' .util-counter')->innerHtml('');
	}

});

/**
 * Le centre d'un groupe, dans le gabarit de groupe : une `AdaptativeView` ne prend qu'un gabarit, et
 * celui d'une ferme ne sait pas s'afficher sans section. Aucun onglet ne s'allume, la page n'en
 * étant aucun.
 */
new AdaptativeView('/groupe/{id}/notifications', function($data, CommunityTemplate $t) {

	$t->title = s("Notifications");
	$t->canonical = \notification\NotificationUi::url($data->eStructure);

	$t->mainTitle = new \notification\NotificationUi()->getTitle($data->eStructure, $data->unread);

	echo new \notification\NotificationUi()->getCentre($data->eStructure, $data->days, $data->pilotage);

	if($data->seen) {
		$t->qs('#'.\notification\NotificationUi::getBellId($data->eStructure).' .util-counter')->innerHtml('');
	}

});

new AdaptativeView('/ferme/{id}/notifications/reglages', function($data, DynamicTemplate $t) {

	$t->title = s("Mes notifications");
	$t->canonical = \notification\NotificationUi::urlSettings($data->eStructure);

	// Même cadre que le centre : la section d'où l'on vient, sans nav, donc sans onglet ni fil
	// d'Ariane (la page n'appartient à aucune section).
	if($t instanceof StructureTemplate) {
		$t->section = $data->section;
		$t->template .= ' structure-'.$data->section.' ';
	}

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Mes notifications"));

	echo new \notification\NotificationUi()->getSettings($data->eStructure, $data->settings, $data->pilotage);

});

new AdaptativeView('/groupe/{id}/notifications/reglages', function($data, CommunityTemplate $t) {

	$t->title = s("Mes notifications");
	$t->canonical = \notification\NotificationUi::urlSettings($data->eStructure);

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Mes notifications"));

	echo new \notification\NotificationUi()->getSettings($data->eStructure, $data->settings, $data->pilotage);

});

new JsonView('doUpdateSetting', function($data, AjaxTemplate $t) {

	// Seule la ligne touchée change : son interrupteur redit le réglage que la base porte désormais.
	$t->qs('#'.\notification\NotificationUi::getSettingId($data->type))->outerHtml(
		new \notification\NotificationUi()->getSetting($data->eStructure, $data->type, $data->settings[$data->type])
	);

});

new JsonView('menu', function($data, AjaxTemplate $t) {

	$t->qs('#notification-menu-'.$data->eStructure['id'])->innerHtml(new \notification\NotificationUi()->getMenu($data->eStructure, $data->section, $data->cNotificationHead, $data->unseen, $data->unread, $data->pilotage, $data->free));

	if($data->seen) {
		$t->qs('#'.\notification\NotificationUi::getBellId($data->eStructure).' .util-counter')->innerHtml('');
	}

});
?>
