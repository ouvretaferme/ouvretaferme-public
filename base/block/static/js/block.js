/**
 * L'éditeur des blocs, direction A de la planche : on écrit sur le rendu de la page.
 *
 * Chaque bloc lu porte son formulaire dans un `<template>` (`BlockUi::getItem()`). L'ouvrir le sort à
 * la place du rendu, qu'on masque sans le retirer, et pose le curseur au point cliqué : aucune requête,
 * donc aucune frappe perdue pendant un aller-retour. Le template se pose par `renderAdjacentHTML()`,
 * qui lance son script d'éditeur une fois, et c'est la seule instance vivante. Un clone du template
 * ne le lancerait pas sur une page arrivée par la navigation ajax : posée par innerHTML, elle marque
 * ses scripts comme déjà exécutés, et `cloneNode()` garde la marque.
 *
 * Le « + » ouvre de même, sans requête, un bloc à écrire posé depuis un template de la page
 * (`BlockUi::getNew()`), qui ne naît en base qu'à son premier enregistrement (`insert()`). Les « + »
 * sont toujours dans la page, un au-dessus du premier bloc et un sous chacun : on ajoute un bloc
 * sans en ouvrir un d'abord. Un usage à plusieurs types ouvre un menu, rempli à l'ouverture depuis la
 * liste de la page (`BlockUi::getMenu()`), la même que celle du « / » (`BlockSlash`, plus bas).
 *
 * Un objet sans bloc n'a ni « + » ni bloc ouvert d'office : il montre le choix du premier bloc
 * (`BlockUi::getStart()`), dont une tuile pose le bloc à écrire de son type (`start()`). Tout bloc à
 * écrire de premier niveau est entre deux « + », pour ajouter au-dessus ou au-dessous de lui avant de
 * l'avoir enregistré (`place()`). « Annuler », dans la barre d'un bloc à écrire de premier niveau, le retire sans
 * l'enregistrer (`cancel()`), et le choix revient dès que la liste est vide (`discard()`).
 *
 * Un bloc parent (`data-block-composite`) n'a pas de formulaire : ses enfants sont des blocs de
 * l'éditeur comme les autres, dans sa forme. Le choisir montre sa barre et le « + » de ses enfants
 * (`select()`). Un enfant ouvert n'a pas de bande à lui : sa barre prend la place de celle du parent,
 * et le nom d'un onglet s'écrit dans sa tête (`fillBand()`).
 *
 * Les réglages du bloc ouvert sont dans sa barre : la couleur d'un encadré se choisit dans un menu qui
 * écrit le champ caché de son formulaire (`setVariant()`). Le repère d'un bloc d'étapes enregistré,
 * qui n'a pas de formulaire, s'enregistre dès qu'on le choisit (`saveVariant()`).
 *
 * Tout geste qui quitte le bloc ouvert l'enregistre s'il a changé :
 * - un clic ailleurs le referme (`close()`), en une requête qui enregistre et rend le bloc lu, et
 *   « Enregistrer », dans sa barre, fait de même (`submit()`) ;
 * - un clic sur un autre bloc ou sur un « + » le referme de même, et ouvre l'autre sans attendre,
 *   sauf un bloc à écrire en cours de création, dont le « + » attend la réponse ;
 * - déplacer, dupliquer, ranger au plan et quitter l'écran attendent l'enregistrement, parce que le
 *   serveur relit la liste ;
 * - un départ que rien ne précède (retour arrière, rechargement, onglet fermé) passe par
 *   `navigationStart` et `beforeunload` plus bas, avec le brouillon de l'éditeur pour filet.
 *
 * Un enregistrement refusé affiche son erreur sous le champ, laisse le bloc ouvert et arrête le geste,
 * pour ne rien perdre de ce qui a été écrit. Les réponses sont des instructions sur les nœuds touchés
 * (`base/block/view/block.v.php`) : le script n'écrit aucun HTML, sauf l'ordre des blocs après un
 * glisser dans le plan, qu'il connaît déjà puisque c'est lui qui vient de le poster, et l'aperçu du
 * texte coupé par le « / » le temps que le serveur réponde (`BlockSlash.choose()`).
 */
class BlockEditor {

	/**
	 * L'enregistrement en cours, rendu à tout appel qui arrive pendant qu'il court : deux clics
	 * rapprochés ne postent qu'une fois.
	 */
	static saving = null;

	/**
	 * Le rendu peut repasser sur un éditeur déjà armé (onrender après une navigation ajax) : le
	 * drapeau évite de poser deux fois les écouteurs, qui enverraient deux fois chaque geste.
	 */
	static init(editor) {

		if(editor.dataset.blockReady === '1') {
			return;
		}

		editor.dataset.blockReady = '1';

		// Dans l'éditeur, un clic sur le texte d'un bloc lu l'ouvre, il ne suit pas le lien qu'il
		// porte : `block.css` retire les liens de ces blocs à la souris, et le clic arrive au bloc.
		//
		// Refermer le bloc ouvert peut déplacer celui qu'on vise, s'il est dessous : le point cliqué
		// se rapporte donc à la place qu'avait son texte au moment du clic, mesurée avant.
		editor.addEventListener('click', e => {

			// Une tête d'onglet choisie pendant qu'un enfant du parent est ouvert : le panneau change
			// (`Lime.Tab.select()`, dans son `onclick`, avant ce délégué), et l'enfant ouvert, qu'on ne
			// voit plus, se referme. Le parent reste choisi. La tête qui porte un champ est celle de
			// l'onglet ouvert : on y écrit son nom.
			const tab = e.target.closest('.tab-item[data-tab]');

			if(
				tab !== null &&
				tab.qs('input') === null
			) {

				const composite = tab.closest('[data-block-composite]');

				if(
					composite !== null &&
					composite.qs(':scope > .block-item-child-open') !== null
				) {

					if(BlockEditor.release()) {
						BlockEditor.select(composite);
					}

					return;

				}

			}

			const item = e.target.closest('.block-item');

			if(
				item === null ||
				item.classList.contains('block-item-selected')
			) {
				return;
			}

			const block = item.closest('[data-block]');

			if(block === null) {
				return;
			}

			e.preventDefault();

			if(block.dataset.blockComposite !== undefined) {

				if(BlockEditor.release()) {
					BlockEditor.select(block);
				}

				return;

			}

			const readTop = BlockEditor.getReadTop(item);

			if(BlockEditor.release() === false) {
				return;
			}

			BlockEditor.open(block, e, readTop);

		});

		editor.addEventListener('click', e => {

			const insert = e.target.closest('[data-block-insert]');

			if(insert !== null) {
				e.preventDefault();
				BlockEditor.insert(insert, insert.dataset.type);
				return;
			}

			const start = e.target.closest('#block-start [data-block-choice]');

			if(start !== null) {
				e.preventDefault();
				BlockEditor.start(start.dataset.type);
				return;
			}

			const discard = e.target.closest('[data-block-discard]');

			if(discard !== null) {
				e.preventDefault();
				BlockEditor.cancel(discard);
				return;
			}

			if(e.target.closest('[data-block-save]') !== null) {
				e.preventDefault();
				BlockEditor.submit();
				return;
			}

			const move = e.target.closest('[data-block-move]');

			if(move !== null) {
				e.preventDefault();
				BlockEditor.move(move);
				return;
			}

			const duplicate = e.target.closest('[data-block-duplicate]');

			if(duplicate !== null) {
				e.preventDefault();
				BlockEditor.duplicate(duplicate);
				return;
			}

			const plan = e.target.closest('[data-block-plan]');

			if(
				plan !== null &&
				e.target.closest('.util-drag-handle') === null
			) {
				e.preventDefault();
				BlockEditor.reach(plan);
				return;
			}

			// Le bord du bloc à écrire est hors de son éditeur : un clic y laisserait le focus sur la
			// page, et ce qu'on tape ensuite ne s'écrirait nulle part. C'est ce que fait le second clic
			// d'un double clic sur le « + », qui tombe sur le bloc à peine ouvert à sa place. Un clic sur
			// un champ du formulaire (le titre d'un encadré, la valeur d'un chiffre) lui a déjà donné la
			// main au `mousedown` : la reprendre ici enverrait la frappe dans le texte.
			const writable = e.target.closest('#block-new');

			if(
				writable !== null &&
				e.target.closest('.editor, input, textarea, select, label, button, .block-insert') === null
			) {
				BlockEditor.getEditorShown(writable)?.focus();
			}

		});

		// L'écoute se pose sur l'éditeur des blocs et non sur le document : un éditeur de texte hors bloc
		// ne propose rien au « / », même quand ce fichier reste chargé après une navigation ajax.
		editor.addEventListener('editorSlash', e => BlockSlash.open(e.target, e.detail.range));

		// `input` couvre la frappe, le collage et la mise en forme de la barre de l'éditeur, qui passe
		// par `execCommand` : c'est le seul signe fiable qu'un texte a changé depuis son ouverture. Le nom
		// d'un onglet, écrit dans la tête du parent, est rattaché à son formulaire sans être dedans
		// (`form`). Seul un champ porte cette propriété : sur tout autre élément, `form` est la méthode
		// que `lime.js` ajoute à `Element.prototype`, et non un formulaire.
		editor.addEventListener('input', e => {

			const form = (e.target.form instanceof HTMLFormElement) ? e.target.form : e.target.closest('.block-form');

			if(form !== null) {
				form.dataset.blockDirty = '1';
			}

			if(e.target.matches('.tab-item > input.util-inplace-field, .block-button input.util-inplace-field, .block-tags-edit input.util-inplace-field')) {
				BlockEditor.sizeField(e.target);
			}

		});

	}

	/**
	 * Le plan est remplacé à chaque geste qui change la liste : il se réarme à chaque rendu.
	 */
	static initPlan(fold) {

		new Sortable(fold, {
			handle: '.util-drag-handle',
			draggable: '[data-sort-id]',
			ghostClass: 'util-drag-ghost',
			chosenClass: 'util-drag-chosen',
			forceFallback: true,
			fallbackOnBody: true,
			onEnd: e => {

				if(e.oldIndex === e.newIndex) {
					return;
				}

				BlockEditor.savePositions(fold);

			}
		});

	}

	static getEditor() {
		return qs('.block-editor');
	}

	/**
	 * Le formulaire du bloc ouvert, ou du bloc à écrire. Celui d'un bloc qu'on vient de quitter reste
	 * dans la page le temps de son enregistrement, `inert` : il n'est plus le bloc ouvert.
	 *
	 * Un bloc retiré par `remove(vanish: TRUE)` laisse 0,15 s dans `#block-list` une copie de lui-même,
	 * `inert` sur sa racine, qui garde son formulaire sans id d'éditeur (`MainJuiciness.vanish()`). Aucun
	 * vrai bloc n'est jamais `inert` : `[data-block][inert]` est cette copie, et chaque sélecteur de ce
	 * fichier qui part de la page plutôt que d'un bloc précis l'écarte.
	 */
	static getForm() {
		return qs('.block-editor .block-form:not([inert], [data-block][inert] *)');
	}

	/**
	 * Un texte que l'éditeur a récupéré de son brouillon (sa barre « modifications récupérées ») a
	 * changé sans aucun `input` : il compte comme changé.
	 */
	static isDirty(form) {
		return (
			form.dataset.blockDirty === '1' ||
			form.qs('.editor-draft-bar') !== null
		);
	}

	/**
	 * Quitter l'écran maintenant perdrait-il quelque chose : un bloc changé, ou un enregistrement qui
	 * n'a pas encore répondu.
	 */
	static isModified() {

		const form = BlockEditor.getForm();

		return (
			BlockEditor.saving !== null ||
			(form !== null && BlockEditor.isDirty(form))
		);

	}

	/**
	 * Ouvre un bloc lu. Tout se fait dans le geste, sans requête : le clavier s'ouvre sur un
	 * téléphone, qui refuse un `focus()` différé, et les premières frappes tombent dans l'éditeur.
	 *
	 * Le formulaire a la géométrie du rendu (`inline`), donc le point cliqué désigne le même endroit
	 * du texte avant et après. Seul le haut du texte peut bouger, quand les outils prennent leur place
	 * dans le bloc au doigt, ou quand le bloc refermé juste avant était au-dessus : l'écart entre le
	 * haut du texte au moment du clic (`readTop`, mesuré ici faute de mieux) et celui du champ se
	 * retranche du point.
	 */
	static open(block, e = null, readTop = null) {

		const read = block.qs(':scope > .block-item');
		const template = block.qs(':scope > template');

		readTop ??= BlockEditor.getReadTop(read);

		read.hidden = true;

		// Après le rendu lu et non en fin d'enveloppe : le « + » du bloc reste sous le bloc ouvert.
		read.renderAdjacentHTML('afterend', template.content.firstElementChild.outerHTML);

		const selected = block.qs(':scope > .block-item-selected');

		BlockEditor.fillBand(selected);
		BlockEditor.selectPlan(block);

		// Un tableau n'a pas d'éditeur de texte, une vidéo à une taille garde le sien masqué : leur premier
		// champ prend la main, ou la case du clic plus bas.
		const editor = BlockEditor.getEditorShown(selected);

		(editor ?? BlockEditor.getFirstField(selected.qs(':scope > .block-form'))).focus();

		if(
			e === null ||
			e.detail === 0
		) {
			return;
		}

		// La colonne des roues décale la grille d'un tableau ouvert, et la croix de chaque pastille décale
		// les étiquettes ouvertes : la case ou l'étiquette se retrouve par sa place dans le rendu lu, et
		// non par le point cliqué.
		const fieldPlaced = BlockTable.getField(e.target, selected) ?? BlockTags.getField(e.target, selected);

		if(fieldPlaced !== null) {

			fieldPlaced.focus();
			fieldPlaced.setSelectionRange(fieldPlaced.value.length, fieldPlaced.value.length);

			return;

		}

		// Toutes les mesures avant d'écrire la sélection : le décalage, puis ce qui est sous le point.
		const shift = selected.qs(':scope > .block-form').getBoundingClientRect().top - readTop;

		// Un clic sur un titre, la valeur d'un chiffre ou l'auteur d'une citation ouvre son champ, qui
		// est à la même place que le texte lu : on y écrit sans second clic.
		const field = document.elementFromPoint(e.clientX, e.clientY + shift)?.closest('input.util-inplace-field') ?? null;

		if(
			field !== null &&
			selected.contains(field)
		) {

			const offset = BlockEditor.getFieldOffset(field, e.clientX, e.clientY + shift);

			field.focus();
			field.setSelectionRange(offset, offset);

			return;

		}

		const range = BlockEditor.getRangeFromPoint(e.clientX, e.clientY + shift);

		if(
			range !== null &&
			editor !== null &&
			editor.contains(range.startContainer)
		) {
			const selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange(range);
		}

	}

	/**
	 * Le haut du rendu d'un bloc lu, que le formulaire du bloc ouvert reprend à l'identique (`open()`).
	 * Le nom au survol (`block-hint`) flotte hors du flux, et la barre d'un parent n'y est pas.
	 */
	static getReadTop(item) {
		return item.qs(':scope > :not(.block-hint, .block-tools)').getBoundingClientRect().top;
	}

	/**
	 * Un bloc parent choisi montre sa barre et le « + » de ses enfants, rendus dans son enveloppe et
	 * montrés par `block.css`.
	 */
	static select(block) {

		block.qs(':scope > .block-item').classList.add('block-item-selected');
		BlockEditor.selectPlan(block);

	}

	static unselect() {
		qsa('.block-editor [data-block-composite]:not([inert]) > .block-item-selected', item => item.classList.remove('block-item-selected', 'block-item-child-open'));
	}

	/**
	 * La ligne du bloc ouvert dans le plan, ramenée sous les yeux par le défilement de la colonne seule :
	 * `scrollIntoView()` ferait aussi défiler la page, et le bloc qu'on vient d'ouvrir avec elle. Un
	 * enfant n'a pas de ligne : c'est celle de son parent. Toutes les mesures, puis l'écriture.
	 */
	static selectPlan(block) {

		qsa('#block-plan .block-plan-selected', row => row.classList.remove('block-plan-selected'));

		let root = block;

		while(root.parentElement.closest('[data-block]') !== null) {
			root = root.parentElement.closest('[data-block]');
		}

		const row = qs('#block-plan-'+ root.dataset.block);

		if(row === null) {
			return;
		}

		row.classList.add('block-plan-selected');

		const aside = row.closest('.block-aside');
		const asideRect = aside.getBoundingClientRect();
		const rowRect = row.getBoundingClientRect();

		if(rowRect.top < asideRect.top) {
			aside.scrollTop -= asideRect.top - rowRect.top;
		} else if(rowRect.bottom > asideRect.bottom) {
			aside.scrollTop += rowRect.bottom - asideRect.bottom;
		}

	}

	/**
	 * Amène un bloc dans la partie visible de la fenêtre, que le clavier d'un téléphone réduit sans
	 * changer `innerHeight` : c'est la hauteur de `visualViewport` qui compte. Le haut du bloc passe
	 * avant son bas, pour qu'un bloc plus haut que la fenêtre montre sa première ligne.
	 */
	static bringIntoView(node) {

		const viewport = window.visualViewport;
		const top = viewport.offsetTop;
		const bottom = viewport.offsetTop + viewport.height;
		const rect = node.getBoundingClientRect();
		const gap = 16;

		if(rect.bottom > bottom - gap) {
			window.scrollBy(0, Math.min(rect.bottom - bottom + gap, rect.top - top - gap));
		} else if(rect.top < top + gap) {
			window.scrollBy(0, rect.top - top - gap);
		}

	}

	/**
	 * Un enfant ouvert n'a pas de bande à lui : sa barre (`block-tools-child`) passe dans celle de son
	 * parent, à côté de celle du parent que `block.css` cache, et le parent perd sa teinte
	 * (`block-item-child-open`). Le nom d'un onglet passe dans la tête de l'onglet, où il se lit, et
	 * reste rattaché au formulaire de l'enfant par son attribut `form`. Un onglet à écrire n'a pas
	 * encore de tête : elle est posée avant le « + » des onglets, et choisie.
	 *
	 * Tout revient à sa place quand l'enfant se referme (`clearBand()`), pour qu'un enregistrement
	 * refusé les retrouve à la réouverture. Un bloc de premier niveau garde sa barre dans sa bande.
	 */
	static band = null;

	static fillBand(selected) {

		BlockEditor.clearBand();

		if(selected.classList.contains('block-child') === false) {
			return;
		}

		const composite = selected.parentElement.closest('[data-block-composite]');
		const parent = composite.qs(':scope > .block-item');
		const tools = selected.qs(':scope > .block-tools-child');

		const band = {
			parent: parent,
			tools: tools,
			toolsHome: selected,
			input: null,
			inputHome: null,
			head: null,
			headText: null,
			headNew: false
		};

		parent.qs(':scope > .block-tools').after(tools);
		parent.classList.add('block-item-selected', 'block-item-child-open');

		const inputHome = selected.qs('[data-block-head]');

		if(inputHome !== null) {

			const isNew = (selected.closest('#block-new') !== null);
			let head;

			if(isNew) {

				head = document.createElement('a');
				head.className = 'tab-item selected';
				head.dataset.tab = 'block-new';
				composite.qs('.block-insert-tab').before(head);

			} else {
				head = qs('#block-tab-'+ selected.closest('[data-block]').dataset.block);
			}

			band.input = inputHome.qs('input');
			band.inputHome = inputHome;
			band.head = head;
			band.headText = head.textContent;
			band.headNew = isNew;

			head.replaceChildren(band.input);
			BlockEditor.sizeField(band.input);

			Lime.Tab.select(head);

		}

		BlockEditor.band = band;

	}

	/**
	 * La tête d'un onglet refermé reprend le nom écrit, ou le sien s'il a été effacé : un nom vide est
	 * refusé à l'enregistrement, et l'erreur se lit alors sous le champ, rendu à la réouverture. Rien ne
	 * revient dans un bloc que la page a perdu, remplacé par une réponse.
	 */
	static clearBand() {

		const band = BlockEditor.band;

		if(band === null) {
			return;
		}

		BlockEditor.band = null;

		if(band.toolsHome.isConnected) {
			band.toolsHome.prepend(band.tools);
		} else {
			band.tools.remove();
		}

		if(band.input !== null) {

			const name = band.input.value.trim();

			if(band.inputHome.isConnected) {
				band.inputHome.append(band.input);
			} else {
				band.input.remove();
			}

			if(band.headNew) {
				band.head.remove();
			} else {
				band.head.textContent = (name === '') ? band.headText : name;
			}

		}

		band.parent.classList.remove('block-item-selected', 'block-item-child-open');

	}

	/**
	 * Le nom d'un onglet, le libellé d'un bouton et une étiquette s'écrivent à la largeur de ce qu'on
	 * tape, là où `field-sizing` n'existe pas (`block.css`) : sept caractères au moins, pour que le
	 * champ reste une cible (`BlockTypeUi::getFieldSize()`).
	 */
	static sizeField(input) {
		input.size = Math.max(input.value.length + 1, 7);
	}

	/**
	 * La variante choisie dans le menu de la barre écrit le champ caché de son formulaire, et le bloc
	 * prend tout de suite sa classe, à la place de celles des autres entrées. Le menu vit dans `body`
	 * une fois ouvert (`Lime.Dropdown`) : l'entrée nomme son formulaire (`data-form`). Une entrée qui
	 * n'en nomme pas est celle d'un parent enregistré (`saveVariant()`).
	 *
	 * Les étapes numérotées n'ont pas de classe : `classList` refuse un nom vide, qui s'écarte donc.
	 */
	static setVariant(item) {

		if(item.dataset.form === undefined) {
			BlockEditor.saveVariant(item);
			return;
		}

		const form = document.getElementById(item.dataset.form);

		if(form === null) {
			return;
		}

		form.qs('input[name="variant"]').value = item.dataset.blockVariant;
		form.dataset.blockDirty = '1';

		const items = Array.from(item.parentElement.qsa('[data-block-variant]'));

		form.qs('[data-block-variant-target]', target => {

			target.classList.remove(...items
				.map(node => node.dataset.blockVariantClass)
				.filter(name => name !== ''));

			if(item.dataset.blockVariantClass !== '') {
				target.classList.add(item.dataset.blockVariantClass);
			}

		});

		items.forEach(node => node.classList.toggle('selected', node === item));

	}

	/**
	 * Un parent enregistré s'enregistre au choix de sa variante et revient rendu en entier
	 * (`doUpdate` avec `close`), toujours choisi pour que sa barre reste ouverte. Un enregistrement en
	 * cours se laisse finir : la réponse rend le parent relu en base.
	 */
	static saveVariant(item) {

		const id = item.getAttribute('post-id');

		(BlockEditor.saving ?? Promise.resolve())
			.then(() => new Ajax.Query(qs('#block-'+ id))
				.url('/block/block:doUpdate')
				.body({
					id: id,
					variant: item.dataset.blockVariant,
					close: 1
				})
				.fetch())
			.then(() => qs('#block-'+ id, block => BlockEditor.select(block)));

	}

	/**
	 * La place du curseur sous un point, dans la valeur d'un champ. `caretPositionFromPoint()` la donne
	 * dans le champ lui-même (`offsetNode` est le champ) ; `caretRangeFromPoint()`, seul connu d'un
	 * navigateur plus ancien, s'arrête au champ sans entrer dans sa valeur. Sans elle, le curseur se
	 * pose en fin de champ, où l'on continue d'écrire.
	 */
	static getFieldOffset(field, x, y) {

		const position = document.caretPositionFromPoint?.(x, y) ?? null;

		if(
			position !== null &&
			position.offsetNode === field
		) {
			return position.offset;
		}

		return field.value.length;

	}

	static getRangeFromPoint(x, y) {

		if(document.caretPositionFromPoint) {

			const position = document.caretPositionFromPoint(x, y);

			if(position === null) {
				return null;
			}

			const range = document.createRange();
			range.setStart(position.offsetNode, position.offset);
			range.collapse(true);

			return range;

		}

		return document.caretRangeFromPoint(x, y);

	}

	/**
	 * Referme le bloc ouvert. Sans changement, il reprend son rendu lu, resté dans la page, sans
	 * requête. Changé, ou enregistré sans être refermé depuis son ouverture (son rendu lu date alors
	 * d'avant), il s'enregistre et revient lu en une seule requête.
	 *
	 * Un bloc à écrire n'a pas de rendu lu : vide, il se retire sans requête.
	 */
	static close() {

		BlockEditor.unselect();

		const form = BlockEditor.getForm();

		if(form === null) {
			return Promise.resolve(true);
		}

		const writable = form.closest('#block-new');

		if(
			writable !== null &&
			BlockEditor.isDraftEmpty(form)
		) {
			BlockEditor.discard(writable);
			return Promise.resolve(true);
		}

		if(
			BlockEditor.isDirty(form) ||
			form.dataset.blockSaved === '1'
		) {
			return BlockEditor.save(true);
		}

		const block = form.closest('[data-block]');

		if(block !== null) {
			BlockEditor.restore(block);
		}

		return Promise.resolve(true);

	}

	/**
	 * Quitte le bloc ouvert avant d'en ouvrir un autre, dans le geste : un seul bloc est ouvert à la
	 * fois. `close()` rend une promesse, mais le geste ne peut pas l'attendre sans sortir du clic, et
	 * un téléphone n'ouvre alors plus son clavier. Le DOM répond dans le même tour : `restore()` et
	 * `discard()` retirent le formulaire, `save()` le passe `inert` avant sa requête. Reste ouvert
	 * un bloc que `Element.form()` refuse d'envoyer (une image se télécharge encore), ou qu'un
	 * enregistrement en cours fera refermer à son retour : le geste est alors sans effet.
	 */
	static release() {

		BlockEditor.close();

		return BlockEditor.getForm() === null;

	}

	/**
	 * Retire un bloc à écrire sans rien enregistrer. S'il était seul, l'objet n'a plus aucun bloc, et
	 * le choix du premier bloc revient (`revealStart()`).
	 */
	static discard(writable) {

		BlockEditor.clearBand();

		// Un onglet à écrire retiré laisse les onglets sans panneau choisi : le premier reprend la main.
		const tabs = writable.classList.contains('tab-panel') ? writable.closest('.tabs') : null;

		writable.qs('.editor', editor => Editor.endInstance('#'+ editor.id));
		writable.remove();

		if(tabs !== null) {
			Lime.Tab.select(tabs.qs('.tab-item[data-tab]'));
		}

		BlockEditor.revealStart();

	}

	/**
	 * Une liste sans bloc, ni lu ni à écrire, est celle d'un objet vide : elle se vide des « + » que le
	 * premier bloc à écrire ou une coupe au « / » y ont laissés, et le choix du premier bloc revient, comme un
	 * rechargement le rendrait (`BlockUi::getEditor()`). La copie d'un bloc en cours de retrait
	 * (`[inert]`, `getForm()`) n'est pas un bloc.
	 */
	static revealStart() {

		const list = qs('#block-list');

		if(list.qs(':scope > :is([data-block]:not([inert]), #block-new, #block-split)') !== null) {
			return;
		}

		list.replaceChildren();
		qs('#block-start').removeHide();

	}

	/**
	 * « Annuler », dans la barre d'un bloc à écrire de premier niveau. Vide, le bloc part sans question ;
	 * écrit, il demande d'abord, par la phrase que porte le bouton (`BlockUi::getTools()`). Pendant son
	 * enregistrement (`inert`), il n'y a plus rien à annuler : la réponse le remplace par le bloc né.
	 */
	static cancel(button) {

		const writable = button.closest('#block-new');
		const form = writable.qs('.block-form');

		if(form.inert) {
			return;
		}

		if(
			BlockEditor.isDraftEmpty(form) === false &&
			confirm(button.dataset.blockDiscard) === false
		) {
			return;
		}

		BlockEditor.discard(writable);

	}

	/**
	 * « Enregistrer », dans la barre du bloc ouvert, fait ce que fait un clic hors du bloc (`close()`) :
	 * le bloc s'enregistre et revient lu, ce qui est le seul signe à l'écran qu'il est enregistré. Un bloc
	 * à écrire encore vide n'a rien à faire naître, et `close()` le retirerait : le curseur revient à son
	 * premier champ, comme sous le « + » d'un parent à écrire (`insertInNew()`).
	 *
	 * Le bloc refermé laisse sous le pointeur son rendu lu, qu'un clic rouvre : le second clic d'un
	 * double clic s'arrête comme après une tuile (`keepStart()`).
	 */
	static submit() {

		const form = BlockEditor.getForm();

		if(form === null) {
			return;
		}

		if(BlockEditor.isDraftEmpty(form)) {
			BlockEditor.getFirstField(form).focus();
			return;
		}

		BlockEditor.close();

		BlockEditor.started = true;

	}

	static restore(block) {

		BlockEditor.clearBand();

		block.qs(':scope > .block-item-selected .editor', editor => Editor.endInstance('#'+ editor.id));

		block.qs(':scope > .block-item-selected').remove();
		block.qs(':scope > .block-item').hidden = false;

	}

	/**
	 * Enregistre le bloc ouvert s'il a changé, et rend une promesse tenue par `true` s'il n'y avait
	 * rien à écrire ou si l'écriture est acceptée, par `false` si elle est refusée : le geste qui
	 * attend s'arrête alors. Un appel qui arrive pendant un enregistrement attend sa fin, puis regarde
	 * de nouveau : le bloc ouvert entre-temps a pu changer lui aussi.
	 *
	 * `close` demande au serveur de rendre le bloc lu (`doUpdate`, vue). Le formulaire quitté devient
	 * `inert` le temps de la requête : on n'y écrit plus rien qui serait perdu au remplacement.
	 * Sans `close`, le bloc reste ouvert : il est marqué propre dès l'envoi, pour qu'une frappe
	 * pendant la requête le marque de nouveau changé.
	 *
	 * Le bloc à écrire du « + » se crée toujours refermé : la réponse le remplace par le bloc né, dont
	 * le formulaire est celui d'un bloc qui existe. Il naît après le bloc qui le précède à cet instant,
	 * lu ici et non au clic : un enregistrement en cours est fini, et le plan a pu bouger depuis. Sous
	 * le « + » de tête, aucun bloc ne le précède : il naît avant celui qui le suit (`before`). Posé par
	 * le « / » dans un objet que la coupe a vidé, il n'a aucun voisin et naît seul.
	 *
	 * Le bloc choisi au « / » attend ainsi la réponse de la coupe, qui est l'enregistrement en cours
	 * (`BlockSlash.choose()`) : ses voisins n'ont leurs ids qu'après elle.
	 */
	static save(close = false) {

		if(BlockEditor.saving !== null) {
			return BlockEditor.saving.then(() => BlockEditor.save(close));
		}

		const form = BlockEditor.getForm();

		if(
			form === null ||
			(
				BlockEditor.isDirty(form) === false &&
				(close === false || form.dataset.blockSaved !== '1')
			)
		) {
			return Promise.resolve(true);
		}

		// Effacé, le bloc à écrire n'a rien à enregistrer, mais son brouillon a pu être rangé : il est
		// oublié, et n'est plus tenu. Le point de départ que retient `EditorDraft` porte les marques de
		// sélection de l'éditeur (`data-location`) : un champ vide n'y serait jamais égal, et la sauvegarde
		// périodique rangerait de nouveau la ligne vide, rendue ensuite en « modifications récupérées ».
		const writable = form.closest('#block-new');

		if(BlockEditor.isDraftEmpty(form)) {

			if(writable !== null) {
				BlockEditor.discard(writable);
				return Promise.resolve(true);
			}

			form.qs('.editor', editor => {
				EditorDraft.forget(editor.dataset.draft);
				EditorDraft.end('#'+ editor.id);
			});
			form.qs('.editor-draft-bar', bar => bar.remove());

			return Promise.resolve(true);

		}

		// Un tableau n'a pas d'éditeur de texte, seulement ses cases (`BlockTypeUi::getTableEditable()`).
		const editor = form.qs('.editor');

		// `Element.form()` refuse d'envoyer un éditeur dont une image se télécharge encore, et lève
		// alors le message à montrer, en texte. Le bloc reste ouvert, avec ce qui a été écrit.
		let body;

		try {
			body = form.form();
		} catch(e) {

			if(typeof e !== 'string') {
				throw e;
			}

			Lime.Alert.showStaticError(e);
			return Promise.resolve(false);

		}

		if(writable !== null) {

			const previous = BlockEditor.getBlockId(writable.previousElementSibling);
			const next = BlockEditor.getBlockId(writable.nextElementSibling);

			if(previous !== null) {
				body.set('after', previous);
			} else if(next !== null) {
				body.set('before', next);
			}

		}

		// Un enfant à écrire naît dans son parent, que la réponse rend en entier (`block.v.php`).
		const parentId = (writable !== null) ? (writable.closest('[data-block-composite]')?.dataset.block ?? null) : null;

		if(
			close ||
			writable !== null
		) {
			body.set('close', '1');
			form.inert = true;
			BlockEditor.clearBand();
		}

		form.dataset.blockDirty = '0';

		BlockEditor.saving = BlockEditor.query(form)
			.url(form.dataset.ajaxForm)
			.body(body)
			.fetch()
			.then(() => BlockEditor.isAccepted(form), () => false)
			.then(saved => {

				BlockEditor.saving = null;

				if(saved) {

					EditorField.flushDraft(form);
					// Le bloc enregistré a pu quitter la page, remplacé par sa réponse : la barre se
					// retire dans le formulaire, que `EditorDraft.hideBar()` ne trouverait plus par son id.
					form.qs('.editor-draft-bar', bar => bar.remove());

					if(form.isConnected) {
						form.dataset.blockSaved = '1';
					} else if(editor !== null) {
						Editor.endInstance('#'+ editor.id);
					}

					// Un onglet né rend ses onglets avec le premier choisi : on reste sur le sien, le dernier.
					if(parentId !== null) {
						qs('#block-'+ parentId +' .tabs-item', head => Lime.Tab.select(Array.from(head.qsa('.tab-item[data-tab]')).pop()));
					}

				} else {

					form.dataset.blockDirty = '1';

					if(
						close ||
						writable !== null
					) {
						BlockEditor.reopen(form);
					}

				}

				return saved;

			});

		return BlockEditor.saving;

	}

	/**
	 * La requête qui enregistre un formulaire de bloc, et la lecture de sa réponse. Un refus ne laisse
	 * qu'un signe à l'appelant : `Lime.Alert.showErrors()` marque le formulaire lui-même
	 * (`form-error`). `hideErrors()`, appelé sur une réponse lisible, ne retire la marque que des descendants du
	 * formulaire (`qsa()`), jamais du formulaire : la marque d'un premier refus survivrait à l'envoi
	 * suivant, qui serait lu comme refusé même accepté, et `reopen()` chercherait alors le parent d'un
	 * formulaire que la réponse a déjà remplacé. Elle se retire donc ici, avant chaque envoi.
	 */
	static query(form) {

		form.classList.remove('form-error');

		return new Ajax.Query(form);

	}

	static isAccepted(form) {
		return (form.classList.contains('form-error') === false);
	}

	/**
	 * L'id du bloc que porte un voisin du bloc à écrire : une enveloppe de bloc, ou la place d'une étape
	 * dans son parent (`BlockTypeUi::getUnit()`), qui en contient une. Le « + » de tête et l'aperçu du
	 * « / » n'en portent pas.
	 */
	static getBlockId(node) {

		if(node === null) {
			return null;
		}

		if(node.dataset.block !== undefined) {
			return node.dataset.block;
		}

		return node.qs(':scope > [data-block]')?.dataset.block ?? null;

	}

	/**
	 * Un bloc refusé en le quittant redevient le bloc ouvert, avec son erreur. Celui qu'on a ouvert
	 * pendant la requête se referme s'il n'a pas changé ; changé, il reste ouvert lui aussi, et c'est
	 * le prochain geste qui l'enregistrera.
	 */
	static reopen(form) {

		const formOther = BlockEditor.getForm();

		if(
			formOther !== null &&
			BlockEditor.isDirty(formOther) === false &&
			formOther.dataset.blockSaved !== '1'
		) {

			const block = formOther.closest('[data-block]');

			if(block !== null) {
				BlockEditor.restore(block);
			}

		}

		form.inert = false;
		BlockEditor.fillBand(form.closest('.block-item-selected'));
		(BlockEditor.getEditorShown(form) ?? BlockEditor.getFirstField(form)).focus();

	}

	/**
	 * Depuis le plan : ouvrir le bloc et l'amener sous les yeux, puisque c'est pour le retrouver
	 * dans une leçon longue qu'on passe par le plan.
	 */
	static reach(row) {

		const block = qs('#block-'+ row.dataset.blockPlan);

		if(block === null) {
			return;
		}

		// Le bloc visé déjà ouvert ne se quitte pas : on l'amène seulement sous les yeux. Sinon le geste
		// reste sans effet, défilement compris, tant que le bloc ouvert ne peut pas se fermer.
		const opened = (block.qs(':scope > .block-item-selected') !== null);

		if(
			opened === false &&
			BlockEditor.release() === false
		) {
			return;
		}

		block.scrollIntoView({block: 'center', behavior: 'smooth'});

		if(opened) {
			return;
		}

		// Un parent n'a pas de formulaire à ouvrir : il est choisi, comme au clic sur le bloc.
		if(block.dataset.blockComposite !== undefined) {
			BlockEditor.select(block);
		} else {
			BlockEditor.open(block);
		}

	}

	/**
	 * Ce que l'auteur écrit dans un bloc hors de son éditeur : tout champ où l'on tape, quel que soit
	 * son habillage. Le type du champ le désigne, pas une classe de forme : un champ qui garde son
	 * cadre (l'adresse d'une vidéo) est écrit au même titre qu'un champ fondu dans la lecture. Les
	 * champs cachés n'en sont pas, ils portent ce que le serveur ou la barre du bloc a posé.
	 */
	static textFields = 'input[type="text"], input[type="url"]';

	/**
	 * Vide, c'est-à-dire sans texte ni média dans son éditeur, et sans rien d'écrit dans ses champs de
	 * texte (`textFields`). Ils se lisent par `form.elements` et non dans le formulaire : le nom d'un
	 * onglet lui est rattaché depuis la tête de ses onglets. Effacé au clavier, l'éditeur garde une
	 * ligne vide ; par `execCommand` ou un collage, il peut ne garder qu'un `<br>` nu, que le serveur
	 * répare en ligne vide.
	 */
	static isDraftEmpty(form) {

		if(form.qs('input[name="draft"]') === null) {
			return false;
		}

		const editor = form.qs('.editor');

		return (
			(
				editor === null ||
				(
					editor.textContent.trim() === '' &&
					editor.qs('figure, img, iframe, hr') === null
				)
			) &&
			Array.from(form.elements).every(field => field.matches(BlockEditor.textFields) === false || field.value.trim() === '')
		);

	}

	/**
	 * Le premier éditeur de texte d'un formulaire de bloc qui s'affiche, ou `null`. Une vidéo en pleine
	 * largeur ou en petite taille garde le sien masqué (`block.css`), où un `focus()` resterait sans
	 * effet : c'est alors son adresse qui prend la main (`getFirstField()`).
	 */
	static getEditorShown(node) {

		return Array.from(node.qsa('.block-form .editor')).find(editor => editor.getClientRects().length > 0) ?? null;

	}

	/**
	 * Le premier champ d'un bloc à écrire, dans l'ordre où l'auteur le lit : le titre d'un encadré,
	 * la valeur d'un chiffre ou le titre d'une étape viennent avant le texte, l'auteur d'une citation
	 * après lui. Le nom d'un onglet, dans la tête de ses onglets (`fillBand()`), vient avant tout.
	 */
	static getFirstField(form) {

		const head = Array.from(form.elements).find(field => field.matches(BlockEditor.textFields) && form.contains(field) === false);

		return head ?? form.qs(BlockEditor.textFields +', .editor');

	}

	/**
	 * Le « + » ouvre le bloc à écrire dans le geste, sans requête : le clavier s'ouvre sur un
	 * téléphone, et ce qu'on tape aussitôt tombe dans le bloc. Posé juste après ce qui porte le « + »
	 * (l'enveloppe d'un bloc, la place d'une étape dans sa liste, ou le « + » de tête lui-même), en frère
	 * et non dedans, parce que la réponse qui referme ce bloc remplace son enveloppe en entier. L'entrée
	 * (`key`) est celle du « + », ou celle choisie dans son menu (`fillMenu()`).
	 *
	 * Un bloc à écrire de premier niveau est entre deux « + » : celui de ce qui le précède, et le sien,
	 * sous lui (`place()`). Le premier ajoute au-dessus de lui, le second au-dessous.
	 *
	 * Un seul bloc à écrire à la fois, l'id de son éditeur étant fixe (`BlockTypeUi::getEditable()`) :
	 * - ouvert à côté de ce « + », de l'entrée demandée et vide, il garde sa place et reprend le curseur
	 *   s'il en était sorti : c'est aussi le second clic d'un double clic. L'enfant d'un parent le garde même
	 *   écrit, son « + » ne proposant qu'un type ;
	 * - ouvert à côté de ce « + » et vide, il laisse sa place au bloc de l'entrée choisie ;
	 * - écrit, le quitter lance sa création, que ce « + » attend pour poser le sien : à côté du bloc né,
	 *   du côté où le « + » se trouvait, ou sous ce « + » s'il était ailleurs.
	 *
	 * Refusée, la création rouvre le bloc avec son erreur (`reopen()`) et le « + » est abandonné : le
	 * rejouer relancerait la même création, refusée de nouveau, sans fin.
	 */
	static insert(button, key) {

		const below = (button.closest('#block-new > .block-insert') !== null);

		if(
			below === false &&
			button.closest('#block-new') !== null
		) {
			BlockEditor.insertInNew(button, key);
			return;
		}

		const writable = qs('#block-new');
		const anchor = below ? writable.previousElementSibling : BlockEditor.getAnchor(button);
		const beside = (writable !== null && anchor.nextElementSibling === writable);
		const child = button.dataset.blockChild ?? null;

		if(
			beside &&
			writable.qs('.block-form').inert === false &&
			writable.qs('.block-form').dataset.blockKey === key &&
			(child !== null || BlockEditor.isDraftEmpty(writable.qs('.block-form')))
		) {

			if(writable.contains(document.activeElement) === false) {
				BlockEditor.getFirstField(writable.qs('.block-form')).focus();
			}

			return;

		}

		if(BlockEditor.release() === false) {
			return;
		}

		// Le bloc quitté était le seul de l'objet, et vide : la liste s'est vidée avec lui, « + » de tête
		// compris, et le choix du premier bloc est revenu (`revealStart()`). L'entrée choisie est ce
		// premier bloc.
		if(anchor.isConnected === false) {
			BlockEditor.placeFirst(key);
			return;
		}

		if(qs('#block-new') !== null) {

			// Les « + » d'un parent quittent la page avec lui quand l'enfant à écrire y naît : la réponse
			// rend le parent en entier. Ce sont ceux du parent rendu qui reprennent le geste, celui de ses
			// enfants comme celui qui ajoute un bloc après lui.
			const composite = (child !== null) ? button.closest('[data-block-composite]').dataset.block : null;

			BlockEditor.saving.then(saved => {

				let again;

				if(composite !== null) {
					again = button.isConnected ? button : qs('#block-'+ composite +' [data-block-insert][data-block-child]');
				} else if(beside) {

					// La réponse a remplacé le bloc à écrire par le bloc né, à la même place. Né seul dans son
					// objet, il a rendu la liste entière, où il se range en dernier (`insertInNew()`) quoi que
					// l'objet ait reçu entre-temps.
					const born = anchor.isConnected ?
						anchor.nextElementSibling :
						(Array.from(qsa('#block-list > [data-block]:not([inert])')).pop() ?? null);

					if(born === null) {
						again = null;
					} else if(below) {
						again = born.qs(':scope > .block-insert > a');
					} else {
						again = born.previousElementSibling.qs(':scope > .block-insert > a, :scope > a');
					}

				} else if(button.isConnected || anchor.dataset.block === undefined) {
					again = button;
				} else {
					again = qs('#block-'+ anchor.dataset.block +' > .block-insert > a');
				}

				// Un bloc supprimé pendant la création emporte son « + », et un parent plein n'en rend
				// plus : le geste ne se rejoue que sur un « + » que la page porte.
				if(
					saved &&
					again !== null &&
					again.isConnected
				) {
					BlockEditor.insert(again, key);
				}

			});

			return;

		}

		BlockEditor.place(anchor, 'afterend', key, child);

	}

	/**
	 * Le « + » d'un parent à écrire (étapes, accordéon, onglets ou chiffres qui naissent,
	 * `BlockTypeUi::getEditable()`) : un enfant n'existe que dans un parent enregistré, et un seul bloc
	 * à écrire est ouvert à la fois. Le parent naît donc d'abord, avec son premier enfant, et c'est le
	 * « + » du parent rendu qui reprend le geste. Sans rien d'écrit, il n'y a rien à faire naître : le curseur revient au premier champ.
	 *
	 * La réponse remplace le bloc à écrire à sa place (`block.v.php`) : le parent né se retrouve par
	 * son voisin, qui reste dans la page. Sans voisin, c'est le premier bloc d'un objet vide, dont la
	 * réponse rend la liste entière, et le bloc né sans voisin se range en dernier (`BlockLib::create()`).
	 */
	static insertInNew(button, key) {

		const writable = button.closest('#block-new');
		const form = writable.qs('.block-form');

		if(BlockEditor.isDraftEmpty(form)) {
			BlockEditor.getFirstField(form).focus();
			return;
		}

		const previous = writable.previousElementSibling;
		const next = writable.nextElementSibling;

		BlockEditor.release();

		if(BlockEditor.saving === null) {
			return;
		}

		BlockEditor.saving.then(saved => {

			if(saved === false) {
				return;
			}

			let block;

			if(previous !== null && previous.isConnected) {
				block = previous.nextElementSibling;
			} else if(next !== null && next.isConnected) {
				block = next.previousElementSibling;
			} else {
				block = Array.from(qsa('#block-list > [data-block]:not([inert])')).pop() ?? null;
			}

			const again = block?.qs(':scope > .block-item [data-block-insert][data-block-child]') ?? null;

			if(again !== null) {
				BlockEditor.insert(again, key);
			}

		});

	}

	/**
	 * Ce qui porte un « + » : le « + » de tête, ou l'enveloppe d'un bloc. Le « + » d'un parent
	 * (`data-block-child`) ajoute après son dernier enfant, ou après sa place dans la forme du parent
	 * (une étape dans sa liste, un panneau d'onglet), où un bloc à écrire ne peut se poser qu'à côté
	 * d'elle.
	 */
	static getAnchor(button) {

		if(button.dataset.blockChild !== undefined) {

			const last = Array.from(button.closest('[data-block-composite]').qsa(':scope > .block-item [data-block]')).pop();

			if(
				last.parentElement.tagName === 'LI' ||
				last.parentElement.classList.contains('tab-panel')
			) {
				return last.parentElement;
			}

			return last;

		}

		return button.closest('[data-block]') ?? button.closest('.block-insert');

	}

	/**
	 * Une tuile du choix du premier bloc : le choix se masque et le bloc à écrire du type se pose dans
	 * la liste, vide, dans le geste comme pour un « + » (`placeFirst()`). Il n'a aucun voisin et naît
	 * seul.
	 */
	static start(key) {

		BlockEditor.placeFirst(key);

		BlockEditor.started = true;

	}

	/**
	 * Le premier bloc d'un objet se pose sous le « + » de tête, que la liste d'un objet vide ne porte
	 * pas et que la page tient prêt (`BlockUi::getEditor()`). Ce « + » quitte la liste avec le bloc quand
	 * il est retiré (`revealStart()`).
	 */
	static placeFirst(key) {

		qs('#block-start').hide();

		const list = qs('#block-list');

		list.innerHTML = BlockEditor.getEditor().qs('template[data-block-beside]').innerHTML;

		BlockEditor.place(list.firstElementChild, 'afterend', key);

	}

	/**
	 * Le geste en cours a commencé sur une tuile ou sur « Enregistrer », qui disparaissent au premier
	 * clic : posé par `start()` et `submit()`, levé au premier clic du geste
	 * suivant (écouteur en bas du fichier), qui passe sur `window` avant le délégué des tuiles.
	 */
	static started = false;

	/**
	 * Les clics suivants d'un double clic sur une tuile : la tuile s'est masquée au premier, ils tombent
	 * donc sur ce qui a pris sa place sous le pointeur, le bloc qu'elle vient de poser, « Annuler » dans
	 * sa barre, le plan, ou un lien du pied de page que la page raccourcie a remonté. Personne n'a visé ce
	 * qui s'y trouve : comme sur un « + » cliqué deux fois (`insert()`), le bloc posé reste et garde le
	 * curseur, et le clic s'arrête là. Après « Enregistrer », il n'y a plus de bloc à écrire, et le clic
	 * s'arrête de même.
	 */
	static keepStart(e) {

		if(e.detail < 2) {
			BlockEditor.started = false;
		}

		if(BlockEditor.started === false) {
			return false;
		}

		e.preventDefault();
		e.stopImmediatePropagation();

		const writable = qs('#block-new');

		if(
			writable !== null &&
			writable.contains(document.activeElement) === false
		) {
			BlockEditor.getFirstField(writable.qs('.block-form')).focus();
		}

		return true;

	}

	/**
	 * Le `<template>` du bloc à écrire d'une entrée, par sa clé (`BlockLib::getChoices()`) : le type seul,
	 * ou le type et sa variante pour une forme fixée. Celui d'un enfant est rangé sous le type de son
	 * parent, dont il prend la forme (`BlockUi::getNew()`).
	 */
	static getNewTemplate(key, parentType = null) {

		const selector = (parentType === null) ?
			'template[data-block-new="'+ key +'"]:not([data-block-parent])' :
			'template[data-block-new="'+ key +'"][data-block-parent="'+ parentType +'"]';

		return BlockEditor.getEditor().qs(selector);

	}

	/**
	 * Pose le bloc à écrire d'une entrée à côté de `anchor`, ouvert, curseur dedans, et l'amène sous les
	 * yeux. Un enfant à écrire prend la forme que lui donne son parent (`parentType`,
	 * `BlockUi::getNew()`).
	 *
	 * Un bloc de premier niveau reçoit son « + » dans son enveloppe, comme un bloc enregistré : ce qui le
	 * retire ou le remplace emporte le « + » avec lui, et la liste ne garde que les siens.
	 */
	static place(anchor, position, key, parentType = null) {

		const template = BlockEditor.getNewTemplate(key, parentType);

		anchor.renderAdjacentHTML(position, template.content.firstElementChild.outerHTML);

		const writable = qs('#block-new');

		if(parentType === null) {
			writable.insertAdjacentHTML('beforeend', BlockEditor.getEditor().qs('template[data-block-beside]').innerHTML);
		}

		BlockEditor.selectPlan(writable);
		BlockEditor.fillBand(writable.qs(':scope > .block-item-selected'));

		BlockEditor.getFirstField(writable.qs('.block-form')).focus();

		BlockEditor.bringIntoView(writable);

		return writable;

	}

	/**
	 * Le menu d'un « + » est rempli à l'ouverture depuis la liste de la page (`BlockUi::getMenu()`), et
	 * le « + » retenu : le choix se fait dans la liste, que le menu déroulant a posée dans `body`.
	 */
	static menuButton = null;

	static fillMenu(button, list) {

		list.innerHTML = BlockEditor.getEditor().qs('template[data-block-menu]').innerHTML;
		BlockEditor.menuButton = button;

	}

	/**
	 * Monter, descendre et dupliquer lisent l'id sur le bouton (`post-id`) : celui d'un enfant est dans la
	 * bande de son parent (`fillBand()`), hors de l'enveloppe du bloc qu'il touche.
	 */
	static move(button) {

		BlockEditor.prepareGesture(button).then(({saved, context}) => {

			if(saved === false) {
				return;
			}

			return new Ajax.Query(context)
				.url('/block/block:doIncrementPosition')
				.body({
					id: button.getAttribute('post-id'),
					increment: button.dataset.blockMove
				})
				.fetch();

		});

	}

	static duplicate(button) {

		BlockEditor.prepareGesture(button).then(({saved, context}) => {

			if(saved === false) {
				return;
			}

			return new Ajax.Query(context)
				.url('/block/block:doDuplicate')
				.body({
					id: button.getAttribute('post-id')
				})
				.fetch();

		});

	}

	/**
	 * Un bloc de premier niveau reste ouvert pendant que le serveur déplace son voisin ou pose sa copie
	 * sous lui. Un enfant, non : la réponse rend son parent en entier, formulaire compris, donc il se
	 * referme d'abord, et la requête prend le parent pour contexte, le bouton quittant la page avec
	 * l'enfant refermé.
	 */
	static prepareGesture(button) {

		if(button.closest('.block-tools-child') === null) {
			return BlockEditor.save().then(saved => ({saved: saved, context: button}));
		}

		const context = button.closest('[data-block-composite]');

		return BlockEditor.close().then(saved => ({saved: saved, context: context}));

	}

	/**
	 * L'ordre entier part au serveur (`doUpdatePositions`), qui refuse une liste périmée. Une fois
	 * accepté, les blocs prennent dans la page l'ordre que le plan montre déjà. Le texte du bloc ouvert
	 * n'a pas à être enregistré pour ranger, mais un enregistrement en cours se laisse finir : le
	 * serveur relit la liste.
	 *
	 * Le bloc à écrire du « + », lui, est quitté : il naît, ou se retire s'il est vide, avant que les ids
	 * se lisent dans le plan. Sa ligne y est alors, et la liste postée est complète. S'il est refusé,
	 * rien ne part, et le plan reprend l'ordre de la page, que le glisser n'a pas touché.
	 */
	static savePositions(fold) {

		(BlockEditor.saving ?? Promise.resolve())
			.then(() => qs('#block-new') === null ? true : BlockEditor.close())
			.then(saved => {

				if(saved === false) {

					qs('#block-list').qsa('[data-block]:not([inert], [data-block][inert] *)', block => fold.append(fold.qs('[data-sort-id="'+ block.dataset.block +'"]')));
					return;

				}

				const ids = Array.from(fold.children)
					.filter(row => row.dataset.sortId !== undefined)
					.map(row => parseInt(row.dataset.sortId));

				return new Ajax.Query(fold)
					.url(fold.dataset.sortUrl)
					.body({
						owner: fold.getAttribute('post-owner'),
						reference: fold.getAttribute('post-reference'),
						positions: JSON.stringify(ids)
					})
					.fetch()
					.then(() => {

						const list = qs('#block-list');

						ids.forEach(id => list.append(qs('#block-'+ id)));

					});

			});

	}

	/**
	 * Un lien qui quitte l'écran : le bloc ouvert s'enregistre, puis le lien est cliqué de nouveau.
	 * Lancé par script, ce clic n'est pas retenu (écouteur en bas du fichier) et passe au cadriciel,
	 * qui suit le lien avec tout ce qu'il porte (`data-ajax-download`, attente).
	 */
	static leave(link) {

		BlockEditor.save().then(saved => {

			if(saved) {
				link.click();
			}

		});

	}

	/**
	 * Le lien que le cadriciel suivrait lui-même en navigation ajax : mêmes exclusions que son délégué
	 * des liens (`framework/util/static/js/ajax.js`). Les autres quittent l'écran sans lui (`never`,
	 * nouvel onglet) ou n'en sortent pas (`#`), et sont traités comme un clic ailleurs.
	 */
	static isNavigation(link, e) {

		for(let node = e.target; node !== null; node = node.parentElement) {

			switch(node.dataset.ajaxNavigation) {

				case 'never' :
					return false;

				case 'touch' :
					if(isTouch() === false) {
						return false;
					}
					break;

				case 'notouch' :
					if(isTouch()) {
						return false;
					}

			}

			if(node === link) {
				break;
			}

		}

		const href = link.getAttribute('href');

		return (
			link.getAttribute('target') !== '_blank' &&
			href.startsWith('#') === false &&
			e.ctrlKey === false &&
			e.metaKey === false &&
			Ajax.Navigation.isUrlValid(href)
		);

	}

}

/**
 * Un clic hors du bloc ouvert le referme, et un lien qui quitte l'écran attend qu'il soit enregistré.
 *
 * Écouté en capture sur `window`, une seule fois pour le document (`block.js` ne s'exécute qu'une
 * fois, `Ajax.Asset`) : le délégué des liens du cadriciel écoute le même clic en capture sur le
 * document, où il est inscrit avant ce fichier, et partirait avant qu'on ait pu le retenir ; la capture
 * passe par `window` avant le document. L'écouteur survit à l'écran : il ne fait rien sans éditeur.
 *
 * Ne sont pas « ailleurs » : les blocs, les blocs à écrire, l'aperçu du « / » et les « + », qui ont
 * leur geste (`init()`), le plan, la barre de l'éditeur de texte, posée dans `body`
 * (`EditorFormat.start()`), un panneau et un menu déroulant (la couleur d'un encadré), qui
 * s'ouvrent par-dessus l'écran sans le quitter. Ni un clic lancé par script (`isTrusted`) : le lien
 * rejoué par `leave()`, une fois le bloc enregistré, ou celui que le cadriciel clique pour livrer un
 * téléchargement (`ajax.js`), qui refermerait le bloc sans que personne ait cliqué ailleurs. Ni les
 * clics suivants d'un double clic sur une tuile ou sur « Enregistrer », arrêtés où qu'ils tombent
 * (`keepStart()`).
 */
window.addEventListener('click', e => {

	if(
		e.isTrusted === false ||
		BlockEditor.getEditor() === null ||
		(e.target instanceof Element) === false ||
		BlockEditor.keepStart(e) ||
		e.target.closest('[data-block], #block-new, #block-split, .block-insert, #block-plan, .editor-box-selection, .editor-box-line, .editor-box-media, .panel, .dropdown-list') !== null
	) {
		return;
	}

	const link = e.target.closest('a[href]');

	if(
		link !== null &&
		BlockEditor.isNavigation(link, e)
	) {

		if(BlockEditor.isModified()) {
			e.preventDefault();
			e.stopImmediatePropagation();
			BlockEditor.leave(link);
		}

		return;

	}

	BlockEditor.close();

}, true);

/**
 * Le menu d'un « + » : `Lime.Dropdown` émet ses événements sur le bouton, sans remontée, donc écoutés
 * en capture, avant de mesurer la liste pour la placer. Il arrête le clic du bouton
 * (`stopImmediatePropagation()`) : aucun délégué `click` ne verrait l'ouverture.
 */
window.addEventListener('dropdownBeforeShow', e => {

	if(e.target.dataset?.blockInsertMenu !== undefined) {
		BlockEditor.fillMenu(e.target, e.detail.list);
	}

}, true);

/**
 * La variante d'un bloc, choisie dans le menu de sa barre, que `Lime.Dropdown` a posé dans `body` :
 * la couleur d'un encadré, la taille d'une vidéo, le repère des étapes, enregistré tout de suite
 * pour un parent qui l'est déjà.
 */
document.addEventListener('click', e => {

	const item = (e.target instanceof Element) ? e.target.closest('.dropdown-item[data-block-variant]') : null;

	if(item !== null) {
		e.preventDefault();
		BlockEditor.setVariant(item);
	}

});

/**
 * Le choix d'un type dans le menu d'un « + » pose son bloc à écrire dans le geste, pour que le clavier
 * d'un téléphone s'ouvre. Le menu se referme de lui-même au clic.
 */
document.addEventListener('click', e => {

	const choice = (e.target instanceof Element) ? e.target.closest('.block-insert-list [data-block-choice]') : null;

	if(
		choice === null ||
		BlockEditor.menuButton === null ||
		BlockEditor.menuButton.isConnected === false
	) {
		return;
	}

	e.preventDefault();
	BlockEditor.insert(BlockEditor.menuButton, choice.dataset.type);

});

/**
 * Un rechargement, un onglet fermé ou un lien `never` ne laissent pas le temps d'une requête : le
 * navigateur demande confirmation, seul avertissement qu'une page peut donner à ce moment-là, et le
 * brouillon de l'éditeur est rangé tout de suite, sans attendre sa sauvegarde périodique, pour qu'un
 * départ confirmé le retrouve à la réouverture du bloc.
 */
window.addEventListener('beforeunload', e => {

	if(
		BlockEditor.getEditor() === null ||
		BlockEditor.isModified() === false
	) {
		return;
	}

	qsa('.block-editor .block-form .editor[data-draft]:not([data-block][inert] *)', editor => EditorDraft.save('#'+ editor.id));

	e.preventDefault();

});

/**
 * Retour arrière et navigations lancées par script (`data-get`, `popstate`) : aucun clic ne les
 * précède. Le corps du formulaire est lu tout de suite, avant que la navigation remplace l'écran, et la
 * requête part sans être attendue ; le brouillon reste le filet si elle n'aboutit pas.
 *
 * Rien ne retient cette navigation : `navigationStart` n'est pas annulable. Si une image se
 * télécharge encore dans le bloc ouvert, `Element.form()` refuse de l'envoyer et `EditorDraft` se tait
 * aussi : ce qui a été tapé depuis le dernier brouillon périodique, cinq secondes au plus, est perdu.
 */
document.addEventListener('navigationStart', () => {

	if(BlockEditor.getEditor() !== null) {
		BlockEditor.save();
	}

});

/**
 * Le « / » : `editor.js` signale un « / » tapé en début de ligne ou après une espace (`editorSlash`) et
 * sait couper le texte à cet endroit (`EditorRange.split()`), sans rien savoir des blocs. Le reste est
 * ici : la liste des types de l'usage (`BlockUi::getMenu()`), posée sous le « / » ou au-dessus quand
 * la place manque dans la partie visible de la fenêtre (le clavier d'un téléphone la réduit sans
 * changer `innerHeight`), puis la coupe.
 *
 * Le focus ne quitte jamais l'éditeur pendant que la liste est ouverte : un appui sur un type est
 * retenu au `pointerdown`, sinon le texte perdrait son curseur et le téléphone fermerait son clavier.
 * On continue d'écrire, et toute frappe referme la liste en laissant le « / » comme un caractère.
 *
 * Seul un texte de premier niveau se coupe (`data-block-split`, posé par `BlockUi::getItem()`) : le
 * texte d'un encadré ou d'une étape garde son « / », et un bloc à écrire aussi, qui n'a rien à couper.
 */
class BlockSlash {

	static list = null;
	static instance = null;
	static range = null;
	static opening = false;

	static open(instance, range) {

		BlockSlash.close();

		const form = instance.closest('.block-form');

		if(
			form === null ||
			form.dataset.blockSplit === undefined ||
			form.inert ||
			BlockEditor.saving !== null ||
			qs('#block-new') !== null
		) {
			return;
		}

		const list = document.createElement('div');
		list.className = 'dropdown-list dropdown-list-open block-slash';
		list.innerHTML = BlockEditor.getEditor().qs('template[data-block-menu]').innerHTML;

		list.addEventListener('pointerdown', e => e.preventDefault());
		list.addEventListener('mousedown', e => e.preventDefault());

		list.addEventListener('click', e => {

			const item = e.target.closest('[data-block-choice]');

			if(item !== null) {
				e.preventDefault();
				BlockSlash.choose(item.dataset.type);
			}

		});

		document.body.append(list);

		BlockSlash.list = list;
		BlockSlash.instance = instance;
		BlockSlash.range = range;

		// Le « / » qui vient d'ouvrir la liste arrive ensuite à l'écouteur `input` de ce fichier, dans le
		// même tour : il ne doit pas la refermer (plus bas). Une composition du clavier (`compositionend`)
		// n'est suivie d'aucun `input` : le drapeau tombe au tour suivant.
		BlockSlash.opening = true;
		setTimeout(() => BlockSlash.opening = false, 0);

		BlockSlash.activate(list.qs('.dropdown-item'));
		BlockSlash.place();

	}

	/**
	 * Sous le « / » si la liste y tient dans la partie visible, au-dessus sinon, et bornée en hauteur
	 * par la plus grande des deux places quand aucune ne suffit. La place se calcule à chaque
	 * défilement ou redimensionnement de la partie visible : elle ne se déclare pas en CSS.
	 */
	static place() {

		const list = BlockSlash.list;

		if(list === null) {
			return;
		}

		const viewport = window.visualViewport;
		const top = viewport.offsetTop;
		const bottom = viewport.offsetTop + viewport.height;
		const caret = BlockSlash.range.getBoundingClientRect();
		const gap = 4;

		list.style.maxHeight = '';

		const height = list.getBoundingClientRect().height;
		const below = bottom - caret.bottom - gap * 2;
		const above = caret.top - top - gap * 2;

		let y;

		if(height <= below || below >= above) {
			list.style.maxHeight = Math.min(height, below) +'px';
			y = caret.bottom + gap;
		} else {
			list.style.maxHeight = Math.min(height, above) +'px';
			y = caret.top - gap - Math.min(height, above);
		}

		const width = list.getBoundingClientRect().width;
		const x = Math.max(viewport.offsetLeft + gap, Math.min(caret.left, viewport.offsetLeft + viewport.width - width - gap));

		list.style.left = (x + window.scrollX) +'px';
		list.style.top = (y + window.scrollY) +'px';
		list.style.zIndex = Lime.getZIndex();

	}

	/**
	 * L'entrée choisie, ramenée sous les yeux par le défilement de la liste seule : `scrollIntoView()`
	 * ferait aussi défiler la page, jusqu'à la liste qui, à l'ouverture, n'est pas encore placée et
	 * attend en bas de `body` (mesuré sur une leçon de quarante blocs : la page remontait de 855 px).
	 */
	static activate(item) {

		if(item === null) {
			return;
		}

		const list = BlockSlash.list;

		list.qsa('.dropdown-item.selected', node => node.classList.remove('selected'));
		item.classList.add('selected');

		const listRect = list.getBoundingClientRect();
		const itemRect = item.getBoundingClientRect();

		if(itemRect.top < listRect.top) {
			list.scrollTop -= listRect.top - itemRect.top;
		} else if(itemRect.bottom > listRect.bottom) {
			list.scrollTop += itemRect.bottom - listRect.bottom;
		}

	}

	static close() {

		if(BlockSlash.list !== null) {
			BlockSlash.list.remove();
		}

		BlockSlash.list = null;
		BlockSlash.instance = null;
		BlockSlash.range = null;

	}

	static isOpen() {
		return BlockSlash.list !== null;
	}

	/**
	 * Coupe le bloc au « / », en une requête (`doSplit`) : le texte d'avant reste dans le bloc, celui
	 * d'après devient un texte sous lui, et le serveur décide seul de ce qui s'écrit.
	 *
	 * Le bloc choisi est posé et prend le curseur dans le geste, sans attendre : c'est un bloc à écrire
	 * (`#block-new`), qui naît par `doCreate` à son premier enregistrement. Cet enregistrement attend la
	 * réponse de la coupe, qui est l'enregistrement en cours (`BlockEditor.saving`), et lit alors ses
	 * voisins dans la page. Refermé vide avant, il se retire comme celui du « + », sans rien écrire.
	 *
	 * Rien avant le « / » : le bloc choisi se pose au-dessus du bloc coupé, qui garde le texte d'après.
	 * Sinon, il se pose dessous, suivi d'un aperçu du texte d'après (`#block-split`), que la réponse
	 * remplace par le bloc né. Refusée, la coupe rend au bloc son texte d'avant la coupe et retire ce
	 * qui a été posé.
	 *
	 * La coupe poste le type seul, lu dans le gabarit de l'entrée : une forme fixée a une clé à elle
	 * (`BlockLib::getChoices()`), et sa variante part avec le bloc choisi, à sa création.
	 */
	static choose(key) {

		const instance = BlockSlash.instance;
		const range = BlockSlash.range;

		BlockSlash.close();

		const form = instance.closest('.block-form');
		const block = form.closest('[data-block]');
		const html = instance.innerHTML;

		const rest = EditorRange.split(instance, range);
		const isBeforeEmpty = (
			instance.textContent.trim() === '' &&
			instance.qs('figure, img, iframe, hr') === null
		);

		let body;

		try {
			body = form.form();
		} catch(e) {

			if(typeof e !== 'string') {
				throw e;
			}

			instance.innerHTML = html;
			Lime.Alert.showStaticError(e);
			return;

		}

		body.set('type', BlockEditor.getNewTemplate(key).content.qs('.block-form input[name="type"]').value);
		body.set('rest', rest);

		// Un paragraphe vidé de son « / » garde son enveloppe, que le serveur ne tient pas pour vide : il
		// garderait un texte sans rien dedans au-dessus du bloc choisi.
		if(isBeforeEmpty) {
			body.set('content', '');
		}

		form.inert = true;

		let preview = null;

		if(isBeforeEmpty) {
			BlockEditor.place(block, 'beforebegin', key);
		} else {

			const writable = BlockEditor.place(block, 'afterend', key);

			if(rest !== '') {

				preview = document.createElement('div');
				preview.id = 'block-split';
				preview.innerHTML = '<div class="block-item"><div class="readitor reador"></div></div>';
				preview.qs('.reador').innerHTML = rest;

				writable.after(preview);

			}

		}

		const instanceId = '#'+ instance.id;

		BlockEditor.saving = BlockEditor.query(form)
			.url('/block/block:doSplit')
			.body(body)
			.fetch()
			.then(() => BlockEditor.isAccepted(form), () => false)
			.then(split => {

				BlockEditor.saving = null;

				if(split) {

					EditorField.flushDraft(form);
					Editor.endInstance(instanceId);

					// Le bloc choisi a pu être retiré pendant la coupe, qui emporte un texte réduit à son « / ».
					BlockEditor.revealStart();

				} else {

					qs('#block-new', writable => BlockEditor.discard(writable));
					preview?.remove();

					instance.innerHTML = html;
					form.inert = false;
					instance.focus();

				}

				return split;

			});

	}

}

document.addEventListener('keydown', e => {

	if(BlockSlash.isOpen() === false) {
		return;
	}

	const items = Array.from(BlockSlash.list.qsa('.dropdown-item'));
	const index = items.findIndex(item => item.classList.contains('selected'));

	switch(e.key) {

		case 'ArrowDown' :
			e.preventDefault();
			e.stopImmediatePropagation();
			BlockSlash.activate(items[(index + 1) % items.length]);
			break;

		case 'ArrowUp' :
			e.preventDefault();
			e.stopImmediatePropagation();
			BlockSlash.activate(items[(index - 1 + items.length) % items.length]);
			break;

		case 'Enter' :
		case 'Tab' :
			e.preventDefault();
			e.stopImmediatePropagation();
			BlockSlash.choose(items[index].dataset.type);
			break;

		case 'Escape' :
			e.preventDefault();
			e.stopImmediatePropagation();
			BlockSlash.close();
			break;

	}

}, true);

/**
 * Toute autre frappe, un effacement compris, laisse le « / » dans le texte et referme la liste. Le
 * « / » qui vient d'ouvrir la liste passe ici juste après elle, dans le même `input` : `editorSlash`
 * part de l'écouteur d'`editor.js`, en capture sur le document, donc toujours avant celui-ci, qui
 * écoute la remontée sur `window` quel que soit l'ordre de chargement des deux fichiers. `opening`
 * le laisse passer.
 */
window.addEventListener('input', () => {

	if(BlockSlash.opening) {
		return;
	}

	if(BlockSlash.isOpen()) {
		BlockSlash.close();
	}

});

document.addEventListener('pointerdown', e => {

	if(
		BlockSlash.isOpen() &&
		BlockSlash.list.contains(e.target) === false
	) {
		BlockSlash.close();
	}

}, true);

window.visualViewport?.addEventListener('resize', () => BlockSlash.place());
window.visualViewport?.addEventListener('scroll', () => BlockSlash.place());
window.addEventListener('scroll', () => BlockSlash.place(), {passive: true});
document.addEventListener('navigationStart', () => BlockSlash.close());

/**
 * Le tableau ouvert (`BlockTypeUi::getTableEditable()`). La grille est `[data-block-table]` : son
 * premier rang est l'en-tête, chaque rang commence par la cellule de sa roue, puis une cellule par
 * colonne. Les champs se nomment `settings[rows][<ligne>][<colonne>]` : chaque geste qui ajoute ou
 * retire une ligne ou une colonne renumérote toute la grille (`renumber()`), le serveur lisant la
 * grille dans l'ordre de ses indices. Le nombre de colonnes est aussi une variable de la grille
 * (`--block-table-columns`), que la feuille lit pour ses pistes.
 *
 * Aucune borne n'est recopiée ici : un collage qui dépasse le nombre de lignes ou de colonnes
 * admis remplit tout, et c'est l'enregistrement qui le refuse sous la borne franchie
 * (`Block::settings.rows`, `.columns`), rien n'étant perdu sans être dit.
 */
class BlockTable {

	/**
	 * La roue dont le menu est ouvert. `Lime.Dropdown` pose le menu dans `body` : le choix qu'on y
	 * fait ne sait plus de quelle ligne ou de quelle colonne il vient.
	 */
	static trigger = null;

	/**
	 * Le champ de la case cliquée dans le tableau lu, que `BlockEditor.open()` vient de remplacer par le
	 * formulaire : rang et colonne se comptent dans le rendu lu (`BlockTypeUi::renderTable()`), dont
	 * chaque enfant est une ligne, l'en-tête comprise, et le nom du champ les reprend dans le même ordre.
	 */
	static getField(target, selected) {

		const cell = target.closest('.block-table:not(.block-table-edit) > div > div');

		if(cell === null) {
			return null;
		}

		const row = cell.parentElement;
		const rowPosition = Array.from(row.parentElement.children).indexOf(row);
		const columnPosition = Array.from(row.children).indexOf(cell);

		return selected.qs('input[name="settings[rows]['+ rowPosition +']['+ columnPosition +']"]');

	}

	static getRows(table) {
		return Array.from(table.children);
	}

	static getColumns(table) {
		return table.firstElementChild.children.length - 1;
	}

	static getGear(table, axis) {

		const template = table.closest('.block-form').qs('template[data-block-table-gear="'+ axis +'"]');

		return template.content.cloneNode(true);

	}

	static createField() {

		const field = document.createElement('input');
		field.type = 'text';
		field.className = 'form-control util-inplace-field';

		return field;

	}

	static createCell(table, isHead) {

		const cell = document.createElement('div');

		cell.append(BlockTable.createField());

		if(isHead) {
			cell.className = 'block-table-head';
			cell.append(BlockTable.getGear(table, 'column'));
		}

		return cell;

	}

	static createRow(table) {

		const row = document.createElement('div');
		row.className = 'util-table-row';

		const gear = document.createElement('div');
		gear.append(BlockTable.getGear(table, 'row'));
		row.append(gear);

		for(let column = 0; column < BlockTable.getColumns(table); column++) {
			row.append(BlockTable.createCell(table, false));
		}

		return row;

	}

	/**
	 * Une ligne s'insère avant le rang `position` (l'en-tête étant le rang 0), ou en bout.
	 */
	static addRow(table, position = null) {

		const row = BlockTable.createRow(table);
		const rows = BlockTable.getRows(table);

		if(position === null || position >= rows.length) {
			table.append(row);
		} else {
			rows[position].insertAdjacentElement('beforebegin', row);
		}

		return row;

	}

	/**
	 * Une colonne s'insère avant la colonne `position` (la première étant 0), ou en bout. Chaque rang
	 * reçoit sa cellule, l'en-tête avec sa roue.
	 */
	static addColumn(table, position = null) {

		BlockTable.getRows(table).forEach((row, index) => {

			const cell = BlockTable.createCell(table, index === 0);

			if(position === null || position + 1 >= row.children.length) {
				row.append(cell);
			} else {
				row.children[position + 1].insertAdjacentElement('beforebegin', cell);
			}

		});

	}

	static removeColumn(table, position) {
		BlockTable.getRows(table).forEach(row => row.children[position + 1].remove());
	}

	static renumber(table) {

		BlockTable.getRows(table).forEach((row, rowIndex) => {

			Array.from(row.children).slice(1).forEach((cell, columnIndex) => {
				cell.qs('input.util-inplace-field').name = 'settings[rows]['+ rowIndex +']['+ columnIndex +']';
			});

		});

		table.style.setProperty('--block-table-columns', BlockTable.getColumns(table));

		table.closest('.block-form').dataset.blockDirty = '1';

	}

	static add(button) {

		const table = button.closest('.block-form').qs('[data-block-table]');

		if(button.dataset.blockTableAdd === 'row') {
			BlockTable.addRow(table).qs('input.util-inplace-field').focus();
		} else {
			BlockTable.addColumn(table);
			table.firstElementChild.lastElementChild.qs('input.util-inplace-field').focus();
		}

		BlockTable.renumber(table);

	}

	/**
	 * Le menu d'une colonne n'offre pas de retirer la dernière : un tableau sans colonne n'a plus de
	 * cellule où écrire. Une ligne peut toujours partir, l'en-tête restant.
	 */
	static open(button, list) {

		BlockTable.trigger = button;

		if(button.dataset.blockTableMenu === 'column') {
			list.qs('[data-block-table-action="column-delete"]').classList.toggle('disabled', BlockTable.getColumns(button.closest('[data-block-table]')) === 1);
		}

	}

	static act(item) {

		const button = BlockTable.trigger;

		if(
			button === null ||
			button.isConnected === false ||
			item.classList.contains('disabled')
		) {
			return;
		}

		const table = button.closest('[data-block-table]');
		const row = button.closest('[data-block-table] > *');
		const rowIndex = BlockTable.getRows(table).indexOf(row);
		const columnIndex = Array.from(row.children).indexOf(button.closest('[data-block-table] > * > *')) - 1;

		switch(item.dataset.blockTableAction) {

			case 'row-before' :
				BlockTable.addRow(table, rowIndex);
				break;

			case 'row-after' :
				BlockTable.addRow(table, rowIndex + 1);
				break;

			case 'row-delete' :
				row.remove();
				break;

			case 'column-before' :
				BlockTable.addColumn(table, columnIndex);
				break;

			case 'column-after' :
				BlockTable.addColumn(table, columnIndex + 1);
				break;

			case 'column-delete' :
				BlockTable.removeColumn(table, columnIndex);
				break;

		}

		BlockTable.trigger = null;
		BlockTable.renumber(table);

	}

	/**
	 * Un collage de plusieurs cellules (un tableur sépare ses colonnes par des tabulations et ses
	 * lignes par des sauts de ligne) remplit la grille à partir de la cellule où il tombe, en
	 * ajoutant les lignes et les colonnes qui manquent. Un texte d'une seule cellule se colle comme
	 * dans tout champ. Le saut de ligne final que pose un tableur n'ouvre pas de ligne vide.
	 */
	static paste(field, e) {

		const text = e.clipboardData.getData('text/plain');

		if(/[\t\r\n]/.test(text.replace(/[\r\n]+$/, '')) === false) {
			return;
		}

		e.preventDefault();

		const lines = text.replace(/[\r\n]+$/, '').split(/\r\n|\r|\n/).map(line => line.split('\t'));

		const table = field.closest('[data-block-table]');
		const cell = field.closest('[data-block-table] > * > *');
		const rowStart = BlockTable.getRows(table).indexOf(cell.parentElement);
		const columnStart = Array.from(cell.parentElement.children).indexOf(cell) - 1;

		const columnsNeeded = columnStart + Math.max(...lines.map(line => line.length));

		while(BlockTable.getColumns(table) < columnsNeeded) {
			BlockTable.addColumn(table);
		}

		while(BlockTable.getRows(table).length < rowStart + lines.length) {
			BlockTable.addRow(table);
		}

		const rows = BlockTable.getRows(table);

		lines.forEach((line, lineIndex) => {

			const cells = Array.from(rows[rowStart + lineIndex].children).slice(1);

			line.forEach((value, valueIndex) => {
				cells[columnStart + valueIndex].qs('input.util-inplace-field').value = value.trim();
			});

		});

		BlockTable.renumber(table);

	}

}

document.addEventListener('click', e => {

	const button = (e.target instanceof Element) ? e.target.closest('[data-block-table-add]') : null;

	if(button !== null) {
		e.preventDefault();
		BlockTable.add(button);
	}

});

/**
 * La roue d'une ligne ou d'une colonne : `Lime.Dropdown` émet ses événements sur le bouton, sans
 * remontée, donc écoutés en capture.
 */
window.addEventListener('dropdownAfterShow', e => {

	if(e.target.dataset?.blockTableMenu !== undefined) {
		BlockTable.open(e.target, e.detail.list);
	}

}, true);

document.addEventListener('click', e => {

	const item = (e.target instanceof Element) ? e.target.closest('.dropdown-item[data-block-table-action]') : null;

	if(item !== null) {
		e.preventDefault();
		BlockTable.act(item);
	}

});

document.addEventListener('paste', e => {

	const field = (e.target instanceof Element) ? e.target.closest('[data-block-table] input.util-inplace-field') : null;

	if(field !== null) {
		BlockTable.paste(field, e);
	}

});

/**
 * Les étiquettes ouvertes (`BlockTypeUi::getTagsEditable()`). Chaque mot est une pastille de la
 * rangée `[data-block-tags]`, son champ nommé `settings[tags][]` : le serveur lit les mots dans l'ordre
 * de l'écran, sans indice à renuméroter. Une pastille neuve vient du `template` du bloc.
 *
 * Le plafond est celui que la rangée porte (`data-block-tags-max`, `BlockSetting::TAGS_MAX`) : à lui,
 * « + Étiquette » disparait et Entrée ne pose plus rien. Un POST qui le dépasse reste refusé par le
 * serveur (`Block::settings.full`). La rangée n'est jamais vide : retirer la dernière pastille en
 * rend une vide, où écrire.
 */
class BlockTags {

	/**
	 * Le champ de l'étiquette cliquée dans la rangée lue, que `BlockEditor.open()` vient de remplacer par
	 * le formulaire : la pastille lue et la pastille ouverte sont au même rang.
	 */
	static getField(target, selected) {

		const tag = target.closest('.block-tags:not(.block-tags-edit) > .badge');

		if(tag === null) {
			return null;
		}

		const position = Array.from(tag.parentElement.children).indexOf(tag);

		return selected.qsa('[data-block-tags] > .badge > input.util-inplace-field')[position] ?? null;

	}

	static getTags(list) {
		return Array.from(list.qsa(':scope > .badge'));
	}

	static isFull(list) {
		return BlockTags.getTags(list).length >= parseInt(list.dataset.blockTagsMax);
	}

	/**
	 * Une pastille vide après celle donnée, ou en bout de rangée, où le curseur se pose. Le champ du
	 * modèle porte l'identifiant que `FormUi` lui a donné, qu'une copie dupliquerait.
	 */
	static add(list, after = null) {

		const tag = list.closest('.block-form').qs('template[data-block-tags-new]').content.firstElementChild.cloneNode(true);
		const field = tag.qs('input.util-inplace-field');

		field.removeAttribute('id');

		if(after === null) {
			list.qs(':scope > [data-block-tags-add]').insertAdjacentElement('beforebegin', tag);
		} else {
			after.insertAdjacentElement('afterend', tag);
		}

		field.focus();

		BlockTags.refresh(list);

	}

	static remove(link) {

		const list = link.closest('[data-block-tags]');

		link.closest('.badge').remove();

		if(BlockTags.getTags(list).length === 0) {
			BlockTags.add(list);
		} else {
			BlockTags.refresh(list);
		}

	}

	static refresh(list) {

		list.qs(':scope > [data-block-tags-add]').classList.toggle('hide', BlockTags.isFull(list));

		list.closest('.block-form').dataset.blockDirty = '1';

	}

}

document.addEventListener('click', e => {

	if((e.target instanceof Element) === false) {
		return;
	}

	const add = e.target.closest('[data-block-tags-add]');
	const remove = e.target.closest('[data-block-tags-remove]');

	if(add !== null) {
		e.preventDefault();
		BlockTags.add(add.closest('[data-block-tags]'));
	} else if(remove !== null) {
		e.preventDefault();
		BlockTags.remove(remove);
	}

});

/**
 * Entrée dans une étiquette pose la suivante, comme on tape une liste de mots, au lieu d'envoyer le
 * formulaire, ce que le navigateur ferait de lui-même depuis un champ de texte. Pendant une composition
 * (accents d'un clavier, saisie d'un téléphone), Entrée valide le caractère et rien d'autre.
 */
document.addEventListener('keydown', e => {

	if(
		e.key !== 'Enter' ||
		e.isComposing ||
		(e.target instanceof Element) === false
	) {
		return;
	}

	const tag = e.target.closest('[data-block-tags] > .badge');

	if(tag === null) {
		return;
	}

	e.preventDefault();

	const list = tag.parentElement;

	if(BlockTags.isFull(list) === false) {
		BlockTags.add(list, tag);
	}

});
