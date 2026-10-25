<?php
namespace series;

class Comment_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'taskCommentList',
			[
				'?task' => Comment::getValidator('task')
			]
		);

		$this->create('seriesComment');
		$this->read('seriesComment/{id}');
		$this->update('seriesComment/{id}');
		$this->delete('seriesComment/{id}');

		$this->element(
			'series\Comment',
			fn() => [
				'id', 'task',
				'text',
				'createdAt', 'createdBy', 'updatedAt'
			]
		);

	}

	public function getList(\ApiData $data): CommentModel {

		return $this->getNewModel($data)
			->whereTask(fn() => $data->get('task'), if: $data->exists('task'));

	}

	public function getNewModel(\ApiData $data): CommentModel {

		return new \series\CommentModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Comment {

		return new \series\Comment([
			'structure' => $data->eStructure
		]);

	}

}
?>
