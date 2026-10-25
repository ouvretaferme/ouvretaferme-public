<?php
namespace gallery;

class Photo extends PhotoElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => ['logo', 'name'],
			'sequence' => ['name', 'mode', 'status', 'structure'],
			'series' => ['name', 'mode'],
			'author' => ['firstName', 'lastName', 'visibility', 'email', 'vignette']
		];

	}

	public function canRead(): bool {

		$this->expects([
			'structure',
			'sequence'
		]);

		return (
			$this['structure']->canWrite() or
			($this['sequence']->empty() === FALSE and $this['sequence']['structure']->canWrite())
		);

	}

	// Géré dans le build()
	public function canCreate(): bool {
		return TRUE;
	}

	public function canWrite(): bool {

		$this->expects([
			'structure'
		]);

		$eUserOnline = \user\ConnectionLib::getOnline();

		if($eUserOnline->empty()) {
			return FALSE;
		}

		return (
			$this['author']['id'] === $eUserOnline['id'] or // L'utilisateur est l'auteur de la photo
			$this['structure']->canManage()
		);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('structure')
			// replaceCheck et non addAfterCheck : la structure arrive vide quand la photo est
			// rattachée à une série, une intervention ou un itinéraire, et PhotoLib la déduit
			// après le build ; le contrôle du schéma refuserait l'élément vide avant lui.
			->replaceCheck(function(\structure\Structure $eStructure): bool {

				if($eStructure->empty()) {
					return TRUE;
				}

                $eStructure = \structure\StructureLib::getById($eStructure);

				return $eStructure->canWrite();

			});

		$l->property('sequence')
			->replaceCheck(function(\sequence\Sequence $eSequence): bool {

				if($eSequence->empty()) {
					return TRUE;
				}

				if(\sequence\Sequence::model()
					->select('id', 'structure')
					->get($eSequence) === FALSE) {
					return FALSE;
				}

				return $eSequence->canWrite();

			});

		$l->property('series')
			->replaceCheck(function(\series\Series $eSeries): bool {

				if($eSeries->empty()) {
					return TRUE;
				}

				if(\series\Series::model()
					->select('id', 'structure')
					->get($eSeries) === FALSE) {
					return FALSE;
				}

				return $eSeries->canWrite();

			});

		$l->property('task')
			->replaceCheck(function(\series\Task $eTask): bool {

				if($eTask->empty()) {
					return TRUE;
				}

				$this->expects(['series']);

				if(
					\series\Task::model()
						->select('id', 'structure', 'series')
						->get($eTask) === FALSE
				) {
					return FALSE;
				}

				return $eTask->canWrite();

			});

	}

}
?>