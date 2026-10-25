<?php
namespace product;

class Unit extends UnitElement {

	public function canRead(): bool {

		$this->expects(['structure']);

		return (
			$this['structure']->empty() or
			$this['structure']->canWrite()
		);

	}

	public function canWrite(): bool {

		$this->expects(['structure']);

		return $this['structure']->canManage();

	}

	public function acceptUpdate(): bool {
		return ($this['fqn'] === NULL);
	}

	public function acceptDelete(): bool {
		return ($this['fqn'] === NULL);
	}

	public function isInteger(): bool {

		return (
			$this->empty() or
			$this['type'] === Unit::INTEGER
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('singular')
			->addAfterCheck('duplicate', function(string $name): bool {

				return Unit::model()
					->whereId('!=', fn() => $this['id'], if: $this->exists())
					->whereStructure($this['structure'])
					->whereSingular($name)
					->exists() === FALSE;

			});

	}

}
?>