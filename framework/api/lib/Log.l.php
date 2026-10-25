<?php
namespace api;

class LogLib extends LogCrud {

	public static function createFromRoute(array $routeInfo, \api\AccessToken $eAccessToken): \api\Log {

		return self::createFromRequest($eAccessToken, $routeInfo['requestRoute']);

	}

	/**
	 * La ligne d'un appel dont la route n'a pas été résolue, et que \ApiRoute::run() refuse donc avant
	 * d'avoir de quoi appeler createFromRoute().
	 */
	public static function createFromRequest(\api\AccessToken $eAccessToken, string $route): \api\Log {

		$headers = getallheaders();
		unset($headers['Authorization'], $headers['Cookie'], $headers['Accept']);

		// Un en-tête vient du client, son encodage n'est garanti par rien : un seul octet hors UTF-8
		// fait rendre FALSE à json_encode(), MySQL refuse alors la colonne json, et l'INSERT du log
		// fait échouer l'appel qu'il journalise.
		$headers = array_filter(
			$headers,
			fn($value, $name) => mb_check_encoding($name, 'UTF-8') and mb_check_encoding($value, 'UTF-8'),
			ARRAY_FILTER_USE_BOTH
		);

		// L'URL et la route viennent du client, leur longueur n'est bornée par rien : sans troncature,
		// l'INSERT du log fait échouer l'appel qu'il journalise.
		$requestUrl = mb_substr(LIME_REQUEST, 0, \api\Log::model()->getPropertyRange('requestUrl')[1]);
		$route = mb_substr($route, 0, \api\Log::model()->getPropertyRange('route')[1]);

		$eLog = new \api\Log([
			'token' => $eAccessToken,
			'reference' => $eAccessToken->empty() ? NULL : $eAccessToken['reference'],
			// Les cinq méthodes que `framework/api/page/index.p.php` déclare sont exactement l'enum :
			// rien d'autre n'atteint \ApiRoute::run(), et une valeur hors enum ferait échouer l'INSERT.
			'requestMethod' => strtolower(\Route::getRequestMethod()),
			'requestUrl' => $requestUrl,
			'requestHeaders' => $headers,
			'route' => $route,
			'startTime' => microtime(TRUE)
		]);

		self::create($eLog);

		return $eLog;

	}

	public static function updateResponse(\api\Log $eLog, string $status, array $errors, int $statusCode): \api\Log {

		$eLog['responseBody'] = $errors;
		$eLog['responseStatusCode'] = $statusCode;
		$eLog['status'] = $status;
		$eLog['time'] = microtime(TRUE) - $eLog['startTime'];

		\api\Log::model()
			->select('responseBody', 'responseStatusCode', 'status', 'time')
			->update($eLog);

		return $eLog;

	}
	
	public static function getList(int $page, \Search $search): array {

		$number = 100;
		$position = $page * $number;

		$select = Log::getSelection();
		$select['token'] = ['name', 'client' => ['name']];

		$eClient = $search->get('client');

		$cLog = Log::model()
			->select($select)
			->whereReference($search->get('reference'), if: $search->get('reference'))
			->whereRoute('LIKE', '%'.$search->get('route').'%', if: $search->get('route'))
			->whereToken('IN', new AccessTokenModel()->select('id')->whereClient($eClient)->getSql(), if: $eClient->notEmpty())
			->sort($search->buildSort())
			->option('count')
			->sort([
				'id' => SORT_DESC
			])
			->getCollection($position, $number);

		return [$cLog, Log::model()->found()];

	}

	public static function getByReference(int $reference, int $page, int $number): array {

		$cLog = Log::model()
			->select([
				'id',
				'requestMethod', 'requestUrl',
				'responseStatusCode', 'status', 'time',
				'createdAt',
				'token' => ['name', 'client' => ['name']],
				// Le corps lui-même ne se charge qu'au dépliage d'une ligne, par getByIdAndReference() :
				// il pèse 23,8 Ko en moyenne, et cette sélection en rend une page entière.
				'hasResponseBody' => new \Sql('responseBody IS NOT NULL', 'bool')
			])
			->whereReference($reference)
			->option('count')
			->sort(['id' => SORT_DESC])
			->getCollection($page * $number, $number);

		return [$cLog, Log::model()->found()];

	}

	public static function getByIdAndReference(int $id, int $reference): Log {

		$eLog = new Log();

		Log::model()
			->select('id', 'responseBody')
			->whereId($id)
			->whereReference($reference)
			->get($eLog);

		return $eLog;

	}

	public static function clean(): void {

		// Une écriture de masse garde un tableau de valeurs : getUpdateCondition() ajoute AND id = <id>
		// dès que l'argument porte un Element, ce qui ramènerait la purge à une seule ligne.
		Log::model()
			->whereStatus(Log::SUCCESS)
			->where('responseBody IS NOT NULL')
			->where('createdAt < NOW() - INTERVAL '.ApiSetting::LOG_BODY_RETENTION_HOURS.' HOUR')
			->update(['responseBody' => NULL]);

		Log::model()
			->where('createdAt < NOW() - INTERVAL '.ApiSetting::LOG_RETENTION_DAYS.' DAY')
			->delete();

	}

}
?>
