<?php
namespace main;

/**
 * Pourquoi un jeton public se signe et ne se chiffre pas.
 *
 * `CryptLib::encrypt()` est un AES en mode CBC, dont l'IV voyage en clair devant le chiffré et sur
 * lequel le premier bloc du clair est appliqué en XOR au déchiffrement. Qui connaît le clair de son
 * propre jeton, ce qui est le cas de tout client qui reconnaît son numéro de commande, réécrit donc
 * celui de n'importe quel autre sans connaître le secret : il lui suffit de poser sur l'IV la
 * différence entre les deux clairs. Le chiffrement cache, il n'authentifie pas.
 *
 * Le premier cas le fait, sur trois caractères, pour que la raison des jetons signés du produit ne
 * repose pas sur une affirmation. Les suivants montrent ce que la signature rend impossible.
 *
 * Aucun cas ne touche la base ni le réseau.
 *
 * Lancement : dev/test package=main file=CryptSignatureAi
 */
class CryptSignatureAiTest extends \Test {

	const BLOCK = 16;

	/** Le clair d'un bloc unique, complété comme PKCS#7 le fait, donc reconstituable par qui lit le sien. */
	private static function getBlock(string $data): string {

		$padding = self::BLOCK - strlen($data);

		return $data.str_repeat(chr($padding), $padding);

	}

	public function testEncryptedTokenIsRewritten(): void {

		$token = \main\CryptLib::encrypt('123', 'user');

		$raw = base64_decode($token);
		$iv = substr($raw, 0, self::BLOCK);

		$forged = base64_encode(($iv ^ self::getBlock('123') ^ self::getBlock('999')).substr($raw, self::BLOCK));

		$this->assertEquals('999', \main\CryptLib::decrypt($forged, 'user'), 'un jeton chiffré se réécrit en un autre sans le secret, en posant la différence des deux clairs sur son IV');

	}

	public function testSignedTokenIsNotRewritten(): void {

		$signed = \main\CryptLib::sign('shopSale:123', 'user');

		$this->assertEquals('shopSale:123', \main\CryptLib::verify($signed, 'user'), 'un jeton signé se relit');
		$this->assertEquals(NULL, \main\CryptLib::verify(str_replace('shopSale:123', 'shopSale:999', $signed), 'user'), 'la même réécriture est refusée sous la signature');

	}

	/** Un secret sert plusieurs jetons : le préfixe est ce qui empêche l'un de valoir pour l'autre. */
	public function testSecretIsSharedBySeveralTokens(): void {

		$this->assertEquals('userEmail:1', \main\CryptLib::verify(\main\CryptLib::sign('userEmail:1', 'user'), 'user'), 'deux jetons du secret user se relisent tous deux');
		$this->assertEquals(NULL, \main\CryptLib::verify(\main\CryptLib::sign('shopSale:1', 'user'), 'payment'), 'mais pas sous un autre secret');

	}

	/** Le condensé d'une valeur ne doit pas être la signature de cette même valeur, sinon il l'offre toute faite. */
	public function testDigestIsNotASignature(): void {

		$signed = \main\CryptLib::sign('camille@example.org', 'user');
		$signature = substr($signed, strrpos($signed, ':') + 1);

		$this->assertFalse(str_starts_with($signature, \main\CryptLib::digest('camille@example.org', 'user')), 'le condensé n\'est pas le début de la signature de la même valeur');
		$this->assertEquals(\main\CryptLib::digest('camille@example.org', 'user'), \main\CryptLib::digest('camille@example.org', 'user'), 'et il est stable');

	}

}
?>
