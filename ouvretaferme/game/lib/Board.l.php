<?php
namespace game;

/**
 * Ce que la scène du plateau reçoit (`GameBoard`, static/js/board.js) : les quatre plateaux de la cour
 * à l'affichage de la page, puis les seules cases qu'un geste a changées.
 *
 * La scène ne reçoit aucun texte : ses marques sont rendues par `TileUi::getLabel()`, et elle ne fait
 * que les poser.
 */
class BoardLib {

	/**
	 * Les cases du joueur par plateau, état rempli (`TileLib::fillState()`), plateau par plateau.
	 */
	public static function getTiles(Player $ePlayer): \Collection {

		$ccTile = TileLib::getByPlayer($ePlayer);

		foreach($ccTile as $cTile) {
			TileLib::fillState($cTile, $ePlayer);
		}

		return $ccTile;

	}

	/**
	 * `board` est le plateau que la vue cadre à l'ouverture, NULL pour toute la cour. La scène ne lit aucun joueur :
	 * l'accueil et la fin de partie montrent la même cour sans personne, et un élément vide n'a pas d'utilisateur à
	 * qui demander sa fiche. La page pose donc la fiche du joueur regardé (`getFiche()`) sur `avatar` quand il y en a un.
	 */
	public static function getScene(\Collection $ccTile, int $boards, bool $readonly, ?int $board): array {

		$scene = [];

		for($position = 1; $position <= GameSetting::BOARDS; $position++) {

			$scene[$position] = [
				'open' => ($position <= $boards),
				'tiles' => $ccTile->offsetExists($position) ? self::export($ccTile[$position]) : []
			];

		}

		return [
			'board' => $board,
			'readonly' => $readonly,
			'avatar' => NULL,
			'boards' => $scene
		];

	}

	/**
	 * Les cases mûres de toute la cour, que la porte du jour annonce.
	 */
	public static function getRipe(\Collection $ccTile): int {

		$ripe = 0;

		foreach($ccTile as $cTile) {
			$ripe += $cTile->find(fn($eTile) => $eTile['isRipe'])->count();
		}

		return $ripe;

	}

	/**
	 * Les cases que le geste sur `$eTileBefore` a changées, relues après lui, état rempli. La décision se
	 * prend sur la culture d'avant le geste comme sur celle d'après :
	 * - une pivoine change le temps de récolte du plateau entier ;
	 * - une luzerne change la récolte de ses voisines (`GameSetting::ADJACENT`) ;
	 * - tout autre geste ne change que sa case.
	 * Les cases se relisent sur le plateau du joueur connecté, seul à pouvoir jouer la case.
	 */
	public static function getChanged(Tile $eTileBefore, Player $ePlayer): \Collection {

		$eTileBefore->expects(['board', 'tile', 'growing']);

		$cTile = TileLib::getByBoard($ePlayer, $eTileBefore['board']);
		TileLib::fillState($cTile, $ePlayer);

		$eTileAfter = $cTile[$eTileBefore['tile']];

		$growings = [
			$eTileBefore['growing']->empty() ? NULL : $eTileBefore['growing']['fqn'],
			$eTileAfter['growing']->empty() ? NULL : $eTileAfter['growing']['fqn']
		];

		if(in_array('pivoine', $growings, TRUE)) {
			return $cTile;
		}

		$tiles = [$eTileBefore['tile']];

		if(in_array('luzerne', $growings, TRUE)) {
			$tiles = array_merge($tiles, GameSetting::ADJACENT[$eTileBefore['tile']]);
		}

		return $cTile->find(fn($eTile) => in_array($eTile['tile'], $tiles, TRUE));

	}

	/**
	 * Les cases au contrat de `GameBoard.apply()`, sans texte.
	 */
	public static function export(\Collection $cTile): array {

		$tiles = [];

		foreach($cTile as $eTile) {

			$tiles[] = [
				'id' => $eTile['id'],
				'board' => $eTile['board'],
				'tile' => $eTile['tile'],
				'growing' => $eTile['growing']->empty() ? NULL : $eTile['growing']['fqn'],
				'companion' => ($eTile['growing']->notEmpty() and $eTile->isHarvestable() === FALSE),
				'ripe' => $eTile['isRipe'],
				'stage' => $eTile['stage'],
				'watering' => $eTile['watering'],
				'actionable' => $eTile['actionable']
			];

		}

		return $tiles;

	}

	/**
	 * La fiche du personnage du joueur, ou la composition par défaut pour qui n'a pas d'avatar. Le modèle
	 * téléversé ne paraît pas : comme l'équipe de l'accueil (`DashboardLib::getTeam()`), la scène monte
	 * toujours la fiche, que `getFiche()` filtre pour tous ceux qui voient la personne. La page ne montre
	 * un autre joueur qu'à ses amis (`FriendLib::are()`).
	 */
	public static function getFiche(Player $ePlayer): array {

		$eUserAvatar = \user\UserAvatarLib::getByUser($ePlayer['user']);

		if($eUserAvatar->empty()) {
			$eUserAvatar = new \user\UserAvatar([
				'source' => \user\UserAvatar::EDITOR,
				'composition' => \user\UserAvatarLib::getDefault()
			]);
		}

		return \user\UserAvatarLib::getFiche($eUserAvatar);

	}

}
?>
