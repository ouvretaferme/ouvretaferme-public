<?php
namespace learning;

/**
 * Les encadrants d'un réseau, d'un groupe de formation ou de l'équipe Ouvretaferme : leur liste,
 * où on les ajoute, les désactive et les supprime, et le panneau de leur fiche. Leurs champs
 * servent aussi au panneau d'une formation, qui crée la personne en même temps que sa ligne
 * (`StaffUi::create()`).
 */
class SupervisorUi {

	public function __construct() {
		\Asset::css('learning', 'learning.css');
	}

	public static function urlCommunity(\structure\Structure $eStructure): string {
		return '/groupe/'.$eStructure['id'].'/formations/encadrants';
	}

	public static function urlAdmin(): string {
		return '/learning/admin/encadrants';
	}

	/**
	 * Le titre de la liste d'un groupe : le retour vers les formations, le compte, l'ajout à droite.
	 */
	public function getCommunityTitle(\structure\Structure $eStructure, \Collection $cSupervisor): string {

		return \main\DesignUi::getPageHeader(
			s("Encadrants").self::getCounter($cSupervisor),
			back: '/groupe/'.$eStructure['id'].'/formations',
			create: '<a href="/learning/supervisor:create?community='.$eStructure['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').' '.s("Ajouter un encadrant").'</a>',
			backLabel: s("Formations")
		);

	}

	/**
	 * Le titre de la liste de l'équipe, sous les onglets de son administration, sur la forme des
	 * thématiques ses voisines (`ThemeUi::getList()`).
	 */
	public function getAdminTitle(\Collection $cSupervisor): string {

		$h = '<div class="util-title">';
			$h .= '<h3>'.s("Encadrants").self::getCounter($cSupervisor).'</h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/supervisor:create" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter un encadrant").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	protected static function getCounter(\Collection $cSupervisor): string {

		if($cSupervisor->empty()) {
			return '';
		}

		return ' <span class="util-counter">'.$cSupervisor->count().'</span>';

	}

	/**
	 * Tous les encadrants dans une seule liste, désactivés compris : un réseau en a peu, ils ne se
	 * rangent pas en onglets. Chacun porte son nombre de formations (`SupervisorLib::fillCourses()`).
	 */
	public function getList(\Collection $cSupervisor): string {

		if($cSupervisor->empty()) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucun encadrant").'</h3>';
			$h .= '</div>';

			return $h;

		}

		$h = '<div class="util-table util-table-hover learning-table-supervisors">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Encadrant").'</div>';
				$h .= '<div class="hide-xs-down">'.self::p('email')->label.'</div>';
				$h .= '<div class="text-end">'.s("Formations").'</div>';
				$h .= '<div></div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($cSupervisor as $eSupervisor) {
				$h .= $this->getRow($eSupervisor);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne, que le changement de statut redessine seule (`supervisor:doUpdateStatus`). Désactivée,
	 * elle se lit en gris atténué ; l'interrupteur et la roue gardent leur couleur. Sous 576 px la
	 * colonne de l'adresse tombe et l'adresse se lit sous le nom.
	 */
	public function getRow(Supervisor $eSupervisor): string {

		$eSupervisor->expects(['courses']);

		$isActive = ($eSupervisor['status'] === Supervisor::ACTIVE);
		$email = ($eSupervisor['email'] === NULL) ? '' : self::getEmail($eSupervisor['email']);

		$h = '<div class="util-table-row'.($isActive ? '' : ' color-muted').'" id="supervisor-row-'.$eSupervisor['id'].'">';

			$h .= '<div>';
				$h .= '<b>'.encode($eSupervisor['name']).'</b>';
				if($eSupervisor['contact']) {
					$h .= ' <span class="badge badge-info">'.s("Contact").'</span>';
				}
				if($eSupervisor['handicap']) {
					$h .= ' <span class="badge badge-info">'.self::p('handicap')->label.'</span>';
				}
				if($eSupervisor['role'] !== NULL) {
					$h .= '<div>'.encode($eSupervisor['role']).'</div>';
				}
				if($email !== '') {
					$h .= '<div class="hide-sm-up">'.$email.'</div>';
				}
				if($eSupervisor['description'] !== NULL) {
					$h .= '<p class="util-annotation">'.encode($eSupervisor['description']).'</p>';
				}
			$h .= '</div>';

			$h .= '<div class="hide-xs-down">'.$email.'</div>';
			$h .= '<div class="text-end">'.$eSupervisor['courses'].'</div>';

			$h .= '<div class="text-end">';
				$h .= \util\TextUi::switch([
					'id' => 'supervisor-switch-'.$eSupervisor['id'],
					'data-ajax' => '/learning/supervisor:doUpdateStatus',
					'post-id' => $eSupervisor['id'],
					'post-status' => $isActive ? Supervisor::INACTIVE : Supervisor::ACTIVE
				], $isActive);
			$h .= '</div>';

			$h .= '<div class="text-end learning-row-actions">';
				$items = '<a href="/learning/supervisor:update?id='.$eSupervisor['id'].'" class="dropdown-item">'.s("Modifier l'encadrant").'</a>';
				$items .= '<div class="dropdown-divider"></div>';
				$items .= '<div class="dropdown-subtitle">'.\Asset::icon('exclamation-circle').'  '.s("Zone de danger").'  '.\Asset::icon('exclamation-circle').'</div>';
				if($eSupervisor['courses'] === 0) {
					$items .= '<a data-ajax="/learning/supervisor:doDelete" post-id="'.$eSupervisor['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer cet encadrant ?").'" data-waiter>'.s("Supprimer l'encadrant").'</a>';
				} else {
					$items .= '<a class="dropdown-item disabled">';
						$items .= '<div class="util-item-label">'.s("Supprimer l'encadrant").'</div>';
						$items .= '<div class="util-item-more">'.p("Encadre {value} formation", "Encadre {value} formations", $eSupervisor['courses']).'</div>';
					$items .= '</a>';
				}
				$h .= \main\DesignUi::getGear($items, encode($eSupervisor['name']));
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une adresse dans une colonne étroite : elle passe à la ligne après l'arobase et avant un point,
	 * et non au milieu d'un mot, que la cellule ne coupe plus qu'en dernier recours.
	 */
	public static function getEmail(string $email): string {
		return str_replace(['@', '.'], ['@<wbr>', '<wbr>.'], encode($email));
	}

	public function create(Supervisor $eSupervisor): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/supervisor:doCreate');

			if($eSupervisor['community']->notEmpty()) {
				$h .= $form->hidden('community', $eSupervisor['community']);
			}

			$h .= $form->dynamicGroups($eSupervisor, ['name*', 'role', 'description', 'email', 'contact', 'handicap']);

			$h .= $form->group(
				content: $form->submit(s("Ajouter l'encadrant"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-supervisor',
			title: s("Ajouter un encadrant"),
			body: $h
		);

	}

	/**
	 * La fiche de la personne : ce qui se règle une fois et vaut dans toutes ses formations, puis les
	 * formations qu'elle encadre, où se règlent son rôle et le contact de chacune. Le statut n'y est
	 * pas : il se change depuis la liste.
	 */
	public function update(Supervisor $eSupervisor, \Collection $cStaff): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/supervisor:doUpdate');

			$h .= $form->hidden('id', $eSupervisor);

			$h .= $form->dynamicGroups($eSupervisor, ['name*', 'role', 'description', 'email', 'contact', 'handicap']);

			if($cStaff->notEmpty()) {

				$h .= $form->group(
					s("Formations"),
					implode('', $cStaff->makeArray(fn(Staff $eStaff) => '<div><a href="'.CourseUi::urlEdition($eStaff['course']).'">'.encode($eStaff['course']['name']).'</a></div>'))
				);

			}

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-supervisor',
			title: s("Modifier un encadrant"),
			body: $h
		);

	}

	/**
	 * Les textes qui nomment le groupe d'un encadrant, selon ce qu'il est : un réseau, un groupe de
	 * formation, que ce package appelle « le groupe », ou l'équipe Ouvretaferme quand il est vide.
	 * L'équipe n'a pas d'« adresse » : une formation de l'équipe sans contact n'a pas de ligne
	 * « Contact » (`StaffUi::getContact()`).
	 */
	public static function getCommunityTexts(\structure\Structure $eStructure): array {

		if($eStructure->empty()) {

			return [
				'contact' => s("Contact de l'équipe"),
				'contactInfo' => s("Son nom et son adresse e-mail se lisent sur la fiche des formations qui n'ont pas désigné le leur. L'équipe n'a qu'un contact."),
				'pick' => s("Encadrant de l'équipe"),
				'courseContactInfo' => s("Son nom et son adresse e-mail se lisent sur la fiche de cette formation, à la place du contact de l'équipe. Une formation n'a qu'un contact."),
			];

		}

		return match($eStructure['communityType']) {

			\structure\Structure::NETWORK => [
				'contact' => s("Contact du réseau"),
				'contactInfo' => s("Son nom et son adresse e-mail se lisent sur la fiche des formations qui n'ont pas désigné le leur. Un réseau n'a qu'un contact."),
				'address' => s("Adresse du réseau"),
				'pick' => s("Encadrant du réseau"),
				'courseContactInfo' => s("Son nom et son adresse e-mail se lisent sur la fiche de cette formation, à la place du contact du réseau. Une formation n'a qu'un contact."),
			],

			\structure\Structure::EDUCATION => [
				'contact' => s("Contact du groupe"),
				'contactInfo' => s("Son nom et son adresse e-mail se lisent sur la fiche des formations qui n'ont pas désigné le leur. Un groupe n'a qu'un contact."),
				'address' => s("Adresse du groupe"),
				'pick' => s("Encadrant du groupe"),
				'courseContactInfo' => s("Son nom et son adresse e-mail se lisent sur la fiche de cette formation, à la place du contact du groupe. Une formation n'a qu'un contact."),
			],

		};

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Supervisor::model()->describer($property, [
			'name' => s("Nom"),
			'role' => s("Rôle habituel"),
			'description' => s("Présentation"),
			'email' => s("Adresse e-mail"),
			'handicap' => s("Référent handicap"),
		]);

		// Ce qu'un réglage change se lit sous son libellé : sous le champ ne va qu'une consigne de saisie.
		switch($property) {

			case 'role' :
				$d->placeholder = s("Ex. : autrice, relectrice, formateur");
				$d->labelAfter = \util\FormUi::info(s("Proposé comme rôle quand la personne est ajoutée à une formation."));
				break;

			case 'description' :
				$d->field = 'textarea';
				break;

			case 'handicap' :
				$d->field = 'yesNo';
				$d->labelAfter = \util\FormUi::info(s("Vaut dans toutes les formations qu'il encadre."));
				break;

			case 'contact' :
				$d->field = 'yesNo';
				$d->label = fn(Supervisor $e) => self::getCommunityTexts($e['community'])['contact'];
				$d->labelAfter = fn(Supervisor $e) => \util\FormUi::info(self::getCommunityTexts($e['community'])['contactInfo']);
				break;

		}

		return $d;

	}

}
?>
