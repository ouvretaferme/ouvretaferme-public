<?php
namespace learning;

/**
 * Le parcours de création d'une formation : le type dans un réseau, la formation, puis la première
 * date d'une formation en présentiel ou en visio, ou le premier module d'une formation en ligne.
 *
 * La formation s'écrit en base dès l'étape « La formation » (`course:doCreate`), en brouillon : les
 * étapes suivantes sont des écritures ordinaires, et un parcours abandonné laisse un brouillon que
 * l'éditeur reprend. Chaque étape est un formulaire qui poste à sa propre action ; l'aperçu se rend
 * ici, à chaque rechargement de l'étape, et ne montre donc que ce qui a été envoyé.
 *
 * Le parcours emprunte les classes du composant `onboarding` sans son script, comme l'inscription
 * à une date (`DateEnrollmentUi::getStepPanel()`) : les états de la barre sont posés par le serveur,
 * aucun attribut `data-onboarding-*` n'est écrit, et on recule par le nom d'une étape faite.
 */
class CourseStartUi {

	public function __construct() {
		\Asset::css('main', 'onboarding.css');
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'courseStart.js');
	}

	/**
	 * Les étapes de la formation, dans l'ordre. Le type n'est demandé qu'à un réseau, seul à tenir
	 * des formations en présentiel ou en visio ; il décide de la troisième étape. Un type que le
	 * réseau n'a pas encore choisi (`NULL`) annonce la date, comme le premier des trois.
	 */
	public static function getSteps(Course $eCourse): array {

		$steps = $eCourse->isNetwork() ? ['type'] : [];

		$steps[] = 'formation';
		$steps[] = ($eCourse->isNetwork() and $eCourse['type'] !== Course::ONLINE) ? 'date' : 'module';

		return $steps;

	}

	/**
	 * Ce que sont les formations, au-dessus du parcours, tant que le groupe n'en a aucune : l'onglet
	 * vide ouvre sur le parcours de création, qui ne dit pas ce que la fonctionnalité permet ensuite.
	 * Seul un réseau date ses formations et reçoit des inscriptions, un groupe de formation n'écrit
	 * que des formations en ligne pour ses promotions : chacun lit ce qu'il pourra faire, pas ce que
	 * l'autre fait.
	 */
	public function getIntroduction(\structure\Structure $eStructure): string {

		if($eStructure->isNetwork()) {

			$content = '<p>'.s("Créez ici les formations du réseau, en présentiel, en visio ou en ligne, et proposez-les à ses membres.").'</p>';
			$content .= \main\DesignUi::getStartGains([
				['calendar-check', s("Les dates et les inscriptions"), s("Vos dates publiées sur une page publique ou sur votre site, et les inscriptions reçues ici")],
				['person-check', s("La présence"), s("La présence pointée après chaque date et les attestations envoyées par e-mail")],
				['journal-text', s("Les leçons en ligne"), s("Des leçons et des quiz que chacun suit à son rythme, et sa progression leçon par leçon")],
			]);

		} else {

			$content = '<p>'.s("Créez ici les formations en ligne du groupe et ouvrez-les à vos promotions.").'</p>';
			$content .= \main\DesignUi::getStartGains([
				['journal-text', s("Les leçons et leurs quiz"), s("Des leçons rangées en modules, que les étudiants suivent à leur rythme")],
				['bar-chart', s("La progression"), s("Ce que chaque étudiant a suivi, leçon par leçon et promotion par promotion")],
				['chat-left-text', s("Les commentaires"), s("Les questions des étudiants sous chaque leçon, et vos réponses")],
			]);

		}

		return new \main\DesignUi()->getStart(
			\Asset::icon(\structure\StructureUi::getCommunityTabIcon('learning')),
			s("Les formations"),
			$content,
			image: \Asset::image('main', 'friends/poussin.svg')
		);

	}

	/**
	 * `$withBack` rend le retour à la liste, qui n'est proposé que si la liste a une formation à
	 * montrer : l'onglet vide d'un groupe est ce parcours.
	 */
	public function getStart(Course $eCourse, string $step, bool $withBack): string {

		$h = '<div class="onboarding">';

			if($withBack) {
				$h .= '<div class="onboarding-header">';
					$h .= '<a href="'.CourseUi::urlList($eCourse).'" class="onboarding-header-skip">'.s("Revenir aux formations").'</a>';
				$h .= '</div>';
			}

			$h .= '<div class="util-card onboarding-panel">';

				$h .= '<div class="onboarding-steps">';

					$h .= $this->getProgress($eCourse, $step);

					$h .= match($step) {
						'type' => $this->getStepType($eCourse),
						'formation' => $this->getStepCourse($eCourse),
						'date' => $this->getStepDate($eCourse),
						'module' => $this->getStepModule($eCourse),
					};

				$h .= '</div>';

				$h .= $this->getPreview($eCourse, $step);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La barre et les noms des étapes. Une étape faite, hors l'étape courante, porte sur son nom
	 * l'adresse qui y ramène : celle de `course:create` tant que la formation n'est pas écrite,
	 * `/demarrer` ensuite. Une étape à venir ne mène nulle part, pour qu'aucune ne s'atteigne sans
	 * son bouton.
	 */
	protected function getProgress(Course $eCourse, string $step): string {

		$steps = self::getSteps($eCourse);
		$current = array_search($step, $steps, TRUE);

		$names = [
			'type' => s("Le type"),
			'formation' => s("La formation"),
			'date' => s("La première date"),
			'module' => s("Le premier module"),
		];

		// Une étape déjà écrite reste faite quand on y revient par son nom : la barre dit ce qui a été
		// fait, pas où l'on est. Le type et la formation le sont dès que la formation existe.
		$done = [];

		foreach($steps as $position => $slug) {
			$done[$slug] = (
				$position < $current or
				($eCourse->exists() and in_array($slug, ['type', 'formation'], TRUE))
			);
		}

		$classes = [];

		foreach($steps as $position => $slug) {
			if($position === $current) {
				$classes[$slug] = ' class="is-current"';
			} else if($done[$slug]) {
				$classes[$slug] = ' class="is-done"';
			} else {
				$classes[$slug] = '';
			}
		}

		$h = '<div class="onboarding-progress">';

			$h .= '<div class="onboarding-stepper">';
				foreach($steps as $slug) {
					$h .= '<span'.$classes[$slug].'></span>';
				}
			$h .= '</div>';

			$h .= '<div class="onboarding-progress-label onboarding-progress-names">';
				foreach($steps as $position => $slug) {
					$target = ($position !== $current and $done[$slug]) ? ' data-get="'.self::urlStep($eCourse, $slug).'"' : '';
					$h .= '<span'.$classes[$slug].$target.'>'.$names[$slug].'</span>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Avant l'écriture, seul le type se revoit, et le titre saisi à l'étape suivante sans avoir été
	 * envoyé se perd : il n'est écrit nulle part, et le reposer dans l'adresse la chargerait d'une
	 * saisie.
	 */
	protected static function urlStep(Course $eCourse, string $slug): string {

		if($eCourse->exists() === FALSE) {
			return CourseUi::urlCreate($eCourse['community'], $eCourse['type']).'&etape=type';
		}

		return CourseUi::urlStart($eCourse).'?etape='.$slug;

	}

	/**
	 * Avant l'écriture, le type ne s'écrit nulle part : l'étape soumet en GET à `course:create`, qui
	 * rouvre le parcours à la formation avec le type dans l'adresse. Un bouton radio que personne n'a
	 * coché n'est pas envoyé : `etape` dit à la page que l'étape a été soumise, pour qu'elle refuse un
	 * envoi sans type au lieu de rouvrir l'étape comme à la première visite. Après l'écriture, le type
	 * se change par `doUpdateStartType`, que `Course::type.dated` garde une fois la première date posée.
	 */
	protected function getStepType(Course $eCourse): string {

		$form = new \util\FormUi(['columns' => 1]);

		if($eCourse->exists()) {
			$h = $form->openAjax('/learning/course:doUpdateStartType', ['class' => 'onboarding-step is-current']);
			$h .= $form->hidden('id', $eCourse);
		} else {
			$h = $form->openAjax('/learning/course:create', ['method' => 'get', 'class' => 'onboarding-step is-current']);
			$h .= $form->hidden('community', $eCourse['community']);
			$h .= $form->hidden('etape', 'formation');
		}

			$h .= '<h2>'.s("Comment se suit la formation ?").'</h2>';

			$types = [Course::ONSITE, Course::REMOTE, Course::ONLINE];

			$h .= '<div class="onboarding-tiles" data-wrapper="type">';
				$h .= $form->radios('type', array_combine($types, $types), $eCourse['type'], [
					'required' => TRUE,
					'callbackRadioContent' => fn(string $type) => $this->getTile(CourseUi::getTypeMark($type), CourseUi::getType($type)[2])
				]);
			$h .= '</div>';

			$h .= $this->getFooter($form->submit(s("Continuer").' '.\Asset::icon('arrow-right'), ['class' => 'btn btn-primary']));

		$h .= $form->close();

		return $h;

	}

	/**
	 * Le bouton dit ce qu'il fait : c'est ici que la formation naît, et une fois écrite l'étape ne
	 * fait plus que l'enregistrer avant la suivante.
	 */
	protected function getStepCourse(Course $eCourse): string {

		$form = new \util\FormUi(['columns' => 1]);

		if($eCourse->exists()) {
			$h = $form->openAjax('/learning/course:doUpdateStart', ['class' => 'onboarding-step is-current']);
			$h .= $form->hidden('id', $eCourse);
			$submit = s("Continuer").' '.\Asset::icon('arrow-right');
		} else {
			$h = $form->openAjax('/learning/course:doCreate', ['class' => 'onboarding-step is-current']);
			if($eCourse->isTeam() === FALSE) {
				$h .= $form->hidden('community', $eCourse['community']);
			}
			$h .= $form->hidden('type', $eCourse['type']);
			$submit = s("Créer la formation");
		}

			$h .= '<h2>'.s("Présentez la formation").'</h2>';

			$h .= $form->dynamicGroups($eCourse, ['name*', 'summary', 'theme']);
			$h .= CourseUi::getSubthemeField($form, $eCourse);
			$h .= $form->dynamicGroup($eCourse, 'level');

			$h .= $this->getFooter($form->submit($submit, ['class' => 'btn btn-primary']));

		$h .= $form->close();

		return $h;

	}

	/**
	 * Les champs du panneau « Ajouter une date », postés à la même action : `origin` lui dit de
	 * terminer le parcours dans l'éditeur plutôt que de recharger.
	 */
	protected function getStepDate(Course $eCourse): string {

		$eDate = new Date([
			'course' => $eCourse,
			'cDateSequence' => new \Collection([$eCourse->getDefaultSequence()]),
			'startAt' => NULL,
			'endAt' => NULL,
			'minutes' => NULL
		]);

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->openAjax('/learning/date:doCreate', ['class' => 'onboarding-step is-current']);

			$h .= $form->hidden('course', $eCourse);
			$h .= $form->hidden('origin', 'start');

			$h .= '<h2>'.match($eCourse['type']) {
				Course::ONSITE, Course::MIXED => s("Quand et où a lieu la première date ?"),
				Course::REMOTE => s("Quand a lieu la première date ?"),
			}.'</h2>';

			$h .= new DateUi()->getFields($form, $eDate);

			$h .= $this->getFooter(
				$form->submit(s("Ajouter la date"), ['class' => 'btn btn-primary']),
				$this->getSkip($eCourse)
			);

		$h .= $form->close();

		return $h;

	}

	/**
	 * Le module et les titres de ses leçons, d'un seul envoi. Les leçons se postent en
	 * `lessonName[<ligne>]`, le nom du module occupant `name` ; `courseStart.js` ajoute et retire
	 * des lignes depuis le gabarit, en continuant la numérotation pour qu'aucune ne reprenne l'index
	 * d'une ligne retirée.
	 */
	protected function getStepModule(Course $eCourse): string {

		$eChapter = new Chapter([
			'course' => $eCourse,
			'number' => $eCourse['chapters'] + 1
		]);

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->openAjax('/learning/course:doCreateProgram', ['class' => 'onboarding-step is-current']);

			$h .= $form->hidden('id', $eCourse);

			$h .= '<h2>'.s("Par quoi commence la formation ?").'</h2>';

			$h .= $form->dynamicGroup($eChapter, 'name*');

			// Le bouton sort de la liste pour prendre l'espacement d'un geste sous un bloc, les lignes
			// restant serrées entre elles.
			$rows = '<div class="onboarding-rows" data-index="1">';
				$rows .= $this->getLessonRow($form, '0');
			$rows .= '</div>';
			$rows .= '<a class="btn btn-outline-primary mt-1" onclick="LearningCourseStart.add(this)">'.\Asset::icon('plus-circle').' '.s("Ajouter une leçon").'</a>';
			$rows .= '<template>'.$this->getLessonRow($form, '__i__').'</template>';

			// Aucun wrapper `lessons` : le refus du plafond ne met aucune ligne en faute, et sans champ
			// qui le porte, `Lime.Alert` le rend dans le bandeau, visible sans défiler au bas d'une
			// longue liste.
			$h .= $form->group(s("Leçons"), $rows, ['for' => FALSE, 'data-wrapper' => '']);

			$h .= $this->getFooter(
				$form->submit(s("Créer le module"), ['class' => 'btn btn-primary']),
				$this->getSkip($eCourse)
			);

		$h .= $form->close();

		return $h;

	}

	/**
	 * Le wrapper porte le nom que `buildIndex()` donne au refus de la ligne, `name[<ligne>]`. L'id est
	 * posé d'après l'index : celui que `FormUi` tire au hasard serait rendu une fois dans le gabarit, puis
	 * recopié à l'identique sur chaque ligne ajoutée.
	 */
	protected function getLessonRow(\util\FormUi $form, string $index): string {

		$h = '<div class="util-card onboarding-row">';
			$h .= '<div class="onboarding-row-main">';
				$h .= '<div class="onboarding-field onboarding-field-name" data-wrapper="name['.$index.']">';
					$h .= $form->text('lessonName['.$index.']', NULL, ['id' => 'lesson-name-'.$index, 'placeholder' => s("Titre de la leçon")]);
				$h .= '</div>';
				$h .= '<a class="btn btn-only-icon btn-sm btn-outline-danger" onclick="LearningCourseStart.remove(this)" title="'.s("Retirer").'">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La troisième étape se passe : la formation est déjà un brouillon, que l'éditeur complète.
	 * Le lien charge la page entière, comme la `RedirectAction` des formulaires voisins : le message
	 * de `success` n'est rendu qu'au chargement complet (`main/view/Root.php`), une navigation ajax
	 * l'ignore et le laisse dans l'adresse.
	 */
	protected static function urlFinish(Course $eCourse): string {
		return CourseUi::urlEdition($eCourse).'?tab=lessons&success=learning\\Course::created';
	}

	protected function getTile(string $title, string $description): string {

		$h = '<div class="onboarding-tile">';
			$h .= '<div class="onboarding-tile-text">';
				$h .= '<div class="onboarding-tile-title">'.$title.'</div>';
				$h .= '<div class="onboarding-tile-desc">'.$description.'</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Passer l'étape ne répond pas à la question qu'elle pose : le lien va dans `onboarding-footer-side`,
	 * à gauche, et `onboarding-footer-main` ne porte que le geste qui l'envoie. Sous 575 px, le
	 * composant empile le lien sous le bouton principal (`onboarding.css`).
	 */
	protected function getFooter(string $submit, string $side = ''): string {

		$h = '<div class="onboarding-footer">';

			if($side !== '') {
				$h .= '<div class="onboarding-footer-side">'.$side.'</div>';
			}

			$h .= '<div class="onboarding-footer-main">'.$submit.'</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La troisième étape se passe sans rien envoyer : un lien vers la fin du parcours, et non un
	 * bouton du formulaire, qui posterait les champs à moitié remplis.
	 */
	protected function getSkip(Course $eCourse): string {
		return '<a href="'.self::urlFinish($eCourse).'" data-ajax-navigation="never" class="btn btn-outline-primary">'.s("Passer cette étape").'</a>';
	}

	/**
	 * La carte de la formation au catalogue (`CourseUi::getCard()`), réduite à ce qui a été envoyé :
	 * rien avant le type, sa seule marque avant la formation dans un réseau, puis la couverture de la
	 * thématique, qui prend la marque du type comme au catalogue, le titre et le niveau. Ni auteur,
	 * ni accès, ni progression, ni compte à zéro : personne ne les a posés. L'en-tête ne paraît
	 * qu'avec la carte.
	 */
	protected function getPreview(Course $eCourse, string $step): string {

		$card = $this->getCard($eCourse, $step);

		$h = '<aside class="onboarding-preview">';

			if($card !== '') {
				$h .= '<div class="onboarding-preview-header">';
					$h .= '<span class="onboarding-preview-title">'.s("Aperçu au catalogue").'</span>';
				$h .= '</div>';
			}

			$h .= '<div class="onboarding-preview-body">'.$card.'</div>';

			if($step !== 'type') {
				$h .= $this->getTip($eCourse, $step);
			}

		$h .= '</aside>';

		return $h;

	}

	protected function getCard(Course $eCourse, string $step): string {

		if($eCourse->exists() === FALSE) {

			if(
				$eCourse->isNetwork() === FALSE or
				$step === 'type'
			) {
				return '';
			}

			$h = '<div class="util-deck util-deck-top learning-card">';
				$h .= '<div class="util-deck-head"><div class="util-deck-body">';
					$h .= '<p>'.CourseUi::getTypeMark($eCourse['type']).'</p>';
				$h .= '</div></div>';
			$h .= '</div>';

			return $h;

		}

		$hasCover = (
			$eCourse['vignette'] !== NULL or
			$eCourse['theme']->notEmpty()
		);

		$h = '<div class="util-deck util-deck-top learning-card">';

			if($hasCover) {
				$h .= CourseUi::getCover($eCourse, badges: CourseUi::getCardBadges($eCourse, withAuthor: FALSE));
			}

			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-deck-body">';

					$h .= '<div class="util-deck-title">'.encode($eCourse['name']).'</div>';

					$type = $hasCover ? '' : CourseUi::getTypeBadge($eCourse);
					$level = CourseUi::getLevel($eCourse);

					if(
						$type !== '' or
						$level !== NULL
					) {
						$h .= '<p>';
							$h .= $type;
							if($level !== NULL) {
								$h .= '<span class="util-annotation">'.$level.'</span>';
							}
						$h .= '</p>';
					}

				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un conseil par étape, sa question en gras seule sur sa ligne (`onboarding.css`). L'étape du type
	 * n'en a pas : ses tuiles disent chacune ce qu'elles décident. Celui de l'étape formation dit ce
	 * que la publication exigera, une leçon en plus pour une formation en ligne (`Course::status.program`).
	 */
	protected function getTip(Course $eCourse, string $step): string {

		$publication = match($eCourse['type']) {
			Course::ONLINE => s("Pour la publier, il faudra une présentation, une thématique et au moins une leçon."),
			Course::ONSITE, Course::REMOTE, Course::MIXED => s("Pour la publier, il faudra une présentation et une thématique."),
		};

		[$question, $answer] = match($step) {
			'formation' => [s("Rien de visible avant la publication."), s("La formation reste un brouillon jusqu'à ce que vous la publiiez.").' '.$publication],
			'date' => [s("Une date suffit pour commencer."), s("Les suivantes s'ajoutent depuis la formation, et les fermes du réseau s'inscrivent à chacune.")],
			'module' => [s("Des titres suffisent."), s("Chaque leçon s'écrit ensuite depuis la formation, dans l'ordre que vous voulez.")],
		};

		return '<div class="util-card onboarding-tip">'.\Asset::icon('info-circle').'<div><b>'.$question.'</b> '.$answer.'</div></div>';

	}

}
?>
