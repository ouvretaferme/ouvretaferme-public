<?php
namespace website;

/**
 * L'inscription à la newsletter d'une structure depuis son site. La newsletter vit dans base/mail,
 * que la version publique n'installe pas : le site ne propose alors aucun formulaire.
 */
class NewsletterLib {

	use \Notifiable;

	public static function build(\structure\Structure $eStructure, array $input): \Element {
		return self::notify('buildNewsletter', $eStructure, $input)[0];
	}

	public static function register(\Element $eContact): void {
		self::notify('registerNewsletter', $eContact);
	}

}
?>
