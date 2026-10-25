<?php
new \sequence\SequencePage()
	->read('/itineraire/{id}', function($data) {

		$data->e->validate('canRead');

		$data->eStructure = \structure\StructureLib::getById($data->e['structure'])->validate('active');

		$data->cFlow = \sequence\FlowLib::getBySequence($data->e);

		if($data->cFlow->count() === 2) {
			$data->tip = \structure\TipLib::pickOne($data->eUserOnline, 'sequence-weeks');
		}

		$data->cPhoto = \gallery\PhotoLib::getBySequence($data->e);

		$data->ccSeries = \series\SeriesLib::getBySequence($data->e);

		$data->harvests = \sequence\CropLib::getHarvestsFromFlow($data->cFlow, \Clock::year(), group: 'month');
		$data->events = \sequence\FlowLib::reorder($data->e, $data->cFlow);

		$data->cActionMain = \work\ActionLib::getMainByStructure($data->eStructure);

		// Une note arrive sur l'itinéraire par l'espèce d'une de ses cultures ou par l'action d'une de ses
		// interventions : un itinéraire n'a pas de planche.
		$data->cNoteLinked = \note\NoteLib::getByTargets($data->eStructure, [
			\plant\NoteTargetLib::getType() => array_values(array_unique($data->e['cCrop']->getColumnCollection('plant')->getIds())),
			\work\NoteTargetLib::getType() => array_values(array_unique($data->cFlow->getColumnCollection('action')->getIds()))
		]);

		throw new ViewAction($data, path: ':display');

	});

new \sequence\SequencePage(function($data) {

		\user\ConnectionLib::checkLogged();

	})
	->getCreateElement(function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('acceptCultivation');

		return new \sequence\Sequence([
			'structure' => $data->eStructure,
		]);

	})
	->create(function($data) {

		$data->e->add([
			'cycle' => \sequence\Sequence::ANNUAL,
			'bedWidth' => $data->eStructure->conf(\map\Configuration::class)['defaultBedWidth'],
			'alleyWidth' => $data->eStructure->conf(\map\Configuration::class)['defaultAlleyWidth'],
		]);

		throw new ViewAction($data);

	})
	->doCreate(function($data) {
		throw new RedirectAction(\sequence\SequenceUi::url($data->e).'?success=sequence\\Sequence::created');
	});

new \sequence\SequencePage()
	->applyElement(function($data, \sequence\Sequence $e) {
		$e->validate('canWrite');
	})
	->update(page: 'updateComment')
	->doUpdateProperties('doUpdateComment', ['comment'], fn() => throw new ReloadAction())
	->quick(['name', 'bedWidth', 'mode'])
	->update()
	->doUpdate(fn($data) => throw new ReloadAction('sequence', 'Sequence::updated'))
	->doUpdateProperties('doUpdateStatus', ['status'], fn() => throw new ReloadAction('sequence', 'Sequence::updated'))
	// Décaler un itinéraire entier, comme on décale une série entière depuis le
	// plan : toutes ses interventions bougent du même nombre de semaines.
	->write('doIncrementWeek', function($data) {

		$fw = new FailWatch();

		$increment = POST('increment', 'int');

		if($increment < -26 or $increment > 26) {
			\Fail::log('Flow::increment.check');
		} else {

			// incrementWeek() a besoin du cycle pour savoir si une semaine peut
			// déborder sur l'année suivante.
			$cFlow = \sequence\FlowLib::getBySequence($data->e, properties: ['sequence' => ['cycle']]);

			\sequence\FlowLib::incrementWeekCollection($cFlow, $increment);

		}

		$fw->validate();

		throw new ReloadAction();

	})
	->write('doDuplicate', function($data) {

		$data->eSequenceNew = \sequence\SequenceLib::duplicate($data->e);

		throw new RedirectAction(\sequence\SequenceUi::url($data->eSequenceNew).'?success=sequence\\Sequence::duplicated');

	})
	->doDelete(fn($data) => throw new RedirectAction(\structure\StructureUi::urlCultivationSequences($data->e['structure']).'?success=sequence\\Sequence::deleted'));

new Page()
	->post('query', function($data) {

		$data->eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canManage');

		$search = new Search([
			'sequences' => POST('ids', 'array')
		]);

		$data->cActionMain = \work\ActionLib::getMainByStructure($data->eStructure);
		$data->cSequence = \sequence\CropLib::getFromQuery($data->eStructure, $data->cActionMain, POST('query'), $search);

		throw new \ViewAction($data);

	});
?>
