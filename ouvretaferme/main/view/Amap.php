<?php
/**
 * Une AMAP vue de l'intérieur, sous le bandeau d'un groupe : le bureau qui la gère, l'adhérent qui
 * y a son espace, le référent qui y suit ses producteurs entrent tous par lui. Les onglets sont les
 * menus que `MenuLib::getAmapGroupMenus()` ouvre au compte connecté, posés par `AmapAction`, et un
 * menu de plusieurs pages en ouvre la liste depuis son onglet.
 *
 * Les vues y écrivent `nav` et `subNav`, les noms de `StructureTemplate`, pour qu'un écran que l'AMAP
 * partage avec une ferme garde une seule écriture.
 */
class AmapTemplate extends GroupTemplate implements DynamicTemplate {

	public string $template = 'amap base ';

	/** Le menu ouvert. */
	public ?string $nav = NULL;

	/** La page ouverte dans ce menu. */
	public ?string $subNav = NULL;

	private ?array $tabs = NULL;

	private function getTabs(): array {

		return $this->tabs ??= new \structure\StructureAmapUi()->getGroupTabs($this->data->eStructure, $this->data->amapMenus);

	}

	protected function getBandStyle(): string {

		return \structure\StructureUi::getCommunityColorStyle($this->data->eStructure, new \structure\Community());

	}

	// La pastille de l'onglet ouvert prend la teinte de la bande claire qui suit.
	protected function getBandClass(): string {
		return 'community-header-amap';
	}

	protected function getBandType(): string {

		return s("AMAP");

	}

	// Un adhérent hors du bureau n'a pas de rangée (`getGroupTabs()`) : ni onglets, ni menu replié.
	protected function getBandTabs(): string {

		$tabs = $this->getTabs();

		if($tabs === []) {
			return '';
		}

		return \structure\StructureCommunityUi::getTabsRow($tabs, $this->nav ?? '', $this->subNav ?? '').\structure\StructureCommunityUi::getTabsMenu($tabs, $this->nav ?? '', $this->subNav ?? '');

	}

	protected function getBandActions(): string {

		return new \structure\StructureAmapUi()->getActionsMember($this->data->eStructure, $this->data->eMemberOnline);

	}

	protected function getMastheadIcon(): string {
		return \structure\StructureAmapUi::getGroupTabIcon($this->nav);
	}

	protected function getMastheadClass(): string {
		return 'util-masthead-amap';
	}

	// La bande claire prend la couleur du bandeau, celle de l'univers AMAP.
	protected function getMastheadStyle(): string {
		return $this->getBandStyle();
	}

	// Les pages d'un menu s'ouvrent depuis son onglet : la barre sous le bandeau n'a rien à porter.
	protected function getBandScope(): string {
		return '';
	}

}
?>
