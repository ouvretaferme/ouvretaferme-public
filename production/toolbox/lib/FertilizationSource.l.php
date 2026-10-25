<?php
namespace toolbox;

/**
 * Les documents cités par le référentiel du calculateur des besoins en fertilisation, lus avec ce qui
 * les cite (`FertilizationCropLib::getList()`, `FertilizationMaterialLib::getList()`) et écrits par le
 * seul script du référentiel.
 */
class FertilizationSourceLib extends FertilizationSourceCrud {

	/**
	 * Tous les documents du référentiel, par ordre alphabétique de leur libellé.
	 */
	public static function getList(): \Collection {

		return FertilizationSource::model()
			->select(FertilizationSource::getSelection())
			->sort(['name' => SORT_ASC])
			->getCollection(NULL, NULL, 'id');

	}

}
?>
