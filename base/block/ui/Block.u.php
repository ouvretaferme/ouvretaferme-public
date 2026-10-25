<?php
namespace block;

/**
 * Les blocs d'un objet, lus puis édités sur leur rendu (direction A de la planche des blocs, édition
 * en place de la reprise du 30/09).
 *
 * La lecture pose les blocs dans une liste qui les espace de 1 rem (`block-list`, `read.css`), l'écart
 * de deux paragraphes : une leçon coupée en blocs se lit comme avant sa coupe. L'édition garde ce
 * rendu et cet écart, et y ajoute son chrome : un « + » dans l'écart au-dessus du premier bloc et
 * sous chacun, le nom du bloc au survol, le bloc ouvert avec sa barre et son formulaire, et le plan
 * de l'objet dans la colonne de droite.
 *
 * Chaque bloc vit dans une enveloppe `#block-<id>` que les réponses remplacent, déplacent ou
 * retirent une à une : un geste ne renvoie que le bloc qu'il touche, et le plan. Chaque bloc lu y
 * porte aussi son formulaire, dans un `<template>` que le script sort au clic : ouvrir un bloc ne
 * demande rien au serveur.
 */
class BlockUi {

	/**
	 * La lecture, dans la liste qui espace les blocs et retire les marges basses des composants
	 * (`read.css`). Le chrome d'édition (feuille, script, Sortable) ne se charge qu'avec `getEditor()` :
	 * un lecteur n'en a pas l'usage. Un objet sans bloc à lire ne pose pas de liste vide, dont la marge
	 * basse s'ajouterait à ce qui suit.
	 *
	 * Une section regroupe, en un seul parcours, les blocs qui la suivent jusqu'à la suivante : sa tête,
	 * puis leur liste, dans un `section` que son fond couvre en entier (`read.css`). Les blocs qui
	 * précèdent la première section restent dans la liste de l'objet, et une section sans rien dessous
	 * n'ouvre pas de liste vide. C'est la liste, que le moteur porte, et non l'arbre, qui regroupe une
	 * section : la lecture nomme donc son type. Les blocs vides ont déjà été écartés
	 * (`BlockLib::getReadable()`) : un bloc vide entre deux sections n'en ouvre pas la liste.
	 */
	public function render(\Collection $cBlock): string {

		if($cBlock->empty()) {
			return '';
		}

		\Asset::css('block', 'read.css');

		$inSection = FALSE;
		$inList = FALSE;

		$h = '<div class="block-list">';

			foreach($cBlock as $eBlock) {

				$read = $this->getTypeUi($eBlock['type'])->render($eBlock);

				if($eBlock['type'] === BlockTypeLib::SECTION) {

					$h .= ($inList ? '</div>' : '').($inSection ? '</section>' : '');
					$h .= '<section class="block-section">'.$read;

					$inSection = TRUE;
					$inList = FALSE;

				} else {

					if($inSection and $inList === FALSE) {
						$h .= '<div class="block-list">';
						$inList = TRUE;
					}

					$h .= $read;

				}

			}

			$h .= ($inList ? '</div>' : '').($inSection ? '</section>' : '');

		$h .= '</div>';

		return $h;

	}

	/**
	 * `$below` est ce que l'usage pose sous les blocs, dans la même colonne (pour une leçon : ses
	 * fichiers, son quiz et sa barre). La colonne de droite colle au défilement de toute la colonne,
	 * pas des seuls blocs. `$choices` sont les entrées que l'objet propose au premier niveau, dans
	 * l'ordre de ses types, une par variante pour une forme fixée (`BlockLib::getChoices()`) : le choix
	 * du premier bloc, le « + » et le « / » les lisent là. `$cBlockNew` porte un bloc à écrire par entrée
	 * et un enfant par parent (`BlockLib::getNew()`), que ces trois gestes posent.
	 *
	 * Le choix du premier bloc est toujours dans la page, devant la liste, et masqué tant que l'objet
	 * porte un bloc : vider l'objet ou y écrire son premier bloc ne fait que basculer sa classe. Un
	 * « + » attend à côté (`data-block-beside`) : le script en pose une copie sous tout bloc à écrire de
	 * premier niveau (`BlockEditor.place()`), et une en tête de la liste d'un objet vide, qui n'en porte
	 * pas (`BlockEditor.placeFirst()`).
	 *
	 * La colonne de droite porte le plan, qu'elle perd sous 768 px.
	 */
	public function getEditor(\Collection $cBlock, array $choices, \Collection $cBlockNew, string $owner, int $reference, string $below = ''): string {

		\Asset::css('block', 'read.css');
		\Asset::css('block', 'block.css');
		\Asset::js('block', 'block.js');
		\Asset::lib('util', 'Sortable-1.11.0.js');

		$h = '<div class="block-editor" data-owner="'.encode($owner).'" data-reference="'.$reference.'" onrender="BlockEditor.init(this)">';

			// L'éditeur ne pose ses libellés qu'une fois par requête, avec le premier champ rendu : ce
			// champ dort ici dans un `<template>`, dont aucun script ne court au chargement. Posés avant
			// la liste, ils valent pour tous les blocs.
			$h .= new \editor\EditorUi()->init(FALSE);

			foreach($cBlockNew as $eBlockNew) {
				$h .= $this->getNew($eBlockNew);
			}

			$h .= $this->getMenu($choices);

			$h .= '<template data-block-beside>'.$this->getInsert($choices).'</template>';

			$h .= '<div class="block-main">';

				$h .= $this->getStart($choices, $cBlock);

				$h .= '<div id="block-list" class="block-list">';

					if($cBlock->notEmpty()) {
						$h .= $this->getList($cBlock);
					}

				$h .= '</div>';

				$h .= $below;

			$h .= '</div>';

			$h .= '<div class="block-aside">';
				$h .= $this->getPlan($cBlock, $owner, $reference);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La liste des entrées de l'objet, rendue une fois dans la page : le « + » d'un objet à plusieurs
	 * entrées en remplit son menu à l'ouverture (`BlockEditor.fillMenu()`), et le « / » la sienne. Une
	 * entrée n'y est qu'un nom et une icône, et sa clé (`data-type`) : le formulaire qu'elle ouvre est dans
	 * le `<template data-block-new>` de la même clé.
	 */
	public function getMenu(array $choices): string {

		$h = '<template data-block-menu>';

			foreach($choices as $choice => ['type' => $type, 'variant' => $variant]) {

				$typeUi = $this->getTypeUi($type);

				$h .= '<a class="dropdown-item" data-block-choice data-type="'.encode($choice).'">'.\Asset::icon($typeUi->getIcon($type)).' '.$typeUi->getName($type, NULL, $variant).'</a>';

			}

		$h .= '</template>';

		return $h;

	}

	/**
	 * Le choix du premier bloc d'un objet qui n'en a aucun : une tuile par entrée, avec ce que le type
	 * contient. Un clic pose le bloc à écrire de l'entrée dans la liste (`BlockEditor.start()`), et
	 * « Annuler » ramène ici (`BlockEditor.discard()`).
	 *
	 * Les tuiles vivent hors de `.block-list`, où `read.css` écarte deux `util-deck` voisins comme les
	 * volets d'un accordéon.
	 */
	public function getStart(array $choices, \Collection $cBlock): string {

		$h = '<div id="block-start"'.($cBlock->empty() ? '' : ' class="hide"').'>';

			$h .= '<div class="util-title">';
				$h .= '<h3>'.s("Premier bloc").'</h3>';
			$h .= '</div>';

			$h .= '<div class="util-cards util-cards-2">';

				foreach($choices as $choice => ['type' => $type, 'variant' => $variant]) {

					$typeUi = $this->getTypeUi($type);

					$h .= '<a class="util-deck" data-block-choice data-type="'.encode($choice).'">';
						$h .= '<div class="util-deck-head">';
							$h .= '<div class="util-icon">'.\Asset::icon($typeUi->getIcon($type)).'</div>';
							$h .= '<div class="util-deck-body">';
								$h .= '<div class="util-deck-title">'.$typeUi->getName($type, NULL, $variant).'</div>';
								$h .= '<p>'.$typeUi->getDescription($type, $variant).'</p>';
							$h .= '</div>';
						$h .= '</div>';
					$h .= '</a>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bloc à écrire qu'ajoutent le « + » et le choix du premier bloc, dans un `<template>` que le
	 * script pose juste après le bloc qui portait le « + » (`BlockEditor.insert()`) ou sous le « + » de
	 * tête d'une liste vide (`BlockEditor.start()`). Il n'a pas de brouillon : rien ne le rend de
	 * nouveau au rechargement, donc rien ne relirait sa clé. Il naît à son premier enregistrement, et
	 * son brouillon est alors celui de son id (`getItem()`). Son type est posté, et jugé par l'usage
	 * (`Block::type.allowed`). Le template porte la clé de son entrée (`BlockLib::getChoices()`), par
	 * laquelle le script le retrouve.
	 *
	 * Un enfant à écrire a la forme et la place que lui donne son parent (`data-block-parent`) : une
	 * étape dans la liste des étapes, un panneau d'onglet, une tuile de chiffre.
	 */
	public function getNew(Block $eBlock): string {

		$eBlock->expects(['choice', 'type', 'parentType']);

		$writable = $this->getWritable($eBlock);

		if($eBlock['parentType'] === NULL) {
			$h = '<template data-block-new="'.encode($eBlock['choice']).'">';
				$h .= '<div id="block-new"'.$this->getSectionMark($eBlock).'>'.$writable.'</div>';
			$h .= '</template>';
		} else {
			$h = '<template data-block-new="'.encode($eBlock['choice']).'" data-block-parent="'.encode($eBlock['parentType']).'">';
				$h .= $this->getTypeUi($eBlock['parentType'])->getUnit($eBlock['parentType'], $eBlock, $writable, TRUE);
			$h .= '</template>';
		}

		return $h;

	}

	/**
	 * Un bloc à écrire a la forme d'un bloc ouvert : sa barre porte son nom, et ni flèches, ni
	 * Dupliquer, ni Supprimer, ni « + », puisqu'il n'existe pas encore (`getTools()`). Son formulaire
	 * le crée au premier enregistrement, avec ce qui y est écrit.
	 *
	 * Le formulaire porte la clé de son entrée (`data-block-key`) : le script y reconnaît le bloc à écrire
	 * qu'un « + » demande de nouveau (`BlockEditor.insert()`), deux formes fixées d'un même type n'étant
	 * pas le même bloc. La variante d'une forme fixée part avec le type : sa barre n'a pas de menu qui
	 * l'écrive (`getVariantMenu()`), et la mise à jour ne la construit pas.
	 */
	private function getWritable(Block $eBlock): string {

		$eBlock->expects(['owner', 'reference', 'choice', 'type', 'variantFixed', 'parentType', 'editorOptions']);

		$this->skipEditorLabels();

		$form = new \util\FormUi();

		$h = '<div class="'.$this->getItemClass($eBlock).' block-item-selected">';

			$h .= $this->getTools($eBlock);

			$h .= $form->openAjax('/block/block:doCreate', ['class' => 'block-form', 'id' => $this->getFormId($eBlock), 'data-block-key' => $eBlock['choice']]);
				$h .= $form->hidden('owner', $eBlock['owner']);
				$h .= $form->hidden('reference', $eBlock['reference']);
				$h .= $form->hidden('draft', 1);
				$h .= $form->hidden('type', $eBlock['type']);

				if($eBlock['variantFixed']) {
					$h .= $form->hidden('variant', $eBlock['variant']);
				}

				$h .= $this->getTypeUi($eBlock['type'])->getEditable($eBlock, $form, NULL);
			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les blocs de la liste, tous lus : un bloc ne s'ouvre que dans la page, au clic. Le « + » de tête
	 * précède le premier : c'est le seul qu'aucun bloc ne porte, et aucune réponse ne le touche, les
	 * blocs se rangeant tous après lui (`BlockEditor.savePositions()`). Les entrées se lisent avant la
	 * boucle, une `Collection` n'ayant qu'un curseur.
	 */
	public function getList(\Collection $cBlock): string {

		$h = $this->getInsert($cBlock->first()['choices']);

		foreach($cBlock as $eBlock) {
			$h .= $this->getItem($eBlock);
		}

		return $h;

	}

	/**
	 * Un bloc lu, dans l'éditeur : un clic l'ouvre. Ses liens ne mènent nulle part tant qu'on
	 * édite, le clic appartient au bloc (`BlockEditor.init()`).
	 *
	 * Le bloc ouvert est dans le `<template>` : sa barre en tête et son formulaire à la place du
	 * rendu. Le « + » est hors du template, pour rester là que le bloc soit ouvert ou non : il ajoute
	 * un bloc sous celui-ci, et suit son bloc dans toutes les réponses qui remplacent, déplacent ou
	 * retirent son enveloppe. Un template n'exécute rien et n'arme aucun éditeur : il n'y a d'instance
	 * vivante que pour le bloc que le script en a sorti (`BlockEditor.open()`). Les flèches du premier
	 * et du dernier bloc de premier niveau se masquent dans `block.css`, par leur place, pour qu'un
	 * geste sur la liste n'ait pas à rendre ses voisins ; un enfant se rend toujours avec toute sa
	 * fratrie (`block.v.php`), qui les décide ici (`getTools()`).
	 *
	 * Un enfant n'a pas de « + » à lui : celui de son parent ajoute au bout (`BlockTypeUi::render()`).
	 * Le brouillon se range sous l'id du bloc : le texte d'un départ confirmé se retrouve à sa
	 * réouverture.
	 */
	public function getItem(Block $eBlock): string {

		$eBlock->expects(['choices', 'editorOptions', 'parentType']);

		if($eBlock['cBlock']->notEmpty()) {
			return $this->getComposite($eBlock);
		}

		$this->skipEditorLabels();

		$form = new \util\FormUi();

		$h = '<div id="block-'.$eBlock['id'].'" data-block="'.$eBlock['id'].'"'.$this->getSectionMark($eBlock).'>';

			// Un texte vidé reste un bloc (`BlockTypeLib::isEmpty()`) : lu, il montre l'invite de l'éditeur
			// au lieu d'une bande vide (`block.css`), sa `.reador` n'ayant alors aucun enfant.
			$h .= '<div class="'.$this->getItemClass($eBlock).'">';
				$h .= $this->getHint($eBlock);
				$h .= $this->getTypeUi($eBlock['type'])->render($eBlock, ['data-placeholder-empty' => s("Commencez à écrire...")]);
			$h .= '</div>';

			$h .= '<template>';

				$h .= '<div class="'.$this->getItemClass($eBlock).' block-item-selected">';

					$h .= $this->getTools($eBlock);

					// Seul un texte de premier niveau se coupe au « / » (`BlockSlash`, `doSplit`).
					$h .= $form->openAjax('/block/block:doUpdate', [
						'class' => 'block-form',
						'id' => $this->getFormId($eBlock),
						'data-block-split' => ($eBlock['type'] === BlockTypeLib::TEXT and $eBlock['parentType'] === NULL) ? '' : NULL,
					]);
						$h .= $form->hidden('id', $eBlock);
						$h .= $this->getTypeUi($eBlock['type'])->getEditable($eBlock, $form, 'block-'.$eBlock['id']);
					$h .= $form->close();

				$h .= '</div>';

			$h .= '</template>';

			if($eBlock['parentType'] === NULL) {
				$h .= $this->getInsert($eBlock['choices']);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'enveloppe d'une section porte `data-block-section` : dans l'éditeur, le bandeau court d'une
	 * section à la suivante par l'ordre des enveloppes (`block.css`), sans classe à recalculer quand un
	 * geste ajoute, retire ou déplace un bloc.
	 */
	private function getSectionMark(Block $eBlock): string {
		return ($eBlock['type'] === BlockTypeLib::SECTION) ? ' data-block-section' : '';
	}

	/**
	 * Un bloc parent (`data-block-composite`) n'a pas de formulaire : il n'écrit rien lui-même, ses
	 * enfants écrivent (`BlockTypeLib::getProperties()`). Chacun des enfants est un bloc de l'éditeur,
	 * avec son enveloppe et son formulaire, posé dans la forme du parent, et le « + » du parent ajoute
	 * au bout, tant que le parent n'est pas plein (`full`). Choisi, le parent montre sa barre, qui
	 * déplace, copie ou retire le parent avec ses enfants : elle n'est pas dans un `<template>`, n'ayant
	 * aucun éditeur à armer, et `block.css` la montre. Un enfant ouvert y met la sienne
	 * (`BlockEditor.fillBand()`).
	 */
	protected function getComposite(Block $eBlock): string {

		$eBlock->expects(['full']);

		$h = '<div id="block-'.$eBlock['id'].'" data-block="'.$eBlock['id'].'" data-block-composite>';

			$h .= '<div class="block-item">';
				$h .= $this->getHint($eBlock);
				$h .= $this->getTools($eBlock);
				$h .= $this->getTypeUi($eBlock['type'])->render($eBlock, [], fn(Block $eBlockChild) => $this->getItem($eBlockChild), $eBlock['full'] === FALSE);
			$h .= '</div>';

			$h .= $this->getInsert($eBlock['choices']);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un enfant n'a ni le débord ni le nom au survol d'un bloc de premier niveau : il est dans le fond
	 * de son parent (`block-child`, `block.css`).
	 */
	private function getItemClass(Block $eBlock): string {
		return ($eBlock['parentType'] === NULL) ? 'block-item' : 'block-item block-child';
	}

	/**
	 * Le nom d'un bloc de premier niveau, montré au survol dans l'écart au-dessus de lui (`block.css`) :
	 * on sait ce qu'on va ouvrir. Un enfant se nomme dans la barre de son parent, une fois ouvert. Le nom
	 * est celui de la forme, pour un type à forme fixée, comme dans la barre et le plan.
	 */
	private function getHint(Block $eBlock): string {

		if($eBlock['parentType'] !== NULL) {
			return '';
		}

		$typeUi = $this->getTypeUi($eBlock['type']);

		return '<span class="block-hint">'.\Asset::icon($typeUi->getIcon($eBlock['type'])).' '.$typeUi->getName($eBlock['type'], NULL, $eBlock['variant']).'</span>';

	}

	/**
	 * Le formulaire d'un bloc est nommé : la couleur d'un encadré, dans la barre, et le nom d'un
	 * onglet, dans la tête de ses onglets, s'y rattachent sans être dedans. Un seul bloc à écrire est
	 * ouvert à la fois.
	 */
	public function getFormId(Block $eBlock): string {
		return $eBlock->exists() ? 'block-form-'.$eBlock['id'] : 'block-form-new';
	}

	/**
	 * Les libellés de l'éditeur sont dans la page, posés une fois par `getEditor()`. Sans cet appel,
	 * une réponse qui rend un bloc les répéterait avec son premier champ (`EditorUi::init()`), et le
	 * bloc rendu par un geste ne serait plus celui que rend un rechargement.
	 */
	private function skipEditorLabels(): void {
		new \editor\EditorUi()->init(FALSE);
	}

	/**
	 * La barre du bloc ouvert, dans une bande en tête de son fond : son nom, puis ses gestes. Celle
	 * d'un enfant (`block-tools-child`) prend la place de celle de son parent quand il s'ouvre
	 * (`BlockEditor.fillBand()`) : un bloc n'a jamais qu'une barre. Chaque geste porte l'id de son
	 * bloc, qu'il ne peut pas lire à sa place dans la page une fois déplacé dans la bande du parent.
	 *
	 * Un enfant se range à gauche et à droite dans une rangée (onglets, chiffres), en haut et en bas
	 * dans une colonne. Le premier et le dernier n'ont pas la flèche qui les sortirait de la fratrie.
	 * Un enfant d'un parent plein ne se copie pas (`full`, `BlockLib::duplicate()`).
	 *
	 * Un bloc à écrire de premier niveau porte « Annuler », qui le retire sans rien enregistrer
	 * (`BlockEditor.cancel()`) : sur un objet vide, rien d'autre ne ramène au choix du premier bloc. La
	 * question à poser quand le bloc porte déjà du texte voyage dans l'attribut, le script étant seul
	 * à savoir ce qui est écrit. Un enfant à écrire n'en a pas : sa barre est celle de son parent.
	 *
	 * Tout bloc ouvert en écriture finit sa barre par « Enregistrer » (`BlockEditor.submit()`), réduit à
	 * son icône sous 576 px. Un parent n'a pas de formulaire (`getComposite()`) : sa barre n'en porte pas,
	 * et le repère des étapes s'enregistre dès qu'on le choisit (`getVariantMenu()`).
	 */
	protected function getTools(Block $eBlock): string {

		$typeUi = $this->getTypeUi($eBlock['type']);
		$parentType = $eBlock['parentType'];
		$composite = ($eBlock->exists() and $eBlock['cBlock']->notEmpty());

		$h = '<div class="block-tools'.($parentType === NULL ? '' : ' block-tools-child').'">';

			$h .= '<span class="block-type">'.\Asset::icon($typeUi->getIcon($parentType ?? $eBlock['type'])).' '.$typeUi->getName($eBlock['type'], $parentType, $eBlock['variant']).'</span>';

			$h .= $this->getVariantMenu($eBlock, $composite);

			if($eBlock->exists()) {

				$horizontal = in_array($parentType, [BlockTypeLib::TABS, BlockTypeLib::FIGURE], TRUE);

				// Monter, descendre et dupliquer passent par le script, qui enregistre d'abord le texte en
				// cours d'écriture (`BlockEditor.save()`). Supprimer n'a rien à garder.
				if($parentType === NULL or $eBlock['position'] > 0) {
					$h .= '<a data-block-move="-1" post-id="'.$eBlock['id'].'" class="btn btn-only-icon btn-sm btn-outline-primary" title="'.($horizontal ? s("Déplacer à gauche") : s("Monter")).'">'.\Asset::icon($horizontal ? 'arrow-left' : 'arrow-up').'</a>';
				}

				if($parentType === NULL or $eBlock['last'] === FALSE) {
					$h .= '<a data-block-move="1" post-id="'.$eBlock['id'].'" class="btn btn-only-icon btn-sm btn-outline-primary" title="'.($horizontal ? s("Déplacer à droite") : s("Descendre")).'">'.\Asset::icon($horizontal ? 'arrow-right' : 'arrow-down').'</a>';
				}

				// Une vidéo ne se copie pas : l'objet n'en porte qu'une (`Block::url.video`).
				if(
					$eBlock['type'] !== BlockTypeLib::VIDEO and
					($parentType === NULL or $eBlock['full'] === FALSE)
				) {
					$h .= '<a data-block-duplicate post-id="'.$eBlock['id'].'" class="btn btn-only-icon btn-sm btn-outline-primary" title="'.s("Dupliquer").'">'.\Asset::icon('copy').'</a>';
				}

				$h .= '<a data-ajax="/block/block:doDelete" post-id="'.$eBlock['id'].'" class="btn btn-only-icon btn-sm btn-outline-primary" data-confirm="'.s("Supprimer ce bloc ?").'" title="'.s("Supprimer").'">'.\Asset::icon('trash').'</a>';

			} else if($parentType === NULL) {
				$h .= '<a data-block-discard="'.s("Annuler ce bloc ? Ce que vous y avez écrit sera perdu.").'" class="btn btn-sm btn-outline-primary">'.s("Annuler").'</a>';
			}

			if($composite === FALSE) {
				$h .= '<a data-block-save class="btn btn-sm btn-primary">'.\Asset::icon('check-lg').'<span class="hide-xs-down"> '.s("Enregistrer").'</span></a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le réglage d'un type à variantes, choisi dans la barre : la couleur d'un encadré, la disposition
	 * d'une vidéo, le repère des étapes, la meilleure réponse. Chaque entrée écrit le champ caché `variant`
	 * du formulaire du bloc (`BlockTypeUi::getEditable()`) et part avec son enregistrement
	 * (`BlockEditor.setVariant()`). Le menu vit dans `body` une fois ouvert (`Lime.Dropdown`) : chaque
	 * entrée nomme donc son formulaire.
	 * Un parent enregistré n'a pas de formulaire (`getComposite()`) : ses entrées portent son id et
	 * s'enregistrent dès qu'on les choisit (`BlockEditor.saveVariant()`).
	 *
	 * Le nom du réglage, ce qui précède chaque entrée, le séparateur qui la devance et la classe
	 * qu'elle pose sur le bloc viennent du type (`getVariantLabel()`, `getVariantMark()`,
	 * `hasVariantDivider()`, `getVariantClass()`) : le moteur ne sait pas ce qu'une variante change.
	 *
	 * Une forme fixée (`variantFixed`) n'a pas de menu : sa variante est choisie au « + », et la barre
	 * la dit dans son nom.
	 */
	protected function getVariantMenu(Block $eBlock, bool $composite): string {

		if(
			$eBlock['variants'] === [] or
			$eBlock['variantFixed']
		) {
			return '';
		}

		$typeUi = $this->getTypeUi($eBlock['type']);
		$values = self::p('variant')->values;

		$h = '<a data-dropdown="bottom-end" class="btn btn-sm btn-outline-primary dropdown-toggle">'.$typeUi->getVariantLabel($eBlock['type']).'</a>';
		$h .= '<div class="dropdown-list">';

			foreach($eBlock['variants'] as $variant) {

				if($typeUi->hasVariantDivider($eBlock['type'], $variant)) {
					$h .= '<div class="dropdown-divider"></div>';
				}

				$h .= '<a class="dropdown-item'.($variant === $eBlock['variant'] ? ' selected' : '').'" data-block-variant="'.encode($variant).'" data-block-variant-class="'.encode($typeUi->getVariantClass($eBlock['type'], $variant)).'" '.($composite ? 'post-id="'.$eBlock['id'].'"' : 'data-form="'.$this->getFormId($eBlock).'"').'>';
					$h .= $typeUi->getVariantMark($eBlock['type'], $variant).$values[$variant];
				$h .= '</a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un objet qui ne propose qu'une entrée, ou un parent qui n'accepte qu'un type d'enfant, ajoute ce
	 * bloc sans rien demander. Plusieurs entrées ouvrent un menu, vide ici et rempli par le script à
	 * l'ouverture depuis la liste de la page (`getMenu()`) : chaque « + » n'en porte pas une copie.
	 *
	 * Le « + » ne porte pas de place : le bloc à écrire lit la sienne dans la page quand il s'enregistre
	 * (`BlockEditor.save()`), le plan ayant pu bouger depuis.
	 */
	public function getInsert(array $choices): string {

		$h = '<div class="block-insert">';

			if(count($choices) === 1) {
				$h .= '<a data-block-insert data-type="'.encode(array_key_first($choices)).'" class="btn btn-only-icon btn-sm btn-outline-primary" title="'.s("Ajouter un bloc ici").'">'.\Asset::icon('plus-lg').'</a>';
			} else {
				$h .= '<a data-dropdown="bottom-start" data-block-insert-menu class="btn btn-sm btn-outline-primary dropdown-toggle" title="'.s("Ajouter un bloc ici").'">'.\Asset::icon('plus-lg').'</a>';
				$h .= '<div class="dropdown-list dropdown-list-3 block-insert-list"></div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le plan : une ligne par bloc, dans l'ordre, que l'on range à la poignée. Il s'efface sous
	 * 768 px, où les flèches du bloc ouvert rangent seules. Son titre vient de l'usage (« Plan de la
	 * leçon ») : le moteur ne nomme pas les objets qui portent ses blocs.
	 *
	 * Le plan ne compte que les blocs enregistrés : un objet sans bloc le dit, y compris pendant
	 * l'écriture de son premier bloc. Une ligne seule n'a rien avec quoi s'échanger : sa poignée se
	 * masque dans `block.css`, par sa place, comme les flèches d'un bloc seul, pour qu'un bloc ajouté
	 * n'ait à apporter que sa ligne (`block.v.php`, `doCreate`).
	 */
	public function getPlan(\Collection $cBlock, string $owner, int $reference): string {

		$ownerUi = BlockSetting::resolve($owner, 'BlockOwnerUi');

		$h = '<div id="block-plan">';

			$h .= '<div class="util-title">';
				$h .= '<h4>'.new $ownerUi()->getPlanTitle($owner).'</h4>';
				if($cBlock->notEmpty()) {
					$h .= '<span class="util-counter">'.$cBlock->count().'</span>';
				}

			$h .= '</div>';

			if($cBlock->empty()) {
				$h .= '<div class="util-empty">'.s("Aucun bloc").'</div>';
			} else {

				$h .= '<div class="util-deck util-deck-open">';

					$h .= '<div class="util-deck-fold" onrender="BlockEditor.initPlan(this)" data-sort-url="/block/block:doUpdatePositions" post-owner="'.encode($owner).'" post-reference="'.$reference.'">';

						foreach($cBlock as $eBlock) {
							$h .= $this->getPlanRow($eBlock);
						}

					$h .= '</div>';

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne du plan nomme le type de son bloc, sauf celle d'une section, qui écrit son titre : le plan
	 * se lit alors comme la table des parties de l'objet.
	 */
	public function getPlanRow(Block $eBlock): string {

		$typeUi = $this->getTypeUi($eBlock['type']);

		$name = ($eBlock['type'] === BlockTypeLib::SECTION) ? '<b>'.encode($eBlock['title']).'</b>' : $typeUi->getName($eBlock['type'], NULL, $eBlock['variant']);

		$h = '<a id="block-plan-'.$eBlock['id'].'" class="util-deck-row" data-sort-id="'.$eBlock['id'].'" data-block-plan="'.$eBlock['id'].'">';
			$h .= '<span class="util-drag-handle">'.\Asset::icon('grip-vertical').'</span>';
			$h .= '<span class="util-deck-row-name">'.\Asset::icon($typeUi->getIcon($eBlock['type'])).' '.$name.'</span>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * La paire d'un type, résolue depuis sa valeur (`block-text` → `\block\BlockTypeUi`) : le
	 * moteur ne nomme aucun type, pas même les siens.
	 */
	private function getTypeUi(string $type): object {

		$class = BlockSetting::resolve($type, 'BlockTypeUi');

		return new $class();

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Block::model()->describer($property, [
			'type' => s("Type"),
			'variant' => s("Couleur"),
			'title' => s("Titre"),
			'content' => s("Texte"),
			'url' => s("Adresse"),
		]);

		switch($property) {

			case 'variant' :
				$d->values = [
					BlockTypeLib::INFO => s("Information"),
					BlockTypeLib::SUCCESS => s("Conseil"),
					BlockTypeLib::WARNING => s("Attention"),
					BlockTypeLib::FULL => s("Pleine largeur"),
					BlockTypeLib::SMALL => s("Petite"),
					BlockTypeLib::LEFT => s("À gauche du texte"),
					BlockTypeLib::RIGHT => s("À droite du texte"),
					BlockTypeLib::NUMBERS => s("Numéros"),
					BlockTypeLib::CHECK => s("Coches"),
					BlockTypeLib::REGULAR => s("Ordinaire"),
					BlockTypeLib::BEST => s("Meilleure réponse"),
				];
				break;

		}

		return $d;

	}

}
?>
