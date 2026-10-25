<?php
namespace hr;

class ContractLib extends ContractCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return self::getPropertiesUpdate();
	}

	/**
	 * `hoursPeriod` avant les heures, `hasVariableDays` avant `days` : les étapes `period`, `annualHours.required`
	 * et `variable` lisent la propriété déjà construite.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['type', 'hoursPeriod', 'weeklyHours', 'annualHours', 'hasVariableDays', 'days'];
	}

	/**
	 * Le contrat que propose le formulaire d'une période, du lundi au vendredi.
	 */
	public static function getNew(): Contract {

		return new Contract([
			'hoursPeriod' => Contract::WEEK,
			'days' => new \Set(Contract::MONDAY | Contract::TUESDAY | Contract::WEDNESDAY | Contract::THURSDAY | Contract::FRIDAY),
			'hasVariableDays' => FALSE
		]);

	}

	/**
	 * Le type ou les heures disent qu'il y a un contrat ; les jours seuls non, lundi à vendredi étant cochés
	 * d'avance (`getNew()`).
	 */
	public static function isPosted(): bool {
		return (POST('type') !== '' or POST('weeklyHours') !== '' or POST('annualHours') !== '');
	}

	public static function getByPresence(Presence $ePresence): Contract {

		return Contract::model()
			->select(Contract::getSelection())
			->wherePresence($ePresence)
			->get();

	}

	/**
	 * Les contrats des personnes de `$cUser` dont la période touche `$start` à `$stop`, chacun avec sa période
	 * (`presence`, sa personne et ses dates), que le compteur d'heures lit jour par jour (`OvertimeLib`).
	 */
	public static function getByUsers(\structure\Structure $eStructure, \Collection $cUser, string $start, string $stop): \Collection {

		$mPresence = Presence::model()
			->select('id')
			->whereStructure($eStructure)
			->whereUser('IN', $cUser)
			->whereFrom('<=', $stop)
			->or(
				fn() => $this->whereTo('>=', $start),
				fn() => $this->whereTo(NULL)
			);

		return Contract::model()
			->select(Contract::getSelection() + [
				'presence' => ['user', 'from', 'to']
			])
			->whereStructure($eStructure)
			->wherePresence('IN', $mPresence->getSql())
			->getCollection();

	}

	/**
	 * Le contrat appartient à sa période : il en prend la structure, et n'a ni l'une ni l'autre dans ses listes
	 * de `build()`.
	 */
	public static function create(Contract $e): void {

		$e->expects([
			'presence' => ['structure']
		]);

		$e['structure'] = $e['presence']['structure'];

		parent::create($e);

	}

	/**
	 * Aligne le contrat d'une période sur celui que le formulaire a construit : un contrat vide efface celui
	 * qui existait.
	 */
	public static function synchronizeByPresence(Presence $ePresence, Contract $e): void {

		$eContractCurrent = self::getByPresence($ePresence);

		if($e->empty()) {

			if($eContractCurrent->notEmpty()) {
				self::delete($eContractCurrent);
			}

		} else if($eContractCurrent->empty()) {

			$e['presence'] = $ePresence;
			self::create($e);

		} else {

			$e['id'] = $eContractCurrent['id'];
			self::update($e, self::getPropertiesUpdate());

		}

	}

	public static function deleteByPresence(Presence $ePresence): void {

		$eContract = Contract::model()
			->select(['id'])
			->wherePresence($ePresence)
			->get();

		if($eContract->notEmpty()) {
			self::delete($eContract);
		}

	}

}
?>
