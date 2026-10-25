<?php
namespace toolbox;

/**
 * Les relevés de pluviomètre : un jour porte un total, un second relevé du même jour l'augmente
 * (arbitrage Q7 B de la planche pf-l8-v2), et l'écran compare le cumul de l'année à celui de
 * l'année d'avant arrêté au même jour.
 *
 * Isolation : une transaction par méthode, annulée en tearDown(). Les pluviomètres se posent sur
 * une structure qui n'en porte aucun, pour que `getGauges()` ne lise que ceux du test.
 *
 * Lancement : dev/test package=toolbox file=Rain
 */
class RainTest extends \Test {

	public function setUp(): void {
		Rain::model()->beginTransaction();
	}

	public function tearDown(): void {
		Rain::model()->rollBack();
	}

	/**
	 * Une zone d'une structure sans pluviomètre, actif ou non.
	 */
	public static function getZone(): \map\Zone {

		$structures = Gauge::model()
			->select('structure')
			->getCollection()
			->getColumnCollection('structure')
			->getIds();

		$eZone = \map\Zone::model()
			->select(['id', 'structure', 'name'])
			->whereStructure('NOT IN', $structures, if: $structures)
			->sort(['id' => SORT_ASC])
			->get();

		$eZone['structure'] = \structure\StructureLib::getById($eZone['structure']['id']);

		return $eZone;

	}

	/**
	 * Un pluviomètre posé sur la zone, ou sur la ferme quand la zone est vide.
	 */
	public static function place(\structure\Structure $eStructure, \map\Zone $eZone = new \map\Zone()): Gauge {

		$eGauge = new Gauge([
			'structure' => $eStructure,
			'zone' => $eZone
		]);

		GaugeLib::create($eGauge);

		return $eGauge;

	}

	private function create(Gauge $eGauge, string $date, float $rain): void {

		RainLib::create(new Rain([
			'structure' => $eGauge['structure'],
			'gauge' => $eGauge,
			'date' => $date,
			'rain' => $rain
		]));

	}

	private function builds(Rain $eRain, array $input): array {

		$fw = new \FailWatch();
		$eRain->build(['gauge', 'date', 'rain'], $input);

		return array_map(fn($fail) => $fail[1], $fw->get());

	}

	public function testSecondReadingAddsToTheDay(): void {

		$eZone = self::getZone();
		$eGauge = self::place($eZone['structure'], $eZone);

		$this->create($eGauge, '2026-09-01', 5);
		$this->create($eGauge, '2026-09-01', 3.2);
		$this->create($eGauge, '2026-09-02', 1);

		$this->assertEquals(2, Rain::model()->whereGauge($eGauge)->count(), 'une ligne par jour');
		$this->assertEquals(8.2, RainLib::getDay($eGauge, '2026-09-01'), 'le second relevé s\'ajoute au premier');
		$this->assertEquals(1.0, RainLib::getDay($eGauge, '2026-09-02'));
		$this->assertEquals(0.0, RainLib::getDay($eGauge, '2026-09-03'), 'un jour sans relevé rend zéro');

	}

	/**
	 * La ferme a désormais son pluviomètre, et donc l'unicité (gauge, date) : son second relevé du
	 * jour augmente la ligne du premier comme sur une parcelle.
	 */
	public function testSecondReadingOnTheFarmAddsToTheDay(): void {

		$eZone = self::getZone();
		$eGaugeFarm = self::place($eZone['structure']);
		$eGaugeZone = self::place($eZone['structure'], $eZone);

		$this->create($eGaugeFarm, '2026-09-01', 5);
		$this->create($eGaugeFarm, '2026-09-01', 3.2);

		$this->assertEquals(1, Rain::model()->whereGauge($eGaugeFarm)->count(), 'une ligne par jour sur la ferme aussi');
		$this->assertEquals(8.2, RainLib::getDay($eGaugeFarm, '2026-09-01'), 'le second relevé s\'ajoute au premier');
		$this->assertEquals(0.0, RainLib::getDay($eGaugeZone, '2026-09-01'), 'la pluie de la ferme n\'est pas celle de la parcelle');

	}

	public function testGaugeMustBeActiveAndOwned(): void {

		$eZone = self::getZone();
		$eGauge = self::place($eZone['structure'], $eZone);

		$eGaugeOther = Gauge::model()
			->select('id')
			->whereStructure('!=', $eZone['structure'])
			->get();

		$eRain = new Rain(['structure' => $eZone['structure']]);

		$this->assertEquals(['Rain::gauge.owned'], $this->builds($eRain, ['gauge' => $eGaugeOther['id'], 'date' => '2026-09-01', 'rain' => '4']), 'le pluviomètre d\'une autre structure est refusé');
		$this->assertEquals(['Rain::gauge.check'], $this->builds($eRain, ['date' => '2026-09-01', 'rain' => '4']), 'un relevé a un pluviomètre');
		$this->assertEquals([], $this->builds($eRain, ['gauge' => $eGauge['id'], 'date' => '2026-09-01', 'rain' => '4']), 'le sien passe');

		$eGauge['status'] = Gauge::INACTIVE;
		GaugeLib::update($eGauge, ['status']);

		$this->assertEquals(['Rain::gauge.owned'], $this->builds($eRain, ['gauge' => $eGauge['id'], 'date' => '2026-09-01', 'rain' => '4']), 'un pluviomètre supprimé ne reçoit plus de relevé');

	}

	public function testFarmGaugeComesFirst(): void {

		$eZone = self::getZone();
		$eGaugeZone = self::place($eZone['structure'], $eZone);
		$eGaugeFarm = self::place($eZone['structure']);

		$this->create($eGaugeFarm, date('Y-m-d', strtotime(\Clock::date().' -5 days')), 4);
		$this->create($eGaugeZone, \Clock::date(), 2);

		$cGauge = RainLib::getGauges($eZone['structure']);

		$this->assertEquals([$eGaugeFarm['id'], $eGaugeZone['id']], $cGauge->getIds(), 'la ferme en tête, bien que relevée plus tôt et posée après');
		$this->assertEquals(4.0, $cGauge->first()['cumulativeRecent']);
		$this->assertEquals(2.0, $cGauge->last()['cumulativeRecent'], 'chacun ses relevés');

	}

	/**
	 * Un pluviomètre existe avant son premier relevé : il est listé, sans date, après ceux qui en ont.
	 */
	public function testGaugeWithoutReadingIsListed(): void {

		$eZone = self::getZone();
		$eGaugeEmpty = self::place($eZone['structure'], $eZone);

		$cGauge = RainLib::getGauges($eZone['structure']);

		$this->assertEquals([$eGaugeEmpty['id']], $cGauge->getIds(), 'posé, sans relevé');
		$this->assertEquals(NULL, $cGauge->first()['lastDate']);
		$this->assertEquals(0.0, $cGauge->first()['cumulative']);

		$eZoneOther = \map\Zone::model()
			->select('id')
			->whereStructure($eZone['structure'])
			->whereId('!=', $eZone['id'])
			->get();

		if($eZoneOther->notEmpty()) {

			$eGaugeRead = self::place($eZone['structure'], $eZoneOther);
			$this->create($eGaugeRead, \Clock::date(), 3);

			$this->assertEquals([$eGaugeRead['id'], $eGaugeEmpty['id']], RainLib::getGauges($eZone['structure'])->getIds(), 'le relevé passe devant celui qui attend');

		}

	}

	public function testFutureDayIsRefused(): void {

		$eZone = self::getZone();
		$eGauge = self::place($eZone['structure'], $eZone);

		$eRain = new Rain(['structure' => $eZone['structure']]);

		$tomorrow = date('Y-m-d', strtotime(\Clock::date().' + 1 day'));

		$this->assertEquals(['Rain::date.future'], $this->builds($eRain, ['gauge' => $eGauge['id'], 'date' => $tomorrow, 'rain' => '4']), 'demain n\'a pas encore de pluie');
		$this->assertEquals([], $this->builds($eRain, ['gauge' => $eGauge['id'], 'date' => \Clock::date(), 'rain' => '4']), 'aujourd\'hui passe');

	}

	/**
	 * Un relevé négatif retire des mm à un jour (retour du 23/09) : jamais plus que le jour
	 * n'en porte, et le jour ramené à zéro disparaît. Le total d'un jour, que la roue crantée modifie,
	 * reste positif.
	 */
	public function testNegativeReadingRemovesFromTheDay(): void {

		$eZone = self::getZone();
		$eGauge = self::place($eZone['structure'], $eZone);

		$this->create($eGauge, '2026-09-01', 5);

		$eRain = new Rain(['structure' => $eZone['structure']]);

		$this->assertEquals(['Rain::rain.exceeds'], $this->builds($eRain, ['gauge' => $eGauge['id'], 'date' => '2026-09-01', 'rain' => '-5.1']), 'plus que le jour n\'en porte');
		$this->assertEquals(['Rain::rain.exceeds'], $this->builds($eRain, ['gauge' => $eGauge['id'], 'date' => '2026-09-02', 'rain' => '-1']), 'un jour sans pluie n\'a rien à retirer');
		$this->assertEquals(['Rain::rain.check'], $this->builds($eRain, ['gauge' => $eGauge['id'], 'date' => '2026-09-01', 'rain' => '0']), 'zéro ne dit rien');
		$this->assertEquals(['Rain::rain.check'], $this->builds($eRain, ['gauge' => $eGauge['id'], 'date' => '2026-09-01', 'rain' => '-100000']), 'la borne du schéma vaut aussi pour un retrait');
		$this->assertEquals(['Rain::gauge.check'], $this->builds($eRain, ['date' => '2026-09-01', 'rain' => '-1']), 'sans pluviomètre, seul le pluviomètre est signalé');
		$this->assertEquals([], $this->builds($eRain, ['gauge' => $eGauge['id'], 'date' => '2026-09-01', 'rain' => '-5']), 'tout ce que le jour porte');

		$this->create($eGauge, '2026-09-01', -1.5);
		$this->assertEquals(3.5, RainLib::getDay($eGauge, '2026-09-01'), 'le retrait diminue le total');

		$this->create($eGauge, '2026-09-01', -3.5);
		$this->assertEquals(0, Rain::model()->whereGauge($eGauge)->count(), 'le jour ramené à zéro est supprimé');

		$this->create($eGauge, '2026-09-03', 2);

		$eRainDay = RainLib::getById(Rain::model()->select('id')->whereGauge($eGauge)->get()['id'], ['id', 'structure', 'rain']);

		$fw = new \FailWatch();
		$eRainDay->build(['rain'], ['rain' => '-1']);

		$this->assertEquals(['Rain::rain.check'], array_map(fn($fail) => $fail[1], $fw->get()), 'le total d\'un jour reste positif');

	}

	/**
	 * Le cumul de l'année d'avant s'arrête au même jour : un relevé postérieur à ce jour-là compte
	 * dans son mois, pas dans la comparaison. Un relevé d'il y a deux ans compte dans le graphique
	 * des années, pas dans la liste des relevés.
	 */
	public function testGaugesCompareToTheSameDayLastYear(): void {

		$eZone = self::getZone();
		$eGauge = self::place($eZone['structure'], $eZone);

		$year = \Clock::year();
		$previous = $year - 1;

		$this->create($eGauge, $year.'-01-01', 10);
		$this->create($eGauge, \Clock::date(), 2.5);
		$this->create($eGauge, $previous.'-01-01', 4);
		$this->create($eGauge, $previous.'-12-31', 7);
		$this->create($eGauge, ($year - 2).'-06-15', 100);

		$cGauge = RainLib::getGauges($eZone['structure']);

		$this->assertEquals([$eGauge['id']], $cGauge->getIds());

		$eGaugeRead = $cGauge->first();

		$this->assertEquals(\Clock::date(), $eGaugeRead['lastDate']);
		$this->assertEquals(2.5, $eGaugeRead['lastRain'], 'les mm du dernier relevé');
		$this->assertEquals(12.5, $eGaugeRead['cumulative'], 'depuis le 1er janvier');
		$this->assertEquals(4.0, $eGaugeRead['cumulativePrevious'], 'l\'année d\'avant, arrêtée au même jour');
		$this->assertEquals([$year, $previous, $year - 2], array_keys($eGaugeRead['years']), 'toutes les années relevées, la plus récente en tête');
		$this->assertEquals(7.0, $eGaugeRead['years'][$previous]['months'][12], 'le 31 décembre compte dans son mois');
		$this->assertEquals(10.0 + ((int)substr(\Clock::date(), 5, 2) === 1 ? 2.5 : 0), $eGaugeRead['years'][$year]['months'][1]);
		$this->assertEquals(100.0, $eGaugeRead['years'][$year - 2]['months'][6], 'une année plus ancienne se compare aussi');
		$this->assertEquals(['days' => 2, 'total' => 11.0], ['days' => $eGaugeRead['years'][$previous]['days'], 'total' => $eGaugeRead['years'][$previous]['total']], 'les jours relevés et le total de l\'année');
		$this->assertEquals(4, $eGaugeRead['cRain']->count(), 'les relevés des deux années, pas ceux d\'avant');
		$this->assertEquals(\Clock::date(), $eGaugeRead['cRain']->first()['date'], 'le plus récent en tête');

	}

	/**
	 * La carte de « Vos outils » nomme le cumul par son année et la pluie récente par « 30 jours »
	 * (retour du 02/10, « nouveaux labels »), sur la carte d'un pluviomètre seul comme en
	 * tête du tableau de plusieurs. L'année est celle sur laquelle `getGauges()` somme, d'où l'horloge
	 * figée sur une autre année que celle du jour.
	 */
	public function testToolCardNamesItsFigures(): void {

		$this->setClock('2031-03-10 10:00:00');

		$eZone = self::getZone();
		$eStructure = $eZone['structure'];
		$eGauge = self::place($eStructure, $eZone);

		$this->create($eGauge, '2031-03-09', 2.5);
		$this->create($eGauge, '2030-11-02', 40);

		$render = function() use($eStructure): string {
			$eStructureRead = \structure\StructureLib::getById($eStructure['id']);
			return new ToolUi()->getList($eStructureRead, new \Collection(), RainLib::getGauges($eStructureRead), new \Collection(), TreatmentLib::getSummary($eStructureRead), EquipmentLib::getSummary($eStructureRead));
		};

		$this->assertEquals(2031, RainLib::getGauges($eStructure)->first()['cumulativeYear'], 'l\'année du cumul est celle de l\'horloge');

		$html = $render();

		$this->assertTrue(str_contains($html, '<span class="util-deck-key">2031</span><span class="util-deck-value">'.RainUi::getMm(2.5).'</span>'), 'le cumul porte son année, et ne compte pas la pluie de l\'année d\'avant');

	}

	public function testGaugesSumTheLastThirtyDays(): void {

		$eZone = self::getZone();
		$eGauge = self::place($eZone['structure'], $eZone);

		$this->create($eGauge, \Clock::date(), 2.5);
		$this->create($eGauge, date('Y-m-d', strtotime(\Clock::date().' -10 days')), 3);
		$this->create($eGauge, date('Y-m-d', strtotime(\Clock::date().' -29 days')), 1);
		$this->create($eGauge, date('Y-m-d', strtotime(\Clock::date().' -30 days')), 8);

		$cGauge = RainLib::getGauges($eZone['structure']);

		$this->assertEquals(6.5, $cGauge->first()['cumulativeRecent'], 'trente jours, aujourd\'hui compris, le trente-et-unième exclu');

	}

}
?>
