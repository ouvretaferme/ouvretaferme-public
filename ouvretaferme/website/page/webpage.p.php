<?php
new \website\WebpagePage(function($data) {
		$data->eWebsite = \website\WebsiteLib::getById(INPUT('website'));
	})
	->create(fn($data) => throw new ViewAction($data))
	->getCreateElement(function($data) {

		return new \website\Webpage([
			'website' => $data->eWebsite,
			'structure' => $data->eWebsite['structure'],
			'template' => \website\TemplateLib::getByFqn('open')
		]);

	})
	->doCreate(function($data) {
		throw new RedirectAction('/website/manage?id='.$data->eWebsite['structure']['id'].'&success=website\\Webpage::created');
	});

new \website\WebpagePage()
	->applyElement(function($data, \website\Webpage $e) {

		$e['website'] = \website\WebsiteLib::getById($e['website']);

		$e->validate('canWrite');

	})
	->update()
	->doUpdate(function($data) {
		throw new ReloadAction('website', 'Webpage::updated');
	})
	->doUpdateProperties('doUpdateStatus', ['status'], fn() => throw new ReloadAction())
	->read('updateContent', function($data) {

		$data->eStructure = \structure\StructureLib::getById($data->e['website']['structure'])->validate('canManage');

		throw new DynamicAction($data);

	})
	->doUpdateProperties('doUpdateContent', ['content'], fn($data) => throw new ViewAction($data))
	->doDelete(function($data) {
		throw new ReloadAction('website', 'Webpage::deleted');
	});
?>
