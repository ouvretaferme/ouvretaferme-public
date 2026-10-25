<?php
namespace hr;

class AbsenceUi {

	/**
	 * L'onglet de l'équipe, à côté de `Member::TEAM` et `Member::ROLE`. Il n'est pas une valeur de
	 * `Member.viewTeamCategory` : l'onglet Absences ne se retient pas comme écran d'arrivée de l'équipe.
	 */
	const string TAB = 'absences';

	/**
	 * Au-delà des trois mois les plus récents, un mois de l'onglet se replie : l'année entière ferait défiler
	 * l'écran loin sous les demandes à valider.
	 */
	const int MONTHS_OPEN = 3;

	public static function urlStructure(\structure\Structure $eStructure, ?int $year = NULL): string {
		return '/ferme/'.$eStructure['id'].'/equipe/absences'.($year === NULL ? '' : '?year='.$year);
	}

	/**
	 * Les jours d'une absence, avec la demi-journée ou les heures quand elle ne couvre pas un jour entier.
	 */
	public static function getDates(Absence $eAbsence): string {

		$from = substr($eAbsence['from'], 0, 10);
		$to = substr($eAbsence['to'], 0, 10);

		$fromDay = \util\DateUi::numeric($from, \util\DateUi::DAY_MONTH);
		$toDay = \util\DateUi::numeric($to, \util\DateUi::DAY_MONTH);

		if($from === $to) {

			$part = $eAbsence->getPartOn($from);

			if($part === NULL) {
				return s("le {date}", ['date' => $fromDay]);
			}

			if($eAbsence['unit'] === Absence::HOUR) {
				return s("le {date} de {from} à {to}", ['date' => $fromDay, 'from' => self::getHour($part['from']), 'to' => self::getHour($part['to'])]);
			}

			return ($part['to'] !== NULL) ?
				s("le {date} matin", ['date' => $fromDay]) :
				s("le {date} après-midi", ['date' => $fromDay]);

		}

		$partFrom = $eAbsence->getPartOn($from);
		$partTo = $eAbsence->getPartOn($to);

		return s("du {from} au {to}", [
			'from' => ($partFrom === NULL) ? $fromDay : s("{date} après-midi", ['date' => $fromDay]),
			'to' => ($partTo === NULL) ? $toDay : s("{date} matin", ['date' => $toDay])
		]);

	}

	/**
	 * Ce que le planning dit d'une personne absente un jour, toute la journée quand `$part` est nul, sinon la part
	 * qu'en donne `Absence::getPartOn()`. Jamais le motif.
	 */
	public static function getAbsent(?array $part): string {

		if($part === NULL) {
			return s("Absent");
		}

		if($part['from'] !== NULL and $part['to'] !== NULL) {
			return s("Absent de {from} à {to}", ['from' => self::getHour($part['from']), 'to' => self::getHour($part['to'])]);
		}

		return ($part['to'] !== NULL) ? s("Absent le matin") : s("Absent l'après-midi");

	}

	public static function getHour(string $time): string {

		[$hour, $minute] = explode(':', $time);

		return ($minute === '00') ?
			s("{hour} h", ['hour' => (int)$hour]) :
			s("{hour} h {minute}", ['hour' => (int)$hour, 'minute' => $minute]);

	}

	public static function getDuration(Absence $eAbsence): string {

		if($eAbsence['unit'] === Absence::HOUR) {

			$minutes = (int)round($eAbsence['duration'] * 60);

			return self::getHour(sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60));

		}

		return p("{value} jour", "{value} jours", $eAbsence['duration'], ['value' => \util\TextUi::number($eAbsence['duration'], NULL)]);

	}

	public static function getStatus(Absence $eAbsence): string {

		return match($eAbsence['status']) {
			Absence::PENDING => '<span class="badge badge-draft">'.s("en attente").'</span>',
			Absence::VALIDATED => '<span class="badge badge-success">'.s("validée").'</span>',
			Absence::REFUSED => '<span class="badge badge-danger">'.s("refusée").'</span>'
		};

	}

	/**
	 * L'onglet Absences de l'équipe : les demandes qui attendent une réponse, puis les absences validées de
	 * l'année, par mois.
	 */
	public function getManage(\structure\Structure $eStructure, int $year, \Collection $cAbsencePending, array $months): string {

		$h = '<div class="util-title">';
			$h .= '<div class="flex-buttons">';
				$h .= '<h2 class="mb-0">'.s("Absences").'</h2>';
				$h .= '<a href="'.self::urlStructure($eStructure, $year - 1).'" class="btn btn-only-icon btn-outline-primary">'.\Asset::icon('chevron-left').'</a>';
				$h .= '<h2 class="mb-0">'.$year.'</h2>';
				// L'année suivante reste visible et inactive sur l'année en cours, comme sur l'onglet Personnes.
				if($year < \Clock::year()) {
					$h .= '<a href="'.self::urlStructure($eStructure, $year + 1).'" class="btn btn-only-icon btn-outline-primary">'.\Asset::icon('chevron-right').'</a>';
				} else {
					$h .= '<a class="btn btn-only-icon btn-outline-primary disabled">'.\Asset::icon('chevron-right').'</a>';
				}
			$h .= '</div>';
			$h .= '<div>';
				$h .= '<a href="/hr/absence:create?structure='.$eStructure['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter une absence").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= $this->getTeam($year, $cAbsencePending, $months);

		return $h;

	}

	/**
	 * Les absences de l'équipe sur l'année, demandes en tête.
	 */
	protected function getTeam(int $year, \Collection $cAbsencePending, array $months): string {

		if(
			$cAbsencePending->empty() and
			$months === []
		) {
			return '<div class="util-empty">'.s("Aucune absence en {value}.", $year).'</div>';
		}

		$h = '<table class="tr-even">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th colspan="2">'.s("Personne").'</th>';
					$h .= '<th class="hide-xs-down">'.s("Motif").'</th>';
					$h .= '<th>'.s("Dates").'</th>';
					$h .= '<th class="text-end">'.s("Durée").'</th>';
					$h .= '<th class="td-min-content"></th>';
				$h .= '</tr>';
			$h .= '</thead>';

			if($cAbsencePending->notEmpty()) {

				$h .= '<tbody class="tbody-group">';
					$h .= '<tr class="tr-title">';
						$h .= '<td colspan="10">'.s("À valider").' <span class="util-counter util-counter-solid">'.$cAbsencePending->count().'</span></td>';
					$h .= '</tr>';
				$h .= '</tbody>';

				$h .= '<tbody class="tbody-group-sub">';
					foreach($cAbsencePending as $eAbsence) {
						$h .= $this->getRow($eAbsence, $this->getDecision($eAbsence), requested: TRUE);
					}
				$h .= '</tbody>';

			}

			$position = 0;

			foreach($months as $month => $cAbsence) {

				$fold = ($position++ >= self::MONTHS_OPEN);

				$h .= '<tbody class="tbody-group'.($fold ? ' tbody-group-fold' : '').'">';
					$h .= '<tr class="tr-title">';
						$h .= '<td colspan="10">';

							$title = ucfirst(\util\DateUi::getMonthName($month)).' <span class="util-counter">'.$cAbsence->count().'</span>';

							if($fold) {
								$h .= '<a class="tbody-group-toggle" onclick="this.closest(\'tbody\').classList.toggle(\'tbody-group-open\')">';
									$h .= $title;
									$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
								$h .= '</a>';
							} else {
								$h .= $title;
							}

						$h .= '</td>';
					$h .= '</tr>';
				$h .= '</tbody>';

				$h .= '<tbody class="tbody-group-sub">';
					foreach($cAbsence as $eAbsence) {
						$h .= $this->getRow($eAbsence, $this->getActions($eAbsence, s("Modifier l'absence"), s("Supprimer l'absence")), requested: FALSE);
					}
				$h .= '</tbody>';

			}

		$h .= '</table>';

		return $h;

	}

	/**
	 * Les absences validées du mois à venir, à l'onglet Absences du planning : qui et quand seulement, ni motif ni
	 * durée (décidé le 19/10), le motif pouvant dire la santé et la durée se lisant aux dates. Chaque
	 * personne a sa frise, celle de l'onglet Personnes bornée au mois, et ses dates écrites dessous ; une demande
	 * en attente n'y paraît pas. `$upcoming` vient de `AbsenceLib::getUpcoming()`.
	 */
	public function getUpcoming(array $upcoming): string {

		[
			'cUser' => $cUser,
			'days' => $days,
			'graduation' => $graduation
		] = $upcoming;

		if($cUser->empty()) {
			return '<div class="util-empty">'.s("Personne n'est absent dans le mois à venir.").'</div>';
		}

		\Asset::css('hr', 'teammate.css');
		\Asset::css('hr', 'absence.css');

		$uiTeammate = new TeammateUi();

		// Un trait sous chaque date de l'axe traverse toutes les lignes (demandé le 20/10) : il tombe au
		// centre de la case du jour, là où la graduation écrit la date. Les jours ont le leur, plus discret
		// (demandé le 22/10), dessiné en fond sur `--days` cases (absence.css).
		$weeks = '<div class="hr-absence-upcoming-weeks"><div style="--days: '.$days.'">';

			foreach(array_keys($graduation) as $index) {
				$weeks .= '<i class="hr-absence-upcoming-week" style="left: '.round(($index + 0.5) / $days * 100, 3).'%"></i>';
			}

		$weeks .= '</div></div>';

		$h = '<table class="tr-even">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th colspan="2">'.s("Personne").'</th>';
					$h .= '<th style="width: 60%">'.$this->getGraduation($days, $graduation).'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			$h .= '<tbody>';

				foreach($cUser as $eUser) {

					$dates = [];

					// Les dates ouvrent aussi l'absence, pour qui peut la modifier : à 360 px, la bande d'un jour fait
					// 6 px, trop peu pour un doigt.
					foreach($eUser['cAbsence'] as $eAbsence) {
						$dates[] = $eAbsence->assertUpdate() ?
							'<a href="/hr/absence:update?id='.$eAbsence['id'].'">'.self::getDates($eAbsence).'</a>' :
							self::getDates($eAbsence);
					}

					$h .= '<tr>';

						$h .= '<td class="td-min-content td-vertical-align-middle">';
							$h .= \user\UserUi::getVignette($eUser, '2rem');
						$h .= '</td>';

						$h .= '<td class="td-vertical-align-middle">'.$eUser->getName().'</td>';

						// Une ligne de dates sous chaque frise, absence ou non, comme dans l'onglet Personnes : sans elle,
						// la frise d'une personne sans absence descend au milieu de sa ligne et ne s'aligne plus sur
						// celles des autres. Elle reprend aussi sa taille, `font-sm` (`TeammateUi::getRows()`) : c'est la
						// légende de la frise, qui se lit après elle.
						$h .= '<td class="td-vertical-align-middle hr-absence-upcoming-rail">';
							$h .= $weeks;
							$h .= $uiTeammate->getRail($eUser['ranges'], $eUser['segments'], $days, NULL, absencesXs: TRUE, segmentsAbsence: $eUser['segmentsAbsence']);
							$h .= '<div class="font-sm color-muted">'.($dates === [] ? s("aucune absence") : implode(', ', $dates)).'</div>';
						$h .= '</td>';

					$h .= '</tr>';

				}

			$h .= '</tbody>';

		$h .= '</table>';

		return $h;

	}

	/**
	 * La date de chaque lundi, toujours avec son mois (demandé le 22/10), en gras sur le premier lundi
	 * de la frise et sur celui qui ouvre un mois.
	 */
	protected function getGraduation(int $days, array $graduation): string {

		$h = '<div class="util-rail">';
			$h .= '<div class="util-graduation">';
				for($index = 0; $index < $days; $index++) {
					if(isset($graduation[$index]) === FALSE) {
						$h .= '<span class="util-graduation-mark"></span>';
					} else if($graduation[$index]['month']) {
						$h .= '<span class="util-graduation-mark util-graduation-mark-key">'.\util\DateUi::numeric($graduation[$index]['date'], \util\DateUi::DAY_MONTH).'</span>';
					} else {
						$h .= '<span class="util-graduation-mark">'.\util\DateUi::numeric($graduation[$index]['date'], \util\DateUi::DAY_MONTH).'</span>';
					}
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les absences validées de la semaine, sous les jours de l'onglet Temps de travail d'une ferme qui ne suit pas le temps
	 * (décidé le 17/10), aux colonnes de Paramètres › Équipe › Absences (`getTeam()`). Le motif ne se montre
	 * qu'au gérant.
	 */
	public function getWeek(\structure\Structure $eStructure, \Collection $cAbsence): string {

		$withType = $eStructure->canManage();

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Absences de la semaine").' <span class="util-counter">'.$cAbsence->count().'</span></h3>';
		$h .= '</div>';

		if($cAbsence->empty()) {
			$h .= '<div class="util-empty">'.s("Personne n'est absent cette semaine.").'</div>';
			return $h;
		}

		$h .= '<table class="tr-even">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th colspan="2">'.s("Personne").'</th>';
					if($withType) {
						$h .= '<th class="hide-xs-down">'.s("Motif").'</th>';
					}
					$h .= '<th>'.s("Dates").'</th>';
					$h .= '<th class="text-end">'.s("Durée").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			$h .= '<tbody>';
				foreach($cAbsence as $eAbsence) {
					$h .= $this->getRow($eAbsence, NULL, requested: FALSE, withType: $withType);
				}
			$h .= '</tbody>';

		$h .= '</table>';

		return $h;

	}

	/**
	 * Une ligne d'absence ; sans `$actions`, la ligne se lit seulement et n'a pas leur colonne.
	 */
	protected function getRow(Absence $eAbsence, ?string $actions, bool $requested, bool $withType = TRUE): string {

		$h = '<tr class="tr-sub">';

			$h .= '<td class="td-min-content td-vertical-align-middle">';
				$h .= \user\UserUi::getVignette($eAbsence['user'], '3rem');
			$h .= '</td>';

			$h .= '<td class="td-vertical-align-middle">'.$eAbsence['user']->getName().'</td>';

			if($withType) {
				$h .= '<td class="td-vertical-align-middle hide-xs-down">'.self::p('type')->values[$eAbsence['type']].'</td>';
			}

			$h .= '<td class="td-vertical-align-middle">';
				$h .= self::getDates($eAbsence);
				if($requested) {
					$h .= '<div class="color-muted">'.s("demandée le {value}", \util\DateUi::numeric(substr($eAbsence['createdAt'], 0, 10), \util\DateUi::DAY_MONTH)).'</div>';
				}
			$h .= '</td>';

			$h .= '<td class="text-end td-vertical-align-middle">'.self::getDuration($eAbsence).'</td>';

			if($actions !== NULL) {
				$h .= '<td class="td-min-content text-end td-vertical-align-middle">'.$actions.'</td>';
			}

		$h .= '</tr>';

		return $h;

	}

	/**
	 * Valider et Refuser, sur la forme des demandes d'adhésion d'une AMAP (`amap\MembershipRequestUi::getActions()`).
	 * Le gérant peut aussi retoucher la demande avant d'y répondre. En `btn-sm`, les deux boutons et la roue tiennent
	 * dans la hauteur d'une absence validée, que la liste aligne sous la demande une fois répondue.
	 */
	protected function getDecision(Absence $eAbsence): string {

		if($eAbsence->assertValidate() === FALSE) {
			return '';
		}

		// La cellule ne se coupe pas (`td-min-content`), ce qui tient les trois gestes sur une ligne ; sous 576 px,
		// la ligne ne tiendrait pas dans l'écran et les retours à la ligne les empilent.
		$break = '<span class="hide-sm-up"><br/></span>';

		$h = '<a data-ajax="/hr/absence:doValidate" post-id="'.$eAbsence['id'].'" class="btn btn-outline-primary btn-sm">'.s("Valider").'</a> ';
		$h .= $break;
		$h .= '<a data-ajax="/hr/absence:doRefuse" post-id="'.$eAbsence['id'].'" data-confirm="'.s("Refuser la demande d'absence de {user} {dates} ?", ['user' => encode($eAbsence['user']->getName()), 'dates' => self::getDates($eAbsence)]).'" class="btn btn-outline-primary btn-sm">'.s("Refuser").'</a> ';
		$h .= $break;
		$h .= $this->getActions($eAbsence, s("Modifier la demande"), s("Supprimer la demande"));

		return $h;

	}

	protected function getActions(Absence $eAbsence, string $update, string $delete): string {

		if($eAbsence->assertUpdate() === FALSE) {
			return '';
		}

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';

		$h .= '<div class="dropdown-list">';
			$h .= '<a href="/hr/absence:update?id='.$eAbsence['id'].'" class="dropdown-item">'.$update.'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/hr/absence:doDelete" post-id="'.$eAbsence['id'].'" data-confirm="'.s("Supprimer cette absence ?").'" class="dropdown-item">'.$delete.'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les absences d'une personne dans l'onglet Absences de son planning, et leur état. Le motif y reste : c'est
	 * la sienne. Une demande se retire ; une absence tranchée ne se retouche que par le gérant, la sienne comprise.
	 */
	public function getRequester(\Collection $cAbsence): string {

		if($cAbsence->empty()) {
			return '<div class="util-empty">'.s("Aucune absence cette année.").'</div>';
		}

		$h = '<table class="tr-even">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Motif").'</th>';
					$h .= '<th>'.s("Dates").'</th>';
					$h .= '<th class="text-end">'.s("Durée").'</th>';
					$h .= '<th>'.s("État").'</th>';
					$h .= '<th class="td-min-content"></th>';
				$h .= '</tr>';
			$h .= '</thead>';

			$h .= '<tbody>';

				foreach($cAbsence as $eAbsence) {

					$h .= '<tr>';
						$h .= '<td class="td-vertical-align-middle">'.self::p('type')->values[$eAbsence['type']].'</td>';
						$h .= '<td class="td-vertical-align-middle">'.self::getDates($eAbsence).'</td>';
						$h .= '<td class="text-end td-vertical-align-middle">'.self::getDuration($eAbsence).'</td>';
						$h .= '<td class="td-vertical-align-middle">'.self::getStatus($eAbsence).'</td>';
						if($eAbsence->isPending()) {
							$actions = $this->getActions($eAbsence, s("Modifier la demande"), s("Retirer la demande"));
						} else {
							$actions = $this->getActions($eAbsence, s("Modifier l'absence"), s("Supprimer l'absence"));
						}
						$h .= '<td class="text-end td-vertical-align-middle">'.$actions.'</td>';
					$h .= '</tr>';

				}

			$h .= '</tbody>';

		$h .= '</table>';

		return $h;

	}

	/**
	 * Les absences d'une personne sur sa fiche, pour le gérant.
	 */
	public function show(\structure\Member $eMember, \Collection $cAbsence): string {

		$h = '<div class="util-title">';

			$h .= '<h2>'.s("Absences").'</h2>';
			$h .= '<a href="/hr/absence:create?structure='.$eMember['structure']['id'].'&user='.$eMember['user']['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter une absence").'</a>';

		$h .= '</div>';

		if($cAbsence->empty()) {
			$h .= '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucune absence").'</h3>';
				$h .= '<p>'.s("Une absence validée paraît au planning de l'équipe et se compte dans la synthèse du mois.").'</p>';
			$h .= '</div>';
		} else {

			$h .= '<div class="util-overflow-sm">';

				$h .= '<table class="tr-even">';
					$h .= '<thead>';
						$h .= '<tr>';
							$h .= '<th>'.s("Motif").'</th>';
							$h .= '<th>'.s("Dates").'</th>';
							$h .= '<th class="text-end">'.s("Durée").'</th>';
							$h .= '<th>'.s("État").'</th>';
							$h .= '<th class="td-min-content"></th>';
						$h .= '</tr>';
					$h .= '</thead>';

					$h .= '<tbody>';

						foreach($cAbsence as $eAbsence) {

							$h .= '<tr>';
								$h .= '<td class="td-vertical-align-middle">'.self::p('type')->values[$eAbsence['type']].'</td>';
								$h .= '<td class="td-vertical-align-middle">'.self::getDates($eAbsence).'</td>';
								$h .= '<td class="text-end td-vertical-align-middle">'.self::getDuration($eAbsence).'</td>';
								$h .= '<td class="td-vertical-align-middle">'.self::getStatus($eAbsence).'</td>';
								$h .= '<td class="text-end td-vertical-align-middle">'.$this->getActions($eAbsence, s("Modifier l'absence"), s("Supprimer l'absence")).'</td>';
							$h .= '</tr>';

						}

					$h .= '</tbody>';

				$h .= '</table>';

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Le gérant saisit pour une personne qu'il choisit, l'absence étant validée d'emblée ; une personne au
	 * planning demande pour elle-même. Depuis « Mes absences » du planning, le gérant saisit pour lui-même, et
	 * sa personne se lit comme au panneau de modification.
	 */
	public function create(\structure\Structure $eStructure, Absence $eAbsence): \Panel {

		$form = new \util\FormUi();

		$manage = $eStructure->canManage();

		$h = $form->openAjax('/hr/absence:doCreate', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('structure', $eStructure);

			if($eAbsence['chooseUser']) {
				$h .= $form->dynamicGroup($eAbsence, 'user');
			} else if($manage) {
				$h .= $form->hidden('origin', 'planning');
				$h .= $form->group(
					s("Personne"),
					$eAbsence['user']->getName()
				);
			}

			$h .= $this->getFields($form, $eAbsence);

			$h .= $form->group(
				content: $form->submit($manage ? s("Ajouter l'absence") : s("Envoyer la demande"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-absence-create',
			title: $manage ? s("Ajouter une absence") : s("Demander une absence"),
			body: $h,
			close: 'reload'
		);

	}

	public function update(Absence $eAbsence): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/hr/absence:doUpdate', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('id', $eAbsence);

			$h .= $form->group(
				s("Personne"),
				$eAbsence['user']->getName()
			);

			$h .= $this->getFields($form, $eAbsence);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-absence-update',
			title: $eAbsence->isPending() ? s("Modifier la demande") : s("Modifier l'absence"),
			body: $h,
			close: 'reload'
		);

	}

	/**
	 * Le motif, puis les bornes et la durée, par jour ou par heure. Les deux onglets postent les mêmes noms : seul
	 * celui qui est choisi les envoie, `tabs-exclusive` désactivant le `fieldset` de l'autre.
	 */
	protected function getFields(\util\FormUi $form, Absence $eAbsence): string {

		$hour = ($eAbsence['unit'] === Absence::HOUR);

		$from = ($eAbsence['from'] === NULL) ? NULL : substr($eAbsence['from'], 0, 10);
		$fromTime = ($eAbsence['from'] === NULL) ? NULL : substr($eAbsence['from'], 11);
		$to = ($eAbsence['to'] === NULL) ? NULL : substr($eAbsence['to'], 0, 10);
		$toTime = ($eAbsence['to'] === NULL) ? NULL : substr($eAbsence['to'], 11);

		$h = $form->dynamicGroup($eAbsence, 'type');

		$h .= '<div class="tabs tabs-exclusive">';

			$h .= '<div class="tabs-item tabs-form">';
				$h .= \main\DesignUi::getTabItem('day', s("Par jour"), $hour === FALSE);
				$h .= \main\DesignUi::getTabItem('hour', s("Par heure"), $hour);
			$h .= '</div>';

			$h .= '<div class="tabs-panel">';

				$h .= '<div class="tab-panel'.($hour ? '' : ' selected').'" data-tab="day">';
					$h .= '<fieldset'.($hour ? ' disabled' : '').'>';

						$h .= $form->hidden('unit', Absence::DAY);

						$h .= $form->group(
							s("Premier jour"),
							$form->inputGroup(
								$form->date('from[date]', $from).
								$form->select('from[half]', [
									'all' => s("toute la journée"),
									'afternoon' => s("l'après-midi")
								], ($hour === FALSE and $fromTime === AbsenceLib::NOON) ? 'afternoon' : 'all', ['required' => TRUE])
							),
							['data-wrapper' => 'from']
						);

						$h .= $form->group(
							s("Dernier jour"),
							$form->inputGroup(
								$form->date('to[date]', $to).
								$form->select('to[half]', [
									'all' => s("toute la journée"),
									'morning' => s("le matin")
								], ($hour === FALSE and $toTime === AbsenceLib::NOON) ? 'morning' : 'all', ['required' => TRUE])
							),
							['data-wrapper' => 'to']
						);

						$h .= $this->getDurationGroup($form, $eAbsence, Absence::DAY);

					$h .= '</fieldset>';
				$h .= '</div>';

				$h .= '<div class="tab-panel'.($hour ? ' selected' : '').'" data-tab="hour">';
					$h .= '<fieldset'.($hour ? '' : ' disabled').'>';

						$h .= $form->hidden('unit', Absence::HOUR);

						$h .= $form->group(
							s("Jour"),
							$form->date('from[date]', $from),
							['data-wrapper' => 'from']
						);

						$h .= $form->group(
							s("Heures"),
							$form->inputGroup(
								$form->time('from[time]', $hour ? substr($fromTime, 0, 5) : NULL).
								$form->addon(s("à")).
								$form->time('to[time]', $hour ? substr($toTime, 0, 5) : NULL)
							),
							['data-wrapper' => 'to']
						);

						$h .= $this->getDurationGroup($form, $eAbsence, Absence::HOUR);

					$h .= '</fieldset>';
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La durée se saisit, en jours ou en heures selon l'onglet ; une durée enregistrée dans l'autre unité ne se
	 * recopie pas, elle n'y aurait pas de sens.
	 */
	protected function getDurationGroup(\util\FormUi $form, Absence $eAbsence, string $unit): string {

		return $form->dynamicGroup($eAbsence, 'duration', function(\PropertyDescriber $d) use($unit) {
			$d->default = fn(Absence $e) => ($e['unit'] === $unit) ? $e['duration'] : NULL;
			$d->append = match($unit) {
				Absence::DAY => s("jours"),
				Absence::HOUR => s("h")
			};
		});

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Absence::model()->describer($property, [
			'from' => s("Début de l'absence"),
			'to' => s("Fin de l'absence"),
			'duration' => s("Durée"),
			'user' => s("Personne"),
			'type' => s("Motif"),
		]);

		switch($property) {

			case 'type' :
				$d->field = 'radio';
				$d->values = [
					Absence::VACATION => s("Congés payés"),
					Absence::RTT => s("RTT"),
					Absence::RECOVERY => s("Récupération"),
					Absence::SICKNESS => s("Maladie"),
					Absence::WORK_ACCIDENT => s("Accident du travail"),
					Absence::PARENTAL => s("Maternité ou paternité"),
					Absence::FAMILY_EVENT => s("Événement familial"),
					Absence::UNPAID => s("Congé sans solde"),
					Absence::OTHER => s("Autre motif")
				];
				$d->attributes = [
					'columns' => 3,
					'required' => TRUE
				];
				break;

			case 'user' :
				$d->values = fn(Absence $e) => $e['cUser'] ?? $e->expects(['cUser']);
				break;

		}

		return $d;

	}

}
?>
