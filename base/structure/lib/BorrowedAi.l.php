<?php
namespace structure;

/**
 * Les données réelles que des tests empruntent encore à la copie de production, faute de pouvoir
 * les poser sur le décor (chantier tech, lot 6.6).
 *
 * Un emprunt n'est sûr que si la donnée ne bouge plus : un exercice clos, une facture rejetée, un
 * jour de livraison passé. `DATA` dit, pour chaque donnée, ce qui la gèle, ou pourquoi rien ne la
 * gèle, et ce qui permet de voir qu'elle a bougé : une empreinte, ou un seuil sous lequel le test
 * ne vérifierait plus rien. `TESTS` rattache chaque test emprunteur à ses données.
 *
 * Une donnée est déclarée une fois, quel que soit le nombre de tests qui la lisent : l'exercice 2024
 * de la 1340 est lu par trois tests, et une empreinte recopiée dans chacun finirait par diverger.
 *
 * Chaque test emprunteur appelle `assert()` dans son `init()`, avant toute connexion : un poste dont
 * la copie ne porte pas la donnée échoue alors en la nommant (« base absente », « schéma en retard »,
 * « empreinte différente »), au lieu d'une erreur SQL lue loin de sa cause. Les tests se connectent
 * ensuite en `migrate: FALSE` : une migration jouée par un test écrit la version de la structure dans
 * la base commune, et sur une base absente l'avance sans rien installer.
 *
 * L'empreinte de référence est celle de la production, d'où viennent toutes les copies : une donnée
 * close avant la copie y a la même empreinte sur tous les postes. Celles qu'aucun poste n'a encore
 * pu mesurer valent NULL avec leur raison, et `BorrowedSentinelAiTest` n'en laisse que baisser le
 * nombre. La mesure de production se fait en lecture, par `measure()`, celle-là même qu'appelle
 * `check()` : `php framework/lime.php -a ouvretaferme -e prod -r 'echo \structure\BorrowedAiLib::measure("<clé>");'`.
 */
class BorrowedAiLib {

	use \Notifiable;

	/** Un exercice comptable, lu sur ses écritures. */
	const FINANCIAL_YEAR = 'financialYear';

	/** Les fiches d'immobilisation reprises, avec un amortissement économique. */
	const ASSET = 'asset';

	/** Une facture électronique et ses événements de cycle de vie. */
	const INVOICE = 'invoice';

	/** Les paiements déjà importés en comptabilité, en base commune, datés par leur mouvement bancaire, en base de la structure. */
	const PAYMENT = 'payment';

	/** Les cultures de la saison en cours. */
	const CULTIVATION = 'cultivation';

	/** Les séries, toutes saisons. */
	const SERIES = 'series';

	/** Le lieu d'une ferme école. */
	const SCHOOL = 'school';

	/** Les ventes et marchés d'un jour de livraison. */
	const DELIVERY_DAY = 'deliveryDay';

	/** Ce qui gèle la donnée. */
	const CLOSED = 'closed';
	const REJECTED = 'rejected';
	const PAST_DAY = 'pastDay';
	const READ_ONLY = 'readOnly';
	const NONE = 'none';

	/** Les sortes de données qui se lisent, en tout ou en partie, dans la base `structure_<id>`. */
	const STRUCTURE_DATABASE = [self::FINANCIAL_YEAR, self::ASSET, self::INVOICE, self::PAYMENT];

	/** Les empreintes viennent de la copie de pavow du 2026-10-04, la seule mesurée à ce jour. */
	const UNMEASURED_ABSENT = 'base absente de la copie de pavow le 2026-10-04 : à mesurer en production';

	/**
	 * Clé => donnée. `print` est l'empreinte attendue (`nombre|plus grand id|somme des CRC32`), ou
	 * NULL : sur une donnée qu'aucun état ne gèle (`NONE`), qui n'en a pas, ou avec sa raison dans
	 * `unmeasured`. `minimum` est le seuil sous lequel le test qui la lit ne vérifierait plus rien,
	 * ou NULL. Les deux clés sont posées sur chaque entrée, ce que `BorrowedSentinelAiTest` vérifie.
	 */
	const DATA = [
		'7-2024' => [
			'structure' => 7, 'kind' => self::FINANCIAL_YEAR, 'year' => 2024,
			'terminal' => self::CLOSED, 'frozen' => 'exercice clos le 2025-01-01 : ses écritures ne changent plus',
			'print' => '2240|5983|4864039184280',
			'minimum' => NULL,
		],
		'7-2025' => [
			'structure' => 7, 'kind' => self::FINANCIAL_YEAR, 'year' => 2025,
			'terminal' => self::CLOSED, 'frozen' => 'exercice clos le 2026-01-24 : ses écritures ne changent plus',
			'print' => '3172|6900|6741305416197',
			'minimum' => NULL,
		],
		'92-2025' => [
			'structure' => 92, 'kind' => self::FINANCIAL_YEAR, 'year' => 2025,
			'terminal' => self::NONE, 'frozen' => 'le test le veut ouvert, et échoue en le nommant une fois clos : rien ne le gèle, seule la caution du compte 246 est lue',
			'print' => NULL,
			'minimum' => 1,
		],
		'209-2025' => [
			'structure' => 209, 'kind' => self::FINANCIAL_YEAR, 'year' => 2025,
			'terminal' => self::CLOSED, 'frozen' => 'exercice clos : ses écritures ne changent plus',
			'print' => NULL, 'unmeasured' => self::UNMEASURED_ABSENT,
			'minimum' => NULL,
		],
		'311-2025' => [
			'structure' => 311, 'kind' => self::FINANCIAL_YEAR, 'year' => 2025,
			'terminal' => self::CLOSED, 'frozen' => 'exercice clos : ses écritures ne changent plus',
			'print' => NULL, 'unmeasured' => self::UNMEASURED_ABSENT,
			'minimum' => NULL,
		],
		'1340-2024' => [
			'structure' => 1340, 'kind' => self::FINANCIAL_YEAR, 'year' => 2024,
			'terminal' => self::CLOSED, 'frozen' => 'exercice clos : ses écritures ne changent plus',
			'print' => NULL, 'unmeasured' => self::UNMEASURED_ABSENT,
			'minimum' => NULL,
		],
		'1340-2025' => [
			'structure' => 1340, 'kind' => self::FINANCIAL_YEAR, 'year' => 2025,
			'terminal' => self::NONE, 'frozen' => 'exercice ouvert, qu\'OpenExerciseBalance veut ouvert : rien ne le gèle',
			'print' => NULL,
			'minimum' => 1,
		],
		'1954-2022' => [
			'structure' => 1954, 'kind' => self::FINANCIAL_YEAR, 'year' => 2022,
			'terminal' => self::CLOSED, 'frozen' => 'exercice clos : ses écritures ne changent plus',
			'print' => NULL, 'unmeasured' => self::UNMEASURED_ABSENT,
			'minimum' => NULL,
		],
		'2154-2025' => [
			'structure' => 2154, 'kind' => self::FINANCIAL_YEAR, 'year' => 2025,
			'terminal' => self::CLOSED, 'frozen' => 'exercice clos : ses écritures ne changent plus',
			'print' => NULL, 'unmeasured' => self::UNMEASURED_ABSENT,
			'minimum' => NULL,
		],
		'3994-2026' => [
			'structure' => 3994, 'kind' => self::FINANCIAL_YEAR, 'year' => 2026,
			'terminal' => self::NONE, 'frozen' => 'le test le veut ouvert, et le saute une fois clos : rien ne le gèle',
			'print' => NULL,
			'minimum' => 1,
		],
		'1340-asset' => [
			'structure' => 1340, 'kind' => self::ASSET,
			'terminal' => self::NONE, 'frozen' => 'le test ne fige aucune valeur : il fausse la première classe d\'une reprise quelconque',
			'print' => NULL,
			'minimum' => 1,
		],
		'266-invoice-2' => [
			'structure' => 266, 'kind' => self::INVOICE, 'id' => 2,
			'terminal' => self::REJECTED, 'frozen' => 'facture rejetée par la plateforme : plus aucun événement de cycle de vie ne lui arrive. Seule ligne de sa table, créée le 2026-07-17 contre la plateforme de recette : peut-être une donnée de dev, absente de la production (non vérifié)',
			'print' => '9|9|18479305726',
			'minimum' => NULL,
		],
		'7-payment' => [
			'structure' => 7, 'kind' => self::PAYMENT, 'until' => '2025-12-31',
			'terminal' => self::CLOSED, 'frozen' => 'paiements dont le mouvement bancaire tombe dans les exercices 2024 et 2025 : leur import est écrit dans ces exercices clos, dont 7-2024 et 7-2025 tiennent la clôture et l\'empreinte. L\'empreinte couvre le paiement lui-même ; le client, les ventes, leurs articles, les autres règlements du document et le mouvement bancaire, que la régénération lit aussi, n\'y entrent pas et peuvent encore bouger',
			'print' => '286|95377|638451768521',
			'minimum' => NULL,
		],
		'7-cultivation' => [
			'structure' => 7, 'kind' => self::CULTIVATION,
			'terminal' => self::NONE, 'frozen' => 'un volume, pas une valeur : l\'optimiseur ne se juge que sur une saison réelle. Le seuil est celui du test (`notEmpty()`), la saison lue étant celle de l\'horloge : la saison suivante n\'en compte que quelques-unes avant janvier',
			'print' => NULL,
			'minimum' => 1,
		],
		'439-series' => [
			'structure' => 439, 'kind' => self::SERIES,
			'terminal' => self::NONE, 'frozen' => 'source d\'une duplication, comparée à sa copie au même instant : seul compte qu\'elle porte de la donnée',
			'print' => NULL,
			'minimum' => 1,
		],
		'2-school' => [
			'structure' => 2, 'kind' => self::SCHOOL,
			'terminal' => self::READ_ONLY, 'frozen' => 'ferme école, que acceptWrite() refuse d\'écrire : elle ne change que par SchoolAiLib',
			'print' => '1|6190|503582585',
			'minimum' => NULL,
		],
		'1619-2026-09-18' => [
			'structure' => 1619, 'kind' => self::DELIVERY_DAY, 'date' => '2026-09-18',
			'terminal' => self::PAST_DAY, 'frozen' => 'jour de livraison passé : il ne reçoit plus de commande',
			'print' => NULL, 'unmeasured' => 'absent de la copie de pavow, arrêtée au 2026-09-11 : mesuré ailleurs à 9 commandes et un marché, sans empreinte',
			'minimum' => 1,
		],
	];

	/**
	 * Chemin du test depuis la racine du dépôt => les clés de `DATA` qu'il lit.
	 */
	const TESTS = [
		'accounting/asset/test/Resume.t.php' => ['1340-asset'],
		'accounting/invoicing/test/EventReasonSanity.t.php' => ['266-invoice-2'],
		'accounting/overview/test/MultiStructureBalance.t.php' => ['7-2024', '7-2025', '311-2025', '209-2025', '2154-2025', '1954-2022', '1340-2024'],
		'accounting/overview/test/OpenExerciseBalance.t.php' => ['3994-2026', '1340-2025', '92-2025', '7-2025', '311-2025', '1340-2024'],
		'accounting/preaccounting/test/ReconcilePayment.t.php' => ['7-2024', '7-2025', '7-payment'],
		'base/structure/test/DuplicateAi.t.php' => ['439-series'],
		'base/structure/test/ProfileAi.t.php' => ['2-school'],
		'base/structure/test/SchoolAi.t.php' => ['2-school'],
		'commercialisation/selling/test/DashboardAi.t.php' => ['1619-2026-09-18'],
		'production/planning/test/HarvestExplainAi.t.php' => ['7-cultivation'],
	];

	/**
	 * Les écarts des données qu'emprunte ce test, nommés, vides si tout est en place. Ne laisse
	 * aucune structure connectée : le test se connecte ensuite lui-même.
	 *
	 * @param string $test Le chemin du test depuis la racine du dépôt.
	 * @return string[]
	 */
	public static function check(string $test): array {

		$keys = self::TESTS[$test] ?? throw new \Exception('Le test '.$test.' n\'est pas déclaré dans BorrowedAiLib::TESTS');

		$gaps = [];

		foreach($keys as $key) {
			array_push($gaps, ...self::checkData($key));
		}

		return $gaps;

	}

	/**
	 * Lève en nommant les écarts de `check()`, avec ce qui les répare.
	 */
	public static function assert(string $test): void {

		$gaps = self::check($test);

		if($gaps !== []) {
			throw new \Exception('Donnée empruntée hors d\'état sur ce poste ('.implode(' ; ', $gaps).')');
		}

	}

	/**
	 * L'empreinte d'une donnée, `nombre|plus grand id|somme des CRC32`, dans l'état où la copie la
	 * porte. Lecture seule.
	 */
	public static function measure(string $key): string {

		$data = self::DATA[$key];
		$eStructure = StructureLib::getById($data['structure']);

		$result = self::read($eStructure, $data);

		if($result === NULL) {
			return 'absente';
		}

		[$count, $print] = $result;

		return $print ?? (string)$count;

	}

	private static function checkData(string $key): array {

		$data = self::DATA[$key];
		$label = $key.' ('.self::getKindLabel($data).')';

		$eStructure = Structure::model()
			->select(Structure::getSelection())
			->whereId($data['structure'])
			->get();

		if($eStructure->empty()) {
			return [$label.' : la structure '.$data['structure'].' n\'existe pas'];
		}

		if(in_array($data['kind'], self::STRUCTURE_DATABASE, TRUE)) {

			$gap = self::checkDatabase($eStructure);

			if($gap !== NULL) {
				return [$label.' : '.$gap];
			}

		}

		$result = self::read($eStructure, $data);

		if($result === NULL) {
			return [$label.' est absente de la base de la '.$data['structure']];
		}

		[$count, $print, $terminal] = $result + [2 => NULL];

		$gaps = [];

		if($terminal === FALSE) {
			$gaps[] = $label.' n\'a pas atteint son état terminal ('.$data['terminal'].')';
		}

		if($data['print'] !== NULL and $print !== $data['print']) {
			$gaps[] = $label.' a pour empreinte '.$print.', attendu '.$data['print'].' : la donnée a bougé, ou la copie de ce poste la précède';
		}

		if($data['minimum'] !== NULL and $count < $data['minimum']) {
			$gaps[] = $label.' compte '.$count.' ligne(s), au moins '.$data['minimum'].' attendue(s) : le test n\'y vérifierait rien';
		}

		return $gaps;

	}

	/**
	 * Ce qui manque à la base `structure_<id>` pour être lue, ou NULL. La version se lit sans
	 * migrer : `MigrateLib::live()` écrirait dans la base commune.
	 */
	private static function checkDatabase(Structure $eStructure): ?string {

		$base = StructureSetting::getDatabaseName($eStructure);

		if(Structure::model()->pdo()->select('SHOW DATABASES LIKE \''.$base.'\'')->fetch() === FALSE) {
			return 'la base '.$base.' est absente de ce poste';
		}

		if($eStructure['version'] % 10 !== 0) {
			return 'la migration de la base '.$base.' a été interrompue (version '.$eStructure['version'].'), et MigrateLib::live() ne la reprend pas';
		}

		if($eStructure['version'] < \data\MigrateLib::VERSION * 10) {
			return 'le schéma de la base '.$base.' est en retard (version '.$eStructure['version'].') : php framework/lime.php -a ouvretaferme -e dev data/migrate structure='.$eStructure['id'];
		}

		return NULL;

	}

	/**
	 * Le nombre de lignes, l'empreinte et, pour une donnée dont l'état se lit, si elle l'a atteint.
	 * NULL pour un exercice ou une facture que la base ne porte pas : rien n'y a d'état ni d'empreinte.
	 *
	 * @return ?array{0: int, 1: ?string, 2?: bool}
	 */
	private static function read(Structure $eStructure, array $data): ?array {

		// Et non `StructureLib::connectDatabase()`, qui marquerait la structure connectée : le
		// `connectDatabase()` du test qui suit sortirait alors sans rien faire.
		if(in_array($data['kind'], self::STRUCTURE_DATABASE, TRUE)) {
			return \notification\NotificationLib::on($eStructure, fn() => self::readStructureDatabase($eStructure, $data), StructureSetting::getPackages());
		}

		return match($data['kind']) {

			self::CULTIVATION => [
				\series\Cultivation::model()
					->whereStructure($eStructure)
					->whereSeason(\Clock::year())
					->count(),
				NULL
			],

			self::SERIES => [
				\series\Series::model()
					->whereStructure($eStructure)
					->count(),
				NULL
			],

			self::SCHOOL => self::getPrint(
				System::model()->whereStructure($eStructure),
				'id, IFNULL(place, \'\'), IFNULL(ST_AsText(placeLngLat), \'\')'
			),

			self::DELIVERY_DAY => self::readClosed('readBorrowedDeliveryDay', $eStructure, $data),

		};

	}

	private static function readStructureDatabase(Structure $eStructure, array $data): ?array {

		return match($data['kind']) {

			self::FINANCIAL_YEAR => self::readFinancialYear($data),

			self::ASSET => [
				\asset\Asset::model()
					->where('economicAmortization > 0')
					->count(),
				NULL
			],

			self::INVOICE => self::readInvoice($data),

			self::PAYMENT => self::readClosed('readBorrowedPayment', $eStructure, $data),

		};

	}

	private static function readFinancialYear(array $data): ?array {

		$eFinancialYear = \financialYear\FinancialYear::model()
			->select(['id', 'status'])
			->whereStartDate('LIKE', $data['year'].'-%')
			->get();

		if($eFinancialYear->empty()) {
			return NULL;
		}

		$result = self::getPrint(
			\journal\Operation::model()->whereFinancialYear($eFinancialYear),
			'id, accountLabel, amount, type, date'
		);

		if($data['terminal'] === self::CLOSED) {
			$result[2] = ($eFinancialYear['status'] === \financialYear\FinancialYear::CLOSE);
		}

		return $result;

	}

	/**
	 * Une donnée des ventes ou des paiements, que leur package lit, absent de la version publique, où
	 * elle est absente.
	 */
	private static function readClosed(string $event, Structure $eStructure, array $data): ?array {

		if(StructureSetting::hasCommercialisationApp() === FALSE) {
			return NULL;
		}

		return self::notify($event, $eStructure, $data)[0];

	}

	/**
	 * L'empreinte des événements, à laquelle s'ajoute le CRC32 de la facture elle-même : une
	 * facture renumérotée ou rouverte change l'empreinte sans changer ses événements.
	 */
	private static function readInvoice(array $data): ?array {

		$eInvoicing = \invoicing\Invoicing::model()
			->select([
				'id', 'pdpStatus',
				'crc' => new \Sql('CRC32(CONCAT_WS(\'|\', id, IFNULL(number, \'\'), status, IFNULL(pdpStatus, \'\'), IFNULL(date, \'\')))', 'int')
			])
			->whereId($data['id'])
			->get();

		if($eInvoicing->empty()) {
			return NULL;
		}

		[$count, $print] = self::getPrint(
			\invoicing\Event::model()->whereInvoicing($eInvoicing),
			'id, statusCode, IFNULL(reason, \'\'), IFNULL(details, \'\')'
		);

		[$eventCount, $eventMaximum, $eventCrc] = explode('|', $print);

		return [
			$count,
			$eventCount.'|'.$eventMaximum.'|'.((int)$eventCrc + $eInvoicing['crc']),
			$eInvoicing['pdpStatus'] === \invoicing\InvoicingSetting::STATUS_REJECTED_BY_PLATFORM
		];

	}

	/**
	 * `nombre|plus grand id|somme des CRC32` des lignes de la requête posée. Une somme de CRC32 et
	 * non un MD5 de GROUP_CONCAT, que `group_concat_max_len` (1 024 octets) tronquerait sans
	 * avertissement.
	 *
	 * @return array{0: int, 1: string}
	 */
	public static function getPrint(\ModuleModel $mModule, string $columns): array {

		$values = $mModule
			->select([
				'count' => new \Sql('COUNT(*)', 'int'),
				'maximum' => new \Sql('IFNULL(MAX(id), 0)', 'int'),
				'crc' => new \Sql('IFNULL(SUM(CRC32(CONCAT_WS(\'|\', '.$columns.'))), 0)', 'int')
			])
			->get()
			->getArrayCopy();

		return [$values['count'], $values['count'].'|'.$values['maximum'].'|'.$values['crc']];

	}

	private static function getKindLabel(array $data): string {

		return match($data['kind']) {
			self::FINANCIAL_YEAR => 'exercice '.$data['year'].' de la '.$data['structure'],
			self::ASSET => 'immobilisations reprises de la '.$data['structure'],
			self::INVOICE => 'facture '.$data['id'].' de la '.$data['structure'],
			self::PAYMENT => 'paiements importés de la '.$data['structure'].' dont le mouvement bancaire est daté au plus tard du '.$data['until'],
			self::CULTIVATION => 'cultures de la saison de la '.$data['structure'],
			self::SERIES => 'séries de la '.$data['structure'],
			self::SCHOOL => 'lieu de la ferme école '.$data['structure'],
			self::DELIVERY_DAY => 'livraisons du '.$data['date'].' de la '.$data['structure'],
		};

	}

}
?>
