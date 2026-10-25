<?php
namespace toolbox;

/**
 * Le calculateur des densités : la saisie passe par le `build()` d'une série et d'une culture, un
 * champ vidé rend un résultat vide sans erreur, et les chiffres sont ceux du plan de la culture.
 * Rien ne s'écrit en base, sauf l'inscription de l'outil à la première visite.
 *
 * Lancement : dev/test package=toolbox file=Density
 */
class DensityTest extends \Test {

	const BED = ['use' => 'bed', 'bedWidth' => '100', 'alleyWidth' => '50', 'rows' => '4', 'plantSpacing' => '30', 'lengthTarget' => '20'];

	private function calculate(array $input): array {

		$fw = new \FailWatch();

		[$eSeries, $eCultivation] = DensityLib::build($input);

		return [$fw->ok(), DensityLib::getPlan($eSeries, $eCultivation)];

	}

	/**
	 * Une planche de 100 cm et 50 de passe-pied, 4 rangs à 30 cm : 400 / 30 = 13,33 plants par mètre
	 * de planche, 8,89 au m² passe-pieds compris, 266,7 sur 20 mL.
	 */
	public function testBedFigures(): void {

		[$ok, $plan] = $this->calculate(self::BED);

		$this->assertTrue($ok);
		$this->assertEquals(13.33, round($plan['perMeter'], 2));
		$this->assertEquals(8.89, round($plan['density'], 2));
		$this->assertEquals(266.67, round($plan['total'], 2));
		$this->assertFalse($plan['stagger'], 'une case décochée, donc absente du POST, doit valoir FALSE');

	}

	/**
	 * Les deux vues se dessinent depuis la saisie : sur 3 m, 4 rangs de 10 plants en perspective,
	 * l'étalon posé sur le premier mètre, sans cote sur le rang, la largeur de la planche cotée à
	 * son bout, et en coupe la chaîne passe-pied, rangs, passe-pied. En quinconce, le rang de
	 * l'étalon garde son plant au mètre.
	 */
	public function testViews(): void {

		[, $plan] = $this->calculate(self::BED);

		$length = \sequence\DensityPlanAiUi::getLength([$plan]);
		$this->assertEquals(300, $length);

		$uiDensityView = new \sequence\DensityViewAiUi();

		$isometric = $uiDensityView->getIsometric($plan, $length);
		$this->assertEquals(40, substr_count($isometric, 'class="density-view-stem"'), '4 rangs de 10 plants sur 3 m');

		$cut = $uiDensityView->getCut($plan, $length);
		preg_match_all('/<text class="density-view-cote(?: density-view-cote-alley)?" [^>]*>([^<]*)<\/text>/', $cut, $matches);
		$this->assertEquals(['25', '13', '25', '25', '25', '13', '25', 'passe-pied', 'passe-pied'], $matches[1]);

		[, $plan] = $this->calculate(self::BED + ['stagger' => '1']);
		$this->assertEquals(40, substr_count($uiDensityView->getIsometric($plan, $length), 'class="density-view-stem"'), 'la quinconce décale sans changer le nombre');

		// Une surface libre à 30 cm entre rangs : trois écarts, le nombre entier le plus proche
		// d'un mètre, une bande sans bord ni largeur cotée.
		[, $plan] = $this->calculate(['use' => 'block', 'bedWidth' => '100', 'rows' => '4', 'plantSpacing' => '25', 'rowSpacing' => '30', 'areaTarget' => '100']);
		$geometry = \sequence\DensityPlanAiUi::getGeometry($plan, 300);
		$this->assertEquals(90.0, $geometry['total']);
		$this->assertEquals([15.0, 45.0, 75.0], $geometry['positions']);

		$isometric = $uiDensityView->getIsometric($plan, 300);
		$this->assertEquals(33, substr_count($isometric, 'class="density-view-stem"'), '3 rangs de 11 plants sur 3 m, aucun collé aux bouts');

	}

	public function testStaggerKeepsTheDensity(): void {

		[, $straight] = $this->calculate(self::BED);
		[, $stagger] = $this->calculate(self::BED + ['stagger' => '1']);

		$this->assertTrue($stagger['stagger']);
		$this->assertEquals($straight['density'], $stagger['density']);

	}

	/** Surface libre, rangs à 40 cm, plants à 25 : 10 plants au m², 1 000 sur 100 m². */
	public function testBlockFigures(): void {

		[$ok, $plan] = $this->calculate(['use' => 'block', 'bedWidth' => '100', 'rows' => '4', 'plantSpacing' => '25', 'rowSpacing' => '40', 'areaTarget' => '100']);

		$this->assertTrue($ok);
		$this->assertEquals(10.0, round($plan['density'], 2));
		$this->assertEquals(1000.0, round($plan['total'], 2));
		$this->assertEquals(NULL, $plan['bedWidth'], 'la largeur de planche d\'une surface libre est gardée');

	}

	/** Un champ vidé en cours de saisie n'est pas une erreur : pas de fail, pas de plan. */
	public function testEmptyFieldIsIncomplete(): void {

		[$ok, $plan] = $this->calculate(['bedWidth' => ''] + self::BED);

		$this->assertTrue($ok, 'une largeur vidée lève une erreur sous les doigts');
		$this->assertEquals([], $plan);

		[$ok, $plan] = $this->calculate(['lengthTarget' => ''] + self::BED);

		$this->assertTrue($ok);
		$this->assertEquals(NULL, $plan['total'], 'sans longueur, il n\'y a pas de total à donner');

	}

	/**
	 * La première visite fait du calculateur un outil de la structure, une seule fois, et la structure
	 * en mémoire le sait déjà : c'est ce qui donne sa roue à l'en-tête de cette visite.
	 */
	public function testOpenMarksTheToolAsUsed(): void {

		$field = \structure\Feature::model()->field('hasToolbox');

		$structureId = \structure\Feature::model()
			->where('JSON_CONTAINS('.$field.', JSON_QUOTE('.\structure\Feature::model()->format(Favorite::DENSITY).')) = 0')
			->sort(['id' => SORT_ASC])
			->getValue('structure');

		$eStructure = \structure\StructureLib::getById($structureId);

		$hasToolbox = fn() => \structure\Feature::model()
			->whereStructure($eStructure)
			->getValue('hasToolbox');

		$before = $hasToolbox();

		DensityLib::open($eStructure);
		$this->assertEquals([...$before, Favorite::DENSITY], $hasToolbox(), 'la première visite n\'inscrit pas le calculateur');
		$this->assertTrue(in_array(Favorite::DENSITY, $eStructure->conf(\structure\Feature::class)['hasToolbox']), 'la structure en mémoire ignore l\'inscription, l\'en-tête n\'aurait pas de roue');

		DensityLib::open(\structure\StructureLib::getById($structureId));
		$this->assertEquals([...$before, Favorite::DENSITY], $hasToolbox(), 'la visite suivante inscrit le calculateur une seconde fois');

		// La structure retrouve son état : d'autres décors lisent `hasToolbox` sur les mêmes structures.
		\structure\FeatureLib::removeToolbox($eStructure, Favorite::DENSITY);
		$this->assertEquals($before, $hasToolbox());

	}

	/** La planche de la ferme ouvre le calculateur quand elle est déclarée, longueur comprise. */
	public function testNewReadsTheStructureBeds(): void {

		foreach([TRUE, FALSE] as $declared) {

			$structureId = \map\Configuration::model()
				->where($declared ? 'defaultBedLength IS NOT NULL' : 'defaultBedLength IS NULL')
				->sort(['id' => SORT_ASC])
				->getValue('structure');

			$eStructure = \structure\StructureLib::getById($structureId);

			[$eSeries] = DensityLib::getNew($eStructure);

			$this->assertEquals($eStructure->conf(\map\Configuration::class)['defaultBedLength'] ?? 20, $eSeries['lengthTarget']);
			$this->assertEquals($declared, $eSeries['lengthTarget'] === $eStructure->conf(\map\Configuration::class)['defaultBedLength'], 'la longueur de planche de la ferme n\'ouvre pas le calculateur');

		}

	}

	/** Une valeur hors des bornes de la série est refusée comme dans la série. */
	public function testOutOfRangeIsRefused(): void {

		[$ok] = $this->calculate(['plantSpacing' => '0'] + self::BED);

		$this->assertFalse($ok);

	}

}
?>
