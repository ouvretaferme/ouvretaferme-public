<?php
new AdaptativeView('/formation/{id}', function($data, LearningAiTemplate $t) {

	$ui = new \learning\CourseUi();

	$t->title = $data->eCourse['name'];
	$t->menu = $ui->getMenu($data->eCourse, $data->cChapterMenu, $data->cProgress, $data->isLogged, $data->hasCatalog);

	if($data->settings !== NULL) {
		$t->custom = TRUE;
		$t->design = $data->settings['customDesign'];
		echo \learning\CustomizeUi::getStyles($data->settings, 'doc');
	}

	if($data->customize) {
		$t->customizeBar = new \learning\CustomizeUi()->get(\learning\CourseUi::url($data->eCourse).'/:doPreview', \learning\CourseUi::url($data->eCourse).'/:doCustomize', \learning\CourseUi::url($data->eCourse), $data->settings);
	}

	echo $ui->getFiche($data->eCourse, $data->cChapter, $data->cStaff, $data->eEnrollment, $data->cProgress, $data->cStructure, $data->isLogged, $data->cDate);

});

new AdaptativeView('edition', function($data, AdminTemplate $t) {

	$ui = new \learning\CourseUi();

	$t->title = $data->eCourse['name'];

	$t->header = '<div class="admin-navigation stick-xs">';
		$t->header .= new \main\AdminUi()->getNavigation('learning');
	$t->header .= '</div>';

	echo '<div class="mb-2">'.$ui->getEditionHead($data->eCourse).'</div>';

	echo $ui->getEdition($data->eCourse, $data->cChapter, $data->cDate, $data->cStaff, $data->tab, $data->withDiffusion, $data->cDatePlanned);

});

new AdaptativeView('editionCommunity', function($data, CommunityTemplate $t) {

	$ui = new \learning\CourseUi();

	$t->tab = 'learning';
	$t->title = $data->eCourse['name'];
	$t->mainTitle = $ui->getEditionHead($data->eCourse);

	$edition = $ui->getEditionTabs($data->eCourse, $data->cChapter, $data->cDate, $data->cStaff, $data->tab, $data->withDiffusion, $data->cDatePlanned);

	$t->mainTabs = $edition['items'];
	$t->mainTabsAttributes = $edition['attributes'];
	$t->mainTabsFold = TRUE;

	echo $edition['panels'];

});

new AdaptativeView('bilan', function($data, AdminTemplate $t) {

	$ui = new \learning\CourseUi();
	$uiUsage = new \learning\UsageUi();

	$t->title = $data->eCourse['name'];

	$t->header = '<div class="admin-navigation stick-xs">';
		$t->header .= new \main\AdminUi()->getNavigation('learning');
	$t->header .= '</div>';

	echo '<div class="mb-2">'.$ui->getEditionHead($data->eCourse, $uiUsage->getSeason($data->eCourse, $data->usage, $data->indicator)).'</div>';

	$edition = $ui->getEditionLinks($data->eCourse, $data->withDiffusion);

	echo '<div class="tabs tabs-h tabs-h-fold">';
		echo '<div class="tabs-item">'.$edition['items'].'</div>';
		echo '<div class="tab-panel selected">'.$uiUsage->get($data->eCourse, $data->cChapter, $data->usage, $data->indicator, $data->withDiffusion).'</div>';
	echo '</div>';

});

new AdaptativeView('bilanCommunity', function($data, CommunityTemplate $t) {

	$ui = new \learning\CourseUi();
	$uiUsage = new \learning\UsageUi();

	$t->tab = 'learning';
	$t->title = $data->eCourse['name'];
	$t->mainTitle = $ui->getEditionHead($data->eCourse, $uiUsage->getSeason($data->eCourse, $data->usage, $data->indicator));

	$edition = $ui->getEditionLinks($data->eCourse, $data->withDiffusion);

	$t->mainTabs = $edition['items'];
	$t->mainTabsAttributes = $edition['attributes'];
	$t->mainTabsFold = TRUE;

	echo '<div class="tab-panel selected">'.$uiUsage->get($data->eCourse, $data->cChapter, $data->usage, $data->indicator, $data->withDiffusion).'</div>';

});

new AdaptativeView('start', function($data, AdminTemplate $t) {

	$t->title = $data->eCourse->exists() ? $data->eCourse['name'] : s("Nouvelle formation");

	$t->header = '<div class="admin-navigation stick-xs">';
		$t->header .= new \main\AdminUi()->getNavigation('learning');
	$t->header .= '</div>';

	echo new \learning\CourseStartUi()->getStart($data->eCourse, $data->step, $data->withBack);

});

new AdaptativeView('startCommunity', function($data, CommunityTemplate $t) {

	$t->tab = 'learning';
	$t->title = $data->eCourse->exists() ? $data->eCourse['name'] : s("Nouvelle formation");
	$t->mainTitle = '';

	echo new \learning\CourseStartUi()->getStart($data->eCourse, $data->step, $data->withBack);

});

new AdaptativeView('updateOffer', function($data, PanelTemplate $t) {

	return new \learning\CourseUi()->updateOffer($data->e);

});

new AdaptativeView('updateDetails', function($data, PanelTemplate $t) {

	return new \learning\CourseUi()->updateDetails($data->e);

});

new AdaptativeView('updatePrice', function($data, PanelTemplate $t) {

	return new \learning\CourseUi()->updatePrice($data->e);

});

new AdaptativeView('updateExternal', function($data, PanelTemplate $t) {

	return new \learning\CourseUi()->updateExternal($data->e);

});

new AdaptativeView('updateSettings', function($data, PanelTemplate $t) {

	return new \learning\CourseUi()->updateSettings($data->e);

});

new AdaptativeView('publish', function($data, PanelTemplate $t) {

	return new \learning\CourseUi()->publish($data->e, $data->cChapter, $data->propertiesMissing, $data->cLessonMissing, $data->programEmpty, $data->recommended);

});

new AdaptativeView('duplicate', function($data, PanelTemplate $t) {

	return new \learning\CourseUi()->duplicate($data->eCourse, $data->eCourseNew, $data->cChapter);

});

new AdaptativeView('share', function($data, PanelTemplate $t) {

	if($data->e['community']->isNetwork()) {
		return new \learning\ShareUi()->shareNetwork($data->e, $data->cChapter, $data->eShare);
	}

	return new \learning\ShareUi()->share($data->e, $data->cChapter, $data->cSection);

});

// L'interrupteur de la formation, et les réglages de chaque date à venir, dont la pause de la
// formation cache l'interrupteur et que sa reprise fait reparaître : rien d'autre de l'éditeur ne
// lit la pause.
new JsonView('doUpdateEnrollmentPaused', function($data, AjaxTemplate $t) {

	$t->qs('#learning-course-enrollment-'.$data->e['id'])->outerHtml(\learning\CourseUi::getEnrollmentSwitch($data->e));

	foreach($data->cDate as $eDate) {
		$t->qs('#learning-date-deck-'.$eDate['id'])->outerHtml(new \learning\DateUi()->getEditionDeck($eDate));
	}

	$t->js()->success('learning', $data->e['enrollmentPaused'] ? 'Course::enrollmentPaused' : 'Course::enrollmentResumed');

});

new JsonView('doUpdateStatus', function($data, AjaxTemplate $t) {

	$t->ajaxRedirect('/learning/course:share?id='.$data->e['id'], purgeLayers: TRUE);
	$t->ajaxReload(purgeLayers: FALSE);
	$t->js()->success('learning', 'Course::updated');

});

new AdaptativeView('unpublish', function($data, PanelTemplate $t) {

	return new \learning\CourseUi()->unpublish($data->e, $data->cChapter);

});

new JsonView('getSubthemeField', function($data, AjaxTemplate $t) {

	$t->qs('#learning-course-subtheme-field')->outerHtml(\learning\CourseUi::getSubthemeField(new \util\FormUi(['columns' => $data->columns]), $data->eCourse));

});
?>
