<?php
namespace hr;

/**
 * Le récapitulatif d'un mois, onglet de l'équipe (`\structure\HeaderUi::getTeamTabs()`) : l'équipe en une ligne
 * par personne, et en panneau le détail d'une personne semaine par semaine, chaque jour comme ceux de la fiche de
 * temps (`util-days`), qui ouvre la saisie de sa journée (`\planning\TimeDayUi::getWorking()`).
 */
class RecapUi {

	/**
	 * L'onglet de l'équipe, comme `AbsenceUi::TAB`, qui ne se retient pas comme écran d'arrivée de l'équipe.
	 */
	const string TAB = 'recap';

	public function __construct() {

		\Asset::css('hr', 'recap.css');
		\Asset::js('main', 'stickyColumnAi.js');

		// La saisie d'une journée est celle du planning, sa feuille et son panneau compris.
		\Asset::css('planning', 'planning.css');
		\Asset::js('planning', 'planning.js');

	}

	public static function urlStructure(\structure\Structure $eStructure, ?string $month = NULL): string {
		return '/ferme/'.$eStructure['id'].'/equipe/recapitulatif'.($month === NULL ? '' : '?month='.$month);
	}

	public static function urlUser(\structure\Structure $eStructure, string $month, \user\User $eUser): string {
		return '/hr/recap:user?id='.$eStructure['id'].'&month='.$month.'&user='.$eUser['id'];
	}

	public static function getWeekId(string $week): string {
		return 'hr-recap-week-'.$week;
	}

	/**
	 * L'équipe : une ligne par personne, et la somme de l'équipe en pied.
	 */
	public function getTeam(\structure\Structure $eStructure, string $month, array $navigation, \Collection $cUser, array $total, array $columns, array $holidays): string {

		$h = '<div id="hr-recap">';

			$h .= $this->getPeriod($eStructure, $month, $navigation, new \user\User());
			$h .= $this->getSummary($navigation, $holidays);

			if($cUser->empty()) {
				$h .= '<div class="util-block-empty">';
					$h .= '<h3>'.s("Personne n'était dans l'équipe ce mois-ci").'</h3>';
				$h .= '</div>';
			} else {
				$h .= $this->getTable($eStructure, $month, $cUser, $total, $columns, $holidays !== []);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une personne, en panneau teinté sur le modèle de `\note\NoteUi::getPanel()`. Le mois se pose sous la fiche,
	 * qui ne dépend pas de lui, et juste au-dessus des chiffres qu'il gouverne. Le panneau fermé, la page se
	 * recharge pour que l'équipe reprenne les journées saisies.
	 */
	public function getUser(\structure\Structure $eStructure, string $month, array $navigation, \user\User $eUser, \structure\Member $eMember, \Collection $cPresence, array $weeks): \Panel {

		$h = $this->getReminder($eMember, $cPresence);
		$h .= $this->getPeriod($eStructure, $month, $navigation, $eUser);
		$h .= $this->getSummary($navigation, []);
		$h .= $this->getFigures($eUser);
		$h .= $this->getWeeks($eStructure, $eUser, $weeks);

		return new \Panel(
			id: 'panel-hr-recap-user',
			documentTitle: $eUser->getName(),
			body: $h,
			header: $this->getHead($eUser),
			close: 'reload',
			attributes: ['class' => 'util-panel-tinted']
		);

	}

	/**
	 * La tête de la fiche, que la bande du panneau porte : la vignette de la personne à la place de l'icône d'un
	 * objet, et son nom.
	 */
	protected function getHead(\user\User $eUser): string {

		$h = '<div class="util-record-head">';
			$h .= \user\UserUi::getVignette($eUser, '3.25rem');
			$h .= '<div class="util-record-identity">';
				$h .= '<h3 class="util-record-title">'.$eUser->getName().'</h3>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La fiche de la personne, sans sa tête que la bande du panneau porte. Chaque période de présence qui touche le
	 * mois est suivie de son contrat (`ContractUi::getText()`), une personne pouvant en compter plusieurs.
	 */
	protected function getReminder(\structure\Member $eMember, \Collection $cPresence): string {

		if(
			$eMember->empty() and
			$cPresence->empty()
		) {
			return '';
		}

		$h = '<article class="util-record util-record-branches mb-2">';
			$h .= '<ul class="util-record-list">';

				if($eMember->notEmpty()) {
					$h .= \main\DesignUi::getRecordItem(s("Rôle"), $eMember['role']->empty() ? s("Aucun") : encode($eMember['role']['name']));
				}

				foreach($cPresence as $ePresence) {

					$h .= \main\DesignUi::getRecordItem(s("Présence"), mb_ucfirst(new PresenceUi()->getPeriod($ePresence)));

					if($ePresence['eContract']->empty()) {
						$h .= \main\DesignUi::getRecordItem(s("Contrat"), '<span class="util-empty">'.s("Aucun contrat").'</span>');
					} else {
						$h .= \main\DesignUi::getRecordItem(s("Contrat"), ContractUi::getText($ePresence['eContract']));
					}

				}

			$h .= '</ul>';
		$h .= '</article>';

		return $h;

	}

	/**
	 * Le mois, avec le mois d'avant et le mois d'après, les douze derniers mois au menu (`RecapLib::getNavigation()`),
	 * et pour l'équipe l'export du mois et la roue du premier jour de la période. Dans le panneau d'une personne, le
	 * mois passe un niveau sous le titre du panneau, et chaque mois rouvre le panneau.
	 */
	protected function getPeriod(\structure\Structure $eStructure, string $month, array $navigation, \user\User $eUser): string {

		$url = fn(string $monthOption) => $eUser->empty() ? self::urlStructure($eStructure, $monthOption) : self::urlUser($eStructure, $monthOption, $eUser);

		$step = fn(?string $monthStep, string $icon) => ($monthStep === NULL) ?
			'<a class="btn btn-only-icon btn-outline-primary disabled">'.\Asset::icon($icon).'</a>' :
			'<a href="'.$url($monthStep).'" class="btn btn-only-icon btn-outline-primary">'.\Asset::icon($icon).'</a>';

		$h = '<div class="util-title">';

			$h .= '<div class="flex-buttons">';

				$h .= $step($navigation['previous'], 'chevron-left');

				$title = $eUser->empty() ? 'h2' : 'h3';

				$h .= '<'.$title.' class="mb-0">';
					$h .= '<a class="dropdown-toggle" data-dropdown="bottom-start">'.self::getMonthName($month).'</a>';
					$h .= '<div class="dropdown-list bg-primary">';
						$h .= '<div class="dropdown-title">'.s("Choisir une période").'</div>';
						foreach($navigation['months'] as $monthOption) {
							$h .= '<a href="'.$url($monthOption).'" class="dropdown-item '.($monthOption === $month ? 'selected' : '').'">'.self::getMonthName($monthOption).'</a>';
						}
					$h .= '</div>';
				$h .= '</'.$title.'>';

				$h .= $step($navigation['next'], 'chevron-right');

			$h .= '</div>';

			// La roue reste la dernière de la rangée, dans l'ordre des gestes d'un en-tête de page (`getPageHeader()`).
			if($eUser->empty()) {
				$h .= '<div class="flex-buttons">';
					$h .= '<a href="/hr/recap:export?id='.$eStructure['id'].'&month='.$month.'" class="btn btn-outline-primary" data-ajax-download data-waiter>'.\Asset::icon('download').'<span class="hide-xs-down"> '.s("Exporter").'</span></a>';
					$h .= '<a href="/structure/configuration:updatePeriod?id='.$eStructure->conf()['id'].'" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	protected static function getMonthName(string $month): string {
		return mb_ucfirst(\util\DateUi::getMonthName((int)substr($month, 5, 2))).' '.substr($month, 0, 4);
	}

	/**
	 * Sous le mois, en phrases, ce que son titre ne dit pas : les bornes d'une période qui n'est pas le mois civil,
	 * et les jours fériés qu'elle compte. Une période à cheval sur deux mois nomme le mois de chaque férié.
	 */
	protected function getSummary(array $navigation, array $holidays): string {

		$civil = $navigation['civil'];
		$sentences = [];

		if($civil === FALSE) {
			$sentences[] = s("Cette synthèse va du {start} au {stop}.", [
				'start' => \util\DateUi::textual($navigation['start'], \util\DateUi::DAY_MONTH),
				'stop' => \util\DateUi::textual($navigation['stop'], \util\DateUi::DATE)
			]);
		}

		if($holidays !== []) {
			$sentences[] = $this->getHolidays($civil, $holidays);
		}

		if($sentences === []) {
			return '';
		}

		return '<div class="util-block-info">'.implode(' ', $sentences).'</div>';

	}

	/**
	 * La phrase des jours fériés, qui reprend la synthèse par « Elle » quand ses bornes la précèdent.
	 */
	protected function getHolidays(bool $civil, array $holidays): string {

		$days = array_map(function(string $date) use($civil) {

			$day = (int)substr($date, 8, 2);
			$values = [
				'weekday' => mb_strtolower(\util\DateUi::getDayName((int)date('N', strtotime($date)))),
				'day' => ($day === 1) ? s("1<sup>er</sup>") : $day
			];

			if($civil) {
				return s("le {weekday} {day}", $values);
			}

			return s("le {weekday} {day} {month}", $values + ['month' => \util\DateUi::getMonthName((int)substr($date, 5, 2))]);

		}, $holidays);

		if(count($days) === 1) {
			$list = $days[0];
		} else {
			$list = s("{list} et {last}", ['list' => implode(', ', array_slice($days, 0, -1)), 'last' => last($days)]);
		}

		if($civil) {
			return p("Cette synthèse compte {value} jour férié, {list}.", "Cette synthèse compte {value} jours fériés, {list}.", count($days), ['list' => $list]);
		}

		return p("Elle compte {value} jour férié, {list}.", "Elle compte {value} jours fériés, {list}.", count($days), ['list' => $list]);

	}

	protected function getTable(\structure\Structure $eStructure, string $month, \Collection $cUser, array $total, array $columns, bool $hasHolidays): string {

		$extra = $columns['extra'];
		$complementary = $columns['complementary'];

		// Sous 576 px, le cadre impose au tableau la largeur d'un grand téléphone (`util-overflow-sm`), et non celle
		// d'une tablette : ses colonnes y tiennent, et il défile moins (21/10). `util-overflow-xs` ne défile
		// que sous 450 px, et laisserait entre les deux le tableau élargir la page.
		$h = '<div class="util-overflow-sm stick-xs">';
			$h .= '<table class="tr-even td-sticky-first">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Personne").'</th>';
						$h .= '<th class="text-end">'.s("Jours travaillés").'</th>';
						$h .= '<th class="text-end">'.s("Heures").'</th>';
						if($extra) {
							$h .= '<th class="text-end">'.($columns['balance'] ? s("Solde") : s("Supplémentaires")).'</th>';
						}
						if($complementary) {
							$h .= '<th class="text-end">'.s("Complémentaires").'</th>';
						}
						if($hasHolidays) {
							$h .= '<th class="text-end">'.s("Fériés travaillés").'</th>';
						}
						$h .= '<th class="text-end">'.s("Absences").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

					foreach($cUser as $eUser) {

						$h .= '<tr>';
							$h .= '<td class="td-vertical-align-middle">';
								$h .= '<div class="hr-recap-person">';
									$h .= \user\UserUi::getVignette($eUser, '3rem');
									$h .= '<div>';
										$h .= '<a href="'.self::urlUser($eStructure, $month, $eUser).'" class="util-button">'.$eUser->getName().'</a>';
										$h .= $this->getMissing($eUser['missing']);
									$h .= '</div>';
								$h .= '</div>';
							$h .= '</td>';
							$h .= $this->getCells($eUser['recap'], $extra, $complementary, $hasHolidays);
						$h .= '</tr>';

					}

				$h .= '</tbody>';

				if($cUser->count() > 1) {

					$h .= '<tfoot>';
						$h .= '<tr>';
							$h .= '<td class="td-vertical-align-middle">';
								$h .= '<b>'.s("Équipe").'</b>';
								$h .= $this->getMissing($total['missing']);
							$h .= '</td>';
							$h .= $this->getCells($total, $extra, $complementary, $hasHolidays, TRUE);
						$h .= '</tr>';
					$h .= '</tfoot>';

				}

			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les jours qui manquent dans la période (`Presence::isDateMissing()`), ceux que le détail d'une personne montre en
	 * orange, sur la ligne du nom qu'ils suivent.
	 */
	protected function getMissing(int $missing): string {

		if($missing === 0) {
			return '';
		}

		return ' <span class="badge badge-warning">'.p("{value} jour non saisi", "{value} jours non saisis", $missing).'</span>';

	}

	protected function getCells(array $recap, bool $extra, bool $complementary, bool $holidays, bool $bold = FALSE): string {

		$cell = fn(string $content) => '<td class="text-end td-vertical-align-middle">'.$content.'</td>';
		$strong = fn(string $content) => $bold ? '<b>'.$content.'</b>' : $content;

		$h = $cell($strong(self::getDays($recap['days'])));
		$h .= $cell($strong(\series\TaskUi::convertTime($recap['time'])));

		if($extra) {
			$h .= $cell($strong($this->getExtra($recap)));
		}

		if($complementary) {
			$h .= $cell($strong($this->getHours($recap['complementary'])));
		}

		if($holidays) {
			$h .= $cell($strong(self::getDays($recap['holidays'])));
		}

		$h .= $cell($strong($this->getAbsences($recap['absences'])));

		return $h;

	}

	/**
	 * Les heures supplémentaires d'un contrat à la semaine, dont celles au-delà de 43 h, ou le solde d'un contrat
	 * annualisé à la fin du mois.
	 */
	protected function getExtra(array $recap): string {

		$h = $this->getExtraValue($recap);

		foreach($this->getExtraDetails($recap) as $detail) {
			$h .= '<div class="hr-recap-detail color-muted">'.$detail.'</div>';
		}

		return $h;

	}

	/**
	 * Le chiffre des heures supplémentaires : celles d'un contrat à la semaine, ou à défaut le solde d'un
	 * annualisé, qui en tient lieu.
	 */
	protected function getExtraValue(array $recap): string {

		if($recap['extra'] === NULL and $recap['year'] !== NULL) {
			return $this->getBalance($recap['year']['balance']);
		}

		return $this->getHours($recap['extra']);

	}

	/**
	 * Ce qui précise le chiffre, une ligne chacun : les heures au-delà de 43 h, et pour un annualisé ce qui lui
	 * reste sur sa période, ou le jour de sa sortie, avec son solde quand le chiffre est celui d'un contrat à la
	 * semaine du même mois.
	 */
	protected function getExtraDetails(array $recap): array {

		$details = [];

		if(
			$recap['extraHigh'] !== NULL and
			$recap['extraHigh'] > 0.0
		) {
			$details[] = s("dont {value} au-delà de {high}&nbsp;h", ['value' => \planning\DurationUi::formatDuration($recap['extraHigh']), 'high' => \util\TextUi::number(OvertimeLib::HIGH, NULL)]);
		}

		$year = $recap['year'];

		if($year !== NULL) {

			if($recap['extra'] !== NULL) {
				$details[] = s("solde annualisé {balance}", ['balance' => $this->getBalance($year['balance'])]);
			} else if($year['end'] !== NULL) {
				$details[] = s("annualisé, à la sortie le {date}", ['date' => \util\DateUi::numeric($year['end'], \util\DateUi::DAY_MONTH)]);
			} else {
				$details[] = s("annualisé, reste {remaining} sur {target}", [
					'remaining' => \planning\DurationUi::formatDuration(max(0.0, $year['remaining'])),
					'target' => \planning\DurationUi::formatDuration($year['target'])
				]);
			}

		}

		return $details;

	}

	protected function getBalance(float $balance): string {
		return ($balance < 0 ? '-' : '+').\planning\DurationUi::formatDuration(abs($balance));
	}

	/**
	 * Des heures supplémentaires ou complémentaires : un tiret quand il n'y en a pas, rien quand aucun contrat ne
	 * les compte.
	 */
	protected function getHours(?float $hours): string {

		if($hours === NULL) {
			return '';
		}

		if($hours <= 0.0) {
			return '<span class="color-muted">-</span>';
		}

		return \series\TaskUi::convertTime($hours);

	}

	protected static function getDays(int|float $days): string {
		return s("{value}&nbsp;j", \util\TextUi::number($days, NULL));
	}

	/**
	 * Une ligne par motif : les jours, ou les heures d'une absence à l'heure.
	 */
	protected function getAbsences(array $absences): string {

		if($absences === []) {
			return self::getDays(0);
		}

		$types = AbsenceUi::p('type')->values;

		$h = '';

		foreach($types as $type => $label) {

			if(array_key_exists($type, $absences) === FALSE) {
				continue;
			}

			$values = [];

			if($absences[$type]['days'] > 0.0) {
				$values[] = self::getDays($absences[$type]['days']);
			}

			if($absences[$type]['hours'] > 0.0) {
				$values[] = \planning\DurationUi::formatDuration($absences[$type]['hours']);
			}

			$h .= '<div>'.implode(' ', $values).'&nbsp;<span class="color-muted">'.mb_strtolower($label).'</span></div>';

		}

		return $h;

	}

	/**
	 * Les chiffres du mois d'une personne, puis ceux de son contrat annualisé sur sa période, que la saisie d'une
	 * journée redessine ensemble.
	 */
	public function getFigures(\user\User $eUser): string {

		$recap = $eUser['recap'];

		$figure = fn(string $label, string $value, array $details = []) => '<div class="util-figure"><div class="util-figure-label util-figure-label-top">'.$label.'</div><div class="util-figure-value">'.$value.'</div>'.implode('', array_map(fn(string $detail) => '<div class="util-figure-variation">'.$detail.'</div>', $details)).'</div>';

		$h = '<div id="hr-recap-figures">';

		$h .= '<div class="util-figures">';

			$h .= $figure(s("Jours travaillés"), self::getDays($recap['days']));
			$h .= $figure(s("Heures"), \series\TaskUi::convertTime($recap['time']));

			if($recap['extra'] !== NULL) {
				$h .= $figure(s("Supplémentaires"), $this->getExtraValue($recap), $this->getExtraDetails($recap));
			}

			if($recap['complementary'] !== NULL) {
				$h .= $figure(s("Complémentaires"), $this->getHours($recap['complementary']));
			}

			$h .= $figure(s("Fériés travaillés"), self::getDays($recap['holidays']));

			$h .= $figure(s("Absences"), self::getDays($recap['absent']));

		$h .= '</div>';

		if($recap['year'] !== NULL) {
			$h .= $this->getYear($recap['year']);
		}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le contrat annualisé sur sa période (`OvertimeLib::getYearBalances()`) : ce qu'attendaient les jours connus,
	 * ce qui est fait, le solde, et ce qui reste sur la cible de la période. Les jours non saisis, hors du solde, se
	 * signalent à côté du titre.
	 */
	protected function getYear(array $year): string {

		$figure = fn(string $label, string $value, string $target = '') => '<div class="util-figure"><div class="util-figure-label util-figure-label-top">'.$label.'</div><div class="util-figure-value">'.$value.'</div>'.($target === '' ? '' : '<div class="util-figure-target">'.$target.'</div>').'</div>';

		$h = '<div class="util-title mt-2">';
			$h .= '<h4 class="mb-0">'.s("Contrat annualisé, du {start} au {stop}", [
				'start' => \util\DateUi::textual($year['start'], \util\DateUi::DAY_MONTH),
				'stop' => \util\DateUi::textual($year['stop'], \util\DateUi::DATE)
			]).'</h4>';
			$h .= $this->getMissing($year['missing']);
		$h .= '</div>';

		$progress = ($year['target'] > 0.0) ? round(min($year['worked'], $year['target']) / $year['target'] * 100, 1) : 0.0;

		$h .= '<div class="util-figures">';
			$h .= $figure(s("Attendu"), \series\TaskUi::convertTime($year['expected']), p("sur {value} jour saisi", "sur les {value} jours saisis", $year['days']));
			$h .= $figure(s("Fait"), \series\TaskUi::convertTime($year['worked']));
			// Le solde d'une tuile s'écrit comme ses voisines (`convertTime()`), celui d'une cellule par `getBalance()`.
			$h .= $figure(s("Solde"), ($year['balance'] < 0 ? '-' : '+').\series\TaskUi::convertTime(abs($year['balance'])));
			$h .= $figure(
				s("Reste à faire"),
				\series\TaskUi::convertTime(max(0.0, $year['remaining'])),
				s("sur {value}", \planning\DurationUi::formatDuration($year['target'])).'<div class="util-progress"><div class="util-progress-part bg-success" style="width: '.$progress.'%"></div></div>'
			);
		$h .= '</div>';

		return $h;

	}

	protected function getWeeks(\structure\Structure $eStructure, \user\User $eUser, array $weeks): string {

		$h = '';

		foreach($weeks as $week => $days) {
			$h .= $this->getWeek($eStructure, $eUser, $week, $days);
		}

		return $h;

	}

	/**
	 * Une semaine du mois, sa durée en tête et ses sept jours dessous, ceux d'un autre mois grisés.
	 */
	public function getWeek(\structure\Structure $eStructure, \user\User $eUser, string $week, array $weekRecap): string {

		$h = '<div id="'.self::getWeekId($week).'" class="hr-recap-week">';

			$h .= '<h4>';
				$h .= s("Semaine {value}", week_number($week));
				if($weekRecap['time'] > 0.0) {
					$h .= ' <span class="color-muted">'.\series\TaskUi::convertTime($weekRecap['time']).'</span>';
				}
			$h .= '</h4>';

			$h .= '<div class="util-days util-days-month">';
				foreach($weekRecap['days'] as $date => $day) {
					$h .= $this->getDay($eStructure, $eUser, $week, $date, $day, $weekRecap['values']);
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un jour : sa journée, puis sa pastille, « Férié » dessous un jour férié, en orange un jour qui manque
	 * (`Presence::isDateMissing()`). Une semaine sans journée ni absence (`$values`) n'a pas de ligne de journées. Le
	 * jour entier ouvre la saisie de la journée, comme sur la fiche de temps.
	 */
	protected function getDay(\structure\Structure $eStructure, \user\User $eUser, string $week, string $date, array $day, bool $values): string {

		$eWorkingTime = $day['eWorkingTime'];

		$states = [];

		if(
			$day['inPeriod'] === FALSE or
			$day['inPresence'] === FALSE or
			$day['future']
		) {
			$states[] = 'is-disabled';
		}

		if($day['missing']) {
			$states[] = 'is-warning';
		}

		if($eWorkingTime->notEmpty()) {
			$value = \planning\DurationUi::formatDuration($eWorkingTime['time']);
		} else if($day['absent']) {
			$value = '<span class="color-muted">'.s("Absent").'</span>';
		} else {
			$value = '&nbsp;';
		}

		$content = $values ? '<b class="util-day-value">'.$value.'</b>' : '';
		$content .= \util\DateUi::calendarWeekday($date, large: TRUE, value: ($day['inPeriod'] and $day['holiday']) ? HolidayUi::getHoliday() : NULL);

		if($day['canWorking'] === FALSE) {
			return '<div class="util-day '.implode(' ', $states).'">'.$content.'</div>';
		}

		$tile = '<a class="util-day '.implode(' ', $states).'" data-planning-duration-open>'.$content.'</a>';

		return new \planning\TimeDayUi()->getWorking(
			$eStructure,
			$week,
			$date,
			$eUser,
			RecapLib::ORIGIN,
			$eWorkingTime->empty() ? NULL : $eWorkingTime['time'],
			TRUE,
			FALSE,
			$eWorkingTime,
			$tile
		);

	}

	/**
	 * Le fichier du mois, une ligne par personne, pour la paie, chacune ouverte par les bornes de la période pour
	 * qu'elle se lise hors de l'écran : les heures en décimales, dont celles au-delà de
	 * 43 h et le compteur d'un annualisé, puis une colonne par motif d'absence du mois, en jours, et en heures pour
	 * un motif posé à l'heure. Les heures supplémentaires et les fériés travaillés gardent leurs colonnes d'un mois
	 * à l'autre, même vides, pour que le fichier s'importe toujours de la même façon.
	 */
	public function getExport(\Collection $cUser, array $columns, string $start, string $stop): array {

		$complementary = $columns['complementary'];

		$types = $columns['types'];
		$labels = AbsenceUi::p('type')->values;

		$head = [
			s("Du"),
			s("Au"),
			s("Nom"),
			s("Jours travaillés"),
			s("Heures"),
			s("Heures supplémentaires"),
			s("Dont au-delà de {value} h", \util\TextUi::number(OvertimeLib::HIGH, NULL)),
			s("Compteur annualisé")
		];

		if($complementary) {
			$head[] = s("Heures complémentaires");
		}

		$head[] = s("Fériés travaillés");

		foreach($types as $type => $hours) {

			$head[] = s("{value} (jours)", $labels[$type]);

			if($hours) {
				$head[] = s("{value} (heures)", $labels[$type]);
			}

		}

		$lines = [$head];

		foreach($cUser as $eUser) {

			$recap = $eUser['recap'];

			// Pas `getName()`, qui encode pour une page : le tableur lirait `&amp;` dans un nom.
			$line = [
				\util\DateUi::numeric($start, \util\DateUi::DATE),
				\util\DateUi::numeric($stop, \util\DateUi::DATE),
				($eUser['firstName'] === NULL) ? $eUser['lastName'] : $eUser['firstName'].' '.$eUser['lastName']
			];

			$line[] = $recap['days'];
			$line[] = round($recap['time'], 2);
			$line[] = ($recap['extra'] === NULL) ? '' : round($recap['extra'], 2);
			$line[] = ($recap['extraHigh'] === NULL) ? '' : round($recap['extraHigh'], 2);
			$line[] = ($recap['year'] === NULL) ? '' : round($recap['year']['balance'], 2);

			if($complementary) {
				$line[] = ($recap['complementary'] === NULL) ? '' : round($recap['complementary'], 2);
			}

			$line[] = $recap['holidays'];

			// Une personne n'a que les motifs de ses absences : un motif d'une autre lui vaut zéro.
			foreach($types as $type => $hours) {

				$absence = array_key_exists($type, $recap['absences']) ? $recap['absences'][$type] : ['days' => 0.0, 'hours' => 0.0];

				$line[] = $absence['days'];

				if($hours) {
					$line[] = round($absence['hours'], 2);
				}

			}

			$lines[] = $line;

		}

		return $lines;

	}

}
?>
