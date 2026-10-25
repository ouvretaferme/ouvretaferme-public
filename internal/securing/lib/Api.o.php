<?php
namespace securing;

/**
 * Les gestes de l'API qui ouvrent ou ferment un accès aux données d'une structure.
 *
 * framework/api ne connaît pas securing : il émet ses événements, et c'est ici qu'ils entrent au
 * journal. La structure est celle de l'écran qui porte le geste, l'écran des clés comme l'écran de
 * consentement validant la structure qu'ils reçoivent avant tout.
 */
class ApiObserverLib {

	public static function keyCreate(string $name, int $reference, array $scopes): void {

		self::expectsConnected($reference);

		LogLib::createByValues(LogSetting::EVENT_API_KEY_CREATE, ['name' => $name, 'scopes' => $scopes]);

	}

	public static function keyRevoke(\api\AccessToken $eAccessToken): void {

		self::expectsConnected($eAccessToken['reference']);

		LogLib::createByValues(LogSetting::EVENT_API_KEY_REVOKE, $eAccessToken);

	}

	public static function applicationAuthorize(\api\Client $eClient, int $reference, array $scopes): void {

		self::expectsConnected($reference);

		LogLib::createByValues(LogSetting::EVENT_API_APPLICATION_AUTHORIZE, ['client' => $eClient, 'scopes' => $scopes]);

	}

	public static function applicationRevoke(\api\Client $eClient, \user\User $eUser, int $reference): void {

		self::expectsConnected($reference);

		LogLib::createByValues(LogSetting::EVENT_API_APPLICATION_REVOKE, ['client' => $eClient, 'user' => $eUser]);

	}

	/**
	 * LogLib écrit dans la base de la structure connectée : une référence qui en diffère inscrirait
	 * le geste au journal d'une autre structure.
	 */
	private static function expectsConnected(int $reference): void {

		if(\structure\Structure::getConnected()['id'] !== $reference) {
			throw new \Exception('API event for structure '.$reference.' outside of its connection');
		}

	}

}
?>
