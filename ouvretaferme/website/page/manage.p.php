<?php
new Page(function($data) {

		\structure\StructureLib::load($data, parameterName: 'id')->validate('canCommunication');

	})
	->get('index', function($data) {

		$data->eWebsite = \website\WebsiteLib::getByStructure($data->eStructure);

		if($data->eWebsite->notEmpty()) {
			$data->cWebpage = \website\WebpageLib::getByWebsite($data->eWebsite);
			$data->cMenu = \website\MenuLib::getByWebsite($data->eWebsite, onlyActive: FALSE);
			[$data->cNews] = \website\NewsLib::getByWebsite($data->eWebsite, onlyPublished: FALSE);
			$data->eWebsite['cDesign'] = \website\DesignLib::getAll();
			$data->eWebsite['customizable'] = $data->eStructure->acceptFeature(\structure\Structure::FEATURE_DESIGN);

			if($data->eWebsite['cDesign']->offsetExists($data->eWebsite['customDesign']['id'])) {
				$data->eWebsite['customDesign'] = $data->eWebsite['cDesign'][$data->eWebsite['customDesign']['id']];
			}
		}

		throw new DynamicAction($data);

	});

new \website\WebsitePage()
	->getCreateElement(function($data) {

		$eStructure = \structure\StructureLib::getById(INPUT('structure'));

		return new \website\Website([
			'structure' => $eStructure,
		]);

	})
	->doCreate(fn($data) => throw new ReloadAction('website', 'Website::created'));

new \website\WebsitePage()
	->update()
	->read('contact', fn($data) => throw new ViewAction($data))
	->read('newsletter', function($data) {

		if(\structure\StructureSetting::hasMailPackage() === FALSE) {
			throw new NotExistsAction();
		}

		throw new ViewAction($data);

	})
	->read('qrCode', fn($data) => throw new ViewAction($data), validate: ['canWrite'])
	->read('downloadQrCode', function($data) {

		$url = \website\WebsiteUi::url($data->e);
		$format = GET('format', ['png', 'svg'], 'png');

		match($format) {
			'png' => throw new DataAction(\util\QrCodeLib::getPng($url), 'image/png', 'qrcode-'.$data->e['internalDomain'].'.png'),
			'svg' => throw new DataAction(\util\QrCodeLib::getSvg($url), 'image/svg+xml', 'qrcode-'.$data->e['internalDomain'].'.svg')
		};

	}, validate: ['canWrite'])
	->doUpdate(fn($data) => throw new BackAction('website', 'Website::updated'))
	->doUpdateProperties('doUpdateStatus', ['status'], fn() => throw new ReloadAction())
	->doUpdateProperties('doUpdateDomainTry', ['domainTry'], fn() => throw new ReloadAction('website', 'Website::domainRetry'))
	->doDelete(fn($data) => throw new ReloadAction('website', 'Website::deleted'));
?>
