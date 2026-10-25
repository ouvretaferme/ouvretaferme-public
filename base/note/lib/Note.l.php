<?php
namespace note;

class NoteLib extends NoteCrud {

	/**
	 * L'ordre d'un onglet, épinglées d'abord, puis de la plus récemment modifiée à la plus ancienne :
	 * celui de la liste et celui du PDF d'un carnet.
	 */
	private const SORT = [
		'pinned' => SORT_DESC,
		'updatedAt' => SORT_DESC,
		'id' => SORT_DESC
	];

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['title', 'notebook', 'cTarget', 'content', 'readers'];
	}

	/**
	 * Les rattachements se changent dans leur propre panneau (`doUpdateTargets`), pas avec la note.
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['title', 'notebook', 'content', 'readers'];
	}

	/**
	 * Les notes de la structure que le membre connecté lit, épinglées d'abord, puis de la plus
	 * récemment modifiée à la plus ancienne. Chacune porte le début de son texte, `extract`, et sa
	 * première photo, `cover`.
	 *
	 * `notebook` absent de la recherche rend toutes les notes, un carnet vide celles de « Non classé ».
	 * `target`, un rattachement, ne garde que les notes qui y sont rattachées.
	 */
	public static function getByStructure(\structure\Structure $eStructure, \Search $search, int $page): \Collection {

		$cNote = self::applySearch(self::applyReadable(Note::model(), $eStructure->getMember()), $eStructure, $search)
			->select(Note::getSelection())
			->sort(self::SORT)
			->getCollection($page * NoteSetting::LIST_MAX, NoteSetting::LIST_MAX);

		foreach($cNote as $eNote) {
			$eNote['extract'] = self::getExtract($eNote['content']);
			$eNote['cover'] = self::getCover($eNote['content']);
		}

		TargetLib::putByNotes($eStructure, $cNote);

		return $cNote;

	}

	/**
	 * Les notes du carnet que ce membre lit, dans l'ordre de son onglet et sans pagination : celles
	 * qu'emporte le PDF du carnet. Le membre est nommé et non lu sur la connexion, la route distante
	 * qui rend le document n'ayant personne de connecté.
	 */
	public static function getByNotebook(Notebook $eNotebook, \structure\Member $eMember): \Collection {

		return self::applyReadable(Note::model(), $eMember)
			->select(Note::getSelection())
			->whereNotebook($eNotebook)
			->sort(self::SORT)
			->getCollection();

	}

	/**
	 * Les notes que le membre connecté lit et qui sont rattachées à l'un des objets de `$targets`
	 * (`[targetType => ids]`), dans l'ordre de la liste et au plus une page de la liste : le bloc Notes
	 * d'un écran de production (L84). Chacune ne garde que ces rattachements-là, qui disent par où elle
	 * arrive sur l'écran ; les autres se lisent dans la note.
	 *
	 * Rien pour une structure à qui les notes sont fermées : chacune de leurs routes se garde ainsi
	 * (`page/note.p.php`), et une carte n'y mènerait nulle part.
	 */
	public static function getByTargets(\structure\Structure $eStructure, array $targets): \Collection {

		$targets = array_filter($targets, fn(array $targetIds) => $targetIds !== []);

		if(
			$targets === [] or
			\dashboard\DashboardSetting::isLanding($eStructure) === FALSE
		) {
			return new \Collection();
		}

		$wheres = [];
		$values = [];

		foreach($targets as $targetType => $targetIds) {

			$wheres[] = fn() => $this
				->whereTargetType($targetType)
				->whereTargetId('IN', $targetIds);

			foreach($targetIds as $targetId) {
				$values[$targetType.':'.$targetId] = TRUE;
			}

		}

		// Le carnet se nomme sur la carte : hors de la liste, aucun onglet ne le dit.
		$properties = Note::getSelection();
		$properties['notebook'] = ['name'];

		$cNote = self::applyReadable(Note::model(), $eStructure->getMember())
			->select($properties)
			->whereId('IN', new TargetModel()
				->select('note')
				->whereStructure($eStructure)
				->or(...$wheres)
				->getSql())
			->sort(self::SORT)
			->getCollection(0, NoteSetting::LIST_MAX);

		foreach($cNote as $eNote) {

			$eNote['extract'] = self::getExtract($eNote['content']);
			$eNote['cover'] = self::getCover($eNote['content']);
			$eNote['cTarget'] = $eNote['cTarget']->find(fn(Target $eTarget) => array_key_exists($eTarget->getValue(), $values));

		}

		TargetLib::putByNotes($eStructure, $cNote);

		return $cNote;

	}

	/**
	 * Le nombre de notes que le membre connecté lit sous la recherche : celui de l'onglet ouvert et du
	 * rattachement choisi, que le comptage par carnet ne connaît pas.
	 */
	public static function countBySearch(\structure\Structure $eStructure, \Search $search): int {
		return self::applySearch(self::applyReadable(Note::model(), $eStructure->getMember()), $eStructure, $search)->count();
	}

	private static function applySearch(NoteModel $mNote, \structure\Structure $eStructure, \Search $search): NoteModel {

		if($search->has('notebook')) {
			$mNote->whereNotebook($search->get('notebook'));
		}

		if($search->has('target')) {

			$eTarget = $search->get('target');

			$mNote->whereId('IN', new TargetModel()
				->select('note')
				->whereStructure($eStructure)
				->whereTargetType($eTarget['targetType'])
				->whereTargetId($eTarget['targetId'])
				->getSql());

		}

		return $mNote;

	}

	/**
	 * Le nombre de notes que le membre connecté lit, par carnet, en une requête : la clé est l'id du
	 * carnet, et 0 pour « Non classé ». Les onglets et la pagination s'en servent tous les deux.
	 */
	public static function countByNotebook(\structure\Structure $eStructure): array {

		return self::applyReadable(Note::model(), $eStructure->getMember())
			->select([
				'notebook',
				'count' => new \Sql('COUNT(*)', 'int')
			])
			->group('notebook')
			->getCollection()
			->makeArray(function(Note $eNote, ?int &$key) {
				$key = $eNote['notebook']->empty() ? 0 : $eNote['notebook']['id'];
				return $eNote['count'];
			});

	}

	/**
	 * La condition de `Note::canRead()`, écrite pour la requête : un administrateur lit tout, un autre
	 * membre ce que toute l'équipe lit. Le membre est vérifié par
	 * la page : celui de la connexion (`Structure::getMember()`), ou le demandeur que nomme l'adresse
	 * signée d'un PDF. Qui n'est pas ou plus membre ne lit rien.
	 */
	public static function applyReadable(NoteModel $mNote, \structure\Member $eMember): NoteModel {

		if($eMember->empty()) {
			return $mNote->where('FALSE');
		}

		$mNote->whereStructure($eMember['structure']);

		if($eMember->isAdmin()) {
			return $mNote;
		}

		return $mNote->whereReaders(Note::TEAM);

	}

	/**
	 * Le texte de la note sans sa mise en forme, pour la carte. Chaque balise devient une espace, pour
	 * que deux paragraphes ne se collent pas en un seul mot. Les photos partent avec leur légende : la
	 * première est déjà la couverture de la carte, et sa légende ne se lit pas sans elle.
	 */
	private static function getExtract(?string $content): string {

		if($content === NULL) {
			return '';
		}

		$content = preg_replace('/<figure\b.*?<\/figure>/s', ' ', $content);

		$text = html_entity_decode(preg_replace('/<[^>]+>/', ' ', $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/\s+/u', ' ', $text);

		return mb_substr(trim($text), 0, NoteSetting::EXTRACT_LIMIT);

	}

	/**
	 * La première photo de la note, pour la couverture de sa carte, lue dans le contenu que la liste
	 * charge déjà : elle ne coûte aucune requête. Seule une photo téléversée dans l'éditeur porte un
	 * `xyz`, qui donne les tailles réduites, et `XmlLib::cleanImageNode()` ne l'écrit jamais sans sa
	 * version ; une image sans `xyz` ne couvre rien.
	 *
	 * @return ?array ['xyz' => string, 'xyzVersion' => int], NULL sans photo
	 */
	private static function getCover(?string $content): ?array {

		if(
			$content === NULL or
			preg_match('/<image\b[^>]* xyz="([a-z0-9]+)"[^>]* xyz-version="([0-9]+)"/', $content, $image) === 0
		) {
			return NULL;
		}

		return [
			'xyz' => $image[1],
			'xyzVersion' => (int)$image[2]
		];

	}

	public static function create(Note $e): void {

		$e->expects(['structure', 'cTarget']);

		Note::model()->beginTransaction();

			parent::create($e);
			self::synchronizeTargets($e);

		Note::model()->commit();

	}

	public static function update(Note $e, array $properties): void {

		// L'épingle range la note sans la modifier : seul ce que ses formulaires écrivent, la note et ses
		// rattachements, avance la date sur laquelle la liste se trie.
		if(array_intersect($properties, self::getPropertiesCreate()) !== []) {
			$e['updatedAt'] = new \Sql('NOW()');
			$properties[] = 'updatedAt';
		}

		// Les rattachements vivent dans leur table, pas dans une colonne de la note.
		$synchronizeTargets = in_array('cTarget', $properties);
		$properties = array_values(array_diff($properties, ['cTarget']));

		Note::model()->beginTransaction();

			parent::update($e, $properties);

			if($synchronizeTargets) {
				self::synchronizeTargets($e);
			}

		Note::model()->commit();

	}

	/**
	 * Aligne les lignes `Target` de la note sur `cTarget`, rangée par valeur. Une ligne dont l'objet a
	 * disparu n'a pas été reproposée au formulaire : elle part ici, au premier enregistrement qui suit.
	 */
	private static function synchronizeTargets(Note $e): void {

		$e->expects(['id', 'structure', 'cTarget']);

		$cTargetExisting = Target::model()
			->select(['id', 'targetType', 'targetId'])
			->whereNote($e)
			->getCollection();

		$values = [];

		foreach($cTargetExisting as $eTargetExisting) {

			$value = $eTargetExisting->getValue();
			$values[] = $value;

			if($e['cTarget']->offsetExists($value) === FALSE) {
				TargetLib::delete($eTargetExisting);
			}

		}

		foreach($e['cTarget'] as $value => $eTarget) {

			if(in_array($value, $values, TRUE) === FALSE) {

				$eTarget['note'] = $e;
				$eTarget['structure'] = $e['structure'];

				TargetLib::create($eTarget);

			}

		}

	}

	/**
	 * Range dans « Non classé » les notes d'un carnet qu'on supprime. L'écriture se fait en masse, en
	 * tableau, parce que toutes les notes du carnet changent d'un même geste, qui n'est pas une
	 * modification de leur contenu : leur `updatedAt`, sur lequel la liste se trie, ne bouge pas.
	 */
	public static function unclassifyByNotebook(Notebook $eNotebook): void {

		$eNotebook->expects(['structure']);

		Note::model()
			->whereStructure($eNotebook['structure'])
			->whereNotebook($eNotebook)
			->update([
				'notebook' => NULL
			]);

	}

	public static function delete(Note $e): void {

		$e->expects(['id']);

		Note::model()->beginTransaction();

			TargetLib::deleteByNote($e);

			parent::delete($e);

		Note::model()->commit();

	}

}
?>
