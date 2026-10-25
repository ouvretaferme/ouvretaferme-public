<?php
namespace structure;

/**
 * Le bouton d'une astuce mène là où l'astuce le dit, pas là où l'URL le demande.
 *
 * `/structure/tip:click` redirigeait vers son paramètre redirect tel quel : un lien portant un
 * identifiant d'astuce valide déposait l'utilisateur sur n'importe quel site. La cible vit
 * désormais dans `TipLib::getLink()`, et le lien du bouton ne porte plus que l'identifiant.
 *
 * Lancement : dev/test package=structure file=TipLinkAi
 */
class TipLinkAiTest extends \Test {

	public function testEveryLinkStaysOnTheSiteOrIsTheKnownExternalOne(): void {

		foreach(TipLib::getList() as $tip) {

			$link = TipLib::getLink($tip);

			if($link === NULL) {
				continue;
			}

			$this->assertTrue(
				(\Lime::isSameSite($link) or $link === \main\MainSetting::URL_PHOTOS),
				$tip.' mène sur le site ou vers les photos libres de droit : '.$link
			);

		}

	}

	public function testTheKnownLinks(): void {

		$this->assertEquals('/doc/selling:wallet', TipLib::getLink('selling-wallet'));
		$this->assertEquals('/doc/accounting:bank#cashflow-manage', TipLib::getLink('accounting-cashflow-attach'));
		$this->assertEquals(\main\MainSetting::URL_PHOTOS, TipLib::getLink('selling-photo'));
		$this->assertEquals(NULL, TipLib::getLink('sequence-weeks'), 'une astuce sans bouton n\'a pas de lien');
		$this->assertEquals(NULL, TipLib::getLink('zz-inconnue'), 'un identifiant inconnu non plus');

	}

}
?>
