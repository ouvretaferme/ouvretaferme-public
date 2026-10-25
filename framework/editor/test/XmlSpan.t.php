<?php
namespace editor;

/**
 * La taille du texte et le surlignage, que l'éditeur pose en data-size et data-highlight sur un span.
 *
 * Le span est le seul nœud de texte qui garde des attributs en dehors du lien, et ce qu'il garde
 * s'affiche sur la boutique publique : chaque refus est donc doublé de la forme admise
 * correspondante, sinon un nettoyage qui jetterait tout passerait tous les cas de refus.
 *
 * Lancement : dev/test package=editor file=XmlSpan
 */
class XmlSpanTest extends \Test {

	private function toXml(string $html): string {
		return (string)new \editor\XmlLib()->fromHtml($html);
	}

	private function toReador(string $xml): string {
		return new \editor\ReadorFormatterUi()->getFromXml($xml);
	}

	private function toEditor(string $xml): string {
		return new \editor\EditorFormatterUi()->getFromXml($xml);
	}

	private function toEmail(string $xml): string {
		return new \editor\ReadorFormatterUi()->getFromXml($xml, ['isEmail' => TRUE]);
	}

	public function testFreeAttributesAreRefused(): void {

		$attributes = [
			'class util-mark' => 'class="util-mark"',
			'class libre' => 'class="x"',
			'onclick' => 'onclick="alert(1)"',
			'id' => 'id="x"',
			'data-foo' => 'data-foo="x"',
			'size posté en direct' => 'size="small"',
			'highlight posté en direct' => 'highlight="true"',
			'data-size=huge' => 'data-size="huge"',
			'data-size vide' => 'data-size=""',
			'data-size=SMALL' => 'data-size="SMALL"',
			'data-size=normal' => 'data-size="normal"',
			'data-highlight=false' => 'data-highlight="false"',
			'data-highlight=1' => 'data-highlight="1"',
			'data-highlight=TRUE' => 'data-highlight="TRUE"',
		];

		foreach($attributes as $case => $attribute) {
			$this->assertEquals('<main><p><span>texte</span></p></main>', $this->toXml('<p><span '.$attribute.'>texte</span></p>'), $case.' sur span');
		}

		$this->assertEquals('<main><p><span style="color: red;">texte</span></p></main>', $this->toXml('<p><span style="font-size: 40px; color: red">texte</span></p>'), 'font-size mêlé à une couleur sur span');
		$this->assertEquals('<main><p><span>texte</span></p></main>', $this->toXml('<p><span style="background: red">texte</span></p>'), 'background sur span');

	}

	public function testAdmittedValuesAreKept(): void {

		$this->assertEquals('<main><p><span size="small">texte</span></p></main>', $this->toXml('<p><span data-size="small">texte</span></p>'), 'data-size=small dans un paragraphe');
		$this->assertEquals('<main><p><span size="large">texte</span></p></main>', $this->toXml('<p><span data-size="large">texte</span></p>'), 'data-size=large dans un paragraphe');
		$this->assertEquals('<main><p><span highlight="true">texte</span></p></main>', $this->toXml('<p><span data-highlight="true">texte</span></p>'), 'data-highlight=true dans un paragraphe');
		$this->assertEquals('<main><p><span size="large" highlight="true">texte</span></p></main>', $this->toXml('<p><span data-size="large" data-highlight="true">texte</span></p>'), 'les deux sur un même span');
		$this->assertEquals('<main><ul><li><span size="small" highlight="true">texte</span></li></ul></main>', $this->toXml('<ul><li><span data-size="small" data-highlight="true">texte</span></li></ul>'), 'les deux dans une liste');

	}

	public function testSizeIsRemovedInHeader(): void {

		$this->assertEquals('<main><header size="0"><span highlight="true">titre</span></header></main>', $this->toXml('<h2><span data-size="large" data-highlight="true">titre</span></h2>'), 'taille dans un titre, surlignage gardé');
		$this->assertEquals('<main><header size="2"><span>titre</span></header></main>', $this->toXml('<h4><span data-size="small">titre</span></h4>'), 'taille seule dans un sous-titre');

	}

	public function testAttributesOnAnotherNodeAreRemoved(): void {

		$this->assertEquals('<main><p><b>texte</b></p></main>', $this->toXml('<p><b data-size="small">texte</b></p>'), 'data-size sur b');
		$this->assertEquals('<main><p>texte</p></main>', $this->toXml('<p data-highlight="true">texte</p>'), 'data-highlight sur p');

	}

	public function testNestedSpansAreKept(): void {

		$xml = $this->toXml('<p><span data-size="small">a <span data-size="large">b</span></span></p>');

		$this->assertEquals('<main><p><span size="small">a <span size="large">b</span></span></p></main>', $xml, 'deux span imbriqués dans le XML');
		$this->assertEquals('<p><span data-size="small">a <span data-size="large">b</span></span></p>', $this->toReador($xml), 'deux span imbriqués au rendu');

	}

	public function testRenderings(): void {

		$xml = $this->toXml('<p><span data-size="large" data-highlight="true" style="color: rgb(255, 0, 0)">texte</span></p>');

		$this->assertEquals('<p><span style="color: rgb(255, 0, 0);" data-size="large" class="util-mark">texte</span></p>', $this->toReador($xml), 'rendu lecteur');
		$this->assertEquals('<p><span style="color: rgb(255, 0, 0);" data-size="large" class="util-mark" data-highlight="true">texte</span></p>', $this->toEditor($xml), 'rendu éditeur');
		$this->assertEquals('<p><span style="color: rgb(255, 0, 0);font-size: 125%;background-color: '.FormatterUi::EMAIL_HIGHLIGHT.';">texte</span></p>', $this->toEmail($xml), 'rendu e-mail fusionné avec la couleur');
		$this->assertEquals('<p><span style="font-size: 90%;">texte</span></p>', $this->toEmail($this->toXml('<p><span data-size="small">texte</span></p>')), 'petit texte en e-mail');

	}

	public function testEditorRoundTrip(): void {

		$cases = [
			'taille' => '<p>a <span data-size="small">b</span> c</p>',
			'surlignage et couleur' => '<p><span data-size="large" data-highlight="true" style="color: rgb(255, 0, 0)">b</span></p>',
			'titre surligné' => '<h3><span data-highlight="true">t</span></h3>',
			'span imbriqués' => '<p><span data-size="small">a <span data-highlight="true">b</span></span></p>',
		];

		foreach($cases as $case => $html) {
			$xml = $this->toXml($html);
			$this->assertEquals($xml, $this->toXml($this->toEditor($xml)), 'réédition sans perte, '.$case);
		}

	}

	public function testEmailPlainText(): void {

		$html = $this->toEmail($this->toXml('<p>Un <span data-size="large" data-highlight="true">mot</span> fort</p>'));

		// La version texte d'un e-mail est le HTML sans ses balises (ouvretaferme/main/ui/MailStructure.u.php, toText())
		$this->assertEquals('Un mot fort', trim(strip_tags($html)), 'texte brut d\'un e-mail');

	}

	public function testPastedMarkWithoutHighlight(): void {

		$xml = $this->toXml('<p><span class="util-mark">collé</span></p>');

		$this->assertFalse(str_contains($xml, 'highlight'), 'util-mark collé sans data-highlight ne devient pas un surlignage');
		$this->assertFalse(str_contains($this->toReador($xml), 'util-mark'), 'util-mark collé sans data-highlight ne se rend pas');

	}

}
?>
