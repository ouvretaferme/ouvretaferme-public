<?php
namespace work;

class MethodLib extends MethodCrud {

	use \ModuleDeferred;

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'action'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function deferred(\structure\Structure $eStructure): \Collection {

		$callback = fn() => Method::model()
			->select([
				'id', 'name'
			])
			->whereStructure($eStructure)
			->getCollection(index: 'id');

		return self::getCache($eStructure['id'], $callback);

	}

	public static function getFromQuery(string $query, \structure\Structure $eStructure, Action $eAction, ?array $properties = []): \Collection {

		if($query !== '') {
			Method::model()->whereName('LIKE', '%'.$query.'%');
		}

		return Method::model()
			->select($properties ?: Method::getSelection())
			->whereStructure($eStructure)
			->whereAction($eAction)
			->getCollection()
			->sort('name', natural: TRUE);

	}

	public static function getForWork(\structure\Structure $eStructure, Action $eAction): \Collection {

		if($eAction->empty()) {
			return new \Collection();
		}

		return Method::model()
			->select([
				'id', 'name'
			])
			->whereStructure($eStructure)
			->whereAction($eAction)
			->getCollection()
			->sort('name', natural: TRUE);

	}

	public static function delete(Method $e): void {

		$e->expects(['id', 'structure', 'action']);

		Method::model()->beginTransaction();

			parent::delete($e);

			\series\Task::model()
				->whereStructure($e['structure'])
				->whereAction($e['action'])
				->where('JSON_CONTAINS(methods, \''.$e['id'].'\')')
				->update([
					'methods' => new \Sql(\series\Task::model()->pdo()->api->jsonRemove('methods', $e['id']))
				]);

			\sequence\Flow::model()
				->whereStructure($e['structure'])
				->whereAction($e['action'])
				->where('JSON_CONTAINS(methods, \''.$e['id'].'\')')
				->update([
					'methods' => new \Sql(\sequence\Flow::model()->pdo()->api->jsonRemove('methods', $e['id']))
				]);

		Method::model()->commit();

	}

}
?>
