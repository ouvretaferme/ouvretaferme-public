// Pièces de géométrie du socle des scènes, communes à la fusion des modèles (crop.js), aux formes des fruits (fruit.js) et aux
// personnages (figure.js).
// Module ES sans import : three et ses compléments sont passés en argument, comme pour static/js/model.js.
// MeshToonMaterial ignore flatShading : l'éclairage suit les normales de la géométrie, que facets() rend plates et smooth() rondes.

export function sceneCreateGeometry(THREE, addons) {

	// Au-delà de 80° entre deux faces, l'arête reste vive : une primitive à six côtés fait 60° d'un pan à l'autre, un fond de
	// cône bien davantage
	const CREASE = Math.cos(80 * Math.PI / 180);

	// mergeGeometries refuse des pièces aux attributs différents : seules la position et la normale passent
	const flat = geometry => {
		const source = geometry.index ? geometry.toNonIndexed() : geometry;
		if(!source.attributes.normal) {
			source.computeVertexNormals();
		}
		const out = new THREE.BufferGeometry();
		out.setAttribute('position', source.attributes.position);
		out.setAttribute('normal', source.attributes.normal);
		return out;
	};

	// Normales par face : chaque triangle reçoit une lumière uniforme, comme un objet taillé
	const facets = geometry => {
		const out = geometry.index ? geometry.toNonIndexed() : geometry.clone();
		out.deleteAttribute('normal');
		out.computeVertexNormals();
		return out;
	};

	// Normales arrondies : chaque sommet prend la moyenne des faces qui le touchent au même endroit, pondérées par leur aire.
	// La géométrie est attendue sans index, trois sommets par triangle
	const smooth = geometry => {
		const position = geometry.attributes.position, count = position.count;
		const around = new Map(), a = new THREE.Vector3(), b = new THREE.Vector3(), c = new THREE.Vector3();
		const weighted = [];
		for(let i = 0; i < count; i += 3) {
			a.fromBufferAttribute(position, i);
			b.fromBufferAttribute(position, i + 1);
			c.fromBufferAttribute(position, i + 2);
			weighted.push(c.sub(b).cross(a.sub(b)).clone());
		}
		// Position arrondie au centième de millimètre : deux pièces se rejoignent par calcul, pas toujours au bit près
		const key = i => `${Math.round(position.getX(i) * 1e5)},${Math.round(position.getY(i) * 1e5)},${Math.round(position.getZ(i) * 1e5)}`;
		for(let i = 0; i < count; i++) {
			const k = key(i);
			if(!around.has(k)) {
				around.set(k, []);
			}
			around.get(k).push(Math.floor(i / 3));
		}
		const normals = new Float32Array(count * 3), own = new THREE.Vector3(), other = new THREE.Vector3(), sum = new THREE.Vector3();
		for(let i = 0; i < count; i++) {
			own.copy(weighted[Math.floor(i / 3)]).normalize();
			sum.set(0, 0, 0);
			for(const face of around.get(key(i))) {
				if(own.dot(other.copy(weighted[face]).normalize()) > CREASE) {
					sum.add(weighted[face]);
				}
			}
			(sum.lengthSq() > 0 ? sum.normalize() : own).toArray(normals, i * 3);
		}
		geometry.setAttribute('normal', new THREE.BufferAttribute(normals, 3));
		return geometry;
	};

	// Une couleur unie posée sur chaque sommet d'une pièce, qui se dessine alors avec d'autres en un seul appel
	const paint = (piece, color) => {
		const values = new Float32Array(piece.attributes.position.count * 3);
		for(let i = 0; i < values.length; i += 3) {
			values[i] = color.r;
			values[i + 1] = color.g;
			values[i + 2] = color.b;
		}
		piece.setAttribute('color', new THREE.BufferAttribute(values, 3));
		return piece;
	};

	return {
		flat,
		facets,
		smooth,
		paint,
		// Plusieurs pièces d'une même couleur en une géométrie
		merge: list => addons.mergeGeometries(list.map(flat)),
		// Icosaèdre, 20 triangles au détail 0 et 80 au détail 1
		ico: (r, detail = 0) => new THREE.IcosahedronGeometry(r, detail),
		// Cône fermé, 2 × sides triangles, la base comprise
		cone: (r, h, sides = 4) => new THREE.ConeGeometry(r, h, sides),
		triangles: geometry => (geometry.index ? geometry.index.count : geometry.attributes.position.count) / 3
	};

}
