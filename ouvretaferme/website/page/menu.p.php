<?php
new \website\MenuPage(function($data) {
		$data->eWebsite = \website\WebsiteLib::getById(INPUT('website'));
	})
	->create(function($data) {

		$data->cWebpage = \website\WebpageLib::getByWebsiteForMenu($data->eWebsite);
		$data->for = GET('for', ['webpage', 'url', 'label'], fn() => throw new NotExpectedAction('For'));

		if(get_exists('parent')) {
			$data->eMenuParent = \website\MenuLib::getById(GET('parent', 'int'))->validateProperty('website', $data->eWebsite);
		} else {
			$data->eMenuParent = new \website\Menu();
		}

		throw new ViewAction($data);

	})
	->getCreateElement(function($data) {
		return new \website\Menu([
			'website' => $data->eWebsite,
			'structure' => $data->eWebsite['structure']
		]);
	})
	->doCreate(function($data) {
		throw new BackAction('website', 'Menu::created');
	});

new \website\MenuPage()
	->applyElement(function($data, \website\Menu $e) {

		$e['website'] = \website\WebsiteLib::getById($e['website']);

		$e->validate('canWrite');

	})
	->quick(['label'])
	->doDelete(function($data) {
		throw new ReloadAction('website', 'Menu::deleted');
	});

new \website\WebsitePage()
	->write('doUpdatePositions', function($data) {

		$positions = json_decode(POST('positions'), TRUE);

		if(is_array($positions) === FALSE) {
			throw new NotExpectedAction('Positions');
		}

		foreach($positions as $position) {

			if(
				is_array($position) === FALSE or
				array_key_exists('id', $position) === FALSE
			) {
				throw new NotExpectedAction('Positions');
			}

		}

		\website\MenuLib::updatePositions($data->e, $positions);

		throw new VoidAction();

	});
?>
