<?php
namespace series;

class Comment extends CommentElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'createdBy' => ['firstName', 'lastName', 'visibility', 'vignette'],
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canPlanningLimited();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('task')
			->addAfterCheck('owned', function(Task $eTask): bool {

				$this->expects(['structure']);

				return Task::model()
					->select('structure', 'series', 'cultivation')
					->whereStructure($this['structure'])
					->get($eTask);

			});

	}

}
?>