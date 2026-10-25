<?php
namespace planning;

/**
 * La saisie d'une récolte depuis l'écran des récoltes : le tiroir qui s'ouvre sous une ligne, et le
 * panneau « Nouvelle récolte » pour une culture que la liste ne montre pas. Les deux postent sur
 * `doHarvest`, dont la réponse remplace les lignes touchées.
 *
 * Une saisie vise un couple variété · calibre. Ouverte depuis une part (`$part`,
 * `HarvestWeekLib::getPart()`), elle porte son couple en champs cachés et le rappelle au-dessus de la saisie.
 * Ouverte depuis la culture, elle le fait choisir quand la culture en propose un (`getChoice()`) :
 * « Toutes » les variétés et « Non spécifié » pour le calibre par défaut, la même saisie qu'avant pour qui ne
 * ventile pas.
 *
 * Toute quantité se saisit dans un champ `float` (`FormUi::number()`, par le describer de
 * `\series\Task`) : le navigateur y lit la virgule française et poste un point. Le temps passe par le
 * champ par pas commun au planning, ouvert dès l'ouverture sous chaque personne (`getTimeWindow()`).
 */
class HarvestEntryUi {

	private const SHEET_PROPERTIES = [
		'add' => 'harvestMore',
		'replace' => 'harvestDay',
	];

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::css('series', 'task.css');
		\Asset::js('planning', 'planning.js');

	}

	/**
	 * Le tiroir d'une ligne, sur un jour : « + » pour ajouter, « Remplacer par » pour remplacer la quantité du
	 * jour dans l'unité de la saisie, que le champ porte déjà et que son unité nomme (R-Retours-0810, 223). Il
	 * se pose dans le rang de la ligne, sous la part
	 * qui l'a ouvert ou en dernier ; Entrée enregistre et le curseur passe à la ligne suivante
	 * (`focusNext`). Le tiroir n'agit que sur le jour ouvert à l'écran, qu'il renvoie (`open`) pour que
	 * la réponse redessine la ligne dans la même colonne.
	 *
	 * Il se lit en trois rangées (R-Retours-0810, 218) : les champs titrés de la récolte, la quantité et le
	 * poids de sa caisse, la variété, le calibre (retour 202, piste B) ; puis le temps, une ligne par personne
	 * (`getDrawerTime()`) ; puis les boutons, alignés à gauche, au pied du tiroir comme sous tout formulaire du
	 * produit. Le jour titre la quantité (retour 115, arbitrage V-Planning du 2026-10-01). Une pointe sous la
	 * colonne du jour rattache le tiroir à ce jour (retour 114), et le formulaire s'y centre à partir de 768
	 * (retour 165).
	 *
	 * Le tiroir ajoute ou remplace, comme la feuille du tactile : une récolte se retire en remplaçant, ou par
	 * « -12 » dans la case (21/10, « ne garde que Ajouter et Remplacer »). Une croix dans son angle le
	 * referme, et en remplacement « Supprimer » vide le jour (`getDrawerDelete()`, retours 300 et 301), quand le
	 * tiroir y a une quantité ou un temps à lui (`$deletable`, `HarvestWeekLib::hasEntry()`).
	 *
	 * La case d'un jour récolté dans une autre unité que celle de la semaine désigne son intervention
	 * (`$eTaskChosen`), que le tiroir poste : son unité est déjà celle de `harvestUnitWeek`. Pour la culture
	 * entière, les autres unités se choisissent dans la liste du champ (`$tasksByUnit`, lot GN). Quand elle le
	 * permet (`$reopen`), une autre unité choisie en remplacement rouvre le tiroir sur elle (lot GO), qui porte alors
	 * l'unité choisie sans intervention (`$unitOther`) ; ailleurs, elles s'éteignent en remplacement, où le
	 * champ montre la quantité de l'unité de la case (`PlanningHarvestCrate.allow()`). Chaque personne du
	 * temps (`$cUserTime`, `HarvestWeekLib::getTimeUsers()`) porte le temps qu'elle y a passé chaque jour, que
	 * remplace sa durée en remplacement. Le temps du jour que le tiroir ne remplace pas
	 * (`$timeKept`, `HarvestWeekLib::getTimeKept()`) se lit sous les personnes.
	 *
	 * « Ajouter à un stock » suit la quantité et le couple dans la première rangée (`getStock()`, DG-4), sur la
	 * récolte que la saisie écrira (`$eTaskStock`), et « Supprimer » emporte le choix qu'il porte (`PlanningHarvestStock.copy()`).
	 */
	public function getDrawer(\structure\Structure $eStructure, string $week, \series\Cultivation $eCultivation, string $date, array $days, string $mode, array $part, array $units, \Collection $cUserTime, \series\Task $eTaskChosen = new \series\Task(), float $timeKept = 0.0, bool $deletable = FALSE, \series\Task $eTaskStock = new \series\Task(), array $tasksByUnit = [], ?string $unitOther = NULL, bool $reopen = FALSE, ?string $unitFirst = NULL, bool $crate = FALSE, array $others = []): string {

		if($part === []) {
			$unit = $eCultivation['harvestUnitWeek'];
			$current = $eCultivation['harvestedDays'][$date][$unit] ?? 0.0;
		} else {
			$unit = $part['unit'];
			$current = $part['days'][$date][$unit] ?? 0.0;
		}

		$form = new \util\FormUi();

		$h = '<div class="planning-harvest-drawer" onrender="PlanningHarvestDrawer.start(this)">';

			$h .= '<div class="planning-harvest-drawer-day" style="grid-column: '.HarvestWeekUi::getDayColumn($days[$date]).'"></div>';

			$h .= $form->openAjax('/planning/harvestWeek:doHarvest', ['class' => 'util-block planning-harvest-drawer-form']);

				$h .= '<a class="util-sheet-close planning-harvest-drawer-close" data-planning-harvest-close>'.\Asset::icon('x-lg').'</a>';

				$h .= $form->hidden('id', $eStructure);
				$h .= $form->hidden('week', $week);
				$h .= $form->hidden('cultivation', $eCultivation);
				$h .= $form->hidden('date', $date);
				$h .= $form->hidden('mode', $mode);
				$h .= $form->hidden('focusNext', 1);
				// Le jour ouvert de l'écran, et non la date saisie : une semaine passée lue sans jour ouvert propose son
				// dimanche (`proposed`), que la réponse ne doit pas ouvrir sur la seule ligne redessinée.
				$h .= $form->hidden('open', array_find_key($days, fn($day) => $day['open']) ?? '');

				$h .= $this->getTaskFields($form, $eTaskChosen, $tasksByUnit);

				if($part !== []) {
					$h .= $form->hiddens(HarvestWeekUi::getCoupleArguments($part));
					$h .= '<div>'.HarvestWeekUi::getCoupleName($part['eVariety'], $part['eSize']).'</div>';
				}

				$h .= '<div class="planning-harvest-entry-line">';

					$h .= '<div class="planning-harvest-entry-titled">';

						$h .= '<div class="util-label">'.$this->getDrawerDayLabel($mode, $date, $days[$date]).'</div>';

						$h .= '<div class="planning-harvest-entry-amount">';

							if($mode === 'replace') {
								$h .= '<span>'.s("Remplacer par").'</span>';
							}

							$h .= $this->getQuantityField($form, $eCultivation['plant'], $unit, $mode, ($mode === 'replace') ? $current : NULL, $tasksByUnit, focus: TRUE, eTaskUnit: $eTaskChosen, unitOther: $unitOther, urlReopen: $reopen ? $this->getReopenUrl($eStructure, $week, $eCultivation, $date, FALSE) : NULL, unitFirst: $unitFirst, crate: $crate);
							$h .= $this->getCrate($form, $eCultivation['plant'], $unit, $mode, $crate);

						$h .= '</div>';

						$h .= $this->getOthers($form, $others, TRUE, $this->hasUnitChoice($eCultivation['plant'], $unit, $tasksByUnit));

					$h .= '</div>';

					if($part === [] and $mode === 'add') {
						$h .= $this->getChoice($form, $eCultivation, $date, $units);
					}

					if($eTaskStock->notEmpty()) {
						$h .= $this->getStock($form, 'planning-harvest-entry-stock', $eTaskStock, titled: TRUE);
					}

				$h .= '</div>';

				if($eStructure->hasTime()) {

					$h .= '<div class="planning-harvest-entry-line">';

						$h .= $this->getDrawerTime($form, $date, $days, $cUserTime, $mode, $timeKept);

						// Un refus du jour du temps (`timeDate`), ou d'une personne qui n'a pas de ligne (`time`), se lit
						// sous la rangée entière, qui garde ainsi son axe, et ne colore aucun des champs de la rangée.
						$h .= '<div class="planning-harvest-entry-refusal" data-wrapper="timeDate time"></div>';

					$h .= '</div>';

				}

				$h .= '<div class="planning-harvest-entry-go">';
					$h .= $form->submit(match($mode) {
						'add' => s("Ajouter"),
						'replace' => s("Remplacer"),
					});
					if($deletable) {
						$h .= $this->getDrawerDelete($eStructure, $week, $eCultivation, $date, $part, $eTaskChosen);
					}
				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	/**
	 * « Supprimer » du tiroir ouvert en remplacement : il poste ce qui désigne la case, le jour, le couple
	 * d'une part et l'intervention que la case désigne, et rien de ce que le formulaire porte
	 * (`harvestWeek:doHarvestDelete`).
	 */
	protected function getDrawerDelete(\structure\Structure $eStructure, string $week, \series\Cultivation $eCultivation, string $date, array $part, \series\Task $eTaskChosen): string {

		$attributes = [
			'data-ajax' => '/planning/harvestWeek:doHarvestDelete',
			'post-id' => $eStructure['id'],
			'post-week' => $week,
			'post-cultivation' => $eCultivation['id'],
			'post-date' => $date,
			'post-open' => $date,
			'data-confirm' => $eStructure->hasTime() ? s("Supprimer la quantité récoltée ce jour et le temps saisi ?") : s("Supprimer la quantité récoltée ce jour ?"),
			'data-planning-harvest-stock-copy' => 'true',
		];

		if($eTaskChosen->notEmpty()) {
			$attributes['post-task'] = $eTaskChosen['id'];
		}

		if($part !== []) {

			// `Element.prototype.post()` (`lime.js`) recompose le nom posté depuis l'attribut, tirets en majuscules :
			// `harvestSize` s'écrit `post-harvest-size`.
			foreach(HarvestWeekUi::getCoupleArguments($part) as $name => $value) {
				$attributes['post-'.strtolower(preg_replace('/[A-Z]/', '-$0', $name))] = $value;
			}

		}

		return '<a class="btn btn-outline-danger planning-harvest-entry-delete" '.attrs($attributes).'>'.s("Supprimer").'</a>';

	}

	/**
	 * Le temps du tiroir (R-Retours-0810, 224) : une `util-table`, une ligne par personne, son nom puis ses pas, et « Ajouter une
	 * personne » pour dernière ligne. Son titre ouvre les jours de la semaine où le temps peut s'écrire, sans ceux à venir (retour
	 * 202) : le jour choisi réécrit le titre (`PlanningHarvestTime.choose()`) et part dans `timeDate`, la récolte
	 * restant sur le jour du tiroir. Le refus d'une durée se lit sous la ligne de sa personne. En remplacement,
	 * chaque durée part du temps de sa personne ce jour-là.
	 *
	 * La case d'où s'ouvre le tiroir dit le temps de toutes les interventions récoltées ce jour-là, et les
	 * personnes ne portent que celui de l'intervention qu'il écrit : le reste (`$timeKept`) se lit sous
	 * elles (`getTimeKept()`), avec son jour, que le choix du jour du temps ne change pas.
	 */
	protected function getDrawerTime(\util\FormUi $form, string $date, array $days, \Collection $cUserTime, string $mode, float $timeKept): string {

		$replace = ($mode === 'replace');

		$h = '<div class="planning-harvest-entry-titled planning-harvest-entry-time">';

			$h .= '<a class="util-label dropdown-toggle" data-dropdown="bottom-start">'.$this->getTimeDayLabel($date, $days[$date]).'</a>';

			$h .= '<div class="dropdown-list">';

				foreach($days as $dayDate => $day) {

					if($day['future']) {
						continue;
					}

					$h .= '<a class="dropdown-item'.($dayDate === $date ? ' selected' : '').'" data-planning-harvest-time-date="'.$dayDate.'" data-planning-harvest-time-label="'.encode($this->getTimeDayLabel($dayDate, $day)).'">'.$this->getDayName($dayDate, $day).'</a>';

				}

			$h .= '</div>';

			$h .= $form->hidden('timeDate', $date);

			$h .= $this->getPeople($form, $cUserTime, $replace, $date);

			$h .= $this->getTimeKept($date, $days[$date], $timeKept);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le temps que le jour compte et que la saisie ne remplace pas (`HarvestWeekLib::getTimeKept()`), dit sous
	 * les durées du tiroir et de la feuille, et seulement quand il y en a. La phrase ne dit pas d'où il vient :
	 * une autre intervention de récolte, ou sur la même une personne que la saisie ne montre pas.
	 */
	protected function getTimeKept(string $date, array $day, float $timeKept): string {

		if($timeKept > 0.0) {
			return '<div class="util-annotation">'.s("{day} compte aussi {time} de récolte.", ['day' => $this->getDayName($date, $day), 'time' => DurationUi::formatDuration($timeKept)]).'</div>';
		} else {
			return '';
		}

	}

	/**
	 * La table des personnes et de leur temps, au tiroir, dans « Nouvelle intervention » faite (retour 245), dans
	 * « Récolter » (HM) et sur la feuille du tactile (HL) : une ligne par personne, sa vignette et son nom puis ses
	 * pas à sa droite, la vignette seule quand le nom ne tient pas à côté d'eux (V28b), le refus de sa durée sous
	 * eux, et « Ajouter une personne » pour dernière ligne.
	 * `$replace` et `$date` ne valent qu'au tiroir et sur la feuille, qui remplacent le temps d'un jour. La
	 * feuille change de geste sans retour au serveur : chaque personne y porte donc toujours son temps passé
	 * (`$withSpent`).
	 */
	public function getPeople(\util\FormUi $form, \Collection $cUserTime, bool $replace = FALSE, ?string $date = NULL, bool $withSpent = FALSE): string {

		$h = '<div class="util-table planning-harvest-entry-people">';

			foreach($cUserTime as $eUser) {

				$person = '<div class="util-table-row planning-harvest-entry-time-row">';
					$person .= '<div class="planning-harvest-entry-time-line">';
						$person .= new TimeWeekUi()->getWho($eUser, '1.25rem');
						$person .= $this->getTimeWindow($form, $eUser, wrapped: FALSE, spent: ($replace or $withSpent) ? $eUser['spent'] : NULL, date: $date, replace: $replace, shown: ($eUser['timeLine'] === 'shown'));
					$person .= '</div>';
				$person .= '</div>';

				$person .= '<div class="planning-harvest-entry-refusal" data-wrapper="time['.$eUser['id'].']"></div>';

				$h .= $this->getTimePerson('planning-harvest-entry-person', $eUser, $person);

			}

			$h .= $this->getTimeAdd($cUserTime);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une personne du temps d'une saisie, que `PlanningHarvestTime.team()` montre ou cache : la personne
	 * connectée toujours, une autre quand « Ajouter une personne » l'a montrée, ou quand la saisie remplace et
	 * qu'elle a du temps sur l'intervention (`data-planning-harvest-worked`). Le geste de la feuille change
	 * sans retour au serveur : chacune est donc rendue, montrée ou non.
	 */
	protected function getTimePerson(string $class, \user\User $eUser, string $content): string {

		$h = '<div class="'.$class.($eUser['timeLine'] === 'hidden' ? ' hide' : '').'" data-planning-harvest-person="'.$eUser['id'].'"'.($eUser['isOnline'] ? ' data-planning-harvest-online' : '').($eUser['worked'] ? ' data-planning-harvest-worked' : '').'>';
			$h .= $content;
		$h .= '</div>';

		return $h;

	}

	/**
	 * « Ajouter une personne », comme sur la page d'une intervention (`TaskDetailUi::getGridAdd()`) : la liste
	 * des autres personnes, celles qui sont montrées y restant cachées, pour qu'un changement de geste n'ait
	 * qu'à les montrer ou les cacher (`PlanningHarvestTime.team()`). Le lien se cache avec la dernière.
	 */
	protected function getTimeAdd(\Collection $cUserTime): string {

		$list = '';
		$left = FALSE;

		foreach($cUserTime as $eUser) {

			if($eUser['isOnline']) {
				continue;
			}

			$shown = ($eUser['timeLine'] === 'shown');
			$left = ($left or $shown === FALSE);

			$list .= '<a class="dropdown-item'.($shown ? ' hide' : '').'" data-planning-harvest-add="'.$eUser['id'].'">'.\user\UserUi::getVignette($eUser, '1.5rem').'  '.$eUser->getName().'</a>';

		}

		if($list === '') {
			return '';
		}

		$h = '<a class="util-table-row util-table-add planning-harvest-time-add'.($left ? '' : ' hide').'" data-dropdown="bottom-start">';
			$h .= '<div><span class="util-table-add-icon">'.\Asset::icon('person-plus').'</span><span>'.s("Ajouter une personne").'</span></div>';
		$h .= '</a>';
		$h .= '<div class="dropdown-list planning-harvest-time-add-list">'.$list.'</div>';

		return $h;

	}

	/**
	 * La feuille du tactile (`util-sheet`), qu'ouvre « + » ou une quantité enregistrée quand l'écran se
	 * touche : le formulaire Récolter habituel, en groupes de formulaire (lot EL, piste 2 retenue le
	 * 15/10), saisi au clavier du téléphone. Dans l'ordre : le geste, l'espèce, la variété et le calibre, la
	 * quantité récoltée, le stock, puis le temps. Le bouton dit ce qui sera écrit (« Ajouter 12,5 kg en 0h30 »).
	 *
	 * Quand ce que vise la saisie porte déjà une récolte ce jour-là, le geste se choisit en tête : ajouter
	 * (`harvestMore`) ou remplacer (`harvestDay`), et rien d'autre (21/10, « ne garde que Ajouter et
	 * Remplacer »). Sinon, le geste est celui de l'ouverture. La quantité n'a qu'un champ, que
	 * `PlanningHarvestSheet.mode()` renomme d'après le geste : ses caisses, l'unité du champ et le refus de ses deux
	 * noms restent ceux d'un seul champ. Le choix du couple ne vaut qu'en ajout : un `fieldset` qui ne vaut pas pour
	 * le geste se désactive (`getSheetFor()`).
	 *
	 * L'espèce ne se choisit que pour une série qui en porte plusieurs dans la liste de la semaine
	 * (`$cCultivationSeries`) : en changer rouvre la feuille sur la ligne de l'autre culture, en ajout, au même jour,
	 * et c'est là que la récolte s'écrit. Ouverte depuis une carte de la Semaine, la feuille ne la propose pas : la
	 * carte n'écrit que dans sa culture (`planningHarvestWeekOrigin()`).
	 *
	 * Comme le tiroir, elle poste l'intervention que désigne la case qui l'a ouverte (`$eTaskChosen`), et le
	 * temps de chacun part, en remplacement, de celui qu'il y a passé (`$cUserTime`). Les autres unités d'une
	 * récolte (`$tasksByUnit`) se choisissent dans la liste du champ, comme dans « Nouvelle récolte » (retour 319),
	 * un couple choisi compris (HN-B2c), et hors de l'ajout, sauf en Semaine, où le remplacement la rouvre sur
	 * l'unité choisie, comme le tiroir (`$reopen`, `$unitOther`). Depuis une case qui désigne son intervention,
	 * l'unité de la case porte cette intervention, que le champ caché `task` poste, et une autre unité la remplace
	 * (`PlanningHarvestCrate.task()`). Un jour récolté en plusieurs unités ajoute, en remplacement, une rangée par
	 * autre unité sous la quantité (`$others`, `getOthers()`).
	 *
	 * Le temps se saisit déroulé, comme au tiroir (21/10, « déroule par défaut le temps de travail ») : une
	 * ligne par personne montrée, ses pas ouverts (`getPeople()`), et « Ajouter une personne » sous elles.
	 *
	 * En remplacement, le temps du jour que la saisie ne remplace pas (`$timeKept`) se lit sous les personnes,
	 * comme au tiroir. Le bouton ne dit alors que la quantité : elle est celle de tout le jour, et « en 0h21 » la
	 * lierait au temps d'une partie seulement de ce jour.
	 */
	public function getSheet(\structure\Structure $eStructure, string $week, \series\Cultivation $eCultivation, string $date, array $days, string $mode, array $part, array $units, \Collection $cUserTime, array $taskWeek = [], \series\Task $eTaskChosen = new \series\Task(), float $timeKept = 0.0, array $tasksByUnit = [], \series\Task $eTaskStock = new \series\Task(), \Collection $cCultivationSeries = new \Collection(), ?string $unitOther = NULL, bool $reopen = FALSE, ?string $unitFirst = NULL, bool $crate = FALSE, array $others = []): string {

		// Le remplacement agit sur ce que vise la saisie, la part ou la culture.
		if($part === []) {
			$unit = $eCultivation['harvestUnitWeek'];
			$currentDay = $eCultivation['harvestedDays'][$date][$unit] ?? 0.0;
		} else {
			$unit = $part['unit'];
			$currentDay = $part['days'][$date][$unit] ?? 0.0;
		}

		$quantities = $eCultivation['harvestedDays'][$date] ?? [];

		$day = $days[$date];
		$dayName = $this->getDayName($date, $day);

		$form = new \util\FormUi();

		// Sans suivi du temps, la feuille n'a pas de temps de travail : tout temps posté serait refusé
		// (`Timesheet::time.untracked`).
		$hasTime = $eStructure->hasTime();

		$unitsShort = [];

		foreach(\series\Task::model()->getPropertyEnum('harvestUnit') as $unitShort) {
			$unitsShort[$unitShort] = \product\UnitUi::getSingular($unitShort, short: TRUE);
		}

		$h = '<div class="util-sheet" id="planning-harvest-sheet" data-mode="'.$mode.'" data-unit="'.$unit.'" data-units-short="'.encode(json_encode($unitsShort)).'" data-current-day="'.$currentDay.'" onrender="PlanningHarvestSheet.start(this)">';

			$h .= '<div class="util-sheet-scrim" data-planning-sheet-close></div>';

			$h .= $form->openAjax(self::getEntryUrl($taskWeek), ['class' => 'util-sheet-panel', 'tabindex' => '-1', 'autocomplete' => 'off']);

				$h .= $form->hidden('id', $eStructure);
				$h .= $form->hidden('week', $week);
				$h .= $form->hidden('cultivation', $eCultivation);
				$h .= $form->hidden('date', $date);
				$h .= $form->hidden('sheet', 1);
				// Le jour ouvert de l'écran, comme le tiroir (`getDrawer()`).
				$h .= $form->hidden('open', array_find_key($days, fn($day) => $day['open']) ?? '');
				$h .= $form->hiddens($taskWeek);

				$h .= $this->getTaskFields($form, $eTaskChosen, $tasksByUnit);

				$modeChoice = ($currentDay > 0.0);

				if($modeChoice === FALSE) {
					$h .= $form->hidden('mode', $mode);
				}

				if($part !== []) {
					$h .= $form->hiddens(HarvestWeekUi::getCoupleArguments($part));
				}

				$h .= '<div class="util-sheet-grab"></div>';

				$h .= '<div class="util-sheet-head">';
					$h .= '<div class="planning-harvest-sheet-title">';
						$h .= \plant\PlantUi::getVignette($eCultivation['plant'], '2.5rem');
						$h .= '<div>';
							$h .= '<h4>'.new HarvestWeekUi()->getName($eCultivation, places: FALSE).'</h4>';
							$h .= '<div class="util-annotation">';
								if($part !== []) {
									$h .= HarvestWeekUi::getCoupleName($part['eVariety'], $part['eSize']);
								} else if($quantities === []) {
									$h .= s("{day}, rien encore", ['day' => $dayName]);
								} else {
									$h .= s("{day}, déjà {value}", ['day' => $dayName, 'value' => implode(', ', array_map(fn($unit, $quantity) => \product\UnitUi::getValue($quantity, $unit, short: TRUE), array_keys($quantities), $quantities))]);
								}
							$h .= '</div>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<a class="util-sheet-close" data-planning-sheet-close>'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';

				$h .= '<div class="util-sheet-body planning-harvest-sheet-body">';

					if($modeChoice) {
						// Sans libellé, le geste sort de la grille du formulaire en colonnes : ses boutons y tiendraient
						// dans la seule colonne des champs, sur deux rangées.
						$h .= $form->group(content: '<div class="util-filter-options-field">'.$form->radios('mode', [
							'add' => s("Ajouter"),
							'replace' => s("Remplacer"),
						], $mode, ['required' => TRUE, 'data-planning-sheet-mode' => 'on']).'</div>', nested: TRUE);
					}

					if($cCultivationSeries->count() > 1) {
						$h .= $form->group(s("Espèce"), $form->select(NULL, $this->getSpecies($eStructure, $week, $date, $cCultivationSeries), $eCultivation['id'], ['required' => TRUE, 'data-planning-sheet-species' => 'on']));
					}

					// Le couple ne se choisit que depuis la culture : ouverte depuis une part, la feuille le porte déjà.
					if($part === []) {
						$h .= $this->getSheetFor('fieldset', 'add', $mode, $this->getChoiceGroups($form, $eCultivation, $units));
					}

					// Le groupe porte les refus des deux noms du champ, que le geste choisit.
					$h .= $form->group(
						s("Quantité récoltée"),
						'<div class="planning-harvest-entry-amount">'.
							$this->getQuantityField($form, $eCultivation['plant'], $unit, $mode, ($mode === 'replace') ? $currentDay : NULL, $tasksByUnit, wrapper: FALSE, sign: FALSE, eTaskUnit: $eTaskChosen, unitOther: $unitOther, urlReopen: $reopen ? $this->getReopenUrl($eStructure, $week, $eCultivation, $date, TRUE) : NULL, unitFirst: $unitFirst, crate: $crate).
							$this->getCrate($form, $eCultivation['plant'], $unit, $mode, $crate).
						'</div>'.
						(($others === []) ? '' : $this->getSheetFor('fieldset', 'replace', $mode, $this->getOthers($form, $others, FALSE, $this->hasUnitChoice($eCultivation['plant'], $unit, $tasksByUnit)))),
						['data-wrapper' => implode(' ', self::SHEET_PROPERTIES).' harvestDates harvestUnit']
					);

					if($eTaskStock->notEmpty()) {
						$h .= $this->getStock($form, 'planning-harvest-sheet-stock', $eTaskStock);
					}

					if($hasTime) {

						$lines = '<div class="planning-harvest-sheet-lines">';

							$lines .= '<div data-planning-sheet-pane="time">';
								$lines .= $this->getPeople($form, $cUserTime, ($mode === 'replace'), $date, withSpent: TRUE);
							$lines .= '</div>';

							if($timeKept > 0.0) {
								$lines .= $this->getSheetFor('div', 'replace', $mode, $this->getTimeKept($date, $day, $timeKept));
							}

							// Le refus d'une durée se lit sous la personne qu'il vise (`getPeople()`), et celui qui n'en vise
							// aucune sous les personnes. Celui d'un retrait qui porte du temps s'y lit dès la frappe
							// (`PlanningHarvestSheet.update()`), le bouton éteint ne laissant aucun envoi le faire venir.
							$lines .= '<div class="planning-harvest-sheet-refusal" data-wrapper="time" data-planning-sheet-remove-time="'.encode(\series\AlertUi::getError('Task::harvestMore.time')).'"></div>';

						$lines .= '</div>';

						// Le libellé nomme le groupe et pas un champ : sans `for` ni `data-wrapper`, il ne viserait que la
						// durée de la dernière personne construite, dont il afficherait le refus une seconde fois
						// (`FormUi::group()`). Le refus a sa place sous les personnes.
						$h .= $form->group(s("Temps de travail"), $lines, ['for' => FALSE, 'data-wrapper' => '']);

					}

				$h .= '</div>';

				// Un ajout sans quantité reste possible, comme au tiroir : le temps seul se lit alors
				// « Ajouter 0h30 », et « en » ne vient qu'après une quantité (`PlanningHarvestSheet.update()`).
				$amount = '<span data-planning-sheet-amount></span>';
				$duration = '<span data-planning-sheet-duration></span>';
				$time = $hasTime ?
					'<span class="hide" data-planning-sheet-time><span data-planning-sheet-time-after>'.s("en {value}", $duration).'</span><span class="hide" data-planning-sheet-time-alone>'.$duration.'</span></span>' :
					'';

				// Sans quantité, « Remplacer » ne garde que son verbe : « Remplacer par » attendrait ce qui n'est pas tapé,
				// et le temps seul s'y lirait comme la nouvelle quantité.
				$replace = '<span data-planning-sheet-typed>'.(($timeKept > 0.0) ? s("Remplacer par {amount}", ['amount' => $amount]) : s("Remplacer par {amount} {time}", ['amount' => $amount, 'time' => $time])).'</span>'.
					'<span class="hide" data-planning-sheet-untyped>'.s("Remplacer").'</span>';

				// Un ajout négatif retire du jour, comme « -12 » dans la case (HN-B4a) : le bouton le dit avec le verbe de
				// la case et la quantité sans son signe (`PlanningHarvestSheet.update()`).
				$add = '<span data-planning-sheet-sign="plus">'.s("Ajouter {amount} {time}", ['amount' => $amount, 'time' => $time]).'</span>'.
					'<span class="hide" data-planning-sheet-sign="minus">'.s("Retirer {amount} {time}", ['amount' => $amount, 'time' => $time]).'</span>';

				$h .= '<div class="util-sheet-foot">';
					$h .= $form->submit(
						$this->getSheetFor('span', 'add', $mode, $add).
						$this->getSheetFor('span', 'replace', $mode, $replace),
						['class' => 'btn btn-primary', 'disabled' => TRUE, 'data-planning-sheet-submit' => 'on']
					);
				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les espèces de la série, une par culture que la semaine liste : chacune rouvre la feuille sur sa ligne, en
	 * ajout, au jour de la feuille (`PlanningHarvestSheet.species()`).
	 */
	protected function getSpecies(\structure\Structure $eStructure, string $week, string $date, \Collection $cCultivationSeries): array {

		$species = [];

		foreach($cCultivationSeries as $eCultivationSeries) {

			$species[] = [
				'value' => $eCultivationSeries['id'],
				'label' => $eCultivationSeries['plant']['name'],
				'attributes' => [
					'data-url' => HarvestWeekUi::urlOpen($eStructure, $week, $eCultivationSeries, $date, 'add'),
				],
			];

		}

		return $species;

	}

	/**
	 * Un morceau de la feuille qui ne vaut que pour certains gestes (`$modes`, séparés par une espace) : caché
	 * sous les autres, et un `fieldset` y est en plus désactivé, pour que ses champs ne partent pas.
	 * `PlanningHarvestSheet.mode()` refait la même bascule quand le geste change.
	 */
	protected function getSheetFor(string $tag, string $modes, string $mode, string $content): string {

		$on = in_array($mode, explode(' ', $modes), TRUE);

		$h = '<'.$tag.' data-planning-sheet-for="'.$modes.'"';

		if($on === FALSE) {
			$h .= ' class="hide"';
			if($tag === 'fieldset') {
				$h .= ' disabled';
			}
		}

		$h .= '>'.$content.'</'.$tag.'>';

		return $h;

	}

	/**
	 * Une rangée par autre unité récoltée ce jour-là (`HarvestWeekLib::getOthers()`, HN-B2c), sous la quantité de
	 * tête, qui garde l'unité de la ligne ou de la part, ses caisses, son temps et son stock : chaque rangée
	 * remplace sa quantité dans son unité, et rien d'autre. Le tiroir répète « Remplacer par » devant chacune,
	 * la feuille le dit par son geste. Leur quantité prend la largeur de celle de la tête (`$unitChoice`), pour
	 * tomber dessous quelle que soit l'unité écrite à sa droite.
	 */
	protected function getOthers(\util\FormUi $form, array $others, bool $label, bool $unitChoice): string {

		$h = '';

		foreach($others as $unit => $quantity) {

			$h .= '<div class="planning-harvest-entry-amount" data-planning-harvest-row="'.$unit.'">';

				if($label) {
					$h .= '<span>'.s("Remplacer par").'</span>';
				}

				$h .= '<div class="planning-harvest-entry-quantity" data-wrapper="harvestDay['.$unit.']">'.$form->dynamicField(new \series\Task(['harvestDay' => $quantity]), 'harvestDay', function(\PropertyDescriber $d) use($form, $unit, $unitChoice) {
					$d->name = 'harvestOther['.$unit.']';
					$d->attributes['inputmode'] = 'decimal';
					$d->append = fn() => $form->addon($this->getUnitField($unit));
					$d->inputGroup = ['class' => 'planning-harvest-entry-number'.($unitChoice ? ' planning-harvest-entry-number-unit' : '')];
				}).'</div>';

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Le jour d'une saisie, « Aujourd'hui » ou « Lundi 5 », par la même construction que le panneau d'un jour
	 * de Temps de travail (`TimeDayUi::getPanelDay()`, qui écrit « 1er »).
	 */
	protected function getDayName(string $date, array $day): string {

		return $day['today'] ? s("Aujourd'hui") : TimeDayUi::getPanelDay($date);

	}

	/**
	 * Le libellé de la quantité du tiroir, qui dit ce que la saisie fera de celle du jour (lot CB, retour 299) :
	 * sur un jour déjà récolté, le bouton du bas était seul à dire si elle s'ajoute ou la remplace. La feuille
	 * du téléphone le dit par son choix du geste.
	 */
	protected function getDrawerDayLabel(string $mode, string $date, array $day): string {

		$dayName = mb_strtolower($this->getDayName($date, $day));

		return match($mode) {
			'add' => s("Ajouter {day}", ['day' => $dayName]),
			'replace' => s("Remplacer {day}", ['day' => $dayName]),
		};

	}

	/**
	 * Le libellé du temps d'une récolte, qui dit à quel jour il s'écrit (R-Retours-0610, point 7) : le tiroir
	 * et « Nouvelle récolte » passent tous deux par lui. La feuille dit le jour dans sa tête.
	 */
	public function getTimeDayLabel(string $date, array $day): string {

		return s("Temps passé {day}", ['day' => mb_strtolower($this->getDayName($date, $day))]);

	}

	/**
	 * Le panneau « Nouvelle récolte », borné à la semaine affichée : le jour se choisit du lundi
	 * (`$dateMin`) au jour proposé. La culture se cherche d'abord ; une fois choisie, le panneau se
	 * recharge avec elle (`planning.js`, `autocompleteSelect`) et propose le jour, la quantité dans
	 * l'unité de sa saisie et le temps. Rien ne dit si la récolte est finie (retour 214) : une quantité
	 * positive termine l'intervention à faire, comme partout (`\series\TaskLib::writeHarvestForWeek()`).
	 *
	 * Le panneau renvoie le jour ouvert à l'écran (`$open`), quel que soit le jour saisi, pour que la
	 * réponse redessine les lignes dans la même colonne.
	 *
	 * Le temps dit le jour où il s'écrit, comme au tiroir : le libellé de chaque jour qu'on
	 * peut choisir (`$days`) est rendu ici, et `PlanningHarvestTime.day()` le reprend quand le jour change.
	 *
	 * Ouvert depuis une carte de la Semaine (retour 203), le panneau réémet ce qu'elle attend (`$taskWeek`)
	 * et poste sur elle (`getEntryUrl()`). La série est celle de la carte et ne se change pas : une autre
	 * culture ne répondrait par aucune carte. Le jour proposé est celui de la carte, que `$dateMax` borne.
	 * Ouvert depuis la fiche d'une série du plan d'assolement (`$origin`), il poste sur la fiche, qui se
	 * redessine.
	 *
	 * Ailleurs, les autres unités d'une récolte (`$tasksByUnit`, `HarvestWeekLib::getEntryTasks()`) se rangent
	 * dans le choix de l'unité : celle que la semaine porte déjà y désigne son intervention, que le champ caché
	 * `task` poste (R-Retours-0810, T, piste A), et une autre se poste elle-même (`harvestUnit`, retour 319).
	 *
	 * « Ajouter à un stock » suit le choix de la variété et du calibre (`getStock()`, DG-4), qui le font relire.
	 */
	public function create(\structure\Structure $eStructure, string $week, \series\Cultivation $eCultivation, ?string $unit, array $units, string $date, string $dateMin, string $dateMax, array $days, \user\User $eUserTime, ?string $open = NULL, array $taskWeek = [], array $tasksByUnit = [], \series\Task $eTaskStock = new \series\Task(), string $origin = ''): \Panel {

		$form = new \util\FormUi();

		$h = $form->hidden('id', $eStructure);
		$h .= $form->hidden('week', $week);
		$h .= $form->hidden('mode', 'add');
		$h .= $form->hidden('panel', 1);
		$h .= $form->hidden('origin', $origin);
		$h .= $form->hiddens($taskWeek);

		// L'unité de la règle ne désigne ni intervention ni unité : les deux champs ne partent qu'avec une autre.
		if($tasksByUnit !== []) {
			$h .= $form->hidden('task', '', ['disabled' => TRUE]);
			$h .= $form->hidden('harvestUnit', '', ['disabled' => TRUE]);
		}

		if($open !== NULL) {
			$h .= $form->hidden('open', $open);
		}

		if($taskWeek !== []) {

			$h .= $form->group(
				s("Série"),
				'<div class="util-vignette">'.self::getAutocomplete($eCultivation)['itemHtml'].'</div>'.
				$form->hidden('cultivation', $eCultivation)
			);

		} else {

			$h .= $form->group(
				s("Série"),
				$form->dynamicField(new \series\Task(['cultivation' => $eCultivation]), 'cultivation', function(\PropertyDescriber $d) use($eStructure, $week) {

					$d->prepend = \Asset::icon('flower2');
					$d->field = 'autocomplete';
					$d->placeholder = s("Espèce ou série");
					$d->autocompleteUrl = '/planning/harvestWeek:query';
					$d->autocompleteBody = [
						'id' => $eStructure['id'],
						'week' => $week,
					];
					$d->autocompleteResults = fn(\series\Cultivation $e) => self::getAutocomplete($e);
					$d->autocompleteDispatch = '#planning-harvest-create-cultivation';

					// `onrender` remplace celui de `FormUi::autocomplete()`, qui démarre le champ : le reprendre, sinon
					// rien ne se cherche. Le curseur ne s'y pose pas à l'ouverture, comme dans « Nouvelle intervention »,
					// décidé (retour 260) : la liste s'ouvre au clic dans le champ.
					$d->attributes['onrender'] = 'PlanningAutocomplete.start(this);';
					$d->attributes['data-planning-task-escape'] = 'true';

				}),
				['id' => 'planning-harvest-create-cultivation', 'data-wrapper' => 'cultivation']
			);

		}

		if($eCultivation->notEmpty()) {

			// Le plus tard qu'accepte la semaine affichée : aujourd'hui dans la semaine courante, le dimanche
			// d'une semaine passée (`HarvestWeekLib::getCreateDate()`).
			$h .= $form->dynamicGroup(new \series\Timesheet(['date' => $date]), 'date', function(\PropertyDescriber $d) use($dateMin, $dateMax) {
				$d->label = s("Jour de récolte");
				$d->attributes['min'] = $dateMin;
				$d->attributes['max'] = $dateMax;
			});

			// Les groupes de la quantité et du temps ne prennent aucun `data-wrapper` : leurs champs portent le leur
			// (`getQuantityField()`, `getTimeWindow()`), et un groupe hérite sinon de celui du dernier champ rendu
			// (`FormUi::group()`), ce qui afficherait chaque refus une seconde fois, sous le libellé.
			$h .= $form->group(
				s("Quantité récoltée"),
				'<div class="planning-harvest-entry-amount">'.
					$this->getQuantityField($form, $eCultivation['plant'], $unit, 'add', NULL, tasksByUnit: $tasksByUnit).
					$this->getCrate($form, $eCultivation['plant'], $unit, 'add').
				'</div>',
				['data-wrapper' => '']
			);

			$h .= $this->getChoiceGroups($form, $eCultivation, $units);

			if($eTaskStock->notEmpty()) {
				$h .= $this->getStock($form, 'planning-harvest-create-stock', $eTaskStock);
			}

			if($eStructure->hasTime()) {

				$labels = [];

				foreach($days as $dayDate => $day) {
					if($day['future'] === FALSE) {
						$labels[$dayDate] = $this->getTimeDayLabel($dayDate, $day);
					}
				}

				$h .= $form->group(
					$this->getTimeLabel('<span data-planning-harvest-time-day>'.$labels[$date].'</span>', $eUserTime),
					$this->getTimeWindow($form, $eUserTime),
					['data-planning-harvest-time-labels' => json_encode($labels), 'data-wrapper' => '']
				);

			}

			// L'envoi du panneau dit ce qu'il écrit, comme « Ajouter » au tiroir et sur la feuille.
			$h .= $form->group(
				content: $form->submit(s("Ajouter la récolte"))
			);

		}

		return new \Panel(
			id: 'panel-planning-harvest-create',
			title: s("Nouvelle récolte"),
			subTitle: s("Semaine {value}", week_number($week)),
			dialogOpen: $form->openAjax(self::getEntryUrl($taskWeek, $origin), ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h
		);

	}

	/**
	 * Où poste une saisie : la fiche d'une série du plan d'assolement, qui se redessine, la Semaine
	 * quand elle s'ouvre depuis une de ses cartes (`$taskWeek` non vide), qui répond par la carte, l'écran des
	 * récoltes sinon.
	 */
	private static function getEntryUrl(array $taskWeek, string $origin = ''): string {

		if($origin === 'assolement') {
			return '/series/place:doHarvest';
		}

		return ($taskWeek === []) ? '/planning/harvestWeek:doHarvest' : '/planning/taskWeek:doHarvest';

	}

	/**
	 * La liste proposée, avant toute frappe (`HarvestWeekLib::getSuggested()`) comme à la frappe
	 * (`HarvestWeekLib::getForQuery()`), sous des intitulés que le framework rend non sélectionnables et que le
	 * clavier saute (`form.js`, seuls les `li[data-n]` se choisissent). Avant toute frappe, une ligne la clôt pour
	 * dire qu'elle n'est pas tout ; la page le décide (`$suggested`).
	 */
	public static function getAutocompleteRanked(\Collection $cCultivation, bool $suggested): array {

		$results = [];
		$suggestion = NULL;

		foreach($cCultivation as $eCultivation) {

			if($eCultivation['harvestSuggestion'] !== $suggestion) {

				$suggestion = $eCultivation['harvestSuggestion'];

				$results[] = self::getAutocompleteText('title', match($suggestion) {
					'week' => s("En récolte cette semaine"),
					'near' => s("Récolte proche"),
					'other' => s("Autres séries"),
				});

			}

			$results[] = self::getAutocomplete($eCultivation);

		}

		if($suggested) {
			$results[] = self::getAutocompleteText('disabled', s("Tapez pour chercher parmi toutes les séries"));
		}

		return $results;

	}

	private static function getAutocompleteText(string $type, string $text): array {

		return [
			'type' => $type,
			'itemHtml' => '<div>'.encode($text).'</div>',
			'itemText' => $text,
		];

	}

	/**
	 * Un résultat se nomme comme sa ligne dans la liste (`HarvestWeekUi::getName()`), avec ce que
	 * `HarvestWeekLib::prepareNames()` a retenu pour le départager des autres résultats. Le champ, lui,
	 * ne tient que du texte : le chevron que `CultivationUi::displayPlaces()` dessine en icône entre la
	 * parcelle et ses planches y devient le caractère `›`, sans quoi « 492 n8 » s'y lirait d'un bloc.
	 */
	public static function getAutocomplete(\series\Cultivation $eCultivation): array {

		$name = new HarvestWeekUi()->getName($eCultivation);

		$item = \plant\PlantUi::getVignette($eCultivation['plant'], '2.5rem');
		$item .= '<div>'.$name.'</div>';

		return [
			'value' => $eCultivation['id'],
			'itemHtml' => $item,
			'itemText' => preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(\Asset::icon('chevron-right'), '›', $name))))
		];

	}

	/**
	 * Le choix du couple dans le tiroir, en deux listes titrées `variety` et `harvestSize` dans sa ligne : la
	 * variété quand la culture en propose (`choiceVariety`), le calibre quand l'espèce en déclare. La feuille
	 * pose les mêmes champs en lignes qui se choisissent dans sa zone (`getSheet()`).
	 *
	 * Chaque couple écrit dans l'unité de son intervention de la semaine (`$units`) : `planning.js`
	 * (`PlanningHarvestChoice`) y règle l'unité affichée quand le couple change.
	 */
	protected function getChoice(\util\FormUi $form, \series\Cultivation $eCultivation, string $date, array $units): string {

		$varieties = $eCultivation['choiceVariety'] ? $this->getVarieties($eCultivation) : [];
		$sizes = $this->getSizes($eCultivation);

		if($varieties === [] and $sizes === []) {
			return '';
		}

		$h = '<div class="planning-harvest-entry-choice" data-planning-harvest-units="'.encode(json_encode($this->getUnits($eCultivation, $date, $units))).'">';

			if($varieties !== []) {
				$h .= $this->getChoiceField(s("Variété"), '<div data-wrapper="variety">'.$form->select('variety', $varieties, NULL, ['placeholder' => s("Toutes")]).'</div>');
			}

			if($sizes !== []) {
				$h .= $this->getChoiceField(s("Calibre"), '<div data-wrapper="harvestSize">'.$form->select('harvestSize', $sizes, NULL, ['placeholder' => s("Non spécifié")]).'</div>');
			}

		$h .= '</div>';

		return $h;

	}

	protected function getChoiceField(string $label, string $field): string {

		return '<div class="planning-harvest-entry-titled"><div class="util-label">'.$label.'</div>'.$field.'</div>';

	}

	/**
	 * Le panneau « Nouvelle récolte » pose le couple en groupes de formulaire, comme ses autres champs.
	 */
	protected function getChoiceGroups(\util\FormUi $form, \series\Cultivation $eCultivation, array $units): string {

		$h = $this->getChoiceFields($form, $eCultivation, new \plant\Variety(), new \plant\Size());

		if($h === '') {
			return '';
		}

		return '<div data-planning-harvest-units="'.encode(json_encode($this->getUnits($eCultivation, NULL, $units))).'">'.$h.'</div>';

	}

	/**
	 * Les champs Variété et Calibre d'une culture (`cVarietyChoice`, `cSizeChoice`), sans leur conteneur : celui de
	 * la feuille dit l'unité de chaque couple (`getChoiceGroups()`), celui de « Récolter » recharge le panneau
	 * (`TaskBatchUi::getHarvest()`, HM2). La variété ne se montre que si elle se choisit (`choiceVariety`).
	 */
	public function getChoiceFields(\util\FormUi $form, \series\Cultivation $eCultivation, \plant\Variety $eVariety, \plant\Size $eSize): string {

		$varieties = $eCultivation['choiceVariety'] ? $this->getVarieties($eCultivation) : [];
		$sizes = $this->getSizes($eCultivation);

		$h = '';

		if($varieties !== []) {
			$h .= $form->group(s("Variété"), $form->select('variety', $varieties, $eVariety, ['placeholder' => s("Toutes")]), ['data-wrapper' => 'variety']);
		}

		if($sizes !== []) {
			$h .= $form->group(s("Calibre"), $form->select('harvestSize', $sizes, $eSize, ['placeholder' => s("Non spécifié")]), ['data-wrapper' => 'harvestSize']);
		}

		return $h;

	}

	protected function getVarieties(\series\Cultivation $eCultivation): array {

		return $eCultivation['cVarietyChoice']->makeArray(fn($eVariety) => $eVariety['name'], preserveKeys: TRUE);

	}

	protected function getSizes(\series\Cultivation $eCultivation): array {

		return $eCultivation['cSizeChoice']->makeArray(fn($eSize) => $eSize['name'], preserveKeys: TRUE);

	}

	/**
	 * Ce que l'écran affiche de l'unité de chaque couple, `*` pour un couple qui n'a pas encore
	 * d'intervention cette semaine et prendra l'unité principale de la culture : le libellé du champ,
	 * celui de la feuille et le récolté du jour dans cette unité (`$date`), dont la feuille pré-remplit
	 * un remplacement.
	 */
	protected function getUnits(\series\Cultivation $eCultivation, ?string $date, array $units): array {

		$units['*'] = $eCultivation['mainUnit'];

		return array_map(fn($unit) => $this->getUnit($eCultivation, $date, $unit), $units);

	}

	protected function getUnit(\series\Cultivation $eCultivation, ?string $date, string $unit): array {

		return [
			'value' => $unit,
			'label' => $this->getUnitField($unit),
			'short' => \product\UnitUi::getSingular($unit, short: TRUE),
			'crate' => ($unit === \series\Task::KG and $eCultivation['plant']['crate']),
			'current' => ($date === NULL) ? 0.0 : ($eCultivation['harvestedDays'][$date][$unit] ?? 0.0),
		];

	}

	/**
	 * L'adresse qui rouvre la saisie en remplacement sur une autre unité (lot GO) : celle de son jour et de sa
	 * forme, tiroir ou feuille, à laquelle `PlanningHarvestCrate.reopen()` n'ajoute que l'intervention
	 * ou l'unité de l'option choisie.
	 */
	protected function getReopenUrl(\structure\Structure $eStructure, string $week, \series\Cultivation $eCultivation, string $date, bool $sheet): string {

		$arguments = [
			'open' => $date,
		];

		if($sheet) {
			$arguments['sheet'] = 1;
		}

		return HarvestWeekUi::urlOpen($eStructure, $week, $eCultivation, $date, 'replace').'&'.http_build_query($arguments);

	}

	/**
	 * L'intervention que le tiroir et la feuille postent. Quand ils proposent les autres unités
	 * (`$tasksByUnit`), le choix de l'unité la porte, et `task` le suit (`PlanningHarvestCrate.task()`) : un
	 * seul champ, qui part avec l'intervention de la case tant que son unité reste choisie, et `harvestUnit`,
	 * qui nomme une unité sans intervention, éteint jusqu'à ce qu'on la choisisse.
	 */
	protected function getTaskFields(\util\FormUi $form, \series\Task $eTaskChosen, array $tasksByUnit): string {

		if($tasksByUnit === []) {
			return $eTaskChosen->empty() ? '' : $form->hidden('task', $eTaskChosen);
		}

		return $form->hidden('task', $eTaskChosen->empty() ? '' : $eTaskChosen['id'], ['disabled' => $eTaskChosen->empty() ? TRUE : NULL]).
			$form->hidden('harvestUnit', '', ['disabled' => TRUE]);

	}

	/**
	 * La quantité, dans l'unité de la saisie : `harvestMore` en ajout, que `writeHarvestForWeek()`
	 * ajoute telle quelle, `harvestDay` en remplacement, pré-rempli de la quantité du jour. Une récolte
	 * en kilos d'une espèce qui se pèse à la caisse (`HarvestWeekLib::prepareCrate()`) choisit son unité
	 * dans le champ, kilos ou caisses, comme dans la maquette. Un ajout porte le « + » devant le champ, en
	 * addon comme l'unité, au tiroir (retour 4 du 04/10) comme dans « Nouvelle récolte » (R-Retours-0810,
	 * 235), sauf sur la feuille, dont le bouton dit le geste et où la quantité prend la moitié de la largeur
	 * (`$sign`) : l'addon de l'unité se marque donc de `data-planning-harvest-unit`, seul addon que le choix
	 * d'une variété réécrit (`PlanningHarvestChoice`).
	 * Les autres unités d'une récolte (`$tasksByUnit`) se choisissent au même endroit (`getUnitChoices()`), où
	 * l'unité de la saisie et ses caisses portent l'intervention que la case désigne (`$eTaskUnit`), ou l'unité
	 * sans intervention sur laquelle la saisie s'est rouverte (`$unitOther`), et la liste l'adresse qui la rouvre
	 * sur une autre (`$urlReopen`, `PlanningHarvestCrate.reopen()`). Le champ
	 * porte le `data-wrapper` de ses refus, sauf quand le groupe qui l'entoure le porte déjà (`$wrapper`) : un
	 * refus s'afficherait deux fois. Seul le tiroir y pose le curseur (`$focus`) : il ne s'ouvre qu'à la
	 * souris, le tactile ouvrant la feuille, et Entrée y enchaîne la ligne suivante. Un panneau s'ouvre sans,
	 * au doigt comme à la souris, le clavier virtuel couvrant sinon ce qu'il montre (12/10).
	 */
	protected function getQuantityField(\util\FormUi $form, \plant\Plant $ePlant, string $unit, string $mode, ?float $current, array $tasksByUnit = [], bool $focus = FALSE, bool $wrapper = TRUE, bool $sign = TRUE, \series\Task $eTaskUnit = new \series\Task(), ?string $unitOther = NULL, ?string $urlReopen = NULL, ?string $unitFirst = NULL, bool $crate = FALSE): string {

		$property = match($mode) {
			'add' => 'harvestMore',
			'replace' => 'harvestDay',
		};

		$unitFirst ??= $unit;

		// Une autre unité rouverte sans récolte ce jour-là se remplace sur un champ vide, et non sur un « 0 » qu'il
		// faudrait effacer avant de taper. Le jour de l'unité de la semaine garde son « 0 » : un jour qui ne porte
		// que du temps se remplace par lui. Rouvert en caisses, le champ garde les kilos du jour, que le retour aux
		// kilos remet (`PlanningHarvestCrate.prefill()`).
		$eTask = new \series\Task([
			$property => ($current === 0.0 and $unitFirst !== $unit) ? NULL : $current
		]);

		return '<div class="planning-harvest-entry-quantity"'.($wrapper ? ' data-wrapper="'.self::getQuantityWrapper($property).'"' : '').'>'.$form->dynamicField($eTask, $property, function(\PropertyDescriber $d) use($form, $ePlant, $unit, $mode, $tasksByUnit, $focus, $sign, $eTaskUnit, $unitOther, $urlReopen, $unitFirst, $crate) {

			if($sign and $mode === 'add') {
				$d->prepend = '+';
			}

			// Le clavier du téléphone, sans virgule pour des caisses, qui se comptent (`PlanningHarvestCrate.update()`).
			$d->attributes['inputmode'] = $this->isCrate($ePlant, $unit, $mode, $crate) ? 'numeric' : 'decimal';

			if($tasksByUnit !== []) {
				// Deux options valent `0` : la sélection se pose sur l'option (`getUnitChoices()`), pas sur la valeur.
				// La liste dit l'unité que lisent ses options sans unité de stock (`PlanningHarvestStock.getUnit()`).
				$d->append = fn() => $form->select('crate', $this->getUnitChoices($ePlant, $unit, $mode, $tasksByUnit, $eTaskUnit, $unitOther, $unitFirst, $crate), NULL, ['required' => TRUE, 'data-planning-harvest-crate' => 'on', 'data-planning-harvest-reopen' => $urlReopen, 'data-planning-harvest-stock-unit' => $unitOther ?? $unit]);
				// Les caisses suivent l'unité en tête de liste, qui n'est pas celle de la saisie quand elle est rouverte.
				$d->inputGroup = ['class' => 'planning-harvest-entry-number planning-harvest-entry-number-unit'.($this->canCrate($ePlant, $unitFirst) ? ' planning-harvest-entry-number-crate' : '')];
			} else if($this->canCrate($ePlant, $unit)) {
				$d->append = fn() => $form->select('crate', [
					0 => $this->getUnitField($unit),
					1 => $this->getCrateUnit($ePlant),
				], (int)$this->isCrate($ePlant, $unit, $mode), ['required' => TRUE, 'data-planning-harvest-crate' => 'on']);
				$d->inputGroup = ['class' => 'planning-harvest-entry-number planning-harvest-entry-number-unit planning-harvest-entry-number-crate'];
			} else {
				$d->append = fn() => $form->addon($this->getUnitField($unit), ['data-planning-harvest-unit' => 'on']);
				$d->inputGroup = ['class' => 'planning-harvest-entry-number'];
			}

			if($focus) {
				$d->attributes['onrender'] = 'this.focus();';
			} else {
				// Le describer de `harvestMore` prend lui-même le focus (`TaskUi::p()`) : il se retire ici.
				unset($d->attributes['onrender']);
			}

		}).'</div>';

	}

	/**
	 * La quantité récoltée de « Nouvelle intervention » (retour 258), celle de « Nouvelle récolte » en ajout : le
	 * même champ et ses caisses, dans l'unité de la saisie (`$unit`), celle que prend une intervention neuve
	 * (`TaskLib::writeHarvestForWeek()`), ou dans une autre unité (`$tasksByUnit`), que le champ caché
	 * `harvestUnit` poste (retour 319). L'unité de la saisie est celle de la culture pour une série, le kilo pour
	 * une espèce seule (BY-bis). Le panneau la reçoit au choix de la série ou de l'espèce (`taskDay:harvest`), le
	 * curseur restant où on l'a laissé. Le groupe du panneau porte les refus du champ
	 * (`TaskCreateUi::getHarvest()`), pour que son libellé passe en rouge comme les autres.
	 */
	public function getQuantity(\util\FormUi $form, \plant\Plant $ePlant, string $unit, array $tasksByUnit): string {

		return '<div class="planning-harvest-entry-amount">'.
			$form->hidden('harvestUnit', '', ['disabled' => TRUE]).
			$this->getQuantityField($form, $ePlant, $unit, 'add', NULL, $tasksByUnit, wrapper: FALSE).
			$this->getCrate($form, $ePlant, $unit, 'add').
		'</div>';

	}

	/**
	 * « Ajouter à un stock » d'une saisie de récolte (DG-4), le champ de l'ancien planning
	 * (`\series\TaskUi::getStockField()`) sous son propre identifiant (`$id`), pour qu'un écran qui porte
	 * plusieurs saisies redessine le bon. Le cadre dit de quelle récolte il s'agit (`$eTaskReference`,
	 * `HarvestStockLib::getReference()`), avec les stocks qu'elle propose (`HarvestStockLib::fill()`), et
	 * `PlanningHarvestStock` le relit quand la variété ou l'unité de la saisie change. Sans stock compatible, le
	 * champ est un cadre vide, qui reste là pour le rechargement. Le tiroir et la feuille titrent le champ comme
	 * leurs autres champs (`$titled`, `getChoiceField()`), les panneaux le posent en groupe de formulaire.
	 */
	public function getStock(\util\FormUi $form, string $id, \series\Task $eTaskReference, bool $titled = FALSE): string {

		$h = '<div class="planning-harvest-stock-field" data-planning-harvest-stock="'.$id.'"';
		$h .= ' data-plant="'.$eTaskReference['plant']['id'].'"';
		$h .= ' data-cultivation="'.($eTaskReference['cultivation']->empty() ? '' : $eTaskReference['cultivation']['id']).'"';
		$h .= ' data-variety="'.($eTaskReference['variety']->empty() ? '' : $eTaskReference['variety']['id']).'"';
		$h .= ' data-unit="'.$eTaskReference['harvestUnit'].'"';
		$h .= ' data-titled="'.($titled ? '1' : '0').'">';

		if($titled === FALSE) {
			return $h.new \series\TaskUi()->getStockField($form, $eTaskReference, $eTaskReference['cProductStock'], $eTaskReference['eProductBookmark'], $id).'</div>';
		}

		$h .= '<div id="'.$id.'">';

			if($eTaskReference['cProductStock']->notEmpty()) {
				$h .= $this->getChoiceField(s("Ajouter à un stock"), '<div data-wrapper="stock">'.new \series\TaskUi()->getStockInput($form, $eTaskReference, $eTaskReference['cProductStock'], $eTaskReference['eProductBookmark']).'</div>');
			}

		$h .= '</div>';

		return $h.'</div>';

	}

	/**
	 * Les propriétés dont le refus se pose sur le champ de quantité.
	 */
	public static function getQuantityWrapper(string $property): string {
		return $property.' harvestDates harvestUnit';
	}

	/**
	 * Le choix de l'unité de « Nouvelle récolte » : l'unité de la règle, suivie des caisses quand elle les
	 * prend, puis chaque autre unité d'une récolte, qui désigne son intervention de la semaine
	 * (`data-planning-harvest-task`) ou, sans elle, se nomme (`data-planning-harvest-other`, retour 319), et ne
	 * prend pas de caisses, son poids ne se réglant que pour l'unité de la règle (`getCrate()`). `crate` reste
	 * à `0` sur toutes les unités et passe à `1` sur les caisses : plusieurs options partagent donc une
	 * valeur, et la sélection se pose par option. Rouverte sur une unité sans intervention (`$unitOther`), la
	 * saisie la porte sur son unité et ses caisses : sans elle, l'écriture repartirait dans l'unité de la règle.
	 *
	 * Une variété ou un calibre choisi (HN-B2c) donne à l'option de l'unité de la saisie celle du couple
	 * (`PlanningHarvestCrate.unit()`) : une seconde option de l'unité de la saisie, cachée et éteinte tant
	 * qu'aucun couple n'est choisi (`data-planning-harvest-couple`), la reprend en fin de liste, et l'autre
	 * unité égale à celle du couple se cache.
	 *
	 * Rouverte sur une autre unité (lot GO), la liste garde l'ordre de l'ouverture (`$unitFirst`), l'unité de
	 * la semaine en tête avec ses caisses, qui désignent alors son intervention : la liste ne change ni d'ordre
	 * ni de largeur d'une unité à l'autre. L'unité de la saisie et ses caisses se marquent
	 * (`data-planning-harvest-own`), seules à se choisir sans rouvrir (`PlanningHarvestCrate.reopen()`).
	 */
	protected function getUnitChoices(\plant\Plant $ePlant, string $unit, string $mode, array $tasksByUnit, \series\Task $eTaskUnit = new \series\Task(), ?string $unitOther = NULL, ?string $unitFirst = NULL, bool $crate = FALSE): array {

		$unitFirst ??= $unit;
		$crateSelected = $this->isCrate($ePlant, $unit, $mode, $crate);

		$units = array_values(array_intersect(\series\Task::model()->getPropertyEnum('harvestUnit'), [$unit, ...array_keys($tasksByUnit)]));
		$units = [$unitFirst, ...array_diff($units, [$unitFirst])];

		$choices = [];

		foreach($units as $unitChoice) {

			if($unitChoice === $unit) {
				$choices[] = $this->getUnitChoice(0, $this->getUnitField($unit), $crateSelected === FALSE, $unitOther, $eTaskUnit, TRUE);
			} else {
				$choices[] = $this->getUnitChoice(0, $this->getUnitField($unitChoice), FALSE, $unitChoice, $tasksByUnit[$unitChoice]);
			}

			if($unitChoice === $unitFirst and $this->canCrate($ePlant, $unitFirst)) {
				$choices[] = ($unitFirst === $unit) ?
					$this->getUnitChoice(1, $this->getCrateUnit($ePlant), $crateSelected, $unitOther, $eTaskUnit, TRUE) :
					$this->getUnitChoice(1, $this->getCrateUnit($ePlant), FALSE, $unitFirst, $tasksByUnit[$unitFirst]);
			}

		}

		$choices[] = $this->getUnitChoice(0, $this->getUnitField($unit), FALSE, $unit, couple: TRUE);

		return $choices;

	}

	/**
	 * Le mot au bout du champ de quantité, au pluriel comme « caisses » à côté de lui : on y compte des
	 * bottes ou des pièces, quel que soit le nombre tapé.
	 */
	public static function getUnitField(string $unit): string {

		return match($unit) {
			\series\Task::KG => s("kg"),
			\series\Task::UNIT => s("pièces"),
			\series\Task::BUNCH => s("bottes"),
		};

	}

	protected function getUnitChoice(int $crate, string $label, bool $selected, ?string $unitOther = NULL, \series\Task $eTask = new \series\Task(), bool $own = FALSE, bool $couple = FALSE): array {

		$attributes = [];

		if($couple) {
			$attributes['data-planning-harvest-couple'] = 'on';
			$attributes['hidden'] = 'hidden';
			$attributes['disabled'] = 'disabled';
		}

		if($selected) {
			$attributes['selected'] = 'selected';
		}

		if($own) {
			$attributes['data-planning-harvest-own'] = 'on';
		}

		if($eTask->notEmpty()) {
			$attributes['data-planning-harvest-task'] = $eTask['id'];
		} else if($unitOther !== NULL) {
			$attributes['data-planning-harvest-other'] = $unitOther;
		}

		// L'unité que le stock lit (`PlanningHarvestStock`), qu'une intervention désignée ne dit pas d'elle-même.
		if($unitOther !== NULL) {
			$attributes['data-planning-harvest-stock-unit'] = $unitOther;
		}

		return [
			'value' => $crate,
			'label' => $label,
			'attributes' => $attributes,
		];

	}

	/**
	 * À droite de la quantité en caisses, le réglage du poids d'une caisse, puis dessous la conversion en kilos
	 * (« 3 caisses · 18 kg ») (retours 283 et 284). Le poids réglé se lit dans l'unité (`getCrateUnit()`), et
	 * « Changer le poids d'une caisse » ouvre son champ ; sans poids, le champ est ouvert d'emblée et
	 * `PlantLib::prepareCrateWeight()` le demande. Qui ne peut pas écrire l'espèce n'a que l'unité.
	 */
	protected function getCrate(\util\FormUi $form, \plant\Plant $ePlant, string $unit, string $mode, bool $crate = FALSE): string {

		if($this->canCrate($ePlant, $unit) === FALSE) {
			return '';
		}

		$weight = $ePlant['crateWeight'];

		$h = '<div class="planning-harvest-crate'.($this->isCrate($ePlant, $unit, $mode, $crate) ? '' : ' hide').'" data-planning-crate data-crate-weight="'.($weight ?? '').'" data-crate-singular="'.s("caisse").'" data-crate-plural="'.s("caisses").'" data-crate-unit="'.encode(s("caisses de {value} kg", '{value}')).'">';

			if($ePlant['crateWrite']) {

				if($weight !== NULL) {
					$h .= '<a data-planning-crate-edit>'.s("Changer le poids d'une caisse").'</a>';
				}

				$h .= '<div class="planning-harvest-crate-weight'.(($weight === NULL) ? '' : ' hide').'" data-wrapper="crateWeight">';
					$h .= $form->dynamicField(new \plant\Plant(['crateWeight' => $weight]), 'crateWeight', function(\PropertyDescriber $d) {
						$d->placeholder = s("Poids");
						$d->attributes['inputmode'] = 'decimal';
					});
				$h .= '</div>';

			}

			// Sous le poids, pour que la conversion qui s'affiche à la frappe ne déplace pas le champ en cours de saisie.
			$h .= '<div class="util-annotation planning-harvest-crate-conversion" data-planning-crate-conversion></div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les caisses dans le choix de l'unité, avec leur poids quand il est réglé (« caisses de 10 kg », retour
	 * 284). `PlanningHarvestCrate.label()` réécrit le libellé quand le poids se tape, sur `data-crate-unit`.
	 */
	public static function getCrateUnit(\plant\Plant $ePlant): string {

		$weight = $ePlant['crateWeight'];

		return ($weight === NULL) ?
			s("caisses") :
			s("caisses de {value} kg", \util\TextUi::number($weight, NULL));

	}

	/**
	 * La quantité se choisit avec son unité, d'une liste et non d'une unité écrite, dès que la récolte a d'autres
	 * unités ou que l'unité prend des caisses (`getQuantityField()`).
	 */
	protected function hasUnitChoice(\plant\Plant $ePlant, string $unit, array $tasksByUnit): bool {
		return ($tasksByUnit !== [] or $this->canCrate($ePlant, $unit));
	}

	/**
	 * Les caisses ne valent qu'en kilos, et pour une espèce qui a un poids de caisse ou qu'on peut régler.
	 */
	protected function canCrate(\plant\Plant $ePlant, string $unit): bool {

		return ($unit === \series\Task::KG and $ePlant['crate']);

	}

	/**
	 * Une espèce pesée à la caisse ajoute en caisses par défaut ; un remplacement part en kilos, l'unité de
	 * la quantité qu'il remplace, le nombre de caisses n'étant pas gardé, sauf quand les caisses viennent
	 * d'être choisies (`$crate`, la saisie rouverte sur elles).
	 */
	protected function isCrate(\plant\Plant $ePlant, string $unit, string $mode, bool $crate = FALSE): bool {

		return (
			$this->canCrate($ePlant, $unit) and
			(
				$crate or
				($mode === 'add' and $ePlant['crateWeight'] !== NULL)
			)
		);

	}

	/**
	 * Le libellé d'un temps qui s'écrit à une personne, suivi de son rond et de son nom, comme la tête de
	 * la bulle du temps (`TaskCardUi::getBubble()`, retour 122 : « confirmer la personne qui saisit »).
	 */
	public function getTimeLabel(string $label, \user\User $eUserTime): string {

		return '<span class="planning-harvest-time-label">'.$label.new TimeWeekUi()->getWho($eUserTime, '1.25rem').'</span>';

	}

	/**
	 * Le temps passé d'une personne dans une saisie de récolte, au tiroir, aux panneaux « Nouvelle récolte » et
	 * « Nouvelle intervention » et sur la feuille du tactile, par les pas du champ (R-Retours-0810, 208). Ils
	 * sont ouverts dès l'ouverture, en ligne sous la personne, sans tuile (`DurationUi::getStepInline()`, lot BW,
	 * retours 280 et 290). Ils n'ont pas d'« Enregistrer » : chaque pas reporte aussitôt la durée dans le formulaire
	 * (`PlanningHarvestTime`), et elle part avec « Ajouter », en heures, par le champ caché `time[<user>]`
	 * (R-Retours-0810, 224), actif seulement au-dessus de zéro. Sans temps, rien n'est posté pour la personne.
	 * Seul le serveur sait ce que la journée porte déjà : un temps qui lui ferait dépasser 24 heures revient en
	 * refus à l'envoi, sous les pas (`time[<user>]`, et `time` pour une personne refusée). Le tiroir et la
	 * feuille le reçoivent ailleurs, et passent `$wrapped` à `FALSE`.
	 *
	 * Une saisie qui peut remplacer porte le temps que la personne a passé chaque jour sur l'intervention
	 * (`$spent`, R-Retours-0810, 219). En remplacement (`$replace`), la durée part de celui du jour du temps
	 * (`$date`), et la durée part toujours, 0 compris : elle remplace ce temps, quand ailleurs elle s'ajoute.
	 * Le jour du temps ou le geste changés, `PlanningHarvestTime.reset()` repart du temps du jour. La durée
	 * d'une personne cachée (`$shown`) ne part pas.
	 */
	public function getTimeWindow(\util\FormUi $form, \user\User $eUser, bool $wrapped = TRUE, ?array $spent = NULL, ?string $date = NULL, bool $replace = FALSE, bool $shown = TRUE): string {

		$time = $replace ? $spent[$date] : 0.0;
		$name = 'time['.$eUser['id'].']';

		$attributes = '';

		if($wrapped) {
			$attributes .= ' data-wrapper="time '.$name.'"';
		}

		if($spent !== NULL) {
			$attributes .= ' data-planning-harvest-spent="'.encode(json_encode($spent)).'"';
		}

		if($replace) {
			$attributes .= ' data-planning-harvest-replace';
		}

		$h = '<div class="planning-harvest-time" data-planning-harvest-user="'.$eUser['id'].'"'.$attributes.'>';
			$h .= new DurationUi()->getStepInline('harvest-'.$eUser['id'], $time);
			$h .= ($replace and $shown) ?
				$form->hidden($name, $time) :
				$form->hidden($name, $replace ? $time : '', ['disabled' => TRUE]);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le temps prévu de « Nouvelle intervention » (L5d), saisi dans la fenêtre des pas
	 * (`DurationUi::getStepWindow()`) hors des saisies de récolte, sans la marque d'un temps passé. `planning.js`
	 * (`PlanningHarvestTime`) écrit le total en heures dans le champ caché, et ne l'active qu'au-dessus de zéro.
	 */
	public function getTimeExpectedField(\util\FormUi $form): string {

		$h = '<div class="planning-harvest-time">';
			$h .= new DurationUi()->getStepWindow('harvest', 0.0, wrapper: NULL, spent: FALSE);
			$h .= $form->hidden('timeExpected', '', ['disabled' => TRUE]);
		$h .= '</div>';

		return $h;

	}

}
?>
