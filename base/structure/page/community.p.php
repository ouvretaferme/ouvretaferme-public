<?php
new \structure\StructurePage(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(GET('id'))->validate('canRead');

	})
	/**
	 * ⚠️ Deux adresses pour une route : la portée est un **segment** et non un paramètre, comme la
	 * saison du plan de culture ou la région des relevés. Le tableau de bord n'ayant pas de suffixe
	 * à lui, il prend `/section/{id}` — et l'adresse nue reste l'adresse nue.
	 *
	 * Le cast `@int` est ce qui départage les collisions : le routeur, lui, ne préfère pas un
	 * littéral à un paramètre — il range par priorité, qui vaut 5 pour tout le monde, et deux
	 * routes compatibles écrasent la même case.
	 */
	->read(['/groupe/{id}', '/groupe/{id}/section/{section@int}'], function($data) {

		// Les deux comptes se lisent avec les lectures des deux écrans qu'ils résument, jamais avec
		// une requête neuve : un chiffre calculé autrement finirait par diverger de la liste qu'il
		// annonce, et l'écran se contredirait sans que rien ne le signale. Les membres viennent de
		// CommunityAction, comme le rail.
		//
		// ⚠️ Les fermes des membres, et elles seules. La ferme projet du groupe n'appartient à
		// personne, donc à aucun sous-groupe : l'ajouter rendait un nombre qui ne se compare pas à
		// lui-même, puisque le même chiffre borné par un sous-groupe ne peut compter qu'elle. Le
		// tableau qui ventile ce total ne pouvait alors pas boucler. Le compte reste celui du
		// premier sous-onglet de l'écran des fermes, qui est aussi celui que la tuile ouvre.
		$data->farms = 0;
		$cStructure = new Collection();

		if($data->eStructure->acceptGroupStructure()) {
			$data->cAffiliation = \structure\AffiliationAiLib::getByCommunity($data->eStructure);
			$cStructure = \structure\AffiliationAiLib::findVisible($data->eStructure, \structure\AffiliationAiLib::groupByStructure($data->cAffiliation, $data->eUserOnline));
			$data->farms = $cStructure->count();
		}

		// `cSection` est chargée ici et non par CommunityAction, qui n'a pas les rattachements sous
		// la main : la tuile des fermes doit compter ce que la portée laisse voir, et seuls les
		// rattachements le disent.
		$data->cSection = \structure\SectionLib::getByStructure($data->eStructure);

		if($data->cSection->notEmpty() and $data->eStructure->acceptGroupStructure()) {

			$eSection = \structure\SectionLib::getSelected($data->cSection, GET('section'));
			$scope = \structure\SectionLib::getScope($data->eStructure);

			// ⚠️ La ventilation par sous-groupe ne se lit plus que sous portée. Le tableau qui la
			// montrait a quitté cet écran, et la seule raison qui reste de rattacher les fermes à
			// leurs sous-groupes est la tuile : sous filtre, elle doit compter ce que le filtre
			// laisse voir, sinon elle annonce le groupe entier pendant que l'écran des fermes n'en
			// montre qu'une partie. Sans portée ni choix, le compte du dessus est déjà le bon et
			// cette lecture n'a plus d'objet.
			if($eSection->notEmpty() or $scope !== NULL) {

				\structure\AffiliationAiLib::fillStructureMembers($data->cAffiliation, $cStructure);

				// Borné, on compte l'union de ses sous-groupes même sans choix : c'est la
				// différence entre un filtre de confort et une portée.
				$sections = $eSection->notEmpty() ? [$eSection['id']] : $scope;

				$data->farms = \structure\SectionLib::countStructures($data->eStructure, $cStructure, $data->cSection, $sections, $scope !== NULL);

			}

		}

		// Sous les deux chiffres, l'équipe lit ce que chaque fonctionnalité allumée a d'ouvert, par
		// les lectures de l'onglet qui le montre. Ces blocs sont ceux du groupe entier, comme leurs
		// onglets : aucune de ces lectures ne prend la portée du sous-groupe.
		$data->cCollectOpen = new Collection();
		$data->cDateEnrollmentLast = new Collection();

		if($data->eStructure->getMember()->isStaff()) {

			if($data->eStructure->acceptGroupQuotation()) {

				$data->cCollectOpen = \structure\StructureLib::getCollectOpens($data->eStructure, $data->eUserOnline);

			}

			if($data->eStructure->acceptLearning() and $data->eStructure->isNetwork()) {
				$data->cDateEnrollmentLast = \learning\DateEnrollmentLib::getLastByCommunity($data->eStructure);
			}

		}

		$data->onboardingApply = TRUE;

		// Le parcours de mise en route ne tient que l'accueil, les autres écrans n'en disent rien.
		$data->startApply = TRUE;

		// Le filtre agit ici : les deux chiffres et le tableau des sous-groupes ventilent ce que
		// le sous-groupe choisi contient.
		$data->sectionApply = TRUE;

		throw new CommunityAction($data);

	})
	/**
	 * Les fermes école, écran propre aux organismes de formation.
	 *
	 * ⚠️ Elles ne se rattachent pas : tout groupe de formation les voit toutes, et la route le
	 * dit en refusant les autres types plutôt qu'en rendant une liste vide.
	 */
	->read('/groupe/{id}/fermes/ecole', function($data) {

		$data->cStructureSchool = \structure\StructureLib::getSchools();

		// ⚠️ Tout se prépare ici, y compris les quatre chiffres : une UI ne va pas chercher sa
		// donnée, et c'est en l'oubliant qu'on avait posé deux warnings sur les saisons. La carte
		// large lit `structureSystem` pour ses valeurs et `structureProduction` pour son métier —
		// deux requêtes qu'un `foreach` dans une UI aurait multipliées par le nombre de fermes.
		foreach($data->cStructureSchool as $eStructureSchool) {

			$eStructureSchool['seasonFirst'] = $eStructureSchool->conf(\map\Configuration::class)['seasonFirst'];
			$eStructureSchool['seasonLast'] = $eStructureSchool->conf(\map\Configuration::class)['seasonLast'];

			// Nulle quand les ventes vont aussi loin que celles de la source : la carte n'annonce
			// une coupure que lorsqu'il y en a une.
			$eStructureSchool['salesUntil'] = $eStructureSchool['source']->notEmpty()
				? \structure\SchoolAiLib::getSalesUntil($eStructureSchool['id'], $eStructureSchool['source']['id'])
				: NULL;

			// ⚠️ Les trois destinations se préparent ici : `getProductionUrl()` pèse les droits du
			// lecteur pour choisir entre le planning et l'assolement, et une UI ne va pas chercher
			// sa donnée. Le profil ne s'ouvre que sous le drapeau, la fiche n'existant pas en
			// production — un bouton mort vaut moins que pas de bouton.
			$eStructureSchool['urls'] = array_filter([
				['color-brand', s("Profil"), \structure\StructureUi::urlProfile($eStructureSchool)],
				['production', s("Cultures"), $eStructureSchool->getProductionUrl()],
				// ⚠️ L'analyse et non la liste : une ferme école porte 366 ventes de synthèse,
				// agrégées par semaine et par produit. Les parcourir n'apprend rien — ce qui
				// s'étudie, ce sont les montants par culture et par saison, que l'analyse rend.
				['commercialisation', s("Ventes"), \structure\StructureUi::urlAnalyzeCommercialisation($eStructureSchool)]
			], fn(array $destination) => $destination[2] !== '' and $destination[2] !== NULL);

			$eSystem = \structure\SystemLib::getByStructure($eStructureSchool);
			$cProduction = \structure\ProductionLib::getByStructure($eStructureSchool);

			$eStructureSchool['place'] = $eSystem['place'] ?? NULL;

			$eStructureSchool['figures'] = \structure\ProfileAiLib::getSystemFigures($eSystem, $cProduction);

			// ⚠️ La carte ne montre que les ateliers de culture : c'est ce qu'on vient étudier sur
			// une ferme école. `find()` et non `filter()`, qui mute la collection en place — celle
			// que `getSystemFigures()` vient de compter pour son « 1 atelier ».
			$eStructureSchool['cProduction'] = $cProduction->find(fn($eProductionSchool) => in_array($eProductionSchool['fqn'], ['maraichage', 'legume'], TRUE));

		}

		throw new CommunityAction($data);

	}, addValidate: ['acceptSchool'])
	/**
	 * Les fermes que la personne connectée partage avec ce groupe.
	 *
	 * Un écran et non un panneau : la liste porte deux ensembles, un geste destructeur et un
	 * assistant, et le panneau se fermait à chaque retrait — le geste renvoyait sur l'écran d'où
	 * l'on venait, pas sur la liste qu'on était en train de parcourir.
	 */
	->read('/groupe/{id}/mes-fermes', function($data) {

		$data->cStructure = \structure\StructureLib::getShareableByUser($data->eUserOnline, $data->eStructure);
		$data->cAffiliation = \structure\AffiliationAiLib::getByCommunity($data->eStructure);
		$data->cStructureShared = \structure\StructureLib::findSharedByUser($data->eUserOnline, \structure\AffiliationAiLib::groupByStructure($data->cAffiliation, $data->eUserOnline));
		$data->hasProducer = \structure\StructureLib::hasProducerByUser($data->eUserOnline);

		throw new CommunityAction($data);

	}, addValidate: ['acceptGroupStructure'])
	->read('/groupe/{id}/fermes/gerer', function($data) {

		$data->eStructure->validate('canManage');

		$data->cAffiliation = \structure\AffiliationAiLib::getByCommunity($data->eStructure);
		$cStructure = \structure\AffiliationAiLib::groupByStructure($data->cAffiliation, $data->eUserOnline);

		\structure\AffiliationAiLib::fillStructureMembers($data->cAffiliation, $cStructure);

		\structure\DuplicateAiLib::fillStructures($data->eStructure, $cStructure, $data->eStructure->assertDuplicate());

		\structure\AffiliationAiLib::fillSharing($data->eStructure, $cStructure);
		$seasons = \series\SeriesLib::getLastSeasons($cStructure->find(fn($eStructure) => $eStructure['sharing']['shareProduction']));

		foreach($cStructure as $eStructure) {
			$eStructure['seasonSeries'] = $seasons[$eStructure['id']] ?? NULL;
		}

		$data->cStructure = $cStructure;

		// Une ferme appartient à un membre, donc aux sous-groupes de ce membre : elle est rangée,
		// et le filtre la borne ici comme sur l'écran de consultation.
		$data->sectionApply = TRUE;

		throw new CommunityAction($data);

	})
	->read(['/groupe/{id}/fermes', '/groupe/{id}/fermes/{section@int}'], function($data) {

		$data->eCommunity = \structure\CommunityLib::getByStructure($data->eStructure);

		$data->hasShareable = FALSE;
		$data->hasProducer = FALSE;
		$data->hasDuplicate = FALSE;
		$data->cStructureMine = new \Collection();
		$data->cStructureMember = new \Collection();
		$data->cStructureProject = new \Collection();

		$data->hasStructures = $data->eStructure->acceptGroupStructure();

		if($data->hasStructures) {

			$data->cAffiliation = \structure\AffiliationAiLib::getByCommunity($data->eStructure);
			$cStructure = \structure\AffiliationAiLib::findVisible($data->eStructure, \structure\AffiliationAiLib::groupByStructure($data->cAffiliation, $data->eUserOnline));

			$canDuplicate = $data->eStructure->assertDuplicate();

			$data->hasDuplicate = \structure\DuplicateAiLib::isShown($data->eStructure, $canDuplicate);

			\structure\DuplicateAiLib::fillStructures($data->eStructure, $cStructure, $canDuplicate);

			\structure\AffiliationAiLib::fillSharing($data->eStructure, $cStructure);
			$seasons = \series\SeriesLib::getLastSeasons($cStructure->find(fn($eStructure) => $eStructure['sharing']['shareProduction']));

			foreach($cStructure as $eStructure) {
				$eStructure['seasonSeries'] = $seasons[$eStructure['id']] ?? NULL;
			}

			$data->cStructureMine = $cStructure->find(fn($eStructure) => $eStructure['isMine']);
			$data->cStructureMember = $cStructure->find(fn($eStructure) => $eStructure['isMine'] === FALSE);

			$data->cStructureProject = \structure\StructureLib::getProjectByCommunity($data->eStructure);

			$data->hasShareable = \structure\StructureLib::getShareableByUser($data->eUserOnline, $data->eStructure)->notEmpty();
			$data->hasProducer = \structure\StructureLib::hasProducerByUser($data->eUserOnline);

			$cStructureAdmin = new \Collection();

			foreach([$data->cStructureMine, $data->cStructureMember, $data->cStructureProject] as $cStructure) {
				foreach($cStructure as $eStructure) {
					$cStructureAdmin[$eStructure['id']] = $eStructure;
				}
			}

			// La carte nomme qui répond de la ferme **dans ce groupe**, et non les administrateurs
			// de la ferme : sept noms s'y affichaient, dont plusieurs n'étaient jamais entrés dans
			// le groupe. C'est la table du lien qui répond, pas une intersection.
			\structure\AffiliationAiLib::fillStructureMembers($data->cAffiliation, $cStructureAdmin);

			\structure\FeatureLib::memorizeByStructures($data->cStructureMine);

			// Sous le drapeau, et la condition n'est pas décorative : FEATURE_INRAE est faux en
			// production, où la fiche refuse toute requête. Poser son adresse sans condition
			// remplacerait, sur un écran déjà livré, le lien vers l'espace de travail par un lien
			// mort. Les fermes des autres membres et celles d'étude n'avaient aucun lien : elles en
			// gagnent un, mais seulement là où il mène quelque part.
			foreach([$data->cStructureMine, $data->cStructureMember, $data->cStructureProject] as $cStructure) {

				foreach($cStructure as $eStructure) {

					if(FEATURE_INRAE) {
						$eStructure['url'] = \structure\StructureUi::urlProfile($eStructure);
					} else {
						$eStructure['url'] = $cStructure === $data->cStructureMine ? $eStructure->getAccessUrl() : '';
					}

				}

			}

		}

		$data->onboardingApply = TRUE;
		$data->sectionApply = TRUE;
		$data->mapApply = $data->hasStructures;

		throw new CommunityAction($data);

	})
	/**
	 * Les membres du groupe, en annuaire ou en table selon qui regarde.
	 *
	 * ⚠️ Les invitations ne se lisent que pour qui peut les administrer : l'annuaire n'en montre
	 * aucune, et les préparer pour tout le monde ferait payer une requête à chaque membre.
	 */
	->read(['/groupe/{id}/membres', '/groupe/{id}/membres/{section@int}'], function($data) {

		$data->cInvite = new \Collection();
		$data->cInviteFinished = new \Collection();

		if($data->eStructure->canManage()) {

			[$data->cInvite, $data->cInviteFinished] = \structure\InviteLib::splitFinished(\structure\InviteLib::getMembersByStructure($data->eStructure));

			// Le menu d'un membre dit, sur sa propre ligne, qui peut changer son rôle à sa place :
			// la réponse dépend du nombre d'administrateurs, et l'UI ne va pas le chercher.
			// Préparé sous `canManage()` comme les invitations, le menu n'existant que là.
			//
			// ⚠️ Posé sur `$data->e` et non sur `$data->eStructure` : `ModulePage` charge le sien
			// depuis `{id}`, le prélude de cette page charge l'autre, et ce sont deux instances de
			// la même ligne. Une propriété posée sur l'une est invisible depuis l'autre, et c'est
			// `$data->e` que la vue passe à l'UI.
			$data->e['admins'] = \structure\MemberLib::countAdminsByStructure($data->eStructure);

		}

		$data->onboardingApply = TRUE;
		$data->onboardingHidden = [\structure\StructureCommunityLib::INVITE];
		$data->sectionApply = TRUE;

		throw new CommunityAction($data);

	})
	/**
	 * Le tableau de bord maraîchage du groupe.
	 *
	 * ⚠️ **L'équipe du réseau seule**, et c'est la garde qui porte tout l'écran : un producteur
	 * membre du groupe y lirait des agrégats du réseau auxquels son rattachement ne lui donne pas
	 * accès ferme par ferme. La lecture se fait sur `$eStructure->getMember()` et jamais sur
	 * `$data->eMember`, que `CommunityAction::render()` pose **après** cette closure.
	 *
	 * ⚠️ `sectionApply` est VRAI, comme la comparaison : un enseignant lit le tableau de bord de
	 * sa promotion, pas celui de l'établissement.
	 */
	->read(['/groupe/{id}/maraichage', '/groupe/{id}/maraichage/{section@int}'], function($data) {

		if($data->eStructure->acceptGroupStructure() === FALSE) {
			throw new NotAllowedAction();
		}

		if($data->eStructure->acceptCommunityFeature('hasCommunityVegetable') === FALSE) {
			throw new NotAllowedAction();
		}

		if($data->eStructure->getMember()->isStaff() === FALSE) {
			throw new NotAllowedAction();
		}

		// Toutes les fermes rattachées, partageuses ou non : le tableau de bord agrège le réseau
		// entier sous un plancher, et `prepareVegetable()` ne se sert du partage que pour décider
		// si les liens vers la comparaison mènent quelque part.
		$data->cAffiliation = \structure\AffiliationAiLib::getByCommunity($data->eStructure);
		$data->cStructure = \structure\AffiliationAiLib::groupByStructure($data->cAffiliation, $data->eUserOnline);

		// ⚠️ `cUser` est ce qui rend la liste filtrable : `CommunityAction::filterStructures()`
		// range une ferme par la personne qui l'a mise dans le groupe, et saute toute collection
		// qui ne la porte pas. Sans cette pose, le sous-groupe ne bornerait rien.
		\structure\AffiliationAiLib::fillStructureMembers($data->cAffiliation, $data->cStructure);

		// Le groupe entier, que le filtre du sous-groupe ne touche pas : il remplace `cStructure`
		// par une collection neuve. Le bloc vide d'un sous-groupe y lit si les statistiques de tout
		// le groupe s'affichent, avant d'y renvoyer (`CommunityAction::prepareVegetable()`).
		$data->cStructureCommunity = $data->cStructure;

		$data->sectionApply = TRUE;

		// ⚠️ Les agrégats ne se lisent pas ici : `CommunityAction` filtre `cStructure` par le
		// sous-groupe **après** cette route, et les lire maintenant les prendrait sur la liste
		// entière — la population affichée sous chaque carte serait alors fausse.
		$data->vegetableApply = TRUE;

		throw new CommunityAction($data);

	})
	/**
	 * Comparer une espèce entre les fermes du groupe.
	 *
	 * ⚠️ `sectionApply` est VRAI : le sous-groupe borne les fermes comparées, et c'est ce qui rend
	 * l'écran utile à un enseignant — sa promotion, pas tout l'établissement. Les fermes arrivent
	 * donc déjà filtrées par `CommunityAction`, portée du lecteur comprise.
	 */
	->read(['/groupe/{id}/comparer', '/groupe/{id}/comparer/{section@int}'], function($data) {

		if($data->eStructure->acceptGroupStructure() === FALSE) {
			throw new NotAllowedAction();
		}

		// Toutes les fermes rattachées : le partage se filtre dans `prepareCompare()`, après que le
		// sous-groupe a réduit la liste. Filtrer ici perdrait ce second filtre, et le compte des
		// fermes comparées se dirait sur la mauvaise population.
		$data->cAffiliation = \structure\AffiliationAiLib::getByCommunity($data->eStructure);
		$data->cStructure = \structure\AffiliationAiLib::findVisible($data->eStructure, \structure\AffiliationAiLib::groupByStructure($data->cAffiliation, $data->eUserOnline));

		// ⚠️ `cUser` est ce qui rend la liste filtrable : `CommunityAction::filterStructures()`
		// range une ferme par la personne qui l'a mise dans le groupe, et saute toute collection
		// qui ne la porte pas. Sans cette pose, l'écran comparerait tout l'établissement à un
		// étudiant borné à sa promotion.
		\structure\AffiliationAiLib::fillStructureMembers($data->cAffiliation, $data->cStructure);

		// Le groupe entier, que le filtre du sous-groupe ne touche pas. Le bloc « Rien à comparer
		// pour l'instant » d'un sous-groupe y lit si la comparaison de tout le groupe a une espèce à
		// proposer, avant d'y renvoyer (`CommunityAction::hasCompareCommunity()`).
		$data->cStructureCommunity = $data->cStructure;

		$data->sectionApply = TRUE;

		// ⚠️ Les espèces et les cultures ne se lisent pas ici : `CommunityAction` filtre
		// `cStructure` par le sous-groupe **après** cette route, et les lire maintenant les
		// prendrait sur la liste entière. Elles se préparent donc dans l'action, derrière ce
		// drapeau, comme l'appel à l'action et le filtre lui-même.
		$data->compareApply = TRUE;

		throw new CommunityAction($data);

	})
	/**
	 * La gestion des sous-groupes, réservée à qui administre le groupe.
	 *
	 * ⚠️ Le sélecteur ne s'y rend pas, et `sectionApply` reste donc à FAUX : filtrer par un
	 * sous-groupe la liste de tous les sous-groupes ne rendrait qu'elle-même.
	 */
	->read('/groupe/{id}/sous-groupes', function($data) {

		$data->eStructure->validate('canManage');

		// L'écran suit sa fonctionnalité et non l'accueil des fermes : le menu ne mène plus ici
		// quand elle est éteinte, et une adresse se tape.
		if(\structure\CommunityFeatureAiLib::has($data->eStructure, 'hasCommunitySections') === FALSE) {
			throw new NotAllowedAction();
		}

		$cSection = \structure\SectionLib::getByStructure($data->eStructure);

		\structure\SectionLib::fillUsers(
			$cSection,
			\structure\MemberLib::getUsersByStructure($data->eStructure)
		);

		// Les clos ferment la liste, dans leur propre tbody : la séparation se décide ici et non
		// par un tri sur la colonne `status`, qui marcherait par accident de l'ordre alphabétique
		// de ses deux valeurs.
		$data->cSection = $cSection->find(fn(\structure\Section $eSection) => $eSection->isClosed() === FALSE);
		$data->cSectionClosed = $cSection->find(fn(\structure\Section $eSection) => $eSection->isClosed());

		// Inviter depuis une ligne, c'est faire entrer quelqu'un que ce sous-groupe borne : le rôle
		// borné voyage donc jusqu'au bouton. Aucun rôle borné, aucun préréglage — l'invitation
		// demandera lequel.
		$data->eRoleSection = \structure\RoleLib::getByStructure($data->eStructure)
			->find(fn(\structure\Role $eRole) => $eRole['sections'] === \structure\Role::OWN)
			->first() ?? new \structure\Role();

		throw new CommunityAction($data);

	});

new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(REQUEST('id'))->validate('canRead');

		if($data->eStructure->acceptGroupStructure() === FALSE) {
			throw new NotExpectedAction();
		}

	})
	->get('shareStructure', function($data) {

		$data->cStructure = \structure\StructureLib::getShareableByUser($data->eUserOnline, $data->eStructure);
		$data->eCommunity = \structure\CommunityLib::getByStructure($data->eStructure);
		$data->members = \structure\MemberLib::countByStructure($data->eStructure);

		// La ferme se relit dans les partageables et jamais dans la requête : System::canWrite()
		// protège l'écriture, rien ne protège l'affichage d'un identifiant forgé.
		$data->eStructureProducer = $data->cStructure[GET('structureProducer', 'int')] ?? new \structure\Structure();

		// Le panneau ne porte plus que l'assistant : sans ferme choisie, il n'a rien à conduire et
		// l'écran des fermes répond à sa place.
		if($data->eStructureProducer->empty()) {
			throw new RedirectAction(\structure\StructureUi::urlCommunityFarmsMine($data->eStructure));
		}

		$data->eSystem = \structure\SystemLib::getByStructure($data->eStructureProducer);
		$data->eSystem['structure'] = $data->eStructureProducer;
		$data->eSystem['cProduction'] = \structure\ProductionLib::getByStructure($data->eStructureProducer);

		throw new ViewAction($data);

	})
	->post('doShareStructure', function($data) {

		$fw = new FailWatch();

		// ⚠️ La case ne se demande que si le groupe a écrit des règles : sans texte, elle
		// demanderait d'accepter le vide, et c'est alors cocher ses autorisations qui est l'accord.
		if(
			$data->eStructure->acceptRules() and
			\structure\CommunityLib::getByStructure($data->eStructure)['rules'] !== NULL
		) {
			\structure\StructureLib::checkCharter($_POST);
		}

		$cStructure = \structure\StructureLib::getShareableByUser($data->eUserOnline, $data->eStructure);

		$eStructureProducer = $cStructure[POST('structureProducer', 'int')] ?? new \structure\Structure();

		$propertiesProducer = [];

		if($eStructureProducer->empty()) {
			\structure\Affiliation::fail('structureProducer.check');
		} else {

			$classCommunity = \structure\StructureLib::getCommunityClass($data->eStructure);
			$propertiesProducer = $classCommunity::getPropertiesShare($eStructureProducer);

			if($propertiesProducer !== []) {
				$eStructureProducer->build($propertiesProducer, $_POST);
			}

		}

		if($fw->ok()) {

			// ⚠️ Ce que la ferme a coché, et non plus ce que le groupe demandait : c'est tout le
			// lot. Le bornage à la demande vit dans `create()`, pas ici — le POST se forge.
			$rights = [];

			foreach(array_keys(\structure\CommunityRightAiLib::RIGHTS) as $right) {
				$rights[$right] = POST($right, 'array', []);
			}

			\structure\AffiliationAiLib::create(new \structure\Affiliation([
				'user' => $data->eUserOnline,
				'structureProducer' => $eStructureProducer,
				'structureCommunity' => $data->eStructure,
			]), $rights);

			if($propertiesProducer !== []) {
				\structure\StructureLib::update($eStructureProducer, $propertiesProducer);
			}

		}

		$fw->validate();

		throw new ReloadAction('structure', 'Affiliation::created');

	})
	/**
	 * Ce qu'une ferme accorde à un groupe : un droit, un public, tout de suite.
	 *
	 * ⚠️ Le geste n'existe que **depuis la ferme** : c'est elle qui accorde, jamais le groupe, et
	 * c'est exactement ce que le gel au rattachement protège. Le contrôle est celui du retrait —
	 * la ferme se relit dans les partagées et jamais dans le POST, et être l'auteur du
	 * rattachement ne dit rien du rôle qu'on a aujourd'hui dans la ferme.
	 *
	 * ⚠️ **Une cellule à la fois, comme la demande du groupe.** Le panneau « Modifier » posait la
	 * question de tous les publics d'un coup et se refermait sur un « Enregistrer » : accorder la
	 * lecture du plan de culture à l'équipe n'a rien à voir avec l'accorder aux autres fermes, et
	 * lier ces décisions faisait dépendre quatre réponses du même geste.
	 *
	 * ⚠️ **Copier suppose de lire**, et la borne se tient dans les deux sens : retirer la lecture à
	 * un public lui retire la copie dans la même écriture, et accorder la copie sans la lecture est
	 * un état que rien ne doit pouvoir créer. L'écran ne propose pas d'interrupteur dans ce cas,
	 * mais un POST se forge.
	 */
	->post('doUpdateStructureRight', function($data) {

		$right = POST('right');
		$audience = POST('audience', 'int');

		if(
			in_array($right, \structure\CommunityRightAiLib::getAudienceRights($data->eStructure), TRUE) === FALSE or
			\structure\CommunityRightAiLib::isOptional($data->eStructure, $right) === FALSE
		) {
			throw new NotExpectedAction('Ce droit ne se règle pas ici');
		}

		$fw = new FailWatch();

		$cStructureShared = \structure\StructureLib::getSharedByUser($data->eUserOnline, $data->eStructure);

		$eStructureProducer = $cStructureShared[POST('structureProducer', 'int')] ?? new \structure\Structure();

		if(
			$eStructureProducer->empty() or
			\structure\MemberLib::isAdmin($data->eUserOnline, $eStructureProducer) === FALSE
		) {
			\structure\Affiliation::fail('structureProducer.remove');
		}

		if($fw->ok()) {

			$on = (POST('value', 'int') === 1);

			$granted = fn(string $property) => $data->eStructure->getGrantedCommunityAudiences($property, $eStructureProducer[$property]);

			if($on and \structure\CommunityRightAiLib::acceptsGrant($right, $audience, $granted) === FALSE) {
				throw new NotExpectedAction('La copie sans la lecture');
			}

			$rights = \structure\CommunityRightAiLib::getGrantChange($right, $audience, $on, $granted);

			\structure\AffiliationAiLib::setRights($eStructureProducer, $data->eStructure, $rights);

		}

		$fw->validate();

		throw new ReloadAction('structure', 'Affiliation::rightsUpdated');

	})
	->post('doRemoveStructure', function($data) {

		$fw = new FailWatch();

		// La ferme se relit dans les partagées et jamais dans le POST : un identifiant forgé
		// retirerait la ferme d'un tiers du groupe. Le contrôle d'administrateur double la lecture
		// et ne la répète pas — être l'auteur du rattachement ne dit rien du rôle qu'on a
		// aujourd'hui dans la ferme.
		$cStructureShared = \structure\StructureLib::getSharedByUser($data->eUserOnline, $data->eStructure);

		$eStructureProducer = $cStructureShared[POST('structureProducer', 'int')] ?? new \structure\Structure();

		if(
			$eStructureProducer->empty() or
			\structure\MemberLib::isAdmin($data->eUserOnline, $eStructureProducer) === FALSE
		) {
			\structure\Affiliation::fail('structureProducer.remove');
		}

		if($fw->ok()) {

			\structure\AffiliationAiLib::removeStructure($eStructureProducer, $data->eStructure);

		}

		$fw->validate();

		throw new ReloadAction('structure', 'Affiliation::deleted');

	});

new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(REQUEST('id'))->validate('canManage', 'isCommunity');

		$data->eCommunity = \structure\CommunityLib::getByStructure($data->eStructure);

	})
	/**
	 * Deux des trois ruptures, en panneau plutôt qu'en `data-confirm` : celui-ci passe par le
	 * `confirm()` du navigateur, qui ne rend que du texte brut.
	 *
	 * ⚠️ Les chiffres se préparent ici : le panneau les nomme, et une UI ne va pas chercher sa
	 * donnée. Ce sont les mêmes lectures que les écrans qu'ils résument — un compte calculé
	 * autrement finirait par contredire la liste qu'il annonce.
	 */
	->get('breakClose', function($data) {

		$data->farms = \structure\AffiliationAiLib::countStructuresByReader($data->eStructure, $data->eUserOnline);
		$data->members = \structure\MemberLib::countByStructure($data->eStructure);

		throw new ViewAction($data);

	})
	->get('breakMember', function($data) {

		$data->eMember = \structure\MemberLib::getById(GET('member'));

		if(
			$data->eMember->empty() or
			$data->eMember['structure']['id'] !== $data->eStructure['id']
		) {
			throw new NotAllowedAction();
		}

		$data->cStructureShared = \structure\StructureLib::getSharedByUser($data->eMember['user'], $data->eStructure);
		$data->cSection = \structure\SectionLib::getByUser($data->eMember['user'], $data->eStructure);

		throw new ViewAction($data);

	})
	/**
	 * Un droit, un public, tout de suite.
	 *
	 * ⚠️ Écrite à la main là où `doUpdateProperties()` ferait l'affaire ailleurs, et pour une seule
	 * raison : le courriel se décide sur le passage de FAUX à VRAI, donc sur une **photo prise
	 * avant l'écriture**. Le gestionnaire du cadriciel ne rend la main qu'après, quand la valeur
	 * d'avant a disparu.
	 *
	 * ⚠️ **Une cellule à la fois, parce que c'est une décision à la fois.** Le tableau enregistrait
	 * ses quatre cases d'un bouton ; chacune est un réglage indépendant, et les lier faisait
	 * dépendre quatre décisions du même geste.
	 *
	 * ⚠️ Les mêmes deux gardes que l'enregistrement groupé, et pour les mêmes raisons : seuls les
	 * droits **au choix** — un droit imposé n'a pas d'interrupteur, et l'accepter ici laisserait un
	 * POST forgé vider une colonne que le type impose toujours — et seuls les droits **à public**,
	 * les autres se réglant là où ils produisent leur effet. Le public posté est borné aux publics
	 * du droit : la grille rend un tiret pour les autres, et une valeur écrite là ne se retirerait
	 * plus.
	 */
	->post('doUpdateShare', function($data) {

		$right = POST('right');
		$audience = POST('audience', 'int');

		if(
			in_array($right, \structure\CommunityRightAiLib::getAudienceRights($data->eStructure), TRUE) === FALSE or
			\structure\CommunityRightAiLib::isOptional($data->eStructure, $right) === FALSE or
			in_array($audience, \structure\CommunityRightAiLib::getAudiences($right), TRUE) === FALSE
		) {
			throw new NotExpectedAction('Ce droit ne se règle pas ici');
		}

		$fw = new \FailWatch();

		// ⚠️ Lu avant l'écriture : c'est le passage de FAUX à VRAI qui déclenche la demande aux
		// fermes, pas la valeur d'arrivée. Éteindre puis rallumer sans que rien ne change ne doit
		// pas renvoyer de courriel.
		$avant = $data->eCommunity[$right];
		$avant = ($avant instanceof \Set) ? $avant->get() : (int)$avant;

		$set = new \Set($avant);
		$set->value($audience, POST('value', 'int') === 1);

		$data->eCommunity[$right] = $set;

		\structure\CommunityLib::update($data->eCommunity, [$right]);

		$fw->validate();

		// Le courriel part sur ce qui **s'ajoute** : un public de plus est une demande neuve pour
		// les fermes, même si la colonne n'était pas vide.
		if(($set->get() & ~$avant) !== 0) {

			$labels = [];

			foreach(\structure\StructureCommunityUi::getShareLabels(\structure\CommunityUi::POINT_PRODUCER) as $property => [$color, $label]) {
				if($property === $right) {
					$labels[] = $label;
				}
			}

			foreach(\structure\AffiliationAiLib::getStructuresToAsk($data->eStructure, [$right]) as $email => $names) {

				$content = \structure\StructureCommunityUi::getShareAskedMail($data->eStructure, $names, $labels);

				\main\MailLib::getInstance()
					->setTo($email)
					->setContent(...$content)
					->send();

			}

		}

		// ⚠️ Depuis la mise en route, l'écran se recharge sur l'étape du partage : sans cela, le
		// parcours rouvrirait sur la première étape, alors qu'on bascule souvent plusieurs droits.
		// L'onglet de réglage ne poste pas d'origine et recharge comme avant.
		$origin = POST('origin', \structure\CommunityStartAiUi::ORIGINS, NULL);

		if($origin !== NULL) {
			throw new RedirectAction(\structure\CommunityStartAiUi::urlStep($data->eStructure, \structure\CommunityStartAiUi::getStepNumber($data->eStructure, 'share'), $origin));
		}

		throw new ReloadAction('structure', 'Community::updated');

	})
	/**
	 * La première étape de la mise en route : ce que les membres liront du groupe.
	 *
	 * ⚠️ L'image n'est pas ici : `StructureLogoUi` poste pour son compte, comme sur l'écran de
	 * paramétrage. Cette action n'enregistre que la présentation.
	 */
	->post('doStartDescription', function($data) {

		$data->eStructure->validate('canManage', 'acceptPresentation');

		$fw = new \FailWatch();

		$data->eStructure->build(['description'], $_POST);

		$fw->validate();

		\structure\StructureLib::update($data->eStructure, ['description']);

		$fw->validate();

		throw new RedirectAction(\structure\CommunityStartAiUi::urlStep($data->eStructure, \structure\CommunityStartAiUi::getStepNumber($data->eStructure, 'feature'), POST('origin', \structure\CommunityStartAiUi::ORIGINS, \structure\CommunityStartAiUi::ORIGIN_HOME)));

	})
	/**
	 * La deuxième étape : une promotion par ligne, plusieurs d'un coup.
	 *
	 * ⚠️ Une boucle sur `SectionLib::create()` et non une insertion de collection : c'est dans le
	 * Lib que vivent les règles du module, et un lot n'en dispense pas.
	 *
	 * ⚠️ Les lignes vides se jettent avant `build()` et non par lui : « je n'ai qu'une promotion à
	 * créer » est un choix légitime, et le formulaire en propose toujours une de plus qu'on remplit.
	 *
	 * La première promotion est obligatoire (planche validée le 26/09/2026) : sans elle,
	 * personne n'a où entrer. Un envoi vide n'est donc refusé qu'au groupe qui n'en a encore aucune,
	 * et le refus se rattache au premier champ, le seul que l'écran marque obligatoire.
	 */
	->post('doStartSections', function($data) {

		$data->eStructure->validate('canManage');

		$fw = new \FailWatch();

		$names = array_filter(POST('name', 'array', []), fn(string $name) => trim($name) !== '');

		if($names === [] and \structure\SectionLib::getByStructure($data->eStructure)->empty()) {
			\structure\Section::fail('name.first', wrapper: 'name[1]');
		}

		foreach($names as $index => $name) {

			$eSection = new \structure\Section(['structure' => $data->eStructure]);

			$eSection->buildIndex(['name'], $_POST, $index);

			if($fw->ok()) {
				\structure\SectionLib::create($eSection);
			}

		}

		$fw->validate();

		throw new RedirectAction(\structure\CommunityStartAiUi::urlStep($data->eStructure, \structure\CommunityStartAiUi::getNextStepNumber($data->eStructure, 'feature'), POST('origin', \structure\CommunityStartAiUi::ORIGINS, \structure\CommunityStartAiUi::ORIGIN_HOME)));

	})
	/**
	 * La dernière étape : les invitations, au public ordinaire ou à l'équipe.
	 *
	 * ⚠️ Aucune invitation du parcours ne porte de sous-groupe : une promotion postée n'est jamais
	 * lue, et un POST forgé n'y rangerait personne.
	 *
	 * ⚠️ Les publics et leurs rôles sortent de `Structure::getAudienceRoles()`, la même
	 * table que l'écran : un type qui n'a pas d'équipe — une communauté de producteurs, dont le
	 * seul encadrement est l'administrateur — n'en propose pas et n'en accepte pas.
	 *
	 * ⚠️ Le rôle se relit dans la liste des rôles du groupe et jamais dans la requête : un
	 * identifiant forgé ferait entrer un membre en administrateur.
	 */
	->post('doStartInvites', function($data) {

		$data->eStructure->validate('canManage');

		$fw = new \FailWatch();

		// ⚠️ Le public se relit dans la table des rôles du type et jamais dans la requête : elle
		// dit quels publics ce groupe propose, et c'est le même filtre que l'écran emploie.
		$roles = $data->eStructure->getAudienceRoles();

		$audience = POST('audience', 'string', 'member');

		if(array_key_exists($audience, $roles) === FALSE) {
			throw new NotExpectedAction('Ce type de groupe ne propose pas ce public');
		}

		$fqn = $roles[$audience];

		$cRole = \structure\RoleLib::getByStructure($data->eStructure);
		$cSection = \structure\SectionLib::getByStructure($data->eStructure, onlyOpen: TRUE);

		$eRole = $cRole->find(fn(\structure\Role $eRole) => $eRole['fqn'] === $fqn)->first() ?? new \structure\Role();

		if($eRole->empty()) {
			throw new NotExpectedAction('Rôle '.$fqn.' absent de ce groupe');
		}

		foreach(preg_split('/[\s,;]+/', POST('emails', 'string', '')) as $email) {

			if(trim($email) === '') {
				continue;
			}

			$eInvite = new \structure\Invite([
				'structure' => $data->eStructure,
				'type' => \structure\Invite::MEMBER,
				'cRole' => $cRole,
				'cSection' => $cSection
			]);

			$eInvite->build(['memberRole', 'memberSection', 'email'], [
				'memberRole' => $eRole['id'],
				'memberSection' => '',
				'email' => trim($email)
			]);

			if($fw->ok()) {
				\structure\InviteLib::create($eInvite);
			}

		}

		$fw->validate();

		// La troisième étape est la dernière : l'enregistrer termine le parcours, qui n'a plus
		// rien à proposer. Le drapeau s'éteint ici et non par un second geste de l'écran, sans
		// quoi une invitation envoyée laisserait le parcours ouvert sur une étape faite.
		$eFeature = \structure\FeatureLib::readByStructure($data->eStructure);
		$eFeature['hasStart'] = FALSE;

		\structure\FeatureLib::update($eFeature, ['hasStart']);

		$fw->validate();

		throw new RedirectAction(\structure\CommunityStartAiUi::urlFinish($data->eStructure, POST('origin', \structure\CommunityStartAiUi::ORIGINS, \structure\CommunityStartAiUi::ORIGIN_HOME)));

	})
	/**
	 * Le lien d'invitation d'un groupe, créé d'un clic et jamais à l'affichage.
	 *
	 * ⚠️ Sans adresse ni limite : c'est ce qui en fait un lien, réutilisable par qui l'ouvre.
	 *
	 * Le rôle est toujours celui du public ordinaire, jamais celui de l'équipe : un lien se
	 * transfère, et l'encadrement d'un groupe ne se distribue pas par un lien qu'on recopie.
	 */
	->post('doStartLink', function($data) {

		$data->eStructure->validate('canManage');

		$fw = new \FailWatch();

		\structure\InviteLib::createLinkByStructure($data->eStructure);

		$fw->validate();

		throw new RedirectAction(\structure\CommunityStartAiUi::urlStep($data->eStructure, \structure\CommunityStartAiUi::getStepNumber($data->eStructure, 'invite'), POST('origin', \structure\CommunityStartAiUi::ORIGINS, \structure\CommunityStartAiUi::ORIGIN_HOME)));

	})
	/**
	 * Les règles que le groupe écrit, sans courriel ni redemande.
	 *
	 * ⚠️ Rien ne se redemande quand elles changent : un texte n'ouvre aucun accès, c'est le
	 * périmètre qui le fait, et lui est gelé au rattachement. Une ferme lit toujours les règles en
	 * vigueur, elles ne sont donc pas versionnées non plus.
	 */
	->post('doUpdateRules', function($data) {

		$data->eStructure->validate('acceptRules');

		$fw = new \FailWatch();

		$data->eCommunity->build(['rules'], $_POST);

		$fw->validate();

		\structure\CommunityLib::update($data->eCommunity, ['rules']);

		$fw->validate();

		throw new ReloadAction('structure', 'Community::updated');

	})
	/**
	 * Allumer ou éteindre une fonctionnalité du groupe.
	 *
	 * ⚠️ La fonctionnalité postée se relit contre la déclaration : `accepts()` refuse ce que le
	 * type ne permet pas **et** ce qui n'est pas une fonctionnalité du tout, un POST forgé ne
	 * pouvant donc pas écrire dans une colonne voisine de `structureFeature`.
	 */
	->post('doUpdateFeature', function($data) {

		$feature = POST('feature', 'string');

		if($data->eStructure->acceptCommunityFeatureSetting($feature) === FALSE) {
			throw new NotExpectedAction('Ce type de groupe ne porte pas cette fonctionnalité');
		}

		$fw = new \FailWatch();

		$eFeature = \structure\FeatureLib::readByStructure($data->eStructure);

		// La valeur arrive sous `value` et se construit sous le nom de la colonne : l'analyseur HTML
		// abaisse les noms d'attributs, un `post-hasCommunityLearning` posterait `hascommunitylearning`.
		$eFeature->build([$feature], [$feature => POST('value')]);

		$fw->validate();

		\structure\FeatureLib::update($eFeature, [$feature]);

		// ⚠️ Depuis la mise en route, l'écran se recharge sur l'étape des fonctionnalités et non
		// là où il était : sans cela, la première allumée rend l'étape « faite » et le parcours
		// rouvrirait sur la troisième, alors qu'on en allume souvent deux. L'onglet Fonctionnalités,
		// lui, ne poste pas d'origine et recharge comme avant.
		$origin = POST('origin', \structure\CommunityStartAiUi::ORIGINS, NULL);

		if($origin !== NULL) {
			throw new RedirectAction(\structure\CommunityStartAiUi::urlStep($data->eStructure, \structure\CommunityStartAiUi::getStepNumber($data->eStructure, 'feature'), $origin));
		}

		throw new ReloadAction('structure', 'Community::updated');

	})
	->post('doExcludeStructure', function($data) {

		$fw = new FailWatch();

		// La ferme se relit dans celles du groupe, pour la même raison qu'au retrait côté ferme.
		// La ferme projet que le groupe possède n'y est pas : elle arrive par Structure.parent
		// et n'a aucun rattachement à supprimer.
		$cStructure = \structure\AffiliationAiLib::getStructuresByCommunity($data->eStructure, $data->eUserOnline);

		$eStructureProducer = $cStructure[POST('structureProducer', 'int')] ?? new \structure\Structure();

		if($eStructureProducer->empty()) {
			\structure\Affiliation::fail('structureProducer.remove');
		}

		if($fw->ok()) {

			\structure\AffiliationAiLib::excludeStructure($eStructureProducer, $data->eStructure);

		}

		$fw->validate();

		throw new ReloadAction('structure', 'Affiliation::deleted');

	});

/**
 * ⚠️ Quitter un groupe n'est pas l'administrer : ce panneau s'ouvre à tout membre, là où la
 * suppression du groupe et le retrait d'un tiers demandent `canManage`. Un bloc à part plutôt
 * qu'un contrôle assoupli, pour que la porte se lise sur la page et non dans une condition.
 */
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(REQUEST('id'))->validate('canRead', 'isCommunity');

	})
	->get('breakLeave', function($data) {

		$eMember = $data->eStructure->getMember();

		if($eMember->empty()) {
			throw new NotAllowedAction();
		}

		$data->eMember = $eMember;
		$data->farms = \structure\AffiliationAiLib::countStructuresByReader($data->eStructure, $data->eUserOnline);
		$data->mine = \structure\StructureLib::getSharedByUser($data->eUserOnline, $data->eStructure)->count();

		// Le même compte que `MemberLib::leave()`, qui refusera : le panneau le dit avant.
		$data->lastAdmin = (
			$eMember['role']['admin'] and
			\structure\MemberLib::countAdminsByStructure($data->eStructure) <= 1
		);

		throw new ViewAction($data);

	})
	->get('duplicateStructure', function($data) {

		$data->eStructure->validate('canDuplicate', 'acceptDuplicate');

		$data->eStructureSource = \structure\AffiliationAiLib::getStructureInScope($data->eStructure, $data->eUserOnline, \structure\SectionLib::getScope($data->eStructure), GET('structure', 'int'))->validate('notEmpty');

		$data->asked = \structure\DuplicateAiLib::getAsked($data->eStructure);
		$data->granted = \structure\DuplicateAiLib::getGranted($data->eStructure, $data->eStructureSource);

		if(in_array(TRUE, $data->granted, TRUE) === FALSE) {
			throw new NotAllowedAction();
		}

		$data->counts = \structure\DuplicateAiLib::getCounts($data->eStructureSource, array_keys(array_filter($data->granted)));

		throw new ViewAction($data);

	})
	->post('doDuplicateStructure', function($data) {

		$data->eStructure->validate('canDuplicate', 'acceptDuplicate');

		$eStructureSource = \structure\AffiliationAiLib::getStructureInScope($data->eStructure, $data->eUserOnline, \structure\SectionLib::getScope($data->eStructure), POST('structure', 'int'))->validate('notEmpty');

		$granted = \structure\DuplicateAiLib::getGranted($data->eStructure, $eStructureSource);

		$production = POST('copyProduction', 'bool');
		$selling = POST('copySelling', 'bool');

		$fw = new FailWatch();

		if($production === FALSE and $selling === FALSE) {
			Fail::log('structure\\Structure::copyProduction.none');
		}

		if($production and $granted['production'] === FALSE) {
			Fail::log('structure\\Structure::copyProduction.refused');
		}

		if($selling and $granted['selling'] === FALSE) {
			Fail::log('structure\\Structure::copySelling.refused');
		}

		$eStructureNew = new \structure\Structure([
			'type' => \structure\Structure::PRODUCER,
			'legalEmail' => $data->eUserOnline['email'],
			'owner' => $data->eUserOnline,
		]);

		$eStructureNew->build(['name'], $_POST);

		$fw->validate();

		\structure\DuplicateAiLib::duplicate($eStructureSource, $eStructureNew, $production, $selling);

		if($production) {
			throw new RedirectAction(\structure\StructureUi::urlCultivationSeries($eStructureNew, \structure\Member::AREA).'&success=structure\\Structure::duplicated');
		} else {
			throw new RedirectAction(\structure\StructureUi::urlSellingProductsAll($eStructureNew).'?success=structure\\Structure::duplicated');
		}

	});
?>
