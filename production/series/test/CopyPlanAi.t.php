<?php
namespace series;

/**
 * La copie d'un plan de culture d'une ferme vers une autre emprunte-t-elle la chaîne de l'import ?
 *
 * La copie ne réécrit rien : elle exporte le plan de la ferme source dans la forme du CSV de
 * Ouvretaferme, le repasse par le convertisseur qui sert déjà à l'import d'un fichier, puis fait
 * analyser la liste obtenue contre la ferme d'arrivée. Trois pièces qui existaient toutes, une
 * seule à écrire — le découplage de l'analyse d'avec le cache du téléversement.
 *
 * Ce test rejoue ce chemin entier. Il ne pose rien dans le cache : c'est justement ce qu'il
 * prouve, que l'analyse se passe de la provenance.
 *
 * Il porte aussi la saison d'arrivée, qui est le second apport du lot. Sans elle, la saison venait
 * de la source et une saison absente de la ferme d'arrivée levait une erreur bloquante que rien ne
 * savait réparer. Le test l'établit dans les deux sens : une saison hors des bornes de la ferme
 * refuse, la même copie vers une saison choisie passe.
 *
 * La ferme source reçoit son plan dans la transaction du test, par l'import d'un fichier : un plan
 * pris tel qu'il est en base dépendrait de ce que d'autres y ont écrit, et ne dirait pas ce que le
 * test copie.
 *
 * Lancement : dev/test package=series file=CopyPlanAi
 */
class CopyPlanAiTest extends \dev\TransactionTest {

	/** Deux fermes du décor : celle dont on copie le plan, celle qui le reçoit. */
	private const SOURCE = \structure\FixtureAiLib::F1;
	private const DESTINATION = \structure\FixtureAiLib::F3;

	private const SPECIES = ['Carotte', 'Tomate'];

	private \structure\Structure $eStructureSource;

	private \user\User $eUserInitial;

	/** La saison où la source porte son plan. */
	private int $season;

	protected function getTransactionModel(): \ModuleModel {
		return Series::model();
	}

	public function setUp(): void {

		parent::setUp();

		// L'écriture d'une série demande un membre qui planifie : l'administrateur de la source.
		$this->eUserInitial = \user\ConnectionLib::getOnline();

		\user\ConnectionLib::setOnline(\user\User::model()
			->select(\user\User::getSelection())
			->whereEmail(\structure\FixtureAiLib::AUTHOR)
			->get());

		$this->eStructureSource = $this->getStructure(self::SOURCE);
		$this->season = (int)$this->eStructureSource->conf(\map\Configuration::class)['seasonLast'];

		$result = CsvLib::getCultivationsFromImport($this->eStructureSource, $this->getPlan());

		if($result['errorsCount'] > 0) {
			throw new \Exception('Le plan de la ferme source ne passe pas l\'analyse : '.json_encode($result['errorsGlobal']));
		}

		// Pas de cache de téléversement à purger : le plan n'a pas été déposé, il est écrit par le test.
		CsvLib::importCultivations($this->eStructureSource, $result['import'], reset: FALSE);

	}

	public function tearDown(): void {

		parent::tearDown();

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	private function getStructure(int $id): \structure\Structure {

		return \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId($id)
			->get();

	}

	/**
	 * Une série en bloc par espèce : la copie ne dépend pas de l'usage, et une série sur planches
	 * demanderait en plus une largeur de planche par défaut à la ferme source.
	 */
	private function getPlan(): array {

		$plan = [];

		foreach(self::SPECIES as $species) {

			$plan[] = [
				'series' => [
					'season' => $this->season,
					'name' => 'Plan copié '.$species,
					'mode' => Series::OPEN_FIELD,
					'use' => Series::BLOCK,
					'bed_length' => NULL,
					'block_area' => 100,
					'finished' => FALSE,
				],
				'cultivations' => [[
					'species' => $species,
					'planting_type' => NULL,
					'seeds_per_hole' => NULL,
					'young_plants_tray' => NULL,
					'young_plants_tray_size' => NULL,
					'sowing_date' => NULL,
					'planting_date' => NULL,
					'first_harvest_date' => NULL,
					'last_harvest_date' => NULL,
					'block_density' => NULL,
					'block_spacing_rows' => NULL,
					'block_spacing_plants' => NULL,
					'bed_density' => NULL,
					'bed_rows' => NULL,
					'bed_spacing_plants' => NULL,
					'harvest_unit' => Cultivation::KG,
					'yield_expected_area' => NULL,
					'yield_expected_length' => NULL,
					'varieties_unit' => Cultivation::PERCENT,
					'varieties' => [],
				]],
			];

		}

		return $plan;

	}

	/**
	 * Le plan de la source traverse la chaîne et arrive analysable.
	 */
	public function testPlanCrossesTheImportChain(): void {

		$import = CsvLib::getImportFromStructure($this->eStructureSource, $this->season);

		$this->assertDiffer(NULL, $import, "la conversion rend une liste et non un échec");
		$this->assertEquals(count(self::SPECIES), count($import), "la liste porte les séries de la source");

		// La forme attendue par l'analyse, et par `importCultivations()` derrière elle.
		$this->assertTrue(array_key_exists('series', $import[0]), "chaque entrée porte sa série");
		$this->assertTrue(array_key_exists('cultivations', $import[0]), "chaque entrée porte ses cultures");

		$eStructureDestination = $this->getStructure(self::DESTINATION);

		$result = CsvLib::getCultivationsFromImport($eStructureDestination, $import);

		$this->assertTrue(array_key_exists('errorsCount', $result), "l'analyse rend son compte d'erreurs");
		$this->assertTrue(array_key_exists('errorsGlobal', $result), "l'analyse rend ses erreurs globales");
		$this->assertEquals(count($import), count($result['import']), "toutes les séries traversent l'analyse");

	}

	/**
	 * La saison d'arrivée remplace celle de la source, et fait disparaître le conflit de saison.
	 *
	 * ⚠️ Le contrôle porte dans les deux sens. Prouver qu'une saison choisie ne lève pas d'erreur
	 * ne dit rien tant qu'on n'a pas vu la même copie en lever une sans elle : une analyse qui
	 * n'échoue jamais rendrait le premier contrôle vert pour la mauvaise raison.
	 */
	public function testDestinationSeasonRemovesTheSeasonConflict(): void {

		$eStructureDestination = $this->getStructure(self::DESTINATION);

		$import = CsvLib::getImportFromStructure($this->eStructureSource, $this->season);

		$this->assertTrue(count($import) > 0, "la liste porte au moins une série");

		// Une saison que la ferme d'arrivée ne connaît pas : elle est hors de ses bornes par
		// construction, `seasonFirst` et `seasonLast` encadrant les saisons ouvertes.
		$seasonOut = (int)$eStructureDestination->conf(\map\Configuration::class)['seasonLast'] + 10;

		foreach($import as $key => $entry) {
			$import[$key]['series']['season'] = $seasonOut;
		}

		$without = CsvLib::getCultivationsFromImport($eStructureDestination, $import);

		$this->assertEquals([$seasonOut], $without['errorsGlobal']['seasons'], "sans saison d'arrivée, la saison de la source est refusée");

		$seasonIn = (int)$eStructureDestination->conf(\map\Configuration::class)['seasonLast'];

		$with = CsvLib::getCultivationsFromImport($eStructureDestination, $import, $seasonIn);

		$this->assertEquals([], $with['errorsGlobal']['seasons'], "avec une saison d'arrivée, le conflit de saison n'existe plus");
		$this->assertEquals($seasonIn, $with['import'][0]['series']['season'], "la saison d'arrivée est celle qui sera écrite");

	}

}
?>
