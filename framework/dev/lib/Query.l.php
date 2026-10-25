<?php
namespace dev;

class QueryLib extends QueryCrud {

	public static function record(): void {

		$eQuery = new Query([
			'method' => \Route::getRequestMethod(),
			'request' => ErrorPhpLib::hideSecretInUrls(SERVER('HTTP_HOST').LIME_REQUEST),
			'page' => \Page::getRequest(),
			'name' => \Page::getName(),
			'mode' => \Route::getRequestedWith(),
			'user' => \user\ConnectionLib::getOnline(),
			'time' => \Database::getQueryTime() * 1000,
			'queries' => \Database::getQueryCount(),
			'duplicates' => \Database::getQueryDuplicates(),
			'duration' => (microtime(TRUE) - SERVER('REQUEST_TIME_FLOAT', 'float')) * 1000
		]);

		// Sans build() : aucune valeur ne vient d'une saisie, toutes sont lues du cadriciel à l'arrêt
		self::create($eQuery);

		QueryStatementLib::createByQuery($eQuery, \Database::getStatements());

	}

	public static function clean(): void {

		$stale = 'createdAt < NOW() - INTERVAL '.DevSetting::QUERY_KEEP.' DAY';

		$mQueryStale = new QueryModel()
			->select('id')
			->where($stale);

		QueryStatement::model()
			->whereQuery('IN', $mQueryStale->getSql())
			->delete();

		Query::model()
			->where($stale)
			->delete();

	}

}
?>
