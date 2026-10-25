<?php
namespace note;

/**
 * Le bloc Notes des écrans de production (L84) : `NoteLib::getByTargets()` rend les notes rattachées
 * à l'un des objets que l'écran lui nomme, une seule fois chacune, avec les seuls rattachements qui
 * l'y amènent ; il ne rend pas ce que le membre ne lit pas, ni rien quand les notes sont fermées à la
 * structure, et son coût en requêtes ne suit pas le nombre de notes.
 *
 * F1 ne porte ni série ni itinéraire : les cibles sont posées comme les pages les construisent (une
 * espèce, une intervention, une planche), et les écrans se jugent dans Chrome. Comptes, espèces et
 * interventions sont ceux de `Target.t.php`, qui dit d'où ils viennent.
 *
 * Lancement : dev/test package=note file=NoteLinked
 */
class NoteLinkedTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	const EMAIL_ADMIN = 'decor.f1.admin@example.org';
	const EMAIL_PLANNING = 'decor.f1.planning@example.org';

	protected \structure\Structure $eStructure;

	private \Collection $cUser;
	private \Collection $cPlant;
	private \work\Action $eAction;

	private \user\User $eUserInitial;
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

		$this->cUser = \user\User::model()
			->select(['id', 'firstName', 'lastName', 'email'])
			->whereEmail('IN', [self::EMAIL_ADMIN, self::EMAIL_PLANNING])
			->getCollection(index: 'email');

		$this->cPlant = \plant\Plant::model()
			->select(['id', 'name'])
			->whereStructure(self::STRUCTURE)
			->whereStatus(\plant\Plant::ACTIVE)
			->sort(['id' => SORT_ASC])
			->getCollection(0, 2);

		$this->eAction = \work\Action::model()
			->select(['id', 'name'])
			->whereStructure(self::STRUCTURE)
			->sort(['id' => SORT_ASC])
			->get();

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->enabledInitial = \dashboard\DashboardSetting::$enabled;
		$this->landingInitial = \dashboard\DashboardSetting::$landing;

		// `getByTargets()` se garde par `isLanding()`, qui suit ces deux drapeaux : fermés dans
		// l'environnement, il ne rendrait rien à aucune méthode de ce fichier.
		\dashboard\DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;
		\dashboard\DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;

	}

	public function setUp(): void {

		parent::setUp();

		if($this->hasDecor() === FALSE) {
			return;
		}

		$this->connectAs(self::EMAIL_ADMIN);

		// Le décor F1 garde en dev les notes de la contre-recette : chaque méthode part d'une structure
		// sans note, dans la transaction qui les rendra à la fin.
		foreach(Note::model()->select('id')->whereStructure($this->eStructure)->getCollection() as $eNote) {
			NoteLib::delete($eNote);
		}

	}

	public function finalize(): void {

		\dashboard\DashboardSetting::$enabled = $this->enabledInitial;
		\dashboard\DashboardSetting::$landing = $this->landingInitial;

		\user\ConnectionLib::setOnline($this->eUserInitial);
		\structure\MemberLib::clearCache();

	}

	/**
	 * L'écran d'une série nomme ses espèces, ses interventions et ses planches : une note rattachée à
	 * l'une d'elles y paraît, une note rattachée à deux n'y paraît qu'une fois et garde ses deux
	 * rattachements, et ce qui la rattache ailleurs ne vient pas.
	 */
	public function testANoteComesOnceWithTheTargetsThatBringIt(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, deux espèces et une intervention') === FALSE) {
			return;
		}

		$eBed = $this->createBed();

		$eNoteBed = $this->createNote('Planche lourde', Note::TEAM, ['map-bed:'.$eBed['id']]);
		$eNoteBoth = $this->createNote('Tuteurage de la blette', Note::TEAM, [$this->getPlantValue(0), $this->getActionValue()]);
		$eNoteOther = $this->createNote('Autre espèce', Note::TEAM, [$this->getPlantValue(1)]);
		$eNoteElsewhere = $this->createNote('Blette et autre espèce', Note::TEAM, [$this->getPlantValue(0), $this->getPlantValue(1)]);

		$cNote = NoteLib::getByTargets($this->eStructure, $this->getTargets($eBed));

		$this->assertEquals($this->sort([$eNoteBed['id'], $eNoteBoth['id'], $eNoteElsewhere['id']]), $this->sort($cNote->getIds()), "les notes de la planche, de l'espèce et de l'intervention, pas celle de l'autre espèce");
		$this->assertEquals(3, $cNote->count(), "la note à deux origines est comptée une fois");

		$this->assertEquals($this->sort([$this->getPlantValue(0), $this->getActionValue()]), $this->getValues($this->getIn($cNote, $eNoteBoth)), "elle porte ses deux origines");
		$this->assertEquals([$this->getPlantValue(0)], $this->getValues($this->getIn($cNote, $eNoteElsewhere)), "une note rattachée aussi ailleurs ne porte que ce qui l'amène ici");
		$this->assertEquals($this->cPlant->first()['name'], $this->getIn($cNote, $eNoteBoth)['cTarget']->find(fn(Target $eTarget) => $eTarget['targetType'] === 'plant-plant', limit: 1)['eElement']['name'], "le badge nomme son espèce");

		$this->assertEquals($this->sort([$eNoteOther['id'], $eNoteElsewhere['id']]), $this->sort(NoteLib::getByTargets($this->eStructure, ['plant-plant' => [$this->cPlant->slice(1, 1)->first()['id']]])->getIds()), "l'espèce de l'autre note la trouve");

	}

	/** Une note que le salarié ne lit pas n'est ni rendue ni comptée. */
	public function testANoteForAdministratorsIsNotShownToAnEmployee(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, deux espèces et une intervention') === FALSE) {
			return;
		}

		$eNoteTeam = $this->createNote('Pour toute l\'équipe', Note::TEAM, [$this->getActionValue()]);
		$eNoteAdmin = $this->createNote('Pour les administrateurs', Note::ADMIN, [$this->getActionValue()]);

		$targets = ['work-action' => [$this->eAction['id']]];

		$this->assertEquals($this->sort([$eNoteTeam['id'], $eNoteAdmin['id']]), $this->sort(NoteLib::getByTargets($this->eStructure, $targets)->getIds()), "l'administratrice voit les deux");

		$this->connectAs(self::EMAIL_PLANNING);

		$this->assertEquals([$eNoteTeam['id']], NoteLib::getByTargets($this->eStructure, $targets)->getIds(), "Bruno ne voit que celle de l'équipe, et le compteur du bloc dit 1");

	}

	/** Une structure à qui les notes sont fermées, ou un écran sans cible, ne rendent rien et ne lisent rien. */
	public function testNothingWithoutNotesOrTargets(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, deux espèces et une intervention') === FALSE) {
			return;
		}

		$this->createNote('Intervention', Note::TEAM, [$this->getActionValue()]);

		$targets = ['work-action' => [$this->eAction['id']]];

		$this->assertEquals(0, $this->countQueries(fn() => NoteLib::getByTargets($this->eStructure, ['plant-plant' => [], 'work-action' => []])), "un écran sans cible ne lit rien");

		\dashboard\DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => FALSE;

		$this->assertEquals(0, NoteLib::getByTargets($this->eStructure, $targets)->count(), "les notes fermées, l'intervention ne montre rien");

		\dashboard\DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;

		$this->assertEquals(1, NoteLib::getByTargets($this->eStructure, $targets)->count(), "rouvertes, elle montre sa note");

	}

	/** Le coût ne suit pas le nombre de notes : 1 note et 14 notes, sur trois types de cibles. */
	public function testTheQueriesDoNotGrowWithTheNotes(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, deux espèces et une intervention') === FALSE) {
			return;
		}

		$eBed = $this->createBed();
		$targets = $this->getTargets($eBed);

		$this->createNote('Note 1', Note::TEAM, [$this->getPlantValue(0), $this->getActionValue(), 'map-bed:'.$eBed['id']]);

		$queriesOne = $this->countQueries(fn() => NoteLib::getByTargets($this->eStructure, $targets));

		for($position = 2; $position <= 14; $position++) {
			$this->createNote('Note '.$position, Note::TEAM, [match($position % 3) {
				0 => $this->getPlantValue(0),
				1 => $this->getActionValue(),
				2 => 'map-bed:'.$eBed['id']
			}]);
		}

		$queriesMany = $this->countQueries(fn() => NoteLib::getByTargets($this->eStructure, $targets));

		$this->assertEquals(14, NoteLib::getByTargets($this->eStructure, $targets)->count(), "les 14 notes sont rendues");
		$this->assertEquals(7, $queriesOne, "à 1 note, sur trois types de cibles");
		$this->assertEquals($queriesOne, $queriesMany, "à 14 notes, autant qu'à une");

	}

	/** Replier le bloc écrit la préférence du seul membre connecté, salarié compris. */
	public function testFoldingWritesOnlyTheConnectedMember(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, deux espèces et une intervention') === FALSE) {
			return;
		}

		$this->connectAs(self::EMAIL_PLANNING);

		$action = $this->runPage('note/note:doLinked', ['id' => self::STRUCTURE, 'viewNoteLinked' => 0]);

		$this->assertTrue($action instanceof \VoidAction, "Bruno replie le bloc : ".get_class($action));
		$this->assertEquals([self::EMAIL_ADMIN => TRUE, self::EMAIL_PLANNING => FALSE], $this->getLinkedByEmail(), "sa ligne seule est écrite");

		$this->runPage('note/note:doLinked', ['id' => self::STRUCTURE, 'viewNoteLinked' => 1]);

		$this->assertEquals([self::EMAIL_ADMIN => TRUE, self::EMAIL_PLANNING => TRUE], $this->getLinkedByEmail(), "il le déplie");

		\dashboard\DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => FALSE;

		$action = $this->runPage('note/note:doLinked', ['id' => self::STRUCTURE, 'viewNoteLinked' => 0]);

		\dashboard\DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;

		$this->assertTrue($action instanceof \NotExpectedAction, "les notes fermées, rien ne s'écrit : ".get_class($action));

	}

	private function getLinkedByEmail(): array {

		$values = [];

		foreach($this->cUser as $email => $eUser) {
			$values[$email] = \structure\Member::model()
				->select('viewNoteLinked')
				->whereStructure(self::STRUCTURE)
				->whereUser($eUser)
				->getValue('viewNoteLinked');
		}

		ksort($values);

		return $values;

	}

	private function hasDecor(): bool {

		return (
			$this->eStructure->notEmpty() and
			$this->cUser->count() === 2 and
			$this->cPlant->count() === 2 and
			$this->eAction->notEmpty()
		);

	}

	/** Les cibles d'un écran de série, sous la forme que la page passe : espèce, intervention, planche. */
	private function getTargets(\map\Bed $eBed): array {

		return [
			'plant-plant' => [$this->cPlant->first()['id']],
			'work-action' => [$this->eAction['id']],
			'map-bed' => [$eBed['id']]
		];

	}

	/**
	 * Zone, parcelle et planche créées sans build() : le cas ne lit que la planche comme cible, sans
	 * contour, dimensions ni saison à vérifier.
	 */
	private function createBed(): \map\Bed {

		$eZone = new \map\Zone([
			'name' => 'Jardin du bloc Notes',
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		]);

		\map\ZoneLib::create($eZone);

		$ePlot = new \map\Plot([
			'name' => 'Serre',
			'zone' => $eZone,
			'area' => 100
		]);

		\map\PlotLib::create($ePlot);

		$eBed = new \map\Bed([
			'name' => 'P1',
			'plot' => $ePlot,
			'area' => 10
		]);

		\map\BedLib::create($eBed);

		return $eBed;

	}

	/**
	 * Une note par le vrai formulaire, qui vérifie que chaque rattachement est un objet de la structure.
	 */
	private function createNote(string $title, string $readers, array $values): Note {

		$action = $this->runPage('note/note:doCreate', [
			'structure' => self::STRUCTURE,
			'title' => $title,
			'content' => '',
			'readers' => $readers,
			'cTarget' => $values
		]);

		if(($action instanceof \RedirectAction) === FALSE) {
			throw new \LogicException('la note « '.$title.' » n\'a pas été créée : '.get_class($action));
		}

		return Note::model()
			->select(Note::getSelection())
			->whereStructure($this->eStructure)
			->sort(['id' => SORT_DESC])
			->get();

	}

	private function getPlantValue(int $position): string {
		return 'plant-plant:'.$this->cPlant->slice($position, 1)->first()['id'];
	}

	private function getActionValue(): string {
		return 'work-action:'.$this->eAction['id'];
	}

	private function getIn(\Collection $cNote, Note $eNote): Note {
		return $cNote->find(fn(Note $eNoteLinked) => $eNoteLinked['id'] === $eNote['id'], limit: 1);
	}

	private function getValues(Note $eNote): array {
		return $this->sort($eNote['cTarget']->makeArray(fn(Target $eTarget) => $eTarget->getValue()));
	}

	private function sort(array $values): array {

		sort($values);
		return $values;

	}

	private function connectAs(string $email): void {

		\user\ConnectionLib::setOnline($this->cUser[$email]);
		\structure\MemberLib::clearCache();

	}

	/** Exécute la vraie page et rend ce qu'elle lève. Forme de `runPage()` de `Target.t.php`. */
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

}
?>
