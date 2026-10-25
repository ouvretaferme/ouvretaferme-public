<?php
namespace work;

class Action extends ActionElement {

	public function isProtected(): bool {

		$this->expects(['fqn']);

		return (
			$this['fqn'] !== NULL and
			ctype_digit($this['fqn']) === FALSE
		);
	}

	public function isFree(): bool {
		return $this->isProtected() === FALSE;
	}

	public function hasColor(): bool {

		$this->expects(['color']);

		return $this['color'] !== new ActionModel()->getDefaultValue('color');

	}

	public function canRead(): bool {
		return $this->canWrite();
	}

	public function canWrite(): bool {

		$this->expects(['structure']);

		return (
			\user\ConnectionLib::getOnline()->isAdmin() or
			(
				$this['structure']->empty() === FALSE and
				$this['structure']->canManage()
			)
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('categories')
			->addAfterCast('known', function(array &$categories): bool {

				$this->expects(['structure']);

				array_walk($categories, fn(&$value) => $value = (int)$value);

				$this['cCategory'] = Category::model()
					->select('id', 'fqn')
					->whereStructure($this['structure'])
					->whereId('IN', $categories)
					->getCollection();

				return (
					$categories !== [] and
					$this['cCategory']->count() === count($categories)
				);

			});

		$l->property('color')
			->addAfterCast('uppercase', function(?string &$color): bool {

				if($color === '') {
					$color = new ActionModel()->getDefaultValue('color');
				}

				$color = strtoupper($color);

				return TRUE;

			});

		$l->property('pace')
			->addAfterCast('category', function(?string &$pace, \PropertyList $l): bool {

				if(
					$l->isInvalid('categories') or
					$this['cCategory']->contains(fn($eCategory) => $eCategory['fqn'] === CATEGORIE_CULTURE) === FALSE
				) {
					$pace = NULL;
				}

				return TRUE;

			});

		$l->property('soil')
			->addAfterCast('category', function(?bool &$soil, \PropertyList $l): bool {

				if(
					$l->isBuilt('categories') === FALSE or
					$this['cCategory']->contains(fn($eCategory) => $eCategory['fqn'] === CATEGORIE_CULTURE) === FALSE
				) {
					$soil = FALSE;
				}

				if($l->for === 'update') {
					$this->expects(['soil']);
					$this['oldSoil'] = $this['soil'];
				}

				return TRUE;

			});

	}

}
?>
