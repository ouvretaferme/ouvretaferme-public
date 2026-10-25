<?php
/**
 * Affichage d'une panel de navigation
 */
class PanelTemplate extends RootTemplate {

	use SmartPanelTemplate;

	public function __construct() {

		parent::__construct();

		$this->template = 'panel';

		self::buildAssets();

	}

}
?>
