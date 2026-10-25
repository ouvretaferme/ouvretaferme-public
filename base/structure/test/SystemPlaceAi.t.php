<?php
namespace structure;

/**
 * La commune, le département et l'intercommunalité que le lieu de production tire de son point
 * (`SystemLib::prepareCodes()`, `main\PlaceLib::getCodesByLngLat()`).
 *
 * Aucun cas n'appelle geo.api.gouv.fr : un test ne dépend pas du réseau. L'appel est coupé par
 * `CurlLib::setLocked()`, qui le fait échouer comme une panne, et la lecture de la réponse s'éprouve
 * sur une réponse enregistrée le 22/10/2026. Un cas qui verrait l'appel partir le verrait donc
 * échouer et rendre des codes à NULL : c'est ce qui prouve qu'un point inchangé ne rappelle pas.
 *
 * Le décor vit dans la transaction de la méthode, sur le système de `FixtureAiLib::F1`.
 *
 * Lancement : dev/test package=structure file=SystemPlaceAi
 */
class SystemPlaceAiTest extends \dev\TransactionTest {

	/** Gap, tel que geo.api.gouv.fr le rend pour le point [6.08, 44.56] */
	const GAP = ['code' => '05061', 'codeDepartement' => '05', 'codeEpci' => '200067825', 'nom' => 'Gap'];

	const POINT_GAP = [6.08, 44.56];
	const POINT_SISTERON = [5.94, 44.2];

	protected function getTransactionModel(): \ModuleModel {
		return System::model();
	}

	public function tearDown(): void {

		parent::tearDown();

		// Le verrou et la réponse sont statiques : laissés posés, ils joueraient pour les fichiers lancés ensuite.
		\util\CurlLib::setLocked(FALSE);
		\main\PlaceLib::setFetcher(NULL);

	}

	public function testExtractCodes(): void {

		$this->assertEquals(
			['placeCity' => '05061', 'placeDepartment' => '05', 'placeEpci' => '200067825'],
			\main\PlaceLib::extractCodes([self::GAP]),
			'la réponse pour Gap donne sa commune, son département et son intercommunalité'
		);

		$this->assertEquals(
			['placeCity' => '85113', 'placeDepartment' => '85', 'placeEpci' => NULL],
			\main\PlaceLib::extractCodes([['code' => '85113', 'codeDepartement' => '85', 'nom' => 'L\'Île-d\'Yeu']]),
			'une commune sans intercommunalité garde son département'
		);

		$this->assertEquals(
			['placeCity' => '2A004', 'placeDepartment' => '2A', 'placeEpci' => '242010056'],
			\main\PlaceLib::extractCodes([['code' => '2A004', 'codeDepartement' => '2A', 'codeEpci' => '242010056']]),
			'la Corse garde ses lettres'
		);

		$this->assertEquals(\main\PlaceLib::CODES_UNKNOWN, \main\PlaceLib::extractCodes([]), 'un point hors de France ne rend aucune commune');
		$this->assertEquals(\main\PlaceLib::CODES_UNKNOWN, \main\PlaceLib::extractCodes([['code' => '05061']]), 'une commune sans département ne vaut rien');
		$this->assertEquals(\main\PlaceLib::CODES_UNKNOWN, \main\PlaceLib::extractCodes([['codeDepartement' => '05', 'code' => '<b>5']]), 'un code de commune mal formé est refusé');
		$this->assertEquals(\main\PlaceLib::CODES_UNKNOWN, \main\PlaceLib::extractCodes([['code' => '05061', 'codeDepartement' => '99']]), 'un département qui n\'existe pas est refusé');
		$this->assertEquals(\main\PlaceLib::CODES_UNKNOWN, \main\PlaceLib::extractCodes([['code' => 5061, 'codeDepartement' => 5]]), 'des nombres à la place des chaînes sont refusés');
		$this->assertEquals(NULL, \main\PlaceLib::extractCodes([['codeEpci' => 'x'] + self::GAP])['placeEpci'], 'une intercommunalité mal formée reste vide, sans perdre la commune');
		$this->assertEquals(\main\PlaceLib::CODES_UNKNOWN, \main\PlaceLib::extractCodes(['Gap']), 'une réponse qui n\'est pas une liste de communes est refusée');

	}

	/**
	 * Règle 2 de V-Sub : un appel qui échoue ne fait jamais échouer l'enregistrement du lieu, et le
	 * département se déduit plus tard, par la reprise.
	 */
	public function testPlaceSavedWhenCallFails(): void {

		$eSystem = $this->getSystem();

		if($this->assertDecorPresent($eSystem->notEmpty(), 'un système pour la structure F1') === FALSE) {
			return;
		}

		\util\CurlLib::setLocked(TRUE);

		$this->savePlace($eSystem, 'Gap', self::POINT_GAP);

		$eSystemSaved = $this->getSystem();

		$this->assertEquals(self::POINT_GAP, $eSystemSaved['placeLngLat'], 'le point est enregistré malgré l\'échec de l\'appel');
		$this->assertEquals(NULL, $eSystemSaved['placeDepartment'], 'le département reste à déduire');
		$this->assertEquals(NULL, $eSystemSaved['placeCity'], 'la commune aussi');
		$this->assertTrue(
			SystemLib::getWithoutCodes($eSystem['structure'], 10)->offsetExists($eSystem['id']),
			'la reprise le retrouvera'
		);

	}

	/**
	 * Le panneau ne construit que le nom, le point et l'altitude : des codes postés n'entrent pas en
	 * base, seule la déduction du point les écrit.
	 */
	public function testPostedCodesIgnored(): void {

		foreach(['placeCity', 'placeDepartment', 'placeEpci'] as $property) {
			$this->assertFalse(in_array($property, SystemLib::getPropertiesUpdate()), $property.' ne se construit pas depuis le formulaire');
			$this->assertFalse(in_array($property, SystemLib::getPropertiesUpdate('api')), $property.' ne se construit pas depuis l\'API');
			$this->assertFalse(in_array($property, SystemLib::getPropertiesShare()), $property.' ne se construit pas depuis le partage');
		}

		$eSystem = $this->getSystem();

		if($this->assertDecorPresent($eSystem->notEmpty(), 'un système pour la structure F1') === FALSE) {
			return;
		}

		\util\CurlLib::setLocked(TRUE);

		$this->savePlace($eSystem, 'Gap', self::POINT_GAP, [
			'placeCity' => '75056',
			'placeDepartment' => '75',
			'placeEpci' => '200054781'
		]);

		$eSystemSaved = $this->getSystem();

		$this->assertEquals(NULL, $eSystemSaved['placeDepartment'], 'le département posté est ignoré');
		$this->assertEquals(NULL, $eSystemSaved['placeCity'], 'la commune postée est ignorée');
		$this->assertEquals(NULL, $eSystemSaved['placeEpci'], 'l\'intercommunalité postée est ignorée');

	}

	/**
	 * Un point renvoyé tel quel (formulaire du système, partage, API) garde ses codes sans rappeler
	 * geo.api.gouv.fr : l'appel verrouillé échouerait et les remettrait à NULL. Un point déplacé, lui,
	 * ne garde jamais l'ancienne commune.
	 */
	public function testCodesFollowThePoint(): void {

		$eSystem = $this->getSystem();

		if($this->assertDecorPresent($eSystem->notEmpty(), 'un système pour la structure F1') === FALSE) {
			return;
		}

		// Les codes de Gap posés comme le décor des tests les pose : `placeDepartment` écrit, aucun appel.
		// Sans build() : il construirait le point posté en JSON et laisserait les codes, qu'aucun
		// formulaire ne poste, à `prepareCodes()` ; le cas veut ces codes-là sans appel au réseau.
		$eSystem['place'] = 'Gap';
		$eSystem['placeLngLat'] = self::POINT_GAP;
		$eSystem['placeCity'] = '05061';
		$eSystem['placeDepartment'] = '05';
		$eSystem['placeEpci'] = '200067825';

		SystemLib::update($eSystem, ['place', 'placeLngLat', 'placeCity', 'placeDepartment', 'placeEpci']);

		\util\CurlLib::setLocked(TRUE);

		$this->savePlace($this->getSystem(), 'Gap', self::POINT_GAP);

		$eSystemSaved = $this->getSystem();

		$this->assertEquals('05', $eSystemSaved['placeDepartment'], 'un point inchangé garde son département');
		$this->assertEquals('05061', $eSystemSaved['placeCity'], 'et sa commune');

		$this->savePlace($eSystemSaved, 'Sisteron', self::POINT_SISTERON);

		$eSystemMoved = $this->getSystem();

		$this->assertEquals(self::POINT_SISTERON, $eSystemMoved['placeLngLat'], 'le point déplacé est enregistré');
		$this->assertEquals(NULL, $eSystemMoved['placeDepartment'], 'il ne garde pas le département de Gap');
		$this->assertEquals(NULL, $eSystemMoved['placeCity'], 'ni sa commune');
		$this->assertEquals(NULL, $eSystemMoved['placeEpci'], 'ni son intercommunalité');

		$this->savePlace($eSystemMoved, '', NULL);

		$this->assertEquals(NULL, $this->getSystem()['placeLngLat'], 'un lieu effacé n\'a plus de point');

	}

	/**
	 * Glaçon 1 de L2, bloquant B1 : un lieu posé pour la première fois, sur un système qui n'en avait
	 * pas, est situé dès cet enregistrement, sans attendre qu'on l'enregistre une seconde fois.
	 */
	public function testNewPlaceLocatedAtOnce(): void {

		$eSystem = $this->getSystem();

		if($this->assertDecorPresent($eSystem->notEmpty(), 'un système pour la structure F1') === FALSE) {
			return;
		}

		$calls = 0;

		\main\PlaceLib::setFetcher(function(array $lngLat) use(&$calls): array {
			$calls++;
			return [self::GAP];
		});

		$this->savePlace($eSystem, '', NULL);
		$this->savePlace($this->getSystem(), 'Gap', self::POINT_GAP);

		$eSystemSaved = $this->getSystem();

		$this->assertEquals(1, $calls, 'le premier point appelle geo.api');
		$this->assertEquals('05061', $eSystemSaved['placeCity']);
		$this->assertEquals('05', $eSystemSaved['placeDepartment']);
		$this->assertEquals('200067825', $eSystemSaved['placeEpci']);

	}

	/**
	 * Arbitrage de V-Sub : l'enregistrement ne rappelle geo.api.gouv.fr que pour un point déplacé,
	 * jamais au seul motif que le département manque, et c'est le rattrapage de la nuit
	 * (`structure/cron/placeCodesAi`) qui retente un point resté sans département.
	 */
	public function testUnchangedPointNotRecalled(): void {

		$eSystem = $this->getSystem();

		if($this->assertDecorPresent($eSystem->notEmpty(), 'un système pour la structure F1') === FALSE) {
			return;
		}

		$calls = 0;
		$cities = [];

		\main\PlaceLib::setFetcher(function(array $lngLat) use(&$calls, &$cities): array {
			$calls++;
			return $cities;
		});

		$this->savePlace($eSystem, 'Gap', self::POINT_GAP);

		$this->assertEquals(1, $calls, 'le point posé appelle geo.api');
		$this->assertEquals(NULL, $this->getSystem()['placeDepartment'], 'qui ne rend aucune commune');

		$this->savePlace($this->getSystem(), 'Gap', self::POINT_GAP);

		$this->assertEquals(1, $calls, 'le même point, enregistré de nouveau sans département, ne rappelle pas geo.api');
		$this->assertTrue(
			SystemLib::getWithoutCodes($eSystem['structure'], 10)->offsetExists($eSystem['id']),
			'le rattrapage le retrouvera'
		);

		$cities = [self::GAP];

		SystemLib::updateCodes($this->getSystem());

		$eSystemLocated = $this->getSystem();

		$this->assertEquals(2, $calls, 'le rattrapage rappelle geo.api sur le même point');
		$this->assertEquals(self::POINT_GAP, $eSystemLocated['placeLngLat'], 'sans toucher au point');
		$this->assertEquals('05061', $eSystemLocated['placeCity']);
		$this->assertEquals('05', $eSystemLocated['placeDepartment']);
		$this->assertEquals('200067825', $eSystemLocated['placeEpci']);
		$this->assertFalse(
			SystemLib::getWithoutCodes($eSystem['structure'], 10)->offsetExists($eSystem['id']),
			'un lieu situé sort du rattrapage'
		);

	}

	/**
	 * Le rattrapage charge ses lieux avant de les traiter un par un : un lieu déplacé entre-temps est
	 * situé à son nouveau point, jamais à celui du chargement. Une structure déclarée à l'étranger
	 * n'entre pas dans le rattrapage.
	 */
	public function testCatchUpReadsTheStoredPoint(): void {

		$eSystem = $this->getSystem();

		if($this->assertDecorPresent($eSystem->notEmpty(), 'un système pour la structure F1') === FALSE) {
			return;
		}

		$lngLatAsked = [];

		\main\PlaceLib::setFetcher(function(array $lngLat) use(&$lngLatAsked): array {
			$lngLatAsked[] = $lngLat;
			return [];
		});

		$this->setCountry(NULL);
		$this->savePlace($eSystem, 'Gap', self::POINT_GAP);

		$eSystemLoaded = $this->getSystem();

		$this->savePlace($this->getSystem(), 'Sisteron', self::POINT_SISTERON);

		SystemLib::updateCodes($eSystemLoaded);

		$this->assertEquals(self::POINT_SISTERON, end($lngLatAsked), 'le rattrapage interroge le point en base, pas celui qu\'il avait chargé');
		$this->assertEquals(self::POINT_SISTERON, $this->getSystem()['placeLngLat'], 'et ne remet pas l\'ancien point');

		$this->assertTrue(
			SystemLib::getWithoutCodes($eSystem['structure'], 10)->offsetExists($eSystem['id']),
			'une structure sans pays reste à rattraper'
		);

		$this->setCountry(\user\UserSetting::BE);

		$this->assertFalse(
			SystemLib::getWithoutCodes($eSystem['structure'], 10)->offsetExists($eSystem['id']),
			'une structure déclarée à l\'étranger n\'entre pas dans le rattrapage'
		);

	}

	/**
	 * Arbitrage de V-Sub : geo.api.gouv.fr ne connaît que la France, une structure déclarée à
	 * l'étranger ne l'appelle pas. La réponse de Gap tient la place de geo.api et compte les appels :
	 * le pendant accepté (France, puis sans pays) prouve qu'elle aurait répondu.
	 */
	public function testAbroadAvoidsTheCall(): void {

		$eSystem = $this->getSystem();

		if($this->assertDecorPresent($eSystem->notEmpty(), 'un système pour la structure F1') === FALSE) {
			return;
		}

		$calls = 0;

		\main\PlaceLib::setFetcher(function(array $lngLat) use(&$calls): array {
			$calls++;
			return [self::GAP];
		});

		$this->setCountry(\user\UserSetting::BE);
		$this->savePlace($this->getSystem(), 'Gap', self::POINT_GAP);

		$this->assertEquals(0, $calls, 'une structure en Belgique n\'appelle pas geo.api');
		$this->assertEquals(self::POINT_GAP, $this->getSystem()['placeLngLat'], 'son lieu est enregistré');
		$this->assertEquals(NULL, $this->getSystem()['placeDepartment'], 'sans département');

		$this->setCountry(\user\UserSetting::FR);
		$this->savePlace($this->getSystem(), 'Sisteron', self::POINT_SISTERON);

		$this->assertEquals(1, $calls, 'une structure en France l\'appelle');
		$this->assertEquals('05', $this->getSystem()['placeDepartment']);

		// Un département déjà déduit ne survit pas au point inchangé d'une structure passée à l'étranger.
		$this->setCountry(\user\UserSetting::BE);
		$this->savePlace($this->getSystem(), 'Sisteron', self::POINT_SISTERON);

		$this->assertEquals(1, $calls, 'pas d\'appel non plus sur un point inchangé');
		$this->assertEquals(NULL, $this->getSystem()['placeDepartment'], 'le département d\'avant le changement de pays est retiré');

		$this->setCountry(NULL);
		$this->savePlace($this->getSystem(), 'Gap', self::POINT_GAP);

		$this->assertEquals(2, $calls, 'sans pays, la structure est présumée française');
		$this->assertEquals('05', $this->getSystem()['placeDepartment']);

	}

	private function setCountry(?int $country): void {

		$eStructure = new Structure([
			'id' => FixtureAiLib::F1,
			'legalCountry' => ($country === NULL) ? new \user\Country() : new \user\Country(['id' => $country]),
		]);

		// Sans build() : il construirait le pays depuis le formulaire légal ou le numéro de TVA, que le
		// cas n'éprouve pas ; il veut ce pays précis sur F1, annulé avec la transaction.
		StructureLib::update($eStructure, ['legalCountry']);

	}

	private function getSystem(): System {
		return SystemLib::getByStructure(new Structure(['id' => FixtureAiLib::F1]));
	}

	/**
	 * Comme le panneau « Lieu de production » (`system.p.php`, `doUpdatePlace`), qui poste le point
	 * en JSON et construit le nom, le point et l'altitude.
	 */
	private function savePlace(System $eSystem, string $place, ?array $lngLat, array $extra = []): void {

		$properties = ['place', 'placeLngLat', 'altitude'];

		$input = $extra + [
			'place' => $place,
			'placeLngLat' => ($lngLat === NULL) ? '' : json_encode($lngLat),
			'altitude' => $eSystem['altitude']
		];

		$fw = new \FailWatch();

		$eSystem->build($properties, $input, new \PropertyList('update'));

		$fw->validate();

		SystemLib::update($eSystem, $properties);

	}

}
?>
