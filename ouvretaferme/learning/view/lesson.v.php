<?php
/**
 * La leçon dans le gabarit des pages publiques, comme `/faq` : la bande, puis la colonne du module à
 * côté du contenu. Le contenu se lit d'abord, ses fichiers, le geste qui valide la leçon, puis la
 * paire qui mène à la suite, au-dessus des commentaires, qu'un long fil ne repousse pas. Sous un
 * réseau, la page prend son thème sur la portée des pages publiques (`CustomizeUi::getPublic()`), et
 * la barre de personnalisation reste dehors : dedans, elle prendrait la police et les jetons du
 * thème qu'elle règle.
 */
new AdaptativeView('/formation/lecon/{id}', function($data, BaseTemplate $t) {

	$lessonUi = new \learning\LessonUi();

	$t->template .= ' presentation';
	$t->title = $data->eLesson['name'];
	$t->header = '';
	$t->mainContainer = FALSE;

	$content = $lessonUi->getPosition($data->eLesson);
	$content .= $lessonUi->getObjective($data->eLesson);
	$content .= $lessonUi->getContent($data->eLesson);
	$content .= new \learning\AttachmentUi()->getReader($data->eLesson);

	$content .= match($data->eLesson['validation']) {
		\learning\Lesson::QUIZ => new \learning\QuizUi()->get($data->eLesson, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged),
		\learning\Lesson::DECLARED => new \learning\ProgressUi()->getFollow($data->eLesson, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged),
	};
	$content .= $lessonUi->getPager($data->eLesson, $data->eCourse);
	$content .= new \learning\CommentUi()->getThread($data->eLesson);
	$content .= $lessonUi->getReading($data->eLesson);

	$h = $lessonUi->getHero($data->eLesson, $data->eCourse, $data->cChapter);
	$h .= $lessonUi->getBody($lessonUi->getSide($data->eLesson, $data->eCourse, $data->cChapter, $data->cProgress, $data->eEnrollment), $content);

	if($data->settings === NULL) {
		echo $h;
		return;
	}

	$t->template .= ' learning-public';

	echo \learning\CustomizeUi::getStyles($data->settings, 'public');

	if($data->customize) {
		echo new \learning\CustomizeUi()->get(\learning\LessonUi::url($data->eLesson).'/:doPreview', \learning\CourseUi::url($data->eCourse).'/:doCustomize', \learning\LessonUi::url($data->eLesson), $data->settings);
	}

	echo \learning\CustomizeUi::getPublic($data->settings['customDesign'], $h);

});

new AdaptativeView('edition', function($data, AdminTemplate $t) {

	$ui = new \learning\LessonUi();

	$t->title = $data->eLesson['name'];

	$t->header = '<div class="admin-navigation stick-xs">';
		$t->header .= new \main\AdminUi()->getNavigation('learning');
	$t->header .= '</div>';

	// L'éditeur de blocs, partagé, laisse sa marge à l'écran qui l'appelle, là où la formation enchaîne sur une section en `mt-2`.
	echo '<div class="mb-2">'.$ui->getEditionHead($data->eLesson, $data->cChapter).'</div>';

	echo $ui->getEdition($data->eLesson, $data->tab, $data->mode);

});

new AdaptativeView('editionCommunity', function($data, CommunityTemplate $t) {

	$ui = new \learning\LessonUi();

	$t->tab = 'learning';
	$t->title = $data->eLesson['name'];
	$t->mainTitle = $ui->getEditionHead($data->eLesson, $data->cChapter);

	$edition = $ui->getEditionTabs($data->eLesson, $data->tab, $data->mode);

	$t->mainTabs = $edition['items'];
	$t->mainTabsAttributes = $edition['attributes'];
	$t->mainTabsFold = TRUE;

	echo $edition['panels'];
	echo $ui->getEditionBar($data->eLesson);

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\LessonUi()->create($data->e);

});

new AdaptativeView('updateSettings', function($data, PanelTemplate $t) {

	return new \learning\LessonUi()->updateSettings($data->e);

});

new JsonView('doPoseQuiz', function($data, AjaxTemplate $t) {

	$t->js()->success('learning', 'Lesson::quizPosed');
	$t->qs('#lesson-quiz')->outerHtml(new \learning\QuestionUi()->getEditor($data->e));

});
?>
