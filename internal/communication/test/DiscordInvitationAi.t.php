<?php
namespace communication;

/**
 * Le lien d'invitation au Discord est celui de la structure (R-45, le 26/09/2026 : « unique par
 * ferme, mais utilisable plus d'une fois ») : un seul par structure, qui se relit tel quel à chaque
 * affichage et se retrouve par sa clé.
 *
 * Tout se joue en transaction annulée par `tearDown()`. Le droit de s'en servir (être de l'équipe,
 * Communauté en cours) se pèse dans subscription, et s'éprouve dans SubscriptionDiscordAi.
 *
 * Lancement : dev/test package=communication file=DiscordInvitationAi
 */
class DiscordInvitationAiTest extends \Test {

	private const STRUCTURE = \structure\FixtureAiLib::F1;

	private const STRUCTURE_OTHER = \structure\FixtureAiLib::F3;

	public function setUp(): void {
		DiscordInvitation::model()->beginTransaction();
	}

	public function tearDown(): void {
		DiscordInvitation::model()->rollBack();
	}

	private function getStructure(int $id): \structure\Structure {

		$eStructure = \structure\StructureLib::getById($id);

		$this->assertTrue($eStructure->notEmpty(), 'Structure de référence #'.$id.' introuvable');

		return $eStructure;

	}

	public function testOneInvitationByStructure(): void {

		$eStructure = $this->getStructure(self::STRUCTURE);

		$eDiscordInvitation = DiscordInvitationLib::getOrCreate($eStructure);
		$eDiscordInvitationAgain = DiscordInvitationLib::getOrCreate($eStructure);

		$this->assertTrue($eDiscordInvitation->notEmpty(), 'le premier affichage crée le lien');
		$this->assertEquals(1, preg_match('/^[0-9a-f]{12}$/', $eDiscordInvitation['key']), 'la clé est tirée au hasard sur douze caractères hexadécimaux');
		$this->assertEquals($eDiscordInvitation['id'], $eDiscordInvitationAgain['id'], 'le second affichage relit le même lien');
		$this->assertEquals($eDiscordInvitation['key'], $eDiscordInvitationAgain['key'], 'la clé ne change pas d\'un affichage à l\'autre');
		$this->assertEquals(1, DiscordInvitation::model()->whereStructure($eStructure)->count(), 'la structure n\'a qu\'une ligne d\'invitation');

	}

	public function testStructuresHaveTheirOwnInvitation(): void {

		$eDiscordInvitation = DiscordInvitationLib::getOrCreate($this->getStructure(self::STRUCTURE));
		$eDiscordInvitationOther = DiscordInvitationLib::getOrCreate($this->getStructure(self::STRUCTURE_OTHER));

		$this->assertTrue($eDiscordInvitation['key'] !== $eDiscordInvitationOther['key'], 'deux structures n\'ont pas la même clé');

	}

	public function testGetByKey(): void {

		$eDiscordInvitation = DiscordInvitationLib::getOrCreate($this->getStructure(self::STRUCTURE));

		$eDiscordInvitationRead = DiscordInvitationLib::getByKey($eDiscordInvitation['key']);

		$this->assertEquals($eDiscordInvitation['id'], $eDiscordInvitationRead->empty() ? NULL : $eDiscordInvitationRead['id'], 'la clé retrouve le lien');
		$this->assertEquals(self::STRUCTURE, $eDiscordInvitationRead->empty() ? NULL : $eDiscordInvitationRead['structure']['id'], 'le lien retrouvé porte sa structure');
		$this->assertTrue(DiscordInvitationLib::getByKey('')->empty(), 'une clé vide ne retrouve rien');
		$this->assertTrue(DiscordInvitationLib::getByKey('000000000000')->empty(), 'une clé inconnue ne retrouve rien');

	}

}
?>
