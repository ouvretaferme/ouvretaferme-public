<?php
namespace planning;

/**
 * La réponse ciblée de l'écran des récoltes : une saisie (`doHarvest`) répond par les seules lignes
 * qu'elle change, et l'écran d'avant, ces instructions appliquées, est exactement l'écran d'un
 * rechargement complet. De même pour l'écran des interventions (L5a, `testTask*`) : la coche, la
 * décoche et la bulle répondent par la carte, et sous « Masquer ce qui est fait » la carte cochée est le
 * seul écart admis. La saisie du temps sur la page d'une intervention (L5b) remplace la même carte, derrière
 * le panneau (`testTaskDetailTime`). La fiche de temps (L6b, `testTime*`) répond par la ligne, la liste, les
 * chiffres et la pastille du jour ; ses zones (CF, `testTimeZone*`) gardent la ligne d'un geste à sa place
 * jusqu'au rechargement, seul écart admis. Les zones « En retard » et « Non planifié » sous la Semaine (L8,
 * `testTaskLate*`, `testTaskUnplanned*`) font partie de l'écran comparé : un geste qui y fait entrer ou
 * sortir une carte, la première ouverture de « Non planifié » et un bloc plafonné s'y comparent aussi. Le lâcher d'une ligne
 * sur « Personnaliser le planning » (L5e, `testTaskOrder`) ne rend rien, et se compare une fois la ligne posée comme le client la pose. La densité et la couleur
 * de la même page (R-Personnalisation, `testTaskDensity`, `testTaskColor`) se comparent après chaque choix, comme les ventes à venir et le stock des récoltes (R-Ventes, `testHarvestSales`). C'est le
 * garde-fou que `CLAUDE.md` exige de toute réponse ciblée, aucune mesure de poids ne voyant une divergence
 * de rendu.
 *
 * Le rendu est celui de la vraie page (`page/harvestWeek.p.php`, vues `harvestWeek`, `open` et
 * `doHarvest`), sur la structure du décor, dans la transaction du test (`HarvestFixtureTest`).
 *
 * Deux preuves précèdent les comparaisons, comme le demande `CLAUDE.md` pour toute comparaison de
 * rendus : le même rendu comparé à lui-même est égal une fois les aléas neutralisés, et la
 * comparaison sait trouver un écart (une récolte ajoutée entre deux rendus change l'empreinte). Le
 * cas « une ligne rendue seule est la ligne de la page » (`testRowAlone`) reste, puisque toutes les
 * réponses passent par `HarvestWeekUi::getRow()`.
 *
 * Deux sessions qui écrivent sur la même structure de la base de dev fausseraient la comparaison,
 * qui oppose deux instants : chaque cas pose ses propres cultures et lit la page dans sa transaction.
 *
 * Lancement : dev/test package=planning file=Fingerprint
 */
class FingerprintTest extends HarvestFixtureTest {

	/**
	 * Les deux preuves : un rendu comparé à lui-même est égal, un rendu changé par une récolte diverge.
	 */
	public function testProofs(): void {

		$eCultivation = $this->createCultivationHarvested();

		[$document] = $this->render();

		$this->assertEquals($this->getFingerprint($document), $this->getFingerprint($this->render()[0]), 'le même rendu, neutralisé, diffère de lui-même');

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 3.0, \Clock::date());

		$this->assertTrue($this->getFingerprint($document) !== $this->getFingerprint($this->render()[0]), 'une récolte ajoutée entre deux rendus ne change pas l\'empreinte');

	}

	/**
	 * Chaque ligne de la page, rendue seule avec les données que la page a préparées, est la même, les ventes
	 * et le stock éteints comme allumés (V47).
	 */
	public function testRowAlone(): void {

		$eCultivation = $this->createCultivationHarvested();

		// Une série sur une planche de plus que l'assolement n'en montre : sa ligne s'arrête sur « … » (retour 103).
		$eSeriesBed = $this->createSeriesBed('R103 '.uniqid());
		$ePlot = $this->createPlot();

		for($position = 0; $position <= TaskCardUi::PLACES; $position++) {
			$this->createPlace($eSeriesBed, $this->createBed($ePlot), 10);
		}

		$eCultivationPlaced = $this->createCultivationHarvested($eSeriesBed);

		foreach([FALSE, TRUE] as $sales) {

			[$document, $data] = $this->render(sales: $sales);

			$this->assertEquals($sales, $data->sales, 'la page ne suit pas la préférence des ventes et du stock');

			$this->assertTrue($data->cCultivation->offsetExists($eCultivation['id']), 'la culture du décor n\'est pas dans la page');
			$this->assertTrue($data->cCultivation->offsetExists($eCultivationPlaced['id']), 'la culture du décor au-delà de la limite de planches n\'est pas dans la page');

			$uiHarvestWeek = new HarvestWeekUi();

			$compared = 0;

			foreach($data->cCultivation as $eCultivationListed) {

				$node = $document->getElementById('planning-harvest-'.$eCultivationListed['id']);

				if($this->assertTrue($node !== NULL, 'la culture '.$eCultivationListed['id'].' n\'a pas de ligne dans la page') === FALSE) {
					continue;
				}

				$alone = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$uiHarvestWeek->getRow($data->eStructure, $data->week, $data->days, $eCultivationListed, $data->canHarvest, $data->quick, $data->canCreate, $data->sales).'</body></html>', LIBXML_NOERROR);

				$expected = $this->neutralize($document->saveHtml($node));
				$actual = $this->neutralize($alone->saveHtml($alone->getElementById('planning-harvest-'.$eCultivationListed['id'])));

				$this->assertEquals($expected, $actual, 'la ligne '.$eCultivationListed['id'].' rendue seule diffère de la page, ventes '.($sales ? 'allumées' : 'éteintes'));

				$compared++;

			}

			$this->assertEquals($data->cCultivation->count(), $compared, 'des lignes de la page n\'ont pas été comparées');

		}

	}

	/**
	 * Le geste courant : la ligne s'ouvre sur aujourd'hui, la quantité s'ajoute, et la réponse ne
	 * remplace que cette ligne, tiroir compris.
	 */
	public function testAddFromDrawer(): void {

		$eCultivation = $this->createCultivationHarvested();

		[$document] = $this->render();

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add'], 'GET');
		$this->apply($document, $instructions);

		$this->assertTrue($document->querySelector('#planning-harvest-'.$eCultivation['id'].' .planning-harvest-drawer') !== NULL, 'la ligne ne s\'ouvre pas');

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '4.5',
			'time' => $this->getTimeInput('0.5'),
			'focusNext' => 1,
		], 'POST');

		$this->assertFalse($data->change['list'], 'une saisie sur une ligne listée rend la liste entière');
		$this->assertEquals([], $data->change['inserted'] + $data->change['removed'], 'une saisie sur une ligne listée entre ou sort des lignes');

		$this->check($document, $instructions, 'ajout depuis le tiroir');

	}

	/**
	 * Retour 410 : une saisie sur une culture qui a une récolte à faire prévue un jour à venir de la semaine. Le
	 * point de ce jour fait partie de la ligne (`HarvestWeekLib::getSignature()`), et la réponse rend l'écran du
	 * rechargement, que la saisie se pose ou non sur l'intervention prévue.
	 */
	public function testAddWithPlannedDay(): void {

		$week = \Clock::week();
		$saturday = week_date_day($week, 6);

		// La seule récolte de la semaine est celle de samedi, à faire : la saisie du jour s'y pose.
		$eCultivation = $this->createCultivation();
		$eCultivation['harvestPeriodExpected'] = \series\Cultivation::WEEK;
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => [$week]]);
		\series\CultivationLib::update($eCultivation, ['harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected']);

		$this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, $week, $saturday);

		[$document] = $this->render();

		if($this->assertDecorPresent($document->querySelectorAll('#planning-harvest-'.$eCultivation['id'].' > .util-bellows-inactive > .util-bellows-band > .util-bellows-mark')->length === 1, 'le point de la récolte prévue samedi dans la ligne') === FALSE) {
			return;
		}

		[, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
		], 'POST');

		$this->check($document, $instructions, 'ajout sur une culture qui a une récolte prévue samedi');

		$this->assertDecorPresent($document->querySelectorAll('#planning-harvest-'.$eCultivation['id'].' > .util-bellows-inactive > .util-bellows-band > .util-bellows-mark')->length === 0, 'la saisie du jour posée sur la récolte prévue samedi, dont le point change');

	}

	/**
	 * « Supprimer » du tiroir ouvert en remplacement (retour 301) : la réponse rend la ligne vidée de son
	 * lundi, sans le tiroir, comme un rechargement. La culture reste à l'écran, prévue cette semaine.
	 */
	public function testDelete(): void {

		$monday = week_date_day(\Clock::week(), 1);

		$eCultivation = $this->createCultivationHarvested();

		[$document] = $this->render(open: $monday);

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $monday, 'open' => $monday, 'mode' => 'replace'], 'GET');
		$this->apply($document, $instructions);

		$this->assertTrue($document->querySelector('#planning-harvest-'.$eCultivation['id'].' .planning-harvest-drawer [data-ajax="/planning/harvestWeek:doHarvestDelete"]') !== NULL, 'le tiroir ne propose pas de supprimer');

		$action = $this->executePage('planning/harvestWeek:doHarvestDelete', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'cultivation' => $eCultivation['id'],
			'date' => $monday,
			'open' => $monday,
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la suppression est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[$data, $template] = $this->runView('doHarvest', $action);

		$this->assertEquals([$eCultivation['id']], $data->change['updated'], 'la suppression ne rend pas la seule ligne vidée');

		$this->check($document, $template->formatInstructions(), 'suppression depuis le tiroir', open: $monday);

	}

	/**
	 * Une saisie en caisses qui règle le poids de l'espèce (L4b) : les lignes se lisent en kilos, et le
	 * poids n'est lu que par la saisie. La réponse ne remplace que la ligne saisie, même quand une autre
	 * culture de la même espèce est à l'écran.
	 */
	public function testCrate(): void {

		$eCultivation = $this->createCultivationHarvested();
		$this->createCultivationHarvested($this->createSeries('Série voisine du décor'));

		[$document] = $this->render();

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add'], 'GET');
		$this->apply($document, $instructions);

		$this->assertTrue($document->querySelector('#planning-harvest-'.$eCultivation['id'].' [data-planning-crate]') !== NULL, 'le tiroir ne propose pas les caisses');

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'crate' => 1,
			'harvestMore' => '3',
			'crateWeight' => '6',
			'focusNext' => 1,
		], 'POST');

		$this->assertEquals([], $data->change['inserted'] + $data->change['removed'], 'une saisie en caisses entre ou sort des lignes');

		$this->check($document, $instructions, 'caisses depuis le tiroir');

	}

	/**
	 * Un autre jour que celui d'arrivée ouvert à l'écran (`MainBellows`, sans aller-retour, que la page
	 * rendue sur `open` reproduit) : la ligne s'ouvre et se redessine dans la colonne ouverte, parce que
	 * chaque requête renvoie ce jour. Sans lui, la ligne répondue reviendrait au jour d'arrivée, et
	 * l'empreinte doit alors différer : c'est la preuve que la comparaison voit la colonne ouverte.
	 */
	public function testAddOnOtherOpenDay(): void {

		$this->setClock(week_date_day(\Clock::week(), 4).' 10:00:00');

		$monday = week_date_day(\Clock::week(), 1);

		$eCultivation = $this->createCultivationHarvested();

		[$document, $data] = $this->render(open: $monday);

		$this->assertTrue($data->days[$monday]['open'], 'la page ne s\'ouvre pas sur le lundi demandé');

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => $monday, 'open' => $monday, 'mode' => 'add'], 'GET');
		$this->apply($document, $instructions);

		$input = [
			'cultivation' => $eCultivation['id'],
			'date' => $monday,
			'mode' => 'add',
			'harvestMore' => '2',
			'focusNext' => 1,
		];

		[, $instructions] = $this->call('doHarvest', $input + ['open' => $monday], 'POST');

		$this->check($document, $instructions, 'ajout sur le lundi ouvert', open: $monday);

		[$document] = $this->render(open: $monday);

		[, $instructions] = $this->call('doHarvest', $input, 'POST');
		$this->apply($document, $instructions);

		$this->assertTrue($this->getFingerprint($this->render(open: $monday)[0]) !== $this->getFingerprint($document), 'une ligne répondue sans le jour ouvert ne change pas l\'empreinte');

	}

	/**
	 * Une semaine passée s'ouvre sans jour ouvert (R-Retours-1210, HD) ; en saisie rapide, sur son lundi. Sans
	 * jour ouvert, la ligne propose le dimanche, comme « Nouvelle récolte », et la ligne saisie
	 * se redessine sans ouvrir de jour, comme au rechargement. L'intertitre garde ses plis, que le planning limité
	 * ouvre aussi par leur tête, et ce planning, qui ne saisit pas, ne s'ouvre sur aucun jour même en saisie rapide.
	 */
	public function testPastWeekWithoutOpenDay(): void {

		$week = $this->getSeason().'-W38';
		$monday = week_date_day($week, 1);
		$sunday = week_date_day($week, 7);

		$eCultivation = $this->createCultivation();
		$this->setExpectedWeeks($eCultivation, [$week]);

		$eTask = $this->createTaskHarvest($eCultivation, $week, $monday);
		$this->createHarvest($eTask, 2.0, $monday);

		[$document, $data] = $this->render(FALSE, $week);

		$this->assertEquals([], array_keys(array_filter($data->days, fn($day) => $day['open'])), 'une semaine passée s\'ouvre sur un jour');
		$this->assertEquals([$sunday], array_keys(array_filter($data->days, fn($day) => $day['proposed'])), 'une semaine passée sans jour ouvert ne propose pas son dimanche');

		$row = $document->getElementById('planning-harvest-'.$eCultivation['id']);

		if($this->assertTrue($row !== NULL, 'la culture de '.$week.' n\'a pas de ligne') === FALSE) {
			return;
		}

		// L'adresse de la ligne est un attribut dont dépend le geste : sans lui, la ligne ne s'ouvre pas.
		$this->assertEquals(HarvestWeekUi::urlOpen($this->eStructure, $week, $eCultivation, $sunday, 'add'), $row->getAttribute('data-planning-harvest-open'), 'la ligne d\'une semaine passée sans jour ouvert ne s\'ouvre pas sur le dimanche');
		$this->assertTrue($document->querySelector('#planning-harvest-plant-'.$eCultivation['plant']['id'].' > .util-bellows-item') !== NULL, 'l\'intertitre d\'une semaine passée sans jour ouvert n\'a pas ses plis');

		[, $instructions] = $this->call('open', ['week' => $week, 'cultivation' => $eCultivation['id'], 'date' => $sunday, 'mode' => 'add'], 'GET');
		$this->apply($document, $instructions);

		// Le jour ouvert que poste le tiroir, tel qu'il est rendu : le dimanche saisi ne doit pas s'y substituer.
		$open = $document->querySelector('#planning-harvest-list .planning-harvest-drawer input[name="open"]');

		if($this->assertTrue($open !== NULL, 'le tiroir d\'une semaine passée ne poste pas de jour ouvert') === FALSE) {
			return;
		}

		$this->assertEquals('', $open->getAttribute('value'), 'le tiroir d\'une semaine passée sans jour ouvert en poste un');

		[, $instructions] = $this->call('doHarvest', [
			'week' => $week,
			'cultivation' => $eCultivation['id'],
			'date' => $sunday,
			'open' => $open->getAttribute('value'),
			'mode' => 'add',
			'harvestMore' => '3',
			'focusNext' => 1,
		], 'POST');

		$this->check($document, $instructions, 'ajout sur une semaine passée sans jour ouvert', week: $week);

		[, $data] = $this->render(TRUE, $week);

		$this->assertEquals([$monday], array_keys(array_filter($data->days, fn($day) => $day['open'])), 'en saisie rapide, une semaine passée ne s\'ouvre pas sur son lundi');

		$eUserOnline = \user\ConnectionLib::getOnline();

		\user\ConnectionLib::setOnline($this->getMemberPlanning());
		\structure\MemberLib::clearCache();

		try {

			[$document, $data] = $this->render(TRUE, $week);

			$this->assertFalse($data->canHarvest, 'le planning limité saisit');
			$this->assertEquals([], array_keys(array_filter($data->days, fn($day) => $day['open'])), 'le planning limité s\'ouvre sur un jour d\'une semaine passée');
			$this->assertTrue($document->querySelector('#planning-harvest-plant-'.$eCultivation['plant']['id'].' > .util-bellows-item') !== NULL, 'l\'intertitre du planning limité n\'a pas ses plis');

		} finally {

			\user\ConnectionLib::setOnline($eUserOnline);
			\structure\MemberLib::clearCache();

		}

	}

	/**
	 * Une culture que la liste ne montre pas, récoltée depuis « Nouvelle récolte » : elle entre dans
	 * la liste à sa place, après la ligne qui la précède.
	 */
	public function testInsertFromPanel(): void {

		// Une liste vide se rend entière (`HarvestWeekLib::getChange()`) : la voisine la fait exister avant l'entrée.
		$this->createCultivationHarvested($this->createSeries('Série témoin de la semaine'));

		$eCultivation = $this->createCultivation($this->createSeries('Série hors semaine'));

		[$document, $data] = $this->render();

		$this->assertFalse($data->cCultivation->offsetExists($eCultivation['id']), 'la culture sans récolte prévue est déjà listée');

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '2',
			'panel' => 1,
		], 'POST');

		$this->assertTrue(array_key_exists($eCultivation['id'], $data->change['inserted']), 'la culture récoltée n\'entre pas dans la liste');

		$this->check($document, $instructions, 'entrée depuis le panneau');

	}

	/**
	 * « Nouvelle récolte » dans une autre unité que celle de la culture (retour 319) : une culture en kilos
	 * déjà récoltée gagne des pièces sur sa ligne, une culture hors de la liste y entre par ses bottes, et
	 * l'écran est chaque fois celui du rechargement. Les puces de la feuille du tactile postent la même
	 * unité, qui rend la même ligne.
	 */
	public function testPanelOtherUnit(): void {

		$eCultivation = $this->createCultivationHarvested($this->createSeries('Série récoltée en kilos'));
		$eCultivationNew = $this->createCultivation($this->createSeries('Série hors semaine'));

		[$document] = $this->render();

		foreach([
			'pièces sur une culture récoltée en kilos' => [$eCultivation, \series\Task::UNIT, 'updated', 'panel'],
			'bottes sur une culture hors de la liste' => [$eCultivationNew, \series\Task::BUNCH, 'inserted', 'panel'],
			'bottes depuis la feuille du tactile' => [$eCultivation, \series\Task::BUNCH, 'updated', 'sheet'],
		] as $case => [$eCultivationWritten, $harvestUnit, $change, $origin]) {

			[$data, $instructions] = $this->call('doHarvest', [
				'cultivation' => $eCultivationWritten['id'],
				'date' => \Clock::date(),
				'mode' => 'add',
				'harvestMore' => '4',
				'crate' => '0',
				'harvestUnit' => $harvestUnit,
				$origin => 1,
			], 'POST');

			if($this->assertTrue($data !== NULL, $case.' : la récolte est refusée') === FALSE) {
				return;
			}

			$changed = match($change) {
				'updated' => in_array($eCultivationWritten['id'], $data->change['updated']),
				'inserted' => array_key_exists($eCultivationWritten['id'], $data->change['inserted']),
			};

			$this->assertTrue($changed, $case.' : la ligne de la culture ne change pas');

			$this->check($document, $instructions, $case);

		}

	}

	/**
	 * L'échelle de la frise ne dépend que de la semaine affichée : une culture entrée dont la période prévue
	 * sort de l'échelle ne fait ressortir aucune ligne voisine, et l'écran est celui du rechargement.
	 */
	public function testInsertKeepsFrieze(): void {

		$week = \Clock::week();
		$weekFar = toWeek(week_date_starts($week).' - '.(\planning\PlanningSetting::FRIEZE_WEEKS + 10).' weeks');

		$eCultivation = $this->createCultivation($this->createSeries('Série de la semaine'));
		$this->setExpectedWeeks($eCultivation, [$week]);

		$eCultivationFar = $this->createCultivation($this->createSeries('Série hors de la frise'));
		$this->setExpectedWeeks($eCultivationFar, [$weekFar]);

		[$document, $data] = $this->render();

		$this->assertFalse($data->cCultivation->offsetExists($eCultivationFar['id']), 'la culture prévue en '.$weekFar.' est déjà listée');

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivationFar['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '2',
			'panel' => 1,
		], 'POST');

		$this->assertTrue(array_key_exists($eCultivationFar['id'], $data->change['inserted']), 'la culture récoltée n\'entre pas dans la liste');
		$this->assertFalse(in_array($eCultivation['id'], $data->change['updated']), 'une entrée hors de la frise fait ressortir une ligne voisine');

		$this->check($document, $instructions, 'entrée hors de la frise');

	}

	/**
	 * La récolte prévue à la semaine, posée par le `build()` de l'écran de la culture.
	 */
	private function setExpectedWeeks(\series\Cultivation $eCultivation, array $weeks): void {

		$eCultivation['harvestPeriodExpected'] = \series\Cultivation::WEEK;
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => $weeks]);

		\series\CultivationLib::update($eCultivation, ['harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected']);

	}

	/**
	 * La saisie rapide : une case changée remplace la quantité du jour, et la réponse ne remplace que la ligne.
	 */
	public function testReplaceQuick(): void {

		$eCultivation = $this->createCultivationHarvested();

		[$document] = $this->render(TRUE);

		$this->assertTrue($document->querySelector('#planning-harvest-'.$eCultivation['id'].' .planning-harvest-cell') !== NULL, 'la saisie rapide n\'a pas de case sur la ligne');

		[, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => week_date_day(\Clock::week(), 1),
			'mode' => 'replace',
			'harvestDay' => '7',
		], 'POST');

		$this->check($document, $instructions, 'remplacement en saisie rapide', quick: TRUE);

	}

	/**
	 * « Récolter » sur les séries cochées (DG-2), avec et sans la saisie rapide : une série déjà récoltée et une série
	 * prévue sans récolte de la semaine, qui reçoit une intervention. La réponse ne redessine que les lignes qui
	 * changent, cases comprises, comme un rechargement.
	 */
	public function testBatchHarvest(): void {

		foreach(['sans la saisie rapide' => FALSE, 'en saisie rapide' => TRUE] as $case => $quick) {

			$ePlant = $this->createPlant();

			$eCultivationHarvested = $this->createCultivation($this->createSeries('DG-2 récoltée '.$case), $ePlant);
			$this->setExpectedWeeks($eCultivationHarvested, [\Clock::week()]);

			$eTask = $this->createTaskHarvest($eCultivationHarvested, \Clock::week(), week_date_day(\Clock::week(), 1));
			$this->createHarvest($eTask, 2.0, week_date_day(\Clock::week(), 1));

			$eCultivationNew = $this->createCultivation($this->createSeries('DG-2 neuve '.$case), $ePlant);
			$this->setExpectedWeeks($eCultivationNew, [\Clock::week()]);

			[$document] = $this->render($quick);

			$this->assertTrue($document->querySelector('#planning-harvest-'.$eCultivationNew['id'].' .planning-harvest-select input[value="'.$this->getBatchValue($eCultivationNew).'"]') !== NULL, $case.', la série sans récolte n\'a pas sa case');

			[$data, $instructions] = $this->call('doBatchHarvest', [
				'ids' => [$this->getBatchValue($eCultivationHarvested), $this->getBatchValue($eCultivationNew)],
				'date' => \Clock::date(),
				'harvestMore' => '6',
				'distribution' => 'fair',
			], 'POST');

			if($this->assertTrue($data !== NULL, $case.', « Récolter » est refusé') === FALSE) {
				continue;
			}

			$this->assertFalse($data->change['list'], $case.', « Récolter » rend la liste entière');

			$this->check($document, $instructions, '« Récolter » sur une série neuve et une série récoltée ('.$case.')', quick: $quick);

		}

	}

	/**
	 * « Récolter » ne relit que les espèces qu'il peut changer (EX-2, `HarvestWeekLib::getPlantsAround()` sur les
	 * séries cochées) : deux séries d'une espèce de trois, avec et sans la saisie rapide. La liste relue ne porte pas
	 * l'espèce voisine, et la réponse ne redessine que les deux lignes cochées.
	 */
	public function testBatchAroundSpecies(): void {

		foreach(['sans la saisie rapide' => FALSE, 'en saisie rapide' => TRUE] as $case => $quick) {

			$ePlant = $this->createPlant();

			$eCultivationFirst = $this->createCultivationHarvested($this->createSeries('A, lot '.$case), $ePlant);
			$eCultivationSecond = $this->createCultivationHarvested($this->createSeries('B, lot '.$case), $ePlant);
			$this->createCultivationHarvested($this->createSeries('C, hors du lot '.$case), $ePlant);

			$eCultivationOther = $this->createCultivationHarvested($this->createSeries('Série d\'une autre espèce '.$case), $this->createPlant());

			[$document] = $this->render($quick);

			$data = $this->callBatchHarvest([$eCultivationFirst, $eCultivationSecond], $instructions);

			if($data === NULL) {
				continue;
			}

			$this->assertFalse($data->cCultivation->offsetExists($eCultivationOther['id']), $case.', « Récolter » relit une espèce qu\'il ne change pas');
			$this->assertEquals([$eCultivationFirst['id'], $eCultivationSecond['id']], $data->change['updated'], $case.', « Récolter » redessine une autre ligne que les séries cochées');

			$this->check($document, $instructions, '« Récolter » sur deux séries d\'une espèce de trois ('.$case.')', quick: $quick);

		}

	}

	/**
	 * « Récolter » sur deux séries d'une espèce dont l'une a une homonyme d'une autre espèce : la liste relue
	 * porte l'autre espèce, dont la clé de nom rencontre celle de la série cochée, et l'écran est celui du
	 * rechargement.
	 */
	public function testBatchAroundHomonym(): void {

		[$ePlant, $eCultivationHomonym] = $this->createHomonyms($this->createSeries('Série homonyme du lot'));

		$eCultivationSecond = $this->createCultivationHarvested($this->createSeries('Seconde série du lot'), $ePlant);

		[$document, $dataBefore] = $this->render();

		$eCultivationFirst = $dataBefore->cCultivation->find(fn($eCultivation) => $eCultivation['plant']['id'] === $ePlant['id'] and $eCultivation['id'] !== $eCultivationSecond['id'])->first();

		if($this->assertDecorPresent($eCultivationFirst !== NULL, 'la série homonyme de l\'espèce cochée est listée') === FALSE) {
			return;
		}

		$data = $this->callBatchHarvest([$eCultivationFirst, $eCultivationSecond], $instructions);

		if($data === NULL) {
			return;
		}

		$this->assertTrue($data->cCultivation->offsetExists($eCultivationHomonym['id']), '« Récolter » ne relit pas l\'espèce de la série homonyme');

		$this->check($document, $instructions, '« Récolter » sur une série qui a une homonyme d\'une autre espèce');

	}

	/**
	 * « Récolter » sur une série d'une saison éloignée, listée parce qu'elle est récoltée cette semaine, et une
	 * série de la saison de la même espèce.
	 */
	public function testBatchAroundOtherSeason(): void {

		$ePlant = $this->createPlant();

		$eCultivationOld = $this->createCultivation($this->createSeries('Série d\'une saison éloignée', $this->getSeason() - 3), $ePlant);
		$eTask = $this->createTaskHarvest($eCultivationOld, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 1.0, \Clock::date());

		$eCultivation = $this->createCultivationHarvested($this->createSeries('Série de la saison'), $ePlant);

		[$document, $dataBefore] = $this->render();

		if($this->assertDecorPresent($dataBefore->cCultivation->offsetExists($eCultivationOld['id']), 'la série d\'une saison éloignée récoltée cette semaine est listée') === FALSE) {
			return;
		}

		$data = $this->callBatchHarvest([$eCultivationOld, $eCultivation], $instructions);

		if($data === NULL) {
			return;
		}

		$this->check($document, $instructions, '« Récolter » sur une série d\'une saison éloignée');

	}

	/**
	 * « Récolter » sur la ligne d'une variété (FD) et sur une autre série de l'espèce, avec et sans la saisie rapide : la
	 * réponse redessine la série ventilée, ses parts comprises, comme un rechargement.
	 */
	public function testBatchPart(): void {

		[$eCultivation, $eVariety] = $this->createCultivationVentilated();
		$eCultivationOther = $this->createCultivationHarvested($this->createSeries('Série entière du lot'), $eCultivation['plant']);

		foreach(['sans la saisie rapide' => FALSE, 'en saisie rapide' => TRUE] as $case => $quick) {

			[$document] = $this->render($quick);

			[$data, $instructions] = $this->call('doBatchHarvest', [
				'ids' => [$this->getBatchValue($eCultivation, $eVariety), $this->getBatchValue($eCultivationOther)],
				'date' => \Clock::date(),
				'harvestMore' => '3',
				'distribution' => 'fair',
			], 'POST');

			if($this->assertTrue($data !== NULL, $case.', « Récolter » sur une variété est refusé') === FALSE) {
				continue;
			}

			$this->assertEquals([$eCultivation['id'], $eCultivationOther['id']], $data->change['updated'], $case.', « Récolter » sur une variété redessine une autre ligne que les séries cochées');

			$this->check($document, $instructions, '« Récolter » sur une variété et une série entière ('.$case.')', quick: $quick);

		}

	}

	/**
	 * La valeur de la case d'une ligne, telle que l'écran la poste.
	 */
	private function getBatchValue(\series\Cultivation $eCultivation, \plant\Variety $eVariety = new \plant\Variety()): string {
		return HarvestWeekLib::getBatchValue($eCultivation, $eVariety, new \plant\Size());
	}

	/**
	 * « Récolter » sur les séries, 3 kg répartis à parts égales aujourd'hui.
	 *
	 * @param \series\Cultivation[] $cultivations
	 */
	private function callBatchHarvest(array $cultivations, ?array &$instructions): ?\stdClass {

		[$data, $instructions] = $this->call('doBatchHarvest', [
			'ids' => array_map(fn($eCultivation) => $this->getBatchValue($eCultivation), $cultivations),
			'date' => \Clock::date(),
			'harvestMore' => '3',
			'distribution' => 'fair',
		], 'POST');

		if($this->assertTrue($data !== NULL, '« Récolter » est refusé') === FALSE) {
			return NULL;
		}

		return $data;

	}

	/**
	 * La saisie rapide encore : le temps écrit par le tiroir qu'ouvre l'horloge d'une case redessine la ligne comme
	 * un rechargement.
	 */
	public function testTimeQuick(): void {

		$eCultivation = $this->createCultivationHarvested();

		// Le lundi saisi est le jour ouvert, où la saisie rapide pose ses champs.
		[$document] = $this->render(TRUE, open: week_date_day(\Clock::week(), 1));

		[, $instructions] = $this->call('doHarvest', [
			'open' => week_date_day(\Clock::week(), 1),
			'cultivation' => $eCultivation['id'],
			'date' => week_date_day(\Clock::week(), 1),
			'mode' => 'replace',
			'harvestDay' => '7',
			'time' => $this->getTimeInput('0.75'),
		], 'POST');

		$this->check($document, $instructions, 'temps écrit en saisie rapide', quick: TRUE, open: week_date_day(\Clock::week(), 1));

	}

	/**
	 * Une culture hors de sa période, listée par sa seule récolte de la semaine : sa récolte remise à
	 * zéro l'y laisse ou l'en sort, et l'écran d'après est celui du rechargement dans les deux cas.
	 */
	public function testReplaceToZero(): void {

		$eCultivation = $this->createCultivation($this->createSeries('Série récoltée hors période'));

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		[$document, $data] = $this->render();

		$this->assertTrue($data->cCultivation->offsetExists($eCultivation['id']), 'la culture récoltée cette semaine n\'est pas listée');

		[, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'replace',
			'harvestDay' => '0',
		], 'POST');

		$this->check($document, $instructions, 'remise à zéro');

	}

	/**
	 * Une part (L3c) : le « + » d'un couple ouvre le tiroir sous sa part, l'ajout ne remplace que la ligne
	 * de sa culture, parts comprises, et l'écran est celui du rechargement, y compris quand un couple
	 * commence (le calibre).
	 */
	public function testPartAdd(): void {

		[$eCultivation, $eVariety] = $this->createCultivationVentilated();

		[$document, $data] = $this->render();

		$key = \series\CultivationLib::getHarvestPartKey($eVariety, new \plant\Size());

		$this->assertTrue($document->getElementById('planning-harvest-'.$eCultivation['id'].'-'.$key) !== NULL, 'la ligne ventilée n\'a pas la part de sa variété');
		$this->assertEquals(2, count($data->cCultivation[$eCultivation['id']]['harvestedParts']), 'la ligne ventilée n\'a pas ses deux parts');

		[, $instructions] = $this->call('open', ['cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'variety' => $eVariety['id'], 'harvestSize' => ''], 'GET');
		$this->apply($document, $instructions);

		$this->assertTrue($document->querySelector('#planning-harvest-'.$eCultivation['id'].'-'.$key.' + .planning-harvest-drawer') !== NULL, 'le tiroir de la part ne s\'ouvre pas sous elle');
		$this->assertTrue($document->querySelector('#planning-harvest-'.$eCultivation['id'].' .planning-harvest-drawer input[name="variety"][value="'.$eVariety['id'].'"]') !== NULL, 'le tiroir de la part ne poste pas son couple');

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '2',
			'variety' => $eVariety['id'],
			'harvestSize' => '',
			'focusNext' => 1,
		], 'POST');

		$this->assertEquals([$eCultivation['id']], $data->change['updated'], 'l\'ajout à une part ne remplace pas la seule ligne de sa culture');

		$this->check($document, $instructions, 'ajout à une part');

		$eSize = $this->createSize($eCultivation['plant']);

		[, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '1',
			'variety' => '',
			'harvestSize' => $eSize['id'],
		], 'POST');

		$this->check($document, $instructions, 'ajout d\'une part');

	}

	/**
	 * Le remplacement d'une part, depuis sa quantité puis depuis sa case en saisie rapide, où la culture
	 * ventilée n'a plus de case en tête.
	 */
	public function testPartReplace(): void {

		[$eCultivation, $eVariety] = $this->createCultivationVentilated();

		[$document] = $this->render();

		[, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'replace',
			'harvestDay' => '5',
			'variety' => $eVariety['id'],
			'harvestSize' => '',
		], 'POST');

		$this->check($document, $instructions, 'remplacement d\'une part');

		[$document] = $this->render(TRUE);

		$this->assertTrue($document->querySelector('#planning-harvest-'.$eCultivation['id'].' > .planning-harvest-row-sub .planning-harvest-cell') !== NULL, 'les parts n\'ont pas de case en saisie rapide');

		[, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'replace',
			'harvestDay' => '7',
			'variety' => '',
			'harvestSize' => '',
		], 'POST');

		$this->check($document, $instructions, 'remplacement d\'une part en saisie rapide', quick: TRUE);

	}

	/**
	 * L'écran des interventions (L5a), les deux preuves : un rendu comparé à lui-même est égal, un rendu
	 * changé par une intervention ajoutée diverge.
	 */
	public function testTaskProofs(): void {

		[$document] = $this->renderTasks();

		$this->assertEquals($this->getFingerprint($document), $this->getFingerprint($this->renderTasks()[0]), 'le même rendu des interventions, neutralisé, diffère de lui-même');

		$this->createTaskWeek();

		$this->assertTrue($this->getFingerprint($document) !== $this->getFingerprint($this->renderTasks()[0]), 'une intervention ajoutée entre deux rendus ne change pas l\'empreinte');

	}

	/**
	 * La coche : la carte se remplace sur place, avec le compte de son bloc, et la bulle se pose après
	 * elle. La bulle est le geste en cours et non l'état de l'écran, qu'un rechargement ne rouvre pas :
	 * elle se retire avant la comparaison, qui reste stricte.
	 */
	public function testTaskDone(): void {

		$eTask = $this->createTaskWeek(timeExpected: 1.0);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callTask('doDone', $eTask);

		$this->assertEquals('card', $data->change, 'la coche d\'une carte de la semaine ne la remplace pas sur place');

		$this->checkTasks($document, $instructions, 'coche');

	}

	/**
	 * La décoche : la carte redevient à faire sur place.
	 */
	public function testTaskTodo(): void {

		$eTask = $this->createTaskWeek();
		$this->callTask('doDone', $eTask);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callTask('doTodo', $eTask);

		$this->assertEquals('card', $data->change, 'la décoche d\'une carte de la semaine ne la remplace pas sur place');

		$this->checkTasks($document, $instructions, 'décoche');

	}

	/**
	 * La bulle : la carte suit le total tapé.
	 */
	public function testTaskTime(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour la bulle') === FALSE) {
			return;
		}

		$eTask = $this->createTaskWeek(timeExpected: 1.0);

		[$document] = $this->renderTasks();

		[, $instructions] = $this->callTask('doDone', $eTask);
		$this->apply($document, $instructions);

		[$data, $instructions, $action] = $this->callTask('doTime', $eTask, ['time' => '2.5']);

		if($this->assertTrue($action instanceof \ViewAction, 'le temps de la bulle est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->checkTasks($document, $instructions, 'bulle');

	}

	/**
	 * En Colonnes, une carte cochée depuis « À faire » y reste, faite, et sa copie entre dans « Fait »
	 * (R-Retours-1210 V40) : la coche, une seconde coche, le temps de la bulle sur la première, puis sa décoche
	 * depuis sa copie. Chaque réponse ciblée, appliquée, vaut l'écran rendu entier avec les mêmes cartes
	 * gardées (`TaskWeekLib::getColumns()`), que seul un geste réémet : un rechargement ne les garde pas.
	 */
	public function testTaskColumnsKept(): void {

		$this->createTaskWitness();

		$eTaskFirst = $this->createTaskWeek(timeExpected: 1.0);
		$eTaskSecond = $this->createTaskWeek();
		// Une carte reste à faire : sans elle, la dernière coche viderait « À faire » de ses cartes à faire.
		$this->createTaskWeek();

		[$document] = $this->renderTasksColumns();

		$input = ['view' => \structure\Member::COLUMNS];

		[, $instructions] = $this->callTask('doDone', $eTaskFirst, $input + ['kept' => [$eTaskFirst['id'] => 1]]);
		$this->checkTasksColumns($document, $instructions, 'première coche', [$eTaskFirst['id']]);

		$kept = [$eTaskFirst['id'] => 1, $eTaskSecond['id'] => 1];

		[, $instructions] = $this->callTask('doDone', $eTaskSecond, $input + ['kept' => $kept]);
		$this->checkTasksColumns($document, $instructions, 'seconde coche', array_keys($kept));

		if($this->assertDecorPresent($this->eStructure->hasTime(), 'la structure saisit le temps de travail') === FALSE) {
			return;
		}

		[, $instructions] = $this->callTask('doTime', $eTaskFirst, $input + ['kept' => $kept, 'time' => '2.5']);
		$this->checkTasksColumns($document, $instructions, 'temps de la carte gardée', array_keys($kept));

		[, $instructions] = $this->callTask('doTodo', $eTaskFirst, $input + ['kept' => $kept]);
		$this->checkTasksColumns($document, $instructions, 'décoche de la copie', [$eTaskSecond['id']]);

	}

	/**
	 * Une récolte saisie depuis une carte (retour 203, `taskWeek:doHarvest`) : la carte terminée se remplace
	 * sur place avec le compte de son bloc ; une récolte qui crée son intervention la fait entrer dans son
	 * bloc. Le panneau se ferme, et l'écran est celui d'un rechargement.
	 */
	public function testTaskHarvest(): void {

		$this->createTaskWitness();

		$eCultivation = $this->createCultivation($this->createSeries('Série '.uniqid()));
		$eTask = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doHarvest', 'doTask', ['task' => $eTask['id'], 'cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'harvestMore' => '3', 'done' => '1', 'panel' => 1]);

		$this->assertEquals('card', $data?->change, 'la récolte d\'une carte ne la remplace pas sur place');
		$this->checkTasks($document, $instructions, 'récolte depuis une carte');

		// Une carte d'une variété, saisie sans variété : la semaine n'a pas de récolte de ce couple.
		$eCultivationNew = $this->createCultivation($this->createSeries('Série '.uniqid()));
		$eTaskVariety = $this->createTask($eCultivationNew, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$this->ventilate($eTaskVariety, ['variety' => $this->createVariety($eCultivationNew['plant'])]);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doHarvest', 'doTask', ['task' => $eTaskVariety['id'], 'cultivation' => $eCultivationNew['id'], 'date' => \Clock::date(), 'mode' => 'add', 'harvestMore' => '2', 'panel' => 1]);

		$this->assertTrue(in_array($data?->change, ['block', 'board'], TRUE), 'l\'intervention que la récolte crée n\'entre pas dans son bloc');
		$this->checkTasks($document, $instructions, 'récolte qui crée son intervention');

	}

	/**
	 * Les commentaires sous la carte (CD), dans la Semaine et dans le Jour : la carte commentée porte sa liste,
	 * échappée et sans balisage. Les gestes qui redessinent la carte, derrière le panneau de l'intervention
	 * et au Jour, rendent ce que rend un rechargement.
	 */
	public function testCardComment(): void {

		$eTask = $this->createTaskWeek();

		$this->createComments($eTask, ['Arroser <b>avant</b>', 'Bâche posée']);

		[$document] = $this->renderTasks();

		$comments = $document->querySelector('#planning-task-'.$eTask['id'].' .planning-task-comments');

		if($this->assertTrue($comments !== NULL, 'la carte commentée ne porte pas ses commentaires') === FALSE) {
			return;
		}

		$this->assertTrue(str_contains($comments->textContent, 'Arroser <b>avant</b>'), 'le premier commentaire n\'est pas sur la carte, ou pas échappé');
		$this->assertTrue($comments->querySelector('b, a') === NULL, 'un commentaire de la carte porte du balisage ou un lien');

		$action = $this->executePage('planning/taskDetail:doUser', [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'weekShown' => \Clock::week(),
			'user' => $this->getMember()['id'],
			'action' => 'add',
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'l\'affectation de la page d\'une intervention est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('doUser', $action, 'taskDetail');

		$this->checkTasks($document, $template->formatInstructions(), 'carte commentée derrière le panneau de l\'intervention');

		$tuesday = week_date_day(\Clock::week(), 2);

		[$document] = $this->renderTaskDay();

		[, $instructions] = $this->callDay('doPlanned', ['task' => $eTask['id'], 'planned' => ['date' => $tuesday]]);
		$this->checkDay($document, $instructions, 'jour posé sur une carte commentée');

		[, $instructions] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => $tuesday]);
		$this->checkDay($document, $instructions, 'coche d\'une carte commentée');

	}

	/**
	 * La page d'une intervention (L5b), ouverte depuis une carte : une saisie de temps dans son bloc remplace
	 * la carte restée derrière le panneau, qui est alors celle d'un rechargement de la semaine. Le total du
	 * bloc, que la même réponse remplace, n'est pas dans cet écran et ne s'y applique pas.
	 */
	public function testTaskDetailTime(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour le bloc') === FALSE) {
			return;
		}

		$eTask = $this->createTaskWeek(timeExpected: 1.0);

		[$document] = $this->renderTasks();

		$action = $this->executePage('planning/taskDetail:doTime', [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'user' => $this->getMember()['id'],
			'time' => '1.75',
			'date' => \Clock::date(),
			'weekShown' => \Clock::week(),
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie de la page d\'une intervention est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('doTime', $action, 'taskDetail');

		$this->checkTasks($document, $template->formatInstructions(), 'saisie depuis la page d\'une intervention');

	}

	/**
	 * Le tableau de l'équipe de la page d'une intervention (lot CT) : une saisie qui ne change pas qui a sa
	 * ligne redessine la case écrite, la somme de la personne et le pied (`user`), et la page, la réponse
	 * appliquée, est celle d'un rechargement sur la même semaine.
	 */
	public function testTaskDetailBlockUser(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour le bloc') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), week_date_day(\Clock::week(), 1));
		$this->createTimesheet($eTask, 2.0, week_date_day(\Clock::week(), 1));

		$document = $this->renderTaskDetail($eTask);

		[$data, $instructions] = $this->callTaskDetail($eTask, $this->getMember(), '0.5', \Clock::date());

		$this->assertEquals('user', $data->change, 'une saisie qui ne change pas qui a sa ligne redessine plus que sa case');

		$this->checkTaskDetail($document, $instructions, $eTask, 'saisie d\'aujourd\'hui', \Clock::week());

	}

	/**
	 * Bruno, au planning limité, saisit son temps sur une intervention où un autre membre a le sien : pour lui,
	 * cette ligne est en lecture seule, avec sa valeur le jour où elle porte du temps et vide un autre jour. Sa
	 * saisie ne touche jamais cette ligne, d'où une réponse limitée à sa case, le jour où l'autre ligne a une
	 * valeur comme celui où elle n'en a pas.
	 */
	public function testTaskDetailBlockReadOnly(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour le bloc') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), week_date_day(\Clock::week(), 1));
		$this->createTimesheet($eTask, 1.0, week_date_day(\Clock::week(), 1));
		// Bruno a déjà sa ligne : sans temps cette semaine, sa première saisie redessinerait le tableau.
		$this->createTimesheetFor($eTask, $this->getMemberPlanning(), 0.25, week_date_day(\Clock::week(), 3));

		\user\ConnectionLib::setOnline($this->getMemberPlanning());

		foreach([
			'jour où la ligne en lecture seule a une valeur' => week_date_day(\Clock::week(), 1),
			'jour où elle n\'en a pas' => week_date_day(\Clock::week(), 2),
		] as $case => $date) {

			$document = $this->renderTaskDetail($eTask);

			$this->assertTrue($document->getElementById('planning-task-detail-user-'.$this->getMember()['id']) !== NULL, $case.' : la ligne en lecture seule n\'est pas là');

			[$data, $instructions] = $this->callTaskDetail($eTask, $this->getMemberPlanning(), '0.5', $date);

			$this->assertEquals('user', $data->change, $case.' : la saisie de Bruno redessine plus que sa case');

			$this->checkTaskDetail($document, $instructions, $eTask, $case, \Clock::week());

		}

	}

	/**
	 * Le premier temps d'une autre personne lui donne sa ligne : la réponse redessine le tableau (`grid`), dont
	 * « Ajouter une personne » qui la perd. Seul qui gère la structure écrit le temps d'une autre personne.
	 */
	public function testTaskDetailBlockFirst(): void {

		$eUserOther = $this->getMemberOther();

		if($this->assertDecorPresent($this->eStructure->hasTime() and $eUserOther->notEmpty() and \structure\StructureLib::getById($this->eStructure['id'])->canManage(), 'la structure '.static::STRUCTURE.' suit le temps, a un second membre et le membre du décor la gère') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), week_date_day(\Clock::week(), 3));

		$document = $this->renderTaskDetail($eTask);

		[$data, $instructions] = $this->callTaskDetail($eTask, $eUserOther, '0.75', week_date_day(\Clock::week(), 1));

		$this->assertEquals('grid', $data->change, 'le premier temps d\'une autre personne ne redessine pas le tableau');

		$this->checkTaskDetail($document, $instructions, $eTask, 'premier temps d\'une autre personne', \Clock::week());

	}

	/**
	 * L'inverse : une autre personne ramenée à zéro perd sa ligne au rechargement, et la réponse redessine le
	 * tableau (`grid`) au lieu de sa seule case, dont la ligne resterait affichée à vide.
	 */
	public function testTaskDetailBlockCleared(): void {

		$eUserOther = $this->getMemberOther();

		if($this->assertDecorPresent($this->eStructure->hasTime() and $eUserOther->notEmpty() and \structure\StructureLib::getById($this->eStructure['id'])->canManage(), 'la structure '.static::STRUCTURE.' suit le temps, a un second membre et le membre du décor la gère') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), week_date_day(\Clock::week(), 3));
		$this->createTimesheetFor($eTask, $eUserOther, 0.75, week_date_day(\Clock::week(), 3));

		$document = $this->renderTaskDetail($eTask);

		$this->assertTrue($document->getElementById('planning-task-detail-user-'.$eUserOther['id']) !== NULL, 'l\'autre personne, qui a du temps, n\'a pas sa ligne avant le geste');

		[$data, $instructions] = $this->callTaskDetail($eTask, $eUserOther, '0', week_date_day(\Clock::week(), 3));

		$this->assertEquals('grid', $data->change, 'une autre personne ramenée à zéro ne redessine pas le tableau');

		$this->checkTaskDetail($document, $instructions, $eTask, 'autre personne ramenée à zéro', \Clock::week());

	}

	/**
	 * Une saisie dans une semaine passée, que la page montre après un changement de semaine : la réponse
	 * appliquée est la page rechargée sur cette semaine, et non sur celle d'ouverture.
	 */
	public function testTaskDetailBlockWeekBefore(): void {

		if($this->assertDecorPresent($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour le bloc') === FALSE) {
			return;
		}

		$weekBefore = toWeek(\Clock::date().' - 7 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), week_date_day(\Clock::week(), 1));
		$this->createTimesheet($eTask, 2.0, week_date_day(\Clock::week(), 1));
		$this->createTimesheet($eTask, 1.0, week_date_day($weekBefore, 5));

		$document = $this->renderTaskDetail($eTask, $weekBefore);

		[$data, $instructions] = $this->callTaskDetail($eTask, $this->getMember(), '0.5', week_date_day($weekBefore, 7));

		$this->assertEquals('user', $data->change, 'une saisie dans la semaine montrée redessine plus que sa case');

		$this->checkTaskDetail($document, $instructions, $eTask, 'saisie dans une semaine passée', $weekBefore);

	}

	/**
	 * L'affectation depuis le nom (`taskDetail:doUser`) : elle redessine le nom tant que les lignes ne bougent
	 * pas, le tableau quand une personne gagne ou perd sa ligne, et la page, la réponse appliquée, est celle
	 * d'un rechargement.
	 */
	public function testTaskDetailAssign(): void {

		$eUserOther = $this->getMemberOther();

		if($this->assertDecorPresent($this->eStructure->hasTime() and $eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' suit le temps et a un second membre') === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTimesheet($eTask, 2.0, week_date_day(\Clock::week(), 1));

		$document = $this->renderTaskDetail($eTask);

		foreach([
			'affecter qui a sa ligne' => [$this->getMember(), 'add', 'user'],
			'affecter qui n\'a pas de ligne' => [$eUserOther, 'add', 'grid'],
			'retirer qui n\'a pas de temps' => [$eUserOther, 'delete', 'grid'],
			'retirer qui a du temps' => [$this->getMember(), 'delete', 'user'],
		] as $case => [$eUser, $assign, $change]) {

			$action = $this->executePage('planning/taskDetail:doUser', [
				'id' => $this->eStructure['id'],
				'task' => $eTask['id'],
				'week' => \Clock::week(),
				'weekShown' => \Clock::week(),
				'user' => $eUser['id'],
				'action' => $assign,
			], 'POST');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : l\'affectation est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				return;
			}

			[$data, $template] = $this->runView('doUser', $action, 'taskDetail');

			$this->assertEquals($change, $data->change, $case.' : ce que la réponse redessine');

			$this->checkTaskDetail($document, $template->formatInstructions(), $eTask, $case, \Clock::week());

		}

	}

	/**
	 * Une récolte : sans temps, le total du temps est posé vide, et après le premier temps la saisie entière
	 * (temps et récolte), la réponse appliquée, est celle d'un rechargement.
	 */
	public function testTaskDetailBlockHarvest(): void {

		if($this->assertDecorPresent($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour le bloc') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$this->writeHarvest($eTask, \Clock::date(), 4.0);

		$document = $this->renderTaskDetail($eTask);

		$this->assertEquals(0, $document->getElementById('planning-task-detail-total')?->childNodes->length, 'sans temps, le total du temps n\'est pas posé vide');

		[, $instructions] = $this->callTaskDetail($eTask, $this->getMember(), '0.5', \Clock::date());

		$this->checkTaskDetail($document, $instructions, $eTask, 'premier temps sur une récolte', \Clock::week());

	}

	/**
	 * Une ligne ajoutée à la main (`taskDetail:user`), que rien n'écrit et qu'un rechargement oublie, puis
	 * affectée : l'affectation la retient, elle quitte la liste que le tableau porte (`data-added`), et la
	 * page, la réponse appliquée, est celle d'un rechargement, attribut compris. L'affectation retirée, la
	 * ligne sort de l'écran comme du rechargement. Chaque geste réémet la liste lue sur l'écran, comme le
	 * client.
	 */
	public function testTaskDetailAdded(): void {

		$eUserOther = $this->getMemberOther();

		if($this->assertDecorPresent($this->eStructure->hasTime() and $eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' suit le temps et a un second membre') === FALSE) {
			return;
		}

		$this->addPresenceTeam();

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTimesheet($eTask, 2.0, week_date_day(\Clock::week(), 1));

		$document = $this->renderTaskDetail($eTask);

		$input = [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'weekShown' => \Clock::week(),
			'user' => $eUserOther['id'],
		];

		$action = $this->executePage('planning/taskDetail:user', $input + ['added' => ''], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'l\'ajout d\'une ligne est refusé') === FALSE) {
			return;
		}

		[, $template] = $this->runView('user', $action, 'taskDetail');

		$this->apply($document, $template->formatInstructions());

		$this->assertEquals((string)$eUserOther['id'], $document->getElementById('planning-task-detail-grid')?->getAttribute('data-added'), 'l\'écran ne retient pas la ligne ajoutée');

		foreach([
			'ligne ajoutée puis affectée' => 'add',
			'affectation retirée d\'une ligne qui avait été ajoutée' => 'delete',
		] as $case => $assign) {

			$action = $this->executePage('planning/taskDetail:doUser', $input + [
				'action' => $assign,
				'added' => $document->getElementById('planning-task-detail-grid')->getAttribute('data-added'),
			], 'POST');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : l\'affectation est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				return;
			}

			[$data, $template] = $this->runView('doUser', $action, 'taskDetail');

			$this->assertEquals('grid', $data->change, $case.' : la réponse ne redessine pas le tableau, qui porte la liste');

			$this->checkTaskDetail($document, $template->formatInstructions(), $eTask, $case, \Clock::week());

		}

		$this->assertTrue($document->getElementById('planning-task-detail-user-'.$eUserOther['id']) === NULL, 'la ligne qui n\'a plus ni affectation ni temps reste à l\'écran');

	}

	/**
	 * Le choix d'une autre semaine (`taskDetail:week`), saisie, vide, puis de retour à la semaine en cours : la
	 * page, la réponse appliquée, est celle qui s'ouvre sur cette semaine.
	 */
	public function testTaskDetailWeek(): void {

		$eUserOther = $this->getMemberOther();

		if($this->assertDecorPresent($this->eStructure->hasTime() and $eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' suit le temps et a un second membre') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), week_date_day(\Clock::week(), 2));
		$this->createTimesheet($eTask, 1.5, week_date_day(\Clock::week(), 2));
		$this->createTimesheetFor($eTask, $eUserOther, 0.5, week_date_day(\Clock::week(), 1));

		$weekWorked = toWeek(\Clock::date().' - 21 DAYS');
		$this->createTimesheet($eTask, 0.25, week_date_day($weekWorked, 3));

		$document = $this->renderTaskDetail($eTask);

		foreach([
			'semaine saisie' => $weekWorked,
			'semaine vide' => toWeek(\Clock::date().' - 7 DAYS'),
			'retour à la semaine en cours' => \Clock::week(),
		] as $case => $weekShown) {

			$action = $this->executePage('planning/taskDetail:week', [
				'id' => $this->eStructure['id'],
				'task' => $eTask['id'],
				'week' => \Clock::week(),
				'weekShown' => $weekShown,
			], 'GET');

			if($this->assertTrue($action instanceof \ViewAction, $case.' : la semaine est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				return;
			}

			[, $template] = $this->runView('week', $action, 'taskDetail');

			$this->checkTaskDetail($document, $template->formatInstructions(), $eTask, $case, $weekShown);

		}

	}

	/**
	 * Une saisie de récolte passe par le planning actuel (`series/task:doUpdateHarvestCollection`), dont la
	 * réponse recharge le panneau (`series/view/task.v.php`, `ajaxReload` ou `ajaxReloadLayer`) : la page
	 * rechargée est la réponse, il n'y a pas d'instruction ciblée à comparer. Le chiffre rechargé doit valoir
	 * la somme des lignes rechargées, que la page calcule sur les mêmes jours que la frise (`harvestTotal`).
	 */
	public function testTaskDetailHarvestTotal(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$this->writeHarvest($eTask, \Clock::date(), 4.0);

		$other = (week_date_day(\Clock::week(), 1) === \Clock::date()) ? week_date_day(\Clock::week(), 2) : week_date_day(\Clock::week(), 1);

		$action = $this->executePage('series/task:doUpdateHarvestCollection', [
			'id' => $this->eStructure['id'],
			'ids' => [$eTask['id']],
			'harvestDate' => $other,
			'harvestMore' => '2.5',
			'harvestUnit' => \series\Task::KG,
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie de récolte est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$document = $this->renderTaskDetail(\series\TaskLib::getById($eTask['id']));

		$rows = [];

		foreach($document->querySelectorAll('#planning-detail-harvest .planning-detail-harvest-days > .util-procedure-step:not(.util-procedure-step-open)') as $step) {
			$rows[$step->querySelector('input[name="harvestDate"]')?->getAttribute('value')] = (float)$step->querySelector('[data-planning-detail-harvest] > b')?->textContent;
		}

		// La frise range les jours du plus ancien au plus récent, et l'autre jour tombe avant ou après aujourd'hui.
		$expected = [\Clock::date() => 4.0, $other => 2.5];
		ksort($expected);

		$this->assertEquals($expected, $rows, 'la frise rechargée n\'a pas les deux jours, chacun à sa quantité');
		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/intervention/'.$eTask['id'], ['week' => \Clock::week()], 'GET');
		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$this->assertEquals(6.5, round($data->eTask['harvestTotal'], 1), 'le total rechargé n\'est pas la somme des lignes');

	}

	/**
	 * Une récolte ajoutée par la dernière ligne du bloc (`taskDetail:doHarvest`, R-Retours-0710, 155) : le bloc,
	 * la réponse appliquée, est celui d'un rechargement, et la carte restée derrière le panneau celle d'un
	 * rechargement de la semaine.
	 */
	public function testTaskDetailHarvest(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$this->writeHarvest($eTask, \Clock::date(), 4.0);

		$eTask = \series\TaskLib::getById($eTask['id']);

		[$documentWeek] = $this->renderTasks();
		$document = $this->renderTaskDetail($eTask);

		$other = (week_date_day(\Clock::week(), 1) === \Clock::date()) ? week_date_day(\Clock::week(), 2) : week_date_day(\Clock::week(), 1);

		$action = $this->executePage('planning/taskDetail:doHarvest', [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'harvestMore' => '2.5',
			'harvestDate' => $other,
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la récolte de la page d\'une intervention est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('doHarvest', $action, 'taskDetail');

		$instructions = $template->formatInstructions();

		$this->checkTaskDetail($document, $instructions, $eTask, 'récolte depuis la page d\'une intervention', \Clock::week());
		$this->checkTasks($documentWeek, $instructions, 'récolte depuis la page d\'une intervention');

	}

	/**
	 * Une récolte écrite comme le formulaire du planning actuel l'écrit (`TaskLib::update()`), qui porte le
	 * jour et le total de l'intervention.
	 */
	private function writeHarvest(\series\Task $eTask, string $date, float $quantity): void {

		// Sans `build()` : le décor veut exactement cette quantité et cette unité, que le formulaire calculerait
		// à partir de sa saisie.
		$eTask['harvest'] = $quantity;
		$eTask['harvestUnit'] = \series\Task::KG;
		$eTask['eHarvest'] = \series\HarvestLib::getElementFromTask($eTask, $date, $quantity);
		$eTask['eHarvest']['unit'] = \series\Harvest::KG;

		\series\TaskLib::update($eTask, ['harvest', 'harvestUnit']);

	}

	/**
	 * Une intervention prévue la semaine d'avant et faite dans celle-ci, remise à faire : elle retourne à
	 * sa semaine prévue et quitte l'écran. Sa carte sort seule quand son bloc garde une voisine
	 * (`remove`), et son bloc sort seul quand elle le vide (`emptied`, R-Retours-0810, 222), ce que le second
	 * cas obtient avec une action de culture créée pour lui, que la semaine ne peut donc pas porter.
	 */
	public function testTaskLeaves(): void {

		$this->createTaskWeek();

		$eTask = $this->createTaskLate($this->getAction(ACTION_PLANTATION), $this->getCategoryCulture());

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callTask('doTodo', $eTask);

		$this->assertEquals('remove', $data->change, 'la sortie d\'un bloc qui garde une voisine ne retire pas la carte seule');

		$this->checkTasks($document, $instructions, 'sortie d\'un bloc qui garde une voisine');

		$eTask = $this->createTaskLate($this->createActionIn($this->getCategoryCulture()), $this->getCategoryCulture());

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callTask('doTodo', $eTask);

		$this->assertEquals('emptied', $data->change, 'la sortie de la seule carte d\'un bloc ne retire pas ce seul bloc');

		$this->checkTasks($document, $instructions, 'sortie de la seule carte d\'un bloc');

	}

	/**
	 * L8 · Une intervention en retard cochée est faite dans sa semaine prévue, comme dans l'ancien planning
	 * (`\series\TaskLib::updateDoneCollection()`) : elle quitte « En retard » sans entrer dans la semaine
	 * affichée, et rien ne change dans les blocs de la semaine.
	 */
	public function testTaskLateDone(): void {

		$this->createTaskWitness();

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTasks();

		$this->assertTrue($document->querySelector('#planning-week-late #planning-task-'.$eTask['id']) !== NULL, 'l\'intervention en retard n\'est pas dans « En retard »');

		[$data, $instructions] = $this->callTask('doDone', $eTask);

		$this->assertEquals(NULL, $data->change, 'l\'intervention en retard cochée change les blocs de la semaine : '.json_encode($data->change));

		$this->checkTasks($document, $instructions, 'coche d\'une intervention en retard');

		$this->assertEquals(NULL, $document->getElementById('planning-task-'.$eTask['id']), 'l\'intervention en retard cochée est encore à l\'écran');

	}

	/**
	 * L8 · La seule intervention en retard d'une action, cochée : son bloc sort seul de la zone, que garde
	 * une autre intervention en retard, et les autres blocs de la zone ne se répartissent pas de nouveau
	 * (R-Retours-0810, 222, la zone sous la même règle que la semaine).
	 */
	public function testTaskLateBlockEmptied(): void {

		$this->createTaskWitness();
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		$eCategory = $this->getCategoryCulture();

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $this->getCultivation()['series'],
			'cultivation' => $this->getCultivation(),
			'plant' => $this->getCultivation()['plant'],
			'action' => $this->createActionIn($eCategory),
			'category' => $eCategory,
			'status' => \series\Task::TODO,
			'plannedWeek' => toWeek(\Clock::date().' - 7 DAYS'),
			'plannedDate' => NULL,
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()`, qui n'a rien à vérifier ici : le décor pose une intervention à faire, prévue la
		// semaine d'avant, sur une action qu'il vient de créer.
		\series\TaskLib::create($eTask);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callTask('doDone', $eTask);

		$change = $data->changeZones;

		$this->assertTrue(is_array($change) and is_array($change[\planning\TaskLateLib::LATE]) and $change[\planning\TaskLateLib::LATE]['emptied'] === [$eTask['action']['id']], 'le bloc vidé de « En retard » ne sort pas seul de la zone : '.json_encode($change));

		$this->checkTasks($document, $instructions, 'bloc vidé dans « En retard »');

	}

	/**
	 * L8 · Une intervention en retard affectée par le lot reste dans sa zone : sa carte se remplace sur place
	 * (`card` de `TaskLateLib::getChange()`).
	 */
	public function testTaskLateCardInPlace(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doBatchUser', 'doBatch', ['ids' => [$eTask['id']], 'user' => $this->getMember()['id'], 'action' => 'add']);

		$change = $data->changeZones;

		$this->assertTrue(is_array($change) and $change[\planning\TaskLateLib::LATE] !== 'board' and $change[\planning\TaskLateLib::LATE]['card'] === [$eTask['id']], 'la carte affectée dans « En retard » ne se remplace pas sur place : '.json_encode($data->changeZones));

		$this->checkTasks($document, $instructions, 'carte affectée dans « En retard »');

	}

	/**
	 * L8 · « En retard » replié par la personne (`folded`, réémis) : ni un geste qui redessine ses blocs ni
	 * l'écran redemandé ne le rouvrent, comme le rechargement avec le même pli.
	 */
	public function testTaskLateFolded(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		// « En retard » ne s'affiche que s'il reste une intervention en retard après la coche.
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTasks(folded: \planning\TaskLateLib::LATE);

		$this->assertFalse($document->getElementById('planning-week-late')->classList->contains('util-fold-open'), '« En retard » replié est rendu déplié');

		[, $instructions] = $this->callTask('doDone', $eTask, ['folded' => \planning\TaskLateLib::LATE]);

		$this->checkTasks($document, $instructions, 'coche dans « En retard » replié', folded: \planning\TaskLateLib::LATE);

		// Un geste ne rend que des cartes, des blocs ou un plateau, jamais le dépliable : seul un écran redemandé
		// prouve que le serveur lit `folded`. Il part d'un écran où la zone est ouverte, pour qu'un `folded`
		// ignoré la laisse ouverte.
		[$document] = $this->renderTasks();

		$action = $this->executePage('planning/taskWeek:screen', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'folded' => \planning\TaskLateLib::LATE,
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'l\'écran redemandé avec « En retard » replié est refusé') === FALSE) {
			return;
		}

		[, $template] = $this->runView('screen', $action, 'taskWeek');

		$this->checkTasks($document, $template->formatInstructions(), 'écran redemandé avec « En retard » replié', folded: \planning\TaskLateLib::LATE);

		$this->assertFalse($document->getElementById('planning-week-late')->classList->contains('util-fold-open'), 'l\'écran redemandé rouvre « En retard » replié');

	}

	/**
	 * L8 · Un lot pris dans « En retard », replanifié cette semaine : ses cartes quittent la zone et entrent
	 * dans la semaine.
	 */
	public function testTaskLateBatch(): void {

		$weekBefore = toWeek(\Clock::date().' - 7 DAYS');

		$eTaskFirst = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekBefore);
		$eTaskSecond = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekBefore);

		[$document] = $this->renderTasks();

		[, $instructions] = $this->callWeek('doBatchPlannedWeek', 'doBatch', ['ids' => [$eTaskFirst['id'], $eTaskSecond['id']], 'plannedWeek' => \Clock::week()]);

		$this->checkTasks($document, $instructions, 'lot replanifié depuis « En retard »');

	}

	/**
	 * L8 · Une intervention non planifiée cochée, « Non planifié » fermé puis ouvert : fermé, seul son compte
	 * change ; ouvert (`unplanned` réémis), sa carte en sort.
	 */
	public function testTaskUnplannedDone(): void {

		foreach([FALSE, TRUE] as $unplanned) {

			$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);
			$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

			[$document] = $this->renderTasks(unplanned: $unplanned);

			$this->assertEquals($unplanned, $document->querySelector('#planning-week-unplanned #planning-task-'.$eTask['id']) !== NULL, 'la carte non planifiée '.($unplanned ? 'n\'est pas' : 'est').' à l\'écran, « Non planifié » '.($unplanned ? 'ouvert' : 'fermé'));

			[, $instructions] = $this->callTask('doDone', $eTask, $unplanned ? ['unplanned' => '1'] : []);

			$this->checkTasks($document, $instructions, 'coche d\'une intervention non planifiée, « Non planifié » '.($unplanned ? 'ouvert' : 'fermé'), unplanned: $unplanned);

		}

	}

	/**
	 * L8 · La première ouverture de « Non planifié » : ses blocs, puis `unplanned` posé sur l'écran et sur la
	 * barre du lot, exactement l'écran rechargé avec `unplanned`, le dépliable ouvert par le client.
	 */
	public function testTaskUnplannedOpen(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		[$document] = $this->renderTasks();

		$action = $this->executePage('planning/taskWeek:unplanned', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'l\'ouverture de « Non planifié » est refusée') === FALSE) {
			return;
		}

		[, $template] = $this->runView('unplanned', $action, 'taskWeek');

		$document->getElementById('planning-week-unplanned')?->classList->add('util-fold-open');

		$this->checkTasks($document, $template->formatInstructions(), 'ouverture de « Non planifié »', unplanned: TRUE);

	}

	/**
	 * L8 · Au-delà du seuil, une carte cachée par le plafond, cochée : le bloc se rend plafonné comme au
	 * rechargement, puis une carte montrée.
	 */
	public function testTaskLateCapped(): void {

		$eCategory = $this->getCategoryCulture();
		$eAction = $this->createActionIn($eCategory);

		$cTask = new \Collection();

		for($i = 0; $i < \planning\TaskLateLib::LIMIT + 3; $i++) {

			$eTask = new \series\Task([
				'structure' => $this->eStructure,
				'series' => $this->getCultivation()['series'],
				'cultivation' => $this->getCultivation(),
				'plant' => $this->getCultivation()['plant'],
				'action' => $eAction,
				'category' => $eCategory,
				'status' => \series\Task::TODO,
				'plannedWeek' => toWeek(\Clock::date().' - 7 DAYS'),
				'plannedDate' => NULL,
				'repeatMaster' => new \series\Repeat(),
			]);

			// Sans `build()`, qui n'a rien à vérifier ici : le décor pose des interventions à faire, prévues la
			// semaine d'avant, sur une action qu'il vient de créer.
			\series\TaskLib::create($eTask);

			$cTask[] = $eTask;

		}

		$threshold = \planning\TaskLateLib::$threshold;
		\planning\TaskLateLib::$threshold = 0;

		try {

			// Les plus récentes d'abord : la première créée est cachée, la dernière montrée.
			foreach(['carte cachée' => $cTask->first(), 'carte montrée' => $cTask->last()] as $case => $eTask) {

				[$document] = $this->renderTasks();

				[, $instructions] = $this->callTask('doDone', $eTask);

				$this->checkTasks($document, $instructions, 'bloc plafonné, '.$case);

			}

		} finally {
			\planning\TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * L8 · Du temps saisi sur la page d'une intervention en retard la fait passer dans la semaine : la page
	 * redemande l'écran entier (`PlanningTaskZone.screen()`), que `taskWeek:screen` rend comme un
	 * rechargement.
	 */
	public function testTaskDetailTimeLeavesZone(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail, pour la page d\'une intervention') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTasks();

		$action = $this->executePage('planning/taskDetail:doTime', [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'user' => $this->getMember()['id'],
			'time' => '0.5',
			'date' => \Clock::date(),
			'weekShown' => \Clock::week(),
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie de la page d\'une intervention est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[$data, $template] = $this->runView('doTime', $action, 'taskDetail');

		$this->assertTrue($data->screen, 'l\'intervention passée de « En retard » à la semaine ne redemande pas l\'écran');
		$this->assertTrue(str_contains(json_encode($template->formatInstructions()), 'PlanningTaskZone.screen()'), 'la réponse ne redemande pas l\'écran');

		$this->apply($document, $template->formatInstructions());

		$action = $this->executePage('planning/taskWeek:screen', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'l\'écran redemandé est refusé') === FALSE) {
			return;
		}

		[, $template] = $this->runView('screen', $action, 'taskWeek');

		$this->checkTasks($document, $template->formatInstructions(), 'écran redemandé par la page d\'une intervention');

	}

	/**
	 * La page d'une intervention, ouverte depuis la semaine, rendue par l'UI de sa vue avec les données
	 * qu'elle a préparées : la vue rend un panneau, dont le corps est la page. `$weekShown` l'ouvre sur cette
	 * semaine, sans quoi elle s'ouvre sur sa semaine d'ouverture.
	 */
	private function renderTaskDetail(\series\Task $eTask, ?string $weekShown = NULL): \Dom\HTMLDocument {

		$input = ['week' => \Clock::week()];

		if($weekShown !== NULL) {
			$input['weekShown'] = $weekShown;
		}

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/intervention/'.$eTask['id'], $input, 'GET');

		$this->assertTrue($action instanceof \ViewAction, 'la page de l\'intervention ne se rend pas');

		$data = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action);

		$panel = new TaskDetailUi()->get($data->eTask, $data->week, $data->cPlace, $data->cPhoto, $data->cUser, $data->cComment, $data->history, $data->days, $data->foot, $data->navigation, $data->timesByDay);

		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$panel->body.'</body></html>', LIBXML_NOERROR);

	}

	/**
	 * Le temps d'une personne un jour, saisi sur la page d'une intervention ouverte depuis la semaine, dans le
	 * tableau de la semaine de ce jour.
	 */
	private function callTaskDetail(\series\Task $eTask, \user\User $eUser, string $time, string $date): array {

		$action = $this->executePage('planning/taskDetail:doTime', [
			'id' => $this->eStructure['id'],
			'task' => $eTask['id'],
			'week' => \Clock::week(),
			'user' => $eUser['id'],
			'time' => $time,
			'date' => $date,
			'weekShown' => toWeek($date),
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la saisie de la page d\'une intervention est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return [new \stdClass(), []];
		}

		[$data, $template] = $this->runView('doTime', $action, 'taskDetail');

		return [$data, $template->formatInstructions()];

	}

	/**
	 * La fiche, qui porte la récolte, et le temps de travail d'avant, les instructions appliquées, contre les mêmes
	 * rechargées sur la semaine `$weekShown`, celle que l'écran montrait.
	 */
	private function checkTaskDetail(\Dom\HTMLDocument $before, array $instructions, \series\Task $eTask, string $case, string $weekShown): void {

		$this->assertTrue($instructions !== [], $case.' : le geste n\'a rendu aucune instruction');

		$this->apply($before, $instructions);

		// La fiche et la saisie : un geste de saisie qui toucherait aux dates ou à l'affectation de la fiche
		// doit l'y repeindre aussi.
		$getBlock = fn(\Dom\HTMLDocument $document) => $this->neutralize(implode('', array_map(
			fn(?\Dom\Element $node) => $node === NULL ? '' : $document->saveHtml($node),
			[$document->querySelector('#planning-task-detail-intervention'), $document->querySelector('#planning-task-detail-time')]
		)));

		$expected = $getBlock($this->renderTaskDetail($eTask, $weekShown));
		$actual = $getBlock($before);

		$this->assertTrue($expected === $actual, $case.' : empreinte de la saisie après réponse ciblée et après rechargement'.$this->getGap($expected, $actual));

	}

	/**
	 * Une intervention prévue la semaine d'avant et faite aujourd'hui : c'est ce qui la fait quitter la
	 * semaine une fois remise à faire. `createTask()` ne sait pas l'écrire, `TaskLib::create()` posant la
	 * semaine prévue sur celle du fait quand l'appelant n'en donne pas.
	 */
	private function createTaskLate(\work\Action $eAction, \work\Category $eCategory): \series\Task {

		$isCulture = $eCategory->is($this->getCategoryCulture());

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => $isCulture ? $this->getCultivation()['series'] : new \series\Series(),
			'cultivation' => $isCulture ? $this->getCultivation() : new \series\Cultivation(),
			'plant' => $isCulture ? $this->getCultivation()['plant'] : new \plant\Plant(),
			'action' => $eAction,
			'category' => $eCategory,
			'status' => \series\Task::DONE,
			'plannedWeek' => toWeek(\Clock::date().' - 7 DAYS'),
			'plannedDate' => NULL,
			'doneWeek' => \Clock::week(),
			'doneDate' => \Clock::date(),
			'repeatMaster' => new \series\Repeat(),
		]);

		// Sans `build()`, qui vérifierait l'action et les semaines saisies : le formulaire ne pose pas
		// ensemble une semaine prévue et un fait, que le décor veut en deux semaines différentes.
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * Sous « Masquer ce qui est fait », la carte cochée reste à l'écran jusqu'au rechargement (charte,
	 * point 10 e) : c'est le seul écart admis avec le rechargement, et la carte gardée porte l'état fait.
	 */
	public function testTaskHidden(): void {

		$eTask = $this->createTaskWeek();
		$this->createTaskWeek();

		$this->setHideDone(TRUE);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callTask('doDone', $eTask);

		$this->assertEquals('card', $data->change, 'la carte cochée sous le masque ne reste pas à l\'écran');

		$this->checkTasks($document, $instructions, 'coche sous le masque', eTaskKept: $eTask);

	}

	/**
	 * « Nouvelle intervention » de la Semaine (L5d) : une intervention neuve dans un bloc qui existe le
	 * rend entier, dans un bloc qui n'existe pas encore rend les blocs, les piles se répartissant côté
	 * navigateur.
	 */
	public function testTaskCreate(): void {

		$this->createTaskWeek();

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
		]);

		$this->assertEquals('block', $data->change, 'une intervention neuve dans un bloc existant ne rend pas son bloc');
		$this->checkTasks($document, $instructions, 'nouvelle intervention, bloc existant');

		// Le décor de la structure peut porter déjà un traitement cette semaine : le bloc neuf se constate.
		$eAction = $this->getAction(ACTION_TRAITEMENT);
		$opens = ($document->getElementById('planning-week-block-'.$eAction['id']) === NULL);

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $eAction['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
		]);

		$this->assertEquals($opens ? 'board' : 'block', $data->change, 'une intervention neuve ne rend pas les blocs quand elle en ouvre un, son bloc sinon');
		$this->checkTasks($document, $instructions, 'nouvelle intervention, bloc neuf');

	}

	/**
	 * La grille de la Semaine (CW) répond aux mêmes gestes par les mêmes changements que les cartes, sur ses
	 * propres nœuds : la ligne se remplace à la coche et à la décoche (`card`), sort seule (`remove`), avec
	 * son groupe quand elle le vide (`emptied`), et une intervention neuve rend son groupe (`block`) ou toute
	 * la grille quand elle en ouvre un (`board`). Le choix de la grille est celui du membre, que les gestes
	 * relisent comme le rechargement.
	 */
	public function testTaskGrid(): void {

		\structure\MemberLib::setView('viewPlanningWeek', \structure\StructureLib::getById($this->eStructure['id']), \structure\Member::GRID);

		$eTask = $this->createTaskWeek();

		[$document] = $this->renderTasks();

		if($this->assertTrue($document->getElementById('planning-grid-task-'.$eTask['id']) !== NULL, 'la Semaine ne se rend pas en grille') === FALSE) {
			return;
		}

		foreach(['doDone' => 'coche', 'doTodo' => 'décoche'] as $route => $case) {

			[$document] = $this->renderTasks();

			[$data, $instructions] = $this->callTask($route, $eTask);

			$this->assertEquals('card', $data->change, 'grille, '.$case.' : la ligne ne se remplace pas sur place');
			$this->checkTasks($document, $instructions, 'grille, '.$case);

		}

		// Les gestes propres à la grille (CW-b) : le jour prévu, le temps d'une personne un jour et l'affectation,
		// dont la réponse ne redessine que les vignettes et l'entrée de la liste.
		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doPlanned', 'doTask', ['task' => $eTask['id'], 'planned' => ['date' => week_date_day(\Clock::week(), 6)]]);

		$this->assertEquals('card', $data?->change, 'grille, jour prévu : la ligne ne se remplace pas sur place');
		$this->checkTasks($document, $instructions, 'grille, jour prévu');

		// L'« Enregistrer » du menu d'un jour (ET) ferme le menu à la réponse acceptée (FJ), qui le vide
		// (`PlanningTaskDetail.open()`) : la réponse s'applique sur le menu tel que son ouverture l'a rendu, puis le
		// menu se vide comme le client le vide, et l'écran se compare à l'écran rechargé, menu fermé.
		$monday = week_date_day(\Clock::week(), 1);

		[$document] = $this->renderTasks();
		$this->openTime($document, $eTask, $monday);

		[$data, $instructions] = $this->callWeek('doTimeTeam', 'doTimeTeam', ['task' => $eTask['id'], 'date' => $monday, 'time' => [$this->getMember()['id'] => '1.25']]);

		$this->assertEquals('card', $data?->change, 'grille, temps d\'un jour : la ligne ne se remplace pas sur place');
		$this->assertTrue($instructions !== [], 'grille, temps d\'un jour : le geste n\'a rendu aucune instruction');

		$this->apply($document, $instructions);

		$list = $document->getElementById('planning-grid-time-'.$eTask['id'].'-'.$monday);

		if($this->assertTrue($list !== NULL, 'grille, temps d\'un jour : la réponse retire le menu du jour') === FALSE) {
			return;
		}

		$list->innerHTML = '';

		[$expected] = $this->renderTasks();

		$fingerprintExpected = $this->getFingerprint($expected);
		$fingerprintActual = $this->getFingerprint($document);

		$this->assertTrue($fingerprintExpected === $fingerprintActual, 'grille, temps d\'un jour : empreinte après réponse ciblée et après rechargement'.$this->getGap($fingerprintExpected, $fingerprintActual));

		[$document] = $this->renderTasks();

		[, $instructions] = $this->callWeek('doUser', 'doUser', ['task' => $eTask['id'], 'user' => $this->getMember()['id'], 'action' => 'add']);

		$this->checkTasks($document, $instructions, 'grille, affectation');

		// Le temps prévu et le jour retiré (DF), sur une intervention que rien n'a commencée : le temps prévu ne
		// redessine que le travail estimé, le retrait sort la ligne de la grille.
		$eTaskFree = $this->createTaskWeek();

		[$document] = $this->renderTasks();

		[, $instructions] = $this->callWeek('doTimeExpected', 'doTimeExpected', ['task' => $eTaskFree['id'], 'timeExpected' => '2.5']);

		// La fenêtre n'est pas rendue à nouveau : le client écrit lui-même la durée dans le champ, sa dernière
		// durée envoyée et sa tuile (`PlanningDurationStep.send()`, `tile()`), ce que le cas reproduit avant la
		// comparaison.
		$expected = '#planning-grid-task-'.$eTaskFree['id'].' .planning-grid-expected';
		$field = $document->querySelector($expected.' .planning-duration-step');

		if($this->assertTrue($field !== NULL, 'grille, temps prévu : la saisie du temps prévu manque') === FALSE) {
			return;
		}

		$field->setAttribute('data-sent', '2.5');
		$this->writeStepped($field, 2.5);

		$value = $document->querySelector($expected.' > .planning-duration-step-tile > .planning-duration-tile-value');
		$value->textContent = DurationUi::formatDuration(2.5);
		$value->classList->add('planning-duration-tile-set');

		$this->checkTasks($document, $instructions, 'grille, temps prévu');

		[$document] = $this->renderTasks();

		[, $instructions] = $this->callWeek('doUnplanned', 'doTask', ['task' => $eTaskFree['id']]);

		$this->checkTasks($document, $instructions, 'grille, jour retiré');

		// La saisie de récolte poste l'affichage des Récoltes sous le même nom (`view`) : la réponse reste celle
		// de la grille que le membre a choisie.
		$eCultivation = $this->createCultivation($this->createSeries('Série '.uniqid()));
		$eTaskHarvest = $this->createTask($eCultivation, ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doHarvest', 'doTask', ['task' => $eTaskHarvest['id'], 'cultivation' => $eCultivation['id'], 'date' => \Clock::date(), 'mode' => 'add', 'harvestMore' => '3', 'panel' => 1]);

		$this->assertTrue($data?->grid ?? FALSE, 'grille, récolte : la réponse ne suit pas la grille choisie par le membre');
		$this->assertEquals('card', $data?->change, 'grille, récolte : la ligne ne se remplace pas sur place');
		$this->checkTasks($document, $instructions, 'grille, récolte');

		$eTaskLate = $this->createTaskLate($this->getAction(ACTION_PLANTATION), $this->getCategoryCulture());

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callTask('doTodo', $eTaskLate);

		$this->assertEquals('remove', $data->change, 'grille : la sortie d\'un groupe qui garde une voisine ne retire pas la ligne seule');
		$this->checkTasks($document, $instructions, 'grille, sortie d\'un groupe qui garde une voisine');

		$eTaskLate = $this->createTaskLate($this->createActionIn($this->getCategoryCulture()), $this->getCategoryCulture());

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callTask('doTodo', $eTaskLate);

		$this->assertEquals('emptied', $data->change, 'grille : la sortie de la seule ligne d\'un groupe ne retire pas ce seul groupe');
		$this->checkTasks($document, $instructions, 'grille, sortie de la seule ligne d\'un groupe');

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
		]);

		$this->assertEquals('block', $data->change, 'grille : une intervention neuve dans un groupe existant ne rend pas son groupe');
		$this->checkTasks($document, $instructions, 'grille, nouvelle intervention, groupe existant');

		$eAction = $this->createActionIn($this->getCategoryCulture());

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $eAction['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
		]);

		$this->assertEquals('board', $data->change, 'grille : une intervention neuve qui ouvre un groupe ne rend pas la grille');
		$this->checkTasks($document, $instructions, 'grille, nouvelle intervention, groupe neuf');

	}

	/**
	 * Les gestes de la barre de sélection en grille (DG-3) : « Fait ! » et « Temps » sur deux lignes, « Récolter »
	 * sur deux récoltes de la même espèce, chacun égal au rechargement. Les lignes portent leur case, rendue
	 * décochée, que la réponse redessine avec elles, et la première est commentée (DW).
	 */
	public function testTaskGridBatch(): void {

		\structure\MemberLib::setView('viewPlanningWeek', \structure\StructureLib::getById($this->eStructure['id']), \structure\Member::GRID);

		$eTaskFirst = $this->createTaskWeek();
		$eTaskSecond = $this->createTaskWeek();
		$this->createTaskWeek();

		$this->createComments($eTaskFirst, ['Arroser avant de planter', 'Pailler le rang entier']);

		[$document] = $this->renderTasks();

		if($this->assertTrue($document->querySelector('#planning-grid-task-'.$eTaskFirst['id'].' [name="batch[]"]') !== NULL, 'la ligne de la grille n\'a pas sa case') === FALSE) {
			return;
		}

		$ids = [$eTaskFirst['id'], $eTaskSecond['id']];

		[$data, $instructions] = $this->callWeek('doBatchDone', 'doBatch', ['ids' => $ids]);

		$this->assertTrue($data?->grid ?? FALSE, 'grille, lot fait : la réponse ne suit pas la grille choisie par le membre');
		$this->assertEquals(array_fill_keys($ids, 'card'), $data?->change, 'grille, lot fait : les lignes ne se remplacent pas sur place');
		$this->checkTasks($document, $instructions, 'grille, lot fait');

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doBatchTime', 'doBatch', [
			'ids' => $ids,
			'date' => \Clock::date(),
			'time' => '2',
		]);

		$this->assertEquals(array_fill_keys($ids, 'card'), $data?->change, 'grille, temps en lot : les lignes ne se remplacent pas sur place');
		$this->checkTasks($document, $instructions, 'grille, temps en lot');

		$eTaskHarvestFirst = $this->createTask($this->createCultivation($this->createSeries('Série '.uniqid())), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());
		$eTaskHarvestSecond = $this->createTask($this->createCultivation($this->createSeries('Série '.uniqid())), ACTION_RECOLTE, \series\Task::TODO, \Clock::week());

		$idsHarvest = [$eTaskHarvestFirst['id'], $eTaskHarvestSecond['id']];

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doBatchHarvest', 'doBatch', [
			'ids' => $idsHarvest,
			'date' => \Clock::date(),
			'harvestMore' => '4',
			'harvestUnit' => \series\Task::KG,
			'distribution' => 'fair',
		]);

		$this->assertEquals(array_fill_keys($idsHarvest, 'card'), $data?->change, 'grille, récolte en lot : les lignes ne se remplacent pas sur place');
		$this->checkTasks($document, $instructions, 'grille, récolte en lot');

	}

	/**
	 * Un refus du serveur sur le temps prévu de la grille : un temps négatif, que la fenêtre ne propose pas et
	 * qui se forge. Le champ reprend la durée d'avant, l'écran est celui d'un rechargement.
	 */
	public function testTaskGridRefused(): void {

		\structure\MemberLib::setView('viewPlanningWeek', \structure\StructureLib::getById($this->eStructure['id']), \structure\Member::GRID);

		$eTask = $this->createTaskWeek(timeExpected: 1.0);

		[$document] = $this->renderTasks();

		$action = $this->executePage('planning/taskWeek:doTimeExpected', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'task' => $eTask['id'],
			'timeExpected' => '-1',
		], 'POST');

		$this->checkRefusedStepped($document, $action, '#planning-grid-task-'.$eTask['id'].' .planning-grid-expected .planning-duration-step', 2.5, 'Task::timeExpected.check', 'temps prévu négatif sur la grille');

		$this->assertEquals($this->getFingerprint($this->renderTasks()[0]), $this->getFingerprint($document), 'la grille après le refus, la durée d\'avant remise, diffère du rechargement');

	}

	/**
	 * « Nouvelle intervention » sur plusieurs séries (R-Retours-0310, point 2) : les interventions neuves
	 * partagent une action, donc un bloc. Dans un bloc qui existe, la réponse le rend entier ; quand elles
	 * l'ouvrent ensemble, elle rend les blocs, le bloc neuf portant alors plus d'une carte.
	 */
	public function testTaskCreateSeveral(): void {

		$this->createTaskWeek();

		$cultivations = [$this->getCultivation()['id'], $this->createCultivation($this->createSeries('Seconde série du décor'))['id']];

		// Créée avant le premier rendu : une action neuve n'arrive dans la liste du mode Personnalisation
		// qu'au rechargement, aucune intervention créée ne pouvant en ouvrir une.
		$eAction = $this->createActionIn($this->getCategoryCulture());

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $cultivations,
			'day' => '',
		]);

		$this->assertEquals(2, $data->cTaskCreated->count(), 'deux séries ne créent pas deux interventions');
		$this->assertEquals('block', $data->change, 'des interventions neuves dans un bloc existant ne rendent pas leur bloc');
		$this->checkTasks($document, $instructions, 'deux séries, bloc existant');

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $eAction['id'],
			'cultivation' => $cultivations,
			'day' => '',
		]);

		$this->assertEquals('board', $data->change, 'des interventions neuves qui ouvrent un bloc ne rendent pas les blocs');
		$this->checkTasks($document, $instructions, 'deux séries, bloc neuf');

	}

	/**
	 * Les gestes de lot de la Semaine : « Fait » remplace les cartes sur place, « Affecter » aussi, et
	 * « Supprimer » retire les cartes, puis le bloc vidé rend les blocs.
	 */
	public function testTaskBatch(): void {

		$eTaskFirst = $this->createTaskWeek();
		$eTaskSecond = $this->createTaskWeek();
		$this->createTaskWeek();

		$ids = [$eTaskFirst['id'], $eTaskSecond['id']];

		[$document] = $this->renderTasks();

		// « Affecter » ne prend que des interventions à faire : il passe avant « Fait ».
		[, $instructions] = $this->callWeek('doBatchUser', 'doBatch', ['ids' => $ids, 'user' => $this->getMember()['id'], 'action' => 'add']);
		$this->checkTasks($document, $instructions, 'lot affecté');

		[$data, $instructions] = $this->callWeek('doBatchDone', 'doBatch', ['ids' => $ids]);
		$this->assertEquals(array_fill_keys($ids, 'card'), $data->change, 'le lot « Fait » ne remplace pas ses cartes sur place');
		$this->checkTasks($document, $instructions, 'lot fait');

		[$data, $instructions] = $this->callWeek('doBatchDelete', 'doBatch', ['ids' => $ids]);
		$this->assertEquals(array_fill_keys($ids, 'remove'), $data->change, 'le lot « Supprimer » ne retire pas ses cartes');
		$this->checkTasks($document, $instructions, 'lot supprimé');

	}

	/**
	 * « Temps » sur un lot de la Semaine (R-Retours-1210, lot BG) : une intervention à faire que sa part du total
	 * passe faite et une faite dont le temps augmente, remplacées sur place, avec le temps de la semaine.
	 */
	public function testTaskBatchTime(): void {

		$eTaskTodo = $this->createTaskWeek();
		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTaskWeek();

		$this->createTimesheet($eTaskDone, 1.0);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doBatchTime', 'doBatch', [
			'ids' => [$eTaskTodo['id'], $eTaskDone['id']],
			'date' => \Clock::date(),
			'time' => '2',
		]);

		if($data === NULL) {
			return;
		}

		$this->assertEquals([$eTaskTodo['id'] => 'card', $eTaskDone['id'] => 'card'], $data->change, '« Temps » ne remplace pas ses cartes sur place');
		$this->checkTasks($document, $instructions, 'temps en lot');

	}

	/**
	 * Un lot qui vide son bloc retire ce seul bloc (`emptied`, R-Retours-0810, 222) : le report à la semaine
	 * suivante, la suppression, et « Fait ! » sous « Masquer ce qui est fait ». Les autres blocs ne se
	 * répartissent pas de nouveau entre les piles, ce que le rendu du serveur, sans piles, ne peut pas montrer
	 * et que Chrome éprouve ; il montre le reste, contenu et ordre des blocs, égal au rechargement. Chaque
	 * action est créée pour le cas, son bloc ne porte donc que la carte du lot, et un bloc la suit : son rang
	 * (`data-util-piles-rank`) doit prendre celui qu'un rechargement lui donne (`planningTaskWeekRanks()`).
	 */
	public function testTaskBatchEmptiesBlock(): void {

		$this->createTaskWitness();

		foreach([
			'lot reporté' => ['doBatchIncrement', ['increment' => 1], FALSE],
			'lot supprimé' => ['doBatchDelete', [], FALSE],
			'lot fait sous le masque' => ['doBatchDone', [], TRUE],
		] as $case => [$route, $input, $hide]) {

			$this->setHideDone($hide);

			$eTask = $this->createTaskIn($this->createActionIn($this->getCategoryCulture()), $this->getCategoryCulture());
			$eTaskAfter = $this->createTaskIn($this->createActionIn($this->getCategoryCulture()), $this->getCategoryCulture());

			[$document] = $this->renderTasks();

			$blocks = array_map(fn(\Dom\Element $block) => $block->getAttribute('data-action'), iterator_to_array($document->querySelectorAll('#planning-week-board > .planning-week-block')));

			if($this->assertDecorPresent(array_search((string)$eTask['action']['id'], $blocks, TRUE) < array_search((string)$eTaskAfter['action']['id'], $blocks, TRUE), $case.', un bloc suit celui que le lot vide') === FALSE) {
				return;
			}

			[$data, $instructions] = $this->callWeek($route, 'doBatch', ['ids' => [$eTask['id']]] + $input);

			if($data === NULL) {
				return;
			}

			$this->assertEquals([$eTask['id'] => 'emptied'], $data->change, $case.' : le lot qui vide un bloc ne retire pas ce seul bloc');
			$this->checkTasks($document, $instructions, $case);

		}

		$this->setHideDone(FALSE);

	}

	/**
	 * Le lâcher d'une ligne de « Personnaliser le planning » ne rend rien : Sortable a déjà déplacé la ligne,
	 * que `PlanningOrder.drop()` renumérote. La page d'avant, ainsi rangée, est celle du rechargement ; la
	 * Semaine se range à son ouverture.
	 */
	public function testTaskOrder(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTask($this->getCultivation(), ACTION_SEMIS_DIRECT, \series\Task::TODO, \Clock::week());

		[$document, $data] = $this->renderCustomize();

		$actions = $data->eMember['cAction']->getIds();

		if($this->assertTrue(count($actions) > 1, 'la liste porte moins de deux actions, le lâcher ne se compare pas') === FALSE) {
			return;
		}

		$last = $actions[count($actions) - 1];

		$action = $this->executePage('planning/taskWeek:doOrder', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'action' => $last,
			'before' => $actions[0],
		], 'POST');

		$this->assertTrue($action instanceof \VoidAction, 'le lâcher est refusé : '.implode(', ', $this->getFails($action)));

		$fold = $document->querySelector('#planning-customize-list > .util-deck-fold');
		$fold->insertBefore($fold->querySelector('[data-action="'.$last.'"]'), $fold->querySelector('[data-action="'.$actions[0].'"]'));

		foreach($fold->querySelectorAll('.planning-customize-rank') as $position => $rank) {
			$rank->textContent = (string)($position + 1);
		}

		$expected = $this->getFingerprint($this->renderCustomize()[0]);
		$actual = $this->getFingerprint($document);

		$this->assertTrue($expected === $actual, 'lâcher : empreinte après le geste et après rechargement'.$this->getGap($expected, $actual));

	}

	/**
	 * R-Personnalisation · La densité choisie répond par le bandeau des réglages, et la page d'avant, ces
	 * instructions appliquées, est celle du rechargement.
	 */
	public function testTaskDensity(): void {

		[$document] = $this->renderCustomize();

		foreach([\structure\Member::DENSE, \structure\Member::SPACIOUS, \structure\Member::NORMAL] as $density) {

			[, $instructions] = $this->callWeek('doDensity', 'doDensity', ['viewPlanningDensity' => $density]);

			\structure\MemberLib::clearCache();
			$this->forgetAccountingYear();

			$this->checkCustomize($document, $instructions, 'densité '.$density);

		}

	}

	/**
	 * R-Personnalisation · La couleur allumée puis éteinte répond par la classe de la page et le bandeau des
	 * réglages, comparés au rechargement.
	 */
	public function testTaskColor(): void {

		[$document] = $this->renderCustomize();

		foreach(['1' => 'allumée', '0' => 'éteinte'] as $color => $case) {

			[, $instructions] = $this->callWeek('doColor', 'doColor', ['viewPlanningColor' => $color]);

			\structure\MemberLib::clearCache();
			$this->forgetAccountingYear();

			$this->checkCustomize($document, $instructions, 'couleur '.$case);

		}

	}

	/**
	 * R-PenseBete · L'interrupteur du pense-bête, allumé puis éteint, répond par le bandeau des réglages,
	 * comparé au rechargement de la page.
	 */
	public function testTaskReminderSwitch(): void {

		[$document] = $this->renderCustomize();

		foreach(['1' => 'allumé', '0' => 'éteint'] as $reminders => $case) {

			[, $instructions] = $this->callWeek('doReminders', 'doReminders', ['viewPlanningReminders' => $reminders]);

			\structure\MemberLib::clearCache();
			$this->forgetAccountingYear();

			$this->checkCustomize($document, $instructions, 'pense-bête '.$case);

		}

	}

	/**
	 * R-PenseBete · L'ajout, la coche, la décoche et la modification d'un rappel ne remplacent que le
	 * pense-bête, qui doit alors être celui du rechargement : rappels de la semaine, restés, compte de la
	 * tête. L'effacement, dans ses trois granularités (la ligne et la tête, le dernier resté avec son
	 * titre, le bloc vidé), y revient aussi.
	 */
	public function testTaskReminder(): void {

		$this->checkTaskReminder(\structure\Member::CARDS);

	}

	/**
	 * DC-bis · Les mêmes gestes dans la grille, où le pense-bête est le premier groupe du tableau et où un
	 * départ retire la ligne sans copie animée.
	 */
	public function testTaskReminderGrid(): void {

		$this->checkTaskReminder(\structure\Member::GRID);

	}

	private function checkTaskReminder(string $view): void {

		$this->createTaskWitness();

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);
		\structure\MemberLib::setView('viewPlanningWeek', \structure\StructureLib::getById($this->eStructure['id']), $view);

		$week = \Clock::week();

		// Sans `build()` : le décor pose un rappel resté d'une semaine passée, que la route n'écrirait
		// jamais là, l'écran ne proposant que sa propre semaine.
		\planning\ReminderLib::create(new \planning\Reminder([
			'structure' => $this->eStructure,
			'week' => toWeek(week_date_starts($week).' -7 days'),
			'date' => NULL,
			'text' => 'Resté',
		]));

		// Sans `build()` : un second rappel de la semaine, sans jour, que la route vérifierait comme l'ajout
		// qui suit ; il garde le pense-bête vivant quand le premier effacement ne retire qu'une ligne.
		$eReminderSecond = new \planning\Reminder([
			'structure' => $this->eStructure,
			'week' => $week,
			'date' => NULL,
			'text' => 'Deuxième',
		]);

		\planning\ReminderLib::create($eReminderSecond);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doReminderCreate', 'doReminder', ['text' => 'Appeler le garage', 'date' => week_date_day($week, 2)]);
		$this->checkTasks($document, $instructions, 'rappel ajouté');

		$reminders = \planning\ReminderLib::getForWeek($this->eStructure, $week);

		$eReminder = $reminders['cReminder']->first();
		$eReminderBefore = $reminders['cReminderBefore']->first();

		[, $instructions] = $this->callWeek('doReminderCheck', 'doReminder', ['reminder' => $eReminder['id']]);
		$this->checkTasks($document, $instructions, 'rappel coché');

		[, $instructions] = $this->callWeek('doReminderUncheck', 'doReminder', ['reminder' => $eReminder['id']]);
		$this->checkTasks($document, $instructions, 'rappel décoché');

		[, $instructions] = $this->callWeek('doReminderUpdate', 'doReminder', ['reminder' => $eReminder['id'], 'text' => 'Appeler le garage demain', 'date' => week_date_day($week, 4)]);
		$this->checkTasks($document, $instructions, 'rappel modifié');

		[, $instructions] = $this->callWeek('doReminderUpdate', 'doReminder', ['reminder' => $eReminderBefore['id'], 'text' => 'Resté', 'date' => week_date_day($eReminderBefore['week'], 1)]);
		$this->checkTasks($document, $instructions, 'resté daté dans sa semaine');

		[, $instructions] = $this->callWeek('doReminderDelete', 'doReminder', ['reminder' => $eReminderSecond['id']]);
		$this->checkTasks($document, $instructions, 'rappel effacé');

		[, $instructions] = $this->callWeek('doReminderDelete', 'doReminder', ['reminder' => $eReminderBefore['id']]);
		$this->checkTasks($document, $instructions, 'dernier resté effacé');

		[, $instructions] = $this->callWeek('doReminderDelete', 'doReminder', ['reminder' => $eReminder['id']]);
		$this->checkTasks($document, $instructions, 'pense-bête vidé');

	}

	/**
	 * R-PenseBete · Un lot qui vide un bloc rend les blocs entiers (`board`), dont le pense-bête est la
	 * première carte : il doit y revenir tel qu'au rechargement.
	 */
	public function testTaskReminderBoard(): void {

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

		// Sans `build()` : un rappel à faire de la semaine, que la route vérifierait comme `testTaskReminder()`.
		\planning\ReminderLib::create(new \planning\Reminder([
			'structure' => $this->eStructure,
			'week' => \Clock::week(),
			'date' => NULL,
			'text' => 'Pendant le lot',
		]));

		$this->testTaskBatchEmptiesBlock();

	}

	/**
	 * Une culture d'une espèce absente de l'écran, récoltée depuis « Nouvelle récolte » : elle entre avec
	 * l'intertitre de son espèce (R-Retours-0810, 225).
	 */
	public function testSectionInserted(): void {

		$this->createCultivationHarvested($this->createSeries('Série témoin de la semaine'));

		$eCultivation = $this->createCultivation($this->createSeries('Série d\'une autre espèce'), $this->createPlant());

		[$document, $data] = $this->render();

		$this->assertFalse($data->cCultivation->offsetExists($eCultivation['id']), 'la culture sans récolte prévue est déjà listée');

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '2',
			'panel' => 1,
		], 'POST');

		$this->assertTrue($data->change['inserted'][$eCultivation['id']]['section'] ?? FALSE, 'la culture d\'une espèce neuve entre sans l\'intertitre de son espèce');

		$this->check($document, $instructions, 'entrée d\'une espèce neuve');

	}

	/**
	 * Une culture qui entre avant la première série de son espèce se place sous l'intertitre déjà à l'écran,
	 * et non au-dessus de lui (R-Retours-0810, 225).
	 */
	public function testSectionInsertedFirst(): void {

		$this->createCultivationHarvested($this->createSeries('Série témoin de la semaine'));

		$eCultivation = $this->createCultivation($this->createSeries('A, série hors semaine'));

		[$document, $data] = $this->render();

		$this->assertFalse($data->cCultivation->offsetExists($eCultivation['id']), 'la culture sans récolte prévue est déjà listée');

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '2',
			'panel' => 1,
		], 'POST');

		$this->assertEquals($eCultivation['id'], $data->cCultivation->first()['id'], 'la culture entrée n\'est pas la première de son espèce');
		$this->assertEquals(['after' => NULL, 'section' => FALSE], $data->change['inserted'][$eCultivation['id']] ?? NULL, 'la première série d\'une espèce déjà à l\'écran ne se place pas sous son intertitre');

		$this->check($document, $instructions, 'entrée en tête d\'une espèce');

	}

	/**
	 * La dernière série d'une espèce qui sort de l'écran emporte l'intertitre de l'espèce (R-Retours-0810, 225).
	 */
	public function testSectionRemoved(): void {

		$this->createCultivationHarvested($this->createSeries('Série témoin de la semaine'));

		$eCultivation = $this->createCultivation($this->createSeries('Série récoltée hors période'), $this->createPlant());

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		[$document, $data] = $this->render();

		$this->assertTrue($data->cCultivation->offsetExists($eCultivation['id']), 'la culture récoltée cette semaine n\'est pas listée');

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'replace',
			'harvestDay' => '0',
		], 'POST');

		$this->assertEquals([$eCultivation['id']], $data->change['removed'], 'la culture remise à zéro hors période reste à l\'écran');
		$this->assertEquals([$eCultivation['plant']['id']], $data->change['sections']['removed'], 'l\'intertitre de l\'espèce qui n\'a plus de série reste à l\'écran');

		$this->check($document, $instructions, 'sortie de la dernière série d\'une espèce');

	}

	/**
	 * Une saisie ne relit que les espèces qu'elle peut changer (EX, `HarvestWeekLib::getPlantsAround()`) : au
	 * milieu d'une espèce de trois séries, la liste relue ne porte pas l'espèce voisine, la réponse ne remplace
	 * que la ligne saisie, et le curseur passe à la série suivante de l'espèce. Avec et sans la saisie rapide.
	 */
	public function testAroundMiddle(): void {

		$ePlant = $this->createPlant();

		$this->createCultivationHarvested($this->createSeries('A, série de l\'espèce'), $ePlant);
		$eCultivation = $this->createCultivationHarvested($this->createSeries('B, série de l\'espèce'), $ePlant);
		$eCultivationLast = $this->createCultivationHarvested($this->createSeries('C, série de l\'espèce'), $ePlant);

		$eCultivationOther = $this->createCultivationHarvested($this->createSeries('Série d\'une autre espèce'), $this->createPlant());

		foreach(['sans la saisie rapide' => FALSE, 'en saisie rapide' => TRUE] as $case => $quick) {

			[$document] = $this->render($quick);

			[$data, $instructions] = $this->call('doHarvest', [
				'cultivation' => $eCultivation['id'],
				'date' => \Clock::date(),
				'mode' => 'add',
				'harvestMore' => '3',
				'focusNext' => 1,
			], 'POST');

			$this->assertFalse($data->cCultivation->offsetExists($eCultivationOther['id']), $case.', la saisie relit une espèce qu\'elle ne change pas');
			$this->assertEquals([$eCultivation['id']], $data->change['updated'], $case.', la saisie redessine une autre ligne que la sienne');
			$this->assertEquals($eCultivationLast['id'], $data->eCultivationNext['id'] ?? NULL, $case.', le curseur ne passe pas à la série suivante de l\'espèce');

			$this->check($document, $instructions, 'saisie au milieu d\'une espèce ('.$case.')', quick: $quick);

		}

	}

	/**
	 * Après la dernière série de son espèce, le curseur passe à la première série de l'espèce qui suit à
	 * l'écran, que la liste relue ne porte pas : c'est celle d'un rechargement complet.
	 */
	public function testAroundLastFocusNext(): void {

		// L'écran range les espèces par nom en ordre naturel (`HarvestWeekLib::sortList()`), et `createPlant()` les
		// nomme d'un `uniqid()` hexadécimal, que cet ordre ne range pas comme la création.
		$cPlant = new \Collection([$this->createPlant(), $this->createPlant()]);
		$cPlant->sort(['name'], natural: TRUE);
		[$ePlant, $ePlantNext] = iterator_to_array($cPlant, FALSE);

		$this->createCultivationHarvested($this->createSeries('A, série de l\'espèce'), $ePlant);
		$eCultivation = $this->createCultivationHarvested($this->createSeries('B, série de l\'espèce'), $ePlant);

		$this->createCultivationHarvested($this->createSeries('A, série de l\'espèce suivante'), $ePlantNext);

		[$document, $dataBefore] = $this->render();

		$eCultivationExpected = HarvestWeekLib::getNext($dataBefore->cCultivation, $eCultivation);

		if($this->assertDecorPresent($eCultivationExpected->notEmpty() and $eCultivationExpected['plant']['id'] !== $ePlant['id'], 'une série d\'une autre espèce suit la dernière série de l\'espèce saisie') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '3',
			'focusNext' => 1,
		], 'POST');

		$this->assertFalse($data->cCultivation->offsetExists($eCultivationExpected['id']), 'la saisie relit l\'espèce suivante, que le cas veut hors de la liste relue');
		$this->assertEquals($eCultivationExpected['id'], $data->eCultivationNext['id'] ?? NULL, 'le curseur ne passe pas à la première série de l\'espèce suivante');

		$this->check($document, $instructions, 'saisie sur la dernière série d\'une espèce');

	}

	/**
	 * Une série mixte (deux espèces, une série) : ses deux lignes ont la même clé de nom et se distinguent par
	 * leurs variétés. Une seconde série de l'une des espèces entre dans la semaine : cette espèce compte deux
	 * lignes, sa ligne mixte montre sa semaine de départ, et la ligne de l'autre espèce, qui n'a rien reçu,
	 * perd ses variétés. Elle est relue et redessinée avec l'espèce saisie.
	 */
	public function testAroundMixedSeries(): void {

		[$ePlant, $eCultivationHomonym] = $this->createHomonyms($this->createSeries('Série mixte'));

		$this->checkHomonymEntering($ePlant, $eCultivationHomonym, 'série mixte');

	}

	/**
	 * Le même cas sur deux séries homonymes de saisons éloignées : la ligne de l'autre espèce est d'une saison
	 * hors des saisons voisines, listée parce qu'elle est récoltée cette semaine.
	 */
	public function testAroundHomonymOtherSeason(): void {

		[$ePlant, $eCultivationHomonym] = $this->createHomonyms($this->createSeries('Série homonyme'), $this->createSeries('Série homonyme', $this->getSeason() - 3));

		$this->checkHomonymEntering($ePlant, $eCultivationHomonym, 'homonyme d\'une autre saison');

	}

	/**
	 * Deux lignes d'espèces différentes, de même nom de série et sans planche, distinguées par leurs variétés :
	 * la première sur `$eSeries`, prévue cette semaine, la seconde sur `$eSeriesOther` (la même série par
	 * défaut), récoltée aujourd'hui. Chacune a une semaine de départ, que l'écran ne montre que quand son espèce
	 * compte plusieurs lignes.
	 *
	 * @return array [l'espèce de la première, la ligne de la seconde]
	 */
	private function createHomonyms(\series\Series $eSeries, \series\Series $eSeriesOther = new \series\Series()): array {

		$ePlant = $this->createPlant();
		$ePlantOther = $this->createPlant();

		$eCultivation = $this->createCultivationHarvested($eSeries, $ePlant);

		$eCultivationOther = $this->createCultivation($eSeriesOther->notEmpty() ? $eSeriesOther : $eSeries, $ePlantOther);
		$eTask = $this->createTaskHarvest($eCultivationOther, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 1.0, \Clock::date());

		foreach([$eCultivation, $eCultivationOther] as $eCultivationVarieties) {

			$this->createSlice($eCultivationVarieties, $this->createVariety($eCultivationVarieties['plant']));

			// Sans build(), qui n'a rien à vérifier : la semaine de départ n'est pas une saisie, la culture la
			// reçoit de ses interventions de semis ou de plantation, que le décor ne pose pas.
			$eCultivationVarieties['startWeek'] = 10;
			$eCultivationVarieties['startAction'] = \series\Cultivation::SOWING;
			\series\CultivationLib::update($eCultivationVarieties, ['startWeek', 'startAction']);

		}

		return [$ePlant, $eCultivationOther];

	}

	/**
	 * Une seconde série de `$ePlant`, hors de la semaine, y entre par « Nouvelle récolte » : la ligne
	 * homonyme de l'autre espèce perd ses variétés, et l'écran est celui d'un rechargement.
	 */
	private function checkHomonymEntering(\plant\Plant $ePlant, \series\Cultivation $eCultivationHomonym, string $case): void {

		$eCultivation = $this->createCultivation($this->createSeries('Seconde série de l\'espèce'), $ePlant);

		[$document, $data] = $this->render();

		if($this->assertDecorPresent($data->cCultivation->offsetExists($eCultivationHomonym['id']) and $data->cCultivation[$eCultivationHomonym['id']]['nameWithVarieties'], $case.' : la ligne homonyme est listée et se nomme par ses variétés') === FALSE) {
			return;
		}

		$this->assertFalse($data->cCultivation->offsetExists($eCultivation['id']), $case.' : la seconde série de l\'espèce est déjà listée');

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'add',
			'harvestMore' => '2',
			'panel' => 1,
		], 'POST');

		$this->assertTrue(array_key_exists($eCultivation['id'], $data->change['inserted']), $case.' : la seconde série de l\'espèce n\'entre pas');
		$this->assertTrue(in_array($eCultivationHomonym['id'], $data->change['updated'], TRUE), $case.' : la ligne homonyme de l\'autre espèce n\'est pas redessinée');
		$this->assertFalse($data->cCultivation[$eCultivationHomonym['id']]['nameWithVarieties'] ?? TRUE, $case.' : la ligne homonyme garde ses variétés');

		$this->check($document, $instructions, $case);

	}

	/**
	 * Une culture récoltée hors période, remise à zéro, sort de l'écran, et son espèce y reste par sa série
	 * prévue : la réponse retire la ligne et garde l'intertitre.
	 */
	public function testAroundRemovedKeepsSpecies(): void {

		$ePlant = $this->createPlant();

		$this->createCultivationHarvested($this->createSeries('A, série prévue de l\'espèce'), $ePlant);

		$eCultivation = $this->createCultivation($this->createSeries('B, série récoltée hors période'), $ePlant);
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		[$document] = $this->render();

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'replace',
			'harvestDay' => '0',
		], 'POST');

		$this->assertEquals([$eCultivation['id']], $data->change['removed'], 'la culture remise à zéro hors période reste à l\'écran');
		$this->assertEquals([], $data->change['sections']['removed'], 'l\'intertitre d\'une espèce qui garde une série quitte l\'écran');

		$this->check($document, $instructions, 'sortie d\'une série, l\'espèce reste');

	}

	/**
	 * La seule culture de la semaine, remise à zéro, la vide : la liste relue sur son espèce est vide, et seule
	 * la semaine entière dit que l'écran passe au bloc vide.
	 */
	public function testAroundWeekEmptied(): void {

		$eCultivation = $this->createCultivation($this->createSeries('Seule série de la semaine'), $this->createPlant());
		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 2.0, \Clock::date());

		[$document, $data] = $this->render();

		if($this->assertDecorPresent($data->cCultivation->getIds() === [$eCultivation['id']], 'la semaine du décor ne porte que la culture saisie') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->call('doHarvest', [
			'cultivation' => $eCultivation['id'],
			'date' => \Clock::date(),
			'mode' => 'replace',
			'harvestDay' => '0',
		], 'POST');

		$this->assertTrue($data->change['list'], 'la semaine vidée ne se rend pas entière');

		$this->check($document, $instructions, 'semaine vidée');

	}

	private function checkCustomize(\Dom\HTMLDocument $before, array $instructions, string $case): void {

		$this->assertTrue($instructions !== [], $case.' : le geste n\'a rendu aucune instruction');

		$this->apply($before, $instructions);

		$expected = $this->getFingerprint($this->renderCustomize()[0]);
		$actual = $this->getFingerprint($before);

		$this->assertTrue($expected === $actual, $case.' : empreinte après le geste et après rechargement'.$this->getGap($expected, $actual));

	}

	/**
	 * R-Retours-0410, point 18 · Sur un écran sans aucune intervention, la première créée rend l'écran entier,
	 * qui retrouve la rangée des outils ; la supprimer le vide de nouveau, et la rangée repart. Les deux
	 * réponses se comparent au rechargement, rangée comprise.
	 */
	public function testTaskEmpty(): void {

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
		]);

		$this->assertEquals('screen', $data->change, 'la première intervention d\'un écran vide ne rend pas l\'écran entier');
		$this->checkTasks($document, $instructions, 'première intervention d\'un écran vide');

		[$data, $instructions] = $this->callWeek('doBatchDelete', 'doBatch', ['ids' => $data->cTaskCreated->getIds()]);

		$this->assertEquals('screen', $data->change, 'la suppression de la seule intervention ne rend pas l\'écran entier');
		$this->checkTasks($document, $instructions, 'suppression de la seule intervention');

	}

	/**
	 * Lot CL, retour 296 · Le pense-bête allumé, la première intervention d'un écran vide et sa suppression
	 * rendent l'écran entier (`screen`). Une semaine que « Masquer ce qui est fait » vide au rendu (la carte
	 * cochée sous le masque restant jusqu'au rechargement, `testTaskHidden()`) se remplit par une intervention
	 * neuve. Chaque réponse se compare au rechargement.
	 */
	public function testTaskEmptyReminder(): void {

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

		[$document] = $this->renderTasks();

		$create = [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
		];

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', $create);

		$this->assertEquals('screen', $data->change, 'la première intervention d\'un écran vide ne rend pas l\'écran entier');
		$this->checkTasks($document, $instructions, 'première intervention d\'un écran vide, pense-bête allumé');

		$eTaskDone = $data->cTaskCreated->first();

		$this->callTask('doDone', $eTaskDone);

		$this->setHideDone(TRUE);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', $create);

		$this->assertEquals('board', $data->change, 'le premier bloc d\'une semaine vidée par le masque ne rend pas les blocs');
		$this->checkTasks($document, $instructions, 'intervention neuve sur une semaine vidée par le masque, pense-bête allumé');

		$this->setHideDone(FALSE);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doBatchDelete', 'doBatch', ['ids' => [$eTaskDone['id'], $data->cTaskCreated->first()['id']]]);

		$this->assertEquals('screen', $data->change, 'la suppression des seules interventions ne rend pas l\'écran entier');
		$this->checkTasks($document, $instructions, 'suppression des seules interventions, pense-bête allumé');

	}

	/**
	 * Un écran que « Non planifié » seul remplit n'est pas vide : la première intervention de la semaine y
	 * ouvre son bloc, sans rendre l'écran entier.
	 */
	public function testTaskEmptyUnplanned(): void {

		$this->createTask($this->getCultivation(), ACTION_TRAITEMENT);

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callWeek('doCreate', 'doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
		]);

		$this->assertEquals('board', $data->change, 'la première intervention de la semaine, « Non planifié » porteur, ne rend pas les seuls blocs');
		$this->checkTasks($document, $instructions, 'première intervention de la semaine, « Non planifié » porteur');

	}

	/**
	 * « Quand » sur une autre semaine que l'écran (piste F de R-CreationRapide) : la réponse ciblée laisse la
	 * Semaine, sous ses trois vues, et le Jour comme leur rechargement, que l'intervention parte la semaine
	 * suivante, que l'écran ne montre pas, ou la précédente à faire, qui entre dans « En retard ».
	 */
	public function testCreateOtherWeek(): void {

		$this->createTask($this->getCultivation(), ACTION_TRAITEMENT);

		$weeks = [
			'semaine suivante' => TaskDayLib::getNextWeek(\Clock::week()),
			'semaine précédente' => toWeek(week_date_starts(\Clock::week()).' - 7 days'),
		];

		foreach([\structure\Member::CARDS, \structure\Member::GRID, \structure\Member::COLUMNS] as $view) {

			\structure\MemberLib::setView('viewPlanningWeek', \structure\StructureLib::getById($this->eStructure['id']), $view);

			foreach($weeks as $case => $when) {

				[$document] = $this->renderTasks();
				[, $instructions] = $this->callWeek('doCreate', 'doCreate', $this->getInputOtherWeek($when));
				$this->checkTasks($document, $instructions, 'Semaine en '.$view.', intervention de la '.$case);

			}

		}

		foreach($weeks as $case => $when) {

			[$document] = $this->renderTaskDay();
			[, $instructions] = $this->callDay('doCreate', $this->getInputOtherWeek($when));
			$this->checkDay($document, $instructions, 'Jour, intervention de la '.$case);

		}

	}

	/** Une plantation à faire sans jour, sur la semaine choisie sous « Quand ». */
	private function getInputOtherWeek(string $when): array {

		return [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
			'when' => $when,
		];

	}

	/**
	 * Le même aller et retour au Jour : la première intervention, posée sur mercredi, rend l'écran entier,
	 * et la supprimer le vide de nouveau.
	 */
	public function testDayEmpty(): void {

		[$document] = $this->renderTaskDay();

		[$data, $instructions] = $this->callDay('doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => week_date_day(\Clock::week(), 3),
		]);

		$this->assertEquals('screen', $data->change, 'la première intervention du Jour vide ne rend pas l\'écran entier');
		$this->checkDay($document, $instructions, 'première intervention du Jour vide');

		[$data, $instructions] = $this->callDay('doBatchDelete', ['ids' => $data->cTaskCreated->getIds()]);

		$this->assertEquals('screen', $data->change, 'la suppression de la seule intervention du Jour ne rend pas l\'écran entier');
		$this->checkDay($document, $instructions, 'suppression de la seule intervention du Jour');

	}

	/**
	 * « J'ai aussi fait » sur un Jour vide : le geste crée la première intervention de la semaine, l'écran se
	 * rend entier et retrouve la rangée des outils (`planningTaskDayBatch()`, geste `create`).
	 */
	public function testDayEmptyAlso(): void {

		[$document] = $this->renderTaskDay();

		[$data, $instructions, $action] = $this->callDay('doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'status' => \series\Task::DONE,
			'day' => \Clock::date(),
			'time' => [$this->getMember()['id'] => '0.5'],
		]);

		if($this->assertTrue($action instanceof \ViewAction, '« J\'ai aussi fait » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals('screen', $data->change, '« J\'ai aussi fait » sur un Jour vide ne rend pas l\'écran entier');
		$this->checkDay($document, $instructions, 'aussi fait sur un Jour vide');

	}

	/**
	 * Une semaine que seule une intervention « En retard » remplit : cochée, elle est faite dans sa semaine et
	 * quitte l'écran, qui se vide. La réponse rend l'écran entier, sans la rangée des outils, comme le
	 * rechargement.
	 */
	public function testTaskEmptyLateDone(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTasks();

		[$data, $instructions] = $this->callTask('doDone', $eTask);

		$this->assertEquals('screen', $data->change, 'la coche de la seule intervention, en retard, ne rend pas l\'écran entier');
		$this->checkTasks($document, $instructions, 'coche de la seule intervention, en retard');

	}

	/**
	 * Le Jour des interventions (L5c), les deux preuves : un rendu comparé à lui-même est égal, un rendu
	 * changé par une intervention ajoutée à « Sans jour » diverge.
	 */
	public function testDayProofs(): void {

		[$document] = $this->renderTaskDay();

		$this->assertEquals($this->getFingerprint($document), $this->getFingerprint($this->renderTaskDay()[0]), 'le même rendu du Jour, neutralisé, diffère de lui-même');

		$this->createTaskWeek();

		$this->assertTrue($this->getFingerprint($document) !== $this->getFingerprint($this->renderTaskDay()[0]), 'une intervention ajoutée entre deux rendus du Jour ne change pas l\'empreinte');

	}

	/**
	 * Le menu du jour : une carte de « Sans jour » posée sur samedi, puis rendue à « Sans jour », puis
	 * reportée à la semaine suivante. Chaque réponse ne redessine que les colonnes que l'intervention
	 * quitte ou rejoint.
	 */
	public function testDayPlanned(): void {

		$this->createTaskWitness();

		$eTask = $this->createTaskWeek();
		$saturday = week_date_day(\Clock::week(), 6);

		[$document] = $this->renderTaskDay();

		[$data, $instructions] = $this->callDay('doPlanned', ['task' => $eTask['id'], 'planned' => ['date' => $saturday]]);
		$this->assertEquals([TaskDayLib::NONE, $saturday], $data->change, 'le jour posé ne redessine pas « Sans jour » et samedi');
		$this->checkDay($document, $instructions, 'jour posé');

		[$data, $instructions] = $this->callDay('doPlanned', ['task' => $eTask['id'], 'planned' => ['week' => \Clock::week()]]);
		$this->assertEquals([TaskDayLib::NONE, $saturday], $data->change, 'le jour retiré ne redessine pas « Sans jour » et samedi');
		$this->checkDay($document, $instructions, 'jour retiré');

		[$data, $instructions] = $this->callDay('doPlanned', ['task' => $eTask['id'], 'planned' => ['week' => TaskDayLib::getNextWeek(\Clock::week())]]);
		$this->assertEquals([TaskDayLib::NONE], $data->change, 'le report ne redessine pas « Sans jour » seule');
		$this->checkDay($document, $instructions, 'report');

	}

	/**
	 * Le pense-bête du Jour (CC), dans des colonnes que les gestes de carte redessinent : la coche, le jour
	 * posé et la coche d'un lot rendent des colonnes entières, qui doivent garder leurs rappels. Puis les
	 * gestes du pense-bête, qui ne redessinent que les rappels des colonnes touchées : la coche, le
	 * changement de jour, la coche d'un resté et l'effacement. Chaque réponse est comparée au rechargement.
	 */
	public function testDayReminder(): void {

		$this->createTaskWitness();

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

		$week = \Clock::week();
		$weekBefore = toWeek(week_date_starts($week).' -7 days');
		$tuesday = week_date_day($week, 2);
		$wednesday = week_date_day($week, 3);

		$eReminder = $this->createDayReminder($week, 'Mardi', $tuesday);
		$eReminderNone = $this->createDayReminder($week, 'Sans jour');
		$eReminderBefore = $this->createDayReminder($weekBefore, 'Resté');

		$eTaskTuesday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week, $tuesday);
		$eTaskWednesday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week, $wednesday);
		$eTaskNone = $this->createTaskWeek();

		[$document] = $this->renderTaskDay();

		[, $instructions] = $this->callDay('doDone', ['task' => $eTaskTuesday['id'], 'column' => $tuesday]);
		$this->checkDay($document, $instructions, 'coche d\'une carte sous un rappel');

		[, $instructions] = $this->callDay('doPlanned', ['task' => $eTaskNone['id'], 'planned' => ['date' => $tuesday]]);
		$this->checkDay($document, $instructions, 'jour posé dans une colonne qui porte un rappel');

		[, $instructions] = $this->callDay('doBatchDone', ['ids' => [$eTaskWednesday['id']]]);
		$this->checkDay($document, $instructions, 'coche d\'un lot');

		$this->checkDay($document, $this->callDayReminder('doReminderCheck', ['reminder' => $eReminder['id']]), 'coche d\'un rappel');
		$this->checkDay($document, $this->callDayReminder('doReminderUpdate', ['reminder' => $eReminder['id'], 'text' => 'Vendredi', 'date' => week_date_day($week, 5)]), 'rappel passé au vendredi');
		$this->checkDay($document, $this->callDayReminder('doReminderCheck', ['reminder' => $eReminderBefore['id']]), 'coche d\'un resté');
		$this->checkDay($document, $this->callDayReminder('doReminderDelete', ['reminder' => $eReminderNone['id']]), 'effacement du dernier rappel sans jour');

	}

	/**
	 * Une semaine vide qui porte un rappel : la première intervention rend l'écran entier, avec son pense-bête.
	 */
	public function testDayReminderScreen(): void {

		\structure\MemberLib::setView('viewPlanningReminders', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

		$week = toWeek(week_date_starts(\Clock::week()).' +70 days');

		$this->createDayReminder($week, 'Semaine vide', week_date_day($week, 3));

		[$document] = $this->renderTaskDay(week: $week);

		[$data, $instructions] = $this->callDay('doCreate', [
			'week' => $week,
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => week_date_day($week, 3),
		]);

		$this->assertEquals('screen', $data->change, 'la première intervention d\'une semaine vide ne rend pas l\'écran entier');
		$this->checkDay($document, $instructions, 'première intervention sous un rappel', week: $week);

	}

	/**
	 * La coche d'une carte de « Sans jour » dans la semaine courante : elle passe faite aujourd'hui, va dans
	 * la colonne d'aujourd'hui, et reste dans « Sans jour » jusqu'au rechargement, sous la main qui l'a
	 * cochée (R-Jour-Fait). La décoche de la carte gardée la rend à « Sans jour ».
	 */
	public function testDayDoneTodo(): void {

		$eTask = $this->createTaskWeek(timeExpected: 1.0);

		[$document] = $this->renderTaskDay();
		$this->checkMark($document, $eTask, FALSE, 'Jour avant la coche');

		[$data, $instructions] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => TaskDayLib::NONE]);
		$this->assertEquals([TaskDayLib::NONE, \Clock::date()], $data->change, 'la coche ne redessine pas « Sans jour » et aujourd\'hui');
		$this->assertEquals(\Clock::date(), \series\TaskLib::getById($eTask['id'])['doneDate'], 'la coche ne date plus l\'intervention d\'aujourd\'hui en base');
		$this->checkDay($document, $instructions, 'coche', tasksKept: [$eTask['id']]);
		$this->checkMark($document, $eTask, TRUE, 'Jour après la coche');

		[$data, $instructions] = $this->callDay('doTodo', ['task' => $eTask['id'], 'column' => TaskDayLib::NONE]);
		$this->checkDay($document, $instructions, 'décoche de la carte gardée');
		$this->checkMark($document, $eTask, FALSE, 'Jour après la décoche');

	}

	/**
	 * La réponse à la coche d'une carte de « Sans jour » porte deux cartes : la carte gardée, avec son
	 * rond coché et sans case de sélection, et celle d'aujourd'hui, qui porte la case. La décoche de l'une ou de
	 * l'autre ne laisse ensuite rien de faux, comparé au même écran rechargé ; les comptes des têtes sont
	 * dans cette comparaison.
	 */
	public function testDayDoneKeptToday(): void {

		foreach([TaskDayLib::NONE => 'carte gardée', \Clock::date() => 'carte d\'aujourd\'hui'] as $columnTodo => $case) {

			$eTask = $this->createTaskWeek(timeExpected: 1.0);

			[$document] = $this->renderTaskDay();

			[$data, $instructions] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => TaskDayLib::NONE]);
			$this->apply($document, $instructions);

			$cardKept = $document->getElementById('planning-task-'.$eTask['id'].'-'.TaskDayLib::NONE);
			$cardToday = $document->getElementById('planning-task-'.$eTask['id'].'-'.\Clock::date());

			if(
				$this->assertTrue($cardKept !== NULL, $case.' : la carte cochée quitte « Sans jour »') === FALSE or
				$this->assertTrue($cardToday !== NULL, $case.' : la carte cochée manque à la colonne d\'aujourd\'hui') === FALSE
			) {
				continue;
			}

			$this->assertTrue($cardKept->querySelector('.planning-task-mark-done[data-planning-task-todo="'.$eTask['id'].'"]') !== NULL, $case.' : la carte gardée ne porte pas le rond coché');
			$this->assertTrue($cardKept->querySelector('[data-dropdown-id]') === NULL, $case.' : la carte gardée porte un menu');
			$this->assertEquals([FALSE, TRUE], [$data->columns[TaskDayLib::NONE]['cTask'][$eTask['id']]['main'], $data->columns[\Clock::date()]['cTask'][$eTask['id']]['main']], $case.' : la case de sélection n\'est pas sur la seule carte d\'aujourd\'hui');

			[, $instructions] = $this->callDay('doTodo', ['task' => $eTask['id'], 'column' => $columnTodo]);
			$this->checkDay($document, $instructions, 'décoche de la '.$case);
			$this->checkMark($document, $eTask, FALSE, 'Jour après la décoche de la '.$case);

		}

	}

	/**
	 * La carte gardée porte, sous son pied fait, un pied d'avant la coche, inerte. Ce pied se déduit du temps
	 * qu'avait la personne avant la coche (`data-kept`), que la coche
	 * change en y écrivant le prévu si elle n'en avait pas, qu'une autre personne en ait eu ou non. Il tient
	 * d'un geste à l'autre : le temps ressaisi sur la copie du jour ne le change pas, ni une seconde coche,
	 * qui redessine la colonne.
	 */
	public function testDayDoneKeptBefore(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre, dont le temps précède la coche') === FALSE) {
			return;
		}

		foreach([
			'prévu seul' => [new \user\User(), '0'],
			'mon temps et prévu' => [$this->getMember(), '0.75'],
			'temps d\'un autre et prévu' => [$eUserOther, '0'],
		] as $case => [$eUserBefore, $timeBefore]) {

			$eTask = $this->createTaskWeek(timeExpected: 1.5);
			$eTaskOther = $this->createTaskWeek();

			if($eUserBefore->notEmpty()) {
				$this->createTimesheetFor($eTask, $eUserBefore, 0.75, week_date_day(\Clock::week(), 1));
			}

			[$document] = $this->renderTaskDay();

			[, $instructions] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => TaskDayLib::NONE]);
			$this->apply($document, $instructions);

			// La personne corrige ensuite son temps sur la copie du jour, ce qui ne change rien à l'avant de la coche.
			[, $instructions] = $this->callDay('doTime', ['task' => $eTask['id'], 'column' => \Clock::date(), 'date' => \Clock::date(), 'time' => '2', 'kept' => $this->getKept($document)]);
			$this->apply($document, $instructions);

			// Chaque réponse redessine la carte gardée : une écriture faite à chaque rendu laisserait deux relevés
			// égaux. L'horloge avancée d'une seconde fait voir la date de mise à jour que pose toute écriture par le
			// Lib, et les valeurs que les gestes ont posées celles qui passeraient à côté.
			$rowsDone = $this->getRows($eTask);
			// La coche date l'intervention du dernier jour où elle porte du temps, sinon d'aujourd'hui.
			$dateDone = $eUserBefore->notEmpty() ? week_date_day(\Clock::week(), 1) : \Clock::date();

			$this->setClock(date('Y-m-d H:i:s', \Clock::shift('+1 second')));

			[, $instructions] = $this->callDay('doDone', ['task' => $eTaskOther['id'], 'column' => TaskDayLib::NONE, 'kept' => $this->getKept($document)]);
			$this->apply($document, $instructions);

			$this->assertEquals($rowsDone, $this->getRows($eTask), $case.' : redessiner la carte gardée a écrit sur l\'intervention ou sur ses fiches de temps');

			$eTaskAfter = \series\Task::model()
				->select(['status', 'timeExpected', 'doneDate', 'plannedDate'])
				->whereId($eTask['id'])
				->get();

			$this->assertEquals(
				[\series\Task::DONE, 1.5, $dateDone, NULL],
				[$eTaskAfter['status'], $eTaskAfter['timeExpected'], $eTaskAfter['doneDate'], $eTaskAfter['plannedDate']],
				$case.' : l\'intervention ne porte plus les valeurs que ses gestes ont écrites ('.json_encode([$eTaskAfter['status'], $eTaskAfter['timeExpected'], $eTaskAfter['doneDate'], $eTaskAfter['plannedDate']]).')'
			);

			$cardKept = $document->getElementById('planning-task-'.$eTask['id'].'-'.TaskDayLib::NONE);

			if($this->assertTrue($cardKept !== NULL, $case.' : la carte cochée quitte « Sans jour » à la coche suivante') === FALSE) {
				continue;
			}

			$this->assertEquals($timeBefore, $cardKept->getAttribute('data-kept'), $case.' : la carte gardée ne porte pas mon temps d\'avant la coche');

			$footBefore = $cardKept->querySelector('.planning-task-foot-kept > .planning-task-foot.not-visible');

			if($this->assertTrue($footBefore !== NULL, $case.' : la carte gardée n\'a pas de pied d\'avant la coche') === FALSE) {
				continue;
			}

			$this->checkBefore($footBefore, $case.', pied');

			// Le groupe du haut d'avant la coche réserve sa place sur la ligne du nom dès qu'il y avait du temps avant
			// elle, et pas sinon : seul le prévu, resté au pied, précède alors la coche.
			$topBefore = $cardKept->querySelector('.planning-task-name > .planning-task-top-kept > .planning-task-top.not-visible');

			if($eUserBefore->empty()) {
				$this->assertTrue($topBefore === NULL, $case.' : la carte gardée a un groupe du haut d\'avant la coche sans temps avant elle');
			} else if($this->assertTrue($topBefore !== NULL, $case.' : la carte gardée n\'a pas de groupe du haut d\'avant la coche') === TRUE) {
				$this->checkBefore($topBefore, $case.', groupe du haut');
			}

			// La personne qui coche rejoint toujours le groupe du haut d'après, même quand rien ne le précédait.
			$topAfter = $cardKept->querySelector('.planning-task-name > .planning-task-top-kept > .planning-task-top:not(.not-visible)');
			$this->assertTrue($topAfter !== NULL, $case.' : la carte gardée n\'a pas de groupe du haut d\'après la coche sur la ligne du nom');

		}

	}

	/**
	 * Une intervention sans série, sans espèce ni note, que la coche fait suivre de celle qui coche : la carte
	 * gardée porte un pied d'avant la coche, inerte, que le temps d'avant soit celui d'une autre personne ou
	 * qu'il n'y en ait aucun. La base de dev ne porte aucune intervention de ce genre.
	 */
	public function testDayDoneKeptBare(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$eUserOther = $this->getMemberOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' a un second membre, dont le temps précède la coche') === FALSE) {
			return;
		}

		foreach([
			'sans titre, le temps d\'une autre personne' => $eUserOther,
			'titrée par l\'action' => new \user\User(),
		] as $case => $eUserBefore) {

			$eTask = $this->createTaskBare(1.5);

			if($eUserBefore->notEmpty()) {
				$this->createTimesheetFor($eTask, $eUserBefore, 0.75, week_date_day(\Clock::week(), 1));
			}

			[$document] = $this->renderTaskDay();

			$cardTodo = $document->getElementById('planning-task-'.$eTask['id'].'-'.TaskDayLib::NONE);

			if($this->assertTrue($cardTodo !== NULL, $case.' : la carte à faire manque à « Sans jour »') === FALSE) {
				continue;
			}

			[, $instructions] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => TaskDayLib::NONE]);
			$this->apply($document, $instructions);

			$cardKept = $document->getElementById('planning-task-'.$eTask['id'].'-'.TaskDayLib::NONE);

			if($this->assertTrue($cardKept !== NULL, $case.' : la carte cochée quitte « Sans jour »') === FALSE) {
				continue;
			}

			$footBefore = $cardKept->querySelector('.planning-task-foot-kept > .planning-task-foot.not-visible');

			if($this->assertTrue($footBefore !== NULL, $case.' : la carte gardée n\'a pas de pied d\'avant la coche') === FALSE) {
				continue;
			}

			$this->checkBefore($footBefore, $case);

		}

	}

	/**
	 * Trois coches de suite dans « Sans jour », chacune réémettant les cartes que la colonne garde déjà,
	 * comme l'écran (`PlanningTaskDay.getBody()`) : aucune carte ne quitte la colonne ni n'y change de
	 * rang, la coche suivante pas plus que la première. Puis chacune se remet à faire depuis sa carte
	 * gardée. Après chaque geste, l'écran est celui du rechargement, aux cartes gardées près.
	 */
	public function testDayDoneKeptSeveral(): void {

		$cTask = new \Collection();

		foreach([1.0, 0.0, 0.5] as $timeExpected) {
			$eTask = $this->createTaskWeek(timeExpected: $timeExpected);
			$cTask[$eTask['id']] = $eTask;
		}

		[$document] = $this->renderTaskDay();

		$order = $this->getColumnTasks($document, TaskDayLib::NONE);

		foreach(['coche', 'décoche'] as $gesture) {

			foreach($cTask as $eTask) {

				$case = $gesture.' de '.$eTask['id'].' après '.count($this->getKept($document)).' carte(s) gardée(s)';

				[$data, $instructions] = $this->callDay($gesture === 'coche' ? 'doDone' : 'doTodo', [
					'task' => $eTask['id'],
					'column' => TaskDayLib::NONE,
					'kept' => $this->getKept($document),
				]);

				$this->assertTrue(in_array(TaskDayLib::NONE, $data->change, TRUE), $case.' : « Sans jour » n\'est pas redessinée');

				$this->checkDay(
					\Dom\HTMLDocument::createFromString($document->saveHtml(), LIBXML_NOERROR),
					$instructions,
					$case,
					tasksKept: array_values(array_filter($order, fn(int $task) => ($cTask->offsetExists($task) and \series\TaskLib::getById($task)->isDone())))
				);

				$this->apply($document, $instructions);
				$this->assertEquals($order, $this->getColumnTasks($document, TaskDayLib::NONE), $case.' : une carte de « Sans jour » a disparu ou changé de rang');

			}

		}

		$this->assertEquals([], $this->getKept($document), 'une carte remise à faire reste gardée');

	}

	/**
	 * « Fait ! » en lot sur des cartes de « Sans jour » les y garde, faites, au même rang, avec celle qu'une
	 * coche y gardait déjà (R-Lot-Garde). Les gestes de lot suivants réémettent les cartes gardées comme
	 * l'écran (`PlanningTaskDay.emitKept()`) : celles que le lot prend suivent le geste, les autres restent.
	 * Même chose sous le masque, dont la copie d'aujourd'hui suit le rechargement, et dans une semaine passée,
	 * où la coche n'a aucun jour.
	 */
	public function testDayBatchDoneKept(): void {

		foreach([
			'semaine courante' => [FALSE, \Clock::week()],
			'sous le masque' => [TRUE, \Clock::week()],
			'semaine passée' => [FALSE, toWeek(\Clock::date().' - 14 DAYS')],
		] as $caseWeek => [$hide, $week]) {

			$this->setHideDone($hide);

			$eTaskSingle = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
			$eTaskFirst = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
			$eTaskSecond = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
			$eTaskThird = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
			$eTaskSaturday = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week, week_date_day($week, 6));

			[$document] = $this->renderTaskDay(week: $week);

			[, $instructions] = $this->callDay('doDone', ['task' => $eTaskSingle['id'], 'week' => $week, 'column' => TaskDayLib::NONE]);
			$this->apply($document, $instructions);

			$order = $this->getColumnTasks($document, TaskDayLib::NONE);

			$gestures = [
				'« Fait ! » en lot' => ['doBatchDone', [$eTaskFirst, $eTaskSecond, $eTaskSaturday], [], [$eTaskSingle, $eTaskFirst, $eTaskSecond]],
				'« À faire » en lot sur une carte gardée' => ['doBatchTodo', [$eTaskFirst], [], [$eTaskSingle, $eTaskSecond]],
				'« Affecter » en lot hors des cartes gardées' => ['doBatchUser', [$eTaskThird], ['user' => $this->getMember()['id'], 'action' => 'add'], [$eTaskSingle, $eTaskSecond]],
				'« Supprimer » en lot une carte gardée' => ['doBatchDelete', [$eTaskSecond], [], [$eTaskSingle]],
			];

			foreach($gestures as $caseGesture => [$route, $tasks, $input, $tasksExpected]) {

				$case = $caseWeek.', '.$caseGesture;

				[$data, $instructions, $action] = $this->callDay($route, $input + [
					'ids' => array_map(fn(\series\Task $eTask) => $eTask['id'], $tasks),
					'week' => $week,
					'kept' => $this->getKept($document),
				]);

				if($this->assertTrue($action instanceof \ViewAction, $case.' : le geste est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
					continue 2;
				}

				$this->assertTrue(in_array(TaskDayLib::NONE, $data->change, TRUE), $case.' : « Sans jour » n\'est pas redessinée');

				$ids = array_map(fn(\series\Task $eTask) => $eTask['id'], $tasksExpected);
				$tasksKept = array_values(array_filter($order, fn(int $task) => in_array($task, $ids, TRUE)));

				$this->checkDay(\Dom\HTMLDocument::createFromString($document->saveHtml(), LIBXML_NOERROR), $instructions, $case, tasksKept: $tasksKept, week: $week);

				$this->apply($document, $instructions);

			}

			$this->assertEquals([$eTaskSingle['id']], array_keys($this->getKept($document)), $caseWeek.' : après les quatre gestes, « Sans jour » ne garde pas la seule carte cochée à l\'unité');

		}

	}

	/**
	 * « Temps » sur un lot du Jour : les interventions de « Sans jour » que leur part du total passe faites y
	 * restent gardées, comme par « Fait ! » en lot, et vont dans la colonne d'aujourd'hui.
	 */
	public function testDayBatchTime(): void {

		$eTaskFirst = $this->createTaskWeek();
		$eTaskSecond = $this->createTaskWeek();

		[$document] = $this->renderTaskDay();

		[$data, $instructions, $action] = $this->callDay('doBatchTime', [
			'ids' => [$eTaskFirst['id'], $eTaskSecond['id']],
			'date' => \Clock::date(),
			'time' => '2',
			'kept' => $this->getKept($document),
		]);

		if($this->assertTrue($action instanceof \ViewAction, '« Temps » du Jour est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([TaskDayLib::NONE, \Clock::date()], $data->change, '« Temps » du Jour ne redessine pas « Sans jour » et aujourd\'hui');
		$this->checkDay($document, $instructions, 'temps en lot', tasksKept: [$eTaskFirst['id'], $eTaskSecond['id']]);

	}

	/**
	 * Le second geste de lot qui suit « Fait ! » en lot ne poste que la carte cochée après lui, et les deux
	 * cartes faites restent gardées. Le formulaire périmé qui postait les cartes faites (contre-recette de
	 * R-Lot-Garde) est côté client : ce test tient la réponse, Chrome tient le formulaire.
	 */
	public function testDayBatchDoneThenPlanned(): void {

		foreach([
			'semaine courante' => FALSE,
			'sous le masque' => TRUE,
		] as $caseWeek => $hide) {

			$this->setHideDone($hide);

			$week = \Clock::week();
			$saturday = week_date_day($week, 6);

			$eTaskFirst = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
			$eTaskSecond = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
			$eTaskThird = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

			[$document] = $this->renderTaskDay(week: $week);

			$order = $this->getColumnTasks($document, TaskDayLib::NONE);
			$tasksKept = array_values(array_filter($order, fn(int $task) => in_array($task, [$eTaskFirst['id'], $eTaskSecond['id']], TRUE)));

			$gestures = [
				'« Fait ! » en lot' => ['doBatchDone', []],
				'« Planifier · Cette semaine » sur la carte cochée ensuite' => ['doBatchPlannedWeek', ['plannedWeek' => $week]],
				'« Planifier · Samedi » sur la même carte' => ['doBatchPlannedDate', ['plannedDate' => $saturday]],
			];

			foreach($gestures as $caseGesture => [$route, $input]) {

				$case = $caseWeek.', '.$caseGesture;
				$tasks = ($route === 'doBatchDone') ? [$eTaskFirst, $eTaskSecond] : [$eTaskThird];

				[, $instructions, $action] = $this->callDay($route, $input + [
					'ids' => array_map(fn(\series\Task $eTask) => $eTask['id'], $tasks),
					'week' => $week,
					'kept' => $this->getKept($document),
				]);

				if($this->assertTrue($action instanceof \ViewAction, $case.' : le geste est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
					continue 2;
				}

				$this->checkDay(\Dom\HTMLDocument::createFromString($document->saveHtml(), LIBXML_NOERROR), $instructions, $case, tasksKept: $tasksKept, week: $week);

				$this->apply($document, $instructions);

			}

			$this->assertEquals($saturday, \series\Task::model()->whereId($eTaskThird)->getValue('plannedDate'), $caseWeek.' : la carte cochée après « Fait ! » n\'est pas posée sur samedi');

		}

	}

	/**
	 * Le champ du temps d'une carte faite, sur un jour passé de la semaine : la carte suit, et la tête de
	 * la colonne avec elle.
	 */
	public function testDayTime(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$monday = week_date_day(\Clock::week(), 1);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);

		[$document] = $this->renderTaskDay();

		[$data, $instructions, $action] = $this->callDay('doTime', ['task' => $eTask['id'], 'date' => $monday, 'time' => '1.5']);

		if($this->assertTrue($action instanceof \ViewAction, 'le temps du jour est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$monday], $data->change, 'le temps d\'un jour redessine d\'autres colonnes que la sienne');
		$this->checkDay($document, $instructions, 'temps');

	}

	/**
	 * Le temps écrit un jour ne redessine que les colonnes qu'il change (`TaskDayLib::getChangeForTime()`),
	 * une colonne de jour ne montrant que son jour (retour 360). Une intervention faite mercredi :
	 * - avec 1h lundi et 0h30 mardi de la personne connectée, lundi vidé fait passer la case de sélection
	 *   à mardi : lundi et mardi, pas mercredi ;
	 * - avec 1h mardi, un temps posé lundi par une date forgée, que la route accepte dans la semaine, y fait
	 *   entrer la carte et la case : lundi et mardi, pas mercredi ;
	 * - avec le seul temps d'une autre personne mardi, la personne connectée qui entre dans la semaine change
	 *   la recherche de chaque carte (`TaskSearchLib::getValues()`) : mardi et mercredi.
	 * La carte gardée dans « Sans jour » porte la semaine : son temps écrit aujourd'hui la redessine aussi.
	 */
	public function testDayTimeColumns(): void {

		if($this->assertTrue($this->eStructure->hasTime(), 'la structure '.static::STRUCTURE.' suit le temps de travail') === FALSE) {
			return;
		}

		$monday = week_date_day(\Clock::week(), 1);
		$tuesday = week_date_day(\Clock::week(), 2);
		$wednesday = week_date_day(\Clock::week(), 3);

		$eTaskLeaves = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $wednesday);
		$this->createTimesheet($eTaskLeaves, 1.0, $monday);
		$this->createTimesheet($eTaskLeaves, 0.5, $tuesday);

		[$document] = $this->renderTaskDay();

		[$data, $instructions, $action] = $this->callDay('doTime', ['task' => $eTaskLeaves['id'], 'column' => $monday, 'date' => $monday, 'time' => '0']);

		if($this->assertTrue($action instanceof \ViewAction, 'le temps vidé lundi est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$monday, $tuesday], $data->change, 'le temps vidé lundi ne redessine pas lundi et mardi seuls');
		$this->checkDay($document, $instructions, 'temps vidé sur la première colonne');

		$eTaskForged = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $wednesday);
		$this->createTimesheet($eTaskForged, 1.0, $tuesday);

		[$document] = $this->renderTaskDay();

		[$data, $instructions] = $this->callDay('doTime', ['task' => $eTaskForged['id'], 'column' => $wednesday, 'date' => $monday, 'time' => '0.5']);
		$this->assertEquals([$monday, $tuesday], $data->change, 'le temps posé lundi par une date forgée ne redessine pas lundi et mardi seuls');
		$this->checkDay($document, $instructions, 'temps posé sur un jour antérieur');

		$eUserOther = $this->getUserOther();

		if($this->assertTrue($eUserOther->notEmpty(), 'la structure '.static::STRUCTURE.' n\'a pas de second membre, pour le temps d\'une autre personne') === FALSE) {
			return;
		}

		$eTaskEnters = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $wednesday);
		$this->createTimesheetFor($eTaskEnters, $eUserOther, 1.0, $tuesday);

		[$document] = $this->renderTaskDay();

		[$data, $instructions] = $this->callDay('doTime', ['task' => $eTaskEnters['id'], 'column' => $wednesday, 'date' => $wednesday, 'time' => '1']);
		$this->assertEquals([$tuesday, $wednesday], $data->change, 'la personne qui entre dans la semaine ne redessine pas toutes les colonnes de l\'intervention');
		$this->checkDay($document, $instructions, 'personne qui entre dans la semaine');

		$eTaskKept = $this->createTaskWeek();
		$this->createTimesheet($eTaskKept, 0.25, $tuesday);

		[$document] = $this->renderTaskDay();

		[, $instructions] = $this->callDay('doDone', ['task' => $eTaskKept['id'], 'column' => TaskDayLib::NONE]);
		$this->apply($document, $instructions);

		[$data, $instructions] = $this->callDay('doTime', ['task' => $eTaskKept['id'], 'column' => \Clock::date(), 'date' => \Clock::date(), 'time' => '2', 'kept' => $this->getKept($document)]);
		$this->assertEquals([TaskDayLib::NONE, $tuesday, \Clock::date()], $data->change, 'le temps d\'aujourd\'hui sur la carte gardée ne redessine pas « Sans jour », mardi, sa première colonne, et aujourd\'hui seuls');
		$this->checkDay($document, $instructions, 'temps sur la carte gardée', tasksKept: [$eTaskKept['id']]);

	}

	/**
	 * « J'ai aussi fait » : la colonne du jour reçoit l'intervention neuve.
	 */
	public function testDayAlso(): void {

		$this->createTaskWitness();

		[$document] = $this->renderTaskDay();

		$eCategory = $this->getCategoryCulture();
		$eAction = $this->getAction(ACTION_PLANTATION);

		[$data, $instructions, $action] = $this->callDay('doCreate', [
			'category' => $eCategory['id'],
			'action' => $eAction['id'],
			'cultivation' => $this->getCultivation()['id'],
			'status' => \series\Task::DONE,
			'day' => \Clock::date(),
			'time' => [$this->getMember()['id'] => '0.5'],
		]);

		if($this->assertTrue($action instanceof \ViewAction, '« J\'ai aussi fait » est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([\Clock::date()], $data->change, '« J\'ai aussi fait » redessine d\'autres colonnes que celle du jour');
		$this->checkDay($document, $instructions, 'aussi fait');

	}

	/**
	 * « J'ai aussi fait » après la coche d'une carte de « Sans jour » : le panneau réémet les cartes gardées
	 * (`PlanningTaskDay.emitKeptForm()`), et la carte gardée reste (R-Garde-Gestes). Le geste ne redessine
	 * que la colonne du jour fait, jamais « Sans jour » : la réémission n'y change rien aujourd'hui, et ce
	 * test tient la règle pour le jour où le geste redessinerait « Sans jour ».
	 */
	public function testDayAlsoKept(): void {

		$eTask = $this->createTaskWeek();

		[$document] = $this->renderTaskDay();

		[, $instructions] = $this->callDay('doDone', ['task' => $eTask['id'], 'column' => TaskDayLib::NONE]);
		$this->apply($document, $instructions);

		[$data, $instructions, $action] = $this->callDay('doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'status' => \series\Task::DONE,
			'day' => \Clock::date(),
			'kept' => $this->getKept($document),
		]);

		if($this->assertTrue($action instanceof \ViewAction, '« J\'ai aussi fait » après une coche est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([\Clock::date()], $data->change, '« J\'ai aussi fait » après une coche redessine d\'autres colonnes que celle du jour');
		$this->checkDay($document, $instructions, 'aussi fait après une coche', tasksKept: [$eTask['id']]);

	}

	/**
	 * Sous « Masquer ce qui est fait », la carte cochée reste dans sa colonne jusqu'au rechargement, comme
	 * dans la Semaine : c'est le seul écart admis.
	 */
	public function testDayHidden(): void {

		$eTask = $this->createTaskWeek();

		$this->setHideDone(TRUE);

		[$document] = $this->renderTaskDay();

		[, $instructions] = $this->callDay('doDone', ['task' => $eTask['id']]);

		$this->checkDay($document, $instructions, 'coche sous le masque', eTaskHidden: $eTask);

	}

	/**
	 * Une carte de « Sans jour » cochée dans une semaine passée n'a aucun jour : la réponse la garde dans
	 * « Sans jour », faite, jusqu'au rechargement, qui ne la montre plus. C'est le second écart admis.
	 */
	public function testDayDoneKept(): void {

		$week = toWeek(\Clock::date().' - 14 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		[$document] = $this->renderTaskDay(week: $week);

		[, $instructions] = $this->callDay('doDone', ['task' => $eTask['id'], 'week' => $week, 'column' => TaskDayLib::NONE]);

		$this->checkDay($document, $instructions, 'coche sans jour dans une semaine passée', tasksKept: [$eTask['id']], week: $week);

	}

	/**
	 * « Nouvelle intervention » du Jour sur deux séries, posées sur mercredi : la réponse ne redessine que
	 * mercredi, qui porte les deux cartes neuves.
	 */
	public function testDayCreateSeveral(): void {

		$this->createTaskWitness();

		$wednesday = week_date_day(\Clock::week(), 3);

		$cultivations = [$this->getCultivation()['id'], $this->createCultivation($this->createSeries('Seconde série du décor'))['id']];

		[$document] = $this->renderTaskDay();

		[$data, $instructions, $action] = $this->callDay('doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $cultivations,
			'day' => $wednesday,
		]);

		if($this->assertTrue($action instanceof \ViewAction, '« Nouvelle intervention » du Jour sur deux séries est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(2, $data->cTaskCreated->count(), 'deux séries ne créent pas deux interventions sur le Jour');
		$this->assertEquals([$wednesday], $data->change, '« Nouvelle intervention » du Jour sur deux séries redessine d\'autres colonnes que la sienne');
		$this->checkDay($document, $instructions, 'nouvelle intervention sur deux séries');

	}

	/**
	 * « Nouvelle intervention » du Jour (L5d) : posée sur mercredi, elle ne redessine que mercredi.
	 */
	public function testDayCreate(): void {

		$this->createTaskWitness();

		$wednesday = week_date_day(\Clock::week(), 3);

		[$document] = $this->renderTaskDay();

		[$data, $instructions, $action] = $this->callDay('doCreate', [
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => $wednesday,
		]);

		if($this->assertTrue($action instanceof \ViewAction, '« Nouvelle intervention » du Jour est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$wednesday], $data->change, '« Nouvelle intervention » du Jour redessine d\'autres colonnes que la sienne');
		$this->checkDay($document, $instructions, 'nouvelle intervention');

	}

	/**
	 * « Nouvelle intervention » sans jour après la coche d'une carte de « Sans jour » : la réponse redessine
	 * « Sans jour », où va l'intervention neuve, et la carte gardée y reste, faite, au même rang
	 * (R-Garde-Gestes). Le panneau réémet les cartes gardées à l'envoi (`PlanningTaskDay.emitKeptForm()`).
	 * Même chose sous le masque, où la copie d'aujourd'hui posée par la coche reste jusqu'au rechargement,
	 * et dans une semaine passée, où la coche n'a aucun jour.
	 */
	public function testDayCreateKept(): void {

		foreach([
			'semaine courante' => [FALSE, \Clock::week()],
			'sous le masque' => [TRUE, \Clock::week()],
			'semaine passée' => [FALSE, toWeek(\Clock::date().' - 14 DAYS')],
		] as $case => [$hide, $week]) {

			$this->setHideDone($hide);

			$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

			[$document] = $this->renderTaskDay(week: $week);

			[, $instructions] = $this->callDay('doDone', ['task' => $eTask['id'], 'week' => $week, 'column' => TaskDayLib::NONE]);
			$this->apply($document, $instructions);

			$order = $this->getColumnTasks($document, TaskDayLib::NONE);

			[$data, $instructions, $action] = $this->callDay('doCreate', [
				'category' => $this->getCategoryCulture()['id'],
				'action' => $this->getAction(ACTION_PLANTATION)['id'],
				'cultivation' => $this->getCultivation()['id'],
				'week' => $week,
				'kept' => $this->getKept($document),
			]);

			if($this->assertTrue($action instanceof \ViewAction, $case.' : « Nouvelle intervention » sans jour est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
				continue;
			}

			$this->assertEquals([TaskDayLib::NONE], $data->change, $case.' : « Nouvelle intervention » sans jour ne redessine pas « Sans jour » seule');

			$this->checkDay(
				\Dom\HTMLDocument::createFromString($document->saveHtml(), LIBXML_NOERROR),
				$instructions,
				$case,
				eTaskHidden: $hide ? $eTask : new \series\Task(),
				tasksKept: [$eTask['id']],
				week: $week
			);

			$this->apply($document, $instructions);

			$tasks = array_values(array_diff($this->getColumnTasks($document, TaskDayLib::NONE), $data->cTaskCreated->getIds()));
			$this->assertEquals($order, $tasks, $case.' : une carte de « Sans jour » a disparu ou changé de rang');

		}

	}

	/**
	 * Un lot du Jour posé sur samedi : « Sans jour », que les cartes quittent, et samedi.
	 */
	public function testDayBatch(): void {

		$this->createTaskWitness();

		$eTaskFirst = $this->createTaskWeek();
		$eTaskSecond = $this->createTaskWeek();
		$saturday = week_date_day(\Clock::week(), 6);

		[$document] = $this->renderTaskDay();

		[$data, $instructions] = $this->callDay('doBatchPlannedDate', ['ids' => [$eTaskFirst['id'], $eTaskSecond['id']], 'plannedDate' => $saturday]);
		$this->assertEquals([TaskDayLib::NONE, $saturday], $data->change, 'le lot posé sur samedi ne redessine pas « Sans jour » et samedi');
		$this->checkDay($document, $instructions, 'lot posé');

		[$data, $instructions] = $this->callDay('doBatchDelete', ['ids' => [$eTaskFirst['id'], $eTaskSecond['id']]]);
		$this->assertEquals([$saturday], $data->change, 'le lot supprimé ne redessine pas samedi seul');
		$this->checkDay($document, $instructions, 'lot supprimé');

	}

	/**
	 * La fiche de temps (L6b), les deux preuves : un rendu comparé à lui-même est égal, un rendu changé par
	 * une intervention posée aujourd'hui diverge.
	 */
	public function testTimeProofs(): void {

		[$document] = $this->renderTimeDay();

		$this->assertEquals($this->getFingerprint($document), $this->getFingerprint($this->renderTimeDay()[0]), 'le même rendu de la fiche, neutralisé, diffère de lui-même');

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		$this->assertTrue($this->getFingerprint($document) !== $this->getFingerprint($this->renderTimeDay()[0]), 'une intervention ajoutée entre deux rendus de la fiche ne change pas l\'empreinte');

	}

	/**
	 * La journée saisie dans le champ de son jour (planche rh-heures-pistes, B), comme les interventions du jour,
	 * puis au-dessus, sous le temps posé, au-delà de 12 h et vidée, sur un jour qui porte 1h30 d'interventions : le
	 * jour, la somme de la semaine et les chiffres suivent, la barre des interventions gardant tout le temps posé,
	 * sans alerte, quand la journée descend sous lui (R-Retours-0410-soir, T). Comme le client, chaque saisie
	 * réémet le jour choisi de la fiche (`data-selected`).
	 */
	public function testTimeWorking(): void {

		$this->createTimesheet($this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date()), 1.5, \Clock::date());

		[$document] = $this->renderTimeDay();

		$selected = $document->getElementById('planning-time')?->getAttribute('data-selected');

		foreach(['1.5' => 'journée comme les interventions', '7.5' => 'journée saisie', '1' => 'journée sous le temps posé', '15.75' => 'journée au-delà de 12 h', '0' => 'journée vidée'] as $time => $case) {

			[, $instructions] = $this->callTimeDay('doWorkingTime', ['date' => \Clock::date(), 'time' => (string)$time, 'selected' => $selected]);
			$this->checkTime($document, $instructions, $case);

			// Les clés '1' et '0' du tableau deviennent des entiers.
			if((string)$time === '1') {
				$this->assertTrue(str_contains($document->querySelector('#planning-time-tasks-day .planning-time-tasks > .util-progress-part')?->getAttribute('style') ?? '', 'width: 12.5%'), 'journée sous le temps posé : la barre comparée ne garde pas tout le temps posé');
			}

		}

	}

	/**
	 * En suivi RH seul (planche rh-saisie-temps, A), la journée se saisit sous la pastille de son jour, sur la
	 * fiche d'aujourd'hui, sur celle d'un autre jour de la semaine et sur la semaine choisie, que la saisie réémet
	 * comme le client : la saisie, la correction et le vidage d'un jour ne répondent que par ce jour et la somme.
	 *
	 * Le suivi par intervention s'éteint et la RH s'allume dans la transaction du test ; la configuration, que
	 * `Structure::conf()` mémorise et que le rollback ne défait pas, se remet en place en sortant, comme les
	 * fonctionnalités.
	 */
	public function testTimeWorkingHr(): void {

		$eConfiguration = \structure\StructureLib::getById($this->eStructure['id'])->conf();
		$timeBefore = $eConfiguration['time'];
		$eConfiguration['time'] = FALSE;

		\structure\FeatureLib::updateFeature($this->eStructure, 'hasHr', TRUE);
		\structure\FeatureLib::forget($this->eStructure);

		try {

			$monday = week_date_day(\Clock::week(), 1);

			$screens = [
				'fiche d\'aujourd\'hui' => ['date' => \Clock::date()],
				'fiche du lundi' => ['date' => $monday],
				'semaine choisie' => ['period' => TimeWeekLib::WEEK],
			];

			foreach($screens as $case => $input) {

				[$document] = $this->renderTimeDay($input);

				$selected = $document->getElementById('planning-time')?->getAttribute('data-selected');

				foreach(['7' => 'saisie', '6.5' => 'corrigée', '0' => 'vidée'] as $time => $step) {

					[, $instructions] = $this->callTimeDay('doWorkingTime', ['date' => $monday, 'time' => (string)$time, 'selected' => $selected]);
					$this->checkTime($document, $instructions, $case.', journée du lundi '.$step, input: $input);

				}

			}

		} finally {

			$eConfiguration['time'] = $timeBefore;
			\structure\FeatureLib::forget($this->eStructure);

		}

	}

	/**
	 * Le temps d'une ligne du jour, un jour passé, saisi puis vidé : la ligne et les chiffres suivent.
	 */
	public function testTimeRow(): void {

		$monday = week_date_day(\Clock::week(), 1);

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday);

		$input = ['date' => $monday];

		[$document] = $this->renderTimeDay($input);

		[$data, $instructions] = $this->callTimeDay('doTime', $input + ['task' => $eTask['id'], 'time' => '1.5'] + $this->getTimePosition($document, $eTask));
		$this->assertEquals('row', $data->change, 'le temps d\'une ligne du jour redessine la liste');
		$this->checkTime($document, $instructions, 'temps saisi', $eTask, $input);

		[, $instructions] = $this->callTimeDay('doTime', $input + ['task' => $eTask['id'], 'time' => '0'] + $this->getTimePosition($document, $eTask));
		$this->checkTime($document, $instructions, 'temps vidé', $eTask, $input);

	}

	/**
	 * La coche d'une ligne sans jour lui donne aujourd'hui avec son temps prévu, et la décoche la rend à
	 * faire : elle reste dans le reste de la semaine, où l'écran la montrait, jusqu'au rechargement.
	 */
	public function testTimeDoneTodo(): void {

		$eTask = $this->createTaskWeek(timeExpected: 1.0);

		[$document] = $this->renderTimeDay();
		$this->checkMark($document, $eTask, FALSE, 'fiche avant la coche');

		[$data, $instructions] = $this->callTimeDay('doDone', ['task' => $eTask['id']] + $this->getTimePosition($document, $eTask));
		$this->assertEquals('row', $data->change, 'la coche d\'une ligne sans jour redessine la liste');
		$this->checkTime($document, $instructions, 'coche', $eTask);
		$this->checkMark($document, $eTask, TRUE, 'fiche après la coche');

		[, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $this->getTimePosition($document, $eTask));
		$this->checkTime($document, $instructions, 'décoche', $eTask);
		$this->checkMark($document, $eTask, FALSE, 'fiche après la décoche');

	}

	/**
	 * Un geste ne déplace pas la ligne sous le doigt (R-Place) : cocher une ligne sans jour lui donne
	 * aujourd'hui, qui la rangerait parmi les jours au rechargement, mais elle garde sa place à l'écran, au
	 * premier geste comme au second, qui réémet le jour que le premier lui a laissé.
	 */
	public function testTimeMove(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		[$document] = $this->renderTimeDay();

		$position = $this->getTimePosition($document, $eTask);

		if($this->assertEquals(['dayWeek' => ''], $position, 'la ligne sans jour n\'est pas à la fin de son groupe') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callTimeDay('doDone', ['task' => $eTask['id']] + $position);
		$this->assertEquals('row', $data->change, 'la coche redessine la liste');
		$this->assertEquals(\Clock::date(), TimeDayLib::getLine($this->renderTimeDay()[1]->timeDay, $eTask)['dayWeek'], 'la coche ne range pas la ligne à aujourd\'hui au rechargement');
		$this->checkTime($document, $instructions, 'premier geste', $eTask);
		$this->assertEquals($position, $this->getTimePosition($document, $eTask), 'la ligne quitte sa place au premier geste');

		[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => \Clock::date(), 'time' => '0.75'] + $this->getTimePosition($document, $eTask));
		$this->assertEquals('row', $data->change, 'le second geste redessine la liste');
		$this->checkTime($document, $instructions, 'second geste', $eTask);
		$this->assertEquals($position, $this->getTimePosition($document, $eTask), 'la ligne quitte sa place au second geste');

	}

	/**
	 * Un refus du serveur (point 9 de R-Saisie) : une journée de plus de 24 heures. La réponse ne porte que
	 * le message, rien de l'écran n'est redessiné. `PlanningDurationStep.refuse()` rend à `data-sent` la durée
	 * d'avant et rouvre la fenêtre sur la durée refusée, où le message se lit ; la refermer
	 * (`PlanningDurationStep.close()`) réécrit `data-sent` dans le champ : l'écran est alors celui d'un
	 * rechargement.
	 */
	public function testTimeRefused(): void {

		$eTaskFirst = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$eTaskSecond = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		[$document] = $this->renderTimeDay();

		[, , $action] = $this->callTimeDay('doTime', ['task' => $eTaskFirst['id'], 'date' => \Clock::date(), 'time' => '20'] + $this->getTimePosition($document, $eTaskFirst));

		if($this->assertTrue($action instanceof \ViewAction, 'les 20 heures de la première ligne sont refusées : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[$document] = $this->renderTimeDay();

		[, , $action] = $this->callTimeDay('doTime', ['task' => $eTaskSecond['id'], 'date' => \Clock::date(), 'time' => '10'] + $this->getTimePosition($document, $eTaskSecond));

		$this->checkRefusedStepped($document, $action, '#planning-time-task-'.$eTaskSecond['id'].' .planning-time-time .planning-duration-step', 10.0, 'Timesheet::time.tooLong', 'journée de plus de 24 heures sur la fiche');

		$this->assertEquals($this->getFingerprint($this->renderTimeDay()[0]), $this->getFingerprint($document), 'la fiche après le refus, la durée d\'avant remise, diffère du rechargement');

	}

	/**
	 * « J'ai aussi fait » venu de la fiche, sur une semaine vide : le bloc vide laisse la place à la liste, qui
	 * reçoit la ligne neuve, et les chiffres prennent son temps.
	 */
	public function testTimeAlso(): void {

		[$document] = $this->renderTimeDay();

		if($this->assertDecorPresent($document->getElementById('planning-time-none') !== NULL, 'la semaine du décor est vide') === FALSE) {
			return;
		}

		$action = $this->executePage('planning/timeDay:doCreate', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'when' => \Clock::week(),
			'user' => $this->getMember()['id'],
			'date' => \Clock::date(),
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'status' => \series\Task::DONE,
			'day' => \Clock::date(),
			'time' => [$this->getMember()['id'] => '0.5'],
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, '« J\'ai aussi fait » venu de la fiche est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('doCreate', $action, 'timeDay');

		$this->checkTime($document, $template->formatInstructions(), 'aussi fait');

	}

	/**
	 * « J'ai aussi fait » sur une liste vidée par « Masquer ce qui est fait » (retour 274) : le bloc du filtre
	 * laisse la place à la liste, et « + Intervention » reste en haut à droite (retour 291). La ligne neuve est faite, donc
	 * masquée au rechargement (retour 287) : elle reste sous les doigts jusque-là, à son jour (R-Place).
	 */
	public function testTimeAlsoHidden(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());

		$this->setHideDone(TRUE);

		[$document] = $this->renderTimeDay();

		if($this->assertDecorPresent($document->getElementById('planning-time-none') !== NULL, 'le masque vide la liste de la semaine') === FALSE) {
			return;
		}

		$action = $this->executePage('planning/timeDay:doCreate', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'when' => \Clock::week(),
			'user' => $this->getMember()['id'],
			'date' => \Clock::date(),
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'status' => \series\Task::DONE,
			'day' => \Clock::date(),
			'time' => [$this->getMember()['id'] => '0.5'],
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, '« J\'ai aussi fait » sous le filtre est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[$data, $template] = $this->runView('doCreate', $action, 'timeDay');

		$this->checkTime($document, $template->formatInstructions(), 'aussi fait sous le filtre', $data->cTaskCreated->first(), dayWeekCreated: \Clock::date());

		$this->assertTrue($this->renderTimeDay()[0]->getElementById('planning-time-none') !== NULL, 'la ligne neuve, faite, reste dans la fiche rechargée sous le filtre');

	}

	/**
	 * Sous « Masquer ce qui est fait », la ligne cochée sans temps reste jusqu'au rechargement, comme dans
	 * la Semaine.
	 */
	public function testTimeHidden(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		$this->setHideDone(TRUE);

		[$document] = $this->renderTimeDay();

		[$data, $instructions] = $this->callTimeDay('doDone', ['task' => $eTask['id']] + $this->getTimePosition($document, $eTask));
		$this->assertEquals('row', $data->change, 'la coche sous le masque redessine la liste');
		$this->checkTime($document, $instructions, 'coche sous le masque', $eTask);

		$this->assertTrue($document->querySelector('#planning-time-task-'.$eTask['id'].' .planning-task-mark-done') !== NULL, 'la ligne gardée ne porte pas le rond coché');

	}

	/**
	 * Sous « Masquer ce qui est fait », une ligne faite quitte la fiche même quand elle porte du temps ce
	 * jour-là (retour 287). Cocher une ligne qui porte du temps la garde jusqu'au rechargement, qui la retire :
	 * la réponse vaut ce rechargement, la ligne gardée mise à part.
	 */
	public function testTimeHiddenSpent(): void {

		$eTaskDone = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), \Clock::date());
		$this->createTimesheetFor($eTaskDone, $this->getMember(), 0.5, \Clock::date());

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$this->createTimesheetFor($eTask, $this->getMember(), 0.5, \Clock::date());

		$this->setHideDone(TRUE);

		[$document] = $this->renderTimeDay();

		$this->assertTrue($document->getElementById('planning-time-task-'.$eTaskDone['id']) === NULL, 'le masque garde une ligne faite qui porte du temps ce jour-là');

		if($this->assertDecorPresent($document->getElementById('planning-time-task-'.$eTask['id']) !== NULL, 'la ligne à faire qui porte du temps est sur la fiche') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callTimeDay('doDone', ['task' => $eTask['id']] + $this->getTimePosition($document, $eTask));
		$this->assertEquals('row', $data->change, 'la coche sous le masque d\'une ligne qui porte du temps redessine la liste');
		$this->checkTime($document, $instructions, 'coche sous le masque d\'une ligne qui porte du temps', $eTask);

		$this->assertTrue($document->querySelector('#planning-time-task-'.$eTask['id'].' .planning-task-mark-done') !== NULL, 'la ligne cochée qui porte du temps ne reste pas sous le doigt');
		$this->assertFalse($this->isInSheet($this->renderTimeDay()[1]->timeDay, $eTask), 'la ligne cochée qui porte du temps reste dans la fiche rechargée');

	}

	/**
	 * Une ligne commentée qui arrive dans une table affichée sans la colonne des commentaires redessine la
	 * liste entière, à trois colonnes (décision de l'architecte sur R136-138). Le commentaire est posé
	 * ailleurs après le rendu de l'écran, qui réémet donc deux colonnes.
	 */
	public function testTimeCommentsArrive(): void {

		$eTask = $this->createTaskWeek();

		$this->deleteComments($this->renderTimeDay()[1]->timeDay);

		[$document, $data] = $this->renderTimeDay();

		if($this->assertFalse($data->timeDay['commented'], 'le décor porte encore une ligne commentée cette semaine') === FALSE) {
			return;
		}

		$this->createComment($eTask, 'Voir le filet', '09:00:00');

		[$data, $instructions] = $this->callTimeDay('doDone', ['task' => $eTask['id'], 'columns' => '2'] + $this->getTimePosition($document, $eTask));
		$this->assertEquals('table', $data->change, 'la ligne commentée arrivée dans une table à deux colonnes ne redessine pas la liste');
		$this->checkTime($document, $instructions, 'ligne commentée dans une table à deux colonnes', $eTask);

		$this->assertEquals('3', $document->getElementById('planning-time-table')?->getAttribute('data-columns'), 'la liste redessinée n\'est pas à trois colonnes');

	}

	/**
	 * La dernière ligne commentée quitte la fiche sous le masque : la table reste à trois colonnes jusqu'au
	 * rechargement (écart 2 accepté par V-Planning), et la ligne gardée y garde sa cellule. Le geste suivant,
	 * sur cette même table, réémet trois colonnes et sa ligne arrive avec trois cellules. Chaque cas se
	 * compare au rechargement rendu dans cette même variante.
	 */
	public function testTimeCommentsLeave(): void {

		$eTaskComment = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());
		$eTaskOther = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week(), \Clock::date());

		$this->deleteComments($this->renderTimeDay()[1]->timeDay);

		$this->createComment($eTaskComment, 'Voir le filet', '09:00:00');

		$this->setHideDone(TRUE);

		[$document, $data] = $this->renderTimeDay();

		if($this->assertTrue($data->timeDay['commented'], 'la ligne commentée n\'ouvre pas la colonne') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callTimeDay('doDone', ['task' => $eTaskComment['id'], 'columns' => '3'] + $this->getTimePosition($document, $eTaskComment));
		$this->assertEquals('row', $data->change, 'la ligne cochée sous le masque ne se remplace pas seule');
		$this->assertFalse($this->renderTimeDay()[1]->timeDay['commented'], 'la ligne commentée cochée sous le masque reste dans la fiche rechargée');
		// Cinq cellules : l'action (R-Retours-0810, 211), le nom, le temps, le commentaire et la marque (200).
		$this->assertEquals(5, $this->countCells($instructions, $eTaskComment), 'la ligne gardée perd sa cellule de commentaire');
		$this->checkTime($document, $instructions, 'dernière ligne commentée retirée', $eTaskComment, commented: TRUE);

		// La réponse ne garde que la ligne du geste : celle du geste précédent reste à l'écran jusqu'au
		// rechargement, sans que la suivante la redessine.
		$document->getElementById('planning-time-task-'.$eTaskComment['id'])->remove();

		[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTaskOther['id'], 'date' => \Clock::date(), 'time' => '0.5', 'columns' => '3'] + $this->getTimePosition($document, $eTaskOther));
		$this->assertEquals('row', $data->change, 'le temps d\'une ligne d\'une table à trois colonnes ne se remplace pas seul');
		$this->assertEquals(5, $this->countCells($instructions, $eTaskOther), 'la ligne d\'une table restée à trois colonnes n\'arrive pas avec sa cellule de commentaire');
		$this->checkTime($document, $instructions, 'geste suivant sur la table restée à trois colonnes', $eTaskOther, commented: TRUE);

	}

	/**
	 * CF · Le temps saisi sur une ligne en retard : la ligne reste dans sa zone (R-Place), le reste de l'écran
	 * est celui du rechargement, où elle est passée dans la liste.
	 */
	public function testTimeZoneTime(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTimeDay();

		[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonePosition($document, $eTask));

		$this->assertEquals('row', $data?->change, 'le temps sur une ligne en retard ne rend pas la ligne seule');
		$this->checkTimeZone($document, $instructions, 'temps sur une ligne en retard', $eTask);

	}

	/**
	 * CF · La coche d'une ligne en retard, puis sa décoche : la ligne reste dans sa zone à chaque geste.
	 */
	public function testTimeZoneDone(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTimeDay();

		$position = $this->getTimeZonePosition($document, $eTask);

		[$data, $instructions] = $this->callTimeDay('doDone', ['task' => $eTask['id']] + $position);
		$this->assertEquals('row', $data?->change, 'la coche d\'une ligne en retard ne rend pas la ligne seule');
		$this->checkTimeZone($document, $instructions, 'coche d\'une ligne en retard', $eTask, $position);

		[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $position);
		$this->assertEquals('row', $data?->change, 'la décoche d\'une ligne gardée dans sa zone ne rend pas la ligne seule');
		$this->checkTimeZone($document, $instructions, 'décoche d\'une ligne gardée dans sa zone', $eTask, $position);

	}

	/**
	 * CF · Une ligne de la liste que la décoche fait sortir de la semaine pour « En retard » : faite cette
	 * semaine sans temps, prévue la semaine d'avant. La liste se redessine, la zone ne reçoit que la ligne
	 * et son compte, et l'écran est celui du rechargement.
	 */
	public function testTimeZoneArrives(): void {

		$this->createTaskWitness();
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
		\series\TaskLib::updateDoneCollection(new \Collection([$eTask]), \Clock::week());

		// `updateDoneCollection()` fait l'intervention dans sa semaine prévue : elle n'entre dans la liste que
		// faite cette semaine, comme la page d'une intervention le permet. Sans `build()`, qui bornerait le jour
		// du fait saisi : le décor le pose à aujourd'hui, que ce contrôle accepte.
		$eTask['doneWeek'] = \Clock::week();
		$eTask['doneDate'] = \Clock::date();
		\series\TaskLib::update($eTask, ['doneWeek', 'doneDate']);

		[$document] = $this->renderTimeDay();

		if($this->assertDecorPresent($document->querySelector('#planning-time-list #planning-time-task-'.$eTask['id']) !== NULL, 'la ligne faite cette semaine est dans la liste') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTask));

		$this->assertEquals('table', $data?->change, 'la ligne sortie de la semaine ne redessine pas la liste');
		$this->assertEquals(['counter', 'insert', 'row'], $this->getZoneSteps($data), 'la ligne sortie de la semaine n\'entre pas seule en tête de son bloc de « En retard »');
		$this->checkTime($document, $instructions, 'ligne sortie de la semaine pour « En retard »');

		$this->assertTrue($document->querySelector('#planning-time-late-board #planning-time-task-'.$eTask['id']) !== NULL, 'la ligne sortie de la semaine n\'est pas dans « En retard »');

	}

	/**
	 * CF · La première ouverture de « Non planifié » : l'écran est celui d'un rechargement où la zone est lue.
	 */
	public function testTimeZoneUnplannedOpen(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		[$document] = $this->renderTimeDay();

		$action = $this->executePage('planning/timeDay:unplanned', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'l\'ouverture de « Non planifié » est refusée') === FALSE) {
			return;
		}

		[, $template] = $this->runView('unplanned', $action, 'timeDay');

		// Le pli s'ouvre côté client (`PlanningTimeZone.toggle()`), avant la réponse.
		$document->getElementById('planning-time-unplanned')?->classList->add('util-fold-open');

		$this->checkTime($document, $template->formatInstructions(), 'ouverture de « Non planifié »', input: ['unplanned' => '1']);

	}

	/**
	 * CF · « Voir les N autres » de deux actions plafonnées de « En retard » : la première ajoute ses deux
	 * lignes, une instruction chacune, la seconde, dont une ligne au-delà du plafond est commentée, ouvre
	 * d'abord la colonne des commentaires de la table. La réponse de la seconde ne rend aucune ligne de la
	 * première, dépliée. À chaque réponse, l'écran est celui d'un rechargement qui réémet les blocs dépliés
	 * (`expanded`), le premier compris.
	 */
	public function testTimeZoneMore(): void {

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			$week = toWeek(\Clock::date().' - 7 DAYS');

			for($i = 0; $i <= TaskLateLib::LIMIT; $i++) {
				$eTaskFirst = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
				$eTaskSecond = $this->createTask($this->getCultivation(), ACTION_SEMIS_DIRECT, \series\Task::TODO, $week);
			}

			$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

			[$document, $data] = $this->renderTimeDay();

			if($this->assertDecorPresent($data->zones[TaskLateLib::LATE]['commented'] === FALSE, '« En retard » n\'a pas de colonne des commentaires') === FALSE) {
				return;
			}

			$actionFirst = $eTaskFirst['action']['id'];
			$actionSecond = $eTaskSecond['action']['id'];

			$restFirst = array_slice($data->zones[TaskLateLib::LATE]['blocks'][$actionFirst]['ids'], TaskLateLib::LIMIT);

			$instructions = $this->callTimeZoneMore($document, TaskLateLib::LATE, $actionFirst);
			$this->checkTime($document, $instructions, '« Voir les N autres » du premier bloc', input: ['expanded' => TaskLateLib::LATE.'-'.$actionFirst]);

			$restSecond = $data->zones[TaskLateLib::LATE]['blocks'][$actionSecond]['ids'][TaskLateLib::LIMIT];
			$this->createComment(\series\TaskLib::getById($restSecond), 'Recette CF second', '10:05:00');

			$instructions = $this->callTimeZoneMore($document, TaskLateLib::LATE, $actionSecond);
			$this->assertFalse($this->hasRows($instructions, $restFirst), 'la réponse du second bloc rend les lignes du premier, déplié');
			$this->checkTime($document, $instructions, '« Voir les N autres » du second bloc, qui ouvre la colonne des commentaires', input: ['expanded' => TaskLateLib::LATE.'-'.$actionFirst.','.TaskLateLib::LATE.'-'.$actionSecond]);

			$this->assertTrue($document->getElementById('planning-time-late-more-'.$actionFirst) === NULL, 'le premier bloc se replie quand le second s\'ouvre');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * CF · Une ligne de la liste qui part dans un bloc plafonné de « En retard » que la personne a déplié : la
	 * réponse n'y pose que la ligne, sans aucune des lignes que le dépliage a montrées, et l'écran est celui
	 * du rechargement qui réémet le bloc déplié. C'est ce qui garde le poids de la réponse indépendant de ce
	 * qui est déplié (règle « une action ne renvoie que ce qui change »).
	 */
	public function testTimeZoneArrivesExpanded(): void {

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			$this->createTaskWitness();

			for($i = 0; $i <= TaskLateLib::LIMIT + 1; $i++) {
				$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
			}

			$eTask = $this->createTaskDoneLate(toWeek(\Clock::date().' - 7 DAYS'));

			[$document, $data] = $this->renderTimeDay();

			$action = $eTask['action']['id'];
			$rest = array_slice($data->zones[TaskLateLib::LATE]['blocks'][$action]['ids'], TaskLateLib::LIMIT);

			$this->apply($document, $this->callTimeZoneMore($document, TaskLateLib::LATE, $action));

			[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTask));

			$this->assertFalse($this->hasRows($instructions, $rest), 'la ligne sortie de la semaine rend les lignes du bloc déplié');
			$this->assertEquals(['counter', 'insert', 'row'], $this->getZoneSteps($data), 'la ligne sortie de la semaine n\'entre pas seule dans le bloc déplié');
			$this->checkTime($document, $instructions, 'ligne sortie de la semaine pour un bloc déplié de « En retard »', input: ['expanded' => TaskLateLib::LATE.'-'.$action]);

			$this->assertTrue($document->getElementById('planning-time-late-more-'.$action) === NULL, 'le bloc déplié se replie');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * CF · Deux lignes de la liste qui partent dans un bloc plafonné de « En retard » que la personne n'a pas
	 * déplié. La première, la plus récente, entre en tête : la dernière ligne montrée passe derrière « Voir
	 * les N autres », qui paraît. La seconde, prévue plus tôt, va derrière le plafond : seul « Voir les N
	 * autres » la compte.
	 */
	public function testTimeZoneArrivesCapped(): void {

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			$this->createTaskWitness();

			for($i = 0; $i < TaskLateLib::LIMIT; $i++) {
				$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
			}

			$eTaskBehind = $this->createTaskDoneLate(toWeek(\Clock::date().' - 14 DAYS'));
			$eTaskFirst = $this->createTaskDoneLate(toWeek(\Clock::date().' - 7 DAYS'));

			[$document] = $this->renderTimeDay();

			$action = $eTaskFirst['action']['id'];

			if($this->assertDecorPresent($document->getElementById('planning-time-late-more-'.$action) === NULL, 'le bloc montre toutes ses lignes avant les gestes') === FALSE) {
				return;
			}

			[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTaskFirst['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTaskFirst));
			$this->assertEquals(['counter', 'insert', 'row', 'remove', 'more'], $this->getZoneSteps($data), 'la ligne en tête du bloc plafonné ne pousse pas la dernière derrière « Voir les N autres »');
			$this->checkTime($document, $instructions, 'ligne en tête d\'un bloc plafonné');

			[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTaskBehind['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTaskBehind));
			$this->assertEquals(['counter', 'more'], $this->getZoneSteps($data), 'la ligne derrière le plafond ne change pas que « Voir les N autres »');
			$this->checkTime($document, $instructions, 'ligne derrière le plafond d\'un bloc');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * CF · Une ligne de la liste dont l'action n'a pas de bloc dans « En retard » : son intitulé paraît à sa
	 * place parmi les blocs, la ligne dessous.
	 */
	public function testTimeZoneArrivesBlock(): void {

		$this->createTaskWitness();
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		$eTask = $this->createTaskDoneLate(toWeek(\Clock::date().' - 7 DAYS'), ACTION_SEMIS_DIRECT);

		[$document] = $this->renderTimeDay();

		if($this->assertDecorPresent($document->getElementById('planning-time-late-action-'.$eTask['action']['id']) === NULL, '« En retard » n\'a pas de bloc de l\'action') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTask));
		$this->assertEquals(['counter', 'title', 'insert'], $this->getZoneSteps($data), 'la ligne d\'une action sans bloc ne pose pas que son intitulé et elle');
		$this->checkTime($document, $instructions, 'ligne d\'une action sans bloc dans « En retard »');

	}

	/**
	 * CF · Une ligne de la liste qui fait franchir à « En retard » le seuil du plafond : la table de la zone se
	 * redessine plafonnée, aucun bloc ne pouvant être déplié avant.
	 */
	public function testTimeZoneArrivesThreshold(): void {

		$threshold = TaskLateLib::$threshold;

		try {

			$this->createTaskWitness();

			for($i = 0; $i <= TaskLateLib::LIMIT; $i++) {
				$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
			}

			$eTask = $this->createTaskDoneLate(toWeek(\Clock::date().' - 7 DAYS'));

			[, $data] = $this->renderTimeDay();

			TaskLateLib::$threshold = $data->zones[TaskLateLib::LATE]['count'];

			[$document] = $this->renderTimeDay();

			[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTask));
			$this->assertEquals(['counter', 'board'], $this->getZoneSteps($data), 'la zone qui franchit le seuil ne redessine pas sa table');
			$this->checkTime($document, $instructions, 'ligne qui fait franchir le seuil à « En retard »');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * CF · Une ligne de la liste qui part dans « En retard » alors que la zone n'est pas à l'écran, faute
	 * d'intervention : la zone paraît entière, avec sa seule ligne.
	 */
	public function testTimeZoneArrivesZone(): void {

		$this->createTaskWitness();

		$eTask = $this->createTaskDoneLate(toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTimeDay();

		if($this->assertDecorPresent($document->getElementById('planning-time-late') === NULL, '« En retard » n\'est pas à l\'écran') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTask));
		$this->assertEquals(['zone'], $this->getZoneSteps($data), '« En retard » ne paraît pas entière');
		$this->checkTime($document, $instructions, '« En retard » qui paraît');

	}

	/**
	 * CF · Une ligne de zone commentée depuis l'affichage, dans une table sans colonne des commentaires : le
	 * geste ouvre la colonne sans redessiner la table, puis rend la ligne.
	 */
	public function testTimeZoneVariant(): void {

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document, $dataBefore] = $this->renderTimeDay();

		if($this->assertDecorPresent($dataBefore->zones[TaskLateLib::LATE]['commented'] === FALSE, '« En retard » n\'a pas de colonne des commentaires') === FALSE) {
			return;
		}

		$this->createComment($eTask, 'Recette CF variante', '10:05:00');

		[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTask));

		$this->assertEquals('variant', $data?->change, 'la ligne commentée n\'ouvre pas la colonne des commentaires');
		$this->assertEquals(['variant', 'row'], $this->getZoneSteps($data), 'la colonne des commentaires s\'ouvre en redessinant la table');
		$this->checkTimeZone($document, $instructions, 'ligne commentée dans une table sans colonne des commentaires', $eTask, zoneCommented: $dataBefore->zones[TaskLateLib::LATE]);

	}

	/**
	 * CF · Une ligne en tête d'un bloc plafonné de « En retard », dont la série est close entre l'affichage
	 * et le geste : elle sort de sa zone, la suivante ouvre le bloc, et la première ligne derrière le
	 * plafond paraît, « Voir les N autres » n'ayant plus rien derrière lui.
	 */
	public function testTimeZoneDeparture(): void {

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			for($i = 0; $i < TaskLateLib::LIMIT; $i++) {
				$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));
			}

			$eCultivation = $this->createCultivation($this->createSeries('Série close du décor'));
			$eTask = $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

			[$document] = $this->renderTimeDay();

			$position = $this->getTimeZonePosition($document, $eTask);

			if($this->assertDecorPresent(($position['actionFirst'] ?? NULL) === '1' and $document->getElementById('planning-time-late-more-'.$eTask['action']['id']) !== NULL, 'la ligne ouvre un bloc plafonné') === FALSE) {
				return;
			}

			$eSeries = $eCultivation['series'];
			$eSeries['status'] = \series\Series::CLOSED;

			// Sans `build()`, qui vérifierait le statut posté : le décor clôt sa propre série, comme la clôture
			// des séries (`SeriesLib::closeCollection()`).
			\series\SeriesLib::update($eSeries, ['status']);

			[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonesInput($document) + $position);

			$this->assertEquals('remove', $data?->change, 'la ligne d\'une série close ne sort pas de sa zone');
			$this->assertEquals(['counter', 'remove', 'row', 'insert', 'more'], $this->getZoneSteps($data), 'la ligne qui sort d\'un bloc plafonné ne laisse pas paraître la suivante');
			$this->checkTime($document, $instructions, 'ligne d\'une série close qui sort de « En retard »');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * CF · Une ligne de « En retard » gardée par le temps qu'elle a reçu, puis le départ d'une autre ligne du
	 * même bloc, dont la série est close entre l'affichage et le geste : la zone lâche aussi la ligne gardée,
	 * que seule la liste peut alors montrer. Une troisième ligne garde le bloc à l'écran.
	 */
	public function testTimeZoneDepartureRetained(): void {

		$week = toWeek(\Clock::date().' - 7 DAYS');

		$this->createTaskWitness();
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		$eTaskRetained = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		$this->checkTimeZoneDepartureRetained($eTaskRetained, ACTION_PLANTATION, 'remove', 'ligne gardée dans un bloc d\'où sort une ligne d\'une série close');

	}

	/**
	 * CF · La même suite quand la ligne gardée et celle qui sort sont les seules de « En retard » : la zone
	 * quitte l'écran avec la ligne gardée.
	 */
	public function testTimeZoneDepartureRetainedAlone(): void {

		$this->createTaskWitness();

		$eTaskRetained = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		$this->checkTimeZoneDepartureRetained($eTaskRetained, ACTION_PLANTATION, 'removeZone', 'ligne gardée dans une zone que vide une ligne d\'une série close');

	}

	/**
	 * CF · La même suite sans rien dans la semaine : la liste était à l'état vide, que la ligne gardée
	 * remplace par la table, avec le bouton de la rangée des outils.
	 */
	public function testTimeZoneDepartureRetainedNone(): void {

		$eTaskRetained = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTimeDay();

		if($this->assertDecorPresent($document->getElementById('planning-time-none') !== NULL, 'la liste est à l\'état vide') === FALSE) {
			return;
		}

		$this->checkTimeZoneDepartureRetained($eTaskRetained, ACTION_PLANTATION, 'removeZone', 'ligne gardée sous une liste vide, dans une zone que vide une ligne d\'une série close');

	}

	/**
	 * CF · La même suite quand la ligne gardée est seule dans son bloc, et que la ligne qui sort en laisse une
	 * dans le sien : l'intitulé du bloc de la ligne gardée sort avec elle.
	 */
	public function testTimeZoneDepartureRetainedTitle(): void {

		$week = toWeek(\Clock::date().' - 7 DAYS');

		$this->createTaskWitness();
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		$eTaskRetained = $this->createTask($this->getCultivation(), ACTION_SEMIS_DIRECT, \series\Task::TODO, $week);

		$this->checkTimeZoneDepartureRetained($eTaskRetained, ACTION_PLANTATION, 'removeTitle', 'seule ligne d\'un bloc, gardée, quand une ligne d\'une série close sort d\'un autre bloc');

	}

	/**
	 * CF · La même suite quand les deux départs font repasser la zone sous le seuil du plafond : sa table se
	 * redessine, sans la ligne gardée.
	 */
	public function testTimeZoneDepartureRetainedThreshold(): void {

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 2;

		try {

			$week = toWeek(\Clock::date().' - 7 DAYS');

			$this->createTaskWitness();
			$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

			$eTaskRetained = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

			$this->checkTimeZoneDepartureRetained($eTaskRetained, ACTION_PLANTATION, 'board', 'ligne gardée dans une zone qu\'une ligne d\'une série close fait repasser sous le seuil');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * CF · Une ligne gardée dont le temps est remis à zéro appartient de nouveau à « En retard » : quand une
	 * autre ligne du bloc sort, d'une série close, elle reste dans la zone et la liste, qui ne reçoit rien,
	 * n'est pas redessinée (une liste pèse des centaines de Ko sur une grosse structure).
	 */
	public function testTimeZoneDepartureRetainedKept(): void {

		$week = toWeek(\Clock::date().' - 7 DAYS');

		$this->createTaskWitness();
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		$eTaskRetained = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		$eCultivation = $this->createCultivation($this->createSeries('Série close du décor'));
		$eTask = $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::TODO, $week);

		[$document] = $this->renderTimeDay();

		foreach(['0.5', '0'] as $time) {

			[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTaskRetained['id'], 'date' => \Clock::date(), 'time' => $time] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTaskRetained));

			if($this->assertDecorPresent($data?->change === 'row', 'la ligne dont le temps passe à '.$time.' reste dans sa zone') === FALSE) {
				return;
			}

			$this->apply($document, $instructions);

		}

		if($this->assertDecorPresent(str_contains($document->getElementById('planning-time-zones')->getAttribute('data-retained'), '-'.$eTaskRetained['id'].'-'), 'l\'écran retient la ligne remise à zéro') === FALSE) {
			return;
		}

		$this->closeSeries($eCultivation['series']);

		[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTask));

		$this->assertEquals('remove', $data?->change, 'la ligne d\'une série close ne sort pas de sa zone');
		$this->assertEquals([], array_values(array_intersect(array_column($instructions, 1), ['#planning-time-tools', '#planning-time-list'])), 'la liste est redessinée alors qu\'aucune ligne gardée ne la rejoint');
		$this->checkTime($document, $instructions, 'ligne gardée remise à zéro, quand une ligne d\'une série close sort de son bloc');

		$this->assertTrue($document->querySelector('#planning-time-late-board #planning-time-task-'.$eTaskRetained['id']) !== NULL, 'la ligne gardée remise à zéro n\'est plus dans « En retard »');
		$this->assertEquals('', $document->getElementById('planning-time-zones')->getAttribute('data-retained'), 'l\'écran retient encore une ligne');

	}

	/**
	 * CF · « En retard » et « Non planifié » gardent chacune une ligne qui a reçu du temps, puis une ligne
	 * d'une série close sort de « En retard » : la liste redessinée montre les deux lignes gardées, que les
	 * deux zones lâchent donc ensemble.
	 */
	public function testTimeZoneDepartureRetainedBoth(): void {
		$this->checkTimeZoneDepartureRetainedBoth(toWeek(\Clock::date().' - 7 DAYS'), 'départ de « En retard »');
	}

	/**
	 * CF · La même suite quand la ligne d'une série close sort de « Non planifié ».
	 */
	public function testTimeZoneDepartureRetainedBothUnplanned(): void {
		$this->checkTimeZoneDepartureRetainedBoth(NULL, 'départ de « Non planifié »');
	}

	/**
	 * `$weekDeparture` est la semaine prévue de la ligne qui sort, `NULL` pour une ligne non planifiée.
	 */
	private function checkTimeZoneDepartureRetainedBoth(?string $weekDeparture, string $case): void {

		$week = toWeek(\Clock::date().' - 7 DAYS');
		$input = ['unplanned' => '1'];

		$this->createTaskWitness();
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		$cTaskRetained = new \Collection([
			$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL),
			$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week),
		]);

		$eCultivation = $this->createCultivation($this->createSeries('Série close du décor'));
		$eTask = $this->createTask($eCultivation, ACTION_PLANTATION, \series\Task::TODO, $weekDeparture);

		[$document] = $this->renderTimeDay($input);

		foreach($cTaskRetained as $eTaskRetained) {

			[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTaskRetained['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTaskRetained));

			if($this->assertDecorPresent($data?->change === 'row', $case.' : la ligne qui reçoit du temps reste dans sa zone') === FALSE) {
				return;
			}

			$this->apply($document, $instructions);

		}

		$this->closeSeries($eCultivation['series']);

		[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTask));

		$this->assertEquals('remove', $data?->change, $case.' : la ligne d\'une série close ne sort pas de sa zone');
		$this->checkTime($document, $instructions, 'deux zones qui gardent une ligne, '.$case, input: $input);

		foreach($cTaskRetained as $eTaskRetained) {
			$this->assertEquals(1, $document->querySelectorAll('[id="planning-time-task-'.$eTaskRetained['id'].'"]')->length, $case.' : une ligne gardée n\'est pas à l\'écran une fois et une seule');
			$this->assertTrue($document->querySelector('#planning-time-list #planning-time-task-'.$eTaskRetained['id']) !== NULL, $case.' : une ligne gardée n\'est pas dans la liste');
		}

		$this->assertEquals('', $document->getElementById('planning-time-zones')->getAttribute('data-retained'), $case.' : l\'écran retient encore une ligne');

	}

	/**
	 * Du temps sur une ligne de « En retard », qui la garde dans sa zone, puis du temps sur une autre ligne de
	 * la zone dont la série vient d'être close : l'écran est celui du rechargement, où la ligne gardée est
	 * dans la liste et nulle part ailleurs. `$step` est l'étape par laquelle la ligne gardée quitte la zone.
	 */
	private function checkTimeZoneDepartureRetained(\series\Task $eTaskRetained, string $action, string $step, string $case): void {

		$eCultivation = $this->createCultivation($this->createSeries('Série close du décor'));
		$eTask = $this->createTask($eCultivation, $action, \series\Task::TODO, toWeek(\Clock::date().' - 7 DAYS'));

		[$document] = $this->renderTimeDay();

		[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTaskRetained['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTaskRetained));

		if($this->assertDecorPresent($data?->change === 'row', 'la ligne qui reçoit du temps reste dans sa zone') === FALSE) {
			return;
		}

		$this->apply($document, $instructions);

		$this->closeSeries($eCultivation['series']);

		[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTask['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTask));

		$this->assertEquals('remove', $data?->change, $case.' : la ligne d\'une série close ne sort pas de sa zone');
		$this->assertTrue(in_array($step, $this->getZoneSteps($data), TRUE), $case.' : la zone ne passe pas par l\'étape '.$step.' ('.implode(', ', $this->getZoneSteps($data)).')');
		$this->checkTime($document, $instructions, $case);

		$this->assertEquals(1, $document->querySelectorAll('[id="planning-time-task-'.$eTaskRetained['id'].'"]')->length, $case.' : la ligne gardée n\'est pas à l\'écran une fois et une seule');
		$this->assertTrue($document->querySelector('#planning-time-list #planning-time-task-'.$eTaskRetained['id']) !== NULL, $case.' : la ligne gardée n\'est pas dans la liste');
		$this->assertEquals('', $document->getElementById('planning-time-zones')->getAttribute('data-retained'), $case.' : l\'écran retient encore une ligne');

	}

	/**
	 * CF · Une ligne de « En retard » garde sa place après avoir reçu du temps (R-Place), alors que la base
	 * ne la compte plus dans la zone. Une ligne de la liste rejoint ensuite le même bloc : la liste redessinée
	 * montre la ligne gardée, qui sort donc de la zone, et aucune ligne ni aucun intitulé n'est à l'écran
	 * deux fois.
	 */
	public function testTimeZoneRetained(): void {

		$week = toWeek(\Clock::date().' - 7 DAYS');

		$this->createTaskWitness();
		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		$eTaskRetained = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
		$eTaskArrival = $this->createTaskDoneLate($week);

		$this->checkTimeZoneRetained($eTaskRetained, $eTaskArrival, ['counter', 'remove', 'insert', 'row'], 'ligne gardée dans un bloc que rejoint une ligne de la liste');

	}

	/**
	 * CF · La même suite quand la ligne gardée est la seule de « En retard » : la zone est restée à l'écran
	 * pour elle, et la ligne qui arrive y prend sa place sans que la zone s'y pose une seconde fois.
	 */
	public function testTimeZoneRetainedAlone(): void {

		$week = toWeek(\Clock::date().' - 7 DAYS');

		$this->createTaskWitness();

		$eTaskRetained = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
		$eTaskArrival = $this->createTaskDoneLate($week);

		[$document] = $this->renderTimeDay();

		if($this->assertDecorPresent($document->querySelectorAll('#planning-time-late-board .planning-time-row')->length === 1, 'la ligne gardée est la seule de « En retard »') === FALSE) {
			return;
		}

		$this->checkTimeZoneRetained($eTaskRetained, $eTaskArrival, ['counter', 'remove', 'insert'], 'seule ligne de « En retard » gardée, que remplace une ligne de la liste');

	}

	/**
	 * CF · Une ligne gardée dans un bloc plafonné, que « Voir les N autres » déplie : le bloc reste déplié pour la
	 * suite, même si la base ne le plafonne plus, et même après le « Voir les N autres » d'un autre bloc : une
	 * ligne de la liste qui le rejoint ensuite ne fait reparaître aucune ligne déjà à l'écran.
	 */
	public function testTimeZoneRetainedMore(): void {

		$threshold = TaskLateLib::$threshold;
		TaskLateLib::$threshold = 0;

		try {

			$week = toWeek(\Clock::date().' - 7 DAYS');

			$this->createTaskWitness();
			$eTaskArrival = $this->createTaskDoneLate($week);

			$cTask = new \Collection();

			for($i = 0; $i < TaskLateLib::LIMIT + 1; $i++) {
				$cTask[] = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
				$eTaskOther = $this->createTask($this->getCultivation(), ACTION_SEMIS_DIRECT, \series\Task::TODO, $week);
			}

			[$document] = $this->renderTimeDay();

			$eTaskRetained = $cTask->last();
			$action = $eTaskRetained['action']['id'];
			$actionOther = $eTaskOther['action']['id'];

			if($this->assertDecorPresent($document->querySelector('#planning-time-late-board #planning-time-task-'.$eTaskRetained['id']) !== NULL and $document->getElementById('planning-time-late-more-'.$action) !== NULL, 'la ligne est montrée dans un bloc plafonné') === FALSE) {
				return;
			}

			[, $instructions] = $this->callTimeDay('doTime', ['task' => $eTaskRetained['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTaskRetained));
			$this->apply($document, $instructions);

			$this->apply($document, $this->callTimeZoneMore($document, TaskLateLib::LATE, $action));

			$this->assertEquals(TaskLateLib::LATE.'-'.$action, $document->getElementById('planning-time-zones')->getAttribute('data-expanded'), 'le bloc déplié n\'est pas retenu pour les gestes suivants');

			$this->apply($document, $this->callTimeZoneMore($document, TaskLateLib::LATE, $actionOther));

			$expanded = TaskLateLib::LATE.'-'.$action.','.TaskLateLib::LATE.'-'.$actionOther;

			$this->assertEquals($expanded, $document->getElementById('planning-time-zones')->getAttribute('data-expanded'), '« Voir les N autres » d\'un autre bloc replie le premier pour les gestes suivants');

			[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTaskArrival['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTaskArrival));

			$this->assertEquals('table', $data?->change, 'la ligne sortie de la semaine ne redessine pas la liste');
			$this->checkTime($document, $instructions, 'ligne sortie de la semaine pour un bloc déplié qui portait une ligne gardée', input: ['expanded' => $expanded]);

			$ids = [];

			foreach($document->querySelectorAll('#planning-time-late-board > .planning-time-row') as $row) {
				$ids[] = $row->getAttribute('id');
			}

			$this->assertEquals(count($ids), count(array_unique($ids)), 'une ligne du bloc est à l\'écran deux fois');

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

	}

	/**
	 * La décoche de la seule ligne de la liste, une intervention en retard faite aujourd'hui, qui repart dans
	 * « En retard » : la liste redessinée est celle du rechargement.
	 */
	public function testTimeListEmptied(): void {

		$week = toWeek(\Clock::date().' - 7 DAYS');

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);
		$eTask = $this->createTaskDoneLate($week);

		[$document] = $this->renderTimeDay();

		if($this->assertDecorPresent($document->querySelectorAll('#planning-time-table .planning-time-row')->length === 1 and $document->querySelector('#planning-time-tools .planning-tools-actions') !== NULL, 'la ligne est la seule de la liste, sous « + Intervention »') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTask));

		$this->assertEquals('table', $data?->change, 'la ligne sortie de la semaine ne redessine pas la liste');
		$this->checkTime($document, $instructions, 'dernière ligne de la liste décochée');

	}

	/**
	 * La même décoche quand « Uniquement assigné à moi » masque une autre ligne de la semaine.
	 */
	public function testTimeListEmptiedFiltered(): void {

		$week = toWeek(\Clock::date().' - 7 DAYS');

		$this->createTaskWitness();
		$eTask = $this->createTaskDoneLate($week);

		$eTask['plannedUsers'] = [];
		\series\TaskLib::updateUser(new \Collection([$eTask]), $this->getMember(), 'add');

		\structure\MemberLib::setView('viewPlanningMine', \structure\StructureLib::getById($this->eStructure['id']), TRUE);

		[$document] = $this->renderTimeDay();

		if($this->assertDecorPresent($document->querySelectorAll('#planning-time-table .planning-time-row')->length === 1, 'la ligne est la seule que le filtre laisse dans la liste') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTask['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTask));

		$this->assertEquals('table', $data?->change, 'la ligne sortie de la semaine ne redessine pas la liste');
		$this->checkTime($document, $instructions, 'dernière ligne d\'une liste filtrée décochée');

	}

	/**
	 * Le nombre de suites de `testTimeZoneSequences()`, ce qu'un lancement du paquet peut porter : une suite
	 * rend la fiche une dizaine de fois, soit une demi-seconde.
	 */
	const ZONE_SEQUENCES = 100;

	/**
	 * CF · Des suites de gestes tirées au sort sur « En retard », la graine fixe pour qu'un écart se rejoue :
	 * du temps posé, changé ou remis à zéro sur une ligne de la zone, sa coche, une série close entre
	 * l'affichage et le geste, sur la ligne du geste comme sur une ligne déjà gardée, une ligne de la liste
	 * qui rejoint la zone, « Voir les N autres », avec ou sans « Uniquement assigné à moi », de part et
	 * d'autre du seuil du plafond. Chaque fois que l'écran ne garde plus aucune ligne, il est celui du
	 * rechargement.
	 *
	 * Un tirage ne promet pas qu'une famille de défauts tombe dans le nombre de suites qu'un lancement du
	 * paquet peut porter : chaque famille connue a donc son test dirigé (`testTimeZoneRetained*`,
	 * `testTimeZoneDepartureRetained*`, `testTimeListEmptied*`), et ce banc cherche celles que personne n'a
	 * encore écrites.
	 */
	public function testTimeZoneSequences(): void {

		$threshold = TaskLateLib::$threshold;

		$randomizer = new \Random\Randomizer(new \Random\Engine\Mt19937(20261013));

		$gaps = [];
		$compared = 0;

		try {

			for($sequence = 0; $sequence < self::ZONE_SEQUENCES; $sequence++) {

				$result = $this->runTimeZoneSequence($randomizer);

				$compared += $result['compared'];

				if($result['gap'] !== '') {
					$gaps[] = 'suite '.$sequence.' : '.$result['gap'];
				}

			}

		} finally {
			TaskLateLib::$threshold = $threshold;
		}

		$this->assertDecorPresent($compared >= self::ZONE_SEQUENCES, 'les suites mènent en moyenne à une comparaison au moins');
		$this->assertEquals([], array_slice($gaps, 0, 2), count($gaps).' suites sur '.self::ZONE_SEQUENCES.' s\'écartent du rechargement');

	}

	/**
	 * Une suite : son décor, ses gestes, puis le décor retiré, la transaction du test portant toutes les
	 * suites. Rend le nombre d'écrans comparés au rechargement et le premier écart, avec les gestes qui y
	 * mènent.
	 */
	private function runTimeZoneSequence(\Random\Randomizer $randomizer): array {

		$week = toWeek(\Clock::date().' - 7 DAYS');
		$eStructure = \structure\StructureLib::getById($this->eStructure['id']);

		$cTask = new \Collection();
		$cTaskTouchable = new \Collection();
		$cTaskArrival = new \Collection();
		$seriesOpen = [];
		$done = [];
		$timed = [];
		$log = [];

		$mine = ($randomizer->getInt(0, 2) === 0);
		$large = ($randomizer->getInt(0, 4) === 0);

		if($randomizer->getInt(0, 3) > 0) {
			$cTask[] = $this->createTaskWitness();
			$log[] = 'témoin';
		}

		$actions = array_slice([ACTION_PLANTATION, ACTION_SEMIS_DIRECT, ACTION_TRAITEMENT], 0, $randomizer->getInt(1, 3));

		foreach($actions as $position => $action) {

			$lines = ($large and $position === 0) ? TaskLateLib::LIMIT + $randomizer->getInt(0, 2) : $randomizer->getInt(1, 3);

			for($i = 0; $i < $lines; $i++) {

				$closable = ($randomizer->getInt(0, 2) === 0);
				$eCultivation = $closable ? $this->createCultivation($this->createSeries('Série close du décor')) : $this->getCultivation();

				$eTask = $this->createTask($eCultivation, $action, \series\Task::TODO, $week);

				$cTask[] = $eTask;
				$cTaskTouchable[$eTask['id']] = $eTask;

				if($closable) {
					$seriesOpen[$eTask['id']] = $eCultivation['series'];
				}

			}

			// Une ligne commentée que la suite ne touche pas : sa table garde sa colonne des commentaires, que
			// le départ de sa dernière ligne commentée laisserait ouverte jusqu'au rechargement.
			if($randomizer->getInt(0, 3) === 0) {
				$eTask = $this->createTask($this->getCultivation(), $action, \series\Task::TODO, $week);
				$this->createComment($eTask, 'Commentaire du décor', '08:00:00');
				$cTask[] = $eTask;
			}

			for($i = $randomizer->getInt(0, 2); $i > 0; $i--) {

				$eTask = $this->createTaskDoneLate($week, $action);

				if($randomizer->getInt(0, 3) === 0) {
					$this->createComment($eTask, 'Commentaire du décor', '09:00:00');
				}

				$cTask[] = $eTask;
				$cTaskArrival[$eTask['id']] = $eTask;

			}

		}

		if($mine) {

			foreach($cTask as $eTask) {

				if($randomizer->getInt(0, 3) > 0) {
					$eTask['plannedUsers'] = [];
					\series\TaskLib::updateUser(new \Collection([$eTask]), $this->getMember(), 'add');
				}

			}

		}

		\structure\MemberLib::setView('viewPlanningMine', $eStructure, $mine);

		$count = $cTaskTouchable->count();

		TaskLateLib::$threshold = max(0, [0, $count - 2, $count - 1, $count, 300][$randomizer->getInt(0, 4)]);

		$log[] = 'décor de '.$cTask->count().' interventions, seuil '.TaskLateLib::$threshold.($mine ? ', assigné à moi' : '');

		[$document] = $this->renderTimeDay();

		$compared = 0;
		$gap = '';

		for($step = $randomizer->getInt(3, 8); $step > 0 and $gap === ''; $step--) {

			$zones = $document->getElementById('planning-time-zones');

			$retained = [];

			foreach(array_filter(explode(',', $zones->getAttribute('data-retained'))) as $value) {
				$retained[] = (int)explode('-', $value)[2];
			}

			$rows = [];

			foreach($document->querySelectorAll('#planning-time-late-board > .planning-time-row') as $row) {

				$id = (int)$row->getAttribute('data-task');

				if($cTaskTouchable->offsetExists($id)) {
					$rows[] = $id;
				}

			}

			$arrivals = [];

			foreach($document->querySelectorAll('#planning-time-list .planning-time-row') as $row) {

				$id = (int)$row->getAttribute('data-task');

				if($cTaskArrival->offsetExists($id)) {
					$arrivals[] = $id;
				}

			}

			$mores = [];

			foreach($document->querySelectorAll('#planning-time-late-board > [id^="planning-time-late-more-"]') as $more) {
				$mores[] = (int)substr($more->getAttribute('id'), strlen('planning-time-late-more-'));
			}

			$rowsTodo = array_values(array_diff($rows, $done));
			$rowsOpen = array_values(array_intersect($rows, array_keys($seriesOpen)));
			$retainedOpen = array_values(array_intersect($retained, array_keys($seriesOpen)));

			$gestures = [];

			if($rows !== []) {
				$gestures[] = 'time';
				$gestures[] = 'time';
			}

			if($rowsTodo !== []) {
				$gestures[] = 'done';
			}

			if($rowsOpen !== []) {
				$gestures[] = 'close';
				$gestures[] = 'close';
			}

			if($retainedOpen !== []) {
				$gestures[] = 'closeRetained';
			}

			if($arrivals !== []) {
				$gestures[] = 'arrival';
				$gestures[] = 'arrival';
			}

			if($mores !== []) {
				$gestures[] = 'more';
			}

			if($gestures === []) {
				break;
			}

			$gesture = $gestures[$randomizer->getInt(0, count($gestures) - 1)];

			$pick = fn(array $ids): int => $ids[$randomizer->getInt(0, count($ids) - 1)];

			$data = new \stdClass();
			$instructions = [];

			switch($gesture) {

				case 'time' :
				case 'close' :

					$id = $pick(($gesture === 'close') ? $rowsOpen : $rows);
					$eTask = $cTaskTouchable[$id];

					if($gesture === 'close') {
						$this->closeSeries($seriesOpen[$id]);
						unset($seriesOpen[$id]);
					}

					// Une ligne qui a du temps le voit changé ou remis à zéro, ce qui la rend à sa zone en base.
					if(in_array($id, $timed, TRUE)) {
						$time = ['0', '1.5'][$randomizer->getInt(0, 1)];
					} else {
						$time = '0.5';
					}

					$timed = ($time === '0') ? array_values(array_diff($timed, [$id])) : array_merge($timed, [$id]);

					[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $id, 'date' => \Clock::date(), 'time' => $time] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTask));

					$log[] = (($gesture === 'close') ? 'série close puis ' : '').$time.' h sur '.$id.(in_array($id, $retained, TRUE) ? ', gardée' : '').' ('.($data?->change ?? 'refusé').', '.count($retained).' gardées)';
					break;

				case 'done' :

					$id = $pick($rowsTodo);
					$done[] = $id;

					[$data, $instructions] = $this->callTimeDay('doDone', ['task' => $id] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $cTaskTouchable[$id]));

					$log[] = 'coche de '.$id.' ('.($data?->change ?? 'refusé').')';
					break;

				case 'closeRetained' :

					$id = $pick($retainedOpen);

					$this->closeSeries($seriesOpen[$id]);
					unset($seriesOpen[$id]);

					$log[] = 'série close sur '.$id.', gardée';
					continue 2;

				case 'arrival' :

					$id = $pick($arrivals);
					$eTask = $cTaskArrival[$id];

					[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $id, 'columns' => $document->getElementById('planning-time-table')->getAttribute('data-columns')] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTask));

					$log[] = 'décoche de '.$id.' dans la liste ('.($data?->change ?? 'refusé').', '.count($retained).' gardées, étapes '.implode(' ', $this->getZoneSteps($data)).')';
					break;

				case 'more' :

					$action = $pick($mores);
					$instructions = $this->callTimeZoneMore($document, TaskLateLib::LATE, $action);

					$log[] = '« Voir les autres » de '.$action;
					break;

			}

			if($data === NULL or $instructions === []) {
				$gap = 'geste refusé ou sans instruction';
				break;
			}

			$this->apply($document, $instructions);

			// Tant qu'une ligne est gardée, l'écran diffère du rechargement, et c'est voulu (R-Place).
			if($document->getElementById('planning-time-zones')->getAttribute('data-retained') !== '') {
				continue;
			}

			[$documentExpected] = $this->renderTimeDay(['expanded' => $document->getElementById('planning-time-zones')->getAttribute('data-expanded')]);

			$gap = $this->getGap($this->getFingerprint($documentExpected), $this->getFingerprint($document));
			$compared++;

		}

		\structure\MemberLib::setView('viewPlanningMine', $eStructure, FALSE);

		\series\TaskLib::deleteCollection($cTask);

		return [
			'compared' => $compared,
			'gap' => ($gap === '') ? '' : implode(' · ', $log).$gap,
			'log' => $log,
		];

	}

	/**
	 * Du temps sur une ligne de « En retard », qui la garde dans sa zone, puis la décoche d'une ligne de la
	 * liste qui rejoint la zone : l'écran est celui du rechargement, sans identifiant en double.
	 */
	private function checkTimeZoneRetained(\series\Task $eTaskRetained, \series\Task $eTaskArrival, array $steps, string $case): void {

		[$document] = $this->renderTimeDay();

		[$data, $instructions] = $this->callTimeDay('doTime', ['task' => $eTaskRetained['id'], 'date' => \Clock::date(), 'time' => '0.5'] + $this->getTimeZonesInput($document) + $this->getTimeZonePosition($document, $eTaskRetained));

		if($this->assertDecorPresent($data?->change === 'row', 'la ligne qui reçoit du temps reste dans sa zone') === FALSE) {
			return;
		}

		$this->apply($document, $instructions);

		[$data, $instructions] = $this->callTimeDay('doTodo', ['task' => $eTaskArrival['id']] + $this->getTimeZonesInput($document) + $this->getTimePosition($document, $eTaskArrival));

		$this->assertEquals('table', $data?->change, $case.' : la ligne sortie de la semaine ne redessine pas la liste');
		$this->assertEquals($steps, $this->getZoneSteps($data), $case.' : les étapes de la zone');
		$this->checkTime($document, $instructions, $case);

		$this->assertEquals(1, $document->querySelectorAll('[id="planning-time-task-'.$eTaskRetained['id'].'"]')->length, $case.' : la ligne gardée est à l\'écran deux fois');
		$this->assertEquals(1, $document->querySelectorAll('[id="planning-time-late-action-'.$eTaskRetained['action']['id'].'"]')->length, $case.' : l\'intitulé du bloc est à l\'écran deux fois');
		$this->assertTrue($document->querySelector('#planning-time-late-board #planning-time-task-'.$eTaskArrival['id']) !== NULL, $case.' : la ligne sortie de la semaine n\'est pas dans « En retard »');

	}

	/**
	 * Une intervention en retard, prévue la semaine donnée et faite aujourd'hui : elle est dans la liste, et sa
	 * décoche la renvoie dans « En retard ».
	 */
	private function createTaskDoneLate(string $week, string $action = ACTION_PLANTATION): \series\Task {

		$eTask = $this->createTask($this->getCultivation(), $action, \series\Task::TODO, $week);
		\series\TaskLib::updateDoneCollection(new \Collection([$eTask]), \Clock::week());

		// `updateDoneCollection()` fait l'intervention dans sa semaine prévue. Sans `build()`, qui bornerait le
		// jour du fait saisi : le décor le pose à aujourd'hui, que ce contrôle accepte.
		$eTask['doneWeek'] = \Clock::week();
		$eTask['doneDate'] = \Clock::date();
		\series\TaskLib::update($eTask, ['doneWeek', 'doneDate']);

		return $eTask;

	}

	/**
	 * Ce que les zones de l'écran font réémettre à un geste, comme le client (`PlanningTimeDay.getBody()`).
	 */
	private function getTimeZonesInput(\Dom\HTMLDocument $document): array {

		$zones = $document->getElementById('planning-time-zones');

		$commented = [];

		foreach($document->querySelectorAll('#planning-time-zones .planning-time-table-comments') as $table) {
			$commented[] = $table->closest('[data-planning-time-zone]')->getAttribute('data-planning-time-zone');
		}

		return [
			'unplanned' => $zones->getAttribute('data-unplanned'),
			'folded' => $zones->getAttribute('data-folded'),
			'expanded' => $zones->getAttribute('data-expanded'),
			'retained' => $zones->getAttribute('data-retained'),
			'commented' => implode(',', $commented),
		];

	}

	/**
	 * Les types des étapes qu'un geste applique dans les zones (`TimeDayLib::getZoneSteps()`), dans leur ordre.
	 */
	private function getZoneSteps(?\stdClass $data): array {

		$types = [];

		foreach($data?->zoneChanges ?? [] as $zoneChange) {
			$types = array_merge($types, array_column($zoneChange['steps'], 'type'));
		}

		return $types;

	}

	/**
	 * Si des instructions rendent l'une des lignes données.
	 */
	private function hasRows(array $instructions, array $ids): bool {

		$json = json_encode($instructions);

		foreach($ids as $id) {
			if(str_contains($json, 'planning-time-task-'.$id.'\\"')) {
				return TRUE;
			}
		}

		return FALSE;

	}

	/**
	 * « Voir les N autres » d'une action d'une zone, avec ce que le client réémet (`PlanningTimeZone.more()`) :
	 * la variante de la table et les blocs déjà dépliés.
	 */
	private function callTimeZoneMore(\Dom\HTMLDocument $document, string $zone, int $action): array {

		$view = $this->executePage('planning/timeDay:more', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'zone' => $zone,
			'action' => $action,
			'columns' => $document->getElementById('planning-time-'.$zone.'-board')->getAttribute('data-columns'),
			'expanded' => $document->getElementById('planning-time-zones')->getAttribute('data-expanded'),
			'retained' => $document->getElementById('planning-time-zones')->getAttribute('data-retained'),
		], 'GET');

		if($this->assertTrue($view instanceof \ViewAction, '« Voir les N autres » est refusé') === FALSE) {
			return [];
		}

		[, $template] = $this->runView('more', $view, 'timeDay');

		return $template->formatInstructions();

	}

	/**
	 * La place d'une ligne de zone, que son geste réémet comme le client (`PlanningTimeDay.post()`) : sa zone,
	 * son rang dans son action, son jour, et la variante de sa table.
	 */
	private function getTimeZonePosition(\Dom\HTMLDocument $document, \series\Task $eTask): array {

		$row = $document->getElementById('planning-time-task-'.$eTask['id']);

		if($this->assertTrue($row?->closest('[data-planning-time-zone]') !== NULL, 'la ligne '.$eTask['id'].' n\'est pas dans une zone') === FALSE) {
			return [];
		}

		return [
			'zone' => $row->closest('[data-planning-time-zone]')->getAttribute('data-planning-time-zone'),
			'actionFirst' => $row->getAttribute('data-action-first'),
			'dayWeek' => $row->getAttribute('data-day-week'),
			'columns' => $row->closest('.planning-time-table')->getAttribute('data-columns'),
		];

	}

	/**
	 * La fiche après un geste sur une ligne de zone, comparée au rechargement. La ligne du geste reste dans sa
	 * zone (R-Place) : les zones attendues sont celles de l'écran d'avant, où seule cette ligne est rendue de
	 * nouveau, et la liste attendue est celle du rechargement sans elle. `$position` est celle de l'écran qui
	 * a porté le premier geste, quand la ligne a déjà été remplacée. `$zoneCommented` est la zone de l'écran
	 * d'avant quand le geste y ouvre la colonne des commentaires : sa table attendue est celle de cette zone
	 * rendue entière dans la variante commentée.
	 */
	private function checkTimeZone(\Dom\HTMLDocument $before, array $instructions, string $case, \series\Task $eTask, array $position = [], array $zoneCommented = []): void {

		$this->assertTrue($instructions !== [], $case.' : le geste n\'a rendu aucune instruction');

		if($position === []) {
			$position = $this->getTimeZonePosition($before, $eTask);
		}

		$zonesBefore = $before->getElementById('planning-time-zones')->cloneNode(TRUE);

		$this->apply($before, $instructions);

		[$documentExpected, $dataExpected] = $this->renderTimeDay();

		$timeDay = TimeDayLib::get($dataExpected->eStructure, $dataExpected->week, $dataExpected->cTask, $dataExpected->date, $dataExpected->eUserTime, $dataExpected->hide, $dataExpected->mine, $eTask, NULL, $position['zone'], $position['actionFirst'] === '1');

		// La rangée des outils avec la liste : « + Intervention » ne paraît qu'avec une ligne dans la semaine.
		$documentExpected->getElementById('planning-time-tools')->replaceWith(...$this->parse($documentExpected, new TimeDayUi()->getTools($dataExpected->eStructure, $dataExpected->week, $dataExpected->date, $dataExpected->eUserTime, $timeDay, $dataExpected->eMember, ($dataExpected->canCreate and $dataExpected->canTime))));
		$documentExpected->getElementById('planning-time-list')->replaceWith(...$this->parse($documentExpected, new TimeDayUi()->getList($dataExpected->eStructure, $dataExpected->week, $dataExpected->date, $dataExpected->eUserTime, $timeDay, $dataExpected->canTime, ($dataExpected->canCreate and $dataExpected->canTime))));

		$eTaskLine = $timeDay['eTaskZoneKept'];

		if($eTaskLine->empty()) {
			$eTaskLine = TimeDayLib::getZoneLine(TaskLateLib::getTaskKept($dataExpected->eStructure, $eTask), 0.0, $position['actionFirst'] === '1');
		}

		$zonesExpected = $documentExpected->importNode($zonesBefore, TRUE);
		$documentExpected->getElementById('planning-time-zones')->replaceWith($zonesExpected);

		// L'écran retient la ligne du geste, la seule que ces contrôles gardent dans une zone.
		$zonesExpected->setAttribute('data-retained', $position['zone'].'-'.$eTask['action']['id'].'-'.$eTask['id'].'-'.$position['actionFirst']);

		$commented = ($position['columns'] === '3');

		if($zoneCommented !== []) {
			$zoneCommented['commented'] = TRUE;
			$commented = TRUE;
			$zonesExpected->querySelector('#planning-time-'.$zoneCommented['zone'].'-board')->replaceWith(...$this->parse($documentExpected, new TimeDayUi()->getZoneBoard($dataExpected->week, $dataExpected->date, $zoneCommented, $dataExpected->canTime)));
		}

		$zonesExpected->querySelector('#planning-time-task-'.$eTask['id'])->replaceWith(...$this->parse($documentExpected, new TimeDayUi()->getRow($dataExpected->week, $dataExpected->date, $eTaskLine, $dataExpected->canTime, $commented)));

		$expected = $this->getFingerprint($documentExpected);
		$actual = $this->getFingerprint($before);

		$this->assertTrue($expected === $actual, $case.' : empreinte après réponse ciblée et après rechargement'.$this->getGap($expected, $actual));

	}

	/**
	 * La structure du décor est une copie de la production, dont les lignes de la fiche portent déjà des
	 * commentaires : ils ouvriraient la colonne avant le geste éprouvé. Ils sont retirés dans la transaction
	 * du test, qui les rend au rollback.
	 */
	private function deleteComments(array $timeDay): void {

		foreach($timeDay['ccTaskWeek'] as $cTask) {

			foreach($cTask as $eTask) {

				foreach($eTask['cComment'] as $eComment) {
					\series\CommentLib::delete($eComment);
				}

			}

		}

	}

	/**
	 * Le nombre de cellules de la ligne que les instructions rendent pour une intervention.
	 */
	private function countCells(array $instructions, \series\Task $eTask): int {

		$document = \Dom\HTMLDocument::createEmpty();

		foreach($instructions as $instruction) {

			if($instruction[0] === 'js' or $instruction[1] !== '#planning-time-task-'.$eTask['id']) {
				continue;
			}

			foreach($instruction[2] as $call) {

				[$method, $arguments] = $call + [1 => []];

				if($method === 'outerHtml') {
					$cells = array_filter(
						iterator_to_array($this->parse($document, $arguments[0])[0]->childNodes),
						fn(\Dom\Node $node) => $node instanceof \Dom\Element
					);

					return count($cells);
				}

			}

		}

		return 0;

	}

	/**
	 * La fiche de l'équipe (R158) : la journée d'une autre personne, saisie puis vidée sur sa ligne par un
	 * membre au planning complet. La ligne, la pastille du jour et le jour de l'équipe suivent, et l'écran
	 * est celui d'un rechargement de l'onglet « Équipe » (contrôle de V-Planning).
	 */
	public function testTimeTeamWorking(): void {

		$eUserOther = $this->getUserOther();
		$monday = week_date_day(\Clock::week(), 1);
		$input = ['user' => TimeWeekLib::TEAM, 'date' => $monday];

		[$document] = $this->renderTimeDay($input);

		$this->assertTrue($document->getElementById('planning-time-team-lane-'.$eUserOther['id']) !== NULL, 'la fiche de l\'équipe ne porte pas la ligne de l\'autre personne');

		$this->checkTime($document, $this->callTimeTeam(['user' => $eUserOther['id'], 'date' => $monday, 'time' => '6.5']), 'journée saisie depuis l\'équipe', input: $input);
		$this->checkTime($document, $this->callTimeTeam(['user' => $eUserOther['id'], 'date' => $monday, 'time' => '0']), 'journée vidée depuis l\'équipe', input: $input);

	}

	/**
	 * Un jour à saisir qui porte des interventions (R-Barre-Interventions), sur la ligne d'une autre personne
	 * de la fiche de l'équipe : sa barre dessine leur part sans journée, puis la journée saisie et vidée la
	 * redessine dans les deux sens.
	 */
	public function testTimeTeamWorkingMissing(): void {

		$eUserOther = $this->getUserOther();
		$monday = week_date_day(\Clock::week(), 1);
		$input = ['user' => TimeWeekLib::TEAM, 'date' => $monday];

		$this->createTimesheetFor($this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::DONE, \Clock::week(), $monday), $eUserOther, 1.75, $monday);

		[$document] = $this->renderTimeDay($input);

		if($this->assertTrue($document->querySelector('#planning-time-team-lane-'.$eUserOther['id'].'.planning-week-time-lane-missing .planning-week-time-lane-done') !== NULL, 'la ligne de l\'autre personne n\'est pas un jour à saisir qui dessine ses interventions') === FALSE) {
			return;
		}

		$this->checkTime($document, $this->callTimeTeam(['user' => $eUserOther['id'], 'date' => $monday, 'time' => '6.5']), 'journée saisie depuis l\'équipe sur un jour qui porte des interventions', input: $input);
		$this->checkTime($document, $this->callTimeTeam(['user' => $eUserOther['id'], 'date' => $monday, 'time' => '0']), 'journée vidée depuis l\'équipe sur un jour qui porte des interventions', input: $input);

	}

	/**
	 * La journée saisie depuis l'équipe sur la ligne d'un absent qui a une journée ce jour-là (R-Retours-1210,
	 * FH) : il garde sa ligne tant qu'il a sa journée, puis la journée vidée le fait sortir du tableau, et la
	 * réponse redessine la carte du jour entière (`teamLeft`), comme le rechargement.
	 */
	public function testTimeTeamWorkingAbsent(): void {

		$eUserOther = $this->getUserOther();
		$monday = week_date_day(\Clock::week(), 1);
		$input = ['user' => TimeWeekLib::TEAM, 'date' => $monday];

		$this->enableHr();

		// Sans `build()`, qui vérifierait les dates d'un formulaire : le décor veut l'absence d'un seul jour.
		\hr\AbsenceLib::create(new \hr\Absence([
			'structure' => $this->eStructure,
			'user' => $eUserOther,
			'from' => $monday.' 00:00:00',
			'to' => $monday.' 23:59:59',
			'duration' => 1.0,
			'type' => \hr\Absence::VACATION,
		]));

		// Sans `build()`, qui bornerait la durée saisie : le décor veut une journée de 4 h déjà posée, avant le geste.
		\hr\WorkingTimeLib::create(new \hr\WorkingTime([
			'structure' => $this->eStructure,
			'user' => $eUserOther,
			'date' => $monday,
			'time' => 4.0,
		]));

		[$document, $data] = $this->renderTimeDay($input);

		if($this->assertDecorPresent($data->timeWeek['days'][$monday]['cUserAbsent']->offsetExists($eUserOther['id']) and $data->cUserPresent->offsetExists($eUserOther['id']), 'l\'autre personne absente lundi, avec sa ligne au tableau') === FALSE) {
			return;
		}

		$this->checkTime($document, $this->callTimeTeam(['user' => $eUserOther['id'], 'date' => $monday, 'time' => '6.5']), 'journée saisie depuis l\'équipe sur la ligne d\'un absent', input: $input);
		$this->checkTime($document, $this->callTimeTeam(['user' => $eUserOther['id'], 'date' => $monday, 'time' => '0']), 'journée vidée depuis l\'équipe sur la ligne d\'un absent', input: $input);

		$this->assertTrue($document->getElementById('planning-time-team-lane-'.$eUserOther['id']) === NULL, 'l\'absent dont la journée est vidée garde sa ligne');

	}

	/**
	 * Une seconde personne sur la fiche de la semaine, autre que le membre du décor : la première que la
	 * fiche liste déjà, sinon un autre membre de la structure, rendu présent dans la transaction du test.
	 */
	private function getUserOther(): \user\User {

		$eUserMember = $this->getMember();

		foreach(TimeDayLib::getUsers($this->eStructure, \Clock::week()) as $eUser) {
			if($eUser->is($eUserMember) === FALSE) {
				return $eUser;
			}
		}

		foreach(\structure\MemberLib::getByStructure($this->eStructure, index: NULL) as $eMember) {

			if($eMember['user']->notEmpty() and $eMember['user']->is($eUserMember) === FALSE) {

				$this->addPresence($eMember['user']);

				return $eMember['user'];

			}

		}

		$this->assertFail('la structure '.static::STRUCTURE.' n\'a pas de second membre pour former une équipe');

		return new \user\User();

	}

	/**
	 * La journée saisie sur une ligne de la fiche de l'équipe (`origin=team`), rendue par sa vue.
	 */
	private function callTimeTeam(array $input): array {

		$action = $this->executePage('planning/timeDay:doWorkingTime', $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'origin' => TimeWeekLib::TEAM,
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la journée saisie depuis l\'équipe est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return [];
		}

		[, $template] = $this->runView('doWorkingTimeTeam', $action, 'timeDay');

		return $template->formatInstructions();

	}

	/**
	 * Une intervention de plantation à faire cette semaine, sur la culture du décor.
	 */
	/**
	 * Un refus sur le champ par pas, comme le client le vit : la durée affichée puis enregistrée
	 * (`PlanningDurationStep.show()`, `save()`), la réponse, qui ne porte que le message, puis la fenêtre
	 * refermée, qui remet la durée d'avant, lue dans `data-sent` (`PlanningDurationStep.refuse()`, `close()`).
	 */
	private function checkRefusedStepped(\Dom\HTMLDocument $document, ?\Action $action, string $selector, float $time, string $fail, string $case): void {

		if($this->assertTrue($action instanceof \FailAction, $case.' : le geste n\'est pas refusé') === FALSE) {
			return;
		}

		$this->assertEquals([$fail], $this->getFails($action), $case.' : le refus n\'est pas celui attendu');

		$field = $document->querySelector($selector);

		if($this->assertTrue($field !== NULL, $case.' : le champ manque') === FALSE) {
			return;
		}

		$before = (float)$field->getAttribute('data-sent');

		$this->writeStepped($field, $time);

		$template = new \AjaxTemplate();
		$template->js()->errors(new \ReflectionProperty(\FailAction::class, 'fail')->getValue($action));
		$instructions = $template->formatInstructions();

		$this->assertEquals([], array_filter($instructions, fn($instruction) => $instruction[0] !== 'js'), $case.' : le refus redessine une partie de l\'écran');
		$this->assertTrue(in_array('errors', array_column(array_merge(...array_column($instructions, 1)), 0), TRUE), $case.' : le refus ne porte pas l\'instruction errors que lit planning.js');

		$this->apply($document, $instructions);

		$this->writeStepped($field, $before);

	}

	/**
	 * Ce que `PlanningDurationStep.write()` écrit dans le champ par pas : les deux cases, la valeur cachée, et
	 * la teinte du temps passé ou de zéro sur le conteneur, qu'un temps prévu ne prend pas. La classe s'écrit
	 * comme `DurationUi::getStepped()` la rend, l'ordre des classes n'y changeant rien au rendu.
	 */
	private function writeStepped(\Dom\Element $field, float $time): void {

		[$hour, $minute] = DurationUi::splitTime($time);

		$field->querySelector('input[name="timeAddHour"]')->setAttribute('value', (string)$hour);
		$field->querySelector('input[name="timeAddMinute"]')->setAttribute('value', sprintf('%02d', $minute));
		$field->querySelector('input[name="timeAdd"]')->setAttribute('value', (string)$time);

		$classes = ['planning-duration-step'];
		$expected = $field->classList->contains('planning-duration-step-expected');

		if($expected) {
			$classes[] = 'planning-duration-step-expected';
		}

		if($time <= 0.0) {
			$classes[] = 'planning-duration-step-zero';
		} else if($expected === FALSE) {
			$classes[] = 'planning-duration-step-spent';
		}

		$field->setAttribute('class', implode(' ', $classes));

	}

	/**
	 * Le menu du jour `$date` d'une ligne de la grille, ouvert dans `$document` comme le client le remplit à son
	 * ouverture (`PlanningTaskGrid.load()`).
	 */
	private function openTime(\Dom\HTMLDocument $document, \series\Task $eTask, string $date): void {

		$action = $this->executePage('planning/taskWeek:time', [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
			'task' => $eTask['id'],
			'date' => $date,
		], 'GET');

		if($this->assertTrue($action instanceof \ViewAction, 'le menu du jour ne s\'ouvre pas : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		[, $template] = $this->runView('time', $action, 'taskWeek');

		$this->apply($document, $template->formatInstructions());

	}

	/**
	 * Une route de la Semaine sans intervention désignée (`doCreate`, les gestes de lot), rendue par sa
	 * vue.
	 */
	private function callWeek(string $route, string $view, array $input): array {

		// « Quand » s'ouvre sur la semaine de l'écran : sans `when`, l'intervention part pour elle, comme du panneau tel qu'il s'ouvre.
		if($route === 'doCreate') {
			$input += ['when' => $input['week'] ?? \Clock::week()];
		}

		$action = $this->executePage('planning/taskWeek:'.$route, $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, $route.' est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return [NULL, [], $action];
		}

		[$data, $template] = $this->runView($view, $action, 'taskWeek');

		return [$data, $template->formatInstructions(), $action];

	}

	private function createDayReminder(string $week, string $text, ?string $date = NULL): \planning\Reminder {

		// Sans `build()` : le décor pose un texte court et un jour de la semaine du rappel, ce que
		// `ReminderWeekTest::testCreate()` éprouve par la route.
		$eReminder = new \planning\Reminder([
			'structure' => $this->eStructure,
			'week' => $week,
			'date' => $date,
			'text' => $text,
		]);

		\planning\ReminderLib::create($eReminder);

		return $eReminder;

	}

	/**
	 * Un geste du pense-bête posté au Jour de la semaine courante, rendu par `doReminder` : ses instructions.
	 */
	private function callDayReminder(string $route, array $input): array {

		$action = $this->executePage('planning/taskDay:'.$route, $input + [
			'id' => $this->eStructure['id'],
			'week' => \Clock::week(),
		], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, $route.' est refusé : '.implode(', ', $this->getFails($action))) === FALSE) {
			return [];
		}

		[, $template] = $this->runView('doReminder', $action, 'taskDay');

		return $template->formatInstructions();

	}

	/**
	 * Une intervention faite lundi, d'une action qu'aucun cas ne touche : l'écran n'est pas vide, et un cas
	 * qui partirait d'un écran vide ou qui le viderait garde sa réponse ciblée, au lieu de l'écran entier
	 * qui retire ou rend la rangée des outils (R-Retours-0410, point 18, `TaskWeekLib::isEmpty()`).
	 */
	private function createTaskWitness(): \series\Task {

		return $this->createTask($this->getCultivation(), ACTION_FERTILISATION, \series\Task::DONE, \Clock::week(), week_date_day(\Clock::week(), 1));

	}

	private function createComments(\series\Task $eTask, array $texts): void {

		foreach($texts as $text) {

			// Sans `build()` : le décor pose un texte court, non vide, ce que la route de « Commenter »
			// (`series/comment:doCreateCollection`) vérifie.
			\series\CommentLib::create(new \series\Comment([
				'task' => $eTask,
				'text' => $text,
			]));

		}

	}

	private function createTaskWeek(float $timeExpected = 0.0): \series\Task {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());

		if($timeExpected > 0.0) {
			$eTask->build(['timeExpected'], ['timeExpected' => (string)$timeExpected]);
			\series\TaskLib::update($eTask, ['timeExpected']);
		}

		return $eTask;

	}

	/**
	 * La marque d'une intervention à l'écran : le rond vide sur chacune de ses cartes tant qu'elle est à
	 * faire, le rond coché une fois faite, jamais les deux. Le Jour peut porter plusieurs cartes d'une intervention
	 * (`TaskDayLib`), d'où un compte par sorte et non une carte. La comparaison au rechargement ne le dit
	 * pas : une réponse et un rechargement qui perdraient tous deux la marque restent égaux.
	 */
	private function checkMark(\Dom\HTMLDocument $document, \series\Task $eTask, bool $done, string $case): void {

		$empty = $document->querySelectorAll('.planning-task-mark:not(.planning-task-mark-done)[data-planning-task-done="'.$eTask['id'].'"]')->length;
		$checked = $document->querySelectorAll('.planning-task-mark-done[data-planning-task-todo="'.$eTask['id'].'"]')->length;

		$this->assertEquals(['vide' => $done === FALSE, 'coché' => $done], ['vide' => $empty > 0, 'coché' => $checked > 0], $case.' : marque de l\'intervention à l\'écran ('.$empty.' ronds vides, '.$checked.' ronds cochés)');

	}

	/**
	 * L'écran des interventions d'avant, les instructions appliquées, contre l'écran rendu après. La bulle
	 * se retire des deux côtés. Sous le masque, la carte gardée (`$eTaskKept`) doit porter l'état fait,
	 * puis se retire, avec son bloc s'il ne portait qu'elle : le rechargement ne la montre plus. Les rangs
	 * des blocs se neutralisent alors, un bloc retiré décalant les suivants.
	 */
	private function checkTasks(\Dom\HTMLDocument $before, array $instructions, string $case, \series\Task $eTaskKept = new \series\Task(), bool $unplanned = FALSE, string $folded = ''): void {

		$this->assertTrue($instructions !== [], $case.' : le geste n\'a rendu aucune instruction');

		$this->apply($before, $instructions);

		foreach($before->querySelectorAll('.planning-task-bubble') as $bubble) {
			$bubble->remove();
		}

		if($eTaskKept->notEmpty()) {

			$card = $before->getElementById('planning-task-'.$eTaskKept['id']);

			if($this->assertTrue($card !== NULL, $case.' : la carte cochée n\'est plus à l\'écran') === FALSE) {
				return;
			}

			$this->assertTrue($card->classList->contains('planning-task-done'), $case.' : la carte gardée ne porte pas l\'état fait');

			$block = $card->closest('.planning-week-block');
			$card->remove();

			if($block !== NULL and $block->querySelector('.planning-task') === NULL) {
				$block->remove();
			}

		}

		$expected = $this->getFingerprint($this->renderTasks(unplanned: $unplanned, folded: $folded)[0]);
		$actual = $this->getFingerprint($before);

		$this->assertTrue($expected === $actual, $case.' : empreinte après réponse ciblée et après rechargement'.$this->getGap($expected, $actual));

	}

	/**
	 * La Semaine en Colonnes, comme `renderTasks()`.
	 */
	private function renderTasksColumns(): array {

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/semaine/'.\Clock::week(), ['view' => \structure\Member::COLUMNS], 'GET');

		$this->assertTrue($action instanceof \ViewAction, 'l\'écran des interventions ne se rend pas en Colonnes');

		[$data, , $html] = $this->runView('taskWeek', $action, 'taskWeek');

		return [
			\Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR),
			$data
		];

	}

	/**
	 * Les Colonnes d'avant, les instructions appliquées, contre les Colonnes rechargées dont les deux colonnes et
	 * les comptes des onglets sont rendus avec les cartes gardées `$tasksKept`, comme la page les rend après un
	 * geste. La bulle est le geste en cours, qu'un rechargement ne rouvre pas : elle se retire avant.
	 */
	private function checkTasksColumns(\Dom\HTMLDocument $before, array $instructions, string $case, array $tasksKept): void {

		$this->assertTrue($instructions !== [], $case.' : le geste n\'a rendu aucune instruction');

		$this->apply($before, $instructions);

		foreach($before->querySelectorAll('.planning-task-bubble, #planning-task-sheet') as $bubble) {
			$bubble->remove();
		}

		[$expected, $data] = $this->renderTasksColumns();

		$columns = TaskWeekLib::getColumns($data->cTask, $tasksKept, $data->order);

		$uiTaskWeek = new TaskWeekUi();

		$board = $uiTaskWeek->getColumns($this->eStructure, $data->week, $columns['ccTaskTodo'], $columns['ccTaskDone'], $columns['countByAction'], $data->cUser, $data->canCreateByStatus, $data->reminders, $data->canReminder);

		$expected->getElementById('planning-week-board')->replaceWith(...$this->parse($expected, $board));

		foreach([\series\Task::TODO, \series\Task::DONE] as $status) {
			$expected->getElementById('planning-week-'.$status.'-counter')->replaceWith(...$this->parse($expected, $uiTaskWeek->getColumnCounter($status, $columns['countByStatus'][$status])));
		}

		$this->assertEquals(count($tasksKept), $before->querySelectorAll('#planning-week-todo .planning-task-done')->length, $case.' : « À faire » ne garde pas les cartes cochées');

		$expectedFingerprint = $this->getFingerprint($expected);
		$actual = $this->getFingerprint($before);

		$this->assertTrue($expectedFingerprint === $actual, $case.' : empreinte après réponse ciblée et après rechargement avec les mêmes cartes gardées'.$this->getGap($expectedFingerprint, $actual));

	}

	/**
	 * Le Jour d'avant, les instructions appliquées, contre le Jour rendu après. Deux sortes de cartes ne
	 * sont qu'à l'écran et se retirent avant de comparer, après avoir vérifié qu'elles portent l'état fait :
	 * la carte gardée sous le masque (`$eTaskHidden`), dans la colonne d'aujourd'hui, et celles que « Sans
	 * jour » garde (`$tasksKept`), qui doivent y être toutes, et seules. Une colonne vidée par ce retrait
	 * prend l'état vide que le rechargement montre.
	 */
	private function checkDay(\Dom\HTMLDocument $before, array $instructions, string $case, \series\Task $eTaskHidden = new \series\Task(), array $tasksKept = [], ?string $week = NULL): void {

		$this->assertTrue($instructions !== [], $case.' : le geste n\'a rendu aucune instruction');

		$this->apply($before, $instructions);

		$this->assertEquals($tasksKept, array_keys($this->getKept($before)), $case.' : « Sans jour » ne garde pas les cartes faites attendues');

		$cards = [];

		foreach($tasksKept as $task) {
			$cards[TaskDayLib::NONE][] = $before->getElementById('planning-task-'.$task.'-'.TaskDayLib::NONE);
		}

		if($eTaskHidden->notEmpty()) {
			$cards[\Clock::date()][] = $before->getElementById('planning-task-'.$eTaskHidden['id'].'-'.\Clock::date());
		}

		foreach($cards as $columnKey => $cardsColumn) {

			foreach($cardsColumn as $card) {

				if($this->assertTrue($card !== NULL, $case.' : une carte gardée n\'est plus à l\'écran') === FALSE) {
					return;
				}

				$this->assertTrue($card->classList->contains('planning-task-done'), $case.' : une carte gardée ne porte pas l\'état fait');

				$group = $card->closest('.planning-day-action');
				$card->remove();

				if($group !== NULL and $group->querySelector('.planning-task') === NULL) {
					$group->remove();
				}

			}

		}

		$expected = $this->renderTaskDay(week: $week)[0];

		// La colonne vidée par le seul retrait des cartes gardées porte l'état vide du rechargement.
		foreach(array_keys($cards) as $columnKey) {

			$column = $expected->getElementById('planning-day-'.$columnKey);

			if($column !== NULL and $column->querySelector('.planning-task') === NULL) {
				$before->getElementById('planning-day-'.$columnKey)->replaceWith($before->importNode($column, TRUE));
			}

		}

		$this->assertTrue($this->getFingerprint($expected) === $this->getFingerprint($before), $case.' : empreinte après réponse ciblée et après rechargement'.$this->getGap($this->getFingerprint($expected), $this->getFingerprint($before)));

	}

	/**
	 * Les cartes que « Sans jour » garde, que l'écran réémet à chaque geste (`PlanningTaskDay.getBody()`),
	 * dans l'ordre de la colonne : l'intervention, et le temps qu'avait la personne avant la coche.
	 */
	private function getKept(\Dom\HTMLDocument $document): array {

		$tasks = [];

		foreach($document->querySelectorAll('#planning-day-'.TaskDayLib::NONE.' .planning-task[data-kept]') as $card) {
			$tasks[(int)$card->getAttribute('data-task')] = $card->getAttribute('data-kept');
		}

		return $tasks;

	}

	/**
	 * Les interventions des cartes d'une colonne du Jour, dans l'ordre de l'écran.
	 */
	private function getColumnTasks(\Dom\HTMLDocument $document, string $column): array {

		$tasks = [];

		foreach($document->querySelectorAll('#planning-day-'.$column.' .planning-task') as $card) {
			$tasks[] = (int)$card->getAttribute('data-task');
		}

		return $tasks;

	}

	/**
	 * Le pied et le groupe du haut d'avant la coche ne sont qu'une place : rien n'y réagit au clic ni au clavier
	 * (`inert`), et rien n'y est relevé, ni lien ni marque de recherche, qui doublerait ce que porte la carte visible.
	 */
	private function checkBefore(\Dom\Element $before, string $case): void {

		$this->assertTrue($before->hasAttribute('inert'), $case.' : la place d\'avant la coche n\'est pas inerte');
		$this->assertEquals(0, $before->querySelectorAll('a, button, input, [href], [tabindex], [id], [data-dropdown-id], [data-ajax], [data-planning-task-time-open]')->length, $case.' : la place d\'avant la coche porte un élément qui réagit ou s\'identifie');

		$attributes = [];

		foreach($before->querySelectorAll('*') as $node) {
			foreach($node->attributes as $attribute) {
				if(str_starts_with($attribute->name, 'data-search')) {
					$attributes[] = $attribute->name;
				}
			}
		}

		$this->assertEquals([], $attributes, $case.' : la place d\'avant la coche porte une marque de recherche, qui compterait la carte deux fois');

	}

	/**
	 * Une intervention sans série, sans espèce et sans note.
	 */
	private function createTaskBare(float $timeExpected): \series\Task {

		$eTask = new \series\Task([
			'structure' => $this->eStructure,
			'series' => new \series\Series(),
			'cultivation' => new \series\Cultivation(),
			'plant' => new \plant\Plant(),
			'action' => $this->getAction(ACTION_TRAITEMENT),
			'category' => $this->getCategoryCulture(),
			'status' => \series\Task::TODO,
			'plannedWeek' => \Clock::week(),
			'plannedDate' => NULL,
			'repeatMaster' => new \series\Repeat(),
			'timeExpected' => $timeExpected,
		]);

		// Sans `build()`, qui vérifierait l'action et la semaine saisies : le décor veut l'intervention que
		// le formulaire ne produit pas, sans rien pour la nommer, puisque c'est le cas éprouvé.
		\series\TaskLib::create($eTask);

		return $eTask;

	}

	/**
	 * La ligne entière d'une intervention et de ses fiches de temps, toutes colonnes : une écriture par le Lib
	 * y change `updatedAt`, une fiche recréée son identifiant. Une fiche réécrite à la même valeur ne s'y voit
	 * pas, `seriesTimesheet` n'ayant pas de date de mise à jour.
	 */
	private function getRows(\series\Task $eTask): string {

		$eTaskRow = \series\Task::model()
			->select(\series\Task::model()->getProperties())
			->whereId($eTask['id'])
			->get();

		$cTimesheet = \series\Timesheet::model()
			->select(\series\Timesheet::model()->getProperties())
			->whereTask($eTask)
			->sort(['id' => SORT_ASC])
			->getCollection();

		return json_encode([$eTaskRow->getArrayCopy(), $cTimesheet->getArrayCopy()]);

	}

	/**
	 * Un membre de la structure autre que celui qui coche, vide s'il est seul.
	 */
	private function getMemberOther(): \user\User {

		$cUser = \structure\MemberLib::getUsersByStructure($this->eStructure)->find(fn($eUser) => $eUser->is($this->getMember()) === FALSE);

		return $cUser->empty() ? new \user\User() : $cUser->first();

	}

	private function createTimesheetFor(\series\Task $eTask, \user\User $eUser, float $time, string $date): void {

		$eTimesheet = new \series\Timesheet([
			'structure' => $this->eStructure,
			'user' => $eUser,
			'series' => $eTask['series'],
			'cultivation' => $eTask['cultivation'],
			'plant' => $eTask['plant'],
			'task' => $eTask,
			'time' => $time,
			'date' => $date,
			'week' => toWeek($date),
		]);

		// Sans `build()`, qui vérifierait le temps et le jour saisis pour la personne connectée : le décor pose
		// aussi le temps d'un autre membre, qu'aucun formulaire ne lui fait saisir.
		\series\TimesheetLib::create($eTimesheet);

	}

	/**
	 * La fiche d'avant, les instructions appliquées, contre la fiche rechargée : égales, sauf la ligne
	 * gardée sous le masque, retirée avant de comparer.
	 */
	/**
	 * La fiche après un geste, comparée au rechargement. La ligne du geste (`$eTaskPlaced`) y garde la place
	 * que l'écran lui donnait (R-Place), lue sur l'écran avant que la réponse ne s'applique, comme le client
	 * la réémet. Une ligne que le geste crée n'a pas de place sur l'écran d'avant : `$dayWeekCreated` est
	 * celle que le geste lui demande, donnée par l'appelant et jamais relue dans la réponse, sans quoi
	 * une place fausse rendue par le serveur deviendrait l'attente. `$commented` compare à une variante
	 * donnée de la table : celle qu'un écran garde jusqu'au rechargement quand sa dernière ligne commentée
	 * le quitte.
	 */
	private function checkTime(\Dom\HTMLDocument $before, array $instructions, string $case, \series\Task $eTaskPlaced = new \series\Task(), array $input = [], ?bool $commented = NULL, ?string $dayWeekCreated = NULL): void {

		$this->assertTrue($instructions !== [], $case.' : le geste n\'a rendu aucune instruction');

		if($dayWeekCreated !== NULL) {
			$position = ['dayWeek' => $dayWeekCreated];
		} else {
			$position = $eTaskPlaced->notEmpty() ? $this->getTimePosition($before, $eTaskPlaced) : [];
		}

		$this->apply($before, $instructions);

		[$documentExpected, $dataExpected] = $this->renderTimeDay($input);

		if($position !== [] or $commented !== NULL) {

			if($position === []) {
				$timeDay = $dataExpected->timeDay;
			} else {
				$timeDay = TimeDayLib::get($dataExpected->eStructure, $dataExpected->week, $dataExpected->cTask, $dataExpected->date, $dataExpected->eUserTime, $dataExpected->hide, $dataExpected->mine, $eTaskPlaced, ($position['dayWeek'] === '') ? NULL : $position['dayWeek']);
			}

			if($commented !== NULL) {
				$timeDay['commented'] = $commented;
			}

			// La liste et non la table : la ligne gardée peut être la seule, que le rechargement remplace par l'état vide.
			$documentExpected->getElementById('planning-time-list')->replaceWith(...$this->parse($documentExpected, new TimeDayUi()->getList($dataExpected->eStructure, $dataExpected->week, $dataExpected->date, $dataExpected->eUserTime, $timeDay, $dataExpected->canTime, ($dataExpected->canCreate and $dataExpected->canTime))));

		}

		$expected = $this->getFingerprint($documentExpected);
		$actual = $this->getFingerprint($before);

		$this->assertTrue($expected === $actual, $case.' : empreinte après réponse ciblée et après rechargement'.$this->getGap($expected, $actual));

	}

	/**
	 * Une culture prévue cette semaine, récoltée lundi en deux unités : la ligne porte tous ses cas,
	 * dont le jour à deux quantités et la colonne Semaine.
	 */
	private function createCultivationHarvested(\series\Series $eSeries = new \series\Series(), \plant\Plant $ePlant = new \plant\Plant()): \series\Cultivation {

		$week = \Clock::week();

		$eCultivation = $this->createCultivation($eSeries, $ePlant);

		$eCultivation['harvestPeriodExpected'] = \series\Cultivation::WEEK;
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => [$week]]);
		\series\CultivationLib::update($eCultivation, ['harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected']);

		$eTask = $this->createTaskHarvest($eCultivation, $week, week_date_day($week, 1));
		$this->createHarvest($eTask, 2.0, week_date_day($week, 1));

		return $eCultivation;

	}

	/**
	 * Une culture prévue cette semaine, récoltée aujourd'hui en deux parts : 6 kg sans variété ni calibre,
	 * 4 kg de sa variété.
	 */
	private function createCultivationVentilated(): array {

		$eCultivation = $this->createCultivationHarvested();
		$eVariety = $this->createVariety($eCultivation['plant']);
		$this->createSlice($eCultivation, $eVariety);

		$eTask = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->createHarvest($eTask, 6.0, \Clock::date());

		$eTaskVentilated = $this->createTaskHarvest($eCultivation, \Clock::week(), \Clock::date());
		$this->ventilate($eTaskVentilated, ['variety' => $eVariety]);

		$this->createHarvest($eTaskVentilated, 4.0, \Clock::date());

		return [$eCultivation, $eVariety];

	}

	/**
	 * L'écran d'avant, les instructions appliquées, contre l'écran rendu après : égaux.
	 */
	private function check(\Dom\HTMLDocument $before, array $instructions, string $case, bool $quick = FALSE, ?string $open = NULL, ?string $week = NULL): void {

		$this->assertTrue($instructions !== [], $case.' : la saisie n\'a rendu aucune instruction');

		$this->apply($before, $instructions);

		$expected = $this->getFingerprint($this->render($quick, $week, $open)[0]);
		$actual = $this->getFingerprint($before);

		$this->assertTrue($expected === $actual, $case.' : empreinte après réponse ciblée et après rechargement'.$this->getGap($expected, $actual));

	}

	/**
	 * Applique les instructions comme `Ajax.Instruction.render()` (`framework/util/static/js/ajax.js`).
	 * Un sélecteur qui ne trouve rien ne fait rien. `focus` ne change pas le document, et le `js` qui
	 * ferme le panneau vit hors de l'écran ; une instruction inconnue fait échouer le cas.
	 */
	private function apply(\Dom\HTMLDocument $document, array $instructions): void {

		foreach($instructions as $instruction) {

			// Une instruction `js` n'a que deux membres, sans sélecteur.
			if($instruction[0] === 'js') {
				continue;
			}

			[$mode, $selector, $calls] = $instruction;

			$this->assertTrue(in_array($mode, ['qs', 'qsa'], TRUE), 'instruction '.$mode.' prise en charge');

			$nodes = ($mode === 'qs') ?
				array_filter([$document->querySelector($selector)]) :
				iterator_to_array($document->querySelectorAll($selector));

			foreach($nodes as $node) {

				foreach($calls as $call) {

					[$method, $arguments] = $call + [1 => []];

					// Un nœud remplacé ou retiré ne reçoit plus rien : le navigateur appliquerait la suite
					// au nœud détaché, sans effet sur l'écran.
					if($node->isConnected === FALSE) {
						break;
					}

					switch($method) {

						case 'outerHtml' :
							$node->replaceWith(...$this->parse($document, $arguments[0]));
							break;

						// Contrairement à `outerHtml`, le client écrit `innerHTML` tel quel (`renderInner()`) : un
						// fragment à plusieurs racines y est admis.
						case 'innerHtml' :
							$node->innerHTML = $arguments[0];
							break;

						case 'insertAdjacentHtml' :
							$this->insert($document, $node, $arguments[0], $arguments[1]);
							break;

						case 'remove' :
							$node->remove();
							break;

						case 'addClass' :
							$node->classList->add(...explode(' ', $arguments[0]));
							break;

						case 'removeClass' :
							$node->classList->remove(...explode(' ', $arguments[0]));
							break;

						case 'setAttribute' :
							$node->setAttribute($arguments[0], $arguments[1]);
							break;

						case 'removeAttribute' :
							$node->removeAttribute($arguments[0]);
							break;

						case 'focus' :
							break;

						default :
							$this->assertFail('instruction '.$method.' prise en charge');
							break;

					}

				}

			}

		}

	}

	private function parse(\Dom\HTMLDocument $document, string $html): array {

		$container = $document->createElement('div');
		$container->innerHTML = $html;

		$nodes = iterator_to_array($container->childNodes);

		// Le client refuse un fragment à plus d'une racine (`renderOuter()`, `renderAdjacentHTML()` dans
		// `lime.js`) : il lève et n'insère rien. L'applicateur échoue donc là où l'écran casserait.
		$this->assertTrue(count($nodes) <= 1, 'fragment à '.count($nodes).' racines, que le navigateur refuse : '.mb_substr($html, 0, 120));

		return $nodes;

	}

	private function insert(\Dom\HTMLDocument $document, \Dom\Element $node, string $position, string $html): void {

		$nodes = $this->parse($document, $html);

		match($position) {
			'beforebegin' => $node->before(...$nodes),
			'afterbegin' => $node->prepend(...$nodes),
			'beforeend' => $node->append(...$nodes),
			'afterend' => $node->after(...$nodes),
		};

	}

	private function getFingerprint(\Dom\HTMLDocument $document): string {

		$board = $document->getElementById('planning-board');

		$this->assertTrue($board !== NULL, 'l\'écran n\'est pas rendu');

		return $this->neutralize($document->saveHtml($board));

	}

	/** Les ids `field-<aléa>` que le cadriciel tire pour chaque champ, et celui du calendrier des semaines (`uniqid()`). */
	private function neutralize(string $html): string {

		return preg_replace(['/field-week-selector-[0-9a-f]{13}/', '/field-[a-z0-9]+/'], ['field-week-selector-X', 'field-X'], $html);

	}

	/**
	 * Où deux empreintes divergent, en quelques centaines de caractères de part et d'autre : l'écran
	 * entier ne se lit pas dans un message.
	 */
	private function getGap(string $expected, string $actual): string {

		if($expected === $actual) {
			return '';
		}

		$length = min(strlen($expected), strlen($actual));
		$position = 0;

		while($position < $length and $expected[$position] === $actual[$position]) {
			$position++;
		}

		$start = max(0, $position - 200);

		return ' ; écart à l\'octet '.$position.', attendu « '.substr($expected, $start, 400).' », obtenu « '.substr($actual, $start, 400).' »';

	}

	/**
	 * Les deux preuves sur l'Année (R-Annee) : un rendu comparé à lui-même est égal, un rendu changé par
	 * une intervention ajoutée dans une semaine chargée diverge.
	 */
	public function testYearProofs(): void {

		[$document] = $this->renderTaskYear();

		$this->assertEquals($this->getFingerprint($document), $this->getFingerprint($this->renderTaskYear()[0]), 'le même rendu de l\'Année, neutralisé, diffère de lui-même');

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, toWeek(\Clock::date().' + 7 DAYS'));

		$this->assertTrue($this->getFingerprint($document) !== $this->getFingerprint($this->renderTaskYear()[0]), 'une intervention ajoutée entre deux rendus de l\'Année ne change pas l\'empreinte');

	}

	/**
	 * Une semaine chargée seule (`weeks`) est la colonne que la page rend quand elle la charge elle-même.
	 */
	public function testYearWeekAlone(): void {

		$week = toWeek(\Clock::date().' + 7 DAYS');

		$this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		[$document] = $this->renderTaskYear();

		[, $instructions] = $this->callYear('weeks', ['weeks' => [$week]], 'GET');

		$column = $document->getElementById('planning-year-'.$week);

		if($this->assertTrue($column !== NULL and $column->hasAttribute('data-loaded'), 'la page ne charge pas la semaine qui suit') === FALSE) {
			return;
		}

		$expected = $this->neutralize($document->saveHtml($column));

		// Le jeton que `PlanningTaskYear.load()` pose au départ du chargement (`callYear()`).
		$column->setAttribute('data-load', '1');

		$this->apply($document, $instructions);

		$actual = $this->neutralize($document->saveHtml($document->getElementById('planning-year-'.$week)));

		$this->assertTrue($expected === $actual, 'la semaine chargée seule diffère de celle de la page'.$this->getGap($expected, $actual));

	}

	/**
	 * La coche d'une carte de la semaine qui suit, puis sa décoche : l'intervention garde sa semaine, donc la
	 * réponse ne porte que la carte et la tête (`TaskYearLib::CARD`), et, appliquée à l'écran d'avant, rend
	 * l'écran d'un rechargement, filtre des catégories compris.
	 */
	public function testYearDoneTodo(): void {

		$week = toWeek(\Clock::date().' + 7 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		[$document] = $this->renderTaskYear();

		[$data, $instructions] = $this->callYear('doDone', ['task' => $eTask['id'], 'week' => $week]);
		$this->checkYearCard($data, $instructions, 'coche');
		$this->checkYear($document, $instructions, 'coche');

		[$data, $instructions] = $this->callYear('doTodo', ['task' => $eTask['id'], 'week' => $week]);
		$this->checkYearCard($data, $instructions, 'décoche');
		$this->checkYear($document, $instructions, 'décoche');

	}

	/**
	 * La coche d'une intervention qui porte du temps une autre semaine, depuis sa semaine prévue : elle paraît
	 * dans les deux mêmes semaines avant et après, et la carte de chacune se remplace avec sa tête.
	 */
	public function testYearDoneWithTimeElsewhere(): void {

		$weekBefore = toWeek(\Clock::date().' - 7 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, \Clock::week());
		$this->createTimesheet($eTask, 1.0, week_date_day($weekBefore, 2));

		[$document] = $this->renderTaskYear();

		[$data, $instructions] = $this->callYear('doDone', ['task' => $eTask['id']]);

		$this->assertEquals([$weekBefore, \Clock::week()], $data->change, 'la coche ne redessine pas la semaine du temps et celle de la carte');
		$this->checkYearCard($data, $instructions, 'coche avec du temps une autre semaine');
		$this->checkYear($document, $instructions, 'coche avec du temps une autre semaine');

	}

	/**
	 * Une intervention prévue la semaine dernière qui porte du temps cette semaine, cochée depuis sa semaine
	 * prévue : la coche la date de la dernière semaine de son temps (`\series\TaskLib::updateDoneCollection()`),
	 * elle quitte sa semaine prévue. L'ensemble des semaines change, les deux colonnes se redessinent entières.
	 */
	public function testYearDoneChangesWeeks(): void {

		$weekBefore = toWeek(\Clock::date().' - 7 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekBefore);
		$this->createTimesheet($eTask, 1.0, \Clock::date());

		[$document] = $this->renderTaskYear();

		[$data, $instructions] = $this->callYear('doDone', ['task' => $eTask['id'], 'week' => $weekBefore]);

		$this->assertEquals([$weekBefore, \Clock::week()], $data->change, 'la coche ne redessine pas la semaine qu\'elle quitte et celle où elle reste');
		$this->assertEquals(\planning\TaskYearLib::COLUMN, $data->granularity, 'la coche qui fait quitter une semaine ne redessine pas les colonnes entières');
		$this->checkYear($document, $instructions, 'coche qui change les semaines');

	}

	/**
	 * Sous le masque, la carte cochée reste à l'écran jusqu'au rechargement : c'est le seul écart admis.
	 */
	public function testYearHidden(): void {

		$week = toWeek(\Clock::date().' + 7 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $week);

		$this->setHideDone(TRUE);

		[$document] = $this->renderTaskYear();

		[$data, $instructions] = $this->callYear('doDone', ['task' => $eTask['id'], 'week' => $week]);

		$this->checkYearCard($data, $instructions, 'coche sous le masque');
		$this->checkYear($document, $instructions, 'coche sous le masque', $eTask, $week);

	}

	/**
	 * « Nouvelle intervention » sur la semaine qui suit : elle ne redessine que cette semaine.
	 */
	public function testYearCreate(): void {

		$week = toWeek(\Clock::date().' + 7 DAYS');

		[$document] = $this->renderTaskYear();

		[$data, $instructions, $action] = $this->callYear('doCreate', [
			'week' => $week,
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $this->getCultivation()['id'],
			'day' => '',
		]);

		if($this->assertTrue($action instanceof \ViewAction, '« Nouvelle intervention » de l\'Année est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals([$week], $data->change, '« Nouvelle intervention » de l\'Année redessine d\'autres semaines que la sienne');
		$this->assertEquals(\planning\TaskYearLib::COLUMN, $data->granularity, '« Nouvelle intervention » ne redessine pas sa colonne entière');
		$this->checkYear($document, $instructions, 'nouvelle intervention');

	}

	/**
	 * « Nouvelle intervention » de l'Année sur deux séries : la semaine qui suit, seule, redessinée entière.
	 */
	public function testYearCreateSeveral(): void {

		$week = toWeek(\Clock::date().' + 7 DAYS');

		$cultivations = [$this->getCultivation()['id'], $this->createCultivation($this->createSeries('Seconde série du décor'))['id']];

		[$document] = $this->renderTaskYear();

		[$data, $instructions, $action] = $this->callYear('doCreate', [
			'week' => $week,
			'category' => $this->getCategoryCulture()['id'],
			'action' => $this->getAction(ACTION_PLANTATION)['id'],
			'cultivation' => $cultivations,
			'day' => '',
		]);

		if($this->assertTrue($action instanceof \ViewAction, '« Nouvelle intervention » de l\'Année sur deux séries est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$this->assertEquals(2, $data->cTaskCreated->count(), 'deux séries ne créent pas deux interventions sur l\'Année');
		$this->assertEquals([$week], $data->change, '« Nouvelle intervention » de l\'Année sur deux séries redessine d\'autres semaines que la sienne');
		$this->assertEquals(\planning\TaskYearLib::COLUMN, $data->granularity, '« Nouvelle intervention » sur deux séries ne redessine pas sa colonne entière');
		$this->checkYear($document, $instructions, 'nouvelle intervention sur deux séries');

	}

	/**
	 * La séquence de la contre-recette de V-Planning : une intervention prévue dans une semaine lointaine,
	 * qui porte du temps cette semaine. Le navigateur demande la semaine lointaine (jeton posé), le serveur
	 * la lit, puis la coche part et revient depuis la carte de cette semaine avant la réponse du chargement.
	 * Appliquée après la coche, cette réponse périmée ne doit rien poser : la semaine reste à charger, et son
	 * prochain chargement la rend à jour.
	 */
	public function testYearLoadAfterGesture(): void {

		$weekFar = toWeek(\Clock::date().' + 56 DAYS');

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekFar);
		$this->createTimesheet($eTask, 1.0, \Clock::date());

		[$document] = $this->renderTaskYear();

		$column = $document->getElementById('planning-year-'.$weekFar);

		if($this->assertTrue($column !== NULL and $column->hasAttribute('data-loaded') === FALSE, 'la semaine lointaine est déjà chargée par la page, le cas ne se monte pas') === FALSE) {
			return;
		}

		$column->setAttribute('data-load', '1');

		[, $instructionsLoad] = $this->callYear('weeks', ['weeks' => [$weekFar]], 'GET');
		[$data, $instructionsGesture] = $this->callYear('doDone', ['task' => $eTask['id']]);

		$this->assertTrue(in_array($weekFar, $data->change, TRUE), 'la coche ne change pas la semaine lointaine, le cas ne se monte pas');

		$this->apply($document, $instructionsGesture);
		$this->apply($document, $instructionsLoad);

		$this->assertFalse($document->getElementById('planning-year-'.$weekFar)->hasAttribute('data-loaded'), 'la réponse du chargement parti avant la coche pose la semaine lointaine dans son état d\'avant');

		$column = $document->getElementById('planning-year-'.$weekFar);
		$column->setAttribute('data-load', '2');

		[, $instructionsReload] = $this->callYear('weeks', ['weeks' => [$weekFar], 'load' => '2'], 'GET');
		$this->apply($document, $instructionsReload);

		$card = $document->getElementById(TaskCardUi::getId($eTask, $weekFar));

		$this->assertTrue($card !== NULL and $card->querySelector('[data-planning-task-todo="'.$eTask['id'].'"]') !== NULL, 'la semaine lointaine rechargée après la coche ne montre pas l\'intervention faite');

	}

	/**
	 * Sous le masque, une intervention prévue la semaine prochaine qui porte du temps huit semaines plus tôt,
	 * dans une semaine que la page ne charge pas. Cochée depuis la semaine prochaine, elle garde ses deux
	 * semaines (`TaskYearLib::CARD`). La semaine du temps se charge ensuite, l'intervention cochée réémise
	 * (`shown`) : la carte cochée y paraît, comme dans la colonne du geste. La décoche depuis la semaine
	 * prochaine la retrouve alors, et la semaine du temps redevient celle d'un chargement neuf parti du même
	 * écran.
	 */
	public function testYearLoadShownUnderMask(): void {

		$weekNext = toWeek(\Clock::date().' + 7 DAYS');
		$weekTime = toWeek(\Clock::date().' - 56 DAYS');

		if($this->assertTrue(week_year($weekTime) === week_year($weekNext), 'la semaine du temps n\'est pas de l\'année de la semaine prochaine, le cas ne se monte pas') === FALSE) {
			return;
		}

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, $weekNext);
		$this->createTimesheet($eTask, 1.0, week_date_day($weekTime, 2));

		$this->setHideDone(TRUE);

		[$document] = $this->renderTaskYear();

		$column = $document->getElementById('planning-year-'.$weekTime);

		if($this->assertTrue($column !== NULL and $column->hasAttribute('data-loaded') === FALSE, 'la semaine du temps est déjà chargée par la page, le cas ne se monte pas') === FALSE) {
			return;
		}

		[$data, $instructions] = $this->callYear('doDone', ['task' => $eTask['id'], 'week' => $weekNext]);

		$this->assertEquals([$weekTime, $weekNext], $data->change, 'la coche ne garde pas la semaine du temps et la semaine prévue, le cas ne se monte pas');
		$this->checkYearCard($data, $instructions, 'coche sous le masque, semaine du temps non chargée');
		$this->apply($document, $instructions);

		// `PlanningTaskYear.checked` après la coche, réémis par le chargement comme par les gestes.
		$shown = [$eTask['id']];

		$document->getElementById('planning-year-'.$weekTime)->setAttribute('data-load', '1');

		[, $instructions] = $this->callYear('weeks', ['weeks' => [$weekTime], 'shown' => $shown], 'GET');
		$this->apply($document, $instructions);

		$card = $document->getElementById(TaskCardUi::getId($eTask, $weekTime));

		$this->assertTrue($card !== NULL and $card->classList->contains('planning-task-done'), 'sous le masque, la semaine chargée après la coche perd la carte cochée');

		[$data, $instructions] = $this->callYear('doTodo', ['task' => $eTask['id'], 'week' => $weekNext, 'shown' => $shown]);

		$this->checkYearCard($data, $instructions, 'décoche sous le masque, semaine du temps chargée après la coche');
		$this->apply($document, $instructions);

		[$expected] = $this->renderTaskYear();
		$expected->getElementById('planning-year-'.$weekTime)->setAttribute('data-load', '1');

		// L'écran réémet toujours l'intervention : un chargement neuf la porte aussi, sinon la carte, qui a du
		// temps dans cette semaine, y serait masquée comme une intervention faite.
		[, $instructions] = $this->callYear('weeks', ['weeks' => [$weekTime], 'shown' => $shown], 'GET');
		$this->apply($expected, $instructions);

		$expectedColumn = $this->neutralize($expected->saveHtml($expected->getElementById('planning-year-'.$weekTime)));
		$actualColumn = $this->neutralize($document->saveHtml($document->getElementById('planning-year-'.$weekTime)));

		$this->assertTrue($expectedColumn === $actualColumn, 'après la décoche, la semaine du temps chargée après la coche diffère d\'un chargement neuf parti du même écran'.$this->getGap($expectedColumn, $actualColumn));

	}

	/**
	 * La décoche d'une intervention faite sans avoir été prévue la retire de sa semaine : elle n'a plus de
	 * colonne à l'Année, comme au rechargement.
	 */
	public function testYearUnplannedTodo(): void {

		$eTask = $this->createTask($this->getCultivation(), ACTION_PLANTATION, \series\Task::TODO, NULL);

		// Faite sans temps et sans semaine prévue : `TaskLib::create()` donne sa semaine faite comme semaine prévue à une
		// intervention créée faite, alors que la coche (`updateDoneCollection()`) ne touche pas à `plannedWeek`.
		\series\TaskLib::updateDoneCollection(new \Collection([$eTask]), \Clock::week());

		[$document] = $this->renderTaskYear();

		[, $instructions] = $this->callYear('doTodo', ['task' => $eTask['id']]);

		$this->checkYear($document, $instructions, 'décoche d\'une intervention faite sans avoir été prévue');

	}

	/**
	 * Un geste qui garde les semaines de l'intervention ne renvoie, par semaine, que la tête et la carte :
	 * aucune colonne entière.
	 */
	private function checkYearCard(\stdClass $data, array $instructions, string $case): void {

		$this->assertEquals(\planning\TaskYearLib::CARD, $data->granularity, $case.' : la réponse n\'est pas à la carte');

		// Les sélecteurs redessinés seuls : le retrait du jeton de chargement vise aussi la colonne entière.
		$selectors = array_column(array_filter($instructions, fn(array $instruction) => ($instruction[0] === 'qs' and in_array('outerHtml', array_column($instruction[2], 0), TRUE))), 1);
		$columns = array_filter($selectors, fn(string $selector) => preg_match('/^#planning-year-\d{4}-W\d{2}$/', $selector) === 1);

		$this->assertEquals([], array_values($columns), $case.' : la réponse redessine des colonnes entières');

		foreach($data->change as $week) {
			$this->assertTrue(in_array('#'.TaskCardUi::getId($data->eTask, $week), $selectors, TRUE), $case.' : la carte de la semaine '.$week.' n\'est pas renvoyée');
		}

	}

	/**
	 * L'écran de l'Année d'avant, les instructions appliquées, comparé au rechargement. Sous le masque, la
	 * carte cochée `$eTaskHidden` est l'écart admis : elle se retire avant la comparaison, avec son groupe
	 * d'action s'il se vide, et la colonne qu'elle laisse vide prend l'état vide du rechargement.
	 */
	private function checkYear(\Dom\HTMLDocument $before, array $instructions, string $case, \series\Task $eTaskHidden = new \series\Task(), ?string $week = NULL): void {

		$this->assertTrue($instructions !== [], $case.' : le geste n\'a rendu aucune instruction');

		$this->apply($before, $instructions);

		$expected = $this->renderTaskYear()[0];

		if($eTaskHidden->notEmpty()) {

			$card = $before->getElementById(TaskCardUi::getId($eTaskHidden, $week));

			if($this->assertTrue($card !== NULL, $case.' : la carte cochée n\'est plus à l\'écran') === FALSE) {
				return;
			}

			$this->assertTrue($card->classList->contains('planning-task-done'), $case.' : la carte gardée ne porte pas l\'état fait');

			$group = $card->closest('.planning-day-action');
			$card->remove();

			if($group !== NULL and $group->querySelector('.planning-task') === NULL) {
				$group->remove();
			}

			$column = $expected->getElementById('planning-year-'.$week);

			if($column !== NULL and $column->querySelector('.planning-task') === NULL) {
				$before->getElementById('planning-year-'.$week)->replaceWith($before->importNode($column, TRUE));
			}

		}

		$this->assertTrue($this->getFingerprint($expected) === $this->getFingerprint($before), $case.' : empreinte après réponse ciblée et après rechargement'.$this->getGap($this->getFingerprint($expected), $this->getFingerprint($before)));

	}

}
?>
