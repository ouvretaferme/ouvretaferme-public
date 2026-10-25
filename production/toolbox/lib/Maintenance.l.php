<?php
namespace toolbox;

/**
 * Les entretiens planifiés sur un matériel, et leur échéance.
 *
 * L'échéance est « Prochaine fois » (`plannedDate`, `plannedValue`), que la personne pose en
 * planifiant (arbitrage Q2 de la planche pf-t4-v4), et qu'un entretien fait ou un retard ignoré font
 * repartir sur un entretien régulier (`plan()`). Au compteur, en heures ou en kilomètres, elle se
 * compare au dernier relevé ; à la date, au jour. Un entretien dû aux deux l'est au premier des deux.
 */
class MaintenanceLib extends MaintenanceCrud {

	const OVERDUE = 'overdue';
	const SOON = 'soon';

	// « Bientôt » se dit à 50 heures, 1 000 kilomètres ou 60 jours de l'échéance (cadrage du lot T4,
	// les kilomètres posés le 30/09 avec le compteur kilométrique) : assez tôt pour commander une pièce
	// ou prendre rendez-vous au garage.
	const SOON_VALUE = [
		Equipment::HOURS => 50,
		Equipment::KILOMETERS => 1000,
	];
	const SOON_DAYS = 60;

	// Le compteur de « Prochaine fois » passe avant sa date, dont l'étape `planned` le lit.
	//
	// L'écran pose le matériel depuis le lien qui ouvre le panneau (`page/maintenance.p.php`) ; l'API n'a
	// pas de lien, elle le construit donc, et en premier : les étapes `meter` lisent son compteur.
	public static function getPropertiesCreate(?string $origin = NULL): array {

		$properties = ['name', 'everyValue', 'everyMonths', 'plannedValue', 'plannedDate'];

		if($origin === 'api') {
			array_unshift($properties, 'equipment');
		}

		return $properties;

	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'everyValue', 'everyMonths', 'plannedValue', 'plannedDate', 'status'];
	}

	/**
	 * Pose sur chaque entretien de `$eEquipment` sa dernière fois, son échéance et son état, lus dans
	 * les entretiens faits et le dernier relevé que le matériel porte (`EquipmentLib::getByStructure()`) :
	 *
	 * - `equipment` : `$eEquipment` ;
	 * - `done` : le dernier entretien fait, ou un élément vide tant qu'aucun n'est noté ;
	 * - `nextValue`, `remainingValue` : l'échéance au compteur, `NULL` sans compteur, sans « Prochaine
	 *   fois » au compteur ou sans relevé à lui comparer ;
	 * - `nextDate`, `remainingDays` : l'échéance à la date, `NULL` sans « Prochaine fois » à une date ;
	 * - `due` : `OVERDUE`, `SOON` ou `NULL`, le pire des deux ;
	 * - `dueBy` : `value` ou `date`, celle des deux échéances qui décide, le compteur à égalité, `NULL`
	 *   quand il n'y en a aucune (un entretien au compteur sur un matériel dont le compteur est coupé).
	 */
	public static function fill(Equipment $eEquipment): void {

		$eEquipment->expects(['meter', 'eEquipmentReadingLast', 'cMaintenance', 'cMaintenanceDone']);

		$cMaintenanceDone = $eEquipment['cMaintenanceDone'];

		foreach($eEquipment['cMaintenance'] as $eMaintenance) {

			// L'entretien porte son matériel entier : ses valeurs se disent dans l'unité du compteur, et
			// le bandeau « Entretiens à faire » nomme le matériel (`EquipmentLib::getDue()`).
			$eMaintenance['equipment'] = $eEquipment;

			// L'historique est trié du plus récent au plus ancien : le premier entretien fait de cet
			// entretien est le dernier en date.
			$eMaintenance['done'] = $cMaintenanceDone->find(fn($eMaintenanceDone) => $eMaintenanceDone['maintenance']->is($eMaintenance), limit: 1, default: new MaintenanceDone());

			if(
				$eEquipment->hasMeter() and
				$eMaintenance['plannedValue'] !== NULL and
				$eEquipment['eEquipmentReadingLast']->notEmpty()
			) {
				$eMaintenance['nextValue'] = $eMaintenance['plannedValue'];
				$eMaintenance['remainingValue'] = $eMaintenance['plannedValue'] - $eEquipment['eEquipmentReadingLast']['value'];
			} else {
				$eMaintenance['nextValue'] = NULL;
				$eMaintenance['remainingValue'] = NULL;
			}

			if($eMaintenance['plannedDate'] !== NULL) {
				$eMaintenance['nextDate'] = $eMaintenance['plannedDate'];
				$eMaintenance['remainingDays'] = (int)round((strtotime($eMaintenance['plannedDate']) - strtotime(\Clock::date())) / 86400);
			} else {
				$eMaintenance['nextDate'] = NULL;
				$eMaintenance['remainingDays'] = NULL;
			}

			$dueValue = ($eMaintenance['remainingValue'] === NULL) ? NULL : self::getDue($eMaintenance['remainingValue'], self::SOON_VALUE[$eEquipment['meter']]);
			$dueDate = self::getDue($eMaintenance['remainingDays'], self::SOON_DAYS);

			$eMaintenance['due'] = (self::rank($dueValue) >= self::rank($dueDate)) ? $dueValue : $dueDate;

			// Un entretien dû seulement au compteur n'a plus d'échéance quand le compteur est coupé : il la
			// retrouve quand le compteur revient (`Equipment::properties()`, « readings »).
			if($eMaintenance['remainingValue'] === NULL and $eMaintenance['remainingDays'] === NULL) {
				$eMaintenance['dueBy'] = NULL;
			} else if($eMaintenance['remainingValue'] === NULL) {
				$eMaintenance['dueBy'] = 'date';
			} else if($eMaintenance['remainingDays'] === NULL) {
				$eMaintenance['dueBy'] = 'value';
			} else {
				$eMaintenance['dueBy'] = (self::rank($dueDate) > self::rank($dueValue)) ? 'date' : 'value';
			}

		}

	}

	/**
	 * « Prochaine fois » d'un entretien régulier qui repart du jour `$date` et du compteur `$value` : la
	 * fréquence au compteur ajoutée au compteur, et la fréquence en mois à la date. Un entretien
	 * « Une seule fois » n'a pas de prochaine fois.
	 */
	public static function getPlanned(Maintenance $eMaintenance, string $date, ?int $value): array {

		$eMaintenance->expects(['everyValue', 'everyMonths', 'equipment' => ['meter']]);

		return [
			($eMaintenance['equipment']->hasMeter() and $eMaintenance['everyValue'] !== NULL and $value !== NULL) ? $value + $eMaintenance['everyValue'] : NULL,
			($eMaintenance['everyMonths'] !== NULL and \util\DateLib::isValid($date)) ? self::addMonths($date, $eMaintenance['everyMonths']) : NULL
		];

	}

	/**
	 * Toute échéance qui change se redit au planning (`planning\DueLib::refresh()`) : la création, la
	 * modification et la suppression, qui passe le statut, comme le départ d'une échéance après un
	 * entretien fait, un retard ignoré ou un premier relevé, qui écrivent tous par `update()`.
	 */
	public static function create(Maintenance $e): void {

		Maintenance::model()->beginTransaction();

			parent::create($e);

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		Maintenance::model()->commit();

	}

	/**
	 * Une échéance changée (`plannedDate`, `plannedValue`) efface le décalage, comme celle qui repart (`plan()`) :
	 * l'échéance au planning étant la plus tardive des deux, une échéance avancée resterait masquée. Le
	 * formulaire d'édition poste toujours les deux : seule une valeur qui diffère de la base compte, et une
	 * édition du seul nom garde le décalage.
	 */
	public static function update(Maintenance $e, array $properties): void {

		$e->expects(['structure']);

		Maintenance::model()->beginTransaction();

			if(
				in_array('postponedDate', $properties) === FALSE and
				array_intersect(['plannedDate', 'plannedValue'], $properties) !== []
			) {

				$eMaintenanceStored = Maintenance::model()
					->select(['plannedDate', 'plannedValue'])
					->whereId($e)
					->get();

				foreach(array_intersect(['plannedDate', 'plannedValue'], $properties) as $property) {

					if($eMaintenanceStored[$property] !== $e[$property]) {
						$e['postponedDate'] = NULL;
						$properties[] = 'postponedDate';
						break;
					}

				}

			}

			parent::update($e, $properties);

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		Maintenance::model()->commit();

	}

	/**
	 * Fait repartir « Prochaine fois » d'un entretien régulier du jour `$date` et du compteur `$value`.
	 * Quand le compteur n'est pas connu ce jour-là, l'échéance au compteur ne peut pas repartir, et
	 * l'entretien ne reste dû qu'à la date s'il a une fréquence en mois : garder l'ancienne dirait un
	 * retard déjà rattrapé.
	 *
	 * Sur un matériel dont le compteur est coupé, l'échéance au compteur n'est ni lue ni due (`fill()`) :
	 * elle reste telle quelle, et revient avec le compteur. L'effacer ici la perdrait pour de bon.
	 */
	public static function plan(Maintenance $eMaintenance, string $date, ?int $value): void {

		$eMaintenance->expects(['equipment' => ['meter']]);

		[$plannedValue, $eMaintenance['plannedDate']] = self::getPlanned($eMaintenance, $date, $value);

		// Une échéance qui repart n'est plus celle qu'on avait décalée (`postpone()`).
		$eMaintenance['postponedDate'] = NULL;

		$properties = ['plannedDate', 'postponedDate'];

		if($eMaintenance['equipment']->hasMeter()) {
			$eMaintenance['plannedValue'] = $plannedValue;
			$properties[] = 'plannedValue';
		}

		self::update($eMaintenance, $properties);

	}

	/**
	 * Le premier relevé d'un matériel, `$value`, fait partir de lui les entretiens réguliers au compteur
	 * qu'il mettrait en retard, ceux dont « Prochaine fois » est à ce relevé ou en dessous : une vidange
	 * toutes les 400 h sur un matériel relevé pour la première fois à 500 h est due à 900 h, au lieu
	 * d'être en retard dès la première saisie (retour du 02/10/2026).
	 *
	 * Une « Prochaine fois » posée au-dessus du relevé est un choix, elle reste. Un entretien « Une seule
	 * fois » n'a pas d'intervalle d'où repartir. La date d'un entretien dû aussi à une date ne bouge pas,
	 * et rien n'entre à l'historique : ce n'est ni un entretien fait, ni un retard ignoré.
	 */
	public static function planFromFirstReading(Equipment $eEquipment, int $value): void {

		$cMaintenance = Maintenance::model()
			->select(['id', 'structure', 'everyValue'])
			->whereEquipment($eEquipment)
			->whereStatus(Maintenance::ACTIVE)
			->whereEveryValue('!=', NULL)
			->wherePlannedValue('<=', $value)
			->getCollection();

		foreach($cMaintenance as $eMaintenance) {

			$eMaintenance['plannedValue'] = $value + $eMaintenance['everyValue'];

			self::update($eMaintenance, ['plannedValue']);

		}

	}

	/**
	 * « Ignorer ce retard » (retour du 30/09, « enlever l'alerte pour ce tour ») : l'échéance
	 * repart d'aujourd'hui et du compteur du jour, comme après un entretien fait, mais rien n'entre dans
	 * l'historique. L'alerte revient quand la nouvelle échéance est dépassée à son tour. `$e` sort de
	 * `fill()`, qui a lu le compteur du jour.
	 */
	public static function ignore(Maintenance $e): void {

		$e->expects(['equipment' => ['meter', 'eEquipmentReadingLast']]);

		$value = ($e['equipment']->hasMeter() and $e['equipment']['eEquipmentReadingLast']->notEmpty()) ? $e['equipment']['eEquipmentReadingLast']['value'] : NULL;

		Maintenance::model()->beginTransaction();

			$e['ignoredDate'] = \Clock::date();

			self::update($e, ['ignoredDate']);
			self::plan($e, \Clock::date(), $value);

		Maintenance::model()->commit();

	}

	/**
	 * Le décalage à la semaine suivante de celle où le planning le montre
	 * (`planning\DueLib::getPostponedDate()`, retour 15 du 04/10) : l'entretien reparaît au
	 * planning le lundi d'après (`PlanningObserverLib::getDue()`), jusqu'à ce que son échéance reparte
	 * (`plan()`). Son échéance au carnet ne change pas.
	 */
	public static function postpone(Maintenance $e): void {

		// Sans `build()`, validé le 04/10 : rien de posté ne devient la propriété, la date se
		// calcule ici depuis la ligne du planning, comme celle d'`ignore()`.
		$e['postponedDate'] = \planning\DueLib::getPostponedDate($e['structure'], PlanningObserverLib::MAINTENANCE, $e['id']);

		self::update($e, ['postponedDate']);

	}

	/**
	 * Les entretiens que le point du lundi peut annoncer, sur tout le parc (`NotificationLib::emitMaintenances()`) :
	 * ceux d'un matériel suivi dont la date tombe d'ici `$until`, retard compris, et ceux qui portent
	 * une échéance au compteur d'un matériel à compteur. La liste est large à dessein : seul le
	 * dernier relevé dit si un compteur est dépassé, et `NotificationLib::describe()` le relit
	 * structure par structure, avec le calcul de l'écran (`fill()`).
	 *
	 * Une lecture par semaine de toute la table, sans index sur l'échéance (même arbitrage que les
	 * factures échues, `selling\NotificationLib::getInvoicesDue()`).
	 */
	public static function getDueUntil(string $until): \Collection {

		return Maintenance::model()
			->select(['id', 'structure'])
			->join(Equipment::model(), 'm1.equipment = m2.id AND m2.status = '.Equipment::model()->format(Equipment::ACTIVE))
			->where('m1.status', Maintenance::ACTIVE)
			->where('m1.plannedDate <= '.Maintenance::model()->format($until).' OR (m1.plannedValue IS NOT NULL AND m2.meter IS NOT NULL)')
			->getCollection();

	}

	protected static function getDue(?int $remaining, int $soon): ?string {

		if($remaining === NULL) {
			return NULL;
		} else if($remaining < 0) {
			return self::OVERDUE;
		} else if($remaining <= $soon) {
			return self::SOON;
		} else {
			return NULL;
		}

	}

	protected static function rank(?string $due): int {

		return match($due) {
			self::OVERDUE => 2,
			self::SOON => 1,
			NULL => 0
		};

	}

	/**
	 * `$date` plus `$months` mois, ramené au dernier jour du mois d'arrivée quand il est plus court :
	 * une vidange du 31 janvier revient le 28 février, et non le 3 mars comme le calcule PHP.
	 */
	public static function addMonths(string $date, int $months): string {

		[$year, $month, $day] = array_map('intval', explode('-', $date));

		$month += $months;
		$year += intdiv($month - 1, 12);
		$month = ($month - 1) % 12 + 1;

		$day = min($day, (int)date('t', mktime(0, 0, 0, $month, 1, $year)));

		return sprintf('%04d-%02d-%02d', $year, $month, $day);

	}

}
?>
