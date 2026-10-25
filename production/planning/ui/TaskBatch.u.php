<?php
namespace planning;

/**
 * La sélection de la Semaine et du Jour (L5d) : la case de chaque carte, la case en tête d'un bloc ou
 * d'un groupe d'action, et la barre de lot du produit (`\util\BatchUi::group()`), en bas d'écran dès la
 * première carte cochée (R-Retours-0310, point 18, qui retire le menu « un » posé sur la carte). À une
 * carte, la barre porte ce que portait ce menu, Modifier compris ; `PlanningTaskSelection.updateMenu()`
 * règle les entrées selon le compte.
 *
 * Les entrées et leurs conditions sont celles de l'ancien planning (`\series\TaskUi::getBatch()`,
 * `Task.changePlanningSelection()`), la récolte comprise depuis DG-1 (« Récolter », `getHarvest()`). Les gestes répétés
 * (Fait !, À faire, un jour, cette semaine, décaler d'une semaine, Affecter, Supprimer) et « Temps », une
 * ligne par intervention (R-Retours-0410, point 10, à la place du temps partagé de l'ancien planning),
 * partent vers les routes de l'écran, qui ne redessinent que ce qui change. Les quatre autres panneaux de
 * l'ancien planning (Une autre semaine, Décaler davantage, Commenter, Modifier) sont repris tels quels :
 * leur écriture recharge l'écran entier (écart b, dette déclarée à la livraison de L5d).
 *
 * Le serveur ne rend jamais une case cochée : la sélection vit dans le navigateur
 * (`PlanningTaskSelection`), et une réponse ciblée reste ainsi octet pour octet le rechargement.
 */
class TaskBatchUi {

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

	}

	/**
	 * La case d'une carte. Ses jetons (`data-batch`) sont ceux de l'ancien planning
	 * (`\series\TaskUi::getBatchCheckbox()`), que lisent les conditions du menu.
	 */
	public function getCheckbox(\series\Task $eTask): string {

		$batch = [];

		if($eTask->acceptPostpone() === FALSE) {
			$batch[] = 'not-postpone';
		}

		$batch[] = $eTask->isDone() ? 'done' : 'todo';

		// Le temps d'une récolte seule se partage à sa quantité du jour (`TaskBatchLib::getDistributions()`) : sans
		// quantité dans la semaine, aucun jour ne le recevrait.
		if($eTask['action']['fqn'] === ACTION_RECOLTE and $eTask['category']['fqn'] === CATEGORIE_CULTURE and ($eTask['harvestWeekTotal'] === NULL or $eTask['harvestWeekTotal'] <= 0.0)) {
			$batch[] = 'not-time';
		}

		// « Récolter » ne s'offre qu'à des récoltes de la même espèce, variété et calibre, dans une seule unité
		// (`TaskBatchLib::validateHarvests()`) : une récolte qui n'a encore rien reçu n'a pas d'unité, et ne départage rien.
		if($eTask['action']['fqn'] === ACTION_RECOLTE and $eTask['plant']->notEmpty()) {

			$batch[] = 'harvest-'.$eTask['plant']['id'].'-'.($eTask['variety']->empty() ? 0 : $eTask['variety']['id']).'-'.($eTask['harvestSize']->empty() ? 0 : $eTask['harvestSize']['id']);

			if($eTask['harvestUnit'] !== NULL) {
				$batch[] = 'harvest-unit-'.$eTask['harvestUnit'];
			}

		}

		if($eTask['plannedUsers'] !== NULL) {
			foreach($eTask['plannedUsers'] as $user) {
				$batch[] = 'user-'.$user;
			}
		}

		$form = new \util\FormUi();

		$h = '<label class="planning-select">';
			$h .= $form->inputCheckbox('batch[]', $eTask['id'], [
				'data-batch' => implode(' ', $batch),
				'onrender' => 'PlanningTaskSelection.restore(this)',
			]);
		$h .= '</label>';

		return $h;

	}

	/**
	 * La case en tête d'un bloc ou d'un groupe d'action : elle prend les cartes à faire qu'il porte, pas les
	 * faites, dont la sélection retirerait « Fait ! » de la barre (`caseTete()` de la maquette), et toutes
	 * ses cartes quand tout y est fait (retours 275 et 276). Son état, cochée, à demi cochée ou absente quand
	 * la recherche masque tout le bloc, suit les cases des cartes dans le navigateur
	 * (`PlanningTaskSelection.updateGroups()`).
	 */
	public function getGroupCheckbox(): string {

		$form = new \util\FormUi();

		$h = '<label class="planning-select planning-select-group">';
			$h .= $form->inputCheckbox(NULL, '1', [
				'data-planning-select-group' => '',
			]);
		$h .= '</label>';

		return $h;

	}

	/**
	 * Les deux libellés sont rendus et la feuille montre celui du mode (`.planning-selecting`) : la rangée des
	 * outils est redessinée par les réponses, qui ne savent pas si le mode est ouvert. Les deux portent la même
	 * icône, pour se lire comme un seul bouton dans ses deux états (retour 279). Sous 576,
	 * « Sélectionner » ne garde que son icône, pour que la rangée tienne sur une ligne à 360 (R-Retours-0610,
	 * point 10) ; « Terminé » garde son mot à toutes les largeurs. La Grille, qui coche sans mode, et la semaine
	 * en Colonnes, dont les cases sont toujours visibles, ne le rendent pas (`TaskWeekUi::get()`).
	 */
	public function getSelectButton(): string {

		$h = '<a class="btn btn-outline-primary planning-select-open" data-planning-select-mode>'.\Asset::icon('check2-square').'<span class="hide-xs-down"> '.s("Sélectionner").'</span></a>';
		$h .= '<a class="btn btn-outline-primary planning-select-close" data-planning-select-mode>'.\Asset::icon('check2-square').' '.s("Terminé").'</a>';

		return $h;

	}

	/**
	 * La barre, rangée dans un nœud qu'aucune réponse ne remplace (`#planning-batch-home`) : elle garde
	 * ainsi sa sélection et ses menus ouverts quand un geste redessine les cartes.
	 *
	 * @param string $page La page de l'écran, `taskWeek` ou `taskDay`, dont les routes reçoivent les gestes.
	 * @param array $post Ce que les gestes réémettent pour que leur réponse dessine l'écran que l'on regarde.
	 */
	public function get(\structure\Structure $eStructure, string $page, string $week, array $post, \Collection $cUser): string {

		$url = '/planning/'.$page.':';

		$attributes = [];

		foreach($post as $name => $value) {
			$attributes['post-'.$name] = $value;
		}

		$h = '<div id="planning-batch-home">';
			$h .= $this->getGroup($eStructure, $url, $attributes, $week, $cUser);
		$h .= '</div>';

		return $h;

	}

	protected function getGroup(\structure\Structure $eStructure, string $url, array $attributes, string $week, \Collection $cUser): string {

		$h = '';

		// Une semaine à venir n'a aucun jour où poser du temps (`\series\Timesheet::properties()`, étape `future`).
		if($eStructure->hasTime() and $eStructure->canPlanningLimited() and $week <= \Clock::week()) {
			$h .= '<a data-ajax-submit="'.$url.'batchTime" data-ajax-method="get" '.attrs($attributes).' class="batch-item planning-batch-time"'.($eStructure->canPlanning() ? '' : ' data-planning-batch-time-done').'>'.\Asset::icon('clock').'<span>'.s("Temps").'</span></a>';
		}

		// Récolter écrit l'intervention, au droit du planning, et une semaine à venir n'a aucun jour où récolter.
		if($eStructure->canPlanning() and $week <= \Clock::week()) {
			$h .= '<a data-ajax-submit="'.$url.'batchHarvest" data-ajax-method="get" '.attrs($attributes).' class="batch-item planning-batch-harvest">'.\Asset::icon('basket').'<span>'.s("Récolter").'</span></a>';
		}

		if($eStructure->canPlanning()) {

			$h .= '<a data-ajax-submit="'.$url.'doBatchDone" '.attrs($attributes).' class="batch-item planning-batch-done">'.\Asset::icon('check-lg').'<span>'.s("Fait !").'</span></a>';
			$confirm = s("Remettre ces interventions à faire ?");
			$h .= '<a data-ajax-submit="'.$url.'doBatchTodo" '.attrs($attributes).' class="batch-item planning-batch-todo" data-confirm="'.$confirm.'" data-confirm-many="'.$confirm.'" data-confirm-one="'.s("Remettre cette intervention à faire ?").'">'.\Asset::icon('arrow-up-left').'<span>'.s("À faire").'</span></a>';

			// Les deux menus s'ouvrent vers le haut depuis une barre dont le titre dépasse au-dessus d'elle, × compris.
			// Leur liste se pose donc au-dessus du titre et non contre le bouton : collée à lui, elle couvre le ×,
			// et le clic qui annule la sélection tombe sur une entrée qui écrit. Le décalage est en rem
			// (`Lime.Dropdown.openAround()`) : le haut du × est 2.7rem au-dessus du bouton, 3 laisse un jour.
			$h .= '<div class="planning-batch-planned">';
				$h .= '<a data-dropdown="top-start" data-dropdown-offset-y="-3" class="batch-item">'.\Asset::icon('watch').'<span>'.s("Planifier").'</span></a>';
				$h .= $this->getPlanned('planning-batch-form', $url, $attributes, $week);
			$h .= '</div>';

			if($cUser->count() > 1) {
				$h .= '<div class="planning-batch-users">';
					$h .= '<a data-dropdown="top-start" data-dropdown-offset-y="-3" class="batch-item">'.\Asset::icon('people-fill').'<span>'.s("Affecter").'</span></a>';
					$h .= $this->getUsers('planning-batch-form', $url, $attributes, $cUser);
				$h .= '</div>';
			}

		}

		if($eStructure->canPlanningLimited()) {
			$h .= '<a data-ajax-submit="/series/comment:createCollection" data-ajax-method="get" class="batch-item">'.\Asset::icon('chat-dots-fill').'<span>'.s("Commenter").'</span></a>';
		}

		if($eStructure->canPlanning()) {
			$h .= '<a class="batch-item planning-batch-update">'.\Asset::icon('gear-fill').'<span>'.s("Modifier").'</span></a>';
			$confirm = s("Supprimer ces interventions ?");
			$danger = '<a data-ajax-submit="'.$url.'doBatchDelete" '.attrs($attributes).' class="batch-item batch-item-danger" data-confirm="'.$confirm.'" data-confirm-many="'.$confirm.'" data-confirm-one="'.s("Supprimer cette intervention ?").'">'.\Asset::icon('trash').'<span>'.s("Supprimer").'</span></a>';
		} else {
			$danger = NULL;
		}

		return \util\BatchUi::group('planning-batch', $h, $danger, hide: 'PlanningTaskSelection.clear()');

	}

	/**
	 * Le panneau de « Temps » (lot BG, planche planning-temps-repartition, piste A) : comme « Compléter pour » de
	 * l'ancien planning, un temps total pour un jour, qui s'ajoute au temps de la personne connectée et se partage
	 * entre les interventions cochées selon une clé (`getTimeShared()`). Le serveur recalcule les parts
	 * (`TaskBatchLib::prepareTimes()`) : celles du panneau ne sont qu'une lecture.
	 *
	 * @param array $post Ce que le formulaire réémet pour que la réponse dessine l'écran que l'on regarde.
	 * @param array $days Les jours de la semaine (`TaskBatchLib::getTimeDays()`).
	 * @param array $distributions Les clés possibles (`TaskBatchLib::getDistributions()`), aucune quand toutes les lignes sont verrouillées.
	 */
	public function getTime(\structure\Structure $eStructure, string $page, string $week, array $post, \Collection $cTask, \user\User $eUser, array $days, ?string $date, array $distributions, ?string $distribution): \Panel {

		$form = new \util\FormUi();

		$h = $form->hidden('id', $eStructure);
		$h .= $form->hidden('week', $week);

		foreach($post as $name => $value) {
			$h .= $form->hidden($name, $value);
		}

		foreach($cTask as $eTask) {
			$h .= $form->hidden('ids[]', $eTask);
		}

		if($distributions !== []) {

			$h .= $form->group(
				s("Jour"),
				$form->hidden('date', $date).$this->getTimeDays($days, $date, 'data-planning-batch-time-day'),
				['data-wrapper' => 'date']
			);

		}

		$h .= $this->getTimeShared($form, $cTask, $distributions, $distribution, $date ?? array_key_first($days));

		if($distributions !== []) {
			$h .= $form->group(
				content: $form->submit(s("Enregistrer les temps"))
			);
		}

		return new \Panel(
			id: 'panel-planning-batch-time',
			title: s("Temps de travail"),
			subTitle: new TimeWeekUi()->getWho($eUser, '1.25rem'),
			dialogOpen: $form->openAjax('/planning/'.$page.':doBatchTime', ['class' => 'panel-dialog', 'data-planning-batch-time' => '']),
			dialogClose: $form->close(),
			body: $h
		);

	}

	/**
	 * Les jours de la semaine affichée, dans la forme du jour de « Nouvelle intervention » (`TaskCreateUi`). Un jour
	 * à venir ne se choisit pas. Chaque jour qui se choisit porte la date dans l'attribut que lit le JS de son
	 * panneau (`$attribute`).
	 */
	protected function getTimeDays(array $days, ?string $date, string $attribute): string {

		$h = '<div class="util-days">';

			foreach($days as $dateListed => $day) {

				$selected = ($dateListed === $date);

				$classes = ['util-day'];

				if($day['today']) {
					$classes[] = 'is-today';
				}

				if($selected) {
					$classes[] = 'is-selected';
				}

				if($day['future']) {
					$classes[] = 'is-disabled';
				}

				$h .= new TimeWeekUi()->getDayDate($dateListed, $classes, $selected, $day['future'] ? '' : $attribute.'="'.$dateListed.'"');

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le temps total, sa clé et la part de chaque ligne, le bloc que « Récolter » reprendra (lot BD).
	 *
	 * Le total se saisit dans la rangée des pas ouverte d'emblée (retour 265), sans l'« Enregistrer » de la bulle,
	 * le panneau ayant le sien, et part dans le champ caché `time`. La clé se choisit par boutons radios, sauf
	 * quand il n'y en a qu'une : la quantité récoltée, qui ne se montre pas (des récoltes seulement, arbitrage de
	 * du 12/10), ou le partage égalitaire, qui se lit (une intervention hors culture). Une intervention
	 * seule reçoit le total entier quelle que soit la clé : elle part cachée, sans rien à choisir ni à lire
	 * (retour 295). Chaque ligne porte sa part puis ce qui la fonde, de chaque clé et, pour une récolte, de
	 * chaque jour : `PlanningTaskBatchTime` montre celui de la clé et du jour choisis et recalcule la part.
	 */
	public function getTimeShared(\util\FormUi $form, \Collection $cTask, array $distributions, ?string $distribution, string $date): string {

		$h = '';

		if($distributions !== []) {

			// Une ligne verrouillée ne reçoit rien : la clé ne départage que les autres.
			$one = ($cTask->find(fn(\series\Task $eTask) => $eTask['timeLocked'] === FALSE)->count() === 1);

			// La clé qui ne se choisit pas part avec le total, dans son groupe et avant lui : un groupe prend le nom
			// du dernier champ écrit (`data-wrapper`), et posée entre deux groupes elle nommerait une ligne
			// d'intervention.
			$hidden = '';

			if($one or $distributions === ['fair'] or $distributions === ['harvest']) {
				$hidden .= $form->hidden('distribution', $distribution);
			}

			$hidden .= $form->hidden('time', '0');

			$h .= $form->group(
				s("Temps total"),
				'<div class="planning-batch-time-total">'.new DurationUi()->getStepBare('batch-time-total', 0.0, wrapper: NULL, foot: FALSE).$hidden.'</div>'
			);

			if($one === FALSE) {

				if($distributions === ['fair']) {
					$h .= $form->group(
						s("Répartition"),
						'<b>'.self::getDistributions()['fair'].'</b>'
					);
				} else if($distributions !== ['harvest']) {
					$h .= $form->group(
						s("Répartition"),
						$form->radios('distribution', array_intersect_key(self::getDistributions(), array_flip($distributions)), $distribution, ['required' => TRUE]),
						['data-wrapper' => 'distribution']
					);
				}

			}

		}

		$h .= '<div class="planning-batch-time-lines">';

			foreach($cTask as $eTask) {

				$h .= $form->group(
					$this->getTimeName($eTask),
					$this->getTimeShare($eTask, $distributions, $distribution, $date),
					['class' => 'planning-batch-time-group']
				);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le panneau de « Récolter » (DG-1, planche planning-dg-plusieurs-series, cadre r) : une quantité pour un jour,
	 * ajoutée aux récoltes cochées et partagée entre elles selon une clé, avec le stock où elle entre et, quand la
	 * structure suit le temps, le temps de chaque personne, partagé par la même clé (HM, comme la feuille de
	 * récolte). Le serveur recalcule les parts (`TaskBatchLib::prepareHarvests()`) : celles du panneau ne sont
	 * qu'une lecture.
	 *
	 * Le bloc sous le jour est celui de « Nouvelle intervention » sur plusieurs séries (`getHarvestBlock()`, DG-5).
	 *
	 * @param array $post Ce que le formulaire réémet pour que la réponse dessine l'écran que l'on regarde.
	 * @param array $days Les jours de la semaine (`TaskBatchLib::getTimeDays()`).
	 * @param array $distributions Les clés possibles (`TaskBatchLib::getHarvestDistributions()`).
	 * @param array $tasksByUnit Les autres unités, quand l'unité se choisit (`HarvestWeekLib::getEntryTasksForCreate()`).
	 * @param \series\Task $eTaskStock La première récolte dans l'unité de la saisie, avec ses stocks (`HarvestStockLib::fill()`).
	 * @param \Collection $cUserTime Les personnes dont le temps se saisit (`TaskCreateLib::getTimeUsers()`), vide
	 *   quand la structure ne suit pas le temps.
	 * @param \series\Cultivation $eCultivationChoice Le couple qui se choisit aux Récoltes (`HarvestWeekLib::getBatchChoices()`,
	 *   HM2), vide ailleurs ou quand rien ne se choisit. Un choix recharge les parts (`harvestWeek:batchHarvestChoice`).
	 */
	public function getHarvest(\structure\Structure $eStructure, string $page, string $week, array $post, \Collection $cTask, array $days, ?string $date, array $distributions, string $distribution, \plant\Plant $ePlant, string $unit, array $tasksByUnit, \series\Task $eTaskStock, \Collection $cUserTime, \series\Cultivation $eCultivationChoice): \Panel {

		$form = new \util\FormUi();

		$h = $form->hidden('id', $eStructure);
		$h .= $form->hidden('week', $week);

		foreach($post as $name => $value) {
			$h .= $form->hidden($name, $value);
		}

		// Les Récoltes désignent la ligne par sa culture et son couple, la clé de la collection : son intervention de
		// la semaine peut ne pas encore exister, et l'écriture la crée (`HarvestWeekLib::getTasksForBatch()`).
		foreach($cTask as $value => $eTask) {
			$h .= $form->hidden('ids[]', match($page) {
				'taskWeek', 'taskDay' => $eTask,
				'harvestWeek' => $value,
			});
		}

		$h .= $form->group(
			s("Jour"),
			$form->hidden('date', $date).$this->getTimeDays($days, $date, 'data-planning-batch-harvest-day'),
			['data-wrapper' => 'date']
		);

		if($eCultivationChoice->notEmpty()) {
			$h .= '<div data-planning-batch-harvest-choice="/planning/harvestWeek:batchHarvestChoice">';
				$h .= new HarvestEntryUi()->getChoiceFields($form, $eCultivationChoice, $eCultivationChoice['eVariety'], $eCultivationChoice['eSize']);
			$h .= '</div>';
		}

		$time = $cUserTime->empty() ? '' : $form->group(
			s("Temps de travail"),
			new HarvestEntryUi()->getPeople($form, $cUserTime),
			['data-wrapper' => 'time timeTeam']
		);

		$h .= $this->getHarvestBlock($form, $cTask, $distributions, $distribution, $ePlant, $unit, $tasksByUnit, $eTaskStock, 'planning-batch-harvest-stock', $eStructure->hasTime(), time: $time, couple: ($page === 'harvestWeek' and $eCultivationChoice->empty()));

		$h .= $form->group(
			content: $form->submit(s("Enregistrer la récolte"))
		);

		return new \Panel(
			id: 'panel-planning-batch-harvest',
			title: s("Récolter"),
			subTitle: p("{value} récolte de {plant}", "{value} récoltes de {plant}", $cTask->count(), ['value' => $cTask->count(), 'plant' => encode(mb_strtolower($ePlant['name']))]),
			dialogOpen: $form->openAjax('/planning/'.$page.':doBatchHarvest', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h
		);

	}

	/**
	 * Le bloc de récolte commun à « Récolter » et à « Nouvelle intervention » sur plusieurs séries (DG-5, piste A de
	 * la planche) : la quantité, le stock, la clé, le temps de chaque personne et la part de chaque série.
	 *
	 * La quantité est le champ de « Nouvelle intervention » (`HarvestEntryUi::getQuantity()`), caisses comprises, et
	 * son unité se choisit tant qu'aucune récolte n'en a (`$tasksByUnit`). Le stock est celui de toute saisie de
	 * récolte (`HarvestEntryUi::getStock()`, DG-4), que `PlanningHarvestStock` relit quand l'unité change. Une seule
	 * série reçoit tout : ni clé ni parts.
	 *
	 * Le conteneur porte l'unité et ses libellés, que lit `PlanningTaskBatchHarvest` pour écrire les parts, et dit
	 * comment le serveur partage le temps, pour que les parts montrent ce qu'il écrira : « Récolter » partage
	 * chaque personne à la minute (`data-time-split`, `TaskBatchLib::prepareHarvests()`), « Nouvelle
	 * intervention » le total des personnes (`TimeTeamLib::createForTasks()`).
	 *
	 * @param string $stockId L'identifiant du champ de stock, propre au panneau.
	 * @param bool $timeShare Les parts montrent le temps, quand la structure le suit.
	 * @param string $time Le temps de chaque personne, posé avant les parts : celui de « Récolter ». « Nouvelle
	 *   intervention » prend le temps dans sa propre table, au-dessus du bloc (retour 245).
	 * @param bool $couple Chaque ligne dit sa variété et son calibre : aux Récoltes, qui cochent des couples différents (FD).
	 *   Un couple commun se lit dans ses champs (HM2), la ligne ne le répète pas.
	 */
	public function getHarvestBlock(\util\FormUi $form, \Collection $cTask, array $distributions, string $distribution, \plant\Plant $ePlant, string $unit, array $tasksByUnit, \series\Task $eTaskStock, string $stockId, bool $timeShare, string $time = '', bool $couple = FALSE): string {

		$unitLabels = [];

		foreach(\series\Task::model()->getPropertyEnum('harvestUnit') as $unitListed) {
			$unitLabels[$unitListed] = \product\UnitUi::getValue(0.0, $unitListed, short: TRUE, callback: fn($value, $label) => $label);
		}

		$h = '<div data-planning-batch-harvest data-unit="'.$unit.'" data-units="'.encode(json_encode($unitLabels)).'"'.($time !== '' ? ' data-time-split' : '').'>';

			$h .= $this->getHarvestQuantity($form, $ePlant, $unit, $tasksByUnit);

			$h .= new HarvestEntryUi()->getStock($form, $stockId, $eTaskStock);

			$one = ($cTask->count() === 1);

			$h .= $this->getHarvestDistribution($form, $one, $distributions, $distribution);

			$h .= $time;

			if($one === FALSE) {
				$h .= $this->getHarvestLines($form, $cTask, $distributions, $distribution, $unit, $timeShare, $couple);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La quantité du bloc, dont la liste d'unités dépend de l'unité et des autres unités offertes (`$tasksByUnit`) :
	 * un couple choisi aux Récoltes qui les change la remplace (`harvestWeek:batchHarvestChoice`).
	 */
	public function getHarvestQuantity(\util\FormUi $form, \plant\Plant $ePlant, string $unit, array $tasksByUnit): string {

		return $form->group(
			s("Quantité récoltée"),
			new HarvestEntryUi()->getQuantity($form, $ePlant, $unit, $tasksByUnit),
			['data-wrapper' => HarvestEntryUi::getQuantityWrapper('harvestMore')]
		);

	}

	/**
	 * La clé de répartition, cachée quand une seule série récolte ou qu'une seule clé vaut. Son conteneur reste
	 * le même dans les deux cas : un couple choisi aux Récoltes peut faire passer de l'un à l'autre.
	 */
	public function getHarvestDistribution(\util\FormUi $form, bool $one, array $distributions, string $distribution): string {

		$h = '<div data-planning-batch-harvest-distribution>';

			if($one or count($distributions) === 1) {
				$h .= $form->hidden('distribution', $distribution);
			} else {
				$h .= $form->group(
					s("Répartition"),
					$form->radios('distribution', array_intersect_key(self::getDistributions(), array_flip($distributions)), $distribution, ['required' => TRUE]),
					['data-wrapper' => 'distribution']
				);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La part de chaque série, dans l'ordre des `ids[]`.
	 */
	public function getHarvestLines(\util\FormUi $form, \Collection $cTask, array $distributions, string $distribution, string $unit, bool $timeShare, bool $couple): string {

		$h = '<div class="planning-batch-time-lines">';

			foreach($cTask as $eTask) {

				// Une part n'a ni champ ni refus. Sans `data-wrapper` ni `for` posés, le groupe prendrait ceux du dernier
				// champ du formulaire (`FormUi::group()`), que la réponse ciblée ne rejoue pas.
				$h .= $form->group(
					$this->getHarvestName($eTask, $couple),
					$this->getHarvestShare($eTask, $distributions, $distribution, $unit, $timeShare),
					['class' => 'planning-batch-time-group', 'data-wrapper' => '', 'for' => FALSE]
				);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le nom d'une série de « Récolter » : sa vignette et son nom, sans l'action, la même pour toutes les lignes
	 * (arbitrage du 14/10). Aux Récoltes, le couple de la ligne suit le nom (`$couple`, FD).
	 */
	protected function getHarvestName(\series\Task $eTask, bool $couple): string {

		$h = '<span class="planning-batch-time-name">';
			$h .= \plant\PlantUi::getVignette($eTask['plant'], '1.75rem');
			$h .= '<span class="planning-batch-time-action">'.$this->getTimeSubject($eTask).'</span>';
			if($couple and ($eTask['variety']->notEmpty() or $eTask['harvestSize']->notEmpty())) {
				$h .= '<span>'.HarvestWeekUi::getCoupleName($eTask['variety'], $eTask['harvestSize']).'</span>';
			}
		$h .= '</span>';

		return $h;

	}

	/**
	 * La part d'une série, en lecture : sa récolte, son temps quand la structure le suit, puis ce qui la fonde
	 * selon la clé (`getTimeBasis()`). Les parts s'ouvrent à zéro, comme le total, et `PlanningTaskBatchHarvest`
	 * les recalcule au clavier. Une part se lit à sa place, dans l'ordre des `ids[]` : aux Récoltes, la série
	 * peut n'avoir que l'intervention que l'enregistrement créera (DG-2), sans identifiant à porter.
	 */
	protected function getHarvestShare(\series\Task $eTask, array $distributions, string $distribution, string $unit, bool $time): string {

		$bases = [
			'area' => $eTask['distributionArea'],
			'plant' => $eTask['distributionPlant'],
		];

		$h = '<div class="planning-batch-time-share" data-planning-batch-harvest-share data-bases="'.encode(json_encode($bases)).'">';

			$h .= '<b class="planning-batch-harvest-part">'.\product\UnitUi::getValue(0.0, $unit, short: TRUE).'</b>';

			if($time) {
				$h .= '<span class="planning-batch-time-part">'.DurationUi::getSpent(0.0).'</span>';
			}

			foreach($this->getTimeBasis($eTask, $distributions) as $key => $basis) {
				$h .= '<span class="util-annotation'.($key === $distribution ? '' : ' hide').'" data-basis="'.$key.'">'.$basis.'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les libellés des clés, ceux de la répartition de la quantité dans « Récolter » (planche de BD), que la clé
	 * du temps reprend. La quantité ne se choisit jamais : elle n'a pas de libellé.
	 */
	protected static function getDistributions(): array {

		return [
			'area' => s("En fonction de la surface"),
			'plant' => s("En fonction du nombre de plants"),
			'fair' => s("À parts égales"),
		];

	}

	/**
	 * Le nom d'une ligne, comme sur la carte : l'action, et dessous la série, l'espèce ou la description.
	 */
	protected function getTimeName(\series\Task $eTask): string {

		$subject = $this->getTimeSubject($eTask);

		$h = '<span class="planning-batch-time-name">';
			if($eTask['plant']->notEmpty()) {
				$h .= \plant\PlantUi::getVignette($eTask['plant'], '1.75rem');
			}
			$h .= '<span>';
				$h .= '<span class="planning-batch-time-action">'.encode($eTask['action']['name']).'</span>';
				if($subject !== '') {
					$h .= '<span class="planning-batch-time-subject">'.$subject.'</span>';
				}
			$h .= '</span>';
		$h .= '</span>';

		return $h;

	}

	protected function getTimeSubject(\series\Task $eTask): string {

		if($eTask['series']->notEmpty()) {
			return \series\SeriesUi::name($eTask['series']);
		} else if($eTask['plant']->notEmpty()) {
			return encode($eTask['plant']['name']);
		} else {
			return new \series\TaskUi()->getTaskDescription($eTask);
		}

	}

	/**
	 * La part d'une ligne, en lecture (retour 265), puis ce qui la fonde : sa surface, son nombre de plants, sa
	 * récolte du jour ou « Rien récolté ce jour », « Hors culture », ou rien au partage égalitaire. La part
	 * s'ouvre à zéro, comme le total. Les bases du partage sont celles du serveur (`TaskBatchLib::getBase()`),
	 * écrites à la précision du calcul pour que la part lue soit celle qui s'écrira.
	 *
	 * Une intervention à faire verrouillée, au rôle planning limité, ne reçoit rien et se dit à faire.
	 */
	protected function getTimeShare(\series\Task $eTask, array $distributions, ?string $distribution, string $date): string {

		if($eTask['timeLocked']) {
			return '<span class="util-annotation">'.s("À faire").'</span>';
		}

		$bases = [
			'area' => $eTask['distributionArea'],
			'plant' => $eTask['distributionPlant'],
			'harvest' => (object)$eTask['harvestWeek'],
		];

		$h = '<div class="planning-batch-time-share" data-planning-batch-time-task="'.$eTask['id'].'" data-bases="'.encode(json_encode($bases)).'">';

			$h .= '<span class="planning-batch-time-part">'.DurationUi::getSpent(0.0).'</span>';

			foreach($this->getTimeBasis($eTask, $distributions) as $key => $basis) {
				$shown = ($key === $distribution or $key === 'harvest-'.$date);
				$h .= '<span class="util-annotation'.($shown ? '' : ' hide').'" data-basis="'.$key.'">'.$basis.'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qui fonde la part d'une ligne, par clé, et pour la quantité, par jour de la semaine. La quantité ne
	 * fonde une part que dans une sélection de récoltes seules (`TaskBatchLib::getDistributions()`) : ailleurs,
	 * une récolte se partage comme les autres lignes.
	 */
	protected function getTimeBasis(\series\Task $eTask, array $distributions): array {

		if($eTask['category']['fqn'] !== CATEGORIE_CULTURE) {
			return ['fair' => s("Hors culture")];
		}

		$basis = [];

		if($eTask['distributionArea'] !== NULL) {
			$basis['area'] = s("{value} m²", round($eTask['distributionArea']));
		}

		if($eTask['distributionPlant'] !== NULL) {
			$basis['plant'] = p("{value} plant", "{value} plants", round($eTask['distributionPlant']));
		}

		if($distributions !== ['harvest']) {
			return $basis;
		}

		foreach($eTask['harvestWeek'] as $date => $quantity) {
			$basis['harvest-'.$date] = ($quantity > 0.0) ? \product\UnitUi::getValue($quantity, $eTask['harvestUnit']) : s("Rien récolté ce jour");
		}

		return $basis;

	}

	/**
	 * « Planifier » : les sept jours de la semaine affichée, cette semaine, puis décaler. Les entrées
	 * d'un menu déroulant vivent hors du formulaire du lot, qu'elles désignent (`data-ajax-target`).
	 */
	protected function getPlanned(string $formId, string $url, array $attributes, string $week): string {

		$target = attrs($attributes + ['data-ajax-target' => '#'.$formId]);

		$h = '<div class="dropdown-list bg-secondary">';

			$h .= '<div class="dropdown-title">'.s("Planifier un jour précis").'</div>';
			$h .= '<div class="batch-planned-days">';
				foreach(week_dates($week) as $position => $date) {
					$h .= '<a data-ajax-submit="'.$url.'doBatchPlannedDate" '.$target.' post-planned-date="'.$date.'" class="dropdown-item">';
						$h .= '<div>'.\util\DateUi::getDayName($position + 1).'</div>';
						$h .= '<div class="batch-planned-days-date">'.\util\DateUi::numeric($date, \util\DateUi::DAY_MONTH).'</div>';
					$h .= '</a>';
				}
			$h .= '</div>';

			$h .= '<div class="dropdown-title">'.s("Planifier à la semaine").'</div>';
			$h .= '<div class="batch-planned-weeks">';
				$h .= '<a data-ajax-submit="'.$url.'doBatchPlannedWeek" '.$target.' post-planned-week="'.\Clock::week().'" class="dropdown-item batch-planned-other">';
					$h .= '<div>'.s("Cette semaine").'</div>';
					$h .= '<div class="batch-planned-days-date">'.s("Semaine {week}, {year}", ['week' => week_number(\Clock::week()), 'year' => week_year(\Clock::week())]).'</div>';
				$h .= '</a>';
				$h .= '<a data-ajax-submit="/series/task:updatePlannedCollection" data-ajax-method="get" data-ajax-target="#'.$formId.'" class="dropdown-item batch-planned-other">';
					$h .= '<div>'.s("Une autre semaine").'</div>';
				$h .= '</a>';
			$h .= '</div>';

			$h .= '<a data-ajax-submit="'.$url.'doBatchIncrement" '.$target.' post-increment="-1" class="dropdown-item planning-batch-postpone">'.s("Décaler une semaine plus tôt").'</a>';
			$h .= '<a data-ajax-submit="'.$url.'doBatchIncrement" '.$target.' post-increment="1" class="dropdown-item planning-batch-postpone">'.s("Décaler une semaine plus tard").'</a>';
			$h .= '<a data-ajax-submit="/series/task:incrementPlannedCollection" data-ajax-method="get" data-ajax-target="#'.$formId.'" class="dropdown-item planning-batch-postpone">'.s("Décaler davantage").'</a>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * « Affecter » : chaque membre, ajouté aux interventions choisies, ou retiré quand toutes l'ont
	 * déjà, ce que `PlanningTaskSelection.change()` pose sur l'entrée (`post-action`).
	 */
	protected function getUsers(string $formId, string $url, array $attributes, \Collection $cUser): string {

		$target = attrs($attributes + ['data-ajax-target' => '#'.$formId]);

		// La liste se repère par sa propre classe : ouverte, `Lime.Dropdown` la déplace sous `body`, hors de `.planning-batch-users`.
		$h = '<div class="dropdown-list bg-secondary planning-batch-users-list'.self::getUsersColumns($cUser).'">';
			$h .= '<div class="dropdown-title">'.s("Affecter à...").'</div>';
			foreach($cUser as $eUser) {
				$h .= '<a data-ajax-submit="'.$url.'doBatchUser" '.$target.' post-user="'.$eUser['id'].'" post-action="add" class="batch-planned-user dropdown-item">';
					$h .= \Asset::icon('plus-lg');
					$h .= \Asset::icon('x-lg');
					$h .= '  '.\user\UserUi::getVignette($eUser, '1.5rem').'  '.$eUser->getName();
				$h .= '</a>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * « Affecter à... » passe sur deux colonnes à partir de six personnes (R-Retours-0810, 180), ici comme dans
	 * la grille de la Semaine (`getUsersDropdown()`) : seize personnes sur une colonne sortaient de l'écran.
	 */
	public static function getUsersColumns(\Collection $cUser): string {

		return ($cUser->count() >= 6) ? ' dropdown-list-2' : '';

	}

	/**
	 * « Affecter à... » d'une ligne de la grille de la Semaine (retours 328 et 333) : un déclencheur, puis sa
	 * liste, qui arrive vide, son seul titre posé, et que la route `users` de l'écran remplit à la première
	 * ouverture. `$key` nomme l'écran (`planning-grid`), dont dérivent les identifiants et l'attribut que lit
	 * son JS.
	 * Ouverte, la liste vit sous `body` (`Lime.Dropdown`), hors de la ligne : le déclencheur désigne donc sa ligne,
	 * et chaque entrée la sienne (`data-task`). `data-dropdown-keep` la garde ouverte, pour choisir plusieurs
	 * personnes de suite. Ses colonnes se décident sur les membres que l'écran a lus (`$cUser`).
	 */
	public static function getUsersDropdown(string $key, int $line, \Collection $cUserShown, \Collection $cUser): string {

		$h = '<a class="'.self::getUsersToggleClass($cUserShown).'" data-dropdown="bottom-start" data-'.$key.'-users="'.$line.'" id="'.$key.'-toggle-'.$line.'">'.self::getUsersToggle($cUserShown).'</a>';

		// La classe de la liste s'écrit en entier : c'est par elle que les délégués de chaque écran et la feuille la
		// retrouvent sous `body`.
		$list = match($key) {
			'planning-grid' => 'planning-grid-users-list'
		};

		$h .= '<div class="dropdown-list '.$list.self::getUsersColumns($cUser).'" id="'.$key.'-users-'.$line.'" data-dropdown-keep>';
			$h .= '<div class="dropdown-title">'.s("Affecter à...").'</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le déclencheur est le bouton qui ajoute une personne tant qu'il n'y en a pas, puis les vignettes suivies du
	 * seul caret. Une affectation ne redessine que sa classe et son contenu : le nœud reste, `Lime.Dropdown`
	 * fermant le menu dont le déclencheur quitte la page.
	 */
	public static function getUsersToggleClass(\Collection $cUserShown): string {

		return $cUserShown->empty() ? 'util-quick planning-users-toggle planning-users-toggle-empty dropdown-toggle' : 'planning-users-toggle dropdown-toggle';

	}

	public static function getUsersToggle(\Collection $cUserShown): string {

		if($cUserShown->empty()) {
			return \Asset::icon('person-plus');
		}

		return self::getUsersVignettes($cUserShown);

	}

	/**
	 * Les vignettes d'une ligne, dans son déclencheur, ou seules quand la ligne ne s'affecte pas.
	 */
	public static function getUsersVignettes(\Collection $cUserShown): string {

		$h = '<span class="planning-users-vignettes">';
			foreach($cUserShown as $eUser) {
				$h .= \user\UserUi::getVignette($eUser, '1.5rem');
			}
		$h .= '</span>';

		return $h;

	}

	/**
	 * Une entrée de la liste, qui ajoute la personne d'un clic, ou la retire quand elle est de la ligne.
	 */
	public static function getUser(string $key, int $line, \user\User $eUser, bool $selected): string {

		$h = '<a class="dropdown-item batch-planned-user'.($selected ? ' selected' : '').'" id="'.$key.'-user-'.$line.'-'.$eUser['id'].'" data-task="'.$line.'" data-'.$key.'-user="'.$eUser['id'].'" post-action="'.($selected ? 'delete' : 'add').'">';
			$h .= \Asset::icon('plus-lg');
			$h .= \Asset::icon('x-lg');
			$h .= '  '.\user\UserUi::getVignette($eUser, '1.5rem').'  '.$eUser->getName();
		$h .= '</a>';

		return $h;

	}

}
?>
