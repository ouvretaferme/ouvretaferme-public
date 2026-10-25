/**
 * La rédaction d'une note de l'équipe : l'aperçu et le nombre de destinataires, que le serveur
 * recalcule à la saisie sans rien enregistrer, puis l'envoi depuis la confirmation.
 */
class CommunicationNoteAi {

	static delay = 500;

	static init(form) {

		form.previewTimer = null;

		// `change` pour les cases, `input` pour les champs de texte et l'éditeur.
		form.addEventListener('input', () => CommunicationNoteAi.schedule(form));
		form.addEventListener('change', () => CommunicationNoteAi.schedule(form));

	}

	static schedule(form) {

		clearTimeout(form.previewTimer);
		form.previewTimer = setTimeout(() => CommunicationNoteAi.refresh(form), CommunicationNoteAi.delay);

	}

	/**
	 * Sans contexte : un aperçu ne doit ni verrouiller « Envoyer », ni effacer les refus que le dernier
	 * envoi a posés sur les champs. `form()` et non `FormData` : lui seul lit le contenu de l'éditeur.
	 */
	static refresh(form) {

		new Ajax.Query()
			.method('post')
			.url(form.dataset.preview)
			.body(form.form())
			.fetch();

	}

	/**
	 * Le bouton de la confirmation poste le formulaire resté sous le panneau. Un `data-ajax-submit`
	 * ferait la même chose en réécrivant la cible du formulaire, qui partirait alors sans
	 * confirmation à la prochaine touche Entrée si l'on annulait.
	 *
	 * Le cadriciel ne lance le `data-waiter` que sur un `data-ajax` ou un formulaire : un bouton qui
	 * poste par `onclick` le lance lui-même. L'envoi écrit une ligne par destinataire, plusieurs
	 * milliers sur tout le parc : sans lui, le bouton ne dit pas qu'il travaille.
	 */
	static send(button) {

		const enable = startWaiter(button);

		new Ajax.Query(button)
			.method('post')
			.url('/communication/admin/noteAi:doCreate')
			.body(qs('#communication-note-create').form())
			.fetch()
			.then(() => enable(), () => enable());

	}

}
