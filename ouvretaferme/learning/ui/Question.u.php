<?php
namespace learning;

/**
 * Le quiz d'une leçon, du côté de l'auteur : la section de l'écran de la leçon qui liste ses
 * questions, et le panneau d'une question avec ses choix (planche L5, QZ3 A et QZ4).
 */
class QuestionUi {

	public function __construct() {
		\Asset::lib('util', 'Sortable-1.11.0.js');
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	/**
	 * La section du quiz, dans son onglet de l'écran de la leçon (`LessonUi::getEdition()`), passée par
	 * `QuestionLib::fillEditor()`. Elle suit la validation que le panneau de la leçon choisit (QZ3 A) :
	 * - un quiz posé liste ses questions, avec le seuil en nombre de bonnes réponses, ou appelle sa
	 *   première question tant qu'il n'en a pas ;
	 * - une leçon qui se déclare suivie garde les questions qu'elle a eues, marquées « Pas posé », et
	 *   « Poser ce quiz » la passe au quiz sans ouvrir ses réglages (`lesson:doPoseQuiz`) ;
	 * - sans question ni quiz posé, elle le constate sous le titre marqué « Pas posé », qui garde le
	 *   bouton de la première question : son onglet existe toujours, et une leçon publiée ne passe au
	 *   quiz qu'avec une question (`Lesson::validation.questioned`), qui s'écrit donc ici d'abord.
	 */
	public function getEditor(Lesson $eLesson): string {

		$eLesson->expects(['validation', 'quizThreshold', 'cQuestion', 'needed']);

		$cQuestion = $eLesson['cQuestion'];
		$posed = ($eLesson['validation'] === Lesson::QUIZ);

		$h = '<div id="lesson-quiz">';

			if($posed and $cQuestion->empty()) {

				$h .= '<div class="util-title mt-2">';
					$h .= '<h3>'.LessonUi::getEditionPanelTitle('quiz').'</h3>';
				$h .= '</div>';

				$h .= '<div class="util-block-cta mb-2">';
					$h .= '<h3>'.s("Le quiz n'a pas encore de question").'</h3>';
					$h .= '<p>'.s("La leçon se valide quand le stagiaire réussit le quiz. Tant qu'il n'a pas de question, elle ne peut pas l'être.").'</p>';
					$h .= '<div class="util-buttons">'.$this->getCreate($eLesson, 'btn-primary').'</div>';
				$h .= '</div>';

			} else {

				$h .= '<div class="util-title mt-2">';
					$h .= '<h3>';
						$h .= LessonUi::getEditionPanelTitle('quiz');
						if($cQuestion->notEmpty()) {
							$h .= ' <span class="util-counter">'.$cQuestion->count().'</span>';
						}
						if($posed === FALSE) {
							$h .= ' <span class="badge badge-neutral badge-dot">'.s("Pas posé").'</span>';
							if($cQuestion->notEmpty()) {
								$h .= ' <a data-ajax="/learning/lesson:doPoseQuiz" post-id="'.$eLesson['id'].'" class="btn btn-sm btn-primary learning-quiz-pose" data-waiter>'.s("Poser ce quiz").'</a>';
							}
						}
					$h .= '</h3>';
					$h .= '<div>'.$this->getCreate($eLesson, 'btn-outline-primary').'</div>';
				$h .= '</div>';

				if($cQuestion->notEmpty()) {

					if($posed) {
						$h .= '<p class="util-annotation mt-1 mb-1">'.s("Réussi à partir de {threshold}&nbsp;%, soit {needed} sur {total}.", [
							'threshold' => $eLesson['quizThreshold'],
							'needed' => p("{value} bonne réponse", "{value} bonnes réponses", $eLesson['needed']),
							'total' => $cQuestion->count()
						]).'</p>';
					} else {
						$h .= '<p class="util-annotation mt-1 mb-1">'.s("Ces questions sont gardées mais ne sont pas posées. La leçon se valide quand le stagiaire la déclare suivie.").'</p>';
					}

					$h .= $this->getList($eLesson, $posed);

				} else {

					$h .= '<div class="util-block util-block-empty">';
						$h .= '<h3>'.s("Aucune question").'</h3>';
					$h .= '</div>';

				}

			}

		$h .= '</div>';

		return $h;

	}

	protected function getCreate(Lesson $eLesson, string $class): string {
		return '<a href="/learning/question:create?lesson='.$eLesson['id'].'" class="btn '.$class.'">'.\Asset::icon('plus-circle').' '.s("Ajouter une question").'</a>';
	}

	/**
	 * Les questions dans l'ordre du quiz, rangées à la poignée comme les fichiers, que la liste suit
	 * au même gabarit. Le numéro se réécrit après un déplacement (`LearningSort.renumber()`).
	 */
	protected function getList(Lesson $eLesson, bool $posed): string {

		$h = '<div class="util-table util-table-bordered learning-table-question mb-2'.($posed ? '' : ' color-muted').'" '.attr('onrender', 'LearningSort.init(this)').' data-sort-url="/learning/question:doUpdatePositions" post-lesson="'.$eLesson['id'].'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div></div>';
				$h .= '<div>'.s("Question").'</div>';
				$h .= '<div>'.s("Bonnes réponses").'</div>';
				$h .= '<div>'.s("Image").'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($eLesson['cQuestion'] as $eQuestion) {

				$h .= '<div class="util-table-row" data-sort-id="'.$eQuestion['id'].'">';
					$h .= '<div>'.\main\DesignUi::getDragHandle().'</div>';
					$h .= '<div><a href="/learning/question:update?id='.$eQuestion['id'].'"><span data-number="question">'.$eQuestion['number'].'</span>. '.encode($eQuestion['statement']).'</a></div>';
					$h .= '<div class="color-muted">'.self::p('type')->values[$eQuestion['type']].'</div>';
					$h .= '<div class="color-muted">'.($eQuestion['hasImage'] ? \Asset::icon('image', ['title' => s("Avec une image")]) : '').'</div>';
					$h .= '<div>'.$this->getActions($eQuestion).'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getActions(Question $eQuestion): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.s("Question {value}", $eQuestion['number']).'</div>';
			$h .= '<a href="/learning/question:update?id='.$eQuestion['id'].'" class="dropdown-item">'.s("Modifier la question").'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/learning/question:doDelete" post-id="'.$eQuestion['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer cette question et les réponses des stagiaires ?").'" data-waiter>'.s("Supprimer la question").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function create(Question $eQuestion): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/question:doCreate', ['onrender' => 'LearningQuiz.init(this)']);

			$h .= $form->hidden('lesson', $eQuestion['lesson']);
			$h .= $form->hidden('origin', $eQuestion['origin']);

			$h .= $this->getFields($form, $eQuestion);

			$h .= $form->group(content: $form->submit(s("Ajouter la question")));

		$h .= $form->close();

		$h .= $this->getChoiceTemplate($form);

		return new \Panel(
			id: 'panel-learning-question',
			title: s("Ajouter une question"),
			subTitle: s("Question {value}", $eQuestion['number']),
			body: $h
		);

	}

	public function update(Question $eQuestion): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/question:doUpdate', ['onrender' => 'LearningQuiz.init(this)']);

			$h .= $form->hidden('id', $eQuestion);
			$h .= $form->hidden('origin', $eQuestion['origin']);

			$h .= $this->getFields($form, $eQuestion);

			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		$h .= $this->getChoiceTemplate($form);

		return new \Panel(
			id: 'panel-learning-question',
			title: s("Modifier la question"),
			subTitle: s("Question {number} sur {total}", ['number' => $eQuestion['number'], 'total' => $eQuestion['questions']]),
			body: $h
		);

	}

	protected function getFields(\util\FormUi $form, Question $eQuestion): string {

		$eQuestion->expects(['cChoice']);

		$h = $form->dynamicGroups($eQuestion, ['statement*', 'image', 'type*']);

		$h .= $form->group(
			s("Choix").\util\FormUi::asterisk(),
			$this->getChoices($form, $eQuestion),
			['data-wrapper' => 'choices']
		);

		$h .= $form->dynamicGroup($eQuestion, 'explanation');

		return $h;

	}

	/**
	 * Les choix, rangés à la poignée : l'ordre posté est celui du quiz (QZ4). Les lignes ajoutées
	 * naissent du gabarit de `getChoiceTemplate()`, que la même méthode rend : `data-index` est le
	 * prochain rang libre, jamais réutilisé, pour qu'une ligne retirée ne laisse pas deux lignes au
	 * même nom.
	 */
	protected function getChoices(\util\FormUi $form, Question $eQuestion): string {

		$cChoice = $eQuestion['cChoice'];

		$h = '<div class="util-table util-table-bordered learning-table-choice">';

			$h .= '<div class="util-table-head">';
				$h .= '<div></div>';
				$h .= '<div>'.s("Juste").'</div>';
				$h .= '<div>'.s("Libellé").'</div>';
				$h .= '<div>'.s("Image").'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			$index = 0;

			foreach($cChoice as $eChoice) {
				$h .= $this->getChoice($form, $eQuestion['type'], $eChoice, (string)$index++);
			}

		$h .= '</div>';

		$h .= '<div class="flex-buttons mt-1">';
			$h .= '<a onclick="LearningQuiz.addChoice(this)" data-index="'.$index.'" class="btn btn-outline-primary btn-sm'.($cChoice->count() >= Question::CHOICES_MAX ? ' hide' : '').'" data-max="'.Question::CHOICES_MAX.'">'.\Asset::icon('plus-circle').' '.s("Ajouter un choix").'</a>';
			$h .= '<span class="util-annotation">'.s("Un libellé, une image ou les deux.").'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Hors du formulaire : un `<template>` n'appartient à aucun formulaire, et sa ligne ne poste rien
	 * tant qu'elle n'a pas été posée dans la liste.
	 */
	protected function getChoiceTemplate(\util\FormUi $form): string {
		return '<template id="learning-choice-template">'.$this->getChoice($form, Question::SINGLE, new Choice(['label' => NULL, 'image' => NULL, 'correct' => FALSE]), '{index}').'</template>';
	}

	/**
	 * Une ligne de choix. « Juste » est un bouton radio ou une case selon le type de la question, sans
	 * nom : il pilote le champ caché voisin, que le formulaire poste toujours, coché ou non. Une case
	 * décochée ne poste rien, et un choix enregistré garderait sinon la valeur qu'il avait.
	 *
	 * Chaque champ porte un id tiré du rang : `FormUi` en tirerait un au hasard, que le modèle
	 * recopierait tel quel à chaque choix ajouté.
	 */
	protected function getChoice(\util\FormUi $form, string $type, Choice $eChoice, string $index): string {

		$attributes = [
			'id' => 'learning-choice-'.$index.'-right',
			'checked' => $eChoice['correct'],
			'onchange' => 'LearningQuiz.check(this)',
		];

		$h = '<div class="util-table-row" data-choice>';

			$h .= '<div>'.\main\DesignUi::getDragHandle().'</div>';

			$h .= '<div class="util-checkbox">';
				$h .= '<label title="'.s("Réponse juste").'">';
					$h .= match($type) {
						Question::SINGLE => $form->inputRadio(NULL, '1', attributes: $attributes),
						Question::MULTIPLE => $form->inputCheckbox(NULL, '1', $attributes),
					};
				$h .= '</label>';
				$h .= $form->hidden('choices[id]['.$index.']', $eChoice->exists() ? $eChoice['id'] : NULL, ['id' => 'learning-choice-'.$index.'-id']);
				$h .= $form->hidden('choices[correct]['.$index.']', $eChoice['correct'], ['id' => 'learning-choice-'.$index.'-correct', 'data-choice-correct' => TRUE]);
			$h .= '</div>';

			$h .= '<div data-wrapper="label['.$index.']">';
				$h .= $form->text('choices[label]['.$index.']', $eChoice['label'], ['id' => 'learning-choice-'.$index.'-label', 'placeholder' => s("Sans libellé")]);
			$h .= '</div>';

			$h .= '<div data-wrapper="image['.$index.']">';
				$h .= new \media\LearningQuizAiUi()->getHashCamera('choices[image]['.$index.']', 'learning-choice-'.$index, $eChoice['image'], '4rem', '3rem');
			$h .= '</div>';

			$h .= '<div>';
				$h .= '<a onclick="LearningQuiz.removeChoice(this)" class="btn btn-only-icon btn-outline-primary" title="'.s("Supprimer").'">'.\Asset::icon('trash').'</a>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Question::model()->describer($property, [
			'statement' => s("Question"),
			'image' => s("Image"),
			'type' => s("Bonnes réponses"),
			'explanation' => s("Explication"),
		]);

		switch($property) {

			case 'statement' :
				$d->field = 'textarea';
				$d->attributes = ['rows' => 2];
				break;

			case 'image' :
				$d->field = fn(\util\FormUi $form, Question $e) => new \media\LearningQuizAiUi()->getHashCamera('image', 'learning-question', $e['image'], '12rem', '8.5rem');
				break;

			case 'type' :
				$d->field = 'radio';
				$d->values = [
					Question::SINGLE => s("Une seule"),
					Question::MULTIPLE => s("Plusieurs"),
				];
				$d->attributes = ['columns' => 2, 'onchange' => 'LearningQuiz.changeType(this)'];
				$d->required = TRUE;
				break;

			case 'explanation' :
				$d->field = 'textarea';
				$d->attributes = ['rows' => 3];
				$d->after = \util\FormUi::info(s("Montrée au stagiaire une fois sa réponse validée."));
				break;

		}

		return $d;

	}

}
?>
