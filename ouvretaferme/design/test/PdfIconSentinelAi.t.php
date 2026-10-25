<?php
namespace design;

/**
 * Test-sentinelle : un PDF porte l'icône `file-pdf`, jamais `file-earmark-pdf`.
 *
 * Deux icônes pour un même geste font croire à deux gestes (skill `ui`, « Conventions de
 * l'interface », « Un même geste porte la même icône dans tout le produit »). Le dépôt en portait
 * deux pour le PDF, et la même pièce changeait d'icône selon qu'on la lisait chez l'adhérent ou chez
 * le producteur.
 *
 * Le relevé lit les chaînes du code PHP (jetons de chaîne, commentaires écartés) qui contiennent
 * `file-earmark-pdf`, donc `\Asset::icon('file-earmark-pdf')` comme une valeur de tableau rendue
 * plus loin par `\Asset::icon()`. Seuls les fichiers que git suit sont lus
 * (`\dev\SentinelLib::getFiles()`), et les sentinelles sont écartées, leurs détecteurs portant la
 * forme fautive.
 *
 * Les exceptions sont permanentes, pas une dette : une table qui donne une icône à chaque type de
 * fichier déposé emploie la famille `file-earmark-*` d'un bout à l'autre, et `file-earmark-pdf` y
 * répond à `file-earmark-image`. Chacune se déclare avec sa raison.
 *
 * Ce qu'elle ne voit pas, et qui reste à la relecture : une icône posée en JavaScript ou en CSS,
 * et un nom d'icône recomposé par concaténation (`'file-earmark-'.$type`).
 *
 * Lancement : dev/test package=design file=PdfIconSentinelAi
 */
class PdfIconSentinelAiTest extends \dev\SentinelTest {

	const ICON = 'file-earmark-pdf';

	/**
	 * Les fichiers où `file-earmark-pdf` est légitime, avec le nombre d'occurrences et la raison.
	 */
	const EXCEPTIONS = [
		'ouvretaferme/learning/ui/Attachment.u.php' => [1, 'table des types de fichier d\'une pièce jointe, en famille file-earmark-*, face à file-earmark-image'],
		'ouvretaferme/learning/ui/DateSheet.u.php' => [1, 'table des types de fichier d\'une feuille de session, en famille file-earmark-*, face à file-earmark-image'],
	];

	private array $occurrences = [];

	public function init() {

		foreach($this->getPhpTokens() as $file => $tokens) {

			foreach(self::findIcon($tokens) as $line) {
				$this->occurrences[] = $file.':'.$line;
			}

		}

	}

	/**
	 * Les lignes des chaînes qui portent l'icône, une fois par chaîne.
	 *
	 * @return int[]
	 */
	private static function findIcon(array $tokens): array {

		$lines = [];

		foreach($tokens as [$type, $text, $line]) {

			if(
				($type === T_CONSTANT_ENCAPSED_STRING or $type === T_ENCAPSED_AND_WHITESPACE) and
				str_contains($text, self::ICON)
			) {
				$lines[] = $line;
			}

		}

		return $lines;

	}

	public function testDetectors(): void {

		$tokens = \dev\SentinelLib::getTokens(<<<'PHP'
			<?php
			$a = \Asset::icon('file-earmark-pdf');
			$b = ['pdf' => "file-earmark-pdf", 'image' => 'file-earmark-image'];
			$c = \Asset::icon('file-pdf');
			// file-earmark-pdf dans un commentaire
			$d = '<i class="bi bi-file-earmark-pdf"></i>';
			PHP);

		$this->assertEquals([2, 3, 6], self::findIcon($tokens));

	}

	public function testPdfIcon(): void {

		$result = \dev\SentinelLib::compare($this->occurrences, array_map(fn(array $exception) => $exception[0], self::EXCEPTIONS));

		$this->assertEquals([], $result['new'], 'icône file-earmark-pdf hors des tables de types de fichier (le skill ui fait foi : un PDF porte file-pdf, corriger le code ; une table qui donne une icône à chaque type de fichier se déclare dans EXCEPTIONS avec sa raison) : '.implode(' · ', $result['new']));
		$this->assertEquals([], $result['stale'], 'exception déclarée qui ne porte plus file-earmark-pdf (le code fait foi : retirer l\'entrée de EXCEPTIONS) : '.implode(' · ', $result['stale']));

	}

}
?>
