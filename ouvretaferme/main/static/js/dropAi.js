/**
 * util-drop (`\main\DropAiUi`) : le survol d'un fichier glissé, et ce que la zone montre selon ce
 * qu'elle a reçu. Ce qui est reçu et ce qui part se compte dans le script de chaque écran, qui le dit
 * à `refresh()` après chaque changement : un dépôt de justificatifs reçoit à la réponse du serveur, un
 * import de vente dès qu'un fichier est posé dans la liste (R-42a).
 */
class MainDrop {

	static over(event, zone) {

		event.preventDefault();
		zone.classList.add('util-drop-over');

	}

	static leave(zone) {
		zone.classList.remove('util-drop-over');
	}

	/**
	 * `container` porte la zone et les grands boutons du téléphone.
	 *
	 * R-42 : un bouton qui choisit des fichiers dit « Ajouter d'autres fichiers » dès qu'un fichier est
	 * reçu. L'appel passe en contour dès que la zone liste un fichier, pour laisser le plein au bouton
	 * qui termine. R-41 : celui-ci reste visible mais inactif tant qu'un fichier part, ou que l'écran
	 * n'a rien à lui faire faire.
	 */
	static refresh(container, received, inactive) {

		const zone = container.querySelector('.util-drop');
		const filled = (zone.querySelector('.util-drop-list > *, .util-previews > *') !== null);

		container.querySelectorAll('[data-drop-choose]').forEach(label => {
			label.textContent = received ? label.dataset.dropMore : label.dataset.dropChoose;
		});

		zone.querySelectorAll('.util-drop-head [data-drop-primary]').forEach(button => {
			button.classList.toggle('btn-primary', filled === false);
			button.classList.toggle('btn-outline-primary', filled);
		});

		zone.querySelectorAll('.util-drop-foot .btn').forEach(button => {

			button.classList.toggle('disabled', inactive);

			// La navigation ajax écoute `a[href]` en capture sur le document avant le garde ci-dessous, et
			// Entrée suit un lien qui a le focus : un lien inactif dépose donc son `href` le temps de l'envoi.
			if(inactive && button.hasAttribute('href')) {
				button.dataset.dropHref = button.getAttribute('href');
				button.removeAttribute('href');
			} else if(inactive === false && button.dataset.dropHref !== undefined) {
				button.setAttribute('href', button.dataset.dropHref);
				delete button.dataset.dropHref;
			}

		});

	}

	/**
	 * Le compte du pied, dans les textes de cette sorte que `DropAiUi::getFoot()` a posés : un, aucun,
	 * ou le pluriel, qui dit aussi zéro quand la sorte n'a pas de texte pour aucun.
	 */
	static count(zone, value, kind = '') {

		const node = zone.querySelector('[data-drop-count]');
		const texts = JSON.parse(node.dataset.dropCount)[kind];

		let text;

		if(value === 1) {
			text = texts.one;
		} else if(value === 0 && texts.none !== undefined) {
			text = texts.none;
		} else {
			text = texts.many;
		}

		node.textContent = text.replace('{value}', value);

	}

	/**
	 * Le poids d'un fichier refusé, en mégaoctets à une décimale, qui remplace `{size}` dans la phrase
	 * que le serveur a écrite pour le navigateur (`\util\TextUi::size('{size}')` par exemple).
	 */
	static getSize(size) {
		return (size / 1024 / 1024).toLocaleString('fr-FR', {maximumFractionDigits: 1});
	}

}

// Un lien inactif n'arrête pas son `onclick` : la classe ne suffit pas, le clic s'arrête ici, en
// capture, avant d'atteindre le bouton (R-41).
document.delegateEventListener('click', '.util-drop-foot .btn.disabled', e => {

	e.preventDefault();
	e.stopPropagation();

});
