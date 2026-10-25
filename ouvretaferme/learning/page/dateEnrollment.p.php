<?php
/**
 * L'inscription à une date d'une formation de réseau, et la liste de ses inscrits.
 *
 * La personne s'inscrit depuis la pile de la date sur la fiche, et s'y désinscrit. Le réseau tient
 * la liste d'une date : il confirme une demande, donne une place à qui attend, annule une
 * inscription, et exporte la liste. Tous les écrans du réseau passent par `DateEnrollment::canManage()`,
 * qui est le droit d'écrire la formation.
 */
/**
 * La réponse du pointage (lot L8e) : la ligne de la personne et le compte des présentes sous le
 * titre, seuls redessinés. La date est relue pour son compte de confirmées, et le résultat au quiz
 * de la seule ligne, qui en a une colonne dès l'ouverture. Sans leçon, l'état des attestations de
 * présence se relit sur toutes les places de la date, et l'envoi se redessine avec lui (lot L8f).
 */
$renderAttendance = function($data): void {

	$data->eDate = \learning\DateLib::getById($data->e['date']['id']);

	\learning\DateEnrollmentLib::fillCounts(new Collection([$data->eDate]));
	\learning\DateEnrollmentLib::fillAttendedByDate($data->eDate);

	$cDateEnrollment = \learning\DateEnrollmentLib::getByDate($data->eDate);
	\learning\DateEnrollmentLib::fillAttestationRecipients($data->eDate, $cDateEnrollment);

	$data->eDateEnrollment = $cDateEnrollment->find(fn(\learning\DateEnrollment $e) => $e['id'] === $data->e['id'])->first();

	if($data->eDate->isQuizOpened()) {
		$data->eDate['eLessonQuiz'] = \learning\LessonLib::getLastQuiz($data->eDate['course']);
		\learning\ProgressLib::fillDateQuiz($data->eDate, new Collection([$data->eDateEnrollment]));
	}

	throw new ViewAction($data, ':attendance');

};

new \learning\DateEnrollmentPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$eDate = \learning\DateLib::getById(INPUT('date'))->validate();

		$eDateEnrollment = \learning\DateEnrollmentLib::getCreateElement($eDate, \user\ConnectionLib::getOnline());

		// Le bouton ne se propose plus sur une date annulée, passée, en pause ou déjà prise, mais une
		// fiche ouverte la veille le montre encore : le refus porte un message, là où la validation
		// d'`acceptCreate()` par la page n'en a pas. Le refus définitif passe avant la pause, dont le
		// message invite à revenir.
		if($eDateEnrollment->acceptOpen() === FALSE) {
			throw new FailAction('learning\DateEnrollment::closed');
		} else if($eDateEnrollment->acceptPause() === FALSE) {
			throw new FailAction('learning\DateEnrollment::paused');
		}

		return $eDateEnrollment;

	})
	/**
	 * Annuler, confirmer, donner une place pèsent l'état de la ligne et non celui de la formation :
	 * la structure qui l'écrit doit être ouverte aux formations pour chacun de ces gestes.
	 */
	->applyElement(function($data, \learning\DateEnrollment $eDateEnrollment) {
		$eDateEnrollment['date']['course']->validate('acceptLearning');
	})
	/**
	 * Le panneau en trois étapes, servies une à une par le serveur sous le même `id` : lire la
	 * formation, s'engager, puis s'inscrire. La première passe à la deuxième en GET, la
	 * deuxième poste ses cases à `doCommit`, qui les refuse ou rend la troisième, et la troisième
	 * poste le tout à `doCreate`, qui revalide les cases avec le reste.
	 */
	->create(function($data) {

		$data->step = (GET('step', 'int') === 2) ? 2 : 1;

		// Les cases que les noms d'étapes rapportent en revenant en arrière, réduites à celles que
		// l'inscription demande : elles ne servent qu'à les rendre cochées, `doCommit` les juge.
		// `in_array()` strict et non `array_intersect()`, qui convertit chaque valeur en chaîne et lève
		// sur un tableau imbriqué forgé dans l'adresse.
		$commitmentsPosted = GET('commitment', 'array');
		$data->commitments = array_values(array_filter($data->e->getCommitments(), fn(string $commitment) => in_array($commitment, $commitmentsPosted, TRUE)));

		// Les choix de l'étape 3, quand on en revient par un nom d'étape, voyagent jusqu'à `doCommit`,
		// qui les juge par `build()`. Un choix absent n'est pas reporté : « Aucune, à titre personnel »
		// poste une chaîne vide, qui n'est pas la même chose que rien de choisi. Une structure qui n'est
		// plus à l'utilisateur ne l'est pas non plus, comme les cases sont réduites à celles que
		// l'inscription demande : reportée, `doCommit` la refuserait à chaque passage, sans champ à
		// l'écran pour la changer, et le parcours ne rejoindrait plus l'étape 3.
		$data->choices = [];

		if(get_exists('structure')) {

			$structure = GET('structure');

			if(
				$structure === '' or
				\learning\EnrollmentLib::getStructures()->offsetExists(GET('structure', 'int'))
			) {
				$data->choices['structure'] = $structure;
			}

		}

		if(get_exists('trainee')) {
			$data->choices['trainee'] = GET('trainee');
		}

		if($data->step === 1) {
			$data->cChapter = \learning\ChapterLib::getByCourse($data->e['date']['course']);
			$data->cStaff = \learning\StaffLib::getByCourse($data->e['date']['course']);
			$data->e['date']['course']['eSupervisorContact'] = \learning\SupervisorLib::getContactByCourse($data->e['date']['course']);
		}

		\learning\DateEnrollmentLib::fillCounts(new Collection([$data->e['date']]));

		$data->status = \learning\DateEnrollmentLib::getStatusForNew($data->e['date'], $data->e['eStructureCommunity']->getCommunity('learningConfirm'));

		throw new ViewAction($data);

	})
	->create(function($data) {

		$fw = new FailWatch();

		$data->e->build(['committedAt', 'conditions'], $_POST);

		$fw->validate();

		$eDate = $data->e['date'];

		\learning\DateEnrollmentLib::fillCounts(new Collection([$eDate]));

		$data->e['cStructure'] = \learning\EnrollmentLib::getStructures($eDate['course']);
		$data->e['structure'] = \learning\EnrollmentLib::getPreselected($eDate['course'], $data->e['cStructure']);
		$data->e['trainee'] = \learning\DateEnrollmentLib::getLastTrainee($data->e['user']);

		// Revenu de l'étape 3 par un nom d'étape, on retrouve ses choix plutôt que la présélection.
		// Ils passent par `build()`, comme ils y repasseront à `doCreate`.
		$data->e->build(array_values(array_filter(['structure', 'trainee'], fn(string $property) => post_exists($property))), $_POST);

		$fw->validate();

		$data->e['isPersonal'] = (post_exists('structure') and $data->e['structure']->empty());

		$data->status = \learning\DateEnrollmentLib::getStatusForNew($eDate, $data->e['eStructureCommunity']->getCommunity('learningConfirm'));

		throw new ViewAction($data);

	}, method: 'post', page: 'doCommit')
	->doCreate(fn($data) => throw new ReloadAction('learning', 'DateEnrollment::'.$data->e['status']))
	->write('doCancel', function($data) {

		\learning\DateEnrollmentLib::cancel($data->e, \learning\DateEnrollment::USER);

		// Le rechargement rouvrirait la fiche, qui refuse (`assertRead`) la personne dont l'annulation
		// vient de retirer le seul accès : elle part vers les dates publiques du réseau, celles que
		// l'e-mail d'une ligne publique donne pour « les autres dates ». Relue après l'annulation,
		// pour peser le droit que la fiche pèsera.
		$eCourse = \learning\CourseLib::getById($data->e['date']['course']['id']);

		if($eCourse->assertRead() === FALSE) {
			$eCommunity = \structure\CommunityLib::getByStructure($eCourse['community']);
			throw new RedirectAction(\learning\DatePublicUi::urlQuery(\learning\DatePublicUi::urlList($eCommunity), ['success' => 'learning\DateEnrollment::canceled'], FALSE));
		}

		throw new ReloadAction('learning', 'DateEnrollment::canceled');

	}, validate: ['canCancel', 'acceptCancel'])
	->write('doCancelByCommunity', function($data) {

		\learning\DateEnrollmentLib::cancel($data->e, \learning\DateEnrollment::COMMUNITY);

		throw new ReloadAction('learning', 'DateEnrollment::canceledByCommunity');

	}, validate: ['canManage', 'acceptCancel'])
	->write('doConfirm', function($data) {

		$fw = new FailWatch();

		\learning\DateEnrollmentLib::confirm($data->e);

		$fw->validate();

		throw new ReloadAction('learning', 'DateEnrollment::confirmedByCommunity');

	}, validate: ['canManage', 'acceptConfirm'])
	->write('doGiveSeat', function($data) {

		$fw = new FailWatch();

		\learning\DateEnrollmentLib::giveSeat($data->e);

		$fw->validate();

		throw new ReloadAction('learning', 'DateEnrollment::seatGiven');

	}, validate: ['canManage', 'acceptGiveSeat'])
	/**
	 * Le pointage d'une date finie : ni `acceptCancel()` ni `acceptUpdate()`, fermés sur une date
	 * passée, mais `acceptAttend()`.
	 */
	->write('doAttend', function($data) use($renderAttendance) {

		\learning\DateEnrollmentLib::attend($data->e);

		$renderAttendance($data);

	}, validate: ['canManage', 'acceptAttend'])
	->write('doUnattend', function($data) use($renderAttendance) {

		\learning\DateEnrollmentLib::unattend($data->e);

		$renderAttendance($data);

	}, validate: ['canManage', 'acceptAttend'])
	->quick(['attendedMinutes'], action: $renderAttendance, validate: ['canManage', 'acceptAttendedMinutes']);

/**
 * L'attestation de présence d'une formation sans leçon (lot L8f), au compte de la place depuis la
 * fiche et à l'équipe depuis la roue de la liste (`canRead()`). Le PDF se rend par l'adresse signée
 * de `pdf:presence`, que le navigateur sans tête lit sans compte connecté : le droit et l'état se
 * jugent ici, `lessons` posé avant que la page juge.
 */
new \learning\DateEnrollmentPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->applyElement(function($data, \learning\DateEnrollment $eDateEnrollment) {
		\learning\DateEnrollmentLib::fillLessons(new Collection([$eDateEnrollment]));
	})
	->read('/formation/date/inscription/{id}/attestation', function($data) {

		$content = \main\PdfLib::build('/learning/pdf:presence?id='.$data->e['id'], footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, \learning\AttestationUi::getPresenceFilename($data->e));

	}, validate: ['canRead', 'acceptPresenceAttestation']);

/**
 * La liste des inscrits d'une date et son export, pour l'équipe qui écrit la formation. La liste
 * est un écran du réseau : elle en prend le gabarit, et la barre « Changer » y passe d'une date à
 * l'autre de la formation.
 */
new Page(function($data) {

		\user\ConnectionLib::checkLogged();

		$data->eDate = \learning\DateLib::getById(INPUT('id'))->validate('canWrite', 'acceptWrite');
		$data->eCourse = $data->eDate['course'];

		$data->cDateEnrollment = \learning\DateEnrollmentLib::getByDate($data->eDate);

		// Le bouton d'ouverture juge le nombre de confirmées, le résultat au quiz le rapporte au même
		// nombre : la liste et l'export les lisent tous deux.
		\learning\DateEnrollmentLib::fillCounts(new Collection([$data->eDate]));
		$data->eDate['eLessonQuiz'] = \learning\LessonLib::getLastQuiz($data->eCourse);

		if($data->eDate->isQuizOpened()) {
			\learning\ProgressLib::fillDateQuiz($data->eDate, $data->cDateEnrollment);
		}

	})
	->get('/formation/date/{id}/inscrits', function($data) {

		\learning\DateEnrollmentLib::fillAttendedByDate($data->eDate);
		\learning\DateEnrollmentLib::fillAttestationRecipients($data->eDate, $data->cDateEnrollment);

		$data->cDateSheet = \learning\DateSheetLib::getByDate($data->eDate);
		$data->eDate['sheets'] = $data->cDateSheet->count();

		\learning\DateEnrollmentLib::fillLosesLessons($data->eCourse, $data->cDateEnrollment);

		$data->eStructure = \structure\StructureLib::getById($data->eCourse['community']);

		// L'adresse d'inscription que l'état vide propose de copier, seulement si elle inscrit : fermée
		// (lot annuaire), elle mène au site de l'organisme, qui a sa propre adresse.
		$data->publicUrl = ($data->eDate->acceptPublic($data->eStructure) and $data->eCourse->isExternal() === FALSE)
			? \learning\DatePublicUi::urlDate(\structure\CommunityLib::getByStructure($data->eStructure), $data->eDate)
			: '';

		$data->cDate = \learning\DateLib::getByCourse($data->eCourse);

		throw new CommunityAction($data);

	})
	/**
	 * La feuille d'émargement (lot L8e), rendue par l'adresse signée de `pdf:sheet`, que le navigateur
	 * sans tête lit sans compte connecté : le droit est jugé par la Page, l'état ici.
	 */
	->get('/formation/date/{id}/emargement', function($data) {

		if($data->eDate->acceptSheet() === FALSE) {
			throw new NotAllowedAction();
		}

		$content = \main\PdfLib::build('/learning/pdf:sheet?id='.$data->eDate['id'], footer: \main\PdfUi::getPagingFooter());

		if($content === NULL) {
			throw new NotExistsAction();
		}

		throw new PdfAction($content, \learning\SheetUi::getFilename($data->eDate));

	})
	->get('export', function($data) {

		throw new CsvAction(new \learning\DateEnrollmentUi()->getExport($data->eDate, $data->cDateEnrollment), 'inscrits-'.$data->eDate['id'].'.csv');

	});
?>
