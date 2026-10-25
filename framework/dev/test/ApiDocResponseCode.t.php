<?php
namespace dev;

class ApiDocResponseCodeProbe extends ApiDocLib {

	public static function createdCodes(\ModuleApi $instance): array {

		$codes = self::readHttpCodes(new \ReflectionMethod($instance, 'pushCreated'), $instance);
		sort($codes);

		return $codes;

	}

}

/**
 * Le code de succès que la référence OpenAPI annonce pour une écriture.
 *
 * Le générateur annonçait 201 pour toute création et 200 pour toute action, sans regarder la
 * route : POST rain, qui ajoute au jour et répond 200, sortait en 201, et POST invoice/deposit,
 * qui crée et répond 201, sortait en 200.
 *
 * Lancement : dev/test package=dev file=ApiDocResponseCode
 */
class ApiDocResponseCodeTest extends \Test {

	private array $spec;

	public function init(): void {

		$libModule = new ModuleLib();
		$libModule->load();

		$this->spec = ApiDocLib::buildOpenApiSpec(ApiDocLib::getExposedModules(), $libModule);

	}

	private function getSuccessCodes(string $path, string $method = 'post'): array {

		return array_values(array_filter(
			array_keys($this->spec['paths'][$path][$method]['responses'] ?? []),
			fn(int $code) => $code < 300
		));

	}

	public function testCreationThatReplacesAnnouncesItsOwnCode(): void {

		$this->assertEquals([200], $this->getSuccessCodes('/rain'), 'POST rain redéfinit pushCreated() et répond 200');

	}

	/** Contrôle du contrôle : une lecture qui rendrait 200 partout passerait le test précédent. */
	public function testPlainCreationStillAnnounces201(): void {

		$this->assertEquals([201], $this->getSuccessCodes('/gauge'), 'POST gauge garde le pushCreated() du socle');

	}

	public function testActionAnnouncesTheCodeItSets(): void {

		$this->assertEquals([201], $this->getSuccessCodes('/invoice/deposit'), 'une création du socle sous une route d\'action répond 201');
		$this->assertEquals([201], $this->getSuccessCodes('/timesheet/distribution'), 'une action qui pose 201 l\'annonce');
		$this->assertEquals([200], $this->getSuccessCodes('/sale/{id}/addProducts', 'patch'), 'une action qui ne pose rien garde le défaut d\'ApiData');

	}

	public function testConditionalCodeIsAnnouncedBesideTheDefault(): void {

		$this->assertEquals([200, 201, 202], $this->getSuccessCodes('/marketSale'), 'POST marketSale pose 200 sur un rejeu, 201 sur une création et 202 sur une vente gardée hors des totaux, chacun dans sa branche');
		$this->assertEquals([200], ApiDocResponseCodeProbe::createdCodes(new ApiDocResponseCodeReplaceApi()), 'sans appel au parent, seul le code posé');
		$this->assertEquals([200, 201], ApiDocResponseCodeProbe::createdCodes(new ApiDocResponseCodeAwakenApi()), 'avec l\'appel au parent, les deux');

	}

	public function testComputedCodeStopsTheGeneration(): void {

		try {

			ApiDocResponseCodeProbe::createdCodes(new ApiDocResponseCodeComputedApi());
			$message = NULL;

		} catch(\Exception $e) {
			$message = $e->getMessage();
		}

		$this->assertTrue($message !== NULL and str_contains($message, 'ApiDocResponseCode.t.php'), 'un code calculé lève en nommant le fichier : '.$message);

	}

}

abstract class ApiDocResponseCodeApi extends \ModuleApi {

	public function getNewModel(\ApiData $data): \ModuleModel {
		return new \user\UserModel();
	}

	public function getNewElement(\ApiData $data): \Element {
		return new \user\User();
	}

}

class ApiDocResponseCodeReplaceApi extends ApiDocResponseCodeApi {

	public function pushCreated(\ApiData $data): void {
		$data->httpCode = 200;
	}

}

class ApiDocResponseCodeAwakenApi extends ApiDocResponseCodeApi {

	public function pushCreated(\ApiData $data): void {

		parent::pushCreated($data);

		if($data->e['awakened']) {
			$data->httpCode = 200;
		}

	}

}

class ApiDocResponseCodeComputedApi extends ApiDocResponseCodeApi {

	public function pushCreated(\ApiData $data): void {
		$data->httpCode = ($data->e['awakened'] ? 200 : 201);
	}

}
?>
