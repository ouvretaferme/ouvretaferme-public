<?php
namespace toolbox;

/**
 * Ce que la Boite à outils dit dû au planning (`planning\DueLib`). Chaque méthode publique de cette
 * classe est un événement du registre des observateurs : rien d'autre ne s'y écrit en public.
 *
 * Première source, le carnet d'entretien : un entretien en retard au compteur est dû dès maintenant,
 * un entretien qui a une date l'est à cette date, qu'elle soit passée ou à venir. Un entretien dû au
 * compteur seul et pas encore en retard n'a pas de date, il n'entre que par son retard, comme sur le
 * planning d'avant (`MaintenanceLib::fill()`).
 *
 * Deuxième et troisième sources, les compteurs du carnet et ceux de l'eau (alertes lot 2) : un
 * compteur est dû au jour où la cloche le rappellerait (`NotificationLib::getReadingDate()`), et le
 * reste jusqu'à son relevé suivant. Ce jour ne dépend que du dernier relevé et du rythme de la
 * structure : chaque geste qui change l'un des deux recalcule.
 */
class PlanningObserverLib {

	const MAINTENANCE = 'toolbox-maintenance';
	const READING = 'toolbox-reading';
	const WATER_READING = 'toolbox-water-reading';

	public static function getDue(\structure\Structure $eStructure): \Collection {

		$cDue = new \Collection();

		$cEquipment = EquipmentLib::getByStructure($eStructure);

		foreach($cEquipment as $eEquipment) {

			foreach($eEquipment['cMaintenance'] as $eMaintenance) {

				// Un retard au compteur ne se date pas : le temps ne le fait ni naitre ni passer, seul un
				// relevé, et le relevé recalcule. Un retard à la date garde sa date, que la lecture
				// compare à l'horloge.
				if($eMaintenance['due'] === MaintenanceLib::OVERDUE and $eMaintenance['dueBy'] === 'value') {
					$dueDate = NULL;
				} else if($eMaintenance['plannedDate'] !== NULL) {
					$dueDate = $eMaintenance['plannedDate'];
				} else {
					continue;
				}

				$cDue[] = new \planning\Due([
					'type' => self::MAINTENANCE,
					'reference' => $eMaintenance['id'],
					'dueDate' => self::getPostponed($dueDate, $eMaintenance['postponedDate']),
				]);

			}

		}

		$cEquipmentMeter = $cEquipment->find(fn(Equipment $eEquipment) => $eEquipment->hasMeter());

		if($cEquipmentMeter->notEmpty()) {

			$readingEveryMonths = MaintenanceConfigurationLib::getReadingEveryMonths($eStructure);

			foreach($cEquipmentMeter as $eEquipment) {

				$eEquipmentReadingLast = $eEquipment['eEquipmentReadingLast'];

				$cDue[] = new \planning\Due([
					'type' => self::READING,
					'reference' => $eEquipment['id'],
					'dueDate' => self::getPostponed(NotificationLib::getReadingDate($readingEveryMonths, $eEquipmentReadingLast->empty() ? NULL : $eEquipmentReadingLast['date'], $eEquipment['createdAt'], $eEquipment['readingSkippedDate']), $eEquipment['readingPostponedDate']),
				]);

			}

		}

		$cWaterMeter = WaterMeterLib::getByStructure($eStructure);

		if($cWaterMeter->notEmpty()) {

			$readingEveryMonths = WaterConfigurationLib::getReadingEveryMonths($eStructure);
			$cWaterReadingLast = WaterReadingLib::getLastByMeters($cWaterMeter);

			foreach($cWaterMeter as $eWaterMeter) {

				$readingLast = $cWaterReadingLast->offsetExists($eWaterMeter['id']) ? $cWaterReadingLast[$eWaterMeter['id']]['date'] : NULL;

				$cDue[] = new \planning\Due([
					'type' => self::WATER_READING,
					'reference' => $eWaterMeter['id'],
					'dueDate' => self::getPostponed(NotificationLib::getReadingDate($readingEveryMonths, $readingLast, $eWaterMeter['createdAt'], $eWaterMeter['readingSkippedDate']), $eWaterMeter['readingPostponedDate']),
				]);

			}

		}

		return $cDue;

	}

	/**
	 * Ce qui est décalé à la semaine suivante (`MaintenanceLib::postpone()`, `EquipmentLib::postponeReading()`)
	 * est dû au lundi du décalage, sauf si la source le dit dû plus tard : un relevé fait ou une échéance
	 * qui repart après le décalage le rendent caduc. `NULL` vaut « dû maintenant ».
	 */
	private static function getPostponed(?string $dueDate, ?string $postponedDate): ?string {

		if($postponedDate === NULL or ($dueDate !== NULL and $dueDate >= $postponedDate)) {
			return $dueDate;
		}

		return $postponedDate;

	}

	/**
	 * Les structures qui ont au moins un entretien à échéance ou un compteur, pour la passe
	 * hebdomadaire. Celles dont le matériel n'est plus suivi en font partie : leur recalcul ne rend
	 * rien, ce qui est juste.
	 */
	public static function getDueStructures(): \Collection {

		$cStructure = new \Collection();

		$sources = [
			Maintenance::model()
				->whereStatus(Maintenance::ACTIVE)
				->where('plannedDate IS NOT NULL OR plannedValue IS NOT NULL')
				->group('structure')
				->getColumn('structure'),
			Equipment::model()
				->whereStatus(Equipment::ACTIVE)
				->where('meter IS NOT NULL')
				->group('structure')
				->getColumn('structure'),
			WaterMeter::model()
				->whereStatus(WaterMeter::ACTIVE)
				->group('structure')
				->getColumn('structure')
		];

		foreach($sources as $cStructureSource) {

			foreach($cStructureSource as $eStructure) {
				$cStructure[$eStructure['id']] = $eStructure;
			}

		}

		return $cStructure;

	}

	/**
	 * Ce qui est dû dans la semaine, chaque ligne accrochée à ce que son rendu lit, dans l'ordre du
	 * bloc « Rappels » (alertes lot 2, Q1 B) :
	 *
	 * - les entretiens (`eMaintenance`), dans l'ordre du planning d'avant : les retards dans l'ordre du
	 *   carnet, puis les dates de la semaine dans l'ordre des jours ;
	 * - les compteurs du carnet (`eEquipment`, son dernier relevé dans `eEquipmentReadingLast`), dans
	 *   l'ordre du carnet ;
	 * - les compteurs d'eau (`eWaterMeter`, son dernier relevé dans `eWaterReadingLast`), dans l'ordre
	 *   de leurs onglets.
	 */
	public static function describeDue(\structure\Structure $eStructure, \Collection $cDue): \Collection {

		$dues = [];

		foreach($cDue as $eDue) {
			$dues[$eDue['type']][$eDue['reference']] = $eDue;
		}

		$cDueOverdue = new \Collection();
		$cDueDated = new \Collection();
		$cDueReading = new \Collection();

		foreach(EquipmentLib::getByStructure($eStructure) as $eEquipment) {

			if(isset($dues[self::READING][$eEquipment['id']])) {

				$eDue = $dues[self::READING][$eEquipment['id']];
				$eDue['eEquipment'] = $eEquipment;

				$cDueReading[] = $eDue;

			}

			foreach($eEquipment['cMaintenance'] as $eMaintenance) {

				if(isset($dues[self::MAINTENANCE][$eMaintenance['id']]) === FALSE) {
					continue;
				}

				$eDue = $dues[self::MAINTENANCE][$eMaintenance['id']];
				$eDue['eMaintenance'] = $eMaintenance;

				if($eMaintenance['due'] === MaintenanceLib::OVERDUE) {
					$cDueOverdue[] = $eDue;
				} else {
					$cDueDated[] = $eDue;
				}

			}

		}

		$cDueDated->sort(fn(\planning\Due $eDue1, \planning\Due $eDue2) => strcmp($eDue1['eMaintenance']['plannedDate'], $eDue2['eMaintenance']['plannedDate']));

		$cDueWater = new \Collection();

		if(isset($dues[self::WATER_READING])) {

			$cWaterMeter = WaterMeterLib::getByStructure($eStructure);
			$cWaterReadingLast = WaterReadingLib::getLastByMeters($cWaterMeter);

			foreach($cWaterMeter as $eWaterMeter) {

				if(isset($dues[self::WATER_READING][$eWaterMeter['id']]) === FALSE) {
					continue;
				}

				$eWaterMeter['eWaterReadingLast'] = $cWaterReadingLast->offsetExists($eWaterMeter['id']) ? $cWaterReadingLast[$eWaterMeter['id']] : new WaterReading();

				$eDue = $dues[self::WATER_READING][$eWaterMeter['id']];
				$eDue['eWaterMeter'] = $eWaterMeter;

				$cDueWater[] = $eDue;

			}

		}

		$cDueDescribed = new \Collection();

		foreach([$cDueOverdue, $cDueDated, $cDueReading, $cDueWater] as $cDueGroup) {

			foreach($cDueGroup as $eDue) {
				$cDueDescribed[] = $eDue;
			}

		}

		return $cDueDescribed;

	}

}
?>
