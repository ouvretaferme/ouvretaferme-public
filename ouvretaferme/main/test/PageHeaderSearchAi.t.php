<?php
namespace main;

/**
 * La recherche d'un écran dans son en-tête (`DesignUi::getPageHeader()`, argument `search`, chantier
 * ds-compta, lot 14).
 *
 * La chaîne vide n'ouvre aucune boîte : l'en-tête reste celui que la méthode rend sans l'argument.
 * Avec la recherche, le champ est rendu une fois, quels que soient les autres arguments.
 *
 * Lancement : dev/test package=main file=PageHeaderSearchAi
 */
class PageHeaderSearchAiTest extends \Test {

	const TITLE = 'Le grand livre';
	const CONTEXT = 'Exercice 2026';
	const PAGES = '<a class="btn">Pages</a>';
	const LOGO = '<img src="logo.png"/>';
	const TOOLS = '<a class="btn btn-outline-secondary">PDF</a>';
	const CREATE = '<a class="btn btn-secondary">Créer</a>';
	const SETTINGS = '<a class="btn btn-color-primary">Réglages</a>';
	const SEARCH = '<div class="util-omnibox">Recherche</div>';

	public function testEmptySearchIsWithoutSearch(): void {
		$this->assertEquals(DesignUi::getPageHeader(self::TITLE, context: self::CONTEXT), DesignUi::getPageHeader(self::TITLE, context: self::CONTEXT, search: ''), 'la chaîne vide n\'ouvre aucune boîte');
	}

	public function testSearchWithContext(): void {
		$this->assertSearch(DesignUi::getPageHeader(self::TITLE, context: self::CONTEXT, search: self::SEARCH), 'recherche et contexte');
	}

	public function testSearchWithPages(): void {
		$this->assertSearch(DesignUi::getPageHeader(self::TITLE, pages: self::PAGES, search: self::SEARCH), 'recherche et pages');
	}

	public function testSearchWithLogo(): void {

		$this->assertSearch(DesignUi::getPageHeader(self::TITLE, logo: self::LOGO, tools: self::TOOLS, search: self::SEARCH), 'recherche et logo');
		$this->assertSearch(DesignUi::getPageHeader(self::TITLE, logo: self::LOGO, search: self::SEARCH), 'recherche et logo, sans geste');

	}

	public function testSearchWithPagesAndFullAside(): void {
		$this->assertSearch(DesignUi::getPageHeader(self::TITLE, context: self::CONTEXT, tools: self::TOOLS, create: self::CREATE, pages: self::PAGES, settings: self::SETTINGS, search: self::SEARCH), 'recherche, pages et tous les gestes');
	}

	private function assertSearch(string $h, string $message): void {
		$this->assertEquals(1, substr_count($h, self::SEARCH), $message.', recherche rendue une fois');
	}

}
?>
