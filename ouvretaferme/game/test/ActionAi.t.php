<?php
namespace game;

/**
 * Les gestes du plateau (semer, arroser, désherber, récolter) et de la cuisine (cuisiner, manger) :
 * ce qu'ils écrivent, ce qu'ils coûtent en temps, et ce qu'ils refusent sans rien écrire.
 */
class ActionAiTest extends GameFixtureAiTest {

	public function testSeed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');
		$eGrowing = $this->getGrowing('carotte');

		$fw = new \FailWatch();

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 1), $eGrowing);

		$this->assertTrue($fw->ok(), 'le semis passe');

		$eTile = $this->getTile($ePlayer, 1, 1);

		$this->assertTrue($eTile['growing']->is($eGrowing), 'la carotte est semée');
		$this->assertEquals(0, $eTile['watering'], 'sans arrosage');
		$this->assertEquals('2026-11-18 10:00:00', $eTile['harvestedAt'], 'mûre au bout de ses jours de culture');
		$this->assertEquals(GameSetting::TIME_SEEDLING, $this->getTime($ePlayer), 'le semis coûte son temps');
		$this->assertEquals(1, History::model()->whereUser($ePlayer['user'])->count(), 'le semis est à l\'historique');

		$fw = new \FailWatch();

		ActionLib::seed($ePlayer, $eTile, $this->getGrowing('betterave'));

		$this->assertTrue($fw->has('Tile::growing.seed'), 'une case occupée ne se sème pas');
		$this->assertTrue($this->getTile($ePlayer, 1, 1)['growing']->is($eGrowing), 'la carotte reste');
		$this->assertEquals(GameSetting::TIME_SEEDLING, $this->getTime($ePlayer), 'le refus ne coûte rien');

		$ePlayer['time'] = GameSetting::TIME_DAY;
		PlayerLib::update($ePlayer, ['time']);

		$fw = new \FailWatch();

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 2), $eGrowing);

		$this->assertTrue($fw->has('Player::time.missing'), 'sans temps, pas de semis');
		$this->assertTrue($this->getTile($ePlayer, 1, 2)['growing']->empty(), 'la case reste vide');

	}

	public function testSeedPivoineIsCapped(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');
		$eGrowing = $this->getGrowing('pivoine');

		// Six pivoines ramènent la récolte à une heure, la septième est refusée.
		$fw = new \FailWatch();

		foreach(range(1, 6) as $tile) {
			$this->rest($ePlayer);
			ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, $tile), $eGrowing);
		}

		$this->assertTrue($fw->ok(), 'six pivoines se sèment');
		$this->assertEquals(1.0, $ePlayer->getHarvestTime(TileLib::getByBoard($ePlayer, 1)), 'la récolte ne prend plus qu\'une heure');

		$this->rest($ePlayer);

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 7), $eGrowing);

		$this->assertTrue($fw->has('Tile::growing.seed'), 'la septième est refusée');
		$this->assertTrue($this->getTile($ePlayer, 1, 7)['growing']->empty(), 'la case reste vide');

		$fw = new \FailWatch();

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 2, 1), $eGrowing);

		$this->assertTrue($fw->ok(), 'le plafond se compte par plateau');

	}

	public function testWaterAndWeed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 1), $this->getGrowing('carotte'));
		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 2), $this->getGrowing('pois'));
		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 3), $this->getGrowing('luzerne'));

		$this->rest($ePlayer);

		$fw = new \FailWatch();

		ActionLib::water($ePlayer, $this->getTile($ePlayer, 1, 1));

		$this->assertTrue($fw->ok(), 'la carotte s\'arrose');
		$this->assertEquals(1, $this->getTile($ePlayer, 1, 1)['watering'], 'un arrosage');
		$this->assertEquals((float)GameSetting::TIME_WATERING, $this->getTime($ePlayer), 'l\'arrosage coûte son temps');

		ActionLib::water($ePlayer, $this->getTile($ePlayer, 1, 2));

		$this->assertTrue($fw->has('Tile::growing.water'), 'le pois ne s\'arrose pas');

		$fw = new \FailWatch();

		ActionLib::water($ePlayer, $this->getTile($ePlayer, 1, 4));

		$this->assertTrue($fw->has('Tile::growing.water'), 'une case vide ne s\'arrose pas');

		$fw = new \FailWatch();

		ActionLib::weed($ePlayer, $this->getTile($ePlayer, 1, 1));

		$this->assertTrue($fw->ok(), 'la carotte se désherbe');
		$this->assertEquals('2026-11-16 10:00:00', $this->getTile($ePlayer, 1, 1)['harvestedAt'], 'le désherbage avance la récolte');
		$this->assertEquals((float)(GameSetting::TIME_WATERING + GameSetting::TIME_WEED), $this->getTime($ePlayer), 'le désherbage coûte son temps');

		$this->rest($ePlayer);

		ActionLib::weed($ePlayer, $this->getTile($ePlayer, 1, 3));

		$this->assertTrue($fw->has('Tile::growing.weed'), 'la luzerne ne se désherbe pas');
		$this->assertEquals(0.0, $this->getTime($ePlayer), 'le refus ne coûte rien');

		$this->setClock('2026-11-16 10:00:00');

		$fw = new \FailWatch();

		ActionLib::water($ePlayer, $this->getTile($ePlayer, 1, 1));
		ActionLib::weed($ePlayer, $this->getTile($ePlayer, 1, 1));

		$this->assertTrue($fw->has('Tile::growing.water'), 'une culture mûre ne s\'arrose plus');
		$this->assertTrue($fw->has('Tile::growing.weed'), 'ni ne se désherbe');

	}

	public function testHarvest(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');
		$eGrowing = $this->getGrowing('carotte');

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 1), $eGrowing);
		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 2), $this->getGrowing('luzerne'));
		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 16), $this->getGrowing('luzerne'));
		ActionLib::water($ePlayer, $this->getTile($ePlayer, 1, 1));

		$this->rest($ePlayer);

		$fw = new \FailWatch();

		ActionLib::harvest($ePlayer, $this->getTile($ePlayer, 1, 1));

		$this->assertTrue($fw->has('Tile::growing.harvest'), 'une culture qui n\'est pas mûre ne se récolte pas');
		$this->assertEquals(0.0, $this->getTime($ePlayer), 'le refus ne coûte rien');

		$this->setClock('2026-11-18 10:00:00');

		$fw = new \FailWatch();

		ActionLib::harvest($ePlayer, $this->getTile($ePlayer, 1, 1));

		$this->assertTrue($fw->ok(), 'la carotte mûre se récolte');

		// La luzerne de la case 2 touche la case 1, celle de la case 16 non.
		$expected = $eGrowing['harvest'] + $eGrowing['bonusWatering'] + GameSetting::BONUS_LUZERNE;

		$this->assertEquals($expected, $this->getStock($ePlayer, $eGrowing), 'la récolte compte l\'arrosage et la luzerne voisine');
		$this->assertEquals((float)GameSetting::TIME_HARVESTING, $this->getTime($ePlayer), 'la récolte coûte son temps');
		$this->assertTrue($this->getTile($ePlayer, 1, 1)['growing']->empty(), 'la case est libérée');
		$this->assertEquals($expected, (int)Player::model()->whereId($ePlayer)->getValue('points'), 'les points suivent le stock');

		$fw = new \FailWatch();

		ActionLib::harvest($ePlayer, $this->getTile($ePlayer, 1, 1));

		$this->assertTrue($fw->has('Tile::growing.harvest'), 'une case vide ne se récolte pas');

	}

	public function testHarvestIsShortenedByPivoines(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 1), $this->getGrowing('mais'));
		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 2), $this->getGrowing('pivoine'));
		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 3), $this->getGrowing('pivoine'));

		$this->setClock('2026-11-09 10:00:00');
		$this->rest($ePlayer);

		ActionLib::harvest($ePlayer, $this->getTile($ePlayer, 1, 1));

		$this->assertEquals((float)(GameSetting::TIME_HARVESTING - 2 * GameSetting::BONUS_PIVOINE / 60), $this->getTime($ePlayer), 'chaque pivoine du plateau raccourcit la récolte');

	}

	public function testCookAndEat(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');
		$eGrowing = $this->getGrowing('carotte');
		$eGrowingSoup = new Growing();

		$cGrowing = GrowingLib::getAll()->find(fn($eGrowing) => $eGrowing['harvest'] !== NULL);

		foreach($cGrowing as $eGrowingHarvested) {
			FoodLib::change($ePlayer, 'harvesting', $eGrowingHarvested, 3);
		}

		$fw = new \FailWatch();

		ActionLib::cook($ePlayer, 2);

		$this->assertTrue($fw->ok(), 'la soupe se cuisine');
		$this->assertEquals(2, $this->getStock($ePlayer, $eGrowingSoup), 'deux soupes');
		$this->assertEquals(1, $this->getStock($ePlayer, $eGrowing), 'chaque légume en a donné deux');

		ActionLib::cook($ePlayer, 2);

		$this->assertTrue($fw->has('Food::current.cook'), 'il manque des légumes');
		$this->assertEquals(2, $this->getStock($ePlayer, $eGrowingSoup), 'le refus ne cuisine rien');
		$this->assertEquals(1, $this->getStock($ePlayer, $eGrowing), 'ni ne prend de légume');

		$fw = new \FailWatch();

		ActionLib::eat($ePlayer, 1);

		$this->assertTrue($fw->ok(), 'la soupe se mange');
		$this->assertEquals(1, $this->getStock($ePlayer, $eGrowingSoup), 'il reste une soupe');
		$this->assertEquals(-1.0 * GameSetting::BONUS_SOUP, $this->getTime($ePlayer), 'la soupe rend du temps');

		foreach([0, -5] as $value) {

			foreach(['cook', 'eat'] as $action) {

				try {
					ActionLib::$action($ePlayer, $value);
					$this->assertFail($action.'('.$value.') est refusé');
				} catch(\NotExpectedAction) {
				}

			}

		}

		$this->assertEquals(1, $this->getStock($ePlayer, $eGrowingSoup), 'une quantité nulle ou négative ne change aucune soupe');
		$this->assertEquals(1, $this->getStock($ePlayer, $eGrowing), 'ni aucun légume');

		ActionLib::eat($ePlayer, 2);

		$this->assertTrue($fw->has('Food::current.eat'), 'pas assez de soupe');
		$this->assertEquals(1, $this->getStock($ePlayer, $eGrowingSoup), 'le refus ne mange rien');
		$this->assertEquals(-1.0 * GameSetting::BONUS_SOUP, $this->getTime($ePlayer), 'ni ne rend de temps');

	}

	public function testBoardsOpenOverTime(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		$this->setClock('2025-11-29 12:00:00');

		$this->assertTrue($this->getTile($ePlayer, 1, 1)->acceptPlay(), 'le premier plateau est ouvert');
		$this->assertFalse($this->getTile($ePlayer, 2, 1)->acceptPlay(), 'le deuxième plateau n\'est pas encore ouvert');

		$this->setClock('2025-11-30 00:00:00');

		$this->assertTrue($this->getTile($ePlayer, 2, 1)->acceptPlay(), 'le deuxième plateau ouvre à sa date');
		$this->assertFalse($this->getTile($ePlayer, 3, 1)->acceptPlay(), 'le troisième pas encore');

		$this->setClock(GameSetting::END.' '.GameSetting::END_TIME.':00');

		$this->assertFalse($this->getTile($ePlayer, 1, 1)->acceptPlay(), 'la partie finie, plus rien ne se joue');

	}

}
?>
