<?php
namespace structure;

/**
 * La liste du sélecteur de structure, ouverte par le nom de la structure (7A, décidé le 2026-10-03).
 *
 * Jusqu'à `SEARCH` structures, tout se lit d'un coup d'œil : les structures puis les groupes, chacun sous
 * son intertitre. Au-delà, la liste commence par la recherche et les structures récentes, la courante en
 * tête, et range le reste dans deux replis qui disent leur nombre ; la recherche ouvre les replis et masque
 * les récentes, qui feraient sinon apparaître deux fois la même structure (`Structure.filterSelector()`).
 */
class SelectorAiUi {

	/**
	 * Le seuil de la recherche, celui de la liste qu'elle remplace.
	 */
	const SEARCH = 10;

	/**
	 * La courante et les quatre dernières visitées.
	 */
	const RECENT = 5;

	/**
	 * L'univers courant s'ajoute au moment du geste (`Structure.openSelector()`) : une navigation ajax en
	 * change sans redessiner la navigation qui porte le déclencheur.
	 */
	public static function url(Structure $eStructure): string {
		return '/structure/selectorAi?structure='.$eStructure['id'];
	}

	public function get(Structure $eStructureCurrent, \Collection $cStructureOwn, \Collection $cStructureCommunity, \Collection $cStructureRecent, bool $home, bool $search): string {

		$h = $home ? $this->getHome() : '';

		if($search) {
			$h .= $this->getLarge($eStructureCurrent, $cStructureOwn, $cStructureCommunity, $cStructureRecent);
		} else {
			$h .= $this->getSmall($eStructureCurrent, $cStructureOwn, $cStructureCommunity);
		}

		return $h;

	}

	protected function getHome(): string {

		$h = '<a href="'.\Lime::getUrl().'" class="dropdown-item dropdown-item-icon">';
			$h .= '<span class="structure-selector-home-icon">'.\Asset::icon('house-fill').'</span>';
			$h .= '<span>'.s("Accueil").'</span>';
		$h .= '</a>';
		$h .= '<div class="dropdown-divider"></div>';

		return $h;

	}

	protected function getSmall(Structure $eStructureCurrent, \Collection $cStructureOwn, \Collection $cStructureCommunity): string {

		$h = '';

		foreach($this->getGroups($cStructureOwn, $cStructureCommunity) as [$title, $cStructure]) {

			$h .= '<div class="dropdown-subtitle">'.$title.'</div>';
			$h .= $this->getItems($eStructureCurrent, $cStructure);

		}

		return $h;

	}

	protected function getLarge(Structure $eStructureCurrent, \Collection $cStructureOwn, \Collection $cStructureCommunity, \Collection $cStructureRecent): string {

		$h = '<div class="structure-selector-search" data-dropdown-keep>';
			$h .= new \util\FormUi()->text('structureSearch', NULL, ['placeholder' => s("Chercher une structure"), 'clearable' => TRUE, 'autocomplete' => 'off']);
		$h .= '</div>';

		$h .= '<div class="dropdown-text dropdown-info structure-selector-empty hide">'.s("Aucune structure ne correspond").'</div>';

		if($cStructureRecent->notEmpty()) {

			$h .= '<div class="structure-selector-recent">';
				$h .= '<div class="dropdown-subtitle">'.s("Récentes").'</div>';
				$h .= $this->getItems($eStructureCurrent, $cStructureRecent);
				$h .= '<div class="dropdown-divider"></div>';
			$h .= '</div>';

		}

		foreach($this->getGroups($cStructureOwn, $cStructureCommunity) as [$title, $cStructure]) {

			// data-dropdown-keep : sans lui, le clic qui déplie le repli fermerait la liste
			// (lime.js, Lime.Dropdown.startCloseListener).
			$h .= '<a class="dropdown-item dropdown-item-group structure-selector-group" data-dropdown-keep '.attr('onclick', 'this.classList.toggle("dropdown-item-group-open")').'>';
				$h .= '<span class="dropdown-item-group-chevron">'.\Asset::icon('chevron-down').'</span>';
				$h .= '<span>'.$title.'</span>';
				$h .= '<span class="structure-selector-count">'.$cStructure->count().'</span>';
			$h .= '</a>';

			$h .= '<div class="dropdown-item-group-items">';
				$h .= $this->getItems($eStructureCurrent, $cStructure);
			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Les intertitres, chacun avec sa collection, et seulement pour une collection qui n'est pas vide.
	 *
	 * Le mot est celui de la famille, lu sur un élément qui n'en porte que le type, et non celui de la
	 * première structure venue : les structures mêlent productrices, boutiques et AMAP, et les groupes
	 * plusieurs natures, dont chacune a son propre nom (« Mes AMAP », « Mes réseaux »).
	 */
	protected function getGroups(\Collection $cStructureOwn, \Collection $cStructureCommunity): array {

		$groups = [];

		foreach([
			[new Structure(['type' => Structure::PRODUCER]), $cStructureOwn],
			[new Structure(['type' => Structure::COMMUNITY, 'communityType' => NULL]), $cStructureCommunity],
		] as [$eStructureFamily, $cStructure]) {

			if($cStructure->notEmpty()) {
				$groups[] = [StructureUi::getText($eStructureFamily, 'my', $cStructure->count()), $cStructure];
			}

		}

		return $groups;

	}

	protected function getItems(Structure $eStructureCurrent, \Collection $cStructure): string {

		$h = '';

		foreach($cStructure as $eStructure) {
			$h .= StructureUi::getDropdownItem($eStructure, $eStructure['menuUrl'], $eStructure['id'] === $eStructureCurrent['id']);
		}

		return $h;

	}

}
?>
