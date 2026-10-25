<?php
namespace scene;

/**
 * Les normales d'un modèle téléversé (`sceneUploadSmooth()`, `upload/upload.mjs`), calculées de ses seules positions
 * quantifiées : une surface courbe s'arrondit, un angle droit s'arrondit, un pli qui écarte les normales de plus de 120° reste vif, une pièce mince repliée garde ses faces,
 * deux os qui se touchent ne se soudent pas, et un second passage ne change aucun octet. Puis le modèle d'exemple du
 * skill, la composition par défaut de l'éditeur, s'ombre comme l'éditeur l'ombre.
 *
 * La sonde n'imprime que des nombres. Aucune écriture en base ni sur le disque.
 *
 * Lancement : dev/test package=scene file=UploadSmoothAi
 */
class UploadSmoothAiTest extends \Test {

	const PROBE = <<<'JS'
		import { register } from 'node:module';
		import { readFileSync } from 'node:fs';

		const SCENE = 'file://' + process.argv[2] + '/';

		// Les crochets de la cuisson font lire three et avatar.js, écrits pour le navigateur
		register(SCENE + 'bake/resolve.mjs');

		const THREE = await import('three');
		const { sceneCreateAvatar } = await import(SCENE + 'static/js/avatar.js');
		const { sceneUploadSmooth, sceneUploadBake } = await import(SCENE + 'upload/upload.mjs');

		// L'angle que les règles de l'avatar donnent au lissage, sous lequel ces cas sont écrits
		const angle = JSON.parse(readFileSync(new URL('upload/avatar.json', SCENE), 'utf8')).smoothAngle;

		const dot = (a, b) => a[0] * b[0] + a[1] * b[1] + a[2] * b[2];
		const unit = a => { const length = Math.hypot(...a); return a.map(value => value / length); };
		const cross = (a, b, c) => {
			const u = [0, 1, 2].map(axis => b[axis] - a[axis]), v = [0, 1, 2].map(axis => c[axis] - a[axis]);
			return [u[1] * v[2] - u[2] * v[1], u[2] * v[0] - u[0] * v[2], u[0] * v[1] - u[1] * v[0]];
		};

		// Des triangles `[os, [a, b, c]]` quantifiés comme la conversion les quantifie, une pièce par suite d'un même os,
		// puis lissés deux fois : rend les normales de chaque coin, et si le second passage a gardé tous les octets
		function smooth(triangles) {

			const points = triangles.flatMap(([, corners]) => corners);
			const box = [Math.min, Math.max].map(bound => [0, 1, 2].map(axis => bound(...points.map(point => point[axis]))));
			const vertices = points.length, buffer = new ArrayBuffer(vertices * 9), positions = new Int16Array(buffer, 0, vertices * 3);

			points.forEach((point, i) => point.forEach((value, axis) => {
				positions[i * 3 + axis] = Math.round((value - box[0][axis]) / ((box[1][axis] - box[0][axis]) || 1) * 65534) - 32767;
			}));

			const parts = [];

			triangles.forEach(([bone], t) => {
				if(parts.at(-1)?.name === bone) {
					parts.at(-1).count += 3;
				} else {
					parts.push({ name: bone, offset: t * 3, count: 3 });
				}
			});

			sceneUploadSmooth(buffer, { box, parts }, vertices, angle);
			const first = Buffer.from(buffer.slice(0));
			sceneUploadSmooth(buffer, { box, parts }, vertices, angle);

			const normals = new Int8Array(buffer, vertices * 6);

			return {
				normals: points.map((_, i) => unit([0, 1, 2].map(axis => normals[i * 3 + axis] / 127))),
				idempotent: first.equals(Buffer.from(buffer)),
			};

		}

		const quad = (bone, a, b, c, d) => [[bone, [a, b, c]], [bone, [a, c, d]]];

		// Le plus petit accord entre la normale d'un coin et celle de sa face
		const faceAgreement = triangles => {
			const { normals } = smooth(triangles);
			return Math.min(...normals.map((normal, i) => dot(normal, unit(cross(...triangles[Math.floor(i / 3)][1])))));
		};

		// Seize côtés sans fonds, de 10 cm de rayon
		const cylinder = [];

		for(let i = 0; i < 16; i++) {
			const at = (k, y) => [Math.cos(k / 16 * 2 * Math.PI) * .1, y, -Math.sin(k / 16 * 2 * Math.PI) * .1];
			cylinder.push(...quad('torse', at(i, 0), at(i + 1, 0), at(i + 1, .2), at(i, .2)));
		}

		const cylinderSmoothed = smooth(cylinder);
		const radial = Math.min(...cylinderSmoothed.normals.map((normal, i) => {
			const point = cylinder[Math.floor(i / 3)][1][i % 3];
			return dot(normal, unit([point[0], 0, point[2]]));
		}));

		// Deux pans qui partagent leur faîte, pliés de `angle` degrés. Les deux diagonales partent du même bout du faîte, pour
		// que chaque coin du faîte reçoive autant d'aire de chaque pan : la normale lissée est alors à mi-angle des deux faces
		const roof = (angle, boneA, boneB) => {
			const far = [Math.cos(angle * Math.PI / 180), Math.sin(angle * Math.PI / 180)];
			return [
				...quad(boneA, [0, 0, 0], [0, 0, 1], [-1, 0, 1], [-1, 0, 0]),
				...quad(boneB, [0, 0, 0], [far[0], far[1], 0], [far[0], far[1], 1], [0, 0, 1]),
			];
		};

		// Deux triangles dos à dos : leurs faces s'annulent au même coin
		const fin = [['torse', [[0, 0, 0], [1, 0, 0], [0, 1, 0]]], ['torse', [[0, 0, 0], [0, 1, 0], [1, 0, 0]]]];

		// Le modèle d'exemple, cuit, contre la composition par défaut que l'éditeur monte (`defaultFiche()`, upload/samples.mjs).
		// Un coin se rapproche des coins de l'éditeur à la même position quantifiée sur le même os, puis de celui dont la face
		// regarde le plus du même côté : à une arête, plusieurs faces de l'éditeur partagent la position
		const rules = JSON.parse(readFileSync(new URL('upload/avatar.json', SCENE), 'utf8'));
		const catalog = JSON.parse(readFileSync(new URL('static/avatar/catalog.json', SCENE), 'utf8'));
		const baked = sceneUploadBake(rules, readFileSync(new URL('upload/avatar/exemples/conforme.glb', SCENE)));
		const model = baked.index.models.avatar, box = model.box, vertices = baked.index.vertices;
		const buffer = Buffer.from(baked.buffer, 'base64');
		const positions = new Int16Array(buffer.buffer, buffer.byteOffset, vertices * 3);
		const normals = new Int8Array(buffer.buffer, buffer.byteOffset + vertices * 6, vertices * 3);

		const quantize = point => point.map((value, axis) => Math.max(-32767, Math.min(32767, Math.round((value - box[0][axis]) / (box[1][axis] - box[0][axis]) * 65534) - 32767))).join(',');
		const point = i => [0, 1, 2].map(axis => box[0][axis] + (positions[i * 3 + axis] + 32767) / 65534 * (box[1][axis] - box[0][axis]));

		const elements = catalog.families
			.filter(family => catalog.defaults.entries[family.key] !== null)
			.map(family => ({ catalogue: catalog.defaults.entries[family.key], reglages: family.palette !== null ? { couleur: catalog.defaults.tints[family.key] } : {} }));
		const editor = sceneCreateAvatar(THREE).build({ corps: { peau: catalog.defaults.skin }, elements });

		const boneOf = name => {
			for(let bone = name; bone !== undefined; bone = editor.parents[bone]) {
				if(rules.bones.includes(bone)) {
					return bone;
				}
			}
		};

		const candidates = new Map();

		for(const part of editor.parts) {

			const geometry = part.geometry.index !== null ? part.geometry.toNonIndexed() : part.geometry;
			const editorPositions = geometry.attributes.position.array, editorNormals = geometry.attributes.normal.array;

			for(let corner = 0; corner < editorPositions.length / 3; corner++) {
				const triangle = corner - corner % 3;
				const face = unit(cross(...[0, 1, 2].map(k => Array.from(editorPositions.slice((triangle + k) * 3, (triangle + k) * 3 + 3)))));
				const key = boneOf(part.name) + '|' + quantize(Array.from(editorPositions.slice(corner * 3, corner * 3 + 3)));
				(candidates.get(key) ?? candidates.set(key, []).get(key)).push({ face, normal: unit(Array.from(editorNormals.slice(corner * 3, corner * 3 + 3))) });
			}

		}

		let close = 0, unmatched = 0;

		for(const { name, offset, count } of model.parts) {
			for(let t = offset; t < offset + count; t += 3) {

				const face = unit(cross(point(t), point(t + 1), point(t + 2)));

				for(let corner = t; corner < t + 3; corner++) {

					const list = candidates.get(name + '|' + positions.slice(corner * 3, corner * 3 + 3).join(','));

					if(list === undefined) {
						unmatched++;
						continue;
					}

					const nearest = list.reduce((best, candidate) => dot(candidate.face, face) > dot(best.face, face) ? candidate : best);
					const normal = unit([0, 1, 2].map(axis => normals[corner * 3 + axis] / 127));

					if(dot(normal, nearest.normal) >= Math.cos(15 * Math.PI / 180)) {
						close++;
					}

				}

			}
		}

		const resmoothed = buffer.buffer.slice(buffer.byteOffset, buffer.byteOffset + buffer.byteLength);
		sceneUploadSmooth(resmoothed, { box, parts: model.parts }, vertices, rules.smoothAngle);

		process.stdout.write(JSON.stringify({
			cylinder: radial,
			cylinderIdempotent: cylinderSmoothed.idempotent,
			roofSoft: faceAgreement(roof(30, 'torse', 'torse')),
			roofSharp: faceAgreement(roof(150, 'torse', 'torse')),
			roofBones: faceAgreement(roof(30, 'torse', 'tete')),
			fin: faceAgreement(fin),
			sample: { vertices, close, unmatched, rebaked: Buffer.from(resmoothed).equals(buffer) },
		}));
		JS;

	private array $result = [];

	// Une seule sonde pour toute la classe : chaque méthode lit son morceau du résultat
	public function init(): void {

		$process = proc_open(['node', '--input-type=module', '-', LIME_DIRECTORY.'/base/scene'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), 'node ne se lance pas ici : ce test éprouve upload.mjs sous node, l\'installer sur la machine qui lance les tests') === FALSE) {
			return;
		}

		fwrite($pipes[0], self::PROBE);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$result = json_decode($output, TRUE);

		$this->assertTrue(is_array($result), 'la sonde rend son résultat : '.$output.$errors);

		$this->result = is_array($result) ? $result : [];

	}

	public function testCurvedSurface(): void {

		$this->assertTrue($this->result['cylinder'] >= .99, 'un cylindre de seize côtés ne prend pas des normales radiales : '.$this->result['cylinder']);
		$this->assertTrue($this->result['cylinderIdempotent'], 'un second lissage change les octets du cylindre');

	}

	public function testCrease(): void {

		// Plié de 30°, chaque coin du faîte est à 15° de sa face ; plié de 150°, il en serait à 75°, au-delà des 60° de la règle
		$this->assertTrue(abs($this->result['roofSoft'] - cos(deg2rad(15))) < .005, 'un pli de 30° ne s\'arrondit pas à mi-angle : '.$this->result['roofSoft']);
		$this->assertTrue($this->result['roofSharp'] >= .999, 'un pli de 150° ne reste pas vif : '.$this->result['roofSharp']);
		$this->assertTrue($this->result['fin'] >= .999, 'deux faces dos à dos ne gardent pas leur normale de face : '.$this->result['fin']);

	}

	public function testBonesNotWelded(): void {

		$this->assertTrue($this->result['roofBones'] >= .999, 'deux os qui se touchent mettent leurs normales en commun : '.$this->result['roofBones']);

	}

	public function testSampleAsEditor(): void {

		$sample = $this->result['sample'];

		$this->assertEquals(0, $sample['unmatched'], 'des coins du modèle d\'exemple n\'ont aucun coin de l\'éditeur à leur place');
		$this->assertTrue($sample['close'] >= $sample['vertices'] * .97, 'le modèle d\'exemple s\'ombre autrement que l\'éditeur : '.$sample['close'].' coins sur '.$sample['vertices'].' à moins de 15°');
		$this->assertTrue($sample['rebaked'], 'un nouveau lissage du modèle d\'exemple cuit change ses octets');

	}

}
?>
