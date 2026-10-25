<?php
new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \learning\AttachmentUi()->update($data->e);

});

new JsonView('doUpload', function($data, AjaxTemplate $t) {

	$t->js()->success('learning', 'Attachment::created');
	$t->qs('#learning-lesson-tab-attachments')->innerHtml(new \learning\LessonUi()->getEditionTab($data->eLesson, 'attachments'));
	$t->js()->eval('LearningLesson.refreshTabs()');
	$t->qs('#lesson-attachments')->outerHtml(new \learning\AttachmentUi()->getEditor($data->eLesson));

});

new JsonView('doUpdate', function($data, AjaxTemplate $t) {

	$t->js()->success('learning', 'Attachment::updated');
	$t->js()->closePanel('#panel-learning-attachment');
	$t->qs('#lesson-attachments')->outerHtml(new \learning\AttachmentUi()->getEditor($data->eLesson));

});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('learning', 'Attachment::deleted');
	$t->qs('#learning-lesson-tab-attachments')->innerHtml(new \learning\LessonUi()->getEditionTab($data->eLesson, 'attachments'));
	$t->js()->eval('LearningLesson.refreshTabs()');
	$t->qs('#lesson-attachments')->outerHtml(new \learning\AttachmentUi()->getEditor($data->eLesson));

});
?>
