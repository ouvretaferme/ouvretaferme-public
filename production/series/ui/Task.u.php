<?php
namespace series;

class TaskUi {

	private static $export = FALSE;

	public function __construct() {

		\Asset::css('sequence', 'flow.css');

		\Asset::css('series', 'task.css');
		\Asset::js('series', 'task.js');

	}

	public static function setExport(bool $export): void {
		self::$export = $export;
	}

	public static function url(Task $eTask): string {
		return '/tache/'.$eTask['id'];
	}

	public static function getPanelHeader(Task $eTask): string {

		$eTask->expects([
			'action' => ['name']
		]);

		return '<div class="panel-header-subtitle">'.encode($eTask['action']['name']).'</div>';

	}

	public function getBatch(\structure\Structure $eStructure, ?string $week = NULL, \Collection $cUser = new \Collection()): string {

		$menu = '';

		if($eStructure->canPlanning()) {
			$menu .= '<a data-ajax-submit="/series/task:updateHarvestCollection" data-ajax-method="get" class="batch-one-item batch-harvest" title="'.s("Compléter la récolte").'">'.\Asset::icon('basket2').'<span>'.s("Récolte").'</span></a>';
		}
		if($eStructure->canPlanning()) {
			$menu .= '<a data-ajax-submit="/series/task:doUpdateTodoCollection" class="batch-one-item batch-todo" data-confirm="'.s("Annuler la réalisation de cette intervention ?").'">'.\Asset::icon('arrow-up-left').'<span>'.s("À faire").'</span></a>';
		}
		if($eStructure->canPlanning()) {

			$menu .= '<div class="batch-planned">';
				$menu .= '<a data-dropdown="top-start" class="batch-one-item">';
					$menu .= \Asset::icon('watch');
					$menu .= '<span>'.s("Planifier").'</span>';
				$menu .= '</a>';
				$menu .= $this->getBatchPlanned('batch-task-one-form', $week);
			$menu .= '</div>';

			if($cUser->count() > 1) {
				$menu .= '<div class="batch-users">';
					$menu .= '<a data-dropdown="top-start" class="batch-one-item">';
						$menu .= \Asset::icon('people-fill');
						$menu .= '<span>'.s("Affecter").'</span>';
					$menu .= '</a>';
					$menu .= $this->getBatchUsers('batch-task-one-form', $cUser);
				$menu .= '</div>';
			}


		}
		if($eStructure->canPlanningLimited()) {
			$menu .= '<a data-ajax-submit="/series/comment:createCollection" data-ajax-method="get" class="batch-one-item">'.\Asset::icon('chat-dots-fill').'<span>'.s("Commenter").'</span></a>';
		}
		if($eStructure->canPlanning()) {
			$menu .= '<a class="batch-one-item batch-update">'.\Asset::icon('gear-fill').'<span>'.s("Modifier").'</span></a>';
		}
		if($eStructure->canPlanning()) {
			$menu .= '<a data-ajax-submit="/series/task:doDeleteCollection" class="batch-one-item batch-item-danger" data-confirm="'.s("Confirmer la suppression de cette intervention ?").'">'.\Asset::icon('trash').'<span>'.s("Supprimer").'</span></a>';
		}

		$h = \util\BatchUi::one('batch-task-one', $menu);

		$menu = '';
		
		if(
			$eStructure->conf()['time'] and
			$eStructure->canPlanningLimited()
		) {

			$menu .= '<a data-ajax-submit="/series/timesheet" data-ajax-method="get" class="batch-item batch-timesheet">';
				$menu .= \Asset::icon('clock');
				$menu .= '<span>'.s("Temps de travail").'</span>';
			$menu .= '</a>';

		}

		if($eStructure->canPlanning()) {

			$menu .= '<a data-ajax-submit="/series/task:updateHarvestCollection" data-ajax-method="get" class="batch-item batch-harvest">';
				$menu .= \Asset::icon('basket2');
				$menu .= '<span>'.s("Récolte").'</span>';
			$menu .= '</a>';

		}

		if($eStructure->canPlanning()) {

			$menu .= '<a data-ajax-submit="/series/task:doUpdateDoneCollection" post-done-week="'.$week.'" class="batch-item batch-done">';
				$menu .= \Asset::icon('check-lg');
				$menu .= '<span>'.s("Fait !").'</span>';
			$menu .= '</a>';

			$menu .= '<a data-ajax-submit="/series/task:doUpdateTodoCollection" data-confirm="'.s("Annuler la réalisation des interventions ?").'" class="batch-item batch-todo">';
				$menu .= \Asset::icon('arrow-up-left');
				$menu .= '<span>'.s("À faire").'</span>';
			$menu .= '</a>';

		}

		if($eStructure->canPlanning()) {

			$menu .= '<div class="batch-planned">';
				$menu .= '<a data-dropdown="top-start" class="batch-item">';
					$menu .= \Asset::icon('watch');
					$menu .= '<span>'.s("Planifier").'</span>';
				$menu .= '</a>';
				$menu .= $this->getBatchPlanned('batch-task-form', $week);
			$menu .= '</div>';

			if($cUser->count() > 1) {
				$menu .= '<div class="batch-users">';
					$menu .= '<a data-dropdown="top-start" class="batch-item">';
						$menu .= \Asset::icon('people-fill');
						$menu .= '<span>'.s("Affecter").'</span>';
					$menu .= '</a>';
					$menu .= $this->getBatchUsers('batch-task-form', $cUser);
				$menu .= '</div>';
			}

		}

		if($eStructure->canPlanningLimited()) {

			$menu .= '<a data-ajax-submit="/series/comment:createCollection" data-ajax-method="get" class="batch-item">';
				$menu .= \Asset::icon('chat-dots-fill');
				$menu .= '<span>'.s("Commenter").'</span>';
			$menu .= '</a>';

		}

		if($eStructure->canPlanning()) {

			$danger = '<a data-ajax-submit="/series/task:doDeleteCollection" data-confirm="'.s("Confirmer la suppression de ces interventions ?").'" class="batch-item batch-item-danger">';
				$danger .= \Asset::icon('trash');
				$danger .= '<span>'.s("Supprimer").'</span>';
			$danger .= '</a>';

		} else {
			$danger = NULL;
		}
		
		$h .= \util\BatchUi::group('batch-task', $menu, $danger, title: s("Pour les interventions sélectionnées"), hide: 'Task.hidePlanningSelection()');

		return $h;

	}

	protected function getBatchUsers(string $formId, \Collection $cUser) {

		$h = '<div class="dropdown-list bg-secondary">';
			$h .= '<div class="dropdown-title">'.s("Affecter à...").'</div>';
			foreach($cUser as $eUser) {
				$h .= '<a data-ajax-submit="/series/task:doUpdateUserCollection" data-ajax-target="#'.$formId.'" post-user="'.$eUser['id'].'" post-reload="context" class="batch-planned-user dropdown-item">';
					$h .= \Asset::icon('plus-lg');
					$h .= \Asset::icon('x-lg');
					$h .= '  '.\user\UserUi::getVignette($eUser, '1.5rem').'  '.$eUser->getName();
				$h .= '</a>';
			}
		$h .= '</div>';

		return $h;

	}

	protected function getBatchPlanned(string $formId, ?string $week) {

		$h = '<div class="dropdown-list bg-secondary">';
			if($week !== NULL) {
				$h .= '<div class="dropdown-title">'.s("Planifier un jour précis").'</div>';
				$h .= '<div class="batch-planned-days">';
					foreach(week_dates($week) as $day => $date) {
						$h .= '<a data-ajax-submit="/series/task:doUpdatePlannedDateCollection" data-ajax-target="#'.$formId.'" post-planned-date="'.$date.'" class="dropdown-item">';
							$h .= '<div>'.\util\DateUi::getDayName($day + 1).'</div>';
							$h .= '<div class="batch-planned-days-date">'.\util\DateUi::numeric($date, \util\DateUi::DAY_MONTH).'</div>';
						$h .= '</a>';
					}
				$h .= '</div>';
				$h .= '<div class="dropdown-title">'.s("Planifier à la semaine").'</div>';
			} else {
				$h .= '<div class="dropdown-title">'.s("Planifier").'</div>';
			}
			$h .= '<div class="batch-planned-weeks">';
				$h .= '<a data-ajax-submit="/series/task:doUpdatePlannedCollection" data-ajax-target="#'.$formId.'" post-planned-week="'.\Clock::week().'" class="dropdown-item batch-planned-other">';
					$h .= '<div>'.s("Cette semaine").'</div>';
					$h .= '<div class="batch-planned-days-date">'.s("Semaine {week}, {year}", ['week' => week_number(\Clock::week()), 'year' => week_year(\Clock::week())]).'</div>';
				$h .= '</a>';
				$h .= '<a data-ajax-submit="/series/task:updatePlannedCollection" data-ajax-method="get" data-ajax-target="#'.$formId.'" class="dropdown-item batch-planned-other">';
					$h .= '<div>'.s("Une autre semaine").'</div>';
				$h .= '</a>';
			$h .= '</div>';
			$h .= '<a data-ajax-submit="/series/task:doIncrementPlannedCollection" data-ajax-target="#'.$formId.'" class="dropdown-item batch-postpone" post-increment="-1">'.s("Décaler une semaine plus tôt").'</a>';
			$h .= '<a data-ajax-submit="/series/task:doIncrementPlannedCollection" data-ajax-target="#'.$formId.'" class="dropdown-item batch-postpone" post-increment="1">'.s("Décaler une semaine plus tard").'</a>';
			$h .= '<a data-ajax-submit="/series/task:incrementPlannedCollection" data-ajax-method="get" data-ajax-target="#'.$formId.'" class="dropdown-item batch-postpone">'.s("Décaler davantage").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function getBatchHarvestString(Task $eTask): string {

		$eTask->expects(['plant', 'variety', 'harvestSize', 'harvestUnit']);

		return
			($eTask['plant']->empty() ? '' : $eTask['plant']['id'])
			.'-'.($eTask['variety']->empty() ? '' : $eTask['variety']['id'])
			.'-'.($eTask['harvestSize']->empty() ? '' : $eTask['harvestSize']['id'])
			.'-'.($eTask['harvestUnit'] ?? '');

	}

	public function getBatchCheckbox(\util\FormUi $form, Task $eTask): string {

		$batch = [];

		if($eTask['action']['fqn'] === ACTION_RECOLTE) {
			$batch[] = 'harvest';
		}
		if($eTask->acceptPostpone() === FALSE) {
			$batch[] = 'not-postpone';
		}
		if($eTask->isDone()) {
			$batch[] = 'done';
		}
		if($eTask['series']->notEmpty()) {
			$batch[] = 'series';
		}
		if($eTask->isTodo()) {
			$batch[] = 'todo';
		}
		if($eTask['plannedUsers']) {
			$batch[] = 'users';
			foreach($eTask['plannedUsers'] as $user) {
				$batch[] = 'user-'.$user;
			}
		}

		$checked = (
			self::$export and
			$eTask->isDone()
		);

		return $form->inputCheckbox('batch[]', $eTask['id'], [
			'checked' => $checked,
			'data-week' => $eTask['doneWeek'] ?? $eTask['plannedWeek'],
			'data-batch' => implode(' ', $batch),
			'data-series' => $eTask['series']->empty() ? '' : $eTask['series']['id'],
			'oninput' => 'Task.changePlanningSelection()'
		]);

	}

	public function getTaskPlace(Task $eTask, ?string $week = NULL): string {

		$place = '';

		if(
			self::$export and
			$eTask['plant']->notEmpty()
		) {
			$place .= \plant\PlantUi::getVignette($eTask['plant'], '1.5rem');
			$place .= '<span class="tasks-planning-item-plant">'.encode($eTask['plant']['name']).'</span>';
		}

		if($eTask['series']->empty() === FALSE) {
			$name = '<span class="tasks-planning-item-series-name">'.SeriesUi::name($eTask['series']).'</span>';
			$place .= '<span>';
				$place .= s("Série {value}", $name);
				$place .= \sequence\CropUi::start($eTask['cultivation'], \work\WorkSetting::$mainActions);
				if(
					$week !== NULL and
					$eTask['series']['season'] !== week_year($week)
				) {
					$place .= ' - '.s("Saison {value}", $eTask['series']['season']);
				}
				if($eTask['series']['cccPlace']->notEmpty()) {
					$place .= ' - <div class="tasks-planning-item-series-places">'.new CultivationUi()->displayPlaces($eTask['series']['use'], $eTask['series']['cccPlace']).'</div>';
				}
			$place .= '</span>';
		} else if($eTask['cccPlace']->notEmpty()) {
			$place .= '<div class="tasks-planning-item-series-places">'.new CultivationUi()->displayPlaces(Series::BED, $eTask['cccPlace']).'</div>';
		} else {
			return '';
		}

		return '<div class="tasks-planning-item-series">'.$place.'</div>';

	}

	public function getTaskContent(Task $eTask, bool $showPlant = TRUE, bool $showAction = TRUE, bool $showTools = FALSE): string {

		\Asset::css('structure', 'action.css');

		$content = '';

		if($showAction) {

			$content .= '<div class="tasks-planning-item-label">';
				if($eTask->isDone() and $eTask['action']['fqn'] === ACTION_RECOLTE) {
					if($showPlant) {
						$content .= '<span class="action-name">'.encode($eTask['plant']['name']).'</span> ';
					}
					$content .= $this->getMore($eTask);
				} else {
					$content .= $this->getAction($eTask);
				}
			$content .= '</div>';

		} else {

			if($eTask['plant']->notEmpty()) {
				if($showPlant) {
					$content .= '<span class="action-name">'.encode($eTask['plant']['name']).'</span> ';
				}
			}

			$content .= $this->getMore($eTask);

			if($showTools) {
				$content .= $this->getTaskTools($eTask);
			}

		}

		$h = '';

		if($content) {
			$h .= '<div class="tasks-planning-item-content">';
				$h .= $content;
			$h .= '</div>';
		}

		return $h;
	}

	/**
	 * Les produits puis les outils d'une intervention, à la suite de son complément, avec la dose d'un produit
	 * phytosanitaire : le planning actuel et la grille de la Semaine les écrivent ainsi. `FlowUi::getTools()` en
	 * fait un bloc à part, sans la dose, qui ne se place pas sur la ligne du complément.
	 */
	public function getTaskTools(Task $eTask): string {

		$h = '';

		foreach(new \Collection()->mergeCollection($eTask['cProduct?']())->mergeCollection($eTask['cTool?']()) as $eTool) {
			$h .= ' <span class="flow-tool-name">'.\work\ToolUi::getVignette($eTool, '2rem', '1.5rem').' '.encode($eTool['name']).$this->getTreatmentSummary($eTask, $eTool).'</span> ';
		}

		return $h;

	}

	protected function getTreatmentSummary(Task $eTask, \work\Tool $eTool): string {

		if(
			$eTool['routineName'] !== 'phyto' or
			$eTask['treatment'] === NULL or
			$eTask['treatment']['dose'] === NULL
		) {
			return '';
		}

		$h = ' · '.\util\TextUi::number($eTask['treatment']['dose'], NULL).' '.($eTask['treatment']['unit'] ?? $eTool['routineValue']['unit']);

		if($eTool['routineValue']['readyToUse'] === FALSE and $eTask['treatment']['water'] !== NULL) {
			$h .= ' · '.s("{value} L/ha", $eTask['treatment']['water']);
		}

		return $h;

	}

	public function getTaskDescription(Task $eTask): string {

		\Asset::css('structure', 'action.css');

		$h = '';

		$description = $this->getDescription($eTask);

		if($description) {
			$h .= '<div class="tasks-planning-item-description">';
				$h .= $description;
			$h .= '</div>';
		}

		return $h;
	}

	public function getTaskComplement(Task $eTask) {

		$h = '';

		foreach($this->getTaskSizeVariety($eTask) as $part) {
			$h .= ' '.$part.' ';
		}

		$h .= $this->getTaskMethods($eTask);

		return $h;

	}

	public function getTaskSizeVariety(Task $eTask): array {

		$parts = [];

		if($eTask['action']['fqn'] === ACTION_RECOLTE) {

			$eTask->expects(['harvestSize', 'variety']);

			if($eTask['harvestSize']->notEmpty()) {
				$parts[] = '<span class="task-size-name">'.encode($eTask['harvestSize']['name']).'</span>';
			}

		}

		if($eTask['variety']->notEmpty()) {
			$parts[] = '<span class="task-variety-name">'.encode($eTask['variety']['name']).'</span>';
		}

		return $parts;

	}

	public function getTaskMethods(Task $eTask): string {

		$h = '';

		foreach($eTask['cMethod?']() as $eMethod) {
			$h .= ' <span class="flow-method-name">'.encode($eMethod['name']).'</span> ';
		}

		return $h;

	}

	public function getMore(Task $eTask): string {

		$h = $this->getTaskComplement($eTask);
		$h .= new \sequence\FlowUi()->getMore($eTask);

		return $h;

	}

	public function getTimeline(\structure\Structure $eStructure, Series $eSeries, \Collection $cCultivation, \Collection $cTask): string {

		\Asset::css('sequence', 'flow.css');

		if($cTask->empty()) {
			return $this->getEmptyTimeline($eSeries);
		}

		$form = new \util\FormUi();

		$h = '<script type="text/javascript">Task.labels = '.json_encode([
			'withFollowing' => s("avec les suivantes"),
			'alone' => s("seule"),
		]).';</script>';

		$h .= '<div id="series-task-wrapper" data-series="'.$eSeries['id'].'" data-batch="#batch-task" onrender="Task.settleMove(this); Task.layoutDelays(this)">';

			$h .= '<div class="util-title">';
				$h .= '<h3>'.s("Interventions").'</h3>';
				$h .= $this->planTask($eSeries);
			$h .= '</div>';

			$h .= '<div class="flow-timeline-wrapper flow-timeline-vertical stick-xs">';

				$h .= '<div class="flow-timeline flow-timeline-header">';

					$h .= '<div></div>';
					$h .= '<div class="util-table-header flow-timeline-axis-header">';
						$h .= s("Semaine");
					$h .= '</div>';
					$h .= '<div></div>';
					$h .= '<div class="util-table-header flow-timeline-rows-header">';
						$h .= '<label class="flow-timeline-update" title="'.s("Tout cocher / Tout décocher").'">';
							$h .= $form->inputCheckbox(attributes: ['onclick' => 'Task.checkPlanningSeries(this)', 'class' => 'batch-all']);
							$h .= '<span>'.s("Intervention").'</span>';
						$h .= '</label>';
					$h .= '</div>';

				$h .= '</div>';

				$h .= $this->getTimelineBody($cTask, [
					'item' => function(Task $eTask, bool $newWeek) {

						$h = '';

						if($newWeek) {
							$h .= '<div class="flow-timeline-circle">'.s("s{value}", week_number($eTask['display'])).'</div>';
						}

						return $h;

					},
					'content' => function(Task $eTask, string $update) use($eStructure, $cCultivation, $form) {

						if($eTask['cultivation']->empty() === FALSE) {
							$eCultivation = $cCultivation[$eTask['cultivation']['id']];
							$eTask['plant'] = $eCultivation['plant'];
							$eTask['cultivation'] = $eCultivation;
						}

						// La fiche : le nom et la description côte à côte, le temps, l'état
						// et la case à droite ; le matériel et les commentaires dessous.
						$h = '<div class="flow-timeline-card-head">';
							$h .= $update;
							$h .= '<a href="'.\planning\TaskDetailUi::url($eTask).'" class="flow-timeline-text">'.$this->getAction($eTask).'</a>';
							$h .= $this->getDescription($eTask);
							$h .= '<div class="flow-timeline-card-side">';
								if($eStructure->conf()['time']) {
									$h .= $this->getTime($eTask);
								}
								$h .= $this->getDone($eTask, \Clock::week());
							$h .= '</div>';
						$h .= '</div>';

						$h .= new \sequence\FlowUi()->getTools($eTask);
						$h .= $this->getComments($eTask);

						return $h;

					},
					'update' => function(Task $eTask) use($form) {

						$filters = [
							'data-filter-action' => $eTask['action']['id'],
							'data-filter-plant' => ($eTask['plant']->empty() ? '' : $eTask['plant']['id']),
							'data-filter-harvest' => $this->getBatchHarvestString($eTask)
						];

						$h = '<label class="flow-timeline-select batch-checkbox" '.attrs($filters).'>';
							$h .= $this->getBatchCheckbox($form, $eTask);
						$h .= '</label>';

						return $h;

					}
				], TRUE, $cCultivation, harvestMovable: ($eSeries['status'] === Series::OPEN));

			$h .= '</div>';

		$h .= '</div>';

		$h .= $this->getBatch($eStructure);

		return $h;

	}

	protected function getEmptyTimeline(Series $eSeries): string {

		$h = '<div id="series-task-wrapper" data-series="'.$eSeries['id'].'">';
			$h .= '<div class="util-title">';
				$h .= '<h3>'.s("Interventions").'</h3>';
				$h .= $this->planTask($eSeries);
			$h .= '</div>';
			$h .= '<p class="util-empty">';
				$h .= s("Vous n'avez pas encore saisi d'intervention pour cette série.");
			$h .= '</p>';
		$h .= '</div>';

		return $h;

	}

	public function planTask(Series $eSeries): string {

		if(
			$eSeries['status'] !== Series::OPEN or
			(new Task(['structure' => $eSeries['structure']]))->canWrite() === FALSE
		) {
			return '';
		}

		$h = '<div>';
			$h .= '<a class="btn btn-outline-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('calendar-plus').' '.s("Nouvelle intervention").'</a>';
			$h .= '<div class="dropdown-list">';

				$h .= '<div class="dropdown-title">'.s("Nouvelle intervention").'</div>';
				$h .= '<a href="/series/task:createFromSeries?structure='.$eSeries['structure']['id'].'&series='.$eSeries['id'].'&status='.Task::TODO.'" class="dropdown-item">'.s("Planifier une future intervention").'</a>';
				$h .= '<a href="/series/task:createFromSeries?structure='.$eSeries['structure']['id'].'&series='.$eSeries['id'].'&status='.Task::DONE.'" class="dropdown-item">'.s("Ajouter une intervention déjà réalisée").'</a>';

			$h .= '</div>';
		$h .= '</div>';

		return $h;
	}

	public function getDone(Task $eTask, string $week, array $onUpdate = []): string {

		\Asset::css('sequence', 'flow.css');

		$h = '<div class="flow-timeline-done" id="series-task-done-'.$eTask['id'].'">';

			if(
				$eTask->isTodo() and
				$eTask->canWrite()
			) {

				$h .= '<a data-ajax="/series/task:doUpdateDoneCollection" '.attrs($onUpdate).' '.attrAjaxBody([['ids[]', $eTask['id']], ['doneWeek', $week]]).' class="flow-timeline-status-todo" title="'.s("Fait !").'">';
					$h .= \Asset::icon('check');
				$h .= '</a>';

			}

		$h .= '</div>';

		return $h;

	}

	public function getAction(Task $eTask, \Collection $cActionMain = new \Collection()): string {
		return \work\ActionUi::text($eTask, $cActionMain).' '.$this->getMore($eTask);
	}

	// La bulle du glissé : ce que « avec la suite » emporte, semaine par semaine.
	// Chargée à l'appui long, pas avant — la liste vit côté serveur ; le choix,
	// lui, se fait au geste (monter ou descendre jusqu'à une cible).
	public function getShiftChoice(Task $eTask, \Collection $cTaskFollowing, \Collection $cCultivationFollowing): string {

		$eTask->expects(['action', 'plannedWeek']);

		$tasks = implode(',', $cTaskFollowing->getIds());
		$cultivations = implode(',', $cCultivationFollowing->getIds());
		$count = $cTaskFollowing->count() + $cCultivationFollowing->count();

		$h = '<div id="series-choice" class="series-item-timeline-choice" data-tasks="'.$tasks.'" data-cultivations="'.$cultivations.'">';

			$h .= '<div class="series-item-timeline-choice-title">'.encode($eTask['action']['name']).' <span>'.s("s{value}", week_number($eTask['plannedWeek'])).'</span></div>';

			$h .= '<div class="series-item-timeline-choice-body">';

				if($count > 0) {

					$h .= '<ul class="series-item-timeline-choice-list">';
						foreach($cTaskFollowing as $eTaskFollowing) {
							$h .= '<li>';
								$h .= \work\ActionUi::getCircle($eTaskFollowing['action']).' ';
								$h .= encode($eTaskFollowing['action']['name']);
								$h .= '<span>'.s("s{value}", week_number($eTaskFollowing['plannedWeek'])).'</span>';
							$h .= '</li>';
						}
						foreach($cCultivationFollowing as $eCultivation) {
							$weeks = $eCultivation['harvestWeeksExpected'];
							sort($weeks);
							$h .= '<li>';
								$h .= '<span class="series-item-timeline-choice-harvest">'.s("R").'</span> ';
								$h .= s("Récolte attendue de {value}", encode($eCultivation['plant']['name']));
								$h .= '<span>'.s("s{from} – s{to}", ['from' => week_number($weeks[0]), 'to' => week_number(end($weeks))]).'</span>';
							$h .= '</li>';
						}
					$h .= '</ul>';

				} else {

					$h .= '<div class="series-item-timeline-choice-alone">'.s("Rien ne suit cette intervention : elle se déplace seule.").'</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getDescription(Task $eTask): string {

		if($eTask['description'] === NULL) {
			return '';
		}

		$description = \util\TextUi::tiny(nl2br(encode($eTask['description'])), FALSE);

		$lines = [];

		foreach(explode("\n", $description) as $position => $content) {

			if(str_starts_with($content, 'o ') or str_starts_with($content, 'O ')) {
				$line = '<div class="flow-timeline-description-list util-tick-line">';
					if($eTask->canWrite()) {
						$line .= '<div class="util-tick" data-action="task-checkbox" post-id="'.$eTask['id'].'" post-position="'.$position.'" post-check="1" data-ajax-navigation="never">'.\Asset::icon('circle').'</div>';
					} else {
						$line .= \Asset::icon('circle');
					}
					$line .= '<div>'.ltrim(mb_substr($content, 1)).'</div>';
				$line .= '</div>';
			} else if(str_starts_with($content, 'x ') or str_starts_with($content, 'X ')) {
				$line = '<div class="flow-timeline-description-list util-tick-line">';
					if($eTask->canWrite()) {
						$line .= '<div class="util-tick" data-action="task-checkbox" post-id="'.$eTask['id'].'" post-position="'.$position.'" post-check="0" data-ajax-navigation="never">'.\Asset::icon('check-circle').'</div>';
					} else {
						$line .= \Asset::icon('check-circle');
					}
					$line .= '<div>'.ltrim(mb_substr($content, 1)).'</div>';
				$line .= '</div>';
			} else {
				$line = $content;
			}

			$lines[] = $line;

		}

		$h = '<div class="flow-timeline-description" data-task="'.$eTask['id'].'">';
			$h .= implode("\n", $lines);
		$h .= '</div>';

		return $h;

	}

	public function getComments(Task $eTask): string {

		$h = '';

		if($eTask['cComment']->notEmpty()) {
			$h .= '<div class="flow-timeline-comments util-print-hide" id="task-'.$eTask['id'].'-comments">';
				$h .= new CommentUi()->getList($eTask['cComment']);
			$h .= '</div>';
		}

		return $h;

	}

	/**
	 * `$origin` est le lieu où la roue est affichée, que la suppression poste pour que la réponse sache où
	 * revenir (`doDelete`, `doDeleteRepeat`).
	 */
	protected function getUpdate(Task $eTask, string $btn, ?string $origin = NULL): string {

		if($eTask->canWrite()) {

			$postOrigin = ($origin === NULL) ? '' : ' post-origin="'.$origin.'"';

			$h = '<a data-dropdown="bottom-end" class="dropdown-toggle btn '.$btn.'">'.\Asset::icon('gear-fill').'</a>';
			$h .= '<div class="dropdown-list">';
				$h .= '<div class="dropdown-title">'.s("Intervention").'</div>';
				if($eTask['action']['fqn'] === ACTION_RECOLTE) {
					$h .= '<a href="/series/task:updateHarvestCollection?ids[]='.$eTask['id'].'" class="dropdown-item">'.s("Compléter la récolte").'</a>';
				}
				$h .= '<a href="/series/task:update?id='.$eTask['id'].'" class="dropdown-item">'.s("Modifier l'intervention").'</a>';

				if($eTask->isDone()) {
					$h .= '<a data-ajax="/series/task:doUpdateTodoCollection" post-id="'.$eTask['id'].'" '.attrAjaxBody([["ids[]", $eTask['id']]]).' class="dropdown-item" data-confirm="'.s("Annuler la réalisation de l'intervention ?").'">'.s("Marquer &laquo; À faire &raquo;").'</a>';
				}

				if($eTask['cultivation']->notEmpty()) {
					$h .= '<a href="/series/task:updateCultivation?id='.$eTask['id'].'" class="dropdown-item">'.s("Changer de série").'</a>';
				}

				$h .= '<div class="dropdown-divider"></div>';

				if($eTask['repeat']->empty()) {

					$h .= '<a data-ajax="/series/task:doDelete" class="dropdown-item" post-id="'.$eTask['id'].'"'.$postOrigin.' data-confirm="'.s("Confirmer la suppression de cette intervention ?").'">'.s("Supprimer l'intervention").'</a>';

				} else {

					$h .= '<div class="dropdown-subtitle">'.s("Supprimer").'</div>';
					$h .= '<a data-ajax="/series/task:doDelete" class="dropdown-item" post-id="'.$eTask['id'].'"'.$postOrigin.' data-confirm="'.s("Confirmer la suppression de cette intervention ?").'"> '.\Asset::icon('arrow-right').'  '.s("Uniquement cette intervention").'</a>';
					$h .= '<a data-ajax="/series/task:doDeleteRepeat" class="dropdown-item" post-id="'.$eTask['id'].'"'.$postOrigin.' data-confirm="'.s("Confirmer la suppression de cette intervention et de toutes les suivantes ?").'"> '.\Asset::icon('arrow-right').'  '.s("Cette intervention et toutes les suivantes").'</a>';

				}



			$h .= '</div>';

		} else {
			$h = '';
		}

		return $h;

	}

	public function getUsersReadOnly(Task $eTask, \Collection $cUser): string {

		$h = '<div class="tasks-item-users-list">';

			foreach($cUser as $eUser) {

				if($eUser['time'] !== NULL) {
					$h .= $this->getUser($eUser, $eUser['time']);
				}

			}

		$h .= '</div>';

		return $h;

	}

	protected function getUser(\user\User $eUser, float $time, string $size = '2rem'): string {

		$h = '<div title="'.$eUser->getName().' : '.self::convertTimeText($time).'">';
			$h .= \user\UserUi::getVignette($eUser, $size);
		$h .= '</div>';

		return $h;

	}

	public static function getTime(Task $eTask, ?float $time = NULL): string {

		$time ??= $eTask['time'];

		$h = '<a href="/series/timesheet?ids[]='.$eTask['id'].'" class="tasks-planning-item-time" title="'.s("Gérer le temps de travail sur l'intervention").'">';
			$h .= '<div class="tasks-planning-item-time-icon">';
				$h .= \Asset::icon('clock');
			$h .= '</div>';
				$h .= self::getTimeValue($time);
		$h .= '</a>';

		return $h;

	}

	public static function getTimeValue(?float $time): string {

		$h = '<div class="tasks-planning-item-time-value '.($time > 0 ? 'text-end' : 'text-center').'">';

		if($time > 0) {
			$h .= self::convertTime($time);
		} else {
			$h .= '&middot; &middot; &middot;';
		}

		$h .= '</div>';

		return $h;

	}

	public static function convertTime(float $time, ?bool $showMinutes = TRUE): string {

		\Asset::css('series', 'task.css');

		$hours = intval($time);

		$minutes = round(($time - $hours) * 60);

		if($minutes === 60.0) {
			$minutes = 0;
			$hours++;
		}

		if(
			$showMinutes === TRUE or
			($showMinutes === NULL and $minutes > 0)
		) {

			return '<span><span class="task-time-hours">'.$hours.'</span><small class="task-time-hour">h</small><span class="task-time-minutes">'.sprintf('%02d', $minutes).'</span></span>';

		} else {
			return '<span><span class="task-time-hours">'.$hours.'</span><small class="task-time-hour">h</small></span>';
		}

	}

	public static function convertTimeText(float $time): string {

		$hours = intval($time);
		$minutes = round(($time - $hours) * 60);

		return $hours.' h '.sprintf('%02d', $minutes);

	}

	// Le diagramme du plan de culture, posé à la verticale : toutes les semaines
	// de la série (plus trois de marge de part et d'autre) forment une colonne
	// continue, une bande par semaine — occupée ou non. Sur le rail : le ruban de
	// la série de sa première à sa dernière intervention, la campagne de récolte
	// attendue, et la pastille de chaque intervention. Le glissé d'une pastille
	// suit le rail et se dépose sur une bande.
	protected function getTimelineBody(\Collection $cTask, array $callbacks, bool $canWrite, \Collection $cCultivation = new \Collection(), bool $harvestMovable = TRUE): string {

		array_expects($callbacks, ['content']);

		$today = \Clock::week();
		$months = \util\DateUi::months();
		$monthOf = fn(string $week) => (int)date('n', strtotime($week.' + 3 DAY'));
		$monthLast = fn(string $week) => $monthOf($week) !== $monthOf(toWeek(strtotime($week.' + 1 WEEK')));

		// Les interventions par semaine ; celles sans semaine à part, en tête.
		$byWeek = [];
		$orphans = [];

		foreach($cTask as $eTask) {
			$eTask->expects(['display']);
			if($eTask['display'] === NULL) {
				$orphans[] = $eTask;
			} else {
				$byWeek[$eTask['display']][] = $eTask;
			}
		}

		ksort($byWeek);

		$weeks = array_keys($byWeek);

		// La campagne de récolte attendue, toutes cultures confondues.
		$harvest = [];

		foreach($cCultivation as $eCultivation) {
			foreach($eCultivation['harvestWeeksExpected'] ?? [] as $week) {
				$harvest[$week] = TRUE;
			}
		}

		if($weeks === [] and $harvest === []) {
			$first = NULL;
		} else {
			$bounds = array_merge($weeks, array_keys($harvest));
			$first = toWeek(strtotime(min($bounds).' - 3 WEEK'));
			$last = toWeek(strtotime(max($bounds).' + 3 WEEK'));
		}

		// Le ruban va de la première intervention à la dernière — ou à la fin de la
		// campagne attendue si elle est plus loin, comme sur le plan.
		$harvestWeeks = array_keys($harvest);
		sort($harvestWeeks);
		$spanFirst = $weeks ? $weeks[0] : ($harvestWeeks ? $harvestWeeks[0] : NULL);
		$spanLast = ($weeks or $harvestWeeks) ? max(array_merge($weeks, $harvestWeeks)) : NULL;
		$harvestMiddle = $harvestWeeks ? $harvestWeeks[intdiv(count($harvestWeeks), 2)] : NULL;
		$harvestAlone = ($harvestWeeks and array_intersect($harvestWeeks, $weeks) === []);

		// Chaque culture a sa campagne : une barre par culture sur le rail, côte à
		// côte, avec ses propres poignées ; la première dessine à gauche.
		$harvestByCultivation = [];
		$harvestPlant = [];

		foreach($cCultivation as $eCultivation) {
			$weeksExpected = $eCultivation['harvestWeeksExpected'] ?? [];
			if($weeksExpected !== []) {
				sort($weeksExpected);
				$harvestByCultivation[$eCultivation['id']] = $weeksExpected;
				$harvestPlant[$eCultivation['id']] = $eCultivation['plant'];
			}
		}

		$harvestCount = count($harvestByCultivation);

		// Les délais du plan : entre deux repères consécutifs, le nombre de semaines,
		// culture par culture — le semis d'une espèce mène à sa plantation puis à sa
		// récolte, pas à celles de l'espèce d'à côté. Dans une semaine, le repère
		// d'une culture est son semis ou sa plantation (le dernier s'il y en a
		// plusieurs), pas la protection posée à côté.
		// Seules ces quatre étapes jalonnent un cycle : le reste (sol, fertilisation,
		// protection, traitement, taille…) se pose entre elles sans les décaler.
		$isKey = fn(Task $eTask) => in_array($eTask['action']['fqn'], [ACTION_SEMIS_PEPINIERE, ACTION_SEMIS_DIRECT, ACTION_PLANTATION, ACTION_RECOLTE]);

		$keyTaskOf = function(string $w, ?int $cultivationId) use($byWeek, $isKey): ?Task {
			$eTaskKey = NULL;
			foreach($byWeek[$w] ?? [] as $eTask) {
				if($isKey($eTask) and ($cultivationId === NULL or $eTask['cultivation']['id'] === $cultivationId)) {
					$eTaskKey = $eTask;
				}
			}
			return $eTaskKey;
		};

		// Une chaîne par culture : ses semaines de semis / plantation et les bouts
		// de sa campagne. Sans aucun semis ni plantation, une seule chaîne où
		// chaque semaine occupée compte, comme sur le plan.
		$chains = [];

		foreach($byWeek as $w => $cTaskWeek) {
			foreach($cTaskWeek as $eTask) {
				if($isKey($eTask) and $eTask['cultivation']->notEmpty()) {
					$chains[$eTask['cultivation']['id']]['weeks'][$w] = TRUE;
				}
			}
		}

		foreach($harvestByCultivation as $cultivationId => $weeksExpected) {
			$chains[$cultivationId]['harvest'] = [$weeksExpected[0], end($weeksExpected)];
		}

		$hasKey = array_filter($chains, fn($chain) => ($chain['weeks'] ?? []) !== []) !== [];

		// Sans aucune de ces étapes, il n'y a pas de cycle à mesurer : reste la
		// campagne attendue, dont on donne la longueur.
		if($hasKey === FALSE) {
			$chains = $harvestWeeks ? [NULL => ['weeks' => [], 'harvest' => [$harvestWeeks[0], end($harvestWeeks)]]] : [];
		}

		// Plusieurs cultures sur la série : les chaînes se croiseraient dans la
		// colonne des semaines sans qu'on sache laquelle est à qui — on n'en
		// affiche aucune.
		if($cCultivation->count() > 1) {
			$chains = [];
		}

		// Chaque semaine porte ses segments : le trait entier sur une bande vide, un
		// départ ou une arrivée sur la bande d'un repère, accroché à la pastille ou
		// au bout de la campagne ; le nombre au milieu. La chaîne k dessine à
		// k traits du rail.
		$delays = [];
		$rank = 0;

		foreach($chains as $cultivationId => $chain) {

			$cultivationId = ($cultivationId === '' or $cultivationId === NULL) ? NULL : (int)$cultivationId;
			$harvestBounds = $chain['harvest'] ?? NULL;

			$steps = array_keys($chain['weeks'] ?? []);
			if($harvestBounds !== NULL) {
				$steps[] = $harvestBounds[0];
				$steps[] = $harvestBounds[1];
			}
			$steps = array_values(array_unique($steps));
			sort($steps);

			$anchorOf = function(string $w) use($cultivationId, $keyTaskOf, $byWeek, $harvestBounds, $hasKey): string {
				if($hasKey ? ($keyTaskOf($w, $cultivationId) !== NULL) : isset($byWeek[$w])) {
					return 'pill';
				}
				return ($harvestBounds !== NULL and $w === $harvestBounds[0]) ? 'top' : 'bottom';
			};

			foreach($steps as $i => $step) {

				if($i === 0) {
					continue;
				}

				$previous = $steps[$i - 1];
				$n = (int)round((strtotime($step) - strtotime($previous)) / 604800);

				if($n < 2) {
					continue;
				}

				$between = [];
				for($w = toWeek(strtotime($previous.' + 1 WEEK')); $w < $step; $w = toWeek(strtotime($w.' + 1 WEEK'))) {
					$between[] = $w;
				}

				$group = $rank.'-'.$i;

				$delays[$previous][] = ['type' => 'start-'.$anchorOf($previous), 'label' => NULL, 'group' => $group, 'chain' => $rank, 'cultivation' => $cultivationId];

				foreach($between as $position => $w) {
					$delays[$w][] = ['type' => 'full', 'label' => ($position === intdiv(count($between), 2)) ? $n : NULL, 'group' => $group, 'chain' => $rank, 'cultivation' => $cultivationId];
				}

				$delays[$step][] = ['type' => 'end-'.$anchorOf($step), 'label' => NULL, 'group' => $group, 'chain' => $rank, 'cultivation' => $cultivationId];

			}

			$rank++;

		}

		$h = '<div class="flow-timeline-body">';

			foreach($orphans as $eTask) {
				$h .= $this->getTimelineOrphan($eTask, $callbacks, $canWrite);
			}

			$lastMonth = NULL;
			$lastYear = NULL;

			$week = $first;

			while($week !== NULL and $week <= $last) {

				$year = week_year($week);
				$month = $monthOf($week);

				if($year !== $lastYear) {
					$h .= '<div class="flow-timeline flow-timeline-year">';
						$h .= '<div></div>';
						$h .= '<div class="flow-timeline-year-value">'.$year.'</div>';
						$h .= '<div></div>';
						$h .= '<div></div>';
					$h .= '</div>';
					$lastYear = $year;
					$lastMonth = NULL;
				}

				$occupied = isset($byWeek[$week]);
				$inSpan = ($spanFirst !== NULL and $week >= $spanFirst and $week <= $spanLast);
				$inHarvest = isset($harvest[$week]);

				$class = 'flow-timeline flow-timeline-band';
				$class .= $occupied ? ' flow-timeline-band-occupied' : ' flow-timeline-band-empty';
				$class .= $inSpan ? ' flow-timeline-band-span' : '';
				$class .= $inHarvest ? ' flow-timeline-band-harvest' : '';
				if($inHarvest and $week === $harvestWeeks[0]) {
					$class .= ' flow-timeline-band-harvest-first';
				}
				if($inHarvest and $week === end($harvestWeeks)) {
					$class .= ' flow-timeline-band-harvest-last';
				}
				$class .= ($week === $today) ? ' flow-timeline-band-today' : '';
				if($inSpan and $week === $spanFirst) {
					$class .= ' flow-timeline-band-span-first';
				}
				if($inSpan and $week === $spanLast) {
					$class .= ' flow-timeline-band-span-last';
				}

				$monthFirst = ($month !== $lastMonth);
				$isMonthLast = $monthLast($week);
				$class .= $monthFirst ? ' flow-timeline-band-month-first' : '';
				$class .= $isMonthLast ? ' flow-timeline-band-month-last' : '';

				$h .= '<div class="'.$class.'" data-week="'.$week.'">';

					// La gouttière des mois : le nom à la verticale au début du mois, un
					// trait qui court sur toute sa hauteur, un cran à chaque bout.
					$h .= '<div class="flow-timeline-month">';
						if($monthFirst) {
							$h .= '<span class="flow-timeline-month-name">'.$months[$month].'</span>';
							$lastMonth = $month;
						}
					$h .= '</div>';

					// L'axe : la semaine, et ses dates en chiffres sur la même ligne.
					$h .= '<a class="flow-timeline-axis" onclick="Task.checkPlanningWeekSeries(this)">';
						$h .= '<span class="flow-timeline-axis-week">'.s("s{value}", week_number($week)).'</span>';
						if($occupied) {
							$dates = week_dates($week);
							$h .= '<span class="flow-timeline-axis-days">'.date('d/m', strtotime($dates[0])).'<span class="flow-timeline-axis-days-separator">–</span><br/>'.date('d/m', strtotime($dates[6])).'</span>';
						}
						foreach($delays[$week] ?? [] as $delay) {
							if($delay['type'] === 'start-pill') {
								continue;
							}
							// L'arrivée s'accroche à la pastille du semis ou de la plantation
							// de la culture s'il y en a une, sinon à la première ; la hauteur
							// exacte est posée en JS (Task.layoutDelays), le CSS vise la première.
							$anchor = ($delay['type'] === 'end-pill') ? ' data-task="'.($keyTaskOf($week, $delay['cultivation']) ?? $byWeek[$week][0])['id'].'"' : '';
							$h .= '<span class="flow-timeline-delay flow-timeline-delay-'.$delay['type'].'" data-delay="'.$delay['group'].'" style="--chain: '.$delay['chain'].';"'.$anchor.'>';
								if($delay['label'] !== NULL) {
									$h .= '<span class="flow-timeline-delay-label">'.s("{value} sem.", $delay['label']).'</span>';
								}
							$h .= '</span>';
						}
					$h .= '</a>';

					// Le rail, avec les pastilles de la semaine, et les bornes de la campagne
					// attendue quand une seule culture la porte : glisser l'une ou l'autre
					// avance, retarde, allonge ou raccourcit — comme sur le plan.
					$h .= '<div class="flow-timeline-rail">';
						$k = 0;
						foreach($harvestByCultivation as $cultivationId => $weeksExpected) {
							if(in_array($week, $weeksExpected)) {
								$h .= $this->getTimelineHarvest($week, $cultivationId, $weeksExpected, $k, $harvestCount, $harvestMovable, $harvestPlant[$cultivationId] ?? new \plant\Plant());
							}
							$k++;
						}
					$h .= '</div>';

					// Les fiches : une ligne par intervention, la pastille en tête, posée
					// sur le rail par sa marge ; la fiche est découpée autour d'elle.
					$h .= '<div class="flow-timeline-rows">';
						if($occupied) {
							// Le délai vers le repère suivant part de la dernière pastille de
							// la semaine : c'est la dernière ligne qui porte le segment.
							// Les départs de la semaine, chacun porté par la ligne du semis ou
							// de la plantation de sa culture (la dernière ligne, en repli).
							$starts = [];
							foreach($delays[$week] ?? [] as $delay) {
								if($delay['type'] === 'start-pill') {
									$eTaskStart = $keyTaskOf($week, $delay['cultivation']) ?? $byWeek[$week][array_key_last($byWeek[$week])];
									$starts[$eTaskStart['id']][] = $delay;
								}
							}
							foreach($byWeek[$week] as $eTask) {
								$h .= $this->getTimelineRow($eTask, $callbacks, $canWrite, $starts[$eTask['id']] ?? []);
							}
						} else if($week === $harvestMiddle and $harvestAlone) {
							$h .= '<div class="flow-timeline-harvest-note">'.s("Période de récolte attendue").'</div>';
						}
					$h .= '</div>';

				$h .= '</div>';

				$week = toWeek(strtotime($week.' + 1 WEEK'));

			}

		$h .= '</div>';

		return $h;

	}

	// Un segment de la campagne d'une culture, sur une bande : le premier et le
	// dernier portent les poignées, celui du milieu la lettre.
	protected function getTimelineHarvest(string $week, int $cultivationId, array $weeksExpected, int $k, int $count, bool $canWrite, \plant\Plant $ePlant = new \plant\Plant()): string {

		$first = $weeksExpected[0];
		$last = end($weeksExpected);
		$middle = $weeksExpected[intdiv(count($weeksExpected), 2)];

		$class = 'flow-timeline-harvest';
		$class .= ($week === $first) ? ' flow-timeline-harvest-first' : '';
		$class .= ($week === $last) ? ' flow-timeline-harvest-last' : '';

		$title = ($count > 1 and $ePlant->notEmpty()) ? s("Récolte attendue de {value}", encode($ePlant['name'])) : s("Récolte attendue");

		$h = '<span class="'.$class.'" data-cultivation="'.$cultivationId.'" data-first="'.$first.'" data-last="'.$last.'" style="--k: '.$k.'; --n: '.$count.';" title="'.$title.'">';
			if($week === $middle) {
				// En association, la barre porte la vignette de sa culture : le R ne
				// dirait pas laquelle des deux campagnes on lit.
				if($count > 1 and $ePlant->notEmpty()) {
					$h .= '<span class="flow-timeline-harvest-plant">'.\plant\PlantUi::getVignette($ePlant, '0.9rem').'</span>';
				} else {
					$h .= '<span class="flow-timeline-harvest-label">'.s("R").'</span>';
				}
			}
			if($canWrite and $week === $first) {
				$h .= '<span class="flow-timeline-harvest-bound flow-timeline-harvest-bound-first" data-cultivation="'.$cultivationId.'" data-first="'.$first.'" data-last="'.$last.'" title="'.encode(s("Glisser pour avancer ou retarder le début de la récolte attendue")).'"></span>';
			}
			if($canWrite and $week === $last) {
				$h .= '<span class="flow-timeline-harvest-bound flow-timeline-harvest-bound-last" data-cultivation="'.$cultivationId.'" data-first="'.$first.'" data-last="'.$last.'" title="'.encode(s("Glisser pour allonger ou raccourcir la récolte attendue")).'"></span>';
			}
		$h .= '</span>';

		return $h;

	}

	protected function getTimelinePill(Task $eTask, bool $canWrite): string {

		$eAction = $eTask['action'];

		if($eTask->isDone()) {
			$style = 'background-color: '.$eAction['color'].'; border-color: '.$eAction['color'].';';
			$class = 'series-item-timeline-event-done';
		} else {
			$style = 'color: '.$eAction['color'].'; border-color: '.$eAction['color'].';';
			$class = 'series-item-timeline-event-expected';
		}

		$movable = ($canWrite and $eTask->acceptPostpone());

		return '<div class="flow-timeline-pill '.$class.($movable ? ' flow-timeline-pill-movable' : '').'" data-task="'.$eTask['id'].'" data-week="'.$eTask['display'].'" style="'.$style.'" title="'.encode($eAction['name']).'">'.\work\ActionUi::getShort($eAction).'</div>';

	}

	protected function getTimelineRow(Task $eTask, array $callbacks, bool $canWrite, array $starts = []): string {

		$update = $canWrite ? ($callbacks['update'])($eTask) : '';

		$h = '<div class="flow-timeline-row" data-task="'.$eTask['id'].'">';
			$h .= $this->getTimelinePill($eTask, $canWrite);
			$h .= '<div class="flow-timeline-card">'.($callbacks['content'])($eTask, $update).'</div>';
			foreach($starts as $delay) {
				$h .= '<span class="flow-timeline-delay flow-timeline-delay-start-row" data-delay="'.$delay['group'].'" style="--chain: '.$delay['chain'].';"></span>';
			}
		$h .= '</div>';

		return $h;

	}

	// Une intervention sans semaine : une bande à part, hors de l'axe.
	protected function getTimelineOrphan(Task $eTask, array $callbacks, bool $canWrite): string {

		$h = '<div class="flow-timeline flow-timeline-band flow-timeline-band-orphan">';
			$h .= '<div class="flow-timeline-month"></div>';
			$h .= '<div class="flow-timeline-axis"><span class="flow-timeline-axis-week">'.\Asset::icon('question-circle-fill').'</span></div>';
			$h .= '<div class="flow-timeline-rail"></div>';
			$h .= '<div class="flow-timeline-rows">'.$this->getTimelineRow($eTask, $callbacks, $canWrite).'</div>';
		$h .= '</div>';

		return $h;

	}

	public function getDetail(Task $eTask, \Collection $cPlace, \Collection $cPhoto, \Collection $cUser, \Collection $cComment, \Collection $cNoteLinked): string {

		return $this->getDetailSummary($eTask, $cPlace, $cUser).$this->getDetailComments($eTask, $cComment).$this->getDetailNotes($eTask, $cNoteLinked).$this->getDetailPlaceAndPhotos($eTask, $cPlace, $cPhoto);

	}

	protected function getDetailSummary(Task $eTask, \Collection $cPlace, \Collection $cUser): string {

		$eTask->expects([
			'cultivation', 'cTool', 'cProduct'
		]);

		$cTool = $eTask['cTool'];
		$cProduct = $eTask['cProduct'];

		$h = $this->getDetailHeader($eTask);

		$columns = 1;

		if($eTask['structure']->conf()['time']) {
			$columns++;
		}

		if($eTask['action']['fqn'] === ACTION_RECOLTE) {
			$columns++;
		}

		if($cTool->notEmpty() or $cProduct->notEmpty()) {
			$columns++;
		}

		$h .= '<div id="task-'.$eTask['id'].'-presentation" class="task-item-presentation task-item-presentation-'.$columns.'">';

			$h .= '<div>';

				$h .= '<div class="util-title">';
					$h .= '<h4>'.s("Intervention").'</h4>';
					$h .= '<div>'.$this->getDetailActions($eTask, $cUser).'</div>';
				$h .= '</div>';

				$h .= '<dl class="util-presentation util-presentation-1">'.$this->getDetailTerms($eTask, $cUser).'</dl>';

			$h .= '</div>';

			if($eTask['structure']->conf()['time']) {

				$h .= '<div>';

					$h .= '<div class="util-title">';
						$h .= '<h4>'.s("Temps de travail").'</h4>';
						$h .= '<a href="/series/timesheet?ids[]='.$eTask['id'].'&close=reloadIgnoreCascade" class="btn btn-only-icon btn-secondary">'.\Asset::icon('clock').'</a>';
					$h .= '</div>';
					$h .= '<div>';
						$h .= new TimesheetUi()->getList($eTask, $cUser, 'reloadIgnoreCascade');
					$h .= '</div>';
				$h .= '</div>';

			}

			if($cTool->notEmpty() or $cProduct->notEmpty()) {

				$h .= '<div>';

					if($cProduct->notEmpty()) {

						$h .= '<h4>'.($eTask['action']['fqn'] === ACTION_TRAITEMENT ? s("Produit") : s("Intrants")).'</h4>';
						$h .= '<div>';
							$h .= new \work\ToolUi()->getList($cProduct);
							if($cTool->notEmpty()) {
								$h .= '<h4 class="mt-1">'.s("Matériel").'</h4>';
								$h .= new \work\ToolUi()->getList($cTool);
							}
						$h .= '</div>';

					} else {
						$h .= '<h4>'.s("Matériel").'</h4>';
						$h .= new \work\ToolUi()->getList($cTool);
					}

				$h .= '</div>';

			}

			if($eTask['action']['fqn'] === ACTION_RECOLTE) {

				$h .= '<div>';

					$h .= '<div class="util-title">';
						$h .= '<h4>'.s("Récolte").'</h4>';
						$h .= '<a href="/series/task:updateHarvestCollection?ids[]='.$eTask['id'].'" class="btn btn-only-icon btn-secondary btn">'.\Asset::icon('plus-circle').'</a>';
					$h .= '</div>';

					if($eTask['harvestDates']) {

						krsort($eTask['harvestDates']);

						$h .= '<dl class="util-presentation util-presentation-1">';

						foreach($eTask['harvestDates'] as $date => $value) {

							$workingTime = $eTask['harvestWorkingTime'][$date] ?? NULL;

							$h .= '<dt>'.\util\DateUi::numeric($date).'</dt>';

							$h .= '<dd';
								if($workingTime > 0) {
									$h .= ' title="'.s("{value} / h", \product\UnitUi::getValue(round($value / $workingTime, 1), $eTask['harvestUnit'])).'"';
								}
							$h .= ' class="util-action">';
								$h .= \product\UnitUi::getValue(round($value, 1), $eTask['harvestUnit']);
								$h .= $this->getHarvestDelete($eTask, $date, $value);
							$h .= '</dd>';

						}

						$h .= '</dl>';

					} else {
						$h .= '/';
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= $this->getDetailWork($eTask, $cPlace);

		return $h;

	}

	/**
	 * Ce que l'intervention a posé, selon son action : la densité d'un semis ou d'une plantation, pour chaque
	 * culture, ou l'apport d'une fertilisation ou d'un traitement sur une série.
	 */
	public function getDetailWork(Task $eTask, \Collection $cPlace): string {

		$h = '';

		if(in_array($eTask['action']['fqn'], [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION])) {

			foreach($eTask['cCultivation'] as $eCultivation) {

				$eTaskSelected = (clone $eTask)->merge([
					'cultivation' => $eCultivation,
					'plant' => $eCultivation['plant']
				]);

				$h .= match($eTaskSelected['action']['fqn']) {

					ACTION_SEMIS_DIRECT => $this->displaySowing($eTaskSelected),
					ACTION_SEMIS_PEPINIERE => $this->displayYoungPlant($eTaskSelected),
					ACTION_PLANTATION => $this->displayPlanting($eTaskSelected),
					default => ''

				};

			}

		}

		if($eTask['series']->notEmpty()) {

			$h .= match($eTask['action']['fqn']) {

				ACTION_FERTILISATION => $this->displayFertilizer($eTask, $cPlace),
				ACTION_TRAITEMENT => $this->displayTreatment($eTask, $cPlace),
				default => ''

			};

		}

		return $h;

	}

	/**
	 * Les couples `dt` et `dd` de la présentation.
	 */
	protected function getDetailTerms(Task $eTask, \Collection $cUser): string {

		$h = '';

		if($eTask['series']->notEmpty()) {
			$h .= '<dt>'.s("Série").'</dt>';
			$h .= '<dd>';
				$h .= SeriesUi::link($eTask['series']);
			$h .= '</dd>';
		}

		$h .= $this->getDetailSchedule($eTask);
		$h .= $this->getDetailAssigned($eTask, $cUser);
		$h .= $this->getDetailRepeat($eTask);

		$h .= '<dt>'.s("Créée").'</dt>';
		$h .= '<dd title="'.s("Plus précisément à {value}", \util\DateUi::numeric($eTask['createdAt'], \util\DateUi::TIME)).'">';
			$h .= s("{date} par {user}", ['date' => \util\DateUi::numeric($eTask['createdAt'], \util\DateUi::DATE), 'user' => $eTask['createdBy']->getName()]);
		$h .= '</dd>';

		return $h;

	}

	/**
	 * Les informations de la fiche d'une intervention du planning (`\planning\TaskDetailUi::getRecord()`),
	 * indexées par la propriété qui les porte, chacune seulement si elle a une valeur : la série, les dates, puis
	 * ce que la présentation de `/tache/{id}` range dans sa colonne « Intervention », moins la création, que son
	 * historique porte, puis les intrants et le matériel, et la description en dernier : un texte de plusieurs
	 * lignes, qui éloignerait de la tête les valeurs courtes posées après lui. L'affectation n'y vient que si le
	 * tableau de l'équipe ne la porte pas (`$assign`).
	 */
	public function getDetailPanelItems(Task $eTask, \Collection $cUser, bool $assign): array {

		$eTask->expects(['cTool', 'cProduct']);

		$items = [];

		if($eTask['series']->notEmpty()) {
			$items['series'] = ['label' => s("Série"), 'value' => SeriesUi::link($eTask['series'])];
		}

		$items += $this->getDetailScheduleItems($eTask);

		if($assign) {
			$items += $this->getDetailAssignedItems($eTask, $cUser);
		}

		$items += $this->getDetailRepeatItems($eTask);

		if($eTask['cProduct']->notEmpty()) {
			$items['cProduct'] = [
				'label' => ($eTask['action']['fqn'] === ACTION_TRAITEMENT ? s("Produit") : s("Intrants")),
				'value' => new \work\ToolUi()->getList($eTask['cProduct'])
			];
		}

		if($eTask['cTool']->notEmpty()) {
			$items['cTool'] = ['label' => s("Matériel"), 'value' => new \work\ToolUi()->getList($eTask['cTool'])];
		}

		if($eTask['description'] !== NULL) {
			$items['description'] = ['label' => s("Description"), 'value' => $this->getDescription($eTask)];
		}

		return $items;

	}

	/**
	 * Les informations de la présentation de `/tache/{id}`, en couples `dt` et `dd`.
	 */
	protected function getDetailTermsOf(array $items): string {

		$h = '';

		foreach($items as $item) {
			$h .= '<dt>'.$item['label'].'</dt>';
			$h .= '<dd>'.$item['value'].'</dd>';
		}

		return $h;

	}

	protected function getDetailAssigned(Task $eTask, \Collection $cUser): string {
		return $this->getDetailTermsOf($this->getDetailAssignedItems($eTask, $cUser));
	}

	protected function getDetailAssignedItems(Task $eTask, \Collection $cUser): array {

		if(
			$cUser->count() <= 1 or
			$eTask['plannedUsers'] === []
		) {
			return [];
		}

		$value = '';

		foreach($eTask['plannedUsers'] as $user) {

			if($cUser->offsetExists($user)) {

				$eUser = $cUser[$user];
				$value .= ' '.\user\UserUi::getVignette($eUser, '1rem');

			}

		}

		return ['plannedUsers' => ['label' => s("Affectée"), 'value' => $value]];

	}

	protected function getDetailRepeat(Task $eTask): string {
		return $this->getDetailTermsOf($this->getDetailRepeatItems($eTask));
	}

	protected function getDetailRepeatItems(Task $eTask): array {

		if($eTask['repeat']->empty()) {
			return [];
		}

		return ['repeat' => ['label' => s("Répétée"), 'value' => RepeatUi::getSequence($eTask['repeat'])]];

	}

	/**
	 * Quand l'intervention est planifiée et quand elle est réalisée, en couples `dt` et `dd`.
	 */
	protected function getDetailSchedule(Task $eTask): string {
		return $this->getDetailTermsOf($this->getDetailScheduleItems($eTask));
	}

	/**
	 * Quand l'intervention est planifiée (`plannedWeek`) et quand elle est réalisée (`doneWeek`) : le jour quand il
	 * est connu, la semaine sinon. Une intervention sans semaine n'a rien.
	 */
	protected function getDetailScheduleItems(Task $eTask): array {

		$items = [];

		if($eTask['plannedDate'] !== NULL) {
			$items['plannedWeek'] = ['label' => s("Planifiée"), 'value' => \util\DateUi::numeric($eTask['plannedDate'])];
		} else if($eTask['plannedWeek'] !== NULL) {
			$items['plannedWeek'] = ['label' => s("Planifiée"), 'value' => $this->getDetailWeek($eTask['plannedWeek'])];
		}

		if($eTask['doneDate'] !== NULL) {
			$items['doneWeek'] = ['label' => s("Réalisée"), 'value' => \util\DateUi::numeric($eTask['doneDate'])];
		} else if($eTask['doneWeek'] !== NULL) {
			$items['doneWeek'] = ['label' => s("Réalisée"), 'value' => $this->getDetailWeek($eTask['doneWeek'])];
		}

		return $items;

	}

	protected function getDetailWeek(string $week): string {
		return s("Semaine {value}", week_number($week)).'<span class="task-item-presentation-week-days">'.\util\DateUi::weekToDays($week).'</span>';
	}

	protected function getDetailHeader(Task $eTask): string {

		$h = '<div class="task-item-header">';
			if($eTask['description'] !== NULL) {
				$h .= $this->getDescription($eTask);
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les gestes de la tête d'une intervention : l'affecter, puis la roue. Le panneau du planning affecte dans
	 * le tableau de son « Temps de travail » et n'en garde ici que la roue (`$assign`).
	 */
	public function getDetailActions(Task $eTask, \Collection $cUser, bool $assign = TRUE, ?string $origin = NULL): string {

		$h = '';

		if(
			$assign and
			$eTask->isTodo() and
			$eTask['plannedWeek'] !== NULL
		) {

			$h .= '<a data-dropdown="bottom-end" class="btn btn-secondary dropdown-toggle">'.($eTask['plannedUsers'] ? \Asset::icon('people-fill') : \Asset::icon('person-fill-add')).'</a>';
			$h .= '<div class="dropdown-list bg-secondary">';

				$h .= '<div class="dropdown-title">'.s("Affecter à...").'</div>';

				foreach($cUser as $eUser) {

					if(\hr\Presence::isWeekPresent($eUser['cPresence'], $eTask['plannedWeek'])->empty()) {
						continue;
					}

					$has = in_array($eUser['id'], $eTask['plannedUsers']);

					$h .= '<a data-ajax="/series/task:doUpdateUserCollection" post-ids="'.$eTask['id'].'" post-user="'.$eUser['id'].'" post-action="'.($has ? 'delete' : 'add').'" post-reload="layer" class="dropdown-item">';
						$h .= $has ? \Asset::icon('x-lg') : \Asset::icon('plus-lg');
						$h .= '  '.\user\UserUi::getVignette($eUser, '1.5rem').'  '.$eUser->getName();
					$h .= '</a>';

				}

			$h .= '</div>';

		}

		$update = $this->getUpdate($eTask, 'btn-color-primary', $origin);

		if($update !== '') {
			$h .= ' '.$update;
		}

		return $h;

	}

	public function getHarvestDelete(Task $eTask, string $date, float $value, ?string $class = NULL): string {

		$h = '<a data-ajax="/series/task:doUpdateHarvestCollection" post-ids="'.$eTask['id'].'" post-harvest-date="'.$date.'" post-harvest-more="'.($value * -1).'" post-harvest-unit="'.$eTask['harvestUnit'].'" post-source="delete"';
		if($class !== NULL) {
			$h .= ' class="'.$class.'"';
		}
		$h .= ' title="'.s("Supprimer cette ligne").'" data-confirm="'.s("Confirmer la suppression de la ligne ?").'">'.\Asset::icon('trash').'</a>';

		return $h;

	}

	public function getDetailComments(Task $eTask, \Collection $cComment): string {

		$h = '<div class="util-title">';
			$h .= '<h3>'.s("Commentaires").'</h3>';
			$h .= '<div>'.$this->getCommentCreate($eTask).'</div>';
		$h .= '</div>';

		if($cComment->notEmpty()) {

			$h .= '<div>';
				$h .= new CommentUi()->getList($cComment, TRUE, TRUE);
			$h .= '</div>';
			$h .= '<br/>';

		}

		return $h;

	}

	/**
	 * Les notes liées, écartées de « Commentaires » au-dessus, dont le titre reste seul quand
	 * l'intervention n'en a aucun.
	 */
	protected function getDetailNotes(Task $eTask, \Collection $cNoteLinked): string {

		if($cNoteLinked->empty()) {
			return '';
		}

		return '<div class="mt-2">'.new \note\NoteUi()->getLinked($eTask['structure'], $cNoteLinked, withTargets: TRUE).'</div>';

	}

	public function getCommentCreate(Task $eTask): string {

		if(new \series\Comment(['structure' => $eTask['structure']])->canCreate()) {
			return '<a href="/series/comment:createCollection?ids[]='.$eTask['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Nouveau commentaire").'</a>';
		} else {
			return '';
		}

	}

	public function getDetailPlaceAndPhotos(Task $eTask, \Collection $cPlace, \Collection $cPhoto): string {

		$h = '';

		if($eTask['series']->notEmpty()) {

			if($cPlace->empty() === FALSE) {

				$h .= '<h3>'.s("Assolement").'</h3>';

				$h .= '<div class="util-overflow-md">';
					$h .= new SeriesUi()->getPlace('series', $eTask['series'], $cPlace);
				$h .= '</div>';

				$h .= '<br/>';

			}

		} else if($eTask->acceptSoil()) {

			$h .= '<div class="util-title">';
				$h .= '<h3>'.s("Assolement").'</h3>';
				if($eTask->canWrite()) {
					$h .= '<a href="/series/place:update?task='.$eTask['id'].'&close=reloadIgnoreCascade" class="btn '.($cPlace->empty() ? 'btn-outline-primary' : 'btn-color-primary').'">';
						if($cPlace->empty()) {
							$h .= \Asset::icon('plus-circle').' '.s("Assoler");
						} else {
							$h .= \Asset::icon('gear-fill');
						}
					$h .= '</a>';
				}
			$h .= '</div>';

			if($cPlace->empty() === FALSE) {

				$h .= '<div class="util-overflow-md">';
					$h .= new SeriesUi()->getPlace('task', $eTask, $cPlace);
				$h .= '</div>';
				$h .= '<br/>';

			}

		}


		if(
			$cPhoto->notEmpty() or
			$eTask->canWrite()
		) {

			$h .= '<div class="util-title">';
				$h .= '<h3 id="scroll-photos">'.s("Photos").'</h3>';

				if($eTask->canWrite()) {

					$h .= '<div data-media="gallery" post-task="'.$eTask['id'].'">';
						$h .= new \media\GalleryUi()->getDropdownLinks(
							\Asset::icon('plus-circle').' <span>'.s("Nouvelle photo").'</span>',
							'btn-outline-primary',
							uploadInputAttributes: ['multiple' => 'multiple']
						);
					$h .= '</div>';

				}

			$h .= '</div>';

		}

		if($cPhoto->notEmpty()) {
			$h .= new \gallery\PhotoUi()->getList($cPhoto, NULL, 4);
		}


		return $h;

	}

	public function getTitle(Task $eTask): string {

		if($eTask['plant']->notEmpty()) {
			$title = s("{action} de {plant}", ['action' => encode($eTask['action']['name']), 'plant' => encode($eTask['plant']['name'])]);
		} else {
			$title = encode($eTask['action']['name']);
		}

		$title .= $this->getTaskComplement($eTask);

		return $title;

	}

	public function getOne(Task $eTask, \Collection $cPlace, \Collection $cPhoto, \Collection $cUser, \Collection $cComment, \Collection $cNoteLinked): \Panel {

		return new \Panel(
			id: 'panel-task',
			title: $this->getTitle($eTask),
			body: $this->getDetail($eTask, $cPlace, $cPhoto, $cUser, $cComment, $cNoteLinked),
			close: 'reloadOnCascade'
		);

	}

	public function displayByAction(\work\Action $eAction, \Collection $cTask): \Panel {

		$hasTools = $cTask->contains(fn($eTask) => $eTask['tools']);

		$h = '';

		if($cTask->empty()) {
			$h .= '<div class="util-empty">'.s("Aucune intervention de cette nature à afficher cette semaine.").'</div>';
		} else {

			if($hasTools) {
				$h .= '<h3>'.s("Répartition par matériel").'</h3>';
				$h .= $this->getListByTool($eAction, $cTask);
			} else {
				$h .= '<div class="util-empty">'.s("Vous n'engagez pas de matériel pour cette intervention cette semaine.").'</div>';
			}

		}

		return new \Panel(
			id: 'panel-task-tools',
			title: encode($eAction['name']),
			body: $h
		);

	}

	protected function getListByTool(\work\Action $eAction, \Collection $cTask): string {

		$validTasks = $cTask->find(fn($eTask) => $eTask['series']->notEmpty())->count();

		$h = '';

		if($validTasks !== $cTask->count()) {

			$h .= '<div class="util-info">';
				$h .= s("Le détail par matériel n'est affiché que pour les interventions créées au sein de séries.");
			$h .= '</div>';

		}

		if($validTasks === 0) {
			return $h;
		}

		$targeted = FALSE;

		$cTask = (clone $cTask)->filter(fn($eTask) => $eTask['series']->notEmpty());

		$cTool = new \Collection();

		foreach($cTask as $eTask) {

			$eSeries = $eTask['series'];

			if($eSeries->empty()) {
				continue;
			}

			foreach($eTask['cTool?']() as $eTool) {

				$cTool[$eTool['id']] ??= $eTool->merge([
					'youngPlants' => 0,
					'seeds' => 0,
					'area' => 0,
					'length' => 0,
					'targeted' => FALSE,
				]);

				if(
					in_array($eAction['fqn'], [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION]) and
					$eTask['cCultivation']->notEmpty()
				) {

					foreach($eTask['cCultivation'] as $eCultivation) {

						foreach($eCultivation['cSlice'] as $eSlice) {

							if($eSlice['youngPlants'] !== NULL) {
								$cTool[$eTool['id']]['youngPlants'] += $eSlice['youngPlants'];
							}

							if($eSlice['seeds'] !== NULL) {
								$cTool[$eTool['id']]['seeds'] += $eSlice['seeds'];
							}

						}

					}

				}

				$cTool[$eTool['id']]['area'] += ($eSeries['area'] ?? $eSeries['areaTarget']);
				$cTool[$eTool['id']]['length'] += ($eSeries['length'] ?? $eSeries['lengthTarget']);

				if($eSeries->isTargeted()) {
					$cTool[$eTool['id']]['targeted'] = TRUE;
					$targeted = TRUE;
				}

			}

		}

		$cTool->sort('name', natural: TRUE);

		$h = '<div class="stick-xs">';

			$h .= '<table class="tr-even">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th colspan="2">';
							$h .= s("Matériel");
						$h .= '</th>';
						$h .= '<th class="text-end">';
							$h .= s("Surface");
						$h .= '</th>';
						$h .= '<th class="text-end">';
							$h .= '<span class="hide-xs-down">'.s("Longueur de planche").'</span>';
							$h .= '<span class="hide-sm-up">'.s("Longueur").'</span>';
						$h .= '</th>';
						if($eAction['fqn'] === ACTION_SEMIS_PEPINIERE or $eAction['fqn'] === ACTION_PLANTATION) {
							$h .= '<th class="text-end hide-xxs-down">';
								$h .= s("Plants");
							$h .= '</th>';
						}
						if($eAction['fqn'] === ACTION_SEMIS_PEPINIERE or $eAction['fqn'] === ACTION_SEMIS_DIRECT) {
							$h .= '<th class="text-end hide-xxs-down">';
								$h .= s("Semences");
							$h .= '</th>';
						}
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

				foreach($cTool as $eTool) {

					$h .= '<tr>';
						$h .= '<td>';
							$h .= \work\ToolUi::getVignette($eTool, '4rem', '3rem').' '.\work\ToolUi::link($eTool);
						$h .= '</td>';
						$h .= '<td class="text-end">';

							switch($eTool['routineName']) {

								case 'tray' :
									if($eTool['youngPlants'] > 0) {
										$h .= '<b>'.(ceil($eTool['youngPlants'] / $eTool['routineValue']['value'] * 10) / 10).'</b>';
									}
									break;

							}

						$h .= '</td>';
						$h .= '<td class="text-end '.($eTool['targeted'] ? 'color-warning' : '').'">';
							if($eTool['area'] > 0) {
								$h .= s("{value} m²", $eTool['area']).($eTool['targeted'] ? '&nbsp;*' : '');
							} else {
								$h .= '?';
							}
						$h .= '</td>';
						$h .= '<td class="text-end '.($eTool['targeted'] ? 'color-warning' : '').'">';
							if($eTool['length'] > 0) {
								$h .= s("{value} mL", $eTool['length']).($eTool['targeted'] ? '&nbsp;*' : '');
							} else {
								$h .= '?';
							}
						$h .= '</td>';
						if($eAction['fqn'] === ACTION_SEMIS_PEPINIERE or $eAction['fqn'] === ACTION_PLANTATION) {
							$h .= '<td class="text-end hide-xxs-down '.($eTool['targeted'] ? 'color-warning' : '').'">';
								if($eTool['youngPlants'] > 0) {
									$h .= $eTool['youngPlants'].($eTool['targeted'] ? '&nbsp;*' : '');
								} else {
									$h .= '?';
								}
							$h .= '</td>';
						}
						if($eAction['fqn'] === ACTION_SEMIS_PEPINIERE or $eAction['fqn'] === ACTION_SEMIS_DIRECT) {
							$h .= '<td class="text-end hide-xxs-down '.($eTool['targeted'] ? 'color-warning' : '').'">';
								if($eTool['seeds'] > 0) {
									$h .= $eTool['seeds'].($eTool['targeted'] ? '&nbsp;*' : '');
								} else {
									$h .= '?';
								}
							$h .= '</td>';
						}
					$h .= '</tr>';

				}

				$h .= '</tbody>';

			$h .= '</table>';

		$h .= '</div>';

		if($targeted) {
			$h .= new CultivationUi()->getWarningTargeted();
		}

		return $h;

	}

	protected function displayAction(Task $eTask, string $content): string {

		$ePlant = $eTask['plant'];

		$h = '<h3>'.\plant\PlantUi::getVignette($ePlant, '2rem').'  '.encode($ePlant['name']).'</h3>';

		if($eTask['cultivation']->notEmpty()) {

			$info = [
				'plant' => \plant\PlantUi::getVignette($ePlant, '1.5rem').' <a href="/plant/plant:update?id='.$ePlant['id'].'">'.encode($ePlant['name']).'</a>'
			];

			switch($eTask['cultivation']['sliceError']) {

				case 'density' :
					$h .= '<div class="util-danger">'.s("Il manque la densité d'implantation de la production pour afficher ces informations.").'</div>';
					break;

				case 'area' :
					$h .= '<div class="util-danger">'.s("Il manque la surface de cette série pour afficher ces informations.").'</div>';
					break;

				case 'seedling' :
					$h .= '<div class="util-danger">'.s("Il manque le choix d'implantation de la production pour afficher ces informations.").'</div>';
					break;

				default :

					switch($eTask['cultivation']['seedling']) {

						case Cultivation::SOWING :
							if($ePlant['seedsSafetyMargin'] !== NULL) {
								$h .= '<div class="util-block-info">';
									$h .= s("Les quantités indiquées incluent la marge de sécurité de {value} % sur vos semis directs de {plant}.", ['value' => $ePlant['seedsSafetyMargin']] + $info);
								$h .= '</div>';
							}
							break;

						case Cultivation::YOUNG_PLANT :
							if($ePlant['plantsSafetyMargin'] !== NULL) {
								$h .= '<div class="util-block-info">';
									$h .= s("Les quantités indiquées incluent la marge de sécurité de {value} % sur vos plants autoproduits de {plant}.", ['value' => $ePlant['plantsSafetyMargin']] + $info);
								$h .= '</div>';
							}
							break;

					}

			}

		}

		$h .= '<div class="task-item-action">';
			$h .= $content;
		$h .= '</div>';

		return $h;

	}

	protected function displayInvalidAction(Task $eTask, string $content): string {

		$h = '<h3>'.encode($eTask['action']['name']).'</h3>';
		$h .= '<div class="task-item-action">';
			$h .= '<div class="util-danger">'.$content.'</div>';
		$h .= '</div>';

		return $h;

	}

	protected function displaySowing(Task $eTask): string {

		if($eTask['cultivation']['seedling'] === Cultivation::YOUNG_PLANT or $eTask['cultivation']['seedling'] === Cultivation::YOUNG_PLANT_BOUGHT) {
			return $this->displayInvalidAction($eTask, s("Ce semis direct est incohérent avec l'implantation que vous avez choisie pour cette production."));
		}

		$h = '<dl class="util-presentation util-presentation-2">';
			$h .= $this->displaySowingPresentation($eTask);
			$h .= $this->displayPlans($eTask);
		$h .= '</dl>';

		return $this->displayAction($eTask, $h);

	}

	protected function displaySowingPresentation(Task $eTask): string {

		$eTask->expects([
			'cultivation' => ['series']
		]);

		$eCultivation = $eTask['cultivation'];
		$eSeries = $eCultivation['series'];

		$uiCrop = new \sequence\CropUi();

		$h = $uiCrop->getPresentationDistance($eSeries, $eCultivation);
		$h .= $this->getPresentationSize($eSeries);
		$h .= $this->getPresentationSeeds($eTask);
		$h .= $this->getPresentationSeedling($eTask);

		return $h;

	}

	protected function displayYoungPlant(Task $eTask): string {

		if($eTask['cultivation']['seedling'] === Cultivation::SOWING or $eTask['cultivation']['seedling'] === Cultivation::YOUNG_PLANT_BOUGHT) {
			return $this->displayInvalidAction($eTask, s("Ce semis en pépinière est incohérent avec l'implantation que vous avez choisie pour cette production."));
		}

		$h = '<dl class="util-presentation util-presentation-2">';
			$h .= $this->displayYoungPlantPresentation($eTask);
			$h .= $this->displayYoungPlantTools($eTask);
		$h .= '</dl>';

		if(
			$eTask['cultivation']['cSlice']->contains(fn($eSlice) => $eSlice['youngPlants'] !== NULL) and
			$eTask['series']->isTargeted()
		) {
			$h .= '<br/>';
			$h .= new CultivationUi()->getWarningTargeted();
		}

		return $this->displayAction($eTask, $h);

	}

	protected function displayYoungPlantPresentation(Task $eTask): string {

		$eTask->expects([
			'cultivation' => ['series']
		]);

		$h = $this->getPresentationYoungPlants($eTask);
		$h .= $this->getPresentationSeeds($eTask);
		$h .= $this->getPresentationSeedling($eTask);

		return $h;

	}

	protected function displayYoungPlantTools(Task $eTask): string {

		$cSlice = $eTask['cultivation']['cSlice'];

		$youngPlants = $cSlice->sum('youngPlants');

		if($youngPlants === 0) {
			return '';
		}

		$h = '';

		foreach($eTask['cTool?']() as $eTool) {

			if(
				$eTool['routineName'] === 'tray' and
				$eTool['routineValue']['value']
			) {

				$h .= '<dt>'.s("Matériel").'</dt>';
				$h .= '<dd>';
					$h .= '<b>';
						$h .= encode($eTool['name']);
					$h .= '</b>';

					$h .= '<div class="task-presentation-seedling-tools">';

						$sum = 0;

						foreach($cSlice as $eSlice) {

							$eVariety = $eSlice['variety'];
							$trays = $eSlice['youngPlants'] ? $eSlice['youngPlants'] / $eTool['routineValue']['value'] : NULL;

							$h .= '<div>';
								$h .= $eVariety->empty() ? '<i>'.s("Variété non renseignée").'</i>' : encode($eVariety['name']);
							$h .= '</div>';
							$h .= '<div class="text-end '.($eTask['series']->isTargeted() ? 'color-warning' : '').'">';
								if($trays !== NULL) {
									$sum += $trays;
									$h .= (round($trays, 1) !== $trays ? '~ ' : '').round($trays, 1).' '.($eTask['series']->isTargeted() ? '*' : '');
								} else {
									$h .= '?';
								}
							$h .= '</div>';
						}

						if($cSlice->count() >= 2 and $sum > 0) {
							$h .= '<div><b>'.s("Total").'</b></div>';
							$h .= '<div class="text-end '.($eTask['series']->isTargeted() ? 'color-warning' : '').'">';
								$h .= (round($sum, 1) !== $sum ? '~ ' : '').round($sum, 1).' '.($eTask['series']->isTargeted() ? '*' : '');
							$h .= '</div>';
						}

					$h .= '</div>';
				$h .= '</dd>';

			}
		}

		return $h;

	}

	protected function displayPlans(Task $eTask): string {

		$plans = new \sequence\DensityPlanAiUi()->getFigures($eTask['cultivation']['densityPlans']);

		if($plans === '') {
			return '';
		}

		return '<dt class="task-item-plans">'.s("Plan").'</dt><dd class="util-presentation-fill task-item-plans">'.$plans.'</dd>';

	}

	protected function displayPlanting(Task $eTask): string {

		if($eTask['cultivation']['seedling'] === Cultivation::SOWING) {
			return $this->displayInvalidAction($eTask, s("Cette plantation est incohérente avec l'implantation en semis direct que vous avez choisie pour cette production."));
		}

		$h = '<dl class="util-presentation util-presentation-2">';
			$h .= $this->displayPlantingPresentation($eTask);
			$h .= $this->displayPlans($eTask);
		$h .= '</dl>';

		return $this->displayAction($eTask, $h);


	}

	public function displayTreatment(Task $eTask, \Collection $cPlace): string {

		$eTask->expects(['eToolPhyto', 'cToolSprayer']);

		if($eTask['eToolPhyto']->empty()) {
			return '';
		}

		$eSeries = $eTask['series'];
		$eToolPhyto = $eTask['eToolPhyto'];
		$treatment = $eTask['treatmentShown'];
		$unit = $treatment['unit'];
		$spray = $treatment['spray'];

		$h = '<div id="task-treatment">';

			$h .= '<h3>'.s("Traitement avec {value}", encode($eToolPhyto['name'])).'</h3>';

			if($eTask->canWrite()) {

				$form = new \util\FormUi();

				$h .= $form->openAjax('/series/task:doUpdateTreatment', ['class' => 'task-treatment-settings', 'data-action' => 'task-treatment']);

					$h .= $form->hidden('id', $eTask);

					$h .= $form->inputGroup(
						$form->addon(s("Dose")).
						$form->number('treatment[dose]', $treatment['dose'], ['step' => 0.001, 'min' => 0]).
						$form->select('treatment[unit]', array_combine(\work\RoutineLib::PHYTO_UNITS, \work\RoutineLib::PHYTO_UNITS), $unit, ['required' => TRUE])
					);

					if($spray) {

						$h .= $form->inputGroup(
							$form->addon(s("Bouillie")).
							$form->number('treatment[water]', $treatment['water'], ['min' => 1]).
							$form->addon(s("L/ha"))
						);

						if($eTask['cToolSprayer']->notEmpty()) {

							$sprayers = [0 => s("Sans pulvérisateur")];

							foreach($eTask['cToolSprayer'] as $eToolSprayer) {
								$sprayers[$eToolSprayer['id']] = s("{name}, {value} L", ['name' => $eToolSprayer['name'], 'value' => \util\TextUi::number($eToolSprayer['routineValue']['capacity'], NULL)]);
							}

							$h .= $form->select('sprayer', $sprayers, $treatment['eToolSprayer']->empty() ? 0 : $treatment['eToolSprayer']['id'], ['required' => TRUE, 'data-action' => 'task-treatment-sprayer']);

						}

					}

					$h .= $form->submit(s("Enregistrer"), ['class' => 'btn btn-primary hide', 'data-treatment' => 'save']);

				$h .= $form->close();

			} else {

				$h .= '<p>';
					$h .= ($treatment['dose'] === NULL) ? s("Dose non renseignée") : s("Dose de {value}", \util\TextUi::number($treatment['dose'], NULL).' '.$unit);
					if($treatment['water'] !== NULL) {
						$h .= ', '.s("bouillie de {value} L/ha", $treatment['water']);
					}
				$h .= '</p>';

			}

			$h .= '<div class="util-overflow-xs">';
				$h .= '<div class="task-fertilizer-grid'.($spray ? ' task-treatment-grid-spray' : '').'">';

					$h .= '<div class="util-table-header">'.s("Parcelle").'</div>';
					$h .= '<div class="util-table-header">'.s("Jardin").'</div>';
					$h .= '<div class="util-table-header">';
						if($eSeries['use'] === Series::BED) {
							$h .= s("Planche");
						}
					$h .= '</div>';
					$h .= '<div class="util-table-header text-end">'.s("Surface").'</div>';
					$h .= '<div class="util-table-header text-end">'.s("Produit").'</div>';
					if($spray) {
						$h .= '<div class="util-table-header text-end">'.s("Bouillie").'</div>';
					}

					foreach($cPlace as $ePlace) {

						$h .= '<div class="task-fertilizer-grid-zone">'.encode($ePlace['zone']['name']).'</div>';
						$h .= '<div class="task-fertilizer-grid-plot">'.encode($ePlace['plot']['name']).'</div>';
						$h .= '<div class="task-fertilizer-grid-bed">';
							if($eSeries['use'] === Series::BED) {
								$h .= ($ePlace['bed']['name'] !== NULL) ? encode($ePlace['bed']['name']) : s("Temporaire");
							}
						$h .= '</div>';
						$h .= '<div class="task-fertilizer-grid-size util-unit">'.s("{value} m²", round($ePlace['area'])).'</div>';
						$h .= '<div class="task-fertilizer-grid-size">'.self::getTreatmentQuantity($ePlace['treatmentQuantity'], $unit).'</div>';
						if($spray) {
							$h .= '<div class="task-fertilizer-grid-size">'.$this->getTreatmentWater($ePlace['treatmentWater']).'</div>';
						}

					}

					if($cPlace->count() > 1) {

						$h .= '<div style="grid-column: span 3"></div>';
						$h .= '<div class="task-fertilizer-grid-size util-unit task-fertilizer-grid-total">'.s("{value} m²", round($treatment['area'])).'</div>';
						$h .= '<div class="task-fertilizer-grid-size task-fertilizer-grid-total">'.self::getTreatmentQuantity($treatment['quantity'], $unit).'</div>';
						if($spray) {
							$h .= '<div class="task-fertilizer-grid-size task-fertilizer-grid-total">'.$this->getTreatmentWater($treatment['waterTotal']).'</div>';
						}

					}

				$h .= '</div>';
			$h .= '</div>';

			if($treatment['tanks'] !== []) {

				$h .= '<h4 class="mt-1">'.s("À préparer").'</h4>';

				$h .= '<div class="util-table task-treatment-tanks">';
					$h .= '<div class="util-table-head">';
						$h .= '<div>'.s("Cuve").'</div>';
						$h .= '<div class="text-end">'.s("Eau").'</div>';
						$h .= '<div class="text-end">'.encode($eToolPhyto['name']).'</div>';
					$h .= '</div>';
					foreach($treatment['tanks'] as $position => $tank) {
						$h .= '<div class="util-table-row">';
							$h .= '<div>'.($position + 1).'</div>';
							$h .= '<div class="text-end">'.$this->getTreatmentWater($tank['water']).'</div>';
							$h .= '<div class="text-end">'.self::getTreatmentQuantity($tank['quantity'], $unit).'</div>';
						$h .= '</div>';
					}
				$h .= '</div>';

			}

			$h .= '<br/>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La dose est par hectare, en kg ou en L : une quantité sous l'unité se lit en g ou en mL.
	 */
	public static function getTreatmentQuantity(?float $quantity, string $unit): string {

		if($quantity === NULL) {
			return '';
		}

		$base = match($unit) {
			'kg/ha' => ['kg', 'g'],
			'L/ha' => ['L', 'mL'],
		};

		if($quantity < 1) {
			return \util\TextUi::number((int)round($quantity * 1000)).' '.$base[1];
		} else {
			return \util\TextUi::number(round($quantity, 2), NULL).' '.$base[0];
		}

	}

	protected function getTreatmentWater(?float $water): string {

		if($water === NULL) {
			return '';
		}

		return s("{value} L", \util\TextUi::number(round($water, 1), NULL));

	}

	protected function displayFertilizer(Task $eTask, \Collection $cPlace): string {

		if(
			$eTask['fertilizer'] === NULL or
			array_filter($eTask['fertilizer']) === []
		) {
			return '';
		}

		$eSeries = $eTask['series'];
		$cTool = $eTask['cProduct?']()->find(fn($eTool) => $eTool['routineName'] === 'fertilizer');

		if($cTool->empty()) {
			return '';
		}

		$form = new \util\FormUi();

		// Liste des N, P, K de la tâche
		$elementsValues = [];

		foreach(\work\RoutineUi::getListFertilizer() as $key => $label) {
			if($eTask['fertilizer'][$key] !== NULL) {
				$elementsValues[$key] = $eTask['fertilizer'][$key].' '.$label;
			}
		}

		$elementSelected = GET('element', array_keys($elementsValues), default: array_key_first($elementsValues));

		if(count($elementsValues) > 1) {

			$fieldElement = $form->select(NULL, $elementsValues, $elementSelected, attributes: [
				'data-action' => 'task-fertilizer-element',
				'class' => 'task-fertilizer-field',
				'onchange' => '',
				'required' => TRUE
			]);

		} else {
			$fieldElement = first($elementsValues);
			$fieldElement .= $form->hidden(NULL, $elementSelected, ['data-action' => 'task-fertilizer-element']);
		}

		// Liste des fertilisants
		$toolsValues = [];

		foreach($cTool as $eTool) {

			$name = encode($eTool['name']);

			[$major, $minor] = new \work\RoutineUi()->getSeparateFertilizer($eTool['routineValue'], ' ');

			if($minor or $major) {

				$name .= '  →  ';
				$name .= implode(' / ', $major);
				if($major and $minor) {
					$name .= ' + ';
				}
				$name .= implode(' / ', $minor);

			}

			$toolsValues[$eTool['id']] = $name;

		}

		$default = array_key_first($toolsValues);

		$toolSelected = (int)GET('tool', array_keys($toolsValues), default: $default);
		$eToolSelected = $cTool->find(fn($eTool) => $eTool['id'] === $toolSelected, limit: 1);

		if(count($toolsValues) > 1) {

			$fieldTool = $form->select(NULL, $toolsValues, $toolSelected, attributes: [
				'data-action' => 'task-fertilizer-tool',
				'class' => 'task-fertilizer-field',
				'placeholder' => 'Choisissez un intrant'
			]);

		} else {
			$fieldTool = $form->select(NULL, $toolsValues, $toolSelected, attributes: [
				'data-action' => 'task-fertilizer-tool',
				'class' => 'task-fertilizer-field',
				'disabled'
			]);
		}

		$elementExpected = $eTask['fertilizer'][$elementSelected];
		$elementPart = $eToolSelected['routineValue'][$elementSelected];

		$h = '<h3>';
			$h .= s("Fertilisation pour {value}", $fieldElement);
		$h .= '</h3>';

		$h .= '<div class="mb-1">';
			$h .= $form->inputGroup(
				$form->addon(s("Intrant")).
				$fieldTool
			);
		$h .= '</div>';

		$h .= '<div class="util-overflow-xs">';
			$h .= '<div class="task-fertilizer-grid">';

				$h .= '<div class="util-table-header">'.s("Parcelle").'</div>';
				$h .= '<div class="util-table-header">'.s("Jardin").'</div>';
				$h .= '<div class="util-table-header">';
					if($eSeries['use'] === Series::BED) {
						$h .= s("Planche");
					}
				$h .= '</div>';
				$h .= '<div class="util-table-header text-end">'.s("Surface").'</div>';
				$h .= '<div class="util-table-header">'.s("Intrant").'</div>';

				foreach($cPlace as $ePlace) {

					$h .= '<div class="task-fertilizer-grid-zone">'.encode($ePlace['zone']['name']).'</div>';
					$h .= '<div class="task-fertilizer-grid-plot">'.encode($ePlace['plot']['name']).'</div>';
					$h .= '<div class="task-fertilizer-grid-bed">';
						if($eSeries['use'] === Series::BED) {

							if($ePlace['bed']['name'] !== NULL) {
								$h .= encode($ePlace['bed']['name']);
							} else {
								$h .= s("Temporaire");
							}

						}
					$h .= '</div>';
					$h .= '<div class="task-fertilizer-grid-size util-unit">';
						$h .= s("{value} m²", round($ePlace['area']));
					$h .= '</div>';
					$h .= '<div class="task-fertilizer-grid-weight">';

						if($elementPart === NULL) {
							$h .= s("Pas de {value} dans l'intrant", \work\RoutineUi::getListFertilizer()[$elementSelected]);
						} else {
							$h .= s("{value} kg", round($ePlace['area'] * $elementExpected / 10000 / ($elementPart / 100), 1));
						}

					$h .= '</div>';

				}

				if($cPlace->count() > 1) {
					$h .= '<div style="grid-column: span 3"></div>';

					$h .= '<div class="task-fertilizer-grid-size util-unit task-fertilizer-grid-total">';
						$h .= s("{value} m²", round($eSeries['area']));
					$h .= '</div>';
					$h .= '<div class="task-fertilizer-grid-weight task-fertilizer-grid-total">';

						if($elementPart === NULL) {
							$h .= s("Pas de {value} dans l'intrant", \work\RoutineUi::getListFertilizer()[$elementSelected]);
						} else {
							$h .= s("{value} kg", round($eSeries['area'] * $elementExpected / 10000 / ($elementPart / 100), 1));
						}

					$h .= '</div>';

				}

			$h .= '</div>';
		$h .= '</div>';

		$h .= '<br/>';

		return $h;


	}

	protected function displayPlantingPresentation(Task $eTask): string {

		$eTask->expects([
			'cultivation' => ['series']
		]);

		$eCultivation = $eTask['cultivation'];
		$eSeries = $eCultivation['series'];

		$uiCrop = new \sequence\CropUi();

		$h = $uiCrop->getPresentationDistance($eSeries, $eCultivation);
		$h .= $this->getPresentationSize($eSeries);
		$h .= $this->getPresentationYoungPlants($eTask);

		return $h;

	}



	protected function getPresentationSize(Series $eSeries): string {

		if($eSeries['length'] !== NULL) {
			$size = s("{value} mL", $eSeries['length']);
		} else if($eSeries['area'] > 0) {
			$size = s("{value} m²", $eSeries['area']);
		} else {
			$size = NULL;
		}


		$h = '<dt>'.s("Surface").'</dt>';
		$h .= '<dd>'.$size.'</dd>';

		return $h;

	}

	protected function getPresentationYoungPlants(Task $eTask): string {

		$h = '<dt>'.s("Plants").'</dt>';
		$h .= '<dd>'.$this->getPresentationSeedlingList($eTask, 'youngPlants').'</dd>';

		return $h;

	}

	protected function getPresentationSeeds(Task $eTask): string {

		$h = '<dt>'.s("Semences").'</dt>';
		$h .= '<dd>'.$this->getPresentationSeedlingList($eTask, 'seeds').'</dd>';

		return $h;

	}

	protected function getPresentationSeedling(Task $eTask): string {

		$h = '';

		if($eTask['cultivation']['seedling'] === Cultivation::YOUNG_PLANT) {
			$h .= '<dt>'.s("Semis").'</dt>';
			$h .= '<dd>'.p("{value} graine / plant", "{value} graines / plant", $eTask['cultivation']['seedlingSeeds']).'</dd>';
		} else if($eTask['cultivation']['seedling'] === Cultivation::SOWING and $eTask['cultivation']['seedlingSeeds'] > 1) {
			$h .= '<dt>'.s("Semis").'</dt>';
			$h .= '<dd>'.p("{value} graine / trou", "{value} graines / trou", $eTask['cultivation']['seedlingSeeds']).'</dd>';
		}

		return $h;

	}

	protected function getPresentationSeedlingList(Task $eTask, string $property): string {

		$cSlice = $eTask['cultivation']['cSlice'];
		$sum = 0;

		$list = '<div class="task-presentation-seedling">';

		foreach($cSlice as $eSlice) {

			$eVariety = $eSlice['variety'];

			$list .= '<div>';
				$list .= $eVariety->empty() ? '<i>'.s("Variété non renseignée").'</i>' : encode($eVariety['name']);
			$list .= '</div>';
			$list .= '<div class="text-end">';
				if($eSlice[$property] !== NULL) {
					$sum += $eSlice[$property];
					$list .= $eTask['series']->isTargeted() ? '<span class="color-warning">'.$eSlice[$property].' *</span>' : $eSlice[$property];
				} else {
					$list .= '?';
				}
			$list .= '</div>';
			$list .= '<div>';
				if(
					$eSlice[$property] !== NULL and
					$eVariety->notEmpty()
				) {
					$list .= match($property) {
						'seeds' => ($eVariety['weightSeed1000'] !== NULL) ? '<small class="color-muted">('.\plant\VarietyUi::getSeedsWeight1000($eVariety, $eSlice[$property]).')</small>' : '',
						'youngPlants' => ($eVariety['numberPlantKilogram'] !== NULL) ? '<small class="color-muted">('.\plant\VarietyUi::getPlantsWeight($eVariety, $eSlice[$property]).')</small>' : ''
					};
				}
			$list .= '</div>';
		}

		if($cSlice->count() >= 2 and $sum > 0) {
			$list .= '<div><b>'.s("Total").'</b></div>';
			$list .= '<div class="text-end '.($eTask['series']->isTargeted() ? 'color-warning' : '').'">';
				$list .= $sum.' '.($eTask['series']->isTargeted() ? '*' : '');
			$list .= '</div>';
		}

		$list .= '</div>';

		return $list;

	}

	public function getPlace(Task $eTask): string {

		if($eTask['series']->empty() === FALSE) {
			return s("Série {value}", SeriesUi::link($eTask['series'], newTab: TRUE));
		} else if($eTask['cccPlace']->notEmpty()) {
			return new CultivationUi()->displayPlaces(Series::BED, $eTask['cccPlace']);
		} else {
			return '';
		}

	}

	public function createFromOneSeries(Task $eTask, Series $eSeries): \Panel {

		$panel = $this->createFromSeries($eTask, FALSE, function(\util\FormUi $form) use($eTask, $eSeries) {
			return $form->hidden('series[]', $eSeries['id']);
		});

		$panel->subTitle = SeriesUi::getPanelHeader($eSeries);

		return $panel;

	}

	public function createFromAllSeries(Task $eTask, \Collection $cSeries): \Panel {

		return $this->createFromSeries($eTask, TRUE, function(\util\FormUi $form) use($eTask, $cSeries) {

			$series = '<div id="task-create-plant">';
				$series .= $form->dynamicField($eTask, 'plantsFilter');
			$series .= '</div>';

			// Dans le cas des récoltes, on affiche les séries uniquement si on a filtré sur une espèce
			if($this->canDisplayMultipleSeries($eTask)) {
				$series .= $this->getSeriesListField($form, $eTask, $cSeries);
			}

			return $this->getCreateSeasonGroup($form, $eTask).$form->group(
				p("Série", "Séries", $cSeries->count()),
				$series,
				['data-wrapper' => 'series']
			);

		});

	}

	protected function canDisplayMultipleSeries(Task $eTask): bool {

		// Dans le cas des récoltes, on affiche les séries uniquement si on a filtré sur une espèce
		return (
			$eTask['action']->empty() or
			$eTask['action']['fqn'] !== ACTION_RECOLTE or
			$eTask['plant']->notEmpty()
		);

	}

	/**
	 * Recherche parmi les séries
	 *
	 * Field checkbox ou hidden
	 */
	public function getSeriesListField(\util\FormUi $form, Task $eTask, \Collection $cSeries): string {

		if($cSeries->empty()) {
			return '<div class="util-warning">'.s("Il n'y a aucune série à afficher pour renseigner une intervention.").'</div>';
		}

		$h = '';

		$h .= '<table id="series-field-table" class="stick-xs">';

			$h .= '<thead>';
				$h .= '<tr>';
					if($cSeries->count() > 1) {
						$h .= '<td id="series-field-filter" class="td-checkbox bg-secondary" title="'.s("Tout cocher / Tout décocher").'">';

							if(
								$eTask['plant']->notEmpty() and
								$cSeries->match(fn($eSeries) => $eSeries['cCultivation']->count() > 1, 1)
							) {

								$h .= '<a class="btn btn-secondary dropdown-toggle" data-dropdown="bottom-start">'.\Asset::icon('check2-square').'</a>';
								$h .= '<div class="dropdown-list bg-secondary">';
									$h .= '<div class="dropdown-title">'.s("Tout cocher ou tout décocher").'</div>';

									$h .= '<label class="dropdown-item">';
										$h .= '<input type="checkbox" '.attr('onclick', 'Task.selectAll(this, null)').'"/>';
										$h .= ' '.s("Interventions partagées");
									$h .= '</label>';

									$h .= '<label class="dropdown-item">';
										$h .= '<input type="checkbox" '.attr('onclick', 'Task.selectAll(this, '.$eTask['plant']['id'].')').'"/>';
										$h .= ' '.s("Interventions sur {value}", \plant\PlantUi::getVignette($eTask['plant'], '1.5rem').' '.encode($eTask['plant']['name']));
									$h .= '</label>';

								$h .= '</div>';

							} else {

								$h .= '<label>';
									$h .= '<input type="checkbox" '.attr('onclick', 'Task.selectAll(this, null)').'"/>';
								$h .= '</label>';

							}

						$h .= '</td>';
					} else {
						$h .= '<th></th>';
					}
					$h .= '<th>'.s("Série").'</th>';
					$h .= '<th></th>';
				$h .= '</tr>';
			$h .= '</thead>';

			foreach($cSeries as $eSeries) {

				$cCultivation = $eSeries['cCultivation'];

				$association = $cCultivation->count() > 1;
				$checked = ($cSeries->count() === 1 and $association === FALSE);

				$places = new CultivationUi()->displayPlaces($eSeries['use'], $eSeries['cccPlace']);

				$h .= '<tbody>';

					$h .= '<tr>';

						$h .= '<td class="td-checkbox">';

							$h .= '<label>';
								$h .= $form->inputCheckbox('series[]', $eSeries['id'], [
									'data-filter-cultivations' => $cCultivation->count(),
									'oninput' => 'Task.selectSeriesCheckbox(this)',
									'onrender' => $checked ? 'Task.selectSeriesCheckbox(this)' : NULL,
									 $checked ? 'checked' : NULL,
									 ($cCultivation->count() > 1) ? 'disabled' : NULL
								]);
							$h .= '</label>';

							if($association === FALSE) {
								$h .= $form->hidden('cultivation['.$eSeries['id'].']', $eSeries['cCultivation']->first()['id'], [
									'data-plant' => $cCultivation->first()['plant']['id']
								]);
							}

						$h .= '</td>';
						$h .= '<td>';

							if($association === FALSE) {

								$h .= '<div class="task-field-vignette">';

									$h .= '<div>';
										$h .= \plant\PlantUi::getVignette($cCultivation->first()['plant'], '2rem');
									$h .= '</div>';
									$h .= '<div>';

							}

							$h .= '<div class="task-field-link">';
								$h .= SeriesUi::link($eSeries, newTab: TRUE);
								if($association === FALSE) {
									$h .= ' '.\sequence\CropUi::start($cCultivation->first(), \work\WorkSetting::$mainActions);
								}
							$h .= '</div>';
							$h .= '<div class="task-field-place">';
								if($places) {
									$h .= $places;
								} else {
									$h .= s("Assolement non renseigné");
								}
							$h .= '</div>';

							if($association === FALSE) {

									$h .= '</div>';
								$h .= '</div>';

							}

						$h .= '</td>';
						$h .= '<td class="task-field-area">';
							if($eSeries['area']) {
								$h .= s("{value} m²", $eSeries['area']);
							} else {
								$h .= '<a href="/series/place:update?series='.$eSeries['id'].($eSeries['mode'] === Series::GREENHOUSE ? '&mode='.Series::GREENHOUSE : '').'" class="btn btn-outline-primary">'.s("Assoler").'</a>';
							}
						$h .= '</td>';
					$h .= '</tr>';

					if($association) {

						$h .= '<tr>';
							$h .= '<td colspan="4" class="series-field-cultivations">';

								$h .= '<label class="series-field-cultivation">';
									$h .= $form->inputRadio('cultivation['.$eSeries['id'].']', '', s("Partagé"), attributes: [
										'checked' => FALSE,
										'oninput' => 'Task.selectCultivationRadio(this)'
									]);
								$h .= '</label>';

								foreach($eSeries['cCultivation'] as $eCultivation) {

									$label = \plant\PlantUi::getVignette($eCultivation['plant'], '2rem').' '.encode($eCultivation['plant']['name']);
									$label .= ' '.\sequence\CropUi::start($eCultivation, \work\WorkSetting::$mainActions);

									$h .= '<label class="series-field-cultivation">';
										$h .= $form->inputRadio('cultivation['.$eSeries['id'].']', $eCultivation['id'], $label, attributes: [
											'data-plant' => $eCultivation['plant']['id'],
											'oninput' => 'Task.selectCultivationRadio(this)',
										]);
									$h .= '</label>';

								}

							$h .= '</td>';
						$h .= '</tr>';

					}

					$h .= '</tbody>';

			}

		$h .= '</table>';

		return $h;

	}

	public function createFromSeries(Task $eTask, bool $multipleSeries, \Closure $series): \Panel {

		$eTask->expects(['structure', 'action', 'plannedWeek', 'doneWeek', 'status']);

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/series/task:doCreateFromSeriesCollection', ['autocomplete' => 'off', 'data-structure' => $eTask['structure']['id']]);

			$h .= $form->hidden('structure', $eTask['structure']['id']);
			$h .= $form->hidden('status', $eTask['status']);

			$h .= $series->call($this, $form);

			if(
				$multipleSeries === FALSE or
				$this->canDisplayMultipleSeries($eTask)
			) {

				$h .= $this->getCreateActionGroup($form, $eTask);

				$h .= '<div id="task-create-variety">';
					$h .= $this->getVarietyGroup($form, $eTask, $eTask['cVariety'], $eTask['varietiesIntersect']);
				$h .= '</div>';

				$h .= '<div id="task-create-size">';
					if($eTask['cSize']->notEmpty()) {
						$h .= $this->getHarvestSizeField($form, $eTask);
					}
				$h .= '</div>';

				$h .= $this->getProductsGroup($form, $eTask);
				$h .= '<div id="task-create-fertilizer">';
					$h .= $this->getFertilizerField($form, $eTask);
				$h .= '</div>';
				$h .= $this->getTreatmentField($form, $eTask);

				$h .= $this->getMethodsGroup($form, $eTask);
				$h .= $this->getToolsGroup($form, $eTask);
				$h .= $this->getTimeGroup($form, $eTask);

				if($eTask['structure']->conf()['time']) {
					$h .= $form->dynamicGroup($eTask, 'timeExpected');
				}

				$h .= $form->dynamicGroup($eTask, 'description');

				$h .= $form->group(
					content: $form->submit(s("Ajouter l'intervention"))
				);

			}

		$h .= $form->close();

		$title = $this->getCreateTitle($eTask);

		return new \Panel(
			id: 'panel-task-create',
			title: $title,
			body: $h
		);

	}

	protected function getCreateSeasonGroup(\util\FormUi $form, Task $e): string {

		$seasons = '';

		for($i = max(\Clock::year() - 1, $e['structure']->conf(\map\Configuration::class)['seasonFirst']); $i <= min(\Clock::year() + 1, $e['structure']->conf(\map\Configuration::class)['seasonLast']); $i++) {

			$action = $e['action']->notEmpty() ? $e['action']['id'] : '';

			$seasons .= '<a href="/series/task:createFromSeries?structure='.$e['structure']['id'].'&season='.$i.'&action='.$action.'&'.http_build_query($e->extracts(['doneWeek', 'plannedWeek', 'status'])).'" class="btn btn-form '.($i === $e['season'] ? 'btn-selected' : '').'">'.$i.'</a> ';

		}

		return $form->group(
			s("Saison"),
			'<div>'.$seasons.'</div>'
		);

	}

	protected function getCreateActionGroup(\util\FormUi $form, Task $e): string {

		$e->expects('action');

		$h = '';

		$eSeries = $e['series'];

		if($eSeries->notEmpty()) {

			$cCultivation = $eSeries['cCultivation'];

			if($cCultivation->count() > 1) {

				$values = [];

				if($e['action']->empty() or $e['action']['fqn'] !== ACTION_RECOLTE) {
					$values[NULL] = s("Partagé");
				}

				foreach($cCultivation as $eCultivation) {
					$values[$eCultivation['id']] = \plant\PlantUi::getVignette($eCultivation['plant'], '2rem').' '.encode($eCultivation['plant']['name']);
				}

				$h .= $form->radios('cultivation['.$eSeries['id'].']', $values, $e['cultivation'], attributes: [
					'required' => TRUE,
					'callbackRadioAttributes' => function() {
						return [
							'onclick' => 'Task.createSelectCultivation(this)'
						];
					}
				]);


				$h .= '<br/>';

			} else {
				$h .= $form->hidden('cultivation['.$eSeries['id'].']', $cCultivation->first()['id']);
			}

		}

		if($e['action']->empty()) {

			$h .= $form->dynamicField($e, 'action');

		} else {

			$h .= $form->hidden('action', $e['action']['id'], [
				'data-fqn' => $e['action']['fqn']
			]);
			$h .= '<h3>'.encode($e['action']['name']).'</h3>';
		}

		return $form->group(
			s("Intervention"),
			$h,
			['data-wrapper' => 'action']
		);

	}

	public function createFromScratch(Task $eTask, \Collection $cAction, \Collection $cCategory): \Panel {

		$eTask->expects(['structure', 'series', 'action', 'cultivation', 'category', 'status']);

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/series/task:doCreate', ['id' => 'task-create', 'autocomplete' => 'off']);

			$h .= $form->hidden('status', $eTask['status']);
			$h .= $form->hidden('structure', $eTask['structure']['id']);

			if($eTask['action']->empty()) {

				$h .= $this->getCreateScratchActionGroup($eTask, $cAction, $cCategory);

			} else {

				$h .= $form->hidden('action', $eTask['action']['id']);
				$h .= $form->hidden('category', $eTask['category']['id']);

				if($eTask['category']['fqn'] === CATEGORIE_CULTURE) {

					$h .= $form->group(
						s("Espèce"),
						$form->dynamicField($eTask, 'plant', function(\PropertyDescriber $d) {
							$d->autocompleteDispatch = '#task-create-plant';
						}),
						['data-wrapper' => 'action plant cultivation', 'id' => 'task-create-plant', 'class' => 'form-group-highlight']
					);

				}

				$h .= $form->group(
					s("Intervention"),
					'<h3>'.encode($eTask['action']['name']).'</h3>'
				);

			}

			if($eTask['cSize']->notEmpty()) {
				$h .= $this->getHarvestSizeField($form, $eTask);
			}

			if($eTask['category']['fqn'] === CATEGORIE_CULTURE) {
				$h .= $this->getProductsGroup($form, $eTask);
				$h .= '<div id="task-create-fertilizer">';
					$h .= $this->getFertilizerField($form, $eTask);
				$h .= '</div>';
				$h .= $this->getTreatmentField($form, $eTask);
			}

			$h .= $this->getMethodsGroup($form, $eTask);
			$h .= $this->getToolsGroup($form, $eTask);
			$h .= $this->getTimeGroup($form, $eTask);

			if($eTask['structure']->conf()['time']) {
				$h .= $form->dynamicGroup($eTask, 'timeExpected');
			}

			$h .= $form->dynamicGroup($eTask, 'description');

			$h .= $form->group(
				content: $form->submit(s("Ajouter l'opération"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-task-create',
			title: $this->getCreateTitle($eTask),
			body: $h
		);

	}

	public function getCreateScratchActionGroup(Task $eTask, \Collection $cAction, \Collection $cCategory): string {

		$eTask->expects(['status', 'structure', 'category']);

		$form = new \util\FormUi([
			'firstColumnSize' => 25
		]);

		$h = '<div id="task-write-action">';

			$h .= $form->hidden('category', $eTask['category']['id']);

			$tabs = '<div class="tabs-categories">';

				foreach($cCategory as $eCategory) {

					$get = $_REQUEST;
					$get['category'] = $eCategory['id'];

					$tabs .= '<a data-ajax="/series/task:createFromScratch?'.http_build_query($get).'" data-ajax-method="get" class="tabs-category '.($eTask['category']['id'] === $eCategory['id'] ? 'active' : '').'">'.encode($eCategory['name']).'</a>';

				}

			$tabs .= '</div>';

			$h .= $form->group('', $tabs);

			if($eTask['category']['fqn'] === CATEGORIE_CULTURE) {
				$h .= $form->group(
					s("Espèce"),
					$form->dynamicField($eTask, 'plant', function(\PropertyDescriber $d) {
						$d->autocompleteDispatch = '#task-create-plant';
					}),
					['id' => 'task-create-plant']
				);
			}

			if($cAction->empty()) {
				$actions = '<p class="util-warning">'.s("Aucune intervention n'a été configurée dans cette catégorie.").'</p>';
			} else {
				$actions = $form->dynamicField($eTask, 'action', function(\PropertyDescriber $d) use($cAction) {
					$d->values = $cAction;
				});
			}

			$h .= $form->group(
				s("Intervention"),
				$actions,
				['data-wrapper' => 'action']
			);

		$h .= '</div>';

		return $h;

	}

	protected function getCreateTitle(Task $eTask): string {
		
		$eTask->expects(['action']);
		
		if(
			$eTask['action']->empty() or
			$eTask['action']['fqn'] !== ACTION_RECOLTE
		) {

			return match($eTask['status']) {
				Task::TODO => s("Planifier une future intervention"),
				Task::DONE => s("Consigner une intervention")
			};
			
		} else {

			return match($eTask['status']) {
				Task::TODO => s("Planifier une future récolte"),
				Task::DONE => s("Consigner une récolte")
			};

		}

	}

	public function update(Task $eTask, \Collection $cAction): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/series/task:doUpdate', ['id' => 'task-update', 'data-ajax-origin' => \Route::getRequestedOrigin(), 'autocomplete' => 'off']);

			$h .= $form->hidden('id', $eTask['id']);

			if(
				$eTask['series']->notEmpty() and
				$eTask['action']['fqn'] !== ACTION_RECOLTE
			) {

				$eSeries = $eTask['series'];

				if(
					$eSeries->notEmpty() and
					$eSeries['cCultivation']->count() > 1
				) {

					$values = [
						NULL => s("Partagé")
					];

					foreach($eSeries['cCultivation'] as $eCultivation) {
						$values[$eCultivation['id']] = \plant\PlantUi::getVignette($eCultivation['plant'], '2rem').' '.encode($eCultivation['plant']['name']);
					}

					$action = $form->radios('cultivation', $values, $eTask['cultivation'], attributes: [
						'required' => TRUE,
						'callbackRadioAttributes' => function() {
							return [
								'onclick' => 'Task.updateSelectCultivation(this)'
							];
						}
					]);

					$action .= '<br/>';

				} else {
					$action = $form->hidden('cultivation', $eTask['cultivation']);
				}

			} else {

				$action = '<div>';

					switch($eTask['category']['fqn']) {

						case CATEGORIE_CULTURE :
							if($eTask['plant']->empty()) {
								$action .= '<b>'.encode($eTask['category']['name']).'</b>';
							} else {
								$action .= \plant\PlantUi::getVignette($eTask['plant'], '3rem').' <b>'.encode($eTask['plant']['name']).'</b>';
							}
							break;

						default :
							$action .= '<b>'.encode($eTask['category']['name']).'</b>';
							break;

					}

				$action .= '</div>';
				$action .= '<br/>';

			}

			if($eTask['action']['fqn'] !== ACTION_RECOLTE) {

				$action .= $form->dynamicField($eTask, 'action', function(\PropertyDescriber $d) use($cAction) {
					$d->values = $cAction;
				});

			} else {
				$action .= '<h3>'.encode($eTask['action']['name']).'</h3>';
			}

			$h .= $form->group($this->p('action'), $action, attributes: ['for' => FALSE]);

			// Changer de variété
			$h .= '<div data-ref="varieties">';
				if($eTask['cultivation']->notEmpty()) {
					$cVariety = $eTask['cultivation']['cSlice']->getColumnCollection('variety');
					$h .= $this->getVarietyGroup($form, $eTask, $cVariety);
				}
			$h .= '</div>';

			if($eTask['action']['fqn'] === ACTION_RECOLTE and $eTask['cSize']->notEmpty()) {
				$h .= $this->getHarvestSizeField($form, $eTask);
			}

			if($eTask['category']['fqn'] === CATEGORIE_CULTURE) {
				$h .= $this->getProductsGroup($form, $eTask);
				$h .= $this->getFertilizerField($form, $eTask);
				$h .= $this->getTreatmentField($form, $eTask);
			}

			$h .= $this->getMethodsGroup($form, $eTask);
			$h .= $this->getToolsGroup($form, $eTask);
			$h .= $this->getTimeGroup($form, $eTask);

			if(
				$eTask['structure']->conf()['time'] and
				$eTask['status'] === Task::TODO
			) {
				$h .= $form->dynamicGroup($eTask, 'timeExpected');
			}

			$h .= $form->dynamicGroup($eTask, 'description');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-task-update',
			title: s("Modifier une intervention"),
			subTitle: $eTask['series']->empty() ? NULL : SeriesUi::getPanelHeader($eTask['series']),
			body: $h,
			close: 'reloadOnHistory'
		);

	}

	public function updateIncrementPlannedCollection(\Collection $cTask): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/series/task:doIncrementPlannedCollection', ['autocomplete' => 'off']);

			$h .= $form->group(
				p("Intervention", "Interventions", $cTask->count()),
				$this->getTasksField($form, $cTask, displayPlanned: TRUE),
				['data-wrapper' => 'tasks']
			);

			$h .= $form->group(
				s("Décaler de ..."),
				$form->inputGroup(
					$form->number('increment', attributes: [
						'onrender' => 'this.focus();',
						'min' => -26,
						'max' => 26
					]).
					$form->addon(s("semaine(s)"))
				).
				\util\FormUi::info(s("Utilisez un nombre négatif pour décaler plus tôt et positif pour décaler plus tard"))
			);

			$h .= $form->group(
				content: $form->submit(s("Valider"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-task-increment',
			title: s("Décaler"),
			body: $h
		);

	}

	public function updateHarvestCollection(\Collection $cTask, \Collection $cProductStock, \product\Product $eProductBookmark): \Panel {

		$form = new \util\FormUi();

		$eTask = $cTask->first();

		$h = '';

		$h .= $form->openAjax('/series/task:doUpdateHarvestCollection', ['autocomplete' => 'off']);

			if($eTask['harvestUnit'] === NULL) {

				$h .= $form->group(
					s("Quantité récoltée"),
					$form->inputGroup($form->dynamicField($eTask, 'harvestMore').$form->dynamicField($eTask, 'harvestUnit')),
					['data-wrapper' => 'harvest harvestUnit']
				);

			} else {

				$h .= $form->group(
					s("Quantité récoltée"),
					$form->inputGroup($form->dynamicField($eTask, 'harvestMore').$form->addon(\product\UnitUi::getSingular($eTask['harvestUnit']))),
					['data-wrapper' => 'harvest']
				);

			}

			$h .= $form->dynamicGroup($eTask, 'harvestDate');

			$h .= $this->getPlantsByTasksField($form, $cTask);
			$h .= $this->getStockField($form, $eTask, $cProductStock, $eProductBookmark);

			if($cTask->count() > 1) {
				$h .= $form->group(
					s("Répartition de la récolte sur les productions"),
					self::getDistributionField($form, $cTask, 'plant', withHarvest: FALSE)
				);
			}

			$h .= $form->group(
				content: $form->submit(s("Valider"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-task-harvest',
			title: s("Compléter la récolte"),
			body: $h
		);

	}

	public function updatePlannedCollection(\Collection $cTask): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/series/task:doUpdatePlannedCollection', ['autocomplete' => 'off']);

			$h .= $this->getTasksField($form, $cTask, displayPlanned: TRUE);

			$h .= $form->dynamicGroup($cTask->first(), 'plannedWeek');

			$h .= $form->group(
				content: $form->submit(s("Valider"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-task-planned',
			title: s("Planifier"),
			body: $h
		);

	}
	
	public function getTasksField(\util\FormUi $form, \Collection $cTask, string $field = 'checkbox', string $class = '', bool $displayPlace = FALSE, bool $displayTime = FALSE, bool $displayArea = FALSE, bool $displayDensity = FALSE, bool $displayPlanned = FALSE): string {

		if($displayArea) {
			$cTask->expects(['distributionArea']);
		}

		$display = ($field === 'checkbox' and $cTask->count() > 1) ? 'checkbox' : 'hidden';

		$h = '<div class="'.$class.' stick-xs mb-1">';
			$h .= '<table>';

				$h .= '<thead>';
					$h .= '<tr>';
						if($display === 'checkbox') {
							$h .= '<th></th>';
						}
						$h .= '<th>'.s("Intervention").'</th>';
						if($displayPlanned) {
							$h .= '<th>'.s("Planifiée").'</th>';
						}
						if($displayTime) {
							$h .= '<th>'.s("Temps<br/>passé").'</th>';
						}
						if($displayPlace) {
							$h .= '<th>'.s("Lieu").'</th>';
						}
						if($displayArea) {
							$h .= '<th class="text-end">'.s("Surface").'</th>';
						}
						if($displayDensity) {
							$h .= '<th class="text-end hide-sm-down">';
								$h .= s("Densité");
							$h .= '</th>';
						}
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';


					foreach($cTask as $eTask) {
						$h .= '<tr>';
							if($display === 'checkbox') {
								$h .= '<td class="td-checkbox">';
									$h .= '<label>'.$form->inputCheckbox('ids[]', $eTask['id'], ['checked']).'</label>';
								$h .= '</td>';
							}
							$h .= '<td>';
								if($eTask['plant']->notEmpty()) {
									$h .= \plant\PlantUi::getVignette($eTask['plant'], '1.5rem').'&nbsp;&nbsp;';
								}
								$h .= $this->getAction($eTask);
							$h .= '</td>';
							if($displayPlanned) {
								$h .= '<td>';
									if($eTask['plannedWeek']) {
										$h .= '<b>'.s("Semaine {value}", week_number($eTask['plannedWeek'])).'</b>';
										$h .= '  <span class="util-annotation" style="white-space: nowrap">'.\util\DateUi::weekToDays($eTask['plannedWeek']).'</span>';
									} else {
										$h .= s("Non planifiée");
									}
								$h .= '</td>';
							}
							if($displayTime) {
								$h .= '<td>'.($eTask['time'] ? self::convertTime($eTask['time']) : '-').'</td>';
							}
							if($displayPlace) {
								$h .= '<td>'.$this->getPlace($eTask).'</td>';
							}
							if($displayArea) {
								$h .= '<td class="text-end">'.($eTask['distributionArea'] ? s("{value} m²", round($eTask['distributionArea'])) : '-').'</td>';
							}
							if($displayDensity) {

								$h .= '<td class="text-end hide-sm-down">';
									if($eTask['cultivation']->notEmpty() and $eTask['cultivation']['density'] !== NULL) {
										$density = round($eTask['cultivation']['density'], 1);
										$h .= s("{value} / m²", $density);
									} else {
										$h .= '-';
									}
								$h .= '</td>';

							}
						$h .= '</tr>';
					}

				$h .= '</tbody>';

			$h .= '</table>';
		$h .= '</div>';

		if($display === 'hidden') {
			foreach($cTask as $eTask) {
				$h .= $form->hidden('ids[]', $eTask['id']);
			}
		}
		
		return $h;
		
	}

	public function getPlantsByTasksField(\util\FormUi $form, \Collection $cTask): string {

		$h = '<table class="stick-xs mb-0">';

			$h .= '<tbody>';

				foreach($cTask as $eTask) {
					$h .= '<tr>';
						if($cTask->count() > 1) {
							$h .= '<td class="td-checkbox">';
								$h .= $form->checkbox('ids[]', $eTask['id'], ['checked' => TRUE]);
							$h .= '</td>';
						}
						$h .= '<td>';
							if($cTask->count() === 1) {
								$h .= $form->hidden('ids[]', $eTask['id']);
							}
							$h .= '<div class="task-field-vignette">';
								$h .= '<div>';
									$h .= \plant\PlantUi::getVignette($eTask['plant'], '2rem');
								$h .= '</div>';
								$h .= '<div>';
									$h .= '<div class="task-field-link">';
										$h .= encode($eTask['plant']['name']);
										$h .= $this->getTaskComplement($eTask);
									$h .= '</div>';
									$h .= '<div class="task-field-place">';
										if($eTask['series']->notEmpty()) {
											$h .= s("Série {value}", SeriesUi::link($eTask['series'], newTab: TRUE));
										} else {
											$h .= s("Hors série");
										}
									$h .= '</div>';
								$h .= '</div>';
							$h .= '</div>';
						$h .= '</td>';
					$h .= '</tr>';
				}

			$h .= '</tbody>';

		$h .= '</table>';

		return $form->group(
			p("Production", "Productions", $cTask->count()),
			$h,
			attributes: ['for' => FALSE]
		);

	}

	public function getStockField(\util\FormUi $form, Task $eTask, \Collection $cProductStock, \product\Product $eProductBookmark, string $id = 'task-harvest-stock'): string {

		$h = '<div id="'.$id.'">';

			if($cProductStock->notEmpty()) {

				$h .= $form->group(
					s("Ajouter à un stock"),
					$this->getStockInput($form, $eTask, $cProductStock, $eProductBookmark),
					attributes: [
						'data-wrapper' => 'stock',
						'for' => FALSE
					]
				);

			}

			$h .= '</div>';

			return $h;

	}

	/**
	 * Le choix du stock et l'étoile qui le mémorise, sans leur libellé, pour un écran qui titre ses champs
	 * autrement qu'en groupe de formulaire.
	 */
	public function getStockInput(\util\FormUi $form, Task $eTask, \Collection $cProductStock, \product\Product $eProductBookmark): string {

		$values = [];

		foreach($cProductStock as $eProductStock) {

			$name = \product\ProductUi::getVignette($eProductStock, '2rem').'  ';
			$name .= encode($this->getStockText($eProductStock));

			$values[$eProductStock['id']] = $name;

		}

		$confirmText = s("Ajouter automatiquement les quantités récoltées de {value} à ce stock dans le futur ?", $this->getStockText(new \product\Product([
			'name' => $eTask['plant']['name'],
			'variety' => $eTask['variety']->notEmpty() ? $eTask['variety']['name'] : NULL,
			'unit' => $eTask['harvestUnit'] ?? 'kg'
		])));

		return $form->inputGroup(
			$form->selectDropdown('stock', $values, $eProductBookmark, attributes: ['placeholder' => s("Pas de mise en stock"), 'class' => 'form-control-lg']).
			'<label class="input-group-addon task-field-bookmark" title="'.s("Mémoriser ce choix pour les futures récoltes").'">'.$form->inputCheckbox('stockRemember', attributes: [
				'checked' => $eProductBookmark->notEmpty(),
				'data-confirm' => $eProductBookmark->notEmpty() ? NULL : $confirmText,
				'data-confirm-text' => $confirmText,
				'onclick' => 'Task.changeBookmark(this)'
			]).\Asset::icon('star', ['class' => 'task-field-bookmark-no']).\Asset::icon('star-fill', ['class' => 'task-field-bookmark-yes']).'</label>'
		);

	}

	protected function getStockText(\product\Product $eProduct): string {

		$text = encode($eProduct['name']).' ('.\product\UnitUi::getSingular($eProduct['unit']).')';

		if($eProduct['variety'] !== NULL) {
			$text .= ' / '.$eProduct['variety'];
		}
		
		return $text;

	}

	public function updateCultivation(Task $eTask, \Collection $cCultivation): \Panel {

		$form = new \util\FormUi();

		if($cCultivation->count() < 2) {

			$h = '<div class="util-empty">';
				$h .= s("Il n'y a aucune autre série compatible avec cette espèce.");
			$h .= '</div>';

		} else {

			$h = '';

			$h .= $form->openAjax('/series/task:doUpdateCultivation', ['id' => 'task-update', 'data-ajax-origin' => \Route::getRequestedOrigin(), 'autocomplete' => 'off']);

				$h .= $form->hidden('id', $eTask['id']);

				$h .= $form->group(
					s("Nouvelle série"),
					$form->dynamicField($eTask, 'cultivation', function($d) use($cCultivation) {

						$d->values = $cCultivation->makeArray(function($eCultivation, &$key) {
							$key = $eCultivation['id'];
							return $eCultivation['series']['name'].' / '.\sequence\CropUi::startText($eCultivation);
						});
						asort($d->values);

						$d->required = TRUE;


					}),
					['data-wrapper' => 'harvest']
				);

				$h .= $form->group(
					content: $form->submit(s("Valider"))
				);

			$h .= $form->close();

		}

		return new \Panel(
			id: 'panel-task-update-cultivation',
			title: s("Changer de série"),
			subTitle: $eTask['series']->empty() ? NULL : SeriesUi::getPanelHeader($eTask['series']),
			body: $h
		);

	}

	public function getTimeGroup(\util\FormUi $form, Task $eTask): string {

		$eTask->expects(['status']);

		$h = '<div class="util-block">';

		switch($eTask['status']) {

			case Task::TODO :
				$h .= $form->dynamicGroup($eTask, 'planned');
				break;

			case Task::DONE :
				$h .= $form->dynamicGroup($eTask, 'done');
				break;

		}

		$h .= '</div>';

		return $h;

	}

	public function getVarietyGroup(\util\FormUi $form, Task $eTask, \Collection $cVariety, ?array $varietiesIntersect = NULL): string {

		$h = '';

		if($cVariety->count() > 1) {

			$cVariety->sort('name');

			$attributes = [
				'placeholder' => s("Toutes")
			];

			if($cVariety->count() <= 3) {

				if($varietiesIntersect !== NULL) {
					$attributes['callbackRadioAttributes'] = function($eVariety) use($varietiesIntersect) {
						if($eVariety !== NULL) {
							return in_array($eVariety['id'], $varietiesIntersect) ? [] : ['disabled'];
						} else {
							return [];
						}
					};
				}

				$h .= $form->group(
					s("Variété"),
					$form->radios('variety', $cVariety, $eTask['variety'] ?? new \plant\Variety(), $attributes + [
						'columns' => 2,
					]),
				);

			} else {

				$values = [];

				foreach($cVariety as $eVariety) {

					$values[] = [
						'value' => $eVariety['id'],
						'label' => $eVariety['name'],
						'attributes' => ($varietiesIntersect === NULL or in_array($eVariety['id'], $varietiesIntersect)) ? [] : ['disabled']
					];

				}

				$h .= $form->group(
					s("Variété"),
					$form->select('variety', $values, $eTask['variety'] ?? new \plant\Variety(), $attributes),
				);

			}

		} else {
			$h .= $form->hidden('variety', '');
		}

		return $h;

	}

	public function getToolsGroup(\util\FormUi $form, Task $eTask): string {

		$h = '<div data-ref="tools" data-structure="'.$eTask['structure']['id'].'">';
			if($eTask['hasTools']->notEmpty()) {
				$h .= $form->dynamicGroup($eTask, 'tools');
			}
		$h .= '</div>';

		return $h;

	}

	public function getProductsGroup(\util\FormUi $form, Task $eTask): string {

		$h = '<div data-ref="products" data-structure="'.$eTask['structure']['id'].'">';
			$h .= $this->getProductsField($form, $eTask);
		$h .= '</div>';

		return $h;

	}

	public function getProductsField(\util\FormUi $form, Task $eTask): string {

		$eTask->expects(['action', 'hasProducts']);

		if($eTask['action']->empty()) {
			return '';
		}

		return match($eTask['action']['fqn']) {

			ACTION_TRAITEMENT => $form->dynamicGroup($eTask, 'products', function(\PropertyDescriber $d) {
				$d->label = s("Produit");
				$d->placeholder = s("Nom commercial ou numéro d'AMM");
				$d->multiple = FALSE;
				$d->autocompleteDefault = fn(Task $e) => $e['cProduct?']()->empty() ? new \work\Tool() : $e['cProduct?']()->first();
			}).
			$form->hidden('productQuery', '').
			$this->getProductNewField($form),

			ACTION_FERTILISATION => $eTask['hasProducts']->empty() ? '' : $form->dynamicGroup($eTask, 'products', function(\PropertyDescriber $d) {
				$d->label = s("Intrants");
				$d->placeholder = s("Ajoutez un intrant");
				$d->multiple = TRUE;
			}),

			default => ''

		};

	}

	// Désactivé tant que « Créer » n'est pas choisi dans la recherche : un fieldset désactivé ne poste rien (Task.js).
	protected function getProductNewField(\util\FormUi $form): string {

		$h = $form->group(
			s("Nom du produit").\util\FormUi::asterisk(),
			$form->text('productNew[name]'),
			['data-wrapper' => 'name']
		);

		return $form->fieldset(s("Nouveau produit"), $h, ['class' => 'form-fieldset hide', 'data-ref' => 'product-new', 'disabled' => 'disabled']);

	}

	public function getMethodsGroup(\util\FormUi $form, Task $eTask): string {

		$h = '<div data-ref="methods" data-structure="'.$eTask['structure']['id'].'">';
			if($eTask['hasMethods']->notEmpty()) {
				$h .= $form->dynamicGroup($eTask, 'methods');
			}
		$h .= '</div>';

		return $h;

	}

	public function getHarvestSizeField(\util\FormUi $form, Task $eTask): string {

		$eTask->expects(['action', 'cSize']);

		return $form->group(
			self::p('harvestSize')->label,
			$form->dynamicField($eTask, 'harvestSize'),
			['class' => ($eTask['action']->notEmpty() and $eTask['action']['fqn'] === ACTION_RECOLTE) ? '' : 'hide']
		);

	}

	public function getFertilizerField(\util\FormUi $form, Task $eTask): string {

		$eTask->expects(['action']);

		return $form->group(
			self::p('fertilizer')->label,
			new \work\RoutineUi()->getFieldFertilizer($form, 'fertilizer', $eTask['fertilizer'] ?? NULL),
			['class' => ($eTask['action']->notEmpty() and $eTask['action']['fqn'] === ACTION_FERTILISATION) ? '' : 'hide', 'data-wrapper' => 'fertilizer']
		);

	}

	/**
	 * Vide à la création, l'intervention prend la dose et la bouillie du produit, et les garde.
	 */
	public function getTreatmentField(\util\FormUi $form, Task $eTask): string {

		$eTask->expects(['action']);

		$treatment = $eTask['treatment'] ?? NULL;

		$eToolProduct = $eTask['cProduct?']()->first() ?? new \work\Tool();
		$readyToUse = ($eToolProduct->notEmpty() and $eToolProduct['routineName'] === 'phyto' and $eToolProduct['routineValue']['readyToUse']);

		$units = array_combine(\work\RoutineLib::PHYTO_UNITS, \work\RoutineLib::PHYTO_UNITS);

		$h = $form->inputGroup(
			$form->addon(s("Dose")).
			$form->number('treatment[dose]', $treatment['dose'] ?? NULL, ['step' => 0.001, 'min' => 0]).
			$form->select('treatment[unit]', $units, $treatment['unit'] ?? \work\RoutineLib::PHYTO_UNITS[0], ['required' => TRUE])
		);

		$h .= $form->inputGroup(
			$form->addon(s("Bouillie")).
			$form->number('treatment[water]', $readyToUse ? NULL : ($treatment['water'] ?? NULL), ['min' => 1]).
			$form->addon(s("L/ha")),
			['class' => $readyToUse ? 'hide' : '', 'data-treatment' => 'water']
		);

		return $form->group(
			self::p('treatment')->label,
			'<div class="task-treatment-settings">'.$h.'</div>',
			['class' => ($eTask['action']->notEmpty() and $eTask['action']['fqn'] === ACTION_TRAITEMENT) ? '' : 'hide', 'data-wrapper' => 'treatment']
		);

	}

	public static function getDistributionField(\util\FormUi $form, \Collection $cTask, string $default, bool $withHarvest = TRUE): ?string {

		$cTask->expects([
			'category'
		]);

		// La distribution est réalisée sur le TEMPS DE TRAVAIL et la RÉCOLTE
		$values = [
			'harvest' => s("En fonction de la quantité récoltée le jour de travail"),
			'area' => s("En fonction de la surface"),
			'plant' => s("En fonction du nombre de plants"),
			'fair' => s("Égalitaire")
		];

		if($withHarvest === FALSE) {
			unset($values['harvest']);
		}

		// Gestion du cas où au moins une intervention ni concerne ni le sol, ni la plante
		// Répartition uniquement égalitaire
		foreach($cTask as $eTask) {

			if($eTask['category']['fqn'] !== CATEGORIE_CULTURE) {

				$h = $form->hidden('distribution', 'fair');
				$h .= '<b>'.$values['fair'].'</b>';
				$h .= '<div class="util-info">';
					$h .= s("Les autres options de répartition ne sont pas disponibles car au moins une intervention ne concerne pas les cultures.");
				$h .= '</div>';

				return $h;

			}

		}

		$byPlant = NULL;
		$byArea = NULL;
		$byHarvest = NULL;

		$harvested = '';

		foreach($cTask as $eTask) {

			if($withHarvest) {

				if($eTask['action']['fqn'] !== ACTION_RECOLTE) {
					unset($values['harvest']);
				} else if($eTask['harvest'] === NULL) {
					$byHarvest = 'noHarvest';
				} else {
					foreach($eTask['harvestDates'] as $day => $value) {
						$harvested .= '<tr data-day="'.$day.'">';
							$harvested .= '<td>';
								$harvested .= '<span class="action-name">'.encode($eTask['plant']['name']).'</span>';
							$harvested .= '</td>';
							$harvested .= '<td>';
								$harvested .= new TaskUi()->getPlace($eTask);
							$harvested .= '</td>';
							$harvested .= '<td>';
								$harvested .= '<span class="annotation" style="color: '.$eTask['action']['color'].'">'.\product\UnitUi::getValue($value, $eTask['harvestUnit']).'</span>';
							$harvested .= '</td>';
						$harvested .= '</tr>';
					}
				}

			}

			// Pas série, répartition uniquement égalitaire
			if($eTask['series']->empty()) {


				if($default === 'area' or $default === 'plant') {
					$default = 'fair';
				}

				$byArea = 'noSeries';
				$byPlant = 'noSeries';

			} else {

				$eTask['series']->expects(['area']);

				// La série n'a pas de surface renseignée, impossible de répartir par surface
				if($eTask['series']['area'] === NULL) {
					$byArea = 'noArea';
					$default = 'fair';
				}

				// La culture de la série n'a pas de densité renseignée, impossible de répartir par densité
				if($eTask['cultivation']->notEmpty()) {

					$eTask['cultivation']->expects(['density']);

					if($eTask['cultivation']['density'] === NULL) {

						$byPlant = 'noDensity';

						if($default === 'plant') {
							$default = ($byArea === NULL) ? 'area' : 'fair';
						}

					}

				}

			}

		}

		if($byArea === 'noSeries') {
			$values['area'] = '<span class="color-muted">'.$values['area'].' - '.\Asset::icon('info-circle').' '.s("indisponible car au moins une intervention n'est pas liée à une série").'</span>';
		} else if($byArea === 'noArea') {
			$values['area'] = '<span class="color-muted">'.$values['area'].' - '.\Asset::icon('info-circle').' '.s("indisponible car vous n'avez pas saisi la surface de toutes les séries").'</span>';
		}

		if($withHarvest) {

			if($byHarvest === 'noHarvest') {
				$values['harvest'] = '<span class="color-muted">'.$values['harvest'].' - '.\Asset::icon('info-circle').' '.s("indisponible car il n'y a aucune saisie de récolte pour au moins une des interventions").'</span>';
			}

			if($harvested !== '') {
				$values['harvest'] .= '<table id="task-distribution-dates"  data-day="'.implode(' ', array_keys($eTask['harvestDates'])).'" onrender="Task.toggleDistributionHarvestTable()">';
					$values['harvest'] .= $harvested;
				$values['harvest'] .= '</table>';
			}

		}

		if($byPlant === 'noSeries') {
			$values['plant'] = '<span class="color-muted">'.$values['plant'].' - '.\Asset::icon('info-circle').' '.s("indisponible car au moins une intervention n'est pas liée à une série").'</span>';
		} else if($byPlant === 'noDensity') {
			$values['plant'] = '<span class="color-muted">'.$values['plant'].' - '.\Asset::icon('info-circle').' '.s("indisponible car vous n'avez pas saisi la densité de toutes les séries").'</span>';
		}

		return $form->radios('distribution', $values, $default, [
			'required' => TRUE,
			'callbackRadioAttributes' => function($option, $key) use($byPlant, $byArea, $byHarvest) {
				if(
					($key === 'area' and $byArea !== NULL) or
					($key === 'harvest' and $byHarvest !== NULL) or
					($key === 'plant' and $byPlant !== NULL)
				) {
					return ['disabled'];
				} else {
					return [];
				}
			}
		]);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Task::model()->describer($property, [
			'action' => s("Intervention"),
			'cultivation' => s("Série"),
			'planned' => s("Planifié"),
			'plannedWeek' => s("Planifié"),
			'done' => s("Réalisé"),
			'doneWeek' => s("Réalisé"),
			'time' => s("Temps de travail effectué"),
			'timeExpected' => s("Temps de travail estimé"),
			'method' => s("Méthode de travail"),
			'description' => s("Observations"),
			'fertilizer' => s("Apports"),
			'treatment' => s("Traitement"),
			'harvestSize' => s("Calibre récolté"),
			'harvestMore' => s("Quantité récoltée"),
			'harvestDay' => s("Quantité récoltée ce jour"),
			'harvestDate' => s("Jour de récolte"),
			'methods' => s("Méthodes de travail"),
			'products' => s("Produits"),
			'tools' => s("Matériel nécessaire")
		]);

		switch($property) {

			case 'action' :
				$d->field = 'radio';
				$d->values = fn(Task $e) => $e['cAction'] ?? $e->expects(['cAction']);
				$d->attributes = [
					'columns' => 3,
					'callbackRadioAttributes' => function(\work\Action $eAction) {
						return [
							'disabled' => ($eAction['disabled'] ?? FALSE) ? 'disabled' : NULL,
							'data-action' => 'task-write-action-change',
							'data-fqn' => $eAction['fqn'],
							'data-structure' => $eAction['structure']['id']
						];
					}
				];
				$d->required = TRUE;
				break;

			case 'timeExpected' :
				$d->append = s("h");
				break;

			case 'planned' :
			case 'done' :
				$d->field = function(\util\FormUi $form, Task $e) use($property) {

					$e->expects([$property.'Week', $property.'Date']);

					if(isset($e[$property.'Selection'])) {

						$isWeek = ($e[$property.'Selection'] === 'week');
						$isDate = ($e[$property.'Selection'] === 'date');

					} else {

						$isWeek = ($e[$property.'Week'] !== NULL and $e[$property.'Date'] === NULL);
						$isDate = ($e[$property.'Date'] !== NULL);

					}

					$h = '<div class="task-write-planned" data-period="'.($isWeek ? 'week' : ($isDate ? 'date' : 'unplanned')).'">';

						$h .= '<div class="task-write-planned-field task-write-planned-week">';
							$h .= $form->week($property.'[week]', $e[$property.'Week'], $isWeek ? [] : ['disabled']);
						$h .= '</div>';

						$h .= '<div class="task-write-planned-field task-write-planned-date">';
							$h .= $form->date($property.'[date]', $e[$property.'Date'], $isDate ? [] : ['disabled']);
						$h .= '</div>';

						$h .= '<div class="task-write-planned-field task-write-planned-unplanned">';
							$h .= $form->fake(s("Non planifié"));
						$h .= '</div>';

						$labelWeek = match($property) {
							'planned' => s("Planifier à la semaine"),
							'done' => s("Consigner à la semaine")
						};

						$labelDate = match($property) {
							'planned' => s("Planifier à la journée"),
							'done' => s("Consigner à la journée")
						};

						$h .= '<div class="field-action">';
							$h .= '<span class="task-write-planned-link-week">';
								$h .= '<a '.attr('onclick', 'Task.changePlanned(this, "week")').'>'.$labelWeek.'</a>';
							$h .= '</span>';
							$h .= '<span class="task-write-planned-separator-1"> | </span>';
							$h .= '<span class="task-write-planned-link-date">';
								$h .= '<a '.attr('onclick', 'Task.changePlanned(this, "date")').'>'.$labelDate.'</a>';
							$h .= '</span>';
							if($property === 'planned') {
								$h .= '<span class="task-write-planned-separator-2"> | </span>';
								$h .= '<span class="task-write-planned-link-unplanned">';
									$h .= '<a '.attr('onclick', 'Task.changePlanned(this, "unplanned")').'>'.s("Non planifié").'</a>';
								$h .= '</span>';
							}
							if($e->exists() === FALSE) {
								$h .= '<span class="task-write-repeat">';
									$h .= ' | '.\Asset::icon('caret-down-fill').' <a '.attr('onclick', 'Task.changeRepeat(this, true)').'>'.s("Répéter").'</a>';
								$h .= '</span>';
								$h .= '<span class="task-write-norepeat hide">';
									$h .= ' | '.\Asset::icon('caret-up-fill').' <a '.attr('onclick', 'Task.changeRepeat(this, false)').'>'.s("Une seule fois").'</a>';
								$h .= '</span>';
							}
						$h .= '</div>';

						if($e->exists() === FALSE) {

							$eRepeat = new Repeat();

							$h .= '<div class="task-write-repeat-field hide">';
								$h .= '<h5>'.s("Répéter l'intervention").'</h5>';
								$h .= $form->dynamicField($eRepeat, 'frequency', function($d) {
									$d->attributes['callbackRadioAttributes'] = fn() => ['disabled'];
								});
								$h .= '<div class="mt-1">';
									$h .= '<h5>'.s("Jusqu'à :").'</h5>';
									$h .= $form->dynamicField($eRepeat, 'stop', function($d) {
										$d->attributes = ['disabled'];
									});
								$h .= '</div>';
							$h .= '</div>';

						}

					$h .= '</div>';

					return $h;

				};
				$d->wrapper = $property.'Week '.$property.'Date '.$property.' frequency stop repeatMaster';
				break;

			case 'plant' :
				$d->autocompleteBody = function(\util\FormUi $form, Task $e) {
					$e->expects(['structure']);
					return [
						'structure' => $e['structure']['id']
					];
				};
				new \plant\PlantUi()->query($d);
				break;

			case 'plantsFilter' :
				$d->autocompleteBody = function(\util\FormUi $form, Task $e) {
					$e->expects(['structure']);
					return [
						'structure' => $e['structure']['id'],
						'season' => $e['season']
					];
				};
				$d->autocompleteDefault = fn(Task $e) => $e['plant'] ?? $e->expects(['plant']);
				$d->autocompleteDispatch = '#task-create-plant';
				$d->placeholder = s("Filtrer sur une espèce...");
				$d->prepend ??= \Asset::icon('search');

				new \plant\PlantUi()->query($d);
				$d->group = function(Task $e) {
					return [
						'id' => 'task-create-plant',
						'data-season' => $e['season']
					];
				};
				break;

			case 'harvestUnit' :
				$d->field = 'select';
				$d->attributes = [
					'onchange' => fn(\util\FormUi $form, Task $e) => 'Task.changeHarvestUnit('.$e['id'].', this)'
				];
				$d->required = TRUE;
				$d->values = \product\UnitUi::getBasicList();
				$d->default = function(Task $e) {
					if($e['cultivation']->notEmpty()) {
						return $e['cultivation']['mainUnit'];
					} else {
						return NULL;
					}
				};
				break;

			case 'harvestMore' :
				$d->type = 'float';
				$d->attributes = [
					'onrender' => 'this.focus();',
					'step' => 0.001
				];
				$d->wrapper = 'harvest';
				break;

			case 'harvestDay' :
				$d->type = 'float';
				$d->attributes = [
					'step' => 0.001,
					'min' => 0
				];
				break;

			case 'harvestDate' :
				$d->field = 'date';
				$d->default = date('Y-m-d', \Clock::format('G') <= 6 ? \Clock::shift('yesterday') : \Clock::timestamp());
				$d->wrapper = 'harvestDates';
				break;

			case 'harvestSize' :
				$d->attributes = ['placeholder' => s("Aucun")];
				$d->values = fn(Task $e) => $e['cSize'] ?? $e->expects(['cSize']);
				break;

			case 'status' :
				$d->values = [
					Task::TODO => s("À faire"),
					Task::DONE => s("Fait"),
				];
				break;

			case 'methods' :
				$d->autocompleteDefault = fn(Task $e) => ($e['cMethod?'] ?? $e->expects(['cMethod?']))();
				$d->autocompleteBody = function(\util\FormUi $form, Task $e) {
					$e->expects(['action', 'structure']);
					return [
						'action' => $e['action']['id'],
						'structure' => $e['structure']['id']
					];
				};
				new \work\MethodUi()->query($d, TRUE);
				$d->wrapper = 'methods';
				break;

			case 'tools' :
				$d->autocompleteDefault = fn(Task $e) => ($e['cTool?'] ?? $e->expects(['cTool?']))();
				$d->autocompleteBody = function(\util\FormUi $form, Task $e) {
					$e->expects(['action', 'structure']);
					return [
						'action' => $e['action']['id'],
						'structure' => $e['structure']['id']
					];
				};
				new \work\ToolUi()->query($d, TRUE);
				$d->wrapper = 'tools';
				break;

			case 'products' :
				$d->autocompleteDefault = fn(Task $e) => ($e['cProduct?'] ?? $e->expects(['cProduct?']))();
				$d->autocompleteBody = function(\util\FormUi $form, Task $e) {
					$e->expects(['action', 'structure']);
					return [
						'action' => $e['action']['id'],
						'structure' => $e['structure']['id'],
						'catalog' => TRUE
					];
				};
				new \work\ToolUi()->queryProduct($d);
				$d->wrapper = 'products';
				break;

		}

		return $d;

	}

}
?>
