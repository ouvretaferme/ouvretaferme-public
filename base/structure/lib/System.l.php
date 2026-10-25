<?php
namespace structure;

class SystemLib extends SystemCrud {

	use \ModuleDeferred;

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure|array {

		$properties = [
			'place', 'placeLngLat',
			'totalArea', 'altitude', 'soil', 'ph',
			'sellingDirect', 'sellingSemi', 'sellingLong',
			'hrFarmer', 'hrEmployee', 'hrOther',
		];

		if($origin !== 'api') {

			// climate n'est pas exposé en lecture par l'API : modifiable, il serait écrivable et
			// jamais lisible, et un PUT qui l'omet le remettrait à NULL.
			$properties[] = 'climate';

			// productionsList est une propriété virtuelle dont le callback lit $_POST : elle n'a pas
			// d'équivalent dans un corps JSON. Les productions se modifient par leur propre module.
			$properties[] = 'productionsList';
		}

		return $properties;

	}

	/**
	 * Les mêmes, moins le temps de travail, pour le panneau de partage d'une ferme à un groupe.
	 *
	 * Le retrait vaut au-delà de l'affichage : ce qui n'est pas construit n'est pas écrit, et
	 * build() pose NULL sur une propriété qu'il construit sans la trouver dans le POST.
	 */
	public static function getPropertiesShare(): array {
		return array_values(array_diff(self::getPropertiesUpdate(), ['hrFarmer', 'hrEmployee', 'hrOther']));
	}

	public static function update(System $eSystem, array $properties): void {

		if(
			in_array('placeLngLat', $properties) and
			in_array('placeDepartment', $properties) === FALSE
		) {
			$properties = self::prepareCodes($eSystem, $properties);
		}

		System::model()->beginTransaction();

			if(array_delete($properties, 'productionsList')) {

				$eSystem->expects(['cProduction']);

				ProductionLib::replaceCollection($eSystem['structure'], $eSystem['cProduction']);

			}

			if($properties) {

				parent::update($eSystem, $properties);
				\Cache::redis()->delete(self::getCacheKey($eSystem['structure']));

			}

		System::model()->commit();


	}

	/**
	 * Pose sur le système la commune, le département et l'intercommunalité de son point, et les ajoute
	 * aux propriétés à écrire, NULL compris : un point déplacé ne garde jamais l'ancienne commune, et
	 * un `update()` qui reçoit `placeDepartment` sait que l'appel est déjà fait.
	 *
	 * L'appel à geo.api.gouv.fr peut durer jusqu'à 6 s, donc il se fait hors de toute transaction :
	 * `update()` l'appelle avant la sienne, et un appelant qui écrit le lieu dans sa propre
	 * transaction (la vue Carte) l'appelle avant de l'ouvrir. Son échec n'empêche pas d'enregistrer
	 * le lieu, les codes restant à NULL jusqu'au rattrapage de la nuit.
	 *
	 * La ligne se relit parce que `build()` a déjà posé le nouveau point sur l'élément : c'est elle
	 * qui dit si le point a bougé. Le formulaire du système, le partage et l'API renvoient le point à
	 * chaque enregistrement, et seul un point déplacé rappelle geo.api : un point inchangé resté sans
	 * département est hors de toute commune, ou son appel a échoué, et l'enregistrement n'attendrait
	 * jusqu'à 6 s que pour rendre la même réponse. C'est le rattrapage de la nuit
	 * (`structure/cron/placeCodesAi`, par `updateCodes()`) qui le retente.
	 */
	public static function prepareCodes(System $eSystem, array $properties): array {

		$eSystemStored = self::getForCodes($eSystem);

		// Un point inchangé garde ses codes, sauf ceux d'une structure passée à l'étranger, qu'on
		// retire sans appeler personne.
		if(
			self::isSamePoint($eSystemStored['placeLngLat'], $eSystem['placeLngLat']) and
			(self::isAbroad($eSystemStored) === FALSE or $eSystemStored['placeDepartment'] === NULL)
		) {
			return $properties;
		}

		self::prepareCodesByPoint($eSystem, $eSystemStored);

		return array_values(array_unique([...$properties, 'placeCity', 'placeDepartment', 'placeEpci']));

	}

	/**
	 * Redéduit les codes d'un point qui n'a pas bougé, ce que l'enregistrement ne fait jamais, et les
	 * écrit. L'appel dure jusqu'à 6 s : jamais dans une transaction.
	 */
	public static function updateCodes(System $eSystem): void {

		$eSystemStored = self::getForCodes($eSystem);

		// Le point se relit : un rattrapage dure jusqu'à une heure, et un lieu déplacé ou effacé
		// pendant ce temps garderait sinon les codes de son ancien point.
		$eSystem['placeLngLat'] = $eSystemStored['placeLngLat'];

		self::prepareCodesByPoint($eSystem, $eSystemStored);

		self::update($eSystem, ['placeCity', 'placeDepartment', 'placeEpci']);

	}

	private static function getForCodes(System $eSystem): System {

		return System::model()
			->select([
				'placeLngLat', 'placeDepartment',
				'structure' => ['producerType', 'legalCountry']
			])
			->whereId($eSystem['id'])
			->get();

	}

	/**
	 * geo.api ne connaît que les communes françaises : une structure déclarée à l'étranger n'a ni
	 * commune, ni département, ni intercommunalité à y lire, et l'appel ne ferait qu'attendre. Sans
	 * pays, la structure reste présumée française, comme partout ailleurs.
	 */
	private static function isAbroad(System $eSystemStored): bool {

		$eCountry = $eSystemStored['structure']['legalCountry'];

		return ($eCountry->notEmpty() and $eCountry->isFrance() === FALSE);

	}

	private static function prepareCodesByPoint(System $eSystem, System $eSystemStored): void {

		$codes = (self::isAbroad($eSystemStored) or $eSystem['placeLngLat'] === NULL) ? \main\PlaceLib::CODES_UNKNOWN : \main\PlaceLib::getCodesByLngLat($eSystem['placeLngLat']);

		$eSystem['placeDepartment'] = $codes['placeDepartment'];

		// Le point d'une ferme école est la préfecture du département de sa source
		// (`SchoolAiLib::placeDepartment()`) : sa commune désignerait une ville où la ferme n'est pas.
		if($eSystemStored['structure']['producerType'] === Structure::SCHOOL) {
			$eSystem['placeCity'] = NULL;
			$eSystem['placeEpci'] = NULL;
		} else {
			$eSystem['placeCity'] = $codes['placeCity'];
			$eSystem['placeEpci'] = $codes['placeEpci'];
		}

	}

	/**
	 * Le point lu en base et le point construit depuis un formulaire ne portent pas les mêmes types
	 * (flottants d'un côté, chaînes possibles de l'autre) : la comparaison se fait sur les valeurs.
	 */
	private static function isSamePoint(?array $lngLatStored, ?array $lngLat): bool {

		if($lngLatStored === NULL or $lngLat === NULL) {
			return ($lngLatStored === $lngLat);
		}

		return (
			(float)$lngLatStored[0] === (float)$lngLat[0] and
			(float)$lngLatStored[1] === (float)$lngLat[1]
		);

	}

	/**
	 * Les systèmes dont le point attend encore son département : jamais déduit, ou dont l'appel a
	 * échoué. Un point hors de France y reste, geo.api.gouv.fr n'y trouvant aucune commune.
	 */
	public static function getWithoutCodes(Structure $eStructure, int $limit): \Collection {

		return System::model()
			->select(System::getSelection())
			->wherePlaceLngLat('!=', NULL)
			->wherePlaceDepartment(NULL)
			// Une structure déclarée à l'étranger n'a aucun département à attendre de geo.api.
			->whereStructure('NOT IN', Structure::model()->select('id')->whereLegalCountry('NOT IN', \user\UserSetting::COUNTRY_FRANCE)->getSql())
			->whereStructure($eStructure, if: $eStructure->notEmpty())
			->sort('id')
			->getCollection(0, $limit, 'id');

	}

	public static function deferred(\structure\Structure $eStructure): \Collection {

		return \Cache::redis()->query(self::getCacheKey($eStructure), function() use ($eStructure) {

			return System::model()
				->select(SystemElement::getSelection())
				->whereStructure($eStructure)
				->getCollection(index: 'id');

		}, 86400);

	}

	public static function delegateByStructure(): SystemModel {

		return System::model()
			->select(System::getSelection())
			->delegateElement('structure');

	}

	public static function getByStructure(Structure $eStructure): System {

		return System::model()
			->select(System::getSelection())
			->whereStructure($eStructure)
			->get();

	}

	/**
	 * ⚠️ Lit la table et non le cache de `deferred()`, tenu 24 h par structure : une lecture de
	 * cache par ferme d'un groupe ne vaut pas cette requête.
	 */
	public static function getByStructures(\Collection $cStructure): \Collection {

		if($cStructure->empty()) {
			return new \Collection();
		}

		return System::model()
			->select(System::getSelection())
			->whereStructure('IN', $cStructure)
			->getCollection(index: 'structure');

	}

	public static function createForStructure(\structure\Structure $eStructure): void {

		$e = new System([
			'structure' => $eStructure
		]);

		self::create($e);

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
		return 'system-'.$eStructure['id'];
	}

}
