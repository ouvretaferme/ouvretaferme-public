<?php
namespace design;

/**
 * Test-sentinelle : un espacement écrit en rem tombe sur l'échelle.
 *
 * L'échelle est celle des tokens `--space-xxs` à `--space-xxl` de `reboot.css`, sept pas de 0,25 à
 * 3 rem (retenue le 2026-10-01, réponse cliquée « Sept pas (Recommandé) », troisième
 * piste de la problématique des retours d'interface). La sentinelle la lit dans `reboot.css`, elle
 * ne la recopie pas : un pas ajouté ou retiré là-bas change ce qu'elle refuse ici.
 *
 * Un espacement est une valeur en rem d'une déclaration `margin`, `padding`, `gap` ou de l'une de
 * leurs variantes (`margin-top`, `padding-inline`, `row-gap`, `grid-gap`…). Une valeur négative ou
 * prise dans un `calc()` compte par sa valeur absolue, une déclaration à plusieurs valeurs compte
 * autant de fois qu'elle en porte hors de l'échelle, et zéro n'est pas un espacement.
 *
 * Seul le hors échelle est déclaré. Les valeurs déjà sur l'échelle mais écrites en rem et non par
 * leur token sont 3 978 : les déclarer ferait un cliquet de quatre mille entrées que n'importe
 * quel remplacement mécanique viderait, sans rien dire d'un écran. Elles passent au token sur une
 * ligne qu'on réécrit.
 *
 * **Ce contrôle vérifie le pas, jamais la forme d'écriture** (confirmé le 2026-10-01,
 * réponse cliquée « Le pas seul (Recommandé) »). Une valeur ramenée sur un pas s'écrit comme la
 * feuille écrit ses autres espacements : le dépôt compte 13 954 valeurs rem brutes sur un pas
 * contre 6 tokens dans une déclaration d'espacement, dont 2 dans la feuille qui les démontre
 * (mesure et commande dans cas-vus, « Une citation lue comme une écriture »). Un message d'erreur qui
 * prescrirait le token rendrait chaque lot étranger à la feuille qu'il touche, et le prescrire
 * comme direction est une convention de lisibilité qui se décide ailleurs qu'ici.
 *
 * L'existant est déclaré feuille par feuille, en nombre, et ne peut que baisser. Il est pris sur
 * `origin/main` à `ac8e6471b` le 2026-10-01, jamais sur l'arbre de travail : 5 311 espacements en
 * rem dans 243 feuilles, 3 978 sur l'échelle (74,9 %), 1 333 hors échelle dans 134 feuilles. Seuls
 * les fichiers que git suit sont lus (`\dev\SentinelLib::getFiles()`), donc deux postes alignés sur
 * le même commit rendent le même verdict.
 *
 * Ce qu'elle ne voit pas, et qui reste à la relecture :
 * - un espacement en px ou en em (107 et 18 déclarations sur `origin/main`), que l'échelle ne
 *   gouverne pas ;
 * - un espacement posé dans un attribut `style`, que borne `InlineStyleSentinelAi` ;
 * - `scroll-margin`, `scroll-padding` et une propriété personnalisée (`--container-xs-padding`),
 *   qui ne sont pas des écarts entre deux blocs ;
 * - une valeur légitime hors de l'échelle parce qu'elle règle une géométrie et non un écart (la
 *   marge négative qui rattrape une bordure) : elle se compte comme les autres, et c'est la
 *   déclaration de la feuille qui la porte.
 *
 * Lancement : dev/test package=design file=SpaceScaleSentinelAi
 */
class SpaceScaleSentinelAiTest extends \dev\SentinelTest {

	const RULE = 'spaceScale';

	const TOKENS = 'ouvretaferme/main/static/css/reboot.css';

	const DECLARED = [
		'accounting/articles/static/css/articles.css' => 16,
		'accounting/bank/static/css/cashflow.css' => 2,
		'accounting/bank/static/css/statementAi.css' => 2,
		'accounting/cash/static/css/cash.css' => 5,
		'accounting/company/static/css/onboardingAi.css' => 8,
		'accounting/financialYear/static/css/financialYear.css' => 10,
		'accounting/invoicing/static/css/pdf.css' => 1,
		'accounting/journal/static/css/journal.css' => 2,
		'accounting/journal/static/css/operation.css' => 1,
		'accounting/journal/static/css/pattern.css' => 1,
		'accounting/preaccounting/static/css/invoicing.css' => 1,
		'accounting/preaccounting/static/css/preaccounting.css' => 5,
		'accounting/preaccounting/static/css/reconciliate.css' => 1,
		'accounting/preaccounting/static/css/step.css' => 2,
		'accounting/vat/static/css/vat.css' => 1,
		'base/block/static/css/block.css' => 4,
		'base/block/static/css/read.css' => 1,
		'base/media/static/css/upload.css' => 1,
		'base/notification/static/css/notification.css' => 14,
		'base/structure/static/css/action.css' => 1,
		'base/structure/static/css/admin.css' => 1,
		'base/structure/static/css/apiKey.css' => 5,
		'base/structure/static/css/profileAi.css' => 14,
		'base/structure/static/css/structure.css' => 2,
		'base/structure/static/css/system.css' => 2,
		'base/user/static/css/user.css' => 1,
		'commercialisation/amap/static/css/membershipRequest.css' => 1,
		'commercialisation/catalog/static/css/groupAi.css' => 6,
		'commercialisation/catalog/static/css/offer.css' => 13,
		'base/product/static/css/product.css' => 3,
		'commercialisation/catalog/static/css/shopVariantAi.css' => 5,
		'commercialisation/quotation/static/css/panelAi.css' => 3,
		'commercialisation/quotation/static/css/quotation.css' => 42,
		'commercialisation/quotation/static/css/synthesisAi.css' => 2,
		'commercialisation/selling/static/css/compositionAi.css' => 3,
		'commercialisation/selling/static/css/invoice.css' => 1,
		'commercialisation/selling/static/css/item.css' => 1,
		'commercialisation/selling/static/css/market.css' => 8,
		'commercialisation/selling/static/css/pdf.css' => 1,
		'commercialisation/selling/static/css/sale.css' => 1,
		'commercialisation/shop/static/css/design-ami.css' => 9,
		'commercialisation/shop/static/css/design-bucolique.css' => 10,
		'commercialisation/shop/static/css/design-bulles.css' => 14,
		'commercialisation/shop/static/css/design-carnet.css' => 7,
		'commercialisation/shop/static/css/design-collage.css' => 15,
		'commercialisation/shop/static/css/design-contour.css' => 8,
		'commercialisation/shop/static/css/design-epure.css' => 8,
		'commercialisation/shop/static/css/design-futur.css' => 6,
		'commercialisation/shop/static/css/design-guinguette.css' => 10,
		'commercialisation/shop/static/css/design-halo.css' => 6,
		'commercialisation/shop/static/css/design-joyeux.css' => 14,
		'commercialisation/shop/static/css/design-kraft.css' => 13,
		'commercialisation/shop/static/css/design-medaillon.css' => 5,
		'commercialisation/shop/static/css/design-origami.css' => 4,
		'commercialisation/shop/static/css/design-profil.css' => 5,
		'commercialisation/shop/static/css/design-psychedelique.css' => 6,
		'commercialisation/shop/static/css/design-reportage.css' => 13,
		'commercialisation/shop/static/css/design-splash.css' => 17,
		'commercialisation/shop/static/css/design-vintage.css' => 6,
		'commercialisation/shop/static/css/menu.css' => 2,
		'commercialisation/shop/static/css/point.css' => 2,
		'commercialisation/shop/static/css/shop.css' => 9,
		'framework/dev/static/css/doc.css' => 3,
		'framework/editor/static/css/editor.css' => 18,
		'framework/util/static/css/batch.css' => 11,
		'framework/util/static/css/form.css' => 20,
		'framework/util/static/css/lime.css' => 7,
		'internal/communication/static/css/noteAi.css' => 1,
		'ouvretaferme/admin/static/css/statisticAi.css' => 23,
		'ouvretaferme/analyze/static/css/report.css' => 1,
		'ouvretaferme/design/static/css/designAi.css' => 5,
		'ouvretaferme/game/static/css/game.css' => 1,
		'ouvretaferme/learning/static/css/learning.css' => 3,
		'ouvretaferme/main/static/css/component.css' => 130,
		'ouvretaferme/main/static/css/customize.css' => 11,
		'ouvretaferme/main/static/css/home.css' => 1,
		'ouvretaferme/main/static/css/onboarding.css' => 17,
		'ouvretaferme/main/static/css/reboot.css' => 12,
		'ouvretaferme/main/static/css/template-community.css' => 2,
		'ouvretaferme/main/static/css/template-structure.css' => 12,
		'ouvretaferme/presentation/static/css/dataAi.css' => 1,
		'ouvretaferme/presentation/static/css/feature.css' => 4,
		'ouvretaferme/presentation/static/css/landing.css' => 9,
		'ouvretaferme/presentation/static/css/restitutionAi.css' => 1,
		'ouvretaferme/website/static/css/customizeAi.css' => 1,
		'ouvretaferme/website/static/css/design-arche.css' => 18,
		'ouvretaferme/website/static/css/design-atelier.css' => 30,
		'ouvretaferme/website/static/css/design-classique.css' => 3,
		'ouvretaferme/website/static/css/design-etal.css' => 39,
		'ouvretaferme/website/static/css/design-gazette.css' => 27,
		'ouvretaferme/website/static/css/design-geek.css' => 32,
		'ouvretaferme/website/static/css/design-horizon.css' => 22,
		'ouvretaferme/website/static/css/design-kermesse.css' => 38,
		'ouvretaferme/website/static/css/design-left.css' => 2,
		'ouvretaferme/website/static/css/design-medaillon.css' => 34,
		'ouvretaferme/website/static/css/design-pele-mele.css' => 38,
		'ouvretaferme/website/static/css/design-sommaire.css' => 16,
		'ouvretaferme/website/static/css/design-submenu.css' => 4,
		'ouvretaferme/website/static/css/manage.css' => 1,
		'production/map/static/css/bed.css' => 4,
		'production/map/static/css/onboarding.css' => 2,
		'production/map/static/css/plan.css' => 26,
		'production/map/static/css/zone.css' => 7,
		'production/planning/static/css/planning.css' => 16,
		'production/plant/static/css/communityAi.css' => 18,
		'production/plant/static/css/criterion.css' => 2,
		'production/plant/static/css/varietyRating.css' => 8,
		'production/sequence/static/css/crop.css' => 1,
		'production/sequence/static/css/flow.css' => 4,
		'production/sequence/static/css/sequence.css' => 1,
		'production/sequence/static/css/slice.css' => 2,
		'production/series/static/css/analyze.css' => 1,
		'production/series/static/css/compare.css' => 1,
		'production/series/static/css/cultivation.css' => 2,
		'production/series/static/css/place.css' => 56,
		'production/series/static/css/placeGardenAi.css' => 10,
		'production/series/static/css/planning.css' => 6,
		'production/series/static/css/series.css' => 53,
		'production/series/static/css/task.css' => 7,
		'production/series/static/css/timesheet.css' => 3,
		'production/toolbox/static/css/composition.css' => 8,
		'production/toolbox/static/css/toolbox.css' => 2,
		'production/toolbox/static/css/treatment.css' => 2,
	];

	private array $scale = [];

	public function init() {

		$this->scale = self::getScale(file_get_contents(\dev\SentinelLib::getRoot().'/'.self::TOKENS));

		foreach(\dev\SentinelLib::getFiles('.css') as $file => $path) {
			$this->addFound(self::RULE, $file, self::findOffScale(file_get_contents($path), $this->scale));
		}

	}

	/**
	 * Les pas de l'échelle, en rem, tels que `reboot.css` les déclare.
	 *
	 * @return float[]
	 */
	private static function getScale(string $css): array {

		preg_match_all('/--space-[a-z]+\s*:\s*(\d*\.?\d+)rem\s*;/', $css, $matches);

		return array_map(floatval(...), $matches[1]);

	}

	/**
	 * Les lignes qui portent une valeur hors de l'échelle, une entrée par valeur. Les commentaires
	 * sont blanchis sans toucher aux retours à la ligne, pour que les numéros restent ceux du fichier.
	 *
	 * @param float[] $scale
	 * @return int[]
	 */
	private static function findOffScale(string $css, array $scale): array {

		$css = preg_replace_callback('#/\*.*?\*/#s', fn(array $matches) => preg_replace('/[^\n]/', ' ', $matches[0]), $css);

		preg_match_all('/(?<![\w-])(?:(?:margin|padding)(?:-(?:top|right|bottom|left|inline|block)(?:-(?:start|end))?)?|(?:grid-)?(?:row-|column-)?gap)\s*:\s*([^;{}]+)/', $css, $declarations, PREG_OFFSET_CAPTURE);

		$found = [];

		foreach($declarations[1] as [$value, $offset]) {

			preg_match_all('/(?<![\w.])-?(\d+\.?\d*|\.\d+)rem\b/', $value, $values, PREG_OFFSET_CAPTURE);

			foreach($values[1] as [$number, $position]) {

				if(in_array((float)$number, $scale, TRUE) === FALSE) {
					$found[] = substr_count($css, "\n", 0, $offset + $position) + 1;
				}

			}

		}

		return $found;

	}

	public function testScale(): void {
		$this->assertEquals([0.25, 0.5, 0.75, 1.0, 1.5, 2.0, 3.0], $this->scale, 'l\'échelle lue dans '.self::TOKENS.' n\'est plus celle des sept pas retenus (reboot.css fait foi sur la valeur d\'un pas : si le changement est voulu, corriger ce contrôle et reprendre DECLARED dans le même lot, sinon remettre les tokens --space-*)');
	}

	public function testDetectors(): void {

		$css = <<<'CSS'
			.a { margin: 1rem 1.25rem; padding-top: .5rem; }
			.b { gap: 0.4rem; row-gap: 3rem; grid-gap: 0.1rem; }
			/* margin: 5rem; */
			.c { margin-left: -0.375rem; padding: calc(1rem + 0.125rem) 0; }
			.d { --card-padding: 5rem; scroll-margin-top: 5rem; width: 5rem; margin: 5px 0.3em; }
			.e { padding-inline: var(--space-md);
				margin-block-end: 4rem; }
			CSS;

		$this->assertEquals([1, 2, 2, 4, 4, 7], self::findOffScale($css, [0.25, 0.5, 0.75, 1.0, 1.5, 2.0, 3.0]));
		$this->assertEquals([0.25, 3.0], self::getScale("--space-xxs: 0.25rem;\n--radius: .75rem;\n--space-xxl: 3rem;"));

	}

	public function testSpaceScale(): void {
		$this->assertRatchet(self::RULE, self::DECLARED, 'espacement en rem hors de l\'échelle --space-xxs à --space-xxl (le code fait foi : ramener la valeur au pas le plus proche de ceux que '.self::TOKENS.' déclare, en lisant le pas sur place et jamais de mémoire, un pas d\'écart valant 25 à 50 % et laissant ce test vert ; la valeur s\'écrit ensuite comme la feuille écrit ses autres espacements, ce test vérifiant le pas et non la forme ; à égale distance de deux pas, 0.375rem par exemple, les deux se mesurent à 360 px, l\'arrondi au hasard n\'est pas un geste ; une feuille neuve ne se déclare pas, et une feuille déjà déclarée ne monte pas)');
	}

}
?>
