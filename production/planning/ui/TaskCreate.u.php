<?php
namespace planning;

/**
 * « Nouvelle intervention » de la Semaine et du Jour (L5d), panneau `creer()` de la maquette navigable :
 * faite ou à faire, fixé par l'entrée qui l'ouvre (piste F de R-CreationRapide), puis l'intervention, la
 * série, la semaine et le jour sous « Quand », le temps de chacun ou le temps prévu, les méthodes et le
 * matériel, la description et la répétition (`TaskCreateLib::createCollection()`).
 *
 * Quatre entrées l'ouvrent : « + À faire » et « + Fait » de la rangée des outils, le « + » d'un bloc
 * de la Semaine ou d'un groupe d'action du Jour, qui pose l'action (écart d), le « + » d'un jour à venir du
 * Jour, qui pose le jour, et « J'ai aussi fait » d'un jour passé du Jour ou du Temps de travail, qui pose le
 * jour et coche Fait (retour 293).
 *
 * Les séries se choisissent par une autocomplétion (`taskDay:query`, écart c), en mode
 * multiple : chaque culture choisie donne une intervention, avec sa série et son espèce, et aucune culture
 * en donne une seule, hors série.
 */
class TaskCreateUi {

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

	}

	/**
	 * L'adresse du panneau.
	 *
	 * @param string $page `taskWeek`, `taskDay`, `taskYear` ou `timeDay`, la page dont les routes ouvrent le panneau.
	 * @param ?string $current La semaine que l'Année affiche, quand le panneau prévoit une autre de ses semaines.
	 * @param ?string $status L'état du panneau, posé par « + À faire » et « + Fait », Fait pour « J'ai aussi fait »
	 *   (`TaskCreateLib::getStatus()`).
	 * @param \user\User $eUserTime La personne de la fiche de temps d'où vient le panneau, dont `$date` est le jour ouvert.
	 * @param ?string $view L'affichage de la Semaine, cartes ou grille (CW), que le panneau réémet pour que la réponse dessine l'écran que l'on regarde.
	 * @param \series\Series $eSeries La série de la fiche du plan d'assolement d'où vient le panneau (GM),
	 * qui le remplit de ses cultures et le fait poster sur la fiche.
	 */
	public static function url(\structure\Structure $eStructure, string $page, string $week, \work\Action $eAction = new \work\Action(), ?string $date = NULL, ?string $current = NULL, ?string $status = NULL, \user\User $eUserTime = new \user\User(), ?string $view = NULL, \series\Series $eSeries = new \series\Series()): string {

		$arguments = [
			'id' => $eStructure['id'],
			'week' => $week,
		];

		if($eAction->notEmpty()) {
			$arguments['action'] = $eAction['id'];
		}

		if($date !== NULL) {
			$arguments['date'] = $date;
		}

		if($current !== NULL) {
			$arguments['current'] = $current;
		}

		if($status !== NULL) {
			$arguments['status'] = $status;
		}

		if($eUserTime->notEmpty()) {
			$arguments['user'] = $eUserTime['id'];
		}

		if($view !== NULL) {
			$arguments['view'] = $view;
		}

		if($eSeries->notEmpty()) {
			$arguments['origin'] = 'assolement';
			$arguments['series'] = $eSeries['id'];
		}

		return '/planning/'.$page.':create?'.http_build_query($arguments);

	}

	/**
	 * « + À faire » et « + Fait » de la rangée des outils (piste F de R-CreationRapide) : chacun ouvre le panneau
	 * sur son état, que le panneau ne change plus. Un état que la semaine ne peut pas porter n'a pas de bouton
	 * (`$canCreateByStatus`) : Fait sur une semaine dont aucun jour n'est passé.
	 */
	public static function getButton(\structure\Structure $eStructure, string $page, string $week, array $canCreateByStatus, ?string $view = NULL, ?string $current = NULL): string {

		$buttons = '';

		foreach($canCreateByStatus as $status => $canCreate) {

			if($canCreate) {
				$buttons .= self::getButtonTo(self::url($eStructure, $page, $week, current: $current, status: $status, view: $view), $status);
			}

		}

		if($buttons === '') {
			return '';
		}

		return '<div class="planning-tools-create-buttons">'.$buttons.'</div>';

	}

	/**
	 * Le bouton d'un état vers le panneau donné, « + À faire » ou « + Fait » : celui d'une colonne des Colonnes
	 * (V57), celui du Temps de travail, qui ouvre Fait sur son jour et sa personne (retours 291 et 293), et ceux
	 * de la fiche d'une série du plan d'assolement.
	 */
	public static function getButtonTo(string $url, string $status): string {

		return '<a href="'.encode($url).'" class="btn btn-outline-primary">'.\Asset::icon('plus-lg').' '.\series\TaskUi::p('status')->values[$status].'</a>';

	}

	/**
	 * Les mêmes gestes dans l'état vide d'un écran sans aucune intervention, dont la rangée des outils est retirée
	 * (R-Retours-0410, point 18) : un bouton principal de `util-block-empty` par état que la semaine peut créer,
	 * l'état se fixant à l'ouverture du panneau (piste F de R-CreationRapide).
	 */
	public static function getEmptyButton(\structure\Structure $eStructure, string $page, string $week, array $canCreateByStatus, ?string $view = NULL): string {

		$h = '<div class="util-buttons">';

			foreach($canCreateByStatus as $status => $canCreate) {

				if($canCreate) {
					$h .= '<a href="'.encode(self::url($eStructure, $page, $week, status: $status, view: $view)).'" class="btn btn-primary">'.\Asset::icon('plus-lg').' '.\series\TaskUi::p('status')->values[$status].'</a>';
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le panneau. Une action posée par le « + » d'un bloc ne se choisit plus : elle se rappelle en
	 * sous-titre et part en champ caché avec sa catégorie. Sinon, elle se cherche (`getActionField()`),
	 * récolte comprise, ou se prend parmi les pastilles des plus fréquentes (`getShortcuts()`).
	 *
	 * L'état, faite ou à faire, ouvre le formulaire, coché sur le bouton qui ouvre le panneau, et se change
	 * encore (retours sur la piste F) : les champs des deux états sont rendus, ceux de l'autre cachés
	 * (`getFor()`). « Quand » vient juste au-dessus de la récolte et du temps (retour 258), la semaine puis le
	 * jour (`getWhen()`). Faite, l'intervention prend le temps de chaque personne (retour 245), et une récolte sa
	 * quantité (retour 258) ; à faire, son temps prévu et sa répétition.
	 *
	 * Sous « Espèce ou série », « Partagé » puis les champs de l'ancien formulaire qui dépendent des séries
	 * choisies (`getShared()`, `getFields()`, retour 256). « Méthodes de travail » et « Matériel nécessaire »
	 * (`getWorkFields()`, retour 255) viennent sous le temps : ils valent pour Fait comme pour À faire.
	 *
	 * @param string $page La page dont les routes ouvrent le panneau, ou `placeSheet` quand il s'ouvre depuis la fiche
	 *   d'une planche, qui reçoit alors l'envoi (`getCreateUrl()`).
	 * @param string $week La semaine de l'écran, que le panneau réémet pour que la réponse le dessine.
	 * @param string $when La semaine de l'intervention, celle de l'écran à l'ouverture.
	 * @param array $weeks Les semaines proposées en pastilles (`TaskCreateLib::getWeeks()`).
	 * @param \series\Task $eTask L'intervention du panneau et son action, vide quand elle se cherche.
	 * @param \Collection $cActionShortcut Les pastilles d'actions (`TaskCreateLib::getShortcuts()`).
	 * @param string $status L'état coché à l'ouverture (`TaskCreateLib::getStatus()`).
	 * @param array $days Les sept jours de `$when` (`TaskCreateLib::getDays()`).
	 * @param \Collection $cUserTime Les personnes du temps d'une intervention faite (`TaskCreateLib::getTimeUsers()`), aucune
	 *   sans suivi du temps : Fait n'a alors que le jour et la description.
	 * @param ?string $current La semaine que l'Année affiche, réémise telle quelle.
	 * @param array $post Les champs que la page réémet pour répondre par l'écran que l'on regarde, la personne et
	 *   le jour ouvert de la fiche de temps.
	 */
	public function create(\structure\Structure $eStructure, string $page, string $week, string $when, array $weeks, \series\Task $eTask, \work\Category $eCategoryAction, \Collection $cActionShortcut, ?string $date, string $status, array $days, \Collection $cUserTime = new \Collection(), ?string $current = NULL, array $post = []): \Panel {

		$form = new \util\FormUi();

		$eAction = $eTask['action'];

		$h = $form->hidden('id', $eStructure);
		$h .= $form->hidden('week', $week);

		if($current !== NULL) {
			$h .= $form->hidden('current', $current);
		}

		foreach($post as $name => $value) {
			$h .= $form->hidden($name, $value);
		}

		$statuses = \series\TaskUi::p('status')->values;

		$h .= $form->group(
			content: $form->radios('status', [
				\series\Task::DONE => $statuses[\series\Task::DONE],
				\series\Task::TODO => $statuses[\series\Task::TODO],
			], $status, [
				'columns' => 2,
				'required' => TRUE,
			]),
			attributes: ['class' => 'planning-task-create-status']
		);

		if($eAction->notEmpty()) {

			$h .= $form->hidden('action', $eAction);
			$h .= $form->hidden('category', $eCategoryAction);

		} else {
			$h .= self::getActionField($form, $eStructure, $when, $cActionShortcut);
		}

		$h .= $form->group(
			s("Espèce ou série"),
			$form->dynamicField(new \series\Task(['cultivation' => new \series\Cultivation()]), 'cultivation', function(\PropertyDescriber $d) use($eStructure, $when, $eTask) {

				$d->prepend = \Asset::icon('flower2');
				$d->field = 'autocomplete';
				$d->multiple = TRUE;
				$d->placeholder = s("Tapez un nom de série ou d'espèce");
				$d->attributes = [
					'data-planning-task-escape' => 'true',
					'data-planning-task-exclude' => 'true',
					'data-planning-task-choice' => 'true',
				];
				$d->autocompleteUrl = '/planning/taskDay:query';
				$d->autocompleteBody = [
					'id' => $eStructure['id'],
					'week' => $when,
				];
				$d->autocompleteHead = self::getSeasonHead($when);
				$d->autocompleteKeepQuery = TRUE;
				$d->autocompleteResults = fn(\series\Cultivation $e) => TaskDayUi::getAutocomplete($e);
				$d->autocompleteDefault = $eTask['cCultivationChosen'];

				// Des cultures posées d'office n'émettent pas `autocompleteUpdate` : variété, calibre et « Partagé »
				// se demandent au rendu, sans quoi le panneau changerait de forme au premier geste. `onrender`
				// remplace celui de `FormUi::autocomplete()`, qui démarre le champ : le reprendre.
				if($eTask['cCultivationChosen']->notEmpty()) {
					$d->attributes['onrender'] = 'AutocompleteField.start(this); PlanningTaskChoice.update(this);';
				}

			}),
			['data-wrapper' => 'cultivation plant']
		);

		$h .= self::getShared();
		$h .= self::getFields($form, $eTask);

		$h .= $form->group(
			s("Quand").'<span class="'.match($status) {
				\series\Task::DONE => '',
				\series\Task::TODO => 'hide',
			}.'" data-planning-task-create-asterisk>'.\util\FormUi::asterisk().'</span>',
			'<div data-planning-task-create-when>'.$this->getWhen($eStructure, $weeks, $when, $days, $date, $status).'</div>',
			['data-wrapper' => 'when day planned plannedWeek plannedDate done']
		);

		$h .= $this->getHarvest($eAction);

		$h .= $this->getFor(\series\Task::DONE, $status, '<div data-planning-task-create-time>'.$this->getTime($form, $cUserTime).'</div>');

		if($eStructure->hasTime()) {

			$h .= $this->getFor(\series\Task::TODO, $status, $form->group(
				s("Temps prévu"),
				new HarvestEntryUi()->getTimeExpectedField($form),
				['data-wrapper' => 'timeExpected']
			));

		}

		$h .= self::getWorkFields($form, $eTask);

		$h .= $form->dynamicGroup($eTask, 'description', function(\PropertyDescriber $d) {
			$d->label = s("Description");
		});

		$h .= $this->getFor(\series\Task::TODO, $status, $form->group(
			s("Répéter"),
			'<div class="planning-create-repeat">'.
				$form->select('frequency', \series\RepeatUi::p('frequency')->values, NULL, ['placeholder' => s("Une seule fois")]).
				'<div class="planning-create-repeat-stop">'.
					'<span class="util-label">'.s("jusqu'à").'</span>'.
					$form->week('stop').
				'</div>'.
			'</div>',
			['data-wrapper' => 'frequency stop repeatMaster']
		));

		$h .= $form->group(
			content: $form->submit(s("Ajouter l'intervention"))
		);

		return new \Panel(
			id: 'panel-planning-task-create',
			title: s("Nouvelle intervention"),
			subTitle: $eAction->empty() ? NULL : encode($eAction['name']),
			dialogOpen: $form->openAjax(self::getCreateUrl($page), ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h
		);

	}

	/**
	 * Les champs d'un seul des deux états, fait ou à faire. Ceux de l'autre restent rendus, cachés, et leur
	 * `fieldset` désactivé ne les poste pas : `PlanningTaskCreateStatus` bascule les deux sans retour au serveur.
	 */
	protected function getFor(string $for, string $status, string $content): string {

		$shown = ($for === $status);

		return '<fieldset class="planning-task-create-for'.($shown ? '' : ' hide').'" data-planning-task-create-for="'.$for.'"'.($shown ? '' : ' disabled').'>'.$content.'</fieldset>';

	}

	/**
	 * Où poste le panneau : la page d'où il s'ouvre, qui redessine ce que l'on regarde, ou la fiche d'une série du
	 * plan d'assolement (`placeSheet`), qui se redessine.
	 */
	private static function getCreateUrl(string $page): string {

		return match($page) {
			'taskWeek', 'taskDay', 'taskYear', 'timeDay' => '/planning/'.$page.':doCreate',
			'placeSheet' => '/series/place:doCreate'
		};

	}

	/**
	 * « Partagé » de l'ancien formulaire (retour 256), sous « Espèce ou série » : pour chaque culture choisie dont la
	 * série en compte plusieurs, la culture, choisie d'office, ou l'intervention de la série. Le conteneur est vide
	 * jusqu'au premier choix, que `PlanningTaskChoice` envoie à `taskDay:getCultivationFields`.
	 */
	public static function getShared(): string {

		return '<div data-ref="shared"></div>';

	}

	/**
	 * Le contenu de « Partagé », la réponse de `taskDay:getCultivationFields` : un choix par culture partageable
	 * (`TaskCreateLib::getNewForChoices()`), sous le nom de sa série, en une colonne comme dans l'ancien formulaire : la
	 * vignette de la culture abaisse la ligne de base de son libellé, que « Partagé » n'a pas. Une récolte n'est jamais
	 * partagée, comme dans l'ancien formulaire, et une action hors culture refuse la culture elle-même
	 * (`TaskCreateLib::prepare()`, `cultivation.category`) : le `fieldset` est alors caché et désactivé, et ne poste
	 * rien (`PlanningTaskAction.toggle()`).
	 */
	public static function getSharedFields(\util\FormUi $form, \series\Task $eTask): string {

		if($eTask['cCultivationShared']->empty()) {
			return '';
		}

		$harvest = ($eTask['action']->notEmpty() and $eTask['action']['fqn'] === ACTION_RECOLTE);
		$culture = ($eTask['category']->notEmpty() and $eTask['category']['fqn'] === CATEGORIE_CULTURE);
		$outside = ($eTask['category']->notEmpty() and $culture === FALSE);
		$hidden = ($harvest or $outside);

		$h = '<fieldset class="planning-task-create-for'.($hidden ? ' hide' : '').'" data-planning-task-create-shared'.($hidden ? ' disabled' : '').'>';

			foreach($eTask['cCultivationShared'] as $eCultivation) {

				$h .= $form->group(
					encode($eCultivation['series']['name']),
					$form->radios('shared['.$eCultivation['id'].']', [
						0 => \plant\PlantUi::getVignette($eCultivation['plant'], '2rem').' '.encode($eCultivation['plant']['name']),
						1 => s("Partagé"),
					], 0, [
						'required' => TRUE,
					])
				);

			}

		$h .= '</fieldset>';

		return $h;

	}

	/**
	 * Les champs de l'ancien formulaire (`TaskUi::createFromSeries()`, `createFromScratch()`) de « Nouvelle
	 * intervention », dans son ordre, sous « Espèce ou série » :
	 * - variété et calibre, qui dépendent des choix d'« Espèce ou série » : leurs conteneurs sont vides jusqu'au
	 *   premier choix (`PlanningTaskChoice`). Une action hors culture refuse la culture que la variété précise : son
	 *   `fieldset` est alors caché et désactivé. Sans action, rien n'est encore refusé et il reste ouvert ;
	 * - le produit ou les intrants, la fertilisation et le traitement (retour 256), qui ne valent que pour une action
	 *   de culture (`createFromScratch()`) : hors culture, leur `fieldset` est caché et désactivé, et ne poste rien.
	 * « Méthodes de travail » et « Matériel nécessaire » viennent plus bas, sous les champs du statut
	 * (`getWorkFields()`). Ceux qui dépendent de l'action sont rendus pour celle de `$eTask`
	 * (`TaskCreateLib::getNew()`), et `PlanningTaskAction.fields()` les redessine ou les montre quand elle change.
	 */
	public static function getFields(\util\FormUi $form, \series\Task $eTask): string {

		$uiTask = new \series\TaskUi();

		$culture = ($eTask['category']->notEmpty() and $eTask['category']['fqn'] === CATEGORIE_CULTURE);
		$outside = ($eTask['category']->notEmpty() and $culture === FALSE);

		$h = '<fieldset class="planning-task-create-for'.($outside ? ' hide' : '').'" data-ref="variety" data-planning-task-create-variety'.($outside ? ' disabled' : '').'></fieldset>';

		$h .= '<div data-ref="size"></div>';

		$h .= '<fieldset class="planning-task-create-for'.($culture ? '' : ' hide').'" data-planning-task-create-culture'.($culture ? '' : ' disabled').'>';
			$h .= $uiTask->getProductsGroup($form, $eTask);
			$h .= $uiTask->getFertilizerField($form, $eTask);
			$h .= $uiTask->getTreatmentField($form, $eTask);
		$h .= '</fieldset>';

		return $h;

	}

	/**
	 * « Méthodes de travail » et « Matériel nécessaire » (retour 255), sous les champs du statut (DI) : vides quand
	 * l'action n'en propose pas, `PlanningTaskAction.fields()` les redessine quand elle change.
	 */
	public static function getWorkFields(\util\FormUi $form, \series\Task $eTask): string {

		$uiTask = new \series\TaskUi();

		return $uiTask->getMethodsGroup($form, $eTask).
			$uiTask->getToolsGroup($form, $eTask);

	}

	/**
	 * Le calibre de récolte (retour 256), la réponse de `taskDay:getCultivationFields` : quand l'espèce choisie a des
	 * calibres, caché hors récolte comme dans l'ancien formulaire (`TaskUi::getHarvestSizeField()`).
	 */
	public static function getSize(\util\FormUi $form, \series\Task $eTask): string {

		if($eTask['cSize']->empty()) {
			return '';
		}

		return new \series\TaskUi()->getHarvestSizeField($form, $eTask);

	}

	/**
	 * La récolte d'une récolte faite (retour 258), sous « Quand » (DG-5) : rendue vide, et cachée et désactivée
	 * pour ne rien poster, elle prend par `taskDay:harvest` (`PlanningTaskCreateHarvest`) le bloc de « Récolter »
	 * (`TaskBatchUi::getHarvestBlock()`) : la quantité et « Ajouter à un stock » (DG-4), puis pour plusieurs séries
	 * de la même espèce la clé et les parts. Le temps de chaque personne reste dans « Temps de travail »
	 * (retour 245), que les parts partagent : le bloc n'a pas de « Temps total ».
	 */
	protected function getHarvest(\work\Action $eAction): string {

		$harvest = ($eAction->notEmpty() and $eAction['fqn'] === ACTION_RECOLTE);

		return '<fieldset class="planning-task-create-for hide" data-planning-task-create-harvest="'.($harvest ? '1' : '0').'" disabled>'.
			'<div data-planning-task-create-quantity></div>'.
		'</fieldset>';

	}

	/**
	 * « Quand » (piste A de R-CreationQuand) : un onglet par semaine proposée, relative à la semaine en cours
	 * (`TaskCreateLib::getWeeks()`), puis « Autre… », qui ouvre le calendrier des saisons de la structure, et sous
	 * l'onglet choisi le tiroir des jours de sa semaine (`util-week-picker`). Une semaine sans onglet prend celui
	 * d'« Autre… », renommé d'après elle. La semaine part dans le champ caché `when`, le jour dans `day`, vide pour
	 * « Non défini » (`PlanningTaskCreateDay`, `planning.js`), et `taskDay:getWhen` redessine le tout quand la
	 * semaine change (`PlanningTaskCreateWhen`).
	 *
	 * À faire, « Non défini » ouvre le tiroir, comme la colonne du Jour. Faite, l'intervention a un jour qui n'est
	 * pas à venir (retour 258) : les jours à venir sont grisés (`is-disabled`).
	 */
	public function getWhen(\structure\Structure $eStructure, array $weeks, string $when, array $days, ?string $date, string $status): string {

		$form = new \util\FormUi();
		$uiTimeWeek = new TimeWeekUi();

		$h = $form->hidden('when', $when);
		$h .= $form->hidden('day', $date ?? '');

		$h .= '<div class="util-week-picker">';

			$h .= '<div class="util-week-picker-weeks">';

				foreach($weeks as $week => $position) {

					$name = match($position) {
						'current' => s("Cette semaine"),
						'next' => s("Semaine prochaine"),
						'previous' => s("Semaine dernière"),
					};

					$h .= $this->getWeek($name, $week, $week === $when, 'data-planning-task-create-week="'.$week.'"');

				}

				if(array_key_exists($when, $weeks)) {
					$h .= '<a class="util-week-picker-week util-week-picker-other" data-dropdown="bottom-start">'.s("Autre…").'</a>';
				} else {
					$h .= $this->getWeek(s("Semaine {value}", week_number($when)), $when, TRUE, 'data-dropdown="bottom-start"');
				}

				$h .= '<div class="dropdown-list dropdown-list-minimalist">';
					$h .= \util\FormUi::weekSelector(
						(int)week_year($when),
						onclickWeeks: 'PlanningTaskCreateWhen.select(\'__CURRENT__\')',
						defaultWeek: $when,
						minYear: $eStructure->conf(\map\Configuration::class)->getFirstValidSeason(),
						maxYear: $eStructure->conf(\map\Configuration::class)->getLastValidSeason(),
						// Aucun jour d'une semaine à venir ne peut porter Fait.
						maxWeek: match($status) {
							\series\Task::TODO => NULL,
							\series\Task::DONE => \Clock::week(),
						}
					);
				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="util-week-picker-days util-days">';

				if($status === \series\Task::TODO) {
					$h .= $uiTimeWeek->getDayNone(['util-day'], $date === NULL, 'data-planning-task-create-day=""');
				}

				foreach($days as $dateListed => $day) {

					$selected = ($dateListed === $date);

					$classes = ['util-day'];

					if($day['today']) {
						$classes[] = 'is-today';
					}

					if($selected) {
						$classes[] = 'is-selected';
					}

					if($status === \series\Task::DONE and $day['done'] === FALSE) {
						$classes[] = 'is-disabled';
					}

					$h .= $uiTimeWeek->getDayDate($dateListed, $classes, $selected, 'data-planning-task-create-day="'.$dateListed.'"');

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un onglet de semaine de « Quand » : son nom, puis ses dates.
	 */
	protected function getWeek(string $name, string $week, bool $selected, string $attributes): string {

		$h = '<a class="util-week-picker-week'.($selected ? ' selected' : '').'" '.$attributes.'>';
			$h .= '<span class="util-week-picker-week-name">'.$name.'</span>';
			$h .= '<span class="util-week-picker-week-dates">'.\util\DateUi::weekToDays($week, short: TRUE, withYear: FALSE).'</span>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * « Temps de travail » d'une intervention faite, que `taskDay:getWhen` redessine pour les personnes de la
	 * semaine choisie (`TaskCreateLib::getTimeUsers()`).
	 */
	public function getTime(\util\FormUi $form, \Collection $cUserTime): string {

		if($cUserTime->empty()) {
			return '';
		}

		return $form->group(
			s("Temps de travail"),
			new HarvestEntryUi()->getPeople($form, $cUserTime),
			['data-wrapper' => 'time timeTeam']
		);

	}

	/**
	 * La rangée en tête de la liste « Espèce ou série » de « Nouvelle intervention »
	 * (J-193) : la saison où chercher, parmi celle de la semaine, choisie d'office, et ses deux voisines. Elle
	 * part sous le nom `season` avec la recherche (`taskDay:query`).
	 */
	public static function getSeasonHead(string $week): array {

		$season = week_year($week);

		$values = [];

		foreach([$season - 1, $season, $season + 1] as $value) {
			$values[$value] = (string)$value;
		}

		return [
			'name' => 'season',
			'label' => s("Saison"),
			'values' => $values,
			'selected' => $season,
		];

	}

	/**
	 * Le champ « Intervention » de « Nouvelle intervention » (retour 192) :
	 * l'autocomplétion du cadriciel, qui cherche les actions de la structure par `taskDay:queryAction` et
	 * s'ouvre sur toutes. L'action choisie part sous le nom `action`, et sa catégorie, celle sous laquelle
	 * elle a été choisie, dans le champ caché `category`, que `PlanningTaskAction.select()` remplit.
	 *
	 * Sous le champ, les actions les plus fréquentes en pastilles (piste F de R-CreationRapide,
	 * `TaskCreateLib::getShortcuts()`), chacune précédée du rond de son action, comme dans la liste :
	 * une pastille porte le résultat que la recherche rendrait pour elle (`getActionResult()`), et la choisir
	 * remplit le champ comme un choix dans la liste (`PlanningTaskCreateShortcut`).
	 *
	 * @param string $when La semaine de l'intervention, que la recherche poste sous `week`, le nom que lit le
	 *   constructeur de `taskDay`.
	 */
	public static function getActionField(\util\FormUi $form, \structure\Structure $eStructure, string $when, \Collection $cActionShortcut): string {

		$body = [
			'id' => $eStructure['id'],
			'week' => $when,
		];

		$h = $form->hidden('category', '');

		$h .= $form->group(
			s("Intervention").\util\FormUi::asterisk(),
			$form->dynamicField(new \series\Task(['action' => new \work\Action()]), 'action', function(\PropertyDescriber $d) use($body) {

				// Le describer de `Task::action` est celui des boutons de l'ancien planning : ici, ni liste à lire
				// (`cAction`), les actions venant de la recherche, ni `required` natif, le refus restant celui du
				// serveur (`Task::action.check`), affiché sous le champ comme pour les autres.
				$d->values = [];
				$d->required = FALSE;

				$d->prepend = \Asset::icon('list-task');
				$d->placeholder = s("Tapez un nom d'intervention");
				$d->field = 'autocomplete';
				$d->autocompleteUrl = '/planning/taskDay:queryAction';
				$d->autocompleteBody = $body;

				// `onrender` remplace celui de `FormUi::autocomplete()`, qui démarre le champ : le reprendre, sinon
				// rien ne se cherche. Le curseur ne s'y pose pas à l'ouverture, décidé (retour 260) : la
				// liste s'ouvre au clic dans le champ.
				$d->attributes = [
					'data-planning-task-action' => 'true',
					'data-planning-task-escape' => 'true',
					'onrender' => 'PlanningAutocomplete.start(this);',
				];

			}).
			self::getShortcuts($cActionShortcut),
			['data-wrapper' => 'action category']
		);

		return $h;

	}

	protected static function getShortcuts(\Collection $cActionShortcut): string {

		if($cActionShortcut->empty()) {
			return '';
		}

		$h = '<div class="planning-task-create-shortcuts">';

			foreach($cActionShortcut as $eAction) {

				$result = self::getActionResult($eAction, $eAction['eCategory']);

				$h .= '<a class="btn btn-sm btn-outline-secondary" data-planning-task-create-shortcut="'.encode(json_encode($result)).'">'.\work\ActionUi::getCircle($eAction).encode($eAction['name']).'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les résultats de `taskDay:queryAction`, rangés comme l'était le menu : chaque catégorie en intitulé, puis
	 * ses actions. Sans rien de tapé, les actions les plus fréquentes passent avant, sous « Fréquentes »
	 * (retour 257), et restent aussi à leur place dans leur catégorie.
	 *
	 * @param \Collection $cCategory Les catégories et leurs actions (`TaskDayLib::getCategoriesForCreate()`).
	 * @param \Collection $cActionFrequent Les actions fréquentes et leur catégorie (`TaskDayLib::getActionsFrequent()`).
	 */
	public static function getActionResults(\Collection $cCategory, \Collection $cActionFrequent): array {

		$results = [];

		if($cActionFrequent->notEmpty()) {

			// La forme de `TaskDayUi::getAutocompleteTitle()`, qui est propre à « Espèce ou série » : les deux
			// champs ne partagent que le type `title` du cadriciel.
			$results[] = [
				'type' => 'title',
				'itemHtml' => '<div>'.s("Fréquentes").'</div>',
				'itemText' => s("Fréquentes"),
			];

			foreach($cActionFrequent as $eAction) {
				$results[] = self::getActionResult($eAction, $eAction['eCategory']);
			}

		}

		foreach($cCategory as $eCategory) {

			// Pas `TaskDayUi::getAutocompleteTitle()`, qui pose un libellé de `s()` tel quel : le nom d'une
			// catégorie est saisi par la structure et s'échappe.
			$results[] = [
				'type' => 'title',
				'itemHtml' => '<div>'.encode($eCategory['name']).'</div>',
				'itemText' => $eCategory['name'],
			];

			foreach($eCategory['cAction'] as $eAction) {
				$results[] = self::getActionResult($eAction, $eCategory);
			}

		}

		return $results;

	}

	/**
	 * Une action de la liste, qui porte la catégorie sous laquelle elle est choisie, dit si elle est la récolte et
	 * si cette catégorie est celle des cultures, et porte son `fqn`, comme `data-fqn` dans l'ancien formulaire, que
	 * `PlanningTaskAction.select()` lit, et sa couleur quand elle en a une choisie, que prend le point du champ.
	 */
	private static function getActionResult(\work\Action $eAction, \work\Category $eCategory): array {

		return [
			'value' => $eAction['id'],
			'itemHtml' => \work\ActionUi::getCircle($eAction).encode($eAction['name']),
			'itemText' => $eAction['name'],
			'category' => $eCategory['id'],
			'harvest' => ($eAction['fqn'] === ACTION_RECOLTE),
			'fqn' => $eAction['fqn'],
			'culture' => ($eCategory['fqn'] === CATEGORIE_CULTURE),
			'color' => $eAction->hasColor() ? $eAction['color'] : NULL,
		];

	}

}
?>
