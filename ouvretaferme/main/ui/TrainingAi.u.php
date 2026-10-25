<?php
namespace main;

/**
 * Ce que la page des formations et l'accueil des producteurs affichent pareil d'une date : ses heures
 * et le lien d'inscription.
 */
class TrainingAiUi {

	/**
	 * L'inscription se prend chez l'organisme quand la date ou sa formation en donne l'adresse : la
	 * page publique du réseau n'y ajouterait qu'un clic de plus vers le même formulaire. Sinon,
	 * l'inscription se fait sur la page publique de la date.
	 */
	public static function getEnroll(\structure\Community $eCommunity, \learning\Date $eDate, string $class): string {

		$url = $eDate->getExternalUrl();

		if($url !== NULL) {
			return '<a href="'.encode($url).'" target="_blank" class="'.$class.'">'.s("S'inscrire").'</a>';
		}

		return '<a href="'.\learning\DatePublicUi::urlDate($eCommunity, $eDate).'" class="'.$class.'">'.s("S'inscrire").'</a>';

	}

	public static function getHours(string $start, string $end): string {
		return s("{start} à {end}", [
			'start' => str_replace([':00', ':'], 'h', $start),
			'end' => str_replace([':00', ':'], 'h', $end),
		]);
	}

}
?>
