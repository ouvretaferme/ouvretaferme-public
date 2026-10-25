// Lecteur des modèles cuits (static/model/models.json et models.bin, écrits par bake/bake.mjs).
// Module ES sans import : three est passé en argument, pour que la page choisisse d'où elle le charge.
// Le fichier binaire porte, pour tous les modèles à la suite, les positions en Int16 puis les normales en Int8, et enfin les
// couleurs par sommet en Uint8 des seules pièces qui en ont, en sRGB. Une position se lit sur la boite du modèle (box), une normale
// sur [-1, 1]. Une pièce a des couleurs par sommet quand l'index lui donne un colorOffset : un fichier qui n'en a aucune se lit
// tel quel, et un lecteur qui ne les connait pas ignore la fin du fichier. Les pivots et les ancres d'un personnage sont
// écrits en clair dans l'index, hors de la boite, parce qu'un pivot quantifié décalerait tout un membre.
// La page charge les deux fichiers comme elle l'entend et passe ici l'index décodé et le tampon.

// Huit bits suffisent en sRGB, pas en linéaire, où les tons sombres feraient des paliers : la cuisson écrit en sRGB, le rendu lit
// du linéaire, par la conversion de la norme sRGB
const LINEAR = Array.from({ length: 256 }, (_, value) => {
	const c = value / 255;
	return (c <= .04045) ? c / 12.92 : ((c + .055) / 1.055) ** 2.4;
});

// Rend le contrat d'un module, { d, h, aligned, parts: [{ geometry, color, sway, name }] }, sans exécuter le module,
// et pour un personnage articulé pivots, parents et anchors
export function sceneBuildModel(THREE, { index, buffer }, slug) {
	const model = index.models[slug];
	const [min, max] = model.box;
	const positions = new Int16Array(buffer, 0, index.vertices * 3);
	const normals = new Int8Array(buffer, index.vertices * 6, index.vertices * 3);
	const parts = model.parts.map(part => {
		const position = new Float32Array(part.count * 3), normal = new Float32Array(part.count * 3);
		const start = (model.offset + part.offset) * 3;
		for(let i = 0; i < part.count * 3; i++) {
			const axis = i % 3;
			position[i] = min[axis] + (positions[start + i] + 32767) / 65534 * (max[axis] - min[axis]);
			normal[i] = normals[start + i] / 127;
		}
		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.BufferAttribute(position, 3));
		geometry.setAttribute('normal', new THREE.BufferAttribute(normal, 3));
		if(part.colorOffset !== undefined) {
			// Une vue par pièce : un index qui déborde du fichier lève une erreur, comme pour les positions, au lieu de peindre en NaN
			const colors = new Uint8Array(buffer, index.vertices * 9 + part.colorOffset * 3, part.count * 3);
			const color = Float32Array.from(colors, value => LINEAR[value]);
			geometry.setAttribute('color', new THREE.BufferAttribute(color, 3));
		}
		const out = { geometry, color: part.color, sway: part.sway };
		if(part.name !== undefined) {
			out.name = part.name;
		}
		return out;
	});
	const out = { d: model.d, h: model.h, aligned: model.aligned, parts };
	for(const key of ['pivots', 'parents', 'anchors']) {
		if(model[key] !== undefined) {
			out[key] = structuredClone(model[key]);
		}
	}
	return out;
}
