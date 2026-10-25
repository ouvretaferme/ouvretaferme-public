<?php
namespace scene;

/**
 * Le bâtiment téléversé, profil `decor` et famille `building` (`upload/decor.json`, `upload/upload.mjs`) :
 * - le serveur rend chaque refus propre au décor avec ses valeurs, sur des dérivés du modèle conforme du skill ;
 * - un modèle accepté se relit par `sceneBuildModel()` comme un bâtiment composé : le corps sans nom, les pièces nommées
 *   `vitre`, `vantailGauche` et `vantailDroit`, les ancres que le cadre pose, et une normale de face à chaque coin ;
 * - l'emprise, le plafond de triangles et celui des couleurs laissent passer tout bâtiment que l'éditeur du décor compose,
 *   à ses cotes les plus grandes : un bâtiment de l'éditeur exporté tel quel est toujours accepté.
 *
 * Une sonde node monte les bâtiments avec le cadre même du site et écrit les dérivés dans un dossier temporaire, retiré à
 * la fin. Aucune écriture en base ni dans le dépôt.
 *
 * Lancement : dev/test package=scene file=UploadBuildingAi
 */
class UploadBuildingAiTest extends \Test {

	const PROBE = <<<'JS'
		import { register } from 'node:module';
		import { readFileSync, writeFileSync } from 'node:fs';

		const SCENE = 'file://' + process.argv[2] + '/';
		const OUTPUT = process.argv[3] + '/';

		// Les crochets de la cuisson font lire three et building.js, écrits pour le navigateur
		register(SCENE + 'bake/resolve.mjs');

		const THREE = await import('three');
		const addons = await import('three/addons/utils/BufferGeometryUtils.js');
		const { sceneCreateGeometry } = await import(SCENE + 'static/js/geometry.js');
		const { sceneCreateBuilding } = await import(SCENE + 'static/js/building.js');
		const { sceneBuildModel } = await import(SCENE + 'static/js/model.js');
		const { sceneUploadRules, sceneUploadBake } = await import(SCENE + 'upload/upload.mjs');
		const glb = await import(SCENE + 'upload/glb.mjs');
		const { sceneUploadComposeBuilding } = await import(SCENE + 'upload/decor/composer.mjs');

		const choices = JSON.parse(readFileSync(new URL('static/building/choices.json', SCENE), 'utf8'));
		const rules = sceneUploadRules(JSON.parse(readFileSync(new URL('upload/decor.json', SCENE), 'utf8')), 'building');
		const building = sceneCreateBuilding(THREE, sceneCreateGeometry(THREE, addons), choices);

		const unpack = bytes => {
			const length = bytes.readUInt32LE(12);
			return { json: JSON.parse(bytes.subarray(20, 20 + length).toString()), bin: bytes.subarray(28 + length) };
		};

		const derive = (model, alter) => {
			const json = structuredClone(model.json);
			alter(json);
			return glb.sceneUploadPack({ json, bin: model.bin });
		};

		// Chaque bâtiment de l'éditeur à ses cotes les plus grandes. Une teinte, ou des menuiseries, ne font que colorer une
		// pièce déjà là : chaque valeur garde le même compte de couleurs, sauf à tomber sur une autre couleur du bâtiment, qui
		// le baisse. La teinte de départ de chaque mur et de chaque couverture suffit donc à borner les couleurs
		const worst = {};

		for(const [type, entry] of Object.entries(choices)) {

			const keys = Object.keys(entry.settings).filter(key => Object.hasOwn(entry.tints, key) === false && key !== 'joinery');
			let combinations = [{}];

			for(const key of keys) {
				combinations = combinations.flatMap(settings => entry.settings[key].map(value => ({ ...settings, [key]: value })));
			}

			const measure = { x: 0, z: 0, y: 0, triangles: 0, colors: 0, failed: 0 };

			for(const settings of combinations) {

				for(const [key, [from, defaults]] of Object.entries(entry.tints)) {
					settings[key] = defaults[settings[from]];
				}

				if(entry.settings.joinery !== undefined) {
					settings.joinery = entry.initial.settings.joinery;
				}

				if(entry.excluded.some(rule => Object.entries(rule).every(([key, value]) => settings[key] === value))) {
					continue;
				}

				const model = building.build({ type, length: entry.length?.[1] ?? null, width: entry.width?.[1] ?? null, height: entry.height?.[1] ?? null, settings });

				if(model === null) {
					measure.failed++;
					continue;
				}

				let triangles = 0;

				for(const part of model.parts) {
					part.geometry.computeBoundingBox();
					const { min, max } = part.geometry.boundingBox;
					measure.x = Math.max(measure.x, -min.x, max.x);
					measure.z = Math.max(measure.z, -min.z, max.z);
					measure.y = Math.max(measure.y, max.y);
					triangles += (part.geometry.index?.count ?? part.geometry.attributes.position.count) / 3;
				}

				measure.triangles = Math.max(measure.triangles, triangles);
				measure.colors = Math.max(measure.colors, new Set(model.parts.map(part => part.color)).size);

			}

			worst[type] = measure;

		}

		// Un bâtiment qui porte toutes les pièces nommées : une porte coulissante et des fenêtres
		const fiche = { type: 'building', length: 14, width: 9, height: 4.2, settings: { roof: 'deux-pans', cover: 'tole', coverTint: 'toleSombre', wall: 'bardage-tole', wallTint: 'pierre', door: 'porte-coulissante', windows: 2 } };
		const composed = sceneUploadComposeBuilding(THREE, glb, building, choices, rules, fiche).bytes;
		const baked = sceneUploadBake(rules, composed);
		const bytes = Buffer.from(baked.buffer, 'base64');
		const read = sceneBuildModel(THREE, { index: baked.index, buffer: bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) }, 'building');

		// Chaque coin porte la normale de sa face, à l'arrondi de l'Int8 près
		let corners = 0, flat = 0;

		for(const part of read.parts) {
			const position = part.geometry.attributes.position, normal = part.geometry.attributes.normal;
			for(let corner = 0; corner < position.count; corner++) {
				const t = corner - corner % 3;
				const [a, b, c] = [t, t + 1, t + 2].map(i => new THREE.Vector3().fromBufferAttribute(position, i));
				const face = new THREE.Vector3().crossVectors(b.sub(a), c.sub(a)).normalize();
				corners++;
				if(face.dot(new THREE.Vector3().fromBufferAttribute(normal, corner).normalize()) >= .999) {
					flat++;
				}
			}
		}

		writeFileSync(OUTPUT + 'composed.glb', composed);

		// Les dérivés, chacun refusé pour une seule raison
		const sample = unpack(readFileSync(new URL('upload/decor/exemples/conforme.glb', SCENE)));
		const meshes = sample.json.nodes.filter(node => node.mesh !== undefined);
		// Une porte coulissante, une fenêtre et une descente en couleurs du catalogue : huit couleurs, le plafond des règles
		const frameFiche = {
			corps: { toit: 'deux-pans', largeur: 8, profondeur: 16, egout: 4, faitage: 7, socle: 0 },
			mur: { catalogue: 'pierre' },
			couverture: { catalogue: 'tuile', reglages: { couleur: 'tuile' } },
			ouvertures: [{ catalogue: 'porte-coulissante', facade: 'avant', u: 0 }, { catalogue: 'fenetre', facade: 'droite', u: -3 }],
			elements: [{ catalogue: 'descente', reglages: { couleur: 'gris' } }],
		};

		const frame = sceneUploadComposeBuilding(THREE, glb, building, choices, rules, frameFiche);

		// Le même corps monté à 18 m de faitage, plus haut que l'ancien plafond de 14 m et sous celui des règles
		const tall = sceneUploadComposeBuilding(THREE, glb, building, choices, rules, {
			...frameFiche,
			corps: { ...frameFiche.corps, egout: 12, faitage: 18 },
		}).bytes;

		writeFileSync(OUTPUT + 'eightColors.glb', frame.bytes);
		writeFileSync(OUTPUT + 'tall.glb', tall);

		// Un corps fait de petits triangles posés au sol en grille, autant que demandé : le plafond des règles, puis un de plus
		const soup = count => {
			const positions = new Float32Array(count * 9);
			for(let i = 0; i < count; i++) {
				const x = (i % 50) * .2 - 5, z = Math.floor(i / 50) * .2 - 5;
				positions.set([x, 0, z, x + .1, 0, z, x, .1, z], i * 9);
			}
			const geometry = new THREE.BufferGeometry();
			geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
			return glb.sceneUploadPack(glb.sceneUploadWriteGlb([{ name: 'batiment', color: '#8d9499', geometry }]));
		};

		writeFileSync(OUTPUT + 'trianglesMax.glb', soup(rules.maxTriangles));
		writeFileSync(OUTPUT + 'trianglesOver.glb', soup(rules.maxTriangles + 1));

		const derived = {
			// Le corps renommé d'après la vitre : la pièce obligatoire n'a plus d'objet
			missing: derive(sample, json => { json.nodes.forEach(node => { node.name = node.name.replace(/^batiment\./, 'vitre.'); }); }),
			// Les huit couleurs du cadre, et un objet du corps doublé sous une neuvième
			colors: derive(unpack(Buffer.from(frame.bytes)), json => {
				const node = json.nodes.find(node => node.mesh !== undefined && node.name.startsWith('batiment'));
				json.materials.push({ pbrMetallicRoughness: { baseColorFactor: [.9, .1, .6, 1] } });
				json.meshes.push({ primitives: json.meshes[node.mesh].primitives.map(primitive => ({ ...primitive, material: json.materials.length - 1 })) });
				json.nodes.push({ ...node, name: 'batiment.neuvieme', mesh: json.meshes.length - 1 });
				json.scenes[0].nodes.push(json.nodes.length - 1);
			}),
			// Le bâtiment de 18 m étiré en hauteur jusqu'à 21 m : il dépasse le plafond des règles par le haut
			tooTall: derive(unpack(Buffer.from(tall)), json => { json.nodes.push({ children: json.scenes[0].nodes, scale: [1, 21 / 18, 1] }); json.scenes[0].nodes = [json.nodes.length - 1]; }),
			// Chaque objet posé trois fois de plus au même endroit : ses triangles comptent quatre fois, au-dessus du plafond
			triangles: derive(sample, json => { [1, 2, 3].forEach(() => meshes.forEach(node => { json.nodes.push({ name: node.name.replace(/\.\d+$/, '.1') + String(json.nodes.length).padStart(2, '0'), mesh: node.mesh }); json.scenes[0].nodes.push(json.nodes.length - 1); })); }),
			// Tout le bâtiment décalé de 10 m de côté : il sort de l'emprise centrée
			bounds: derive(sample, json => { json.nodes.push({ children: json.scenes[0].nodes, translation: [10, 0, 0] }); json.scenes[0].nodes = [json.nodes.length - 1]; }),
			// Tout le bâtiment enfoncé de 1 m : il sort aussi de l'emprise par le bas, mais sa correction est de le poser
			sunk: derive(sample, json => { json.nodes.push({ children: json.scenes[0].nodes, translation: [0, -1, 0] }); json.scenes[0].nodes = [json.nodes.length - 1]; }),
			// L'ancre de la porte posée deux fois
			anchorTwice: derive(sample, json => { json.nodes.push({ name: 'porte', translation: [1, 0, 7] }); json.scenes[0].nodes.push(json.nodes.length - 1); }),
			// L'enseigne posée loin devant le bâtiment
			anchorOutside: derive(sample, json => { json.nodes.find(node => node.name === 'enseigne').translation = [0, 4, 40]; }),
		};

		for(const [name, data] of Object.entries(derived)) {
			writeFileSync(OUTPUT + name + '.glb', data);
		}

		// Des règles sans profil connu ne se lisent pas, plutôt que de passer pour un décor sans pièces
		let unknownProfile = false;

		try {
			sceneUploadRules({ version: 1 });
		} catch {
			unknownProfile = true;
		}

		process.stdout.write(JSON.stringify({
			unknownProfile,
			worst,
			names: [...new Set(read.parts.map(part => part.name ?? null))],
			anchors: read.anchors,
			corners,
			flat,
		}));
		JS;

	private string $directory = '';
	private array $result = [];

	// Une seule sonde pour toute la classe : chaque méthode lit son morceau du résultat et les fichiers écrits
	public function init(): void {

		$this->directory = sys_get_temp_dir().'/building-'.bin2hex(random_bytes(6));
		mkdir($this->directory);

		$process = proc_open(['node', '--input-type=module', '-', LIME_DIRECTORY.'/base/scene', $this->directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), 'node ne se lance pas ici : ce test monte les bâtiments sous node, l\'installer sur la machine qui lance les tests') === FALSE) {
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

	public function finalize(): void {

		if($this->directory !== '') {
			exec('rm -rf '.escapeshellarg($this->directory));
		}

	}

	public function testRules(): void {

		$rules = UploadLib::getRules('decor');

		$this->assertTrue(is_int($rules['version']) and is_int($rules['maxBytes']), "les règles du décor ne portent pas leur version et leur poids à la racine, que lit l'import");
		$this->assertTrue(is_int($rules['families']['building']['maxTriangles'] ?? NULL), "les règles du décor ne portent pas le plafond de triangles d'un bâtiment, que lit l'import");

		$this->assertTrue($this->result['unknownProfile'] ?? FALSE, "le lecteur accepte des règles sans profil connu");

		foreach([['decor', 'silo'], ['decor', NULL], ['avatar', 'building']] as [$profile, $family]) {

			try {
				UploadLib::getModelRules($profile, $family);
				$refused = FALSE;
			} catch(\Exception) {
				$refused = TRUE;
			}

			$this->assertTrue($refused, "le profil ".$profile." accepte la famille ".$family." qu'il ne déclare pas");

		}

	}

	public function testEditorFits(): void {

		$rules = UploadLib::getModelRules('decor', 'building');

		$this->assertEquals(['building', 'hangar', 'house'], array_keys($this->result['worst'] ?? []), "la sonde ne mesure pas chaque type de l'éditeur du décor");

		foreach($this->result['worst'] ?? [] as $type => $measure) {

			$this->assertEquals(0, $measure['failed'], "l'éditeur ne compose pas certains ".$type." à ses cotes les plus grandes");
			$this->assertTrue($measure['z'] * 2 <= $rules['bounds']['length'] and $measure['x'] * 2 <= $rules['bounds']['width'] and $measure['y'] <= $rules['bounds']['height'], "un ".$type." de l'éditeur sort de l'emprise des règles : ".json_encode($measure));
			$this->assertTrue($measure['triangles'] <= $rules['maxTriangles'], "un ".$type." de l'éditeur dépasse le plafond de triangles des règles : ".$measure['triangles']);
			$this->assertTrue($measure['colors'] <= $rules['maxColors'], "un ".$type." de l'éditeur dépasse le plafond de couleurs des règles : ".$measure['colors']);

		}

	}

	public function testAccepted(): void {

		$result = UploadLib::run('decor', $this->directory.'/composed.glb', 'building');

		$this->assertEquals(['rules', 'triangles', 'index', 'buffer'], array_keys($result), "le serveur ne convertit pas un bâtiment composé : ".json_encode($result['refusal'] ?? NULL));
		$this->assertEquals(['building'], array_keys($result['index']['models'] ?? []), "le modèle converti ne porte pas le nom de sa famille, que lit sceneBuildModel()");

	}

	// Les plafonds relevés le 24/10 : 20 m de haut, 8 couleurs et 2 500 triangles pour un bâtiment
	public function testCeilings(): void {

		$rules = UploadLib::getModelRules('decor', 'building');

		$this->assertEquals(20, $rules['bounds']['height'], "le plafond de hauteur d'un bâtiment n'est pas de 20 m");
		$this->assertEquals(8, $rules['maxColors'], "le plafond de couleurs d'un bâtiment n'est pas de 8");

		$this->assertEquals(2500, $rules['maxTriangles'], "le plafond de triangles d'un bâtiment n'est pas de 2 500");

		foreach(['tall' => 'un bâtiment de 18 m', 'eightColors' => 'un bâtiment de 8 couleurs', 'trianglesMax' => 'un bâtiment de 2 500 triangles'] as $name => $label) {
			$result = UploadLib::run('decor', $this->directory.'/'.$name.'.glb', 'building');
			$this->assertEquals(['rules', 'triangles', 'index', 'buffer'], array_keys($result), $label." est refusé : ".json_encode($result['refusal'] ?? NULL));
		}

	}

	public function testReadAsBuilding(): void {

		$names = $this->result['names'] ?? [];
		sort($names);

		$this->assertEquals([NULL, 'vantailDroit', 'vantailGauche', 'vitre'], $names, "le modèle relu n'a pas le corps sans nom et les pièces nommées d'un bâtiment composé");
		$this->assertEquals(['porte', 'porteCoin', 'enseigne', 'faitage'], array_keys($this->result['anchors'] ?? []), "le modèle relu ne porte pas les ancres du cadre");
		$this->assertTrue(($this->result['corners'] ?? 0) > 0 and $this->result['flat'] === $this->result['corners'], "des coins du bâtiment relu ne portent pas la normale de leur face : ".($this->result['flat'] ?? 0).' sur '.($this->result['corners'] ?? 0));

	}

	public function testRefusals(): void {

		$rules = UploadLib::getModelRules('decor', 'building');

		$expected = [
			'missing' => ['refusal' => 'missing', 'values' => ['piece' => 'batiment']],
			'colors' => ['refusal' => 'colors', 'values' => ['count' => 9, 'max' => $rules['maxColors']]],
			'bounds' => ['refusal' => 'bounds', 'values' => $rules['bounds']],
			'tooTall' => ['refusal' => 'bounds', 'values' => $rules['bounds']],
			'trianglesOver' => ['refusal' => 'triangles', 'values' => ['count' => $rules['maxTriangles'] + 1, 'max' => $rules['maxTriangles']]],
			'sunk' => ['refusal' => 'ground', 'values' => []],
			'anchorTwice' => ['refusal' => 'anchor', 'values' => ['name' => 'porte']],
			'anchorOutside' => ['refusal' => 'anchor', 'values' => ['name' => 'enseigne']],
		];

		foreach($expected as $name => $verdict) {
			$result = UploadLib::run('decor', $this->directory.'/'.$name.'.glb', 'building');
			$this->assertEquals($verdict, ['refusal' => $result['refusal'] ?? NULL, 'values' => $result['values'] ?? NULL], $name.'.glb n\'est pas refusé comme attendu');
		}

		$result = UploadLib::run('decor', $this->directory.'/triangles.glb', 'building');

		$this->assertEquals('triangles', $result['refusal'] ?? NULL, "un bâtiment posé quatre fois n'est pas refusé pour ses triangles");
		$this->assertEquals($rules['maxTriangles'], $result['values']['max'] ?? NULL, "le refus des triangles ne rend pas le plafond de la famille");

	}

}
?>
