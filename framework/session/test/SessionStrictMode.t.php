<?php
namespace session;

/**
 * `session.use_strict_mode` demande au gestionnaire si l'identifiant reçu désigne une session encore
 * vivante, et en tire un neuf quand ce n'est pas le cas. Sans lui, `SessionLib::read()` créait la
 * ligne de l'identifiant que le client avait choisi.
 *
 * Lancement : dev/test package=session file=SessionStrictMode
 */
class SessionStrictModeTest extends \dev\TransactionTest {

	protected function getTransactionModel(): \ModuleModel {
		return new SessionModel();
	}

	public function testUnknownSidIsRefused(): void {

		$this->assertFalse(SessionLib::validateSid(SessionLib::createSid()), 'identifiant bien formé mais inconnu');
		$this->assertFalse(SessionLib::validateSid('../../etc/passwd'), 'identifiant mal formé');

	}

	public function testLivingSidIsAccepted(): void {

		$this->setClock('2026-10-05 12:00:00');

		$sid = SessionLib::createSid();

		// Insertion directe : SessionLib n'ouvre une session que sous session_start(), absent en ligne de
		// commande, et un identifiant tiré par createSid() n'a rien qu'une validation pourrait refuser
		$eSession = new Session([
			'sid' => $sid,
			'content' => '',
		]);

		Session::model()->insert($eSession);

		$this->assertTrue(SessionLib::validateSid($sid), 'session ouverte à l\'instant');

		$this->setClock('2026-10-05 14:59:00');
		$this->assertTrue(SessionLib::validateSid($sid), 'session à une minute de sa fin');

		$this->setClock('2026-10-05 15:01:00');
		$this->assertFalse(SessionLib::validateSid($sid), 'session expirée après SessionLib::LIFETIME');

	}

	public function testHandlerAnswersStrictMode(): void {

		$this->assertTrue(new SessionHandler() instanceof \SessionUpdateTimestampHandlerInterface, 'PHP n\'interroge validateId() que sur ce contrat');

	}

}
?>
