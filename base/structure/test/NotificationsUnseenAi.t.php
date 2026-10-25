<?php
namespace structure;

/**
 * MemberLib::getUnseenByStructure() rend à chaque structure le compteur de son adhésion, et lui seul :
 * les notifications sont segmentées par structure (décidé le 2026-09-20), celles d'un
 * groupe ne s'ajoutent plus à la cloche des structures qui y sont rattachées.
 *
 * Deux choses à prouver :
 * - le compteur d'un groupe qui a des non vues ne s'ajoute pas à celui de la structure rattachée ;
 * - les rattachements ne sont plus lus, sur aucune page. La preuve est la mémoïsation de
 *   AffiliationAiLib : fillCommunitiesByStructures() y pose une clé par structure dès qu'elle est
 *   appelée, même sans aucun rattachement.
 *
 * Aucune écriture en base. Les adhésions de l'utilisateur et les rattachements sont injectés dans
 * les mémoïsations de MemberLib et d'AffiliationAiLib par réflexion, pour un utilisateur fictif.
 *
 * Lancement : dev/test package=structure file=NotificationsUnseenAi
 */
class NotificationsUnseenAiTest extends \Test {

	const PRODUCER = 999990001;
	const COMMUNITY = 999990002;
	const COMMUNITY_OTHER = 999990003;

	private int $userId = 930000;

	private \user\User $eUserInitial;
	private array $cMemberOnlineInitial;
	private array $cCommunityByStructureInitial;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->cMemberOnlineInitial = self::getMemberCache()->getValue();
		$this->cCommunityByStructureInitial = self::getAffiliationCache()->getValue();

	}

	/** Les mémoïsations sont des propriétés de processus : rien ne les annule entre deux tests. */
	public function finalize(): void {

		self::getMemberCache()->setValue(NULL, $this->cMemberOnlineInitial);
		self::getAffiliationCache()->setValue(NULL, $this->cCommunityByStructureInitial);

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function testCommunityUnseenIsNotAdded(): void {

		$eUser = $this->connect([self::PRODUCER => 2, self::COMMUNITY => 3, self::COMMUNITY_OTHER => 0]);

		// Le rattachement est connu de la mémoïsation : seule la règle empêche l'addition.
		$this->setCommunities($eUser, self::PRODUCER, [self::COMMUNITY]);

		$unseen = MemberLib::getUnseenByStructure();

		$this->assertEquals([self::PRODUCER => 2, self::COMMUNITY => 3, self::COMMUNITY_OTHER => 0], $unseen, 'chaque structure garde le compteur de son adhésion, les 3 non vues du groupe restent au groupe');

	}

	public function testAffiliationsAreNotRead(): void {

		$eUser = $this->connect([self::PRODUCER => 2, self::COMMUNITY => 3, self::COMMUNITY_OTHER => 0]);

		MemberLib::getUnseenByStructure();

		$this->assertFalse($this->hasReadAffiliations($eUser), 'un groupe a des non vues, et les rattachements ne sont pas lus pour autant');

	}

	/** Une structure dont l'utilisateur n'est pas membre actif n'a pas d'adhésion en ligne, donc pas de compteur. */
	public function testOnlyMembershipsHaveACounter(): void {

		$this->connect([self::PRODUCER => 2]);

		$this->assertEquals([self::PRODUCER => 2], MemberLib::getUnseenByStructure());

	}

	/** @param array $unseen [id de structure => non vues de l'adhésion] */
	private function connect(array $unseen): \user\User {

		$eUser = new \user\User(['id' => $this->userId++]);
		\user\ConnectionLib::setOnline($eUser);

		$cMember = new \Collection();

		foreach($unseen as $structure => $notificationsUnseen) {
			$cMember[$structure] = new Member([
				'structure' => new Structure(['id' => $structure]),
				'notificationsUnseen' => $notificationsUnseen,
			]);
		}

		$property = self::getMemberCache();
		$property->setValue(NULL, $property->getValue() + [$eUser['id'] => $cMember]);

		return $eUser;

	}

	private function setCommunities(\user\User $eUser, int $producer, array $communities): void {

		$cStructureCommunity = new \Collection();

		foreach($communities as $community) {
			$cStructureCommunity[$community] = new Structure(['id' => $community, 'type' => Structure::COMMUNITY]);
		}

		$property = self::getAffiliationCache();
		$property->setValue(NULL, $property->getValue() + [$producer.'-'.$eUser['id'] => $cStructureCommunity]);

	}

	private function hasReadAffiliations(\user\User $eUser): bool {
		return array_key_exists(self::PRODUCER.'-'.$eUser['id'], self::getAffiliationCache()->getValue());
	}

	private static function getMemberCache(): \ReflectionProperty {
		return new \ReflectionProperty(MemberLib::class, 'cMemberOnline');
	}

	private static function getAffiliationCache(): \ReflectionProperty {
		return new \ReflectionProperty(AffiliationAiLib::class, 'cCommunityByStructure');
	}

}
?>
