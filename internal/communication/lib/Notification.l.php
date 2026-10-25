<?php
namespace communication;

/**
 * Les notes de l'équipe, vues du centre de notifications : une **source commune** du moteur
 * (`base/notification/lib/Notification.l.php`, en tête), et non un type.
 *
 * Elles vivent en base commune (`NoteRecipient`) et le moteur les fusionne à la lecture de chaque
 * structure. Une ligne rendue au moteur est un élément `\notification\Notification` construit ici,
 * jamais écrit : son `id` est celui de la ligne `NoteRecipient`, son `reference` celui de la note.
 *
 * Une note vue ou lue dans une structure l'est dans toutes celles où l'utilisateur la reçoit (N16) :
 * les écritures portent sur le couple (note, utilisateur), et rendent les structures touchées pour
 * que le moteur y recale le compteur.
 *
 * Ce fichier ne porte pas le suffixe Ai : son nom est celui que le moteur résout
 * (`base/notification/CLAUDE.md`).
 */
class NotificationLib {

	const string NOTE = 'communication-note';

	/**
	 * Aucun type : une note ne se règle ni ne se coupe, et ne passe pas par la file. Le moteur lit
	 * quand même cette méthode sur toute paire qu'il découvre.
	 */
	public static function getTypes(): array {
		return [];
	}

	public static function getGroupName(\notification\Notification $eNotification): ?string {
		return NULL;
	}

	public static function getAmount(\notification\Notification $eNotification): ?float {
		return NULL;
	}

	/**
	 * La page du bouton, et sans bouton le centre, où la note se lit en entier.
	 *
	 * L'aperçu du formulaire n'a pas de structure : il garde l'adresse telle que saisie, que la
	 * sentinelle résout au clic pour l'administrateur qui l'essaie.
	 */
	public static function getUrl(\notification\Notification $eNotification): string {

		$buttonPath = $eNotification['content']['buttonPath'];

		if($buttonPath === NULL) {
			return \notification\NotificationUi::url($eNotification['eStructure']);
		}

		if($eNotification['eStructure']->empty()) {
			return $buttonPath;
		}

		return NoteLib::getButtonUrl($buttonPath, $eNotification['eStructure']);

	}

	public static function getCommon(\structure\Structure $eStructure, \user\User $eUser): \Collection {

		$cNoteRecipient = NoteRecipient::model()
			->select(self::getSelection())
			->whereUser($eUser)
			->whereStructure($eStructure)
			->whereCreatedAt('>', self::getSince())
			->getCollection();

		$cNotification = new \Collection();

		foreach($cNoteRecipient as $eNoteRecipient) {
			$cNotification[] = self::toNotification($eNoteRecipient);
		}

		return $cNotification;

	}

	public static function getCommonById(\structure\Structure $eStructure, \user\User $eUser, int $id): \notification\Notification {

		$eNoteRecipient = NoteRecipient::model()
			->select(self::getSelection())
			->whereId($id)
			->whereUser($eUser)
			->whereStructure($eStructure)
			->whereCreatedAt('>', self::getSince())
			->get();

		if($eNoteRecipient->empty()) {
			return new \notification\Notification();
		}

		return self::toNotification($eNoteRecipient);

	}

	/**
	 * @return array [id d'utilisateur => nombre], les seuls utilisateurs qui en ont
	 */
	public static function countCommonUnseen(\structure\Structure $eStructure, array $users): array {

		$cNoteRecipient = NoteRecipient::model()
			->select([
				'user',
				'number' => new \Sql('COUNT(*)', 'int')
			])
			->whereUser('IN', $users)
			->whereStructure($eStructure)
			->whereSeenAt(NULL)
			->whereCreatedAt('>', self::getSince())
			->group('user')
			->getCollection();

		$unseen = [];

		foreach($cNoteRecipient as $eNoteRecipient) {
			$unseen[$eNoteRecipient['user']['id']] = $eNoteRecipient['number'];
		}

		return $unseen;

	}

	/**
	 * Marque vues les notes que l'utilisateur n'a pas encore vues dans cette structure, et avec elles
	 * les mêmes notes dans ses autres structures. Une note sans bouton est lue du même coup : elle n'a
	 * aucun geste qui la lirait (décidé le 24/09, « vue = lue »).
	 *
	 * @return array Les structures où une ligne a changé
	 */
	public static function seeCommon(\structure\Structure $eStructure, \user\User $eUser): array {

		$cNoteRecipient = NoteRecipient::model()
			->select([
				'note' => ['buttonLabel']
			])
			->whereUser($eUser)
			->whereStructure($eStructure)
			->whereSeenAt(NULL)
			->whereCreatedAt('>', self::getSince())
			->getCollection();

		if($cNoteRecipient->empty()) {
			return [];
		}

		$cNote = $cNoteRecipient->getColumnCollection('note');
		$cNoteWithoutButton = $cNote->find(fn(Note $eNote) => $eNote['buttonLabel'] === NULL);

		$structures = NoteRecipientLib::see($eUser, $cNote);

		if($cNoteWithoutButton->notEmpty()) {
			$structures = array_merge($structures, NoteRecipientLib::read($eUser, $cNoteWithoutButton));
		}

		return $structures;

	}

	/**
	 * Marque lue une note, ou toutes celles que l'utilisateur n'a pas lues dans cette structure quand
	 * la ligne est vide, dans toutes ses structures.
	 *
	 * @return array Les structures où une ligne a changé
	 */
	public static function readCommon(\structure\Structure $eStructure, \user\User $eUser, \notification\Notification $eNotification): array {

		$cNote = NoteRecipient::model()
			->select('note')
			->whereId($eNotification, if: $eNotification->notEmpty())
			->whereUser($eUser)
			->whereStructure($eStructure)
			->whereReadAt(NULL)
			->whereCreatedAt('>', self::getSince())
			->getColumn('note');

		if($cNote->empty()) {
			return [];
		}

		return NoteRecipientLib::read($eUser, $cNote);

	}

	/**
	 * Efface les lignes de plus de trente jours. La note reste : la liste des envois garde tout.
	 *
	 * @return array [id de structure => [id d'utilisateur…]] ceux qui perdent une ligne non vue
	 */
	public static function cleanCommon(): array {

		$cNoteRecipient = NoteRecipient::model()
			->select(['structure', 'user'])
			->whereCreatedAt('<', self::getSince())
			->whereSeenAt(NULL)
			->getCollection();

		$users = [];

		foreach($cNoteRecipient as $eNoteRecipient) {
			$users[$eNoteRecipient['structure']['id']][] = $eNoteRecipient['user']['id'];
		}

		NoteLib::freezeCounts(self::getSince());
		NoteRecipientLib::deleteBefore(self::getSince());

		return $users;

	}

	/**
	 * La note en cours de rédaction, sous la forme d'une ligne que le moteur sait rendre : l'aperçu
	 * du formulaire est ainsi le rendu même du centre. La ligne n'existe chez personne, d'où ni id ni
	 * structure, et elle se montre non vue, comme ses destinataires la découvriront.
	 */
	public static function getPreview(Note $eNote): \notification\Notification {

		return new \notification\Notification([
			'id' => NULL,
			'source' => __NAMESPACE__,
			'type' => self::NOTE,
			'reference' => NULL,
			'group' => NULL,
			'content' => self::getContent($eNote),
			'seenAt' => NULL,
			'readAt' => NULL,
			'createdAt' => \Clock::datetime(),
			'eStructure' => new \structure\Structure()
		]);

	}

	private static function getSelection(): array {

		return [
			'id', 'user', 'seenAt', 'readAt', 'createdAt',
			'note' => ['title', 'content', 'buttonLabel', 'buttonPath']
		];

	}

	private static function toNotification(NoteRecipient $eNoteRecipient): \notification\Notification {

		return new \notification\Notification([
			'id' => $eNoteRecipient['id'],
			'user' => $eNoteRecipient['user'],
			'type' => self::NOTE,
			'reference' => $eNoteRecipient['note']['id'],
			'group' => NULL,
			'content' => self::getContent($eNoteRecipient['note']),
			'seenAt' => $eNoteRecipient['seenAt'],
			'readAt' => $eNoteRecipient['readAt'],
			'createdAt' => $eNoteRecipient['createdAt']
		]);

	}

	private static function getContent(Note $eNote): array {

		return [
			'title' => $eNote['title'],
			'content' => $eNote['content'],
			'buttonLabel' => $eNote['buttonLabel'],
			'buttonPath' => $eNote['buttonPath']
		];

	}

	/** La même borne que le moteur (`NotificationLib::getSince()`), sur la même durée de vie. */
	private static function getSince(): \Sql {
		return new \Sql('NOW() - INTERVAL '.\notification\NotificationSetting::DAYS.' DAY');
	}

}
?>
