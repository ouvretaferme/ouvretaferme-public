<?php
/**
 * Le signal de lecture d'une leçon ouverte (lot L8c), que `LearningReading` envoie toutes les
 * `ReadingLib::PERIOD` secondes. Il ne compte que pour un compte inscrit à une formation de groupe
 * ou de réseau, et se refuse sinon : le POST se forge, la leçon ne l'envoie jamais hors de ce cas.
 */
new Page()
	->post('doSee', function($data) {

		\user\ConnectionLib::checkLogged();

		$eLesson = \learning\LessonLib::getById(POST('lesson'))->validate('assertRead');

		$eEnrollment = \learning\EnrollmentLib::getByUserAndCourse(\user\ConnectionLib::getOnline(), $eLesson['course'])->validate();
		$eEnrollment['course']->validate('acceptReading');

		\learning\ReadingLib::see($eEnrollment, $eLesson);

		throw new VoidAction();

	});
?>
