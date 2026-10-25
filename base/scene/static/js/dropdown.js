/**
 * Une liste déroulante ouverte dans une scène en plein écran. Le plein écran du navigateur ne montre que la scène, alors que la
 * liste ouverte part dans le <body> : elle revient dans la scène. Toute liste déroulante d'une scène passe par ici, à
 * l'événement `dropdownAfterShow` de son bouton, celle de la vitesse de lecture comprise (`SceneSpeed`).
 *
 * Un script classique, posé par la scène qui s'en sert (`Asset::js('scene', 'dropdown.js')`) : il ne lit rien de three.
 */
class SceneDropdown {

	/**
	 * Posée près de son bouton, la liste l'est par un décalage compté depuis le <body>, que la scène ne partage pas : l'écart
	 * entre sa place d'avant et celle d'après se lit donc de part et d'autre du déplacement, seule entorse à « mesurer puis
	 * écrire », une fois par ouverture. Au milieu de l'écran, `Lime.Dropdown` la centre après coup, d'où qu'elle soit.
	 */
	static follow(container, button, list) {

		list.style.translate = '';

		if(document.fullscreenElement !== container) {
			return;
		}

		const before = list.getBoundingClientRect();

		container.append(list);

		if(button.dataset.dropdownDisplay === 'around') {
			const after = list.getBoundingClientRect();
			list.style.translate = (before.left - after.left) +'px '+ (before.top - after.top) +'px';
		}

	}

}
