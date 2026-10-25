<?php
namespace structure;

/**
 * L'invitation d'une personne sans compte la désigne (`Invite.member`), et le compte qui l'accepte
 * reprend son passé : la fiche d'équipe, les périodes, les absences, le temps de travail, les temps et
 * l'historique des interventions et les interventions où elle était prévue. La personne sans compte
 * disparait, l'équipe ne compte qu'une personne.
 *
 * Décor : la ferme F1, une personne sans compte créée par le test, un compte en @example.org.
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=structure file=InviteGhostAi
 */
class InviteGhostAiTest extends \dev\TransactionTest {

	/**
	 * Un refus à l'acceptation annule toute la pile de transactions (`InviteLib::accept()`), celle de
	 * setUp() comprise : les tests qui l'éprouvent ne lisent plus rien du décor ensuite.
	 */
	const CLOSED_BY_TEST = TRUE;

	protected Structure $eStructure;

	protected function getTransactionModel(): \ModuleModel {
		return new MemberModel();
	}

	public function init(): void {

		$this->eStructure = Structure::model()
			->select(Structure::getSelection())
			->whereId(FixtureAiLib::F1)
			->get();

	}

	public function testCreateRefusals(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eMemberGhost = $this->createGhost($this->eStructure, 'Refus');

		$eStructureOther = Structure::model()
			->select(Structure::getSelection())
			->whereId(FixtureAiLib::F2)
			->get();

		$eMemberGhostOther = $this->createGhost($eStructureOther, 'Ailleurs');
		$eMemberAccount = $this->createAccount('Membre', $this->eStructure);

		$this->assertBuildFails($eMemberGhostOther, 'ghost-other@example.org', 'Invite::member.check', 'une personne sans compte d\'une autre structure se désigne');
		$this->assertBuildFails($eMemberAccount, 'ghost-account@example.org', 'Invite::member.check', 'un membre avec un compte se désigne comme une personne sans compte');
		$this->assertBuildFails($eMemberGhost, NULL, 'Invite::email.required', 'une invitation qui désigne une personne sans compte part sans adresse');
		$this->assertBuildFails($eMemberGhost, $eMemberAccount['user']['email'], 'Invite::email.duplicateMember', 'l\'adresse d\'un membre de l\'équipe reçoit l\'invitation');

		// Un compte qui n'est plus membre mais garde sa fiche d'équipe ne peut pas recevoir celle d'une autre personne.
		$eMemberFormer = $this->createAccount('Ancien', $this->eStructure);
		$this->createTeammate($eMemberFormer['user'], '2026-01-01');
		MemberLib::deleteInternal($eMemberFormer);

		$this->assertBuildFails($eMemberGhost, $eMemberFormer['user']['email'], 'Invite::email.duplicateTeammate', 'un compte qui a déjà sa fiche d\'équipe reçoit celle de la personne sans compte');

		$this->createInvite($eMemberGhost, 'ghost-first-'.uniqid().'@example.org');

		$this->assertBuildFails($eMemberGhost, 'ghost-second@example.org', 'Invite::member.duplicate', 'une seconde invitation part pour la même personne');

	}

	/**
	 * Une invitation expirée ne s'accepte plus : elle n'empêche pas d'en envoyer une autre.
	 */
	public function testCreateAfterExpired(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eMemberGhost = $this->createGhost($this->eStructure, 'Expirée');

		$eInvite = $this->createInvite($eMemberGhost, 'ghost-expired-'.uniqid().'@example.org');

		$eInvite['expiresAt'] = '2026-06-14';
		InviteLib::update($eInvite, ['expiresAt']);

		$this->assertTrue(InviteLib::getPendingByMember($eMemberGhost)->empty(), 'une invitation expirée compte encore comme en cours');

		$this->createInvite($eMemberGhost, 'ghost-renewed-'.uniqid().'@example.org');

	}

	public function testAcceptTransfers(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUserAccount = $this->createUser('Rattaché');
		$eMemberGhost = $this->createGhost($this->eStructure, 'Rattaché');
		$eUserGhost = $eMemberGhost['user'];

		\user\ConnectionLib::setOnline($eUserAccount);

		$this->createTeammate($eUserGhost, '2026-01-01');
		$this->createAbsence($eUserGhost, '2026-05-04 00:00:00', '2026-05-05 23:59:59');
		$this->createWorkingTime($eUserGhost, '2026-06-01');

		$eMemberColleague = $this->createAccount('Collègue', $this->eStructure);
		$eUserColleague = $eMemberColleague['user'];

		// Hors build() : la vue du planning se règle à l'écran sur une personne de l'équipe, que le décor pose déjà.
		$eMemberColleague['viewPlanningUserDaily'] = $eUserGhost;
		MemberLib::update($eMemberColleague, ['viewPlanningUserDaily']);
		$eTask = $this->createTask([$eUserGhost['id'], $eUserColleague['id']]);
		$eTaskBoth = $this->createTask([$eUserGhost['id'], $eUserAccount['id']]);
		$this->createTimesheet($eTask, $eUserGhost, '2026-06-02');

		$eInvite = $this->createInvite($eMemberGhost, $eUserAccount['email']);

		$accepted = InviteLib::accept(InviteLib::getByKey($eInvite['key']), $eUserAccount);

		\user\ConnectionLib::setOnline(new \user\User());

		if($this->assertTrue($accepted, 'l\'invitation acceptée échoue') === FALSE) {
			return;
		}

		$counts = fn(\user\User $eUser) => [
			'teammate' => \hr\Teammate::model()->whereStructure($this->eStructure)->whereUser($eUser)->count(),
			'presence' => \hr\Presence::model()->whereStructure($this->eStructure)->whereUser($eUser)->count(),
			'absence' => \hr\Absence::model()->whereStructure($this->eStructure)->whereUser($eUser)->count(),
			'workingTime' => \hr\WorkingTime::model()->whereStructure($this->eStructure)->whereUser($eUser)->count(),
			'timesheet' => \series\Timesheet::model()->whereStructure($this->eStructure)->whereUser($eUser)->count(),
			'history' => \series\History::model()->whereStructure($this->eStructure)->whereWorker($eUser)->count()
		];

		$this->assertEquals(['teammate' => 1, 'presence' => 1, 'absence' => 1, 'workingTime' => 1, 'timesheet' => 1, 'history' => 1], $counts($eUserAccount), 'le compte ne reprend pas tout le passé de la personne sans compte');
		$this->assertEquals(['teammate' => 0, 'presence' => 0, 'absence' => 0, 'workingTime' => 0, 'timesheet' => 0, 'history' => 0], $counts($eUserGhost), 'la personne sans compte garde une part de son passé');

		$plannedUsers = fn(\series\Task $eTask) => \series\Task::model()->select('plannedUsers')->whereId($eTask)->get()['plannedUsers'];

		$this->assertEquals($this->sorted([$eUserColleague['id'], $eUserAccount['id']]), $this->sorted($plannedUsers($eTask)), 'l\'intervention ne prévoit pas le compte à la place de la personne sans compte');
		$this->assertEquals([$eUserAccount['id']], $this->sorted($plannedUsers($eTaskBoth)), 'le compte déjà prévu l\'est deux fois');

		$this->assertTrue(MemberLib::getGhostById($this->eStructure, $eMemberGhost['id'])->empty(), 'la personne sans compte reste dans l\'équipe');
		$this->assertEquals(\user\User::CLOSED, \user\User::model()->select('status')->whereId($eUserGhost)->get()['status'], 'le compte de la personne sans compte reste ouvert');
		$this->assertEquals($eUserAccount['id'], Member::model()->select('viewPlanningUserDaily')->whereId($eMemberColleague)->get()['viewPlanningUserDaily']['id'], 'le planning d\'un collègue affiche encore la personne sans compte');

		$cMember = MemberLib::getByUsers($this->eStructure, new \Collection([$eUserAccount]));
		$this->assertEquals(Member::IN, $cMember->empty() ? NULL : $cMember->first()['status'], 'le compte n\'entre pas dans la structure');

		$this->assertEquals(Invite::INVALID, Invite::model()->select('status')->whereId($eInvite)->get()['status'], 'l\'invitation reste en cours');

	}

	/**
	 * Le compte a reçu sa propre fiche d'équipe entre l'envoi et l'acceptation : le transfert refuse sans
	 * rien écrire.
	 */
	public function testTransferConflict(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eMemberAccount = $this->createAccount('Conflit', $this->eStructure);
		$eMemberGhost = $this->createGhost($this->eStructure, 'Conflit');

		$this->createTeammate($eMemberGhost['user'], '2026-01-01');
		$this->createWorkingTime($eMemberAccount['user'], '2026-06-01');

		$fw = new \FailWatch();

		MemberLib::transferGhost($eMemberGhost, $eMemberAccount['user']);

		$this->assertTrue($fw->has('Member::ghostConflict'), 'le transfert vers un compte qui a déjà du temps de travail passe');
		$this->assertTrue(MemberLib::getGhostById($this->eStructure, $eMemberGhost['id'])->notEmpty(), 'la personne sans compte disparait sur un refus');
		$this->assertEquals(1, \hr\Teammate::model()->whereStructure($this->eStructure)->whereUser($eMemberGhost['user'])->count(), 'la fiche d\'équipe quitte la personne sans compte sur un refus');

	}

	/**
	 * À l'acceptation, le refus du transfert refuse l'invitation et dit pourquoi. L'annulation de
	 * `InviteLib::accept()` défait toute la pile, décor compris : ce qui reste en base ne se lit plus ici,
	 * et `testTransferConflict` éprouve que le refus n'écrit rien.
	 */
	public function testAcceptConflict(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUserAccount = $this->createUser('Conflit');
		$eMemberGhost = $this->createGhost($this->eStructure, 'Conflit');

		$this->createTeammate($eMemberGhost['user'], '2026-01-01');

		$eInvite = $this->createInvite($eMemberGhost, $eUserAccount['email']);

		$this->createWorkingTime($eUserAccount, '2026-06-01');

		$fw = new \FailWatch();

		$accepted = InviteLib::accept(InviteLib::getByKey($eInvite['key']), $eUserAccount);

		$this->assertFalse($accepted, 'l\'invitation s\'accepte sur un compte qui a déjà du temps de travail');
		$this->assertTrue($fw->has('Member::ghostConflict'), 'le refus ne dit pas pourquoi');

	}

	/**
	 * Supprimer la personne sans compte retire l'invitation qui la désignait : elle ne s'accepte plus.
	 * Une suppression qui croise l'acceptation est refusée par `Member::ghostGone`, sous verrou.
	 */
	public function testDeleteGhostInvalidatesInvite(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eUserAccount = $this->createUser('Disparue');
		$eMemberGhost = $this->createGhost($this->eStructure, 'Disparue');

		$eInvite = $this->createInvite($eMemberGhost, $eUserAccount['email']);

		MemberLib::deleteGhostUser($this->eStructure, $eMemberGhost['user']);

		$this->assertEquals(Invite::INVALID, Invite::model()->select('status')->whereId($eInvite)->get()['status'], 'l\'invitation d\'une personne supprimée reste en cours');

		$fw = new \FailWatch();

		$this->assertFalse(MemberLib::transferGhost($eMemberGhost, $eUserAccount), 'le passé d\'une personne supprimée se transfère');
		$this->assertTrue($fw->has('Member::ghostGone'), 'le refus ne dit pas pourquoi');

	}

	protected function assertBuildFails(Member $eMember, ?string $email, string $fail, string $message): void {

		$fw = new \FailWatch();

		$this->buildInvite($eMember, $email);

		$this->assertTrue($fw->has($fail), $message.' ('.($fw->ko() ? $fw->getLast() : 'aucun refus').')');

	}

	protected function buildInvite(Member $eMember, ?string $email): Invite {

		$eInvite = new Invite([
			'structure' => $this->eStructure,
			'member' => new Member(),
			'type' => Invite::MEMBER,
			'cRole' => RoleLib::getByStructure($this->eStructure),
			'cSection' => new \Collection(),
			'sendMail' => FALSE
		]);

		$eInvite->build(InviteLib::getPropertiesCreate()($eInvite), [
			'memberRole' => RoleLib::getByStructure($this->eStructure, withAdmin: FALSE)->first()['id'],
			'member' => $eMember['id'],
			'email' => $email
		]);

		return $eInvite;

	}

	/**
	 * Par `build()`, comme le formulaire de l'équipe.
	 */
	protected function createInvite(Member $eMember, string $email): Invite {

		$fw = new \FailWatch();

		$eInvite = $this->buildInvite($eMember, $email);

		if($fw->ko()) {
			throw new \Exception('L\'invitation est refusée : '.$fw->getLast());
		}

		InviteLib::create($eInvite);

		return $eInvite;

	}

	protected function createGhost(Structure $eStructure, string $name): Member {

		$eUser = MemberLib::getNewGhostUser()->merge([
			'firstName' => 'Rattachement',
			'lastName' => $name
		]);

		// Hors build() : un nom que rien ne refuse, comme le pose le panneau de création.
		MemberLib::createGhostUser($eStructure, $eUser);

		$eMember = Member::model()
			->select(Member::getSelection())
			->whereStructure($eStructure)
			->whereUser($eUser)
			->get();

		$eMember['structure'] = $eStructure;

		return $eMember;

	}

	protected function createUser(string $name): \user\User {

		$eUser = new \user\User([
			'firstName' => 'Rattachement',
			'lastName' => $name,
			'email' => 'structure-invite-ghost-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le domaine réservé et uniqid() assurent ce que build() vérifierait de l'adresse.
		\user\UserLib::create($eUser);

		return $eUser;

	}

	protected function createAccount(string $name, Structure $eStructure): Member {

		$eMember = new Member([
			'user' => $this->createUser($name),
			'structure' => $eStructure,
			'role' => RoleLib::getByStructure($eStructure, withAdmin: FALSE)->first(),
			'status' => Member::IN
		]);

		// Hors build(), comme le pose InviteLib::accept().
		MemberLib::create($eMember);

		return $eMember;

	}

	protected function createTeammate(\user\User $eUser, string $from): void {

		// Hors build() : l'étape `member` de `Teammate::user` lit une adhésion que le décor pose à part.
		\hr\TeammateLib::create(new \hr\Teammate([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'ePresence' => new \hr\Presence([
				'from' => $from,
				'to' => NULL,
				'eContract' => new \hr\Contract()
			])
		]));

	}

	protected function createAbsence(\user\User $eUser, string $from, string $to): void {

		// Hors build() : le décor veut une absence déjà validée, que le panneau ne pose qu'en demande.
		\hr\AbsenceLib::create(new \hr\Absence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'type' => \hr\Absence::VACATION,
			'unit' => \hr\Absence::DAY,
			'status' => \hr\Absence::VALIDATED,
			'from' => $from,
			'to' => $to,
			'duration' => 2.0
		]));

	}

	protected function createWorkingTime(\user\User $eUser, string $date): void {

		// Hors build() : la date est passée par rapport à l'horloge du test, ce que `date.check` vérifierait.
		\hr\WorkingTimeLib::create(new \hr\WorkingTime([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'date' => $date,
			'time' => 7.0
		]));

	}

	/**
	 * Une intervention d'administration, hors culture : elle n'a besoin ni de série ni de plante.
	 */
	protected function createTask(array $plannedUsers): \series\Task {

		$eCategory = \work\Category::model()
			->select(\work\Category::getSelection())
			->whereStructure($this->eStructure)
			->whereFqn(CATEGORIE_ADMINISTRATION)
			->get();

		$eAction = \work\Action::model()
			->select(\work\Action::getSelection())
			->whereStructure($this->eStructure)
			->getCollection()
			->find(fn(\work\Action $eAction) => in_array($eCategory['id'], $eAction['categories']))
			->first();

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'action' => $eAction,
			'category' => $eCategory,
			'series' => new \series\Series(),
			'repeatMaster' => new \series\Repeat(),
			'status' => \series\Task::TODO,
			'plannedWeek' => '2026-W24',
			'plannedUsers' => $plannedUsers
		]);

		// Hors build() : le décor veut ces personnes prévues, que le formulaire ne propose que dans l'équipe du jour.
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	protected function createTimesheet(\series\Task $eTask, \user\User $eUser, string $date): void {

		// Hors build() : la date est passée par rapport à l'horloge du test, et le décor veut ce temps exact.
		\series\TimesheetLib::create(new \series\Timesheet([
			'structure' => $this->eStructure,
			'task' => $eTask,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'user' => $eUser,
			'date' => $date,
			'week' => toWeek($date),
			'time' => 1.5
		]));

	}

	protected function sorted(array $values): array {

		sort($values);
		return $values;

	}

}
?>
