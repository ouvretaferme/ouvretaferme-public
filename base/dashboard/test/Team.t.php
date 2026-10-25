<?php
namespace dashboard;

/**
 * L'équipe en 3D de la tête de l'accueil (A8 de 3d-avatars), telle que `DashboardLib::getTeam()` la prépare pour la
 * page : qui en est, dans quel ordre, avec quelle fiche, et ce que coûte sa lecture.
 *
 * Le décor est la structure F1, ses quatre membres et leurs comptes fictifs. Leurs avatars, leurs périodes au planning
 * et leurs absences sont ceux que chaque méthode pose dans sa transaction : la dev peut en porter d'autres, que
 * `setUp()` retire d'abord. Alice, Bruno et Claire sont au planning, du lundi au vendredi ; David n'y est pas.
 *
 * La semaine est celle du lundi 19 octobre 2026, sans jour férié, et la journée le mercredi 21.
 *
 * L'équipe montrée au seul membre se juge sur `DashboardSetting::isTeam()` ; ce que le canevas en fait se joue dans Chrome.
 *
 * Lancement : dev/test package=dashboard file=Team
 */
class TeamTest extends \dev\TransactionTest {

	const STRUCTURE = \structure\FixtureAiLib::F1;

	// Une autre structure du décor, dont l'administrateur de F1 n'est pas membre
	const STRUCTURE_OTHER = \structure\FixtureAiLib::F2;

	const ADMIN = 'decor.f1.admin@example.org';
	const PLANNING = 'decor.f1.planning@example.org';
	const ACCOUNTANT = 'decor.f1.comptable@example.org';
	const OBSERVER = 'decor.f1.observateur@example.org';

	const NOW = '2026-10-21 10:00:00';

	// Un objet en main du catalogue (avatar.js, `objet()`), qu'une fiche peut encore citer : l'éditeur ne le propose plus,
	// mais une fiche enregistrée avant lui ou rendue par l'agent d'après photo peut le porter
	const OBJECT = 'panier';

	protected \structure\Structure $eStructure;

	protected \user\User $eUserAdmin;
	protected \user\User $eUserPlanning;
	protected \user\User $eUserAccountant;
	protected \user\User $eUserObserver;

	// Le drapeau des modèles téléversés tel que le processus le tient, qu'une méthode peut fermer ou ouvrir
	protected \Closure $avatarUploadInitial;

	protected function getTransactionModel(): \ModuleModel {
		return \structure\Member::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->avatarUploadInitial = \user\UserSetting::$avatarUpload;

		$this->eStructure = $this->getStructure(self::STRUCTURE);

		// Aucun avatar dans l'équipe du décor : chaque méthode pose ceux dont elle a besoin. Une écriture directe et non
		// `UserAvatarLib::delete()`, qui rendrait leur photo de profil aux comptes et retirerait du disque un modèle
		// téléversé : la transaction annulée ne rendrait pas le disque
		\user\UserAvatar::model()
			->whereUser('IN', \structure\MemberLib::getByStructure($this->eStructure, index: NULL)->getColumnCollection('user', 'user'))
			->delete();

		[$this->eUserAdmin, $this->eUserPlanning, $this->eUserAccountant, $this->eUserObserver] = [
			$this->getUser(self::ADMIN),
			$this->getUser(self::PLANNING),
			$this->getUser(self::ACCOUNTANT),
			$this->getUser(self::OBSERVER)
		];

		// Le planning et les absences du décor sont ceux de la méthode
		$cAbsence = \hr\Absence::model()
			->select('id')
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cAbsence as $eAbsence) {
			\hr\AbsenceLib::delete($eAbsence);
		}

		$cPresence = \hr\Presence::model()
			->select('id')
			->whereStructure($this->eStructure)
			->getCollection();

		foreach($cPresence as $ePresence) {
			\hr\PresenceLib::delete($ePresence);
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

		// La transaction annulée rend `hasHr` et les saisons à la base, pas aux configurations que le processus garde pour F1
		\structure\FeatureLib::forget($this->eStructure);
		\map\ConfigurationLib::forget($this->eStructure);

	}

	/**
	 * Les membres au planning de la semaine, et la personne connectée, qui a toujours sa place.
	 */
	public function testMembers(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$eUserGhost = $this->createGhost();
		$this->createPresence($eUserGhost);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals([$this->eUserAdmin['id'], $this->eUserPlanning['id'], $this->eUserAccountant['id']], $this->sortIds($team['cUser']->getIds()), "les trois membres au planning de la semaine");
		$this->assertEquals(FALSE, $team['cUser']->offsetExists($eUserGhost['id']), "une personne sans compte n'a jamais d'avatar : elle n'est pas montrée, même au planning");
		$this->assertEquals(FALSE, $team['cUser']->offsetExists($this->eUserObserver['id']), "un membre qui n'est pas au planning n'est pas montré");

		foreach($team['cUser'] as $eUser) {
			$this->assertEquals(NULL, $eUser['fiche'], "sans avatar, pas de fiche : la page peint le corps commun en gris");
		}

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserObserver, FALSE, TRUE);

		$this->assertEquals($this->eUserObserver['id'], $team['cUser']->first()['id'], "la personne connectée a sa place sans être au planning");

		// Une période qui finit avant la semaine n'y met personne
		$cPresence = \hr\Presence::model()
			->select('id')
			->whereStructure($this->eStructure)
			->whereUser($this->eUserAccountant)
			->getCollection();

		foreach($cPresence as $ePresence) {
			\hr\PresenceLib::delete($ePresence);
		}

		$this->createPresence($this->eUserAccountant, '2026-01-01', '2026-10-18');

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals(FALSE, $team['cUser']->offsetExists($this->eUserAccountant['id']), "une période finie la veille de la semaine ne met pas au planning");

	}

	public function testOrder(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserAccountant);
		$this->createAvatar($this->eUserAdmin);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE);

		$this->assertEquals([$this->eUserAdmin['id'], $this->eUserAccountant['id'], $this->eUserPlanning['id']], $team['cUser']->getIds(), "la personne connectée d'abord, puis les membres qui ont un avatar, puis les corps gris");
		$this->assertEquals(TRUE, $team['cUser'][$this->eUserAdmin['id']]['me'], "la personne connectée est désignée");
		$this->assertEquals(FALSE, $team['cUser'][$this->eUserAccountant['id']]['me'], "les autres ne le sont pas");
		$this->assertEquals(FALSE, $team['invite'], "qui a un avatar n'est pas invité à en faire un");
		$this->assertEquals(NULL, $team['cUser'][$this->eUserPlanning['id']]['fiche'], "le corps gris n'a pas de fiche");

		// Une personne que la liste des absences nomme passe devant celles qui ont un avatar
		$this->createAbsence($this->eUserPlanning, '2026-10-22 00:00:00', '2026-10-22 23:59:59');

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE);

		$this->assertEquals([$this->eUserAdmin['id'], $this->eUserPlanning['id'], $this->eUserAccountant['id']], $team['cUser']->getIds(), "la personne connectée, puis celles que la liste nomme, puis les autres");

	}

	public function testInvite(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE);

		$this->assertEquals(TRUE, $team['invite'], "sans avatar, l'éditeur ouvert : sa place porte « Faire mon avatar »");
		$this->assertEquals(FALSE, $team['cUser']->offsetExists($this->eUserAdmin['id']), "et elle n'a pas de corps gris");

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals(FALSE, $team['invite'], "sans l'éditeur, rien à proposer");
		$this->assertEquals($this->eUserAdmin['id'], $team['cUser']->first()['id'], "elle garde sa place, en corps gris");

	}

	public function testFiche(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$composition = \user\UserAvatarLib::getDefault();
		$composition['objet'] = self::OBJECT;

		$this->createAvatar($this->eUserAdmin, $composition);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE);
		$fiche = $team['cUser'][$this->eUserAdmin['id']]['fiche'];

		$catalogues = array_column($fiche['elements'], 'catalogue');

		$this->assertEquals($composition['peau'], $fiche['corps']['peau'], "la peau de la composition");
		$this->assertEquals(TRUE, in_array($composition['haut'], $catalogues, TRUE), "les entrées de la composition");
		$this->assertEquals(FALSE, in_array(self::OBJECT, $catalogues, TRUE), "aucune fiche ne porte d'objet : le moment de la journée choisit le sien");

		$catalogues = array_column($team['silhouette']['elements'], 'catalogue');

		$this->assertEquals(FALSE, in_array(self::OBJECT, $catalogues, TRUE), "le corps commun n'a pas d'objet non plus");

	}

	/**
	 * L'avatar tiré d'une photo enregistre la fiche que l'agent a rendue : l'équipe n'en reçoit que la peau et les
	 * entrées du catalogue, sans l'objet en main, comme de celle de l'éditeur.
	 */
	public function testFichePhoto(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$default = \user\UserAvatarLib::getDefault();

		$fiche = [
			'corps' => ['peau' => $default['peau'], 'note' => "Lunettes posées sur le front"],
			'elements' => [
				['catalogue' => $default['haut'], 'reglages' => ['couleur' => '#3a5f8a', 'note' => "Délavée"], 'description' => "Chemise à carreaux"],
				['catalogue' => self::OBJECT, 'reglages' => []]
			],
			'photo' => "Prise devant la serre"
		];

		$this->createAvatar($this->eUserAdmin, $fiche, \user\UserAvatar::PHOTO);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE);
		$ficheTeam = $team['cUser'][$this->eUserAdmin['id']]['fiche'];

		$this->assertEquals([
			'corps' => ['peau' => $default['peau']],
			'elements' => [
				['catalogue' => $default['haut'], 'reglages' => ['couleur' => '#3a5f8a']]
			]
		], $ficheTeam, "la peau et les entrées de la fiche rendue par l'agent, sans l'objet en main ni ce qu'elle porte d'autre");

	}

	/**
	 * Un membre qui a téléversé son modèle garde sa composition, dans le rang comme au second plan : elle est le repli du
	 * modèle que le navigateur ne lit pas. Ici le modèle n'a plus son fichier, et la tête ne le reçoit pas.
	 */
	public function testFicheUpload(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$composition = \user\UserAvatarLib::getDefault();

		$this->createAvatar($this->eUserPlanning, $composition);
		$this->createAvatar($this->eUserAccountant, $composition);
		$this->createUpload($this->eUserPlanning);
		$this->createUpload($this->eUserAccountant);

		// Claire absente toute la journée : elle passe au second plan
		$this->createAbsence($this->eUserAccountant, '2026-10-21 00:00:00', '2026-10-21 23:59:59');

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals($team['silhouette'], $team['cUser'][$this->eUserPlanning['id']]['fiche'], "dans le rang, la composition par défaut qu'il porte sous son modèle");
		$this->assertEquals(TRUE, $team['cUser'][$this->eUserAccountant['id']]['away'], "témoin : Claire est au second plan");
		$this->assertEquals($team['silhouette'], $team['cUser'][$this->eUserAccountant['id']]['fiche'], "au second plan aussi");
		$this->assertEquals(TRUE, $team['cUser'][$this->eUserPlanning['id']]['eUserAvatarUpload']->empty(), "un modèle sans fichier ne se monte pas");

	}

	/**
	 * Le modèle téléversé que la tête monte à la place de la composition : pour les `TEAM_UPLOADS_MAX` premiers qui en ont
	 * un, dans l'ordre de la tête, et pour aucun si la structure n'a pas les modèles téléversés. Chacun garde sa fiche.
	 * Qui peut voir le modèle de qui se juge dans `base/user/test/UserAvatarUploadSceneAi.t.php`.
	 */
	public function testUpload(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		\user\UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => TRUE;

		$this->createAvatar($this->eUserPlanning);
		$this->createAvatar($this->eUserAccountant);
		$this->createUpload($this->eUserPlanning, file: TRUE);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$eUserPlanning = $team['cUser'][$this->eUserPlanning['id']];

		$this->assertEquals(TRUE, $eUserPlanning['eUserAvatarUpload']->notEmpty(), "le modèle de Bruno");
		$this->assertEquals(['models' => ['avatar' => []]], $eUserPlanning['eUserAvatarUpload']['bakedIndex'], "avec l'index de sa conversion");
		$this->assertEquals(FALSE, $eUserPlanning['eUserAvatarUpload']->offsetExists('hash'), "sans le nom de son fichier");
		$this->assertEquals(TRUE, $eUserPlanning['fiche'] !== NULL, "et sa fiche, le repli");
		$this->assertEquals(TRUE, $team['cUser'][$this->eUserAccountant['id']]['eUserAvatarUpload']->empty(), "Claire n'a que sa composition");

		// Au-delà du plafond, les suivants gardent leur composition, dans l'ordre de la tête
		foreach(range(1, DashboardSetting::TEAM_UPLOADS_MAX) as $position) {
			$eUser = $this->createMember('Membre '.$position);
			$this->createAvatar($eUser);
			$this->createUpload($eUser, file: TRUE);
		}

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$uploads = $team['cUser']->find(fn(\user\User $eUser) => $eUser['eUserAvatarUpload']->notEmpty());

		$this->assertEquals(DashboardSetting::TEAM_UPLOADS_MAX, $uploads->count(), "pas plus de modèles que le plafond");
		$this->assertEquals(array_slice($team['cUser']->find(fn(\user\User $eUser) => $eUser['fiche'] !== NULL)->getIds(), 0, DashboardSetting::TEAM_UPLOADS_MAX), $uploads->getIds(), "les premiers de la tête");

		\user\UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => FALSE;

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals(0, $team['cUser']->find(fn(\user\User $eUser) => $eUser['eUserAvatarUpload']->notEmpty())->count(), "aucun modèle sans le drapeau de la structure");

	}

	/**
	 * La liste des absences : une ligne par personne au planning dont l'absence de la semaine n'est pas finie,
	 * « toute la semaine » seulement quand chaque jour de son contrat est pris, passés compris, sinon ses jours restants.
	 */
	public function testAbsences(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		// Bruno du lundi au vendredi : toute la semaine, et au second plan aujourd'hui
		$this->createAbsence($this->eUserPlanning, '2026-10-19 00:00:00', '2026-10-23 23:59:59');

		// Claire du mardi au vendredi : le lundi travaillé lui retire « toute la semaine »
		$this->createAbsence($this->eUserAccountant, '2026-10-20 00:00:00', '2026-10-23 23:59:59');

		// Alice le lundi seul, absence finie ; jeudi après-midi ; vendredi en heures
		$this->createAbsence($this->eUserAdmin, '2026-10-19 00:00:00', '2026-10-19 23:59:59');
		$this->createAbsence($this->eUserAdmin, '2026-10-22 '.\hr\AbsenceLib::NOON, '2026-10-22 '.\hr\AbsenceLib::DAY_END, duration: 0.5);
		$this->createAbsence($this->eUserAdmin, '2026-10-23 09:00:00', '2026-10-23 11:00:00', \hr\Absence::HOUR, duration: 2.0);

		// David n'est pas au planning : son absence n'a pas de ligne
		$this->createAbsence($this->eUserObserver, '2026-10-21 00:00:00', '2026-10-21 23:59:59');

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);
		$cUserAbsent = $team['cUserAbsent'];

		$this->assertEquals([$this->eUserPlanning['id'], $this->eUserAccountant['id'], $this->eUserAdmin['id']], $cUserAbsent->getIds(), "aujourd'hui d'abord, à jour égal par prénom, puis le jeudi");

		$eUserPlanning = $cUserAbsent[$this->eUserPlanning['id']];

		$this->assertEquals(TRUE, $eUserPlanning['absenceWeek'], "Bruno, toute la semaine");
		$this->assertEquals(['2026-10-21', '2026-10-22', '2026-10-23'], array_keys($eUserPlanning['absenceDays']), "ses jours restants, aujourd'hui compris");
		$this->assertEquals(TRUE, $eUserPlanning['absenceAway'], "absent toute la journée aujourd'hui");

		$eUserAccountant = $cUserAbsent[$this->eUserAccountant['id']];

		$this->assertEquals(FALSE, $eUserAccountant['absenceWeek'], "Claire était là lundi : pas toute la semaine");
		$this->assertEquals(['2026-10-21', '2026-10-22', '2026-10-23'], array_keys($eUserAccountant['absenceDays']), "ses jours restants nommés");

		$eUserAdmin = $cUserAbsent[$this->eUserAdmin['id']];

		$this->assertEquals([
			'2026-10-22' => DashboardSetting::ABSENCE_AFTERNOON,
			'2026-10-23' => DashboardSetting::ABSENCE_HOURS
		], $eUserAdmin['absenceDays'], "le lundi passé n'est plus dit, le jeudi après-midi, le vendredi en heures");
		$this->assertEquals(['2026-10-23' => [['from' => '09:00', 'to' => '11:00']]], $eUserAdmin['absenceHours'], "le vendredi dit sa plage");
		$this->assertEquals(FALSE, $eUserAdmin['absenceAway'], "présente aujourd'hui");

		$this->assertEquals(TRUE, $team['cUser'][$this->eUserPlanning['id']]['away'], "Bruno au second plan");
		$this->assertEquals(FALSE, $team['cUser'][$this->eUserAdmin['id']]['away'], "Alice dans le rang");
		$this->assertEquals(TRUE, $team['cUser'][$this->eUserAdmin['id']]['absent'], "et nommée dans la liste");

	}

	/**
	 * « Toute la semaine » se compte sur les jours du contrat : Claire, au contrat du mardi au vendredi, absente ces
	 * quatre jours, l'est toute la semaine.
	 */
	public function testAbsencesContract(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->replacePresence($this->eUserAccountant, \hr\Contract::TUESDAY | \hr\Contract::WEDNESDAY | \hr\Contract::THURSDAY | \hr\Contract::FRIDAY);
		$this->createAbsence($this->eUserAccountant, '2026-10-20 00:00:00', '2026-10-23 23:59:59');

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals(TRUE, $team['cUserAbsent'][$this->eUserAccountant['id']]['absenceWeek'], "chaque jour de son contrat est pris");

		// Un jour hors contrat ne se dit pas : le lundi n'est pas travaillé
		$this->replacePresence($this->eUserPlanning, \hr\Contract::TUESDAY | \hr\Contract::WEDNESDAY);
		$this->createAbsence($this->eUserPlanning, '2026-10-22 00:00:00', '2026-10-23 23:59:59');

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals(FALSE, $team['cUserAbsent']->offsetExists($this->eUserPlanning['id']), "une absence qui ne tombe sur aucun jour de son contrat n'a pas de ligne");

		// Une période sans contrat travaille du lundi au vendredi
		$cPresence = \hr\Presence::model()
			->select('id')
			->whereStructure($this->eStructure)
			->whereUser($this->eUserAdmin)
			->getCollection();

		foreach($cPresence as $ePresence) {
			\hr\PresenceLib::delete($ePresence);
		}

		// Hors build() : l'étape `to.present` vérifierait que la période ne chevauche aucune autre, ce que la suppression
		// ci-dessus garantit
		\hr\PresenceLib::create(new \hr\Presence([
			'structure' => $this->eStructure,
			'user' => $this->eUserAdmin,
			'from' => '2025-01-01',
			'to' => NULL
		]));

		$this->createAbsence($this->eUserAdmin, '2026-10-19 00:00:00', '2026-10-23 23:59:59');

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserObserver, FALSE, TRUE);

		$this->assertEquals(TRUE, $team['cUserAbsent'][$this->eUserAdmin['id']]['absenceWeek'], "sans contrat, absente du lundi au vendredi : toute la semaine");

	}

	/**
	 * Une demi-journée se dit « matin » ou « après-midi », et le matin et l'après-midi posés à part font la journée.
	 */
	public function testAbsencesHalfDay(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAbsence($this->eUserPlanning, '2026-10-22 '.\hr\AbsenceLib::DAY_START, '2026-10-22 '.\hr\AbsenceLib::NOON, duration: 0.5);

		$this->createAbsence($this->eUserAccountant, '2026-10-21 '.\hr\AbsenceLib::DAY_START, '2026-10-21 '.\hr\AbsenceLib::NOON, duration: 0.5);
		$this->createAbsence($this->eUserAccountant, '2026-10-21 '.\hr\AbsenceLib::NOON, '2026-10-21 '.\hr\AbsenceLib::DAY_END, duration: 0.5);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals(['2026-10-22' => DashboardSetting::ABSENCE_MORNING], $team['cUserAbsent'][$this->eUserPlanning['id']]['absenceDays'], "Bruno jeudi matin");
		$this->assertEquals(['2026-10-21' => DashboardSetting::ABSENCE_DAY], $team['cUserAbsent'][$this->eUserAccountant['id']]['absenceDays'], "Claire aujourd'hui, matin et après-midi : la journée");
		$this->assertEquals(TRUE, $team['cUserAbsent'][$this->eUserAccountant['id']]['absenceAway'], "elle passe au second plan");

	}

	/**
	 * Une absence en heures dit ses plages, dans l'ordre du jour ; une demi-journée posée le même jour laisse le jour seul.
	 */
	public function testAbsencesHours(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAbsence($this->eUserPlanning, '2026-10-22 14:00:00', '2026-10-22 16:00:00', \hr\Absence::HOUR, duration: 2.0);
		$this->createAbsence($this->eUserPlanning, '2026-10-22 08:30:00', '2026-10-22 10:00:00', \hr\Absence::HOUR, duration: 1.5);

		$this->createAbsence($this->eUserAccountant, '2026-10-23 '.\hr\AbsenceLib::DAY_START, '2026-10-23 '.\hr\AbsenceLib::NOON, duration: 0.5);
		$this->createAbsence($this->eUserAccountant, '2026-10-23 14:00:00', '2026-10-23 16:00:00', \hr\Absence::HOUR, duration: 2.0);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals(['2026-10-22' => [['from' => '08:30', 'to' => '10:00'], ['from' => '14:00', 'to' => '16:00']]], $team['cUserAbsent'][$this->eUserPlanning['id']]['absenceHours'], "Bruno jeudi, deux plages dans l'ordre du jour");
		$this->assertEquals(['2026-10-23' => DashboardSetting::ABSENCE_HOURS], $team['cUserAbsent'][$this->eUserAccountant['id']]['absenceDays'], "témoin : Claire vendredi, le matin et des heures");
		$this->assertEquals(['2026-10-23' => []], $team['cUserAbsent'][$this->eUserAccountant['id']]['absenceHours'], "aucune plage, le jour seul");

	}

	/**
	 * La liste nomme quatre absences au plus, les premières de son ordre, et compte les autres (arbitrage 26). Le second
	 * plan et l'ordre du rang lisent toujours toutes les absences.
	 */
	public function testAbsencesListed(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAbsence($this->eUserAdmin, '2026-10-21 00:00:00', '2026-10-21 23:59:59');
		$this->createAbsence($this->eUserPlanning, '2026-10-22 00:00:00', '2026-10-22 23:59:59');
		$this->createAbsence($this->eUserAccountant, '2026-10-23 00:00:00', '2026-10-23 23:59:59');

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserObserver, FALSE, TRUE);

		$this->assertEquals(3, $team['cUserAbsentListed']->count(), "trois absences, toutes nommées");
		$this->assertEquals(0, $team['absentOthers'], "aucune à compter");

		$eUserFourth = $this->createMember('Quatrième');
		$eUserFifth = $this->createMember('Cinquième');

		$this->createAbsence($eUserFourth, '2026-10-23 00:00:00', '2026-10-23 23:59:59');
		$this->createAbsence($eUserFifth, '2026-10-23 '.\hr\AbsenceLib::NOON, '2026-10-23 '.\hr\AbsenceLib::DAY_END, duration: 0.5);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserObserver, FALSE, TRUE);

		$this->assertEquals(5, $team['cUserAbsent']->count(), "témoin : cinq absences");
		$this->assertEquals(array_slice($team['cUserAbsent']->getIds(), 0, DashboardSetting::TEAM_ABSENCES_MAX), $team['cUserAbsentListed']->getIds(), "les quatre premières dans l'ordre du premier jour");
		$this->assertEquals(1, $team['absentOthers'], "la cinquième se compte");
		$this->assertEquals(FALSE, $team['cUserAbsentListed']->offsetExists($eUserFourth['id']), "témoin : Quatrième n'est pas nommée");
		$this->assertEquals(TRUE, $team['cUser'][$eUserFourth['id']]['absent'], "le rang lit toutes les absences, Quatrième comprise");

	}

	/**
	 * Le compte des autres ne mène à l'onglet Absences du planning que si celui-ci accepte la semaine : il la refuse
	 * hors des saisons de la structure (`production/planning/page/absence.p.php`).
	 */
	public function testAbsencesOthersLink(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserObserver, FALSE, TRUE);

		$this->assertEquals(TRUE, $team['absentOthersLink'], "la semaine est dans les saisons, le compte mène au planning");

		// Deux saisons qui finissent avant la marge du calendrier : l'année de la semaine sort des saisons valides
		$seasonLast = week_year(\Clock::week()) - \structure\StructureSetting::CALENDAR_LIMIT - 1;
		\map\ConfigurationLib::updateProperties($this->eStructure, ['seasonFirst' => $seasonLast - 1, 'seasonLast' => $seasonLast]);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserObserver, FALSE, TRUE);

		$this->assertEquals(FALSE, $team['absentOthersLink'], "hors des saisons, le compte reste en texte");

	}

	/**
	 * Seules les absences validées se disent, et rien sans le droit de les voir au planning ni quand la structure n'a pas
	 * les ressources humaines.
	 */
	public function testAbsencesValidated(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAbsence($this->eUserPlanning, '2026-10-21 00:00:00', '2026-10-21 23:59:59', status: \hr\Absence::REFUSED);
		$this->createAbsence($this->eUserAccountant, '2026-10-21 00:00:00', '2026-10-21 23:59:59');

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals([$this->eUserAccountant['id']], $team['cUserAbsent']->getIds(), "une absence refusée n'a pas de ligne");

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, FALSE);

		$this->assertEquals(0, $team['cUserAbsent']->count(), "sans le droit de voir les absences au planning, aucune absence");
		$this->assertEquals(FALSE, $team['cUser'][$this->eUserAccountant['id']]['away'], "et personne au second plan");

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', FALSE);

		$team = DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, FALSE, TRUE);

		$this->assertEquals(0, $team['cUserAbsent']->count(), "sans les ressources humaines, aucune absence");
		$this->assertEquals(FALSE, $team['cUser'][$this->eUserAccountant['id']]['away'], "et personne au second plan");

	}

	/**
	 * L'équipe n'est montrée qu'à un membre de la structure : l'accueil d'une structure dont on n'est pas membre, une
	 * école ou un partage en communauté, n'en montre rien, même drapeaux ouverts.
	 */
	public function testMemberOnly(): void {

		$eUserAdmin = $this->eUserAdmin;
		$eStructureAlone = $this->getStructure(self::STRUCTURE_OTHER);

		if($this->assertDecorPresent($eUserAdmin->notEmpty() and $eStructureAlone->notEmpty(), "le compte ".self::ADMIN." et la structure F2 du décor") === FALSE) {
			return;
		}

		[$eUserInitial, $enabledInitial, $teamInitial] = [\user\ConnectionLib::getOnline(), DashboardSetting::$enabled, DashboardSetting::$team];

		DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => TRUE;
		DashboardSetting::$team = fn(\structure\Structure $eStructure): bool => TRUE;
		\user\ConnectionLib::setOnline($eUserAdmin);
		\structure\MemberLib::clearCache();

		try {

			$this->assertEquals(TRUE, DashboardSetting::isTeam($this->eStructure), "membre de F1, l'administrateur voit l'équipe de F1");
			$this->assertEquals(FALSE, $eStructureAlone->isMember(), "témoin : l'administrateur de F1 n'est pas membre de F2");
			$this->assertEquals(FALSE, DashboardSetting::isTeam($eStructureAlone), "il ne voit pas celle de F2");

		} finally {

			DashboardSetting::$enabled = $enabledInitial;
			DashboardSetting::$team = $teamInitial;
			\user\ConnectionLib::setOnline($eUserInitial);
			\structure\MemberLib::clearCache();

		}

	}

	/**
	 * Un nombre de requêtes qui ne dépend pas de l'effectif : les membres, leurs comptes (la sélection de `Member` les lit
	 * à part), leurs périodes de la semaine (deux aussi, pour la même raison), leurs absences, puis leurs avatars et leurs
	 * modèles téléversés. Les contrats ne se lisent que lorsqu'une absence tombe dans la semaine, en une fois pour toutes
	 * les personnes absentes.
	 */
	public function testQueries(): void {

		$this->setClock(self::NOW);

		if($this->assertDecor() === FALSE) {
			return;
		}

		$this->createAvatar($this->eUserAdmin);

		// Les rôles, les drapeaux et le catalogue se gardent pour le processus : un premier passage les lit, que la
		// mesure ne compte pas
		DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE);

		$queries = $this->countQueries(fn() => DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE));

		$this->assertEquals(7, $queries, "les membres et leurs comptes, leurs périodes et leurs comptes, leurs absences, puis leurs avatars et leurs modèles téléversés");

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', FALSE);
		DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE);

		$this->assertEquals(6, $this->countQueries(fn() => DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE)), "sans les ressources humaines, les absences ne se lisent pas");

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', TRUE);
		DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE);

		$this->createAbsence($this->eUserPlanning, '2026-10-21 00:00:00', '2026-10-21 23:59:59');

		$queriesAbsent = $this->countQueries(fn() => DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE));

		$this->createAbsence($this->eUserAccountant, '2026-10-22 00:00:00', '2026-10-22 23:59:59');
		$this->createAbsence($this->eUserAdmin, '2026-10-23 00:00:00', '2026-10-23 23:59:59');

		$queriesAbsents = $this->countQueries(fn() => DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE));

		$this->assertEquals(TRUE, $queriesAbsent > $queries, "témoin : une absence fait lire les contrats");
		$this->assertEquals($queriesAbsent, $queriesAbsents, "autant pour trois personnes absentes que pour une");

		// Un modèle téléversé fait lire la règle de son auteur, puis autant de requêtes quel que soit le nombre de modèles
		\user\UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => TRUE;

		$this->createAvatar($this->eUserPlanning);
		$this->createUpload($this->eUserPlanning, file: TRUE);

		$queriesUpload = $this->countQueries(fn() => DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE));

		foreach(range(1, DashboardSetting::TEAM_UPLOADS_MAX) as $position) {
			$eUser = $this->createMember('Membre '.$position);
			$this->createAvatar($eUser);
			$this->createUpload($eUser, file: TRUE);
		}

		$queriesUploads = $this->countQueries(fn() => DashboardLib::getTeam($this->eStructure, $this->eUserAdmin, TRUE, TRUE));

		$this->assertEquals(TRUE, $queriesUpload > $queriesAbsents, "témoin : un modèle fait lire la règle de son auteur");
		$this->assertEquals($queriesUpload, $queriesUploads, "autant pour sept modèles que pour un");

	}

	private function assertDecor(): bool {

		return $this->assertDecorPresent(
			$this->eStructure->notEmpty() and
			$this->eUserAdmin->notEmpty() and
			$this->eUserPlanning->notEmpty() and
			$this->eUserAccountant->notEmpty() and
			$this->eUserObserver->notEmpty(),
			"la structure F1 et ses quatre comptes du décor"
		);

	}

	private function sortIds(array $ids): array {

		sort($ids);

		return $ids;

	}

	private function getStructure(int $id): \structure\Structure {

		return \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId($id)
			->get();

	}

	private function getUser(string $email): \user\User {

		return \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

	}

	private function createAvatar(\user\User $eUser, ?array $composition = NULL, string $source = \user\UserAvatar::EDITOR): void {

		\user\UserAvatarLib::create(new \user\UserAvatar([
			'user' => $eUser,
			'source' => $source,
			'composition' => $composition ?? \user\UserAvatarLib::getDefault(),
			'replacesPhoto' => FALSE
		]));

	}

	/**
	 * Un modèle téléversé montré à la place de la composition, avec son fichier si `$file`, sinon tel qu'il reste une fois
	 * retiré. Hors `UserAvatarUploadLib::create()`, qui envoie le binaire converti au stockage des médias, que la
	 * transaction annulée ne rendrait pas : la tête ne lit que l'envoi, et un hash qui ne désigne aucun fichier lui suffit.
	 */
	private function createUpload(\user\User $eUser, bool $file = FALSE): void {

		$eUserAvatarUpload = new \user\UserAvatarUpload([
			'user' => $eUser,
			'hash' => $file ? substr(md5(uniqid()), 0, 20) : NULL,
			'bakedIndex' => $file ? ['models' => ['avatar' => []]] : NULL,
			'rules' => 1,
			'status' => \user\UserAvatarUpload::NEW
		]);

		\user\UserAvatarUpload::model()->insert($eUserAvatarUpload);

		$eUserAvatar = \user\UserAvatarLib::getByUser($eUser);
		$eUserAvatar['upload'] = $eUserAvatarUpload;

		\user\UserAvatarLib::update($eUserAvatar, ['upload']);

	}

	/**
	 * Une période au planning, ouverte depuis 2025 sauf bornes données, sous un contrat des jours `$days`. Hors
	 * `build()` : les périodes du décor ne se chevauchent pas, ce que l'étape `to.present` vérifierait.
	 */
	private function createPresence(\user\User $eUser, string $from = '2025-01-01', ?string $to = NULL, int $days = \hr\Contract::MONDAY | \hr\Contract::TUESDAY | \hr\Contract::WEDNESDAY | \hr\Contract::THURSDAY | \hr\Contract::FRIDAY): void {

		\hr\PresenceLib::create(new \hr\Presence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'from' => $from,
			'to' => $to,
			'eContract' => new \hr\Contract([
				'type' => \hr\Contract::CDI,
				'weeklyHours' => 35.0,
				'annualHours' => NULL,
				'hoursPeriod' => \hr\Contract::WEEK,
				'days' => new \Set($days),
				'hasVariableDays' => FALSE
			])
		]));

	}

	/**
	 * Un membre de plus, au planning du lundi au vendredi, avec le rôle de Bruno.
	 */
	private function createMember(string $firstName): \user\User {

		$eUser = new \user\User([
			'firstName' => $firstName,
			'lastName' => 'Décor',
			'email' => 'dashboard-team-'.uniqid().'@example.org',
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

	private function replacePresence(\user\User $eUser, int $days): void {

		$cPresence = \hr\Presence::model()
			->select('id')
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->getCollection();

		foreach($cPresence as $ePresence) {
			\hr\PresenceLib::delete($ePresence);
		}

		$this->createPresence($eUser, days: $days);

	}

	/**
	 * Hors `build()` : une absence se pose ici comme le formulaire la compose, une borne à midi pour une demi-journée,
	 * et avec le statut que la page donne selon qui saisit, ce que le test veut choisir.
	 */
	private function createAbsence(\user\User $eUser, string $from, string $to, string $unit = \hr\Absence::DAY, string $status = \hr\Absence::VALIDATED, float $duration = 1.0): void {

		\hr\AbsenceLib::create(new \hr\Absence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'type' => \hr\Absence::VACATION,
			'unit' => $unit,
			'status' => $status,
			'from' => $from,
			'to' => $to,
			'duration' => $duration
		]));

	}

	/**
	 * Un membre fantôme présent, comme la production en compte : `createGhostUser()` le pose sorti, et c'est le passage
	 * à `IN` qui le met dans le champ de `MemberLib::getByStructure()`. Une écriture directe, parce que le produit ne
	 * fait jamais entrer un fantôme par un geste que ce test pourrait jouer.
	 */
	private function createGhost(): \user\User {

		$eUser = \structure\MemberLib::getNewGhostUser();
		$eUser['firstName'] = 'Fantôme';
		$eUser['lastName'] = 'Décor';

		\structure\MemberLib::createGhostUser($this->eStructure, $eUser);

		\structure\Member::model()
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->update(['status' => \structure\Member::IN]);

		return $eUser;

	}

}
?>
