<?php
namespace series;

class CommentLib extends CommentCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['task', 'text'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['text'];
	}

	public static function delegateByTask() {

		return Comment::model()
			->select(Comment::getSelection())
			->sort(['createdAt' => SORT_DESC])
			->delegateCollection('task');

	}

	public static function getByTask(Task $eTask): \Collection {

		return Comment::model()
			->select(Comment::getSelection())
			->whereTask($eTask)
			->sort(['createdAt' => SORT_DESC])
			->getCollection();

	}

	public static function create(Comment $e): void {

		$e->expects([
			'task' => ['structure', 'series', 'cultivation']
		]);

		$e['structure'] = $e['task']['structure'];
		$e['series'] = $e['task']['series'];
		$e['cultivation'] = $e['task']['cultivation'];

		parent::create($e);

	}

	public static function createForTasks(\Collection $cTask, Comment $e): void {

		$cTask->map(function(Task $eTask) use($e) {

			self::create((clone $e)->merge([
				'task' => $eTask
			]));

		});

	}

	public static function update(Comment $e, array $properties = []): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

}
