<?php
/**
 * Commencer une formation, c'est s'y inscrire. Un compte à plusieurs structures choisit dans un
 * panneau celle au nom de laquelle il la suit ; le bouton d'un compte à une structure poste
 * celle-ci, celui d'un compte sans structure n'en poste aucune, et `build()` juge les trois. Le
 * panneau s'ouvre aussi à tous quand le réseau demande à chacun s'il cherche une prise en charge
 * (`Enrollment::hasFundingQuestion()`).
 *
 * Sur la fiche d'une formation qui s'offre à l'inscription (`Course::isEnrollFirst()`), le même
 * geste se dit « S'inscrire » et ramène à la fiche.
 *
 * Commencée depuis une leçon, la formation y ramène : le bouton poste `origin`, que le panneau
 * reçoit en GET et réémet, et `ChapterLib::getLanding()` ne la retient que si elle est de la
 * formation.
 */
new \learning\EnrollmentPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$eEnrollment = new \learning\Enrollment([
			'user' => \user\ConnectionLib::getOnline(),
			'course' => \learning\CourseLib::getById(INPUT('course'))->validate('assertPublicRead'),
			'fundingRequested' => NULL
		]);

		$eEnrollment['course']['onlineConfirm'] = \learning\EnrollmentLib::getOnlineConfirm($eEnrollment['course']);

		// Le bouton ne se propose plus après l'échéance, ni pendant une pause, ni avant l'inscription
		// confirmée à une date, ni sur une formation datée sans leçon, mais un onglet ouvert la veille
		// le montre encore : le refus porte un message, là où la validation d'`acceptCreate()` par la
		// page n'en a pas. Les refus définitifs passent avant la pause, qui invite à revenir, et
		// avant l'inscription à confirmer, qu'un message ne promet donc jamais en vain.
		if($eEnrollment->acceptCourse() === FALSE) {
			throw new FailAction('learning\Enrollment::tutorial');
		} else if($eEnrollment->acceptLessons() === FALSE) {
			throw new FailAction('learning\Enrollment::lessons');
		} else if($eEnrollment->acceptWindow() === FALSE) {
			throw new FailAction('learning\Enrollment::closed');
		} else if($eEnrollment->acceptPause() === FALSE) {
			throw new FailAction('learning\Enrollment::paused');
		} else if($eEnrollment->acceptDated() === FALSE) {
			throw new FailAction('learning\Enrollment::dated');
		}

		// Lu avant l'écriture : une fois inscrite, la personne lit, et la question ne se pose plus.
		$data->enrollFirst = $eEnrollment['course']->isEnrollFirst();

		return $eEnrollment;

	})
	->create(function($data) {

		$data->e['cStructure'] = \learning\EnrollmentLib::getStructures($data->e['course']);
		$data->e['structure'] = \learning\EnrollmentLib::getPreselected($data->e['course'], $data->e['cStructure']);

		// Le panneau porte le mot du bouton qui l'a ouvert : celui de la fiche ne poste pas de leçon
		// d'origine.
		$data->enrollFirst = (
			$data->enrollFirst and
			get_exists('origin') === FALSE
		);

		throw new ViewAction($data);

	})
	->doCreate(function($data) {

		// Une demande ne donne pas encore la lecture : la personne revient à la fiche, qui la montre
		// « Demande envoyée ».
		if($data->e['status'] === \learning\Enrollment::REQUESTED) {
			throw new RedirectAction(\util\HttpUi::setArgument(\learning\CourseUi::url($data->e['course']), 'success', 'learning\\Enrollment::requested'));
		}

		$eLessonOrigin = \learning\LessonLib::getById(POST('origin', 'int'));

		$eLesson = \learning\ChapterLib::getLanding(\learning\ChapterLib::getByCourse($data->e['course']), $eLessonOrigin);

		if($eLesson->empty()) {
			throw new ReloadAction();
		}

		// Depuis la fiche, le geste était « S'inscrire » : elle revient et le dit, la personne
		// commence quand elle veut.
		if(
			$eLessonOrigin->empty() and
			$data->enrollFirst
		) {
			throw new RedirectAction(\learning\CourseUi::url($data->e['course']));
		}

		// Sous un quiz, « Commencer » est posé au pied des questions : le lecteur revient sur le
		// quiz, qu'il vient de lire, et non en haut de la leçon.
		if(
			$eLesson->is($eLessonOrigin) and
			$eLesson['validation'] === \learning\Lesson::QUIZ
		) {
			throw new RedirectAction(\learning\QuizUi::url($eLesson));
		}

		throw new RedirectAction(\learning\LessonUi::url($eLesson));

	})
	->write('doCancel', function($data) {

		\learning\EnrollmentLib::cancel($data->e);

		throw new ReloadAction('learning', 'Enrollment::canceled');

	}, validate: ['canCancel', 'acceptCancel'])
	/**
	 * Annuler une demande ne change que la table des demandes, redessinée seule ; la confirmer fait
	 * entrer un stagiaire dans la grille, à une place qui dépend de la vue (grille ou leçon, ferme
	 * choisie, barre des fermes qui peut gagner la sienne) : l'écran se recharge.
	 */
	->write('doCancelByCommunity', function($data) {

		\learning\EnrollmentLib::cancelByCommunity($data->e);

		$data->cEnrollmentRequest = \learning\EnrollmentLib::getRequestsByCourse($data->e['course']);

		throw new ViewAction($data);

	}, validate: ['canManage', 'acceptCancel'])
	->write('doConfirm', function($data) {

		$fw = new FailWatch();

		\learning\EnrollmentLib::confirm($data->e);

		$fw->validate();

		throw new ReloadAction('learning', 'Enrollment::confirmedByCommunity');

	}, validate: ['canManage', 'acceptConfirm']);
?>
