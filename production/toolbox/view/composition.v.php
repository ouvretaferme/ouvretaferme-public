<?php
new AdaptativeView('plan', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::BASKET;

	$t->title = s("Paniers de l'année");

	$uiComposition = new \toolbox\CompositionUi();

	$t->mainTitle = $uiComposition->getHeader($data->eStructure, $data->cFavorite);

	echo $uiComposition->getWeeks($data->eCompositionPlan, $data->tab);
	echo $uiComposition->getProposal($data->eCompositionPlan, $data->tab);

});

/**
 * L'accueil d'une structure qui n'a jamais composé ses paniers, commun aux outils de la Boîte.
 */
new AdaptativeView('home', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::BASKET;

	$t->title = s("Paniers de l'année");

	$uiComposition = new \toolbox\CompositionUi();

	$t->mainTitle = $uiComposition->getHeader($data->eStructure, $data->cFavorite);

	echo $uiComposition->getStart($data->eStructure);

});

/**
 * Le départ en trois questions, que « Composer mes paniers » ouvre depuis l'accueil et que la roue
 * crantée rouvre.
 */
new AdaptativeView('start', function($data, StructureTemplate $t) {

	$t->nav = 'toolbox';
	$t->subNav = \toolbox\Favorite::BASKET;

	$t->title = s("Paniers de l'année");

	$t->mainTitle = new \toolbox\CompositionUi()->getHeader($data->eStructure, $data->cFavorite);

	echo new \toolbox\CompositionStartUi()->getStart($data->eCompositionPlan);

});

/**
 * Un onglet ouvert sans recharger la page (chantier Performances, P14) : ce qui, hors des paniers,
 * nomme la taille ouverte se rend avec eux, c'est-à-dire la carte des semaines livrées, dont le
 * panneau relit la taille ouverte. L'adresse de la page suit dans l'historique, et le contexte
 * de la page aussi : c'est lui qu'un rechargement après une écriture relit (`ReloadAction`,
 * `ajax.js`), et non l'adresse affichée.
 */
new JsonView('tab', function($data, AjaxTemplate $t) {

	$uiComposition = new \toolbox\CompositionUi();

	$url = match($data->tab) {
		\toolbox\CompositionPlan::TAB_FORMULA => \toolbox\CompositionUi::urlFormula($data->eStructure, $data->eCompositionPlan['eCompositionFormula']),
		\toolbox\CompositionPlan::TAB_SYNTHESIS => \toolbox\CompositionUi::urlSynthesis($data->eStructure),
		\toolbox\CompositionPlan::TAB_SPECIES => \toolbox\CompositionUi::urlSpecies($data->eStructure),
	};

	$t->qs('#toolbox-composition-weeks')->outerHtml($uiComposition->getWeeks($data->eCompositionPlan, $data->tab));
	$t->qs('#toolbox-composition-proposal')->outerHtml($uiComposition->getProposal($data->eCompositionPlan, $data->tab));
	$t->js()->pushHistory($url);
	$t->qs('body')->setAttribute('data-context', $url);

});

new AdaptativeView('weeks', function($data, PanelTemplate $t) {

	return new \toolbox\CompositionUi()->getWeeksPanel($data->eCompositionPlan, $data->formula, $data->origin);

});

/**
 * Les semaines livrées sont communes à toutes les tailles : toutes sont recomposées, et avec elles
 * la carte des semaines livrées, et les paniers, onglets compris. L'onglet rendu est celui d'où la
 * roue de la carte est partie (`origin`) : une taille, la synthèse ou les espèces, dont le prix se
 * lit sur les semaines livrées.
 */
new JsonView('doWeeks', function($data, AjaxTemplate $t) {

	$uiComposition = new \toolbox\CompositionUi();

	$t->js()->closePanel('#toolbox-composition-weeks-panel');
	$t->qs('#toolbox-composition-weeks')->outerHtml($uiComposition->getWeeks($data->eCompositionPlan, $data->tab));
	$t->qs('#toolbox-composition-proposal')->outerHtml($uiComposition->getProposal($data->eCompositionPlan, $data->tab));

});

/**
 * Une soudure retirée (lot r22, point 4) change les semaines livrées comme le panneau, qu'elle
 * n'ouvre pas : la carte des semaines et les paniers.
 */
new JsonView('doRemoveWeeks', function($data, AjaxTemplate $t) {

	$uiComposition = new \toolbox\CompositionUi();

	$t->qs('#toolbox-composition-weeks')->outerHtml($uiComposition->getWeeks($data->eCompositionPlan));
	$t->qs('#toolbox-composition-proposal')->outerHtml($uiComposition->getProposal($data->eCompositionPlan));

});

new AdaptativeView('notice', function($data, PanelTemplate $t) {

	return new \toolbox\CompositionUi()->getNoticePanel($data->eCompositionPlan, $data->notice);

});

new AdaptativeView('settings', function($data, PanelTemplate $t) {

	return new \toolbox\CompositionUi()->getSettingsPanel($data->eCompositionPlan, $data->formula, $data->origin);

});

/**
 * Un réglage peut changer le nom et le prix d'une taille, que les onglets portent, et recomposer
 * n'importe laquelle, donc la synthèse : les paniers se rendent entiers, sur l'onglet d'où part la
 * roue. La carte des semaines livrées ne bouge pas, les semaines étant communes à toutes les
 * tailles.
 */
new JsonView('doSettings', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#toolbox-composition-settings-panel');
	$t->qs('#toolbox-composition-proposal')->outerHtml(new \toolbox\CompositionUi()->getProposal($data->eCompositionPlan, $data->tab));

});

/**
 * « Synchroniser » (lot r27), depuis le bloc « Plan de culture » en tête de l'onglet « Espèces »
 * (r29, #80, r48a) : la saison des espèces que le plan de culture date a changé, et l'onglet se rend
 * entier, sans son bloc. Les paniers recomposés ne sont pas à l'écran.
 */
new JsonView('doSynchronize', function($data, AjaxTemplate $t) {

	$t->qs('#toolbox-composition-species')->outerHtml(new \toolbox\CompositionUi()->getSpecies($data->eCompositionPlan));

});

new AdaptativeView('addSpecies', function($data, PanelTemplate $t) {

	return new \toolbox\CompositionSpeciesUi()->add($data->eCompositionSpecies, $data->variant);

});

new JsonView('queryAddProduct', function($data, AjaxTemplate $t) {

	$t->push('results', \toolbox\CompositionSpeciesUi::getProductResults($data->cProduct, $data->e));

});

/**
 * Une espèce ajoutée (r48c) prend son rang dans le tableau de l'onglet « Espèces », à sa place dans
 * l'ordre alphabétique (`CompositionUi::getSpeciesAdded()`). Les paniers qu'elle a recomposés ne
 * sont pas à l'écran.
 */
new JsonView('doAddSpecies', function($data, AjaxTemplate $t) {

	$changes = new \toolbox\CompositionUi()->getSpeciesAdded($data->eCompositionPlan, $data->eCompositionSpecies['id']);

	$t->js()->closePanel('#toolbox-composition-add-panel');

	foreach($changes['insert'] as [$selector, $position, $html]) {
		$t->qs($selector)->insertAdjacentHtml($position, $html);
	}

	foreach($changes['inner'] as $selector => $html) {
		$t->qs($selector)->innerHtml($html);
	}

});

/**
 * Le même panneau au départ (r48e), où rien n'est encore écrit.
 */
new AdaptativeView('addStartSpecies', function($data, PanelTemplate $t) {

	return new \toolbox\CompositionSpeciesUi()->addStart($data->eCompositionSpecies, $data->delivered);

});

/**
 * L'espèce ajoutée au départ n'est pas écrite : son rang arrive hors du formulaire, et
 * `ToolboxCompositionStart.insert()` le range dans la liste, à sa place dans l'ordre alphabétique,
 * où il part avec « Composer mes paniers ».
 */
new JsonView('doAddStartSpecies', function($data, AjaxTemplate $t) {

	$row = new \toolbox\CompositionStartUi()->getSpeciesRow(new \util\FormUi(), $data->eCompositionPlan, $data->eCompositionSpecies);

	$t->js()->closePanel('#toolbox-composition-add-panel');
	$t->qs('#toolbox-composition-start-added')->innerHtml($row);
	$t->js()->eval('ToolboxCompositionStart.insert()');

});

new AdaptativeView('getPdf', function($data, PdfTemplate $t) {

	$uiCompositionPdf = new \toolbox\CompositionPdfUi();

	$t->title = s("Les paniers de l'année");

	echo \main\PdfUi::getHeader();
	echo \main\PdfUi::getStructure($data->e, $t->title, $uiCompositionPdf->getPeriod($data->eCompositionPlan));
	echo $uiCompositionPdf->getFigures($data->eCompositionPlan);
	echo $uiCompositionPdf->getWeeks($data->eCompositionPlan);
	echo \main\PdfUi::getFooter($data->e, note: s("Paniers de l'année édités le {date}.", ['date' => \util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE)]));

});
?>
