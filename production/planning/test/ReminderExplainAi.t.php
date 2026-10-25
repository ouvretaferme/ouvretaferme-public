<?php
namespace planning;

/**
 * Les plans d'exécution des lectures du pense-bête (FX) : chacune lit un index, jamais la table entière.
 * Les rappels cochés se lisent par la date de leur coche, que leur semaine ne borne pas, sur l'index
 * `(structure, status, doneAt)` ; ceux à faire et ceux cochés en avance sur `(structure, status, week)`.
 *
 * Un `EXPLAIN` ne vaut que sur un volume réel, et la table est trop jeune pour en avoir un : sur quelques
 * lignes, l'optimiseur préfère balayer la table, et le test dirait faux. Le décor pose donc l'historique
 * d'une structure qui écrit plusieurs rappels par semaine depuis plus d'un an, coché pour moitié, dans la
 * transaction de la méthode.
 *
 * Chaque requête est construite par la méthode `apply*` que `ReminderLib` appelle, pour que l'`EXPLAIN`
 * mesure la requête du produit et non une copie. Le nom de l'index n'est pas figé : le test juge l'accès,
 * pas l'index que le moteur choisit.
 *
 * Lancement : dev/test package=planning file=ReminderExplainAi
 */
class ReminderExplainAiTest extends HarvestFixtureTest {

	/**
	 * Les quatre lectures, depuis la semaine en cours, une semaine passée de six mois et une semaine à
	 * venir : `getTodoUntilWeek()`, `getDoneSinceWeek()`, `getDoneAheadWeek()` et celle de l'accueil,
	 * `getDoneSinceDay()`.
	 */
	public function testReadsUseAnIndex(): void {

		$now = \Clock::datetime();
		$week = \Clock::week();

		$this->createHistory($week);

		$this->setClock($now);

		$weekPast = toWeek(week_date_starts($week).' -182 days');
		$weekNext = toWeek(week_date_starts($week).' +14 days');

		$queries = [
			'getTodoUntilWeek' => ReminderLib::applyTodoUntilWeek(new ReminderModel()->select(['id']), $this->eStructure, $week),
			'getTodoUntilWeek à venir' => ReminderLib::applyTodoUntilWeek(new ReminderModel()->select(['id']), $this->eStructure, $weekNext),
			'getDoneSinceWeek' => ReminderLib::applyDoneSinceWeek(new ReminderModel()->select(['id']), $this->eStructure, $week),
			'getDoneSinceWeek passée' => ReminderLib::applyDoneSinceWeek(new ReminderModel()->select(['id']), $this->eStructure, $weekPast),
			'getDoneAheadWeek' => ReminderLib::applyDoneAheadWeek(new ReminderModel()->select(['id']), $this->eStructure, $week),
			'getDoneAheadWeek à venir' => ReminderLib::applyDoneAheadWeek(new ReminderModel()->select(['id']), $this->eStructure, $weekNext),
			'getDoneSinceDay' => ReminderLib::applyDoneSinceDay(new ReminderModel()->select(['id']), $this->eStructure, \Clock::date(), $week),
		];

		foreach($queries as $name => $query) {

			$sql = (string)$query->getSql();
			$rows = Reminder::model()->pdo()->query('EXPLAIN '.$sql)->fetchAll(\PDO::FETCH_ASSOC);

			$this->assertTrue(in_array($rows[0]['type'], ['const', 'eq_ref', 'ref', 'range'], TRUE), $name.' ne lit pas d\'index : '.$sql.' '.json_encode($rows));
			$this->assertTrue($rows[0]['key'] !== NULL, $name.' ne nomme aucun index : '.$sql.' '.json_encode($rows));

		}

	}

	/**
	 * Trois rappels par semaine sur soixante-dix semaines jusqu'à celle en cours, deux sur trois cochés dans
	 * leur semaine, posés par le Lib comme le produit les pose.
	 */
	private function createHistory(string $week): void {

		for($weeksAgo = 69; $weeksAgo >= 0; $weeksAgo--) {

			$weekWritten = toWeek(week_date_starts($week).' -'.($weeksAgo * 7).' days');

			for($position = 1; $position <= 3; $position++) {

				// Sans `build()` : il vérifierait le texte (1 à 250) et le jour dans la semaine, que le décor
				// pose lui-même, et que `ReminderWeekTest::testCreate()` éprouve par la route.
				$eReminder = new Reminder([
					'structure' => $this->eStructure,
					'week' => $weekWritten,
					'date' => NULL,
					'text' => 'Rappel '.$position.' de '.$weekWritten,
				]);

				ReminderLib::create($eReminder);

				if($position !== 3) {
					$this->setClock(week_date_day($weekWritten, $position + 1).' 09:00:00');
					ReminderLib::check($eReminder);
				}

			}

		}

	}

}
?>
