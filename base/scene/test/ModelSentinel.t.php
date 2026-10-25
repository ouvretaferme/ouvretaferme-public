<?php
namespace scene;

/**
 * Un modèle 3D vit deux fois dans ce package : le module qui le construit (`model/<slug>.js`) et sa
 * cuisson (`static/model/models.json` et `models.bin`), seule servie. Rien ne relie les deux à
 * l'exécution : un module retouché sans recuisson laisse l'ancien modèle à l'écran, sans erreur.
 * Cette sentinelle compare l'empreinte de chaque module à celle que la cuisson a relevée, et tient
 * le plafond de triangles et de couleurs que chaque modèle paie multiplié par le nombre de pieds.
 *
 * Aucune écriture, aucune base ouverte : elle ne lit que des fichiers du package.
 *
 * Lancement : dev/test package=scene file=ModelSentinel
 */
class ModelSentinelTest extends \Test {

	const int TRIANGLES = 300;
	const int COLORS = 4;

	/**
	 * Les modèles au-dessus d'un plafond, avec leur mesure et leur raison. Une mesure qui bouge,
	 * dans un sens ou dans l'autre, se redéclare.
	 */
	const array OVER = [
		'courgette.fruits' => ['triangles' => 420, 'colors' => 5, 'reason' => "le pied de courgette et ses trois fruits en un seul modèle, variante retenue le 2026-10-08, gardée telle quelle au-dessus du plafond par lui le 2026-10-09 ; le pied seul, `courgette`, tient le plafond"],
		'paysan' => ['triangles' => 296, 'colors' => 5, 'reason' => "cinq couleurs pour un personnage dessiné à quelques exemplaires et non par milliers comme une plante : la peau, la tenue, la salopette, les bottes et la coiffe se lisent chacune de loin ; un personnage n'a pas le plafond des couleurs (2026-10-10)"],
		'paysan.houe' => ['triangles' => 314, 'colors' => 6, 'reason' => "le paysan à cinq couleurs et sa houe, dont le fer en tôle ajoute la sixième ; un personnage n'a pas le plafond des couleurs, et ses 314 triangles tiennent le sien, 2 000 (2026-10-10)"],
		'paysan.fourche' => ['triangles' => 280, 'colors' => 5, 'reason' => "cinq couleurs : la peau, la tenue, la casquette, le bois sombre de la salopette, des bottes et du manche, la tôle des dents de la fourche ; un personnage n'a pas le plafond des couleurs (2026-10-10)"],
		'paysan.casquette' => ['triangles' => 256, 'colors' => 5, 'reason' => "cinq couleurs aux mêmes places que le paysan, la casquette à la place du chapeau : la peau, la tenue, la salopette, les bottes et la casquette ; un personnage n'a pas le plafond des couleurs (2026-10-10)"],
		'brouette' => ['triangles' => 114, 'colors' => 5, 'reason' => "la brouette que pousse un paysan, dessinée à un exemplaire par paysan et non par milliers comme une plante : la caisse, son bord, la roue, le pneu et les bras se lisent chacun"],
		'paysan-a' => ['triangles' => 1567, 'colors' => 15, 'reason' => "paysan anonyme sur le personnage en os, dessiné à quelques exemplaires et non par milliers comme une plante : chaque vêtement, la peau, les cheveux et l'outil portent leur couleur ; un personnage a son propre plafond, 2 000 triangles, et pas celui des couleurs (2026-10-10)"],
		'paysan-b' => ['triangles' => 1744, 'colors' => 16, 'reason' => "paysan anonyme sur le personnage en os, dessiné à quelques exemplaires et non par milliers comme une plante : chaque vêtement, la peau, les cheveux et l'outil portent leur couleur ; un personnage a son propre plafond, 2 000 triangles, et pas celui des couleurs (2026-10-10)"],
		'paysan-c' => ['triangles' => 1582, 'colors' => 15, 'reason' => "paysan anonyme sur le personnage en os, dessiné à quelques exemplaires et non par milliers comme une plante : chaque vêtement, la peau, les cheveux et l'outil portent leur couleur ; un personnage a son propre plafond, 2 000 triangles, et pas celui des couleurs (2026-10-10)"],
		'paysan-d' => ['triangles' => 1678, 'colors' => 12, 'reason' => "paysan anonyme sur le personnage en os, dessiné à quelques exemplaires et non par milliers comme une plante : chaque vêtement, la peau et les cheveux portent leur couleur, sans outil en main ; un personnage a son propre plafond, 2 000 triangles, et pas celui des couleurs (2026-10-10)"],
		'paysan-e' => ['triangles' => 1753, 'colors' => 14, 'reason' => "paysan anonyme sur le personnage en os, dessiné à quelques exemplaires et non par milliers comme une plante : chaque vêtement, la peau et les cheveux portent leur couleur, sans outil en main ; un personnage a son propre plafond, 2 000 triangles, et pas celui des couleurs (2026-10-10)"],
		'paysan-f' => ['triangles' => 1492, 'colors' => 10, 'reason' => "paysan anonyme sur le personnage en os, dessiné à quelques exemplaires et non par milliers comme une plante : chaque vêtement, la peau et les cheveux portent leur couleur, sans outil en main ; un personnage a son propre plafond, 2 000 triangles, et pas celui des couleurs (2026-10-10)"],
		'abri' => ['triangles' => 184, 'colors' => 6, 'reason' => "six couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'arbre' => ['triangles' => 364, 'colors' => 4, 'reason' => "un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'bureau' => ['triangles' => 568, 'colors' => 6, 'reason' => "six couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'caisses' => ['triangles' => 156, 'colors' => 5, 'reason' => "cinq couleurs ; un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'quad' => ['triangles' => 900, 'colors' => 4, 'reason' => "un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'tracteur' => ['triangles' => 348, 'colors' => 4, 'reason' => "un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'cuve' => ['triangles' => 156, 'colors' => 5, 'reason' => "cinq couleurs ; un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'fruitier.or' => ['triangles' => 298, 'colors' => 5, 'reason' => "cinq couleurs ; un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'fruitier.rouge' => ['triangles' => 298, 'colors' => 5, 'reason' => "cinq couleurs ; un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'hangar' => ['triangles' => 616, 'colors' => 6, 'reason' => "six couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'bergerie' => ['triangles' => 808, 'colors' => 5, 'reason' => "cinq couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'chambre-froide' => ['triangles' => 292, 'colors' => 6, 'reason' => "six couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'garage' => ['triangles' => 208, 'colors' => 6, 'reason' => "six couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'fenil' => ['triangles' => 1124, 'colors' => 6, 'reason' => "six couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'hangar-solaire' => ['triangles' => 1012, 'colors' => 5, 'reason' => "cinq couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'laverie' => ['triangles' => 1324, 'colors' => 6, 'reason' => "six couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'magasin' => ['triangles' => 952, 'colors' => 6, 'reason' => "six couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'poulailler-mobile' => ['triangles' => 584, 'colors' => 5, 'reason' => "cinq couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'sechoir' => ['triangles' => 820, 'colors' => 4, 'reason' => "un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'stabulation' => ['triangles' => 1024, 'colors' => 5, 'reason' => "cinq couleurs ; un bâtiment du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un bâtiment, 1 500 triangles (2026-10-10)"],
		'mare' => ['triangles' => 221, 'colors' => 6, 'reason' => "six couleurs ; un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'bassin-bache' => ['triangles' => 472, 'colors' => 4, 'reason' => "un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'poche-eau' => ['triangles' => 500, 'colors' => 4, 'reason' => "quatre couleurs ; un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
		'puits' => ['triangles' => 336, 'colors' => 4, 'reason' => "un objet du décor, posé à quelques exemplaires et non par milliers comme une plante, sous le plafond d'un objet, 900 triangles (2026-10-10)"],
	];

	/**
	 * Les modules écrits à la main avant que tout modèle se fasse dans Blender (décidé le
	 * 2026-10-12, skill `3d`, « L'outil : Blender »). La liste ne fait que descendre : un module refait
	 * dans Blender en sort, et un module neuf écrit à la main n'y entre pas.
	 */
	const array HANDWRITTEN = [
		'abri', 'anemone', 'arbre', 'arbre-mort', 'arbre.peuplier', 'arbre.sapin', 'bande-fleurie', 'blette',
		'bleuet', 'bouleau', 'brouette', 'buisson', 'bureau', 'caisse', 'caisses', 'campanule',
		'cardon', 'champignon-de-paris', 'chene', 'chou-blanc', 'chou-fleur',
		'chou-pointu', 'chou-rave', 'chou-romanesco', 'chou-rouge', 'ciboule-de-chine', 'ciboulette', 'cloture.electrique', 'cloture.electrique.bout',
		'cloture.ganivelle', 'cloture.ganivelle.bout', 'cloture.mouton', 'cloture.mouton.bout', 'cloture.rigide', 'cloture.rigide.bout', 'compost', 'concombre',
		'courgette', 'courgette.fruits', 'cuve', 'delphinium', 'digitale', 'echalote',
		'electrovanne', 'endive', 'feverole', 'figue', 'fougeres', 'framboise', 'fruitier', 'fruitier.or',
		'fruitier.rouge', 'groseille', 'haie', 'hangar', 'hibiscus', 'kiwi', 'lavande',
		'lupin', 'mache', 'mais', 'mare', 'marguerite',
		'melon', 'mure', 'myrtille', 'narcisse', 'panneau', 'pasteque',
		'paysan', 'paysan.casquette', 'paysan.fourche', 'paysan.houe', 'peche', 'pe-tsai', 'physalis', 'pierrier', 'pivoine', 'poche-eau',
		'poireau', 'poivron', 'pomme', 'pomme-de-terre', 'portail', 'prune', 'puits', 'rocher',
		'roncier', 'roseaux', 'rudbeckia', 'safran', 'sarrasin', 'saule',
		'scabieuse', 'seigle', 'souche', 'tomate', 'topinambour', 'tournesol', 'tulipe', 'vesce',
		'vigne', 'zinnia',
	];

	private function getDirectory(): string {
		return LIME_DIRECTORY.'/base/scene';
	}

	private function getIndex(): array {
		return json_decode(file_get_contents($this->getDirectory().'/static/model/models.json'), TRUE);
	}

	/**
	 * @return array<string, string> l'empreinte de chaque module, par slug
	 */
	private function getModules(): array {

		$modules = [];

		foreach(glob($this->getDirectory().'/model/*.js') as $path) {
			$modules[basename($path, '.js')] = sha1_file($path);
		}

		return $modules;

	}

	public function testEveryModuleIsBaked(): void {

		$modules = $this->getModules();
		$index = $this->getIndex();

		$this->assertTrue(count($modules) > 100, "la sentinelle ne trouve plus les modules de base/scene/model : elle ne sait plus lire ce qu'elle compare");

		$helpers = $modules['helpers'];
		unset($modules['helpers']);

		$missing = array_keys(array_diff_key($modules, $index['models']));
		$orphan = array_keys(array_diff_key($index['models'], $modules));

		$stale = [];
		foreach($index['models'] as $slug => $model) {
			if(isset($modules[$slug]) and $modules[$slug] !== $model['hash']) {
				$stale[] = $slug;
			}
		}

		$gesture = "le module fait foi, la cuisson se refait par `node base/scene/bake/bake.mjs` et ses deux fichiers partent avec le module";

		$this->assertEquals([], $missing, "modules sans cuisson, ".$gesture." : ".implode(', ', $missing));
		$this->assertEquals([], $orphan, "modèles cuits dont le module n'existe plus, ".$gesture." : ".implode(', ', $orphan));
		$this->assertEquals([], $stale, "modules retouchés depuis leur cuisson, ".$gesture." : ".implode(', ', $stale));
		$this->assertEquals($helpers, $index['helpers'], "model/helpers.js a changé depuis la cuisson, et tous les modules s'en servent : ".$gesture);

	}

	public function testBinaryHoldsWhatTheIndexCounts(): void {

		$index = $this->getIndex();

		// Par sommet : trois Int16 de position et trois Int8 de normale ; puis trois octets sRGB par sommet coloré
		$this->assertEquals($index['vertices'] * 9 + $index['colors'] * 3, filesize($this->getDirectory().'/static/model/models.bin'), "models.bin n'a pas la taille que models.json annonce, les deux fichiers ne viennent pas de la même cuisson : relancer `node base/scene/bake/bake.mjs` et pousser les deux");

	}

	public function testEveryModelFitsItsBudget(): void {

		$over = [];
		foreach($this->getIndex()['models'] as $slug => $model) {
			if($model['triangles'] > self::TRIANGLES or $model['colors'] > self::COLORS) {
				$over[$slug] = $model['triangles'].' triangles, '.$model['colors'].' couleurs';
			}
		}

		$declared = [];
		foreach(self::OVER as $slug => $measure) {
			$declared[$slug] = $measure['triangles'].' triangles, '.$measure['colors'].' couleurs';
		}

		$new = array_diff_assoc($over, $declared);
		$stale = array_diff_assoc($declared, $over);

		$this->assertEquals([], $new, "modèles au-dessus de ".self::TRIANGLES." triangles ou de ".self::COLORS." couleurs sans déclaration à cette mesure (alléger le module, ou le déclarer dans OVER avec sa raison) : ".implode(', ', array_map(fn($slug) => $slug.' ('.$new[$slug].')', array_keys($new))));
		$this->assertEquals([], $stale, "déclarations de OVER que la cuisson ne mesure plus ainsi, à retirer ou à remettre à la mesure : ".implode(', ', array_map(fn($slug) => $slug.' ('.$stale[$slug].')', array_keys($stale))));

	}

	/**
	 * Un module dit d'où il vient par son en-tête : l'export de Blender, ou le générateur de `3d-batiment`
	 * qui l'engendre depuis sa fiche. Tout autre module est écrit à la main.
	 */
	public function testEveryModelComesFromBlender(): void {

		$handwritten = [];
		foreach(glob($this->getDirectory().'/model/*.js') as $path) {

			$source = file_get_contents($path);

			if(
				basename($path) !== 'helpers.js' and
				str_contains($source, 'Généré par Blender') === FALSE and
				str_contains($source, 'Engendré par le skill 3d-batiment') === FALSE
			) {
				$handwritten[] = basename($path, '.js');
			}

		}

		$this->assertTrue(count($handwritten) < count(glob($this->getDirectory().'/model/*.js')), "la sentinelle prend tous les modules pour écrits à la main : elle ne sait plus lire leur en-tête");

		$new = array_values(array_diff($handwritten, self::HANDWRITTEN));
		$stale = array_values(array_diff(self::HANDWRITTEN, $handwritten));

		$this->assertEquals([], $new, "modules écrits à la main alors qu'un modèle 3D se fait dans Blender et s'exporte par .claude/skills/3d/blender_export.py (skill 3d, « L'outil : Blender ») : ".implode(', ', $new));
		$this->assertEquals([], $stale, "modules de HANDWRITTEN qui viennent désormais de Blender ou n'existent plus, à retirer de la liste : ".implode(', ', $stale));

	}

}
