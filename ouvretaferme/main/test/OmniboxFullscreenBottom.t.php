<?php
namespace main;

/**
 * Le plein écran d'un champ posé en bas (`OmniboxUi::get(fullscreenBottom: TRUE)`) : l'attribut que lit
 * `UtilOmnibox.isFullscreen()` pour suivre la largeur au lieu du doigt, et la flèche qui en sort.
 */
class OmniboxFullscreenBottomTest extends \Test {

	public function testAttribute() {

		$h = OmniboxUi::get(fullscreenBottom: TRUE);

		$this->assertTrue(str_contains($h, ' data-fullscreen="bottom"'), 'le champ ne part pas en plein écran du bas');
		$this->assertTrue(str_contains($h, 'class="util-omnibox-back"'), 'le plein écran du bas n\'a pas de flèche pour en sortir');

	}

	public function testDefault() {

		$h = OmniboxUi::get();

		$this->assertFalse(str_contains($h, 'data-fullscreen'), 'sans l\'option, le plein écran suit le doigt');
		$this->assertFalse(str_contains($h, 'util-omnibox-back'), 'sans l\'option, la loupe reste seule');

	}

	public function testInlineRefused() {

		try {
			OmniboxUi::get(fullscreen: FALSE, fullscreenBottom: TRUE);
			$this->assertTrue(FALSE, 'un champ en place est accepté en plein écran du bas');
		} catch(\Exception $e) {
			$this->assertEquals('fullscreenBottom expects fullscreen', $e->getMessage(), 'le champ en place est refusé pour une autre raison');
		}

	}

}
?>
