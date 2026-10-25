<?php
/**
 * Le suivi des stagiaires d'une formation de groupe (D2) : la grille de ce que chacun a fait, leçon
 * par leçon, la vue d'une leçon où l'équipe le constate (F23), ses deux exports, le tableau
 * (F28) et le détail par leçon, le journal de lecture d'un stagiaire (L8c) et son relevé en PDF (L8d).
 *
 * L'écran est un écran du groupe : il prend son gabarit, et la barre « Changer » de ses promotions
 * y choisit celle qu'on regarde. Elle ne propose que les promotions auxquelles la formation est
 * ouverte, bornées par la portée de l'encadrant (`SectionLib::getScope()`) : un rôle rangé dans
 * une seule promotion ne voit, n'exporte, ne coche et ne lit le journal que de ses stagiaires.
 * La même liste sert les huit routes, lue une fois ici.
 *
 * Une formation de réseau n'a pas de promotion : ses stagiaires sont les comptes inscrits
 * (`EnrollmentLib::getLearners()`), et la barre choisit une ferme parmi celles au nom desquelles
 * ils se sont inscrits. Un rôle borné à des promotions n'y borne rien : tout encadrant que
 * `canUpdate` admet voit tous les inscrits.
 */

/**
 * Les stagiaires que l'encadrant suit, sous la portée donnée ou toute entière : les membres des
 * promotions partagées pour un groupe de formation, les comptes inscrits pour un réseau.
 */
$getLearners = function($data, \structure\Section $eSection = new \structure\Section(), \structure\Structure $eStructure = new \structure\Structure()): Collection {

	if($data->eStructure->isNetwork()) {
		return \learning\EnrollmentLib::getLearners($data->eCourse, $eStructure);
	}

	return \learning\ShareLib::getLearners($data->eCourse, $data->cSectionTrack, $eSection);

};

/**
 * La ferme que l'adresse regarde, parmi celles de la barre : une autre, ou aucune, vaut toutes.
 */
$getStructureTrack = function($data): \structure\Structure {

	$structure = GET('structure', 'int');

	if(
		$structure === NULL or
		$data->cStructureTrack->offsetExists($structure) === FALSE
	) {
		return new \structure\Structure();
	}

	return $data->cStructureTrack[$structure]['structure'];

};

/**
 * La leçon et le stagiaire que la case désigne, tous deux vérifiés ici et non par l'écran, le POST
 * se forgeant : la leçon est de cette formation, le stagiaire parmi ceux que l'encadrant suit.
 */
$getTarget = function($data) use($getLearners): array {

	$eLesson = \learning\LessonLib::getById(POST('lesson', 'int'));

	if(
		$eLesson->empty() or
		$eLesson['course']['id'] !== $data->eCourse['id']
	) {
		throw new NotAllowedAction();
	}

	$cUser = $getLearners($data);

	$user = POST('user', 'int');

	if($cUser->offsetExists($user) === FALSE) {
		throw new NotAllowedAction();
	}

	return [$eLesson, $cUser[$user]];

};

/**
 * La ligne du stagiaire dans la vue de la leçon, seule redessinée : c'est tout ce que la case
 * change. La leçon est relue dans le programme, où `fillTrack()` pose son nombre de questions : la
 * ligne n'a les colonnes du quiz que si elle en a un.
 */
$renderRow = function($data, \learning\Lesson $eLesson, \user\User $eUser): void {

	$data->eUser = $eUser;

	$cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
	\learning\ProgressLib::fillTrack($data->eCourse, $cChapter, new Collection([$eUser['id'] => $eUser]));

	$data->eLesson = \learning\ChapterLib::findLesson($cChapter, $eLesson['id']);

	throw new ViewAction($data, ':doTrack');

};

/**
 * La grille et la vue d'une leçon lisent les mêmes stagiaires, sous la promotion choisie : la
 * promotion est toujours le dernier segment de l'adresse, que le sélecteur du groupe retire.
 */
$loadTrack = function($data) use($getLearners, $getStructureTrack): void {

	$data->eSectionTrack = \structure\SectionLib::getSelected($data->cSectionTrack, GET('section'));
	$data->eStructureTrack = $getStructureTrack($data);

	$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
	$data->eCourse['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);

	$data->eCourse['shares'] = \learning\ShareLib::countByCourse($data->eCourse);

	// Les demandes à confirmer passent en tête, sans la portée de la barre : c'est la file du réseau
	// entier, qu'une ferme choisie ne doit pas cacher.
	if($data->eStructure->isNetwork()) {
		$data->cEnrollmentRequest = \learning\EnrollmentLib::getRequestsByCourse($data->eCourse);
	} else {
		$data->cEnrollmentRequest = new Collection();
	}

	$data->cUser = $getLearners($data, $data->eSectionTrack, $data->eStructureTrack);
	\learning\ProgressLib::fillTrack($data->eCourse, $data->cChapter, $data->cUser);
	\learning\ReadingLib::fillLastSeen($data->eCourse, $data->cUser);

	// Le gabarit range les promotions closes à part dans son sélecteur : il reçoit sa propre
	// liste, que l'écran ne relit pas. Le filtre agit ici, le tableau ne montrant que les
	// stagiaires de la promotion choisie.
	$data->cSection = $data->cSectionTrack;
	$data->sectionApply = TRUE;

};

/**
 * Les deux exports, le tableau et le détail par leçon, sous la même portée que la grille : la
 * promotion ou la ferme choisie, sinon tous les stagiaires que l'encadrant suit. Le nom du fichier
 * dit cette portée. La garde de l'administrateur reste dans chaque route :
 * `ImportExportGuardSentinelAi` ne lit une garde que dans le gestionnaire ou le constructeur.
 */
$loadExport = function($data, string $file) use($getLearners, $getStructureTrack): void {

	$eSection = \structure\SectionLib::getSelected($data->cSectionTrack, GET('section'));
	$eStructure = $getStructureTrack($data);

	$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);

	$data->cUser = $getLearners($data, $eSection, $eStructure);
	\learning\ProgressLib::fillTrack($data->eCourse, $data->cChapter, $data->cUser);
	\learning\ReadingLib::fillLastSeen($data->eCourse, $data->cUser);

	$data->file = $file.'-'.$data->eCourse['id'];

	if($eSection->notEmpty()) {
		$data->file .= '-'.$eSection['id'];
	} else if($eStructure->notEmpty()) {
		$data->file .= '-ferme-'.$eStructure['id'];
	}

};

/**
 * Le journal de lecture d'un stagiaire (lot L8c), le même pour l'équipe et pour lui : ce qu'il a
 * validé, la durée retenue (`ProgressLib::fillSummary()`, la définition de l'attestation, plus les
 * heures pointées de `DateEnrollmentLib::fillAttended()`), le temps
 * mesuré, puis ses séances jour par jour. `$data->eUser` est le stagiaire, `fillTrack()` y pose son
 * inscription, dont le panneau nomme la structure.
 */
$loadJournal = function($data): void {

	$data->cChapter = \learning\ChapterLib::getByCourse($data->eCourse);
	$data->eCourse['lessons'] = \learning\ChapterLib::countLessons($data->cChapter);

	$cUser = new Collection([$data->eUser['id'] => $data->eUser]);

	\learning\ProgressLib::fillTrack($data->eCourse, $data->cChapter, $cUser);
	\learning\ProgressLib::fillDetail($data->eCourse, $data->cChapter, $cUser);
	\learning\ProgressLib::fillSummary($data->cChapter, $cUser);
	\learning\DateEnrollmentLib::fillAttended($data->eCourse, $cUser);
	\learning\ReadingLib::fillMeasured($data->eCourse, $data->cChapter, $cUser);

	$data->journal = \learning\ReadingLib::getJournal($data->eCourse, $data->cChapter, $data->eUser);

};

/**
 * Le stagiaire dont l'équipe lit le journal ou tire le relevé, parmi ceux que l'encadrant suit, et
 * lui seul : l'adresse se forge, et un rôle borné à une promotion ne lit pas le journal d'un
 * stagiaire d'une autre.
 */
$getLearner = function($data) use($getLearners): \user\User {

	$cUser = $getLearners($data);

	$user = GET('user', 'int');

	if($cUser->offsetExists($user) === FALSE) {
		throw new NotAllowedAction();
	}

	return $cUser[$user];

};

/**
 * Le relevé de suivi en PDF (lot L8d), rendu sur l'adresse signée de `pdf:report` : la route qui
 * l'appelle a déjà jugé que le compte peut le lire.
 */
$buildReport = function(\learning\Course $eCourse, \user\User $eUser): void {

	$content = \main\PdfLib::build('/learning/pdf:report?id='.$eCourse['id'].'&user='.$eUser['id'], footer: \main\PdfUi::getPagingFooter());

	if($content === NULL) {
		throw new NotExistsAction();
	}

	throw new PdfAction($content, \learning\ReportUi::getFilename($eCourse, $eUser));

};

new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eCourse = \learning\CourseLib::getById(INPUT('id'));
		$data->eCourse->validate('canUpdate', 'acceptTrack');

		$data->eStructure = \structure\StructureLib::getById($data->eCourse['community']);

		if($data->eStructure->isNetwork()) {
			$data->cSectionTrack = new Collection();
			$data->cStructureTrack = \learning\EnrollmentLib::getStructuresByCourse($data->eCourse);
		} else {
			$data->cSectionTrack = \learning\ShareLib::getSectionsForTrack($data->eCourse, \structure\SectionLib::getScope($data->eStructure));
			$data->cStructureTrack = new Collection();
		}

	})
	->get(['/formation/{id}/suivi', '/formation/{id}/suivi/{section@int}', '/formation/{id}/suivi/ferme/{structure@int}'], function($data) use($loadTrack) {

		$loadTrack($data);

		$data->eLesson = new \learning\Lesson();

		throw new CommunityAction($data);

	})
	->get(['/formation/{id}/suivi/lecon/{lesson@int}', '/formation/{id}/suivi/lecon/{lesson@int}/{section@int}', '/formation/{id}/suivi/lecon/{lesson@int}/ferme/{structure@int}'], function($data) use($loadTrack) {

		$loadTrack($data);

		$data->eLesson = \learning\ChapterLib::findLesson($data->cChapter, GET('lesson', 'int'));

		if($data->eLesson->empty()) {
			throw new NotExistsAction();
		}

		throw new CommunityAction($data);

	})
	->post('doCheck', function($data) use($getTarget, $renderRow) {

		[$eLesson, $eUser] = $getTarget($data);

		\learning\ProgressLib::check($data->eCourse, $eLesson, $eUser);

		$renderRow($data, $eLesson, $eUser);

	})
	->post('doUncheck', function($data) use($getTarget, $renderRow) {

		[$eLesson, $eUser] = $getTarget($data);

		\learning\ProgressLib::uncheck($eLesson, $eUser);

		$renderRow($data, $eLesson, $eUser);

	})
	->get('journal', function($data) use($getLearner, $loadJournal) {

		$data->eUser = $getLearner($data);

		$loadJournal($data);

		throw new ViewAction($data);

	})
	->get('report', function($data) use($getLearner, $buildReport) {

		$buildReport($data->eCourse, $getLearner($data));

	})
	->get('export', function($data) use($loadExport) {

		$data->eStructure->canManage() ?: throw new NotAllowedAction();

		$loadExport($data, 'suivi');

		throw new CsvAction(new \learning\ProgressUi()->getExport($data->cChapter, $data->cUser), $data->file.'.csv');

	})
	->get('exportDetail', function($data) use($loadExport) {

		$data->eStructure->canManage() ?: throw new NotAllowedAction();

		$loadExport($data, 'suivi-detail');

		\learning\ProgressLib::fillDetail($data->eCourse, $data->cChapter, $data->cUser);
		\learning\ReadingLib::fillMeasured($data->eCourse, $data->cChapter, $data->cUser);

		throw new CsvAction(new \learning\ProgressUi()->getExportDetail($data->cChapter, $data->cUser), $data->file.'.csv');

	});

/**
 * « Mon suivi » : le stagiaire relit son propre journal. L'inscription suffit à le lui ouvrir, y
 * compris quand la fenêtre de sa promotion est close : c'est ce qu'il a fait, pas une leçon à lire.
 */
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eCourse = \learning\CourseLib::getById(INPUT('id'))->validate('acceptLearning', 'acceptReading');

	})
	->get('mine', function($data) use($loadJournal) {

		$data->eUser = \user\ConnectionLib::getOnline();

		\learning\EnrollmentLib::getByUserAndCourse($data->eUser, $data->eCourse)->validate('canRead');

		$loadJournal($data);

		throw new ViewAction($data);

	})
	->get('reportMine', function($data) use($buildReport) {

		$eUser = \user\ConnectionLib::getOnline();

		\learning\EnrollmentLib::getByUserAndCourse($eUser, $data->eCourse)->validate('canRead');

		$buildReport($data->eCourse, $eUser);

	});
?>
