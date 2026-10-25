<?php
namespace user;

/**
 * La vignette par défaut d'un utilisateur (`UserUi::getVignette()`) rend ses initiales échappées : un
 * « < » en tête d'un prénom ou d'un nom ouvrirait sinon une balise qui avale le balisage suivant.
 *
 * Lancement : dev/test package=user file=VignetteEscape
 */
class VignetteEscapeTest extends \Test {

	public function testInitialsAreEscaped(): void {

		foreach([
			'prénom et nom' => ['<b', '&lt;'],
			'nom seul' => [NULL, '<b'],
		] as $case => [$firstName, $lastName]) {

			$eUser = new User([
				'id' => 1,
				'vignette' => NULL,
				'firstName' => $firstName,
				'lastName' => $lastName
			]);

			$h = UserUi::getVignette($eUser, '2rem');

			$this->assertFalse(str_contains($h, '<B'), 'les initiales ('.$case.') ouvrent une balise');
			$this->assertTrue(str_contains($h, '>&lt;'), 'les initiales ('.$case.') ne sont pas échappées');

		}

	}

}
?>
