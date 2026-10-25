<?php
/**
 * Les formations d'un groupe de formation ou d'un réseau, dans l'onglet « Formations » du groupe.
 *
 * L'équipe du groupe y entre : c'est elle qui les écrit. Les deux les listent en tableau ; un réseau
 * y ajoute ses dates et un filtre par type. Un membre d'un réseau qui n'est pas de l'équipe n'entre
 * que dans la liste, où il lit les formations que le réseau partage à ses structures (lot
 * glacons-parcours, choix 1 A) : elle a son propre bloc, le dernier du fichier.
 *
 * L'équipe y retrouve aussi les commentaires écrits sous les leçons du groupe et les avis donnés
 * sur ses formations, qu'elle marque lus pour toute l'équipe, et ses encadrants.
 */
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(INPUT('id'))->validate();

		if($data->eStructure->acceptLearning() === FALSE) {
			throw new NotExistsAction();
		}

		$data->eStructure->validate('canRead');

		if(($data->eStructure->isEducation() or $data->eStructure->isNetwork()) === FALSE) {
			throw new NotExpectedAction('Not a training community');
		}

		if($data->eStructure->getMember()->isStaff() === FALSE) {
			throw new NotAllowedAction($data->eStructure);
		}

	})
	->get('/groupe/{id}/formations/commentaires', function($data) {

		$before = GET('before', '?int');

		// Les nouveaux ne se montrent qu'en tête de la première page : les suivantes ne reprennent que
		// les lus plus anciens.
		$data->cCommentNew = ($before === NULL) ? \learning\CommentLib::getNewByStructure($data->eStructure) : new Collection();
		$data->cCommentRead = \learning\CommentLib::getReadByStructure($data->eStructure, $before, \learning\CommentLib::READ_PER_PAGE + 1);
		$data->older = $data->cCommentRead->cut(\learning\CommentLib::READ_PER_PAGE);

		\learning\CommentLib::fillTeamByStructure($data->eStructure, $data->cCommentNew);
		\learning\CommentLib::fillTeamByStructure($data->eStructure, $data->cCommentRead);
		\learning\CommentLib::fillLessonNumber($data->cCommentNew, $data->cCommentRead);
		\learning\CommentLib::fillTeamAnswers($data->eStructure, $data->cCommentNew, $data->cCommentRead);

		$data->commentsNew = \learning\CommentLib::countNewByStructure($data->eStructure);

		throw new CommunityAction($data);

	})
	/**
	 * Les avis donnés sur les formations du groupe, au catalogue public ou non (lot glacons-parcours,
	 * choix 4 A), dans la forme de la boite des commentaires.
	 */
	->get('/groupe/{id}/formations/avis', function($data) {

		$before = GET('before', '?int');

		$data->cReviewNew = ($before === NULL) ? \learning\ReviewLib::getNewByStructure($data->eStructure) : new Collection();
		$data->cReviewRead = \learning\ReviewLib::getReadByStructure($data->eStructure, $before, \learning\ReviewLib::READ_PER_PAGE + 1);
		$data->older = $data->cReviewRead->cut(\learning\ReviewLib::READ_PER_PAGE);

		$data->reviewsNew = \learning\ReviewLib::countNewByStructure($data->eStructure);

		throw new CommunityAction($data);

	})
	/**
	 * Les encadrants du groupe, que toute son équipe ajoute, corrige, désactive et supprime
	 * (`page/supervisor.p.php`), chacun avec le nombre de formations qu'il encadre.
	 */
	->get('/groupe/{id}/formations/encadrants', function($data) {

		$data->cSupervisor = \learning\SupervisorLib::getByCommunity($data->eStructure);

		throw new CommunityAction($data);

	})
	->post('doReadAll', function($data) {

		\learning\CommentLib::readAllByStructure($data->eStructure);

		throw new ReloadAction();

	})
	->post('doReadAllReviews', function($data) {

		\learning\ReviewLib::readAllByStructure($data->eStructure);

		throw new ReloadAction();

	})
	/**
	 * Seul un réseau date ses formations, et donc reçoit des inscriptions à une date.
	 */
	->get('updateEnrollment', function($data) {

		$data->eStructure->validate('canManage', 'isNetwork');

		$data->eCommunity = $data->eStructure->community();

		throw new ViewAction($data);

	})
	->post('doUpdateEnrollment', function($data) {

		$data->eStructure->validate('canManage', 'isNetwork');

		\learning\CommunityLib::updateEnrollment($data->eStructure, $_POST);

		throw new ReloadAction('learning', 'DateEnrollment::settingsUpdated');

	})
	/**
	 * La page publique d'un réseau à diffuser : son adresse, le code qui l'embarque, et l'aperçu de
	 * ce que le code affiche, tant qu'elle a quelque chose à montrer, une date à venir d'une formation
	 * publiée ou une formation en ligne publiée.
	 */
	->get('/groupe/{id}/formations/integrer', function($data) {

		$data->eStructure->validate('canManage', 'isNetwork');

		$data->eCommunity = $data->eStructure->community();
		$data->withPublic = (
			\learning\DateLib::existsPublicByCommunity($data->eStructure) or
			\learning\CourseLib::existsPublicOnlineByCommunity($data->eStructure)
		);

		throw new CommunityAction($data);

	});

/**
 * La liste, ouverte aussi au membre d'un réseau qui n'est pas de l'équipe : mêmes portes que le
 * premier bloc, sauf la dernière, que la route pose elle-même.
 */
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eStructure = \structure\StructureLib::getById(INPUT('id'))->validate();

		if($data->eStructure->acceptLearning() === FALSE) {
			throw new NotExistsAction();
		}

		$data->eStructure->validate('canRead');

		if(($data->eStructure->isEducation() or $data->eStructure->isNetwork()) === FALSE) {
			throw new NotExpectedAction('Not a training community');
		}

	})
	->get('/groupe/{id}/formations', function($data) {

		$data->cShare = new Collection();

		if($data->eStructure->getMember()->isStaff() === FALSE) {

			$eUser = \user\ConnectionLib::getOnline();

			$data->cShare = \learning\ShareLib::getByUserAndCommunity($eUser, $data->eStructure);

			if($data->eStructure->isNetwork() === FALSE or $data->cShare->empty()) {
				throw new NotAllowedAction($data->eStructure);
			}

			// Le bandeau ne relit pas les partages que la page vient de lire (`Lime.s.php`).
			$data->hasLearningShared = TRUE;

			// Toutes les formations que le réseau ouvre à ses membres : celles où le compte est inscrit
			// avec son avancement, les autres avec « S'inscrire ».
			$cCourse = $data->cShare->getColumnCollection('course', 'course');

			\learning\CourseLib::fillCounts($cCourse);
			\learning\ProgressLib::fillDone($eUser, $cCourse);
			\learning\ShareLib::fillProposed($eUser, $cCourse);

			$data->types = \learning\CourseLib::countByType($cCourse);
			$data->type = GET('type', [\learning\Course::ONSITE, \learning\Course::REMOTE, \learning\Course::MIXED, \learning\Course::ONLINE], NULL);

			$data->cShareShown = ($data->type === NULL)
				? $data->cShare
				: $data->cShare->find(fn(\learning\Share $eShare) => $eShare['course']['type'] === $data->type, clone: FALSE);

			$data->cCourse = new Collection();

			throw new CommunityAction($data);

		}

		$cCourse = \learning\CourseLib::getByCommunity($data->eStructure);

		// Un groupe sans formation n'a pas de liste à montrer : son onglet est le parcours de création,
		// ouvert sur le type dans un réseau, sur la formation ailleurs, comme `course:create`.
		if($cCourse->empty()) {

			$data->eCourseStart = \learning\CourseLib::getCreateElement($data->eStructure)->validate('canCreate', 'acceptCreate');

			if($data->eStructure->isNetwork()) {
				$data->eCourseStart['type'] = NULL;
				$data->step = 'type';
			} else {
				$data->step = 'formation';
			}

			\learning\CourseLib::fillChoices($data->eCourseStart);

			throw new CommunityAction($data);

		}

		$data->eCourseStart = new \learning\Course();

		// Le filtre « Type » d'un réseau compte toute la liste, puis la réduit au type retenu avant
		// les compteurs, qui ne se paient que pour les lignes affichées. Les demandes à confirmer se
		// lisent avant lui : leur colonne ne doit pas paraître ni disparaître d'un onglet du filtre à
		// l'autre. Le filtre clone les formations, qui gardent leur compte et leur marque.
		if($data->eStructure->isNetwork()) {

			\learning\DateEnrollmentLib::fillRequested($cCourse);
			\learning\EnrollmentLib::fillRequests($cCourse);
			\learning\CourseLib::fillCatalogBadge($cCourse);

			$data->withRequested = \learning\DateEnrollmentLib::hasRequested($data->eStructure, $cCourse);

			$data->types = \learning\CourseLib::countByType($cCourse);
			$data->type = GET('type', [\learning\Course::ONSITE, \learning\Course::REMOTE, \learning\Course::MIXED, \learning\Course::ONLINE], NULL);

			if($data->type !== NULL) {
				$cCourse = $cCourse->find(fn(\learning\Course $eCourse) => $eCourse['type'] === $data->type);
			}

		} else {
			$data->types = [];
			$data->type = NULL;
			$data->withRequested = FALSE;
		}

		\learning\CourseLib::fillCounts($cCourse);
		\learning\EnrollmentLib::fillReaders($cCourse);
		\learning\DateSheetLib::fillCountByCourse($cCourse);

		// Seul le tableau d'un groupe de formation dit à qui ses formations sont ouvertes, mais les deux
		// tableaux lisent le nombre de partages, qui ouvre l'accès au suivi.
		\learning\ShareLib::fillShares($cCourse);

		$data->cCourse = $cCourse;
		$data->commentsNew = \learning\CommentLib::countNewByStructure($data->eStructure);
		$data->reviewsNew = \learning\ReviewLib::countNewByStructure($data->eStructure);

		throw new CommunityAction($data);

	});
?>
