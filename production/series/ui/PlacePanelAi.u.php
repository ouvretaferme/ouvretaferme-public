<?php
namespace series;

/**
 * Les panneaux d'action du plan d'assolement.
 *
 * Trois grains, trois panneaux, tous ancrés à l'objet qu'ils visent :
 *  - la série, ouverte d'un clic sur son bloc ;
 *  - la planche dans la série, après un second clic qui n'en retient qu'une ;
 *  - la planche elle-même, ouverte d'un clic sur son nom.
 *
 * Ce sont des dropdowns du framework : ils héritent du positionnement, du
 * repli plein écran sur mobile et de la fermeture au clic extérieur. Le
 * contenu est chargé en ajax à l'ouverture — le rendre pour les quelques
 * centaines de blocs d'un plan alourdirait la page pour rien.
 */
class PlacePanelAiUi {

	/** Les titres de notes qu'un menu de planche montre avant « Voir toutes les notes ». */
	private const BED_NOTES_MAX = 3;

	public function __construct() {

		\Asset::css('series', 'place.css');
		\Asset::js('series', 'place.js');

	}

	/**
	 * Une entrée du panneau. Le marqueur de droite (raccourci clavier, état
	 * armé, longueur en cours) tient dans la même ligne que le libellé.
	 */
	protected function getItem(string $icon, string $label, ?string $onclick = NULL, string $class = '', ?string $hint = NULL, ?string $attributes = NULL): string {

		if($attributes === NULL) {
			$attributes = 'onclick="'.$onclick.'" class="dropdown-item dropdown-item-icon '.$class.'"';
		}

		$h = '<a '.$attributes.'>';
			$h .= \Asset::icon($icon);
			$h .= '<span class="place-item-label">';
				$h .= '<span>'.$label.'</span>';
				if($hint !== NULL) {
					$h .= '<i class="place-item-hint">'.$hint.'</i>';
				}
			$h .= '</span>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * La grille libellé / valeur d'un dropdown : le composant util-presentation
	 * du produit, que le dropdown sait désormais habiller sur fond sombre.
	 */
	protected static function getPresentation(array $lines): string {

		$h = '<dl class="util-presentation util-presentation-1 dropdown-presentation">';

			foreach($lines as $label => $value) {
				$h .= '<dt>'.$label.'</dt>';
				$h .= '<dd>'.$value.'</dd>';
			}

		$h .= '</dl>';

		return $h;

	}

	/**
	 * Le panneau d'une planche : ce qu'elle est, ce qu'elle porte, ce qui y est
	 * déjà passé, et les gestes qui visent la planche plutôt qu'une culture.
	 */
	public function getBedPanel(\map\Bed $eBed, Series $eSeries, \Collection $cSeriesBed, array $rotations, ?array $freeGap, \Collection $cBedSwap, int $season, \Collection $cNoteLinked): string {

		$h = '<div class="dropdown-title">';

			$h .= s("Planche {value}", encode($eBed['name']));

			$h .= match($eBed['plot']['mode']) {
				\map\Plot::GREENHOUSE => ' <span class="badge badge-greenhouse">'.s("Abri").'</span>',
				default => ' <span class="badge badge-production">'.s("Plein champ").'</span>',
			};

			$h .= '<div class="font-sm">'.encode(\map\PlotUi::getName($eBed['plot'])).' · '.s("Parcelle {value}", encode($eBed['zone']['name'])).'</div>';

		$h .= '</div>';

		$lines = [];

		// Une surface libre n'a ni longueur ni largeur : le gabarit rendait ses
		// deux placeholders tels quels, « length m × width cm ».
		if($eBed['length'] and $eBed['width']) {
			$lines[s("Dimensions")] = s("{length} m × {width} cm", $eBed);
		}

		if($eBed['area']) {
			$lines[s("Surface")] = s("{value} m²", $eBed['area']);
		}


		$h .= self::getPresentation($lines);

		$h .= $this->getBedRotations($rotations);

		$h .= $this->getBedNotes($eBed, $cNoteLinked);

		$h .= '<div class="dropdown-divider place-item-write"></div>';

		if($eSeries->notEmpty()) {
			$h .= $this->getItem('plus-circle', s("Poser {value} ici", encode($eSeries['name'])), 'SeriesPanel.placeHere('.$eBed['id'].')', 'place-item-write');
		}

		$h .= $this->getItem('arrow-left-right', s("Échanger avec une autre planche"), 'SeriesPanel.toggleSwap(this)', 'place-item-write');
		$h .= $this->getSwapChoices($eBed, $cBedSwap);

		// La saison est obligatoire : bed:update la valide et refuse la requête sans elle
		$h .= $this->getItem('pencil', s("Modifier la planche"), attributes: 'href="/map/bed:update?id='.$eBed['id'].'&amp;season='.$season.'" class="dropdown-item dropdown-item-icon place-item-write"');

		if($cSeriesBed->notEmpty()) {
			$h .= $this->getItem('eraser', s("Vider la planche"), 'SeriesPanel.emptyBed('.$eBed['id'].')', 'place-item-danger place-item-write');
		}

		return $h;

	}

	/**
	 * Les notes liées à la planche (L84), par leur titre : le menu n'a pas la place d'une carte. Trois au
	 * plus, l'épinglée d'abord, puis toutes celles de la planche dans la liste des notes.
	 */
	protected function getBedNotes(\map\Bed $eBed, \Collection $cNoteLinked): string {

		if($cNoteLinked->empty()) {
			return '';
		}

		$h = '<div class="dropdown-divider"></div>';

		foreach($cNoteLinked->slice(0, min(self::BED_NOTES_MAX, $cNoteLinked->count())) as $eNote) {
			$h .= $this->getItem($eNote['pinned'] ? 'pin-angle-fill' : 'journal-text', encode($eNote['title']), attributes: 'href="'.\note\NoteUi::url($eNote).'" class="dropdown-item dropdown-item-icon"');
		}

		if($cNoteLinked->count() > self::BED_NOTES_MAX) {
			$url = \note\NoteUi::urlTarget(\note\NoteUi::urlStructure($eBed['structure']), $eBed['eTarget']);
			$h .= $this->getItem('chevron-right', s("Voir toutes les notes"), attributes: 'href="'.$url.'" class="dropdown-item dropdown-item-icon"');
		}

		return $h;

	}

	/**
	 * Le choix de la planche d'échange, déplié sous l'entrée : sans lui il
	 * faudrait quitter le plan pour un geste qui s'y joue entièrement.
	 */
	protected function getSwapChoices(\map\Bed $eBed, \Collection $cBedSwap): string {

		$h = '<div class="place-panel-swap place-panel-fold dropdown-block" data-bed="'.$eBed['id'].'">';

			foreach($cBedSwap as $eBedSwap) {

				if($eBedSwap['id'] === $eBed['id']) {
					continue;
				}

				$h .= '<a onclick="SeriesPanel.swapBed('.$eBed['id'].', '.$eBedSwap['id'].')" class="place-panel-swap-item">'.encode($eBedSwap['name']).'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getBedRotations(array $rotations): string {

		if(array_filter($rotations, fn($families) => $families !== []) === []) {
			return '';
		}

		$lines = [];

		// Une saison sans culture ne dit rien qu'on ait besoin de lire : la ligne
		// disparaît plutôt que d'annoncer son propre vide.
		foreach($rotations as $season => $families) {

			if($families === []) {
				continue;
			}

			$lines[$season] = implode(', ', array_map('encode', $families));

		}

		$h = '<div class="dropdown-subtitle">'.s("Familles déjà passées").'</div>';
		$h .= self::getPresentation($lines);

		return $h;

	}

}
?>
