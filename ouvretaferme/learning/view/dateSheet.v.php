<?php
new JsonView('doUpload', function($data, AjaxTemplate $t) {

	$t->qs('#learning-date-sheets')->outerHtml(new \learning\DateSheetUi()->getSection($data->eDate, $data->cDateSheet));

});

new JsonView('doDelete', function($data, AjaxTemplate $t) {

	$t->js()->success('learning', 'DateSheet::deleted');
	$t->qs('#learning-date-sheets')->outerHtml(new \learning\DateSheetUi()->getSection($data->eDate, $data->cDateSheet));

});
?>
