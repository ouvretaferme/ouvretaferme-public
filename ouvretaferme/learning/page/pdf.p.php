<?php
/**
 * Les documents PDF des formations, rendus par le navigateur sans tête de `main\PdfLib::build()`
 * sur une adresse signée. Aucun compte n'y est connecté : la route qui signe l'adresse a déjà jugé
 * le droit et l'état, celle-ci relit seulement ce que le document montre.
 */
new Page()
	->remote('attestation', 'selling', function($data) {

		$data->eCourse = \learning\CourseLib::getById(GET('id'));
		$data->eUser = \user\UserLib::getById(GET('user'), ['id', 'firstName', 'lastName']);

		$eEnrollment = \learning\EnrollmentLib::getByUserAndCourse($data->eUser, $data->eCourse);

		if($eEnrollment->empty()) {
			throw new NotExpectedAction('Cannot generate the attestation of a course without enrollment');
		}

		$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
		\learning\LessonLib::fillChapterFormat($data->cChapter);

		$cUser = new Collection([$data->eUser['id'] => $data->eUser]);

		\learning\ProgressLib::fillTrack($data->eCourse, $data->cChapter, $cUser);
		\learning\ProgressLib::fillDetail($data->eCourse, $data->cChapter, $cUser);
		\learning\ProgressLib::fillSummary($data->cChapter, $cUser);
		\learning\DateEnrollmentLib::fillAttended($data->eCourse, $cUser);

		// La ferme au nom de laquelle le compte suit la formation, vide à titre personnel.
		$data->eStructure = $eEnrollment['structure']->empty()
			? new \structure\Structure()
			: \structure\StructureLib::getById($eEnrollment['structure']);

		$data->eStructureIssuer = \learning\CourseLib::getIssuer($data->eCourse, $data->eUser);
		$data->issuedAt = \Clock::date();

		throw new ViewAction($data);

	})
	/**
	 * Le relevé de suivi d'un stagiaire (lot L8d), au nom du groupe auteur : seules les formations
	 * de groupe ou de réseau tiennent un journal. Le stagiaire d'un groupe n'a pas d'inscription,
	 * il est membre d'une promotion partagée : la route qui signe l'adresse a jugé qu'il est suivi.
	 */
	->remote('report', 'selling', function($data) {

		$data->eCourse = \learning\CourseLib::getById(GET('id'))->validate('acceptReading');
		$data->eUser = \user\UserLib::getById(GET('user'), ['id', 'firstName', 'lastName']);

		if($data->eUser->empty()) {
			throw new NotExpectedAction('Cannot generate the report of an unknown user');
		}

		$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
		$data->eCourse['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);

		$cUser = new Collection([$data->eUser['id'] => $data->eUser]);

		\learning\ProgressLib::fillTrack($data->eCourse, $data->cChapter, $cUser);
		\learning\ProgressLib::fillDetail($data->eCourse, $data->cChapter, $cUser);
		\learning\ProgressLib::fillSummary($data->cChapter, $cUser);
		\learning\DateEnrollmentLib::fillAttended($data->eCourse, $cUser);
		\learning\ReadingLib::fillMeasured($data->eCourse, $data->cChapter, $cUser);

		$data->journal = \learning\ReadingLib::getJournal($data->eCourse, $data->cChapter, $data->eUser);

		$data->eStructureCommunity = \structure\StructureLib::getById($data->eCourse['community']);
		$data->issuedAt = \Clock::date();

		throw new ViewAction($data);

	})
	/**
	 * L'attestation de présence d'une place pointée à une date d'une formation sans leçon (lot L8f),
	 * au nom du réseau. L'état se rejuge ici : une place dépointée entre la signature de l'adresse et
	 * le rendu ne reçoit pas d'attestation.
	 */
	->remote('presence', 'selling', function($data) {

		$data->eDateEnrollment = \learning\DateEnrollmentLib::getById(GET('id'), \learning\DateEnrollment::getSelection());

		if($data->eDateEnrollment->empty()) {
			throw new NotExpectedAction('Cannot generate the attestation of an unknown enrollment');
		}

		\learning\DateEnrollmentLib::fillLessons(new Collection([$data->eDateEnrollment]));

		if($data->eDateEnrollment->acceptPresenceAttestation() === FALSE) {
			throw new NotExpectedAction('Cannot generate the attestation of an enrollment without presence');
		}

		$data->eStructureCommunity = \structure\StructureLib::getById($data->eDateEnrollment['date']['course']['community']);
		$data->issuedAt = \Clock::date();

		throw new ViewAction($data);

	})
	/**
	 * La feuille d'émargement d'une date sur place (lot L8e), au nom du réseau : ses personnes
	 * confirmées, ses encadrants, une colonne par demi-journée.
	 */
	->remote('sheet', 'selling', function($data) {

		$data->eDate = \learning\DateLib::getById(GET('id'));

		if($data->eDate->empty()) {
			throw new NotExpectedAction('Cannot generate the sheet of an unknown date');
		}

		$data->cDateEnrollment = \learning\DateEnrollmentLib::getConfirmedByDate($data->eDate);
		$data->cStaff = \learning\StaffLib::getByCourse($data->eDate['course']);
		$data->eStructureCommunity = \structure\StructureLib::getById($data->eDate['course']['community']);
		$data->halfDays = \learning\DateLib::getHalfDays($data->eDate);
		$data->issuedAt = \Clock::date();

		throw new ViewAction($data);

	});
?>
