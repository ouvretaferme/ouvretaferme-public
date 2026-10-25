<?php
/**
 * Les notes de la structure (L47) : la liste, la note ouverte, et ce que ses administrateurs y écrivent.
 *
 * Chaque route se garde comme l'entrée « Notes » du groupe Bureau, par `isLanding()` : une
 * structure qui n'a pas l'entrée n'a pas non plus les écrans qu'elle ouvre.
 */

/**
 * La liste d'où s'ouvre un panneau de note (`NoteUi::getListContext()`), lue des arguments que ses liens
 * portent et que ses formulaires postent. Sans `origin=list`, un tableau vide : la note s'ouvre en page,
 * et ses gestes y reviennent. Le filtre n'est gardé que s'il se lit : il ne sert qu'à refaire l'adresse
 * de la liste, dont la page vérifie elle-même que l'objet existe.
 */
$getList = function(): array {

	if(INPUT('origin') !== 'list') {
		return [];
	}

	$eTarget = \note\TargetLib::getByValue(INPUT('target'));

	return [
		'tab' => INPUT('tab', '?int'),
		'target' => $eTarget->empty() ? NULL : $eTarget->getValue(),
		'page' => INPUT('page', 'int')
	];

};

/**
 * Ce que demande la note ouverte par qui l'écrit, qui la modifie dans sa lecture même : son carnet
 * se choisit parmi ceux de la structure, et « Qui peut la lire » montre les administrateurs à qui elle
 * reste réservée. Les carnets sont reçus : après un enregistrement depuis la liste, ce sont ceux qu'elle
 * vient de lire pour ses onglets.
 */
$prepareWrite = function(\stdClass $data, Collection $cNotebook): void {

	$data->e['cNotebook'] = $cNotebook;
	$data->e['cMemberAdmin'] = \structure\MemberLib::getAdminsByStructure($data->eStructure);

	// Le carnet d'une note est toujours un carnet de sa structure (`Note::notebook.structure`), donc
	// l'un de ceux qu'on vient de lire.
	if($data->e['notebook']->notEmpty()) {
		$data->e['notebook'] = $cNotebook[$data->e['notebook']['id']];
	}

};

/**
 * Ce que montre la note ouverte, en page comme en panneau : son carnet nommé et ses rattachements. Un
 * rattachement dont l'objet a disparu ne se repropose pas : il part au prochain enregistrement des
 * rattachements.
 */
$prepareRead = function(\stdClass $data) use($prepareWrite): void {

	if($data->e->assertUpdate()) {
		$prepareWrite($data, \note\NotebookLib::getByStructure($data->eStructure));
	} else if($data->e['notebook']->notEmpty()) {
		$data->e['notebook'] = \note\NotebookLib::getById($data->e['notebook']['id']);
	}

	\note\TargetLib::putByNotes($data->eStructure, new Collection([$data->e]));

};

/**
 * La liste d'un onglet, telle que la page la montre : à son ouverture, puis après un geste fait dans le
 * panneau d'une note, que la réponse redessine à partir du même état.
 */
$prepareList = function(\stdClass $data, ?int $notebook, ?string $target, int $page): void {

	$data->page = $page;

	$data->cNotebook = \note\NotebookLib::getByStructure($data->eStructure);
	$data->counts = \note\NoteLib::countByNotebook($data->eStructure);

	// L'onglet : sans `notebook`, toutes les notes ; `notebook=0`, « Non classé », qui n'existe
	// qu'avec un carnet ; sinon un carnet de la structure. Un carnet inconnu, supprimé entre-temps
	// ou d'une autre structure, ramène à toutes les notes.
	$data->search = new Search();

	if($data->cNotebook->notEmpty()) {

		if($notebook === 0) {
			$data->search->set('notebook', new \note\Notebook());
		} else if($notebook !== NULL and $data->cNotebook->offsetExists($notebook)) {
			$data->search->set('notebook', $data->cNotebook[$notebook]);
		}

	}

	// Le rattachement du filtre, `<type>:<id>`, ne vaut que si son package rend l'objet pour la
	// structure : un objet supprimé ou d'une autre structure ramène à la liste sans filtre.
	$eTarget = \note\TargetLib::getByValues($data->eStructure, [$target])->first();

	if($eTarget !== NULL) {
		$data->search->set('target', $eTarget);
	}

	$data->cNote = \note\NoteLib::getByStructure($data->eStructure, $data->search, $data->page);

	if($data->search->has('target')) {
		$data->listed = \note\NoteLib::countBySearch($data->eStructure, $data->search);
	} else if($data->search->has('notebook')) {

		$eNotebook = $data->search->get('notebook');

		// Le comptage est groupé : un onglet sans note n'y a pas de ligne.
		$data->listed = $data->counts[$eNotebook->empty() ? 0 : $eNotebook['id']] ?? 0;

	} else {
		$data->listed = array_sum($data->counts);
	}

	$data->nPage = ceil($data->listed / \note\NoteSetting::LIST_MAX);

	// Un onglet sans note n'a rien à chercher : « Nouvelle note » passe de la rangée des outils à son bloc
	// vide. Un filtre qui ne trouve rien garde la rangée, d'où on le retire, sauf quand la structure n'a
	// plus aucune note à filtrer.
	$data->isTabEmpty = (
		$data->listed === 0 and
		($data->search->has('target') === FALSE or $data->counts === [])
	);

	$data->eNoteCreate = new \note\Note([
		'structure' => $data->eStructure,
		'notebook' => $data->search->get('notebook', new \note\Notebook())
	]);

	$data->eNotebookCreate = new \note\Notebook([
		'structure' => $data->eStructure
	]);

};

/**
 * Ce que la réponse redessine de la liste restée sous le panneau, après qu'une note y a été modifiée ou
 * supprimée (`$data->listChange`). La liste est relue après l'écriture, et la granularité se décide ici :
 * - `card` : la note reste à cette page de l'onglet, et sa carte se repose devant celle qui la suit
 *   désormais (`$data->eNoteNext`), la liste se rangeant par date de modification ;
 * - `remove` : la note quitte un onglet qui tient sur une page, et sa carte part seule ;
 * - `all` : tout le reste. Une page suivante ferait remonter une note à la place de celle qui part, un
 *   onglet vidé change de bloc, et un filtre par rattachement affiche le nombre de notes qu'il garde.
 * Les compteurs des onglets se réécrivent avec `card` et `remove`, `all` redessinant la tête entière.
 *
 * Le panneau de la note, lui, suit le geste (`$data->panelChange`) : `replace` après une modification,
 * `close` après une suppression, `targets` après un changement de rattachements, qui ne redessine que
 * leur branche.
 */
$prepareListChange = function(\stdClass $data, \note\Note $eNote) use($prepareList): void {

	$prepareList($data, $data->list['tab'], $data->list['target'], $data->list['page']);

	$position = array_search($eNote['id'], $data->cNote->getIds(), TRUE);

	if($position !== FALSE and $data->page === 0) {

		$data->listChange = 'card';
		$data->eNoteCard = $data->cNote[$position];
		$data->eNoteNext = $data->cNote->offsetExists($position + 1) ? $data->cNote[$position + 1] : new \note\Note();

	} else if(
		$position === FALSE and
		$data->listed < \note\NoteSetting::LIST_MAX and
		$data->cNote->notEmpty() and
		$data->search->has('target') === FALSE
	) {
		$data->listChange = 'remove';
	} else {
		$data->listChange = 'all';
	}

};

new \structure\StructurePage()
	/**
	 * `StructurePage` valide `Structure::canRead()`, qui ouvre aussi l'école et les groupes qui observent
	 * la production : les notes ne se lisent qu'entre membres.
	 */
	->read('/ferme/{id}/notes', function($data) use($prepareList) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

		if($data->eStructure->isMember() === FALSE) {
			throw new NotAllowedAction('Notes are read by members only');
		}

		$prepareList($data, GET('notebook', '?int'), GET('target', '?string'), GET('page', 'int'));

		throw new ViewAction($data);

	})
	/**
	 * Ce que propose le filtre de la liste : les objets auxquels sont rattachées les notes que le membre
	 * lit, réduits à ce qu'il tape. Chaque suggestion mène à l'onglet d'où il cherche.
	 */
	->read('queryFilter', function($data) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

		if($data->eStructure->isMember() === FALSE) {
			throw new NotAllowedAction('Notes are read by members only');
		}

		$data->notebook = POST('notebook', '?int');
		$data->cTarget = \note\TargetLib::getReadableByStructure($data->eStructure, POST('query'));

		throw new ViewAction($data);

	}, method: 'post')
	/**
	 * Ce qu'on peut rattacher à une note, pour l'omnibox du formulaire : seul celui qui écrit les notes
	 * de la structure le demande.
	 */
	->read('queryTarget', function($data) {

		$data->eStructure = $data->e;

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

		$data->cTarget = \note\TargetLib::search($data->eStructure, POST('query'));

		throw new ViewAction($data);

	}, method: 'post', validate: ['canManage'])
	/**
	 * Le bloc Notes des écrans de production, replié ou déplié par le membre (L84) : une préférence
	 * à lui, qui vaut sur tous ces écrans. Le bloc a déjà basculé à l'écran, d'où une réponse vide.
	 * En read() et non en write() : ce qui s'écrit est la ligne du membre, gardée par canSelf, et un
	 * salarié qui ne gère pas la structure replie ses notes comme un administrateur.
	 */
	->read('doLinked', function($data) {

		$data->eStructure = $data->e;

		if(OTF_DEMO) {
			throw new \FailAction('user\User::demo.write');
		}

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

		if($data->eStructure->isMember() === FALSE) {
			throw new NotAllowedAction('Notes are read by members only');
		}

		$eMember = $data->eStructure->getMember();
		$eMember->validate('canSelf');

		$fw = new FailWatch();

		$eMember->build(['viewNoteLinked'], $_POST);

		$fw->validate();

		\structure\MemberLib::update($eMember, ['viewNoteLinked']);

		throw new VoidAction();

	}, method: 'post');

new \note\NotePage()
	->read('/note/{id}', function($data) use($getList, $prepareRead) {

		$data->eStructure = \structure\StructureLib::getById($data->e['structure']['id']);

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

		$prepareRead($data);

		$data->list = $getList();

		// Ouverte de la liste, la note s'y pose en panneau. Le même lien ouvert dans un nouvel onglet, ou
		// rechargé, arrive hors ajax : il rend la page de la note.
		if($data->list !== [] and Route::getRequestedWith() === 'ajax') {
			throw new ViewAction($data, ':panel');
		}

		throw new ViewAction($data);

	});

new \note\NotePage(function($data) use($getList) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure', '?int'));

		if($data->eStructure->empty()) {
			throw new NotExistsAction('structure #'.INPUT('structure', '?int'));
		}

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

		$data->list = $getList();

	})
	->getCreateElement(function($data) {

		return new \note\Note([
			'structure' => $data->eStructure,
			'notebook' => new \note\Notebook(),
			'readers' => \note\Note::ADMIN,
			'cTarget' => new Collection()
		]);

	})
	->create(function($data) {

		$data->e['cNotebook'] = \note\NotebookLib::getByStructure($data->eStructure);
		$data->e['cMemberAdmin'] = \structure\MemberLib::getAdminsByStructure($data->eStructure);

		// « Nouvelle note » depuis l'onglet d'un carnet range la note dans ce carnet.
		$notebook = GET('tab', '?int');

		if($notebook !== NULL and $data->e['cNotebook']->offsetExists($notebook)) {
			$data->e['notebook'] = $data->e['cNotebook'][$notebook];
		}

		throw new ViewAction($data);

	})
	->doCreate(function($data) {

		if($data->list === []) {
			throw new RedirectAction(\note\NoteUi::url($data->e).'?success=note\\Note::created');
		}

		// La liste rouvre « Toutes » si l'on y écrivait. Depuis l'onglet d'un carnet, elle ouvre celui de
		// la note, que le formulaire a pu changer. Le filtre et la page tombent : rien ne dit que la note
		// y paraît.
		if($data->list['tab'] === NULL) {
			$tab = NULL;
		} else if($data->e['notebook']->empty()) {
			$tab = 0;
		} else {
			$tab = $data->e['notebook']['id'];
		}

		$data->listCreated = [
			'tab' => $tab,
			'target' => NULL,
			'page' => 0
		];

		throw new ViewAction($data);

	});

new \note\NotePage()
	->applyElement(function($data, \note\Note $e) use($getList) {

		$data->eStructure = \structure\StructureLib::getById($e['structure']['id']);

		if(\dashboard\DashboardSetting::isLanding($data->eStructure) === FALSE) {
			throw new NotExpectedAction('Notes are closed for this structure');
		}

		$data->list = $getList();

	})
	->doUpdate(function($data) use($prepareWrite, $prepareListChange) {

		if($data->list === []) {
			throw new ReloadAction('note', 'Note::updated');
		}

		// Le panneau de la note se redessine : relue, elle porte ses rattachements, que `doUpdate` ne
		// construit pas, avec ce que leurs packages en rendent.
		$data->e = \note\NoteLib::getById($data->e['id']);

		$data->panelChange = 'replace';
		$prepareListChange($data, $data->e);

		// `ModulePage` a vérifié que la note s'écrit : son panneau redessiné est celui de qui l'écrit.
		$prepareWrite($data, $data->cNotebook);
		\note\TargetLib::putByNotes($data->eStructure, new Collection([$data->e]));

		throw new ViewAction($data);

	})
	->update(function($data) {

		\note\TargetLib::putByNotes($data->eStructure, new Collection([$data->e]));

		throw new ViewAction($data);

	}, page: 'updateTargets')
	/**
	 * Les rattachements enregistrés seuls : la note reste ouverte dessous, avec ce qu'on y a changé sans
	 * l'enregistrer, et seule sa branche des rattachements se redessine.
	 */
	->doUpdateProperties('doUpdateTargets', ['cTarget'], function($data) use($prepareListChange) {

		// Le cast de `cTarget` a déjà posé l'objet de chaque rattachement (`TargetLib::getByValues()`).
		\note\TargetLib::sortByNotes(new Collection([$data->e]));

		$data->panelChange = 'targets';

		if($data->list !== []) {
			$prepareListChange($data, $data->e);
		}

		throw new ViewAction($data);

	})
	->doUpdateProperties('doUpdatePinned', ['pinned'], function($data) use($prepareList) {

		if($data->list !== []) {
			$prepareList($data, $data->list['tab'], $data->list['target'], $data->list['page']);
		}

		throw new ViewAction($data);

	})
	->doDelete(function($data) use($prepareListChange) {

		if($data->list !== []) {

			$data->panelChange = 'close';
			$prepareListChange($data, $data->e);

			throw new ViewAction($data);

		}

		throw new RedirectAction(\note\NoteUi::urlStructure($data->eStructure).'?success=note\\Note::deleted');

	});
?>
