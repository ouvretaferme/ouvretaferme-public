<?php
namespace structure;

class ProductionLib extends ProductionCrud {

	use \ModuleDeferred;

	public static function getPropertiesCreate(?string $origin = NULL): \Closure|array {

		return array_merge(['fqn'], self::getPropertiesUpdate($origin));

	}

	/**
	 * fqn décide des champs qu'un atelier accepte (cf Production::getOptions()) : il s'échange en
	 * supprimant l'atelier, pas en le renommant.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): \Closure|array {

		return [
			'area', 'areaGreenhouse', 'organic', 'startedAt', 'description',
			'mechanisation'
		];

	}

	public static function create(Production $e): void {

		$e->expects(['structure']);

		try {
			parent::create($e);
		} catch(\DuplicateException) {
			Production::fail('fqn.duplicate');
			return;
		}

		\Cache::redis()->delete(self::getCacheKey($e['structure']));

	}

	public static function delete(Production $e): void {

		$e->expects(['structure']);

		parent::delete($e);

		\Cache::redis()->delete(self::getCacheKey($e['structure']));

	}

	public static function createCollection(\Collection $cProduction): void {

		Production::model()->insert($cProduction);

	}

	public static function replaceCollection(Structure $eStructure, \Collection $cProduction): void {

		Production::model()->beginTransaction();

			Production::model()
				->whereStructure($eStructure)
				->delete();

			self::createCollection($cProduction);

		Production::model()->commit();

		\Cache::redis()->delete(self::getCacheKey($eStructure));

	}

	public static function update(Production $eProduction, array $properties): void {

		parent::update($eProduction, $properties);

		\Cache::redis()->delete(self::getCacheKey($eProduction['structure']));

	}

	public static function deferred(\structure\Structure $eStructure): \Collection {

		return \Cache::redis()->query(self::getCacheKey($eStructure), function() use ($eStructure) {

			return Production::model()
				->select(ProductionElement::getSelection())
				->whereStructure($eStructure)
				->sort([
					'id' => SORT_ASC
				])
				->getCollection(index: 'id');

		}, 86400);

	}

	public static function delegateByStructure(): ProductionModel {

		return Production::model()
			->select(Production::getSelection())
			->delegateCollection('structure');

	}

	public static function getByStructure(Structure $eStructure): \Collection {

		return self::deferred($eStructure);

	}

	/**
	 * ⚠️ Lit la table et non le cache de `deferred()`, tenu 24 h par structure : une lecture de
	 * cache par ferme d'un groupe ne vaut pas cette requête.
	 *
	 * ⚠️ `UNIQUE ["structure", "fqn"]` : une ferme porte plusieurs ateliers, et
	 * `getCollection(index: 'structure')` n'en garderait qu'un.
	 */
	public static function getByStructures(\Collection $cStructure): \Collection {

		if($cStructure->empty()) {
			return new \Collection();
		}

		$cProduction = Production::model()
			->select(Production::getSelection())
			->whereStructure('IN', $cStructure)
			->getCollection();

		$ccProduction = new \Collection();

		foreach($cProduction as $eProduction) {

			$structure = $eProduction['structure']['id'];

			if($ccProduction->offsetExists($structure) === FALSE) {
				$ccProduction[$structure] = new \Collection();
			}

			$ccProduction[$structure][] = $eProduction;

		}

		return $ccProduction;

	}

	public static function buildFromInput(Structure $eStructure, array $input, bool $errorIfEmpty): \Collection {

		$productions = (array)($input['fqn'] ?? []);

		if(count($productions) === 0) {

			if($errorIfEmpty) {
				Production::fail('createEmpty');
			}

			return new \Collection();
		}

		$cProduction = new \Collection();

		$positions = array_keys($productions);

		$properties = self::getPropertiesCreate();

		foreach($positions as $position) {

			if($input['fqn'][$position] === '') {
				continue;
			}

			$eProduction = new Production([
				'structure' => $eStructure
			]);

			$eProduction->buildIndex($properties, $input, $position, new \PropertyList('create'));

			$cProduction[$position] = $eProduction;

		}

		return $cProduction;

	}

	/**
	 * ⚠️ Publique parce qu'un écrivain hors Lib en a besoin : un script de `page/configure/` écrit
	 * en direct par le modèle — c'est la règle du dépôt — et contourne donc aussi les caches que
	 * les Libs entretiennent. Ceux-là ne se réparent pas seuls : la copie d'une ferme école a
	 * laissé pendant 24 h un cache vide sur des lignes bien écrites.
	 */
	public static function deleteCache(Structure $eStructure): void {
		\Cache::redis()->delete(self::getCacheKey($eStructure));
	}

	private static function getCacheKey(Structure $eStructure): string {
		return 'production-'.$eStructure['id'];
	}

}
