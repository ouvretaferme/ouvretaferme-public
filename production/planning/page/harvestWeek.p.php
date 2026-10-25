<?php
/**
 * Les récoltes d'une semaine, premier écran du nouveau planning : on y lit la semaine, et on y saisit
 * une récolte en un geste. La ligne s'ouvre sur un jour (`open`), le panneau « Nouvelle récolte »
 * (`create`, `query`) prend une culture que la liste ne montre pas.
 *
 * La saisie rapide (`viewPlanningHarvestQuick`, R-Retours-1210 V35) est une préférence du membre, écrite par
 * `structure/member:doUpdatePlanningHarvestQuick` : allumée, chaque case vide du jour ouvert porte son champ.
 * Les ventes à venir et le stock (`viewPlanningSales`, V47) en sont une autre, écrite par
 * `structure/member:doUpdatePlanningSales`. Chaque requête de l'écran relit les deux sur le membre, et la
 * réponse redessine les lignes dans cette forme.
 *
 * Toutes les saisies passent par `doHarvest`, qui écrit par `\series\TaskLib::writeHarvestForWeek()` et
 * répond par les seules lignes que l'écriture change (`HarvestWeekLib::getChange()`), jamais par la
 * page entière.
 *
 * Le panneau et la feuille s'ouvrent aussi depuis une carte de la Semaine (`origin=semaine`, retour 203) :
 * la saisie poste alors sur `taskWeek:doHarvest`, qui répond par la carte.
 */

/**
 * La carte de la Semaine d'où s'ouvre la saisie (`origin=semaine`) : une récolte de la culture saisie,
 * sans quoi rien ne s'ouvre. Pose sur `$data` ce que le formulaire réémet pour que la Semaine réponde par
 * l'écran que l'on regarde (`taskWeek`, vide hors de cette origine), et rend le jour que la carte propose
 * (`HarvestWeekLib::getTaskDate()`), `NULL` hors de cette origine.
 */
function planningHarvestWeekOrigin(\stdClass $data, \series\Cultivation $eCultivation): ?string {

	$data->taskWeek = [];
	$data->eTaskCard = new \series\Task();

	if(GET('origin') !== 'semaine') {
		return NULL;
	}

	$eTask = \planning\HarvestWeekLib::getTaskCard($data->eStructure, $eCultivation, GET('task', 'int'));

	if($eTask->empty()) {
		throw new NotExpectedAction('Not a harvest of this cultivation');
	}

	$data->eTaskCard = $eTask;

	$data->taskWeek = [
		'task' => $eTask['id'],
		'unplanned' => GET('unplanned'),
		'folded' => GET('folded'),
	];

	return \planning\HarvestWeekLib::getTaskDate($eTask, $data->days);

}

/**
 * Les lignes cochées de « Récolter » (DG-2), une série ou l'une de ses parts (FD, retour 405), dans l'ordre de
 * l'écran : des couples de cultures que la liste de la semaine montre, comme le tiroir (`open`), chacune écrite
 * au droit de la culture. Les cases ne s'affichent que pour qui récolte une semaine qui
 * a un jour où récolter (`canCreate`). La liste est relue sur les seules espèces que la récolte peut changer
 * (`HarvestWeekLib::getPlantsAround()`) : la récolte n'ajoute une quantité qu'à des lignes déjà listées, et
 * cette liste sert de liste d'avant.
 *
 * Les couples se lisent ici, et le couple choisi aux Récoltes (HM2) s'applique entre cette lecture et celle des
 * interventions (`planningHarvestWeekBatchTasks()`).
 *
 * @return array [les cultures, les sondes des couples, les espèces, la liste de la semaine sur ces espèces]
 */
function planningHarvestWeekBatch(\stdClass $data, array $values): array {

	if($data->canHarvest === FALSE) {
		throw new NotAllowedAction();
	}

	if($data->canCreate === FALSE) {
		throw new NotExpectedAction('No harvest in a future week');
	}

	$couples = \planning\HarvestWeekLib::getBatchCouples($values);
	$ids = array_values(array_unique(array_column($couples, 'cultivation')));

	$cPlant = \planning\HarvestWeekLib::getPlantsAround($data->eStructure, $ids);

	// Aucune culture postée n'est de la structure, et des espèces vides liraient toute la semaine.
	if($cPlant->empty()) {
		throw new FailAction('series\Cultivation::cultivations.listed');
	}

	// La liste de la semaine borne les séries, comme pour le tiroir : une série cochée peut en sortir avant
	// l'envoi, après une saisie faite sur un autre écran.
	$cCultivationListed = \planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE, cPlant: $cPlant);

	$cCultivation = new \Collection();

	foreach($ids as $id) {

		if($cCultivationListed->offsetExists($id) === FALSE) {
			throw new FailAction('series\Cultivation::cultivations.listed');
		}

		$cCultivation[$id] = $cCultivationListed[$id];

	}

	$cCultivation->validate('canWrite');

	$cTaskCouple = \planning\HarvestWeekLib::getBatchProbes($data->eStructure, $cCultivation, $couples);

	return [$cCultivation, $cTaskCouple, $cPlant, $cCultivationListed];

}

/**
 * L'intervention que reçoit chaque couple (`HarvestWeekLib::getTasksForBatch()`), indexée par couple, et l'unité
 * commune de leurs lignes.
 *
 * @return array [les interventions indexées par couple, l'unité]
 */
function planningHarvestWeekBatchTasks(\stdClass $data, \Collection $cTaskCouple): array {

	$cTask = \planning\HarvestWeekLib::getTasksForBatch($data->eStructure, $cTaskCouple, $data->week, $data->createDate);

	\planning\TaskBatchLib::validateHarvestsCouples($cTask);

	return [$cTask, \planning\HarvestWeekLib::getBatchUnit($cTaskCouple, $cTask)];

}

/**
 * La liste d'avant une saisie de la culture, relue sur les seules espèces que la saisie peut changer
 * (`HarvestWeekLib::getPlantsAround()`), et ces espèces, que la liste d'après reprend. Une espèce absente de
 * l'écran y entre avec son intertitre, que seule la semaine entière sait placer : la saisie relit alors toute
 * la semaine, et les espèces sont vides.
 *
 * @return array [les espèces, la liste d'avant]
 */
function planningHarvestWeekBefore(\stdClass $data, \series\Cultivation $eCultivation): array {

	$cPlant = \planning\HarvestWeekLib::getPlantsAround($data->eStructure, [$eCultivation['id']]);
	$cCultivationBefore = \planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE, cPlant: $cPlant);

	if(\planning\HarvestWeekLib::hasPlant($cCultivationBefore, $eCultivation['plant']) === FALSE) {
		$cPlant = new \Collection();
		$cCultivationBefore = \planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE);
	}

	return [$cPlant, $cCultivationBefore];

}

/**
 * La liste d'après une saisie, sur les espèces de la liste d'avant, et les lignes que l'écriture change.
 */
function planningHarvestWeekAfter(\stdClass $data, \series\Cultivation $eCultivation, \Collection $cPlant, \Collection $cCultivationBefore): void {

	$data->cCultivation = \planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE, cPlant: $cPlant);
	$data->change = \planning\HarvestWeekLib::getChangeAround($data->eStructure, $data->week, $cCultivationBefore, $data->cCultivation, $eCultivation, $cPlant);

}

/**
 * La fenêtre des ventes à venir du membre, et son dernier jour, d'aujourd'hui inclus, que l'en-tête de la
 * colonne écrit : une UI ne calcule pas de date.
 */
function planningHarvestWeekSalesWindow(\stdClass $data): void {

	$data->salesDays = $data->eMember['viewPlanningSalesDays'];
	$data->eMember['salesDate'] = date('Y-m-d', \Clock::shift('+'.$data->salesDays.' days'));

}

new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id');

		$data->eStructure->validate('canPlanningLimited');

		$data->week = INPUT('week', fn($value) => Filter::check('week', $value), \Clock::week());

		$data->eMember = $data->eStructure->getMember();
		$data->canHarvest = \planning\HarvestWeekLib::canHarvest($data->eStructure);

		$data->quick = $data->eStructure->getView('viewPlanningHarvestQuick');

		// Les ventes à venir et le stock, deux colonnes de la semaine pour qui la structure ouvre la vente et les a
		// allumés (V47), sur la fenêtre que règlent la roue du planning (`taskWeek:doSalesDays`) et l'en-tête de la
		// colonne (`doSalesDays`, V51).
		$data->canSales = $data->eStructure->canSelling();
		$data->sales = ($data->canSales and $data->eStructure->getView('viewPlanningSales'));
		$data->salesDays = NULL;

		if($data->sales) {
			planningHarvestWeekSalesWindow($data);
		}

		// Le jour ouvert à l'écran, que chaque requête de la semaine renvoie : la réponse redessine ses lignes
		// dans la même colonne. `getDays()` écarte un jour hors de la semaine ou à venir. La saisie rapide ouvre
		// le jour proposé d'une semaine passée sans qu'on l'ait choisi, pour que ses champs se voient sans clic.
		$defaultOpen = ($data->canHarvest and $data->quick);
		$data->days = \planning\HarvestWeekLib::getWeekDays($data->eStructure, $data->week, INPUT('open', fn($value) => Filter::check('date', $value)), $defaultOpen);

		$data->createDate = \planning\HarvestWeekLib::getCreateDate($data->days);
		$data->canCreate = ($data->canHarvest and $data->createDate !== NULL);

	})
	->get(['/ferme/{id}/planning-v2/recoltes', '/ferme/{id}/planning-v2/recoltes/{week@week}'], function($data) {

		$data->cCultivation = \planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE);

		throw new ViewAction($data, ':harvestWeek');

	})
	->get('open', function($data) {

		if($data->canHarvest === FALSE) {
			throw new NotAllowedAction();
		}

		$data->cCultivation = \planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE);

		$cultivation = GET('cultivation', 'int');

		if($data->cCultivation->offsetExists($cultivation) === FALSE) {
			throw new NotExpectedAction('Cultivation not listed this week');
		}

		$data->eCultivation = $data->cCultivation[$cultivation];

		\planning\HarvestWeekLib::prepareCrate($data->eCultivation['plant']);

		$data->date = planningHarvestWeekOrigin($data, $data->eCultivation) ?? GET('date', fn($value) => (Filter::check('date', $value) and isset($data->days[$value]) and $data->days[$value]['future'] === FALSE));

		if($data->date === NULL) {
			throw new NotExpectedAction('Invalid date');
		}

		$data->mode = GET('mode', ['add', 'replace'], 'add');

		$data->sheet = GET('sheet', 'bool');

		$fw = new FailWatch();

		// Hors de la Semaine, la case d'un jour récolté dans une autre unité que celle de la semaine désigne
		// son intervention (`HarvestWeekLib::prepareTask()`), que le tiroir et la feuille réémettent. Depuis
		// une carte, ils réémettent celle de la carte (`taskWeek`).
		if($data->taskWeek === []) {
			$data->eTaskChosen = \planning\HarvestWeekLib::getTaskChosen($data->eStructure, $data->eCultivation, $data->week, $_GET);
			$data->eTaskCard = $data->eTaskChosen;
		} else {
			$data->eTaskChosen = new \series\Task();
		}

		$eTaskCouple = \planning\HarvestWeekLib::getCoupleProbe($data->eStructure, $data->eCultivation);
		$eTaskCouple->build(['variety', 'harvestSize', 'harvestUnit'], $_GET);

		$fw->validate();

		// L'intervention qu'écrira la saisie, par la règle de l'écriture (`HarvestWeekLib::writeEntry()`) : une
		// autre unité choisie en remplacement rouvre la saisie sur elle (lot GO), dont le tiroir montre alors la
		// quantité et les durées.
		[$data->unitOther, $eTaskEntry] = \planning\HarvestWeekLib::getTaskForChoice($data->eStructure, $data->eCultivation, $data->date, $eTaskCouple, $data->eTaskCard);

		$fw->validate();

		// La liste lit l'unité de la semaine par la règle de la semaine ; depuis une carte, une case qui désigne
		// son intervention ou une autre unité, la saisie annonce celle de l'intervention qu'elle écrira.
		$unitWeek = $data->eCultivation['harvestUnitWeek'];

		if($data->unitOther !== NULL) {
			$data->eCultivation['harvestUnitWeek'] = $data->unitOther;
		} else if($data->eTaskCard->notEmpty()) {
			$data->eCultivation['harvestUnitWeek'] = \planning\HarvestWeekLib::getEntryUnit($data->eCultivation, $eTaskEntry);
		}

		$data->units = \planning\HarvestWeekLib::getEntryUnits($data->eStructure, $data->eCultivation, $data->week, $data->eCultivation['harvestUnitWeek']);

		$data->part = (get_exists('variety') or get_exists('harvestSize')) ?
			\planning\HarvestWeekLib::getPart($data->eCultivation, $eTaskCouple['variety'], $eTaskCouple['harvestSize'], $data->units) :
			[];

		// Les autres unités d'une récolte se choisissent dans la liste du champ, comme dans « Nouvelle récolte »
		// (retour 319), au tiroir comme à la feuille (lot GN), quel que soit le geste d'ouverture : la feuille
		// change de geste sans revenir au serveur. En remplacement, elles ne se gardent que là où leur choix rouvre
		// la saisie sur elles (`$data->reopen`, `PlanningHarvestCrate.allow()`).
		// Depuis une case qui désigne son intervention, l'unité de la case la garde et les autres la remplacent
		// (lot EL). Une carte ou une part écrivent dans une intervention connue.
		$data->tasksByUnit = ($data->taskWeek === [] and $data->part === []) ?
			\planning\HarvestWeekLib::getEntryTasks($data->eStructure, $data->eCultivation, $data->week, $data->eCultivation['harvestUnitWeek']) :
			[];

		$data->reopen = ($data->tasksByUnit !== []);

		// Rouverte, la liste garde l'ordre de l'ouverture, l'unité de la semaine et ses caisses en tête : choisir
		// les caisses rouvre sur cette unité, caisses choisies (`crate`).
		$data->unitFirst = $data->reopen ? $unitWeek : $data->eCultivation['harvestUnitWeek'];
		$data->crate = ($data->reopen and GET('crate', 'bool'));

		$data->cUserTime = \planning\HarvestWeekLib::getTimeUsers($data->eStructure, $data->eCultivation, $data->week, $data->days, $data->mode, $eTaskEntry);

		// Un ajout part de zéro : seul un remplacement montre des durées à rapprocher de la case du jour. La
		// feuille change de geste sans revenir au serveur, elle le reçoit donc dès l'ajout et ne le montre
		// qu'en remplacement.
		if($data->mode === 'replace' or $data->sheet) {
			$data->timeKept = \planning\HarvestWeekLib::getTimeKept($data->eCultivation, $data->part, $data->date, $data->cUserTime);
		} else {
			$data->timeKept = 0.0;
		}

		// Le jour récolté dans plusieurs unités se remplace unité par unité, la tête gardant celle de la ligne ou de
		// la part (HN-B2c). La feuille les reçoit dès l'ajout, comme `timeKept`. Une carte écrit dans son
		// intervention, et une saisie rouverte sur une autre unité ne remplace qu'elle.
		if(
			($data->mode === 'replace' or $data->sheet) and
			$data->taskWeek === [] and
			$data->unitOther === NULL
		) {
			$data->others = \planning\HarvestWeekLib::getOthers($data->eCultivation, $data->part, $data->date, ($data->part === []) ? $data->eCultivation['harvestUnitWeek'] : $data->part['unit']);
		} else {
			$data->others = [];
		}

		// « Supprimer » vise l'intervention désignée ou celle de la semaine (`HarvestWeekLib::deleteEntry()`),
		// jamais une unité seule.
		$data->deletable = (
			$data->mode === 'replace' and
			$data->unitOther === NULL and
			\planning\HarvestWeekLib::hasEntry($data->eCultivation, $data->part, $data->date, $data->cUserTime, $data->eTaskChosen->notEmpty())
		);

		// La récolte du stock est celle qu'écrira la saisie (`HarvestWeekLib::writeEntry()`) : l'intervention
		// désignée, sinon le couple choisi, dans l'unité de la case.
		$data->eTaskStock = \planning\HarvestStockLib::fill(\planning\HarvestStockLib::getReference(
			$data->eTaskCard->empty() ? $eTaskCouple : $data->eTaskCard,
			($data->part === []) ? $data->eCultivation['harvestUnitWeek'] : $data->part['unit']
		));

		// L'espèce se change à la feuille entre les cultures de la série que la semaine liste, seules que
		// `harvestWeek:open` rouvre. Une carte n'écrit que dans la sienne.
		$data->cCultivationSeries = ($data->sheet and $data->taskWeek === []) ?
			$data->cCultivation->find(fn($eCultivation) => $eCultivation['series']['id'] === $data->eCultivation['series']['id']) :
			new \Collection();

		throw new ViewAction($data);

	})
	->get('create', function($data) {

		if($data->canHarvest === FALSE) {
			throw new NotAllowedAction();
		}

		if($data->canCreate === FALSE) {
			throw new NotExpectedAction('No harvest in a future week');
		}

		// L'écran d'où s'ouvre le panneau, que la saisie réémet et qui choisit où elle poste (`HarvestEntryUi::create()`)
		$data->origin = GET('origin', ['menu', 'semaine', 'assolement'], '');

		$data->eCultivation = \series\CultivationLib::getById(GET('cultivation', '?int'), \series\CultivationLib::getHarvestWeekSelection());

		if($data->eCultivation->notEmpty()) {

			$data->eCultivation
				->validate('canWrite')
				->validateProperty('structure', $data->eStructure);

			\planning\HarvestWeekLib::prepareName($data->eStructure, $data->eCultivation, $data->week);
			\planning\HarvestWeekLib::prepareCrate($data->eCultivation['plant']);

		}

		// Depuis une carte de la Semaine, le jour proposé est celui de `HarvestWeekLib::getTaskDate()`, que le
		// champ peut dépasser en arrière comme en avant, jusqu'au jour de « Nouvelle récolte ». L'intervention
		// annoncée est celle que l'écriture préfère.
		$date = planningHarvestWeekOrigin($data, $data->eCultivation);

		$data->eTask = $data->eCultivation->empty() ?
			new \series\Task() :
			\planning\HarvestWeekLib::getTaskWeek($data->eStructure, $data->eCultivation, $data->week, eTaskCard: $data->eTaskCard);

		$data->unit = $data->eCultivation->empty() ?
			NULL :
			\planning\HarvestWeekLib::getEntryUnit($data->eCultivation, $data->eTask);

		$data->units = $data->eCultivation->empty() ?
			[] :
			\planning\HarvestWeekLib::getEntryUnits($data->eStructure, $data->eCultivation, $data->week, $data->unit);

		// Depuis une carte, l'intervention est la sienne, et aucune autre unité ne se propose.
		$data->tasksByUnit = ($data->eCultivation->empty() or $data->taskWeek !== []) ?
			[] :
			\planning\HarvestWeekLib::getEntryTasks($data->eStructure, $data->eCultivation, $data->week, $data->unit);

		$data->eTaskStock = $data->eCultivation->empty() ?
			new \series\Task() :
			\planning\HarvestStockLib::fill(\planning\HarvestStockLib::getReference(
				$data->eTask->empty() ? \planning\HarvestWeekLib::getCoupleProbe($data->eStructure, $data->eCultivation) : $data->eTask,
				$data->unit
			));

		$data->date = $date ?? $data->createDate;
		$data->dateMin = week_date_starts($data->week);
		$data->dateMax = $data->createDate;
		$data->open = array_find_key($data->days, fn($day) => $day['open']);

		throw new ViewAction($data);

	})
	/**
	 * « Ajouter à un stock » d'une saisie de récolte (DG-4), relu quand la variété ou l'unité de la saisie change
	 * (`PlanningHarvestStock`), pour toutes les saisies du nouveau planning : la sonde se bâtit sur la culture ou
	 * l'espèce postée, bornée à la structure (`HarvestStockLib::getProbeForField()`). Rien ne s'écrit.
	 */
	->post('stock', function($data) {

		if($data->canHarvest === FALSE) {
			throw new NotAllowedAction();
		}

		$data->stockId = POST('stockId', fn($value) => (is_string($value) and preg_match('/^[a-z0-9-]+$/', $value) === 1), fn() => throw new NotExpectedAction('Invalid stock field'));
		$unit = \series\Task::POST('harvestUnit', 'harvestUnit', fn() => throw new NotExpectedAction('Invalid unit'));
		$data->titled = POST('titled', 'bool');

		$data->eTaskStock = \planning\HarvestStockLib::fill(\planning\HarvestStockLib::getProbeForField(
			$data->eStructure,
			POST('cultivation', '?int'),
			POST('plant', '?int'),
			POST('variety', '?int'),
			$unit
		));

		throw new ViewAction($data);

	})
	->post('query', function($data) {

		$data->suggested = (POST('query') === '');

		if($data->suggested) {
			$data->cCultivation = \planning\HarvestWeekLib::getSuggested($data->eStructure, $data->week);
		} else {
			$data->cCultivation = \planning\HarvestWeekLib::getForQuery($data->eStructure, $data->week, POST('query'));
		}

		throw new ViewAction($data);

	})
	->get('entry', function($data) {

		// La saisie a eu lieu ailleurs (le champ du menu ou celui d'une case) : la culture ne sert qu'à choisir ce
		// qui se relit.
		$eCultivation = \series\CultivationLib::getById(GET('cultivation', 'int'))
			->validate('canRead')
			->validateProperty('structure', $data->eStructure);

		// Une saisie dans la case d'une culture (`cell`, HN-B) ne touche que sa ligne, pourvu que la culture soit
		// listée cette semaine : sinon, comme une saisie du menu, les espèces voisines se relisent.
		$cCultivationCell = GET('cell', 'bool') ?
			\planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE, cPlant: new \Collection([$eCultivation['plant']['id'] => $eCultivation['plant']])) :
			new \Collection();

		if($cCultivationCell->offsetExists($eCultivation['id'])) {

			$data->change = \planning\HarvestWeekLib::getChangeCell($cCultivationCell[$eCultivation['id']]);
			$data->cCultivation = $cCultivationCell;

		} else {

			$cPlant = \planning\HarvestWeekLib::getPlantsAround($data->eStructure, [$eCultivation['id']]);

			$data->change = \planning\HarvestWeekLib::getChangePlants($data->eStructure, $data->week, $cPlant);

			$data->cCultivation = $data->change['list'] ?
				\planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE) :
				\planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE, cPlant: $cPlant);

		}

		throw new ViewAction($data);

	})
	/**
	 * La fenêtre des ventes à venir, choisie dans l'en-tête de leur colonne (V51) : la préférence qu'écrit aussi
	 * la roue du planning (`taskWeek:doSalesDays`), dont l'écriture se reprend ici, l'accueil compris. La réponse
	 * ne redessine que l'en-tête et la case des ventes de chaque espèce de la semaine, le stock ne dépendant pas
	 * de la fenêtre. Ventes éteintes depuis un autre onglet, la préférence s'écrit sans rien relire, et la
	 * réponse recharge la liste restée dans l'ancienne forme (`PlanningHarvest.align()`).
	 */
	->post('doSalesDays', function($data) {

		if(OTF_DEMO) {
			throw new \FailAction('user\User::demo.write');
		}

		$data->eMember->validate('canSelf');

		$fw = new FailWatch();

		$data->eMember->build(['viewPlanningSalesDays'], $_POST);

		$fw->validate();

		\structure\MemberLib::update($data->eMember, ['viewPlanningSalesDays']);

		\planning\DashboardLib::forgetSales($data->eStructure, \user\ConnectionLib::getOnline());

		$data->soldByPlant = [];

		if($data->sales) {

			// Le constructeur a lu la fenêtre avant l'écriture.
			planningHarvestWeekSalesWindow($data);

			$cCultivation = \planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE);

			// Les séries d'une espèce portent toutes ses ventes (`HarvestWeekLib::prepareSales()`) : la dernière lue
			// vaut pour l'intertitre.
			foreach($cCultivation as $eCultivation) {
				$data->soldByPlant[$eCultivation['plant']['id']] = $eCultivation['sales']['sold'];
			}

		}

		throw new ViewAction($data);

	})
	->post('doHarvest', function($data) {

		// La culture n'est que le parent de l'écriture : elle se valide en droit seulement, comme dans
		// `series/page/task.p.php`. L'intervention et le temps, qui s'écrivent, valident leur paire dans `writeEntry()`.
		$eCultivation = \series\CultivationLib::getById(POST('cultivation', 'int'))
			->validate('canWrite')
			->validateProperty('structure', $data->eStructure);

		// La case ou l'unité de « Nouvelle récolte » qui désigne son intervention (`HarvestWeekLib::getTaskChosen()`).
		$fw = new FailWatch();

		$eTaskChosen = \planning\HarvestWeekLib::getTaskChosen($data->eStructure, $eCultivation, $data->week, $_POST);

		$fw->validate();

		[$cPlant, $cCultivationBefore] = planningHarvestWeekBefore($data, $eCultivation);

		$entry = \planning\HarvestWeekLib::writeEntry($data->eStructure, $eCultivation, $data->days, $_POST, $eTaskChosen, others: TRUE);

		[
			'date' => $date,
			'mode' => $data->mode,
			'eTaskCouple' => $eTaskCouple,
		] = $entry;

		$data->panel = POST('panel', 'bool');
		$data->sheet = POST('sheet', 'bool');
		$data->origin = POST('origin');

		planningHarvestWeekAfter($data, $eCultivation, $cPlant, $cCultivationBefore);

		$data->eCultivationNext = POST('focusNext', 'bool') ?
			\planning\HarvestWeekLib::getNextAround($data->eStructure, $data->week, $data->cCultivation, $eCultivation, $cPlant) :
			new \series\Cultivation();

		$data->written = \planning\HarvestWeekLib::getWritten($cCultivationBefore, $data->cCultivation, $eCultivation, $date, $eTaskCouple);

		throw new ViewAction($data);

	})
	/**
	 * « Supprimer » du tiroir ouvert en remplacement : la quantité du jour dans l'unité du tiroir et le temps
	 * que le tiroir montre ce jour-là. Le lien ne poste que ce qui désigne la case, jamais une quantité ni une
	 * durée, et l'écriture est celle d'un remplacement (`HarvestWeekLib::deleteEntry()`). La réponse est celle
	 * d'une saisie : les lignes que l'écriture change, le tiroir partant avec la sienne.
	 */
	->post('doHarvestDelete', function($data) {

		$eCultivation = \series\CultivationLib::getById(POST('cultivation', 'int'))
			->validate('canWrite')
			->validateProperty('structure', $data->eStructure);

		$fw = new FailWatch();

		$eTaskChosen = \planning\HarvestWeekLib::getTaskChosen($data->eStructure, $eCultivation, $data->week, $_POST);

		$fw->validate();

		[$cPlant, $cCultivationBefore] = planningHarvestWeekBefore($data, $eCultivation);

		\planning\HarvestWeekLib::deleteEntry($data->eStructure, $eCultivation, $data->week, $data->days, $_POST, $eTaskChosen);

		$data->panel = FALSE;
		$data->sheet = FALSE;
		$data->origin = NULL;
		$data->written = [];

		planningHarvestWeekAfter($data, $eCultivation, $cPlant, $cCultivationBefore);

		$data->eCultivationNext = new \series\Cultivation();

		throw new ViewAction($data, ':doHarvest');

	})
	/**
	 * « Récolter » sur les séries cochées (DG-2), le panneau de la Semaine et du Jour du planning
	 * (`planning/taskWeek:batchHarvest`), dans l'unité des lignes, qui ne se choisit pas. Le jour proposé
	 * est celui que l'écran a ouvert, à défaut celui d'une ligne (`proposed`, `HarvestWeekLib::getDays()`).
	 */
	->get('batchHarvest', function($data) {

		[, $cTaskCouple] = planningHarvestWeekBatch($data, GET('ids', 'array'));

		$data->eCultivationChoice = \planning\HarvestWeekLib::getBatchChoices($cTaskCouple);

		[$data->cTaskBatch, $unit] = planningHarvestWeekBatchTasks($data, $cTaskCouple);

		// Le jour ouvert et le jour proposé se lisent dans les jours de l'écran, avant que le panneau ne pose les
		// siens. Une semaine passée peut n'avoir aucun jour ouvert, mais en propose toujours un.
		$open = array_find_key($data->days, fn($day) => $day['open']);
		$proposed = array_find_key($data->days, fn($day) => $day['proposed']);

		[
			'days' => $data->days,
			'distributions' => $data->distributions,
			'distribution' => $data->distribution,
			'unit' => $data->unit,
			'ePlant' => $data->ePlant,
			'tasksByUnit' => $data->tasksByUnit,
			'eTaskStock' => $data->eTaskStock,
		] = \planning\TaskBatchLib::getHarvestPanel($data->cTaskBatch, $data->week, $unit);

		// Les bases se partagent après que le panneau les a posées (`TaskBatchLib::fillTimes()`), avant le rendu qui les lit.
		\planning\HarvestWeekLib::fillBatchBases($data->cTaskBatch);

		$data->date = $proposed;
		$data->cUserTime = \planning\TaskCreateLib::getTimeUsers($data->eStructure, $data->week);

		$data->page = 'harvestWeek';
		$data->post = [
			'open' => $open,
		];

		throw new ViewAction($data, 'planning/taskWeek:batchHarvest');

	})
	/**
	 * Le couple choisi dans « Récolter » aux Récoltes (HM2) : ce qui en dépend se redessine, et la saisie reste dans
	 * le panneau. Le panneau d'avant se calcule sur les couples cochés, celui d'après sur le couple choisi, et la
	 * vue ne remplace la répartition et la quantité que si ce qu'elles offrent a changé. La clé choisie se garde si
	 * elle vaut encore.
	 */
	->get('batchHarvestChoice', function($data) {

		[, $cTaskCouple] = planningHarvestWeekBatch($data, GET('ids', 'array'));

		$eCultivationChoice = \planning\HarvestWeekLib::getBatchChoices($cTaskCouple);

		[$cTaskBefore, $unitBefore] = planningHarvestWeekBatchTasks($data, $cTaskCouple);

		\planning\TaskBatchLib::fillTimes($cTaskBefore, $data->week);
		[$distributionsBefore] = \planning\TaskBatchLib::getHarvestDistributions($cTaskBefore);

		\planning\HarvestWeekLib::applyBatchCouple($cTaskCouple, $eCultivationChoice, $_GET);

		[$data->cTaskBatch, $unit] = planningHarvestWeekBatchTasks($data, $cTaskCouple);

		[
			'distributions' => $data->distributions,
			'distribution' => $data->distribution,
			'unit' => $data->unit,
			'ePlant' => $data->ePlant,
			'tasksByUnit' => $data->tasksByUnit,
			'eTaskStock' => $data->eTaskStock,
		] = \planning\TaskBatchLib::getHarvestPanel($data->cTaskBatch, $data->week, $unit);

		\planning\HarvestWeekLib::fillBatchBases($data->cTaskBatch);

		if(in_array(GET('distribution'), $data->distributions, TRUE)) {
			$data->distribution = GET('distribution');
		}

		$data->distributionChanged = ($data->distributions !== $distributionsBefore);
		// Les Récoltes fixent l'unité (`HarvestWeekLib::getBatchUnit()`), qui n'en offre alors pas d'autre : la
		// liste d'unités du champ ne change qu'avec elle.
		$data->quantityChanged = ($data->unit !== $unitBefore);
		$data->timeShare = $data->eStructure->hasTime();
		// Une seule ligne n'a ni parts ni répartition à l'écran (`TaskBatchUi::getHarvestBlock()`).
		$data->linesShown = ($data->cTaskBatch->count() > 1);

		throw new ViewAction($data);

	})
	/**
	 * La récolte s'ajoute à chaque série le jour choisi (`TaskBatchLib::writeHarvests()`), une série sans
	 * intervention de récolte cette semaine en recevant une, comme la saisie d'une case. La réponse est celle
	 * d'une saisie : les lignes que l'écriture change (`HarvestWeekLib::getChange()`), aucune n'étant forcée.
	 */
	->post('doBatchHarvest', function($data) {

		[, $cTaskCouple, $cPlant, $cCultivationBefore] = planningHarvestWeekBatch($data, POST('ids', 'array'));

		\planning\HarvestWeekLib::applyBatchCouple($cTaskCouple, \planning\HarvestWeekLib::getBatchChoices($cTaskCouple), $_POST);

		[$cTask, $unit] = planningHarvestWeekBatchTasks($data, $cTaskCouple);

		$eUser = \user\ConnectionLib::getOnline();

		if($data->eStructure->hasTime()) {
			new \series\Timesheet([
				'structure' => $data->eStructure,
				'user' => $eUser,
			])->validate('canWrite', 'acceptWrite');
		}

		\planning\TaskBatchLib::fillTimes($cTask, $data->week);
		\planning\HarvestWeekLib::fillBatchBases($cTask);

		$harvest = \planning\TaskBatchLib::prepareHarvests($data->eStructure, $cTask, $data->week, $_POST, $unit);

		\planning\TaskBatchLib::writeHarvests($harvest);

		$data->cCultivation = \planning\HarvestWeekLib::get($data->eStructure, $data->week, $data->salesDays, previous: TRUE, cPlant: $cPlant);
		$data->change = \planning\HarvestWeekLib::getChange($cCultivationBefore, $data->cCultivation, new \series\Cultivation());

		throw new ViewAction($data);

	});
?>
