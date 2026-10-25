/**
 * Ce que la lecture d'un bloc demande au navigateur, et rien de l'éditeur (`block.js`) : ce fichier
 * n'est chargé que par le rendu des types qui en ont besoin, la vidéo
 * (`BlockTypeUi::getVideoFrame()`), en lecture comme dans l'éditeur, l'instant d'une étape
 * (`BlockTypeUi::getStepAt()`) et les réponses qui ont une meilleure (`BlockTypeUi::renderAnswers()`),
 * en lecture seulement.
 *
 * Une vidéo ne demande rien à son hébergeur tant qu'on ne l'a pas lancée : le cadre ne porte qu'un
 * lien, et l'adresse du lecteur attend dans `data-block-video`, composée par le serveur depuis la
 * liste des hébergeurs, avec ce qui le fait démarrer. Le clic pose le lecteur à la place du lien et
 * du nom de l'hébergeur, et retient le navigateur de suivre le lien, qui mène au même lecteur
 * sans démarrage et ne sert que sans script. Avec Ctrl ou Cmd, le lien garde son geste et s'ouvre dans
 * un autre onglet.
 *
 * Un hébergeur refuse un lecteur dont il ne connait pas l'origine, et la politique de référent du
 * document peut être plus stricte que ce qu'il attend : le lien (`BlockTypeUi::getVideoFrame()`) et
 * le lecteur posé ici déclarent donc chacun la leur, qui n'annonce que l'origine de la page.
 *
 * Seul un enfant direct d'un cadre de vidéo est écouté : le sélecteur lit une parenté, que le rendu
 * du type est seul à écrire (`BlockTypeUi::getVideoFrame()`), et non un attribut, qu'il suffit de
 * savoir nommer. Le script n'a ainsi pas à connaitre ce que chaque texte de la page laisse passer
 * (`BlockTypeTest::testVideo()` fige que celui d'un bloc ne produit pas cette parenté). L'adresse
 * lue reste jugée ici : une adresse qui n'est pas en `https:` n'est jamais posée, et le lien garde
 * alors son geste.
 *
 * Le clic s'écoute sur le document : rien n'est à armer à l'arrivée d'un écran, et une leçon atteinte
 * en navigation ajax est servie par le même écouteur.
 *
 * Un lecteur posé s'annonce par l'événement `blockVideoPlay`, émis sur son cadre et qui remonte au
 * document. Le moteur de blocs ne sait pas qui l'écoute : ce qui se passe dans un lecteur d'une autre
 * origine n'atteint pas la page, et c'est le seul moment où elle peut apprendre qu'une vidéo a été
 * lancée.
 */
class BlockVideo {

	static play(poster) {
		return BlockVideo.load(poster.parentElement, poster.dataset.blockVideo);
	}

	/**
	 * L'instant d'une étape lance la vidéo de son objet à ce moment-là, qu'elle attende son premier clic
	 * ou qu'elle soit déjà lancée : le lecteur est reposé sur l'adresse du départ. La vidéo est celle du
	 * plus proche conteneur de l'étape qui en porte une, en remontant la page : la liste des blocs d'un
	 * objet, ou ce qui l'entoure quand l'usage pose la vidéo à part. L'objet n'en a qu'une
	 * (`Block::url.video`). Le cadre se désigne par le rendu, jamais par ce qu'écrit une étape, qui
	 * ne donne que des secondes, relues ici en entier.
	 */
	static jump(button) {

		const at = parseInt(button.dataset.blockAt, 10);

		if(Number.isInteger(at) === false || at < 0) {
			return false;
		}

		let frame = null;

		for(let node = button.parentElement; node !== null && frame === null; node = node.parentElement) {
			frame = node.querySelector('.block-video-frame[data-block-video-start]');
		}

		if(frame === null) {
			return false;
		}

		if(BlockVideo.load(frame, frame.dataset.blockVideoStart.replace('{s}', String(at))) === false) {
			return false;
		}

		// L'étape se lit souvent loin de la vidéo, posée en tête de l'objet ou à part par l'usage : lancée
		// hors de la fenêtre, elle s'entendrait sans se voir. L'usage règle l'écart sous sa barre collée
		// par le `scroll-margin-top` du cadre.
		frame.scrollIntoView({block: 'nearest'});

		return true;

	}

	static load(frame, href) {

		let url;

		try {
			url = new URL(href);
		} catch(e) {
			return false;
		}

		if(url.protocol !== 'https:') {
			return false;
		}

		const iframe = document.createElement('iframe');

		iframe.src = url.href;
		// Un navigateur bloque le démarrage d'une vidéo dans un cadre d'une autre origine tant que la
		// page ne le lui permet pas : le clic qui charge le lecteur ne suffirait pas à le lancer. La
		// permission vaut pour tout lecteur, puisque l'adresse d'un bloc porte toujours le paramètre de
		// démarrage de son hébergeur (`BlockSetting::getVideo()`).
		iframe.allow = 'autoplay; fullscreen; picture-in-picture';
		iframe.referrerPolicy = 'strict-origin-when-cross-origin';

		frame.replaceChildren(iframe);
		frame.dispatchEvent(new CustomEvent('blockVideoPlay', {bubbles: true}));

		return true;

	}

}

document.addEventListener('click', e => {

	const poster = (e.target instanceof Element) ? e.target.closest('.block-video-frame > [data-block-video]') : null;

	if(
		poster === null ||
		e.ctrlKey ||
		e.metaKey
	) {
		return;
	}

	if(BlockVideo.play(poster)) {
		e.preventDefault();
	}

});

/**
 * L'instant n'est écouté que sous la parenté que son rendu est seul à écrire (`BlockTypeUi::getStepAt()`) :
 * une étape, son conteneur d'instant, puis le bouton.
 */
document.addEventListener('click', e => {

	const button = (e.target instanceof Element) ? e.target.closest('.util-procedure-step > .block-step-at > [data-block-at]') : null;

	if(button === null) {
		return;
	}

	BlockVideo.jump(button);

});

/**
 * La meilleure réponse se révèle au clic sur « Voir la meilleure réponse » : chaque réponse marquée
 * de la grille prend la forme révélée que porte son `<template>`, et le bouton part avec son
 * enveloppe. Comme pour la vidéo, le clic s'écoute sur le document et le sélecteur lit une parenté que
 * le rendu du type est seul à écrire (`BlockTypeUi::renderAnswers()`) : la grille, puis l'enveloppe du
 * bouton.
 */
class BlockAnswers {

	static reveal(button) {

		const wrapper = button.parentElement;

		wrapper.previousElementSibling.qsa(':scope > [data-block-answer-best]', answer => {
			answer.replaceWith(answer.qs(':scope > template').content.cloneNode(true));
		});

		wrapper.remove();

	}

}

document.addEventListener('click', e => {

	const button = (e.target instanceof Element) ? e.target.closest('.block-answers + div > [data-block-answers-reveal]') : null;

	if(button === null) {
		return;
	}

	BlockAnswers.reveal(button);

});
