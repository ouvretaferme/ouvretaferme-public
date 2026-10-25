<?php
namespace hr;

/**
 * L'équipe d'une structure de production : l'entrée d'une personne sans compte, le rangement de l'onglet
 * Équipe par année, le contrat d'une période, et ce que le départ d'un accès ou d'une personne sans compte
 * fait de ses périodes.
 *
 * Lancement : dev/test package=hr file=TeammateAi
 */
class TeammateAiTest extends \dev\TransactionTest {

	protected \structure\Structure $eStructure;

	protected function getTransactionModel(): \ModuleModel {
		return new TeammateModel();
	}

	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\structure\FixtureAiLib::F1)
			->get();

	}

	/**
	 * Une personne sans compte entre dans l'équipe avec sa période et son contrat, comme le pose le panneau
	 * « Ajouter une personne ».
	 */
	protected function createGhost(string $name, array $presences): Teammate {

		$eUser = \structure\MemberLib::getNewGhostUser()->merge([
			'firstName' => 'Équipe',
			'lastName' => $name
		]);

		// Hors build() : le décor veut ces dates exactes, y compris des périodes passées que le formulaire
		// accepterait aussi, et un nom que rien ne refuse.
		$eTeammate = new Teammate([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'ePresence' => $this->getPresence(...array_shift($presences))
		]);

		TeammateLib::create($eTeammate);

		foreach($presences as $presence) {

			$ePresence = $this->getPresence(...$presence);
			$ePresence['structure'] = $this->eStructure;
			$ePresence['user'] = $eUser;

			// Hors build() : to.present est éprouvé par PresenceApiAi, ce décor ne pose que des périodes disjointes.
			PresenceLib::create($ePresence);

		}

		return $eTeammate;

	}

	protected function getPresence(string $from, ?string $to, Contract $eContract = new Contract()): Presence {

		return new Presence([
			'from' => $from,
			'to' => $to,
			'eContract' => $eContract
		]);

	}

	/** L'équipe de la structure, réduite aux personnes du test : le décor en a d'autres. */
	protected function getTeam(Teammate ...$teammates): \Collection {

		$users = array_map(fn(Teammate $eTeammate) => $eTeammate['user']['id'], $teammates);

		return TeammateLib::getByStructure($this->eStructure)
			->find(fn(Teammate $eTeammate) => in_array($eTeammate['user']['id'], $users));

	}

	public function testCreateGhost(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eContract = new Contract([
			'type' => Contract::SEASONAL,
			'weeklyHours' => 35.0,
			'days' => new \Set(Contract::MONDAY | Contract::TUESDAY)
		]);

		$eTeammate = $this->createGhost('Sans compte', [
			['2026-03-01', NULL, $eContract]
		]);

		$eUser = $eTeammate['user'];

		$this->assertTrue($eUser['id'] !== NULL, 'la personne sans compte a reçu son compte');
		$this->assertTrue(TeammateLib::isTeammate($this->eStructure, $eUser), 'elle est dans l\'équipe');

		$eMember = \structure\Member::model()
			->select(['status', 'role' => ['fqn']])
			->whereStructure($this->eStructure)
			->whereUser($eUser)
			->get();

		$this->assertEquals(\structure\Member::OUT, $eMember['status']);
		$this->assertEquals('ghost', $eMember['role']['fqn']);

		$cPresence = PresenceLib::getByUser($this->eStructure, $eUser);

		$this->assertEquals(1, $cPresence->count());

		$eContractSaved = ContractLib::getByPresence($cPresence->first());

		$this->assertEquals(Contract::SEASONAL, $eContractSaved['type']);
		$this->assertEquals(35.0, $eContractSaved['weeklyHours']);
		$this->assertEquals($this->eStructure['id'], $eContractSaved['structure']['id'], 'le contrat prend la structure de sa période');

	}

	public function testGroupCurrentYear(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammateHere = $this->createGhost('Là', [
			['2026-03-01', NULL],
			['2024-04-01', '2024-10-31']
		]);
		$eTeammateLeft = $this->createGhost('Partie', [
			['2026-01-10', '2026-04-30']
		]);
		$eTeammateBefore = $this->createGhost('Avant', [
			['2024-05-01', '2024-09-30']
		]);

		$team = TeammateLib::group($this->getTeam($eTeammateHere, $eTeammateLeft, $eTeammateBefore), 2026);

		$this->assertEquals(365, $team['days']);
		$this->assertEquals(165, $team['today'], 'le 15 juin est le 166e jour');

		$groups = $team['groups'];

		$this->assertEquals([$eTeammateHere['id']], $groups['now']->getIds());
		$this->assertEquals([$eTeammateLeft['id']], $groups['year']->getIds());
		$this->assertEquals([$eTeammateBefore['id']], $groups['before']->getIds());

		$eTeammate = $groups['now']->first();

		$this->assertEquals('2026-03-01', $eTeammate['ePresenceShown']['from']);
		$this->assertEquals(1, $eTeammate['periodsBefore']);
		$this->assertEquals([[59, 365]], $eTeammate['ranges'], 'une période ouverte court jusqu\'au 31 décembre');
		$this->assertEquals([$eTeammate['ePresenceShown']['id']], $eTeammate['rangesPresence'], 'la frise ouvre la période qu\'elle dessine, et non celle de 2024');

		$eTeammate = $groups['year']->first();

		$this->assertEquals('2026-04-30', $eTeammate['ePresenceShown']['to']);
		$this->assertEquals([[9, 120]], $eTeammate['ranges']);

		$eTeammate = $groups['before']->first();

		$this->assertEquals('2024-05-01', $eTeammate['ePresenceShown']['from']);
		$this->assertEquals([], $eTeammate['ranges']);
		$this->assertEquals([], $eTeammate['rangesPresence']);

	}

	public function testGroupComing(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammateReturning = $this->createGhost('Revient', [
			['2026-03-01', '2026-06-15'],
			['2026-09-01', '2026-10-31'],
			['2027-03-01', NULL]
		]);
		$eTeammateArriving = $this->createGhost('Arrive', [
			['2026-11-01', NULL]
		]);

		$groups = TeammateLib::group($this->getTeam($eTeammateReturning, $eTeammateArriving), 2026)['groups'];

		$this->assertEquals([$eTeammateReturning['id'], $eTeammateArriving['id']], $groups['now']->getIds(), 'une arrivée à venir se range avec les présents');

		$eTeammate = $groups['now']->first();

		$this->assertEquals('2026-03-01', $eTeammate['ePresenceShown']['from'], 'la ligne donne la période en cours');
		$this->assertEquals(['2026-09-01', '2027-03-01'], $eTeammate['cPresenceComing']->getColumn('from'), 'les périodes prévues suivent, de la plus proche à la plus lointaine');

		$eTeammate = $groups['now']->last();

		$this->assertEquals('2026-11-01', $eTeammate['ePresenceShown']['from']);
		$this->assertTrue($eTeammate['ePresenceShown']->isComing(), 'la période de la ligne est à venir');
		$this->assertTrue($eTeammate['cPresenceComing']->empty());

	}

	public function testGroupPastYear(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammateHere = $this->createGhost('Là', [
			['2026-03-01', NULL],
			['2024-04-01', '2024-10-31']
		]);
		$eTeammateArrived = $this->createGhost('Arrivée après', [
			['2026-01-10', '2026-04-30']
		]);
		$eTeammateBefore = $this->createGhost('Avant', [
			['2023-05-01', '2023-09-30']
		]);

		$team = TeammateLib::group($this->getTeam($eTeammateHere, $eTeammateArrived, $eTeammateBefore), 2024);

		$this->assertEquals(366, $team['days'], '2024 est bissextile');
		$this->assertEquals(NULL, $team['today'], 'une année passée n\'a pas de marqueur');

		$groups = $team['groups'];

		$this->assertTrue($groups['now']->empty(), 'une année passée n\'a pas de groupe « En ce moment »');
		$this->assertEquals([$eTeammateHere['id']], $groups['year']->getIds(), 'une personne arrivée après l\'année n\'y figure pas');
		$this->assertEquals([$eTeammateBefore['id']], $groups['before']->getIds());

		$eTeammate = $groups['year']->first();

		$this->assertEquals('2024-04-01', $eTeammate['ePresenceShown']['from'], 'la ligne donne la période de l\'année affichée');
		$this->assertEquals(0, $eTeammate['periodsBefore']);
		$this->assertEquals([[91, 305]], $eTeammate['ranges']);

	}

	public function testEmptyContractDeletes(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Contrat', [
			['2026-03-01', NULL, new Contract(['type' => Contract::CDI, 'weeklyHours' => NULL, 'days' => NULL])]
		]);

		$ePresence = PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->first();

		$this->assertTrue(ContractLib::getByPresence($ePresence)->notEmpty());

		$ePresence['to'] = '2026-08-31';

		PresenceLib::updateWithContract($ePresence, ['to'], new Contract());

		$this->assertTrue(ContractLib::getByPresence($ePresence)->empty(), 'un contrat vide efface celui de la période');
		$this->assertEquals('2026-08-31', PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->first()['to']);

	}

	/**
	 * Les heures d'un contrat se saisissent par semaine ou par an selon la période choisie. Les deux champs se postent
	 * toujours, l'un masqué : celui de l'autre période est écarté, et un contrat annualisé sans heures par an refusé.
	 */
	public function testHoursPeriod(): void {

		$fw = new \FailWatch();

		$eContractYear = new Contract();
		$eContractYear->build(ContractLib::getPropertiesCreate(), ['type' => Contract::CDI, 'hoursPeriod' => Contract::YEAR, 'weeklyHours' => '35', 'annualHours' => '1607', 'hasVariableDays' => '0', 'days' => ['', (string)Contract::MONDAY]], new \PropertyList('create'));

		$this->assertTrue($fw->ok(), 'un contrat annualisé avec ses heures par an est refusé');
		$this->assertEquals(1607.0, $eContractYear['annualHours']);
		$this->assertEquals(NULL, $eContractYear['weeklyHours'], 'les heures par semaine restent sur un contrat annualisé');

		$eContractWeek = new Contract();
		$eContractWeek->build(ContractLib::getPropertiesCreate(), ['type' => Contract::CDI, 'hoursPeriod' => Contract::WEEK, 'weeklyHours' => '35', 'annualHours' => '1607', 'hasVariableDays' => '0', 'days' => ['', (string)Contract::MONDAY]], new \PropertyList('create'));

		$this->assertTrue($fw->ok(), 'un contrat à la semaine avec ses heures par semaine est refusé');
		$this->assertEquals(35.0, $eContractWeek['weeklyHours']);
		$this->assertEquals(NULL, $eContractWeek['annualHours'], 'les heures par an restent sur un contrat à la semaine');

		$eContractWithout = new Contract();
		$eContractWithout->build(ContractLib::getPropertiesCreate(), ['type' => Contract::CDI, 'hoursPeriod' => Contract::YEAR, 'weeklyHours' => '35', 'annualHours' => '', 'hasVariableDays' => '0', 'days' => ['', (string)Contract::MONDAY]], new \PropertyList('create'));

		$this->assertTrue($fw->has('Contract::annualHours.required'), 'un contrat annualisé sans heures par an est accepté');

		$fw = new \FailWatch();

		$eContractOver = new Contract();
		$eContractOver->build(ContractLib::getPropertiesCreate(), ['type' => Contract::CDI, 'hoursPeriod' => Contract::YEAR, 'annualHours' => '2400', 'hasVariableDays' => '0', 'days' => ['', (string)Contract::MONDAY]], new \PropertyList('create'));

		$this->assertTrue($fw->has('Contract::annualHours.check'), 'un volume au-delà de 2 300 h est accepté');

		$fw = new \FailWatch();

		$eContractEmpty = new Contract();
		$eContractEmpty->build(ContractLib::getPropertiesCreate(), ['type' => Contract::CDI, 'hoursPeriod' => Contract::WEEK, 'weeklyHours' => '', 'annualHours' => '', 'hasVariableDays' => '0', 'days' => ['', (string)Contract::MONDAY]], new \PropertyList('create'));

		$this->assertTrue($fw->ok(), 'un contrat à la semaine sans heures est refusé');

	}

	/**
	 * Des jours variables ne se cochent pas : le formulaire désactive les cases, qui ne postent plus que le
	 * champ caché vide, et un POST qui porte les deux est refusé plutôt que l'un ne soit jeté en silence.
	 */
	public function testVariableDays(): void {

		$this->setClock('2026-06-15 10:00:00');

		$fw = new \FailWatch();

		$eContractVariable = new Contract();
		$eContractVariable->build(ContractLib::getPropertiesCreate(), ['type' => Contract::SEASONAL, 'weeklyHours' => '', 'hoursPeriod' => Contract::WEEK, 'hasVariableDays' => '1', 'days' => ['']], new \PropertyList('create'));

		$this->assertTrue($fw->ok(), 'des jours variables sans jour coché sont acceptés');
		$this->assertTrue($eContractVariable['hasVariableDays']);
		$this->assertEquals(0, $eContractVariable['days']->get());

		$eContractBoth = new Contract();
		$eContractBoth->build(ContractLib::getPropertiesCreate(), ['type' => Contract::SEASONAL, 'weeklyHours' => '', 'hoursPeriod' => Contract::WEEK, 'hasVariableDays' => '1', 'days' => ['', (string)Contract::MONDAY]], new \PropertyList('create'));

		$this->assertTrue($fw->has('Contract::days.variable'), 'des jours variables et un jour coché sont refusés');

		$fw = new \FailWatch();

		$eContractFixed = new Contract();
		$eContractFixed->build(ContractLib::getPropertiesUpdate(), ['type' => Contract::SEASONAL, 'weeklyHours' => '', 'hoursPeriod' => Contract::WEEK, 'hasVariableDays' => '0', 'days' => ['', (string)Contract::MONDAY]], new \PropertyList('update'));

		$this->assertTrue($fw->ok(), 'des jours fixes se cochent');

		$eTeammate = $this->createGhost('Jours variables', [
			['2026-03-01', NULL, $eContractVariable]
		]);

		$ePresence = PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->first();

		$eContractSaved = ContractLib::getByPresence($ePresence);

		$this->assertTrue($eContractSaved['hasVariableDays'], 'les jours variables sont enregistrés');
		$this->assertEquals(0, $eContractSaved['days']->get(), 'sans jour');

		PresenceLib::updateWithContract($ePresence, ['to'], $eContractFixed);

		$eContractSaved = ContractLib::getByPresence($ePresence);

		$this->assertFalse($eContractSaved['hasVariableDays'], 'revenir à des jours fixes efface les jours variables');
		$this->assertTrue($eContractSaved['days']->value(Contract::MONDAY));

	}

	/**
	 * Une personne qui a un accès à `$eStructure`, comme le pose `InviteLib::accept()`.
	 */
	protected function createAccess(string $name, \structure\Structure $eStructure): \structure\Member {

		$eUser = new \user\User([
			'firstName' => 'Équipe',
			'lastName' => $name,
			'email' => 'hr-teammate-'.uniqid().'@example.org',
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		// Hors build() : le domaine réservé et uniqid() assurent ce que build() vérifierait de l'adresse.
		\user\UserLib::create($eUser);

		$eMember = new \structure\Member([
			'user' => $eUser,
			'structure' => $eStructure,
			'role' => \structure\RoleLib::getByStructure($eStructure, withAdmin: FALSE)->first(),
			'status' => \structure\Member::IN
		]);

		// Hors build(), comme le pose InviteLib::accept().
		\structure\MemberLib::create($eMember);

		return $eMember;

	}

	/** Une personne de l'équipe qui a un accès, avec sa période en cours depuis le 1er mars. */
	protected function createTeammateWithAccess(string $name): \structure\Member {

		$eMember = $this->createAccess($name, $this->eStructure);

		// Hors build() : l'étape `member` de `Teammate::user` demande l'accès qui vient d'être posé.
		TeammateLib::create(new Teammate([
			'structure' => $this->eStructure,
			'user' => $eMember['user'],
			'ePresence' => $this->getPresence('2026-03-01', NULL)
		]));

		return $eMember;

	}

	protected function getMember(\user\User $eUser): \structure\Member {
		return \structure\MemberLib::getByUserAndStructure($eUser, $this->eStructure);
	}

	protected function createAbsence(\user\User $eUser, string $from, string $to, float $duration): Absence {

		$eAbsence = new Absence([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'type' => Absence::VACATION,
			'unit' => Absence::DAY,
			'status' => Absence::VALIDATED,
			'from' => $from,
			'to' => $to,
			'duration' => $duration
		]);

		// Hors build() : le décor veut une absence déjà validée, que le panneau du gérant pose aussi, et certaines
		// hors présence, que `to.outOfPresence` refuserait : c'est le cas qu'éprouve la sortie du planning.
		AbsenceLib::create($eAbsence);

		return $eAbsence;

	}

	protected function getAbsence(Absence $eAbsence): Absence {

		return Absence::model()
			->select(['id', 'from', 'to', 'duration'])
			->whereId($eAbsence['id'])
			->get();

	}

	/**
	 * Retirer tout rôle à une personne de l'équipe la garde dans la structure, sortie et sans rôle, avec ses périodes
	 * et ses absences (décidé le 22/10).
	 */
	public function testDeleteAccessKeepsPresences(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eMember = $this->createTeammateWithAccess('Accès');
		$eUser = $eMember['user'];

		$eAbsenceCurrent = $this->createAbsence($eUser, '2026-06-10 00:00:00', '2026-06-19 23:59:59', 6.0);
		$eAbsenceComing = $this->createAbsence($eUser, '2026-07-01 00:00:00', '2026-07-03 23:59:59', 3.0);

		\structure\MemberLib::deleteInternal($eMember);

		$eMemberKept = $this->getMember($eUser);

		$this->assertTrue(TeammateLib::isTeammate($this->eStructure, $eUser), 'la personne reste dans l\'équipe');
		$this->assertEquals(1, PresenceLib::getByUser($this->eStructure, $eUser)->count(), 'sa période reste ouverte');
		$this->assertEquals($eMember['id'], $eMemberKept['id'], 'son adhésion reste');
		$this->assertTrue($eMemberKept->isRevoked(), 'sortie et sans rôle');
		$this->assertFalse(\structure\StructureLib::getByUser($eUser)->offsetExists($this->eStructure['id']), 'la structure ne s\'ouvre plus à son compte');
		$this->assertFalse(\structure\MemberLib::getByUser($eUser)->offsetExists($this->eStructure['id']), 'ni ses droits');
		$this->assertEquals('2026-06-19 23:59:59', $this->getAbsence($eAbsenceCurrent)['to'], 'l\'absence en cours ne bouge pas');
		$this->assertTrue($this->getAbsence($eAbsenceComing)->notEmpty(), 'l\'absence à venir reste');
		$this->assertFalse($eMemberKept->acceptDelete(), 'le rôle d\'une adhésion sortie ne se retire plus');

		$eMemberKept['structure'] = $this->eStructure;

		$this->assertFalse($eMemberKept->acceptUpdateRole(), 'et ne se change pas, il se redonne par invitation');
		$this->assertFalse($eMemberKept->acceptInvite(), 'elle n\'est pas une personne sans compte');

	}

	/** Hors de l'équipe, retirer tout rôle retire l'adhésion, comme avant. */
	public function testDeleteAccessOutsideTeam(): void {

		$eMember = $this->createAccess('Hors équipe', $this->eStructure);

		\structure\MemberLib::deleteInternal($eMember);

		$this->assertTrue($this->getMember($eMember['user'])->empty(), 'l\'adhésion est retirée');

	}

	/**
	 * Dans une boutique, une adhésion sans rôle ne se montrerait nulle part : le retrait la supprime, même pour une
	 * personne de son équipe.
	 */
	public function testDeleteAccessShop(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eStructureShop = \structure\StructureLib::getById(\structure\FixtureAiLib::B1);
		$eMember = $this->createAccess('Boutique', $eStructureShop);

		// Hors build() : l'étape `member` de `Teammate::user` demande l'accès qui vient d'être posé.
		TeammateLib::create(new Teammate([
			'structure' => $eStructureShop,
			'user' => $eMember['user'],
			'ePresence' => $this->getPresence('2026-03-01', NULL)
		]));

		\structure\MemberLib::deleteInternal($eMember);

		$this->assertTrue(\structure\MemberLib::getByUserAndStructure($eMember['user'], $eStructureShop)->empty(), 'l\'adhésion est retirée');

	}

	/**
	 * L'invitation acceptée rend son adhésion gardée à la personne sans accès, avec le rôle choisi : une seule ligne,
	 * entrée de nouveau, et la personne reste dans l'équipe.
	 */
	public function testInviteGivesRoleBack(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eMember = $this->createTeammateWithAccess('Sans rôle');
		$eUser = $eMember['user'];

		\structure\MemberLib::deleteInternal($eMember);

		$cRole = \structure\RoleLib::getByStructure($this->eStructure);
		$eRole = \structure\RoleLib::getByStructure($this->eStructure, withAdmin: FALSE)->first();

		$eInvite = new \structure\Invite([
			'structure' => $this->eStructure,
			'member' => new \structure\Member(),
			'type' => \structure\Invite::MEMBER,
			'cRole' => $cRole,
			'cSection' => new \Collection(),
			'sendMail' => FALSE
		]);

		$fw = new \FailWatch();

		$eInvite->build(\structure\InviteLib::getPropertiesCreate()($eInvite), [
			'memberRole' => $eRole['id'],
			'email' => $eUser['email']
		]);

		if($this->assertTrue($fw->ok(), 'l\'invitation à l\'adresse d\'une personne sans accès est refusée : '.$fw->getLast()) === FALSE) {
			return;
		}

		\structure\InviteLib::create($eInvite);

		$this->assertEquals($eInvite['id'], \structure\InviteLib::getPendingByEmail($this->eStructure, $eUser['email'])['id'], 'l\'invitation en cours se retrouve par l\'adresse du compte');

		$accepted = \structure\InviteLib::accept(\structure\InviteLib::getByKey($eInvite['key']), $eUser);

		$cMember = \structure\MemberLib::getByUsers($this->eStructure, new \Collection([$eUser]));

		$this->assertTrue($accepted, 'l\'invitation acceptée échoue');
		$this->assertEquals(1, $cMember->count(), 'une seule adhésion');
		$this->assertEquals($eMember['id'], $cMember->first()['id'], 'celle qui était gardée');
		$this->assertEquals(\structure\Member::IN, $cMember->first()['status'], 'entrée de nouveau');
		$this->assertEquals($eRole['id'], $cMember->first()['role']['id'], 'avec le rôle choisi');
		$this->assertEquals($eInvite['key'], $cMember->first()['inviteKey'], 'et la clé de l\'invitation');
		$this->assertTrue(TeammateLib::isTeammate($this->eStructure, $eUser), 'la personne reste dans l\'équipe');

	}

	/** Une adresse qui a déjà une adhésion avec un rôle ne se réinvite pas. */
	public function testInviteRefusesMember(): void {

		$eMember = $this->createAccess('Déjà là', $this->eStructure);

		$eInvite = new \structure\Invite([
			'structure' => $this->eStructure,
			'member' => new \structure\Member(),
			'type' => \structure\Invite::MEMBER,
			'cRole' => \structure\RoleLib::getByStructure($this->eStructure),
			'cSection' => new \Collection()
		]);

		$fw = new \FailWatch();

		$eInvite->build(\structure\InviteLib::getPropertiesCreate()($eInvite), [
			'memberRole' => \structure\RoleLib::getByStructure($this->eStructure, withAdmin: FALSE)->first()['id'],
			'email' => $eMember['user']['email']
		]);

		$this->assertTrue($fw->has('Invite::email.duplicateMember'), 'un membre ne se réinvite pas');

	}

	/** Sortir de l'équipe efface l'adhésion gardée : sans équipe, aucun écran ne la montrerait. */
	public function testLeaveDeletesRevokedMember(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eMember = $this->createAccess('Partie', $this->eStructure);
		$eUser = $eMember['user'];

		// Hors build() : l'étape `member` de `Teammate::user` demande l'accès qui vient d'être posé.
		$eTeammate = new Teammate([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'ePresence' => $this->getPresence('2026-07-01', NULL)
		]);

		TeammateLib::create($eTeammate);

		\structure\MemberLib::deleteInternal($eMember);

		$this->assertTrue($this->leave($eTeammate)->ok(), 'le geste passe');

		$this->assertFalse(TeammateLib::isTeammate($this->eStructure, $eUser), 'la personne sort de l\'équipe');
		$this->assertTrue($this->getMember($eUser)->empty(), 'avec son adhésion gardée');

	}

	/**
	 * Sortir du planning borne les absences comme les périodes (décidé le 22/10) : celle en cours finit la
	 * veille avec les jours qu'elle prend jusque-là, celles d'aujourd'hui et à venir s'effacent, la passée reste.
	 */
	public function testLeaveBoundsAbsences(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Absente', [
			['2026-03-01', NULL]
		]);

		$eUser = $eTeammate['user'];

		$eAbsencePast = $this->createAbsence($eUser, '2026-05-04 00:00:00', '2026-05-05 23:59:59', 2.0);
		// Du mercredi 10 au vendredi 19 juin : jusqu'au dimanche 14, mercredi, jeudi et vendredi comptent.
		$eAbsenceCurrent = $this->createAbsence($eUser, '2026-06-10 00:00:00', '2026-06-19 23:59:59', 8.0);
		$eAbsenceShort = $this->createAbsence($eUser, '2026-06-11 00:00:00', '2026-06-16 23:59:59', 2.0);
		$eAbsenceToday = $this->createAbsence($eUser, '2026-06-15 00:00:00', '2026-06-15 23:59:59', 1.0);
		$eAbsenceComing = $this->createAbsence($eUser, '2026-07-01 00:00:00', '2026-07-03 23:59:59', 3.0);
		// Le samedi 13 et le dimanche 14 : aucun jour travaillé jusqu'à la veille.
		$eAbsenceWeekEnd = $this->createAbsence($eUser, '2026-06-13 00:00:00', '2026-06-16 23:59:59', 2.0);

		$this->assertTrue($this->leave($eTeammate)->ok(), 'le geste passe');

		$eAbsenceCurrentSaved = $this->getAbsence($eAbsenceCurrent);
		$eAbsenceShortSaved = $this->getAbsence($eAbsenceShort);

		$this->assertEquals(['2026-05-04 00:00:00', '2026-05-05 23:59:59', 2.0], [$this->getAbsence($eAbsencePast)['from'], $this->getAbsence($eAbsencePast)['to'], $this->getAbsence($eAbsencePast)['duration']], 'la passée reste telle quelle');
		$this->assertEquals('2026-06-14 23:59:59', $eAbsenceCurrentSaved['to'], 'l\'absence en cours finit la veille');
		$this->assertEquals(3.0, $eAbsenceCurrentSaved['duration'], 'avec les jours travaillés jusque-là');
		$this->assertEquals('2026-06-14 23:59:59', $eAbsenceShortSaved['to'], 'une absence plus courte que ses jours finit aussi la veille');
		$this->assertEquals(2.0, $eAbsenceShortSaved['duration'], 'et garde sa durée saisie');
		$this->assertTrue($this->getAbsence($eAbsenceToday)->empty(), 'celle d\'aujourd\'hui s\'efface');
		$this->assertTrue($this->getAbsence($eAbsenceComing)->empty(), 'celle à venir s\'efface');
		$this->assertTrue($this->getAbsence($eAbsenceWeekEnd)->empty(), 'celle qui ne prend aucun jour jusqu\'à la veille s\'efface');

	}

	/**
	 * Une absence en cours que la personne ne couvre pas de son début à la veille, ce que `build()` refuserait, s'efface
	 * au lieu de bloquer la sortie (décidé le 22/10).
	 */
	public function testLeaveDeletesAbsenceOutOfPresence(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Arrivée récente', [
			['2026-06-12', NULL]
		]);

		$eAbsence = $this->createAbsence($eTeammate['user'], '2026-06-10 00:00:00', '2026-06-19 23:59:59', 4.0);

		$this->assertTrue($this->leave($eTeammate)->ok(), 'le geste passe');

		$this->assertTrue($this->getAbsence($eAbsence)->empty(), 'l\'absence hors présence s\'efface');
		$this->assertEquals([['2026-06-12', '2026-06-14']], $this->getRanges($eTeammate), 'et la période finit la veille');

	}

	/**
	 * La page Équipe lit ses adhésions par la règle de l'API (`MemberLib::getTeamByStructure()`) : ceux qui ont
	 * un accès, les personnes sans compte et l'équipe, même sans période, et ni celle dont l'accès est retiré, ni
	 * l'accès d'une autre structure. « Hors planning » en garde ce qui n'est pas dans l'équipe.
	 */
	public function testOutsideTeam(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eMemberAccess = $this->createAccess('Hors planning', $this->eStructure);

		$eUserGhost = \structure\MemberLib::getNewGhostUser()->merge([
			'firstName' => 'Équipe',
			'lastName' => 'Sans compte hors planning'
		]);

		// Hors build() : un nom que rien ne refuse, comme le pose le panneau de création.
		\structure\MemberLib::createGhostUser($this->eStructure, $eUserGhost);

		$eMemberTeammate = $this->createAccess('Dans l\'équipe', $this->eStructure);

		// Hors build() : l'étape `member` de `Teammate::user` demande l'accès qui vient d'être posé.
		TeammateLib::create(new Teammate([
			'structure' => $this->eStructure,
			'user' => $eMemberTeammate['user'],
			'ePresence' => $this->getPresence('2026-03-01', NULL)
		]));

		$eTeammateEmpty = $this->createGhost('Sans période', [
			['2026-03-01', NULL]
		]);
		PresenceLib::delete(PresenceLib::getByUser($this->eStructure, $eTeammateEmpty['user'])->first());

		$eMemberLeft = $this->createAccess('Accès retiré', $this->eStructure);
		\structure\MemberLib::deleteInternal($eMemberLeft);

		$eMemberElsewhere = $this->createAccess('Ailleurs', \structure\StructureLib::getById(\structure\FixtureAiLib::F2));

		$cMember = \structure\MemberLib::getTeamByStructure($this->eStructure, 'user');

		$this->assertTrue($cMember->offsetExists($eMemberAccess['user']['id']), 'un accès hors de l\'équipe y figure');
		$this->assertTrue($cMember->offsetExists($eUserGhost['id']), 'une personne sans compte hors de l\'équipe y figure');
		$this->assertTrue($cMember->offsetExists($eMemberTeammate['user']['id']), 'une personne de l\'équipe y figure');
		$this->assertTrue($cMember->offsetExists($eTeammateEmpty['user']['id']), 'même sans période');
		$this->assertTrue(TeammateLib::isTeammate($this->eStructure, $eTeammateEmpty['user']), 'et elle reste dans l\'équipe, donc hors de « Hors planning »');
		$this->assertFalse(TeammateLib::isTeammate($this->eStructure, $eMemberAccess['user']), 'un accès sans période est « Hors planning »');
		$this->assertFalse($cMember->offsetExists($eMemberLeft['user']['id']), 'un accès retiré n\'y figure pas');
		$this->assertFalse($cMember->offsetExists($eMemberElsewhere['user']['id']), 'l\'accès d\'une autre structure n\'y figure pas');

	}

	public function testPeriodIncludesGhost(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Planning', [
			['2026-03-01', NULL]
		]);

		$cUser = \structure\MemberLib::getUsersByStructureForPeriod($this->eStructure, '2026-06-01', '2026-06-30');

		$this->assertTrue($cUser->offsetExists($eTeammate['user']['id']), 'une personne sans compte de l\'équipe est au planning');

		$cUser = \structure\MemberLib::getUsersByStructureForPeriod($this->eStructure, '2026-01-01', '2026-01-31');

		$this->assertFalse($cUser->offsetExists($eTeammate['user']['id']), 'ni avant son arrivée');

	}

	/**
	 * Une modification ne fait pas chevaucher la période qu'elle change avec la suivante.
	 */
	public function testUpdateOverlap(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Chevauchée', [
			['2026-03-01', '2026-06-30'],
			['2026-09-01', NULL]
		]);

		$ePresence = PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->find(fn(Presence $e) => $e['from'] === '2026-03-01')->first();
		$ePresence['structure'] = $this->eStructure;
		$ePresence['user'] = $eTeammate['user'];

		$fw = new \FailWatch();

		$ePresence->build(['from', 'to'], ['from' => '2026-03-01', 'to' => '2026-09-01'], new \PropertyList('update'));

		$this->assertTrue($fw->has('Presence::to.present'), 'un départ le jour de l\'arrivée suivante est refusé');

		$fw = new \FailWatch();

		$ePresence->build(['from', 'to'], ['from' => '2026-03-01', 'to' => '2026-09-15'], new \PropertyList('update'));

		$this->assertTrue($fw->has('Presence::to.present'), 'une modification qui recouvre la période suivante est refusée');

		$fw = new \FailWatch();

		$ePresence->build(['from', 'to'], ['from' => '2026-03-01', 'to' => '2026-08-31'], new \PropertyList('update'));

		$this->assertTrue($fw->ok(), 'une modification qui s\'arrête la veille de la période suivante passe');

	}

	/**
	 * Une période neuve se contrôle comme une création quelle que soit la liste que reçoit `build()` : un
	 * appelant qui n'en passe aucune, comme un décor de test, n'en fait pas une modification.
	 */
	public function testCreateOverlapWithoutPropertyList(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Recouverte', [
			['2026-03-01', '2026-06-30']
		]);

		$fw = new \FailWatch();

		$warning = NULL;

		set_error_handler(function(int $code, string $message) use (&$warning): bool {
			$warning = $message;
			return TRUE;
		}, E_WARNING);

		$ePresence = new Presence(['structure' => $this->eStructure]);
		$ePresence->build(['user', 'from', 'to'], ['user' => $eTeammate['user']['id'], 'from' => '2026-06-01', 'to' => '2026-07-31']);

		restore_error_handler();

		$this->assertEquals(NULL, $warning, 'le contrôle du chevauchement a lu l\'identifiant d\'une période qui n\'en a pas encore');
		$this->assertTrue($fw->has('Presence::to.present'), 'une période neuve qui recouvre une période enregistrée est refusée');

		$fw = new \FailWatch();

		$ePresence = new Presence(['structure' => $this->eStructure]);
		$ePresence->build(['user', 'from', 'to'], ['user' => $eTeammate['user']['id'], 'from' => '2026-07-01', 'to' => '2026-07-31']);

		$this->assertTrue($fw->ok(), 'une période neuve qui commence le lendemain passe');

	}

	public function testDeleteGhostUser(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Effacée', [
			['2026-03-01', NULL, new Contract(['type' => Contract::CDD, 'weeklyHours' => NULL, 'days' => NULL])]
		]);

		$ePresence = PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->first();

		$fw = new \FailWatch();

		\structure\MemberLib::deleteGhostUser($this->eStructure, $eTeammate['user']);

		$this->assertTrue($fw->has('Member::deleteGhostTeammate'), 'une personne sans compte de l\'équipe ne se supprime pas');
		$this->assertTrue(TeammateLib::isTeammate($this->eStructure, $eTeammate['user']), 'elle reste dans l\'équipe');
		$this->assertEquals(1, \structure\MemberLib::getByUsers($this->eStructure, new \Collection([$eTeammate['user']['id'] => $eTeammate['user']]))->count(), 'et dans la structure');

		TeammateLib::delete($eTeammate);

		$this->assertTrue(PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->empty(), 'sortie de l\'équipe, elle perd ses périodes');
		$this->assertTrue(ContractLib::getByPresence($ePresence)->empty(), 'et leurs contrats');

		\structure\MemberLib::deleteGhostUser($this->eStructure, $eTeammate['user']);

		$this->assertEquals(1, $fw->count(), 'puis se supprime sans nouveau refus');
		$this->assertTrue(\structure\MemberLib::getByUsers($this->eStructure, new \Collection([$eTeammate['user']['id'] => $eTeammate['user']]))->empty(), 'et quitte la structure');

	}

	protected function leave(Teammate $eTeammate): \FailWatch {

		$eTeammate['structure'] = $this->eStructure;

		$fw = new \FailWatch();

		TeammateLib::leave($eTeammate);

		return $fw;

	}

	protected function getRanges(Teammate $eTeammate): array {
		return PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->makeArray(fn(Presence $e) => [$e['from'], $e['to']]);
	}

	/**
	 * Le jour du geste n'est plus une présence : la période en cours finit la veille.
	 */
	public function testLeaveStarted(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Partante', [
			['2026-03-01', NULL]
		]);

		$eTeammate['cPresence'] = PresenceLib::getByUser($this->eStructure, $eTeammate['user']);

		$this->assertTrue($eTeammate->acceptLeave(), 'une période sans fin sort du planning');

		$this->assertTrue($this->leave($eTeammate)->ok(), 'le geste passe');

		$this->assertEquals([['2026-03-01', '2026-06-14']], $this->getRanges($eTeammate), 'la période finit la veille');
		$this->assertTrue(TeammateLib::isTeammate($this->eStructure, $eTeammate['user']), 'la personne reste dans l\'équipe, avec son passé');

		$eTeammate['cPresence'] = PresenceLib::getByUser($this->eStructure, $eTeammate['user']);

		$this->assertFalse($eTeammate->acceptLeave(), 'une personne sortie du planning n\'en sort plus');

	}

	/**
	 * Une période qui commence aujourd'hui n'a pas de veille dans ses dates : elle s'efface, comme une période à venir.
	 */
	public function testLeaveStartingToday(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Arrivée', [
			['2026-06-15', '2026-08-31'],
			['2026-01-05', '2026-02-28']
		]);

		$this->assertTrue($this->leave($eTeammate)->ok(), 'le geste passe');

		$this->assertEquals([['2026-01-05', '2026-02-28']], $this->getRanges($eTeammate), 'seule la période passée reste');

	}

	/**
	 * La période en cours finit la veille, la suivante s'efface, la passée reste telle quelle.
	 */
	public function testLeaveStartedAndComing(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Saisonnière', [
			['2026-09-01', NULL],
			['2026-04-01', '2026-06-30'],
			['2025-03-01', '2025-10-31']
		]);

		$this->assertTrue($this->leave($eTeammate)->ok(), 'le geste passe');

		$this->assertEquals([['2026-04-01', '2026-06-14'], ['2025-03-01', '2025-10-31']], $this->getRanges($eTeammate), 'la période en cours finit la veille et la suivante s\'efface');

	}

	/**
	 * Sans autre période, la personne sort de l'équipe et redevient une personne sans compte qui se supprime.
	 */
	public function testLeaveComingAlone(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Annulée', [
			['2026-09-01', NULL, new Contract(['type' => Contract::CDD, 'weeklyHours' => NULL, 'days' => NULL])]
		]);

		$ePresence = PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->first();

		$this->assertTrue($this->leave($eTeammate)->ok(), 'le geste passe');

		$cUser = new \Collection([$eTeammate['user']['id'] => $eTeammate['user']]);

		$this->assertTrue(PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->empty(), 'la période est effacée');
		$this->assertTrue(ContractLib::getByPresence($ePresence)->empty(), 'avec son contrat');
		$this->assertFalse(TeammateLib::isTeammate($this->eStructure, $eTeammate['user']), 'la personne sort de l\'équipe');
		$this->assertEquals(1, \structure\MemberLib::getByUsers($this->eStructure, $cUser)->count(), 'et reste rattachée à la structure');

		$fw = new \FailWatch();

		\structure\MemberLib::deleteGhostUser($this->eStructure, $eTeammate['user']);

		$this->assertTrue($fw->ok(), 'puis se supprime');
		$this->assertTrue(\structure\MemberLib::getByUsers($this->eStructure, $cUser)->empty(), 'et quitte la structure');

	}

	/**
	 * Une personne dont toutes les périodes sont finies n'a plus de planning à quitter.
	 */
	public function testLeaveFinished(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Partie', [
			['2026-01-05', '2026-06-14']
		]);

		$eTeammate['cPresence'] = PresenceLib::getByUser($this->eStructure, $eTeammate['user']);

		$this->assertFalse($eTeammate->acceptLeave(), 'une période finie hier ne sort plus du planning');

		$this->setClock('2026-06-14 10:00:00');

		$this->assertTrue($eTeammate->acceptLeave(), 'son dernier jour, elle y est encore');

	}

	/**
	 * Une période posée après l'affichage du bouton s'efface aussi : la personne sort de l'équipe au lieu de
	 * rester au planning par cette seule période.
	 */
	public function testLeaveRereadsCreated(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Doublée', [
			['2026-09-01', '2026-10-31']
		]);

		$eTeammate['structure'] = $this->eStructure;

		// Hors build() : la période posée entre deux gestes ne chevauche pas la première, to.present est éprouvé par PresenceApiAi.
		PresenceLib::create(new Presence(['structure' => $this->eStructure, 'user' => $eTeammate['user'], 'from' => '2027-03-01', 'to' => NULL]));

		TeammateLib::leave($eTeammate);

		$this->assertTrue(PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->empty(), 'les deux périodes à venir s\'effacent');
		$this->assertFalse(TeammateLib::isTeammate($this->eStructure, $eTeammate['user']), 'et la personne sort de l\'équipe');

	}

	/**
	 * Une période terminée après l'affichage du bouton garde sa fin : la veille ne la prolonge pas.
	 */
	public function testLeaveRereadsEnded(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eTeammate = $this->createGhost('Écourtée', [
			['2026-03-01', NULL]
		]);

		$eTeammate['structure'] = $this->eStructure;

		// Hors build() : la fin posée entre deux gestes est celle que le formulaire accepterait, avant aujourd'hui.
		$ePresence = PresenceLib::getByUser($this->eStructure, $eTeammate['user'])->first();
		$ePresence['to'] = '2026-05-31';
		PresenceLib::update($ePresence, ['to']);

		TeammateLib::leave($eTeammate);

		$this->assertEquals([['2026-03-01', '2026-05-31']], $this->getRanges($eTeammate), 'la période garde la fin posée entre-temps');
		$this->assertTrue(TeammateLib::isTeammate($this->eStructure, $eTeammate['user']), 'et la personne reste dans l\'équipe');

	}

}
?>
