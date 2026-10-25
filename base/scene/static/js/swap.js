/**
 * Des boutons qui prennent la place les uns des autres : Lecture et Pause, l'entrée et la sortie du plein écran.
 *
 * Un script classique, posé par la scène qui s'en sert (`Asset::js('scene', 'swap.js')`) : il ne lit rien de three.
 */
class SceneSwap {

	/**
	 * Cache `hidden` et montre `shown`. Un bouton activé se cache souvent lui-même, et le focus tomberait alors sur la
	 * page : tenu au clavier, il passe au premier bouton montré, qui se lit à l'anneau et s'active de nouveau à la même
	 * touche ; pris à la souris, il passe à `fallback`, la scène, qui reçoit ainsi les touches suivantes sans que la page
	 * défile.
	 */
	static swap(hidden, shown, fallback) {

		const focused = hidden.find(button => button.contains(document.activeElement)) ?? null;
		const visible = (focused !== null && document.activeElement.matches(':focus-visible'));

		hidden.forEach(button => button.classList.add('hide'));
		shown.forEach(button => button.classList.remove('hide'));

		if(focused === null) {
			return;
		}

		if(visible) {
			shown[0].focus();
		} else {
			fallback.focus({ preventScroll: true });
		}

	}

}
