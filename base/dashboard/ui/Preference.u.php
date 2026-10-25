<?php
namespace dashboard;

/**
 * Les contrôles de l'accueil en réglage : l'interrupteur que chaque titre prend à sa place, les
 * étages que l'on range, la barre qui ferme le réglage (L43, piste « Réglé sur place, depuis la
 * roue », tranchée le 2026-10-12).
 *
 * `DashboardUi` dessine l'accueil, ce fichier ce qui s'y pose quand on le règle : l'accueil ordinaire
 * n'en charge rien, ni Sortable ni `preference.js`.
 *
 * ⚠️ Comme tout le package, ce fichier **ne nomme aucun bloc ni aucune contribution**, et ne cite aucun
 * `\<package>\DashboardUi` littéral : les noms voyagent en variable et la classe du package se résout à
 * l'exécution. `test/DashboardBlockSentinel.t.php` lit tous les fichiers d'ici sauf `test/`.
 */
class PreferenceUi {

	public function __construct() {
		\Asset::lib('util', 'Sortable-1.11.0.js');
		\Asset::js('dashboard', 'preference.js');
	}

	/**
	 * Les trois actions du réglage. Elles portent la structure en `post-id`, que `ModulePage::write()`
	 * relit et valide (`framework/core/Module.php`) : c'est le droit de la structure qui garde le geste,
	 * et non la confiance qu'on met dans l'écran qui l'envoie.
	 */
	const string ACTION_HIDDEN = '/dashboard/preference:doUpdateHidden';
	const string ACTION_SECTION = '/dashboard/preference:doUpdateSection';
	const string ACTION_POSITIONS = '/dashboard/preference:doUpdatePositions';

	/**
	 * L'identifiant de la carte d'un univers dans l'encadré du réglage, que `doUpdateSection` et
	 * `doUpdateHidden` renvoient.
	 */
	public static function getSectionId(string $section): string {
		return 'dashboard-setting-section-'.$section;
	}

	/**
	 * La rangée des univers, que l'on range : sans `post-scope`, `doUpdatePositions` y lit des univers.
	 *
	 * `onrender` et non `DOMContentLoaded`, qui ne se redéclenche pas après une navigation ajax.
	 */
	public function getSectionSort(\structure\Structure $eStructure): string {
		return ' onrender="DashboardPreference.initSections(this)" post-scope=""'.$this->getSortAction($eStructure);
	}

	/**
	 * Une colonne de blocs. Elle ne porte pas d'univers : chaque cellule porte le sien en
	 * `data-section`, et c'est l'univers du bloc pris qui borne le geste et ce qu'il poste.
	 */
	public function getColumnSort(\structure\Structure $eStructure): string {
		return ' onrender="DashboardPreference.initColumn(this)"'.$this->getSortAction($eStructure);
	}

	private function getSortAction(\structure\Structure $eStructure): string {
		return ' data-sort-url="'.self::ACTION_POSITIONS.'" post-id="'.$eStructure['id'].'"';
	}

	/**
	 * Les deux interrupteurs, et la raison qu'ils ont d'être deux gestes et non un.
	 *
	 * Un univers écrit une ligne par bloc, un bloc une seule : ce sont deux règles, donc deux actions,
	 * et la réponse de chacune ne rend pas les mêmes nœuds.
	 *
	 * Chacun poste ce que le geste écrit, et non l'état présent : l'interrupteur allumé poste « cache »,
	 * et c'est ce qui rend l'action idempotente quand deux clics se suivent.
	 *
	 * ⚠️ Les clés postées restent en **minuscules** : l'analyseur HTML abaisse les noms d'attributs, et
	 * un `post-figureName` ne posterait rien.
	 */
	public function getSectionSwitch(\structure\Structure $eStructure, string $section, bool $on): string {

		return \util\TextUi::switch([
			'data-ajax' => self::ACTION_SECTION,
			'post-id' => $eStructure['id'],
			'post-scope' => $section,
			'post-hidden' => $on ? 1 : 0
		], on: $on, textOn: s("Oui"), textOff: s("Non"));

	}

	/**
	 * L'interrupteur d'un bloc, ou d'une nouvelle du blog sous le nom de son emplacement en guise
	 * d'univers : `doUpdateHidden` cherche alors le nom parmi les occupants de l'emplacement.
	 */
	public function getBlockSwitch(\structure\Structure $eStructure, string $scope, string $name, bool $on): string {

		return \util\TextUi::switch([
			'data-ajax' => self::ACTION_HIDDEN,
			'post-id' => $eStructure['id'],
			'post-name' => $name,
			'post-scope' => $scope,
			'post-hidden' => $on ? 1 : 0
		], on: $on, textOn: s("Oui"), textOff: s("Non"));

	}

	/**
	 * La barre qui ferme le réglage, posée en bas de l'écran pendant qu'on règle. « Terminer » est un
	 * lien vers l'accueil : chaque geste est déjà écrit, il n'y a rien à valider.
	 */
	public function getBar(\structure\Structure $eStructure): string {

		$h = '<div class="util-block-info util-block-sticked dashboard-setting-bar">';
			$h .= '<span>'.s("Réglage du tableau de bord").'</span>';
			$h .= '<a href="'.DashboardUi::url($eStructure).'" class="btn btn-light">'.s("Terminer").'</a>';
		$h .= '</div>';

		return $h;

	}

}
?>
