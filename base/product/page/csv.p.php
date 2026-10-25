<?php
new \structure\StructurePage()
	->read('exportProducts', function($data) {

		$data->e->validate('canAnalyzeProduction', 'canPersonalData');

		$export = \product\CsvLib::getExportProducts($data->e);
		array_unshift($export, new \product\CsvUi()->getExportProductsHeader($data->e));

		throw new CsvAction($export, 'produits.csv');

	});

new \structure\StructurePage()
	->read('importProducts', function($data) {

		$data->eStructure = $data->e;

		if(get_exists('reset')) {
			\product\CsvLib::resetProducts($data->eStructure);
		}

		$data->data = \product\CsvLib::getProducts($data->eStructure);

		throw new ViewAction($data, $data->data ? ':importProductFile' : NULL);

	}, validate: ['canManage', 'acceptImportExport'])
	->write('doImportProducts', function($data) {

		$fw = new FailWatch();

		\product\CsvLib::uploadProducts($data->e);

		if($fw->ok()) {
			throw new RedirectAction('/product/csv:importProducts?id='.$data->e['id']);
		} else {
			throw new RedirectAction('/product/csv:importProducts?id='.$data->e['id'].'&error='.$fw->getLast());
		}


	}, addValidate: ['canManage', 'acceptImportExport'])
	->write('doCreateProducts', function($data) {

		$data->data = \product\CsvLib::getProducts($data->e);

		if(
			$data->data === NULL or
			$data->data['errorsCount'] > 0
		) {
			throw new RedirectAction('/product/csv:importProducts?id='.$data->e['id']);
		}

		$fw = new FailWatch();

		\product\CsvLib::importProducts($data->e, $data->data['import']);

		$fw->validate();

		throw new RedirectAction('/product/csv:importProducts?id='.$data->e['id'].'&created');

	}, addValidate: ['canManage', 'acceptImportExport']);
?>
