<?php
namespace planning;

/**
 * La page d'une intervention du nouveau planning (L5b), sur le modèle de la note (HF, piste B retenue par
 * le 19/10) : la tête de la fiche, son titre, sa série et ses gestes, est la bande du panneau, et la
 * fiche pose dessous ses informations en colonnes, quand elle est planifiée et réalisée et ce qui la décrit, et
 * pour une récolte ce qui a été récolté, sur toute la largeur (HI). Suit le « Temps de travail » si la ferme
 * suit le temps, puis les sections sans onglets : les commentaires, le détail (ce que l'action a posé,
 * l'assolement, les photos), l'historique.
 *
 * Le détail est celui de `/tache/{id}`, rendu par morceaux par `\series\TaskUi`. Ses gestes (affecter, modifier,
 * commenter, assoler, photographier) ouvrent les formulaires du planning actuel et rechargent ce panneau,
 * puisque c'est son adresse que porte la page (V-Planning, écart b).
 *
 * Aucune coche : le passage en fait reste au planning (« virer la possibilité de passer en fait »).
 */
class TaskDetailUi {

	/**
	 * Le lieu que la roue de la page poste avec une suppression : la réponse ramène alors à la semaine de
	 * l'intervention quand la page n'a pas d'écran sous elle (`PlanningTaskDetail.deleted()`).
	 */
	const string ORIGIN = 'planning';

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

	}

	/**
	 * `week` est la semaine de l'écran d'où la page s'ouvre : la réponse d'une saisie de temps y remet la
	 * carte à jour. Ouverte d'ailleurs, la page n'a pas de carte à suivre.
	 */
	public static function url(\series\Task $eTask, ?string $week = NULL): string {

		$url = \structure\StructureUi::urlStructure($eTask['structure']).'/planning-v2/intervention/'.$eTask['id'];

		if($week !== NULL) {
			$url .= '?'.http_build_query(['week' => $week]);
		}

		return $url;

	}

	/**
	 * Une autre semaine dans le tableau du « Temps de travail » (`taskDetail:week`), sans quitter la page.
	 * `$day` est le jour que la réponse montre dans le tableau : un jour saisi du dépli du total.
	 */
	public static function urlWeek(\series\Task $eTask, ?string $week, string $weekShown, ?string $day = NULL): string {

		$arguments = array_filter([
			'id' => $eTask['structure']['id'],
			'task' => $eTask['id'],
			'weekShown' => $weekShown,
			'week' => $week,
			'day' => $day
		], fn($value) => $value !== NULL);

		return '/planning/taskDetail:week?'.http_build_query($arguments);

	}

	/**
	 * `$days` sont les sept jours de la semaine que le « Temps de travail » montre (`TaskDetailLib::getDays()`),
	 * `$navigation` cette semaine et ses voisines (`getNavigation()`) et `$timesByDay` les jours saisis
	 * (`getTimesByDay()`).
	 *
	 * La fiche précède `#planning-task-detail` : util-panel-tinted (component.css) ne l'accroche à la bande que
	 * premier enfant du corps.
	 */
	public function get(\series\Task $eTask, ?string $week, \Collection $cPlace, \Collection $cPhoto, \Collection $cUser, \Collection $cComment, array $history, array $days, array $foot, array $navigation, array $timesByDay): \Panel {

		$uiTask = new \series\TaskUi();

		// Le tableau du « Temps de travail » porte l'affectation (lot CT) : la fiche ne la garde que dans une
		// ferme qui ne suit pas le temps, où le tableau n'existe pas.
		$assign = ($eTask['structure']->hasTime() === FALSE);

		$sections = [
			$eTask['structure']->hasTime() ? $this->getTime($eTask, $week, $cUser, $days, $foot, $navigation, $timesByDay) : '',
			$this->getComments($eTask, $cComment),
			$uiTask->getDetailWork($eTask, $cPlace).$uiTask->getDetailPlaceAndPhotos($eTask, $cPlace, $cPhoto),
			$this->getHistorySection($eTask, $history)
		];

		$h = $this->getRecord($eTask, $week, $cUser, $assign);

		$h .= '<div id="planning-task-detail" data-structure="'.$eTask['structure']['id'].'" data-task="'.$eTask['id'].'" data-week="'.($week ?? '').'">';

			// Une section sans contenu n'est pas posée : elle garderait l'écart qui la sépare de la précédente.
			foreach($sections as $section) {
				if($section !== '') {
					$h .= '<section class="planning-detail-section">'.$section.'</section>';
				}
			}

		$h .= '</div>';

		return new \Panel(
			id: 'panel-planning-task',
			documentTitle: strip_tags($uiTask->getTitle($eTask)),
			header: $this->getRecordMain($eTask, $cUser, $assign),
			body: $h,
			close: 'reloadOnCascade',
			attributes: ['class' => 'util-panel-tinted']
		);

	}

	/**
	 * La tête de la fiche, que le panneau porte dans sa bande : le titre, qui ne nomme que l'action et l'espèce
	 * (retour 191), puis les gestes, la roue, et affecter quand le tableau ne le porte pas (`$assign`). La série
	 * ouvre les informations de la fiche (`getRecord()`). Sans marque : « vire l'icône de calendrier ».
	 */
	protected function getRecordMain(\series\Task $eTask, \Collection $cUser, bool $assign): string {

		$h = '<div class="util-record-head">';
			$h .= '<div class="util-record-identity">';
				$h .= '<h3 class="util-record-title">'.new \series\TaskUi()->getTitle($eTask).'</h3>';
			$h .= '</div>';
		$h .= '</div>';

		$actions = new \series\TaskUi()->getDetailActions($eTask, $cUser, $assign, self::ORIGIN);

		if($actions !== '') {
			$h .= '<div class="util-record-actions">'.$actions.'</div>';
		}

		return $h;

	}

	/**
	 * Les informations de la fiche, pendues au trait qui part de la bande (util-record-branches), une par rangée,
	 * la récolte en dernier (`getHarvest()`). Une intervention qui n'a aucune information, ni semaine ni ce qui la décrit, et n'est pas
	 * une récolte, n'a pas de fiche : sa tête suffit. `data-task` laisse le surlignage d'une modification
	 * (`series/page/task.p.php`) viser la fiche de cette intervention.
	 */
	protected function getRecord(\series\Task $eTask, ?string $week, \Collection $cUser, bool $assign): string {

		$items = new \series\TaskUi()->getDetailPanelItems($eTask, $cUser, $assign);
		$harvest = ($eTask['action']['fqn'] === ACTION_RECOLTE);

		if($items === [] and $harvest === FALSE) {
			return '';
		}

		$h = '<article class="util-record util-record-branches" id="planning-task-detail-intervention" data-task="'.$eTask['id'].'">';
			$h .= '<ul class="util-record-list">';
				foreach($items as $item) {
					$h .= \main\DesignUi::getRecordItem($item['label'], $item['value']);
				}
				if($harvest) {
					$h .= \main\DesignUi::getRecordItem(s("Récolte"), $this->getHarvest($eTask, $week), class: 'util-record-item-wide');
				}
			$h .= '</ul>';
		$h .= '</article>';

		return $h;

	}

	/**
	 * Les commentaires, sous leur titre et le bouton qui en ajoute, comme sur `/tache/{id}`. Sans commentaire,
	 * le titre et son bouton suffisent ; pour qui ne peut pas commenter, il n'y a rien à montrer.
	 */
	protected function getComments(\series\Task $eTask, \Collection $cComment): string {

		if(
			$cComment->empty() and
			new \series\Comment(['structure' => $eTask['structure']])->canCreate() === FALSE
		) {
			return '';
		}

		return new \series\TaskUi()->getDetailComments($eTask, $cComment);

	}

	/**
	 * La récolte, valeur de la fiche que `doHarvest` redessine (HI, « mets la récolte dans le bloc au-dessus ») :
	 * le total, à la couleur de l'action comme celui de la colonne Semaine de la grille (V23, « récolte
	 * totale en bleu comme d'hab »), somme des jours que la page prépare (`harvestTotal`), ou son absence, puis
	 * à sa droite la frise des jours, du plus ancien au plus récent (HK, « le détail par jour à droite de la qte
	 * totale »). Pour qui peut écrire, la quantité d'un jour porte un crayon qui ouvre la modification du jour
	 * dans son étape (`getHarvestDay()`), et la frise finit sur une étape ouverte qui ajoute une récolte
	 * (`getHarvestEntry()`) ; le formulaire complet du planning actuel reste derrière la roue (« Compléter la
	 * récolte »).
	 */
	public function getHarvest(\series\Task $eTask, ?string $week): string {

		$dates = $eTask['harvestDates'];
		$canWrite = $eTask->canWrite();

		$h = '<div class="planning-detail-harvest" id="planning-detail-harvest">';

			if($dates !== []) {
				$h .= '<span class="annotation annotation-lg" style="color: '.$eTask['action']['color'].'">'.\product\UnitUi::getValue(round($eTask['harvestTotal'], 1), $eTask['harvestUnit']).'</span>';
			} else {
				$h .= '<span class="color-muted">'.s("Aucune récolte saisie.").'</span>';
			}

			if($dates !== [] or $canWrite) {

				$h .= '<ol class="util-procedure util-procedure-dots planning-detail-harvest-days">';

					foreach($dates as $date => $value) {

						$quantity = '<b>'.\product\UnitUi::getValue(round($value, 1), $eTask['harvestUnit']).'</b>';

						$h .= '<li class="util-procedure-step">';
							$h .= '<div class="planning-detail-harvest-line">';
								$h .= '<span class="planning-detail-harvest-date">'.\util\DateUi::numeric($date).'</span>';
								if($canWrite) {
									$h .= '<a class="util-quick" data-planning-detail-harvest>'.$quantity.' '.\Asset::icon('pencil-fill').'</a>';
								} else {
									$h .= $quantity;
								}
							$h .= '</div>';
							if($canWrite) {
								$h .= $this->getHarvestDay($eTask, $week, $date, $value);
							}
						$h .= '</li>';

					}

					if($canWrite) {
						$h .= '<li class="util-procedure-step util-procedure-step-open">';
							$h .= $this->getHarvestEntry($eTask, $week);
						$h .= '</li>';
					}

				$h .= '</ol>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La modification d'un jour, fermée sous sa ligne dans son étape (`PlanningTaskDetail`) : la quantité du jour, pré-remplie,
	 * qui remplace celle qu'il porte (`mode=replace`), le stock, qui reçoit l'écart (`getHarvestStock()`),
	 * « Enregistrer », « Annuler », et « Supprimer », qui remet le jour à zéro et emporte le choix du stock
	 * (`PlanningHarvestStock.copy()`).
	 */
	protected function getHarvestDay(\series\Task $eTask, ?string $week, string $date, float $value): string {

		$form = new \util\FormUi(['columns' => 1]);

		$h = '<div class="util-table planning-detail-harvest-form hide">';

		$h .= $form->openAjax('/planning/taskDetail:doHarvest', ['class' => 'util-table-row util-table-add-entry planning-detail-harvest-edit', 'autocomplete' => 'off']);

			$h .= '<div data-wrapper="harvestDates">';
				$h .= \util\DateUi::numeric($date);
				$h .= $form->hidden('harvestDate', $date);
			$h .= '</div>';

			$quantity = $form->dynamicField(new \series\Task(['harvestDay' => round($value, 3)]), 'harvestDay');
			$quantity .= $form->addon(\product\UnitUi::getSingular($eTask['harvestUnit']));

			$h .= '<div data-wrapper="harvest harvestDay harvestUnit">'.$form->inputGroup($quantity).'</div>';

			$h .= $this->getHarvestStock($form, 'planning-detail-harvest-stock-'.$date, $eTask);

			// Les champs cachés vont dans la dernière cellule, comme dans `getHarvestEntry()`.
			$h .= '<div class="util-table-add-actions">';
				$h .= $form->hidden('id', $eTask['structure']);
				$h .= $form->hidden('task', $eTask);
				$h .= $form->hidden('mode', 'replace');

				if($week !== NULL) {
					$h .= $form->hidden('week', $week);
				}

				$h .= $form->submit(s("Enregistrer"));
				$h .= '<a class="btn btn-outline-primary" data-planning-detail-harvest-cancel>'.s("Annuler").'</a>';
				$h .= '<a class="btn btn-outline-primary planning-detail-harvest-delete" data-ajax="/planning/taskDetail:doHarvest" post-id="'.$eTask['structure']['id'].'" post-task="'.$eTask['id'].'"'.($week !== NULL ? ' post-week="'.$week.'"' : '').' post-mode="replace" post-harvest-date="'.$date.'" post-harvest-day="0" data-planning-harvest-stock-copy="true" data-confirm="'.s("Supprimer la récolte de ce jour ?").'">'.s("Supprimer").'</a>';
			$h .= '</div>';

		$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien qui ajoute une récolte, dans l'étape ouverte qui finit la frise, puis la ligne de saisie qu'il ouvre
	 * à sa place (`PlanningTaskDetail`) : le jour, la quantité, et l'unité tant que l'intervention n'en a pas, puis
	 * le stock (`getHarvestStock()`).
	 */
	protected function getHarvestEntry(\series\Task $eTask, ?string $week): string {

		$h = '<a class="planning-detail-harvest-line planning-detail-harvest-add" data-planning-detail-harvest>'.\Asset::icon('plus-lg').'<span>'.s("Ajouter une récolte").'</span></a>';

		$form = new \util\FormUi(['columns' => 1]);

		$h .= '<div class="util-table planning-detail-harvest-form hide">';

		$h .= $form->openAjax('/planning/taskDetail:doHarvest', ['class' => 'util-table-row util-table-add-entry', 'autocomplete' => 'off']);

			$h .= '<div data-wrapper="harvestDates">'.$form->dynamicField($eTask, 'harvestDate').'</div>';

			// Le describer pose le focus au rendu, qui irait sur cette ligne cachée, et branche l'unité sur le champ
			// du stock du formulaire complet, que la ligne n'a pas.
			$quantity = $form->dynamicField($eTask, 'harvestMore', fn(\PropertyDescriber $d) => $d->attributes = ['step' => 0.001]);

			if($eTask['harvestUnit'] === NULL) {
				$quantity .= $form->dynamicField($eTask, 'harvestUnit', fn(\PropertyDescriber $d) => $d->attributes = []);
			} else {
				$quantity .= $form->addon(\product\UnitUi::getSingular($eTask['harvestUnit']));
			}

			$h .= '<div data-wrapper="harvest harvestMore harvestUnit">'.$form->inputGroup($quantity).'</div>';

			$h .= $this->getHarvestStock($form, 'planning-detail-harvest-stock-new', $eTask);

			// Les champs cachés vont dans la dernière cellule : en tête du formulaire, l'un d'eux serait la première
			// cellule de la ligne, et le jour perdrait l'alignement de sa colonne.
			$h .= '<div class="util-table-add-actions">';
				$h .= $form->hidden('id', $eTask['structure']);
				$h .= $form->hidden('task', $eTask);

				if($week !== NULL) {
					$h .= $form->hidden('week', $week);
				}

				$h .= $form->submit(s("Ajouter"));
				$h .= '<a class="btn btn-outline-primary" data-planning-detail-harvest-cancel>'.s("Annuler").'</a>';
			$h .= '</div>';

		$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	/**
	 * « Ajouter à un stock » d'une saisie de la récolte, sur une rangée à lui, sous le jour et la quantité
	 * (`HarvestEntryUi::getStock()`, DG-4). Chaque saisie a le sien, la récolte en portant plusieurs.
	 */
	protected function getHarvestStock(\util\FormUi $form, string $id, \series\Task $eTask): string {

		return '<div class="planning-detail-harvest-stock">'.new HarvestEntryUi()->getStock($form, $id, $eTask['eTaskStock']).'</div>';

	}

	/**
	 * La section « Historique » : son titre, puis le tableau que `doTime` et `doHarvest` remplacent par son id.
	 */
	protected function getHistorySection(\series\Task $eTask, array $history): string {

		$h = '<div class="util-title">';
			$h .= '<h3>'.s("Historique").'</h3>';
		$h .= '</div>';
		$h .= $this->getHistory($eTask, $history);

		return $h;

	}

	/**
	 * L'historique, sous la forme de l'historique d'une vente (`selling\HistoryUi::getList()`) :
	 * Date, Événement, Quantité, Par (`TaskDetailLib::getHistory()`). La quantité a sa colonne
	 * (R-Retours-0710, 160) : la récolte soulignée à la couleur de l'action, comme sur la carte de la
	 * semaine, le temps dans sa marque.
	 */
	public function getHistory(\series\Task $eTask, array $history): string {

		$h = '<div class="util-overflow-sm stick-xs" id="planning-detail-history">';

			$h .= '<table class="table-unstyled tr-bordered">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Date").'</th>';
						$h .= '<th>'.s("Événement").'</th>';
						$h .= '<th class="text-end">'.s("Quantité").'</th>';
						$h .= '<th>'.s("Par").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

					foreach($history as $event) {

						$h .= '<tr>';
							$h .= '<td class="td-min-content">'.\util\DateUi::numeric($event['date'], \util\DateUi::DATE_HOUR_MINUTE).'</td>';
							$h .= '<td>'.$this->getHistoryEvent($event).'</td>';
							$h .= '<td class="text-end td-min-content">'.$this->getHistoryQuantity($eTask, $event).'</td>';
							$h .= '<td>'.($event['user']->empty() ? '-' : $event['user']->getName()).'</td>';
						$h .= '</tr>';

					}

				$h .= '</tbody>';

			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	protected function getHistoryEvent(array $event): string {

		return match($event['event']) {
			TaskDetailLib::HISTORY_CREATED => s("Création de l'intervention"),
			\series\History::DONE, \series\History::TODO => \series\HistoryUi::p('event')->values[$event['event']],
			\series\History::TIME => s("Temps de travail du {date} pour {user}", [
				'date' => \util\DateUi::numeric($event['element']['day'], \util\DateUi::DAY_MONTH),
				'user' => $event['element']['worker']->empty() ? '-' : $event['element']['worker']->getName()
			]),
			\series\History::HARVEST => s("Récolte du {date}", [
				'date' => \util\DateUi::numeric($event['element']['day'], \util\DateUi::DAY_MONTH)
			]),
			TaskDetailLib::HISTORY_COMMENT => s("Commentaire"),
		};

	}

	/**
	 * Le temps et la récolte sont des écarts (`series\HistoryLib`) : le signe dit si le geste a ajouté ou
	 * retiré.
	 */
	protected function getHistoryQuantity(\series\Task $eTask, array $event): string {

		return match($event['event']) {
			\series\History::TIME => DurationUi::getSpentChange($event['element']['time']),
			\series\History::HARVEST => '<span class="annotation" style="color: '.$eTask['action']['color'].'">'.($event['element']['harvest'] < 0 ? '-' : '+').\product\UnitUi::getValue(round(abs($event['element']['harvest']), 1), $event['element']['harvestUnit']).'</span>',
			TaskDetailLib::HISTORY_CREATED, TaskDetailLib::HISTORY_COMMENT, \series\History::DONE, \series\History::TODO => '',
		};

	}

	/**
	 * Le « Temps de travail » (HI, « renomme équipe en temps de travail ») : le titre et, à sa droite, la tête
	 * de la semaine (`getNav()`, « mets le calendrier des semaines à droite au niveau du titre »), qui passe
	 * dessous quand la ligne ne la tient pas ; puis le total avec le prévu (`getTotal()`), qui déplie les jours
	 * saisis (`getWorked()`), et le tableau de cette semaine, une ligne par personne et une colonne par jour
	 * (`getGrid()`, lot CT, piste C retenue le 14/10 au retour 311), qui porte le temps et
	 * l'affectation.
	 *
	 * Chaque morceau a son identifiant : le choix d'une semaine redessine la tête et le tableau, une saisie le
	 * total, les jours saisis, puis la case, la somme de sa ligne et le pied, une affectation le nom de la
	 * personne, et l'une comme l'autre le tableau entier quand une ligne entre, sort ou change de rang
	 * (`TaskDetailLib::getChange()`).
	 */
	protected function getTime(\series\Task $eTask, ?string $week, \Collection $cUser, array $days, array $foot, array $navigation, array $timesByDay): string {

		$h = '<section class="planning-detail-block planning-detail-block-time util-fold" id="planning-task-detail-time">';

			$h .= '<div class="util-title">';
				$h .= '<h3>'.s("Temps de travail").'</h3>';
				$h .= $this->getNav($eTask, $week, $navigation);
			$h .= '</div>';

			$h .= $this->getTotal($eTask);
			$h .= $this->getWorked($eTask, $week, $cUser, $timesByDay);
			$h .= $this->getGrid($eTask, $cUser, $days, $foot, self::getGridDetail($eTask, $week, $navigation['week']));

		$h .= '</section>';

		return $h;

	}

	/**
	 * La tête de la semaine, celle du planning (`series\PlanningUi::getCalendar()`) : les flèches vers les
	 * semaines voisines, la semaine, qui ouvre le calendrier des semaines, et « Aujourd'hui » hors de la
	 * semaine en cours. Chaque geste passe par `taskDetail:week` avec la semaine choisie (`weekShown`).
	 */
	public function getNav(\series\Task $eTask, ?string $week, array $navigation): string {

		$weekShown = $navigation['week'];
		$year = (int)week_year($weekShown);

		$h = '<div class="planning-detail-time-nav" id="planning-task-detail-nav">';

			$h .= $this->getNavStep($eTask, $week, $navigation['before'], 'chevron-left');

			$h .= '<a class="planning-detail-time-nav-label dropdown-toggle" data-dropdown="bottom-start"><b>'.s("Semaine {week}, {year}", ['week' => week_number($weekShown), 'year' => $year]).'</b></a>';
			$h .= '<div class="dropdown-list dropdown-list-minimalist">';
				$h .= \util\FormUi::weekSelector(
					$year,
					onclickWeeks: 'PlanningTaskDetail.week(\'__CURRENT__\')',
					defaultWeek: $weekShown,
					minYear: $eTask['structure']->conf(\map\Configuration::class)->getFirstValidSeason(),
					maxYear: \Clock::year()
				);
			$h .= '</div>';

			$h .= $this->getNavStep($eTask, $week, $navigation['after'], 'chevron-right');

			if($navigation['isCurrent'] === FALSE) {
				$h .= '<a class="btn btn-outline-primary planning-detail-time-nav-today" data-ajax="'.self::urlWeek($eTask, $week, \Clock::week()).'" data-ajax-method="get">'.s("Aujourd'hui").'</a>';
			}

		$h .= '</div>';

		return $h;

	}

	protected function getNavStep(\series\Task $eTask, ?string $week, ?string $weekShown, string $icon): string {

		if($weekShown === NULL) {
			return '<a class="btn btn-only-icon btn-outline-primary disabled">'.\Asset::icon($icon).'</a>';
		} else {
			return '<a class="btn btn-only-icon btn-outline-primary" data-ajax="'.self::urlWeek($eTask, $week, $weekShown).'" data-ajax-method="get">'.\Asset::icon($icon).'</a>';
		}

	}

	/**
	 * Les jours saisis, que le total déplie (R-Retours-0710, 157), du plus récent au plus ancien
	 * (`TaskDetailLib::getTimesByDay()`) : le jour, qui montre sa semaine dans le tableau, ses personnes avec
	 * leur temps, et le temps du jour quand deux personnes au moins y ont travaillé. Le nœud reste sans jour
	 * saisi, pour que la première saisie le remplisse.
	 */
	public function getWorked(\series\Task $eTask, ?string $week, \Collection $cUser, array $timesByDay): string {

		$h = '<div class="util-fold-content" id="planning-task-detail-worked">';

			if($timesByDay !== []) {

				$h .= '<div class="util-table util-table-bordered planning-detail-worked-table">';

					foreach($timesByDay as $day => $times) {

						$h .= '<div class="util-table-row">';
							$h .= '<div><a data-ajax="'.self::urlWeek($eTask, $week, toWeek($day), $day).'" data-ajax-method="get">'.\util\DateUi::numeric($day).'</a></div>';
							$h .= '<div class="planning-detail-worked-users">';
								foreach($times as $userId => $time) {
									$h .= '<span class="planning-detail-worked-user">'.\user\UserUi::getVignette($cUser[$userId], '1.25rem').' '.$cUser[$userId]->getName().' <span class="util-annotation">'.DurationUi::formatDuration($time).'</span></span>';
								}
							$h .= '</div>';
							$h .= '<div class="text-end">'.(count($times) > 1 ? DurationUi::getSpent(array_sum($times)) : '').'</div>';
						$h .= '</div>';

					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Où vit un tableau de l'équipe, décidé ici pour que les gestes n'aient rien à deviner
	 * (`PlanningTaskDetail`) :
	 * - `prefix` : le préfixe de ses identifiants, propre à chaque tableau, puisque la fiche ouverte en panneau
	 *   par-dessus la grille et les menus de ses jours vivent dans la même page ;
	 * - `body` : ce que chaque geste réémet, en plus des lignes ajoutées (`added`) ;
	 * - `screen` : `week` quand l'écran de la semaine ajoute son état à chaque geste (`PlanningTask.getBody()`) ;
	 * - `urls` : les routes d'« Ajouter une personne », de l'affectation et du temps ;
	 * - `inline` : le tableau vit dans un menu, où rien ne s'ouvre par-dessus lui, `Lime.Dropdown.open()`
	 *   refermant tout autre menu ouvert. La liste d'« Ajouter une personne » s'y déplie sous sa ligne, chaque
	 *   personne y a ses pas déjà ouverts dans sa case, et un seul « Enregistrer », en pied, écrit les durées
	 *   de toutes (ET, retour 398).
	 */
	public static function getGridDetail(\series\Task $eTask, ?string $week, string $weekShown): array {

		return [
			'prefix' => 'planning-task-detail',
			'body' => [
				'id' => $eTask['structure']['id'],
				'task' => $eTask['id'],
				'week' => $week ?? '',
				'weekShown' => $weekShown
			],
			'screen' => NULL,
			'urls' => [
				'add' => '/planning/taskDetail:user',
				'user' => '/planning/taskDetail:doUser',
				'time' => '/planning/taskDetail:doTime'
			],
			'inline' => FALSE
		];

	}

	/**
	 * Le tableau du menu d'un jour de la grille (CW), réduit à ce jour.
	 */
	public static function getGridWeek(\series\Task $eTask, string $date): array {

		return [
			'prefix' => 'planning-grid-team-'.$eTask['id'].'-'.$date,
			'body' => [
				'task' => $eTask['id'],
				'date' => $date
			],
			'screen' => 'week',
			'urls' => [
				'add' => '/planning/taskWeek:time',
				'user' => '/planning/taskWeek:doUser',
				'time' => '/planning/taskWeek:doTimeTeam'
			],
			'inline' => TRUE
		];

	}

	/**
	 * Le tableau de l'équipe : une colonne par jour, une ligne par personne (`line`), les personnes affectées
	 * d'abord, puis le pied et « Ajouter une personne ». La fiche lui passe les sept jours de la semaine
	 * montrée, avec la somme de chaque ligne dans une colonne « Semaine », le menu d'un jour de la grille ce
	 * seul jour, dont la case est déjà la somme. À un seul jour, le tableau n'a pas de tête : le titre du menu
	 * nomme ce jour (`TaskWeekGridUi::getTimeList()`, retour 404). Sans aucune ligne, il n'a ni tête ni pied :
	 * il ne reste que le geste, ou le constat pour qui n'a personne à ajouter.
	 *
	 * Il porte ce que chaque geste réémet (`PlanningTaskDetail`) : son contexte (`getGridDetail()`,
	 * `getGridWeek()`) et les lignes ajoutées à la main (`added`), qu'aucune écriture ne retient encore. Lui
	 * seul les porte, et toute réponse qui change les lignes le redessine d'un seul nœud : la liste n'est
	 * jamais périmée.
	 *
	 * Dans un menu (`inline`), un seul « Enregistrer » clôt le tableau dès qu'une ligne a une saisie, et chaque
	 * rendu du tableau passe par `PlanningTaskDetail.rendered()`, qui y reporte les durées tapées et pas
	 * encore enregistrées, puis le focus : l'ouverture, « Ajouter une personne », « Affecter » et
	 * « Enregistrer » le redessinent tous.
	 */
	public function getGrid(\series\Task $eTask, \Collection $cUser, array $days, array $foot, array $context): string {

		$rows = '';
		$userIdsAdded = [];
		$timed = FALSE;

		foreach($cUser as $eUser) {

			if($eUser['line']) {
				$rows .= $this->getGridRow($eTask, $eUser, $days, $context);
				$timed = ($timed or $eUser['canTime']);
			}

			if($eUser['added']) {
				$userIdsAdded[] = $eUser['id'];
			}

		}

		$add = $this->getGridAdd($cUser, $context);

		$classes = ['util-table', 'planning-detail-time-table', 'planning-detail-team-grid'];

		if($context['inline']) {
			$classes[] = 'planning-detail-team-grid-inline';
		}

		$h = '<div class="'.implode(' ', $classes).'" id="'.$context['prefix'].'-grid" data-body="'.encode(json_encode($context['body'])).'" data-screen="'.$context['screen'].'" data-url-add="'.$context['urls']['add'].'" data-url-user="'.$context['urls']['user'].'" data-url-time="'.$context['urls']['time'].'" data-added="'.implode(',', $userIdsAdded).'"'.($context['inline'] ? ' onrender="PlanningTaskDetail.rendered(this)"' : '').'>';

			if($rows !== '') {

				if(count($days) > 1) {

					$h .= '<div class="util-table-head">';
						$h .= '<div></div>';

						foreach($days as $date => $day) {
							$h .= '<div'.$this->getGridDayAttributes($date, $day).'>';
								$h .= '<span>'.\util\DateUi::getDayNameShort((int)date('N', strtotime($date))).'</span>';
								$h .= '<span>'.(int)substr($date, 8, 2).'</span>';
							$h .= '</div>';
						}

						$h .= '<div>'.s("Semaine").'</div>';

					$h .= '</div>';

				}

				$h .= $rows;
				$h .= $this->getGridFoot($days, $foot, $context);

			} else if($add === '') {
				$h .= '<div class="util-empty">'.s("Aucun temps saisi cette semaine.").'</div>';
			}

			$h .= $add;

			if($context['inline'] and $timed) {
				$h .= '<div class="planning-detail-team-grid-save">';
					$h .= '<a class="btn btn-primary" data-planning-detail-save>'.s("Enregistrer").'</a>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une personne, une ligne : son nom et son état d'affectation, une case par jour, puis la somme de sa
	 * semaine quand le tableau en montre plusieurs jours.
	 */
	protected function getGridRow(\series\Task $eTask, \user\User $eUser, array $days, array $context): string {

		$h = '<div class="util-table-row">';

			$h .= $this->getGridUser($eUser, $context);

			foreach($days as $date => $day) {
				$h .= $this->getGridCell($eTask, $eUser, $date, $day, $context);
			}

			if(count($days) > 1) {
				$h .= $this->getGridSum($eUser, $context);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La vignette, le nom et l'état d'affectation : « Affecté », qui retire l'affectation au clic, ou
	 * « Affecter ». Quand la personne connectée ne peut pas le changer (`canAssign`), « Affecté » se lit sans
	 * se cliquer, et rien ne propose d'affecter.
	 *
	 * Sur la fiche au téléphone, la personne tient sur la ligne de ses jours (V26) : la vignette reste seule,
	 * cerclée quand la personne est affectée, et ouvre un menu qui dit son nom et le geste d'affectation
	 * (`getGridUserMenu()`). Le menu d'un jour (`inline`) garde le nom : il est lui-même un menu, que
	 * `Lime.Dropdown` ne sait pas empiler.
	 */
	public function getGridUser(\user\User $eUser, array $context): string {

		$wide = $context['inline'] ? '' : ' hide-xs-down';

		$h = '<div class="planning-detail-time-user" id="'.$context['prefix'].'-user-'.$eUser['id'].'" data-user="'.$eUser['id'].'" data-grid="'.$context['prefix'].'-grid">';
			$h .= '<span class="planning-detail-team-vignette'.$wide.'">'.\user\UserUi::getVignette($eUser, '1.75rem').'</span>';
			$h .= '<div class="planning-detail-time-name'.$wide.'">';
				$h .= '<span class="planning-detail-team-name">'.$eUser->getName().'</span>';

				if($eUser['assigned'] and $eUser['canAssign']) {
					$h .= '<a class="btn btn-xs btn-badge badge badge-success" data-planning-detail-assign="delete">'.s("Affecté").'</a>';
				} else if($eUser['assigned']) {
					$h .= '<span class="badge badge-success">'.s("Affecté").'</span>';
				} else if($eUser['canAssign']) {
					$h .= '<a class="planning-detail-team-assign" data-planning-detail-assign="add">'.s("Affecter").'</a>';
				}

			$h .= '</div>';

			if($context['inline'] === FALSE) {
				$h .= $this->getGridUserMenu($eUser, $context);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La vignette de la fiche au téléphone et son menu. Ouvert, le menu vit sous `body` : il nomme sa personne
	 * (`data-user`) et son tableau (`data-grid`), que le geste d'affectation lit comme sur la ligne.
	 */
	protected function getGridUserMenu(\user\User $eUser, array $context): string {

		$assigned = $eUser['assigned'] ? ' planning-detail-team-vignette-assigned' : '';

		$h = '<a class="dropdown-toggle planning-detail-team-vignette planning-detail-team-vignette-menu'.$assigned.' hide-sm-up" data-dropdown="bottom-start">'.\user\UserUi::getVignette($eUser, '1.75rem').'</a>';
		$h .= '<div class="dropdown-list planning-detail-team-menu" data-user="'.$eUser['id'].'" data-grid="'.$context['prefix'].'-grid">';
			$h .= '<div class="dropdown-title">'.$eUser->getName().'</div>';

			if($eUser['canAssign']) {
				$h .= $eUser['assigned'] ?
					'<a class="dropdown-item" data-planning-detail-assign="delete">'.s("Retirer l'affectation").'</a>' :
					'<a class="dropdown-item" data-planning-detail-assign="add">'.s("Affecter").'</a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le temps d'une personne un jour : sa durée, ou « + » tant qu'elle n'en a pas, qui ouvre par-dessus la page
	 * la fenêtre du champ par pas et de son « Enregistrer » (`DurationUi::getStepWindow()`). Un jour à venir est
	 * grisé, sans geste : le temps ne s'y écrit pas. Une personne dont la personne connectée ne peut pas
	 * écrire le temps n'a que sa valeur en texte, rien le jour où elle n'a rien : une valeur figée n'est pas
	 * un champ, et n'ouvre rien.
	 *
	 * Dans un menu (`inline`), les pas sont déjà ouverts dans la case, sans tuile ni pied (ET, retour 398) :
	 * l'« Enregistrer » du tableau les écrit tous (`getGrid()`), et le refus d'une durée se lit sous ses pas
	 * (`time[<user>]`).
	 *
	 * Sur la fiche comme dans le menu, une durée écrite s'efface par sa poubelle (FJ, retour 412,
	 * `DurationUi::getStepPanel()`).
	 */
	public function getGridCell(\series\Task $eTask, \user\User $eUser, string $date, array $day, array $context): string {

		$time = $eUser['timesWeek'][$date];

		if($day['future']) {
			$field = '';
		} else if($eUser['canTime'] and $context['inline']) {
			$field = new DurationUi()->getStepInline($this->getKey($eUser, $date, $context), $time, 'time['.$eUser['id'].']', erase: TRUE);
		} else if($eUser['canTime']) {
			$field = new DurationUi()->getStepWindow($this->getKey($eUser, $date, $context), $time, icon: 'plus-lg', erase: TRUE);
		} else if($time > 0.0) {
			$field = DurationUi::getSpent($time);
		} else {
			$field = '';
		}

		$classes = array_merge(['planning-detail-time-field'], $this->getGridDayClasses($day));

		return '<div class="'.implode(' ', $classes).'" id="'.$context['prefix'].'-cell-'.$eUser['id'].'-'.$date.'" data-user="'.$eUser['id'].'" data-date="'.$date.'">'.$field.'</div>';

	}

	/**
	 * Ce qu'une colonne dit de son jour, de la tête au pied : aujourd'hui, ou à venir, rien un jour passé, et
	 * sa date, que portent aussi les cases (`getGridCell()`) : la colonne entière s'éclaire par elle quand un
	 * jour saisi la montre, du dépli, premier et dernier compris (`taskDetail:week`).
	 */
	protected function getGridDayAttributes(string $date, array $day): string {

		$classes = $this->getGridDayClasses($day);

		return ($classes === [] ? '' : ' class="'.implode(' ', $classes).'"').' data-date="'.$date.'"';

	}

	protected function getGridDayClasses(array $day): array {

		$classes = [];

		if($day['today']) {
			$classes[] = 'planning-detail-team-grid-today';
		}

		if($day['future']) {
			$classes[] = 'planning-detail-team-grid-future';
		}

		return $classes;

	}

	/**
	 * La somme de la semaine d'une personne, rien sans temps.
	 */
	public function getGridSum(\user\User $eUser, array $context): string {

		return '<div class="planning-detail-team-grid-sum" id="'.$context['prefix'].'-sum-'.$eUser['id'].'">'.($eUser['timeWeek'] > 0.0 ? DurationUi::formatDuration($eUser['timeWeek']) : '').'</div>';

	}

	/**
	 * Le pied : le temps de l'intervention chaque jour, toutes personnes, et celui de la semaine
	 * (`TaskDetailLib::getFoot()`). Quand il ne se montre pas, le nœud reste, vide, pour que la saisie qui
	 * amène la deuxième ligne le remplisse.
	 */
	public function getGridFoot(array $days, array $foot, array $context): string {

		$h = '<div class="util-table-row planning-detail-team-grid-foot" id="'.$context['prefix'].'-foot">';

			if($foot['shown']) {

				$h .= '<div>'.s("Total").'</div>';

				foreach($days as $date => $day) {
					$h .= '<div'.$this->getGridDayAttributes($date, $day).'>'.($day['time'] > 0.0 ? DurationUi::formatDuration($day['time']) : '').'</div>';
				}

				if(count($days) > 1) {
					$h .= '<div class="planning-detail-team-grid-sum">'.($foot['time'] > 0.0 ? DurationUi::formatDuration($foot['time']) : '').'</div>';
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La clé d'une saisie, par tableau, personne et jour : la réponse qui redessine la case y retrouve le champ
	 * dont un envoi est encore en route, ou la fenêtre ouverte entre-temps. Le préfixe du tableau la rend unique
	 * dans la page, où la fiche et les menus de la grille peuvent montrer la même case.
	 */
	protected function getKey(\user\User $eUser, string $date, array $context): string {
		return $context['prefix'].'-'.$eUser['id'].'-'.$date;
	}

	/**
	 * Le total, qui déplie les jours saisis (R-Retours-0710, 157), et le prévu, sous l'intertitre, que chaque
	 * saisie redessine. Il s'écrit en gras dans le texte ; celui de la récolte prend la forme du total de la grille
	 * des récoltes, à la couleur de l'action (V23).
	 * Sans temps, rien : un total vide n'a rien à dire, et la première saisie le fait paraître, puisque c'est ce
	 * nœud qu'elle redessine. Avec du temps, ce nœud est la tête du pli elle-même : le chevron ne tourne que
	 * sous un enfant direct de `util-fold-open` (`component.css`).
	 */
	public function getTotal(\series\Task $eTask): string {

		if($eTask['time'] <= 0.0) {
			return '<div id="planning-task-detail-total"></div>';
		}

		$h = '<a class="util-fold-head planning-detail-time-total" id="planning-task-detail-total" onclick="this.closest(\'.util-fold\').classList.toggle(\'util-fold-open\')">';
			$h .= '<span>'.s("Total <b>{value}</b>", DurationUi::formatDuration($eTask['time'])).'</span>';
			$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
			if($eTask['timeExpected'] > 0.0) {
				$h .= '<span class="util-annotation">'.s("prévu {value}", \series\TaskUi::convertTime($eTask['timeExpected'])).'</span>';
			}
		$h .= '</a>';

		return $h;

	}

	/**
	 * « Ajouter une personne », dernière ligne du tableau, qui ouvre la liste des personnes sans ligne où un
	 * geste est possible (`addable`), comme « Affecter » dans la barre de la sélection
	 * (`TaskBatchUi::getUsers()`). En choisir une demande le tableau avec sa ligne (`urls.add`). Son icône
	 * tient la place de la vignette. Dans un menu (`inline`), la liste se déplie sous la ligne au lieu de
	 * s'ouvrir en menu, que `Lime.Dropdown` ne sait pas empiler. La liste nomme son tableau (`data-grid`) :
	 * ouverte en menu, elle vit sous `body`.
	 */
	protected function getGridAdd(\Collection $cUser, array $context): string {

		$list = '';

		foreach($cUser as $eUser) {

			if($eUser['addable']) {
				$list .= '<a class="dropdown-item" data-planning-detail-add="'.$eUser['id'].'">'.\user\UserUi::getVignette($eUser, '1.5rem').'  '.$eUser->getName().'</a>';
			}

		}

		if($list === '') {
			return '';
		}

		if($context['inline']) {
			$h = '<a class="util-table-row util-table-add" onclick="this.nextElementSibling.classList.toggle(\'hide\')">';
		} else {
			$h = '<a class="util-table-row util-table-add" data-dropdown="bottom-start">';
		}

			$h .= '<div><span class="util-table-add-icon">'.\Asset::icon('person-plus').'</span><span>'.s("Ajouter une personne").'</span></div>';
		$h .= '</a>';
		$h .= '<div class="'.($context['inline'] ? 'hide' : 'dropdown-list').' planning-detail-time-add-list" data-grid="'.$context['prefix'].'-grid">'.$list.'</div>';

		return $h;

	}

}
?>
