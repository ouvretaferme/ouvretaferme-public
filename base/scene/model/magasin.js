// Magasin de vente à la ferme : petite construction en bois clair sous la tuile, une porte vitrée encadrée de volets, deux vitrines et l'ardoise des prix posée contre le mur.
// Engendré par le skill 3d-batiment depuis la fiche qu'il embarque : il se retouche par sa fiche, puis
// node .claude/skills/3d-batiment/livrer.mjs <ce module> <ce module>, jamais à la main.
const FICHE = {
	"nom": "magasin",
	"description": "Magasin de vente à la ferme : petite construction en bois clair sous la tuile, une porte vitrée encadrée de volets, deux vitrines et l'ardoise des prix posée contre le mur.",
	"corps": {
		"toit": "deux-pans",
		"largeur": 7,
		"profondeur": 10,
		"egout": 3,
		"faitage": 5.6,
		"socle": 0.2,
		"fonde": "boutique paysanne de 70 m², pignon sur la cour"
	},
	"mur": {
		"catalogue": "bardage-bois",
		"reglages": {
			"couleur": "bois",
			"joints": "boisSombre",
			"pas": 0.9,
			"facades": [
				"gauche",
				"droite"
			]
		},
		"fonde": "bardage mélèze"
	},
	"couverture": {
		"catalogue": "tuile",
		"fonde": "tuile mécanique",
		"reglages": {
			"gouttiere": "gris"
		}
	},
	"ouvertures": [
		{
			"catalogue": "porte-vitree",
			"facade": "avant",
			"u": 0,
			"reglages": {
				"largeur": 1.2,
				"hauteur": 2.2,
				"couleur": "feuilleSombre",
				"volets": "feuilleSombre"
			},
			"fonde": "porte vitrée verte, volets ouverts"
		},
		{
			"catalogue": "fenetre",
			"facade": "avant",
			"u": -2.6,
			"appui": 0.7,
			"reglages": {
				"largeur": 1.2,
				"hauteur": 1.4,
				"bois": 1,
				"cadre": "feuilleSombre"
			},
			"fonde": "vitrine gauche"
		},
		{
			"catalogue": "fenetre",
			"facade": "avant",
			"u": 2.6,
			"appui": 0.7,
			"reglages": {
				"largeur": 1.2,
				"hauteur": 1.4,
				"bois": 1,
				"cadre": "feuilleSombre"
			},
			"fonde": "vitrine droite"
		},
		{
			"catalogue": "fenetre",
			"facade": "droite",
			"u": 0,
			"appui": 1,
			"reglages": {
				"largeur": 1.6,
				"hauteur": 1,
				"cadre": "feuilleSombre"
			},
			"fonde": "jour sur le flanc"
		}
	],
	"elements": [
		{
			"catalogue": "auvent",
			"reglages": {
				"facade": "avant",
				"u": 0,
				"largeur": 6,
				"hauteur": 2.3,
				"avancee": 1.5,
				"couleur": "feuilleSombre"
			},
			"fonde": "l'auvent de toile verte sur toute la façade de vente"
		},
		{
			"catalogue": "caisses",
			"reglages": {
				"facade": "avant",
				"u": -2.6,
				"recul": 0.95,
				"colonnes": 3,
				"table": 0.8,
				"couleur": "bois",
				"couleurTable": "boisSombre",
				"contenus": [
					"tuile",
					"feuilleSombre",
					"tuile"
				]
			},
			"fonde": "l'étal de légumes sous la vitrine gauche"
		},
		{
			"catalogue": "caisses",
			"reglages": {
				"facade": "avant",
				"u": 2.6,
				"recul": 0.95,
				"colonnes": 2,
				"table": 0.8,
				"couleur": "bois",
				"couleurTable": "boisSombre",
				"contenus": [
					"feuilleSombre",
					"tuile"
				]
			},
			"fonde": "et sous la vitrine droite"
		}
	]
};

// Les bâtiments du socle : le cadre qui monte un bâtiment d'après sa fiche, et les trois compositions qu'une structure règle
// dans l'éditeur du décor (un bâtiment, le hangar du catalogue retouché, une maison).
// Le skill 3d-batiment monte ses modèles par ce cadre, et ses modules engendrés en embarquent une copie qu'il refait à chaque
// livraison : ce fichier est la seule source. Il ne se retouche qu'avec une relivraison des modules engendrés.
// Module ES sans import : three et les pièces de géométrie (`facets`, `merge`) sont passés en argument, ceux du socle
// (geometry.js) par SceneLoader, ceux de base/scene/model/helpers.js par un module engendré, qui sont les mêmes.
// Une fiche parle en mètres. Le faitage court selon Z, la façade avant regarde +Z, le pied est à y = 0.

function sceneCreateBuilding(THREE, geometry, choices = null) {

	const { facets, merge } = geometry;

	// La palette de base/scene/model/helpers.js, dont le cadre tire chaque couleur ; test/BuildingAi.t.php la compare à celle-ci
	const P = {
		creme: '#efe3cc', pierre: '#d5c6ab', bois: '#c79a62', boisSombre: '#6f4b32', tole: '#aab3b8', toleSombre: '#6f7a80',
		tuile: '#b9553a', ardoise: '#55677a', vitre: '#9fd0dc', encre: '#1d4b53', rouge: '#d6452f', bleu: '#3b6fb5',
		blanc: '#f6f3ea', noir: '#2c3136', or: '#f2b632', orSombre: '#c98a12', paille: '#e2b74e', peau: '#f0c9a0',
		peauSombre: '#b98058', herbe: '#8fbf72', feuille: '#4d9a4a', feuilleSombre: '#2f7d3d', feuilleClaire: '#9ccc65',
		terre: '#9a6b48', route: '#dccfae', gris: '#8d9499',
	};

	// Les pièces en boites franches de helpers.js, à l'identique : un modèle engendré se cuit par elles au même octet
	const box = (x, y, z, sx, sy, sz) => facets(new THREE.BoxGeometry(sx, sy, sz).translate(x, y, z));
	const block = (x, y0, z, sx, sy, sz) => box(x, y0 + sy / 2, z, sx, sy, sz);
	const prism = (points, depth, z = 0) => {
		const shape = new THREE.Shape(points.map(([x, y]) => new THREE.Vector2(x, y)));
		return facets(new THREE.ExtrudeGeometry(shape, { depth, bevelEnabled: false }).translate(0, 0, z - depth / 2));
	};
	const collector = () => {
		const parts = [];
		const add = (piece, color, name) => {
			const part = { geometry: piece, color, sway: 0 };
			if(name !== undefined) {
				part.name = name;
			}
			parts.push(part);
			return piece;
		};
		return { parts, add };
	};
	const measure = parts => {
		const bounds = new THREE.Box3();
		for(const { geometry: piece } of parts) {
			piece.computeBoundingBox();
			bounds.union(piece.boundingBox);
		}
		const size = bounds.getSize(new THREE.Vector3());
		return { d: Math.max(size.x, size.z), h: bounds.max.y };
	};

	const match = (valeur, table) => {
		if(table[valeur] === undefined) {
			throw new Error('Valeur inattendue : ' + valeur);
		}
		return table[valeur];
	};

	// ---------- Le catalogue ----------
	// Ce qu'une fiche peut citer, famille par famille. Une entrée fixe sa forme, et ses réglages sont ce que la photo règle, avec
	// leur défaut. Une couleur est une clé de P, ou null pour retirer la pièce qu'elle colore. Aucune entrée ne dépend d'un bâtiment.

	const BORNES_CORPS = {
		largeur: [2, 30],
		profondeur: [2, 60],
		egout: [1.8, 12],
		faitage: [1.8, 20],
		socle: [0, 1],
	};

	const TOITS = ['deux-pans', 'appentis', 'croupe'];

	const FACADES = ['avant', 'arriere', 'gauche', 'droite'];

	// Les moellons d'un mur de pierre, deux triangles chacun : la part du plafond de 1 500 triangles d'un bâtiment qu'ils peuvent
	// prendre sans qu'aucune combinaison de l'éditeur le dépasse, ce que test/BuildingAi.t.php éprouve
	const MOELLONS_MAX = 220;

	const CATALOGUE = {

		mur: {
			'bardage-bois': {
				decrit: "planches verticales claires rythmées de couvre-joints sombres",
				reglages: { couleur: 'bois', joints: 'boisSombre', pas: 1, largeurJoint: .14, saillie: .06, facades: ['gauche', 'droite'], soubassement: 0, couleurSoubassement: 'gris' },
			},
			'bardage-tole': {
				decrit: "tôle nervurée, rendue en larges couvre-joints de la couleur du mur, sur un soubassement de béton",
				reglages: { couleur: 'pierre', joints: 'pierre', pas: 1.6, largeurJoint: .22, saillie: .1, facades: ['avant', 'arriere', 'gauche', 'droite'], soubassement: .55, couleurSoubassement: 'gris' },
			},
			'crepi': {
				decrit: "un enduit lisse, sans relief",
				reglages: { couleur: 'creme', soubassement: 0, couleurSoubassement: 'gris' },
			},
			'pierre': {
				decrit: "un mur de pierre, lu à distance par ses moellons serrés en quinconce et ses chaines d'angle posées en harpe, d'une teinte qui tranche sur le mur",
				reglages: { couleur: 'pierre', angles: 'gris', moellons: 'gris', pasMoellons: 1.1, assise: .5, soubassement: 0, couleurSoubassement: 'gris' },
			},
		},

		couverture: {
			'tole': {
				decrit: "des plaques de tôle sombre en un ou plusieurs rangs, et une faitière claire",
				reglages: { couleur: 'toleSombre', faitiere: 'tole', rangs: 1, debord: .2, rive: .2, sousPlaque: null, gouttiere: null, pannes: null },
			},
			'tuile': {
				decrit: "deux rangs de tuile sur une plaque grise qui déborde, une gouttière blanche sous l'égout",
				reglages: { couleur: 'tuile', faitiere: 'tuile', rangs: 2, debord: .3, rive: .45, sousPlaque: 'gris', gouttiere: 'blanc', pannes: 'boisSombre' },
			},
			'ardoise': {
				decrit: "l'ardoise bleu gris en rangs fins, la faitière de la même teinte",
				reglages: { couleur: 'ardoise', faitiere: 'ardoise', rangs: 3, debord: .25, rive: .25, sousPlaque: null, gouttiere: 'gris', pannes: null },
			},
			'panneaux-solaires': {
				decrit: "une tôle claire couverte de panneaux sombres en grille, un jour entre chaque panneau, sur les pans qu'on lui donne",
				reglages: { couleur: 'tole', faitiere: 'tole', rangs: 1, debord: .3, rive: .3, sousPlaque: null, gouttiere: null, pannes: null, panneaux: 'encre', colonnes: 8, lignes: 3, pans: ['droite'] },
			},
		},

		ouverture: {
			'porte-pleine': {
				decrit: "une porte de bois pleine, à taille d'homme",
				porte: true,
				reglages: { largeur: 1.1, hauteur: 1.75, couleur: 'boisSombre' },
			},
			'porte-vitree': {
				decrit: "une porte claire vitrée, ses petits bois, et ses volets ouverts à plat sur le mur",
				porte: true,
				reglages: { largeur: 1.1, hauteur: 2.1, couleur: 'blanc', bois: 2, volets: 'blanc' },
			},
			'porte-coulissante': {
				decrit: "deux vantaux clairs sous un rail qui déborde, que la scène peut faire coulisser",
				porte: true,
				reglages: { largeur: 4.4, hauteur: 4.2, couleur: 'creme', fond: 'bois', rail: 'bois', montant: 'tuile' },
			},
			'baie': {
				decrit: "une large ouverture sans porte, l'intérieur dans l'ombre, partagée par ses poteaux, sur une lisse basse",
				reglages: { largeur: 6, hauteur: 2.6, fond: 'noir', poteaux: 2, couleurPoteaux: 'boisSombre', lisse: 1, couleurLisse: 'bois' },
			},
			'fenetre': {
				decrit: "un vitrage sur un appui de béton, ses petits bois, et des volets ouverts, d'un seul côté ou clos",
				appui: 1,
				reglages: { largeur: .6, hauteur: .6, appui: 'gris', bois: 0, cadre: 'blanc', volets: 'aucun', couleurVolets: 'blanc' },
			},
		},

		element: {
			'cheminee': {
				decrit: "un conduit carré sur le toit, coiffé d'un chapeau",
				reglages: { x: 0, z: 0, couleur: 'creme', chapeau: 'gris', cote: .5, hauteur: .95 },
			},
			'descente': {
				decrit: "la descente d'eau à un angle, du toit au sol",
				reglages: { facade: 'avant', cote: 'gauche', couleur: 'gris' },
			},
			'bac': {
				decrit: "un bac de lavage en béton sur deux plots, son eau et son robinet, contre une façade",
				reglages: { facade: 'avant', u: 0, longueur: 2.3, couleur: 'gris' },
			},
			'lanterneau': {
				decrit: "le faitage ouvert sur sa longueur, couvert d'un chapeau qui laisse passer l'air, d'une étable ou d'une bergerie",
				reglages: { couleur: 'tole', fond: 'noir', longueur: 0.8 },
			},
			'bottes': {
				decrit: "des balles rondes de foin ou de paille en rangs couchés, posées au sol, empilées en pyramide",
				reglages: { x: 0, z: 0, rangs: 2, colonnes: 4, diametre: 1.5, largeur: 1.2, couleur: 'paille', sens: 'x' },
			},
			'ruches': {
				decrit: "des ruches Dadant alignées sur une poutre, leur toit de tôle et leur entrée sombre tournée vers l'avant",
				reglages: { facade: 'avant', u: 0, nombre: 4, ecart: .9, recul: 1.4, couleur: 'blanc', toit: 'tole', support: 'boisSombre' },
			},
			'groupe-froid': {
				decrit: "le groupe frigorifique accroché au mur, sa grille sombre et son ventilateur",
				reglages: { facade: 'droite', u: 0, hauteur: 1.6, couleur: 'blanc', grille: 'noir', helice: 'gris' },
			},
			'roues': {
				decrit: "le bâtiment sur un châssis à roues, que le tracteur déplace",
				reglages: { essieux: 2, diametre: .8, couleur: 'noir', chassis: 'gris', timon: 'gris' },
			},
			'betes': {
				decrit: "des bêtes devant une façade, la tête tournée vers le mur où elles mangent : vaches, brebis ou poules",
				reglages: { facade: 'avant', u: 0, recul: 1.2, nombre: 3, ecart: 1.4, espece: 'vache', robe: 'blanc', tache: 'noir', tete: 'noir' },
			},
			'barriere': {
				decrit: "le parc devant une façade, ses poteaux et ses deux lisses sur trois côtés",
				reglages: { facade: 'avant', u: 0, largeur: 6, profondeur: 4, hauteur: 1.1, couleur: 'gris' },
			},
			'caisses': {
				decrit: "des caisses de légumes pleines contre une façade, posées au sol ou sur un étal",
				reglages: { facade: 'avant', u: 0, recul: .5, colonnes: 3, rangs: 2, couleur: 'bois', contenus: ['tuile'], table: 0, couleurTable: 'boisSombre' },
			},
			'auvent': {
				decrit: "un auvent de toile au-dessus d'une façade, son lambrequin droit au bord",
				reglages: { facade: 'avant', u: 0, largeur: 3, hauteur: 2.4, avancee: 1.4, couleur: 'feuilleSombre' },
			},
			'pondoirs': {
				decrit: "la rangée de pondoirs en saillie sur un flanc de poulailler, leur couvercle en pente qui s'ouvre du dehors",
				reglages: { facade: 'droite', u: 0, longueur: 3, hauteur: .6, couleur: 'bois', couvercle: 'boisSombre' },
			},
			'panneau': {
				decrit: "un vieux panneau posé contre le mur, une bande encore claire",
				reglages: { facade: 'avant', u: 0, largeur: 2.5, hauteur: 1, couleur: 'tuile', bande: 'creme' },
			},
		},

	};

	// ---------- La fiche ----------
	// Une fiche se résout contre le catalogue : chaque entrée citée reçoit ses défauts, chaque valeur ses bornes, et chaque ouverture
	// doit tenir dans sa façade sans en chevaucher une autre. resoudre() lève une seule erreur qui nomme tous les écarts, pour
	// qu'une fiche se corrige en un tour et non un écart à la fois.

	// Les réglages qui placent, seuls à pouvoir être négatifs : une taille ne l'est jamais
	const POSITIONS = ['x', 'z', 'u'];

	const CORPS = { toit: 'deux-pans', largeur: 3, profondeur: 4, egout: 2.1, faitage: 2.8, socle: .2, couleurSocle: 'gris', haut: 'gauche' };

	// Le volume nu, partagé par la résolution et par le cadre : la largeur d'une façade, la hauteur du mur à l'abscisse u de cette
	// façade, vue de dehors, et la hauteur du toit au point (x, z)
	const volume = corps => {
		const { toit, largeur: W, profondeur: D, egout, faitage } = corps;
		const montee = faitage - egout;
		// Pour un appentis, le côté haut est à gauche (-X) ou à droite (+X)
		const sens = corps.haut === 'droite' ? 1 : -1;
		const toitA = (x, z) => {
			if(toit === 'deux-pans') {
				return egout + montee * (1 - Math.abs(x) / (W / 2));
			}
			if(toit === 'appentis') {
				return egout + montee * (.5 + sens * x / W);
			}
			return egout + montee * Math.min(1, 1 - Math.abs(x) / (W / 2), (D / 2 - Math.abs(z)) / (W / 2));
		};
		// Le point de la façade à l'abscisse u, dans le plan du mur
		const point = (facade, u) => match(facade, {
			avant: [u, D / 2],
			arriere: [-u, -D / 2],
			droite: [W / 2, -u],
			gauche: [-W / 2, u],
		});
		const largeurFacade = facade => (facade === 'avant' || facade === 'arriere') ? W : D;
		const hautMur = (facade, u) => {
			const [x, z] = point(facade, u);
			return toit === 'croupe' ? egout : toitA(x, z);
		};
		return { W, D, toitA, point, largeurFacade, hautMur };
	};

	// L'étendue d'une ouverture sur sa façade, volets, rail et cadre compris : [gauche, droite, bas, haut], depuis le centre de la façade
	const etendue = (ouverture, socle) => {
		const r = ouverture.reglages;
		const bas = ouverture.appui ?? socle;
		let cote = r.largeur / 2;
		if(ouverture.catalogue === 'porte-vitree' && r.volets !== null) {
			cote += .08 + .58;
		}
		else if(ouverture.catalogue === 'porte-coulissante') {
			cote += .35 + (r.montant === null ? 0 : .3);
		}
		else if(ouverture.catalogue === 'fenetre' && r.volets !== 'aucun' && r.volets !== 'clos') {
			cote += .05 + (r.volets === 'paire' ? r.largeur * .42 : r.largeur);
		}
		if(ouverture.catalogue === 'baie') {
			cote += .12;
		}
		const haut = bas + r.hauteur + (ouverture.catalogue === 'porte-coulissante' ? .22 : 0);
		return [ouverture.u - cote, ouverture.u + cote, bas, haut];
	};

	const resoudre = fiche => {
		const ecarts = [];
		const corps = { ...CORPS, ...fiche.corps };
		delete corps.fonde;

		for(const cle of Object.keys(fiche.corps ?? {})) {
			if(cle !== 'fonde' && CORPS[cle] === undefined) {
				ecarts.push('corps.' + cle + ' : clé inconnue, les clés sont ' + Object.keys(CORPS).join(', '));
			}
		}
		if(TOITS.includes(corps.toit) === false) {
			ecarts.push('corps.toit vaut ' + corps.toit + ', les toits sont ' + TOITS.join(', '));
		}
		for(const [cle, [min, max]] of Object.entries(BORNES_CORPS)) {
			if(typeof corps[cle] !== 'number' || corps[cle] < min || corps[cle] > max) {
				ecarts.push('corps.' + cle + ' vaut ' + corps[cle] + ', hors de [' + min + ', ' + max + ']');
			}
		}
		if(corps.faitage <= corps.egout) {
			ecarts.push('corps.faitage (' + corps.faitage + ') doit passer au-dessus de corps.egout (' + corps.egout + ')');
		}
		if(corps.toit === 'croupe' && corps.profondeur < corps.largeur) {
			ecarts.push('un toit en croupe court selon la profondeur, qui doit valoir au moins la largeur');
		}

		// Une entrée citée : son catalogue existe dans la famille, ses réglages sont connus et prennent leur défaut
		const entree = (famille, citee, chemin) => {
			const modele = CATALOGUE[famille][citee?.catalogue];
			if(modele === undefined) {
				ecarts.push(chemin + '.catalogue vaut ' + citee?.catalogue + ', la famille ' + famille + ' porte ' + Object.keys(CATALOGUE[famille]).join(', '));
				return null;
			}
			const reglages = { ...modele.reglages };
			for(const [cle, valeur] of Object.entries(citee.reglages ?? {})) {
				if(cle in modele.reglages === false) {
					ecarts.push(chemin + '.reglages.' + cle + ' : réglage inconnu de ' + citee.catalogue + ', qui porte ' + Object.keys(modele.reglages).join(', '));
				}
				else if(typeof modele.reglages[cle] === 'number' && (typeof valeur !== 'number' || (valeur < 0 && POSITIONS.includes(cle) === false))) {
					ecarts.push(chemin + '.reglages.' + cle + ' vaut ' + valeur + ', un nombre positif est attendu');
				}
				else {
					reglages[cle] = valeur;
				}
			}
			return { catalogue: citee.catalogue, modele, reglages };
		};

		const mur = entree('mur', fiche.mur, 'mur');
		const couverture = entree('couverture', fiche.couverture, 'couverture');
		const v = volume(corps);

		const ouvertures = [];
		(fiche.ouvertures ?? []).forEach((citee, i) => {
			const chemin = 'ouvertures[' + i + ']';
			const resolue = entree('ouverture', citee, chemin);
			if(resolue === null) {
				return;
			}
			if(FACADES.includes(citee.facade) === false) {
				ecarts.push(chemin + '.facade vaut ' + citee.facade + ', les façades sont ' + FACADES.join(', '));
				return;
			}
			const ouverture = {
				...resolue, facade: citee.facade, u: citee.u ?? 0,
				appui: resolue.modele.appui === undefined ? null : (citee.appui ?? resolue.modele.appui),
			};
			const [gauche, droite, bas, haut] = etendue(ouverture, corps.socle);
			const demi = v.largeurFacade(ouverture.facade) / 2 - .1;
			if(gauche < -demi || droite > demi) {
				ecarts.push(chemin + ' (' + ouverture.catalogue + ') déborde de la façade ' + ouverture.facade + ' : de ' + gauche.toFixed(2) + ' à ' + droite.toFixed(2) + ' pour ±' + demi.toFixed(2));
			}
			const plafond = Math.min(v.hautMur(ouverture.facade, gauche), v.hautMur(ouverture.facade, droite)) - .1;
			if(haut > plafond) {
				ecarts.push(chemin + ' (' + ouverture.catalogue + ') monte à ' + haut.toFixed(2) + ' m, le mur s\'arrête à ' + plafond.toFixed(2) + ' m au-dessus d\'elle');
			}
			for(const autre of ouvertures) {
				const [g2, d2, b2, h2] = etendue(autre, corps.socle);
				if(autre.facade === ouverture.facade && gauche < d2 && g2 < droite && bas < h2 && b2 < haut) {
					ecarts.push(chemin + ' (' + ouverture.catalogue + ') chevauche ouvertures[' + autre.indice + '] (' + autre.catalogue + ') sur la façade ' + ouverture.facade);
				}
			}
			ouverture.indice = i;
			ouvertures.push(ouverture);
		});

		// La scène lit le devant d'un bâtiment, et le seuil où l'on entre, sur la façade avant
		const portes = ouvertures.filter(ouverture => ouverture.modele.porte === true);
		if(portes.length > 0 && portes.some(ouverture => ouverture.facade === 'avant') === false) {
			ecarts.push('aucune porte sur la façade avant, où la scène cherche le seuil : la porte principale va en avant, quitte à tourner le bâtiment');
		}

		const elements = [];
		(fiche.elements ?? []).forEach((citee, i) => {
			const resolue = entree('element', citee, 'elements[' + i + ']');
			if(resolue !== null) {
				if('facade' in resolue.reglages && FACADES.includes(resolue.reglages.facade) === false) {
					ecarts.push('elements[' + i + '].reglages.facade vaut ' + resolue.reglages.facade + ', les façades sont ' + FACADES.join(', '));
					return;
				}
				elements.push(resolue);
			}
		});

		if(ecarts.length > 0) {
			throw new Error('La fiche ' + (fiche.nom ?? 'sans nom') + ' ne se résout pas :\n- ' + ecarts.join('\n- '));
		}
		return { nom: fiche.nom, corps, mur, couverture, ouvertures, elements, volume: v };
	};

	// ---------- Le cadre ----------
	// Il monte, d'après une fiche résolue, le volume, son mur, sa couverture, ses ouvertures et ses éléments, et rend le contrat
	// d'un modèle de base/scene. Chaque pièce se dessine par sa fabrique, d'après son entrée du catalogue et ses réglages.

	const construire = fiche => {
		const { parts, add } = collector();
		const { corps, mur, couverture, ouvertures, elements, volume: v } = resoudre(fiche);
		const { W, D } = v;
		const { egout, faitage, socle } = corps;

		const teinte = cle => {
			if(typeof cle === 'string' && cle.startsWith('#')) {
				return cle;
			}
			if(P[cle] === undefined) {
				throw new Error('Couleur inconnue de la palette : ' + cle + ', la palette porte ' + Object.keys(P).join(', '));
			}
			return P[cle];
		};
		// Une couleur réglée à null retire la pièce qu'elle colore
		const poser = (geometrie, cle, nom) => {
			if(cle !== null) {
				add(geometrie, teinte(cle), nom);
			}
		};

		// Une plaque sur une façade : centrée en u, de y0 à y0 + h, d'épaisseur e, son milieu à dz devant le plan du mur
		const ANGLE = { avant: 0, droite: Math.PI / 2, arriere: Math.PI, gauche: -Math.PI / 2 };
		const demi = facade => (facade === 'avant' || facade === 'arriere') ? D / 2 : W / 2;
		const plaque = (facade, u, y0, l, h, e, dz) => facets(new THREE.BoxGeometry(l, h, e).translate(u, y0 + h / 2, demi(facade) + dz).rotateY(ANGLE[facade]));
		// Une géométrie posée devant une façade : construite dans le repère de la façade, u le long du mur, dz vers le dehors
		const surFacade = (facade, geometrie, u, y, dz) => facets(geometrie.translate(u, y, demi(facade) + dz).rotateY(ANGLE[facade]));
		const versMonde = (facade, u, y, dz) => new THREE.Vector3(u, y, demi(facade) + dz).applyAxisAngle(new THREE.Vector3(0, 1, 0), ANGLE[facade]).toArray().map(n => Math.round(n * 1000) / 1000);

		// Ce qui reste libre de [y0, y1] à l'abscisse u d'une façade, une fois retirées les ouvertures qui passent là, marge comprise
		const libreVertical = (facade, u, y0, y1, marge) => {
			let segments = [[y0, y1]];
			for(const ouverture of ouvertures.filter(ouverture => ouverture.facade === facade)) {
				const [gauche, droite, bas, haut] = etendue(ouverture, socle);
				if(u > gauche - marge && u < droite + marge) {
					segments = segments.flatMap(([a, b]) => [[a, Math.min(b, bas - marge)], [Math.max(a, haut + marge), b]]).filter(([a, b]) => b - a > .3);
				}
			}
			return segments;
		};
		const libreHorizontal = (facade, u0, u1, y0, y1) => {
			let segments = [[u0, u1]];
			for(const ouverture of ouvertures.filter(ouverture => ouverture.facade === facade)) {
				const [gauche, droite, bas, haut] = etendue(ouverture, socle);
				if(bas < y1 && haut > y0) {
					segments = segments.flatMap(([a, b]) => [[a, Math.min(b, gauche)], [Math.max(a, droite), b]]).filter(([a, b]) => b - a > .05);
				}
			}
			return segments;
		};

		// Le volume
		const m = mur.reglages;
		if(corps.toit === 'deux-pans') {
			add(prism([[-W / 2, 0], [W / 2, 0], [W / 2, egout], [0, faitage], [-W / 2, egout]], D), teinte(m.couleur));
		}
		else if(corps.toit === 'appentis') {
			add(prism([[-W / 2, 0], [W / 2, 0], [W / 2, v.toitA(W / 2, 0)], [-W / 2, v.toitA(-W / 2, 0)]], D), teinte(m.couleur));
		}
		else {
			add(block(0, 0, 0, W, egout, D), teinte(m.couleur));
		}
		if(socle > 0) {
			poser(block(0, 0, 0, W + .12, socle, D + .12), corps.couleurSocle);
		}
		// Le soubassement s'interrompt aux portes, qui descendent au sol
		if(m.soubassement > 0) {
			for(const facade of ['avant', 'arriere', 'gauche', 'droite']) {
				const bout = v.largeurFacade(facade) / 2 + (facade === 'avant' || facade === 'arriere' ? .1 : 0);
				for(const [a, b] of libreHorizontal(facade, -bout, bout, 0, m.soubassement)) {
					poser(plaque(facade, (a + b) / 2, 0, b - a, m.soubassement, .2, 0), m.couleurSoubassement);
				}
			}
		}

		// Le relief du mur
		if(mur.catalogue === 'bardage-bois' || mur.catalogue === 'bardage-tole') {
			const pied = Math.max(socle, m.soubassement);
			for(const facade of m.facades) {
				const bord = v.largeurFacade(facade) / 2 - .4;
				const n = Math.ceil(bord / m.pas);
				for(let i = -n; i < n; i++) {
					// Décalés d'un demi-pas : aucun ne tombe dans l'axe du faitage, où il ferait un poinçon de charpente
					const u = (i + .5) * m.pas;
					if(Math.abs(u) > bord) {
						continue;
					}
					for(const [a, b] of libreVertical(facade, u, pied, v.hautMur(facade, u) - .12, .15)) {
						poser(plaque(facade, u, a, m.largeurJoint, b - a, m.saillie * 2, 0), m.joints);
					}
				}
			}
		}
		else if(mur.catalogue === 'pierre') {
			// La pierre se lit par la couleur seule : l'éclairage en aplats de la scène rend un relief de quelques centimètres de la
			// même teinte que le mur. Chaine et moellons sont donc des faces posées à e devant le mur, deux triangles chacune, et
			// non des boites, qui en coutent douze pour un relief que personne ne voit
			const pied = Math.max(socle, m.soubassement), e = .03;
			const face = (l, h) => facets(new THREE.PlaneGeometry(l, h));
			// Les pierres d'une même couleur partent en une seule pièce : l'aperçu de l'éditeur monte un maillage par pièce, et
			// remonte chaque vignette à chaque choix
			const pierres = new Map();
			const tailler = (geometrie, cle) => {
				if(cle !== null) {
					pierres.set(cle, [...(pierres.get(cle) ?? []), geometrie]);
				}
			};
			// Les chaines d'angle : une pierre par assise, alternativement longue sur une face et courte sur l'autre, chaque face
			// prolongée de e pour fermer l'arête
			const assises = Math.floor((egout - pied) / m.assise);
			for(const [sx, sz] of [[-1, 1], [1, 1], [1, -1], [-1, -1]]) {
				for(let i = 0; i < assises; i++) {
					const [lx, lz] = i % 2 === 0 ? [.55, .3] : [.3, .55];
					const y = pied + (i + .5) * m.assise, h = m.assise - .06;
					tailler(face(lx + e, h).rotateY(sz > 0 ? 0 : Math.PI).translate(sx * (W / 2 + e - (lx + e) / 2), y, sz * (D / 2 + e)), m.angles);
					tailler(face(lz + e, h).rotateY(sx * Math.PI / 2).translate(sx * (W / 2 + e), y, sz * (D / 2 + e - (lz + e) / 2)), m.angles);
				}
			}
			// Les moellons, en quinconce d'une assise à l'autre, jusque dans les pignons, hors des chaines et des ouvertures. Les
			// longueurs et les décalages suivent des suites fixes, pour qu'un bâtiment se monte toujours pareil. Leur nombre est
			// borné pour tenir le plafond de triangles d'un bâtiment : un grand mur les espace au lieu d'en porter davantage
			if(m.moellons !== null) {
				const hauteur = m.assise * .62, longueurs = [.6, .8, .5, .7], rangs = Math.floor((faitage - pied) / m.assise);
				const placer = pas => {
					const places = [];
					for(const facade of FACADES) {
						const bord = v.largeurFacade(facade) / 2 - .75, n = Math.ceil(bord / pas) + 1;
						for(let i = 0; i < rangs; i++) {
							const y = pied + (i + .5) * m.assise, bas = y - hauteur / 2, haut = y + hauteur / 2;
							for(let k = -n; k <= n; k++) {
								const l = longueurs[((i * 3 + k) % 4 + 4) % 4];
								const u = (k + (i % 2) * .5 + (((i * 7 + k * 3) % 5 + 5) % 5 - 2) * .08) * pas;
								const [g, d] = [u - l / 2, u + l / 2];
								if(g < -bord || d > bord || haut > Math.min(v.hautMur(facade, g), v.hautMur(facade, d)) - .2) {
									continue;
								}
								if(libreHorizontal(facade, g - .12, d + .12, bas - .1, haut + .1).some(([a, b]) => a <= g - .12 && b >= d + .12)) {
									places.push([facade, u, y, l]);
								}
							}
						}
					}
					return places;
				};
				let pas = m.pasMoellons, places = placer(pas);
				while(places.length > MOELLONS_MAX) {
					pas *= 1.15;
					places = placer(pas);
				}
				for(const [facade, u, y, l] of places) {
					tailler(surFacade(facade, face(l, hauteur), u, y, e), m.moellons);
				}
			}
			for(const [cle, geometries] of pierres) {
				poser(merge(geometries), cle);
			}
		}

		// La couverture. Un pan part de son point haut (x0, y0) et descend vers side ; s se mesure le long de la pente
		const c = couverture.reglages;
		const montee = faitage - egout;
		let haut = faitage;
		const pan = (side, x0, pente, s0, s1, longueur, nomPan) => {
			const surPente = (s, lift, sx, sy, sz, tilt = 0) => facets(new THREE.BoxGeometry(sx, sy, sz).rotateZ(-side * (pente - tilt))
				.translate(x0 + side * (s * Math.cos(pente) + lift * Math.sin(pente)), faitage - s * Math.sin(pente) + lift * Math.cos(pente), 0));
			const l = s1 - s0;
			if(c.sousPlaque !== null) {
				poser(surPente(s0 + l / 2, .04, l, .08, longueur), c.sousPlaque);
			}
			const lift = c.sousPlaque === null ? .07 : .16, rang = l / c.rangs;
			for(let i = 0; i < c.rangs; i++) {
				// Chaque rang un peu moins pentu que le pan, pour que celui du haut recouvre celui du bas en marche
				poser(surPente(s0 + (i + .5) * rang, lift, rang + (c.rangs > 1 ? .2 : 0), .12, c.sousPlaque === null ? longueur : D + .3, c.rangs > 1 ? .05 : 0), c.couleur);
			}
			// Les panneaux en grille, un jour entre chacun pour que la grille se lise sur la tôle
			if(couverture.catalogue === 'panneaux-solaires' && c.panneaux !== null && c.pans.includes(nomPan)) {
				const marge = .3, pasS = (l - marge * 2) / c.lignes, pasZ = (D - marge * 2) / c.colonnes;
				for(let i = 0; i < c.lignes; i++) {
					for(let j = 0; j < c.colonnes; j++) {
						const panneau = surPente(s0 + marge + (i + .5) * pasS, lift + .1, pasS - .14, .06, pasZ - .14);
						poser(panneau.translate(0, 0, -D / 2 + marge + (j + .5) * pasZ), c.panneaux);
					}
				}
			}
			if(c.gouttiere !== null) {
				poser(box(x0 + side * (s1 * Math.cos(pente) + .06), faitage - s1 * Math.sin(pente) - .06, 0, .18, .16, longueur), c.gouttiere);
			}
			return lift;
		};
		if(corps.toit === 'deux-pans') {
			const pente = Math.atan2(montee, W / 2), s1 = (W / 2 + c.debord) / Math.cos(pente);
			let lift = 0;
			for(const side of [-1, 1]) {
				lift = pan(side, 0, pente, 0, s1, D + c.rive * 2, side < 0 ? 'gauche' : 'droite');
			}
			haut = faitage + lift + .17;
			poser(box(0, haut - .05, 0, .34, .1, D + c.rive * 2 + .04), c.faitiere);
		}
		else if(corps.toit === 'appentis') {
			const sens = corps.haut === 'droite' ? 1 : -1, pente = Math.atan2(montee, W);
			const lift = pan(-sens, sens * W / 2, pente, -c.debord / Math.cos(pente), (W + c.debord) / Math.cos(pente), D + c.rive * 2, sens < 0 ? 'droite' : 'gauche');
			haut = faitage + c.debord * Math.tan(pente) + lift + .1;
			poser(box(sens * (W / 2 + c.debord * .9), haut - .06, 0, .3, .12, D + c.rive * 2 + .04), c.faitiere);
		}
		else {
			haut = croupe(W, D, egout, montee, c.debord, geometrie => poser(geometrie, c.couleur)) + .02;
			poser(box(0, haut, 0, .3, .1, Math.max(D - W, 0) + .3), c.faitiere);
		}
		if(c.pannes !== null && corps.toit !== 'croupe') {
			// Les bouts de pannes sous le débord des deux rives
			for(const bout of [-1, 1]) {
				for(const x of [-W * .36, 0, W * .36]) {
					poser(box(x, v.toitA(x, 0) - .14, bout * (D / 2 + c.rive / 2), .16, .2, c.rive), c.pannes);
				}
			}
		}

		// Les ouvertures
		const ancres = {};
		for(const ouverture of ouvertures) {
			const { facade, u } = ouverture, r = ouverture.reglages, l = r.largeur, h = r.hauteur;
			if(ouverture.catalogue === 'porte-pleine') {
				poser(plaque(facade, u, socle, l, h, .1, .04), r.couleur);
			}
			else if(ouverture.catalogue === 'porte-vitree') {
				poser(plaque(facade, u, socle, l + .16, h + .06, .16, 0), r.couleur);
				poser(plaque(facade, u, socle + h * .27, l * .56, h * .62, .1, .06), 'vitre', 'vitre');
				for(let i = 1; i <= r.bois; i++) {
					poser(plaque(facade, u, socle + h * (.27 + i * .62 / (r.bois + 1)) - .04, l * .56, .08, .1, .09), r.couleur);
				}
				if(r.volets !== null) {
					for(const sens of [-1, 1]) {
						const x = u + sens * (l / 2 + .08 + .29);
						poser(plaque(facade, x, socle, .58, h, .18, .03), r.volets);
						// Les deux barres de chaque volet, en relief : elles accrochent la lumière sur un aplat
						for(const y of [.2, h - .38]) {
							poser(plaque(facade, x, socle + y + .14, .58, .16, .08, .14), r.volets);
						}
					}
				}
			}
			else if(ouverture.catalogue === 'baie') {
				// L'intérieur dans l'ombre, ses poteaux régulièrement espacés et un poteau à chaque bout, la lisse devant
				poser(plaque(facade, u, socle, l, h, .06, .02), r.fond);
				const intervalles = r.poteaux + 1;
				for(let i = 0; i <= intervalles; i++) {
					poser(plaque(facade, u - l / 2 + i * l / intervalles, 0, .2, socle + h + .1, .2, .1), r.couleurPoteaux);
				}
				poser(plaque(facade, u, socle + h, l + .32, .22, .22, .1), r.couleurPoteaux);
				if(r.couleurLisse !== null && r.lisse > 0) {
					poser(plaque(facade, u, socle + r.lisse - .1, l, .16, .12, .2), r.couleurLisse);
				}
			}
			else if(ouverture.catalogue === 'porte-coulissante') {
				// Le fond se voit dans le joint entre les vantaux
				poser(plaque(facade, u, socle, l, h, .08, .03), r.fond);
				for(const [nom, sens] of [['vantailGauche', -1], ['vantailDroit', 1]]) {
					const x = u + sens * l / 4, vantail = l / 2 - .08;
					poser(plaque(facade, x, socle, vantail, h, .14, .13), r.couleur, nom);
					poser(plaque(facade, x, socle + h * .5 - .1, vantail, .2, .08, .22), r.fond, nom);
					poser(plaque(facade, x, socle + h * .5 + .1, .22, h * .5 - .1, .08, .22), r.couleur, nom);
					poser(plaque(facade, x, socle, .22, h * .5 - .1, .08, .22), r.couleur, nom);
				}
				poser(plaque(facade, u, socle + h, l + .7, .22, .3, .16), r.rail);
				if(r.montant !== null) {
					poser(plaque(facade, u + l / 2 + .16, 0, .26, socle + h, .22, .12), r.montant);
				}
			}
			else {
				const bas = ouverture.appui;
				if(r.appui !== null) {
					poser(plaque(facade, u, bas - .16, l + .3, .16, .32, 0), r.appui);
				}
				if(r.volets === 'clos') {
					poser(plaque(facade, u, bas, l, h, .2, 0), r.couleurVolets);
					continue;
				}
				poser(plaque(facade, u, bas, l, h, .1, .02), 'vitre', 'vitre');
				for(let i = 1; i <= r.bois; i++) {
					poser(plaque(facade, u - l / 2 + i * l / (r.bois + 1), bas, .08, h, .18, 0), r.cadre);
				}
				if(r.volets === 'paire' || r.volets === 'simple') {
					const volet = r.volets === 'paire' ? l * .42 : l;
					for(const sens of r.volets === 'paire' ? [-1, 1] : [-1]) {
						poser(plaque(facade, u + sens * (l / 2 + volet / 2 + .05), bas - .04, volet, h + .08, .2, 0), r.couleurVolets);
					}
				}
			}
			// La porte principale est la première de la façade avant : porte est le milieu du seuil, porteCoin le coin haut droit de
			// l'ouverture, d'où la scène lit la largeur et la hauteur de la porte
			if(ouverture.modele.porte === true && facade === 'avant' && ancres.porte === undefined) {
				ancres.porte = versMonde(facade, u, 0, 0);
				ancres.porteCoin = versMonde(facade, u + l / 2, socle + h, 0);
				ancres.enseigne = versMonde(facade, u, socle + h + .6, .24);
			}
		}

		// Les éléments
		for(const element of elements) {
			const r = element.reglages;
			if(element.catalogue === 'cheminee') {
				const y = v.toitA(r.x, r.z);
				poser(block(r.x, y - .1, r.z, r.cote, r.hauteur, r.cote), r.couleur);
				poser(block(r.x, y + r.hauteur - .1, r.z, r.cote + .18, .12, r.cote + .18), r.chapeau);
				ancres.cheminee = [r.x, Math.round((y + r.hauteur + .02) * 1000) / 1000, r.z];
			}
			else if(element.catalogue === 'descente') {
				const u = (r.cote === 'gauche' ? -1 : 1) * v.largeurFacade(r.facade) / 2;
				poser(plaque(r.facade, u, 0, .24, v.hautMur(r.facade, u) - .1, .24, -.04), r.couleur);
			}
			else if(element.catalogue === 'bac') {
				poser(plaque(r.facade, r.u, .25, r.longueur, .5, .74, .42), r.couleur);
				poser(plaque(r.facade, r.u, .725, r.longueur - .3, .04, .5, .42), 'vitre', 'vitre');
				for(const sens of [-1, 1]) {
					poser(plaque(r.facade, r.u + sens * (r.longueur / 2 - .35), 0, .34, .25, .5, .42), r.couleur);
				}
				poser(plaque(r.facade, r.u + .1, .75, .16, .5, .16, .08), r.couleur);
				ancres.bac = versMonde(r.facade, r.u, .75, .42);
			}
			else if(element.catalogue === 'lanterneau') {
				const longueur = D * r.longueur;
				poser(box(0, haut + .14, 0, .5, .32, longueur), r.fond);
				poser(box(0, haut + .34, 0, 1.1, .1, longueur + .2), r.couleur);
				haut += .39;
			}
			else if(element.catalogue === 'bottes') {
				// Une pyramide couchée : chaque rang a une balle de moins et se pose dans le creux des deux du dessous
				const rayon = r.diametre / 2;
				for(let rang = 0; rang < r.rangs; rang++) {
					const n = r.colonnes - rang;
					for(let i = 0; i < n; i++) {
						const along = (i - (n - 1) / 2) * r.diametre;
						const y = rayon + rang * rayon * Math.sqrt(3);
						const balle = facets(new THREE.CylinderGeometry(rayon, rayon, r.largeur, 8).rotateZ(Math.PI / 2).rotateY(r.sens === 'x' ? Math.PI / 2 : 0));
						poser(r.sens === 'x' ? balle.translate(r.x + along, y, r.z) : balle.translate(r.x, y, r.z + along), r.couleur);
					}
				}
			}
			else if(element.catalogue === 'ruches') {
				// Les ruches devant leur façade, sur une poutre portée par deux plots, l'entrée en bas tournée vers le dehors
				const dz = r.recul, rang = (r.nombre - 1) * r.ecart + .7;
				poser(plaque(r.facade, r.u, .3, rang, .12, .16, dz), r.support);
				for(const sens of [-1, 1]) {
					poser(plaque(r.facade, r.u + sens * (rang / 2 - .1), 0, .14, .3, .14, dz), r.support);
				}
				for(let i = 0; i < r.nombre; i++) {
					const u = r.u + (i - (r.nombre - 1) / 2) * r.ecart;
					poser(plaque(r.facade, u, .42, .5, .52, .45, dz), r.couleur);
					poser(plaque(r.facade, u, .94, .6, .06, .55, dz), r.toit);
					poser(plaque(r.facade, u, .46, .3, .04, .04, dz + .23), r.support);
				}
			}
			else if(element.catalogue === 'groupe-froid') {
				// Le caisson, sa grille sombre et le moyeu du ventilateur devant : le disque se lit de loin, la grille seule passait pour une fenêtre
				poser(plaque(r.facade, r.u, r.hauteur, 1.2, .9, .5, .25), r.couleur);
				for(const sens of [-1, 1]) {
					poser(plaque(r.facade, r.u + sens * .45, r.hauteur - .3, .06, .3, .5, .25), r.helice);
				}
				poser(surFacade(r.facade, new THREE.CylinderGeometry(.34, .34, .04, 10).rotateX(Math.PI / 2), r.u, r.hauteur + .45, .52), r.grille);
				poser(surFacade(r.facade, new THREE.CylinderGeometry(.1, .1, .06, 6).rotateX(Math.PI / 2), r.u, r.hauteur + .45, .55), r.helice);
			}
			else if(element.catalogue === 'betes') {
				// Chaque bête varie en taille, en recul et en angle, pour qu'aucune ne soit la copie de sa voisine
				const VARIE = [[1, 0, .05], [.92, .35, -.12], [1.06, -.15, .1], [.97, .25, -.04], [1.03, -.3, .14], [.95, .1, -.08]];
				// Les brebis se tiennent en troupeau, sur deux ou trois rangs ; les vaches et les poules gardent le rang du cornadis ou du pied de mur
				const etale = r.espece === 'brebis' ? 3 : 1;
				for(let i = 0; i < r.nombre; i++) {
					const [taille, dz, angle] = VARIE[i % VARIE.length];
					const u = r.u + (i - (r.nombre - 1) / 2) * r.ecart;
					for(const [geometrie, cle] of bete(r.espece, r.robe, r.tache, r.tete)) {
						poser(surFacade(r.facade, geometrie.scale(taille, taille, taille).rotateY(angle * etale), u, 0, r.recul + dz * etale), cle);
					}
				}
			}
			else if(element.catalogue === 'barriere') {
				// Deux lisses sur trois côtés, un poteau tous les deux mètres environ
				const { largeur: l, profondeur: p, hauteur: h } = r;
				for(const y of [h * .45, h - .08]) {
					poser(plaque(r.facade, r.u, y, l, .08, .06, p), r.couleur);
					for(const sens of [-1, 1]) {
						poser(surFacade(r.facade, new THREE.BoxGeometry(.06, .08, p), r.u + sens * l / 2, y + .04, p / 2), r.couleur);
					}
				}
				const poteaux = Math.max(2, Math.round(l / 2));
				for(let i = 0; i <= poteaux; i++) {
					poser(plaque(r.facade, r.u - l / 2 + i * l / poteaux, 0, .1, h, .1, p), r.couleur);
				}
				for(const sens of [-1, 1]) {
					poser(plaque(r.facade, r.u + sens * l / 2, 0, .1, h, .1, p / 2), r.couleur);
				}
			}
			else if(element.catalogue === 'caisses') {
				// Les caisses montrent leur contenu sur le dessus du dernier rang ; sur un étal, un seul rang, posé sur la table
				const largeur = .6, haute = .3, rangs = r.table > 0 ? 1 : r.rangs;
				if(r.table > 0) {
					const l = r.colonnes * (largeur + .06) + .2;
					poser(plaque(r.facade, r.u, r.table - .06, l, .06, .8, r.recul), r.couleurTable);
					for(const sens of [-1, 1]) {
						for(const avance of [-.3, .3]) {
							poser(plaque(r.facade, r.u + sens * (l / 2 - .08), 0, .08, r.table - .06, .08, r.recul + avance), r.couleurTable);
						}
					}
				}
				for(let i = 0; i < r.colonnes; i++) {
					const u = r.u + (i - (r.colonnes - 1) / 2) * (largeur + .06);
					const empile = Math.max(1, rangs - (i % 2));
					for(let rang = 0; rang < empile; rang++) {
						poser(plaque(r.facade, u, r.table + rang * haute, largeur, haute, .4, r.recul), r.couleur);
					}
					poser(plaque(r.facade, u, r.table + empile * haute - .04, largeur - .08, .1, .32, r.recul), r.contenus[i % r.contenus.length]);
				}
			}
			else if(element.catalogue === 'auvent') {
				// La toile part du mur et descend vers son bord, d'où pend le lambrequin
				const pente = .35, l = r.largeur, a = r.avancee;
				const toile = new THREE.BoxGeometry(l, .05, a / Math.cos(pente)).rotateX(pente);
				poser(surFacade(r.facade, toile, r.u, r.hauteur + a * Math.tan(pente) / 2, a / 2), r.couleur);
				poser(plaque(r.facade, r.u, r.hauteur - .28, l, .3, .04, a), r.couleur);
			}
			else if(element.catalogue === 'pondoirs') {
				// Le caisson en saillie à hauteur de main, son couvercle en pente vers le dehors
				poser(plaque(r.facade, r.u, r.hauteur, r.longueur, .5, .55, .27), r.couleur);
				const couvercle = new THREE.BoxGeometry(r.longueur + .1, .05, .68).rotateX(.4);
				poser(surFacade(r.facade, couvercle, r.u, r.hauteur + .5 + .13, .3), r.couvercle);
				for(const sens of [-1, 1]) {
					poser(plaque(r.facade, r.u + sens * (r.longueur / 2 - .1), 0, .08, r.hauteur, .08, .48), r.couvercle);
				}
			}
			else if(element.catalogue === 'roues') {
				// Les roues de part et d'autre des longs côtés, le châssis qui les relie et le timon devant
				const rayon = r.diametre / 2;
				const zs = r.essieux === 1 ? [0] : Array.from({ length: r.essieux }, (_, i) => -D / 2 + .8 + i * (D - 1.6) / (r.essieux - 1));
				for(const z of zs) {
					for(const sx of [-1, 1]) {
						poser(facets(new THREE.CylinderGeometry(rayon, rayon, .22, 10).rotateZ(Math.PI / 2).translate(sx * (W / 2 + .2), rayon, z)), r.couleur);
					}
					poser(box(0, rayon, z, W + .2, .12, .12), r.chassis);
				}
				for(const sx of [-1, 1]) {
					poser(box(sx * (W / 2 + .02), rayon, 0, .1, .16, D + .2), r.chassis);
				}
				// La traverse avant ferme le châssis hors du mur, et le timon en descend vers l'anneau d'attelage, posé sur sa béquille
				const avant = D / 2 + .1, timon = 1.5, chute = rayon - .3;
				poser(box(0, rayon, avant, W + .04, .16, .12), r.chassis);
				poser(facets(new THREE.BoxGeometry(.12, .12, Math.hypot(timon, chute)).rotateX(-Math.atan2(chute, timon)).translate(0, rayon - chute / 2, avant + timon / 2)), r.timon);
				poser(box(0, .15, avant + timon - .1, .1, .3, .1), r.timon);
			}
			else {
				// Le panneau penche contre le mur, sa bande claire sur la moitié gauche
				const penche = (u, y, l, h, e, dz) => facets(new THREE.BoxGeometry(l, h, e).rotateX(-.16).translate(u, y, demi(r.facade) + dz).rotateY(ANGLE[r.facade]));
				poser(penche(r.u, r.hauteur / 2, r.largeur, r.hauteur, .1, .2), r.couleur);
				poser(penche(r.u - r.largeur * .22, r.hauteur * .52, r.largeur * .4, r.hauteur * .6, .08, .26), r.bande);
			}
		}

		ancres.faitage = [corps.toit === 'appentis' ? (corps.haut === 'droite' ? 1 : -1) * W / 2 : 0, Math.round(haut * 1000) / 1000, 0];
		const { d, h } = measure(parts);
		return { d, h, aligned: false, parts, anchors: ancres };
	};

	// Le toit en croupe, d'un seul solide : quatre pans de même pente, le faitage selon Z, un chant à l'égout. Rend la hauteur du faitage
	const croupe = (W, D, egout, montee, debord, poser) => {
		const pente = montee / (W / 2), a = W / 2 + debord, b = D / 2 + debord;
		const ye = egout - pente * debord, e = .12, yr = egout + montee + .04, r = Math.max(D - W, 0) / 2;
		const [A, B, C, E] = [[-a, ye, b], [a, ye, b], [a, ye, -b], [-a, ye, -b]];
		const [A2, B2, C2, E2] = [A, B, C, E].map(([x, , z]) => [x, ye - e, z]);
		const R1 = [0, yr, r], R2 = [0, yr, -r];
		const centre = new THREE.Vector3(0, ye - e / 2, 0);
		const faces = [[A, B, R1], [C, E, R2], [B, C, R2, R1], [E, A, R1, R2], [A2, B2, B, A], [B2, C2, C, B], [C2, E2, E, C], [E2, A2, A, E], [A2, B2, C2, E2]];
		const positions = [];
		for(const face of faces) {
			for(let i = 1; i < face.length - 1; i++) {
				const [p, q, s] = [face[0], face[i], face[i + 1]].map(point => new THREE.Vector3(...point));
				const normale = new THREE.Vector3().crossVectors(q.clone().sub(p), s.clone().sub(p));
				const milieu = p.clone().add(q).add(s).divideScalar(3);
				// Chaque triangle se retourne au besoin pour regarder hors du solide
				const triangle = normale.dot(milieu.sub(centre)) < 0 ? [p, s, q] : [p, q, s];
				positions.push(...triangle.flatMap(point => point.toArray()));
			}
		}
		const geometrie = new THREE.BufferGeometry();
		geometrie.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
		poser(facets(geometrie));
		return yr;
	};

	// Une bête dans le repère de sa façade, centrée sous le corps, la tête vers le mur (-z) : des boites, que la scène voit à quelques
	// pixels. Rend des paires [géométrie, couleur] ; la tache marque la robe d'une vache, la crête d'une poule.
	const bete = (espece, robe, tache, tete) => {
		const B = (l, h, e, x, y, z) => new THREE.BoxGeometry(l, h, e).translate(x, y + h / 2, z);
		if(espece === 'vache') {
			const pattes = [-1, 1].flatMap(sx => [-1, 1].map(sz => B(.14, .62, .14, sx * .2, 0, sz * .6)));
			return [
				[merge([B(.62, .72, 1.7, 0, .6, 0), ...pattes]), robe],
				[B(.64, .34, .6, 0, .9, .3), tache],
				[B(.32, .36, .5, 0, .86, -1.05), tete],
			];
		}
		if(espece === 'brebis') {
			// La toison descend bas sur des pattes courtes et la tête pique vers l'herbe : avec de hautes pattes et la tête levée, une
			// brebis vue de haut passait pour une chaise
			const pattes = [-1, 1].flatMap(sx => [-1, 1].map(sz => B(.09, .22, .09, sx * .15, 0, sz * .3)));
			return [
				[merge([B(.56, .5, 1, 0, .2, 0), B(.44, .2, .76, 0, .7, 0)]), robe],
				[merge([B(.2, .26, .34, 0, .26, -.62), ...pattes]), tete],
			];
		}
		return [
			[merge([B(.22, .22, .34, 0, .1, 0), B(.12, .14, .12, 0, .28, -.16)]), robe],
			[B(.04, .1, .04, 0, 0, 0), tete],
			[B(.03, .06, .08, 0, .42, -.16), tache],
		];
	};

	// ---------- Les bâtiments d'une structure ----------
	// L'éditeur du décor ne règle pas une fiche : il règle les cotes et les choix de choices.json, et chaque type en compose la
	// fiche ici. Les couleurs secondaires sont liées aux teintes choisies, pour tenir six couleurs quel que soit le choix
	// (test/BuildingAi.t.php éprouve toutes les combinaisons).

	// Les chaines d'angle et les moellons d'un mur de pierre composé prennent la teinte du mur assombrie de 20 %, une couleur hors
	// de la palette P. Une clé fixe de P, claire ou sombre, se confondrait avec l'un des murs proposés, et deux couleurs de pierre
	// passeraient le plafond de six couleurs d'un bâtiment. Assombrie juste assez pour que le mur se lise pierre sur le plan, sans
	// que les moellons sautent aux yeux sur un mur clair : test/BuildingAi.t.php borne l'écart de clarté des deux côtés.
	// Un hex se prend tel quel : le même test compte les couleurs en remplaçant chaque teinte par un hex qu'aucune pièce ne porte
	const teintePierre = cle => {
		const hex = cle.startsWith('#') ? cle : P[cle];
		return '#' + [1, 3, 5].map(i => Math.round(parseInt(hex.slice(i, i + 2), 16) * .8).toString(16).padStart(2, '0')).join('');
	};

	// Le bâtiment composé. Toute la menuiserie (porte, poteaux, volets, cadres, pannes) est en bois sombre et l'intérieur d'une
	// ouverture en noir, que prennent aussi les panneaux solaires ; ni soubassement, ni gouttière, ni plaque sous la tuile
	const composeBuilding = (length, width, height, settings) => {

		const menuiserie = 'boisSombre';
		const appentis = settings.roof === 'appentis';

		const mur = { couleur: settings.wallTint };
		if(settings.wall === 'bardage-bois') {
			mur.joints = menuiserie;
		}
		else if(settings.wall === 'bardage-tole') {
			mur.joints = settings.wallTint;
			mur.soubassement = 0;
		}
		else if(settings.wall === 'pierre') {
			mur.angles = mur.moellons = teintePierre(settings.wallTint);
		}

		const couverture = { couleur: settings.coverTint, faitiere: settings.coverTint, gouttiere: null, sousPlaque: null };
		if(settings.cover === 'tuile') {
			couverture.pannes = menuiserie;
		}
		else if(settings.cover === 'panneaux-solaires') {
			couverture.panneaux = 'noir';
		}

		const fiche = {
			nom: 'batiment',
			corps: {
				toit: settings.roof,
				largeur: width,
				// Un toit en croupe court selon la profondeur, qui vaut donc au moins la largeur
				profondeur: settings.roof === 'croupe' ? Math.max(length, width) : length,
				egout: height,
				faitage: height + width * (appentis ? .25 : .38),
				socle: 0,
				...(appentis ? { haut: 'gauche' } : {}),
			},
			mur: { catalogue: settings.wall, reglages: mur },
			couverture: { catalogue: settings.cover, reglages: couverture },
			ouvertures: [],
		};

		// La grande porte tient dans la façade avant à un mètre de chaque angle, et sous l'égout, rail compris
		const porte = match(settings.door, {
			'porte-pleine': { largeur: Math.min(3.2, width - 2), hauteur: Math.min(3.4, height - .4), couleur: menuiserie },
			'porte-coulissante': { largeur: Math.min(4.4, width - 2), hauteur: Math.min(4.2, height - .5), couleur: settings.coverTint, fond: 'noir', rail: menuiserie, montant: menuiserie },
			'baie': { largeur: Math.min(6, width - 2), hauteur: Math.min(2.6, height - .4), couleurPoteaux: menuiserie, couleurLisse: menuiserie },
			'aucune': null,
		});
		if(porte !== null) {
			fiche.ouvertures.push({ catalogue: settings.door, facade: 'avant', u: 0, reglages: porte });
		}

		// Les fenêtres vont par paire, une sur chaque long côté, au quart de la longueur
		const quart = fiche.corps.profondeur / 4;
		for(let i = 0; i < settings.windows; i++) {
			fiche.ouvertures.push({
				catalogue: 'fenetre', facade: i % 2 === 0 ? 'droite' : 'gauche', u: i < 2 ? -quart : quart, appui: 1,
				reglages: { largeur: .8, hauteur: 1, volets: 'paire', couleurVolets: menuiserie, cadre: menuiserie, appui: null },
			});
		}

		return fiche;

	};

	// Le hangar agricole du catalogue (model/hangar.js), refait par une fiche : le hangar retouché en garde la largeur, la
	// descente d'eau et le vieux panneau, et ne change que ce qu'un hangar a de variable
	const HANGAR = {
		nom: 'hangar',
		corps: { toit: 'deux-pans', largeur: 10, profondeur: 18, egout: 5, faitage: 6.6, socle: 0 },
		mur: { catalogue: 'bardage-tole' },
		couverture: { catalogue: 'tole', reglages: { rangs: 2, debord: .3, rive: .3, faitiere: 'toleSombre' } },
		ouvertures: [
			{ catalogue: 'porte-coulissante', facade: 'avant', u: 2 },
		],
		elements: [
			{ catalogue: 'descente', reglages: { facade: 'avant', cote: 'gauche' } },
			{ catalogue: 'panneau', reglages: { facade: 'avant', u: -1.8 } },
		],
	};

	// Les couvre-joints prennent la teinte du bardage, la porte, son rail et ses poteaux celle de la couverture, et le bandeau du
	// panneau celle du bardage. Restent le gris du soubassement et de la descente, la tuile du panneau, et le noir d'une baie
	// ou l'encre des panneaux solaires
	const composeHangar = (length, height, settings) => {

		const fiche = JSON.parse(JSON.stringify(HANGAR));
		const menuiserie = settings.coverTint;

		fiche.corps.profondeur = length;
		fiche.corps.egout = height;
		fiche.corps.faitage = height + 1.6;
		fiche.mur = { catalogue: settings.wall, reglages: { couleur: settings.wallTint, joints: settings.wallTint } };
		fiche.couverture = { catalogue: settings.cover, reglages: { ...fiche.couverture.reglages, couleur: settings.coverTint, faitiere: settings.coverTint } };

		const porte = match(settings.door, {
			'porte-coulissante': { u: 2, reglages: { hauteur: Math.min(4.2, height - .3), couleur: menuiserie, fond: menuiserie, rail: menuiserie, montant: menuiserie } },
			'baie': { u: 0, reglages: { largeur: 6, hauteur: Math.min(2.6, height - .4), couleurPoteaux: menuiserie, couleurLisse: menuiserie } },
			'aucune': null,
		});
		fiche.ouvertures = porte === null ? [] : [{ catalogue: settings.door, facade: 'avant', ...porte }];
		fiche.elements = fiche.elements.map(element => element.catalogue === 'panneau' ? { ...element, reglages: { ...element.reglages, bande: settings.wallTint } } : element);

		return fiche;

	};

	// La maison d'habitation. La menuiserie (porte, cadres, volets) prend une seule teinte, la cheminée celle des murs et son
	// chapeau celle de la couverture, sans appui ni gouttière. La porte va sur le pignon avant, où la scène cherche le seuil,
	// surmontée d'une fenêtre à l'étage, et les fenêtres se répartissent sur les deux longues façades, à chaque niveau
	const composeHouse = (length, width, settings) => {

		const etage = 2.7;
		const egout = settings.levels === 2 ? etage * 2 + .3 : etage + .3;
		const profondeur = Math.max(length, width);

		const mur = { couleur: settings.wallTint };
		if(settings.wall === 'pierre') {
			mur.angles = mur.moellons = teintePierre(settings.wallTint);
		}
		else if(settings.wall === 'bardage-bois') {
			mur.joints = settings.wallTint;
		}

		const fiche = {
			nom: 'maison',
			corps: { toit: settings.roof, largeur: width, profondeur, egout, faitage: egout + width * (settings.roof === 'croupe' ? .36 : .42), socle: 0 },
			mur: { catalogue: settings.wall, reglages: mur },
			couverture: { catalogue: settings.cover, reglages: { couleur: settings.coverTint, faitiere: settings.coverTint, gouttiere: null, sousPlaque: null, pannes: null } },
			ouvertures: [
				{ catalogue: settings.door, facade: 'avant', u: 0, reglages: { largeur: 1, hauteur: 2.1, couleur: settings.joinery, ...(settings.door === 'porte-vitree' ? { volets: null } : {}) } },
			],
			elements: [],
		};

		const fenetre = (facade, u, appui) => ({
			catalogue: 'fenetre', facade, u, appui,
			reglages: { largeur: .9, hauteur: 1.2, bois: 1, cadre: settings.joinery, appui: null, volets: settings.shutters, couleurVolets: settings.joinery },
		});
		const places = Array.from({ length: settings.windows }, (_, i) => Math.round((-profondeur / 2 + profondeur * (i + .5) / settings.windows) * 100) / 100);
		for(let niveau = 0; niveau < settings.levels; niveau++) {
			const appui = niveau * etage + .9;
			for(const facade of ['droite', 'gauche']) {
				for(const u of places) {
					fiche.ouvertures.push(fenetre(facade, u, appui));
				}
			}
			if(niveau > 0) {
				fiche.ouvertures.push(fenetre('avant', 0, appui));
			}
		}

		if(settings.chimney === 1) {
			fiche.elements.push({ catalogue: 'cheminee', reglages: { x: 0, z: -profondeur / 4, couleur: settings.wallTint, chapeau: settings.coverTint, cote: .6, hauteur: 1 } });
		}

		return fiche;

	};

	// La fiche d'un bâtiment de la structure, { type, length, width, height, settings } comme map\DecorBuilding les porte
	const compose = ({ type, length, width, height, settings }) => match(type, {
		building: () => composeBuilding(length, width, height, settings),
		hangar: () => composeHangar(length, height, settings),
		house: () => composeHouse(length, width, settings),
	})();

	return {

		construct: construire,

		// Un bâtiment de la structure au contrat d'un modèle, ou null s'il ne se compose pas : une pose peut citer un bâtiment
		// retiré entre-temps, et la scène qui l'appelle dans un `onrender` ne doit pas tomber pour lui. Un choix absent prend
		// celui du type dans choices.json, pour qu'un bâtiment enregistré avant un choix neuf se monte encore
		build: building => {

			if(building === null || building === undefined) {
				return null;
			}

			try {
				const settings = { ...(choices?.[building.type]?.initial.settings ?? {}), ...building.settings };
				return construire(compose({ ...building, settings }));
			}
			catch {
				return null;
			}

		},

	};

}

export function build(THREE, helpers) {
	return sceneCreateBuilding(THREE, helpers).construct(FICHE);
}
