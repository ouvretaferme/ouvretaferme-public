<?php
namespace learning;

/**
 * Les encadrants d'une formation : leur onglet dans l'éditeur, et leurs panneaux.
 */
class StaffUi {

	public function __construct() {
		\Asset::js('learning', 'learning.js');
	}

	/**
	 * L'onglet « Encadrants » de l'éditeur (retour du 30/09, point 5, qui les sort de la
	 * roue), sur la forme des dates, sa voisine : titre, compteur et ajout à droite, puis un deck par
	 * encadrant. Le deck n'a qu'une destination, le panneau où l'on modifie et où l'on retire : le
	 * bloc entier est donc le lien, sans roue. Sous les decks, la ligne « Contact » de la fiche, telle
	 * que la fiche l'affiche, dit en plus d'où vient ce contact quand la formation n'en désigne pas.
	 *
	 * Sans encadrant, l'onglet appelle un geste : qui écrit une formation l'encadre le plus souvent
	 * elle-même, et « M'ajouter comme encadrant » ouvre le panneau d'ajout sur son compte (verdict de
	 * du 08/10). Rien ne s'écrit avant l'envoi du panneau, où le rôle reste à saisir.
	 */
	public function getEditionSection(Course $eCourse, \Collection $cStaff): string {

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Encadrants").($cStaff->empty() ? '' : ' <span class="util-counter">'.$cStaff->count().'</span>').'</h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/staff:create?course='.$eCourse['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter un encadrant").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		if($cStaff->empty()) {

			$h .= '<div class="util-block-cta">';
				$h .= '<h3>'.s("Aucun encadrant").'</h3>';
				$h .= '<div class="util-buttons">';
					$h .= '<a href="/learning/staff:create?course='.$eCourse['id'].'&origin=self" class="btn btn-primary">'.\Asset::icon('person-plus').' '.s("M'ajouter comme encadrant").'</a>';
				$h .= '</div>';
			$h .= '</div>';

		}

		foreach($cStaff as $eStaff) {
			$h .= $this->getDeck($eCourse, $eStaff);
		}

		$contact = self::getContact($eCourse, $cStaff, TRUE);

		if($contact !== '') {
			$h .= CourseUi::getFicheTable([s("Contact") => $contact]);
		}

		return $h;

	}

	/**
	 * La valeur de la ligne « Contact » d'une formation, la même sur sa fiche et dans l'onglet de son
	 * éditeur : l'encadrant qu'elle désigne, sinon le contact de son réseau, tous deux posés par la
	 * page (`eSupervisorContact`, par `SupervisorLib::getContactByCourse()`), sinon l'adresse légale du
	 * groupe auteur. Une formation de l'équipe sans contact n'a pas de ligne. Avec `$withSource`,
	 * l'onglet marque ce qui ne vient pas de la formation : la carte du contact désigné porte déjà
	 * « Contact », celui du réseau peut ne pas encadrer la formation et n'a alors pas de carte.
	 */
	public static function getContact(Course $eCourse, \Collection $cStaff, bool $withSource = FALSE): string {

		$eCourse->expects(['community', 'eSupervisorContact']);

		$eSupervisorContact = $eCourse['eSupervisorContact'];

		if($eSupervisorContact->notEmpty()) {

			$h = encode($eSupervisorContact['name']).', <a href="mailto:'.encode($eSupervisorContact['email']).'">'.encode($eSupervisorContact['email']).'</a>';

			if(
				$withSource and
				$cStaff->contains(fn(Staff $eStaff) => $eStaff['contact']) === FALSE
			) {
				$h .= ' <span class="badge badge-info">'.SupervisorUi::getCommunityTexts($eCourse['community'])['contact'].'</span>';
			}

			return $h;

		}

		if($eCourse['community']->empty()) {
			return '';
		}

		$h = '<a href="mailto:'.encode($eCourse['community']['legalEmail']).'">'.encode($eCourse['community']['legalEmail']).'</a>';

		if($withSource) {
			$h .= ' <span class="badge badge-info">'.SupervisorUi::getCommunityTexts($eCourse['community'])['address'].'</span>';
		}

		return $h;

	}

	/**
	 * La marque « Contact » dit que la formation a désigné cette personne, et rien d'autre : le contact
	 * du réseau qui encadre la formation sans y être désigné ne la porte pas. « Désactivé » dit que la
	 * personne n'est plus proposée ailleurs ; elle reste sur cette formation.
	 */
	protected function getDeck(Course $eCourse, Staff $eStaff): string {

		$badges = '';

		if($eStaff['contact']) {
			$badges .= '<span class="badge badge-info">'.s("Contact").'</span>';
		}

		if($eStaff['supervisor']['handicap']) {
			$badges .= '<span class="badge badge-info">'.SupervisorUi::p('handicap')->label.'</span>';
		}

		if($eStaff['supervisor']['status'] === Supervisor::INACTIVE) {
			$badges .= '<span class="badge badge-neutral">'.s("Désactivé").'</span>';
		}

		$h = '<a href="/learning/staff:update?id='.$eStaff['id'].'" class="util-deck mb-1">';
			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-icon util-icon-round '.ThemeUi::getIconClass($eCourse['theme']).'">'.\Asset::icon('person').'</div>';
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.encode($eStaff['supervisor']['name']).'</div>';
					$h .= '<p>'.encode($eStaff['role']).'</p>';
					if($eStaff['supervisor']['description'] !== NULL) {
						$h .= '<p class="util-annotation">'.encode($eStaff['supervisor']['description']).'</p>';
					}
				$h .= '</div>';
				if($badges !== '') {
					$h .= '<div class="util-deck-aside">'.$badges.'</div>';
				}
				$h .= '<span class="util-deck-arrow">'.\Asset::icon('chevron-right').'</span>';
			$h .= '</div>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * L'ajout d'un encadrant, par deux chemins exclusifs : choisir une personne que le réseau connait
	 * déjà (`cSupervisor`, posé sur la ligne par la page : les personnes actives qui n'encadrent pas
	 * encore la formation), ou en saisir une nouvelle, qui nait dans le réseau de la formation. Quand
	 * il n'y a personne à choisir, le panneau ne porte que le second, sans onglets.
	 *
	 * Chaque chemin a son champ `source`, que l'action lit, et son « Rôle dans la formation » :
	 * `Lime.Tab` désactive le `fieldset` du chemin non retenu, un seul des deux est donc posté. Sur le
	 * second, ce rôle devient aussi le rôle habituel de la personne. « Contact de la formation » vaut
	 * pour les deux et se lit une fois, sous eux.
	 *
	 * Quand la ligne arrive avec sa personne (« M'ajouter comme encadrant »), le premier chemin porte
	 * son rôle habituel comme `LearningStaff.proposeRole()` l'aurait posé au choix de cette personne,
	 * `data-proposed` compris : choisir quelqu'un d'autre le remplace, le corriger à la main le garde.
	 * Le second chemin ne le reçoit pas, il saisit une autre personne.
	 */
	public function create(Staff $eStaff, Supervisor $eSupervisor): \Panel {

		$eStaff->expects(['cSupervisor']);

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/staff:doCreate');

			$h .= $form->hidden('course', $eStaff['course']);

			if($eStaff['cSupervisor']->empty()) {

				$h .= $this->getCreateNew($form, $eStaff, $eSupervisor);

			} else {

				$h .= '<div class="tabs tabs-h tabs-exclusive">';
					$h .= '<div class="tabs-item tabs-form">';
						$h .= '<a class="tab-item selected" data-tab="pick" onclick="Lime.Tab.select(this)">'.SupervisorUi::getCommunityTexts($eStaff['course']['community'])['pick'].'</a>';
						$h .= '<a class="tab-item" data-tab="new" onclick="Lime.Tab.select(this)">'.s("Nouvel encadrant").'</a>';
					$h .= '</div>';
					$h .= '<div class="tab-panel selected" data-tab="pick">';
						$h .= '<fieldset>';
							$h .= $form->hidden('source', 'pick');
							$h .= $form->dynamicGroups($eStaff, ['supervisor*', 'role*'], ['role*' => function(\PropertyDescriber $d) use($eStaff) {

								if($eStaff['supervisor']->notEmpty() and $eStaff['supervisor']['role'] !== NULL) {
									$d->default = $eStaff['supervisor']['role'];
									$d->attributes['data-proposed'] = $eStaff['supervisor']['role'];
								}

							}]);
						$h .= '</fieldset>';
					$h .= '</div>';
					$h .= '<div class="tab-panel" data-tab="new">';
						$h .= '<fieldset disabled>';
							$h .= $this->getCreateNew($form, $eStaff, $eSupervisor);
						$h .= '</fieldset>';
					$h .= '</div>';
				$h .= '</div>';

			}

			$h .= $form->dynamicGroups($eStaff, ['contact']);

			$h .= $form->group(
				content: $form->submit(s("Ajouter l'encadrant"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-staff',
			title: s("Ajouter un encadrant"),
			body: $h
		);

	}

	protected function getCreateNew(\util\FormUi $form, Staff $eStaff, Supervisor $eSupervisor): string {

		$h = $form->hidden('source', 'new');
		$h .= $form->dynamicGroups($eSupervisor, ['name*']);
		$h .= $form->dynamicGroups($eStaff, ['role*']);
		$h .= $form->dynamicGroups($eSupervisor, ['description', 'email', 'handicap']);

		return $h;

	}

	/**
	 * Ce qu'une formation règle d'un encadrant : le rôle qu'il y tient, et s'il en est le contact.
	 * Son nom, son adresse, sa présentation et la marque « Référent handicap » sont ceux de la
	 * personne, les mêmes dans toutes ses formations : ils se corrigent sur sa fiche, que « Modifier la
	 * fiche » ouvre.
	 */
	public function update(Staff $eStaff): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/staff:doUpdate');

			$h .= $form->hidden('id', $eStaff);

			$supervisor = '<b>'.encode($eStaff['supervisor']['name']).'</b>';

			if($eStaff['supervisor']->assertUpdate()) {
				$supervisor .= '<a href="/learning/supervisor:update?id='.$eStaff['supervisor']['id'].'" class="btn btn-sm btn-outline-primary ml-1">'.s("Modifier la fiche").'</a>';
			}

			$h .= $form->group(self::p('supervisor')->label, $supervisor);
			$h .= $form->dynamicGroups($eStaff, ['role*', 'contact']);

			$h .= $form->group(
				content: '<div class="flex-buttons">'.$form->submit(s("Enregistrer")).'<a data-ajax="/learning/staff:doDelete" post-id="'.$eStaff['id'].'" class="btn btn-outline-primary" data-confirm="'.s("Retirer cet encadrant de la formation ?").'" data-waiter>'.s("Retirer de la formation").'</a></div>'
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-staff',
			title: s("Modifier un encadrant"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Staff::model()->describer($property, [
			'supervisor' => s("Encadrant"),
			'role' => s("Rôle dans la formation"),
			'contact' => s("Contact de la formation"),
		]);

		switch($property) {

			// Les personnes à proposer sont posées sur la ligne par la page (`cSupervisor`). Chaque option
			// porte le rôle habituel de sa personne quand elle en a un, que `LearningStaff.proposeRole()`
			// reporte dans le rôle de la formation. La personne de la ligne est choisie d'entrée : aucune
			// depuis « Ajouter un encadrant », la fiche du compte depuis « M'ajouter comme encadrant ».
			case 'supervisor' :
				$d->field = function(\util\FormUi $form, Staff $e) {

					$e->expects(['supervisor', 'cSupervisor']);

					return $form->select(
						'supervisor',
						$e['cSupervisor']->makeArray(fn(Supervisor $eSupervisor) => [
							'value' => $eSupervisor['id'],
							'label' => $eSupervisor['name'],
							'attributes' => ($eSupervisor['role'] === NULL) ? [] : ['data-role' => $eSupervisor['role']]
						]),
						$e['supervisor'],
						attributes: ['onchange' => 'LearningStaff.proposeRole(this)']
					);

				};
				break;

			case 'role' :
				$d->placeholder = s("Ex. : autrice, relectrice, formateur");
				break;

			// Ce que la désignation change se lit sous le libellé : sous le champ ne va qu'une consigne
			// de saisie (retours du 01/10, points 20 et 21, qui passent aussi les cases en
			// oui / non).
			case 'contact' :
				$d->field = 'yesNo';
				$d->labelAfter = fn(Staff $e) => \util\FormUi::info(SupervisorUi::getCommunityTexts($e['course']['community'])['courseContactInfo']);
				break;

		}

		return $d;

	}

}
?>
