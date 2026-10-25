<?php
new AdaptativeView('manage', function($data, DynamicTemplate $t) {

	$t->title = s("Les clés API de {value}", $data->eStructure['name']);

	if($t instanceof StructureTemplate) {
		$t->nav = 'data';
		$t->subNav = 'api';
	}

	// Un groupe atteint la page par son onglet « Administrer », dont l'écran s'appelle « Paramétrage ».
	// Le titre reste celui de la ferme : c'est un écran à part, pas un onglet de ce paramétrage, et le
	// retour y ramène. Une ferme y arrive par son menu de données, qui reste ouvert à côté.
	if($t instanceof CommunityTemplate) {

		$t->tab = 'settings';

		$t->mainTitle = \main\DesignUi::getPageHeader(
			s("Intégrations"),
			back: \structure\StructureUi::urlStructureGeneral($data->eStructure),
			backLabel: \structure\StructureUi::getSettingsGeneralName($data->eStructure)
		);

	} else {
		$t->mainTitle = \main\DesignUi::getPageHeader(s("Intégrations"));
	}

	echo new \structure\ApiKeyUi()->getManage($data->eStructure, $data->eUserOnline, $data->cAccessToken, $data->cAccessTokenRevoked, $data->cAccessTokenApplication, $data->jwt, $data->limitsReached, $data->dayLimit, $data->cLog, $data->nLog, $data->logPage, $data->nLogPage);

});

new JsonView('logResponse', function($data, AjaxTemplate $t) {

	$t->qs('#apiKey-log-response-'.$data->eLog['id'])->innerHtml(new \structure\ApiKeyUi()->getLogResponse($data->eLog));
	$t->qs('#apiKey-log-response-'.$data->eLog['id'])->removeClass('hide');
	$t->qs('#apiKey-log-expand-'.$data->eLog['id'])->addClass('apiKey-log-expanded');

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \structure\ApiKeyUi()->create($data->eStructure, $data->eAccessTokenRenew);

});

new AdaptativeView('authorize', function($data, BaseTemplate $t) {

	$t->title = s("Quelle structure relier à {value} ?", $data->authorizationRequest->getClient()->getName());
	$t->metaNoindex = TRUE;

	echo new \structure\ApiKeyUi()->getAuthorizeChoice($data->cStructureAuthorize);

});
?>
