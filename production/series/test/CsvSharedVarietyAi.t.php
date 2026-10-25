<?php
namespace series;

/**
 * L'import CSV reconnaît-il les deux variétés partagées ?
 *
 * « Variétés en mélange » et « Variété non renseignée » n'appartiennent à aucune
 * structure et à aucune espèce. La recherche de `CsvLib::getCultivations()` étant
 * bornée par les deux, elle ne pouvait pas les trouver : l'import les prenait pour
 * des variétés inconnues et annonçait qu'il allait les « ajouter à votre ferme ».
 * Mesuré le 23/08/2026 sur la copie de production — 339 « Variété non renseignée »
 * sur 20 structures et 29 « Variétés en mélange » sur 9, tous des doublons.
 *
 * Le test rejoue le vrai chemin : il pose un import dans le cache, appelle
 * `getCultivations()` et lit ce qu'elle en dit. Un test qui se contenterait de
 * comparer deux `toFqn()` prouverait que les noms coïncident, pas que le code les
 * rapproche.
 *
 * Lancement : dev/test package=series file=CsvSharedVarietyAi
 */
class CsvSharedVarietyAiTest extends \Test {

	/** La structure du décor qui possède l'espèce utilisée ci-dessous. */
	private const STRUCTURE = \structure\FixtureAiLib::F1;
	private const SPECIES = 'Carotte';

	private function getStructure(): \structure\Structure {

		return \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(self::STRUCTURE)
			->get();

	}

	/**
	 * Un import d'une série et d'une culture, portant les variétés demandées.
	 */
	private function run(array $varieties): array {

		$eStructure = $this->getStructure();

		$import = [[
			'series' => [
				'season' => (int)date('Y'),
				'name' => 'Import de contrôle',
				'mode' => 'open-field',
				'use' => 'bed',
				'bed_length' => 100,
				'block_area' => NULL,
				'finished' => FALSE,
			],
			'cultivations' => [[
				'species' => self::SPECIES,
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
				'harvest_unit' => NULL,
				'yield_expected_area' => NULL,
				'yield_expected_length' => NULL,
				'varieties_unit' => Cultivation::PERCENT,
				'varieties' => $varieties,
			]],
		]];

		\Cache::redis()->set('import-cultivations-'.self::STRUCTURE, $import);

		$result = CsvLib::getCultivations($eStructure);

		\Cache::redis()->delete('import-cultivations-'.self::STRUCTURE);

		return $result;

	}

	private function getVariety(string $name, int $part): array {
		return ['variety' => $name, 'part' => $part, 'eVariety' => new \plant\Variety()];
	}

	/**
	 * Les deux variétés partagées sont retrouvées, et rien n'est annoncé comme neuf.
	 */
	public function testSharedVarietiesAreRecognised(): void {

		$result = $this->run([
			$this->getVariety('Variétés en mélange', 50),
			$this->getVariety('Variété non renseignée', 50),
		]);

		$this->assertEquals([], $result['infoGlobal']['varieties'], "aucune variété n'est annoncée comme nouvelle");

		$varieties = $result['import'][0]['cultivations'][0]['varieties'];

		$this->assertEquals(\plant\PlantSetting::VARIETY_MIX, $varieties[0]['eVariety']['id'] ?? NULL, "« Variétés en mélange » est rattachée à la variété partagée");
		$this->assertEquals(\plant\PlantSetting::VARIETY_UNKNOWN, $varieties[1]['eVariety']['id'] ?? NULL, "« Variété non renseignée » est rattachée à la variété partagée");

	}

	/**
	 * La graphie d'un tableur ne doit pas rouvrir la brèche.
	 */
	public function testSpellingVariantsAreRecognised(): void {

		$result = $this->run([
			$this->getVariety('variétés en mélange', 50),
			$this->getVariety('VARIÉTÉ NON RENSEIGNÉE', 50),
		]);

		$this->assertEquals([], $result['infoGlobal']['varieties'], "la casse ne rouvre pas la brèche");

	}

	/**
	 * Le témoin : une variété que la structure n'a pas doit rester annoncée comme
	 * nouvelle, sans quoi le test ci-dessus passerait même si plus rien n'était
	 * jamais signalé.
	 */
	public function testUnknownVarietyIsStillAnnounced(): void {

		$name = 'Variété de contrôle '.uniqid();

		$result = $this->run([$this->getVariety($name, 100)]);

		$this->assertEquals([$name], array_values($result['infoGlobal']['varieties']), "une variété inconnue reste annoncée comme nouvelle");

		$varieties = $result['import'][0]['cultivations'][0]['varieties'];

		$this->assertTrue($varieties[0]['eVariety']->empty(), "elle n'est rattachée à rien");

	}

}
?>
