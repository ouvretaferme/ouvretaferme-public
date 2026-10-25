// Écrit les modèles que portent les skills Claude de téléversement, chacun tiré de ce que le site monte lui-même.
// Le skill de l'avatar (base/scene/upload/avatar/), d'après l'avatar livré par le skill 3d-perso (static/js/avatar.js et son
// catalogue) :
//   corps.glb                  le corps nu, peau par défaut, d'où l'utilisateur part ;
//   exemples/conforme.glb      la composition par défaut de l'éditeur ;
//   exemples/refus-*.glb       trois dérivés du conforme, chacun refusé pour une seule raison ;
//   exemples/attendu.json      ce que rend `upload.mjs --check` pour chacun, au caractère près.
// Le skill du décor (base/scene/upload/decor/), d'après les bâtiments du site (static/js/building.js et ses choix) :
//   exemples/fiche.json        une fiche de l'éditeur du décor, un bâtiment de pierre ;
//   exemples/conforme.glb      ce bâtiment composé par decor/composer.mjs ;
//   exemples/refus-*.glb       trois dérivés du conforme, chacun refusé pour une seule raison ;
//   exemples/conforme-*.glb    un élément conforme par rangée Eau, Nature et Matériel ;
//   exemples/attendu.json      ce que rend `upload.mjs --check` pour chacun, sous sa famille, au caractère près.
// Et à côté, hors des archives, <profil>.samples.json : l'empreinte des sources lues, que test/UploadSkillSentinel.t.php
// compare. Son nom porte un point pour qu'aucun profil (`UploadLib::getRules()`, `^[a-z]+$`) ne le lise comme des règles.
// Se lance depuis la racine du dépôt après toute relivraison de l'avatar ou du cadre des bâtiments, ou retouche des règles :
//   node base/scene/upload/samples.mjs              écrit les fichiers
//   node base/scene/upload/samples.mjs --verifier   régénère en mémoire, nomme les fichiers périmés et sort en 1 s'il y en a
// Les fichiers écrits ne se retouchent pas à la main.
import { register } from 'node:module';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';

register('../bake/resolve.mjs', import.meta.url);

const SCENE = new URL('../', import.meta.url);
const UPLOAD = new URL('./', import.meta.url);

// Les sources dont les modèles d'un profil dépendent : une seule qui bouge les rend périmés
const SOURCES = {
	avatar: ['static/js/avatar.js', 'static/avatar/catalog.json', 'upload/avatar.json', 'upload/upload.mjs', 'upload/glb.mjs', 'upload/samples.mjs', 'upload/avatar/composer.mjs'],
	decor: ['static/js/building.js', 'static/js/geometry.js', 'static/building/choices.json', 'upload/decor.json', 'upload/upload.mjs', 'upload/glb.mjs', 'upload/samples.mjs', 'upload/decor/composer.mjs'],
};

const THREE = await import('three');
const addons = await import('three/addons/utils/BufferGeometryUtils.js');
const { sceneCreateAvatar } = await import(new URL('static/js/avatar.js', SCENE));
const { sceneCreateGeometry } = await import(new URL('static/js/geometry.js', SCENE));
const { sceneCreateBuilding } = await import(new URL('static/js/building.js', SCENE));
const { sceneUploadRules, sceneUploadCheck } = await import(new URL('upload.mjs', UPLOAD));
const glb = await import(new URL('glb.mjs', UPLOAD));
const { sceneUploadBoneParts } = await import(new URL('avatar/composer.mjs', UPLOAD));
const { sceneUploadComposeBuilding } = await import(new URL('decor/composer.mjs', UPLOAD));

const readJson = url => JSON.parse(readFileSync(url, 'utf8'));

// Un dérivé du conforme, sa retouche faite sur une copie du JSON : le binaire ne change pas
const derive = (model, alter) => {
	const json = structuredClone(model.json);
	alter(json);
	return glb.sceneUploadPack({ json, bin: model.bin });
};

// Ce que rend upload.mjs --check, sous la même forme que sa sortie standard
function check(rules, bytes) {
	try {
		return sceneUploadCheck(rules, bytes);
	}
	catch(error) {
		if(error.refusal === undefined) {
			throw error;
		}
		return { refusal: error.refusal, values: error.values };
	}
}

// Juge chaque fichier, vérifie qu'il rend le refus attendu, et écrit attendu.json des exemples. Un profil à plusieurs familles
// juge chacune sous ses règles : attendu.json garde alors ce que les appels précédents y ont écrit
function judge(rules, files, expected) {

	const results = (files['exemples/attendu.json'] === undefined) ? {} : JSON.parse(files['exemples/attendu.json']);

	for(const [file, refusal] of Object.entries(expected)) {
		const result = check(rules, files[file]);
		if((result.refusal ?? null) !== refusal) {
			throw new Error(`${file} rend ${JSON.stringify(result)} au lieu de ${refusal ?? 'aucun refus'}`);
		}
		if(file.startsWith('exemples/')) {
			results[file.replace('exemples/', '')] = result;
		}
	}

	files['exemples/attendu.json'] = Buffer.from(JSON.stringify(results, null, '\t') + '\n');

}

// La composition par défaut de l'éditeur d'avatar, comme UserAvatarLib::getFicheByComposition() la monte : une entrée par
// famille qui en a une par défaut, teinte de la famille comprise
function defaultFiche(catalog) {

	const elements = catalog.families
		.filter(family => catalog.defaults.entries[family.key] !== null)
		.map(family => ({
			catalogue: catalog.defaults.entries[family.key],
			reglages: family.palette !== null ? { couleur: catalog.defaults.tints[family.key] } : {},
		}));

	return { corps: { peau: catalog.defaults.skin }, elements };

}

function buildAvatar() {

	const catalog = readJson(new URL('static/avatar/catalog.json', SCENE));
	const rules = readJson(new URL('avatar.json', UPLOAD));

	const avatar = sceneCreateAvatar(THREE);
	const body = glb.sceneUploadWriteGlb(sceneUploadBoneParts(avatar.build({ corps: { peau: catalog.defaults.skin }, elements: [] }), rules));
	const sample = glb.sceneUploadWriteGlb(sceneUploadBoneParts(avatar.build(defaultFiche(catalog)), rules));

	const files = {
		'corps.glb': glb.sceneUploadPack(body),
		'exemples/conforme.glb': glb.sceneUploadPack(sample),
		// Une pièce de la tête prend le nom d'un objet que l'utilisateur ajouterait sans le rattacher à un os
		'exemples/refus-os.glb': derive(sample, json => { json.nodes.find(node => node.name === 'tete.002').name = 'chapeau'; }),
		// L'armature qu'exporte un personnage animé dans Blender
		'exemples/refus-armature.glb': derive(sample, json => { json.skins = [{ joints: [0] }]; }),
		// Tout le modèle levé de 10 cm : les pieds ne touchent plus le sol
		'exemples/refus-sol.glb': derive(sample, json => { json.nodes.forEach(node => { node.translation = [0, .1, 0]; }); }),
	};

	judge(rules, files, { 'corps.glb': null, 'exemples/conforme.glb': null, 'exemples/refus-os.glb': 'bone', 'exemples/refus-armature.glb': 'rig', 'exemples/refus-sol.glb': 'ground' });

	return files;

}

// Un bâtiment de pierre, une porte et deux fenêtres en façade : la pierre porte ses moellons et ses chaines d'angle, la
// couleur composée de la pierre et la vitre, que l'exemple montre ainsi à l'utilisateur
const FICHE = { type: 'building', length: 14, width: 9, height: 4.2, settings: { roof: 'deux-pans', cover: 'tuile', coverTint: 'tuile', wall: 'pierre', wallTint: 'pierre', door: 'porte-pleine', windows: 2 } };

// Un abreuvoir, un noyer au tronc marqué par l'ancre `arbre` et un broyeur, chacun sous sa famille de `decor.json`
const OBJECTS = [
	['water', 'conforme-eau.glb', [
		['objet', '#8c949c', new THREE.BoxGeometry(2.4, .7, .8).translate(0, .35, 0)],
	], {}],
	['tree', 'conforme-nature.glb', [
		['arbre', '#6b4f36', new THREE.CylinderGeometry(.18, .25, 2.4, 6).translate(0, 1.2, 0)],
		['feuillage', '#4f7a3a', new THREE.IcosahedronGeometry(1.8, 0).translate(0, 3.4, 0)],
	], { arbre: [0, 0, 0] }],
	['other', 'conforme-materiel.glb', [
		['objet', '#c8462c', new THREE.BoxGeometry(1.6, 1.2, 1).translate(0, .6, 0)],
		['objet', '#3a3a3a', new THREE.CylinderGeometry(.3, .3, .2, 8).rotateZ(Math.PI / 2).translate(.9, .3, 0)],
	], {}],
];

function buildDecor() {

	const choices = readJson(new URL('static/building/choices.json', SCENE));
	const rules = sceneUploadRules(readJson(new URL('decor.json', UPLOAD)), 'building');
	const building = sceneCreateBuilding(THREE, sceneCreateGeometry(THREE, addons), choices);

	// Le conforme se relit depuis ses octets, pour que ses dérivés partent du fichier même que l'exemple porte
	const bytes = sceneUploadComposeBuilding(THREE, glb, building, choices, rules, FICHE).bytes;
	const length = bytes.readUInt32LE(12);
	const sample = { json: JSON.parse(bytes.subarray(20, 20 + length).toString()), bin: bytes.subarray(28 + length) };

	const files = {
		'exemples/fiche.json': Buffer.from(JSON.stringify(FICHE, null, '\t') + '\n'),
		'exemples/conforme.glb': bytes,
		// Une enseigne modelée à part, sous un nom qui n'est aucune pièce d'un bâtiment
		'exemples/refus-piece.glb': derive(sample, json => { json.nodes[0].name = 'enseigne.001'; }),
		// Une texture de pierre posée sur le mur, comme l'exporte un matériau qui lit une image
		'exemples/refus-texture.glb': derive(sample, json => { json.images = [{ bufferView: 0, mimeType: 'image/png' }]; json.textures = [{ source: 0 }]; }),
		// Tout le bâtiment levé de 50 cm : il ne touche plus le sol
		'exemples/refus-sol.glb': derive(sample, json => { json.nodes.push({ children: json.scenes[0].nodes, translation: [0, .5, 0] }); json.scenes[0].nodes = [json.nodes.length - 1]; }),
	};

	judge(rules, files, { 'exemples/conforme.glb': null, 'exemples/refus-piece.glb': 'piece', 'exemples/refus-texture.glb': 'texture', 'exemples/refus-sol.glb': 'ground' });

	// Un élément conforme par rangée Eau, Nature et Matériel, composé ici en three.js dans la forme qu'un modèle de Blender doit
	// prendre : quelques formes à facettes, sous les pièces de la famille
	for(const [family, file, parts, anchors] of OBJECTS) {

		const bytesObject = glb.sceneUploadPack(glb.sceneUploadWriteGlb(parts.map(([name, color, geometry]) => ({ name, color, geometry: (geometry.index === null) ? geometry : geometry.toNonIndexed() })), anchors));

		files['exemples/'+ file] = bytesObject;
		judge(sceneUploadRules(readJson(new URL('decor.json', UPLOAD)), family), files, { ['exemples/'+ file]: null });

	}

	return files;

}

function build() {

	const targets = {};

	for(const [profile, files] of Object.entries({ avatar: buildAvatar(), decor: buildDecor() })) {

		for(const [file, bytes] of Object.entries(files)) {
			targets[fileURLToPath(new URL(profile + '/' + file, UPLOAD))] = bytes;
		}

		const fingerprints = Object.fromEntries(SOURCES[profile].map(source => [source, createHash('sha1').update(readFileSync(new URL(source, SCENE))).digest('hex')]));
		targets[fileURLToPath(new URL(profile + '.samples.json', UPLOAD))] = Buffer.from(JSON.stringify({ sources: fingerprints }, null, '\t') + '\n');

	}

	return targets;

}

if(process.argv[1] === fileURLToPath(import.meta.url)) {

	const targets = build();

	if(process.argv.includes('--verifier')) {

		const stale = Object.entries(targets).filter(([path, bytes]) => {
			try {
				return readFileSync(path).equals(bytes) === false;
			}
			catch {
				return true;
			}
		}).map(([path]) => path);

		process.stdout.write(JSON.stringify({ stale }));
		process.exit(stale.length === 0 ? 0 : 1);

	}

	for(const [path, bytes] of Object.entries(targets)) {
		mkdirSync(dirname(path), { recursive: true });
		writeFileSync(path, bytes);
	}

	console.log(Object.keys(targets).length + ' fichiers écrits');

}
