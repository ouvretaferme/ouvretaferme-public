<?php
/**
 * Les formations du compte : d'abord celles que ses promotions lui ouvrent, une section par
 * promotion, puis celles qu'il suit et n'a pas finies, celles que ses réseaux lui proposent et où
 * il ne s'est pas inscrit, une section par réseau, et celles qu'il a suivies en entier. Une
 * formation se commence depuis sa fiche, en s'y inscrivant.
 */
new Page(function($data) {

		if(\learning\LearningSetting::isActiveAnywhere() === FALSE) {
			throw new NotExistsAction();
		}

	})
	->get('/mes-formations', function($data) {

		\user\ConnectionLib::checkLogged();

		$eUser = \user\ConnectionLib::getOnline();

		$cCourseEnrolled = \learning\EnrollmentLib::getByUser($eUser)->getColumnCollection('course', 'course');

		$cShare = \learning\ShareLib::getByUser($eUser);

		$cShareSection = $cShare->find(fn(\learning\Share $eShare) => $eShare['section']->notEmpty(), clone: FALSE);
		$cCourseSection = $cShareSection->getColumnCollection('course', 'course');

		$cShareNetwork = $cShare->find(fn(\learning\Share $eShare) => $eShare['section']->empty(), clone: FALSE);

		\learning\ShareLib::fillProposed($eUser, $cShareNetwork->getColumnCollection('course', 'course'));

		// Une formation inscrite se range dans « À suivre » ou « Suivies en entier », et ne paraît
		// plus sous son réseau ; une formation ouverte à une promotion se range sous la promotion,
		// qu'on s'y soit inscrit ou non.
		$cShareProposed = $cShareNetwork->find(fn(\learning\Share $eShare) => $eShare['course']['proposed'], clone: FALSE);
		$cCourse = $cCourseEnrolled->find(fn(\learning\Course $eCourse) => $cCourseSection->offsetExists($eCourse['id']) === FALSE, clone: FALSE);

		// Les trois ensembles ne partagent aucune formation : chacune n'a qu'un élément à l'écran, qui
		// reçoit ses compteurs.
		$cCourseShown = new \Collection()
			->mergeCollection($cCourse, reindex: FALSE)
			->mergeCollection($cCourseSection, reindex: FALSE)
			->mergeCollection($cShareProposed->getColumnCollection('course', 'course'), reindex: FALSE);

		\learning\CourseLib::fillCounts($cCourseShown);
		\learning\ProgressLib::fillDone($eUser, $cCourseShown);

		foreach($cCourseSection as $eCourse) {
			$eCourse['eShare'] = \learning\ShareLib::getWindowByUserAndCourse($eUser, $eCourse);
		}

		foreach($cCourse as $eCourse) {

			$eCourse['remaining'] = $eCourse['lessons'] - $eCourse['done'];

			// La soustraction rendrait zéro sur une formation dont aucune leçon n'est estimée, que
			// l'annotation de la carte lirait comme « il reste 0 min ».
			$eCourse['remainingMinutes'] = ($eCourse['minutes'] === NULL) ? NULL : $eCourse['minutes'] - $eCourse['doneMinutes'];

		}

		$data->ccShareSection = $cShareSection->reindex(['key']);
		$data->ccShareProposed = $cShareProposed->reindex(['key']);

		$data->cCourseRunning = $cCourse->find(fn(\learning\Course $eCourse) => $eCourse->isDone() === FALSE);
		$data->cCourseDone = $cCourse->find(fn(\learning\Course $eCourse) => $eCourse->isDone());
		$data->started = $data->cCourseRunning->find(fn(\learning\Course $eCourse) => $eCourse['done'] > 0)->count();
		$data->hasCatalog = \learning\LearningSetting::$active;

		throw new ViewAction($data);

	});
?>
