<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le décor des tutoriels de Ouvretaferme, partagé par les tests de leur lecture et de leur sommaire.
 *
 * `LearningSetting::$structureTutorial` désigne un réseau de production que la copie de dev ne porte
 * qu'à partir du rechargement qui suit sa création, et dont elle reprend le contenu réel : un test
 * qui le lirait dépendrait de ce que l'équipe y publie. Chaque méthode y pose donc le réseau G7 du
 * décor commun (`COMMUNITY_TUTORIAL`), présent dans `LearningSetting::STRUCTURE_BETA` en dev, et
 * `tearDown()` rend la valeur de départ.
 *
 * Ce que le fichier attend de la base, relu par `assertFixtures()` avant chaque test :
 * - le réseau G7, de type `network`, semé par `structure/configure/fixtureAi write=1` ;
 * - l'utilisateur 1 (`USER_ADMIN`), dont `setUp()` fait un conseiller IN du réseau : c'est lui qui
 *   écrit les tutoriels.
 *
 * Les tutoriels eux-mêmes s'écrivent dans la transaction du test : G7 n'en porte aucun pour de bon,
 * ce qui distingue un réseau du décor de la copie du réseau de production.
 */
abstract class LearningTutorialFixtureTest extends LearningFixtureTest {

	const COMMUNITY_TUTORIAL = \structure\FixtureAiLib::G7;

	protected \structure\Structure $eStructureTutorial;

	private ?int $structureTutorialInitial;

	protected function getMemberships(): array {
		return parent::getMemberships() + [
			self::COMMUNITY_TUTORIAL => 'advisor',
		];
	}

	public function setUp(): void {

		parent::setUp();

		$this->structureTutorialInitial = LearningSetting::$structureTutorial;

		LearningSetting::$structureTutorial = self::COMMUNITY_TUTORIAL;

	}

	public function tearDown(): void {

		LearningSetting::$structureTutorial = $this->structureTutorialInitial;

		parent::tearDown();

	}

	protected function assertFixtures(): bool {

		if(parent::assertFixtures() === FALSE) {
			return FALSE;
		}

		$this->eStructureTutorial = $this->getCommunity(self::COMMUNITY_TUTORIAL);

		if($this->eStructureTutorial->empty() or $this->eStructureTutorial->isNetwork() === FALSE) {
			$this->assertFail('le réseau des tutoriels '.self::COMMUNITY_TUTORIAL.' est absent de cette base, ou n\'est pas un réseau : structure/configure/fixtureAi write=1 à jouer');
			return FALSE;
		}

		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAdmin, $this->eStructureTutorial);

		if(
			$eMember->empty() or
			$eMember['status'] !== \structure\Member::IN or
			$eMember['role']['fqn'] !== 'advisor'
		) {
			$this->assertFail('l\'utilisateur '.self::USER_ADMIN.' n\'est pas conseiller IN du réseau des tutoriels '.self::COMMUNITY_TUTORIAL.' dans cette base');
			return FALSE;
		}

		return TRUE;

	}

}
?>
