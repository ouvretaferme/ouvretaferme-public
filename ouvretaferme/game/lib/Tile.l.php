<?php
namespace game;

class TileLib extends TileCrud {

	public static function createByPlayer(Player $ePlayer): void {

		for($board = 1; $board <= GameSetting::BOARDS; $board++) {
			self::createBoard($ePlayer, $board);
		}

	}

	public static function createBoard(Player $ePlayer, int $board): void {

		for($tile = 1; $tile <= 16; $tile++) {

			$eTile = new Tile([
				'user' => $ePlayer['user'],
				'board' => $board,
				'tile' => $tile
			]);

			self::create($eTile);

		}

	}

	public static function getByBoard(Player $ePlayer, int $board): \Collection {

		return Tile::model()
			->select(Tile::getSelection())
			->whereUser($ePlayer['user'])
			->whereBoard($board)
			->getCollection(index: 'tile');

	}

	/**
	 * Toutes les cases du joueur, par plateau puis par case : la cour montre les quatre plateaux.
	 */
	public static function getByPlayer(Player $ePlayer): \Collection {

		return Tile::model()
			->select(Tile::getSelection())
			->whereUser($ePlayer['user'])
			->sort(['board' => SORT_ASC, 'tile' => SORT_ASC])
			->getCollection(index: ['board', 'tile']);

	}

	public static function getForUpdate(Tile $eTile): Tile {

		return Tile::model()
			->select(Tile::getSelection())
			->whereId($eTile)
			->getForUpdate()
			->validate();

	}

	public static function getTilesLuzerne(\Collection $cTile): array {

		return $cTile
			->find(fn($eTile) => $eTile['growing']->notEmpty() and $eTile['growing']['fqn'] === 'luzerne')
			->getColumn('tile');

	}

	/**
	 * Ce que la page montre d'une case, sur les cases d'un même plateau : la récolte dépend des luzernes
	 * voisines, et le temps de récolte des pivoines du plateau.
	 * - `stage`, de 0 au semis à 1 à maturité, une plante compagne étant toujours adulte ;
	 * - `remaining`, les secondes jusqu'à la récolte, NULL sans récolte prévue ;
	 * - `actionable`, un geste au moins est permis au joueur connecté, ce qui exclut la vue d'un ami.
	 */
	public static function fillState(\Collection $cTile, Player $ePlayer): void {

		// Lus avant la boucle : une Collection n'a qu'un curseur.
		$harvestTime = $ePlayer->getHarvestTime($cTile);
		$tilesLuzerne = self::getTilesLuzerne($cTile);

		foreach($cTile as $eTile) {

			$eTile['isRipe'] = $eTile->isRipe();
			$eTile['harvest'] = $eTile->isHarvestable() ? $eTile->getHarvest($tilesLuzerne) : NULL;
			$eTile['harvestTime'] = $harvestTime;
			$eTile['stage'] = self::getStage($eTile);
			$eTile['remaining'] = ($eTile['harvestedAt'] === NULL) ? NULL : max(0, strtotime($eTile['harvestedAt']) - \Clock::timestamp());
			$eTile['actionable'] = (
				$eTile->acceptPlay() and
				(
					$eTile->assertSeed() or
					$eTile->assertWater() or
					$eTile->assertWeed() or
					$eTile->assertHarvest()
				)
			);

		}

	}

	private static function getStage(Tile $eTile): float {

		if($eTile['growing']->empty()) {
			return 0.0;
		}

		if(
			$eTile->isHarvestable() === FALSE or
			$eTile->isRipe()
		) {
			return 1.0;
		}

		$seeded = strtotime($eTile['seededAt']);
		$span = strtotime($eTile['harvestedAt']) - $seeded;

		// Un désherbage avance la récolte sans toucher au semis : la durée se relit à chaque fois. Une
		// culture qui n'est pas mûre a sa récolte après l'instant, lui-même après le semis, donc le
		// quotient reste dans [0, 1[ et la durée n'est jamais nulle.
		return round((\Clock::timestamp() - $seeded) / $span, 2);

	}

	public static function deleteByPlayer(Player $ePlayer): void {

		$cTile = Tile::model()
			->select('id')
			->whereUser($ePlayer['user'])
			->getCollection();

		foreach($cTile as $eTile) {
			self::delete($eTile);
		}

	}

}
?>
