<?php
namespace dev;

/**
 * Le journal des appels API garde la ligne quatorze jours et le corps de la réponse vingt-quatre
 * heures. La dissymétrie est une question de poids : mesuré le 02/10/2026 sur le journal d'une seule
 * structure, 1 272 réponses réussies pesaient 28,8 Mo, 23,8 Ko en moyenne, là où les 24 corps
 * d'erreur de la même table tenaient dans 3,5 Ko. Ce que le corps d'un appel réussi apporte, le
 * client l'a déjà reçu ; celui d'une erreur est ce qu'on relit pour comprendre un refus, donc il
 * reste aussi longtemps que sa ligne.
 *
 * Ce que ce fichier éprouve est \api\LogLib::clean(), le point d'entrée du cron api/cron/clean, et
 * les deux sens comptent : ce qu'il efface et ce qu'il laisse. Il éprouve aussi la colonne calculée
 * qui dit à l'écran de clés si un corps est encore là, l'écran ne pouvant pas charger cent corps
 * pour le savoir.
 *
 * La transaction s'ouvre sur `api\LogModel`, la table du journal vivant dans la base commune, et
 * elle couvre le fichier entier : chaque cas prend donc sa propre référence, une lecture par
 * référence voyant sinon les lignes des cas joués avant elle.
 *
 * Lancement : dev/test package=dev file=ApiLogRetention
 */
class ApiLogRetentionTest extends TransactionTest {

	/**
	 * `api\Log.reference` est un entier brut et non un élément : le cadriciel ne peut pas citer le
	 * package `structure`, et une référence qu'aucune structure ne porte garde les cas hors des lignes
	 * réelles du journal, que `clean()` traverse de toute façon.
	 */
	const REFERENCE_CLEAN = 999000;
	const REFERENCE_DELAY = 999001;
	const REFERENCE_LIST = 999002;

	const ROUTE = 'devRetentionProbe';

	protected function getTransactionModel(): \ModuleModel {
		return new \api\LogModel();
	}

	/**
	 * Une réponse réussie perd son corps après vingt-quatre heures et garde sa ligne, une erreur garde
	 * les deux, et une ligne de plus de quatorze jours s'en va entièrement.
	 */
	public function testCleanClearsSuccessBodiesOnly(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eLogRecent = $this->createLog(self::REFERENCE_CLEAN, \api\Log::SUCCESS, '2026-06-15 08:00:00');
		$eLogOld = $this->createLog(self::REFERENCE_CLEAN, \api\Log::SUCCESS, '2026-06-14 09:00:00');
		$eLogError = $this->createLog(self::REFERENCE_CLEAN, \api\Log::ERROR, '2026-06-14 09:00:00');
		$eLogExpired = $this->createLog(self::REFERENCE_CLEAN, \api\Log::SUCCESS, '2026-05-31 10:00:00');

		\api\LogLib::clean();

		$this->assertEquals(['bonjour'], $this->readBody($eLogRecent), 'le corps de moins de 24 h');
		$this->assertEquals(NULL, $this->readBody($eLogOld), 'le corps réussi de plus de 24 h');
		$this->assertEquals(['bonjour'], $this->readBody($eLogError), "le corps d'erreur de plus de 24 h");

		$this->assertEquals(TRUE, \api\LogLib::getById($eLogOld['id'], ['id'])->notEmpty(), 'la ligne de 25 h');
		$this->assertEquals(FALSE, \api\LogLib::getById($eLogExpired['id'], ['id'])->notEmpty(), 'la ligne de 15 jours');

	}

	/**
	 * Contrôle du contrôle : sans l'intervalle, les quatre lignes garderaient leur corps. Une purge
	 * qui n'efface rien et une purge qui efface tout rendent le même vert sur la seule ligne de 25 h.
	 */
	public function testCleanLeavesEverythingBeforeTheDelay(): void {

		$this->setClock('2026-06-15 10:00:00');

		$eLog = $this->createLog(self::REFERENCE_DELAY, \api\Log::SUCCESS, '2026-06-15 09:59:00');

		\api\LogLib::clean();

		$this->assertEquals(['bonjour'], $this->readBody($eLog), "le corps d'une minute");

	}

	/**
	 * L'écran de clés sait si un corps est encore là sans le charger : cent lignes à 23,8 Ko feraient
	 * payer 2,4 Mo pour afficher un tableau de dates et de codes HTTP.
	 */
	public function testTheListSaysWhetherTheBodyIsStillThere(): void {

		$this->setClock('2026-06-15 10:00:00');

		$this->createLog(self::REFERENCE_LIST, \api\Log::SUCCESS, '2026-06-15 08:00:00');
		$this->createLog(self::REFERENCE_LIST, \api\Log::SUCCESS, '2026-06-14 09:00:00');

		\api\LogLib::clean();

		[$cLog, ] = \api\LogLib::getByReference(self::REFERENCE_LIST, 0, 100);

		$found = [];

		foreach($cLog as $eLog) {
			$found[] = $eLog['hasResponseBody'];
		}

		$this->assertEquals([FALSE, TRUE], $found, 'le tri est par id décroissant, donc la ligne de 25 h vient en second');

		foreach($cLog as $eLog) {
			$this->assertEquals(FALSE, $eLog->offsetExists('responseBody'), "la liste ne charge pas le corps");
		}

	}

	/**
	 * Une ligne du journal, antidatée. `createdAt` est en `SPECIAL(now)` dans le schéma, donc sa valeur
	 * se repose par une écriture après la création, et l'assertion dit si le cadriciel l'a acceptée :
	 * sans elle, les quatre lignes porteraient la même date et chaque cas serait vert sans rien
	 * éprouver. build() est hors jeu ici, aucune de ces valeurs ne vient d'un formulaire et un test
	 * veut exactement les dates qu'il pose, celles qu'un POST ne permettrait pas.
	 */
	private function createLog(int $reference, string $status, string $createdAt): \api\Log {

		$eLog = new \api\Log([
			'token' => new \api\AccessToken(),
			'reference' => $reference,
			'requestMethod' => 'get',
			'requestUrl' => '/'.self::ROUTE,
			'requestHeaders' => [],
			'route' => self::ROUTE,
			'responseBody' => ['bonjour'],
			'responseStatusCode' => match($status) {
				\api\Log::SUCCESS => 200,
				\api\Log::ERROR => 401
			},
			'status' => $status
		]);

		\api\LogLib::create($eLog);

		\api\Log::model()
			->whereId($eLog['id'])
			->update(['createdAt' => $createdAt]);

		$this->assertEquals(
			$createdAt,
			\api\LogLib::getById($eLog['id'], ['id', 'createdAt'])['createdAt'],
			'la date posée sur la ligne'
		);

		return $eLog;

	}

	private function readBody(\api\Log $eLog): ?array {

		return \api\LogLib::getById($eLog['id'], ['id', 'responseBody'])['responseBody'];

	}

}
?>
