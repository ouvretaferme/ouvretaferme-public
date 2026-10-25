<?php
namespace note;

/**
 * Un carnet, qui range les notes de la structure en onglets. Il se lit comme la structure et s'écrit
 * comme une note.
 */
class Notebook extends NotebookElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->isMember();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * La ferme école se lit et ne s'écrit pas, comme ses notes (`Note::acceptCreate()`).
	 */
	public function acceptCreate(): bool {

		$this->expects(['structure']);
		return $this['structure']->acceptWrite();

	}

	public static function properties(\PropertyList $l): void {

		// L'unicité `["structure", "name"]` du schéma, dite au champ plutôt qu'en erreur de base.
		$l->property('name')
			->addAfterCheck('duplicate', function(string $name, \PropertyList $l): bool {

				$this->expects(['structure']);

				$mNotebook = Notebook::model()
					->whereStructure($this['structure'])
					->whereName($name);

				if($l->for === 'update') {
					$mNotebook->whereId('!=', $this['id']);
				}

				return $mNotebook->exists() === FALSE;

			});

	}

}
?>
