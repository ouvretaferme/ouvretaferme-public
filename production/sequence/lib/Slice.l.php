<?php
namespace sequence;

class SliceLib extends SliceCrud {

	public static function delegateByCrop(): SliceModel {

		return Slice::model()
			->select([
				// Une collection fille est chargée par sa propre clé étrangère : sans `id`, chaque
				// élément rend NULL et une notice, le parent n'ayant rien qui la désigne.
				'id',
				'variety' => \plant\Variety::getSelection(),
				'partPercent'
			])
			->delegateCollection('crop', callback: fn($c) => $c->sort(['variety' => ['name']], natural: TRUE));

	}

	public static function createCollection(\Collection $c): void {

		$c->map(fn($e) => self::prepareCreate($e));

		try {
			Slice::model()->insert($c);
		} catch(\DuplicateException) {
			Crop::fail('variety.duplicate');
		}

	}

	public static function create(Slice $e): void {

		self::prepareCreate($e);

		parent::create($e);

	}

	private static function prepareCreate(Slice $e): void {

		$e->expects([
			'crop' => ['structure', 'plant', 'sequence']
		]);

		// Transfert de propriétés
		$e['structure'] = $e['crop']['structure'];
		$e['plant'] = $e['crop']['plant'];
		$e['sequence'] = $e['crop']['sequence'];

	}

	private static function getVarietyById(Crop|\series\Cultivation $eCrop, string $varietyId, string $wrapper): \plant\Variety {

		$eCrop->expects(['plant', 'structure']);

		$eVariety = \plant\Variety::model()
			->select(\plant\Variety::getSelection())
			->whereId($varietyId)
			->or(
				fn() => $this->whereStructure($eCrop['structure']),
				fn() => $this->whereStructure(NULL),
			)
			->get();

		if($eVariety->empty()) {
			throw new \FailException('sequence\Crop::variety.notExists', wrapper: $wrapper);
		}

		if($eVariety['plant']->empty()) {
			return $eVariety;
		}

		if($eVariety['plant']->is($eCrop['plant'])) {
			return $eVariety;
		}

		$ePlantReference = \plant\VarietyNameAiLib::getPlantReference($eCrop['plant']);

		if(
			$eVariety['structure']->notEmpty() or
			$ePlantReference->empty() or
			$eVariety['plant']->is($ePlantReference) === FALSE
		) {
			throw new \FailException('sequence\Crop::variety.notExists', wrapper: $wrapper);
		}

		$eVarietyStructure = \plant\Variety::model()
			->select(\plant\Variety::getSelection())
			->whereStructure($eCrop['structure'])
			->whereFqn($eVariety['fqn'])
			->get();

		if($eVarietyStructure->notEmpty()) {
			return $eVarietyStructure;
		}

		return new \plant\Variety([
			'name' => $eVariety['name'],
			'fqn' => $eVariety['fqn'],
			'reference' => $eVariety,
			'plant' => $eCrop['plant'],
			'structure' => $eCrop['structure'],
		]);

	}

	private static function getVarietyByCreate(Crop|\series\Cultivation $eCrop, string $name): \plant\Variety {

		$eCrop->expects(['plant', 'structure']);

		// On vérifie par acquit de conscience s'il n'en n'existe pas une du même nom
		$eVariety = \plant\Variety::model()
			->select(\plant\Variety::getSelection())
			->whereName($name)
			->wherePlant($eCrop['plant'])
			->or(
				fn() => $this->whereStructure($eCrop['structure']),
				fn() => $this->whereStructure(NULL),
			)
			->get();

		if($eVariety->notEmpty()) {
			return $eVariety;
		}

		return new \plant\Variety([
			'name' => $name,
			'plant' => $eCrop['plant'],
			'structure' => $eCrop['structure'],
		]);

	}

	public static function createVariety(\Collection $cSlice) {

		foreach($cSlice as $eSlice) {

			$eVariety = $eSlice['variety'];

			if(
				$eVariety->notEmpty() and
				$eVariety->offsetExists('id') === FALSE
			) {

				\plant\VarietyLib::setReference($eVariety);

				try {
					\plant\Variety::model()->insert($eVariety);
				}
					// Variété déjà créée en amont, peuplement de l'ID nécessaire pour continuer les traitements et pas d'erreur de duplicate
				catch(\DuplicateException) {

					\plant\Variety::model()
						->select(\plant\Variety::getSelection())
						->whereStructure($eVariety['structure'])
						->wherePlant($eVariety['plant'])
						->whereName($eVariety['name'])
						->get($eVariety);

					// La relecture ne voit pas toujours la ligne qui vient de causer le
					// doublon : en REPEATABLE READ, une requête concurrente qui a commité
					// après l'ouverture de la transaction courante est invisible ici, alors
					// que l'unique (structure, plant, name) l'a bien vue. La variété reste
					// alors sans id, et la slice partait en base avec variety NULL — erreur
					// SQL à l'écran, sur une création que l'autre requête a déjà menée à
					// bien. Cas vu en prod le 22/08/2026 sur un double envoi du formulaire.
					if($eVariety->offsetExists('id') === FALSE) {
						throw new \FailException('sequence\Crop::variety.concurrent');
					}

				}
			}

		}

	}

	public static function prepare(Crop|\series\Cultivation $eCrop, array $input, string $wrapper): \Collection {

		$eCrop ->expects(['plant', 'structure']);

		// La structure/structure peut être enregistré sous deux clés différentes selon que $eCrop est un Crop ou un Cultivation
		if($eCrop instanceof Crop) {
			$unit = \series\Cultivation::PERCENT;
		} else {
			$eCrop->expects(['varietiesUnit']);
			$unit = $eCrop['varietiesUnit'];
		}

		if(
			array_key_exists('variety', $input) === FALSE or
			array_key_exists('varietyCreate', $input) === FALSE or
			array_key_exists('varietyPart'.ucfirst($unit), $input) === FALSE
		) {
			throw new \FailException('sequence\Crop::varieties.check', wrapper: $wrapper);
		}

		$cSlice = new \Collection();

		foreach((array)$input['variety'] as $key => $varietyId) {

			// Rien n'est renseigné
			if($varietyId === '') {
				$varietyId = (string)\plant\PlantSetting::VARIETY_UNKNOWN;
			}

			// Nouvelle variété
			if($varietyId === 'new') {

				$varietyCreate = $input['varietyCreate'][$key] ?? '';

				if($varietyCreate === '') {
					throw new \FailException('sequence\Crop::variety.createEmpty');
				}

				$fw = new \FailWatch();

				$eVariety = new \plant\Variety();
				$eVariety->build(['name'], ['name' => $varietyCreate]);

				if($fw->ko()) {
					throw new \FailException('sequence\Crop::variety.createName');
				}

				$eVariety = self::getVarietyByCreate($eCrop, $varietyCreate);

				// Variété déjà existante
			} else {

				$eVariety = self::getVarietyById($eCrop, $varietyId, $wrapper);

			}

			if($unit === \series\Cultivation::TRAY) {
				$part = (float)($input['varietyPartTray'][$eCrop['varietiesTool']['id']][$key] ?? 0.0);
			} else {
				$part = (int)($input['varietyPart'.ucfirst($unit)][$key] ?? 0);
			}

			if($eCrop instanceof Crop) {
				$eSlice = new Slice([
					'crop' => $eCrop
				]);
			} else {
				$eSlice = new \series\Slice([
					'cultivation' => $eCrop
				]);
			}

			$eSlice['variety'] = $eVariety;
			$eSlice['part'.ucfirst($unit)] = $part;

			$cSlice[] = $eSlice;

		}

		// Une seule variété -> les parts sont automatiquement remplies
		foreach($cSlice as $eSlice) {

			$part = $eSlice['part'.ucfirst($unit)];

			if($part <= 0) {
				throw new \FailException('sequence\Crop::variety.partZero', wrapper: $wrapper);
			}

		}

		return $cSlice;

	}

	public static function deleteByCrop(Crop $eCrop): void {

		Slice::model()
			->whereCrop($eCrop)
			->delete();

	}

}
?>
