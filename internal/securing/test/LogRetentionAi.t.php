<?php
namespace securing;

/**
 * La purge du journal (`LogLib::clean()`, cron de 4 h 17) garde dix ans les traces d'un geste de conformité
 * (`LogSetting::LOG_RETENTION_LEGAL`) et efface les autres au bout de 730 jours, ou de leur durée propre.
 *
 * Le journal vit dans la base de la structure : le test ouvre celle de la F1 du décor, et la transaction de
 * chaque méthode rend la table telle qu'elle était. `clean()` purge toute la table, les lignes de dev comprises,
 * ce que l'annulation rend aussi.
 *
 * Lancement : dev/test package=securing file=LogRetentionAi
 */
class LogRetentionAiTest extends \dev\TransactionTest {

	const COMMENT = 'LogRetentionAi';

	protected function getTransactionModel(): \ModuleModel {
		return Log::model();
	}

	public function init(): void {

		$eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\structure\FixtureAiLib::F1)
			->get();

		if($eStructure->empty()) {
			throw new \Exception('Structure de référence #'.\structure\FixtureAiLib::F1.' introuvable : sème le décor par structure/configure/fixtureAi write=1');
		}

		\structure\StructureLib::connectDatabase($eStructure, migrate: TRUE);

	}

	public function testAComplianceTraceOutlivesTheDefault(): void {

		$this->insert(LogSetting::EVENT_FINANCIAL_YEAR_REOPEN, 731);
		$this->insert(LogSetting::EVENT_CASH_VALIDATE, 731);
		$this->insert(LogSetting::EVENT_ACCOUNT_CREATE, 731);

		LogLib::clean();

		$this->assertEquals([LogSetting::EVENT_CASH_VALIDATE, LogSetting::EVENT_FINANCIAL_YEAR_REOPEN], $this->remaining(), 'à 731 jours, la réouverture et la validation de caisse restent, la création d\'un compte part');

	}

	public function testAComplianceTraceGoesAfterTenYears(): void {

		$this->insert(LogSetting::EVENT_FINANCIAL_YEAR_REOPEN, LogSetting::LOG_RETENTION_LEGAL + 1);
		$this->insert(LogSetting::EVENT_FINANCIAL_YEAR_CLOSE, LogSetting::LOG_RETENTION_LEGAL - 1);

		LogLib::clean();

		$this->assertEquals([LogSetting::EVENT_FINANCIAL_YEAR_CLOSE], $this->remaining(), 'passé dix ans, la trace part ; la veille, elle reste');

	}

	public function testOtherDurationsAreUnchanged(): void {

		$this->insert(LogSetting::EVENT_PDP_GET_ACCESS_TOKEN, 91);
		$this->insert(LogSetting::EVENT_ACCOUNT_CREATE, 729);

		LogLib::clean();

		$this->assertEquals([LogSetting::EVENT_ACCOUNT_CREATE], $this->remaining(), 'un jeton part à 91 jours, un événement ordinaire reste avant 730');

	}

	private function insert(string $event, int $days): void {

		$eLog = new Log([
			'module' => LogSetting::EVENTS[$event][0],
			'event' => $event,
			'data' => [],
			'comment' => self::COMMENT,
			'level' => LogSetting::EVENTS[$event][1],
			'channel' => Log::WEB,
			'doneBy' => new \user\User(),
			// La purge compare à NOW() en SQL : l'âge se pose de la même façon, que setClock() ne déplacerait pas
			'doneAt' => new \Sql('NOW() - INTERVAL '.$days.' DAY'),
		]);

		// Sans build() : la trace n'a aucun champ posté, et le cas veut une date d'effet passée que build() ne poserait
		// pas. La transaction de la méthode l'emporte.
		LogLib::create($eLog);

	}

	private function remaining(): array {

		return Log::model()
			->whereComment(self::COMMENT)
			->sort(['event' => SORT_ASC])
			->getColumn('event');

	}

}
?>
