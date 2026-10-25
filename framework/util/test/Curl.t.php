<?php
namespace util;

/**
 * L'adresse d'une requête GET : les appelants existants gardent le `?` que les paramètres ajoutent,
 * même vides, et seul un appelant qui l'a demandé (CurlLib::setUrlAsIs()) envoie l'adresse telle quelle.
 * Aucune requête ne part : l'adresse se lit dans buildUrl(), par une sous-classe.
 *
 * Lancement : dev/test package=util file=Curl
 */
class CurlTest extends \Test {

	public function testBuildUrl(): void {

		$curl = new CurlUrlTest();

		$this->assertEquals('https://example.org/page?', $curl->getUrl('https://example.org/page', []), 'l\'adresse des appelants existants ne change pas');
		$this->assertEquals('https://example.org/page?a=1', $curl->getUrl('https://example.org/page', ['a' => 1]));
		$this->assertEquals('https://example.org/page?b=2&a=1', $curl->getUrl('https://example.org/page?b=2', 'a=1'));

		$curl->setUrlAsIs(TRUE);

		$this->assertEquals('https://example.org/page', $curl->getUrl('https://example.org/page', []), 'telle que l\'appelant l\'écrit');
		$this->assertEquals('https://example.org/page?b=2', $curl->getUrl('https://example.org/page?b=2', ''));

		$this->assertEquals('https://example.org/page?', new CurlUrlTest()->getUrl('https://example.org/page', []), 'le réglage tient à l\'instance');

		try {
			$curl->getUrl('https://example.org/page', ['a' => 1]);
			$this->assertFail('une adresse telle quelle ne porte aucun paramètre');
		} catch(\Exception $e) {
			$this->assertPass();
		}

	}

}

class CurlUrlTest extends CurlLib {

	public function getUrl(string $url, $params): string {
		return $this->buildUrl($url, $params);
	}

}
?>
