<?php
namespace communication;

/**
 * Une ligne par (note, utilisateur, structure) : ce que le centre de notifications d'un membre lit
 * de la note, et où il en marque la vue et la lecture.
 *
 * Sans suffixe Ai : le cadriciel résout le Lib d'une entité par son nom (`ModuleLib::getLib()`).
 */
class NoteRecipientLib extends NoteRecipientCrud {

	public static function createByNote(Note $eNote, \Collection $cMember): void {

		foreach($cMember as $eMember) {

			self::create(new NoteRecipient([
				'note' => $eNote,
				'user' => $eMember['user'],
				'structure' => $eMember['structure'],
			]));

		}

	}

	/*
	 * Écritures de masse, avec un tableau de valeurs, comme celles des notifications
	 * (`notification\NotificationLib::readAll()`, forme retenue le 2026-09-19) : une ligne
	 * n'a aucune règle par élément à rejouer, et une administratrice de nombreuses structures en
	 * touche une par structure. Un élément porteur d'un id réduirait l'écriture à sa seule ligne.
	 */

	/**
	 * @return array Les structures où une ligne a changé
	 */
	public static function see(\user\User $eUser, \Collection $cNote): array {

		$structures = self::getStructures($eUser, $cNote, 'seenAt');

		NoteRecipient::model()
			->whereUser($eUser)
			->whereNote('IN', $cNote)
			->whereSeenAt(NULL)
			->update([
				'seenAt' => new \Sql('NOW()')
			]);

		return $structures;

	}

	/**
	 * Lue vaut vue, sans écraser la date d'une vue antérieure.
	 *
	 * @return array Les structures où une ligne a changé
	 */
	public static function read(\user\User $eUser, \Collection $cNote): array {

		$structures = self::getStructures($eUser, $cNote, 'readAt');

		NoteRecipient::model()
			->whereUser($eUser)
			->whereNote('IN', $cNote)
			->whereReadAt(NULL)
			->update([
				'readAt' => new \Sql('NOW()'),
				'seenAt' => new \Sql('COALESCE(seenAt, NOW())')
			]);

		return $structures;

	}

	/**
	 * Un administrateur compte une fois, comme dans `Note::users`, même s'il a vu ou lu la note dans
	 * plusieurs de ses structures. Une note dont les lignes sont effacées est absente du tableau.
	 *
	 * @return array [id de note => ['views' => administrateurs qui l'ont vue, 'reads' => qui l'ont lue]]
	 */
	public static function countByNote(\Collection $cNote): array {

		$cNoteRecipient = NoteRecipient::model()
			->select([
				'note',
				'views' => new \Sql('COUNT(DISTINCT IF(seenAt IS NOT NULL, user, NULL))', 'int'),
				'reads' => new \Sql('COUNT(DISTINCT IF(readAt IS NOT NULL, user, NULL))', 'int')
			])
			->whereNote('IN', $cNote)
			->group('note')
			->getCollection();

		$counts = [];

		foreach($cNoteRecipient as $eNoteRecipient) {
			$counts[$eNoteRecipient['note']['id']] = [
				'views' => $eNoteRecipient['views'],
				'reads' => $eNoteRecipient['reads']
			];
		}

		return $counts;

	}

	public static function deleteBefore(\Sql $date): void {

		NoteRecipient::model()
			->whereCreatedAt('<', $date)
			->delete();

	}

	private static function getStructures(\user\User $eUser, \Collection $cNote, string $property): array {

		return NoteRecipient::model()
			->select('structure')
			->whereUser($eUser)
			->whereNote('IN', $cNote)
			->where($property, NULL)
			->getColumn('structure')
			->getIds();

	}

}
?>
