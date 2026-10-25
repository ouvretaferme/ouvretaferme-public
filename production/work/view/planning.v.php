<?php
new AdaptativeView('getWeeklyPdf', function($data, PdfTemplate $t) {

	$t->title = s("Planning de la semaine {week}, {year}", ['week' => week_number($data->week), 'year' => week_year($data->week)]);

	echo \main\PdfUi::getPage('0.75cm');

	echo '<style>';
		echo 'html { font-size: 9px !important; }';
	echo '</style>';
	
	echo \main\PdfUi::getHeader();
	echo \main\PdfUi::getStructure($data->e, $t->title, s("Du {from} au {to}", ['from' => \util\DateUi::textual(week_date_starts($data->week)), 'to' => \util\DateUi::textual(week_date_ends($data->week))]));

		echo '<h3>';
			if($data->e['logo'] !== NULL) {
				echo \structure\StructureUi::getLogo($data->e, '2rem').'  ';
			}
			echo encode($data->e['name']);
		echo '</h3>';
	echo '</div>';

	echo '<div id="planning-export-container">';
		$planningUi = new \series\PlanningUi();
		echo $planningUi->getExportPlanning($data->e, $data->week, $data->ccTask);
		echo $planningUi->getExportDelayed($data->e, $data->week, $data->ccTaskDelayed);
	echo '</div>';

	echo \main\PdfUi::getFooter($data->e, note: s("Planning édité le {date}.", ['date' => \util\DateUi::numeric(\Clock::date(), \util\DateUi::DATE)]), page: s("Page 1"));

});
?>
