<?php
/**
 * Le sommaire des tutoriels de Ouvretaferme : les formations en ligne publiées du réseau des
 * tutoriels (`LearningSetting::$structureTutorial`), phase par phase, chaque module avec ses
 * leçons, qui mènent à leur lecture sans compte (`Course::acceptTutorial()`).
 *
 * Ouverte à tout visiteur, connecté ou non, et la même pour tous : ni inscription, ni progression,
 * ni contenu de leçon. Un module annonce les questions publiées de la FAQ qui renvoient à lui, et
 * mène à elles sur `/faq`. Le statut se juge dans la requête (`CourseLib::getPublicOnlineByCommunity()`),
 * jamais par `Course::canRead()`, que `acceptTutorial()` ouvre sur un brouillon du réseau.
 *
 * Sans réseau des tutoriels, ou sur un réseau fermé aux formations, l'adresse n'existe pas : la
 * réponse part avant toute lecture des formations.
 */
new Page()
	->get('/tutoriels', function($data) {

		if(\learning\LearningSetting::$structureTutorial === NULL) {
			throw new NotExistsAction();
		}

		$eStructureTutorial = \structure\StructureLib::getById(\learning\LearningSetting::$structureTutorial);

		if(
			$eStructureTutorial->empty() or
			$eStructureTutorial->acceptLearning() === FALSE
		) {
			throw new NotExistsAction();
		}

		$data->cCourse = \learning\CourseLib::getPublicOnlineByCommunity($eStructureTutorial);

		$cChapterAll = new Collection();

		// Les chiffres de la bande comptent toutes les leçons, vides comprises, comme la fiche.
		$data->chapters = 0;
		$data->lessons = 0;
		$data->minutes = NULL;

		foreach($data->cCourse as $eCourse) {

			$cChapter = \learning\ChapterLib::getByCourse($eCourse, byPhase: TRUE);

			$cChapterAll->appendCollection($cChapter);

			$eCourse['chapters'] = $cChapter->count();
			$eCourse['lessons'] = \learning\ChapterLib::countLessons($cChapter);
			$eCourse['minutes'] = \learning\ChapterLib::sumMinutes($cChapter);

			// L'état d'une leçon, que le sommaire partage avec la fiche (`CourseUi::getLessonState()`),
			// rend la leçon quiz selon l'état du lecteur, et ses liens suivent ce que le lecteur peut
			// cocher (`Lesson::isLinked()`) : le sommaire est le même pour tous, il se lit comme le
			// visiteur sans compte.
			\learning\ResponseLib::fillCourseQuiz(new \user\User(), $eCourse, $cChapter);
			$eCourse['canFollow'] = FALSE;

			\learning\PhaseLib::fillChapters($eCourse, $cChapter);

			$data->chapters += $eCourse['chapters'];
			$data->lessons += $eCourse['lessons'];

			if($eCourse['minutes'] !== NULL) {
				$data->minutes ??= 0;
				$data->minutes += $eCourse['minutes'];
			}

		}

		// Un module mène à sa première leçon écrite, depuis la recherche comme depuis l'appel du bas de
		// page, qui part du premier module qui en a une, dans l'ordre où le sommaire les montre.
		$data->eChapterStart = new \learning\Chapter();

		foreach($cChapterAll as $eChapter) {

			$eChapter['eLessonFirst'] = $eChapter['cLesson']->find(fn(\learning\Lesson $eLesson) => $eLesson['written'], limit: 1, default: new \learning\Lesson());

			if(
				$data->eChapterStart->empty() and
				$eChapter['eLessonFirst']->notEmpty()
			) {
				$data->eChapterStart = $eChapter;
			}

		}

		// Les questions de la FAQ de chaque module, comptées en une fois pour toutes les formations.
		$data->faqsByChapter = \learning\FaqLib::countPublishedByChapter($eStructureTutorial, $cChapterAll->getIds());

		throw new ViewAction($data);

	});
?>
