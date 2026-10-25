<?php
namespace api;

class ApiSetting extends \Settings {

	const VERSIONS = [
		'0.1beta'
	];

	const RATE_LIMIT_SECOND = 4;
	const RATE_LIMIT_MINUTE = 30;
	const RATE_LIMIT_DAY = 500;

	const IS_API_ACTIVE = TRUE;

	/* L'écriture s'ouvre après la lecture, et d'abord aux seules références que l'application désigne
	   (ApiInternalData::acceptScope()). Fermée, elle refuse tout scope *:write à l'appel, ce qui vaut
	   aussi pour un jeton émis avant la fermeture : ses scopes sont relus dans le jeton signé. */
	const IS_API_WRITE_ACTIVE = (LIME_ENV === 'dev');

	/* Durées de conservation du journal des appels API, appliquées par le cron api/cron/clean. Le
	   corps d'une réponse réussie s'efface avant sa ligne : mesuré le 02/10/2026 sur le journal d'une
	   seule structure, 23,8 Ko en moyenne contre 144 octets pour un corps d'erreur, que le
	   diagnostic garde aussi longtemps que la ligne qui le porte. */
	const LOG_RETENTION_DAYS = 14;
	const LOG_BODY_RETENTION_HOURS = 24;

	/* Utilisé pour identifier les accès API sans OAuth */
	const PERSONAL_CLIENT_ID = 'personal-access';

	/* Scopes proposés pour les clés API personnelles (5 domaines métier × read/write) */
	const PERSONAL_SCOPE_NAMES = [
		'general:read', 'general:write',
		'production:read', 'production:write',
		'commercialisation:read', 'commercialisation:write',
		'accounting:read', 'accounting:write',
		'learning:read', 'learning:write',
	];

	public static string $encryptionKey = '';

	public static ?\Closure $devAccessToken = NULL;

	/**
	 * Les scopes que porte le jeton de dev à la place de `PERSONAL_SCOPE_NAMES`, pour qu'un test
	 * éprouve une clé limitée à quelques domaines. Lu par la seule branche du jeton de dev
	 * (`ApiRoute::getAuthorization()`, `LIME_ENV === 'dev'`), et nulle part ailleurs : une lecture
	 * hors de cette branche ferait d'une propriété de test une porte de la production.
	 */
	public static ?array $devScopes = NULL;

	/**
	 * Qui porte le jeton de dev pour cette référence.
	 *
	 * L'API refuse une clé dont le porteur n'a plus le droit d'administrer sa référence : le décor des
	 * tests doit donc en nommer un, et ce droit est une notion de l'application, pas du cadriciel.
	 */
	public static ?\Closure $devAccessTokenUser = NULL;

	/* pour résoudre la structure liée à la clé API */
	private static ?\Closure $referenceContextResolver = NULL;
	private static ?\Closure $referenceResolver = NULL;
	private static ?\Closure $dayLimitResolver = NULL;

	/* ce que la réponse de /api/oauth/token dit de la référence du jeton */
	private static ?\Closure $tokenParamsResolver = NULL;

	public static function setReferenceContextResolver(\Closure $resolver): void {
		self::$referenceContextResolver = $resolver;
	}

	public static function setReferenceResolver(\Closure $resolver): void {
		self::$referenceResolver = $resolver;
	}

	public static function setDayLimitResolver(\Closure $resolver): void {
		self::$dayLimitResolver = $resolver;
	}

	public static function getDayLimit(int $reference): int {
		return (self::$dayLimitResolver === NULL) ? self::RATE_LIMIT_DAY : (self::$dayLimitResolver)($reference);
	}

	public static function setTokenParamsResolver(\Closure $resolver): void {
		self::$tokenParamsResolver = $resolver;
	}

	public static function getTokenParams(int $reference): array {
		return (self::$tokenParamsResolver === NULL) ? [] : (self::$tokenParamsResolver)($reference);
	}

	public static function resolveReferenceContext(\stdClass $data): void {

		if(self::$referenceContextResolver === NULL) {
			throw new \Exception('ApiSetting::$referenceContextResolver is not configured');
		}

		(self::$referenceContextResolver)($data);

	}

	public static function getReference(): int {

		if(self::$referenceResolver === NULL) {
			throw new \Exception('ApiSetting::$referenceResolver is not configured');
		}

		return (self::$referenceResolver)();

	}

	public static function isScopeOpen(string $scope, bool $writeActive): bool {
		return ($writeActive or str_ends_with($scope, ':write') === FALSE);
	}

	public static function getPrivateKeyPath(): string {
		return __DIR__.'/private.pem';
	}

	public static function getPublicKeyPath(): string {
		return __DIR__.'/public.pem';
	}

}
