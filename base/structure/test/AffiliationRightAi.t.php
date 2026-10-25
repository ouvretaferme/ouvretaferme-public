<?php
namespace structure;

/**
 * Copier suppose de lire : l'invariant `requires`, tenu par les écritures d'un rattachement.
 *
 * `CommunityRightAiTest` éprouve le calcul en mémoire. Ici, ce que la table porte après chacun des
 * deux chemins qui l'écrivent : l'entrée dans le groupe (`AffiliationAiLib::create()`) et le
 * réglage d'un rattachement existant (`AffiliationAiLib::setRights()`). Les deux scénarios sont
 * ceux que la relecture du 14/09/2026 a reproduits, la copie de la production restant accordée
 * sans la lecture.
 *
 * Décor : le groupe partageant G4 et la ferme F6 du décor des tests. Le groupe est pris
 * par son id et reconstruit en mémoire sous le type et la demande que chaque cas exige : c'est
 * la demande du groupe qui décide de ce que `setRights()` touche, et la changer en base
 * changerait ce que lisent les autres fichiers du décor. Isolation : une transaction par méthode,
 * annulée en `tearDown()`.
 *
 * Lancement : dev/test package=structure file=AffiliationRightAi
 */
class AffiliationRightAiTest extends CommunityFixtureAiTest {

	public function setUp(): void {
		Affiliation::model()->beginTransaction();
	}

	public function tearDown(): void {
		Affiliation::model()->rollBack();
	}

	private function getGroup(string $communityType, array $asked): Structure {

		$eCommunity = new Community([
			'hasSellingDuplicate' => FALSE,
		]);

		foreach(AffiliationAiLib::SHARES as $right) {
			$eCommunity[$right] = \Set::fromArray($asked[$right] ?? []);
		}

		return new Structure([
			'id' => $this->communitySharing,
			'type' => Structure::COMMUNITY,
			'communityType' => $communityType,
			'community' => $eCommunity,
		]);

	}

	private function getFarm(): Structure {
		return $this->getStructure(self::STRUCTURE_SHARED);
	}

	/**
	 * Pose l'état de départ du couple : un état de test, que le Lib refuserait justement de fabriquer.
	 */
	private function placeShares(array $shares): void {

		$values = [];

		foreach(AffiliationAiLib::SHARES as $right) {
			$values[$right] = \Set::fromArray($shares[$right] ?? []);
		}

		Affiliation::model()
			->whereStructureProducer(self::STRUCTURE_SHARED)
			->whereStructureCommunity($this->communitySharing)
			->update($values);

	}

	private function readShares(): array {

		$eAffiliation = Affiliation::model()
			->select(AffiliationAiLib::SHARES)
			->whereStructureProducer(self::STRUCTURE_SHARED)
			->whereStructureCommunity($this->communitySharing)
			->get();

		$shares = [];

		foreach(AffiliationAiLib::SHARES as $right) {
			$shares[$right] = $eAffiliation[$right]->get();
		}

		return $shares;

	}

	/**
	 * À l'entrée, une duplication cochée sans la lecture ne s'écrit pas ; cochée avec, elle s'écrit.
	 */
	public function testEntryDropsCopyWithoutRead(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureGroup = $this->getGroup(Structure::NETWORK, [
			'shareProduction' => [Community::STAFF, Community::MEMBER],
			'shareProductionExport' => [Community::STAFF],
			'shareProductionDuplicate' => [Community::STAFF],
		]);

		$cases = [
			'lecture décochée' => [
				'chosen' => ['shareProduction' => [Community::MEMBER], 'shareProductionExport' => [Community::STAFF], 'shareProductionDuplicate' => [Community::STAFF]],
				'expected' => ['shareProduction' => Community::MEMBER, 'shareProductionExport' => 0, 'shareProductionDuplicate' => 0],
			],
			'lecture cochée' => [
				'chosen' => ['shareProduction' => [Community::STAFF], 'shareProductionExport' => [Community::STAFF], 'shareProductionDuplicate' => [Community::STAFF]],
				'expected' => ['shareProduction' => Community::STAFF, 'shareProductionExport' => Community::STAFF, 'shareProductionDuplicate' => Community::STAFF],
			],
		];

		foreach($cases as $label => ['chosen' => $chosen, 'expected' => $expected]) {

			Affiliation::model()
				->whereStructureProducer(self::STRUCTURE_SHARED)
				->whereStructureCommunity($this->communitySharing)
				->delete();

			$fw = new \FailWatch();

			AffiliationAiLib::create(new Affiliation([
				'user' => new \user\User(['id' => $this->user]),
				'structureProducer' => $this->getFarm(),
				'structureCommunity' => $eStructureGroup,
				'contributesQuotation' => TRUE,
			]), $chosen);

			$this->assertTrue($fw->ok(), $label.' : rattachement accepté ('.json_encode($fw->get()).')');

			$shares = $this->readShares();

			foreach($expected as $right => $value) {
				$this->assertEquals($value, $shares[$right], $label.' : '.$right);
			}

		}

	}

	/**
	 * Retirer la lecture retire la duplication même quand le groupe ne la demande plus.
	 *
	 * ⚠️ Le cas qui compte est le second : le groupe qui cesse de demander est ce qui faisait sauter
	 * la cascade. Le premier le précède pour montrer que le contrôle sait trouver une copie gardée.
	 */
	public function testRemovingReadRemovesCopiesNoLongerAsked(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$start = [
			'shareProduction' => [Community::STAFF, Community::MEMBER],
			'shareProductionExport' => [Community::STAFF],
			'shareProductionDuplicate' => [Community::STAFF],
		];

		$this->placeShares($start);

		$eStructureGroupAsking = $this->getGroup(Structure::NETWORK, $start);

		AffiliationAiLib::setRights($this->getFarm(), $eStructureGroupAsking, ['shareProduction' => [Community::STAFF]]);

		$shares = $this->readShares();

		$this->assertEquals(Community::STAFF, $shares['shareProductionDuplicate'], 'retirer la lecture aux producteurs garde la duplication de l\'équipe');
		$this->assertEquals(Community::STAFF, $shares['shareProductionExport'], 'et la copie de l\'équipe');

		$this->placeShares($start);

		$eStructureGroupStopped = $this->getGroup(Structure::NETWORK, [
			'shareProduction' => [Community::STAFF, Community::MEMBER],
		]);

		AffiliationAiLib::setRights($this->getFarm(), $eStructureGroupStopped, ['shareProduction' => [Community::MEMBER]]);

		$shares = $this->readShares();

		$this->assertEquals(Community::MEMBER, $shares['shareProduction'], 'la lecture de l\'équipe est retirée');
		$this->assertEquals(0, $shares['shareProductionDuplicate'], 'la duplication, plus demandée, part avec elle');
		$this->assertEquals(0, $shares['shareProductionExport'], 'la copie, plus demandée, part avec elle');

		$this->assertFalse(
			CommunityRightAiLib::grants($eStructureGroupAsking, 'shareProductionDuplicate', new \Set($shares['shareProductionDuplicate']), Community::STAFF, [Community::MEMBER]),
			'le groupe qui redemande ne retrouve rien'
		);

	}

	/**
	 * Une copie écrite seule ne passe pas sans la lecture, même si le groupe la demande.
	 */
	public function testCopyWrittenAloneNeedsRead(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->placeShares(['shareProduction' => [Community::MEMBER]]);

		$eStructureGroup = $this->getGroup(Structure::NETWORK, [
			'shareProduction' => [Community::STAFF, Community::MEMBER],
			'shareProductionDuplicate' => [Community::STAFF],
		]);

		AffiliationAiLib::setRights($this->getFarm(), $eStructureGroup, ['shareProductionDuplicate' => [Community::STAFF]]);

		$this->assertEquals(0, $this->readShares()['shareProductionDuplicate'], 'sans la lecture de l\'équipe');

		$this->placeShares(['shareProduction' => [Community::STAFF]]);

		AffiliationAiLib::setRights($this->getFarm(), $eStructureGroup, ['shareProductionDuplicate' => [Community::STAFF]]);

		$this->assertEquals(Community::STAFF, $this->readShares()['shareProductionDuplicate'], 'avec la lecture de l\'équipe');

	}

	/**
	 * En formation la lecture et la duplication sont imposées : la ferme qui rejoint ne poste plus
	 * de case, ses colonnes valent zéro, et l'équipe les reçoit quand même.
	 */
	public function testImposedDuplicationIsGrantedOnZero(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->placeShares([]);

		$eStructureGroup = $this->getGroup(Structure::EDUCATION, []);

		$shares = $this->readShares();

		$this->assertEquals(0, $shares['shareProduction'], 'la colonne de lecture reste à zéro');
		$this->assertEquals(0, $shares['shareProductionDuplicate'], 'la colonne de duplication reste à zéro');
		$this->assertTrue(
			CommunityRightAiLib::grants($eStructureGroup, 'shareProductionDuplicate', new \Set($shares['shareProductionDuplicate']), Community::STAFF, [Community::MEMBER]),
			'la duplication est accordée à l\'équipe'
		);

	}

}
?>
