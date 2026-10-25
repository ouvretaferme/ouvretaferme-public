<?php
namespace design;

/**
 * Test-sentinelle : un chiffre et son intitulé n'ont qu'une famille, `util-figure`.
 *
 * Décidé le 2026-10-01, réponse cliquée « util-figure (Recommandé) » : `util-figure*`
 * survit, la variation de `util-metric*` y entre sous le nom `util-figure-variation`, et les
 * écrans qui emploient encore `util-metric*` se rendent sur une ligne qu'on réécrit. La convention
 * est dans le skill `ui`, sa démonstration sur la page du design system (`util-figure-variation`).
 *
 * La sentinelle lit chaque fichier PHP du dépôt et relève les lignes qui portent `util-metric`,
 * nom entier ou préfixe d'une classe assemblée (`'util-metric-variation-'.$direction`). L'existant
 * est déclaré par fichier, en nombre de lignes, et ne peut que baisser. Il est pris sur
 * `origin/main` à `f6d00526b` le 2026-10-01, jamais sur l'arbre de travail :
 * `git grep -c "util-metric" origin/main -- '*.php'` rend 16 fichiers et 69 lignes, dont les 6 de
 * la page du design system, retirées par le lot qui pose cette sentinelle. Restent 63 lignes dans
 * 15 fichiers. Les deux fichiers de test déclarés lisent le balisage de leur écran et se rendent
 * avec lui.
 *
 * Ce qu'elle ne voit pas, et qui reste à la relecture :
 * - une feuille de package qui cible la famille (`commercialisation/quotation/static/css/synthesisAi.css`),
 *   à rendre avec l'écran qu'elle habille ;
 * - une classe dont le préfixe lui-même est assemblé (`'util-'.$family.'-value'`), aucune sur
 *   `origin/main` ;
 * - les règles `.util-metric-*` de `component.css`, qui se retirent quand la déclaration est vide.
 *
 * Lancement : dev/test package=design file=FigureFamilySentinelAi
 */
class FigureFamilySentinelAiTest extends \dev\SentinelTest {

	const RULE = 'figureFamily';

	const RETIRED = 'util-metric';

	const DECLARED = [
		'accounting/cash/ui/Header.u.php' => 10,
		'accounting/financialYear/ui/FinancialYear.u.php' => 2,
		'accounting/overview/ui/DashboardAi.u.php' => 7,
		'commercialisation/quotation/ui/Contribute.u.php' => 4,
		'commercialisation/quotation/ui/Public.u.php' => 4,
		'commercialisation/quotation/ui/Response.u.php' => 4,
		'commercialisation/quotation/ui/Result.u.php' => 4,
		'commercialisation/quotation/ui/SynthesisAi.u.php' => 5,
		'production/plant/ui/CommunityAi.u.php' => 2,
	];

	/**
	 * Le nombre de fichiers PHP lus, pour prouver que la lecture a eu lieu : à zéro, la règle
	 * serait verte sans avoir rien regardé.
	 */
	private int $read = 0;

	public function init() {

		foreach(\dev\SentinelLib::getFiles('.php') as $file => $path) {

			// Les sentinelles nomment la famille pour la déclarer (ABSENT de DesignSystemSentinelAi,
			// et ce fichier) : les lire ferait de chaque déclaration un emploi.
			if(fnmatch('*Sentinel*.t.php', basename($file))) {
				continue;
			}

			$this->read++;

			$this->addFound(self::RULE, $file, self::findLines((string)file_get_contents($path)));

		}

	}

	/**
	 * Les lignes d'un source qui portent la famille en retrait, une fois chacune.
	 *
	 * @return int[]
	 */
	public static function findLines(string $source): array {

		$lines = [];

		foreach(explode("\n", $source) as $index => $line) {

			if(str_contains($line, self::RETIRED)) {
				$lines[] = $index + 1;
			}

		}

		return $lines;

	}

	public function testDetectors(): void {

		$code = <<<'PHP'
			<?php
			$h .= '<div class="util-figure-value">12</div>';
			$h .= '<div class="util-metric-label">'.$label.'</div><div class="util-metric-value">12</div>';
			$h .= '<div class="util-metric-variation '.($direction ? 'util-metric-variation-'.$direction : '').'">';
			$h .= '<div class="util-figure-variation util-figure-variation-up">';
			$h .= '<div class="synthesis-metric">';
			PHP;

		$this->assertEquals([3, 4], self::findLines(str_replace("\t", '', $code)));

	}

	public function testFigureFamily(): void {

		$this->assertTrue($this->read >= 1000, 'les fichiers PHP ne se lisent plus ('.$this->read.' lus, plus de 4 000 le 2026-10-01) : aucun emploi de '.self::RETIRED.' ne paraîtrait');

		$this->assertRatchet(self::RULE, self::DECLARED, 'ligne qui emploie la famille '.self::RETIRED.', en retrait (le code fait foi : écrire le chiffre en util-figure, util-figure-value, util-figure-label et util-figure-variation, démontrés sur la page du design system ; un fichier neuf ne se déclare pas, et un fichier déjà déclaré ne monte pas)');

	}

}
?>
