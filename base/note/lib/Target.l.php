<?php
namespace note;

/**
 * Les objets auxquels une note se rattache. Aucun n'est nommé ici : chaque package qui en apporte porte
 * la paire `NoteTargetLib` + `NoteTargetUi`, que `NoteSetting::resolve()` trouve depuis le type, et dont
 * le contrat est tenu par `NoteTargetSentinel`. Chaque objet qu'un package rend porte son `name`, qui
 * range les badges d'une note (`putByNotes()`).
 *
 * Un objet supprimé n'efface rien : sa ligne reste jusqu'au prochain enregistrement des rattachements,
 * et ne paraît plus nulle part d'ici là, puisque rien ne se montre que le package n'a rendu.
 */
class TargetLib extends TargetCrud {

	/**
	 * Les types que les packages apportent, avec le package de chacun, dans l'ordre où l'omnibox range
	 * leurs familles (`getPosition()`). Le balayage plutôt qu'une liste déclarée, pour la raison de
	 * `DashboardLib::getBlocks()` : un package qui en apporte un n'a aucune raison d'ouvrir celui-ci.
	 */
	public static function getTypes(): array {

		$types = [];
		$positions = [];

		foreach(\Package::getList() as $package => $app) {

			// Le package des notes ne s'apporte pas d'objet à lui-même.
			if($package === __NAMESPACE__) {
				continue;
			}

			$class = '\\'.$package.'\\NoteTargetLib';

			if(class_exists($class)) {
				$types[$class::getType()] = $package;
				$positions[$class::getType()] = $class::getPosition();
			}

		}

		uksort($types, fn(string $type1, string $type2) => $positions[$type1] <=> $positions[$type2]);

		return $types;

	}

	/**
	 * Le rattachement que désigne une valeur postée, ou un élément vide si la valeur ne se lit pas ou
	 * nomme un type qu'aucun package n'apporte.
	 */
	public static function getByValue(string $value): Target {

		// L'identifiant s'écrit sans zéro en tête : `<type>:05` et `<type>:5` désigneraient le même objet
		// sous deux valeurs, que le dédoublonnage de la propriété ne saurait plus reconnaître.
		if(preg_match('/^([a-z]+-[a-z]+):([1-9][0-9]*)$/', $value, $match) !== 1) {
			return new Target();
		}

		if(array_key_exists($match[1], self::getTypes()) === FALSE) {
			return new Target();
		}

		return new Target([
			'targetType' => $match[1],
			'targetId' => (int)$match[2]
		]);

	}

	/**
	 * Les rattachements que désignent des valeurs postées, rangés par valeur, chacun avec l'objet que son
	 * package rend pour la structure (`eElement`). Une valeur illisible, ou que le package ne rend pas
	 * pour cette structure, n'y est pas : l'appelant compare pour refuser.
	 */
	public static function getByValues(\structure\Structure $eStructure, array $values): \Collection {

		$cTarget = new \Collection();

		foreach($values as $value) {

			if(is_string($value) === FALSE) {
				continue;
			}

			$eTarget = self::getByValue($value);

			if($eTarget->notEmpty()) {
				$cTarget[$value] = $eTarget;
			}

		}

		self::putObjects($eStructure, $cTarget);

		return $cTarget->find(fn(Target $eTarget) => $eTarget['eElement']->notEmpty());

	}

	/**
	 * Pose sur chaque rattachement l'objet que son package rend pour la structure (`eElement`, vide quand
	 * l'objet n'existe plus). Une requête par type présent, quel que soit le nombre de rattachements.
	 */
	public static function putObjects(\structure\Structure $eStructure, \Collection $cTarget): void {

		$ids = [];

		foreach($cTarget as $eTarget) {
			$ids[$eTarget['targetType']][] = $eTarget['targetId'];
		}

		$objects = [];

		foreach($ids as $targetType => $targetIds) {
			$objects[$targetType] = NoteSetting::resolve($targetType, 'NoteTargetLib')::getByIds($eStructure, array_values(array_unique($targetIds)));
		}

		foreach($cTarget as $eTarget) {

			$cElement = $objects[$eTarget['targetType']];

			$eTarget['eElement'] = $cElement->offsetExists($eTarget['targetId']) ? $cElement[$eTarget['targetId']] : new \AnonymousElement();

		}

	}

	/**
	 * Les rattachements des notes d'une liste, en une requête pour toute la liste.
	 */
	public static function delegateByNote(): TargetModel {

		return Target::model()
			->select(['targetType', 'targetId'])
			->delegateCollection('note');

	}

	/**
	 * Pose sur les rattachements des notes leur objet, en une requête par type pour toutes les notes, et
	 * retire ceux dont l'objet n'existe plus : la note reste entière, son badge disparaît.
	 *
	 * Les rattachements sortent rangés par type, dans l'ordre des familles de l'omnibox, puis par le nom de
	 * leur objet : toute page qui montre des badges passe par ici, et une note les montre ainsi dans le même
	 * ordre partout. La requête qui les charge ne peut pas le donner, le nom vivant dans le package de l'objet.
	 */
	public static function putByNotes(\structure\Structure $eStructure, \Collection $cNote): void {

		$cTarget = new \Collection();

		foreach($cNote as $eNote) {
			foreach($eNote['cTarget'] as $eTarget) {
				$cTarget[] = $eTarget;
			}
		}

		self::putObjects($eStructure, $cTarget);

		self::sortByNotes($cNote);

	}

	/**
	 * Range les rattachements de chaque note dont les objets sont posés, par type puis par nom, et retire
	 * ceux dont l'objet n'existe plus.
	 */
	public static function sortByNotes(\Collection $cNote): void {

		$positions = array_flip(array_keys(self::getTypes()));

		foreach($cNote as $eNote) {

			$eNote['cTarget'] = $eNote['cTarget']
				->find(fn(Target $eTarget) => $eTarget['eElement']->notEmpty())
				->sort(fn(Target $eTarget1, Target $eTarget2) => (
					($positions[$eTarget1['targetType']] <=> $positions[$eTarget2['targetType']]) ?:
					\L::getCollator(TRUE)->compare($eTarget1['eElement']['name'], $eTarget2['eElement']['name'])
				));

		}

	}

	/**
	 * Ce à quoi sont rattachées les notes que le membre connecté lit dans la structure, une fois chacun,
	 * réduit à ce qui répond à la recherche : ce que propose le filtre de la liste. Une note qu'il ne lit
	 * pas ne lui apprend rien, pas même l'objet dont elle parle.
	 */
	public static function getReadableByStructure(\structure\Structure $eStructure, string $query): \Collection {

		// Deux requêtes et non une citation : la condition de lecture corrèle la table des lecteurs à
		// `m1.id`, l'alias de la note quand elle mène la requête, et une note citée prend celui de `s1`.
		$notes = NoteLib::applyReadable(new NoteModel(), $eStructure->getMember())->getColumn('id');

		if($notes === []) {
			return new \Collection();
		}

		$cTargetAttached = Target::model()
			->select(['targetType', 'targetId'])
			->whereStructure($eStructure)
			->whereNote('IN', $notes)
			->group(['targetType', 'targetId'])
			->getCollection();

		$ids = [];

		foreach($cTargetAttached as $eTarget) {
			$ids[$eTarget['targetType']][] = $eTarget['targetId'];
		}

		$cTarget = new \Collection();

		foreach($ids as $targetType => $targetIds) {

			$cElement = NoteSetting::resolve($targetType, 'NoteTargetLib')::search($eStructure, $query, NoteSetting::SEARCH_LIMIT, $targetIds);

			foreach($cElement as $eElement) {

				$eTarget = new Target([
					'targetType' => $targetType,
					'targetId' => $eElement['id'],
					'eElement' => $eElement
				]);

				$cTarget[$eTarget->getValue()] = $eTarget;

			}

		}

		return $cTarget;

	}

	/**
	 * Ce qu'on peut rattacher à une note de la structure qui répond à la recherche, type après type, dans
	 * l'ordre des packages.
	 */
	public static function search(\structure\Structure $eStructure, string $query): \Collection {

		$cTarget = new \Collection();

		foreach(self::getTypes() as $targetType => $package) {

			$cElement = NoteSetting::resolve($targetType, 'NoteTargetLib')::search($eStructure, $query, NoteSetting::SEARCH_LIMIT);

			foreach($cElement as $eElement) {

				$eTarget = new Target([
					'targetType' => $targetType,
					'targetId' => $eElement['id'],
					'eElement' => $eElement
				]);

				$cTarget[$eTarget->getValue()] = $eTarget;

			}

		}

		return $cTarget;

	}

	public static function deleteByNote(Note $eNote): void {

		$cTarget = Target::model()
			->select('id')
			->whereNote($eNote)
			->getCollection();

		foreach($cTarget as $eTarget) {
			self::delete($eTarget);
		}

	}

}
?>
