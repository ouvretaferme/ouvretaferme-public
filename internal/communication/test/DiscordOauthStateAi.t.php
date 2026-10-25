<?php
namespace communication;

/**
 * Le `state` du retour OAuth Discord peut-il être fabriqué ?
 *
 * Il était l'identifiant de l'accès chiffré par `CryptLib::encrypt()` sous le secret `discord`, un
 * AES-CBC sans authentification : l'IV voyage en clair, donc un chiffré dont on connaît le clair se
 * réécrit en n'importe quel autre. Il ne portait aucun horodatage, donc se rejouait indéfiniment.
 *
 * Le `state` est désormais signé (`CryptLib::sign()`), préfixé et valable
 * `CommunicationSetting::DISCORD_STATE_LIFETIME`. Depuis que le lien est celui de la structure (R-45),
 * il porte l'invitation et l'utilisateur à qui l'aller l'a émis : réécrire l'un ou l'autre ferait
 * rejoindre le serveur par le lien d'une autre structure, ou sous le nom d'un autre.
 *
 * Tout se joue en transaction annulée par `tearDown()`, la dev étant partagée par plusieurs sessions.
 * Aucun cas ne touche le réseau.
 *
 * Lancement : dev/test package=communication file=DiscordOauthStateAi
 */
class DiscordOauthStateAiTest extends \Test {

	private const STRUCTURE = \structure\FixtureAiLib::F1;

	private const USER = 1;

	private \structure\Structure $eStructure;

	private \user\User $eUser;

	public function init(): void {

		$this->eStructure = \structure\StructureLib::getById(self::STRUCTURE);
		$this->eUser = \user\UserLib::getById(self::USER);

	}

	public function setUp(): void {
		DiscordInvitation::model()->beginTransaction();
	}

	public function tearDown(): void {
		DiscordInvitation::model()->rollBack();
	}

	private function getInvitation(): DiscordInvitation {

		$this->assertTrue($this->eStructure->notEmpty(), 'Structure de référence #'.self::STRUCTURE.' introuvable');
		$this->assertTrue($this->eUser->notEmpty(), 'Utilisateur de référence #'.self::USER.' introuvable');

		return DiscordInvitationLib::getOrCreate($this->eStructure);

	}

	private function assertRefused(string $state, string $message): void {

		[$eDiscordInvitation, $eUser] = DiscordAccessLib::getByState($state);

		$this->assertTrue(($eDiscordInvitation->empty() and $eUser->empty()), $message);

	}

	public function testRoundTrip(): void {

		$eDiscordInvitation = $this->getInvitation();

		[$eDiscordInvitationRead, $eUserRead] = DiscordAccessLib::getByState(DiscordAccessLib::getState($eDiscordInvitation, $this->eUser));

		$this->assertEquals($eDiscordInvitation['id'], $eDiscordInvitationRead->empty() ? NULL : $eDiscordInvitationRead['id'], 'l\'invitation est relue');
		$this->assertEquals(self::STRUCTURE, $eDiscordInvitationRead->empty() ? NULL : $eDiscordInvitationRead['structure']['id'], 'l\'invitation relue porte la structure du lien');
		$this->assertEquals(self::USER, $eUserRead->empty() ? NULL : $eUserRead['id'], 'l\'utilisateur est relu');

	}

	/** Les `state` d'avant la signature, qui circulent encore le jour du déploiement. */
	public function testEncryptedStateIsRefused(): void {

		$eDiscordInvitation = $this->getInvitation();

		$this->assertRefused(\main\CryptLib::encrypt($eDiscordInvitation['id'], 'discord'), 'un state chiffré sous le secret discord, comme ceux émis avant la signature, est refusé');

	}

	public function testRewrittenStateIsRefused(): void {

		$eDiscordInvitation = $this->getInvitation();
		$state = DiscordAccessLib::getState($eDiscordInvitation, $this->eUser);

		$this->assertRefused(str_replace('discord:'.$eDiscordInvitation['id'].':', 'discord:'.($eDiscordInvitation['id'] + 1).':', $state), 'une invitation réécrite sous la signature d\'origine est refusée');
		$this->assertRefused(str_replace(':'.self::USER.':', ':'.(self::USER + 1).':', $state), 'un utilisateur réécrit sous la signature d\'origine est refusé');

		$last = substr($state, -1);
		$this->assertRefused(substr($state, 0, -1).($last === '0' ? '1' : '0'), 'une signature altérée d\'un caractère est refusée');

	}

	public function testMalformedStateIsRefused(): void {

		$eDiscordInvitation = $this->getInvitation();

		$this->assertRefused(\main\CryptLib::sign('discord:'.$eDiscordInvitation['id'].'abc:'.self::USER.':'.time(), 'discord'), 'une invitation qui n\'est pas faite que de chiffres est refusée, même signée');
		$this->assertRefused(\main\CryptLib::sign('discord:'.$eDiscordInvitation['id'].':'.time(), 'discord'), 'un state signé sans utilisateur, comme ceux d\'avant le lien de structure, est refusé');
		$this->assertRefused(\main\CryptLib::sign('superpdp:'.$eDiscordInvitation['id'].':'.self::USER.':'.time(), 'discord'), 'un state signé d\'un autre préfixe est refusé');
		$this->assertRefused(\main\CryptLib::sign('discord:0:'.self::USER.':'.time(), 'discord'), 'un state signé d\'une invitation inexistante est refusé');
		$this->assertRefused('', 'une chaîne vide est refusée');

	}

	public function testExpiredStateIsRefused(): void {

		$eDiscordInvitation = $this->getInvitation();

		$this->assertRefused(DiscordAccessLib::getState($eDiscordInvitation, $this->eUser, time() - CommunicationSetting::DISCORD_STATE_LIFETIME - 60), 'un state de plus d\'une heure est refusé');

		[$eDiscordInvitationRead] = DiscordAccessLib::getByState(DiscordAccessLib::getState($eDiscordInvitation, $this->eUser, time() - CommunicationSetting::DISCORD_STATE_LIFETIME + 60));
		$this->assertTrue($eDiscordInvitationRead->notEmpty(), 'un state de moins d\'une heure est accepté');

	}

	/** Le secret du `state` lui est propre : une signature faite sous un autre ne vaut pas ici. */
	public function testStateSignedWithAnotherSecretIsRefused(): void {

		$eDiscordInvitation = $this->getInvitation();

		$this->assertRefused(\main\CryptLib::sign('discord:'.$eDiscordInvitation['id'].':'.self::USER.':'.time(), 'payment'), 'un state signé sous le secret payment est refusé');

	}

}
?>
