<?php
namespace learning;

/**
 * Les comptes prévenus de l'ouverture d'une formation (`Announcement`).
 */
class AnnouncementLib extends AnnouncementCrud {

	/**
	 * Pose l'annonce de la formation pour ce compte, et dit si elle est neuve : l'e-mail ne part que
	 * dans ce cas. `add-ignore` laisse l'unicité `(course, user)` trancher, y compris entre deux
	 * passages simultanés (l'enregistrement d'un partage et le cron) ; `create()` ne porte pas
	 * l'option, d'où l'insertion par le modèle, comme `EnrollmentLib::create()`.
	 */
	public static function claim(Course $eCourse, \user\User $eUser): bool {

		$eAnnouncement = new Announcement([
			'course' => $eCourse,
			'user' => $eUser
		]);

		return (
			Announcement::model()
				->option('add-ignore')
				->insert($eAnnouncement) === 1
		);

	}

	public static function deleteByCourse(Course $eCourse): void {

		$cAnnouncement = Announcement::model()
			->select('id')
			->whereCourse($eCourse)
			->getCollection();

		foreach($cAnnouncement as $eAnnouncement) {
			self::delete($eAnnouncement);
		}

	}

}
?>
