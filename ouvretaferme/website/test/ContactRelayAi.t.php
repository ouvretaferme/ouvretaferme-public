<?php
namespace website;

/**
 * Le formulaire de contact d'un site de structure ne relaie plus le texte du visiteur.
 *
 * `ContactLib::create()` envoyait deux e-mails : le message à la structure, et un accusé de
 * réception au visiteur, à l'adresse qu'il avait saisie, au nom de la structure, reprenant son
 * objet et son message. Comme rien ne vérifiait l'adresse, n'importe qui faisait partir un texte
 * de son choix vers n'importe quelle boîte sous le nom d'une structure. L'accusé ne reprend plus
 * que le nom de la structure ; le message entier ne part qu'à elle.
 *
 * La structure est celle de référence des tests d'API, lue pour son nom et sa configuration
 * d'e-mail, sans écriture.
 *
 * Lancement : dev/test package=website file=ContactRelayAi
 */
class ContactRelayAiTest extends \Test {

	const TITLE = 'zzTestObjetDuVisiteur42';
	const CONTENT = 'zzTestMessageDuVisiteur42 https://example.org/zzTest';

	private Contact $eContact;

	public function init(): void {

		$eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\dev\ApiTest::STRUCTURE)
			->get();

		if($eStructure->empty()) {
			throw new \Exception('Structure de référence #'.\dev\ApiTest::STRUCTURE.' introuvable');
		}

		$this->eContact = new Contact([
			'structure' => $eStructure,
			'name' => 'zzTest Visiteur',
			'email' => 'zztest.visiteur@example.org',
			'title' => self::TITLE,
			'content' => self::CONTENT
		]);

	}

	public function testTheAcknowledgementCarriesNothingFromTheVisitor(): void {

		[$title, $text, $html] = ContactUi::getUserEmail($this->eContact);

		foreach(['title' => $title, 'text' => $text, 'html' => $html] as $part => $value) {
			$this->assertFalse(str_contains($value, self::TITLE), $part.' : l\'objet du visiteur n\'y est pas');
			$this->assertFalse(str_contains($value, self::CONTENT), $part.' : son message non plus');
		}

		$this->assertTrue(str_contains($text, $this->eContact['structure']['name']), 'le nom de la structure y est');

	}

	public function testTheStructureReceivesTheWholeMessage(): void {

		[$title, $text, $html] = ContactUi::getStructureEmail($this->eContact);

		$this->assertTrue(str_contains($html, self::TITLE), 'l\'objet');
		$this->assertTrue(str_contains($html, encode(self::CONTENT)), 'le message');
		$this->assertTrue(str_contains($html, 'zztest.visiteur@example.org'), 'et l\'adresse pour répondre');

	}

}
?>
