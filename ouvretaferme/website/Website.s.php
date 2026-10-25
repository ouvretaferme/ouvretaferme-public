<?php
namespace website;

class WebsiteSetting extends \Settings  {

	public static function domain(): string {
		return 'site.'.\Lime::getDomain();
	}

	const DOMAIN_MAX_TRY = 3;

	const BLOG_STRUCTURE = 98;

	const BLOG_URL = 'https://blog.ouvretaferme.org/';

	const DESIGN_DEFAULT_ID = 1;

	const DESIGN_DEFAULT_FQN = 'classique';

	/**
	 * La couleur d'un site qui n'en a jamais choisi, la même que celle d'une boutique
	 * (ShopSetting::COLOR_DEFAULT) : celle que la feuille de style donne à --commercialisation.
	 */
	const COLOR_DEFAULT = '#3b4790';

	const KEEP_CONTACT_IN_DAYS = 365;

}

?>
