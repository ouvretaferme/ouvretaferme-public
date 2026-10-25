<?php
namespace entry;

class SeasonComplementUi {

	/**
	 * « Saison 2025 », en texte brut, comme le produit l'écrit (`\series\SeriesUi::getPanelHeader()`),
	 * d'un seul tenant : la confirmation passe à la ligne à 360 px, et l'année ne s'y sépare pas du mot.
	 * C'est aussi ce que la lecture comprend (`SeasonComplementLib::WORD`).
	 */
	public static function getSeason(int $season): string {

		return s("Saison\u{00A0}{value}", $season);

	}

}
?>
