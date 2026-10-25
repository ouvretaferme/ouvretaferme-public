<?php
namespace learning;

/**
 * Les encadrants d'une formation : chaque ligne rattache une personne du réseau (`Supervisor`) à
 * une formation, avec le rôle qu'elle y tient, son rang, et la marque du contact de la formation.
 */
class StaffLib extends StaffCrud {

	/**
	 * `supervisor` précède `contact`, dont l'étape `noEmail` lit l'adresse de la personne construite
	 * juste avant. Depuis la création d'une personne (`supervisor`), la page pose elle-même la
	 * personne qu'elle vient de construire : seuls le rôle et le contact se lisent dans l'envoi.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {

		if($origin === 'supervisor') {
			return ['role', 'contact'];
		}

		return ['supervisor', 'role', 'contact'];

	}

	/**
	 * La personne d'une ligne ne se change pas : on retire l'encadrant de la formation et on en
	 * ajoute un autre.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['role', 'contact'];
	}

	/**
	 * « M'ajouter comme encadrant » : pose sur le panneau d'ajout ce que le compte sait de la personne,
	 * sans rien écrire. Le réseau de la formation la reconnait par l'adresse du compte.
	 *
	 * S'il ne la connait pas, la personne à saisir reçoit le nom et l'adresse du compte, et le rôle
	 * reste à écrire, le compte ne le connaissant pas. S'il a sa fiche et qu'on peut encore l'ajouter
	 * (`cSupervisor`), la ligne s'ouvre sur elle, la plus ancienne quand plusieurs conviennent ; son
	 * rôle habituel n'est pas posé sur la ligne, que lirait aussi le chemin d'une personne nouvelle,
	 * c'est le panneau qui le propose avec la fiche. S'il n'a d'elle que des fiches désactivées ou qui
	 * encadrent déjà la formation, rien n'est posé et le panneau s'ouvre comme par « Ajouter un
	 * encadrant » : la saisir de nouveau ferait une seconde personne à la même adresse.
	 *
	 * Un compte sans adresse ne se cherche pas : une adresse vide désignerait toutes les fiches qui
	 * n'en ont pas.
	 */
	public static function prepareSelf(Staff $e, Supervisor $eSupervisor, \user\User $eUser): void {

		$e->expects([
			'course' => ['community'],
			'cSupervisor'
		]);
		$eUser->expects(['firstName', 'lastName', 'email']);

		if($eUser['email'] === NULL) {
			$cSupervisorSelf = new \Collection();
		} else {
			$cSupervisorSelf = SupervisorLib::getByEmail($e['course']['community'], $eUser['email']);
		}

		if($cSupervisorSelf->empty()) {

			$eSupervisor['name'] = ($eUser['firstName'] === NULL) ? $eUser['lastName'] : $eUser['firstName'].' '.$eUser['lastName'];

			if($eUser['email'] !== NULL) {
				$eSupervisor['email'] = $eUser['email'];
			}

			return;

		}

		foreach($cSupervisorSelf as $eSupervisorSelf) {

			if($e['cSupervisor']->offsetExists($eSupervisorSelf['id'])) {
				$e['supervisor'] = $eSupervisorSelf;
				return;
			}

		}

	}

	public static function getByCourse(Course $eCourse): \Collection {

		return Staff::model()
			->select(Staff::getSelectionWithoutCourse())
			->whereCourse($eCourse)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection()
			->setColumn('course', $eCourse);

	}

	/**
	 * Les formations qu'une personne encadre, chacune avec le rôle qu'elle y tient, par titre.
	 */
	public static function getBySupervisor(Supervisor $eSupervisor): \Collection {

		return Staff::model()
			->select([
				'id',
				'course' => ['name', 'status', 'community'],
				'role', 'contact'
			])
			->whereSupervisor($eSupervisor)
			->getCollection()
			->sort(['course' => ['name' => SORT_ASC]]);

	}

	/**
	 * Une formation a au plus un contact : l'encadrant qui le devient le retire à celui qui l'était,
	 * dans la même transaction, pour que deux enregistrements simultanés ne laissent jamais deux
	 * contacts. `contact` s'écrit au modèle avec `FALSE` par défaut : un encadrant construit sans
	 * lui, comme ceux des décors de test, n'est pas contact.
	 *
	 * Aucune clé étrangère ne relie la ligne à sa personne : la personne se relit donc sous verrou,
	 * et une suppression concurrente (`SupervisorLib::delete()`, qui la verrouille aussi) attend
	 * cette liaison ou la précède. Précédée, la ligne ne s'écrit pas : elle désignerait une personne
	 * disparue.
	 */
	public static function create(Staff $e): void {

		$e->expects(['course', 'supervisor']);
		$e->add(['contact' => FALSE]);

		Staff::model()->beginTransaction();

			$eSupervisor = Supervisor::model()
				->whereId($e['supervisor'])
				->getForUpdate();

			// Pas de `return` avant le `commit()` : il laisserait le compteur de transactions à 1 et le
			// COMMIT ne partirait jamais.
			if($eSupervisor->empty()) {

				Staff::fail('supervisor.check');

			} else {

				$e['position'] = Staff::model()
					->whereCourse($e['course'])
					->count();

				parent::create($e);

				if($e['contact']) {
					self::removeOtherContact($e);
				}

			}

		Staff::model()->commit();

	}

	/**
	 * L'encadrant que le réseau ne connait pas encore, saisi depuis une formation : la personne nait
	 * dans le réseau de la formation, puis sa ligne, dans une seule transaction pour qu'un refus de
	 * la seconde ne laisse pas une personne que rien ne rattache.
	 */
	public static function createWithSupervisor(Staff $e, Supervisor $eSupervisor): void {

		Staff::model()->beginTransaction();

			SupervisorLib::create($eSupervisor);

			$e['supervisor'] = $eSupervisor;

			self::create($e);

		Staff::model()->commit();

	}

	public static function update(Staff $e, array $properties): void {

		Staff::model()->beginTransaction();

			parent::update($e, $properties);

			if(
				in_array('contact', $properties, TRUE) and
				$e['contact']
			) {
				$e->expects(['course']);
				self::removeOtherContact($e);
			}

		Staff::model()->commit();

	}

	/**
	 * Écriture de masse, donc un tableau et non l'élément : `update()` borne à l'`id` de l'élément
	 * qu'on lui passe (`Module::getUpdateCondition()`).
	 */
	private static function removeOtherContact(Staff $e): void {

		Staff::model()
			->whereCourse($e['course'])
			->whereId('!=', $e['id'])
			->whereContact(TRUE)
			->update(['contact' => FALSE]);

	}

}
?>
