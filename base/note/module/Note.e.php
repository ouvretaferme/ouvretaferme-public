<?php
namespace note;

/**
 * Une note de la structure : écrite par ses administrateurs, lue par eux seuls ou par toute l'équipe,
 * selon `readers` (L47).
 *
 * La lecture est réservée aux membres de la structure : ni l'école ni les groupes qui observent la
 * production n'y entrent, contrairement à `Structure::canRead()`. Un administrateur lit toutes les
 * notes, pour que le départ de celui qui l'a écrite n'en laisse aucune que personne ne peut ouvrir.
 */
class Note extends NoteElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'cTarget' => TargetLib::delegateByNote()
		];

	}

	public function canRead(): bool {

		$this->expects(['structure', 'readers']);

		if($this['structure']->isMember() === FALSE) {
			return FALSE;
		}

		if($this['structure']->canManage()) {
			return TRUE;
		}

		return match($this['readers']) {
			Note::ADMIN => FALSE,
			Note::TEAM => TRUE,
		};

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * La ferme école se lit et ne s'écrit pas. La règle ne monte pas dans `acceptWrite()` : une note
	 * ne peut exister que dans une structure qui l'a acceptée à la création.
	 */
	public function acceptCreate(): bool {

		$this->expects(['structure']);
		return $this['structure']->acceptWrite();

	}

	public static function properties(\PropertyList $l): void {

		$l->property('content')
			->replaceCast(function(mixed &$value): bool {
				Note::model()->cast('content', $value);
				$value = new \editor\XmlLib()->fromHtml($value, NoteSetting::getEditorOptions());
				return TRUE;
			});

		// Un id de carnet se poste comme un autre : il doit être un carnet de la structure de la note.
		// Le carnet vide, « Non classé », est toujours permis.
		$l->property('notebook')
			->addAfterCheck('structure', function(Notebook $eNotebook): bool {

				$this->expects(['structure']);

				if($eNotebook->empty()) {
					return TRUE;
				}

				return Notebook::model()
					->whereId($eNotebook)
					->whereStructure($this['structure'])
					->exists();

			});

		// Les rattachements arrivent de l'omnibox du formulaire, une valeur `<type>:<id>` par jeton, et ne
		// nomment que ce que le package du type rend pour la structure de la note. Une valeur qu'il ne rend
		// pas, objet d'une autre structure ou supprimé entre-temps, refuse tout le choix.
		$l->property('cTarget')
			->replaceCast(function(mixed &$value): bool {

				$this->expects(['structure']);

				$values = is_array($value) ? array_values(array_unique($value, SORT_REGULAR)) : [];
				$value = TargetLib::getByValues($this['structure'], $values);

				return ($value->count() === count($values));

			})
			->addAfterCheck('max', fn(\Collection $cTarget): bool => ($cTarget->count() <= NoteSetting::TARGETS_MAX))
			->replaceSet(function(\Collection $cTarget): void {
				$this['cTarget'] = $cTarget;
			});

	}

}
?>
