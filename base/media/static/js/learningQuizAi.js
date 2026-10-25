/**
 * Image d'une question ou d'un choix de quiz (`LearningQuizAiUi::getHashCamera()`). L'envoi rend un
 * hash, posé dans le champ caché du cadre : c'est le formulaire de la question qui l'enregistre, et
 * un panneau fermé sans l'enregistrer n'écrit rien. Le média orphelin part au nettoyage de nuit.
 */
class MediaLearningQuiz extends Media {

	type = 'learning-quiz';

	onUploaded(context, id, data) {

		// `context` est le champ de fichier du menu : son cadre est le premier parent qui porte le type
		const camera = context.firstParent('.media-image-upload[data-type="learning-quiz"]');

		if(camera === null) {
			return;
		}

		MediaLearningQuiz.fill(camera, data.media.hash, data.media.urls.m);

	};

	static fill(camera, hash, url) {

		camera.qs('input[type="hidden"]').value = hash ?? '';
		camera.qs('.media-image-content').style.backgroundImage = (url === null) ? '' : 'url(' + url + ')';

		camera.classList.toggle('media-image-yes', hash !== null);
		camera.classList.toggle('media-image-no', hash === null);
		camera.classList.toggle('media-image-upload-empty', hash === null);

		camera.qsa('[data-action="learning-quiz-remove"], .dropdown-divider', node => node.classList.toggle('hide', hash === null));

	};

}

Media.classes['learning-quiz'] = MediaLearningQuiz;

document.delegateEventListener('click', 'a[data-action="learning-quiz-remove"]', function(e) {

	e.preventDefault();

	MediaLearningQuiz.fill(this.firstParent('.media-image-upload[data-type="learning-quiz"]'), null, null);

});
