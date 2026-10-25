<?php
namespace scene;

/**
 * Le dessus de la tête d'un avatar téléversé, vu d'en haut (`skull` des règles, `skullOpen()` de `upload/upload.mjs`). Le crâne
 * de `corps.glb` s'ouvre en haut sous sa calotte, et le site ne dessine que la face avant : un modèle qui retire la calotte
 * sans rien qui referme le dessus montre le sol au travers de la tête. C'est ce qu'a fait un modèle posé en dev sur la
 * structure 7, dont les cheveux étaient une copie gonflée du crâne ouvert. Toute composition de l'éditeur passe, chaque
 * coiffure et chaque couvre-chef du catalogue compris, et le corps nu aussi ; le corps sans sa calotte, la calotte retournée
 * et des cheveux copiés du crâne ouvert sont refusés.
 *
 * La sonde n'imprime que des codes de refus. Aucune écriture en base ni sur le disque.
 *
 * Lancement : dev/test package=scene file=UploadSkullAi
 */
class UploadSkullAiTest extends \Test {

	const PROBE = <<<'JS'
		import { register } from 'node:module';
		import { readFileSync } from 'node:fs';

		const SCENE = 'file://' + process.argv[2] + '/';

		// Les crochets de la cuisson font lire three et avatar.js, écrits pour le navigateur
		register(SCENE + 'bake/resolve.mjs');

		const THREE = await import('three');
		const { sceneCreateAvatar } = await import(SCENE + 'static/js/avatar.js');
		const { sceneUploadRules, sceneUploadCheck } = await import(SCENE + 'upload/upload.mjs');
		const glb = await import(SCENE + 'upload/glb.mjs');
		const { sceneUploadBoneParts } = await import(SCENE + 'upload/avatar/composer.mjs');

		const readJson = path => JSON.parse(readFileSync(new URL(path, SCENE), 'utf8'));
		const rules = sceneUploadRules(readJson('upload/avatar.json'));
		const catalog = readJson('static/avatar/catalog.json');
		const avatar = sceneCreateAvatar(THREE);

		// Le code du refus d'un modèle fait de `parts`, null s'il est accepté
		const judge = parts => {
			try {
				sceneUploadCheck(rules, glb.sceneUploadPack(glb.sceneUploadWriteGlb(parts)));
				return null;
			}
			catch(error) {
				if(error.refusal === undefined) {
					throw error;
				}
				return error.refusal;
			}
		};

		const compose = elements => judge(sceneUploadBoneParts(avatar.build({ corps: { peau: catalog.defaults.skin }, elements }), rules));
		const entries = key => catalog.families.find(family => family.key === key).entries;

		// Le corps nu, celui de corps.glb : la calotte est la pièce de la tête qui monte le plus haut, le crâne la plus dense
		const body = sceneUploadBoneParts(avatar.build({ corps: { peau: catalog.defaults.skin }, elements: [] }), rules)
			.map(part => ({ ...part, geometry: (part.geometry.index === null) ? part.geometry : part.geometry.toNonIndexed() }));
		const heads = body.filter(part => part.name === 'tete');
		const top = part => { part.geometry.computeBoundingBox(); return part.geometry.boundingBox.max.y; };
		const calotte = heads.reduce((a, b) => top(b) > top(a) ? b : a);
		const skull = heads.reduce((a, b) => b.geometry.attributes.position.count > a.geometry.attributes.position.count ? b : a);
		const without = body.filter(part => part !== calotte);

		// La calotte retournée : deux sommets de chaque triangle échangés, la face avant tournée vers l'intérieur
		const flipped = calotte.geometry.clone(), position = flipped.attributes.position;
		for(let i = 0; i < position.count; i += 3) {
			const x = position.getX(i + 1), y = position.getY(i + 1), z = position.getZ(i + 1);
			position.setXYZ(i + 1, position.getX(i + 2), position.getY(i + 2), position.getZ(i + 2));
			position.setXYZ(i + 2, x, y, z);
		}

		// Des cheveux copiés du crâne ouvert, agrandis de 8 % autour du pivot de la tête : ils gardent le même trou
		const [px, py, pz] = rules.pivots.tete;
		const hair = skull.geometry.clone().translate(-px, -py, -pz).scale(1.08, 1.08, 1.08).translate(px, py, pz);

		console.log(JSON.stringify({
			coiffures: Object.fromEntries(entries('coiffure').map(entry => [entry, compose([{ catalogue: entry, reglages: {} }])])),
			couvreChefs: Object.fromEntries(entries('couvre-chef').map(entry => [entry, compose([{ catalogue: 'courte', reglages: {} }, { catalogue: entry, reglages: {} }])])),
			body: judge(body),
			withoutCalotte: judge(without),
			flippedCalotte: judge([...without, { ...calotte, geometry: flipped }]),
			hairFromSkull: judge([...without, { name: 'tete', color: '#6b4423', geometry: hair }]),
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

	public function testEveryCoiffureClosesTheSkull(): void {

		$this->assertTrue(count($this->result['coiffures'] ?? []) > 0, 'la sonde ne lit aucune coiffure du catalogue');

		foreach($this->result['coiffures'] ?? [] as $coiffure => $refusal) {
			$this->assertEquals(NULL, $refusal, 'la coiffure '.$coiffure.' de l\'éditeur, composée, est refusée : '.$refusal);
		}

	}

	public function testEveryHeadwearClosesTheSkull(): void {

		$this->assertTrue(count($this->result['couvreChefs'] ?? []) > 0, 'la sonde ne lit aucun couvre-chef du catalogue');

		foreach($this->result['couvreChefs'] ?? [] as $couvreChef => $refusal) {
			$this->assertEquals(NULL, $refusal, 'le couvre-chef '.$couvreChef.' de l\'éditeur, composé, est refusé : '.$refusal);
		}

	}

	public function testBodyIsClosed(): void {
		$this->assertTrue(array_key_exists('body', $this->result) and $this->result['body'] === NULL, 'le corps nu de corps.glb est refusé, ou la sonde ne l\'a pas jugé');
	}

	public function testOpenSkullRefused(): void {

		$this->assertEquals('skull', $this->result['withoutCalotte'] ?? NULL, 'le corps sans sa calotte, crâne ouvert, est accepté');
		$this->assertEquals('skull', $this->result['flippedCalotte'] ?? NULL, 'la calotte retournée, invisible de dessus, est acceptée');
		$this->assertEquals('skull', $this->result['hairFromSkull'] ?? NULL, 'des cheveux copiés du crâne ouvert, qui en gardent le trou, sont acceptés');

	}

}
?>
