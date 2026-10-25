<?php
namespace website;

/**
 * Les certificats des domaines personnalisés ne coupent plus nginx.
 *
 * Jusqu'au 26/09/2026, `DomainLib::buildCertificate()` lançait certbot en mode standalone, qui arrête
 * nginx pour occuper le port 80, et reprenait chaque nuit, sans limite, les sites dont le certificat
 * avait échoué : trois domaines abandonnés coupaient tous les sites trois fois par nuit (journal du
 * serveur, 25 et 26/09, 02:37). Ce qui s'y prouve :
 * 1. un échec de certificat compte ses essais et n'est plus repris au-delà de `DOMAIN_MAX_TRY` ;
 * 2. le fichier nginx d'un domaine sert le défi de Let's Encrypt sur le port 80, sécurisé ou non,
 *    sans quoi le mode webroot échouerait là où le standalone réussissait.
 *
 * Le premier cas pose l'état sur un site existant dans une transaction annulée.
 *
 * Lancement : dev/test package=website file=DomainAi
 */
class DomainAiTest extends \Test {

	const ACME = 'include /var/www/otf/ouvretaferme/main/conf/rewrite/acme.cfg;';

	public function testCertificateTriesAreBounded(): void {

		$eWebsite = Website::model()
			->select('id')
			->whereDomain('!=', NULL)
			->get();

		if($eWebsite->empty()) {
			$this->assertFail('aucun site à domaine personnalisé dans la base');
			return;
		}

		Website::model()->beginTransaction();

		try {

			Website::model()->update($eWebsite, [
				'domainStatus' => Website::FAILURE_CERTIFICATE_CREATED,
				'domainTry' => WebsiteSetting::DOMAIN_MAX_TRY - 1
			]);

			$this->assertTrue(in_array($eWebsite['id'], DomainLib::getForCertificate()->getIds()), 'un échec sous la limite est repris');

			Website::model()->update($eWebsite, [
				'domainTry' => WebsiteSetting::DOMAIN_MAX_TRY
			]);

			$this->assertFalse(in_array($eWebsite['id'], DomainLib::getForCertificate()->getIds()), 'un échec à la limite ne relance plus certbot');

		} finally {
			Website::model()->rollBack();
		}

	}

	public function testRewriteServesChallengeUnsecured(): void {

		$content = DomainLib::getRewriteContent('www.zztest.fr', FALSE);

		$this->assertEquals(1, substr_count($content, self::ACME), 'le seul bloc, en port 80, sert le défi');

	}

	public function testRewriteServesChallengeSecured(): void {

		$content = DomainLib::getRewriteContent('www.zztest.fr', TRUE);

		$this->assertEquals(2, substr_count($content, self::ACME), 'le bloc 443 et le bloc 80 servent le défi');
		$this->assertTrue(str_contains($content, "location / {\n\t\treturn 301 https://www.zztest.fr"), 'la redirection du port 80 vit dans une location, sinon elle passe devant le défi');

	}

}
?>
