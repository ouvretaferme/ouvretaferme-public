<?php
namespace dev;

class QueryStatementLib extends QueryStatementCrud {

	public static function createByQuery(Query $eQuery, array $statements): void {

		uasort($statements, fn($statementA, $statementB) => $statementB['time'] <=> $statementA['time']);

		$length = QueryStatement::model()->getPropertyRange('statement')[1];

		foreach(array_slice($statements, 0, DevSetting::QUERY_SAVE_MAX) as $statement) {

			$normalized = self::normalize($statement['statement']);

			$eQueryStatement = new QueryStatement([
				'query' => $eQuery,
				'hash' => hash('xxh128', $normalized),
				'statement' => mb_substr($normalized, 0, $length),
				'executions' => $statement['executions'],
				'time' => $statement['time'] * 1000,
				'timeMax' => $statement['timeMax'] * 1000
			]);

			self::create($eQueryStatement);

		}

	}

	public static function normalize(string $statement): string {

		// Sans quantificateurs possessifs, PCRE épuise sa pile JIT sur un littéral de quelques Ko et rend NULL
		$normalized = preg_replace([
			'/\'(?:[^\'\\\\]++|\\\\.|\'\')*+\'/s',
			'/"(?:[^"\\\\]++|\\\\.|"")*+"/s',
			'/structure_[0-9]+/',
			'/(?<![\w.])[0-9]+(?:\.[0-9]+)?(?![\w.])/',
			'/\bIN\s*\(\s*\?(?:\s*,\s*\?)*+\s*\)/i',
			'/\s+/'
		], [
			'?',
			'?',
			'structure_?',
			'?',
			'IN (?)',
			' '
		], $statement);

		if($normalized === NULL) {
			return 'Requête non normalisée ('.preg_last_error_msg().')';
		}

		return trim($normalized);

	}

}
?>
