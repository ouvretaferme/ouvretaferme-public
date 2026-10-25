// Lit un modèle 3D téléversé (glTF binaire, .glb), juge s'il tient les règles de son profil et le convertit au format cuit que
// relit static/js/model.js, sceneBuildModel(). Il tourne sur le serveur, sous node, lancé par lib/Upload.l.php ; le navigateur ne
// l'exécute jamais, d'où sa place hors de static/.
//   node base/scene/upload/upload.mjs --check|--bake --rules=<règles.json> [--family=<famille>] --in=<fichier.glb>
//   node base/scene/upload/upload.mjs --smooth --rules=<règles.json> --in=<binaire cuit> --index=<index.json>
// écrit sur la sortie standard un seul objet JSON : { refusal, values } pour un modèle refusé, { triangles } pour --check,
// { rules, triangles, index, buffer } pour --bake, { buffer } pour --smooth, qui refait les normales d'un binaire déjà cuit
// (sceneUploadSmooth()), le tampon en base64. Un refus n'est pas une panne et sort en 0 ; le serveur
// tient toute autre sortie pour un fichier illisible. Aucun texte du produit ici : un refus est un code de `refusals` et ses
// valeurs, que l'Alert.u.php du package qui reçoit le modèle met en phrase.
//
// Le fichier vient d'un inconnu. Le lecteur ne suit aucune adresse, ne lit ni texture ni extension, borne chaque longueur et
// chaque indice avant d'allouer, refuse un nombre non fini, et compte les triangles, instances comprises, avant d'en lire un.
//
// Deux profils, nommés par `profile` dans les règles, partagent le lecteur, les plafonds et le format cuit. Les deux veulent
// des triangles et une couleur de matériau par primitive, et ignorent ce qui suit un point dans un nom (`tete.001` vaut `tete`).
// - `avatar` : une pièce par nœud de maillage, nommée d'après l'os qui la porte (`tete`, `brasG`) ; les pieds au sol à
//   y = 0, la face vers +z. Les pivots et les parents des os sont ceux des règles, jamais ceux du fichier : les poses de
//   l'éditeur ne valent que pour eux.
// - `decor` : un objet du décor d'une famille (`families`, `--family`), dont le bloc complète le tronc commun des règles. Une
//   pièce par nœud de maillage, nommée d'après les pièces de la famille ; des ancres en nœuds vides, nommés d'après elles ;
//   le point le plus bas au sol, l'emprise centrée sur l'origine, la face vers +z.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const GLB_MAGIC = 0x46546c67, CHUNK_JSON = 0x4e4f534a, CHUNK_BIN = 0x004e4942;
const FLOAT = 5126, INDEX_TYPES = { 5121: 1, 5123: 2, 5125: 4 }, TRIANGLES = 4;

export class SceneUploadRefusal extends Error {

	constructor(refusal, values = {}) {
		super(refusal);
		this.refusal = refusal;
		this.values = values;
	}

}

const refuse = (refusal, values) => { throw new SceneUploadRefusal(refusal, values); };

// Les règles d'un modèle : celles du profil, et pour le décor le tronc commun complété du bloc de sa famille. Un profil
// inconnu, une famille demandée à un profil qui n'en a pas, ou absente du décor, sont une panne de l'appelant et non un
// refus du fichier : le lecteur ne sait juger que l'avatar et le décor
export function sceneUploadRules(json, family = null) {

	if(['avatar', 'decor'].includes(json.profile) === false) {
		throw new Error('profil inconnu des règles : ' + json.profile + ', les profils sont avatar et decor');
	}

	if(json.families === undefined) {
		if(family !== null) {
			throw new Error('le profil ' + json.profile + ' n\'a pas de famille');
		}
		return json;
	}

	if(family === null || Object.hasOwn(json.families, family) === false) {
		throw new Error('famille inconnue du profil ' + json.profile + ' : ' + family + ', les familles sont ' + Object.keys(json.families).join(', '));
	}

	const { families, ...common } = json;

	return { ...common, ...families[family], family };

}

// Un renvoi du fichier vers une de ses listes : un entier, dans la liste. Tout autre valeur est un fichier mal formé
const at = (list, i) => (Array.isArray(list) && Number.isInteger(i) && i >= 0 && i < list.length && list[i] !== null && typeof list[i] === 'object') ? list[i] : refuse('format');
const finite = (values, length) => (Array.isArray(values) && values.length === length && values.every(Number.isFinite)) ? values : refuse('format');

// L'en-tête et les deux morceaux d'un glTF binaire : le JSON, puis le binaire, qui peut manquer
function readContainer(bytes) {

	if(bytes.length < 20) {
		refuse('format');
	}

	const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);

	if(view.getUint32(0, true) !== GLB_MAGIC || view.getUint32(4, true) !== 2 || view.getUint32(8, true) !== bytes.length) {
		refuse('format');
	}

	const chunks = [];

	for(let offset = 12; offset < bytes.length;) {
		if(offset + 8 > bytes.length) {
			refuse('format');
		}
		const length = view.getUint32(offset, true), type = view.getUint32(offset + 4, true);
		if(offset + 8 + length > bytes.length) {
			refuse('format');
		}
		chunks.push({ type, data: bytes.subarray(offset + 8, offset + 8 + length) });
		offset += 8 + length;
	}

	if(chunks.length === 0 || chunks.length > 2 || chunks[0].type !== CHUNK_JSON || (chunks.length === 2 && chunks[1].type !== CHUNK_BIN)) {
		refuse('format');
	}

	let json;

	try {
		json = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(chunks[0].data));
	}
	catch {
		refuse('format');
	}

	if(json === null || typeof json !== 'object' || Array.isArray(json)) {
		refuse('format');
	}

	return { json, bin: chunks[1]?.data ?? new Uint8Array(0) };

}

// Ce que le fichier peut demander hors de lui-même, ou au-delà de pièces peintes : refusé avant toute lecture de géométrie
function checkContent(json) {

	const filled = key => Array.isArray(json[key]) && json[key].length > 0;

	if(filled('extensionsUsed') || filled('extensionsRequired') || (Array.isArray(json.buffers) && json.buffers.some(buffer => buffer?.uri !== undefined))) {
		refuse('external');
	}

	if(filled('images') || filled('textures') || filled('samplers')) {
		refuse('texture');
	}

	if(filled('skins') || filled('animations')) {
		refuse('rig');
	}

	if(Array.isArray(json.buffers) && json.buffers.length > 1) {
		refuse('format');
	}

}

// Les matrices sont des tableaux de 16 nombres, par colonnes comme dans glTF
const multiply = (a, b) => {
	const out = new Array(16);
	for(let c = 0; c < 4; c++) {
		for(let r = 0; r < 4; r++) {
			out[c * 4 + r] = a[r] * b[c * 4] + a[4 + r] * b[c * 4 + 1] + a[8 + r] * b[c * 4 + 2] + a[12 + r] * b[c * 4 + 3];
		}
	}
	return out;
};
const IDENTITY = [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1];

function nodeMatrix(node) {

	if(node.matrix !== undefined) {
		return finite(node.matrix, 16);
	}

	const [tx, ty, tz] = node.translation === undefined ? [0, 0, 0] : finite(node.translation, 3);
	const [x, y, z, w] = node.rotation === undefined ? [0, 0, 0, 1] : finite(node.rotation, 4);
	const [sx, sy, sz] = node.scale === undefined ? [1, 1, 1] : finite(node.scale, 3);

	return [
		(1 - 2 * (y * y + z * z)) * sx, (2 * (x * y + z * w)) * sx, (2 * (x * z - y * w)) * sx, 0,
		(2 * (x * y - z * w)) * sy, (1 - 2 * (x * x + z * z)) * sy, (2 * (y * z + x * w)) * sy, 0,
		(2 * (x * z + y * w)) * sz, (2 * (y * z - x * w)) * sz, (1 - 2 * (x * x + y * y)) * sz, 0,
		tx, ty, tz, 1,
	];

}

const determinant = m => m[0] * (m[5] * m[10] - m[9] * m[6]) - m[4] * (m[1] * m[10] - m[9] * m[2]) + m[8] * (m[1] * m[6] - m[5] * m[2]);

// Les nœuds de la scène, chacun avec sa matrice dans le repère du modèle : ceux qui portent un maillage, et à part les nœuds
// vides, où le décor lit ses ancres. Un nœud atteint deux fois (un cycle, ou un enfant partagé) refuse le fichier : le
// dépliage n'aurait pas de fin, ou compterait des triangles que l'auteur ne voit pas
function collectNodes(json, rules) {

	const nodes = json.nodes ?? [];

	if(Array.isArray(nodes) === false) {
		refuse('format');
	}

	if(nodes.length > rules.maxNodes) {
		refuse('nodes');
	}

	const scenes = json.scenes;
	const scene = at(scenes, json.scene ?? 0);
	const roots = scene.nodes ?? [];

	if(Array.isArray(roots) === false) {
		refuse('format');
	}

	const seen = new Set(), found = [], empties = [];

	const visit = (i, parent, depth) => {

		if(depth > rules.maxDepth) {
			refuse('nodes');
		}

		const node = at(nodes, i);

		if(seen.has(i)) {
			refuse('format');
		}

		seen.add(i);

		const matrix = multiply(parent, nodeMatrix(node));

		if(node.mesh !== undefined) {
			found.push({ node, matrix });
		} else {
			empties.push({ node, matrix });
		}

		const children = node.children ?? [];

		if(Array.isArray(children) === false) {
			refuse('format');
		}

		children.forEach(child => visit(child, matrix, depth + 1));

	};

	roots.forEach(root => visit(root, IDENTITY, 1));

	return { meshes: found, empties };

}

// Le nom d'un nœud jusqu'au premier point
const baseName = node => (typeof node.name === 'string' ? node.name : '').split('.')[0];

// L'os de l'avatar, ou la pièce du décor, que nomme un nœud de maillage. Les noms se cherchent dans un Set, jamais comme clé
// d'un objet, où `constructor` ou `__proto__` répondraient. Le nom tiré du fichier repart tronqué : il vient d'un inconnu
function pieceOf(node, rules) {

	const name = typeof node.name === 'string' ? node.name : '';

	if(rules.profile === 'avatar') {
		if(new Set(rules.bones).has(baseName(node)) === false) {
			refuse('bone', { name: name.slice(0, 40), bones: rules.bones.join(', ') });
		}
	} else if(new Set(rules.pieces).has(baseName(node)) === false) {
		refuse('piece', { name: name.slice(0, 40), pieces: rules.pieces.join(', ') });
	}

	return baseName(node);

}

// La vue d'un accesseur sur le binaire, bornée avant toute lecture
function accessorView(json, bin, index, { componentTypes, type }) {

	const accessor = at(json.accessors, index);

	if(accessor.sparse !== undefined || accessor.type !== type || componentTypes[accessor.componentType] === undefined) {
		refuse('format');
	}

	const size = componentTypes[accessor.componentType], width = type === 'VEC3' ? 3 : 1;
	const count = accessor.count, view = at(json.bufferViews, accessor.bufferView);
	const viewOffset = view.byteOffset ?? 0, accessorOffset = accessor.byteOffset ?? 0;
	const stride = view.byteStride ?? size * width;

	if(
		Number.isInteger(count) === false || count < 1 ||
		view.buffer !== 0 || Number.isInteger(view.byteLength) === false || Number.isInteger(viewOffset) === false || Number.isInteger(accessorOffset) === false ||
		Number.isInteger(stride) === false || stride < size * width || stride > 252 ||
		viewOffset < 0 || accessorOffset < 0 || viewOffset + view.byteLength > bin.length ||
		accessorOffset + (count - 1) * stride + size * width > view.byteLength ||
		(viewOffset + accessorOffset) % size !== 0
	) {
		refuse('format');
	}

	return { data: new DataView(bin.buffer, bin.byteOffset + viewOffset + accessorOffset, view.byteLength - accessorOffset), count, stride, size };

}

const readIndex = ({ data, stride, size }, i) => size === 1 ? data.getUint8(i * stride) : size === 2 ? data.getUint16(i * stride, true) : data.getUint32(i * stride, true);

// La couleur de base d'un matériau, en sRGB : glTF l'écrit en linéaire, la cuisson la garde comme un hex sans dièse
function colorOf(json, primitive) {

	if(primitive.material === undefined) {
		return 'ffffff';
	}

	const factor = at(json.materials, primitive.material).pbrMetallicRoughness?.baseColorFactor ?? [1, 1, 1, 1];

	return finite(factor, 4).slice(0, 3).map(value => {
		const linear = Math.min(1, Math.max(0, value));
		const srgb = linear <= .0031308 ? linear * 12.92 : 1.055 * linear ** (1 / 2.4) - .055;
		return Math.round(srgb * 255).toString(16).padStart(2, '0');
	}).join('');

}

// Les primitives à lire, chacune avec son os, sa couleur et le nombre de ses triangles, comptés avant d'en lire un seul
function collectPrimitives(json, bin, rules) {

	const primitives = [];
	const { meshes, empties } = collectNodes(json, rules);
	let triangles = 0;

	for(const { node, matrix } of meshes) {

		const bone = pieceOf(node, rules);
		const mesh = at(json.meshes, node.mesh);

		if(Array.isArray(mesh.primitives) === false) {
			refuse('format');
		}

		for(const primitive of mesh.primitives) {

			if(primitive === null || typeof primitive !== 'object' || (primitive.mode ?? TRIANGLES) !== TRIANGLES || primitive.attributes === null || typeof primitive.attributes !== 'object') {
				refuse('format');
			}

			if(primitive.targets !== undefined) {
				refuse('rig');
			}

			if(primitives.length === rules.maxPrimitives) {
				refuse('nodes');
			}

			const position = accessorView(json, bin, primitive.attributes.POSITION, { componentTypes: { [FLOAT]: 4 }, type: 'VEC3' });
			const index = primitive.indices === undefined ? null : accessorView(json, bin, primitive.indices, { componentTypes: INDEX_TYPES, type: 'SCALAR' });
			const corners = index === null ? position.count : index.count;

			if(corners % 3 !== 0) {
				refuse('format');
			}

			triangles += corners / 3;

			primitives.push({ bone, color: colorOf(json, primitive), matrix, position, index, corners });

		}

	}

	// Le compte se finit avant de refuser : le refus dit combien le modèle en porte. Il ne coûte rien, chaque primitive
	// étant bornée par son tampon, lui-même borné par le poids
	if(triangles > rules.maxTriangles) {
		refuse('triangles', { count: triangles, max: rules.maxTriangles });
	}

	return { primitives, triangles, empties };

}

// Les sommets dépliés d'une primitive, dans le repère du modèle : trois par triangle, dans l'ordre qui garde la face avant
// quand la matrice retourne le repère
function unfold({ matrix, position, index, corners }) {

	const flip = determinant(matrix) < 0;
	const points = new Float64Array(corners * 3);

	const read = i => {
		const x = position.data.getFloat32(i * position.stride, true), y = position.data.getFloat32(i * position.stride + 4, true), z = position.data.getFloat32(i * position.stride + 8, true);
		if(Number.isFinite(x) === false || Number.isFinite(y) === false || Number.isFinite(z) === false) {
			refuse('format');
		}
		return [
			matrix[0] * x + matrix[4] * y + matrix[8] * z + matrix[12],
			matrix[1] * x + matrix[5] * y + matrix[9] * z + matrix[13],
			matrix[2] * x + matrix[6] * y + matrix[10] * z + matrix[14],
		];
	};

	for(let t = 0; t < corners; t += 3) {

		const order = flip ? [0, 2, 1] : [0, 1, 2];
		order.forEach((k, rank) => {
			const i = index === null ? t + k : readIndex(index, t + k);
			if(i >= position.count) {
				refuse('format');
			}
			points.set(read(i), (t + rank) * 3);
		});

	}

	if(points.every(Number.isFinite) === false) {
		refuse('format');
	}

	return { points };

}

// Euler XYZ comme three (Object3D.rotation), en matrice de colonnes
function rotation([x, y, z]) {

	const a = Math.cos(x), b = Math.sin(x), c = Math.cos(y), d = Math.sin(y), e = Math.cos(z), f = Math.sin(z);
	const ae = a * e, af = a * f, be = b * e, bf = b * f;

	return [
		c * e, af + be * d, bf - ae * d, 0,
		-c * f, ae - bf * d, be + af * d, 0,
		d, -b * c, a * c, 0,
		0, 0, 0, 1,
	];

}

const translation = ([x, y, z]) => [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, x, y, z, 1];

// La descente d'une pose en part de `descenteAssis`, comme avatarDescente() du skill 3d-perso la mesure sur une composition :
// chaque os tourne autour de son pivot sous son parent, et le point le plus bas des pièces des tibias se pose au sol
function descent(rules, feet, angles) {

	const worlds = {};

	const world = bone => {
		if(worlds[bone] === undefined) {
			const parent = rules.parents[bone];
			const start = parent === undefined ? [0, 0, 0] : rules.pivots[parent];
			const local = multiply(translation(rules.pivots[bone].map((value, axis) => value - start[axis])), rotation(angles[bone] ?? [0, 0, 0]));
			worlds[bone] = parent === undefined ? local : multiply(world(parent), local);
		}
		return worlds[bone];
	};

	let low = Infinity;

	for(const { bone, points } of feet) {
		const m = world(bone), [px, py, pz] = rules.pivots[bone];
		for(let i = 0; i < points.length; i += 3) {
			const x = points[i] - px, y = points[i + 1] - py, z = points[i + 2] - pz;
			low = Math.min(low, m[1] * x + m[5] * y + m[9] * z + m[13]);
		}
	}

	return +(low / rules.anchors.descenteAssis).toFixed(5);

}

function read(rules, bytes) {

	if(bytes.length > rules.maxBytes) {
		refuse('weight', { size: bytes.length, max: rules.maxBytes });
	}

	const { json, bin } = readContainer(bytes);

	checkContent(json);

	const { primitives, triangles, empties } = collectPrimitives(json, bin, rules);
	const pieces = primitives.map(primitive => ({ bone: primitive.bone, color: primitive.color, ...unfold(primitive) }));

	return rules.profile === 'avatar' ? readAvatar(rules, pieces, triangles) : readDecor(rules, pieces, triangles, empties);

}

// La boite du modèle entier
function boxOf(pieces) {

	const min = [Infinity, Infinity, Infinity], max = [-Infinity, -Infinity, -Infinity];

	for(const { points } of pieces) {
		for(let i = 0; i < points.length; i++) {
			min[i % 3] = Math.min(min[i % 3], points[i]);
			max[i % 3] = Math.max(max[i % 3], points[i]);
		}
	}

	return { min, max };

}

// Un avatar : chaque os pourvu, l'emprise des règles, les pieds au sol
function readAvatar(rules, pieces, triangles) {

	for(const bone of rules.bones) {
		if(pieces.some(piece => piece.bone === bone) === false) {
			refuse('missing', { bone });
		}
	}

	const { min, max } = boxOf(pieces);
	const [low, high] = rules.bounds;

	if([0, 1, 2].some(axis => max[axis] > high[axis] || (axis !== 1 && min[axis] < low[axis]))) {
		refuse('bounds');
	}

	// Au repos, le point le plus bas des pieds est au sol, et rien ne passe dessous
	const feet = pieces.filter(piece => piece.bone === 'tibiaG' || piece.bone === 'tibiaD');
	const sole = Math.min(...feet.map(({ points }) => Math.min(...points.filter((_, i) => i % 3 === 1))));

	if(Math.abs(sole) > rules.ground || min[1] < -rules.ground) {
		refuse('ground');
	}

	const open = skullOpen(rules, pieces.filter(piece => piece.bone === 'tete'));

	if(open.open > 0) {
		refuse('skull', open);
	}

	return { pieces, triangles, min, max, feet };

}

// Le dessus de la tête, vu d'en haut (`skull` des règles) : les rayons verticaux tirés autour du pivot de la tête qui ne
// rencontrent, au-dessus de lui, aucune face avant d'une pièce de la tête. Le site ne dessine que la face avant, et une face
// vue de dessus l'est quand ses sommets tournent dans le sens direct autour de +y. Le crâne de corps.glb s'ouvre en haut sous
// sa calotte, `tete.002` : la retirer sans rien qui referme le dessus laisse voir le sol au travers de la tête
function skullOpen(rules, heads) {

	const { radius, rays } = rules.skull;
	const [cx, cy, cz] = rules.pivots.tete;
	const faces = [];

	for(const { points } of heads) {
		for(let i = 0; i < points.length; i += 9) {
			const [ax, ay, az, bx, by, bz, qx, qy, qz] = points.subarray(i, i + 9);
			const area = (bz - az) * (qx - ax) - (bx - ax) * (qz - az);
			if(area > 0 && Math.max(ay, by, qy) > cy) {
				faces.push({ ax, ay, az, bx, by, bz, qx, qy, qz, area });
			}
		}
	}

	// Une face rencontrée l'est par l'intérieur ou sur un bord : les poids barycentriques du point dans le plan horizontal
	const covers = (x, z) => faces.some(({ ax, ay, az, bx, by, bz, qx, qy, qz, area }) => {
		const u = ((qz - bz) * (x - bx) - (qx - bx) * (z - bz)) / area;
		const v = ((az - qz) * (x - qx) - (ax - qx) * (z - qz)) / area;
		const w = 1 - u - v;
		return u >= -1e-9 && v >= -1e-9 && w >= -1e-9 && u * ay + v * by + w * qy > cy;
	});

	let open = 0, total = 0;

	for(let i = 0; i < rays; i++) {
		for(let j = 0; j < rays; j++) {
			const dx = radius * (2 * i / (rays - 1) - 1), dz = radius * (2 * j / (rays - 1) - 1);
			if(dx * dx + dz * dz > radius * radius) {
				continue;
			}
			total++;
			if(covers(cx + dx, cz + dz) === false) {
				open++;
			}
		}
	}

	return { open, rays: total };

}

// L'emprise d'un objet du décor est centrée sur l'origine, où la scène le pose : `length` le long de z, `width` le long de
// x, et rien sous le sol au-delà de la tolérance
const outside = ({ length, width, height }, ground, [x, y, z]) => Math.abs(x) > width / 2 || Math.abs(z) > length / 2 || y > height || y < -ground;

// Un objet du décor : ses pièces obligatoires, ses couleurs, son emprise, son sol, puis ses ancres
function readDecor(rules, pieces, triangles, empties) {

	for(const piece of rules.required) {
		if(pieces.some(({ bone }) => bone === piece) === false) {
			refuse('missing', { piece });
		}
	}

	const colors = new Set(pieces.map(piece => piece.color)).size;

	if(colors > rules.maxColors) {
		refuse('colors', { count: colors, max: rules.maxColors });
	}

	const { min, max } = boxOf(pieces);

	// Le sol avant l'emprise : un modèle enfoncé sortirait aussi de l'emprise par le bas, et sa correction est de le poser
	if(Math.abs(min[1]) > rules.ground) {
		refuse('ground');
	}

	if(outside(rules.bounds, rules.ground, min) || outside(rules.bounds, rules.ground, max)) {
		refuse('bounds', rules.bounds);
	}

	// Une ancre est un nœud vide nommé d'après elle, les autres nœuds vides (un groupe que l'export garde) ne se lisent pas.
	// Une ancre posée deux fois se lirait au hasard de l'ordre du fichier, une ancre hors de l'emprise ne désigne rien du modèle
	const names = new Set(rules.anchors), anchors = {};

	for(const { node, matrix } of empties) {

		const name = baseName(node);

		if(names.has(name) === false) {
			continue;
		}

		const point = [matrix[12], matrix[13], matrix[14]];

		if(Object.hasOwn(anchors, name) || outside(rules.bounds, rules.ground, point)) {
			refuse('anchor', { name });
		}

		anchors[name] = point.map(value => +value.toFixed(5));

	}

	// L'ancre du sommet se calcule quand le fichier ne la pose pas : au milieu de l'emprise, au point le plus haut
	if(rules.top !== undefined && Object.hasOwn(anchors, rules.top) === false) {
		anchors[rules.top] = [(min[0] + max[0]) / 2, max[1], (min[2] + max[2]) / 2].map(value => +value.toFixed(5));
	}

	return { pieces, triangles, min, max, anchors };

}

export function sceneUploadCheck(rules, bytes) {
	return { triangles: read(rules, bytes).triangles };
}

// Les normales d'un modèle cuit, calculées de ses seules positions quantifiées et écrites en place dans la section Int8 du
// tampon, celle d'entrée n'étant jamais lue : le site ignore les normales du fichier et garde seul la main sur l'ombrage.
// Sur une même pièce nommée, toutes couleurs confondues, les coins qui partagent une position reçoivent la somme des normales
// de leurs faces, pondérées par l'aire. Un coin dont cette normale s'écarte de sa face de plus de `angle` degrés
// (`smoothAngle` des règles) prend celle de la face. L'avatar le règle à 60°, comme redresser() de l'avatar composé : un
// angle droit s'arrondit comme dans l'éditeur, seul un pli qui écarte les normales de plus de 120° reste vif, et une pièce
// mince repliée ne s'allume pas à contre-jour. Le décor le règle à 0 : chaque face garde la sienne, à facettes comme les
// bâtiments du site.
// Partir des positions quantifiées, et non des flottants du fichier, fait qu'une conversion et une reconversion du binaire
// gardé rendent les mêmes octets, et qu'un second passage ne change rien.
export function sceneUploadSmooth(buffer, { box, parts }, vertices, angle) {

	// Arrondi pour qu'un angle rond donne son cosinus exact : cos(60°) se calcule 0,5000000000000001
	const limit = +Math.cos(angle * Math.PI / 180).toFixed(12);
	const positions = new Int16Array(buffer, 0, vertices * 3), normals = new Int8Array(buffer, vertices * 6, vertices * 3);
	const size = [0, 1, 2].map(axis => box[1][axis] - box[0][axis]);
	const point = corner => [0, 1, 2].map(axis => box[0][axis] + (positions[corner * 3 + axis] + 32767) / 65534 * size[axis]);
	const key = corner => positions[corner * 3] + ',' + positions[corner * 3 + 1] + ',' + positions[corner * 3 + 2];

	for(const bone of new Set(parts.map(part => part.name))) {

		const corners = parts.filter(part => part.name === bone);
		const sums = new Map(), faces = new Map();

		for(const { offset, count } of corners) {
			for(let t = offset; t < offset + count; t += 3) {
				const [a, b, c] = [t, t + 1, t + 2].map(point);
				const u = [b[0] - a[0], b[1] - a[1], b[2] - a[2]], v = [c[0] - a[0], c[1] - a[1], c[2] - a[2]];
				const face = [u[1] * v[2] - u[2] * v[1], u[2] * v[0] - u[0] * v[2], u[0] * v[1] - u[1] * v[0]];
				faces.set(t, face);
				for(let corner = t; corner < t + 3; corner++) {
					const sum = sums.get(key(corner)) ?? sums.set(key(corner), [0, 0, 0]).get(key(corner));
					sum[0] += face[0];
					sum[1] += face[1];
					sum[2] += face[2];
				}
			}
		}

		for(const { offset, count } of corners) {
			for(let t = offset; t < offset + count; t += 3) {
				const face = faces.get(t), faceLength = Math.hypot(...face);
				for(let corner = t; corner < t + 3; corner++) {
					const sum = sums.get(key(corner)), sumLength = Math.hypot(...sum);
					const smooth = limit < 1 && sumLength > 0 && (faceLength === 0 || (sum[0] * face[0] + sum[1] * face[1] + sum[2] * face[2]) / (sumLength * faceLength) >= limit);
					const [n, length] = smooth ? [sum, sumLength] : [face, faceLength || 1];
					for(let axis = 0; axis < 3; axis++) {
						normals[corner * 3 + axis] = Math.round(Math.max(-1, Math.min(1, n[axis] / length)) * 127);
					}
				}
			}
		}

	}

}

// Le modèle au format cuit : une pièce par os, ou par pièce du décor, et par couleur, rigide, ses positions quantifiées sur sa
// boite et ses normales sur un octet, comme bake/bake.mjs les écrit. L'avatar y ajoute les poses des règles avec la descente
// de ses propres pieds, le décor ses ancres, et le corps du décor (`body`) n'est pas nommé, comme celui d'un modèle du site
export function sceneUploadBake(rules, bytes) {

	const reading = read(rules, bytes);
	const { pieces, triangles, min, max } = reading;
	const avatar = rules.profile === 'avatar';

	const groups = new Map();

	for(const bone of avatar ? rules.bones : rules.pieces) {
		for(const piece of pieces.filter(piece => piece.bone === bone)) {
			const key = bone + '#' + piece.color;
			if(groups.has(key) === false) {
				groups.set(key, { bone, color: piece.color, pieces: [] });
			}
			groups.get(key).pieces.push(piece);
		}
	}

	const vertices = triangles * 3;
	const buffer = new ArrayBuffer(vertices * 9);
	const positions = new Int16Array(buffer, 0, vertices * 3);
	const box = [min.map(value => +value.toFixed(5)), max.map(value => +value.toFixed(5))];
	const parts = [];
	let offset = 0;

	for(const { bone, color, pieces: group } of groups.values()) {

		const start = offset;

		for(const { points } of group) {
			for(let i = 0; i < points.length; i++) {
				const axis = i % 3, size = box[1][axis] - box[0][axis];
				positions[offset * 3 + i] = size > 0 ? Math.max(-32767, Math.min(32767, Math.round((points[i] - box[0][axis]) / size * 65534) - 32767)) : -32767;
			}
			offset += points.length / 3;
		}

		parts.push({ color, sway: 0, offset: start, count: offset - start, name: bone });

	}

	sceneUploadSmooth(buffer, { box, parts }, vertices, rules.smoothAngle);

	const model = {
		d: +Math.max(max[0] - min[0], max[2] - min[2]).toFixed(5),
		h: +max[1].toFixed(5),
		aligned: false,
		triangles,
		colors: new Set(parts.map(part => part.color)).size,
		box,
		offset: 0,
		parts,
	};

	if(avatar) {
		model.pivots = rules.pivots;
		model.parents = rules.parents;
		model.anchors = rules.anchors;
		model.poses = Object.fromEntries(Object.entries(rules.poses).map(([name, angles]) => [name, { descente: descent(rules, reading.feet, angles), ...angles }]));
	} else {
		// Le nom ne sert qu'au lissage, qui ne soude jamais deux pièces : le corps le perd une fois les normales faites
		for(const part of parts.filter(part => part.name === rules.body)) {
			delete part.name;
		}
		model.anchors = reading.anchors;
	}

	const index = { vertices, colors: 0, models: { [avatar ? 'avatar' : rules.family]: model } };

	return { rules: rules.version, triangles, index, buffer: Buffer.from(buffer).toString('base64') };

}

// fileURLToPath() et non `pathname`, qui garde encodés les espaces et les accents d'un chemin
if(process.argv[1] === fileURLToPath(import.meta.url)) {

	const option = name => process.argv.slice(2).find(arg => arg.startsWith('--' + name + '='))?.slice(name.length + 3);

	// La reconversion d'un binaire déjà cuit par le serveur : il vient de lui, pas d'un inconnu, mais un index qui ne
	// décrit pas le tampon reste une panne, qui sort en erreur
	if(process.argv.includes('--smooth')) {

		if(option('rules') === undefined || option('in') === undefined || option('index') === undefined) {
			process.stderr.write('node upload.mjs --smooth --rules=<règles.json> --in=<binaire cuit> --index=<index.json>\n');
			process.exit(2);
		}

		const rules = JSON.parse(readFileSync(option('rules'), 'utf8'));
		const bytes = readFileSync(option('in'));
		const index = JSON.parse(readFileSync(option('index'), 'utf8'));
		const models = Object.values(index.models ?? {});
		const vertices = index.vertices;

		if(
			Number.isInteger(vertices) === false || bytes.length !== vertices * 9 || models.length !== 1 ||
			models[0].parts.some(({ offset, count }) => offset % 3 !== 0 || count % 3 !== 0 || offset + count > vertices)
		) {
			process.stderr.write('index sans rapport avec le binaire\n');
			process.exit(1);
		}

		const buffer = bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.length);
		sceneUploadSmooth(buffer, models[0], vertices, rules.smoothAngle);

		// Pas de process.exit() après l'écriture : sur un tube, il couperait la sortie avant qu'elle soit lue en entier
		process.stdout.write(JSON.stringify({ buffer: Buffer.from(buffer).toString('base64') }));

	}
	else {

		const mode = process.argv.includes('--bake') ? 'bake' : process.argv.includes('--check') ? 'check' : null;

		if(mode === null || option('rules') === undefined || option('in') === undefined) {
			process.stderr.write('node upload.mjs --check|--bake --rules=<règles.json> [--family=<famille>] --in=<fichier.glb>\n');
			process.exit(2);
		}

		const rules = sceneUploadRules(JSON.parse(readFileSync(option('rules'), 'utf8')), option('family') ?? null);
		const bytes = readFileSync(option('in'));
		let out;

		try {
			out = mode === 'bake' ? sceneUploadBake(rules, bytes) : sceneUploadCheck(rules, bytes);
		}
		catch(error) {
			if(error instanceof SceneUploadRefusal === false) {
				throw error;
			}
			out = { refusal: error.refusal, values: error.values };
		}

		process.stdout.write(JSON.stringify(out));

	}

}
