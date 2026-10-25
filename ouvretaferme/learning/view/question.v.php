<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\QuestionUi()->create($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \learning\QuestionUi()->update($data->e);

});

new JsonView('doCreate', function($data, AjaxTemplate $t) {

	$t->js()->success('learning', 'Question::created');
	$t->js()->closePanel('#panel-learning-question');
	$t->qs('#learning-lesson-tab-quiz')->innerHtml(new \learning\LessonUi()->getEditionTab($data->eLesson, 'quiz'));
	$t->js()->eval('LearningLesson.refreshTabs()');
	$t->qs('#lesson-quiz')->outerHtml(new \learning\QuestionUi()->getEditor($data->eLesson));

});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->success('learning', 'Question::updated');
	$t->js()->closePanel('#panel-learning-question');
	$t->qs('#lesson-quiz')->outerHtml(new \learning\QuestionUi()->getEditor($data->eLesson));

});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('learning', 'Question::deleted');
	$t->qs('#learning-lesson-tab-quiz')->innerHtml(new \learning\LessonUi()->getEditionTab($data->eLesson, 'quiz'));
	$t->js()->eval('LearningLesson.refreshTabs()');
	$t->qs('#lesson-quiz')->outerHtml(new \learning\QuestionUi()->getEditor($data->eLesson));

});
?>
