/**
 * L'éditeur d'un bâtiment de la structure : les choix du type, que le serveur rend en boutons radio d'après `choices.json`,
 * montent le bâtiment dans l'aperçu par la fabrique du socle (`building.build`), et chaque vignette montre le bâtiment tel
 * qu'il serait avec ce choix. Les choix partent en JSON dans `settings`, que le formulaire envoie : le serveur les confronte
 * au même fichier.
 *
 * Un bâtiment neuf s'importe aussi, dans un second onglet quand l'envoi est ouvert : « Valider » fait vérifier le fichier, et
 * l'aperçu, caché sur cet onglet jusque-là, montre le modèle vérifié avant qu'on l'ajoute. Le pied n'enregistre qu'une
 * composition et se cache sur l'onglet du modèle. Le dépôt du fichier est celui de l'avatar (`SceneUploadDrop`,
 * `base/scene/static/js/upload.js`).
 */
class MapSceneBuilding {

	// Une seule à la fois : un panneau rouvert en crée une nouvelle, et le navigateur limite les contextes WebGL d'une page
	static current = null;

	// Le côté d'une vignette, en pixels, celui des vignettes du catalogue du décor
	static THUMB = 128;

	// La hausse de la caméra, en part de la distance : l'aperçu et les vignettes voient le bâtiment d'un peu au-dessus, comme la scène
	static RISE = .55;

	// Rien n'empêche le cadriciel de jouer deux fois le onrender d'un même pied : l'éditeur déjà monté sur ce formulaire est gardé
	static start(form) {

		const current = MapSceneBuilding.current;

		if(
			current !== null &&
			current.form === form &&
			current.container === form.qs('[data-scene-directory]')
		) {
			return;
		}

		current?.dispose();
		MapSceneBuilding.current = new MapSceneBuilding(form);

	}

	/**
	 * Le bâtiment enregistré revient à la scène ouverte derrière le panneau, qui le range et remonte ses poses. Un éditeur ouvert
	 * par son adresse n'a pas de scène derrière lui, ni même `scene.js`, et la page qui suivra lira le bâtiment en base.
	 */
	static saved(building, decor) {

		if(typeof MapScene === 'undefined') {
			return;
		}

		MapScene.current?.decor?.saved(building, decor);

	}

	// Le modèle vérifié se montre sur son onglet, la composition sur le sien
	static selectTab(target) {

		Lime.Tab.select(target);

		const editor = MapSceneBuilding.current;

		if(editor !== null && editor.form.contains(target)) {
			editor.showUpload(target.dataset.tab === 'upload');
			editor.refreshFooter();
		}

	}

	static drop(event, zone) {
		MapSceneBuilding.current?.uploadDrop?.drop(event, zone);
	}

	/**
	 * Le modèle vérifié par `decorUpload:doPreview`, son index et son binaire en base64, que l'aperçu monte comme le binaire
	 * qu'on lirait de la route du fichier.
	 */
	static previewed(upload) {

		const editor = MapSceneBuilding.current;

		if(editor === null) {
			return;
		}

		const buffer = Uint8Array.from(atob(upload.buffer), character => character.charCodeAt(0)).buffer;

		editor.upload = { index: upload.index, buffer };
		editor.uploadDrop.showVerified(true);
		editor.showUpload(editor.getTab() === 'upload');
		editor.refreshFooter();

	}

	constructor(form) {

		this.form = form;
		this.editor = form.qs('[data-building-editor]');
		this.type = this.editor.dataset.type;
		this.choices = JSON.parse(this.editor.dataset.choices);
		this.entry = this.choices[this.type];
		this.container = form.qs('[data-scene-directory]');
		this.input = form.qs('input[name="settings"]');
		this.settings = JSON.parse(this.input.value);
		this.submit = form.qs('[type="submit"]');

		this.preview = form.qs('.util-composer-preview');

		// Le modèle vérifié, montré à la place de la composition tant que `uploadShown`
		this.upload = null;
		this.uploadShown = false;

		const drop = form.qs('[data-scene-upload]');
		this.uploadDrop = (drop === null) ? null : new SceneUploadDrop(drop, form, () => this.refreshDrop());

		this.loaded = null;
		this.renderer = null;
		this.thumbs = null;
		this.root = null;
		this.angle = .6;
		this.changeId = null;
		this.disposed = false;

		// La teinte qui suit son matériau : le matériau vers sa teinte
		this.followers = Object.fromEntries(Object.entries(this.entry.tints).map(([tint, [material]]) => [material, tint]));

		this.listeners = [
			[form, 'change', event => this.choose(event.target)],
			[form, 'input', event => {
				if(event.target.dataset.buildingDimension !== undefined) {
					this.requestChange();
				}
			}],
			// Entrée sur un choix ou dans l'onglet du modèle enverrait la composition, le navigateur le faisant de tout champ : le
			// modèle part par ses boutons
			[form, 'keydown', event => {
				if(
					event.key === 'Enter' &&
					(event.target.matches('input[type="radio"]') || event.target.closest('.tab-panel[data-tab="upload"]') !== null)
				) {
					event.preventDefault();
				}
			}],
			[form.closest('.panel'), 'panelClose', () => this.dispose()],
		];

		if(drop !== null) {
			this.listeners.push([drop, 'click', event => this.clickDrop(event)]);
		}

		const random = form.qs('[data-building-random]');

		if(random !== null) {
			this.listeners.push([random, 'click', () => this.random()]);
		}

		this.listeners.forEach(([target, type, listener]) => target.addEventListener(type, listener));

		// L'aperçu se colle sous l'en-tête du panneau, qui est lui-même collé et dont la hauteur suit son titre
		const header = form.closest('.panel')?.qs('.panel-header');
		form.style.setProperty('--util-composer-top', (header?.offsetHeight ?? 0) +'px');

		this.refreshFooter();
		this.refuse();

		SceneLoader.loadCrop(this.container, ['building.build', 'building.uploaded']).then(loaded => {

			// Un éditeur remplacé ou libéré pendant le chargement ne monte plus rien
			if(this.disposed) {
				return;
			}

			this.loaded = loaded;
			this.mount();
			this.place();
			this.paintThumbnails();

		});

	}

	// La valeur d'un choix telle que `choices.json` l'écrit : un nombre de fenêtres reste un nombre
	valueOf(key, value) {
		return this.entry.settings[key].find(item => String(item) === value);
	}

	// Les choix si l'on prenait `value` pour `key` : un matériau emmène la teinte par défaut de sa rangée
	variant(key, value) {

		const settings = { ...this.settings, [key]: value };
		const tint = this.followers[key];

		if(tint !== undefined) {
			settings[tint] = this.entry.tints[tint][1][value];
		}

		return settings;

	}

	isExcluded(settings) {
		return this.entry.excluded.some(rule => Object.entries(rule).every(([key, value]) => settings[key] === value));
	}

	choose(target) {

		if(target.name === 'type') {

			// Les deux types n'ont pas les mêmes choix : l'éditeur se rouvre sur ceux de départ du type choisi
			new Ajax.Navigation()
				.url('/map/decorBuilding:create?structure='+ this.editor.dataset.structure +'&type='+ target.value)
				.fetch();

			return;

		}

		if(this.entry.settings[target.name] === undefined) {
			return;
		}

		this.settings = this.variant(target.name, this.valueOf(target.name, target.value));
		this.check();
		this.change();

	}

	// Coche ce que portent les choix, ce qu'un matériau ou un tirage a changé à côté du geste
	check() {

		for(const [key, value] of Object.entries(this.settings)) {

			const radio = this.form.qs('input[type="radio"][name="'+ key +'"][value="'+ value +'"]');

			if(radio !== null) {
				radio.checked = true;
			}

		}

	}

	// Un choix qui ferait une combinaison que le type écarte ne se prend pas
	refuse() {

		this.form.qsa('input[type="radio"]', radio => {

			if(this.entry.settings[radio.name] !== undefined) {
				radio.disabled = this.isExcluded(this.variant(radio.name, this.valueOf(radio.name, radio.value)));
			}

		});

	}

	change() {

		this.input.value = JSON.stringify(this.settings);
		this.refuse();
		this.place();
		this.paintThumbnails();

	}

	// Une cote se suit pendant qu'on la tape : une image par image du navigateur, pas une par touche
	requestChange() {

		if(this.changeId !== null) {
			return;
		}

		this.changeId = requestAnimationFrame(() => {
			this.changeId = null;
			this.place();
		});

	}

	random() {

		const r = Math.random;
		const pick = list => list[Math.floor(r() * list.length)];
		const keys = Object.keys(this.entry.settings);

		// Un tirage refait jusqu'à ce qu'il se compose : les combinaisons écartées sont rares, quelques tirages suffisent
		do {

			this.settings = {};

			for(const key of keys) {
				this.settings[key] = pick(this.entry.settings[key]);
			}

		} while(this.isExcluded(this.settings));

		this.check();
		this.change();

	}

	// Les cotes du formulaire, ramenées dans les bornes du type le temps de l'aperçu : le serveur dit ce qu'une cote hors bornes a de faux
	getBuilding(settings = this.settings, type = this.type) {

		const dimension = property => {

			const bounds = this.choices[type][property];

			if(bounds === null) {
				return null;
			}

			if(type !== this.type) {
				return this.choices[type].initial[property];
			}

			const value = parseFloat(this.form.qs('[data-building-dimension="'+ property +'"]').value);

			return Number.isFinite(value) ? Math.max(bounds[0], Math.min(bounds[1], value)) : this.choices[type].initial[property];

		};

		return { type, length: dimension('length'), width: dimension('width'), height: dimension('height'), settings };

	}

	mount() {

		const { THREE } = this.loaded;

		this.materials = new Map();
		this.toon = color => {

			if(this.materials.has(color) === false) {
				this.materials.set(color, new THREE.MeshToonMaterial({ color }));
			}

			return this.materials.get(color);

		};

		this.scene = new THREE.Scene();
		this.scene.add(new THREE.HemisphereLight('#dcefff', '#93a06c', 1.6));

		const sun = new THREE.DirectionalLight('#fff1d6', 2.2);
		sun.position.set(30, 50, 40);
		this.scene.add(sun);

		this.camera = new THREE.PerspectiveCamera(30, 1, .5, 500);

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
			dragged = event.clientX;

			this.render();

		});

		canvas.addEventListener('pointerup', () => dragged = null);

		this.thumbs = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
		this.thumbs.setSize(MapSceneBuilding.THUMB, MapSceneBuilding.THUMB, false);

		this.observer = new ResizeObserver(() => this.render());
		this.observer.observe(this.container);

	}

	// Le bâtiment en pièces de sa couleur, ou null s'il ne se compose pas
	assemble(building) {

		const { THREE } = this.loaded;
		const model = this.loaded.building.build(building);

		if(model === null) {
			return null;
		}

		const root = new THREE.Group();

		for(const part of model.parts) {
			root.add(new THREE.Mesh(part.geometry, this.toon(part.color)));
		}

		return root;

	}

	// Le modèle téléversé, dans les matériaux de la composition : une couleur cuite s'écrit sans dièse
	assembleUpload() {

		const { THREE } = this.loaded;
		const model = this.loaded.building.uploaded(this.upload.index, this.upload.buffer);

		const root = new THREE.Group();

		for(const part of model.parts) {
			root.add(new THREE.Mesh(part.geometry, this.toon('#'+ part.color.replace('#', ''))));
		}

		return root;

	}

	release(root) {
		root.traverse(object => object.geometry?.dispose());
	}

	// La caméra recule jusqu'à voir tout le bâtiment, quelle que soit sa taille
	frame(camera, root, angle) {

		const { THREE } = this.loaded;

		const box = new THREE.Box3().setFromObject(root);
		const center = box.getCenter(new THREE.Vector3());
		const radius = box.getSize(new THREE.Vector3()).length() / 2;
		const distance = radius / Math.sin(camera.fov * Math.PI / 360) * 1.05;

		camera.position.set(center.x + Math.sin(angle) * distance, center.y + distance * MapSceneBuilding.RISE, center.z + Math.cos(angle) * distance);
		camera.lookAt(center);
		camera.updateProjectionMatrix();

	}

	place() {

		if(this.loaded === null) {
			return;
		}

		if(this.root !== null) {
			this.scene.remove(this.root);
			this.release(this.root);
		}

		this.root = this.uploadShown ? this.assembleUpload() : this.assemble(this.getBuilding());

		if(this.root !== null) {
			this.scene.add(this.root);
		}

		this.render();

	}

	render() {

		const { clientWidth: width, clientHeight: height } = this.container;

		if(this.disposed || this.renderer === null || width === 0 || height === 0) {
			return;
		}

		this.renderer.setSize(width, height);

		this.camera.aspect = width / height;

		if(this.root === null) {
			this.renderer.clear();
			return;
		}

		this.frame(this.camera, this.root, this.angle);
		this.renderer.render(this.scene, this.camera);

	}

	/**
	 * Chaque vignette montre le bâtiment avec son choix, les autres restant ceux du moment, et celles du type le bâtiment de départ
	 * de chaque type : elles se repeignent à chaque choix, sur un contexte à part, de la taille d'une vignette.
	 */
	paintThumbnails() {

		if(this.thumbs === null) {
			return;
		}

		const { THREE } = this.loaded;
		const camera = new THREE.PerspectiveCamera(30, 1, .5, 500);

		this.form.qsa('.util-choices:not(.util-choices-color) input[type="radio"]', radio => {

			const image = radio.parentElement.qs('img');

			if(image === null) {
				return;
			}

			const building = (radio.name === 'type') ?
				this.getBuilding(this.choices[radio.value].initial.settings, radio.value) :
				this.getBuilding(this.variant(radio.name, this.valueOf(radio.name, radio.value)));

			const root = this.assemble(building);

			if(root === null) {
				image.removeAttribute('src');
				return;
			}

			this.scene.add(root);

			if(this.root !== null) {
				this.root.visible = false;
			}

			this.frame(camera, root, .6);
			this.thumbs.render(this.scene, camera);
			image.src = this.thumbs.domElement.toDataURL('image/png');

			this.scene.remove(root);
			this.release(root);

			if(this.root !== null) {
				this.root.visible = true;
			}

		});

	}

	/**
	 * Ce qui part avec le fichier : le nom de l'onglet du modèle et la composition de l'onglet « Composer en ligne », que le
	 * bâtiment garde sans la montrer. Les champs se lisent un à un, et non par `FormData`, qui laisse de côté ceux d'un onglet
	 * désactivé ; le nom, que les deux onglets portent, se lit dans celui du modèle.
	 */
	getUploadValues() {

		const values = Object.fromEntries(
			['structure', 'decor', 'type', 'length', 'width', 'height', 'settings']
				.map(name => [name, this.form.elements.namedItem(name)])
				.filter(([, field]) => field !== null)
				.map(([name, field]) => [name, field.value])
		);

		values.name = this.form.qs('.tab-panel[data-tab="upload"] input[name="name"]').value;

		return values;

	}

	getTab() {
		return this.form.qs('.tab-item.selected')?.dataset.tab ?? 'compose';
	}

	// « Valider » vérifie le fichier, « Ajouter » le reposte pour de bon, « Choisir un autre fichier » en ouvre un autre
	clickDrop(event) {

		if(event.target.closest('[data-scene-upload-validate]') !== null) {
			this.uploadDrop.send(this.getUploadValues(), () => this.disposed, this.uploadDrop.node.dataset.sceneUploadPreview);
		} else if(event.target.closest('[data-scene-upload-add]') !== null) {
			this.uploadDrop.send(this.getUploadValues(), () => this.disposed);
		} else if(event.target.closest('[data-scene-upload-again]') !== null) {
			this.uploadDrop.chooseAgain();
		}

	}

	// Un nouveau fichier a fait oublier le modèle vérifié : l'aperçu revient à la composition
	refreshDrop() {

		if(this.upload !== null && this.uploadDrop.isVerified() === false) {
			this.upload = null;
			this.showUpload(false);
		}

		this.refreshFooter();

	}

	// Montre le modèle vérifié ou la composition, sans rien enregistrer
	showUpload(shown) {

		this.uploadShown = (shown && this.upload !== null);
		this.place();

	}

	/**
	 * Le pied n'enregistre qu'une composition. Sur l'onglet du modèle, l'aperçu attend le modèle vérifié : sans lui, le dépôt
	 * prend toute la largeur. « Au hasard » ne tire qu'une composition.
	 */
	refreshFooter() {

		const upload = (this.getTab() === 'upload');

		this.submit.closest('.panel-footer')?.classList.toggle('hide', upload);
		this.preview.classList.toggle('hide', upload && this.upload === null);
		this.form.qs('[data-building-random]')?.classList.toggle('hide', upload);

	}

	dispose() {

		if(this.disposed) {
			return;
		}

		this.disposed = true;

		if(this.changeId !== null) {
			cancelAnimationFrame(this.changeId);
			this.changeId = null;
		}

		this.listeners.forEach(([target, type, listener]) => target.removeEventListener(type, listener));
		this.uploadDrop?.dispose();

		this.observer?.disconnect();

		if(this.root !== null) {
			this.release(this.root);
		}

		this.materials?.forEach(material => material.dispose());

		// dispose() libère les ressources de three, pas le contexte WebGL, que le navigateur compte jusqu'à son plafond
		for(const renderer of [this.renderer, this.thumbs]) {
			if(renderer !== null) {
				renderer.dispose();
				renderer.forceContextLoss();
			}
		}

		this.renderer?.domElement.remove();

		if(MapSceneBuilding.current === this) {
			MapSceneBuilding.current = null;
		}

	}

}

// Une navigation retire les panneaux sans les fermer (`Lime.Panel.purge()`), donc sans `panelClose` : l'aperçu garderait
// ses contextes WebGL
document.addEventListener('navigation.sleep', () => MapSceneBuilding.current?.dispose());
