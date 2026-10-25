<?php
namespace game;

/**
 * Ce que la cour reçoit (`BoardLib`) : les cases qu'un geste a changées, le stade d'une culture, ce
 * qu'un ami voit d'une partie, la cour sans personne, les récoltes mûres que la porte annonce, le jour
 * de l'Avent, la place du joueur au classement, et le code ami, tiré au hasard et limité en essais.
 */
class BoardAiTest extends GameFixtureAiTest {

	public function testChangedFollowsTheGrowing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		$this->assertEquals([6], $this->seedAndGetChanged($ePlayer, 6, 'carotte'), 'une carotte ne change que sa case');

		$this->rest($ePlayer);

		$expected = array_merge([7], GameSetting::ADJACENT[7]);
		sort($expected);

		$this->assertEquals($expected, $this->seedAndGetChanged($ePlayer, 7, 'luzerne'), 'une luzerne change ses voisines, dont la récolte compte sur elle');

		$this->rest($ePlayer);

		$this->assertEquals(range(1, 16), $this->seedAndGetChanged($ePlayer, 16, 'pivoine'), 'une pivoine change le temps de récolte du plateau entier');

		$changed = BoardLib::getChanged($this->getTile($ePlayer, 2, 1), $ePlayer)->getColumn('board');
		$this->assertEquals([2], array_values(array_unique($changed)), 'un geste ne relit que le plateau de sa case');

	}

	public function testLuzerneIsNotHarvested(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 3), $this->getGrowing('luzerne'));

		$this->setClock('2026-12-20 10:00:00');
		$this->rest($ePlayer);

		$eTile = $this->getTile($ePlayer, 1, 3);

		$this->assertFalse($eTile->acceptHarvest(), 'une luzerne ne se récolte jamais');

		$fw = new \FailWatch();

		ActionLib::harvest($ePlayer, $eTile);

		$this->assertTrue($fw->has('Tile::growing.harvest'), 'sa récolte est refusée');
		$this->assertTrue($this->getTile($ePlayer, 1, 3)['growing']->notEmpty(), 'elle reste en place');

	}

	public function testStageGoesFromSeedToRipe(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 1), $this->getGrowing('carotte'));
		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 2), $this->getGrowing('luzerne'));

		$state = fn() => BoardLib::getTiles($ePlayer)[1];

		$this->assertEquals(0.0, $state()[1]['stage'], 'au semis');
		$this->assertEquals(1.0, $state()[2]['stage'], 'une plante compagne est toujours adulte');
		$this->assertEquals(0.0, $state()[3]['stage'], 'une case vide');

		// La carotte pousse seize jours
		$this->setClock('2026-11-10 10:00:00');

		$this->assertEquals(0.5, $state()[1]['stage'], 'à mi-chemin de la récolte');
		$this->assertFalse($state()[1]['isRipe']);

		$this->setClock('2026-11-18 10:00:00');

		$this->assertEquals(1.0, $state()[1]['stage'], 'mûre');
		$this->assertTrue($state()[1]['isRipe']);
		$this->assertEquals(0, $state()[1]['remaining'], 'plus rien à attendre');

	}

	public function testFriendSeesWithoutActing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayerFriend = $this->createPlayer(self::EMAIL_USER_FRIEND, 'zzTestAmi');
		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		$scene = $this->getScene($ePlayer, NULL);

		$this->assertFalse($scene['readonly'], 'sa propre cour se joue');
		$this->assertEquals(GameSetting::BOARDS * 16, $this->countActionable($scene), 'chaque case vide d\'un plateau ouvert se sème');

		\user\ConnectionLib::setOnline($ePlayerFriend['user']);
		$this->forgetOnline();

		$scene = $this->getScene($ePlayer, 2);

		$this->assertTrue($scene['readonly'], 'la cour d\'un autre se regarde');
		$this->assertEquals(0, $this->countActionable($scene), 'aucune case ne s\'y touche');
		$this->assertEquals(2, $scene['board']);

		$this->assertEquals(['corps', 'elements'], array_keys($scene['avatar']), 'l\'avatar part en fiche, rien d\'autre');

		$json = json_encode($scene);

		foreach(['hash', 'bakedIndex', 'upload', 'email'] as $secret) {
			$this->assertFalse(str_contains($json, '"'.$secret.'"'), 'la scène d\'un ami ne porte pas '.$secret);
		}

	}

	public function testEmptyCourtReadsNoPlayer(): void {

		$scene = BoardLib::getScene(new \Collection(), GameSetting::BOARDS, TRUE, NULL);

		$this->assertTrue($scene['readonly'], 'la cour sans personne ne se joue pas');
		$this->assertEquals(NULL, $scene['avatar'], 'personne ne s\'y tient');
		$this->assertEquals(GameSetting::BOARDS, count(array_filter($scene['boards'], fn($board) => $board['open'])), 'tous les plateaux y sont ouverts');
		$this->assertEquals(0, array_sum(array_map(fn($board) => count($board['tiles']), $scene['boards'])), 'aucune case n\'y est posée');

	}

	public function testRipeCountsTheWholeCourt(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 1), $this->getGrowing('carotte'));
		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 2, 1), $this->getGrowing('carotte'));
		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 2), $this->getGrowing('luzerne'));

		$this->assertEquals(0, BoardLib::getRipe(BoardLib::getTiles($ePlayer)), 'rien n\'est mûr au semis');

		// La carotte pousse seize jours, la luzerne reste en place sans jamais se récolter
		$this->setClock('2026-11-18 10:00:00');

		$this->assertEquals(2, BoardLib::getRipe(BoardLib::getTiles($ePlayer)), 'les deux carottes, sur deux plateaux, et pas la luzerne');

	}

	public function testAdventRunsFromFirstToTwentyFourth(): void {

		$this->setClock('2026-11-30 10:00:00');
		$this->assertEquals(NULL, GameSetting::getAdventDay(), 'pas de calendrier en novembre');

		$this->setClock('2026-12-01 00:00:00');
		$this->assertEquals(1, GameSetting::getAdventDay(), 'le 1er décembre ouvre le calendrier');

		$this->setClock('2026-12-24 23:00:00');
		$this->assertEquals(24, GameSetting::getAdventDay(), 'le 24, les rennes passent');

		$this->setClock('2026-12-25 10:00:00');
		$this->assertEquals(NULL, GameSetting::getAdventDay(), 'le calendrier s\'arrête au 24');

	}

	public function testRankingKeepsThePlayerBeyondTwenty(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		// Un seul renne le range derrière les joueurs de la base qui en ont plus, sans attendre une récolte
		$ePlayer['points'] = 1;
		PlayerLib::update($ePlayer, ['points']);

		$position = Player::model()
			->whereName('NOT IN', GameSetting::ADMIN)
			->wherePoints('>', 1)
			->count() + 1;

		$cPlayer = PlayerLib::getPointsRanking($ePlayer);
		$ePlayerRanked = $cPlayer->find(fn($ePlayerRanking) => $ePlayerRanking->is($ePlayer))->first();

		$this->assertTrue($ePlayerRanked !== NULL, 'le joueur connecté figure au classement');
		$this->assertEquals($position, $ePlayerRanked['position'], 'à sa vraie place');
		$this->assertEquals($position, PlayerLib::getPosition($ePlayer), 'la même place que dans Ma partie');

		$this->assertTrue($position > 20, 'le cas demande au moins vingt joueurs à plus d\'un renne dans la base de dev, copie de la production');
		$this->assertEquals(21, $cPlayer->count(), 'au-delà des vingt premiers, il vient en plus d\'eux');

	}

	// La scène telle que la page la monte (`page/index.p.php`) : la fiche se pose à part, sous un joueur qui existe
	private function getScene(Player $ePlayer, ?int $board): array {

		$scene = BoardLib::getScene(BoardLib::getTiles($ePlayer), $ePlayer->getBoards(), $ePlayer->isOnline() === FALSE, $board);
		$scene['avatar'] = BoardLib::getFiche($ePlayer);

		return $scene;

	}

	public function testFriendCodeIsDrawnAndThrottled(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayerFriend = $this->createPlayer(self::EMAIL_USER_FRIEND, 'zzTestAmi');
		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		$this->assertTrue(PlayerLib::isCode($ePlayer['code']), 'le code est tiré dans l\'alphabet, à sa longueur');
		$this->assertTrue($ePlayer['code'] !== $ePlayerFriend['code'], 'deux joueurs, deux codes');

		$code = $ePlayer['code'];
		PlayerLib::updateCode($ePlayer);

		$this->assertTrue($code !== $this->getPlayer($ePlayer['user'])['code'], 'un code se retire');

		if(\Cache::isDisabled()) {
			$this->assertTrue(FALSE, 'la limite d\'essais demande Redis');
			return;
		}

		$this->clearThrottle($ePlayer);

		for($attempt = 1; $attempt <= GameSetting::LIMIT_FRIEND; $attempt++) {

			$fw = new \FailWatch();
			PlayerLib::addFriend($ePlayer, 'ZZZZZZZZ');

			$this->assertTrue($fw->has('Friend::code.unknown'), 'essai '.$attempt.' : refusé pour son code');

		}

		$fw = new \FailWatch();
		PlayerLib::addFriend($ePlayer, $ePlayerFriend['code']);

		$this->assertTrue($fw->has('Friend::code.throttled'), 'au-delà de la limite, même un bon code est refusé');
		$this->assertFalse(FriendLib::exists($ePlayer, $ePlayerFriend), 'et rien n\'est écrit');

		$this->clearThrottle($ePlayer);

	}

	private function seedAndGetChanged(Player $ePlayer, int $tile, string $fqn): array {

		$eTileBefore = $this->getTile($ePlayer, 1, $tile);

		$fw = new \FailWatch();

		ActionLib::seed($ePlayer, $eTileBefore, $this->getGrowing($fqn));

		$this->assertTrue($fw->ok(), 'le semis de '.$fqn.' passe');

		$tiles = BoardLib::getChanged($eTileBefore, $ePlayer)->getColumn('tile');
		sort($tiles);

		return $tiles;

	}

	private function countActionable(array $scene): int {

		$count = 0;

		foreach($scene['boards'] as $board) {
			foreach($board['tiles'] as $tile) {
				$count += $tile['actionable'] ? 1 : 0;
			}
		}

		return $count;

	}

	// Le compteur d'essais vit dans Redis, hors de la transaction du test : il se remet à zéro autour du cas
	private function clearThrottle(Player $ePlayer): void {
		\user\ThrottleAiLib::clear('game-friend', (string)$ePlayer['user']['id']);
	}

}
?>
