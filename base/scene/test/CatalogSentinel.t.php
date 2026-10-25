<?php
namespace scene;

/**
 * Le catalogue des espèces (`model/species.json`) et ses alias (`model/alias.json`) disent quel
 * modèle cuit montre une culture. Ils ne disent jamais qu'un modèle existe, ils le citent : la
 * liste des modèles est celle de `static/model/models.json`, que seule la cuisson écrit. Rien à
 * l'exécution ne relie la citation au modèle avant qu'une scène dessine une planche vide. Cette
 * sentinelle tient les citations, les modèles cuits que personne ne cite, et les clés qu'une
 * entrée peut porter, puisque `species.js` et `crop.js` ignorent sans rien dire une clé mal
 * orthographiée.
 *
 * Aucune écriture, aucune base ouverte : elle ne lit que des fichiers du package.
 *
 * Lancement : dev/test package=scene file=CatalogSentinel
 */
class CatalogSentinelTest extends \Test {

	/**
	 * Les clés qu'une entrée du catalogue peut porter, avec ce qui les lit.
	 */
	const array KEYS = [
		'plant' => "le nom de l'espèce, que species.get() compare au nom de la culture",
		'slug' => "l'identifiant de l'entrée, cible des alias, et le modèle quand module manque",
		'fqn' => "l'espèce du produit à laquelle l'entrée répond avant tout nom, species.get()",
		'family' => "la forme de l'espèce : ligneux (tree, berry), rangs et écart par défaut, crop.js et fruit.js",
		'module' => "le modèle cuit, quand il n'a pas le nom du slug",
		'fruitModule' => "le pied qui porte ses fruits, crop.models() le rend en dressed",
		'pickedModule' => "le pied allégé de ce qui se cueille, crop.models() le rend en picked",
		'd' => "l'emprise adulte en mètres, quand elle diffère de celle du modèle",
		'size' => "le facteur de taille du plant à l'écran, crop.size()",
		'sink' => "l'enfoncement du pied sous le sol, crop.models()",
		'pitch' => "l'écart sur le rang en part de l'emprise, crop.pitch()",
		'rows' => "le nombre de rangs, crop.rows()",
		'parterre' => "une fleur posée en parterre désordonné : rangs, écart et pose, crop.js",
		'standing' => "une espèce dont la récolte laisse le plant en place, fruit.harvestedWhole()",
		'color' => "la couleur principale, qui repeint le modèle",
		'leaf' => "la couleur du feuillage",
		'accent' => "la couleur d'accent",
	];

	/**
	 * Les clés que toute entrée porte : le nom et le slug la trouvent, la famille donne sa forme, et les trois couleurs sont
	 * celles que crop.js repeint quand un alias à couleurs en dérive une espèce.
	 */
	const array REQUIRED = ['plant', 'slug', 'family', 'color', 'leaf', 'accent'];

	/**
	 * Les couleurs qu'un alias peut porter, qui en font une espèce dérivée (species.js).
	 */
	const array TINTS = ['color', 'leaf', 'accent'];

	/**
	 * Les modèles cuits qu'aucune entrée ne cite, avec leur raison.
	 */
	const array UNUSED = [
		'engrais-vert' => "en attente de la session des modèles : l'entrée engrais-vert cite couvert, et elle dit lequel des deux modèles fait foi",
		'lentille' => "en attente de la session des modèles : l'entrée lentille cite couvert, et elle dit lequel des deux modèles fait foi",
		'mesclun' => "en attente de la session des modèles : l'entrée mesclun cite laitue, et elle dit lequel des deux modèles fait foi",
		'quinoa' => "en attente de la session des modèles : l'entrée quinoa cite couvert, et elle dit lequel des deux modèles fait foi",
		'paysan' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'paysan.houe' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'paysan.fourche' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'paysan.casquette' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'paysan-a' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'paysan-b' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'paysan-c' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'paysan-d' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'paysan-e' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'paysan-f' => "un personnage de scène, pas une espèce : la scène le demande par son slug",
		'brouette' => "un accessoire de personnage, pas une espèce : la scène le demande par son slug",
		'caisse' => "un accessoire de personnage, pas une espèce : la scène le demande par son slug",
		'merle' => "un animal du thème Matin calme, pas une espèce : la scène le demande par son slug (production/map)",
		'merle.vol' => "un animal du thème Matin calme, pas une espèce : la scène le demande par son slug (production/map)",
		'rouge-gorge' => "un animal du thème Matin calme, pas une espèce : la scène le demande par son slug (production/map)",
		'rouge-gorge.vol' => "un animal du thème Matin calme, pas une espèce : la scène le demande par son slug (production/map)",
		'herisson' => "un animal du thème Matin calme, pas une espèce : la scène le demande par son slug (production/map)",
		'hangar' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'bureau' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'abri' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'bergerie' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'chambre-froide' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'fenil' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'hangar-solaire' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'laverie' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'magasin' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'poulailler-mobile' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'sechoir' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'stabulation' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'mare' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'bassin-bache' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'cuve' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'poche-eau' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'puits' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'pont' => "un modèle du décor que personne ne pose, pas une espèce : la scène le déduit là où un chemin ou une route croise un cours d'eau (production/map, MapSceneDecor)",
		'panneau' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'compost' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'caisses' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'tracteur' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'utilitaire' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'pick-up' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'remorque' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'camion' => "un véhicule du bourg, pas une espèce : la scène le demande par son slug (accounting/town, TownVehicles)",
		'garage' => "un bâtiment du bourg, pas une espèce : la scène le demande par son slug (accounting/town)",
		'quad' => "un véhicule du bourg, pas une espèce : la scène le demande par son slug (accounting/town)",
		'palette' => "la charge du camion du bourg, pas une espèce : la scène la demande par son slug (accounting/town, TownVehicles)",
		'arbre' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'arbre.sapin' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'arbre.peuplier' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'buisson' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'fruitier' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'fruitier.rouge' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'fruitier.or' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'haie' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'haie.baies' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'haie.bocage' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'haie.charmille' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'haie.charmille.bout' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'cloture.mouton' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'cloture.mouton.bout' => "le piquet qui ferme une clôture du décor posé, pas une espèce : la scène le pose au bout de la ligne (production/map, MapSceneDecor)",
		'cloture.ganivelle' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'cloture.ganivelle.bout' => "le piquet qui ferme une clôture du décor posé, pas une espèce : la scène le pose au bout de la ligne (production/map, MapSceneDecor)",
		'cloture.rigide' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'cloture.rigide.bout' => "le piquet qui ferme une clôture du décor posé, pas une espèce : la scène le pose au bout de la ligne (production/map, MapSceneDecor)",
		'cloture.electrique' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'cloture.electrique.bout' => "le piquet qui ferme une clôture du décor posé, pas une espèce : la scène le pose au bout de la ligne (production/map, MapSceneDecor)",
		'portail' => "un élément du décor posé, pas une espèce : la scène le pose seul, ou dans le passage que laisse le tour d'un enclos (production/map, MapSceneDecor)",
		'chene' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'saule' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'bouleau' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'arbre-mort' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'souche' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'roncier' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'fougeres' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'roseaux' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'rocher' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'pierrier' => "un modèle du décor posé, pas une espèce : la scène le demande par son slug (production/map, MapSceneDecor)",
		'bande-fleurie' => "une travée de tracé du décor posé, pas une espèce : la scène la répète le long de la ligne (production/map, MapSceneDecor)",
		'electrovanne' => "un élément d'irrigation du décor posé, pas une espèce : la scène le cale sur une conduite comme la vanne (production/map, MapSceneDecor)",
	];

	private function getDirectory(): string {
		return LIME_DIRECTORY.'/base/scene';
	}

	/**
	 * Un fichier absent ou illisible rend un tableau vide, que le plancher de testEveryCitationIsBaked nomme.
	 */
	private function read(string $file): array {

		$path = $this->getDirectory().'/'.$file;
		$content = is_file($path) ? json_decode(file_get_contents($path), TRUE) : NULL;

		return is_array($content) ? $content : [];

	}

	public function testEveryCitationIsBaked(): void {

		$species = $this->read('model/species.json');
		$alias = $this->read('model/alias.json');
		$models = $this->read('static/model/models.json')['models'];

		$this->assertTrue(count($species) > 100 and count($alias) > 100 and count($models) > 100, "la sentinelle ne trouve plus le catalogue, ses alias ou les modèles cuits de base/scene : elle ne sait plus lire ce qu'elle compare");

		$cited = [];
		$slugs = [];

		foreach($species as $entry) {

			$slugs[] = $entry['slug'];
			$cited[$entry['module'] ?? $entry['slug']][] = $entry['slug'];

			foreach(['fruitModule', 'pickedModule'] as $key) {
				if(isset($entry[$key])) {
					$cited[$entry[$key]][] = $entry['slug'].' ('.$key.')';
				}
			}

		}

		$missing = [];
		foreach(array_diff_key($cited, $models) as $model => $by) {
			$missing[] = $model.' cité par '.implode(', ', $by);
		}

		// Un alias cite une entrée, qui cite son modèle : chou-pe-tsai n'a pas de modèle à son nom
		$orphan = [];
		foreach($alias as $name => $target) {
			if($target[0] !== NULL and in_array($target[0], $slugs, TRUE) === FALSE) {
				$orphan[] = $name.' → '.$target[0];
			}
		}

		$this->assertEquals([], $missing, "modèles cités par model/species.json qui ne sont pas cuits (static/model/models.json fait foi, la cuisson seule l'écrit : corriger la citation, ou écrire le module dans model/ et le cuire) : ".implode(', ', $missing));
		$this->assertEquals([], $orphan, "alias de model/alias.json qui visent une entrée absente de model/species.json (le catalogue fait foi : corriger la cible, ou null quand rien ne montre la culture) : ".implode(', ', $orphan));

		$unused = array_diff(array_keys($models), array_keys($cited));
		$new = array_diff($unused, array_keys(self::UNUSED));
		$stale = array_diff(array_keys(self::UNUSED), $unused);

		$this->assertEquals([], $new, "modèles cuits qu'aucune entrée de model/species.json ne cite (le citer depuis une entrée, ou le déclarer dans UNUSED avec sa raison) : ".implode(', ', $new));
		$this->assertEquals([], $stale, "modèles déclarés dans UNUSED qui sont désormais cités ou ne sont plus cuits, retirer la déclaration : ".implode(', ', $stale));

	}

	public function testEntriesUseKnownKeys(): void {

		$unknown = [];
		$incomplete = [];

		foreach($this->read('model/species.json') as $entry) {

			$absent = array_diff(self::REQUIRED, array_keys($entry));
			if($absent !== []) {
				$incomplete[] = ($entry['slug'] ?? $entry['plant'] ?? '?').' ('.implode(', ', $absent).')';
				continue;
			}

			foreach(array_diff(array_keys($entry), array_keys(self::KEYS)) as $key) {
				$unknown[] = $entry['slug'].' ('.$key.')';
			}
		}

		foreach($this->read('model/alias.json') as $name => $target) {

			if(count($target) < 2 or count($target) > 3) {
				$unknown[] = $name.' (['.count($target).' valeurs], attendu [cible, nature] ou [cible, nature, couleurs])';
				continue;
			}

			foreach(array_diff(array_keys((count($target) === 3) ? $target[2] : []), self::TINTS) as $key) {
				$unknown[] = $name.' ('.$key.')';
			}

		}

		$this->assertEquals([], $incomplete, "entrées de model/species.json auxquelles manque une clé que toute entrée porte (compléter l'entrée : sans elle, species.get() ne la trouve pas ou crop.js repeint une espèce dérivée en blanc) : ".implode(', ', $incomplete));
		$this->assertEquals([], $unknown, "clés du catalogue ou des alias que species.js et crop.js ne lisent pas, et ignoreraient sans rien dire (corriger la clé dans model/species.json ou model/alias.json, ou la déclarer dans KEYS pour une entrée, dans TINTS pour une couleur d'alias, une fois le code qui la lit écrit) : ".implode(', ', $unknown));

	}

}
