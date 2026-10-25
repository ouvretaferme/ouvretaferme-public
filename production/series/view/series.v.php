<?php
new AdaptativeView('/serie/{id}', function($data, StructureTemplate $t) {

	$t->title = s("Série {value}", $data->e['name']);
	$t->nav = 'cultivation';
	$t->subNav = 'series';

	$t->mainTitle =  new \series\SeriesUi()->getHeader($data->e);

	echo new \series\CultivationUi()->getList($data->e, $data->cSeriesPerennial, $data->cCultivation, $data->cTask, $data->cPlace, $data->cActionMain);
	echo new \note\NoteUi()->getLinked($data->eStructure, $data->cNoteLinked, withTargets: TRUE);
	echo new \series\TaskUi()->getTimeline($data->eStructure, $data->e, $data->cCultivation, $data->cTask);

	if($data->eStructure->conf()['time']) {
		echo new \series\SeriesUi()->getWorkingTime($data->e, $data->cCultivation, $data->ccTask, $data->ccTaskHarvested);
	}

	echo new \series\SeriesUi()->getPhotos($data->e, $data->cPhoto);

});

new AdaptativeView('duplicate', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->duplicate($data->eStructure, $data->c, $data->cTaskMetadata, $data->hasPlaces);
});

new AdaptativeView('createSequence', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->createSequence($data->e, $data->cCultivation, $data->cTaskMetadata);
});

new AdaptativeView('updateSeasonCollection', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->updateSeason($data->eStructure, $data->c);
});

new AdaptativeView('closeCollection', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->closeCollection($data->c);
});

new AdaptativeView('numberCollection', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->numberCollection($data->c, $data->numbering);
});

new AdaptativeView('update', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->update($data->e);
});

new AdaptativeView('updateComment', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->updateComment($data->e);
});

new AdaptativeView('createFrom', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->createFrom($data->eStructure, $data->season);
});

new AdaptativeView('createFromPlant', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->createFromPlant($data->eStructure, $data->season, $data->eSeries, $data->eCultivation, $data->ePlant, $data->cAction);
});

new JsonView('addPlant', function($data, AjaxTemplate $t) {

	$t->qs('#series-create-plant input[name="index"]')->value($data->nextIndex);
	$t->qs('#series-create-plant-list')->insertAdjacentHtml('beforeend', new \series\SeriesUi()->addFromPlant($data->eSeries, $data->eCultivation, $data->ePlant, $data->nextIndex, $data->cAction));
	$t->qs('#series-create-add-plant input')->value('');
	$t->js()->eval('Series.showOrHideDeletePlant()');

});

new AdaptativeView('createFromSequence', function($data, PanelTemplate $t) {
	return new \series\SeriesUi()->createFromSequence($data->eStructure, $data->season, $data->eSequence, $data->cCultivation, $data->cFlow, $data->events);
});

new AdaptativeView('getTasksFromSequence', function($data, AjaxTemplate $t) {

	$t->qs('#series-create-tasks')->innerHtml(
		new \series\SeriesUi()->getTasksFromSequence($data->season, $data->eSequence, $data->events, $data->startYear, $data->startWeek)
	);

});

new AdaptativeView('getCultivation', function($data, PdfTemplate $t) {

	$t->title = s("Plan de culture {value}", $data->season);

	echo \main\PdfUi::getHeader();
	echo \main\PdfUi::getStructure($data->e, $t->title, s("Saison {value}", $data->season));

	echo new \series\CultivationUi()->getPrinting($data->season, $data->e, $data->ccCultivation);

	echo \main\PdfUi::getFooter($data->e, note: s("Plan de culture édité le {date}.", ['date' => \util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE)]), page: s("Page 1"));

});

new AdaptativeView('getSeedling', function($data, PdfTemplate $t) {

	$t->title = s("Semences et plants {value}", $data->season);

	echo \main\PdfUi::getHeader();
	$criteria = \series\HeaderUi::getSeedlingCriteria($data->search);

	echo \main\PdfUi::getStructure($data->e, $t->title, s("Saison {value}", $data->season).($criteria === '' ? '' : ' &middot; '.$criteria));

	echo new \series\CultivationUi()->getSeedlingPrinting($data->sections, $data->search);

	echo \main\PdfUi::getFooter($data->e, note: s("Semences et plants édités le {date}.", ['date' => \util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE)]));

});

new AdaptativeView('getSoil', function($data, PdfTemplate $t) {

	$t->title = match($data->soilView) {
		\structure\Member::GARDEN => s("Plan d'assolement {value}", $data->season),
		\structure\Member::PLAN => s("Liste d'assolement {value}", $data->season),
	};

	echo \main\PdfUi::getHeader();
	echo \main\PdfUi::getStructure($data->e, $t->title, s("Saison {value}", $data->season));

	echo match($data->soilView) {
		\structure\Member::GARDEN => new \series\PlaceGardenAiUi()->getPrinting($data->e, $data->cZone, $data->season),
		\structure\Member::PLAN => new \map\ZoneUi()->getPrinting($data->e, $data->cZone, $data->season),
	};

	echo \main\PdfUi::getFooter($data->e, note: match($data->soilView) {
		\structure\Member::GARDEN => s("Plan d'assolement édité le {date}.", ['date' => \util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE)]),
		\structure\Member::PLAN => s("Liste d'assolement éditée le {date}.", ['date' => \util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE)]),
	});

});
?>
