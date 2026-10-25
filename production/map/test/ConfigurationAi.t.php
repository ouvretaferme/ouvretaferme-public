<?php
namespace map;

/**
 * Le terrain de l'onglet 3D (`map\Configuration`) par le chemin d'écriture du produit : `build()` sur
 * la liste de `ConfigurationLib::getPropertiesCreate()`, puis `ConfigurationLib::create()`, comme
 * `doCreate` de `page/configuration.p.php`, sur la structure `FixtureAiLib::F1`.
 *
 * Les réglages de culture (saisons, planches, rotations, période des diagrammes) n'ont plus que cette
 * ligne pour source : les saisons d'une structure neuve, leurs incréments, le changement d'année et
 * l'écran « Les cultures » s'y écrivent, et le mémo de `ConfigurationLib::getByStructure()` les rend
 * aussitôt. La création d'une structure, qui pose la ligne, l'est par `structure\DuplicateAi`.
 *
 * Chaque méthode part d'une structure sans réglage : la ligne que le dev aurait déjà se retire dans la
 * transaction de la méthode.
 *
 * Lancement : dev/test package=map file=ConfigurationAi
 */
class ConfigurationAiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new ConfigurationModel();
	}

	public function setUp(): void {

		parent::setUp();

		\user\ConnectionLib::setOnline($this->getAdmin());

		$eConfiguration = Configuration::model()
			->select(['id', 'structure'])
			->whereStructure($this->eStructure)
			->get();

		if($eConfiguration->notEmpty()) {
			ConfigurationLib::delete($eConfiguration);
		}

	}

	/**
	 * Le terrain tel que le client le poste, construit et enregistré comme le fait `doCreate`.
	 */
	private function createConfiguration(array $input, \FailWatch $fw): Configuration {

		$eConfiguration = new Configuration([
			'structure' => $this->eStructure
		]);

		// `doCreate` valide `canCreate()` et `acceptCreate()` avant d'écrire : un réglage que la route refuserait ne s'écrit pas ici non plus
		$this->assertTrue($eConfiguration->canCreate());
		$this->assertTrue($eConfiguration->acceptCreate());

		$eConfiguration->build(ConfigurationLib::getPropertiesCreate(), $input, new \PropertyList('create'));

		if($fw->ok()) {
			ConfigurationLib::create($eConfiguration);
		}

		return $eConfiguration;

	}

	private function countRows(): int {

		return Configuration::model()
			->whereStructure($this->eStructure)
			->count();

	}

	/** Sans ligne, la structure lit les défauts du schéma : l'herbe verte tendre, le limon et le pré mixte. */
	public function testDefaults(): void {

		$eConfiguration = ConfigurationLib::readByStructure($this->eStructure);

		$this->assertEquals(0, $this->countRows());
		$this->assertEquals(Configuration::TENDER, $eConfiguration['decorGrass']);
		$this->assertEquals(Configuration::LOAM, $eConfiguration['decorSoil']);
		$this->assertEquals(Configuration::MIXED, $eConfiguration['decorMeadow']);
		$this->assertEquals($this->eStructure['id'], $eConfiguration['structure']['id']);

	}

	/** La première écriture crée la ligne, la seconde la remplace. */
	public function testCreateTwice(): void {

		$fw = new \FailWatch();

		$this->createConfiguration(['decorGrass' => Configuration::CLEAR, 'decorSoil' => Configuration::OCHRE, 'decorMeadow' => Configuration::MIXED], $fw);
		$this->createConfiguration(['decorGrass' => Configuration::DRY, 'decorSoil' => Configuration::HUMUS, 'decorMeadow' => Configuration::TUFTED], $fw);

		$this->assertTrue($fw->ok());
		$this->assertEquals(1, $this->countRows());

		$eConfiguration = ConfigurationLib::readByStructure($this->eStructure);

		$this->assertEquals(Configuration::DRY, $eConfiguration['decorGrass']);
		$this->assertEquals(Configuration::HUMUS, $eConfiguration['decorSoil']);
		$this->assertEquals(Configuration::TUFTED, $eConfiguration['decorMeadow']);

	}

	/** Une valeur hors de l'enum est refusée par le schéma, et rien ne s'écrit. */
	public function testOutsideEnum(): void {

		$fw = new \FailWatch();

		$this->createConfiguration(['decorGrass' => 'moss', 'decorSoil' => Configuration::SAND, 'decorMeadow' => Configuration::MIXED], $fw);

		$this->assertTrue($fw->has('Configuration::decorGrass.check'));
		$this->assertEquals(0, $this->countRows());

		$fw = new \FailWatch();

		$this->createConfiguration(['decorGrass' => Configuration::LUSH, 'decorSoil' => 'clay<script>', 'decorMeadow' => Configuration::MIXED], $fw);

		$this->assertTrue($fw->has('Configuration::decorSoil.check'));
		$this->assertEquals(0, $this->countRows());

		// Une page ouverte avant le fond du pré ne le poste pas : son choix d'herbe ou de sol est refusé, sans rien écrire
		$fw = new \FailWatch();

		$this->createConfiguration(['decorGrass' => Configuration::LUSH, 'decorSoil' => Configuration::SAND], $fw);

		$this->assertTrue($fw->has('Configuration::decorMeadow.check'));
		$this->assertEquals(0, $this->countRows());

	}

	/** La neige, retirée de l'enum, est refusée comme toute valeur inconnue : une page restée ouverte ne l'enregistre plus. */
	public function testSnowRetired(): void {

		$fw = new \FailWatch();

		$this->createConfiguration(['decorGrass' => 'snow', 'decorSoil' => Configuration::BROWN, 'decorMeadow' => Configuration::MIXED], $fw);

		$this->assertTrue($fw->has('Configuration::decorGrass.check'));
		$this->assertEquals(0, $this->countRows());

	}

	/** Un membre qui ne gère pas la structure ne règle pas son terrain. */
	public function testNotManager(): void {

		\user\ConnectionLib::setOnline(\user\User::model()
			->select('id')
			->whereEmail('decor.f1.planning@example.org')
			->get());

		$eConfiguration = new Configuration(['structure' => $this->eStructure]);

		$this->assertTrue($eConfiguration->canRead());
		$this->assertFalse($eConfiguration->canCreate());

	}

	/** La scène lit le terrain enregistré, ou les défauts tant que la structure n'a rien réglé. */
	public function testScene(): void {

		$this->assertEquals(['grass' => Configuration::TENDER, 'soil' => Configuration::LOAM, 'meadow' => Configuration::MIXED], SceneLib::getByStructure($this->eStructure, \Clock::year())['terrain']);

		$fw = new \FailWatch();

		$this->createConfiguration(['decorGrass' => Configuration::GRAZED, 'decorSoil' => Configuration::CHALK, 'decorMeadow' => Configuration::FLOWERY], $fw);

		$this->assertEquals(['grass' => Configuration::GRAZED, 'soil' => Configuration::CHALK, 'meadow' => Configuration::FLOWERY], SceneLib::getByStructure($this->eStructure, \Clock::year())['terrain']);

	}

	/**
	 * Une structure neuve déclare l'année en cours, et la suivante dès le mois qui précède le changement
	 * de saison.
	 */
	public function testCreateForStructure(): void {

		$this->setClock('2037-06-15 10:00:00');

		ConfigurationLib::createForStructure($this->eStructure);

		$eConfiguration = ConfigurationLib::getByStructure($this->eStructure);

		$this->assertEquals([2037, 2037], [$eConfiguration['seasonFirst'], $eConfiguration['seasonLast']]);

		ConfigurationLib::deleteByStructure($this->eStructure);

		$this->setClock('2037-'.sprintf('%02d', \structure\StructureSetting::NEW_SEASON - 1).'-01 10:00:00');

		ConfigurationLib::createForStructure($this->eStructure);

		$eConfiguration = ConfigurationLib::getByStructure($this->eStructure);

		$this->assertEquals([2037, 2038], [$eConfiguration['seasonFirst'], $eConfiguration['seasonLast']]);

	}

	/**
	 * Une saison s'incrémente d'un pas, sans croiser l'autre, et le mémo rend la valeur écrite : l'écriture
	 * en `Sql` ne la porte pas sur l'élément.
	 */
	public function testUpdateSeason(): void {

		$this->setClock('2037-06-15 10:00:00');

		ConfigurationLib::createForStructure($this->eStructure);

		ConfigurationLib::updateSeasonLast(ConfigurationLib::getByStructure($this->eStructure), -1);

		$this->assertEquals(2037, ConfigurationLib::getByStructure($this->eStructure)['seasonLast'], 'la dernière saison ne passe pas sous la première');

		ConfigurationLib::updateSeasonLast(ConfigurationLib::getByStructure($this->eStructure), 1);

		$this->assertEquals(2038, ConfigurationLib::getByStructure($this->eStructure)['seasonLast']);

		ConfigurationLib::updateSeasonFirst(ConfigurationLib::getByStructure($this->eStructure), -1);

		$this->assertEquals(2036, ConfigurationLib::getByStructure($this->eStructure)['seasonFirst']);

	}

	/** Le changement d'année avance la dernière saison, et le mémo de toutes les structures s'oublie. */
	public function testNextSeason(): void {

		$this->setClock('2037-06-15 10:00:00');

		ConfigurationLib::createForStructure($this->eStructure);

		$this->assertEquals(2037, ConfigurationLib::getByStructure($this->eStructure)['seasonLast']);

		$this->setClock('2037-12-01 10:00:00');

		ConfigurationLib::createNextSeason();

		$this->assertEquals(2038, ConfigurationLib::getByStructure($this->eStructure)['seasonLast']);

	}

	/** Le terrain posé ou le décor adopté sans ligne créent celle-ci avec les saisons d'une structure neuve. */
	public function testCreateAndAdoptWithoutRow(): void {

		$this->setClock('2037-06-15 10:00:00');

		$fw = new \FailWatch();

		$this->createConfiguration(['decorGrass' => Configuration::PALE, 'decorSoil' => Configuration::SCHIST, 'decorMeadow' => Configuration::MIXED], $fw);

		$this->assertTrue($fw->ok());
		$this->assertEquals([2037, 2037], array_values(ConfigurationLib::readByStructure($this->eStructure)->extracts(['seasonFirst', 'seasonLast'])));

		ConfigurationLib::deleteByStructure($this->eStructure);

		ConfigurationLib::adoptDecor($this->eStructure);

		$eConfiguration = ConfigurationLib::readByStructure($this->eStructure);

		$this->assertTrue($eConfiguration['decorAdopted']);
		$this->assertEquals([2037, 2037], [$eConfiguration['seasonFirst'], $eConfiguration['seasonLast']]);

	}

	/** Les largeurs de planche refusent moins de 5 cm, comme l'écran « Les cultures ». */
	public function testDefaultBedWidth(): void {

		ConfigurationLib::createForStructure($this->eStructure);

		$fw = new \FailWatch();

		$eConfiguration = ConfigurationLib::readByStructure($this->eStructure);
		$eConfiguration->build(['defaultBedWidth'], ['defaultBedWidth' => 4], new \PropertyList('update'));

		$this->assertTrue($fw->has('Configuration::defaultBedWidth.size'));

	}

	/** La duplication retire la ligne posée à la création, pour faire place à celle de sa source. */
	public function testDeleteByStructure(): void {

		ConfigurationLib::adoptDecor($this->eStructure);
		$this->assertEquals(1, $this->countRows());

		ConfigurationLib::deleteByStructure($this->eStructure);
		$this->assertEquals(0, $this->countRows());

		ConfigurationLib::deleteByStructure($this->eStructure);
		$this->assertEquals(0, $this->countRows());

	}

	/** Le terrain reste hors de l'API : aucun contrat ne nomme le module. */
	public function testNotInApi(): void {

		$named = [];

		foreach(\dev\SentinelLib::getFiles('.a.php', FALSE) as $file => $path) {

			if(preg_match('/map\\\\+Configuration\b/', file_get_contents($path)) === 1) {
				$named[] = $file;
			}

		}

		$this->assertEquals([], $named, 'un contrat d\'API nomme map\Configuration, que le lot 9 tient hors de l\'API : c\'est le contrat qui se retire');

	}

}
?>
