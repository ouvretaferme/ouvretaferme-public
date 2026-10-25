<?php
namespace series;

/**
 * Le bloc des interventions de la semaine, son panneau « Choisir », et les chiffres de la semaine passée.
 *
 * Les rangs sont ceux du système de design (`util-rows`), le panneau celui de « Personnaliser le planning »
 * (`util-deck`, poignée, pastille d'action), avec un interrupteur par rang : rien de neuf n'est dessiné,
 * seul l'alignement de la pastille et de son nom est propre au bloc.
 */
class DashboardUi {

	public function __construct() {
		\Asset::css('series', 'dashboard.css');
		\Asset::lib('util', 'Sortable-1.11.0.js');
		\Asset::js('series', 'dashboard.js');
	}

	public function getTitle(string $name): string {

		return match($name) {
			'series-week' => s("Interventions de la semaine")
		};

	}

	/**
	 * Les mots du bouton que le socle pose en pied de carte, là où l'adresse vient de
	 * `DashboardLib::getUrl()` : un `.l.php` n'a pas le droit d'appeler `s()`, et c'est ce qui range
	 * le libellé ici et l'adresse là-bas. La flèche reste au socle, le bloc ne rend que les mots.
	 */
	public function getUrlLabel(string $name): string {

		return match($name) {
			'series-week' => s("Voir le planning")
		};

	}

	public function render(string $name, \structure\Structure $eStructure, array $values): string {

		return match($name) {
			'series-week' => $this->getWeek($eStructure, $values)
		};

	}

	/**
	 * Ce qui reste à faire cette semaine en grand, puis une ligne par action suivie, dans l'ordre du
	 * membre : sa pastille, son nom, les espèces qu'elle touche cette semaine, et à droite ce qui en est
	 * fait sur ce qui est prévu. La ligne se déplie sur ses interventions, chacune avec la marque « Fait »
	 * du planning, qui la coche sans quitter l'accueil. Les actions sans rien cette semaine se nomment
	 * ensemble sur une dernière ligne : les taire laisserait croire que le choix s'est perdu, et une ligne
	 * chacune noyait les quelques actions de la semaine (#83, six vides sur sept).
	 *
	 * `<details>` et non un bouton, comme le pli du socle (`DashboardUi::getFold()`) : le pli tient sans
	 * une ligne de JS. Le pli n'est pas celui du socle, dont la tête est un intitulé sous lequel la liste
	 * se déplie : ici la tête est le rang de l'action dans `util-rows`, avec son compte, et la réponse
	 * d'une coche la remplace par son id. Cette réponse ne remplace que la ligne de l'intervention et la
	 * tête de son action (`series/dashboard:doDone`), jamais le pli entier, qui se refermerait.
	 */
	private function getWeek(\structure\Structure $eStructure, array $values): string {

		if($values['actions'] === []) {
			return '<p class="util-empty">'.s("Aucune intervention suivie.").'</p>';
		}

		\Asset::css('planning', 'planning.css');

		$h = $this->getWeekValue($values['todo'], $values['late']);

		$h .= '<div id="'.self::getWeekId().'" class="util-rows series-dashboard-week" data-structure="'.$eStructure['id'].'">';

			foreach($values['actions'] as $action) {

				if($action['late'] > 0) {
					$h .= $this->getWeekLate($action, $values['lateUrl']);
				}

			}

			foreach($values['actions'] as $action) {

				if($action['done'] + $action['todo'] > 0) {
					$h .= $this->getWeekAction($eStructure, $values['week'], $action);
				}

			}

			$h .= $this->getWeekIdle($values['actions']);

		$h .= '</div>';

		return $h;

	}

	public static function getWeekId(): string {
		return 'series-dashboard-week';
	}

	public static function getWeekValueId(): string {
		return 'series-dashboard-week-value';
	}

	public static function getWeekIdleId(): string {
		return 'series-dashboard-week-idle';
	}

	public static function getWeekActionId(int $action): string {
		return 'series-dashboard-action-'.$action;
	}

	public static function getWeekSummaryId(int $action): string {
		return 'series-dashboard-summary-'.$action;
	}

	public static function getWeekTaskId(int $task): string {
		return 'series-dashboard-task-'.$task;
	}

	/**
	 * Le chiffre du bloc est ce qui presse le plus : les retards quand il y en a, la semaine sous eux, et la
	 * semaine seule sinon (L67, arbitré par V-Dashboard le 2026-10-19).
	 */
	public function getWeekValue(int $todo, int $late): string {

		$week = p("{value} intervention à faire cette semaine", "{value} interventions à faire cette semaine", $todo, ['value' => \util\TextUi::number($todo)]);

		$h = '<div id="'.self::getWeekValueId().'">';

			if($late > 0) {
				$h .= \dashboard\DashboardUi::getValue(\util\TextUi::number($late), p("intervention en retard", "interventions en retard", $late));
				$h .= '<p class="util-annotation">'.$week.'</p>';
			} else {
				$h .= \dashboard\DashboardUi::getValue(\util\TextUi::number($todo), p("intervention à faire cette semaine", "interventions à faire cette semaine", $todo));
			}

		$h .= '</div>';

		return $h;

	}

	private function getWeekAction(\structure\Structure $eStructure, string $week, array $action): string {

		$h = '<details id="'.self::getWeekActionId($action['id']).'" class="series-dashboard-fold">';

			$h .= $this->getWeekSummary($action);

			$h .= '<div class="series-dashboard-tasks">';

				foreach($action['tasks'] as $task) {
					$h .= $this->getWeekTask($eStructure, $week, $task);
				}

			$h .= '</div>';

		$h .= '</details>';

		return $h;

	}

	/**
	 * Une action en retard, en tête du bloc : ce qui n'a pas été fait les semaines passées ne se lit
	 * qu'au planning, et la semaine courante semblait vide quand le retard s'accumulait (L67). La ligne
	 * mène à la semaine du planning, dont « Retardé » les montre une à une.
	 */
	private function getWeekLate(array $action, string $url): string {

		$h = '<a href="'.$url.'" class="util-row">';

			$h .= '<div class="series-dashboard-action">';
				$h .= \work\ActionUi::getCircle(new \work\Action(['color' => $action['color']]));
				$h .= '<div>'.encode($action['name']).'</div>';
			$h .= '</div>';

			$h .= '<div class="series-dashboard-count color-danger"><span>'.s("{value} en retard", '<b>'.$action['late'].'</b>').'</span></div>';

		$h .= '</a>';

		return $h;

	}

	/** La tête du pli : la ligne de l'action telle qu'elle se lit repliée, et le chevron qui la déplie. */
	public function getWeekSummary(array $action): string {

		$h = '<summary id="'.self::getWeekSummaryId($action['id']).'" class="util-row">';

			$h .= '<div class="series-dashboard-action">';
				$h .= \work\ActionUi::getCircle(new \work\Action(['color' => $action['color']]));
				$h .= '<div>'.encode($action['name']).'</div>';
				if($action['plants'] !== []) {
					$h .= '<div class="util-annotation">'.$this->getPlants($action).'</div>';
				}
			$h .= '</div>';

			$h .= '<div class="series-dashboard-count">';
				$h .= $this->getCount($action['done'], $action['todo']);
				$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
			$h .= '</div>';

		$h .= '</summary>';

		return $h;

	}

	/**
	 * Une intervention, nommée comme sur la grille de la Semaine (`TaskWeekGridUi::getName()`), et la marque
	 * de sa carte (`TaskCardUi::getMark()`), qui ne se clique qu'avec le droit d'écrire au planning : le
	 * planning limité, qui voit le bloc, la lit éteinte. Le nom ouvre la page de l'intervention par le lien
	 * de la carte (`TaskCardUi`), sous le même droit `Task::canRead()`.
	 */
	public function getWeekTask(\structure\Structure $eStructure, string $week, array $task): string {

		if($task['series'] !== NULL) {
			$name = SeriesUi::name(new Series($task['series']));
		} else if($task['plant'] !== NULL) {
			$name = encode($task['plant']);
		} else if($task['description'] !== NULL) {
			$name = encode($task['description']);
		} else {
			$name = '<span class="color-muted">'.s("Sans description").'</span>';
		}

		$eTask = new Task([
			'id' => $task['id'],
			'status' => $task['status'],
			'structure' => $eStructure
		]);

		$h = '<div id="'.self::getWeekTaskId($task['id']).'" class="series-dashboard-task">';
			if($eTask->canRead()) {
				$h .= '<a href="'.\planning\TaskDetailUi::url($eTask, $week).'" class="series-dashboard-task-name">'.$name.'</a>';
			} else {
				$h .= '<div class="series-dashboard-task-name">'.$name.'</div>';
			}
			$h .= new \planning\TaskCardUi()->getMark($eTask);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les actions suivies qui n'ont rien cette semaine, nommées ensemble, ou rien quand toutes ont quelque chose.
	 * Une action en retard n'y est pas : sa ligne de retard la nomme déjà en tête du bloc.
	 */
	public function getWeekIdle(array $actions): string {

		$idle = [];

		foreach($actions as $action) {
			if($action['done'] + $action['todo'] + $action['late'] === 0) {
				$idle[] = encode($action['name']);
			}
		}

		if($idle === []) {
			return '';
		}

		$names = (count($idle) === 1) ?
			$idle[0] :
			s("{list} et {last}", ['list' => implode(', ', array_slice($idle, 0, -1)), 'last' => end($idle)]);

		return '<div id="'.self::getWeekIdleId().'" class="util-row color-muted">'.p("{value} n'a rien cette semaine.", "{value} n'ont rien cette semaine.", count($idle), ['value' => $names]).'</div>';

	}

	/**
	 * Les espèces, nommées seules : ce qui en est fait se lit au compte à droite et, intervention par
	 * intervention, dans le pli.
	 */
	private function getPlants(array $action): string {

		$plants = array_map(fn(string $plant) => encode($plant), $action['plants']);

		if($action['plantsHidden'] > 0) {
			$plants[] = p("{value} autre", "{value} autres", $action['plantsHidden']);
		}

		return implode(', ', $plants);

	}

	private function getCount(int $done, int $todo): string {

		$total = $done + $todo;

		if($done === 0) {
			return s("{value} à faire", '<b>'.$todo.'</b>');
		}

		if($todo === 0) {
			return p("{value} faite", "{value} faites", $done, ['value' => '<b>'.$done.'</b>']);
		}

		return p("{value} faite sur {total}", "{value} faites sur {total}", $done, ['value' => '<b>'.$done.'</b>', 'total' => $total]);

	}

	/**
	 * Le contenu du panneau « Choisir » : toutes les actions que le bloc peut suivre, une ligne chacune
	 * avec sa poignée, sa pastille, son nom et son interrupteur, les suivies d'abord dans l'ordre du
	 * bloc. L'ordre des lignes allumées est l'ordre du bloc : glisser une ligne ou basculer un
	 * interrupteur enregistre toute la liste (`SeriesDashboard.save()`).
	 */
	public function getChoice(string $name, \structure\Structure $eStructure, array $choice): string {

		$h = '<div class="util-deck util-deck-open series-dashboard-choice">';
			$h .= '<div class="util-deck-fold" onrender="SeriesDashboard.initSort(this)" data-save-url="/series/dashboard:doUpdateActions" post-id="'.$choice['member'].'">';

				foreach($choice['actions'] as $action) {

					$h .= '<div class="util-deck-row" data-action="'.$action['id'].'">';
						$h .= \main\DesignUi::getDragHandle();
						$h .= \work\ActionUi::getCircle(new \work\Action(['color' => $action['color']]));
						$h .= '<span class="util-deck-row-name">'.encode($action['name']).'</span>';
						$h .= '<div class="util-deck-aside">'.\util\TextUi::switch(['onclick' => 'SeriesDashboard.toggle(this)'], on: $action['followed']).'</div>';
					$h .= '</div>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

}
?>
