<?php
namespace learning;

require_once __DIR__.'/LearningTutorialFixture.t.php';

/**
 * La personnalisation du réseau des tutoriels (lot T14) : son accueil et ses formations répondent 404
 * au public, et le sommaire `/tutoriels` ne porte pas son thème. La carte « Personnaliser
 * l'apparence » de son paramétrage ouvre donc la barre sur la fiche de son premier tutoriel publié,
 * dont l'essai ramène à la même fiche ; sans tutoriel publié, la carte ne paraît pas. Un lien en
 * mode vers son accueil ou ses formations, d'avant ce lot, mène son gérant à la même fiche.
 *
 * Le réseau des tutoriels est celui du décor (`LearningTutorialFixtureTest`), où l'utilisateur 1
 * n'est que conseiller : `becomeManager()` l'en fait administrateur dans la transaction du test.
 *
 * Lancement : dev/test package=learning file=CustomizeTutorial
 */
class CustomizeTutorialTest extends LearningTutorialFixtureTest {

	/**
	 * La carte mène à la fiche du premier tutoriel publié, dans l'ordre du sommaire, ni au brouillon
	 * créé avant lui, ni au tutoriel publié après. La fiche répond au gérant avec la barre, et l'essai
	 * d'un réglage y ramène avec les réglages dans l'adresse.
	 */
	public function testCardOpensOnTutorial(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourseDraft = $this->createCourse($this->eStructureTutorial, Course::DRAFT, Course::PRIVATE);
		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);
		$eCourseLater = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);

		$this->becomeManager();

		$data = $this->executeView('structure/structure:update', ['id' => $this->eStructureTutorial['id']]);

		$this->assertTrue($data instanceof \stdClass, 'le gérant ouvre le paramétrage');
		$this->assertTrue(is_array($data->e['customizeSettings']), 'la carte paraît');
		$this->assertEquals($eCourse['id'], $data->e['eCourseCustomize']['id'], 'la carte désigne le premier tutoriel publié, ni le brouillon '.$eCourseDraft['id'].' ni le suivant '.$eCourseLater['id']);

		$url = CourseUi::url($eCourse, ['customize' => 1]);

		$this->assertEquals($url, CustomizeUi::urlCard($this->getTutorialCommunity(), $data->e['eCourseCustomize']), 'la carte ouvre la barre sur sa fiche');
		$this->assertTrue(str_contains(new \structure\StructureUi()->update($data->e), 'href="'.$url.'"'), 'le paramétrage rend ce lien');

		$dataCourse = $this->executeView('formation/'.$eCourse['id'], ['customize' => '1']);

		$this->assertTrue($dataCourse instanceof \stdClass, 'la fiche répond au gérant');
		$this->assertTrue($dataCourse->customize, 'avec la barre');
		$this->assertTrue(is_array($dataCourse->settings), 'et le thème du réseau');

		$settings = ['customDesign' => '', 'color' => '#2f6b4f', 'customBackground' => '', 'customTitleFont' => '', 'customFont' => ''];

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('formation/'.$eCourse['id'].'/:doPreview', $settings, 'POST', $redirect), 'l\'essai d\'un réglage est accepté');
		$this->assertEquals(CourseUi::url($eCourse, CustomizeUi::getQuery($settings)), $redirect, 'et ramène à la même fiche, le réglage dans l\'adresse');
		$this->assertTrue(str_contains((string)$redirect, 'customize=1'), 'la barre toujours ouverte');

	}

	/**
	 * Un lien en mode vers l'accueil ou vers les formations du réseau des tutoriels, comme celui du
	 * signalement, mène son gérant à la fiche. Le visiteur garde son 404 sur les formations.
	 */
	public function testOldLinksLeadToTutorial(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);

		$this->becomeManager();

		$eCommunity = $this->getTutorialCommunity();
		$expected = \Lime::getUrl().CourseUi::url($eCourse, ['customize' => 1]);

		$this->assertEquals($expected, $this->getRedirect('reseau/'.$eCommunity['fqn'], ['customize' => '1']), 'l\'accueil en mode mène à la fiche');

		$query = ['customize' => '1', 'customDesign' => '', 'color' => '', 'customBackground' => '', 'customTitleFont' => '', 'customFont' => ''];

		$this->assertEquals([\RedirectAction::class, NULL], $this->executePage('reseau/'.$eCommunity['fqn'].'/formations', $query, 'GET', $redirect), 'les formations en mode redirigent');
		$this->assertEquals($expected, $redirect, 'vers la fiche');

		$this->actAs(new \user\User());

		$this->assertEquals([\ViewAction::class, NULL], $this->executePage('reseau/'.$eCommunity['fqn'].'/formations', $query, 'GET', $redirect), 'le visiteur reste sur la page d\'erreur');
		$this->assertEquals(NULL, $redirect, 'sans redirection');

	}

	/**
	 * Sans tutoriel publié, aucune page du réseau ne porte son thème : la carte ne paraît pas.
	 */
	public function testNoCardWithoutTutorial(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$this->createCourse($this->eStructureTutorial, Course::DRAFT, Course::PRIVATE);

		$this->becomeManager();

		$this->assertTrue(CommunityLib::hasPublic($this->eStructureTutorial), 'le réseau a des pages publiques, ce n\'est pas ce qui retire la carte');

		$data = $this->executeView('structure/structure:update', ['id' => $this->eStructureTutorial['id']]);

		$this->assertTrue($data instanceof \stdClass, 'le gérant ouvre le paramétrage');
		$this->assertTrue($data->e['eCourseCustomize']->empty(), 'aucun tutoriel publié à désigner');
		$this->assertEquals(NULL, $data->e['customizeSettings'], 'la carte ne paraît pas');

	}

	private function getTutorialCommunity(): \structure\Community {
		return \structure\CommunityLib::getByStructure($this->eStructureTutorial);
	}

	/**
	 * L'utilisateur 1, conseiller du réseau des tutoriels dans le décor, en devient administrateur
	 * dans la transaction du test.
	 */
	private function becomeManager(): void {

		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAdmin, $this->eStructureTutorial);
		$eMember['role'] = \structure\RoleLib::getOne($this->eStructureTutorial, 'admin');

		\structure\MemberLib::update($eMember, ['role']);

		$this->forgetMemoization();
		$this->actAs($this->eUserAdmin);

	}

}
?>
