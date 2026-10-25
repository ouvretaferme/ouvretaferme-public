<?php
namespace main;

/**
 * Les formations à Ouvretaferme animées par la FRAB AuRA. Le catalogue est celui que le réseau tient
 * dans l'e-learning : ses formations publiées en visio ou sur place, et leurs dates à venir, dont
 * l'inscription passe par la page publique du réseau.
 */
class TrainingAiLib {

	/**
	 * Le réseau dont on lit les formations : la FRAB AuRA. La base de dev se recharge depuis une copie
	 * de la production, qui porte le même id ; une base qui ne l'a pas rend un catalogue vide.
	 */
	const COMMUNITY = 6659;

	const HOME_DATES = 3;

	/**
	 * L'accueil ne propose une date qu'à partir du surlendemain : celles du jour et du lendemain n'y
	 * paraissent plus (décidé le 2026-10-20). Le catalogue de /presentation/formations les
	 * garde.
	 */
	const HOME_FROM = '+2 days';

	/**
	 * Le réseau, vide quand il n'existe pas sur cette base ou qu'il n'a pas accès aux formations :
	 * la page ne propose alors aucune formation, plutôt que des dates dont l'inscription répondrait
	 * comme une adresse inconnue.
	 */
	public static function getCommunity(): \structure\Structure {

		$eStructure = \structure\StructureLib::getById(self::COMMUNITY);

		if(
			$eStructure->empty() or
			$eStructure->isNetwork() === FALSE or
			$eStructure->acceptLearning() === FALSE
		) {
			return new \structure\Structure();
		}

		$eStructure['community'] = \structure\CommunityLib::getByStructure($eStructure);

		return $eStructure;

	}

	/**
	 * Les formations du réseau qui ont une date à venir, chacune avec ses prochaines dates dans `cDate`,
	 * pour l'accueil des producteurs. Une formation sans date n'y a rien à proposer. Une date dont les
	 * inscriptions sont en pause, par elle-même ou par sa formation, n'est pas proposée : chaque date
	 * de l'encart mène à une inscription.
	 */
	public static function getHomeCourses(\structure\Structure $eStructure): \Collection {

		if($eStructure->empty()) {
			return new \Collection();
		}

		$cCourse = \learning\CourseLib::getPublicByCommunity($eStructure);
		$cDate = \learning\DateLib::getPublicByCommunity($eStructure);

		$from = date('Y-m-d', \Clock::shift(self::HOME_FROM));

		foreach($cCourse as $eCourse) {
			$eCourse['cDate'] = $cDate->find(fn(\learning\Date $eDate) => (
				$eDate['course']['id'] === $eCourse['id'] and
				substr($eDate['startAt'], 0, 10) >= $from and
				$eDate->isEnrollmentPaused() === FALSE
			), limit: self::HOME_DATES);
		}

		return $cCourse->find(fn(\learning\Course $eCourse) => $eCourse['cDate']->notEmpty());

	}

}
?>
