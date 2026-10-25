<?php
namespace note;

/**
 * Les PDF des notes, lot 6 (L47) : la route distante ne rend rien sans adresse signée, la route qui
 * signe juge le droit avant, et le carnet n'emporte que les notes que le demandeur y lit, dans l'ordre
 * de son onglet.
 *
 * Rien n'appelle Puppeteer : les cas s'arrêtent avant `\main\PdfLib::build()` (refus), ou jouent la
 * route distante elle-même, comme `amap/test/Sheet.t.php`.
 *
 * Les comptes sont ceux du décor F1, connectés pour de vrai : la route distante relit en base
 * l'adhésion du demandeur que nomme l'adresse, et un compte fictif n'y est membre de rien. La vraie
 * page se joue comme dans `Note.t.php`, qui dit pourquoi.
 *
 * Lancement : dev/test package=note file=Pdf
 */
class PdfTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	const USER_ADMIN = 'decor.f1.admin@example.org';
	const USER_MEMBER = 'decor.f1.planning@example.org';
	const USER_OTHER = 'decor.f2.admin@example.org';

	protected \structure\Structure $eStructure;

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

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.self::STRUCTURE.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->enabledInitial = \dashboard\DashboardSetting::$enabled;
		$this->landingInitial = \dashboard\DashboardSetting::$landing;

		\dashboard\DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;
		\dashboard\DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => TRUE;

	}

	public function setUp(): void {

		parent::setUp();

		$this->connectAs(self::USER_ADMIN);

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

		\dashboard\DashboardSetting::$enabled = $this->enabledInitial;
		\dashboard\DashboardSetting::$landing = $this->landingInitial;

		\user\ConnectionLib::setOnline($this->eUserInitial);
		\structure\MemberLib::clearCache();

	}

	public function testTheRemoteRouteRefusesAnUnsignedAddress(): void {

		$eNotebook = $this->createNotebook('Cultures');
		$eNote = $this->createNote('Pucerons', Note::TEAM, $eNotebook);
		$eNoteOther = $this->createNote('Rotation', Note::ADMIN, $eNotebook);

		$this->assertTrue($this->runRemote('/note/pdf:getNote?id='.$eNote['id'], sign: FALSE) instanceof \NotAllowedAction, "une adresse sans signature est refusée");
		$this->assertTrue($this->runRemote('/note/pdf:getNotebook?id='.$eNotebook['id'].'&user='.$this->getUser(self::USER_MEMBER)['id'], sign: FALSE) instanceof \NotAllowedAction, "celle d'un carnet aussi");

		// La signature d'une note n'ouvre pas sa voisine : elle porte l'identifiant demandé.
		$url = \Lime::signRemote('selling', '/note/pdf:getNote?id='.$eNote['id']);
		$url = str_replace('id='.$eNote['id'].'&', 'id='.$eNoteOther['id'].'&', $url);

		$this->assertTrue($this->runRemote($url, sign: FALSE) instanceof \NotAllowedAction, "la signature d'une note n'ouvre pas une autre note");

		// Ni un carnet signé pour un membre ne s'ouvre au nom de l'administrateur.
		$url = \Lime::signRemote('selling', '/note/pdf:getNotebook?id='.$eNotebook['id'].'&user='.$this->getUser(self::USER_MEMBER)['id']);
		$url = str_replace('user='.$this->getUser(self::USER_MEMBER)['id'], 'user='.$this->getUser(self::USER_ADMIN)['id'], $url);

		$this->assertTrue($this->runRemote($url, sign: FALSE) instanceof \NotAllowedAction, "un carnet signé pour un membre ne s'ouvre pas au nom d'un autre");

		$action = $this->runRemote('/note/pdf:getNote?id='.$eNote['id']);

		$this->assertTrue($action instanceof \ViewAction, "l'adresse signée rend la note : ".get_class($action));
		$this->assertEquals($eNote['id'], $this->getData($action)->e['id'], "celle qu'elle nomme");

	}

	public function testTheRightIsJudgedBeforeSigning(): void {

		$eNotebook = $this->createNotebook('Cultures');
		$eNoteAdmin = $this->createNote('Tarifs', Note::ADMIN, $eNotebook);
		$eNotebookEmpty = $this->createNotebook('Matériel');

		$this->createNote('Pucerons', Note::ADMIN, $eNotebookEmpty);

		$this->connectAs(self::USER_MEMBER);

		$action = $this->runDownload('note/pdf:downloadNote', $eNoteAdmin['id']);

		$this->assertTrue($action instanceof \NotAllowedAction, "un membre ne télécharge pas une note des administrateurs : ".get_class($action));

		$action = $this->runDownload('note/pdf:downloadNotebook', $eNotebookEmpty['id']);

		$this->assertTrue($action instanceof \NotExpectedAction, "ni un carnet où il ne lit aucune note : ".get_class($action));

		$this->connectAs(self::USER_OTHER);

		$action = $this->runDownload('note/pdf:downloadNotebook', $eNotebook['id']);

		$this->assertTrue($action instanceof \NotAllowedAction, "un non-membre ne télécharge pas un carnet : ".get_class($action));

	}

	public function testANotebookLeavesOutWhatTheMemberDoesNotRead(): void {

		$eNotebook = $this->createNotebook('Cultures');
		$eNotebookOther = $this->createNotebook('Bâtiments');

		$eNoteTeam = $this->createNote('Pucerons', Note::TEAM, $eNotebook);
		$eNoteAdmin = $this->createNote('Rotation', Note::ADMIN, $eNotebook);
		$eNotePinned = $this->createNote('Chambre froide', Note::TEAM, $eNotebook);
		$this->createNote('Serre', Note::TEAM, $eNotebookOther);

		// Par le Lib, sans `build()`, qui n'aurait à juger qu'un booléen : l'épingle s'éprouve dans `Note.t.php`.
		NoteLib::update(new Note(['id' => $eNotePinned['id'], 'pinned' => TRUE]), ['pinned']);

		$this->assertEquals([$eNotePinned['id'], $eNoteAdmin['id'], $eNoteTeam['id']], $this->runNotebook($eNotebook, self::USER_ADMIN), "l'administrateur emporte tout le carnet, l'épinglée d'abord, puis de la plus récente à la plus ancienne");
		$this->assertEquals([$eNotePinned['id'], $eNoteTeam['id']], $this->runNotebook($eNotebook, self::USER_MEMBER), "le membre n'emporte que ce que toute l'équipe lit");

		$action = $this->runRemote('/note/pdf:getNotebook?id='.$eNotebook['id'].'&user='.$this->getUser(self::USER_OTHER)['id']);

		$this->assertTrue($action instanceof \NotAllowedAction, "un demandeur qui n'est pas membre n'emporte rien : ".get_class($action));

	}

	/**
	 * Ce que la route distante met dans le PDF du carnet signé pour ce demandeur. Elle ne connaît
	 * personne : le compte connecté ici est un autre, pour qu'une lecture sur la connexion se voie.
	 */
	private function runNotebook(Notebook $eNotebook, string $email): array {

		$this->connectAs(self::USER_OTHER);

		$action = $this->runRemote('/note/pdf:getNotebook?id='.$eNotebook['id'].'&user='.$this->getUser($email)['id']);

		if(($action instanceof \ViewAction) === FALSE) {
			throw new \Exception('Carnet de '.$email.' non rendu : '.get_class($action));
		}

		return $this->getData($action)->cNote->getIds();

	}

	private function createNotebook(string $name): Notebook {

		$action = $this->runPage('note/notebook:doCreate', ['structure' => self::STRUCTURE, 'name' => $name], 'POST');

		if(($action instanceof \RedirectAction) === FALSE) {
			throw new \Exception('Carnet '.$name.' non créé : '.get_class($action));
		}

		return Notebook::model()
			->select(Notebook::getSelection())
			->whereStructure($this->eStructure)
			->whereName($name)
			->get();

	}

	/**
	 * Par le Lib, sans `build()` : le carnet posé est celui que le cas vient de créer dans F1, que
	 * `build()` aurait accepté.
	 */
	private function createNote(string $title, string $readers, Notebook $eNotebook): Note {

		$eNote = new Note([
			'structure' => $this->eStructure,
			'notebook' => $eNotebook,
			'title' => $title,
			'content' => '<p>'.$title.'</p>',
			'readers' => $readers,
			'cTarget' => new \Collection()
		]);

		NoteLib::create($eNote);

		return Note::model()
			->select(Note::getSelection())
			->whereId($eNote['id'])
			->get();

	}

	/** La route qui signe, appelée comme le bouton du PDF l'appelle. */
	private function runDownload(string $page, int $id): \Throwable {
		return $this->runPage($page, ['id' => $id], 'GET');
	}

	/**
	 * La route distante comme Puppeteer l'appelle : l'adresse signée par `Lime::signRemote()`, ou telle
	 * quelle quand le cas éprouve une adresse sans signature ou retouchée.
	 */
	private function runRemote(string $url, bool $sign = TRUE): \Throwable {

		if($sign) {
			$url = \Lime::signRemote('selling', $url);
		}

		$get = [];
		parse_str(parse_url($url, PHP_URL_QUERY), $get);

		$page = 'note/pdf:'.substr(parse_url($url, PHP_URL_PATH), strlen('/note/pdf:'));

		$requestUri = $_SERVER['REQUEST_URI'] ?? NULL;
		$_SERVER['REQUEST_URI'] = $url;

		try {
			return $this->runPage($page, $get, 'GET');
		} finally {
			$_SERVER['REQUEST_URI'] = $requestUri;
		}

	}

	private function getData(\ViewAction $action): \stdClass {
		return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);
	}

	/** Exécute la vraie page et rend ce qu'elle lève. Forme reprise de `Note.t.php`. */
	private function runPage(string $page, array $input, string $requestMethod): \Throwable {

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, $requestMethod);

		$getPages = new \ReflectionMethod(\Page::class, 'getPages');

		if($requestMethod === 'GET') {
			$_GET = $input;
		} else {
			$_POST = $input;
		}

		$_REQUEST = $input;

		try {
			\Page::execute($getPages->invoke(NULL, $page), new \stdClass());
		} catch(\Throwable $e) {
			return $e;
		} finally {
			$_GET = [];
			$_POST = [];
			$_REQUEST = [];
			$method->setValue(NULL, $methodBefore);
		}

		return new \LogicException('l\'action '.$page.' n\'a rien levé');

	}

	/**
	 * Un compte du décor, connecté pour de vrai : ses adhésions se relisent en base, le cache de
	 * `MemberLib` vidé, comme à une nouvelle requête.
	 */
	private function connectAs(string $email): void {

		\user\ConnectionLib::setOnline($this->getUser($email));
		\structure\MemberLib::clearCache();

	}

	private function getUser(string $email): \user\User {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

		if($eUser->empty()) {
			throw new \Exception('Compte '.$email.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		return $eUser;

	}

}
?>
