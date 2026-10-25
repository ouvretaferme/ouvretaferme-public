<?php
namespace series;

/**
 * Test bout-en-bout de l'API publique series\Slice (production/series/api/Slice_0_1beta.a.php).
 *
 * Contrairement aux autres modules exposés, Slice_0_1betaApi ne déclare aucune route dans son
 * __construct() (pas de list/create/read/update/delete) : c'est un pur sous-élément, utilisé
 * uniquement via la correlation 'varieties' de series\Cultivation (cf CultivationApi.t.php,
 * `$data->exportCollection($e['cSlice'])`). getNewModel()/getNewElement() lèvent explicitement
 * \UnsupportedException. Ce test documente cet état (pas de endpoint HTTP direct à tester) plutôt
 * que d'appeler \dev\ApiTest::callApi() sur une route qui n'existe pas.
 *
 * Lancement : dev/test package=series file=SliceApi
 */
class SliceApiTest extends \dev\ApiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new SliceModel();
	}

	public function testNoDirectRoute(): void {

		$api = new Slice_0_1betaApi();

		// ApiData construit lui-même son Api à partir du routeInfo, il attend donc la description
		// de route et non l'instance. Slice n'en déclarant aucune, on en fabrique une minimale.
		$routeInfo = ['class' => Slice_0_1betaApi::class];

		try {
			$api->getNewModel(new \ApiData($routeInfo, new \api\AccessToken()));
			$this->assertFail('Slice_0_1betaApi::getNewModel() was expected to throw \UnsupportedException');
		} catch(\UnsupportedException) {
			$this->assertTrue(TRUE);
		}

		try {
			$api->getNewElement(new \ApiData($routeInfo, new \api\AccessToken()));
			$this->assertFail('Slice_0_1betaApi::getNewElement() was expected to throw \UnsupportedException');
		} catch(\UnsupportedException) {
			$this->assertTrue(TRUE);
		}

	}

	/**
	 * Vérifie que Slice est bien exposé comme sous-objet de series\Cultivation (seul chemin HTTP
	 * qui le fait apparaître réellement) : couverture fonctionnelle complémentaire dans
	 * series/test/CultivationApi.t.php (assertion sur 'varieties' dans testReadDelete()).
	 */
	public function testExposedOnlyThroughCultivation(): void {

		$this->assertTrue(in_array('series\Slice', \dev\ApiDocLib::getExposedModules()));

	}

}
?>
