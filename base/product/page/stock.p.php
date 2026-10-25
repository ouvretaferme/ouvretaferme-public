<?php
new \product\ProductPage()
	->write('doEnableStock', function($data) {

		$fw = new FailWatch();

		\product\StockLib::enable($data->e);

		$fw->validate();

		throw new ReloadAction('product', 'Stock::enabled');

	}, validate: ['canStock', 'acceptEnableStock'])
	->write('doDisableStock', function($data) {

		$fw = new FailWatch();

		\product\StockLib::disable($data->e);

		$fw->validate();

		throw new ReloadAction('product', 'Stock::disabled');

	}, validate: ['canStock', 'acceptDisableStock']);

new Page()
	->get('add', function($data) {

		$data->eStructure = \structure\StructureLib::getById(INPUT('structure'))->validate('canWrite');

		throw new ViewAction($data);

	});

new \product\ConfigurationPage()
	->read('updateNote', function($data) {
		throw new ViewAction($data);
	})
	->doUpdateProperties('doUpdateNote', ['stockNotes'], function($data) {

		throw new ReloadAction();

	})
	->write('doNoteStatus', function($data) {

		\product\ConfigurationLib::updateStockNotesStatus($data->e, POST('enable', 'bool'));

		throw new ReloadAction();

	});

new \product\ProductPage()
	->read('update', function($data) {

		$data->cStock = \product\StockLib::getByProduct($data->e);

		throw new ViewAction($data);

	}, validate: ['canStock', 'acceptStock'])
	->read('increment', fn($data) => throw new ViewAction($data), validate: ['canStock', 'acceptStock'])
	->read('decrement', fn($data) => throw new ViewAction($data), validate: ['canStock', 'acceptStock'])
	->write('doUpdate', function($data) {

		$sign = post_exists('sign') ? POST('sign', ['+', '-'], fn() => throw new NotExpectedAction('Bad sign')) : NULL;

		$fw = new FailWatch();

		$eStock = new \product\Stock();
		$eStock->build(['quantity', 'comment'], $_POST);

		$fw->validate();

		if($sign === NULL) {
			\product\StockLib::set($data->e, $eStock['quantity'], $eStock);
		} else {

			if($eStock['quantity'] !== NULL) {

				match($sign) {
					'+' => \product\StockLib::increment($data->e, $eStock['quantity'], $eStock),
					'-' => \product\StockLib::decrement($data->e, $eStock['quantity'], $eStock),
				};

			} else {
				throw new FailAction('product\Stock::quantity.check');
			}

		}

		$fw->validate();

		throw new ReloadAction('product', 'Stock::updated');

	}, validate: ['canStock', 'acceptStock'])
	->read('bookmarks', function($data) {

		$data->cBookmark = \product\StockLib::getBookmarksByProduct($data->e);

		throw new ViewAction($data);

	}, validate: ['canStock'])
	->write('doDeleteBookmarks', function($data) {

		\product\StockBookmarkLib::deleteByProduct($data->e);

		throw new ReloadAction();

	}, validate: ['canStock']);

new \product\StockBookmarkPage()
	->doDelete(fn() => throw new ReloadLayerAction(), page: 'doDeleteBookmark');
?>
