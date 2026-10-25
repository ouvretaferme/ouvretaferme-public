/**
 * Un type de média sans champ d'élément (`MediaUi::getHashCamera()`). L'envoi rend un hash, posé dans
 * le champ caché du cadre : c'est le formulaire hôte qui l'enregistre, et un panneau fermé sans
 * l'enregistrer n'écrit rien. Le média orphelin part au nettoyage de nuit.
 *
 * Un type s'y range par une sous-classe qui ne porte que son nom (`maintenanceDoneAi.js`).
 */
class MediaHash extends Media {

	onUploaded(context, id, data) {

		// `context` est le champ de fichier du menu : son cadre est le premier parent qui porte le type
		const camera = context.firstParent('.media-image-upload[data-type="' + this.type + '"]');

		if(camera === null) {
			return;
		}

		MediaHash.fill(camera, data.media.hash, data.media.urls.m);

	};

	static fill(camera, hash, url) {

		camera.qs('input[type="hidden"]').value = hash ?? '';
		camera.qs('.media-image-content').style.backgroundImage = (url === null) ? '' : 'url(' + url + ')';

		camera.classList.toggle('media-image-yes', hash !== null);
		camera.classList.toggle('media-image-no', hash === null);
		camera.classList.toggle('media-image-upload-empty', hash === null);

		camera.qsa('[data-action="media-hash-remove"], .dropdown-divider', node => node.classList.toggle('hide', hash === null));

	};

}

document.delegateEventListener('click', 'a[data-action="media-hash-remove"]', function(e) {

	e.preventDefault();

	MediaHash.fill(this.firstParent('.media-image-upload[data-type]'), null, null);

});
