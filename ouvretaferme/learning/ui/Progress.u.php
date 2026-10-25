<?php
namespace learning;

/**
 * Où en est le lecteur : la bande de progression d'une carte ou d'une fiche, et les boutons qui
 * cochent une leçon.
 */
class ProgressUi {

	public function __construct() {
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	/**
	 * `$due`, l'échéance de la promotion, suit la progression : les deux disent au lecteur où il en
	 * est, et se lisent ensemble. Une formation suivie en entier n'a plus d'échéance à tenir.
	 *
	 * `$journal` ajoute au bout de la bande le lien « Mon suivi », vers le journal de lecture du
	 * stagiaire (L8c) : la fiche seule le passe, la carte d'une formation n'en porte pas.
	 *
	 * `$duePast` donne à la bande le fond d'un état quand l'échéance est passée : la carte le passe,
	 * la fiche écrit l'échéance passée sans fond (planche carte-hierarchie du 02/10, bandes C). Avec
	 * une échéance, la barre prend sa propre ligne (`learning-progress-due`).
	 */
	public function getBand(Course $eCourse, ?string $due = NULL, bool $journal = FALSE, bool $duePast = FALSE): string {

		$done = $eCourse['done'];
		$lessons = $eCourse['lessons'];

		$link = fn(string $class = '') => $journal ? '<a href="'.ReadingUi::urlMine($eCourse).'"'.($class === '' ? '' : ' class="'.$class.'"').'>'.\Asset::icon('clock-history').' '.s("Mon suivi").'</a>' : '';

		// Sans barre de progression entre eux, le texte et le lien ne sont séparés que par l'écart de la bande.
		if($eCourse->isDone()) {
			return '<div class="util-deck-band util-deck-band-state"><span>'.\Asset::icon('check-lg').' '.s("Suivie en entier").'</span>'.$link('ml-1').'</div>';
		}

		// Une formation publiée sans leçon n'a rien à commencer : seule l'échéance reste à lire.
		if($lessons === 0) {
			return ($due === NULL) ? '' : '<div class="util-deck-band'.($duePast ? ' util-deck-band-state' : '').'">'.$due.'</div>';
		}

		$progress = ($done === 0)
			? s("Pas commencée")
			: p("{value} leçon suivie sur {total}", "{value} leçons suivies sur {total}", $done, ['total' => $lessons]);

		$h = '<div class="util-deck-band'.($duePast ? ' util-deck-band-state' : '').($due === NULL ? '' : ' learning-progress-due').'">';

			$h .= '<span>'.$progress.($due === NULL ? '' : ' · '.$due).'</span>';

			$h .= '<div class="util-progress learning-progress">';
				if($done > 0) {
					$h .= '<div class="util-progress-part bg-success" style="width: '.round($done / $lessons * 100, 1).'%"></div>';
				}
			$h .= '</div>';

			$h .= $link();

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'état d'une leçon suivie, à la place du geste qui l'a cochée (`getFollow()`, `QuizUi::get()`) :
	 * c'est une information, pas un bouton, il se lit donc en marque. Le retrait est rare : il passe
	 * derrière la marque, qui l'ouvre. C'est la forme que le design
	 * system donne à un état qu'on peut changer, `btn btn-badge` et le chevron de `dropdown-toggle`.
	 *
	 * Une leçon à quiz ne se retire pas (`Lesson::acceptUndo()`) : son état se lit seulement, et se
	 * dit « Réussie » quand c'est le quiz qui l'a validée, le mot du suivi de l'équipe. Elle peut
	 * aussi avoir été déclarée suivie avant de passer au quiz, et le reste.
	 */
	public function getState(Lesson $eLesson, Course $eCourse, \Collection $cProgress): string {

		$eProgress = $cProgress[$eLesson['id']];
		$date = \util\DateUi::numeric($eProgress['doneAt'], \util\DateUi::DAY_MONTH);

		$label = \Asset::icon('check-lg').' '.match($eProgress['source']) {
			Progress::DECLARED => s("Suivie le {value}", $date),
			Progress::QUIZ => s("Réussie le {value}", $date),
		};

		// Passé l'échéance de sa promotion, la progression est figée : l'état se lit, il ne se retire plus.
		if(
			$eCourse['canProgress'] === FALSE or
			$eLesson->acceptUndo() === FALSE
		) {
			return '<span class="btn btn-badge btn-xs btn-readonly badge badge-success">'.$label.'</span>';
		}

		$h = '<a class="btn btn-badge btn-xs badge badge-success dropdown-toggle" data-dropdown="bottom-start">'.$label.'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<a data-ajax="/learning/lesson:doUndo" post-id="'.$eLesson['id'].'" class="dropdown-item">'.s("Retirer").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La progression du stagiaire en tête de la colonne de la leçon (`LessonUi::getSide()`), le texte
	 * au-dessus de sa barre : la colonne est étroite. Elle compte comme la bande de la fiche
	 * (`getBand()`), toutes les leçons de la formation.
	 */
	public function getColumn(Course $eCourse): string {

		$done = $eCourse['done'];
		$lessons = $eCourse['lessons'];

		$progress = ($done === 0)
			? s("Pas commencée")
			: p("{value} leçon suivie sur {total}", "{value} leçons suivies sur {total}", $done, ['total' => $lessons]);

		$h = '<div class="mb-2">';
			$h .= '<div class="mb-1">'.$progress.'</div>';
			$h .= '<div class="util-progress">';
				if($done > 0) {
					$h .= '<div class="util-progress-part bg-success" style="width: '.round($done / $lessons * 100, 1).'%"></div>';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Sous la leçon, ce qui reste au lecteur sur elle : la cocher tant qu'elle ne l'est pas, puis son
	 * état (`getState()`), qui se lit et se retire là où il a été posé. La suite est la paire du bas
	 * de page (`LessonUi::getPager()`). Un compte qui n'a pas commencé la formation la commence
	 * d'abord. Un visiteur n'a rien à faire ici ; le stagiaire dont l'échéance est passée y lit la
	 * date où sa formation s'est figée. Les boutons gardent la taille standard : en grand, ils
	 * passeraient devant le contenu, qui est le sujet de la page.
	 *
	 * Sous un quiz, le bouton plein envoie les réponses : il est dans le formulaire du quiz
	 * (`QuizUi::get()`), qui ne rend ce bloc qu'avant une tentative. « Commencer la formation »
	 * emporte la leçon lue, où l'inscription ramène le lecteur (`ChapterLib::getLanding()`), sur
	 * son quiz quand elle en a un (`QuizUi::url()`).
	 *
	 * Le lecteur d'une formation qu'il lit sans inscription (Q-C1, le catalogue public) et dont la
	 * demande attend le réseau trouve la marque de la fiche à la place de « Commencer la formation » :
	 * le bouton ne ferait rien, la ligne existant déjà. La demande et le réglage du réseau sont posés
	 * sur la formation par la page de la leçon (`eEnrollmentRequest`, `onlineConfirm`).
	 */
	public function getFollow(Lesson $eLesson, Enrollment $eEnrollment, \Collection $cProgress, \Collection $cStructure, bool $isLogged): string {

		// Suivie, la leçon garde son état passé l'échéance, en lecture seule (`getState()`).
		if($cProgress->offsetExists($eLesson['id'])) {
			return '<div class="flex-buttons learning-follow mt-2">'.$this->getState($eLesson, $eLesson['course'], $cProgress).'</div>'.self::getDuePast($eLesson['course']);
		}

		if(self::hasFollowAction($eLesson, $eEnrollment, $isLogged) === FALSE) {
			return self::getDuePast($eLesson['course']);
		}

		$h = '<div class="flex-buttons learning-follow mt-2">';

			if($eEnrollment->empty()) {

				$h .= $eLesson['course']['eEnrollmentRequest']->notEmpty()
					? new EnrollmentUi()->getRequest($eLesson['course']['eEnrollmentRequest'])
					: new EnrollmentUi()->getStart($eLesson['course'], $cStructure, small: TRUE, eLessonOrigin: $eLesson);

			} else {

				$h .= match($eLesson['validation']) {
					Lesson::DECLARED => '<a data-ajax="/learning/lesson:doDone" post-id="'.$eLesson['id'].'" class="btn btn-primary" data-waiter>'.\Asset::icon('check-lg').' '.s("J'ai suivi cette leçon").'</a>',
					Lesson::QUIZ => new \util\FormUi()->submit(s("Valider mes réponses"), ['data-waiter' => s("Correction en cours...")]),
				};

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Passé l'échéance de sa promotion, la progression est figée (`Course::canProgress()`) : le
	 * stagiaire lit la date à la place du geste qu'il n'a plus.
	 */
	public static function getDuePast(Course $eCourse): string {

		if($eCourse['canProgress']) {
			return '';
		}

		return '<p class="util-annotation mt-2">'.ShareUi::getDuePast($eCourse['eShare']).'</p>';

	}

	/**
	 * Le lecteur a-t-il un geste sous la leçon, cocher, envoyer ses réponses ou commencer la
	 * formation ? Non pour un visiteur, pour un stagiaire dont l'échéance est passée, ni pour qui
	 * ne peut plus s'inscrire (échéance de sa promotion, formation datée sans inscription
	 * confirmée) : il ne lui reste que la suite. `QuizUi::get()` en tire, avec l'inscription, la
	 * forme du quiz, pour ne jamais rendre des cases qu'aucun bouton n'envoie.
	 */
	public static function hasFollowAction(Lesson $eLesson, Enrollment $eEnrollment, bool $isLogged): bool {

		return (
			$isLogged and
			$eLesson['course']['canProgress'] and
			(
				$eEnrollment->notEmpty() or
				$eLesson['course']['acceptEnrollment']
			)
		);

	}

	public static function urlTrack(Course $eCourse): string {
		return CourseUi::url($eCourse).'/suivi';
	}

	public static function getTrackTitle(Course $eCourse): string {

		return \main\DesignUi::getPageHeader(
			s("Suivi · {value}", encode($eCourse['name'])),
			back: CourseUi::urlList($eCourse),
			backLabel: s("Formations")
		);

	}

	/**
	 * La vue d'une leçon dans le suivi, sous la promotion regardée s'il y en a une : la promotion
	 * reste le dernier segment, que le sélecteur du groupe sait retirer.
	 */
	public static function urlTrackLesson(Course $eCourse, Lesson $eLesson, \structure\Section $eSection): string {
		return self::urlTrack($eCourse).'/lecon/'.$eLesson['id'].($eSection->empty() ? '' : '/'.$eSection['id']);
	}

	/**
	 * Le suivi d'une formation de réseau, sous la ferme regardée s'il y en a une : la ferme est un
	 * segment nommé, `/ferme/{id}`, qu'une promotion n'a pas, les deux portées ne se lisant pas au
	 * même endroit.
	 */
	public static function urlTrackStructure(Course $eCourse, \structure\Structure $eStructure): string {
		return self::urlTrack($eCourse).($eStructure->empty() ? '' : '/ferme/'.$eStructure['id']);
	}

	public static function urlTrackLessonStructure(Course $eCourse, Lesson $eLesson, \structure\Structure $eStructure): string {
		return self::urlTrack($eCourse).'/lecon/'.$eLesson['id'].($eStructure->empty() ? '' : '/ferme/'.$eStructure['id']);
	}

	/**
	 * La barre de portée du suivi d'une formation de réseau : la ferme regardée en surtitre, ou
	 * toutes, et la liste des fermes au nom desquelles des comptes sont inscrits (`$cStructure`,
	 * `EnrollmentLib::getStructuresByCourse()`), chacune avec ses inscrits. Sous deux fermes, rien :
	 * un choix dont toutes les options mènent au même écran n'en est pas un, la même règle que la
	 * barre des promotions. Le rendu est celui du groupe, `getScopeLine()`, et les adresses se
	 * posent depuis la formation et la leçon regardée, jamais depuis l'adresse courante.
	 */
	public function getStructureLine(\structure\Structure $eStructureCommunity, Course $eCourse, Lesson $eLesson, \Collection $cStructure, \structure\Structure $eStructure): string {

		if($cStructure->count() < 2) {
			return '';
		}

		$url = fn(\structure\Structure $eStructureItem) => $eLesson->empty()
			? self::urlTrackStructure($eCourse, $eStructureItem)
			: self::urlTrackLessonStructure($eCourse, $eLesson, $eStructureItem);

		$all = s("Toutes les fermes");

		$entries = [[
			'url' => $url(new \structure\Structure()),
			'label' => $all,
			'count' => NULL,
			'selected' => $eStructure->empty(),
			'closed' => FALSE
		]];

		foreach($cStructure as $eEnrollment) {
			$entries[] = [
				'url' => $url($eEnrollment['structure']),
				'label' => encode($eEnrollment['structure']['name']),
				'count' => $eEnrollment['learners'],
				'selected' => ($eStructure->notEmpty() and $eStructure['id'] === $eEnrollment['structure']['id']),
				'closed' => FALSE
			];
		}

		$overtitle = $eStructure->empty()
			? $all
			: encode($eStructure['name']);

		$class = \structure\StructureUi::getClass($eStructureCommunity);

		return new $class()->getScopeLine($eStructureCommunity, $overtitle, $entries);

	}

	/**
	 * Le suivi des stagiaires d'une formation (D2). Deux écrans sous le même titre : la grille, une
	 * ligne par stagiaire et une colonne par leçon, qui se lit sans rien cocher ; et la vue d'une
	 * leçon (`$eLesson`), où l'équipe constate (F23). Le menu « Constater une leçon » passe de l'un à
	 * l'autre. Les promotions (`$cSection`) sont celles que l'encadrant peut regarder parmi celles
	 * auxquelles la formation est ouverte ; `$eSection` celle qu'il a choisie, ou aucune. Pour une
	 * formation de réseau, qui n'a pas de promotion, la portée est une ferme (`$eStructureTrack`,
	 * ou aucune) et les stagiaires sont les comptes inscrits. Les stagiaires et le programme sont
	 * passés par `ProgressLib::fillTrack()`.
	 *
	 * L'export (F28) n'est proposé qu'à qui administre le groupe, la même règle que la route.
	 */
	public function getTrack(\structure\Structure $eStructure, Course $eCourse, \Collection $cChapter, \Collection $cSection, \structure\Section $eSection, \Collection $cUser, Lesson $eLesson = new Lesson(), \structure\Structure $eStructureTrack = new \structure\Structure()): string {

		$eCourse->expects(['lessons', 'shares']);

		$isNetwork = $eCourse['community']->isNetwork();

		// Un réseau n'a pas de promotion : ses stagiaires sont ses inscrits, qu'il ait ouvert la
		// formation à ses membres ou non, et l'état vide commun le dit plus bas.
		if(
			$isNetwork === FALSE and
			$cSection->empty()
		) {

			$h = '<div class="util-block util-block-empty">';

				if($eCourse['shares'] > 0) {
					$h .= '<h3>'.s("Cette formation n'est ouverte à aucune de vos promotions").'</h3>';
					$h .= '<p>'.s("Elle est partagée à des promotions dont vous n'encadrez pas les stagiaires.").'</p>';
				} else {
					$h .= '<h3>'.s("Cette formation n'est ouverte à aucune promotion").'</h3>';
					$h .= '<p>'.s("Ses stagiaires paraîtront ici dès qu'elle sera partagée à une promotion.").'</p>';
					if($eCourse->acceptShare()) {
						$h .= '<a href="/learning/course:share?id='.$eCourse['id'].'" class="btn btn-primary">'.\Asset::icon('people-fill').' '.s("Ouvrir à une promotion").'</a>';
					}
				}

			$h .= '</div>';

			return $h;

		}

		if($eCourse['lessons'] === 0) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Cette formation n'a pas encore de leçon").'</h3>';
				$h .= '<p>'.s("Le suivi montrera une colonne par leçon dès la première écrite.").'</p>';
				$h .= '<a href="'.CourseUi::urlEdition($eCourse).'" class="btn btn-primary">'.s("Écrire la formation").'</a>';
			$h .= '</div>';

			return $h;

		}

		$h = '<div class="util-title">';
			$h .= '<h3>'.p("{value} stagiaire", "{value} stagiaires", $cUser->count()).'</h3>';
			if($cUser->notEmpty()) {
				$h .= '<div class="learning-track-actions">';
					$h .= $this->getTrackMenu($eCourse, $cChapter, $eSection, $eLesson, $eStructureTrack);
					if($eStructure->canManage()) {
						$h .= $this->getExportMenu($eCourse, $eSection, $eStructureTrack, $isNetwork);
					}
				$h .= '</div>';
			}
		$h .= '</div>';

		if($cUser->empty()) {

			$h .= '<div class="util-block util-block-empty">';
				if($isNetwork) {
					$h .= '<h3>'.s("Aucun stagiaire inscrit").'</h3>';
					$h .= '<p>'.s("Les comptes qui s'inscrivent y paraissent avec ce qu'ils ont suivi.").'</p>';
				} else {
					$h .= '<h3>'.($eSection->empty() ? s("Aucun stagiaire dans ces promotions") : s("Aucun stagiaire dans cette promotion")).'</h3>';
					$h .= '<p>'.s("Les membres du groupe rangés dans une promotion y paraissent avec ce qu'ils ont suivi.").'</p>';
				}
			$h .= '</div>';

			return $h;

		}

		if($eLesson->notEmpty()) {
			return $h.$this->getTrackLesson($eCourse, $eLesson, $cUser);
		}

		$h .= '<div class="util-overflow">';
			$h .= '<div class="util-table util-table-even util-table-sticky-column learning-table-suivi" style="--learning-lessons: '.$eCourse['lessons'].'">';

				$h .= '<div class="util-table-head">';
					$h .= '<div>';
						$h .= '<div class="learning-track-person">';
							$h .= '<span>'.s("Stagiaire").'</span>';
							$h .= '<span>'.s("Leçons").'</span>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= '<div>'.s("Dernière<br/>activité").'</div>';
					foreach($cChapter as $eChapter) {
						foreach($eChapter['cLesson'] as $eLessonChapter) {
							$lesson = encode($eLessonChapter['number'].'. '.$eLessonChapter['name']);
							$h .= '<div class="text-center" title="'.$lesson.'"><span class="util-text-clamp">'.$lesson.'</span></div>';
						}
					}
				$h .= '</div>';

				foreach($cUser as $eUser) {
					$h .= $this->getTrackRow($eCourse, $cChapter, $eUser);
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le menu qui passe de la grille à la vue d'une leçon, et d'une leçon à l'autre, sous la même
	 * portée, promotion ou ferme. Son libellé nomme la leçon regardée.
	 */
	private function getTrackMenu(Course $eCourse, \Collection $cChapter, \structure\Section $eSection, Lesson $eLesson, \structure\Structure $eStructureTrack): string {

		$isNetwork = $eCourse['community']->isNetwork();

		$urlAll = $isNetwork
			? self::urlTrackStructure($eCourse, $eStructureTrack)
			: self::urlTrack($eCourse).($eSection->empty() ? '' : '/'.$eSection['id']);

		$urlLesson = fn(Lesson $eLessonChapter) => $isNetwork
			? self::urlTrackLessonStructure($eCourse, $eLessonChapter, $eStructureTrack)
			: self::urlTrackLesson($eCourse, $eLessonChapter, $eSection);

		// Le nom d'une leçon peut passer la largeur d'un téléphone : le libellé se coupe en ellipse
		// (learning.css), le nom entier reste dans le title.
		$label = $eLesson->empty() ? s("Constater une leçon") : encode($eLesson['number'].'. '.$eLesson['name']);

		$h = '<a class="btn btn-outline-primary dropdown-toggle learning-track-lesson" data-dropdown="bottom-end"'.($eLesson->empty() ? '' : ' title="'.$label.'"').'><span>'.$label.'</span></a>';

		$h .= '<div class="dropdown-list">';
			$h .= '<a href="'.$urlAll.'" class="dropdown-item'.($eLesson->empty() ? ' selected' : '').'">'.s("Toutes les leçons").'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			foreach($cChapter as $eChapter) {
				foreach($eChapter['cLesson'] as $eLessonChapter) {
					$selected = ($eLesson->notEmpty() and $eLesson['id'] === $eLessonChapter['id']);
					$h .= '<a href="'.$urlLesson($eLessonChapter).'" class="dropdown-item'.($selected ? ' selected' : '').'">'.encode($eLessonChapter['number'].'. '.$eLessonChapter['name']).'</a>';
				}
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * La ligne d'un stagiaire dans la grille, passée par `ProgressLib::fillTrack()`. Elle se lit :
	 * le constat se pose dans la vue d'une leçon, et se dit ici en toutes lettres sous l'état et le
	 * score, lisible sans survol, même au doigt.
	 */
	public function getTrackRow(Course $eCourse, \Collection $cChapter, \user\User $eUser): string {

		$eUser->expects(['lastSeenAt']);

		$h = '<div class="util-table-row" id="learning-track-'.$eUser['id'].'">';
			$h .= '<div>';
				$h .= '<div class="learning-track-person">';
					$h .= $this->getTrackPerson($eCourse, $eUser);
					$h .= '<span>'.$eUser['followed'].'/'.$eCourse['lessons'].'</span>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<div>'.($eUser['lastSeenAt'] === NULL ? '' : \util\DateUi::numeric($eUser['lastSeenAt'], \util\DateUi::DAY_MONTH)).'</div>';

			foreach($cChapter as $eChapter) {

				foreach($eChapter['cLesson'] as $eLesson) {

					$h .= '<div>'.$this->getTrackCell($eUser['track'][$eLesson['id']]).'</div>';

				}

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une cellule du suivi : l'état en badge, le score d'un quiz tenté, et le constat de l'équipe.
	 * La liste des inscrits d'une date la reprend telle quelle pour son quiz.
	 */
	public function getTrackCell(array $track): string {

		$h = '<div class="learning-track">';
			$h .= $this->getTrackState($track);
			$h .= match($track['state']) {
				ProgressLib::TRACK_NONE, ProgressLib::TRACK_DECLARED => '',
				ProgressLib::TRACK_PASSED => '<span class="util-annotation">'.$this->getTrackScore($track).'</span>',
				ProgressLib::TRACK_FAILED => '<span class="util-annotation">'.$this->getTrackScore($track).', '.p("{value} tentative", "{value} tentatives", $track['attempts']).'</span>',
			};
			if($track['checked']) {
				$h .= '<span class="util-annotation">'.s("Constatée").'</span>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * La vue d'une leçon : une ligne par stagiaire, son état, et la case où l'équipe constate. Le
	 * score et les tentatives ne s'ajoutent qu'à une leçon qui a un quiz, avec leur propre gabarit.
	 */
	private function getTrackLesson(Course $eCourse, Lesson $eLesson, \Collection $cUser): string {

		$eLesson->expects(['questions']);

		$quiz = ($eLesson['questions'] > 0);

		$h = '<div class="util-table util-table-even learning-table-suivi-lesson-view'.($quiz ? ' learning-table-suivi-lesson-view-quiz' : '').'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Stagiaire").'</div>';
				$h .= '<div>'.s("État").'</div>';
				if($quiz) {
					$h .= '<div class="text-end">'.s("Score").'</div>';
					$h .= '<div class="text-end">'.s("Tentatives").'</div>';
				}
				$h .= '<div class="text-center">'.s("Constatée").'</div>';
			$h .= '</div>';

			foreach($cUser as $eUser) {
				$h .= $this->getTrackLessonRow($eCourse, $eLesson, $eUser);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La ligne d'un stagiaire dans la vue d'une leçon, que la case redessine seule : l'état du
	 * stagiaire a pu changer depuis le chargement de l'écran.
	 */
	public function getTrackLessonRow(Course $eCourse, Lesson $eLesson, \user\User $eUser): string {

		$eLesson->expects(['questions']);

		$track = $eUser['track'][$eLesson['id']];
		$attempted = in_array($track['state'], [ProgressLib::TRACK_PASSED, ProgressLib::TRACK_FAILED], TRUE);

		$h = '<div class="util-table-row" id="learning-track-'.$eUser['id'].'">';
			$h .= '<div>'.$this->getTrackPerson($eCourse, $eUser).'</div>';
			$h .= '<div>'.$this->getTrackState($track).'</div>';
			if($eLesson['questions'] > 0) {
				$h .= '<div class="text-end">'.($attempted ? $this->getTrackScore($track) : '').'</div>';
				$h .= '<div class="text-end">'.($attempted ? $track['attempts'] : '').'</div>';
			}
			$h .= '<div class="util-checkbox text-center">';
				$h .= '<label>';
					$h .= new \util\FormUi()->inputCheckbox(attributes: [
						'checked' => $track['checked'],
						'title' => s("Constatée par l'équipe"),
						'oninput' => 'LearningTrack.check(this)',
						'data-course' => $eCourse['id'],
						'data-lesson' => $eLesson['id'],
						'data-user' => $eUser['id']
					]);
				$h .= '</label>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le stagiaire, et la structure au nom de laquelle il s'est inscrit s'il en a choisi une. Son nom
	 * ouvre son journal de lecture.
	 */
	private function getTrackPerson(Course $eCourse, \user\User $eUser): string {

		$h = '<div>';
			$h .= '<a href="'.ReadingUi::urlJournal($eCourse, $eUser).'"><b>'.\user\UserUi::name($eUser).'</b></a>';
			if(
				$eUser['eEnrollment']->notEmpty() and
				$eUser['eEnrollment']['structure']->notEmpty()
			) {
				$h .= '<div class="util-annotation">'.encode($eUser['eEnrollment']['structure']['name']).'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que le stagiaire a fait de la leçon, en badge : déclarée, réussie ou à refaire au quiz. Une
	 * leçon pas commencée n'en porte pas, la cellule vide le dit.
	 */
	public function getTrackState(array $track): string {

		return match($track['state']) {
			ProgressLib::TRACK_NONE => '',
			ProgressLib::TRACK_DECLARED => '<span class="badge badge-info badge-dot">'.s("Déclarée").'</span>',
			ProgressLib::TRACK_PASSED => '<span class="badge badge-success badge-dot">'.s("Réussie").'</span>',
			ProgressLib::TRACK_FAILED => '<span class="badge badge-draft badge-dot">'.s("À refaire").'</span>',
		};

	}

	public function getTrackScore(array $track): string {
		return s("{right} sur {questions}", ['right' => $track['right'], 'questions' => $track['questions']]);
	}

	/**
	 * Deux exports sous la même portée que la grille : le tableau tel que l'écran le montre, et le
	 * détail, une ligne par stagiaire et par leçon, avec ses dates.
	 */
	private function getExportMenu(Course $eCourse, \structure\Section $eSection, \structure\Structure $eStructureTrack, bool $isNetwork): string {

		$url = fn(string $page) => $isNetwork
			? self::urlExportStructure($eCourse, $eStructureTrack, $page)
			: self::urlExport($eCourse, $eSection, $page);

		$h = '<a class="btn btn-outline-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('download').' '.s("Exporter en CSV").'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<a href="'.$url('export').'" class="dropdown-item" data-ajax-download data-waiter>'.s("Le tableau").'</a>';
			$h .= '<a href="'.$url('exportDetail').'" class="dropdown-item" data-ajax-download data-waiter>'.s("Le détail par leçon").'</a>';
		$h .= '</div>';

		return $h;

	}

	public static function urlExport(Course $eCourse, \structure\Section $eSection, string $page = 'export'): string {

		$url = '/learning/progress:'.$page.'?id='.$eCourse['id'];

		if($eSection->notEmpty()) {
			$url .= '&section='.$eSection['id'];
		}

		return $url;

	}

	public static function urlExportStructure(Course $eCourse, \structure\Structure $eStructure, string $page = 'export'): string {

		$url = '/learning/progress:'.$page.'?id='.$eCourse['id'];

		if($eStructure->notEmpty()) {
			$url .= '&structure='.$eStructure['id'];
		}

		return $url;

	}

	/**
	 * L'export du suivi (F28), en-tête compris, sur des stagiaires passés par
	 * `ProgressLib::fillTrack()` et `ReadingLib::fillLastSeen()`. Le total est un nombre seul : un
	 * tableur lirait « 1/2 » comme le 1er février. La dernière activité se date en entier, l'année
	 * comprise, que la grille omet.
	 */
	public function getExport(\Collection $cChapter, \Collection $cUser): array {

		$header = [...$this->getExportPersonHeader(), s("Leçons suivies"), s("Dernière activité")];

		foreach($cChapter as $eChapter) {
			foreach($eChapter['cLesson'] as $eLesson) {
				$header[] = $eLesson['number'].'. '.$eLesson['name'];
			}
		}

		$export = [$header];

		foreach($cUser as $eUser) {

			$export[] = array_merge(
				$this->getExportPerson($eUser),
				[$eUser['followed'], ($eUser['lastSeenAt'] === NULL) ? '' : \util\DateUi::numeric($eUser['lastSeenAt'], \util\DateUi::DATE)],
				array_map(fn(array $track) => $this->getTrackExport($track), array_values($eUser['track']))
			);

		}

		return $export;

	}

	/**
	 * L'export détaillé, une ligne par stagiaire et par leçon, sur des stagiaires passés par
	 * `ProgressLib::fillTrack()`, `fillDetail()` et `ReadingLib::fillMeasured()`. Une leçon que le
	 * stagiaire n'a pas touchée a sa ligne, à cases vides : l'absence se lit, elle ne se déduit pas
	 * d'une ligne manquante. Date et heure dans une seule colonne, sans « à », qu'un tableur lit
	 * comme une date. Les bonnes réponses, le score et l'heure sont ceux de la dernière tentative,
	 * seule gardée. Le temps mesuré est celui du relevé et du journal, toutes séances de la leçon.
	 */
	public function getExportDetail(\Collection $cChapter, \Collection $cUser): array {

		$export = [[
			...$this->getExportPersonHeader(),
			s("Inscription"),
			s("Module"),
			s("Leçon"),
			s("Durée estimée (min)"),
			s("Temps mesuré (min)"),
			s("État"),
			s("Suivie le"),
			s("Bonnes réponses"),
			s("Questions"),
			s("Score (%)"),
			s("Tentatives"),
			s("Dernière tentative"),
			s("Constatée le"),
			s("Constatée par"),
		]];

		$dateTime = fn(?string $value) => ($value === NULL) ? '' : \util\DateUi::numeric($value, \util\DateUi::DATE).' '.\util\DateUi::numeric($value, \util\DateUi::TIME_HOUR_MINUTE);

		foreach($cUser as $eUser) {

			$person = $this->getExportPerson($eUser);
			$enrolledAt = $eUser['eEnrollment']->empty() ? '' : $dateTime($eUser['eEnrollment']['createdAt']);

			foreach($cChapter as $eChapter) {

				foreach($eChapter['cLesson'] as $eLesson) {

					$track = $eUser['track'][$eLesson['id']];
					$detail = $eUser['detail'][$eLesson['id']];

					$export[] = [
						...$person,
						$enrolledAt,
						$eChapter['number'].'. '.$eChapter['name'],
						$eLesson['number'].'. '.$eLesson['name'],
						$eLesson['minutes'] ?? '',
						($eUser['measured'][$eLesson['id']] === NULL) ? '' : $eUser['measured'][$eLesson['id']],
						match($track['state']) {
							ProgressLib::TRACK_NONE => '',
							ProgressLib::TRACK_DECLARED => s("Déclarée"),
							ProgressLib::TRACK_PASSED => s("Réussie"),
							ProgressLib::TRACK_FAILED => s("À refaire"),
						},
						$dateTime($detail['doneAt']),
						$track['right'] ?? '',
						$track['questions'] ?? '',
						$detail['score'] ?? '',
						($track['attempts'] > 0) ? $track['attempts'] : '',
						$dateTime($detail['answeredAt']),
						$dateTime($detail['checkedAt']),
						$detail['eUserChecked']->empty() ? '' : decode($detail['eUserChecked']->getName()),
					];

				}

			}

		}

		return $export;

	}

	/**
	 * Les deux premières colonnes des deux exports : qui est le stagiaire, et au nom de quelle ferme
	 * il suit la formation. Une seule écriture pour que les deux fichiers d'un même menu disent la
	 * même chose.
	 */
	private function getExportPersonHeader(): array {
		return [s("Stagiaire"), s("Ferme")];
	}

	private function getExportPerson(\user\User $eUser): array {

		$eEnrollment = $eUser['eEnrollment'];

		return [
			decode($eUser->getName()),
			($eEnrollment->notEmpty() and $eEnrollment['structure']->notEmpty()) ? $eEnrollment['structure']['name'] : ''
		];

	}

	/**
	 * Ce qu'une cellule de l'export dit de la leçon, ce que l'écran dit dans la cellule : les
	 * morceaux de l'état, puis le constat de l'équipe, joints par une virgule, le premier avec sa
	 * majuscule. Une seule règle pour toutes les combinaisons, qui ne demande ni « et » ni phrase à
	 * reconstruire quand un morceau s'ajoute.
	 */
	public function getTrackExport(array $track): string {

		$parts = match($track['state']) {
			ProgressLib::TRACK_NONE => [],
			ProgressLib::TRACK_DECLARED => [s("déclarée")],
			ProgressLib::TRACK_PASSED => [s("réussie"), $this->getTrackScore($track)],
			ProgressLib::TRACK_FAILED => [s("à refaire"), $this->getTrackScore($track), p("{value} tentative", "{value} tentatives", $track['attempts'])],
		};

		if($track['checked']) {
			$parts[] = s("constatée");
		}

		return mb_ucfirst(implode(', ', $parts));

	}

}
?>
