<?php
namespace note;

/**
 * Les rattachements d'une note (L47, lot 4) : `build()` ne rattache que ce que le package d'un type rend
 * pour la structure de la note, jusqu'au plafond ; un objet supprimé laisse la note entière et disparaît
 * de ses badges ; le filtre de la liste ne rend que les notes rattachées que le membre lit, et ses
 * suggestions ne parlent que de celles-là.
 *
 * Les comptes sont ceux du décor F1, dont l'appartenance se relit dans la base : Alice administre,
 * Bruno est saisonnier. Les espèces et les interventions sont celles que F1 tient de sa création. Les
 * gestes d'écriture passent par la vraie page, tout se joue dans la transaction de `TransactionTest`.
 *
 * Lancement : dev/test package=note file=Target
 */
class TargetTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;
	const STRUCTURE_OTHER = \structure\FixtureAiLib::F2;

	const EMAIL_ADMIN = 'decor.f1.admin@example.org';
	const EMAIL_PLANNING = 'decor.f1.planning@example.org';

	protected \structure\Structure $eStructure;

	private \Collection $cUser;
	private \Collection $cPlant;
	private \Collection $cAction;

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
			->getCollection(0, NoteSetting::TARGETS_MAX + 1);

		$this->cAction = \work\Action::model()
			->select(['id', 'name'])
			->whereStructure(self::STRUCTURE)
			->sort(['id' => SORT_ASC])
			->getCollection(0, 1);

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->enabledInitial = \dashboard\DashboardSetting::$enabled;
		$this->landingInitial = \dashboard\DashboardSetting::$landing;

		// Les pages se gardent par `isLanding()`, qui suit ces deux drapeaux : fermés dans
		// l'environnement, ils feraient refuser chaque geste de ce fichier.
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

	public function testAPlantAndAnActionAreAttached(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, onze espèces et une intervention') === FALSE) {
			return;
		}

		$values = [$this->getPlantValue(0), $this->getActionValue()];

		$action = $this->runPage('note/note:doCreate', $this->getPost('Récolte de la blette') + ['cTarget' => $values]);

		$this->assertTrue($action instanceof \RedirectAction, "l'administratrice rattache une espèce et une intervention : ".get_class($action));

		$eNote = $this->getLast();

		$this->assertEquals($this->sort($values), $this->getTargetValues($eNote), "les deux rattachements sont écrits");

		$cTarget = $this->getListed($eNote)['cTarget'];

		$this->assertEquals($this->sort($values), $this->sort($cTarget->makeArray(fn(Target $eTarget) => $eTarget->getValue())), "la liste porte les deux rattachements de la note");
		$this->assertEquals($this->cPlant->first()['name'], $cTarget->find(fn(Target $eTarget) => $eTarget['targetType'] === 'plant-plant', limit: 1)['eElement']['name'], "chacun avec son objet");

		$action = $this->runPage('note/note:doUpdateTargets', ['id' => $eNote['id'], 'cTarget' => [$this->getActionValue(), $this->getPlantValue(1)]]);

		$this->assertTrue($action instanceof \ViewAction, "elle change d'espèce dans le panneau des rattachements : ".get_class($action));
		$this->assertEquals($this->sort([$this->getActionValue(), $this->getPlantValue(1)]), $this->getTargetValues($eNote), "l'ancienne espèce part, la nouvelle arrive, l'intervention reste");

		$this->runPage('note/note:doUpdate', $this->getPost('Récolte de la blette') + ['id' => $eNote['id']]);

		$this->assertEquals($this->sort([$this->getActionValue(), $this->getPlantValue(1)]), $this->getTargetValues($eNote), "enregistrer la note ne touche pas aux rattachements");

		$this->runPage('note/note:doUpdatePinned', ['id' => $eNote['id'], 'pinned' => 1]);

		$this->assertEquals($this->sort([$this->getActionValue(), $this->getPlantValue(1)]), $this->getTargetValues($eNote), "l'épingle ne touche pas aux rattachements");

		$this->runPage('note/note:doUpdateTargets', ['id' => $eNote['id']]);

		$this->assertEquals([], $this->getTargetValues($eNote), "le dernier jeton retiré, l'omnibox ne poste plus rien et la note n'est plus rattachée");

	}

	/**
	 * Une espèce et une intervention que la base partage (`structure` nulle) se rattachent comme celles de
	 * la structure, la note les montre sous leur nom et le filtre les propose.
	 */
	public function testASharedPlantAndActionAreAttached(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, onze espèces et une intervention') === FALSE) {
			return;
		}

		$ePlantShared = \plant\Plant::model()
			->select(['id', 'name'])
			->whereStructure(NULL)
			->get();

		$eActionShared = \work\Action::model()
			->select(['id', 'name'])
			->whereStructure(NULL)
			->get();

		if($this->assertDecorPresent(($ePlantShared->notEmpty() and $eActionShared->notEmpty()), 'une espèce et une intervention partagées') === FALSE) {
			return;
		}

		$values = ['plant-plant:'.$ePlantShared['id'], 'work-action:'.$eActionShared['id']];

		$action = $this->runPage('note/note:doCreate', $this->getPost('Récolte partagée') + ['cTarget' => $values]);

		$this->assertTrue($action instanceof \RedirectAction, "l'administratrice rattache une espèce et une intervention partagées : ".get_class($action));

		$eNote = $this->getLast();

		$this->assertEquals($this->sort($values), $this->getTargetValues($eNote), "les deux rattachements sont écrits");

		$cTarget = $this->getListed($eNote)['cTarget'];

		$this->assertEquals($ePlantShared['name'], $cTarget->find(fn(Target $eTarget) => $eTarget['targetType'] === 'plant-plant', limit: 1)['eElement']['name'], "la note montre l'espèce partagée");
		$this->assertEquals($eActionShared['name'], $cTarget->find(fn(Target $eTarget) => $eTarget['targetType'] === 'work-action', limit: 1)['eElement']['name'], "et l'intervention partagée");
		$this->assertEquals($this->sort($values), $this->sort(array_keys(TargetLib::getReadableByStructure($this->eStructure, '')->getArrayCopy())), "le filtre les propose toutes les deux");

	}

	/**
	 * Une valeur que le package du type ne rend pas pour la structure refuse tout le choix : un objet
	 * d'une autre structure, un type qu'aucun package n'apporte, une valeur illisible.
	 */
	public function testWhatThePackageDoesNotReturnIsRefused(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, onze espèces et une intervention') === FALSE) {
			return;
		}

		$ePlantOther = \plant\Plant::model()
			->select('id')
			->whereStructure(self::STRUCTURE_OTHER)
			->get();

		$eBedOther = \map\Bed::model()
			->select('id')
			->whereStructure('!=', self::STRUCTURE)
			->whereStatus(\map\Bed::ACTIVE)
			->wherePlotFill(FALSE)
			->whereZoneFill(FALSE)
			->get();

		$eBedFill = \map\Bed::model()
			->select('id')
			->wherePlotFill(TRUE)
			->get();

		if($this->assertDecorPresent(($ePlantOther->notEmpty() and $eBedOther->notEmpty() and $eBedFill->notEmpty()), 'une espèce de F2, deux planches d\'ailleurs') === FALSE) {
			return;
		}

		$refused = [
			"une espèce d'une autre structure" => 'plant-plant:'.$ePlantOther['id'],
			"une planche d'une autre structure" => 'map-bed:'.$eBedOther['id'],
			"une planche de remplissage" => 'map-bed:'.$eBedFill['id'],
			"un type qu'aucun package n'apporte" => 'selling-customer:1',
			"une valeur illisible" => 'Blette',
			"la même espèce écrite avec un zéro en tête" => 'plant-plant:0'.$this->cPlant->first()['id'],
			"un tableau à la place d'une valeur" => ['plant-plant:'.$this->cPlant->first()['id']],
		];

		foreach($refused as $what => $value) {

			$fw = new \FailWatch();
			$action = $this->runPage('note/note:doCreate', $this->getPost('Intrusion') + ['cTarget' => [$this->getPlantValue(0), $value]]);

			$this->assertTrue($action instanceof \FailAction and $fw->has('Note::cTarget.cast'), $what." refuse le choix : ".get_class($action).' '.$fw);

		}

		$this->assertTrue($this->getLast()->empty(), "aucune note n'est écrite");
		$this->assertEquals(0, Target::model()->whereStructure($this->eStructure)->count(), "ni aucun rattachement");

	}

	public function testAboveTheCapIsRefused(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, onze espèces et une intervention') === FALSE) {
			return;
		}

		$values = $this->cPlant->makeArray(fn(\plant\Plant $ePlant) => 'plant-plant:'.$ePlant['id']);

		$fw = new \FailWatch();
		$action = $this->runPage('note/note:doCreate', $this->getPost('Toutes les espèces') + ['cTarget' => $values]);

		$this->assertTrue($action instanceof \FailAction and $fw->has('Note::cTarget.max'), (NoteSetting::TARGETS_MAX + 1)." rattachements sont refusés : ".get_class($action).' '.$fw);

		array_pop($values);

		$action = $this->runPage('note/note:doCreate', $this->getPost('Toutes les espèces') + ['cTarget' => $values]);

		$this->assertTrue($action instanceof \RedirectAction, NoteSetting::TARGETS_MAX." sont acceptés : ".get_class($action));

		$action = $this->runPage('note/note:doCreate', $this->getPost('La même deux fois') + ['cTarget' => [$this->getPlantValue(0), $this->getPlantValue(0)]]);

		$this->assertTrue($action instanceof \RedirectAction, "la même espèce postée deux fois est acceptée : ".get_class($action));
		$this->assertEquals([$this->getPlantValue(0)], $this->getTargetValues($this->getLast()), "et rattachée une fois");

	}

	/**
	 * Supprimer l'objet n'écrit rien sur la note : sa ligne reste, son badge disparaît, et la ligne part au
	 * premier enregistrement de la note qui suit.
	 */
	public function testADeletedTargetLeavesTheNoteWhole(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, onze espèces et une intervention') === FALSE) {
			return;
		}

		// Par le Lib, sans `build()` : `build()` vérifierait le nom et la famille d'une espèce saisie, et
		// ce test ne veut qu'une espèce que rien n'emploie, pour pouvoir la supprimer.
		$ePlant = new \plant\Plant([
			'structure' => $this->eStructure,
			'name' => 'Espèce de test '.uniqid(),
			'fqn' => NULL,
			'cycle' => \plant\Plant::ANNUAL
		]);

		\plant\PlantLib::create($ePlant);

		$this->runPage('note/note:doCreate', $this->getPost('Essai') + ['cTarget' => ['plant-plant:'.$ePlant['id'], $this->getActionValue()]]);

		$eNote = $this->getLast();

		$fw = new \FailWatch();

		\plant\PlantLib::delete($ePlant);

		$this->assertTrue($fw->ok() and \plant\Plant::model()->whereId($ePlant['id'])->exists() === FALSE, "l'espèce, que rien n'emploie, est supprimée : ".$fw);

		$this->assertEquals($this->sort(['plant-plant:'.$ePlant['id'], $this->getActionValue()]), $this->getTargetValues($eNote), "la suppression n'écrit rien sur la note");

		$eNoteListed = $this->getListed($eNote);

		$this->assertEquals('Essai', $eNoteListed['title'], "la note reste dans la liste");
		$this->assertEquals([$this->getActionValue()], $eNoteListed['cTarget']->makeArray(fn(Target $eTarget) => $eTarget->getValue()), "sans le badge de l'espèce supprimée");

		$this->runPage('note/note:doUpdateTargets', ['id' => $eNote['id'], 'cTarget' => [$this->getActionValue()]]);

		$this->assertEquals([$this->getActionValue()], $this->getTargetValues($eNote), "la ligne orpheline part au premier enregistrement des rattachements");

	}

	/**
	 * Une note montre ses badges dans le même ordre partout, par type puis par nom de l'objet : sur la carte
	 * de la liste, sous le filtre et sur sa page. Les rattachements sont postés dans un ordre qui n'est ni
	 * celui de leur écriture, ni celui de l'index `["note", "targetType", "targetId"]`, les deux ordres que
	 * la base rend selon la requête qui les charge.
	 */
	public function testTheBadgesComeInTheSameOrderEverywhere(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, onze espèces et une intervention') === FALSE) {
			return;
		}

		// Une espèce créée ici porte un id plus grand que celles du décor, et un nom qui la range avant elles.
		// Par le Lib, sans `build()` : `build()` lirait le nom et le cycle d'un formulaire posté, que ce test
		// pose en dur pour obtenir un nom qui contredise son id.
		$ePlantFirst = new \plant\Plant([
			'structure' => $this->eStructure,
			'name' => 'Aa espèce de test '.uniqid(),
			'fqn' => NULL,
			'cycle' => \plant\Plant::ANNUAL
		]);

		\plant\PlantLib::create($ePlantFirst);

		$ePlantSecond = $this->cPlant->first();

		$values = [$this->getActionValue(), 'plant-plant:'.$ePlantSecond['id'], 'plant-plant:'.$ePlantFirst['id']];
		$expected = ['plant-plant:'.$ePlantFirst['id'], 'plant-plant:'.$ePlantSecond['id'], $this->getActionValue()];

		$this->runPage('note/note:doCreate', $this->getPost('Une autre note de la liste') + ['cTarget' => [$this->getActionValue(), 'plant-plant:'.$ePlantSecond['id']]]);
		$this->runPage('note/note:doCreate', $this->getPost('Trois badges') + ['cTarget' => $values]);

		$eNote = $this->getLast();

		$getValues = fn(Note $eNote): array => $eNote['cTarget']->makeArray(fn(Target $eTarget) => $eTarget->getValue());

		$this->assertEquals($expected, $getValues($this->getListed($eNote)), "la carte de la liste range les espèces par nom, puis l'intervention");

		$search = new \Search([
			'target' => TargetLib::getByValue($this->getActionValue())
		]);

		$this->assertEquals($expected, $getValues(NoteLib::getByStructure($this->eStructure, $search, 0)->find(fn(Note $eNoteListed) => $eNoteListed['id'] === $eNote['id'], limit: 1)), "la liste filtrée dans le même ordre");

		TargetLib::putByNotes($this->eStructure, new \Collection([$eNote]));

		$this->assertEquals($expected, $getValues($eNote), "et la page de la note, qui la charge seule");

	}

	/**
	 * Le filtre ne rend que les notes rattachées que le membre lit, et ses suggestions ne nomment que les
	 * objets dont parlent ces notes-là.
	 */
	public function testTheFilterReturnsOnlyReadableAttachedNotes(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, onze espèces et une intervention') === FALSE) {
			return;
		}

		$this->runPage('note/note:doCreate', ['readers' => Note::TEAM] + $this->getPost('Blette pour toute l\'équipe') + ['cTarget' => [$this->getPlantValue(0)]]);
		$eNoteTeam = $this->getLast();

		$this->runPage('note/note:doCreate', $this->getPost('Blette et intervention, administrateurs') + ['cTarget' => [$this->getPlantValue(0), $this->getActionValue()]]);
		$eNoteAdmin = $this->getLast();

		$this->runPage('note/note:doCreate', ['readers' => Note::TEAM] + $this->getPost('Sans rattachement'));
		$eNoteLoose = $this->getLast();

		$search = new \Search([
			'target' => TargetLib::getByValue($this->getPlantValue(0))
		]);

		$this->assertEquals($this->sort([$eNoteTeam['id'], $eNoteAdmin['id']]), $this->sort(NoteLib::getByStructure($this->eStructure, $search, 0)->getIds()), "l'administratrice voit les deux notes rattachées à l'espèce, pas la troisième");
		$this->assertEquals(2, NoteLib::countBySearch($this->eStructure, $search), "et les compte");

		$this->assertEquals($this->sort([$this->getPlantValue(0), $this->getActionValue()]), $this->sort(array_keys(TargetLib::getReadableByStructure($this->eStructure, '')->getArrayCopy())), "ses suggestions nomment l'espèce et l'intervention");

		$this->connectAs(self::EMAIL_PLANNING);

		$this->assertEquals([$eNoteTeam['id']], NoteLib::getByStructure($this->eStructure, $search, 0)->getIds(), "Bruno ne voit que la note ouverte à toute l'équipe");
		$this->assertEquals(1, NoteLib::countBySearch($this->eStructure, $search), "et ne compte qu'elle");

		$this->assertEquals([$this->getPlantValue(0)], array_keys(TargetLib::getReadableByStructure($this->eStructure, '')->getArrayCopy()), "ses suggestions ne nomment pas l'intervention, dont ne parle qu'une note qu'il ne lit pas");
		$this->assertEquals([], array_keys(TargetLib::getReadableByStructure($this->eStructure, 'zzzz')->getArrayCopy()), "ce qu'il tape réduit les suggestions");

		$data = $this->runList(['target' => $this->getPlantValue(0)]);

		$this->assertEquals([$eNoteTeam['id']], $data->cNote->getIds(), "la page filtrée rend la même note");
		$this->assertEquals(1, $data->listed, "et le compte de l'omnibox");

		$data = $this->runList(['target' => 'plant-plant:'.\plant\Plant::model()->select('id')->whereStructure(self::STRUCTURE_OTHER)->get()['id']]);

		$this->assertFalse($data->search->has('target'), "une espèce d'une autre structure dans l'adresse ne filtre rien");
		$this->assertEquals($this->sort([$eNoteTeam['id'], $eNoteLoose['id']]), $this->sort($data->cNote->getIds()), "la liste reste entière");

	}

	/** Seul celui qui écrit les notes demande ce qu'on peut rattacher. */
	public function testOnlyAWriterSearchesTargets(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, onze espèces et une intervention') === FALSE) {
			return;
		}

		$action = $this->runPage('note/note:queryTarget', ['id' => self::STRUCTURE, 'query' => '']);

		$this->assertTrue($action instanceof \ViewAction, "l'administratrice cherche : ".get_class($action));

		$this->connectAs(self::EMAIL_PLANNING);

		$action = $this->runPage('note/note:queryTarget', ['id' => self::STRUCTURE, 'query' => '']);

		$this->assertTrue($action instanceof \NotAllowedAction, "Bruno, qui n'écrit pas les notes, est refusé : ".get_class($action));

		$action = $this->runPage('note/note:queryFilter', ['id' => self::STRUCTURE, 'query' => '']);

		$this->assertTrue($action instanceof \ViewAction, "mais filtre la liste qu'il lit : ".get_class($action));

	}

	/**
	 * Deux planches « 1 » de deux serres se départagent par leur parcelle : la recherche lit son nom, et
	 * la suggestion la nomme après le jardin. Le jardin d'une parcelle qui le remplit entier se nomme seul,
	 * et le nom du jardin trouve aussi ses planches.
	 */
	public function testBedsOfTheSameNameAreToldApartByTheirPlot(): void {

		if($this->assertDecorPresent($this->hasDecor(), 'F1, Alice, Bruno, onze espèces et une intervention') === FALSE) {
			return;
		}

		$eZone = new \map\Zone([
			'name' => 'Jardin des notes',
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		]);

		// Sans build() : le cas ne lit que les noms du jardin, des parcelles et des planches, et pas de
		// contour à vérifier. Le jardin porte aussi sa parcelle de remplissage (`PlotLib::createFromZone()`).
		\map\ZoneLib::create($eZone);

		foreach(['Serre nord', 'Serre sud'] as $name) {

			$ePlot = new \map\Plot([
				'name' => $name,
				'zone' => $eZone,
				'area' => 100
			]);

			\map\PlotLib::create($ePlot);

			\map\BedLib::create(new \map\Bed([
				'name' => '1',
				'plot' => $ePlot,
				'area' => 10
			]));

		}

		$cBed = \map\NoteTargetLib::search($this->eStructure, 'Serre sud', NoteSetting::SEARCH_LIMIT);

		$this->assertEquals(['1'], $cBed->getColumn('name'), "le nom de la serre trouve sa planche, et elle seule");
		$this->assertEquals(['Serre sud', FALSE], [$cBed->first()['plot']['name'], $cBed->first()['plot']['zoneFill']], "la planche porte la serre qui la nomme");

		$plots = \map\NoteTargetLib::search($this->eStructure, '1', NoteSetting::SEARCH_LIMIT)
			->find(fn(\map\Bed $eBed) => $eBed['zone']['id'] === $eZone['id'])
			->makeArray(fn(\map\Bed $eBed) => $eBed['plot']['name']);

		$this->assertEquals(['Serre nord', 'Serre sud'], $this->sort($plots), "les deux planches 1 se départagent par leur serre");

		$plots = \map\NoteTargetLib::search($this->eStructure, 'Jardin des notes', NoteSetting::SEARCH_LIMIT)
			->makeArray(fn(\map\Bed $eBed) => $eBed['plot']['name']);

		$this->assertEquals(['Serre nord', 'Serre sud'], $this->sort($plots), "le nom du jardin, que la suggestion affiche, trouve ses planches");

		// Sans build() : la planche reprend le nom de la recherche, ce qu'aucune règle du formulaire ne
		// regarde. `$ePlot` est la dernière serre créée, « Serre sud ».
		\map\BedLib::create(new \map\Bed([
			'name' => 'Serre sud bis',
			'plot' => $ePlot,
			'area' => 10
		]));

		$cBedFirst = \map\NoteTargetLib::search($this->eStructure, 'Serre sud', 1);

		$this->assertEquals(['Serre sud bis'], $cBedFirst->getColumn('name'), "une planche qui répond par son nom passe devant celles que sa parcelle amène");

	}

	private function hasDecor(): bool {

		return (
			$this->eStructure->notEmpty() and
			$this->cUser->count() === 2 and
			$this->cPlant->count() === NoteSetting::TARGETS_MAX + 1 and
			$this->cAction->count() === 1
		);

	}

	/** Ce que le formulaire poste hors des rattachements, pour une note que seuls les administrateurs lisent. */
	private function getPost(string $title): array {

		return [
			'structure' => self::STRUCTURE,
			'title' => $title,
			'content' => '',
			'readers' => Note::ADMIN
		];

	}

	private function getPlantValue(int $position): string {
		return 'plant-plant:'.$this->cPlant->slice($position, 1)->first()['id'];
	}

	private function getActionValue(): string {
		return 'work-action:'.$this->cAction->first()['id'];
	}

	private function sort(array $values): array {

		sort($values);
		return $values;

	}

	/** Les rattachements écrits en base, triés : la collection de l'élément ne dit pas ce que la page a écrit. */
	private function getTargetValues(Note $eNote): array {

		return $this->sort(Target::model()
			->select(['targetType', 'targetId'])
			->whereNote($eNote)
			->getCollection()
			->makeArray(fn(Target $eTarget) => $eTarget->getValue()));

	}

	/** La note telle que la liste la rend au membre connecté, objets de ses rattachements compris. */
	private function getListed(Note $eNote): Note {
		return NoteLib::getByStructure($this->eStructure, new \Search(), 0)->find(fn(Note $eNoteListed) => $eNoteListed['id'] === $eNote['id'], limit: 1);
	}

	private function getLast(): Note {

		return Note::model()
			->select(Note::getSelection())
			->whereStructure($this->eStructure)
			->sort(['id' => SORT_DESC])
			->get();

	}

	/**
	 * Un compte réel du décor, son appartenance relue dans la base.
	 */
	private function connectAs(string $email): void {

		\user\ConnectionLib::setOnline($this->cUser[$email]);
		\structure\MemberLib::clearCache();

	}

	/** `/ferme/{id}/notes` et ce que la page pose pour la vue. Forme de `runList()` de `Notebook.t.php`. */
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

}
?>
