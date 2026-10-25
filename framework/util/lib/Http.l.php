<?php
namespace util;

/**
 * Http
 */
class HttpLib  {

	/**
	 * Gets current browser as a string
	 *
	 * @param bool $withVersion
	 *
	 * @return string or null
	 */
	public static function getBrowserString(bool $withVersion = TRUE) {

		$browser = self::getBrowser($withVersion);

		if($browser['browser'] === NULL or $withVersion === FALSE) {
			return $browser['browser'];
		}

		return $browser['browser'].' '.$browser['version'];
	}

	const CHROME_MIN = 111;
	const FIREFOX_MIN = 113;
	const SAFARI_MIN = 16;

	/**
	 * Gets if the used browser is obsolete or not
	 *
	 * @return boolean
	 */
	public static function isObsolete(): bool {

		$browser = self::getBrowser();

		// Sur iOS, Chrome et Firefox ne sont que des coquilles autour du WebKit du
		// système : c'est la version d'iOS qui décide de ce qui se rend, pas le
		// numéro que la coquille affiche. Un iPhone resté en 15 annonce « Chrome
		// 126 » et se voyait déclaré à jour.
		$ios = self::getIosVersion();

		if($ios !== NULL) {
			return ($ios < self::SAFARI_MIN);
		}

		// Un numéro illisible ne prouve rien : on n'alarme que sur ce qu'on sait,
		// sauf pour Internet Explorer où le nom suffit.
		if(in_array($browser['version'], ['', NULL], TRUE)) {
			return ($browser['browser'] === 'MSIE');
		}

		return match($browser['browser']) {

			// Internet Explorer et l'Opera d'avant Chromium : aucune version n'a
			// jamais atteint le socle, il n'y a pas de seuil à écrire.
			'MSIE', 'Opera' => TRUE,

			'Chrome' => ($browser['version'] < self::CHROME_MIN),
			'Firefox' => ($browser['version'] < self::FIREFOX_MIN),
			'Safari' => ($browser['version'] < self::SAFARI_MIN),

			default => FALSE

		};

	}

	/**
	 * Version majeure d'iOS, ou NULL hors iOS.
	 */
	public static function getIosVersion(): ?int {

		if(server_exists('HTTP_USER_AGENT') === FALSE) {
			return NULL;
		}

		$userAgent = SERVER('HTTP_USER_AGENT');

		if(preg_match('@(?:iPhone|iPad|iPod).*OS (\d+)[_.]@', $userAgent, $matches) === 1) {
			return (int)$matches[1];
		}

		return NULL;

	}

	/**
	 * Get current browser
	 *
	 * @param bool $withVersion
	 * @return array
	 */
	public static function getBrowser(bool $withVersion = TRUE): array {

		// Get browser
		if(server_exists('HTTP_USER_AGENT')) {

			$browser = '';
			$version = '';

			// C'est le DERNIER motif trouvé qui l'emporte, la boucle ne s'arrête pas
			// au premier : l'ordre va donc du plus générique au plus spécifique.
			// Safari est en tête parce que tous les autres le citent dans leur
			// signature — Chrome, mais aussi les navigateurs iOS, qui sont du WebKit
			// déguisé. Trident est là pour Internet Explorer 11, qui a retiré le
			// jeton MSIE de sa signature et n'était donc plus reconnu du tout.
			$browsers = [
				'MSIE' => 'MSIE',
				'Trident' => 'MSIE',
				'Opera' => 'Opera',
				'Safari' => 'Safari',
				'Firefox' => 'Firefox',
				'FxiOS' => 'Firefox',
				'Chrome' => 'Chrome',
				'CriOS' => 'Chrome',
			];

			$userAgent = SERVER('HTTP_USER_AGENT');

			foreach($browsers as $browserCheck => $browserName) {

				if(strrpos($userAgent, $browserCheck) !== FALSE) {

					$browser = $browserName;

					if($browserCheck !== 'Safari') {
						$regexp = '@.*('.$browserCheck.'[/ ]?)([\d]+)@';
					} else {
						$regexp = '@.*(Version/)([\d]+)@';
					}

					if($withVersion and preg_match($regexp, $userAgent, $matches)) {
						$version = (int)$matches[2];
					}
				}

			}

		} else {

			$browser = NULL;
			$version = NULL;

		}

		return ['browser' => $browser, 'version' => $version];

	}

}
?>
