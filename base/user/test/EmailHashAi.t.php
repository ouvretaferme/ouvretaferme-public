<?php
namespace user;

/**
 * Le lien qui vérifie une adresse électronique.
 *
 * Il était `<utilisateur>/<adresse>` chiffré (`CryptLib::encrypt()`, un AES-CBC sans authentification)
 * dont le premier bloc se réécrit en posant la différence des clairs sur l'IV, sans connaître le
 * secret. La démonstration de la réécriture est dans `main\CryptSignatureAiTest`.
 *
 * Il est signé depuis le 2026-09-22. L'adresse n'y entre plus en clair mais par un condensé
 * (`CryptLib::digest()`), un lien de vérification voyageant dans une chaîne de requête : ce que le
 * chiffrement cachait, la signature le montrerait. Le contrôle, lui, ne change pas, une adresse
 * modifiée depuis l'envoi invalide le lien. Les liens chiffrés restent acceptés jusqu'au 2026-12-31,
 * le temps que ceux des boîtes de réception s'éteignent : un cas le dit, et s'enlève avec le repli
 * (`main\LegacyTokenSentinelAiTest`).
 *
 * Tout se joue en transaction annulée par `tearDown()`, la dev étant partagée par plusieurs sessions.
 *
 * Lancement : dev/test package=user file=EmailHashAi
 */
class EmailHashAiTest extends \Test {

	public function setUp(): void {
		User::model()->beginTransaction();
	}

	public function tearDown(): void {
		User::model()->rollBack();
	}

	/**
	 * Posé par le modèle : UserLib::create() ouvre une inscription complète, quand ces cas n'ont besoin
	 * que d'un compte non vérifié portant une adresse.
	 */
	private function createUser(): User {

		$eUser = new User([
			'lastName' => 'Vérification',
			'email' => 'verification-'.uniqid().'@example.org',
			'verified' => FALSE,
		]);

		User::model()->insert($eUser);

		return $eUser;

	}

	private function isVerified(User $eUser): bool {

		return UserLib::getById($eUser['id'], ['id', 'verified'])['verified'];

	}

	private function validate(string $hash): bool {

		$fw = new \FailWatch();

		EmailLib::validate($hash);

		return $fw->ok();

	}

	public function testSignedHashVerifies(): void {

		$eUser = $this->createUser();

		$this->assertTrue($this->validate(EmailLib::computeHash($eUser)), 'le lien signé est accepté');
		$this->assertTrue($this->isVerified($eUser), 'et l\'adresse est vérifiée');

	}

	public function testRewrittenHashIsRefused(): void {

		$eUser = $this->createUser();
		$eUserOther = $this->createUser();

		$hash = EmailLib::computeHash($eUser);

		$this->assertFalse($this->validate(str_replace('userEmail:'.$eUser['id'].':', 'userEmail:'.$eUserOther['id'].':', $hash)), 'un compte réécrit sous la signature d\'origine est refusé');

		$last = substr($hash, -1);
		$this->assertFalse($this->validate(substr($hash, 0, -1).($last === '0' ? '1' : '0')), 'une signature altérée d\'un caractère est refusée');

		$this->assertFalse($this->validate('nimportequoi'), 'un lien qui n\'est ni signé ni chiffré est refusé');
		$this->assertFalse($this->isVerified($eUser), 'aucun de ces liens n\'a vérifié l\'adresse');

	}

	/** L'adresse ne voyage plus dans le lien, mais le contrôle qu'elle n'a pas changé reste. */
	public function testHashOfAnotherAddressIsRefused(): void {

		$eUser = $this->createUser();

		$hash = EmailLib::computeHash($eUser);

		$eUser['email'] = 'verification-'.uniqid().'@example.org';
		User::model()->select('email')->update($eUser);

		$this->assertFalse($this->validate($hash), 'un lien émis pour l\'adresse précédente est refusé');
		$this->assertFalse($this->isVerified($eUser), 'et l\'adresse nouvelle n\'est pas vérifiée pour autant');

	}

	/** Le secret `user` sert aussi à la référence d'une commande de boutique : seul le préfixe les sépare. */
	public function testShopReferenceIsNotAHash(): void {

		$eUser = $this->createUser();

		$this->assertFalse($this->validate(\main\CryptLib::sign('shopSale:'.$eUser['id'], 'user')), 'une référence de commande, signée sous le même secret, ne vaut pas comme lien de vérification');

	}

	/** Ce cas s'enlève avec le repli de EmailLib::getByHash(), à l'échéance que porte main\LegacyTokenSentinelAiTest. */
	public function testLegacyEncryptedHashVerifies(): void {

		$eUser = $this->createUser();

		$this->assertTrue($this->validate(\main\CryptLib::encrypt($eUser['id'].'/'.$eUser['email'], 'user')), 'un lien chiffré, comme ceux déjà envoyés, est encore accepté');
		$this->assertTrue($this->isVerified($eUser), 'et vérifie l\'adresse');

	}

}
?>
