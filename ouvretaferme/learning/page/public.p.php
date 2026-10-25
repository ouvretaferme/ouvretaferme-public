<?php
/**
 * Les formations d'un réseau sur son adresse publique, `reseau.<domaine>/{fqn}/formations` : la liste
 * des dates à venir et de ses formations en ligne, l'inscription à une date sans compte, et la page
 * d'une inscription par son jeton. L'inscription à une formation en ligne se fait sur sa fiche. Les
 * questions fréquentes du réseau, sa FAQ publiée, se lisent sur `/reseau/{fqn}/faq`.
 *
 * Aucune connexion exigée : les routes fonctionnent sans session, parce que la page s'intègre au site
 * du réseau et que le cookie `SameSite=Lax` ne part pas dans une iframe tierce. Elles la lisent quand
 * elle est là, sur l'adresse même : un compte qui lit la formation est renvoyé à sa fiche, un compte
 * hors du réseau s'inscrit sans ressaisir son identité. Les étapes voyagent par l'adresse et les
 * champs cachés, jamais en session.
 *
 * Aucun `can*` ne garde les écritures sans compte, il n'y a pas de membre à peser : c'est l'état de
 * la date qui ferme l'inscription (`DateEnrollment::acceptPublicCreate()`), et l'état de la ligne son
 * annulation (`acceptCancel()`), le jeton tenant lieu de droit. Déclarées dans `WriteRightSentinel`.
 * Deux écritures pèsent un droit : l'annulation de la ligne sans jeton du compte connecté,
 * `canCancel()`, et l'enregistrement de la barre de personnalisation, `canManage()` sur le réseau.
 */

/**
 * Un lien mort mène à une vraie page 404 : celui qui clique a un lien périmé dans un message ou sur
 * le site du réseau, il ne force pas une adresse. Forme de `responseAiNotFound()`.
 */
function learningPublicNotFound($data): ViewAction {

	$action = new ViewAction($data, '/error:404');
	$action->setStatusCode(404);

	return $action;

}

/**
 * Une ancienne adresse du réseau (`CommunityRedirect`) mène à la même page sous l'adresse actuelle,
 * comme la racine (`communityAddressAi.p.php`). Seulement sur les pages qu'un GET atteint : un envoi
 * garde l'adresse que son formulaire porte.
 */
function learningPublicCurrent($data, string $url): void {

	if(GET('fqn') !== $data->eCommunity['fqn']) {
		throw new RedirectAction(\learning\DatePublicUi::urlQuery($url, [], $data->embed), httpStatus: 'HTTP/1.1 302 Found');
	}

}

$start = function($data) {

	$data->embed = (get_exists('embed') or post_exists('embed'));

	$data->eCommunity = \structure\CommunityLib::getByAddress(GET('fqn'));
	$data->eStructureCommunity = \structure\StructureLib::getById($data->eCommunity->empty() ? NULL : $data->eCommunity['structure']['id']);

	if(
		$data->eStructureCommunity->empty() or
		$data->eStructureCommunity->isNetwork() === FALSE
	) {
		throw learningPublicNotFound($data);
	}

	// Le réseau des tutoriels ne propose aucune formation à laquelle on s'inscrit : ses formations se
	// lisent sans compte depuis `/tutoriels`. Son gérant règle le thème sur la fiche d'un tutoriel,
	// où un lien en mode qui date d'avant la carte du paramétrage le mène.
	if($data->eStructureCommunity['id'] === \learning\LearningSetting::$structureTutorial) {

		if(
			$data->embed === FALSE and
			get_exists('customize') and
			$data->eStructureCommunity->canManage()
		) {

			$eCourseCustomize = \learning\CommunityLib::getCustomizeCourse($data->eStructureCommunity);

			if($eCourseCustomize->notEmpty()) {
				throw new RedirectAction(\Lime::getUrl().\learning\CustomizeUi::urlCard($data->eCommunity, $eCourseCustomize), httpStatus: 'HTTP/1.1 302 Found');
			}

		}

		throw learningPublicNotFound($data);

	}

	if($data->eStructureCommunity->acceptLearning() === FALSE) {
		throw new NotExistsAction();
	}

	// La barre de personnalisation, jamais dans l'intégration : elle s'afficherait sur le site du
	// réseau, à tous ceux qui le visitent avec une session de gérant ouverte.
	$data->customize = (
		$data->embed === FALSE and
		get_exists('customize') and
		$data->eStructureCommunity->canManage()
	);

	// Les réglages essayés arrivent ici par `:doPreview`, qui a déjà refusé avec un message ceux qui ne
	// se gardent pas. Une adresse écrite à la main qui en porte un refusé revient à la barre, sur les
	// réglages enregistrés : une page publique n'a pas de couche ajax pour afficher le refus.
	if($data->customize) {
		\learning\CommunityLib::applyPreview($data->eCommunity, $_GET, onKo: fn() => throw new RedirectAction(\learning\DatePublicUi::urlList($data->eCommunity).'?customize=1', httpStatus: 'HTTP/1.1 302 Found'));
	}

	$data->settings = \learning\CommunityLib::getSettings($data->eCommunity);

	// Les liens de la liste et la redirection vers la fiche gardent le mode et les réglages essayés.
	$data->customizeQuery = $data->customize ? \learning\CustomizeUi::getQuery($data->settings['input']) : [];

	// Lu ici et posé sur le réseau : le réglage et les conditions servent aux trois étapes, et
	// `community()` les garderait en cache statique pour tout le processus.
	$data->eStructureCommunity['community'] = $data->eCommunity;

};

new Page($start)
	->get('/reseau/{fqn}/formations', function($data) {

		learningPublicCurrent($data, \learning\DatePublicUi::urlList($data->eCommunity));

		// Sans date à venir, la liste rend son état vide et non un 404 : elle est intégrée au site du
		// réseau, qui ne doit jamais montrer une erreur (arbitrage 6 du plan).
		$data->cDate = \learning\DateLib::getPublicByCommunity($data->eStructureCommunity);
		\learning\DateEnrollmentLib::fillForCourse($data->cDate, \user\ConnectionLib::getOnline());

		$data->cCourse = \learning\CourseLib::getPublicOnlineByCommunity($data->eStructureCommunity);
		\learning\CourseLib::fillCounts($data->cCourse);
		// La carte du visiteur, que `getListOnline()` rend sans bande de progression : `done` n'y sert
		// qu'à `Course::isDone()`, et le compte d'un inscrit poserait l'accent d'une formation terminée
		// sur une carte qui ne dit aucune progression.
		\learning\ProgressLib::fillDone(new \user\User(), $data->cCourse);

		// L'accès à la FAQ n'est pas rendu dans l'intégration, où il mènerait l'iframe du site du
		// réseau hors de sa liste : la requête n'y part pas.
		$data->faqs = $data->embed ? 0 : \learning\FaqLib::countPublishedByCommunity($data->eStructureCommunity);

		throw new ViewAction($data);

	})
	/**
	 * Les questions fréquentes du réseau, la FAQ publiée que son équipe écrit, à côté de ses
	 * formations. Le rendu est celui de `/faq` (`FaqUi`), sous le bandeau et le thème du réseau,
	 * filtré par module et non par écran.
	 */
	->get('/reseau/{fqn}/faq', function($data) {

		learningPublicCurrent($data, \learning\DatePublicUi::urlFaq($data->eCommunity));

		$data->ccFaq = \learning\FaqLib::getPublishedByCommunity($data->eStructureCommunity);
		\learning\FaqLib::fillChapterPublic($data->ccFaq);
		$data->faqs = $data->ccFaq->countByDepth();

		// Le rail filtre par module : deux formations ont chacune leur « Module 1 », que seul le nom
		// de la formation distingue.
		$data->cccFaq = \learning\FaqLib::groupByChapter($data->ccFaq);
		$data->withCourse = (\learning\FaqLib::countCourses($data->cccFaq) > 1);

		throw new ViewAction($data);

	});

/**
 * L'essai et l'enregistrement de la barre de personnalisation, sur l'adresse du réseau et non sur
 * une de ses pages : le thème vaut pour toutes, accueil, formations et relevés de prix, et un réseau
 * qui ne tient que ses relevés le règle aussi (`CommunityLib::hasPublic()`). Sur le domaine du
 * réseau comme la barre elle-même : l'enregistrement ne traverse pas de domaine.
 */
new Page(function($data) {

		$data->eCommunity = \structure\CommunityLib::getByAddress(GET('fqn'));
		$data->eStructureCommunity = \structure\StructureLib::getById($data->eCommunity->empty() ? NULL : $data->eCommunity['structure']['id']);

		if(
			$data->eStructureCommunity->empty() or
			\learning\CommunityLib::hasPublic($data->eStructureCommunity) === FALSE
		) {
			throw new NotExistsAction();
		}

		$data->eStructureCommunity->validate('canManage');

	})
	->post('/reseau/{fqn}/:doCustomize', function($data) {

		\learning\CommunityLib::updateCustomize($data->eStructureCommunity, $_POST);

		throw new ReloadAction('learning', 'Community::customized');

	})
	/**
	 * Un réglage essayé dans la barre passe ici avant de recharger la page d'où il part : refusé, il
	 * s'affiche comme toute erreur du cadriciel ; accepté, la page se recharge avec tous les réglages
	 * dans l'adresse. Une origine inconnue renvoie aux formations.
	 */
	->post('/reseau/{fqn}/:doPreview', function($data) {

		$values = \learning\CommunityLib::checkCustomize($data->eCommunity, $_POST);

		$url = \learning\CustomizeUi::urlOrigin($data->eCommunity, GET('origin', \learning\CustomizeUi::ORIGINS, 'learning'));

		throw new RedirectAction(\learning\DatePublicUi::urlQuery($url, \learning\CustomizeUi::getQuery($values), FALSE), httpStatus: 'HTTP/1.1 302 Found');

	});

// Une page ouverte avant la pause poste encore : le refus porte alors son message, qui invite à
// revenir, là où la validation d'`acceptPublicCreate()` n'en a pas. Une date que la page publique ne
// montre plus est refusée avant, sans promettre de retour.
$acceptPause = function(\learning\DateEnrollment $e): bool {

	if($e->acceptPause() === FALSE) {
		throw new FailAction('learning\DateEnrollment::paused');
	}

	return TRUE;

};

new \learning\DateEnrollmentPage($start)
	->getCreateElement(function($data) {

		$eDate = \learning\DateLib::getById(GET('date', '?int'));

		if(
			$eDate->empty() or
			$eDate->acceptPublic($data->eStructureCommunity) === FALSE
		) {
			throw learningPublicNotFound($data);
		}

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->notEmpty()) {

			// Un compte qui lit la formation s'inscrit depuis sa fiche, le parcours du membre. C'est
			// aussi là que mène « Se connecter » de l'encart, qui revient sur cette adresse.
			if($eDate['course']->canRead()) {
				throw new RedirectAction(\Lime::getUrl().\learning\CourseUi::url($eDate['course'], $data->customizeQuery), httpStatus: 'HTTP/1.1 302 Found');
			}

			// Sa ligne ouverte sur la date se lit à la place des étapes, qu'elle vienne de cette page ou
			// de la fiche du temps où le compte était du réseau, sans jeton alors : jamais une page
			// d'erreur. Seulement en GET : un envoi sur une ligne déjà ouverte reste refusé par
			// `acceptPublicCreate()`.
			$eDateEnrollmentOnline = \learning\DateEnrollmentLib::getByDateAndUser($eDate, $eUser);

			if(
				\Route::getRequestMethod() === 'GET' and
				$eDateEnrollmentOnline->notEmpty() and
				$eDateEnrollmentOnline->isOpen()
			) {
				learningPublicCurrent($data, \learning\DatePublicUi::urlDate($data->eCommunity, $eDate));

				$data->eDateEnrollment = $eDateEnrollmentOnline;
				$data->eDateEnrollment['losesLessons'] = \learning\DateEnrollmentLib::losesLessons($data->eDateEnrollment);

				\learning\DateEnrollmentLib::fillLessons(new Collection([$data->eDateEnrollment]));

				throw new ViewAction($data, ':/reseau/{fqn}/inscription/{token}');
			}

		}

		return \learning\DateEnrollmentLib::getPublicCreateElement($eDate, $data->eStructureCommunity, $eUser);

	})
	/**
	 * Les trois étapes, une par adresse : la deuxième et la troisième par `?step=`, les cases de la
	 * deuxième portées par l'adresse. La troisième ne se sert qu'avec toutes les cases, c'est-à-dire
	 * depuis `doCommit`, qui les juge ; sinon, retour à la deuxième.
	 */
	->create(function($data) {

		learningPublicCurrent($data, \learning\DatePublicUi::urlDate($data->eCommunity, $data->e['date']));

		$eDate = $data->e['date'];

		// Inscriptions en pause : l'étape 1 se lit, qui le dit à la place de tout geste, et aucune
		// étape suivante ne se sert par son adresse. La pause passe avant le site de l'organisme, où
		// elle n'existe pas : y mener ferait croire qu'on s'inscrit ailleurs.
		// Inscription sur le site de l'organisme (lot annuaire) : la page d'une date y mène, en 302
		// parce que la formation peut rouvrir et qu'un 301 resterait dans le navigateur. Intégrée, elle
		// ne redirige pas l'iframe, que le site de l'organisme refuserait souvent de s'afficher dedans :
		// l'étape 1 se lit, avec le lien sortant à la place de « Continuer ».
		if($eDate->isEnrollmentPaused()) {

			$data->step = 1;

		} else if($eDate['course']->isExternal()) {

			if($data->embed === FALSE) {
				throw new RedirectAction($eDate->getExternalUrl(), httpStatus: 'HTTP/1.1 302 Found');
			}

			$data->step = 1;

		} else {

			$data->step = GET('step', 'int');

			if(in_array($data->step, [1, 2, 3], TRUE) === FALSE) {
				$data->step = 1;
			}

		}

		// `in_array()` strict et non `array_intersect()`, pour la raison du panneau du membre
		// (`dateEnrollment.p.php`) : une valeur forgée en tableau lèverait.
		$commitmentsPosted = GET('commitment', 'array');
		$data->commitments = array_values(array_filter($data->e->getCommitments(), fn(string $commitment) => in_array($commitment, $commitmentsPosted, TRUE)));

		if(
			$data->step === 3 and
			$data->commitments !== $data->e->getCommitments()
		) {
			throw new RedirectAction(\learning\DatePublicUi::urlQuery(\learning\DatePublicUi::urlDate($data->eCommunity, $eDate), ['step' => 2, 'commitment' => $data->commitments], $data->embed), httpStatus: 'HTTP/1.1 302 Found');
		}

		$data->cChapter = new Collection();
		$data->cStaff = new Collection();

		if($data->step === 1) {
			$data->cChapter = \learning\ChapterLib::getByCourse($eDate['course']);
			$data->cStaff = \learning\StaffLib::getByCourse($eDate['course']);
			$eDate['course']['eSupervisorContact'] = \learning\SupervisorLib::getContactByCourse($eDate['course']);
		}

		$data->online = $data->e['user']->notEmpty();

		if($data->step === 3) {
			$data->e['trainee'] = $data->online ? \learning\DateEnrollmentLib::getLastTrainee($data->e['user']) : NULL;
		}

		\learning\DateEnrollmentLib::fillCounts(new Collection([$eDate]));

		$data->status = \learning\DateEnrollmentLib::getStatusForNew($eDate, $data->eCommunity['learningConfirm']);

		throw new ViewAction($data);

	}, page: '/reseau/{fqn}/formations/{date}', validate: ['acceptPublicRead'])
	->create(function($data) {

		$fw = new FailWatch();

		$data->e->build(['committedAt', 'conditions'], $_POST);

		$fw->validate();

		// Les redirections qui suivent un POST gardent le 301 de `RedirectAction`, comme les relevés :
		// servies en ajax, elles deviennent une instruction et aucun navigateur ne les retient. Celles
		// qu'un GET atteint sont temporaires.
		throw new RedirectAction(\learning\DatePublicUi::urlQuery(\learning\DatePublicUi::urlDate($data->eCommunity, $data->e['date']), ['step' => 3, 'commitment' => $data->e->getCommitments()], $data->embed));

	}, method: 'post', page: '/reseau/{fqn}/formations/{date}/:doCommit', validate: ['acceptPublicRead', $acceptPause, 'acceptPublicCreate'])
	/**
	 * Écrit par `createPublic()` et non par `doCreate()`, qui appellerait `create()` : la ligne se
	 * relit par l'adresse, se compte par le plafond, et reçoit son jeton. La réponse est l'écran
	 * « envoyé » à la place des étapes, sans jeton ni geste d'annulation : le jeton ne part que dans
	 * l'e-mail, sans quoi quiconque tape une adresse lirait et annulerait la ligne qu'elle désigne.
	 */
	->create(function($data) {

		$fw = new FailWatch();

		$origin = $data->e['user']->empty() ? 'public' : 'publicOnline';

		$data->e->build(\learning\DateEnrollmentLib::getPropertiesCreate($origin), $_POST, new \PropertyList('create'));

		$fw->validate();

		\learning\DateEnrollmentLib::createPublic($data->e);

		$fw->validate();

		\learning\DateEnrollmentLib::fillCounts(new Collection([$data->e['date']]));
		\learning\DateEnrollmentLib::fillLessons(new Collection([$data->e]));
		\learning\DateEnrollmentLib::fillAccount($data->e);

		throw new ViewAction($data);

	}, method: 'post', page: '/reseau/{fqn}/formations/{date}/:doCreate', validate: ['acceptPublicRead', $acceptPause, 'acceptPublicCreate']);

/**
 * La ligne d'un jeton, seulement sur l'adresse de son réseau : un jeton inconnu ou venu d'un autre
 * réseau rend le même 404.
 */
$getByToken = function($data): \learning\DateEnrollment {

	$eDateEnrollment = \learning\DateEnrollmentLib::getByToken(GET('token'));

	if(
		$eDateEnrollment->empty() or
		$eDateEnrollment['date']['course']['community']['id'] !== $data->eStructureCommunity['id']
	) {
		throw learningPublicNotFound($data);
	}

	return $eDateEnrollment;

};

new Page($start)
	->get('/reseau/{fqn}/inscription/{token}', function($data) use($getByToken) {

		$data->eDateEnrollment = $getByToken($data);
		$data->eDateEnrollment['losesLessons'] = \learning\DateEnrollmentLib::losesLessons($data->eDateEnrollment);

		\learning\DateEnrollmentLib::fillLessons(new Collection([$data->eDateEnrollment]));

		learningPublicCurrent($data, \learning\DatePublicUi::urlEnrollment($data->eCommunity, $data->eDateEnrollment['token']));

		throw new ViewAction($data);

	})
	/**
	 * L'attestation de présence d'une formation sans leçon (lot L8f), par le jeton de la place comme
	 * l'annulation et l'avis : une place sans compte n'a pas d'autre accès. Le PDF se rend par
	 * l'adresse signée de `pdf:presence`.
	 */
	->get('/reseau/{fqn}/inscription/{token}/attestation', function($data) use($getByToken) {

		$eDateEnrollment = $getByToken($data);

		\learning\DateEnrollmentLib::fillLessons(new Collection([$eDateEnrollment]));

		$eDateEnrollment->validate('acceptPresenceAttestation');

		$content = \main\PdfLib::build('/learning/pdf:presence?id='.$eDateEnrollment['id'], footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, \learning\AttestationUi::getPresenceFilename($eDateEnrollment));

	});

new \learning\DateEnrollmentPage($start)
	->getElement($getByToken)
	->write('/reseau/{fqn}/inscription/{token}/:doCancel', function($data) {

		\learning\DateEnrollmentLib::cancel($data->e, \learning\DateEnrollment::USER);

		throw new RedirectAction(\learning\DatePublicUi::urlQuery(\learning\DatePublicUi::urlEnrollment($data->eCommunity, $data->e['token']), [], $data->embed));

	}, validate: ['acceptCancel']);

/**
 * L'avis par le lien d'une inscription (lot L15c) : le jeton tient lieu de droit, comme pour
 * l'annulation : le droit se pèse sur le compte de la place (`canCreateByLink`), et l'état sur la
 * ligne et l'avis (`acceptCreate`, `acceptUpdate`, `acceptDelete`). L'avis est
 * celui de la personne, par son compte ou par son adresse (`ReviewLib::getByPerson()`), et à défaut
 * celui à écrire sur cette ligne. Des routes de `Page` et non de `ReviewPage` : l'élément se lit
 * par la personne et non par un identifiant, et la même route écrit un avis neuf ou modifie
 * l'existant, chacun sous son `accept*`.
 */
$getReview = function(\learning\DateEnrollment $eDateEnrollment): \learning\Review {

	$eReview = \learning\ReviewLib::getByPerson($eDateEnrollment);

	return $eReview->empty()
		? \learning\ReviewLib::getCreateElementByDateEnrollment($eDateEnrollment)
		: $eReview;

};

new Page($start)
	->get('/reseau/{fqn}/inscription/{token}/avis', function($data) use($getByToken, $getReview) {

		$data->eDateEnrollment = $getByToken($data);

		learningPublicCurrent($data, \learning\ReviewUi::urlPublic($data->eCommunity, $data->eDateEnrollment['token']));

		$data->eReview = $getReview($data->eDateEnrollment);

		throw new ViewAction($data);

	})
	->post('/reseau/{fqn}/inscription/{token}/avis/:doReview', function($data) use($getByToken, $getReview) {

		$data->eDateEnrollment = $getByToken($data);
		$data->eReview = $getReview($data->eDateEnrollment);

		$fw = new FailWatch();

		if($data->eReview->exists()) {

			$data->eReview->validate('acceptUpdate');

			$properties = \learning\ReviewLib::getPropertiesUpdate();
			$data->eReview->build($properties, $_POST);

			$fw->validate();

			\learning\ReviewLib::update($data->eReview, $properties);

		} else {

			$data->eReview->validate('canCreateByLink', 'acceptCreate');

			$data->eReview->build(\learning\ReviewLib::getPropertiesCreate(), $_POST, new \PropertyList('create'));

			$fw->validate();

			\learning\ReviewLib::create($data->eReview);

		}

		$data->deleted = FALSE;

		throw new ViewAction($data);

	})
	->post('/reseau/{fqn}/inscription/{token}/avis/:doDelete', function($data) use($getByToken, $getReview) {

		$data->eDateEnrollment = $getByToken($data);
		$data->eReview = $getReview($data->eDateEnrollment);

		if($data->eReview->exists() === FALSE) {
			throw new NotExpectedAction('No review to delete');
		}

		\learning\ReviewLib::delete($data->eReview);

		$data->deleted = TRUE;

		throw new ViewAction($data);

	});

/**
 * La ligne sans jeton que le compte connecté tient sur une date, venue de la fiche du temps où il
 * était du réseau : il l'annule d'ici, par le même `cancel()` que le jeton, le droit étant alors
 * celui du compte (`canCancel()`). La ligne se lit par le compte de la session, jamais par un
 * identifiant posté : sans session, ou sous un autre compte, la date n'a pas de ligne à annuler.
 */
new \learning\DateEnrollmentPage($start)
	->getElement(function($data): \learning\DateEnrollment {

		$eDate = \learning\DateLib::getById(GET('date', '?int'));

		if(
			$eDate->empty() or
			$eDate['course']['community']['id'] !== $data->eStructureCommunity['id']
		) {
			throw learningPublicNotFound($data);
		}

		$eDateEnrollment = \learning\DateEnrollmentLib::getByDateAndUser($eDate, \user\ConnectionLib::getOnline());

		if($eDateEnrollment->empty()) {
			throw learningPublicNotFound($data);
		}

		return $eDateEnrollment;

	})
	->write('/reseau/{fqn}/formations/{date}/:doCancel', function($data) {

		\learning\DateEnrollmentLib::cancel($data->e, \learning\DateEnrollment::USER);

		$data->e = \learning\DateEnrollmentLib::getById($data->e['id'], \learning\DateEnrollment::getSelection());

		\learning\DateEnrollmentLib::fillLessons(new Collection([$data->e]));

		throw new ViewAction($data);

	}, validate: ['canCancel', 'acceptCancel']);

/**
 * Le script que le réseau colle sur son site : il pose l'iframe de la liste dans le bloc `target`,
 * et règle sa hauteur sur celle que la page lui envoie (`LearningPublic`). Modèle
 * `shop/page/public.p.php`, `embed-full.js`. En `@priority` : un fqn admet le point, et
 * `/reseau/{fqn}` prendrait sinon l'adresse.
 */
new Page()
	->get([[
		'/reseau/embed-learning.js',
		'@priority' => 1
	]], function($data) {

		$eStructureCommunity = \structure\StructureLib::getById(GET('id', '?int'));

		// Le réseau des tutoriels n'a pas de formation à laquelle on s'inscrit, donc rien à intégrer
		// sur un site : ses formations se lisent depuis `/tutoriels`.
		if(
			$eStructureCommunity->empty() or
			$eStructureCommunity->isNetwork() === FALSE or
			$eStructureCommunity['id'] === \learning\LearningSetting::$structureTutorial or
			$eStructureCommunity->acceptLearning() === FALSE
		) {
			throw new NotExistsAction();
		}

		$target = GET('target');

		if(
			$target === '' or
			toFqn($target) !== $target
		) {
			throw new NotExpectedAction();
		}

		$url = \learning\DatePublicUi::urlList(\structure\CommunityLib::getByStructure($eStructureCommunity)).'?embed=1';
		$key = random_int(0, 1000000);

		header('Content-Type: application/javascript');

		// Le message n'est lu que s'il vient de cette iframe : la page hôte en reçoit d'autres, de
		// ses propres cadres.
		echo <<<END

		const otfLearningIframe$key = document.createElement("iframe");
		otfLearningIframe$key.src = "$url";
		otfLearningIframe$key.style.width = "1px";
		otfLearningIframe$key.style.minWidth = "100%";
		otfLearningIframe$key.style.border = "none";
		otfLearningIframe$key.style.height = "500px";
		document.getElementById("$target").appendChild(otfLearningIframe$key);

		window.addEventListener("message", function(e) {

			if(e.source !== otfLearningIframe$key.contentWindow || !e.data || !e.data.learningHeight) {
				return;
			}

			otfLearningIframe$key.style.height = e.data.learningHeight + "px";

		}, false);
END;

	});
?>
