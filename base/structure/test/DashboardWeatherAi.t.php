<?php
namespace structure;

/**
 * La météo du groupe Bureau du menu et le cache qui le nourrit (`WeatherAiLib`).
 *
 * Aucun appel réseau. Chaque méthode verrouille `\util\CurlLib`, qui lève avant toute requête : un
 * test qui atteindrait Open-Meteo échoue au lieu d'appeler. La clé posée est factice, et le cron est
 * éprouvé par un `fetcher` remplacé. La prévision du décor est écrite dans la forme de la réponse
 * d'Open-Meteo puis lue par `parse()`, pour que le cache éprouvé soit celui que le vrai client écrit.
 *
 * Les clés écrites dans Redis sont celles des cases du décor, F6 étant posée à Tallende comme la 7 :
 * chaque méthode rend en sortant, qu'elle réussisse ou non, ce qu'elle y a trouvé, la prévision réelle
 * que l'accueil de la 7 lit en dev comme l'absence. La clé d'abonnement, le `fetcher` et le verrou sont
 * rendus à leur état d'avant de la même façon.
 *
 * Deux structures du décor : F6, posée à Tallende (`[3.13, 45.67]`), et F1, sans position. Le membre qui
 * choisit un modèle n'est pas lu en base : `getByStructure()` ne lit de lui que son identifiant et son
 * choix, que les cas lui donnent.
 *
 * Lancement : dev/test package=structure file=DashboardWeatherAi
 */
class DashboardWeatherAiTest extends \Test {

	/** La case de Tallende : 3.13 / 0.02 = 156.5, arrondi à 157, soit 3.14 ; 45.67 / 0.02 = 2283.5, soit 45.68 */
	const string KEY = 'weather-icon-3.14-45.68';

	/** La case voisine à l'ouest, à 0,02° : `%.2f` les distingue. */
	const string KEY_WEST = 'weather-icon-3.12-45.68';

	/** La case de Tallende sans modèle, comme `getCells()` la range. */
	const string CELL = '3.14-45.68';

	const string CELL_WEST = '3.12-45.68';

	public function testCellRoundsBothAxesInLongitudeLatitudeOrder(): void {

		$this->assertEquals([3.14, 45.68], WeatherAiLib::getCell([3.13, 45.67]));
		$this->assertEquals(self::KEY, WeatherAiLib::getKey(WeatherAiLib::getCell([3.13, 45.67]), Member::ICON));
		$this->assertEquals(self::KEY, WeatherAiLib::getKey(WeatherAiLib::getCell([3.135, 45.675]), Member::ICON), 'une voisine à quelques centaines de mètres lit la même clé');
		$this->assertEquals(self::KEY_WEST, WeatherAiLib::getKey(WeatherAiLib::getCell([3.11, 45.67]), Member::ICON), 'à un kilomètre et demi à l\'ouest, la case d\'à côté');

	}

	/** Une clé par modèle, et celle d'ICON garde le nom qu'elle portait quand il était le seul. */
	public function testEachModelHasItsKey(): void {

		$keys = array_map(fn(string $model) => WeatherAiLib::getKey([3.14, 45.68], $model), WeatherAiLib::MODELS);

		$this->assertEquals(['weather-icon-3.14-45.68', 'weather-meteofrance-3.14-45.68', 'weather-ukmo-3.14-45.68', 'weather-ecmwf-3.14-45.68'], $keys);

	}

	public function testWithoutPlaceTheBlockSaysNothing(): void {

		$this->withOpenMeteoKey(function() {
			$this->assertEquals(NULL, WeatherAiLib::getByStructure($this->getStructure(FixtureAiLib::F1)));
		});

	}

	/**
	 * La prévision a été lue à 5 h, il est 8 h 30 : `now` est l'heure de 8 h prise dans les heures, et
	 * ce qui précède ne se montre plus.
	 */
	public function testTheForecastStartsAtTheCurrentHour(): void {

		$this->withOpenMeteoKey(function() {

			$this->setClock('2026-10-05 08:30:00');

			\Cache::redis()->set(self::KEY, $this->getForecast('2026-10-05T05:00', '2026-10-04'), WeatherAiLib::TIMEOUT);

			$values = WeatherAiLib::getByStructure($this->getStructure(FixtureAiLib::F6));

			$this->assertEquals('2026-10-05 08:00', $values['now']['time']);
			$this->assertEquals('Tallende', $values['place']);
			$this->assertEquals(['2026-10-05 06:00', '2026-10-05 20:00'], [$values['band'][0]['time'], $values['band'][7]['time']], 'la bande garde le matin du jour');
			$this->assertEquals(count(WeatherAiLib::BAND), count($values['band']));
			$this->assertEquals('2026-10-05', $values['days'][0]['date'], 'la veille ne se montre plus');
			$this->assertEquals(WeatherAiLib::DAYS, count($values['days']));
			$this->assertEquals(['2026-10-05', '2026-10-06'], [$values['today'], $values['tomorrow']]);

			$this->assertEquals($values, DashboardLib::load('structure-weather', $this->getStructure(FixtureAiLib::F6)), 'le contrat du socle rend la même chose');
			$this->assertEquals(WeatherAiLib::SOURCE_URL, DashboardLib::getUrl('structure-weather', $this->getStructure(FixtureAiLib::F6), NULL));

		});

	}

	/** Passé 20 h, la bande montre le lendemain ; une heure que le cache ne porte pas manque sans la vider. */
	public function testAfterTheLastHourTheBandShowsTomorrow(): void {

		$this->withOpenMeteoKey(function() {

			$this->setClock('2026-10-05 21:10:00');

			\Cache::redis()->set(self::KEY, $this->getForecast('2026-10-05T07:00', '2026-10-05'), WeatherAiLib::TIMEOUT);

			$values = WeatherAiLib::getByStructure($this->getStructure(FixtureAiLib::F6));

			$this->assertEquals('2026-10-06 06:00', $values['band'][0]['time']);
			$this->assertEquals(count(WeatherAiLib::BAND), count($values['band']));

			$this->setClock('2026-10-05 08:30:00');

			$values = WeatherAiLib::getByStructure($this->getStructure(FixtureAiLib::F6));

			$this->assertEquals('2026-10-05 08:00', $values['band'][0]['time'], 'le cache commence à 7 h : 6 h manque');
			$this->assertEquals(count(WeatherAiLib::BAND) - 1, count($values['band']));

		});

	}

	/**
	 * La prévision commence à 5 h, donc 8 h est la quatrième heure et 10 h la sixième. Une heure sèche
	 * ne dit rien, une heure à 0,4 mm le dit, et 0,04 mm reste sous le seuil.
	 */
	public function testRainIsShownFromATenthOfAMillimetre(): void {

		$this->withOpenMeteoKey(function() {

			$this->setClock('2026-10-05 08:30:00');

			\Cache::redis()->set(self::KEY, $this->getForecast('2026-10-05T05:00', '2026-10-05', [3 => 0.4, 5 => 0.04]), WeatherAiLib::TIMEOUT);

			$eStructure = $this->getStructure(FixtureAiLib::F6);
			$values = WeatherAiLib::getByStructure($eStructure);

			$this->assertEquals([NULL, 0.4, NULL], [$values['band'][0]['mm'], $values['band'][1]['mm'], $values['band'][2]['mm']], '6 h sèche, 8 h à 0,4 mm, 10 h sous le seuil');
			$this->assertEquals([4.3, NULL], [$values['days'][0]['mm'], $values['days'][1]['mm']], 'arrondi au dixième, puis sous le seuil');

		});

	}

	/** Une prévision qui ne couvre pas l'heure en cours n'a rien à dire, et une clé absente non plus. */
	public function testAStaleOrMissingForecastSaysNothing(): void {

		$this->withOpenMeteoKey(function() {

			$this->setClock('2026-10-08 08:30:00');

			$this->assertEquals(NULL, WeatherAiLib::getByStructure($this->getStructure(FixtureAiLib::F6)), 'aucune clé');

			\Cache::redis()->set(self::KEY, $this->getForecast('2026-10-05T05:00', '2026-10-04'), WeatherAiLib::TIMEOUT);

			$this->assertEquals(NULL, WeatherAiLib::getByStructure($this->getStructure(FixtureAiLib::F6)), 'les 48 heures sont passées');

			// Le cas que seule la garde sur la première heure attrape : la liste filtrée n'est pas vide,
			// mais elle commence à 5 h quand il est 3 h 30, et 5 h n'est pas « maintenant ».
			$this->setClock('2026-10-05 03:30:00');

			$this->assertEquals(NULL, WeatherAiLib::getByStructure($this->getStructure(FixtureAiLib::F6)), 'la prévision ne couvre pas encore l\'heure en cours');

		});

	}

	/**
	 * La météo est seule dans le groupe Bureau du menu depuis que le chiffre de la semaine est revenu
	 * sous la date (L62) : sans prévision le menu ne porte rien, avec une prévision il ne porte qu'elle.
	 */
	public function testTheMenuCarriesTheWeatherAlone(): void {

		$cUser = MemberLib::getUsersByStructure($this->getStructure(FixtureAiLib::F6));

		if($this->assertTrue($cUser->notEmpty(), 'F6 a une équipe') === FALSE) {
			return;
		}

		// Le poste ne montre une pièce qu'au membre qui a le droit de la lire, et aucune session n'en
		// met un en ligne en ligne de commande.
		$eUserOnline = \user\ConnectionLib::getOnline();
		\user\ConnectionLib::setOnline($cUser->first());

		try {

			$this->withOpenMeteoKey(function() {

				$this->setClock('2026-10-05 08:30:00');

				$eStructure = $this->getStructure(FixtureAiLib::F6);

				$this->assertEquals([], \dashboard\DashboardLib::loadHome($eStructure), 'aucune prévision, rien au menu');

				\Cache::redis()->set(self::KEY, $this->getForecast('2026-10-05T05:00', '2026-10-05'), WeatherAiLib::TIMEOUT);

				$home = \dashboard\DashboardLib::loadHome($eStructure);

				$this->assertEquals([\dashboard\DashboardSetting::WEATHER], array_keys($home), 'la météo, et rien d\'autre');
				$this->assertEquals(['structure-weather'], array_keys($home[\dashboard\DashboardSetting::WEATHER]));

			});

		} finally {

			\user\ConnectionLib::setOnline($eUserOnline);

		}

	}

	/** Sans clé d'abonnement, rien ne se montre, même avec une prévision en cache, et le cron n'appelle rien. */
	public function testWithoutKeyNothingIsShownNorCalled(): void {

		$this->withOpenMeteoKey(function() {

			StructureSetting::$openMeteoKey = NULL;

			$this->setClock('2026-10-05 08:30:00');

			\Cache::redis()->set(self::KEY, $this->getForecast('2026-10-05T05:00', '2026-10-04'), WeatherAiLib::TIMEOUT);

			$this->assertEquals(NULL, WeatherAiLib::getByStructure($this->getStructure(FixtureAiLib::F6)));

			// Le réseau est verrouillé : un appel lèverait ici au lieu de rendre 0.
			$this->assertEquals(0, WeatherAiLib::refresh([self::CELL => [3.14, 45.68]]));

		});

	}

	/** Avec la clé et sans `fetcher`, le cron appelle le point d'accès des abonnés, que le verrou arrête. */
	public function testWithKeyTheCronCallsTheCustomerApi(): void {

		$this->withOpenMeteoKey(function() {

			try {
				WeatherAiLib::refresh([self::CELL => [3.14, 45.68]]);
				$this->assertFail('le cron aurait dû appeler Open-Meteo');
			} catch(\Exception $e) {
				$this->assertEquals('HTTP requests are locked, customer-api.open-meteo.com cannot be reached', $e->getMessage());
			}

			$this->assertFalse(\Cache::redis()->exists(self::KEY));

		});

	}

	public function testQueryCarriesEveryCellAndTheKey(): void {

		$this->withOpenMeteoKey(function() {

			$query = WeatherAiLib::getQuery([self::CELL => [3.14, 45.68], self::CELL_WEST => [3.12, 45.68]], Member::ICON);

			$this->assertEquals('45.68,45.68', $query['latitude']);
			$this->assertEquals('3.14,3.12', $query['longitude'], 'la longitude dans le même ordre que la latitude');
			$this->assertEquals('dwd_icon_seamless', $query['models'], 'ICON demandé, pas le choix automatique');
			$this->assertTrue(str_contains($query['hourly'], ',precipitation,') and str_ends_with($query['daily'], ',precipitation_sum'), 'les millimètres des heures et des jours');
			$this->assertEquals('test', $query['apikey']);
			$this->assertEquals(WeatherAiLib::FORECAST_DAYS, $query['forecast_days']);
			$this->assertEquals(date_default_timezone_get(), $query['timezone'], 'le fuseau de \Clock');

			$models = array_map(fn(string $model) => WeatherAiLib::getQuery([self::CELL => [3.14, 45.68]], $model)['models'], WeatherAiLib::MODELS);

			$this->assertEquals(['dwd_icon_seamless', 'meteofrance_seamless', 'ukmo_seamless', 'ecmwf_ifs'], $models, 'un seul modèle par requête, celui de la passe');

		});

	}

	/**
	 * La forme de Météo-France : aucun risque de pluie, et rien au-delà de sa portée. Les heures et les
	 * jours vides ne s'écrivent pas, le risque absent reste `NULL`.
	 */
	public function testParseSkipsWhatTheModelDoesNotCover(): void {

		$response = $this->getResponse('2026-10-05T05:00', '2026-10-05', 12.0);

		$response['hourly']['precipitation_probability'] = array_fill(0, 48, NULL);
		$response['daily']['precipitation_probability_max'] = array_fill(0, 9, NULL);

		for($i = 40; $i < 48; $i++) {
			$response['hourly']['temperature_2m'][$i] = NULL;
			$response['hourly']['weather_code'][$i] = NULL;
		}

		for($i = 4; $i < 9; $i++) {
			$response['daily']['temperature_2m_max'][$i] = NULL;
			$response['daily']['temperature_2m_min'][$i] = NULL;
		}

		$forecast = WeatherAiLib::parse([self::CELL], $response)[self::CELL];

		$this->assertEquals(40, count($forecast['hours']), 'les huit heures vides ne s\'écrivent pas');
		$this->assertEquals(4, count($forecast['days']), 'les jours sans température ne s\'écrivent pas');
		$this->assertEquals(NULL, $forecast['days'][0]['rain'], 'un risque absent reste absent');

	}

	/** Le membre lit le modèle qu'il a choisi, et le menu poste ce choix. */
	public function testTheChosenModelIsRead(): void {

		$this->withOpenMeteoKey(function() {

			$this->setClock('2026-10-05 08:30:00');

			\Cache::redis()->set(self::KEY, $this->getForecast('2026-10-05T05:00', '2026-10-05'), WeatherAiLib::TIMEOUT);
			\Cache::redis()->set(WeatherAiLib::getKey([3.14, 45.68], Member::METEOFRANCE), $this->getForecast('2026-10-05T05:00', '2026-10-05', temperature: 20.0, withRain: FALSE), WeatherAiLib::TIMEOUT);

			$eStructure = $this->getStructure(FixtureAiLib::F6);
			$values = WeatherAiLib::getByStructure($eStructure, new Member(['id' => 1, 'viewWeatherModel' => Member::METEOFRANCE]));

			$this->assertEquals([Member::METEOFRANCE, Member::METEOFRANCE, 1], [$values['model'], $values['chosen'], $values['member']]);
			$this->assertEquals(20.3, $values['now']['temperature'], 'la prévision lue est celle de Météo-France');

			$html = new DashboardUi()->render('structure-weather', $eStructure, $values);

			$this->assertTrue(str_contains($html, 'post-view-weather-model="'.Member::METEOFRANCE.'"'), 'le menu poste le choix');

		});

	}

	/**
	 * Un modèle choisi qui manque se replie sur ICON. Un visiteur de l'équipe lit ICON sans menu.
	 */
	public function testAMissingModelFallsBackToIcon(): void {

		$this->withOpenMeteoKey(function() {

			$this->setClock('2026-10-05 08:30:00');

			\Cache::redis()->set(self::KEY, $this->getForecast('2026-10-05T05:00', '2026-10-05'), WeatherAiLib::TIMEOUT);

			$eStructure = $this->getStructure(FixtureAiLib::F6);
			$values = WeatherAiLib::getByStructure($eStructure, new Member(['id' => 1, 'viewWeatherModel' => Member::UKMO]));

			$this->assertEquals([Member::ICON, Member::UKMO], [$values['model'], $values['chosen']], 'ICON lu, UKMO toujours choisi');

			$html = new DashboardUi()->render('structure-weather', $eStructure, $values);

			$this->assertTrue(str_contains($html, 'post-view-weather-model="'.Member::UKMO.'"'), 'le menu poste le choix');

			// Il ne manque qu'à cette heure-ci : Météo-France ne couvre que les 4 premières heures.
			\Cache::redis()->set(WeatherAiLib::getKey([3.14, 45.68], Member::METEOFRANCE), $this->getForecast('2026-10-05T01:00', '2026-10-05', hours: 4), WeatherAiLib::TIMEOUT);

			$values = WeatherAiLib::getByStructure($eStructure, new Member(['id' => 1, 'viewWeatherModel' => Member::METEOFRANCE]));

			$this->assertEquals(Member::ICON, $values['model'], 'un modèle qui ne couvre pas l\'heure se replie');

			$values = WeatherAiLib::getByStructure($eStructure);

			$this->assertEquals([Member::ICON, NULL], [$values['model'], $values['member']]);

			$html = new DashboardUi()->render('structure-weather', $eStructure, $values);

			$this->assertFalse(str_contains($html, 'doUpdateWeatherModel'), 'un visiteur sans ligne de membre n\'a pas de menu');

		});

	}

	/** Le choix passe par `build()`, qui refuse un modèle hors de l'enum. */
	public function testBuildRefusesAnUnknownModel(): void {

		$fw = new \FailWatch();

		$eMember = new Member();
		$eMember->build(['viewWeatherModel'], ['viewWeatherModel' => 'gfs']);

		$this->assertTrue($fw->has('Member::viewWeatherModel.check'), 'un modèle inconnu passe');

		$fw = new \FailWatch();

		$eMember->build(['viewWeatherModel'], ['viewWeatherModel' => Member::ECMWF]);

		$this->assertTrue($fw->ok());
		$this->assertEquals(Member::ECMWF, $eMember['viewWeatherModel']);

	}

	/** Open-Meteo rend une liste pour plusieurs positions, un objet seul pour une seule. */
	public function testParseFollowsTheOrderOfTheRequest(): void {

		$east = $this->getResponse('2026-10-05T05:00', '2026-10-05', 12.0);
		$west = $this->getResponse('2026-10-05T05:00', '2026-10-05', 7.0);

		$forecasts = WeatherAiLib::parse([self::KEY, self::KEY_WEST], [$east, $west]);

		$this->assertEquals([self::KEY, self::KEY_WEST], array_keys($forecasts));
		$this->assertEquals(12.0, $forecasts[self::KEY]['hours'][0]['temperature']);
		$this->assertEquals(7.0, $forecasts[self::KEY_WEST]['hours'][0]['temperature']);
		$this->assertEquals('2026-10-05 05:00', $forecasts[self::KEY]['hours'][0]['time']);
		$this->assertEquals(['date' => '2026-10-05', 'min' => 6.0, 'max' => 16.0, 'code' => 3, 'rain' => 40, 'mm' => 4.32], $forecasts[self::KEY]['days'][0]);

		$forecasts = WeatherAiLib::parse([self::KEY_WEST], $west);

		$this->assertEquals([self::KEY_WEST], array_keys($forecasts));
		$this->assertEquals(7.0, $forecasts[self::KEY_WEST]['hours'][0]['temperature']);

	}

	/** Le cron demande chaque modèle à son tour, ICON d'abord, et n'écrit que ce que la source rend. */
	public function testRefreshWritesWhatTheFetcherReturns(): void {

		$cells = [self::CELL => [3.14, 45.68], self::CELL_WEST => [3.12, 45.68]];

		$this->withOpenMeteoKey(function() use ($cells) {

			$received = [];

			WeatherAiLib::setFetcher(function(array $batch, string $model) use (&$received) {
				$received[$model] = $batch;
				return [
					self::CELL => $this->getForecast('2026-10-05T05:00', '2026-10-04'),
					self::CELL_WEST => $this->getForecast('2026-10-05T05:00', '2026-10-04')
				];
			});

			$this->assertEquals(8, WeatherAiLib::refresh($cells), 'deux cases rendues par modèle, huit écrites');
			$this->assertEquals(WeatherAiLib::MODELS, array_keys($received), 'chaque modèle, ICON d\'abord');
			$this->assertEquals($cells, $received[Member::ICON], 'les cases partent avec leur clé');

			foreach(WeatherAiLib::MODELS as $model) {
				$this->assertTrue(\Cache::redis()->exists(WeatherAiLib::getKey([3.14, 45.68], $model)), 'la case de Tallende manque pour '.$model);
				$this->assertTrue(\Cache::redis()->exists(WeatherAiLib::getKey([3.12, 45.68], $model)), 'la case voisine manque pour '.$model);
			}

		});

	}

	/** La lecture du parc, en lecture seule : la case de F6 y est, et rien n'en est écrit. */
	public function testCellsCoverEveryPosition(): void {

		$cells = WeatherAiLib::getCells();

		$this->assertTrue(array_key_exists(self::CELL, $cells));
		$this->assertEquals([3.14, 45.68], $cells[self::CELL], 'longitude d\'abord, pas la latitude que `ST_X()` rendrait');

	}

	/**
	 * Pose une clé factice et verrouille le réseau le temps de `$callback`, puis rend la clé, le
	 * `fetcher`, le verrou et les clés de cache des deux cases du décor, pour chaque modèle, à leur état
	 * d'avant, chaque méthode partant de cases vides. Une prévision trouvée
	 * repart avec la durée de vie pleine : sa durée restante n'est pas lue, et six heures de plus en dev
	 * ne trompent personne.
	 */
	private function withOpenMeteoKey(\Closure $callback): void {

		$key = StructureSetting::$openMeteoKey;
		$found = [];

		foreach(WeatherAiLib::MODELS as $model) {
			foreach([[3.14, 45.68], [3.12, 45.68]] as $cell) {
				$cacheKey = WeatherAiLib::getKey($cell, $model);
				$found[$cacheKey] = \Cache::redis()->get($cacheKey);
			}
		}

		foreach(array_keys($found) as $cacheKey) {
			\Cache::redis()->delete($cacheKey);
		}

		StructureSetting::$openMeteoKey = 'test';
		\util\CurlLib::setLocked(TRUE);

		try {
			$callback();
		} finally {
			StructureSetting::$openMeteoKey = $key;
			WeatherAiLib::setFetcher(NULL);
			\util\CurlLib::setLocked(FALSE);
			foreach($found as $cacheKey => $forecast) {

				if(is_array($forecast)) {
					\Cache::redis()->set($cacheKey, $forecast, WeatherAiLib::TIMEOUT);
				} else {
					\Cache::redis()->delete($cacheKey);
				}

			}
		}

	}

	private function getStructure(int $structure): Structure {

		return Structure::model()
			->select(Structure::getSelection())
			->whereId($structure)
			->get();

	}

	/**
	 * La prévision d'une case telle que le cron l'écrit, lue depuis une réponse d'Open-Meteo. Sans
	 * `$withRain`, la réponse n'a aucun risque de pluie, comme celle de Météo-France.
	 */
	private function getForecast(string $hour, string $day, array $rain = [], float $temperature = 10.0, bool $withRain = TRUE, int $hours = 48): array {

		$response = $this->getResponse($hour, $day, $temperature, $rain, $hours);

		if($withRain === FALSE) {
			$response['hourly']['precipitation_probability'] = array_fill(0, $hours, NULL);
			$response['daily']['precipitation_probability_max'] = array_fill(0, 9, NULL);
		}

		return WeatherAiLib::parse([self::CELL], $response)[self::CELL];

	}

	/**
	 * Une position de la réponse d'Open-Meteo, dans sa forme documentée : `$hours` heures à partir de `$hour`
	 * et 9 jours à partir de `$day`, en colonnes parallèles. Il pleut 4,32 mm le premier jour et 0,04 mm
	 * les suivants, sous le seuil ; les heures sont sèches sauf celles que `$rain` mouille, par rang.
	 */
	private function getResponse(string $hour, string $day, float $temperature, array $rain = [], int $hours = 48): array {

		$hourly = ['time' => [], 'temperature_2m' => [], 'weather_code' => [], 'precipitation_probability' => [], 'precipitation' => [], 'wind_speed_10m' => []];

		for($i = 0; $i < $hours; $i++) {
			$hourly['time'][] = date('Y-m-d\TH:00', strtotime($hour.' + '.$i.' hours'));
			$hourly['temperature_2m'][] = $temperature + $i / 10;
			$hourly['weather_code'][] = 3;
			$hourly['precipitation_probability'][] = 20;
			$hourly['precipitation'][] = $rain[$i] ?? 0.0;
			$hourly['wind_speed_10m'][] = 12.0;
		}

		$daily = ['time' => [], 'weather_code' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => [], 'precipitation_probability_max' => [], 'precipitation_sum' => []];

		for($i = 0; $i < 9; $i++) {
			$daily['time'][] = date('Y-m-d', strtotime($day.' + '.$i.' days'));
			$daily['weather_code'][] = 3;
			$daily['temperature_2m_max'][] = 16.0;
			$daily['temperature_2m_min'][] = 6.0;
			$daily['precipitation_probability_max'][] = 40;
			$daily['precipitation_sum'][] = ($i === 0) ? 4.32 : 0.04;
		}

		return ['latitude' => 45.75, 'longitude' => 3.25, 'timezone' => 'Europe/Paris', 'hourly' => $hourly, 'daily' => $daily];

	}

}
?>
