<?php
namespace sequence;

class FlowUi {

	public function __construct() {
		\Asset::css('sequence', 'flow.css');
		\Asset::js('sequence', 'flow.js');
	}

	public function getTimeline(Sequence $eSequence, array $events, bool $write, ?int $referenceYear = NULL): string {

		if($events === []) {
			return $this->getEmptyTimeline($eSequence, $write);
		}

		\Asset::css('series', 'series.css');

		$cCrop = $eSequence['cCrop'];

		$form = new \util\FormUi();

		$lanes = self::countLanes($events);

		// Le script reste dans le conteneur : les réponses ajax le remplacent par outerHtml, qui refuse plus d'une racine.
		$h = '<div id="flow-wrapper" data-sequence="'.$eSequence['id'].'" data-batch="#batch-flow" onrender="Flow.layoutDelays(this); Flow.settlePositions(this)">';

			$h .= '<script type="text/javascript">Flow.labels = '.json_encode([
				'withFollowing' => s("avec les suivantes"),
				'alone' => s("seule"),
			]).';</script>';

			$h .= '<div class="flow-timeline-wrapper flow-timeline-vertical flow-timeline-sequence'.($lanes === 0 ? ' flow-timeline-sequence-without-lanes' : '').' stick-xs" style="--flow-lanes: '.$lanes.'">';

				$h .= $this->getTimelineHeader($form, $write);

				$h .= '<div class="flow-timeline-body">';

					// Les périodes en cours, un couloir chacune : une intervention qui se
					// répète prend sa place à son départ et la rend à sa fin. Le trait de
					// chaque bande se dessine depuis cette liste.
					$lasting = [];

					foreach($events as $season => $years) {

						$endless = self::isEndless($years);

						// Le ruban va de la première intervention de la saison à la dernière,
						// sur l'échelle de Flow.weekIndex() : une année compte 52 semaines.
						$spanIndexes = [];

						foreach($years as $year => $weeks) {
							foreach(array_keys($weeks) as $week) {
								$spanIndexes[] = $year * 52 + $week;
							}
						}

						$spanFirst = min($spanIndexes);
						$spanLast = max($spanIndexes);

						// Trois semaines de marge de part et d'autre, comme sur une série, pour
						// y glisser une intervention.
						if($write) {

							[$yearMin, $yearMax] = ($eSequence['cycle'] === Sequence::PERENNIAL) ? [0, 0] : [-1, 1];

							$padding = array_merge(
								range(max($yearMin * 52 + 1, $spanFirst - 3), $spanFirst),
								range($spanLast, min($yearMax * 52 + 52, $spanLast + 3))
							);

							foreach($padding as $index) {
								$year = intdiv($index - 1 + 104, 52) - 2;
								$years[$year][$index - $year * 52] ??= [];
							}

							ksort($years);

						}

						if($eSequence['cycle'] === Sequence::PERENNIAL) {

							$h .= '<div class="flow-timeline flow-timeline-season">';
								$h .= '<div></div>';
								$h .= '<div class="flow-timeline-season-value">';
									$h .= $endless ? s("Saison {value} et suivantes", $season) : s("Saison {value}", $season);
								$h .= '</div>';
								$h .= '<div></div>';
								$h .= '<div class="flow-timeline-row flow-timeline-row-empty"><div></div><div class="flow-timeline-lanes">'.$this->getLanes($lasting, [], [], FALSE, FALSE).'</div><div></div></div>';
							$h .= '</div>';

						}

						$firstYear = array_key_first($years);

						foreach($years as $year => $weeks) {

							if($referenceYear !== NULL) {
								$currentYear = $referenceYear + $year - $firstYear;
							} else {
								$currentYear = \Clock::year() + $year;
							}

							if(count($years) > 1 or $year !== 0) {

								$h .= '<div class="flow-timeline flow-timeline-year">';
									$h .= '<div></div>';
									$h .= '<div class="flow-timeline-year-value">';
										$h .= ($referenceYear !== NULL) ? $currentYear : self::p('yearOnly')->values[$year];
									$h .= '</div>';
									$h .= '<div></div>';
									$h .= '<div class="flow-timeline-row flow-timeline-row-empty"><div></div><div class="flow-timeline-lanes">'.$this->getLanes($lasting, [], [], FALSE, FALSE).'</div><div></div></div>';
								$h .= '</div>';

							}

							$weekNumbers = array_keys($weeks);
							sort($weekNumbers);

							$delays = self::getDelays($eSequence, $weeks);

							// Toutes les semaines de la première à la dernière, y compris les
							// vides : c'est ce qui donne aux traits leur longueur.
							for($week = $weekNumbers[0]; $week <= end($weekNumbers); $week++) {

								$flows = $weeks[$week] ?? [];

								$starting = [];
								$stopping = [];
								$rows = [];

								foreach($flows as ['field' => $field, 'flow' => $eFlow]) {

									if($eFlow['crop']->empty() === FALSE) {
										$eFlow['crop'] = $cCrop[$eFlow['crop']['id']];
										$eFlow['plant'] = $eFlow['crop']['plant'];
									}

									if($field === 'stop') {
										$stopping[] = $eFlow;
										continue;
									}

									if($field === 'start') {
										$starting[] = $eFlow;
									}

									$rows[] = $eFlow;

								}

								$occupied = ($rows !== []);

								// Le trait couvre la semaine entière, du haut de sa bande de
								// départ au bas de sa bande de fin : le couloir se prend donc
								// avant de rendre la bande, et se rend après.
								foreach($starting as $eFlow) {
									$lasting[self::takeLane($lasting)] = $eFlow;
								}

								$index = $year * 52 + $week;

								$class = 'flow-timeline flow-timeline-band';
								$class .= $occupied ? ' flow-timeline-band-occupied' : ' flow-timeline-band-empty';
								if($index >= $spanFirst and $index <= $spanLast) {
									$class .= ' flow-timeline-band-span';
									$class .= ($index === $spanFirst) ? ' flow-timeline-band-span-first' : '';
									$class .= ($index === $spanLast) ? ' flow-timeline-band-span-last' : '';
								}

								$h .= '<div class="'.$class.'" data-week="'.$week.'" data-year="'.$year.'" data-season="'.$season.'">';

									$h .= '<div class="flow-timeline-month"></div>';

									$h .= '<a class="flow-timeline-axis" onclick="Flow.changeWeekSelection(this)">';
										$h .= '<span class="flow-timeline-axis-week">'.s("s{value}", $week).'</span>';
										if($occupied) {
											$h .= '<span class="flow-timeline-axis-days">'.\util\DateUi::weekToDays($currentYear.'-W'.sprintf('%02d', $week), TRUE, FALSE).'</span>';
										}
										foreach($delays[$week] ?? [] as $delay) {

											if($delay['type'] === 'start-pill') {
												continue;
											}

											// L'arrivée s'arrête à la pastille de l'étape, pas au haut de la
											// bande : sa hauteur exacte est posée en JS (Flow.layoutDelays).
											$anchor = ($delay['flow'] === NULL) ? '' : ' data-flow="'.$delay['flow'].'"';

											$h .= '<span class="flow-timeline-delay flow-timeline-delay-'.$delay['type'].'" data-delay="'.$delay['group'].'"'.$anchor.' style="--chain: 0;">';
												if($delay['label'] !== NULL) {
													$h .= '<span class="flow-timeline-delay-label">'.s("{value} sem.", $delay['label']).'</span>';
												}
											$h .= '</span>';

										}
									$h .= '</a>';

									$h .= '<div class="flow-timeline-rail"></div>';

									$h .= '<div class="flow-timeline-rows">';

										if($occupied) {

											$position = 0;

											foreach($rows as $eFlow) {

												$last = ($position === count($rows) - 1);

												// Le départ est porté par la ligne de l'étape qui l'ancre, pas par
												// la dernière ligne de la semaine.
												$rowDelays = array_values(array_filter($delays[$week] ?? [], fn(array $delay) => $delay['type'] === 'start-pill' and $delay['flow'] === $eFlow['id']));

												$h .= $this->getFlowRow($form, $eFlow, $flows, $week, $write, $position === 0, $last, $lasting, $starting, $stopping, $position === 0, $last, $rowDelays);
												$position++;

											}

										} else {

											// Une semaine sans intervention porte quand même les traits
											// de ce qui la traverse.
											$h .= '<div class="flow-timeline-row flow-timeline-row-empty">';
												$h .= '<div></div>';
												$h .= '<div class="flow-timeline-lanes">'.$this->getLanes($lasting, $starting, $stopping, TRUE, TRUE, $write).'</div>';
												$h .= '<div></div>';
											$h .= '</div>';

										}

									$h .= '</div>';

								$h .= '</div>';

								// La fin d'une période rend son couloir, après le rendu de la
								// bande : le trait descend jusqu'à sa dernière semaine incluse.
								foreach($stopping as $eFlow) {
									self::releaseLane($lasting, $eFlow);
								}

							}

						}

						if($endless) {
							break;
						}

					}

				$h .= '</div>';

			$h .= '</div>';

			if($write) {
				$h .= $this->getBatch();
			}

		$h .= '</div>';

		return $h;

	}

	// Le nombre de couloirs à réserver : le maximum de périodes actives en même
	// temps sur tout l'itinéraire. La colonne ne change pas de largeur en cours de
	// route, sinon les bandes ne s'alignent plus.
	/**
	 * Les délais entre les repères de l'itinéraire, comme sur la page d'une série :
	 * semis, plantation, puis le départ de la récolte. Deux repères séparés d'au
	 * moins deux semaines portent un trait pointillé et le nombre de semaines.
	 *
	 * Rien au-delà d'une culture : deux chaînes se croiseraient dans la colonne des
	 * semaines sans qu'on sache laquelle est à qui.
	 */
	protected static function getDelays(Sequence $eSequence, array $weeks): array {

		if($eSequence['cCrop']->count() > 1) {
			return [];
		}

		$anchors = [];

		foreach($weeks as $week => $flows) {

			foreach($flows as ['field' => $field, 'flow' => $eFlow]) {

				// La fin d'une période n'est pas une étape : une récolte jalonne le
				// cycle par son départ.
				if($field === 'stop') {
					continue;
				}

				if(in_array($eFlow['action']['fqn'], [ACTION_SEMIS_DIRECT, ACTION_SEMIS_PEPINIERE, ACTION_PLANTATION, ACTION_RECOLTE])) {
					$anchors[$week] = $eFlow['id'];
				}

			}

		}

		$steps = array_keys($anchors);
		sort($steps);

		$delays = [];

		foreach($steps as $index => $step) {

			if($index === 0) {
				continue;
			}

			$previous = $steps[$index - 1];
			$length = $step - $previous;

			if($length < 2) {
				continue;
			}

			$between = range($previous + 1, $step - 1);

			$group = 'd'.$previous.'-'.$step;

			$delays[$previous][] = ['type' => 'start-pill', 'label' => NULL, 'flow' => $anchors[$previous], 'group' => $group];

			foreach($between as $position => $week) {
				$delays[$week][] = ['type' => 'full', 'label' => ($position === intdiv(count($between), 2)) ? $length : NULL, 'flow' => NULL, 'group' => $group];
			}

			$delays[$step][] = ['type' => 'end-pill', 'label' => NULL, 'flow' => $anchors[$step], 'group' => $group];

		}

		return $delays;

	}

	protected static function countLanes(array $events): int {

		$lasting = [];
		$max = 0;

		foreach($events as $years) {
			foreach($years as $weeks) {

				$weekNumbers = array_keys($weeks);
				sort($weekNumbers);

				foreach($weekNumbers as $week) {

					foreach($weeks[$week] as ['field' => $field, 'flow' => $eFlow]) {
						if($field === 'start') {
							$lasting[self::takeLane($lasting)] = $eFlow;
						}
					}

					$max = max($max, count($lasting));

					foreach($weeks[$week] as ['field' => $field, 'flow' => $eFlow]) {
						if($field === 'stop') {
							self::releaseLane($lasting, $eFlow);
						}
					}

				}

			}
		}

		return $max;

	}

	protected static function isEndless(array $years): bool {

		foreach($years as $weeks) {
			foreach($weeks as $flows) {
				foreach($flows as $flow) {
					if($flow['endless'] === FALSE) {
						return FALSE;
					}
				}
			}
		}

		return TRUE;

	}

	protected static function takeLane(array $lasting): int {

		$lane = 0;

		while(array_key_exists($lane, $lasting)) {
			$lane++;
		}

		return $lane;

	}

	protected static function releaseLane(array &$lasting, Flow $eFlow): void {

		foreach($lasting as $lane => $eFlowLasting) {
			if($eFlowLasting['id'] === $eFlow['id']) {
				unset($lasting[$lane]);
				return;
			}
		}

	}

	/**
	 * Un trait par période en cours, dans son couloir.
	 *
	 * Le trait démarre en haut de la bande de sa semaine de départ et s'arrête en
	 * bas de celle de sa dernière semaine, bouts arrondis. Entre les deux il
	 * déborde de chaque côté pour couvrir l'écart des lignes et la bordure des
	 * bandes, sinon il se lit comme une suite de tirets.
	 */
	protected function getLanes(array $lasting, array $starting, array $stopping, bool $first, bool $last, bool $write = FALSE, ?array $linking = NULL): string {

		$starts = array_map(fn(Flow $eFlow) => $eFlow['id'], $starting);
		$stops = array_map(fn(Flow $eFlow) => $eFlow['id'], $stopping);

		$h = '';

		foreach($lasting as $lane => $eFlow) {

			$classes = 'flow-timeline-lane';
			$bounds = [];

			if($first and in_array($eFlow['id'], $starts)) {
				$classes .= ' flow-timeline-lane-start';
				$bounds[] = 'start';
			}

			if($last and in_array($eFlow['id'], $stops)) {
				$classes .= ' flow-timeline-lane-stop';
				$bounds[] = 'stop';
			}

			$h .= '<div class="'.$classes.'" data-flow="'.$eFlow['id'].'" style="--flow-lane: '.$lane.'; color: '.$eFlow['action']['color'].'; background-color: '.$eFlow['action']['color'].';">';

				// Une poignée à chaque bout : elle étend ou raccourcit la période sans
				// toucher à l'autre borne.
				if($write and $eFlow->canWrite()) {

					foreach($bounds as $bound) {
						$h .= '<a class="flow-timeline-lane-handle flow-timeline-lane-handle-'.$bound.'" data-bound="'.$bound.'" title="'.($bound === 'start' ? s("Glisser pour changer la semaine de début") : s("Glisser pour changer la semaine de fin")).'"></a>';
					}

				}

			$h .= '</div>';

		}

		// Le pointillé qui relie la pastille de l'intervention à son trait : il ne
		// se dessine que sur la ligne de l'intervention elle-même.
		foreach($linking ?? $starting as $eFlow) {

			foreach($lasting as $lane => $eFlowLasting) {

				if($eFlowLasting['id'] === $eFlow['id']) {
					$h .= '<div class="flow-timeline-lane-link" style="--flow-lane: '.$lane.'; border-color: '.$eFlow['action']['color'].';"></div>';
				}

			}

		}

		return $h;

	}

	protected function getFlowRow(\util\FormUi $form, Flow $eFlow, array $flows, int $week, bool $write, bool $first, bool $last, array $lasting = [], array $starting = [], array $stopping = [], bool $bandFirst = FALSE, bool $bandLast = FALSE, array $delays = []): string {

		$eAction = $eFlow['action'];

		$h = '<div class="flow-timeline-row" data-flow="'.$eFlow['id'].'" data-field="'.($eFlow['weekOnly'] !== NULL ? 'only' : 'start').'" data-week="'.$week.'">';

			// Le départ d'un délai part du centre de la pastille de la dernière ligne
			// de la semaine et descend vers le repère suivant.
			foreach($delays as $delay) {
				$h .= '<span class="flow-timeline-delay flow-timeline-delay-start-row" data-delay="'.$delay['group'].'" style="--chain: 0;"></span>';
			}

			$movable = ($write and $eFlow->canWrite());

			$h .= '<div class="flow-timeline-pill flow-timeline-pill-expected'.($movable ? ' flow-timeline-pill-movable' : '').'" data-flow="'.$eFlow['id'].'" style="color: '.$eAction['color'].'; border-color: '.$eAction['color'].';" title="'.encode($eAction['name']).'">'.\work\ActionUi::getShort($eAction).'</div>';

			// Les traits de ce qui se répète passent entre la pastille et la fiche.
			// Le coude n'est dessiné que sur la ligne de l'intervention qui démarre.
			$h .= '<div class="flow-timeline-lanes">';
				$h .= $this->getLanes($lasting, $starting, $stopping, $bandFirst, $bandLast, $write, array_filter($starting, fn(Flow $eStart) => $eStart['id'] === $eFlow['id']));
			$h .= '</div>';

			$h .= '<div class="flow-timeline-card">';

				$h .= '<div class="flow-timeline-card-head">';

					if($write) {
						$h .= '<label class="flow-timeline-select batch-checkbox">';
							$h .= $this->getBatchCheckbox($form, $eFlow, $week);
						$h .= '</label>';
					}

					$h .= '<a href="/sequence/flow:update?id='.$eFlow['id'].'" class="flow-timeline-text">';
						$h .= \work\ActionUi::text($eFlow).' ';
						$h .= self::getFlowComplement($eFlow);
					$h .= '</a>';

					if($eFlow['frequency'] !== NULL) {
						$h .= '<span class="flow-timeline-frequency" style="color: '.$eAction['color'].'; border-color: '.$eAction['color'].';">'.$this->p('frequency')->values[$eFlow['frequency']].'</span>';
					}

					// La description passe dans l'en-tête de la fiche, à droite du titre,
					// comme sur la page de série — et non sur une ligne à elle.
					$h .= $this->getComment($eFlow);

					$h .= '<div class="flow-timeline-card-side">';
						$h .= $this->getMore($eFlow);
					$h .= '</div>';

				$h .= '</div>';

				$h .= $this->getTools($eFlow);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getFlowComplement(Flow $eFlow): string {

		$h = '';

		foreach($eFlow['cMethod?']() as $eMethod) {
			$h .= ' <span class="flow-method-name">'.encode($eMethod['name']).'</span> ';
		}

		return $h;

	}

	protected function getBatchCheckbox(\util\FormUi $form, Flow $eFlow, string $week): string {

		return $form->inputCheckbox('batch[]', $eFlow['id'], [
			'data-week' => $week,
			'oninput' => 'Flow.changeSelection()',
			'data-flow' => $eFlow['id']
		]);

	}

	public function getBatch(): string {

		$menu = '<div class="batch-planned">';
			$menu .= '<a data-dropdown="top-start" class="batch-one-item">';
				$menu .= \Asset::icon('watch');
				$menu .= '<span>'.s("Planifier").'</span>';
			$menu .= '</a>';
			$menu .= $this->getBatchPlanned('batch-flow-one-form');
		$menu .= '</div>';

		$menu .= '<a class="batch-one-item batch-update">'.\Asset::icon('gear-fill').'<span>'.s("Modifier").'</span></a>';

		$menu .= '<a data-ajax-submit="/sequence/flow:doDeleteCollection" class="batch-one-item batch-item-danger" data-confirm="'.s("Confirmer la suppression de cette intervention ?").'">'.\Asset::icon('trash').'<span>'.s("Supprimer").'</span></a>';

		$h = \util\BatchUi::one('batch-flow-one', $menu);

		$menu = '<a data-dropdown="top-start" class="batch-item">';
			$menu .= \Asset::icon('watch');
			$menu .= '<span>'.s("Planifier").'</span>';
		$menu .= '</a>';
		$menu .= $this->getBatchPlanned('batch-flow-form');

		$danger = '<a data-ajax-submit="/sequence/flow:doDeleteCollection" data-confirm="'.s("Confirmer la suppression de ces interventions ?").'" class="batch-item batch-item-danger">';
			$danger .= \Asset::icon('trash');
			$danger .= '<span>'.s("Supprimer").'</span>';
		$danger .= '</a>';

		$h .= \util\BatchUi::group('batch-flow', $menu, $danger, title: s("Pour les interventions sélectionnées"));

		return $h;

	}

	protected function getBatchPlanned(string $formId) {

		$h = '<div class="dropdown-list bg-secondary">';
			$h .= '<a data-ajax-submit="/sequence/flow:doIncrementWeekCollection" data-ajax-target="#'.$formId.'" class="dropdown-item" post-increment="-1">'.s("Une semaine plus tôt").'</a>';
			$h .= '<a data-ajax-submit="/sequence/flow:doIncrementWeekCollection" data-ajax-target="#'.$formId.'" class="dropdown-item" post-increment="1">'.s("Une semaine plus tard").'</a>';
			$h .= '<a data-ajax-submit="/sequence/flow:incrementWeekCollection" data-ajax-method="get" data-ajax-target="#'.$formId.'" class="dropdown-item">'.s("Décaler davantage").'</a>';
		$h .= '</div>';

		return $h;

	}

	protected function getTimelineHeader(\util\FormUi $form, bool $write): string {

		$h = '<div class="flow-timeline flow-timeline-header">';

			$h .= '<div></div>';
			$h .= '<div class="util-table-header flow-timeline-axis-header">';
				$h .= s("Semaine");
			$h .= '</div>';
			$h .= '<div></div>';
			$h .= '<div class="util-table-header flow-timeline-rows-header">';
				if($write) {
					$h .= '<label class="flow-timeline-update" title="'.s("Tout cocher / Tout décocher").'">';
						$h .= $form->inputCheckbox(attributes: ['onclick' => 'Flow.changeAllSelection(this)', 'class' => 'batch-all']);
						$h .= '<span>'.s("Intervention").'</span>';
					$h .= '</label>';
				} else {
					$h .= s("Intervention");
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getEmptyTimeline(Sequence $eSequence, bool $write): string {

		$h = '<div id="flow-wrapper" data-sequence="'.$eSequence['id'].'">';

			$h .= '<p class="util-empty">';
				if($write) {
					$h .= s("Vous n'avez pas encore saisi d'intervention pour cet itinéraire technique.");
				} else {
					$h .= s("Il n'y a pas encore d'intervention pour cet itinéraire technique.");
				}
			$h .= '</p>';

		$h .= '</div>';

		return $h;

	}

	public function planTask(Sequence $eSequence): string {

		if($eSequence->canWrite() === FALSE) {
			return '';
		}

		$h = '<div>';
			$h .= '<a class="btn btn-outline-primary" href="/sequence/flow:create?sequence='.$eSequence['id'].'">'.\Asset::icon('plus-circle').' '.s("Nouvelle intervention").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function getMore(\series\Task|Flow $eFlow): string {

		switch($eFlow['action']['fqn']) {

			case ACTION_RECOLTE :
				if($eFlow instanceof \series\Task and $eFlow['harvest'] > 0.0) {

					// Une période sans récolte (la semaine, ou le jour d'une colonne du planning) n'écrit rien, pas même
					// une annotation vide, qui garderait sa marge.
					$harvest = $eFlow->offsetExists('harvestPeriod') ? $eFlow->format('harvestPeriod') : $eFlow->format('harvest');

					if($harvest === NULL) {
						return '';
					}

					return '<span class="annotation" style="color: '.$eFlow['action']['color'].'">'.$harvest.'</span>';

				} else {
					return '';
				}

			case ACTION_FERTILISATION :
				if($eFlow['fertilizer'] !== NULL) {

					[$major, $minor] = new \sequence\FlowUi()->getFertilizer($eFlow);

					$major = array_map(fn($value) => '<span class="annotation">'.$value.'</span>', $major);
					$minor = array_map(fn($value) => '<span class="annotation">'.$value.'</span>', $minor);

					$h = '<span class="flow-timeline-fertilizer">';
						$h .= implode('&nbsp;&nbsp;', $major);
						if($major and $minor) {
							$h .= ' + ';
						}
						$h .= implode('&nbsp;&nbsp;', $minor);
					$h .= '</span>';

					return $h;

				} else {
					return '';
				}

			default :
				return '';

		}

	}

	public function getComment(\series\Task|Flow $eFlow): string {

		$h = '';

		if($eFlow['description'] !== NULL) {
			$h .= '<div class="flow-timeline-description">';
				$h .= nl2br(encode($eFlow['description']));
			$h .= '</div>';
		}

		return $h;

	}

	public function getFertilizer(\series\Task|Flow $eFlow): array {

		return new \work\RoutineUi()->getSeparateFertilizer($eFlow['fertilizer']);

	}

	public function getTools(\series\Task|Flow $eFlow): string {

		$eFlow->expects(['cTool?', 'cProduct?']);

		$cTool = new \Collection()
			->mergeCollection($eFlow['cProduct?']())
			->mergeCollection($eFlow['cTool?']());

		$h = '';

		if($cTool->notEmpty()) {

			$h = '<div class="flow-timeline-tools">';
				$h .= new \work\ToolUi()->getList($cTool);
			$h .= '</div>';

		}

		return $h;

	}

	public function create(Flow $eFlow, \Collection $cAction): \Panel {

		$form = new \util\FormUi();

		$eFlow->merge([
			'crop' => new Crop(),
			'yearOnly' => 0,
			'yearStart' => 0,
			'yearStop' => 0,
			'frequency' => Flow::W1,
			'action' => new \work\Action(),
			'cTool?' => fn() => new \Collection(),
			'hasTools' => new \Collection(),
			'cProduct?' => fn() => new \Collection(),
			'hasProducts' => new \Collection(),
			'cMethod?' => fn() => new \Collection(),
			'hasMethods' => new \Collection(),
		]);

		$eSequence = $eFlow['sequence'];

		if($eSequence['cCrop']->count() > 1) {
			$cAction->map(fn($eAction) => $eAction['disabled'] = ($eAction['fqn'] === ACTION_RECOLTE));
		}

		$h = '';

		$h .= $form->openAjax('/sequence/flow:doCreate', ['id' => 'flow-create', 'autocomplete' => 'off']);

			$h .= $form->hidden('sequence', $eSequence['id']);

			$h .= $this->getCropField($form, $eSequence, $eFlow);

			$h .= $form->dynamicGroup($eFlow, 'action', function($d) use($cAction) {
				$d->values = $cAction;
			});

			$h .= $this->getProductsGroup($form, $eFlow);

			$h .= '<div id="flow-create-fertilizer">';
				$h .= $this->getFertilizerField($form, $eFlow);
			$h .= '</div>';

			if($eFlow['sequence']['cycle'] === Sequence::PERENNIAL) {
				$h .= $this->getSeasonField($form, $eFlow);
			}

			$h .= $this->getPeriodField($form, $eFlow);

			$h .= $this->getMethodsGroup($form, $eFlow);
			$h .= $this->getToolsGroup($form, $eFlow);

			$h .= $form->dynamicGroup($eFlow, 'description');


			$h .= $form->group(
				content: $form->submit(s("Ajouter l'intervention"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-flow-create',
			title: s("Ajouter une intervention"),
			subTitle: SequenceUi::getPanelHeader($eSequence),
			body: $h
		);

	}

	public function update(Sequence $eSequence, Flow $eFlow, \Collection $cAction): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/sequence/flow:doUpdate', ['id' => 'flow-update', 'autocomplete' => 'off']);

			$h .= $form->hidden('id', $eFlow['id']);

			$h .= $this->getCropField($form, $eSequence, $eFlow);

			$h .= $form->dynamicGroup($eFlow, 'action', function($d) use($cAction) {
				$d->values = $cAction;
			});

			$h .= $this->getProductsGroup($form, $eFlow);

			if($eFlow['action']['fqn'] === ACTION_FERTILISATION) {
				$h .= $this->getFertilizerField($form, $eFlow);
			}

			if($eFlow['sequence']['cycle'] === Sequence::PERENNIAL) {
				$h .= $this->getSeasonField($form, $eFlow);
			}

			$h .= $this->getPeriodField($form, $eFlow);

			$h .= $this->getMethodsGroup($form, $eFlow);
			$h .= $this->getToolsGroup($form, $eFlow);

			$h .= $form->dynamicGroup($eFlow, 'description');


			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-flow-update',
			title: s("Modifier une intervention"),
			subTitle: SequenceUi::getPanelHeader($eSequence),
			body: $h,
		);

	}

	public function getMethodsGroup(\util\FormUi $form, Flow $eFlow): string {

		$h = '<div data-ref="methods" data-structure="'.$eFlow['sequence']['structure']['id'].'">';
			if($eFlow['hasMethods']->notEmpty()) {
				$h .= $form->dynamicGroup($eFlow, 'methods');
			}
		$h .= '</div>';

		return $h;

	}

	public function getProductsGroup(\util\FormUi $form, Flow $eFlow): string {

		$h = '<div data-ref="products" data-structure="'.$eFlow['sequence']['structure']['id'].'">';
			$h .= $this->getProductsField($form, $eFlow);
		$h .= '</div>';

		return $h;

	}

	public function getProductsField(\util\FormUi $form, Flow $eFlow): string {

		$eFlow->expects(['action', 'hasProducts']);

		if($eFlow['action']->empty() or $eFlow['hasProducts']->empty()) {
			return '';
		}

		return match($eFlow['action']['fqn']) {

			ACTION_TRAITEMENT => $form->dynamicGroup($eFlow, 'products', function(\PropertyDescriber $d) {
				$d->label = s("Produit");
				$d->multiple = FALSE;
				$d->autocompleteDefault = fn(Flow $e) => $e['cProduct?']()->empty() ? new \work\Tool() : $e['cProduct?']()->first();
			}),

			ACTION_FERTILISATION => $form->dynamicGroup($eFlow, 'products', function(\PropertyDescriber $d) {
				$d->label = s("Intrants");
				$d->placeholder = s("Ajoutez un intrant");
				$d->multiple = TRUE;
			}),

			default => ''

		};

	}

	public function getToolsGroup(\util\FormUi $form, Flow $eFlow): string {

		$h = '<div data-ref="tools" data-structure="'.$eFlow['sequence']['structure']['id'].'">';
			if($eFlow['hasTools']->notEmpty()) {
				$h .= $form->dynamicGroup($eFlow, 'tools');
			}
		$h .= '</div>';

		return $h;

	}

	public function updateIncrementWeekCollection(\Collection $cTask): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/sequence/flow:doIncrementWeekCollection', ['autocomplete' => 'off']);

			$h .= $form->group(
				p("Intervention", "Interventions", $cTask->count()),
				$this->getFlowsField($form, $cTask),
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
			id: 'panel-flow-increment',
			title: s("Décaler"),
			body: $h
		);

	}

	public function getFlowsField(\util\FormUi $form, \Collection $cFlow): string {

		$display = ($cFlow->count() > 1) ? 'checkbox' : 'hidden';

		$h = '<table class="stick-xs">';

			$h .= '<thead>';
				$h .= '<tr>';
					if($display === 'checkbox') {
						$h .= '<th></th>';
					}
					$h .= '<th>'.s("Action").'</th>';
					$h .= '<th>'.s("Semaine").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			$h .= '<tbody>';

				foreach($cFlow as $eFlow) {
					$h .= '<tr>';
						if($display === 'checkbox') {
							$h .= '<td class="td-checkbox">';
								$h .= '<label>'.$form->inputCheckbox('ids[]', $eFlow['id'], ['checked']).'</label>';
							$h .= '</td>';
						}
						$h .= '<td>'.\work\ActionUi::text($eFlow).'</td>';
						$h .= '<td>'.new SequenceUi()->getTextualWeek($eFlow, $eFlow['action']).'</td>';
					$h .= '</tr>';
				}

			$h .= '</body>';

		$h .= '</table>';

		if($display === 'hidden') {
			foreach($cFlow as $eFlow) {
				$h .= $form->hidden('ids[]', $eFlow['id']);
			}
		}

		return $h;

	}

	public function getFertilizerField(\util\FormUi $form, Flow $eFlow): string {

		$eFlow->expects(['action']);

		return $form->group(
			self::p('fertilizer')->label,
			new \work\RoutineUi()->getFieldFertilizer($form, 'fertilizer', $eFlow['fertilizer'] ?? NULL),
			['class' => ($eFlow['action']->notEmpty() and $eFlow['action']['fqn'] === ACTION_FERTILISATION) ? '' : 'hide', 'data-wrapper' => 'fertilizer']
		);

	}

	public function getCropField(\util\FormUi $form, Sequence $eSequence, Flow $eFlow): string {

		$eSequence->expects(['cCrop']);

		if($eSequence['cCrop']->count() > 1) {

			$values = [
				NULL => s("Partagé")
			];

			foreach($eSequence['cCrop'] as $eCrop) {
				$values[$eCrop['id']] = \plant\PlantUi::getVignette($eCrop['plant'], '2rem').' '.encode($eCrop['plant']['name']);
			}

			return $form->group(
				$this->p('crop'),
				$form->radios('crop', $values, $eFlow->empty() ? NULL : $eFlow['crop'], attributes: [
					'required' => TRUE,
					'callbackRadioAttributes' => function() {
						return [
							'onclick' => 'Flow.createSelectCultivation(this)'
						];
					}
				])
			);

		} else {
			return $form->hidden('crop', $eSequence['cCrop']->first()['id']);
		}

	}

	protected function getPeriodField(\util\FormUi $form, Flow $eFlow): string {

		if(($eFlow['weekStart'] ?? NULL) === NULL) {
			$styleOnly = '';
			$styleInterval = 'display: none';
			$period = 'only';
		} else {
			$styleOnly = 'display: none';
			$styleInterval = '';
			$period = 'interval';
		}

		$h = $form->hidden('period', $period, ['id' => 'flow-period']);

		$h .= '<div id="flow-period-only" style="'.$styleOnly.'">';
			$h .= $form->group(
				self::p('weekOnly')->label,
				$this->getWeekField($form, $eFlow, fn($name) => $name.'Only').
				'<div class="field-action"><a data-action="flow-period-interval">'.s("Répéter l'intervention plusieurs fois dans la saison").'</a></div>',
				['data-wrapper' => 'weekOnly yearOnly']
			);
		$h .= '</div>';

		$h .= '<div id="flow-period-interval" style="'.$styleInterval.'">';

			$h .= $form->group(
				self::p('weekStart')->label,
				$this->getWeekField($form, $eFlow, fn($name) => $name.'Start'),
				['data-wrapper' => 'weekStart yearStart']
			);

			$h .= $form->group(
				self::p('weekStop')->label,
				$this->getWeekField($form, $eFlow, fn($name) => $name.'Stop').
				'<div class="field-action"><a data-action="flow-period-only">'.s("Ne pas répéter l'intervention dans la saison").'</a></div>',
				['data-wrapper' => 'weekStop yearStop']
			);

			$h .= $form->dynamicGroup($eFlow, 'frequency');

		$h .= '</div>';

		return $h;

	}

	protected function getSeasonField(\util\FormUi $form, Flow $eFlow): string {

		if(($eFlow['seasonStart'] ?? NULL) === NULL) {
			$styleOnly = '';
			$styleInterval = 'display: none';
			$season = 'only';
		} else {
			$styleOnly = 'display: none';
			$styleInterval = '';
			$season = 'interval';
		}

		$values = function($d) use($eFlow) {

			$d->values = [];

			$lastSeason = $eFlow['sequence']['perennialLifetime'] ?? SequenceSetting::MAX_SEASON_STOP;

			for($season = 1; $season <= $lastSeason; $season++) {
				$d->values[$season] = s("Saison {value}", $season);
			}

		};

		$h = $form->hidden('season', $season, ['id' => 'flow-season']);

		$h .= '<div id="flow-season-only" style="'.$styleOnly.'">';
			$h .= $form->group(
				self::p('seasonOnly')->label,
				$form->dynamicField($eFlow, 'seasonOnly', $values).
				'<div class="field-action"><a data-action="flow-season-interval">'.s("Répéter l'intervention sur plusieurs saisons").'</a></div>'
			);
		$h .= '</div>';

		$h .= '<div id="flow-season-interval" style="'.$styleInterval.'">';

			$h .= $form->group(
				self::p('seasonStart')->label,
				$form->dynamicField($eFlow, 'seasonStart', $values)
			);

			$h .= $form->group(
				self::p('seasonStop')->label,
				$form->dynamicField($eFlow, 'seasonStop', $values).
				'<div class="field-action"><a data-action="flow-season-only">'.s("Ne pas répéter l'intervention sur plusieurs saisons").'</a></div>'
			);

		$h .= '</div>';

		return $h;

	}

	public function getWeekField(\util\FormUi $form, Flow $eFlow, \Closure $name): string {

		$h = '<div class="flow-write-week">';
			$h .= $form->dynamicField($eFlow, $name('week'));
			if($eFlow['sequence']['cycle'] === Sequence::ANNUAL) {
				$h .= $form->dynamicField($eFlow, $name('year'));
			}
		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Flow::model()->describer($property, [
			'crop' => s("Production"),
			'action' => s("Intervention"),
			'method' => s("Méthode de travail"),
			'fertilizer' => s("Apports"),
			'description' => s("Observation sur l'intervention"),
			'seasonOnly' => s("Quelle saison pour cette intervention ?"),
			'seasonStart' => s("Première saison de l'intervention"),
			'seasonStop' => s("Dernière saison de l'intervention"),
			'weekOnly' => s("Semaine de l'intervention"),
			'weekStart' => s("Début de l'intervention"),
			'weekStop' => s("Fin de l'intervention"),
			'frequency' => s("Fréquence de l'intervention"),
			'methods' => s("Méthodes de travail"),
			'products' => s("Produits"),
			'tools' => s("Matériel nécessaire")
		]);

		switch($property) {

			case 'description' :
				$d->after = \util\FormUi::info(s("Le commentaire est facultatif et permet d'apporter des précisions sur l'intervention à réaliser."));
				break;

			case 'action' :
				$d->field = 'radio';
				$d->attributes = [
					'columns' => 3,
					'callbackRadioAttributes' => function(\work\Action $eAction) {
						return [
							'disabled' => ($eAction['disabled'] ?? FALSE) ? 'disabled' : NULL,
							'data-action' => 'flow-write-action-change',
							'data-fqn' => $eAction['fqn'],
							'data-structure' => $eAction['structure']['id'],
						];
					}
				];
				$d->required = TRUE;
				break;

			case 'weekOnly' :
			case 'weekStart' :
			case 'weekStop' :
				$d->field = fn(\util\FormUi $form, Flow $e, string $field) => $form->week($field, isset($e[$property]) ? \Clock::year().'-W'.sprintf('%02d', $e[$property]) : NULL, ['withYear' => FALSE]);
				break;

			case 'yearOnly' :
			case 'yearStart' :
			case 'yearStop' :
				$d->field = 'select';
				$d->values = self::getYearsSelect();
				$d->required = TRUE;
				break;

			case 'seasonOnly' :
			case 'seasonStart' :
			case 'seasonStop' :
				$d->field = 'select';
				break;

			case 'frequency' :
				$d->values = [
					Flow::W1 => s("Chaque semaine"),
					Flow::W2 => s("Toutes les 2 semaines"),
					Flow::W3 => s("Toutes les 3 semaines"),
					Flow::W4 => s("Toutes les 4 semaines"),
					Flow::M1 => s("1 fois par mois"),
				];
				$d->field = 'select';
				break;

			case 'methods' :
				$d->autocompleteDefault = fn(Flow $e) => ($e['cMethod?'] ?? $e->expects(['cMethod?']))();
				$d->autocompleteBody = function(\util\FormUi $form, Flow $e) {
					$e->expects([
						'action',
						'sequence' => ['structure']
					]);
					return [
						'action' => $e['action']['id'],
						'structure' => $e['sequence']['structure']['id']
					];
				};
				new \work\MethodUi()->query($d, TRUE);
				$d->wrapper = 'methods';
				break;

			case 'tools' :
				$d->autocompleteDefault = fn(Flow $e) => ($e['cTool?'] ?? $e->expects(['cTool?']))();
				$d->autocompleteBody = function(\util\FormUi $form, Flow $e) {
					$e->expects([
						'action',
						'sequence' => ['structure']
					]);
					return [
						'action' => $e['action']['id'],
						'structure' => $e['sequence']['structure']['id']
					];
				};
				new \work\ToolUi()->query($d, TRUE);
				$d->wrapper = 'tools';
				break;

			case 'products' :
				$d->autocompleteDefault = fn(Flow $e) => ($e['cProduct?'] ?? $e->expects(['cProduct?']))();
				$d->autocompleteBody = function(\util\FormUi $form, Flow $e) {
					$e->expects([
						'action',
						'sequence' => ['structure']
					]);
					return [
						'action' => $e['action']['id'],
						'structure' => $e['sequence']['structure']['id']
					];
				};
				new \work\ToolUi()->queryProduct($d);
				$d->wrapper = 'products';
				break;

		}

		return $d;

	}

	public static function getYearsSelect(): array {
		return [
			-1 => s("Année « N-1 »"),
			0 => s("Année « N »"),
			1 => s("Année « N+1 »")
		];
	}
	
}
?>
