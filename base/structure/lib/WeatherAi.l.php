<?php
namespace structure;

/**
 * Les prévisions météo de la position d'une structure, lues dans un cache que seul le cron remplit :
 * l'accueil n'appelle jamais la source, et une page ne dépend donc ni de sa disponibilité ni de son
 * temps de réponse.
 *
 * Une prévision se tient par case et par modèle, et non par structure. Une case de 0,02° (environ 2 km)
 * suit la maille d'ICON-D2, le modèle retenu le 2026-10-05 (« on part sur ICON ») et que
 * chaque membre lit tant qu'il n'en a pas choisi un autre (`Member::viewWeatherModel`) : deux
 * structures de la même case liraient de toute façon la même prévision. Mesuré le 2026-10-05 en dev sur
 * la lecture de `getCells()`, les 3 166 positions du parc tiennent dans 2 429 cases, contre 882 à
 * 0,25°. La donnée ne dépend d'aucun tenant, et la clé ne porte aucune structure.
 *
 * La source est Open-Meteo, par l'abonnement commercial retenu le 2026-10-05 : l'offre
 * gratuite exclut l'usage commercial. La clé vit dans `secret.c.php`, hors du dépôt
 * (`StructureSetting::$openMeteoKey`). Sans elle, `refresh()` n'appelle rien et le bloc ne paraît
 * nulle part, même si une prévision traîne encore dans le cache.
 */
class WeatherAiLib {

	const float CELL = 0.02;

	/**
	 * Deux fois l'intervalle du cron (`page/cron/weatherAi.p.php`) : un passage manqué ne fait rien
	 * disparaître, deux font expirer la clé, et le bloc se retire au lieu de montrer une prévision
	 * périmée.
	 */
	const int TIMEOUT = 21600;

	/**
	 * Les cases d'une requête. Open-Meteo compte un appel par position, quel que soit le découpage, tant
	 * qu'une position demande au plus 10 variables sur au plus 2 semaines ; au-delà, l'appel compte au
	 * prorata, d'après la grille d'Open-Meteo lue le 2026-10-25. Ici 11 variables sur 8 jours, donc 1,1
	 * appel par position : la taille du lot ne change pas la facture, seulement la longueur de l'URL, qui
	 * reste sous 2 Ko à 50 cases. Une passe sur les 2 429 cases du parc fait 49 requêtes par modèle, soit
	 * 196 requêtes et environ 10 700 appels décomptés pour les quatre.
	 */
	const int BATCH = 50;

	/**
	 * Les heures de la bande, de deux en deux sur la journée de travail (planche B validée). Passé la
	 * dernière, la bande montre le lendemain : la journée est finie, et ce qui reste à décider est
	 * celui du jour suivant.
	 */
	const array BAND = [6, 8, 10, 12, 14, 16, 18, 20];

	/**
	 * Les modèles qu'un membre peut choisir, et ce que chacun couvre, mesuré le 2026-10-05 sur Lyon :
	 * ICON et ECMWF portent huit jours complets, UKMO sept, Météo-France quatre et aucun risque de pluie.
	 * Le cron les demande tous, un par requête : la réponse garde ses clés sans suffixe tant qu'un seul
	 * modèle est demandé, et `parse()` les lit sans suffixe.
	 */
	const array MODELS = [Member::ICON, Member::METEOFRANCE, Member::UKMO, Member::ECMWF];

	/** En dessous, une pluie arrondie au dixième ne se lit pas : la cellule ne dit rien plutôt que « 0 mm ». */
	const float RAIN_MIN = 0.1;

	/**
	 * Les moments du détail d'un jour, par leur première heure, de six heures chacun (planche L96, piste
	 * C3) : la nuit, le matin, l'après-midi et le soir.
	 */
	const array MOMENTS = [0, 6, 12, 18];

	const int MOMENT_HOURS = 6;

	/** Le moment que le détail montre d'abord quand le modèle le couvre : le matin. */
	const int MOMENT_FIRST = 6;

	/** Les jours qu'une tuile ouvre en détail, après aujourd'hui : ceux des tuiles du menu. */
	const int DETAIL_DAYS = 3;

	/** Les jours montrés à partir d'aujourd'hui. */
	const int DAYS = 7;

	/** L'adresse de la source, que le menu du modèle cite : la licence CC BY 4.0 exige l'attribution, abonnement compris. */
	const string SOURCE_URL = 'https://open-meteo.com/';

	/** Le point d'accès réservé aux abonnés, qui seul accepte la clé. */
	const string API_URL = 'https://customer-api.open-meteo.com/v1/forecast';

	/**
	 * Un jour de plus que `DAYS` : le cache vit six heures, et une prévision lue à 23 h doit encore
	 * porter sept jours le lendemain matin.
	 */
	const int FORECAST_DAYS = 8;

	/**
	 * Remplace l'appel à Open-Meteo, pour les tests seulement : aucun test n'atteint le réseau.
	 *
	 * Reçoit une liste de cases `[lng, lat]` et le modèle demandé, et rend, sous les mêmes clés, la
	 * prévision de chacune, dans la forme que rend `parse()`.
	 */
	private static ?\Closure $fetcher = NULL;

	public static function setFetcher(?\Closure $fetcher): void {
		self::$fetcher = $fetcher;
	}

	/**
	 * L'arrondi se fait ici et nulle part ailleurs, en PHP : le cron qui écrit et le bloc qui lit
	 * doivent tomber sur la même clé, et un `ROUND()` SQL n'arrondit pas les demis comme `round()`.
	 *
	 * @param array $lngLat `[longitude, latitude]`, l'ordre que l'ORM rend pour un `point`
	 */
	public static function getCell(array $lngLat): array {

		return [
			round($lngLat[0] / self::CELL) * self::CELL,
			round($lngLat[1] / self::CELL) * self::CELL
		];

	}

	/**
	 * Le préfixe change avec la forme de la prévision : une case de 0,02° dont les deux coordonnées sont
	 * des multiples de 0,5 porte le nom d'une ancienne case de 0,25°, et lirait jusqu'à son expiration une
	 * prévision sans millimètres. Deux cases voisines diffèrent de 0,02 sur un axe, que `%.2f` écrit.
	 *
	 * Le modèle s'y écrit par sa valeur d'enum. `v2` : les heures et les jours portent leur rafale, et les
	 * heures leur nuit (L96), qu'une prévision écrite avant n'a pas, et que le rendu lit.
	 */
	public static function getKey(array $cell, string $model): string {
		return 'weather-v2-'.$model.'-'.self::getCellId($cell);
	}

	/** Une case sans modèle, telle que `getCells()` la range. */
	public static function getCellId(array $cell): string {
		return sprintf('%.2f', $cell[0]).'-'.sprintf('%.2f', $cell[1]);
	}

	/** Le nom du modèle dans l'API d'Open-Meteo. */
	public static function getApiModel(string $model): string {

		return match($model) {
			Member::ICON => 'dwd_icon_seamless',
			Member::METEOFRANCE => 'meteofrance_seamless',
			Member::UKMO => 'ukmo_seamless',
			Member::ECMWF => 'ecmwf_ifs'
		};

	}

	/**
	 * Ce que le bloc montre, à partir de l'heure courante de `\Clock` : la prévision a pu être lue
	 * jusqu'à six heures plus tôt, et ce qui était « maintenant » à l'appel ne l'est plus. `now` est
	 * donc l'heure en cours prise dans `hours`, et une prévision qui ne la couvre pas n'a plus rien à
	 * dire.
	 *
	 * La bande se lit dans toutes les heures du cache et non à partir de maintenant : l'après-midi, elle
	 * garde le matin du jour, comme sur la planche. Une heure que le cache ne porte pas manque à la
	 * bande sans la vider.
	 *
	 * Le modèle est celui que le membre a choisi. S'il ne couvre pas l'heure et le jour en cours, le bloc lit ICON,
	 * et `model` le dit : un modèle qu'Open-Meteo a cessé de servir sur une case ne retire pas la météo de
	 * l'accueil. Un membre de l'équipe qui visite la structure n'en est pas membre : il lit ICON, et
	 * `member` vaut `NULL`, son choix n'ayant aucune ligne où s'écrire.
	 *
	 * `day` porte le détail du jour qu'une tuile ouvre, lu dans la même prévision que la tuile, pour que
	 * l'une et l'autre ne puissent pas venir de deux modèles ou de deux lectures du cache.
	 *
	 * @param string $date le jour ouvert, `''` quand aucun ne l'est
	 */
	public static function getByStructure(Structure $eStructure, Member $eMember = new Member(), string $date = ''): ?array {

		if(StructureSetting::$openMeteoKey === NULL) {
			return NULL;
		}

		$eSystem = SystemLib::getByStructure($eStructure);

		if($eSystem->empty() or $eSystem['placeLngLat'] === NULL) {
			return NULL;
		}

		$chosen = $eMember->empty() ? Member::ICON : $eMember['viewWeatherModel'];
		$forecast = self::read($eSystem['placeLngLat'], $chosen);

		if($forecast === NULL) {
			return NULL;
		}

		['model' => $model, 'hours' => $hours, 'days' => $days] = $forecast;

		$hour = \Clock::format('Y-m-d H:00');
		$today = \Clock::date();

		$tomorrow = date('Y-m-d', \Clock::shift('+1 DAY'));
		$bandDate = ((int)\Clock::format('G') < self::BAND[count(self::BAND) - 1]) ? $today : $tomorrow;

		$band = [];

		foreach(self::BAND as $bandHour) {

			$time = $bandDate.' '.sprintf('%02d', $bandHour).':00';

			if(array_key_exists($time, $hours)) {
				$band[] = $hours[$time];
			}

		}

		return [
			'member' => $eMember->empty() ? NULL : $eMember['id'],
			'chosen' => $chosen,
			'model' => $model,
			'place' => $eSystem['place'],
			'now' => $hours[$hour],
			'band' => $band,
			'days' => array_slice($days, 0, self::DAYS),
			'day' => self::getDay($hours, $days, $date),
			'today' => $today,
			'tomorrow' => $tomorrow
		];

	}

	/**
	 * Les heures d'un des jours qu'ouvrent les tuiles, rangées par moment (`MOMENTS`). Un moment dont
	 * aucune heure n'est couverte manque, et le jour qui n'en a aucun, comme une date qu'aucune tuile ne
	 * montre, rend `NULL`. Les dates admises sont celles des tuiles, lues dans `$days` comme le rendu les
	 * lit (`DashboardUi::getForecast()`).
	 */
	private static function getDay(array $hours, array $days, string $date): ?array {

		$offered = array_column(array_slice($days, 1, self::DETAIL_DAYS), 'date');

		if(in_array($date, $offered, TRUE) === FALSE) {
			return NULL;
		}

		$moments = [];

		foreach(self::MOMENTS as $start) {

			$hoursMoment = [];

			for($hour = $start; $hour < $start + self::MOMENT_HOURS; $hour++) {

				$time = $date.' '.sprintf('%02d', $hour).':00';

				if(array_key_exists($time, $hours)) {
					$hoursMoment[] = $hours[$time] + ['hour' => $hour];
				}

			}

			if($hoursMoment !== []) {
				$moments[$start] = $hoursMoment;
			}

		}

		if($moments === []) {
			return NULL;
		}

		return [
			'date' => $date,
			'moments' => $moments
		];

	}

	/**
	 * La prévision de la case, dans le modèle choisi s'il couvre l'heure et le jour en cours, sinon dans
	 * ICON, sinon `NULL`. Les heures sont rangées par leur `time`, les jours commencent aujourd'hui.
	 */
	private static function read(array $lngLat, string $chosen): ?array {

		$cell = self::getCell($lngLat);
		$hour = \Clock::format('Y-m-d H:00');
		$today = \Clock::date();

		foreach(array_unique([$chosen, Member::ICON]) as $candidate) {

			$forecast = \Cache::redis()->get(self::getKey($cell, $candidate));

			if(is_array($forecast)) {

				$hours = array_column(array_map(self::prepareRain(...), $forecast['hours']), NULL, 'time');
				$days = array_map(self::prepareRain(...), array_values(array_filter($forecast['days'], fn(array $entry) => ($entry['date'] >= $today))));

				// La phrase du moment lit le minimum du jour dans la première tuile : un modèle qui porte
				// l'heure sans porter le jour ne la couvre pas.
				if(array_key_exists($hour, $hours) and $days !== [] and $days[0]['date'] === $today) {
					return [
						'model' => $candidate,
						'hours' => $hours,
						'days' => $days
					];
				}

			}

		}

		return NULL;

	}

	/**
	 * Le cache garde les millimètres que rend la source, l'arrondi et le seuil sont ceux de l'affichage :
	 * `mm` vaut `NULL` quand il n'y a rien à lire. La source peut aussi rendre `NULL` sur une heure.
	 */
	private static function prepareRain(array $entry): array {

		$mm = ($entry['mm'] === NULL) ? NULL : round($entry['mm'], 1);
		$entry['mm'] = ($mm !== NULL and $mm >= self::RAIN_MIN) ? $mm : NULL;

		return $entry;

	}

	/**
	 * Les cases de toutes les positions connues, rangées par `getCellId()`. Une seule lecture des
	 * coordonnées, et l'arrondi en PHP par `getCell()` pour la raison qu'il donne. `ST_Longitude()`/`ST_Latitude()` et non
	 * `ST_X()`/`ST_Y()` : en SRID 4326, MySQL range la latitude en premier, et `ST_X()` la rend.
	 */
	public static function getCells(): array {

		$cSystem = System::model()
			->select([
				'lng' => new \Sql('ST_Longitude(placeLngLat)', 'float'),
				'lat' => new \Sql('ST_Latitude(placeLngLat)', 'float')
			])
			->where('placeLngLat IS NOT NULL')
			->getCollection();

		$cells = [];

		foreach($cSystem as $eSystem) {
			$cell = self::getCell([$eSystem['lng'], $eSystem['lat']]);
			$cells[self::getCellId($cell)] = $cell;
		}

		return $cells;

	}

	/**
	 * Rend le nombre de prévisions écrites, une par case et par modèle. Sans clé, rien n'est demandé ni
	 * écrit.
	 *
	 * Une requête qui échoue lève et interrompt la passe : les clés déjà écrites restent, les autres
	 * gardent la prévision du passage précédent jusqu'à leur expiration, et le passage suivant reprend
	 * tout. ICON passe en premier, parce que c'est lui que lit un membre dont le modèle manque.
	 *
	 * @param array $cells `getCellId()` => `[lng, lat]`, la forme que rend `getCells()`
	 */
	public static function refresh(array $cells): int {

		if(self::$fetcher === NULL and StructureSetting::$openMeteoKey === NULL) {
			return 0;
		}

		$written = 0;

		foreach(self::MODELS as $model) {

			foreach(array_chunk($cells, self::BATCH, TRUE) as $batch) {

				$forecasts = (self::$fetcher === NULL) ? self::fetch($batch, $model) : (self::$fetcher)($batch, $model);

				foreach($forecasts as $cellId => $forecast) {
					\Cache::redis()->set(self::getKey($batch[$cellId], $model), $forecast, self::TIMEOUT);
					$written++;
				}

			}

		}

		return $written;

	}

	private static function fetch(array $batch, string $model): array {

		$curl = new \util\CurlLib();

		$response = $curl->exec(self::API_URL, self::getQuery($batch, $model));

		$httpCode = $curl->getLastInfos()['httpCode'];
		$body = json_decode($response, TRUE);

		if($httpCode !== 200 or is_array($body) === FALSE) {
			throw new \Exception('Open-Meteo error (HTTP code is '.$httpCode.')');
		}

		return self::parse(array_keys($batch), $body);

	}

	/**
	 * Les heures et les dates sont demandées dans le fuseau de `\Clock`, qui est celui du serveur :
	 * `getByStructure()` les compare à son heure courante, et les deux doivent parler du même fuseau.
	 * Pour la trentaine de positions hors de métropole, les heures montrées sont donc celles de Paris.
	 */
	public static function getQuery(array $batch, string $model): array {

		return [
			'latitude' => implode(',', array_map(fn(array $cell) => sprintf('%.2f', $cell[1]), $batch)),
			'longitude' => implode(',', array_map(fn(array $cell) => sprintf('%.2f', $cell[0]), $batch)),
			'hourly' => 'temperature_2m,weather_code,precipitation_probability,precipitation,wind_speed_10m,wind_gusts_10m,is_day',
			'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
			'models' => self::getApiModel($model),
			'timezone' => date_default_timezone_get(),
			'forecast_days' => self::FORECAST_DAYS,
			'apikey' => StructureSetting::$openMeteoKey
		];

	}

	/**
	 * Ramène la réponse d'Open-Meteo à la forme du cache, sous les clés de cache des cases demandées.
	 *
	 * Open-Meteo rend une liste de positions dans l'ordre de la requête, mais un objet seul quand la
	 * requête n'en porte qu'une : le dernier lot d'une passe peut ne compter qu'une case. Ses heures
	 * arrivent en `2026-10-05T08:00`, que le cache écrit `2026-10-05 08:00`, la forme de
	 * `\Clock::format('Y-m-d H:00')`.
	 *
	 * Un modèle qui ne couvre pas toute la fenêtre rend `NULL` au-delà de sa portée, Météo-France dès le
	 * quatrième jour au soir. Une heure sans température, ciel, vent ou jour et un jour sans minimum, maximum ou
	 * ciel ne s'écrivent pas : le rendu les montre tous. Le risque de pluie peut manquer partout
	 * (Météo-France), et reste alors `NULL`, comme la rafale d'une heure.
	 *
	 * Les millimètres et la rafale d'un jour se tirent de ses heures, que la source donne de toute façon,
	 * et non de `precipitation_sum` et `wind_gusts_10m_max` : chaque variable de plus fait compter
	 * chaque position un dixième d'appel de plus (`BATCH`). Les heures qu'une valeur manquante écarte
	 * comptent quand même, l'eau tombée et le vent n'en dépendant pas. Un jour dont aucune heure ne
	 * porte de valeur la laisse à `NULL`.
	 *
	 * @param array $keys les clés des cases, dans l'ordre des positions de la requête
	 */
	public static function parse(array $keys, array $body): array {

		$locations = array_is_list($body) ? $body : [$body];

		$forecasts = [];

		foreach($keys as $position => $key) {

			$hourly = $locations[$position]['hourly'];
			$daily = $locations[$position]['daily'];

			$hours = [];
			$mmByDate = [];
			$gustByDate = [];

			foreach($hourly['time'] as $index => $time) {

				$date = substr($time, 0, 10);

				if($hourly['precipitation'][$index] !== NULL) {
					$mmByDate[$date] = ($mmByDate[$date] ?? 0.0) + $hourly['precipitation'][$index];
				}

				if($hourly['wind_gusts_10m'][$index] !== NULL) {
					$gustByDate[$date] = max($gustByDate[$date] ?? 0.0, $hourly['wind_gusts_10m'][$index]);
				}

				if(
					$hourly['temperature_2m'][$index] === NULL or
					$hourly['weather_code'][$index] === NULL or
					$hourly['wind_speed_10m'][$index] === NULL or
					$hourly['is_day'][$index] === NULL
				) {
					continue;
				}

				$hours[] = [
					'time' => str_replace('T', ' ', $time),
					'temperature' => $hourly['temperature_2m'][$index],
					'code' => $hourly['weather_code'][$index],
					'rain' => $hourly['precipitation_probability'][$index],
					'mm' => $hourly['precipitation'][$index],
					'wind' => $hourly['wind_speed_10m'][$index],
					'gust' => $hourly['wind_gusts_10m'][$index],
					'day' => ($hourly['is_day'][$index] === 1)
				];

			}

			$days = [];

			foreach($daily['time'] as $index => $date) {

				if(
					$daily['temperature_2m_min'][$index] === NULL or
					$daily['temperature_2m_max'][$index] === NULL or
					$daily['weather_code'][$index] === NULL
				) {
					continue;
				}

				$days[] = [
					'date' => $date,
					'min' => $daily['temperature_2m_min'][$index],
					'max' => $daily['temperature_2m_max'][$index],
					'code' => $daily['weather_code'][$index],
					'rain' => $daily['precipitation_probability_max'][$index],
					'mm' => $mmByDate[$date] ?? NULL,
					'gust' => $gustByDate[$date] ?? NULL
				];

			}

			$forecasts[$key] = ['hours' => $hours, 'days' => $days];

		}

		return $forecasts;

	}

}
?>
