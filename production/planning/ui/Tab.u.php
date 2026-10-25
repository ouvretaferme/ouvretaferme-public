<?php
namespace planning;

/**
 * Les onglets du nouveau planning, communs à ses écrans.
 *
 * Un onglet ne s'affiche que si son écran est codé, c'est-à-dire présent dans
 * `PlanningSetting::PAGES` : un onglet qui mènerait à un écran absent promettrait un geste que le
 * produit ne tient pas. Arbitrage du chantier, réversible, et que `ScreenSentinelTest` tient : chaque
 * clé de `PAGES` a son écran. Chaque écran à venir ajoute sa clé à `PAGES` et sa branche aux
 * `match` ci-dessous. L'onglet `semaine` s'intitule « Interventions »,
 * comme dans la maquette : la semaine, le jour et l'année sont ses trois périodes (`getPeriod()`).
 */
class TabUi {

	/**
	 * Les écrans qui portent les onglets : ceux de `PlanningSetting::PAGES`, plus le Jour et l'Année des
	 * Interventions et la fiche de l'équipe. La personnalisation reçoit l'un d'eux en `origin`, liste fermée
	 * qui ne laisse sa flèche de retour mener qu'à un écran du planning.
	 */
	const array SCREENS = ['semaine', 'jour', 'annee', 'recoltes', 'temps', 'equipe', 'absences'];

	/**
	 * Les onglets, seuls en tête de chaque écran : rien ne se pose avant eux, pour qu'ils restent à la même
	 * place d'un écran à l'autre (piste A de la planche planning-bascule, arbitrée). Ce qui est
	 * propre à un écran vient dessous, dans sa propre rangée.
	 *
	 * L'onglet Temps de travail s'affiche aussi à une ferme qui ne suit pas le temps, réduit aux jours et aux
	 * absences (décidé le 17/10). L'onglet Absences s'affiche à toute personne au planning, RH
	 * allumée (`\hr\Absence::assertPlanning()`, décidé le 18/10).
	 *
	 * @param string $screen L'écran affiché, une valeur de `SCREENS`, d'où l'onglet choisi se déduit.
	 * @param \structure\Member $eMember Le membre connecté, vide hors de la structure : lui seul personnalise.
	 * @param string $panel Les sous-onglets de l'onglet choisi (`tabs-sub`), posés dans son panneau comme le
	 * veut le design system : sans la rangée au-dessus, rien ne dit de quoi ils sont la partie (retour 177).
	 */
	public function get(\structure\Structure $eStructure, string $week, string $screen, \structure\Member $eMember, string $panel = ''): string {

		$selected = self::getTab($screen);

		$tabs = '';

		foreach(PlanningSetting::PAGES as $page) {

			if($page === 'absences' and new \hr\Absence(['structure' => $eStructure])->assertPlanning() === FALSE) {
				continue;
			}

			$tabs .= '<a href="'.self::getUrl($eStructure, $page, $week).'" class="tab-item '.($page === $selected ? 'selected' : '').'">';
				$tabs .= $this->getName($page);
			$tabs .= '</a>';

		}

		$tabs .= $this->getMenu($eStructure, $week, $screen, $eMember);

		return \main\DesignUi::getPageTabs(
			$tabs,
			panels: ($panel === '') ? '' : '<div class="tab-panel selected">'.$panel.'</div>'
		);

	}

	/**
	 * La roue à la suite des onglets, un onglet comme les autres (retour 16 du 03/10), dans leur continuité. Elle
	 * mène à la personnalisation (retour 359), seule entrée du menu qu'elle remplace ; la
	 * flèche de retour ramène à l'écran affiché (`origin`). Elle n'existe pas hors de la structure, où personne ne
	 * personnalise.
	 */
	public function getMenu(\structure\Structure $eStructure, string $week, string $screen, \structure\Member $eMember): string {

		if($eMember->empty()) {
			return '';
		}

		return '<a href="'.TaskWeekUi::urlCustomize($eStructure, $week, $screen).'" class="tab-item planning-views-menu">'.\Asset::icon('gear-fill').'</a>';

	}

	/**
	 * La rangée propre à un écran, sous les onglets : ce qui s'y pose à gauche (`$start`), puis à droite
	 * (`$end`), qui passe au rang suivant, toujours à droite, quand la place manque. La Semaine, le Jour et
	 * l'Année font exception (R-Retours-1210 V61) : `$start` y tient seul sa ligne, et `$end` passe toujours au
	 * rang suivant, sur toute sa largeur.
	 *
	 * `$mode` est la ligne qui reste sous les yeux en mode sélection, celle qui porte « Terminé » : elle se
	 * pose avant `$end`. Sous 576 px, elle seule colle en haut de l'écran, le reste de la rangée
	 * défile ; c'est pourquoi `$start` et elle reçoivent chacun leur enveloppe (`planning.css`, « la
	 * sélection »). Un écran sans mode sélection laisse `$mode` vide et sa rangée reste sans enveloppe.
	 *
	 * `$id` nomme la rangée qu'un geste redessine (`TimeDayUi::getTools()`).
	 */
	public function getTools(string $start, string $end = '', string $mode = '', ?string $id = NULL): string {

		$h = '<div'.($id === NULL ? '' : ' id="'.$id.'"').' class="planning-tools">';

			if($mode === '') {
				$h .= $start;
			} else {
				$h .= '<div class="planning-tools-start">'.$start.'</div>';
				$end = '<div class="planning-tools-mode">'.$mode.'</div>'.$end;
			}

			if($end !== '') {
				$h .= '<div class="planning-tools-end">'.$end.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La bascule entre la semaine et le jour des Interventions, à gauche sous les onglets (écart d de L3a,
	 * tranché par V-Planning le 2026-09-30). Le jour est une période de l'onglet, pas un onglet :
	 * `PlanningSetting::PAGES` n'en sait rien. Le jour montre la semaine entière, rangée en colonnes de
	 * jours (L5c), et l'année (`$urlYear`, R-Annee) la range par semaine.
	 *
	 * La Semaine affichée porte ses affichages, cartes ou grille (`$weekViews`, vue => [libellé, adresse,
	 * choisie], DE), et dit l'affichage montré dans une pastille de compteur
	 * (retour 342), par son picto (DM), sur le jeton comme sur le bouton de la période sous 576 (FL, écart à
	 * DE). Le jeton ouvre leur menu, et le menu de la période les porte sous les périodes, pour qu'on choisisse
	 * aussi sous 576.
	 * Chaque entrée des menus porte le même picto (`TaskWeekUi::getViewIcon()`), suivi du mot. La liste suit
	 * le jeton dans le groupe, `Lime.Dropdown` ne la cherchant que dans l'élément qui suit son déclencheur.
	 * Depuis le Jour ou l'Année, la Semaine est un lien, qui l'ouvre dans l'affichage retenu sur le membre.
	 */
	public function getPeriod(string $selected, string $urlWeek, string $urlDay, ?string $urlYear = NULL, array $weekViews = []): string {

		$periods = [
			'day' => [s("Jour"), $urlDay],
			'week' => [s("Semaine"), $urlWeek],
		];

		if($urlYear !== NULL) {
			$periods['year'] = [s("Année"), $urlYear];
		}

		$viewMenu = ($selected === 'week' and $weekViews !== []);
		$viewCounter = $viewMenu ? ' <span class="util-counter">'.TaskWeekUi::getViewIcon(array_find_key($weekViews, fn($weekView) => $weekView[2])).'</span>' : '';

		$h = '<div class="planning-period">';

			$h .= '<div class="util-filter-options hide-xs-down">';
				foreach($periods as $period => [$name, $url]) {

					if($period === 'week' and $viewMenu) {

						$h .= '<a data-dropdown="bottom-start" class="util-filter-option is-selected dropdown-toggle">'.$name.$viewCounter.'</a>';
						$h .= '<div class="dropdown-list planning-period-view-list">';
							$h .= '<div class="dropdown-subtitle">'.s("Mode d'affichage").'</div>';
							foreach($weekViews as $view => [$label, $urlView, $chosen]) {
								$h .= '<a href="'.$urlView.'" class="dropdown-item '.($chosen ? 'selected' : '').'">'.TaskWeekUi::getViewIcon($view).'&nbsp;&nbsp;'.$label.'</a>';
							}
						$h .= '</div>';

					} else {
						$h .= '<a href="'.$url.'" class="util-filter-option '.($period === $selected ? 'is-selected' : '').'">'.$name.($period === $selected ? $viewCounter : '').'</a>';
					}

				}
			$h .= '</div>';

			$h .= '<a data-dropdown="bottom-start" class="btn btn-outline-primary dropdown-toggle hide-sm-up">'.$periods[$selected][0].$viewCounter.'</a>';

			$h .= '<div class="dropdown-list planning-period-list">';
				foreach($periods as $period => [$name, $url]) {
					$h .= '<a href="'.$url.'" class="dropdown-item '.($period === $selected ? 'selected' : '').'">'.$name.'</a>';
				}
				if($viewMenu) {
					$h .= '<div class="dropdown-divider"></div>';
					$h .= '<div class="dropdown-subtitle">'.s("Mode d'affichage").'</div>';
					foreach($weekViews as $view => [$label, $urlView, $chosen]) {
						$h .= '<a href="'.$urlView.'" class="dropdown-item '.($chosen ? 'selected' : '').'">'.TaskWeekUi::getViewIcon($view).'&nbsp;&nbsp;'.$label.'</a>';
					}
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La recherche des Interventions (R-Recherche), dans la rangée de la Semaine, du Jour et de l'Année, après
	 * la période : l'omnibox du design system, un jeton par groupe, qui masque les cartes que ses jetons
	 * écartent (`PlanningTaskSearch`, `planning.js`). La Semaine et le Jour portent toutes leurs cartes, et
	 * leurs suggestions se relèvent sur elles ; l'Année n'en charge qu'une partie, et les demande au serveur
	 * (`$suggestionsUrl`, rappelée avec `$suggestionsBody`, R-Recherche-2). Les groupes, le décompte et
	 * l'avatar des membres suggérés (`getSearchVignettes()`) sont rendus ici, le JS les lit sur l'enveloppe ;
	 * la vignette d'une espèce suit sa carte (`TaskCardUi::getSearchPlant()`).
	 */
	public function getSearch(?string $suggestionsUrl = NULL, array $suggestionsBody = [], \Collection $cUser = new \Collection()): string {

		$h = '<div class="planning-search" data-groups="'.encode(json_encode($this->getSearchGroups(), JSON_UNESCAPED_UNICODE)).'" data-vignettes="'.encode(json_encode($this->getSearchVignettes($cUser, new \Collection()), JSON_UNESCAPED_UNICODE)).'" data-count-one="'.encode(s("{value} intervention", '{value}')).'" data-count-many="'.encode(s("{value} interventions", '{value}')).'" onrender="PlanningTaskSearch.start(this)">';
			$h .= \main\OmniboxUi::get(
				suggestionsUrl: $suggestionsUrl,
				suggestionsBody: $suggestionsBody,
				placeholder: s("Catégorie, personne, intervention, espèce, emplacement"),
				id: 'planning-search',
				singleByGroup: TRUE
			);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le libellé de chaque groupe de la recherche (`TaskSearchLib::GROUPS`), que portent les jetons, dans
	 * l'ordre où la liste les montre : la catégorie d'abord, qui remplace les onglets de catégorie (V58), puis
	 * les personnes (R-Retours-0810, 232), que le texte d'aide nomme dans le même ordre.
	 */
	public function getSearchGroups(): array {

		return [
			'category' => s("Catégorie"),
			'user' => s("Personne"),
			'action' => s("Intervention"),
			'plant' => s("Espèce"),
			'place' => s("Emplacement"),
		];

	}

	/**
	 * Les suggestions de l'Année, au format de l'omnibox, à partir des valeurs de chaque groupe
	 * (`TaskSearchLib::getSuggestions()`), chacune avec sa vignette.
	 */
	public function getSearchSuggestions(array $values, \Collection $cUser, \Collection $cPlant): array {

		$groups = $this->getSearchGroups();
		$vignettes = $this->getSearchVignettes($cUser, $cPlant);
		$suggestions = [];

		foreach($values as $group => $valuesGroup) {

			foreach($valuesGroup as $value) {
				$suggestions[] = ['group' => $groups[$group], 'label' => $value] + ($vignettes[$group][$value] ?? []);
			}

		}

		return $suggestions;

	}

	/**
	 * La vignette de chaque personne et de chaque espèce, par groupe puis par libellé, celui que la
	 * recherche lit sur les cartes (`TaskSearchLib::getValues()`) : l'avatar de la personne, et l'espèce
	 * comme sur sa carte (`TaskCardUi::getSearchVignette()`).
	 */
	public function getSearchVignettes(\Collection $cUser, \Collection $cPlant): array {

		$vignettes = [
			'user' => [],
			'plant' => [],
		];

		foreach($cUser as $eUser) {
			$vignettes['user'][$eUser->getName()] = ['vignette' => \user\UserUi::getVignette($eUser, '1.25rem')];
		}

		foreach($cPlant as $ePlant) {
			$vignettes['plant'][$ePlant['name']] = TaskCardUi::getSearchVignette($ePlant);
		}

		return $vignettes;

	}

	/**
	 * Ce que dit l'écran quand la recherche écarte toutes ses cartes, sous la rangée.
	 */
	public function getSearchEmpty(): string {

		$h = '<div class="util-block-empty planning-search-empty hide">';
			$h .= '<h3>'.s("Aucune intervention ne correspond à cette recherche").'</h3>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * « Sélectionner » à gauche avec « Masquer ce qui est fait », « + À faire » et « + Fait » au bout à droite
	 * (R-Retours-1210 V61), sur la même ligne à partir de 360 (R-Retours-0610, point 10) : sous 576,
	 * « Sélectionner » n'y garde que son icône (`TaskBatchUi::getSelectButton()`). La Grille n'a pas
	 * « Sélectionner », et les Colonnes n'ont pas d'actions : chaque colonne porte son « + À faire » ou
	 * son « + Fait » (`TaskWeekUi::getColumnCreate()`).
	 */
	public function getActions(string $actions): string {

		return '<div class="planning-tools-actions">'.$actions.'</div>';

	}

	/**
	 * « Masquer ce qui est fait », dans la rangée de l'écran, dans la Semaine, le Jour et la Fiche
	 * de temps. La position est une préférence du membre (retour 108), la même pour les trois écrans :
	 * le geste l'écrit et l'écran se recharge dessus. Sans adhésion à la structure, il n'y a rien où l'écrire.
	 * Sous 450, le libellé court tient sur une ligne avec les boutons de la rangée (retours sur la piste F).
	 */
	public function getHideSwitch(\structure\Member $eMember): string {

		if($eMember->empty()) {
			return '';
		}

		$label = '<span class="hide-xxs-down">'.s("Masquer ce qui est fait").'</span>';
		$label .= '<span class="hide-xs-up">'.s("Masquer fait").'</span>';

		return $this->getSwitch($eMember, '/structure/member:doUpdatePlanningHideDone', 'view-planning-hide-done', $eMember['viewPlanningHideDone'], 'planning-hide', 'planning-hide-label', $label);

	}

	/**
	 * « Saisie rapide », à gauche de la rangée des Récoltes (R-Retours-1210 V35) : allumée, chaque case vide
	 * du jour ouvert porte son champ (`HarvestWeekUi::getCell()`). Une préférence du membre, comme le masque.
	 */
	public function getHarvestQuickSwitch(\structure\Member $eMember): string {

		if($eMember->empty()) {
			return '';
		}

		return $this->getSwitch($eMember, '/structure/member:doUpdatePlanningHarvestQuick', 'view-planning-harvest-quick', $eMember['viewPlanningHarvestQuick'], 'planning-harvest-quick', 'planning-harvest-quick-label', s("Saisie rapide"));

	}

	/**
	 * « Ventes et stock », à côté de « Saisie rapide » (V47) : allumé, la semaine des Récoltes porte les
	 * ventes à venir et le stock de chaque espèce (`HarvestWeekUi::getSalesCells()`). Une préférence du
	 * membre, que la page ne propose qu'à qui la structure ouvre la vente.
	 */
	public function getHarvestSalesSwitch(\structure\Member $eMember): string {

		if($eMember->empty()) {
			return '';
		}

		return $this->getSwitch($eMember, '/structure/member:doUpdatePlanningSales', 'view-planning-sales', $eMember['viewPlanningSales'], 'planning-harvest-sales-switch', 'planning-harvest-sales-switch-label', s("Ventes et stock"));

	}

	/**
	 * « Uniquement assigné à moi », à côté de « Masquer ce qui est fait », sur la Fiche de temps seule
	 * (retour 251) : la fiche liste toutes les interventions de la semaine, l'interrupteur ne garde que
	 * celles qui reviennent à la personne de la fiche (`TimeDayLib::isAssigned()`). Une préférence du membre
	 * connecté, comme le masque, mais le filtre suit la fiche : sur celle d'un autre, le libellé nomme la
	 * personne qu'il garde.
	 */
	public function getMineSwitch(\structure\Member $eMember, \user\User $eUser): string {

		if($eMember->empty()) {
			return '';
		}

		if($eUser->isOnline()) {
			$label = s("Uniquement assigné à moi");
		} else {
			$label = s("Uniquement assigné à {firstName}", ['firstName' => $eUser->getFirstName()]);
		}

		return $this->getSwitch($eMember, '/structure/member:doUpdatePlanningMine', 'view-planning-mine', $eMember['viewPlanningMine'], 'planning-mine', 'planning-mine-label', $label);

	}

	/**
	 * Un interrupteur de préférence du membre, suivi de son libellé. L'interrupteur est lui-même un lien, et
	 * son libellé un second qui envoie la même chose : enveloppé dans un lien, l'analyseur HTML fermerait le
	 * premier avant d'ouvrir le suivant.
	 */
	private function getSwitch(\structure\Member $eMember, string $url, string $post, bool $on, string $class, string $classLabel, string $label): string {

		$attributes = [
			'data-ajax' => $url,
			'post-id' => $eMember['id'],
			'post-'.$post => $on ? 0 : 1,
		];

		$h = '<div class="'.$class.'">';
			$h .= \util\TextUi::switch($attributes, on: $on);
			$h .= '<a '.attrs($attributes).' class="'.$classLabel.'">'.$label.'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'entrée du nouveau planning, celle du menu : le premier écran de `PlanningSetting::PAGES`, à la
	 * semaine courante. Le menu ne nomme aucun écran, pour qu'un lot qui change l'écran d'ouverture
	 * n'ait qu'à réordonner `PAGES`.
	 */
	public static function url(\structure\Structure $eStructure): string {

		return self::getUrl($eStructure, PlanningSetting::PAGES[0]);

	}

	/**
	 * L'adresse d'un écran de `SCREENS` à une semaine : celle de son onglet, et celle où ramène la flèche de
	 * retour de la personnalisation ouverte depuis lui.
	 */
	public static function getUrl(\structure\Structure $eStructure, string $screen, ?string $week = NULL): string {

		return match($screen) {
			'semaine' => TaskWeekUi::url($eStructure, $week),
			'jour' => TaskDayUi::url($eStructure, $week),
			'annee' => TaskYearUi::url($eStructure, $week),
			'recoltes' => HarvestWeekUi::url($eStructure, $week),
			'temps' => TimeDayUi::url($eStructure, $week),
			'equipe' => TimeDayUi::url($eStructure, $week, team: TRUE),
			'absences' => AbsenceUi::url($eStructure, $week)
		};

	}

	protected static function getTab(string $screen): string {

		return match($screen) {
			'semaine', 'jour', 'annee' => 'semaine',
			'temps', 'equipe' => 'temps',
			'recoltes', 'absences' => $screen
		};

	}

	protected function getName(string $page): string {

		return match($page) {
			'semaine' => s("Interventions"),
			'recoltes' => s("Récoltes"),
			'temps' => s("Temps de travail"),
			'absences' => s("Absences")
		};

	}

}
?>
