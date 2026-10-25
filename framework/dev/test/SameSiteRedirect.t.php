<?php
namespace dev;

/**
 * Une redirection demandée par le client reste sur le site.
 *
 * Le paramètre redirect de la connexion, de la déconnexion et de la connexion en tant qu'un autre
 * compte partait tel quel dans l'en-tête Location : un lien de connexion légitime pouvait déposer
 * l'utilisateur, une fois connecté, sur n'importe quel site.
 *
 * `Lime::isSameSite()` n'accepte qu'un chemin absolu ou une URL sur l'hôte du site et ses
 * sous-domaines (boutique, site, reseau, demo). Chaque refus se double d'une acceptation voisine,
 * sinon un contrôle qui refuse tout passerait aussi.
 *
 * Lancement : dev/test package=dev file=SameSiteRedirect
 */
class SameSiteRedirectTest extends \Test {

	public function testAnAbsolutePathStaysOnTheSite(): void {

		$this->assertTrue(\Lime::isSameSite('/'), 'la racine');
		$this->assertTrue(\Lime::isSameSite('/structure/7/vente?success=x'), 'un chemin avec ses arguments');
		$this->assertTrue(\Lime::isSameSite('/doc/accounting:bank#cashflow-manage'), 'un chemin avec une ancre');

	}

	/** Un navigateur lit «//hôte» et «/\hôte» comme une URL sans schéma, donc comme un autre hôte. */
	public function testASchemeRelativeUrlIsRefused(): void {

		$this->assertFalse(\Lime::isSameSite('//evil.example/'), 'deux barres obliques');
		$this->assertFalse(\Lime::isSameSite('/\\evil.example/'), 'une barre oblique et une contre-oblique');
		$this->assertFalse(\Lime::isSameSite('///evil.example/'), 'trois barres obliques');

	}

	/** Un navigateur retire tabulations et sauts de ligne d'une URL : «/\t/hôte» devient «//hôte». */
	public function testAControlCharacterIsRefused(): void {

		$this->assertFalse(\Lime::isSameSite("/\t/evil.example/"), 'une tabulation entre les deux barres');
		$this->assertFalse(\Lime::isSameSite("/\n/evil.example/"), 'un saut de ligne entre les deux barres');
		$this->assertFalse(\Lime::isSameSite("/\r/evil.example/"), 'un retour chariot entre les deux barres');
		$this->assertFalse(\Lime::isSameSite(\Lime::getUrl()."/\t/evil.example/"), 'une tabulation dans le chemin d\'une URL du site');
		$this->assertTrue(\Lime::isSameSite('/structure/7/vente?nom=a%09b'), 'une tabulation encodée dans les arguments');

	}

	public function testTheSiteHostAndItsSubdomainsAreAccepted(): void {

		$this->assertTrue(\Lime::isSameSite(\Lime::getUrl()), 'l\'URL du site');
		$this->assertTrue(\Lime::isSameSite(\Lime::getUrl().'/user/update:email'), 'une page du site');
		$this->assertTrue(\Lime::isSameSite('https://boutique.'.\Lime::getDomain().'/ma-ferme'), 'une boutique');
		$this->assertTrue(\Lime::isSameSite('HTTP://'.strtoupper(\Lime::getHost()).'/'), 'l\'hôte en majuscules');
		$this->assertTrue(\Lime::isSameSite('http://'.\Lime::getHost().':8080/'), 'l\'hôte avec un port');

	}

	public function testAnotherHostIsRefused(): void {

		$this->assertFalse(\Lime::isSameSite('https://evil.example/'), 'un autre hôte');
		$this->assertFalse(\Lime::isSameSite('https://'.\Lime::getHost().'.evil.example/'), 'l\'hôte du site en préfixe d\'un autre');
		$this->assertFalse(\Lime::isSameSite('https://'.\Lime::getHost().'@evil.example/'), 'l\'hôte du site en identifiant d\'un autre');
		$this->assertFalse(\Lime::isSameSite('https://evil'.\Lime::getDomain().'/'), 'le domaine du site collé à un autre nom');
		$this->assertFalse(\Lime::isSameSite('https://'.\Lime::getHost().'\\@evil.example/'), 'l\'hôte du site suivi d\'une contre-oblique');

	}

	public function testAnythingElseIsRefused(): void {

		$this->assertFalse(\Lime::isSameSite('javascript:alert(1)'), 'un schéma javascript');
		$this->assertFalse(\Lime::isSameSite('ftp://'.\Lime::getHost().'/'), 'un autre schéma sur le bon hôte');
		$this->assertFalse(\Lime::isSameSite('structure/7'), 'un chemin relatif');
		$this->assertFalse(\Lime::isSameSite(''), 'une chaîne vide');

	}

	public function testGetRedirectFallsBackToTheDefault(): void {

		$this->assertEquals('/structure/7', \Lime::getRedirect('/structure/7'), 'un chemin du site est rendu tel quel');
		$this->assertEquals('/', \Lime::getRedirect('https://evil.example/'), 'un autre hôte tombe sur la racine');
		$this->assertEquals('/', \Lime::getRedirect(NULL), 'l\'absence de paramètre aussi');
		$this->assertEquals('/', \Lime::getRedirect(''), 'et le paramètre vide');
		$this->assertEquals('/vente', \Lime::getRedirect('//evil.example/', '/vente'), 'le défaut se choisit');

	}

}
?>
