<?php
namespace block;

/**
 * Les blocs d'un objet, rangés par `position` à partir de 0, sans trou, dans chaque fratrie : le
 * premier niveau d'un côté, les enfants de chaque parent de l'autre (`owner`, `reference`, `parent`).
 * Toute écriture date l'objet qui les porte par le `touch()` de son usage : une leçon dont on déplace
 * un bloc a changé.
 *
 * L'arbre se monte ici et nulle part ailleurs : un bloc lu porte ses enfants dans `cBlock`, et
 * l'interface ne voit jamais la liste à plat.
 */
class BlockLib extends BlockCrud {

	/**
	 * Le type seul. Un bloc naît d'un bloc à écrire, avec ce qui y est écrit : la page y ajoute les
	 * propriétés que déclare le type posté (`block.p.php`), une fois le type admis par l'usage.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['type'];
	}

	/**
	 * Ce qu'un bloc construit à sa création, pour le type posté : le type et ce qu'il écrit, s'il est
	 * admis à cette place, sinon le type seul, que `Block::type.allowed` refuse alors au lieu du `match`
	 * de `BlockTypeLib`, qui lèverait. La place est celle de `Block::type.allowed` : sous un parent, ce
	 * qu'il porte ; au premier niveau, ce que l'objet propose. `parent` est posé entier, `eOwner` aussi
	 * au premier niveau.
	 */
	public static function getPropertiesCreateByType(Block $e, mixed $type): array {

		$e->expects(['owner', 'parent']);

		if($e['parent']->empty()) {
			$e->expects(['eOwner']);
			$types = BlockSetting::resolve($e['owner'], 'BlockOwnerLib')::getTypesByOwner($e['owner'], $e['eOwner']);
		} else {
			$e['parent']->expects(['type']);
			$types = BlockSetting::resolve($e['parent']['type'], 'BlockTypeLib')::getChildren($e['parent']['type']);
		}

		if(in_array($type, $types, TRUE)) {
			return array_merge(self::getPropertiesCreate(), BlockSetting::resolve($type, 'BlockTypeLib')::getPropertiesCreate($type));
		} else {
			return self::getPropertiesCreate();
		}

	}

	/**
	 * Un usage posté est un texte libre : il n'ouvre une classe que si son package existe et que
	 * l'usage figure parmi ceux que ce package déclare. Le package se vérifie avant la classe, pour ne
	 * jamais demander à l'autoloader un nom forgé.
	 */
	public static function isOwner(string $owner): bool {

		if(array_key_exists(BlockSetting::getPackage($owner), \Package::getList()) === FALSE) {
			return FALSE;
		}

		$class = BlockSetting::resolve($owner, 'BlockOwnerLib');

		return (
			class_exists($class) and
			in_array($owner, $class::getOwners(), TRUE)
		);

	}

	/**
	 * Les entrées du « + », du « / » et des tuiles, une par type, et une par variante pour un type à
	 * forme fixée (`isVariantFixed()`), dans l'ordre des types. Chacune a sa clé, le type seul ou
	 * `<type>:<variante>`, que le balisage porte pour retrouver le bloc à écrire de l'entrée
	 * (`BlockUi::getNew()`). La variante d'une autre entrée est `NULL` : son bloc naît sur la première,
	 * et l'auteur la change dans la barre.
	 */
	public static function getChoices(array $types): array {

		$choices = [];

		foreach($types as $type) {

			$typeLib = BlockSetting::resolve($type, 'BlockTypeLib');

			if($typeLib::isVariantFixed($type)) {

				foreach($typeLib::getVariants($type) as $variant) {
					$choices[$type.':'.$variant] = ['type' => $type, 'variant' => $variant];
				}

			} else {
				$choices[$type] = ['type' => $type, 'variant' => NULL];
			}

		}

		return $choices;

	}

	/**
	 * Les blocs à écrire qu'ajoute le « + », un par entrée du menu (`getChoices()`) et un enfant par
	 * parent, rendus une fois dans la page (`BlockUi::getEditor()`) et posés au clic : le « + » ne
	 * demande rien au serveur. Un enfant prend la forme de son parent (une étape, un pli, un onglet, un
	 * chiffre), d'où un bloc à écrire par parent et non un seul par type d'enfant. Le bloc naît à son
	 * premier enregistrement, après celui qui le précède alors dans la page. Un type à variantes s'ouvre
	 * sur la première, une forme fixée sur celle de son entrée. `$types` sont ceux que l'objet propose
	 * (`getTypesByOwner()` de l'usage), lus une fois par la page.
	 */
	public static function getNew(string $owner, int $reference, array $types): \Collection {

		$cBlock = new \Collection();

		foreach(self::getChoices($types) as $choice => ['type' => $type, 'variant' => $variant]) {
			$cBlock[$choice] = self::getNewBlock($owner, $reference, $choice, $type, $variant, NULL);
		}

		foreach($types as $parentType) {

			foreach(BlockSetting::resolve($parentType, 'BlockTypeLib')::getChildren($parentType) as $type) {
				$cBlock[$type.'-'.$parentType] = self::getNewBlock($owner, $reference, $type, $type, NULL, $parentType);
			}

		}

		return $cBlock;

	}

	/**
	 * `$choice` est la clé de l'entrée qui pose le bloc (`getChoices()`), que son formulaire porte pour
	 * le script ; celle d'un enfant est son type, le « + » d'un parent n'ajoutant qu'un type.
	 */
	private static function getNewBlock(string $owner, int $reference, string $choice, string $type, ?string $variant, ?string $parentType): Block {

		$typeLib = BlockSetting::resolve($type, 'BlockTypeLib');

		$variant ??= self::getDefaultVariant($type);

		return new Block([
			'owner' => $owner,
			'reference' => $reference,
			'parent' => new Block(),
			'parentType' => $parentType,
			'choice' => $choice,
			'type' => $type,
			'variants' => $typeLib::getVariants($type),
			'variantFixed' => $typeLib::isVariantFixed($type),
			'childTypes' => $typeLib::getChildren($type),
			'variant' => $variant,
			'title' => NULL,
			'content' => NULL,
			'url' => NULL,
			'settings' => $typeLib::getDefaultSettings($type, $variant),
			'full' => FALSE,
			'editorOptions' => $typeLib::getEditorOptions($type, $owner),
		]);

	}

	/**
	 * La variante d'un bloc qui naît sans en avoir choisi : la première de son type, ou aucune.
	 */
	private static function getDefaultVariant(string $type): ?string {

		$variants = BlockSetting::resolve($type, 'BlockTypeLib')::getVariants($type);

		return ($variants === []) ? NULL : $variants[0];

	}

	/**
	 * Un bloc porte-t-il la variante qu'un seul de sa fratrie peut porter (`getVariantUnique()`) ?
	 */
	private static function hasVariantUnique(Block $e): bool {

		$variantUnique = BlockSetting::resolve($e['type'], 'BlockTypeLib')::getVariantUnique($e['type']);

		return (
			$variantUnique !== NULL and
			$e['variant'] === $variantUnique
		);

	}

	/**
	 * Pose sur un bloc de l'éditeur ce que son formulaire demande : les options d'éditeur, les entrées
	 * que son « + » propose (`choices`, celles de l'usage au premier niveau, celles du parent pour un
	 * enfant), ses variantes, si elles sont une forme fixée que sa barre ne change pas (`variantFixed`),
	 * et les types de ses enfants (`childTypes`), que le « + » d'un parent ajoute. Chaque
	 * bloc lu porte son formulaire, prêt à s'ouvrir sans requête (`BlockUi::getItem()`), donc chaque
	 * bloc rendu dans l'éditeur passe par ici, enfants compris.
	 * Un enfant y reçoit aussi le type de son parent (`parentType`), qui décide de sa forme : une
	 * étape, un pli, un onglet ou un chiffre.
	 *
	 * `full` dit qu'un parent porte autant d'enfants que son type en accepte : ni son « + » ni la copie
	 * d'un de ses enfants ne s'affichent, que `Block::type.full` et `duplicate()` refuseraient. Un
	 * enfant le reçoit de son parent. Il se compte sur `cBlock`, que l'éditeur rend en entier, comme
	 * `last`, qui retire à un enfant la flèche qui le sortirait de sa fratrie : un enfant se rend
	 * toujours avec son parent entier (`block.v.php`).
	 *
	 * `$types` sont ceux que l'objet propose au premier niveau (`getTypesByOwner()` de l'usage), lus une
	 * fois par la page pour tous les blocs qu'elle rend.
	 */
	public static function fillEditor(Block $eBlock, array $types, Block $eBlockParent = new Block()): void {

		$eBlock['editorOptions'] = BlockSetting::resolve($eBlock['type'], 'BlockTypeLib')::getEditorOptions($eBlock['type'], $eBlock['owner']);
		$eBlock['variants'] = BlockSetting::resolve($eBlock['type'], 'BlockTypeLib')::getVariants($eBlock['type']);
		$eBlock['variantFixed'] = BlockSetting::resolve($eBlock['type'], 'BlockTypeLib')::isVariantFixed($eBlock['type']);
		$eBlock['childTypes'] = BlockSetting::resolve($eBlock['type'], 'BlockTypeLib')::getChildren($eBlock['type']);

		if($eBlockParent->empty()) {
			$eBlock['choices'] = self::getChoices($types);
			$eBlock['parentType'] = NULL;
			$childrenMax = BlockSetting::resolve($eBlock['type'], 'BlockTypeLib')::getChildrenMax($eBlock['type']);
			$eBlock['full'] = (
				$childrenMax !== NULL and
				$eBlock['cBlock']->count() >= $childrenMax
			);
		} else {
			$eBlock['choices'] = self::getChoices(BlockSetting::resolve($eBlockParent['type'], 'BlockTypeLib')::getChildren($eBlockParent['type']));
			$eBlock['parentType'] = $eBlockParent['type'];
			$eBlock['full'] = $eBlockParent['full'];
			$eBlock['last'] = ($eBlock['position'] === $eBlockParent['cBlock']->count() - 1);
		}

		foreach($eBlock['cBlock'] as $eBlockChild) {
			self::fillEditor($eBlockChild, $types, $eBlock);
		}

	}

	/**
	 * L'arbre d'un objet : les blocs de premier niveau, chacun portant ses enfants dans `cBlock`, dans
	 * l'ordre de leur fratrie.
	 */
	public static function getByOwnerAndReference(string $owner, int $reference): \Collection {
		return self::buildTree(self::getFlat($owner, $reference));
	}

	/**
	 * Un bloc relu avec ses enfants, pour les réponses qui le rendent.
	 */
	public static function getWithChildren(int $id): Block {

		$eBlock = self::getById($id);

		if($eBlock->empty()) {
			return $eBlock;
		}

		$eBlock['cBlock'] = self::getChildren($eBlock);

		foreach($eBlock['cBlock'] as $eBlockChild) {
			$eBlockChild['cBlock'] = new \Collection();
		}

		return $eBlock;

	}

	/**
	 * Le parent d'un bloc, relu en base, ou un bloc vide pour un bloc de premier niveau.
	 */
	public static function getParent(Block $e): Block {

		if($e['parent']->empty()) {
			return new Block();
		}

		return self::getById($e['parent']['id']);

	}

	/**
	 * Un parent qui porte déjà autant d'enfants que son type en accepte (`getChildrenMax()`) : il refuse
	 * un enfant de plus, posé ou copié. Un bloc de premier niveau n'a pas de parent, donc pas de borne.
	 */
	public static function isFull(Block $eBlockParent): bool {

		if($eBlockParent->empty()) {
			return FALSE;
		}

		$eBlockParent->expects(['type']);

		$childrenMax = BlockSetting::resolve($eBlockParent['type'], 'BlockTypeLib')::getChildrenMax($eBlockParent['type']);

		if($childrenMax === NULL) {
			return FALSE;
		}

		$eBlockParent->expects(['owner', 'reference']);

		// Un modèle neuf : l'appel vient d'une étape de `build()`, qui peut tomber pendant qu'une requête
		// se compose sur le singleton, et le compte la consommerait avec la sienne.
		return (
			new BlockModel()
				->whereOwner($eBlockParent['owner'])
				->whereReference($eBlockParent['reference'])
				->whereParent($eBlockParent)
				->count() >= $childrenMax
		);

	}

	/**
	 * L'objet porte-t-il déjà sa vidéo, autre que `$eBlockExcept` ? Il n'en porte qu'une
	 * (`Block::url.video`), à laquelle les instants de ses étapes se rapportent.
	 */
	public static function hasVideo(string $owner, int $reference, Block $eBlockExcept = new Block()): bool {

		// Un modèle neuf, pour la même raison que `isFull()` : l'appel vient d'une étape de `build()`.
		return new BlockModel()
			->whereOwner($owner)
			->whereReference($reference)
			->whereType(BlockTypeLib::VIDEO)
			->whereId('!=', $eBlockExcept, if: $eBlockExcept->notEmpty())
			->exists();

	}

	/**
	 * Les blocs d'un objet, ou de plusieurs objets en une requête quand `$reference` en est la liste,
	 * à plat, rangés par position : l'arbre d'un objet se rebâtit dessus (`buildTree()`), et la copie
	 * comme la suppression d'un objet entier les traitent un par un.
	 */
	private static function getFlat(string $owner, int|array $reference): \Collection {

		return Block::model()
			->select(Block::getSelection())
			->whereOwner($owner)
			->whereReference(is_array($reference) ? 'IN' : '=', $reference)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	private static function getChildren(Block $eBlockParent): \Collection {

		return Block::model()
			->select(Block::getSelection())
			->whereOwner($eBlockParent['owner'])
			->whereReference($eBlockParent['reference'])
			->whereParent($eBlockParent)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * La liste est recopiée dans un tableau avant d'être parcourue deux fois : une `Collection` n'a
	 * qu'un curseur.
	 */
	private static function buildTree(\Collection $cBlockFlat): \Collection {

		$blocks = [];

		foreach($cBlockFlat as $id => $eBlock) {
			$blocks[$id] = $eBlock;
		}

		$children = [];

		foreach($blocks as $eBlock) {

			$eBlock['cBlock'] = new \Collection();

			if($eBlock['parent']->notEmpty()) {
				$children[$eBlock['parent']['id']][$eBlock['id']] = $eBlock;
			}

		}

		$cBlock = new \Collection();

		foreach($blocks as $eBlock) {

			if($eBlock['parent']->notEmpty()) {
				continue;
			}

			$eBlock['cBlock'] = new \Collection($children[$eBlock['id']] ?? []);
			$cBlock[$eBlock['id']] = $eBlock;

		}

		return $cBlock;

	}

	/**
	 * Les blocs qu'un lecteur voit : ceux qui ont quelque chose à montrer. Une leçon dont l'auteur a
	 * vidé le seul texte se lit alors comme une leçon sans contenu, et non comme une page blanche.
	 *
	 * L'instant d'une étape mène à la vidéo de son objet : sans vidéo à lire, il ne mène nulle part, et
	 * la lecture le retire. Les repères de sa capture restent (`BlockTypeLib::castItem()`).
	 */
	public static function getReadable(string $owner, int $reference): \Collection {
		return self::filterReadable(self::getByOwnerAndReference($owner, $reference));
	}

	/**
	 * Les blocs lisibles de plusieurs objets, en une requête pour toute la liste, rangés par objet :
	 * chaque référence demandée a sa collection, vide quand l'objet n'a rien à montrer. La règle est
	 * celle de `getReadable()`, qui juge un bloc sur ses enfants (`BlockTypeLib::isEmpty()`) : l'arbre
	 * de chaque objet se rebâtit avant le filtre.
	 */
	public static function getReadableReferences(string $owner, array $references): array {

		$readable = [];

		foreach($references as $reference) {
			$readable[$reference] = new \Collection();
		}

		if($references === []) {
			return $readable;
		}

		$cBlockFlat = self::getFlat($owner, $references);

		$flat = [];

		foreach($cBlockFlat as $id => $eBlock) {
			$flat[$eBlock['reference']][$id] = $eBlock;
		}

		foreach($flat as $reference => $blocks) {
			$readable[$reference] = self::getReadableFromFlat(new \Collection($blocks));
		}

		return $readable;

	}

	/**
	 * Les blocs lisibles d'un objet dont la liste à plat est déjà chargée, sans requête : c'est la
	 * forme qu'une sélection rend (`delegateByOwner()`), indexée par id de bloc comme l'attend
	 * l'arbre. L'arbre se bâtit sur les éléments reçus, qui en gardent les enfants.
	 */
	public static function getReadableFromFlat(\Collection $cBlockFlat): \Collection {
		return self::filterReadable(self::buildTree($cBlockFlat));
	}

	private static function filterReadable(\Collection $cBlockTree): \Collection {

		$hasContent = fn(Block $eBlock) => BlockSetting::resolve($eBlock['type'], 'BlockTypeLib')::isEmpty($eBlock) === FALSE;

		$cBlock = $cBlockTree->find($hasContent);

		$hasVideo = $cBlock->contains(fn(Block $eBlock) => $eBlock['type'] === BlockTypeLib::VIDEO);

		foreach($cBlock as $eBlock) {

			$eBlock['cBlock'] = $eBlock['cBlock']->find($hasContent);

			if($hasVideo === FALSE) {

				foreach($eBlock['cBlock'] as $eBlockChild) {

					if($eBlockChild['type'] === BlockTypeLib::ITEM) {

						$settings = $eBlockChild['settings'];
						unset($settings['at']);

						$eBlockChild['settings'] = $settings;

					}

				}

			}

		}

		return $cBlock;

	}

	/**
	 * Les blocs de chaque objet d'une liste, à plat, en une requête pour toute la liste, à poser dans une
	 * sélection : `'cBlock' => BlockLib::delegateByOwner($owner)`. Un modèle neuf, et
	 * pas `Block::model()` : la sélection qui le porte est lue plus tard, par une autre requête, et
	 * le modèle singleton aurait été consommé entre-temps.
	 */
	public static function delegateByOwner(string $owner): BlockModel {

		return new BlockModel()
			->select(Block::getSelection())
			->whereOwner($owner)
			->sort(['position' => SORT_ASC, 'id' => SORT_ASC])
			->delegateCollection('reference', 'id');

	}

	/**
	 * Les blocs texte d'un objet mis bout à bout, sous une seule enveloppe `<main>` : c'est ce que
	 * l'API rend pour le contenu d'une leçon. Chaque bloc porte sa propre enveloppe, que la
	 * concaténation brute répéterait, et un XML à plusieurs racines ne se relit pas.
	 */
	public static function getText(\Collection $cBlock): ?string {

		$parts = [];

		foreach($cBlock as $eBlock) {

			if(
				$eBlock['type'] === BlockTypeLib::TEXT and
				$eBlock['content'] !== NULL
			) {
				$parts[] = preg_replace('/^<main>(.*)<\/main>$/s', '$1', $eBlock['content']);
			}

		}

		if($parts === []) {
			return NULL;
		}

		return '<main>'.implode('', $parts).'</main>';

	}

	/**
	 * Le bloc se range dans sa fratrie juste après `eBlockAfter`, juste avant `eBlockBefore`, ou en
	 * dernier sans l'un ni l'autre. `eBlockBefore` ne sert qu'au « + » de tête, qui n'a pas de bloc avant
	 * lui : un `eBlockAfter` vide veut déjà dire « en dernier », il ne peut pas vouloir dire aussi « en
	 * tête ». Les suivants descendent d'un rang dans la même transaction, pour que les rangs restent
	 * contigus.
	 *
	 * Un parent naît avec sa première étape, qui prend ce que le formulaire a écrit (`title`,
	 * `content`) et naît sur la première variante de son propre type : les deux lignes s'écrivent dans
	 * la même transaction, et l'objet n'est daté qu'une fois. Un bloc qui naît sur la variante unique
	 * de son type la retire à ses frères (`resetSiblingsVariant()`).
	 */
	public static function create(Block $e): void {

		$e->expects(['owner', 'reference', 'type']);

		foreach(['eBlockAfter', 'eBlockBefore', 'parent'] as $property) {

			if($e->offsetExists($property) === FALSE) {
				$e[$property] = new Block();
			}

		}

		// La variante de tout bloc créé est lue par `resetSiblingsVariant()`, et un type sans variante
		// n'en construit pas : le défaut se pose sans écraser ce que `build()` a construit.
		$e->add(['variant' => self::getDefaultVariant($e['type'])]);

		$children = BlockSetting::resolve($e['type'], 'BlockTypeLib')::getChildren($e['type']);

		if($children !== []) {

			$e->expects(['title', 'content']);

			$eBlockChild = new Block([
				'owner' => $e['owner'],
				'reference' => $e['reference'],
				'parent' => $e,
				'type' => $children[0],
				'variant' => self::getDefaultVariant($children[0]),
				'title' => $e['title'],
				'content' => $e['content'],
				'eBlockAfter' => new Block(),
				'eBlockBefore' => new Block(),
			]);

			$e['title'] = NULL;
			$e['content'] = NULL;

		}

		Block::model()->beginTransaction();

			self::insert($e);
			self::resetSiblingsVariant($e);

			if($children !== []) {
				self::insert($eBlockChild);
			}

			self::touch($e);

		Block::model()->commit();

	}

	/**
	 * L'écriture d'un bloc à son rang, sans la transaction ni la date de l'objet, que porte l'appelant.
	 */
	private static function insert(Block $e): void {

		if(
			$e['eBlockAfter']->empty() and
			$e['eBlockBefore']->empty()
		) {

			$e['position'] = self::whereSiblings($e)->count();

		} else {

			if($e['eBlockAfter']->notEmpty()) {
				$e['position'] = $e['eBlockAfter']['position'] + 1;
			} else {
				$e['position'] = $e['eBlockBefore']['position'];
			}

			// Un tableau de valeurs et non un élément : `update($e)` ajouterait `AND id = <id>` et ne
			// décalerait qu'une ligne.
			self::whereSiblings($e)
				->wherePosition('>=', $e['position'])
				->update(['position' => new \Sql('position + 1')]);

		}

		parent::create($e);

	}

	/**
	 * La fratrie d'un bloc : le même objet et le même parent, `NULL` au premier niveau
	 * (`whereParent()` d'un élément vide rend `parent IS NULL`).
	 */
	private static function whereSiblings(Block $e): BlockModel {

		return Block::model()
			->whereOwner($e['owner'])
			->whereReference($e['reference'])
			->whereParent($e['parent']);

	}

	/**
	 * `cBlockReset` rend les frères qui ont perdu la variante unique que ce bloc vient de prendre : la
	 * page ne voit que l'élément, `ModulePage` appelant elle-même cette méthode.
	 */
	public static function update(Block $e, array $properties): void {

		$e->expects(['owner', 'reference']);

		$e['cBlockReset'] = new \Collection();

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		Block::model()->beginTransaction();

			parent::update($e, $properties);

			if(in_array('variant', $properties, TRUE)) {
				$e['cBlockReset'] = self::resetSiblingsVariant($e);
			}

			self::touch($e);

		Block::model()->commit();

	}

	/**
	 * Un bloc qui porte la variante unique de son type la retire à ses frères, qui reprennent la
	 * première, une ligne à la fois et dans la transaction de l'appelant. Il n'y en a qu'un à remettre,
	 * sauf si deux écritures concurrentes ont marqué chacune le leur : le geste suivant corrige.
	 */
	private static function resetSiblingsVariant(Block $e): \Collection {

		$e->expects(['owner', 'reference', 'parent', 'type', 'variant']);

		if(self::hasVariantUnique($e) === FALSE) {
			return new \Collection();
		}

		$cBlockReset = self::whereSiblings($e)
			->select(['id', 'variant'])
			->whereId('!=', $e['id'])
			->whereVariant($e['variant'])
			->getCollection(index: 'id');

		foreach($cBlockReset as $eBlockReset) {

			$eBlockReset['variant'] = self::getDefaultVariant($e['type']);
			$eBlockReset['updatedAt'] = new \Sql('NOW()');

			Block::model()
				->select('variant', 'updatedAt')
				->update($eBlockReset);

		}

		return $cBlockReset;

	}

	/**
	 * Les suivants de la fratrie remontent d'un rang : la liste reste sans trou, et
	 * `incrementPosition()` trouve toujours le voisin au rang d'à côté. Un parent part avec ses
	 * enfants, et le dernier enfant retiré emporte son parent, qui n'aurait plus rien à montrer ni
	 * aucun formulaire où l'on écrive : tout dans la même transaction.
	 */
	public static function delete(Block $e): void {

		$e->expects(['owner', 'reference', 'parent', 'position']);

		Block::model()->beginTransaction();

			self::remove($e);

			if(
				$e['parent']->notEmpty() and
				self::whereSiblings($e)->count() === 0
			) {
				self::remove(self::getById($e['parent']['id']));
			}

			self::touch($e);

		Block::model()->commit();

		\media\BlockItemLib::deleteFiles();

	}

	/**
	 * Le parent est relu sur l'objet et non pris à l'élément : c'est la ligne en base qui porte les
	 * enfants, quelle que soit la sélection de l'appelant.
	 */
	private static function remove(Block $e): void {

		$e->expects(['media']);

		foreach(self::getChildren($e) as $eBlockChild) {
			self::deleteMediaByBlock($eBlockChild);
			parent::delete($eBlockChild);
		}

		self::deleteMediaByBlock($e);
		parent::delete($e);

		// Même raison qu'à la création pour le tableau de valeurs.
		self::whereSiblings($e)
			->wherePosition('>', $e['position'])
			->update(['position' => new \Sql('position - 1')]);

	}

	/**
	 * Monter ou descendre d'un rang dans sa fratrie, en échangeant avec le voisin. Un autre incrément
	 * n'est pas un geste de l'écran, qui range la liste entière par `updatePositions()` : il est
	 * ignoré, comme dans `product\CategoryLib::incrementPosition()`. Au bout de la fratrie, il n'y a
	 * pas de voisin et rien ne bouge.
	 */
	public static function incrementPosition(Block $e, int $increment): void {

		$e->expects(['owner', 'reference', 'parent', 'position']);

		if(in_array($increment, [-1, 1], TRUE) === FALSE) {
			return;
		}

		Block::model()->beginTransaction();

			$eBlockNeighbour = self::whereSiblings($e)
				->select(['id', 'position'])
				->wherePosition($e['position'] + $increment)
				->get();

			if($eBlockNeighbour->notEmpty()) {

				$eBlockNeighbour['position'] = $e['position'];
				$e['position'] += $increment;

				Block::model()
					->select('position')
					->update($eBlockNeighbour);

				Block::model()
					->select('position')
					->update($e);

				self::touch($e);

			}

		Block::model()->commit();

	}

	/**
	 * L'ordre entier du premier niveau d'un objet, depuis le plan de l'écran, qui ne montre pas les
	 * enfants. Une liste qui ne porte pas exactement les blocs de premier niveau a été composée sur un
	 * écran périmé (un bloc ajouté dans un autre onglet) : elle est refusée plutôt que d'écrire des
	 * rangs faux.
	 */
	public static function updatePositions(string $owner, int $reference, array $ids): void {

		$cBlock = Block::model()
			->select(['id', 'owner', 'reference'])
			->whereOwner($owner)
			->whereReference($reference)
			->whereParent(new Block())
			->getCollection();

		$positions = array_flip(array_values($ids));

		if(
			$cBlock->empty() or
			count($ids) !== $cBlock->count() or
			count($positions) !== count($ids) or
			array_diff($ids, $cBlock->getIds()) !== []
		) {
			Block::fail('positionsIncomplete');
			return;
		}

		Block::model()->beginTransaction();

			foreach($cBlock as $eBlock) {

				$eBlock['position'] = $positions[$eBlock['id']];

				Block::model()
					->select('position')
					->update($eBlock);

			}

			self::touch($cBlock->first());

		Block::model()->commit();

	}

	/**
	 * La copie se range juste après l'original, dans sa fratrie, avec des copies de ses enfants aux
	 * mêmes rangs. Elle ne passe pas par `create()`, qui donnerait à un parent une première étape
	 * neuve : ce sont celles de l'original qu'il reçoit. Chaque image est dupliquée à part
	 * (`duplicateMedia()`).
	 *
	 * Un enfant ne se copie pas dans un parent plein (`isFull()`) : la copie serait l'enfant de trop que
	 * `Block::type.full` refuse à la création. Une vidéo ne se copie pas non plus, l'objet n'en portant
	 * qu'une (`Block::url.video`). Rend alors un bloc vide.
	 *
	 * La copie d'un bloc qui porte la variante unique de son type naît sur la première, l'original la
	 * gardant : une copie de la meilleure réponse est une réponse ordinaire. Les enfants d'un parent
	 * copié gardent la leur, leur fratrie étant neuve.
	 */
	public static function duplicate(Block $e): Block {

		if(self::isFull(self::getParent($e))) {
			Block::fail('type.full');
			return new Block();
		}

		if($e['type'] === BlockTypeLib::VIDEO) {
			Block::fail('url.video');
			return new Block();
		}

		$eBlockCopy = new Block($e->extracts(self::getPropertiesCopied()));

		if(self::hasVariantUnique($eBlockCopy)) {
			$eBlockCopy['variant'] = self::getDefaultVariant($eBlockCopy['type']);
		}

		$eBlockCopy['eBlockAfter'] = $e;
		$eBlockCopy['eBlockBefore'] = new Block();

		$cBlockCopy = new \Collection();

		Block::model()->beginTransaction();

			self::insert($eBlockCopy);

			$cBlockCopy[$eBlockCopy['id']] = $eBlockCopy;

			foreach(self::getChildren($e) as $eBlockChild) {

				$eBlockChildCopy = new Block($eBlockChild->extracts(self::getPropertiesCopied()));
				$eBlockChildCopy['parent'] = $eBlockCopy;
				$eBlockChildCopy['position'] = $eBlockChild['position'];

				parent::create($eBlockChildCopy);

				$cBlockCopy[$eBlockChildCopy['id']] = $eBlockChildCopy;

			}

			self::touch($eBlockCopy);

			self::duplicateMedia($cBlockCopy);

		Block::model()->commit();

		return $eBlockCopy;

	}

	/**
	 * Tous les blocs d'un objet, recopiés sur un autre du même usage, aux mêmes rangs. Sans `touch()` :
	 * l'appelant copie l'objet entier et le date lui-même. Un bloc enfant suit son parent, dont l'id
	 * change à la copie : les parents sont donc écrits d'abord.
	 */
	public static function duplicateByOwnerAndReference(string $owner, int $referenceFrom, int $referenceTo): void {

		$cBlock = self::getFlat($owner, $referenceFrom);

		$cBlockOrdered = $cBlock->find(fn(Block $eBlock) => $eBlock['parent']->empty());
		$cBlockOrdered->appendCollection($cBlock->find(fn(Block $eBlock) => $eBlock['parent']->notEmpty()));

		$ids = [];
		$cBlockCopy = new \Collection();

		Block::model()->beginTransaction();

			foreach($cBlockOrdered as $eBlock) {

				$eBlockCopy = new Block($eBlock->extracts(self::getPropertiesCopied()));
				$eBlockCopy['reference'] = $referenceTo;
				$eBlockCopy['position'] = $eBlock['position'];

				if($eBlock['parent']->notEmpty()) {
					$eBlockCopy['parent'] = new Block(['id' => $ids[$eBlock['parent']['id']]]);
				}

				parent::create($eBlockCopy);

				$ids[$eBlock['id']] = $eBlockCopy['id'];
				$cBlockCopy[$eBlockCopy['id']] = $eBlockCopy;

			}

			self::duplicateMedia($cBlockCopy);

		Block::model()->commit();

	}

	/**
	 * Sans `touch()` ni resserrement des rangs : l'objet disparaît avec ses blocs, enfants compris, et
	 * leurs images, dans la transaction de l'appelant.
	 */
	public static function deleteByOwnerAndReference(string $owner, int $reference): void {

		foreach(self::getFlat($owner, $reference) as $eBlock) {
			self::deleteMediaByBlock($eBlock);
			parent::delete($eBlock);
		}

	}

	/**
	 * Pose l'image d'un bloc depuis un fichier local, que l'appelant a reçu et retire : le moteur ne sait
	 * pas comment l'image est arrivée. Le bloc et l'objet sont datés comme pour toute écriture de bloc.
	 * Le type admis se juge avant (`Block::acceptMedia()`). Les repères d'une étape (`settings.marks`)
	 * restent : une capture remplacée reprend le même écran, et celui qui veut les déplacer réécrit le
	 * réglage.
	 */
	public static function putMedia(Block $e, string $file): void {

		$e->expects(['owner', 'reference', 'media']);

		\media\ApiAiLib::putFile('block-item', $e, $file);

		self::touchMedia($e);

	}

	/**
	 * Retire l'image d'un bloc. Sans image, rien ne change, et rien n'est daté. Elle part par le même
	 * chemin que celle d'un bloc retiré (`deleteMediaByBlock()`). Les repères d'une étape partent avec
	 * elle : placés en pourcentage de cette image, ils ne désignent plus rien.
	 */
	public static function deleteMedia(Block $e): void {

		$e->expects(['owner', 'reference', 'media', 'settings']);

		if($e['media'] === NULL) {
			return;
		}

		$eBlockBefore = new Block(['media' => $e['media']]);

		$settings = $e['settings'];
		unset($settings['marks']);

		$e['media'] = NULL;
		$e['settings'] = $settings;
		$e['updatedAt'] = new \Sql('NOW()');

		Block::model()->beginTransaction();

			Block::model()
				->select('media', 'settings', 'updatedAt')
				->update($e);

			self::deleteMediaByBlock($eBlockBefore);

			self::touch($e);

		Block::model()->commit();

		\media\BlockItemLib::deleteFiles();

	}

	/**
	 * `MediaLib` écrit la colonne seule : la date du bloc et celle de l'objet se posent ici, ensemble.
	 */
	private static function touchMedia(Block $e): void {

		$e['updatedAt'] = new \Sql('NOW()');

		Block::model()->beginTransaction();

			Block::model()
				->select('updatedAt')
				->update($e);

			self::touch($e);

		Block::model()->commit();

	}

	/**
	 * Le seul chemin par lequel l'image d'un bloc s'en va. Le média passe au statut retiré dans la
	 * transaction de l'appelant, et un rollback la remet en place avec sa ligne. Le fichier quitte le
	 * disque à `deleteFiles()`, que `delete()` et `deleteMedia()` appellent après leur COMMIT : il ne
	 * reste pas servi à son adresse publique. Sous `deleteByOwnerAndReference()`, la transaction est
	 * celle de l'appelant, et le fichier part au passage de `CleanLib`. Le hash se coupe à ses vingt
	 * caractères : la colonne porte derrière lui la couleur dominante et la version, que la table
	 * `media` ne connaît pas, et `MediaLib::delete()`, qui ne coupe pas, laisserait le média actif.
	 */
	private static function deleteMediaByBlock(Block $e): void {

		if($e['media'] !== NULL) {
			new \media\BlockItemLib()->deleteByHash(substr($e['media'], 0, 20));
		}

	}

	/**
	 * Chaque copie prend sa propre image, sous un hash neuf : `ApiAiLib::putFile()` remplace l'image
	 * d'un bloc en réutilisant son hash, et écraserait sinon celle de l'original. Les fichiers se
	 * copient après les lignes, au plus près du COMMIT : un fichier copié sur le disque survit à un
	 * rollback.
	 *
	 */
	private static function duplicateMedia(\Collection $cBlockCopy): void {

		foreach($cBlockCopy as $eBlockCopy) {

			if($eBlockCopy['media'] === NULL) {
				continue;
			}

			new \media\BlockItemLib()->duplicate($eBlockCopy);

			Block::model()
				->select('media')
				->update($eBlockCopy);

		}

	}

	/**
	 * Coupe un bloc texte au « / » (`doSplit`). `$e` porte le texte d'avant, `$eBlockRest` celui
	 * d'après, construits tous deux par `build()`. Le bloc choisi dans la liste n'est pas écrit ici :
	 * il naît à son premier enregistrement, par `doCreate`, comme celui du « + ».
	 *
	 * - Un texte d'avant garde le bloc, et le texte d'après, s'il y en a un, devient un texte sous lui.
	 * - Sans texte d'avant, rien ne reste au-dessus : le bloc garde le texte d'après, et le bloc choisi
	 *   se pose avant lui.
	 * - Sans l'un ni l'autre, le bloc ne portait que le « / » : il est retiré.
	 *
	 * Tout s'écrit dans la même transaction, et l'objet n'est daté qu'une fois. Rend `FALSE` si le bloc
	 * a été retiré.
	 */
	public static function split(Block $e, Block $eBlockRest): bool {

		$e->expects(['owner', 'reference', 'parent', 'position', 'content']);
		$eBlockRest->expects(['content']);

		Block::model()->beginTransaction();

			if($e['content'] === NULL and $eBlockRest['content'] === NULL) {

				self::remove($e);
				$kept = FALSE;

			} else {

				if($e['content'] === NULL) {
					$e['content'] = $eBlockRest['content'];
					$eBlockRest['content'] = NULL;
				}

				$e['updatedAt'] = new \Sql('NOW()');

				Block::model()
					->select('content', 'updatedAt')
					->update($e);

				if($eBlockRest['content'] !== NULL) {

					$eBlockRest['owner'] = $e['owner'];
					$eBlockRest['reference'] = $e['reference'];
					$eBlockRest['parent'] = $e['parent'];
					$eBlockRest['type'] = $e['type'];
					$eBlockRest['eBlockAfter'] = $e;
					$eBlockRest['eBlockBefore'] = new Block();

					self::insert($eBlockRest);

				}

				$kept = TRUE;

			}

			self::touch($e);

		Block::model()->commit();

		return $kept;

	}

	private static function getPropertiesCopied(): array {
		return ['owner', 'reference', 'parent', 'type', 'variant', 'title', 'content', 'media', 'url', 'settings'];
	}

	private static function touch(Block $e): void {
		BlockSetting::resolve($e['owner'], 'BlockOwnerLib')::touch($e['owner'], $e['reference']);
	}

}
?>
