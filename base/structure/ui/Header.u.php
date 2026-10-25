<?php
namespace structure;

class HeaderUi {

	public static function getTeam(Structure $eStructure, string $selectedView): string {

		// L'administration d'une AMAP s'ouvre depuis sa page Paramètres, et y ramène.
		return \main\DesignUi::getPageHeader(
			self::getTeamCategories($eStructure)[Member::TEAM],
			back: $eStructure->isAmap() ? StructureUi::urlAmapSettings($eStructure) : '',
			create: self::getTeamActions($eStructure, $selectedView),
			backLabel: $eStructure->isAmap() ? s("Paramètres") : ''
		);

	}

	public static function getTeamTabs(Structure $eStructure, string $selectedView): string {

		$categories = self::getTeamCategories($eStructure);

		if(
			count($categories) === 1 and
			$eStructure->acceptProduction() === FALSE
		) {
			return '';
		}

		$tabs = '';

		// L'onglet d'une structure de production liste son équipe et, dessous, ceux qui ont un accès sans en être :
		// il se nomme d'après les deux, la page gardant le titre « Équipe ».
		if($eStructure->acceptProduction()) {
			$categories[Member::TEAM] = s("Personnes");
		}

		foreach($categories as $key => $value) {

			$tabs .= '<a href="'.StructureUi::urlStructureTeam($eStructure, $key).'" class="tab-item '.($key === $selectedView ? 'selected' : '').'">'.$value.'</a>';

			// Les onglets de la RH suivent les personnes, et les rôles, qui se règlent rarement, restent les derniers.
			// Le compteur des absences ne dit que les demandes qui attendent une réponse : c'est ce que le gérant vient
			// y chercher.
			if(
				$key === Member::TEAM and
				$eStructure->acceptHr()
			) {

				$counter = ($eStructure['absencesPending'] > 0) ? ' <span class="util-counter util-counter-solid">'.$eStructure['absencesPending'].'</span>' : '';

				$tabs .= '<a href="'.\hr\AbsenceUi::urlStructure($eStructure).'" class="tab-item '.($selectedView === \hr\AbsenceUi::TAB ? 'selected' : '').'">'.s("Absences").$counter.'</a>';
				$tabs .= '<a href="'.\hr\RecapUi::urlStructure($eStructure).'" class="tab-item '.($selectedView === \hr\RecapUi::TAB ? 'selected' : '').'">'.s("Synthèse mensuelle").'</a>';

			}

		}

		return \main\DesignUi::getPageTabs($tabs);

	}

	protected static function getTeamActions(Structure $eStructure, string $selectedView): string {

		switch($selectedView) {

			case Member::TEAM :
				if($eStructure->isAmap()) {
					return $eStructure['inviteCreate'] ? '<a href="'.InviteUi::urlCreateMember($eStructure, $eStructure['eRoleAdmin']).'" class="btn btn-secondary">'.\Asset::icon('person-plus-fill').' '.s("Inviter un administrateur").'</a>' : '';
				} else if($eStructure->acceptProduction()) {
					return self::getTeamAdd($eStructure);
				} else {
					return '<a href="/structure/invite:createMember?structure='.$eStructure['id'].'" class="btn btn-secondary">'.\Asset::icon('person-plus-fill').' '.s("Nouvelle invitation").'</a>';
				}

		}

		return '';

	}

	/**
	 * Une structure de production fait entrer quelqu'un de deux façons, qui ne mènent pas au même formulaire :
	 * l'invitation, par laquelle la personne crée son compte, ou la personne sans compte, que le planning porte
	 * sans qu'elle se connecte. Celle qui a déjà un compte se met au planning depuis sa ligne de « Hors planning »
	 * ou sa fiche.
	 */
	protected static function getTeamAdd(Structure $eStructure): string {

		$h = '<a class="btn btn-secondary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('person-plus-fill').' '.s("Ajouter une personne").'</a>';

		$h .= '<div class="dropdown-list">';

			$h .= '<a href="/structure/invite:createMember?structure='.$eStructure['id'].'" class="dropdown-item">';
				$h .= '<div class="util-item-label">'.s("Envoyer une invitation par e-mail à rejoindre votre équipe").'</div>';
				$h .= '<div class="util-item-more">'.s("La personne crée son compte sur Ouvretaferme puis rejoint votre équipe.").'</div>';
			$h .= '</a>';

			$h .= '<a href="/hr/teammate:create?structure='.$eStructure['id'].'" class="dropdown-item">';
				$h .= '<div class="util-item-label">'.s("Ajouter une personne sans compte").'</div>';
				$h .= '<div class="util-item-more">'.s("La personne n'a pas de compte sur Ouvretaferme, vous la gérez à sa place sur le planning.").'</div>';
			$h .= '</a>';

		$h .= '</div>';

		return $h;

	}

	public static function getTeamCategories(Structure $eStructure): array {

		$categories = [
			// Un groupe n'a pas d'équipe, il a des membres : c'est le mot de sa rangée d'onglets,
			// de son rail et de ses deux tuiles, et le même écran ne peut pas changer de nom selon
			// l'endroit d'où on l'ouvre.
			Member::TEAM => match($eStructure['type']) {
				Structure::COMMUNITY => s("Membres"),
				Structure::AMAP => s("Administration"),
				Structure::PRODUCER, Structure::SHOP => s("Équipe"),
			},
		];

		if($eStructure->acceptRoles()) {
			$categories[Member::ROLE] = s("Rôles");
		}

		return $categories;

	}

}
?>
