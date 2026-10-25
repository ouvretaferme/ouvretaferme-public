<?php
new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \learning\ChapterUi()->create($data->e);

});

new AdaptativeView('update', function($data, PanelTemplate $t) {

	return new \learning\ChapterUi()->update($data->e);

});
?>
