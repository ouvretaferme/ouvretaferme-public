<?php
new AdaptativeView('breakLeave', function($data, PanelTemplate $t) {

	$class = \structure\StructureUi::getClass($data->eStructure);

	return new $class()->breakLeave($data->eStructure, $data->eMember, $data->farms, $data->mine, $data->lastAdmin);

});

new AdaptativeView('breakClose', function($data, PanelTemplate $t) {

	$class = \structure\StructureUi::getClass($data->eStructure);

	return new $class()->breakClose($data->eStructure, $data->farms, $data->members);

});

new AdaptativeView('breakMember', function($data, PanelTemplate $t) {

	$class = \structure\StructureUi::getClass($data->eStructure);

	return new $class()->breakMember($data->eStructure, $data->eMember, $data->cStructureShared, $data->cSection);

});

new AdaptativeView('duplicateStructure', function($data, PanelTemplate $t) {

	return new \structure\StructureDuplicateAiUi()->duplicate($data->eStructure, $data->eStructureSource, $data->asked, $data->granted, $data->counts);

});

new AdaptativeView('shareStructure', function($data, PanelTemplate $t) {

	// L'instanciation passe par getClass() et non par le nom de classe : les quatre
	// Structure*Ui implémentent une interface déclarée dans Structure.u.php, que
	// l'autoloader ne sait pas résoudre seule. Les atteindre directement lève une
	// fatale sur la première vue qui le fait.
	$class = \structure\StructureUi::getClass($data->eStructure);

	return new $class()->shareStructure($data->eStructure, $data->eCommunity, $data->members, $data->eStructureProducer, $data->eSystem);

});

new AdaptativeView(['/groupe/{id}/membres', '/groupe/{id}/membres/{section@int}'], function($data, CommunityTemplate $t) {

	$t->title = s("Membres");
	$t->tab = 'members';

	$class = \structure\StructureUi::getClass($data->e);

	// ⚠️ Une adresse, deux rendus, choisis sur le lecteur. La gestion existe pour ses gestes —
	// inviter, changer un rôle, ranger, retirer — et ils sont réservés à qui administre : mise
	// devant un conseiller ou un enseignant, la table lui proposerait des contrôles qui
	// refuseraient tous. C'est `canManage()` et non `isStaff()` pour cette raison.
	$create = '';

	if($data->e->canManage()) {
		$create = '<a href="/structure/invite:createMember?structure='.$data->e['id'].'" class="btn btn-secondary">'.\Asset::icon('person-plus-fill').' '.\structure\StructureUi::getText($data->e, 'membersInviteAll').'</a>';
	}

	$t->mainTitle = \main\DesignUi::getPageHeader(s("Les membres"), create: $create);

	if($data->e->canManage()) {

		echo new $class()->getMembersManage($data->e, $data->ccMember, $data->cRole, $data->cInvite, $data->cInviteFinished, $data->cSection);

	} else {

		echo new $class()->getMembers($data->e, $data->ccMember, $data->cRole);

	}

});

new AdaptativeView(['/groupe/{id}', '/groupe/{id}/section/{section@int}'], function($data, CommunityTemplate $t) {

	$t->tab = 'home';

	$class = \structure\StructureUi::getClass($data->e);

	// Pendant la mise en route, le parcours tient l'écran à la place du tableau de bord
	// (`CommunityTemplate::getContent()`) : le titre nomme le parcours, et les chiffres d'un tableau
	// de bord qui n'a encore rien à montrer se taisent.
	if($data->start !== NULL) {

		$t->title = s("Mise en route");
		$t->mainTitle = \main\DesignUi::getPageHeader($t->title);

		return;

	}

	// Le titre nomme qui le lit : chacun a son tableau de bord, et l'encadrant comme l'adhérent
	// doivent savoir lequel ils regardent.
	$t->title = $class::getText($data->e, $data->eMember->isStaff() ? 'dashboardStaff' : 'dashboardMember');
	$t->mainTitle = \main\DesignUi::getPageHeader($t->title);
	$t->mainFigures = new $class()->getDashboardFigures($data->e, $data->members, $data->farms);

	// Le tableau des sous-groupes a quitté cet écran : il était le troisième chemin vers la même
	// chose — la barre de portée en change, le menu Administrer les gère — et ne faisait que les
	// lister avec leurs compteurs.
	//
	// Un bloc n'existe que s'il a quelque chose d'ouvert : sans collecte en cours, pas d'appel à en
	// ouvrir une, qui reste dans l'onglet des collectes.
	$collects = $data->cCollectOpen->notEmpty() ?
		implode('', \structure\StructureUi::getObserverEntries('getCommunityCollects', $data->e, $data->cCollectOpen, $data->e->canManage())) :
		'';

	$enrollments = $data->cDateEnrollmentLast->notEmpty() ?
		new \learning\DateEnrollmentUi()->getLast($data->cDateEnrollmentLast) :
		'';

	echo new $class()->getDashboard($collects, $enrollments);

});

new AdaptativeView(['/groupe/{id}/maraichage', '/groupe/{id}/maraichage/{section@int}'], function($data, CommunityTemplate $t) {

	$t->tab = 'production';
	$t->title = s("Maraîchage");

	$class = \structure\StructureUi::getClass($data->e);

	$t->mainTitle = \main\DesignUi::getPageHeader($t->title);
	$t->mainTabs = new $class()->getProductionTabs($data->e, 'vegetable', $data->eSection);
	$t->mainFilter = new \series\VegetableDashboardAiUi()->getSeason($data->e, $data->seasons, $data->season, $data->eSection);

	// Aucune valeur du réseau n'atteint le plancher : le bloc remplace l'écran entier plutôt que
	// d'en laisser des cartes vides, qui se liraient comme des zéros.
	if($data->hasAboveFloor) {

		echo new \series\VegetableDashboardAiUi()->get($data->e, $data->dashboard, $data->hasSharing, $data->hasCompare, $data->eSection);

	} else {

		echo new \series\VegetableDashboardAiUi()->getBelowFloor($data->e, $data->dashboard, $data->structures, $data->hasCompare, $data->hasFiguresCommunity, $data->eSection);

	}

});

new AdaptativeView(['/groupe/{id}/comparer', '/groupe/{id}/comparer/{section@int}'], function($data, CommunityTemplate $t) {

	$t->tab = 'production';
	$t->title = s("Maraîchage");

	$class = \structure\StructureUi::getClass($data->e);

	$t->mainTitle = \main\DesignUi::getPageHeader($t->title);
	$t->mainTabs = new $class()->getProductionTabs($data->e, 'compare', $data->eSection);

	// Aucune espèce cultivée dans le groupe : l'écran s'arrête là. Les sélecteurs et l'invitation
	// à choisir une espèce se rendaient tous les deux, et le lecteur lisait coup sur coup qu'il
	// n'y a rien à comparer puis qu'il lui reste à choisir quoi.
	if($data->nothingToCompare) {

		echo new \series\CompareAiUi()->getNoCultivation($data->e, $data->hasSharing, $data->structures, $data->eSection, $data->hasCompareCommunity);

	} else {

		$t->mainFilter = new \series\CompareAiUi()->getSeason($data->e, $data->ePlant, $data->seasons, $data->season, $data->eStructureFarm, $data->eStructureAgainst, $data->eSection);

		echo new \series\CompareAiUi()->getSelectors($data->e, $data->cSpecies, $data->ePlant, $data->season, $data->structures, $data->cStructureFarm, $data->eStructureFarm, $data->eStructureAgainst, $data->eSection);

		if($data->ePlant->notEmpty()) {

			echo new \series\CompareAiUi()->getCompare(
				$data->eConfiguration,
				$data->cStructure,
				$data->cByStructure,
				$data->ePlant,
				$data->season
			);

		} else if($data->eStructureFarm->notEmpty()) {

			echo new \series\CompareAiUi()->getSpeciesTimeline(
				$data->eConfiguration,
				$data->e,
				$data->cStructure,
				$data->cPlantTimeline,
				$data->eStructureFarm,
				$data->eStructureAgainst,
				$data->season,
				$data->eSection
			);

		} else {

			echo new \series\CompareAiUi()->getNoSpecies();

		}

	}

});

new AdaptativeView('/groupe/{id}/fermes/ecole', function($data, CommunityTemplate $t) {

	$t->tab = 'schools';

	$t->title = s("Fermes école");

	$class = \structure\StructureUi::getClass($data->e);
	$t->mainTitle = \main\DesignUi::getPageHeader(s("Les fermes école"));

	echo new $class()->getSchools($data->e, $data->cStructureSchool);

});

new AdaptativeView('/groupe/{id}/mes-fermes', function($data, CommunityTemplate $t) {

	$t->tab = 'structures';

	$t->title = s("Mes fermes");

	$class = \structure\StructureUi::getClass($data->e);
	$t->mainTitle = \main\DesignUi::getPageHeader(s("Mes fermes dans ce groupe"));

	echo new $class()->getShareChoice($data->eStructure, $data->cStructure, $data->cStructureShared, $data->hasProducer);

});

new AdaptativeView('/groupe/{id}/sous-groupes', function($data, CommunityTemplate $t) {

	$t->tab = 'home';

	// Le titre du document et celui de la page : le second se rend dans le contenu, le premier
	// nomme l'onglet du navigateur. Le panneau que cette page remplace le posait par ricochet,
	// `Panel::getDocumentTitle()` retombant sur son titre.
	$t->title = \structure\StructureUi::getText($data->e, 'sectionsTitle');

	// La création est un bouton de la ligne de titre, comme les ventes ou les clients : l'écran
	// liste ce qui existe, le geste qui en ajoute ne vit pas dans la liste.
	//
	// Sur une liste vide il n'y monte pas : le bloc qui annonce l'absence porte alors le formulaire,
	// et deux façons de créer à deux cents pixels d'écart en font une de trop.
	$create = '';

	if($data->cSection->notEmpty() or $data->cSectionClosed->notEmpty()) {
		$create = '<a href="/structure/sectionAi:create?structure='.$data->e['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').' '.\structure\StructureUi::getText($data->e, 'sectionCreate').'</a>';
	}

	$t->mainTitle = \main\DesignUi::getPageHeader($t->title, create: $create);

	echo new \structure\SectionUi()->getManage($data->e, $data->cSection, $data->cSectionClosed, $data->eRoleSection);

});

new AdaptativeView('/groupe/{id}/fermes/gerer', function($data, CommunityTemplate $t) {

	$t->tab = 'structures';

	$t->title = s("Gérer les fermes");

	$class = \structure\StructureUi::getClass($data->e);
	// L'écran s'ouvre depuis la roue de celui des fermes, où le retour ramène.
	$t->mainTitle = \main\DesignUi::getPageHeader(
		s("Gérer les fermes"),
		back: \structure\StructureUi::urlCommunityFarms($data->e),
		backLabel: \structure\StructureCommunityUi::getFarmsLabel($data->e)
	);

	echo new $class()->getManageStructures(
		$data->e,
		$data->cStructure,
		$data->e->getCommunityAudienceRights()
	);

});

new AdaptativeView(['/groupe/{id}/fermes', '/groupe/{id}/fermes/{section@int}'], function($data, CommunityTemplate $t) {

	$t->tab = 'structures';

	$class = \structure\StructureUi::getClass($data->e);

	// ⚠️ Le titre nomme l'ensemble et non la première rangée : « Fermes des membres » est devenu
	// le libellé d'un onglet, et l'écrire aussi en titre aurait posé deux fois le même mot à
	// quarante pixels d'écart, le second contredisant les deux autres onglets.
	// ⚠️ Le titre du document ne reprend pas celui de la page : celui-ci varie avec le type du
	// groupe et avec la portée du lecteur, quand l'onglet du navigateur doit nommer l'écran de la
	// même façon d'un groupe à l'autre.
	$t->title = s("Fermes");

	$settings = '';

	if($data->e->canManage() and $data->hasStructures) {

		// La gestion sait filtrer par sous-groupe : y arriver sans le filtre qu'on vient de poser
		// serait l'incohérence. /mes-fermes ne le réémet pas, elle liste les fermes de la personne
		// connectée et non celles du groupe — un sous-groupe n'y découpe rien.
		// La gestion des fermes ne porte pas de portée : elle liste tout ce que le groupe héberge,
		// et la borner ferait disparaître de l'écran de gestion ce qu'on vient y administrer.
		$settings = '<a href="'.\structure\StructureUi::urlCommunityFarmsManage($data->e).'" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('gear-fill').'</a>';

	}

	$t->mainTitle = \main\DesignUi::getPageHeader(new $class()->getFarmsTitle($data->e, $data->sectionScope !== NULL), settings: $settings);

	// L'appel à l'action du groupe est rendu au-dessus par le gabarit : là où il porte un geste,
	// les blocs vides de cet écran n'ont pas à en reproposer un second.
	$onboarding = ($data->onboarding['state'] !== \structure\StructureCommunityLib::NOTHING);

	if($data->hasStructures) {

		$membersHead = '';

		if($data->cStructureLocated->notEmpty()) {
			$membersHead = new $class()->getMap($data->cStructureLocated, $data->mapTotal);
		}

		echo new $class()->getStructures(
			$data->e,
			$data->eCommunity,
			$data->cStructureMine,
			$data->cStructureProject,
			$data->cStructureMember,
			$data->e->canManage(),
			$data->hasShareable,
			$data->hasProducer,
			$data->hasDuplicate,
			$data->eSection,
			$onboarding,
			$membersHead
		);

		return;

	}

	// Un groupe qui n'héberge pas de fermes rendait une grille vide :
	// une page qui ne porte rien n'est pas une page. Le geste d'invitation y est toujours valable,
	// c'est le seul qui fasse venir ce que le groupe n'a pas.
	echo new $class()->getEmptyStart($data->e, $data->e->canManage(), $onboarding);

});

