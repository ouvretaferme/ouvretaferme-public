<?php
namespace game;

/**
 * Chaque geste relit le joueur puis la case sous verrou, dans sa transaction : deux envois
 * simultanés du même geste ne passent ainsi qu'une fois. La cuisine verrouille aussi le joueur en
 * premier, avant les stocks : deux gestes qui prennent leurs verrous dans le même ordre ne
 * s'attendent jamais l'un l'autre.
 * Un refus survient avant toute écriture et clôt la transaction par `commit()` : il n'y a rien à
 * annuler, et un `rollBack()` défairait aussi la transaction de l'appelant.
 */
class ActionLib {

	public static function seed(Player $ePlayer, Tile $eTile, Growing $eGrowing): void {

		Player::model()->beginTransaction();

			$ePlayer = PlayerLib::getForUpdate($ePlayer);
			$eTile = TileLib::getForUpdate($eTile);

			if(
				$eTile->acceptSeed() === FALSE or
				(
					$eGrowing['fqn'] === 'pivoine' and
					$ePlayer->getHarvestTime(TileLib::getByBoard($ePlayer, $eTile['board'])) <= 1
				)
			) {
				Player::model()->commit();
				Tile::fail('growing.seed');
				return;
			}

			$time = GameSetting::TIME_SEEDLING;

			if(self::spendTime($ePlayer, $time) === FALSE) {
				return;
			}

			$eTile->merge([
				'growing' => $eGrowing,
				'watering' => ($eGrowing['bonusWatering'] === NULL ? NULL : 0),
				'seededAt' => new \Sql('NOW()'),
				'harvestedAt' => $eGrowing['harvest'] ? new \Sql('NOW() + INTERVAL '.$eGrowing['days'].' DAY') : NULL
			]);

			TileLib::update($eTile, ['growing', 'watering', 'seededAt', 'harvestedAt']);

			HistoryLib::create(new History([
				'user' => $ePlayer['user'],
				'time' => $time,
				'message' => HistoryUi::getMessage('seedling', [GrowingUi::getVignette($eGrowing, '1rem')])
			]));

		Player::model()->commit();

	}

	public static function water(Player $ePlayer, Tile $eTile): void {

		Player::model()->beginTransaction();

			$ePlayer = PlayerLib::getForUpdate($ePlayer);
			$eTile = TileLib::getForUpdate($eTile);

			if($eTile->acceptWater() === FALSE) {
				Player::model()->commit();
				Tile::fail('growing.water');
				return;
			}

			$time = GameSetting::TIME_WATERING;

			if(self::spendTime($ePlayer, $time) === FALSE) {
				return;
			}

			$eTile['watering'] = new \Sql('watering + 1');

			TileLib::update($eTile, ['watering']);

			HistoryLib::create(new History([
				'user' => $ePlayer['user'],
				'time' => $time,
				'message' => HistoryUi::getMessage('watering', [GrowingUi::getVignette($eTile['growing'], '1rem')])
			]));

		Player::model()->commit();

	}

	public static function weed(Player $ePlayer, Tile $eTile): void {

		Player::model()->beginTransaction();

			$ePlayer = PlayerLib::getForUpdate($ePlayer);
			$eTile = TileLib::getForUpdate($eTile);

			if($eTile->acceptWeed() === FALSE) {
				Player::model()->commit();
				Tile::fail('growing.weed');
				return;
			}

			$time = GameSetting::TIME_WEED;

			if(self::spendTime($ePlayer, $time) === FALSE) {
				return;
			}

			$eTile['harvestedAt'] = new \Sql('harvestedAt - INTERVAL '.GameSetting::BONUS_WEED.' DAY');

			TileLib::update($eTile, ['harvestedAt']);

			HistoryLib::create(new History([
				'user' => $ePlayer['user'],
				'time' => $time,
				'message' => HistoryUi::getMessage('weeding', [GrowingUi::getVignette($eTile['growing'], '1rem')])
			]));

		Player::model()->commit();

	}

	public static function harvest(Player $ePlayer, Tile $eTile): void {

		Player::model()->beginTransaction();

			$ePlayer = PlayerLib::getForUpdate($ePlayer);
			$eTile = TileLib::getForUpdate($eTile);

			if($eTile->acceptHarvest() === FALSE) {
				Player::model()->commit();
				Tile::fail('growing.harvest');
				return;
			}

			$cTile = TileLib::getByBoard($ePlayer, $eTile['board']);
			$time = $ePlayer->getHarvestTime($cTile);

			if(self::spendTime($ePlayer, $time) === FALSE) {
				return;
			}

			FoodLib::change($ePlayer, 'harvesting', $eTile['growing'], $eTile->getHarvest(TileLib::getTilesLuzerne($cTile)), $time);

			$eTile->merge([
				'growing' => new Growing(),
				'watering' => NULL,
				'seededAt' => NULL,
				'harvestedAt' => NULL
			]);

			TileLib::update($eTile, ['growing', 'watering', 'seededAt', 'harvestedAt']);

		Player::model()->commit();

	}

	public static function cook(Player $ePlayer, int $value): void {

		self::checkSoups($value);

		$cGrowing = Growing::model()
			->select('id')
			->whereHarvest('!=', NULL)
			->getCollection();

		$values = array_fill(0, $cGrowing->count(), -1 * $value);

		$cGrowing[] = new Growing();
		$values[] = $value;

		Player::model()->beginTransaction();

			$ePlayer = PlayerLib::getForUpdate($ePlayer);

			if(FoodLib::change($ePlayer, 'soup-cook', $cGrowing, $values) === FALSE) {
				Player::model()->commit();
				Food::fail('current.cook');
				return;
			}

		Player::model()->commit();

	}

	public static function eat(Player $ePlayer, int $value): void {

		self::checkSoups($value);

		Player::model()->beginTransaction();

			$ePlayer = PlayerLib::getForUpdate($ePlayer);

			if(FoodLib::change($ePlayer, 'soup-eat', new Growing(), -1 * $value) === FALSE) {
				Player::model()->commit();
				Food::fail('current.eat');
				return;
			}

			PlayerLib::changeTime($ePlayer, GameSetting::BONUS_SOUP * $value);

		Player::model()->commit();

	}

	// Une quantité négative retournerait le geste : cuisiner rendrait des légumes, manger des soupes.
	private static function checkSoups(int $value): void {

		if($value < 1) {
			throw new \NotExpectedAction('Invalid number of soups');
		}

	}

	// Le temps se dépense par une mise à jour conditionnelle qui n'écrit rien quand il manque : le
	// refus clôt donc la transaction sans rien annuler.
	private static function spendTime(Player $ePlayer, float $time): bool {

		if(PlayerLib::changeTime($ePlayer, -1 * $time)) {
			return TRUE;
		}

		Player::model()->commit();
		Player::fail('time.missing');

		return FALSE;

	}

}
?>
