<?php
namespace website;

class Menu extends MenuElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'webpage' => ['url']
		];

	}

	public function canRead(): bool {

		$this->expects(['structure']);

		return $this['structure']->canCommunication();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);

		return $this['structure']->canCommunication();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('webpage')
			->replaceCheck(function(Webpage $eWebpage): bool {

				if($eWebpage->empty()) {
					return TRUE;
				}

				return (
					Webpage::model()
						->select('structure', 'status')
						->get($eWebpage) and
					$eWebpage->canWrite()
				);

			});

		$l->property('parent')
			->replaceCheck(function(Menu $eMenuParent): bool {

				if($eMenuParent->empty()) {
					return TRUE;
				}

				return Menu::model()
					->whereWebsite($this['website'])
					->whereParent(NULL)
					->exists($eMenuParent);

			});

	}

}
?>