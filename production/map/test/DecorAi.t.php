<?php
namespace map;

/**
 * Le décor posé dans l'onglet 3D (`map\Decor`) par le chemin d'écriture du produit : `build()` sur
 * la liste de `DecorLib::getPropertiesCreate()`, puis `DecorLib::create()`, comme `doCreate` de
 * `page/decor.p.php`.
 *
 * Le décor du cas est une parcelle tracée posée par `ZoneLib::create()` dans la transaction de la
 * méthode, sur la structure `FixtureAiLib::F1`. Les positions se prennent autour du centre que
 * `DecorLib::getCenter()` rend une fois cette parcelle posée, quelles que soient les autres zones
 * tracées de la structure.
 *
 * Lancement : dev/test package=map file=DecorAi
 */
class DecorAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new DecorModel();
	}

	public function setUp(): void {

		parent::setUp();

		// Une parcelle et un décor portent leur auteur (SPECIAL(user)), qu'aucune session ne pose en ligne de commande
		\user\ConnectionLib::setOnline($this->getAdmin());

	}

	/** Une parcelle carrée de 100 m de côté autour de [3.1, 45.6], ou sans contour. */
	private function createZone(bool $outlined = TRUE, \structure\Structure $eStructure = new \structure\Structure()): Zone {

		$eZone = new Zone([
			'name' => 'Parcelle du décor '.uniqid(),
			'structure' => $eStructure->empty() ? $this->eStructure : $eStructure,
			'area' => 10000,
			'coordinates' => $outlined ? [[3.0994, 45.5996], [3.1006, 45.5996], [3.1006, 45.6004], [3.0994, 45.6004], [3.0994, 45.5996]] : NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé et sans croisement : ce carré
		// fermé l'est, et le cas ne lit que ses sommets
		ZoneLib::create($eZone);

		return $eZone;

	}

	private function getCenter(): array {
		return DecorLib::getCenter($this->eStructure);
	}

	/** Un point à tant de mètres à l'est et au nord du centre, par la projection de la scène. */
	private function offset(array $center, float $east, float $north = 0.0): array {

		return [
			$center[0] + $east / (111320 * cos(deg2rad($center[1]))),
			$center[1] + $north / 110540
		];

	}

	/**
	 * Le décor tel que le client le poste, construit et enregistré comme le fait `doCreate` : les
	 * propriétés `point` et `json` arrivent en chaîne JSON, que la conversion du schéma décode.
	 */
	private function createDecor(array $input, \FailWatch $fw): Decor {

		$eDecor = new Decor([
			'structure' => $this->eStructure,
			'center' => DecorLib::getCenter($this->eStructure)
		]);

		// `doCreate` valide `acceptCreate()` avant d'écrire : un décor que la route refuserait ne se pose pas ici non plus
		$this->assertTrue($eDecor->acceptCreate());

		foreach(['coordinates', 'trace'] as $property) {
			if(isset($input[$property]) and is_array($input[$property])) {
				$input[$property] = json_encode($input[$property]);
			}
		}

		$eDecor->build(DecorLib::getPropertiesCreate(), $input + [
			'kit' => 'cabane',
			'angle' => 0,
			'size' => 1,
			'coordinates' => json_encode($eDecor['center'] ?? [3.1, 45.6])
		], new \PropertyList('create'));

		if($fw->ok()) {
			DecorLib::create($eDecor);
		}

		return $eDecor;

	}

	public function testCreate(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();

		$eDecor = $this->createDecor([
			'coordinates' => $this->offset($center, 30, -20),
			'angle' => 1.5,
			'size' => 2
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecorStored = Decor::model()
			->select(['kit', 'coordinates', 'angle', 'size', 'model', 'trace', 'hedge', 'createdBy'])
			->whereId($eDecor['id'])
			->get();

		$this->assertEquals('cabane', $eDecorStored['kit']);
		$this->assertEquals(2, count($eDecorStored['coordinates']));
		$this->assertEquals(1.5, $eDecorStored['angle']);
		$this->assertEquals(2.0, $eDecorStored['size']);
		$this->assertEquals(NULL, $eDecorStored['model']);
		$this->assertEquals(NULL, $eDecorStored['trace']);
		$this->assertEquals(FALSE, $eDecorStored['hedge']);
		$this->assertEquals($this->getAdmin()['id'], $eDecorStored['createdBy']['id']);

	}

	/** Un kit inconnu du catalogue se garde tel quel, seule sa forme est contrôlée. */
	public function testKitFormat(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'kit-du-futur-2'], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => '<b>cabane</b>'], $fw);
		$this->assertTrue($fw->has('Decor::kit.format'), json_encode($fw->get()));

	}

	/** Sans zone tracée, la page pose un centre nul et rien ne se pose. */
	public function testCoordinatesWithoutCenter(): void {

		$fw = new \FailWatch();

		$eDecor = new Decor([
			'structure' => $this->eStructure,
			'center' => NULL
		]);

		$eDecor->build(['coordinates'], ['coordinates' => json_encode([3.1, 45.6])], new \PropertyList('create'));

		$this->assertTrue($fw->has('Decor::coordinates.drawn'), json_encode($fw->get()));

	}

	public function testCoordinatesNear(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$this->createDecor(['coordinates' => $this->offset($center, 4900)], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['coordinates' => $this->offset($center, 3600, 3600)], $fw);
		$this->assertTrue($fw->has('Decor::coordinates.near'), json_encode($fw->get()));

	}

	public function testCoordinatesMalformed(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$this->createDecor(['coordinates' => '[3.1]'], $fw);
		$this->assertTrue($fw->has('Decor::coordinates.check'), json_encode($fw->get()));

	}

	public function testTrace(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();

		$eDecor = $this->createDecor([
			'kit' => 'chemin',
			'width' => 2,
			'trace' => [$this->offset($center, -50), [(string)$this->offset($center, 0)[0], (string)$this->offset($center, 0)[1]], $this->offset($center, 0, 50)]
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$trace = Decor::model()
			->select('trace')
			->whereId($eDecor['id'])
			->getValue('trace');

		$this->assertEquals(3, count($trace));
		$this->assertTrue(is_float($trace[1][0]), 'un point posté en chaîne est enregistré en nombre');

	}

	public function testWidthWithoutTrace(): void {

		$this->createZone();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'chemin', 'width' => 2], $fw);
		$this->assertTrue($fw->has('Decor::width.traced'), json_encode($fw->get()));

		// Un tracé refusé ne fait pas refuser la largeur une seconde fois
		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'chemin', 'width' => 2, 'trace' => '[[3.1]]'], $fw);
		$this->assertTrue($fw->has('Decor::trace.check'), json_encode($fw->get()));
		$this->assertFalse($fw->has('Decor::width.traced'), json_encode($fw->get()));

	}

	public function testTraceMalformed(): void {

		$this->createZone();
		$center = $this->getCenter();

		foreach([
			[$center],
			[$center, [3.1]],
			[$center, ['lng' => 3.1, 'lat' => 45.6]],
			[$center, [3.1, 'abc']],
			array_fill(0, 501, $center),
		] as $trace) {

			$fw = new \FailWatch();
			$this->createDecor(['kit' => 'chemin', 'trace' => $trace], $fw);
			$this->assertTrue($fw->has('Decor::trace.check'), json_encode($trace));

		}

	}

	public function testTraceNear(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'chemin', 'trace' => [$center, $this->offset($center, 6000)]], $fw);
		$this->assertTrue($fw->has('Decor::trace.near'), json_encode($fw->get()));

	}

	public function testTraceCollapsed(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'tuyau', 'diameter' => 32, 'trace' => [$center, $center, $center]], $fw);
		$this->assertTrue($fw->has('Decor::trace.collapsed'), json_encode($fw->get()));

		// Un point posté en chaîne est le même point que son nombre
		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'tuyau', 'diameter' => 32, 'trace' => [$center, [(string)$center[0], (string)$center[1]]]], $fw);
		$this->assertTrue($fw->has('Decor::trace.collapsed'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'tuyau', 'diameter' => 32, 'trace' => [$center, $center, $this->offset($center, 10)]], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

	}

	public function testEnclosure(): void {

		$eZone = $this->createZone();

		$fw = new \FailWatch();

		$eDecor = $this->createDecor([
			'kit' => 'enclos',
			'zone' => $eZone['id'],
			'model' => Decor::ELECTRIC,
			'hedge' => TRUE,
			'gate' => 0.25
		], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals($eZone['id'], $eDecor['zone']['id']);

	}

	public function testEnclosureWithoutOutline(): void {

		$eZone = $this->createZone(outlined: FALSE);
		$this->createZone();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id'], 'model' => Decor::SHEEP], $fw);
		$this->assertTrue($fw->has('Decor::zone.outlined'), json_encode($fw->get()));

	}

	public function testEnclosureOfAnotherStructure(): void {

		$this->createZone();

		$eStructureOther = \structure\Structure::model()
			->select('id')
			->whereId(\structure\FixtureAiLib::F2)
			->get();

		if($this->assertDecorPresent($eStructureOther->notEmpty(), 'la structure FixtureAiLib::F2') === FALSE) {
			return;
		}

		$eZoneOther = $this->createZone(eStructure: $eStructureOther);

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZoneOther['id'], 'model' => Decor::SHEEP], $fw);
		$this->assertTrue($fw->has('Decor::zone.owned'), json_encode($fw->get()));

	}

	public function testEnclosureExclusive(): void {

		$eZone = $this->createZone();

		$ePlot = $this->getPlotFill($eZone);

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id'], 'plot' => $ePlot['id'], 'model' => Decor::SHEEP], $fw);
		$this->assertTrue($fw->has('Decor::plot.exclusive'), json_encode($fw->get()));

	}

	/** Une emprise n'a qu'un enclos : le second se refuse, qu'elle soit une parcelle ou un jardin. */
	public function testEnclosureAlone(): void {

		$eZone = $this->createZone();
		$eZonePlot = $this->createZone();
		$ePlot = $this->getPlotFill($eZonePlot);

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id'], 'model' => Decor::SHEEP], $fw);
		$this->createDecor(['kit' => 'enclos', 'plot' => $ePlot['id'], 'hedge' => TRUE], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id'], 'hedge' => TRUE], $fw);
		$this->assertTrue($fw->has('Decor::zone.alone'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'plot' => $ePlot['id'], 'model' => Decor::PANEL], $fw);
		$this->assertTrue($fw->has('Decor::plot.alone'), json_encode($fw->get()));

		$this->assertEquals(1, Decor::model()->whereStructure($this->eStructure)->whereZone($eZone)->count());
		$this->assertEquals(1, Decor::model()->whereStructure($this->eStructure)->wherePlot($ePlot)->count());

		// Une parcelle et le jardin qui la remplit ont le même contour : la clôture de l'un refuse celle de l'autre, dans les deux sens
		$eZoneFirst = $this->createZone();
		$eZoneFilledFirst = $this->createZone();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZoneFirst['id'], 'model' => Decor::SHEEP], $fw);
		$this->createDecor(['kit' => 'enclos', 'plot' => $this->getPlotFill($eZoneFilledFirst)['id'], 'model' => Decor::SHEEP], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'plot' => $this->getPlotFill($eZoneFirst)['id'], 'hedge' => TRUE], $fw);
		$this->assertTrue($fw->has('Decor::plot.alone'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZoneFilledFirst['id'], 'hedge' => TRUE], $fw);
		$this->assertTrue($fw->has('Decor::zone.alone'), json_encode($fw->get()));

	}

	/** Une clôture ou une haie ne se pose que sur ce qui la porte : un tracé ou une emprise. */
	public function testFence(): void {

		$eZone = $this->createZone();

		$fw = new \FailWatch();
		$this->createDecor(['model' => Decor::PANEL], $fw);
		$this->assertTrue($fw->has('Decor::model.fence'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['hedge' => TRUE], $fw);
		$this->assertTrue($fw->has('Decor::hedge.fence'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['gate' => 0.5], $fw);
		$this->assertTrue($fw->has('Decor::gate.fence'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id']], $fw);
		$this->assertTrue($fw->has('Decor::hedge.enclosure'), json_encode($fw->get()));

	}

	/** Un refus plus haut ne fait pas lever les étapes qui lisent la propriété refusée. */
	public function testRefusalDoesNotCascade(): void {

		$eZone = $this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$this->createDecor(['coordinates' => $this->offset($center, 6000), 'trace' => [$center, $this->offset($center, 10)], 'model' => Decor::SHEEP], $fw);
		$this->assertEquals(['Decor::coordinates.near'], array_column($fw->get(), 1), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['trace' => [$center], 'model' => Decor::SHEEP, 'hedge' => TRUE], $fw);
		$this->assertEquals(['Decor::trace.check'], array_column($fw->get(), 1), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['zone' => $this->createZone(outlined: FALSE)['id'], 'plot' => $this->getPlotFill($eZone)['id'], 'model' => Decor::SHEEP, 'hedge' => TRUE, 'gate' => 0.5], $fw);
		$this->assertEquals(['Decor::zone.outlined'], array_column($fw->get(), 1), json_encode($fw->get()));

	}

	/** Un jardin tracé dans la parcelle, à côté du jardin qui la remplit. */
	private function createPlot(Zone $eZone): Plot {

		$ePlot = new Plot([
			'name' => 'Jardin du décor '.uniqid(),
			'zone' => $eZone,
			'mode' => Plot::OPEN_FIELD,
			'area' => 100,
			'coordinates' => [[3.0996, 45.5998], [3.0998, 45.5998], [3.0998, 45.6], [3.0996, 45.6], [3.0996, 45.5998]],
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]);

		// Sans build(), qui vérifierait que le polygone posté est fermé, sans croisement et dans sa
		// parcelle : ce carré fermé est tracé dans celui de createZone(), et le cas ne lit que son id
		PlotLib::create($ePlot);

		return $ePlot;

	}

	private function getPlotFill(Zone $eZone): Plot {

		return Plot::model()
			->select('id')
			->whereZone($eZone)
			->whereZoneFill(TRUE)
			->get();

	}

	/** Le jardin qui remplit une parcelle se clôt sur le contour de sa parcelle, et seulement s'il en a un. */
	public function testEnclosurePlot(): void {

		$eZone = $this->createZone();
		$eZoneBare = $this->createZone(outlined: FALSE);

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'plot' => $this->getPlotFill($eZone)['id'], 'hedge' => TRUE], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'plot' => $this->getPlotFill($eZoneBare)['id'], 'hedge' => TRUE], $fw);
		$this->assertTrue($fw->has('Decor::plot.outlined'), json_encode($fw->get()));

	}

	/** Changer la clôture d'une emprise, comme `doUpdateEnclosure`. */
	public function testUpdateEnclosure(): void {

		$eZone = $this->createZone();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id'], 'model' => Decor::SHEEP], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$properties = ['model', 'hedge', 'gate'];

		$eDecorUpdated = Decor::model()
			->select(Decor::getSelection())
			->whereId($eDecor['id'])
			->get();

		$eDecorUpdated->build($properties, ['model' => Decor::PALING, 'hedge' => TRUE, 'gate' => 0.75], new \PropertyList('update'));
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		DecorLib::update($eDecorUpdated, $properties);

		$eDecorStored = Decor::model()
			->select(['model', 'hedge', 'gate', 'updatedAt'])
			->whereId($eDecor['id'])
			->get();

		$this->assertEquals(Decor::PALING, $eDecorStored['model']);
		$this->assertTrue($eDecorStored['hedge']);
		$this->assertEquals(0.75, $eDecorStored['gate']);
		$this->assertDiffer(NULL, $eDecorStored['updatedAt']);

		$eDecorUpdated->build($properties, ['model' => '', 'hedge' => '0'], new \PropertyList('update'));
		$this->assertTrue($fw->has('Decor::hedge.enclosure'), 'une emprise reste close : '.json_encode($fw->get()));

	}

	/** Changer le modèle d'une clôture dessinée, comme le client le poste à `doUpdateEnclosure`, avec la haie et le portail que le tracé n'a pas. */
	public function testUpdateTraceModel(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor(['kit' => 'cloture', 'trace' => [$center, $this->offset($center, 10)], 'model' => Decor::SHEEP], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$properties = ['model', 'hedge', 'gate'];

		$eDecorUpdated = Decor::model()
			->select(Decor::getSelection())
			->whereId($eDecor['id'])
			->get();

		$eDecorUpdated->build($properties, ['model' => Decor::ELECTRIC, 'hedge' => 0, 'gate' => ''], new \PropertyList('update'));
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		DecorLib::update($eDecorUpdated, $properties);

		$eDecorStored = Decor::model()
			->select(['model', 'hedge', 'gate', 'trace'])
			->whereId($eDecor['id'])
			->get();

		$this->assertEquals(Decor::ELECTRIC, $eDecorStored['model']);
		$this->assertFalse($eDecorStored['hedge']);
		$this->assertEquals(NULL, $eDecorStored['gate']);
		$this->assertEquals(2, count($eDecorStored['trace']));

	}

	/**
	 * Le décor relu en base et construit sur la liste de `doUpdatePosition`, puis enregistré, comme le fait la route. Rend la liste
	 * construite.
	 */
	private function updatePosition(Decor $eDecor, array $input, \FailWatch $fw): array {

		$eDecorUpdated = Decor::model()
			->select(Decor::getSelection())
			->whereId($eDecor['id'])
			->get();

		$eDecorUpdated['center'] = DecorLib::getCenter($this->eStructure);

		$properties = DecorLib::getPropertiesPosition($eDecorUpdated);

		$eDecorUpdated->build($properties, $input, new \PropertyList('update'));

		if($fw->ok()) {
			DecorLib::update($eDecorUpdated, $properties);
		}

		return $properties;

	}

	/** Un tracé se modifie par ses points et sa largeur, un objet se déplace, se tourne et se retaille : jamais l'inverse. */
	public function testPropertiesPosition(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$eDecorTrace = $this->createDecor(['kit' => 'chemin', 'width' => 1, 'trace' => [$center, $this->offset($center, 10)]], $fw);
		$eDecorObject = $this->createDecor(['coordinates' => $this->offset($center, 20)], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->assertEquals(['trace', 'width'], DecorLib::getPropertiesPosition($eDecorTrace));
		$this->assertEquals(['coordinates', 'angle', 'size'], DecorLib::getPropertiesPosition($eDecorObject));

	}

	/** Un point déplacé, un point inséré et un point retiré, comme les poignées de la scène : le tracé change, la largeur suit. */
	public function testUpdateTracePoints(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor(['kit' => 'chemin', 'width' => 1, 'trace' => [$center, $this->offset($center, 10), $this->offset($center, 20)]], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$readTrace = fn() => Decor::model()
			->select(['width', 'trace', 'coordinates'])
			->whereId($eDecor['id'])
			->get();

		// Le point du milieu monte de 5 m
		$this->updatePosition($eDecor, ['trace' => json_encode([$center, $this->offset($center, 10, 5), $this->offset($center, 20)]), 'width' => 3], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecorStored = $readTrace();
		$this->assertEquals(3, $eDecorStored['width']);
		$this->assertEquals(3, count($eDecorStored['trace']));
		$this->assertEquals(round($this->offset($center, 10, 5)[1], 6), round($eDecorStored['trace'][1][1], 6));

		// Un point inséré au milieu du premier segment
		$this->updatePosition($eDecor, ['trace' => json_encode([$center, $this->offset($center, 5, 2.5), $this->offset($center, 10, 5), $this->offset($center, 20)]), 'width' => 3], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(4, count($readTrace()['trace']));

		// Deux points retirés : il en reste deux, le minimum
		$this->updatePosition($eDecor, ['trace' => json_encode([$center, $this->offset($center, 20)]), 'width' => 3], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(2, count($readTrace()['trace']));

		// Un seul point n'est plus un tracé
		$this->updatePosition($eDecor, ['trace' => json_encode([$center]), 'width' => 3], $fw);
		$this->assertTrue($fw->has('Decor::trace.check'), json_encode($fw->get()));
		$this->assertEquals(2, count($readTrace()['trace']));

		// Un tracé vide ou absent ne l'efface pas : il en ferait un objet ponctuel
		foreach([['trace' => '', 'width' => 3], ['width' => 3]] as $input) {

			$fw = new \FailWatch();
			$this->updatePosition($eDecor, $input, $fw);
			$this->assertTrue($fw->has('Decor::trace.kept'), json_encode($fw->get()));
			$this->assertEquals(2, count($readTrace()['trace']));

		}

	}

	/**
	 * Un tracé ne se déplace pas par sa place : un `coordinates` posté ne se construit pas, et la place enregistrée est le centre de
	 * la boite de ses points, celle où la scène le pose.
	 */
	public function testUpdateTraceCoordinates(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor(['kit' => 'haie', 'trace' => [$center, $this->offset($center, 10)]], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$properties = $this->updatePosition($eDecor, [
			'coordinates' => json_encode($this->offset($center, 300)),
			'angle' => 1,
			'size' => 2,
			'trace' => json_encode([$this->offset($center, 0, -4), $this->offset($center, 30, 8)])
		], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals(['trace', 'width'], $properties);

		$eDecorStored = Decor::model()
			->select(['coordinates', 'angle', 'size'])
			->whereId($eDecor['id'])
			->get();

		$this->assertEquals(round($this->offset($center, 15, 2)[0], 6), round($eDecorStored['coordinates'][0], 6));
		$this->assertEquals(round($this->offset($center, 15, 2)[1], 6), round($eDecorStored['coordinates'][1], 6));
		$this->assertEquals(0.0, (float)$eDecorStored['angle']);
		$this->assertEquals(1.0, (float)$eDecorStored['size']);

	}

	/** Un objet posé se déplace par sa place, et un tracé posté ne le change pas en tracé. */
	public function testUpdateObjectPosition(): void {

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor(['coordinates' => $center], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->updatePosition($eDecor, [
			'coordinates' => json_encode($this->offset($center, 12)),
			'angle' => 0.5,
			'size' => 1.5,
			'trace' => json_encode([$center, $this->offset($center, 10)])
		], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecorStored = Decor::model()
			->select(['coordinates', 'trace', 'size'])
			->whereId($eDecor['id'])
			->get();

		$this->assertEquals(round($this->offset($center, 12)[0], 6), round($eDecorStored['coordinates'][0], 6));
		$this->assertEquals(1.5, (float)$eDecorStored['size']);
		$this->assertEquals(NULL, $eDecorStored['trace']);

	}

	public function testLimitCount(): void {

		$this->createZone();

		$existing = Decor::model()
			->whereStructure($this->eStructure)
			->count();

		$fw = new \FailWatch();

		// Un arbre, léger, pour que DECOR_MAX arbres tiennent sous DECOR_TRIANGLES : le cas éprouve le plafond de la liste, pas celui du poids
		for($i = $existing; $i < MapSetting::DECOR_MAX; $i++) {
			$this->createDecor(['kit' => 'arbre'], $fw);
		}

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->createDecor(['kit' => 'arbre'], $fw);
		$this->assertTrue($fw->has('Decor::structure.limit'), json_encode($fw->get()));

	}

	/**
	 * Chaque objet posé pèse le plus lourd tirage de son kit : des bosquets, à 8 736 triangles, passent DECOR_TRIANGLES bien avant
	 * DECOR_MAX, et la pose de trop est refusée sans rien écrire. Un kit que le tableau ignore pèse autant que le plus lourd.
	 */
	public function testLimitTriangles(): void {

		$this->createZone();

		$fw = new \FailWatch();

		$triangles = DecorLib::getTriangles($this->eStructure);
		$room = intdiv(MapSetting::DECOR_TRIANGLES - $triangles, DecorLib::KIT_TRIANGLES['bosquet']);

		$this->assertTrue($room < MapSetting::DECOR_MAX, "des bosquets atteignent le poids de la scène avant le plafond de la liste");

		for($i = 0; $i < $room; $i++) {
			$this->createDecor(['kit' => 'bosquet'], $fw);
		}

		$this->assertTrue($fw->ok(), json_encode($fw->get()));
		$this->assertEquals($triangles + $room * 8736, DecorLib::getTriangles($this->eStructure), "chaque bosquet pèse 8 736 triangles");

		$count = Decor::model()->whereStructure($this->eStructure)->count();

		$this->createDecor(['kit' => 'bosquet'], $fw);
		$this->assertTrue($fw->has('Decor::structure.triangles'), json_encode($fw->get()));
		$this->assertEquals($count, Decor::model()->whereStructure($this->eStructure)->count(), "le bosquet de trop n'est pas écrit");

		$this->assertEquals(max(DecorLib::KIT_TRIANGLES), DecorLib::getKitTriangles('cabane'), "un kit absent du tableau pèse le plus lourd");

	}

	public function testLimitTraceLength(): void {

		if($this->assertDecorPresent(
			Decor::model()->whereStructure($this->eStructure)->count() === 0,
			'la structure '.static::STRUCTURE.' sans décor à elle, pour compter la longueur des tracés du cas'
		) === FALSE) {
			return;
		}

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$eDecor = $this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -1000), $this->offset($center, 1000)]], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -400, 100), $this->offset($center, 400, 100)]], $fw);
		$this->assertTrue($fw->has('Decor::trace.limit'), 'création au-delà du plafond : '.json_encode($fw->get()));

		// Rallonger le tracé déjà posé au-delà du plafond, comme `doUpdatePosition` : un point ajouté à 800 m de son bout
		$fw = new \FailWatch();

		$this->updatePosition($eDecor, ['trace' => json_encode([$this->offset($center, -1000), $this->offset($center, 1000), $this->offset($center, 1000, 800)])], $fw);

		$this->assertTrue($fw->has('Decor::trace.limit'), 'mise à jour au-delà du plafond : '.json_encode($fw->get()));
		$this->assertEquals(2, count(Decor::model()->select('trace')->whereId($eDecor['id'])->getValue('trace')), 'le tracé refusé reste celui d\'avant');

	}

	/** Une haie pèse 2,2 fois sa longueur et 8,2 m de plus sous le plafond : 1 200 m y tiennent, pas 1 225, et le tour d'un enclos compte sa haie de même. */
	public function testLimitHedgeWeight(): void {

		if($this->assertDecorPresent(
			Decor::model()->whereStructure($this->eStructure)->count() === 0,
			'la structure '.static::STRUCTURE.' sans décor à elle, pour compter la longueur des tracés du cas'
		) === FALSE) {
			return;
		}

		$eZone = $this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'haie', 'trace' => [$this->offset($center, -612.5), $this->offset($center, 612.5)]], $fw);
		$this->assertTrue($fw->has('Decor::trace.limit'), 'une haie de 1 225 m, 2 695 m à son poids et 2 703 avec ce qu\'elle compte une fois : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$eDecor = $this->createDecor(['kit' => 'haie', 'trace' => [$this->offset($center, -600), $this->offset($center, 600)]], $fw);
		$this->assertTrue($fw->ok(), 'une haie de 1 200 m : '.json_encode($fw->get()));

		// Hors de `build()`, qui n'aurait vérifié que le format du tracé, et du Lib, qui le refuserait pour son poids : le cas a
		// besoin d'une haie posée avant que la haie ne pèse plus que sa longueur, que seule une écriture directe laisse au-delà du plafond
		Decor::model()->update($eDecor, ['trace' => [$this->offset($center, -1000), $this->offset($center, 1000)]]);

		$fw = new \FailWatch();
		$this->updatePosition($eDecor, ['trace' => json_encode([$this->offset($center, -1000), $this->offset($center, 1050)])], $fw);
		$this->assertTrue($fw->has('Decor::trace.limit'), 'une haie au-delà du plafond qui s\'allonge : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->updatePosition($eDecor, ['trace' => json_encode([$this->offset($center, -1000), $this->offset($center, 900)])], $fw);
		$this->assertTrue($fw->ok(), 'une haie au-delà du plafond qui raccourcit sans y revenir : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->updatePosition($eDecor, ['trace' => json_encode([$this->offset($center, -375), $this->offset($center, 375)])], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		// Le carré de la parcelle fait 364 m de tour. Avec les 1 658 m que pèse la haie de 750 m, une clôture doublée d'une haie
		// en pèse 3,2 tours et 8,2 m, 1 172 m, et dépasse ; une haie seule en pèse 2,2 et 8,2 m, 809 m, et tient
		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id'], 'model' => Decor::SHEEP, 'hedge' => TRUE], $fw);
		$this->assertTrue($fw->has('Decor::trace.limit'), 'un enclos à clôture et à haie : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id'], 'hedge' => TRUE], $fw);
		$this->assertTrue($fw->ok(), 'un enclos à haie seule : '.json_encode($fw->get()));

		// L'enclos posé pèse 800 m pour son tour et ce qu'une haie compte une fois. Une haie de 858 m, 1 896 m à elle seule, ne
		// dépasse avec lui que par ce second compte : 2 696 m sans, 2 704 avec
		$fw = new \FailWatch();
		$this->updatePosition($eDecor, ['trace' => json_encode([$this->offset($center, -429), $this->offset($center, 429)])], $fw);
		$this->assertTrue($fw->has('Decor::trace.limit'), 'une haie de 858 m à côté d\'un enclos à haie : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->updatePosition($eDecor, ['trace' => json_encode([$this->offset($center, -427), $this->offset($center, 427)])], $fw);
		$this->assertTrue($fw->ok(), 'une haie de 854 m à côté d\'un enclos à haie : '.json_encode($fw->get()));

	}

	/** Un chemin ou un cours d'eau ne pèse rien sous le plafond de longueur, que seules les haies et les clôtures remplissent. */
	public function testLimitBand(): void {

		if($this->assertDecorPresent(
			Decor::model()->whereStructure($this->eStructure)->count() === 0,
			'la structure '.static::STRUCTURE.' sans décor à elle, pour compter la longueur des tracés du cas'
		) === FALSE) {
			return;
		}

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -1000), $this->offset($center, 1000)]], $fw);
		$this->createDecor(['kit' => 'chemin', 'width' => 2, 'trace' => [$this->offset($center, -1000, 50), $this->offset($center, 1000, 50)]], $fw);
		$this->createDecor(['kit' => 'cours-eau', 'width' => 3, 'trace' => [$this->offset($center, -1000, 100), $this->offset($center, 1000, 100)]], $fw);
		$this->assertTrue($fw->ok(), 'deux bandes de 2 000 m au-delà du plafond : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -400, 150), $this->offset($center, 400, 150)]], $fw);
		$this->assertTrue($fw->has('Decor::trace.limit'), 'une clôture au-delà du plafond : '.json_encode($fw->get()));

	}

	/** Une bande fleurie pèse trois fois sa longueur sous le plafond : 300 m passent en clôture, pas en bande fleurie. */
	public function testLimitTraceWeight(): void {

		if($this->assertDecorPresent(
			Decor::model()->whereStructure($this->eStructure)->count() === 0,
			'la structure '.static::STRUCTURE.' sans décor à elle, pour compter la longueur des tracés du cas'
		) === FALSE) {
			return;
		}

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -1000), $this->offset($center, 1000)]], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'bande-fleurie', 'trace' => [$this->offset($center, -150, 100), $this->offset($center, 150, 100)]], $fw);
		$this->assertTrue($fw->has('Decor::trace.limit'), 'une bande fleurie de 300 m, 900 m au plafond : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -150, 100), $this->offset($center, 150, 100)]], $fw);
		$this->assertTrue($fw->ok(), 'une clôture de 300 m : '.json_encode($fw->get()));

	}

	/** Une route, sans largeur, pèse sous le plafond une fraction de sa longueur : 2 000 m n'y comptent que pour 300 m. */
	public function testLimitTraceWeightRoad(): void {

		if($this->assertDecorPresent(
			Decor::model()->whereStructure($this->eStructure)->count() === 0,
			'la structure '.static::STRUCTURE.' sans décor à elle, pour compter la longueur des tracés du cas'
		) === FALSE) {
			return;
		}

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -1000), $this->offset($center, 1000)]], $fw);
		$this->createDecor(['kit' => 'route', 'trace' => [$this->offset($center, -1000, 50), $this->offset($center, 1000, 50)]], $fw);
		$this->assertTrue($fw->ok(), 'une route de 2 000 m, 300 m au plafond : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -250, 100), $this->offset($center, 250, 100)]], $fw);
		$this->assertTrue($fw->has('Decor::trace.limit'), 'une clôture de 500 m après la route : '.json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -150, 100), $this->offset($center, 150, 100)]], $fw);
		$this->assertTrue($fw->ok(), 'une clôture de 300 m après la route : '.json_encode($fw->get()));

	}

	/** Une bande qui change de largeur reste hors du plafond, mais sans largeur elle y compte sa longueur : `update()` recompte. */
	public function testLimitBandWidthUpdate(): void {

		if($this->assertDecorPresent(
			Decor::model()->whereStructure($this->eStructure)->count() === 0,
			'la structure '.static::STRUCTURE.' sans décor à elle, pour compter la longueur des tracés du cas'
		) === FALSE) {
			return;
		}

		$this->createZone();
		$center = $this->getCenter();

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -1000), $this->offset($center, 1000)]], $fw);
		$eDecor = $this->createDecor(['kit' => 'chemin', 'width' => 2, 'trace' => [$this->offset($center, -500, 50), $this->offset($center, 500, 50)]], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$eDecorWider = Decor::model()
			->select(Decor::getSelection())
			->whereId($eDecor['id'])
			->get();

		$eDecorWider->build(['width'], ['width' => 3], new \PropertyList('update'));
		DecorLib::update($eDecorWider, ['width']);
		$this->assertTrue($fw->ok(), 'une bande élargie : '.json_encode($fw->get()));

		$eDecorBare = Decor::model()
			->select(Decor::getSelection())
			->whereId($eDecor['id'])
			->get();

		// Hors de `build()`, qui lit NULL comme un champ non posté et laisserait la largeur en place ; la largeur ôtée n'a d'ailleurs
		// rien que le schéma refuse, c'est le plafond d'`update()` que le cas éprouve
		$eDecorBare['width'] = NULL;

		$fw = new \FailWatch();
		DecorLib::update($eDecorBare, ['width']);
		$this->assertTrue($fw->has('Decor::trace.limit'), 'une bande sans largeur au-delà du plafond : '.json_encode($fw->get()));

		$this->assertEquals(3, Decor::model()->select('width')->whereId($eDecor['id'])->getValue('width'));

	}

	/** Le tour d'une emprise compte une fois pour sa clôture, et sa haie en plus. */
	public function testLimitEnclosureLength(): void {

		if($this->assertDecorPresent(
			Decor::model()->whereStructure($this->eStructure)->count() === 0,
			'la structure '.static::STRUCTURE.' sans décor à elle, pour compter la longueur des tracés du cas'
		) === FALSE) {
			return;
		}

		$eZone = $this->createZone();
		$center = $this->getCenter();

		// Le carré de la parcelle fait 364 m de tour : 2 300 m de tracé laissent passer sa clôture seule,
		// pas sa clôture doublée d'une haie
		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'cloture', 'model' => Decor::SHEEP, 'trace' => [$this->offset($center, -1150), $this->offset($center, 1150)]], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id'], 'model' => Decor::SHEEP, 'hedge' => TRUE], $fw);
		$this->assertTrue($fw->has('Decor::trace.limit'), json_encode($fw->get()));

		$fw = new \FailWatch();
		$this->createDecor(['kit' => 'enclos', 'zone' => $eZone['id'], 'model' => Decor::SHEEP], $fw);
		$this->assertTrue($fw->ok(), json_encode($fw->get()));

	}

	/** Une clôture s'en va avec l'espace qu'elle entoure, et le reste du décor demeure. */
	public function testDeleteWithEnclosure(): void {

		$eZoneDeleted = $this->createZone();
		$eZoneFillDeleted = $this->createZone();
		$eZoneKept = $this->createZone();

		$ePlotDeleted = $this->createPlot($eZoneKept);

		$fw = new \FailWatch();

		$eDecorZone = $this->createDecor(['kit' => 'enclos', 'zone' => $eZoneDeleted['id'], 'model' => Decor::SHEEP], $fw);
		$eDecorZonePlot = $this->createDecor(['kit' => 'enclos', 'plot' => $this->getPlotFill($eZoneFillDeleted)['id'], 'hedge' => TRUE], $fw);
		$eDecorPlot = $this->createDecor(['kit' => 'enclos', 'plot' => $ePlotDeleted['id'], 'model' => Decor::PANEL], $fw);
		$eDecorKept = $this->createDecor(['kit' => 'enclos', 'zone' => $eZoneKept['id'], 'model' => Decor::PALING], $fw);
		$eDecorPosed = $this->createDecor([], $fw);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		ZoneLib::delete($eZoneDeleted);
		ZoneLib::delete($eZoneFillDeleted);
		PlotLib::delete($ePlotDeleted);

		$this->assertTrue($fw->ok(), json_encode($fw->get()));

		$this->assertEquals(
			[$eDecorKept['id'], $eDecorPosed['id']],
			Decor::model()
				->whereId('IN', [$eDecorZone['id'], $eDecorZonePlot['id'], $eDecorPlot['id'], $eDecorKept['id'], $eDecorPosed['id']])
				->sort(['id' => SORT_ASC])
				->getColumn('id')
		);

	}

}
?>
