<?php
/**
 * Les avis depuis la fiche d'une formation, par un compte connecté : l'auteur donne, modifie et
 * retire son avis ; qui écrit la formation répond et signale. Chaque geste recharge la fiche, qui
 * relit la note, la répartition et le fil.
 *
 * Le panneau de l'auteur ne pèse pas l'état : un avis masqué s'y relit avec son motif, et seul son
 * retrait reste possible (`update()` sans `acceptUpdate`, `doUpdate` avec).
 *
 * « Voir les autres avis » est public comme la fiche, et rend la suite du fil au même endroit.
 */
new Page()
	->get('more', function($data) {

		$data->eCourse = \learning\CourseLib::getById(GET('course'))->validate('assertRead', 'isInCatalog');

		$withHidden = ($data->eCourse->canWrite() or $data->eCourse->canDecide());

		$data->offset = GET('offset', 'int');
		$data->cReview = \learning\ReviewLib::getForCourse($data->eCourse, $withHidden, $data->offset);
		$data->more = \learning\ReviewLib::countForCourse($data->eCourse, $withHidden) - $data->offset - $data->cReview->count();

		throw new ViewAction($data);

	});

new \learning\ReviewPage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$eCourse = \learning\CourseLib::getById(INPUT('course'))->validate('assertRead');

		return \learning\ReviewLib::getCreateElement($eCourse, \user\ConnectionLib::getOnline());

	})
	/**
	 * Les gestes sur un avis pèsent son état (masqué, au catalogue) et non celui de sa formation :
	 * la structure qui l'écrit doit être ouverte aux formations pour chacun d'eux.
	 */
	->applyElement(function($data, \learning\Review $eReview) {
		$eReview['course']->validate('acceptLearning');
	})
	->create()
	->doCreate(fn($data) => throw new ReloadAction('learning', $data->e->isCatalog() ? 'Review::created' : 'Review::given'))
	->update(validate: ['canUpdate'])
	->doUpdate(fn() => throw new ReloadAction('learning', 'Review::updated'))
	->doDelete(fn() => throw new ReloadAction('learning', 'Review::deleted'))
	->update(page: 'answer', validate: ['canAnswer', 'acceptAnswer'])
	->write('doAnswer', function($data) {

		$fw = new FailWatch();

		$data->e->build(['answer'], $_POST);

		$fw->validate();

		\learning\ReviewLib::answer($data->e);

		throw new ReloadAction('learning', $data->e->isCatalog() ? 'Review::answered' : 'Review::answerSaved');

	}, validate: ['canAnswer', 'acceptAnswer'])
	->write('doDeleteAnswer', function($data) {

		\learning\ReviewLib::deleteAnswer($data->e);

		throw new ReloadAction('learning', 'Review::answerDeleted');

	}, validate: ['canAnswer', 'acceptAnswer'])
	/**
	 * Lire ne dépend pas de l'état de l'avis : masqué ou hors catalogue, il reste dans la boite.
	 */
	->write('doRead', function($data) {

		if($data->e['course']->isTeam()) {
			throw new NotExpectedAction('No review inbox for a team course');
		}

		\learning\ReviewLib::read($data->e);

		$data->eStructure = $data->e['course']['community'];
		$data->eReview = \learning\ReviewLib::getById($data->e['id'], \learning\Review::getSelection());
		$data->reviewsNew = \learning\ReviewLib::countNewByStructure($data->eStructure);

		throw new ViewAction($data);

	}, validate: ['canAnswer'])
	->update(page: 'report', validate: ['canReport', 'acceptReport'])
	->write('doReport', function($data) {

		$fw = new FailWatch();

		$data->e->build(['reportReason'], $_POST);

		$fw->validate();

		\learning\ReviewLib::report($data->e);

		throw new ReloadAction('learning', 'Review::reported');

	}, validate: ['canReport', 'acceptReport']);
?>
