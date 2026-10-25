<?php
namespace planning;

/**
 * L'Année de l'écran « Interventions » (R-Annee), piste A de la planche M-Annee retenue : une
 * colonne par semaine, cartes rangées par action sous le nom de l'action, comme une colonne du Jour
 * (`TaskDayUi::getColumn()`), dont elle reprend les classes et les styles. La semaine de l'adresse se pose
 * au bord gauche du cadre à l'ouverture, et la semaine courante se teinte, comme aujourd'hui au Jour.
 *
 * Au-dessus du cadre, les mois en liens : un clic défile jusqu'à la première semaine du mois, et le mois
 * de la semaine en vue se lit en gras (`PlanningTaskYear`).
 *
 * La recherche des Interventions y est celle de la Semaine et du Jour (R-Recherche-2) : les suggestions et
 * les semaines qui portent un résultat viennent du serveur (`taskYear:query`, `taskYear:search`), qui voit
 * les semaines que l'écran n'a pas encore chargées ; les colonnes chargées filtrent leurs cartes sur place.
 *
 * Une carte de l'Année porte ce que porte une ligne du planning annuel actuel (`series\PlanningUi`,
 * `getYearTask()`) et rien de plus, pour qu'une semaine chargée se lise d'un coup d'œil : ni vignette, ni
 * pied, ni note (`getCard()`).
 *
 * Chaque colonne a son identifiant stable (`#planning-year-<semaine>`) : le chargement d'une semaine et la
 * réponse d'un geste remplacent les colonnes qu'ils changent, et elles seules.
 */
class TaskYearUi {

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

	}

	public static function url(\structure\Structure $eStructure, ?string $week = NULL): string {

		$url = \structure\StructureUi::urlStructure($eStructure).'/planning-v2/annee';

		if($week !== NULL and $week !== \Clock::week()) {
			$url .= '/'.$week;
		}

		return $url;

	}

	/**
	 * L'en-tête d'année du planning actuel : l'année précédente s'ouvre sur décembre, la suivante sur janvier,
	 * comme dans le planning actuel. Ses flèches sont ses deux seuls liens, et la semaine
	 * de chacune vient de la page (`TaskYearLib::getCalendar()`).
	 */
	public function getCalendar(\structure\Structure $eStructure, int $year, array $calendar): string {

		return new \series\PlanningUi()->getYearCalendar(
			$eStructure,
			$year,
			link: fn(int $yearLinked) => self::url($eStructure, ($yearLinked < $year) ? $calendar['before'] : $calendar['after'])
		);

	}

	/**
	 * L'écran entier. Il porte ce que les gestes et les chargements réémettent : la semaine de l'adresse
	 * (`data-week`). Pas de sélection dans la rangée
	 * des outils (écart c du plan). « + À faire » et « + Fait » y ouvrent la semaine de l'adresse (piste F de
	 * R-CreationRapide), le « + » de chaque semaine ouvre la sienne à faire.
	 */
	public function get(\structure\Structure $eStructure, string $week, \structure\Member $eMember, array $columns, array $months, bool $canCreate, array $canCreateByStatus): string {

		$h = '<div id="planning-year" class="'.TaskWeekUi::getColorClass($eMember).'" data-structure="'.$eStructure['id'].'" data-week="'.$week.'">';

			$uiTab = new TabUi();

			$h .= $uiTab->getTools(
				$uiTab->getPeriod('year', TaskWeekUi::url($eStructure, $week), TaskDayUi::url($eStructure, $week), self::url($eStructure, $week)).
				$uiTab->getSearch('/planning/taskYear:query', ['id' => $eStructure['id'], 'week' => $week]),
				$uiTab->getHideSwitch($eMember).
				$uiTab->getActions(TaskCreateUi::getButton($eStructure, 'taskYear', $week, $canCreateByStatus, current: $week))
			);

			$h .= $uiTab->getSearchEmpty();

			$h .= $this->getMonths($months, $columns[$week]['month']);

			$h .= '<div class="util-overflow-frame planning-day-scroll" onrender="PlanningTaskYear.start(this)">';
				$h .= '<div class="planning-day-columns">';

					foreach($columns as $weekColumn => $column) {
						$h .= $this->getColumn($eStructure, $weekColumn, $week, $column, $canCreate);
					}

				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les mois de l'année, en liens texte : ils déplacent le défilement et ne filtrent rien, ce que des jetons
	 * auraient laissé croire (passe ui de M-Annee, point 7). Le mois de la semaine ouverte est en gras, puis
	 * celui de la semaine en vue (`PlanningTaskYear.watch()`).
	 *
	 * @param array $months La première semaine de chaque mois, par numéro de mois (`TaskYearLib::getCalendar()`).
	 */
	public function getMonths(array $months, int $month): string {

		$h = '<nav class="planning-year-months">';

			foreach($months as $monthListed => $week) {

				$h .= '<a href="#planning-year-'.$week.'" class="'.($monthListed === $month ? 'is-selected' : '').'" data-planning-year-month="'.$monthListed.'" data-ajax-navigation="never">';
					$h .= '<span class="hide-sm-down">'.ucfirst(\util\DateUi::getMonthName($monthListed)).'</span>';
					$h .= '<span class="hide-md-up">'.ucfirst(\util\DateUi::getMonthName($monthListed, type: 'short')).'</span>';
				$h .= '</a>';

			}

		$h .= '</nav>';

		return $h;

	}

	/**
	 * Une semaine : sa tête, puis, chargée, ses cartes rangées par action. La tête d'une semaine chargée dit ce
	 * qui reste à faire en compteur et ce qui y a été fait ; celle d'une semaine pas encore chargée n'a que son
	 * nom et ses dates (écart b du plan), et prend la même largeur, pour que son chargement ne déplace rien.
	 *
	 * La semaine courante se nomme « Cette semaine », et sa tête se teinte comme aujourd'hui au Jour.
	 *
	 * @param string $current La semaine de l'adresse, que « Nouvelle intervention » réémet.
	 */
	public function getColumn(\structure\Structure $eStructure, string $week, string $current, array $column, bool $canCreate): string {

		$now = ($week === \Clock::week());

		$h = '<section class="util-card planning-day-column '.($now ? 'planning-day-today' : '').'" id="planning-year-'.$week.'" data-column="'.$week.'" data-month="'.$column['month'].'" '.($column['loaded'] ? 'data-loaded="1"' : '').' onrender="PlanningTaskYear.watch(this)">';

			$h .= '<div class="util-title">';

				$h .= $this->getTitle($week, $column);

				if($canCreate) {
					$h .= '<a href="'.encode(TaskCreateUi::url($eStructure, 'taskYear', $week, current: $current)).'" class="btn btn-only-icon btn-sm btn-outline-primary">'.\Asset::icon('plus-lg').'</a>';
				}

			$h .= '</div>';

			if($column['loaded']) {

				if($column['cTask']->empty()) {

					$h .= '<div class="util-block-empty">';
						$h .= '<h3>'.($column['hidden'] ? s("Rien ne reste à montrer") : s("Rien cette semaine")).'</h3>';
					$h .= '</div>';

				} else {
					$h .= $this->getTasks($eStructure, $week, $column['cTask']);
				}

			}

		$h .= '</section>';

		return $h;

	}

	/**
	 * La tête d'une semaine, que la réponse d'un geste remplace seule quand l'intervention garde ses semaines
	 * (`TaskYearLib::getGranularity()`). Le compteur ne paraît que s'il reste à faire, un compteur vide ne
	 * s'affichant pas (design system) ; sur une semaine passée, ce reste est en retard, et le compteur prend
	 * le rouge que le design system réserve à ce qui presse (arbitrage de V-Planning du 2026-10-03 : les
	 * retards restent dans leur semaine prévue, marqués là).
	 */
	public function getTitle(string $week, array $column): string {

		$now = ($week === \Clock::week());

		$h = '<div class="planning-day-title">';

			$h .= '<h4>';
				$h .= $now ? s("Cette semaine") : s("Semaine {value}", week_number($week));
				if($column['loaded'] and $column['todo'] > 0) {
					$h .= ' <span class="util-counter '.($week < \Clock::week() ? 'util-counter-danger' : '').'">'.$column['todo'].'</span>';
				}
			$h .= '</h4>';

			$h .= '<div class="util-annotation">';
				$h .= \util\DateUi::weekToDays($week, short: TRUE);
				if($column['loaded'] and $column['done'] > 0) {
					$h .= ' · '.p("{value} faite", "{value} faites", $column['done']);
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La carte réduite de l'Année, que la réponse d'un geste remplace aussi seule : un titre, le lieu sous le nom
	 * d'une série, puis le cadre d'un semis ou d'une plantation, comme la carte de la Semaine
	 * (`TaskCardUi::getFacts()`). Le titre, sur une ligne, dans cet ordre : la série, les emplacements, l'espèce,
	 * le complément du planning actuel (`TaskUi::getMore()`), le début de la description, l'action. L'action
	 * n'est pas répétée quand il y a mieux : la tête de son groupe la porte déjà.
	 *
	 * Elle garde la marque de la carte de la Semaine (`TaskCardUi::getMark()`),
	 * et le lien vers la page de l'intervention. `$week` est la semaine de la carte, où la coche date
	 * l'intervention (`data-column`, que `PlanningTaskYear.send()` poste).
	 */
	public function getCard(\structure\Structure $eStructure, string $week, \series\Task $eTask): string {

		$eTask->expects(['search']);

		$uiTaskCard = new TaskCardUi();

		$h = '<div class="planning-task '.($eTask->isDone() ? 'planning-task-done' : '').'" id="'.TaskCardUi::getId($eTask, $week).'" data-task="'.$eTask['id'].'" data-column="'.$week.'">';

			// Un droit limité voit la carte sans pouvoir ouvrir la page de l'intervention, que `Task::canRead()` lui refuse.
			if($eTask->canRead()) {
				$h .= '<a href="'.TaskDetailUi::url($eTask, $week).'" class="planning-task-main" '.TaskCardUi::getSearch($eTask).'>';
			} else {
				$h .= '<div class="planning-task-main" '.TaskCardUi::getSearch($eTask).'>';
			}

				if($eTask['series']->notEmpty()) {
					$h .= '<div class="planning-task-name">'.\series\SeriesUi::name($eTask['series']).'</div>';
					$h .= $uiTaskCard->getPlace($eTask, $week);
				} else if($eTask['cccPlace']->notEmpty()) {
					$h .= '<div class="planning-task-name">'.$uiTaskCard->getPlaces(\series\Series::BED, $eTask['cccPlace']).'</div>';
				} else if($eTask['plant']->notEmpty()) {
					$h .= '<div class="planning-task-name">'.encode($eTask['plant']['name']).'</div>';
				} else {

					$more = new \series\TaskUi()->getMore($eTask);
					$description = $eTask->getDescriptionStart();

					if($more !== '') {
						$name = $more;
					} else if($description !== '') {
						$name = encode($description);
					} else {
						$name = encode($eTask['action']['name']);
					}

					$h .= '<div class="planning-task-name">'.$name.'</div>';

				}

				$h .= TaskCardUi::getFacts($eTask);

			$h .= $eTask->canRead() ? '</a>' : '</div>';

			$h .= '<div class="planning-task-validate">';
				$h .= $uiTaskCard->getMark($eTask);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les cartes d'une semaine, dans l'ordre de la Semaine (`TaskWeekLib::get()`), qui range déjà par action :
	 * un groupe s'ouvre à chaque action nouvelle, sous son nom, comme au Jour.
	 */
	protected function getTasks(\structure\Structure $eStructure, string $week, \Collection $cTask): string {

		$h = '';
		$action = NULL;

		foreach($cTask as $eTask) {

			if($eTask['action']['id'] !== $action) {

				if($action !== NULL) {
					$h .= '</div>';
				}

				$action = $eTask['action']['id'];

				$h .= '<div class="planning-day-action">';
				$h .= '<div class="planning-block-head planning-day-action-head">';
					$h .= '<div class="planning-block-name">';
						$h .= TaskWeekUi::getActionCircle($eTask['action']);
						$h .= '<div class="planning-day-action-name">'.encode($eTask['action']['name']).'</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

			$h .= $this->getCard($eStructure, $week, $eTask);

		}

		$h .= '</div>';

		return $h;

	}

}
?>
