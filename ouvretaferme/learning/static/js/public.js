/**
 * La page publique des formations d'un réseau, et le code que le réseau colle sur son site.
 *
 * Intégrée (`learning-embed`), la page dit sa hauteur à la page hôte, dont le script
 * (`/reseau/embed-learning.js`) règle celle de l'iframe. Un `ResizeObserver` plutôt qu'une mesure à
 * chaque navigation : l'étape 1 déplie des textes d'éditeur et des images après le rendu, et une
 * erreur de formulaire allonge la page sans navigation.
 */
class LearningPublic {

	static observer = null;

	static embed() {

		if(document.body.matches('[data-template~="learning-embed"]') === false || window.parent === window) {
			return;
		}

		// Réarmé à chaque navigation : l'observateur ne suit que le nœud qu'on lui a donné.
		if(LearningPublic.observer !== null) {
			LearningPublic.observer.disconnect();
		}

		// La hauteur de `main` et non celle du document : le document mesure au moins la fenêtre,
		// donc l'iframe, et ne rendrait jamais une hauteur plus petite que celle déjà posée.
		const main = qs('body > main');

		LearningPublic.observer = new ResizeObserver(() => {
			window.parent.postMessage({learningHeight: Math.ceil(main.getBoundingClientRect().bottom + window.scrollY)}, '*');
		});

		LearningPublic.observer.observe(main);

	}

	/**
	 * Copie `data-text`, ou le texte du bloc que désigne `data-selector`. `doCopy()` du cadriciel
	 * copie son `innerHTML`, qui rend un code à coller échappé (`&lt;script&gt;`) : ce qui se colle
	 * doit être le texte affiché.
	 */
	static copy(target) {

		const text = (target.dataset.text !== undefined) ? target.dataset.text : qs(target.dataset.selector).textContent;

		navigator.clipboard.writeText(text).then(() => alert(target.dataset.message));

	}

}

document.addEventListener('navigation.wakeup', () => LearningPublic.embed());
