<?php
namespace structure;

/**
 * Test-sentinelle : ce qu'une structure partage avec les autres ne vient que des fermes installées
 * (chantier fermes-reelles, lot L4).
 *
 * Une ferme de test, d'étudiant ou un projet d'installation fausse une moyenne de prix, un calendrier
 * de cultures ou un classement de variétés au même titre qu'une ferme réelle, sans que rien ne le
 * montre. Le filtre est une sous-requête de StructureLib, `getInstalledSql()` ou son complément
 * `getNotInstalledSql()`, et ce test exige qu'un agrégat entre structures en appelle une. Celui qui écrira le prochain agrégat dans un autre package
 * ne lira ni le cadrage ni ce fichier ; il lancera la suite.
 *
 * Deux inventaires, comparés dans les deux sens :
 *
 * - **Les lectures du consentement.** Toute méthode d'un `.l.php`, et tout fichier de `page/`, qui
 *   borne une requête sur `consentData` est un agrégat entre structures : c'est le seul usage de ce
 *   consentement. Il appelle le filtre, ou il est déclaré dans CONSENT_WITHOUT_FILTER avec sa raison.
 * - **Les agrégats sans consentement.** Une méthode qui agrège entre structures sans lire le
 *   consentement ne se reconnaît pas à la lecture ; elle est déclarée dans CONSUMERS, et doit appeler
 *   le filtre.
 *
 * Une déclaration qui ne correspond plus au code fait échouer le test : une méthode renommée ou qui a
 * cessé de lire le consentement se voit.
 *
 * Lancement : dev/test package=structure file=InstalledSentinelAi
 */
class InstalledSentinelAiTest extends \Test {

	/**
	 * Lectures du consentement qui n'appellent pas le filtre, avec leur raison.
	 */
	const CONSENT_WITHOUT_FILTER = [
		// Le compte de structures qui partagent leurs données, affiché à l'équipe dans l'admin des
		// utilisateurs : il ne se partage avec personne. Il écarte le décor des tests, ce que contrôle
		// StructureSweepSentinelAi, et garde les fermes non installées, qui sont du parc.
		'structure/lib/Structure.l.php::countConsentData',
		// Le rattachement automatique à la mercuriale par le chiffre d'affaires : il ne retient que les
		// structures que QuotationLib::getTurnovers() lui rend, filtrées à la source (voir CONSUMERS).
		'quotation/lib/Quotation.l.php::attachByTurnover',
		// Le relevé du décor des tests : il compare une structure du décor, dont `consentData`, à sa
		// déclaration dans FixtureAiLib::DECOR, et n'agrège rien entre structures.
		'structure/lib/FixtureAi.l.php::checkStructure',
	];

	/**
	 * Agrégats entre structures qui ne lisent pas le consentement, et doivent appeler le filtre.
	 */
	const CONSUMERS = [
		// Le classement des variétés les plus cultivées, proposé à toutes les fermes.
		'plant/lib/VarietyNameAi.l.php::getPopular',
		// Les sources d'un arrêté de la mercuriale : les prix saisis, les prix catalogue et les
		// produits rattachés, dont les ventes lues par ReferenceLib::getSales() découlent.
		'quotation/lib/Reference.l.php::getDaily',
		'quotation/lib/Reference.l.php::getCatalogues',
		'quotation/lib/Reference.l.php::getAttachments',
		// Le chiffre d'affaires qui fait entrer une structure dans la mercuriale sans qu'elle ait
		// rien saisi, pour le lot de nuit et pour le script de rattachement.
		'quotation/lib/Quotation.l.php::getTurnovers',
		// Les structures dont le prix catalogue est recalculé chaque nuit.
		'quotation/lib/Product.l.php::getActiveStructures',
		// Les statistiques de l'admin, filtrées sur décision du 2026-09-17. Elles écartent les
		// producteurs non installés (getNotInstalledSql) au lieu de ne garder que les installés : les
		// boutiques vendent aussi et restent comptées. Les statistiques d'utilisateurs ne lisent aucune
		// structure et ne sont pas concernées.
		'admin/lib/StatisticAi.l.php::computeParcDepth',
		'admin/lib/StatisticAi.l.php::computeParcTerritory',
		'admin/lib/StatisticAi.l.php::computeParcProduction',
		'admin/lib/StatisticAi.l.php::computeParcProductionRecent',
		'admin/lib/StatisticAi.l.php::computeParcDeclared',
		'admin/lib/StatisticAi.l.php::stockStructureClosed',
		'admin/lib/StatisticAi.l.php::statisticStructureCreated',
		'admin/lib/StatisticAi.l.php::statisticStructureActive',
		'admin/lib/StatisticAi.l.php::statisticProductionHarvest',
		'admin/lib/StatisticAi.l.php::statisticShopOrder',
		'admin/lib/StatisticAi.l.php::getPayments',
		'admin/lib/StatisticAi.l.php::structureIdsByDay',
		'admin/lib/StatisticAi.l.php::countByDay',
		'admin/lib/StatisticAi.l.php::sumMinutesByDay',
		'admin/lib/StatisticAi.l.php::getInvoices',
		'admin/lib/StatisticAi.l.php::getSales',
		'company/lib/StatisticAccountingAi.l.php::computeFeature',
		'company/lib/StatisticAccountingAi.l.php::computeVat',
		'company/lib/StatisticAccountingAi.l.php::computeHelp',
		'company/lib/StatisticAccountingAi.l.php::computeStripe',
		'company/lib/StatisticAccountingAi.l.php::computeSumup',
		'company/lib/StatisticAccountingAi.l.php::computeAccess',
		'company/lib/StatisticAccountingAi.l.php::computeSelfConsumption',
		// Les parcours de bases par structure servent aussi aux crons de paiement : le tri est fait par
		// la fonction de rappel, sur la liste que rend getNotInstalled().
		'company/lib/StatisticAccountingAi.l.php::computeFlows',
		'company/lib/StatisticAccountingAi.l.php::computeStructures',
		'company/lib/StatisticAccountingAi.l.php::computeInvoicing',
		'company/lib/StatisticAccountingAi.l.php::computePdp',
		'company/lib/StatisticAccountingAi.l.php::getNotInstalled',
	];

	// La condition posée sur une requête, et le tri en PHP d'une collection déjà lue (`->find(fn(…) => …['consentData'] …)`).
	// Une lecture de `['consentData']` hors d'un tri porte sur une seule structure, la connectée : ce n'est pas un agrégat.
	const CONSENT_PATTERN = '/whereConsentData\(|[\'"](m\d+\.)?consentData[\'"]\s*,|->(find|filter)\(fn\b[^;]*\[\'consentData\'\]/';
	// Les deux sous-requêtes de StructureLib, et la liste des statistiques comptables qui en dérive.
	const FILTER = '/\bget(Not)?Installed(Sql)?\(/';

	/**
	 * Les fichiers inspectés, indexés par leur chemin depuis le package : les `.l.php` et les pages.
	 * Les tests, les vues et l'UI lisent `consentData` pour afficher le choix d'une structure, pas pour
	 * agréger.
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
					(str_ends_with($relative, '.l.php') === FALSE and str_starts_with($relative, 'page/') === FALSE) or
					str_ends_with($relative, '.php') === FALSE
				) {
					continue;
				}

				$files[$package.'/'.$relative] = $file->getPathname();

			}

		}

		return $files;

	}

	/**
	 * Les unités d'un fichier : ses méthodes pour un `.l.php`, le fichier entier pour une page, dont
	 * les actions sont des closures sans nom.
	 * @return array<string, string> texte de chaque unité, par `chemin::méthode` ou `chemin`
	 */
	private function getUnits(string $relative, string $path): array {

		$source = file_get_contents($path);

		if(str_ends_with($relative, '.l.php') === FALSE) {
			return [$relative => $source];
		}

		$tokens = \PhpToken::tokenize($source);
		$units = [];
		$count = count($tokens);

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

				// Les commentaires ne comptent pas : un appel au filtre cité dans une explication n'en est pas un.
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

	public function testConsentReadersFilterInstalled(): void {

		$found = [];
		$units = [];

		foreach($this->getFiles() as $relative => $path) {

			foreach($this->getUnits($relative, $path) as $unit => $body) {

				$units[$unit] = $body;

				if(
					preg_match(self::CONSENT_PATTERN, $body) === 1 and
					preg_match(self::FILTER, $body) === 0
				) {
					$found[] = $unit;
				}

			}

		}

		$undeclared = array_diff($found, self::CONSENT_WITHOUT_FILTER);
		$stale = array_diff(self::CONSENT_WITHOUT_FILTER, $found);

		$this->assertEquals([], array_values($undeclared), 'agrégat sur le consentement sans filtre des structures installées, à filtrer ou à déclarer avec sa raison : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($stale), 'déclaration périmée dans CONSENT_WITHOUT_FILTER (méthode disparue, filtrée, ou qui ne lit plus le consentement) : '.implode(', ', $stale));

		$missing = [];

		foreach(self::CONSUMERS as $unit) {

			if(
				array_key_exists($unit, $units) === FALSE or
				preg_match(self::FILTER, $units[$unit]) === 0
			) {
				$missing[] = $unit;
			}

		}

		$this->assertEquals([], $missing, 'agrégat déclaré dans CONSUMERS introuvable ou sans filtre des structures installées : '.implode(', ', $missing));

	}

}
?>
