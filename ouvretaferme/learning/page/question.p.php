<?php
/**
 * Le quiz d'une leçon, écrit par l'auteur depuis l'écran de la leçon : ajout et modification d'une
 * question dans un panneau, tri à la poignée, suppression.
 *
 * Chaque geste répond par la seule section du quiz (`view/question.v.php`), jamais par l'écran
 * entier, où le contenu de la leçon peut être en cours de saisie : c'est la règle des fichiers de
 * la leçon (`attachment.p.php`), qui partagent l'écran.
 *
 * `doCreate` et `doUpdate` sont écrits à la main : `ModulePage` ne construit que l'élément, et une
 * question s'écrit avec ses choix. Les deux routes reprennent les validations de `ModulePage`
 * (`framework/core/Module.php`), construisent la question puis ses choix sous le même `FailWatch`,
 * et n'écrivent qu'une fois tout validé, par un seul appel au Lib qui tient la transaction.
 */
$createElement = function(): \learning\Question {

	$eLesson = \learning\LessonLib::getById(INPUT('lesson'))->validate('canUpdate', 'acceptUpdate');

	return new \learning\Question([
		'lesson' => $eLesson,
		'type' => \learning\Question::SINGLE,
		'image' => NULL,
	]);

};

/**
 * Ouvert comme une page, et non par-dessus l'écran de la leçon, le panneau n'a rien derrière lui :
 * le fermer revient à l'URL précédente, que `Lime.Tab.restore()` a dépouillée de son onglet, et la
 * leçon se rouvrirait sur son contenu. Le formulaire poste alors l'onglet du quiz, et l'action y
 * ramène elle-même. Ouvert par-dessus l'écran, il ne poste rien, et l'action ne remplace que la
 * section du quiz.
 */
$getOrigin = fn(): string => (Route::getRequestedWith() === 'ajax') ? '' : 'quiz';

$throwOrigin = function(\learning\Lesson $eLesson, string $success): void {

	if(POST('origin', ['quiz'], '') === 'quiz') {
		throw new RedirectAction(\learning\LessonUi::urlEdition($eLesson).'?tab=quiz&success=learning\\'.$success);
	}

};

new Page()
	->post('doCreate', function($data) use($createElement, $throwOrigin) {

		$eQuestion = $createElement()->validate('canCreate', 'acceptCreate');

		$fw = new FailWatch();

		$eQuestion->build(\learning\QuestionLib::getPropertiesCreate(), $_POST, new \PropertyList('create'));

		$cChoice = \learning\ChoiceLib::buildForQuestion($eQuestion, POST('choices', 'array', []));

		$fw->validate();

		\learning\QuestionLib::create($eQuestion, $cChoice);

		$throwOrigin($eQuestion['lesson'], 'Question::created');

		$data->eLesson = $eQuestion['lesson'];
		\learning\QuestionLib::fillEditor($data->eLesson);

		throw new ViewAction($data);

	})
	->post('doUpdate', function($data) use($throwOrigin) {

		$eQuestion = \learning\QuestionLib::getById(POST('id'))->validate('canUpdate', 'acceptUpdate');

		$fw = new FailWatch();

		$properties = \learning\QuestionLib::getPropertiesUpdate();

		$eQuestion->build($properties, $_POST, new \PropertyList('update'));

		$cChoice = \learning\ChoiceLib::buildForQuestion($eQuestion, POST('choices', 'array', []));

		$fw->validate();

		\learning\QuestionLib::update($eQuestion, $properties, $cChoice);

		$throwOrigin($eQuestion['lesson'], 'Question::updated');

		$data->eLesson = $eQuestion['lesson'];
		\learning\QuestionLib::fillEditor($data->eLesson);

		throw new ViewAction($data);

	})
	->post('doUpdatePositions', function($data) {

		$eLesson = \learning\LessonLib::getById(POST('lesson'))->validate('canUpdate', 'acceptUpdate');

		$positions = json_decode(POST('positions'), TRUE);

		if(is_array($positions) === FALSE) {
			throw new NotExpectedAction('Positions');
		}

		$fw = new FailWatch();

		\learning\QuestionLib::updatePositions($eLesson, array_map('intval', $positions));

		$fw->validate();

		throw new VoidAction();

	});

new \learning\QuestionPage()
	->getCreateElement(fn($data) => $createElement())
	/**
	 * Une question neuve part avec le nombre de choix le plus petit qu'elle accepte, vides.
	 */
	->create(function($data) use($getOrigin) {

		$data->e['cChoice'] = new Collection();

		for($position = 0; $position < \learning\Question::CHOICES_MIN; $position++) {
			$data->e['cChoice'][] = new \learning\Choice(['label' => NULL, 'image' => NULL, 'correct' => FALSE]);
		}
		$data->e['number'] = \learning\QuestionLib::countByLesson($data->e['lesson']) + 1;
		$data->e['origin'] = $getOrigin();

		throw new ViewAction($data);

	})
	->update(function($data) use($getOrigin) {

		$data->e['cChoice'] = \learning\ChoiceLib::getByQuestion($data->e);
		$data->e['number'] = $data->e['position'] + 1;
		$data->e['questions'] = \learning\QuestionLib::countByLesson($data->e['lesson']);
		$data->e['origin'] = $getOrigin();

		throw new ViewAction($data);

	})
	/**
	 * Même `validate:` que la suppression d'une leçon, pour la même raison (`lesson.p.php`) : la
	 * dernière question d'un quiz posé sous une formation publiée est refusée par
	 * `QuestionLib::delete()`, qui parle, et non par un `accept*`, qui sortirait muet.
	 */
	->doDelete(function($data) {

		$data->eLesson = $data->e['lesson'];
		\learning\QuestionLib::fillEditor($data->eLesson);

		throw new ViewAction($data);

	}, validate: ['canDelete', 'acceptWrite']);
?>
