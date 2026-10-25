<?php
namespace note;

/**
 * Les notes de la structure, lot 1 (L47) : qui lit et qui écrit, ce que `build()` refuse, l'épingle
 * qui range sans dater, et la liste qui rejoint `Note::canRead()`.
 *
 * Les gestes d'écriture passent par la vraie page (`note/note`) et non par le Lib seul : c'est elle qui
 * fait poser par `ModulePage` les deux questions de droit et le `build()`.
 *
 * Le décor F1 plutôt qu'une structure réelle, et des utilisateurs fictifs très hors des identifiants
 * réels, membres par le cache de `MemberLib` comme dans `dashboard/test/Preference.t.php`, qui dit
 * pourquoi. Tout se joue dans la transaction de `TransactionTest`, annulée après chaque méthode.
 *
 * Lancement : dev/test package=note file=Note
 */
class NoteTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	const USER_ADMIN = 4000000101;
	const USER_MEMBER = 4000000102;
	const USER_OUTSIDE = 4000000103;

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

		// Les pages se gardent par `isLanding()`, qui suit ces deux drapeaux : fermés dans
		// l'environnement, ils feraient refuser chaque geste de ce fichier.
		\dashboard\DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;
		\dashboard\DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;

	}

	public function setUp(): void {

		parent::setUp();

		$this->connectAs(self::USER_ADMIN, manager: TRUE);

		// Le décor F1 garde en dev les notes de la contre-recette : chaque méthode part d'une structure
		// sans note, dans la transaction qui les rendra à la fin.
		foreach(Note::model()->select('id')->whereStructure($this->eStructure)->getCollection() as $eNote) {
			NoteLib::delete($eNote);
		}

	}

	public function finalize(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);

		\dashboard\DashboardSetting::$enabled = $this->enabledInitial;
		\dashboard\DashboardSetting::$landing = $this->landingInitial;

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function testAnAdministratorWritesANote(): void {

		$action = $this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Chambre froide', 'content' => '<p>Porte fermée avant 8 h</p>', 'readers' => Note::TEAM]);

		$this->assertTrue($action instanceof \RedirectAction, "l'administrateur écrit une note : ".get_class($action));

		$eNote = $this->getLast();

		$this->assertEquals('Chambre froide', $eNote['title'], "le titre est écrit");
		$this->assertEquals(Note::TEAM, $eNote['readers'], "les lecteurs aussi");

		$action = $this->runPage('note/note:doUpdate', ['id' => $eNote['id'], 'title' => 'Chambre froide nord', 'content' => '', 'readers' => Note::ADMIN]);

		$this->assertTrue($action instanceof \ReloadAction, "il la modifie : ".get_class($action));
		$this->assertEquals('Chambre froide nord', $this->getLast()['title'], "le titre modifié est écrit");

		$action = $this->runPage('note/note:doDelete', ['id' => $eNote['id']]);

		$this->assertTrue($action instanceof \RedirectAction, "il la supprime : ".get_class($action));
		$this->assertTrue($this->getLast()->empty(), "la note n'est plus là");

	}

	/**
	 * Ouverte depuis la liste, la note y rend ses gestes : enregistrée, elle redessine son panneau sur la
	 * liste rechargée ; supprimée, elle laisse la liste telle qu'on l'avait.
	 */
	public function testANoteOpenedFromTheListReturnsToIt(): void {

		$this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Chambre froide', 'content' => '', 'readers' => Note::TEAM]);

		$eNote = $this->getLast();

		$action = $this->runPage('note/note:doUpdate', ['id' => $eNote['id'], 'title' => 'Chambre froide nord', 'content' => '', 'readers' => Note::TEAM, 'origin' => 'list', 'target' => 'pas-une:valeur']);

		$this->assertTrue($action instanceof \ViewAction, "enregistrée, elle redessine son panneau : ".get_class($action));

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals('Chambre froide nord', $data->e['title'], "relue après l'enregistrement");
		$this->assertEquals(['tab' => NULL, 'target' => NULL, 'page' => 0], $data->list, "un filtre illisible ne passe pas dans l'adresse de la liste");
		$this->assertEquals(['card', $eNote['id']], [$data->listChange, $data->eNoteCard['id']], "restée dans l'onglet, seule sa carte se redessine");

		$action = $this->runPage('note/note:doDelete', ['id' => $eNote['id'], 'origin' => 'list']);

		$this->assertTrue($action instanceof \ViewAction, "supprimée, elle referme son panneau : ".get_class($action));

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertTrue(in_array($eNote['id'], $data->cNote->getIds(), TRUE) === FALSE, "la liste relue ne la porte plus");
		$this->assertTrue($this->getLast()->empty(), "la note n'est plus là");

	}

	/**
	 * Ce que la page décide de redessiner de la liste restée sous le panneau. Le décor F1 tient sur une
	 * page : une note qui part y laisse sa place vide, sans qu'aucune remonte de la page suivante.
	 */
	public function testTheListChangeIsDecidedByWhereTheNoteLands(): void {

		$this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Bâche tissée', 'content' => '', 'readers' => Note::TEAM]);
		$eNoteKept = $this->getLast();

		$this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Bâche percée', 'content' => '', 'readers' => Note::TEAM]);
		$eNoteDeleted = $this->getLast();

		$action = $this->runPage('note/note:doDelete', ['id' => $eNoteDeleted['id'], 'origin' => 'list']);
		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals(['remove', 'close'], [$data->listChange, $data->panelChange], "supprimée d'un onglet qui tient sur une page, sa carte part seule");

		$action = $this->runPage('note/note:doUpdate', ['id' => $eNoteKept['id'], 'title' => 'Bâche tissée verte', 'content' => '', 'readers' => Note::TEAM, 'origin' => 'list', 'page' => 1]);
		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals(['all', 'replace'], [$data->listChange, $data->panelChange], "modifiée depuis une page qui ne la montre pas, la liste se redessine entière");

	}

	public function testBuildRefusesAnEmptyTitleAndUnknownReaders(): void {

		$fw = new \FailWatch();
		$action = $this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => '', 'content' => '', 'readers' => Note::TEAM]);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Note::title.check'), "un titre vide est refusé : ".get_class($action).' '.$fw);

		$fw = new \FailWatch();
		$action = $this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Pucerons', 'content' => '', 'readers' => 'everybody']);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Note::readers.check'), "une valeur hors de l'énumération est refusée : ".get_class($action).' '.$fw);

		$this->assertTrue($this->getLast()->empty(), "aucune note n'est écrite");

	}

	/**
	 * Une photo téléversée dans l'éditeur, telle que `editor.js` la poste : un `xyz` et sa version, sans
	 * lesquels `XmlLib` ne la reconnaît pas comme un média de l'éditeur.
	 */
	private function getPhoto(string $hash, string $caption = ''): string {

		$image = '<div class="editor-media" data-type="image" data-w="1000" data-h="750" data-url="https://media.example.org/editor/'.$hash.'.jpg" data-color="#ffffff" data-xyz="'.$hash.'" data-xyz-w="1000" data-xyz-h="750" data-xyz-version="3"></div>';

		return '<figure data-interactive="true">'.$image.($caption === '' ? '' : '<figcaption>'.$caption.'</figcaption>').'</figure>';

	}

	public function testAPhotoIsKeptWithItsCaption(): void {

		$content = '<p>Avant</p>'.$this->getPhoto('zztestnotephoto001j', 'Côté haie').'<p>Après</p>';

		$this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Avec photo', 'content' => $content, 'readers' => Note::TEAM]);

		$eNote = $this->getLast();

		$this->assertTrue($eNote->notEmpty(), "la note est écrite");
		$this->assertTrue(str_contains($eNote['content'], 'xyz="zztestnotephoto001j"'), "la photo est gardée : ".$eNote['content']);
		$this->assertTrue(str_contains($eNote['content'], '<figcaption>Côté haie</figcaption>'), "sa légende aussi : ".$eNote['content']);
		$this->assertTrue(str_contains($eNote['content'], 'Après'), "avec le texte qui l'entoure");

	}

	/** Une note ne prend que des photos : une vidéo, une citation ou une grille postées sont retirées. */
	public function testAFigureThatIsNotAPhotoIsRemoved(): void {

		$video = '<figure data-interactive="true"><div class="editor-media" data-type="video" data-w="100" data-h="80" data-url="https://www.youtube.com/watch?v=dQw4w9WgXcQ" data-source="youtube"></div></figure>';
		$quote = '<figure data-interactive="false"><div class="editor-media" data-type="quote" data-about="quote"><div class="editor-quote"><blockquote><p>Citée</p></blockquote></div></div></figure>';

		$this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Sans photo', 'content' => '<p>Avant</p>'.$video.$quote.'<p>Après</p>', 'readers' => Note::TEAM]);

		$eNote = $this->getLast();

		$this->assertTrue($eNote->notEmpty(), "la note est écrite");
		$this->assertFalse(str_contains($eNote['content'], 'figure'), "sans la vidéo ni la citation : ".$eNote['content']);
		$this->assertEquals('<main><p>Avant</p><p>Après</p></main>', $eNote['content'], "le texte qui les entoure est gardé");

	}

	/**
	 * La carte prend la première photo en couverture, et son texte ne reprend pas la légende, qui se lit
	 * avec la photo dans la note.
	 */
	public function testTheCardCoversWithTheFirstPhotoWithoutItsCaption(): void {

		$content = '<p>Pucerons sous le voile</p>'.$this->getPhoto('zztestnotephoto001j', 'Côté haie').'<p>Repasser lundi</p>'.$this->getPhoto('zztestnotephoto002j');

		$this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Avec photos', 'content' => $content, 'readers' => Note::TEAM]);
		$this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Sans photo', 'content' => '<p>Rien à montrer</p>', 'readers' => Note::TEAM]);

		$cNote = NoteLib::getByStructure($this->eStructure, new \Search(), 0);

		$this->assertEquals(2, $cNote->count(), "les deux notes sont listées");

		$eNoteWithout = $cNote->first();
		$eNoteWith = $cNote->last();

		$this->assertEquals('Sans photo', $eNoteWithout['title'], "la plus récente d'abord");
		$this->assertEquals(NULL, $eNoteWithout['cover'], "une note sans photo n'a pas de couverture");

		$this->assertEquals(['xyz' => 'zztestnotephoto001j', 'xyzVersion' => 3], $eNoteWith['cover'], "la première photo couvre la carte");
		$this->assertEquals('Pucerons sous le voile Repasser lundi', $eNoteWith['extract'], "le texte de la carte sans la légende");

	}

	public function testAMemberReadsTeamNotesOnlyAndWritesNothing(): void {

		$eNoteAdmin = $this->create(Note::ADMIN);
		$eNoteTeam = $this->create(Note::TEAM);

		$this->connectAs(self::USER_MEMBER, manager: FALSE);

		$this->assertTrue($eNoteTeam->canRead(), "un membre lit une note de toute l'équipe");
		$this->assertFalse($eNoteAdmin->canRead(), "il ne lit pas une note des administrateurs");
		$this->assertFalse($eNoteTeam->canWrite(), "il n'écrit pas une note qu'il lit");
		$this->assertFalse($eNoteTeam->assertCreate(), "il n'a pas « Nouvelle note »");

		$action = $this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Pucerons', 'content' => '', 'readers' => Note::TEAM]);

		$this->assertTrue($action instanceof \NotAllowedAction, "la création lui est refusée : ".get_class($action));

		foreach(['note/note:doUpdatePinned' => ['pinned' => 1], 'note/note:doDelete' => []] as $page => $post) {

			$action = $this->runPage($page, ['id' => $eNoteTeam['id']] + $post);
			$this->assertTrue($action instanceof \NotAllowedAction, $page." lui est refusé : ".get_class($action));

		}

		$this->connectAs(self::USER_ADMIN, manager: TRUE);

		$eNote = $this->getLast();

		$this->assertTrue($eNote->notEmpty() and $eNote['pinned'] === FALSE, "la note est intacte");

	}

	/**
	 * Ni un non-membre ni l'école ou le groupe qui observent la production, que `Structure::canRead()`
	 * ouvre : aucun d'eux n'est membre de la structure, donc aucun ne lit une note, même de toute
	 * l'équipe.
	 */
	public function testANonMemberReadsNothing(): void {

		$eNoteTeam = $this->create(Note::TEAM);

		$this->connectAs(self::USER_OUTSIDE, member: FALSE);

		$this->assertFalse($eNoteTeam->canRead(), "un non-membre ne lit pas une note de toute l'équipe");
		$this->assertFalse($eNoteTeam->canWrite(), "ni ne l'écrit");

		$action = $this->runPage('note/note:doUpdatePinned', ['id' => $eNoteTeam['id'], 'pinned' => 1]);
		$this->assertTrue($action instanceof \NotAllowedAction, "ni ne l'épingle : ".get_class($action));

		$pilotage = new \dashboard\PilotageUi()->get($this->eStructure, NULL, NULL);
		$this->assertFalse(str_contains($pilotage, \note\NoteUi::urlStructure($this->eStructure)), "le groupe Bureau ne lui mène pas aux notes");

		$this->connectAs(self::USER_MEMBER, manager: FALSE);

		$pilotage = new \dashboard\PilotageUi()->get($this->eStructure, NULL, NULL);
		$this->assertTrue(str_contains($pilotage, \note\NoteUi::urlStructure($this->eStructure)), "il y mène un membre");

	}

	/** L'administrateur d'une autre structure n'a aucun droit sur les notes de celle-ci. */
	public function testAnAdministratorOfAnotherStructureReachesNothing(): void {

		$eNoteTeam = $this->create(Note::TEAM);

		$this->connectAs(self::USER_OUTSIDE, manager: TRUE, structureId: \structure\FixtureAiLib::F2);

		$this->assertFalse($eNoteTeam->canRead(), "il ne lit pas une note de toute l'équipe d'ici");

		$action = $this->runPage('note/note:doCreate', ['structure' => self::STRUCTURE, 'title' => 'Intrusion', 'content' => '', 'readers' => Note::TEAM]);
		$this->assertTrue($action instanceof \NotAllowedAction, "il ne crée pas de note dans cette structure : ".get_class($action));

		$posts = [
			'note/note:doUpdate' => ['title' => 'Intrusion', 'content' => '', 'readers' => Note::TEAM],
			'note/note:doUpdatePinned' => ['pinned' => 1],
			'note/note:doDelete' => []
		];

		foreach($posts as $page => $post) {

			$action = $this->runPage($page, ['id' => $eNoteTeam['id']] + $post);
			$this->assertTrue($action instanceof \NotAllowedAction, $page." lui est refusé : ".get_class($action));

		}

		$this->connectAs(self::USER_ADMIN, manager: TRUE);

		$eNote = $this->getLast();

		$this->assertTrue($eNote['id'] === $eNoteTeam['id'] and $eNote['title'] === $eNoteTeam['title'] and $eNote['pinned'] === FALSE, "la note est intacte");

	}

	public function testASchoolDoesNotCreateANote(): void {

		$eStructureSchool = new \structure\Structure([
			'id' => $this->eStructure['id'],
			'producerType' => \structure\Structure::SCHOOL
		]);

		$eNote = new Note(['structure' => $eStructureSchool]);

		$this->assertTrue($eNote->canCreate(), "l'administrateur d'une ferme école a le droit");
		$this->assertFalse($eNote->acceptCreate(), "mais la ferme école n'accepte pas de note");
		$this->assertFalse($eNote->assertCreate(), "donc pas de « Nouvelle note »");

	}

	/** Le tri de la liste repose sur `updatedAt` : l'épingle range sans faire passer la note pour modifiée. */
	public function testPinningDoesNotAdvanceUpdatedAt(): void {

		$eNote = $this->create(Note::TEAM);

		// Une date passée, que `build()` n'a pas à vérifier : ce test pose le décor dont il compare la
		// date, et aucun formulaire n'écrit `updatedAt`.
		Note::model()->update($eNote, ['updatedAt' => '2020-01-01 00:00:00']);

		$action = $this->runPage('note/note:doUpdatePinned', ['id' => $eNote['id'], 'pinned' => 1]);

		$this->assertTrue($action instanceof \ViewAction, "l'administrateur épingle la note : ".get_class($action));

		$eNote = $this->getLast();

		$this->assertTrue($eNote['pinned'], "la note est épinglée");
		$this->assertEquals('2020-01-01 00:00:00', $eNote['updatedAt'], "sa date de modification ne bouge pas");

		$this->runPage('note/note:doUpdate', ['id' => $eNote['id'], 'title' => 'Modifiée', 'content' => '', 'readers' => Note::TEAM]);

		$this->assertTrue($this->getLast()['updatedAt'] !== '2020-01-01 00:00:00', "une modification du formulaire la fait avancer");

	}

	/** La liste pose en requête la condition de `canRead()` : les deux rendent les mêmes notes. */
	public function testTheListAgreesWithCanRead(): void {

		$cNote = new \Collection();

		foreach([Note::ADMIN, Note::ADMIN, Note::TEAM] as $readers) {
			$eNote = $this->create($readers);
			$cNote[$eNote['id']] = $eNote;
		}

		foreach([[self::USER_ADMIN, TRUE], [self::USER_MEMBER, FALSE]] as [$userId, $manager]) {

			$this->connectAs($userId, manager: $manager);

			$readable = $cNote->find(fn(Note $eNote) => $eNote->canRead())->getIds();
			$listed = NoteLib::getByStructure($this->eStructure, new \Search(), 0)->getIds();

			sort($readable);
			sort($listed);

			$this->assertEquals($readable, $listed, "la liste rend ce que canRead() ouvre, utilisateur ".$userId);
			$this->assertEquals(count($listed), array_sum(NoteLib::countByNotebook($this->eStructure)), "et le compte de la pagination la suit");

		}

		$this->assertEquals(1, count($listed), "le membre ne lit que la note de l'équipe");

	}

	public function testTheDemoDoesNotCopyNotes(): void {

		$this->assertTrue(in_array('note', \structure\DemoLib::COPY_PACKAGE_EXCLUDE), "la démonstration n'emporte pas les notes de la structure qu'elle copie");

	}

	/**
	 * L56 : la note se modifie dans son panneau. Chaque champ part par `doUpdate`, le même passage que le
	 * formulaire qu'elle remplace (`build()` puis `NoteLib::update()`), et la réponse redessine le panneau
	 * et la seule carte de la note, sans rouvrir de couche ni toucher au reste de la liste.
	 */
	public function testAWriterChangesEveryFieldFromThePanel(): void {

		$eNotebook = new Notebook([
			'structure' => $this->eStructure,
			'name' => 'Carnet L56'
		]);

		// Par le Lib, sans `build()` : le décor ne pose qu'un nom neuf dans la structure du test, que
		// `build()` aurait accepté, et ce test juge l'enregistrement de la note, pas celui du carnet.
		NotebookLib::create($eNotebook);

		$eNote = $this->create(Note::ADMIN);
		$content = '<p>Pucerons sous le voile</p>'.$this->getPhoto('zztestnotephoto0056j');

		$action = $this->runPage('note/note:doUpdate', [
			'id' => $eNote['id'],
			'title' => 'Pucerons sur les laitues',
			'notebook' => $eNotebook['id'],
			'content' => $content,
			'readers' => Note::TEAM,
			'origin' => 'list',
			'tab' => $eNotebook['id']
		]);

		$this->assertTrue($action instanceof \ViewAction, "le panneau enregistre la note : ".get_class($action));

		$eNoteSaved = $this->getLast();

		$this->assertEquals(
			['Pucerons sur les laitues', $eNotebook['id'], Note::TEAM],
			[$eNoteSaved['title'], $eNoteSaved['notebook']['id'], $eNoteSaved['readers']],
			"le titre, le carnet et les lecteurs sont écrits"
		);
		$this->assertTrue(str_contains($eNoteSaved['content'], 'zztestnotephoto0056j'), "le texte garde sa photo");

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals(['replace', 'card'], [$data->panelChange, $data->listChange], "le panneau se redessine et la carte seule suit");
		$this->assertTrue($data->e['cNotebook']->offsetExists($eNotebook['id']) and $data->e['notebook']['name'] === 'Carnet L56', "le panneau redessiné propose les carnets et nomme le sien");
		$this->assertTrue($data->e['cMemberAdmin'] instanceof \Collection, "et les administrateurs que le choix des lecteurs montre");

		$instructions = $this->getInstructions('doUpdate', $action);

		$this->assertTrue(in_array('#panel-note replacePanel', $instructions, TRUE), "la réponse remplace le panneau : ".implode(', ', $instructions));
		$this->assertTrue(in_array('title innerHtml', $instructions, TRUE), "et donne son titre à l'onglet du navigateur");
		$this->assertTrue(in_array('#'.NoteUi::getCardId($eNote).' remove', $instructions, TRUE), "et redessine la carte de la note");
		$this->assertTrue(array_intersect(['#note-list outerHtml', '#note-head outerHtml'], $instructions) === [], "sans redessiner la liste ni ses onglets");
		$this->assertTrue(in_array('js eval', $instructions, TRUE) === FALSE, "et sans refermer de couche : il n'y en a plus au-dessus du panneau");

	}

	/**
	 * Le panneau d'un administrateur est un formulaire, dont le champ du titre, rendu dans la fiche,
	 * fait partie : le panneau n'a plus de titre à lui. Celui d'un membre reste la lecture, sans
	 * formulaire ni champ.
	 */
	public function testOnlyAWriterReceivesTheForm(): void {

		$eNote = $this->create(Note::TEAM);
		$eNote['notebook'] = new Notebook();
		$eNote['cNotebook'] = new \Collection();
		$eNote['cMemberAdmin'] = new \Collection();
		\note\TargetLib::putByNotes($this->eStructure, new \Collection([$eNote]));

		$list = ['tab' => NULL, 'target' => NULL, 'page' => 0];

		// La tête de la fiche, titre et gestes, est dans la bande du panneau, hors du formulaire.
		$panel = new NoteUi()->getPanel($this->eStructure, $eNote, $list);
		$document = $this->getDocument($panel->header.$panel->body);

		$form = $document->querySelector('form[data-ajax-form="/note/note:doUpdate"]');

		$this->assertTrue($form !== NULL, "l'administrateur reçoit le formulaire de la note");
		$this->assertTrue($document->querySelector('input[name="title"][form="'.$form?->getAttribute('id').'"]') !== NULL, "le titre de la fiche est posté avec lui");
		$this->assertEquals([NULL, 'Note de test'], [$panel->title, $panel->documentTitle], "le panneau n'a pas de titre, l'onglet du navigateur garde celui de la note");

		$this->connectAs(self::USER_MEMBER, manager: FALSE);

		$panel = new NoteUi()->getPanel($this->eStructure, $eNote, $list);
		$document = $this->getDocument($panel->header.$panel->body);

		$this->assertTrue($document->querySelector('form') === NULL and $document->querySelector('input') === NULL, "le membre reçoit la lecture seule");

	}

	/**
	 * Le carnet se change par la marque de la tête et par la roue : leurs entrées écrivent le champ
	 * caché du formulaire de la note, qu'elles nomment, puisque la liste ouverte vit hors du formulaire
	 * (`Lime.Dropdown`).
	 */
	public function testTheNotebookChoicesWriteTheFormOfTheNote(): void {

		$eNotebook = new Notebook([
			'structure' => $this->eStructure,
			'name' => 'Carnet <b>L64</b>'
		]);

		// Par le Lib, sans `build()` : le décor ne pose qu'un nom neuf dans la structure du test, que
		// `build()` aurait accepté, et ce test juge la roue de la note, pas l'écriture du carnet.
		NotebookLib::create($eNotebook);

		$eNote = $this->create(Note::ADMIN);
		$eNote['notebook'] = new Notebook();
		$eNote['cNotebook'] = new \Collection([$eNotebook['id'] => $eNotebook]);
		$eNote['cMemberAdmin'] = new \Collection();
		\note\TargetLib::putByNotes($this->eStructure, new \Collection([$eNote]));

		// La marque est dans la bande du panneau, la roue aussi : la tête de la fiche y est montée.
		$panel = new NoteUi()->getPanel($this->eStructure, $eNote, ['tab' => NULL, 'target' => NULL, 'page' => 0]);
		$document = $this->getDocument($panel->header.$panel->body);

		$formId = $document->querySelector('form[data-ajax-form="/note/note:doUpdate"]')?->getAttribute('id');
		$values = array_map(fn($item) => $item->getAttribute('data-value'), iterator_to_array($document->querySelectorAll('.dropdown-item[data-note-choice="notebook"][data-form="'.$formId.'"]')));

		$this->assertEquals(['', (string)$eNotebook['id'], '', (string)$eNotebook['id']], $values, "la marque puis la roue proposent « Non classé » et chaque carnet, pour le formulaire de la note");
		$this->assertTrue($document->querySelector('#'.$formId.' input[type="hidden"][name="notebook"]') !== NULL, "le formulaire garde le champ qu'elles écrivent");
		$this->assertTrue($document->querySelector('b') === NULL, "le nom du carnet est échappé dans la marque et la roue");

		$this->runPage('note/note:doUpdate', ['id' => $eNote['id'], 'title' => 'Note de test', 'notebook' => $eNotebook['id'], 'content' => '', 'readers' => Note::ADMIN]);

		$this->assertEquals($eNotebook['id'], $this->getLast()['notebook']['id'], "le carnet choisi s'enregistre avec la note");

	}

	/**
	 * L'épingle ne remplace que son entrée de la roue : le carnet choisi sans être enregistré reste tel
	 * qu'on l'a laissé.
	 */
	public function testPinningReplacesOnlyItsEntry(): void {

		$eNote = $this->create(Note::TEAM);

		$action = $this->runPage('note/note:doUpdatePinned', ['id' => $eNote['id'], 'pinned' => 1]);

		$this->assertTrue($action instanceof \ViewAction, "l'administrateur épingle la note : ".get_class($action));

		$instructions = $this->getInstructions('doUpdatePinned', $action);

		$this->assertEquals(['#'.NoteUi::getPinId($eNote).' outerHtml'], array_values(array_filter($instructions, fn($instruction) => str_starts_with($instruction, '#'))), "seule l'entrée de l'épingle est redessinée : ".implode(', ', $instructions));

	}

	public function testTheUpdateIsRefusedToWhoDoesNotWrite(): void {

		$eNote = $this->create(Note::TEAM);
		$post = ['id' => $eNote['id'], 'title' => 'Intrusion', 'content' => '', 'readers' => Note::TEAM, 'origin' => 'list'];

		$this->connectAs(self::USER_MEMBER, manager: FALSE);
		$action = $this->runPage('note/note:doUpdate', $post);
		$this->assertTrue($action instanceof \NotAllowedAction, "un membre qui lit la note ne l'enregistre pas : ".get_class($action));

		$this->connectAs(self::USER_OUTSIDE, member: FALSE);
		$action = $this->runPage('note/note:doUpdate', $post);
		$this->assertTrue($action instanceof \NotAllowedAction, "un non-membre non plus : ".get_class($action));

		$this->connectAs(self::USER_ADMIN, manager: TRUE);
		$this->assertEquals('Note de test', $this->getLast()['title'], "la note est intacte");

	}

	public function testBuildRefusesAnEmptyTitleOnUpdate(): void {

		$eNote = $this->create(Note::TEAM);

		$fw = new \FailWatch();
		$action = $this->runPage('note/note:doUpdate', ['id' => $eNote['id'], 'title' => '', 'content' => '', 'readers' => Note::TEAM, 'origin' => 'list']);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Note::title.check'), "un titre effacé est refusé : ".get_class($action).' '.$fw);
		$this->assertEquals('Note de test', $this->getLast()['title'], "et la note garde le sien");

	}

	/** Les instructions d'une réponse de `view/note.v.php`, en « sélecteur méthode ». */
	private function getInstructions(string $name, \ViewAction $action): array {

		$view = $this->getView('note/note', $name);
		$template = new \AjaxTemplate();
		$template->setData(new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action));

		new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $template->getData(), $template);

		$instructions = [];

		foreach($template->formatInstructions() as $export) {

			[$mode, $selector, $calls] = match($export[0]) {
				'qs', 'qsa' => $export,
				'js' => ['js', 'js', $export[1]]
			};

			foreach($calls as $call) {
				$instructions[] = $selector.' '.$call[0];
			}

		}

		return $instructions;

	}

	private function getDocument(string $html): \Dom\HTMLDocument {
		return \Dom\HTMLDocument::createFromString('<!doctype html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
	}

	/**
	 * Par le Lib, sans `build()` : ce test pose des notes de chaque sorte de lecteurs, que `build()`
	 * aurait acceptées, et compare la lecture et la liste, pas le formulaire.
	 */
	private function create(string $readers): Note {

		$eNote = new Note([
			'structure' => $this->eStructure,
			'title' => 'Note de test',
			'content' => NULL,
			'readers' => $readers,
			'cTarget' => new \Collection()
		]);

		NoteLib::create($eNote);

		return Note::model()
			->select(Note::getSelection())
			->whereId($eNote['id'])
			->get();

	}

	private function getLast(): Note {

		return Note::model()
			->select(Note::getSelection())
			->whereStructure($this->eStructure)
			->sort(['id' => SORT_DESC])
			->get();

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
