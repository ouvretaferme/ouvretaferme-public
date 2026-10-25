<?php
namespace structure;

/**
 * Le menu de la personne sur un écran de groupe : un lecteur qui n'est pas membre n'en a pas.
 * Ce qu'il montre aux membres se regarde dans Chrome.
 *
 * Lancement : dev/test package=structure file=CommunityMenuAi
 */
class CommunityMenuAiTest extends CommunityFixtureAiTest {

	public function setUp(): void {

		$this->forgetMemoized();

		if($this->hasDecor()) {
			\user\ConnectionLib::setOnline(\user\User::model()->select(\user\User::getSelection())->whereId($this->user)->get());
		}

	}

	public function tearDown(): void {
		$this->forgetMemoized();
	}

	public function testNonMember(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructure = $this->getCommunity(TRUE);

		\user\ConnectionLib::setOnline(new \user\User());
		$this->forgetMemoized();

		$this->assertTrue($eStructure->getMember()->empty(), 'le lecteur déconnecté n\'est pas membre');

		$h = new StructureCommunityUi()->getActionsMember($eStructure, new Member(), $this->getStructures(2), $this->getSections([Section::OPEN]));

		$this->assertEquals('', $h, 'un lecteur qui n\'est pas membre n\'a pas de menu');

	}

	/**
	 * Le groupe partageant du décor, que le lecteur principal administre. La fonctionnalité des
	 * sous-groupes est posée en mémoire pour que chaque test choisisse si l'administration paraît.
	 */
	private function getCommunity(bool $hasCommunitySections): Structure {

		$eStructure = $this->getStructure($this->communitySharing);
		$eStructure->conf(Feature::class)['hasCommunitySections'] = $hasCommunitySections;

		$this->assertTrue($eStructure->canManage(), 'le lecteur principal administre le groupe du décor');

		return $eStructure;

	}

	private function getStructures(int $number): \Collection {

		$cStructure = new \Collection();

		foreach(range(1, $number) as $position) {
			$cStructure[$position] = new Structure(['id' => $position, 'name' => 'Ferme du menu '.$position]);
		}

		return $cStructure;

	}

	private function getSections(array $statuses): \Collection {

		$cSection = new \Collection();

		foreach($statuses as $index => $status) {
			$position = $index + 1;
			$cSection[$position] = new Section(['id' => $position, 'name' => 'Sous-groupe du menu '.$position, 'status' => $status]);
		}

		return $cSection;

	}

}
?>
