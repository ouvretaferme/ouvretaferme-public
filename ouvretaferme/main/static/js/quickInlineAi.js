/*
 * util-quick-inline : une valeur qui devient champ sur place, dans sa cellule.
 *
 * util-quick ouvre un formulaire en plein écran (dropdown-list-minimalist), ce qui floute la page
 * et cache les cases voisines. Ici le champ prend la place de la valeur : le tableau reste sous les
 * yeux pendant la saisie.
 *
 * Le composant ne porte que l'ouverture et la fermeture. Le champ est un vrai formulaire ajax du
 * cadriciel (data-ajax-form) : Entrée l'envoie nativement, les erreurs suivent la chaîne FailWatch,
 * et la réponse remplace ce qui a changé. Échap et la perte du focus referment sans rien envoyer.
 *
 * Une boîte qui porte data-quick-inline-submit-on-blur (QuickInlineAiUi::get(), submitOnBlur) enregistre
 * à la perte du focus au lieu de refermer, si un champ a changé ; sinon elle referme. Échap y remet
 * aussi les champs à leur valeur d'origine : sans cela, la saisie abandonnée resterait dans la boîte
 * refermée et partirait à la prochaine sortie.
 *
 * Pendant l'envoi, la sortie ne fait rien, sur toute boîte. Le verrou d'Ajax.Query désactive le bouton
 * d'envoi : un clic sur lui retire donc le focus du champ, et la boîte se refermerait sur la réponse,
 * emportant le refus qui doit s'afficher sous le champ. Un refus rend donc le focus au champ refusé,
 * le premier de la boîte à défaut, quand rien d'autre ne l'a pris. Sur une boîte qui referme à la
 * sortie, une réponse réussie qui laisse la boîte en place sans que le focus y soit la referme, comme
 * l'aurait fait la sortie. Sur une boîte qui enregistre
 * à la sortie, une réponse qui remplace la boîte retire aussi le focus du champ après qu'Ajax.Query a
 * rendu son verrou et avant ajaxAfterFetch, et la valeur, encore différente de l'origine, repartirait.
 *
 * La valeur d'origine d'un champ est celle que la base a acceptée : elle ne change qu'à une réponse
 * réussie, si bien qu'Échap après un refus rend le texte enregistré. Une sortie qui retrouve la valeur
 * que la base vient de refuser abandonne la saisie, comme Échap, au lieu de renvoyer le même refus.
 *
 * form.reset() ne ramène pas un champ caché : pour lui, écrire value réécrit l'attribut, qui est aussi
 * sa valeur d'origine. La boîte retient donc ses champs cachés à l'ouverture et les remet à Échap, puis
 * émet quickInlineReset, pour que ce qui les montre ailleurs dans la page (les jours d'un rappel en
 * pastilles, par exemple) se remette d'accord avec eux.
 *
 * Ce qui s'ouvre avec le champ ailleurs dans la page (sous le libellé d'une ligne, par exemple) porte
 * data-quick-inline-for="<id de la boîte>" et la classe hide au repos.
 *
 * Délégation sur document : la navigation est ajax, et une réponse remplace les boîtes sans prévenir.
 */
class MainQuickInline {

	static open(box) {

		document.qsa('.util-quick-inline.util-quick-inline-open', other => {
			if(other !== box) {
				this.close(other);
			}
		});

		if(box.dataset.quickInlineSubmitOnBlur !== undefined) {
			this.hiddens(box).forEach(field => field.dataset.quickInlineOrigin ??= field.value);
		}

		box.classList.add('util-quick-inline-open');
		this.linked(box).forEach(node => node.classList.remove('hide'));

		box.qs('.util-quick-inline-field input:not([type="hidden"])', input => {
			input.focus();
			input.select();
		});

	}

	static close(box) {

		box.classList.remove('util-quick-inline-open');
		this.linked(box).forEach(node => node.classList.add('hide'));

	}

	static escape(box) {

		if(box.dataset.quickInlineSubmitOnBlur !== undefined) {
			box.qs('.util-quick-inline-field', form => form.reset());
			// hideErrors() ne parcourt que les descendants de ce qu'on lui passe, et le refus pose aussi
			// form-error sur le formulaire lui-même, qui ferait encore luire le bouton d'envoi
			Lime.Alert.hideErrors(box);
			this.hiddens(box).forEach(field => field.value = field.dataset.quickInlineOrigin ?? field.value);
			delete box.dataset.quickInlineRefused;
		}

		this.close(box);

		if(box.dataset.quickInlineSubmitOnBlur !== undefined) {
			box.dispatchEvent(new CustomEvent('quickInlineReset', {bubbles: true}));
		}

	}

	static blur(box) {

		if(box.dataset.quickInlineSending !== undefined) {
			return;
		} else if(box.dataset.quickInlineSubmitOnBlur === undefined) {
			this.close(box);
		} else if(this.changed(box) === false) {
			this.close(box);
		} else if(box.dataset.quickInlineRefused === this.values(box)) {
			this.escape(box);
		} else {
			box.qs('.util-quick-inline-field', form => form.requestSubmit());
		}

	}

	static sent(box, failed) {

		delete box.dataset.quickInlineSending;

		const isOpen = (box.isConnected && box.classList.contains('util-quick-inline-open'));

		if(isOpen && failed && (document.activeElement === null || document.activeElement === document.body)) {
			(box.querySelector('.form-error-wrapper :is(input:not([type="hidden"]), textarea)') ?? this.fields(box)[0])?.focus();
		}

		if(box.dataset.quickInlineSubmitOnBlur === undefined) {

			if(isOpen && failed === false && box.contains(document.activeElement) === false) {
				this.close(box);
			}

			return;

		}

		if(failed) {
			box.dataset.quickInlineRefused = this.values(box);
		} else {
			delete box.dataset.quickInlineRefused;
			this.fields(box).forEach(field => field.defaultValue = field.value);
			this.hiddens(box).forEach(field => field.dataset.quickInlineOrigin = field.value);
		}

	}

	// Un champ caché compte comme les autres : ce qui le remplit ailleurs dans la page (un jour choisi
	// en pastille) n'envoie rien, c'est la sortie de la boîte qui l'emporte
	static changed(box) {
		return (
			this.fields(box).some(field => field.value !== field.defaultValue) ||
			this.hiddens(box).some(field => field.value !== field.dataset.quickInlineOrigin)
		);
	}

	static values(box) {
		return JSON.stringify([...this.fields(box), ...this.hiddens(box)].map(field => field.value));
	}

	static fields(box) {
		return Array.from(box.querySelectorAll('.util-quick-inline-field :is(input:not([type="hidden"]), textarea)'));
	}

	static hiddens(box) {
		return Array.from(box.querySelectorAll('.util-quick-inline-field input[type="hidden"]'));
	}

	static linked(box) {
		return box.id ? Array.from(document.querySelectorAll('[data-quick-inline-for="'+ box.id +'"]')) : [];
	}

}

// L'envoi commence à ajaxBeforeFetch et non à submit : form.js peut refuser l'envoi avant la requête
// (une URL invalide, le verrou d'Ajax.Query), et la boîte ne recevrait alors jamais de réponse
document.addEventListener('ajaxBeforeFetch', e => {

	const box = e.detail.trigger?.closest('.util-quick-inline') ?? null;

	if(box !== null) {
		box.dataset.quickInlineSending = '';
	}

});

// Une réponse qui remplace la boîte la détache : ajax.js émet alors sur le document, et le détail
// porte encore le nœud d'origine, dont closest() remonte la branche détachée
document.addEventListener('ajaxAfterFetch', e => {

	const box = e.detail.trigger?.closest('.util-quick-inline') ?? null;

	if(box !== null) {
		MainQuickInline.sent(box, e.detail.failed !== false);
	}

});

// Un lien qui ouvre la boîte hors d'elle (le code d'une case) la nomme ; celui qu'elle porte ouvre la sienne
document.delegateEventListener('click', '[data-quick-inline-open]', function(e) {

	e.preventDefault();

	const box = this.dataset.quickInlineOpen ? document.getElementById(this.dataset.quickInlineOpen) : this.closest('.util-quick-inline');

	if(box !== null) {
		MainQuickInline.open(box);
	}

});

document.delegateEventListener('keydown', '.util-quick-inline-field', function(e) {

	if(e.key === 'Escape') {
		e.preventDefault();
		MainQuickInline.escape(this.closest('.util-quick-inline'));
	}

});

// Une fenêtre qui perd le focus ne referme rien : c'est ce qui arrive quand un champ fichier ouvre le
// sélecteur du système, et la boîte doit être encore là pour recevoir le fichier choisi
document.delegateEventListener('focusout', '.util-quick-inline', function(e) {

	if(
		this.classList.contains('util-quick-inline-open') &&
		document.hasFocus() &&
		(e.relatedTarget === null || this.contains(e.relatedTarget) === false)
	) {
		MainQuickInline.blur(this);
	}

});

// Un clic dans ce qui s'est ouvert avec le champ ne doit pas lui retirer le focus, sinon la perte du
// focus referme tout avant que le clic n'arrive
document.delegateEventListener('mousedown', '[data-quick-inline-for]', function(e) {
	e.preventDefault();
});
