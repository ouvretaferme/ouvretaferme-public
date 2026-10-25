<?php
/**
 * Le catalogue : les formations publiées de l'équipe Ouvretaferme et celles des réseaux que l'équipe
 * y a acceptées, rangées par thématique.
 *
 * Il s'ouvre sans compte. Le visiteur y voit aussi les formations réservées aux comptes, avec leur
 * cadenas : leur carte le mène à la fiche, qui le dit à son tour avant le bouton de connexion. Le
 * compte connecté y lit en plus où il en est de chacune.
 *
 * Les filtres de thématique, de sous-thématique et de niveau passent par l'adresse : un catalogue filtré se partage et se
 * recharge tel quel.
 *
 * Deux onglets (planche catalogue-attractif v3) : Formations, ouvert par défaut, et Prochaines dates
 * (`tab=dates`), l'agenda des formations datées, qui ne paraît que si une formation du catalogue a
 * une date à venir. Les filtres valent pour les deux, et l'adresse garde l'onglet.
 */
new Page(function($data) {

		if(\learning\LearningSetting::$active === FALSE) {
			throw new NotExistsAction();
		}

	})
	->get('/formations', function($data) {

		$eUser = \user\ConnectionLib::getOnline();

		$data->cTheme = \learning\ThemeLib::getAll();
		$data->cLevel = \learning\LevelLib::getAll();

		$data->eThemeSelected = $data->cTheme->offsetExists(GET('theme', 'int')) ? $data->cTheme[GET('theme', 'int')] : new \learning\Theme();
		$data->eLevelSelected = $data->cLevel->offsetExists(GET('level', 'int')) ? $data->cLevel[GET('level', 'int')] : new \learning\Level();

		// Une sous-thématique ne vaut que sous sa thématique : celle d'une autre, que l'adresse garde
		// quand la liste des thématiques renvoie le formulaire, est ignorée. Changer de thématique
		// vide donc le filtre de sous-thématique, sans script.
		$data->cSubtheme = \learning\SubthemeLib::getByTheme($data->eThemeSelected);
		$data->eSubthemeSelected = $data->cSubtheme->offsetExists(GET('subtheme', 'int')) ? $data->cSubtheme[GET('subtheme', 'int')] : new \learning\Subtheme();

		$cCourse = \learning\CourseLib::getCatalog();

		$data->catalog = $cCourse->count();

		// Les compteurs se posent sur tout le catalogue, avant les filtres : l'onglet Prochaines dates
		// paraît dès qu'une formation du catalogue a une date à venir, qu'un filtre la garde ou non,
		// pour que la barre d'onglets ne disparaisse pas sous un filtre.
		\learning\CourseLib::fillCounts($cCourse);

		$data->hasAgenda = ($cCourse->find(fn(\learning\Course $eCourse) => ($eCourse->isDated() and $eCourse['dates'] > 0))->count() > 0);
		$data->tab = ($data->hasAgenda and GET('tab') === 'dates') ? 'dates' : 'formations';
		// Sans barre d'onglets, la loupe se pose sous le titre.
		$data->withSearch = ($data->catalog > 0 and $data->hasAgenda === FALSE);
		$data->isFiltered = ($data->eThemeSelected->notEmpty() or $data->eLevelSelected->notEmpty() or $data->eSubthemeSelected->notEmpty());

		// Les deux `notEmpty()` disent sur quoi les filtres portent, et non qu'on se méfie de la
		// donnée : une formation sans thématique n'appartient à aucune thématique, et une formation
		// sans niveau, ce qu'une formation publiée a le droit d'être, à aucun niveau. Ni l'une ni
		// l'autre n'entre dans une sélection, elles ne paraissent que sous « Tous ».
		$cCourse = $cCourse->find(fn(\learning\Course $eCourse) => (
			($data->eThemeSelected->empty() or ($eCourse['theme']->notEmpty() and $eCourse['theme']['id'] === $data->eThemeSelected['id'])) and
			($data->eLevelSelected->empty() or ($eCourse['level']->notEmpty() and $eCourse['level']['id'] === $data->eLevelSelected['id'])) and
			($data->eSubthemeSelected->empty() or $eCourse['subtheme']->is($data->eSubthemeSelected))
		));

		$data->courses = $cCourse->count();
		$data->isLogged = $eUser->notEmpty();

		$cDateAgenda = \learning\DateLib::getAgenda($cCourse);

		$data->dates = $cDateAgenda->count();

		if($data->tab === 'dates') {

			// L'agenda s'arrête à la fin du mois suivant, le reste derrière « Voir les autres dates ».
			// Quand rien ne tombe avant, il montre tout plutôt qu'un onglet vide et un bouton.
			$bound = date('Y-m-01', \Clock::shift('first day of +2 months'));

			$cDateShown = GET('all', 'bool')
				? $cDateAgenda
				: $cDateAgenda->find(fn(\learning\Date $eDate) => $eDate['startAt'] < $bound);

			if($cDateShown->empty()) {
				$cDateShown = $cDateAgenda;
			}

			$data->ccDateMonth = \learning\DateLib::groupByMonth($cDateShown);
			$data->datesRemaining = $data->dates - $cDateShown->count();

			throw new ViewAction($data);

		}

		\learning\ProgressLib::fillDone($eUser, $cCourse);

		// Sans filtre, « Premiers pas » ouvre la page, dans l'ordre où l'équipe les a choisies, et
		// chaque rangée de thématique montre trois formations qui n'en sont pas. « Voir les N
		// formations » compte la thématique entière, celles de « Premiers pas » comprises : c'est ce
		// que montre la thématique filtrée, où plus rien n'est tronqué.
		if($data->isFiltered) {
			$data->cCourseFeatured = new \Collection();
			$cCourseRows = $cCourse;
		} else {
			$data->cCourseFeatured = $cCourse->find(fn(\learning\Course $eCourse) => $eCourse['featured'])->sort(['featuredAt' => SORT_ASC, 'id' => SORT_ASC]);
			$cCourseRows = $cCourse->find(fn(\learning\Course $eCourse) => $eCourse['featured'] === FALSE);
		}

		$data->cThemeCatalog = \learning\CourseLib::groupByTheme($cCourseRows, $data->cTheme);

		foreach($data->cThemeCatalog as $eTheme) {

			$eTheme['courses'] = $cCourse->find(fn(\learning\Course $eCourse) => $eCourse['theme']->is($eTheme))->count();
			$eTheme['more'] = ($data->isFiltered === FALSE and $eTheme['cCourse']->count() > \learning\CourseLib::CATALOG_ROW);

			if($eTheme['more']) {
				$eTheme['cCourse'] = $eTheme['cCourse']->slice(0, \learning\CourseLib::CATALOG_ROW);
			}

		}

		throw new ViewAction($data);

	})
	/**
	 * Comment les avis sont publiés (D111-17), liée depuis la mention de chaque fiche. Ouverte sans
	 * compte, comme le catalogue.
	 */
	->get('/formations/avis', function($data) {

		throw new ViewAction($data);

	});
?>
