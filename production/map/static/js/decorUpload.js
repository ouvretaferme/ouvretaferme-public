/**
 * L'aperçu des modèles 3D du décor importés, dans l'administration (`DecorUploadUi::getAdmin()` et le panneau de retrait) :
 * chaque vignette tourne sur elle-même. Le modèle est monté par le lecteur des modèles cuits (`sceneBuildModel()`), sous la
 * famille que porte son index, celle d'un bâtiment ou de la rangée de l'élément.
 *
 * Une page montre une file entière de vignettes, et le navigateur plafonne les contextes WebGL d'une page : un seul
 * renderer, hors du document, dessine chaque modèle à son tour, et son image est copiée dans le canevas 2D de la vignette.
 * Il se libère sur `navigation.sleep` : en navigation ajax, la page suivante ne recharge pas le document, et un contexte
 * oublié tiendrait la carte graphique.
 */
class MapDecorUploadViewer {

	static current = null;

	// En radians par seconde, le tour sur lui-même, qui montre les quatre faces sans geste de la souris
	static TURN = .4;

	// L'angle de la vignette immobile, quand le système demande moins de mouvement : trois quarts de face
	static ANGLE = .6;

	static FOV = 28;

	// Une file entière tourne sur une seule carte graphique : trente images par seconde suffisent à l'œil
	static INTERVAL = 1000 / 30;

	/**
	 * Appelée par `onrender` : une exception levée ici ferait tomber les `onrender` suivants de la page, d'où une chaîne de
	 * promesse dès la première instruction.
	 */
	static show(node) {

		Promise.resolve()
			.then(() => {
				MapDecorUploadViewer.current ??= new MapDecorUploadViewer(node.qs('[data-scene-directory]'));
				return MapDecorUploadViewer.current.add(node);
			})
			.catch(error => console.error(error));

	}

	static stop() {

		if(MapDecorUploadViewer.current !== null) {
			MapDecorUploadViewer.current.close();
			MapDecorUploadViewer.current = null;
		}

	}

	constructor(container) {

		this.previews = [];
		this.closed = false;
		this.motion = (window.matchMedia('(prefers-reduced-motion: reduce)').matches === false);
		this.frameId = null;
		this.last = 0;

		this.three = null;
		this.renderer = null;

		this.listeners = new AbortController();
		document.addEventListener('visibilitychange', () => this.request(), { signal: this.listeners.signal });

		// Une vignette ne télécharge son modèle qu'en approchant de l'écran : une page entière de modèles ne se lit pas
		// d'un coup, seulement ce que l'admin fait défiler
		this.waiting = new Map();
		this.observer = new IntersectionObserver(entries => entries
			.filter(entry => entry.isIntersecting)
			.forEach(entry => {
				this.observer.unobserve(entry.target);
				this.waiting.get(entry.target)?.();
				this.waiting.delete(entry.target);
			}), { rootMargin: '200px 0px' });

		this.ready = SceneLoader.load(container).then(three => this.mount(three));

	}

	mount(three) {

		const { THREE } = three;

		this.three = three;

		this.scene = new THREE.Scene();
		this.scene.add(new THREE.HemisphereLight('#ffffff', '#8a8a8a', 2.2));

		const sun = new THREE.DirectionalLight('#ffffff', 1.6);
		sun.position.set(3, 5, 4);
		this.scene.add(sun);

		this.camera = new THREE.PerspectiveCamera(MapDecorUploadViewer.FOV, 4 / 3, .1, 200);

		// L'image se copie après le rendu : sans `preserveDrawingBuffer`, le navigateur peut avoir vidé le tampon
		this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });

	}

	/**
	 * Une vignette qui ne se charge pas, binaire refusé ou cassé, reste vide sans arrêter les autres.
	 */
	async add(node) {

		const preview = { node, canvas: node.qs('canvas'), model: null, drawn: false };

		this.previews.push(preview);

		try {

			await new Promise(resolve => {
				this.waiting.set(node, resolve);
				this.observer.observe(node);
			});

			if(this.closed) {
				return;
			}

			const [buffer] = await Promise.all([
				SceneLoader.read(node.dataset.url, 'arrayBuffer'),
				this.ready
			]);

			if(this.closed) {
				return;
			}

			preview.model = this.build(JSON.parse(node.dataset.index), buffer);

			this.request();

		} catch(error) {

			this.previews = this.previews.filter(entry => entry !== preview);
			console.error(error);

		}

	}

	/**
	 * Le modèle posé sur le sol, centré sur l'axe du tour, sa hauteur et son empan au sol pour le cadrage. L'index d'un envoi
	 * ne porte qu'un modèle, sous le nom de sa famille.
	 */
	build(index, buffer) {

		const { THREE, sceneBuildModel } = this.three;

		const [family] = Object.keys(index.models);
		const [min, max] = index.models[family].box;
		const model = sceneBuildModel(THREE, { index, buffer }, family);

		const holder = new THREE.Group();
		const body = new THREE.Group();

		model.parts.forEach(part => body.add(new THREE.Mesh(
			part.geometry,
			part.geometry.hasAttribute('color') ?
				new THREE.MeshLambertMaterial({ vertexColors: true }) :
				new THREE.MeshLambertMaterial({ color: new THREE.Color('#'+ part.color) })
		)));

		body.position.set(-(min[0] + max[0]) / 2, -min[1], -(min[2] + max[2]) / 2);
		holder.add(body);

		return {
			holder,
			h: max[1] - min[1],
			size: Math.hypot(max[0] - min[0], max[2] - min[2])
		};

	}

	// Une seule demande d'image à la fois, aucune tant que l'onglet est caché
	request() {

		if(
			this.closed ||
			this.frameId !== null ||
			document.hidden
		) {
			return;
		}

		this.frameId = requestAnimationFrame(now => this.animate(now));

	}

	animate(now) {

		this.frameId = null;

		// Une vignette retirée du document, panneau fermé ou liste rechargée, emporte son modèle
		this.previews
			.filter(preview => preview.node.isConnected === false)
			.forEach(preview => this.release(preview));

		this.previews = this.previews.filter(preview => preview.node.isConnected);

		if(now - this.last < MapDecorUploadViewer.INTERVAL) {
			this.request();
			return;
		}

		this.last = now;

		// Toutes les mesures, puis toutes les écritures : redimensionner un canevas entre deux lectures de la géométrie
		// forcerait un recalcul de la mise en page à chaque vignette. Immobile, chaque vignette ne se dessine qu'une fois,
		// y compris hors de l'écran ; en mouvement, seules celles qui se voient se redessinent
		const measured = this.previews
			.filter(preview => preview.model !== null && (this.motion || preview.drawn === false))
			.map(preview => ({ preview, rect: preview.canvas.getBoundingClientRect() }))
			.filter(({ rect }) => rect.width > 0 && (this.motion === false || (rect.bottom > 0 && rect.top < window.innerHeight)));

		const time = now / 1000;

		measured.forEach(({ preview, rect }) => this.draw(preview, rect, time));

		if(this.motion) {
			this.request();
		}

	}

	draw(preview, rect, time) {

		const { canvas, model } = preview;
		const ratio = Math.min(window.devicePixelRatio, 2);
		const width = Math.round(rect.width * ratio);
		const height = Math.round(rect.height * ratio);

		if(canvas.width !== width || canvas.height !== height) {
			canvas.width = width;
			canvas.height = height;
		}

		this.renderer.setSize(width, height, false);

		// Le recul suit le plus grand de l'empan au sol et de la hauteur : un clocher se cadre comme un hangar
		const reach = Math.max(model.size, model.h * 1.6);

		this.camera.aspect = width / height;
		this.camera.position.set(0, model.h * .5 + reach * .5, reach * 1.55);
		this.camera.lookAt(0, model.h * .4, 0);
		this.camera.updateProjectionMatrix();

		model.holder.rotation.y = this.motion ? MapDecorUploadViewer.ANGLE + time * MapDecorUploadViewer.TURN : MapDecorUploadViewer.ANGLE;

		this.scene.add(model.holder);
		this.renderer.render(this.scene, this.camera);
		this.scene.remove(model.holder);

		const context = canvas.getContext('2d');
		context.clearRect(0, 0, width, height);
		context.drawImage(this.renderer.domElement, 0, 0);

		preview.drawn = true;

	}

	release(preview) {

		this.observer.unobserve(preview.node);
		this.waiting.delete(preview.node);

		preview.model?.holder.traverse(object => {
			object.geometry?.dispose();
			object.material?.dispose();
		});

	}

	close() {

		this.closed = true;

		if(this.frameId !== null) {
			cancelAnimationFrame(this.frameId);
			this.frameId = null;
		}

		this.listeners.abort();

		this.observer.disconnect();
		this.waiting.clear();

		this.previews.forEach(preview => this.release(preview));
		this.previews = [];

		if(this.renderer !== null) {

			// dispose() libère les ressources de three, pas le contexte WebGL, que le navigateur compte jusqu'à son plafond
			this.renderer.dispose();
			this.renderer.forceContextLoss();
			this.renderer = null;

		}

	}

}

document.addEventListener('navigation.sleep', () => MapDecorUploadViewer.stop());
