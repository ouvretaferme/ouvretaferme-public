<?php
// Invitation d'un membre
new \structure\InvitePage(function($data) {

		$data->eStructure = \structure\StructureLib::getById(REQUEST('structure'))->validate('canManage');

	})
	->getCreateElement(function($data) {

		// Seulement les ouverts : inviter dans un sous-groupe clos est la seule chose que clore
		// empêche. Vide sur une ferme comme sur un groupe qui n'en a aucun, et le formulaire ne
		// propose alors pas un choix sans option.
		$cSection = \structure\SectionLib::getByStructure($data->eStructure, onlyOpen: TRUE);

		// Relue parmi les personnes sans compte de la structure : le formulaire affiche son nom, et un
		// identifiant forgé ne doit pas nommer une personne d'ailleurs. `build()` juge seul à l'écriture.
		$eMember = \structure\MemberLib::getGhostById($data->eStructure, REQUEST('member', '?int'));

		$cRole = \structure\RoleLib::getByStructure($data->eStructure);

		// « Redonner un accès » nomme l'adhésion gardée d'une personne de l'équipe, relue dans la structure. Son
		// paramètre n'est pas `member` : celui-là repart au POST et désigne la personne sans compte que
		// l'invitation rattache, alors que l'acceptation retrouve seule l'adhésion gardée du compte qui accepte.
		$eMemberRevoked = \structure\MemberLib::getRevokedById($data->eStructure, GET('revoked', '?int'));

		return new \structure\Invite([
			'structure' => $data->eStructure,
			'member' => $eMember,
			'eMemberRevoked' => $eMemberRevoked,
			// L'adresse d'un compte ne se montre qu'à qui elle est communiquée, comme sur sa fiche.
			'email' => ($eMemberRevoked->notEmpty() and $eMemberRevoked['user']['visibility'] === \user\User::PUBLIC) ? $eMemberRevoked['user']['email'] : NULL,
			'type' => \structure\Invite::MEMBER,
			'cRole' => $cRole,

			// Le rôle arrive posé quand le lien le porte, relu dans `cRole` et jamais dans la
			// requête : la liste des choix légitimes sert de filtre, comme pour le sous-groupe.
			'memberRole' => $cRole[GET('memberRole', '?int')] ?? new \structure\Role(),
			'cSection' => $cSection,

			// La destination arrive posée quand le lien la porte — le bandeau d'accueil nomme le
			// sous-groupe qu'on vient de créer, et c'est celui-là qu'on veut peupler.
			//
			// ⚠️ Relue dans `cSection` et jamais dans la requête : un id forgé afficherait le nom
			// d'un sous-groupe d'un autre groupe dans un formulaire qui ne l'accepterait pourtant
			// pas. La liste des choix légitimes est déjà là, elle sert de filtre.
			'memberSection' => $cSection[GET('memberSection', '?int')] ?? new \structure\Section()
		]);

	})
	->create(fn($data) => throw new ViewAction($data), page: 'createMember')
	->doCreate(function($data) {

		// Le rôle bâti ne porte que son id : la liste des choix légitimes le complète.
		$data->e['memberRole'] = $data->e['cRole'][$data->e['memberRole']['id']];

		if($data->e->isAmapProducer()) {
			$url = match(POST('origin')) {
				'producer' => \structure\StructureUi::urlAmapProducers($data->eStructure),
				'referent' => \structure\StructureUi::urlAmapReferents($data->eStructure),
				default => \structure\StructureUi::urlMembers($data->eStructure)
			};
		} else {
			$url = \structure\StructureUi::urlMembers($data->eStructure);
		}

		throw new RedirectAction($url.'?success=structure\\Invite::created');

	}, page: 'doCreateMember');

// Le rôle se choisit parmi ceux de la structure, et le sous-groupe parmi ses sous-groupes ouverts,
// comme à la création : ces listes sont celles que `memberRole` et `memberSection` vérifient.
new \structure\InvitePage()
	->applyElement(function($data, \structure\Invite $e) {

		$e['cRole'] = \structure\RoleLib::getByStructure($e['structure']);
		$e['cSection'] = \structure\SectionLib::getByStructure($e['structure'], onlyOpen: TRUE);

	})
	->update(page: 'updateRole', validate: ['canUpdate', 'acceptUpdateRole'])
	->doUpdateProperties('doUpdateRole', ['memberRole', 'memberSection'], fn() => throw new ReloadAction('structure', 'Invite::updatedRole'), validate: ['canUpdate', 'acceptUpdateRole']);

new \structure\InvitePage()
	->doUpdateProperties('doUpdateNotifyEmail', ['notifyEmail'], fn() => throw new ReloadAction('structure', 'Invite::notification'))
	->write('doResend', function($data) {

		\structure\InviteLib::resend($data->e);

		throw new ReloadAction('structure', 'Invite::resent');

	}, validate: ['canWrite', 'acceptResend'])
	->doDelete(fn() => throw new ReloadAction());

new Page(function($data) {
		\user\ConnectionLib::checkLogged();
	})
	->get( 'join', function($data) {
		throw new ViewAction($data);
	})
	->post('doJoin', function($data) {

		$data->eInvite = \structure\InviteLib::getByKey(POST('key'));

		$fw = new FailWatch();

		\structure\InviteLib::validateJoin($data->eInvite);

		$fw->validate();

		\structure\InviteLib::prepareShare($data->eInvite);
		\structure\InviteLib::prepareTos($data->eInvite, $data->eUserOnline);

		if($data->eInvite['tosRequired']) {
			\user\SignUpLib::checkTos($_POST);
		}

		$eStructureProducer = new \structure\Structure();
		$eSystem = new \structure\System();

		$rights = [];
		$propertiesProducer = [];

		// ⚠️ `POST(…, 'int')` rend 0 sur un champ absent : seul un 0 réellement posté vaut le choix
		// de n'apporter aucune ferme.
		$withoutFarm = (
			$data->eInvite['structure']->acceptJoinWithoutFarm() and
			post_exists('structureProducer') and
			POST('structureProducer', 'int') === 0
		);

		if(
			$data->eInvite['cStructureShareable']->notEmpty() and
			$withoutFarm === FALSE
		) {

			// ⚠️ La case ne se demande que si le groupe a écrit des règles : sans texte, elle
			// demanderait d'accepter le vide. Cocher ses autorisations est alors l'accord.
			if(
				$data->eInvite['structure']->acceptRules() and
				$data->eInvite['eCommunity']['rules'] !== NULL
			) {
				\structure\StructureLib::checkCharter($_POST);
			}

			$eStructureProducer = $data->eInvite['cStructureShareable'][POST('structureProducer', 'int')] ?? new \structure\Structure();

			if($eStructureProducer->empty()) {
				\structure\Affiliation::fail('structureProducer.check');
			} else {

				$classCommunity = \structure\StructureLib::getCommunityClass($data->eInvite['structure']);
				$propertiesProducer = $classCommunity::getPropertiesShare($eStructureProducer);

				if($propertiesProducer !== []) {
					$eStructureProducer->build($propertiesProducer, $_POST);
				}

				$eSystem = \structure\InviteLib::preparePlace($eStructureProducer, $_POST);

			}

			// Ce que la ferme a coché sur l'écran d'entrée, borné à la demande du groupe par
			// `AffiliationAiLib::create()` — le POST se forge.
			foreach(array_keys(\structure\CommunityRightAiLib::RIGHTS) as $right) {
				$rights[$right] = POST($right, 'array', []);
			}

		}

		if($data->eInvite->isAmapProducer()) {
			$eStructureProducer = \structure\InviteLib::buildInviteAmapProducer($data->eInvite, $_POST);
		}

		// ⚠️ Relue dans la liste des structures rattachables et jamais dans la requête : un
		// identifiant forgé ferait entrer sur la boutique une structure que l'invité n'administre
		// pas, ou qui y participe déjà.
		if($data->eInvite['type'] === \structure\Invite::PRODUCER) {

			$eStructureProducer = $data->eInvite['cStructureJoinable'][POST('structureProducer', 'int')] ?? new \structure\Structure();

			if($eStructureProducer->empty()) {
				\structure\Invite::fail('structureProducer.check');
			}

		}

		if($fw->ok()) {

			if(\structure\InviteLib::accept($data->eInvite, $data->eUserOnline, $eStructureProducer, $rights)) {

				// Après l'adhésion et non dedans : `InviteLib::accept()` ouvre sa propre transaction
				// et la rejette en entier sur échec, donc la catégorie ne s'écrit que sur un
				// rattachement qui a bien eu lieu.
				if($propertiesProducer !== []) {
					\structure\StructureLib::update($eStructureProducer, $propertiesProducer);
				}

				if($eSystem->notEmpty()) {
					\structure\SystemLib::update($eSystem, ['place', 'placeLngLat']);
				}

				if(
					$data->eInvite->isAmapProducer() or
					$data->eInvite['type'] === \structure\Invite::PRODUCER
				) {
					$arguments = '&producer='.$eStructureProducer['id'];
				} else {
					$arguments = '';
				}

				if(
					POST('origin') === 'quotation' and
					$eStructureProducer->notEmpty() and
					$eStructureProducer->acceptQuotation()
				) {
					throw new RedirectAction(\structure\StructureUi::getObserverEntries('getCategoryUrls', $eStructureProducer)['quotation']['home']);
				}

				throw new RedirectAction('/structure/invite:welcome?key='.$data->eInvite['key'].$arguments);

			} else {

				// Un refus qui dit sa raison la montre (la personne sans compte désignée a disparu, le compte a
				// déjà son propre temps) ; sans raison, c'est le code qui ne vaut plus.
				$fw->validate();

				throw new FailAction('structure\Invite::invalidUnknown');

			}


		} else {
			throw new FailAction($fw);
		}


	})
	->get('welcome', function($data) {

		$data->eInvite = \structure\InviteLib::getByKey(GET('key'), onlyPending: FALSE)->validate();

		if(
			$data->eInvite['type'] === \structure\Invite::MEMBER and
			$data->eInvite['structure']->isMember() === FALSE
		) {
			throw new NotExpectedAction();
		}

		$data->eStructureProducer = new \structure\Structure();

		// L'adresse ne porte que des identifiants : la participation relue dit si celui de la
		// structure désigne bien une entrée sur cette boutique, et non une structure quelconque.
		if(
			$data->eInvite['type'] === \structure\Invite::PRODUCER and
			get_exists('producer')
		) {

			$eStructureProducer = \structure\StructureLib::getById(GET('producer'));

			if(\structure\InviteLib::matchProducer($data->eInvite, $eStructureProducer)) {
				$data->eStructureProducer = $eStructureProducer;
			}

		}

		// L'adresse ne porte que des identifiants : la ligne relue dit si celui du producteur
		// désigne bien un rattachement que ce visiteur vient d'engager, et non celui d'un autre.
		if(
			$data->eInvite->isAmapProducer() and
			get_exists('producer')
		) {
			$eStructureProducer = \structure\StructureLib::getById(GET('producer'));
		} else {
			$eStructureProducer = new \structure\Structure();
		}

		\structure\InviteLib::prepareWelcome($data->eInvite, $eStructureProducer, $data->eUserOnline);

		throw new ViewAction($data);

	});

new Page()
	->get('/invitation', function($data) {

		$data->eInvite = \structure\InviteLib::getByKey(GET('key'));

		\structure\InviteLib::prepareShare($data->eInvite);
		\structure\InviteLib::prepareTos($data->eInvite, $data->eUserOnline);

		throw new ViewAction($data);

	});
?>
