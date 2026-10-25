<?php
namespace note;

class NotebookLib extends NotebookCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name'];
	}

	/**
	 * Les carnets de la structure, dans l'ordre de leurs onglets.
	 */
	public static function getByStructure(\structure\Structure $eStructure): \Collection {

		return Notebook::model()
			->select(Notebook::getSelection())
			->whereStructure($eStructure)
			->sort([
				'position' => SORT_ASC,
				'id' => SORT_ASC
			])
			->getCollection(index: 'id');

	}

	/**
	 * Un carnet neuf prend le dernier onglet.
	 */
	public static function create(Notebook $e): void {

		$e->expects(['structure']);

		Notebook::model()->beginTransaction();

			$e['position'] = Notebook::model()
				->whereStructure($e['structure'])
				->count() + 1;

			parent::create($e);

		Notebook::model()->commit();

	}

	/**
	 * Échange la place du carnet avec celle de son voisin, d'un onglet vers la gauche (-1) ou vers la
	 * droite (1). Le premier carnet ne recule pas, le dernier n'avance pas.
	 */
	public static function incrementPosition(Notebook $e, int $increment): void {

		$e->expects(['structure', 'position']);

		$eNotebookNeighbour = Notebook::model()
			->select('id')
			->whereStructure($e['structure'])
			->wherePosition($e['position'] + $increment)
			->get();

		if($eNotebookNeighbour->empty()) {
			return;
		}

		Notebook::model()->beginTransaction();

			$eNotebookNeighbour['position'] = $e['position'];
			self::update($eNotebookNeighbour, ['position']);

			$e['position'] += $increment;
			self::update($e, ['position']);

		Notebook::model()->commit();

	}

	/**
	 * Les notes du carnet passent dans « Non classé » avec lui, dans la même transaction : aucune note
	 * n'est effacée, et aucune ne reste rangée dans un carnet qui n'existe plus. Les carnets restants
	 * se renumérotent pour que `position`, un `int8`, ne grandisse pas au fil des créations et des
	 * suppressions.
	 */
	public static function delete(Notebook $e): void {

		$e->expects(['id', 'structure']);

		Notebook::model()->beginTransaction();

			NoteLib::unclassifyByNotebook($e);

			parent::delete($e);

			self::reorder($e['structure']);

		Notebook::model()->commit();

	}

	private static function reorder(\structure\Structure $eStructure): void {

		$position = 1;

		foreach(self::getByStructure($eStructure) as $eNotebook) {

			if($eNotebook['position'] !== $position) {
				$eNotebook['position'] = $position;
				self::update($eNotebook, ['position']);
			}

			$position++;

		}

	}

}
?>
