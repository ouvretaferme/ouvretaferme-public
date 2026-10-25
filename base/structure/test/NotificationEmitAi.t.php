<?php
namespace structure;

/**
 * Les points d'émission des notifications du package : qui les pose, à quelle structure, et surtout
 * quand ils se taisent.
 *
 * Ce qui se contrôle ici n'est pas le traitement (il est éprouvé dans `base/notification/test`), mais
 * le geste : un rattachement neuf s'annonce une fois quel que soit le nombre d'associés, un retrait
 * qui ne retire rien ne s'annonce pas, un départ de personne n'annonce pas en plus le départ de sa
 * ferme (plan du lot 4, point 8).
 *
 * L'émission s'observe à son entrée : `NotificationSetting::$enabled` est la première chose que lit
 * `emit()`, et il reçoit la structure destinataire. Le remplacer par un relevé qui répond non compte
 * les émissions sans rien déposer dans la file, et la déduplication de la file (`QueueLib::push()`
 * fait un upsert) ne peut donc pas masquer une émission en trop.
 *
 * Décor : `CommunityFixtureAiTest`. Isolation : une transaction par méthode, annulée en
 * `tearDown()`. Les e-mails des retraits partent vers les adresses légales du décor, qu'en
 * développement `SendLib` n'envoie qu'à sa liste blanche.
 *
 * Lancement : dev/test package=structure file=NotificationEmitAi
 */
class NotificationEmitAiTest extends CommunityFixtureAiTest {

	/** Les structures destinataires, une entrée par émission */
	private array $emitted = [];

	private \Closure $enabledInitial;

	public function setUp(): void {

		$this->emitted = [];
		$this->enabledInitial = \notification\NotificationSetting::$enabled;

		\notification\NotificationSetting::$enabled = function(Structure $eStructure): bool {
			$this->emitted[] = $eStructure['id'];
			return FALSE;
		};

		Affiliation::model()->beginTransaction();

	}

	public function tearDown(): void {

		Affiliation::model()->rollBack();

		\notification\NotificationSetting::$enabled = $this->enabledInitial;

	}

	/**
	 * Une ferme rattachée par un premier associé s'annonce au groupe ; le second associé qui la
	 * rattache à son tour ne l'annonce pas une seconde fois.
	 */
	public function testProducerJoinedOncePerCouple(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureProducer = $this->getStructure($this->structureOtherAssociate);
		$eStructureCommunity = $this->getStructure($this->communitySharing);

		Affiliation::model()
			->whereStructureProducer($eStructureProducer)
			->whereStructureCommunity($eStructureCommunity)
			->delete();

		foreach([$this->user, $this->userOther] as $user) {

			$fw = new \FailWatch();

			AffiliationAiLib::create(new Affiliation([
				'user' => new \user\User(['id' => $user]),
				'structureProducer' => $eStructureProducer,
				'structureCommunity' => $eStructureCommunity,
				'contributesQuotation' => TRUE
			]));

			$this->assertTrue($fw->ok(), 'rattachement par l\'utilisateur '.$user.' accepté ('.json_encode($fw->get()).')');

		}

		$this->assertEquals(2, Affiliation::model()->whereStructureProducer($eStructureProducer)->whereStructureCommunity($eStructureCommunity)->count(), 'les deux associés ont chacun leur rattachement');
		$this->assertEquals([$eStructureCommunity['id']], $this->emitted, 'une seule annonce, au groupe');

	}

	/**
	 * Une ferme qui se retire s'annonce au groupe, une fois ; un second retrait, qui ne trouve plus
	 * rien, se tait. Le retrait par le groupe s'annonce à la ferme, sous la même garde.
	 */
	public function testRemovalsEmitOnlyWhatTheyRemove(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureProducer = $this->getStructure(self::STRUCTURE_SHARED);
		$eStructureCommunity = $this->getStructure($this->communitySharing);
		$eStructureCommunityBis = $this->getStructure($this->communitySharingBis);

		AffiliationAiLib::removeStructure($eStructureProducer, $eStructureCommunity);

		$this->assertEquals([$eStructureCommunity['id']], $this->emitted, 'la ferme qui se retire s\'annonce au groupe');

		AffiliationAiLib::removeStructure($eStructureProducer, $eStructureCommunity);
		AffiliationAiLib::excludeStructure($eStructureProducer, $eStructureCommunity);

		$this->assertEquals([$eStructureCommunity['id']], $this->emitted, 'un retrait qui ne retire rien se tait, par la ferme comme par le groupe');

		$this->emitted = [];

		AffiliationAiLib::excludeStructure($eStructureProducer, $eStructureCommunityBis);

		$this->assertEquals([$eStructureProducer['id']], $this->emitted, 'la ferme retirée par un groupe le reçoit');

	}

	/**
	 * Une personne qui quitte le groupe s'annonce au groupe, et à lui seul : le départ de la ferme
	 * qu'elle y avait apportée n'est pas annoncé en plus, ce serait la même nouvelle deux fois.
	 */
	public function testMemberLeftAlone(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eMember = Member::model()
			->select(['id', 'user', 'structure', 'role' => ['admin']])
			->whereUser($this->userOther)
			->whereStructure($this->communitySharing)
			->get();

		if($eMember->empty()) {
			$this->assertFail('le second lecteur n\'est pas membre du groupe partageant');
			return;
		}

		$this->assertTrue(Affiliation::model()->whereUser($this->userOther)->whereStructureCommunity($this->communitySharing)->exists(), 'le second lecteur a apporté une ferme au groupe');

		$fw = new \FailWatch();

		MemberLib::leave($eMember);

		$this->assertTrue($fw->ok(), 'départ accepté ('.json_encode($fw->get()).')');
		$this->assertEquals([$this->communitySharing], $this->emitted, 'une seule annonce, celle du départ de la personne');

	}

}
?>
