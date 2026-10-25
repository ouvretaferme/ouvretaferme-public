// Courgette en fruits (variante 22, retenue par Vincent le 2026-10-08) : le pied de courgette.js, et trois courgettes
// grossies deux fois qui partent du cœur, relevées à travers les feuilles, la fleur jaune au bout. À taille réelle un fruit ne se voit pas
// à l'échelle de la scène, et couché le long de la planche il se confond avec ses voisins en une ligne verte.
// Les fruits et leurs fleurs portent le nom « fruit », pour que la scène ne les montre qu'à la récolte.
import { build as buildPlant } from './courgette.js';

export function build(THREE, helpers) {
	const FRUIT = '#2f6b2a'; const FLOWER = '#f4b41a';
	// Longueur d'un fruit en mètres pour un pied de 1 m, et son inclinaison depuis la verticale
	const LENGTH = .5, TILT = .85, COUNT = 3, SIDES = 6;
	// Le socle de la scène grossit la hauteur 1,25 fois plus que l'emprise : les fruits sont écrasés d'autant, pour en sortir
	// à leur forme. Le pied, modelé pour ce socle, ne l'est pas
	const STRETCH = 1.25;

	const plant = buildPlant(THREE, helpers);
	const box = new THREE.Box3();
	for(const part of plant.parts) {
		part.geometry.computeBoundingBox();
		box.union(part.geometry.boundingBox);
	}
	// Le pied est modelé à une échelle libre : les fruits se dessinent dans la sienne
	const unit = Math.max(box.max.x - box.min.x, box.max.z - box.min.z) / plant.d;
	const jitter = (i, k = 0) => { const x = Math.sin(i * 12.9898 + k * 78.233) * 43758.5453; return x - Math.floor(x); };

	const fruits = [], flowers = [];
	for(let i = 0; i < COUNT; i++) {
		const length = LENGTH * unit * (.88 + jitter(i, 3) * .24), r = length * .115;
		const tilt = TILT + (jitter(i, 4) - .5) * .25, azimuth = .6 + i * Math.PI * 2 / COUNT + (jitter(i, 5) - .5) * .5;
		const place = g => g.translate(0, .06 * unit, 0).rotateZ(-tilt).rotateY(azimuth).translate(0, .05 * unit, 0).scale(1, 1 / STRETCH, 1).translate(0, box.min.y, 0);
		// Le fruit le long de +Y, pédoncule au cœur du pied : mince à l'attache, renflé vers la fleur
		const profile = [[.3, 0], [.85, .16], [1, .75], [.45, 1]].map(([k, t]) => new THREE.Vector2(k * r, t * length));
		fruits.push(place(new THREE.LatheGeometry(profile, SIDES)));
		// La fleur fanée au bout : une trompette ouverte à cinq pans
		flowers.push(place(new THREE.ConeGeometry(r * 1.4, length * .28, 5, 1, true).rotateX(Math.PI).translate(0, length * 1.1, 0)));
	}
	const parts = [
		...plant.parts,
		{ name: 'fruit', color: FRUIT, geometry: helpers.merge(fruits) },
		{ name: 'fruit', color: FLOWER, geometry: helpers.merge(flowers) },
	];
	// Les fruits dépassent du feuillage : l'emprise annoncée suit, pour que le socle garde le pied à sa taille
	const whole = box.clone();
	for(const part of parts.slice(-2)) {
		part.geometry.computeBoundingBox();
		whole.union(part.geometry.boundingBox);
	}
	const size = whole.getSize(new THREE.Vector3()), d = Math.max(size.x, size.z) / unit;
	return { d, h: size.y / unit, parts };
}
