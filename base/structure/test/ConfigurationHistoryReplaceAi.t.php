<?php
namespace structure;

/**
 * Un changement de redevabilité ou d'exigibilité daté sur un choix de l'historique ou avant lui remplace ce choix et
 * tous ceux d'après (arbitrage du 2026-10-18, planche `conformite-r2-exigibilite`, « remplace »). L'écran
 * montre ce qui disparaît avec une case à cocher, et `build()` refuse l'envoi sans elle, le POST étant contournable.
 *
 * Les formulaires qui changent le réglage sans poster de date (déclaration de résultat, régime de TVA de l'exercice,
 * écran des déclarations) n'inscrivent rien dans l'historique, et n'ont rien à confirmer.
 *
 * S'appuie sur la F3 du décor, dont la réponse et l'historique sont posés dans une transaction annulée.
 *
 * Lancement : dev/test package=structure file=ConfigurationHistoryReplaceAi
 */
class ConfigurationHistoryReplaceAiTest extends \dev\TransactionTest {

	const STRUCTURE = FixtureAiLib::F3;

	protected function getTransactionModel(): \ModuleModel {
		return Configuration::model();
	}

	public function setUp(): void {

		parent::setUp();

		// Écriture de masse hors Lib : le décor veut ces deux réglages sans passer par l'historique qu'il éprouve.
		// La transaction de la méthode l'emporte.
		Configuration::model()
			->whereStructure(self::STRUCTURE)
			->update(['hasVat' => TRUE, 'vatChargeability' => Configuration::CASH]);

		ConfigurationHistory::model()
			->whereStructure(self::STRUCTURE)
			->delete();

		$this->history('hasVat', TRUE, '2024-07-01');
		$this->history('hasVat', TRUE, '2025-01-01');
		$this->history('hasVat', NULL, '2026-01-01');
		$this->history('vatChargeability', Configuration::CASH, '2025-01-01');

		$_POST = [];

	}

	public function tearDown(): void {

		$_POST = [];

		parent::tearDown();

	}

	private function structure(): Structure {
		return Structure::model()->select(Structure::getSelection())->whereId(self::STRUCTURE)->get();
	}

	private function configuration(): Configuration {
		return ConfigurationLib::readByStructure($this->structure());
	}

	private function history(string $field, mixed $value, string $effectiveAt): void {

		$eConfigurationHistory = new ConfigurationHistory([
			'structure' => new Structure(['id' => self::STRUCTURE]),
			'field' => $field,
			'value' => [$field => $value],
			'effectiveAt' => $effectiveAt,
		]);

		// Hors Lib : le décor pose l'historique tel qu'une reprise ou createForPeriod() l'ont laissé
		ConfigurationHistory::model()->insert($eConfigurationHistory);

	}

	private function entries(string $field): array {

		return ConfigurationHistory::model()
			->select('effectiveAt', 'value')
			->whereStructure(self::STRUCTURE)
			->whereField($field)
			->whereOverwrittenBy(NULL)
			->sort(['effectiveAt' => SORT_ASC])
			->getCollection()
			->makeArray(fn(ConfigurationHistory $eConfigurationHistory) => [$eConfigurationHistory['effectiveAt'], $eConfigurationHistory['value'][$field]]);

	}

	private function overwritten(string $field): array {

		return ConfigurationHistory::model()
			->select('effectiveAt', 'value', 'overwrittenBy')
			->whereStructure(self::STRUCTURE)
			->whereField($field)
			->whereOverwrittenBy('!=', NULL)
			->sort(['effectiveAt' => SORT_ASC])
			->getCollection()
			->makeArray(fn(ConfigurationHistory $eConfigurationHistory) => [$eConfigurationHistory['effectiveAt'], $eConfigurationHistory['value'][$field], $eConfigurationHistory['overwrittenBy']]);

	}

	private function overwriteFrom(string $effectiveAt): void {

		[$eConfiguration] = $this->build(['hasVat'], ['hasVat' => '0', 'hasVatEffectiveAt' => $effectiveAt, 'hasVatHistoryConfirmed' => '1']);
		ConfigurationLib::update($eConfiguration, ['hasVat']);

		// update() lit la date d'effet dans le POST : une annulation qui suivrait l'inscrirait de nouveau
		$_POST = [];

	}

	private function periods(string $field): array {

		$eStructure = $this->structure();

		return ConfigurationHistoryLib::getPeriods($eStructure->conf(), $field, ConfigurationHistoryLib::getHistory($eStructure)[$field]);

	}

	private function build(array $properties, array $input): array {

		$_POST = $input;

		$eConfiguration = $this->configuration();

		$fw = new \FailWatch();
		$eConfiguration->build($properties, $_POST);

		return [$eConfiguration, $fw];

	}

	public function testTheReplacedChoicesStartAtTheDate(): void {

		$cConfigurationHistory = ConfigurationHistoryLib::getReplaced($this->structure(), 'hasVat', '2025-01-01');

		$this->assertEquals(['2025-01-01', '2026-01-01'], $cConfigurationHistory->getColumn('effectiveAt'), 'le choix de la même date et ceux d\'après, du plus ancien au plus récent');
		$this->assertEquals(NULL, $cConfigurationHistory->last()['value']['hasVat'], 'une entrée « non renseigné » de createForPeriod() est remplacée comme les autres');

		$this->assertTrue(ConfigurationHistoryLib::getReplaced($this->structure(), 'hasVat', '2026-01-02')->empty(), 'après la dernière entrée, rien n\'est remplacé');

	}

	public function testAnAntedatedChangeIsRefusedWithoutConfirmation(): void {

		[, $fw] = $this->build(['hasVat'], ['hasVat' => '0', 'hasVatEffectiveAt' => '2024-07-01']);

		$this->assertTrue($fw->has('Configuration::hasVat.historyConfirmed'), 'trois choix seraient supprimés sans que la case soit cochée, lu : '.json_encode($fw->get()));

	}

	public function testAConfirmedAntedatedChangeReplacesTheLaterChoices(): void {

		[$eConfiguration, $fw] = $this->build(['hasVat'], ['hasVat' => '0', 'hasVatEffectiveAt' => '2024-07-01', 'hasVatHistoryConfirmed' => '1']);

		$this->assertTrue($fw->ok(), 'la case cochée, l\'envoi passe, lu : '.json_encode($fw->get()));

		ConfigurationLib::update($eConfiguration, ['hasVat']);

		$this->assertEquals([['2024-07-01', FALSE]], $this->entries('hasVat'), 'le choix de la même date et ceux d\'après ne sont plus en vigueur');
		$this->assertEquals([['2024-07-01', TRUE, '2024-07-01'], ['2025-01-01', TRUE, '2024-07-01'], ['2026-01-01', NULL, '2024-07-01']], $this->overwritten('hasVat'), 'ils restent dans l\'historique, écrasés par le changement du 01/07/2024');
		$this->assertEquals([['2025-01-01', Configuration::CASH]], $this->entries('vatChargeability'), 'l\'historique de l\'autre réglage ne bouge pas');

	}

	public function testAChangeAfterTheLastChoiceAsksNothing(): void {

		[$eConfiguration, $fw] = $this->build(['hasVat'], ['hasVat' => '0', 'hasVatEffectiveAt' => '2026-06-01']);

		$this->assertTrue($fw->ok(), 'aucun choix n\'est remplacé, rien n\'est à confirmer, lu : '.json_encode($fw->get()));

		ConfigurationLib::update($eConfiguration, ['hasVat']);

		$this->assertEquals([['2024-07-01', TRUE], ['2025-01-01', TRUE], ['2026-01-01', NULL], ['2026-06-01', FALSE]], $this->entries('hasVat'), 'le changement s\'ajoute à l\'historique');

	}

	public function testAChangeWithoutDateAsksNothingAndWritesNoHistory(): void {

		[$eConfiguration, $fw] = $this->build(['hasVat'], ['hasVat' => '0']);

		$this->assertTrue($fw->ok(), 'un formulaire sans date d\'effet n\'a rien à confirmer, lu : '.json_encode($fw->get()));

		ConfigurationLib::update($eConfiguration, ['hasVat']);

		$this->assertFalse($this->configuration()['hasVat'], 'le réglage change');
		$this->assertEquals([['2024-07-01', TRUE], ['2025-01-01', TRUE], ['2026-01-01', NULL]], $this->entries('hasVat'), 'l\'historique reste tel quel');

	}

	public function testAnUnchangedValueAsksNothing(): void {

		[, $fw] = $this->build(['hasVat'], ['hasVat' => '1', 'hasVatEffectiveAt' => '2024-07-01']);

		$this->assertTrue($fw->ok(), 'la valeur enregistrée, reposée avec une date, ne remplace rien, lu : '.json_encode($fw->get()));

	}

	public function testAForgedDateDoesNotFail(): void {

		[, $fw] = $this->build(['hasVat'], ['hasVat' => '0', 'hasVatEffectiveAt' => ['2024-07-01']]);

		$this->assertFalse($fw->has('Configuration::hasVat.historyConfirmed'), 'une date postée en tableau ne se pèse pas, update() la refusera');

	}

	public function testCancellingTheLastChangeRestoresTheSetting(): void {

		// Écriture hors Lib : le décor veut le réglage que le dernier changement a posé, sans passer par update()
		Configuration::model()
			->whereStructure(self::STRUCTURE)
			->update(['hasVat' => NULL]);

		$this->history('vatChargeability', Configuration::DEBIT, '2026-01-01');

		// Écriture hors Lib, pour la même raison : l'exigibilité que le changement du 01/01/2026 a posée
		Configuration::model()
			->whereStructure(self::STRUCTURE)
			->update(['vatChargeability' => Configuration::DEBIT]);

		ConfigurationHistoryLib::cancel($this->structure(), 'hasVat', '2026-01-01');

		$this->assertEquals([['2024-07-01', TRUE], ['2025-01-01', TRUE]], $this->entries('hasVat'), 'le changement annulé part');
		$this->assertTrue($this->configuration()['hasVat'], 'le réglage reprend la valeur du changement d\'avant');

		ConfigurationHistoryLib::cancel($this->structure(), 'vatChargeability', '2026-01-01');

		$this->assertEquals(Configuration::CASH, $this->configuration()['vatChargeability'], 'l\'exigibilité reprend la valeur d\'avant');

		ConfigurationHistoryLib::cancel($this->structure(), 'vatChargeability', '2025-01-01');

		$this->assertEquals([], $this->entries('vatChargeability'), 'plus aucun changement d\'exigibilité');
		$this->assertEquals(Configuration::DEBIT, $this->configuration()['vatChargeability'], 'sans changement restant, l\'exigibilité reprend celle que le premier avait remplacée');

	}

	public function testCancellingAnOlderChangeKeepsTheSetting(): void {

		ConfigurationHistoryLib::cancel($this->structure(), 'hasVat', '2025-01-01');

		$this->assertEquals([['2024-07-01', TRUE], ['2026-01-01', NULL]], $this->entries('hasVat'), 'seul le changement annulé part');
		$this->assertTrue($this->configuration()['hasVat'], 'le réglage ne vient pas d\'un changement plus ancien que le dernier, il ne bouge pas');

	}

	public function testCancellingTheLastChangeKeepsASettingItDidNotSet(): void {

		// Écriture hors Lib : le décor veut un réglage changé sans date, que le dernier changement n'a pas posé
		Configuration::model()
			->whereStructure(self::STRUCTURE)
			->update(['hasVat' => FALSE]);

		ConfigurationHistoryLib::cancel($this->structure(), 'hasVat', '2026-01-01');

		$this->assertEquals([['2024-07-01', TRUE], ['2025-01-01', TRUE]], $this->entries('hasVat'), 'le changement annulé part');
		$this->assertFalse($this->configuration()['hasVat'], 'le réglage ne porte pas le « non renseigné » du dernier changement, il n\'en vient pas et ne reprend pas le « oui » d\'avant');

	}

	public function testCancellingTheOnlyRedevabilityChangeKeepsTheSetting(): void {

		// Écriture de masse hors Lib : le décor ne garde que le changement du 01/01/2025
		ConfigurationHistory::model()
			->whereStructure(self::STRUCTURE)
			->whereField('hasVat')
			->whereEffectiveAt('!=', '2025-01-01')
			->delete();

		ConfigurationHistoryLib::cancel($this->structure(), 'hasVat', '2025-01-01');

		$this->assertEquals([], $this->entries('hasVat'), 'le changement part');
		$this->assertTrue($this->configuration()['hasVat'], 'aucune valeur d\'avant : le réglage ne repasse pas à « non renseigné »');

	}

	public function testAnAntedatedChargeabilityChangeIsConfirmedToo(): void {

		[, $fw] = $this->build(['vatChargeability'], ['vatChargeability' => Configuration::DEBIT, 'vatChargeabilityEffectiveAt' => '2024-01-01']);

		$this->assertTrue($fw->has('Configuration::vatChargeability.historyConfirmed'), 'le choix du 01/01/2025 serait supprimé sans confirmation, lu : '.json_encode($fw->get()));

		[$eConfiguration, $fw] = $this->build(['vatChargeability'], ['vatChargeability' => Configuration::DEBIT, 'vatChargeabilityEffectiveAt' => '2024-01-01', 'vatChargeabilityHistoryConfirmed' => '1']);

		$this->assertTrue($fw->ok(), 'confirmé, l\'envoi passe, lu : '.json_encode($fw->get()));

		ConfigurationLib::update($eConfiguration, ['vatChargeability']);

		$this->assertEquals([['2024-01-01', Configuration::DEBIT]], $this->entries('vatChargeability'), 'le choix d\'après n\'est plus en vigueur');
		$this->assertEquals([['2025-01-01', Configuration::CASH, '2024-01-01']], $this->overwritten('vatChargeability'), 'il reste dans l\'historique, écrasé');

	}

	public function testTheReadsIgnoreTheOverwrittenChoices(): void {

		$this->overwriteFrom('2024-07-01');

		$this->assertFalse(ConfigurationHistoryLib::getForDate($this->structure(), 'hasVat', '2025-06-01')['value']['hasVat'], 'au 01/06/2025, le choix écrasé du 01/01/2025 ne vaut plus');
		$this->assertEquals(['2024-07-01'], ConfigurationHistoryLib::getByField($this->structure(), 'hasVat')->getColumn('effectiveAt'), 'les choix en vigueur seuls');
		$this->assertEquals(['2024-07-01'], ConfigurationHistoryLib::getReplaced($this->structure(), 'hasVat', '2024-01-01')->getColumn('effectiveAt'), 'un nouveau changement antidaté n\'écrase que les choix en vigueur');

	}

	public function testCancellingTheOverwritingChangeLeavesTheOverwrittenChoices(): void {

		$this->overwriteFrom('2025-01-01');

		ConfigurationHistoryLib::cancel($this->structure(), 'hasVat', '2025-01-01');

		$this->assertEquals([['2024-07-01', TRUE]], $this->entries('hasVat'), 'le changement annulé part');
		$this->assertEquals([['2025-01-01', TRUE, '2025-01-01'], ['2026-01-01', NULL, '2025-01-01']], $this->overwritten('hasVat'), 'les choix qu\'il avait écrasés ne reviennent pas');
		$this->assertTrue($this->configuration()['hasVat'], 'le réglage reprend la valeur du changement en vigueur d\'avant');

	}

	public function testAChangeAtTheDateOfAnOverwrittenChoice(): void {

		$this->overwriteFrom('2024-07-01');

		[$eConfiguration] = $this->build(['hasVat'], ['hasVat' => '1', 'hasVatEffectiveAt' => '2025-01-01']);
		ConfigurationLib::update($eConfiguration, ['hasVat']);
		$_POST = [];

		$this->assertEquals([['2024-07-01', FALSE], ['2025-01-01', TRUE]], $this->entries('hasVat'), 'le choix s\'écrit à côté du choix écrasé de même date');

		ConfigurationHistoryLib::cancel($this->structure(), 'hasVat', '2025-01-01');

		$this->assertEquals([['2024-07-01', FALSE]], $this->entries('hasVat'), 'l\'annulation ne retire que le choix en vigueur');
		$this->assertEquals([['2024-07-01', TRUE, '2024-07-01'], ['2025-01-01', TRUE, '2024-07-01'], ['2026-01-01', NULL, '2024-07-01']], $this->overwritten('hasVat'), 'le choix écrasé de même date reste');

	}

	public function testCreateForPeriodReplacesTheChoiceInForce(): void {

		ConfigurationHistoryLib::createForPeriod($this->structure(), 'hasVat', FALSE, '2025-01-01');

		$this->assertEquals([['2024-07-01', TRUE], ['2025-01-01', FALSE], ['2026-01-01', NULL]], $this->entries('hasVat'), 'la valeur de l\'exercice remplace la ligne de même date, sans s\'y ajouter');
		$this->assertEquals([], $this->overwritten('hasVat'), 'une borne d\'exercice n\'écrase rien');

	}

	public function testMovingAChangeCarriesTheChoicesItOverwrote(): void {

		$this->overwriteFrom('2025-01-01');

		ConfigurationHistoryLib::move($this->structure(), 'hasVat', '2025-01-01', '2025-02-01');

		$this->assertEquals([['2024-07-01', TRUE], ['2025-02-01', FALSE]], $this->entries('hasVat'), 'le changement suit la borne');
		$this->assertEquals([['2025-01-01', TRUE, '2025-02-01'], ['2026-01-01', NULL, '2025-02-01']], $this->overwritten('hasVat'), 'les choix écrasés désignent la nouvelle date');

	}

	public function testCancellingTheLastChangeAfterANullChoice(): void {

		// Écriture hors Lib : le décor veut le réglage que le changement du 01/06/2026 a posé
		Configuration::model()
			->whereStructure(self::STRUCTURE)
			->update(['hasVat' => FALSE]);

		$this->history('hasVat', FALSE, '2026-06-01');

		ConfigurationHistoryLib::cancel($this->structure(), 'hasVat', '2026-06-01');

		$this->assertEquals(NULL, $this->configuration()['hasVat'], 'le réglage reprend le « non renseigné » du changement d\'avant');

	}

	public function testThePeriodsOfTheRedevability(): void {

		$this->setClock('2026-10-19 12:00:00');

		$this->overwriteFrom('2025-01-01');

		$read = array_map(fn(array $period) => [
			$period['from'], $period['until'], $period['value'], $period['isFuture'],
			array_map(fn(array $change) => [$change['eConfigurationHistory']['effectiveAt'], $change['type'], $change['valueAfterCancel']], $period['changes']),
		], $this->periods('hasVat'));

		$this->assertEquals([
			['2025-01-01', NULL, FALSE, FALSE, [
				['2026-01-01', 'overwritten', NULL],
				['2025-01-01', 'first', TRUE],
				['2025-01-01', 'overwritten', NULL],
			]],
			['2024-07-01', '2024-12-31', TRUE, FALSE, [
				['2024-07-01', 'first', FALSE],
			]],
			[NULL, '2024-06-30', NULL, FALSE, []],
		], $read, 'du plus récent au plus ancien, les écrasés rangés dans la période qui couvre leur date ; annuler le dernier changement rend le « oui » d\'avant, annuler un plus ancien laisse le réglage');

	}

	public function testThePeriodsWithOnlyOverwrittenChoices(): void {

		// Écriture de masse hors Lib : le décor ne garde que le changement du 01/01/2025
		ConfigurationHistory::model()
			->whereStructure(self::STRUCTURE)
			->whereField('hasVat')
			->whereEffectiveAt('!=', '2025-01-01')
			->delete();

		$this->overwriteFrom('2025-01-01');

		ConfigurationHistoryLib::cancel($this->structure(), 'hasVat', '2025-01-01');

		$periods = $this->periods('hasVat');

		$this->assertEquals([[NULL, NULL, FALSE]], array_map(fn(array $period) => [$period['from'], $period['until'], $period['value']], $periods), 'une période sans bornes, au réglage');
		$this->assertEquals(['overwritten'], array_column($periods[0]['changes'], 'type'), 'le choix écrasé reste à lire');

	}

	public function testThePeriodsConfirmAndLookAhead(): void {

		$this->setClock('2025-03-01 12:00:00');

		$periods = $this->periods('hasVat');

		$this->assertEquals(['2026-01-01', '2024-07-01', NULL], array_column($periods, 'from'), 'deux « oui » à la suite font une période');
		$this->assertEquals([TRUE, FALSE, FALSE], array_column($periods, 'isFuture'), 'la période qui n\'a pas commencé se dit à venir');
		$this->assertEquals(['confirmed', 'first'], array_column($periods[1]['changes'], 'type'), 'le second « oui » confirme le premier');

	}

	public function testThePeriodsOfTheChargeability(): void {

		foreach([Configuration::CASH => Configuration::DEBIT, Configuration::DEBIT => Configuration::CASH] as $value => $before) {

			// Écriture hors Lib : le décor veut le réglage que le seul changement d'exigibilité a posé
			Configuration::model()
				->whereStructure(self::STRUCTURE)
				->update(['vatChargeability' => $value]);

			// Écriture de masse hors Lib : le décor remplace le changement d'exigibilité de setUp()
			ConfigurationHistory::model()
				->whereStructure(self::STRUCTURE)
				->whereField('vatChargeability')
				->delete();

			$this->history('vatChargeability', $value, '2025-01-01');

			$periods = $this->periods('vatChargeability');

			$this->assertEquals([$value, $before], array_column($periods, 'value'), 'avant le premier changement, l\'exigibilité qu\'il a remplacée ('.$value.')');
			$this->assertEquals($before, $periods[0]['changes'][0]['valueAfterCancel'], 'l\'annuler rend cette exigibilité ('.$value.')');

		}

	}

}
?>
