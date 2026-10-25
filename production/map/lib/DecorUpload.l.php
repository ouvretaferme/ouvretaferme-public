<?php
namespace map;

/**
 * Le modèle 3D qu'une structure téléverse pour en faire un bâtiment neuf de sa rangée. `DecorBuilding.upload` désigne
 * l'envoi montré, et un bâtiment qui en a un ne se modifie pas (`DecorBuilding::acceptUpdate()`) : on le supprime et on en
 * importe un autre. L'envoi et son fichier partent avec le bâtiment (`deleteByBuilding()`).
 */
class DecorUploadLib extends DecorUploadCrud {

	// Le profil des règles de conversion (`base/scene/upload/decor.json`) et la famille d'un bâtiment dans ce profil
	const string PROFILE = 'decor';
	const string FAMILY_BUILDING = 'building';

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['commitment', 'shared', 'file'];
	}

	/**
	 * Ce que l'éditeur d'un bâtiment neuf montre de l'envoi (`DecorUploadUi::getTab()`) : le drapeau, le poids que le dépôt
	 * annonce et juge avant l'envoi, et les triangles qu'il annonce.
	 */
	public static function fillEditor(DecorBuilding $eDecorBuilding): void {

		$rules = \scene\UploadLib::getModelRules(self::PROFILE, self::FAMILY_BUILDING);

		$eDecorBuilding['uploadEnabled'] = MapSetting::isDecorUploadEnabled($eDecorBuilding['structure']);
		$eDecorBuilding['uploadRules'] = [
			'maxBytes' => $rules['maxBytes'],
			'maxTriangles' => $rules['maxTriangles'],
		];

	}

	/**
	 * Le modèle que la scène monte à la place de la composition, null s'il n'y en a pas ou si la route du fichier le refuserait
	 * (`decorUpload:model`) : la scène ne demande pas un binaire qu'on ne lui servirait pas. L'adresse est celle de la route, jamais celle
	 * du stockage, publique pour qui connait le hash.
	 */
	public static function getScene(DecorUpload $e): ?array {

		if($e->empty() or $e->assertRead() === FALSE) {
			return NULL;
		}

		return [
			'url' => DecorUploadUi::url($e),
			'index' => $e['bakedIndex']
		];

	}

	/**
	 * Le bâtiment neuf, construit par l'appelant, se crée dans la même transaction que son envoi par
	 * `DecorBuildingLib::create()`, sous la composition qu'il garde sans la montrer : un refus de sa création
	 * (`structure.limit`, `decorChanged`) n'écrit aucun envoi.
	 */
	public static function create(DecorUpload $e): void {

		$e->expects(['structure', 'building', 'shared', 'rules', 'bakedIndex', 'bakedBuffer']);

		if($e['building']->exists()) {
			throw new \Exception('An upload only creates a new building');
		}

		$e['sharedAt'] = $e['shared'] ? \Clock::datetime() : NULL;

		DecorUpload::model()->beginTransaction();

			DecorBuildingLib::create($e['building']);

			if($e['building']->exists() === FALSE) {
				DecorUpload::model()->commit();
				return;
			}

			$e['hash'] = NULL;

			parent::create($e);

			$hash = NULL;
			new \media\MapDecorUploadLib()->send($e, $hash, $e['bakedBuffer'], 'bin');

			$e['building']['upload'] = $e;
			DecorBuildingLib::update($e['building'], ['upload']);

		DecorUpload::model()->commit();

	}

	/**
	 * Les envois d'un bâtiment qui quitte la rangée, lignes comprises : `building` n'est pas nullable, et un envoi sans
	 * bâtiment n'aurait plus rien à modérer. Dans la transaction de `DecorBuildingLib::delete()`, qui a verrouillé le bâtiment puis supprimé ses poses ;
	 * les fichiers quittent le disque à son COMMIT.
	 */
	public static function deleteByBuilding(DecorBuilding $eDecorBuilding): void {

		$cDecorUpload = DecorUpload::model()
			->select('id', 'hash')
			->whereBuilding($eDecorBuilding)
			->option('for-update')
			->getCollection();

		foreach($cDecorUpload as $eDecorUpload) {

			if($eDecorUpload['hash'] !== NULL) {
				new \media\MapDecorUploadLib()->deleteByHash($eDecorUpload['hash']);
			}

			self::delete($eDecorUpload);

		}

	}

}
?>
