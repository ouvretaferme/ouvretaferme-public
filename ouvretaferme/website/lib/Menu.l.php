<?php
namespace website;

class MenuLib extends MenuCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['url', 'webpage', 'label', 'parent'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): \Closure {
		return function($e) {
			if($e['webpage']->notEmpty()) {
				return ['url', 'label'];
			} else {
				return ['label'];
			}
		};
	}

	public static function getByWebsite(Website $eWebsite, bool $onlyActive = TRUE): \Collection {

		if($onlyActive) {
			Menu::model()->whereStatus(Menu::ACTIVE);
		}

		$cMenu = Menu::model()
			->select(Menu::getSelection())
			->whereWebsite($eWebsite)
			->sort(new \Sql('IF(position IS NOT NULL, position, id) ASC'))
			->getCollection();

		return self::getTree($cMenu);

	}

	protected static function getTree(\Collection $cMenu): \Collection {

		$cMenuRoot = new \Collection();
		$cMenuChild = [];

		foreach($cMenu as $eMenu) {

			if($eMenu['parent']->empty()) {
				$eMenu['cMenuChild'] = new \Collection();
				$cMenuRoot[] = $eMenu;
			} else {
				$cMenuChild[$eMenu['parent']['id']][] = $eMenu;
			}

		}

		foreach($cMenuRoot as $eMenu) {

			foreach($cMenuChild[$eMenu['id']] ?? [] as $eMenuChildItem) {
				$eMenu['cMenuChild'][] = $eMenuChildItem;
			}

		}

		return $cMenuRoot;

	}

	public static function containsWebpage(\Collection $cMenu, Webpage $eWebpage): bool {

		if($eWebpage->empty()) {
			return FALSE;
		}

		foreach($cMenu as $eMenu) {

			$cMenuLevel = new \Collection([$eMenu]);
			$cMenuLevel->mergeCollection($eMenu['cMenuChild']);

			foreach($cMenuLevel as $eMenuLevel) {

				if(
					$eMenuLevel['webpage']->notEmpty() and
					$eMenuLevel['webpage']['id'] === $eWebpage['id']
				) {
					return TRUE;
				}

			}

		}

		return FALSE;

	}

	public static function create(Menu $e): void {

		$e->expects([
			'website' => ['structure'],
			'webpage',
			'url',
			'parent'
		]);

		if(
			$e['webpage']->notEmpty() and
			$e['url'] !== NULL
		) {
			Menu::fail('webpage.check');
			Menu::fail('url.check');
			return;
		}

		try {

			$e['structure'] = $e['website']['structure'];

			if($e['webpage']->notEmpty()) {
				$e['webpage']->expects(['status']);
				$e['status'] = $e['webpage']['status'];
			} else {
				$e['status'] = Menu::ACTIVE;
			}

			Menu::model()->insert($e);

		} catch(\DuplicateException $e) {

			switch($e->getInfo()['duplicate']) {

				case ['webpage'] :
					Menu::fail('webpage.duplicate');
					break;

			}

		}

	}

	public static function updatePositions(Website $eWebsite, array $positions): void {

		$cMenu = Menu::model()
			->select('id', 'parent')
			->whereWebsite($eWebsite)
			->getCollection(index: 'id');

		$parents = [];

		foreach($positions as $position) {

			$id = (int)$position['id'];

			if($cMenu->offsetExists($id) === FALSE) {
				Menu::fail('positions.incomplete');
				return;
			}

			$parents[$id] = ($position['parent'] ?? NULL) === NULL ? NULL : (int)$position['parent'];

		}

		if(count($parents) !== $cMenu->count()) {
			Menu::fail('positions.incomplete');
			return;
		}

		foreach($parents as $parent) {

			if(
				$parent !== NULL and
				(
					array_key_exists($parent, $parents) === FALSE or
					$parents[$parent] !== NULL
				)
			) {
				Menu::fail('positions.depth');
				return;
			}

		}

		Menu::model()->beginTransaction();

		$position = 0;

		foreach($positions as $item) {

			$position++;

			Menu::model()
				->whereWebsite($eWebsite)
				->whereId((int)$item['id'])
				->update([
					'parent' => $parents[(int)$item['id']],
					'position' => $position
				]);

		}

		Menu::model()->commit();

	}

	public static function delete(Menu $e): void {

		$e->expects(['id']);

		Menu::model()->beginTransaction();

		$cMenuChild = Menu::model()
			->select('id')
			->whereParent($e)
			->getCollection();

			foreach($cMenuChild as $eMenuChild) {
				self::delete($eMenuChild);
			}

			Menu::model()->delete($e);

		Menu::model()->commit();

	}

}
?>
