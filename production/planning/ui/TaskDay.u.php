<?php
namespace planning;

/**
 * Le Jour de l'écran « Interventions » (L5c), forme P1 retenue le 27/09 : la colonne « Sans
 * jour », puis une colonne par jour de la semaine, qui défilent de côté, aujourd'hui centré à
 * l'ouverture, comme le planning actuel et la maquette navigable (`jourColonnes()`).
 *
 * Les gestes de la composition J1 de `lots.md` s'y retrouvent ainsi (Q1, tranchée par V-Planning) : la
 * Journée est la colonne du jour, le Reste de la semaine est « Sans jour » ; « Aujourd'hui » et
 * « Retirer de la journée » sont le menu du jour de chaque carte à faire, que le glisser à la souris
 * double ; « J'ai aussi fait » est le « + » d'un jour passé ou d'aujourd'hui, qui ouvre « Nouvelle
 * intervention » avec Fait coché (retour 293).
 *
 * Chaque colonne a son identifiant stable (`#planning-day-<colonne>`) : un geste redessine les colonnes
 * qu'il change, et elles seules (`TaskDayLib::getChange()`).
 */
class TaskDayUi {

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

	}

	public static function url(\structure\Structure $eStructure, ?string $week = NULL): string {

		$url = \structure\StructureUi::urlStructure($eStructure).'/planning-v2/jour';

		if($week !== NULL and $week !== \Clock::week()) {
			$url .= '/'.$week;
		}

		return $url;

	}

	/**
	 * L'en-tête de semaine du planning actuel, comme la Semaine (`TaskWeekUi::getCalendar()`) : changer de
	 * semaine garde le Jour et le masque.
	 */
	public function getCalendar(\structure\Structure $eStructure, string $week): string {

		return new \series\PlanningUi()->getCalendar(
			$eStructure,
			$week,
			fn($week) => self::url($eStructure, $week)
		);

	}

	/**
	 * L'écran entier. Il porte ce que les gestes réémettent (`data-week`) et la semaine suivante du menu du jour
	 * (`data-week-next`), pour que leur réponse dessine les colonnes que l'on regarde.
	 * Les onglets sont au-dessus, hors de l'écran, comme sur la Semaine (`taskDay.v.php`).
	 *
	 * Une semaine sans aucune intervention (`$empty`) ne garde de la rangée des outils que la période, et
	 * « + À faire » et « + Fait » passent dans un état vide au-dessus des colonnes, comme à la Semaine
	 * (R-Retours-0410, point 18). Les colonnes restent : la tête d'un jour passé porte « J'ai aussi fait »,
	 * le seul geste qui y saisit ce qui a été fait.
	 *
	 * `$reminders` porte le pense-bête rangé par colonne (`ReminderLib::getByDay()`), NULL quand le membre l'a
	 * éteint.
	 */
	public function get(\structure\Structure $eStructure, string $week, string $weekNext, array $columns, \structure\Member $eMember, \Collection $cUser, bool $canCreate, array $canCreateByStatus, bool $empty, array $dues, ?array $reminders): string {

		$h = '<div id="planning-day" class="'.TaskWeekUi::getColorClass($eMember).'" data-structure="'.$eStructure['id'].'" data-week="'.$week.'" data-week-next="'.$weekNext.'" data-batch="#planning-batch" onrender="PlanningTaskSelection.apply()">';

			$uiTab = new TabUi();

			$period = $uiTab->getPeriod('day', TaskWeekUi::url($eStructure, $week), self::url($eStructure, $week), TaskYearUi::url($eStructure, $week));

			if($empty) {

				$h .= $uiTab->getTools($period);

				$h .= '<div class="util-block-empty">';
					$h .= '<h3>'.s("Aucune intervention cette semaine").'</h3>';
					if($canCreate) {
						$h .= TaskCreateUi::getEmptyButton($eStructure, 'taskDay', $week, $canCreateByStatus);
					}
				$h .= '</div>';

			} else {

				$h .= $uiTab->getTools(
					$period.
					$uiTab->getSearch(cUser: $cUser),
					mode: $uiTab->getHideSwitch($eMember).
					$uiTab->getActions(new TaskBatchUi()->getSelectButton().TaskCreateUi::getButton($eStructure, 'taskDay', $week, $canCreateByStatus))
				);

				$h .= $uiTab->getSearchEmpty();

			}

			$h .= '<div class="util-overflow-frame planning-day-scroll" onrender="PlanningTaskDay.center(this)">';
				$h .= '<div class="planning-day-columns">';

					foreach($columns as $key => $column) {
						$h .= $this->getColumn($eStructure, $week, $key, $column, $cUser, $canCreate, $empty, $reminders === NULL ? NULL : $reminders[$key]);
					}

				$h .= '</div>';
			$h .= '</div>';

			$h .= new DueUi()->getZones($eStructure, $week, $dues);

			$h .= new TaskBatchUi()->get($eStructure, 'taskDay', $week, ['id' => $eStructure['id'], 'week' => $week], $cUser);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une colonne : sa tête, puis ses cartes rangées par action, sous le nom de l'action comme les blocs
	 * de la Semaine.
	 *
	 * La tête dit ce qui reste à faire en compteur et, sous un jour passé ou aujourd'hui, ce qui y a été
	 * fait et le temps passé ce jour-là par tout le monde. Un jour passé ou aujourd'hui porte
	 * « J'ai aussi fait » ; un jour à venir porte « Nouvelle intervention » sur ce jour (L5d).
	 *
	 * Aujourd'hui se nomme ainsi, et sa tête se teinte comme la colonne du même jour dans Récoltes.
	 *
	 * Les cartes vivent dans un corps distinct de la tête : à partir de la tablette, chaque colonne y défile
	 * seule, sous sa tête (R-Retours-0410, point 16).
	 *
	 * Au téléphone, « Sans jour » se replie en un rail le long des jours, qui porte son compte et s'ouvre
	 * par-dessus eux (maquette navigable, `centrerJourApresAssigner()`) : la flèche le referme.
	 *
	 * Sur un écran sans aucune intervention (`$empty`), le corps se tait : l'état vide de l'écran est le seul
	 * message, et huit états vides de même poids en dessous le noieraient, « Tout est posé sur un jour » le
	 * contredisant même (R-Retours-0410, point 18, décision de V-Planning). La tête reste, avec « J'ai aussi
	 * fait » sur un jour passé. « Sans jour » reste aussi, vide : `PlanningTaskDay.size()` lit sa largeur.
	 *
	 * Le pense-bête du jour ouvre le corps, au-dessus des cartes, y compris sur un écran vide : un rappel n'est
	 * pas une intervention. Son conteneur est rendu même sans rappel, pour qu'une réponse sache où l'écrire
	 * (`doReminder`, `taskDay.v.php`).
	 */
	public function getColumn(\structure\Structure $eStructure, string $week, string $key, array $column, \Collection $cUser, bool $canCreate, bool $empty, ?\Collection $cReminder): string {

		$date = $column['date'];
		$today = \Clock::date();
		$past = ($date !== NULL and $date <= $today);

		$h = '<section class="util-card planning-day-column '.($date === $today ? 'planning-day-today' : '').'" id="planning-day-'.$key.'" data-column="'.$key.'" onrender="'.($date === NULL ? 'PlanningTaskDay.renderNone(this); ' : '').'PlanningTaskSelection.apply()">';

			$h .= '<div class="util-title">';

				$h .= '<div class="planning-day-title">';

					if($date === NULL) {

						$h .= '<h4>'.s("Non défini").' <span class="util-counter">'.$column['todo'].'</span></h4>';
						$h .= '<div class="util-annotation">'.s("Semaine {value}", week_number($week)).'</div>';

					} else {

						$h .= '<h4>';
							$h .= ($date === $today ? s("Aujourd'hui") : \util\DateUi::getDayName((int)date('N', strtotime($date)))).' ';
							$h .= '<span class="color-muted">'.\util\DateUi::numeric($date, \util\DateUi::DAY_MONTH).'</span> ';
							$h .= '<span class="util-counter">'.$column['todo'].'</span>';
						$h .= '</h4>';

						if($past) {
							$h .= '<div class="util-annotation">'.$this->getDone($eStructure, $column).'</div>';
						}

					}

				$h .= '</div>';

				if($date === NULL) {
					$h .= '<a class="btn btn-only-icon btn-sm btn-outline-primary hide-sm-up planning-day-close" data-planning-day-close>'.\Asset::icon('chevron-left').'</a>';
				} else if($past and $canCreate) {
					$h .= '<a href="'.encode(TaskCreateUi::url($eStructure, 'taskDay', $week, date: $date, status: \series\Task::DONE)).'" class="btn btn-sm btn-outline-primary">'.\Asset::icon('plus-lg').'<span class="hide-xs-down planning-day-also-label"> '.s("J'ai aussi fait").'</span></a>';
				} else if($canCreate) {
					$h .= '<a href="'.encode(TaskCreateUi::url($eStructure, 'taskDay', $week, date: $date)).'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('plus-lg').'</a>';
				}

			$h .= '</div>';

			$h .= '<div class="planning-day-body" onrender="PlanningTaskDay.restoreScroll(this)">';

				if($cReminder !== NULL) {
					$h .= $this->getReminders($eStructure, $week, $key, $cReminder);
				}

				if($column['cTask']->notEmpty()) {
					$h .= $this->getTasks($eStructure, $week, $key, $column['cTask'], $cUser, $canCreate and $past === FALSE);
				} else if($empty === FALSE) {

					$h .= '<div class="util-block-empty">';
						$h .= '<h3>'.$this->getEmpty($column, $past).'</h3>';
					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</section>';

		return $h;

	}

	public function getReminders(\structure\Structure $eStructure, string $week, string $key, \Collection $cReminder): string {

		return '<div id="planning-day-reminders-'.$key.'">'.new ReminderUi()->getDay($eStructure, $week, $cReminder).'</div>';

	}

	protected function getDone(\structure\Structure $eStructure, array $column): string {

		if($column['done'] === 0) {
			return s("rien de fait");
		}

		$done = p("{value} faite", "{value} faites", $column['done']);

		if($eStructure->hasTime() and $column['time'] > 0.0) {
			$done .= ' · '.DurationUi::getSpent($column['time']);
		}

		return $done;

	}

	/**
	 * L'état vide d'une colonne dit si le masque l'a vidée ou s'il n'y a rien.
	 */
	protected function getEmpty(array $column, bool $past): string {

		if($column['hidden']) {
			return s("Rien ne reste à montrer");
		}

		if($column['date'] === NULL) {
			return s("Tout est posé sur un jour");
		}

		return $past ? s("Rien ce jour-là") : s("Rien de posé");

	}

	/**
	 * Les cartes d'une colonne, dans l'ordre de la Semaine (`TaskWeekLib::get()`), qui range déjà par
	 * action : un groupe s'ouvre à chaque action nouvelle. Sa tête porte, comme un bloc de la Semaine, la
	 * case qui prend ses cartes à faire et, dans « Sans jour » et les jours à venir, le « + » qui ouvre
	 * « Nouvelle intervention » sur son action et son jour (L5d). Le groupe porte son action, où le glisser
	 * d'une carte cherche sa place (`PlanningTaskDay.placeGhost()`).
	 */
	protected function getTasks(\structure\Structure $eStructure, string $week, string $key, \Collection $cTask, \Collection $cUser, bool $canCreate): string {

		$uiTaskCard = new TaskCardUi();
		$uiTaskBatch = new TaskBatchUi();

		$h = '';
		$action = NULL;

		foreach($cTask as $eTask) {

			if($eTask['action']['id'] !== $action) {

				if($action !== NULL) {
					$h .= '</div>';
				}

				$action = $eTask['action']['id'];

				$h .= '<div class="planning-day-action" data-action="'.$action.'">';
				$h .= '<div class="planning-block-head planning-day-action-head">';
					$h .= '<div class="planning-block-name">';
						$h .= $uiTaskBatch->getGroupCheckbox();
						$h .= TaskWeekUi::getActionCircle($eTask['action']);
						$h .= '<div class="planning-day-action-name">'.encode($eTask['action']['name']).'</div>';
					$h .= '</div>';
					if($canCreate) {
						$h .= '<a href="'.encode(TaskCreateUi::url($eStructure, 'taskDay', $week, eAction: $eTask['action'], date: ($key === TaskDayLib::NONE) ? NULL : $key)).'" class="btn btn-only-icon btn-xs btn-outline-primary">'.\Asset::icon('plus-lg').'</a>';
					}
				$h .= '</div>';

			}

			$h .= $uiTaskCard->get($eStructure, $week, $eTask, $cUser, $key);

		}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les résultats de « Espèce ou série », en deux sections titrées (retour 243) : les séries, sous la rangée
	 * des saisons qui ne règle qu'elles, puis les espèces. Les deux sections sont toujours rendues, pour que la
	 * rangée garde sa place d'une frappe à l'autre : une section vide le dit sur sa propre ligne, une section
	 * coupée finit par « Voir les N autres », qui l'ouvre (retour 382).
	 */
	public static function getAutocompleteResults(\Collection $cCultivation, int $cultivationOthers, \Collection $cPlant, int $plantOthers): array {

		$results = [];

		$results[] = self::getAutocompleteTitle(s("Dans les séries"));
		$results[] = ['type' => 'head'];

		if($cCultivation->empty()) {
			$results[] = self::getAutocompleteNone(s("Aucune série"));
		}

		foreach($cCultivation as $eCultivation) {
			$results[] = self::getAutocomplete($eCultivation);
		}

		if($cultivationOthers > 0) {
			$results[] = self::getAutocompleteMore('cultivation', $cultivationOthers);
		}

		$results[] = self::getAutocompleteTitle(s("Hors série"));

		if($cPlant->empty()) {
			$results[] = self::getAutocompleteNone(s("Aucune espèce"));
		}

		foreach($cPlant as $ePlant) {
			$results[] = self::getAutocompletePlant($ePlant);
		}

		if($plantOthers > 0) {
			$results[] = self::getAutocompleteMore('plant', $plantOthers);
		}

		return $results;

	}

	/**
	 * Le geste qui ouvre une section coupée, au libellé de « Voir les N autres » de la Semaine
	 * (`TaskWeekUi`). Un bouton et non un lien, sur une ligne qui ne se choisit pas : le socle donne à un lien
	 * d'une ligne toute sa largeur (form.css), et une ligne choisissable remplirait le champ. Il relance la
	 * recherche sans coupe pour sa section (`PlanningTaskAction.more()`).
	 */
	private static function getAutocompleteMore(string $section, int $others): array {

		$text = p("Voir l'autre", "Voir les {value} autres", $others);

		return [
			'type' => 'disabled',
			'itemHtml' => '<button type="button" class="btn btn-sm btn-outline-white" data-planning-task-more="'.$section.'">'.$text.'</button>',
			'itemText' => $text
		];

	}

	/**
	 * Même forme que le compte d'une section coupée de la Boîte à outils (`\work\ToolUi::getAutocompleteOthers()`) :
	 * une ligne grise qui ne se choisit pas.
	 */
	private static function getAutocompleteNone(string $text): array {

		return [
			'type' => 'disabled',
			'itemHtml' => '<div>'.$text.'</div>',
			'itemText' => $text
		];

	}

	private static function getAutocompleteTitle(string $title): array {

		return [
			'type' => 'title',
			'itemHtml' => '<div>'.$title.'</div>',
			'itemText' => $title
		];

	}

	/**
	 * Une espèce, pour une intervention hors série. Sa valeur porte sa nature (`plant:<id>`), le champ n'ayant
	 * qu'un nom pour les cultures et les espèces (`TaskCreateLib::readChoice()`).
	 */
	public static function getAutocompletePlant(\plant\Plant $ePlant): array {

		$item = \plant\PlantUi::getVignette($ePlant, '2.5rem');
		$item .= '<div>'.encode($ePlant['name']).'</div>';

		return [
			'value' => 'plant:'.$ePlant['id'],
			'itemHtml' => $item,
			'itemText' => $ePlant['name']
		];

	}

	/**
	 * Un résultat de l'autocomplétion : la vignette, la série, puis l'assolement en retrait sous le nom,
	 * petit et atténué, comme une ligne des Récoltes (retour 93, `HarvestWeekUi::getPlaces()`). Le champ,
	 * lui, ne tient que du texte, la série et son assolement.
	 */
	public static function getAutocomplete(\series\Cultivation $eCultivation): array {

		$name = new HarvestWeekUi()->getName($eCultivation, places: FALSE);
		$places = new TaskCardUi()->getPlaces($eCultivation['series']['use'], $eCultivation['series']['cccPlace']);

		$item = \plant\PlantUi::getVignette($eCultivation['plant'], '2.5rem');
		$item .= '<div>';
			$item .= '<div>'.$name.'</div>';
			if($places !== '') {
				$item .= '<div class="util-annotation">'.$places.'</div>';
			}
		$item .= '</div>';

		$text = $name.($places !== '' ? ' · '.str_replace(\Asset::icon('chevron-right'), '›', $places) : '');

		return [
			'value' => $eCultivation['id'],
			'itemHtml' => $item,
			'itemText' => preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($text)))
		];

	}

}
?>
