/**
 * L'aperçu des modèles 3D téléversés, dans l'administration (`UserAvatarUploadUi::getAdmin()` et le panneau de retrait) :
 * chaque vignette tourne et enchaîne les poses que la conversion a mesurées sur le modèle. Le personnage est monté par la
 * fabrique de l'éditeur d'avatar (`SceneLoader.loadAvatar()`), sous sa lumière et son matériau toon.
 *
 * Une page montre une file entière de vignettes, et le navigateur plafonne les contextes WebGL d'une page : un seul
 * renderer, hors du document, dessine chaque modèle à son tour, et son image est copiée dans le canevas 2D de la vignette.
 * Il se libère sur `navigation.sleep` : en navigation ajax, la page suivante ne recharge pas le document, et un contexte
 * oublié tiendrait la carte graphique.
 */
class UserAvatarUploadViewer {

	static current = null;

	// Le dégradé du matériau toon et le cadrage en pied (part de la hauteur visée, distance, hausse) : ceux de l'aperçu de
	// l'éditeur (`UserAvatarEditor`), pour qu'un modèle se juge comme son auteur l'a vu
	static RAMP = [120, 160, 200, 235, 255];
	static FRAME = [.5, 4.1, .35];
	static FOV = 30;

	// En secondes, une pose tenue puis le passage à la suivante ; en radians par seconde, le tour sur lui-même, qui montre
	// le dos et les côtés sans geste de la souris
	static HOLD = 1.7;
	static BLEND = .7;
	static TURN = .5;

	// L'angle de la vignette immobile, quand le système demande moins de mouvement : celui où l'éditeur montre l'avatar
	static ANGLE = .55;

	// Une file entière tourne sur une seule carte graphique : trente images par seconde suffisent à l'œil
	static INTERVAL = 1000 / 30;

	/**
	 * Appelée par `onrender` : une exception levée ici ferait tomber les `onrender` suivants de la page, d'où une chaîne de
	 * promesse dès la première instruction.
	 */
	static show(node) {

		Promise.resolve()
			.then(() => {
				UserAvatarUploadViewer.current ??= new UserAvatarUploadViewer(node.qs('[data-scene-directory]'));
				return UserAvatarUploadViewer.current.add(node);
			})
			.catch(error => console.error(error));

	}

	static stop() {

		if(UserAvatarUploadViewer.current !== null) {
			UserAvatarUploadViewer.current.close();
			UserAvatarUploadViewer.current = null;
		}

	}

	constructor(container) {

		this.previews = [];
		this.closed = false;
		this.motion = (window.matchMedia('(prefers-reduced-motion: reduce)').matches === false);
		this.frameId = null;
		this.last = 0;

		this.loaded = null;
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

		this.ready = SceneLoader.loadAvatar(container).then(loaded => this.mount(loaded));

	}

	mount(loaded) {

		const { THREE } = loaded;

		this.loaded = loaded;

		this.ramp = new THREE.DataTexture(new Uint8Array(UserAvatarUploadViewer.RAMP), UserAvatarUploadViewer.RAMP.length, 1, THREE.RedFormat);
		this.ramp.minFilter = this.ramp.magFilter = THREE.LinearFilter;
		this.ramp.needsUpdate = true;

		this.material = new THREE.MeshToonMaterial({ color: '#fff', vertexColors: true, gradientMap: this.ramp, side: THREE.FrontSide });

		this.scene = new THREE.Scene();
		this.scene.add(new THREE.HemisphereLight('#dcefff', '#93a06c', 1.4));

		const sun = new THREE.DirectionalLight('#fff1d6', 2);
		sun.position.set(-30, 50, 40);
		this.scene.add(sun);

		this.camera = new THREE.PerspectiveCamera(UserAvatarUploadViewer.FOV, 3 / 4, .05, 50);

		// L'image se copie après le rendu : sans `preserveDrawingBuffer`, le navigateur peut avoir vidé le tampon
		this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });

	}

	/**
	 * Une vignette qui ne se charge pas, binaire refusé ou cassé, reste vide sans arrêter les autres.
	 */
	async add(node) {

		const preview = { node, canvas: node.qs('canvas'), figure: null, names: [], drawn: false };

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

			preview.model = this.loaded.uploaded(JSON.parse(node.dataset.index), buffer);
			preview.figure = this.loaded.figure.assemble(preview.model, 'avatar', this.material);
			preview.names = Object.keys(preview.model.poses);

			this.request();

		} catch(error) {

			this.previews = this.previews.filter(entry => entry !== preview);
			console.error(error);

		}

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

		if(now - this.last < UserAvatarUploadViewer.INTERVAL) {
			this.request();
			return;
		}

		this.last = now;

		// Toutes les mesures, puis toutes les écritures : redimensionner un canevas entre deux lectures de la géométrie
		// forcerait un recalcul de la mise en page à chaque vignette. Immobile, chaque vignette ne se dessine qu'une fois,
		// y compris hors de l'écran ; en mouvement, seules celles qui se voient se redessinent
		const measured = this.previews
			.filter(preview => preview.figure !== null && (this.motion || preview.drawn === false))
			.map(preview => ({ preview, rect: preview.canvas.getBoundingClientRect() }))
			.filter(({ rect }) => rect.width > 0 && (this.motion === false || (rect.bottom > 0 && rect.top < window.innerHeight)));

		const time = now / 1000;

		measured.forEach(({ preview, rect }) => this.draw(preview, rect, time));

		if(this.motion) {
			this.request();
		}

	}

	draw(preview, rect, time) {

		const { canvas, figure } = preview;
		const ratio = Math.min(window.devicePixelRatio, 2);
		const width = Math.round(rect.width * ratio);
		const height = Math.round(rect.height * ratio);

		if(canvas.width !== width || canvas.height !== height) {
			canvas.width = width;
			canvas.height = height;
		}

		this.renderer.setSize(width, height, false);

		this.camera.aspect = width / height;
		this.camera.updateProjectionMatrix();

		const [part, distance, rise] = UserAvatarUploadViewer.FRAME;
		const target = figure.h * part;
		const angle = this.motion ? time * UserAvatarUploadViewer.TURN : UserAvatarUploadViewer.ANGLE;

		this.camera.position.set(Math.sin(angle) * distance, target + rise, Math.cos(angle) * distance);
		this.camera.lookAt(0, target, 0);

		this.pose(preview, this.motion ? time : null);

		this.scene.add(figure.root);
		this.renderer.render(this.scene, this.camera);
		this.scene.remove(figure.root);

		const context = canvas.getContext('2d');
		context.clearRect(0, 0, width, height);
		context.drawImage(this.renderer.domElement, 0, 0);

		preview.drawn = true;

	}

	/**
	 * Le personnage part de sa pose immobile, debout, comme dans l'éditeur, puis tient chaque pose de la conversion avant de
	 * glisser vers la suivante. `time` à NULL le laisse immobile.
	 */
	pose(preview, time) {

		const { figure, model, names } = preview;
		const { root, bones, torso, rest } = figure;

		[root, ...Object.values(bones)].forEach(bone => bone.rotation.set(0, 0, 0));
		torso.position.y = rest;
		root.position.y = 0;

		if(time === null || names.length === 0) {
			return;
		}

		const cycle = UserAvatarUploadViewer.HOLD + UserAvatarUploadViewer.BLEND;
		const step = Math.floor(time / cycle);
		const progress = Math.max(0, (time - step * cycle - UserAvatarUploadViewer.HOLD) / UserAvatarUploadViewer.BLEND);
		const weight = progress * progress * (3 - 2 * progress);

		const from = model.poses[names[step % names.length]];
		const to = model.poses[names[(step + 1) % names.length]];

		Object.keys(bones)
			.filter(name => from[name] !== undefined || to[name] !== undefined)
			.forEach(name => {
				const a = from[name] ?? [0, 0, 0], b = to[name] ?? [0, 0, 0];
				bones[name].rotation.set(...a.map((angle, axis) => angle + (b[axis] - angle) * weight));
			});

		root.position.y = -(from.descente + (to.descente - from.descente) * weight) * model.anchors.descenteAssis;

	}

	release(preview) {

		this.observer.unobserve(preview.node);
		this.waiting.delete(preview.node);

		preview.figure?.root.traverse(object => object.geometry?.dispose());

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

			this.material.dispose();
			this.ramp.dispose();

			// dispose() libère les ressources de three, pas le contexte WebGL, que le navigateur compte jusqu'à son plafond
			this.renderer.dispose();
			this.renderer.forceContextLoss();
			this.renderer = null;

		}

	}

}

document.addEventListener('navigation.sleep', () => UserAvatarUploadViewer.stop());
