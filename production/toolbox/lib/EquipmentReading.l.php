<?php
namespace toolbox;

/**
 * Les relevés du compteur d'un matériel, en heures ou en kilomètres, et ce que l'écran en tire.
 *
 * Un relevé dit ce que le compteur affiche un jour : la table n'en porte qu'un par matériel et par
 * jour, et un second relevé du même jour remplace le premier. Les heures d'un entretien fait
 * deviennent aussi le relevé de son jour (`MaintenanceDoneLib::create()`).
 */
class EquipmentReadingLib extends EquipmentReadingCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['equipment', 'date', 'value'];
	}

	// Un relevé se corrige depuis l'historique : son jour reste, seul le compteur change.
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['value'];
	}

	/**
	 * Pose le relevé du jour, ou remplace celui qui y est. L'`INSERT IGNORE` bute sur l'unicité
	 * (equipment, date), ce qui rend l'écriture sûre même quand deux personnes relèvent le même
	 * compteur au même instant. Le relevé garde l'unité du matériel, qui survit à un compteur coupé.
	 *
	 * Le tout premier relevé d'un matériel fait partir les entretiens réguliers qu'il mettrait en retard
	 * (`MaintenanceLib::planFromFirstReading()`). Tout relevé passe ici, qu'il vienne de « Mettre à jour
	 * le compteur », du suivi d'un matériel, du compteur d'un entretien fait ou de l'API.
	 */
	public static function create(EquipmentReading $e): void {

		$e->expects(['structure', 'equipment' => ['meter'], 'date', 'value']);

		$e['meter'] = $e['equipment']['meter'];

		EquipmentReading::model()->beginTransaction();

			$isFirst = (
				EquipmentReading::model()
					->whereEquipment($e['equipment'])
					->exists() === FALSE
			);

			$affected = EquipmentReading::model()
				->option('add-ignore')
				->insert($e);

			if($affected === 0) {

				// La ligne du jour n'est pas `$e`, qui n'a pas d'id : l'écriture se désigne par le jour, et
				// garde donc un tableau de valeurs.
				EquipmentReading::model()
					->whereEquipment($e['equipment'])
					->whereDate($e['date'])
					->update([
						'value' => $e['value']
					]);

			}

			if($isFirst) {
				MaintenanceLib::planFromFirstReading($e['equipment'], $e['value']);
			}

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		EquipmentReading::model()->commit();

	}

	/**
	 * Un relevé corrigé ou retiré change le dernier compteur, donc les retards au compteur que le
	 * planning montre (`planning\DueLib::refresh()`).
	 */
	public static function update(EquipmentReading $e, array $properties): void {

		$e->expects(['structure']);

		EquipmentReading::model()->beginTransaction();

			parent::update($e, $properties);

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		EquipmentReading::model()->commit();

	}

	public static function delete(EquipmentReading $e): void {

		$e->expects(['structure']);

		EquipmentReading::model()->beginTransaction();

			parent::delete($e);

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		EquipmentReading::model()->commit();

	}

	/**
	 * Le relevé d'avant `$date` ne dépasse pas `$value` : un compteur ne recule pas. Le relevé du jour
	 * même ne compte pas, `create()` le remplace.
	 */
	public static function acceptBefore(Equipment $eEquipment, string $date, int $value): bool {

		$before = EquipmentReading::model()
			->whereEquipment($eEquipment)
			->whereDate('<', $date)
			->getValue(new \Sql('MAX(value)', 'int'));

		return ($before === NULL or $before <= $value);

	}

	/**
	 * Le relevé d'après `$date` n'est pas sous `$value`.
	 */
	public static function acceptAfter(Equipment $eEquipment, string $date, int $value): bool {

		$after = EquipmentReading::model()
			->whereEquipment($eEquipment)
			->whereDate('>', $date)
			->getValue(new \Sql('MIN(value)', 'int'));

		return ($after === NULL or $after >= $value);

	}

	/**
	 * Un relevé qui sort de l'ordre du compteur n'est pas refusé quand la personne le confirme : il peut corriger
	 * une erreur (retour du 01/10, « ne pas bloquer mais mettre un confirm »). La réponse arrive dans
	 * `meterConfirmed`, posée par l'instruction `confirmReading` (`static/js/instruction.js`) : sans elle, un envoi direct
	 * passerait sans que la question ait été posée.
	 */
	public static function isConfirmed(array $input): bool {
		return (($input['meterConfirmed'] ?? '0') === '1');
	}

	/**
	 * Le relevé que contredit `$value` posé à `$date`, ou un élément vide : celui d'avant qui le dépasse,
	 * sinon celui d'après qui reste en dessous. Parmi plusieurs, celui qui contredit le plus, le même que
	 * `acceptBefore()` et `acceptAfter()` comparent.
	 */
	public static function getConflict(Equipment $eEquipment, string $date, int $value): EquipmentReading {

		$eEquipmentReading = EquipmentReading::model()
			->select(['date', 'value'])
			->whereEquipment($eEquipment)
			->whereDate('<', $date)
			->whereValue('>', $value)
			->sort(['value' => SORT_DESC, 'date' => SORT_DESC])
			->get();

		if($eEquipmentReading->notEmpty()) {
			return $eEquipmentReading;
		}

		return EquipmentReading::model()
			->select(['date', 'value'])
			->whereEquipment($eEquipment)
			->whereDate('>', $date)
			->whereValue('<', $value)
			->sort(['value' => SORT_ASC, 'date' => SORT_ASC])
			->get();

	}

	/**
	 * Le relevé que contredit un envoi refusé, quand le compteur en est le seul refus, avec en
	 * `eEquipmentReadingNew` celui que la personne veut enregistrer. La page en fait une question
	 * (`EquipmentReadingUi::getConfirm()`). Un élément vide si un autre champ est refusé : celui-là se
	 * corrige d'abord, sous son champ, et le compteur est jugé à l'envoi suivant.
	 *
	 * Un refus du compteur porte l'élément refusé et sa valeur : c'est la forme de ceux que `build()`
	 * journalise (`framework/core/Data.php`), que `EquipmentLib::follow()` reprend.
	 */
	public static function getConflictFromFail(\FailWatch $fw): EquipmentReading {

		$fails = $fw->get();

		foreach($fails as [, $failName]) {

			if(in_array($failName, [
				'EquipmentReading::value.backwards',
				'EquipmentReading::value.ahead',
				'MaintenanceDone::value.backwards',
				'MaintenanceDone::value.ahead'
			]) === FALSE) {
				return new EquipmentReading();
			}

		}

		[$e, $value] = $fails[0][2];

		$eEquipmentReading = self::getConflict($e['equipment'], $e['date'], $value);

		if($eEquipmentReading->notEmpty()) {

			$eEquipmentReading['eEquipmentReadingNew'] = new EquipmentReading([
				'equipment' => $e['equipment'],
				'date' => $e['date'],
				'value' => $value
			]);

		}

		return $eEquipmentReading;

	}

	/**
	 * Ce que le compteur de `$eEquipment` affichait à `$date` : le dernier relevé de ce jour ou d'avant,
	 * ou un élément vide. C'est le compteur d'un entretien fait (`MaintenanceDoneLib::prepareValue()`) :
	 * un relevé d'après dirait une valeur que le matériel n'avait pas encore.
	 */
	public static function getByEquipmentAndDate(Equipment $eEquipment, string $date): EquipmentReading {

		return EquipmentReading::model()
			->select(['id', 'date', 'value'])
			->whereEquipment($eEquipment)
			->whereDate('<=', $date)
			->sort(['date' => SORT_DESC])
			->get();

	}

	/**
	 * Le dernier relevé de `$cEquipmentReading`, triés par date, ou un élément vide sans relevé.
	 */
	public static function getLast(\Collection $cEquipmentReading): EquipmentReading {

		return $cEquipmentReading->empty() ? new EquipmentReading() : $cEquipmentReading->last();

	}

	/**
	 * Ce que le compteur affichait à `$date`, lu dans les relevés `$cEquipmentReading` triés par date :
	 * le relevé du jour, ou la droite entre celui d'avant et celui d'après, un compteur tournant à peu
	 * près régulièrement entre deux relevés. `NULL` avant le premier relevé et après le dernier, où
	 * rien ne permet de le savoir.
	 */
	public static function getValueAt(\Collection $cEquipmentReading, string $date): ?float {

		$eEquipmentReadingBefore = new EquipmentReading();

		foreach($cEquipmentReading as $eEquipmentReading) {

			if($eEquipmentReading['date'] === $date) {
				return (float)$eEquipmentReading['value'];
			}

			if($eEquipmentReading['date'] > $date) {

				if($eEquipmentReadingBefore->empty()) {
					return NULL;
				}

				$days = (strtotime($eEquipmentReading['date']) - strtotime($eEquipmentReadingBefore['date'])) / 86400;
				$elapsed = (strtotime($date) - strtotime($eEquipmentReadingBefore['date'])) / 86400;

				return $eEquipmentReadingBefore['value'] + ($eEquipmentReading['value'] - $eEquipmentReadingBefore['value']) * $elapsed / $days;

			}

			$eEquipmentReadingBefore = $eEquipmentReading;

		}

		return NULL;

	}

	/**
	 * Le compteur tourné depuis le 1er janvier jusqu'au dernier relevé, puis sur la même période de
	 * l'année précédente, chacune `NULL` quand les relevés ne couvrent pas son début (un matériel
	 * entré au carnet en cours d'année ne sait pas ce qu'il affichait au 1er janvier).
	 *
	 * Un relevé confirmé sous un précédent (`isConfirmed()`) corrige une erreur, ou suit un compteur
	 * changé : la période qui le traverse rend une différence négative, qui ne dit pas ce que le matériel a
	 * tourné. Elle vaut alors `NULL`, comme une période que les relevés ne couvrent pas.
	 */
	public static function getYear(\Collection $cEquipmentReading): array {

		if($cEquipmentReading->empty()) {
			return [NULL, NULL];
		}

		$eEquipmentReadingLast = $cEquipmentReading->last();

		$year = \Clock::year();
		$sameDatePrevious = ($year - 1).substr($eEquipmentReadingLast['date'], 4);

		$start = self::getValueAt($cEquipmentReading, $year.'-01-01');
		$startPrevious = self::getValueAt($cEquipmentReading, ($year - 1).'-01-01');
		$endPrevious = self::getValueAt($cEquipmentReading, $sameDatePrevious);

		$valueYear = ($start === NULL or substr($eEquipmentReadingLast['date'], 0, 4) !== (string)$year) ? NULL : (int)round($eEquipmentReadingLast['value'] - $start);
		$valueYearPrevious = ($startPrevious === NULL or $endPrevious === NULL) ? NULL : (int)round($endPrevious - $startPrevious);

		return [
			($valueYear !== NULL and $valueYear < 0) ? NULL : $valueYear,
			($valueYearPrevious !== NULL and $valueYearPrevious < 0) ? NULL : $valueYearPrevious
		];

	}

}
?>
