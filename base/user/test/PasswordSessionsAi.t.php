<?php
namespace user;

/**
 * Un nouveau mot de passe ferme les autres sessions du compte.
 *
 * `SignUpLib::updatePassword()` supprimait la reconnexion automatique (`UserAuto`) mais laissait
 * vivre les sessions ouvertes : celui qui avait volé une session gardait le compte après que son
 * propriétaire l'avait repris. Elle appelle désormais `SessionLib::killByUser()` en épargnant la
 * session courante, pour que celui qui change son mot de passe reste connecté.
 *
 * Les sessions sont semées par le modèle dans la transaction, sur un compte semé de même : la
 * table `session` vit dans la base commune, comme `user`.
 *
 * Lancement : dev/test package=user file=PasswordSessionsAi
 */
class PasswordSessionsAiTest extends \Test {

	const SID_CURRENT = 'zzTestSessionCourante42';
	const SID_OTHER = 'zzTestSessionAutre42';
	const SID_STRANGER = 'zzTestSessionEtrangere42';

	private User $eUser;
	private User $eUserOther;

	public function setUp(): void {

		User::model()->beginTransaction();

		$this->eUser = $this->createUser();
		$this->eUserOther = $this->createUser();

		$this->createSession(self::SID_CURRENT, $this->eUser);
		$this->createSession(self::SID_OTHER, $this->eUser);
		$this->createSession(self::SID_STRANGER, $this->eUserOther);

	}

	public function tearDown(): void {
		User::model()->rollBack();
	}

	private function createUser(): User {

		$eUser = new User([
			'firstName' => 'zzTest',
			'lastName' => 'Sessions',
			'email' => 'zztest.sessions.'.uniqid().'@example.org',
			'visibility' => User::PRIVATE,
			'roleCustomer' => TRUE,
			'roleProducer' => FALSE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		UserLib::create($eUser);

		$eUser['auth'] = new UserAuth([
			'user' => $eUser,
			'type' => UserAuth::BASIC,
			'login' => $eUser['email'],
			'password' => password_hash('zzTestMotDePasse42', PASSWORD_DEFAULT)
		]);

		SignUpLib::updatePassword($eUser);

		return $eUser;

	}

	private function createSession(string $sid, User $eUser): void {

		$eSession = new \session\Session([
			'sid' => $sid,
			'content' => '',
			'user' => $eUser
		]);

		\session\Session::model()->insert($eSession);

	}

	private function hasSession(string $sid): bool {

		return \session\Session::model()
			->whereSid($sid)
			->exists();

	}

	public function testKillByUserSparesTheGivenSession(): void {

		\session\SessionLib::killByUser($this->eUser, self::SID_CURRENT);

		$this->assertTrue($this->hasSession(self::SID_CURRENT), 'la session courante reste');
		$this->assertFalse($this->hasSession(self::SID_OTHER), 'l\'autre session du compte est fermée');
		$this->assertTrue($this->hasSession(self::SID_STRANGER), 'la session d\'un autre compte n\'est pas touchée');

	}

	public function testKillByUserWithoutExceptionClosesEverything(): void {

		\session\SessionLib::killByUser($this->eUser);

		$this->assertFalse($this->hasSession(self::SID_CURRENT));
		$this->assertFalse($this->hasSession(self::SID_OTHER));
		$this->assertTrue($this->hasSession(self::SID_STRANGER));

	}

	/**
	 * Le geste du produit. En ligne de commande il n'y a pas de session courante (`session_id()`
	 * est vide), donc toutes celles du compte se ferment, et aucune autre.
	 */
	public function testANewPasswordClosesTheSessionsOfTheAccount(): void {

		$this->eUser['auth']['password'] = password_hash('zzTestNouveauMotDePasse42', PASSWORD_DEFAULT);

		SignUpLib::updatePassword($this->eUser);

		$this->assertFalse($this->hasSession(self::SID_CURRENT));
		$this->assertFalse($this->hasSession(self::SID_OTHER));
		$this->assertTrue($this->hasSession(self::SID_STRANGER), 'la session d\'un autre compte n\'est pas touchée');

	}

}
?>
