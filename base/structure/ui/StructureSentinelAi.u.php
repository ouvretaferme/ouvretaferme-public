<?php
namespace structure;

class StructureSentinelAiUi {

	public function __construct() {
		\Asset::css('structure', 'structure.css');
	}

	/**
	 * Le choix de la structure sur laquelle ouvrir l'adresse demandée.
	 *
	 * Les decks sont ceux de l'accueil, sans leurs portes : la question posée ici n'est pas quel
	 * outil ouvrir, mais sur quelle structure ouvrir celui qui a été demandé. Chaque deck n'a donc
	 * qu'une destination, et c'est le bloc entier qui est le lien.
	 */
	public function getChoice(\Collection $cStructure): string {

		$h = '<div class="member-structures util-cards util-cards-3">';

			foreach($cStructure as $eStructure) {

				$h .= '<a href="'.encode($eStructure['urlRequested']).'" class="util-deck">';

					$h .= '<div class="util-deck-head">';

						$h .= StructureUi::getLogo($eStructure, '4rem');

						$h .= '<div class="util-deck-body">';

							$h .= '<div class="util-deck-title">'.encode($eStructure['name']).'</div>';

							if($eStructure['legalCity'] !== NULL) {
								$h .= '<p>'.\Asset::icon('geo-fill').' '.encode($eStructure['legalCity']).'</p>';
							}

						$h .= '</div>';

						$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';

					$h .= '</div>';

				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

}
?>
