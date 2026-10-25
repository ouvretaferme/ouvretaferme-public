<?php
namespace editor;

/**
 * Les figures de citation et de grille, que l'éditeur peut poster vides : une citation glissée dans
 * une autre en sort sans contenu, et le nettoyage doit la retirer au lieu de lever.
 *
 * Lancement : dev/test package=editor file=XmlFigure
 */
class XmlFigureTest extends \Test {

	private function toXml(string $html): ?string {
		return new \editor\XmlLib()->fromHtml($html, ['acceptFigure' => TRUE]);
	}

	private function figure(string $media): string {
		return '<figure data-interactive="false" contenteditable="false" data-widget="figure" data-caption="true">'.$media.'<span contenteditable="false"></span></figure>';
	}

	private function quote(string $content): string {
		return '<div class="editor-media" data-type="quote" data-about="quote">'.$content.'</div>';
	}

	public function testEmptyFiguresAreRemoved(): void {

		$this->assertEquals(NULL, $this->toXml($this->figure('<div class="editor-media" data-type="quote"></div>')), 'citation vide');
		$this->assertEquals(NULL, $this->toXml($this->figure('<div class="editor-media" data-type="grid"></div>')), 'grille vide');

	}

	public function testEmptyQuoteInsideQuoteIsRemoved(): void {

		$inner = '<figure data-widget="figure" data-caption="true" data-interactive="false"><div class="editor-media" data-type="quote" data-about="quote"></div></figure>';
		$html = $this->figure($this->quote('<div class="editor-quote"><div class="editor-quote-image"></div><blockquote contenteditable="true"><p>Texte</p>'.$inner.'</blockquote></div>')).'<p>Après</p>';

		$this->assertEquals('<main><figure interactive="false"><quote about="quote"><p>Texte</p></quote></figure><p>Après</p></main>', $this->toXml($html));

	}

	public function testWhitespaceAroundFigureContentIsIgnored(): void {

		$expectedQuote = '<main><figure interactive="false"><quote about="quote"><p>Texte</p></quote></figure></main>';
		$this->assertEquals($expectedQuote, $this->toXml($this->figure($this->quote('<div class="editor-quote"><div class="editor-quote-image"></div><blockquote contenteditable="true"><p>Texte</p></blockquote></div>'))), 'citation');
		$this->assertEquals($expectedQuote, $this->toXml($this->figure($this->quote("<div class=\"editor-quote\">\n <div class=\"editor-quote-image\"></div>\n<blockquote contenteditable=\"true\"><p>Texte</p></blockquote>\n</div>"))), 'citation espacée');

		$expectedGrid = '<main><figure interactive="false"><grid columns="2"><cell><p>A</p></cell><cell><p>B</p></cell></grid></figure></main>';
		$this->assertEquals($expectedGrid, $this->toXml($this->figure('<div class="editor-media" data-type="grid"><div class="editor-grid" data-columns="2"><div contenteditable="true"><p>A</p></div><div contenteditable="true"><p>B</p></div></div></div>')), 'grille');
		$this->assertEquals($expectedGrid, $this->toXml($this->figure("<div class=\"editor-media\" data-type=\"grid\"><div class=\"editor-grid\" data-columns=\"2\">\n <div contenteditable=\"true\"><p>A</p></div>\n <div contenteditable=\"true\"><p>B</p></div>\n</div></div>")), 'grille espacée');

	}

}
?>
