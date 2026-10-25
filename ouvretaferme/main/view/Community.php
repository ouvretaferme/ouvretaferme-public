<?php
class CommunityTemplate extends GroupTemplate implements DynamicTemplate {

	public string $template = 'community base ';

	public ?string $mainType = NULL;

	public string $tab = '';

	// Un bandeau que le groupe ne peut plus remplacer ne se rend plus, même encore posé en base.
	private function hasBanner(): bool {

		return (
			$this->data->eStructure->acceptBanner() and
			$this->data->eStructure['banner'] !== NULL
		);

	}

	// Sous une photo, l'encre dérivée de la couleur se lirait sur le dégradé sombre de l'image, qui la
	// cache : le bandeau garde alors les jetons par défaut, donc du blanc.
	protected function getBandStyle(): string {

		return $this->hasBanner() ? '' : \structure\StructureUi::getCommunityColorStyle($this->data->eStructure, $this->data->eCommunity);

	}

	// Sans photo, rien : pas de bloc vide portant un dégradé, qui assombrirait l'aplat sans rien
	// recouvrir. C'est le cas le plus fréquent — une photo de bandeau est plus dure à fournir qu'un
	// logo, que 10,2 % des fermes seulement ont posé.
	protected function getBandMedia(): string {

		if($this->hasBanner() === FALSE) {
			return '';
		}

		return '<div class="community-header-media" style="background-image: url('.new \media\StructureBannerUi()->getUrlByElement($this->data->eStructure, 'l').')"></div>';

	}

	protected function getBandClass(): string {
		return $this->data->eStructure->isNetwork() ? 'community-header-network' : '';
	}

	protected function getBandType(): string {

		return $this->mainType ?? \structure\StructureUi::getCommunityType($this->data->eStructure);

	}

	// ⚠️ La rangée reste pendant la mise en route : le parcours ne tient que l'accueil, et les autres
	// écrans s'ouvrent depuis lui.
	protected function getBandTabs(): string {

		$class = \structure\StructureUi::getClass($this->data->eStructure);

		return new $class()->getTabs(
			$this->data->eStructure,
			$this->data->members,
			$this->data->hasStructures,
			$this->data->hasLearningShared,
			$this->tab,
			$this->data->eSection
		);

	}

	protected function getBandActions(): string {

		$class = \structure\StructureUi::getClass($this->data->eStructure);

		return new $class()->getActionsMember(
			$this->data->eStructure,
			$this->data->eMember,
			$this->data->cStructureMe,
			$this->data->cSectionMe
		);

	}

	// La barre de portée, qu'aucun écran ne montre pendant la mise en route. Une ligne posée dans
	// `scopeLine` sort du même `getScopeLine()`, pour que la bande ait une seule forme quel que soit
	// l'écran qui la filtre.
	protected function getBandScope(): string {

		if($this->data->start !== NULL) {
			return '';
		}

		$class = \structure\StructureUi::getClass($this->data->eStructure);

		return $this->data->scopeLine ?? new $class()->getSectionLine(
			$this->data->eStructure,
			$this->data->cSection,
			$this->data->cSectionClosed,
			$this->data->eSection,
			$this->data->sectionChoice,
			$this->data->sectionApply
		);

	}

	protected function getMastheadIcon(): string {
		return \structure\StructureUi::getCommunityTabIcon($this->tab);
	}

	// Un réseau a sa propre teinte de bande, la même pour tous (`--network-background`).
	protected function getMastheadClass(): string {
		return $this->data->eStructure->isNetwork() ? 'util-masthead-network' : '';
	}

	protected function getMastheadStyle(): string {
		return \structure\StructureUi::getCommunityColorStyle($this->data->eStructure, $this->data->eCommunity);
	}

	protected function getContent(string $stream): string {

		$class = \structure\StructureUi::getClass($this->data->eStructure);

		$h = '';

		// L'appel à l'action vit dans le gabarit et non dans chaque vue : c'est le même geste qu'on
		// propose sur tous les écrans du groupe tant qu'il n'est pas peuplé, et le recopier vue par vue
		// l'aurait laissé diverger. Les routes qui le montrent le demandent par `onboardingApply`, dont
		// le défaut est FAUX.
		//
		// ⚠️ Un groupe en cours de mise en route porte son parcours à la place, sur l'accueil, et rien
		// sur les autres écrans (`prepareStart()`) : deux guides pour le même geste ne se mettraient
		// pas à jour ensemble. `start` ne se pose que sur la route qui déclare `startApply`, et vaut
		// `NULL` partout ailleurs.
		if($this->data->startWelcome) {

			$h .= \structure\CommunityStartAiUi::getWelcome(
				$this->data->eStructure,
				$this->data->cSection,
				$this->data->startCounts,
				$this->tab
			);

		} else if($this->data->start !== NULL) {

			$h .= \structure\CommunityStartAiUi::getStartSteps(
				$this->data->eStructure,
				$this->data->cSection,
				$this->data->startCounts,
				$this->data->eInviteLink,
				$this->data->start,
				$this->data->startOrigin
			);

		} else if($this->data->onboardingApply) {
			$h .= new $class()->getOnboarding($this->data->eStructure, $this->data->onboarding);
		}

		// Le parcours remplace l'accueil tant que la mise en route n'est pas terminée.
		if($this->data->start === NULL) {
			$h .= $stream;
		}

		return $h;

	}

}
?>
