// Rend un modèle .glb comme l'éditeur d'avatar du site l'affichera, sans navigateur ni Blender :
//   node rendre.mjs <modele.glb> <dossier des images> [--pose=<nom>|--pose=repos]
// Le modèle passe d'abord par le validateur du site, qui le convertit : les images montrent donc ce qui partira, avec les
// normales que le site calcule, et non celles du fichier. Un modèle refusé n'est pas rendu, le refus est imprimé.
// Imprime d'abord une ligne par pièce du modèle converti (son nom, sa couleur, ses triangles), puis une par image.
// Sans pose, écrit face.png, trois-quarts.png et dos.png au repos, puis une image de trois-quarts par pose des règles,
// trois-quarts-<pose>.png. Avec --pose, les trois vues dans cette pose, suffixées de son nom, et --pose=repos les
// trois vues au repos seules.
// Fond transparent, éclairage et caméra de l'éditeur, seule la face avant de chaque triangle dessinée.
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { deflateSync } from 'node:zlib';
import { join } from 'node:path';

const here = name => new URL(name, import.meta.url);

const args = process.argv.slice(2);
const [input, output] = args.filter(arg => arg.startsWith('--') === false);
const poseAsked = args.find(arg => arg.startsWith('--pose='))?.slice(7);

if(output === undefined) {
	process.stderr.write('node rendre.mjs <modele.glb> <dossier des images> [--pose=<nom>|--pose=repos]\n');
	process.exit(2);
}

const THREE = await import(here('lib/three.mjs'));
const { sceneBuildModel } = await import(here('lib/model.mjs'));
const { sceneUploadBake } = await import(here('upload.mjs'));
const { rendre, poser, png, empreinte } = await import(here('rendu.mjs'));
const rules = JSON.parse(readFileSync(here('avatar.json'), 'utf8'));

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
const model = sceneBuildModel(THREE, { index: baked.index, buffer: bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) }, 'avatar');
const poses = baked.index.models.avatar.poses;

if(poseAsked !== undefined && poseAsked !== 'repos' && poses[poseAsked] === undefined) {
	process.stderr.write(`pose inconnue : ${poseAsked}, les poses : repos, ${Object.keys(poses).join(', ')}\n`);
	process.exit(2);
}

for(const part of model.parts) {
	process.stdout.write(`${part.name} #${String(part.color).replace('#', '')} ${part.geometry.index !== null ? part.geometry.index.count / 3 : part.geometry.attributes.position.count / 3} triangles\n`);
}

// Chaque pièce sur le pivot de son os, comme l'éditeur articule le modèle
const figure = { parts: model.parts.map(part => ({ ...part, color: '#' + String(part.color).replace('#', ''), pivot: model.pivots[part.name] })), parents: model.parents };

// Une pose assise descend la figure, comme l'éditeur, de sa descente fois celle des ancres du modèle
const posed = name => {
	const pose = name === null ? {} : poses[name];
	const { racine, objets } = poser(THREE, figure, pose);
	racine.position.y = -(pose.descente ?? 0) * model.anchors.descenteAssis;
	racine.updateMatrixWorld(true);
	return objets;
};

// L'éditeur vise toujours la mi-hauteur de la figure debout : la boite du repos sert de cadre à toutes les images
const repos = posed(null);
const box = new THREE.Box3();
for(const { geometry, matrixWorld } of repos) {
	box.union(new THREE.Box3().setFromBufferAttribute(geometry.attributes.position).applyMatrix4(matrixWorld));
}
const cadre = [box.min.x, box.min.y, box.min.z, box.max.x, box.max.y, box.max.z];

let images;

if(poseAsked === undefined) {
	images = [...['face', 'trois-quarts', 'dos'].map(vue => [vue, vue, repos]), ...Object.keys(poses).map(name => ['trois-quarts-' + name, 'trois-quarts', posed(name)])];
} else if(poseAsked === 'repos') {
	images = ['face', 'trois-quarts', 'dos'].map(vue => [vue, vue, repos]);
} else {
	images = ['face', 'trois-quarts', 'dos'].map(vue => [vue + '-' + poseAsked, vue, posed(poseAsked)]);
}

mkdirSync(output, { recursive: true });

for(const [name, vue, objets] of images) {
	const image = rendre(THREE, objets, { profil: 'editeur', vue, cadre });
	writeFileSync(join(output, name + '.png'), png(image, deflateSync));
	process.stdout.write(`${name}.png ${empreinte(image.pixels)}\n`);
}
