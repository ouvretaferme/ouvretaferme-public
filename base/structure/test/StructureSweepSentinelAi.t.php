<?php
namespace structure;

/**
 * Test-sentinelle : un parcours du parc qui compte ou qui envoie ne voit pas le décor des tests
 * (chantier tech, lot 6.2a).
 *
 * Le décor des tests vit dans la base de tous : ses fermes sont `REAL`, pour franchir les gardes
 * d'une ferme installée, et rien dans une colonne ne les distingue. Elles ne se reconnaissent qu'à
 * leur identifiant, réservé dans `StructureSetting::FIXTURE_STRUCTURES`. Un compteur de l'admin, une
 * statistique de la nuit, une relance ou un message au salon de l'équipe qui parcourt le parc sans
 * écarter ces identifiants compte ou prévient pour une structure qui n'existe pas.
 *
 * Le partage avec `InstalledSentinelAi` : elle garde ce qu'une structure **partage avec les autres**
 * (un agrégat appelle le filtre des fermes installées) ; celle-ci garde les **balayages du parc**
 * (un parcours écarte le décor). Une même méthode peut figurer dans les deux, pour deux raisons.
 *
 * Deux inventaires, comparés dans les deux sens :
 *
 * - **Les balayages qui se reconnaissent à la lecture.** Toute méthode d'un `.l.php` ou d'un
 *   observateur `.o.php`, et tout fichier de `page/` hors `page/configure/`, qui lit `Structure` ou
 *   `Feature` sans borne, ou qui appelle un helper de boucle. Il appelle le point unique et figure
 *   dans SWEEPS, ou il est déclaré dans SWEEPS_WITHOUT_FILTER avec sa raison.
 * - **Les parcours qui partent d'un autre module**, qu'aucun jeton ne reconnaît : CONSUMERS pour
 *   ceux qui appellent le point unique, CONSUMERS_WITHOUT_FILTER pour ceux qui s'en passent.
 *
 * Les scripts de `page/configure/` ne sont pas lus : ils se lancent à la main, par quelqu'un qui
 * choisit sa population. Les helpers de boucle restent exhaustifs, le tri se fait chez l'appelant :
 * un paiement, un ménage ou une migration doivent atteindre le décor comme le reste.
 *
 * Ce que la lecture ne voit pas, et qui reste à la charge de la relecture :
 *
 * - un balayage dans un fichier à méthodes qui n'est ni Lib ni observateur (`.e.php`, `.a.php`),
 *   qui n'est pas lu ;
 * - un modèle tenu dans une variable (`$mStructure = Structure::model()` puis `$mStructure->…`) ;
 * - une borne conditionnelle (`->whereStructure($id, if: …)`), tenue pour une borne ;
 * - une liste `IN` tenue pour une borne quelle que soit sa taille : si elle vient elle-même d'un
 *   balayage, le décor y entre ;
 * - un second balayage ajouté dans une unité déjà déclarée : l'unité est la méthode ou la page, et
 *   un seul appel au point unique la tient pour aveugle.
 *
 * Lancement : dev/test package=structure file=StructureSweepSentinelAi
 */
class StructureSweepSentinelAiTest extends \Test {

	/**
	 * Balayages reconnus à la lecture, qui appellent le point unique.
	 */
	const SWEEPS = [
		// Les fermes installées, que lisent les agrégats entre structures.
		'structure/lib/Structure.l.php::getInstalledSql',
		// Le compte des structures qui partagent leurs données.
		'structure/lib/Structure.l.php::countConsentData',
		// Le total de l'admin des structures.
		'structure/lib/Admin.l.php::countStructures',
		// Les chiffres de l'équipement lus en direct par l'admin.
		'admin/lib/Admin.l.php::countFeatures',
		'admin/lib/Admin.l.php::countPdpMembership',
		// La passe de la nuit qui ouvre les bases par structure : le helper rend tout le parc, la
		// passe saute le décor.
		'data/lib/Analytics.l.php::calculate',
		// Les cases du formulaire d'une note de l'équipe.
		'communication/lib/Note.l.php::countStructureTypes',
		// Les structures entrées dans les relevés de prix, que lisent l'e-mail mensuel et l'envoi
		// des prix.
		'quotation/lib/Alert.l.php::getStructuresWithQuotation',
		// Le nombre d'adhérentes affiché au public.
		'association/lib/Membership.l.php::count',
		// Les statistiques de la nuit qui écartent ce que rend getNotInstalledSql(), et les quatre
		// traversées des bases par structure, qui sautent ce que rend getNotInstalled().
		'admin/lib/StatisticAi.l.php::computeParcDepth',
		'admin/lib/StatisticAi.l.php::computeParcTerritory',
		'admin/lib/StatisticAi.l.php::computeParcDeclared',
		'admin/lib/StatisticAi.l.php::stockStructureClosed',
		'admin/lib/StatisticAi.l.php::statisticStructureCreated',
		'company/lib/StatisticAccountingAi.l.php::computeFeature',
		'company/lib/StatisticAccountingAi.l.php::computeFlows',
		'company/lib/StatisticAccountingAi.l.php::computeStructures',
		'company/lib/StatisticAccountingAi.l.php::computeInvoicing',
		'company/lib/StatisticAccountingAi.l.php::computePdp',
	];

	/**
	 * Balayages reconnus à la lecture qui n'appellent pas le point unique, avec leur raison.
	 */
	const SWEEPS_WITHOUT_FILTER = [
		// Les helpers de boucle et la liste des bases à migrer rendent tout le parc : une migration,
		// un paiement ou un ménage atteignent le décor comme le reste, le tri se fait chez l'appelant.
		'structure/lib/Structure.l.php::getForMigrate',
		'structure/lib/Structure.l.php::runAccountingAll',
		'structure/lib/Structure.l.php::runPdpAll',
		'structure/lib/Structure.l.php::runAll',
		// La liste de l'admin des structures : c'est par elle que l'équipe retrouve le décor, sorti
		// du total et des tuiles.
		'structure/lib/Admin.l.php::getStructures',
		// Les ménages de la nuit : notifications de plus de trente jours, documents d'exercice,
		// corbeille des justificatifs. Le décor se nettoie comme le reste.
		'notification/lib/Notification.l.php::clean',
		'proof/page/cron/clean.p.php',
		'financialYear/page/cron/pdf.p.php',
		// Les imports de paiements dans les cahiers de caisse : ils ne lisent que les cahiers reliés
		// à un compte Stripe ou SumUp, et c'est la caisse de la structure qu'ils écrivent.
		'cash/page/cron/stripe.p.php',
		'cash/page/cron/sumupAi.p.php',
		// Reposent sur un état du décor, que son empreinte vérifie (lot 6.2b) : aucun lien bancaire,
		// aucun partenaire d'e-facturation. Sans cela ces trois crons parlent à de vrais services.
		'bank/page/cron/enableBanking.p.php',
		'pdp/page/cron/fetch.p.php',
		'pdp/page/cron/synchronize.p.php',
		// L'écho des scripts de statistiques, qui annoncent combien de structures ils vont
		// parcourir : ce n'est pas une statistique.
		'company/lib/StatisticAccountingAi.l.php::getStructureCount',
		'admin/lib/StatisticAccountingAi.l.php::getPdpStructureCount',
		// Le registre des justificatifs écrit les lignes du décor comme les autres, marquées `excluded`,
		// et sa tuile comme son écran ne lisent que les lignes où `excluded` est faux (R-146). La
		// recherche par nom ne lit `Structure` que pour borner le registre.
		'company/lib/ProofRegister.l.php::compute',
		'company/lib/ProofRegister.l.php::applySearch',
		// Le registre des documents de vente suit la même règle : le décor y a ses lignes, marquées
		// `excluded`, que l'écran ne lit pas. La recherche par nom ne lit `Structure` que pour le borner.
		'selling/lib/ExternalRegister.l.php::applySearch',
		// Le registre des envois aux impôts écarte le décor de sa passe par le prédicat qu'il partage
		// avec celui des justificatifs, CompanyLib::isExcludedFromRegister(), avec les structures de test
		// et de formation. La recherche par nom ne lit `Structure` que pour borner le registre.
		'company/lib/TransmissionRegister.l.php::compute',
		'company/lib/TransmissionRegister.l.php::applyStructure',
		// La projection du plan de culture s'écrit pour chaque structure, et ne se lit que par la
		// liste de structures que l'appelant passe à VegetablePlanAiLib::getByStructures().
		'aggregate/lib/VegetableProjectionAi.l.php::rebuild',
		// Les contrôles d'intégrité de la nuit, qui écrivent dans le journal des erreurs de l'équipe :
		// ils ne comptent ni n'envoient rien pour le produit.
		'structure/lib/Dev.o.php::getVersion',
		'account/lib/Dev.o.php::integrity',
		'bank/lib/Dev.o.php::integrity',
		'invoicing/lib/Dev.o.php::integrity',
		'vat/lib/Dev.o.php::integrity',
		// Le tableau des structures d'un arrêté de la mercuriale, lu par un identifiant que
		// l'appelant tient d'une ligne déjà filtrée par ReferenceLib.
		'quotation/lib/Reference.l.php::getStructure',
		// La fermeture des fermes de formation sans visite depuis six mois : elle ne lit que des
		// structures TRAINING, que le décor n'est jamais (son empreinte vérifie `producerType`), et ne
		// compte ni n'envoie rien.
		'training/lib/Farm.l.php::closeUnvisited',
		// Le rattrapage de la nuit des codes du lieu de production (`structure/cron/placeCodesAi`) :
		// il déduit la commune d'un point comme l'enregistrement du lieu le fait pour chacun, et ne
		// compte ni n'envoie rien. `Structure` n'y est lu que pour écarter les structures déclarées
		// à l'étranger.
		'structure/lib/System.l.php::getWithoutCodes',
		// Le relief de la nuit (`map/cron/relief`) : il ne demande à l'IGN que les tuiles des zones
		// qu'une structure a tracées, et ne compte ni n'envoie rien pour le produit. Une structure du
		// décor reçoit ses tuiles comme une autre, sans qu'aucun chiffre change. `Structure` n'y est lu
		// que pour écarter les structures fermées.
		'map/lib/Relief.l.php::getStructures',
	];

	/**
	 * Parcours du parc qui partent d'un autre module, et doivent appeler le point unique.
	 */
	const CONSUMERS = [
		// L'instantané horaire du parc comptable : sa liste vient de l'observateur de company, que la
		// lecture ne reconnaît pas comme un balayage, et la passe saute le décor.
		'admin/lib/AccountingStructure.l.php::compute',
		// Ce que les chiffres écartent. La sous-requête nomme le décor par `IN`, que la lecture prend
		// pour une borne : elle se déclare ici.
		'structure/lib/Structure.l.php::getNotInstalledSql',
		// Les deux autres chiffres de l'admin lus en direct : les factures passées par la plateforme
		// et les demandes d'aide.
		'admin/lib/Admin.l.php::countPdpInvoicing',
		'admin/lib/Admin.l.php::countAccountingHelp',
		// Les destinataires d'une note de l'équipe, aperçu et envoi.
		'communication/lib/Note.l.php::getRecipients',
		// La relance d'une formule qui se termine : e-mail et notification.
		'subscription/lib/Subscription.l.php::remind',
		// Ce qui prévient l'équipe : le message au salon d'une adhésion payée, l'e-mail et le message
		// au salon d'une demande d'aide.
		'association/lib/Discord.l.php::notifyMembership',
		'company/lib/AccountingHelp.l.php::notify',
		// Le compte public des adhésions par année.
		'association/lib/History.l.php::countByYears',
		// Les structures dont le prix catalogue est recalculé chaque nuit. Conséquence à connaître :
		// celui d'une ferme du décor ne l'est jamais, comme celui d'une ferme de test.
		'quotation/lib/Product.l.php::getActiveStructures',
		// Les quatre traversées statistiques de la nuit sautent ce que rend cette liste, qui dérive
		// de getNotInstalledSql().
		'company/lib/StatisticAccountingAi.l.php::getNotInstalled',
	];

	/**
	 * Parcours qui partent d'un autre module et n'écartent pas le décor, avec leur raison.
	 */
	const CONSUMERS_WITHOUT_FILTER = [
		// Le journal des échanges avec la plateforme de facturation : l'équipe doit y lire ceux du
		// décor, c'est un journal et non un compteur.
		'admin/lib/Admin.l.php::loadInvoicingHistory',
		// Les statistiques d'inscrits et d'actifs comptent des comptes et ne lisent aucune
		// structure : les comptes du décor y sont comptés une fois, le jour de leur création.
		'admin/lib/StatisticAi.l.php::statisticUserCreated',
		'admin/lib/StatisticAi.l.php::getRoles',
		'admin/lib/StatisticActiveAi.l.php::countByDay',
		// Reposent sur un état du décor, que son empreinte vérifie (lot 6.2b) : aucun compte du
		// décor n'a d'accès Discord, aucune de ses structures n'a d'adhésion payée.
		'subscription/lib/Subscription.l.php::synchronizeDiscord',
		'association/lib/Membership.l.php::sendDocuments',
	];

	// Une requête qui part de Structure ou de Feature. Sans espace de noms, elle ne vaut que dans le
	// package structure.
	const HEAD = '/(\\\\?structure\\\\)?\b(Structure|Feature)::model\(\)|new\s+(\\\\?structure\\\\)?(Structure|Feature)Model\(\)/';
	// Ce qui lit : une ligne agrégée par get() est un compte du parc au même titre qu'une collection.
	const READ = '/->(getCollection|count|getColumn|getValue|getSql|existsSql|exists|get)\(/';
	// Ce qui borne : un identifiant de structure, un groupe, une source, un membre, ou un élément
	// passé à get(). Un NOT IN ne borne pas, il écarte : c'est la forme du point unique.
	const BOUND = '/->where(Id|Structure|Parent|Source|User)\((?!\s*[\'"]NOT IN)|where\(\s*[\'"](m\d+\.)?(id|structure|parent|user)[\'"]\s*,(?!\s*[\'"]NOT IN)|->get\(\s*\$/';
	const HELPERS = '/\b(runAll|runAccountingAll|runPdpAll|getAccountingStructures)\(/';
	// Le point unique : la constante, la méthode d'élément, et les sous-requêtes qui la portent.
	const FILTER = '/\bFIXTURE_STRUCTURES\b|->isFixture\(|\bget(Not)?Installed(Sql)?\(/';

	/**
	 * Les fichiers inspectés, par leur chemin depuis le package : les `.l.php`, les observateurs
	 * `.o.php` et les pages hors `page/configure/`.
	 * @return array<string, string>
	 */
	private function getFiles(): array {

		$files = [];

		foreach(\Package::getList() as $package => $app) {

			$path = \Package::getPath($package);

			if(is_dir($path) === FALSE) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

			foreach($iterator as $file) {

				$relative = substr($file->getPathname(), strlen($path) + 1);

				if(
					str_contains($file->getPathname(), '/nodejs/') or
					str_contains($file->getPathname(), '/perso/') or
					str_starts_with($relative, 'page/configure/') or
					(self::hasMethods($relative) === FALSE and str_starts_with($relative, 'page/') === FALSE) or
					str_ends_with($relative, '.php') === FALSE
				) {
					continue;
				}

				$files[$package.'/'.$relative] = $file->getPathname();

			}

		}

		ksort($files);

		return $files;

	}

	private static function hasMethods(string $relative): bool {
		return (str_ends_with($relative, '.l.php') or str_ends_with($relative, '.o.php'));
	}

	/**
	 * Les unités d'un fichier, sans leurs commentaires : ses méthodes pour un Lib ou un observateur,
	 * le fichier entier pour une page, dont les actions sont des closures sans nom.
	 * @return array<string, string> texte de chaque unité, par `chemin::méthode` ou `chemin`
	 */
	private function getUnits(string $relative, string $path): array {

		$tokens = \PhpToken::tokenize(file_get_contents($path));
		$count = count($tokens);

		if(self::hasMethods($relative) === FALSE) {

			$body = '';

			foreach($tokens as $token) {
				if($token->id !== T_COMMENT and $token->id !== T_DOC_COMMENT) {
					$body .= $token->text;
				}
			}

			return [$relative => $body];

		}

		$units = [];

		for($i = 0; $i < $count; $i++) {

			if($tokens[$i]->id !== T_FUNCTION) {
				continue;
			}

			// Une closure n'a pas de nom : elle appartient à la méthode qui l'englobe, déjà comptée.
			$j = $i + 1;
			while($j < $count and $tokens[$j]->isIgnorable()) {
				$j++;
			}

			if($j >= $count or $tokens[$j]->id !== T_STRING) {
				continue;
			}

			$name = $tokens[$j]->text;

			while($j < $count and $tokens[$j]->text !== '{' and $tokens[$j]->text !== ';') {
				$j++;
			}

			if($j >= $count or $tokens[$j]->text === ';') {
				continue;
			}

			$depth = 0;
			$body = '';

			for($k = $j; $k < $count; $k++) {

				$text = $tokens[$k]->text;

				// T_CURLY_OPEN et T_DOLLAR_OPEN_CURLY_BRACES ouvrent une accolade dans une chaîne, que la
				// fermeture « } » referme comme les autres.
				if($text === '{' or $tokens[$k]->id === T_CURLY_OPEN or $tokens[$k]->id === T_DOLLAR_OPEN_CURLY_BRACES) {
					$depth++;
				} else if($text === '}') {
					$depth--;
				}

				if($tokens[$k]->id !== T_COMMENT and $tokens[$k]->id !== T_DOC_COMMENT) {
					$body .= $text;
				}

				if($depth === 0) {
					break;
				}

			}

			$units[$relative.'::'.$name] = $body;
			$i = $k;

		}

		return $units;

	}

	/**
	 * L'instruction qui commence à cette position : jusqu'au point-virgule de son niveau, ou
	 * jusqu'à la fermeture de ce qui l'englobe quand la requête est un argument.
	 */
	private function getStatement(string $body, int $start): string {

		$depth = 0;
		$length = strlen($body);

		for($i = $start; $i < $length; $i++) {

			$character = $body[$i];

			if($character === '(' or $character === '[' or $character === '{') {
				$depth++;
			} else if($character === ')' or $character === ']' or $character === '}') {

				$depth--;

				if($depth < 0) {
					break;
				}

			} else if($character === ';' and $depth === 0) {
				break;
			}

		}

		return substr($body, $start, $i - $start);

	}

	/**
	 * Une unité balaie le parc quand une de ses requêtes sur Structure ou Feature lit sans borne,
	 * ou qu'elle appelle un helper de boucle qu'elle ne définit pas.
	 */
	private function isSweep(string $unit, string $body): bool {

		$package = substr($unit, 0, strpos($unit, '/'));

		if(preg_match_all(self::HEAD, $body, $matches, PREG_OFFSET_CAPTURE) > 0) {

			foreach($matches[0] as [$head, $position]) {

				if(str_contains($head, '\\') === FALSE and $package !== 'structure') {
					continue;
				}

				$statement = $this->getStatement($body, $position);

				if(
					preg_match(self::READ, $statement) === 1 and
					preg_match(self::BOUND, $statement) === 0
				) {
					return TRUE;
				}

			}

		}

		if(preg_match_all(self::HELPERS, $body, $matches) > 0) {

			$name = substr($unit, strrpos($unit, ':') + 1);

			foreach($matches[1] as $helper) {
				if($helper !== $name) {
					return TRUE;
				}
			}

		}

		return FALSE;

	}

	/**
	 * @return array<string, string> le texte de chaque unité du dépôt
	 */
	private function getAllUnits(): array {

		$units = [];

		foreach($this->getFiles() as $relative => $path) {
			$units += $this->getUnits($relative, $path);
		}

		return $units;

	}

	public function testSweepsIgnoreFixtures(): void {

		$units = $this->getAllUnits();

		$found = [];

		foreach($units as $unit => $body) {
			if($this->isSweep($unit, $body)) {
				$found[] = $unit;
			}
		}

		$declared = array_merge(self::SWEEPS, self::SWEEPS_WITHOUT_FILTER);

		$undeclared = array_diff($found, $declared);
		$stale = array_diff($declared, $found);
		$twice = array_intersect(self::SWEEPS, self::SWEEPS_WITHOUT_FILTER);

		$this->assertEquals([], array_values($undeclared), 'balayage du parc non déclaré, à rendre aveugle au décor des tests (SWEEPS) ou à déclarer avec sa raison (SWEEPS_WITHOUT_FILTER) : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($stale), 'déclaration périmée (méthode disparue, bornée, ou qui ne balaie plus le parc) : '.implode(', ', $stale));
		$this->assertEquals([], array_values($twice), 'unité déclarée dans SWEEPS et dans SWEEPS_WITHOUT_FILTER : '.implode(', ', $twice));

		$missing = [];

		foreach(self::SWEEPS as $unit) {
			if(array_key_exists($unit, $units) and preg_match(self::FILTER, $units[$unit]) === 0) {
				$missing[] = $unit;
			}
		}

		$this->assertEquals([], $missing, 'balayage déclaré dans SWEEPS qui n\'appelle pas le point unique : '.implode(', ', $missing));

		$filtered = [];

		foreach(self::SWEEPS_WITHOUT_FILTER as $unit) {
			if(array_key_exists($unit, $units) and preg_match(self::FILTER, $units[$unit]) === 1) {
				$filtered[] = $unit;
			}
		}

		$this->assertEquals([], $filtered, 'balayage déclaré sans filtre qui appelle désormais le point unique, à passer dans SWEEPS : '.implode(', ', $filtered));

	}

	public function testConsumersIgnoreFixtures(): void {

		$units = $this->getAllUnits();

		$missing = [];

		foreach(self::CONSUMERS as $unit) {

			if(
				array_key_exists($unit, $units) === FALSE or
				preg_match(self::FILTER, $units[$unit]) === 0
			) {
				$missing[] = $unit;
			}

		}

		$this->assertEquals([], $missing, 'parcours déclaré dans CONSUMERS introuvable ou qui n\'appelle pas le point unique : '.implode(', ', $missing));

		$stale = [];
		$filtered = [];

		foreach(self::CONSUMERS_WITHOUT_FILTER as $unit) {

			if(array_key_exists($unit, $units) === FALSE) {
				$stale[] = $unit;
			} else if(preg_match(self::FILTER, $units[$unit]) === 1) {
				$filtered[] = $unit;
			}

		}

		$this->assertEquals([], $stale, 'parcours déclaré dans CONSUMERS_WITHOUT_FILTER introuvable : '.implode(', ', $stale));
		$this->assertEquals([], $filtered, 'parcours déclaré sans filtre qui appelle désormais le point unique, à passer dans CONSUMERS : '.implode(', ', $filtered));

	}

	/**
	 * La liste des identifiants réservés : jamais vide, un NOT IN sur une liste vide écartant toutes
	 * les lignes (`ModuleModel::whereBuild()` rend `(0)`), et jamais un identifiant qu'un autre
	 * usage réserve déjà.
	 */
	public function testFixtureStructuresAreReserved(): void {

		$fixtures = StructureSetting::FIXTURE_STRUCTURES;

		$this->assertTrue($fixtures !== [], 'StructureSetting::FIXTURE_STRUCTURES est vide : chaque NOT IN posé sur elle écarterait tout le parc');

		$invalid = array_filter($fixtures, fn($fixture) => (is_int($fixture) === FALSE or $fixture <= 0));

		$this->assertEquals([], array_values($invalid), 'identifiant réservé qui n\'est pas un entier positif : '.implode(', ', array_map('json_encode', $invalid)));
		$this->assertEquals(count($fixtures), count(array_unique($fixtures)), 'identifiant réservé deux fois : '.implode(', ', array_diff_key($fixtures, array_unique($fixtures))));
		$this->assertTrue(array_is_list($fixtures), 'StructureSetting::FIXTURE_STRUCTURES est une liste : les identifiants sont ses valeurs, pas ses clés');

		$reserved = [
			'Structure::DEMO' => Structure::DEMO,
			'Structure::DEMO_BIS' => Structure::DEMO_BIS,
		];

		foreach(array_keys(StructureSetting::SCHOOL_STRUCTURES) as $school) {
			$reserved['StructureSetting::SCHOOL_STRUCTURES['.$school.']'] = $school;
		}

		$reserved += $this->getStructureConstants();

		$literals = [];
		$taken = [];

		foreach($reserved as $name => $id) {
			if(in_array($id, $fixtures, TRUE)) {
				if(str_contains($name, '.t.php::')) {
					$literals[] = $name.' = '.$id;
				} else {
					$taken[] = $name.' = '.$id;
				}
			}
		}

		$this->assertEquals([], $literals, 'structure du décor des tests écrite en littéral dans un test, à citer par FixtureAiLib::<code> : '.implode(', ', $literals));
		$this->assertEquals([], $taken, 'identifiant réservé au décor des tests et déjà pris par un autre usage : '.implode(', ', $taken));

	}

	/**
	 * Les structures que le dépôt désigne par une constante, dans les tests qui empruntent une
	 * structure et dans les réglages.
	 * @return array<string, int> par `chemin::CONSTANTE`
	 */
	private function getStructureConstants(): array {

		$constants = [];

		foreach(\Package::getList() as $package => $app) {

			$path = \Package::getPath($package);

			if(is_dir($path) === FALSE) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

			foreach($iterator as $file) {

				$relative = substr($file->getPathname(), strlen($path) + 1);

				// Dans un test, toute constante STRUCTURE_<NOM> désigne une structure. Dans un réglage,
				// STRUCTURE_<NOM> peut être un seuil (STRUCTURE_SERIES_MIN) : seul le nom exact compte.
				if(str_ends_with($relative, '.t.php')) {
					$pattern = '/\bconst\s+(STRUCTURE(?:_[A-Z]+)*|[A-Z]+_STRUCTURE)\s*=\s*(\d+)\s*;/';
				} else if(str_ends_with($relative, '.s.php')) {
					$pattern = '/\bconst\s+(STRUCTURE|[A-Z]+_STRUCTURE)\s*=\s*(\d+)\s*;/';
				} else {
					continue;
				}

				if(preg_match_all($pattern, file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER) === 0) {
					continue;
				}

				foreach($matches as [, $name, $id]) {
					$constants[$package.'/'.$relative.'::'.$name] = (int)$id;
				}

			}

		}

		return $constants;

	}

}
?>
