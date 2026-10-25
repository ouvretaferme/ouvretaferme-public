<?php
namespace dev;

require_once __DIR__.'/GetAllHeadersPolyfill.php';

/**
 * Base class for end-to-end API tests: goes through \ApiRoute::run() for real (route lookup,
 * scope check, input validation, Lib::create/update/delete...) instead of calling Lib methods
 * directly, by bypassing OAuth with \api\ApiSetting::$devAccessToken (cf getAuthorization() in
 * framework/core/Api.php, only active when LIME_ENV === 'dev').
 *
 * Subclasses must implement getTransactionModel() (any Model on the reference structure's DB
 * connection, cf accounting/account/test/Accounting.t.php for the rationale: one connection can
 * serve several modules, one beginTransaction()/rollBack() covers all of them).
 *
 * Lancement : dev/test package=<package> file=<Fichier>
 */
abstract class ApiTest extends TransactionTest {

	const VERSION = '0.1beta';

	const STRUCTURE = \structure\FixtureAiLib::F1;

	protected \structure\Structure $eStructure;

	public function init(): void {

		// Sélection complète (pas juste id/version) : \api\ApiData::authorized() recharge la structure
		// via \structure\StructureLib::getById() à chaque callApi(), mais \structure\Structure::isConnected()
		// compare par id et court-circuite alors la reconnexion — Structure::getConnected() resterait sous-chargé
		// pour tout le test si on ne le connectait pas correctement dès le départ.
		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(static::STRUCTURE)
			->get();

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.static::STRUCTURE.' introuvable : sème le décor par structure/configure/fixtureAi write=1, ou importe la copie de production pour une structure réelle');
		}

		\structure\StructureLib::connectDatabase($this->eStructure, migrate: TRUE);

		// Le même court-circuit par id joue quand un fichier de test précédent du paquet a déjà connecté
		// cette structure avec une sélection plus légère (\account\AccountingTest ne charge que id et
		// version) : connectDatabase() ne remplace alors rien, et c'est cette structure-là que lirait
		// Structure::getConnected(). La reposer ici rend le fichier indépendant de l'ordre des tests.
		\structure\Structure::setConnected($this->eStructure);

		\api\ApiSetting::$devAccessToken = fn() => $this->getDevAccessToken($this->eStructure['id']);

	}

	/**
	 * Le compteur des refus d'authentification par adresse vit dans Redis, hors de toute transaction,
	 * et tous les tests de la ligne de commande partagent la même adresse : sans remise à zéro, les
	 * refus qu'éprouvent les fichiers d'API de toutes les fenêtres finiraient par couper les suivants.
	 */
	public function setUp(): void {

		parent::setUp();

		\user\ThrottleAiLib::clear('api-ip', getIp());

	}

	/**
	 * Le jeton de dev, porté par un administrateur réel de la structure.
	 *
	 * `ApiData::authorized()` relit à chaque appel que le porteur administre encore la structure : un
	 * jeton sans utilisateur est refusé, exactement comme le serait en production une clé dont le
	 * porteur a quitté les lieux.
	 */
	protected function getDevAccessToken(int $reference): \api\AccessToken {

		return new \api\AccessToken([
			'reference' => $reference,
			'user' => (\api\ApiSetting::$devAccessTokenUser)($reference),
		]);

	}

	/** Relu par son identifiant : le crochet est posé par l'application, qui ne s'engage sur aucune sélection. */
	protected function getAdmin(): \user\User {

		return \user\UserLib::getById((\api\ApiSetting::$devAccessTokenUser)($this->eStructure['id'])['id']);

	}

	/**
	 * Call an exposed API route exactly as ApiRoute::run() would for a real HTTP request.
	 *
	 * A string body is sent as is, to test what json_encode() can not produce. A route that answers
	 * with a PDF returns its content as the body.
	 *
	 * @return array{status: int, body: mixed}
	 */
	protected function callApi(string $method, string $route, array|string $body = [], array $get = [], ?string $version = NULL): array {

		$routeProperty = new \ReflectionProperty(\Route::class, 'requestMethod');
		$routeProperty->setAccessible(TRUE);
		$routeProperty->setValue(NULL, $method);

		$_GET = $get;

		http_response_code(200);

		$hasBody = in_array($method, ['POST', 'PUT', 'PATCH']);

		if($hasBody) {
			ApiTestInput::set(is_string($body) ? $body : json_encode($body));
		}

		try {

			\ApiRoute::run($version ?? static::VERSION, $route);

			return ['status' => http_response_code(), 'body' => NULL];

		} catch(\JsonAction $action) {

			$dataProperty = new \ReflectionProperty($action, 'data');
			$dataProperty->setAccessible(TRUE);

			return ['status' => http_response_code(), 'body' => $dataProperty->getValue($action)];

		} catch(\PdfAction $action) {

			$dataProperty = new \ReflectionProperty($action, 'data');
			$dataProperty->setAccessible(TRUE);

			return ['status' => http_response_code(), 'body' => $dataProperty->getValue($action)];

		} catch(\VoidAction) {

			return ['status' => http_response_code(), 'body' => NULL];

		} finally {

			if($hasBody) {
				ApiTestInput::restore();
			}

		}

	}

}

/**
 * php://input can not be written to in CLI: swap the 'php' stream wrapper for the duration of a
 * single callApi() call so ApiInternalData::load() can read a fake JSON body from it.
 */
class ApiTestInput {

	public $context;

	protected static string $content = '';

	protected int $position = 0;

	public static function set(string $content): void {
		self::$content = $content;
		stream_wrapper_unregister('php');
		stream_wrapper_register('php', self::class);
	}

	public static function restore(): void {
		stream_wrapper_restore('php');
	}

	public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool {

		$this->position = 0;

		return ($path === 'php://input');

	}

	public function stream_read(int $count): string {

		$chunk = substr(self::$content, $this->position, $count);
		$this->position += strlen($chunk);

		return $chunk;

	}

	public function stream_eof(): bool {
		return $this->position >= strlen(self::$content);
	}

	public function stream_stat(): array {
		return [];
	}

}
?>
