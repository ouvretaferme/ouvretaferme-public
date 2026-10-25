<?php
new JsonView('getByUser', function($data, AjaxTemplate $t) {

	$t->qs('#planning-weekly-time')->innerHtml(new \series\PlanningUi()->getWeekTime($data->eStructure, $data->week, new Collection(), $data->eUserTime, $data->cUserStructure));

});
?>
