<?php
namespace structure;

/**
 * `ConfigurationLib::getConfigurationForDate()` rend l'assujettissement à la TVA à la date donnée,
 * lu dans l'historique. Une ligne d'historique porte la valeur à partir de sa date d'effet, et une
 * ligne sans valeur dit « non renseigné » à partir de la sienne. Avant la première ligne, rien n'est
 * connu : être redevable au 2026-01-01 ne dit rien de 2025 (décidé le 2026-09-28, qui
 * remplace la lecture de l'inverse avant le premier changement). Sans aucune ligne, la réponse du
 * jour vaut pour toutes les dates.
 *
 * La condition qui choisissait les champs à lire dans l'historique a été inversée le 2026-04-01
 * (3b6242e3f) : la méthode rendait alors la réponse du jour pour `hasVat`, et lisait l'historique
 * pour les champs qui n'en ont pas.
 *
 * S'appuie sur la F3 du décor, dont la réponse et l'historique sont posés dans une transaction annulée,
 * comme la 6278 de la copie de production : non redevable depuis le 2019-01-01, redevable depuis le
 * 2026-01-01.
 *
 * Lancement : dev/test package=structure file=ConfigurationForDateAi
 */
class ConfigurationForDateAiTest extends \Test {

	const STRUCTURE = FixtureAiLib::F3;

	public function setUp(): void {

		parent::setUp();

		Configuration::model()->beginTransaction();

		Configuration::model()
			->whereStructure(self::STRUCTURE)
			->update(['hasVat' => TRUE]);

		ConfigurationHistory::model()
			->whereStructure(self::STRUCTURE)
			->delete();

	}

	public function tearDown(): void {
		Configuration::model()->rollBack();
		parent::tearDown();
	}

	private function structure(): Structure {
		return Structure::model()->select(Structure::getSelection())->whereId(self::STRUCTURE)->get();
	}

	private function history(string $field, mixed $value, string $effectiveAt): void {

		$eConfigurationHistory = new ConfigurationHistory([
			'structure' => new Structure(['id' => self::STRUCTURE]),
			'field' => $field,
			'value' => [$field => $value],
			'effectiveAt' => $effectiveAt,
		]);

		ConfigurationHistory::model()->insert($eConfigurationHistory);

	}

	public function testWithoutHistoryTheAnswerOfTheDay(): void {

		$eStructure = $this->structure();

		$this->assertTrue($eStructure->notEmpty(), 'prémisse : la F3 du décor existe en base');
		$this->assertEquals(1, Configuration::model()->whereStructure(self::STRUCTURE)->count(), 'prémisse : la F3 porte sa ligne de configuration');
		$this->assertEquals(TRUE, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', '2020-06-01'), 'sans historique, la réponse du jour');

	}

	public function testTheAnswerAtTheDate(): void {

		$this->history('hasVat', FALSE, '2019-01-01');
		$this->history('hasVat', TRUE, '2026-01-01');

		$eStructure = $this->structure();

		foreach([
			'2018-06-30' => NULL,
			'2019-01-01' => FALSE,
			'2025-01-01' => FALSE,
			'2025-12-31' => FALSE,
			'2026-01-01' => TRUE,
			'2026-06-30' => TRUE,
		] as $date => $expected) {
			$this->assertEquals($expected, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', $date), $date);
		}

		$this->assertFalse(ConfigurationLib::getHasVatByDate($eStructure, '2025-01-01'), 'le booléen suit la date');

	}

	public function testBeforeTheFirstLineNothingIsKnown(): void {

		$this->history('hasVat', FALSE, '2026-01-01');

		$eStructure = $this->structure();

		$this->assertEquals(NULL, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', '2025-06-01'), 'non redevable au 2026-01-01 ne dit rien de 2025');
		$this->assertEquals(FALSE, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', '2026-06-01'), 'puis non redevable');
		$this->assertFalse(ConfigurationLib::getHasVatByDate($eStructure, '2025-06-01'), 'le booléen ne suppose pas redevable ce qui n\'est pas renseigné');

	}

	public function testALineWithoutValueIsUnknown(): void {

		$this->history('hasVat', TRUE, '2024-01-01');
		$this->history('hasVat', NULL, '2025-01-01');

		$eStructure = $this->structure();

		$this->assertEquals(TRUE, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', '2024-12-31'), 'redevable pendant 2024');
		$this->assertEquals(NULL, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', '2025-01-01'), 'non renseigné ensuite');

	}

	public function testCreateForPeriodBoundsThePeriod(): void {

		$eStructure = $this->structure();

		ConfigurationHistoryLib::createForPeriod($eStructure, 'hasVat', TRUE, '2024-01-01', '2024-12-31');

		foreach([
			'2023-12-31' => NULL,
			'2024-01-01' => TRUE,
			'2024-12-31' => TRUE,
			'2025-01-01' => NULL,
		] as $date => $expected) {
			$this->assertEquals($expected, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', $date), 'exercice 2024 seul, '.$date);
		}

	}

	public function testCreateForPeriodKeepsTheNextLine(): void {

		$this->history('hasVat', FALSE, '2025-01-01');

		$eStructure = $this->structure();

		ConfigurationHistoryLib::createForPeriod($eStructure, 'hasVat', TRUE, '2024-01-01', '2024-12-31');

		$this->assertEquals(TRUE, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', '2024-06-01'), 'la réponse vaut pour 2024');
		$this->assertEquals(FALSE, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', '2025-01-01'), 'la ligne suivante n\'est pas remplacée par « non renseigné »');

	}

	public function testCreateForPeriodWithoutEnd(): void {

		$eStructure = $this->structure();

		ConfigurationHistoryLib::createForPeriod($eStructure, 'hasVat', FALSE, '2026-01-01');

		$this->assertEquals(NULL, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', '2025-12-31'), 'rien avant');
		$this->assertEquals(FALSE, ConfigurationLib::getConfigurationForDate($eStructure, 'hasVat', '2030-01-01'), 'la réponse vaut ensuite, faute de fin');

	}

	private function answer(?bool $hasVatBefore, string $answer): Configuration {

		Configuration::model()
			->whereStructure(self::STRUCTURE)
			->update(['hasVat' => $hasVatBefore]);

		$eStructure = $this->structure();

		$eConfiguration = ConfigurationLib::readByStructure($eStructure);
		$eConfiguration['structure'] = $eStructure;
		$eConfiguration->build(['hasVat'], ['hasVat' => $answer]);

		return $eConfiguration;

	}

	private function hasVatOfTheDay(): ?bool {
		return Configuration::model()->whereStructure(self::STRUCTURE)->getValue('hasVat');
	}

	/**
	 * La porte de la liasse, sur l'exercice en cours d'une structure qui n'a jamais répondu : la
	 * réponse vaut à partir du début de l'exercice, et devient celle de la structure.
	 */
	public function testTheGateOnTheCurrentYearAnswersForTheStructure(): void {

		$this->setClock('2026-06-15 10:00:00');

		ConfigurationLib::updateHasVatForPeriod($this->answer(NULL, '1'), '2026-01-01', '2026-12-31');

		$this->assertEquals(TRUE, $this->hasVatOfTheDay(), 'la réponse devient celle de la structure');
		$this->assertEquals(TRUE, ConfigurationLib::getConfigurationForDate($this->structure(), 'hasVat', '2026-03-01'), 'elle vaut dans l\'exercice');
		$this->assertEquals(NULL, ConfigurationLib::getConfigurationForDate($this->structure(), 'hasVat', '2025-06-01'), 'et ne dit rien de l\'exercice d\'avant');

	}

	/**
	 * Sur l'exercice en cours d'une structure qui a déjà répondu, la réponse du jour peut venir d'un
	 * changement en cours d'exercice : la porte ne la réécrit pas (point 5 du relecteur).
	 */
	public function testTheGateKeepsTheAnswerOfTheDay(): void {

		$this->setClock('2026-06-15 10:00:00');

		$this->history('hasVat', FALSE, '2026-04-01');

		ConfigurationLib::updateHasVatForPeriod($this->answer(FALSE, '1'), '2026-01-01', '2026-12-31');

		$this->assertEquals(FALSE, $this->hasVatOfTheDay(), 'la réponse du jour est gardée');
		$this->assertEquals(TRUE, ConfigurationLib::getConfigurationForDate($this->structure(), 'hasVat', '2026-02-01'), 'la réponse de la porte vaut jusqu\'au changement');
		$this->assertEquals(FALSE, ConfigurationLib::getConfigurationForDate($this->structure(), 'hasVat', '2026-05-01'), 'le changement vaut ensuite');

	}

	/**
	 * Sur un exercice passé, la réponse est bornée à l'exercice et ne touche pas la structure.
	 */
	public function testTheGateOnAPastYearIsBounded(): void {

		$this->setClock('2026-06-15 10:00:00');

		ConfigurationLib::updateHasVatForPeriod($this->answer(NULL, '1'), '2024-01-01', '2024-12-31');

		$this->assertEquals(NULL, $this->hasVatOfTheDay(), 'la structure reste sans réponse');
		$this->assertEquals(TRUE, ConfigurationLib::getConfigurationForDate($this->structure(), 'hasVat', '2024-06-01'), 'la réponse vaut dans l\'exercice');
		$this->assertEquals(NULL, ConfigurationLib::getConfigurationForDate($this->structure(), 'hasVat', '2025-01-01'), 'et plus au lendemain de sa fin');

	}

	/**
	 * Une première réponse, par les réglages ou l'onboarding, s'écrit aussi dans l'historique à sa date
	 * d'effet : sans cette ligne, elle vaudrait pour toutes les dates, y compris les exercices sur
	 * lesquels la structure n'a rien dit (décidé le 2026-09-28).
	 */
	public function testTheFirstAnswerIsWrittenAtItsDate(): void {

		Configuration::model()
			->whereStructure(self::STRUCTURE)
			->update(['hasVat' => NULL]);

		$eStructure = $this->structure();

		$eConfiguration = ConfigurationLib::readByStructure($eStructure);
		$eConfiguration['structure'] = $eStructure;
		$eConfiguration->build(['hasVat'], ['hasVat' => '1']);

		$_POST['hasVatEffectiveAt'] = '2026-01-01';
		ConfigurationLib::update($eConfiguration, ['hasVat']);
		unset($_POST['hasVatEffectiveAt']);

		$cConfigurationHistory = ConfigurationHistory::model()
			->select(['value', 'effectiveAt'])
			->whereStructure(self::STRUCTURE)
			->whereField('hasVat')
			->getCollection();

		$this->assertEquals(1, $cConfigurationHistory->count(), 'une ligne écrite');
		$this->assertEquals('2026-01-01', $cConfigurationHistory->first()['effectiveAt'], 'à la date postée');
		$this->assertEquals(TRUE, ConfigurationLib::getConfigurationForDate($this->structure(), 'hasVat', '2026-06-01'), 'la réponse vaut à partir de sa date');
		$this->assertEquals(NULL, ConfigurationLib::getConfigurationForDate($this->structure(), 'hasVat', '2025-06-01'), 'et ne dit rien de l\'année d\'avant');

	}

	public function testAnAnswerLeftEmptyWritesNothing(): void {

		Configuration::model()
			->whereStructure(self::STRUCTURE)
			->update(['hasVat' => NULL]);

		$eStructure = $this->structure();

		$eConfiguration = ConfigurationLib::readByStructure($eStructure);
		$eConfiguration['structure'] = $eStructure;
		$eConfiguration['hasVatOld'] = NULL;

		$_POST['hasVatEffectiveAt'] = '2026-01-01';
		ConfigurationLib::update($eConfiguration, ['hasVat']);
		unset($_POST['hasVatEffectiveAt']);

		$this->assertFalse(ConfigurationHistoryLib::hasHistory($eStructure, 'hasVat'), 'aucune ligne pour une réponse qui ne change rien');

	}

}
