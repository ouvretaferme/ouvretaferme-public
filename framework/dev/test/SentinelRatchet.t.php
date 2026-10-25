<?php
namespace dev;

/**
 * Contrôle du contrôle : les outils de `SentinelLib` sur lesquels reposent toutes les
 * sentinelles. Un cliquet qui ne sait pas échouer rendrait vertes toutes celles qui l'emploient.
 *
 * Lancement : dev/test package=dev file=SentinelRatchet
 */
class SentinelRatchetTest extends \Test {

	public function testCompareNamesBothDirections(): void {

		// `shop.yml` est dans le dépôt, `d.php` non : les deux écarts comptent zéro occurrence, et
		// appellent le geste inverse l'un de l'autre.
		$result = SentinelLib::compare(
			['a.php:3', 'a.php:9', 'b.php:1', 'c.php:4'],
			['a.php' => 1, 'b.php' => 1, 'commercialisation/shop/shop.yml' => 2, 'd.php' => 2]
		);

		$this->assertEquals(['a.php (2 pour 1 déclarée(s), lignes 3, 9)', 'c.php (1 pour 0 déclarée(s), lignes 4)'], $result['new'], 'occurrence de trop et fichier non déclaré');
		$this->assertEquals([
			'commercialisation/shop/shop.yml (2 déclarée(s) pour 0)',
			'd.php (absent de l\'arbre, 2 déclarée(s). Vérifier `git cat-file -e origin/main:d.php` avant de baisser)',
		], $result['stale'], 'dette résorbée et déclaration dont le fichier manque');

		$this->assertEquals(['new' => [], 'stale' => []], SentinelLib::compare(['a.php:3'], ['a.php' => 1]), 'déclaration exacte');

		$this->assertEquals(['a.php' => 2, 'c.php' => 1], SentinelLib::getNewCounts(['a.php:3', 'a.php:9', 'b.php:1', 'c.php:4'], ['a.php' => 1, 'b.php' => 1]), 'comptes des entrées neuves, dans l\'ordre de compare()');

	}

	public function testCompareIgnoresAnUntrackedFile(): void {

		// Un fichier présent sur le disque que git ne suit pas n'a aucun geste juste : le déclarer
		// rougit ceux qui tirent, ne pas le déclarer rougit l'arbre de son auteur. Le témoin se
		// fabrique ici plutôt que de citer un chemin du dépôt, qui serait suivi dès le lendemain.
		$untracked = SentinelLib::getRoot().'/framework/dev/test/.untracked-'.getmypid().'.php';
		file_put_contents($untracked, '<?php'."\n");

		try {

			$relative = 'framework/dev/test/'.basename($untracked);

			$result = SentinelLib::compare([], [$relative => 2, 'jamais/nulle/part.php' => 1]);

			$this->assertEquals([], $result['new'], 'un fichier non suivi ne produit aucune occurrence nouvelle');

			$stale = ['jamais/nulle/part.php (absent de l\'arbre, 1 déclarée(s). Vérifier `git cat-file -e origin/main:jamais/nulle/part.php` avant de baisser)'];

			// Sans git, `SentinelLib::getTracked()` rend NULL et le repli annoncé est le système de
			// fichiers : le filtre que cette méthode éprouve est alors désactivé par construction,
			// et le témoin, présent sur le disque, redevient une déclaration au-dessus de ce qu'on
			// trouve. Affirmer l'inverse faisait échouer la méthode pour la seule raison que git
			// manque, ce qui se lit comme une déclaration périmée, l'inverse de la vérité. Relevé
			// par Coquelicow le 2026-10-03, « 2 déclarée(s) pour 0 » sur un conteneur sans git, et
			// imputé à deux passes simultanées, qui n'y sont pour rien : le témoin porte le pid.
			if(SentinelLib::getTracked() === NULL) {
				array_unshift($stale, $relative.' (2 déclarée(s) pour 0)');
			}

			$this->assertEquals(
				$stale,
				$result['stale'],
				'l\'entrée du fichier non suivi dort, celle du chemin qui ne désigne rien reste nommée'
			);

			$this->assertEquals(
				SentinelLib::getTracked() === NULL,
				isset(SentinelLib::getFiles('.php')[$relative]),
				'le relevé des fichiers écarte ce que git ne suit pas, et le porte quand git manque'
			);

		} finally {
			unlink($untracked);
		}

	}

	public function testTokensCarryLines(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			/* commentaire
			   sur deux lignes */
			$a = "x
			y";
			$b = (1);
			PHP);

		$lines = [];

		foreach($tokens as [$type, $text, $line]) {
			$lines[] = $text.'@'.$line;
		}

		$this->assertEquals(['<?php'."\n".'@1', '$a@4', '=@4', '"x'."\n".'y"@4', ';@5', '$b@6', '=@6', '(@6', '1@6', ')@6', ';@6'], $lines, 'lignes des jetons d\'un caractère, commentaires retirés');

		$this->assertEquals(9, SentinelLib::getClosing($tokens, 7), 'parenthèse fermante');

	}

	public function testNewEntryNamesItsCause(): void {

		$cause = fn(string $path, ?array $behind, array $written = [], array $upstream = ['a.php', 'Test.t.php'], ?bool $covered = NULL) => new \ReflectionMethod(SentinelLib::class, 'getNewCause')->invoke(NULL, $path, 'Test.t.php', $behind, $written, $upstream, $covered);

		$this->assertEquals('amont non lu, aucune cause établie', $cause('a.php', NULL), 'git muet');
		$this->assertTrue(str_starts_with($cause('a.php', ['Test.t.php'], ['a.php']), '/pull d\'abord'), 'un arbre en retard passe avant une écriture en vol');
		$this->assertTrue(str_starts_with($cause('b.php', [], ['b.php']), 'fichier neuf'), 'un fichier absent de l\'amont');
		$this->assertTrue(str_starts_with($cause('a.php', [], ['a.php', 'Test.t.php']), 'écriture en vol'), 'une occurrence modifiée dans l\'arbre passe avant la déclaration');
		$this->assertTrue(str_starts_with($cause('a.php', [], ['Test.t.php']), 'le fichier de la déclaration'), 'une déclaration réécrite dans l\'arbre');

		$this->assertTrue(str_starts_with($cause('a.php', [], ['Test.t.php'], covered: FALSE), 'le fichier de la déclaration'), 'une déclaration réécrite qui ne couvre pas l\'arbre dans l\'amont non plus');
		$this->assertTrue(str_starts_with($cause('a.php', [], ['Test.t.php'], covered: TRUE), 'l\'arbre a retiré une entrée que l\'amont porte, ne rien déclarer'), 'une déclaration de l\'amont qui couvre l\'arbre');

		// Un fichier suivi, identique des deux côtés et déclaré nulle part : le rouge est celui de
		// l'amont, et le verdict nomme le dernier commit du fichier, sauf quand git ne répond pas.
		$upstream = new \ReflectionMethod(SentinelLib::class, 'getNewCause')->invoke(NULL, 'framework/dev/lib/Sentinel.l.php', 'framework/dev/test/SentinelRatchet.t.php', [], [], ['framework/dev/lib/Sentinel.l.php', 'framework/dev/test/SentinelRatchet.t.php']);
		$this->assertTrue(str_starts_with($upstream, 'rouge dans origin/main, à corriger ou à déclarer par ') or $upstream === 'amont non lu, aucune cause établie', 'rouge dans l\'amont : '.$upstream);

		$this->assertTrue(str_starts_with(SentinelLib::explainNew(['b.php:doCreate (ligne 3)'], 'Test.t.php')[0], 'b.php:doCreate (ligne 3) ['), 'une entrée de assertDeclared garde sa forme et reçoit son verdict');

		// Sans constante nommée, la déclaration de l'amont n'est pas relue et ne conclut rien.
		$explained = SentinelLib::explainNew(['a.php (2 pour 1 déclarée(s), lignes 3, 9)'], 'Test.t.php', ['a.php' => 2], ['a.php' => 1])[0];
		$this->assertTrue(str_contains($explained, '[arbre 2, amont ') and str_contains($explained, ' ; déclaré 1, amont non relu ; '), 'les deux comptes précèdent le verdict : '.$explained);

	}

	public function testReadDeclaration(): void {

		$code = <<<'PHP'
			<?php
			class A {
				const FIRST = [
					'a.php' => 3,
				];
				const SECOND = [
					'a.php' => 5, // le même chemin, dans une autre constante
					'b.php:doCreate' => 'une raison, ' . 'écrite en deux',
					'c\'d.php' => 1,
				];
				const COMPUTED = [
					self::PATH => 1,
				];
			}
			PHP;

		$this->assertEquals(['a.php' => 5, 'b.php:doCreate' => 'présente', 'c\'d.php' => 1], SentinelLib::readDeclaration($code, 'SECOND'), 'la constante nommée, et non la première qui déclare le chemin');
		$this->assertEquals(['a.php' => 3], SentinelLib::readDeclaration($code, 'FIRST'), 'première constante');
		$this->assertEquals(NULL, SentinelLib::readDeclaration($code, 'COMPUTED'), 'une clé calculée ne se lit pas');
		$this->assertEquals(NULL, SentinelLib::readDeclaration($code, 'ABSENT'), 'une constante absente');

	}

	public function testFilesStayInsideRepository(): void {

		$files = SentinelLib::getFiles('.yml');

		$this->assertTrue(isset($files['commercialisation/shop/shop.yml']), 'un .yml de package est lu');

		$excluded = array_filter(array_keys($files), fn(string $file) => preg_match('#(^|/)(node_modules|nodejs|perso|vendor|\.git|\.claude)/#', $file) === 1);

		$this->assertEquals([], array_values($excluded), 'répertoires exclus : '.implode(', ', $excluded));

	}

}
?>
