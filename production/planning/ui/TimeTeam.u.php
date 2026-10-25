<?php
namespace planning;

/**
 * Le bloc du temps d'une équipe (`TimeTeamLib`), dans le formulaire d'un autre écran : les personnes en
 * boutons à vignette, et sous chaque personne choisie sa durée derrière sa tuile, comme le temps prévu de
 * la grille de la Semaine (`DurationUi::getStepWindow()`). Rien ne part avant l'envoi du formulaire : `PlanningTimeTeam`
 * reporte le choix et les durées dans les champs cachés `time[<user>]`.
 */
class TimeTeamUi {

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

	}

	/**
	 * `$cUser` : les personnes dont on peut écrire le temps (`TimeTeamLib::getUsers()`), vide quand la
	 * structure ne suit pas le temps, et le bloc ne s'affiche pas.
	 *
	 * Avec une seule personne, la durée suffit : la vignette ne dirait rien que le compte connecté ne
	 * sache déjà. Sinon, la personne connectée est choisie d'office. Le temps est facultatif : une durée
	 * laissée à zéro n'est pas postée.
	 */
	public function getField(\util\FormUi $form, \Collection $cUser): string {

		if($cUser->empty()) {
			return '';
		}

		// Seule, la personne n'a pas de place à elle : ses refus tombent sur le bloc.
		$single = ($cUser->count() === 1);

		$h = '<div class="planning-time-crew" data-wrapper="timeTeam'.($single ? ' time['.$cUser->first()['id'].']' : '').'">';

			if($single) {
				$h .= $this->getDuration($form, $cUser->first());
			} else {
				$h .= '<div class="planning-time-crew-users">';
					foreach($cUser as $eUser) {
						$h .= $this->getUser($form, $eUser);
					}
				$h .= '</div>';
			}

		$h .= '</div>';

		return $form->group(s("Temps de travail"), $h);

	}

	/**
	 * Une personne choisie se marque par le fond et montre sa durée sous elle ; le clic la décoche, cache sa
	 * durée et éteint son champ.
	 */
	protected function getUser(\util\FormUi $form, \user\User $eUser): string {

		$selected = $eUser->isOnline();

		$h = '<div class="planning-time-crew-user" data-wrapper="time['.$eUser['id'].']">';
			$h .= '<a class="btn btn-outline-primary planning-time-crew-person'.($selected ? ' is-selected' : '').'" data-planning-time-crew-user>';
				$h .= \user\UserUi::getVignette($eUser, '1.25rem');
				$h .= '<span>'.encode($eUser['firstName'] ?? $eUser['lastName']).'</span>';
			$h .= '</a>';
			$h .= $this->getDuration($form, $eUser);
		$h .= '</div>';

		return $h;

	}

	protected function getDuration(\util\FormUi $form, \user\User $eUser): string {

		$h = '<div class="planning-time-crew-duration">';
			$h .= new DurationUi()->getStepWindow('time-'.$eUser['id'], 0.0, wrapper: NULL);
			$h .= $form->hidden('time['.$eUser['id'].']', '0', ['disabled' => TRUE]);
		$h .= '</div>';

		return $h;

	}

}
?>
