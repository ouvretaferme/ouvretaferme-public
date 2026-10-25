// Cuit les modules de model/ en deux fichiers servis, static/model/models.json et models.bin.
// Se lance depuis la racine du dépôt : node base/scene/bake/bake.mjs
// Un package qui a ses propres modèles se cuit chez lui, de son model/ vers son static/model/, en passant son dossier :
// node base/scene/bake/bake.mjs <dossier du package>. Ses modules reçoivent le helpers.js d'ici, qui n'existe qu'une fois.
// Un module est du code qui construit sa géométrie à chaque chargement ; la cuisson l'exécute une fois et garde le résultat,
// que static/js/model.js relit au même contrat. Elle relit aussi ce qu'elle vient d'écrire et refuse un écart de position ou de couleur.
import { register } from 'node:module';
import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createHash } from 'node:crypto';

const HELPERS = new URL('../model/helpers.js', import.meta.url);
const ROOT = process.argv[2] === undefined ? new URL('../', import.meta.url) : pathToFileURL(resolve(process.argv[2]) + '/');
const MODEL = new URL('model/', ROOT), OUT = new URL('static/model/', ROOT);

// resolve.mjs ne reconnait que les modules de ce package : ceux d'un autre lui sont désignés par leur dossier
register('./resolve.mjs', { parentURL: import.meta.url, data: { model: MODEL.href } });

const THREE = await import('three');
const helpers = await import(HELPERS);
const { sceneBuildModel } = await import(new URL('../static/js/model.js', import.meta.url));

// pivots, parents et anchors font un personnage articulé : name y désigne l'os qui porte la pièce
const KEYS = ['d', 'h', 'aligned', 'parts', 'pivots', 'parents', 'anchors'], PART_KEYS = ['geometry', 'color', 'sway', 'name'];
// Un point se garde en clair hors de la quantification par boite : un pivot faux d'un dixième de millimètre décale tout un bras
const point = values => values.map(n => +n.toFixed(5));
const points = table => Object.fromEntries(Object.entries(table).map(([key, values]) => [key, point(values)]));
const slugs = readdirSync(MODEL).filter(file => file.endsWith('.js') && file !== 'helpers.js').map(file => file.slice(0, -3)).sort();
const models = {}, positions = [], normals = [], colors = [], refused = [];
let vertices = 0;
// Une couleur par sommet (un dégradé de chevelure, une lisière) se garde en sRGB sur un octet par canal, après les normales
const color = new THREE.Color();

for(const slug of slugs) {
	const model = (await import(new URL(slug + '.js', MODEL))).build(THREE, helpers);
	const unknown = [...Object.keys(model).filter(key => !KEYS.includes(key)), ...model.parts.flatMap(part => Object.keys(part).filter(key => !PART_KEYS.includes(key)))];
	if(unknown.length > 0) {
		refused.push(slug + ' rend ' + [...new Set(unknown)].join(', ') + ', que la cuisson ne garde pas');
		continue;
	}
	// Un os sans pièce ou un parent sans pivot ne se verrait qu'à l'écran, en membre qui tourne autour de l'origine
	const bones = new Set(model.parts.map(part => part.name)), pivots = model.pivots ?? {}, parents = model.parents ?? {};
	const loose = [
		...Object.keys(pivots).filter(bone => !bones.has(bone)),
		...Object.entries(parents).flat().filter(bone => pivots[bone] === undefined),
	];
	if(loose.length > 0) {
		refused.push(slug + ' articule ' + [...new Set(loose)].join(', ') + ', sans pièce ou sans pivot');
		continue;
	}
	const geometries = model.parts.map(part => {
		const geometry = part.geometry.index ? part.geometry.toNonIndexed() : part.geometry;
		if(!geometry.attributes.normal) {
			geometry.computeVertexNormals();
		}
		return geometry;
	});
	const box = new THREE.Box3();
	for(const geometry of geometries) {
		box.union(new THREE.Box3().setFromBufferAttribute(geometry.attributes.position));
	}
	const min = box.min.toArray(), max = box.max.toArray();
	let offset = 0;
	const parts = model.parts.map((part, k) => {
		const position = geometries[k].attributes.position.array, normal = geometries[k].attributes.normal.array;
		for(let i = 0; i < position.length; i++) {
			const axis = i % 3, size = max[axis] - min[axis];
			positions.push(size > 0 ? Math.round((position[i] - min[axis]) / size * 65534) - 32767 : -32767);
			normals.push(Math.round(Math.max(-1, Math.min(1, normal[i])) * 127));
		}
		const out = { color: new THREE.Color(part.color).getHexString(), sway: part.sway ?? 1, offset, count: position.length / 3 };
		const tint = geometries[k].attributes.color;
		if(tint !== undefined) {
			out.colorOffset = colors.length / 3;
			for(let i = 0; i < tint.count; i++) {
				const hex = color.setRGB(tint.getX(i), tint.getY(i), tint.getZ(i)).getHex();
				colors.push(hex >> 16, (hex >> 8) & 255, hex & 255);
			}
		}
		if(part.name !== undefined) {
			out.name = part.name;
		}
		offset += out.count;
		return out;
	});
	models[slug] = {
		d: model.d, h: model.h, aligned: model.aligned === true,
		triangles: offset / 3,
		colors: new Set(parts.map(part => part.color)).size,
		hash: createHash('sha1').update(readFileSync(new URL(slug + '.js', MODEL))).digest('hex'),
		box: [min.map(n => +n.toFixed(5)), max.map(n => +n.toFixed(5))],
		offset: vertices,
		parts,
	};
	if(model.pivots !== undefined) {
		models[slug].pivots = points(model.pivots);
	}
	if(model.parents !== undefined) {
		models[slug].parents = model.parents;
	}
	if(model.anchors !== undefined) {
		models[slug].anchors = points(model.anchors);
	}
	vertices += offset;
}

if(refused.length > 0) {
	console.error(refused.join('\n'));
	process.exit(1);
}

const index = { helpers: createHash('sha1').update(readFileSync(HELPERS)).digest('hex'), vertices, colors: colors.length / 3, models };
const buffer = new ArrayBuffer(vertices * 9 + colors.length);
new Int16Array(buffer, 0, vertices * 3).set(positions);
new Int8Array(buffer, vertices * 6, vertices * 3).set(normals);
new Uint8Array(buffer, vertices * 9, colors.length).set(colors);

// La relecture passe par le lecteur du client : un écart vient alors de l'un ou de l'autre, et se voit ici plutôt qu'à l'écran.
// Une position quantifiée sur 16 bits s'écarte au plus d'un demi-pas de sa boite : un dixième de millimètre couvre un modèle
// de 13 m, un plus grand (le hangar de 18,7 m) a droit à son demi-pas. Un pivot ou une ancre se garde en clair, et reste au
// dixième de millimètre. Une couleur par sommet s'écarte au plus d'un demi-pas sRGB, comparée en sRGB des deux côtés, plus deux
// millièmes de pas : l'aller et retour par le linéaire en Float32 en perd jusqu'à 0,0015, et une source juste à un demi-pas près serait refusée
let worst = 0;
const srgb = (attribute, i) => color.setRGB(attribute.getX(i), attribute.getY(i), attribute.getZ(i)).getRGB({}, THREE.SRGBColorSpace);
const drifted = [];
for(const slug of slugs) {
	const source = (await import(new URL(slug + '.js', MODEL))).build(THREE, helpers), baked = sceneBuildModel(THREE, { index, buffer }, slug);
	const [min, max] = index.models[slug].box;
	const limit = Math.max(.0001, Math.max(...max.map((n, axis) => n - min[axis])) / 65534 / 2 + .00001);
	let drift = 0, pointDrift = 0, tintDrift = 0;
	source.parts.forEach((part, k) => {
		const geometry = part.geometry.index ? part.geometry.toNonIndexed() : part.geometry;
		const a = geometry.attributes.position.array, b = baked.parts[k].geometry.attributes.position.array;
		for(let i = 0; i < a.length; i++) {
			drift = Math.max(drift, Math.abs(a[i] - b[i]));
		}
		const tint = geometry.attributes.color, tintBaked = baked.parts[k].geometry.attributes.color;
		if(tint !== undefined) {
			if(tintBaked === undefined) {
				tintDrift = Infinity;
				return;
			}
			for(let i = 0; i < tint.count; i++) {
				const x = srgb(tint, i), y = srgb(tintBaked, i);
				tintDrift = Math.max(tintDrift, Math.abs(x.r - y.r), Math.abs(x.g - y.g), Math.abs(x.b - y.b));
			}
		}
	});
	for(const key of ['pivots', 'anchors']) {
		for(const [name, values] of Object.entries(source[key] ?? {})) {
			values.forEach((n, axis) => pointDrift = Math.max(pointDrift, Math.abs(n - baked[key][name][axis])));
		}
	}
	if(drift > limit || pointDrift > .0001) {
		drifted.push(slug + ' s\'écarte de ' + Math.max(drift, pointDrift) + ' m de sa source');
	}
	if(tintDrift > (.5 + .002) / 255) {
		drifted.push(slug + (tintDrift === Infinity ? ' perd sa couleur par sommet à la relecture' : ' s\'écarte de ' + Math.round(tintDrift * 255 * 10) / 10 + ' pas sRGB de sa couleur par sommet'));
	}
	worst = Math.max(worst, drift, pointDrift);
}
if(drifted.length > 0) {
	console.error(drifted.join('\n') + '\nRien n\'est écrit');
	process.exit(1);
}

mkdirSync(OUT, { recursive: true });
writeFileSync(new URL('models.json', OUT), JSON.stringify(index));
writeFileSync(new URL('models.bin', OUT), Buffer.from(buffer));
console.log(slugs.length + ' modèles, ' + vertices / 3 + ' triangles, ' + colors.length / 3 + ' sommets colorés, ' + buffer.byteLength + ' octets, écart de relecture ' + worst.toExponential(1) + ' m');
