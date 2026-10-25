<?php
namespace learning;

/**
 * Le quiz d'une leçon, du côté du lecteur : toutes les questions sur la page et un seul envoi
 * (planche L5, QZ1 A), puis la correction de la dernière tentative, question par question, sans la
 * bonne réponse (QZ2 A) : une nouvelle tentative reste une tentative.
 *
 * La leçon est passée par `ResponseLib::fillQuiz()`. Le quiz se lit sans inscription, comme la
 * leçon ; seul l'inscrit l'envoie, et le bouton qui l'envoie est celui de `ProgressUi::getFollow()`.
 * Qui n'est pas inscrit lit les questions sans cases, et « Commencer la formation » le ramène sur
 * le quiz (`url()`), où elles apparaissent (2026-09-25 : des réponses cochées avant
 * l'inscription, qu'elle ne garde pas, étaient un geste pour rien).
 *
 * Sur une formation datée, le quiz reste fermé jusqu'à ce que le réseau l'ouvre pour une date où le
 * lecteur est confirmé (`ResponseLib::fillQuizState()`) : il ne montre alors aucune question, pas
 * même à lire (lot L10d, N1).
 */
class QuizUi {

	public function __construct() {
		\Asset::css('util', 'form.css');
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	/**
	 * La leçon ouverte sur son quiz, et non sur son titre : on y revient de « Commencer la
	 * formation », posé sous les questions, et la leçon au-dessus serait à redescendre.
	 */
	public static function url(Lesson $eLesson, array $query = []): string {
		return LessonUi::url($eLesson, $query).'#learning-quiz';
	}

	/**
	 * La section entière, que les deux gestes du lecteur redessinent (`view/response.v.php`) sans
	 * toucher au défilement : un geste ne déplace jamais l'écran. Après une tentative, le résultat
	 * et ses boutons prennent la place de « Valider mes réponses », en pied du quiz, là où le geste
	 * a été fait ; chaque correction reste sous sa question, et la tête ne garde que le titre.
	 *
	 * Une leçon déclarée suivie avant de passer au quiz garde cet acquis (V-Learning, C3) : le quiz
	 * s'y lit sans formulaire, et `ResponseLib::answer()` refuserait de toute façon une tentative
	 * sur une progression déjà close. Il se lit aussi sans formulaire pour qui n'est pas inscrit,
	 * qu'il puisse commencer la formation ou non (`ProgressUi::hasFollowAction()`) : seul
	 * l'inscrit a un bouton qui envoie des réponses.
	 */
	public function get(Lesson $eLesson, Enrollment $eEnrollment, \Collection $cProgress, \Collection $cStructure, bool $isLogged): string {

		$eLesson->expects(['quiz']);

		$h = '<div id="learning-quiz">';

			$h .= '<div class="util-title mt-2">';
				$h .= '<h3>'.s("Quiz").'</h3>';
			$h .= '</div>';

			if($eLesson['quiz'] !== ResponseLib::QUIZ_OPEN) {

				$h .= $this->getClosed($eLesson);

			} else if(
				$cProgress->offsetExists($eLesson['id']) and
				$cProgress[$eLesson['id']]['source'] === Progress::DECLARED
			) {

				$h .= $this->getDeclared($eLesson, $cProgress);

				$h .= $this->getReadings($eLesson);

			} else if($eLesson['attempted']) {

				$h .= '<div class="learning-quiz">';
					foreach($eLesson['cQuestion'] as $eQuestion) {
						$h .= $this->getCorrection($eQuestion);
					}
				$h .= '</div>';

				if($cProgress->offsetExists($eLesson['id'])) {
					$h .= $this->getPassed($eLesson);
				} else {
					$h .= $this->getFailed($eLesson, $eEnrollment);
				}

			} else if(
				$eEnrollment->empty() or
				ProgressUi::hasFollowAction($eLesson, $eEnrollment, $isLogged) === FALSE
			) {

				$h .= $this->getReadings($eLesson);

				$h .= new ProgressUi()->getFollow($eLesson, $eEnrollment, $cProgress, $cStructure, $isLogged);

			} else {

				$h .= $this->getNeeded($eLesson);

				$form = new \util\FormUi();

				$h .= $form->openAjax('/learning/response:doAnswer');

					$h .= $form->hidden('lesson', $eLesson);

					// Le gérant qui essaie un thème le garde après sa réponse : la section et la colonne
					// redessinées relisent les réglages postés (`response.p.php`).
					$h .= $form->hiddens($eLesson['course']['customizeQuery'] ?? []);

					$h .= '<div class="learning-quiz">';
						foreach($eLesson['cQuestion'] as $eQuestion) {
							$h .= $this->getQuestion($form, $eQuestion);
						}
					$h .= '</div>';

					$h .= new ProgressUi()->getFollow($eLesson, $eEnrollment, $cProgress, $cStructure, $isLogged);

				$h .= $form->close();

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le quiz d'une formation datée fermé au lecteur, sans ses questions : pas encore ouvert pour la
	 * date où il est confirmé, réservé aux inscriptions confirmées quand la sienne attend, ou réservé
	 * aux personnes inscrites à une date (N1). La fiche porte les dates où s'inscrire.
	 */
	protected function getClosed(Lesson $eLesson): string {

		$eLesson->expects(['eDateConfirmed', 'course' => ['community']]);

		$h = '<div class="util-card util-card-accent util-card-accent-info">';

			if($eLesson['quiz'] === ResponseLib::QUIZ_NOT_OPENED) {

				$h .= '<div class="util-card-title">'.s("Le quiz n'est pas encore ouvert").'</div>';

				// Le premier jour atteint, qu'on y soit, après, ou la date finie, il n'y a plus de jour à annoncer.
				if($eLesson['eDateConfirmed']->isStarted()) {
					$h .= '<p class="mb-0">'.s("{community} ne l'a pas encore ouvert. Vous recevrez un e-mail à l'ouverture.", [
						'community' => encode($eLesson['course']['community']['name'])
					]).'</p>';
				} else {
					$h .= '<p class="mb-0">'.s("{community} l'ouvre à partir du premier jour de votre date, le {day}. Vous recevrez alors un e-mail.", [
						'community' => encode($eLesson['course']['community']['name']),
						'day' => \util\DateUi::textual($eLesson['eDateConfirmed']['startAt'], \util\DateUi::DAY_MONTH)
					]).'</p>';
				}

			} else if($eLesson['quiz'] === ResponseLib::QUIZ_UNCONFIRMED) {

				$h .= '<div class="util-card-title">'.s("Votre inscription n'est pas encore confirmée").'</div>';
				$h .= '<p class="mb-0">'.s("Le quiz est réservé aux personnes dont l'inscription est confirmée.").'</p>';

			} else {

				$h .= '<div class="util-card-title">'.s("Le quiz est réservé aux personnes inscrites à une date").'</div>';
				$h .= '<p>'.s("Il s'ouvre pour chaque date à partir de son premier jour.").'</p>';
				$h .= '<a href="'.CourseUi::url($eLesson['course'], $eLesson['course']['customizeQuery'] ?? []).'" class="btn btn-outline-primary">'.s("Voir les dates").'</a>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getNeeded(Lesson $eLesson): string {
		return '<p class="util-annotation">'.p("{value} bonne réponse sur {total} pour valider la leçon.", "{value} bonnes réponses sur {total} pour valider la leçon.", $eLesson['needed'], ['total' => $eLesson['cQuestion']->count()]).'</p>';
	}

	/**
	 * Réussi, le quiz ne se refait pas (QZ2) : la suite est la paire du bas de page
	 * (`LessonUi::getPager()`). Le résultat est en pied du quiz, là où l'on vient de valider, et
	 * porte sa propre marque, de la forme de « À refaire ».
	 */
	protected function getPassed(Lesson $eLesson): string {

		$h = '<div class="learning-quiz-result mt-2">';
			$h .= '<span class="badge badge-success badge-dot">'.s("Réussie").'</span>';
			$h .= '<span class="util-annotation">'.p("{value} bonne réponse sur {total}, {attempt}", "{value} bonnes réponses sur {total}, {attempt}", $eLesson['right'], ['total' => $eLesson['cQuestion']->count(), 'attempt' => $this->getAttempt($eLesson['attempts'])]).'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La leçon est acquise par la déclaration : il n'y a pas de résultat à dire, seulement son état
	 * (`ProgressUi::getState()`), qui ne se retire pas sous un quiz.
	 */
	protected function getDeclared(Lesson $eLesson, \Collection $cProgress): string {

		$h = '<div class="flex-buttons learning-follow mt-1 mb-2">';
			$h .= new ProgressUi()->getState($eLesson, $eLesson['course'], $cProgress);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une nouvelle tentative n'est offerte qu'à qui peut encore progresser : l'inscrit dont
	 * l'échéance n'est pas passée, les deux conditions de la route qui l'enregistre. Passé
	 * l'échéance, la date se lit à sa place.
	 */
	protected function getFailed(Lesson $eLesson, Enrollment $eEnrollment): string {

		$h = '<div class="learning-quiz-result mt-2">';
			$h .= '<span class="badge badge-draft badge-dot">'.s("À refaire").'</span>';
			$h .= '<span class="util-annotation">'.s("{right} sur {total}, il en faut {needed}", ['right' => $eLesson['right'], 'total' => $eLesson['cQuestion']->count(), 'needed' => $eLesson['needed']]).'</span>';
		$h .= '</div>';

		if($eEnrollment->empty()) {
			return $h;
		}

		if($eLesson['course']['canProgress'] === FALSE) {
			return $h.ProgressUi::getDuePast($eLesson['course']);
		}

		$h .= '<div class="flex-buttons learning-follow mt-1">';
			$h .= '<a data-ajax="/learning/response:retry?'.http_build_query(['lesson' => $eLesson['id']] + $eLesson['course']['customizeQuery']).'" data-ajax-method="get" class="btn btn-primary">'.\Asset::icon('arrow-repeat').' '.s("Nouvelle tentative").'</a>';
		$h .= '</div>';

		return $h;

	}

	protected function getAttempt(int $attempt): string {

		return match($attempt) {
			1 => s("à la première tentative"),
			2 => s("à la deuxième tentative"),
			3 => s("à la troisième tentative"),
			default => s("à la {value}e tentative", $attempt),
		};

	}

	/**
	 * Une question à répondre. Les choix en image se rangent côte à côte, un par colonne, et
	 * passent à deux colonnes sur un petit écran par la règle du cadriciel.
	 *
	 * Un groupe de cases entièrement décoché ne poste rien : c'est la réponse vide d'une question,
	 * que `ResponseLib::buildAnswers()` pose d'office pour chaque question de la leçon.
	 */
	protected function getQuestion(\util\FormUi $form, Question $eQuestion): string {

		$name = 'answer['.$eQuestion['id'].'][]';

		$values = [];

		foreach($eQuestion['cChoice'] as $eChoice) {
			$values[$eChoice['id']] = $this->getChoice($eChoice);
		}

		$columns = $eQuestion['hasChoiceImage'] ? $eQuestion['cChoice']->count() : 1;

		$h = '<div class="learning-quiz-question">';

			$h .= $this->getStatement($eQuestion);

			$h .= match($eQuestion['type']) {
				Question::SINGLE => $form->radios($name, $values, attributes: ['required' => TRUE, 'columns' => $columns]),
				Question::MULTIPLE => $form->checkboxes($name, $values, attributes: ['all' => FALSE, 'columns' => $columns]),
			};

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une question corrigée. La réponse est une valeur figée : elle s'écrit en texte, avec la marque
	 * du champ qu'elle a été, et non en champ désactivé, que le cadriciel éclaircirait.
	 */
	protected function getCorrection(Question $eQuestion): string {

		$badge = $eQuestion['isRight'] ?
			' <span class="badge badge-success badge-dot">'.s("Juste").'</span>' :
			' <span class="badge badge-draft badge-dot">'.s("À revoir").'</span>';

		$h = '<div class="learning-quiz-question">';

			$h .= $this->getStatement($eQuestion, $badge);
			$h .= $this->getChoices($eQuestion, $eQuestion['picked']);

			if($eQuestion['explanation'] !== NULL) {
				$h .= '<div class="util-info mt-1"><p>'.nl2br(encode($eQuestion['explanation'])).'</p></div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les questions lues sans y répondre, sous une leçon acquise par la déclaration ou pour qui n'a
	 * pas de quoi les envoyer.
	 */
	protected function getReadings(Lesson $eLesson): string {

		$h = '<div class="learning-quiz">';
			foreach($eLesson['cQuestion'] as $eQuestion) {
				$h .= $this->getReading($eQuestion);
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une question lue sans y répondre : ni choix coché, ni correction, ni explication, qui ne se
	 * montre qu'après une réponse. Les choix sont une liste de texte, sans le cadre d'un champ :
	 * rien ne s'y saisit, et un cadre de champ sans champ ment sur ce qu'on peut y faire
	 * (question `l12a-lecture-cadre`, tranchée le 2026-09-25). Sans marque non plus : un cercle
	 * vide passerait pour un bouton à cocher, et « Plusieurs réponses possibles » dit déjà le type
	 * de la question.
	 */
	protected function getReading(Question $eQuestion): string {

		$h = '<div class="learning-quiz-question">';
			$h .= $this->getStatement($eQuestion);
			$h .= '<ul class="learning-quiz-reading mb-0'.($eQuestion['hasChoiceImage'] ? ' learning-quiz-reading-images' : '').'">';
				foreach($eQuestion['cChoice'] as $eChoice) {
					$h .= '<li>'.$this->getChoice($eChoice).'</li>';
				}
			$h .= '</ul>';
		$h .= '</div>';

		return $h;

	}

	/** Les choix d'une question corrigée, ceux de la tentative cochés. */
	protected function getChoices(Question $eQuestion, array $picked): string {

		$columns = $eQuestion['hasChoiceImage'] ? $eQuestion['cChoice']->count() : 1;

		$h = '<div class="form-control field-radio-group field-radio-group-'.$columns.'">';

			foreach($eQuestion['cChoice'] as $eChoice) {

				$isPicked = in_array($eChoice['id'], $picked, TRUE);

				$h .= '<div class="field-radio-group-item'.($isPicked ? ' learning-quiz-picked' : '').'">';

					$icon = match($eQuestion['type']) {
						Question::SINGLE => $isPicked ? 'record-circle-fill' : 'circle',
						Question::MULTIPLE => $isPicked ? 'check-square-fill' : 'square',
					};

					$h .= '<span class="'.($isPicked ? 'color-primary' : 'color-muted').'">'.\Asset::icon($icon).'</span>';

					$h .= '<div>'.$this->getChoice($eChoice).'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getStatement(Question $eQuestion, string $badge = ''): string {

		$h = '<h4>'.$eQuestion['number'].'. '.encode($eQuestion['statement']).$badge.'</h4>';

		if($eQuestion['type'] === Question::MULTIPLE) {
			$h .= '<p class="util-annotation">'.s("Plusieurs réponses possibles.").'</p>';
		}

		if($eQuestion['image'] !== NULL) {
			$h .= '<img src="'.new \media\LearningQuizAiUi()->getUrlByHash($eQuestion['image'], 'm').'" class="learning-quiz-image" alt=""/>';
		}

		return $h;

	}

	protected function getChoice(Choice $eChoice): string {

		$h = '';

		if($eChoice['image'] !== NULL) {
			$h .= '<img src="'.new \media\LearningQuizAiUi()->getUrlByHash($eChoice['image'], 'm').'" class="learning-quiz-choice-image" alt=""/>';
		}

		if($eChoice['label'] !== NULL) {
			$h .= encode($eChoice['label']);
		}

		return $h;

	}

}
?>
