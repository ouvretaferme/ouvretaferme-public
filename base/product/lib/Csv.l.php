<?php
namespace product;

class CsvLib {

	public static function uploadProducts(\structure\Structure $eStructure): bool {

		return \main\CsvLib::upload('import-products-'.$eStructure['id'], function($csv) {

			if(
				count(array_intersect($csv[0], ['type', 'name', 'vat_rate'])) === 3 and
				count(array_intersect($csv[0], ['price_private', 'price_pro'])) > 0
			) {
				$csv = self::convertProducts($csv);
				if($csv === NULL) {
					return NULL;
				}
			} else {
				\Fail::log('main\csvSource');
				return NULL;
			}

			return $csv;

		});

	}

	public static function convertProducts(array $products): ?array {

		$import = [];

		$head = array_shift($products);

		foreach($products as $product) {

			if(count($product) < count($head)) {
				$product = array_merge($product, array_fill(0, count($head) - count($product), ''));
			} else if(count($head) < count($product)) {
				$product = array_slice($product, 0, count($head));
			}

			// Rétrocompatibilité
			$qualityKey = array_find_key($head, fn($value) => $value === 'quality');
			if($qualityKey !== NULL) {
				$head[$qualityKey] = 'marks';
			}

			$line = array_combine($head, $product) + [
				'type' => '',
				'name' => '',
				'unit' => '',
				'price_private' => '',
				'price_pro' => '',
				'vat_rate' => '',
				'distribution' => '',
				'additional' => '',
				'reference' => '',
				'origin' => '',
				'marks' => '',
				'species' => '',
				'variety' => '',
				'frozen' => '',
				'packaging' => '',
				'composition' => '',
				'allergen' => '',
				'description' => '',
			];

			$description = $line['description'];

			if($description) {

				$description = encode($description);

				$formattedDescription = new \editor\XmlLib()->fromHtml('<p>'.implode('</p><p>', preg_split("/(\r?\n)+/s", $description)).'</p>');
			} else {

				$formattedDescription = NULL;

			}

			$import[] = [
				'type' => $line['type'] ?: NULL,
				'name' => $line['name'] ?: NULL,
				'unit' => $line['unit'] ?: NULL,
				'price_private' => ($line['price_private'] !== '') ? \main\CsvLib::formatFloat($line['price_private']) : NULL,
				'price_pro' => ($line['price_pro'] !== '') ? \main\CsvLib::formatFloat($line['price_pro']) : NULL,
				'vat_rate' => \main\CsvLib::formatFloat($line['vat_rate']),
				'additional' => $line['additional'] ?: NULL,
				'distribution' => $line['distribution'] ?: NULL,
				'reference' => $line['reference'] ?: NULL,
				'origin' => $line['origin'] ?: NULL,
				'marks' => trim($line['marks']) ? preg_split('/\s*,\s*/', trim($line['marks'])) : [],
				'species' => $line['species'] ?: NULL,
				'variety' => $line['variety'] ?: NULL,
				'frozen' => (($line['frozen'] ?? 'no') === 'yes'),
				'packaging' => $line['packaging'] ?: NULL,
				'composition' => $line['composition'] ?: NULL,
				'allergen' => $line['allergen'] ?: NULL,
				'description' => $formattedDescription
			];

		}

		return $import;

	}

	public static function resetProducts(\structure\Structure $eStructure): bool {

		return \Cache::redis()->delete('import-products-'.$eStructure['id']);

	}

	public static function importProducts(\structure\Structure $eStructure, array $products): bool {

		$fw = new \FailWatch();

		$cProduct = new \Collection();

		foreach($products as $product) {

			$eProduct = $product['eProduct']->merge(([
				'unit' => $product['eUnit'],
				'profile' => $product['profile'],
				'name' => $product['name'],
				'private' => ($product['price_private'] !== NULL),
				'privatePrice' => $product['price_private'],
				'privatePriceInitial' => NULL,
				'pro' => ($product['price_pro'] !== NULL),
				'proPrice' => $product['price_pro'],
				'proPriceInitial' => NULL,
				'vat' => $product['vat'],
				'distribution' => $product['distribution'],
				'additional' => $product['additional'],
				'origin' => $product['origin'],
				'organic' => $product['organic'],
				'qualities' => $product['qualities'],
				'plant' => $product['ePlant'],
				'variety' => $product['variety'],
				'frozen' => $product['frozen'],
				'packaging' => $product['packaging'],
				'ingredients' => $product['composition'],
				'allergens' => $product['allergen'],
				'description' => $product['description'],
				'duplicateOf' => new Product()
			]));

			if($eProduct['profile'] === Product::COMPOSITION) {
				$eProduct['compositionVisibility'] = Product::PUBLIC;
			}

			$cProduct[] = $eProduct;

		}

		if(self::resetProducts($eStructure)) {

			Product::model()->beginTransaction();

			foreach($cProduct as $eProduct) {

				if(
					$eProduct['reference'] !== NULL and
					Product::model()
						->select(['id', 'variantOf', 'variants', 'priceMode'])
						->whereStructure($eStructure)
						->whereReference($eProduct['reference'])
						->get($eProduct)
				) {

					ProductLib::update($eProduct, [
						'name',
						'private', 'privatePrice', 'pro', 'proPrice', 'vat',
						'profile', 'distribution', 'additional', 'origin', 'organic', 'qualities',
						'plant', 'variety', 'frozen', 'packaging', 'ingredients', 'allergens'
					]);

				} else {

					ProductLib::create($eProduct);

				}

			}

			if($fw->ko()) {
				Product::model()->rollBack();
				return FALSE;
			}

			Product::model()->commit();

		}

		return TRUE;

	}

	public static function getProductProfiles(): array {
		return [
			Product::PLANT => 'plant',
			Product::ANIMAL => 'animal',
			Product::COMPOSITION => 'composition',
			Product::FOOD => 'food',
			Product::PRODUCT => 'product',
			Product::SERVICE => 'service',
		];
	}

	public static function getExportProducts(\structure\Structure $eStructure): array {

		$data = Product::model()
			->select(ProductElement::getSelection() + [
				'plant' => ['name'],
				'category' => ['name'],
				'unit' => Unit::getSelection(),
			])
			->whereStructure($eStructure)
			->whereStatus(Product::ACTIVE)
			->whereVariants(0)
			->sort('name')
			->getCollection()
			->makeArray(function($eProduct) use($eStructure) {

				$marks = match($eProduct['organic']) {
					Product::YES => ['organic'],
					Product::CONVERSION => ['conversion'],
					Product::NO => []
				};

				foreach(Product::model()->getPropertySet('qualities') as $quality) {

					if($eProduct['qualities']->value($quality)) {
						$marks[] = Product::getQualities()[$quality];
					}

				}

				return [
					self::getProductProfiles()[$eProduct['profile']] ?? $eProduct['profile'],
					$eProduct['name'],
					$eProduct['reference'],
					$eProduct['unit']->empty() ? '' : $eProduct['unit']['singular'],
					($eProduct['privatePrice'] !== NULL) ? \util\TextUi::csvNumber($eProduct['privatePrice']) : '',
					($eProduct['proPrice'] !== NULL) ? \util\TextUi::csvNumber($eProduct['proPrice']) : '',
					$eStructure->conf()['hasVat'] ? \util\TextUi::csvNumber(ProductSetting::getVatRate($eStructure, $eProduct['vat'])) : '',
					$eProduct['distribution'],
					$eProduct['additional'],
					$eProduct['origin'],
					implode(', ', $marks),
					$eProduct['plant']->empty() ? '' : $eProduct['plant']['name'],
					$eProduct['variety'],
					$eProduct['frozen'],
					$eProduct['packaging'],
					$eProduct['ingredients'],
					$eProduct['allergens'],
				];
			});

		return $data;

	}

	public static function getProducts(\structure\Structure $eStructure): ?array {

		$import = \Cache::redis()->get('import-products-'.$eStructure['id']);

		if($import === FALSE) {
			return NULL;
		}

		$units = [];
		foreach(UnitLib::getByStructure($eStructure) as $eUnit) {
			$units[mb_strtolower($eUnit['singular'])] = $eUnit;
		}

		$vatRates = ProductSetting::getVatRates($eStructure);

		$errorsCount = 0;
		$errorsGlobal = [
			'vatRates' => [],
			'units' => [],
			'species' => [],
			'references' => [],
			'profiles' => [],
		];
		$infoGlobal = [
			'references' => [],
		];

		$cachePlants = [];

		$references = [];

		foreach($import as $key => $product) {

			$eProduct = new Product([
				'structure' => $eStructure,
			]);

			$errors = [];
			$warnings = [];
			$ignore = FALSE;

			$profile = $product['type'];

			if($product['type'] === NULL) {
				$errors[] = 'profileMissing';
			}
			// Valeur de l'enum
			else if(in_array($product['type'], Product::getProfiles('import'))) {
				// OK
			}
			// Valeur humainement compréhensible
			else if(in_array($product['type'], self::getProductProfiles())) {
				$profile = array_search($product['type'], self::getProductProfiles());
			} else {
				$errors[] = 'profileInvalid';
				$errorsGlobal['profiles'][] = $product['type'];
			}

			$import[$key]['profile'] = $profile;

			if($product['name'] === NULL) {
				$errors[] = 'nameMissing';
			}

			$l = new \PropertyList();

			$properties = ['reference'];

			$eProduct->build($properties, [
				'reference' => $product['reference'],
			], $l);

			foreach($properties as $property) {

				if($l->isBuilt($property) === FALSE) {
					$errors[] = $property.'Error';
				} else {
					if($eProduct['reference'] !== NULL) {
						$references[] = $eProduct['reference'];
					}
				}

			}

			$import[$key]['eUnit'] = new Unit();

			if($product['unit'] !== NULL) {

				$unit = mb_strtolower($product['unit']);

				if(array_key_exists($unit, $units)) {
					$import[$key]['eUnit'] = $units[$unit];
				} else {
					$errorsGlobal['units'][] = $product['unit'];
					$errors[] = 'unitInvalid';
				}

			}

			if($product['vat_rate'] !== NULL) {

				$vat = array_find_key($vatRates, fn($value) => (float)$value === $product['vat_rate']);

				$import[$key]['vat'] = $vat;

				if($vat === NULL) {
					$errorsGlobal['vatRates'][] = $product['vat_rate'];
					$errors[] = 'vatRateInvalid';
				}

			} else {
				$import[$key]['vat'] = \first($vatRates);
			}

			$import[$key]['ePlant'] = new \plant\Plant();

			if($product['species'] !== NULL) {

				if(in_array($profile, Product::getProfiles('plant'))) {

					$plantFqn = \toFqn($product['species'], ' ');

					if(empty($cachePlants[$plantFqn])) {

						$cachePlants[$plantFqn] = \plant\Plant::model()
							->select(['id', 'vignette', 'fqn', 'name', 'cycle'])
							->whereStructure($eStructure)
							->whereStatus(\plant\Plant::ACTIVE)
							->or(
								 fn() => $this->whereName($product['species']),
								 fn() => $this->where('REGEXP_REPLACE(REPLACE(name, "-", " "), " +", " ") = '.\plant\Plant::model()->format($plantFqn))
							)
							->get();

					}

					$import[$key]['ePlant'] = $cachePlants[$plantFqn];

					if($cachePlants[$plantFqn]->empty()) {
						$errorsGlobal['species'][] = $product['species'];
						$errors[] = 'speciesInvalid';
					}

				} else {
					$warnings[] = 'speciesIncompatible';
				}

			}

			if($product['distribution'] !== NULL) {

				if(in_array($profile, Product::getProfiles('distribution')) === FALSE) {
					$warnings[] = 'distributionIncompatible';
					$import[$key]['distribution'] = NULL;
				} else if(in_array($product['distribution'], Product::model()->getPropertyEnum('distribution')) === FALSE) {
					$errors[] = 'distributionInvalid';
				}

			} else {

				if(in_array($profile, Product::getProfiles('distribution'))) {
					$import[$key]['distribution'] = Product::PRODUCTION;
				}

			}

			$import[$key]['organic'] = Product::NO;
			$import[$key]['qualities'] = new \Set();
			$import[$key]['marksInvalid'] = [];

			if($product['marks']) {

				if(in_array($profile, Product::getProfiles('marks')) === FALSE) {
					$warnings[] = 'marksIncompatible';
				} else {

					$qualities = array_flip(Product::getQualities());

					foreach($product['marks'] as $mark) {

						switch($mark) {

							case 'organic' :
								$import[$key]['organic'] = Product::YES;
								break;

							case 'conversion' :
								$import[$key]['organic'] = Product::CONVERSION;
								break;

							default:

								if(array_key_exists($mark, $qualities)) {
									$import[$key]['qualities']->value($qualities[$mark], TRUE);
								} else {
									$import[$key]['marksInvalid'][] = $mark;
									$errors[] = 'markInvalid';
								}

								break;

						}

					}
				}

			}

			if($product['variety'] !== NULL) {

				if(in_array($profile, Product::getProfiles('variety')) === FALSE) {
					$warnings[] = 'varietyIncompatible';
					$import[$key]['variety'] = NULL;
				}

			}

			if($product['allergen'] !== NULL) {

				if(in_array($profile, Product::getProfiles('allergens')) === FALSE) {
					$warnings[] = 'allergenIncompatible';
					$import[$key]['allergen'] = NULL;
				}

			}

			if($product['packaging'] !== NULL) {

				if(in_array($profile, Product::getProfiles('packaging')) === FALSE) {
					$warnings[] = 'packagingIncompatible';
					$import[$key]['packaging'] = NULL;
				}

			}

			if($product['composition'] !== NULL) {

				if(in_array($profile, Product::getProfiles('ingredients')) === FALSE) {
					$warnings[] = 'compositionIncompatible';
					$import[$key]['composition'] = NULL;
				}

			}


			if(in_array($profile, Product::getProfiles('frozen')) === FALSE) {

				if($product['frozen']) {
					$warnings[] = 'frozenIncompatible';
				}

				$import[$key]['frozen'] = NULL;

			}

			$import[$key]['eProduct'] = $eProduct;

			$errors = array_filter($errors);
			$errors = array_unique($errors);

			$errorsCount += count($errors);

			$import[$key]['errors'] = $errors;
			$import[$key]['warnings'] = $warnings;
			$import[$key]['ignore'] = $ignore;

		}

		if($references) {

			$references = array_merge(
				$references,
				Product::model()
					->whereStructure($eStructure)
					->whereReference('IN', $references)
					->getColumn('reference')
			);

			$referencesCount = array_count_values($references);
			$referencesCount = array_filter($referencesCount, fn($value) => $value > 1);

			$infoGlobal['references'] = array_keys($referencesCount);

		}

		$errorsGlobal['units'] = array_unique($errorsGlobal['units']);
		$errorsGlobal['vatRates'] = array_unique($errorsGlobal['vatRates']);
		$errorsGlobal['species'] = array_unique($errorsGlobal['species']);
		$errorsGlobal['profiles'] = array_unique($errorsGlobal['profiles']);

		return [
			'import' => $import,
			'errorsCount' => $errorsCount + count($errorsGlobal['species']),
			'errorsGlobal' => $errorsGlobal,
			'infoGlobal' => $infoGlobal,
		];

	}

}
?>
