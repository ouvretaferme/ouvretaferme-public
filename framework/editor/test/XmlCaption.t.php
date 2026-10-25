<?php
namespace editor;

/**
 * En photo seule (`figureOnlyImage`), la légende d'une figure est retirée à l'écriture, sauf avec
 * `figureCaption`, qui n'a d'effet que dans ce mode.
 *
 * Aucune écriture en base.
 *
 * Lancement : dev/test package=editor file=XmlCaption
 */
class XmlCaptionTest extends \Test {

	private const IMAGE = '<div data-type="image" data-w="100" data-h="80" data-url="https://media.exemple.org/photo.jpg" data-color="#ffffff"></div>';
	private const VIDEO = '<div data-type="video" data-w="100" data-h="80" data-url="https://media.exemple.org/video" data-source="youtube"></div>';

	private const IMAGE_XML = '<image url="https://media.exemple.org/photo.jpg" color="#ffffff" width="100" height="80"/>';

	private function toXml(string $html, array $options): ?string {
		return new \editor\XmlLib()->fromHtml($html, ['acceptFigure' => TRUE] + $options);
	}

	private function figure(string $content): string {
		return '<p>Avant.</p><figure data-interactive="true">'.$content.'</figure><p>Après.</p>';
	}

	private function expected(string $figure): string {
		return '<main><p>Avant.</p><figure interactive="true">'.$figure.'</figure><p>Après.</p></main>';
	}

	public function testOnlyImageRemovesCaption(): void {

		$this->assertEquals(
			$this->expected(self::IMAGE_XML),
			$this->toXml($this->figure(self::IMAGE.'<figcaption>Une légende</figcaption>'), ['figureOnlyImage' => TRUE]),
			'photo seule, sans figureCaption : la légende est retirée'
		);

	}

	public function testCaptionIsKept(): void {

		$options = ['figureOnlyImage' => TRUE, 'figureCaption' => TRUE];

		$this->assertEquals(
			$this->expected(self::IMAGE_XML.'<figcaption>Une légende</figcaption>'),
			$this->toXml($this->figure(self::IMAGE.'<figcaption>Une légende</figcaption>'), $options),
			'la légende reste, en dernier enfant de la figure'
		);

		$this->assertEquals(
			$this->expected(self::IMAGE_XML.'<figcaption>Une légende</figcaption>'),
			$this->toXml($this->figure(self::IMAGE.self::VIDEO.'<figcaption>Une légende</figcaption>'), $options),
			'la vidéo reste retirée, la légende reste'
		);

	}

	public function testEmptyCaptionIsLikeNoCaption(): void {

		$options = ['figureOnlyImage' => TRUE, 'figureCaption' => TRUE];
		$withoutCaption = $this->toXml($this->figure(self::IMAGE), $options);

		$this->assertEquals($this->expected(self::IMAGE_XML), $withoutCaption, 'la photo sans légende');
		$this->assertEquals($withoutCaption, $this->toXml($this->figure(self::IMAGE.'<figcaption></figcaption>'), $options), 'une légende vide');
		$this->assertEquals($withoutCaption, $this->toXml($this->figure(self::IMAGE."<figcaption>\u{a0}</figcaption>"), $options), 'une légende faite d\'un espace insécable');

	}

	public function testCaptionWithoutOnlyImage(): void {

		$html = $this->figure(self::IMAGE.'<figcaption>Une légende</figcaption>');

		$this->assertEquals(
			$this->toXml($html, []),
			$this->toXml($html, ['figureCaption' => TRUE]),
			'sans figureOnlyImage, figureCaption ne change rien'
		);

		$this->assertTrue(str_contains($this->toXml($html, []), '<figcaption>Une légende</figcaption>'), 'et la légende y est gardée, comme aujourd\'hui');

	}

}
?>
