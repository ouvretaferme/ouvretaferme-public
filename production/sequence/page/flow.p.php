<?php
$do = function($data) {

	$data->cFlow = \sequence\FlowLib::getBySequence($data->eSequence);
	$data->events = \sequence\FlowLib::reorder($data->eSequence, $data->cFlow);

	throw new ViewAction($data);

};

new \sequence\FlowPage(function($data) {

		\user\ConnectionLib::checkLogged();

		$sequence = INPUT('sequence', '?int');

		$data->eSequence = \sequence\SequenceLib::getById($sequence)->validate('canWrite');

		$data->cAction = \work\ActionLib::getByStructure($data->eSequence['structure'], category: CATEGORIE_CULTURE);

	})
	->create()
	->getCreateElement(function($data) {

		$e = new \sequence\Flow([
			'sequence' => $data->eSequence,
			'structure' => $data->eSequence['structure']
		]);

		return $e;

	})
	->doCreate(fn() => throw new ReloadAction());

new \sequence\FlowPage()
	->applyElement(function($data, \sequence\Flow $e) {

		$e['sequence'] = \sequence\SequenceLib::getById($e['sequence'])->validate('canWrite');

		$data->eSequence = $e['sequence'];

	})
	->update(function($data) {

		$data->e['hasMethods'] = \work\MethodLib::getForWork($data->e['sequence']['structure'], $data->e['action']);
		$data->e['hasTools'] = \work\ToolLib::getForWork($data->e['sequence']['structure'], $data->e['action'], $data->e['tools']);
		$data->e['hasProducts'] = \work\ToolLib::getProductsForWork($data->e['sequence']['structure'], $data->e['action'], $data->e['products']);

		$data->cAction = \work\ActionLib::getByStructure($data->e['sequence']['structure'], category: CATEGORIE_CULTURE);

		throw new ViewAction($data);

	})
	->doUpdate($do)
	->write('doPosition', function($data) {

		$fw = new FailWatch();

		\sequence\FlowLib::updatePosition($data->e['sequence'], POST('positions', 'json', []));

		$fw->validate();

		throw new ReloadAction();

	})
	->write('doIncrementWeek', function($data) use($do) {

		$increment = POST('increment', 'int');
		\sequence\FlowLib::incrementWeek($data->e, $increment);

		$do($data);

	})
	->write('doIncrementWeekPlan', function($data) {

		$fw = new FailWatch();

		$increment = POST('increment', 'int');

		if($increment < -26 or $increment > 26) {
			\Fail::log('Flow::increment.check');
		} else if(POST('mode', ['only', 'following'], 'only') === 'following') {
			\sequence\FlowLib::incrementWeekFollowing($data->e, $increment);
		} else {
			\sequence\FlowLib::incrementWeek($data->e, $increment);
		}

		$fw->validate();

		throw new ReloadAction();

	})
	->write('doIncrementWeekBound', function($data) {

		$fw = new FailWatch();

		$increment = POST('increment', 'int');

		if($increment < -26 or $increment > 26) {
			\Fail::log('Flow::increment.check');
		} else {
			\sequence\FlowLib::incrementWeekBound($data->e, POST('bound', ['start', 'stop']), $increment);
		}

		$fw->validate();

		throw new ReloadAction();

	})
	->doDelete($do);

new Page()
	->post('getFields', function($data) {

		$eStructure = \structure\StructureLib::getById(POST('structure'))->validate('canWrite');
		$eAction = \work\ActionLib::getById(POST('action'))->validate('canRead');

		// Le formulaire « Modifier » poste l'itinéraire : revenir à son action retrouve ce qu'il porte
		// en base, que les champs redessinés pour une autre action n'ont pas gardé.
		$eFlowSaved = \sequence\FlowLib::getById(POST('id'));

		if($eFlowSaved->notEmpty()) {
			$eFlowSaved
				->validate('canWrite')
				->validateProperty('structure', $eStructure);
		}

		if($eFlowSaved->notEmpty() and $eFlowSaved['action']->is($eAction)) {
			$values = $eFlowSaved->extracts(['tools', 'products', 'methods']);
		} else {
			$values = [
				'tools' => [],
				'products' => [],
				'methods' => []
			];
		}

		$data->eFlow = new \sequence\Flow([
			'sequence' => new \sequence\Sequence([
				'structure' => $eStructure
			]),
			'action' => $eAction,
			'cTool?' => fn() => \work\ToolLib::askCollection($values['tools'], $eStructure),
			'hasTools' => \work\ToolLib::getForWork($eStructure, $eAction, $values['tools']),
			'cProduct?' => fn() => \work\ToolLib::askCollection($values['products'], $eStructure),
			'hasProducts' => \work\ToolLib::getProductsForWork($eStructure, $eAction, $values['products']),
			'cMethod?' => fn() => \work\MethodLib::askCollection($values['methods'], $eStructure),
			'hasMethods' => \work\MethodLib::getForWork($eStructure, $eAction)
		] + $values);

		throw new \ViewAction($data);

	});


(new Page(function($data) {

		$data->c = \sequence\FlowLib::getByIds(REQUEST('ids', 'array'), properties: \sequence\Flow::getSelection() + [
			'sequence' => ['cycle'],
			'plant' => ['name']
		]);

		\sequence\Flow::validateBatch($data->c);

		$data->eStructure = $data->c->first()['structure'];

	}))
	->get('incrementWeekCollection', function($data) {

		throw new ViewAction($data);

	})
	->post('doIncrementWeekCollection', function($data) {

		$fw = new FailWatch();

		\sequence\FlowLib::incrementWeekCollection($data->c, POST('increment', 'int'));

		$fw->validate();

		throw new ReloadAction();

	})
	->post('doDeleteCollection', function($data) {

		\sequence\FlowLib::deleteCollection($data->c);

		throw new ReloadAction();

	});
?>
