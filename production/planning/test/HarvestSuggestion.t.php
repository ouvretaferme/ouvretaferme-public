<?php
namespace planning;

/**
 * La liste que propose « Nouvelle récolte » avant toute frappe (R-Retours-0410, point 7) : les cultures de la
 * grille sous « En récolte cette semaine », puis les récoltes les plus proches sous « Récolte proche », et la
 * ligne qui invite à taper. Et la même liste à la frappe (R-Retours-0610, point 9) : les séries qui répondent à
 * ce qu'on tape, rangées de même, puis les « Autres séries », coupées à dix après le rangement.
 *
 * Le décor est posé sur la structure hôte, qui porte ses propres séries : les assertions ne lisent que les
 * cultures du décor, dans l'ordre où la liste les rend. Leur espèce est nommée pour passer en tête à écart
 * égal, sans quoi une série réelle au même écart pourrait les repousser au-delà de la coupe.
 */
class HarvestSuggestionTest extends HarvestFixtureTest {

	/**
	 * L'ordre : la semaine en tête, puis l'écart à la semaine la plus proche de la période, compté semaine par
	 * semaine. À écart égal, la récolte qui commence passe avant celle qui finit. Une période trouée dont le
	 * trou tombe sur la semaine affichée n'est pas en récolte, et une culture sans semaines prévues ne se
	 * propose pas. Une période finie la semaine d'avant reste dans la grille (règle R2, R-Retours-0710), donc en
	 * tête, et la récolte finie la plus proche hors de la grille l'est depuis deux semaines.
	 */
	public function testOrder(): void {

		$week = \Clock::week();
		$ePlant = $this->createPlantFirst();

		$eCultivationWeek = $this->createCultivationExpected($ePlant, 'S1', [$week]);
		$eCultivationAfterTwo = $this->createCultivationExpected($ePlant, 'S2', [$this->shiftWeek($week, 2), $this->shiftWeek($week, 3)]);
		$eCultivationBeforeTwo = $this->createCultivationExpected($ePlant, 'S3', [$this->shiftWeek($week, -3), $this->shiftWeek($week, -2)]);
		$eCultivationAfterOne = $this->createCultivationExpected($ePlant, 'S4', [$this->shiftWeek($week, 1)]);
		$eCultivationHole = $this->createCultivationExpected($ePlant, 'S5', [$this->shiftWeek($week, -3), $this->shiftWeek($week, 3)]);
		$eCultivationNone = $this->createCultivation($this->createSeries('S6'), $ePlant);

		$cCultivation = HarvestWeekLib::getSuggested($this->eStructure, $week);

		$this->assertEquals('week', $cCultivation[$eCultivationWeek['id']]['harvestSuggestion'] ?? NULL, 'une culture prévue cette semaine n\'est pas en tête');

		$decor = array_values(array_intersect($cCultivation->getIds(), [
			$eCultivationWeek['id'], $eCultivationAfterTwo['id'], $eCultivationBeforeTwo['id'], $eCultivationAfterOne['id'], $eCultivationHole['id'], $eCultivationNone['id'],
		]));

		$this->assertEquals([
			$eCultivationWeek['id'],
			$eCultivationAfterOne['id'],
			$eCultivationAfterTwo['id'],
			$eCultivationBeforeTwo['id'],
			$eCultivationHole['id'],
		], $decor, 'la liste ne range pas les cultures du décor par écart à la semaine, la semaine en tête');

		foreach([$eCultivationAfterTwo, $eCultivationBeforeTwo, $eCultivationAfterOne, $eCultivationHole] as $eCultivation) {
			$this->assertEquals('near', $cCultivation[$eCultivation['id']]['harvestSuggestion'], 'une récolte proche n\'est pas sous « Récolte proche »');
		}

		$this->assertTrue($cCultivation->find(fn($eCultivation) => $eCultivation['harvestSuggestion'] === 'near')->count() <= PlanningSetting::SUGGESTION_NEAR, 'les récoltes proches passent '.PlanningSetting::SUGGESTION_NEAR);

	}

	/**
	 * La semaine se montre en entière, quelle que soit sa taille : un plafond la couperait par l'alphabet et
	 * écarterait des cultures récoltées cette semaine (décision de V-Planning du 04/10). Seules les récoltes
	 * proches sont plafonnées, et la coupe garde les plus proches.
	 */
	public function testCap(): void {

		$week = \Clock::week();
		$ePlant = $this->createPlantFirst();

		$cultivationsWeek = [];

		for($position = 1; $position <= 31; $position++) {
			$cultivationsWeek[] = $this->createCultivationExpected($ePlant, 'S'.$position, [$week])['id'];
		}

		$cultivationsNear = [];

		for($position = 1; $position <= PlanningSetting::SUGGESTION_NEAR; $position++) {
			$cultivationsNear[] = $this->createCultivationExpected($ePlant, 'P'.$position, [$this->shiftWeek($week, 1)])['id'];
		}

		$eCultivationFar = $this->createCultivationExpected($ePlant, 'Q1', [$this->shiftWeek($week, 2)]);

		$cCultivation = HarvestWeekLib::getSuggested($this->eStructure, $week);

		$weekShown = $cCultivation->find(fn($eCultivation) => $eCultivation['harvestSuggestion'] === 'week')->getIds();
		$weekMissing = array_diff($cultivationsWeek, $weekShown);

		$this->assertEquals([], $weekMissing, 'des cultures de la semaine manquent à la liste : '.implode(', ', $weekMissing));

		$nearShown = $cCultivation->find(fn($eCultivation) => $eCultivation['harvestSuggestion'] === 'near')->getIds();

		$this->assertEquals(PlanningSetting::SUGGESTION_NEAR, count($nearShown), 'les récoltes proches ne sont pas coupées à '.PlanningSetting::SUGGESTION_NEAR);
		$this->assertEquals($cultivationsNear, array_values($nearShown), 'la coupe des récoltes proches ne garde pas les plus proches');
		$this->assertFalse($cCultivation->offsetExists($eCultivationFar['id']), 'une récolte plus lointaine passe la coupe');

	}

	/**
	 * Une période qui passe le 1er janvier se compte par les dates ISO : la dernière semaine de l'année est
	 * à une semaine de la première de l'année suivante.
	 */
	public function testNewYear(): void {

		$season = $this->getSeason();
		$weekLast = toWeek(($season).'-12-28');

		$this->setClock(week_date_day($weekLast, 4).' 10:00:00');

		$ePlant = $this->createPlantFirst();

		$eCultivationJanuary = $this->createCultivationExpected($ePlant, 'S1', [($season + 1).'-W01', ($season + 1).'-W02']);
		$eCultivationFar = $this->createCultivationExpected($ePlant, 'S2', [$this->shiftWeek($weekLast, -2)]);

		$cCultivation = HarvestWeekLib::getSuggested($this->eStructure, $weekLast);

		$decor = array_values(array_intersect($cCultivation->getIds(), [$eCultivationJanuary['id'], $eCultivationFar['id']]));

		$this->assertEquals([$eCultivationJanuary['id'], $eCultivationFar['id']], $decor, 'la semaine 1 de l\'année suivante ne compte pas à une semaine de la dernière');

	}

	/**
	 * La réponse : les deux intitulés et la ligne du pied ne se choisissent pas, les cultures se choisissent.
	 * Une frappe garde sa liste sans intitulés.
	 */
	public function testResponse(): void {

		$week = \Clock::week();
		$ePlant = $this->createPlantFirst();

		$this->createCultivationExpected($ePlant, 'S1', [$week]);
		$this->createCultivationExpected($ePlant, 'S2', [$this->shiftWeek($week, 1)]);

		[$data, $instructions, $action] = $this->call('query', ['query' => ''], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la liste par défaut est refusée : '.implode(', ', $this->getFails($action))) === FALSE) {
			return;
		}

		$results = HarvestEntryUi::getAutocompleteRanked($data->cCultivation, $data->suggested);

		$texts = array_map(fn(array $result) => ($result['type'] ?? 'value').' '.(($result['type'] ?? 'value') === 'value' ? '' : $result['itemText']), $results);
		$texts = array_values(array_filter($texts, fn(string $text) => $text !== 'value '));

		$this->assertEquals([
			'title En récolte cette semaine',
			'title Récolte proche',
			'disabled Tapez pour chercher parmi toutes les séries',
		], $texts, 'la liste par défaut ne porte pas ses intitulés et son pied');

		$this->assertEquals('title', $results[0]['type'] ?? NULL, 'la liste par défaut ne commence pas par son intitulé');
		$this->assertEquals('disabled', $results[count($results) - 1]['type'] ?? NULL, 'la liste par défaut ne finit pas par son pied');

		[$data, , $action] = $this->call('query', ['query' => '0 Décor'], 'POST');

		if($this->assertTrue($action instanceof \ViewAction, 'la frappe est refusée') === FALSE) {
			return;
		}

		$this->assertFalse($data->suggested, 'une frappe rend la liste par défaut');

	}

	/**
	 * « Récolte proche » s'arrête à trois mois de la semaine, à champ vide comme à la frappe (V-Planning, 06/10) :
	 * une série prévue cinq mois plus tard ne s'y propose plus, même quand il reste de la place sous le plafond.
	 */
	public function testNearWindow(): void {

		$week = \Clock::week();
		$ePlant = $this->createPlantFirst();

		$eCultivationNear = $this->createCultivationExpected($ePlant, 'S1', [$this->shiftWeek($week, 4)]);
		$eCultivationFar = $this->createCultivationExpected($ePlant, 'S2', [$this->shiftWeek($week, 22)]);

		$cCultivation = HarvestWeekLib::getSuggested($this->eStructure, $week);

		$this->assertEquals('near', $cCultivation[$eCultivationNear['id']]['harvestSuggestion'] ?? NULL, 'une récolte à un mois ne se propose pas sous « Récolte proche »');
		$this->assertFalse($cCultivation->offsetExists($eCultivationFar['id']), 'une récolte à cinq mois se propose sous « Récolte proche »');

	}

	/**
	 * La frappe range comme la liste à champ vide : la semaine, puis la récolte proche par écart à la semaine,
	 * puis les autres séries, la saison de la semaine d'abord, celles qui ont une récolte prévue avant celles
	 * qui n'en ont pas. Chaque groupe porte son nom, que l'interface met en intitulé.
	 */
	public function testQueryOrder(): void {

		$week = \Clock::week();
		$season = week_year($week);
		$word = 'frappe'.uniqid();
		$ePlant = $this->createPlantFirst();

		$eCultivationWeek = $this->createCultivationExpected($ePlant, $word.' E', [$week]);
		$eCultivationAfterOne = $this->createCultivationExpected($ePlant, $word.' D', [$this->shiftWeek($week, 1)]);
		$eCultivationBeforeTwo = $this->createCultivationExpected($ePlant, $word.' C', [$this->shiftWeek($week, -2)]);
		$eCultivationFar = $this->createCultivationExpected($ePlant, $word.' B', [$this->shiftWeek($week, 22)]);
		$eCultivationNone = $this->createCultivation($this->createSeries($word.' A'), $ePlant);
		$eCultivationPrevious = $this->createCultivation($this->createSeries($word.' 0', $season - 1), $ePlant);

		$cCultivation = HarvestWeekLib::getForQuery($this->eStructure, $week, $word);

		$this->assertEquals([
			$eCultivationWeek['id'],
			$eCultivationAfterOne['id'],
			$eCultivationBeforeTwo['id'],
			$eCultivationFar['id'],
			$eCultivationNone['id'],
			$eCultivationPrevious['id'],
		], $cCultivation->getIds(), 'la frappe ne range pas la semaine, puis la récolte proche, puis les autres séries');

		$this->assertEquals(
			['week', 'near', 'near', 'other', 'other', 'other'],
			array_values($cCultivation->getColumn('harvestSuggestion')),
			'la frappe ne nomme pas le groupe de chaque série'
		);

	}

	/**
	 * La coupe à dix vient après le rangement : une série de la semaine dont le nom passe après dix autres
	 * séries, et qui est la plus ancienne, reste dans la liste. Une coupe en SQL, par saison puis par
	 * identifiant, l'écartait.
	 */
	public function testQueryCut(): void {

		$week = \Clock::week();
		$word = 'coupe'.uniqid();
		$ePlant = $this->createPlantFirst();

		$eCultivationWeek = $this->createCultivationExpected($ePlant, $word.' Z', [$week]);

		for($position = 1; $position <= PlanningSetting::HARVEST_QUERY_SERIES + 1; $position++) {
			$this->createCultivationExpected($ePlant, $word.' A'.$position, [$this->shiftWeek($week, 1)]);
		}

		$cCultivation = HarvestWeekLib::getForQuery($this->eStructure, $week, $word);

		$this->assertEquals(PlanningSetting::HARVEST_QUERY_SERIES, $cCultivation->count(), 'la frappe n\'est pas coupée à '.PlanningSetting::HARVEST_QUERY_SERIES);
		$this->assertEquals($eCultivationWeek['id'], $cCultivation->first()['id'] ?? NULL, 'la coupe écarte la série de la semaine, ou ne la met pas en tête');

	}

	/**
	 * Une série récoltée cette semaine hors de sa période est de la semaine, comme dans la grille : la frappe le
	 * lit dans les récoltes de la semaine, et non dans `harvestWeeks`, qui suit la semaine où l'intervention est
	 * faite. Le décor les sépare : une intervention faite la semaine dernière, récoltée cette semaine.
	 */
	public function testQueryWeekHarvested(): void {

		$week = \Clock::week();
		$weekBefore = $this->shiftWeek($week, -1);
		$word = 'recolte'.uniqid();
		$ePlant = $this->createPlantFirst();

		$eCultivation = $this->createCultivationExpected($ePlant, $word.' S1', [$this->shiftWeek($week, 22)]);

		$eTask = $this->createTaskHarvest($eCultivation, $weekBefore, week_date_starts($weekBefore));
		$this->createHarvest($eTask, 5.0, \Clock::date());

		$harvestWeeks = \series\CultivationLib::getById($eCultivation['id'])['harvestWeeks'] ?? [];

		if($this->assertDecorPresent(in_array($week, $harvestWeeks, TRUE) === FALSE, 'une culture récoltée cette semaine sans que harvestWeeks contienne la semaine') === FALSE) {
			return;
		}

		$cCultivation = HarvestWeekLib::getForQuery($this->eStructure, $week, $word);

		$this->assertEquals('week', $cCultivation[$eCultivation['id']]['harvestSuggestion'] ?? NULL, 'une série récoltée cette semaine hors période n\'est pas sous « En récolte cette semaine » à la frappe');

	}

	/**
	 * Une espèce dont le nom passe avant les espèces réelles de la structure hôte, en ordre naturel.
	 */
	private function createPlantFirst(): \plant\Plant {

		$ePlant = $this->createPlant();

		// Sans build(), qui vérifierait un nom saisi : le décor renomme son espèce pour la ranger en tête.
		$ePlant['name'] = '0 Décor '.uniqid();
		\plant\PlantLib::update($ePlant, ['name']);

		return $ePlant;

	}

	private function createCultivationExpected(\plant\Plant $ePlant, string $series, array $weeks): \series\Cultivation {

		$eCultivation = $this->createCultivation($this->createSeries($series), $ePlant);

		$eCultivation['harvestPeriodExpected'] = \series\Cultivation::WEEK;
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => $weeks]);

		\series\CultivationLib::update($eCultivation, ['harvestPeriodExpected', 'harvestMonthsExpected', 'harvestWeeksExpected']);

		return $eCultivation;

	}

	private function shiftWeek(string $week, int $weeks): string {

		return toWeek(week_date_starts($week).' '.($weeks >= 0 ? '+' : '-').' '.abs($weeks).' weeks');

	}

}
?>
