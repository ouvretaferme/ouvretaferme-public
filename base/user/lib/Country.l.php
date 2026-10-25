<?php
namespace user;

class CountryLib extends CountryCrud {

	use \ModuleDeferred;
	use \Notifiable;

	private static array|\Collection|null $countries = NULL;

	public static function deferred(): \Collection {

		return self::getCache('list', fn() => self::getAll());

	}

	public static function getAll(): \Collection {

		return \Cache::redis()->query('country-all', function() {

			return Country::model()
				->select(Country::getSelection())
				->getCollection(index: 'id');

		}, 3600);

	}

	public static function getByCode(string $code): Country {

		return Country::model()
			->select(Country::getSelection())
			->whereCode($code)
			->get();

	}

	public static function getForForm(): array|Country {

		if(self::$countries === NULL) {

			$ccCountry = Country::model()
				->select([
					'id',
					'name', 'eu', 'parent',
					'positioned' => new \Sql('position IS NOT NULL', 'int')
				])
				->sort([
					new \Sql('position IS NULL'),
					'position' => SORT_ASC,
					'name' => SORT_ASC
				])
				->getCollection(index: ['positioned', NULL]);

			if($ccCountry->count() === 1) {
				self::$countries = $ccCountry->first();
			} else {

				$callback = fn($eCountry) => [
					'value' => $eCountry['id'],
					'label' => $eCountry['name'],
					'attributes' => [
						'data-eu' => (int)$eCountry['eu'],
						'data-france' => (int)$eCountry->isFrance()
					]
				];

				self::$countries = [
					[
						'label' => s("Pays francophones"),
						'values' => $ccCountry[1]->makeArray($callback)
					],
					[
						'label' => s("Autres pays"),
						'values' => $ccCountry[0]->makeArray($callback)
					]
				];

			}

		}

		return self::$countries;

	}

}
?>
