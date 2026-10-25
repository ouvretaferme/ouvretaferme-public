<?php
namespace hr;

class PresenceLib extends PresenceCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return array_merge(['user'], self::getPropertiesUpdate());
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['from', 'to'];
	}

	public static function create(Presence $e): void {

		$e->expects(['structure', 'user']);

		$e->add([
			'eContract' => new Contract()
		]);

		Presence::model()->beginTransaction();

			parent::create($e);

			if($e['eContract']->notEmpty()) {
				$e['eContract']['presence'] = $e;
				ContractLib::create($e['eContract']);
			}

		Presence::model()->commit();

	}

	/**
	 * La période et le contrat du formulaire s'enregistrent ensemble, et un contrat vide efface celui de la
	 * période. L'API n'écrit que la période, par `update()`, et laisse son contrat tel quel.
	 */
	public static function updateWithContract(Presence $e, array $properties, Contract $eContract): void {

		Presence::model()->beginTransaction();

			self::update($e, $properties);

			ContractLib::synchronizeByPresence($e, $eContract);

		Presence::model()->commit();

	}

	public static function delete(Presence $e): void {

		Presence::model()->beginTransaction();

			ContractLib::deleteByPresence($e);

			parent::delete($e);

		Presence::model()->commit();

	}

	/**
	 * Les structures actives où le compte est au planning aujourd'hui ou plus tard, ce qui refuse sa fermeture
	 * (`user\DropLib::changeClose()`, décidé le 22/10). Les adhésions se lisent de tout statut, et donc pas
	 * par `StructureLib::getByUser()`, qui ne rend que celles qui ouvrent la structure : une personne à qui l'on a
	 * retiré tout rôle reste au planning. Les périodes se lisent ensuite par l'index
	 * `(structure, user)`, une période ne portant pas d'index sur la seule personne.
	 */
	public static function getStructuresOpenByUser(\user\User $eUser): \Collection {

		$cStructure = \structure\Member::model()
			->select(['structure' => ['name', 'type', 'status']])
			->whereUser($eUser)
			->getCollection()
			->getColumnCollection('structure', 'structure')
			->find(fn(\structure\Structure $eStructure) => (
				$eStructure['type'] === \structure\Structure::PRODUCER and
				$eStructure['status'] === \structure\Structure::ACTIVE
			));

		if($cStructure->empty()) {
			return new \Collection();
		}

		$structures = Presence::model()
			->select('structure')
			->whereStructure('IN', $cStructure)
			->whereUser($eUser)
			->or(
				fn() => $this->whereTo(NULL),
				fn() => $this->whereTo('>=', \Clock::date())
			)
			->getColumn('structure')
			->getIds();

		return $cStructure->find(fn(\structure\Structure $eStructure) => in_array($eStructure['id'], $structures));

	}

	public static function fillUsers(\structure\Structure $eStructure, \Collection $cUser): void {

		\user\User::model()
			->select([
				'cPresence' => Presence::model()
					->select(Presence::getSelection())
					->whereStructure($eStructure)
					->sort([
						'from' => SORT_ASC
					])
					->delegateCollection('user')
			])
			->get($cUser);

	}

	public static function getByUser(\structure\Structure $eStructure, \user\User $eUser): \Collection {

		$selectPresence = Presence::getSelection();

		// RH éteinte, rien de la RH ne se lit, et la fiche d'une personne n'a pas de colonne Contrat.
		if($eStructure->acceptHr()) {
			$selectPresence['eContract'] = Contract::model()
				->select(Contract::getSelection())
				->delegateElement('presence');
		}

		return Presence::model()
			->select($selectPresence)
			->whereUser($eUser)
			->whereStructure($eStructure)
			->sort([
				'from' => SORT_DESC
			])
			->getCollection();

	}

	/**
	 * Les périodes d'une personne qui touchent `$start` à `$stop`, la plus récente d'abord, chacune avec son contrat
	 * (`eContract`, vide sans contrat).
	 */
	public static function getByUserBetween(\structure\Structure $eStructure, \user\User $eUser, string $start, string $stop): \Collection {

		$ccPresence = self::getByUsersBetween($eStructure, new \Collection([$eUser['id'] => $eUser]), $start, $stop);

		return $ccPresence->offsetExists($eUser['id']) ? $ccPresence[$eUser['id']] : new \Collection();

	}

	/**
	 * Les périodes de chaque personne de `$cUser` qui touchent `$start` à `$stop`, par personne, la plus récente
	 * d'abord, chacune avec son contrat (`eContract`, vide sans contrat).
	 */
	public static function getByUsersBetween(\structure\Structure $eStructure, \Collection $cUser, string $start, string $stop): \Collection {

		return Presence::model()
			->select(Presence::getSelection() + [
				'eContract' => Contract::model()
					->select(Contract::getSelection())
					->delegateElement('presence')
			])
			->whereStructure($eStructure)
			->whereUser('IN', $cUser)
			->whereFrom('<=', $stop)
			->or(
				fn() => $this->whereTo('>=', $start),
				fn() => $this->whereTo(NULL)
			)
			->sort([
				'from' => SORT_DESC
			])
			->getCollection(NULL, NULL, ['user', NULL]);

	}

	public static function isPresentBetween(\structure\Structure $eStructure, \user\User $eUser, string $start, ?string $stop): bool {

		return Presence::model()
			->or(
				fn() => $this
					->whereFrom('<=', $start)
					->whereTo(NULL),
				fn() => $this
					->whereFrom('<=', $start)
					->whereTo('>=', $start),
				// Une période qui commence après `$start` chevauche si elle commence au plus tard à `$stop`, qu'elle
				// ait une fin ou non : sa propre fin n'y change rien.
				fn() => $this
					->whereFrom('>', $start)
					->whereFrom('<=', $stop, if: $stop !== NULL)
			)
			->whereStructure($eStructure)
			->whereUser($eUser)
			->exists();

	}

	/**
	 * Si la journée de `$date` se saisit, la règle de `Presence::isDateWorkable()` lue en base.
	 */
	public static function isWorkable(\structure\Structure $eStructure, \user\User $eUser, string $date): bool {

		return (
			self::isPresentBetween($eStructure, $eUser, $date, $date) or
			Presence::model()
				->whereStructure($eStructure)
				->whereUser($eUser)
				->exists() === FALSE
		);

	}

	/**
	 * Si chaque jour de `$start` à `$stop` est dans une période de la personne, la règle de
	 * `Presence::isRangePresent()` lue en base.
	 */
	public static function isPresentThroughout(\structure\Structure $eStructure, \user\User $eUser, string $start, string $stop): bool {

		$cPresence = Presence::model()
			->select(['from', 'to'])
			->whereStructure($eStructure)
			->whereUser($eUser)
			->whereFrom('<=', $stop)
			->or(
				fn() => $this->whereTo('>=', $start),
				fn() => $this->whereTo(NULL)
			)
			->getCollection();

		return Presence::isRangePresent($cPresence, $start, $stop);

	}

	/**
	 * Les périodes qui touchent `$start` à `$stop`, ou qui ne finissent pas avant `$start` quand `$stop` est nul.
	 * Par défaut, seuls les comptes actifs : un compte suspendu est écarté comme un compte fermé. `$withClosed`
	 * garde tous les comptes, pour une lecture du passé où une personne partie a pourtant travaillé.
	 */
	public static function getBetween(\structure\Structure $eStructure, string $start, ?string $stop, bool $withClosed = FALSE): \Collection {

		$mPresence = Presence::model();

		if($withClosed === FALSE) {
			$mPresence
				->join(\user\User::model(), 'm1.user = m2.id')
				->where('m2.status', \user\User::ACTIVE);
		}

		return $mPresence
			->select(Presence::getSelection())
			->whereFrom('<=', $stop, if: $stop !== NULL)
			->or(
				fn() => $this->whereTo('>=', $start),
				fn() => $this->whereTo(NULL)
			)
			->whereStructure($eStructure)
			->sort([
				'from' => SORT_DESC
			])
			->getCollection(NULL, NULL, ['user', NULL]);

	}

	/**
	 * Les périodes d'une personne sans compte passent au compte qui la remplace (`structure\MemberLib::transferGhost()`), leurs contrats avec elles.
	 */
	public static function transferUser(\structure\Structure $eStructure, \user\User $eUserFrom, \user\User $eUserTo): void {

		// Écriture de masse : toutes les périodes de la personne changent de titulaire, sans rien d'autre à recalculer.
		Presence::model()
			->whereStructure($eStructure)
			->whereUser($eUserFrom)
			->update([
				'user' => $eUserTo
			]);

	}

}
?>
