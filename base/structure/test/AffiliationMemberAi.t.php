<?php
namespace structure;

/**
 * Ce qu'un groupe ouvre quand il s'adresse à ses fermes rattachées : les comptes de ces fermes
 * (`AffiliationAiLib::getMembersByAffiliate()`), et, dans l'autre sens, les couples (ferme, groupe)
 * qui valent encore pour un lot de fermes (`getValidByStructureProducers()`). Les deux empruntent
 * `applyStillValid()` : un rattachement dont l'auteur a quitté le groupe ou la ferme ne compte plus,
 * et ce fichier le prouve sur la table, dans les deux lectures.
 *
 * Décor : le groupe partageant G4 et ses fermes (F6 et F7 rattachées par le lecteur principal, F11,
 * la ferme du second associé, rattachée par lui, F10, la ferme fermée). Isolation : une transaction
 * par méthode, annulée en `tearDown()`.
 *
 * Lancement : dev/test package=structure file=AffiliationMemberAi
 */
class AffiliationMemberAiTest extends CommunityFixtureAiTest {

	public function setUp(): void {
		Affiliation::model()->beginTransaction();
	}

	public function tearDown(): void {
		Affiliation::model()->rollBack();
	}

	private function getGroup(): Structure {
		return $this->getStructure($this->communitySharing);
	}

	private function getUser(): \user\User {
		return \user\UserLib::getById($this->user);
	}

	private function getUserOther(): \user\User {
		return \user\UserLib::getById($this->userOther);
	}

	/**
	 * Passe un compte hors d'une structure, par le Lib, comme le fait l'écran des membres.
	 */
	private function leave(\user\User $eUser, Structure $eStructure): void {

		$eMember = MemberLib::getByUserAndStructure($eUser, $eStructure);

		$this->assertTrue($eMember->notEmpty(), 'le compte '.$eUser['id'].' est membre de la structure '.$eStructure['id'].' avant de la quitter');

		$eMember['status'] = Member::OUT;
		MemberLib::update($eMember, ['status']);

	}

	/**
	 * Les comptes d'une ferme rattachée se lisent en une fois, la ferme nommée sur chaque adhésion.
	 * L'auteur du rattachement y est, puisqu'il administre la ferme ; l'administrateur du groupe qui
	 * n'est pas membre de la ferme n'y est pas.
	 */
	public function testMembersOfAffiliates(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$ccMember = AffiliationAiLib::getMembersByAffiliate($this->getGroup());

		$this->assertTrue($ccMember->offsetExists(self::STRUCTURE_SHARED), 'F6, rattachée par le lecteur principal, est là');
		$this->assertTrue($ccMember->offsetExists(self::STRUCTURE_SHARING_ONLY), 'F7 aussi');
		$this->assertTrue($ccMember->offsetExists($this->structureOtherAssociate), 'et celle du second associé');

		$this->assertTrue($ccMember[self::STRUCTURE_SHARED]->offsetExists($this->user), 'le lecteur principal, qui a rattaché F6, en est membre');
		$this->assertEquals(self::NAME_STRUCTURE_OTHER_ASSOCIATE, $ccMember[$this->structureOtherAssociate][$this->userOther]['structure']['name'], 'chaque adhésion nomme sa ferme');

		$this->assertFalse($ccMember[self::STRUCTURE_SHARED]->offsetExists($this->userOther), 'le second associé administre le groupe, pas F6 : il n\'y est pas');
		$this->assertFalse($ccMember->offsetExists($this->structureClosed), 'la ferme fermée n\'y est pas');
		$this->assertFalse($ccMember->offsetExists(self::STRUCTURE_SILENT), 'F8 est rattachée à un autre groupe');

	}

	/**
	 * Un rattachement dont l'auteur a quitté le groupe ne compte plus, et sa ferme sort de la liste,
	 * même si elle a toujours des membres.
	 */
	public function testAuthorOutOfGroup(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->leave($this->getUserOther(), $this->getGroup());

		$ccMember = AffiliationAiLib::getMembersByAffiliate($this->getGroup());

		$this->assertFalse($ccMember->offsetExists($this->structureOtherAssociate), 'le second associé a quitté le groupe : sa ferme n\'est plus rattachée');
		$this->assertTrue($ccMember->offsetExists(self::STRUCTURE_SHARED), 'F6, rattachée par le lecteur principal, reste');

	}

	/**
	 * Un membre parti de sa ferme n'est plus dans ses comptes, la ferme restant rattachée par un
	 * associé qui, lui, y est encore.
	 */
	public function testMemberOutOfFarm(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getStructure($this->structureOtherAssociate);

		$ccMember = AffiliationAiLib::getMembersByAffiliate($this->getGroup());

		$this->assertTrue($ccMember[$eStructure['id']]->offsetExists($this->user), 'le lecteur principal administre aussi la ferme du second associé');

		$this->leave($this->getUser(), $eStructure);

		$ccMember = AffiliationAiLib::getMembersByAffiliate($this->getGroup());

		$this->assertTrue($ccMember->offsetExists($eStructure['id']), 'la ferme reste rattachée, par le second associé');
		$this->assertFalse($ccMember[$eStructure['id']]->offsetExists($this->user), 'le lecteur principal, parti, n\'est plus dans ses comptes');
		$this->assertTrue($ccMember[$eStructure['id']]->offsetExists($this->userOther), 'le second associé y est toujours');

	}

	/**
	 * Borné aux fermes données : un lecteur qui ne veut que les siennes ne lit pas les autres.
	 */
	public function testMembersBounded(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$cStructure = new \Collection([
			self::STRUCTURE_SHARED => new Structure(['id' => self::STRUCTURE_SHARED]),
			self::STRUCTURE_SILENT => new Structure(['id' => self::STRUCTURE_SILENT])
		]);

		$ccMember = AffiliationAiLib::getMembersByAffiliate($this->getGroup(), $cStructure);

		$this->assertEquals([self::STRUCTURE_SHARED], $ccMember->getKeys(), 'F6 seule : F8 n\'est pas rattachée à ce groupe, F7 n\'est pas demandée');

		$cStructure = new \Collection([
			self::STRUCTURE_SILENT => new Structure(['id' => self::STRUCTURE_SILENT])
		]);

		$this->assertTrue(AffiliationAiLib::getMembersByAffiliate($this->getGroup(), $cStructure)->empty(), 'aucune ferme demandée n\'est rattachée : rien');

	}

	/**
	 * Les couples (ferme, groupe) qui valent pour un lot de fermes, le groupe nommé ; un couple dont
	 * l'auteur a quitté le groupe se tait.
	 */
	public function testValidCouples(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$cStructure = new \Collection([
			self::STRUCTURE_SHARED => new Structure(['id' => self::STRUCTURE_SHARED]),
			$this->structureOtherAssociate => new Structure(['id' => $this->structureOtherAssociate]),
			self::STRUCTURE_SILENT => new Structure(['id' => self::STRUCTURE_SILENT])
		]);

		$ccAffiliation = AffiliationAiLib::getValidByStructureProducers($cStructure);

		$communities = $ccAffiliation[self::STRUCTURE_SHARED]->getKeys();
		sort($communities);

		$this->assertEquals([$this->communitySharing, $this->communitySharingBis], $communities, 'F6 est rattachée aux deux groupes partageants, et à eux seuls');
		$this->assertEquals(self::NAME_COMMUNITY_SHARING, $ccAffiliation[self::STRUCTURE_SHARED][$this->communitySharing]['structureCommunity']['name'], 'le groupe est nommé');
		$this->assertEquals([$this->communitySilent], $ccAffiliation[self::STRUCTURE_SILENT]->getKeys(), 'F8 au groupe silencieux');
		$this->assertEquals([$this->communitySharing], $ccAffiliation[$this->structureOtherAssociate]->getKeys(), 'la ferme du second associé au groupe partageant');

		$this->leave($this->getUserOther(), $this->getGroup());

		$ccAffiliation = AffiliationAiLib::getValidByStructureProducers($cStructure);

		$this->assertFalse($ccAffiliation->offsetExists($this->structureOtherAssociate), 'l\'auteur parti du groupe, le couple se tait');
		$this->assertTrue($ccAffiliation->offsetExists(self::STRUCTURE_SHARED), 'les couples du lecteur principal tiennent');

		$this->assertTrue(AffiliationAiLib::getValidByStructureProducers(new \Collection())->empty(), 'aucune ferme, aucun couple, sans requête');

	}

}
?>
