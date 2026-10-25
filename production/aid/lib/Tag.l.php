<?php
namespace aid;

class TagLib extends TagCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['fqn', 'name', 'projects'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name', 'projects'];
	}

	public static function getByAid(Aid $eAid): \Collection {

		return Tag::model()
			->select(Tag::getSelection())
			->whereId('IN', new AidTagModel()->select('tag')->whereAid($eAid)->getSql())
			->sort(['position' => SORT_ASC])
			->getCollection(index: 'id');

	}

	// Un tag neuf se range après les autres, l'ordre se reprend dans l'admin.
	public static function create(Tag $e): void {

		$e['position'] = Tag::model()->count();

		parent::create($e);

	}

}
?>
