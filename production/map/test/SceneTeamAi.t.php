<?php
namespace map;

/**
 * L'équipe de la scène 3D du plan avec ses avatars (lot 4b de A9), telle que `SceneLib::getByStructure()` la rend :
 * `team`, une entrée par présent de la saison, et la colonne `team` de `crew`, les rangs des présents de chaque jour.
 *
 * Ce qui s'y prouve :
 * 1. sans `avatars`, aucun rang ne sort, même quand l'équipe a des avatars ;
 * 2. avec, le modèle téléversé passe avant la composition, qui passe avant le corps anonyme, et les rangs d'un jour
 *    comptent ses présents ;
 * 3. un auteur que la structure n'admet pas (fantôme, compte suspendu, bannissement en cours) garde le corps anonyme,
 *    composition comprise ; un envoi retiré ou un drapeau éteint laissent la composition ;
 * 4. au-delà de `MapSetting::CREW_UPLOADS_MAX`, les moins présents gardent leur composition ;
 * 5. la page ne demande les avatars que pour un membre qui voit le planning des autres.
 *
 * Le décor est la structure F1, ses quatre membres et leurs comptes fictifs. Leurs avatars et leurs périodes au planning
 * sont ceux que chaque méthode pose dans sa transaction : la dev peut en porter d'autres, que `setUp()` retire d'abord.
 * Alice, Bruno et Claire sont au planning depuis 2025 ; David, observateur, n'y est pas. La scène compte présent qui a une
 * période ce jour-là, quels que soient les jours de son contrat (`SceneLib::getCrew()`).
 *
 * Qui peut voir le modèle de qui se juge aussi dans `base/user/test/UserAvatarUploadSceneAi.t.php` ; ce que la scène
 * en fait se joue dans Chrome.
 *
 * Lancement : dev/test package=map file=SceneTeamAi
 */
class SceneTeamAiTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	const ADMIN = 'decor.f1.admin@example.org';
	const PLANNING = 'decor.f1.planning@example.org';
	const ACCOUNTANT = 'decor.f1.comptable@example.org';
	const OBSERVER = 'decor.f1.observateur@example.org';

	const NOW = '2026-10-21 10:00:00';
	const SEASON = 2026;

	private \structure\Structure $eStructure;

	private \user\User $eUserAdmin;
	private \user\User $eUserPlanning;
	private \user\User $eUserAccountant;
	private \user\User $eUserObserver;

	private \user\User $eUserInitial;
	private \Closure $avatarUploadInitial;

	protected function getTransactionModel(): \ModuleModel {
		return \structure\Member::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->avatarUploadInitial = \user\UserSetting::$avatarUpload;

		\user\UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => TRUE;

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(self::STRUCTURE)
			->get();

		// Aucun avatar dans l'équipe du décor : chaque méthode pose ceux dont elle a besoin. Une écriture directe et non
		// `UserAvatarLib::delete()`, qui retirerait du disque un modèle téléversé que la transaction annulée ne rendrait pas
		\user\UserAvatar::model()
			->whereUser('IN', \structure\MemberLib::getByStructure($this->eStructure, index: NULL)->getColumnCollection('user', 'user'))
			->delete();

		[$this->eUserAdmin, $this->eUserPlanning, $this->eUserAccountant, $this->eUserObserver] = array_map(
			fn(string $email) => \user\User::model()
				->select(\user\User::getSelection())
				->whereEmail($email)
				->get(),
			[self::ADMIN, self::PLANNING, self::ACCOUNTANT, self::OBSERVER]
		);

		$cPresence = \hr\Presence::model()
			->select('id')
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cPresence as $ePresence) {
			\hr\PresenceLib::delete($ePresence);
		}

		$cAbsence = \hr\Absence::model()
			->select('id')
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cAbsence as $eAbsence) {
			\hr\AbsenceLib::delete($eAbsence);
		}

		foreach([$this->eUserAdmin, $this->eUserPlanning, $this->eUserAccountant] as $eUser) {
			if($eUser->notEmpty()) {
				$this->createPresence($eUser);
			}
		}

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', TRUE);

	}

	public function tearDown(): void {

		parent::tearDown();

		\user\UserSetting::$avatarUpload = $this->avatarUploadInitial;
		\user\ConnectionLib::setOnline($this->eUserInitial);
		\structure\MemberLib::clearCache();

		// La transaction annulée rend `hasHr` à la base, pas à la configuration que le processus garde pour F1
		\structure\FeatureLib::forget($this->eStructure);
		ConfigurationLib::forget($this->eStructure);

	}

	public function testAnonymous(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserPlanning);
		$this->createUpload($this->eUserPlanning);

		$scene = SceneLib::getByStructure($this->eStructure, self::SEASON);

		$this->assertEquals(['date', 'members', 'team'], $scene['columns']['crew']);
		$this->assertEquals([], $scene['team'], "sans avatars, aucune entrée");
		$this->assertEquals(TRUE, $scene['crew'] !== [], "témoin : l'équipe compte des présents");
		$this->assertEquals([NULL], array_values(array_unique(array_column($scene['crew'], 2), SORT_REGULAR)), "aucun rang, même vers un corps anonyme");

	}

	public function testOrder(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserPlanning);
		$this->createAvatar($this->eUserAccountant);
		$this->createUpload($this->eUserAccountant);

		$scene = $this->getScene();

		$this->assertEquals(3, count($scene['team']), "une entrée par présent de la saison");

		[$claire, $bruno, $alice] = $scene['team'];

		$this->assertEquals(TRUE, $claire['eUserAvatarUpload']->notEmpty(), "le modèle de Claire, en premier");
		$this->assertEquals(['models' => ['avatar' => []]], $claire['eUserAvatarUpload']['bakedIndex'], "avec l'index de sa conversion");
		$this->assertEquals(FALSE, $claire['eUserAvatarUpload']->offsetExists('hash'), "sans le nom de son fichier");
		$this->assertEquals(TRUE, is_array($claire['fiche']), "et sa fiche, le repli");
		$this->assertEquals(TRUE, $bruno['eUserAvatarUpload']->empty() and is_array($bruno['fiche']), "la composition de Bruno, ensuite");
		$this->assertEquals(NULL, $alice, "Alice, sans avatar, garde le corps anonyme");

		foreach($scene['crew'] as [$date, $members, $ranks]) {

			$this->assertEquals($members, count($ranks), "autant de rangs que de présents le ".$date);

			$sorted = $ranks;
			sort($sorted);

			$this->assertEquals($sorted, $ranks, "les avatars d'abord le ".$date);

		}

		$this->assertEquals([self::SEASON.'-10-21', 3, [0, 1, 2]], $this->getDay($scene, self::SEASON.'-10-21'), "les trois le même jour");

	}

	public function testGhost(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserPlanning);
		$this->createUpload($this->eUserPlanning);

		// Le rôle se pose sans `build()`, qui refuse le rôle fantôme à un compte : c'est le cas que le test éprouve
		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserPlanning, $this->eStructure);
		$eMember['role'] = \structure\RoleLib::getOne($this->eStructure, 'ghost');

		\structure\MemberLib::update($eMember, ['role']);

		$this->assertAnonymous('auteur au rôle fantôme');

	}

	public function testSuspended(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserPlanning);
		$this->createUpload($this->eUserPlanning);

		// Le statut se pose sans `build()` : aucun formulaire ne suspend un compte, c'est une écriture d'admin
		$this->eUserPlanning['status'] = \user\User::SUSPENDED;

		\user\UserLib::update($this->eUserPlanning, ['status']);

		$this->assertAnonymous('auteur au compte suspendu');

	}

	public function testFormerMember(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserPlanning);
		$this->createUpload($this->eUserPlanning);

		// Le départ se pose sans `build()`, que la sortie d'un membre n'emprunte pas ; sa période au planning reste ouverte, et
		// le compte avec elle
		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserPlanning, $this->eStructure);
		$eMember['status'] = \structure\Member::OUT;

		\structure\MemberLib::update($eMember, ['status']);

		$this->assertAnonymous('ancien membre');

	}

	public function testBanned(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserPlanning);

		// Sans `BanLib::createBan()`, qui coupe aussi les sessions, que le test n'éprouve pas, et sans `build()`, que le
		// bannissement n'emprunte pas
		\user\BanLib::create(new \user\Ban([
			'user' => $this->eUserPlanning,
			'reason' => 'test',
			'admin' => $this->eUserAdmin,
			'until' => date('Y-m-d H:i:s', \Clock::shift('+1 day'))
		]));

		$this->assertAnonymous('auteur banni, composition comprise');

	}

	public function testWithdrawnAndFlag(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserPlanning);
		$eUserAvatarUpload = $this->createUpload($this->eUserPlanning);

		$this->assertEquals(TRUE, $this->getScene()['team'][0]['eUserAvatarUpload']->notEmpty(), "témoin : le modèle de Bruno");

		\user\UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => FALSE;

		$team = $this->getScene()['team'];

		$this->assertEquals(TRUE, $team[0]['eUserAvatarUpload']->empty() and is_array($team[0]['fiche']), "drapeau éteint : la composition");

		\user\UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => TRUE;

		// Le retrait se pose sans `build()`, qui ne porte pas le statut, que seule la modération écrit
		$eUserAvatarUpload['status'] = \user\UserAvatarUpload::WITHDRAWN;

		\user\UserAvatarUploadLib::update($eUserAvatarUpload, ['status']);

		$team = $this->getScene()['team'];

		$this->assertEquals(TRUE, $team[0]['eUserAvatarUpload']->empty() and is_array($team[0]['fiche']), "envoi retiré : la composition");

	}

	public function testCap(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		// Bruno n'arrive qu'en octobre : moins présent que les membres ajoutés, son modèle passe après le plafond
		$this->replacePresence($this->eUserPlanning, self::SEASON.'-10-01');
		$this->createAvatar($this->eUserPlanning);
		$this->createUpload($this->eUserPlanning);

		foreach(range(1, MapSetting::CREW_UPLOADS_MAX) as $position) {
			$eUser = $this->createMember('Membre '.$position);
			$this->createAvatar($eUser);
			$this->createUpload($eUser);
		}

		$team = $this->getScene()['team'];

		$models = array_filter($team, fn(?array $member) => $member !== NULL and $member['eUserAvatarUpload']->notEmpty());

		$this->assertEquals(MapSetting::CREW_UPLOADS_MAX, count($models), "pas plus de modèles que le plafond");
		$this->assertEquals(range(0, MapSetting::CREW_UPLOADS_MAX - 1), array_keys($models), "aux premiers rangs");
		$this->assertEquals(FALSE, in_array($this->eUserPlanning['id'], array_map(fn(array $member) => $member['eUserAvatarUpload']['user']['id'], $models)), "le moins présent garde sa composition");
		$this->assertEquals(TRUE, $team[MapSetting::CREW_UPLOADS_MAX]['eUserAvatarUpload']->empty() and is_array($team[MapSetting::CREW_UPLOADS_MAX]['fiche']), "juste après les modèles");

	}

	/**
	 * La page demande les avatars pour un membre qui voit le planning des autres, pas pour un observateur.
	 */
	public function testPage(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserPlanning);

		// La page renvoie à la création du plan tant que la structure n'a pas de parcelle de la saison, que son auteur signe
		\user\ConnectionLib::setOnline($this->eUserAdmin);

		$eZone = new Zone([
			'name' => 'Parcelle du décor '.uniqid(),
			'structure' => $this->eStructure,
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL
		]);

		ZoneLib::create($eZone);

		$this->assertEquals(TRUE, $this->getPageScene($this->eUserAdmin)['team'] !== [], "l'administrateur voit les avatars");

		$scene = $this->getPageScene($this->eUserObserver);

		$this->assertEquals([], $scene['team'], "l'observateur n'en voit aucun");
		$this->assertEquals([NULL], array_values(array_unique(array_column($scene['crew'], 2), SORT_REGULAR)), "ni aucun rang");

	}

	private function assertDecor(): bool {

		return $this->assertDecorPresent(
			$this->eStructure->notEmpty() and
			$this->eStructure->hasFeatureProduction() and
			$this->eUserAdmin->notEmpty() and
			$this->eUserPlanning->notEmpty() and
			$this->eUserAccountant->notEmpty() and
			$this->eUserObserver->notEmpty(),
			'la structure F1 avec la production et ses quatre comptes du décor'
		);

	}

	private function assertAnonymous(string $case): void {

		$team = $this->getScene()['team'];

		$this->assertEquals(TRUE, $team !== [], 'témoin : l\'équipe compte des présents');
		$this->assertEquals([], array_filter($team), $case.' : le corps anonyme pour tous');

	}

	private function getScene(): array {

		return SceneLib::getByStructure($this->eStructure, self::SEASON, avatars: TRUE);

	}

	private function getDay(array $scene, string $date): array {

		foreach($scene['crew'] as $day) {
			if($day[0] === $date) {
				return $day;
			}
		}

		return [];

	}

	/**
	 * La page du plan 3D jouée comme une requête du lecteur, forme de `executePage()` dans `base/hr/test/AbsenceAi.t.php` :
	 * la scène qu'elle passe à la vue.
	 */
	private function getPageScene(\user\User $eUser): array {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		\user\ConnectionLib::setOnline($eUser);
		\structure\MemberLib::clearCache();

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		$path = 'ferme/'.self::STRUCTURE.'/plan-3d/'.self::SEASON;
		$input = ['id' => (string)self::STRUCTURE, 'season' => (string)self::SEASON];

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [$input, [], $input];

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $path), new \stdClass());
			throw new \Exception('La page '.$path.' ne rend aucune vue');
		} catch(\ViewAction $action) {
			return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action)->scene;
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
			\user\ConnectionLib::setOnline($this->eUserInitial);
			\structure\MemberLib::clearCache();
		}

	}

	private function createAvatar(\user\User $eUser): void {

		\user\UserAvatarLib::create(new \user\UserAvatar([
			'user' => $eUser,
			'source' => \user\UserAvatar::EDITOR,
			'composition' => \user\UserAvatarLib::getDefault(),
			'replacesPhoto' => FALSE
		]));

	}

	/**
	 * Un modèle téléversé montré à la place de la composition. Hors `UserAvatarUploadLib::create()`, qui envoie le binaire
	 * converti au stockage des médias, que la transaction annulée ne rendrait pas : la scène ne lit que l'envoi, et un hash
	 * qui ne désigne aucun fichier lui suffit.
	 */
	private function createUpload(\user\User $eUser): \user\UserAvatarUpload {

		$eUserAvatarUpload = new \user\UserAvatarUpload([
			'user' => $eUser,
			'hash' => substr(md5(uniqid()), 0, 20),
			'bakedIndex' => ['models' => ['avatar' => []]],
			'rules' => 1,
			'status' => \user\UserAvatarUpload::NEW
		]);

		\user\UserAvatarUpload::model()->insert($eUserAvatarUpload);

		$eUserAvatar = \user\UserAvatarLib::getByUser($eUser);
		$eUserAvatar['upload'] = $eUserAvatarUpload;

		\user\UserAvatarLib::update($eUserAvatar, ['upload']);

		return $eUserAvatarUpload;

	}

	/**
	 * Une période au planning ouverte depuis `$from`, du lundi au vendredi. Hors `build()` : les périodes du décor ne se
	 * chevauchent pas, ce que l'étape `to.present` vérifierait.
	 */
	private function createPresence(\user\User $eUser, string $from = '2025-01-01'): void {

		\hr\PresenceLib::create(new \hr\Presence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'from' => $from,
			'to' => NULL,
			'eContract' => new \hr\Contract([
				'type' => \hr\Contract::CDI,
				'weeklyHours' => 35.0,
				'annualHours' => NULL,
				'hoursPeriod' => \hr\Contract::WEEK,
				'days' => new \Set(\hr\Contract::MONDAY | \hr\Contract::TUESDAY | \hr\Contract::WEDNESDAY | \hr\Contract::THURSDAY | \hr\Contract::FRIDAY),
				'hasVariableDays' => FALSE
			])
		]));

	}

	private function replacePresence(\user\User $eUser, string $from): void {

		$cPresence = \hr\Presence::model()
			->select('id')
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->getCollection();

		foreach($cPresence as $ePresence) {
			\hr\PresenceLib::delete($ePresence);
		}

		$this->createPresence($eUser, $from);

	}

	/**
	 * Un membre de plus, au planning du lundi au vendredi, avec le rôle de Bruno.
	 */
	private function createMember(string $firstName): \user\User {

		$eUser = new \user\User([
			'firstName' => $firstName,
			'lastName' => 'Décor',
			'email' => 'scene-team-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le domaine réservé et uniqid() assurent ce que build() vérifierait de l'adresse
		\user\UserLib::create($eUser);

		$eMember = \structure\Member::model()
			->select('role')
			->whereStructure($this->eStructure)
			->whereUser($this->eUserPlanning)
			->get();

		// Hors build(), comme le pose InviteLib::accept()
		\structure\MemberLib::create(new \structure\Member([
			'user' => $eUser,
			'structure' => $this->eStructure,
			'role' => $eMember['role'],
			'status' => \structure\Member::IN
		]));

		$this->createPresence($eUser);

		return $eUser;

	}

}
?>
