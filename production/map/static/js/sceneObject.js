/**
 * Le panneau d'un élément neuf d'une rangée Eau, Nature ou Matériel (`\map\DecorObjectUi`) : le dépôt est celui de l'avatar et
 * du bâtiment (`SceneUploadDrop`, `base/scene/static/js/upload.js`), et l'aperçu, caché jusque-là, montre le modèle que
 * « Valider » a fait vérifier, avant qu'on l'ajoute. Il le monte par la fabrique des bâtiments téléversés, sous la famille de
 * l'élément (`building.uploaded`).
 */
class MapSceneObject {

	// Une seule à la fois : un panneau rouvert en crée une nouvelle, et le navigateur limite les contextes WebGL d'une page
	static current = null;

	// La hausse de la caméra, en part de la distance, celle de l'éditeur d'un bâtiment
	static RISE = .55;

	// Rien n'empêche le cadriciel de jouer deux fois le onrender d'un même corps : l'aperçu déjà monté sur ce formulaire est gardé
	static start(form) {

		const current = MapSceneObject.current;

		if(
			current !== null &&
			current.form === form &&
			current.container === form.qs('[data-scene-directory]')
		) {
			return;
		}

		current?.dispose();
		MapSceneObject.current = new MapSceneObject(form);

	}

	/**
	 * L'élément créé revient à la scène ouverte derrière le panneau, qui le range dans sa rangée. Un panneau ouvert par son
	 * adresse n'a pas de scène derrière lui, ni même `scene.js`, et la page qui suivra lira l'élément en base.
	 */
	static saved(object) {

		if(typeof MapScene === 'undefined') {
			return;
		}

		MapScene.current?.decor?.objectSaved(object);

	}

	static drop(event, zone) {
		MapSceneObject.current?.uploadDrop.drop(event, zone);
	}

	/**
	 * Le modèle vérifié par `decorObject:doPreview`, son index et son binaire en base64, que l'aperçu monte comme le binaire
	 * qu'on lirait de la route du fichier.
	 */
	static previewed(upload) {

		const editor = MapSceneObject.current;

		if(editor === null) {
			return;
		}

		editor.upload = { index: upload.index, buffer: Uint8Array.from(atob(upload.buffer), character => character.charCodeAt(0)).buffer };
		editor.uploadDrop.showVerified(true);
		editor.place();

	}

	constructor(form) {

		this.form = form;
		this.family = form.elements.namedItem('family').value;
		this.container = form.qs('[data-scene-directory]');
		this.preview = form.qs('.util-composer-preview');

		const drop = form.qs('[data-scene-upload]');

		this.uploadDrop = new SceneUploadDrop(drop, form, () => this.refreshDrop());

		// Le modèle vérifié, null tant que « Valider » n'a rien rendu
		this.upload = null;

		this.loaded = null;
		this.renderer = null;
		this.root = null;
		this.angle = .6;
		this.disposed = false;

		this.listeners = [
			[drop, 'click', event => this.clickDrop(event)],
			// Entrée dans le nom enverrait le formulaire, le navigateur le faisant de tout champ : le modèle part par ses boutons
			[form, 'keydown', event => {
				if(event.key === 'Enter') {
					event.preventDefault();
				}
			}],
			[form.closest('.panel'), 'panelClose', () => this.dispose()],
		];

		this.listeners.forEach(([target, type, listener]) => target.addEventListener(type, listener));

		// L'aperçu se colle sous l'en-tête du panneau, qui est lui-même collé et dont la hauteur suit son titre
		const header = form.closest('.panel')?.qs('.panel-header');
		form.style.setProperty('--util-composer-top', (header?.offsetHeight ?? 0) +'px');

		SceneLoader.loadCrop(this.container, ['building.uploaded']).then(loaded => {

			// Un panneau remplacé ou fermé pendant le chargement ne monte plus rien
			if(this.disposed) {
				return;
			}

			this.loaded = loaded;
			this.mount();
			this.place();

		});

	}

	mount() {

		const { THREE } = this.loaded;

		this.materials = new Map();

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

		this.observer = new ResizeObserver(() => this.render());
		this.observer.observe(this.container);

	}

	// Le modèle vérifié, pièce par pièce dans sa couleur : une couleur cuite s'écrit sans dièse
	assemble() {

		const { THREE } = this.loaded;
		const model = this.loaded.building.uploaded(this.upload.index, this.upload.buffer, this.family);

		const root = new THREE.Group();

		for(const part of model.parts) {

			const color = '#'+ part.color.replace('#', '');

			if(this.materials.has(color) === false) {
				this.materials.set(color, new THREE.MeshToonMaterial({ color }));
			}

			root.add(new THREE.Mesh(part.geometry, this.materials.get(color)));

		}

		return root;

	}

	release(root) {
		root.traverse(object => object.geometry?.dispose());
	}

	/**
	 * L'aperçu ne montre que le modèle vérifié : sans lui, il se cache et le dépôt prend toute la largeur (`util-composer`).
	 */
	place() {

		this.preview.classList.toggle('hide', this.upload === null);

		if(this.loaded === null) {
			return;
		}

		if(this.root !== null) {
			this.scene.remove(this.root);
			this.release(this.root);
			this.root = null;
		}

		if(this.upload !== null) {
			this.root = this.assemble();
			this.scene.add(this.root);
		}

		this.render();

	}

	render() {

		const { clientWidth: width, clientHeight: height } = this.container;

		if(this.disposed || this.renderer === null || this.root === null || width === 0 || height === 0) {
			return;
		}

		this.renderer.setSize(width, height);

		const { THREE } = this.loaded;

		// La caméra recule jusqu'à voir tout le modèle, quelle que soit sa taille
		const box = new THREE.Box3().setFromObject(this.root);
		const center = box.getCenter(new THREE.Vector3());
		const radius = box.getSize(new THREE.Vector3()).length() / 2;
		const distance = radius / Math.sin(this.camera.fov * Math.PI / 360) * 1.05;

		this.camera.aspect = width / height;
		this.camera.position.set(center.x + Math.sin(this.angle) * distance, center.y + distance * MapSceneObject.RISE, center.z + Math.cos(this.angle) * distance);
		this.camera.lookAt(center);
		this.camera.updateProjectionMatrix();

		this.renderer.render(this.scene, this.camera);

	}

	// Le nom et la famille partent avec le fichier, à chaque envoi
	getUploadValues() {

		return Object.fromEntries(
			['structure', 'family', 'name']
				.map(name => [name, this.form.elements.namedItem(name).value])
		);

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

	// Un nouveau fichier a fait oublier le modèle vérifié : l'aperçu se cache
	refreshDrop() {

		if(this.upload !== null && this.uploadDrop.isVerified() === false) {
			this.upload = null;
			this.place();
		}

	}

	dispose() {

		if(this.disposed) {
			return;
		}

		this.disposed = true;

		this.listeners.forEach(([target, type, listener]) => target.removeEventListener(type, listener));
		this.uploadDrop.dispose();

		this.observer?.disconnect();

		if(this.root !== null) {
			this.release(this.root);
		}

		this.materials?.forEach(material => material.dispose());

		// dispose() libère les ressources de three, pas le contexte WebGL, que le navigateur compte jusqu'à son plafond
		if(this.renderer !== null) {
			this.renderer.dispose();
			this.renderer.forceContextLoss();
			this.renderer.domElement.remove();
		}

		if(MapSceneObject.current === this) {
			MapSceneObject.current = null;
		}

	}

}

// Une navigation retire les panneaux sans les fermer (`Lime.Panel.purge()`), donc sans `panelClose` : l'aperçu garderait
// son contexte WebGL
document.addEventListener('navigation.sleep', () => MapSceneObject.current?.dispose());
