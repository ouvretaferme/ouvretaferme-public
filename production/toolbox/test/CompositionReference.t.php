<?php
namespace toolbox;

/**
 * Le plan de culture et la conservation des espèces (lot r9, planche r9 validée le 27/09,
 * repris au lot r27) :
 *
 * - la durée de conservation d'une espèce pour une structure, celle de sa copie, sinon celle du
 *   référentiel partagé (`CompositionLib::getStorage()`) ;
 * - les semaines que le plan de culture ouvre à une espèce, chaque semaine de récolte prolongée de
 *   la conservation (`getReferenceWindows()`) ;
 * - « Appliquer le plan de culture » (`CompositionPlanLib::synchronize()`, r51b) : les espèces
 *   cochées prennent la saison de leurs semaines de récolte et de conservation, une espèce listée
 *   et décochée garde ses mois, qui deviennent choisis à la main, et les propositions se refont ;
 *   le deck « Plan de culture » de l'onglet « Espèces » montre ce que l'envoi fera, espèce par
 *   espèce, sur les mois dessinés ;
 * - le plan synchronisé borne les quantités au stock prévu.
 *
 * Isolation : une transaction par méthode, annulée en tearDown(), comme `CompositionPanelTest`.
 *
 * Lancement : dev/test package=toolbox file=CompositionReference
 */
class CompositionReferenceTest extends \Test {

	const SEASON = 2026;

	private \user\User $eUserInitial;

	public function setUp(): void {

		CompositionPlan::model()->beginTransaction();

		$this->eUserInitial = \user\ConnectionLib::getOnline();

	}

	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);

		CompositionPlan::model()->rollBack();

	}

	public function testRelativeWeek(): void {

		$this->assertEquals(14, CompositionLib::getRelativeWeek('2026-W14', 2026), 'une semaine de la saison');
		$this->assertEquals(-2, CompositionLib::getRelativeWeek('2025-W50', 2026), 'l\'année d\'avant, sous 1');
		$this->assertEquals(0, CompositionLib::getRelativeWeek('2025-W52', 2026), 'la dernière de l\'année d\'avant');
		$this->assertEquals(52, CompositionLib::getRelativeWeek('2026-W53', 2026), 'une semaine 53 sur la 52');
		$this->assertEquals(53, CompositionLib::getRelativeWeek('2027-W01', 2026), 'l\'année d\'après, au-delà de 52');

	}

	/**
	 * La copie d'une structure suit la durée du référentiel tant qu'elle porte NULL, et la remplace
	 * dès qu'elle en porte une, 0 compris. Une structure sans copie suit le référentiel, et rien
	 * n'ouvre sa fiche.
	 */
	public function testStorage(): void {

		$ePlantCopy = \plant\Plant::model()
			->select(['id', 'structure', 'reference' => ['structure']])
			->where('reference', '!=', NULL)
			->where('structure', '!=', NULL)
			->sort(['id' => SORT_ASC])
			->get();

		$plantId = $ePlantCopy['reference']['id'];

		// Décor posé par le Lib sans build() : build() aurait borné `storage` entre 0 et 52 semaines
		// (`plant.yml`) et ne l'aurait accepté que dans les propriétés que `getPropertiesUpdate()` ouvre
		// à la structure. Le test ne valide aucune saisie, il pose sur le référentiel une durée que
		// seul l'administrateur du référentiel écrit, pour lire comment la copie la suit.
		$ePlantCopy['reference']['storage'] = 6;
		\plant\PlantLib::update($ePlantCopy['reference'], ['storage']);

		// Même décor sans build() : NULL, que le schéma accepte (`null: TRUE`), est justement la valeur
		// que le test éprouve, « la copie suit le référentiel ».
		$ePlantCopy['storage'] = NULL;
		\plant\PlantLib::update($ePlantCopy, ['storage']);

		$storage = CompositionLib::getStorage($ePlantCopy['structure'], [$plantId]);

		$this->assertEquals([$plantId], array_keys($storage), 'une entrée par espèce demandée');
		$this->assertEquals([6, FALSE, $ePlantCopy['id']], [$storage[$plantId]['weeks'], $storage[$plantId]['typed'], $storage[$plantId]['plant']['id']], 'NULL suit le référentiel, et la copie s\'ouvre');

		// Même décor sans build() : 0 est la borne basse de `storage` (`plant.yml`), que build() aurait
		// admise ; le test l'écrit directement pour éprouver « 0 remplace le référentiel », pas la saisie.
		$ePlantCopy['storage'] = 0;
		\plant\PlantLib::update($ePlantCopy, ['storage']);

		$storage = CompositionLib::getStorage($ePlantCopy['structure'], [$plantId]);

		$this->assertEquals([0, TRUE], [$storage[$plantId]['weeks'], $storage[$plantId]['typed']], '0 posé par la structure remplace le référentiel');

		$eStructureWithout = new \structure\Structure([
			'id' => \structure\Structure::model()->select(['id' => new \Sql('MAX(id)', 'int')])->get()['id'] + 1000,
		]);

		$storage = CompositionLib::getStorage($eStructureWithout, [$plantId]);

		$this->assertEquals([6, FALSE, TRUE], [$storage[$plantId]['weeks'], $storage[$plantId]['typed'], $storage[$plantId]['plant']->empty()], 'sans copie, le référentiel, et aucune fiche');

		$this->assertEquals([], CompositionLib::getStorage($ePlantCopy['structure'], []), 'aucune espèce demandée');

	}

	/**
	 * Les semaines de récolte du plan de culture, face à une lecture de la base : une espèce propre se
	 * range sous son espèce du référentiel, les saisons S-1 et S comptent, rien au-delà de la 52.
	 */
	public function testHarvestWeeks(): void {

		$eStructure = $this->getStructureCultivated();

		$harvestWeeks = CompositionLib::getHarvestWeeks($eStructure, self::SEASON);

		$cCultivation = \series\Cultivation::model()
			->select(['harvestWeeksExpected', 'plant' => ['reference']])
			->whereStructure($eStructure)
			->whereSeason('IN', [self::SEASON - 1, self::SEASON])
			->getCollection();

		$expected = [];

		foreach($cCultivation as $eCultivation) {

			$plantId = $eCultivation['plant']['reference']->notEmpty() ? $eCultivation['plant']['reference']['id'] : $eCultivation['plant']['id'];

			foreach($eCultivation['harvestWeeksExpected'] ?? [] as $weekIso) {

				// Une culture de l'année d'avant peut dater une récolte deux ans plus tôt : elle se rend,
				// et n'ouvre rien (`testReferenceWindows()`).
				[$year, $week] = array_map('intval', explode('-W', $weekIso));
				$relative = ($year - self::SEASON) * 52 + min($week, 52);

				if($relative <= 52) {
					$expected[$plantId][$relative] = $relative;
				}

			}

		}

		$actual = array_map(function(array $weeks) {
			sort($weeks);
			return $weeks;
		}, $harvestWeeks);

		$expected = array_map(function(array $weeks) {
			sort($weeks);
			return array_values($weeks);
		}, $expected);

		ksort($actual);
		ksort($expected);

		$this->assertTrue(count($expected) >= CompositionLib::LINES_MIN, 'une structure qui date au moins quatre espèces, '.count($expected));
		$this->assertEquals($expected, $actual, 'les semaines de chaque espèce');

	}

	/**
	 * Une récolte de la semaine w ouvre les semaines w à w + conservation, bornées à la saison ; la
	 * semaine de récolte est notée récoltée, les autres non. Une récolte de l'année d'avant n'ouvre
	 * que ce que sa conservation porte dans la saison. Seules les espèces du plan comptent.
	 */
	public function testReferenceWindows(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure);

		$plants = CompositionSpeciesLib::getPlants(CompositionSpeciesLib::getByPlan($eCompositionPlan))['plants'];

		$harvestWeeks = CompositionLib::getHarvestWeeks($eStructure, self::SEASON);

		$storageNone = array_fill_keys(array_keys($plants), ['weeks' => 0]);

		$windows = CompositionLib::getReferenceWindows($harvestWeeks, $plants, $storageNone);

		$expected = [];

		foreach(array_intersect($plants, array_keys($harvestWeeks)) as $speciesId => $plantId) {

			$weeks = array_filter($harvestWeeks[$plantId], fn(int $week) => $week >= 1);

			if($weeks !== []) {
				sort($weeks);
				$expected[$speciesId] = array_fill_keys($weeks, TRUE);
			}

		}

		ksort($windows);
		ksort($expected);

		$this->assertTrue(count($expected) >= CompositionLib::LINES_MIN, 'des espèces du plan datées');
		$this->assertEquals($expected, $windows, 'sans conservation, les seules semaines de récolte de la saison');

		// Une conservation de deux semaines sur une espèce datée : chaque récolte ouvre les deux
		// suivantes, non récoltées.
		$speciesId = array_key_first($expected);
		$storageTwo = $storageNone;
		$storageTwo[$speciesId] = ['weeks' => 2];

		$window = CompositionLib::getReferenceWindows($harvestWeeks, $plants, $storageTwo)[$speciesId];

		$expectedWindow = [];

		foreach($harvestWeeks[$plants[$speciesId]] as $week) {

			foreach(range($week, $week + 2) as $weekOpen) {

				if($weekOpen >= 1 and $weekOpen <= 52) {
					$expectedWindow[$weekOpen] ??= FALSE;
				}

			}

		}

		foreach($harvestWeeks[$plants[$speciesId]] as $week) {

			if($week >= 1) {
				$expectedWindow[$week] = TRUE;
			}

		}

		ksort($expectedWindow);

		$this->assertEquals($expectedWindow, $window, 'la récolte, puis la conservation');
		$this->assertTrue(in_array(FALSE, $window, TRUE), 'des semaines ouvertes par la seule conservation');

		// Une récolte de l'année d'avant, sur la seule espèce qui en porte une : sa dernière semaine
		// de l'année d'avant, conservée jusqu'à la semaine 0, n'ouvre rien de la saison ; une semaine
		// de plus ouvre la semaine 1, non récoltée.
		$speciesIdPrevious = NULL;

		foreach($expected as $speciesIdExpected => $window) {

			if(array_filter($harvestWeeks[$plants[$speciesIdExpected]], fn(int $week) => $week < 1) !== []) {
				$speciesIdPrevious = $speciesIdExpected;
				break;
			}

		}

		$this->assertTrue($speciesIdPrevious !== NULL, 'une espèce datée qui récolte aussi l\'année d\'avant');

		$plantsPrevious = [$speciesIdPrevious => $plants[$speciesIdPrevious]];

		$last = max(array_filter($harvestWeeks[$plants[$speciesIdPrevious]], fn(int $week) => $week < 1));
		$firstInSeason = min(array_filter($harvestWeeks[$plants[$speciesIdPrevious]], fn(int $week) => $week >= 1));

		$this->assertTrue($firstInSeason > 1, 'et pas en semaine 1, que sa récolte de la saison ouvrirait');

		$windowPrevious = CompositionLib::getReferenceWindows($harvestWeeks, $plantsPrevious, [$speciesIdPrevious => ['weeks' => -$last]]);
		$this->assertFalse(isset($windowPrevious[$speciesIdPrevious][1]), 'conservée jusqu\'à la semaine 0, rien de la saison');

		$windowPrevious = CompositionLib::getReferenceWindows($harvestWeeks, $plantsPrevious, [$speciesIdPrevious => ['weeks' => 1 - $last]]);
		$this->assertEquals(FALSE, $windowPrevious[$speciesIdPrevious][1] ?? NULL, 'une semaine de plus ouvre la semaine 1, non récoltée');

	}

	/**
	 * La source d'un plan : la disponibilité est celle de la communauté, la saison écrite sur chaque
	 * espèce étant la porte de ses candidates (lot r27), avec une conservation par espèce au panier.
	 */
	public function testSourceByPlan(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$sourceShared = CompositionLib::getSource();

		$this->assertEquals($sourceShared['availability'], $source['availability'], 'la disponibilité de la communauté');

		// Chaque espèce du plan que la communauté compte a sa conservation, rangée par l'espèce du plan.
		$speciesIdsUnits = array_keys(CompositionLib::projectByPlants($sourceShared['units'], $source['plants']));
		$speciesIdsStorage = array_keys($source['storage']);
		sort($speciesIdsUnits);
		sort($speciesIdsStorage);

		$this->assertEquals($speciesIdsUnits, $speciesIdsStorage, 'une conservation par espèce au panier');

	}

	/**
	 * La saison que le départ écrit sur chaque espèce (lot r27, `CompositionPlanLib::create()`,
	 * `CompositionSpeciesLib::getSeasonsDefault()`) :
	 *
	 * - sur un plan qui suit la communauté, une espèce cochée prend la saison de la communauté, trous
	 *   d'une semaine bouchés, sous `NATURAL`, et une espèce décochée n'en a aucune ;
	 * - le menu de la synchronisation annonce les espèces datées, puis plus aucune une fois
	 *   synchronisé, et une seconde synchronisation, qui ne changerait rien, est refusée ;
	 * - un plan synchronisé que l'on recommence le reste (`restart()`), et ses espèces datées par le
	 *   plan de culture prennent sa saison, sous `CULTIVATION`, les autres celle de la communauté.
	 */
	public function testCreateSeasons(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan, []);
		$natural = CompositionLib::getNaturalWindows(CompositionLib::getSeasonWeeks(), self::SEASON, $source);

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);

		$wrong = [];
		$counts = [CompositionSpecies::NATURAL => 0, CompositionSpecies::EXCLUDED => 0];

		foreach($cCompositionSpecies as $eCompositionSpecies) {

			$speciesId = $eCompositionSpecies['id'];

			$expected = match($eCompositionSpecies['status']) {
				CompositionSpecies::NATURAL => CompositionLib::getWindowWeeksFilled($natural[$speciesId]),
				CompositionSpecies::EXCLUDED => NULL,
			};

			if($eCompositionSpecies['windowWeeks'] !== $expected) {
				$wrong[] = $speciesId;
			}

			$counts[$eCompositionSpecies['status']]++;

		}

		$this->assertEquals([], $wrong, 'chaque espèce porte la saison de la communauté, une espèce retirée aucune : '.implode(', ', $wrong));
		$this->assertTrue($counts[CompositionSpecies::NATURAL] > 0 and $counts[CompositionSpecies::EXCLUDED] > 0, 'des espèces cochées et décochées : '.json_encode($counts));

		$plants = $this->getSynchronization($eCompositionPlan)['plants'];
		$this->assertTrue($plants > 0, 'le deck annonce les espèces datées : '.$plants);

		$this->synchronize($eCompositionPlan);

		$this->assertEquals(0, $this->getSynchronization($eCompositionPlan)['plants'], 'synchronisé, plus aucune espèce ne change');

		$fw = new \FailWatch();

		CompositionPlanLib::synchronize($eCompositionPlan, $this->getSpeciesIdsChecked($eCompositionPlan));

		$this->assertEquals(['CompositionPlan::synchronizeEmpty'], array_column($fw->get(), 1), 'une seconde application n\'a plus d\'espèce à cocher et est refusée');

		$eCompositionPlanRestart = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlanRestart['weeks'] = $eCompositionPlanRestart['seasonWeeks'];

		CompositionPlanLib::restart($eCompositionPlanRestart);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		$this->assertEquals(CompositionPlan::CULTIVATION, $eCompositionPlanWritten['reference'], 'recommencé, le plan reste synchronisé');

		// Recommencer recopie les espèces sous de nouveaux identifiants : la saison de la communauté et la
		// conservation se relisent sur le plan écrit.
		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlanWritten);

		$sourceWritten = CompositionLib::getSourceByPlan($eCompositionPlanWritten, []);
		$naturalWritten = CompositionLib::getNaturalWindows(CompositionLib::getSeasonWeeks(), self::SEASON, $sourceWritten);

		$reference = CompositionLib::getReferenceWindows(
			CompositionLib::getHarvestWeeks($eStructure, self::SEASON),
			CompositionSpeciesLib::getPlants($cCompositionSpecies)['plants'],
			$sourceWritten['storage']
		);

		$wrong = [];
		$counts = [CompositionSpecies::CULTIVATION => 0, CompositionSpecies::NATURAL => 0, CompositionSpecies::EXCLUDED => 0];

		foreach($cCompositionSpecies as $eCompositionSpecies) {

			$speciesId = $eCompositionSpecies['id'];

			$expected = match($eCompositionSpecies['status']) {
				CompositionSpecies::CULTIVATION => array_key_exists($speciesId, $reference) ? CompositionLib::getWindowWeeksFilled(array_keys($reference[$speciesId])) : 'non datée',
				CompositionSpecies::NATURAL => array_key_exists($speciesId, $reference) ? 'datée' : CompositionLib::getWindowWeeksFilled($naturalWritten[$speciesId]),
				CompositionSpecies::EXCLUDED => NULL,
			};

			if($eCompositionSpecies['windowWeeks'] !== $expected) {
				$wrong[] = $speciesId;
			}

			$counts[$eCompositionSpecies['status']]++;

		}

		$this->assertEquals([], $wrong, 'une espèce datée prend la saison du plan de culture, une autre celle de la communauté : '.implode(', ', $wrong));
		$this->assertTrue($counts[CompositionSpecies::CULTIVATION] > 0, 'des espèces datées : '.json_encode($counts));

	}

	/**
	 * « Recommencer les paniers » garde la saison de vente de chaque espèce que le départ coche
	 * (`CompositionPlanLib::restart()`, arbitrage r27 du 28/09) :
	 *
	 * - sur la même saison, une saison réglée à la main reste `CUSTOM` et une saison prise du plan de
	 *   culture reste `CULTIVATION`, semaine pour semaine, même quand le plan de culture en donnerait
	 *   une autre aujourd'hui ;
	 * - sur une autre saison, la saison réglée à la main passe encore, et celle du plan de culture,
	 *   qui suivait l'autre plan de culture, repart sur la communauté, le plan avec elle.
	 *
	 * La saison `CULTIVATION` est réécrite avant le départ sur des semaines que le plan de culture ne
	 * donne pas : reprise, elle se distingue d'une saison que `create()` recalculerait.
	 */
	public function testRestartSeasons(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure);

		$this->synchronize($eCompositionPlan);

		$plantsNext = CompositionPlanLib::getForStart($eStructure, self::SEASON + 1)['cCompositionSpecies']->getKeys();

		$cCompositionSpeciesCultivation = CompositionSpeciesLib::getByPlan($eCompositionPlan)->find(fn(CompositionSpecies $e) => (
			$e['status'] === CompositionSpecies::CULTIVATION and
			in_array($e['plant']['id'], $plantsNext)
		));

		$this->assertTrue($cCompositionSpeciesCultivation->count() >= 2, 'deux espèces synchronisées, proposables sur les deux saisons : '.$cCompositionSpeciesCultivation->count());

		$eCompositionSpeciesCustom = $cCompositionSpeciesCultivation->first();
		$eCompositionSpeciesCustom['status'] = CompositionSpecies::CUSTOM;
		$eCompositionSpeciesCustom['windowWeeks'] = [[20, 25]];

		$eCompositionSpeciesCultivation = $cCompositionSpeciesCultivation->last();
		$eCompositionSpeciesCultivation['windowWeeks'] = [[30, 33]];

		CompositionSpeciesLib::update($eCompositionSpeciesCustom, ['status', 'windowWeeks']);
		CompositionSpeciesLib::update($eCompositionSpeciesCultivation, ['windowWeeks']);

		$plantCustom = $eCompositionSpeciesCustom['plant']['id'];
		$plantCultivation = $eCompositionSpeciesCultivation['plant']['id'];

		// Le départ range ses espèces par fiche, et le plan recommencé recopie les siennes sous de
		// nouveaux identifiants : l'espèce se suit par sa fiche, servie et lue sous le même id.
		$reference = CompositionLib::getReferenceWindows(
			CompositionLib::getHarvestWeeks($eStructure, self::SEASON),
			[$plantCultivation => $plantCultivation],
			CompositionLib::getStorage($eStructure, [$plantCultivation])
		);

		$this->assertTrue([[30, 33]] !== CompositionLib::getWindowWeeksFilled(array_keys($reference[$plantCultivation])), 'le plan de culture donne une autre saison que celle écrite');

		$restart = function(int $season) use($eStructure, $plantCustom, $plantCultivation): array {

			$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure, $season);
			$eCompositionPlanStart['weeks'] = $eCompositionPlanStart['seasonWeeks'];

			// Le départ coche les deux espèces, comme l'utilisateur qui les garde.
			$eCompositionPlanStart['cCompositionSpecies'][$plantCustom]['status'] = CompositionSpecies::NATURAL;
			$eCompositionPlanStart['cCompositionSpecies'][$plantCultivation]['status'] = CompositionSpecies::NATURAL;

			CompositionPlanLib::restart($eCompositionPlanStart);

			$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
			$eCompositionPlanWritten['structure'] = $eStructure;

			$seasons = [];

			foreach(CompositionSpeciesLib::getByPlan($eCompositionPlanWritten) as $eCompositionSpecies) {
				$seasons[$eCompositionSpecies['plant']['id']] = $eCompositionSpecies->extracts(['status', 'windowWeeks']);
			}

			return [
				$eCompositionPlanWritten['reference'],
				$seasons[$plantCustom],
				$seasons[$plantCultivation],
			];

		};

		[$planReference, $custom, $cultivation] = $restart(self::SEASON);

		$this->assertEquals(CompositionPlan::CULTIVATION, $planReference, 'même saison, le plan reste synchronisé');
		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[20, 25]]], $custom, 'même saison, la saison réglée à la main passe');
		$this->assertEquals(['status' => CompositionSpecies::CULTIVATION, 'windowWeeks' => [[30, 33]]], $cultivation, 'même saison, la saison du plan de culture passe telle qu\'elle était écrite');

		[$planReference, $custom, $cultivation] = $restart(self::SEASON + 1);

		// Une autre saison se choisit au menu des plans de culture (r48b) : le plan suit ce plan de
		// culture, et l'espèce prend la saison qu'il lui donne, ou celle de la communauté s'il ne la
		// date pas. Les semaines écrites sous l'autre saison ne passent dans aucun des deux cas.
		$referenceNext = CompositionLib::getReferenceWindows(
			CompositionLib::getHarvestWeeks($eStructure, self::SEASON + 1),
			[$plantCultivation => $plantCultivation],
			CompositionLib::getStorage($eStructure, [$plantCultivation])
		);

		$this->assertEquals(CompositionPlan::CULTIVATION, $planReference, 'autre saison, le plan suit le plan de culture de cette saison');
		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[20, 25]]], $custom, 'autre saison, la saison réglée à la main passe encore');
		$this->assertEquals(array_key_exists($plantCultivation, $referenceNext) ? CompositionSpecies::CULTIVATION : CompositionSpecies::NATURAL, $cultivation['status'], 'autre saison, la saison vient du plan de culture de cette saison, ou de la communauté');
		$this->assertTrue($cultivation['windowWeeks'] !== [[30, 33]], 'autre saison, les semaines du plan de culture ne passent pas');

	}

	/**
	 * « Synchroniser sur le plan de culture » : chaque espèce que le plan de culture date prend la
	 * saison de ses semaines de récolte et de conservation, trous courts bouchés, sous
	 * `CULTIVATION` ; une espèce réglée à la main la garde, sauf quand sa case du bloc « Plan de
	 * culture » est cochée (r29, #80) ;
	 * une espèce qu'il ne date pas garde la sienne. Le plan passe à `CULTIVATION`, et les
	 * propositions se refont : aucune espèce n'est plus servie hors de sa saison.
	 */
	public function testSynchronize(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure);

		$linesBefore = $this->getLines($eCompositionPlan);

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$harvestWeeks = CompositionLib::getHarvestWeeks($eStructure, self::SEASON);

		// Le départ ne coche que les espèces cultivées, que le plan de culture date toutes : une espèce
		// que la communauté vend et que la structure ne cultive pas se recoche, sur sa saison.
		$natural = CompositionLib::getNaturalWindows(CompositionLib::getSeasonWeeks(), self::SEASON, $source);

		$eCompositionSpeciesUndated = CompositionSpeciesLib::getByPlan($eCompositionPlan)->find(fn(CompositionSpecies $e) => (
			$e['status'] === CompositionSpecies::EXCLUDED and
			array_key_exists($e['id'], $natural) and
			array_key_exists($e['plant']['id'], $harvestWeeks) === FALSE
		))->first();

		$eCompositionSpeciesUndated['status'] = CompositionSpecies::NATURAL;
		$eCompositionSpeciesUndated['windowWeeks'] = CompositionLib::getWindowWeeksFilled($natural[$eCompositionSpeciesUndated['id']]);
		CompositionSpecies::model()->select(['status', 'windowWeeks'])->update($eCompositionSpeciesUndated);

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
		$reference = CompositionLib::getReferenceWindows($harvestWeeks, CompositionSpeciesLib::getPlants($cCompositionSpecies)['plants'], $source['storage']);

		$cCompositionSpeciesDated = $cCompositionSpecies->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::NATURAL and array_key_exists($e['id'], $reference));
		$cCompositionSpeciesUndated = $cCompositionSpecies->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::NATURAL and array_key_exists($e['id'], $reference) === FALSE);

		$this->assertTrue($cCompositionSpeciesDated->count() >= 4, 'des espèces datées : '.$cCompositionSpeciesDated->count());
		$this->assertTrue($cCompositionSpeciesUndated->notEmpty(), 'une espèce que le plan de culture ne date pas');

		// Une espèce datée réglée à la main, sur une saison qu'aucune synchronisation n'écrirait.
		$eCompositionSpeciesCustom = $cCompositionSpeciesDated->first();
		$eCompositionSpeciesCustom['status'] = CompositionSpecies::CUSTOM;
		$eCompositionSpeciesCustom['windowWeeks'] = [[50, 51]];
		CompositionSpecies::model()->select(['status', 'windowWeeks'])->update($eCompositionSpeciesCustom);

		$speciesIdCustom = $eCompositionSpeciesCustom['id'];
		$synchronized = fn(int $speciesId) => [CompositionSpecies::CULTIVATION, CompositionLib::getWindowWeeksFilled(array_keys($reference[$speciesId]))];

		// Une espèce datée qui porte déjà, sous la communauté, les semaines du plan de culture : ses mois
		// se dessinent pareil des deux côtés, elle ne changerait que d'état.
		$eCompositionSpeciesSame = $cCompositionSpeciesDated->last();
		$speciesIdSame = $eCompositionSpeciesSame['id'];
		$eCompositionSpeciesSame['windowWeeks'] = $synchronized($speciesIdSame)[1];
		// Hors `build()`, qui contrôlerait la forme des intervalles : `getWindowWeeksFilled()` la rend déjà, et le test veut exactement ces semaines.
		CompositionSpecies::model()->select(['windowWeeks'])->update($eCompositionSpeciesSame);

		$read = function() use($eCompositionPlan): array {

			$species = [];

			foreach(CompositionSpeciesLib::getByPlan($eCompositionPlan) as $eCompositionSpecies) {
				$species[$eCompositionSpecies['id']] = [$eCompositionSpecies['status'], $eCompositionSpecies['windowWeeks']];
			}

			return $species;

		};

		$speciesBefore = $read();

		// Ce que le deck liste : les espèces dont les mois dessinés changent, cochées sauf celle réglée
		// à la main.
		$synchronization = $this->getSynchronization($eCompositionPlan);
		$speciesIdsChecked = $this->getSpeciesIdsChecked($eCompositionPlan);

		$this->assertTrue(count($speciesIdsChecked) >= 2, 'deux espèces cochées d\'office au moins : '.count($speciesIdsChecked));
		$this->assertEquals([count($speciesIdsChecked), 1], [$synchronization['plants'], $synchronization['custom']], 'le deck compte à part l\'espèce réglée à la main');
		$this->assertTrue($synchronization['cCompositionSpecies']->offsetExists($speciesIdCustom), 'qu\'il liste');
		$this->assertFalse($synchronization['cCompositionSpecies']->offsetExists($speciesIdSame), 'il ne liste pas l\'espèce dont les mois se dessinent pareil');
		$this->assertEquals([], array_intersect($cCompositionSpeciesUndated->getIds(), $synchronization['cCompositionSpecies']->getKeys()), 'ni une espèce que le plan de culture ne date pas');

		$wrong = [];

		foreach($synchronization['cCompositionSpecies'] as $speciesId => $eCompositionSpecies) {

			if(array_keys($eCompositionSpecies['monthsCultivation']) !== range(1, 12)) {
				$wrong[] = $speciesId;
			}

		}

		$this->assertEquals([], $wrong, 'chaque espèce listée porte les douze mois du plan de culture : '.implode(', ', $wrong));

		// Le POST est contournable : un identifiant que le deck ne liste pas refuse tout l'envoi, qu'il
		// désigne une espèce non datée ou une espèce dont les mois ne changent pas. Rien n'est écrit.
		$speciesIdUndated = $cCompositionSpeciesUndated->first()['id'];

		foreach(['non datée' => $speciesIdUndated, 'aux mêmes mois' => $speciesIdSame, 'hors du plan' => 0] as $name => $speciesIdForeign) {

			$fw = new \FailWatch();

			CompositionPlanLib::synchronize($eCompositionPlan, array_merge($speciesIdsChecked, [$speciesIdForeign]));

			$this->assertEquals(['CompositionPlan::synchronizeListed'], array_column($fw->get(), 1), 'une espèce que le deck ne liste pas refuse l\'envoi : '.$name);
			$this->assertEquals($speciesBefore, $read(), 'et rien n\'est écrit : '.$name);

		}

		$fw = new \FailWatch();

		CompositionPlanLib::synchronize($eCompositionPlan, []);

		$this->assertEquals(['CompositionPlan::synchronizeEmpty'], array_column($fw->get(), 1), 'un envoi sans espèce cochée est refusé');
		$this->assertEquals([$speciesBefore, CompositionPlan::COMMUNITY], [$read(), CompositionPlanLib::getByStructure($eStructure)['reference']], 'et rien n\'est écrit, le plan reste sous la communauté');

		// L'envoi, une case décochée.
		$speciesIdUnchecked = array_shift($speciesIdsChecked);

		CompositionPlanLib::synchronize($eCompositionPlan, $speciesIdsChecked);

		$speciesAfter = $read();

		$this->assertEquals(CompositionPlan::CULTIVATION, CompositionPlanLib::getByStructure($eStructure)['reference'], 'le plan est synchronisé');

		$wrong = [];

		foreach($cCompositionSpeciesDated as $eCompositionSpecies) {

			$speciesId = $eCompositionSpecies['id'];

			if(in_array($speciesId, [$speciesIdCustom, $speciesIdUnchecked], TRUE) === FALSE and $speciesAfter[$speciesId] !== $synchronized($speciesId)) {
				$wrong[] = $speciesId;
			}

		}

		$this->assertEquals([], $wrong, 'les espèces cochées prennent la saison du plan de culture : '.implode(', ', $wrong));
		$this->assertEquals($synchronized($speciesIdSame), $speciesAfter[$speciesIdSame], 'celle dont les mois ne changent pas le suit sans avoir été listée');
		$this->assertEquals([CompositionSpecies::CUSTOM, [[50, 51]]], $speciesAfter[$speciesIdCustom], 'celle réglée à la main garde sa saison');
		$this->assertEquals([CompositionSpecies::CUSTOM, $speciesBefore[$speciesIdUnchecked][1]], $speciesAfter[$speciesIdUnchecked], 'la décochée garde ses semaines, qui deviennent choisies à la main');

		$synchronization = $this->getSynchronization($eCompositionPlan);

		$this->assertEquals([0, 2], [$synchronization['plants'], $synchronization['custom']], 'elle sort du compte, et le deck n\'a plus rien à cocher');

		foreach($cCompositionSpeciesUndated as $eCompositionSpecies) {
			$this->assertEquals($speciesBefore[$eCompositionSpecies['id']], $speciesAfter[$eCompositionSpecies['id']], 'une espèce non datée garde sa saison');
		}

		$windows = CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan);
		$linesAfter = $this->getLines($eCompositionPlan);

		$outside = [];

		foreach($linesAfter as [$speciesId, $week]) {

			if(in_array($week, $windows[$speciesId] ?? [], TRUE) === FALSE) {
				$outside[] = $speciesId.' en semaine '.$week;
			}

		}

		$this->assertEquals([], $outside, 'une espèce servie hors de sa saison : '.implode(', ', $outside));
		$this->assertDiffer($linesBefore, $linesAfter, 'les propositions ont changé');

		// Sa case cochée, l'espèce réglée à la main suit le plan de culture à son tour.
		CompositionPlanLib::synchronize($eCompositionPlan, [$speciesIdCustom]);

		$this->assertEquals($synchronized($speciesIdCustom), $read()[$speciesIdCustom], 'sa case cochée, celle réglée à la main aussi');

	}

	/**
	 * L'application du plan de culture vit en tête de l'onglet « Espèces » (r29, #79, r48a, r51b) : un
	 * deck dépliable qui compte les espèces que le plan de culture date à d'autres mois, les montre
	 * une par une avec leurs mois des deux côtés et une case, décochée pour une espèce réglée à la
	 * main, et les envoie par son bouton. Appliqué, le plan n'a plus rien à cocher, et le deck
	 * disparaît. Les onglets des paniers et de la synthèse ne le calculent pas.
	 * Sur un plan synchronisé, les semaines que la seule conservation ouvre sont de saison, et vertes
	 * comme les autres (r29, #87).
	 */
	public function testRender(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure);

		// Seul qui peut écrire le plan voit le bloc.
		$this->connectAdmin($eStructure);

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);

		$this->assertFalse(array_key_exists('synchronization', $eCompositionPlanScreen->getArrayCopy()), 'l\'onglet d\'une taille ne calcule pas la synchronisation, l\'onglet « Espèces » la lit à son ouverture');

		$document = fn() => \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$this->getSpeciesTab($eStructure).'</body></html>', LIBXML_NOERROR);
		$values = fn(\Dom\HTMLDocument $document, string $selector) => array_map(fn(\Dom\Element $node) => (int)$node->getAttribute('value'), iterator_to_array($document->querySelectorAll($selector)));

		$html = $this->getSpeciesTab($eStructure);
		$dom = $document();

		$synchronization = $this->getSynchronization($eCompositionPlan);
		$plants = $synchronization['plants'];
		// L'onglet range ses espèces par nom, et le deck les liste dans cet ordre.
		$speciesIds = array_values(array_intersect(
			CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES)['cCompositionSpecies']->getKeys(),
			$synchronization['cCompositionSpecies']->getKeys()
		));

		$this->assertTrue($plants >= 2, 'des espèces datées à d\'autres mois');
		$this->assertEquals($plants, count($speciesIds), 'toutes à l\'onglet');
		$this->assertEquals(0, $synchronization['custom'], 'aucune réglée à la main');
		$this->assertEquals(1, substr_count($html, 'data-ajax-form="/toolbox/composition:doSynchronize"'), 'le deck a son formulaire, qui part à la synchronisation');

		$table = '.util-deck-fold > .util-deck-content > .util-table.toolbox-composition-synchronize-table';

		$this->assertEquals(1, $dom->querySelectorAll($table.' > .util-table-head > .util-checkbox input[type="checkbox"][onclick]:not([name])')->length, 'la case de tête, qui ne part pas avec l\'envoi');
		$this->assertEquals($speciesIds, $values($dom, $table.' > .util-table-row > .util-checkbox input[name="species[]"]'), 'une case par espèce listée, dans l\'ordre de l\'onglet');
		$this->assertEquals($speciesIds, $values($dom, $table.' input[name="species[]"][checked]'), 'toutes cochées');

		$button = $dom->querySelector('.util-deck-content > .toolbox-composition-synchronize-submit > button[type="submit"].btn.btn-primary');

		$this->assertEquals('ToolboxCompositionSynchronize.update()', $button->getAttribute('onrender'), 'le bouton se recompte au rendu');

		// Une espèce listée passe à la main : elle quitte le compte du titre pour la seconde ligne, et
		// sa case se décoche.
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);

		$eCompositionSpecies = $synchronization['cCompositionSpecies']->first();
		$eCompositionSpecies['status'] = CompositionSpecies::CUSTOM;
		// Hors `build()`, qui exigerait une saison avec l'état : l'espèce garde la sienne, seul l'état change.
		CompositionSpecies::model()->select(['status'])->update($eCompositionSpecies);

		$speciesIdCustom = $eCompositionSpecies['id'];

		$dom = $document();
		$synchronization = $this->getSynchronization($eCompositionPlan);

		$this->assertEquals([$plants - 1, 1], [$synchronization['plants'], $synchronization['custom']], 'une espèce de moins dans le compte, une de plus à part');
		$this->assertEquals($speciesIds, $values($dom, $table.' input[name="species[]"]'), 'la liste la garde');
		$this->assertEquals(array_values(array_diff($speciesIds, [$speciesIdCustom])), $values($dom, $table.' input[name="species[]"][checked]'), 'décochée');

		// Sans espèce à cocher d'office, le deck disparaît, même avec une espèce réglée à la main que
		// le plan de culture date à d'autres mois.
		$this->synchronize($eCompositionPlan);

		$synchronization = $this->getSynchronization($eCompositionPlan);

		$this->assertEquals([0, 1], [$synchronization['plants'], $synchronization['custom']], 'plus rien à cocher, l\'espèce réglée à la main reste datée à d\'autres mois');

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);

		// La synchronisation écrit dans la saison les semaines que la seule conservation ouvre, et la
		// grille les passe au vert comme les semaines de récolte, sans marque à part (r29, #87).
		$reference = CompositionLib::getReferenceWindows(CompositionLib::getHarvestWeeks($eStructure, self::SEASON), CompositionSpeciesLib::getPlants($cCompositionSpecies)['plants'], $source['storage']);

		$storedSeason = 0;

		foreach($eCompositionPlanScreen['eCompositionFormula']['grid'] as $group) {

			foreach($group['species'] as $row) {
				$storedSeason += count(array_intersect(array_keys($reference[$row['species']['id']] ?? [], FALSE, TRUE), $row['weeksSeason']));
			}

		}

		$this->assertTrue($storedSeason > 0, 'des semaines que la seule conservation ouvre, de saison');

	}

	/**
	 * Un chou s'ouvre à la main dans une semaine qui sert déjà un autre chou (r34d, retour
	 * du 30/09, « vire l'alerte le user fait ce qu'il veut ») : sa case porte
	 * `toolbox-composition-grid-cell` et son titre, comme celle d'une semaine sans chou, et la bulle
	 * de la semaine n'a plus d'emplacement de refus. La proposition garde la règle, que
	 * `CompositionPanelTest::testGridCabbage()` éprouve sur le relevé réel.
	 *
	 * La grille est lue sur un relevé construit à la main (`$source`), qui ne dit que ce que
	 * `getGrid()` interroge, et sur les espèces du référentiel, dont elle relit les noms.
	 */
	public function testGridCabbageOpen(): void {

		$cPlantCabbage = \plant\Plant::model()
			->select(['id', 'name', 'organ'])
			->whereStructure(NULL)
			->whereName('LIKE', 'Chou %')
			->sort(['id' => SORT_ASC])
			->getCollection(0, 2);

		$ePlantOther = \plant\Plant::model()
			->select(['id', 'name', 'organ'])
			->whereStructure(NULL)
			->whereName('Carotte')
			->get();

		if($cPlantCabbage->count() < 2 or $ePlantOther->empty()) {
			$this->assertFail('le référentiel n\'a pas deux choux et une carotte : le contrôle ne verrait rien');
			return;
		}

		$ePlantCabbageServed = $cPlantCabbage[0];
		$ePlantCabbageRefused = $cPlantCabbage[1];

		// Une espèce du plan par fiche, sous l'identifiant de la fiche : la grille ne lit que l'espèce,
		// et le relevé fait main se range par ces mêmes identifiants.
		$cCompositionSpecies = new \Collection();

		foreach([$ePlantCabbageServed, $ePlantCabbageRefused, $ePlantOther] as $ePlant) {
			$cCompositionSpecies[$ePlant['id']] = new CompositionSpecies(['id' => $ePlant['id'], 'plant' => $ePlant, 'name' => NULL]);
		}

		$line = fn(\plant\Plant $ePlant) => new CompositionLine(['species' => $cCompositionSpecies[$ePlant['id']], 'origin' => CompositionLine::USER, 'quantity' => 1.0, 'quantityProposed' => NULL]);

		// La semaine 23 sert un chou et une carotte, la semaine 24 la seule carotte, la semaine 25 les
		// deux choux, comme une composition écrite avant la règle.
		$cCompositionWeek = new \Collection([
			new CompositionWeek(['week' => 23, 'cCompositionLine' => new \Collection([$line($ePlantCabbageServed), $line($ePlantOther)])]),
			new CompositionWeek(['week' => 24, 'cCompositionLine' => new \Collection([$line($ePlantOther)])]),
			new CompositionWeek(['week' => 25, 'cCompositionLine' => new \Collection([$line($ePlantCabbageServed), $line($ePlantCabbageRefused)])]),
		]);

		$units = array_fill_keys([$ePlantCabbageServed['id'], $ePlantCabbageRefused['id'], $ePlantOther['id']], ['quantity' => 1.0]);

		$source = [
			'availability' => [],
			'seasons' => [],
			'units' => $units,
			'frequency' => ['byWeek' => [], 'byYear' => []],
			'cabbages' => [$ePlantCabbageServed['id'] => TRUE, $ePlantCabbageRefused['id'] => TRUE],
			'plants' => [],
			'speciesBare' => [],
			'priced' => [$ePlantCabbageRefused['id'] => TRUE],
		];

		$grid = CompositionLib::getGrid($cCompositionWeek, self::SEASON, [$ePlantCabbageRefused['id'] => [23, 24]], $source, $cCompositionSpecies);

		$rows = [];

		foreach($grid as $group) {
			foreach($group['species'] as $row) {
				$rows[$row['species']['id']] = $row;
			}
		}

		$rowRefused = $rows[$ePlantCabbageRefused['id']];

		$this->assertEquals([23 => 23, 24 => 24, 25 => 25], $rowRefused['open'], 'les cases des semaines 23, 24 et 25 s\'ouvrent, chou servi ou non');
		$this->assertEquals([23 => 23, 24 => 24, 25 => 25], $rows[$ePlantCabbageServed['id']]['open'], 'celles du chou servi aussi');

	}

	/**
	 * Le nombre de paniers (lot r23b2) : sur un plan synchronisé, le stock de chaque semaine
	 * se partage entre les paniers, donc en changer le nombre recompose les semaines non corrigées ;
	 * la semaine corrigée garde ses lignes. Le pendant : sous la communauté, le même changement ne
	 * touche à aucune ligne. Trente mille paniers ne laissent à chacun presque rien du stock : la
	 * différence ne peut pas manquer.
	 */
	public function testBasketsRecompose(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure);

		$lines = $this->getLines($eCompositionPlan);

		$this->setBaskets($eCompositionPlan, 30000);

		$this->assertEquals($lines, $this->getLines($eCompositionPlan), 'sous la communauté, aucune ligne ne bouge');

		$this->setBaskets($eCompositionPlan, 10);

		$this->synchronize($eCompositionPlan);

		$lines = $this->getLines($eCompositionPlan);

		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();
		$cCompositionWeek = CompositionWeekLib::getByFormula($eCompositionFormula);

		$this->assertTrue($cCompositionWeek->count() > 1, 'des semaines écrites');

		$eCompositionWeekAmended = $cCompositionWeek->first();
		CompositionWeekLib::amend($eCompositionWeekAmended);

		$weekAmended = $eCompositionWeekAmended['week'];
		$linesAmended = array_values(array_filter($lines, fn(array $line) => $line[1] === $weekAmended));

		$this->assertTrue($linesAmended !== [], 'la semaine corrigée a des lignes');

		$this->setBaskets($eCompositionPlan, 30000);

		$linesAfter = $this->getLines($eCompositionPlan);

		$this->assertEquals($linesAmended, array_values(array_filter($linesAfter, fn(array $line) => $line[1] === $weekAmended)), 'la semaine corrigée garde ses lignes');

		$others = fn(array $lines) => array_values(array_filter($lines, fn(array $line) => $line[1] !== $weekAmended));

		$this->assertDiffer($others($lines), $others($linesAfter), 'les autres semaines se recomposent');
		$this->assertTrue(CompositionWeekLib::getByFormulaAndWeek($eCompositionFormula, $weekAmended)['amended'], 'et restent corrigées');

	}

	/**
	 * L'aperçu du choix « Selon la saison » (lot r23b2) dit ce qu'on retrouverait en y revenant, et sur
	 * un plan synchronisé, ce qu'on retrouverait est plafonné par le stock : une espèce du
	 * plan de culture réglée sur « 1 semaine sur 5 » annonce ce que la grille servait d'elle avant,
	 * comme sous la communauté (`CompositionFrequencyTest::testQuickForm()`). L'espèce retenue est
	 * la première que le stock borne, celle où un aperçu sans stock se tromperait.
	 */
	public function testFrequencyPreviewStock(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure);

		$this->synchronize($eCompositionPlan);

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		// L'espèce où l'aperçu sans stock se tromperait : ce que la proposition sert d'elle change
		// selon que le stock la borne ou non.
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$prices = CompositionLib::getPrices($eCompositionPlan, $source);
		$windows = CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan);

		$proposal = CompositionLib::proposeFormula($eCompositionPlan, $eCompositionFormula, $source, $prices);
		$proposalFree = CompositionLib::propose((float)$eCompositionFormula['price'], $eCompositionFormula['gap'], $eCompositionFormula['linesMin'], $eCompositionFormula['linesMax'], $eCompositionPlan['weeks'], $windows, $eCompositionPlan['season'], $source, $prices, CompositionSpeciesLib::getStepsByPlan($eCompositionPlan), $eCompositionFormula['version']);

		$eCompositionSpeciesScreen = new CompositionSpecies();

		foreach($eCompositionPlanScreen['eCompositionFormula']['grid'] as ['species' => $rows]) {

			foreach($rows as $row) {

				$speciesId = $row['species']['id'];

				if(
					$eCompositionSpeciesScreen->empty() and
					$row['eCompositionSpecies']->notEmpty() and
					$row['eCompositionSpecies']['served'] > 0 and
					CompositionLib::getProposalServed($proposal, $speciesId, $eCompositionPlan['season'], $windows, $source) !== CompositionLib::getProposalServed($proposalFree, $speciesId, $eCompositionPlan['season'], $windows, $source)
				) {
					$eCompositionSpeciesScreen = $row['eCompositionSpecies'];
				}

			}

		}

		$this->assertTrue($eCompositionSpeciesScreen->notEmpty(), 'une espèce servie que le stock borne');

		$eCompositionSpecies = CompositionSpeciesLib::getById($eCompositionSpeciesScreen['id'], CompositionSpecies::getSelection() + [
			'plan' => CompositionPlan::getSelection(),
		]);
		$eCompositionSpecies['plan']['structure'] = $eStructure;

		$eCompositionSpecies['frequency'] = CompositionSpecies::DIVERSIFICATION;
		CompositionSpeciesLib::update($eCompositionSpecies, ['frequency']);

		CompositionSpeciesLib::loadFrequency($eCompositionSpecies, NULL);

		$this->assertEquals([$eCompositionSpeciesScreen['served'], $eCompositionSpeciesScreen['candidate']], [$eCompositionSpecies['served'], $eCompositionSpecies['candidate']], 'ce qu\'on retrouverait, sous le stock');

	}

	/**
	 * Un plan que la reprise du lot r27 n'a pas encore traité (arbitrage r27 du 28/09) : ses espèces
	 * cochées n'ont pas de saison écrite. Il se lit sans lever, sur la saison que la reprise écrira
	 * (`CompositionSpeciesLib::applySeasonsUnwritten()`) : saisons et proposition sont les mêmes avant
	 * et après elle, sous la communauté comme synchronisé sur le plan de culture.
	 */
	public function testSeasonsUnwritten(): void {

		$eStructure = $this->getStructureCultivated();
		$this->connectAdmin($eStructure);

		$eCompositionPlan = $this->createStarted($eStructure);

		$this->checkSeasonsUnwritten($eStructure, $eCompositionPlan, [CompositionSpecies::NATURAL]);

		$this->synchronize($eCompositionPlan);

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlan['structure'] = $eStructure;

		$this->checkSeasonsUnwritten($eStructure, $eCompositionPlan, [CompositionSpecies::CULTIVATION]);

	}

	/**
	 * Remet le plan dans l'état d'avant la reprise, comme la base de production le porte au
	 * déploiement : chaque espèce sous `NATURAL`, sans saison, y compris celles que le départ a
	 * retirées faute de saison, qu'avant le lot rien ne retirait et que la reprise retire. Écrit par le modèle, cet état
	 * n'étant plus productible par le Lib. Puis lit la proposition, joue la reprise comme
	 * le script de `migration/2026-09-28-paniers-saison-de-vente.md`, et la relit. `$statuses` sont les statuts que la
	 * reprise doit écrire, pour que la comparaison ne passe pas sur un plan qui n'en exerce aucun.
	 */
	private function checkSeasonsUnwritten(\structure\Structure $eStructure, CompositionPlan $eCompositionPlan, array $statuses): void {

		$reference = $eCompositionPlan['reference'];

		// Une écriture de masse garde un tableau de valeurs : un élément porteur d'id la bornerait à
		// sa ligne (`Module::getUpdateCondition()`).
		$unwritten = CompositionSpecies::model()
			->wherePlan($eCompositionPlan)
			->whereStatus('!=', CompositionSpecies::CUSTOM)
			->update(['status' => CompositionSpecies::NATURAL, 'windowWeeks' => NULL]);

		$this->assertTrue($unwritten > 0, $reference.', des espèces sans saison écrite : '.$unwritten);

		$read = function() use($eStructure, $eCompositionPlan): array {

			$eCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

			$source = CompositionLib::getSourceByPlan($eCompositionPlan);
			$prices = CompositionLib::getPrices($eCompositionPlan, $source);

			$this->assertTrue(CompositionPlanLib::getForScreen($eStructure, NULL)->notEmpty(), 'l\'écran se lit');

			$tab = [];

			foreach(CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES)['cCompositionSpecies'] as $speciesId => $eCompositionSpecies) {
				$tab[$speciesId] = $eCompositionSpecies['months'];
			}

			return [
				'windows' => CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan),
				'proposal' => CompositionLib::proposeFormula($eCompositionPlan, $eCompositionFormula, $source, $prices),
				'tab' => $tab,
			];

		};

		$before = $read();

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'plant', 'name', 'status', 'windowWeeks'])
			->wherePlan($eCompositionPlan)
			->whereStatus(CompositionSpecies::NATURAL)
			->whereWindowWeeks(NULL)
			->getCollection(index: 'id');

		$seasons = CompositionSpeciesLib::getSeasonsUnwritten($eCompositionPlan, $cCompositionSpecies);

		$this->assertEquals($statuses, array_values(array_intersect($statuses, array_column($seasons, 'status'))), $reference.', la reprise écrit ces statuts : '.implode(', ', array_unique(array_column($seasons, 'status'))));

		foreach($seasons as $speciesId => $season) {

			$eCompositionSpecies = $cCompositionSpecies[$speciesId];
			$eCompositionSpecies['status'] = $season['status'];
			$eCompositionSpecies['windowWeeks'] = $season['windowWeeks'];

			CompositionSpecies::model()
				->select(['status', 'windowWeeks'])
				->update($eCompositionSpecies);

		}

		$after = $read();

		$this->assertEquals($after['windows'], $before['windows'], $reference.', les mêmes saisons avant et après la reprise');
		$this->assertEquals($after['proposal'], $before['proposal'], $reference.', la même proposition avant et après la reprise');
		$this->assertEquals($after['tab'], $before['tab'], $reference.', les mêmes mois dans l\'onglet des espèces avant et après la reprise');

	}

	/**
	 * Le nombre de paniers de la taille du plan, comme le règle `compositionFormula.p.php` (`quick`).
	 */
	private function setBaskets(CompositionPlan $eCompositionPlan, int $baskets): void {

		$eCompositionFormula = CompositionFormulaLib::getById(CompositionFormulaLib::getByPlan($eCompositionPlan)->first()['id'], CompositionFormula::getSelection() + [
			'plan' => CompositionPlan::getSelection(),
		]);

		$fw = new \FailWatch();

		$eCompositionFormula->build(['baskets'], ['baskets' => (string)$baskets]);

		$this->assertTrue($fw->ok(), $baskets.' paniers admis');

		CompositionFormulaLib::update($eCompositionFormula, ['baskets']);

	}

	/**
	 * Les lignes du plan, `[[espèce, semaine, unité, quantité]]`, dans un ordre stable.
	 */
	private function getLines(CompositionPlan $eCompositionPlan): array {

		$rows = CompositionLine::model()->pdo()->select(
			'SELECT l.species species, w.week week, l.unit unit, l.quantity quantity'
			.' FROM '.CompositionLine::model()->getDatabase().'.toolboxCompositionLine l'
			.' JOIN '.CompositionLine::model()->getDatabase().'.toolboxCompositionWeek w ON w.id = l.week'
			.' JOIN '.CompositionLine::model()->getDatabase().'.toolboxCompositionFormula f ON f.id = w.formula'
			.' WHERE f.plan = '.$eCompositionPlan['id']
			.' ORDER BY f.id, w.week, l.species, l.unit'
		)->fetchAll(\PDO::FETCH_ASSOC);

		return array_map(fn(array $row) => [(int)$row['species'], (int)$row['week'], (int)$row['unit'], (float)$row['quantity']], $rows);

	}

	/**
	 * Sur un plan synchronisé, le stock d'une taille déduit ce que servent les autres tailles, jamais
	 * ce qu'elle sert elle-même (`CompositionLib::getStock()`, lot r23). Les deux chemins qui le lisent
	 * lui passent chacun ces autres tailles (chantier Performances, P6) :
	 *
	 * - la composition (`CompositionLib::proposeFormula()`), qui les lit au moment de composer : la
	 *   proposition de la petite taille change quand la grande quitte le plan ;
	 * - la soudure de l'écran (`CompositionLib::getLean()`), qui les tire des tailles que l'écran a
	 *   lues : la soudure rendue est celle des seules autres tailles, et une copie de la taille ouverte
	 *   parmi elles la change, sans quoi le décor ne distinguerait rien.
	 *
	 * Le décor fait de la grande taille, à 30 000 paniers et ses semaines corrigées, celle qui épuise
	 * le stock.
	 */
	public function testStockReadsOtherFormulas(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure);

		$eCompositionFormulaSmall = CompositionFormulaLib::getByPlan($eCompositionPlan)->first();

		// La grande taille se compose sous la communauté, qui ne plafonne rien : elle sert les espèces
		// du plan de culture, que 30 000 paniers épuisent. Ses semaines corrigées restent écrites
		// quand la synchronisation refait les propositions.
		$eCompositionFormulaLarge = new CompositionFormula([
			'plan' => $eCompositionPlan,
			'name' => 'Le grand panier',
			'price' => 30.0,
			'baskets' => 30000,
		]);

		CompositionFormulaLib::add($eCompositionFormulaLarge);

		foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormulaLarge, CompositionSpeciesLib::getByPlan($eCompositionPlan)) as $eCompositionWeek) {
			CompositionWeekLib::amend($eCompositionWeek);
		}

		$this->synchronize($eCompositionPlan);

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlan['structure'] = $eStructure;

		$this->assertEquals(CompositionPlan::CULTIVATION, $eCompositionPlan['reference'], 'le plan est synchronisé');

		// La proposition de la petite taille, avec puis sans la grande.
		$eCompositionFormulaSmall = CompositionFormulaLib::getByPlan($eCompositionPlan)[$eCompositionFormulaSmall['id']];
		$source = CompositionLib::getSourceByPlan($eCompositionPlan);
		$prices = $source['pricesPlan'];

		$proposalWithLarge = CompositionLib::proposeFormula($eCompositionPlan, $eCompositionFormulaSmall, $source, $prices);

		CompositionFormulaLib::delete(CompositionFormulaLib::getByPlan($eCompositionPlan)[$eCompositionFormulaLarge['id']]);

		$proposalAlone = CompositionLib::proposeFormula($eCompositionPlan, $eCompositionFormulaSmall, $source, $prices);

		$this->assertTrue($proposalWithLarge !== $proposalAlone, 'la petite taille compose sur ce que la grande laisse au stock');

		// La soudure de la petite taille, seule au plan, réduite à ses semaines écrites impaires : les
		// semaines paires, sans panier écrit, sont de soudure et disent chacune les espèces que le
		// stock donne encore, après ce que la semaine d'avant y a pris. Compter ces paniers une
		// seconde fois, comme ceux d'une autre taille, en retire quand le stock couvre une prise sans
		// couvrir la seconde : le nombre de paniers qui tombe dans cette fenêtre dépend des récoltes
		// de la structure. Compter deux fois les paniers d'un essai revient à peu près à l'essai
		// suivant, qui en porte le double : dès que deux essais voisins rendent deux soudures
		// différentes, l'un des deux distingue.
		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, $eCompositionFormulaSmall['id']);
		$eCompositionFormulaOpened = $eCompositionPlanScreen['eCompositionFormula'];

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlanScreen);
		$cultivations = CompositionLib::getCultivations($eStructure, $eCompositionPlanScreen['season']);
		$windows = CompositionSpeciesLib::getWindows($cCompositionSpecies);
		$source = CompositionLib::getSourceByPlan($eCompositionPlanScreen, $windows, $cCompositionSpecies, $cultivations);
		$steps = CompositionSpeciesLib::getSteps($cCompositionSpecies);
		$cCompositionSpeciesPriced = CompositionSpeciesLib::getWithPrices($eCompositionPlanScreen, $source, $cCompositionSpecies, $cultivations);

		$cCompositionWeekOdd = $eCompositionFormulaOpened['cCompositionWeek']->find(fn(CompositionWeek $eCompositionWeek) => $eCompositionWeek['week'] % 2 === 1);

		$getLean = function(CompositionFormula $eCompositionFormula, \Collection $cCompositionFormula) use($eCompositionPlanScreen, $windows, $source, $steps, $cCompositionSpeciesPriced, $cultivations): array {
			$eCompositionPlanScreen['cCompositionFormula'] = $cCompositionFormula;
			return $this->getLeanPrint(CompositionLib::getLean($eCompositionPlanScreen, $eCompositionFormula, $windows, $source, $steps, $cCompositionSpeciesPriced, $cultivations));
		};

		$discriminated = FALSE;

		foreach([10, 20, 40, 80, 160, 320, 640, 1280, 2560] as $baskets) {

			$eCompositionFormula = new CompositionFormula([
				'id' => $eCompositionFormulaOpened['id'],
				'price' => $eCompositionFormulaOpened['price'],
				'baskets' => $baskets,
				'cCompositionWeek' => $cCompositionWeekOdd,
			]);

			// La même taille sous un autre identifiant : ce que ses paniers prendraient au stock s'ils
			// y étaient comptés comme ceux d'une autre taille.
			$eCompositionFormulaCopy = new CompositionFormula([
				'id' => 0,
				'baskets' => $baskets,
				'cCompositionWeek' => $cCompositionWeekOdd,
			]);

			$leanAlone = $getLean($eCompositionFormula, new \Collection());

			if($getLean($eCompositionFormula, new \Collection([0 => $eCompositionFormulaCopy, $eCompositionFormula['id'] => $eCompositionFormula])) === $leanAlone) {
				continue;
			}

			$discriminated = TRUE;

			$this->assertEquals($leanAlone, $getLean($eCompositionFormula, new \Collection([$eCompositionFormula['id'] => $eCompositionFormula])), $baskets.' paniers : la soudure ne compte pas la taille ouverte parmi les autres');

			break;

		}

		$this->assertTrue($discriminated, 'aucun nombre de paniers où compter la taille ouverte change sa soudure : le contrôle ne verrait rien');

	}

	/**
	 * Une soudure (`CompositionLib::getLean()`) où chaque espèce se lit par son identifiant : les
	 * espèces sont des objets que chaque lecture instancie à nouveau, et deux soudures égales ne
	 * portent donc jamais les mêmes.
	 */
	private function getLeanPrint(array $lean): array {

		$weeks = [];

		foreach($lean['weeks'] as $week => $leanWeek) {
			$weeks[$week] = [$leanWeek['cCompositionSpecies']->getIds(), $leanWeek['period']];
		}

		$periods = [];

		foreach($lean['periods'] as $period) {
			$periods[] = [
				$period['weeks'],
				array_map(fn(array $stretch) => [$stretch['species']['id'], $stretch['copy']['id'] ?? NULL, $stretch['weeks'], $stretch['needed']], $period['stretch']),
			];
		}

		return ['weeks' => $weeks, 'periods' => $periods];

	}

	/**
	 * Le départ sur toute la saison, chaque espèce sur la saison de la communauté, une taille à 18 €.
	 */
	private function createStarted(\structure\Structure $eStructure): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlan['weeks'] = $eCompositionPlan['seasonWeeks'];
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

	private function connectAdmin(\structure\Structure $eStructure): void {

		$eMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->where('m1.structure', $eStructure['id'])
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->sort(['m1.id' => SORT_ASC])
			->get();

		\user\ConnectionLib::setOnline($eMember['user']);

	}

	/**
	 * La structure sans plan qui date le plus d'espèces dans la saison : la synchronisation y a de quoi
	 * écrire.
	 */
	private function getStructureCultivated(): \structure\Structure {

		$planned = CompositionPlan::model()
			->select('structure')
			->getColumn('structure')
			->getIds();

		$eCultivation = \series\Cultivation::model()
			->select([
				'structure' => new \Sql('structure', 'int'),
			])
			->whereSeason(self::SEASON)
			->whereHarvestWeeksExpected('!=', NULL)
			->where('structure', 'NOT IN', $planned, if: $planned !== [])
			->group(new \Sql('structure'))
			->sort(new \Sql('COUNT(DISTINCT plant) DESC, structure ASC'))
			->get();

		return \structure\StructureLib::getById($eCultivation['structure']);

	}


	/**
	 * L'onglet « Espèces » tel que la page le rend : le plan lu pour lui
	 * (`CompositionPlanLib::getForScreen()`), synchronisation comprise.
	 */
	/**
	 * Ce que le deck « Plan de culture » montre (r51b), lu sur toutes les espèces du plan.
	 */
	private function getSynchronization(CompositionPlan $eCompositionPlan): array {

		return CompositionPlanLib::getSynchronization($eCompositionPlan, CompositionSpeciesLib::getByPlan($eCompositionPlan));

	}

	/**
	 * Les espèces que le deck coche d'office : celles qu'il liste et dont personne n'a choisi les mois.
	 */
	private function getSpeciesIdsChecked(CompositionPlan $eCompositionPlan): array {

		return $this->getSynchronization($eCompositionPlan)['cCompositionSpecies']
			->find(fn(CompositionSpecies $eCompositionSpecies) => $eCompositionSpecies['status'] !== CompositionSpecies::CUSTOM)
			->getKeys();

	}

	/**
	 * « Appliquer le plan de culture », tel que le deck l'envoie quand on n'y touche pas.
	 */
	private function synchronize(CompositionPlan $eCompositionPlan): void {

		CompositionPlanLib::synchronize($eCompositionPlan, $this->getSpeciesIdsChecked($eCompositionPlan));

	}

	private function getSpeciesTab(\structure\Structure $eStructure): string {

		return new CompositionUi()->getSpecies(CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES));

	}

}
?>
