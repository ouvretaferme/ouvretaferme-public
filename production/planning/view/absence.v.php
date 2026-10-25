<?php
new AdaptativeView('absence', function($data, StructureTemplate $t) {

	$t->title = s("Absences de {value}", $data->eStructure['name']);
	$t->nav = 'planning-v2';
	$t->canonical = \planning\AbsenceUi::url($data->eStructure, $data->week);

	$uiAbsence = new \planning\AbsenceUi();

	$t->mainTitle = $uiAbsence->getHeader();

	echo '<div id="planning-board">';
		echo $uiAbsence->get($data->eStructure, $data->week, $data->eMember, $data->manage, $data->canCreate, $data->cAbsence, $data->upcoming);
	echo '</div>';

});
?>
