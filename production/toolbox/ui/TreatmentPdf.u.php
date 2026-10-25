<?php
namespace toolbox;

/**
 * Le carnet de traitement en PDF : la charte des documents (`\main\PdfUi`) autour d'un tableau par
 * mois, du plus ancien au plus récent, comme un registre se lit. Un traitement noté à la semaine y
 * prend la place de sa semaine, comme à l'écran (`TreatmentLib::getRegister()`).
 *
 * Neuf colonnes ne tiennent pas sur un A4 portrait sans couper chaque cellule : le document est en
 * paysage (`treatmentPdf.css`).
 */
class TreatmentPdfUi {

	public function __construct() {
		\Asset::css('toolbox', 'pdfTable.css');
		\Asset::css('toolbox', 'treatmentPdf.css');
	}

	/**
	 * Le nom du fichier, que le lien de l'écran annonce (`data-ajax-download`) et que la page donne
	 * au document : les deux doivent dire le même.
	 */
	public static function getFilename(int $year): string {
		return s("Registre phytosanitaire {value}", $year).'.pdf';
	}

	/**
	 * Les chiffres de l'écran, comptés sur les mêmes traitements, et comme à l'écran, « Produit à renseigner »
	 * n'apparaît que s'il en reste.
	 */
	public function getFigures(int $year, \Collection $cTask, \Collection $cTaskWithout): string {

		$figures = [
			[$cTask->count(), p("Traitement en {year}", "Traitements en {year}", $cTask->count(), ['year' => $year])],
		];

		if($cTaskWithout->notEmpty()) {
			$figures[] = [$cTaskWithout->count(), p("Produit à renseigner", "Produits à renseigner", $cTaskWithout->count())];
		}

		return \main\PdfUi::getFigures($figures);

	}

	public function getMonths(int $year, array $tasksByMonth): string {

		if($tasksByMonth === []) {
			return '<div class="pdf-note">'.s("Aucun traitement en {value}.", $year).'</div>';
		}

		$h = '';

		foreach($tasksByMonth as $month => $cTask) {

			$h .= '<div class="toolbox-pdf-section">';

				$h .= '<h3>'.mb_ucfirst(\util\DateUi::textual($month.'-01', \util\DateUi::MONTH_YEAR)).'</h3>';

				$h .= '<table class="toolbox-pdf-table toolbox-pdf-treatments">';

					$h .= '<thead>';
						$h .= '<tr>';
							$h .= '<th>'.s("Date").'</th>';
							$h .= '<th>'.s("Culture et emplacements").'</th>';
							$h .= '<th>'.s("Produit et AMM").'</th>';
							$h .= '<th class="text-end">'.s("Dose").'</th>';
							$h .= '<th class="text-end">'.s("Bouillie").'</th>';
							$h .= '<th class="text-end">'.s("Quantité").'</th>';
							$h .= '<th class="text-end">'.s("Surface").'</th>';
							$h .= '<th>'.s("Matériel").'</th>';
							$h .= '<th>'.s("Description").'</th>';
						$h .= '</tr>';
					$h .= '</thead>';

					$h .= '<tbody>';
						foreach($cTask as $eTask) {
							$h .= $this->getRow($eTask);
						}
					$h .= '</tbody>';

				$h .= '</table>';

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Une série qui n'est pas encore placée n'a qu'une surface prévue : la surface et la quantité qui
	 * en découle portent « prévus », comme sur l'écran.
	 */
	protected function getRow(\series\Task $eTask): string {

		$eToolPhyto = $eTask['eToolPhyto'];
		$planned = $eTask['areaPlanned'] ? '<div class="toolbox-pdf-muted">'.s("prévus").'</div>' : '';

		$h = '<tr>';

			$h .= '<td class="toolbox-pdf-date">'.($eTask['doneDate'] === NULL ? TreatmentUi::getDone($eTask) : \util\DateUi::numeric($eTask['doneDate'], \util\DateUi::DATE)).'</td>';

			$h .= '<td>';
				if($eTask['series']->notEmpty()) {
					$h .= '<div>'.encode($eTask['series']['name']).'</div>';
					if($eTask['series']['cccPlace']->notEmpty()) {
						$h .= '<div class="toolbox-pdf-muted">'.new \series\CultivationUi()->displayPlaces($eTask['series']['use'], $eTask['series']['cccPlace']).'</div>';
					}
				} else if($eTask['plant']->notEmpty()) {
					$h .= encode($eTask['plant']['name']);
				} else {
					$h .= s("Hors série");
				}
			$h .= '</td>';

			$h .= '<td>';
				if($eToolPhyto->empty()) {
					$h .= '<span class="toolbox-pdf-muted">'.s("Produit à renseigner").'</span>';
				} else {
					$h .= '<div>'.encode($eToolPhyto['name']).'</div>';
					if($eToolPhyto['routineValue']['amm'] !== NULL) {
						$h .= '<div class="toolbox-pdf-muted">'.s("AMM {value}", encode($eToolPhyto['routineValue']['amm'])).'</div>';
					}
				}
			$h .= '</td>';

			$h .= '<td class="text-end">'.$this->getDose($eTask).'</td>';
			$h .= '<td class="text-end">'.$this->getWater($eTask).'</td>';

			$h .= '<td class="text-end">';
				if($eTask['quantity'] !== NULL) {
					$h .= \series\TaskUi::getTreatmentQuantity($eTask['quantity'], $eTask['unit']).$planned;
				}
			$h .= '</td>';

			$h .= '<td class="text-end">';
				if($eTask['area'] !== NULL) {
					$h .= s("{value} m²", \util\TextUi::number($eTask['area'])).$planned;
				}
			$h .= '</td>';

			$h .= '<td>'.encode(implode(', ', $eTask['cTool']->getColumn('name'))).'</td>';
			$h .= '<td>'.($eTask['description'] === NULL ? '' : nl2br(encode($eTask['description']))).'</td>';

		$h .= '</tr>';

		return $h;

	}

	protected function getDose(\series\Task $eTask): string {

		$dose = $eTask['treatment']['dose'] ?? NULL;

		if($eTask['eToolPhyto']->empty() or $dose === NULL) {
			return '';
		}

		return \work\RoutineUi::getPhytoDose($dose, $eTask['unit']);

	}

	/**
	 * Un produit prêt à l'emploi ne se dilue pas : la case le dit, comme la liste des produits, parce
	 * qu'une case vide d'un registre se lit comme une donnée manquante. Elle ne reste vide que sans
	 * produit, ou sans bouillie notée sur un produit dilué.
	 */
	protected function getWater(\series\Task $eTask): string {

		if($eTask['eToolPhyto']->empty()) {
			return '';
		}

		if($eTask['eToolPhyto']['routineValue']['readyToUse']) {
			return '<span class="toolbox-pdf-muted">'.s("Prêt à l'emploi").'</span>';
		}

		$water = $eTask['treatment']['water'] ?? NULL;

		if($water === NULL) {
			return '';
		}

		return s("{value} L/ha", $water);

	}

}
?>
