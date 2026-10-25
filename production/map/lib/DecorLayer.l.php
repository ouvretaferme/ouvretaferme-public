<?php
namespace map;

class DecorLayerLib extends DecorLayerCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['type', 'name'];
	}

	public static function getByStructure(\structure\Structure $eStructure): \Collection {

		return DecorLayer::model()
			->select(['id', 'name', 'type', 'position'])
			->whereStructure($eStructure)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Un calque neuf prend la place qui suit le dernier : la carte les montre dans l'ordre de leur création.
	 */
	public static function create(DecorLayer $e): void {

		$e->expects(['structure']);

		$positions = DecorLayer::model()
			->whereStructure($e['structure'])
			->getColumn('position');

		if(count($positions) >= MapSetting::DECOR_LAYER_MAX) {
			DecorLayer::fail('structure.limit');
			return;
		}

		$e['position'] = ($positions === []) ? 0 : max($positions) + 1;

		parent::create($e);

	}

	/**
	 * Un calque s'en va avec ses éléments, chacun par `DecorLib::delete()`, tout ou rien, et ceux qui le
	 * suivent remontent d'une place. Un élément posé
	 * dans le calque pendant sa suppression peut lui survivre : la scène le range alors dans le calque
	 * de base.
	 */
	public static function delete(DecorLayer $e): void {

		$e->expects(['id', 'structure']);

		DecorLayer::model()->beginTransaction();

		$cDecor = Decor::model()
			->select('id')
			->whereStructure($e['structure'])
			->whereLayer($e)
			->getCollection();

		foreach($cDecor as $eDecor) {
			DecorLib::delete($eDecor);
		}

		parent::delete($e);

		// Les calques restants se renumérotent de 0 à n - 1 : la position reste bornée par DECOR_LAYER_MAX
		$position = 0;

		foreach(self::getByStructure($e['structure']) as $eDecorLayer) {

			if($eDecorLayer['position'] !== $position) {
				$eDecorLayer['position'] = $position;
				self::update($eDecorLayer, ['position']);
			}

			$position++;

		}

		DecorLayer::model()->commit();

	}

}
?>
