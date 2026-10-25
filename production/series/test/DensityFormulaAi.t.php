<?php
namespace series;

/**
 * La densité d'implantation d'une culture sur planches, écrite par deux chemins.
 *
 * `seriesCultivation.density` se compte en plants au m², passe-pied compris — c'est ce que
 * l'écran affiche (`CultivationUi`, « / m² »). Deux codes l'écrivent : `CropLib::calculateDensity()`
 * en PHP, à chaque création et à chaque mise à jour de l'écartement, et le SQL de masse de
 * `CultivationLib::updateDensityBySeries()` quand la planche ou le passe-pied de la série changent.
 * Les deux doivent rendre le même nombre, sans quoi la colonne dit deux choses selon le geste qui
 * l'a écrite.
 *
 * Le cas porte sur une planche de 80 cm et un passe-pied de 40 : la largeur totale vaut 120 cm, donc
 * la densité au m² et le nombre de plants par mètre linéaire diffèrent de 20 %. C'est l'écart que le
 * JS de saisie annonçait, en posant la seconde dans le champ de la première.
 *
 * Le cas à passe-pied nul vérifie qu'une série sans passe-pied s'écrit par les deux chemins de la
 * même façon. ⚠️ Il ne tient pas le parenthésage de `bedWidth + ($alleyWidth ?? 0)` : sous les deux
 * formes, `NULL` se coerce en 0 dans l'addition, les quatre combinaisons rendent le même nombre
 * (mesuré). Cette correction retire une précédence fausse qui ne deviendra fausse en résultat que
 * le jour où un opérande cessera d'être numérique ; aucun test ne peut la tenir aujourd'hui, et
 * c'est la sentinelle de style qui garde la forme.
 *
 * Lancement : dev/test package=series file=DensityFormulaAi
 */
class DensityFormulaAiTest extends SeriesFixtureAiTest {

	/** Les cinq propriétés que `CultivationLib::update()` exige de voir ensemble. */
	const DISTANCE_PROPERTIES = ['distance', 'density', 'rows', 'rowSpacing', 'plantSpacing'];

	const ROWS = 3;
	const PLANT_SPACING = 30;

	/**
	 * La planche permanente du décor est plus large que la planche temporaire annoncée par la série
	 * (80 cm) : c'est ce qui rend `series.area` différente de `length × (bedWidth + alleyWidth)`, et
	 * c'est la seule façon de distinguer les deux conversions d'une longueur en surface.
	 */
	const PLACE_LENGTH = 20;
	const PLACE_WIDTH = 200;

	/** Une part strictement inférieure à la longueur de la série, pour que le rapport ne vaille pas 1. */
	const PART_LENGTH = 10;

	/** En blocs, l'écartement entre rangs remplace leur nombre. Trente sur trente rend 11,1111, que deux décimales séparent d'une seule. */
	const ROW_SPACING = 30;

	/**
	 * Deux décimales, parce que la colonne est un `decimal(10, 2)` : au-delà, le test comparerait
	 * l'arrondi de MySQL à celui de PHP et non les deux formules. Le décor place l'écart à mesurer
	 * à 1,67, cent fois la maille.
	 */
	const PRECISION = 2;

	/** Une culture sur la série en planches du décor, avec un écartement posé comme l'écran le pose. */
	private function createCultivationBed(Series $eSeries): Cultivation {

		$eCultivation = $this->createCultivation($eSeries);

		$eCultivation['distance'] = Cultivation::SPACING;
		$eCultivation['rows'] = self::ROWS;
		$eCultivation['rowSpacing'] = NULL;
		$eCultivation['plantSpacing'] = self::PLANT_SPACING;

		CultivationLib::update($eCultivation, self::DISTANCE_PROPERTIES);

		return $eCultivation;

	}

	/** Une culture sur la série en blocs du décor, où la densité se lit des deux écartements. */
	private function createCultivationBlock(Series $eSeries): Cultivation {

		$eCultivation = $this->createCultivation($eSeries);

		$eCultivation['distance'] = Cultivation::SPACING;
		$eCultivation['rows'] = NULL;
		$eCultivation['rowSpacing'] = self::ROW_SPACING;
		$eCultivation['plantSpacing'] = self::PLANT_SPACING;

		CultivationLib::update($eCultivation, self::DISTANCE_PROPERTIES);

		return $eCultivation;

	}

	/** La densité telle qu'elle est en base, et non telle que l'élément de l'appelant la porte. */
	private function readDensity(Cultivation $eCultivation): ?float {

		return Cultivation::model()
			->select(['id', 'density'])
			->whereId($eCultivation['id'])
			->get()['density'];

	}

	/**
	 * Les deux chemins d'écriture rendent le même nombre.
	 */
	public function testBothWritersAgree(): void {

		$eSeries = $this->createSeriesBed('Densité du décor');
		$eCultivation = $this->createCultivationBed($eSeries);

		$expected = \sequence\CropLib::calculateDensity($eCultivation, $eSeries);

		$this->assertTrue($expected !== NULL, 'la formule de référence ne rend rien sur le décor, le cas ne prouve rien');
		$this->assertEquals(round($expected, self::PRECISION), round($this->readDensity($eCultivation), self::PRECISION), 'la création n\'écrit pas la densité de la formule de référence');

		// Le second chemin, celui que déclenche un changement de planche ou de passe-pied.
		CultivationLib::updateDensityBySeries($eSeries);

		$this->assertEquals(round($expected, self::PRECISION), round($this->readDensity($eCultivation), self::PRECISION), 'l\'écriture de masse n\'écrit pas la densité de la formule de référence');

	}

	/**
	 * La densité écrite est bien au m² et non par mètre linéaire de planche.
	 *
	 * C'est la confusion que portait le JS de saisie : les deux nombres sont l'un pour l'autre dans
	 * le rapport de la largeur totale, soit 20 % sur le décor.
	 */
	public function testDensityCountsTheAlley(): void {

		$eSeries = $this->createSeriesBed('Densité au mètre du décor');
		$eCultivation = $this->createCultivationBed($eSeries);

		$density = $this->readDensity($eCultivation);
		$plantsByMeter = (100 / self::PLANT_SPACING) * self::ROWS;
		$width = ($eSeries['bedWidth'] + $eSeries['alleyWidth']) / 100;

		$this->assertTrue($width !== 1.0, 'le décor ne sépare plus les deux comptes, le cas est à changer');
		$this->assertEquals(round($plantsByMeter / $width, self::PRECISION), round($density, self::PRECISION), 'la densité écrite n\'est pas celle du mètre carré');

	}

	/**
	 * Une série sans passe-pied s'écrit aussi, et par les deux chemins de la même façon.
	 */
	public function testSeriesWithoutAlleyAgreesToo(): void {

		$eSeries = $this->createSeriesBed('Densité sans passe-pied du décor');
		$eCultivation = $this->createCultivationBed($eSeries);

		$eSeries['alleyWidth'] = NULL;
		SeriesLib::update($eSeries, ['alleyWidth']);

		CultivationLib::updateDensityBySeries($eSeries);

		$expected = \sequence\CropLib::calculateDensity($eCultivation, $eSeries);

		$this->assertTrue($expected !== NULL, 'la formule de référence ne rend rien sans passe-pied');
		$this->assertEquals(round($expected, self::PRECISION), round($this->readDensity($eCultivation), self::PRECISION), 'sans passe-pied, les deux chemins ne rendent plus le même nombre');

	}

	/**
	 * En blocs aussi, les deux chemins rendent le même nombre.
	 *
	 * L'écriture de masse arrondissait au dixième là où la colonne en garde deux : la série reprise
	 * portait alors une densité que sa propre création n'aurait jamais écrite.
	 */
	public function testBothWritersAgreeOnBlock(): void {

		$eSeries = $this->createSeries('Densité en blocs du décor');
		$eCultivation = $this->createCultivationBlock($eSeries);

		$expected = \sequence\CropLib::calculateDensity($eCultivation, $eSeries);

		$this->assertTrue($expected !== NULL, 'la formule de référence ne rend rien en blocs, le cas ne prouve rien');
		$this->assertTrue(round($expected, self::PRECISION) !== round($expected, 1), 'le décor ne distingue plus le dixième du centième, le cas est à changer');
		$this->assertEquals(round($expected, self::PRECISION), round($this->readDensity($eCultivation), self::PRECISION), 'la création n\'écrit pas la densité de la formule de référence en blocs');

		CultivationLib::updateDensityBySeries($eSeries);

		$this->assertEquals(round($expected, self::PRECISION), round($this->readDensity($eCultivation), self::PRECISION), 'l\'écriture de masse n\'écrit pas la densité de la formule de référence en blocs');

	}

	/**
	 * Le champ de saisie rend la densité que la base porte, sans la tronquer.
	 *
	 * `$d->default` en closure remplace la valeur (`framework/util/ui/Form.u.php:526`) : arrondie au
	 * dixième, elle repartait au POST telle qu'affichée, et ouvrir une fiche pour l'enregistrer sans
	 * rien toucher suffisait à perdre les centièmes.
	 */
	public function testDensityFieldCarriesTheStoredValue(): void {

		$eSeries = $this->createSeriesBed('Densité affichée du décor');
		$eCultivation = $this->createCultivationBed($eSeries);

		$density = $this->readDensity($eCultivation);

		$this->assertTrue(round($density, self::PRECISION) !== round($density, 1), 'le décor ne porte plus de centièmes, le cas ne prouve rien');

		$eCultivation['distance'] = Cultivation::DENSITY;
		$eCultivation['density'] = $density;

		$field = new \sequence\CropUi()->getDistanceField(new \util\FormUi(), $eCultivation, Series::BED);

		$this->assertTrue(str_contains($field, 'value="'.$density.'"'), 'le champ densité ne rend pas la valeur de la base : '.$density.' attendu');

	}

	/**
	 * Une répartition exprimée en mètres de planche passe par la surface avant la densité.
	 *
	 * `seriesCultivation.density` se compte au m² de surface brute, passe-pied compris — celle que
	 * `series.area` porte. Multiplier `partLength`, qui est un métré linéaire, directement par cette
	 * densité mélange deux dimensions.
	 *
	 * La conversion se fait par la part de la longueur totale, jamais par `series.bedWidth` : cette
	 * largeur n'est que celle des planches temporaires, quand `series.area` est bâtie place par
	 * place avec la largeur de chaque planche permanente (`PlaceLib`, `Place.l.php:171`). Le décor
	 * pose donc une planche permanente de 200 cm sous une série qui annonce 80, de sorte que les
	 * deux conversions ne puissent pas rendre le même nombre.
	 */
	public function testYoungPlantsOnLengthConvertToArea(): void {

		$eSeries = $this->createSeriesBed('Plants au mètre de planche du décor');
		$eCultivation = $this->createCultivationBed($eSeries);

		$this->createPlace($eSeries, $this->createBed(length: self::PLACE_LENGTH, width: self::PLACE_WIDTH), self::PLACE_LENGTH);

		$eCultivation = Cultivation::model()
			->select(Cultivation::getSelection())
			->whereId($eCultivation['id'])
			->get();

		// La branche visée est celle de l'implantation en planches saisie en densité, la seule où
		// une longueur de planche rencontre un compte au mètre carré.
		$eCultivation['distance'] = Cultivation::DENSITY;
		$eCultivation['varietiesUnit'] = Cultivation::LENGTH;

		$eSlice = new Slice([
			'partPercent' => 100,
			'partArea' => NULL,
			'partLength' => self::PART_LENGTH,
			'partPlant' => NULL,
			'partTray' => NULL
		]);

		$eSeriesPlaced = $eCultivation['series'];
		$length = $eSeriesPlaced['length'] ?? $eSeriesPlaced['lengthTarget'];
		$widthArea = $length * ($eSeriesPlaced['bedWidth'] + $eSeriesPlaced['alleyWidth']) / 100;

		$this->assertTrue($eCultivation['density'] > 0, 'le décor n\'écrit plus de densité, le cas ne prouve rien');
		$this->assertTrue($eSeriesPlaced['area'] > 0, 'le décor ne pose plus de surface sur la série, le cas ne prouve rien');
		$this->assertTrue($eSeriesPlaced['area'] !== $widthArea, 'le décor ne sépare plus la surface réelle de la largeur de planche annoncée, le cas ne prouve rien');

		$this->assertEquals(
			(int)round(self::PART_LENGTH / $length * $eSeriesPlaced['area'] * $eCultivation['density']),
			$eCultivation->getYoungPlants($eSlice),
			'les plants d\'une répartition en mètres de planche ne passent pas par la surface de la série'
		);

	}

	/**
	 * Sur une série en blocs, une répartition en mètres de planche nomme l'état au lieu d'inventer un
	 * nombre.
	 *
	 * L'état est reconnu par le produit (`CultivationUi`, « Les variétés ont été réparties au mL alors
	 * que la culture est sur surface au libre au m² »), et une série en blocs ne porte pas de longueur :
	 * il n'y a donc aucune part de longueur à prendre. La méthode rend `NULL` avec `length` pour raison,
	 * que l'écran sait dire. Les deux formes antérieures rendaient un nombre : celle de `HEAD`
	 * multipliait un métré par une densité au m², et la conversion par `series.bedWidth` rendait 0 sans
	 * erreur, `bedWidth` étant nulle sur toutes les séries en blocs.
	 */
	public function testYoungPlantsOnBlockWithLengthUnitNamesTheState(): void {

		$eSeries = $this->createSeries('Plants au mètre sur un bloc du décor');
		$eCultivation = $this->createCultivationBlock($eSeries);

		$eCultivation = Cultivation::model()
			->select(Cultivation::getSelection())
			->whereId($eCultivation['id'])
			->get();

		$eCultivation['distance'] = Cultivation::DENSITY;
		$eCultivation['varietiesUnit'] = Cultivation::LENGTH;

		$eSlice = new Slice([
			'partPercent' => 100,
			'partArea' => NULL,
			'partLength' => self::PART_LENGTH,
			'partPlant' => NULL,
			'partTray' => NULL
		]);

		$eSeriesBlock = $eCultivation['series'];

		$this->assertTrue($eCultivation['density'] > 0, 'le décor n\'écrit plus de densité, le cas ne prouve rien');
		$this->assertTrue($eSeriesBlock['bedWidth'] === NULL, 'le décor en blocs porte désormais une largeur de planche, le cas ne prouve plus que la conversion par bedWidth rendait 0');
		$this->assertTrue(($eSeriesBlock['area'] ?? $eSeriesBlock['areaTarget']) !== NULL, 'le décor en blocs ne porte plus de surface, le cas s\'arrêterait avant la conversion');
		$this->assertTrue(($eSeriesBlock['length'] ?? $eSeriesBlock['lengthTarget']) === NULL, 'le décor en blocs porte une longueur, le cas ne prouve rien');

		$error = NULL;

		$this->assertEquals(NULL, $eCultivation->getYoungPlants($eSlice, error: $error), 'une répartition en mètres sur un bloc rend encore un nombre');
		$this->assertEquals('length', $error, 'l\'état n\'est pas nommé, l\'écran ne pourra rien dire');

	}

}
?>
