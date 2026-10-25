<?php
namespace scene;

/**
 * Les bâtiments qu'une structure compose dans l'éditeur du décor (`static/js/building.js`, `building.build`), éprouvés sous
 * node tels que le navigateur les charge, sur toutes les combinaisons de `static/building/choices.json`. Chacune doit se
 * monter, sous le plafond d'un bâtiment : 1 500 triangles et 6 couleurs (`base/scene/CLAUDE.md`, « Le contrat d'un module »,
 * et `ModelSentinel`). Le serveur n'accepte que ces combinaisons (`map\DecorBuilding::properties()`), donc aucune autre ne
 * peut atteindre une scène.
 *
 * Les formes se comptent à chaque coin des bornes de leurs cotes et aux cotes de départ, une teinte par choix de teinte ; les
 * teintes ne changent pas la géométrie. Les couleurs se comptent aux cotes de départ, chaque teinte remplacée par une couleur
 * qu'aucune pièce ne porte : c'est le pire cas, une teinte réelle ne pouvant que se confondre avec une autre couleur.
 *
 * Aucune écriture, aucune base ouverte.
 *
 * Lancement : dev/test package=scene file=BuildingAi
 */
class BuildingAiTest extends \Test {

	const int TRIANGLES_MAX = 1500;
	const int COLORS_MAX = 6;

	/* L'écart de clarté L* entre un mur de pierre et ses pierres. Sous le plancher, les moellons se fondent dans le mur sur le plan
	 * (7,2 sur la tuile à 15 % d'assombrissement) ; au-dessus du plafond, ils sautent aux yeux sur un mur clair (22 sur la pierre
	 * beige à 30 %, « moins de contrates pierres/fond », 24/10) */
	const int STONE_CONTRAST_MIN = 9;
	const int STONE_CONTRAST_MAX = 20;
	const int STONE_TRIANGLES = 240;

	/* Les choix qui sont des teintes, que l'éditeur propose en pastilles */
	const array TINTS = ['coverTint', 'wallTint', 'joinery'];

	public function testCombinations(): void {

		$result = $this->run('
			const tints = '.json_encode(self::TINTS).';

			// Les combinaisons de choix d\'un type, celles que le type écarte retirées
			const combinations = (type, only) => {
				const entry = choices[type];
				let list = [{}];
				for(const [key, values] of Object.entries(entry.settings)) {
					list = list.flatMap(settings => (only(key) ? values : [null]).map(value => ({ ...settings, [key]: value })));
				}
				return list;
			};
			const excluded = (type, settings) => choices[type].excluded.some(rule => Object.entries(rule).every(([key, value]) => settings[key] === value));

			const out = {};

			for(const [type, entry] of Object.entries(choices)) {

				const corners = value => value === null ? [null] : value;
				const sizes = [entry.initial];
				for(const length of corners(entry.length)) {
					for(const width of corners(entry.width)) {
						for(const height of corners(entry.height)) {
							sizes.push({ length, width, height });
						}
					}
				}

				const summary = { shapes: 0, failed: [], triangles: 0, colors: 0, overColors: [] };

				// Les formes : une teinte par choix de teinte, la première que le reste des choix admet
				for(const shape of combinations(type, key => tints.includes(key) === false)) {
					for(const key of tints.filter(key => key in entry.settings)) {
						shape[key] = entry.settings[key].find(value => excluded(type, { ...shape, [key]: value }) === false);
					}
					if(excluded(type, shape)) {
						continue;
					}
					for(const size of sizes) {
						summary.shapes++;
						const model = building.build({ type, ...size, settings: shape });
						if(model === null) {
							summary.failed.push({ size, shape });
							continue;
						}
						summary.triangles = Math.max(summary.triangles, model.parts.reduce((sum, part) => sum + part.geometry.attributes.position.count / 3, 0));
					}
				}

				// Les couleurs : chaque teinte devient une couleur qu\'aucune pièce ne porte
				for(const shape of combinations(type, key => tints.includes(key) === false)) {
					if(excluded(type, shape)) {
						continue;
					}
					tints.filter(key => key in entry.settings).forEach((key, i) => shape[key] = "#00000" + (i + 1));
					const model = building.build({ type, ...entry.initial, settings: shape });
					if(model === null) {
						summary.failed.push({ size: entry.initial, shape });
						continue;
					}
					const colors = new Set(model.parts.map(part => part.color)).size;
					summary.colors = Math.max(summary.colors, colors);
					if(colors > '.self::COLORS_MAX.' && summary.overColors.length < 3) {
						summary.overColors.push(shape);
					}
				}

				summary.failed = summary.failed.slice(0, 3);
				out[type] = summary;

			}

			emit(out);
		');

		foreach(['building', 'hangar', 'house'] as $type) {

			$summary = $result[$type] ?? NULL;

			if($this->assertTrue($summary !== NULL, "choices.json ne porte plus le type ".$type) === FALSE) {
				continue;
			}

			$this->assertTrue($summary['shapes'] > 0, "aucune forme de ".$type." n'a été montée");
			$this->assertEquals([], $summary['failed'], "ces bâtiments ".$type." ne se composent pas : resserrer les bornes de choices.json ou corriger la composition de building.js");
			$this->assertTrue($summary['triangles'] <= self::TRIANGLES_MAX, "un bâtiment ".$type." monte à ".$summary['triangles']." triangles, au-delà de ".self::TRIANGLES_MAX);
			$this->assertTrue($summary['colors'] <= self::COLORS_MAX, "un bâtiment ".$type." porte ".$summary['colors']." couleurs, au-delà de ".self::COLORS_MAX." : lier une couleur secondaire à une teinte dans building.js. Exemples : ".json_encode($summary['overColors']));

		}

	}

	/**
	 * Un mur de pierre se distingue d'un enduit de la même teinte, à l'aperçu de l'éditeur comme sur le plan : l'éclairage en
	 * aplats des scènes ne rend pas le relief, seule la couleur de ses chaines d'angle et de ses moellons le dit. Pour chaque
	 * teinte que l'éditeur propose avec la pierre, le mur de pierre porte exactement une couleur de plus que l'enduit, plus
	 * sombre que le mur de `STONE_CONTRAST_MIN` à `STONE_CONTRAST_MAX` en clarté L*, sur assez de triangles pour couvrir le
	 * mur : des chaines seules en compteraient seize par assise, soit 128 sur le bâtiment de départ. Les pierres d'une couleur
	 * partent en une seule pièce, d'où un compte en triangles et non en pièces. Le mur de pierre du catalogue, tel qu'une fiche
	 * du skill 3d-batiment le cite sans réglage, tient le même écart.
	 */
	public function testStoneContrast(): void {

		$result = $this->run('
			// La clarté L* de CIE Lab d\'une couleur #rrggbb
			const lightness = hex => {
				const [r, g, b] = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255).map(c => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4);
				const y = .2126 * r + .7152 * g + .0722 * b;
				return 116 * (y > .008856 ? Math.cbrt(y) : 7.787 * y + 16 / 116) - 16;
			};
			const out = [];
			for(const [type, entry] of Object.entries(choices)) {
				if(entry.settings.wall.includes("pierre") === false) {
					continue;
				}
				for(const wallTint of entry.settings.wallTint) {
					const settings = { ...entry.initial.settings, wallTint };
					const colors = wall => new Set(building.build({ type, ...entry.initial, settings: { ...settings, wall } }).parts.map(part => part.color));
					const plaster = colors("crepi");
					const stone = building.build({ type, ...entry.initial, settings: { ...settings, wall: "pierre" } });
					const added = [...new Set(stone.parts.map(part => part.color))].filter(color => plaster.has(color) === false);
					const wall = choices[type].colors.wallTint[wallTint];
					out.push({
						type, wallTint, added,
						contrast: added.length === 1 ? Math.round((lightness(wall) - lightness(added[0])) * 10) / 10 : null,
						triangles: stone.parts.filter(part => added.includes(part.color)).reduce((sum, part) => sum + part.geometry.attributes.position.count / 3, 0),
					});
				}
			}
			const fiche = wall => ({
				nom: "pierre", corps: { toit: "deux-pans", largeur: 8, profondeur: 12, egout: 4, faitage: 6, socle: 0 },
				mur: { catalogue: wall }, couverture: { catalogue: "tole" }, ouvertures: [],
			});
			// L\'enduit du catalogue n\'a pas la teinte de la pierre, et le mur de pierre, la première pièce, est écarté avec lui
			const plaster = new Set(building.construct(fiche("crepi")).parts.map(part => part.color));
			const stone = building.construct(fiche("pierre"));
			const added = [...new Set(stone.parts.map(part => part.color))].filter(color => plaster.has(color) === false && color !== stone.parts[0].color);
			out.push({
				type: "catalogue", wallTint: "pierre", added,
				contrast: added.length === 1 ? Math.round((lightness(stone.parts[0].color) - lightness(added[0])) * 10) / 10 : null,
				triangles: stone.parts.filter(part => added.includes(part.color)).reduce((sum, part) => sum + part.geometry.attributes.position.count / 3, 0),
			});
			emit(out);
		');

		$this->assertTrue(count($result) >= 13, "les teintes de mur de building et house ne se lisent plus dans choices.json");

		foreach($result as ['type' => $type, 'wallTint' => $wallTint, 'added' => $added, 'contrast' => $contrast, 'triangles' => $triangles]) {

			$this->assertEquals(1, count($added), "le mur de pierre ".$wallTint." d'un ".$type." porte ".count($added)." couleurs de plus que l'enduit, une seule est attendue");
			$this->assertTrue($contrast !== NULL and $contrast >= self::STONE_CONTRAST_MIN, "la pierre ".$wallTint." d'un ".$type." ne s'assombrit que de ".$contrast." en L*, ".self::STONE_CONTRAST_MIN." au moins sont attendus");
			$this->assertTrue($contrast !== NULL and $contrast <= self::STONE_CONTRAST_MAX, "la pierre ".$wallTint." d'un ".$type." s'assombrit de ".$contrast." en L*, ".self::STONE_CONTRAST_MAX." au plus sont attendus");
			$this->assertTrue($triangles >= self::STONE_TRIANGLES, "les pierres du mur ".$wallTint." d'un ".$type." ne comptent que ".$triangles." triangles, ".self::STONE_TRIANGLES." au moins sont attendus");

		}

	}

	/**
	 * Une pose peut citer un bâtiment retiré, ou d'un type que le fichier ne connait plus : la scène qui le monte dans un
	 * `onrender` ne doit pas tomber pour lui (`CLAUDE.md`, « JS en navigation AJAX »). Un bâtiment enregistré avant un choix neuf
	 * prend le choix de départ de son type.
	 */
	public function testBuildNeverThrows(): void {

		$result = $this->run('
			const { settings, ...initial } = choices.house.initial;
			const partial = { ...settings };
			delete partial.chimney;
			emit({
				missing: building.build(null) === null,
				unknown: building.build({ type: "barn", length: 10, width: 6, height: 3, settings: {} }) === null,
				broken: building.build({ type: "building", length: 10, width: 6, height: 3, settings: { door: "porte-blindee" } }) === null,
				partial: building.build({ type: "house", ...initial, settings: partial }) !== null,
			});
		');

		$this->assertTrue($result['missing'] ?? FALSE, "un bâtiment absent rend null");
		$this->assertTrue($result['unknown'] ?? FALSE, "un type inconnu rend null");
		$this->assertTrue($result['broken'] ?? FALSE, "un choix inconnu rend null");
		$this->assertTrue($result['partial'] ?? FALSE, "une maison sans choix de cheminée prend celui de départ");

	}

	/**
	 * Les choix de départ et les teintes qu'un changement de matériau propose sont des choix que le serveur accepte.
	 */
	public function testInitialChoicesAreAllowed(): void {

		$choices = json_decode(file_get_contents($this->getDirectory().'/static/building/choices.json'), TRUE, flags: JSON_THROW_ON_ERROR);

		foreach($choices as $type => $entry) {

			$this->assertEquals(array_keys($entry['settings']), array_keys($entry['initial']['settings']), "les choix de départ de ".$type." nomment chaque choix du type");

			foreach($entry['initial']['settings'] as $key => $value) {
				$this->assertTrue(in_array($value, $entry['settings'][$key], TRUE), $type.".initial.settings.".$key." n'est pas un choix du type");
			}

			foreach(['length', 'width', 'height'] as $property) {

				$bounds = $entry[$property];
				$value = $entry['initial'][$property];

				$this->assertTrue(
					($bounds === NULL and $value === NULL) or ($bounds !== NULL and $value >= $bounds[0] and $value <= $bounds[1]),
					$type.".initial.".$property." sort de ses bornes"
				);

			}

			foreach($entry['tints'] as $key => [$material, $defaults]) {

				$this->assertEquals($entry['settings'][$material], array_keys($defaults), $type.".tints.".$key." donne une teinte à chaque choix de ".$material);

				foreach($defaults as $choice => $tint) {
					$this->assertTrue(in_array($tint, $entry['settings'][$key], TRUE), $type.".tints.".$key.".".$choice." n'est pas une teinte du type");
				}

			}

		}

	}

	/**
	 * Les pastilles de l'éditeur, que le serveur rend avant que la fabrique soit chargée, portent la couleur que la fabrique
	 * peindra : chacune est celle de la palette P sous le même nom, et chaque choix de la clé a la sienne.
	 */
	public function testColorsFollowPalette(): void {

		$choices = json_decode(file_get_contents($this->getDirectory().'/static/building/choices.json'), TRUE, flags: JSON_THROW_ON_ERROR);

		preg_match_all("/(\w+): '(#[0-9a-f]{6})'/", file_get_contents($this->getDirectory().'/static/js/building.js'), $matches);
		$palette = array_combine($matches[1], $matches[2]);

		$this->assertTrue(isset($palette['feuilleSombre']), "la palette P ne se lit plus dans static/js/building.js");

		foreach($choices as $type => $entry) {

			foreach($entry['colors'] as $key => $colors) {

				$this->assertEquals($entry['settings'][$key], array_keys($colors), $type.".colors.".$key." donne une couleur à chaque choix de ".$key);

				foreach($colors as $name => $color) {
					$this->assertEquals($palette[$name] ?? NULL, $color, $type.".colors.".$key.".".$name." n'est pas la couleur de P.".$name);
				}

			}

		}

	}

	/**
	 * La palette de `building.js` est celle de `model/helpers.js`, que la cuisson passe aux modèles : une couleur qui y bougerait
	 * seule ferait d'un bâtiment composé un autre bâtiment que celui du catalogue.
	 */
	public function testPaletteIsShared(): void {

		$read = function(string $path, string $pattern): string {

			$matched = preg_match($pattern, file_get_contents($this->getDirectory().'/'.$path), $matches);

			$this->assertEquals(1, $matched, "la palette P ne se trouve plus dans ".$path);

			return preg_replace('/\s+/', '', $matches[1] ?? '');

		};

		$this->assertEquals(
			$read('model/helpers.js', '/^export const P = (\{.*?\});/ms'),
			$read('static/js/building.js', '/^\tconst P = (\{.*?\});/ms'),
			"la palette P de static/js/building.js diffère de celle de model/helpers.js"
		);

	}

	private function getDirectory(): string {
		return LIME_DIRECTORY.'/base/scene';
	}

	/**
	 * Le scénario joué sous node, avec `building` (la fabrique, sur les choix servis) et `choices`, et `emit()` qui rend son
	 * résultat.
	 */
	private function run(string $scenario): array {

		$script = '
			const directory = "file://" + '.json_encode($this->getDirectory().'/static').';
			const emit = value => process.stdout.write(JSON.stringify(value));

			// Les crochets de la cuisson font lire en modules ES les .js du package, qu\'aucun package.json ne déclare tels
			require("node:module").register("file://" + '.json_encode($this->getDirectory().'/bake/resolve.mjs').');

			(async () => {
				const THREE = await import(directory + "/lib/three-0.170.0.module.min.js");
				const addons = await import(directory + "/lib/three-0.170.0.BufferGeometryUtils.js");
				const { sceneCreateGeometry } = await import(directory + "/js/geometry.js");
				const { sceneCreateBuilding } = await import(directory + "/js/building.js");
				const { readFileSync } = await import("node:fs");
				const choices = JSON.parse(readFileSync(new URL(directory + "/building/choices.json"), "utf8"));
				const building = sceneCreateBuilding(THREE, sceneCreateGeometry(THREE, addons), choices);

				'.$scenario.'
			})();
		';

		$process = proc_open(['node', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, NULL, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test éprouve les bâtiments du socle 3D sous node, l'installer sur la machine qui lance les tests") === FALSE) {
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
?>
