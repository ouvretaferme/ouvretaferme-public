<?php
/**
 * Les dates d'une formation de réseau, depuis son éditeur : on les ajoute, on les modifie, on les
 * annule et on les supprime dans un panneau. Elles se lisent sur la fiche et sur les cartes.
 */
new \learning\DatePage()
	->getCreateElement(function($data) {

		$eCourse = \learning\CourseLib::getById(INPUT('course'))->validate('canUpdate', 'acceptUpdate');

		return new \learning\Date([
			'course' => $eCourse,
			// Une formation en ligne n'a pas de date : `Date::acceptWrite()` la refuse ensuite.
			'cDateSequence' => $eCourse->isDated() ? new Collection([$eCourse->getDefaultSequence()]) : new Collection(),
			'startAt' => NULL,
			'endAt' => NULL,
			'minutes' => NULL,
			'priceFunded' => NULL,
			'price' => NULL,
			'funding' => NULL,
			'fundingNote' => NULL
		]);

	})
	->create()
	/**
	 * La première date posée depuis le parcours de création le termine : on arrive dans l'éditeur de
	 * la formation. `origin` voyage par le formulaire de l'étape.
	 */
	->doCreate(function($data) {

		if(POST('origin') === 'start') {
			throw new RedirectAction(\learning\CourseUi::urlEdition($data->e['course']).'?tab=dates&success=learning\\Course::created');
		}

		throw new ReloadAction('learning', 'Date::created');

	})
	->update()
	->doUpdate(fn() => throw new ReloadAction('learning', 'Date::updated'))
	->update(function($data) {

		\learning\DateEnrollmentLib::fillCounts(new Collection([$data->e]));

		\learning\DateEnrollmentLib::fillLosesLessonsByDate($data->e);

		throw new ViewAction($data);

	}, page: 'cancel', addValidate: ['acceptCancel'])
	->write('doCancel', function($data) {

		\learning\DateLib::cancel($data->e);

		throw new ReloadAction('learning', 'Date::canceled');

	}, addValidate: ['acceptCancel'])
	->doDelete(fn() => throw new ReloadAction('learning', 'Date::deleted'));

/**
 * L'offre d'une date (lot L15a), depuis l'onglet Diffusion de sa formation. Le choix ferme les
 * offres que le réseau ne peut pas encore prendre, d'où le compte posé sur la formation, et
 * « Sans offre » sur la dernière date offerte d'une formation au catalogue ; les étapes
 * `Date::offer.account` et `offer.catalog` refusent le même POST de leur côté.
 */
new \learning\DatePage()
	->update(function($data) {

		$data->e['course']['hasPayoutAccount'] = \learning\CommunityLib::hasPayoutAccount($data->e['course']['community']);
		$data->e['holdsCatalog'] = \learning\DateLib::holdsCatalog($data->e);

		throw new ViewAction($data);

	}, page: 'updateOffer', validate: ['canUpdate', 'acceptUpdateOffer'])
	->doUpdate(fn() => throw new ReloadAction('learning', 'Date::offerUpdated'), propertiesUpdate: ['offer'], page: 'doUpdateOffer', validate: ['canUpdate', 'acceptUpdateOffer'])
	/**
	 * La pause des nouvelles inscriptions de la date seule, depuis son interrupteur : seule sa bande
	 * est redessinée, qui reste en place puisque la bascule ne change pas ce qui la montre. Fermée par
	 * celle de la formation, la date ne se règle pas (`Date::acceptUpdateEnrollmentPaused()`).
	 */
	->doUpdate(fn($data) => throw new ViewAction($data), propertiesUpdate: ['enrollmentPaused'], page: 'doUpdateEnrollmentPaused', validate: ['canUpdate', 'acceptUpdateEnrollmentPaused']);

/**
 * L'ouverture du quiz d'une date, depuis la liste de ses inscrits. Le geste vaut dès le premier jour
 * et jusqu'après la date : il ne passe donc pas par `update()`, dont `acceptUpdate()` le fermerait à
 * la fin de la date, mais par le droit d'écrire la formation et `acceptOpenQuiz()`. Le nombre de
 * confirmées et la leçon quiz se posent avant la validation, qui les juge.
 */
new \learning\DatePage()
	->applyElement(function($data, \learning\Date $e) {

		\learning\DateEnrollmentLib::fillCounts(new Collection([$e]));
		$e['eLessonQuiz'] = \learning\LessonLib::getLastQuiz($e['course']);

	})
	->read('openQuiz', fn($data) => throw new ViewAction($data), validate: ['canWrite', 'acceptWrite'], addValidate: ['acceptOpenQuiz'])
	->write('doOpenQuiz', function($data) {

		$fw = new FailWatch();

		\learning\DateLib::openQuiz($data->e);

		$fw->validate();

		throw new ReloadAction('learning', 'Date::quizOpened');

	}, addValidate: ['acceptOpenQuiz']);

/**
 * L'envoi du lien de la visio aux personnes confirmées, depuis la carte de la date dans l'éditeur.
 * Comme l'ouverture du quiz, le geste a son état à lui : `acceptSendLink()`, qui le laisse ouvert
 * jusqu'à la fin de la date et le ferme dès que le lien est parti. Le nombre de personnes qui le
 * recevront se pose avant la validation, qui le juge, et le panneau le chiffre.
 */
new \learning\DatePage()
	->applyElement(fn($data, \learning\Date $e) => \learning\DateEnrollmentLib::fillLinkRecipients(new Collection([$e])))
	->read('sendLink', fn($data) => throw new ViewAction($data), validate: ['canWrite', 'acceptWrite'], addValidate: ['acceptSendLink'])
	->write('doSendLink', function($data) {

		$fw = new FailWatch();

		\learning\DateLib::sendLink($data->e);

		$fw->validate();

		throw new ReloadAction('learning', 'Date::linkMailed');

	}, addValidate: ['acceptSendLink']);

/**
 * L'envoi des attestations de présence d'une date finie d'une formation sans leçon (lot L8f), depuis
 * la liste de ses inscrits. Les places de la date se lisent avant la validation : le panneau ne
 * s'ouvre que s'il reste quelqu'un à qui écrire (`acceptSendAttestations()`), et le chiffre ;
 * l'envoi ne juge que la date (`hasPresenceAttestations()`), puis dit par un message qu'il refuse
 * une formation sans objectifs (`acceptAttestations()`), que l'attestation doit citer, ou qu'il n'a
 * rien envoyé.
 * Le geste se refait pour les personnes pointées après un envoi ; une place ne reçoit la sienne
 * qu'une fois (`DateEnrollmentLib::sendAttestations()`).
 */
new \learning\DatePage()
	->applyElement(fn($data, \learning\Date $e) => \learning\DateEnrollmentLib::fillAttestationRecipients($e, \learning\DateEnrollmentLib::getByDate($e)))
	->read('sendAttestations', fn($data) => throw new ViewAction($data), validate: ['canWrite', 'acceptWrite'], addValidate: ['acceptSendAttestations'])
	->write('doSendAttestations', function($data) {

		$data->e->validate('acceptAttestations', onFail: fn() => throw new FailAction('learning\Date::attestationsObjectives'));

		if(\learning\DateEnrollmentLib::sendAttestations($data->e) === 0) {
			throw new FailAction('learning\Date::attestationsAlreadyMailed');
		}

		throw new ReloadAction('learning', 'Date::attestationsMailed');

	}, addValidate: ['hasPresenceAttestations']);

/**
 * La durée de formation d'une date finie (lot L8e), éditée sur place sous le titre de la liste de
 * ses inscrits : hors de `acceptUpdate()`, fermé sur une date passée. La réponse redessine le
 * tableau des places, dont les heures des présentes suivent la durée, et la ligne de durée.
 */
new \learning\DatePage()
	->quick(['minutes'], action: function($data) {

		$data->e = \learning\DateLib::getById($data->e['id']);

		\learning\DateEnrollmentLib::fillCounts(new Collection([$data->e]));
		\learning\DateEnrollmentLib::fillAttendedByDate($data->e);

		$data->cDateEnrollment = \learning\DateEnrollmentLib::getByDate($data->e);

		\learning\DateEnrollmentLib::fillAttestationRecipients($data->e, $data->cDateEnrollment);

		if($data->e->isQuizOpened()) {
			$data->e['eLessonQuiz'] = \learning\LessonLib::getLastQuiz($data->e['course']);
			\learning\ProgressLib::fillDateQuiz($data->e, $data->cDateEnrollment);
		}

		throw new ViewAction($data, ':minutes');

	}, validate: ['canWrite', 'acceptAttendance']);
?>
