<?php
namespace toolbox;

/**
 * Les paniers de l'année en PDF (lot L4, planche du 24/09) : un document de la saison, à montrer aux
 * adhérents, qui dit ce qu'il y a dans les paniers semaine après semaine. Il porte la charte des PDF
 * (`\main\PdfUi`) autour d'un corps propre à l'outil : une carte par semaine, quatre par rang, en
 * portrait (B), et une colonne de quantité par taille quand il y en a plusieurs (A).
 *
 * Les quantités de l'année ne vont pas dans le document : elles ne regardent pas les adhérents.
 */
class CompositionPdfUi {

	public function __construct() {
		\Asset::css('toolbox', 'pdf.css');
	}

	/**
	 * Le nom du fichier, que le lien de l'écran annonce (`data-ajax-download`) et que la page donne
	 * au document : les deux doivent dire le même.
	 */
	public static function getFilename(int $season): string {
		return s("Paniers {value}", $season).'.pdf';
	}

	/**
	 * La période des paniers, sous le titre : « Du 2 mars au 8 novembre 2026 ».
	 */
	public function getPeriod(CompositionPlan $eCompositionPlan): string {
		return ucfirst(CompositionUi::getPeriod($eCompositionPlan['season'], $eCompositionPlan['weeks'])).' '.$eCompositionPlan['season'];
	}

	/**
	 * Les trois chiffres de la charte : les semaines, les espèces, et le prix du panier, ou de
	 * chaque taille quand il y en a plusieurs (« 18 et 28 € »).
	 */
	public function getFigures(CompositionPlan $eCompositionPlan): string {

		$cCompositionFormula = $eCompositionPlan['cCompositionFormula'];

		if($cCompositionFormula->count() === 1) {

			$eCompositionFormula = $cCompositionFormula->first();

			$price = \util\TextUi::money((float)$eCompositionFormula['price'], precision: 0);
			$priceLabel = $eCompositionFormula['name'];

		} else {

			$prices = $cCompositionFormula->makeArray(fn(CompositionFormula $e) => \util\TextUi::number((float)$e['price'], 0));
			$last = array_pop($prices);

			$price = s("{prices} et {last} €", ['prices' => implode(', ', $prices), 'last' => $last]);
			$priceLabel = p("{value} panier", "{value} paniers", $cCompositionFormula->count());

		}

		return \main\PdfUi::getFigures([
			[count($eCompositionPlan['weeks']), s("Semaines de paniers")],
			[$eCompositionPlan['species'], s("Produits au fil de l'année")],
			[str_replace(' ', "\u{a0}", $price), $priceLabel],
		]);

	}

	/**
	 * Une carte par semaine livrée, dans l'ordre de l'année. Avec plusieurs tailles, chaque carte
	 * porte une colonne de quantité par taille, titrée par son prix, et une case vide quand la taille
	 * ne sert pas l'espèce cette semaine.
	 */
	public function getWeeks(CompositionPlan $eCompositionPlan): string {

		$cCompositionFormula = $eCompositionPlan['cCompositionFormula'];
		$sizes = ($cCompositionFormula->count() > 1);

		$h = '<div class="toolbox-pdf-weeks'.($sizes ? ' toolbox-pdf-weeks-sizes' : '').'">';

			foreach($eCompositionPlan['baskets'] as $week => $rows) {

				$h .= '<div class="toolbox-pdf-week">';

					$h .= '<h4>'.$this->getWeekDays($eCompositionPlan['season'], $week).'</h4>';

					if($sizes) {
						$h .= '<div class="toolbox-pdf-week-row toolbox-pdf-week-sizes">';
							$h .= '<span class="toolbox-pdf-week-plant"></span>';
							foreach($cCompositionFormula as $eCompositionFormula) {
								$h .= '<span>'.str_replace(' ', "\u{a0}", \util\TextUi::money((float)$eCompositionFormula['price'], precision: 0)).'</span>';
							}
						$h .= '</div>';
					}

					foreach($rows as $row) {

						$h .= '<div class="toolbox-pdf-week-row">';

							$h .= '<span class="toolbox-pdf-week-plant">';
								$h .= \plant\PlantUi::getVignette($row['species']['plant'], '1rem');
								$h .= '<span>'.encode($row['species']->getLabel()).'</span>';
							$h .= '</span>';

							foreach($row['formulas'] as $quantity) {
								$h .= '<span>'.($quantity === NULL ? '' : CompositionUi::getQuantity($quantity, $row['unit'])).'</span>';
							}

						$h .= '</div>';

					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * « Du 2 au 8 mars », ou « Du 30 mars au 5 avril » quand la semaine change de mois.
	 */
	protected function getWeekDays(int $season, int $week): string {

		$isoWeek = CompositionUi::getIsoWeek($season, $week);

		$from = week_date_starts($isoWeek);
		$to = week_date_ends($isoWeek);

		return s("Du {from} au {to}", [
			'from' => (substr($from, 5, 2) === substr($to, 5, 2)) ? (int)substr($from, 8, 2) : \util\DateUi::textual($from, \util\DateUi::DAY_MONTH),
			'to' => \util\DateUi::textual($to, \util\DateUi::DAY_MONTH),
		]);

	}

}
?>
