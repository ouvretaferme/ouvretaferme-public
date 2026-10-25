<?php
namespace toolbox;

/**
 * Le calendrier type (r48b) : d'où partent les saisons d'un plan de paniers.
 *
 * - le départ pose deux tuiles, le calendrier type et le plan de culture, à la structure qui a un
 *   plan de culture, et la seule tuile du calendrier aux autres (`CompositionPlanLib::getForStart()`,
 *   `CompositionStartUi`) ;
 * - un plan parti du calendrier porte `reference` CALENDAR et son climat, et chaque espèce que le
 *   calendrier date la saison de ce climat, sous `CALENDAR` (`CompositionPlanLib::create()`) ;
 * - recommencer garde ces saisons tant que la saison, la référence et le climat ne changent pas
 *   (`restart()`) ;
 * - « Synchroniser » fait passer le plan au plan de culture sans lui ôter son climat : une espèce
 *   que le plan de culture ne date pas garde la saison du calendrier (`synchronize()`) ;
 * - un plan écrit avant le calendrier, sous la communauté et sans climat, ne lit aucun calendrier.
 *
 * Le calendrier se lit dans la table de l'agrégé, semée par `aggregate/configure/calendarAi` : sur
 * une base où elle est vide, chaque méthode le dit au lieu de passer sans rien voir.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode.
 *
 * Lancement : dev/test package=toolbox file=CompositionCalendar
 */
class CompositionCalendarTest extends \dev\TransactionTest {

	const SEASON = 2026;

	protected function getTransactionModel(): \ModuleModel {
		return CompositionPlan::model();
	}

	/**
	 * Ce que le départ pose à l'arrivée : les tuiles à qui a un plan de culture, celle du plan de
	 * culture cochée quand la saison est cultivée ; le climat seul, sans tuile, à qui n'en a pas (r48e :
	 * un choix à une option ne se montre pas). Et la saison que chaque source donnerait à chaque espèce.
	 *
	 * La liste dit au repos ce qui s'y fait (r48e) : « Ajouter une espèce » à côté de son titre, et sur
	 * chaque rang la saison qui ouvre le menu des mois, et la corbeille.
	 */
	public function testStartReference(): void {

		$eStructure = $this->getStructureUncultivated();
		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);

		$this->assertEquals([FALSE, CompositionPlan::CALENDAR], [$eCompositionPlan['tiles'], $eCompositionPlan['reference']], 'sans plan de culture, le calendrier et rien d\'autre à choisir');
		$this->assertEquals(CompositionLib::getClimate($eStructure), $eCompositionPlan['climate'], 'le climat de la structure');
		$this->assertEquals(NULL, $eCompositionPlan['climatePlan'], 'aucun plan à recommencer');

		$xpath = $this->getStart($eCompositionPlan);

		$this->assertEquals(1, $xpath->query('//input[@type="hidden"][@name="reference"][@value="'.CompositionPlan::CALENDAR.'"]')->length, 'le calendrier part dans un champ caché');
		$this->assertEquals(1, $xpath->query('//select[@name="climate"]')->length, 'le climat se choisit');

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);

		$this->assertEquals([TRUE, CompositionPlan::CULTIVATION], [$eCompositionPlan['tiles'], $eCompositionPlan['reference']], 'une saison cultivée, les tuiles et celle du plan de culture');

		$counts = array_fill_keys(array_merge([CompositionPlan::COMMUNITY, CompositionPlan::CULTIVATION], CompositionPlan::model()->getPropertyEnum('climate')), 0);

		foreach($eCompositionPlan['cCompositionSpecies'] as $eCompositionSpecies) {

			foreach($eCompositionSpecies['seasons'] as $source => $months) {

				$counts[$source]++;

				$this->assertEquals(range(1, 12), array_keys($months), 'douze mois par saison');

			}

		}

		$this->assertEquals($eCompositionPlan['cCompositionSpecies']->count(), $counts[CompositionPlan::COMMUNITY], 'la communauté date toutes les espèces du départ');
		$this->assertTrue($counts[CompositionPlan::CULTIVATION] > 0, 'le plan de culture en date');

		foreach(CompositionPlan::model()->getPropertyEnum('climate') as $climate) {
			$this->assertTrue($counts[$climate] > 0, 'le calendrier '.$climate.' date des espèces : sinon la table est vide, jouer aggregate/configure/calendarAi');
		}

		$xpath = $this->getStart($eCompositionPlan);

		$this->assertEquals(
			[CompositionPlan::CALENDAR, CompositionPlan::CULTIVATION],
			array_map(fn(\DOMElement $node) => $node->getAttribute('value'), iterator_to_array($xpath->query('//input[@type="radio"][@name="reference"]'))),
			'deux tuiles, le calendrier d\'abord'
		);
		$this->assertEquals(CompositionPlan::CULTIVATION, $xpath->query('//input[@type="radio"][@name="reference"][@checked]')->item(0)->getAttribute('value'), 'celle du plan de culture cochée');

		// Un rang par espèce proposable, chacun avec son statut en champ caché, et plus aucune case.
		$species = $eCompositionPlan['cCompositionSpecies']->count();
		$delivered = $eCompositionPlan['cCompositionSpecies']->find(fn(CompositionSpecies $e) => $e['status'] !== CompositionSpecies::EXCLUDED)->count();

		$this->assertTrue($delivered > 0 and $delivered < $species, 'des espèces livrées et d\'autres retirées : '.$delivered.' sur '.$species);
		$this->assertEquals($species, $xpath->query('//input[@type="hidden"][starts-with(@name, "status[")]')->length, 'un statut posté par espèce');
		$this->assertEquals('/toolbox/composition:addStartSpecies?id='.$eStructure['id'], $xpath->query('//a[@id="toolbox-composition-start-species"]')->item(0)->getAttribute('data-url'), '« Ajouter une espèce » ouvre le panneau du départ');
		// Aucun rang ne poste de mois au départ (r57, r58).
		$this->assertEquals(
			[0, $species],
			[
				$xpath->query('//div[contains(concat(" ", @class, " "), " toolbox-composition-species-start ")]//input[starts-with(@name, "months[")]')->length,
				$xpath->query('//div[contains(concat(" ", @class, " "), " toolbox-composition-species-start ")]//a[@data-action="toolbox-composition-start-remove"]')->length,
			],
			'chaque rang porte la corbeille ; aucun champ des mois'
		);

	}

	/**
	 * Un plan parti du calendrier : la référence et le climat sont écrits, chaque espèce que le
	 * calendrier date prend la saison de ce climat sous `CALENDAR`, les autres celle de la communauté,
	 * et l'onglet « Espèces » sépare la récolte de la conservation.
	 */
	public function testCreate(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure, CompositionPlan::CALENDAR, CompositionPlan::CONTINENTAL);

		$this->assertEquals([CompositionPlan::CALENDAR, CompositionPlan::CONTINENTAL], [$eCompositionPlan['reference'], $eCompositionPlan['climate']], 'la référence et le climat écrits');

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);
		$plants = CompositionSpeciesLib::getPlants($cCompositionSpecies)['plants'];

		$calendar = CompositionLib::getCalendarWindows(CompositionPlan::CONTINENTAL, $plants);
		$calendarOther = CompositionLib::getCalendarWindows(CompositionPlan::MEDITERRANEAN, $plants);
		$natural = CompositionLib::getNaturalWindows(CompositionLib::getSeasonWeeks(), self::SEASON, CompositionLib::getSourceByPlan($eCompositionPlan, []));

		$wrong = [];
		$counts = [CompositionSpecies::CALENDAR => 0, CompositionSpecies::NATURAL => 0, CompositionSpecies::EXCLUDED => 0];
		$distinct = 0;
		$stored = 0;

		foreach($cCompositionSpecies as $eCompositionSpecies) {

			$speciesId = $eCompositionSpecies['id'];

			if($eCompositionSpecies['status'] === CompositionSpecies::EXCLUDED) {
				$expected = [CompositionSpecies::EXCLUDED, NULL];
			} else if(array_key_exists($speciesId, $calendar)) {

				$expected = [CompositionSpecies::CALENDAR, CompositionLib::getWindowWeeksFilled(array_keys($calendar[$speciesId]))];

				if(array_key_exists($speciesId, $calendarOther) and $calendarOther[$speciesId] !== $calendar[$speciesId]) {
					$distinct++;
				}

				if(in_array(FALSE, $calendar[$speciesId], TRUE)) {
					$stored++;
				}

			} else {
				$expected = [CompositionSpecies::NATURAL, CompositionLib::getWindowWeeksFilled($natural[$speciesId])];
			}

			if([$eCompositionSpecies['status'], $eCompositionSpecies['windowWeeks']] !== $expected) {
				$wrong[] = $speciesId;
			}

			$counts[$eCompositionSpecies['status']]++;

		}

		$this->assertEquals([], $wrong, 'chaque espèce porte la saison du calendrier continental, sinon celle de la communauté : '.implode(', ', $wrong));
		$this->assertTrue($counts[CompositionSpecies::CALENDAR] > 0, 'des espèces datées par le calendrier : '.json_encode($counts));
		$this->assertTrue($distinct > 0, 'dont certaines que le méditerranéen date autrement : le contrôle verrait un autre climat');
		$this->assertTrue($stored > 0, 'et certaines que la conservation prolonge');

		// Aucune espèce n'est servie hors de sa saison.
		$windows = CompositionSpeciesLib::getWindowsByPlan($eCompositionPlan);
		$outside = [];

		foreach($this->getLines($eCompositionPlan) as [$speciesId, $week]) {

			if(in_array($week, $windows[$speciesId] ?? [], TRUE) === FALSE) {
				$outside[] = $speciesId.' en semaine '.$week;
			}

		}

		$this->assertEquals([], $outside, 'une espèce servie hors de sa saison : '.implode(', ', $outside));

		// Une espèce retirée puis remise reprend la saison du calendrier.
		$eCompositionSpecies = $cCompositionSpecies->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::CALENDAR)->first();
		$eCompositionSpecies['plan'] = $eCompositionPlan;

		$this->assertEquals(
			['status' => CompositionSpecies::CALENDAR, 'windowWeeks' => $eCompositionSpecies['windowWeeks']],
			CompositionSpeciesLib::getSeasonRestored($eCompositionSpecies),
			'une espèce remise reprend la saison du calendrier'
		);

	}

	/**
	 * Recommencer : le départ rouvre sur le calendrier et le climat du plan, les saisons écrites
	 * passent tant que la source ne change pas, et un autre climat les refait toutes, sauf celles
	 * réglées à la main.
	 */
	public function testRestart(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure, CompositionPlan::CALENDAR, CompositionPlan::CONTINENTAL);

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);

		$calendar = CompositionLib::getCalendarWindows(CompositionPlan::MEDITERRANEAN, CompositionSpeciesLib::getPlants($cCompositionSpecies)['plants']);

		$cCompositionSpeciesCalendar = $cCompositionSpecies->find(fn(CompositionSpecies $e) => (
			$e['status'] === CompositionSpecies::CALENDAR and
			array_key_exists($e['id'], $calendar)
		));

		$this->assertTrue($cCompositionSpeciesCalendar->count() >= 2, 'deux espèces datées par les deux climats : '.$cCompositionSpeciesCalendar->count());

		$eCompositionSpeciesCustom = $cCompositionSpeciesCalendar->first();
		$eCompositionSpeciesCustom['status'] = CompositionSpecies::CUSTOM;
		$eCompositionSpeciesCustom['windowWeeks'] = [[20, 25]];

		$eCompositionSpeciesCalendar = $cCompositionSpeciesCalendar->last();
		$eCompositionSpeciesCalendar['windowWeeks'] = [[30, 33]];

		CompositionSpeciesLib::update($eCompositionSpeciesCustom, ['status', 'windowWeeks']);
		CompositionSpeciesLib::update($eCompositionSpeciesCalendar, ['windowWeeks']);

		$windowsCalendar = CompositionLib::getWindowWeeksFilled(array_keys($calendar[$eCompositionSpeciesCalendar['id']]));

		$this->assertTrue([[30, 33]] !== $windowsCalendar, 'le calendrier méditerranéen donne une autre saison que celle écrite');

		// Recommencer recopie les espèces sous de nouveaux identifiants : chacune se retrouve par sa
		// fiche, qu'aucune variante ne partage dans un plan sorti du départ.
		$plantCustom = $eCompositionSpeciesCustom['plant']['id'];
		$plantCalendar = $eCompositionSpeciesCalendar['plant']['id'];

		$restart = function(?string $climate) use($eStructure, $plantCustom, $plantCalendar): array {

			$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure);
			$eCompositionPlanStart['weeks'] = $eCompositionPlanStart['seasonWeeks'];

			$arrival = [$eCompositionPlanStart['reference'], $eCompositionPlanStart['climate']];

			if($climate !== NULL) {
				$eCompositionPlanStart['climate'] = $climate;
			}

			CompositionPlanLib::restart($eCompositionPlanStart);

			$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
			$eCompositionPlanWritten['structure'] = $eStructure;

			$seasons = [];

			foreach(CompositionSpeciesLib::getByPlan($eCompositionPlanWritten) as $eCompositionSpecies) {
				$seasons[$eCompositionSpecies['plant']['id']] = $eCompositionSpecies->extracts(['status', 'windowWeeks']);
			}

			return [
				$arrival,
				[$eCompositionPlanWritten['reference'], $eCompositionPlanWritten['climate']],
				$seasons[$plantCustom],
				$seasons[$plantCalendar],
			];

		};

		[$arrival, $written, $custom, $season] = $restart(NULL);

		$this->assertEquals([CompositionPlan::CALENDAR, CompositionPlan::CONTINENTAL], $arrival, 'le départ rouvre sur le calendrier et le climat du plan');
		$this->assertEquals([CompositionPlan::CALENDAR, CompositionPlan::CONTINENTAL], $written, 'même climat, le plan les garde');
		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[20, 25]]], $custom, 'même climat, la saison réglée à la main passe');
		$this->assertEquals(['status' => CompositionSpecies::CALENDAR, 'windowWeeks' => [[30, 33]]], $season, 'même climat, la saison du calendrier passe telle qu\'elle était écrite');

		[, $written, $custom, $season] = $restart(CompositionPlan::MEDITERRANEAN);

		$this->assertEquals([CompositionPlan::CALENDAR, CompositionPlan::MEDITERRANEAN], $written, 'autre climat, écrit sur le plan');
		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => [[20, 25]]], $custom, 'autre climat, la saison réglée à la main passe encore');
		$this->assertEquals(
			['status' => CompositionSpecies::CALENDAR, 'windowWeeks' => $windowsCalendar],
			$season,
			'autre climat, l\'espèce prend la saison du calendrier méditerranéen'
		);

	}

	/**
	 * « Synchroniser » sur un plan parti du calendrier : le bloc parait dès que le plan de culture
	 * date une espèce, le plan passe au plan de culture et garde son climat, et une espèce que le plan
	 * de culture ne date pas garde la saison du calendrier, même une fois le plan recommencé.
	 */
	public function testSynchronize(): void {

		$eStructure = $this->getStructureCultivated();

		$harvestWeeks = CompositionLib::getHarvestWeeks($eStructure, self::SEASON);

		// Le départ ne livre que les espèces du plan de culture : une espèce que le calendrier date
		// et que la structure ne cultive pas est livrée en plus, pour que la synchronisation ait une
		// espèce à ne pas dater.
		$eCompositionPlan = $this->createStarted($eStructure, CompositionPlan::CALENDAR, CompositionPlan::CONTINENTAL, function(CompositionPlan $eCompositionPlan) use($harvestWeeks): void {

			$eCompositionSpeciesUndated = $eCompositionPlan['cCompositionSpecies']->find(fn(CompositionSpecies $e) => (
				$e['status'] === CompositionSpecies::EXCLUDED and
				array_key_exists(CompositionPlan::CONTINENTAL, $e['seasons']) and
				array_key_exists($e['plant']['id'], $harvestWeeks) === FALSE
			), clone: FALSE)->first();

			$eCompositionSpeciesUndated['status'] = CompositionSpecies::NATURAL;

		});

		$read = function() use($eCompositionPlan): array {

			$species = [];

			foreach(CompositionSpeciesLib::getByPlan($eCompositionPlan) as $eCompositionSpecies) {
				$species[$eCompositionSpecies['id']] = [$eCompositionSpecies['status'], $eCompositionSpecies['windowWeeks']];
			}

			return $species;

		};

		$speciesBefore = $read();

		$plants = CompositionSpeciesLib::getPlants(CompositionSpeciesLib::getByPlan($eCompositionPlan))['plants'];
		$reference = CompositionLib::getReferenceWindows($harvestWeeks, $plants, CompositionLib::projectByPlants(CompositionLib::getStorage($eStructure, array_values(array_unique($plants))), $plants));

		$dated = array_keys(array_filter($speciesBefore, fn(array $species, int $speciesId) => $species[0] === CompositionSpecies::CALENDAR and array_key_exists($speciesId, $reference), ARRAY_FILTER_USE_BOTH));
		$undated = array_keys(array_filter($speciesBefore, fn(array $species, int $speciesId) => $species[0] === CompositionSpecies::CALENDAR and array_key_exists($speciesId, $reference) === FALSE, ARRAY_FILTER_USE_BOTH));

		$this->assertTrue(count($dated) >= 2 and count($undated) >= 1, 'des espèces du calendrier que le plan de culture date, et une qu\'il ne date pas : '.count($dated).' et '.count($undated));

		// Le deck « Plan de culture » ne liste que des espèces datées, celles dont les mois dessinés
		// changent (r51b), et l'envoi de toutes ses cases fait suivre le plan de culture aux autres aussi.
		$synchronization = CompositionPlanLib::getSynchronization($eCompositionPlan, CompositionSpeciesLib::getByPlan($eCompositionPlan));
		$speciesIdsListed = $synchronization['cCompositionSpecies']->getKeys();

		$this->assertTrue($synchronization['plants'] >= 1 and $synchronization['custom'] === 0, 'le deck « Plan de culture » a des espèces à cocher, aucune réglée à la main : '.$synchronization['plants']);
		$this->assertEquals([], array_diff($speciesIdsListed, array_keys(array_filter($speciesBefore, fn(array $species, int $speciesId) => $species[0] !== CompositionSpecies::EXCLUDED and array_key_exists($speciesId, $reference), ARRAY_FILTER_USE_BOTH))), 'toutes datées par le plan de culture');

		CompositionPlanLib::synchronize($eCompositionPlan, $speciesIdsListed);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);

		$this->assertEquals([CompositionPlan::CULTIVATION, CompositionPlan::CONTINENTAL], [$eCompositionPlanWritten['reference'], $eCompositionPlanWritten['climate']], 'le plan suit le plan de culture et garde son climat');

		$speciesAfter = $read();

		$wrong = array_filter($dated, fn(int $speciesId) => $speciesAfter[$speciesId] !== [CompositionSpecies::CULTIVATION, CompositionLib::getWindowWeeksFilled(array_keys($reference[$speciesId]))]);

		$this->assertEquals([], $wrong, 'les espèces datées prennent la saison du plan de culture : '.implode(', ', $wrong));

		foreach($undated as $speciesId) {
			$this->assertEquals($speciesBefore[$speciesId], $speciesAfter[$speciesId], 'une espèce non datée garde la saison du calendrier');
		}

		// Recommencer sous le plan de culture : le départ ne demande pas de climat, le plan garde le sien.
		$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure);
		$eCompositionPlanStart['weeks'] = $eCompositionPlanStart['seasonWeeks'];

		$this->assertEquals([CompositionPlan::CULTIVATION, CompositionPlan::CONTINENTAL, CompositionPlan::CONTINENTAL], [$eCompositionPlanStart['reference'], $eCompositionPlanStart['climate'], $eCompositionPlanStart['climatePlan']], 'le départ rouvre sur le plan de culture, avec le climat du plan');

		// Le formulaire n'écrit pas de climat sous cette tuile (`CompositionPlan::properties()`).
		$eCompositionPlanStart['climate'] = NULL;

		CompositionPlanLib::restart($eCompositionPlanStart);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);

		$this->assertEquals([CompositionPlan::CULTIVATION, CompositionPlan::CONTINENTAL], [$eCompositionPlanWritten['reference'], $eCompositionPlanWritten['climate']], 'recommencé, le plan garde son climat');

		// Recommencer recopie les espèces sous de nouveaux identifiants : chacune se retrouve par sa
		// fiche, qu'aucune variante ne partage dans un plan sorti du départ.
		$speciesRestarted = [];

		foreach(CompositionSpeciesLib::getByPlan($eCompositionPlanWritten) as $eCompositionSpecies) {
			$speciesRestarted[$eCompositionSpecies['plant']['id']] = [$eCompositionSpecies['status'], $eCompositionSpecies['windowWeeks']];
		}

		foreach($undated as $speciesId) {
			$this->assertEquals($speciesBefore[$speciesId], $speciesRestarted[$plants[$speciesId]], 'recommencé, une espèce non datée garde la saison du calendrier');
		}

	}

	/**
	 * Un plan écrit avant le calendrier suit la communauté et n'a pas de climat : rien n'y lit le
	 * calendrier, ni ses saisons, ni une espèce remise, ni l'onglet « Espèces ». Seul le départ, rouvert
	 * pour recommencer, lui fait choisir une source.
	 */
	public function testCommunity(): void {

		$eStructure = $this->getStructureCultivated();
		$eCompositionPlan = $this->createStarted($eStructure, CompositionPlan::COMMUNITY, NULL);

		$cCompositionSpecies = CompositionSpeciesLib::getByPlan($eCompositionPlan);

		$statuses = array_values(array_unique($cCompositionSpecies->getColumn('status')));
		sort($statuses);

		$this->assertEquals([CompositionSpecies::EXCLUDED, CompositionSpecies::NATURAL], $statuses, 'des espèces sur la saison de la communauté, ou retirées');

		$plants = CompositionSpeciesLib::getPlants($cCompositionSpecies)['plants'];

		$this->assertTrue(CompositionLib::getCalendarWindows(CompositionPlan::CONTINENTAL, $plants) !== [], 'le calendrier date ces espèces : le contrôle verrait une lecture');
		$this->assertEquals([], CompositionLib::getCalendarWindows($eCompositionPlan['climate'], $plants), 'un plan sans climat ne lit aucun calendrier');

		$eCompositionSpecies = $cCompositionSpecies->find(fn(CompositionSpecies $e) => $e['status'] === CompositionSpecies::NATURAL)->first();
		$eCompositionSpecies['plan'] = $eCompositionPlan;

		$this->assertEquals(
			['status' => CompositionSpecies::NATURAL, 'windowWeeks' => $eCompositionSpecies['windowWeeks']],
			CompositionSpeciesLib::getSeasonRestored($eCompositionSpecies),
			'une espèce remise reprend la saison de la communauté'
		);

		$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure);

		$this->assertEquals(CompositionPlan::CULTIVATION, $eCompositionPlanStart['reference'], 'recommencer propose le plan de culture de la saison');
		$this->assertEquals(NULL, $eCompositionPlanStart['climatePlan'], 'et le plan n\'a pas de climat à garder');

	}

	/**
	 * Le départ envoyé sur toute la saison, une taille à 18 €, les espèces livrées comme le départ les
	 * propose, sous la référence et le climat donnés. `$prepare` retouche le départ avant l'envoi.
	 */
	private function createStarted(\structure\Structure $eStructure, string $reference, ?string $climate, ?\Closure $prepare = NULL): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlan['weeks'] = $eCompositionPlan['seasonWeeks'];
		$eCompositionPlan['reference'] = $reference;
		$eCompositionPlan['climate'] = $climate;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		if($prepare !== NULL) {
			$prepare($eCompositionPlan);
		}

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

	private function getStart(CompositionPlan $eCompositionPlan): \DOMXPath {

		$html = new CompositionStartUi()->getStart($eCompositionPlan);

		$libxmlErrors = libxml_use_internal_errors(TRUE);
		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);

		return new \DOMXPath($document);

	}

	private function getLines(CompositionPlan $eCompositionPlan): array {

		$cCompositionLine = CompositionLine::model()
			->select(['species', 'week' => ['week']])
			->whereWeek('IN', CompositionWeek::model()->select('id')->whereFormula('IN', CompositionFormulaLib::getByPlan($eCompositionPlan))->getCollection())
			->getCollection();

		$lines = [];

		foreach($cCompositionLine as $eCompositionLine) {
			$lines[] = [$eCompositionLine['species']['id'], $eCompositionLine['week']['week']];
		}

		return $lines;

	}

	/**
	 * La structure sans plan qui date le plus d'espèces dans la saison : le plan de culture y a de
	 * quoi dater, et le calendrier de quoi dater autrement.
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
	 * Une structure active sans plan de paniers et sans aucune culture, quelle que soit la saison :
	 * elle n'a pas de plan de culture à choisir.
	 */
	private function getStructureUncultivated(): \structure\Structure {

		$eStructure = \structure\Structure::model()
			->select(['id' => new \Sql('m1.id')])
			->join(CompositionPlan::model(), 'm1.id = m2.structure', 'LEFT')
			->join(\series\Cultivation::model(), 'm1.id = m3.structure', 'LEFT')
			->where('m2.id IS NULL')
			->where('m3.id IS NULL')
			->where('m1.status', \structure\Structure::ACTIVE)
			->sort(['m1.id' => SORT_ASC])
			->get();

		return \structure\StructureLib::getById($eStructure['id']);

	}

}
?>
