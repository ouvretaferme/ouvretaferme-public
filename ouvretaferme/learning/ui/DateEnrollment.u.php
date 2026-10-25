<?php
namespace learning;

/**
 * L'inscription à une date : le panneau et la marque d'état de la personne sur la fiche, la liste
 * des inscrits du réseau et son export, les réglages du réseau, et les e-mails de chaque changement
 * d'état.
 */
class DateEnrollmentUi {

	public function __construct() {
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	public static function urlList(Date $eDate): string {
		return '/formation/date/'.$eDate['id'].'/inscrits';
	}

	public static function urlExport(Date $eDate): string {
		return '/learning/dateEnrollment:export?id='.$eDate['id'];
	}

	public static function urlPresence(DateEnrollment $eDateEnrollment): string {
		return '/formation/date/inscription/'.$eDateEnrollment['id'].'/attestation';
	}

	public static function urlSheet(Date $eDate): string {
		return '/formation/date/'.$eDate['id'].'/emargement';
	}

	/**
	 * Ce que la pile d'une date porte à droite sur la fiche : la marque de la ligne ouverte de la
	 * personne, qui s'ouvre sur le seul geste qu'elle a, ou le bouton qui l'inscrit. Rien sur une
	 * date annulée, ni pour qui ne peut pas s'y inscrire. Une formation qui inscrit sur le site de
	 * l'organisme y mène, sauf la ligne déjà ouverte, qui reste gérée ici (lot annuaire). Inscriptions
	 * en pause, aucun geste ne s'offre, pas même le site de l'organisme : la fiche dit la pause dans
	 * ses chiffres, et la ligne déjà ouverte reste montrée.
	 *
	 * Un visiteur sans compte n'a pas le panneau, qui demande d'être connecté : le même bouton le
	 * mène à la page publique de la date (L10c), où l'inscription se fait sans compte sous la même
	 * garde d'état. `eCommunity`, le réseau de l'adresse, est posé par la page pour lui seul. Son
	 * libellé est celui de la page publique, « S'inscrire à cette date » : la barre du visiteur
	 * propose déjà « S'inscrire » pour créer un compte.
	 */
	public function getFicheAction(Date $eDate): string {

		$eDateEnrollment = $eDate['eDateEnrollment'];

		if($eDateEnrollment->isOpen()) {
			return $this->getMine($eDateEnrollment);
		}

		if($eDate->isEnrollmentPaused()) {
			return '';
		}

		if($eDate['course']->isExternal()) {
			return CourseUi::getExternalLink($eDate->getExternalUrl());
		}

		if($eDateEnrollment->assertCreate()) {
			$url = '/learning/dateEnrollment:create?date='.$eDate['id'];
			$label = s("S'inscrire");
		} else if(
			$eDateEnrollment['user']->empty() and
			$eDateEnrollment->acceptCreate()
		) {
			$url = DatePublicUi::urlDate($eDate['eCommunity'], $eDate);
			$label = s("S'inscrire à cette date");
		} else {
			return '';
		}

		if($eDate['remaining'] === 0) {
			return '<a href="'.$url.'" class="btn btn-outline-primary">'.\Asset::icon('hourglass-split').' '.s("Liste d'attente").'</a>';
		}

		return '<a href="'.$url.'" class="btn btn-outline-primary">'.\Asset::icon('person-plus').' '.$label.'</a>';

	}

	/**
	 * « Demande envoyée » côté personne dit l'état que le réseau lit « À confirmer » : le même
	 * état vu de chaque côté.
	 */
	protected function getMine(DateEnrollment $eDateEnrollment): string {

		[$label, $class, $action, $confirm] = self::getMineTexts($eDateEnrollment['status'], $eDateEnrollment['losesLessons']);

		$h = '<a class="btn btn-badge badge '.$class.' dropdown-toggle" data-dropdown="bottom-end">'.$label.'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.DateUi::getDays($eDateEnrollment['date']).'</div>';
			$h .= '<a data-ajax="/learning/dateEnrollment:doCancel" post-id="'.$eDateEnrollment['id'].'" class="dropdown-item" data-confirm="'.$confirm.'" data-waiter>'.$action.'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'état d'une ligne ouverte vu par la personne, et son geste d'annulation : le badge, sa classe,
	 * le libellé du geste et sa confirmation. Les mêmes sur la fiche et sur la page ouverte par le lien
	 * de l'e-mail (`DatePublicUi`), pour qu'on y reconnaisse le même écran. `$losesLessons`, posé par
	 * la page (`DateEnrollmentLib::losesLessons()`), ajoute à la confirmation que l'accès aux leçons
	 * s'arrête ; il n'est vrai que sur une ligne confirmée.
	 *
	 * @return array{string, string, string, string}
	 */
	public static function getMineTexts(string $status, bool $losesLessons = FALSE): array {

		return match($status) {
			DateEnrollment::REQUESTED => [s("Demande envoyée"), 'badge-info', s("Annuler ma demande"), s("Annuler votre demande d'inscription à cette date ?")],
			DateEnrollment::CONFIRMED => [s("Confirmée"), 'badge-confirmed', s("Annuler mon inscription"), $losesLessons
				? s("Annuler votre inscription à cette date ? Votre place pourra revenir à une autre personne et vous n'aurez plus accès aux leçons de la formation. Votre progression vous sera rendue si votre inscription est de nouveau confirmée.")
				: s("Annuler votre inscription à cette date ? Votre place pourra revenir à une autre personne.")],
			DateEnrollment::WAITING => [s("En attente"), 'badge-info badge-outline', s("Quitter la liste d'attente"), s("Quitter la liste d'attente de cette date ?")],
		};

	}

	/**
	 * Le panneau d'inscription, en trois étapes servies une à une sous le même `id` : lire la
	 * formation, s'engager, puis s'inscrire. Chaque étape repeint l'en-tête et le corps ; le
	 * formulaire (`dialogOpen`) ne s'écrit qu'à l'ouverture, et chaque clic sur un
	 * `data-ajax-submit` réécrit sa cible (`form.js`) : chaque bouton d'étape, envoi compris, nomme
	 * donc sa cible lui-même, sans quoi l'envoi partirait vers celle du dernier « Continuer ».
	 * L'état que prendra l'inscription (`$status`, le même calcul que l'écriture,
	 * `DateEnrollmentLib::getStatusForNew()`) choisit le titre, la note et le bouton d'envoi.
	 *
	 * `$commitments` sont les cases déjà cochées, que les noms d'étapes font voyager quand on revient
	 * en arrière : l'étape 1 les garde en champs cachés, et l'étape 2 les rend cochées. `$choices`
	 * porte de même « Au nom de » et le statut choisis à l'étape 3, que les étapes 1 et 2 gardent en
	 * champs cachés. Rien n'est acquis pour autant, `doCommit` revalide les uns et les autres.
	 */
	public function createReading(DateEnrollment $eDateEnrollment, string $status, \Collection $cChapter, \Collection $cStaff, array $commitments, array $choices): \Panel {

		$eDate = $eDateEnrollment['date'];

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->hidden('date', $eDate);

		foreach($commitments as $commitment) {
			$h .= $form->hidden('commitment[]', $commitment);
		}

		$h .= $form->hiddens($choices);

		$h .= $this->getReading($eDate, $cChapter, $cStaff);

		$h .= $form->group(
			content: '<a '.self::getStepTarget(2).' class="btn btn-primary">'.s("Continuer").' '.\Asset::icon('arrow-right').'</a>'
		);

		return $this->getStepPanel($eDateEnrollment, $status, 1, $h);

	}

	/**
	 * Le corps de l'étape 1, dans le panneau du membre comme sur la page publique : la pile de la
	 * date, puis ce que la formation dit d'elle-même, dans l'ordre de sa fiche. `$withSummary` pour la
	 * page publique, où rien d'autre ne montre le résumé : le panneau du membre s'ouvre sur la fiche,
	 * qui le porte déjà sous son titre.
	 */
	public function getReading(Date $eDate, \Collection $cChapter, \Collection $cStaff, bool $withSummary = FALSE): string {

		$eCourse = $eDate['course'];

		$h = new DateUi()->getEnrollmentDeck($eDate);

		if($withSummary and $eCourse['summary'] !== NULL) {
			$h .= '<p class="mt-2">'.encode($eCourse['summary']).'</p>';
		}

		if($eCourse['description'] !== NULL) {
			$h .= '<h3 class="mt-2">'.s("Pourquoi cette formation").'</h3>';
			$h .= new \editor\EditorUi()->value($eCourse['description']);
		}

		if($eCourse['objectives'] !== NULL) {
			$h .= '<h3 class="mt-2">'.s("Ce que vous saurez faire").'</h3>';
			$h .= new \editor\EditorUi()->value($eCourse['objectives']);
		}

		$h .= '<h3 class="mt-2">'.s("Pour qui").'</h3>';
		$h .= new CourseUi()->getAudience($eCourse, $cStaff);

		if($cChapter->notEmpty()) {
			$h .= '<h3 class="mt-2">'.s("Programme").'</h3>';
			$h .= new CourseUi()->getProgramHeads($eCourse, $cChapter);
		}

		return $h;

	}

	/**
	 * Une case par engagement, puis les conditions du réseau et leur case quand il en a écrit.
	 * Les refus sont ceux de `build()`, par `doCommit` : rien ne se refuse côté client, « Continuer »
	 * reste actif.
	 */
	public function createCommitment(DateEnrollment $eDateEnrollment, string $status, array $commitments, array $choices): \Panel {

		$eDate = $eDateEnrollment['date'];

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->hidden('date', $eDate);
		$h .= $form->hiddens($choices);

		$h .= $this->getCommitmentFields($form, $eDateEnrollment, $commitments);

		$h .= $form->group(
			content: '<a '.self::getStepTarget(3).' class="btn btn-primary">'.s("Continuer").' '.\Asset::icon('arrow-right').'</a>'
		);

		return $this->getStepPanel($eDateEnrollment, $status, 2, $h);

	}

	/**
	 * Le corps de l'étape 2, dans le panneau du membre comme sur la page publique.
	 */
	public function getCommitmentFields(\util\FormUi $form, DateEnrollment $eDateEnrollment, array $commitments): string {

		$eDate = $eDateEnrollment['date'];

		$h = '<h3>'.s("Les engagements").'</h3>';

		$h .= $form->group(
			content: $form->checkboxes('commitment[]', [
				[
					'value' => 'presence',
					'label' => self::getCommitmentLabel(self::getPresence($eDate), DateUi::getCommitmentLine($eDate))
				],
				[
					'value' => 'cancel',
					'label' => self::getCommitmentLabel(s("Si je ne peux plus venir, j'annule mon inscription au plus tôt"), s("Ma place revient alors à une personne en attente."))
				],
			], $commitments, attributes: ['all' => FALSE]),
			attributes: ['data-wrapper' => 'committedAt']
		);

		if($eDateEnrollment->hasConditions()) {

			$community = encode($eDateEnrollment['eStructureCommunity']['name']);

			$h .= '<h3 class="mt-2">'.s("Les conditions de {value}", $community).'</h3>';
			$h .= '<div class="mb-1">'.new \editor\EditorUi()->value($eDateEnrollment['eStructureCommunity']->getCommunity('learningConditions')).'</div>';

			$h .= $form->group(
				content: $form->checkbox('commitment[]', 'conditions', [
					'checked' => in_array('conditions', $commitments, TRUE),
					'callbackLabel' => fn($input) => $input.' <b>'.s("J'accepte les conditions de {value}", $community).'</b>'
				]),
				attributes: ['data-wrapper' => 'conditions']
			);

		}

		return $h;

	}

	/**
	 * « Au nom de » et le statut, puis l'envoi. Les cases acceptées à l'étape 2 sont reportées telles
	 * que l'élément les demande, et `doCreate` les revalide avec le reste.
	 *
	 * « Au nom de » ne se demande qu'à un compte qui a plusieurs structures, la règle du bouton qui
	 * commence une formation en ligne (`EnrollmentUi::getStart()`) : la seule structure du compte, ou
	 * aucune, part en champ caché et passe par le même `build()`.
	 */
	public function createInscription(DateEnrollment $eDateEnrollment, string $status): \Panel {

		$eDate = $eDateEnrollment['date'];

		[$note, $submit] = self::getSendTexts($eDateEnrollment, $status);

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->hidden('date', $eDate);

		foreach($eDateEnrollment->getCommitments() as $commitment) {
			$h .= $form->hidden('commitment[]', $commitment);
		}

		if($eDateEnrollment['cStructure']->count() > 1) {
			$h .= $form->dynamicGroups($eDateEnrollment, ['structure', 'trainee']);
		} else {
			$h .= $form->hidden('structure', $eDateEnrollment['cStructure']->empty() ? '' : $eDateEnrollment['cStructure']->first());
			$h .= $form->dynamicGroup($eDateEnrollment, 'trainee');
		}

		if($note !== NULL) {
			$h .= \util\FormUi::info($note);
		}

		$h .= $form->group(
			content: '<a data-ajax-submit="/learning/dateEnrollment:doCreate" class="btn btn-primary" data-waiter>'.$submit.'</a>'
		);

		return $this->getStepPanel($eDateEnrollment, $status, 3, $h);

	}

	/**
	 * La note sous l'étape 3 et le bouton qui envoie, selon l'état que prendra l'inscription : dans
	 * le panneau du membre comme sur la page publique.
	 *
	 * @return array{0: ?string, 1: string}
	 */
	public static function getSendTexts(DateEnrollment $eDateEnrollment, string $status): array {

		$community = encode($eDateEnrollment['eStructureCommunity']['name']);

		return match($status) {
			DateEnrollment::REQUESTED => [
				s("{value} confirme chaque inscription. Un e-mail vous le dira.", $community),
				s("Envoyer la demande")
			],
			DateEnrollment::WAITING => [
				s("La date est complète. Si une place se libère, {value} peut vous la donner, et un e-mail vous le dira.", $community),
				s("Rejoindre la liste d'attente")
			],
			DateEnrollment::CONFIRMED => [
				NULL,
				s("S'inscrire")
			],
		};

	}

	/**
	 * Le parcours emprunte les classes du composant `onboarding` sans son script : l'étape
	 * courante et les états `is-current` et `is-done` des segments et des noms, que
	 * `MainOnboarding::goTo()` poserait côté client, sont posés ici, et aucun attribut
	 * `data-onboarding-*` n'est écrit, que `MainOnboarding`, resté chargé après un autre écran en
	 * navigation ajax, attraperait. On avance par « Continuer » et on recule par le nom d'une étape
	 * faite, le seul à porter la cible de son étape (`getStepTarget()`) : le nom d'une étape suivante
	 * ne mène nulle part, pour qu'aucune ne se saute ni ne s'atteigne sans son bouton. Ce bouton suit
	 * le contenu de l'étape, dans le corps et non au pied : le panneau tient dans l'écran à chaque
	 * étape, et le bouton se lit juste sous ce qu'il valide.
	 */
	protected function getStepPanel(DateEnrollment $eDateEnrollment, string $status, int $step, string $content): \Panel {

		\Asset::css('main', 'onboarding.css');

		$eDate = $eDateEnrollment['date'];

		$h = '<div class="onboarding learning-enrollment-steps">';

			$h .= self::getProgress($step, fn(int $position): string => self::getStepTarget($position));

			$h .= '<section class="onboarding-step is-current"><div>'.$content.'</div></section>';

		$h .= '</div>';

		$form = new \util\FormUi(['columns' => 1]);

		return new \Panel(
			id: 'panel-learning-date-enrollment',
			title: match($status) {
				DateEnrollment::REQUESTED, DateEnrollment::CONFIRMED => s("S'inscrire"),
				DateEnrollment::WAITING => s("Liste d'attente"),
			},
			subTitle: CourseUi::getTypeBadge($eDate['course']).encode($eDate['course']['name']),
			dialogOpen: $form->openAjax('/learning/dateEnrollment:doCreate', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h,
			// Les étapes 1 et 2 arrivent en GET et remplacent l'adresse du panneau. L'étape 3 arrive
			// par le POST de `doCommit` : en couche, elle empilerait une entrée d'historique à chaque
			// passage, et le dépilement après `doCreate` rejouerait une étape 2 que l'inscription faite refuse.
			layer: ($step !== 3)
		);

	}

	/**
	 * La barre des trois étapes. `$target` rend l'attribut qui mène à une étape faite, la seule
	 * dont le nom se clique : un envoi de formulaire dans le panneau, un lien sur la page publique.
	 */
	public static function getProgress(int $step, \Closure $target): string {

		$names = [
			1 => s("La formation"),
			2 => s("Les engagements"),
			3 => s("L'inscription"),
		];

		$classes = [];

		foreach($names as $position => $name) {
			$classes[$position] = match($position <=> $step) {
				-1 => ' class="is-done"',
				0 => ' class="is-current"',
				1 => ''
			};
		}

		$h = '<div class="onboarding-progress">';

			$h .= '<div class="onboarding-stepper">';
				foreach($names as $position => $name) {
					$h .= '<span'.$classes[$position].'></span>';
				}
			$h .= '</div>';

			$h .= '<div class="onboarding-progress-label onboarding-progress-names">';
				foreach($names as $position => $name) {
					$attribute = ($position < $step) ? ' '.$target($position) : '';
					$h .= '<span'.$classes[$position].$attribute.'>'.$name.'</span>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qui mène à l'étape `$position`, pour « Continuer » vers l'étape suivante comme pour le nom
	 * d'une étape faite. Les étapes 1 et 2 se servent en GET en y soumettant le formulaire, pour que
	 * les cases cochées voyagent avec ; l'étape 3 ne s'atteint que par `doCommit`, qui juge les cases,
	 * et donc que par le « Continuer » de l'étape 2, puisqu'elle n'est jamais une étape faite.
	 */
	protected static function getStepTarget(int $position): string {

		if($position === 3) {
			return 'data-ajax-submit="/learning/dateEnrollment:doCommit"';
		}

		return 'data-ajax-submit="/learning/dateEnrollment:create" data-ajax-method="get" post-step="'.$position.'"';

	}

	/**
	 * La présence que la date demande : en entier pour une visio ou une seule journée, chaque
	 * journée quand la date en couvre plusieurs aux mêmes heures et au même endroit, chaque séance
	 * sinon, que la phrase de `DateUi::getCommitmentLine()` énumère.
	 */
	protected static function getPresence(Date $eDate): string {

		$sequences = count(DateUi::getDistinctSequences($eDate));

		if($sequences > 1) {
			return match($sequences) {
				2 => s("Je participerai aux deux séances"),
				default => s("Je participerai aux {value} séances", $sequences),
			};
		}

		if($eDate->getModes() === [DateSequence::REMOTE]) {
			return s("Je suivrai la visio en entier");
		}

		$days = $eDate->countDays();

		return match($days) {
			1 => s("Je participerai à la formation en entier"),
			2 => s("Je participerai aux deux journées"),
			default => s("Je participerai aux {value} journées", $days),
		};

	}

	protected static function getCommitmentLabel(string $label, string $reason): string {
		return '<div class="field-radio-group-label">'.$label.'</div><div class="field-radio-group-reason">'.$reason.'</div>';
	}

	/**
	 * La tête de la liste, dans la bande de l'écran : la formation en titre, le retour à son éditeur
	 * en surtitre, et dessous les horaires et le lieu de la date regardée. Quand la formation a
	 * plusieurs dates, le jour est dans le filtre de la bande (`getDateFilter()`), pour que le titre
	 * ne change pas quand on passe d'une date à l'autre ; sans filtre, il ouvre la ligne.
	 */
	public function getListTitle(Date $eDate, \Collection $cDate): string {

		$context = DateUi::getLine($eDate, withLink: FALSE);

		// Une ligne par séance au-delà d'une : le jour se pose au-dessus d'elles plutôt que devant
		// la première seule, comme sur la page publique de la formation (DatePublicUi).
		if(self::hasDateChoice($cDate) === FALSE) {
			$context = DateUi::getDays($eDate).((count(DateUi::getDistinctSequences($eDate)) > 1) ? '<br/>' : ' · ').$context;
		}

		return \main\DesignUi::getPageHeader(
			encode($eDate['course']['name']),
			context: '<span class="util-annotation">'.$context.'</span>',
			back: CourseUi::urlEdition($eDate['course']),
			backLabel: s("Formation")
		);

	}

	/**
	 * Les places de la date, dans la bande de l'écran. Les places prises comptent les demandes à
	 * confirmer (règle 1 de `DateEnrollmentLib`) : le second chiffre le dit, pour que « 3 sur 3 » ne
	 * se lise pas comme trois personnes confirmées. Une date annulée n'a plus de places à compter,
	 * le titre de la liste dit l'annulation.
	 */
	public function getListFigures(Date $eDate): string {

		if($eDate['status'] === Date::CANCELED) {
			return '';
		}

		$h = '<div class="util-figures-line util-figures-line-card">';

			$h .= '<div class="util-figures-line-item">';
				$h .= '<div class="util-figure-value">'.(($eDate['seats'] === NULL) ? $eDate['taken'] : $eDate['taken'].'<small> / '.$eDate['seats'].'</small>').'</div>';
				$h .= '<div class="util-figure-label">'.p("place prise", "places prises", $eDate['taken']).'</div>';
			$h .= '</div>';

			if($eDate['requested'] > 0) {
				$h .= '<div class="util-figures-line-item">';
					$h .= '<div class="util-figure-value">'.$eDate['requested'].'</div>';
					$h .= '<div class="util-figure-label">'.s("à confirmer").'</div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La liste d'une date : les places prises et leur tableau, puis la liste d'attente et les
	 * annulées, chacune dans son tableau sous son propre titre, absent quand il n'a pas de ligne. Sur
	 * une date annulée, les places ne veulent plus rien dire : le titre dit l'annulation à leur place.
	 * Sans inscrit, le geste qui remplit la liste : `$publicUrl`, l'adresse d'inscription de la date,
	 * vide quand elle ne répond pas (formation non publiée, date annulée ou passée).
	 *
	 * Une date finie se pointe (lot L8e) : la durée et le compte des présentes sous le titre, les
	 * colonnes Présence et Heures dans le tableau des places, et pour une date sur place la feuille
	 * signée à la fin, que la date ait des inscrits ou non.
	 */
	public function getList(Date $eDate, \Collection $cDateEnrollment, \Collection $cDateSheet, string $publicUrl): string {

		$h = $this->getListHead($eDate, $cDateEnrollment);

		if($eDate->isQuizOpened()) {
			$h .= '<p class="util-annotation">'.$this->getQuizSummary($eDate).'</p>';
		}

		if($this->hasAttendance($eDate)) {
			$h .= $this->getAttendanceSummary($eDate);
		}

		if($cDateEnrollment->empty()) {

			$h .= '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucun inscrit").'</h3>';
				if($publicUrl !== '') {
					\Asset::js('learning', 'public.js');
					$h .= '<div class="util-buttons">';
						$h .= '<a onclick="LearningPublic.copy(this)" data-text="'.encode($publicUrl).'" data-message="'.s("Adresse copiée !").'" class="btn btn-primary">'.\Asset::icon('clipboard').' '.s("Copier l'adresse d'inscription").'</a>';
					$h .= '</div>';
				}
			$h .= '</div>';

			return $h.$this->getSheets($eDate, $cDateSheet);

		}

		$h .= $this->getPlaces($eDate, $cDateEnrollment);

		$cDateEnrollmentCanceled = $cDateEnrollment->find(fn(DateEnrollment $e) => $e['status'] === DateEnrollment::CANCELED);

		$h .= $this->getSection($eDate, s("Liste d'attente"), $cDateEnrollment->find(fn(DateEnrollment $e) => $e['status'] === DateEnrollment::WAITING));

		// Sur une date annulée, toutes les lignes sont annulées et « Date annulée » les titre déjà.
		$h .= match($eDate['status']) {
			Date::PLANNED => $this->getSection($eDate, s("Annulées"), $cDateEnrollmentCanceled),
			Date::CANCELED => $cDateEnrollmentCanceled->empty() ? '' : $this->getTable($eDate, ['' => $cDateEnrollmentCanceled])
		};

		$h .= $this->getSheets($eDate, $cDateSheet);

		return $h;

	}

	/**
	 * Le tableau des places, les demandes à confirmer puis les confirmées, seul à porter la présence
	 * d'une date finie : c'est lui que redessine une durée changée. Un bloc vide garde l'identifiant,
	 * pour qu'une réponse ait toujours où se poser.
	 */
	public function getPlaces(Date $eDate, \Collection $cDateEnrollment): string {

		$cDateEnrollmentRequested = $cDateEnrollment->find(fn(DateEnrollment $e) => $e['status'] === DateEnrollment::REQUESTED);
		$cDateEnrollmentConfirmed = $cDateEnrollment->find(fn(DateEnrollment $e) => $e['status'] === DateEnrollment::CONFIRMED);

		$h = '<div id="learning-date-places">';

			if($cDateEnrollmentRequested->notEmpty() or $cDateEnrollmentConfirmed->notEmpty()) {
				$h .= $this->getTable($eDate, [
					s("À confirmer") => $cDateEnrollmentRequested,
					s("Confirmées") => $cDateEnrollmentConfirmed,
				], $this->hasAttendance($eDate));
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La présence se pointe sur une date finie qui a eu au moins une personne confirmée : sans elle,
	 * il n'y a personne à pointer, ni d'heures à compter. `confirmed` est posé par la page
	 * (`DateEnrollmentLib::fillCounts()`).
	 */
	public function hasAttendance(Date $eDate): bool {

		$eDate->expects(['confirmed']);

		return (
			$eDate->acceptAttendance() and
			$eDate['confirmed'] > 0
		);

	}

	/**
	 * Une date finie d'une formation sans leçon délivre une attestation de présence à chaque place
	 * pointée (lot L8f) : la liste gagne l'envoi, l'état des envois et la roue des places pointées,
	 * qui mènent aux objectifs tant que la formation n'en a pas. `course.lessons` est posé par la page
	 * (`DateEnrollmentLib::fillAttestationRecipients()`).
	 */
	public function hasAttestation(Date $eDate): bool {

		return (
			$this->hasAttendance($eDate) and
			$eDate->hasPresenceAttestations()
		);

	}

	/**
	 * « Envoyer les attestations », rendu caché tant qu'aucune place n'attend la sienne : le pointage
	 * le redessine seul (`learning-date-attestation-send`), et une personne pointée le fait paraître.
	 * Une formation sans objectifs ne délivre pas d'attestation (`Date::acceptAttestations()`) : le
	 * même bouton mène alors à l'écran où ils s'écrivent.
	 */
	public function getAttestationButton(Date $eDate): string {

		$eDate->expects(['attestationRecipients']);

		$class = 'btn btn-outline-primary';

		if($eDate['attestationRecipients'] === 0) {
			$class .= ' hide';
		}

		if($eDate->acceptAttestations() === FALSE) {
			return '<a href="'.self::urlObjectives($eDate['course']).'" class="'.$class.'" id="learning-date-attestation-send">'.\Asset::icon('pencil').' '.s("Écrire les objectifs").'</a>';
		}

		return '<a href="/learning/date:sendAttestations?id='.$eDate['id'].'" class="'.$class.'" id="learning-date-attestation-send">'.\Asset::icon('envelope').' '.s("Envoyer les attestations").'</a>';

	}

	/**
	 * Le panneau du détail de la formation, où s'écrivent ses objectifs.
	 */
	public static function urlObjectives(Course $eCourse): string {
		return '/learning/course:updateDetails?id='.$eCourse['id'];
	}

	/**
	 * Sous le titre de la liste d'une date finie : la durée de formation, qui s'édite sur place, et
	 * le nombre de personnes pointées sur les confirmées. `attended` est posé par la page
	 * (`DateEnrollmentLib::fillAttendedByDate()`). Sans leçon, l'état des attestations de présence
	 * suit, dès qu'une personne est pointée (lot L8f).
	 */
	public function getAttendanceSummary(Date $eDate): string {

		$eDate->expects(['attended', 'confirmed']);

		$minutes = $eDate->quick('minutes', CourseUi::getDuration($eDate->getMinutes()), validate: ['canWrite', 'acceptAttendance']);

		$attended = ($eDate['attended'] === 0)
			? s("aucune présence pointée")
			: p("{value} personne présente sur {total}", "{value} personnes présentes sur {total}", $eDate['attended'], ['total' => $eDate['confirmed']]);

		$h = s("Durée de formation {value}", $minutes).' · '.$attended;

		if(
			$this->hasAttestation($eDate) and
			$eDate['attended'] > 0
		) {
			$h .= ' · '.$this->getAttestationState($eDate);
		}

		return '<div class="util-annotation mb-1" id="learning-date-attendance">'.$h.'</div>';

	}

	/**
	 * « aucune attestation envoyée », « attestations envoyées le 3 novembre » au format de la roue, et
	 * combien restent à envoyer après un envoi.
	 */
	protected function getAttestationState(Date $eDate): string {

		$eDate->expects(['attestationRecipients', 'attestationMailed', 'attestationMailedAt']);

		if($eDate['attestationMailed'] === 0) {
			return s("aucune attestation envoyée");
		}

		$h = p("attestation envoyée le {date}", "attestations envoyées le {date}", $eDate['attestationMailed'], ['date' => \util\DateUi::textual($eDate['attestationMailedAt'], \util\DateUi::DAY_MONTH)]);

		if($eDate['attestationRecipients'] > 0) {
			$h .= ', '.p("{value} à envoyer", "{value} à envoyer", $eDate['attestationRecipients']);
		}

		return $h;

	}

	/**
	 * La feuille signée d'une date finie dont une séance au moins était en présentiel, que la visio
	 * n'a pas.
	 */
	protected function getSheets(Date $eDate, \Collection $cDateSheet): string {

		if(
			$eDate->acceptAttendance() === FALSE or
			$eDate->hasMode(DateSequence::ONSITE) === FALSE
		) {
			return '';
		}

		return new DateSheetUi()->getSection($eDate, $cDateSheet);

	}

	/**
	 * Un tableau à part sous son titre et son compteur, pour la liste d'attente puis les annulées :
	 * ni l'une ni les autres ne tiennent de place, et le titre dit leur état à la place des groupes du
	 * tableau des places. Rien quand la section est vide.
	 */
	protected function getSection(Date $eDate, string $title, \Collection $cDateEnrollment): string {

		if($cDateEnrollment->empty()) {
			return '';
		}

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.$title.' <span class="util-counter">'.$cDateEnrollment->count().'</span></h3>';
		$h .= '</div>';

		$h .= $this->getTable($eDate, ['' => $cDateEnrollment]);

		return $h;

	}

	/**
	 * Un tableau d'inscrits, aux mêmes colonnes quel que soit l'état de ses lignes, pour qu'une ligne se
	 * lise pareil d'un tableau à l'autre. `$groups` range les lignes sous leur titre de groupe, qui dit
	 * l'état sans colonne ni couleur ; un groupe vide est sauté, un titre vide n'est pas écrit.
	 */
	protected function getTable(Date $eDate, array $groups, bool $attendance = FALSE): string {

		$class = 'util-table util-table-even learning-table-enrolled';

		// Aucune ligne d'une date passée n'a de geste (`DateEnrollment::acceptCancel()`) : la colonne
		// de la roue sort de ses tableaux, en-tête compris.
		$attestation = ($attendance and $this->hasAttestation($eDate));

		if($attendance) {
			$class .= ' learning-table-enrolled-attendance';
		} else if($eDate->isPast()) {
			$class .= ' learning-table-enrolled-past';
		}

		if($eDate->isQuizOpened()) {
			$class .= ' learning-table-enrolled-quiz';
		}

		if($attestation) {
			$class .= ' learning-table-enrolled-attestation';
		}

		$h = '<div class="'.$class.'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Stagiaire").'</div>';
				$h .= '<div>'.s("Statut").'</div>';
				$h .= '<div>'.s("Reçue le").'</div>';
				if($eDate->isQuizOpened()) {
					$h .= '<div>'.s("Quiz").'</div>';
				}
				if($attendance) {
					$h .= '<div class="text-center">'.s("Présence").'</div>';
					$h .= '<div class="text-end">'.s("Heures").'</div>';
				}
				if($eDate->isPast() === FALSE or $attestation) {
					$h .= '<div></div>';
				}
			$h .= '</div>';

			foreach($groups as $title => $cDateEnrollmentGroup) {

				if($cDateEnrollmentGroup->empty()) {
					continue;
				}

				if($title !== '') {
					$h .= '<div class="util-table-title">'.$title.' <span class="util-counter">'.$cDateEnrollmentGroup->count().'</span></div>';
				}

				foreach($cDateEnrollmentGroup as $eDateEnrollment) {
					$h .= $this->getRow($eDate, $eDateEnrollment, $attendance);
				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Sous le titre de la liste, dès l'ouverture : le jour où le quiz a été ouvert, et combien des
	 * personnes confirmées aujourd'hui l'ont réussi.
	 */
	protected function getQuizSummary(Date $eDate): string {

		$eDate->expects(['quizOpenedAt', 'quizPassed', 'confirmed']);

		$passed = ($eDate['quizPassed'] === 0)
			? s("aucune réussite sur {value}", $eDate['confirmed'])
			: p("{value} réussite sur {total}", "{value} réussites sur {total}", $eDate['quizPassed'], ['total' => $eDate['confirmed']]);

		return s("Quiz ouvert le {value}", \util\DateUi::numeric($eDate['quizOpenedAt'], \util\DateUi::DATE)).' · '.$passed;

	}

	/**
	 * La règle qui répartit le jour entre le titre et le filtre de la bande : une formation d'une
	 * seule date n'a rien à choisir, son jour passe dans la ligne du titre.
	 */
	protected static function hasDateChoice(\Collection $cDate): bool {
		return ($cDate->count() > 1);
	}

	/**
	 * La date regardée, en filtre de la bande (`CommunityTemplate::$mainFilter`), comme la saison de
	 * Maraîchage : le jour sur le jeton, et dessous les autres dates de la formation. Les dates
	 * annulées ou passées sont les portées closes, sous un filet. Le jeton ne dit que les jours, le
	 * lieu étant sous le titre ; les entrées du menu gardent le leur, qui les distingue. Une
	 * formation d'une seule date n'a rien à choisir : le jour passe alors dans la ligne du titre.
	 */
	public function getDateFilter(Date $eDate, \Collection $cDate): string {

		if(self::hasDateChoice($cDate) === FALSE) {
			return '';
		}

		$cDateOpen = $cDate->find(fn(Date $e) => $e['status'] === Date::PLANNED and $e->isPast() === FALSE);
		$cDateClosed = $cDate->find(fn(Date $e) => ($e['status'] === Date::PLANNED and $e->isPast() === FALSE) === FALSE);

		$h = '<div class="util-filter">';

			$h .= '<div class="util-filter-label">'.s("Date").'</div>';

			$h .= '<div>';

				// util-chip sans dropdown-toggle : le jeton porte déjà son chevron en
				// background-image, quand dropdown-toggle en poserait un second en :after.
				$h .= '<a class="util-chip is-active" data-dropdown="bottom-start">'.DateUi::getDays($eDate).'</a>';

				$h .= '<div class="dropdown-list">';

					foreach($cDateOpen as $eDateItem) {
						$h .= $this->getDateItem($eDateItem, $eDate, closed: FALSE);
					}

					if($cDateOpen->notEmpty() and $cDateClosed->notEmpty()) {
						$h .= '<div class="dropdown-divider"></div>';
					}

					foreach($cDateClosed as $eDateItem) {
						$h .= $this->getDateItem($eDateItem, $eDate, closed: TRUE);
					}

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getDateItem(Date $eDateItem, Date $eDate, bool $closed): string {

		$class = 'dropdown-item util-scope-line-item';

		if($eDateItem['id'] === $eDate['id']) {
			$class .= ' selected';
		}

		if($closed) {
			$class .= ' util-scope-line-item-closed';
		}

		$h = '<a href="'.self::urlList($eDateItem).'" class="'.$class.'">';
			$h .= '<div>';
				$h .= '<div>'.DateUi::getDays($eDateItem).'</div>';
				$where = $this->getDateWhere($eDateItem);
				if($where !== NULL) {
					$h .= '<div class="dropdown-info">'.$where.'</div>';
				}
			$h .= '</div>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * Le lieu d'une date, sous son jour dans le menu : son adresse, « En visio » ou « Présentiel et
	 * visio ». Rien quand ses séances en présentiel ont plusieurs lieux, pour que l'entrée n'ait pas
	 * de seconde ligne vide.
	 */
	protected function getDateWhere(Date $eDate): ?string {

		return DateUi::getWhere($eDate);

	}

	/**
	 * Le titre du tableau des places et ses gestes. Les places se comptent dans la bande
	 * (`getListFigures()`) : sans inscrit, une date à venir n'a ni tableau à titrer ni geste, et sa
	 * liste commence par l'appel à la remplir.
	 */
	protected function getListHead(Date $eDate, \Collection $cDateEnrollment): string {

		if($eDate['status'] === Date::PLANNED and $cDateEnrollment->empty()) {
			return '';
		}

		$h = '<div class="util-title">';
			$h .= '<h3>'.match($eDate['status']) {
				Date::PLANNED => s("Places prises"),
				Date::CANCELED => s("Date annulée")
			}.'</h3>';
			if($cDateEnrollment->notEmpty()) {
				$h .= '<div class="flex-buttons">';
					if($eDate->acceptSheet()) {
						$h .= '<a href="'.self::urlSheet($eDate).'" class="btn btn-outline-primary" data-ajax-download data-waiter>'.\Asset::icon('download').' '.s("Feuille d'émargement").'</a>';
					}
					if($eDate->acceptOpenQuiz()) {
						$h .= '<a href="/learning/date:openQuiz?id='.$eDate['id'].'" class="btn btn-outline-primary">'.\Asset::icon('unlock').' '.s("Ouvrir le quiz").'</a>';
					}
					$h .= '<a href="'.self::urlExport($eDate).'" class="btn btn-outline-primary" data-ajax-download data-waiter>'.\Asset::icon('download').' '.s("Exporter en CSV").'</a>';
					if($this->hasAttestation($eDate)) {
						$h .= $this->getAttestationButton($eDate);
					}
				$h .= '</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les places prises d'une date rapportées à son plafond, quand elle en a un.
	 */
	protected function getTakenSeats(Date $eDate): string {

		if($eDate['seats'] === NULL) {
			return p("{value} place prise", "{value} places prises", $eDate['taken']);
		}

		return p("{value} place prise sur {seats}", "{value} places prises sur {seats}", $eDate['taken'], ['seats' => $eDate['seats']]);

	}

	/**
	 * Sur le tableau de bord d'un réseau : les dernières inscriptions reçues sur ses dates à venir
	 * (`DateEnrollmentLib::getLastByCommunity()`), chacune avec les places prises de sa date à cet
	 * instant. Le nom de la formation mène à la liste des inscrits de la date, où se fait la
	 * confirmation. Sous 575 px la formation et le jour de sa date passent sous le nom, le jour de réception n'est plus montré.
	 */
	public function getLast(\Collection $cDateEnrollment): string {

		$h = '<div class="util-title">';
			$h .= '<h3>'.s("Dernières inscriptions").'</h3>';
		$h .= '</div>';

		$h .= '<div class="util-table util-table-hover learning-table-last mb-2">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Stagiaire").'</div>';
				$h .= '<div>'.s("Formation").'</div>';
				$h .= '<div>'.s("Reçue le").'</div>';
				$h .= '<div class="text-end">'.s("Places").'</div>';
			$h .= '</div>';

			foreach($cDateEnrollment as $eDateEnrollment) {

				$eDate = $eDateEnrollment['date'];

				$course = '<a href="'.self::urlList($eDate).'">'.encode($eDate['course']['name']).'</a>';
				$day = \util\DateUi::textual($eDate['startAt'], \util\DateUi::DAY_MONTH);
				$requested = ($eDateEnrollment['status'] === DateEnrollment::REQUESTED) ? ' <span class="badge badge-info">'.s("À confirmer").'</span>' : '';

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						$h .= '<b>'.$eDateEnrollment->getName().'</b>';
						$h .= '<div class="util-annotation">'.self::getStructure($eDateEnrollment).'</div>';
						$h .= '<div class="hide-sm-up">'.$course.$requested.'</div>';
						$h .= '<div class="util-annotation hide-sm-up">'.$day.'</div>';
					$h .= '</div>';
					$h .= '<div>';
						$h .= $course;
						$h .= '<div class="util-annotation">'.$day.'</div>';
					$h .= '</div>';
					$h .= '<div>'.\util\DateUi::textual($eDateEnrollment['requestedAt'], \util\DateUi::DAY_MONTH).$requested.'</div>';
					$h .= '<div class="text-end">'.$this->getTakenSeats($eDate).'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne d'inscrit, que le pointage redessine seule (`learning-date-enrollment-<id>`). Avec
	 * `$attendance`, la case et les heures d'une confirmée ; une demande restée à confirmer garde
	 * les deux cellules vides.
	 */
	public function getRow(Date $eDate, DateEnrollment $eDateEnrollment, bool $attendance = FALSE): string {

		$trainee = self::p('trainee')->values[$eDateEnrollment['trainee']];

		$h = '<div class="util-table-row" id="learning-date-enrollment-'.$eDateEnrollment['id'].'">';

			$h .= '<div>';
				$h .= '<b>'.$eDateEnrollment->getName().'</b>';
				$h .= '<div class="util-annotation">'.self::getStructure($eDateEnrollment).'</div>';
				if($eDateEnrollment['status'] === DateEnrollment::CANCELED) {
					$h .= '<div class="util-annotation">'.$this->getCanceled($eDate, $eDateEnrollment).'</div>';
				}
				$h .= '<div class="util-annotation hide-sm-up">'.$trainee.'</div>';
			$h .= '</div>';
			$h .= '<div>'.$trainee.'</div>';
			$h .= '<div>'.\util\DateUi::numeric($eDateEnrollment['requestedAt'], \util\DateUi::DAY_MONTH).'</div>';
			if($eDate->isQuizOpened()) {
				$h .= '<div>'.$this->getQuiz($eDateEnrollment).'</div>';
			}
			if($attendance) {
				$h .= $this->getAttendance($eDateEnrollment);
			}
			if($eDate->isPast() === FALSE) {
				$h .= '<div class="text-end">'.$this->getActions($eDate, $eDateEnrollment).'</div>';
			} else if($attendance and $this->hasAttestation($eDate)) {
				$h .= '<div class="text-end">'.$this->getAttestationActions($eDateEnrollment).'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La case qui pointe la personne, et ses heures, celles de la date tant qu'elles ne sont pas
	 * corrigées, qui s'éditent sur place. Le tiret pour une confirmée qui n'est pas venue.
	 */
	protected function getAttendance(DateEnrollment $eDateEnrollment): string {

		if($eDateEnrollment->acceptAttend() === FALSE) {
			return '<div></div><div></div>';
		}

		$h = '<div class="util-checkbox text-center">';
			$h .= '<label>';
				$h .= new \util\FormUi()->inputCheckbox(attributes: [
					'checked' => $eDateEnrollment->isAttended(),
					'title' => s("Présente"),
					'oninput' => 'LearningDate.attend(this)',
					'data-id' => $eDateEnrollment['id']
				]);
			$h .= '</label>';
		$h .= '</div>';

		$h .= '<div class="text-end">';
			if($eDateEnrollment->isAttended()) {
				$h .= $eDateEnrollment->quick('attendedMinutes', CourseUi::getDuration($eDateEnrollment->getAttendedMinutes()), validate: ['canManage', 'acceptAttendedMinutes']);
			} else {
				$h .= '<span class="util-annotation">-</span>';
			}
		$h .= '</div>';

		return $h;

	}

	protected function getQuiz(DateEnrollment $eDateEnrollment): string {

		if(self::hasQuiz($eDateEnrollment) === FALSE) {
			return '-';
		}

		return new ProgressUi()->getTrackCell($eDateEnrollment['quizTrack']);

	}

	/**
	 * La ligne peut-elle passer le quiz : confirmée, et portée par un compte, le quiz ne se passant
	 * qu'avec un compte. Les autres prennent le tiret dans la liste comme dans l'export ; le vide reste
	 * à la confirmée qui ne l'a pas encore passé.
	 */
	protected static function hasQuiz(DateEnrollment $eDateEnrollment): bool {

		return (
			$eDateEnrollment['status'] === DateEnrollment::CONFIRMED and
			$eDateEnrollment['user']->notEmpty()
		);

	}

	/**
	 * Qui a annulé la ligne, et quand : la personne, le réseau, ou l'annulation de la date.
	 */
	protected function getCanceled(Date $eDate, DateEnrollment $eDateEnrollment): string {

		$day = \util\DateUi::numeric($eDateEnrollment['canceledAt'], \util\DateUi::DAY_MONTH);

		return match($eDateEnrollment['canceledBy']) {
			DateEnrollment::USER => s("Annulée à sa demande le {value}", $day),
			DateEnrollment::COMMUNITY => s("Annulée par {community} le {day}", ['community' => encode($eDate['course']['community']['name']), 'day' => $day]),
			DateEnrollment::DATE => s("Date annulée le {value}", $day)
		};

	}

	/**
	 * La structure choisie par un membre, sinon la ferme saisie sur la page publique.
	 */
	public static function getStructure(DateEnrollment $eDateEnrollment): string {

		if($eDateEnrollment['structure']->notEmpty()) {
			return encode($eDateEnrollment['structure']['name']);
		}

		if($eDateEnrollment['structureName'] !== NULL) {
			return encode($eDateEnrollment['structureName']);
		}

		return s("À titre personnel");

	}

	/**
	 * La roue d'une place pointée d'une date finie sans leçon (lot L8f) : son attestation de présence
	 * à télécharger, et le jour où l'e-mail la lui a portée, ou les objectifs à écrire tant que la
	 * formation n'en a pas. Les autres lignes n'en ont pas, la date
	 * passée n'ayant plus d'autre geste.
	 */
	protected function getAttestationActions(DateEnrollment $eDateEnrollment): string {

		if($eDateEnrollment->hasPresenceAttestation() === FALSE) {
			return '';
		}

		$items = '';

		if($eDateEnrollment->acceptPresenceAttestation()) {
			$items .= '<a href="'.self::urlPresence($eDateEnrollment).'" class="dropdown-item" data-ajax-download data-waiter>';
				$items .= '<div class="util-item-label">'.s("Attestation de présence").'</div>';
				if($eDateEnrollment['attestationMailedAt'] !== NULL) {
					$items .= '<div class="util-item-more">'.s("Envoyée le {value}", \util\DateUi::textual($eDateEnrollment['attestationMailedAt'], \util\DateUi::DAY_MONTH)).'</div>';
				}
			$items .= '</a>';
		} else {
			$items .= '<a href="'.self::urlObjectives($eDateEnrollment['date']['course']).'" class="dropdown-item">'.s("Écrire les objectifs").'</a>';
		}

		return \main\DesignUi::getGear($items, $eDateEnrollment->getName(), 'btn-sm');

	}

	/**
	 * La roue d'une ligne ouverte, qui s'ouvre sur les gestes du réseau. Une ligne annulée, ou d'une
	 * date passée, n'en a pas.
	 */
	protected function getActions(Date $eDate, DateEnrollment $eDateEnrollment): string {

		if($eDateEnrollment->acceptCancel() === FALSE) {
			return '';
		}

		$items = '';

		if($eDateEnrollment->acceptConfirm()) {
			$items .= '<a data-ajax="/learning/dateEnrollment:doConfirm" post-id="'.$eDateEnrollment['id'].'" class="dropdown-item" data-waiter>'.s("Confirmer l'inscription").'</a>';
		}

		if($eDateEnrollment->acceptGiveSeat()) {

			if(
				$eDate['seats'] !== NULL and
				$eDate['taken'] >= $eDate['seats']
			) {
				$items .= '<a class="dropdown-item disabled">';
					$items .= '<div class="util-item-label">'.s("Donner une place").'</div>';
					$items .= '<div class="util-item-more">'.s("Aucune place libre, {taken} sur {seats}", ['taken' => $eDate['taken'], 'seats' => $eDate['seats']]).'</div>';
				$items .= '</a>';
			} else {
				$items .= '<a data-ajax="/learning/dateEnrollment:doGiveSeat" post-id="'.$eDateEnrollment['id'].'" class="dropdown-item" data-waiter>'.s("Donner une place").'</a>';
			}

		}

		if($eDateEnrollment['status'] !== DateEnrollment::CONFIRMED) {
			$items .= '<div class="dropdown-divider"></div>';
		}

		$confirm = $eDateEnrollment['losesLessons']
			? s("Annuler l'inscription de {value} ? Un e-mail le lui dira. Cette personne n'aura plus accès aux leçons de la formation, et sa progression lui sera rendue si son inscription est de nouveau confirmée.", $eDateEnrollment->getName())
			: s("Annuler l'inscription de {value} ? Un e-mail le lui dira.", $eDateEnrollment->getName());

		$items .= '<a data-ajax="/learning/dateEnrollment:doCancelByCommunity" post-id="'.$eDateEnrollment['id'].'" class="dropdown-item" data-confirm="'.$confirm.'" data-waiter>'.s("Annuler l'inscription").'</a>';

		return \main\DesignUi::getGear($items, $eDateEnrollment->getName(), 'btn-sm');

	}

	/**
	 * L'export : nom, prénom, e-mail, structure, statut du stagiaire, date de demande et état, dans
	 * l'ordre des demandes. Une ligne sans compte y porte sa saisie, et sa ferme en texte libre.
	 * Dès l'ouverture du quiz, son résultat, avec le tiret et le vide de l'écran (`hasQuiz()`).
	 * Quand la liste pointe les présences (`hasAttendance()`), la présence et les heures d'une
	 * confirmée, en heures et virgule comme le champ qui les corrige ; vides ailleurs (lot L8e-3).
	 */
	public function getExport(Date $eDate, \Collection $cDateEnrollment): array {

		$attendance = $this->hasAttendance($eDate);

		$head = [
			s("Nom"),
			s("Prénom"),
			s("E-mail"),
			s("Structure"),
			s("Statut"),
			s("Date de demande"),
			s("État"),
		];

		if($eDate->isQuizOpened()) {
			$head[] = s("Quiz");
		}

		if($attendance) {
			$head[] = s("Présence");
			$head[] = s("Heures");
		}

		$lines = [$head];

		$states = self::p('status')->values;
		$trainees = self::p('trainee')->values;
		$progressUi = new ProgressUi();

		foreach($cDateEnrollment as $eDateEnrollment) {

			$line = [
				$eDateEnrollment->getLastName(),
				$eDateEnrollment->getFirstName() ?? '',
				$eDateEnrollment->getEmail() ?? '',
				$eDateEnrollment['structure']->empty() ? ($eDateEnrollment['structureName'] ?? '') : $eDateEnrollment['structure']['name'],
				$trainees[$eDateEnrollment['trainee']],
				\util\DateUi::numeric($eDateEnrollment['requestedAt'], \util\DateUi::DATE),
				$states[$eDateEnrollment['status']],
			];

			if($eDate->isQuizOpened()) {
				$line[] = self::hasQuiz($eDateEnrollment) ? $progressUi->getTrackExport($eDateEnrollment['quizTrack']) : '-';
			}

			if($attendance) {

				if($eDateEnrollment['status'] !== DateEnrollment::CONFIRMED) {
					array_push($line, '', '');
				} else if($eDateEnrollment->isAttended()) {
					array_push($line, s("Présente"), DateUi::getHoursPlaceholder($eDateEnrollment->getAttendedMinutes()));
				} else {
					array_push($line, s("Absente"), '');
				}

			}

			$lines[] = $line;

		}

		return $lines;

	}

	/**
	 * Le panneau des réglages du réseau, ouvert par le menu de la roue à droite du titre de l'onglet
	 * « Formations » : le régime des inscriptions, à une date puis à une formation en ligne, et les
	 * conditions que chaque personne accepte en s'inscrivant. La page publique se diffuse depuis sa propre page (`getIntegration()`).
	 */
	public function updateSettings(\structure\Structure $eStructure, \structure\Community $eCommunity): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/community:doUpdateEnrollment');

			$h .= $form->hidden('id', $eStructure);

			$values = [
				0 => '<div class="field-radio-group-label">'.s("Acquise tout de suite").'</div><div class="field-radio-group-reason">'.s("La personne est inscrite dès l'envoi du formulaire, dans la limite des places.").'</div>',
				1 => '<div class="field-radio-group-label">'.s("Demande à confirmer").'</div><div class="field-radio-group-reason">'.s("Chaque inscription attend que le réseau la confirme depuis la liste des inscrits.").'</div>',
			];

			$h .= $form->group(
				s("Inscription à une date"),
				$form->radios('learningConfirm', $values, (int)$eCommunity['learningConfirm'], ['required' => TRUE]),
				['data-wrapper' => 'learningConfirm']
			);

			$h .= $form->dynamicGroup($eCommunity, 'learningOnlineConfirm');

			$h .= $form->dynamicGroup($eCommunity, 'learningConditions');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-enrollment-settings',
			title: s("Réglages des inscriptions"),
			subTitle: encode($eStructure['name']),
			body: $h
		);

	}

	public static function urlIntegration(\structure\Structure $eStructure): string {
		return '/groupe/'.$eStructure['id'].'/formations/integrer';
	}

	public function getIntegrationTitle(\structure\Structure $eStructure): string {

		return \main\DesignUi::getPageHeader(
			s("Intégrer sur votre site"),
			back: '/groupe/'.$eStructure['id'].'/formations',
			backLabel: s("Formations")
		);

	}

	/**
	 * La page « Intégrer sur votre site » d'un réseau : l'adresse de sa page publique et le code qui
	 * l'embarque, à copier, puis l'aperçu, qui exécute ce même code. Sans formation en ligne publiée
	 * ni formation publiée datée à venir, l'adresse et le code restent à copier pour préparer le
	 * site, et l'aperçu, qui n'aurait rien à afficher, laisse place à une phrase (`$withPublic`).
	 */
	public function getIntegration(\structure\Structure $eStructure, \structure\Community $eCommunity, bool $withPublic): string {

		\Asset::css('util', 'form.css');
		\Asset::js('learning', 'public.js');

		$h = '<div class="util-title"><h3>'.s("Page publique").'</h3></div>';
		$h .= self::getCopyBox('learning-public-url', DatePublicUi::urlList($eCommunity), s("Adresse copiée !"), code: FALSE);

		$h .= '<div class="util-title mt-2"><h3>'.s("Code à coller sur votre site").'</h3></div>';
		$h .= self::getCopyBox('learning-public-code', DatePublicUi::getEmbedScript($eStructure), s("Code copié !"), code: TRUE);

		$h .= '<div class="util-title mt-2"><h3>'.s("Aperçu").'</h3></div>';

		if($withPublic) {
			$h .= '<div>'.DatePublicUi::getEmbedScript($eStructure).'</div>';
		} else {
			$h .= '<div class="util-block-empty"><h3>'.s("Aucune formation publiée n'a de date à venir").'</h3></div>';
		}

		return $h;

	}

	/**
	 * Un texte à copier dans son champ, le bouton à côté : le balisage de
	 * `structure\CommunityStartAi::getLinkBox()`, protégée dans son package. La copie passe par
	 * `LearningPublic.copy()`, qui copie le texte affiché et non le HTML.
	 */
	protected static function getCopyBox(string $id, string $text, string $message, bool $code): string {

		$h = '<div class="input-group">';
			$h .= '<div class="form-control learning-copy-box'.($code ? ' mono' : '').'" id="'.$id.'">'.encode($text).'</div>';
			$h .= '<a onclick="LearningPublic.copy(this)" data-selector="#'.$id.'" data-message="'.$message.'" class="btn btn-outline-primary">'.\Asset::icon('clipboard').' '.s("Copier").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les faits d'une date dans les e-mails : le jour, l'horaire, le lieu, au nom de qui la
	 * personne s'est inscrite quand l'e-mail confirme une place, et le lien de la visio à qui vient
	 * d'obtenir la sienne : la fiche ne le montre qu'à une inscription confirmée. Tant que le réseau
	 * ne l'a pas envoyé (`Date::isLinkMailed()`), la ligne dit qu'il suivra, même posé : c'est
	 * `DateLib::sendLink()` qui le fait sortir. La durée ne se dit que dans l'e-mail du lien, qui
	 * rappelle la séance.
	 *
	 * Le prix se dit dans les e-mails qui accompagnent une place, demandée, confirmée ou libérée
	 * (`$withPrice`) : le montant que le paiement en ligne encaisse quand la date en a un, sinon les
	 * montants saisis avec leur libellé. Jamais les financeurs, qui s'y liraient comme un
	 * financement accordé.
	 */
	protected function getFacts(DateEnrollment $eDateEnrollment, bool $withStructure, bool $withLink, bool $withDuration = FALSE, bool $withPrice = FALSE): array {

		$eDate = $eDateEnrollment['date'];

		$facts = [
			s("Date") => DateUi::getDays($eDate),
		];

		$sequences = DateUi::getDistinctSequences($eDate);

		if(count($sequences) === 1) {

			$facts[s("Horaire")] = DateUi::getHours($sequences[0]);

			$where = DateUi::getWhere($eDate);

			if($where !== NULL) {
				$facts[s("Lieu")] = $where;
			}

		} else {

			// Une séance par fait, le jour en libellé : l'horaire et le lieu se lisent ensemble. Un jour
			// qui porte plusieurs séances met l'horaire dans le libellé, qui est la clé du fait.
			$byDay = array_count_values(array_map(fn(DateSequence $eDateSequence) => $eDateSequence->getDay(), $sequences));

			foreach($sequences as $eDateSequence) {

				$day = DateUi::getDayName($eDateSequence->getDay()).' '.\util\DateUi::textual($eDateSequence->getDay(), \util\DateUi::DAY_MONTH);

				if($byDay[$eDateSequence->getDay()] > 1) {
					$facts[$day.', '.DateUi::getHours($eDateSequence)] = DateUi::getSequenceWhere($eDateSequence);
				} else {
					$where = DateUi::getCommitmentWhere($eDateSequence);
					$facts[$day] = DateUi::getHours($eDateSequence).(($where === NULL) ? '' : ', '.$where);
				}

			}

		}

		if($withDuration) {
			$facts[s("Durée")] = CourseUi::getDuration($eDate->getMinutes());
		}

		if(
			$withLink and
			$eDate->hasMode(DateSequence::REMOTE) and
			$eDate->isLinkMailed()
		) {
			$facts[s("Lien de la visio")] = \main\MailSoftwareAiUi::linkTag($eDate['link']).encode($eDate['link']).'</a>';
		} else if(
			$withLink and
			$eDate->isLinkAwaited()
		) {
			$facts[s("Lien de la visio")] = s("Il vous sera envoyé par e-mail avant la séance.");
		}

		if($withPrice) {

			$price = CourseUi::isPaidOnline($eDate['course'], $eDate)
				? CourseUi::getAmount($eDate->getPrice())
				: CourseUi::getPriceAmounts($eDate);

			if($price !== '') {
				$facts[s("Prix")] = $price;
			}

		}

		if($withStructure) {
			$facts[s("Au nom de")] = self::getStructure($eDateEnrollment);
		}

		return $facts;

	}

	protected static function getCourseUrl(DateEnrollment $eDateEnrollment): string {
		return \Lime::getUrl().CourseUi::url($eDateEnrollment['date']['course']);
	}

	/**
	 * Le bouton d'un e-mail et sa note : la fiche de la formation pour un membre, la page de
	 * l'inscription par son jeton pour une ligne de la page publique, qui peut ne pas lire la fiche.
	 * `DateEnrollmentLib::getMails()` pose le groupe du réseau (`eCommunity`) sur une ligne publique.
	 *
	 * @return array{0: string, 1: string, 2: string}
	 */
	protected static function getCancelButton(DateEnrollment $eDateEnrollment, string $noteCourse, string $notePublic): array {

		if($eDateEnrollment->isPublic()) {
			return [
				s("Voir mon inscription"),
				DatePublicUi::urlEnrollment($eDateEnrollment['eCommunity'], $eDateEnrollment['token']),
				$notePublic,
			];
		}

		return [
			s("Voir la formation"),
			self::getCourseUrl($eDateEnrollment),
			$noteCourse,
		];

	}

	/**
	 * Les autres dates de la formation : sur sa fiche pour qui la lit encore après l'annulation, sur
	 * la page publique du réseau pour une ligne qui en vient ou un compte que l'annulation prive de la
	 * fiche (`readsCourse`, posé par `DateEnrollmentLib::fillCanceled()` avec `eCommunity`) : la
	 * fiche le refuserait.
	 *
	 * @return array{0: string, 1: string}
	 */
	protected static function getOtherDates(DateEnrollment $eDateEnrollment): array {

		$eDateEnrollment->expects(['readsCourse']);

		if(
			$eDateEnrollment->isPublic() or
			$eDateEnrollment['readsCourse'] === FALSE
		) {

			$eDateEnrollment->expects(['eCommunity']);

			return [
				s("Les autres dates restent ouvertes sur la page des formations de {value}.", encode($eDateEnrollment['date']['course']['community']['name'])),
				DatePublicUi::urlList($eDateEnrollment['eCommunity'])
			];

		}

		return [
			s("Les autres dates de la formation restent ouvertes sur sa fiche."),
			self::getCourseUrl($eDateEnrollment)
		];

	}

	/**
	 * L'accusé de réception d'une inscription faite sur la page publique, selon l'état qu'elle a
	 * pris : demande à confirmer, place réservée ou liste d'attente. Il porte toujours le lien de la
	 * page de l'inscription, seul moyen d'y revenir sans compte, puis le contact (`getContact()`),
	 * l'adresse d'envoi ne recevant pas de réponse, et pour une ligne sans compte l'invitation à en
	 * créer un, comme l'écran qui suit l'envoi (planche E1, « Créer mon compte »), seulement si la
	 * formation a des leçons (`course.lessons`) : elle les promet.
	 *
	 * Sans le pied du gabarit pour une ligne sans compte (`hasFooter()`).
	 */
	public function getReceivedMail(DateEnrollment $eDateEnrollment, \structure\Structure $eStructureCommunity, Supervisor $eSupervisorContact): array {

		$eDateEnrollment->expects(['date' => ['course' => ['lessons']]]);

		$eCourse = $eDateEnrollment['date']['course'];

		$values = [
			'community' => encode($eCourse['community']['name']),
			'course' => encode($eCourse['name']),
			'day' => self::getDayOf($eDateEnrollment),
		];

		[$title, $heroTitle, $heroText] = match($eDateEnrollment['status']) {
			DateEnrollment::REQUESTED => [
				s("Demande d'inscription reçue pour la date {value}", self::getDayOf($eDateEnrollment)),
				s("Demande reçue"),
				s("{community} a bien reçu votre demande d'inscription à la formation « {course} ». Un e-mail vous dira quand elle sera confirmée.", $values)
			],
			DateEnrollment::CONFIRMED => [
				s("Votre place est réservée {value}", self::getDayOn($eDateEnrollment)),
				s("Place réservée"),
				s("Votre inscription à la formation « {course} » de {community} est confirmée.", $values)
			],
			DateEnrollment::WAITING => [
				s("Vous êtes sur la liste d'attente {value}", self::getDayOf($eDateEnrollment)),
				s("Liste d'attente"),
				s("La date {day} de la formation « {course} » est complète. Si une place se libère, {community} peut vous la donner, et un e-mail vous le dira.", $values)
			],
		};

		[$buttonLabel, $note] = match($eDateEnrollment['status']) {
			DateEnrollment::REQUESTED => [s("Voir ma demande"), s("Un empêchement ? Annulez votre demande depuis cette page, la place pourra revenir à une personne en attente.")],
			DateEnrollment::CONFIRMED => [s("Voir mon inscription"), s("Un empêchement ? Annulez votre inscription depuis cette page, la place pourra revenir à une personne en attente.")],
			DateEnrollment::WAITING => [s("Voir mon inscription"), s("Vous ne souhaitez plus attendre ? Quittez la liste d'attente depuis cette page.")],
		};

		[$asideTitle, $asideText] = ($eDateEnrollment['user']->empty() and $eCourse['lessons'] > 0)
			? [
				s("Les leçons se suivent avec un compte"),
				s("Créez votre compte avec l'adresse {value}. Il reprendra cette inscription, et les leçons de la formation s'ouvriront dès que votre place sera confirmée.", encode($eDateEnrollment->getEmail())).' '.\main\MailSoftwareAiUi::linkTag(\Lime::getUrl().'/inscription').s("Créer mon compte").'</a>'
			]
			: [NULL, NULL];

		return \main\MailSoftwareAiUi::format(
			$title,
			heroTitle: $heroTitle,
			heroText: $heroText,
			facts: $this->getFacts($eDateEnrollment, withStructure: TRUE, withLink: $eDateEnrollment['status'] === DateEnrollment::CONFIRMED, withPrice: TRUE),
			buttonLabel: $buttonLabel,
			buttonUrl: DatePublicUi::urlEnrollment($eDateEnrollment['eCommunity'], $eDateEnrollment['token']),
			buttonNote: $note,
			contentAfter: '<p>'.self::getContact($eStructureCommunity, $eSupervisorContact).'</p>',
			asideTitle: $asideTitle,
			asideText: $asideText,
			footer: self::hasFooter($eDateEnrollment)
		);

	}

	/**
	 * Le rappel, parti la veille ou l'avant-veille à chaque personne confirmée (`DateEnrollmentLib::remind()`).
	 * Il redit ce que la confirmation disait, avec le moyen d'annuler : un membre annule depuis la
	 * fiche, une ligne de la page publique depuis la page de son inscription, avec le contact
	 * (`getContact()`). Une ligne sans compte, et sans compte à son adresse, reçoit en plus
	 * l'invitation à en créer un quand la formation a des leçons : elles ne s'ouvrent qu'avec lui
	 * (`accountExists` et `course.lessons`, posés par `DateEnrollmentLib::getMails()`).
	 */
	public function getRemindMail(DateEnrollment $eDateEnrollment, \structure\Structure $eStructureCommunity, Supervisor $eSupervisorContact): array {

		$eDateEnrollment->expects(['accountExists', 'date' => ['course' => ['lessons']]]);

		$eCourse = $eDateEnrollment['date']['course'];

		[$buttonLabel, $buttonUrl, $buttonNote] = self::getCancelButton(
			$eDateEnrollment,
			s("Un empêchement ? Annulez votre inscription depuis la fiche de la formation, la place pourra revenir à une personne en attente."),
			s("Un empêchement ? Annulez votre inscription depuis cette page, la place pourra revenir à une personne en attente.")
		);

		[$asideTitle, $asideText] = ($eDateEnrollment['accountExists'] === FALSE and $eCourse['lessons'] > 0)
			? [
				s("Les leçons se suivent avec un compte"),
				s("Créez votre compte avec l'adresse {value}. Il reprendra votre inscription, et les leçons de la formation s'ouvriront avec lui.", encode($eDateEnrollment->getEmail())).' '.\main\MailSoftwareAiUi::linkTag(\Lime::getUrl().'/inscription').s("Créer mon compte").'</a>'
			]
			: [NULL, NULL];

		return \main\MailSoftwareAiUi::format(
			s("Votre formation {value}", self::getDayOf($eDateEnrollment)),
			heroTitle: s("Votre formation approche"),
			heroText: s("Votre place est confirmée pour la formation « {course} » de {community}.", ['community' => encode($eCourse['community']['name']), 'course' => encode($eCourse['name'])]),
			facts: $this->getFacts($eDateEnrollment, withStructure: TRUE, withLink: TRUE),
			buttonLabel: $buttonLabel,
			buttonUrl: $buttonUrl,
			buttonNote: $buttonNote,
			contentAfter: $eDateEnrollment->isPublic() ? '<p>'.self::getContact($eStructureCommunity, $eSupervisorContact).'</p>' : NULL,
			asideTitle: $asideTitle,
			asideText: $asideText,
			footer: self::hasFooter($eDateEnrollment)
		);

	}

	/**
	 * À chaque personne confirmée, quand le réseau ouvre le quiz de la date (`DateLib::openQuiz()`).
	 * Le quiz se passe avec un compte, d'où trois variantes : une ligne portée par un compte va au
	 * quiz ; une ligne sans compte dont l'adresse en a un y va aussi, la connexion rattachant la
	 * ligne au compte (L10c) ; une ligne sans compte à son adresse est invitée à en créer un.
	 *
	 * « du {day} » (`getDayOf()`) et non « que vous avez suivie le {day} » : le quiz s'ouvre dès le
	 * premier jour, avant que la formation ait été suivie.
	 */
	public function getQuizOpenedMail(DateEnrollment $eDateEnrollment, \structure\Structure $eStructureCommunity, Supervisor $eSupervisorContact): array {

		$eDateEnrollment->expects(['accountExists', 'date' => ['eLessonQuiz' => ['questions', 'needed']]]);

		$eDate = $eDateEnrollment['date'];
		$eCourse = $eDate['course'];
		$eLessonQuiz = $eDate['eLessonQuiz'];

		$heroText = s("{community} ouvre le quiz de la formation « {course} » {day}.", ['community' => encode($eCourse['community']['name']), 'course' => encode($eCourse['name']), 'day' => self::getDayOf($eDateEnrollment)]);

		$facts = [
			s("Quiz") => p("{value} question", "{value} questions", $eLessonQuiz['questions']),
			s("Pour valider") => p("{value} bonne réponse", "{value} bonnes réponses", $eLessonQuiz['needed']),
		];

		$quizUrl = \Lime::getUrl().QuizUi::url($eLessonQuiz);
		$title = s("Le quiz de la formation « {value} » est ouvert", $eCourse['name']);

		if($eDateEnrollment['user']->notEmpty()) {

			return \main\MailSoftwareAiUi::format(
				$title,
				heroTitle: s("Le quiz est ouvert"),
				heroText: $heroText,
				facts: $facts,
				buttonLabel: s("Passer le quiz"),
				buttonUrl: $quizUrl
			);

		}

		$email = encode($eDateEnrollment->getEmail());

		if($eDateEnrollment['accountExists']) {

			return \main\MailSoftwareAiUi::format(
				$title,
				heroTitle: s("Le quiz est ouvert"),
				heroText: $heroText,
				facts: $facts,
				buttonLabel: s("Passer le quiz"),
				buttonUrl: $quizUrl,
				buttonNote: s("Connectez-vous avec l'adresse {value}, votre inscription sera reprise dans votre compte.", $email),
				contentAfter: '<p>'.self::getContact($eStructureCommunity, $eSupervisorContact).'</p>',
				footer: FALSE
			);

		}

		return \main\MailSoftwareAiUi::format(
			$title,
			heroTitle: s("Le quiz est ouvert"),
			heroText: $heroText.' '.s("Le quiz se passe avec un compte Ouvretaferme."),
			facts: $facts,
			buttonLabel: s("Créer mon compte"),
			buttonUrl: \Lime::getUrl().'/inscription',
			buttonNote: s("Avec l'adresse {value}, le compte reprend votre inscription et vous mène au quiz.", $email),
			contentAfter: '<p>'.self::getContact($eStructureCommunity, $eSupervisorContact).'</p>',
			footer: FALSE
		);

	}

	/**
	 * Le lendemain de la date, à la personne confirmée d'une formation au catalogue public (lot L15c,
	 * `DateEnrollmentLib::invite()`) : le bouton mène au formulaire de l'avis, par le jeton pour une
	 * ligne de la page publique, sur la fiche sinon. Le nom sous lequel l'avis paraîtra est dit avant
	 * qu'il soit écrit.
	 */
	public function getReviewInvitedMail(DateEnrollment $eDateEnrollment): array {

		$eCourse = $eDateEnrollment['date']['course'];

		$author = new Review([
			'user' => $eDateEnrollment['user'],
			'dateEnrollment' => $eDateEnrollment
		])->getAuthorName();

		$firstName = $eDateEnrollment->getFirstName();

		$buttonUrl = $eDateEnrollment->isPublic()
			? ReviewUi::urlPublic($eDateEnrollment['eCommunity'], $eDateEnrollment['token'])
			: \Lime::getUrl().ReviewUi::urlCourse($eCourse);

		return \main\MailSoftwareAiUi::format(
			s("Votre avis sur la formation {value}", $eCourse['name']),
			heroTitle: s("Votre avis sur la formation"),
			heroText: s("{hello}, vous avez suivi la formation « {course} » {day} avec {community}. Vous pouvez la noter sur cinq étoiles et écrire un avis. Il paraît sur sa fiche sous « {author} », avec sa date.", [
				'hello' => ($firstName === NULL) ? s("Bonjour") : s("Bonjour {value}", encode($firstName)),
				'course' => encode($eCourse['name']),
				'day' => self::getDayOn($eDateEnrollment),
				'community' => encode($eCourse['community']['name']),
				'author' => encode($author)
			]),
			buttonLabel: s("Donner mon avis"),
			buttonUrl: $buttonUrl,
			footer: self::hasFooter($eDateEnrollment)
		);

	}

	/**
	 * L'attestation de présence d'une formation sans leçon (lot L8f) : un lien et jamais une pièce
	 * jointe, vers la page de l'inscription par son jeton pour une ligne de la page publique, vers la
	 * fiche sinon, où l'attestation se télécharge. Les heures sont celles du pointage, rapportées à la
	 * durée de la date quand la présence est partielle.
	 */
	public function getAttestationMail(DateEnrollment $eDateEnrollment): array {

		$eCourse = $eDateEnrollment['date']['course'];
		$firstName = $eDateEnrollment->getFirstName();

		$attended = $eDateEnrollment->getAttendedMinutes();
		$minutes = $eDateEnrollment['date']->getMinutes();

		$facts = $this->getFacts($eDateEnrollment, withStructure: FALSE, withLink: FALSE);
		$facts[s("Présence")] = ($attended < $minutes)
			? s("{attended} sur {minutes}", ['attended' => CourseUi::getDuration($attended), 'minutes' => CourseUi::getDuration($minutes)])
			: CourseUi::getDuration($attended);

		$buttonUrl = $eDateEnrollment->isPublic()
			? DatePublicUi::urlEnrollment($eDateEnrollment['eCommunity'], $eDateEnrollment['token'])
			: self::getCourseUrl($eDateEnrollment);

		return \main\MailSoftwareAiUi::format(
			s("Votre attestation de présence à la formation {value}", $eCourse['name']),
			heroTitle: s("Attestation de présence"),
			heroText: s("{hello}, {community} a constaté votre présence à la formation « {course} » {day}. Votre attestation est prête, au format PDF.", [
				'hello' => ($firstName === NULL) ? s("Bonjour") : s("Bonjour {value}", encode($firstName)),
				'community' => encode($eCourse['community']['name']),
				'course' => encode($eCourse['name']),
				'day' => self::getDayOn($eDateEnrollment),
			]),
			facts: $facts,
			buttonLabel: s("Télécharger mon attestation"),
			buttonUrl: $buttonUrl,
			buttonNote: s("L'attestation se télécharge depuis cette page autant de fois que nécessaire."),
			footer: self::hasFooter($eDateEnrollment)
		);

	}

	/**
	 * À qui écrire pour en savoir plus, l'adresse d'envoi ne recevant pas de réponse : l'encadrant
	 * contact de la formation, ou à défaut le réseau à son adresse légale. La même règle que la ligne
	 * Contact de la fiche (`CourseUi::getFicheRows()`).
	 */
	public static function getContact(\structure\Structure $eStructureCommunity, Supervisor $eSupervisorContact): string {

		[$name, $email] = $eSupervisorContact->empty()
			? [$eStructureCommunity['name'], $eStructureCommunity['legalEmail']]
			: [$eSupervisorContact['name'], $eSupervisorContact['email']];

		return s("Pour en savoir plus, écrivez à {name} à l'adresse <link>{email}</link>.", ['name' => encode($name), 'email' => encode($email), 'link' => \main\MailSoftwareAiUi::linkTag('mailto:'.$email)]);

	}

	/**
	 * Le pied du gabarit renvoie à la documentation et à la foire aux questions de Ouvretaferme, qui
	 * s'adressent à ses utilisateurs (`SoftwareAiUi`) : une personne inscrite sans compte n'en a pas
	 * l'usage. Une ligne de la page publique rattachée depuis à un compte le retrouve.
	 */
	protected static function hasFooter(DateEnrollment $eDateEnrollment): bool {
		return $eDateEnrollment['user']->notEmpty();
	}

	protected static function getDay(DateEnrollment $eDateEnrollment): string {
		return mb_strtolower(DateUi::getDays($eDateEnrollment['date']));
	}

	/**
	 * « du mardi 29 septembre 2026 », ou « du samedi 26 au lundi 28 septembre 2026 » : sur plusieurs
	 * jours, `DateUi::getDays()` porte déjà son « du », qu'une phrase en « du {day} » doublerait. Les
	 * phrases qui disent le jour s'écrivent donc sans leur préposition, qui vient d'ici ou de
	 * `getDayOn()`.
	 */
	protected static function getDayOf(DateEnrollment $eDateEnrollment): string {

		if($eDateEnrollment['date']->countDays() === 1) {
			return s("du {value}", self::getDay($eDateEnrollment));
		}

		return self::getDay($eDateEnrollment);

	}

	/**
	 * « le mardi 29 septembre 2026 », ou « du samedi 26 au lundi 28 septembre 2026 » : la forme en
	 * « le » de `getDayOf()`, pour une phrase qui situe dans le temps.
	 */
	protected static function getDayOn(DateEnrollment $eDateEnrollment): string {

		if($eDateEnrollment['date']->countDays() === 1) {
			return s("le {value}", self::getDay($eDateEnrollment));
		}

		return self::getDay($eDateEnrollment);

	}

	/**
	 * À la confirmation par le réseau, et à l'inscription immédiate : la personne annule depuis la
	 * fiche, qui rend sa place à qui attend.
	 */
	public function getConfirmedMail(DateEnrollment $eDateEnrollment): array {

		$eCourse = $eDateEnrollment['date']['course'];

		[$buttonLabel, $buttonUrl, $buttonNote] = self::getCancelButton(
			$eDateEnrollment,
			s("Un empêchement ? Annulez votre inscription depuis la fiche de la formation, la place pourra revenir à une personne en attente."),
			s("Un empêchement ? Annulez votre inscription depuis cette page, la place pourra revenir à une personne en attente.")
		);

		return \main\MailSoftwareAiUi::format(
			s("Votre inscription {value} est confirmée", self::getDayOf($eDateEnrollment)),
			heroTitle: s("Inscription confirmée"),
			heroText: s("{community} confirme votre inscription à la formation « {course} ».", ['community' => encode($eCourse['community']['name']), 'course' => encode($eCourse['name'])]),
			facts: $this->getFacts($eDateEnrollment, withStructure: TRUE, withLink: TRUE, withPrice: TRUE),
			buttonLabel: $buttonLabel,
			buttonUrl: $buttonUrl,
			buttonNote: $buttonNote,
			footer: self::hasFooter($eDateEnrollment)
		);

	}

	public function getSeatGivenMail(DateEnrollment $eDateEnrollment): array {

		$eCourse = $eDateEnrollment['date']['course'];

		[$buttonLabel, $buttonUrl, $buttonNote] = self::getCancelButton(
			$eDateEnrollment,
			s("Un empêchement ? Annulez votre inscription depuis la fiche de la formation, la place pourra revenir à une autre personne en attente."),
			s("Un empêchement ? Annulez votre inscription depuis cette page, la place pourra revenir à une autre personne en attente.")
		);

		return \main\MailSoftwareAiUi::format(
			s("Une place s'est libérée pour la date {value}", self::getDayOf($eDateEnrollment)),
			heroTitle: s("Une place pour vous"),
			heroText: s("Vous étiez en liste d'attente pour la formation « {course} ». {community} vous donne une place qui s'est libérée. Votre inscription est confirmée.", ['community' => encode($eCourse['community']['name']), 'course' => encode($eCourse['name'])]),
			facts: $this->getFacts($eDateEnrollment, withStructure: TRUE, withLink: TRUE, withPrice: TRUE),
			buttonLabel: $buttonLabel,
			buttonUrl: $buttonUrl,
			buttonNote: $buttonNote,
			footer: self::hasFooter($eDateEnrollment)
		);

	}

	/**
	 * À chaque personne confirmée, quand le réseau envoie le lien de la visio
	 * (`DateEnrollmentLib::sendLinked()`). L'e-mail rappelle la séance et rien d'autre. Le bouton
	 * principal rejoint la visio, et le lien est aussi dans les faits, lisible pour qui le copie dans
	 * son application. L'annulation, que portait le bouton des autres e-mails, passe dans le texte,
	 * avec le contact (`getContact()`) : la fiche pour un membre, la page de son inscription pour
	 * une ligne de la page publique.
	 *
	 * Le gabarit ne dit pas « nouveau lien » : un lien changé après un premier envoi repart par le
	 * même geste, et la date ne garde pas trace du premier.
	 */
	public function getLinkedMail(DateEnrollment $eDateEnrollment, \structure\Structure $eStructureCommunity, Supervisor $eSupervisorContact): array {

		$eDate = $eDateEnrollment['date'];
		$eCourse = $eDate['course'];

		$cancel = $eDateEnrollment->isPublic()
			? s("Un empêchement ? Annulez votre inscription depuis <link>la page de votre inscription</link>, la place pourra revenir à une personne en attente.", ['link' => \main\MailSoftwareAiUi::linkTag(DatePublicUi::urlEnrollment($eDateEnrollment['eCommunity'], $eDateEnrollment['token']))])
			: s("Un empêchement ? Annulez votre inscription depuis <link>la fiche de la formation</link>, la place pourra revenir à une personne en attente.", ['link' => \main\MailSoftwareAiUi::linkTag(self::getCourseUrl($eDateEnrollment))]);

		return \main\MailSoftwareAiUi::format(
			s("Le lien de la visio {value}", self::getDayOf($eDateEnrollment)),
			heroTitle: s("Lien de la visio"),
			heroText: s("{community} vous envoie le lien de la visio de la formation « {course} », à laquelle votre inscription est confirmée.", ['community' => encode($eStructureCommunity['name']), 'course' => encode($eCourse['name'])]),
			facts: $this->getFacts($eDateEnrollment, withStructure: FALSE, withLink: TRUE, withDuration: TRUE),
			buttonLabel: s("Rejoindre la visio"),
			buttonUrl: $eDate['link'],
			contentAfter: $cancel.'<br/><br/>'.self::getContact($eStructureCommunity, $eSupervisorContact),
			footer: self::hasFooter($eDateEnrollment)
		);

	}

	/**
	 * Au réseau, à son adresse de contact, quand le rappel d'une visio est parti sans lien
	 * (`DateEnrollmentLib::getLinkMissingMails()`) : les personnes confirmées l'attendent, et la
	 * séance est dans un ou deux jours. Deux textes, selon ce qui reste à faire : poser le lien puis
	 * l'envoyer, ou seulement l'envoyer quand il est déjà sur la date. Aucun ne promet que le lien
	 * parte de lui-même. Le bouton mène à l'éditeur de la formation, où la date le signale et porte
	 * le geste. Le jour se lit dans les faits, qui le disent sur une ou plusieurs journées sans
	 * phrase à accorder.
	 */
	public function getLinkMissingMail(Date $eDate, int $waiting): array {

		$eCourse = $eDate['course'];

		$values = ['course' => encode($eCourse['name'])];

		[$title, $heroTitle, $heroText, $buttonLabel] = $eDate->isLinkMissing()
			? [
				s("Lien de la visio à poser pour « {value} »", encode($eCourse['name'])),
				s("Lien de la visio à poser"),
				p(
					"Le rappel de la formation « {course} » est parti sans le lien de la visio, et {value} personne confirmée l'attend. Posez-le sur la date, puis cliquez sur « Envoyer le lien de la visio » pour qu'elle le reçoive par e-mail.",
					"Le rappel de la formation « {course} » est parti sans le lien de la visio, et {value} personnes confirmées l'attendent. Posez-le sur la date, puis cliquez sur « Envoyer le lien de la visio » pour qu'elles le reçoivent par e-mail.",
					$waiting,
					$values
				),
				s("Poser le lien de la visio")
			]
			: [
				s("Lien de la visio à envoyer pour « {value} »", encode($eCourse['name'])),
				s("Lien de la visio à envoyer"),
				p(
					"Le rappel de la formation « {course} » est parti sans le lien de la visio, et {value} personne confirmée l'attend. Le lien est déjà posé sur la date, il reste à cliquer sur « Envoyer le lien de la visio » pour qu'elle le reçoive par e-mail.",
					"Le rappel de la formation « {course} » est parti sans le lien de la visio, et {value} personnes confirmées l'attendent. Le lien est déjà posé sur la date, il reste à cliquer sur « Envoyer le lien de la visio » pour qu'elles le reçoivent par e-mail.",
					$waiting,
					$values
				),
				s("Envoyer le lien")
			];

		return \main\MailSoftwareAiUi::format(
			$title,
			heroTitle: $heroTitle,
			heroText: $heroText,
			facts: [
				s("Date") => DateUi::getDays($eDate),
				s("Horaire") => s("{from} à {to}", [
					'from' => \util\DateUi::textual($eDate['startAt'], \util\DateUi::TIME_HOUR_MINUTE),
					'to' => \util\DateUi::textual($eDate['endAt'], \util\DateUi::TIME_HOUR_MINUTE)
				]),
			],
			buttonLabel: $buttonLabel,
			buttonUrl: \Lime::getUrl().CourseUi::urlEdition($eCourse).'?tab=dates'
		);

	}

	public function getDateCanceledMail(DateEnrollment $eDateEnrollment): array {

		$eCourse = $eDateEnrollment['date']['course'];

		[$otherDates, $otherDatesUrl] = self::getOtherDates($eDateEnrollment);

		$values = ['community' => encode($eCourse['community']['name']), 'day' => self::getDayOf($eDateEnrollment), 'course' => encode($eCourse['name'])];

		$lessons = $eDateEnrollment['losesLessons']
			? s("Vous n'avez plus accès aux leçons de la formation. Votre progression vous sera rendue dès que votre inscription à une autre date sera confirmée.").' '
			: '';

		return \main\MailSoftwareAiUi::format(
			s("La date {value} est annulée", self::getDayOf($eDateEnrollment)),
			'<p>'.$lessons.$otherDates.'</p>',
			heroTitle: s("Date annulée"),
			heroText: $eDateEnrollment->isRequest()
				? s("{community} annule la date {day} de la formation « {course} ». Votre demande pour cette date est annulée.", $values)
				: s("{community} annule la date {day} de la formation « {course} ». Votre inscription à cette date est annulée.", $values),
			facts: $this->getFacts($eDateEnrollment, withStructure: FALSE, withLink: FALSE),
			buttonLabel: s("Voir les autres dates"),
			buttonUrl: $otherDatesUrl,
			footer: self::hasFooter($eDateEnrollment)
		);

	}

	/**
	 * À la personne, quand le réseau annule. L'adresse d'envoi ne reçoit pas de réponse : c'est
	 * le contact (`getContact()`) qui permet d'en savoir plus.
	 */
	public function getCanceledByCommunityMail(DateEnrollment $eDateEnrollment, \structure\Structure $eStructureCommunity, Supervisor $eSupervisorContact): array {

		$eCourse = $eDateEnrollment['date']['course'];

		[$otherDates, $otherDatesUrl] = self::getOtherDates($eDateEnrollment);

		$values = ['community' => encode($eStructureCommunity['name']), 'day' => self::getDayOf($eDateEnrollment), 'course' => encode($eCourse['name'])];

		[$title, $heroTitle, $heroText] = $eDateEnrollment->isRequest()
			? [
				s("Votre demande {value} est annulée", self::getDayOf($eDateEnrollment)),
				s("Demande annulée"),
				s("{community} annule votre demande pour la date {day} de la formation « {course} ».", $values)
			]
			: [
				s("Votre inscription {value} est annulée", self::getDayOf($eDateEnrollment)),
				s("Inscription annulée"),
				s("{community} annule votre inscription à la date {day} de la formation « {course} ».", $values)
			];

		$lessons = $eDateEnrollment['losesLessons']
			? s("Vous n'avez plus accès aux leçons de la formation. Votre progression vous sera rendue si votre inscription est de nouveau confirmée.").' '
			: '';

		return \main\MailSoftwareAiUi::format(
			$title,
			'<p>'.$lessons.self::getContact($eStructureCommunity, $eSupervisorContact).' '.$otherDates.'</p>',
			heroTitle: $heroTitle,
			heroText: $heroText,
			facts: $this->getFacts($eDateEnrollment, withStructure: FALSE, withLink: FALSE),
			buttonLabel: s("Voir les autres dates"),
			buttonUrl: $otherDatesUrl,
			footer: self::hasFooter($eDateEnrollment)
		);

	}

	/**
	 * Au réseau, quand une personne annule : la place qu'elle libère attend son geste s'il y a une
	 * liste d'attente, et c'est la liste qui la donne.
	 */
	public function getCanceledByUserMail(DateEnrollment $eDateEnrollment): array {

		$eCourse = $eDateEnrollment['date']['course'];

		$values = ['name' => $eDateEnrollment->getName(), 'day' => self::getDayOf($eDateEnrollment), 'course' => encode($eCourse['name'])];

		[$title, $heroTitle, $heroText] = $eDateEnrollment->isRequest()
			? [
				s("{name} annule sa demande {day}", ['name' => decode($values['name']), 'day' => $values['day']]),
				s("Demande annulée"),
				s("{name} annule sa demande pour la date {day} de la formation « {course} ».", $values)
			]
			: [
				s("{name} annule son inscription {day}", ['name' => decode($values['name']), 'day' => $values['day']]),
				s("Inscription annulée"),
				s("{name} annule son inscription à la date {day} de la formation « {course} ».", $values)
			];

		$lessons = $eDateEnrollment['losesLessons']
			? s("Cette personne n'a plus accès aux leçons de la formation. Sa progression lui sera rendue si son inscription est de nouveau confirmée.").' '
			: '';

		return \main\MailSoftwareAiUi::format(
			$title,
			'<p>'.$lessons.s("Si des personnes attendent une place à cette date, vous pouvez la donner depuis la liste des inscrits.").'</p>',
			heroTitle: $heroTitle,
			heroText: $heroText,
			facts: $this->getFacts($eDateEnrollment, withStructure: TRUE, withLink: FALSE),
			buttonLabel: s("Voir les inscrits"),
			buttonUrl: \Lime::getUrl().self::urlList($eDateEnrollment['date']),
			footer: FALSE
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = DateEnrollment::model()->describer($property, [
			'structure' => s("Au nom de"),
			'structureName' => s("Ferme"),
			'firstName' => s("Prénom"),
			'lastName' => s("Nom"),
			'email' => s("Adresse e-mail"),
			'trainee' => s("Statut"),
			'status' => s("État"),
			'attendedMinutes' => s("Heures de présence"),
		]);

		switch($property) {

			case 'firstName' :
			case 'lastName' :
				$d->required = TRUE;
				break;

			case 'email' :
				$d->field = 'email';
				$d->required = TRUE;
				break;

			// La ferme de la personne, en texte libre : sans compte, aucune structure à choisir.
			case 'structureName' :
				$d->after = \util\FormUi::info(s("Laissez vide si vous venez à titre personnel."));
				break;

			case 'structure' :
				$d->field = 'radio';
				$d->values = function(DateEnrollment $e) {

					$e->expects(['cStructure', 'isPersonal']);

					$values = [];

					foreach($e['cStructure'] as $eStructure) {
						$values[$eStructure['id']] = encode($eStructure['name']);
					}

					// `FormUi::inputRadio()` compare la valeur à (string) NULL, soit '', et cocherait ce
					// choix dès qu'aucune structure ne l'est : un élément vide ne distingue pas « rien de
					// choisi » de « Aucune », c'est `isPersonal` qui le dit.
					$values[] = ['value' => '', 'label' => s("Aucune, à titre personnel"), 'attributes' => ['checked' => $e['isPersonal']]];

					return $values;

				};
				$d->required = TRUE;
				break;

			case 'trainee' :
				$d->field = 'radio';
				$d->values = [
					DateEnrollment::FARMER => s("Chef d'exploitation"),
					DateEnrollment::SOLIDARITY => s("Cotisant solidaire"),
					DateEnrollment::EMPLOYEE => s("Salarié"),
					DateEnrollment::PROJECT => s("Porteur de projet"),
				];
				$d->required = TRUE;
				break;

			// En heures au pas de 0,5, que `DateEnrollment::properties()` convertit en minutes. Vide, la
			// personne compte la durée de la date, que le champ montre en indication.
			case 'attendedMinutes' :
				$d->field = fn(\util\FormUi $form, DateEnrollment $e) => DateUi::getHoursField($form, DateEnrollment::model(), 'attendedMinutes', $e['attendedMinutes'], $e['date']->getMinutes());
				break;

			case 'status' :
				$d->values = [
					DateEnrollment::REQUESTED => s("À confirmer"),
					DateEnrollment::CONFIRMED => s("Confirmée"),
					DateEnrollment::WAITING => s("En attente"),
					DateEnrollment::CANCELED => s("Annulée"),
				];
				break;

		}

		return $d;

	}

}
?>
