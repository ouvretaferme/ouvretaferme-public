<?php
namespace learning;

/**
 * Les formations s'adressent à des structures de toute sorte, et la règle de rédaction du dépôt
 * (CLAUDE.md, « Rédaction ») y veut « structure » et jamais « ferme ». Elle a une exception, que
 * tranchée le 26/09 pour les formations comme le Gardien l'avait fait pour les groupes :
 * la structure membre d'un réseau ou d'un groupe s'y appelle une ferme. Ce qui sépare les deux est
 * le sens, pas la forme : aucun motif ne dit si « ferme » nomme la ferme membre ou la structure du
 * lecteur. Le contrôle ne tranche donc pas le sens, il tient l'inventaire : chaque chaîne du
 * package qui contient « ferm » est expliquée par une forme déclarée (la marque, le verbe fermer)
 * ou par une chaîne déclarée avec sa raison et son nombre d'occurrences. Une chaîne nouvelle, ou
 * une occurrence de plus d'une chaîne déclarée, échoue en nommant ses lignes, et c'est à celui qui
 * l'écrit de dire si elle nomme une ferme membre. Une déclaration qui ne sert plus échoue aussi.
 *
 * Les chaînes lues sont celles passées à `s()` ou `p()` dans le package (ses tests exceptés),
 * interpolées et heredoc comprises, et les thématiques que `page/configure/theme.p.php` sème, que
 * le catalogue affiche. `WritingSentinel` ne porte pas la règle pour tout le dépôt, parce que la
 * production et la mercuriale parlent à bon droit de fermes.
 *
 * Lancement : dev/test package=learning file=WordingSentinel
 */
class WordingSentinelTest extends \dev\SentinelTest {

	const PACKAGE = 'ouvretaferme/learning';

	const THEME_SEED = 'page/configure/theme.p.php';

	const RULE = 'ferme dans un texte des formations';

	/**
	 * Les formes qui contiennent « ferm » sans désigner une ferme, et pourquoi.
	 */
	const FORMS = [
		'/Ouvretaferme/u' => 'la marque, un nom propre',
		'/Fermé pour le moment/u' => 'le verbe fermer : la pause des inscriptions (lot T13), formulation F1 décidée le 19/10, sur la fiche, la carte et la page publique',
	];

	/**
	 * La ferme membre d'un réseau ou d'un groupe : c'est ainsi que la nomment les écrans
	 * de formation d'un réseau (arbitrage du 26/09, 17:15, sur la règle des groupes du Gardien).
	 * Chaque chaîne avec son nombre d'occurrences dans le fichier, depuis la racine du package.
	 */
	const MEMBER = [
		// Alert, DateEnrollment et DatePublic : la page publique d'un réseau (lot L10c), où la personne
		// qui s'inscrit sans compte dit sa ferme en texte libre, et dont le bandeau nomme les fermes du
		// réseau qui donnent leurs prix.
		'ui/Alert.u.php' => [
			'Donnez le nom de votre ferme en {value} caractères au plus.' => 1,
		],
		// L'attestation de suivi (lot L8b) nomme « ferme » la structure du stagiaire quand le réseau
		// ou le groupe auteur la délivre (forme A, une promotion, une date ou une inscription les lie) ; délivrée par
		// Ouvretaferme seule (forme B), elle dit « structure ». L'attestation de présence (lot L8f),
		// toujours délivrée par le réseau de la date, nomme de même la ferme choisie ou saisie à
		// l'inscription.
		'ui/Attestation.u.php' => [
			'{user}, de la ferme {structure},' => 2,
		],
		'ui/CourseStart.u.php' => [
			'Les suivantes s\'ajoutent depuis la formation, et les fermes du réseau s\'inscrivent à chacune.' => 1,
		],
		'ui/DateEnrollment.u.php' => [
			'Ferme' => 1,
		],
		'ui/DatePublic.u.php' => [
			'Formations et relevés de prix des fermes du réseau' => 1,
		],
		'ui/Progress.u.php' => [
			'Toutes les fermes' => 1,
		],
	];

	/**
	 * Une thématique semée nomme le sujet enseigné et non la structure du lecteur, au même titre que
	 * « Production et maraîchage » (porte L12c du 26/09).
	 */
	const THEMES = [
		self::THEME_SEED => [
			'Stratégie de la ferme' => 1,
		],
	];

	/**
	 * L'en-tête de colonne de l'export CSV du suivi, que le lot L12b porte avec le nom du fichier
	 * exporté : la déclaration part avec son hunk.
	 */
	const EXPORT = [
		'ui/Progress.u.php' => [
			'Ferme' => 1,
		],
	];

	/**
	 * Les chaînes du package qui contiennent « ferm », par fichier : `[ligne, texte]`.
	 *
	 * @return array<string, array<int, array{0: int, 1: string}>>
	 */
	private function getTexts(): array {

		$root = \dev\SentinelLib::getRoot().'/'.self::PACKAGE;
		$texts = [];

		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

		foreach($files as $file) {

			$path = substr($file->getPathname(), strlen($root) + 1);

			if(str_ends_with($path, '.php') === FALSE or str_starts_with($path, 'test/')) {
				continue;
			}

			$tokens = \dev\SentinelLib::getTokens(file_get_contents($file->getPathname()));

			$found = ($path === self::THEME_SEED)
				? $this->findStrings($tokens)
				: $this->findTranslated($tokens);

			if($found !== []) {
				$texts[$path] = $found;
			}

		}

		ksort($texts);

		return $texts;

	}

	/**
	 * Les chaînes passées à `s()` ou `p()` qui contiennent « ferm ».
	 *
	 * @return array<int, array{0: int, 1: string}>
	 */
	private function findTranslated(array $tokens): array {

		$found = [];

		foreach($tokens as $i => [$type, $text]) {

			if(
				$type !== T_STRING or
				($text !== 's' and $text !== 'p') or
				($tokens[$i + 1][0] ?? NULL) !== '(' or
				in_array($tokens[$i - 1][0] ?? NULL, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], TRUE)
			) {
				continue;
			}

			$closing = \dev\SentinelLib::getClosing($tokens, $i + 1);

			$found = array_merge($found, $this->findStrings(array_slice($tokens, $i + 2, $closing - $i - 2)));

		}

		return $found;

	}

	/**
	 * Les chaînes littérales qui contiennent « ferm », dans l'ordre du code. Une chaîne interpolée
	 * ou un heredoc arrive en morceaux : chaque morceau est lu pour lui-même.
	 *
	 * @return array<int, array{0: int, 1: string}>
	 */
	private function findStrings(array $tokens): array {

		$found = [];

		foreach($tokens as $token) {

			$string = self::getString($token);

			if($string === NULL) {
				continue;
			}

			$string = stripslashes($string);

			if(mb_stripos($string, 'ferm') !== FALSE) {
				$found[] = [$token[2], $string];
			}

		}

		return $found;

	}

	/**
	 * Retire des chaînes trouvées ce que les formes expliquent : rend les occurrences restantes,
	 * sous la clé `fichier « chaîne »` et par ligne, et les formes qui n'ont rien expliqué.
	 *
	 * @return array{found: array<string, int[]>, formsUnused: string[]}
	 */
	private function explain(array $texts, array $forms): array {

		$found = [];
		$formsUsed = [];

		foreach($texts as $file => $occurrences) {

			foreach($occurrences as [$line, $string]) {

				$rest = $string;

				foreach($forms as $pattern => $reason) {

					$cleaned = preg_replace($pattern, '', $rest);

					if($cleaned !== $rest) {
						$formsUsed[$pattern] = TRUE;
						$rest = $cleaned;
					}

				}

				if(mb_stripos($rest, 'ferm') !== FALSE) {
					$found[self::getKey($file, $string)][] = $line;
				}

			}

		}

		return [
			'found' => $found,
			'formsUnused' => array_values(array_diff(array_keys($forms), array_keys($formsUsed))),
		];

	}

	/**
	 * Les déclarations, sous la clé `fichier « chaîne »`, avec leur nombre d'occurrences.
	 *
	 * @return array<string, int>
	 */
	private static function getDeclared(array ...$lists): array {

		$declared = [];

		foreach($lists as $list) {

			foreach($list as $file => $strings) {

				foreach($strings as $string => $count) {
					$key = self::getKey($file, $string);
					$declared[$key] = ($declared[$key] ?? 0) + $count;
				}

			}

		}

		return $declared;

	}

	private static function getKey(string $file, string $string): string {
		return $file.' « '.$string.' »';
	}

	/**
	 * Le détecteur trouve le nom dans une chaîne traduite, au singulier, au pluriel, capitalisé,
	 * dans le second argument de `p()`, dans une chaîne interpolée et dans un heredoc, et laisse le
	 * code autour : une chaîne hors de `s()`, une méthode `->s()`, un commentaire.
	 */
	public function testDetector(): void {

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			$a = s("Une ferme rattachée");
			$b = '/ferme/'.s("Fermer").$e->s("ferme");
			// s("ferme")
			$c = p("{value} structure", "{value} fermes", 2);
			$d = s("Fermes cochées", ['x' => 'ferme']);
			$f = s("La ferme {$name} est rattachée");
			$g = s(<<<TXT
			Une ferme en heredoc
			TXT);
			PHP);

		$this->assertEquals([
			[2, 'Une ferme rattachée'],
			[3, 'Fermer'],
			[5, '{value} fermes'],
			[6, 'Fermes cochées'],
			[6, 'ferme'],
			[7, 'La ferme '],
			[9, "Une ferme en heredoc\n"],
		], $this->findTranslated($tokens));

	}

	/**
	 * Les formes retirent ce qu'elles expliquent, sans couvrir le nom qui reste à côté, et une
	 * forme qui n'explique rien est rendue.
	 */
	public function testExplain(): void {

		$result = $this->explain(
			['ui/A.u.php' => [[3, 'Fermer'], [4, 'Fermer la ferme'], [5, 'Ouvretaferme'], [6, 'Ferme'], [9, 'Ferme']]],
			['/\bFermer\b/u' => 'verbe', '/Ouvretaferme/u' => 'marque', '/inutile/u' => 'rien']
		);

		$this->assertEquals([
			'ui/A.u.php « Fermer la ferme »' => [4],
			'ui/A.u.php « Ferme »' => [6, 9],
		], $result['found']);
		$this->assertEquals(['/inutile/u'], $result['formsUnused']);

	}

	/**
	 * Le nombre déclaré tient : une occurrence de plus d'une chaîne déclarée échoue en nommant ses
	 * lignes, une de moins signale la déclaration périmée, et les déclarations de deux listes sur
	 * la même chaîne s'additionnent.
	 */
	public function testCount(): void {

		// Le décor prend le fichier de ce test, qui existe : `compare()` ne dit « absent de l'arbre »
		// que sur un chemin qu'il ne trouve pas, et c'est l'autre forme du message qui s'éprouve ici.
		$file = 'ouvretaferme/learning/test/WordingSentinel.t.php';

		$found = [$file.' « Ferme »' => [6, 9], $file.' « Fermes »' => [12]];

		$occurrences = [];
		foreach($found as $key => $lines) {
			foreach($lines as $line) {
				$occurrences[] = $key.':'.$line;
			}
		}

		$this->assertEquals(
			['new' => [$file.' « Ferme » (2 pour 1 déclarée(s), lignes 6, 9)'], 'stale' => [$file.' « Fermes » (2 déclarée(s) pour 1)']],
			\dev\SentinelLib::compare($occurrences, self::getDeclared([$file => ['Ferme' => 1]], [$file => ['Fermes' => 2]]))
		);

		// Et sur un chemin que l'arbre ne porte pas, la garde du retard sur `origin/main` sort, avec
		// une commande qui ne garde que le chemin et non ce que la clé compte à sa suite.
		$this->assertEquals(
			['new' => [], 'stale' => ['ui/A.u.php « Fermes » (absent de l\'arbre, 2 déclarée(s). Vérifier `git cat-file -e origin/main:ui/A.u.php` avant de baisser)']],
			\dev\SentinelLib::compare([], self::getDeclared([], ['ui/A.u.php' => ['Fermes' => 2]]))
		);

		$this->assertEquals([$file.' « Ferme »' => 2], self::getDeclared([$file => ['Ferme' => 1]], [$file => ['Ferme' => 1]]));

	}

	public function testWording(): void {

		$result = $this->explain($this->getTexts(), self::FORMS);

		foreach($result['found'] as $key => $lines) {
			$this->addFound(self::RULE, $key, $lines);
		}

		$this->assertEquals([], $result['formsUnused'], 'forme périmée, la retirer de WordingSentinel : '.implode(' · ', $result['formsUnused']));
		$this->assertRatchet(self::RULE, self::getDeclared(self::MEMBER, self::THEMES, self::EXPORT), '« ferme » dans un texte des formations, écrire « structure », ou déclarer dans WordingSentinel la ferme membre d\'un réseau ou d\'un groupe');

	}

}
?>
