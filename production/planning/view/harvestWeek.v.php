<?php
new AdaptativeView('harvestWeek', function($data, StructureTemplate $t) {

	$t->title = s("Récoltes de {value}", $data->eStructure['name']);
	$t->nav = 'planning-v2';
	$t->canonical = \planning\HarvestWeekUi::url($data->eStructure, $data->week);

	$uiHarvestWeek = new \planning\HarvestWeekUi();

	$t->mainTitle = $uiHarvestWeek->getCalendar($data->eStructure, $data->week);

	echo '<div id="planning-board">';
		echo $uiHarvestWeek->get($data->eStructure, $data->week, $data->days, $data->cCultivation, $data->eMember, $data->canHarvest, $data->canCreate, $data->quick, $data->canSales, $data->sales);
		if($data->canCreate) {
			echo $uiHarvestWeek->getBatch($data->eStructure, $data->week, $data->days);
		}
	echo '</div>';

});

/**
 * Un seul tiroir ouvert à la fois : celui qui l'était se retire avec sa marque, ce qui rend sa ligne
 * identique à celle que `getRow()` rend fermée.
 *
 * Le tiroir d'une part se pose sous elle, dans le rang de sa culture ; celui d'un couple qui n'a pas
 * encore de part, ou de la culture, en fin de rang.
 *
 * Au tactile, la feuille se pose sur le `body`, hors de la liste que les réponses remplacent, et
 * la précédente s'en retire.
 */
new JsonView('open', function($data, AjaxTemplate $t) {

	if($data->sheet) {

		$t->qsa('#planning-harvest-sheet')->remove();
		$t->qs('body')->insertAdjacentHtml('beforeend', new \planning\HarvestEntryUi()->getSheet($data->eStructure, $data->week, $data->eCultivation, $data->date, $data->days, $data->mode, $data->part, $data->units, $data->cUserTime, $data->taskWeek, $data->eTaskChosen, $data->timeKept, $data->tasksByUnit, $data->eTaskStock, $data->cCultivationSeries, $data->unitOther, $data->reopen, $data->unitFirst, $data->crate, $data->others));

		return;

	}

	$t->qsa('#planning-harvest-list .planning-harvest-drawer')->remove();
	$t->qsa('#planning-harvest-list .planning-harvest-open')->removeClass('planning-harvest-open');

	$drawer = new \planning\HarvestEntryUi()->getDrawer($data->eStructure, $data->week, $data->eCultivation, $data->date, $data->days, $data->mode, $data->part, $data->units, $data->cUserTime, $data->eTaskChosen, $data->timeKept, $data->deletable, $data->eTaskStock, $data->tasksByUnit, $data->unitOther, $data->reopen, $data->unitFirst, $data->crate, $data->others);

	$row = '#planning-harvest-'.$data->eCultivation['id'];

	if($data->part !== [] and array_key_exists($data->part['key'], $data->eCultivation['harvestedParts'])) {

		$t->qs($row.'-'.$data->part['key'])
			->addClass('planning-harvest-open')
			->insertAdjacentHtml('afterend', $drawer);

	} else {

		$t->qs($row)
			->addClass('planning-harvest-open')
			->insertAdjacentHtml('beforeend', $drawer);

	}

});

new AdaptativeView('create', function($data, PanelTemplate $t) {

	return new \planning\HarvestEntryUi()->create($data->eStructure, $data->week, $data->eCultivation, $data->unit, $data->units, $data->date, $data->dateMin, $data->dateMax, $data->days, $data->eUserOnline, $data->open, $data->taskWeek, $data->tasksByUnit, $data->eTaskStock, $data->origin);

});

/**
 * Le couple choisi dans « Récolter » (HM2) : les parts, le stock et l'unité suivent le couple, la répartition et
 * la quantité seulement quand ce qu'elles offrent change (`batchHarvestChoice`), et la saisie reste en place. Une
 * seule série n'a ni parts ni répartition à montrer.
 */
new JsonView('batchHarvestChoice', function($data, AjaxTemplate $t) {

	$form = new \util\FormUi();
	$uiTaskBatch = new \planning\TaskBatchUi();
	$panel = '#panel-planning-batch-harvest ';

	$t->qs($panel.'[data-planning-batch-harvest]')->setAttribute('data-unit', $data->unit);
	$t->qs($panel.'[data-planning-harvest-stock="planning-batch-harvest-stock"]')->outerHtml(new \planning\HarvestEntryUi()->getStock($form, 'planning-batch-harvest-stock', $data->eTaskStock));

	if($data->quantityChanged) {
		$t->qs($panel.'[data-wrapper="'.\planning\HarvestEntryUi::getQuantityWrapper('harvestMore').'"]')->outerHtml($uiTaskBatch->getHarvestQuantity($form, $data->ePlant, $data->unit, $data->tasksByUnit));
	}

	if($data->linesShown) {

		if($data->distributionChanged) {
			$t->qs($panel.'[data-planning-batch-harvest-distribution]')->outerHtml($uiTaskBatch->getHarvestDistribution($form, FALSE, $data->distributions, $data->distribution));
		}

		$t->qs($panel.'.planning-batch-time-lines')->outerHtml($uiTaskBatch->getHarvestLines($form, $data->cTaskBatch, $data->distributions, $data->distribution, $data->unit, $data->timeShare, FALSE));

	}

});

new JsonView('stock', function($data, AjaxTemplate $t) {

	$t->qs('[data-planning-harvest-stock="'.$data->stockId.'"]')->outerHtml(new \planning\HarvestEntryUi()->getStock(new \util\FormUi(), $data->stockId, $data->eTaskStock, $data->titled));

});

new JsonView('query', function($data, AjaxTemplate $t) {

	$t->push('results', \planning\HarvestEntryUi::getAutocompleteRanked($data->cCultivation, $data->suggested));

});

/**
 * Les lignes et les intertitres d'espèce que `HarvestWeekLib::getChange()` a retenus, dans l'ordre où ils
 * s'appliquent : les sorties, les intertitres et les lignes changés, puis les entrées dans l'ordre de la
 * liste, chacune après ce qui la précède et qui existe déjà à ce moment. Une liste qui naît ou se vide se
 * rend entière.
 *
 * `$list` ne vise que la liste de la semaine et de la forme de la réponse (`data-quick`, `data-sales`) : « Nouvelle récolte »
 * s'ouvre aussi du menu, sur n'importe quel écran, et laisse intacte la liste d'une autre semaine. La forme suit
 * la préférence du membre, qu'un autre onglet ou un autre appareil a pu changer depuis le rendu : la liste de la
 * semaine restée dans l'autre forme se recharge (`PlanningHarvest.align()`), sans quoi la saisie qu'on vient d'y
 * faire, écrite en base, ne s'y verrait pas.
 */
function planningHarvestWeekChange(AjaxTemplate $t, string $list, array $change, \structure\Structure $eStructure, string $week, array $days, \Collection $cCultivation, \structure\Member $eMember, bool $canHarvest, bool $canCreate, bool $quick, bool $canSales, bool $sales): void {

	$t->js()->eval('PlanningHarvest.align("'.$week.'", '.($quick ? 1 : 0).', '.($sales ? 1 : 0).')');

	$uiHarvestWeek = new \planning\HarvestWeekUi();

	if($change['list']) {
		$t->qs($list)->outerHtml($uiHarvestWeek->get($eStructure, $week, $days, $cCultivation, $eMember, $canHarvest, $canCreate, $quick, $canSales, $sales));
		return;
	}

	foreach($change['removed'] as $cultivation) {
		$t->qs($list.' #planning-harvest-'.$cultivation)->remove();
	}

	foreach($change['sections']['removed'] as $plant) {
		$t->qs($list.' #planning-harvest-plant-'.$plant)->remove();
	}

	foreach($change['sections']['updated'] as $plant => $cultivation) {
		$t->qs($list.' #planning-harvest-plant-'.$plant)->outerHtml($uiHarvestWeek->getSection($cCultivation[$cultivation]['plant'], $days, $canCreate, $sales, $cCultivation[$cultivation]['sales']));
	}

	foreach($change['updated'] as $cultivation) {
		$t->qs($list.' #planning-harvest-'.$cultivation)->outerHtml($uiHarvestWeek->getRow($eStructure, $week, $days, $cCultivation[$cultivation], $canHarvest, $quick, $canCreate, $sales));
	}

	foreach($change['inserted'] as $cultivation => ['after' => $after, 'section' => $section]) {

		$eCultivation = $cCultivation[$cultivation];
		$selectorPlant = $list.' #planning-harvest-plant-'.$eCultivation['plant']['id'];

		if($section) {
			$t->qs(($after === NULL) ? $list.' .util-table-head' : $list.' #planning-harvest-'.$after)->insertAdjacentHtml('afterend', $uiHarvestWeek->getSection($eCultivation['plant'], $days, $canCreate, $sales, $eCultivation['sales']));
		}

		$t->qs(($section or $after === NULL) ? $selectorPlant : $list.' #planning-harvest-'.$after)->insertAdjacentHtml('afterend', $uiHarvestWeek->getRow($eStructure, $week, $days, $eCultivation, $canHarvest, $quick, $canCreate, $sales));

	}

}

/**
 * La liste à laquelle s'adresse une réponse : celle de la semaine, dans la forme où la page l'a rendue.
 */
function planningHarvestWeekList(string $week, bool $quick, bool $sales): string {
	return '#planning-harvest-list[data-week="'.$week.'"][data-quick="'.($quick ? 1 : 0).'"][data-sales="'.($sales ? 1 : 0).'"]';
}

/**
 * La fenêtre des ventes à venir choisie dans l'en-tête : l'en-tête, puis la case des ventes de l'intertitre de
 * chaque espèce de la semaine. Comme pour une saisie (`planningHarvestWeekChange()`), la liste restée dans une
 * autre forme que la préférence du membre se recharge, sans quoi la fenêtre écrite ne s'y verrait pas.
 */
new JsonView('doSalesDays', function($data, AjaxTemplate $t) {

	// Ouvert, le menu est déplacé dans `<body>` (`lime.js`) : remplacer l'en-tête sans le fermer l'y laisserait.
	$t->js()->closeDropdowns();

	$t->js()->eval('PlanningHarvest.align("'.$data->week.'", '.($data->quick ? 1 : 0).', '.($data->sales ? 1 : 0).')');

	if($data->sales === FALSE) {
		return;
	}

	$list = planningHarvestWeekList($data->week, $data->quick, $data->sales);

	$uiHarvestWeek = new \planning\HarvestWeekUi();

	$t->qs($list.' .util-table-head > .planning-harvest-sales')->outerHtml($uiHarvestWeek->getSalesHead($data->eStructure, $data->week, $data->eMember));

	foreach($data->soldByPlant as $plant => $sold) {
		$t->qs($list.' #planning-harvest-plant-'.$plant.' > .planning-harvest-sales')->outerHtml($uiHarvestWeek->getSalesCell($sold));
	}

});

/**
 * La réponse d'une saisie : les lignes qu'elle change (`planningHarvestWeekChange()`), puis la case qu'elle
 * éclaire et le curseur.
 */
new JsonView('doHarvest', function($data, AjaxTemplate $t) {

	$list = planningHarvestWeekList($data->week, $data->quick, $data->sales);

	planningHarvestWeekChange($t, $list, $data->change, $data->eStructure, $data->week, $data->days, $data->cCultivation, $data->eMember, $data->canHarvest, $data->canCreate, $data->quick, $data->canSales, $data->sales);

	// Après les lignes : la case éclairée est celle que la réponse vient de poser.
	if($data->written !== []) {
		$t->js()->highlight(array_map(fn(string $selector) => $list.' '.$selector, \planning\HarvestWeekUi::getWrittenSelectors($data->written)));
	}

	// En saisie rapide, la ligne ne prend pas le curseur : la saisie s'y fait case par case, dans le champ de chacune.
	if($data->quick === FALSE and $data->eCultivationNext->notEmpty()) {
		$t->qs($list.' #planning-harvest-'.$data->eCultivationNext['id'])->focus();
	}

	if($data->panel) {
		$t->js()->closePanel('#panel-planning-harvest-create');
	}

	if($data->origin === 'menu') {
		$t->js()->success('planning', 'Task::harvested');
	}

	if($data->sheet) {
		$t->qs('#planning-harvest-sheet')->remove();
	}

});

/**
 * La réponse à une saisie du champ du menu (`PlanningHarvestEntry`) : les lignes des espèces reposées
 * (`HarvestWeekLib::getChangePlants()`) partent avec leurs intertitres, puis reviennent à leur place. Leurs
 * sous-lignes sont dans la ligne et partent avec elle.
 */
new JsonView('entry', function($data, AjaxTemplate $t) {

	$list = planningHarvestWeekList($data->week, $data->quick, $data->sales);

	if($data->change['list'] === FALSE) {
		foreach($data->change['sections']['removed'] as $plant) {
			$t->qsa($list.' .util-table-row[data-plant="'.$plant.'"]')->remove();
		}
	}

	planningHarvestWeekChange($t, $list, $data->change, $data->eStructure, $data->week, $data->days, $data->cCultivation, $data->eMember, $data->canHarvest, $data->canCreate, $data->quick, $data->canSales, $data->sales);

});

/**
 * La réponse de « Récolter » sur les séries cochées (DG-2) : la sélection se vide avant que les lignes
 * redessinées ne la relisent (`PlanningHarvestSelection.restore()`), puis les lignes que la récolte change.
 */
new JsonView('doBatchHarvest', function($data, AjaxTemplate $t) {

	$t->js()->closePanel('#panel-planning-batch-harvest');
	$t->js()->eval('PlanningHarvestSelection.clear()');

	planningHarvestWeekChange($t, planningHarvestWeekList($data->week, $data->quick, $data->sales), $data->change, $data->eStructure, $data->week, $data->days, $data->cCultivation, $data->eMember, $data->canHarvest, $data->canCreate, $data->quick, $data->canSales, $data->sales);

});
?>
