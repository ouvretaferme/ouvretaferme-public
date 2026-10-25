// Compose un bâtiment avec le cadre des bâtiments du site, et l'écrit en .glb prêt à téléverser :
//   node composer.mjs <fiche.json> <sortie.glb>
// La fiche prend l'une des deux formes que le site sait monter :
// - celle de l'éditeur du décor, un type de lib/choices.json, ses cotes et un choix par réglage :
//   { "type": "building", "length": 14, "width": 9, "height": 4.2, "settings": { "roof": "deux-pans", … } }
// - celle du cadre, qui cite le catalogue de lib/building.mjs (corps, mur, couverture, ouvertures, éléments) :
//   { "nom": "grange", "corps": { "toit": "deux-pans", … }, "mur": { "catalogue": "pierre" }, … }
// Imprime une ligne par pièce écrite (son nom dans le fichier, sa couleur, ses triangles), puis le compte des triangles et
// des couleurs face aux plafonds des règles, puis le jugement du validateur du site sur le modèle écrit.
// Le module n'importe que node : three, l'écriture du .glb (glb.mjs), la fabrique des bâtiments, ses choix et les règles lui
// sont passés, et seule l'entrée en ligne de commande va les chercher à côté de lui, dans la disposition du dossier
// téléchargé.
import { register } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// Une fiche que l'éditeur du décor refuserait, refusée avec ce qui est admis à la place, comme le site la juge
function checkEditorFiche(choices, fiche) {

	const entry = choices[fiche.type];

	if(entry === undefined) {
		throw new Error(`le type ${fiche.type} n'existe pas, les types sont ${Object.keys(choices).join(', ')}`);
	}

	const errors = [];

	for(const dimension of ['length', 'width', 'height']) {
		const bounds = entry[dimension], value = fiche[dimension] ?? null;
		if(bounds === null && value !== null) {
			errors.push(`${dimension} est fixé par le type ${fiche.type} : retirer la cote ou la mettre à null`);
		} else if(bounds !== null && (typeof value !== 'number' || value < bounds[0] || value > bounds[1])) {
			errors.push(`${dimension} vaut ${value}, admis de ${bounds[0]} à ${bounds[1]} m`);
		}
	}

	const settings = fiche.settings ?? {};

	for(const [key, values] of Object.entries(entry.settings)) {
		if(values.includes(settings[key]) === false) {
			errors.push(`settings.${key} vaut ${settings[key]}, admis : ${values.join(', ')}`);
		}
	}

	for(const key of Object.keys(settings).filter(key => Object.hasOwn(entry.settings, key) === false)) {
		errors.push(`settings.${key} n'est pas un réglage du type ${fiche.type}, qui porte ${Object.keys(entry.settings).join(', ')}`);
	}

	for(const rule of entry.excluded) {
		if(Object.entries(rule).every(([key, value]) => settings[key] === value)) {
			errors.push(`les choix ${Object.entries(rule).map(([key, value]) => key + ' = ' + value).join(' et ')} ne vont pas ensemble`);
		}
	}

	if(errors.length > 0) {
		throw new Error('La fiche ne se compose pas dans l\'éditeur :\n- ' + errors.join('\n- '));
	}

}

// Le bâtiment de la fiche au contrat d'un modèle : par l'éditeur pour une fiche qui porte un type, par le cadre sinon
function buildFiche(building, choices, fiche) {

	if(fiche.type === undefined) {
		return building.construct(fiche);
	}

	checkEditorFiche(choices, fiche);

	const model = building.build(fiche);

	// build() tait la raison d'un échec, qu'une fiche déjà contrôlée ne devrait pas connaitre
	if(model === null) {
		throw new Error('Le bâtiment ne se compose pas, bien que ses choix soient admis');
	}

	return model;

}

// Les pièces du modèle jointes par nom et par couleur : une primitive par couple. Une pièce sans nom est le corps du
// bâtiment, que les règles nomment `body`
function groupParts(model, rules) {

	const groups = new Map();

	for(const part of model.parts) {
		const name = part.name ?? rules.body;
		const key = name + '#' + part.color;
		if(groups.has(key) === false) {
			groups.set(key, { name, color: part.color, positions: [] });
		}
		const geometry = part.geometry.index !== null ? part.geometry.toNonIndexed() : part.geometry;
		groups.get(key).positions.push(...geometry.attributes.position.array);
	}

	return [...groups.values()];

}

// Le bâtiment de la fiche en .glb, et la description de ses pièces : une primitive par couple de nom et de couleur, et les
// seules ancres que les règles lisent
export function sceneUploadComposeBuilding(THREE, glb, building, choices, rules, fiche) {

	const model = buildFiche(building, choices, fiche);

	for(const name of Object.keys(model.anchors)) {
		if(rules.anchors.includes(name) === false) {
			delete model.anchors[name];
		}
	}

	const groups = groupParts(model, rules);

	if(groups.length > rules.maxPrimitives) {
		throw new Error(`${groups.length} couples de pièce et de couleur, plus que les ${rules.maxPrimitives} primitives admises`);
	}

	const parts = groups.map(({ name, color, positions }) => {
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.BufferAttribute(Float32Array.from(positions), 3));
		return { name, color, geometry };
	});

	return {
		bytes: glb.sceneUploadPack(glb.sceneUploadWriteGlb(parts, model.anchors)),
		pieces: groups.map(({ name, color, positions }) => ({ name, color, triangles: positions.length / 9 })),
		anchors: Object.keys(model.anchors),
	};

}

// fileURLToPath() et non `pathname`, qui garde encodés les espaces et les accents d'un chemin
if(process.argv[1] === fileURLToPath(import.meta.url)) {

	const [input, output] = process.argv.slice(2);

	if(output === undefined) {
		process.stderr.write('node composer.mjs <fiche.json> <sortie.glb>\n');
		process.exit(2);
	}

	// Avant tout import de three : le complément de three le cite sous le nom de son fichier dans le site
	register('./resolve.mjs', import.meta.url);

	const here = name => new URL(name, import.meta.url);
	const THREE = await import(here('lib/three.mjs'));
	const addons = await import(here('lib/BufferGeometryUtils.mjs'));
	const { sceneCreateGeometry } = await import(here('lib/geometry.mjs'));
	const { sceneCreateBuilding } = await import(here('lib/building.mjs'));
	const glb = await import(here('glb.mjs'));
	const { sceneUploadRules, sceneUploadCheck } = await import(here('upload.mjs'));
	const choices = JSON.parse(readFileSync(here('lib/choices.json'), 'utf8'));
	const rules = sceneUploadRules(JSON.parse(readFileSync(here('decor.json'), 'utf8')), 'building');
	const fiche = JSON.parse(readFileSync(input, 'utf8'));

	const building = sceneCreateBuilding(THREE, sceneCreateGeometry(THREE, addons), choices);

	let composed;

	try {
		composed = sceneUploadComposeBuilding(THREE, glb, building, choices, rules, fiche);
	} catch(error) {
		process.stderr.write(error.message + '\n');
		process.exit(1);
	}

	writeFileSync(output, composed.bytes);

	const ranks = {};

	for(const { name, color, triangles } of composed.pieces) {
		ranks[name] = (ranks[name] ?? 0) + 1;
		process.stdout.write(`${name}.${String(ranks[name]).padStart(3, '0')} ${color} ${triangles} triangles\n`);
	}

	const triangles = composed.pieces.reduce((sum, piece) => sum + piece.triangles, 0);
	const colors = new Set(composed.pieces.map(piece => piece.color)).size;

	process.stdout.write(`${triangles} triangles pour ${rules.maxTriangles} au plus, ${colors} couleurs pour ${rules.maxColors} au plus, ancres ${composed.anchors.join(', ')}\n`);

	try {
		process.stdout.write(JSON.stringify(sceneUploadCheck(rules, composed.bytes)) + '\n');
	} catch(error) {
		process.stdout.write(JSON.stringify({ refusal: error.refusal, values: error.values }) + '\n');
		process.exitCode = 1;
	}

}
