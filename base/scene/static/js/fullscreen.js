/**
 * Le plein écran d'une scène, qui emporte tout son conteneur, fiches et réglages compris. Deux boutons, l'entrée et la sortie
 * (`SceneUi::getFullscreen()`), cachés au rendu : un navigateur qui ne permet pas le plein écran, Safari sur iPhone, ne les
 * montre jamais. Échap en sort sans passer par le bouton, d'où la bascule sur l'événement du navigateur et non sur le clic.
 *
 * Un script classique, posé par la scène qui s'en sert (`Asset::js('scene', 'fullscreen.js')`) avec `swap.js`, qui
 * passe le focus d'un bouton à l'autre : il ne lit rien de three. Ses écouteurs tombent avec `signal`, que la scène
 * lève en se fermant.
 */
class SceneFullscreen {

	constructor(container, enter, leave, signal) {

		// Un navigateur sans l'API ne la déclare pas du tout : seule la valeur vraie montre le bouton
		if(document.fullscreenEnabled !== true) {
			return;
		}

		enter.classList.remove('hide');

		// Un refus du navigateur laisse la scène telle quelle, ce qui suffit à le dire : il n'y a rien à afficher de plus
		enter.addEventListener('click', () => container.requestFullscreen().catch(() => {}), { signal });
		leave.addEventListener('click', () => document.exitFullscreen().catch(() => {}), { signal });

		container.addEventListener('fullscreenchange', () => {
			if(document.fullscreenElement === container) {
				SceneSwap.swap([enter], [leave], container);
			} else {
				SceneSwap.swap([leave], [enter], container);
			}
		}, { signal });

	}

}
