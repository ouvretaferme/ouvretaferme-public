/**
 * Charge three, ses compléments et le lecteur des modèles pour une scène du produit, puis, à la
 * demande, le socle des cultures et des personnages, et les modèles cuits.
 *
 * Deux adresses de three, même à une query près, donneraient deux exemplaires dont les objets ne
 * se reconnaissent pas : three n'est donc nommé qu'ici et, en chemin relatif, dans les compléments
 * de static/lib/. Tout le reste reçoit `THREE` et `addons` en argument, comme static/js/model.js.
 */
class SceneLoader {

	// Gardée pour la page entière : une navigation ajax ou une seconde scène reçoivent le même three
	static promise = null;

	// Les modules du socle et la paire cuite, gardés comme three ; les fabriques, elles, se créent à chaque appel
	static cropPromise = null;

	// Les modules des personnages, que les scènes et l'éditeur d'avatar partagent
	static figurePromise = null;

	// Le calendrier des cultures, que la fiche d'une série lit sans three et que `loadCrop()` reprend
	static stagePromise = null;

	// En millisecondes, le temps qu'un modèle téléversé a pour arriver avant que la scène s'ouvre sans lui. Un modèle en retard
	// ne remplace pas la composition déjà montée : un corps qui change sous les yeux se remarque plus qu'un modèle absent
	static UPLOAD_DELAY = 3000;

	// Les paires cuites par leur adresse entière, répertoire et deux empreintes : la paire commune que `loadCrop()` et
	// `loadBake()` demandent sur la même page ne se lit qu'une fois
	static pairPromises = {};

	/**
	 * Le conteneur est celui que rend `SceneUi::getContainer()`, qui porte le répertoire du package.
	 *
	 * Les fichiers de lib/ portent leur version dans leur nom et se chargent sans query. Le lecteur
	 * est un module, que le lot /minify/ ne peut pas concaténer : il prend la version des assets
	 * pour changer d'adresse quand il change, par `getVersion()` qui lève tant qu'elle n'est pas
	 * posée plutôt que de rendre une adresse en `?undefined`.
	 */
	static load(container) {

		if(SceneLoader.promise !== null) {
			return SceneLoader.promise;
		}

		const directory = container.dataset.sceneDirectory;

		SceneLoader.promise = Promise.all([
			import(directory +'/lib/three-0.170.0.module.min.js'),
			import(directory +'/lib/three-0.170.0.MapControls.js'),
			import(directory +'/lib/three-0.170.0.CSS2DRenderer.js'),
			import(directory +'/lib/three-0.170.0.BufferGeometryUtils.js'),
			import(directory +'/js/model.js?'+ Ajax.Asset.getVersion())
		])
			.then(([THREE, controls, renderer, geometry, model]) => ({
				THREE,
				addons: {
					MapControls: controls.MapControls,
					CSS2DRenderer: renderer.CSS2DRenderer,
					CSS2DObject: renderer.CSS2DObject,
					mergeGeometries: geometry.mergeGeometries,
					mergeVertices: geometry.mergeVertices,
					toCreasedNormals: geometry.toCreasedNormals
				},
				sceneBuildModel: model.sceneBuildModel
			}))
			.catch(error => {

				// Une promesse rejetée ne se rejoue pas : la garder fermerait la scène après un chargement
				// coupé, jusqu'au rechargement complet de la page
				SceneLoader.promise = null;

				throw error;

			});

		return SceneLoader.promise;

	}

	/**
	 * Ce que `load()` rend, plus `baked` (la paire cuite, `{ index, buffer }`) et les seuls noms du
	 * socle demandés, sous la forme `'<module>.<clé>'` (`'species.get'`, `'crop.layout'`…). La liste
	 * est écrite en clair par chaque scène : `test/ExportSentinel.t.php` la confronte au contrat.
	 *
	 * Les fabriques se créent à chaque appel, parce que `fruit` et `crop` tiennent des tampons et des
	 * pools : deux scènes qui se succèdent en navigation ajax ne partagent rien d'autre que les
	 * modules importés et la paire cuite.
	 *
	 * Le conteneur porte aussi `data-scene-bake` : l'empreinte de chaque fichier cuit, qui fait son
	 * adresse et ne change qu'avec la cuisson, et le catalogue des espèces (`scene\BakeLib`).
	 *
	 * Les choix des bâtiments d'une structure (`building/choices.json`) se lisent ici et se passent à
	 * `sceneCreateBuilding()`, qui ne lit aucun fichier : le serveur les lit aussi, pour juger ce que
	 * l'éditeur poste (`map\DecorBuilding::properties()`).
	 */
	static loadCrop(container, names) {

		if(SceneLoader.cropPromise === null) {

			const directory = container.dataset.sceneDirectory;

			SceneLoader.cropPromise = Promise.resolve()
				.then(() => JSON.parse(container.dataset.sceneBake))
				.then(bake => Promise.all([
					SceneLoader.load(container),
					SceneLoader.loadFigure(container),
					import(directory +'/js/species.js?'+ Ajax.Asset.getVersion()),
					SceneLoader.loadStage(container),
					import(directory +'/js/crop.js?'+ Ajax.Asset.getVersion()),
					import(directory +'/js/fruit.js?'+ Ajax.Asset.getVersion()),
					import(directory +'/js/building.js?'+ Ajax.Asset.getVersion()),
					SceneLoader.loadPair({ directory, json: bake.json, bin: bake.bin }),
					SceneLoader.read(directory +'/building/choices.json?'+ Ajax.Asset.getVersion(), 'json'),
					{ species: bake.species, alias: bake.alias }
				]))
				.then(([three, { geometry, figure }, species, stage, crop, fruit, building, baked, choices, catalog]) => ({ three, modules: { geometry, species, stage, crop, fruit, figure, building }, baked, catalog, choices }))
				.catch(error => {

					// Même raison que dans load()
					SceneLoader.cropPromise = null;

					throw error;

				});

		}

		return SceneLoader.cropPromise.then(shared => SceneLoader.create(shared, names));

	}

	/**
	 * Le seul module du calendrier (`stage.js`), sans three ni paire cuite, dont `sceneCreateStage()` crée le calendrier
	 * comme `create()` le fait pour une scène : la fiche d'une série en lit les stades
	 * hors de toute scène (`SeriesSheet`, production/series/static/js/seriesSheet.js). Le conteneur porte
	 * `data-scene-directory`, comme celui de `SceneUi::getContainer()`.
	 */
	static loadStage(container) {

		if(SceneLoader.stagePromise === null) {

			const directory = container.dataset.sceneDirectory;

			SceneLoader.stagePromise = import(directory +'/js/stage.js?'+ Ajax.Asset.getVersion())
				.catch(error => {

					// Même raison que dans load()
					SceneLoader.stagePromise = null;

					throw error;

				});

		}

		return SceneLoader.stagePromise;

	}

	/**
	 * Une paire cuite, celle d'un package qui porte ses propres modèles ou la paire commune, `{ index, buffer }`, à
	 * passer à `sceneBuildModel()` que rend `load()`. Rien du socle des cultures n'est chargé.
	 *
	 * Son répertoire et ses deux empreintes sont sous `pairs.<nom>` de `data-scene-bake` (`scene\BakeLib::getPair()`),
	 * à part des clés que lit `loadCrop()` : une scène qui appelle les deux sur le même conteneur reçoit les deux paires.
	 * Sa paire se lit une fois par page chargée, comme three, et une seule fois quand elle est la paire commune.
	 */
	static loadBake(container, name) {

		return Promise.resolve()
			.then(() => JSON.parse(container.dataset.sceneBake).pairs[name])
			.then(pair => SceneLoader.loadPair(pair));

	}

	/**
	 * Une paire cuite à son adresse, `{ directory, json, bin }`, lue une fois par page chargée : deux appelants qui
	 * visent le même répertoire aux mêmes empreintes reçoivent la même lecture.
	 */
	static loadPair(pair) {

		const address = pair.directory +'?'+ pair.json +'&'+ pair.bin;

		if(SceneLoader.pairPromises[address] === undefined) {

			SceneLoader.pairPromises[address] = Promise.all([
				SceneLoader.read(pair.directory +'/model/models.json?'+ pair.json, 'json'),
				SceneLoader.read(pair.directory +'/model/models.bin?'+ pair.bin, 'arrayBuffer')
			])
				.then(([index, buffer]) => {

					// Neuf octets par sommet (models.json, `vertices`), puis trois par sommet coloré (`colors`) : un
					// binaire d'une autre cuisson que son index se lirait sans erreur, en pièces décalées
					if(buffer.byteLength !== index.vertices * 9 + index.colors * 3) {
						throw new Error('models.bin ne correspond pas à models.json ('+ pair.directory +')');
					}

					return { index, buffer };

				})
				.catch(error => {

					// Même raison que dans load()
					delete SceneLoader.pairPromises[address];

					throw error;

				});

		}

		return SceneLoader.pairPromises[address];

	}

	/**
	 * Les modules des personnages : `geometry`, que leur fabrique reçoit, et `figure`.
	 */
	static loadFigure(container) {

		if(SceneLoader.figurePromise === null) {

			const directory = container.dataset.sceneDirectory;

			SceneLoader.figurePromise = Promise.all([
				import(directory +'/js/geometry.js?'+ Ajax.Asset.getVersion()),
				import(directory +'/js/figure.js?'+ Ajax.Asset.getVersion())
			])
				.then(([geometry, figure]) => ({ geometry, figure }))
				.catch(error => {

					// Même raison que dans load()
					SceneLoader.figurePromise = null;

					throw error;

				});

		}

		return SceneLoader.figurePromise;

	}

	/**
	 * Ce que rend `load()`, plus la fabrique de l'avatar d'un utilisateur, qui monte le personnage depuis sa
	 * fiche dans la page : une composition par personne ne se cuit pas d'avance. S'y ajoute la fabrique des
	 * personnages de la scène, qui l'articule et le fait bouger, et `uploaded()`, qui monte au même contrat le modèle
	 * qu'un utilisateur a téléversé, depuis l'index et le binaire de la conversion : ses poses sont celles que la
	 * conversion a mesurées sur ses pieds, et il n'a ni émotion ni expression. Ni la paire cuite ni le reste du socle ne
	 * sont chargés, l'éditeur d'avatar n'en a pas besoin.
	 */
	static loadAvatar(container) {

		const directory = container.dataset.sceneDirectory;

		return Promise.all([
			SceneLoader.load(container),
			import(directory +'/js/avatar.js?'+ Ajax.Asset.getVersion()),
			SceneLoader.loadFigure(container)
		])
			.then(([three, avatar, { geometry, figure }]) => {

				const { THREE, addons } = three;

				// `assemble()` lit un modèle cuit dans la paire cuite ; l'avatar, monté dans la page, arrive déjà au contrat
				// d'un modèle, à ceci près qu'une couleur cuite s'écrit sans dièse
				const built = (THREE, model) => ({ ...model, parts: model.parts.map(part => ({ ...part, color: part.color.replace('#', '') })) });

				return {
					...three,
					avatar: avatar.sceneCreateAvatar(THREE),
					figure: figure.sceneCreateFigure(THREE, addons, geometry.sceneCreateGeometry(THREE, addons), built),
					uploaded: (index, buffer) => ({
						...three.sceneBuildModel(THREE, { index, buffer }, 'avatar'),
						poses: index.models.avatar.poses,
						emotions: {},
						expressions: {}
					})
				};

			});

	}

	/**
	 * Le modèle téléversé d'un membre, `{ url, index }`, avec son binaire en plus, `buffer`. Une lecture refusée ou plus longue
	 * qu'UPLOAD_DELAY rend null, et la scène monte la composition.
	 */
	static readUpload(upload) {

		// Le délai s'annule dès que la course est jouée, pour qu'aucun rejet ne parte après elle
		let timer = null;
		const delay = new Promise((resolve, reject) => {
			timer = setTimeout(() => reject(new Error('delay')), SceneLoader.UPLOAD_DELAY);
		});

		return Promise.race([SceneLoader.read(upload.url, 'arrayBuffer'), delay])
			.finally(() => clearTimeout(timer))
			.then(buffer => ({ ...upload, buffer }))
			.catch(() => null);

	}

	/**
	 * Le modèle téléversé `upload` (`readUpload()`), monté par `loaded` (`loadAvatar()`) avec les gestes et les émotions de la
	 * fiche de son auteur : des angles d'os du corps commun, que le modèle partage. Une page ne pose un modèle qu'à côté d'une
	 * fiche, puisqu'il s'enregistre sur l'avatar composé. Le binaire vient du réseau : un modèle que la fabrique ne sait pas
	 * monter rend null, et l'appelant garde la composition.
	 */
	static mountUpload(loaded, fiche, upload) {

		const composed = loaded.avatar.build(structuredClone(fiche));

		try {
			return { ...loaded.uploaded(upload.index, upload.buffer), animations: composed.animations, emotions: composed.emotions, uploaded: true };
		} catch(error) {
			return null;
		}

	}

	/**
	 * Un fichier statique du produit, les fichiers cuits comme le sprite des icônes d'espèces : rien n'y
	 * est envoyé et aucune instruction ajax n'en revient, d'où une lecture directe et non `Ajax.Query`.
	 */
	static read(url, as) {

		return fetch(url).then(response => {

			if(response.ok === false) {
				throw new Error(response.url +' : '+ response.status);
			}

			return response[as]();

		});

	}

	static create({ three, modules, baked, catalog, choices }, names) {

		const { THREE, addons, sceneBuildModel } = three;
		const geometry = modules.geometry.sceneCreateGeometry(THREE, addons);

		const created = {};

		// crop et fruit reçoivent la règle des ligneux de l'instance de species : une seule par appel, que la scène demande ou non un nom de species
		const species = () => created.species ??= modules.species.sceneCreateSpecies(catalog);

		const factories = {
			species,
			crop: () => modules.crop.sceneCreateCrop(THREE, addons, geometry, sceneBuildModel, species().woody),
			fruit: () => modules.fruit.sceneCreateFruit(THREE, geometry, species().woody),
			stage: () => modules.stage.sceneCreateStage(),
			figure: () => modules.figure.sceneCreateFigure(THREE, addons, geometry, sceneBuildModel),
			// Un bâtiment téléversé se monte comme un modèle cuit, à côté de la composition qu'il remplace. L'index d'un envoi du décor
			// range son modèle sous sa famille (`base/scene/upload/decor.json`) : un élément d'Eau, de Nature ou de Matériel passe la sienne
			building: () => ({
				...modules.building.sceneCreateBuilding(THREE, geometry, choices),
				uploaded: (index, buffer, family = 'building') => sceneBuildModel(THREE, { index, buffer }, family)
			})
		};

		const result = { THREE, addons, baked };

		for(const name of names) {

			const [module, key] = name.split('.');

			// hasOwn : un nom hérité d'Object.prototype (`toString`, `constructor`) passerait sinon pour une clé du contrat
			if(Object.hasOwn(factories, module) === false) {
				throw new Error('SceneLoader : module inconnu dans '+ name);
			}

			created[module] ??= factories[module]();
			result[module] ??= {};

			if(Object.hasOwn(created[module], key) === false) {
				throw new Error('SceneLoader : nom inconnu '+ name);
			}

			result[module][key] = created[module][key];

		}

		return result;

	}

}
