<?php
namespace planning;

/**
 * Ce qui est dû hors cultures, en tête d'une semaine du planning. Chaque source rend son bloc par son
 * observateur `<package>/ui/Planning.o.php` (`PlanningObserverUi::getDue()`), qui porte ses mots et
 * ses gestes.
 */
class DueUi {

	/**
	 * `$dues` sort de `DueLib::getForWeek()` : une collection par source, aucune vide. Les blocs se
	 * tiennent dans `#planning-due`, que le geste fait depuis l'un d'eux remplace seul
	 * (`getTarget()`) : la semaine voyage avec le geste pour que le bloc rendu soit celui qu'on regarde.
	 */
	public function getWeek(\structure\Structure $eStructure, string $week, array $dues): string {

		if($dues === []) {
			return '';
		}

		$h = '<div id="planning-due">';

			foreach($dues as $source => $cDue) {

				$observer = self::getObserver($source);

				$h .= '<div class="planning-week-title">'.$observer::getDueTitle().'</div>';
				$h .= $observer::getDue($eStructure, $week, $cDue, DueLib::ORIGIN_BLOCK);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La même chose au nouveau planning (retour 15 du 04/10) : une zone par source, sous les
	 * blocs de la Semaine ou les colonnes du Jour, de la forme d'« En retard » et « Non planifié »
	 * (`TaskWeekUi::getZone()`), dont la source pose les cartes (`DueLib::ORIGIN_ZONE`). La Semaine garde
	 * son repli (`$folded`, `PlanningTaskZone.fold()`) ; un geste de la zone la redessine ouverte, puisqu'il
	 * part d'elle ouverte.
	 */
	public function getZones(\structure\Structure $eStructure, string $week, array $dues, array $folded = []): string {

		if($dues === []) {
			return '';
		}

		$h = '<div id="planning-due">';

			foreach($dues as $source => $cDue) {

				$observer = self::getObserver($source);

				$open = (in_array($source, $folded, TRUE) === FALSE);

				$h .= '<div class="util-fold mt-2 '.($open ? 'util-fold-open' : '').'" id="planning-due-'.$source.'">';

					$h .= '<a class="util-fold-head">';
						$h .= '<h3>'.$observer::getDueTitle().' <span class="util-counter">'.$cDue->count().'</span></h3>';
						$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
					$h .= '</a>';

					$h .= '<div class="util-fold-content">';
						$h .= $observer::getDue($eStructure, $week, $cDue, DueLib::ORIGIN_ZONE);
					$h .= '</div>';

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected static function getObserver(string $source): string {
		return '\\'.$source.'\\PlanningObserverUi';
	}

	/**
	 * Ce que remplace la réponse d'un geste fait au planning, et l'adresse que ce geste transmet à son
	 * panneau : l'origine (`DueLib::isOrigin()`), que le formulaire réémet, et la semaine affichée.
	 */
	public static function getTarget(): string {
		return '#planning-due';
	}

	public static function getOrigin(string $week, string $origin): string {
		return 'origin='.$origin.'&week='.$week;
	}

	/**
	 * La croix d'une rangée, qui passe ce qui est dû sans rien enregistrer (demande du
	 * 03/10). Elle poste la semaine affichée comme le formulaire d'un panneau réémet `getOrigin()`, et
	 * l'action répond par `planning/due:skip`. `$label` sert la rangée de l'ancien planning ; une carte de
	 * la zone nomme déjà ce qu'elle passe, sa porte dit « Passer ».
	 */
	public static function getSkip(string $url, int $id, string $week, string $origin, string $label): string {

		return match($origin) {
			DueLib::ORIGIN_BLOCK => self::getGesture($url, $id, $week, $origin, $label, 'x-lg'),
			DueLib::ORIGIN_ZONE => self::getGesture($url, $id, $week, $origin, s("Passer"), 'x-lg', FALSE),
		};

	}

	/**
	 * Le décalage d'une rangée à la semaine suivante (retour 15 du 04/10), posté comme la
	 * croix : la date se calcule à l'action, depuis la ligne du planning (`DueLib::getPostponedDate()`), et
	 * la semaine postée ne sert qu'à redessiner la semaine affichée.
	 */
	public static function getPostpone(string $url, int $id, string $week, string $origin): string {
		return self::getGesture($url, $id, $week, $origin, s("Plus tard"), 'arrow-right');
	}

	/**
	 * Le bouton d'un geste selon la forme d'où il part : en contour dans une rangée de l'ancien planning, le
	 * premier geste (`$main`) en contour noir et les suivants en contour gris, pour que le rang se lise au cerne :
	 * un contour de l'univers, plus contrasté que le cerne noir, passerait devant le premier geste.
	 * En porte d'une carte au nouveau, tous de la même teinte claire.
	 */
	public static function getGestureClass(string $origin, bool $main = FALSE): string {

		return match($origin) {
			DueLib::ORIGIN_BLOCK => $main ? 'btn btn-sm btn-outline-primary' : 'btn btn-sm btn-outline-muted',
			DueLib::ORIGIN_ZONE => 'btn btn-light',
		};

	}

	/**
	 * L'icône seule sous 576 px (`$hide`), comme les têtes de page : la rangée garde alors son nom et ses deux
	 * gestes sur une ligne. Au-dessus, le libellé dit ce que l'icône ne dit pas (une flèche, une croix). La
	 * croix d'une carte garde son libellé court à toute largeur : seule dans une porte claire, elle se lirait
	 * « fermer la carte ».
	 */
	protected static function getGesture(string $url, int $id, string $week, string $origin, string $label, string $icon, bool $hide = TRUE): string {

		$h = '<a data-ajax="'.$url.'" post-id="'.$id.'" post-week="'.$week.'" post-origin="'.$origin.'" class="'.self::getGestureClass($origin).'">';
			$h .= \Asset::icon($icon);
			$h .= $hide ? '<span class="hide-xs-down"> '.$label.'</span>' : ' '.$label;
		$h .= '</a>';

		return $h;

	}

}
?>
