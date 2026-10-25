/**
 * La vitesse de lecture d'une scène : un bouton qui dit la vitesse en cours et ouvre la liste des vitesses (`SceneUi::getSpeed()`).
 * Le facteur de chaque vitesse vient de la liste, et la scène le multiplie à son pas.
 *
 * Le choix tient le temps de la page : chaque visite repart à la vitesse retenue au rendu.
 *
 * Un script classique, posé par la scène qui s'en sert (`Asset::js('scene', 'speed.js')`, avec `dropdown.js`) : il ne lit rien
 * de three.
 */
class SceneSpeed {

	/**
	 * `changed()` dit que la vitesse a changé.
	 */
	constructor(container, changed) {

		this.container = container;
		this.labels = [...container.qsa('[data-scene-speed-label]')];

		// La liste ouverte quitte la scène pour le <body> : ses entrées se retiennent tant qu'elle y est encore
		this.items = [...container.qsa('[data-scene-speed-item]')];

		this.restore();

		this.items.forEach(item => item.addEventListener('click', () => {
			this.choose(item);
			changed();
		}));

		const button = container.qs('[data-scene-speed]');
		button.addEventListener('dropdownAfterShow', event => SceneDropdown.follow(container, button, event.detail.list));

	}

	choose(chosen) {

		this.set(Number(chosen.dataset.sceneSpeedItem));
		this.labels.forEach(label => label.classList.toggle('selected', label.dataset.sceneSpeedLabel === chosen.dataset.sceneSpeedItem));
		this.items.forEach(item => item.classList.toggle('selected', item === chosen));

	}

	/**
	 * Une vitesse hors de la liste, celle du film de l'année du plan (`MapSceneRecord`) : la liste garde la vitesse choisie, que
	 * `restore()` rend ensuite.
	 */
	set(factor) {

		this.factor = factor;

		// Ce que la feuille d'une scène anime pendant la lecture dure d'autant moins que la lecture va vite
		this.container.style.setProperty('--scene-speed', factor);

	}

	restore() {
		this.choose(this.items.find(item => item.classList.contains('selected')));
	}

}
