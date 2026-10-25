<?php
namespace design;

/**
 * Test-sentinelle : aucun attribut `title` dans un fichier de présentation.
 *
 * Une info-bulle ne s'affiche qu'au survol d'une souris : sur un écran tactile, elle n'existe pas.
 * Ce qu'un `title` porte est donc perdu pour une partie des utilisateurs, et le contrôle qui en
 * dépend se lit à moitié. Décidé le 2026-10-04 : « Pas de title de toute façon ça sert à
 * rien on les voit pas sur mobile ». Un contrôle se nomme par un libellé visible, et la raison d'un
 * contrôle verrouillé s'écrit en texte sous lui (skill `ui`, « Conventions de l'interface »).
 *
 * L'existant est déclaré fichier par fichier, en nombre, et ne peut que baisser. Il est pris sur
 * `origin/main` à `a196e8c8b` le 2026-10-04, jamais sur l'arbre de travail :
 * `git grep -o -P '(?<![\w-])title="' origin/main -- '*.u.php' '*.v.php'` rend 454 attributs dans
 * 166 fichiers. Seuls les fichiers que git suit sont lus (`\dev\SentinelLib::getFiles()`), donc deux
 * postes alignés sur le même commit rendent le même verdict.
 *
 * Ce qu'elle ne voit pas, et qui reste à la relecture :
 * - un `title` posé par un tableau d'attributs (`'title' => …`), que rien ne distingue ici d'une
 *   clé de donnée homonyme, le titre d'un panneau ou d'une page ;
 * - un attribut écrit entre guillemets échappés dans une chaîne à guillemets doubles ;
 * - un `title` posé en JavaScript.
 * Un `data-title="…"` n'est pas un attribut `title` et n'est pas compté.
 *
 * Lancement : dev/test package=design file=TitleAttributeSentinelAi
 */
class TitleAttributeSentinelAiTest extends \dev\SentinelTest {

	const RULE = 'titleAttribute';

	const DECLARED = [
		'base/block/ui/BlockType.u.php' => 5,
		'base/block/ui/Block.u.php' => 6,
		'base/media/ui/LearningQuizAi.u.php' => 1,
		'base/media/ui/Media.u.php' => 2,
		'base/notification/ui/Notification.u.php' => 2,
		'base/structure/ui/Admin.u.php' => 1,
		'base/structure/ui/CommunityStartAi.u.php' => 2,
		'base/structure/ui/Menu.u.php' => 1,
		'base/structure/ui/StructureCommunity.u.php' => 2,
		'base/user/ui/Admin.u.php' => 2,
		'base/user/ui/User.u.php' => 1,
		'commercialisation/amap/ui/Configuration.u.php' => 1,
		'commercialisation/amap/ui/ContractProduct.u.php' => 1,
		'commercialisation/amap/ui/Subscription.u.php' => 1,
		'commercialisation/payment/ui/PaymentTransaction.u.php' => 5,
		'commercialisation/payment/ui/Wallet.u.php' => 2,
		'commercialisation/catalog/ui/Offer.u.php' => 10,
		'base/product/ui/Product.u.php' => 10,
		'base/product/ui/Stock.u.php' => 2,
		'base/product/ui/VariantAi.u.php' => 3,
		'commercialisation/quotation/ui/PanelAi.u.php' => 1,
		'commercialisation/quotation/ui/Suggestion.u.php' => 2,
		'commercialisation/quotation/ui/Table.u.php' => 3,
		'commercialisation/selling/ui/Analyze.u.php' => 2,
		'commercialisation/selling/ui/CompositionAi.u.php' => 2,
		'commercialisation/selling/ui/Customer.u.php' => 4,
		'commercialisation/selling/ui/Invoice.u.php' => 1,
		'commercialisation/selling/ui/Item.u.php' => 1,
		'commercialisation/selling/ui/MarketPointingAi.u.php' => 1,
		'commercialisation/selling/ui/Market.u.php' => 2,
		'commercialisation/selling/ui/Merchant.u.php' => 3,
		'commercialisation/selling/ui/Sale.u.php' => 13,
		'commercialisation/selling/ui/Stock.u.php' => 5,
		'commercialisation/shop/ui/CustomizeAi.u.php' => 3,
		'commercialisation/shop/ui/Date.u.php' => 3,
		'commercialisation/shop/ui/Point.u.php' => 1,
		'commercialisation/shop/ui/Share.u.php' => 1,
		'commercialisation/shop/ui/Shop.u.php' => 2,
		'commercialisation/shop/view/doc.v.php' => 1,
		'framework/editor/ui/Editor.u.php' => 1,
		'framework/util/ui/Batch.u.php' => 1,
		'framework/util/ui/Form.u.php' => 3,
		'framework/util/ui/Text.u.php' => 2,
		'ouvretaferme/admin/ui/StatisticAi.u.php' => 2,
		'internal/security/ui/AccessReport.u.php' => 1,
		'ouvretaferme/analyze/ui/Report.u.php' => 2,
		'ouvretaferme/design/ui/Design.u.php' => 13,
		'ouvretaferme/design/ui/Pdf.u.php' => 1,
		'ouvretaferme/learning/ui/Catalog.u.php' => 1,
		'ouvretaferme/learning/ui/Comment.u.php' => 1,
		'ouvretaferme/learning/ui/CourseStart.u.php' => 1,
		'ouvretaferme/learning/ui/Course.u.php' => 1,
		'ouvretaferme/learning/ui/Customize.u.php' => 2,
		'ouvretaferme/learning/ui/DateSequence.u.php' => 1,
		'ouvretaferme/learning/ui/Lesson.u.php' => 1,
		'ouvretaferme/learning/ui/Progress.u.php' => 2,
		'ouvretaferme/learning/ui/Question.u.php' => 2,
		'ouvretaferme/learning/ui/Share.u.php' => 1,
		'ouvretaferme/main/ui/Chart.u.php' => 2,
		'ouvretaferme/main/ui/Customize.u.php' => 1,
		'ouvretaferme/main/ui/Design.u.php' => 3,
		'ouvretaferme/subscription/ui/AdminAi.u.php' => 6,
		'ouvretaferme/subscription/ui/PageAi.u.php' => 2,
		'ouvretaferme/website/ui/CustomizeAi.u.php' => 7,
		'ouvretaferme/website/ui/Manage.u.php' => 6,
		'ouvretaferme/website/ui/Webpage.u.php' => 1,
		'ouvretaferme/website/view/doc.v.php' => 1,
		'production/map/ui/Bed.u.php' => 4,
		'production/map/ui/Onboarding.u.php' => 1,
		'production/map/ui/Plan.u.php' => 6,
		'production/plant/ui/Analyze.u.php' => 1,
		'production/plant/ui/Family.u.php' => 2,
		'production/plant/ui/Forecast.u.php' => 1,
		'production/plant/ui/Plant.u.php' => 2,
		'production/plant/ui/VarietyRatingAi.u.php' => 3,
		'production/sequence/ui/Crop.u.php' => 4,
		'production/sequence/ui/Flow.u.php' => 3,
		'production/sequence/ui/Sequence.u.php' => 9,
		'production/sequence/ui/Slice.u.php' => 2,
		'production/series/ui/Comment.u.php' => 2,
		'production/series/ui/CompareAi.u.php' => 7,
		'production/series/ui/Csv.u.php' => 3,
		'production/series/ui/Cultivation.u.php' => 14,
		'production/series/ui/GardenChairsAi.u.php' => 2,
		'production/series/ui/PlaceGardenAi.u.php' => 3,
		'production/series/ui/PlaceNoDateAi.u.php' => 1,
		'production/series/ui/Planning.u.php' => 8,
		'production/series/ui/Series.u.php' => 1,
		'production/series/ui/Task.u.php' => 14,
		'production/series/ui/Timesheet.u.php' => 1,
		'production/toolbox/ui/CompositionSpecies.u.php' => 2,
		'production/toolbox/ui/CompositionStart.u.php' => 2,
		'production/toolbox/ui/Composition.u.php' => 14,
		'production/toolbox/ui/EquipmentReading.u.php' => 1,
		'production/toolbox/ui/Equipment.u.php' => 1,
		'production/toolbox/ui/Fertilization.u.php' => 1,
		'production/toolbox/ui/Gauge.u.php' => 1,
		'production/toolbox/ui/MaintenanceDone.u.php' => 2,
		'production/toolbox/ui/Maintenance.u.php' => 1,
		'production/toolbox/ui/Rain.u.php' => 2,
		'production/toolbox/ui/Tool.u.php' => 3,
		'production/toolbox/ui/Treatment.u.php' => 2,
		'production/toolbox/ui/WaterMeter.u.php' => 1,
		'production/toolbox/ui/WaterReading.u.php' => 1,
		'production/work/ui/Category.u.php' => 1,
	];

	public function init() {

		foreach(\dev\SentinelLib::getFiles('.php') as $file => $path) {

			if(self::isPresentation($file) === FALSE) {
				continue;
			}

			$this->addFound(self::RULE, $file, self::findTitle(explode("\n", file_get_contents($path))));

		}

	}

	/**
	 * Une ligne est relevée autant de fois qu'elle porte d'attributs `title`.
	 *
	 * @param string[] $lines
	 * @return int[]
	 */
	private static function findTitle(array $lines): array {

		$found = [];

		foreach($lines as $position => $line) {

			$count = preg_match_all('/(?<![\w-])title="/', $line);

			for($i = 0; $i < $count; $i++) {
				$found[] = $position + 1;
			}

		}

		return $found;

	}

	public function testDetectors(): void {

		$lines = explode("\n", <<<'HTML'
			<a title="Effacer">
			<div title="Un"> <span title="Deux">
			<div data-title="Donnée"> <div class="util-title">
			<title>Page</title>
			HTML);

		$this->assertEquals([1, 2, 2], self::findTitle($lines));

	}

	public function testTitleAttribute(): void {
		$this->assertRatchet(self::RULE, self::DECLARED, 'attribut title dans un fichier de présentation (le code fait foi : un contrôle se nomme par un libellé visible et la raison d\'un verrou s\'écrit en texte sous lui, une info-bulle ne se voyant pas au doigt ; un fichier neuf ne se déclare pas)');
	}

}
?>
