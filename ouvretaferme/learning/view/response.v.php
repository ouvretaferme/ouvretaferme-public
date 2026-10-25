<?php
/**
 * Une leçon réussie change aussi la colonne, qui la coche et compte une leçon suivie de plus : elle
 * se redessine avec le quiz, et seulement dans ce cas. Le reste de la page ne bouge pas.
 */
new JsonView('doAnswer', function($data, AjaxTemplate $t) {

	$t->qs('#learning-quiz')->outerHtml(new \learning\QuizUi()->get($data->eLesson, $data->eEnrollment, $data->cProgress, $data->cStructure, TRUE));

	if($data->passed) {
		$t->qs('#learning-lesson-side')->outerHtml(new \learning\LessonUi()->getSide($data->eLesson, $data->eCourse, $data->cChapter, $data->cProgress, $data->eEnrollment));
	}

});

new JsonView('retry', function($data, AjaxTemplate $t) {

	$t->qs('#learning-quiz')->outerHtml(new \learning\QuizUi()->get($data->eLesson, $data->eEnrollment, $data->cProgress, $data->cStructure, TRUE));

});
?>
