// Compose un avatar à partir du catalogue de l'éditeur du site, et l'écrit en .glb prêt à téléverser :
//   node composer.mjs <fiche.json> <sortie.glb>
// La fiche est celle de l'éditeur : la peau, puis une entrée du catalogue par famille, avec sa couleur.
//   { "corps": { "peau": "#e6b48f" }, "elements": [{ "catalogue": "courte", "reglages": { "couleur": "#483e30" } }, …] }
// Les entrées, les familles et les couleurs se lisent dans lib/catalog.json, chaque entrée décrite sous `labels`.
// Imprime une ligne par pièce écrite (son nom dans le fichier, sa couleur, ses triangles, les entrées qui la font),
// avertit d'une entrée que l'import perdrait, puis imprime le jugement du validateur du site sur le modèle écrit.
// Le module n'importe que node : three, l'écriture du .glb (glb.mjs), la fabrique de l'avatar, le catalogue et les règles
// lui sont passés, et seule l'entrée en ligne de commande va les chercher à côté de lui, dans la disposition du dossier
// téléchargé.
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// L'os des règles qui porte une pièce : le sien, ou le premier de ses parents qui en est un (un œil pend à sa paupière,
// qui pend à la tête). Une pièce qui n'en trouve aucun arrête l'écriture plutôt que de produire un modèle refusé
function boneOf(model, name, rules) {

	for(let bone = name; bone !== undefined; bone = model.parents[bone]) {
		if(rules.bones.includes(bone)) {
			return bone;
		}
	}

	throw new Error(`la pièce ${name} ne pend à aucun os des règles`);

}

// Les pièces d'un avatar monté, chacune sous le nom de l'os des règles qui la porte, comme le fichier les nomme
export function sceneUploadBoneParts(model, rules) {
	return model.parts.map(part => ({ ...part, name: boneOf(model, part.name, rules) }));
}

// Une fiche que l'éditeur du site ne saurait pas monter, refusée avec ce qui est admis à la place
function checkFiche(catalog, fiche) {

	const errors = [];
	const skins = catalog.palettes.skin;

	if(skins.includes(fiche.corps?.peau) === false) {
		errors.push(`la peau ${fiche.corps?.peau} n'est pas une teinte du catalogue, admises : ${skins.join(', ')}`);
	}

	const families = new Map();

	for(const { catalogue: entry, reglages = {} } of fiche.elements ?? []) {

		const family = catalog.families.find(family => family.entries.includes(entry));

		if(family === undefined) {
			errors.push(`l'entrée ${entry} n'est dans aucune famille du catalogue (lib/catalog.json) : lib/avatar.mjs porte aussi des pièces des personnages des scènes que l'éditeur n'offre pas, et seules les entrées du catalogue sont admises`);
			continue;
		}

		if(families.has(family.key)) {
			errors.push(`la famille ${family.key} porte deux entrées, ${families.get(family.key)} et ${entry}, une seule est admise`);
		}

		families.set(family.key, entry);

		const tint = reglages.couleur;

		if(tint === undefined) {
			continue;
		}

		// Comme l'éditeur : une couleur de la palette de la famille, ou une couleur libre, sauf pour une palette partagée
		// (le métal des bijoux) ; une famille sans palette n'a pas de couleur
		if(family.palette === null) {
			errors.push(`la famille ${family.key} (${entry}) ne prend pas de couleur`);
		} else if(catalog.palettes[family.palette].includes(tint) === false && (catalog.sharedPalettes.includes(family.palette) || /^#[0-9a-f]{6}$/.test(tint) === false)) {
			errors.push(`la couleur ${tint} de ${entry} n'est pas admise, la palette de la famille ${family.key} : ${catalog.palettes[family.palette].join(', ')}${catalog.sharedPalettes.includes(family.palette) ? '' : ', ou une couleur libre en #rrggbb minuscule'}`);
		}

	}

	for(const tuple of catalog.excluded) {
		if(tuple.every(entry => [...families.values()].includes(entry))) {
			errors.push(`les entrées ${tuple.join(' et ')} ne se portent pas ensemble`);
		}
	}

	if(errors.length > 0) {
		throw new Error(errors.join('\n'));
	}

}

// Les pièces du modèle jointes par os et par couleur, sous la clé `<os>#<couleur>` : une primitive par couple
function groupParts(model, rules) {

	const groups = new Map();

	for(const part of model.parts) {
		const name = boneOf(model, part.name, rules);
		const key = name + '#' + part.color;
		if(groups.has(key) === false) {
			groups.set(key, { name, color: part.color, geometries: [] });
		}
		groups.get(key).geometries.push(part.geometry.index !== null ? part.geometry.toNonIndexed() : part.geometry);
	}

	return groups;

}

// L'empreinte de chaque couple d'os et de couleur. Sa forme : sa boite et son aire, qu'un motif qui redécoupe les
// triangles d'une manche pour la teindre ne change pas. Sa teinte : ses positions et ses teintes par sommet, octet par octet
function fingerprint(THREE, groups, withTints) {

	const prints = new Map();
	const a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3();

	for(const [key, { geometries }] of groups) {

		let print;

		if(withTints) {
			let hash = 2166136261;
			for(const geometry of geometries) {
				for(const attribute of [geometry.attributes.position, geometry.attributes.color].filter(attribute => attribute !== undefined)) {
					for(const byte of new Uint8Array(attribute.array.buffer, attribute.array.byteOffset, attribute.array.byteLength)) {
						hash = Math.imul(hash ^ byte, 16777619);
					}
				}
			}
			print = String(hash);
		} else {
			const box = new THREE.Box3();
			let area = 0;
			for(const geometry of geometries) {
				const position = geometry.attributes.position;
				box.union(new THREE.Box3().setFromBufferAttribute(position));
				for(let i = 0; i < position.count; i += 3) {
					a.fromBufferAttribute(position, i);
					b.fromBufferAttribute(position, i + 1);
					c.fromBufferAttribute(position, i + 2);
					area += b.sub(a).cross(c.sub(a)).length() / 2;
				}
			}
			print = [...box.min.toArray(), ...box.max.toArray(), area].map(value => value.toFixed(4)).join(' ');
		}

		prints.set(key, print);

	}

	return prints;

}

const differs = (a, b) => [...new Set([...a.keys(), ...b.keys()])].filter(key => a.get(key) !== b.get(key));

// Ce que chaque entrée de la fiche fait au modèle importé, en le montant une fois sans elle : les couples d'os et de
// couleur qu'elle change, et les entrées que l'éditeur montre et que l'import perd, parce qu'elles ne font que teindre
// par sommet des pièces déjà là (un motif, un aspect de peau) et qu'une pièce importée ne garde qu'une couleur.
// Une entrée sans aucun effet, même dans l'éditeur, est celle qu'il mettrait à sa place : elle n'est pas signalée
export function sceneUploadDescribe(THREE, sceneCreateAvatar, catalog, rules, fiche) {

	checkFiche(catalog, fiche);

	const avatar = sceneCreateAvatar(THREE);
	const groups = groupParts(avatar.build(fiche), rules);
	const shapes = fingerprint(THREE, groups, false), tints = fingerprint(THREE, groups, true);

	const pieces = [...groups].map(([key, { name, color, geometries }]) => ({
		key, name, color,
		triangles: geometries.reduce((sum, geometry) => sum + geometry.attributes.position.count / 3, 0),
		entries: [],
	}));
	const lost = [];

	for(const element of fiche.elements ?? []) {

		const without = groupParts(avatar.build({ ...fiche, elements: fiche.elements.filter(other => other !== element) }), rules);
		const changed = differs(shapes, fingerprint(THREE, without, false));

		if(changed.length === 0 && differs(tints, fingerprint(THREE, without, true)).length > 0) {
			lost.push(element.catalogue);
		}

		for(const piece of pieces) {
			if(changed.includes(piece.key)) {
				piece.entries.push(element.catalogue);
			}
		}

	}

	return { pieces, lost };

}

// L'avatar de la fiche en .glb, ses pièces jointes par os et par couleur : une primitive par couple, sous le plafond des
// règles, là où l'éditeur en monte une par pièce
export function sceneUploadCompose(THREE, glb, sceneCreateAvatar, catalog, rules, fiche) {

	checkFiche(catalog, fiche);

	const groups = groupParts(sceneCreateAvatar(THREE).build(fiche), rules);

	if(groups.size > rules.maxPrimitives) {
		throw new Error(`${groups.size} couples d'os et de couleur, plus que les ${rules.maxPrimitives} primitives admises : retirer une entrée ou une couleur`);
	}

	const parts = [...groups.values()].map(({ name, color, geometries }) => {
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.BufferAttribute(Float32Array.from(geometries.flatMap(g => Array.from(g.attributes.position.array))), 3));
		return { name, color, geometry };
	});

	return glb.sceneUploadPack(glb.sceneUploadWriteGlb(parts));

}

// fileURLToPath() et non `pathname`, qui garde encodés les espaces et les accents d'un chemin
if(process.argv[1] === fileURLToPath(import.meta.url)) {

	const [input, output] = process.argv.slice(2);

	if(output === undefined) {
		process.stderr.write('node composer.mjs <fiche.json> <sortie.glb>\n');
		process.exit(2);
	}

	const here = name => new URL(name, import.meta.url);
	const THREE = await import(here('lib/three.mjs'));
	const { sceneCreateAvatar } = await import(here('lib/avatar.mjs'));
	const glb = await import(here('glb.mjs'));
	const { sceneUploadCheck } = await import(here('upload.mjs'));
	const catalog = JSON.parse(readFileSync(here('lib/catalog.json'), 'utf8'));
	const rules = JSON.parse(readFileSync(here('avatar.json'), 'utf8'));
	const fiche = JSON.parse(readFileSync(input, 'utf8'));

	let model, description;

	try {
		model = sceneUploadCompose(THREE, glb, sceneCreateAvatar, catalog, rules, fiche);
		description = sceneUploadDescribe(THREE, sceneCreateAvatar, catalog, rules, fiche);
	} catch(error) {
		process.stderr.write(error.message + '\n');
		process.exit(1);
	}

	writeFileSync(output, model);

	// Une pièce par ligne, sous le nom qu'elle porte dans le fichier, avec les entrées qui la font, ou le corps commun
	// quand aucune ne la change
	const ranks = {};

	for(const { name, color, triangles, entries } of description.pieces) {
		ranks[name] = (ranks[name] ?? 0) + 1;
		process.stdout.write(`${name}.${String(ranks[name]).padStart(3, '0')} ${color} ${triangles} triangles (${entries.length > 0 ? entries.join(', ') : 'corps'})\n`);
	}

	for(const entry of description.lost) {
		process.stderr.write(`Avertissement, ${entry} (${catalog.labels?.[entry] ?? entry}) ne se verra pas une fois importé, car il ne fait que teindre des pièces déjà là et une pièce importée ne garde qu'une couleur.\n`);
	}

	try {
		process.stdout.write(JSON.stringify(sceneUploadCheck(rules, model)) + '\n');
	} catch(error) {
		process.stdout.write(JSON.stringify({ refusal: error.refusal, values: error.values }) + '\n');
		process.exitCode = 1;
	}

}
