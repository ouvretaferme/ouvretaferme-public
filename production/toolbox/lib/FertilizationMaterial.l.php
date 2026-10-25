<?php
namespace toolbox;

/**
 * Les matières du calculateur des besoins en fertilisation. Comme les cultures, elles ne s'écrivent
 * que par le script `toolbox/configure/fertilizationReference`.
 */
class FertilizationMaterialLib extends FertilizationMaterialCrud {

	/**
	 * Les matières indexées par id, dans l'ordre que le script leur donne.
	 */
	public static function getList(): \Collection {

		return FertilizationMaterial::model()
			->select(FertilizationMaterial::getSelection() + [
				'source' => FertilizationSource::getSelection(),
			])
			->sort('position')
			->getCollection(index: 'id');

	}

}
?>
