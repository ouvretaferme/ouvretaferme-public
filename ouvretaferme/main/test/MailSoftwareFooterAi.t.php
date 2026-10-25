<?php
namespace main;

/**
 * Le pied des e-mails du logiciel propose la foire aux questions du site de présentation, et s'en
 * passe quand le site n'est pas installé, comme dans la version publique : l'adresse viendrait sinon
 * d'une page qui n'existe pas.
 *
 * Le package est retiré par `Package::setList()`, remis en place par tearDown(). Aucune écriture en
 * base.
 *
 * Lancement : dev/test package=main file=MailSoftwareFooterAi
 */
class MailSoftwareFooterAiTest extends \Test {

	private array $listInitial = [];

	public function setUp(): void {
		$this->listInitial = \Package::getList();
	}

	public function tearDown(): void {
		\Package::setList($this->listInitial);
	}

	public function testFooterFollowsThePresentationPackage(): void {

		$urlFaq = MailSoftwareAiUi::getFaqUrl();

		$this->assertTrue($urlFaq !== NULL, 'le site de présentation donne sa foire aux questions');

		[, $text, $html] = MailSoftwareAiUi::format('Sujet', '<p>Corps</p>');

		$this->assertTrue(str_contains($text, \Lime::getUrl().$urlFaq), 'le corps texte la propose');
		$this->assertTrue(str_contains($html, \Lime::getUrl().$urlFaq), 'le corps HTML aussi');

		$list = $this->listInitial;
		unset($list['presentation']);
		\Package::setList($list);

		$this->assertEquals(NULL, MailSoftwareAiUi::getFaqUrl(), 'sans le site de présentation, pas de foire aux questions');

		[, $text, $html] = MailSoftwareAiUi::format('Sujet', '<p>Corps</p>');

		$this->assertFalse(str_contains($text, $urlFaq), 'le corps texte ne la propose plus');
		$this->assertFalse(str_contains($html, $urlFaq), 'le corps HTML non plus');
		$this->assertTrue(str_contains($html, \Lime::getUrl().MailSoftwareAiUi::URL_DOC), 'la documentation reste');

	}

}
?>
