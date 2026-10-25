<?php
namespace game;

/**
 * Le joueur : son inscription, sa journée, ce qui le rend premium, sa remise à zéro et ses amis.
 */
class PlayerAiTest extends GameFixtureAiTest {

	public function testCreateDealsTheBoardsAndTheStocks(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		$this->assertEquals(GameSetting::BOARDS * 16, Tile::model()->whereUser($ePlayer['user'])->count(), 'quatre plateaux de seize cases');

		$harvestable = Growing::model()->whereHarvest('!=', NULL)->count();

		$this->assertEquals($harvestable + 1, Food::model()->whereUser($ePlayer['user'])->count(), 'un stock par culture récoltable, et la soupe');

	}

	public function testOnlineTimeStartsAfreshEachDay(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		$ePlayer['time'] = 5;
		$ePlayer['timeUpdatedAt'] = '2026-11-01';

		PlayerLib::update($ePlayer, ['time', 'timeUpdatedAt']);

		$this->forgetOnline();

		$ePlayerOnline = PlayerLib::getOnline();

		$this->assertEquals(0.0, (float)$ePlayerOnline['time'], 'le joueur connecté retrouve sa journée');
		$this->assertEquals(0.0, $this->getTime($ePlayer), 'la journée rendue est écrite');

		$ePlayer['time'] = 5;
		$ePlayer['timeUpdatedAt'] = '2026-11-01';

		PlayerLib::update($ePlayer, ['time', 'timeUpdatedAt']);

		PlayerLib::resetTime();

		$this->assertEquals(0.0, $this->getTime($ePlayer), 'la remise à zéro de la nuit rend la journée aux joueurs de la veille');

	}

	public function testPremiumIsReadForEachPlayer(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');
		$ePlayerPremium = $this->createPlayer(self::EMAIL_USER_PREMIUM, 'zzTestPremium');

		// Le premium du joueur ordinaire se lit en premier : un premium mémoïsé pour tout le
		// processus le rendrait ensuite au second.
		$this->assertFalse($ePlayer->isPremium(), 'un utilisateur sans adhésion n\'est pas premium');

		$eStructure = \structure\StructureLib::getByUser($ePlayerPremium['user'], type: \structure\Structure::PRODUCER)->first();
		$eStructure['membership'] = TRUE;

		\structure\StructureLib::update($eStructure, ['membership']);

		$this->assertTrue($ePlayerPremium->isPremium(), 'l\'administrateur d\'une ferme adhérente est premium');
		$this->assertFalse($ePlayer->isPremium(), 'le premium de l\'un ne passe pas à l\'autre');

		$this->assertEquals(GameSetting::TIME_DAY, $ePlayer->getDailyTime(), 'journée ordinaire');
		$this->assertEquals(GameSetting::TIME_DAY_PREMIUM, $ePlayerPremium->getDailyTime(), 'journée premium');

	}

	public function testPlayerIsWrittenOnlyByItsOwner(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$ePlayerFriend = $this->createPlayer(self::EMAIL_USER_FRIEND, 'zzTestAmi');
		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		$this->assertTrue($ePlayerFriend->canRead(), 'un joueur se lit de tous');
		$this->assertFalse($ePlayerFriend->canWrite(), 'un joueur ne s\'écrit pas par un autre');
		$this->assertTrue($ePlayer->canWrite(), 'un joueur s\'écrit par son propriétaire');

		$this->assertFalse($this->getTile($ePlayerFriend, 1, 1)->canWrite(), 'une case ne s\'écrit pas par un autre joueur');
		$this->assertTrue($this->getTile($ePlayer, 1, 1)->canWrite(), 'une case s\'écrit par son joueur');

	}

	public function testRestartDeletesEverything(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		ActionLib::seed($ePlayer, $this->getTile($ePlayer, 1, 1), $this->getGrowing('carotte'));

		$this->assertEquals(1, History::model()->whereUser($ePlayer['user'])->count(), 'le semis est à l\'historique');

		PlayerLib::restart($ePlayer);

		// La seconde, comme un double envoi, trouve le joueur déjà effacé et ne lève rien.
		PlayerLib::restart($ePlayer);

		$this->assertTrue(PlayerLib::getByUser($ePlayer['user'])->empty(), 'le joueur est supprimé');
		$this->assertEquals(0, Tile::model()->whereUser($ePlayer['user'])->count(), 'ses cases aussi');
		$this->assertEquals(0, Food::model()->whereUser($ePlayer['user'])->count(), 'ses stocks aussi');
		$this->assertEquals(0, History::model()->whereUser($ePlayer['user'])->count(), 'son historique aussi');

	}

	public function testFriends(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setClock('2026-11-02 10:00:00');

		$ePlayerFriend = $this->createPlayer(self::EMAIL_USER_FRIEND, 'zzTestAmi');
		$ePlayer = $this->createPlayer(self::EMAIL_USER, 'zzTestJoueur');

		// Le compteur d'essais vit dans Redis, hors de la transaction du test : sans remise à zéro, un
		// troisième lancement dans l'heure serait refusé
		\user\ThrottleAiLib::clear('game-friend', (string)$ePlayer['user']['id']);

		$fw = new \FailWatch();

		PlayerLib::addFriend($ePlayer, 'zz-inconnu');
		$this->assertTrue($fw->has('Friend::code.unknown'), 'un code inconnu est refusé');

		$fw = new \FailWatch();

		PlayerLib::addFriend($ePlayer, $ePlayer['code']);
		$this->assertTrue($fw->has('Friend::code.unknown'), 'son propre code est refusé');

		$fw = new \FailWatch();

		PlayerLib::addFriend($ePlayer, $ePlayerFriend['code']);
		PlayerLib::addFriend($ePlayer, $ePlayerFriend['code']);

		$this->assertTrue($fw->ok(), 'un ami se rajoute sans erreur');
		$this->assertTrue(FriendLib::exists($ePlayer, $ePlayerFriend), 'l\'amitié est écrite dans un sens');
		$this->assertTrue(FriendLib::exists($ePlayerFriend, $ePlayer), 'et dans l\'autre');
		$this->assertEquals(2, Friend::model()->whereUser('IN', [$ePlayer['user']['id'], $ePlayerFriend['user']['id']])->count(), 'une seule fois');

		$ePlayer = $this->getPlayer($ePlayer['user']);
		$ePlayerFriend = $this->getPlayer($ePlayerFriend['user']);

		$this->assertTrue($ePlayer->assertMotivate($ePlayerFriend), 'le joueur peut motiver son ami');

		$fw = new \FailWatch();

		PlayerLib::motivate($ePlayer, $ePlayerFriend);

		$this->assertTrue($fw->ok(), 'la motivation passe');
		$this->assertEquals(-1.0 * GameSetting::BONUS_MOTIVATION, $this->getTime($ePlayerFriend), 'l\'ami gagne du temps');

		$fw = new \FailWatch();

		PlayerLib::motivate($ePlayer, $ePlayerFriend);

		$this->assertTrue($fw->has('Player::giftSentAt.motivate'), 'une seule motivation par jour');
		$this->assertEquals(-1.0 * GameSetting::BONUS_MOTIVATION, $this->getTime($ePlayerFriend), 'la seconde motivation ne donne rien');

		$ePlayer = $this->getPlayer($ePlayer['user']);
		$ePlayerFriend = $this->getPlayer($ePlayerFriend['user']);

		$this->assertFalse($ePlayer->assertMotivate($ePlayerFriend), 'le bouton disparait après la motivation');

		PlayerLib::removeFriend($ePlayer, $ePlayerFriend);

		$this->assertFalse(FriendLib::exists($ePlayer, $ePlayerFriend), 'l\'amitié se défait dans un sens');
		$this->assertFalse(FriendLib::exists($ePlayerFriend, $ePlayer), 'et dans l\'autre');

		$this->setClock('2026-11-03 10:00:00');

		$fw = new \FailWatch();

		PlayerLib::motivate($ePlayer, $ePlayerFriend);

		$this->assertTrue($fw->has('Player::giftSentAt.motivate'), 'on ne motive pas un joueur qui n\'est plus un ami');

	}

}
?>
