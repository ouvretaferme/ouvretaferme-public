<?php
namespace toolbox;

/**
 * Ce que la Boite à outils apporte au centre de notifications, hors affichage : le point du lundi sur
 * les entretiens du matériel, et le rappel des compteurs qu'on ne relève plus, au rythme que la
 * ferme règle dans son carnet (planche production-finir-carnet-notifications, arbitrée
 * le 01/10/2026 ; le rythme, d'abord trimestriel pour tous, se règle depuis le 02/10/2026), et le
 * même rappel pour les compteurs d'eau, au rythme que la structure règle dans sa consommation d'eau
 * (03/10/2026).
 *
 * Le contrat de la paire `NotificationLib` + `NotificationUi` est écrit dans
 * `base/notification/CLAUDE.md` et tenu par `base/notification/test/NotificationTypeSentinel.t.php`.
 * Le nom de cette classe est celui que la convention résout depuis le type (`toolbox-maintenance` →
 * `toolbox`).
 */
class NotificationLib {

	/**
	 * Un entretien en retard, ou à faire dans la semaine, `reference` portant l'entretien. Groupé par
	 * jour (`group` à 0) : le lundi, le moteur réunit les entretiens de la structure en une ligne qui
	 * se déroule, comme les factures échues d'une journée (Q1 C, « avec la possibilité de dérouler
	 * comme les ventes qd elles sont groupées »).
	 */
	const string MAINTENANCE = 'toolbox-maintenance';

	/**
	 * Un matériel à compteur qui n'a pas été relevé depuis `getReadingMonths()` mois, `reference` portant
	 * le matériel suivi. Groupé par jour, pour la même ligne à dérouler (Q2 B, « Une seule notif pour
	 * tous les matériels qui ont un compteur (avec la liste à dérouler) »).
	 */
	const string READING = 'toolbox-reading';

	/**
	 * Un compteur d'eau qui n'a pas été relevé depuis `getReadingMonths()` mois, `reference` portant le
	 * compteur : le rappel des compteurs du carnet, appliqué à la consommation d'eau avec son propre
	 * rythme (demande du 03/10/2026, « exactement comme le carnet d'entretien du matériel »).
	 */
	const string WATER_READING = 'toolbox-water-reading';

	/**
	 * Le point du lundi regarde jusqu'au dimanche. « À faire » ne retient que les échéances à une
	 * date (« pour "à faire" on ne met que ceux sur périodicité temporelle ») : un compteur n'a pas de
	 * semaine prévisible, et un entretien au compteur n'entre que par son retard.
	 */
	const int MAINTENANCE_DAYS = 6;

	/**
	 * Le jour du mois où part le rappel des compteurs, celui de `page/cron/reading.p.php`, et celui où
	 * un compteur entre au planning (`getReadingDate()`).
	 */
	const int READING_DAY = 5;

	/**
	 * Les types que ce package émet, et ce que chacun suppose (le détail des clés est dans
	 * `\notification\NotificationLib::getTypes()`).
	 *
	 * Reçus par les membres qui ont le droit du planning (Q3 A), celui qui consigne un entretien ou un
	 * relevé (`Structure::canPlanning()`, `CLAUDE.md` du package). `once` à FALSE : les deux reviennent,
	 * chaque lundi tant qu'un entretien reste à faire, à chaque rappel tant qu'un compteur dort.
	 */
	public static function getTypes(): array {

		$declaration = [
			'right' => 'planning',
			'rightValues' => [\structure\Role::FULL],
			'feature' => 'production',
			'family' => \notification\NotificationSetting::PRODUCTION,
			'values' => [\notification\NotificationSetting::OFF, \notification\NotificationSetting::ON],
			'default' => \notification\NotificationSetting::ON,
			'once' => FALSE
		];

		return [
			self::MAINTENANCE => $declaration,
			self::READING => $declaration,
			self::WATER_READING => $declaration
		];

	}

	/**
	 * Met en file les entretiens que le point du lundi peut annoncer. La liste est large
	 * (`MaintenanceLib::getDueUntil()`), `describeMaintenance()` tranche au traitement.
	 */
	public static function emitMaintenances(): void {

		foreach(MaintenanceLib::getDueUntil(self::getUntil()) as $eMaintenance) {
			\notification\NotificationLib::emit($eMaintenance['structure'], self::MAINTENANCE, $eMaintenance['id']);
		}

	}

	public static function emitReadings(): void {

		foreach(self::getReadings() as $eEquipment) {
			\notification\NotificationLib::emit($eEquipment['structure'], self::READING, $eEquipment['id']);
		}

	}

	public static function emitWaterReadings(): void {

		foreach(self::getWaterReadings() as $eWaterMeter) {
			\notification\NotificationLib::emit($eWaterMeter['structure'], self::WATER_READING, $eWaterMeter['id']);
		}

	}

	/**
	 * Les matériels dont le compteur se rappelle ce mois-ci, sur tout le parc. Le cron passe le 5 de
	 * chaque mois, et c'est le rythme de chaque ferme qui dit si ce mois est le sien
	 * (`isReadingMonth()`).
	 */
	public static function getReadings(): array {

		return self::getDue(
			MaintenanceConfigurationLib::getReadingEveryMonthsByStructure(),
			MaintenanceConfigurationLib::getReadingEveryMonthsDefault(),
			EquipmentLib::getUnreadSince(...)
		);

	}

	/**
	 * Les compteurs d'eau qui se rappellent ce mois-ci, sur tout le parc, au rythme que chaque
	 * structure règle pour sa consommation d'eau.
	 */
	public static function getWaterReadings(): array {

		return self::getDue(
			WaterConfigurationLib::getReadingEveryMonthsByStructure(),
			WaterConfigurationLib::getReadingEveryMonthsDefault(),
			WaterMeterLib::getUnreadSince(...)
		);

	}

	/**
	 * Ce que `$getUnreadSince` rend à relever ce mois-ci, chaque élément au rythme de sa structure
	 * (`$readingEveryMonthsByStructure`, `$readingEveryMonthsDefault` sans réglage).
	 *
	 * Le parc se lit une fois par seuil (`getReadingMonths()`), et pour les seuls seuils des rythmes
	 * qui tombent ce mois : une seule lecture les mois où seul le rappel mensuel part. Un élément
	 * n'est retenu que dans la lecture qui porte le seuil de sa structure.
	 */
	private static function getDue(array $readingEveryMonthsByStructure, int $readingEveryMonthsDefault, \Closure $getUnreadSince): array {

		$month = (int)\Clock::format('n');

		$readingEveryMonthsDue = array_filter(
			MaintenanceConfiguration::READING_EVERY_MONTHS,
			fn(int $readingEveryMonths) => self::isReadingMonth($readingEveryMonths, $month)
		);

		$elements = [];

		foreach(array_unique(array_map(self::getReadingMonths(...), $readingEveryMonthsDue)) as $months) {

			foreach($getUnreadSince(self::getSince($months)) as $e) {

				// Une structure sans réglage n'a pas de ligne : elle suit le défaut du schéma.
				$readingEveryMonths = $readingEveryMonthsByStructure[$e['structure']['id']] ?? $readingEveryMonthsDefault;

				if(
					in_array($readingEveryMonths, $readingEveryMonthsDue, TRUE) and
					self::getReadingMonths($readingEveryMonths) === $months
				) {
					$elements[] = $e;
				}

			}

		}

		return $elements;

	}

	/**
	 * Le rappel d'une ferme part-il ce mois ? Les rythmes se comptent depuis janvier : tous les
	 * 3 mois donne janvier, avril, juillet et octobre, tous les 6 mois janvier et juillet.
	 */
	public static function isReadingMonth(int $readingEveryMonths, int $month): bool {
		return (($month - 1) % $readingEveryMonths === 0);
	}

	/**
	 * Depuis combien de mois un compteur doit être resté sans relevé pour se rappeler : deux
	 * (« qui n'ont pas été relevés sur les 2 mois précédents »), un seul quand le rappel est mensuel :
	 * à deux mois, un compteur relevé à la suite d'un rappel sauterait toujours le suivant.
	 */
	public static function getReadingMonths(int $readingEveryMonths): int {

		return match($readingEveryMonths) {
			1 => 1,
			2, 3, 6 => 2
		};

	}

	/** Le dernier jour que le point du lundi regarde. */
	public static function getUntil(): string {
		return date('Y-m-d', strtotime(\Clock::date().' + '.self::MAINTENANCE_DAYS.' days'));
	}

	/** Le jour depuis lequel un compteur doit avoir été relevé pour ne pas être rappelé. */
	public static function getSince(int $months): string {
		return date('Y-m-d', strtotime(\Clock::date().' - '.$months.' months'));
	}

	/**
	 * Le jour où un compteur entre au planning (alertes lot 2, Q2 B) : le premier jour de rappel de la
	 * cloche, le 5 d'un mois de relevé (`page/cron/reading.p.php`), où le cron le nommerait.
	 *
	 * Relevé le `$readingLast`, il ne l'est plus au rappel qui tombe plus de `getReadingMonths()` mois
	 * après, la borne de `getSince()` vue depuis ce jour-là. Jamais relevé, il l'est dès le premier
	 * rappel qui suit son entrée au carnet (`$createdAt`). Le jour ne dépend donc que du dernier
	 * relevé : il ne bouge qu'avec lui, et la ligne du planning reste jusqu'au relevé suivant.
	 *
	 * Un relevé passé par la croix du planning (`$readingSkipped`, demande du 03/10) repousse
	 * le jour au rappel qui suit la croix. Un relevé fait ensuite repart plus loin que la croix, qui ne
	 * compte alors plus : elle n'a pas à être effacée.
	 */
	public static function getReadingDate(int $readingEveryMonths, ?string $readingLast, string $createdAt, ?string $readingSkipped = NULL): string {

		if($readingLast === NULL) {
			$after = substr($createdAt, 0, 10);
		} else {
			$after = date('Y-m-d', strtotime($readingLast.' + '.self::getReadingMonths($readingEveryMonths).' months'));
		}

		if($readingSkipped !== NULL and $readingSkipped > $after) {
			$after = $readingSkipped;
		}

		$date = substr($after, 0, 8).sprintf('%02d', self::READING_DAY);

		while(
			$date <= $after or
			self::isReadingMonth($readingEveryMonths, (int)substr($date, 5, 2)) === FALSE
		) {
			$date = date('Y-m-d', strtotime($date.' + 1 month'));
		}

		return $date;

	}

	/**
	 * Relit l'objet au moment du traitement.
	 *
	 * ⚠️ Bornée par la structure partout : le carnet vit dans la base commune, où un identifiant seul
	 * désigne l'objet de n'importe quel tenant.
	 */
	public static function describe(string $type, \structure\Structure $eStructure, ?int $reference): \notification\Notification {

		return match($type) {
			self::MAINTENANCE => self::describeMaintenance($eStructure, $reference),
			self::READING => self::describeReading($eStructure, $reference),
			self::WATER_READING => self::describeWaterReading($eStructure, $reference)
		};

	}

	/**
	 * L'entretien, relu avec le carnet de sa structure : c'est le calcul de l'écran
	 * (`MaintenanceLib::fill()`) qui dit le retard, pour que la notification et le carnet ne se
	 * contredisent jamais. Rien à dire d'un entretien fait, retiré ou replanifié depuis le cron, ni
	 * d'un entretien au compteur que le dernier relevé n'a pas dépassé.
	 */
	private static function describeMaintenance(\structure\Structure $eStructure, ?int $reference): \notification\Notification {

		foreach(EquipmentLib::getByStructure($eStructure) as $eEquipment) {

			if($eEquipment['cMaintenance']->offsetExists($reference) === FALSE) {
				continue;
			}

			$eMaintenance = $eEquipment['cMaintenance'][$reference];

			$late = ($eMaintenance['due'] === MaintenanceLib::OVERDUE);

			if(
				$late === FALSE and
				($eMaintenance['remainingDays'] === NULL or $eMaintenance['remainingDays'] > self::MAINTENANCE_DAYS)
			) {
				return new \notification\Notification();
			}

			// Tout ce que la ligne dira pendant trente jours : des valeurs et des dates absolues, l'écran
			// ne relisant rien.
			return new \notification\Notification([
				'group' => 0,
				'content' => [
					'maintenance' => $eMaintenance['name'],
					'equipment' => $eEquipment['tool']['name'],
					'equipmentId' => $eEquipment['id'],
					'meter' => $eEquipment['meter'],
					'late' => $late,
					'by' => $late ? $eMaintenance['dueBy'] : 'date',
					'plannedDate' => $eMaintenance['nextDate'],
					'plannedValue' => $eMaintenance['nextValue'],
					'readingValue' => $eEquipment['eEquipmentReadingLast']->empty() ? NULL : $eEquipment['eEquipmentReadingLast']['value'],
					'until' => self::getUntil()
				]
			]);

		}

		return new \notification\Notification();

	}

	/**
	 * Le matériel, relu seulement s'il est toujours suivi, toujours à compteur et toujours sans relevé
	 * récent, au seuil du rythme de sa ferme : un relevé saisi entre le cron et le traitement retire
	 * la raison du rappel.
	 */
	private static function describeReading(\structure\Structure $eStructure, ?int $reference): \notification\Notification {

		$months = self::getReadingMonths(MaintenanceConfigurationLib::getReadingEveryMonths($eStructure));

		$cEquipment = EquipmentLib::getUnreadSince(self::getSince($months), $eStructure);

		if($cEquipment->offsetExists($reference) === FALSE) {
			return new \notification\Notification();
		}

		$eEquipment = $cEquipment[$reference];
		$eEquipmentReading = EquipmentReadingLib::getByEquipmentAndDate($eEquipment, \Clock::date());

		return new \notification\Notification([
			'group' => 0,
			'content' => [
				'equipment' => $eEquipment['tool']['name'],
				'lastDate' => $eEquipmentReading->empty() ? NULL : $eEquipmentReading['date']
			]
		]);

	}

	/**
	 * Le compteur d'eau, relu seulement s'il est toujours actif et toujours sans relevé récent, au
	 * seuil du rythme de sa structure, comme un compteur du carnet (`describeReading()`).
	 */
	private static function describeWaterReading(\structure\Structure $eStructure, ?int $reference): \notification\Notification {

		$months = self::getReadingMonths(WaterConfigurationLib::getReadingEveryMonths($eStructure));

		$cWaterMeter = WaterMeterLib::getUnreadSince(self::getSince($months), $eStructure);

		if($cWaterMeter->offsetExists($reference) === FALSE) {
			return new \notification\Notification();
		}

		$eWaterMeter = $cWaterMeter[$reference];
		$eWaterReading = WaterReadingLib::getByMeterAndDate($eWaterMeter, \Clock::date());

		return new \notification\Notification([
			'group' => 0,
			'content' => [
				'meter' => $eWaterMeter['name'],
				'lastDate' => $eWaterReading->empty() ? NULL : $eWaterReading['date']
			]
		]);

	}

	public static function getAmount(\notification\Notification $eNotification): ?float {

		return NULL;

	}

	/** Le groupe est la journée seule : il ne désigne rien de plus. */
	public static function getGroupName(\notification\Notification $eNotification): ?string {

		return NULL;

	}

	/**
	 * Où mène un clic : le carnet, ouvert sur le matériel que la ligne nomme, ou la consommation d'eau,
	 * ouverte sur l'onglet du compteur.
	 */
	public static function getUrl(\notification\Notification $eNotification): string {

		if($eNotification['type'] === self::WATER_READING) {
			return WaterReadingUi::urlMeter($eNotification['eStructure'], new WaterMeter(['id' => $eNotification['reference']]));
		}

		$equipmentId = match($eNotification['type']) {
			self::MAINTENANCE => $eNotification['content']['equipmentId'],
			self::READING => $eNotification['reference']
		};

		return EquipmentUi::urlEquipment($eNotification['eStructure'], new Equipment(['id' => $equipmentId]));

	}

}
?>
