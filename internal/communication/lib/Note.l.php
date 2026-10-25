<?php
namespace communication;

/**
 * Les notes que l'équipe Ouvretaferme adresse aux administrateurs des structures, par le centre de
 * notifications.
 *
 * Les destinataires sont calculés une fois, à l'envoi, et figés dans `NoteRecipient` : une structure
 * qui change de type ou allume une fonctionnalité le lendemain ne reçoit pas après coup une note qui
 * ne lui était pas destinée, et les chiffres de la liste des envois restent ceux de l'envoi.
 *
 * Sans suffixe Ai : le cadriciel résout le Lib d'une entité par son nom (`ModuleLib::getLib()`).
 */
class NoteLib extends NoteCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['title', 'content', 'buttonLabel', 'buttonPath', 'structureTypes', 'features'];
	}

	/**
	 * La page du bouton est une adresse du site, jamais une adresse externe (décidé le
	 * 23/09/2026). « // » en tête serait lu par le navigateur comme un autre domaine, d'où le refus.
	 */
	public static function isPath(?string $buttonPath): bool {
		return ($buttonPath === NULL or preg_match('#^/(?!/)[^\s\\\\]*$#', $buttonPath) === 1);
	}

	/**
	 * La page du bouton chez un destinataire : le segment `x` des deux formes que résout
	 * `StructureSentinelAiLib` (/x/… et /ferme/x/…) prend l'id de la structure où il lit la note,
	 * pour qu'il y arrive sans passer par le choix d'une structure. Une adresse sans ce segment
	 * est la même pour tous.
	 *
	 * Une découpe et non un preg_replace, pour la même raison que `getUrlRequested()` : « $1 »
	 * suivi de l'id se lirait comme un autre groupe.
	 */
	public static function getButtonUrl(string $buttonPath, \structure\Structure $eStructure): string {

		$sentinel = \structure\StructureSentinelAiLib::SENTINEL;

		if(preg_match('#^/(ferme/)?'.$sentinel.'(/|$)#', $buttonPath, $match) === 0) {
			return $buttonPath;
		}

		// /x seul n'a pas de cible (cf. `StructureSentinelAiLib::getPrefix()`), /ferme/x si.
		if($match[1] === '' and $match[2] === '') {
			return $buttonPath;
		}

		$head = '/'.$match[1].$sentinel;

		return '/'.$match[1].$eStructure['id'].substr($buttonPath, strlen($head));

	}

	/**
	 * Le nombre de structures actives de chaque type, pour les cases du formulaire. Une seule requête
	 * groupée plutôt qu'un comptage par case.
	 */
	public static function countStructureTypes(): array {

		$counts = \structure\Structure::model()
			->select([
				'type', 'producerType', 'communityType',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->whereStatus(\structure\Structure::ACTIVE)
			->whereId('NOT IN', \structure\StructureSetting::FIXTURE_STRUCTURES)
			->group(['type', 'producerType', 'communityType'])
			->getCollection();

		$structureTypes = array_fill_keys(array_keys(CommunicationSetting::NOTE_STRUCTURE_TYPES), 0);

		foreach($counts as $eStructure) {

			foreach(CommunicationSetting::NOTE_STRUCTURE_TYPES as $key => [$type, $property, $value]) {

				if(
					$eStructure['type'] === $type and
					($property === NULL or $eStructure[$property] === $value)
				) {
					$structureTypes[$key] += $eStructure['count'];
				}

			}

		}

		return $structureTypes;

	}

	/**
	 * Les adhésions qui reçoivent la note : administrateurs actifs d'une structure active, dont le type
	 * est coché et qui utilise **toutes** les fonctionnalités cochées, puis dans une structure où les
	 * notifications sont ouvertes.
	 *
	 * L'aperçu du formulaire et l'envoi passent tous deux par ici : les chiffres qu'on lit avant
	 * d'envoyer sont ceux des lignes écrites.
	 *
	 * Le dernier filtre se fait en PHP parce que `NotificationSetting::isEnabled()` est une closure,
	 * posée par l'application et remplacée par les tests : il n'a pas d'équivalent SQL.
	 */
	public static function getRecipients(Note $eNote): \Collection {

		if($eNote['structureTypes'] === []) {
			return new \Collection();
		}

		$mStructure = \structure\Structure::model();

		$conditionsType = [];

		foreach($eNote['structureTypes'] as $key) {

			[$type, $property, $value] = CommunicationSetting::NOTE_STRUCTURE_TYPES[$key];

			$conditionsType[] = ($property === NULL) ?
				'm3.type = '.$mStructure->format($type) :
				'(m3.type = '.$mStructure->format($type).' AND m3.'.$property.' = '.$mStructure->format($value).')';

		}

		// Les noms de colonne viennent d'une liste fermée, que `Note::properties()` a déjà imposée.
		$conditionFeature = '';

		foreach($eNote['features'] as $feature) {
			$conditionFeature .= ' AND m4.'.$feature.' = 1';
		}

		return \structure\Member::model()
			->select(['id', 'user', 'structure'])
			->join(\structure\Role::model(), 'm1.role = m2.id AND m2.admin = 1')
			->join(\structure\Structure::model(), 'm1.structure = m3.id AND m3.status = '.$mStructure->format(\structure\Structure::ACTIVE).' AND ('.implode(' OR ', $conditionsType).')')
			->join(\structure\Feature::model(), 'm1.structure = m4.structure'.$conditionFeature)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m1.structure', 'NOT IN', \structure\StructureSetting::FIXTURE_STRUCTURES)
			->getCollection()
			->find(fn(\structure\Member $eMember) => \notification\NotificationSetting::isEnabled($eMember['structure']));

	}

	/**
	 * Ce que l'aperçu et la note affichent : les structures touchées, et les personnes, un
	 * administrateur de deux structures étant compté une fois.
	 */
	public static function countRecipients(\Collection $cMember): array {

		return [
			'structures' => count(array_unique($cMember->getColumnCollection('structure')->getIds())),
			'users' => count(array_unique($cMember->getColumnCollection('user')->getIds())),
		];

	}

	/**
	 * Envoie la note : une ligne par adhésion destinataire, et le compteur de chacune monté d'un cran,
	 * dans la même transaction, pour qu'une note écrite sans destinataires ou un compteur monté sans
	 * ligne ne survive pas à une erreur.
	 */
	public static function create(Note $e): void {

		$e->expects(['title', 'content', 'structureTypes', 'features']);

		$cMember = self::getRecipients($e);

		if($cMember->empty()) {
			Note::fail('recipients.empty');
			return;
		}

		$e['createdBy'] = \user\ConnectionLib::getOnline();
		$e->merge(self::countRecipients($cMember));

		Note::model()->beginTransaction();

			parent::create($e);

			NoteRecipientLib::createByNote($e, $cMember);

			\structure\MemberLib::addNotificationsUnseen($cMember);

		Note::model()->commit();

	}

	/**
	 * Tous les envois, du plus récent au plus ancien. Rien ne s'efface : les lignes des destinataires
	 * partent au bout de trente jours, la note reste.
	 */
	public static function getList(): \Collection {

		$cNote = Note::model()
			->select(Note::getSelection() + [
				'until' => new \Sql('DATE(createdAt + INTERVAL '.\notification\NotificationSetting::DAYS.' DAY)', 'date')
			])
			->sort(['createdAt' => SORT_DESC, 'id' => SORT_DESC])
			->getCollection();

		$counts = NoteRecipientLib::countByNote($cNote->find(fn(Note $eNote) => $eNote['reads'] === NULL));

		foreach($cNote as $eNote) {

			// Une note dont les lignes ont été effacées avant que `reads` soit figé à l'effacement
			// n'a plus de vue ni de lecture à compter : ses deux nombres restent inconnus
			if($eNote['reads'] === NULL) {
				$eNote['views'] = $counts[$eNote['id']]['views'] ?? NULL;
				$eNote['reads'] = $counts[$eNote['id']]['reads'] ?? NULL;
			}

		}

		return $cNote;

	}

	/**
	 * Fige le nombre de vues et de lectures des notes dont les lignes vont être effacées, avant
	 * qu'elles le soient : la liste des envois garde les chiffres après le départ de chez les membres.
	 */
	public static function freezeCounts(\Sql $date): void {

		$cNoteExpired = NoteRecipient::model()
			->select('note')
			->whereCreatedAt('<', $date)
			->group('note')
			->getColumn('note');

		if($cNoteExpired->empty()) {
			return;
		}

		$cNote = Note::model()
			->select('id')
			->whereId('IN', $cNoteExpired)
			->whereReads(NULL)
			->getCollection();

		$counts = NoteRecipientLib::countByNote($cNote);

		foreach($cNote as $eNote) {
			$eNote['views'] = $counts[$eNote['id']]['views'];
			$eNote['reads'] = $counts[$eNote['id']]['reads'];
			self::update($eNote, ['views', 'reads']);
		}

	}

}
?>
