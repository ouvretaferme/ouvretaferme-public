<?php

require_once LIME_DIRECTORY.'/framework/core/Database/DatabaseApi.php';

/**
 * MySQL for Database
 */

class MySQLApi extends DatabaseApi {

	public function field(string $field): string {
		return "`".str_replace('`', '``', $field)."`";
	}

	public function quote(string $string): string {
		$from = ['\\', '"', '\'', chr(0), chr(10), chr(13), chr(26)];
		$to = ['\\\\', '\\"', '\\\'', '\\0', '\\n', '\\r', '\\Z'];
		return "'".str_replace($from, $to, $string)."'";
	}

	public function delimiter(): string {
		return "`";
	}

	/**
	 * Retire une valeur d'un tableau JSON d'entiers, l'ordre des autres conservé.
	 *
	 * JSON_ARRAYAGG rend NULL sur un ensemble vide : sans le COALESCE, retirer la dernière valeur
	 * écrirait NULL et non un tableau vide. Une colonne déjà NULL le reste, « rien de posé » ne
	 * disant pas la même chose qu'un tableau vide.
	 */
	public function jsonRemove(string $property, int $number): string {
		$field = $this->field($property);
		return 'COALESCE((SELECT JSON_ARRAYAGG(jsonEntry.value) FROM JSON_TABLE('.$field.', \'$[*]\' COLUMNS (value INT PATH \'$\')) jsonEntry WHERE jsonEntry.value != '.$number.'), IF('.$field.' IS NULL, NULL, JSON_ARRAY()))';
	}

	public function jsonAdd(string $property, int $number): string {
		return 'JSON_ARRAY_INSERT('.$this->field($property).', \'$[0]\', '.$number.')';
	}

	public function limit(int $start, int $length): string {
		$return = 'LIMIT ';
		if($start > 0) {
			$return .= $start.', ';
		}
		$return .= $length;
		return $return;
	}

	public function random(): string {
		return 'RAND()';
	}

	// PHP porte un point en [longitude, latitude] ; une colonne SRID 4326 le range dans l'ordre de ses
	// axes, latitude d'abord. Chaque encode*() et decode*() renverse donc l'ordre. Dans une requête écrite
	// à la main, ST_AsText() et ST_X() rendent la latitude en premier, ST_AsGeoJSON() la longitude.
	public function encodePoint(array $point): string {
		return 'ST_PointFromText(\'POINT('.(float)$point[1].' '.(float)$point[0].')\', 4326)';
	}

	public function decodePoint(string $value): string {
		return json_encode(array_reverse(json_decode($value, TRUE)['coordinates']));
	}

	public function encodePolygon(array $points): string {

		// Last point must be the same as first point
		if($points[0] !== $points[count($points) - 1]) {
			$points[] = $points[0];
		}

		$list = array_reduce($points, function(array $carry, array $point) {
			$carry[] = (float)$point[1].' '.(float)$point[0];
			return $carry;
		}, []);

		return 'ST_PolygonFromText(\'POLYGON(('.implode(', ', $list).'))\', 4326)';

	}

	public function decodePolygon(string $value): string {

		$polygon = [];

		foreach(json_decode($value, TRUE)['coordinates'][0] as $point) {
			$polygon[] = [$point[1], $point[0]];
		}

		return json_encode($polygon);

	}

	public function concat(...$list): string {

		if($list) {
			return 'CONCAT('.implode(', ', $list).')';
		} else {
			return '';
		}

	}

	public function found() {
		$rows = $this->db->selectUnique("SELECT FOUND_ROWS()");
		return is_null($rows) ? NULL : (int)$rows;
	}

	public function now(string $mode = 'datetime', ?string $difference = NULL): mixed {

		if($difference !== NULL) {
			$interval = ' + INTERVAL '.$difference;
		} else {
			$interval = '';
		}

		switch($mode) {
			case 'datetime' :
				return $this->db->selectUnique('SELECT NOW() '.$interval);
			case 'date' :
				return $this->db->selectUnique('SELECT CURDATE() '.$interval);
			case 'time' :
				return $this->db->selectUnique('SELECT CURTIME() '.$interval);
			case 'timestamp' :
				return (int)$this->db->selectUnique('SELECT UNIX_TIMESTAMP(NOW() '.$interval.')');

			default :
				return NULL;
		}
	}

	public function color(string $sql): string {

		if(Route::getRequestedWith() === 'http') {

			$words = [
				'keyword' => [
					'SELECT', 'INSERT', 'INTO', 'UPDATE', 'DELETE', ' FROM ', 'WHERE', 'GROUP BY', ' VALUES', ' LIKE ',
					'DISTINCT', ' TO ', ' IN ', 'ORDER BY ', ' AND ', ' XOR ', ' OR ', 'LIMIT', '\w JOIN', ' ON ', ' SET ',
					' BETWEEN '
				],
				'function' => [
					'IF\(', 'POW\(', 'MAX\(', 'MIN\(', 'AVG\(', 'STD_DEV\(', 'SUM\(', 'COUNT\(',
					'SUBDATE\(', 'ADDDATE\(', 'UNIX_TIMESTAMP\(', 'FROM_UNIXTIME\(', 'NOW\(', 'CURDATE\(', 'CURTIME\(', 'DATE\(', 'TO_DAYS\('
				],
				'string' => ["'[^\']+'"],
				'table' => ['`[^`]+`\.`[^`]+`']
			];

			foreach($words as $css => $keywords) {
				$sql = preg_replace('/('.implode('|', $keywords).')/', '<span class="sql-'.$css.'">$1</span>$2', $sql);
			}

			return $sql;

		} else {
			return $sql;
		}

	}

	public function getReplicationDelay(string $replicationName, ?int $cacheTimeout = NULL): int {

		// No delay while in development
		if(LIME_ENV !== 'prod') {
			return 0;
		}

		if($cacheTimeout !== NULL) {

			$cache = Cache::redis();
			$cacheKey = 'replication-'.$this->db->getParam('connection').'-'.$replicationName.'-'.$cacheTimeout;

			$delay = $cache->get($cacheKey);

			if($delay !== FALSE) {
				return $delay;
			}

		}

		// If this connection is not a replication, get the replication
		$connection = $this->db->getParam('connection');

		if(strpos($connection, '@'.$replicationName) === FALSE) {
			$database = new Database($connection.'@'.$replicationName, [PDO::ATTR_TIMEOUT => 3]);
		}
		// We are on a replication
		else {
			$database = $this->db;
		}

		$statement = $database->query('SHOW SLAVE STATUS');

		if($statement) {
			$result = $statement->fetch();
			if(is_array($result) and isset($result['Seconds_Behind_Master'])) {
				$delay = (int)$result['Seconds_Behind_Master'];
			} else {
				$delay = NULL;
			}
		} else {
			$delay = NULL;
		}

		if($delay === NULL) {
			throw new QueryDatabaseException("No replication has been found");
		}

		$delay = (int)$delay;

		if($cacheTimeout !== NULL) {
			$cache->set($cacheKey, $delay, $cacheTimeout);
		}

		return $delay;

	}

}
?>
