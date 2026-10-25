<?php
/**
 * Les modules d'une formation, depuis son éditeur. Un module se crée, se renomme et se supprime
 * dans un panneau ; son ordre se change à la souris, par l'action de `course`.
 *
 * La formation se contrôle ici par `acceptWrite()` : `Chapter::acceptCreate()` rend TRUE pour
 * l'API, qui ne connaît le parent qu'après `build()`.
 */
new \learning\ChapterPage()
	->getCreateElement(function($data) {

		return new \learning\Chapter([
			'course' => \learning\CourseLib::getById(INPUT('course'))->validate('acceptWrite')
		]);

	})
	// Le numéro que le champ du nom montre en préfixe, lu dans le même programme que celui de
	// l'éditeur. Le module renommé garde le sien. Le module créé prend le nombre de modules pour
	// position (`ChapterLib::create()`), et les positions restent sans trou (`ChapterLib::delete()`
	// resserre les suivants) : il vient donc en dernier, au numéro qui suit le nombre de modules.
	->create(function($data) {

		$data->e['number'] = \learning\ChapterLib::getByCourse($data->e['course'])->count() + 1;

		throw new ViewAction($data);

	})
	->doCreate(fn() => throw new ReloadAction('learning', 'Chapter::created'))
	->update(function($data) {

		$data->e['number'] = \learning\ChapterLib::getByCourse($data->e['course'])[$data->e['id']]['number'];

		throw new ViewAction($data);

	})
	->doUpdate(fn() => throw new ReloadAction('learning', 'Chapter::updated'))
	/**
	 * `validate:` retire `acceptDelete` et garde `acceptWrite`, sur qui il retombe : `ModulePage::doDelete()`
	 * valide avant d'ouvrir son `FailWatch`, et un refus posé dans un `accept*` sortirait sans message.
	 * Le refus qui parle est celui de `ChapterLib::delete()`, qui lève `deleteLastPublished` ou `deleteCompleted`.
	 */
	->doDelete(fn() => throw new ReloadAction('learning', 'Chapter::deleted'), validate: ['canDelete', 'acceptWrite']);
?>
