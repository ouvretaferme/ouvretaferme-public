<?php
namespace main;

/**
 * Les gestes de la tête, dans la bande d'un écran de groupe (`DesignUi::getPageMasthead()`).
 *
 * La bande retrouve les gestes en lisant le texte exact que `getPageHeader()` rend autour d'eux : la
 * boîte `util-page-header-aside`, puis un commentaire par emplacement. Une classe ajoutée à la boîte
 * ou un emplacement renommé ferait échouer cette lecture sans aucune erreur, et les gestes resteraient
 * dans la tête au lieu de passer dans la barre. Le test fixe donc l'arbre rendu.
 *
 * Lancement : dev/test package=main file=PageMastheadAi
 */
class PageMastheadAiTest extends \Test {

	const string TITLE = 'Collecte de mars';
	const string TOOLS = '<a class="btn btn-outline-secondary tools-gesture">Envoyer</a>';
	const string CREATE = '<a class="btn btn-secondary create-gesture">Terminer</a>';
	const string SETTINGS = '<a class="btn btn-outline-secondary settings-gesture">Réglages</a>';

	private function getMasthead(string $html): \Dom\Element {
		return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR)->body->firstElementChild;
	}

	public function testGesturesLeaveTheHeader(): void {

		$masthead = $this->getMasthead(DesignUi::getPageMasthead(DesignUi::getPageHeader(self::TITLE, tools: self::TOOLS, create: self::CREATE, settings: self::SETTINGS)));

		$this->assertEquals(0, $masthead->querySelectorAll('.util-page-header .util-page-header-aside')->length, 'la tête garde ses gestes, la bande n\'a pas reconnu la boîte que rend getPageHeader()');
		$this->assertEquals(1, $masthead->querySelectorAll('.util-masthead-actions > .util-masthead-bar')->length, 'les outils et la roue ne passent pas dans la barre');
		$this->assertEquals(1, $masthead->querySelectorAll('.util-masthead-bar > .tools-gesture')->length, 'les outils ne sont pas dans la barre');
		$this->assertEquals(1, $masthead->querySelectorAll('.util-masthead-bar > .settings-gesture')->length, 'la roue n\'est pas dans la barre');
		$this->assertEquals(1, $masthead->querySelectorAll('.util-masthead-actions > .util-masthead-bar + .create-gesture')->length, 'la création ne suit pas la barre');
		$this->assertEquals(1, $masthead->querySelectorAll('.util-page-header h1')->length, 'le titre ne reste pas dans la tête');

	}

	public function testCreateAloneHasNoBar(): void {

		$masthead = $this->getMasthead(DesignUi::getPageMasthead(DesignUi::getPageHeader(self::TITLE, create: self::CREATE)));

		$this->assertEquals(0, $masthead->querySelectorAll('.util-page-header .util-page-header-aside')->length, 'la tête garde la création');
		$this->assertEquals(0, $masthead->querySelectorAll('.util-masthead-bar')->length, 'une barre s\'ouvre sans outil ni roue');
		$this->assertEquals(1, $masthead->querySelectorAll('.util-masthead-actions > .create-gesture')->length, 'la création n\'est pas dans la boîte des gestes');

	}

	public function testHeaderWithoutGestures(): void {

		$masthead = $this->getMasthead(DesignUi::getPageMasthead(DesignUi::getPageHeader(self::TITLE)));

		$this->assertEquals(0, $masthead->querySelectorAll('.util-masthead-actions')->length, 'une boîte de gestes s\'ouvre sans geste');

	}

}
?>
