<?php
namespace map;

/**
 * Les éléments qu'une structure importe dans les rangées Eau, Nature et Matériel de son décor, posés sur le plan sous le kit
 * de leur rangée (`DecorObject::KITS`, `Decor.object`). Un élément n'a pas de composition de repli : il naît de son envoi
 * (`DecorUploadLib::createByObject()`), et la scène monte le modèle de l'envoi qu'il cite (`upload`).
 */
class DecorObjectLib extends DecorObjectCrud {

	const SELECTION_SCENE = ['id', 'family', 'name'];

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['family', 'name'];
	}

	/**
	 * Ce que le panneau d'un élément neuf montre de l'envoi (`DecorObjectUi::create()`) : le poids que le dépôt annonce et juge
	 * avant l'envoi, et les triangles de la famille, qu'il annonce.
	 */
	public static function fillEditor(DecorObject $e): void {

		$e->expects(['family']);

		$rules = \scene\UploadLib::getModelRules(DecorUploadLib::PROFILE, $e['family']);

		$e['uploadRules'] = [
			'maxBytes' => $rules['maxBytes'],
			'maxTriangles' => $rules['maxTriangles'],
		];

	}

	/**
	 * Les rangées de la structure, au format de la scène : un élément par entrée, dans l'ordre où ils ont été importés, avec
	 * le modèle 3D qu'il montre.
	 */
	public static function getForScene(\structure\Structure $eStructure): array {

		$cDecorObject = DecorObject::model()
			->select(self::SELECTION_SCENE + [
				'upload' => ['structure' => \structure\StructureElement::getSelection(), 'bakedIndex', 'status', 'hash']
			])
			->whereStructure($eStructure)
			->sort(['id' => SORT_ASC])
			->getCollection();

		$objects = [];

		foreach($cDecorObject as $eDecorObject) {
			$objects[] = self::getScene($eDecorObject, $eDecorObject['upload']);
		}

		return $objects;

	}

	/**
	 * Ce que la rangée affiche et le modèle que la scène monte (`upload`, null sans modèle montrable : ses poses se dessinent
	 * alors sous une forme neutre).
	 */
	public static function getScene(DecorObject $e, DecorUpload $eDecorUpload = new DecorUpload()): array {

		$e->expects(self::SELECTION_SCENE);

		return $e->extracts(self::SELECTION_SCENE) + [
			'upload' => DecorUploadLib::getScene($eDecorUpload)
		];

	}

	/**
	 * Un élément neuf entre dans les rangées de sa structure, sous `DECOR_OBJECT_MAX`, dans la transaction de son envoi
	 * (`DecorUploadLib::createByObject()`) : un refus n'écrit ni l'élément ni l'envoi.
	 */
	public static function create(DecorObject $e): void {

		$e->expects(['structure']);

		DecorObject::model()->beginTransaction();

		// Première lecture de la transaction : deux créations simultanées se comptent l'une après l'autre
		\structure\Structure::model()
			->whereId($e['structure'])
			->getForUpdate();

		if(DecorObject::model()->whereStructure($e['structure'])->count() >= MapSetting::DECOR_OBJECT_MAX) {
			DecorObject::model()->commit();
			DecorObject::fail('structure.limit');
			return;
		}

		parent::create($e);

		DecorObject::model()->commit();

	}

	public static function update(DecorObject $e, array $properties): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	/**
	 * Un élément posé quitte aussi le plan : ses poses partent avec lui, puis ses envois, fichiers compris, dans la même
	 * transaction. L'élément se verrouille le premier, comme le fait la pose (`DecorLib::create()`) : une pose validée
	 * entre-temps est vue et supprimée, et une pose qui attend le verrou ne trouve plus l'élément.
	 */
	public static function delete(DecorObject $e): void {

		$e->expects(['id', 'structure']);

		DecorObject::model()->beginTransaction();

			DecorObject::model()
				->select('id')
				->whereId($e)
				->whereStructure($e['structure'])
				->option('for-update')
				->get();

			DecorLib::deleteByObject($e);
			DecorUploadLib::deleteByObject($e);

			parent::delete($e);

		DecorObject::model()->commit();

		\media\MediaLib::deleteFiles();

	}

}
?>
