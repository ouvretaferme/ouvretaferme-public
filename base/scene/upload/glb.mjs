// L'écriture d'un modèle en glTF binaire (.glb), commune aux compositeurs de chaque profil et aux exemples du skill.
// Le module n'importe que node : les pièces lui arrivent déjà nommées, chacune avec sa géométrie three et sa couleur.

// glTF écrit la couleur de base en linéaire
const linear = hex => [1, 3, 5].map(i => {
	const value = parseInt(hex.slice(i, i + 2), 16) / 255;
	return +(value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4).toFixed(6);
});

// Un glTF : un nœud par pièce, nommé `<nom>.<rang>` d'après le nom qu'elle porte, sa géométrie telle quelle et sa couleur,
// puis un nœud vide par ancre, nommé d'après elle et posé à sa place. Les teintes par sommet (le fondu d'une chevelure) ne
// se gardent pas : une pièce téléversée ne prend qu'une couleur.
//   parts : [{ name, color: '#rrggbb', geometry }]
//   anchors : { nom: [x, y, z] }
export function sceneUploadWriteGlb(parts, anchors = {}) {

	const json = { asset: { version: '2.0', generator: 'Ouvretaferme' }, scene: 0, scenes: [{ nodes: [] }], nodes: [], meshes: [], materials: [], accessors: [], bufferViews: [], buffers: [] };
	const chunks = [], ranks = {};
	let length = 0;

	const view = (bytes, target) => {
		chunks.push(bytes, Buffer.alloc((4 - bytes.length % 4) % 4));
		json.bufferViews.push({ buffer: 0, byteOffset: length, byteLength: bytes.length, target });
		length += bytes.length + (4 - bytes.length % 4) % 4;
		return json.bufferViews.length - 1;
	};

	parts.forEach((part, k) => {

		const positions = Float32Array.from(part.geometry.attributes.position.array);
		const min = [0, 1, 2].map(axis => Math.min(...positions.filter((_, i) => i % 3 === axis)));
		const max = [0, 1, 2].map(axis => Math.max(...positions.filter((_, i) => i % 3 === axis)));

		json.accessors.push({ bufferView: view(Buffer.from(positions.buffer), 34962), componentType: 5126, count: positions.length / 3, type: 'VEC3', min, max });

		const primitive = { attributes: { POSITION: json.accessors.length - 1 }, material: k };

		if(part.geometry.index !== null) {
			const indices = Uint32Array.from(part.geometry.index.array);
			json.accessors.push({ bufferView: view(Buffer.from(indices.buffer), 34963), componentType: 5125, count: indices.length, type: 'SCALAR' });
			primitive.indices = json.accessors.length - 1;
		}

		ranks[part.name] = (ranks[part.name] ?? 0) + 1;
		const name = part.name + '.' + String(ranks[part.name]).padStart(3, '0');

		json.materials.push({ name: part.color, pbrMetallicRoughness: { baseColorFactor: [...linear(part.color), 1], metallicFactor: 0, roughnessFactor: 1 } });
		json.meshes.push({ name, primitives: [primitive] });
		json.nodes.push({ name, mesh: k });
		json.scenes[0].nodes.push(k);

	});

	for(const [name, translation] of Object.entries(anchors)) {
		json.nodes.push({ name, translation });
		json.scenes[0].nodes.push(json.nodes.length - 1);
	}

	json.buffers.push({ byteLength: length });

	return { json, bin: Buffer.concat(chunks) };

}

export function sceneUploadPack({ json, bin }) {

	let text = Buffer.from(JSON.stringify(json));
	text = Buffer.concat([text, Buffer.alloc((4 - text.length % 4) % 4, 0x20)]);

	const header = Buffer.alloc(12), jsonHeader = Buffer.alloc(8), binHeader = Buffer.alloc(8);
	header.writeUInt32LE(0x46546c67, 0);
	header.writeUInt32LE(2, 4);
	header.writeUInt32LE(12 + 8 + text.length + 8 + bin.length, 8);
	jsonHeader.writeUInt32LE(text.length, 0);
	jsonHeader.writeUInt32LE(0x4e4f534a, 4);
	binHeader.writeUInt32LE(bin.length, 0);
	binHeader.writeUInt32LE(0x004e4942, 4);

	return Buffer.concat([header, jsonHeader, text, binHeader, bin]);

}
