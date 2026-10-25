<?php
/**
 * Une leçon : sa lecture, sa progression, et son édition par l'auteur de la formation.
 *
 * La progression s'écrit au nom de l'inscription : suivre une leçon demande d'avoir commencé la
 * formation, et la fiche le propose avant toute leçon. Un visiteur lit une formation ouverte sans
 * compte, sans rien pouvoir cocher.
 */
$renderEdition = function($data) {

	if($data->eCourse->isTeam()) {
		throw new ViewAction($data, ':edition');
	}

	$data->eStructure = \structure\StructureLib::getById($data->eCourse['community']);

	throw new CommunityAction($data, ':editionCommunity');

};

new Page()
	->get('/formation/lecon/{id}', function($data) {

		$data->eLesson = \learning\LessonLib::getById(GET('id'))->validate();

		if($data->eLesson['course']->acceptLearning() === FALSE) {
			throw new NotExistsAction();
		}

		if($data->eLesson->assertRead() === FALSE) {
			\user\ConnectionLib::checkLogged();
		}

		$data->eLesson->validate('assertRead');

		$eUser = \user\ConnectionLib::getOnline();

		$data->eCourse = $data->eLesson['course'];

		$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
		\learning\ChapterLib::locate($data->cChapter, $data->eLesson);

		$data->eCourse['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);

		\learning\ChapterLib::fillPosition($data->eCourse, $data->cChapter, $data->eLesson);

		\learning\LessonLib::fillReadable($data->eLesson);
		$data->eLesson['cAttachment'] = \learning\AttachmentLib::getByLesson($data->eLesson);

		$data->eLesson['eCommentNew'] = new \learning\Comment(['lesson' => $data->eLesson]);

		if($data->eLesson['eCommentNew']->assertRead()) {

			$cComment = \learning\CommentLib::getByLesson($data->eLesson);
			\learning\CommentLib::fillTeam($data->eCourse, $cComment);
			\learning\CommentLib::fillAnswers($cComment);

			// Le fil compte commentaires et réponses, et ne liste que les commentaires : chacun porte
			// les siennes.
			$data->eLesson['comments'] = $cComment->count();
			$data->eLesson['cComment'] = $cComment->find(fn(\learning\Comment $eComment) => $eComment['parent']->empty());

		} else {
			$data->eLesson['comments'] = 0;
			$data->eLesson['cComment'] = new Collection();
		}

		$data->eEnrollment = \learning\EnrollmentLib::getByUserAndCourse($eUser, $data->eCourse);
		$data->cStructure = $eUser->empty() ? new Collection() : \learning\EnrollmentLib::getStructures();
		\learning\LessonLib::fillContent($data->eLesson, $data->eEnrollment, $data->cStructure);
		// Une ligne par compte et par formation : une inscription confirmée exclut la demande.
		$data->eCourse['eEnrollmentRequest'] = $data->eEnrollment->empty() ? \learning\EnrollmentLib::getRequestByUserAndCourse($eUser, $data->eCourse) : new \learning\Enrollment();
		$data->cProgress = \learning\ProgressLib::getDoneByUserAndCourse($eUser, $data->eCourse);
		\learning\LessonLib::fillWritten($data->eCourse, $data->cChapter, $data->cProgress, $data->eLesson);

		// Le retour de la bande et de la paire : un tutoriel ramène au sommaire des tutoriels, toute
		// autre formation à sa fiche.
		$data->eCourse['isTutorial'] = $data->eCourse->acceptTutorial();

		// Le gérant du réseau règle le thème depuis la leçon comme depuis la fiche (`course.p.php`).
		$isManager = ($data->eCourse->isNetwork() and $data->eCourse['community']->canManage());

		$data->customize = ($isManager and get_exists('customize'));

		// L'ouverture est horodatée par le serveur, sans attendre le premier signal de la leçon. Le
		// gérant qui essaie un thème ne lit pas la leçon : chaque essai recharge la page, et tiendrait
		// le journal de lecture à sa place.
		$data->eLesson['reading'] = $data->customize ? FALSE : \learning\ReadingLib::see($data->eEnrollment, $data->eLesson);

		// Le temps qu'une vidéo lancée peut tenir la lecture active sans geste. La page ne sait jamais si
		// une vidéo hébergée ailleurs joue, est en pause ou est finie : elle ne lui accorde donc pas plus
		// que la durée estimée de la leçon, et le délai d'inactivité à une leçon qui n'en a pas.
		$data->eLesson['videoLimit'] = ($data->eLesson['minutes'] === NULL) ? \learning\ReadingLib::IDLE : $data->eLesson['minutes'] * 60;

		if($data->eLesson['validation'] === \learning\Lesson::QUIZ) {
			\learning\ResponseLib::fillQuiz($eUser, $data->eLesson);
		}

		$data->isLogged = $eUser->notEmpty();

		$data->eCourse['canProgress'] = $data->eCourse->canProgress();
		$data->eCourse['canFollow'] = $data->eCourse->canFollow($data->eEnrollment);
		$data->eCourse['acceptEnrollment'] = new \learning\Enrollment(['user' => $eUser, 'course' => $data->eCourse])->acceptCreate();
		$data->eCourse['eShare'] = \learning\ShareLib::getWindowByUserAndCourse($eUser, $data->eCourse);

		// Le réseau se lit ici et non par `getSettingsByCourse()`, qui le relirait sans les réglages
		// essayés ; son réglage des formations en ligne en vient aussi.
		if($data->eCourse->isNetwork()) {

			$eCommunity = \structure\CommunityLib::getByStructure($data->eCourse['community']);

			if($data->customize) {
				\learning\CommunityLib::applyPreview($eCommunity, $_GET, onKo: fn() => throw new RedirectAction(\learning\LessonUi::url($data->eLesson, ['customize' => 1]), httpStatus: 'HTTP/1.1 302 Found'));
			}

			$data->settings = \learning\CommunityLib::getSettings($eCommunity);
			$data->eCourse['onlineConfirm'] = \learning\EnrollmentLib::getOnlineConfirmByCommunity($data->eCourse, $eCommunity);

		} else {
			$data->settings = NULL;
			$data->eCourse['onlineConfirm'] = NULL;
		}

		$data->eCourse['customizeQuery'] = $data->customize ? \learning\CustomizeUi::getQuery($data->settings['input']) : [];

		throw new ViewAction($data);

	})
	/**
	 * L'essai d'un réglage de la barre depuis une leçon : la leçon se recharge avec les réglages dans
	 * l'adresse. L'enregistrement est celui de la formation (`course.p.php`).
	 */
	->post('/formation/lecon/{id}/:doPreview', function($data) {

		$eLesson = \learning\LessonLib::getById(GET('id'))->validate();
		$eCourse = $eLesson['course'];

		if(
			$eCourse->acceptLearning() === FALSE or
			$eCourse->isNetwork() === FALSE
		) {
			throw new NotExistsAction();
		}

		$eCourse['community']->validate('canManage');

		$values = \learning\CommunityLib::checkCustomize(\structure\CommunityLib::getByStructure($eCourse['community']), $_POST);

		throw new RedirectAction(\learning\LessonUi::url($eLesson, \learning\CustomizeUi::getQuery($values)), httpStatus: 'HTTP/1.1 302 Found');

	})
	->get('/formation/lecon/{id}/edition', function($data) use($renderEdition) {

		$data->eLesson = \learning\LessonLib::getById(GET('id'))->validate('canUpdate', 'acceptUpdate');
		$data->eCourse = $data->eLesson['course'];

		$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);

		// Pose `number`, `eLessonPrevious` et `eLessonNext`, vides aux deux bouts du programme : la
		// barre en tire les boutons qu'elle affiche.
		\learning\ChapterLib::locate($data->cChapter, $data->eLesson);

		// Le mode du contenu voyage dans l'adresse, validé contre sa liste : rien ne le retient d'une visite à l'autre.
		$data->mode = GET('mode', \learning\LessonUi::EDITION_MODES, \learning\LessonUi::MODE_EDITION);

		// La forme que dit la ligne sous le titre, dans les deux modes.
		\learning\LessonLib::fillFormat(new Collection([$data->eLesson]));

		if($data->mode === \learning\LessonUi::MODE_PREVIEW) {

			// Les blocs de la page de lecture, sans ceux qui n'ont rien à montrer.
			\learning\LessonLib::fillReadable($data->eLesson);
			\learning\LessonLib::fillContent($data->eLesson, new \learning\Enrollment(), new Collection());

		} else {

			$types = \learning\BlockOwnerLib::getTypesByOwner(\learning\BlockOwnerLib::LESSON, $data->eLesson);

			$data->eLesson['blockChoices'] = \block\BlockLib::getChoices($types);
			$data->eLesson['cBlock'] = \block\BlockLib::getByOwnerAndReference(\learning\BlockOwnerLib::LESSON, $data->eLesson['id']);

			foreach($data->eLesson['cBlock'] as $eBlock) {
				\block\BlockLib::fillEditor($eBlock, $types);
			}

			$data->eLesson['cBlockNew'] = \block\BlockLib::getNew(\learning\BlockOwnerLib::LESSON, $data->eLesson['id'], $types);

		}

		$data->eLesson['cAttachment'] = \learning\AttachmentLib::getByLesson($data->eLesson);

		\learning\QuestionLib::fillEditor($data->eLesson);

		$data->tab = GET('tab', \learning\LessonUi::EDITION_TABS, '');

		$renderEdition($data);

	})
	->post('doUpdatePositions', function($data) {

		$eChapter = \learning\ChapterLib::getById(POST('chapter'))->validate('canUpdate', 'acceptUpdate');

		$positions = json_decode(POST('positions'), TRUE);

		if(is_array($positions) === FALSE) {
			throw new NotExpectedAction('Positions');
		}

		$fw = new FailWatch();

		\learning\LessonLib::updatePositions($eChapter, array_map('intval', $positions));

		$fw->validate();

		throw new VoidAction();

	})
	->post('doDone', function($data) {

		\user\ConnectionLib::checkLogged();

		$eLesson = \learning\LessonLib::getById(POST('id'))->validate('assertRead', 'acceptDone');

		$eEnrollment = \learning\EnrollmentLib::getByUserAndCourse(\user\ConnectionLib::getOnline(), $eLesson['course']);

		if($eLesson['course']->canFollow($eEnrollment) === FALSE) {
			throw new NotAllowedAction();
		}

		\learning\ProgressLib::done($eEnrollment, $eLesson);

		throw new ReloadAction();

	})
	->post('doUndo', function($data) {

		\user\ConnectionLib::checkLogged();

		$eLesson = \learning\LessonLib::getById(POST('id'))->validate('assertRead', 'acceptUndo');

		$eEnrollment = \learning\EnrollmentLib::getByUserAndCourse(\user\ConnectionLib::getOnline(), $eLesson['course']);

		if($eLesson['course']->canFollow($eEnrollment) === FALSE) {
			throw new NotAllowedAction();
		}

		\learning\ProgressLib::undo($eEnrollment, $eLesson);

		throw new ReloadAction();

	});

/**
 * Le module se contrôle ici par `acceptWrite()` : `Lesson::acceptCreate()` rend TRUE pour l'API,
 * qui ne connaît le parent qu'après `build()`. En modification, `chapter.owned` lit les modules
 * de la formation, où la leçon peut changer de module.
 */
new \learning\LessonPage()
	->getCreateElement(function($data) {

		$eChapter = \learning\ChapterLib::getById(INPUT('chapter'))->validate('acceptWrite');

		return new \learning\Lesson([
			'course' => $eChapter['course'],
			'chapter' => $eChapter
		]);

	})
	/**
	 * L'étape `Lesson::chapter.owned` cherche le module posté dans `cChapter` : elle refuse ce qui
	 * n'est pas un module de la formation, et c'est aussi elle qui pose `course` sur la leçon.
	 */
	->applyElement(function($data, \learning\Lesson $eLesson) {
		$eLesson['cChapter'] = \learning\ChapterLib::getByCourse($eLesson['course']);
	})
	->create()
	->doCreate(fn($data) => throw new RedirectAction(\learning\LessonUi::urlEdition($data->e)))
	/**
	 * Le nombre de questions dit, sous le choix de la validation, ce qu'une leçon rendue à la
	 * déclaration garde, et ce qu'un quiz demandera au seuil choisi.
	 */
	->update(function($data) {

		$data->e['questions'] = \learning\QuestionLib::countByLesson($data->e);

		throw new ViewAction($data);

	}, page: 'updateSettings')
	/**
	 * Le panneau ouvert depuis les fichiers ou le quiz ramène sur son onglet, que l'écran ne retient
	 * pas d'un chargement à l'autre : l'origine voyage dans le lien puis dans le formulaire, jamais
	 * par `HTTP_REFERER`. Elle ne choisit qu'un onglet, et la leçon est écrite quand elle se lit : une
	 * valeur inconnue rend le rechargement de l'écran d'où le panneau a été ouvert, sans refus.
	 */
	->doUpdate(function($data) {

		$origin = POST('origin', \learning\LessonUi::EDITION_TABS, 'content');

		if($origin === 'content') {
			throw new ReloadAction('learning', 'Lesson::updated');
		}

		throw new RedirectAction(\learning\LessonUi::urlEdition($data->e).'?tab='.$origin.'&success=learning\\Lesson::updated');

	}, propertiesUpdate: ['chapter', 'name', 'objective', 'minutes', 'validation', 'quizThreshold'], page: 'doUpdateSettings')
	/**
	 * « Poser ce quiz », depuis la section du quiz : le passage au quiz du panneau des réglages, en
	 * un geste. Les valeurs sont construites ici et non postées, comme les gestes du catalogue, et
	 * passent par `build()`, dont l'étape `Lesson::validation.questioned`. Le seuil est celui que la
	 * leçon garde d'un quiz précédent, sinon le seuil par défaut, comme le panneau le préremplit
	 * (`LearningQuiz.toggleThreshold()`).
	 */
	->write('doPoseQuiz', function($data) {

		$fw = new FailWatch();

		$data->e->build(['validation', 'quizThreshold'], [
			'validation' => \learning\Lesson::QUIZ,
			'quizThreshold' => $data->e['quizThreshold'] ?? \learning\Lesson::QUIZ_THRESHOLD_DEFAULT
		]);

		$fw->validate();

		\learning\LessonLib::update($data->e, ['validation', 'quizThreshold']);

		\learning\QuestionLib::fillEditor($data->e);

		throw new ViewAction($data);

	}, validate: ['canUpdate', 'acceptUpdate'])
	/**
	 * Les issues de la barre de l'écran d'une leçon, une fois le bloc ouvert enregistré par le
	 * script : aucune écriture ici, seulement la destination. La voisine se cherche au moment du clic,
	 * sur le programme tel qu'il est alors. La destination voyage dans le lien, jamais par
	 * `HTTP_REFERER` ; une valeur inconnue est un appel forgé, et la page refuse plutôt que de
	 * choisir à sa place.
	 */
	->update(function($data) {

		$eLesson = $data->e;
		$destination = GET('destination');
		$success = 'success=learning\\Lesson::updated';

		if(in_array($destination, ['previous', 'next'], TRUE)) {

			\learning\ChapterLib::locate($eLesson['cChapter'], $eLesson);

			$eLessonTarget = match($destination) {
				'previous' => $eLesson['eLessonPrevious'],
				'next' => $eLesson['eLessonNext'],
			};

			if($eLessonTarget->notEmpty()) {
				throw new RedirectAction(\learning\LessonUi::urlEdition($eLessonTarget).'?'.$success);
			}

			// La barre n'offre pas le geste aux deux bouts du programme, mais une leçon peut avoir
			// changé de place depuis que la page a été rendue : le retour à la formation vaut mieux
			// qu'un refus sur un bouton qui existait.
			$destination = 'course';

		}

		// L'ancre se pose après la requête, sinon `?success=` en fait partie et le message ne s'affiche
		// jamais. L'onglet des leçons est demandé : l'ancre viserait sinon une leçon dans un panneau
		// caché, si le navigateur avait retenu un autre onglet.
		$url = match($destination) {
			'course' => \learning\CourseUi::urlEdition($eLesson['course']).'?tab=lessons&'.$success.'#lesson-'.$eLesson['id'],
			default => throw new NotExpectedAction('Destination')
		};

		throw new RedirectAction($url);

	}, page: 'leave')
	/**
	 * `validate:` retire `acceptDelete` et garde `acceptWrite`, sur qui il retombe : `ModulePage::doDelete()`
	 * valide avant d'ouvrir son `FailWatch`, et un refus posé dans un `accept*` sortirait sans message.
	 * Le refus qui parle est celui de `LessonLib::delete()`, qui lève `deleteLastPublished` ou `deleteCompleted`.
	 */
	->doDelete(fn() => throw new ReloadAction('learning', 'Lesson::deleted'), validate: ['canDelete', 'acceptWrite']);
?>
