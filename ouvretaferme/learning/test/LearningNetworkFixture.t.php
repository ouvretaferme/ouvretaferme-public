<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le décor d'un réseau et d'une ferme rattachée, partagé par les tests de l'ouverture aux membres
 * (`ShareMembers.t.php`) et du suivi par compte (`TrackNetwork.t.php`).
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` avant chaque test :
 * - le réseau G3 du décor commun (`COMMUNITY_NETWORK`), de type `network`, dont `setUp()` fait de
 *   l'utilisateur 1 (`USER_ADMIN`) un conseiller IN : c'est lui qui écrit les formations du réseau
 *   et qui rattache la ferme ;
 * - la ferme F3 du décor (`getFarm()`), sans membre, que `setUp()` fait administrer par
 *   l'utilisateur 1 le temps du test (`getMemberships()` du décor commun) : le rattachement se crée
 *   en son nom par `AffiliationAiLib::create()`, qui l'exige.
 *
 * Tout le reste se pose dans la transaction du test : le lecteur fictif entre dans la ferme, la
 * ferme se rattache au réseau, l'ouverture aux membres s'écrit par le chemin de `doShare` et
 * l'inscription par `EnrollmentLib::create()`.
 */
abstract class LearningNetworkFixtureTest extends LearningFixtureTest {

	const COMMUNITY_NETWORK = \structure\FixtureAiLib::G3;

	protected \structure\Structure $eStructureNetwork;
	protected \structure\Structure $eStructureFarm;

	protected function getMemberships(): array {
		return parent::getMemberships() + [
			self::COMMUNITY_NETWORK => 'advisor',
		];
	}

	protected function assertFixtures(): bool {

		if(parent::assertFixtures() === FALSE) {
			return FALSE;
		}

		$this->eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);

		if($this->eStructureNetwork->empty() or $this->eStructureNetwork->isNetwork() === FALSE) {
			$this->assertFail('le réseau '.self::COMMUNITY_NETWORK.' est absent de cette base, ou n\'est pas un réseau');
			return FALSE;
		}

		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAdmin, $this->eStructureNetwork);

		if(
			$eMember->empty() or
			$eMember['status'] !== \structure\Member::IN or
			$eMember['role']['fqn'] !== 'advisor'
		) {
			$this->assertFail('l\'utilisateur '.self::USER_ADMIN.' n\'est pas conseiller IN du réseau '.self::COMMUNITY_NETWORK.' dans cette base');
			return FALSE;
		}

		$this->eStructureFarm = $this->getFarm();

		if(\structure\MemberLib::isAdmin($this->eUserAdmin, $this->eStructureFarm) === FALSE) {
			$this->assertFail('l\'utilisateur '.self::USER_ADMIN.' n\'administre pas la ferme '.$this->eStructureFarm['id'].', que le rattachement exige');
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Le décor de chaque test : le lecteur fictif membre IN de la ferme, la ferme rattachée au
	 * réseau au nom de l'admin, et une formation publiée du réseau, privée sauf `$catalog`.
	 */
	protected function seed(string $type = Course::ONLINE, string $catalog = Course::PRIVATE): Course {

		$this->actAs($this->eUserAdmin);

		$this->join($this->eUserReader, $this->eStructureFarm);
		$this->affiliate($this->eStructureFarm);

		return $this->createCourse($this->eStructureNetwork, Course::PUBLISHED, $catalog, type: $type);

	}

	/**
	 * Le réglage d'inscription aux formations en ligne du réseau, posé par le panneau des réglages
	 * (`CommunityLib::updateEnrollment()`), qui reçoit aussi les deux autres réglages tels qu'ils sont.
	 */
	protected function setOnlineConfirm(string $onlineConfirm): void {

		$eCommunity = \structure\CommunityLib::getByStructure($this->eStructureNetwork);

		$fw = new \FailWatch();

		CommunityLib::updateEnrollment($this->eStructureNetwork, [
			'learningConfirm' => $eCommunity['learningConfirm'] ? '1' : '0',
			'learningOnlineConfirm' => $onlineConfirm,
			'learningConditions' => $eCommunity['learningConditions'],
		]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('réglage refusé : '.json_encode($this->getFails($fw))));

	}

	protected function leave(\user\User $eUser, \structure\Structure $eStructure): void {

		$eMember = \structure\MemberLib::getByUserAndStructure($eUser, $eStructure);

		$eMember['status'] = \structure\Member::OUT;
		\structure\MemberLib::update($eMember, ['status']);

	}

	/**
	 * La ferme se rattache au réseau du décor, ou au groupe `$eStructureCommunity` quand il est donné.
	 */
	protected function affiliate(\structure\Structure $eStructureFarm, \structure\Structure $eStructureCommunity = new \structure\Structure()): void {

		if($eStructureCommunity->empty()) {
			$eStructureCommunity = $this->eStructureNetwork;
		}

		$fw = new \FailWatch();

		\structure\AffiliationAiLib::create(new \structure\Affiliation([
			'user' => $this->eUserAdmin,
			'structureProducer' => $eStructureFarm,
			'structureCommunity' => $eStructureCommunity,
			'contributesQuotation' => FALSE
		]));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('rattachement de la ferme '.$eStructureFarm['id'].' au groupe '.$eStructureCommunity['id'].' refusé : '.json_encode($this->getFails($fw))));

	}

	/**
	 * Le réseau du décor perd toutes ses fermes (F1 et F2), dans la transaction du test : par
	 * `deleteByStructures()` et non `removeStructure()`, qui l'annoncerait par e-mail au réseau.
	 */
	protected function unaffiliateAll(): void {

		$cAffiliation = \structure\Affiliation::model()
			->select(['structureProducer'])
			->whereStructureCommunity($this->eStructureNetwork)
			->getCollection();

		foreach($cAffiliation as $eAffiliation) {
			\structure\AffiliationAiLib::deleteByStructures($eAffiliation['structureProducer'], $this->eStructureNetwork);
		}

	}

	/**
	 * Le chemin de `doShare` pour un réseau : « Tous les membres du réseau », ou « Personne pour
	 * l'instant » quand `$members` est faux, en chaîne comme le navigateur la poste.
	 */
	protected function shareNetwork(Course $eCourse, bool $members = TRUE): array {

		$fw = new \FailWatch();

		$cShare = ShareLib::buildFromInput($eCourse, [
			'audience' => $members ? Share::MEMBERS : ''
		]);

		if($fw->ok()) {
			ShareLib::synchronizeCourse($eCourse, $cShare);
		}

		return $this->getFails($fw);

	}

	/**
	 * Le lecteur s'inscrit en son nom, sans structure, à une formation en ligne du réseau :
	 * l'ouverture aux membres ne donne pas la lecture, l'inscription la donne.
	 */
	protected function enrollReader(Course $eCourse, \user\User $eUser = new \user\User()): Enrollment {

		if($eUser->empty()) {
			$eUser = $this->eUserReader;
		}

		$eEnrollment = new Enrollment([
			'user' => $eUser,
			'course' => $eCourse,
			'structure' => new \structure\Structure()
		]);

		EnrollmentLib::create($eEnrollment);

		return $eEnrollment;

	}

	/**
	 * Une couleur claire, que `build()` refuse depuis le seuil du blanc de la bande (C1h), posée sur
	 * le réseau comme elle l'était avant lui : c'est l'état d'un réseau enregistré auparavant, que les
	 * pages affichent encore et que la saisie doit laisser passer.
	 */
	protected function storeColor(string $color): void {

		$eCommunity = \structure\CommunityLib::getByStructure($this->eStructureNetwork);
		$eCommunity['color'] = $color;

		// Sans build() : l'étape `contrast` refuserait cette couleur, et le cas est justement celle
		// qu'un réseau porte depuis avant la règle.
		\structure\CommunityLib::update($eCommunity, ['color']);

		$this->forgetMemoization();

	}

	/**
	 * Une autre ferme du réseau que celle du lecteur, prise parmi les rattachements du décor (F1 et F2 au réseau G3).
	 */
	protected function getOtherFarm(): \structure\Structure {

		$eAffiliation = \structure\Affiliation::model()
			->select(['structureProducer' => ['name', 'status']])
			->whereStructureCommunity($this->eStructureNetwork)
			->whereStructureProducer('!=', $this->eStructureFarm)
			->get();

		$this->assertTrue($eAffiliation->notEmpty(), 'le réseau '.self::COMMUNITY_NETWORK.' a d\'autres fermes rattachées');

		return $eAffiliation['structureProducer'];

	}

}
?>
