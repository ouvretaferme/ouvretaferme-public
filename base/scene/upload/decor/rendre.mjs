// Rend un bâtiment .glb comme l'éditeur du décor du site l'affichera, sans navigateur ni Blender :
//   node rendre.mjs <modele.glb> <dossier des images>
// Le modèle passe d'abord par le validateur du site, qui le convertit : les images montrent donc ce qui partira, avec les
// normales que le site calcule, et non celles du fichier. Un modèle refusé n'est pas rendu, le refus est imprimé.
// Imprime d'abord une ligne par pièce du modèle converti (son nom, sa couleur, ses triangles, et la clé de la palette du site
// qui porte cette couleur, ou `pierre <teinte>` pour la pierre d'un bâtiment composé, ou `hors palette`), puis ses ancres,
// puis une ligne par image : face.png, trois-quarts.png, dos.png et dessus.png.
// Fond transparent, éclairage et caméra de l'éditeur du décor, seule la face avant de chaque triangle dessinée.
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { deflateSync } from 'node:zlib';
import { join } from 'node:path';

const here = name => new URL(name, import.meta.url);

const [input, output] = process.argv.slice(2);

if(output === undefined) {
	process.stderr.write('node rendre.mjs <modele.glb> <dossier des images>\n');
	process.exit(2);
}

const THREE = await import(here('lib/three.mjs'));
const { sceneBuildModel } = await import(here('lib/model.mjs'));
const { sceneUploadRules, sceneUploadBake } = await import(here('upload.mjs'));
const { rendre, png, empreinte } = await import(here('rendu.mjs'));
const rules = sceneUploadRules(JSON.parse(readFileSync(here('decor.json'), 'utf8')), 'building');
const choices = JSON.parse(readFileSync(here('lib/choices.json'), 'utf8'));

// La palette du site, lue dans le cadre même, qui n'exporte que sa fabrique : une clé par ligne `nom: '#rrggbb'` du bloc
// `const P = { … };`
const source = readFileSync(here('lib/building.mjs'), 'utf8');
const block = source.slice(source.indexOf('const P = {'), source.indexOf('};', source.indexOf('const P = {')));
const palette = new Map([...block.matchAll(/(\w+): '(#[0-9a-f]{6})'/g)].map(([, key, hex]) => [hex, key]));

// La pierre d'un bâtiment composé dans l'éditeur : chaque teinte de mur proposée, assombrie de 20 %
for(const tints of Object.values(choices).map(entry => entry.colors.wallTint)) {
	for(const [key, hex] of Object.entries(tints)) {
		const stone = '#' + [1, 3, 5].map(i => Math.round(parseInt(hex.slice(i, i + 2), 16) * .8).toString(16).padStart(2, '0')).join('');
		if(palette.has(stone) === false) {
			palette.set(stone, 'pierre ' + key);
		}
	}
}

let baked;

try {
	baked = sceneUploadBake(rules, readFileSync(input));
} catch(error) {
	if(error.refusal === undefined) {
		throw error;
	}
	process.stdout.write(JSON.stringify({ refusal: error.refusal, values: error.values }) + '\n');
	process.exit(1);
}

const bytes = Buffer.from(baked.buffer, 'base64');
const model = sceneBuildModel(THREE, { index: baked.index, buffer: bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) }, 'building');

// Le corps du bâtiment ne porte pas de nom dans le modèle converti, comme dans ceux du site
for(const part of model.parts) {
	const color = '#' + String(part.color).replace('#', '');
	process.stdout.write(`${part.name ?? rules.body} ${color} ${part.geometry.attributes.position.count / 3} triangles (${palette.get(color) ?? 'hors palette'})\n`);
}

for(const [name, point] of Object.entries(model.anchors)) {
	process.stdout.write(`ancre ${name} ${point.join(' ')}\n`);
}

const identity = new THREE.Matrix4();
const objets = model.parts.map(part => ({ geometry: part.geometry, matrixWorld: identity, color: '#' + String(part.color).replace('#', '') }));

mkdirSync(output, { recursive: true });

for(const vue of ['face', 'trois-quarts', 'dos', 'dessus']) {
	const image = rendre(THREE, objets, { profil: 'batiment', vue, largeur: 600, hauteur: 450 });
	writeFileSync(join(output, vue + '.png'), png(image, deflateSync));
	process.stdout.write(`${vue}.png ${empreinte(image.pixels)}\n`);
}
