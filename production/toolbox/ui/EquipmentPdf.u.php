<?php
namespace toolbox;

/**
 * Le carnet d'entretien d'un matériel en PDF : la charte des documents (`\main\PdfUi`) autour de deux
 * tableaux, les entretiens planifiés puis l'historique entier, du plus récent au plus ancien comme à
 * l'écran. Le tableau est celui du registre de traitement (`pdfTable.css`).
 */
class EquipmentPdfUi {

	public function __construct() {
		\Asset::css('toolbox', 'pdfTable.css');
		\Asset::css('toolbox', 'equipmentPdf.css');
	}

	/**
	 * Le nom du fichier, que le lien de l'écran annonce (`data-ajax-download`) et que la page donne
	 * au document : les deux doivent dire le même.
	 */
	public static function getFilename(Equipment $eEquipment): string {
		return s("Carnet d'entretien {value}", $eEquipment['tool']['name']).'.pdf';
	}

	/**
	 * Le titre du document, qui nomme le matériel (recette du 01/10) : un carnet sorti de
	 * son classeur dit de quel matériel il est sans qu'on cherche la ligne du dessous. Du texte brut,
	 * que `\main\PdfUi::getStructure()` encode.
	 *
	 * L'immatriculation suit le nom (recette de l'immatriculation du 01/10, « l'ajouter au titre du
	 * PDF ») : deux utilitaires du même nom ne se distinguent que par elle. Elle ne se montre qu'avec
	 * un compteur, comme en tête du carnet à l'écran (`EquipmentUi`).
	 */
	public static function getTitle(Equipment $eEquipment): string {

		$title = s("Carnet d'entretien · {value}", $eEquipment['tool']['name']);

		if($eEquipment->hasMeter() and $eEquipment['registration'] !== NULL) {
			$title .= ' · '.$eEquipment['registration'];
		}

		return $title;

	}

	/**
	 * Le compteur et le jour de son dernier relevé, puis ce que l'historique porte : le nombre
	 * d'entretiens et d'interventions, et ce qu'ils ont coûté. Sans compteur ou sans relevé, la bande
	 * commence aux entretiens.
	 */
	public function getFigures(Equipment $eEquipment): string {

		$figures = [];

		if($eEquipment->hasMeter() and $eEquipment['eEquipmentReadingLast']->notEmpty()) {

			$figures[] = [
				EquipmentUi::getValue($eEquipment, $eEquipment['eEquipmentReadingLast']['value']),
				s("Compteur relevé le {value}", \util\DateUi::numeric($eEquipment['eEquipmentReadingLast']['date'], \util\DateUi::DATE))
			];

		}

		$done = $eEquipment['cMaintenanceDone']->count();

		$figures[] = [$done, p("Entretien fait", "Entretiens faits", $done)];
		$figures[] = [MaintenanceDoneUi::getCost($eEquipment['cost']), s("Coût des entretiens")];

		return \main\PdfUi::getFigures($figures);

	}

	/**
	 * Les entretiens qui ont une échéance, ceux du bloc à l'écran (`cMaintenanceScheduled`), avec leurs
	 * deux bornes quand ils reviennent au compteur et à la date, et l'année sur chaque date : le
	 * document s'archive, il ne se lit pas dans l'année où il est sorti. Sans
	 * entretien planifié, le document ne porte ni le titre ni une mention (retour du 01/10,
	 * « ne rien afficher si y'en a pas »).
	 */
	public function getMaintenances(Equipment $eEquipment): string {

		if($eEquipment['cMaintenanceScheduled']->empty()) {
			return '';
		}

		$h = '<div class="toolbox-pdf-section">';

			$h .= '<h3>'.s("Entretiens planifiés").'</h3>';

			$h .= '<table class="toolbox-pdf-table toolbox-pdf-maintenances">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Entretien").'</th>';
						$h .= '<th>'.s("Fréquence").'</th>';
						$h .= '<th>'.s("Prochaine échéance").'</th>';
						$h .= '<th>'.s("État").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

					foreach($eEquipment['cMaintenanceScheduled'] as $eMaintenance) {

						$h .= '<tr>';
							$h .= '<td>'.encode($eMaintenance['name']).'</td>';
							$h .= '<td>'.MaintenanceUi::getFrequencyText($eMaintenance).'</td>';
							$h .= '<td>';
								$h .= '<div>'.MaintenanceUi::getDueText($eMaintenance, year: TRUE).'</div>';
								$other = MaintenanceUi::getDueOther($eMaintenance, year: TRUE);
								if($other !== NULL) {
									$h .= '<div class="toolbox-pdf-muted">'.$other.'</div>';
								}
							$h .= '</td>';
							$h .= '<td'.($eMaintenance['due'] === MaintenanceLib::OVERDUE ? ' class="toolbox-pdf-overdue"' : '').'>';
								if($eMaintenance['due'] !== NULL) {
									$h .= MaintenanceUi::getDueLabel($eMaintenance);
								}
							$h .= '</td>';
						$h .= '</tr>';

					}

				$h .= '</tbody>';

			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'historique entier (`EquipmentLib::getWithHistory()`), avec les colonnes de l'écran : le
	 * compteur seulement sur un matériel qui en a un. Un historique vide ne s'annonce pas non plus.
	 */
	public function getHistory(Equipment $eEquipment): string {

		if($eEquipment['history'] === []) {
			return '';
		}

		$meter = $eEquipment->hasMeter();

		$h = '<div class="toolbox-pdf-section">';

			$h .= '<h3>'.s("Historique de l'entretien").'</h3>';

			$h .= '<table class="toolbox-pdf-table toolbox-pdf-history">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Date").'</th>';
						$h .= '<th>'.s("Entretien").'</th>';
						if($meter) {
							$h .= '<th class="text-end">'.s("Compteur").'</th>';
						}
						$h .= '<th class="text-end">'.s("Coût").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

					foreach($eEquipment['history'] as $eHistory) {

						$reading = ($eHistory instanceof EquipmentReading);

						$h .= '<tr>';

							$h .= '<td class="toolbox-pdf-date">'.\util\DateUi::numeric($eHistory['date'], \util\DateUi::DATE).'</td>';

							$h .= '<td>';
								if($reading) {
									$h .= s("Relevé du compteur");
								} else {
									$h .= '<div>'.encode($eHistory->getName()).'</div>';
									if($eHistory['comment'] !== NULL) {
										$h .= '<div class="toolbox-pdf-muted">'.nl2br(encode($eHistory['comment'])).'</div>';
									}
								}
							$h .= '</td>';

							if($meter) {
								$h .= '<td class="text-end">'.($eHistory['value'] === NULL ? '' : EquipmentUi::getValue($eEquipment, $eHistory['value'])).'</td>';
							}

							$h .= '<td class="text-end">';
								if($reading === FALSE and $eHistory['cost'] !== NULL) {
									$h .= MaintenanceDoneUi::getCost($eHistory['cost']);
								}
							$h .= '</td>';

						$h .= '</tr>';

					}

				$h .= '</tbody>';

			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

}
?>
