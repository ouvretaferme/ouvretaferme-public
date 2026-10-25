<?php
namespace learning;

/**
 * Les encadrants d'un réseau, d'un groupe de formation ou de l'équipe Ouvretaferme (`community`
 * vide), saisis une fois et rattachés à leurs formations par `Staff.supervisor`.
 */
class SupervisorLib extends SupervisorCrud {

	/**
	 * `email` précède `contact`, dont l'étape `noEmail` lit l'adresse construite juste avant. Le
	 * contact du réseau ne se désigne pas depuis une formation (`course`) : le panneau d'une
	 * formation ne règle que le contact de cette formation, que porte la liaison.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {

		if($origin === 'course') {
			return ['name', 'role', 'description', 'email', 'handicap'];
		}

		return ['name', 'role', 'description', 'email', 'handicap', 'contact'];

	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'role', 'description', 'email', 'handicap', 'contact'];
	}

	/**
	 * Un encadrant nait chez celui qui le saisit : l'équipe quand aucun groupe n'est donné, le groupe
	 * sinon. `coursesContact` est posé ici parce que l'étape `Supervisor::email.contact` le lit
	 * pendant `build()`, y compris à la création, où la personne n'encadre encore aucune formation.
	 */
	public static function getCreateElement(\structure\Structure $eStructure = new \structure\Structure()): Supervisor {

		return new Supervisor([
			'community' => $eStructure,
			'coursesContact' => 0
		]);

	}

	/**
	 * Les encadrants d'un groupe, ou de l'équipe pour un groupe vide, par nom, chacun avec ce que
	 * ses formations disent de lui (`fillCourses()`).
	 */
	public static function getByCommunity(\structure\Structure $eStructure): \Collection {

		$cSupervisor = Supervisor::model()
			->select(Supervisor::getSelection())
			->whereCommunity($eStructure->empty() ? NULL : $eStructure)
			->sort(['name' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection();

		self::fillCourses($cSupervisor);

		return $cSupervisor;

	}

	/**
	 * Pose sur chaque encadrant le nombre de formations qu'il encadre (`courses`) et, parmi elles,
	 * de celles dont il est le contact (`coursesContact`), par un seul compte groupé sur les
	 * liaisons : le premier ferme la suppression à l'écran, le second est lu par l'étape
	 * `Supervisor::email.contact`. Le compte groupé ne rend aucune ligne pour un encadrant sans
	 * formation : son zéro se pose ici, il ne se lit pas dans la requête.
	 */
	public static function fillCourses(\Collection $cSupervisor): void {

		if($cSupervisor->empty()) {
			return;
		}

		$cStaffCount = Staff::model()
			->select([
				'supervisor',
				'courses' => new \Sql('COUNT(*)', 'int'),
				'coursesContact' => new \Sql('SUM(contact)', 'int')
			])
			->whereSupervisor('IN', $cSupervisor)
			->group('supervisor')
			->getCollection(index: 'supervisor');

		foreach($cSupervisor as $eSupervisor) {

			if($cStaffCount->offsetExists($eSupervisor['id'])) {
				$eSupervisor['courses'] = $cStaffCount[$eSupervisor['id']]['courses'];
				$eSupervisor['coursesContact'] = $cStaffCount[$eSupervisor['id']]['coursesContact'];
			} else {
				$eSupervisor['courses'] = 0;
				$eSupervisor['coursesContact'] = 0;
			}

		}

	}

	/**
	 * Les personnes actives du réseau d'une formation qui ne l'encadrent pas encore, par nom et
	 * indexées par leur identifiant : celles qu'on peut lui ajouter (`Staff::supervisor.owned`). C'est
	 * la seule lecture que le statut filtre : une personne désactivée n'est plus proposée, et reste sur
	 * les formations qu'elle encadre, leur fiche, leur feuille d'émargement et leurs e-mails. Deux
	 * requêtes et non un anti-join, dont la sélection porterait `community` des deux côtés ; la liste
	 * écartée est bornée par les encadrants d'une seule formation.
	 */
	public static function getForCourse(Course $eCourse): \Collection {

		$eCourse->expects(['community']);

		$taken = Staff::model()
			->whereCourse($eCourse)
			->getColumn('supervisor')
			->getIds();

		return Supervisor::model()
			->select(Supervisor::getSelection())
			->whereCommunity($eCourse['community']->empty() ? NULL : $eCourse['community'])
			->whereStatus(Supervisor::ACTIVE)
			->whereId('NOT IN', $taken, if: $taken !== [])
			->sort(['name' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Les fiches d'un groupe, ou de l'équipe pour un groupe vide, qui portent cette adresse, de la plus
	 * ancienne à la plus récente : c'est par elles qu'un compte se reconnait parmi les encadrants
	 * (`StaffLib::prepareSelf()`), une fiche n'étant liée à aucun compte. Les fiches désactivées en
	 * sont : le réseau connait la personne, même s'il ne la propose plus.
	 */
	public static function getByEmail(\structure\Structure $eStructure, string $email): \Collection {

		return Supervisor::model()
			->select(Supervisor::getSelection())
			->whereCommunity($eStructure->empty() ? NULL : $eStructure)
			->whereEmail($email)
			->sort(['id' => SORT_ASC])
			->getCollection();

	}

	/**
	 * La personne à qui s'adresser pour une formation, que sa fiche et les e-mails aux stagiaires
	 * d'une date nomment (`DateEnrollmentLib::getMails()`) : celle de la ligne contact, sinon le
	 * contact de son réseau, sinon un élément vide. Une personne désactivée reste le contact qu'elle
	 * était : le statut ne se lit pas ici.
	 */
	public static function getContactByCourse(Course $eCourse): Supervisor {

		$eCourse->expects(['community']);

		$eStaff = Staff::model()
			->select([
				'supervisor' => ['name', 'email']
			])
			->whereCourse($eCourse)
			->whereContact(TRUE)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->get();

		if($eStaff->empty()) {
			return self::getContactByCommunity($eCourse['community']);
		}

		return $eStaff['supervisor'];

	}

	/**
	 * Le contact d'un réseau, ou de l'équipe pour un groupe vide : la personne qui en porte la marque,
	 * qu'elle encadre ou non la formation qui la lit. Un élément vide quand le réseau n'en a pas.
	 */
	public static function getContactByCommunity(\structure\Structure $eStructure): Supervisor {

		return Supervisor::model()
			->select(['id', 'name', 'email'])
			->whereCommunity($eStructure->empty() ? NULL : $eStructure)
			->whereContact(TRUE)
			->sort(['id' => SORT_ASC])
			->get();

	}

	/**
	 * Un réseau a au plus un contact : l'encadrant qui le devient le retire à celui qui l'était, dans
	 * la même transaction, pour que deux enregistrements simultanés ne laissent jamais deux contacts.
	 * `contact` s'écrit au modèle avec `FALSE` par défaut : un encadrant construit sans lui, comme
	 * celui que crée le panneau d'une formation, n'est pas contact.
	 */
	public static function create(Supervisor $e): void {

		$e->expects(['community']);
		$e->add(['contact' => FALSE]);

		Supervisor::model()->beginTransaction();

			parent::create($e);

			if($e['contact']) {
				self::removeOtherContact($e);
			}

		Supervisor::model()->commit();

	}

	public static function update(Supervisor $e, array $properties): void {

		Supervisor::model()->beginTransaction();

			parent::update($e, $properties);

			if(
				in_array('contact', $properties, TRUE) and
				$e['contact']
			) {
				$e->expects(['community']);
				self::removeOtherContact($e);
			}

		Supervisor::model()->commit();

	}

	/**
	 * Un encadrant rattaché à une formation ne se supprime pas : ses liaisons désigneraient une
	 * personne disparue, sur des fiches publiées. Il se retire d'abord de chacune de ses formations.
	 *
	 * Aucune clé étrangère ne relie `learningStaff.supervisor` à la personne : ce sont deux verrous qui
	 * tiennent le contrôle et la suppression ensemble. Celui de la personne fait attendre ce qui la
	 * rattache en parallèle (`StaffLib::create()`, qui la relit sous verrou), et la lecture de ses
	 * liaisons est verrouillante parce qu'une lecture simple rendrait l'instantané de la transaction
	 * (REPEATABLE READ), pris avant l'attente : une liaison validée entre-temps n'y serait pas.
	 */
	public static function delete(Supervisor $e): void {

		Supervisor::model()->beginTransaction();

			Supervisor::model()
				->whereId($e)
				->getForUpdate();

			$eStaff = Staff::model()
				->whereSupervisor($e)
				->getForUpdate();

			// Pas de `return` avant le `commit()` : il laisserait le compteur de transactions à 1 et le
			// COMMIT ne partirait jamais.
			if($eStaff->notEmpty()) {
				Supervisor::fail('deleteLinked');
			} else {
				parent::delete($e);
			}

		Supervisor::model()->commit();

	}

	/**
	 * Écriture de masse, donc un tableau et non l'élément : `update()` borne à l'`id` de l'élément
	 * qu'on lui passe (`Module::getUpdateCondition()`). L'équipe Ouvretaferme compte pour un réseau,
	 * celui dont `community` est vide.
	 */
	private static function removeOtherContact(Supervisor $e): void {

		Supervisor::model()
			->whereCommunity($e['community']->empty() ? NULL : $e['community'])
			->whereId('!=', $e['id'])
			->whereContact(TRUE)
			->update(['contact' => FALSE]);

	}

}
?>
