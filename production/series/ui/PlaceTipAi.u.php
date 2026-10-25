<?php
namespace series;

/**
 * La bulle de survol d'un bloc du plan : le départ de la série et sa récolte, en
 * deux lignes. Un premier jet dessinait une frise des 52 semaines ; elle a été
 * retirée à l'arbitrage, et son argument avec — l'intervalle affiché ici doit donc
 * tenir tout seul, y compris quand la récolte traverse l'année civile.
 */
class PlaceTipAiUi {

	public function __construct() {

		\Asset::css('series', 'place.css');

	}

	public function get(Series $eSeries): string {

		$tip = $eSeries['soilTip'] ?? [];

		$h = '<div class="place-tip-body">';

			$h .= '<div class="place-tip-head">';
				$h .= '<b>'.encode($eSeries['name']).'</b>';
			$h .= '</div>';

			$h .= $this->getStart($tip);
			$h .= $this->getHarvest($tip);

		$h .= '</div>';

		// Le marqueur que le JS relit pour verifier que la reponse concerne encore la
		// serie survolee : c'est du rendu, il sort d'ici et pas de la vue.
		$h .= '<i data-series="'.$eSeries['id'].'"></i>';

		return $h;

	}

	/**
	 * Une seule ligne de départ, celle qui existe : une culture porte un semis ou
	 * une plantation, jamais les deux. Mesuré : plantation 68,3 %, semis 24,1 %,
	 * rien 7,6 %. Deux lignes dont une vide n'apprendraient rien.
	 */
	protected function getStart(array $tip): string {

		if(($tip['start'] ?? NULL) === NULL) {
			return '';
		}

		$label = match($tip['startAction']) {
			Cultivation::SOWING => s("Semis"),
			Cultivation::PLANTING => s("Plantation"),
		};

		return '<div class="place-tip-line"><span class="place-tip-key">'.$label.'</span> '.s("s{value}", $tip['start']).'</div>';

	}

	protected function getHarvest(array $tip): string {

		$count = $tip['count'] ?? 0;

		if($count === 0) {
			return '';
		}

		// « Récolte » ou « Récolte prévue » : le réalisé ne couvre que 7,2 % des
		// cultures, écrire « Récolte » partout affirmerait du réalisé neuf fois
		// sur dix à tort.
		$label = ($tip['done'] ?? FALSE) ? s("Récolte") : s("Récolte prévue");

		$gaps = $tip['gaps'] ?? 0;

		// Le compte d'abord, l'intervalle ensuite : 45 % des listes à plus d'une
		// semaine ont des trous, si bien que « s32 - s50 » laissait croire à
		// dix-neuf semaines pleines là où il y en avait quatorze. Le nombre est ce
		// qu'on cherche, les bornes ne le situent que dans l'année.
		$h = '<div class="place-tip-line">';
			$h .= '<span class="place-tip-key">'.$label.'</span> ';
			$h .= p("{value} semaine", "{value} semaines", $count);

			if($tip['first'] !== $tip['last']) {
				$h .= ', '.s("de la {first} à la {last}", ['first' => $tip['first'], 'last' => $tip['last']]);
			} else {
				$h .= ', '.s("la {value}", $tip['first']);
			}

			if($gaps > 0) {
				$h .= ' · '.p("{value} interruption", "{value} interruptions", $gaps);
			}
		$h .= '</div>';

		return $h;

	}

}
?>
