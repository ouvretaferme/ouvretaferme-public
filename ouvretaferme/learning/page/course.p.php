<?php
/**
 * Une formation : sa fiche, lue par le lecteur, et son éditeur, tenu par son auteur.
 *
 * L'auteur est l'équipe Ouvretaferme (`community` nul, droits d'administrateur) ou l'équipe d'un
 * groupe de formation ou d'un réseau. Les deux éditent à la même adresse : seul le gabarit change,
 * l'administration pour l'une, les écrans du groupe pour l'autre.
 */

/**
 * Le gabarit de l'éditeur suit l'auteur : une formation de groupe s'édite parmi les écrans du
 * groupe, qui demandent la structure entière.
 */
$renderEdition = function($data, string $view) {

	if($data->eCourse->isTeam()) {
		throw new ViewAction($data, ':'.$view);
	}

	$data->eStructure = \structure\StructureLib::getById($data->eCourse['community']);

	throw new CommunityAction($data, ':'.$view.'Community');

};

/**
 * Ce que la tête de l'éditeur lit, sur l'éditeur comme sur le Bilan, qui la partagent
 * (`CourseUi::getEditionHead()`).
 */
$loadEditionHead = function(\learning\Course $eCourse) {

	$cCourse = new Collection([$eCourse['id'] => $eCourse]);

	// Le compteur du groupe, pas un COUNT() : l'ouverture ne s'offre qu'à un groupe qui a des
	// promotions, ou à un réseau qui a des membres hors de son équipe
	// (`Course::acceptShareAudience()`), et une formation de l'équipe n'a ni les uns ni les
	// autres. Les partages, que la ligne sous le titre dit, donnent aussi leur nombre :
	// `fillShares()` écarte les mêmes partages orphelins que `countByCourse()`.
	if($eCourse->isTeam()) {
		$eCourse['sections'] = 0;
		$eCourse['shares'] = 0;
	} else {

		if($eCourse->isNetwork()) {
			$eCourse['members'] = \learning\ShareLib::countMembers($eCourse);
		} else {
			$eCourse['sections'] = $eCourse['community']->getCommunity('sections');
		}

		\learning\ShareLib::fillShares($cCourse);

	}

	\learning\EnrollmentLib::fillReaders($cCourse);
	\learning\EnrollmentLib::fillRequests($cCourse);

	\learning\CourseLib::fillHasDateUpcoming($eCourse);

	// La roue de la tête grise « Supprimer » quand une date porte une feuille signée.
	\learning\DateSheetLib::fillCountByCourse($cCourse);

};

/**
 * Dupliquer copie une formation dans son groupe, par qui peut la modifier.
 */
$getDuplicate = function(): array {

	$eCourse = \learning\CourseLib::getById(INPUT('id'))->validate('canUpdate', 'acceptUpdate');

	$eCourseNew = \learning\CourseLib::getCreateElement($eCourse['community'])->validate('canCreate', 'acceptCreate');

	return [$eCourse, $eCourseNew];

};

new Page()
	->get('/formation/{id}', function($data) {

		$data->eCourse = \learning\CourseLib::getById(GET('id'))->validate();

		if($data->eCourse->acceptLearning() === FALSE) {
			throw new NotExistsAction();
		}

		// La fiche d'une formation qui s'offre à l'inscription se lit sans compte ni droit de lecture :
		// c'est d'elle qu'on s'inscrit.
		if($data->eCourse->assertPublicRead() === FALSE) {
			\user\ConnectionLib::checkLogged();
		}

		$data->eCourse->validate('assertPublicRead');

		$eUser = \user\ConnectionLib::getOnline();

		$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
		$data->cStaff = \learning\StaffLib::getByCourse($data->eCourse);
		$data->eCourse['eSupervisorContact'] = \learning\SupervisorLib::getContactByCourse($data->eCourse);

		$data->eEnrollment = \learning\EnrollmentLib::getByUserAndCourse($eUser, $data->eCourse);
		// Une ligne par compte et par formation : une inscription confirmée exclut la demande.
		$data->eCourse['eEnrollmentRequest'] = $data->eEnrollment->empty() ? \learning\EnrollmentLib::getRequestByUserAndCourse($eUser, $data->eCourse) : new \learning\Enrollment();
		$data->cProgress = \learning\ProgressLib::getDoneByUserAndCourse($eUser, $data->eCourse);

		\learning\ProgressLib::fillChapterDone($data->cChapter, $data->cProgress);
		\learning\LessonLib::fillChapterFormat($data->cChapter);

		// Le nombre de leçons compte les leçons vides, comme `Course::isDone()` ; leur durée ne compte
		// pas (`ChapterLib::getByCourse()`).
		$data->eCourse['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);
		$data->eCourse['minutes'] = \learning\ChapterLib::sumMinutes($data->cChapter);
		$data->eCourse['done'] = $data->cProgress->count();

		// Sans leçon, chaque date pointée délivre son attestation de présence (lot L8f).
		$data->eCourse['cDateEnrollmentAttended'] = ($eUser->notEmpty() and $data->eCourse->isDated() and $data->eCourse['lessons'] === 0)
			? \learning\DateEnrollmentLib::getPresenceByCourse($data->eCourse, $eUser)
			: new Collection();

		$data->cStructure = $eUser->empty() ? new Collection() : \learning\EnrollmentLib::getStructures();
		$data->isLogged = $eUser->notEmpty();
		$data->hasCatalog = \learning\LearningSetting::$active;

		// Le compte d'un visiteur se crée sur place tant que les formations sont ouvertes à tous
		// (`account.p.php`) ; fermées, la première leçon le renvoie à la connexion.
		$data->eCourse['withAccountCreate'] = $data->hasCatalog;

		$data->eCourse['canProgress'] = $data->eCourse->canProgress();
		$data->eCourse['canFollow'] = $data->eCourse->canFollow($data->eEnrollment);
		$data->eCourse['eLessonFirst'] = \learning\CourseLib::getFirstLinked($data->cChapter, $data->eCourse);
		$data->eCourse['eLessonResume'] = \learning\CourseLib::getResume($data->cChapter, $data->cProgress, $data->eCourse);
		$data->eCourse['acceptEnrollment'] = new \learning\Enrollment(['user' => $eUser, 'course' => $data->eCourse])->acceptCreate();

		$data->eCourse['withEnroll'] = $data->eCourse->isEnrollFirst();

		// Le compte créé sur place revient ici, le panneau d'inscription ouvert, quand l'inscription
		// peut partir en demande (`account:doCreate`).
		$data->eCourse['withEnrollPanel'] = ($eUser->notEmpty() and get_exists('enroll'));

		// Les leçons ne s'ouvrent qu'à l'inscrit : avant, le menu ne les liste pas, et le programme les
		// montre sans y mener.
		$data->cChapterMenu = $data->eCourse['withEnroll'] ? new Collection() : $data->cChapter;

		$data->eCourse['isDateConfirmed'] = ($data->eCourse->isDated() and \learning\DateEnrollmentLib::hasConfirmed($eUser, $data->eCourse));
		\learning\ResponseLib::fillCourseQuiz($eUser, $data->eCourse, $data->cChapter);
		$data->eCourse['eShare'] = \learning\ShareLib::getWindowByUserAndCourse($eUser, $data->eCourse);
		\learning\ReviewLib::fillCourse($data->eCourse, $eUser);

		$data->cDate = $data->eCourse->isDated()
			? \learning\DateLib::getUpcomingByCourse($data->eCourse)
			: new Collection();

		// Le prix de la tête est celui de la prochaine date planifiée, lu comme la carte le lit :
		// `cDate` garde aussi les dates annulées et celles qui ont commencé.
		\learning\DateLib::fillUpcoming(new Collection([$data->eCourse]));

		\learning\DateEnrollmentLib::fillForCourse($data->cDate, $eUser);
		\learning\DateEnrollmentLib::fillLosesLessons($data->eCourse, $data->cDate->getColumnCollection('eDateEnrollment'));

		// Qui a déjà sa ligne, inscription, demande ou place à une date, ne lit pas la pause en tête
		// de la fiche : elle ne ferme que les nouvelles inscriptions.
		$data->eCourse['hasEnrollmentLine'] = (
			$data->eEnrollment->notEmpty() or
			$data->eCourse['eEnrollmentRequest']->notEmpty() or
			$data->eCourse['isDateConfirmed'] or
			$data->cDate->find(fn(\learning\Date $eDate) => $eDate['eDateEnrollment']->isOpen())->notEmpty()
		);

		// Le réseau se lit une fois : son thème habille la fiche, son réglage des formations en ligne
		// décide du bouton « S'inscrire », et un visiteur s'inscrit sur la page publique de la date,
		// dont l'adresse est celle du réseau.
		$isVisitor = ($eUser->empty() and $data->cDate->notEmpty());

		$eCommunity = ($data->eCourse->isNetwork() or $isVisitor)
			? \structure\CommunityLib::getByStructure($data->eCourse['community'])
			: new \structure\Community();

		$data->eCourse['onlineConfirm'] = \learning\EnrollmentLib::getOnlineConfirmByCommunity($data->eCourse, $eCommunity);

		// Le gérant du réseau règle le thème depuis la fiche comme depuis la liste : la barre, et les
		// réglages essayés portés par l'adresse, montrés sans rien écrire.
		$isManager = ($data->eCourse->isNetwork() and $data->eCourse['community']->canManage());

		$data->customize = ($isManager and get_exists('customize'));

		if($data->customize) {
			\learning\CommunityLib::applyPreview($eCommunity, $_GET, onKo: fn() => throw new RedirectAction(\learning\CourseUi::url($data->eCourse, ['customize' => 1]), httpStatus: 'HTTP/1.1 302 Found'));
		}

		$data->settings = $data->eCourse->isNetwork()
			? \learning\CommunityLib::getSettings($eCommunity)
			: NULL;

		$data->eCourse['customizeQuery'] = $data->customize ? \learning\CustomizeUi::getQuery($data->settings['input']) : [];
		$data->eCourse['canCustomize'] = ($isManager and $data->customize === FALSE);

		if($isVisitor) {

			foreach($data->cDate as $eDate) {
				$eDate['eCommunity'] = $eCommunity;
			}

		}

		// Sans date planifiée à venir, aucune pile ne porte le lien vers le site de l'organisme (lot
		// annuaire) : la fiche le pose alors en tête. Une date annulée ne porte pas de porte.
		$data->eCourse['hasDatePlanned'] = $data->cDate->contains(fn(\learning\Date $eDate): bool => $eDate['status'] === \learning\Date::PLANNED);

		throw new ViewAction($data);

	})
	/**
	 * L'attestation de suivi (F69), calculée à la demande et jamais stockée : elle se délivre au
	 * compte inscrit qui a suivi toutes les leçons, comme la fiche la compte (`Course::isDone()`).
	 * Le PDF se rend par l'adresse signée de `pdf:attestation`, que le navigateur sans tête lit sans
	 * compte connecté : c'est ici, et seulement ici, que se jugent le droit et l'état.
	 */
	->get('/formation/{id}/attestation', function($data) {

		$eCourse = \learning\CourseLib::getById(GET('id'))->validate('assertRead');

		$eUser = \user\ConnectionLib::getOnline();

		$eEnrollment = \learning\EnrollmentLib::getByUserAndCourse($eUser, $eCourse)->validate();

		$cChapter = \learning\ChapterLib::getByCourse($eCourse);

		$eCourse['lessons'] = \learning\ChapterLib::countLessons($cChapter);
		$eCourse['done'] = \learning\ProgressLib::getDoneByUserAndCourse($eUser, $eCourse)->count();

		if($eCourse->acceptAttestation($eEnrollment) === FALSE) {
			throw new NotAllowedAction();
		}

		$content = \main\PdfLib::build('/learning/pdf:attestation?id='.$eCourse['id'].'&user='.$eEnrollment['user']['id'], footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, \learning\AttestationUi::getFilename($eCourse));

	})
	->get('/formation/{id}/edition', function($data) use($renderEdition, $loadEditionHead) {

		$data->eCourse = \learning\CourseLib::getById(GET('id'))->validate('canUpdate', 'acceptUpdate');

		$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
		\learning\ChapterLib::fillHolds($data->eCourse, $data->cChapter);
		\learning\LessonLib::fillChapterFormat($data->cChapter);

		$data->eCourse['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);
		$data->eCourse['minutes'] = \learning\ChapterLib::sumMinutes($data->cChapter);

		$loadEditionHead($data->eCourse);

		\learning\CommentLib::fillCountByCourse($data->eCourse, $data->cChapter);

		$data->cDate = $data->eCourse->isDated()
			? \learning\DateLib::getByCourse($data->eCourse)
			: new Collection();

		\learning\DateEnrollmentLib::fillCounts($data->cDate);
		\learning\DateEnrollmentLib::fillLinkRecipients($data->cDate);

		$data->cStaff = \learning\StaffLib::getByCourse($data->eCourse);
		$data->eCourse['eSupervisorContact'] = \learning\SupervisorLib::getContactByCourse($data->eCourse);

		// L'onglet Diffusion (retour de Vincent du 03/10, point 5), sur la seule formation qui a une
		// offre et un circuit (`acceptCatalog()`) : l'offre de la formation en ligne ou de chaque date
		// planifiée à venir, où en est sa demande au catalogue, et la carte que le catalogue montrera,
		// préparée comme le catalogue la prépare pour un visiteur. `offered` dit si la formation peut
		// se proposer au catalogue (`Course::catalog.offer`).
		$data->withDiffusion = $data->eCourse->acceptCatalog();

		if($data->withDiffusion) {

			$cCourse = new Collection([$data->eCourse['id'] => $data->eCourse]);

			\learning\CourseLib::fillCounts($cCourse);
			\learning\CourseLib::fillGoals($cCourse);
			\learning\DateLib::fillUpcoming($cCourse);
			\learning\ProgressLib::fillDone(new \user\User(), $cCourse);

			$data->eCourse['offered'] = \learning\CourseLib::isOffered($data->eCourse);

			if($data->eCourse->isDated()) {
				$data->cDatePlanned = \learning\DateLib::getPlannedByCourse($data->eCourse);
			} else {
				$data->cDatePlanned = new Collection();
			}

		} else {
			$data->cDatePlanned = new Collection();
		}

		// L'onglet qu'une redirection demande, que `Lime.Tab.restore()` préfère à celui retenu par
		// le navigateur. Absent, le navigateur reprend le dernier onglet ouvert.
		$data->tab = GET('tab', \learning\CourseUi::EDITION_TABS, '');

		$renderEdition($data, 'edition');

	})
	/**
	 * Le Bilan d'une formation en ligne, sous les onglets de l'éditeur mais sur une page à elle, parce
	 * que sa période, choisie à côté du titre, gouverne tout l'écran. Une formation à dates n'en a pas
	 * (`acceptUsage()`). Les chiffres sont ceux de `GET course/{id}/usage` (`UsageLib::get()`). Le pas
	 * se lit après la période, qui dit lesquels elle admet, et l'indicateur choisi n'est que de l'écran.
	 */
	->get('/formation/{id}/bilan', function($data) use($renderEdition, $loadEditionHead) {

		$data->eCourse = \learning\CourseLib::getById(GET('id'))->validate('canUpdate', 'acceptUpdate', 'acceptUsage');

		$loadEditionHead($data->eCourse);

		$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);

		$data->withDiffusion = $data->eCourse->acceptCatalog();

		$period = GET('period', \learning\UsageLib::PERIODS, \learning\UsageLib::PERIOD_DEFAULT);
		$steps = \learning\UsageLib::getSteps($period);
		$step = GET('step', $steps, \learning\UsageLib::STEP_DEFAULT);

		$data->usage = \learning\UsageLib::get($data->eCourse, $data->cChapter, $period, $step);
		$data->usage['steps'] = $steps;
		$data->indicator = GET('indicator', \learning\UsageUi::INDICATORS, \learning\UsageUi::INDICATOR_DEFAULT);

		$renderEdition($data, 'bilan');

	})
	/**
	 * Le parcours de création, une fois la formation écrite : ses étapes y reviennent par leur nom,
	 * et il s'ouvre sur la troisième, la première date ou le premier module selon le type. Le module
	 * prend le numéro qui suit ceux de la formation, que le champ montre en préfixe.
	 */
	->get('/formation/{id}/demarrer', function($data) use($renderEdition) {

		$data->eCourse = \learning\CourseLib::getById(GET('id'))->validate('canUpdate', 'acceptUpdate');

		\learning\CourseLib::fillChoices($data->eCourse);

		\learning\CourseLib::fillCounts(new Collection([$data->eCourse]));

		$step = GET('etape', ['type', 'formation'], NULL);

		if(
			$step === 'type' and
			$data->eCourse->isNetwork() === FALSE
		) {
			$step = NULL;
		}

		$data->step = $step ?? ($data->eCourse->isDated() ? 'date' : 'module');
		$data->withBack = TRUE;

		$renderEdition($data, 'start');

	})
	/**
	 * L'ancienne page Mode de diffusion et catalogue, devenue l'onglet Diffusion de l'éditeur (retour
	 * de Vincent du 03/10, point 5). L'adresse reste, pour les liens déjà posés, et mène à l'onglet
	 * avec les mêmes portes.
	 */
	->get('/formation/{id}/catalogue', function($data) {

		$eCourse = \learning\CourseLib::getById(GET('id'))->validate('canUpdate', 'acceptCatalog');

		throw new PermanentRedirectAction(\learning\CatalogUi::urlCourse($eCourse));

	})
	->get('duplicate', function($data) use($getDuplicate) {

		[$data->eCourse, $data->eCourseNew] = $getDuplicate();

		$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
		$data->eCourse['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);
		\learning\DateLib::fillUpcoming(new Collection([$data->eCourse]));

		throw new ViewAction($data);

	})
	->post('doDuplicate', function($data) use($getDuplicate) {

		[$eCourse, $eCourseNew] = $getDuplicate();

		$fw = new FailWatch();

		$eCourseNew->build(['name'], $_POST, new PropertyList('create'));

		$fw->validate();

		$eCourseCopy = \learning\CourseLib::duplicate($eCourse, $eCourseNew);

		throw new RedirectAction(\learning\CourseUi::urlEdition($eCourseCopy).'?success=learning\\Course::duplicated');

	})
	->post('doUpdatePositions', function($data) {

		$eCourse = \learning\CourseLib::getById(POST('id'))->validate('canUpdate', 'acceptUpdate');

		$positions = json_decode(POST('positions'), TRUE);

		if(is_array($positions) === FALSE) {
			throw new NotExpectedAction('Positions');
		}

		$fw = new FailWatch();

		\learning\ChapterLib::updatePositions($eCourse, array_map('intval', $positions));

		$fw->validate();

		throw new VoidAction();

	})
	/**
	 * Le groupe de la sous-thématique, rechargé quand la thématique change dans le formulaire d'une
	 * formation, écrite ou en création. Une formation déjà écrite garde sa sous-thématique quand on
	 * revient à sa thématique ; ailleurs, le choix repart de zéro. Les sous-thématiques sont lisibles
	 * de tous (`Subtheme::canRead()`) : sans formation, la route ne demande aucun droit.
	 */
	->post('getSubthemeField', function($data) {

		if(POST('id')) {
			$data->eCourse = \learning\CourseLib::getById(POST('id'))->validate('canUpdate');
		} else {
			$data->eCourse = new \learning\Course([
				'subtheme' => new \learning\Subtheme(),
			]);
		}

		$data->columns = (POST('columns', 'int') === 1) ? 1 : 2;

		$data->eCourse['theme'] = \learning\ThemeLib::getById(POST('theme'));
		$data->eCourse['cSubtheme'] = \learning\SubthemeLib::getByTheme($data->eCourse['theme']);

		if(
			$data->eCourse['subtheme']->notEmpty() and
			$data->eCourse['cSubtheme']->offsetExists($data->eCourse['subtheme']['id']) === FALSE
		) {
			$data->eCourse['subtheme'] = new \learning\Subtheme();
		}

		throw new ViewAction($data);

	});

/**
 * La barre de personnalisation posée sur la fiche et les leçons d'une formation de réseau : l'essai
 * d'un réglage et l'enregistrement, sur le domaine de la fiche, comme ceux de la liste du réseau
 * (`public.p.php`). Le droit est celui de gérer le réseau de la formation, et non d'écrire la
 * formation : c'est le thème du réseau qui s'enregistre.
 */
new Page(function($data) {

	$data->eCourse = \learning\CourseLib::getById(GET('id'))->validate();

	if(
		$data->eCourse->acceptLearning() === FALSE or
		$data->eCourse->isNetwork() === FALSE
	) {
		throw new NotExistsAction();
	}

	$data->eCourse['community']->validate('canManage');

})
	->post('/formation/{id}/:doPreview', function($data) {

		$values = \learning\CommunityLib::checkCustomize(\structure\CommunityLib::getByStructure($data->eCourse['community']), $_POST);

		throw new RedirectAction(\learning\CourseUi::url($data->eCourse, \learning\CustomizeUi::getQuery($values)), httpStatus: 'HTTP/1.1 302 Found');

	})
	->post('/formation/{id}/:doCustomize', function($data) {

		\learning\CommunityLib::updateCustomize($data->eCourse['community'], $_POST);

		throw new ReloadAction('learning', 'Community::customized');

	});

new \learning\CoursePage()
	->getCreateElement(function($data) {

		$community = INPUT('community', '?int');

		return \learning\CourseLib::getCreateElement(
			$community === NULL ? new \structure\Structure() : \structure\StructureLib::getById($community)->validate(),
			INPUT('type', [\learning\Course::ONSITE, \learning\Course::REMOTE, \learning\Course::ONLINE], \learning\Course::ONLINE)
		);

	})
	/**
	 * Le parcours de création, avant que la formation ne soit écrite : le type dans un réseau, puis
	 * la formation, que `doCreate` écrit. Une adresse de réseau qui ne porte pas de type, ou qui y
	 * ramène par le nom de l'étape (`etape`), s'ouvre sur le type ; le type posté par l'étape, ou
	 * par le bouton d'un filtre vide, mène à la formation. L'étape envoyée sans type, qui demande la
	 * formation (`etape=formation`), est refusée sous les tuiles.
	 */
	->create(function($data) use($renderEdition) {

		$type = GET('type', [\learning\Course::ONSITE, \learning\Course::REMOTE, \learning\Course::ONLINE], NULL);

		if($data->e->isNetwork()) {

			$fw = new FailWatch();

			if($type === NULL and GET('etape') === 'formation') {
				\learning\Course::fail('type.missing');
			}

			$fw->validate();

			$data->e['type'] = $type;

			$data->step = ($type === NULL or GET('etape') === 'type') ? 'type' : 'formation';

		} else {
			$data->step = 'formation';
		}

		\learning\CourseLib::fillChoices($data->e);

		$data->eCourse = $data->e;
		$data->withBack = (\learning\CourseLib::countByCommunity($data->e['community']) > 0);

		$renderEdition($data, 'start');

	})
	->doCreate(fn($data) => throw new RedirectAction(\learning\CourseUi::urlStart($data->e)))
	->update(function($data) {

		\learning\CourseLib::fillChoices($data->e);
		$data->e['dated'] = \learning\DateLib::existsByCourse($data->e);

		throw new ViewAction($data);

	}, page: 'updateSettings')
	->update(page: 'updateDetails')
	/**
	 * La page de la formation n'est obligatoire que si une de ses dates à venir n'a pas la sienne
	 * (`Course::externalUrl.uncovered`) : le champ ne porte l'astérisque que dans ce cas.
	 */
	->update(function($data) {

		$data->e['externalUrlRequired'] = (\learning\DateLib::hasExternalUrlOnEachUpcoming($data->e) === FALSE);

		throw new ViewAction($data);

	}, page: 'updateExternal', validate: ['canUpdate', 'acceptUpdateExternal'])
	/**
	 * L'offre d'une formation en ligne (lot L15a), depuis son onglet Diffusion. Le choix ferme
	 * les offres que le réseau ne peut pas encore prendre, d'où le compte lu ici ; l'étape
	 * `Course::offer.account` refuse le même POST de son côté.
	 */
	->update(function($data) {

		$data->e['hasPayoutAccount'] = \learning\CommunityLib::hasPayoutAccount($data->e['community']);

		throw new ViewAction($data);

	}, page: 'updateOffer', validate: ['canUpdate', 'acceptUpdateOffer'])
	->doUpdate(fn() => throw new ReloadAction('learning', 'Course::offerUpdated'), propertiesUpdate: ['offer'], page: 'doUpdateOffer', validate: ['canUpdate', 'acceptUpdateOffer'])
	/**
	 * Le prix et le financement d'une formation de réseau, depuis son éditeur : le panneau poste
	 * « Formation payante » puis les quatre champs, et n'écrit qu'eux.
	 */
	->update(function($data) {

		$data->e['paid'] = $data->e->isPaid();

		throw new ViewAction($data);

	}, page: 'updatePrice', validate: ['canUpdate', 'acceptUpdatePrice'])
	->doUpdate(fn() => throw new ReloadAction('learning', 'Course::priceUpdated'), propertiesUpdate: ['paid', ...\learning\CourseLib::getPropertiesPrice()], page: 'doUpdatePrice', validate: ['canUpdate', 'acceptUpdatePrice'])
	->update(function($data) {

		$data->cChapter = \learning\ChapterLib::getByCourse($data->e);
		$data->e['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);
		\learning\DateLib::fillUpcoming(new Collection([$data->e]));

		[$data->propertiesMissing, $data->cLessonMissing, $data->programEmpty] = \learning\CourseLib::getMissingForPublication($data->e, $data->cChapter);
		$data->recommended = \learning\CourseLib::getRecommendedForPublication($data->e);

		if($data->e->isNetwork()) {
			$data->e['shares'] = \learning\ShareLib::countByCourse($data->e);
			$data->e['members'] = \learning\ShareLib::countMembers($data->e);
		} else if($data->e->isTeam() === FALSE) {
			$data->e['shares'] = \learning\ShareLib::countByCourse($data->e);
			$data->e['sections'] = $data->e['community']->getCommunity('sections');
		}

		throw new ViewAction($data);

	}, page: 'publish')
	/** Seul ce panneau dit combien de lecteurs ont déjà commencé la formation. */
	->update(function($data) {

		$data->cChapter = \learning\ChapterLib::getByCourse($data->e);
		$data->e['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);
		\learning\DateLib::fillUpcoming(new Collection([$data->e]));

		\learning\EnrollmentLib::fillReaders(new Collection([$data->e]));

		throw new ViewAction($data);

	}, page: 'unpublish')
	->update(function($data) {

		$data->cChapter = \learning\ChapterLib::getByCourse($data->e);
		$data->e['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);
		\learning\DateLib::fillUpcoming(new Collection([$data->e]));

		if($data->e['community']->isNetwork()) {
			$data->e['members'] = \learning\ShareLib::countMembers($data->e);
			$data->eShare = \learning\ShareLib::getForNetworkPanel($data->e);
		} else {
			$data->cSection = \learning\ShareLib::getSections($data->e);
		}

		throw new ViewAction($data);

	}, page: 'share', addValidate: ['acceptShare'])
	/**
	 * Le circuit du catalogue public, côté réseau (lot L14). Chaque geste construit `catalog` avec
	 * l'état où il mène, jamais lu dans le POST : une valeur postée mènerait où elle veut, jusqu'à
	 * l'acceptation que seule l'équipe décide.
	 */
	->write('doCatalogRequest', function($data) {

		$fw = new FailWatch();

		$data->e->build(['catalog'], ['catalog' => \learning\Course::PROPOSED]);

		$fw->validate();

		\learning\CourseLib::updateCatalog($data->e);

		throw new ReloadAction('learning', 'Course::catalogRequested');

	}, validate: ['canUpdate', 'acceptCatalogRequest'])
	->write('doCatalogWithdraw', function($data) {

		$fw = new FailWatch();

		$data->e->build(['catalog'], ['catalog' => \learning\Course::PRIVATE]);

		$fw->validate();

		\learning\CourseLib::updateCatalog($data->e);

		throw new ReloadAction('learning', 'Course::catalogWithdrawn');

	}, validate: ['canUpdate', 'acceptCatalogWithdraw'])
	->doUpdate(fn() => throw new ReloadAction('learning', 'Course::updated'), page: 'doUpdateSettings')
	/**
	 * Le détail de la formation, depuis son onglet : le panneau poste les sept champs, et n'écrit
	 * qu'eux, pour que les réglages et le détail ne se vident pas l'un l'autre.
	 */
	->doUpdate(fn() => throw new ReloadAction('learning', 'Course::detailsUpdated'), propertiesUpdate: \learning\CourseLib::getPropertiesDetails(), page: 'doUpdateDetails')
	/**
	 * Le réglage de l'inscription, depuis l'onglet Organisation (lot annuaire). `externalOnly` passe
	 * avant `externalUrl` : les étapes `externalUrl.unlinked` et `externalUrl.uncovered` lisent la
	 * nouvelle valeur du réglage.
	 */
	->doUpdate(fn() => throw new ReloadAction('learning', 'Course::externalUpdated'), propertiesUpdate: ['externalOnly', 'externalUrl'], page: 'doUpdateExternal', validate: ['canUpdate', 'acceptUpdateExternal'])
	/**
	 * Les étapes du parcours de création, revues par leur nom une fois la formation écrite. Chacune
	 * n'écrit que ce qu'elle montre, pour la même raison que `doUpdateStatus` ci-dessous : les
	 * enjeux, les objectifs et le reste, qu'elles ne postent pas, se videraient.
	 */
	->doUpdate(fn($data) => throw new RedirectAction(\learning\CourseUi::urlStart($data->e).'?etape=formation'), propertiesUpdate: ['type'], page: 'doUpdateStartType')
	->doUpdate(fn($data) => throw new RedirectAction(\learning\CourseUi::urlStart($data->e)), propertiesUpdate: ['name', 'summary', 'theme', 'subtheme', 'level'], page: 'doUpdateStart')
	/**
	 * Le premier module et ses leçons, d'un seul envoi : un formulaire ne poste qu'à une adresse.
	 * Chaque élément passe par son `build()`, le module comme depuis son panneau, les leçons par
	 * `buildIndex()` pour que leurs refus se rattachent à leur ligne. Une ligne laissée vide, ou
	 * faite d'espaces, n'est pas une leçon : l'auteur en a ajouté une de trop, ou n'en veut aucune.
	 * Le cast du cadriciel la réduirait à un nom vide, que le schéma refuse.
	 *
	 * Les leçons naissent sans durée : le parcours n'en demande que les titres, et la publication
	 * réclame la durée (`Course::status.classified`). `minutes` se construit quand même, non posté :
	 * sa règle `classified` refuse une leçon sans durée sous une formation déjà publiée, que cette
	 * adresse atteint aussi.
	 */
	->write('doCreateProgram', function($data) {

		$fw = new FailWatch();

		$eChapter = new \learning\Chapter(['course' => $data->e]);
		$eChapter->build(['name'], $_POST, new PropertyList('create'));

		// Une valeur qui n'est pas une chaîne ne vient pas du formulaire, et le cast en texte la
		// convertirait avec un avertissement : elle est écartée comme une ligne vide.
		$names = array_filter(POST('lessonName', 'array'), fn($name) => is_string($name) and trim($name) !== '');

		$cLesson = new Collection();

		if(count($names) > \learning\Chapter::LESSONS_START_MAX) {
			\learning\Course::fail('lessons.tooMany');
		} else {

			foreach(array_keys($names) as $index) {

				$eLesson = new \learning\Lesson([
					'course' => $data->e,
					'chapter' => $eChapter
				]);

				$eLesson->buildIndex(['name', 'minutes'], ['name' => $names], $index, new PropertyList('create'));

				$cLesson[] = $eLesson;

			}

		}

		$fw->validate();

		\learning\ChapterLib::createWithLessons($eChapter, $cLesson);

		throw new RedirectAction(\learning\CourseUi::urlEdition($data->e).'?tab=lessons&success=learning\\Course::created');

	}, validate: ['canUpdate', 'acceptUpdate'])
	/**
	 * Publier et dépublier n'écrivent que l'état, et c'est pourquoi ils ont leur page : une valeur
	 * non postée vaut NULL (`framework/core/Data.php`), donc un geste qui ne poste que `status` sur
	 * la liste entière de `getPropertiesUpdate()` viderait la présentation, le classement et les
	 * objectifs au passage.
	 *
	 * Une formation de réseau publiée n'est pas encore ouverte aux membres tant qu'on ne l'a pas
	 * fait : le panneau « Ouvrir la formation aux membres » s'ouvre à la suite, dès que le réseau a
	 * un membre hors de son équipe. Republiée, une formation garde l'ouverture d'avant sa
	 * dépublication, et le texte du panneau de publication le dit : rien ne s'ouvre alors.
	 */
	->doUpdate(function($data) {

		if(
			$data->e['status'] === \learning\Course::PUBLISHED and
			$data->e->isNetwork() and
			\learning\ShareLib::countByCourse($data->e) === 0 and
			\learning\ShareLib::countMembers($data->e) > 0
		) {
			throw new ViewAction($data);
		}

		throw new ReloadAction('learning', 'Course::updated');

	}, propertiesUpdate: ['status'], page: 'doUpdateStatus')
	->doDelete(fn($data) => throw new RedirectAction(\learning\CourseUi::urlList($data->e).'?success=learning\\Course::deleted'));

/**
 * La pause des nouvelles inscriptions, depuis l'en-tête de l'éditeur. Elle ne se règle que tant que
 * la formation prend des inscriptions (`Course::acceptUpdateEnrollmentPaused()`), qui pour une
 * formation datée lit ses dates à venir : le compte se pose avant la validation. La réponse redessine
 * l'interrupteur, et chaque date à venir entière, dont la bande de l'interrupteur se retire pendant
 * la pause de la formation et revient à la reprise : préparée comme l'éditeur la prépare.
 */
new \learning\CoursePage()
	->applyElement(function($data, \learning\Course $e) {
		\learning\CourseLib::fillHasDateUpcoming($e);
	})
	->doUpdate(function($data) {

		$data->cDate = \learning\DateLib::getUpcomingByCourse($data->e);
		\learning\DateEnrollmentLib::fillCounts($data->cDate);
		\learning\DateEnrollmentLib::fillLinkRecipients($data->cDate);

		throw new ViewAction($data);

	}, propertiesUpdate: ['enrollmentPaused'], page: 'doUpdateEnrollmentPaused', validate: ['canUpdate', 'acceptUpdateEnrollmentPaused']);

/**
 * L'enregistrement de l'ouverture, sous la même condition que les écrans qui l'offrent
 * (`Course::acceptShareAudience()`) : un réseau qui n'a aucun membre hors de son équipe, ou un
 * groupe sans promotion, n'ouvre rien, même par un envoi écrit à la main. Le panneau ouvert par
 * son adresse reste sous `acceptShare()` seul, et dit lui-même qu'il n'a rien à proposer. Le
 * compte qu'elle juge se pose avant la validation.
 */
new \learning\CoursePage()
	->applyElement(function($data, \learning\Course $e) {

		if($e->isNetwork()) {
			$e['members'] = \learning\ShareLib::countMembers($e);
		} else if($e->isTeam() === FALSE) {
			$e['sections'] = $e['community']->getCommunity('sections');
		}

	})
	->write('doShare', function($data) {

		$fw = new FailWatch();

		$cShare = \learning\ShareLib::buildFromInput($data->e, $_POST);

		$fw->validate();

		\learning\ShareLib::synchronizeCourse($data->e, $cShare);
		\learning\ShareLib::announce($data->e);

		if($data->e->isNetwork()) {
			throw new ReloadAction('learning', $cShare->empty() ? 'Share::membersClosed' : 'Share::membersOpened');
		}

		throw new ReloadAction('learning', 'Share::saved');

	}, addValidate: ['acceptShareAudience']);
?>
