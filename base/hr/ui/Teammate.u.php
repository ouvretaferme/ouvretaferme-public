<?php
namespace hr;

class TeammateUi {

	/**
	 * L'onglet Personnes d'une structure de production. « Présents au planning » met chaque personne de l'équipe
	 * sur la barre de ses périodes de l'année, rangée selon qu'elle est là, partie dans l'année ou partie avant ;
	 * `$team` vient de `TeammateLib::group()`. « Hors planning » liste dessous ceux qui ont un accès sans être dans
	 * l'équipe, puis les invitations.
	 */
	public function getManage(\structure\Structure $eStructure, array $team, int $year, \Collection $cMemberOutside, \Collection $cInvite, \Collection $cInviteFinished): string {

		// RH éteinte, la colonne Contrat part des deux tableaux : un même drapeau les gouverne, et ils restent alignés.
		// La roue des invitations, seul geste resté sur une ligne, pose de même sa colonne dans les deux.
		$hr = $eStructure->acceptHr();
		$invites = ($cInvite->notEmpty() or $cInviteFinished->notEmpty());

		$h = $this->getTeam($eStructure, $team, $year, $hr, $invites);
		$h .= $this->getOutside($eStructure, $cMemberOutside, $cInvite, $cInviteFinished, $hr, $invites);

		return $h;

	}

	protected function getTeam(\structure\Structure $eStructure, array $team, int $year, bool $hr, bool $invites): string {

		\Asset::css('hr', 'teammate.css');

		[
			'groups' => $groups,
			'days' => $days,
			'today' => $today,
			'isCurrent' => $isCurrent
		] = $team;

		$h = '<div class="util-title">';
			$h .= '<div class="flex-buttons">';
				$h .= '<h2 class="mb-0">'.s("Présents au planning").'</h2>';
				$h .= $this->getYearNavigation($eStructure, $year, $isCurrent);
			$h .= '</div>';
		$h .= '</div>';

		if(
			$groups['now']->empty() and
			$groups['year']->empty() and
			$groups['before']->empty()
		) {
			$h .= '<div class="util-block-empty">';
				$h .= '<h3>'.($isCurrent ? s("Personne n'est encore dans l'équipe") : s("Personne n'a travaillé dans l'équipe en {value}", $year)).'</h3>';
			$h .= '</div>';
			return $h;
		}

		// Sous 576 px, le tableau défile dans son cadre (`util-overflow-sm`) : la frise garde une largeur lisible, et
		// Contrat et Rôle restent à portée (21/10).
		$h .= '<div class="util-overflow-sm">';
		$h .= '<table class="tr-even">';

			$h .= $this->getHead($this->getGraduation(), s("Contrat"), $hr, $invites);

			if($isCurrent) {
				$h .= $this->getGroup(s("Présents en ce moment"), $groups['now'], $days, $today, $hr, $invites);
				$h .= $this->getGroup(s("Partis cette année"), $groups['year'], $days, $today, $hr, $invites);
			} else {
				$h .= $this->getGroup(s("En {value}", $year), $groups['year'], $days, $today, $hr, $invites);
			}

			$h .= $this->getGroup(s("Partis avant {value}", $year), $groups['before'], $days, $today, $hr, $invites, fold: TRUE);

		$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les personnes sans compte y entrent comme celles qui ont un accès : elles n'ont pas d'autre place sur l'écran.
	 * Sous 576 px, il défile dans son cadre comme celui de l'équipe. RH allumée, la colonne Contrat y reste vide
	 * pour l'alignement sur le tableau de l'équipe.
	 */
	protected function getOutside(\structure\Structure $eStructure, \Collection $cMemberOutside, \Collection $cInvite, \Collection $cInviteFinished, bool $hr, bool $invites): string {

		if(
			$cMemberOutside->empty() and
			$cInvite->empty() and
			$cInviteFinished->empty()
		) {
			return '';
		}

		$h = '<div class="mt-2">';

			$h .= '<div class="util-title">';
				$h .= '<h2>'.s("Hors planning").($cMemberOutside->empty() ? '' : ' <span class="util-counter">'.$cMemberOutside->count().'</span>').'</h2>';
			$h .= '</div>';

			$h .= '<div class="util-overflow-sm">';
			$h .= '<table class="tr-even">';

				$h .= $this->getHead(s("Ajouté"), '', $hr, $invites);

				if($cMemberOutside->notEmpty()) {

					$h .= '<tbody class="tbody-group-sub">';

						foreach($cMemberOutside as $eMember) {

							$urlPlanning = '/hr/teammate:create?structure='.$eStructure['id'].'&user='.$eMember['user']['id'];

							$h .= '<tr class="tr-sub">';

								$h .= '<td class="td-min-content td-vertical-align-middle">';
									$h .= \user\UserUi::getVignette($eMember['user'], '3rem');
								$h .= '</td>';

								$h .= '<td class="td-vertical-align-middle">';
									$h .= $this->getName($eMember['user'], $eMember);
								$h .= '</td>';

								// Le bouton prend la colonne de la frise, celle où la personne apparaîtra : posé en bout de ligne,
								// il élargirait la dernière colonne de ce tableau seul, et les deux tableaux ne s'aligneraient
								// plus.
								$h .= '<td class="td-vertical-align-middle">';
									$h .= '<span class="util-annotation">'.\util\DateUi::numeric($eMember['createdAt'], \util\DateUi::DATE).'</span>';
									$h .= '<a href="'.$urlPlanning.'" class="btn btn-outline-primary ml-2">'.\Asset::icon('person-plus-fill').' '.s("Ajouter au planning").'</a>';
								$h .= '</td>';

								if($hr) {
									$h .= '<td></td>';
								}

								$h .= '<td class="td-vertical-align-middle">'.$this->getAccess($eMember['user'], $eStructure['id'], $eMember).'</td>';

								if($invites) {
									$h .= '<td></td>';
								}

							$h .= '</tr>';

						}

					$h .= '</tbody>';

				}

				$h .= $this->getInvites(s("Invitations en attente"), $cInvite, $hr, fold: FALSE);
				$h .= $this->getInvites(s("Invitations terminées"), $cInviteFinished, $hr, fold: TRUE);

			$h .= '</table>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'en-tête que partagent les deux tableaux de l'onglet. Toutes les colonnes sauf Personne ont leur largeur :
	 * la dernière, celle de la roue des invitations, est vide au-dessus, et sa largeur écrite, celle de la roue avec
	 * le jeu de la cellule, la tient pareille dans les deux tableaux. Personne prend le reste, le même au-dessus et
	 * en dessous, et les deux tableaux s'alignent. Les lignes repliées n'y comptent pas, ce qui fait que déplier un
	 * groupe ne déplace rien.
	 */
	protected function getHead(string $first, string $contract, bool $hr, bool $invites): string {

		$h = '<thead>';
			$h .= '<tr>';
				$h .= '<th colspan="2">'.s("Personne").'</th>';
				$h .= '<th style="width: 32%">'.$first.'</th>';
				if($hr) {
					$h .= '<th style="width: 20%">'.$contract.'</th>';
				}
				$h .= '<th style="width: 15%">'.s("Rôle").'</th>';
				if($invites) {
					$h .= '<th style="width: 6.5rem"></th>';
				}
			$h .= '</tr>';
		$h .= '</thead>';

		return $h;

	}

	protected function getInvites(string $title, \Collection $cInvite, bool $hr, bool $fold): string {

		if($cInvite->empty()) {
			return '';
		}

		$uiInvite = new \structure\InviteUi();

		$h = $this->getGroupTitle($title, $cInvite->count(), $fold);

		$h .= '<tbody class="tbody-group-sub">';

			foreach($cInvite as $eInvite) {

				$h .= '<tr class="tr-sub">';

					$h .= '<td class="td-min-content td-vertical-align-middle">';
						$h .= \user\UserUi::getVignette(new \user\User(), '3rem');
					$h .= '</td>';

					$h .= '<td class="td-vertical-align-middle">';
						// Une adresse ne se coupe qu'aux points et après l'arobase : sans ces césures, une adresse longue
						// élargirait la colonne Personne de ce tableau seul, et les deux tableaux ne s'aligneraient plus.
						if($eInvite['email'] !== NULL) {
							$h .= str_replace(['@', '.'], ['@<wbr>', '.<wbr>'], encode($eInvite['email']));
						} else {
							$h .= $uiInvite->getCode($eInvite).' <span class="color-muted font-sm">'.s("sans adresse").'</span>';
						}
					$h .= '</td>';

					$h .= '<td class="td-vertical-align-middle">';
						if($eInvite['expiresAt'] !== NULL) {
							$h .= '<span class="util-annotation">'.s("expire le {value}", \util\DateUi::numeric($eInvite['expiresAt'])).'</span>';
						}
					$h .= '</td>';

					if($hr) {
						$h .= '<td></td>';
					}

					$h .= '<td class="td-vertical-align-middle">'.encode($eInvite['memberRole']['name']).'</td>';

					$h .= '<td class="text-end td-vertical-align-middle">';
						$h .= $uiInvite->getActions($eInvite);
					$h .= '</td>';

				$h .= '</tr>';

			}

		$h .= '</tbody>';

		return $h;

	}

	/**
	 * Le pas d'une année à l'autre reprend celui du planning à la semaine (`series\PlanningUi::getCalendarStep()`).
	 * L'année suivante reste visible et inactive sur l'année en cours, la page ne montrant pas l'avenir.
	 */
	protected function getYearNavigation(\structure\Structure $eStructure, int $year, bool $isCurrent): string {

		$url = \structure\StructureUi::urlTeam($eStructure).'?year=';

		$h = '<div class="flex-buttons">';
			$h .= '<a href="'.$url.($year - 1).'" class="btn btn-only-icon btn-outline-primary">'.\Asset::icon('chevron-left').'</a>';
			$h .= '<h2 class="mb-0">'.$year.'</h2>';
			if($isCurrent) {
				$h .= '<a class="btn btn-only-icon btn-outline-primary disabled">'.\Asset::icon('chevron-right').'</a>';
			} else {
				$h .= '<a href="'.$url.($year + 1).'" class="btn btn-only-icon btn-outline-primary">'.\Asset::icon('chevron-right').'</a>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les initiales des mois : à 1199 px, chacun des douze mois tient dans une case d'environ 23 px.
	 */
	protected function getGraduation(): string {

		$h = '<div class="util-rail">';
			$h .= '<div class="util-graduation">';
				for($month = 1; $month <= 12; $month++) {
					$h .= '<span class="util-graduation-mark">'.mb_strtoupper(mb_substr(\util\DateUi::getMonthName($month), 0, 1)).'</span>';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les trois groupes ont la même tête et les mêmes lignes filles : seul celui des personnes parties avant
	 * l'année se replie, et ses lignes n'ont rien à montrer sur la barre de l'année.
	 */
	protected function getGroup(string $title, \Collection $cTeammate, int $days, ?int $today, bool $hr, bool $invites, bool $fold = FALSE): string {

		if($cTeammate->empty()) {
			return '';
		}

		$h = $this->getGroupTitle($title, $cTeammate->count(), $fold);

		$h .= '<tbody class="tbody-group-sub">';
			$h .= $this->getRows($cTeammate, $days, $today, $hr, $invites, $fold === FALSE);
		$h .= '</tbody>';

		return $h;

	}

	/**
	 * La tête d'un groupe, celle des personnes de l'équipe comme celle des invitations. Un groupe replié s'ouvre sur
	 * sa tête.
	 */
	protected function getGroupTitle(string $title, int $count, bool $fold): string {

		$h = '<tbody class="tbody-group'.($fold ? ' tbody-group-fold' : '').'">';
			$h .= '<tr class="tr-title">';
				$h .= '<td colspan="10">';
					if($fold) {
						$h .= '<a class="tbody-group-toggle" onclick="this.closest(\'tbody\').classList.toggle(\'tbody-group-open\')">';
							$h .= $title.' <span class="util-counter">'.$count.'</span>';
							$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
						$h .= '</a>';
					} else {
						$h .= $title.' <span class="util-counter">'.$count.'</span>';
					}
				$h .= '</td>';
			$h .= '</tr>';
		$h .= '</tbody>';

		return $h;

	}

	protected function getRows(\Collection $cTeammate, int $days, ?int $today, bool $hr, bool $invites, bool $withRail): string {

		$h = '';

		foreach($cTeammate as $eTeammate) {

			$eMember = $eTeammate['eMember'];
			$ePresence = $eTeammate['ePresenceShown'];

			$h .= '<tr class="tr-sub">';

				$h .= '<td class="td-min-content td-vertical-align-middle">';
					$h .= \user\UserUi::getVignette($eTeammate['user'], '3rem');
				$h .= '</td>';

				$h .= '<td class="td-vertical-align-middle">';
					$h .= $this->getName($eTeammate['user'], $eMember);
				$h .= '</td>';

				$h .= '<td class="td-vertical-align-middle">';
					if($withRail) {
						$h .= $this->getRail($eTeammate['ranges'], $eTeammate['segments'], $days, $today, rangesPresence: $eTeammate['rangesPresence'], segmentsAbsence: $eTeammate['segmentsAbsence']);
					}
					$h .= '<div class="font-sm">';
						$h .= new PresenceUi()->getPeriod($ePresence);
						if($eTeammate['periodsBefore'] > 0) {
							$h .= ' <span class="color-muted">'.p("et {value} période de présence avant", "et {value} périodes de présence avant", $eTeammate['periodsBefore']).'</span>';
						}
					$h .= '</div>';
					if($withRail) {
						foreach($eTeammate['cPresenceComing'] as $ePresenceComing) {
							$h .= '<div class="font-sm">'.new PresenceUi()->getPeriod($ePresenceComing).'</div>';
						}
					}
				$h .= '</td>';

				if($hr) {
					$h .= '<td class="td-vertical-align-middle">';
						if($ePresence->notEmpty()) {
							$h .= $ePresence['eContract']->empty() ? '<span class="color-muted">—</span>' : ContractUi::getText($ePresence['eContract']);
						}
					$h .= '</td>';
				}

				$h .= '<td class="td-vertical-align-middle">'.$this->getAccess($eTeammate['user'], $eTeammate['structure']['id'], $eMember).'</td>';

				if($invites) {
					$h .= '<td></td>';
				}

			$h .= '</tr>';

		}

		return $h;

	}

	/**
	 * Une période d'un jour garde un pour cent de large, sans quoi elle ne se verrait pas.
	 *
	 * Les absences validées de chaque période (`$segments`, `TeammateLib::fillSegments()`) se posent par-dessus,
	 * hachurées, sans couper la période, qui reste d'un seul tenant (décidé le 17/10). Elles vivent
	 * dans la période, qui les rogne à ses bouts arrondis. Sur une année, une bande d'un jour ferait moins d'un
	 * pixel sous 576 px : la frise n'y montre alors que les périodes. Sur un mois, un jour y fait encore 6 px,
	 * et l'onglet Absences du planning garde ses bandes (`$absencesXs`).
	 *
	 * Avec `$rangesPresence`, les identifiants des périodes dans l'ordre de `$ranges`, chaque période ouvre sa
	 * modification (décidé le 20/10). Le lien couvre la période sous ses absences au lieu de la
	 * remplacer, une absence ne pouvant pas devenir à son tour un lien à l'intérieur d'un lien.
	 *
	 * Avec `$segmentsAbsence` (`TeammateLib::fillSegments()`), chaque absence ouvre aussi la sienne, par un lien
	 * frère posé sur sa propre étendue et non sur la bande : une bande réunit des absences voisines, dont
	 * chacune doit ouvrir la bonne. Le lien suit la bande, effacé sous 576 px avec elle.
	 */
	public function getRail(array $ranges, array $segments, int $days, ?int $today, bool $absencesXs = FALSE, array $rangesPresence = [], array $segmentsAbsence = []): string {

		$h = '<div class="util-rail color-secondary">';
			$h .= '<div class="util-rail-track">';
				foreach($ranges as $index => [$start, $stop]) {
					$h .= '<i class="util-rail-range hr-teammate-range" style="left: '.round($start / $days * 100, 3).'%; width: '.round(max(($stop - $start) / $days * 100, 1.0), 3).'%">';
						if($rangesPresence !== []) {
							$h .= '<a href="/hr/presence:update?id='.$rangesPresence[$index].'" class="hr-teammate-range-link"></a>';
						}
						foreach($segments[$index] as [$absenceStart, $absenceStop]) {
							$h .= '<i class="hr-teammate-absence'.($absencesXs ? '' : ' hide-xs-down').'" style="left: '.round(($absenceStart - $start) / ($stop - $start) * 100, 3).'%; width: '.round(($absenceStop - $absenceStart) / ($stop - $start) * 100, 3).'%"></i>';
						}
						if($segmentsAbsence !== []) {
							foreach($segmentsAbsence[$index] as [$absenceId, $absenceStart, $absenceStop]) {
								$h .= '<a href="/hr/absence:update?id='.$absenceId.'" class="hr-teammate-absence-link'.($absencesXs ? '' : ' hide-xs-down').'" style="left: '.round(($absenceStart - $start) / ($stop - $start) * 100, 3).'%; width: '.round(($absenceStop - $absenceStart) / ($stop - $start) * 100, 3).'%"></a>';
							}
						}
					$h .= '</i>';
				}
				if($today !== NULL) {
					$h .= '<i class="util-rail-marker" style="left: '.round(($today + 0.5) / $days * 100, 3).'%"></i>';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le nom mène à la fiche dès que la personne a une adhésion, ce qui est le cas de toute l'équipe. Un badge dit à
	 * côté ce qui manque à son accès (décidé le 22/10) : un compte fermé, pas de compte, ou plus de rôle.
	 */
	protected function getName(\user\User $eUser, \structure\Member $eMember): string {

		if($eMember->notEmpty()) {
			$h = '<a href="/structure/member:show?id='.$eMember['id'].'" class="util-button">'.$eUser->getName().'</a>';
		} else {
			$h = $eUser->getName();
		}

		if($eUser['status'] === \user\User::CLOSED) {
			$h .= ' <span class="badge badge-neutral">'.s("Compte fermé").'</span>';
		} else if(
			$eMember->empty() or
			$eMember->acceptInvite()
		) {
			$h .= ' <span class="badge badge-neutral">'.s("Sans compte").'</span>';
		} else if($eMember->isRevoked()) {
			$h .= ' <span class="badge badge-neutral">'.s("Sans accès").'</span>';
		}

		return $h;

	}

	/**
	 * Un compte fermé n'a plus rien à recevoir. Une personne sans compte s'invite à en créer un, une personne
	 * sans accès à le retrouver.
	 */
	protected function getAccess(\user\User $eUser, int $structure, \structure\Member $eMember): string {

		if($eUser['status'] === \user\User::CLOSED) {
			return '';
		}

		if(
			$eMember->empty() or
			$eMember->acceptInvite()
		) {
			return $this->getInvite($structure, $eMember);
		}

		if($eMember->isRevoked()) {
			return $this->getGiveBack($structure, $eMember);
		}

		return encode($eMember['role']['name']);

	}

	/**
	 * L'invitation existante à l'adresse du compte, ou le geste qui en crée une : l'acceptation rend son adhésion
	 * gardée à la personne (`structure\InviteLib::acceptMember()`).
	 */
	protected function getGiveBack(int $structure, \structure\Member $eMember): string {

		if($eMember['eInvite']->notEmpty()) {
			return \structure\InviteUi::getSentToMember($eMember['eInvite']);
		}

		return '<a href="/structure/invite:createMember?structure='.$structure.'&revoked='.$eMember['id'].'" class="btn btn-sm btn-outline-primary">'.s("Redonner un accès").'</a>';

	}

	/**
	 * L'invitation existante, que seule une personne qui administre ouvre, comme l'écran qui la porte.
	 * Celle d'une personne sans compte la désigne, pour que le compte qui l'accepte reprenne son passé.
	 */
	protected function getInvite(int $structure, \structure\Member $eMember): string {

		if(
			$eMember->notEmpty() and
			$eMember['eInvite']->notEmpty()
		) {
			return \structure\InviteUi::getSentToMember($eMember['eInvite']);
		}

		$url = '/structure/invite:createMember?structure='.$structure;

		if(
			$eMember->notEmpty() and
			$eMember->acceptInvite()
		) {
			$url .= '&member='.$eMember['id'];
		}

		return '<a href="'.$url.'" class="btn btn-sm btn-outline-primary">'.s("Inviter à créer un compte").'</a>';

	}

	public function create(\structure\Structure $eStructure, Teammate $eTeammate, Presence $ePresence, Contract $eContract): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/hr/teammate:doCreate', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('structure', $eStructure);

			// Une personne qui a déjà un compte arrive de sa ligne « Hors planning » ou de sa fiche, et `build()` refait
			// au POST la vérification qu'elle est membre de la structure, de tout statut, et pas déjà dans l'équipe.
			if($eTeammate['user']->notEmpty()) {

				$h .= $form->hidden('source', 'member');
				$h .= $form->hidden('user', $eTeammate['user']);
				$h .= $form->group(
					self::p('user')->label,
					$eTeammate['user']->getName(),
					['data-wrapper' => 'user']
				);

			} else {
				$h .= $form->hidden('source', 'ghost');
				$h .= $form->dynamicGroups(new \user\User(), ['firstName', 'lastName']);
			}

			$h .= $form->dynamicGroups($ePresence, ['from', 'to']);
			$h .= ContractUi::getFields($form, $eContract);

			$h .= $form->group(
				content: $form->submit($eTeammate['user']->notEmpty() ? s("Ajouter au planning") : s("Ajouter la personne"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-teammate-create',
			title: $eTeammate['user']->notEmpty() ? s("Ajouter au planning") : s("Ajouter une personne sans compte"),
			body: $h,
			close: 'reload'
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Teammate::model()->describer($property, [
			'user' => s("Personne"),
		]);

		return $d;

	}

}
?>
