<?php
namespace communication;

/**
 * Le retrait du rôle de la Communauté est-il acquis ?
 *
 * Le retrait quotidien passe un accès en `REVOKED` sur la seule réponse de Discord. Un 404 y a deux
 * sens : la personne a quitté le serveur (Unknown Member, 10007), et le retrait est acquis ; ou le
 * rôle, voire le serveur, est inconnu (10011, 10004), parce que `roleId` ou `guildId` est mal posé.
 * Accepter tout 404 passerait alors tout le parc en `REVOKED` en laissant le rôle à chacun.
 *
 * Aucun cas ne touche le réseau ni la base : seule la lecture de la réponse est éprouvée.
 *
 * Lancement : dev/test package=communication file=DiscordRoleAi
 */
class DiscordRoleAiTest extends \Test {

	public function testRemovedWhenDiscordConfirms(): void {

		$this->assertTrue(DiscordAccessLib::isRoleRemoved(204, ''), 'un 204 retire le rôle');

	}

	public function testRemovedWhenMemberLeftTheServer(): void {

		$this->assertTrue(DiscordAccessLib::isRoleRemoved(404, '{"message": "Unknown Member", "code": 10007}'), 'un membre parti du serveur n\'a plus le rôle');

	}

	public function testNotRemovedWhenRoleOrServerIsUnknown(): void {

		$this->assertFalse(DiscordAccessLib::isRoleRemoved(404, '{"message": "Unknown Role", "code": 10011}'), 'un rôle inconnu ne retire rien');
		$this->assertFalse(DiscordAccessLib::isRoleRemoved(404, '{"message": "Unknown Guild", "code": 10004}'), 'un serveur inconnu ne retire rien');
		$this->assertFalse(DiscordAccessLib::isRoleRemoved(404, '{"message": "Unknown Member", "code": "10007"}'), 'un code qui n\'est pas un entier ne vaut pas Unknown Member');
		$this->assertFalse(DiscordAccessLib::isRoleRemoved(404, '<html>Not Found</html>'), 'un 404 sans JSON ne retire rien');
		$this->assertFalse(DiscordAccessLib::isRoleRemoved(404, ''), 'un 404 vide ne retire rien');

	}

	public function testNotRemovedOnOtherAnswers(): void {

		$this->assertFalse(DiscordAccessLib::isRoleRemoved(403, '{"message": "Missing Permissions", "code": 50013}'), 'un bot sans la gestion des rôles, ou placé sous le rôle, ne retire rien');
		$this->assertFalse(DiscordAccessLib::isRoleRemoved(429, '{"message": "You are being rate limited.", "retry_after": 1.2, "global": false}'), 'une limite de débit ne retire rien, le retrait repasse le lendemain');
		$this->assertFalse(DiscordAccessLib::isRoleRemoved(0, ''), 'une requête sans réponse ne retire rien');

	}

}
?>
