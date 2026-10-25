<?php
namespace planning;

class DashboardUi {

	public function getTitle(string $name): string {

		return match($name) {
			'planning-harvest' => s("Récoltes de la semaine"),
			'planning-bilan' => s("Récoltes de la semaine passée"),
			'planning-reminder' => s("Pense-bête"),
			'planning-sales' => s("Ventes à venir et récoltes")
		};

	}

	/** Les mots du pied de carte, dont l'adresse vient de `DashboardLib::getUrl()`. */
	public function getUrlLabel(string $name): string {

		return match($name) {
			'planning-harvest', 'planning-bilan' => s("Voir les récoltes"),
			'planning-reminder' => s("Voir le pense-bête"),
			'planning-sales' => s("Voir les ventes")
		};

	}

	public function render(string $name, \structure\Structure $eStructure, array $values): string {

		return match($name) {
			'planning-harvest' => $this->getHarvest($values),
			'planning-bilan' => $this->getBilan($values),
			'planning-reminder' => $this->getReminders($eStructure, $values),
			'planning-sales' => $this->getSales($values)
		};

	}

	/**
	 * Les rangs du pense-bête de la Semaine (`ReminderUi::getBlock()`) : la feuille, sa punaise, son titre
	 * et son pied sont au socle, que la déclaration du bloc fait poser (`shape`). Une rangée par rappel à
	 * faire, puis la rangée qui en ajoute un.
	 *
	 * Les gestes vont au droit de la Semaine, celui d'écrire une intervention : le planning limité lit la
	 * liste sans case ni ligne d'ajout.
	 */
	private function getReminders(\structure\Structure $eStructure, array $values): string {

		\Asset::css('planning', 'planning.css');

		$h = '<div id="dashboard-reminders">';

			$h .= $this->getReminderRows($eStructure, $values['reminders']);

		$h .= '</div>';

		return $h;

	}

	/** Les rangs de la note, que la réponse d'un changement de jour redessine ensemble (`planning/dashboard`). */
	public function getReminderRows(\structure\Structure $eStructure, array $reminders): string {

		$canWrite = $eStructure->canPlanning();

		$h = '<div class="util-rows" id="'.self::getReminderRowsId().'">';

			foreach($reminders as $reminder) {
				$h .= $this->getReminderRow($eStructure, $reminder, $canWrite);
			}

			if($canWrite) {
				$h .= $this->getReminderAdd($eStructure);
			}

		$h .= '</div>';

		return $h;

	}

	/** La rangée d'un rappel, que la réponse d'un geste pose ou redessine seule (`planning/dashboard`). */
	public function getReminder(\structure\Structure $eStructure, array $reminder): string {

		return $this->getReminderRow($eStructure, $reminder, $eStructure->canPlanning());

	}

	/**
	 * Le texte à gauche et à droite ce que la Semaine écrit dans sa pastille (`ReminderUi::getReminder()`) :
	 * la semaine prévue d'un rappel resté, le jour d'un rappel de la semaine qui en a un. Pour qui écrit, la
	 * case coche et le texte se corrige sur place.
	 *
	 * Un rappel coché aujourd'hui reste jusqu'au soir, barré comme à la Semaine, sans son jour, qui n'a plus
	 * rien à rappeler. Sa case le décoche, et son texte ne s'ouvre plus : on corrige ce qui reste à faire.
	 */
	private function getReminderRow(\structure\Structure $eStructure, array $reminder, bool $canWrite): string {

		if($reminder['before']) {
			$badge = '<span class="badge badge-outline">'.s("s{value}", week_number($reminder['week'])).'</span>';
		} else if($reminder['done'] === FALSE and $reminder['date'] !== NULL) {
			$badge = '<span class="badge badge-outline">'.\util\DateUi::getDayNameShort((int)date('N', strtotime($reminder['date']))).'</span>';
		} else {
			$badge = '';
		}

		$label = '<span class="planning-reminder-label">'.encode($reminder['text']).'</span>';

		$h = '<div class="util-row'.($reminder['done'] ? ' planning-reminder-done' : '').'" id="'.self::getReminderId($reminder['id']).'">';
			if($canWrite) {
				$h .= '<div class="util-tick-line">';
					$h .= '<a data-ajax="/planning/dashboard:'.($reminder['done'] ? 'doReminderUncheck' : 'doReminderCheck').'" post-id="'.$eStructure['id'].'" post-reminder="'.$reminder['id'].'" class="util-tick">'.\Asset::icon($reminder['done'] ? 'check-circle' : 'circle').'</a>';
					$h .= '<div>'.($reminder['done'] ? $label : $this->getReminderEdit($eStructure, $reminder)).'</div>';
				$h .= '</div>';
			} else {
				$h .= '<div>'.$label.'</div>';
			}
			$h .= '<div>'.$badge.'</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le texte qui devient champ sur place, avec dessous les pastilles du jour et « Effacer » : la saisie
	 * d'une ligne de la Semaine (`ReminderUi::getEdit()`), postée aux routes de l'accueil. Le jour voyage
	 * en champ caché avec le texte, `doReminderUpdate` construisant les deux : un jour absent du
	 * formulaire s'y lirait comme un jour retiré.
	 */
	private function getReminderEdit(\structure\Structure $eStructure, array $reminder): string {

		$form = new \util\FormUi();

		$eReminder = new Reminder([
			'id' => $reminder['id'],
			'text' => $reminder['text'],
			'date' => $reminder['date'],
			'days' => $reminder['days']
		]);

		$id = 'dashboard-reminder-edit-'.$reminder['id'];

		$hidden = [
			'id' => $eStructure['id'],
			'reminder' => $reminder['id']
		];

		$field = '<div data-wrapper="text">'.$form->dynamicField($eReminder, 'text').'</div>';
		$field .= $form->hidden('date', $reminder['date']);

		$h = \main\QuickInlineAiUi::get($id, \main\QuickInlineAiUi::open(encode($reminder['text'])), '/planning/dashboard:doReminderUpdate', $hidden, $field, submitOnBlur: TRUE);

		$linked = new ReminderUi()->getDays($eReminder);
		$linked .= '<a data-ajax="/planning/dashboard:doReminderDelete" post-id="'.$eStructure['id'].'" post-reminder="'.$reminder['id'].'" data-confirm="'.s("Effacer ce rappel ?").'" class="planning-reminder-delete">'.s("Effacer").'</a>';

		$h .= \main\QuickInlineAiUi::linked($id, $linked, 'div', 'planning-reminder-edit');

		return $h;

	}

	/**
	 * La dernière rangée, qui ajoute un rappel à la semaine en cours : visible au repos, elle devient
	 * champ sur place comme le texte d'un rappel. Sortir du champ enregistre ce qui y est tapé, comme pour une
	 * correction : un texte tapé ne se perd pas, et un champ resté vide se referme sans rien envoyer.
	 * La Semaine ouvre un panneau pour le même geste (`ReminderUi::create()`), parce qu'elle y choisit
	 * aussi le jour : ici le rappel naît sans jour, et son jour se pose en le corrigeant.
	 */
	public function getReminderAdd(\structure\Structure $eStructure): string {

		$form = new \util\FormUi();

		$field = '<div data-wrapper="text">';
			$field .= $form->dynamicField(new Reminder(['text' => NULL]), 'text', function(\PropertyDescriber $d) {
				$d->placeholder = s("À ne pas oublier cette semaine");
			});
		$field .= '</div>';

		$h = '<div class="util-row" id="'.self::getReminderAddId().'">';
			$h .= \main\QuickInlineAiUi::get(
				'dashboard-reminder-create',
				\main\QuickInlineAiUi::action(\Asset::icon('plus-circle').' '.s("Ajouter un rappel")),
				'/planning/dashboard:doReminderCreate',
				['id' => $eStructure['id']],
				$field,
				submitOnBlur: TRUE
			);
		$h .= '</div>';

		return $h;

	}

	public static function getReminderId(int $reminder): string {
		return 'dashboard-reminder-'.$reminder;
	}

	public static function getReminderRowsId(): string {
		return 'dashboard-reminder-rows';
	}

	public static function getReminderAddId(): string {
		return 'dashboard-reminder-add';
	}

	/**
	 * Le total de la semaine en grand dans son unité, les autres unités à côté et jamais ajoutées, la
	 * semaine passée dans la même unité, puis une rangée par espèce et par unité, avec le jour quand
	 * l'espèce n'a été récoltée qu'un jour. Le nombre de cultures que le plan met en récolte ferme la
	 * cellule, et devient son chiffre quand rien n'est saisi.
	 */
	private function getHarvest(array $values): string {

		if($values['main'] === NULL) {

			$h = '';

			if($values['expected'] > 0) {
				$h .= \dashboard\DashboardUi::getValue(\util\TextUi::number($values['expected']), p("culture en récolte d'après le plan", "cultures en récolte d'après le plan", $values['expected']));
			}

			$h .= '<p class="util-empty">'.s("Aucune récolte saisie cette semaine.").'</p>';

			return $h;

		}

		$others = [];

		foreach($values['totals'] as $unit => $total) {
			if($unit !== $values['main']) {
				$others[] = $this->getQuantity($total, $unit);
			}
		}

		$label = ($others === []) ?
			s("récoltés cette semaine") :
			s("récoltés cette semaine, et {value}", implode(', ', $others));

		$h = \dashboard\DashboardUi::getValue(
			$this->getQuantity($values['totals'][$values['main']], $values['main'], fn(string $unit) => '<small>'.$unit.'</small>'),
			$label
		);

		if($values['previous'] > 0) {
			$h .= '<p class="util-annotation">'.s("{value} sur toute la semaine précédente", $this->getQuantity($values['previous'], $values['main'])).'</p>';
		}

		$h .= '<div class="util-rows">';

			foreach($values['plants'] as $plant) {

				$h .= '<div class="util-row">';

					$h .= '<div class="util-vignette">';
						$h .= \plant\PlantUi::getVignette(new \plant\Plant($plant['plant']), '1.5rem');
						$h .= '<span>';
							$h .= encode($plant['plant']['name']);
							if($plant['date'] !== NULL) {
								$h .= ' <span class="util-annotation">'.mb_strtolower(\util\DateUi::getDayName((int)date('N', strtotime($plant['date'])))).'</span>';
							}
						$h .= '</span>';
					$h .= '</div>';

					$h .= '<div><b>'.$this->getQuantity($plant['quantity'], $plant['unit']).'</b></div>';

				$h .= '</div>';

			}

			if($values['hidden'] > 0) {
				$h .= '<div class="util-row color-muted">'.p("{value} autre récolte", "{value} autres récoltes", $values['hidden']).'</div>';
			}

		$h .= '</div>';

		if($values['expected'] > 0) {
			$h .= '<p class="util-annotation">'.p("{value} culture en récolte cette semaine", "{value} cultures en récolte cette semaine", $values['expected']).'</p>';
		}

		return $h;

	}

	/**
	 * Le nombre d'espèces à livrer, puis une rangée par espèce : son nom et ce qui en a été récolté cette
	 * semaine à gauche, ce qui est à livrer et le stock à droite. Les mots sont ceux de l'écran Récoltes,
	 * et rien ne rapproche les deux côtés : une vente se compte dans l'unité du produit, une récolte dans
	 * celle de la série, et aucune règle ne passe de l'une à l'autre.
	 *
	 * Une espèce sans récolte cette semaine n'a pas de seconde ligne : « Récoltes de la semaine », sa
	 * voisine dans la bande, dit déjà ce qui n'a pas été récolté.
	 */
	private function getSales(array $values): string {

		$plants = count($values['plants']) + $values['hidden'];
		$end = mb_strtolower(\util\DateUi::getDayName((int)date('N', strtotime($values['end'])))).' '.\util\DateUi::numeric($values['end'], \util\DateUi::DAY_MONTH);

		$h = '<div class="dashboard-sales">';

			$h .= \dashboard\DashboardUi::getValue((string)$plants, p("espèce à livrer d'ici {value}", "espèces à livrer d'ici {value}", $plants, ['value' => $end]));

			$h .= '<div class="util-rows">';

				foreach($values['plants'] as $plant) {

					$h .= '<div class="util-row">';

						$h .= '<div>';
							$h .= '<b>'.encode($plant['name']).'</b>';
							if($plant['harvested'] !== []) {
								$h .= '<br/><span class="color-muted">'.s("{value} récoltés cette semaine", implode(', ', array_map(fn(string $unit, float $quantity) => $this->getQuantity($quantity, $unit), array_keys($plant['harvested']), $plant['harvested']))).'</span>';
							}
						$h .= '</div>';

						$h .= '<div>';
							$h .= s("{value} à livrer", ['value' => '<b>'.$this->getUnitValues($plant['sold']).'</b>']);
							if($plant['stock'] !== []) {
								$h .= '<br/><span class="color-muted">'.s("{value} en stock", $this->getUnitValues($plant['stock'])).'</span>';
							}
						$h .= '</div>';

					$h .= '</div>';

				}

				if($values['hidden'] > 0) {
					$h .= '<div class="util-row color-muted">'.p("{value} autre espèce", "{value} autres espèces", $values['hidden']).'</div>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le chiffre en grand, les autres unités dans son libellé sans comparaison, puis les premières espèces
	 * derrière leur vignette, chacune dans son unité (L71, piste C arbitrée le 2026-10-19).
	 */
	private function getBilan(array $values): string {

		$others = [];

		foreach($values['totals'] as $unit => $total) {
			if($unit !== $values['main']) {
				$others[] = $this->getQuantity($total, $unit);
			}
		}

		$label = ($others === []) ?
			s("récoltés") :
			s("récoltés, et {value}", implode(', ', $others));

		$h = \dashboard\DashboardUi::getValue(
			$this->getQuantity($values['totals'][$values['main']], $values['main'], fn(string $unit) => '<small>'.$unit.'</small>'),
			$label
		);

		$h .= new \dashboard\DashboardUi()->getComparison($values['variation'], s("sur la semaine {week} de {year}", ['week' => week_number($values['week']), 'year' => $values['year'] - 1]));

		$h .= '<div class="util-vignettes mt-1">';

			foreach($values['plants'] as $plant) {

				$h .= '<span class="util-vignette">';
					$h .= \plant\PlantUi::getVignette(new \plant\Plant($plant['plant']), '1.5rem');
					$h .= '<span>'.encode($plant['plant']['name']).' <b>'.$this->getQuantity($plant['quantity'], $plant['unit']).'</b></span>';
				$h .= '</span>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une quantité à la virgule, l'unité en forme courte comme sur l'écran des récoltes, l'espace qui
	 * les sépare insécable comme dans `UnitUi::getValue()`. Celui-ci écrit le nombre tel que PHP le
	 * rend, donc au point, et n'applique son `callback` qu'aux unités de récolte.
	 */
	private function getQuantity(float $value, string|\product\Unit|null $unit, ?\Closure $wrap = NULL): string {

		$number = \util\TextUi::number(round($value, 2), NULL);

		if($unit === NULL or ($unit instanceof \product\Unit and $unit->empty())) {
			return $number;
		}

		$label = \product\UnitUi::getSingular($unit, short: TRUE);

		return $number."\u{00A0}".(($wrap === NULL) ? $label : $wrap($label));

	}

	/** Une valeur par unité de produit, l'unité refaite depuis le tableau que le cache a gardé. */
	private function getUnitValues(array $entries): string {

		return implode(', ', array_map(
			fn(array $entry) => $this->getQuantity($entry['value'], ($entry['unit'] === NULL) ? NULL : new \product\Unit($entry['unit'])),
			$entries
		));

	}

}
?>
