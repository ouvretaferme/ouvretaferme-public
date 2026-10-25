<?php
namespace learning;

/**
 * Une leçon : sa lecture, sur une page publique (`view/lesson.v.php`), et son édition par l'auteur de
 * la formation.
 */
class LessonUi {

	use \Notifiable;

	const EDITION_TABS = ['content', 'attachments', 'quiz'];

	const MODE_EDITION = 'edition';
	const MODE_PREVIEW = 'preview';

	const EDITION_MODES = [self::MODE_EDITION, self::MODE_PREVIEW];

	public function __construct() {
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	public static function url(Lesson $eLesson, array $query = []): string {
		return '/formation/lecon/'.$eLesson['id'].($query === [] ? '' : '?'.http_build_query($query));
	}

	public static function urlEdition(Lesson $eLesson): string {
		return self::url($eLesson).'/edition';
	}

	/**
	 * L'écran de l'auteur, son contenu dans le mode demandé. Le mode voyage dans l'adresse et nulle
	 * part ailleurs : sans lui, le contenu s'ouvre en édition. L'onglet du contenu est demandé avec
	 * lui, pour que la bascule y reste quel que soit l'onglet que le navigateur a retenu.
	 */
	public static function urlEditionMode(Lesson $eLesson, string $mode): string {

		return self::urlEdition($eLesson).'?tab=content'.match($mode) {
			self::MODE_EDITION => '',
			self::MODE_PREVIEW => '&mode='.self::MODE_PREVIEW,
		};

	}

	public static function urlLeave(Lesson $eLesson, string $destination): string {
		return '/learning/lesson:leave?id='.$eLesson['id'].'&destination='.$destination;
	}

	/**
	 * La marque d'une leçon vide (`Lesson::isWritten()`), partout où le programme la montre : la
	 * colonne d'une leçon, la fiche et son menu, le sommaire des tutoriels.
	 */
	public static function getSoon(): string {
		return '<span class="badge badge-info">'.s("À venir").'</span>';
	}

	/**
	 * La forme calculée d'une leçon (`LessonLib::getFormat()`). Une leçon vide n'a pas de picto : elle
	 * n'a pas de forme, seulement son état.
	 */
	public static function getFormat(string $format): string {

		$icon = match($format) {
			Lesson::VIDEO => \Asset::icon('play-btn-fill').' ',
			Lesson::STEPS => \Asset::icon('list-ol').' ',
			Lesson::TEXT => \Asset::icon('file-text').' ',
			Lesson::EMPTY => '',
		};

		return '<span class="learning-format">'.$icon.self::p('format')->values[$format].'</span>';

	}

	public static function getSteps(int $steps): string {
		return p("{value}&nbsp;étape", "{value}&nbsp;étapes", $steps);
	}

	/**
	 * Ce que la leçon contient, puis sa durée, lus sur ses blocs (`LessonLib::fillReader()`). La vidéo
	 * passe devant les étapes, que la leçon lit à côté d'elle. Une leçon vide n'annonce rien, ni
	 * durée : il n'y a rien à y lire.
	 */
	public static function getFigures(Lesson $eLesson): array {

		if($eLesson['written'] === FALSE) {
			return [];
		}

		$figures = [];

		if($eLesson['eBlockVideo']->notEmpty()) {
			$figures[] = s("Vidéo");
		} else if($eLesson['steps'] > 0) {
			$figures[] = self::getSteps($eLesson['steps']);
		}

		if($eLesson['minutes'] !== NULL) {
			$figures[] = s("{value} de lecture", CourseUi::getDuration($eLesson['minutes']));
		}

		return $figures;

	}

	/**
	 * La bande de la leçon (`DesignUi::getPageHero()`) : son module en surtitre, la flèche de retour,
	 * vers la même adresse que le retour de la paire (`getBack()`), et sous le titre ce que la leçon
	 * contient. Où elle se tient dans la formation se lit en tête du contenu (`getPosition()`).
	 */
	public function getHero(Lesson $eLesson, Course $eCourse, \Collection $cChapter): string {

		$eChapter = $cChapter[$eLesson['chapter']['id']];

		[$back] = $this->getBack($eCourse);

		return \main\DesignUi::getPageHero(
			encode($eLesson['name']),
			label: CourseUi::getChapterName($eCourse, $eChapter),
			back: $back,
			lead: implode('&nbsp;· ', self::getFigures($eLesson))
		);

	}

	/**
	 * La pastille « Leçon n sur N » en tête du contenu (`ChapterLib::fillPosition()`), sur toute leçon,
	 * pour qu'elle reste à la même place d'une leçon du module à l'autre. Elle prend la teinte de la
	 * bande (`learning.css`).
	 */
	public function getPosition(Lesson $eLesson): string {

		$eLesson->expects(['positionNumber', 'positionTotal']);

		return '<p class="mb-1"><span class="badge learning-lesson-position">'.s("Leçon&nbsp;{number} sur&nbsp;{total}", ['number' => $eLesson['positionNumber'], 'total' => $eLesson['positionTotal']]).'</span></p>';

	}

	/**
	 * La bande transparente de la leçon sous le thème d'un réseau (`learning.css`), la colonne à
	 * gauche, que le téléphone pose en rangée au-dessus du contenu, comme celle de `/faq`.
	 */
	public function getBody(string $side, string $content): string {

		$h = '<div class="util-band learning-lesson">';
			$h .= '<div class="container util-layout-aside-start" style="--aside-width: 17rem">';
				$h .= $side;
				$h .= '<div>'.$content.'</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La colonne : la progression du stagiaire, puis les leçons du module. Elle compte et coche toutes
	 * les leçons, comme la fin de la formation (`Course::isDone()`), qui attend aussi les leçons vides :
	 * une leçon vide reste à suivre, et s'y lit « À venir » tant qu'elle ne l'est pas. Elle n'est un
	 * lien que pour qui peut la cocher (`Lesson::isLinked()`), comme sur la fiche et le sommaire des
	 * tutoriels ; la leçon ouverte reste le sien. Un quiz
	 * réussi redessine la colonne entière (`view/response.v.php`), qui coche la leçon et compte une
	 * leçon suivie de plus.
	 */
	public function getSide(Lesson $eLesson, Course $eCourse, \Collection $cChapter, \Collection $cProgress, Enrollment $eEnrollment): string {

		$eChapter = $cChapter[$eLesson['chapter']['id']];

		$h = '<nav id="learning-lesson-side" class="util-layout-aside-side">';

			if($eEnrollment->notEmpty()) {
				$h .= new ProgressUi()->getColumn($eCourse);
			}

			$h .= '<div class="util-filter-options util-filter-options-column util-filter-options-nav">';

				foreach($eChapter['cLesson'] as $eLessonChapter) {

					$selected = ($eLessonChapter['id'] === $eLesson['id']);
					$soon = (
						$eLessonChapter['written'] === FALSE and
						$selected === FALSE
					);
					$linked = (
						$selected or
						$eLessonChapter->isLinked($eCourse)
					);

					if($linked === FALSE) {
						$h .= '<span class="util-filter-option color-muted">';
					} else {
						$h .= '<a href="'.self::url($eLessonChapter, $eCourse['customizeQuery']).'" class="util-filter-option'.($selected ? ' is-selected' : '').'">';
					}

						$h .= self::getNumbered($eCourse, $eLessonChapter);
						if($cProgress->offsetExists($eLessonChapter['id'])) {
							// Sur l'aplat de la leçon courante, à la couleur du réseau, le vert du succès se fond : la
							// coche y prend l'encre de l'entrée.
							$h .= ' '.\Asset::icon('check-circle-fill', $selected ? [] : ['class' => 'color-success']);
						} else if($soon) {
							$h .= ' '.self::getSoon();
						}

					$h .= $linked ? '</a>' : '</span>';

				}

			$h .= '</div>';

		$h .= '</nav>';

		return $h;

	}

	/**
	 * La paire du bas de page, vers les voisines écrites de la leçon sur toute la formation
	 * (`LessonLib::fillWritten()`) : la précédente à gauche, la suivante à droite, seule à porter la
	 * flèche d'une page qui avance. Sans voisine d'un côté, la carte de ce côté ramène au sommaire ou
	 * à la fiche ; sans aucune voisine, il ne reste que ce retour.
	 */
	public function getPager(Lesson $eLesson, Course $eCourse): string {

		$eLessonPrevious = $eLesson['eLessonPreviousWritten'];
		$eLessonNext = $eLesson['eLessonNextWritten'];

		[$backUrl, $backName] = $this->getBack($eCourse);

		$back = $this->getPagerCard($backUrl, s("Retour"), $backName, FALSE);

		$h = '<nav class="util-cards util-cards-2 mt-3">';

			if(
				$eLessonPrevious->empty() and
				$eLessonNext->empty()
			) {

				$h .= $back;

			} else {

				$h .= $eLessonPrevious->empty()
					? $back
					: $this->getPagerCard(self::url($eLessonPrevious, $eCourse['customizeQuery']), s("Leçon précédente"), self::getNumbered($eCourse, $eLessonPrevious), FALSE);

				$h .= $eLessonNext->empty()
					? $back
					: $this->getPagerCard(self::url($eLessonNext, $eCourse['customizeQuery']), s("Leçon suivante"), self::getNumbered($eCourse, $eLessonNext), TRUE);

			}

		$h .= '</nav>';

		return $h;

	}

	protected function getPagerCard(string $url, string $kicker, string $title, bool $arrow): string {

		$h = '<a href="'.$url.'" class="util-deck util-deck-round">';
			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-kicker">'.$kicker.'</div>';
					$h .= '<div class="util-deck-title">'.$title.'</div>';
				$h .= '</div>';
				if($arrow) {
					$h .= '<div class="util-deck-arrow">'.\Asset::icon('chevron-right').'</div>';
				}
			$h .= '</div>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * Où ramène la leçon, et son nom : un tutoriel au sommaire des tutoriels, toute autre formation à
	 * sa fiche (`isTutorial`, posé par la page).
	 */
	protected function getBack(Course $eCourse): array {

		if($eCourse['isTutorial']) {
			return ['/tutoriels', s("Tous les tutoriels")];
		}

		return [CourseUi::url($eCourse, $eCourse['customizeQuery']), s("Fiche de la formation")];

	}

	/**
	 * Une leçon par son numéro et son nom, comme le menu de la fiche la nomme (`CourseUi::getMenu()`).
	 */
	protected static function getNumbered(Course $eCourse, Lesson $eLesson): string {

		return match($eCourse['type']) {
			Course::ONLINE => CourseUi::getLessonNumber($eCourse, $eLesson).' ',
			Course::REMOTE, Course::ONSITE, Course::MIXED => CourseUi::getLessonNumber($eCourse, $eLesson).'. '
		}.encode($eLesson['name']);

	}

	/**
	 * Ce que la leçon apprend, en tête de sa lecture (planche L8, bloc 2). Rien quand elle n'en dit
	 * pas : un libellé seul annoncerait un contenu qui n'existe pas.
	 */
	public function getObjective(Lesson $eLesson): string {

		if($eLesson['objective'] === NULL) {
			return '';
		}

		$h = '<div class="mb-2">';
			$h .= '<div class="util-label">'.self::p('objective')->label.'</div>';
			$h .= '<div>'.encode($eLesson['objective']).'</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le contenu de la leçon, dans l'ordre que `LessonLib::fillReader()` a tiré de ses blocs : la carte
	 * de la vidéo (`getVideoCard()`), ce qui précède les étapes, puis les étapes. Au lecteur, les
	 * étapes se lisent à gauche et la capture de l'étape choisie à droite (`LearningReader`), avec les
	 * flèches qui passent d'une étape à l'autre. Sans lecteur, les étapes restent dans le fil. Sous la
	 * largeur de deux colonnes, chaque étape garde sa capture sous son texte (`learning.css`).
	 */
	public function getContent(Lesson $eLesson): string {

		if($eLesson['written'] === FALSE) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Cette leçon n'est pas encore écrite").'</h3>';
				$h .= '<p>'.s("Elle paraîtra ici dès qu'elle le sera.").'</p>';
			$h .= '</div>';

			return $h;

		}

		// Écrite par son seul quiz, la leçon n'a rien à lire : le quiz se rend à part, sous le contenu.
		if($eLesson['cBlock']->empty()) {
			return '';
		}

		if($eLesson['videoCard'] === FALSE and $eLesson['reader'] === FALSE) {

			$h = new \block\BlockUi()->render($eLesson['cBlock']);

		} else {

			$h = '<div class="util-stack">';

				if($eLesson['videoCard']) {
					$h .= $this->getVideoCard($eLesson);
				}

				$h .= new \block\BlockUi()->render($eLesson['cBlockIntro']);

				if($eLesson['reader']) {
					$h .= $this->getReader($eLesson);
				} else if($eLesson['cBlockSteps']->notEmpty()) {
					$h .= '<div id="learning-lesson-steps">'.new \block\BlockUi()->render($eLesson['cBlockSteps']).'</div>';
				}

			$h .= '</div>';

		}

		// La comptabilité remplace dans le rendu ce que le contenu emprunte à la structure du lecteur
		// (`LessonLib::fillContent()`, appelée par la page).
		if(\structure\StructureSetting::hasAccountingApp()) {
			self::notify('filterContent', $h, $eLesson);
		}

		return $h;

	}

	/**
	 * La vidéo en tête de la leçon, à côté de son titre, qui mène aux étapes. Le texte que l'auteur a
	 * posé à côté d'elle vient avec : la carte remplace le bloc dans le fil, il n'aurait pas d'autre
	 * place. Il ne se lit qu'avec la vidéo à gauche ou à droite, comme dans le fil
	 * (`BlockTypeUi::renderVideo()`) : une autre disposition le garde sans le montrer.
	 */
	protected function getVideoCard(Lesson $eLesson): string {

		$title = $eLesson['eBlockVideo']['title'] ?? $eLesson['name'];

		$h = '<div class="util-card util-split learning-lesson-video">';

			$h .= new \block\BlockTypeUi()->getVideoFigure($eLesson['eBlockVideo']);

			$h .= '<div>';
				$h .= '<div class="util-label">'.s("Vidéo").'</div>';

				if($eLesson['cBlockSteps']->empty()) {
					$h .= '<div class="util-deck-title-heading">'.encode($title).'</div>';
				} else {
					$h .= '<a href="#learning-lesson-steps" '.attr('onclick', 'LearningVideoCard.toSteps(event)').' class="util-deck-title-heading">'.encode($title).'</a>';
				}

				if(in_array($eLesson['eBlockVideo']['variant'], [\block\BlockTypeLib::LEFT, \block\BlockTypeLib::RIGHT], TRUE)) {
					$h .= '<div class="mt-1">'.new \editor\EditorUi()->value($eLesson['eBlockVideo']['content']).'</div>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lecteur : les étapes à gauche, à droite la capture de l'étape choisie, que le script recopie
	 * (`LearningReader`), et les flèches qui passent d'une étape à l'autre.
	 */
	protected function getReader(Lesson $eLesson): string {

		$h = '<div id="learning-lesson-steps" class="learning-reader" '.attr('onrender', 'LearningReader.start(this)').'>';
			$h .= '<div class="learning-reader-text">'.new \block\BlockUi()->render($eLesson['cBlockSteps']).'</div>';
			$h .= '<div class="learning-reader-view">';
				$h .= '<div class="learning-reader-capture"></div>';
				$h .= '<div class="util-action mt-1">';
					$h .= '<button type="button" class="btn btn-outline-primary" data-reader-move="-1">'.\Asset::icon('chevron-left').' '.s("Étape précédente").'</button>';
					$h .= '<button type="button" class="btn btn-outline-primary" data-reader-move="1">'.s("Étape suivante").' '.\Asset::icon('chevron-right').'</button>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le nœud qui fait signaler la lecture de la leçon (`LearningReading`), posé seulement quand la
	 * page a tenu le journal (`ReadingLib::see()`). Il ne montre rien : les cadences viennent du Lib,
	 * pour que le serveur et le navigateur ne comptent pas deux délais différents, et le plafond d'une
	 * vidéo lancée (`videoLimit`, en secondes) de la page, qui le tire de la durée de la leçon.
	 */
	public function getReading(Lesson $eLesson): string {

		if($eLesson['reading'] === FALSE) {
			return '';
		}

		return '<div class="hide" data-lesson="'.$eLesson['id'].'" data-period="'.ReadingLib::PERIOD.'" data-idle="'.ReadingLib::IDLE.'" data-video-limit="'.$eLesson['videoLimit'].'" '.attr('onrender', 'LearningReading.start(this)').'></div>';

	}

	public function getActions(Lesson $eLesson): string {

		$items = '<a href="/learning/lesson:updateSettings?id='.$eLesson['id'].'" class="dropdown-item">'.s("Modifier la leçon").'</a>';

		if($eLesson->assertDelete()) {
			$items .= '<div class="dropdown-divider"></div>';
			$items .= '<a data-ajax="/learning/lesson:doDelete" post-id="'.$eLesson['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer cette leçon ?").'" data-waiter>'.s("Supprimer la leçon").'</a>';
		} else if($eLesson['holdsCompleted']) {
			// La dernière leçon d'une formation suivie en entier se garde (`acceptDelete()`) : le
			// geste se montre fermé, avec ce qui le ferme, comme « Supprimer la formation ».
			$items .= '<div class="dropdown-divider"></div>';
			$items .= '<a class="dropdown-item disabled">';
				$items .= '<div class="util-item-label">'.s("Supprimer la leçon").'</div>';
				$items .= '<div class="util-item-more">'.s("Un inscrit a suivi toute la formation. Dépubliez-la d'abord.").'</div>';
			$items .= '</a>';
		}

		return \main\DesignUi::getGear($items, encode($eLesson['name']));

	}

	public function create(Lesson $eLesson): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/lesson:doCreate');

			$h .= $form->hidden('chapter', $eLesson['chapter']);

			$h .= $form->group(
				s("Module"),
				'<b>'.encode($eLesson['chapter']['name']).'</b>'
			);

			// La durée se demande sous le titre, mais ne s'exige que sous une formation publiée, où la
			// leçon paraît devant les lecteurs sitôt créée : ailleurs elle peut s'estimer une fois la
			// leçon écrite. Ce que l'étape `Lesson::minutes.classified` refuse, l'écran l'exige, et rien
			// de plus (planche B du lot « publier », D1).
			$properties = match($eLesson['course']['status']) {
				Course::PUBLISHED => ['name*', 'minutes*'],
				Course::DRAFT => ['name*', 'minutes'],
			};

			$h .= $form->dynamicGroups($eLesson, $properties);

			$h .= $form->group(
				content: $form->submit(s("Ajouter la leçon"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-lesson-create',
			title: s("Ajouter une leçon"),
			body: $h
		);

	}

	/**
	 * La tête de l'éditeur, la même sous les deux gabarits : l'en-tête d'écran du socle, comme celle
	 * de la formation (`CourseUi::getEditionHead()`). Le retour ramène à la formation, que la bande
	 * d'un groupe nomme au-dessus du titre, et la ligne de contexte situe la leçon.
	 */
	public function getEditionHead(Lesson $eLesson, \Collection $cChapter): string {

		return \main\DesignUi::getPageHeader(
			encode($eLesson['name']),
			context: '<span class="util-annotation">'.$this->getEditionSubTitle($eLesson, $cChapter).'</span>',
			back: CourseUi::urlEdition($eLesson['course']),
			settings: $this->getEditionActions($eLesson),
			backLabel: encode($eLesson['course']['name'])
		);

	}

	/**
	 * La roue ouvre le panneau de la leçon, pas un menu : elle n'a donc pas le chevron de la roue de
	 * la formation, dont elle garde le fond blanc, et son title dit ce qu'elle ouvre.
	 */
	public function getEditionActions(Lesson $eLesson): string {
		return '<a href="/learning/lesson:updateSettings?id='.$eLesson['id'].'" class="btn btn-only-icon btn-color-primary" title="'.s("Modifier la leçon").'">'.\Asset::icon('gear-fill').'</a>';
	}

	/**
	 * La ligne de contexte de l'éditeur : le module et le rang de la leçon, sa forme, sa durée. Le
	 * titre est juste au-dessus et n'y est pas répété ; la formation non plus, le retour du titre y
	 * mène. Une leçon sans durée perd le dernier terme, et `array_filter()` retire son séparateur
	 * avec lui.
	 */
	protected function getEditionSubTitle(Lesson $eLesson, \Collection $cChapter): string {

		$eChapter = $cChapter[$eLesson['chapter']['id']];

		return implode('&nbsp;· ', array_filter([
			s("Module {number}&nbsp;· {name}", ['number' => $eChapter['number'], 'name' => encode($eChapter['name'])]),
			s("Leçon&nbsp;{value}", $eLesson['number']),
			self::getFormat($eLesson['format']),
			$eLesson['minutes'] === NULL ? NULL : CourseUi::getDuration($eLesson['minutes'])
		]));

	}

	/**
	 * L'écran d'une leçon, en onglets comme l'édition d'une formation (`CourseUi::getEdition()`) : ses
	 * blocs, édités sur leur rendu, avec le plan qui ne décrit qu'eux, puis ses fichiers et son quiz.
	 * La barre est commune aux trois, sous les onglets. Chaque bloc ouvert porte son propre formulaire :
	 * il n'y a pas de formulaire autour de la page, deux formulaires ne s'imbriquant pas.
	 *
	 * L'écran s'ouvre toujours sur le contenu, sauf quand un lien demande un autre onglet (`$tab`, le
	 * quiz depuis ce qui manque à une formation pour être publiée). La rangée ne porte donc pas d'`id` :
	 * c'est sous lui que `Lime.Tab` retient le dernier onglet ouvert d'une visite à l'autre.
	 *
	 * Le panneau de la roue ramène sur l'onglet d'où il est ouvert : son lien porte cet onglet
	 * (`origin`), que le formulaire reposte. Le délégué de navigation lit un lien en capture, avant
	 * tout `onclick` posé sur lui : l'origine s'y écrit donc quand l'onglet change, et non quand la
	 * roue est cliquée (`LearningLesson.updateOrigin()`), à la suite de `Lime.Tab.restore()` comme de
	 * `Lime.Tab.select()`.
	 *
	 * `$mode` ne concerne que le contenu, édité ou visualisé : les fichiers et le quiz sont les mêmes
	 * dans les deux modes.
	 */
	public function getEdition(Lesson $eLesson, string $tab = '', string $mode = self::MODE_EDITION): string {

		$edition = $this->getEditionTabs($eLesson, $tab, $mode);

		$h = '<div class="tabs tabs-h tabs-h-fold" '.attrs($edition['attributes']).'>';
			$h .= '<div class="tabs-item">'.$edition['items'].'</div>';
			$h .= $edition['panels'];
		$h .= '</div>';

		$h .= $this->getEditionBar($eLesson);

		return $h;

	}

	/**
	 * Les onglets de l'écran en trois morceaux, comme ceux de la formation (`CourseUi::getEditionTabs()`) :
	 * les attributs de leur `.tabs`, les onglets et les panneaux. Sous un groupe, les onglets sont dans
	 * la bande de l'écran et le gabarit pose le `.tabs` autour de la bande et du corps
	 * (`GroupTemplate::$mainTabsAttributes`) ; `getEdition()` les assemble pour l'équipe.
	 */
	public function getEditionTabs(Lesson $eLesson, string $tab = '', string $mode = self::MODE_EDITION): array {

		$panels = [
			'content' => $this->getEditionContent($eLesson, $mode),
			'attachments' => new AttachmentUi()->getEditor($eLesson),
			'quiz' => new QuestionUi()->getEditor($eLesson),
		];

		$default = 'content';

		$items = '';

		foreach(self::EDITION_TABS as $name) {
			$items .= $this->getEditionTabItem($eLesson, $name, $name === $default);
		}

		$h = '';

		foreach($panels as $name => $content) {
			$h .= '<div class="tab-panel'.($name === $default ? ' selected' : '').'" data-tab="'.$name.'">';
				$h .= $content;
			$h .= '</div>';
		}

		return [
			'attributes' => [
				'onrender' => 'Lime.Tab.restore(this, "'.$default.'", "'.$tab.'"); LearningLesson.updateOrigin()'
			],
			'items' => $items,
			'panels' => $h
		];

	}

	/**
	 * Le panneau du contenu : l'interrupteur entre l'édition et la visualisation, en tête et à droite,
	 * puis les blocs dans le mode demandé (retours du 01/10/2026). En édition, l'éditeur des
	 * blocs et son plan. En visualisation, les blocs tels que la page de lecture les rend
	 * (`getContent()`, sur `BlockLib::getReadable()`), sans plan, sans « + » et sans bloc vide.
	 */
	protected function getEditionContent(Lesson $eLesson, string $mode): string {

		$h = '<div class="text-end mb-1">'.$this->getEditionMode($eLesson, $mode).'</div>';

		if($mode === self::MODE_PREVIEW) {

			$eLesson->expects(['cBlock']);

			$h .= $this->getContent($eLesson);

		} else {

			$eLesson->expects(['cBlock', 'blockChoices', 'cBlockNew']);

			$h .= new \block\BlockUi()->getEditor($eLesson['cBlock'], $eLesson['blockChoices'], $eLesson['cBlockNew'], BlockOwnerLib::LESSON, $eLesson['id']);

		}

		return $h;

	}

	/**
	 * L'interrupteur du contenu. C'est un lien vers le même écran dans l'autre mode : l'éditeur des
	 * blocs le retient le temps d'enregistrer le bloc ouvert (`block.js`), comme les issues de la barre.
	 */
	public function getEditionMode(Lesson $eLesson, string $mode): string {

		$modeTarget = match($mode) {
			self::MODE_EDITION => self::MODE_PREVIEW,
			self::MODE_PREVIEW => self::MODE_EDITION,
		};

		return \util\TextUi::switch(['href' => self::urlEditionMode($eLesson, $modeTarget)], on: $mode === self::MODE_EDITION, textOn: s("Édition"), textOff: s("Visualisation"));

	}

	/**
	 * Le nom d'un onglet, court pour tenir dans la rangée et dans son menu replié.
	 */
	public static function getEditionTabName(string $tab): string {

		return match($tab) {
			'content' => s("Contenu de la leçon"),
			'attachments' => s("Fichiers à télécharger"),
			'quiz' => s("Quiz de fin"),
		};

	}

	/**
	 * Le titre d'un panneau : le nom de son onglet, sauf pour les fichiers, dont le titre dit en plus
	 * à qui ils sont destinés (retour du 01/10/2026), ce que l'onglet n'a pas la place de dire.
	 */
	public static function getEditionPanelTitle(string $tab): string {

		return match($tab) {
			'attachments' => s("Fichiers à télécharger par les stagiaires"),
			'content', 'quiz' => self::getEditionTabName($tab),
		};

	}

	protected function getEditionTabItem(Lesson $eLesson, string $tab, bool $selected): string {

		return '<a class="tab-item'.($selected ? ' selected' : '').'" id="learning-lesson-tab-'.$tab.'" data-tab="'.$tab.'" onclick="Lime.Tab.select(this); LearningLesson.updateOrigin()">'.$this->getEditionTab($eLesson, $tab).'</a>';

	}

	/**
	 * La tête d'un onglet : son nom, suivi du nombre de fichiers ou de questions dès qu'il y en a un.
	 * Un geste sur les fichiers ou le quiz la rend de nouveau (`view/attachment.v.php`,
	 * `view/question.v.php`), sans toucher à l'onglet qui la porte, dont seul le navigateur sait s'il
	 * est encore choisi quand la réponse arrive. Le repli de la rangée se refait ensuite
	 * (`LearningLesson.refreshTabs()`), pour que son menu reprenne le nombre à jour.
	 */
	public function getEditionTab(Lesson $eLesson, string $tab): string {

		$count = match($tab) {
			'content' => 0,
			'attachments' => $eLesson['cAttachment']->count(),
			'quiz' => $eLesson['cQuestion']->count(),
		};

		return self::getEditionTabName($tab).($count > 0 ? ' <span class="util-counter">'.$count.'</span>' : '');

	}

	/**
	 * Les deux déplacements de l'écran : la leçon d'avant et celle d'après. Le retour à la formation est
	 * en tête, par le retour du titre (`getEditionHead()`).
	 * Ce sont des liens ordinaires : l'éditeur des blocs retient tout lien qui quitte l'écran le temps
	 * d'enregistrer le bloc ouvert (`block.js`), ceux-ci comme le menu du haut. Chacun passe par
	 * `lesson:leave`, qui cherche la voisine au moment du clic. Aux deux bouts du programme le bouton
	 * de déplacement est absent, jamais grisé : il n'y a rien à espérer d'un clic dessus. Une leçon
	 * seule dans sa formation n'a aucune voisine, donc pas de barre.
	 */
	public function getEditionBar(Lesson $eLesson): string {

		if(
			$eLesson['eLessonPrevious']->empty() and
			$eLesson['eLessonNext']->empty()
		) {
			return '';
		}

		$h = '<div class="learning-lesson-bar">';

			$h .= '<div class="learning-lesson-bar-move">';
				if($eLesson['eLessonPrevious']->notEmpty()) {
					$h .= '<a href="'.self::urlLeave($eLesson, 'previous').'" class="btn btn-outline-primary">'.\Asset::icon('chevron-left').' '.s("Leçon précédente").'</a>';
				}
			$h .= '</div>';

			$h .= '<div class="learning-lesson-bar-move">';
				if($eLesson['eLessonNext']->notEmpty()) {
					$h .= '<a href="'.self::urlLeave($eLesson, 'next').'" class="btn btn-outline-primary">'.s("Leçon suivante").' '.\Asset::icon('chevron-right').'</a>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function updateSettings(Lesson $eLesson): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/lesson:doUpdateSettings', ['onrender' => 'LearningQuiz.initSettings(this)']);

			$h .= $form->hidden('id', $eLesson);
			$h .= $form->hidden('origin', GET('origin'));

			// Même règle qu'à la création : sous une formation publiée, `Lesson::minutes.classified`
			// refuse une durée vide, et l'écran le dit par l'astérisque plutôt que par un refus.
			$minutes = match($eLesson['course']['status']) {
				Course::PUBLISHED => 'minutes*',
				Course::DRAFT => 'minutes',
			};

			$h .= $form->dynamicGroups($eLesson, ['name*', 'objective', $minutes, 'chapter*', 'validation*', 'quizThreshold*']);

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-lesson-settings',
			title: s("Modifier la leçon"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Lesson::model()->describer($property, [
			'name' => s("Titre"),
			'objective' => s("Objectif"),
			'minutes' => s("Durée estimée"),
			'format' => s("Forme"),
			'chapter' => s("Module"),
			'validation' => s("Validation"),
			'quizThreshold' => s("Seuil de réussite"),
		]);

		switch($property) {

			case 'objective' :
				$d->placeholder = s("Ex. : savoir constituer son dossier de notification");
				break;

			case 'minutes' :
				$d->append = s("min");
				break;

			case 'format' :
				$d->values = [
					Lesson::VIDEO => s("Vidéo"),
					Lesson::STEPS => s("Étapes"),
					Lesson::TEXT => s("Texte"),
					Lesson::EMPTY => s("Vide"),
				];
				break;

			case 'chapter' :
				$d->field = 'select';
				$d->values = function(Lesson $e) {

					$e->expects(['cChapter']);

					$values = [];

					foreach($e['cChapter'] as $eChapter) {
						$values[$eChapter['id']] = s("Module {number} · {name}", ['number' => $eChapter['number'], 'name' => $eChapter['name']]);
					}

					return $values;

				};
				$d->required = TRUE;
				break;

			/**
			 * Le quiz reste proposé quand une règle le ferme, avec sa raison : sous une formation
			 * publiée, une leçon ne passe au quiz qu'avec une question (`Lesson::validation.questioned`).
			 * Une leçon qui se déclare suivie et garde des questions le dit sous l'option qui les
			 * poserait, et `LearningQuiz` cache cette phrase dès que l'option est cochée : elle ne
			 * vaut que pour la déclaration. `questions` est posé par la route `lesson:updateSettings`.
			 */
			case 'validation' :
				$d->field = 'radio';
				$d->values = function(Lesson $e) {

					$e->expects(['questions', 'course' => ['status']]);

					$quiz = s("Il réussit le quiz de la leçon");
					$attributes = [];

					if(
						$e['questions'] === 0 and
						$e['course']['status'] === Course::PUBLISHED
					) {
						$quiz = '<div class="field-radio-group-label">'.$quiz.'</div><div class="field-radio-group-reason">'.s("Ajoutez d'abord une question dans la section du quiz").'</div>';
						$attributes['disabled'] = 'disabled';
					} else if(
						$e['questions'] > 0 and
						$e['validation'] === Lesson::DECLARED
					) {
						$quiz = '<div class="field-radio-group-label">'.$quiz.'</div><div class="field-radio-group-reason" id="lesson-quiz-kept">'.p("{value} question écrite, gardée tant qu'elle n'est pas posée", "{value} questions écrites, gardées tant qu'elles ne sont pas posées", $e['questions']).'</div>';
					}

					return [
						['value' => Lesson::DECLARED, 'label' => s("Le stagiaire la déclare suivie")],
						['value' => Lesson::QUIZ, 'label' => $quiz, 'attributes' => $attributes],
					];

				};
				$d->required = TRUE;
				break;

			/**
			 * Le seuil ne se montre qu'avec le quiz, mais se poste toujours : `LearningQuiz` le cache
			 * avec la déclaration sans le désactiver, parce qu'un champ désactivé ne part pas et que
			 * `build()` effacerait alors le seuil de la leçon. Le champ porte le seuil enregistré, vide
			 * s'il n'a jamais été réglé, pour qu'une leçon déclarée le garde tel quel ; la valeur par
			 * défaut n'est proposée qu'au passage en quiz, par le script. Le nombre de bonnes réponses se recompte
			 * à la frappe, dans l'information sous le champ : chaque phrase possible est écrite ici,
			 * une par nombre de bonnes réponses, et le script choisit la sienne.
			 */
			case 'quizThreshold' :
				$d->append = s("%");
				$d->group = fn(Lesson $e) => ($e['validation'] === Lesson::QUIZ) ? [] : ['class' => 'hide'];
				$d->attributes = function(\util\FormUi $form, Lesson $e) {

					$texts = [];

					for($needed = 0; $needed <= $e['questions']; $needed++) {
						$texts[] = p("Soit {value} bonne réponse sur {total}.", "Soit {value} bonnes réponses sur {total}.", $needed, ['total' => $e['questions']]);
					}

					return [
						'data-default' => Lesson::QUIZ_THRESHOLD_DEFAULT,
						'data-questions' => $e['questions'],
						'data-texts' => json_encode($texts),
						'oninput' => 'LearningQuiz.updateNeeded(this)',
					];

				};
				$d->after = fn(\util\FormUi $form, Lesson $e) => ($e['questions'] > 0) ? \util\FormUi::info('', id: 'lesson-quiz-needed') : '';
				break;

		}

		return $d;

	}

}
?>
