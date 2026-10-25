<?php
namespace website;

/**
 * Test-sentinelle : tout gabarit de page que le code nomme par son `fqn` est déclaré ici, avec la
 * migration qui l'installe, et existe dans la base.
 *
 * Un gabarit est une ligne de `websiteTemplate`, pas une constante : le code qui écrit
 * `TemplateLib::getByFqn('membership')` ou `['template']['fqn'] === 'news'` dépend d'une donnée
 * qu'aucun `.yml` ne décrit. Si la ligne manque, `getByFqn()` rend un gabarit vide et la comparaison
 * est toujours fausse : rien ne lève, la page naît sans son gabarit ou la règle ne s'applique jamais.
 * Celui qui ajoute un gabarit dans le code, ou qui tire un dépôt dont la migration n'est pas jouée,
 * n'a aucun moyen de le voir. Ce test le lui dit, en nommant la migration à jouer.
 *
 * Trois écarts, chacun avec le côté qui se corrige :
 * - un `fqn` nommé dans le code et absent de la déclaration : le code fait foi, on le déclare avec la
 *   migration qui insère sa ligne ;
 * - un `fqn` déclaré et absent de la base : la déclaration fait foi, on joue la migration nommée ;
 * - une ligne de la base absente de la déclaration : la base fait foi, on la déclare avec la
 *   migration qui l'a insérée.
 * Une déclaration dont la ligne a quitté la base tombe dans le deuxième cas, et se retire si la
 * migration qui la supprime est jouée.
 *
 * Le code se lit sur deux formes, les seules du dépôt : la comparaison sur `['template']['fqn']` et
 * l'appel `TemplateLib::getByFqn()`. Un `fqn` passé par une variable ne se voit pas ; le test de
 * détection, sur un code en ligne, prouve que les deux formes sont trouvées.
 *
 * Lancement : dev/test package=website file=TemplateCatalogSentinelAi
 */
class TemplateCatalogSentinelAiTest extends \Test {

	/** Chaque gabarit, avec la migration qui insère sa ligne. */
	const CATALOG = [
		'homepage' => 'installé avec le site, avant le dossier migration/ ; protégé de la suppression',
		'news' => 'installé avec le site, avant le dossier migration/ ; protégé de la suppression',
		'open' => 'installé avec le site, avant le dossier migration/ ; la page libre que l\'utilisateur ajoute',
		'producers' => 'migration/2026-10-04-website-gabarits-amap.md ; la liste des producteurs d\'une AMAP',
		'distributions' => 'migration/2026-10-04-website-gabarits-amap.md ; le calendrier des distributions d\'une AMAP',
		'membership' => 'migration/2026-10-04-website-gabarits-amap.md ; l\'adhésion à une AMAP, créée hors ligne sans formulaire ouvert',
		'contact' => 'migration/2026-10-04-website-gabarits-amap.md ; le formulaire de contact d\'une AMAP',
	];

	const PATTERNS = [
		'/\[\'template\'\]\[\'fqn\'\]\s*[!=]==\s*\'([a-zA-Z]+)\'/',
		'/TemplateLib::getByFqn\(\'([a-zA-Z]+)\'\)/',
	];

	public function testDetection(): void {

		$code = <<<'CODE'
		if($eWebpage['template']['fqn'] !== 'homepage') {}
		if($data->eWebpage['template']['fqn'] === 'news') {}
		$eTemplate = \website\TemplateLib::getByFqn('open');
		$eUnit = \product\UnitLib::getByFqn('kg');
		if($eDesign['fqn'] !== 'classique') {}
		CODE;

		$this->assertEquals(['homepage', 'news', 'open'], $this->scan($code), 'les deux formes sont trouvées, et seulement celles des gabarits');

	}

	public function testCodeIsDeclared(): void {

		$cited = [];

		foreach(\dev\SentinelLib::getFiles('.php') as $file => $path) {

			if(str_contains($file, '/test/')) {
				continue;
			}

			foreach($this->scan(file_get_contents($path)) as $fqn) {
				$cited[$fqn][] = $file;
			}

		}

		$this->assertTrue(count($cited) >= 3, 'le balayage n\'a trouvé que '.count($cited).' gabarits, le motif ne lit plus le code');

		$undeclared = array_diff_key($cited, self::CATALOG);

		$this->assertEquals([], array_keys($undeclared), 'le code fait foi, déclarer dans CATALOG ces gabarits, avec la migration qui insère leur ligne : '.implode(' · ', array_map(fn($fqn) => $fqn.' ('.implode(', ', array_unique($undeclared[$fqn])).')', array_keys($undeclared))));

	}

	public function testDeclarationIsInstalled(): void {

		$installed = Template::model()
			->select('fqn')
			->getColumn('fqn');

		$missing = array_diff(array_keys(self::CATALOG), $installed);
		$this->assertEquals([], array_values($missing), 'la déclaration fait foi, jouer la migration nommée pour installer ces gabarits : '.implode(' · ', array_map(fn($fqn) => $fqn.' ('.self::CATALOG[$fqn].')', $missing)));

		$unknown = array_diff($installed, array_keys(self::CATALOG));
		$this->assertEquals([], array_values($unknown), 'la base fait foi, déclarer dans CATALOG ces gabarits, avec la migration qui les a insérés : '.implode(' · ', $unknown));

	}

	/** @return string[] les `fqn` de gabarit nommés par ce code, sans doublon, dans l'ordre de lecture */
	private function scan(string $code): array {

		$found = [];

		foreach(self::PATTERNS as $pattern) {
			preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
			foreach($matches[1] as [$fqn, $offset]) {
				$found[$offset] = $fqn;
			}
		}

		ksort($found);

		return array_values(array_unique($found));

	}

}
?>
