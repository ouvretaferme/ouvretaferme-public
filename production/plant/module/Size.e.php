<?php
namespace plant;

class Size extends SizeElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'plant' => ['fqn', 'name'],
			'structure' => ['name']
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);

		return $this['structure']->canWrite();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);

		return (
			$this['structure']->notEmpty() and
			$this['structure']->canManage()
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('structure')
			->addAfterCheck('manage', function(\structure\Structure $eStructure): bool {

				return (
					(\user\ConnectionLib::getOnline()->isAdmin() and $eStructure->empty()) or
					$eStructure->canManage()
				);

			});

		$l->property('plant')
			->replaceCheck(function(Plant $ePlant): bool {

				$this->expects(['structure']);

				return Plant::model()
					->whereStructure($this['structure'])
					->exists($ePlant);

			});

	}

}
?>
