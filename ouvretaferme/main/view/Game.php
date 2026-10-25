<?php
class GameTemplate extends BaseTemplate {

	public string $template = 'game';
	public ?string $header = '';
	public ?string $footer = '';

	// La cour prend toute la largeur de l'écran (game.css)
	public bool $mainContainer = FALSE;

	public function __construct() {

		parent::__construct();

		Asset::css('game', 'game.css');

	}

	protected function getHead(): string {

		$h = parent::getHead();
		$h .= \game\DeskUi::getFonts();

		return $h;

	}

}
?>
