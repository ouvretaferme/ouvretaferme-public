<?php
namespace toolbox;

/**
 * Les entretiens faits : l'historique d'un matériel, et ce qui fait repartir « Prochaine fois » de
 * chaque entretien régulier, ou clôt un entretien « Une seule fois » (`follow()`).
 */
class MaintenanceDoneLib extends MaintenanceDoneCrud {

	// Le compteur ne se saisit qu'à la création, quand la personne choisit de le mettre à jour
	// (`MaintenanceDone::properties()`, `meterUpdate`) ; sinon `create()` le lit dans les relevés.
	// Une ligne modifiée garde le sien tant que sa date ne change pas (`update()`). `name` ne s'écrit
	// que sur une intervention (`MaintenanceDone::properties()`).
	//
	// L'API n'a pas de lien pour dire de quel matériel et de quel entretien on parle : elle les construit,
	// avant `name` et `value` dont les étapes les lisent. Elle ne porte pas la photo, qui se dépose par
	// un envoi de fichier qu'elle n'a pas, et dont un corps complet n'a donc pas à parler.
	public static function getPropertiesCreate(?string $origin = NULL): array {

		if($origin === 'api') {
			return ['equipment', 'maintenance', 'name', 'date', 'value', 'cost', 'comment'];
		}

		// `task` n'est pas une colonne : c'est la tâche que « Fait » écrit au planning, construite après
		// la date qu'elle reprend (`MaintenanceDone::properties()`). L'API ne la construit pas, et ne
		// crée donc aucune tâche.
		return ['name', 'date', 'value', 'cost', 'comment', 'photo', 'task'];

	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {

		if($origin === 'api') {
			return ['name', 'date', 'cost', 'comment'];
		}

		return ['name', 'date', 'cost', 'comment', 'photo'];

	}

	/**
	 * Le compteur d'un entretien fait est ce que le matériel affichait ce jour-là : saisi, il devient le
	 * relevé de ce jour (`meterUpdate`, retour du 30/09) ; sinon il est lu dans les relevés
	 * (`prepareValue()`). C'est de lui que l'échéance au compteur repart.
	 *
	 * L'entretien fait fait repartir l'échéance de son entretien quand il en devient la dernière fois
	 * (`follow()`). Une intervention n'a pas d'échéance.
	 */
	public static function create(MaintenanceDone $e): void {

		// Un entretien fait construit à la main, sans passer par `build()`, ne met pas le compteur à jour.
		if($e->offsetExists('meterUpdate') === FALSE) {
			$e['meterUpdate'] = FALSE;
		}

		if($e['meterUpdate'] === FALSE) {
			self::prepareValue($e);
		}

		// Seul `build()` de l'écran prépare une tâche du planning (`MaintenanceTaskLib::prepare()`) : par
		// l'API ou construit à la main, un entretien fait n'en écrit aucune.
		if($e->offsetExists('eTask') === FALSE) {
			$e['eTask'] = new \series\Task();
		}

		MaintenanceDone::model()->beginTransaction();

			MaintenanceTaskLib::create($e);

			$eMaintenanceDoneLast = self::getLast($e['maintenance']);

			parent::create($e);

			if($e['meterUpdate']) {

				EquipmentReadingLib::create(new EquipmentReading([
					'structure' => $e['structure'],
					'equipment' => $e['equipment'],
					'date' => $e['date'],
					'value' => $e['value']
				]));

			}

			if($e['maintenance']->notEmpty()) {
				self::follow($e['maintenance'], $eMaintenanceDoneLast);
			}

		MaintenanceDone::model()->commit();

	}

	/**
	 * Une ligne dont la date change peut cesser d'être, ou devenir, la dernière fois de son entretien.
	 * Son compteur est celui que le matériel affichait le jour de l'entretien : déplacée, elle le relit
	 * dans les relevés à la nouvelle date (`prepareValue()`), à l'écran comme par l'API, et le relevé de
	 * l'ancien jour reste (décidé le 01/10/2026). Sur un matériel dont le compteur est coupé,
	 * les relevés ne se lisent plus : elle garde celui qu'elle porte, qui reprend son sens quand le
	 * compteur revient.
	 *
	 * Le formulaire poste la date à chaque enregistrement : la date d'avant se lit donc en base, et ni
	 * le compteur ni l'échéance ne bougent quand elle est la même. Une échéance corrigée sur l'entretien
	 * survit ainsi à la correction d'un coût.
	 */
	public static function update(MaintenanceDone $e, array $properties): void {

		$e->expects(['maintenance', 'equipment' => ['meter']]);

		MaintenanceDone::model()->beginTransaction();

			$dateChanged = (
				in_array('date', $properties) and
				MaintenanceDone::model()->whereId($e['id'])->getValue('date') !== $e['date']
			);

			$eMaintenanceDoneLast = self::getLast($e['maintenance']);

			if($dateChanged and $e['equipment']->hasMeter()) {
				self::prepareValue($e);
				$properties[] = 'value';
			}

			parent::update($e, $properties);

			if($e['maintenance']->notEmpty() and $dateChanged) {
				self::follow($e['maintenance'], $eMaintenanceDoneLast);
			}

		MaintenanceDone::model()->commit();

	}

	public static function delete(MaintenanceDone $e): void {

		$e->expects(['maintenance']);

		MaintenanceDone::model()->beginTransaction();

			$eMaintenanceDoneLast = self::getLast($e['maintenance']);

			parent::delete($e);

			if($e['maintenance']->notEmpty()) {
				self::follow($e['maintenance'], $eMaintenanceDoneLast);
			}

		MaintenanceDone::model()->commit();

	}

	/**
	 * La dernière fois qu'un entretien a été fait, un élément vide tant qu'il ne l'a jamais été, ou
	 * pour une intervention, qui n'a pas d'entretien.
	 */
	protected static function getLast(Maintenance $eMaintenance): MaintenanceDone {

		if($eMaintenance->empty()) {
			return new MaintenanceDone();
		}

		return MaintenanceDone::model()
			->select(['id', 'date', 'value'])
			->whereMaintenance($eMaintenance)
			->sort(['date' => SORT_DESC, 'id' => SORT_DESC])
			->get();

	}

	/**
	 * Aligne un entretien sur la dernière fois qu'il a été fait, après un entretien fait noté, corrigé
	 * ou retiré de l'historique. `$eMaintenanceDoneBefore` est sa dernière fois d'avant l'écriture
	 * (`getLast()`) :
	 *
	 * - un entretien régulier repart de sa dernière fois (`MaintenanceLib::plan()`) quand elle a changé,
	 *   sauf quand un retard a été ignoré depuis : l'échéance part alors déjà de ce jour-là. Un retard
	 *   ignoré avant la dernière fois est oublié. Une ligne notée, déplacée ou retirée avant la dernière
	 *   fois n'entre qu'à l'historique : l'échéance ne bouge pas, et celle que la personne a corrigée sur
	 *   l'entretien reste. Sans aucun entretien fait, « Prochaine fois » reste aussi ce qu'elle est :
	 *   celle que la personne avait posée n'est plus connue ;
	 * - un entretien « Une seule fois » est clos (`Maintenance::DONE`) tant qu'un entretien fait le
	 *   nomme, et revient au bloc du matériel quand ce n'est plus le cas. Un entretien supprimé le reste.
	 */
	protected static function follow(Maintenance $eMaintenance, MaintenanceDone $eMaintenanceDoneBefore): void {

		$eMaintenance = MaintenanceLib::getById($eMaintenance, ['id', 'structure', 'everyValue', 'everyMonths', 'ignoredDate', 'status', 'equipment' => ['meter']]);

		if($eMaintenance['status'] === Maintenance::DELETED) {
			return;
		}

		$eMaintenanceDoneLast = self::getLast($eMaintenance);

		if($eMaintenance->isRegular()) {

			$isSame = (
				$eMaintenanceDoneBefore->notEmpty() and
				$eMaintenanceDoneLast->notEmpty() and
				$eMaintenanceDoneBefore->extracts(['id', 'date', 'value']) === $eMaintenanceDoneLast->extracts(['id', 'date', 'value'])
			);

			if(
				$eMaintenanceDoneLast->notEmpty() and
				$isSame === FALSE and
				($eMaintenance['ignoredDate'] === NULL or $eMaintenance['ignoredDate'] <= $eMaintenanceDoneLast['date'])
			) {

				$eMaintenance['ignoredDate'] = NULL;

				// Noté avant tout relevé, l'entretien fait n'a pas de compteur : l'échéance au compteur part du
				// premier relevé d'après, faute de quoi un entretien au compteur seul n'aurait plus d'échéance
				// et quitterait le bloc du matériel. Un compteur ne recule pas, le plus petit d'après est donc
				// le premier.
				$value = $eMaintenanceDoneLast['value'] ?? EquipmentReading::model()
					->whereEquipment($eMaintenance['equipment'])
					->whereDate('>', $eMaintenanceDoneLast['date'])
					->getValue(new \Sql('MIN(value)', 'int'));

				MaintenanceLib::update($eMaintenance, ['ignoredDate']);
				MaintenanceLib::plan($eMaintenance, $eMaintenanceDoneLast['date'], $value);

			}

		} else {

			$eMaintenance['status'] = $eMaintenanceDoneLast->empty() ? Maintenance::ACTIVE : Maintenance::DONE;

			MaintenanceLib::update($eMaintenance, ['status']);

		}

	}

	/**
	 * Un entretien dû au compteur seul repart du compteur du jour où il est fait. Sur un matériel qui
	 * n'a aucun relevé, ce compteur ne se lit ni avant ni après (`follow()`) : l'entretien fait doit
	 * le porter, sans quoi son entretien n'aurait plus d'échéance (tranché sous mandat le 02/10/2026).
	 * Dû aussi à la date, il garde celle-ci, et rien de plus n'est exigé.
	 */
	public static function isValueRequired(MaintenanceDone $e): bool {

		$e->expects([
			'equipment' => ['meter'],
			'maintenance'
		]);

		if($e['maintenance']->empty() or $e['equipment']->hasMeter() === FALSE) {
			return FALSE;
		}

		$e['maintenance']->expects(['everyValue', 'everyMonths']);

		return (
			$e['maintenance']['everyValue'] !== NULL and
			$e['maintenance']['everyMonths'] === NULL and
			EquipmentReading::model()
				->whereEquipment($e['equipment'])
				->exists() === FALSE
		);

	}

	/**
	 * Le compteur du jour de l'entretien, lu dans les relevés d'un matériel à compteur. Le panneau s'en
	 * sert aussi pour dire « Prochaine fois » avant l'envoi.
	 */
	public static function prepareValue(MaintenanceDone $e): void {

		$e->expects(['equipment' => ['meter'], 'date']);

		$eEquipmentReading = $e['equipment']->hasMeter() ? EquipmentReadingLib::getByEquipmentAndDate($e['equipment'], $e['date']) : new EquipmentReading();

		$e['value'] = $eEquipmentReading->empty() ? NULL : $eEquipmentReading['value'];

	}

}
?>
