/**
 * L'éditeur d'avatar : les choix du catalogue fermé, que le serveur rend en boutons radio, montent l'avatar dans
 * l'aperçu, et l'enregistrement envoie avec la composition l'image de l'avatar, qui devient la photo de profil.
 * Chaque rangée de teintes finit par une couleur libre, réglée par sa teinte et sa clarté, sauf celle d'une palette
 * partagée, le métal des bijoux, et la peau se choisit sur une rampe qui s'arrête sur les teintes du catalogue.
 *
 * L'aperçu vit : au repos, il respire et cligne des yeux, et entre deux moments de repos il prend une émotion du
 * modèle, pose et visage, ou de temps en temps une posture. Il revient de face, immobile, un instant après chaque
 * choix, pour que la pièce choisie se voie. Il ne tourne jamais de lui-même : on choisit sur une silhouette qui
 * reste où on l'a laissée. Sans mouvement demandé par le système, il reste immobile, et l'image enregistrée est
 * toujours celle du personnage immobile.
 */
class UserAvatarEditor {

	// Une seule à la fois : un panneau rouvert en crée une nouvelle, et le navigateur limite les contextes WebGL d'une page
	static current = null;

	// Le dégradé du matériau toon des scènes, en cinq paliers lissés
	static RAMP = [120, 160, 200, 235, 255];

	// La part de la hauteur visée, la distance et la hausse de la caméra : en pied pour l'aperçu, la tête et les épaules pour
	// l'image, qui s'affiche dans un cercle. L'aperçu garde du champ autour du personnage debout, parce que ses émotions et
	// ses postures avancent les pieds vers la caméra et que la marche le soulève
	static FRAMES = {
		body: [.5, 4.1, .35],
		face: [.78, 2.2, .05],
	};

	static IMAGE_SIZE = 512;

	// Les postures qui tiennent sur place : celles du modèle, et la marche de figure.js, qui pose un pas à chaque instant
	static POSTURES = ['pose:recolte', 'pose:lance', 'walk'];

	// Les familles qu'un tirage ne remplit qu'une fois sur RARE_SHARE : la rousseur et chaque place d'un bijou. Tirées comme
	// les autres, elles couvriraient presque chaque avatar tiré de bijoux et de taches
	static RARE = ['taches', 'oreilles', 'cou', 'poignet'];
	static RARE_SHARE = .25;

	// Les familles qu'un tirage ne pose jamais : la pilosité, qui genrerait l'avatar à la place de la personne (Vincent,
	// 2026-10-20). Comme la peau, le tirage garde celle que la personne a choisie, et les familles tirées s'y plient par
	// leurs exclusions
	static UNDRAWN = ['barbe'];

	// La part des moments qui prennent une posture plutôt qu'une émotion
	static POSTURE_SHARE = .25;

	// En secondes, à 15 % près : un moment de repos, puis un moment d'émotion ou de posture, qui revient toutes les 3 à 4 s.
	// Puis le passage de l'un à l'autre, la pose immobile de face qui suit un choix, et le clignement, qui revient
	// toutes les 3 à 6 s
	static REST = 1.2;
	static GESTURE = 2.2;
	static BLEND = .6;
	static STILL = 1.8;
	static BLINK = .15;
	static BLINK_EVERY = [3, 6];

	// Le pas d'une marche sur place, en radians de phase par seconde
	static STEP = 6;

	// Une couleur libre se règle par sa teinte et sa clarté : sa saturation est tenue, celle où un habit garde une
	// couleur franche sans sortir du ton des palettes
	static SATURATION = .5;

	// La luminance sous laquelle la pipette d'une couleur libre passe à l'encre claire : celle où le blanc et l'encre du
	// texte (`--text`) contrastent autant l'un que l'autre
	static DARK = .22;

	// Rien n'empêche le cadriciel de jouer deux fois le onrender d'un même pied : l'éditeur déjà monté sur ce formulaire
	// est alors gardé, faute de quoi le formulaire aurait deux aperçus et deux tirages pour chaque geste
	static start(form) {

		const current = UserAvatarEditor.current;

		if(
			current !== null &&
			current.form === form &&
			current.container === form.qs('[data-scene-directory]')
		) {
			return;
		}

		current?.dispose();
		UserAvatarEditor.current = new UserAvatarEditor(form);

	}

	constructor(form) {

		this.form = form;
		this.catalog = JSON.parse(form.dataset.catalog);
		// Une palette partagée (le métal des bijoux) a une seule rangée, nommée comme elle, pour toutes ses familles
		this.shared = this.catalog.sharedPalettes;
		// Le serveur ne rend que les familles qu'une composition porte : l'accessoire s'enregistre à part
		this.families = this.catalog.families.filter(({ key }) => form.qs('input[name="'+ key +'"]') !== null);
		this.container = form.qs('[data-scene-directory]');
		this.submit = form.qs('[type="submit"]');
		this.skin = form.qs('input[name="peau"]');

		this.loaded = null;
		this.renderer = null;
		this.figure = null;
		this.angle = .55;

		// Le modèle 3D téléversé en place : son index vient avec la page, son binaire se lit à part (`buffer`). Le modèle
		// vérifié par « Valider » (`previewed()`) passe devant lui tant qu'on ne l'a pas oublié. `shown` est celui que
		// l'aperçu monte, null pour la composition
		this.preview = form.qs('.util-composer-preview');

		this.upload = (this.preview.dataset.uploadUrl === undefined) ? null : {
			url: this.preview.dataset.uploadUrl,
			index: JSON.parse(this.preview.dataset.uploadIndex),
			buffer: null
		};
		this.verified = null;
		this.shown = null;

		this.uploadDrop = null;
		this.watchDrop();

		this.motion = (window.matchMedia('(prefers-reduced-motion: reduce)').matches === false);
		this.frameId = null;
		this.model = null;
		this.gesture = 'still';
		this.since = 0;
		this.next = 0;
		this.from = null;
		this.emotion = null;
		this.face = null;
		this.faces = new Map();
		this.blinkAt = 0;
		this.facing = this.angle;
		this.time = performance.now() / 1000;
		this.changeId = null;
		this.revealing = false;
		this.arrowed = false;

		// Les rangées de teintes qu'on a choisies soi-même, celles d'un avatar enregistré comprises : une teinte qui n'est
		// pas celle d'où part son entrée a été choisie. Une zone enregistrée vide garde la teinte de l'entrée quittée, qui
		// n'a pas été choisie non plus, et la peau a pu changer depuis : elle se compare à celles d'où partent toutes les
		// entrées de sa famille, sur toutes les peaux
		const composition = this.getComposition();

		this.touched = new Set(
			this.families
				.filter(family => this.getTintKey(family) === family.key +'-teinte')
				.filter(family => {
					const tint = composition[family.key +'-teinte'], entry = composition[family.key];
					const tints = (entry === null) ? [null, ...family.entries].flatMap(entry => this.getDefaultTints(family, entry)) : [this.getDefaultTint(family, entry)];
					return tints.includes(tint) === false;
				})
				.map(({ key }) => key +'-teinte')
		);

		this.disposed = false;

		// Le formulaire peut survivre à l'éditeur, quand le panneau repeint son corps dans la même enveloppe : ses
		// écouteurs partent avec lui
		this.listeners = [
			[document, 'visibilitychange', () => this.request()],
			// Un curseur se suit pendant qu'on le glisse, par son `input` : son `change` n'arrive qu'au relâchement
			// Le dépôt du modèle ne compose rien : il a ses propres écouteurs
			[form, 'change', event => {
				if(event.target.type !== 'range' && event.target.closest('[data-scene-upload]') === null) {
					this.closeTunes();
					this.follow(event.target.name);
					this.change();
				}
			}],
			// Une flèche du clavier qui arrive sur une couleur libre ouvre son menu comme un clic, le navigateur cochant
			// un radio par un clic. Le clavier reste alors dans la rangée, que la flèche suivante continue de parcourir
			[form, 'keydown', event => {

				this.arrowed = event.key.startsWith('Arrow');

				// Entrée sur un choix ou un curseur enverrait le formulaire, le navigateur le faisant de tout champ : dans
				// une rangée où la même touche ouvre le menu d'une couleur libre, l'avatar s'enregistrerait par mégarde.
				// L'onglet du modèle n'enregistre pas de composition : ses gestes sont ses boutons
				if(
					event.key === 'Enter' &&
					(event.target.matches('input[type="radio"], input[type="range"]') || event.target.closest('.tab-panel[data-tab="upload"]') !== null)
				) {
					event.preventDefault();
				}

			}],
			// Le contenu de l'onglet du modèle se repeint en partie (`avatarUpload:doPhoto`) : ses boutons s'écoutent sur le
			// formulaire. Un `data-confirm` refusé arrête le clic avant lui (`lime.js`)
			[form, 'click', event => this.clickUpload(event)],
			[form, 'keyup', () => this.arrowed = false],
			// Un relâchement peut ne jamais arriver ici, la fenêtre quittée flèche enfoncée : le pointeur qui revient
			// n'est pas une flèche
			[form, 'pointerdown', () => this.arrowed = false],
			[this.skin, 'input', () => {
				this.followSkin();
				this.requestChange();
			}],
			[form.qs('[data-avatar-random]'), 'click', () => this.random()],
			[form, 'submit', event => {
				event.preventDefault();
				this.save();
			}],
			[form.closest('.panel'), 'panelClose', () => this.dispose()],
			// Un menu ouvert est placé une fois, dans `body` : il ne suit pas sa pastille quand l'éditeur défile, et se
			// retrouverait sous celle d'une autre zone. `scroll` ne remonte pas, d'où la capture
			[document, 'scroll', event => {
				if(this.revealing === false && (event.target instanceof Element === false || event.target.closest('.dropdown-list') === null)) {
					this.closeTunes();
				}
			}, true],
		];

		// Le menu d'une couleur libre quitte le formulaire pour `body` le temps d'être ouvert (`Lime.Dropdown.open()`) :
		// ce qui s'y passe s'écoute sur lui
		form.qsa('[data-avatar-free]').forEach(free => {

			const tune = free.nextElementSibling;

			// L'encre de la pipette d'une couleur libre enregistrée se décide ici comme pendant le réglage
			const radio = free.qs('input');
			free.classList.toggle('user-avatar-free-dark', radio.hidden === false && UserAvatarEditor.isDark(radio.value));

			this.listeners.push(
				// Le clavier qui quitte la rangée emporte la flèche en cours, dont le relâchement ne viendra plus ici, et
				// referme le menu qu'elle a ouvert : il ne reste ouvert que si le clavier y entre
				[free.closest('[data-avatar-tints]'), 'focusout', event => {

					if(event.currentTarget.contains(event.relatedTarget) || tune.contains(event.relatedTarget)) {
						return;
					}

					this.arrowed = false;

					if(Lime.Dropdown.isOpen(free)) {
						Lime.Dropdown.close(free);
					}

				}],
				[free, 'dropdownBeforeShow', () => this.revealTune(free, tune)],
				[free, 'dropdownAfterShow', () => this.openTune(free, tune)],
				[free, 'keydown', event => {
					if(event.key === 'Enter' || event.key === ' ') {
						event.preventDefault();
						if(Lime.Dropdown.isOpen(free)) {
							tune.qs('[data-avatar-hue]').focus({ preventScroll: true });
						} else {
							free.click();
						}
					}
				}],
				[tune, 'input', () => this.tune(free, tune)],
				// Échap ferme le menu et lui seul : le panneau de l'éditeur se ferme au même `keyup`, écouté sur `document`
				[tune, 'keyup', event => {
					if(event.key === 'Escape') {
						event.stopPropagation();
						Lime.Dropdown.close(free);
						(free.qs('input:not([hidden])') ?? free).focus();
					}
				}],
				// Le menu ouvert vit au bout de `body`, après le pied de l'éditeur : Tab en sortirait vers le bouton qui
				// enregistre, ou hors de la page. Le clavier revient à la pastille, d'où Tab en avant poursuit de lui-même
				// vers la rangée suivante, le navigateur avançant depuis le champ qui a le clavier quand la touche finit
				[tune, 'keydown', event => {

					if(event.key !== 'Tab' || event.target !== tune.qs(event.shiftKey ? '[data-avatar-hue]' : '[data-avatar-lightness]')) {
						return;
					}

					if(event.shiftKey) {
						event.preventDefault();
					}

					Lime.Dropdown.close(free);
					(free.qs('input:not([hidden])') ?? free).focus();

				}]
			);

		});

		this.listeners.forEach(([target, type, listener, capture = false]) => target.addEventListener(type, listener, capture));

		this.submit.disabled = true;

		this.refreshFooter();

		// L'aperçu se colle sous l'en-tête du panneau, qui est lui-même collé et dont la hauteur suit son titre
		const header = form.closest('.panel')?.qs('.panel-header');
		form.style.setProperty('--util-composer-top', (header?.offsetHeight ?? 0) +'px');

		this.change();

		// Le binaire du modèle téléversé est servi par sa route, après contrôle des droits, et non par le stockage : il se lit
		// comme un fichier cuit, sans instruction ajax en retour. Un binaire illisible laisse l'éditeur monter la composition
		Promise.all([
			SceneLoader.loadAvatar(this.container),
			(this.upload === null) ? null : SceneLoader.read(this.upload.url, 'arrayBuffer').catch(() => null)
		]).then(([loaded, buffer]) => {

			// Un éditeur remplacé ou libéré pendant le chargement ne monte plus rien
			if(this.disposed) {
				return;
			}

			this.loaded = loaded;

			if(buffer === null) {
				this.upload = null;
			} else {
				this.upload.buffer = buffer;
			}

			this.shown = (this.getTab() === 'upload') ? this.upload : null;

			this.mount();
			this.place();

			this.submit.disabled = false;

			// Le serveur ne rend pas la 3D : la reprise d'une image d'avatar (`user/configure/avatarPngAi`) ouvre
			// l'éditeur dans un navigateur sans écran, qui lit ici l'image de la composition enregistrée, rendue comme à
			// l'enregistrement (`ouvretaferme/main/nodejs/image.js`)
			if(this.form.dataset.headless !== undefined) {
				this.capture(null, blob => {
					const reader = new FileReader();
					reader.addEventListener('load', () => {
						window.image = reader.result;
						window.imageReady = true;
					});
					reader.readAsDataURL(blob);
				});
			}

		});

	}

	getComposition() {

		const value = name => this.form.qs('input[name="'+ name +'"]:checked')?.value ?? '';

		const composition = { peau: this.catalog.palettes.skin[this.skin.value] };

		this.families.forEach(family => {

			composition[family.key] = (value(family.key) === '') ? null : value(family.key);

			const tintKey = this.getTintKey(family);

			if(tintKey !== null) {
				composition[tintKey] = value(tintKey);
			}

		});

		return composition;

	}

	// La clé de la teinte d'une famille, celle de UserAvatarLib::getTintKey()
	getTintKey({ key: family, palette }) {

		if(palette === null) {
			return null;
		}

		return this.shared.includes(palette) ? palette : family +'-teinte';

	}

	// La fiche du personnage du skill 3d-perso : la peau, puis un élément du catalogue par famille portée, avec sa couleur
	getFiche(composition) {

		const elements = this.families
			.filter(({ key: family }) => composition[family] !== null)
			.map(family => ({
				catalogue: composition[family.key],
				reglages: (family.palette === null) ? {} : { couleur: composition[this.getTintKey(family)] }
			}));

		return { corps: { peau: composition.peau }, elements };

	}

	// Les teintes d'où part une zone selon la peau, un cran après l'autre : le catalogue range par cran celles d'une entrée
	// qui ne se lit pas sur toute la rampe (une monture, trait fin posé sur la peau), ne donne une teinte qu'aux entrées qui
	// ont la leur, et aucune à une famille dont chaque entrée a la sienne
	getDefaultTints({ key: family, palette }, entry) {

		const { skinEntryTints, entryTints, tints } = this.catalog.defaults;

		return skinEntryTints[entry] ?? this.catalog.palettes.skin.map(() => entryTints[entry] ?? tints[family] ?? this.catalog.palettes[palette][0]);

	}

	// La teinte d'où part une zone sur la peau du curseur, celle de UserAvatarLib::getDefaultTint()
	getDefaultTint(family, entry) {
		return this.getDefaultTints(family, entry)[this.skin.value];
	}

	// Une entrée apporte sa teinte avec elle, tant qu'on n'a pas choisi soi-même celle de sa rangée : une monture arrive
	// sombre sur une peau claire et claire sur une peau sombre, et une monture qu'on a voulue verte le reste
	follow(name) {

		if(name.endsWith('-teinte')) {
			this.touched.add(name);
			return;
		}

		const family = this.families.find(family => family.key === name && this.getTintKey(family) === name +'-teinte');

		if(family !== undefined) {
			this.followEntry(family);
		}

	}

	// La peau qui change emporte les teintes qui se lisent à son cran, sous la même règle
	followSkin() {

		this.families
			.filter(family => this.getTintKey(family) === family.key +'-teinte')
			.forEach(family => this.followEntry(family));

	}

	followEntry(family) {

		const name = family.key;

		if(this.touched.has(name +'-teinte')) {
			return;
		}

		const entry = this.form.qs('input[name="'+ name +'"]:checked').value;

		// « Aucune » n'apporte rien : sa rangée est masquée, et garde la teinte de l'entrée qu'on quitte
		if(entry === '') {
			return;
		}

		this.form.qs('input[name="'+ name +'-teinte"][value="'+ this.getDefaultTint(family, entry) +'"]').checked = true;

	}

	// Un choix est exclu quand il compléterait un tuple du catalogue dont il fait partie, toutes ses entrées portées à la
	// fois. Le serveur pose la même règle sur la même liste (UserAvatarLib::isExcluded()). Un tuple déjà complet sans ce
	// choix ne le grise pas : un avatar enregistré avant qu'un tuple entre au catalogue doit pouvoir changer n'importe
	// laquelle de ses pièces
	isExcluded(composition, family, entry) {

		const entries = this.families.map(({ key }) => (key === family) ? entry : composition[key]);

		return this.catalog.excluded.some(tuple => tuple.includes(entry) && tuple.every(excluded => entries.includes(excluded)));

	}

	// Un choix exclu se grise
	disable(composition) {

		this.families.forEach(({ key: family }) => {
			this.form.qsa('input[name="'+ family +'"]').forEach(input => {
				input.disabled = this.isExcluded(composition, family, input.value === '' ? null : input.value);
			});
		});

	}

	// Les teintes d'une famille vide, ou d'une entrée qui n'a rien à colorer (`untinted`), sont sans effet : leur rangée se
	// retire, et le métal des bijoux tant qu'aucun bijou n'est porté. Le défilement ne bouge pas sous le choix qu'on vient
	// de faire : tant qu'une rangée manque, l'éditeur garde au moins la hauteur qui atteint le bas du panneau, sans quoi le
	// navigateur remonterait le panneau raccourci. Toutes revenues, il retrouve la sienne
	hidePalettes(composition) {

		const rows = [
			...this.families
				.filter(family => this.getTintKey(family) === family.key +'-teinte')
				.map(({ key: family }) => [this.form.qs('[data-avatar-tints="'+ family +'-teinte"]'), composition[family] === null || this.catalog.untinted.includes(composition[family])]),
			...this.shared.map(palette => [
				this.form.qs('[data-avatar-tints="'+ palette +'"]'),
				this.families.every(({ key: family, palette: paletteFamily }) => paletteFamily !== palette || composition[family] === null)
			])
		];

		const floor = rows.some(([, empty]) => empty) ? Math.ceil(this.form.closest('.panel').getBoundingClientRect().bottom - this.form.getBoundingClientRect().top) : null;

		rows.forEach(([row, empty]) => row.classList.toggle('hide', empty));

		this.form.style.minHeight = (floor === null) ? '' : floor +'px';

	}

	// La teinte, en degrés, et la clarté, de 0 à 1, d'une couleur écrite en hexadécimal
	static getHueLightness(color) {

		const [red, green, blue] = [1, 3, 5].map(index => parseInt(color.slice(index, index + 2), 16) / 255);
		const max = Math.max(red, green, blue);
		const min = Math.min(red, green, blue);
		const lightness = (max + min) / 2;

		if(max === min) {
			return [0, lightness];
		}

		const chroma = max - min;

		let sextant;

		if(max === red) {
			sextant = ((green - blue) / chroma + 6) % 6;
		} else if(max === green) {
			sextant = (blue - red) / chroma + 2;
		} else {
			sextant = (red - green) / chroma + 4;
		}

		return [sextant * 60, lightness];

	}

	// Une couleur est foncée quand l'encre claire y contraste plus que la sombre. La mesure est la luminance relative,
	// celle du calcul de contraste : la clarté du curseur ne distingue pas un jaune d'un bleu, qui ne portent pas la
	// même encre
	static isDark(color) {
		return UserAvatarEditor.getLuminance(color) < UserAvatarEditor.DARK;
	}

	static getLuminance(color) {

		const [red, green, blue] = [1, 3, 5]
			.map(index => parseInt(color.slice(index, index + 2), 16) / 255)
			.map(channel => (channel <= .04045) ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4);

		return .2126 * red + .7152 * green + .0722 * blue;

	}

	// Le rapport de contraste de deux couleurs, de 1 pour deux couleurs de même luminance à 21 du noir au blanc
	static getContrast(first, second) {

		const [dark, light] = [first, second].map(color => UserAvatarEditor.getLuminance(color)).sort((a, b) => a - b);

		return (light + .05) / (dark + .05);

	}

	// Les teintes qu'un tirage peut rendre à une entrée. Celle que le catalogue range par cran ne se lit pas sur toute la
	// rampe : il lui reste les teintes qui contrastent avec la peau du curseur au moins autant que la moins lisible de celles
	// d'où elle part, dont celle du cran fait toujours partie
	getRandomTints(palette, entry) {

		const tints = this.catalog.palettes[palette];
		const tintsSkin = this.catalog.defaults.skinEntryTints[entry];

		if(tintsSkin === undefined) {
			return tints;
		}

		const skins = this.catalog.palettes.skin;
		const floor = Math.min(...tintsSkin.map((tint, index) => UserAvatarEditor.getContrast(tint, skins[index])));

		return tints.filter(tint => UserAvatarEditor.getContrast(tint, skins[this.skin.value]) >= floor);

	}

	// La couleur d'une teinte et d'une clarté, à la saturation tenue, écrite comme celles des palettes
	static getColor(hue, lightness) {

		const amplitude = UserAvatarEditor.SATURATION * Math.min(lightness, 1 - lightness);

		const channel = offset => {
			const position = (offset + hue / 30) % 12;
			const value = lightness - amplitude * Math.max(-1, Math.min(position - 3, 9 - position, 1));
			return Math.round(value * 255).toString(16).padStart(2, '0');
		};

		return '#'+ channel(0) + channel(8) + channel(4);

	}

	// Le menu d'une couleur libre s'ouvre sur la couleur de la zone, sans rien choisir. Une pastille qui porte déjà une
	// couleur libre la reprend : la rouvrir, c'est y revenir
	openTune(free, tune) {

		const radio = free.qs('input');

		// Une flèche du clavier arrive ici par un clic sur le radio lui-même, que le cadriciel annule pour ouvrir le
		// menu : le navigateur rend alors au groupe son état d'avant, une fois le clic fini. La coche se pose après lui
		if(radio.hidden === false) {
			setTimeout(() => {
				if(this.disposed === false && radio.checked === false) {
					radio.checked = true;
					this.touched.add(radio.name);
					this.change();
				}
			});
		}

		const color = radio.hidden ? this.form.qs('input[name="'+ radio.name +'"]:checked').value : radio.value;
		const [hue, lightness] = UserAvatarEditor.getHueLightness(color);

		const hueField = tune.qs('[data-avatar-hue]');
		hueField.value = Math.round(hue);
		tune.qs('[data-avatar-lightness]').value = Math.round(lightness * 100);

		tune.style.setProperty('--user-avatar-hue', hueField.value);

		// Le clavier passe aux curseurs sans que la page défile vers un menu que le cadriciel n'a pas fini de placer,
		// sauf quand une flèche a ouvert le menu : elle parcourt la rangée, et la suivante doit y rester
		if(this.arrowed === false) {
			hueField.focus({ preventScroll: true });
		}

	}

	// Le cadriciel pose le menu sous sa pastille sans regarder la place qui reste : une pastille au pied de l'éditeur
	// remonte d'abord de ce qui manque, pour que les deux curseurs restent au-dessus du pied
	revealTune(free, tune) {

		if(Lime.Dropdown.isFullscreen(tune)) {
			return;
		}

		const panel = this.form.closest('.panel');
		const missing = free.getBoundingClientRect().bottom + tune.getBoundingClientRect().height + rem() - this.form.qs('.panel-footer').getBoundingClientRect().top;

		if(missing <= 0) {
			return;
		}

		// Ce défilement-ci ne ferme pas le menu qu'il prépare : son `scroll` part avant l'image suivante
		this.revealing = true;
		panel.scrollTop += missing;
		requestAnimationFrame(() => this.revealing = false);

	}

	closeTunes() {

		this.form.qsa('[data-avatar-free]').forEach(free => {
			if(Lime.Dropdown.isOpen(free)) {
				Lime.Dropdown.close(free);
			}
		});

	}

	// La pastille de la couleur libre prend la couleur des deux curseurs, et devient le choix de sa rangée
	tune(free, tune) {

		const hue = tune.qs('[data-avatar-hue]').value;
		const color = UserAvatarEditor.getColor(parseInt(hue), tune.qs('[data-avatar-lightness]').value / 100);

		tune.style.setProperty('--user-avatar-hue', hue);

		const radio = free.qs('input');
		radio.hidden = false;
		radio.value = color;
		radio.checked = true;

		this.touched.add(radio.name);

		free.classList.add('user-avatar-free-filled');
		free.removeAttribute('tabindex');
		free.qs('span').style.setProperty('--choice-color', color);
		free.classList.toggle('user-avatar-free-dark', UserAvatarEditor.isDark(color));

		this.requestChange();

	}

	change() {

		const composition = this.getComposition();

		this.disable(composition);
		this.hidePalettes(composition);
		this.place();

	}

	// Un curseur qu'on glisse change plus souvent que l'écran ne se peint, et chaque changement remonte le personnage :
	// un seul par image
	requestChange() {

		if(this.changeId !== null) {
			return;
		}

		this.changeId = requestAnimationFrame(() => {
			this.changeId = null;
			this.change();
		});

	}

	// La peau reste celle du curseur, et la pilosité celle choisie : le tirage change la tenue et les traits de l'avatar,
	// jamais la couleur ni le genre de la personne
	random() {

		const pick = list => list[Math.floor(Math.random() * list.length)];
		const check = (name, value) => this.form.qs('input[name="'+ name +'"][value="'+ (value ?? '') +'"]').checked = true;

		// Une famille tirée contre la tenue précédente perdait les choix que cette tenue excluait, alors que la tenue allait être
		// tirée à nouveau. Les familles facultatives repartent donc de rien, sauf celles que le tirage ne pose pas ; une famille
		// obligatoire n'a pas de choix vide
		this.families.filter(({ key, optional }) => optional && UserAvatarEditor.UNDRAWN.includes(key) === false).forEach(({ key }) => check(key, null));

		this.families.filter(({ key }) => UserAvatarEditor.UNDRAWN.includes(key) === false).forEach(familyCatalog => {

			const { key: family, entries, optional, palette } = familyCatalog;
			const composition = this.getComposition();

			const allowed = (optional ? [null, ...entries] : entries).filter(entry => this.isExcluded(composition, family, entry) === false);

			let entry;

			// Une famille rare est facultative : elle reste vide, sauf une fois sur RARE_SHARE où elle prend une de ses entrées
			if(UserAvatarEditor.RARE.includes(family)) {
				const worn = allowed.filter(entry => entry !== null);
				entry = (worn.length > 0 && Math.random() < UserAvatarEditor.RARE_SHARE) ? pick(worn) : null;
			} else {
				entry = pick(allowed);
			}

			check(family, entry);

			// Une zone restée vide, ou dont l'entrée n'a rien à colorer, ne tire pas de teinte : personne ne la verrait, et
			// elle passerait pour choisie quand une entrée arrive, qui n'apporterait plus la sienne. Une palette partagée se
			// tire une fois, après toutes les familles
			if(this.getTintKey(familyCatalog) === family +'-teinte' && entry !== null && this.catalog.untinted.includes(entry) === false) {
				check(family +'-teinte', pick(this.getRandomTints(palette, entry)));
				this.touched.add(family +'-teinte');
			}

		});

		this.shared.forEach(palette => check(palette, pick(this.catalog.palettes[palette])));

		this.change();

	}

	mount() {

		const { THREE } = this.loaded;

		const ramp = new THREE.DataTexture(new Uint8Array(UserAvatarEditor.RAMP), UserAvatarEditor.RAMP.length, 1, THREE.RedFormat);
		ramp.minFilter = ramp.magFilter = THREE.LinearFilter;
		ramp.needsUpdate = true;

		this.materials = new Map();
		this.toon = (color, painted) => {

			const key = painted ? 'painted' : color;

			if(this.materials.has(key) === false) {
				this.materials.set(key, new THREE.MeshToonMaterial({ color: painted ? '#fff' : color, vertexColors: painted, gradientMap: ramp, side: THREE.FrontSide }));
			}

			return this.materials.get(key);

		};

		this.scene = new THREE.Scene();
		this.scene.add(new THREE.HemisphereLight('#dcefff', '#93a06c', 1.4));

		const sun = new THREE.DirectionalLight('#fff1d6', 2);
		sun.position.set(-30, 50, 40);
		this.scene.add(sun);

		this.camera = new THREE.PerspectiveCamera(30, 1, .05, 50);

		this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
		this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

		const canvas = this.renderer.domElement;
		this.container.append(canvas);

		let dragged = null;

		canvas.addEventListener('pointerdown', event => {
			dragged = event.clientX;
			canvas.setPointerCapture(event.pointerId);
		});

		canvas.addEventListener('pointermove', event => {

			if(dragged === null) {
				return;
			}

			this.angle -= (event.clientX - dragged) / 90;
			this.facing = this.angle;
			dragged = event.clientX;

			this.render();

		});

		canvas.addEventListener('pointerup', () => dragged = null);

		this.observer = new ResizeObserver(() => this.render());
		this.observer.observe(this.container);

	}

	place() {

		if(this.loaded === null) {
			return;
		}

		const { avatar, figure } = this.loaded;

		if(this.figure !== null) {
			// Le visage de départ remis sur chaque trait, pour que la libération qui suit le trouve
			this.setFace(null);
			this.scene.remove(this.figure.root);
			this.figure.root.traverse(object => object.geometry?.dispose());
			this.faces.forEach(geometry => geometry.dispose());
			this.faces.clear();
		}

		this.model = (this.shown === null) ? avatar.build(this.getFiche(this.getComposition())) : this.loaded.uploaded(this.shown.index, this.shown.buffer);
		this.figure = figure.assemble(this.model, 'avatar', this.toon(null, true));
		this.scene.add(this.figure.root);

		// Un modèle téléversé n'a pas de paupières : il ne cligne pas
		this.figure.eyelids = ['paupiereG', 'paupiereD'].map(name => this.figure.bones[name]).filter(bone => bone !== undefined);

		// Le visage de chaque trait tel que le modèle le monte, qu'une émotion remplace le temps de son moment
		this.figure.traits = Object.fromEntries(
			this.model.parts
				.filter(({ name }) => this.model.parents[name] === 'tete' || this.model.parents[name]?.startsWith('paupiere'))
				.map(({ name }) => [name, this.figure.bones[name].children.find(child => child.isMesh)])
		);
		this.figure.neutral = Object.fromEntries(Object.entries(this.figure.traits).map(([role, mesh]) => [role, mesh.geometry]));

		// Un choix se montre de face sur le personnage immobile, d'où l'animation reprend sans passage : les os d'un
		// nouveau personnage ne sont pas ceux d'où partait le geste en cours
		this.still();
		this.face = null;
		this.gesture = 'still';
		this.since = this.time;
		this.next = this.time + UserAvatarEditor.STILL;
		this.from = null;

		this.render();
		this.request();

	}

	// Montre le modèle vérifié, à défaut celui en place, ou la composition, sans rien enregistrer. Le modèle téléversé n'a
	// ni émotion ni expression (`SceneLoader.loadAvatar()`) : l'éditeur ne lui fait jouer que ses postures (`choose()`)
	showUpload(shown) {

		this.shown = shown ? (this.verified ?? this.upload) : null;
		this.place();

	}

	// Le personnage tel que le modèle le monte, debout, les bras le long du corps, les yeux ouverts
	still() {

		const { root, bones, torso, rest } = this.figure;

		[root, ...Object.values(bones)].forEach(bone => bone.rotation.set(0, 0, 0));
		this.figure.eyelids.forEach(bone => bone.scale.set(1, 1, 1));
		torso.position.y = rest;
		root.position.y = 0;

	}

	// Les traits du visage d'un moment, par rôle, dans le repère du modèle ; ceux qu'il ne nomme pas reprennent leur forme
	// de départ. Une géométrie se peint à la couleur de son trait et se pose sur son os une fois, à son premier usage
	setFace(visage) {

		if(visage === this.face) {
			return;
		}

		this.face = visage;

		Object.entries(this.figure.traits).forEach(([role, mesh]) => {

			const geometry = visage?.[role];

			if(geometry === undefined) {
				mesh.geometry = this.figure.neutral[role];
				return;
			}

			if(this.faces.has(geometry) === false) {

				const { THREE } = this.loaded;
				const color = new THREE.Color(this.model.parts.find(({ name }) => name === role).color);
				const colors = new Float32Array(geometry.attributes.position.count * 3);

				for(let i = 0; i < colors.length; i += 3) {
					color.toArray(colors, i);
				}

				const painted = geometry.clone();
				painted.setAttribute('color', new THREE.BufferAttribute(colors, 3));
				painted.translate(...this.model.pivots[role].map(value => -value));

				this.faces.set(geometry, painted);

			}

			mesh.geometry = this.faces.get(geometry);

		});

	}

	// Les angles de chaque os et la hauteur du buste et du corps, que le passage d'un moment à l'autre interpole
	snapshot() {

		const { root, bones, torso } = this.figure;

		return [
			...[root, ...Object.values(bones)].flatMap(bone => [bone.rotation.x, bone.rotation.y, bone.rotation.z]),
			torso.position.y,
			root.position.y
		];

	}

	restore(values) {

		const { root, bones, torso } = this.figure;

		[root, ...Object.values(bones)].forEach((bone, index) => bone.rotation.set(...values.slice(index * 3, index * 3 + 3)));
		[torso.position.y, root.position.y] = values.slice(-2);

	}

	// Le moment suivant : le repos après une émotion ou une posture, et après le repos une émotion, autre que la
	// précédente, ou de temps en temps une posture
	choose() {

		const pick = list => list[Math.floor(Math.random() * list.length)];

		if(this.gesture !== 'stand') {
			return 'stand';
		}

		const emotions = Object.keys(this.model.emotions).map(name => 'emotion:'+ name);

		// Les postures du modèle viennent du skill 3d-perso, dont la liste peut changer sans que l'aperçu soit relivré. Un
		// modèle téléversé, sans émotion, ne joue qu'elles
		if(emotions.length === 0 || Math.random() < UserAvatarEditor.POSTURE_SHARE) {
			return pick(UserAvatarEditor.POSTURES.filter(name => name.startsWith('pose:') === false || this.model.poses[name.slice(5)] !== undefined));
		}

		return pick(emotions.filter(name => name !== this.emotion));

	}

	// Les angles d'un moment d'émotion ou de posture, et son visage
	getMoment() {

		const [kind, name] = this.gesture.split(':');

		if(kind === 'emotion') {
			return this.model.emotions[name];
		}

		if(kind === 'pose') {

			const pose = this.model.poses[name];

			return { pose, visage: (pose.expression === undefined) ? null : { bouche: this.model.expressions[pose.expression] } };

		}

		return { pose: null, visage: null };

	}

	// Le moment à l'instant donné. Chacun ne pose que ses os : tout part du personnage immobile
	pose(time) {

		this.still();

		if(this.gesture === 'still') {
			return;
		}

		const { root, torso, bones } = this.figure;

		if(this.gesture === 'stand' || this.gesture === 'walk') {

			root.position.y = this.loaded.figure.pose(this.figure, this.gesture, { phase: time * UserAvatarEditor.STEP, time });

			// Le poids passe d'une jambe à l'autre au repos : le corps se balance sur ses pieds, plus lentement qu'il ne respire
			if(this.gesture === 'stand') {
				root.rotation.z = Math.sin(time * .9) * .02;
			}

			return;

		}

		const { pose } = this.getMoment();

		Object.entries(pose)
			.filter(([name]) => bones[name] !== undefined)
			.forEach(([name, angles]) => bones[name].rotation.set(...angles));

		root.position.y = -pose.descente * this.model.anchors.descenteAssis;

		// Une émotion ou une posture tenue respire comme le repos
		torso.rotation.x += Math.sin(time * 1.3) * .02;

	}

	// Un clignement ferme les yeux par leur paupière, ramenée à plat, et les rouvre, au repos comme pendant un moment
	blink(time) {

		if(this.gesture === 'still') {
			this.blinkAt = time + UserAvatarEditor.BLINK_EVERY[0];
			return;
		}

		const progress = (time - this.blinkAt) / UserAvatarEditor.BLINK;

		if(progress >= 1) {
			const [min, max] = UserAvatarEditor.BLINK_EVERY;
			this.blinkAt = time + min + Math.random() * (max - min);
			return;
		}

		if(progress > 0) {
			const scale = 1 - .9 * Math.sin(Math.PI * progress);
			this.figure.eyelids.forEach(bone => bone.scale.setY(scale));
		}

	}

	animate(now) {

		this.frameId = null;

		// Le panneau fermé emporte le formulaire : l'aperçu s'arrête avec lui
		if(this.form.isConnected === false) {
			this.dispose();
			return;
		}

		const time = now / 1000;
		const elapsed = time - this.time;

		this.time = time;

		if(time >= this.next) {

			const hold = (this.gesture === 'stand') ? UserAvatarEditor.GESTURE : UserAvatarEditor.REST;

			this.from = this.snapshot();
			this.since = time;
			this.gesture = this.choose();

			if(this.gesture.startsWith('emotion:')) {
				this.emotion = this.gesture;
			}

			this.next = time + hold * (.85 + Math.random() * .3);

		}

		this.pose(time);

		// Un visage ne s'interpole pas : il change à mi-passage, quand la pose est entre les deux
		const progress = (this.from === null) ? 1 : Math.min(1, (time - this.since) / UserAvatarEditor.BLEND);

		if(progress >= .5) {
			this.setFace(this.getMoment().visage);
		}

		if(this.from !== null) {

			const weight = progress * progress * (3 - 2 * progress);

			if(progress < 1) {
				const to = this.snapshot();
				this.restore(to.map((value, index) => this.from[index] + (value - this.from[index]) * weight));
			} else {
				this.from = null;
			}

		}

		this.blink(time);

		// L'aperçu revient de face pendant la pose immobile, et retrouve ensuite l'angle où on l'a laissé
		const facing = (this.gesture === 'still') ? 0 : this.angle;
		this.facing += (facing - this.facing) * Math.min(1, elapsed * 6);

		this.render();
		this.request();

	}

	// Une seule demande d'image à la fois, aucune tant que l'onglet est caché
	request() {

		if(
			this.motion === false ||
			this.frameId !== null ||
			this.figure === null ||
			document.hidden
		) {
			return;
		}

		this.frameId = requestAnimationFrame(now => this.animate(now));

	}

	frame(frame, angle) {

		const [part, distance, rise] = UserAvatarEditor.FRAMES[frame];
		const target = this.figure.h * part;

		this.camera.position.set(Math.sin(angle) * distance, target + rise, Math.cos(angle) * distance);
		this.camera.lookAt(0, target, 0);

	}

	render() {

		const { clientWidth: width, clientHeight: height } = this.container;

		// L'image enregistrée revient par toBlob(), après une fermeture possible du panneau : un renderer libéré ne rend plus
		if(this.disposed || this.figure === null || width === 0 || height === 0) {
			return;
		}

		this.renderer.setSize(width, height);

		this.camera.aspect = width / height;
		this.camera.updateProjectionMatrix();

		this.frame('body', this.motion ? this.facing : this.angle);

		this.renderer.render(this.scene, this.camera);

	}

	/**
	 * Le pied n'enregistre qu'une composition : sur l'onglet du modèle, il se cache, et l'aperçu attend un modèle, en place
	 * ou vérifié, sans quoi le dépôt prend toute la largeur. « Au hasard » ne tire qu'une composition.
	 */
	static selectTab(target) {

		Lime.Tab.select(target);

		const editor = UserAvatarEditor.current;

		if(editor !== null && editor.form.contains(target)) {
			editor.showUpload(target.dataset.tab === 'upload');
			editor.refreshFooter();
		}

	}

	static drop(event, zone) {
		UserAvatarEditor.current?.uploadDrop?.drop(event, zone);
	}

	/**
	 * Le modèle vérifié par `avatarUpload:doPreview`, son index et son binaire en base64, que l'aperçu monte comme le binaire
	 * qu'on lirait de la route du fichier.
	 */
	static previewed(upload) {

		const editor = UserAvatarEditor.current;

		if(editor === null) {
			return;
		}

		const buffer = Uint8Array.from(atob(upload.buffer), character => character.charCodeAt(0)).buffer;

		editor.verified = { index: upload.index, buffer };
		editor.uploadDrop.showVerified(true);
		editor.showUpload(editor.getTab() === 'upload');
		editor.refreshDrop();

	}

	/**
	 * Le modèle que `avatarUpload:doCreate` vient de mettre en place, celui que « Valider » a vérifié : la même conversion
	 * a rendu le même binaire, que l'aperçu reprend sans le relire. L'onglet du modèle est déjà repeint autour d'un nouveau
	 * dépôt.
	 */
	static placed(upload) {

		const editor = UserAvatarEditor.current;

		if(editor === null) {
			return;
		}

		editor.placeUpload(upload);

	}

	placeUpload(upload) {

		this.preview.dataset.uploadUrl = upload.url;
		this.preview.dataset.uploadIndex = JSON.stringify(upload.index);

		this.watchDrop();

		// Un « Choisir un autre fichier » pendant l'envoi a oublié le modèle vérifié : son binaire se relit alors du serveur
		const buffer = (this.verified === null) ? SceneLoader.read(upload.url, 'arrayBuffer') : Promise.resolve(this.verified.buffer);

		this.verified = null;

		buffer.then(buffer => {

			if(this.disposed) {
				return;
			}

			this.upload = { ...upload, buffer };
			this.showUpload(this.getTab() === 'upload');
			this.refreshFooter();

		});

	}

	// Le dépôt d'un modèle, quand l'envoi est ouvert (`SceneUploadDrop`), qui garde le fichier choisi en attente. L'onglet
	// du modèle se repeint après un import : le dépôt neuf remplace l'ancien
	watchDrop() {

		this.uploadDrop?.dispose();

		const node = this.form.qs('[data-scene-upload]');
		this.uploadDrop = (node === null) ? null : new SceneUploadDrop(node, this.form, () => this.refreshDrop());

	}

	/**
	 * Le dépôt a changé. Un nouveau fichier ou la croix ont fait oublier le modèle vérifié : l'aperçu revient au modèle en
	 * place, dont la carte n'a pas quitté l'écran et retrouve sa suppression.
	 */
	refreshDrop() {

		if(this.verified !== null && this.uploadDrop.isVerified() === false) {
			this.verified = null;
			this.showUpload(this.getTab() === 'upload');
		}

		// Tant qu'un modèle vérifié attend, la carte du modèle en place reste pour dire ce qu'il remplacera, sans sa suppression,
		// qui se lirait comme celle du modèle que l'aperçu montre
		this.form.qs('[data-avatar-upload-clear]')?.classList.toggle('hide', this.verified !== null);

		this.refreshFooter();

	}

	getTab() {
		return this.form.qs('.tab-item.selected')?.dataset.tab ?? 'compose';
	}

	refreshFooter() {

		const upload = (this.getTab() === 'upload');

		this.submit.closest('.panel-footer')?.classList.toggle('hide', upload);
		this.preview.classList.toggle('hide', upload && (this.verified ?? this.upload) === null);
		this.form.qs('[data-avatar-random]').classList.toggle('hide', upload);

	}

	/**
	 * Les gestes de l'onglet du modèle. « Valider » vérifie le fichier, « Choisir comme avatar » le reposte pour de bon,
	 * « Choisir un autre fichier » en ouvre un autre. « Supprimer ce modèle » et l'interrupteur de la photo s'appliquent au
	 * modèle en place. L'image qui part est celle de ce que l'avatar montrera, rendue avant de poster, et seulement quand il
	 * remplace la photo de profil. Chaque geste dit qu'il travaille jusqu'à la réponse (`data-waiter`), rendu compris.
	 */
	clickUpload(event) {

		if(this.loaded === null) {
			return;
		}

		const target = event.target;

		if(target.closest('[data-scene-upload-validate]') !== null) {
			this.uploadDrop.send({}, () => this.disposed, this.uploadDrop.node.dataset.sceneUploadPreview);
		} else if(target.closest('[data-scene-upload-add]') !== null) {

			// Sans modèle en place, le choix est l'interrupteur du modèle vérifié ; avec, celui du modèle en place, qu'il garde
			const choice = this.uploadDrop.node.qs('[data-scene-upload-verified] input[name="replacesPhotoUpload"]');
			const replacesPhoto = (choice === null) ? (this.isPhotoReplaced() ? '1' : '0') : choice.value;

			this.uploadDrop.send(new Promise(resolve => this.post(this.verified, replacesPhoto, resolve)), () => this.disposed);

		} else if(target.closest('[data-scene-upload-again]') !== null) {
			this.uploadDrop.chooseAgain();
		} else if(target.closest('[data-avatar-upload-clear]') !== null) {

			// Sans composition, l'avatar part avec le modèle et la photo revient : aucune image à rendre
			const node = target.closest('[data-avatar-upload-clear]');
			const replacesPhoto = (node.dataset.avatarUploadClear === 'composition' && this.isPhotoReplaced()) ? '1' : '0';

			this.press(node, null, replacesPhoto, '/user/avatarUpload:doClear');

		} else if(target.closest('[data-avatar-photo]') !== null) {
			const node = target.closest('[data-avatar-photo]');
			this.press(node, this.upload, node.dataset.avatarPhoto, '/user/avatarUpload:doPhoto');
		}

	}

	// L'interrupteur de la photo n'est là qu'avec un modèle en place
	isPhotoReplaced() {
		return (this.form.qs('[data-avatar-photo]')?.classList.contains('field-switch-on') ?? false);
	}

	// Le choix de la photo et, quand il la remplace, l'image de `upload`, ou de la composition pour null
	post(upload, replacesPhoto, send) {

		if(replacesPhoto === '1') {
			this.capture(upload, blob => send({ replacesPhoto, image: blob }));
		} else {
			send({ replacesPhoto });
		}

	}

	// Le cadriciel ne lance le `data-waiter` que sur un `data-ajax` ou un formulaire : ces gestes, qui postent par un écouteur,
	// le lancent eux-mêmes. Une réponse qui recharge la page ne repeint le geste qu'une fois la page rechargée
	// (`Ajax.Instruction.renderRedirect()`) : il reste inactif jusque-là, sans quoi l'interrupteur se rouvrirait un instant
	// dans son ancien état. Seul un refus, qui ne recharge rien, le rend
	press(node, upload, replacesPhoto, url) {

		const enable = startWaiter(node);

		this.post(upload, replacesPhoto, values => {

			const body = new FormData();

			for(const [name, value] of Object.entries(values)) {
				body.append(name, value);
			}

			new Ajax.Query(this.form)
				.url(url)
				.body(body)
				.fetch()
				.then(json => {
					if(json.__redirect === undefined) {
						enable();
					}
				}, () => enable());

		});

	}

	save() {

		const compose = this.form.qs('.tab-panel[data-tab="compose"]') ?? this.form;

		this.capture(null, blob => {

			const body = new FormData();
			body.append('composition', JSON.stringify(this.getComposition()));
			body.append('replacesPhoto', compose.qs('input[name="replacesPhoto"]:checked').value);
			body.append('image', blob, 'avatar.png');

			new Ajax.Query(this.form)
				.url(this.form.dataset.url)
				.body(body)
				.fetch();

		});

	}

	// L'image se rend sur un contexte à part, carré et au fond transparent, comme l'aperçu : la photo de profil laisse
	// voir autour du personnage le fond de chaque écran où elle s'affiche. `upload` est le modèle à rendre, null pour la
	// composition, que l'aperçu le montre ou non
	capture(upload, send) {

		const { THREE } = this.loaded;
		const size = UserAvatarEditor.IMAGE_SIZE;

		const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
		renderer.setPixelRatio(1);
		renderer.setSize(size, size, false);

		const shown = this.shown;

		if(upload !== shown) {
			this.shown = upload;
			this.place();
		}

		// L'image est le personnage immobile, à quelque instant de son geste qu'on enregistre : l'image suivante de
		// l'aperçu le repose
		this.still();
		this.setFace(null);

		this.camera.aspect = 1;
		this.camera.updateProjectionMatrix();
		this.frame('face', .3);

		renderer.render(this.scene, this.camera);

		renderer.domElement.toBlob(blob => {

			// dispose() libère les ressources de three, pas le contexte WebGL, que le navigateur compte jusqu'à son plafond
			renderer.dispose();
			renderer.forceContextLoss();

			if(upload !== shown) {
				this.shown = shown;
				this.place();
			} else {
				this.render();
			}

			send(blob);

		}, 'image/png');

	}

	dispose() {

		if(this.disposed) {
			return;
		}

		this.disposed = true;
		this.motion = false;

		if(this.frameId !== null) {
			cancelAnimationFrame(this.frameId);
			this.frameId = null;
		}

		if(this.changeId !== null) {
			cancelAnimationFrame(this.changeId);
			this.changeId = null;
		}

		this.listeners.forEach(([target, type, listener, capture = false]) => target.removeEventListener(type, listener, capture));
		this.uploadDrop?.dispose();

		this.observer?.disconnect();

		// dispose() libère les ressources de three, pas le contexte WebGL, que le navigateur compte jusqu'à son plafond
		if(this.renderer !== null) {
			this.renderer.dispose();
			this.renderer.forceContextLoss();
			this.renderer.domElement.remove();
		}

		if(UserAvatarEditor.current === this) {
			UserAvatarEditor.current = null;
		}

	}

}

// Une navigation retire les panneaux sans les fermer (`Lime.Panel.purge()`), donc sans `panelClose` : l'aperçu garderait
// son contexte WebGL et son écouteur sur document
document.addEventListener('navigation.sleep', () => UserAvatarEditor.current?.dispose());
