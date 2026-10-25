<?php
namespace toolbox;

/**
 * Les matériels que suit le carnet du matériel (planche pf-t4-v2), et ce que l'écran en lit.
 *
 * Un matériel de « Mon matériel » (`work\Tool`) entre au carnet par « Suivre un matériel », et en
 * sort par « Ne plus suivre », qui garde ses relevés et son historique : le suivre de nouveau
 * reprend la même ligne (`follow()`).
 */
class EquipmentLib extends EquipmentCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['tool', 'meter', 'registration'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['meter', 'registration', 'status'];
	}

	/**
	 * Suivre un matériel sans premier relevé : c'est par ce nom que l'API crée une ressource
	 * (`\ModuleApi::create()`), et un suivi ne se crée jamais autrement que par `follow()`, qui réveille
	 * la ligne d'un matériel qu'on ne suivait plus au lieu d'en écrire une seconde.
	 */
	public static function create(Equipment $e): void {
		self::follow($e, new EquipmentReading());
	}

	/**
	 * Met un matériel au carnet, avec son premier relevé quand il porte un compteur et que la personne
	 * l'a donné (`$eEquipmentReading` vide sinon). Un matériel validé par son nom dans l'autocomplétion
	 * (`eToolNew`, `Equipment::properties()`) entre dans « Mon matériel » au même geste, et un matériel
	 * qu'on ne suivait plus se réveille avec son historique, l'unicité sur `tool` n'en admettant qu'un.
	 *
	 * Le premier matériel suivi fait du carnet un outil de la structure (`Feature.hasToolbox`), qui le
	 * reste quand on ne suit plus rien, comme la pluviométrie (`GaugeLib::create()`).
	 *
	 * `$confirmed` : la personne a confirmé un premier relevé sous le dernier de l'historique
	 * (`EquipmentReadingLib::isConfirmed()`).
	 *
	 * `$e['awakened']` dit au retour si le matériel s'est réveillé : l'API rend alors 200 et non 201,
	 * aucune ligne n'étant née.
	 */
	public static function follow(Equipment $e, EquipmentReading $eEquipmentReading, bool $confirmed = FALSE): void {

		$e->expects(['structure', 'tool', 'meter', 'eToolNew']);

		// Un matériel neuf de « Mon matériel » n'a jamais été suivi, il n'a rien à réveiller.
		if($e['eToolNew']->empty()) {

			$eEquipmentAsleep = Equipment::model()
				->select('id')
				->whereTool($e['tool'])
				->whereStatus(Equipment::INACTIVE)
				->get();

		} else {
			$eEquipmentAsleep = new Equipment();
		}

		// Un matériel qu'on suit de nouveau retrouve ses valeurs de compteur : il garde leur unité, et le premier de
		// son retour ne passe pas sous le dernier, ce que `Equipment::meter` et `EquipmentReading::value`
		// ne pouvaient pas vérifier avant de savoir quel matériel se réveille. La question se pose avant la transaction, qu'un refus n'a donc
		// pas à annuler (`framework/dev/test/RollBackSentinel.t.php`).
		if(
			$e->hasMeter() and
			$eEquipmentAsleep->notEmpty()
		) {

			$locked = self::getLockedMeter($eEquipmentAsleep);

			if($locked !== NULL and $locked !== $e['meter']) {
				Equipment::fail('meter.locked');
				return;
			}

			if(
				$eEquipmentReading->notEmpty() and
				$confirmed === FALSE and
				EquipmentReadingLib::acceptBefore($eEquipmentAsleep, $eEquipmentReading['date'], $eEquipmentReading['value']) === FALSE
			) {

				// Le refus porte le relevé et sa valeur, comme ceux de `build()`, pour que la page en fasse
				// la question (`EquipmentReadingLib::getConflictFromFail()`). Le matériel qui se réveille
				// garde l'unité choisie, `getLockedMeter()` venant de le vérifier.
				$eEquipmentReading['equipment'] = new Equipment([
					'id' => $eEquipmentAsleep['id'],
					'meter' => $e['meter']
				]);

				EquipmentReading::fail('value.backwards', [$eEquipmentReading, $eEquipmentReading['value']]);
				return;

			}

		}

		$e['awakened'] = $eEquipmentAsleep->notEmpty();

		Equipment::model()->beginTransaction();

			if($e['eToolNew']->notEmpty()) {
				\work\ToolLib::create($e['eToolNew']);
				$e['tool'] = $e['eToolNew'];
			}

			if($eEquipmentAsleep->notEmpty()) {

				$e['id'] = $eEquipmentAsleep['id'];
				$e['status'] = Equipment::ACTIVE;

				Equipment::model()
					->select(self::prepareLastMeter($e, ['meter', 'registration', 'status']))
					->update($e);

			} else {
				$e['lastMeter'] = $e['meter'];
				parent::create($e);
			}

			if(in_array(Favorite::MAINTENANCE, $e['structure']->conf(\structure\Feature::class)['hasToolbox']) === FALSE) {
				\structure\FeatureLib::addToolbox($e['structure'], Favorite::MAINTENANCE);
			}

			if($e->hasMeter() and $eEquipmentReading->notEmpty()) {

				$eEquipmentReading['equipment'] = $e;

				EquipmentReadingLib::create($eEquipmentReading);

			}

			// Un matériel qui se réveille retrouve ses entretiens, et leurs échéances le planning.
			\planning\DueLib::refresh($e['structure'], 'toolbox');

		Equipment::model()->commit();

	}

	/**
	 * Les matériels suivis, dans l'ordre où ils sont entrés au carnet : un matériel garde son onglet
	 * d'une visite à l'autre. Chacun porte ses relevés, le compteur de l'année (`valueYear`,
	 * `valueYearPrevious`, `EquipmentReadingLib::getYear()`), ses entretiens, avec leur échéance
	 * (`MaintenanceLib::fill()`), et son historique, du plus récent au plus ancien, à la page que
	 * `$pages` demande pour lui (id du matériel => page), la première sinon.
	 */
	public static function getByStructure(\structure\Structure $eStructure, array $pages = []): \Collection {

		$cEquipment = Equipment::model()
			->select([
				'id', 'structure', 'meter', 'registration', 'readingSkippedDate', 'readingPostponedDate', 'status', 'createdAt',
				'tool' => ['name', 'vignette', 'routineName'],
				'cEquipmentReading' => EquipmentReading::model()
					->select(['id', 'date', 'value'])
					->sort(['date' => SORT_ASC])
					->delegateCollection('equipment'),
				'cMaintenance' => Maintenance::model()
					->select(['id', 'structure', 'name', 'everyValue', 'everyMonths', 'plannedDate', 'plannedValue', 'ignoredDate', 'postponedDate', 'status'])
					->whereStatus(Maintenance::ACTIVE)
					->sort(['id' => SORT_ASC])
					->delegateCollection('equipment', 'id'),
				'cMaintenanceDone' => MaintenanceDone::model()
					->select([
						'id', 'structure', 'name', 'date', 'value', 'cost', 'comment', 'photo',
						'maintenance' => ['name']
					])
					->sort(['date' => SORT_DESC, 'id' => SORT_DESC])
					->delegateCollection('equipment')
			])
			->whereStructure($eStructure)
			->whereStatus(Equipment::ACTIVE)
			->sort(['id' => SORT_ASC])
			->getCollection(index: 'id');

		foreach($cEquipment as $eEquipment) {

			$eEquipment['eEquipmentReadingLast'] = EquipmentReadingLib::getLast($eEquipment['cEquipmentReading']);
			[$eEquipment['valueYear'], $eEquipment['valueYearPrevious']] = EquipmentReadingLib::getYear($eEquipment['cEquipmentReading']);

			MaintenanceLib::fill($eEquipment);
			self::fillSummary($eEquipment);
			self::fillHistory($eEquipment, $pages[$eEquipment['id']] ?? 0);

		}

		return $cEquipment;

	}

	/**
	 * Ce que le bloc d'un matériel montre (planche pf-t4-v3, Q1) : l'entretien dont l'échéance à la
	 * date vient la première (`eMaintenanceNext`), que la tête d'un matériel sans compteur annonce, et
	 * les seuls entretiens qui ont une échéance (`cMaintenanceScheduled`, retour du 30/09) :
	 * celui qui ne revient qu'au compteur d'un matériel dont le compteur est coupé ne se lit plus que
	 * dans l'historique, et revient dans le bloc avec le compteur. Celui qui ne revient qu'au compteur
	 * d'un matériel sans relevé y est aussi : son échéance est connue (« à 400 h »), c'est son reste
	 * qui ne se calcule pas encore, donc il n'est ni bientôt dû ni en retard (`due` reste `NULL`) et
	 * « Fait » demande le compteur (`MaintenanceDoneLib::isValueRequired()`). `overdue` compte ceux qui
	 * sont en retard.
	 */
	protected static function fillSummary(Equipment $eEquipment): void {

		$eEquipment['eMaintenanceNext'] = new Maintenance();

		foreach($eEquipment['cMaintenance'] as $eMaintenance) {

			if(
				$eMaintenance['nextDate'] !== NULL and
				(
					$eEquipment['eMaintenanceNext']->empty() or
					$eMaintenance['nextDate'] < $eEquipment['eMaintenanceNext']['nextDate']
				)
			) {
				$eEquipment['eMaintenanceNext'] = $eMaintenance;
			}

		}

		$awaitsReading = ($eEquipment->hasMeter() and $eEquipment['eEquipmentReadingLast']->empty());

		$eEquipment['cMaintenanceScheduled'] = $eEquipment['cMaintenance']->find(fn($eMaintenance) => (
			$eMaintenance['dueBy'] !== NULL or
			($awaitsReading and $eMaintenance['plannedValue'] !== NULL)
		));

		// Le compteur rouge de l'onglet du matériel (retour du 01/10).
		$eEquipment['overdue'] = $eEquipment['cMaintenanceScheduled']->find(fn($eMaintenance) => $eMaintenance['due'] === MaintenanceLib::OVERDUE)->count();

	}

	/**
	 * L'historique entier d'un matériel : ses entretiens faits, ses interventions et, sur un matériel
	 * à compteur, ses relevés (retour du 30/09, « ajouter modif de kilomètre/heure à
	 * l'historique »), du plus récent au plus ancien, et à jour égal l'entretien avant le relevé.
	 *
	 * Un relevé que dit déjà un entretien fait, du même jour et au même compteur, ne se répète pas :
	 * c'est celui que « Mettre à jour le compteur » a posé, ou celui que l'entretien a lu.
	 */
	public static function getHistory(Equipment $eEquipment): array {

		$eEquipment->expects(['meter', 'cEquipmentReading', 'cMaintenanceDone']);

		$history = [];

		foreach($eEquipment['cMaintenanceDone'] as $eMaintenanceDone) {
			$history[] = $eMaintenanceDone;
		}

		if($eEquipment->hasMeter()) {

			foreach($eEquipment['cEquipmentReading'] as $eEquipmentReading) {

				$said = array_filter($history, fn($eHistory) => (
					$eHistory['date'] === $eEquipmentReading['date'] and
					$eHistory['value'] === $eEquipmentReading['value']
				));

				if($said === []) {
					$history[] = $eEquipmentReading;
				}

			}

		}

		usort($history, fn($eHistory1, $eHistory2) => (
			strcmp($eHistory2['date'], $eHistory1['date']) ?:
			(($eHistory1 instanceof EquipmentReading) <=> ($eHistory2 instanceof EquipmentReading))
		));

		return $history;

	}

	/**
	 * L'écran ne reçoit qu'une page de l'historique (`history`,
	 * `ToolboxSetting::MAINTENANCE_HISTORY_PER_PAGE`), `historyPage` sur `historyPages`. Elle se coupe
	 * ici, après la fusion : les relevés sont déjà tous lus pour la courbe et les cumuls de l'année, et
	 * un relevé ne se sait répété qu'en face des entretiens faits. Une page que l'historique n'a plus,
	 * après une ligne supprimée, retombe sur la dernière.
	 */
	protected static function fillHistory(Equipment $eEquipment, int $page): void {

		$history = self::getHistory($eEquipment);

		$eEquipment['historyPages'] = (int)ceil(count($history) / ToolboxSetting::MAINTENANCE_HISTORY_PER_PAGE);
		$eEquipment['historyPage'] = max(0, min($page, $eEquipment['historyPages'] - 1));
		$eEquipment['history'] = array_slice($history, $eEquipment['historyPage'] * ToolboxSetting::MAINTENANCE_HISTORY_PER_PAGE, ToolboxSetting::MAINTENANCE_HISTORY_PER_PAGE);

	}

	/**
	 * Le matériel suivi `$id` de la structure, tel que le carnet le montre, avec son historique entier
	 * en `history` et ce que ses entretiens faits ont coûté en `cost` : c'est ce qu'un export emporte,
	 * là où l'écran n'en lit qu'une page. Un élément vide si la structure ne suit pas ce matériel.
	 */
	public static function getWithHistory(\structure\Structure $eStructure, int $id): Equipment {

		$cEquipment = self::getByStructure($eStructure);

		if($cEquipment->offsetExists($id) === FALSE) {
			return new Equipment();
		}

		$eEquipment = $cEquipment[$id];
		$eEquipment['history'] = self::getHistory($eEquipment);
		$eEquipment['cost'] = (float)$eEquipment['cMaintenanceDone']->sum(fn($eMaintenanceDone) => $eMaintenanceDone['cost'] ?? 0.0);

		return $eEquipment;

	}

	/**
	 * L'historique en CSV : une ligne par ligne de l'historique (`getWithHistory()`), du plus récent au
	 * plus ancien comme à l'écran. `type` dit ce qu'est la ligne, un relevé du compteur, un entretien
	 * planifié qui a été fait, ou une intervention consignée hors de tout entretien.
	 */
	public static function getExport(Equipment $eEquipment): array {

		$output = [];

		foreach($eEquipment['history'] as $eHistory) {

			if($eHistory instanceof EquipmentReading) {

				$output[] = [
					$eHistory['date'],
					'reading',
					'',
					'',
					$eHistory['value'],
					$eEquipment['meter'],
					NULL,
				];

			} else {

				$output[] = [
					$eHistory['date'],
					$eHistory['maintenance']->empty() ? 'intervention' : 'maintenance',
					$eHistory->getName(),
					$eHistory['comment'] ?? '',
					$eEquipment->hasMeter() ? $eHistory['value'] : NULL,
					$eEquipment['meter'] ?? '',
					\util\TextUi::csvNumber($eHistory['cost']),
				];

			}

		}

		return $output;

	}

	/**
	 * Les entretiens en retard ou bientôt dus de tous les matériels `$cEquipment`, les retards
	 * d'abord : c'est le bandeau « Entretiens à faire » du carnet, et la ligne de la carte de la Boîte.
	 */
	public static function getDue(\Collection $cEquipment): \Collection {

		$cMaintenanceDue = new \Collection();

		foreach([MaintenanceLib::OVERDUE, MaintenanceLib::SOON] as $due) {

			foreach($cEquipment as $eEquipment) {

				foreach($eEquipment['cMaintenance'] as $eMaintenance) {

					if($eMaintenance['due'] === $due) {
						$cMaintenanceDue[] = $eMaintenance;
					}

				}

			}

		}

		return $cMaintenanceDue;

	}

	/**
	 * Ce que la carte du carnet montre dans la Boîte à outils (arbitrage Q4 A de la planche pf-t4-v2,
	 * « une synthèse en une ligne du rouge ») : le nombre de matériels suivis, le jour du dernier entretien
	 * fait ou de la dernière intervention consignée, tous matériels confondus (`NULL` tant que rien n'a été
	 * fait), et le nombre des entretiens en retard.
	 */
	public static function getSummary(\structure\Structure $eStructure): array {

		$cEquipment = self::getByStructure($eStructure);

		// `cMaintenanceDone` est trié du plus récent au plus ancien : le premier de chaque matériel est son
		// dernier.
		$lastDone = NULL;

		foreach($cEquipment as $eEquipment) {

			if($eEquipment['cMaintenanceDone']->notEmpty()) {
				$lastDone = max($lastDone ?? '', $eEquipment['cMaintenanceDone']->first()['date']);
			}

		}

		return [
			'equipments' => $cEquipment->count(),
			'lastDone' => $lastDone,
			'overdue' => self::getDue($cEquipment)->find(fn($eMaintenance) => $eMaintenance['due'] === MaintenanceLib::OVERDUE)->count()
		];

	}

	/**
	 * Un matériel qu'on ne suit plus, ou dont le compteur change, change les entretiens que le planning
	 * montre (`planning\DueLib::refresh()`).
	 */
	public static function update(Equipment $e, array $properties): void {

		$e->expects(['structure']);

		Equipment::model()->beginTransaction();

			parent::update($e, self::prepareLastMeter($e, $properties));

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		Equipment::model()->commit();

	}

	/**
	 * La croix du planning sur un compteur à relever (demande du 03/10) : le compteur quitte
	 * le planning sans relevé et revient au rappel suivant (`NotificationLib::getReadingDate()`). Rien
	 * n'entre dans les relevés.
	 *
	 * La croix retient le jour du rappel qu'elle passe, ou aujourd'hui s'il est passé : le planning
	 * montre aussi les semaines à venir, où un compteur dû le 5 se passe avant ce jour-là.
	 */
	public static function skipReading(Equipment $e): void {

		$e->expects(['structure', 'meter', 'createdAt', 'readingSkippedDate']);

		self::fillReadingLast($e);

		$eEquipmentReadingLast = $e['eEquipmentReadingLast'];
		$dueDate = NotificationLib::getReadingDate(MaintenanceConfigurationLib::getReadingEveryMonths($e['structure']), $eEquipmentReadingLast->empty() ? NULL : $eEquipmentReadingLast['date'], $e['createdAt'], $e['readingSkippedDate']);

		$e['readingSkippedDate'] = max(\Clock::date(), $dueDate);

		self::update($e, ['readingSkippedDate']);

	}

	/**
	 * Le décalage d'un compteur à relever à la semaine suivante de celle où le planning le montre
	 * (`planning\DueLib::getPostponedDate()`, retour 15 du 04/10) : il reparaît le lundi
	 * d'après. Comme la croix, un relevé fait ensuite le rend caduc sans le remettre à NULL
	 * (`PlanningObserverLib::getDue()`).
	 */
	public static function postponeReading(Equipment $e): void {

		// Sans `build()`, validé le 04/10 : rien de posté ne devient la propriété, la date se
		// calcule ici depuis la ligne du planning, comme celle de `skipReading()`.
		$e['readingPostponedDate'] = \planning\DueLib::getPostponedDate($e['structure'], PlanningObserverLib::READING, $e['id']);

		self::update($e, ['readingPostponedDate']);

	}

	/**
	 * Un compteur écrit devient la dernière unité du matériel (`lastMeter`), et `$properties` gagne
	 * la colonne. Un compteur coupé ne l'écrase pas : c'est l'unité des valeurs déjà saisies.
	 */
	private static function prepareLastMeter(Equipment $e, array $properties): array {

		if(in_array('meter', $properties) and $e['meter'] !== NULL) {
			$e['lastMeter'] = $e['meter'];
			$properties[] = 'lastMeter';
		}

		return $properties;

	}

	/**
	 * L'unité à laquelle le compteur de `$eEquipment` est tenu, NULL tant qu'il ne porte aucune valeur
	 * de compteur : un relevé, un entretien fait, ou la « Prochaine fois » d'un entretien. Ces valeurs
	 * ont été saisies dans la dernière unité du compteur (`lastMeter`), qu'il ne quitte plus (étape
	 * `locked` d'`Equipment::properties()`, planche pf-t4-v3). Les relevés portent aussi la leur, que
	 * la lecture préfère.
	 */
	public static function getLockedMeter(Equipment $eEquipment): ?string {

		$readings = EquipmentReading::model()
			->whereEquipment($eEquipment)
			->getValue('meter');

		if($readings !== NULL) {
			return $readings;
		}

		$hasValues = (
			Maintenance::model()
				->whereEquipment($eEquipment)
				->where('plannedValue IS NOT NULL')
				->exists() or
			MaintenanceDone::model()
				->whereEquipment($eEquipment)
				->where('value IS NOT NULL')
				->exists()
		);

		if($hasValues === FALSE) {
			return NULL;
		}

		return Equipment::model()
			->whereId($eEquipment)
			->getValue('lastMeter');

	}

	/**
	 * Le dernier relevé du compteur à ce jour, dit sous les champs où l'on saisit une valeur du
	 * compteur (retour du 01/10, « indiquer le nombre d'heures / km actuel pour faciliter la
	 * saisie ») : « Prochaine fois » d'un entretien, le compteur de « Fait » et de « Consigner un
	 * entretien ». Un élément vide sans compteur ou sans relevé.
	 */
	public static function fillReadingLast(Equipment $eEquipment): void {

		$eEquipment['eEquipmentReadingLast'] = $eEquipment->hasMeter() ? EquipmentReadingLib::getByEquipmentAndDate($eEquipment, \Clock::date()) : new EquipmentReading();

	}

	/**
	 * Le matériel suivi au carnet de `$eTool`, vide quand le carnet ne le suit pas, avec l'unité à
	 * laquelle son compteur est tenu (`meterLocked`) : « Modifier le matériel » y règle son compteur
	 * (planche pf-t4-v3, Q3).
	 */
	public static function getByTool(\work\Tool $eTool): Equipment {

		$eEquipment = Equipment::model()
			->select(['id', 'structure', 'meter', 'registration', 'status'])
			->whereTool($eTool)
			->whereStatus(Equipment::ACTIVE)
			->get();

		if($eEquipment->notEmpty()) {
			$eEquipment['meterLocked'] = self::getLockedMeter($eEquipment);
		}

		return $eEquipment;

	}

	/**
	 * Un matériel suivi au carnet est un matériel unique, que ses relevés et ses entretiens désignent :
	 * « Mon matériel » ne lui propose plus de stock (retour du 30/09 sur la recette T4).
	 * `work\ToolLib::getPropertiesUpdate()` retire le stock de ce qui se construit, le contrôle
	 * `followed` de `work\Tool` refuse l'édition rapide, et les écrans lisent `followed`, que les pages
	 * posent par `fillFollowed()`.
	 */
	/**
	 * La structure suit-elle déjà un matériel : « Suivre un matériel » devient alors « Suivre un autre
	 * matériel » (retour du 30/09).
	 */
	public static function hasFollowed(\structure\Structure $eStructure): bool {

		return Equipment::model()
			->whereStructure($eStructure)
			->whereStatus(Equipment::ACTIVE)
			->exists();

	}

	public static function isFollowed(\work\Tool $eTool): bool {

		// Un matériel sans identifiant n'est suivi par rien, et la question ne part pas en base. Le
		// générateur de la doc d'API la pose à des sondes sans identifiant, et tourne sans base : la
		// requête y échouerait pour toutes, et `ToolUpdate` deviendrait un corps libre
		// (`framework/dev/test/ApiDocNoDatabaseAi.t.php`).
		if($eTool->exists() === FALSE) {
			return FALSE;
		}

		return Equipment::model()
			->whereTool($eTool)
			->whereStatus(Equipment::ACTIVE)
			->exists();

	}

	/**
	 * Le carnet porte-t-il ce matériel, qu'il le suive encore ou non : ses relevés et son historique
	 * le désignent alors, et « Mon matériel » ne peut plus en effacer la ligne (`work\ToolLib::delete()`).
	 */
	public static function isRecorded(\work\Tool $eTool): bool {

		return Equipment::model()
			->whereTool($eTool)
			->exists();

	}

	/**
	 * Le matériel quitte « Mon matériel » : le carnet cesse de le suivre, comme par « Ne plus suivre »,
	 * et garde ses relevés et son historique. Sans cela, le carnet montrerait encore l'onglet d'un
	 * matériel supprimé et le nommerait dans ses notifications, toutes ses lectures ne retenant que
	 * les matériels suivis.
	 */
	public static function forget(\work\Tool $eTool): void {

		$eEquipment = Equipment::model()
			->select(['id', 'structure'])
			->whereTool($eTool)
			->whereStatus(Equipment::ACTIVE)
			->get();

		if($eEquipment->empty()) {
			return;
		}

		$eEquipment['status'] = Equipment::INACTIVE;

		self::update($eEquipment, ['status']);

	}

	public static function fillFollowed(\Collection $cTool): void {

		$toolsFollowed = Equipment::model()
			->whereTool('IN', $cTool)
			->whereStatus(Equipment::ACTIVE)
			->getColumn('tool')
			->getIds();

		foreach($cTool as $eTool) {
			$eTool['followed'] = in_array($eTool['id'], $toolsFollowed);
		}

	}

	/**
	 * Les matériels suivis à compteur qu'aucun relevé n'a touchés depuis `$since`, jamais relevés
	 * compris : ceux que le rappel des compteurs nomme (`NotificationLib::READING`). Sur tout le parc pour
	 * le cron, bornés par la structure pour la relecture d'une notification.
	 *
	 * Un anti-join : le matériel à gauche, ses relevés récents à droite, la borne de date dans le `ON`
	 * pour que le matériel sans relevé récent reste une ligne. La clé unique (equipment, date) sert la
	 * jointure.
	 */
	public static function getUnreadSince(string $since, \structure\Structure $eStructure = new \structure\Structure()): \Collection {

		return Equipment::model()
			->select(['id', 'structure', 'meter', 'tool' => ['name']])
			->join(EquipmentReading::model(), 'm1.id = m2.equipment AND m2.date >= '.EquipmentReading::model()->format($since), 'LEFT')
			->where('m2.id IS NULL')
			->where('m1.structure', $eStructure, if: $eStructure->notEmpty())
			->where('m1.status', Equipment::ACTIVE)
			->where('m1.meter IS NOT NULL')
			->getCollection(index: 'id');

	}

	/**
	 * Les matériels de la structure que l'autocomplétion de « Suivre un matériel » propose : ceux de
	 * « Mon matériel » qui ne sont pas des produits (un produit porte une routine) et que le carnet ne
	 * suit pas déjà.
	 */
	public static function getToolsFromQuery(\structure\Structure $eStructure, string $query): \Collection {

		return \work\Tool::model()
			->select(\work\Tool::getSelection())
			->join(Equipment::model(), 'm1.id = m2.tool AND m2.status = \''.Equipment::ACTIVE.'\'', 'LEFT')
			->where('m1.structure', $eStructure['id'])
			->where('m1.status', \work\Tool::ACTIVE)
			->where('m1.routineName IS NULL')
			->where('m1.name', 'LIKE', '%'.$query.'%', if: $query !== '')
			->where('m2.id IS NULL')
			->getCollection()
			->sort('name', natural: TRUE);

	}

}
?>
