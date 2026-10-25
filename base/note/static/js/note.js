/**
 * La note ouverte par qui peut la modifier : sa lecture est son formulaire (`NoteUi::getUpdate()`), et
 * « Enregistrer » ne paraît que tant qu'elle diffère de ce que le serveur a rendu.
 *
 * La référence se prend au premier geste dans la note, pas au rendu : l'éditeur réorganise son contenu
 * en démarrant (`Editor.startInstance()`), après le rendu, et ce qu'il range n'est pas un changement.
 *
 * Le nom de la classe est préfixé par le package : tous les JS partagent un seul scope global et
 * s'accumulent en navigation ajax (`framework/dev/test/JsSentinel.t.php`).
 */
class NoteUpdate {

	static start(form) {

		if(form.noteUpdateReference !== undefined) {
			return;
		}

		form.noteUpdateReference = NoteUpdate.serialize(form);

		// L'éditeur pose ses photos sans émettre `input` : ce qui entre ou sort du formulaire se compare
		// aussi.
		new MutationObserver(() => NoteUpdate.schedule(form)).observe(form, {
			childList: true,
			characterData: true,
			subtree: true
		});

	}

	/**
	 * Une frappe émet `input` et une mutation, l'éditeur des rafales de mutations : une
	 * seule comparaison par image suffit à les couvrir toutes.
	 */
	static schedule(form) {

		if(form.noteUpdateScheduled === true) {
			return;
		}

		form.noteUpdateScheduled = true;

		requestAnimationFrame(() => {
			form.noteUpdateScheduled = false;
			NoteUpdate.check(form);
		});

	}

	static check(form) {

		if(form.noteUpdateReference === undefined) {
			return;
		}

		const changed = (NoteUpdate.serialize(form) !== form.noteUpdateReference);
		const target = NoteUpdate.getSubmitTarget(form);

		if(target === null) {
			return;
		}

		// Le pied du panneau reste vide sans changement : vide, il n'est ni collant ni bordé (lime.css).
		if(changed === false) {

			if(target.innerHTML !== '') {
				target.innerHTML = '';
			}

		} else if(target.innerHTML === '') {
			target.innerHTML = form.qs('template[data-note-submit]').innerHTML;
		}

	}

	static getSubmitTarget(form) {

		const panel = form.closest('.panel');

		if(panel !== null) {
			return panel.qs('.panel-footer');
		}

		return form.qs('.note-update-submit');

	}

	/**
	 * Les champs tels que le formulaire les posterait (`Element.prototype.form()`). Ce que l'éditeur
	 * pose dans son contenu pour qu'on le manie (la classe `selected` d'une photo choisie, les barres
	 * qu'il ouvre sur une photo survolée ou dans une figure vide) et la ligne vide d'un éditeur qu'on
	 * vient d'ouvrir ne sont pas des changements.
	 */
	static serialize(form) {

		const values = [];

		new FormData(form).forEach((value, name) => {
			if(typeof value === 'string') {
				values.push(name +'='+ value);
			}
		});

		form.qsa('.editor', editor => {

			const clone = editor.cloneNode(true);
			clone.qsa('figure.selected', figure => figure.classList.remove('selected'));
			clone.qsa('.editor-box-media, .editor-box-figure, .editor-box-figcaption', box => box.remove());

			const html = clone.innerHTML.match(/^<p(?: [a-z\-]+="[a-zA-Z0-9]+")*>(?:<br\/?>)*<\/p>$/) === null ? clone.innerHTML : '';

			values.push(editor.dataset.name +'='+ html);

		});

		return values.join('\n');

	}

	static getForm(node) {

		if(node.form instanceof HTMLFormElement) {
			return node.form.matches('form.note-update') ? node.form : null;
		}

		return node.closest('form.note-update');

	}

	/**
	 * Le titre prend la largeur de ce qu'il porte par `field-sizing`, que Firefox ignore : il y garde
	 * sinon ses 20 caractères par défaut, et rogne un titre long. Même règle que les onglets des blocs
	 * (`BlockEditor.sizeField()`), dont le script n'est pas chargé ici.
	 */
	static sizeTitle(input) {
		input.size = Math.max(Math.max(input.value.length, input.placeholder.length) + 1, 7);
	}

	/**
	 * Une entrée du carnet ou des lecteurs écrit le champ caché du formulaire, et la marque prend son
	 * libellé. Le carnet se choisit dans la marque comme dans la roue : la sélection se marque dans
	 * les deux menus, que `Lime.Dropdown` a pu déplacer dans `body`, et seulement sur les entrées de la
	 * même propriété, la roue mêlant le carnet à d'autres gestes.
	 */
	static choose(item) {

		const form = document.getElementById(item.dataset.form);

		if(form === null) {
			return;
		}

		NoteUpdate.start(form);

		const property = item.dataset.noteChoice;
		const items = Array.from(qsa('.dropdown-item[data-form="'+ item.dataset.form +'"][data-note-choice="'+ property +'"]'));

		form.elements.namedItem(property).value = item.dataset.value;

		qs('#'+ item.dataset.form +'-'+ property +' .note-choice-label', label => {
			label.innerHTML = item.qs('.note-choice-label').innerHTML;
		});

		items.forEach(node => node.classList.toggle('selected', node.dataset.value === item.dataset.value));

		NoteUpdate.check(form);

	}

}

['pointerdown', 'focusin', 'keydown'].forEach(type => document.addEventListener(type, e => {

	const form = (e.target instanceof Element) ? NoteUpdate.getForm(e.target) : null;

	if(form !== null) {
		NoteUpdate.start(form);
	}

}, true));

['input', 'change'].forEach(type => document.addEventListener(type, e => {

	const form = (e.target instanceof Element) ? NoteUpdate.getForm(e.target) : null;

	if(form === null) {
		return;
	}

	if(e.target.matches('input.note-update-title')) {
		NoteUpdate.sizeTitle(e.target);
	}

	NoteUpdate.schedule(form);

}));

document.addEventListener('click', e => {

	const item = (e.target instanceof Element) ? e.target.closest('.dropdown-item[data-note-choice]') : null;

	if(item !== null) {
		e.preventDefault();
		NoteUpdate.choose(item);
	}

});

/**
 * Le bloc Notes des écrans de production (L84). Le replier ou le déplier vaut pour tous : chaque bloc
 * de l'écran suit, la série et le panneau d'intervention ouvert par-dessus compris, et la préférence
 * s'écrit sur le membre, que les écrans suivants relisent.
 */
class NoteLinked {

	static toggle(head) {

		const fold = head.closest('.note-linked');
		const open = (fold.classList.contains('util-fold-open') === false);

		qsa('.note-linked', node => node.classList.toggle('util-fold-open', open));

		const body = new FormData();
		body.append('id', fold.dataset.structure);
		body.append('viewNoteLinked', open ? '1' : '0');

		new Ajax.Query(fold)
			.url('/note/note:doLinked')
			.body(body)
			.fetch();

	}

}
