<?php
new AdaptativeView('/mes-formations', function($data, BaseTemplate $t) {

	$t->title = s("Mes formations");
	$t->header = '';

	echo new \learning\CatalogUi()->getMine($data->ccShareSection, $data->ccShareProposed, $data->cCourseRunning, $data->cCourseDone, $data->started, $data->hasCatalog);

});
?>
