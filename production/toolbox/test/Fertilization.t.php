<?php
namespace toolbox;

/**
 * Le calculateur des besoins en fertilisation : les quatre mesures du contrat (planche
 * production-finir-npk-contrat, validée le 03/10/2026), sur une planche de 36 m² passe-pied
 * compris, puis le référentiel tel que `toolbox/configure/fertilizationReference` le pose.
 *
 * Les mesures se font sur des éléments montés en mémoire, aux valeurs du référentiel : le calcul se
 * juge seul, et une correction des références ne le fait pas rougir.
 *
 * Lancement : dev/test package=toolbox file=Fertilization
 */
class FertilizationTest extends \Test {

	// 75 cm travaillés et 45 de passe-pied sur 30 m : 36 m².
	const BED = ['use' => 'bed', 'bedWidth' => '75', 'alleyWidth' => '45', 'lengthTarget' => '30'];

	private function getCarrot(): array {

		$eFertilizationCrop = new FertilizationCrop(['exportedNitrogen' => 1.2, 'exportedPhosphate' => 1.0, 'exportedPotash' => 5.5]);
		$eFertilizationYield = new FertilizationYield(['yield' => 60, 'nitrogen' => 120, 'phosphate' => 60, 'potash' => 380]);

		return [$eFertilizationCrop, $eFertilizationYield];

	}

	private function getLambsLettuce(): array {

		$eFertilizationCrop = new FertilizationCrop(['exportedNitrogen' => 4.5, 'exportedPhosphate' => NULL, 'exportedPotash' => NULL]);
		$eFertilizationYield = new FertilizationYield(['yield' => 10, 'nitrogen' => 50, 'phosphate' => 20, 'potash' => 60]);

		return [$eFertilizationCrop, $eFertilizationYield];

	}

	private function getMaterial(?float $nitrogen, ?float $phosphate, ?float $potash, ?float $availableNitrogen, ?float $availablePhosphate): FertilizationMaterial {

		return new FertilizationMaterial([
			'nitrogen' => $nitrogen,
			'phosphate' => $phosphate,
			'potash' => $potash,
			'availableNitrogen' => $availableNitrogen,
			'availablePhosphate' => $availablePhosphate,
		]);

	}

	private function getCompost(): FertilizationMaterial {
		return $this->getMaterial(6.7, 3.6, 10.8, 0.25, 0.55);
	}

	private function getArea(): float {

		$fw = new \FailWatch();

		$area = FertilizationLib::getArea(FertilizationLib::buildSeries(self::BED));

		$this->assertTrue($fw->ok());

		return $area;

	}

	public function testArea(): void {

		$this->assertEquals(36.0, $this->getArea(), 'la surface compte le passe-pied');

		$this->assertEquals(100.0, FertilizationLib::getArea(FertilizationLib::buildSeries(['use' => 'block', 'areaTarget' => '100'])));
		$this->assertEquals(NULL, FertilizationLib::getArea(FertilizationLib::buildSeries(['use' => 'bed', 'bedWidth' => '75', 'alleyWidth' => '45', 'lengthTarget' => ''])), 'une longueur effacée est une saisie en cours');

	}

	/**
	 * La surface libre s'ouvre sur un hectare (04/10), et le calculateur de densités garde
	 * ses 100 m².
	 */
	public function testNewBlockArea(): void {

		$eStructure = \structure\StructureLib::getById(\structure\FixtureAiLib::F1);

		[$eSeries] = FertilizationLib::getNew($eStructure);
		$this->assertEquals(10000, $eSeries['areaTarget']);

		[$eSeries] = \toolbox\DensityLib::getNew($eStructure);
		$this->assertEquals(100, $eSeries['areaTarget']);

	}

	/**
	 * Carotte de garde à 60 t/ha et compost de fumier de bovins, sur ses teneurs totales (« le calcul
	 * doit tenir compte du total, pas du disponible la 1ere année », le 03/10) : 0,432 / 6,7 × 1 000
	 * = 64,5 kg pour couvrir l'azote, qui apportent 7 % de phosphore de plus que le mobilisé et 49 % de
	 * potassium de moins.
	 */
	public function testCarrotCompost(): void {

		[$eFertilizationCrop, $eFertilizationYield] = $this->getCarrot();

		$result = FertilizationLib::calculate($this->getArea(), $eFertilizationYield, $eFertilizationCrop, [$this->getCompost()]);

		$this->assertEquals(64.5, round($result['quantities'][0], 1));
		$this->assertEquals(0, $result['balance']['nitrogen']['gap']);
		$this->assertEquals(7, $result['balance']['phosphate']['gap']);
		$this->assertEquals(-49, $result['balance']['potash']['gap']);

		$this->assertEquals(0.432, round($result['balance']['nitrogen']['mobilized'], 3));
		$this->assertEquals(0.2592, round($result['balance']['nitrogen']['exported'], 4), '1,2 kg/t × 60 t/ha sur 36 m²');
		$this->assertEquals(1.188, round($result['balance']['potash']['exported'], 3));

	}

	/**
	 * La teneur d'une matière choisie se lit en % du produit brut sous l'en-tête « Teneur », à une
	 * décimale toujours affichée (« affiche les pourcentages je me suis trompé », puis « +1 chiffre après
	 * la virgule sur le % », le 03/10). La part disponible garde la précision de sa source, sans
	 * zéro de traîne. Le calcul garde la valeur exacte.
	 */
	public function testContentsArePercents(): void {

		$eFertilizationMaterial = $this->getMaterial(20, 3.6, 133.5, 0.25, 0.55);
		$eFertilizationMaterial['id'] = 1;
		$eFertilizationMaterial['name'] = 'Compost de fumier de bovins';
		$eFertilizationMaterial['source'] = new FertilizationSource(['id' => 1, 'name' => 'RMT 2019', 'url' => 'https://example.org/rmt.pdf']);

		$html = new FertilizationUi()->getMaterial(new \util\FormUi(), new \Collection([1 => $eFertilizationMaterial]), new \Collection(), 0, ['value' => '1', 'eFertilizationMaterial' => $eFertilizationMaterial]);

		$this->assertTrue(str_contains($html, '>'.s("{value} %", '2,0').'<'), '20 kg/t s\'écrivent 2,0 %, avec leur zéro');
		$this->assertTrue(str_contains($html, '>'.s("{value} %", '0,4').'<'), '3,6 kg/t s\'écrivent 0,4 %');
		$this->assertTrue(str_contains($html, '>'.s("{value} %", '13,4').'<'), '133,5 kg/t s\'écrivent 13,4 %');
		$this->assertTrue(str_contains($html, '>'.s("{value} %", '25').'<'), 'la part disponible sans zéro de traîne');

	}

	/**
	 * La même dose sur un hectare : 17 910 kg passent en tonnes, et la dose au m² en t/ha (« au dela de
	 * 1000 kg passer à la tonne et en tonne/ha », le 03/10). La planche reste en kg.
	 */
	public function testTonnes(): void {

		[$eFertilizationCrop, $eFertilizationYield] = $this->getCarrot();

		$eFertilizationMaterial = $this->getCompost();
		$eFertilizationMaterial['name'] = 'Compost de fumier de bovins';
		$materials = [0 => ['value' => '1', 'eFertilizationMaterial' => $eFertilizationMaterial]];

		$eSeries = FertilizationLib::buildSeries(['use' => 'block', 'areaTarget' => '10000']);
		$result = FertilizationLib::calculate(FertilizationLib::getArea($eSeries), $eFertilizationYield, $eFertilizationCrop, [$eFertilizationMaterial]);

		$html = new FertilizationUi()->getResult($eSeries, $materials, $result);

		$this->assertTrue(str_contains($html, '<div class="util-figure-value">17,9 <small>t</small></div>'), 'la quantité en tonnes');
		$this->assertTrue(str_contains($html, '17,9 t / ha'), 'la dose en t/ha');
		$this->assertEquals('1,2 t', FertilizationUi::getWeight(1234.0), 'un poids du bilan en tonnes au-delà de 1 000 kg');
		$this->assertEquals('12,0 kg', FertilizationUi::getWeight(12.0), 'une décimale sous 100');
		$this->assertEquals('774 kg', FertilizationUi::getWeight(773.73), 'aucune au-dessus');
		$this->assertEquals('432 g', FertilizationUi::getWeight(0.432), 'les grammes inchangés');

		// Le bilan suit la règle de la tuile (M-Prod le 03/10) : 120 kg d'azote mobilisés sur l'hectare.
		$this->assertTrue(str_contains($html, '<span class="badge fertilization-nitrogen">120 kg</span>'), 'le bilan sans décimale au-dessus de 100');

		$eSeries = FertilizationLib::buildSeries(self::BED);
		$result = FertilizationLib::calculate($this->getArea(), $eFertilizationYield, $eFertilizationCrop, [$eFertilizationMaterial]);

		$html = new FertilizationUi()->getResult($eSeries, $materials, $result);

		$this->assertTrue(str_contains($html, '1,8 kg / m²'), 'et sa dose au m²');

	}

	/**
	 * Corne broyée en principale, un engrais potassique à 300 kg/t de K₂O en complément : la corne couvre
	 * l'azote (0,432 / 133,5 × 1 000 = 3,24 kg), l'engrais le premier manque qu'il comble, le potassium
	 * ((1,368 - 3,24 × 0,0063) / 0,3 = 4,49 kg). Le peu de phosphore de la corne laisse un manque de 88 %.
	 */
	public function testHornAndPotash(): void {

		[$eFertilizationCrop, $eFertilizationYield] = $this->getCarrot();

		$result = FertilizationLib::calculate($this->getArea(), $eFertilizationYield, $eFertilizationCrop, [
			$this->getMaterial(133.5, 8.1, 6.3, 0.575, NULL),
			$this->getMaterial(NULL, NULL, 300, NULL, NULL),
		]);

		$this->assertEquals(3.24, round($result['quantities'][0], 2));
		$this->assertEquals(4.49, round($result['quantities'][1], 2));
		$this->assertEquals(-88, $result['balance']['phosphate']['gap']);
		$this->assertEquals(0, $result['balance']['potash']['gap']);

	}

	/**
	 * Une composition saisie, 4-3-3 : 0,432 / 40 × 1 000 = 10,8 kg.
	 */
	public function testComposition(): void {

		[$eFertilizationCrop, $eFertilizationYield] = $this->getCarrot();

		$fw = new \FailWatch();

		$eFertilizationMaterial = FertilizationLib::buildComposition(['nitrogen' => [2 => '4'], 'phosphate' => [2 => '3'], 'potash' => [2 => '3']], 2);

		$this->assertTrue($fw->ok());

		$result = FertilizationLib::calculate($this->getArea(), $eFertilizationYield, $eFertilizationCrop, [$eFertilizationMaterial]);

		$this->assertEquals(10.8, round($result['quantities'][0], 1));
		$this->assertEquals(50, $result['balance']['phosphate']['gap']);
		$this->assertEquals(-76, $result['balance']['potash']['gap']);

	}

	/**
	 * Un intrant de la structure pré-remplit la saisie manuelle (piste A, le 03/10) : ses
	 * teneurs en % deviennent les kg/t de la matière, P et K lus en oxydes, et l'azote compte entier,
	 * le calcul lisant la teneur totale. Un intrant que la
	 * structure n'a pas, ou que le calcul reçoit sans la liste des intrants, ne choisit rien.
	 */
	public function testTool(): void {

		$cTool = new \Collection([
			12 => new \work\Tool(['id' => 12, 'name' => 'Guanor', 'routineValue' => ['n' => 7.0, 'p' => 6.0, 'k' => 8.0, 'mgo' => NULL]]),
		]);

		$fw = new \FailWatch();

		$material = FertilizationLib::getMaterial([], 3, FertilizationLib::TOOL.'12', new \Collection(), $cTool);

		$this->assertTrue($fw->ok());
		$this->assertEquals(FertilizationMaterial::COMPOSITION, $material['value'], 'un intrant choisi passe en saisie manuelle');
		$this->assertEquals('Guanor', $material['eFertilizationMaterial']['name']);
		$this->assertEquals([70.0, 60.0, 80.0, NULL], [
			$material['eFertilizationMaterial']['nitrogen'],
			$material['eFertilizationMaterial']['phosphate'],
			$material['eFertilizationMaterial']['potash'],
			$material['eFertilizationMaterial']['availableNitrogen'],
		]);
		$this->assertEquals(70.0, FertilizationLib::getContents($material['eFertilizationMaterial'])['nitrogen'], 'l\'azote compte entier');

		$this->assertEquals('', FertilizationLib::getMaterial([], 3, FertilizationLib::TOOL.'13', new \Collection(), $cTool)['value'], 'un intrant hors de la structure ne choisit rien');
		$this->assertEquals('', FertilizationLib::getMaterial([], 3, FertilizationLib::TOOL.'12', new \Collection())['value'], 'le calcul ne lit pas d\'intrant');

	}

	/**
	 * Mâche de plein champ à 10 t/ha et compost : 0,18 / 6,7 × 1 000 = 26,9 kg. L'exporté du phosphore n'est pas publié
	 * et reste vide.
	 */
	public function testLambsLettuceCompost(): void {

		[$eFertilizationCrop, $eFertilizationYield] = $this->getLambsLettuce();

		$result = FertilizationLib::calculate($this->getArea(), $eFertilizationYield, $eFertilizationCrop, [$this->getCompost()]);

		$this->assertEquals(26.9, round($result['quantities'][0], 1));
		$this->assertEquals(34, $result['balance']['phosphate']['gap']);
		$this->assertEquals(34, $result['balance']['potash']['gap']);
		$this->assertEquals(NULL, $result['balance']['phosphate']['exported']);

	}

	/**
	 * Un complément qui n'apporte aucun élément encore en manque se dose à 0 et reste dans le calcul
	 * (écart déclaré le 03/10, retenu sous mandat).
	 */
	public function testUselessComplement(): void {

		[$eFertilizationCrop, $eFertilizationYield] = $this->getCarrot();

		$result = FertilizationLib::calculate($this->getArea(), $eFertilizationYield, $eFertilizationCrop, [
			$this->getCompost(),
			$this->getMaterial(122, NULL, NULL, 0.35, NULL),
		]);

		$this->assertEquals(2, count($result['quantities']));
		$this->assertEquals(0.0, $result['quantities'][1]);

	}

	/**
	 * La saisie telle que le formulaire la poste : un rendement d'une autre espèce revient au premier
	 * de l'espèce choisie, un complément sans matière reste dans la saisie sans entrer dans le calcul,
	 * et une composition refusée l'est sur son champ, à son index.
	 */
	public function testBuild(): void {

		$cFertilizationCrop = FertilizationCropLib::getList();
		$cFertilizationMaterial = FertilizationMaterialLib::getList();

		$eFertilizationCropCarrot = $cFertilizationCrop->find(fn(FertilizationCrop $e) => $e['plant']['fqn'] === 'carotte')->first();
		$eFertilizationCropOther = $cFertilizationCrop->find(fn(FertilizationCrop $e) => $e['plant']['fqn'] !== 'carotte')->first();

		$fw = new \FailWatch();

		[$eSeries, $eFertilizationCrop, $eFertilizationYield, $materials] = FertilizationLib::build(self::BED + [
			'crop' => (string)$eFertilizationCropCarrot['id'],
			'yield' => (string)$eFertilizationCropOther['cYield']->first()['id'],
			'material' => [0 => (string)$cFertilizationMaterial->first()['id'], 4 => ''],
		], $cFertilizationCrop, $cFertilizationMaterial);

		$this->assertTrue($fw->ok());
		$this->assertEquals($eFertilizationCropCarrot['cYield']->first()['id'], $eFertilizationYield['id'], 'le rendement d\'une autre espèce revient au premier de la carotte');
		$this->assertEquals([0, 4], array_keys($materials));

		$result = FertilizationLib::getResult($eSeries, $eFertilizationCrop, $eFertilizationYield, $materials);

		$this->assertEquals([0], array_keys($result['quantities']), 'le complément sans matière n\'entre pas dans le calcul');

		FertilizationLib::build(self::BED + [
			'crop' => (string)$eFertilizationCropCarrot['id'],
			'material' => [3 => FertilizationMaterial::COMPOSITION],
			'nitrogen' => [3 => '-1'],
		], $cFertilizationCrop, $cFertilizationMaterial);

		$this->assertEquals(1, $fw->count(), 'une teneur négative est refusée');

		$fw = new \FailWatch();

		$this->assertEquals(NULL, FertilizationLib::getResult(FertilizationLib::buildSeries(self::BED), new FertilizationCrop(), new FertilizationYield(), $materials), 'sans culture, pas de résultat');

	}

	/**
	 * La culture saisie à la main (onglet « Saisie manuelle », Q5 A, le 03/10) : le mobilisé
	 * seul, sans espèce donc sans exporté. Un champ vide reste inconnu, une valeur hors des bornes du
	 * schéma est refusée sur son champ (`nitrogen[manual]`), avec son message, et sans aucun mobilisé
	 * le calcul n'a pas de résultat.
	 */
	public function testManual(): void {

		$cFertilizationCrop = FertilizationCropLib::getList();
		$cFertilizationMaterial = FertilizationMaterialLib::getList();

		$fw = new \FailWatch();

		[$eSeries, $eFertilizationCrop, $eFertilizationYield, $materials] = FertilizationLib::build(self::BED + [
			'crop' => FertilizationCrop::MANUAL,
			'nitrogen' => [FertilizationCrop::MANUAL => '120', 0 => '4'],
			'phosphate' => [FertilizationCrop::MANUAL => '60', 0 => '3'],
			'potash' => [FertilizationCrop::MANUAL => '', 0 => '3'],
			'material' => [0 => FertilizationMaterial::COMPOSITION],
		], $cFertilizationCrop, $cFertilizationMaterial);

		$this->assertTrue($fw->ok());
		$this->assertTrue($eFertilizationCrop->empty(), 'aucune espèce');
		$this->assertEquals([120.0, 60.0, NULL], [(float)$eFertilizationYield['nitrogen'], (float)$eFertilizationYield['phosphate'], $eFertilizationYield['potash']], 'un champ vide reste inconnu');

		$result = FertilizationLib::getResult($eSeries, $eFertilizationCrop, $eFertilizationYield, $materials);

		$this->assertEquals(10.8, round($result['quantities'][0], 1), 'la composition de 4 % d\'azote couvre les 0,432 kg mobilisés sur 36 m²');
		$this->assertEquals([NULL, NULL, NULL], array_column($result['balance'], 'exported'), 'sans espèce, pas d\'exporté');
		$this->assertEquals(NULL, $result['balance']['potash']['mobilized']);

		FertilizationLib::build(self::BED + [
			'crop' => FertilizationCrop::MANUAL,
			'nitrogen' => [FertilizationCrop::MANUAL => '-5'],
		], $cFertilizationCrop, $cFertilizationMaterial);

		$this->assertEquals(1, $fw->count(), 'un mobilisé négatif est refusé');

		[[$failName, $message, $wrapper]] = $fw->format();

		$this->assertEquals('FertilizationYield::nitrogen.check', $failName);
		$this->assertEquals('nitrogen['.FertilizationCrop::MANUAL.']', $wrapper, 'l\'erreur tombe sous son champ');
		$this->assertTrue($message !== NULL, 'le refus a son message dans Alert.u.php');

		$fw = new \FailWatch();

		$eFertilizationYieldEmpty = FertilizationLib::buildMobilized(['crop' => FertilizationCrop::MANUAL]);

		$this->assertEquals(NULL, FertilizationLib::getResult($eSeries, new FertilizationCrop(), $eFertilizationYieldEmpty, $materials), 'sans aucun mobilisé, pas de résultat');

	}

	/**
	 * La fiabilité d'un rendement de référence (planche production-finir-fiabilite, Q1 A et Q2 B,
	 * le 03/10), sur la carotte de garde à 60 t/ha, dont l'exporté par hectare (72, 60, 330)
	 * reste sous le mobilisé (120, 60, 380), l'égalité du phosphore ne comptant pas pour un dépassement.
	 */
	public function testConfidence(): void {

		$eFertilizationSourceMinistry = new FertilizationSource(['id' => 1]);
		$eFertilizationSourceComifer = new FertilizationSource(['id' => 2]);

		[$eFertilizationCrop, $eFertilizationYield] = $this->getCarrot();

		$eFertilizationCrop['exportedSources'] = [
			['eFertilizationSource' => $eFertilizationSourceComifer, 'elements' => ['nitrogen', 'phosphate', 'potash']],
		];

		$this->assertEquals(['mobilized' => FertilizationLib::CONFIDENCE_MEDIUM, 'exported' => FertilizationLib::CONFIDENCE_MEDIUM, 'exceeded' => []], FertilizationLib::getConfidence($eFertilizationCrop, $eFertilizationYield), 'un seul document pour les trois éléments : une source unique est moyennement fiable, aucune valeur n\'est assez fiable');

		$eFertilizationCrop['exportedSources'] = [
			['eFertilizationSource' => $eFertilizationSourceMinistry, 'elements' => ['nitrogen']],
			['eFertilizationSource' => $eFertilizationSourceComifer, 'elements' => ['phosphate', 'potash']],
		];

		$this->assertEquals(['mobilized' => FertilizationLib::CONFIDENCE_MEDIUM, 'exported' => FertilizationLib::CONFIDENCE_MEDIUM, 'exceeded' => []], FertilizationLib::getConfidence($eFertilizationCrop, $eFertilizationYield), 'deux documents');

		$eFertilizationYield['yield'] = 90;

		$this->assertEquals(['mobilized' => FertilizationLib::CONFIDENCE_MEDIUM, 'exported' => FertilizationLib::CONFIDENCE_LOW, 'exceeded' => ['phosphate', 'potash']], FertilizationLib::getConfidence($eFertilizationCrop, $eFertilizationYield), 'à 90 t/ha, l\'exporté dépasse le mobilisé en P₂O₅ et K₂O : seul l\'exporté est peu fiable, le mobilisé ayant une source unique');

		$eFertilizationYield['yield'] = NULL;

		$this->assertEquals(['mobilized' => FertilizationLib::CONFIDENCE_MEDIUM, 'exported' => NULL, 'exceeded' => []], FertilizationLib::getConfidence($eFertilizationCrop, $eFertilizationYield), 'sans rendement, l\'exporté par hectare ne se calcule pas et n\'a pas de niveau');

		[$eFertilizationCrop, $eFertilizationYield] = $this->getLambsLettuce();

		$eFertilizationCrop['exportedSources'] = [
			['eFertilizationSource' => $eFertilizationSourceMinistry, 'elements' => ['nitrogen']],
		];

		$this->assertEquals(FertilizationLib::CONFIDENCE_MEDIUM, FertilizationLib::getConfidence($eFertilizationCrop, $eFertilizationYield)['exported'], 'un seul document, mais P₂O₅ et K₂O manquent : moyennement fiable au plus');

		$eFertilizationCrop['exportedSources'] = [];
		$eFertilizationCrop['exportedNitrogen'] = NULL;

		$this->assertEquals(NULL, FertilizationLib::getConfidence($eFertilizationCrop, $eFertilizationYield)['exported'], 'aucun exporté publié, aucune marque');

		$cFertilizationCrop = FertilizationCropLib::getList();

		$eFertilizationCropCarrot = $cFertilizationCrop->find(fn(FertilizationCrop $e) => $e['plant']['fqn'] === 'carotte')->first();
		$eFertilizationYieldStorage = $eFertilizationCropCarrot['cYield']->find(fn(FertilizationYield $e) => $e['name'] === 'Garde' and (float)$e['yield'] === 90.0)->first();

		$this->assertEquals(['phosphate', 'potash'], $eFertilizationYieldStorage['confidence']['exceeded'], 'le référentiel porte la fiabilité de chaque rendement : la carotte de garde à 90 t/ha exporte 90 et 495 pour 70 et 455 mobilisés');

	}

	/**
	 * Le référentiel tel que le pose `toolbox/configure/fertilizationReference` : la carotte et ses
	 * quatre rendements, et les matières des mesures ci-dessus avec les mêmes valeurs. Rouge sur une
	 * base où le script n'a pas tourné : le lancer (migration/2026-10-03-toolbox-fertilisation.md).
	 */
	public function testReference(): void {

		$cFertilizationCrop = FertilizationCropLib::getList();

		$eFertilizationCropCarrot = $cFertilizationCrop->find(fn(FertilizationCrop $e) => $e['plant']['fqn'] === 'carotte')->first();

		$this->assertTrue($eFertilizationCropCarrot->notEmpty(), 'aucune carotte dans le référentiel : lancer toolbox/configure/fertilizationReference');
		$this->assertEquals(4, $eFertilizationCropCarrot['cYield']->count());

		$eFertilizationYield = $eFertilizationCropCarrot['cYield']->find(fn(FertilizationYield $e) => $e['name'] === 'Garde' and (float)$e['yield'] === 60.0)->first();

		$this->assertEquals([120.0, 60.0, 380.0], [(float)$eFertilizationYield['nitrogen'], (float)$eFertilizationYield['phosphate'], (float)$eFertilizationYield['potash']]);
		$this->assertEquals([1.2, 1.0, 5.5], [(float)$eFertilizationCropCarrot['exportedNitrogen'], (float)$eFertilizationCropCarrot['exportedPhosphate'], (float)$eFertilizationCropCarrot['exportedPotash']]);

		$materials = [];

		foreach(FertilizationMaterialLib::getList() as $eFertilizationMaterial) {
			$materials[$eFertilizationMaterial['name']] = array_map(fn($value) => $value === NULL ? NULL : (float)$value, $eFertilizationMaterial->extracts(['nitrogen', 'phosphate', 'potash', 'availableNitrogen', 'availablePhosphate']));
		}

		$this->assertEquals(['nitrogen' => 6.7, 'phosphate' => 3.6, 'potash' => 10.8, 'availableNitrogen' => 0.25, 'availablePhosphate' => 0.55], $materials['Compost de fumier de bovins']);
		$this->assertEquals(['nitrogen' => 4.7, 'phosphate' => 2.3, 'potash' => 5.6, 'availableNitrogen' => 0.25, 'availablePhosphate' => 0.8], $materials['Fumier compact de bovins'], 'le Keq P publié par le RMT 2019 (fiche 22)');
		$this->assertEquals(['nitrogen' => 133.5, 'phosphate' => 8.1, 'potash' => 6.3, 'availableNitrogen' => 0.575, 'availablePhosphate' => NULL], $materials['Corne broyée'], 'l\'IGZ 2021, ligne « Hornspäne »');
		$this->assertFalse(array_key_exists('Sulfate de potasse magnésien', $materials), 'retiré du référentiel (03/10, « vire Sulfate de potasse magnésien »)');

	}

	/**
	 * Le champ de l'espèce ne propose que le référentiel : la saisie se compare sans accents ni casse,
	 * et un nom qui commence par elle passe devant un nom qui la contient.
	 */
	public function testQuery(): void {

		$cFertilizationCrop = FertilizationCropLib::getList();
		$names = fn(string $query) => array_values(FertilizationCropLib::getFromQuery($cFertilizationCrop, $query)->makeArray(fn($eFertilizationCrop) => $eFertilizationCrop['plant']['name']));

		$this->assertEquals($cFertilizationCrop->count(), count($names('')), 'une saisie vide rend tout le référentiel');
		$this->assertEquals([], $names('zzz'), 'une espèce hors du référentiel ne se trouve pas');
		$this->assertEquals(['Radis botte', 'Radis rave'], array_slice($names('ra'), 0, 2), 'les noms qui commencent par la saisie d\'abord');
		$this->assertTrue(in_array('Betterave', array_slice($names('ra'), 2)), 'puis ceux qui la contiennent');
		$this->assertTrue(str_starts_with($names('CELERI')[0], 'Céleri'), 'sans accents ni casse');

	}

	/**
	 * Chaque valeur du référentiel désigne le document qui la publie, et l'exporté en désigne un par
	 * élément : la carotte prend son azote au Ministère et ses oxydes au COMIFER, l'aubergine, que le
	 * COMIFER ne publie pas, ses oxydes à la LfL, le concombre, que ni l'un ni l'autre ne publie, n'a que
	 * l'azote du Ministère, le céleri à couper, sans azote publié, n'a que les oxydes de la LfL des
	 * condiments. Mêmes conditions que `testReference()`.
	 */
	public function testSources(): void {

		$cFertilizationCrop = FertilizationCropLib::getList();

		$getCrop = fn(string $fqn) => $cFertilizationCrop->find(fn(FertilizationCrop $e) => $e['plant']['fqn'] === $fqn)->first();

		$getSources = fn(FertilizationCrop $eFertilizationCrop) => array_map(
			fn(array $source) => [$source['eFertilizationSource']['name'], $source['elements']],
			$eFertilizationCrop['exportedSources']
		);

		$this->assertEquals([['Ministère 2026', ['nitrogen']], ['COMIFER 2009', ['phosphate', 'potash']]], $getSources($getCrop('carotte')));
		$this->assertEquals([['Ministère 2026', ['nitrogen']], ['LfL 2024', ['phosphate', 'potash']]], $getSources($getCrop('aubergine')));
		$this->assertEquals([0.5, 3.0], [(float)$getCrop('aubergine')['exportedPhosphate'], (float)$getCrop('aubergine')['exportedPotash']], 'la LfL publie en kg/dt, le référentiel en kg/t');
		$this->assertEquals([['Ministère 2026', ['nitrogen']]], $getSources($getCrop('concombre')));
		$this->assertEquals([['LfL 2024, condiments', ['phosphate', 'potash']]], $getSources($getCrop('celeri-couper')));

		$withoutUrl = [];

		foreach($cFertilizationCrop as $eFertilizationCrop) {
			foreach($eFertilizationCrop['cYield'] as $eFertilizationYield) {
				if(str_starts_with($eFertilizationYield['source']['url'], 'https://') === FALSE) {
					$withoutUrl[] = $eFertilizationCrop['plant']['fqn'];
				}
			}
		}

		foreach(FertilizationMaterialLib::getList() as $eFertilizationMaterial) {
			if(str_starts_with($eFertilizationMaterial['source']['url'], 'https://') === FALSE) {
				$withoutUrl[] = $eFertilizationMaterial['name'];
			}
		}

		$this->assertEquals([], $withoutUrl, 'valeurs dont le document n\'a pas d\'adresse : '.implode(', ', $withoutUrl));

	}

}
?>
