<?php
/**
 * La liste de ceux qui ont un accès : l'écran Équipe d'une AMAP ou d'une boutique. Une structure de production
 * range les siens dans l'onglet Personnes, sous ceux qui sont au planning.
 */
$getAccess = function($data) {

	$data->cInvite = \structure\InviteLib::getMembersByStructure($data->eStructure);

	if($data->eStructure->isAmap()) {

		$data->eStructure['eRoleAdmin'] = \structure\RoleLib::getOne($data->eStructure, 'admin');
		// Une AMAP fermée n'invite plus d'administrateur, son équipe restant celle qui la ferme.
		$data->eStructure['inviteCreate'] = new \structure\Invite(['type' => \structure\Invite::MEMBER, 'structure' => $data->eStructure])->acceptCreate();

		$data->cInvite = $data->cInvite->find(fn(\structure\Invite $eInvite) => $eInvite->isAmapProducer() === FALSE);

	}

	$data->ccMember = \structure\MemberLib::getTeamByStructure($data->eStructure, ['role', NULL]);

	[$data->cInvite, $data->cInviteFinished] = \structure\InviteLib::splitFinished($data->cInvite);

	foreach($data->ccMember as $cMember) {
		foreach($cMember as $eMember) {
			$eMember['structure'] = $data->eStructure;
		}
	}

	// La colonne des producteurs d'une AMAP ne se pose que si un administrateur en tient un : vide sur
	// toutes les lignes, elle ne dirait rien.
	$data->producers = \structure\StructureLib::loadAmapMemberProducers($data->eStructure, $data->ccMember);

	$data->eStructure['admins'] = \structure\MemberLib::countAdminsByStructure($data->eStructure);

};

new Page(function($data) {

		$data->eStructure = \structure\StructureLib::getById(GET('id'))->validate('canManage');

		// ⚠️ Les groupes ont leur propre écran des membres, sur `/groupe/{id}/membres`, avec leurs
		// sous-groupes et leurs gestes. Celui-ci porte des choses qu'un groupe n'a pas — les
		// utilisateurs fantômes, les présences — et un favori ne doit pas ramener à un écran qui
		// ne lui appartient plus.
		if($data->eStructure->isCommunity()) {
			throw new NotExpectedAction('Les membres d\'un groupe se gèrent sur son propre écran');
		}

	})
	->get('/ferme/{id}/equipe', function($data) use($getAccess) {

		\structure\MemberLib::setView('viewTeamCategory', $data->eStructure, \structure\Member::TEAM);

		if($data->eStructure->acceptProduction()) {

			// La navigation s'arrête à l'année en cours : l'équipe d'une année à venir n'est faite que des
			// arrivées déjà prévues, que l'année en cours montre déjà.
			$data->year = min(GET('year', 'int', \Clock::year()), \Clock::year());

			[$data->cInvite, $data->cInviteFinished] = \structure\InviteLib::splitFinished(\structure\InviteLib::getMembersByStructure($data->eStructure));

			// Une personne sans compte déjà invitée le montre sur sa ligne : l'invitation en cours qui la
			// désigne se reprend des invitations de l'écran, sans autre lecture.
			$invitesByMember = [];

			// Une personne à qui l'on a retiré tout rôle, invitée de nouveau, se reconnait à l'adresse de son compte :
			// l'invitation ne la désigne pas, l'acceptation retrouvant seule son adhésion gardée.
			$invitesByEmail = [];

			foreach($data->cInvite as $eInvite) {

				if($eInvite->acceptExpired() === FALSE) {
					continue;
				}

				if($eInvite['member']->notEmpty()) {
					$invitesByMember[$eInvite['member']['id']] = $eInvite;
				} else if($eInvite['email'] !== NULL) {
					$invitesByEmail[$eInvite['email']] = $eInvite;
				}

			}

			$cTeammate = \hr\TeammateLib::getByStructure($data->eStructure);
			$cUserTeammate = $cTeammate->getColumnCollection('user', 'user');
			$cMember = \structure\MemberLib::getTeamByStructure($data->eStructure, 'user');

			foreach($cTeammate as $eTeammate) {
				if($cMember->offsetExists($eTeammate['user']['id'])) {
					$eMember = $cMember[$eTeammate['user']['id']];

					// Un compte fermé n'a plus d'adresse, et plus rien à recevoir.
					if($eMember->isRevoked()) {
						$eMember['eInvite'] = ($eMember['user']['email'] !== NULL) ? ($invitesByEmail[$eMember['user']['email']] ?? new \structure\Invite()) : new \structure\Invite();
					} else {
						$eMember['eInvite'] = $invitesByMember[$eMember['id']] ?? new \structure\Invite();
					}

					$eTeammate['eMember'] = $eMember;
				} else {
					$eTeammate['eMember'] = new \structure\Member();
				}
			}

			$data->team = \hr\TeammateLib::group($cTeammate, $data->year);

			\hr\AbsenceLib::fillUsers($data->eStructure, $cUserTeammate, $data->year.'-01-01', $data->year.'-12-31');
			\hr\TeammateLib::fillSegments($data->team, $data->year);

			$data->eStructure['admins'] = \structure\MemberLib::countAdminsByStructure($data->eStructure);

			// Ce que la règle de l'équipe garde hors de `hrTeammate` forme la section « Hors planning » : une
			// personne de l'équipe n'y figure jamais, même sans période, et personne n'apparait dans les deux.
			$data->cMemberOutside = $cMember->find(fn(\structure\Member $eMember) => $cUserTeammate->offsetExists($eMember['user']['id']) === FALSE);

			foreach($data->cMemberOutside as $eMember) {
				$eMember['structure'] = $data->eStructure;
				$eMember['isTeammate'] = FALSE;
				$eMember['eInvite'] = $invitesByMember[$eMember['id']] ?? new \structure\Invite();
			}

			if($data->eStructure->acceptHr()) {
				$data->eStructure['absencesPending'] = \hr\AbsenceLib::countPending($data->eStructure);
			} else if(
				$data->eStructure->assertProduction() and
				$cTeammate->count() >= 2
			) {
				// Dès deux personnes, la RH se propose une fois par utilisateur, tant qu'il ne l'a pas masquée.
				$data->tip = \structure\TipLib::pickOne($data->eUserOnline, 'hr-start');
			}

		} else {
			$getAccess($data);
		}

		throw new DynamicAction($data);

	});

new Page()
	->get('show', function($data) {

		$data->eMember = \structure\MemberLib::getById(GET('id'))->validate('canWrite');
		$data->eStructure = \structure\StructureLib::getById($data->eMember['structure']);
		$data->eMember['structure'] = $data->eStructure;

		$data->cPresence = \hr\PresenceLib::getByUser($data->eMember['structure'], $data->eMember['user']);

		$eTeammate = \hr\TeammateLib::getByUser($data->eStructure, $data->eMember['user']);

		if($eTeammate->notEmpty()) {
			$eTeammate['structure'] = $data->eStructure;
			$eTeammate['cPresence'] = $data->cPresence;
		}

		$data->eMember['eTeammate'] = $eTeammate;
		$data->eMember['isTeammate'] = $eTeammate->notEmpty();
		if($data->eMember->acceptInvite()) {
			$data->eMember['eInvite'] = \structure\InviteLib::getPendingByMember($data->eMember);
		} else if(
			$data->eMember->isRevoked() and
			$data->eMember['user']['email'] !== NULL
		) {
			$data->eMember['eInvite'] = \structure\InviteLib::getPendingByEmail($data->eStructure, $data->eMember['user']['email']);
		} else {
			$data->eMember['eInvite'] = new \structure\Invite();
		}
		$data->cAbsence = $data->eStructure->acceptHr() ? \hr\AbsenceLib::getByUser($data->eStructure, $data->eMember['user']) : new Collection();

		throw new DynamicAction($data);

	});

(new Page(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('acceptGhost', 'canManage');

		\user\ConnectionLib::checkLogged();

	}))
	->get('updatePrivate', function($data) {

		$data->eUserOnline = \user\UserLib::getById(GET('user'))->validate('isPrivate');

		if(\structure\MemberLib::isMember($data->eUserOnline, $data->eStructure, NULL) === FALSE) {
			throw new NotAllowedAction('Not member');
		}

		throw new ViewAction($data);

	})
	->post('doUpdatePrivate', function($data) {

		$eUser = \user\UserLib::getById(POST('user'))->validate('isPrivate');

		if(\structure\MemberLib::isMember($eUser, $data->eStructure, NULL) === FALSE) {
			throw new NotAllowedAction('Not member');
		}

		$fw = new FailWatch();

		$eUser->build(['firstName', 'lastName'], $_POST);

		$fw->validate();

		\user\UserLib::update($eUser, ['firstName', 'lastName']);

		throw new BackAction('structure', 'Member::userUpdated');

	})
	->post('doDeletePrivate', function($data) {

		$eUser = \user\UserLib::getById(POST('user'))->validate('isPrivate');

		$fw = new FailWatch();

		\structure\MemberLib::deleteGhostUser($data->eStructure, $eUser);

		$fw->validate();

		throw new ReloadAction('structure', 'Member::userDeleted');

	});

new \structure\MemberPage(function($data) {

		if(OTF_DEMO) {
			throw new \FailAction('user\User::demo.write');
		}

	})
	->write('doLeave', function($data, \structure\Member $e) {

		$fw = new FailWatch();

		\structure\MemberLib::leave($e);

		$fw->validate();

		throw new RedirectAction('/?success=structure\\Member::left');

	}, validate: ['canSelf'])
	->doUpdateProperties('doUpdateSoilColor', ['viewSoilColor'], fn($data) => throw new ReloadAction(), validate: ['canSelf'])
	->doUpdateProperties('doUpdateSoilOverlay', ['viewSoilOverlay'], fn($data) => throw new ReloadAction(), validate: ['canSelf'])
	->doUpdateProperties('doUpdateSoilTasks', ['viewSoilTasks'], fn($data) => throw new ReloadAction(), validate: ['canSelf'])
	// La carte a déjà basculé d'elle-même : un rechargement la reconstruirait pour rien
	->doUpdateProperties('doUpdateMapBasemap', ['viewMapBasemap'], fn($data) => throw new VoidAction(), validate: ['canSelf'])
	->doUpdateProperties('doUpdatePlanningHideDone', ['viewPlanningHideDone'], fn($data) => throw new ReloadAction(), validate: ['canSelf'])
	->doUpdateProperties('doUpdatePlanningHarvestQuick', ['viewPlanningHarvestQuick'], fn($data) => throw new ReloadAction(), validate: ['canSelf'])
	->doUpdateProperties('doUpdatePlanningSales', ['viewPlanningSales'], fn($data) => throw new ReloadAction(), validate: ['canSelf'])
	->doUpdateProperties('doUpdatePlanningMine', ['viewPlanningMine'], fn($data) => throw new ReloadAction(), validate: ['canSelf'])
	->doUpdateProperties('doUpdateWeatherModel', ['viewWeatherModel'], function($data) {

		// La météo se relit ici pour que la vue ne redessine que la tête : la structure du membre ne
		// porte que son identifiant, et la prévision se lit à sa position.
		$data->eStructure = \structure\StructureLib::getById($data->e['structure']);
		$data->weather = \structure\WeatherAiLib::getByStructure($data->eStructure, $data->e);

		throw new ViewAction($data);

	}, validate: ['canSelf'])
	->doUpdateProperties('doUpdateSellingPreparing', ['viewSellingPreparing'], fn($data) => throw new RedirectAction(\structure\StructureUi::urlSellingSalesAll($data->e['structure'])), validate: ['canSelf'])
	->quick(['viewAnalyzeComposition'], [
		'viewAnalyzeComposition' => fn() => throw new ReloadAction()
	])
	->doDelete(function($data) {

		$eStructure = \structure\StructureLib::getById($data->e['structure'], ['id', 'type']);

		// Une structure de production garde dans son équipe la personne qui y est : seul son rôle part.
		if($eStructure->isAmap()) {
			$success = 'Member::deletedAmap';
		} else if(
			$eStructure->acceptProduction() and
			\hr\TeammateLib::isTeammate($eStructure, $data->e['user'])
		) {
			$success = 'Member::deletedRole';
		} else {
			$success = 'Member::deleted';
		}

		throw new RedirectAction(\structure\StructureUi::urlMembers($eStructure).'?success=structure\\'.$success);

	});

new \structure\MemberPage(function($data) {

		if(OTF_DEMO) {
			throw new \FailAction('user\User::demo.write');
		}

	})
	->applyElement(function($data, \structure\Member $e) {

		$e['cRole'] = \structure\RoleLib::getByStructure($e['structure']);

		// ⚠️ La structure se relit par son identifiant : celle que porte l'adhésion arrive en
		// sélection partielle, et `has()` demande `type`, `communityType` et la ligne de
		// fonctionnalités. Le même piège que `producerType` lu sur une structure déléguée.
		$data->eStructureMember = \structure\StructureLib::getById($e['structure']['id']);
		$e['structure'] = $data->eStructureMember;

		$data->cSection = \structure\CommunityFeatureAiLib::has($data->eStructureMember, 'hasCommunitySections')
			? \structure\SectionLib::getByStructure($data->eStructureMember)
			: new \Collection();

		$e['cSection'] = $data->cSection->empty()
			? new \Collection()
			: \structure\SectionLib::getByUser($e['user'], $data->eStructureMember);

	})
	->update(function($data) {

		// La confirmation du retrait de tout rôle dit, dans une structure de production, si la personne reste dans l'équipe.
		$data->e['isTeammate'] = (
			$data->eStructureMember->acceptProduction() and
			\hr\TeammateLib::isTeammate($data->eStructureMember, $data->e['user'])
		);

		throw new ViewAction($data);

	// L'état d'abord : `canUpdateRole()` lit le rôle, qu'une adhésion sans accès n'a plus.
	}, addValidate: ['acceptUpdateRole', 'canUpdateRole'])
	/**
	 * ⚠️ Le rôle est écrit par `ModulePage`, qui appelle `MemberLib::update()` tout seul ; ce
	 * gestionnaire ne s'occupe que du rangement, qui ne vit pas sur `Member` mais dans une colonne
	 * json de `Section`.
	 */
	->doUpdate(function($data) {

		\structure\SectionLib::updateUserSections($data->e, $data->cSection, POST('sections', 'array', []));

		throw new ReloadAction('structure', 'Member::updated');

	}, addValidate: ['acceptUpdateRole', 'canUpdateRole']);
?>
