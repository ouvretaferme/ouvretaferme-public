<?php
namespace map;

/**
 * Le nom de la structure planté au bord du plan (`static/js/scene.js`, `MapSceneSign`) : ses lettres crème sont cernées d'un
 * contour sombre, pour se lire sur une terre ou une herbe claire. Chaque barre a le sien, plus grand d'un trait tout autour,
 * qui ne s'avance pas devant la face de la plaque et se dessine avant elle : seul reste le tour de la lettre entière. Le
 * nom du bourg, tondu dans l'herbe par `accounting/town`, n'en dépend pas.
 * La teinte et la lisibilité se jugent dans Chrome.
 *
 * Éprouvé sous node, sur les seules géométries de `MapSceneSign.letters()`. Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=map file=SignAi
 */
class SignAiTest extends \Test {

	public function testEveryBarHasItsOutline(): void {

		$result = $this->run('
			const characters = MapSceneSign.characters("Jardins de Tallende");
			const { plates, outlines } = MapSceneSign.letters(THREE, characters, MapSceneSign.SIZE);

			const box = geometry => {
				geometry.computeBoundingBox();
				return geometry.boundingBox;
			};

			const width = MapSceneSign.OUTLINE_WIDTH;
			const wider = [];
			const ahead = [];

			plates.forEach((plate, i) => {

				const [inner, outer] = [box(plate), box(outlines[i])];

				if(outer.min.x > inner.min.x - width / 2 || outer.max.x < inner.max.x + width / 2 || outer.min.y > inner.min.y - width / 2 || outer.max.y < inner.max.y + width / 2) {
					wider.push(i);
				}

				// Le lacet et le dévers d\'une lettre portent les coins de la boite plus large un peu plus loin en avant que la plaque
				if(outer.max.z > inner.max.z + width / 4) {
					ahead.push(i);
				}

			});

			const luminance = color => new THREE.Color(color).toArray().reduce((sum, channel, i) => sum + channel * [.2126, .7152, .0722][i], 0);

			emit({ plates: plates.length, outlines: outlines.length, wider, ahead, outline: luminance(MapSceneSign.OUTLINE), plate: luminance(MapSceneSign.PLATE) });
		');

		if($result === []) {
			return;
		}

		$this->assertTrue($result['plates'] > 0, 'le nom a des barres');
		$this->assertEquals($result['plates'], $result['outlines'], 'chaque barre a son contour');
		$this->assertEquals([], $result['wider'], 'un contour déborde sa barre tout autour, du côté du trait comme à ses bouts');
		$this->assertEquals([], $result['ahead'], 'un contour ne s\'avance pas devant la face de sa plaque, où il épaissirait le contour vu de biais');
		$this->assertTrue($result['outline'] < .1 and $result['plate'] > .8, 'le contour est sombre et la plaque claire');

	}

	/**
	 * Le contour ne recouvre jamais une plaque, sous aucun angle, et un objet dessiné après lui derrière l'enseigne ne l'efface
	 * pas. Sans WebGL sous node, chaque rayon d'une vue rejoue ce que three en dessine : les opaques avant les transparents, chaque
	 * liste par ordre puis par distance, la face que la matière montre, le test et l'écriture de la profondeur, la couleur écrite
	 * ou non et le recul en profondeur.
	 */
	public function testPlatesCoverTheJoints(): void {

		$result = $this->run('
			const sign = new MapSceneSign({
				THREE,
				addons: { mergeGeometries },
				plan: { name: "DS", places: [], beds: [] },
				geometry: { beds: [], plots: new Map(), corners: () => [], height: () => 0, relief: null },
				camera: { position: new THREE.Vector3(60, 60, 60) },
				controls: { target: new THREE.Vector3() },
				opening: [{ x: 0, n: 0 }],
				boards: { boards: [] },
				decor: { elements: [] },
				parent: new THREE.Group(),
				gradientMap: null
			});

			const [plate, post, outline] = sign.group.children;
			sign.group.updateMatrixWorld(true);

			const box = new THREE.Box3().setFromObject(plate);
			const center = box.getCenter(new THREE.Vector3());
			const facing = new THREE.Vector3(0, 0, 1).applyQuaternion(sign.group.quaternion);

			// Un mur de la taille de l\'enseigne, à 2 m derrière elle : opaque à l\'ordre du sol ou après lui, ou transparent
			const wall = (renderOrder, transparent) => {
				const mesh = new THREE.Mesh(new THREE.PlaneGeometry(40, 20), new THREE.MeshBasicMaterial({ side: THREE.DoubleSide, transparent, opacity: transparent ? .5 : 1 }));
				mesh.position.copy(center).addScaledVector(facing, -2);
				mesh.quaternion.copy(sign.group.quaternion);
				mesh.renderOrder = renderOrder;
				mesh.updateMatrixWorld(true);
				return mesh;
			};

			const raycaster = new THREE.Raycaster();

			// Ce que montre un rayon : la pièce dont la couleur reste au bout de toutes les passes
			const draw = (meshes, origin, direction) => {

				const depth = mesh => origin.distanceTo(mesh.getWorldPosition(new THREE.Vector3()));
				const opaque = meshes.filter(m => m.material.transparent === false).sort((a, b) => a.renderOrder - b.renderOrder || depth(a) - depth(b));
				const blended = meshes.filter(m => m.material.transparent).sort((a, b) => a.renderOrder - b.renderOrder || depth(b) - depth(a));

				let z = Infinity;
				let shown = null;

				for(const mesh of [...opaque, ...blended]) {

					raycaster.set(origin, direction);
					const material = mesh.material;
					const bias = material.polygonOffset ? material.polygonOffsetFactor * .01 + material.polygonOffsetUnits * .001 : 0;
					const fragments = raycaster.intersectObject(mesh, false).map(hit => hit.distance + bias);
					const passed = fragments.filter(d => material.depthTest === false || d <= z + 1e-6);

					if(passed.length > 0 && material.colorWrite) {
						shown = mesh;
					}

					// Sans test de profondeur, WebGL ne pose pas non plus la profondeur
					if(material.depthTest && material.depthWrite && passed.length > 0) {
						z = Math.min(z, ...passed);
					}

				}

				return shown;

			};

			const size = box.getSize(new THREE.Vector3());
			const views = [[0, .9], [-.9, .9], [.6, .9], [-.9, .5]];
			const counts = { plate: 0, covered: 0, rim: 0, lost: 0 };

			for(const [yaw, phi] of views) {

				const direction = new THREE.Vector3().setFromSphericalCoords(1, phi, yaw).applyQuaternion(sign.group.quaternion).negate();
				const right = new THREE.Vector3().crossVectors(direction, new THREE.Vector3(0, 1, 0)).normalize();
				const up = new THREE.Vector3().crossVectors(right, direction).normalize();
				const reach = Math.max(size.x, size.y, size.z);

				for(let i = 0; i <= 80; i++) {
					for(let j = 0; j <= 40; j++) {

						const origin = center.clone()
							.addScaledVector(right, (i / 80 - .5) * reach * 1.1)
							.addScaledVector(up, (j / 40 - .5) * reach * .6)
							.addScaledVector(direction, -50);

						const shown = draw([plate, post, ...sign.group.children.slice(3), outline], origin, direction);

						raycaster.set(origin, direction);
						const plateHit = raycaster.intersectObject(plate, false)[0];
						const postHit = raycaster.intersectObject(post, false)[0];

						if(plateHit !== undefined && (postHit === undefined || postHit.distance > plateHit.distance)) {
							counts.plate++;
							if(shown !== plate) {
								counts.covered++;
							}
						}

						if(shown === outline) {
							counts.rim++;
							for(const behind of [wall(0, false), wall(1, false), wall(5, false), wall(0, true), wall(20, true)]) {
								if(draw([plate, post, ...sign.group.children.slice(3), outline, behind], origin, direction) !== outline) {
									counts.lost++;
								}
							}
						}

					}
				}

			}

			// Ce que le rendu ne départage pas, parce que le contour se dessine avant tout le reste, et que la scène pourra un jour
			// faire passer quelque chose avant lui : chaque passe teste la profondeur, reste opaque et ne recule pas
			const passes = [outline, ...sign.group.children.slice(3)];

			emit({
				...counts,
				materials: {
					outline: outline.material === sign.outline,
					side: passes.every(mesh => mesh.material.side === THREE.BackSide),
					depthTest: passes.every(mesh => mesh.material.depthTest),
					opaque: passes.every(mesh => mesh.material.transparent === false),
					offset: passes.some(mesh => mesh.material.polygonOffset),
					shadow: passes.some(mesh => mesh.castShadow)
				}
			});
		');

		if($result === []) {
			return;
		}

		$this->assertTrue($result['plate'] > 1000 and $result['rim'] > 200, 'les vues voient les plaques et leur contour ('.$result['plate'].' et '.$result['rim'].' rayons)');
		$this->assertEquals(0, $result['covered'], 'aucun contour ne passe sur une plaque qui se voit, de face, tournée ou de haut');
		$this->assertEquals(0, $result['lost'], 'un mur derrière l\'enseigne, opaque comme le sol ou transparent, n\'efface pas le contour');
		$this->assertEquals(['outline' => TRUE, 'side' => TRUE, 'depthTest' => TRUE, 'opaque' => TRUE, 'offset' => FALSE, 'shadow' => FALSE], $result['materials'], 'les passes du contour, vues par l\'intérieur, testent la profondeur, restent opaques, sans recul ni ombre');

	}

	/**
	 * Le nom du bourg ne passe pas par l'enseigne du plan : aucun fichier du bourg ne la nomme, son contour ne peut donc pas l'atteindre.
	 */
	public function testTownNameIsNotTheSign(): void {

		$files = glob(LIME_DIRECTORY.'/accounting/town/static/js/*.js');

		$this->assertTrue(count($files) > 0, 'les fichiers du bourg se lisent');
		$this->assertEquals([], array_values(array_filter($files, fn($file) => str_contains(file_get_contents($file), 'MapSceneSign'))), 'aucun fichier du bourg ne nomme MapSceneSign');

	}

	/**
	 * Joue un scénario sous node avec les fichiers de la scène chargés comme le navigateur les charge, et three, et rend ce qu'il
	 * émet.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = '.json_encode(LIME_DIRECTORY).';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire three en module ES, qu\'aucun package.json ne déclare tel
			require("node:module").register("file://" + directory + "/base/scene/bake/resolve.mjs");

			(async () => {
				const { readFileSync } = await import("node:fs");
				const vm = await import("node:vm");
				const THREE = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.module.min.js");
				const { mergeGeometries } = await import("file://" + directory + "/base/scene/static/lib/three-0.170.0.BufferGeometryUtils.js");

				const source = ["base/scene/static/js/plan.js", "base/scene/static/js/crew.js", "production/series/static/js/seriesSheet.js", "production/map/static/js/scene.js", "production/map/static/js/sceneLayer.js", "production/map/static/js/sceneDecor.js"].map(file => readFileSync(directory + "/" + file, "utf8")).join("\n");
				const context = vm.createContext({ window: {}, document: { addEventListener: () => {} }, console, clearTimeout });
				const { MapSceneSign } = vm.runInContext(source + "\n;({ MapSceneSign })", context);

				'.$scenario.'
			})().catch(error => process.stderr.write(String(error.stack)));
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test éprouve la scène sous node, l'installer sur la machine qui lance les tests") === FALSE) {
			return [];
		}

		fwrite($pipes[0], $script);
		fclose($pipes[0]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$result = json_decode($output, TRUE);

		$this->assertTrue(is_array($result), 'node rend son résultat : '.$output.$errors);

		return $result ?? [];

	}

}
