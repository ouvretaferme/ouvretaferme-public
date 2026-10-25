<?php
namespace toolbox;

/**
 * Les pluviomètres : un seul actif par lieu, la ferme comprise ; supprimé, il garde ses relevés en
 * base mais disparaît de toute lecture, et en reposer un au même endroit repart de zéro (plan Gauge
 * validé par M-Prod le 23/09).
 *
 * Isolation : une transaction par méthode, annulée en tearDown(), sur une structure sans pluviomètre
 * (`RainTest::getZone()`).
 *
 * Lancement : dev/test package=toolbox file=Gauge
 */
class GaugeTest extends \Test {

	public function setUp(): void {
		Gauge::model()->beginTransaction();
	}

	public function tearDown(): void {

		Gauge::model()->rollBack();

		// Le rollback ne défait pas la mémoïsation de `conf()`, qui garde la pluviométrie inscrite
		// dans la transaction : la méthode suivante croirait l'outil déjà là et ne l'inscrirait pas.
		\structure\FeatureLib::forgetAll();

	}

	private function builds(Gauge $eGauge, array $properties, array $input): array {

		$fw = new \FailWatch();
		$eGauge->build($properties, $input);

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	private function delete(Gauge $eGauge): void {

		$eGauge['status'] = Gauge::INACTIVE;
		GaugeLib::update($eGauge, ['status']);

	}

	public function testPlaceHoldsOneActiveGauge(): void {

		$eZone = RainTest::getZone();
		$eStructure = $eZone['structure'];

		$eZoneOther = \map\Zone::model()
			->select('id')
			->whereStructure('!=', $eStructure)
			->get();

		$eGauge = new Gauge(['structure' => $eStructure]);

		$this->assertEquals([], $this->builds($eGauge, ['zone'], ['zone' => $eZone['id']]), 'une parcelle libre');
		$this->assertEquals([], $this->builds($eGauge, ['zone'], ['zone' => '']), 'la ferme, libre');
		$this->assertTrue($eGauge['zone']->empty(), 'la ferme n\'a pas de zone');
		$this->assertEquals(['Gauge::zone.chosen'], $this->builds($eGauge, ['zone'], []), 'un lieu laissé sur l\'invite n\'est pas la ferme');
		$this->assertEquals(['Gauge::zone.owned'], $this->builds($eGauge, ['zone'], ['zone' => $eZoneOther['id']]), 'la parcelle d\'une autre structure');

		RainTest::place($eStructure, $eZone);
		RainTest::place($eStructure);

		$this->assertEquals(['Gauge::zone.placed'], $this->builds($eGauge, ['zone'], ['zone' => $eZone['id']]), 'la parcelle porte déjà un pluviomètre');
		$this->assertEquals(['Gauge::zone.placed'], $this->builds($eGauge, ['zone'], ['zone' => '']), 'la ferme aussi');

	}

	/**
	 * « Nouveau pluviomètre » ne propose que les lieux sans pluviomètre actif : la ferme en tête tant
	 * qu'elle n'en a pas, puis les parcelles de la saison.
	 */
	public function testFreePlacesExcludeGauges(): void {

		$eZone = RainTest::getZone();
		$eStructure = $eZone['structure'];
		$cZoneSeason = new \Collection([$eZone['id'] => $eZone]);

		$cZoneFree = GaugeLib::getFree($eStructure, $cZoneSeason);
		$this->assertEquals([0, $eZone['id']], $cZoneFree->getKeys(), 'sans pluviomètre, la ferme puis la parcelle');
		$this->assertFalse($cZoneFree->first()->exists(), 'la ferme n\'a pas d\'id');

		$eGauge = RainTest::place($eStructure, $eZone);
		$this->assertEquals([0], GaugeLib::getFree($eStructure, $cZoneSeason)->getKeys(), 'la parcelle équipée n\'est plus libre, même sans relevé');

		RainTest::place($eStructure);
		$this->assertTrue(GaugeLib::getFree($eStructure, $cZoneSeason)->empty(), 'plus aucun lieu libre');

		$this->delete($eGauge);
		$this->assertEquals([$eZone['id']], GaugeLib::getFree($eStructure, $cZoneSeason)->getKeys(), 'la parcelle redevient libre à la suppression');

	}

	/**
	 * Changer de lieu (retour du 23/09) : le lieu du pluviomètre lui reste permis, celui
	 * d'un autre non, et ses relevés le suivent.
	 */
	public function testMoveKeepsReadings(): void {

		$eZone = RainTest::getZone();
		$eStructure = $eZone['structure'];
		$cZoneSeason = new \Collection([$eZone['id'] => $eZone]);

		$eGauge = RainTest::place($eStructure);
		RainLib::create(new Rain(['structure' => $eStructure, 'gauge' => $eGauge, 'date' => \Clock::date(), 'rain' => 4]));

		$this->assertEquals([0, $eZone['id']], GaugeLib::getFree($eStructure, $cZoneSeason, $eGauge)->getKeys(), 'son propre lieu reste proposé');

		$eGaugeUpdate = GaugeLib::getById($eGauge['id']);
		$this->assertEquals([], $this->buildsUpdate($eGaugeUpdate, ['zone' => '']), 'rester à la ferme');
		$this->assertEquals([], $this->buildsUpdate($eGaugeUpdate, ['zone' => $eZone['id']]), 'passer sur la parcelle libre');
		GaugeLib::update($eGaugeUpdate, ['zone']);

		$cGauge = RainLib::getGauges($eStructure);
		$this->assertEquals($eZone['id'], $cGauge->first()['zone']['id'], 'le pluviomètre est sur la parcelle');
		$this->assertEquals(4.0, $cGauge->first()['cumulative'], 'ses relevés l\'ont suivi');

		RainTest::place($eStructure);
		$this->assertEquals(['Gauge::zone.placed'], $this->buildsUpdate(GaugeLib::getById($eGauge['id']), ['zone' => '']), 'la ferme porte maintenant un autre pluviomètre');

	}

	private function buildsUpdate(Gauge $eGauge, array $input): array {

		$fw = new \FailWatch();
		$eGauge->build(['zone'], $input, new \PropertyList('update'));

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	public function testDeletionHidesReadingsWithoutErasingThem(): void {

		$eZone = RainTest::getZone();
		$eGauge = RainTest::place($eZone['structure'], $eZone);

		RainLib::create(new Rain(['structure' => $eZone['structure'], 'gauge' => $eGauge, 'date' => \Clock::date(), 'rain' => 7]));

		$this->delete($eGauge);

		$this->assertTrue(RainLib::getGauges($eZone['structure'])->empty(), 'le pluviomètre supprimé n\'est plus lu');
		$this->assertEquals(1, Rain::model()->whereGauge($eGauge)->count(), 'ses relevés restent en base');

		$eGaugeNew = RainTest::place($eZone['structure'], $eZone);
		$cGauge = RainLib::getGauges($eZone['structure']);

		$this->assertEquals([$eGaugeNew['id']], $cGauge->getIds(), 'reposé au même endroit, c\'est un nouveau pluviomètre');
		$this->assertEquals(0.0, $cGauge->first()['cumulative'], 'qui repart de zéro');
		$this->assertEquals(NULL, $cGauge->first()['lastDate']);

	}

	/**
	 * Un pluviomètre supprimé ne revient pas : le seul statut accepté est la suppression, et un
	 * pluviomètre déjà supprimé ne se modifie plus.
	 */
	public function testDeletionIsFinal(): void {

		$eZone = RainTest::getZone();
		$eGauge = RainTest::place($eZone['structure'], $eZone);

		$eGauge['status'] = Gauge::ACTIVE;
		$this->assertTrue($eGauge->acceptUpdate());
		$this->assertEquals(['Gauge::status.deleted'], $this->builds($eGauge, ['status'], ['status' => Gauge::ACTIVE]), 'rendre actif n\'est pas un geste');

		$this->delete($eGauge);
		$this->assertFalse(GaugeLib::getById($eGauge['id'])->acceptUpdate(), 'supprimé, il ne se modifie plus');

	}

	public function testFirstGaugeMarksTheToolAsUsed(): void {

		$eZone = RainTest::getZone();

		$hasToolbox = fn() => \structure\Feature::model()
			->whereStructure($eZone['structure'])
			->getValue('hasToolbox');

		$this->assertEquals([], $hasToolbox(), 'aucun outil avant le premier pluviomètre');

		$eGauge = RainTest::place($eZone['structure'], $eZone);
		$this->assertEquals([Favorite::RAIN], $hasToolbox(), 'le premier pluviomètre inscrit la pluviométrie, avant tout relevé');

		RainTest::place(\structure\StructureLib::getById($eZone['structure']['id']));
		$this->assertEquals([Favorite::RAIN], $hasToolbox(), 'les suivants ne l\'inscrivent pas une seconde fois');

		$this->delete($eGauge);
		$this->assertEquals([Favorite::RAIN], $hasToolbox(), 'la suppression laisse l\'outil parmi ceux de la structure');

	}

}
?>
