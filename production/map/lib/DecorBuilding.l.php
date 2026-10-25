<?php
namespace map;

/**
 * Les bâtiments qu'une structure compose dans l'éditeur du décor, rangés dans sa rangée Bâtiments et posés sur le plan sous
 * le kit `batiment` (`Decor.building`). Le serveur ne compose rien : il garde le type, les cotes et les choix, que la scène
 * monte par `sceneCreateBuilding()` (`base/scene/static/js/building.js`).
 */
class DecorBuildingLib extends DecorBuildingCrud {

	const SELECTION_SCENE = ['id', 'type', 'name', 'length', 'width', 'height', 'settings'];

	/**
	 * Les choix de l'éditeur, par type, que `building.js` lit aussi par `SceneLoader` : une seule source pour ce que l'éditeur
	 * propose, ce que la scène sait monter et ce que le serveur accepte. Le fichier est le même pour toutes les structures,
	 * d'où une seule lecture par processus.
	 */
	private static ?array $choices = NULL;

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['type', 'name', 'length', 'width', 'height', 'settings'];
	}

	// Le type choisit les cotes et les choix : il ne change pas, un autre type est un autre bâtiment
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'length', 'width', 'height', 'settings'];
	}

	public static function getChoices(): array {

		self::$choices ??= json_decode(file_get_contents(\Package::getPath('scene').'/static/building/choices.json'), TRUE, flags: JSON_THROW_ON_ERROR);

		return self::$choices;

	}

	/**
	 * Une combinaison que le type écarte : chaque règle de `excluded` nomme des choix qui, pris ensemble, ne se composent pas.
	 */
	public static function isExcluded(string $type, array $settings): bool {

		foreach(self::getChoices()[$type]['excluded'] as $rule) {

			$matched = TRUE;

			foreach($rule as $key => $value) {

				if($settings[$key] !== $value) {
					$matched = FALSE;
					break;
				}

			}

			if($matched) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * La rangée de la structure, au format de la scène : un bâtiment par entrée, dans l'ordre où ils ont été composés, avec le
	 * modèle 3D que chacun montre à la place de sa composition.
	 */
	public static function getForScene(\structure\Structure $eStructure): array {

		$cDecorBuilding = DecorBuilding::model()
			->select(self::SELECTION_SCENE + [
				'upload' => ['structure' => \structure\StructureElement::getSelection(), 'bakedIndex', 'status', 'hash']
			])
			->whereStructure($eStructure)
			->sort(['id' => SORT_ASC])
			->getCollection();

		$buildings = [];

		foreach($cDecorBuilding as $eDecorBuilding) {
			$buildings[] = self::getScene($eDecorBuilding, $eDecorBuilding['upload']);
		}

		return $buildings;

	}

	/**
	 * Ce que `building.js` monte, ce que la rangée affiche, et le modèle 3D que la scène monte à sa place (`upload`, null sans
	 * modèle montrable). `imported` dit si le bâtiment a été importé, ce qui lui ôte « Modifier » (`DecorBuilding::acceptUpdate()`),
	 * même quand son modèle a été retiré : il se lit sur l'envoi joint, non vide dès que la colonne l'est. Un bâtiment composé
	 * n'en a jamais, d'où le défaut.
	 */
	public static function getScene(DecorBuilding $e, DecorUpload $eDecorUpload = new DecorUpload()): array {

		$e->expects(self::SELECTION_SCENE);

		return $e->extracts(self::SELECTION_SCENE) + [
			'imported' => $eDecorUpload->notEmpty(),
			'upload' => DecorUploadLib::getScene($eDecorUpload)
		];

	}

	/**
	 * Un bâtiment neuf entre dans la rangée de sa structure, sous `DECOR_BUILDING_MAX`. Un hangar personnalisé remplace dans la
	 * même transaction le hangar posé d'où l'éditeur s'est ouvert (`eDecor`), qui passe au kit `batiment` : relu ici sous
	 * verrou, il ne se convertit qu'une fois quand deux fenêtres enregistrent le même hangar.
	 */
	public static function create(DecorBuilding $e): void {

		$e->expects(['structure', 'eDecor']);

		DecorBuilding::model()->beginTransaction();

		// Première lecture de la transaction : deux créations simultanées se comptent l'une après l'autre
		\structure\Structure::model()
			->whereId($e['structure'])
			->getForUpdate();

		if(DecorBuilding::model()->whereStructure($e['structure'])->count() >= MapSetting::DECOR_BUILDING_MAX) {
			DecorBuilding::model()->commit();
			DecorBuilding::fail('structure.limit');
			return;
		}

		$eDecor = $e['eDecor'];

		if($eDecor->notEmpty()) {

			$found = Decor::model()
				->select(['kit'])
				->whereStructure($e['structure'])
				->whereKit('hangar')
				->option('for-update')
				->get($eDecor);

			if($found === FALSE) {
				DecorBuilding::model()->commit();
				DecorBuilding::fail('decorChanged');
				return;
			}

			// Le hangar converti pèse désormais le plafond d'un import : la scène doit encore le porter
			if(DecorLib::getTriangles($e['structure']) - DecorLib::getKitTriangles('hangar') + DecorLib::getKitTriangles('batiment') > MapSetting::DECOR_TRIANGLES) {
				DecorBuilding::model()->commit();
				DecorBuilding::fail('structure.triangles');
				return;
			}

		}

		parent::create($e);

		if($eDecor->notEmpty()) {

			// Sans `build()` : le kit et le bâtiment viennent du serveur, et le hangar converti a été relu sous verrou ci-dessus,
			// borné par la structure. Aucune valeur postée n'atteint cette écriture
			$eDecor['kit'] = 'batiment';
			$eDecor['building'] = $e;
			$eDecor['structure'] = $e['structure'];

			DecorLib::update($eDecor, ['kit', 'building']);

		}

		DecorBuilding::model()->commit();

	}

	public static function update(DecorBuilding $e, array $properties): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	/**
	 * Un bâtiment posé quitte aussi le plan : ses poses partent avec lui, puis ses envois, fichiers compris, dans la même
	 * transaction. Le bâtiment se verrouille le premier, comme le fait la pose (`DecorLib::create()`) : une pose validée
	 * entre-temps est vue et supprimée, et une pose qui attend le verrou ne trouve plus le bâtiment.
	 */
	public static function delete(DecorBuilding $e): void {

		$e->expects(['id', 'structure']);

		DecorBuilding::model()->beginTransaction();

			DecorBuilding::model()
				->select('id')
				->whereId($e)
				->whereStructure($e['structure'])
				->option('for-update')
				->get();

			DecorLib::deleteByBuilding($e);
			DecorUploadLib::deleteByBuilding($e);

			parent::delete($e);

		DecorBuilding::model()->commit();

		\media\MediaLib::deleteFiles();

	}

}
?>
