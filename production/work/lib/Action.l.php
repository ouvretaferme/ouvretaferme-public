<?php
namespace work;

class ActionLib extends ActionCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'categories', 'color'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure {
		return function(Action $e) {
			$e->expects(['fqn']);
			if($e->isProtected() === FALSE) {
				return ['name', 'categories', 'favorite', 'color', 'pace', 'soil'];
			} else {
				return ['name', 'favorite', 'color', 'pace'];
			}
		};
	}

	public static function duplicateForStructure(\structure\Structure $eStructure): \Collection {

		$cCategory = CategoryLib::duplicateForStructure($eStructure);

		$cAction = Action::model()
			->select(Action::getSelection())
			->whereStructure(NULL)
			->getCollection(index: 'id');

		$cAction->map(function(Action $eAction) use($eStructure, $cCategory) {

			$eAction['id'] = NULL;
			$eAction['structure'] = $eStructure;

			foreach($eAction['categories'] as $key => $category) {
				$eAction['categories'][$key] = $cCategory[$category]['id'];
			}

		});

		Action::model()->insert($cAction);

		return $cAction;

	}

	public static function countByStructure(\structure\Structure $eStructure): array {

		return Action::model()
			->select([
				Action::ACTIVE => new \Sql('SUM(status = "'.Action::ACTIVE.'")', 'int'),
				Action::INACTIVE => new \Sql('SUM(status = "'.Action::INACTIVE.'")', 'int')
			])
			->whereStructure($eStructure)
			->get()
			->getArrayCopy() ?: [Action::ACTIVE => 0, Action::INACTIVE => 0];

	}

	public static function getByStructure(\structure\Structure $eStructure, mixed $id = NULL, array|string|null $fqn = NULL, Category|string $category = new Category(), ?string $index = NULL, \Search $search = new \Search()): \Collection|Action {

		$expects = 'collection';

		if(is_string($category)) {
			$eCategory = CategoryLib::getByStructure($eStructure, fqn: $category);
		} else {
			$eCategory = $category;
		}

		if($eCategory->notEmpty()) {
			Action::model()->where('JSON_CONTAINS(categories, \''.$eCategory['id'].'\')');
		}

		if($id !== NULL) {
			if(is_array($id)) {
				Action::model()->whereId('IN', $id);
			} else {
				$expects = 'element';
				Action::model()->whereId($id);
			}
		}

		if($fqn !== NULL) {
			if(is_string($fqn)) {
				$expects = 'element';
				Action::model()->whereFqn($fqn);
			} else {
				Action::model()->whereFqn('IN', $fqn);
			}
		}

		if($search->get('name')) {
			Action::model()->whereName('LIKE', '%'.$search->get('name').'%');
		}

		if($search->get('status')) {
			Action::model()->whereStatus($search->get('status'));
		} else {
			Action::model()->whereStatus(Action::ACTIVE);
		}

		Action::model()
			->select(Action::getSelection())
			->whereStructure($eStructure)
			->sort(['name' => SORT_ASC]);

		if($expects === 'element') {
			return Action::model()->get();
		} else {
			return Action::model()->getCollection(NULL, NULL, $index);
		}

	}

	public static function getMainByStructure(\structure\Structure $eStructure): \Collection {

		if(WorkSetting::$mainActions !== NULL) {
			return WorkSetting::$mainActions;
		}

		$cAction = Action::model()
			->select(['id', 'fqn', 'color', 'categories', 'soil'])
			->whereStructure($eStructure)
			->or(
				fn() => $this->whereFqn('IN', [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION, ACTION_RECOLTE]),
				fn() => $this->whereSoil(TRUE)
			)
			->getCollection(index: 'fqn');

		WorkSetting::$mainActions = $cAction;

		return $cAction;

	}

	public static function saveMain(\Collection $cAction): void {

		WorkSetting::$mainActions ??= $cAction
			->find(fn($eAction) => (
				in_array($eAction['fqn'], [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION, ACTION_RECOLTE]) or
				$eAction['soil']
			), indexByKey: 'fqn');

	}

	public static function getForManage(\structure\Structure $eStructure, \Search $search = new \Search()): \Collection|Action {

		Action::model()
			->select([
				'cMethod' => Method::model()
					->select(['id', 'name'])
					->delegateCollection('action'),
				'tasks' => \series\Task::model()
					->group('action')
					->delegateProperty('action', new \Sql('COUNT(*)', 'int')),
				'flows' => \sequence\Flow::model()
					->group('action')
					->delegateProperty('action', new \Sql('COUNT(*)', 'int'))

			]);

		return self::getByStructure($eStructure, search: $search);

	}

	public static function getByStructureWithPace(\structure\Structure $eStructure): \Collection|Action {

		return Action::model()
			->select(Action::getSelection())
			->whereStructure($eStructure)
			->wherePace('!=', NULL)
			->sort(['name' => SORT_ASC])
			->getCollection();

	}

	public static function canUse(Action $eAction, \structure\Structure $eStructure): bool {

		return (
			$eAction->empty() === FALSE and
			Action::model()
				->select(Action::getSelection()) // Requis pour traitements futurs
				->whereStructure($eStructure)
				->get($eAction)
		);

	}

	public static function update(Action $e, array $properties): void {

		Action::model()->beginTransaction();

			if(
				in_array('soil', $properties) and
				$e['oldSoil'] !== $e['soil']
			) {

				if($e['soil']) {
					$e['fqn'] = $e['id'];
				} else {
					$e['fqn'] = NULL;
				}

				$properties[] = 'fqn';

			}

			parent::update($e, $properties);

			if(
				in_array('soil', $properties) and
				$e['oldSoil'] !== $e['soil']
			) {

				$cSeries = \series\Task::model()
					->select([
						'series' => \series\SeriesElement::getSelection()
					])
					->whereStructure($e['structure'])
					->whereSeason('>=', \Clock::year())
					->whereAction($e)
					->group('series')
					->getColumn('series');

				foreach($cSeries as $eSeries) {
					\series\SeriesLib::recalculate($eSeries['structure'], $eSeries);
				}

			}

		Action::model()->commit();

	}

	public static function delete(Action $e): void {

		$e->expects(['id', 'fqn', 'structure']);

		if($e->isProtected()) {
			Action::fail('deleteMandatory');
			return;
		}

		if(\series\Task::model()
				->whereAction($e)
				->exists() or \sequence\Flow::model()
				->whereAction($e)
				->exists()) {
			Action::fail('deleteUsed');
			return;
		}


		Tool::model()
			->whereStructure($e['structure'])
			->whereAction($e)
			->update([
				'action' => new Action()
			]);

		parent::delete($e);

	}

}
?>
