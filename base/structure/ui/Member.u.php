<?php
namespace structure;

class MemberUi {

	public function __construct() {
		\Asset::css('structure', 'structure.css');
	}

	public function getMyStructures(\Collection $ccStructure): string {

		$h = '';

		foreach($ccStructure as $type => $cStructure) {

			$h .= '<h2>'.\structure\StructureUi::getText($cStructure->first(), 'my', $cStructure->count()).'</h2>';

			$h .= '<div class="member-structures util-cards util-cards-3">';

				foreach($cStructure as $eStructure) {

					$h .= new StructureUi()->getPanel($eStructure);

				}

				if($type === Structure::PRODUCER) {

					if($cStructure->count() <= 2) {

						// La démonstration n'a qu'une destination : le bloc entier est le lien, et il
						// prend le chevron plutôt qu'un bouton.
						$h .= '<a href="'.\structure\Structure::DEMO_URL.'/series?view=area" class="util-deck">';

							$h .= '<div class="util-deck-head">';

								$h .= \Asset::image('main', 'favicon.png', ['style' => 'width: 4rem; height: 4rem']);

								$h .= '<div class="util-deck-body">';
									$h .= '<div class="util-deck-title">'.s("Ferme de démonstration").'</div>';
									$h .= '<p>'.s("Testez {siteName} en explorant la démo !").'</p>';
								$h .= '</div>';

								$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';

							$h .= '</div>';

						$h .= '</a>';

					}

				}

			$h .= '</div>';

		}

		return $h;

	}

	public function getNoStructures(\user\User $eUser, Structure $eStructureCreate): string {

		$h = '';

		if($eUser->isProducer()) {

			$h .= '<h2>'.s("Ma structure").'</h2>';
			$h .= '<div class="util-block">';
				$h .= new StructureUi()->getCreate($eStructureCreate);
			$h .= '</div>';
			$h .= '<br/>';

		}

		if($eUser->isConsultant()) {

			$h .= '<h2>'.s("Ma communauté de producteurs").'</h2>';
			$h .= '<div class="util-info">';
				$h .= '<p>'.s("Vous accompagnez des producteurs et vous venez de vous inscrire sur {siteName}.<br/>Vous pouvez désormais créer un groupe de producteurs !").'</p>';
			$h .= '</div>';
			$h .= '<div class="util-buttons">';
				$h .= '<a href="/demarrer/communaute" class="util-button">';
					$h .= '<div>';
						$h .= '<h4>'.s("Créer une communauté de producteurs").'</h4>';
					$h .= '</div>';
					$h .= \Asset::icon('people-fill');
				$h .= '</a>';
			$h .= '</div>';

		}

		if($eUser->isTeacher()) {

			$h .= '<h2>'.s("Mon organisme de formation").'</h2>';
			$h .= '<div class="util-info">';
				$h .= '<p>'.s("Vous formez des étudiants en cultures spécialisées et vous venez de vous inscrire sur {siteName}.<br/>Vous pouvez désormais créer un groupe pour inclure {siteName} dans votre matériel pédagogique !").'</p>';
			$h .= '</div>';
			$h .= '<div class="util-buttons">';
				$h .= '<a href="/demarrer/education" class="util-button">';
					$h .= '<div>';
						$h .= '<h4>'.s("Créer un groupe de formation").'</h4>';
					$h .= '</div>';
					$h .= \Asset::icon('people-fill');
				$h .= '</a>';
			$h .= '</div>';

		}

		// Une fois, quel que soit le rôle : rejoindre une structure ne dépend pas de ce qu'on y fait
		$h .= '<h2>'.s("Vous avez un code d'invitation ?").'</h2>';
		$h .= new InviteUi()->join();

		return $h;

	}

	public function updatePrivate(\structure\Structure $eStructure, \user\User $eUser): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/member:doUpdatePrivate');

			$h .= $form->hidden('structure', $eStructure['id']);
			$h .= $form->hidden('user', $eUser['id']);

			$h .= $form->dynamicGroups($eUser, ['firstName', 'lastName'], [
				'firstName' => function($d) use($form) {
					$d->after =  \util\FormUi::info(s("Facultatif"));
				}
			]);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-member-update',
			title: \Asset::icon('snapchat').'  '.s("Modifier une personne sans compte"),
			body: $h,
			close: 'reload'
		);

	}

	/**
	 * Ce qu'une ligne de membre propose, dans la même roue crantée que la table des invitations
	 * juste au-dessus.
	 *
	 * ⚠️ Le geste existait déjà, il n'avait pas d'affordance : il fallait avoir découvert que le
	 * nom ouvre une fiche. Deux tables empilées sur le même écran, dont l'une offre ses actions et
	 * l'autre non, se lisent comme « ici on peut, là on ne peut pas ».
	 *
	 * ⚠️ **Pas de retrait sur sa propre ligne** : `MemberLib::delete()` refuse
	 * (`Member::deleteItself`), et un écran qui propose ce que l'action refuse fait faire le geste
	 * pour rien. C'est aussi ce qui protège la dernière personne qui administre sans avoir à la
	 * compter : seul un administrateur membre peut retirer quelqu'un (`canWrite()` →
	 * `canManage()` → `isOwner()`), et il ne peut pas se retirer lui-même.
	 *
	 * ⚠️ Cette table ne sert plus que les structures qui ne sont pas des groupes : ceux-ci ont la
	 * leur sur `/groupe/{id}/membres`, avec leurs sous-groupes et leurs gestes à eux.
	 */
	protected function getManageActions(Structure $eStructure, \structure\Member $eMember): string {

		// ⚠️ Une entrée refusée **garde sa place** et dit pourquoi, au lieu de disparaître : un
		// menu amputé se lit comme une panne, et le lecteur ouvre les lignes voisines pour
		// comprendre ce qui lui manque. C'est la forme de la page design system,
		// `dropdown-item disabled` avec `util-item-label` et `util-item-more`.
		$disabled = fn(string $label, string $reason) => '<a class="dropdown-item disabled"><div class="util-item-label">'.$label.'</div><div class="util-item-more">'.$reason.'</div></a>';

		$isSelf = $eMember->canSelf();

		$items = '';

		if($eMember->acceptUpdateRole()) {

			if($eMember->canUpdateRole()) {
				$items .= '<a href="/structure/member:update?id='.$eMember['id'].'" class="dropdown-item">'.s("Changer le rôle").'</a>';
			} else if($isSelf === FALSE) {
				$items .= $disabled(s("Changer le rôle"), s("Ce rôle ne peut pas être changé ici"));
			} else if($eStructure['admins'] <= 1) {
				$items .= $disabled(s("Changer le rôle"), s("Vous êtes le seul administrateur : invitez-en un autre, lui pourra changer votre rôle"));
			} else {
				$items .= $disabled(s("Changer le rôle"), s("Un autre administrateur peut le changer"));
			}

		}

		$items .= '<div class="dropdown-divider"></div>';

		$leave = self::getLeaveText($eStructure);

		if($eMember['status'] !== Member::IN) {
			$items .= $disabled($leave, s("Cette personne est déjà sortie de l'équipe"));
		} else if($isSelf) {
			$items .= $disabled($leave, $eStructure->isAmap() ? s("On ne se sort pas soi-même de l'administration") : s("On ne se sort pas soi-même de l'équipe"));
		} else {
			$items .= '<a data-ajax="/structure/member:doDelete" post-id="'.$eMember['id'].'" class="dropdown-item" data-confirm="'.self::getLeaveConfirm($eStructure, $eMember).'">'.$leave.'</a>';
		}

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';

		$h .= '<div class="dropdown-list">';

			$h .= '<div class="dropdown-title">'.$eMember['user']->getName().'</div>';

			$h .= '<a href="/structure/member:show?id='.$eMember['id'].'" class="dropdown-item">'.s("Voir la fiche").'</a>';

			$h .= $items;

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une structure de production sépare son équipe de ses rôles : on y retire tout rôle, et la personne garde sa
	 * place dans l'équipe.
	 */
	protected static function getLeaveText(Structure $eStructure): string {

		if($eStructure->isAmap()) {
			return s("Sortir de l'administration");
		} else if($eStructure->acceptProduction()) {
			return s("Retirer tout rôle");
		} else {
			return s("Sortir de l'équipe");
		}

	}

	/**
	 * Une structure de production propose ce retrait dans le panneau du rôle, que la personne soit dans l'équipe ou
	 * non : `isTeammate` dit laquelle des deux phrases est vraie.
	 */
	protected static function getLeaveConfirm(Structure $eStructure, Member $eMember): string {

		if($eStructure->isAmap()) {
			return s("Souhaitez-vous réellement retirer cette personne de l'administration ?");
		} else if($eStructure->acceptProduction() and $eMember['isTeammate']) {
			return s("Souhaitez-vous réellement retirer tout rôle à cette personne ? Elle reste dans l'équipe.");
		} else if($eStructure->acceptProduction()) {
			return s("Souhaitez-vous réellement retirer tout rôle à cette personne ?");
		} else {
			return s("Souhaitez-vous réellement retirer cet utilisateur de la ferme ?");
		}

	}

	/** `$producers` pose la colonne des producteurs d'une AMAP, que la page ne demande que si un administrateur en tient un. */
	public function getManage(Structure $eStructure, \Collection $ccMember, \Collection $cInvite, \Collection $cInviteFinished = new \Collection(), bool $producers = FALSE): string {

		$h = '';

		if($ccMember->notEmpty()) {

			if($cInvite->notEmpty()) {
				$h = '<h3>'.s("Les invitations en cours").'</h3>';
				$h .= new InviteUi()->getManage($cInvite);
			}

			// Les membres se titrent dès qu'une liste d'invitations partage l'écran avec eux, en cours
			// au-dessus ou terminées en dessous : sans titre, leur table se lirait comme une suite de
			// la liste voisine.
			if($cInvite->notEmpty() or $cInviteFinished->notEmpty()) {
				$h .= '<h3>'.s("Les membres {ofStructure}", ['ofStructure' => StructureUi::getText($eStructure, 'of')]).'</h3>';
			}

			$h .= '<div class="util-overflow-md">';
				$h .= '<table class="tr-even">';

					$h .= '<thead>';
						$h .= '<tr>';
							$h .= '<th colspan="2">'.s("Membre").'</th>';
							$h .= '<th>'.s("Ajouté").'</th>';
							if($producers) {
								$h .= '<th>'.s("Référent de").'</th>';
							}
							$h .= '<th></th>';
						$h .= '</tr>';
					$h .= '</thead>';

						foreach($ccMember as $cMember) {

							// Le rôle se dit une fois en tête de son groupe, et non sur chacune des
							// lignes qu'il coiffe : c'est ce qui range le tableau, et le répéter sous
							// chaque nom disait la même chose autant de fois qu'il y a de membres.
							if($eStructure->isAmap() === FALSE) {

								$eRole = $cMember->first()['role'];

								$h .= '<tbody>';
									$h .= '<tr class="tr-title">';
										$h .= '<td colspan="10">'.encode($eRole['name']).'</td>';
									$h .= '</tr>';
								$h .= '</tbody>';

							}

							$h .= '<tbody>';

								foreach($cMember as $eMember) {

									$h .= '<tr>';

										$h .= '<td class="td-min-content">';
											$h .= \user\UserUi::getVignette($eMember['user'], '2rem');
										$h .= '</td>';

										$h .= '<td>';

											$h .= '<a href="/structure/member:show?id='.$eMember['id'].'" class="util-button">'.$eMember['user']->getName().'</a>';

										$h .= '</td>';

										$h .= '<td>';
											$h .= '<span class="color-muted font-sm">'.\util\DateUi::numeric($eMember['createdAt'], \util\DateUi::DATE).'</span>';
										$h .= '</td>';

										if($producers) {

											$producers = StructureUi::getObserverEntries('getAmapMemberProducers', $eMember);

											if($producers) {
												$h .= '<td><div class="util-vignette">'.implode('', $producers).'</div></td>';
											} else {
												$h .= '<td><span class="util-empty">—</span></td>';
											}

										}

										$h .= '<td class="text-end">';
											$h .= $this->getManageActions($eStructure, $eMember);
										$h .= '</td>';

									$h .= '</tr>';

								}

							$h .= '</tbody>';

						}

				$h .= '</table>';
			$h .= '</div>';

		}

		if($cInviteFinished->notEmpty()) {
			$h .= '<h3 class="mt-2">'.s("Les invitations terminées").'</h3>';
			$h .= new InviteUi()->getManage($cInviteFinished);
		}

		return $h;

	}

	/**
	 * La tête de la fiche. Un groupe y pose les gestes que la fiche d'une ferme garde sous l'identité
	 * (`getUser()`) : la bande d'un écran de groupe les range en barre à côté du nom.
	 */
	public function getUserTitle(\structure\Member $eMember, \structure\Structure $eStructure): string {

		$tools = ($eStructure->isCommunity() and self::hasUserGestures($eMember))
			? $this->getUserGestures($eStructure, $eMember, 'btn-outline-secondary')
			: '';

		return \main\DesignUi::getPageHeader(
			$eMember['user']->getName(),
			back: StructureUi::urlMembers($eStructure),
			logo: \user\UserUi::getVignette($eMember['user'], '6rem'),
			tools: $tools,
			backLabel: ($eStructure->isCommunity() or $eStructure->isAmap()) ? HeaderUi::getTeamCategories($eStructure)[Member::TEAM] : ''
		);

	}

	/**
	 * Les gestes de la fiche ne valent que pour une personne qui a encore un rôle : un compte fermé
	 * et une personne sortie sans compte à inviter ont les leurs, sous l'identité (`getUser()`).
	 *
	 * La condition est le complément des deux premières branches de `getUser()`, le compte fermé et
	 * la sortie sans invitation possible : l'une ne change pas sans l'autre.
	 */
	private static function hasUserGestures(\structure\Member $eMember): bool {

		return (
			$eMember['user']['status'] !== \user\User::CLOSED and
			(
				$eMember['status'] !== Member::OUT or
				$eMember->acceptInvite()
			)
		);

	}

	/**
	 * Sortir du planning termine la période en cours et efface les périodes à venir (`hr\TeammateLib::leave()`).
	 */
	protected function getLeavePlanning(\structure\Member $eMember): string {

		$eTeammate = $eMember['eTeammate'];

		if(
			$eTeammate->empty() or
			$eTeammate->assertLeave() === FALSE
		) {
			return '';
		}

		$confirm = s("{value} ne sera plus au planning à partir d'aujourd'hui. Une période en cours s'arrête la veille et une période à venir est effacée.", encode($eMember['user']->getName()));

		return '<a data-ajax="/hr/teammate:doLeave" post-id="'.$eTeammate['id'].'" class="btn btn-outline-primary" data-confirm="'.$confirm.'">'.s("Sortir du planning").'</a> ';

	}

	/**
	 * Les gestes que les roues de l'onglet Personnes portaient se font sur la fiche, depuis que le nom
	 * de chaque ligne y mène (décidé le 20/10). Aucun ne fait avancer la fiche plus qu'un
	 * autre : ils sont dessinés en `$button`, sauf « Sortir du planning », qui garde le dessin de
	 * `getLeavePlanning()`, et une invitation déjà envoyée, qui se dit par sa marque. Une ferme les
	 * pose sous l'identité, un groupe dans la tête de la fiche (`getUserTitle()`), que sa bande range
	 * en barre.
	 */
	protected function getUserGestures(Structure $eStructure, \structure\Member $eMember, string $button): string {

		$h = '';

		if($eMember->assertUpdateRole()) {

			$h .= '<a href="/structure/member:update?id='.$eMember['id'].'" class="btn '.$button.'">';
				$h .= s("Changer le rôle");
			$h .= '</a> ';

		}

		$h .= $this->getLeavePlanning($eMember);

		if($eMember->acceptInvite()) {

			if($eMember['eInvite']->notEmpty()) {
				$h .= InviteUi::getSentToMember($eMember['eInvite']).' ';
			} else {
				$h .= '<a href="/structure/invite:createMember?structure='.$eMember['structure']['id'].'&member='.$eMember['id'].'" class="btn '.$button.'">';
					$h .= s("Inviter à créer un compte");
				$h .= '</a> ';
			}

		}

		// Le rôle d'une personne sans compte ne se retire pas : on la renomme, et hors de l'équipe on la supprime
		// (`MemberLib::deleteGhostUser()` refuse un coéquipier).
		if(
			$eMember['role']->isGhost() and
			$eStructure->acceptGhost() and
			$eStructure->canManage()
		) {

			$h .= '<a href="/structure/member:updatePrivate?structure='.$eStructure['id'].'&user='.$eMember['user']['id'].'" class="btn '.$button.'">';
				$h .= s("Modifier la personne sans compte");
			$h .= '</a> ';

			if($eMember['isTeammate'] === FALSE) {
				$h .= '<a data-ajax="/structure/member:doDeletePrivate" post-structure="'.$eStructure['id'].'" post-user="'.$eMember['user']['id'].'" class="btn '.$button.'" data-confirm="'.s("Cette action est irréversible, voulez-vous réellement supprimer cette personne sans compte ?").'">';
					$h .= s("Supprimer la personne sans compte");
				$h .= '</a>';
			}

		}

		// ⚠️ Jamais sur sa propre fiche : `MemberLib::delete()` refuse le retrait de
		// soi-même (`Member::deleteItself`), et le bouton menait donc à un message
		// d'erreur. Se retirer d'un groupe a son propre chemin, `MemberLib::leave()`,
		// qui refuse au dernier administrateur au lieu de le laisser partir.
		if(
			$eMember->canSelf() === FALSE and
			$eMember['role']->isGhost() === FALSE
		) {

			if($eStructure->isCommunity()) {

				// Sortir d'un groupe détache des fermes et vide des sous-groupes : le panneau
				// les chiffre. Sortir d'une équipe de ferme ne détache rien, la confirmation
				// du navigateur y suffit.
				$h .= '<a href="/structure/community:breakMember?id='.$eStructure['id'].'&member='.$eMember['id'].'" class="btn '.$button.'">';
					$h .= s("Retirer du groupe");
				$h .= '</a>';

			} else if($eStructure->acceptProduction() === FALSE) {

				// Une structure de production retire tout rôle depuis le panneau qu'ouvre « Changer le rôle »,
				// à côté de son enregistrement (décidé le 20/10).
				$h .= '<a data-ajax="/structure/member:doDelete" post-id="'.$eMember['id'].'" class="btn '.$button.'" data-confirm="'.self::getLeaveConfirm($eStructure, $eMember).'">';
					$h .= self::getLeaveText($eStructure);
				$h .= '</a>';
			}

		}

		return $h;

	}

	public function getUser(Structure $eStructure, \structure\Member $eMember, \Collection $cPresence, \Collection $cAbsence): string {

		$h = '<div class="util-block">';
		$h .= '<dl class="util-presentation util-presentation-2">';

			// Un compte fermé n'a plus d'adresse, une personne sans compte n'en a jamais eu.
			if(
				$eMember['user']['status'] !== \user\User::CLOSED and
				$eMember->acceptInvite() === FALSE
			) {
				$h .= '<dt>'.s("Adresse e-mail").'</dt>';
				$h .= '<dd>'.($eMember['user']['visibility'] === \user\User::PUBLIC ? encode($eMember['user']['email']) : '<i>'.s("Non communiquée").'</i>').'</dd>';
			}
			$h .= '<dt>'.s("Depuis le").'</dt>';
			$h .= '<dd>'.\util\DateUi::numeric($eMember['createdAt'], \util\DateUi::DATE).'</dd>';

			$h .= '<dt>'.s("Rôle").'</dt>';
			$h .= '<dd>'.($eMember['role']->empty() ? s("Aucun") : encode($eMember['role']['name'])).'</dd>';

			if($eMember['inviteKey'] !== NULL) {
				$h .= '<dt>'.s("Méthode d'invitation").'</dt>';
				$h .= '<dd>'.encode($eMember['inviteKey']).'</dd>';
			}

		$h .= '</dl>';
		$h .= '</div>';

		// Un compte fermé n'a plus d'accès à donner ni à retirer : il ne reste qu'à sortir la personne du planning.
		if($eMember['user']['status'] === \user\User::CLOSED) {

			$h .= '<div class="util-info">'.s("Ce compte est fermé.").'</div>';

			$leave = $this->getLeavePlanning($eMember);

			if($leave !== '') {
				$h .= '<div>'.$leave.'</div>';
			}

		// Une personne sans compte est rattachée sortie (`MemberLib::createGhostUser()`) : elle n'a pas de rôle
		// à perdre, et sa fiche garde ses périodes.
		} else if(
			$eMember['status'] === Member::OUT and
			$eMember->acceptInvite() === FALSE
		) {

			if($eStructure->acceptProduction()) {

				// Retirer tout rôle laisse la personne au planning (`getLeaveConfirm()`) : c'est d'ici qu'elle en sort,
				// et d'ici qu'elle retrouve un accès, par une invitation qui lui rend son adhésion.
				$h .= '<div>';

					if($eMember['eInvite']->notEmpty()) {
						$h .= InviteUi::getSentToMember($eMember['eInvite']).' ';
					} else {
						$h .= '<a href="/structure/invite:createMember?structure='.$eStructure['id'].'&revoked='.$eMember['id'].'" class="btn btn-outline-primary">';
							$h .= s("Redonner un accès");
						$h .= '</a> ';
					}

					$h .= $this->getLeavePlanning($eMember);

				$h .= '</div>';

			} else {
				$h .= '<div class="util-info">'.s("Cet utilisateur a été sorti de l'équipe de la ferme et ne peut donc plus accéder ni être affecté aux interventions sur la ferme.").'</div>';
			}

		// Un groupe les pose dans la tête de la fiche (`getUserTitle()`).
		} else if($eStructure->isCommunity() === FALSE) {
			$h .= '<div>'.$this->getUserGestures($eStructure, $eMember, 'btn-outline-primary').'</div>';
		}

		if($eStructure->assertProduction()) {
			$h .= '<br/>';
			$h .= new \hr\PresenceUi()->show($eMember, $cPresence);
		}

		if($eStructure->acceptHr()) {
			$h .= '<br/>';
			$h .= new \hr\AbsenceUi()->show($eMember, $cAbsence);
		}

		return $h;

	}

	/**
	 * Le rôle et le rangement d'une personne, dans le même formulaire.
	 *
	 * ⚠️ Les deux étaient deux panneaux et deux routes, ouverts par deux entrées voisines du même
	 * menu. Ils règlent pourtant la même chose au même moment — ce que cette personne est dans ce
	 * groupe — et changer l'un sans l'autre demandait de rouvrir la roue crantée. Un formulaire,
	 * un enregistrement.
	 *
	 * ⚠️ Le rangement ne se rend que là où il existe : une ferme n'a pas de sous-groupes, et un
	 * groupe qui a éteint la fonctionnalité n'en a plus. `$cSection` arrive alors vide et le champ
	 * disparaît, sans que ce panneau ait à connaître ni le type de la structure ni ses
	 * fonctionnalités.
	 */
	public function update(\structure\Member $eMember, \structure\Structure $eStructure = new \structure\Structure(), \Collection $cSection = new \Collection()): \Panel {

		\Asset::js('structure', 'memberAi.js');

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/member:doUpdate');

			$h .= $form->hidden('id', $eMember['id']);

			$h .= $form->dynamicGroup($eMember, 'role');

			$h .= self::getSectionsGroup($form, $eStructure, $eMember, $cSection);

			// Seule une structure de production retire tout rôle ici, les autres le font par la roue de leur liste.
			// Le panneau ne s'ouvre ni sur soi ni sur une personne sans compte (`canUpdateRole()`), deux retraits que
			// `MemberLib::delete()` refuse, et c'est ce qui épargne le dernier administrateur.
			if(
				$eStructure->acceptProduction() and
				$eMember['status'] === Member::IN
			) {

				$h .= $form->group(
					content: '<div class="flex-justify-space-between">'.$form->submit(s("Enregistrer")).'<a data-ajax="/structure/member:doDelete" post-id="'.$eMember['id'].'" class="btn btn-danger" data-confirm="'.self::getLeaveConfirm($eStructure, $eMember).'">'.self::getLeaveText($eStructure).'</a></div>'
				);

			} else {

				$h .= $form->group(
					content: $form->submit(s("Enregistrer"))
				);

			}

		$h .= $form->close();

		return new \Panel(
			id: 'panel-member-update',
			title: $eMember['user']->getName(),
			subTitle: $cSection->empty() ? s("Son rôle") : s("Son rôle et son rangement"),
			body: $h,
			close: 'reload'
		);

	}

	/**
	 * Les sous-groupes où ranger cette personne, en cases à cocher.
	 *
	 * ⚠️ Plusieurs à la fois : `Section.users` porte des listes d'ids, et `getByUser()` comme
	 * `dissociateUser()` supposent tous deux qu'une personne peut être rangée dans plusieurs. Un
	 * choix unique aurait fermé cette porte sans que le modèle la referme.
	 *
	 * ⚠️ `FormUi::checkboxes()` et non des `<label><input type="checkbox">` montés à la main :
	 * le cadriciel rend le groupe avec son cadre, ses colonnes et son « tout cocher », et le
	 * balisage reste celui du dépôt le jour où il change.
	 */
	protected static function getSectionsGroup(\util\FormUi $form, \structure\Structure $eStructure, \structure\Member $eMember, \Collection $cSection): string {

		if($cSection->empty()) {
			return '';
		}

		$values = [];

		foreach($cSection as $eSection) {

			// Un sous-groupe clos le dit : sans mention il se lit comme courant, et on y range
			// alors quelqu'un dans un ensemble qui ne sert plus.
			$values[$eSection['id']] = encode($eSection['name'])
				.($eSection->isClosed() ? ' '.StructureCommunityUi::getSectionClosed() : '');

		}

		// ⚠️ La structure vient en argument et ne se lit pas sur l'adhésion : celle que `Member`
		// délègue arrive en sélection partielle, sans `type`, et `getClass()` y lève. Le même piège
		// que `producerType` sur une structure déléguée.
		return $form->group(
			\structure\StructureUi::getClass($eStructure)::getText($eStructure, 'section', 2),
			$form->checkboxes('sections[]', $values, ($eMember['cSection'] ?? new \Collection())->getIds()),
			['data-wrapper' => 'sections', 'onrender' => 'StructureMember.watchSections(this)']
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Member::model()->describer($property, [
			'email' => s("Adresse e-mail"),
			'user' => s("Utilisateur"),
			'role' => s("Rôle"),
		]);

		switch($property) {

			case 'email' :
				$d->field = 'email';
				break;

			case 'viewPlanningWeek' :
				$d->values = [
					Member::CARDS => s("Cartes par intervention"),
					Member::COLUMNS => s("Colonnes fait et à faire"),
					Member::GRID => s("Tableau complet"),
				];
				break;

			case 'viewPlanningDensity' :
				$d->values = [
					Member::DENSE => s("Plus dense"),
					Member::NORMAL => s("Normal"),
					Member::SPACIOUS => s("Moins dense"),
				];
				break;

			case 'viewWeatherModel' :
				$d->values = [
					Member::ICON => s("ICON (DWD)"),
					Member::METEOFRANCE => s("Météo-France"),
					Member::UKMO => s("UKMO (Met Office)"),
					Member::ECMWF => s("ECMWF"),
				];
				break;

			case 'role' :
				$d->labelAfter = fn(Member $eMember) => $eMember['structure']->acceptRoles()
					? \util\FormUi::info(s("Les rôles proposés sur cette page peuvent être personnalisés pour votre organisation.")).'<a href="'.StructureUi::urlRole($eMember['structure']).'" target="_blank" style="margin: 0.5rem 0" class="btn btn-sm btn-outline-primary">'.s("En savoir plus sur les rôles").'</a>'
					: '';
				$d->field = 'radio';
				$d->required = TRUE;
				$d->attributes = fn(\util\FormUi $form, Member $e) => [
					'callbackRadioAttributes' => fn($option, $key) => ['data-staff' => (int)$e['cRole'][$key]->isStaff()]
				];
				$d->values = fn(Member $e) => $e['cRole']->makeArray(function(Role $eRole, &$key) {

					$key = $eRole['id'];
					$label = '<h4 class="mb-0">'.encode($eRole['name']).'</h4>';

					if($eRole['admin'] === FALSE) {

						$label .= '<div class="color-muted mt-1"><i>'.s("Accès autorisé à :").'</i></div>';
						$label .= '<ul class="color-muted">';

							foreach(new RoleUi()->getProperties() as [$title, $properties]) {

								$list = [];

								foreach($properties as $property) {

									switch($property) {

										case 'planning' :
											if($eRole['planning'] === Role::LIMITED) {
												$list[] = s("Planning avec restrictions");
											}
											if($eRole['planning'] === Role::FULL) {
												$list[] = s("Planning intégral");
											}
											break;

										case 'accounting' :
											if($eRole['accounting'] === Role::LIMITED) {
												$list[] = s("Comptabilité avec restrictions");
											}
											if($eRole['accounting'] === Role::FULL) {
												$list[] = s("Comptabilité intégrale");
											}
											break;

										default :
											if($eRole[$property]) {
												$list[] = RoleUi::p($property)->label;
											}
											break;


									}

								}

								if($list) {
									$label .= '<li>';
										$label .= $title.' '.\Asset::icon('arrow-right-short').' '.implode(', ', $list);
									$label .= '</li>';
								}

							}

						$label .= '</ul>';

					}

					return $label;

				}) ?? $e->expects(['cRole']);
				break;

		}

		return $d;

	}

}
