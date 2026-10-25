<?php
/**
 * Créer son compte pour se former (choix 2 B du lot glacons-parcours) : depuis la fiche d'une
 * formation qui demande un compte, dans un panneau qui ouvre ensuite sa première leçon, ou depuis
 * « Je veux seulement me former » sur /inscription, dans une page qui mène au catalogue.
 *
 * Le compte n'a aucun rôle, comme celui que crée une boutique (`ShopLib::createUser()`) : il se
 * forme sans structure, et l'accueil ne lui en demande pas. L'inscription elle-même est celle de
 * /inscription (`SignUpLib::createBasic()`), limite d'inscriptions et conditions comprises.
 *
 * La formation commence dans la foulée quand elle le permet ; une formation datée attend une place
 * confirmée, et le compte revient alors sur sa fiche. Celle où l'on s'inscrit avant de lire
 * (`Course::isEnrollFirst()`) l'inscrit et le ramène aussi à sa fiche. Quand l'inscription peut
 * partir en demande, le compte connecté revient à la fiche sans être inscrit, le panneau
 * d'inscription ouvert : c'est lui qui pose la question de la prise en charge, ou qui dit que le
 * réseau confirme chaque inscription.
 *
 * L'e-mail de bienvenue est celui des formations (`AccountLib::sendSignUp()`), qui parle de la
 * formation d'où vient le compte ou, depuis le catalogue, de ses « Premiers pas ». La page l'envoie
 * elle-même une fois le compte créé, et écarte celui des autres inscriptions (`sendMail` à faux).
 */
new Page(function($data) {

		if(\learning\LearningSetting::$active === FALSE) {
			throw new NotExistsAction();
		}

		\user\ConnectionLib::checkAnonymous();

		$data->eUser = \user\UserLib::getNew();
		$data->eCourse = \learning\CourseLib::getById(INPUT('course'));

		if($data->eCourse->notEmpty()) {

			if($data->eCourse->acceptLearning() === FALSE) {
				throw new NotExistsAction();
			}

			$data->eCourse->validate('assertPublicRead');

		}

	})
	->get('create', function($data) {

		$data->eCourse->validate();

		$data->enrollFirst = $data->eCourse->isEnrollFirst();

		throw new ViewAction($data);

	})
	->get('/formations/compte', function($data) {

		throw new ViewAction($data);

	})
	->post('logIn', function($data) {

		$data->email = POST('email', '?string');

		throw new ViewAction($data);

	})
	->post('doCreate', function($data) {

		$data->eUser['sendMail'] = FALSE;

		\user\SignUpLib::createBasic($data->eUser, $_POST);

		\user\ConnectionLib::logInUser($data->eUser);

		// Après la connexion : un envoi qui échoue laisse au moins le compte connecté.
		\learning\AccountLib::sendSignUp($data->eUser, $data->eCourse);

		if($data->eCourse->empty()) {
			throw new RedirectAction(\util\HttpUi::setArgument('/formations', 'success', 'user\\User::welcomeCreate'));
		}

		$eEnrollment = new \learning\Enrollment([
			'user' => $data->eUser,
			'course' => $data->eCourse
		]);

		if($eEnrollment->acceptCreate() === FALSE) {
			throw new RedirectAction(\util\HttpUi::setArgument(\learning\CourseUi::url($data->eCourse), 'success', 'user\\User::welcomeCreate'));
		}

		// Le formulaire du compte ne pose pas la question de la prise en charge et ne dit pas que le
		// réseau confirme chaque inscription : le compte, désormais connecté, revient à la fiche, le
		// panneau d'inscription ouvert, comme s'il avait cliqué sur le bouton.
		$data->eCourse['onlineConfirm'] = \learning\EnrollmentLib::getOnlineConfirm($data->eCourse);

		if($data->eCourse->acceptEnrollmentRequest()) {
			throw new RedirectAction(\util\HttpUi::setArgument(\learning\CourseUi::url($data->eCourse, ['enroll' => 1]), 'success', 'user\\User::welcomeCreate'));
		}

		$fw = new FailWatch();

		// Un compte qui vient d'être créé n'a aucune structure : il suit la formation sans, comme le
		// bouton « Commencer » d'un compte sans structure. La question écartée plus haut, la
		// structure est la seule propriété à construire.
		$eEnrollment->build(['structure'], ['structure' => '']);

		$fw->validate();

		// Lu avant l'écriture : une fois inscrit, le compte lit, et la question ne se pose plus.
		$enrollFirst = $data->eCourse->isEnrollFirst();

		\learning\EnrollmentLib::create($eEnrollment);

		// La pause relue sous verrou peut refuser l'inscription après la page : le compte, déjà créé
		// et connecté, revient à la fiche comme plus haut, qui lui montre la pause.
		$fw->validate(onKo: fn() => throw new RedirectAction(\util\HttpUi::setArgument(\learning\CourseUi::url($data->eCourse), 'success', 'user\\User::welcomeCreate')));

		// Le geste était « S'inscrire » : le compte revient à la fiche, qui le lui dit, et commence
		// quand il veut.
		if($enrollFirst) {
			throw new RedirectAction(\util\HttpUi::setArgument(\learning\CourseUi::url($data->eCourse), 'success', 'user\\User::welcomeCreate'));
		}

		$eLesson = \learning\ChapterLib::getLanding(\learning\ChapterLib::getByCourse($data->eCourse), new \learning\Lesson());

		throw new RedirectAction(\util\HttpUi::setArgument(\learning\LessonUi::url($eLesson), 'success', 'user\\User::welcomeCreate'));

	});
?>
