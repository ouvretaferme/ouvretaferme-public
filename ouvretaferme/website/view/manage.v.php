<?php
new AdaptativeView('index', function($data, DynamicTemplate $t) {

	$t->title = s("Le site internet de {value}", $data->eStructure['name']);
	if($t instanceof StructureTemplate or $t instanceof AmapTemplate) {
		$t->nav = 'communications';
		$t->subNav = 'website';
	}

	if($data->eWebsite->empty()) {

		$t->mainTitle = '<h1>'.s("Créer votre site internet").'</h1>';

		echo new \website\ManageUi()->create($data->eStructure);

	} else {

		$t->mainTitle = new \website\ManageUi()->displayTitle($data->eWebsite);

		echo new \website\ManageUi()->display($data->eWebsite);
		echo new \website\ManageUi()->configure($data->eWebsite, $data->cWebpage, $data->cMenu, $data->cNews);
	}


});

new AdaptativeView('qrCode', function($data, PanelTemplate $t) {

	$url = '/website/manage:downloadQrCode?id='.$data->e['id'];

	return new \util\QrCodeUi()->getPanel(s("QR code du site"), \website\WebsiteUi::url($data->e), $url.'&format=png', $url.'&format=svg');

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \website\ManageUi()->update($data->e);

});

new AdaptativeView('contact', function($data, PanelTemplate $t) {

	return new \website\ManageUi()->contact($data->e);

});

new AdaptativeView('newsletter', function($data, PanelTemplate $t) {

	return new \website\ManageUi()->newsletter($data->e);

});
?>
