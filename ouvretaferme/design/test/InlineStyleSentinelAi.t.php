<?php
namespace design;

/**
 * Test-sentinelle : un attribut `style` ne porte ni espacement ni fond d'état.
 *
 * Une marge haute, un remplissage ou une couleur de fond écrits dans un attribut `style` d'un
 * fichier de présentation refont à la main ce que le socle donne déjà : les marges du composant et
 * les classes `mt-*` et `mb-*` pour l'espacement, l'état du composant pour le fond. Écrit en ligne,
 * l'écart échappe aux feuilles, donc à tout contrôle qui les lit, et il n'est vu qu'en recette.
 * C'est la première forme que borne la piste 2 de la problématique des retours d'interface
 * (retenue par Vincent le 2026-09-30) : une forme lisible sans juger le sens, refusée avant la
 * recette.
 *
 * Trois propriétés seulement, `margin-top`, `padding` et ses côtés, `background-color`. `width` et
 * `grid-column`, souvent calculés par le PHP, restent hors de la règle, comme toute autre
 * propriété : `style="` seul compte 898 attributs et ne mordrait sur rien.
 *
 * L'existant est déclaré fichier par fichier, en nombre, et ne peut que baisser. Il est pris sur
 * `origin/main` à `d7f3203fd` le 2026-10-01, jamais sur l'arbre de travail :
 * `git grep -oE 'style="[^"]*(margin-top|padding|background-color)' origin/main -- '*.u.php' '*.v.php'`
 * rend 257 attributs dans 60 fichiers. Seuls les fichiers que git suit sont lus
 * (`\dev\SentinelLib::getFiles()`), donc deux postes alignés sur le même commit rendent le même
 * verdict.
 *
 * Ce qu'elle ne voit pas, et qui reste à la relecture :
 * - un attribut dont la valeur arrive par une variable (`style="'.$style.'"`) ;
 * - un attribut écrit entre guillemets échappés dans une chaîne à guillemets doubles ;
 * - une couleur de fond légitime parce qu'elle est une donnée (la couleur d'une espèce, d'un
 *   thème) : elle se compte comme les autres, et c'est la déclaration du fichier qui la porte.
 *
 * Lancement : dev/test package=design file=InlineStyleSentinelAi
 */
class InlineStyleSentinelAiTest extends \dev\SentinelTest {

	const RULE = 'inlineStyle';

	const DECLARED = [
		'accounting/bank/ui/Cashflow.u.php' => 3,
		'accounting/cash/ui/Cash.u.php' => 1,
		'accounting/cash/ui/Register.u.php' => 1,
		'accounting/financialYear/ui/Fec.u.php' => 1,
		'accounting/invoicing/view/doc.v.php' => 6,
		'accounting/journal/ui/JournalCode.u.php' => 4,
		'accounting/preaccounting/view/sale.v.php' => 1,
		'accounting/taxReturn/ui/Pdf.u.php' => 1,
		'base/mail/ui/Contact.u.php' => 1,
		'base/mail/ui/InternalAi.u.php' => 2,
		'ouvretaferme/main/ui/MailBlockAi.u.php' => 44,
		'ouvretaferme/main/ui/MailSoftwareAi.u.php' => 2,
		'ouvretaferme/main/ui/MailStructure.u.php' => 12,
		'base/media/ui/LearningQuizAi.u.php' => 1,
		'base/structure/ui/Admin.u.php' => 5,
		'base/structure/ui/Survey.u.php' => 3,
		'commercialisation/payment/ui/PaymentTransaction.u.php' => 1,
		'base/product/ui/Csv.u.php' => 1,
		'base/product/ui/Product.u.php' => 1,
		'commercialisation/quotation/ui/SynthesisAi.u.php' => 1,
		'commercialisation/selling/ui/Analyze.u.php' => 8,
		'commercialisation/selling/ui/CustomerGroup.u.php' => 1,
		// La couleur d'un groupe de clients, choisie par la structure : aucune classe ne peut la porter
		'commercialisation/selling/ui/Campaign.u.php' => 2,
		'commercialisation/selling/ui/Customer.u.php' => 4,
		'commercialisation/selling/ui/Grid.u.php' => 1,
		'commercialisation/selling/ui/Invoice.u.php' => 2,
		'commercialisation/selling/ui/Item.u.php' => 1,
		'commercialisation/selling/ui/Sale.u.php' => 5,
		'commercialisation/shop/ui/CustomizeAi.u.php' => 2,
		'commercialisation/shop/ui/OpenAi.u.php' => 3,
		'commercialisation/shop/ui/Point.u.php' => 2,
		'framework/util/ui/Text.u.php' => 1,
		'internal/communication/ui/WebinaireSubscription.u.php' => 2,
		'ouvretaferme/admin/ui/StatisticAi.u.php' => 2,
		'internal/securing/ui/Log.u.php' => 2,
		'ouvretaferme/design/ui/Design.u.php' => 30,
		'ouvretaferme/learning/ui/Customize.u.php' => 2,
		'ouvretaferme/website/ui/CustomizeAi.u.php' => 3,
		'ouvretaferme/website/ui/Manage.u.php' => 1,
		'production/map/ui/Plan.u.php' => 1,
		// La couleur de chaque action sur la barre des interventions de la fiche de temps : une donnée de la
		// structure, comme le rond de `work/ui/Action.u.php` (R-Retours-0410-soir, R2).
		'production/planning/ui/TimeDay.u.php' => 1,
		'production/plant/ui/Analyze.u.php' => 3,
		'production/plant/ui/Family.u.php' => 1,
		'production/plant/ui/Forecast.u.php' => 1,
		'production/plant/ui/Plant.u.php' => 1,
		'production/plant/ui/VarietyRatingAi.u.php' => 1,
		'production/sequence/ui/Crop.u.php' => 3,
		'production/sequence/ui/Flow.u.php' => 1,
		'production/sequence/ui/Sequence.u.php' => 1,
		'production/sequence/ui/Slice.u.php' => 1,
		'production/series/ui/CompareAi.u.php' => 2,
		'production/series/ui/Cultivation.u.php' => 2,
		'production/series/ui/Place.u.php' => 1,
		'production/series/ui/Planning.u.php' => 2,
		'production/series/ui/Series.u.php' => 2,
		'production/work/ui/Action.u.php' => 1,
	];

	public function init() {

		foreach(\dev\SentinelLib::getFiles('.php') as $file => $path) {

			if(self::isPresentation($file) === FALSE) {
				continue;
			}

			$this->addFound(self::RULE, $file, self::findInlineStyle(explode("\n", file_get_contents($path))));

		}

	}

	/**
	 * Une ligne est relevée autant de fois qu'elle porte d'attributs fautifs. Un attribut qui cumule
	 * deux des propriétés compte une fois.
	 *
	 * @param string[] $lines
	 * @return int[]
	 */
	private static function findInlineStyle(array $lines): array {

		$found = [];

		foreach($lines as $position => $line) {

			$count = preg_match_all('/style="[^"]*(?:margin-top|padding|background-color)/', $line);

			for($i = 0; $i < $count; $i++) {
				$found[] = $position + 1;
			}

		}

		return $found;

	}

	public function testDetectors(): void {

		$lines = explode("\n", <<<'HTML'
			<div style="margin-top: 1rem">
			<div style="color: red; padding-left: 0"> <span style="background-color: var(--muted)">
			<div style="width: 50%"> <div style="grid-column: span 2">
			<div class="padding" style="margin-bottom: 1rem">
			<div style="margin-top: 0; padding: 0">
			HTML);

		$this->assertEquals([1, 2, 2, 5], self::findInlineStyle($lines));

	}

	public function testInlineStyle(): void {
		$this->assertRatchet(self::RULE, self::DECLARED, 'margin-top, padding ou background-color dans un attribut style (le code fait foi : l\'espacement se pose par la marge du composant ou une classe mt-* ou mb-*, le fond par l\'état du composant, et un fichier neuf ne se déclare pas)');
	}

}
?>
