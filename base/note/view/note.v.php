<?php
/**
 * Les écrans des notes vivent sous Bureau, sans univers : aucun `$t->nav`, comme l'accueil. C'est
 * `$t->homeNav` qui garde le groupe Bureau ouvert et y choisit l'entrée « Notes ».
 */
new AdaptativeView('/ferme/{id}/notes', function($data, StructureTemplate $t) {

	$t->title = s("Notes de {value}", $data->eStructure['name']);
	$t->canonical = \note\NoteUi::urlStructure($data->eStructure);
	$t->homeNav = 'note';

	$noteUi = new \note\NoteUi();

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Notes"));

	echo $noteUi->getTabs($data->eStructure, $data->cNotebook, $data->counts, $data->search, $data->listed, $data->isTabEmpty, $data->page, $data->eNoteCreate, $data->eNotebookCreate);
	echo $noteUi->getList($data->eStructure, $data->cNote, $data->cNotebook, $data->counts, $data->search, $data->isTabEmpty, $data->page, $data->nPage, $data->eNoteCreate);

});

new AdaptativeView('/note/{id}', function($data, StructureTemplate $t) {

	$t->title = $data->e['title'];
	$t->canonical = \note\NoteUi::url($data->e);
	$t->homeNav = 'note';

	$noteUi = new \note\NoteUi();

	// L'en-tête est celui de la rubrique : la fiche de la note porte son titre et ses gestes.
	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("Notes"),
		back: \note\NoteUi::urlStructure($data->eStructure)
	);

	echo $noteUi->getRead($data->eStructure, $data->e);

});

new AdaptativeView('panel', function($data, PanelTemplate $t) {
	return new \note\NoteUi()->getPanel($data->eStructure, $data->e, $data->list);
});

new JsonView('queryFilter', function($data, AjaxTemplate $t) {
	$t->push('results', \note\NoteUi::getFilterSuggestions($data->eStructure, $data->cTarget, $data->notebook));
});

new JsonView('queryTarget', function($data, AjaxTemplate $t) {
	$t->push('results', \note\NoteUi::getTargetSuggestions($data->cTarget));
});

new AdaptativeView('updateTargets', function($data, PanelTemplate $t) {
	return new \note\NoteUi()->updateTargets($data->e, $data->list);
});

new AdaptativeView('create', function($data, PanelTemplate $t) {
	return new \note\NoteUi()->create($data->e, $data->list);
});

/**
 * La note créée depuis la liste y brille à son arrivée, dans l'onglet que la page a choisi.
 */
new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$t->ajaxRedirect(\note\NoteUi::urlList($data->eStructure, $data->listCreated), purgeLayers: TRUE);
	$t->js()->success('note', 'Note::created');
	$t->js()->highlight(['#'.\note\NoteUi::getCardId($data->e)]);

});

/**
 * Une note modifiée ou supprimée depuis son panneau, ou ses rattachements changés dans le leur, avec ce
 * que le geste change de la liste restée dessous, dans la granularité que la page a choisie
 * (`$prepareListChange`, note.p.php).
 *
 * Modifiée dans son panneau, la note s'y redessine sur place, et l'onglet du navigateur prend son
 * titre : la rouvrir par `pushPanel()` l'empilerait une seconde fois dans l'historique, et le retour
 * arrière ne la fermerait plus. Supprimée, son panneau se referme. Ses rattachements enregistrés, leur
 * panneau se ferme, et la note ouverte dessous, en panneau ou en page, ne reprend que leur branche :
 * ce qu'on y a changé sans l'enregistrer reste.
 */
new JsonView(['doUpdate', 'doDelete', 'doUpdateTargets'], function($data, AjaxTemplate $t) {

	$noteUi = new \note\NoteUi();

	switch($data->panelChange) {

		case 'replace' :
			$panel = $noteUi->getPanel($data->eStructure, $data->e, $data->list);
			$t->qs('#panel-note')->replacePanel($panel);
			$t->qs('title')->innerHtml($panel->getDocumentTitle());
			break;

		case 'close' :
			$t->js()->moveHistory(-1);
			break;

		case 'targets' :
			$list = '#'.\note\NoteUi::getRecordListId($data->e);
			$t->js()->closePanel('#panel-note-targets');
			$t->qs($list.' > .note-targets')->remove();
			$t->qs($list)->insertAdjacentHtml('afterbegin', $noteUi->getTargetsItem($data->eStructure, $data->e));
			break;

	}

	// La note ouverte en page n'a pas de liste dessous.
	if($data->list !== []) {

		if($data->listChange === 'all') {

			$t->qs('#note-head')->outerHtml($noteUi->getTabs($data->eStructure, $data->cNotebook, $data->counts, $data->search, $data->listed, $data->isTabEmpty, $data->page, $data->eNoteCreate, $data->eNotebookCreate));
			$t->qs('#note-list')->outerHtml($noteUi->getList($data->eStructure, $data->cNote, $data->cNotebook, $data->counts, $data->search, $data->isTabEmpty, $data->page, $data->nPage, $data->eNoteCreate));

		} else {

			$t->qs('#'.\note\NoteUi::getCardId($data->e))->remove();

			if($data->listChange === 'card') {

				$card = $noteUi->getListCard($data->eStructure, $data->eNoteCard, $data->cNotebook, $data->search, $data->page);

				if($data->eNoteNext->notEmpty()) {
					$t->qs('#'.\note\NoteUi::getCardId($data->eNoteNext))->insertAdjacentHtml('beforebegin', $card);
				} else {
					$t->qs('#note-list .util-cards')->insertAdjacentHtml('beforeend', $card);
				}

			}

			if($data->cNotebook->notEmpty()) {

				foreach(\note\NoteUi::getTabCounts($data->cNotebook, $data->counts) as $key => $count) {
					$t->qsa('[data-note-count="'.$key.'"]')->innerHtml((string)$count);
				}

			}

		}

	}

	$t->js()->success('note', match($data->panelChange) {
		'replace', 'targets' => 'Note::updated',
		'close' => 'Note::deleted',
	});

});

/**
 * L'épingle ne change rien de ce que la note ouverte montre, hormis l'entrée de la roue qui l'a posée.
 */
new JsonView('doUpdatePinned', function($data, AjaxTemplate $t) {

	$noteUi = new \note\NoteUi();

	// La liste entière et non la seule carte : l'épingle change les notes d'une page, une note épinglée
	// depuis la page suivante monte dans celle-ci, une note désépinglée peut en sortir.
	if($data->list !== []) {
		$t->qs('#note-list')->outerHtml($noteUi->getList($data->eStructure, $data->cNote, $data->cNotebook, $data->counts, $data->search, $data->isTabEmpty, $data->page, $data->nPage, $data->eNoteCreate));
	}

	$t->qs('#'.\note\NoteUi::getPinId($data->e))->outerHtml($noteUi->getPin($data->e, $data->list));
	$t->js()->closeDropdowns();
	$t->js()->success('note', $data->e['pinned'] ? 'Note::pinned' : 'Note::unpinned');

});
?>
