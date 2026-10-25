<?php
namespace planning;

/**
 * L'onglet Absences du planning, à toute personne au planning (`\hr\Absence::assertPlanning()`) : ses absences,
 * avec la demande d'une nouvelle, puis celles de l'équipe.
 */
class AbsenceUi {

	/**
	 * La semaine ne change rien à la liste : elle suit l'adresse pour que les autres onglets rouvrent celle d'où
	 * l'on vient.
	 */
	public static function url(\structure\Structure $eStructure, ?string $week = NULL): string {

		$url = \structure\StructureUi::urlStructure($eStructure).'/planning-v2/absences';

		if($week !== NULL and $week !== \Clock::week()) {
			$url .= '/'.$week;
		}

		return $url;

	}

	public function getHeader(): string {
		return \main\DesignUi::getPageHeader(s("Absences"));
	}

	/**
	 * Les siennes d'abord, sous leur titre qui porte l'ajout, puis l'équipe (décidé le 19/10). L'ajout
	 * ne vise que la personne connectée, gérant compris (décidé le 20/10) : le gérant saisit une
	 * absence validée, les autres la demandent. Le choix de la personne reste à l'onglet Absences de l'équipe.
	 */
	public function get(\structure\Structure $eStructure, string $week, \structure\Member $eMember, bool $manage, bool $canCreate, \Collection $cAbsence, array $upcoming): string {

		$uiAbsence = new \hr\AbsenceUi();

		$h = new TabUi()->get($eStructure, $week, 'absences', $eMember);

		$h .= '<div class="util-title">';
			$h .= '<h3>'.s("Mes absences").'</h3>';
			if($canCreate) {
				$h .= '<div>';
					$h .= '<a href="/hr/absence:create?structure='.$eStructure['id'].'&origin=planning" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.($manage ? s("Ajouter une absence") : s("Demander une absence")).'</a>';
				$h .= '</div>';
			}
		$h .= '</div>';

		$h .= $uiAbsence->getRequester($cAbsence);

		$h .= '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Absences de l'équipe dans le mois à venir").'</h3>';
		$h .= '</div>';

		$h .= $uiAbsence->getUpcoming($upcoming);

		return $h;

	}

}
?>
