/**
 * Le film de la saison, depuis le mode photo : après un décompte, la Lecture de janvier à décembre réglée pour durer une minute,
 * filmée en temps réel dans le cadre choisi, avec la frise des mois en bas de l'image. La frise se dessine sur un canevas posé
 * sur la scène, que le film recopie : l'écran montre pendant le film la frise du fichier. Tout se fait dans le navigateur, sans
 * conversion : MP4 quand il sait l'écrire, WebM sinon.
 *
 * Seule la saison jouée jusqu'au bout se télécharge. Arrêter, Échap, la sortie du mode photo ou du plein écran, un geste qui arrête
 * la Lecture, la scène qui change de taille ou l'onglet passé en arrière-plan jettent le film : un onglet caché ralentit le
 * battement de la Lecture, et le film en sortirait plus long et saccadé. Ces deux derniers arrêts le disent par un message, parce
 * que l'utilisateur n'a pas fait le geste et ne saurait pas pourquoi aucun film n'arrive ; les autres restent muets. Le message
 * est le bandeau d'erreur de la page, qui se peint sous la scène en plein écran : il y monte le temps du plein écran.
 */
class MapSceneRecord {

	static FORMATS = [
		{ type: 'video/mp4;codecs=avc1', extension: 'mp4' },
		{ type: 'video/webm', extension: 'webm' }
	];

	static FRAMES = 30;

	static COUNTDOWN = 5;

	static DURATION = 60000;

	// La taille des mois suit la hauteur de l'image, sans passer sous le plancher de ceux de la barre d'outils (`least`)
	static TEXT = .026;

	// L'ombre de `util-card` (`--shadow-v2`, .06) ne se voit pas sur l'herbe : la carte et le curseur la portent à .12, écart
	// relevé par la passe ui de la planche T25c
	static SHADOW = 'rgba(0, 0, 0, .12)';

	/**
	 * `source` est le canevas du rendu, `speed` la vitesse de la Lecture (`SceneSpeed`), `months` les mois de la frise de l'écran
	 * (`MapScene.bindTimeline()`). `rewind()` montre le premier jour de la saison, `play()` lance la Lecture depuis le jour montré,
	 * `stop()` l'arrête, et `at()` dit la part de la saison jouée et le mois en cours.
	 */
	constructor(container, source, speed, months, name, { rewind, play, stop, at }) {

		this.container = container;
		this.source = source;
		this.speed = speed;
		this.months = months;
		this.name = name;
		this.rewind = rewind;
		this.play = play;
		this.stop = stop;
		this.at = at;

		this.active = false;
		this.exited = false;
		this.timer = null;
		this.recorder = null;
		this.film = document.createElement('canvas');
		this.filmContext = this.film.getContext('2d');

		this.overlay = document.createElement('canvas');
		this.overlay.className = 'map-scene-record-months hide';
		this.overlayContext = this.overlay.getContext('2d');
		source.after(this.overlay);

		this.format = ('MediaRecorder' in window) ? (MapSceneRecord.FORMATS.find(format => MediaRecorder.isTypeSupported(format.type)) ?? null) : null;
		this.startedAt = null;
		this.second = null;
		this.style = null;

		this.button = container.qs('[data-scene-record]');
		this.cancelButton = container.qs('[data-scene-record-stop]');
		this.progress = container.qs('[data-scene-record-progress]');
		this.countdown = container.qs('[data-scene-record-countdown]');
		this.idle = [container.qs('[data-scene-photo-save]'), this.button, container.qs('[data-scene-photo-close]')];

		// Un navigateur qui ne sait filmer un canevas dans aucun des deux formats ne montre pas le bouton
		this.button.classList.toggle('hide', this.format === null);
		this.button.addEventListener('click', () => this.start());
		this.cancelButton.addEventListener('click', () => this.cancel());

		// Un film jeté par la sortie du plein écran que l'onglet caché a provoquée est déjà arrêté : seul le message reste à dire
		this.onHidden = () => {

			if(document.hidden === false) {
				return;
			}

			if(this.exited) {
				this.exited = false;
				this.warn(this.button.dataset.hiddenError);
			} else {
				this.interrupt(this.button.dataset.hiddenError);
			}

		};

		document.addEventListener('visibilitychange', this.onHidden);

		// Sortir du plein écran est un geste, Échap compris : le film s'arrête sans message. Le navigateur envoie `fullscreenchange`
		// avant les images de la boucle de rendu, et le canevas ne change de taille qu'ensuite, par le `ResizeObserver` de `MapScene` :
		// `frame()` ne voit donc jamais cette taille-là. Le conteneur survit à la scène que le relief remonte : dispose() retire l'écouteur
		this.onFullscreen = () => {

			// Le bandeau revient où la page le pose, pour qu'une erreur levée ensuite hors de la scène ne s'écrive pas dedans
			const banner = MapSceneRecord.alert();

			if(document.fullscreenElement !== this.container && banner !== null) {
				document.body.append(banner);
			}

			if(this.active === false) {
				return;
			}

			this.cancel();

			// Un onglet passé en arrière-plan fait aussi sortir du plein écran, et le navigateur peut l'annoncer avant
			// `visibilitychange` : la marque dit alors à `onHidden` que l'arrêt n'était pas un geste. Elle tombe à la deuxième image,
			// qu'un onglet caché ne joue pas : `fullscreenchange` part avant les rappels d'animation de son image, si bien qu'un rappel
			// demandé ici tourne dans cette même image, l'onglet encore visible
			this.exited = true;
			requestAnimationFrame(() => requestAnimationFrame(() => {
				this.exited = false;
			}));

		};

		this.container.addEventListener('fullscreenchange', this.onFullscreen);

	}

	/**
	 * Le décompte, qui annonce la durée du film. Le film est déjà en cours pour tout ce qui l'annule : Arrêter, Échap ou la sortie
	 * du mode photo pendant le décompte ne téléchargent rien.
	 */
	start() {

		this.active = true;

		// Le message d'un film arrêté malgré l'utilisateur ne vaut plus pour celui qui commence
		Lime.Alert.hideStaticErrors();

		// La Lecture compte un pas de `PLAY_DAY / factor` par jour de la saison : le facteur se règle pour qu'elle tienne dans la durée
		// du film, quelle que soit la vitesse choisie à l'écran, qui revient après lui. Le pas est une milliseconde entière
		// (`MapScene` arrondit celui de son battement) : arrondi au-dessus, il ne finit pas le film avant sa durée
		const day = Math.ceil(MapSceneRecord.DURATION / ((this.months.span + 1) * 7));
		this.speed.set(MapScene.PLAY_DAY / day);

		this.countdown.qs('[data-scene-record-seconds]').textContent = MapSceneRecord.DURATION / 1000;

		this.container.classList.add('map-scene-record-on');
		MapScene.swap(this.idle, [this.cancelButton, this.countdown]);

		this.count(MapSceneRecord.COUNTDOWN);

	}

	count(left) {

		if(left === 0) {
			this.record();
			return;
		}

		this.countdown.qs('[data-scene-record-count]').textContent = left;
		this.timer = setTimeout(() => this.count(left - 1), 1000);

	}

	record() {

		this.timer = null;

		this.film.width = this.overlay.width = this.source.width;
		this.film.height = this.overlay.height = this.source.height;

		// La frise du film prend la police et les couleurs de celle de la barre d'outils, que le mode photo cache sans retirer leur
		// style. Le plancher de ses mois, .9rem comme les leurs, se compte en pixels du tampon du rendu
		const scene = getComputedStyle(this.container);

		this.style = {
			font: getComputedStyle(this.container.qs('[data-scene-months]')).fontFamily,
			surface: scene.getPropertyValue('--background-surface'),
			border: scene.getPropertyValue('--border'),
			production: scene.getPropertyValue('--production'),
			text: scene.getPropertyValue('--text'),
			least: .9 * parseFloat(getComputedStyle(document.documentElement).fontSize) * this.source.width / this.source.clientWidth
		};

		// Un enregistreur arrêté rend ses dernières données après coup : chacun garde les siennes, qu'un film annulé emporte avec lui
		const recording = { media: new MediaRecorder(this.film.captureStream(MapSceneRecord.FRAMES), { mimeType: this.format.type }), chunks: [] };
		recording.media.addEventListener('dataavailable', event => recording.chunks.push(event.data));

		// L'enregistreur ne part qu'à sa première image encodée, une fraction de seconde après son appel : la Lecture partie avec
		// l'appel se jouerait en partie hors du fichier, plus court d'autant. Le premier jour s'y filme en attendant, et un film
		// annulé dans l'intervalle ne part pas
		recording.media.addEventListener('start', () => {

			if(this.recorder === recording) {
				this.startedAt = performance.now();
				this.second = null;
				this.play();
			}

		}, { once: true });

		this.rewind();
		recording.media.start();

		this.recorder = recording;
		this.second = null;

		MapScene.swap([this.countdown], [this.progress, this.overlay]);

	}

	/**
	 * La saison est jouée jusqu'au dernier jour : le film se télécharge. L'enregistrement se quitte avant tout le reste, la Lecture
	 * s'arrêtant ensuite par `MapScene.stopPlaying()`, qui annule un film encore en cours.
	 */
	end() {

		if(this.active === false) {
			return;
		}

		this.active = false;

		const { chunks } = this.recorder;

		this.recorder.media.addEventListener('stop', () => {

			const link = document.createElement('a');
			link.href = URL.createObjectURL(new Blob(chunks, { type: this.format.type }));
			link.download = this.name +'.'+ this.format.extension;
			link.click();

			setTimeout(() => URL.revokeObjectURL(link.href));

		});

		this.finish();

	}

	/**
	 * Le film s'arrête sans rien télécharger. Sortir de l'enregistrement d'abord : l'arrêt de la Lecture qui suit revient ici.
	 */
	cancel() {

		if(this.active === false) {
			return;
		}

		this.active = false;

		this.finish();
		this.stop();

	}

	/**
	 * Le film s'arrête sans que l'utilisateur l'ait demandé, et le message dit pourquoi. `onHidden` écoute en permanence : sans
	 * film en cours, un changement d'onglet ne dit rien.
	 */
	interrupt(message) {

		if(this.active === false) {
			return;
		}

		this.cancel();
		this.warn(message);

	}

	/**
	 * Le bandeau d'erreur de la page, monté dans la scène en plein écran : posé sur `body`, il se peindrait sous elle. `fixed`, il
	 * garde sa place à l'écran. La sortie du plein écran le redescend (`onFullscreen`).
	 */
	warn(message) {

		Lime.Alert.showStaticError(message);

		// Un message non vide crée toujours le bandeau
		if(document.fullscreenElement === this.container) {
			this.container.append(MapSceneRecord.alert());
		}

	}

	/**
	 * Le nœud que `Lime.Alert.showStaticError()` crée sur `body` et retrouve par son identifiant. Sa croix le retire du document.
	 */
	static alert() {
		return document.getElementById('alert-danger');
	}

	/**
	 * La seule sortie, du décompte comme du film.
	 */
	finish() {

		clearTimeout(this.timer);
		this.timer = null;

		if(this.recorder !== null) {
			this.recorder.media.stop();
			this.recorder.media.stream.getTracks().forEach(track => track.stop());
			this.recorder = null;
		}

		this.startedAt = null;

		this.speed.restore();

		this.container.classList.remove('map-scene-record-on');
		MapScene.swap([this.progress, this.cancelButton, this.countdown, this.overlay], this.idle);

	}

	/**
	 * Appelé juste après le rendu de chaque image, dans la même tâche : le canevas du rendu efface son image dès qu'elle est
	 * affichée.
	 */
	frame() {

		// Pendant le décompte, le film est commencé sans enregistreur : rien ne se filme encore
		if(this.active === false || this.recorder === null) {
			return;
		}

		const { width, height } = this.film;

		// Le film garde le cadre de son départ : une scène qui change de taille (téléphone tourné, fenêtre redimensionnée) l'arrête
		if(this.source.width !== width || this.source.height !== height) {
			this.interrupt(this.button.dataset.resizedError);
			return;
		}

		this.overlayContext.clearRect(0, 0, width, height);
		this.drawMonths(width, height, this.at());

		this.filmContext.drawImage(this.source, 0, 0, width, height);
		this.filmContext.drawImage(this.overlay, 0, 0, width, height);

		const elapsed = (this.startedAt === null) ? 0 : Math.min(MapSceneRecord.DURATION, performance.now() - this.startedAt);
		const second = Math.floor(elapsed / 1000);

		if(second !== this.second) {

			this.second = second;

			this.progress.qs('.util-progress-part').style.width = (elapsed / MapSceneRecord.DURATION * 100) +'%';
			this.progress.qs('span').textContent = MapSceneRecord.clock(second) +' / '+ MapSceneRecord.clock(MapSceneRecord.DURATION / 1000);

		}

	}

	/**
	 * La frise de la barre d'outils dans sa carte, en bas de l'image, sur le canevas posé sur la scène : la piste, le trait et le
	 * curseur jusqu'au jour joué, chaque mois sous la marque de son premier jour et le mois en cours en gras. Ses mesures sont
	 * celles de la barre d'outils (scene.css), comptées dans le `rem` de la frise du film, où les mois font .9rem.
	 */
	drawMonths(width, height, { position, month }) {

		const context = this.overlayContext;
		const text = Math.max(height * MapSceneRecord.TEXT, this.style.least);
		const rem = text / .9;
		const font = text +'px '+ this.style.font;
		const bold = 'bold '+ font;

		const thumb = 1.75 * rem;
		const track = .5 * rem;
		const padding = .75 * rem;
		const card = { left: rem, width: width - 2 * rem, height: 2 * padding + thumb + rem };
		card.top = height - rem - card.height;

		const left = card.left + rem + thumb / 2;
		const span = card.width - 2 * rem - thumb;
		const at = week => left + (week - this.months.min) / this.months.span * span;
		const played = left + span * Math.min(1, Math.max(0, position));
		const middle = card.top + padding + thumb / 2;

		context.save();

		context.shadowColor = MapSceneRecord.SHADOW;
		context.shadowBlur = 1.25 * rem;
		context.shadowOffsetY = .25 * rem;
		context.fillStyle = this.style.surface;
		context.beginPath();
		context.roundRect(card.left, card.top, card.width, card.height, .75 * rem);
		context.fill();

		context.restore();

		// Le contour de 1 px de `util-card` est rapporté au `rem` de la frise, pas à la racine du document : pris sur la racine de base (`html { font-size: 13px }`, `reboot.css`), il grossit avec l'image comme le texte, au lieu de rester à 1 px sur un film de 1 800 px de large
		const line = rem / 13;

		context.strokeStyle = this.style.border;
		context.lineWidth = line;
		context.beginPath();
		context.roundRect(card.left + line / 2, card.top + line / 2, card.width - line, card.height - line, .75 * rem);
		context.stroke();

		context.fillStyle = this.style.border;
		context.beginPath();
		context.roundRect(left - thumb / 2, middle - track / 2, span + thumb, track, track / 2);
		context.fill();

		context.fillStyle = this.style.production;
		context.beginPath();
		context.roundRect(left - thumb / 2, middle - track / 2, played - left + thumb / 2, track, track / 2);
		context.fill();

		// Le curseur porte le contour de 3 px de celui de la barre d'outils
		context.save();

		context.shadowColor = MapSceneRecord.SHADOW;
		context.shadowBlur = 1.25 * rem;
		context.shadowOffsetY = .25 * rem;
		context.fillStyle = this.style.surface;
		context.beginPath();
		context.arc(played, middle, thumb / 2, 0, 2 * Math.PI);
		context.fill();

		context.restore();

		context.fillStyle = this.style.production;
		context.beginPath();
		context.arc(played, middle, thumb / 2 - 3 * line, 0, 2 * Math.PI);
		context.fill();

		const shown = this.shownMonths(at, font, bold, month, rem);
		const below = middle + track / 2 + .2 * rem;

		context.fillStyle = this.style.text;
		context.textAlign = 'center';
		context.textBaseline = 'alphabetic';

		shown.forEach(index => {

			const x = at(this.months.list[index].week);

			context.fillRect(Math.round(x), below, line, 5 * line);

			context.font = (index === month) ? bold : font;
			context.fillText(this.months.list[index].label, x, card.top + padding + thumb + .9 * rem);

		});

	}

	/**
	 * Les mois qui tiennent sous la frise, comme sous celle de la barre d'outils : les douze, sinon un sur deux. Le mois en cours
	 * s'écrit toujours, et ses voisins qui le toucheraient s'effacent.
	 */
	shownMonths(at, font, bold, month, gap) {

		const context = this.overlayContext;
		const indexes = this.months.list.map((shown, index) => index);

		const box = index => {
			context.font = (index === month) ? bold : font;
			const half = context.measureText(this.months.list[index].label).width / 2;
			const x = at(this.months.list[index].week);
			return [x - half, x + half];
		};

		const fits = indexes.every(index => index === 0 || box(index - 1)[1] + gap <= box(index)[0]);
		const kept = fits ? indexes : indexes.filter(index => index % 2 === 0 || index === month);
		const current = box(month);

		return kept.filter(index => index === month || box(index)[1] + gap <= current[0] || box(index)[0] >= current[1] + gap);

	}

	static clock(seconds) {
		return Math.floor(seconds / 60) +':'+ String(seconds % 60).padStart(2, '0');
	}

	/**
	 * La scène se ferme : le film en cours se jette sans arrêter une Lecture qui disparait avec elle.
	 */
	dispose() {

		if(this.active) {
			this.active = false;
			this.finish();
		}

		document.removeEventListener('visibilitychange', this.onHidden);
		this.container.removeEventListener('fullscreenchange', this.onFullscreen);
		this.overlay.remove();

	}

}
