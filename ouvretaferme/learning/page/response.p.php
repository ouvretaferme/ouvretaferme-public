<?php
/**
 * Le quiz d'une leçon, du côté du lecteur : l'envoi d'une tentative, et le quiz rendu vierge pour
 * la suivante.
 *
 * Les deux gestes répondent par la section du quiz et laissent le défilement où il est : le
 * résultat et ses boutons se rendent en pied du quiz, à la place du bouton qui vient d'être pressé
 * (`QuizUi::get()`).
 *
 * La section dit le geste qui reste par `ProgressUi::getFollow()`, d'où la même préparation que la
 * lecture de la leçon (`lesson.p.php`), bornée à ce que le lecteur inscrit voit : les deux routes demandent
 * une inscription, `cStructure` ne sert qu'au bouton qui la propose.
 *
 * Les deux valident `canAnswer` : le quiz d'une formation datée est fermé côté serveur, et non
 * seulement à l'écran, avant son ouverture et à qui n'est pas confirmé à une date.
 */
/**
 * Le gérant qui essaie un thème sur la leçon y répond au quiz sans quitter l'essai : le formulaire et
 * « Nouvelle tentative » renvoient les réglages de l'adresse, relus comme la leçon les relit
 * (`lesson.p.php`), pour que la section et la colonne redessinées les gardent dans leurs liens. Lu avant
 * toute écriture : un réglage refusé ne vient que d'une requête écrite à la main, la page n'ayant
 * posté que ce qu'elle montrait déjà.
 */
$customize = function($data, array $input) {

	$eCourse = $data->eLesson['course'];

	$isManager = ($eCourse->isNetwork() and $eCourse['community']->canManage());

	if($isManager and isset($input['customize'])) {

		$eCommunity = \structure\CommunityLib::getByStructure($eCourse['community']);

		\learning\CommunityLib::applyPreview($eCommunity, $input, onKo: fn() => throw new NotExpectedAction('Customize settings refused'));

		$eCourse['customizeQuery'] = \learning\CustomizeUi::getQuery(\learning\CommunityLib::getSettings($eCommunity)['input']);

	} else {
		$eCourse['customizeQuery'] = [];
	}

};

$load = function($data, bool $retry) {

	$eUser = \user\ConnectionLib::getOnline();

	$data->eCourse = $data->eLesson['course'];

	$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
	\learning\ChapterLib::locate($data->cChapter, $data->eLesson);

	$data->eCourse['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);
	$data->eCourse['canProgress'] = $data->eCourse->canProgress();
	$data->eCourse['acceptEnrollment'] = FALSE;
	$data->eCourse['eShare'] = \learning\ShareLib::getWindowByUserAndCourse($eUser, $data->eCourse);

	$data->cProgress = \learning\ProgressLib::getDoneByUserAndCourse($eUser, $data->eCourse);
	$data->cStructure = new Collection();

	\learning\ResponseLib::fillQuiz($eUser, $data->eLesson, $retry);

};

new Page(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	/**
	 * Un quiz sans question ne se tente pas : sous une formation publiée, `Lesson::validation.questioned`
	 * et `QuestionLib::delete()` empêchent d'y arriver, et le formulaire n'a alors rien à envoyer.
	 */
	->post('doAnswer', function($data) use($customize, $load) {

		$data->eLesson = \learning\LessonLib::getById(POST('lesson'))->validate('assertRead', 'canAnswer', 'acceptAnswer');

		$data->eEnrollment = \learning\EnrollmentLib::getByUserAndCourse(\user\ConnectionLib::getOnline(), $data->eLesson['course']);

		if($data->eLesson['course']->canFollow($data->eEnrollment) === FALSE) {
			throw new NotAllowedAction();
		}

		$customize($data, $_POST);

		$cQuestion = \learning\QuestionLib::getByLesson($data->eLesson);

		if($cQuestion->empty()) {
			throw new NotExpectedAction('Quiz without question');
		}

		$answers = \learning\ResponseLib::buildAnswers($cQuestion, POST('answer', 'array', []));

		$fw = new FailWatch();

		\learning\ResponseLib::answer($data->eEnrollment, $data->eLesson, $cQuestion, $answers);

		$fw->validate();

		$load($data, retry: FALSE);

		$data->passed = $data->cProgress->offsetExists($data->eLesson['id']);

		// Réussie, la leçon redessine aussi la colonne, qui la coche et compte une leçon suivie de plus
		// (`view/response.v.php`) : elle se prépare comme à l'ouverture de la leçon.
		if($data->passed) {
			\learning\LessonLib::fillWritten($data->eCourse, $data->cChapter, $data->cProgress, $data->eLesson);
			$data->eCourse['canFollow'] = $data->eCourse->canFollow($data->eEnrollment);
		}

		throw new ViewAction($data);

	})
	->get('retry', function($data) use($customize, $load) {

		$data->eLesson = \learning\LessonLib::getById(GET('lesson'))->validate('assertRead', 'canAnswer', 'acceptAnswer');

		$data->eEnrollment = \learning\EnrollmentLib::getByUserAndCourse(\user\ConnectionLib::getOnline(), $data->eLesson['course']);

		if($data->eLesson['course']->canFollow($data->eEnrollment) === FALSE) {
			throw new NotAllowedAction();
		}

		$customize($data, $_GET);

		$load($data, retry: TRUE);

		throw new ViewAction($data);

	});
?>
