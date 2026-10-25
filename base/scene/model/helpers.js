// Helpers partagés des silhouettes. Les modéliseurs les lisent et ne les modifient pas : un besoin neuf se code dans le fichier de l'espèce.
// MeshToonMaterial ignore flatShading : l'éclairage suit les normales de la géométrie. Celles des primitives de three sont lissées
// (un icosaèdre paraît rond), facets() les rend plates pour un aspect taillé.
import * as THREE from 'three';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';

// Icosaèdre (detail 0 : 20 triangles, 1 : 80) et octaèdre (8 triangles)
export const ico = (r, detail = 0) => new THREE.IcosahedronGeometry(r, detail);
export const oct = r => new THREE.OctahedronGeometry(r, 0);
// Tube ouvert (2 × sides triangles) et cône fermé (2 × sides triangles, la base comprise)
export const stem = (r0, r1, h, sides = 4) => new THREE.CylinderGeometry(r0, r1, h, sides, 1, true);
export const cone = (r, h, sides = 4) => new THREE.ConeGeometry(r, h, sides);
// Répète une pièce autour de l'axe vertical : make(angle, rang)
export const around = (count, make) => Array.from({ length: count }, (_, i) => make(i * Math.PI * 2 / count, i));

// Ne garde que position et normale : mergeGeometries refuse des pièces aux attributs différents
export const flat = g => {
	const source = g.index ? g.toNonIndexed() : g;
	if(!source.attributes.normal) source.computeVertexNormals();
	const out = new THREE.BufferGeometry();
	out.setAttribute('position', source.attributes.position);
	out.setAttribute('normal', source.attributes.normal);
	return out;
};
// Normales par face : chaque triangle reçoit une lumière uniforme, comme un objet taillé
export const facets = g => {
	const out = g.index ? g.toNonIndexed() : g.clone();
	out.deleteAttribute('normal');
	out.computeVertexNormals();
	return out;
};
// Fusionne plusieurs pièces d'une même couleur en une géométrie
export const merge = list => mergeGeometries(list.map(flat));
export { mergeGeometries };
export const triangles = g => (g.index ? g.index.count : g.attributes.position.count) / 3;

// Les objets et les personnages, repris des modèles du bourg de la scène comptable : la palette P et des pièces
// en boites franches, pour que les deux chantiers taillent dans les mêmes couleurs
export const P = {
	creme: '#efe3cc', pierre: '#d5c6ab', bois: '#c79a62', boisSombre: '#6f4b32', tole: '#aab3b8', toleSombre: '#6f7a80',
	tuile: '#b9553a', ardoise: '#55677a', vitre: '#9fd0dc', encre: '#1d4b53', rouge: '#d6452f', bleu: '#3b6fb5',
	blanc: '#f6f3ea', noir: '#2c3136', or: '#f2b632', orSombre: '#c98a12', paille: '#e2b74e', peau: '#f0c9a0',
	peauSombre: '#b98058', herbe: '#8fbf72', feuille: '#4d9a4a', feuilleSombre: '#2f7d3d', feuilleClaire: '#9ccc65',
	terre: '#9a6b48', route: '#dccfae', gris: '#8d9499',
};

// Boite centrée en (x, y, z), de taille (sx, sy, sz). 12 triangles
export const box = (x, y, z, sx, sy, sz) => facets(new THREE.BoxGeometry(sx, sy, sz).translate(x, y, z));
// Boite posée : (x, z) est le centre de sa base, y0 le dessous
export const block = (x, y0, z, sx, sy, sz) => box(x, y0 + sy / 2, z, sx, sy, sz);
// Cylindre vertical à pans, posé sur y0. 4 × sides triangles
export const drum = (x, y0, z, r, h, sides = 8, rTop = r) => facets(new THREE.CylinderGeometry(rTop, r, h, sides).translate(x, y0 + h / 2, z));
// Roue : cylindre couché, axe selon X, centré en (x, y, z). 4 × sides triangles
export const wheel = (x, y, z, r, w, sides = 10) => facets(new THREE.CylinderGeometry(r, r, w, sides).rotateZ(Math.PI / 2).translate(x, y, z));
// Prisme : un contour 2D [[x, y], …] dans le plan XY, tiré sur la profondeur depth selon Z, centré en z
export const prism = (points, depth, z = 0) => {
	const shape = new THREE.Shape(points.map(([x, y]) => new THREE.Vector2(x, y)));
	return facets(new THREE.ExtrudeGeometry(shape, { depth, bevelEnabled: false }).translate(0, 0, z - depth / 2));
};

// Ramasse les pièces d'un objet : add(geometry, color, name, pivot). Un objet ne bouge pas au vent, d'où sway 0.
// Le pivot appartient à l'os que name désigne, et plusieurs pièces portent le même os : il se range une fois dans pivots,
// et deux pivots différents pour un même os sont refusés, puisque la scène n'en tournerait qu'un
export const collector = () => {
	const parts = [], pivots = {};
	const add = (geometry, color, name, pivot) => {
		const part = { geometry, color, sway: 0 };
		if(name !== undefined) {
			part.name = name;
		}
		if(pivot !== undefined) {
			if(pivots[name] !== undefined && pivots[name].some((n, axis) => n !== pivot[axis])) {
				throw new Error('L\'os ' + name + ' reçoit deux pivots');
			}
			pivots[name] = pivot;
		}
		parts.push(part);
		return geometry;
	};
	return { parts, pivots, add };
};
// Emprise et hauteur mesurées sur les pièces, pour rendre d et h sans les recopier
export const measure = parts => {
	const bounds = new THREE.Box3();
	for(const { geometry } of parts) {
		geometry.computeBoundingBox();
		bounds.union(geometry.boundingBox);
	}
	const size = bounds.getSize(new THREE.Vector3());
	return { d: Math.max(size.x, size.z), h: bounds.max.y };
};
