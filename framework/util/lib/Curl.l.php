<?php
namespace util;

/**
 * Facilitates CURL requests for HTTP/HTTPs
 */
class CurlLib {

	/**
	 * Info for last CURL request
	 *
	 * @var array
	 */
	protected ?array $infos = NULL;

	/**
	 * When set, every request is refused, for a process that must not reach anything outside
	 */
	private static bool $locked = FALSE;

	/**
	 * When set, a GET request reaches the URL exactly as given, without the trailing `?` that the
	 * parameters otherwise bring: a request that must name a page as it is written (aid\WatchLib).
	 * An instance setting, so that another request of the same process keeps its URL.
	 */
	private bool $urlAsIs = FALSE;

	public static function setLocked(bool $locked): void {
		self::$locked = $locked;
	}

	public function setUrlAsIs(bool $urlAsIs): void {
		$this->urlAsIs = $urlAsIs;
	}

	/**
	 * Execute an HTTP request using CURL
	 *
	 */
	public function exec(string $url, $params, string $mode = 'GET', array $options = []) {

		if(self::$locked) {
			throw new \Exception('HTTP requests are locked, '.parse_url($url, PHP_URL_HOST).' cannot be reached');
		}

		$curl = $this->prepare($url, $params, $mode, $options);

		$content = curl_exec($curl);

		$this->infos = [
			'httpCode' => (int)curl_getinfo($curl, CURLINFO_HTTP_CODE),
			'headerSize' => curl_getinfo($curl, CURLINFO_HEADER_SIZE),
			'headerOut' => curl_getinfo($curl, CURLINFO_HEADER_OUT),
			'redirectUrl' => curl_getinfo($curl, CURLINFO_REDIRECT_URL),
			'contentType' => curl_getinfo($curl, CURLINFO_CONTENT_TYPE),
			'primaryIp' => curl_getinfo($curl, CURLINFO_PRIMARY_IP)
		];

		$errno = curl_errno($curl);

		if($errno !== 0) {
			throw new \Exception(curl_error($curl), $errno);
		}

		curl_close($curl);

		return $content;

	}

	/**
	 * Get infos for the last CURL request
	 */
	public function getLastInfos(): ?array {
		return $this->infos;
	}

	/**
	 * Prepare a CURL query
	 *
	 */
	protected function prepare(string $url, $params, $mode, array $options) {

		$curl = curl_init();

		if($mode === 'POST') {

			if(is_array($params)) {
				foreach($params as $value) {
					if(is_array($value) or (is_string($value) and substr($value, 0, 1) === '@')) {
						$params = http_build_query($params);
						break;
					}
				}
			}

			// libcurl envoie un tableau plat en multipart/form-data, quel que soit l'en-tête posé : une API
			// qui exige de l'urlencoded reçoit une chaîne, le http_build_query() fait par l'appelant.
			curl_setopt_array($curl, [
				CURLOPT_POST => TRUE,
				CURLOPT_POSTFIELDS => $params
			]);

		} else if($mode === 'DELETE') {

			curl_setopt_array($curl, [
				CURLOPT_CUSTOMREQUEST => 'DELETE',
			]);

		} else {
			$url = $this->buildUrl($url, $params);
		}

		curl_setopt_array($curl, $options + [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_REFERER => \Lime::getUrl().'/',
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_FAILONERROR => FALSE
		]);

		return $curl;

	}

	/**
	 * The URL of a GET request, with its parameters
	 */
	protected function buildUrl(string $url, $params): string {

		if($this->urlAsIs) {

			if($params !== [] and $params !== '' and $params !== NULL) {
				throw new \Exception('A URL sent as is carries no parameter');
			}

			return $url;

		}

		return $url.(strpos($url, '?') === FALSE ? '?' : '&').(is_array($params) ? http_build_query($params) : $params);

	}

}

?>
