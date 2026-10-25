<?php
namespace note;

/**
 * Les carnets des notes : la suppression qui range les notes dans « Non classé » sans en
 * effacer, l'unicité du nom dite au champ, le carnet d'une autre structure refusé, les compteurs des
 * onglets qui suivent ce que lit le membre, et « Non classé » qui n'existe qu'avec un carnet.
 *
 * Même décor et même façon de jouer la vraie page que `Note.t.php`, qui dit pourquoi.
 *
 * Lancement : dev/test package=note file=Notebook
 */
class NotebookTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;
	const STRUCTURE_OTHER = \structure\FixtureAiLib::F2;

	const USER_ADMIN = 4000000101;
	const USER_MEMBER = 4000000102;

	protected \structure\Structure $eStructure;

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;
	private \Closure $enabledInitial;
	private \Closure $landingInitial;

	protected function getTransactionModel(): \ModuleModel {
		return new NoteModel();
	}

	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(self::STRUCTURE)
			->get();

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.self::STRUCTURE.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();
		$this->enabledInitial = \dashboard\DashboardSetting::$enabled;
		$this->landingInitial = \dashboard\DashboardSetting::$landing;

		\dashboard\DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;
		\dashboard\DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;

	}

	public function setUp(): void {

		parent::setUp();

		$this->connectAs(self::USER_ADMIN, manager: TRUE);

		// Le décor F1 garde en dev les notes et les carnets de la contre-recette : chaque méthode part
		// d'une structure vide, dans la transaction qui les rendra à la fin.
		foreach(Note::model()->select('id')->whereStructure($this->eStructure)->getCollection() as $eNote) {
			NoteLib::delete($eNote);
		}

		foreach(NotebookLib::getByStructure($this->eStructure) as $eNotebook) {
			NotebookLib::delete($eNotebook);
		}

	}

	public function finalize(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);

		\dashboard\DashboardSetting::$enabled = $this->enabledInitial;
		\dashboard\DashboardSetting::$landing = $this->landingInitial;

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function testAnAdministratorWritesOrdersAndDeletesNotebooks(): void {

		$eNotebookCultures = $this->createNotebook('Cultures');
		$eNotebookBuildings = $this->createNotebook('Bâtiments');

		$this->assertEquals([1, 2], [$eNotebookCultures['position'], $eNotebookBuildings['position']], "un carnet neuf prend le dernier onglet");

		$action = $this->runPage('note/notebook:doUpdate', ['id' => $eNotebookBuildings['id'], 'name' => 'Bâtiments et serres']);

		$this->assertTrue($action instanceof \ReloadAction, "l'administrateur renomme un carnet : ".get_class($action));
		$this->assertEquals('Bâtiments et serres', NotebookLib::getById($eNotebookBuildings['id'])['name'], "le nom est écrit");

		$action = $this->runPage('note/notebook:doIncrementPosition', ['id' => $eNotebookBuildings['id'], 'increment' => -1]);

		$this->assertTrue($action instanceof \ReloadAction, "il le place avant son voisin : ".get_class($action));
		$this->assertEquals([$eNotebookBuildings['id'], $eNotebookCultures['id']], NotebookLib::getByStructure($this->eStructure)->getIds(), "les deux carnets échangent leur place");

		$action = $this->runPage('note/notebook:doIncrementPosition', ['id' => $eNotebookBuildings['id'], 'increment' => -1]);

		$this->assertEquals([1, 2], NotebookLib::getByStructure($this->eStructure)->getColumn('position'), "le premier carnet ne recule pas plus loin");

		$action = $this->runPage('note/notebook:doIncrementPosition', ['id' => $eNotebookBuildings['id'], 'increment' => 5]);

		$this->assertTrue($action instanceof \NotExpectedAction, "un saut de plusieurs onglets est refusé : ".get_class($action));

		$this->createNotebook('Matériel');

		$action = $this->runPage('note/notebook:doDelete', ['id' => $eNotebookBuildings['id']]);

		$this->assertTrue($action instanceof \RedirectAction, "il supprime un carnet : ".get_class($action));
		$this->assertEquals([1, 2], NotebookLib::getByStructure($this->eStructure)->getColumn('position'), "les carnets restants se renumérotent");

	}

	/** `delete()` vide le carnet vers « Non classé » sans effacer de note. */
	public function testDeletingANotebookKeepsItsNotesUnclassified(): void {

		$eNotebook = $this->createNotebook('Cultures');
		$eNotebookOther = $this->createNotebook('Matériel');

		$eNoteInside = $this->createNote(Note::TEAM, $eNotebook);
		$eNoteOther = $this->createNote(Note::TEAM, $eNotebookOther);
		$eNoteUnclassified = $this->createNote(Note::ADMIN, new Notebook());

		// Une date passée, que `build()` n'a pas à vérifier : ce décor compare la date de tri, qu'aucun
		// formulaire n'écrit.
		Note::model()->update($eNoteInside, ['updatedAt' => '2020-01-01 00:00:00']);

		$this->runPage('note/notebook:doDelete', ['id' => $eNotebook['id']]);

		$this->assertTrue(NotebookLib::getById($eNotebook['id'])->empty(), "le carnet est supprimé");

		$eNoteInsideAfter = $this->getNote($eNoteInside);

		$this->assertTrue($eNoteInsideAfter->notEmpty(), "sa note est conservée");
		$this->assertTrue($eNoteInsideAfter['notebook']->empty(), "et passe dans « Non classé »");
		$this->assertEquals('2020-01-01 00:00:00', $eNoteInsideAfter['updatedAt'], "sans passer pour modifiée");
		$this->assertTrue($this->getNote($eNoteOther)['notebook']->is($eNotebookOther), "la note d'un autre carnet n'en bouge pas");
		$this->assertTrue($this->getNote($eNoteUnclassified)->notEmpty(), "ni celle qui était déjà hors des carnets");

		// Le comptage est groupé, dans l'ordre que la base choisit : seules les clés comptent.
		$counts = NoteLib::countByNotebook($this->eStructure);
		ksort($counts);

		$this->assertEquals([0 => 2, $eNotebookOther['id'] => 1], $counts, "les compteurs le suivent");

	}

	/** L'unicité [structure, name] remonte en erreur de champ, pas en erreur de base. */
	public function testANotebookNameIsUniqueInTheStructure(): void {

		$this->createNotebook('Cultures');
		$eNotebookOther = $this->createNotebook('Matériel');

		$fw = new \FailWatch();
		$action = $this->runPage('note/notebook:doCreate', ['structure' => self::STRUCTURE, 'name' => 'Cultures']);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Notebook::name.duplicate'), "un second carnet du même nom est refusé : ".get_class($action).' '.$fw);
		$this->assertTrue(AlertUi::getError('Notebook::name.duplicate') !== NULL, "avec un message pour le champ");

		$fw = new \FailWatch();
		$action = $this->runPage('note/notebook:doUpdate', ['id' => $eNotebookOther['id'], 'name' => 'Cultures']);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Notebook::name.duplicate'), "renommer un carnet du nom d'un autre aussi : ".get_class($action).' '.$fw);

		$action = $this->runPage('note/notebook:doUpdate', ['id' => $eNotebookOther['id'], 'name' => 'Matériel']);

		$this->assertTrue($action instanceof \ReloadAction, "un carnet garde son propre nom : ".get_class($action));

		$fw = new \FailWatch();
		$action = $this->runPage('note/notebook:doCreate', ['structure' => self::STRUCTURE, 'name' => '']);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Notebook::name.check'), "un nom vide est refusé : ".get_class($action).' '.$fw);

		$this->assertEquals(2, NotebookLib::getByStructure($this->eStructure)->count(), "aucun carnet n'est écrit");

	}

	/** Un carnet d'une autre structure posté avec une note est refusé. */
	public function testANotebookOfAnotherStructureIsRefused(): void {

		$eStructureOther = \structure\StructureLib::getById(self::STRUCTURE_OTHER);

		// Un carnet dans F2, que l'administrateur de F1 ne peut pas créer par la page : il n'en est pas
		// membre. `build()` y vérifierait le nom, que ce décor choisit lui-même.
		$eNotebookOther = new Notebook([
			'structure' => $eStructureOther,
			'name' => 'Carnet de F2'
		]);

		NotebookLib::create($eNotebookOther);

		$fw = new \FailWatch();
		$action = $this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Pucerons', 'notebook' => $eNotebookOther['id'], 'content' => '', 'readers' => Note::TEAM]);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Note::notebook.structure'), "la note n'est pas rangée dans le carnet d'une autre structure : ".get_class($action).' '.$fw);

		$eNotebook = $this->createNotebook('Cultures');
		$eNote = $this->createNote(Note::TEAM, $eNotebook);

		$fw = new \FailWatch();
		$action = $this->runPage('note/note:doUpdate', ['id' => $eNote['id'], 'title' => 'Pucerons', 'notebook' => $eNotebookOther['id'], 'content' => '', 'readers' => Note::TEAM]);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Note::notebook.structure'), "ni déplacée dedans : ".get_class($action).' '.$fw);
		$this->assertTrue($this->getNote($eNote)['notebook']->is($eNotebook), "elle reste dans son carnet");

		$action = $this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Pucerons', 'notebook' => $eNotebook['id'], 'content' => '', 'readers' => Note::TEAM]);

		$this->assertTrue($action instanceof \RedirectAction, "un carnet de la structure est accepté : ".get_class($action));

		$action = $this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Hors carnet', 'notebook' => '', 'content' => '', 'readers' => Note::TEAM]);

		$this->assertTrue($action instanceof \RedirectAction, "« Non classé » aussi : ".get_class($action));

	}

	/** Les compteurs ne comptent pas une note que le membre ne lit pas. */
	public function testTheCountersFollowWhatTheMemberReads(): void {

		$eNotebook = $this->createNotebook('Cultures');

		$this->createNote(Note::TEAM, $eNotebook);
		$this->createNote(Note::ADMIN, $eNotebook);
		$this->createNote(Note::ADMIN, new Notebook());

		$counts = NoteLib::countByNotebook($this->eStructure);
		ksort($counts);

		$this->assertEquals([0 => 1, $eNotebook['id'] => 2], $counts, "l'administrateur compte toutes les notes");

		$this->connectAs(self::USER_MEMBER, manager: FALSE);

		$counts = NoteLib::countByNotebook($this->eStructure);

		$this->assertEquals(1, $counts[$eNotebook['id']], "le membre ne compte dans le carnet que la note de l'équipe");

		$search = new \Search(['notebook' => $eNotebook]);

		$this->assertEquals($counts[$eNotebook['id']], NoteLib::getByStructure($this->eStructure, $search, 0)->count(), "l'onglet du carnet rend ce que son compteur annonce");

		$search = new \Search(['notebook' => new Notebook()]);

		$this->assertFalse(array_key_exists(0, $counts), "la note des administrateurs hors des carnets ne lui compte pas");

		$this->connectAs(self::USER_ADMIN, manager: TRUE);

		$this->assertEquals(1, NoteLib::getByStructure($this->eStructure, $search, 0)->count(), "l'onglet « Non classé » la rend à l'administrateur");

		$this->connectAs(self::USER_MEMBER, manager: FALSE);

		$this->assertEquals(0, NoteLib::getByStructure($this->eStructure, $search, 0)->count(), "et l'onglet « Non classé » ne la lui rend pas");

	}

	/** L'administrateur d'une autre structure n'écrit aucun carnet de celle-ci. */
	public function testAnAdministratorOfAnotherStructureReachesNoNotebook(): void {

		$eNotebook = $this->createNotebook('Cultures');
		$this->createNotebook('Matériel');

		$this->connectAs(self::USER_MEMBER, manager: TRUE, structureId: self::STRUCTURE_OTHER);

		$action = $this->runPage('note/notebook:doCreate', ['structure' => self::STRUCTURE, 'name' => 'Intrusion']);
		$this->assertTrue($action instanceof \NotAllowedAction, "il ne crée pas de carnet ici : ".get_class($action));

		$posts = [
			'note/notebook:doUpdate' => ['name' => 'Intrusion'],
			'note/notebook:doIncrementPosition' => ['increment' => 1],
			'note/notebook:doDelete' => []
		];

		foreach($posts as $page => $post) {

			$action = $this->runPage($page, ['id' => $eNotebook['id']] + $post);
			$this->assertTrue($action instanceof \NotAllowedAction, $page." lui est refusé : ".get_class($action));

		}

		$this->connectAs(self::USER_ADMIN, manager: TRUE);

		$eNotebookAfter = NotebookLib::getById($eNotebook['id']);

		$this->assertTrue($eNotebookAfter['name'] === 'Cultures' and $eNotebookAfter['position'] === 1, "le carnet est intact");

	}

	/** « Non classé » n'existe qu'avec un carnet, et un carnet inconnu ramène à toutes les notes. */
	public function testUnclassifiedExistsOnlyWithANotebook(): void {

		$this->createNote(Note::TEAM, new Notebook());

		$data = $this->runList(['notebook' => 0]);

		$this->assertFalse($data->search->has('notebook'), "sans carnet, notebook=0 ne filtre rien");

		$eNotebook = $this->createNotebook('Cultures');

		$data = $this->runList(['notebook' => 0]);

		$this->assertTrue($data->search->has('notebook') and $data->search->get('notebook')->empty(), "avec un carnet, notebook=0 choisit « Non classé »");
		$this->assertEquals(1, $data->cNote->count(), "qui rend la note hors des carnets");

		$data = $this->runList(['notebook' => $eNotebook['id']]);

		$this->assertTrue($data->search->get('notebook')->is($eNotebook), "un carnet de la structure choisit son onglet");
		$this->assertTrue($data->eNoteCreate['notebook']->is($eNotebook), "et y range « Nouvelle note »");

		$data = $this->runList(['notebook' => 999999999]);

		$this->assertFalse($data->search->has('notebook'), "un carnet inconnu ramène à toutes les notes");

	}

	/**
	 * Un onglet sans note et sans filtre perd sa rangée d'outils : « Nouvelle note » passe dans son bloc
	 * vide. Un filtre qui ne trouve rien, ou une page au-delà de la dernière, la gardent.
	 */
	public function testAnEmptyTabWithoutFilterIsEmpty(): void {

		$data = $this->runList([]);

		$this->assertTrue($data->isTabEmpty, "« Toutes » sans aucune note est vide");

		$eNotebookCultures = $this->createNotebook('Cultures');
		$eNotebookMaterial = $this->createNotebook('Matériel');

		$eNote = $this->createNote(Note::ADMIN, $eNotebookCultures);

		$this->assertFalse($this->runList([])->isTabEmpty, "« Toutes » ne l'est plus");
		$this->assertFalse($this->runList(['notebook' => $eNotebookCultures['id']])->isTabEmpty, "ni le carnet de la note");
		$this->assertTrue($this->runList(['notebook' => $eNotebookMaterial['id']])->isTabEmpty, "un carnet sans note l'est");
		$this->assertTrue($this->runList(['notebook' => 0])->isTabEmpty, "« Non classé » sans note aussi");
		$this->assertFalse($this->runList(['notebook' => $eNotebookCultures['id'], 'page' => 3])->isTabEmpty, "une page au-delà de la dernière garde ses outils");

		$ePlant = \plant\Plant::model()
			->select('id')
			->whereStructure(self::STRUCTURE)
			->whereStatus(\plant\Plant::ACTIVE)
			->get();

		if($this->assertDecorPresent($ePlant->notEmpty(), 'une espèce active dans F1, pour filtrer la liste') === FALSE) {
			return;
		}

		$data = $this->runList(['notebook' => $eNotebookMaterial['id'], 'target' => 'plant-plant:'.$ePlant['id']]);

		$this->assertTrue($data->search->has('target') and $data->listed === 0, "le filtre est posé et ne trouve rien");
		$this->assertFalse($data->isTabEmpty, "il garde la recherche, d'où on le retire");

		$this->connectAs(self::USER_MEMBER, manager: FALSE);

		$data = $this->runList(['notebook' => $eNotebookCultures['id']]);

		$this->assertTrue($data->isTabEmpty and $data->eNoteCreate->assertCreate() === FALSE, "le carnet d'une note réservée aux administrateurs est vide pour le membre, qui n'y écrit pas");

		$this->connectAs(self::USER_ADMIN, manager: TRUE);

		$action = $this->runPage('note/note:doDelete', ['id' => $eNote['id'], 'origin' => 'list', 'tab' => $eNotebookCultures['id']]);
		$data = $this->getData($action);

		$this->assertTrue($data->listChange === 'all' and $data->isTabEmpty, "la dernière note supprimée depuis son carnet, la tête se redessine vide");

		$data = $this->runList(['target' => 'plant-plant:'.$ePlant['id']]);

		$this->assertTrue($data->search->has('target') and $data->isTabEmpty, "sans plus aucune note, un filtre resté dans l'adresse n'a rien à chercher");

	}

	/** Une structure sans note ni carnet offre « Nouveau carnet » à l'administrateur, pas au membre. */
	public function testAStructureWithoutNoteOffersTheFirstNotebook(): void {

		$data = $this->runList([]);

		$this->assertTrue($data->cNotebook->empty() and $data->counts === [], "la structure n'a ni note ni carnet");
		$this->assertTrue($data->eNotebookCreate->assertCreate(), "l'administrateur peut créer le premier carnet");

		$eNotebook = $this->createNotebook('Cultures');

		$this->assertTrue($eNotebook->notEmpty(), "et le crée sans avoir écrit de note");

		NotebookLib::delete($eNotebook);

		$this->connectAs(self::USER_MEMBER, manager: FALSE);

		$data = $this->runList([]);

		$this->assertFalse($data->eNotebookCreate->assertCreate(), "le membre ne le peut pas");

	}

	/**
	 * Une note écrite depuis la liste y ramène : sous « Toutes » si on y écrivait, sinon sous l'onglet de
	 * son carnet, que le formulaire a pu changer. Le filtre et la page ne suivent pas.
	 */
	public function testANoteWrittenFromTheListOpensWhereItShows(): void {

		$eNotebookCultures = $this->createNotebook('Cultures');
		$eNotebookMaterial = $this->createNotebook('Matériel');

		$post = ['structure' => self::STRUCTURE, 'title' => 'Pucerons', 'content' => '', 'readers' => Note::TEAM, 'origin' => 'list'];

		$data = $this->getData($this->runPage('note/note:doCreate', $post + ['notebook' => $eNotebookCultures['id'], 'target' => 'plant-plant:1', 'page' => 2]));

		$this->assertEquals(['tab' => NULL, 'target' => NULL, 'page' => 0], $data->listCreated, "écrite sous « Toutes », elle y revient");

		$data = $this->getData($this->runPage('note/note:doCreate', $post + ['notebook' => $eNotebookMaterial['id'], 'tab' => $eNotebookCultures['id']]));

		$this->assertEquals(['tab' => $eNotebookMaterial['id'], 'target' => NULL, 'page' => 0], $data->listCreated, "rangée dans un autre carnet, elle ouvre le sien");

		$data = $this->getData($this->runPage('note/note:doCreate', $post + ['tab' => $eNotebookCultures['id']]));

		$this->assertEquals(['tab' => 0, 'target' => NULL, 'page' => 0], $data->listCreated, "hors des carnets, elle ouvre « Non classé »");

		$action = $this->runPage('note/note:doCreate', ['origin' => 'elsewhere'] + $post);

		$this->assertTrue($action instanceof \RedirectAction, "sans la liste, elle ouvre sa page : ".get_class($action));

	}

	/** Ce que la page pose pour la vue, quand elle en rend une. */
	private function getData(\Throwable $action): \stdClass {

		if(($action instanceof \ViewAction) === FALSE) {
			throw new \Exception('Pas de vue : '.get_class($action));
		}

		return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

	}

	private function createNotebook(string $name): Notebook {

		$action = $this->runPage('note/notebook:doCreate', ['structure' => self::STRUCTURE, 'name' => $name]);

		if(($action instanceof \RedirectAction) === FALSE) {
			throw new \Exception('Carnet '.$name.' non créé : '.get_class($action));
		}

		return Notebook::model()
			->select(Notebook::getSelection())
			->whereStructure($this->eStructure)
			->whereName($name)
			->get();

	}

	/** Par le Lib, sans `build()` : le carnet posé est celui que le décor vient de créer dans F1. */
	private function createNote(string $readers, Notebook $eNotebook): Note {

		$eNote = new Note([
			'structure' => $this->eStructure,
			'notebook' => $eNotebook,
			'title' => 'Note de test',
			'content' => NULL,
			'readers' => $readers,
			'cTarget' => new \Collection()
		]);

		NoteLib::create($eNote);

		return $this->getNote($eNote);

	}

	private function getNote(Note $eNote): Note {

		return Note::model()
			->select(Note::getSelection())
			->whereId($eNote['id'])
			->get();

	}

	/**
	 * `/ferme/{id}/notes` comme HttpRoute l'exécuterait, et ce que la page pose pour la vue. Forme reprise
	 * de `amap/test/Producer.t.php` et de `quotation/test/ResponseRouteAi.t.php`.
	 */
	private function runList(array $get): \stdClass {

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'GET');

		$_GET = $get;
		$_REQUEST = $_GET;

		try {
			\Page::execute(\Route::getCompatiblePages('ferme/'.self::STRUCTURE.'/notes'), new \stdClass());
		} catch(\ViewAction $action) {
			return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);
		} finally {
			$_GET = [];
			$_REQUEST = [];
			$method->setValue(NULL, $methodBefore);
		}

		throw new \LogicException('la liste des notes n\'a pas rendu son écran');

	}

	/** Exécute la vraie page et rend ce qu'elle lève. Forme reprise de `dashboard/test/Preference.t.php`. */
	private function runPage(string $page, array $post): \Throwable {

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'POST');

		$getPages = new \ReflectionMethod(\Page::class, 'getPages');

		$_POST = $post;
		$_REQUEST = $_POST;

		try {
			\Page::execute($getPages->invoke(NULL, $page), new \stdClass());
		} catch(\Throwable $e) {
			return $e;
		} finally {
			$_POST = [];
			$_REQUEST = [];
			$method->setValue(NULL, $methodBefore);
		}

		return new \LogicException('l\'action '.$page.' n\'a rien levé');

	}

	/**
	 * L'utilisateur connecté et son appartenance à F1, injectée dans le cache de `MemberLib` comme dans
	 * `dashboard/test/Preference.t.php` : un utilisateur fictif n'est membre de rien.
	 */
	private function connectAs(int $userId, bool $manager = FALSE, bool $member = TRUE, int $structureId = self::STRUCTURE): void {

		$eUser = new \user\User(['id' => $userId]);

		\user\ConnectionLib::setOnline($eUser);

		$property = self::getMemberCache();
		$cMember = $property->getValue();

		if($member === FALSE) {
			$cMember[$eUser['id']] = new \Collection();
			$property->setValue(NULL, $cMember);
			return;
		}

		$views = [];

		foreach(\structure\Member::model()->getProperties() as $memberProperty) {
			if(str_starts_with($memberProperty, 'view')) {
				$views[$memberProperty] = \structure\Member::model()->getDefaultValue($memberProperty);
			}
		}

		$eMember = new \structure\Member([
			'user' => $eUser,
			'structure' => $structureId,
			'viewSellingSales' => \structure\Member::SALE,
			'role' => new \structure\Role([
				'admin' => $manager,
				'accounting' => $manager ? \structure\Role::FULL : \structure\Role::DENIED,
				'accountingAnalyze' => $manager,
				'selling' => $manager,
				'planning' => $manager ? \structure\Role::FULL : \structure\Role::DENIED,
				'productionAnalyze' => $manager,
				'productionObserve' => $manager,
				'commercialisationAnalyze' => $manager,
			]),
		] + $views);

		$cMember[$eUser['id']] = new \Collection([$structureId => $eMember]);
		$property->setValue(NULL, $cMember);

	}

	private static function getMemberCache(): \ReflectionProperty {
		return new \ReflectionProperty(\structure\MemberLib::class, 'cMemberOnline');
	}

}
?>
