<?php
namespace website;

class WidgetLib {

	use \Notifiable;

	public static function fill(Website $eWebsite, Webpage $eWebpage): void {

		if($eWebpage->empty()) {
			return;
		}

		$eWebpage['widgets'] = [];

		if($eWebpage['content'] !== NULL) {

			$widgets = self::getWidgets($eWebsite);

			$found = preg_match_all('/\@('.implode('|', array_keys($widgets)).')(=([0-9]+))?/s', $eWebpage['content'], $matches);

			for($i = 0; $i < $found; $i++) {

				$original = mb_strtolower($matches[0][$i]);

				if(isset($eWebpage['widgets'][$original])) {
					continue;
				}

				$app = $matches[1][$i];
				$value = ($matches[3][$i] !== '') ? (int)$matches[3][$i] : NULL;

				$eWebpage['widgets'][$original] = $widgets[$app]($value) ?? $original;

			}

		}

	}

	/**
	 * Les widgets qu'un site peut poser, chacun par la fabrique qui le rend à partir de la valeur écrite après
	 * son nom (`@shop=12`). Ceux des boutiques et des AMAP viennent du code fermé, sur un événement.
	 */
	public static function getWidgets(Website $eWebsite): array {

		return array_replace(
			[
				'contactForm' => fn(?int $value) => self::getContactForm($eWebsite),
				'newsletterForm' => fn(?int $value) => self::getNewsletterForm($eWebsite),
			],
			...self::notify('getWidgets', $eWebsite)
		);

	}

	/**
	 * Un modèle de site qui pose le bouton d'adhésion ne se met pas en ligne seul tant que le bouton ne mène nulle part.
	 */
	public static function hasMembership(Website $eWebsite): bool {
		return in_array(TRUE, self::notify('hasMembership', $eWebsite), TRUE);
	}

	public static function getContactForm(Website $eWebsite): ?\Closure {

		return fn() => new ContactUi()->getForm($eWebsite);

	}

	public static function getNewsletterForm(Website $eWebsite): ?\Closure {

		if(\structure\StructureSetting::hasMailPackage() === FALSE) {
			return fn() => '';
		}

		return fn() => new NewsletterUi()->getForm($eWebsite);

	}

}
?>
