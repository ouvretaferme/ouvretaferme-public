<?php
/**
 * Le fil d'une leçon : un message s'écrit et se modifie dans un panneau ouvert depuis la leçon, et
 * se retire depuis le fil. Chaque geste recharge la leçon, qui relit le fil.
 *
 * Le même panneau répond à un commentaire (`parent`), depuis la leçon ou depuis la page des
 * commentaires du groupe. Celle-ci le dit par `origin=inbox`, et ne redessine alors que la ligne du
 * commentaire répondu et son titre.
 *
 * L'équipe marque un message lu depuis la page des commentaires du groupe, qui ne redessine que sa
 * ligne et son titre. Lire ne dépend pas de l'état de la leçon : `doRead` ne pèse que le droit.
 */
new \learning\CommentPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$eLesson = \learning\LessonLib::getById(INPUT('lesson'))->validate();

		return new \learning\Comment([
			'lesson' => $eLesson,
			'eCommentParent' => \learning\CommentLib::getParentByLesson($eLesson, INPUT('parent', '?int'))
		]);

	})
	->create(function($data) {

		// Le commentaire demandé a pu être retiré depuis l'affichage du fil ou de la boite.
		if(
			GET('parent', '?int') !== NULL and
			$data->e['eCommentParent']->empty()
		) {
			throw new NotExistsAction('Comment #'.GET('parent'));
		}

		$data->origin = GET('origin');

		// L'encart du panneau dit sous quel nom le message paraitra.
		$data->e['createdBy'] = \user\ConnectionLib::getOnline();

		if($data->origin === 'inbox') {
			\learning\ChapterLib::locate(\learning\ChapterLib::getByCourse($data->e['lesson']['course']), $data->e['lesson']);
		}

		throw new ViewAction($data);

	})
	->doCreate(function($data) {

		if($data->e['parent']->empty()) {
			throw new ReloadAction('learning', 'Comment::created');
		}

		// La page des commentaires est celle de l'équipe d'un groupe : elle seule reçoit la ligne du
		// commentaire répondu et le compte des nouveaux, tout autre appelant recharge son écran.
		if(
			POST('origin') !== 'inbox' or
			$data->e['lesson']['course']->isTeam() or
			$data->e->canModerate() === FALSE
		) {
			throw new ReloadAction('learning', 'Comment::answered');
		}

		$data->eStructure = $data->e['lesson']['course']['community'];
		$data->eComment = \learning\CommentLib::getForInbox($data->eStructure, $data->e['parent']);
		$data->commentsNew = \learning\CommentLib::countNewByStructure($data->eStructure);

		throw new ViewAction($data);

	})
	->update()
	->doUpdate(fn() => throw new ReloadAction('learning', 'Comment::updated'))
	->doDelete(fn() => throw new ReloadAction('learning', 'Comment::deleted'))
	->write('doRead', function($data) {

		if($data->e['lesson']['course']->isTeam()) {
			throw new NotExpectedAction('No comment page for a team course');
		}

		$data->e['lesson']['course']->validate('acceptLearning');

		\learning\CommentLib::read($data->e);

		$data->eStructure = $data->e['lesson']['course']['community'];
		$data->eComment = \learning\CommentLib::getForInbox($data->eStructure, $data->e);

		$data->commentsNew = \learning\CommentLib::countNewByStructure($data->eStructure);

		throw new ViewAction($data);

	}, validate: ['canModerate']);
?>
