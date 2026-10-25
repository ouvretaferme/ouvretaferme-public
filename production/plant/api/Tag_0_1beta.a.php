<?php
namespace plant;

class Tag_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list('plantTagList');

		$this->read('plantTag/{id}');

		$this->element(
			'plant\Tag',
			fn() => [
				'id', 'fqn', 'usage', 'name'
			]
		);

	}

	public function getNewModel(\ApiData $data): TagModel {
		return new TagModel();
	}

	public function getNewElement(\ApiData $data): Tag {
		return new Tag();
	}

}
?>
