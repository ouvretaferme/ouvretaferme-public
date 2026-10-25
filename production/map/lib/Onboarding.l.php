<?php
namespace map;

/**
 * Premier plan d'une structure sans parcelle : le lieu de production, une parcelle, ses
 * emplacements et leurs planches en une seule saisie guidée. Le producteur choisit s'il travaille sur
 * planches permanentes (on lui propose de les créer) ou sur planches temporaires / sans
 * planche (l'étape des planches saute).
 */
class OnboardingLib {

	const string PERMANENT = 'permanent';
	const string TEMPORARY = 'temporary';

	public static function load(\stdClass $data, \structure\Structure $eStructure): void {

		$eStructure->validate('canProduction');

		$data->eStructure = $eStructure;

		$data->season = \structure\MemberLib::getDynamicSeason($eStructure, GET('season', 'int'));
		SeasonLib::setOnline($data->season);

		// Même critère que MapLib::load() : une parcelle sur la saison, et le plan reprend la main
		if(ZoneLib::getByStructure($eStructure, season: $data->season)->notEmpty()) {
			throw new \RedirectAction(PlanUi::url($eStructure, $data->season));
		}

		$data->eSystem = \structure\SystemLib::getByStructure($eStructure);
		$data->eSystem['structure'] = $eStructure;

	}

	/**
	 * Un nom vide est accepté : la parcelle et les emplacements reçoivent le nom proposé
	 * en exemple dans le formulaire (Jardin 1, Jardin 2, Tunnel 1…, numérotés par type dans
	 * l'ordre de saisie). L'aperçu du formulaire applique la même règle (onboarding.js).
	 */
	public static function getDefaultZoneName(): string {
		return s("Parcelle principale");
	}

	public static function getDefaultPlotName(string $mode, int|string $rank): string {

		return match($mode) {
			Plot::GREENHOUSE => s("Tunnel {value}", $rank),
			default => s("Jardin {value}", $rank)
		};

	}

	public static function getMode(array $input): string {

		return (($input['mode'] ?? NULL) === self::TEMPORARY) ? self::TEMPORARY : self::PERMANENT;

	}

	/**
	 * Les indices des emplacements postés, dans l'ordre du formulaire. Le plafond est un
	 * plafond métier : au-delà, le formulaire de démarrage n'est plus le bon outil.
	 */
	public static function getPlotIndexes(array $input): array {

		$indexes = array_values(array_filter(
			array_keys($input['plotName'] ?? []),
			fn($index) => is_int($index) and $index > 0
		));

		if(count($indexes) > MapSetting::$onboardingPlotsMax) {
			Plot::fail('onboarding.max', ['value' => MapSetting::$onboardingPlotsMax]);
			return [];
		}

		return $indexes;

	}

	/**
	 * Ce que construit un jardin ou un abri du démarrage. Il ne se trace que dans une parcelle enregistrée avec son contour, comme sur
	 * la Carte (Plot::acceptUpdateShape()) : sans elle, ou refusée, le contour posté est ignoré. Il passe avant les dimensions et la
	 * surface, qu'un abri ou un jardin tracé en tire.
	 */
	public static function getPropertiesPlot(bool $zoneDrawn): array {

		return $zoneDrawn ?
			['mode', 'name', 'coordinates', 'length', 'width', 'area'] :
			['mode', 'name', 'length', 'width', 'area'];

	}

	/**
	 * Crée le plan décrit dans $input, sous FailWatch. Les planches dessinées sur la carte de leur support prennent leur place sur
	 * la saison `$season`, comme sur la Carte.
	 */
	public static function create(\structure\Structure $eStructure, array $input, int $season): Zone {

		$fw = new \FailWatch();

		$withBeds = (self::getMode($input) === self::PERMANENT);

		$eSystem = \structure\SystemLib::getByStructure($eStructure);
		$eSystem['structure'] = $eStructure;

		$eSystem->build(['place', 'placeLngLat', 'altitude'], $input, new \PropertyList('update'));

		// Avant la transaction : la déduction de la commune appelle geo.api.gouv.fr, jusqu'à 6 s, et
		// seulement pour un point que build() a accepté.
		$propertiesSystem = ['place', 'placeLngLat', 'altitude'];

		if($fw->ok()) {
			$propertiesSystem = \structure\SystemLib::prepareCodes($eSystem, $propertiesSystem);
		}

		Zone::model()->beginTransaction();

		if($fw->ok()) {
			\structure\SystemLib::update($eSystem, $propertiesSystem);
		}

		$eZone = new Zone([
			'structure' => $eStructure
		]);

		if(trim($input['name'] ?? '') === '') {
			$input['name'] = self::getDefaultZoneName();
		}

		// Un contour remis à plus tard ne part pas : son champ désactivé laisse `coordinates` vide, et la parcelle se crée sans forme
		$eZone->build(ZoneLib::getPropertiesCreate('map'), $input, new \PropertyList('create'));

		$zoneDrawn = FALSE;

		if($fw->ok()) {
			ZoneLib::create($eZone);
			$zoneDrawn = ($eZone['coordinates'] !== NULL);
		}

		$indexes = self::getPlotIndexes($input);

		// Sans emplacement, les planches vont directement sur la parcelle (index 0)
		$holders = [];

		if($indexes === []) {

			if($fw->ok()) {
				$holders[0] = self::getFillPlot($eZone);
			} else {
				$holders[0] = NULL;
			}

		}

		$ranks = [Plot::GREENHOUSE => 0, Plot::OPEN_FIELD => 0];

		foreach($indexes as $index) {

			// Un jardin sans contour reste sans forme, et ses planches à dessiner (Plot::canBedLine())
			$ePlot = new Plot([
				'zone' => $eZone,
				'structure' => $eStructure,
				'coordinates' => NULL
			]);

			$mode = (($input['plotMode'][$index] ?? NULL) === Plot::GREENHOUSE) ? Plot::GREENHOUSE : Plot::OPEN_FIELD;
			$ranks[$mode]++;

			$name = $input['plotName'][$index] ?? NULL;

			if(trim($name ?? '') === '') {
				$name = self::getDefaultPlotName($mode, $ranks[$mode]);
			}

			$values = [
				'mode' => $input['plotMode'][$index] ?? NULL,
				'name' => $name,
				'coordinates' => $input['plotCoordinates'][$index] ?? NULL,
				'length' => $input['plotLength'][$index] ?? NULL,
				'width' => $input['plotWidth'][$index] ?? NULL,
				'area' => $input['plotArea'][$index] ?? NULL
			];

			// Sur planches permanentes, la surface d'un jardin se déduit de ses planches si elle n'est pas donnée
			if($withBeds and $mode === Plot::OPEN_FIELD and trim($values['area'] ?? '') === '') {
				$values['area'] = self::guessArea($input, $index);
			}

			$ePlot->build(
				self::getPropertiesPlot($zoneDrawn),
				$values,
				new \PropertyList('create')->setWrapper(fn(string $property) => 'plot'.ucfirst($property).'['.$index.']')
			);

			if($fw->ok()) {
				PlotLib::create($ePlot);
				$holders[$index] = $ePlot;
			} else {
				$holders[$index] = NULL;
			}

		}

		$eConfiguration = NULL;

		if($withBeds) {

			foreach($holders as $index => $ePlot) {
				self::createBeds($eStructure, $ePlot, $input, $index, $season, $fw);
			}

			if(($input['saveDefaults'] ?? NULL) and $holders !== []) {
				$eConfiguration = self::buildDefaults($eStructure, $input, array_key_first($holders));
			}

		}

		$fw->validate(
			onOk: fn() => Zone::model()->commit(),
			onKo: fn() => Zone::model()->rollBack()
		);

		if($eConfiguration !== NULL) {
			ConfigurationLib::update($eConfiguration, ['defaultBedLength', 'defaultBedWidth', 'defaultAlleyWidth']);
		}

		return $eZone;

	}

	/**
	 * Le nombre et le format des planches d'un emplacement ; les planches elles-mêmes sont
	 * numérotées de 1 à N et créées seulement si l'emplacement est en base. Dessinées en rang sur
	 * la carte de l'emplacement, elles partent avec leur place (`bedDraw[<index>]`), comme sur la Carte.
	 */
	protected static function createBeds(\structure\Structure $eStructure, ?Plot $ePlot, array $input, int $index, int $season, \FailWatch $fw): void {

		$count = var_filter($input['beds'][$index] ?? NULL, '?int');

		if($count === NULL or $count < 1 or $count > MapSetting::$onboardingBedsMax) {
			Bed::fail('number.check', ['value' => MapSetting::$onboardingBedsMax], wrapper: 'beds['.$index.']');
		}

		$eBed = new Bed([
			'structure' => $eStructure
		]);

		$eBed->build(
			['length', 'width', 'alleyWidth'],
			[
				'length' => $input['bedLength'][$index] ?? NULL,
				'width' => $input['bedWidth'][$index] ?? NULL,
				'alleyWidth' => $input['bedAlleyWidth'][$index] ?? NULL
			],
			new \PropertyList('create')->setWrapper(fn(string $property) => 'bed'.ucfirst($property).'['.$index.']')
		);

		if($fw->ko() or $ePlot === NULL) {
			return;
		}

		$eBed['plot'] = $ePlot;
		$eBed['zone'] = $ePlot['zone'];

		$cBed = BedLib::buildFromInput($eBed, ['name' => array_map('strval', range(1, $count))]);

		$json = var_filter($input['bedDraw'][$index] ?? NULL, '?string');

		if($json === NULL) {
			$eDraw = new Draw();
			$cDraw = new \Collection();
		} else {
			[$eDraw, $cDraw] = BedLib::buildDraw($ePlot, $season, $cBed, $json, 'bedDraw['.$index.']');
		}

		if($fw->ok()) {

			if($eDraw->empty()) {
				BedLib::createCollection($cBed);
			} else {
				BedLib::createCollectionDrawn($cBed, $eDraw, $cDraw);
			}

		}

	}

	/**
	 * Surface d'un jardin sur planches permanentes d'après ce qui est posté pour ses
	 * planches : N × longueur × (largeur + passe-pied). NULL si une valeur manque, build()
	 * dira alors que la surface est à renseigner.
	 */
	protected static function guessArea(array $input, int $index): ?int {

		$count = var_filter($input['beds'][$index] ?? NULL, '?int');
		$length = var_filter($input['bedLength'][$index] ?? NULL, '?int');
		$width = var_filter($input['bedWidth'][$index] ?? NULL, '?int');
		$alleyWidth = var_filter($input['bedAlleyWidth'][$index] ?? NULL, '?int') ?? 0;

		if($count === NULL or $length === NULL or $width === NULL or $count < 1 or $length < 1 or $width < 1) {
			return NULL;
		}

		return max(1, (int)ceil($count * $length * ($width + $alleyWidth) / 100));

	}

	/**
	 * Le format saisi devient celui proposé par défaut pour les prochaines planches de la
	 * structure (case cochée à l'étape des planches). Construit avant la validation, écrit après.
	 */
	protected static function buildDefaults(\structure\Structure $eStructure, array $input, int $index): Configuration {

		$eConfiguration = ConfigurationLib::readByStructure($eStructure);

		$eConfiguration->build(
			['defaultBedLength', 'defaultBedWidth', 'defaultAlleyWidth'],
			[
				'defaultBedLength' => $input['bedLength'][$index] ?? NULL,
				'defaultBedWidth' => $input['bedWidth'][$index] ?? NULL,
				'defaultAlleyWidth' => $input['bedAlleyWidth'][$index] ?? NULL
			],
			new \PropertyList('update')->setWrapper(fn(string $property) => match($property) {
				'defaultBedLength' => 'bedLength['.$index.']',
				'defaultBedWidth' => 'bedWidth['.$index.']',
				'defaultAlleyWidth' => 'bedAlleyWidth['.$index.']'
			})
		);

		return $eConfiguration;

	}

	protected static function getFillPlot(Zone $eZone): Plot {

		return Plot::model()
			->select(Plot::getSelection())
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();

	}

}
?>
