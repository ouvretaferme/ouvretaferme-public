<?php
/**
 * Les gestes de l'éditeur des blocs. Chaque réponse ne renvoie que ce qu'elle touche : le bloc
 * refermé, le voisin déplacé, et le plan de la colonne de droite (`block.v.php`).
 *
 * Un bloc n'a pas de droits à lui : ce sont ceux de l'objet qui le porte, que la page relit ici
 * (`eOwner`) et que l'usage juge (`<package>\BlockOwnerLib`).
 *
 * Ouvrir un bloc ne passe pas par le serveur : chaque bloc lu porte son formulaire
 * (`BlockUi::getItem()`), que le script sort à la place du rendu. Tout bloc rendu ici passe donc par
 * `BlockLib::fillEditor()`.
 */

/**
 * Un autre bloc désigné par le geste (celui après ou avant lequel on ajoute) n'est admis que s'il
 * appartient au même objet : les droits ont été jugés sur cet objet-là seulement.
 */
$getSibling = function(\block\Block $eBlock, ?int $id): \block\Block {

	if($id === NULL) {
		return new \block\Block();
	}

	$eBlockSibling = \block\BlockLib::getById($id);

	if(
		$eBlockSibling->empty() or
		$eBlockSibling['owner'] !== $eBlock['owner'] or
		$eBlockSibling['reference'] !== $eBlock['reference']
	) {
		throw new NotExpectedAction('Block #'.$id);
	}

	return $eBlockSibling;

};

/**
 * Les types que l'objet du bloc propose au premier niveau, jugés sur l'objet relu par la page
 * (`eOwner`), et lus une fois par geste pour tous les blocs que la réponse rend.
 */
$getTypes = fn(\block\Block $eBlock): array => \block\BlockSetting::resolve($eBlock['owner'], 'BlockOwnerLib')::getTypesByOwner($eBlock['owner'], $eBlock['eOwner']);

/**
 * Le parent d'un enfant touché, relu avec tous ses enfants et prêt à se rendre dans l'éditeur, ou un
 * bloc vide pour un bloc de premier niveau et pour un parent que le geste a retiré.
 */
$getComposite = function(\block\Block $eBlockParent, array $types): \block\Block {

	if($eBlockParent->empty()) {
		return new \block\Block();
	}

	$eBlockParent = \block\BlockLib::getWithChildren($eBlockParent['id']);

	if($eBlockParent->notEmpty()) {
		\block\BlockLib::fillEditor($eBlockParent, $types);
	}

	return $eBlockParent;

};

/**
 * Un bloc ne naît que d'un bloc à écrire (`draft`), à son premier enregistrement, avec ce qui y est
 * écrit. Celui du « + » poste son type et le bloc qui le précède dans la page (`after`), ou, posé par
 * le « + » de tête, celui qui le suit (`before`). Son parent est celui du voisin : une étape ajoutée
 * sous une étape est sa sœur. Le type ne construit ce que le type écrit que s'il est admis à cette
 * place (`BlockLib::getPropertiesCreateByType()`), que l'API juge de la même façon.
 *
 * Sans voisin, c'est le premier bloc d'un objet, choisi parmi les tuiles (`BlockUi::getStart()`), ou
 * un bloc choisi au « / » dans une liste que la coupe vient de vider : il se range en dernier. Le type
 * se poste toujours : sans lui, `build()` refuse le bloc (`Block::type.check`).
 */
new \block\BlockPage(
	propertiesCreate: function(\block\Block $eBlock) {

		if($eBlock['type'] !== NULL) {
			return \block\BlockSetting::resolve($eBlock['type'], 'BlockTypeLib')::getPropertiesCreate($eBlock['type']);
		}

		return \block\BlockLib::getPropertiesCreateByType($eBlock, POST('type'));

	},
	propertiesUpdate: fn(\block\Block $eBlock) => \block\BlockSetting::resolve($eBlock['type'], 'BlockTypeLib')::getProperties($eBlock['type'])
)
	->getCreateElement(function($data) use($getSibling) {

		$owner = INPUT('owner');

		if(\block\BlockLib::isOwner($owner) === FALSE) {
			throw new NotExpectedAction('Owner');
		}

		if(POST('draft', 'bool') === FALSE) {
			throw new NotExpectedAction('Draft');
		}

		$reference = INPUT('reference', 'int');

		$eBlock = new \block\Block([
			'owner' => $owner,
			'reference' => $reference,
			'type' => NULL,
			'eOwner' => \block\BlockSetting::resolve($owner, 'BlockOwnerLib')::getOwner($owner, $reference),
		]);

		// Deux places pour un même bloc se contredisent.
		if(
			INPUT('after', '?int') !== NULL and
			INPUT('before', '?int') !== NULL
		) {
			throw new NotExpectedAction('After and before');
		}

		$eBlock['eBlockAfter'] = $getSibling($eBlock, INPUT('after', '?int'));
		$eBlock['eBlockBefore'] = $getSibling($eBlock, INPUT('before', '?int'));

		$eBlockSibling = $eBlock['eBlockAfter']->notEmpty() ? $eBlock['eBlockAfter'] : $eBlock['eBlockBefore'];

		if(
			$eBlockSibling->notEmpty() and
			$eBlockSibling['parent']->notEmpty()
		) {
			$eBlock['parent'] = \block\BlockLib::getById($eBlockSibling['parent']['id']);
		} else {
			$eBlock['parent'] = new \block\Block();
		}

		return $eBlock;

	})
	/**
	 * Le refus d'un titre vide lit le type du parent : il nomme l'enfant d'après lui (une étape, un
	 * volet, un chiffre) et reconnaît le nom d'un onglet (`Block::isTabName()`). Le parent est donc
	 * posé entier ici sur tout bloc lu par son id, comme `getCreateElement()` le pose sur celui qui
	 * naît.
	 */
	->applyElement(function($data, \block\Block $eBlock) {

		$ownerLib = \block\BlockSetting::resolve($eBlock['owner'], 'BlockOwnerLib');

		$eBlock['eOwner'] = $ownerLib::getOwner($eBlock['owner'], $eBlock['reference']);
		$eBlock['parent'] = \block\BlockLib::getParent($eBlock);

	})
	/**
	 * Un bloc de premier niveau se rend seul et range sa ligne dans le plan. Un enfant se rend dans son
	 * parent, relu avec tous ses enfants : ses voisins en décident la forme (le numéro d'une étape,
	 * l'onglet choisi).
	 */
	->doCreate(function($data) use($getTypes, $getComposite) {

		$types = $getTypes($data->e);

		$data->eBlockParent = $data->e['parent'];

		if($data->eBlockParent->empty()) {

			$data->cBlock = \block\BlockLib::getByOwnerAndReference($data->e['owner'], $data->e['reference']);

			foreach($data->cBlock as $eBlock) {
				\block\BlockLib::fillEditor($eBlock, $types);
			}

		} else {
			$data->eBlockParent = $getComposite($data->eBlockParent, $types);
		}

		throw new ViewAction($data);

	})
	/**
	 * Refermer un bloc modifié (`close`) l'enregistre et le rend lu, avec un formulaire neuf : celui
	 * que la page portait date d'avant ce qu'on vient d'écrire. Relu en base, le bloc rendu est
	 * celui que montrera un rechargement. Un enfant est pris dans son parent relu en entier : ses
	 * gestes dépendent de sa fratrie (le rang pour les flèches, le compte pour la copie).
	 *
	 * Un parent n'a pas de formulaire : le seul champ qu'il écrit, le repère des étapes, s'enregistre
	 * dès qu'on le choisit dans sa barre, toujours avec `close`, pour que la liste se rende aussitôt
	 * avec sa nouvelle marque.
	 *
	 * Les frères qui viennent de perdre la variante unique du bloc (`cBlockReset`, la meilleure
	 * réponse) sont pris dans leur parent relu, refermé ou non, pour que l'écran ne garde pas deux
	 * marques.
	 */
	->doUpdate(function($data) use($getTypes, $getComposite) {

		$types = $getTypes($data->e);

		$data->close = POST('close', 'bool');
		$data->cBlockReset = new \Collection();

		if(
			$data->close or
			$data->e['cBlockReset']->notEmpty()
		) {
			$eBlockParent = $getComposite($data->e['parent'], $types);
		} else {
			$eBlockParent = new \block\Block();
		}

		if($data->close) {

			if($eBlockParent->empty()) {
				$data->eBlockClosed = \block\BlockLib::getWithChildren($data->e['id']);
				\block\BlockLib::fillEditor($data->eBlockClosed, $types);
			} else {
				$data->eBlockClosed = $eBlockParent['cBlock'][$data->e['id']];
			}

		}

		if($data->e['cBlockReset']->notEmpty()) {
			$data->cBlockReset = $eBlockParent['cBlock']->find(fn(\block\Block $eBlockChild) => $data->e['cBlockReset']->offsetExists($eBlockChild['id']));
		}

		throw new ViewAction($data);

	})
	/**
	 * Un enfant se déplace, se copie ou se retire dans son parent, que la réponse rend de nouveau en
	 * entier : les numéros des étapes et les têtes des onglets suivent l'ordre de toute la fratrie.
	 */
	->write('doIncrementPosition', function($data) use($getTypes, $getComposite) {

		$types = $getTypes($data->e);

		$increment = POST('increment', 'int');
		$position = $data->e['position'];

		\block\BlockLib::incrementPosition($data->e, $increment);

		$data->eBlockParent = $getComposite($data->e['parent'], $types);

		if($data->eBlockParent->notEmpty()) {
			throw new ViewAction($data);
		}

		$data->cBlock = \block\BlockLib::getByOwnerAndReference($data->e['owner'], $data->e['reference']);
		$data->eBlockNeighbour = $data->cBlock->find(fn(\block\Block $eBlock) => (
			$eBlock['position'] === $position and
			$eBlock['id'] !== $data->e['id']
		))->first() ?? new \block\Block();
		$data->increment = $increment;

		if($data->eBlockNeighbour->notEmpty()) {
			\block\BlockLib::fillEditor($data->eBlockNeighbour, $types);
		}

		throw new ViewAction($data);

	})
	->write('doDuplicate', function($data) use($getTypes, $getComposite) {

		$types = $getTypes($data->e);

		$fw = new FailWatch();

		$eBlockCopy = \block\BlockLib::duplicate($data->e);

		$fw->validate();

		$data->eBlockParent = $getComposite($data->e['parent'], $types);

		if($data->eBlockParent->notEmpty()) {
			throw new ViewAction($data);
		}

		$data->eBlockCopy = \block\BlockLib::getWithChildren($eBlockCopy['id']);
		\block\BlockLib::fillEditor($data->eBlockCopy, $types);

		$data->cBlock = \block\BlockLib::getByOwnerAndReference($data->e['owner'], $data->e['reference']);

		throw new ViewAction($data);

	})
	/**
	 * Le retrait de la dernière étape emporte son parent (`BlockLib::delete()`) : relu vide, le parent
	 * quitte la page comme un bloc de premier niveau.
	 */
	->doDelete(function($data) use($getTypes, $getComposite) {

		$data->eBlockParent = $getComposite($data->e['parent'], $getTypes($data->e));

		$data->cBlock = \block\BlockLib::getByOwnerAndReference($data->e['owner'], $data->e['reference']);

		throw new ViewAction($data);

	})
	/**
	 * Le « / » coupe un texte de premier niveau à l'endroit où il a été tapé : le texte d'avant reste
	 * dans le bloc, celui d'après devient un texte sous lui (`BlockLib::split()`). Le bloc choisi dans
	 * la liste n'est pas écrit ici : il attend dans la page la réponse, qui lui donne ses voisins, et
	 * naît par `doCreate` à son premier enregistrement comme celui du « + ». Refermé vide avant, il
	 * ne laisse rien.
	 *
	 * Son type est pourtant jugé ici, sur un bloc qu'on n'écrit pas, comme `doCreate` le jugera à la
	 * même place : un type refusé ne coupe rien, et l'éditeur retrouve son texte.
	 */
	->write('doSplit', function($data) use($getTypes) {

		if(
			$data->e['type'] !== \block\BlockTypeLib::TEXT or
			$data->e['parent']->notEmpty()
		) {
			throw new NotExpectedAction('Split');
		}

		$types = $getTypes($data->e);

		$fw = new FailWatch();

		new \block\Block([
			'owner' => $data->e['owner'],
			'reference' => $data->e['reference'],
			'parent' => new \block\Block(),
			'eOwner' => $data->e['eOwner'],
		])->build(['type'], $_POST);

		$data->e->build(['content'], $_POST);

		$eBlockRest = new \block\Block([
			'owner' => $data->e['owner'],
			'reference' => $data->e['reference'],
			'type' => \block\BlockTypeLib::TEXT,
			'eOwner' => $data->e['eOwner'],
		]);

		$eBlockRest->build(['content'], ['content' => POST('rest')]);

		$fw->validate();

		if(\block\BlockLib::split($data->e, $eBlockRest)) {
			$data->eBlockSplit = \block\BlockLib::getWithChildren($data->e['id']);
			\block\BlockLib::fillEditor($data->eBlockSplit, $types);
		} else {
			$data->eBlockSplit = new \block\Block();
		}

		if($eBlockRest->exists()) {
			$data->eBlockRest = \block\BlockLib::getWithChildren($eBlockRest['id']);
			\block\BlockLib::fillEditor($data->eBlockRest, $types);
		} else {
			$data->eBlockRest = new \block\Block();
		}

		$data->cBlock = \block\BlockLib::getByOwnerAndReference($data->e['owner'], $data->e['reference']);

		throw new ViewAction($data);

	});

/**
 * L'ordre entier depuis le plan (amendement A1) : la liste des ids, dans l'ordre, pour l'objet
 * posté. Les droits se jugent sur l'objet, comme pour un bloc, faute de bloc désigné.
 *
 * Le script range lui-même les blocs dans l'ordre qu'il vient de poster, sans rien recevoir : les
 * flèches du premier et du dernier bloc se règlent dans `block.css`, par leur place dans la liste.
 */
new Page()
	->post('doUpdatePositions', function($data) {

		$owner = POST('owner');

		if(\block\BlockLib::isOwner($owner) === FALSE) {
			throw new NotExpectedAction('Owner');
		}

		$reference = POST('reference', 'int');

		new \block\Block([
			'owner' => $owner,
			'reference' => $reference,
			'eOwner' => \block\BlockSetting::resolve($owner, 'BlockOwnerLib')::getOwner($owner, $reference),
		])->validate('canWrite', 'acceptWrite');

		$positions = json_decode(POST('positions'), TRUE);

		if(is_array($positions) === FALSE) {
			throw new NotExpectedAction('Positions');
		}

		$fw = new FailWatch();

		\block\BlockLib::updatePositions($owner, $reference, array_map('intval', $positions));

		$fw->validate();

		throw new VoidAction();

	});
?>
