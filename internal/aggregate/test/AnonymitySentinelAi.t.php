<?php
namespace aggregate;

/**
 * Test-sentinelle : ce que les tables agrégées savent des structures, et qui les lit.
 *
 * `aggregate` met à plat des données de toutes les structures pour qu'une page les montre à
 * n'importe quelle autre. Deux régimes y cohabitent :
 *
 * - les cultures ne sont pas anonymisées en table : `structure` et `seriesCultivation` désignent une
 *   structure et l'une de ses cultures, et ce sont les seuils appliqués à la lecture
 *   (`CultivationAiSetting`) qui empêchent d'en isoler une ;
 * - les compositions ne portent aucun identifiant (`ANONYMOUS`) : aucune colonne ne mène à une
 *   structure, une vente ni un produit. Leurs colonnes réunies en désignent pourtant presque toujours
 *   une (semaine, valeur et nombre de lignes suffisent pour 2 419 compositions sur 2 522 en dev) :
 *   le seuil appliqué à la lecture reste leur protection aussi, et un identifiant ajouté la rendrait
 *   inutile.
 *
 * Trois choses peuvent donc rompre l'anonymat sans erreur et sans rien à l'écran le jour où elles
 * arrivent : une colonne qui en dit plus sur une structure, un identifiant ajouté à une table
 * anonymisée, et un nouveau lecteur qui n'applique pas les seuils.
 *
 * Le test déclare les colonnes et les lecteurs, avec leur raison, et compare dans les deux sens :
 *
 * 1. chaque colonne de chaque module du package, rangée dans une nature (`IDENTIFIER`, `REFERENCE`,
 *    `FACT`) ; une colonne qui mène à une structure est un identifiant, une table anonymisée n'en
 *    porte aucun, et une colonne texte n'est admise que si elle recopie le référentiel arbitré,
 *    jamais une saisie libre d'une structure ;
 * 2. chaque fichier hors du package qui nomme une classe de `aggregate`.
 *
 * Lancement : dev/test package=aggregate file=AnonymitySentinelAi
 */
class AnonymitySentinelAiTest extends \Test {

	/** Désigne une structure ou l'une de ses lignes : ne sort jamais d'un calcul qui applique les seuils. */
	const IDENTIFIER = 'identifier';

	/** Recopié du référentiel partagé, identique pour toutes les structures qui l'emploient. */
	const REFERENCE = 'reference';

	/** Une valeur de conduite ou de calendrier, sans identité propre. */
	const FACT = 'fact';

	/**
	 * Les modules anonymisés en table : aucune colonne `IDENTIFIER`.
	 */
	const ANONYMOUS = [
		'aggregate\Calendar',
		'aggregate\Composition',
		'aggregate\CompositionItem',
		'aggregate\Price',
		'aggregate\Sale',
		'aggregate\SaleTotal',
	];

	/**
	 * Les colonnes de chaque module, leur nature et leur raison.
	 */
	const COLUMNS = [
		'aggregate\Calendar' => [
			'id' => [self::FACT, 'clé de la ligne, refaite à chaque calcul'],
			'climate' => [self::FACT, 'l\'un des quatre climats du calendrier, où sont rangées des centaines de structures'],
			'plant' => [self::REFERENCE, 'espèce de référence (`plant.reference`), jamais l\'espèce propre d\'une structure'],
			'harvestWeeks' => [self::FACT, 'semaines de récolte habituelles de l\'espèce, retenues au-dessus d\'une part des structures qui la cultivent : aucune ne vient d\'une seule structure'],
			'weeks' => [self::FACT, 'les mêmes semaines, prolongées par la conservation du référentiel (`plant.storage`)'],
			'structures' => [self::FACT, 'nombre de structures derrière la ligne, celui de tous les climats en repli : un compte, lu par aucun écran'],
			'fallback' => [self::FACT, 'dit que la saison est celle de tous les climats, le climat seul comptant trop peu de structures'],
			'online' => [self::FACT, 'ligne au seuil de contributeurs, la seule que les lecteurs servent'],
		],
		'aggregate\Composition' => [
			'id' => [self::FACT, 'clé de la ligne, refaite chaque nuit dans un ordre mélangé, reliée à aucune vente'],
			'contributor' => [self::FACT, 'rang de la structure, tiré au mélange à chaque passage : compte les structures d\'une tranche pour appliquer le seuil ; le numéro change à chaque passage'],
			'composedProduct' => [self::FACT, 'rang du produit composé, tiré au mélange à chaque passage : reconnaît deux tailles de panier sans désigner un produit'],
			'year' => [self::FACT, 'année ISO de livraison'],
			'week' => [self::FACT, 'semaine ISO de livraison, la 53 rangée en 52'],
			'altitude' => [self::FACT, 'altitude brute de la structure, filtrée par paliers à la lecture'],
			'value' => [self::FACT, 'montant TTC constaté sur la composition, découpé en tranches à la lecture'],
			'lines' => [self::FACT, 'nombre de lignes de la composition, y compris celles sans espèce de référence'],
			'online' => [self::FACT, 'version servie ou en préparation'],
		],
		'aggregate\CompositionItem' => [
			'id' => [self::FACT, 'clé de la ligne, refaite chaque nuit'],
			'composition' => [self::FACT, 'composition anonymisée de rattachement, qui ne porte aucun identifiant'],
			'plant' => [self::REFERENCE, 'espèce de référence (`plant.reference`), jamais l\'espèce propre d\'une structure'],
			'quantity' => [self::FACT, 'quantité par panier dans l\'unité de la ligne, colisage compris'],
			'unit' => [self::REFERENCE, 'unité du référentiel (structure IS NULL) de même fqn, jamais le clone de la structure'],
			'online' => [self::FACT, 'version servie ou en préparation'],
		],
		'aggregate\Cultivation' => [
			'id' => [self::FACT, 'clé de la ligne, refaite à chaque nuit et reliée à aucune donnée vive'],
			'plant' => [self::REFERENCE, 'espèce de référence (structure IS NULL), jamais l\'espèce propre d\'une structure'],
			'seriesCultivation' => [self::IDENTIFIER, 'départage les égalités dans CultivationCropPlanAiLib, jamais rendu à la page'],
			'structure' => [self::IDENTIFIER, 'compte les structures d\'un créneau pour appliquer les seuils, jamais rendu à la page'],
			'season' => [self::FACT, 'saison de la culture'],
			'startAction' => [self::FACT, 'semis direct ou plantation'],
			'seedling' => [self::FACT, 'itinéraire de pépinière'],
			'mode' => [self::FACT, 'mode de la série'],
			'use' => [self::FACT, 'planche ou bloc, porté par la série'],
			'bedWidth' => [self::FACT, 'largeur travaillée de la planche, en centimètres : c\'est elle qui groupe les conduites, et un groupe ne s\'affiche qu\'au-dessus du seuil de structures'],
			'alleyWidth' => [self::FACT, 'largeur du passe-pied, en centimètres, recopiée brute de la série. Aucune lecture ne la sert au 2026-09-21 : le passe-pied dessiné se déduit de la densité, et la colonne est gardée pour le lot I4 (arbitré), qui devra lire cette table et jamais series'],
			'rows' => [self::FACT, 'nombre de rangs sur la planche'],
			'rowSpacing' => [self::FACT, 'écartement entre les rangs, en centimètres'],
			'plantSpacing' => [self::FACT, 'écartement sur le rang, en centimètres'],
			'density' => [self::FACT, 'densité de plantation par unité de surface, déjà calculée par la culture source'],
			'startWeek' => [self::FACT, 'semaine de départ ramenée dans l\'année'],
			'month' => [self::FACT, 'mois de la semaine de départ'],
			'seedlingIndex' => [self::FACT, 'semaine de semis en pépinière, en index continu'],
			'harvestPeriod' => [self::FACT, 'récolte prévue à la semaine ou au mois'],
			'harvestFrom' => [self::FACT, 'première semaine de récolte, en index continu'],
			'harvestTo' => [self::FACT, 'dernière semaine de récolte, en index continu'],
			'harvestCount' => [self::FACT, 'nombre de semaines de récolte'],
			'harvestWeeks' => [self::FACT, 'numéros des semaines de récolte'],
			'yieldExpected' => [self::FACT, 'rendement attendu recopié brut de la culture source, protégé comme altitude par les seuils appliqués à la lecture'],
			'mainUnit' => [self::FACT, 'unité dans laquelle se compte le rendement : un objectif ne s\'agrège qu\'avec ceux de la même unité'],
			'altitude' => [self::FACT, 'altitude brute de la structure, filtrée par paliers à la lecture : un palier regroupe des centaines de structures'],
			'structureSeries' => [self::FACT, 'nombre de séries de la structure, comparé à un seuil à la lecture'],
			'online' => [self::FACT, 'version servie ou en préparation'],
			'createdAt' => [self::FACT, 'date de la projection, la même pour toutes les lignes d\'une nuit'],
		],
		'aggregate\CultivationVariety' => [
			'id' => [self::FACT, 'clé de la ligne, refaite à chaque nuit'],
			'cultivation' => [self::IDENTIFIER, 'ligne agrégée de rattachement, qui porte la structure et sa culture : rattache la variété à son créneau, jamais rendu à la page'],
			'plant' => [self::REFERENCE, 'espèce de référence'],
			'structure' => [self::IDENTIFIER, 'compte les structures qui emploient une variété pour appliquer les seuils, jamais rendu à la page'],
			'variety' => [self::REFERENCE, 'variété de référence, jamais la variété propre d\'une structure'],
			'name' => [self::REFERENCE, 'nom arbitré de la variété de référence'],
			'searchKey' => [self::REFERENCE, 'forme de recherche du nom arbitré'],
			'online' => [self::FACT, 'version servie ou en préparation'],
		],
		'aggregate\Price' => [
			'id' => [self::FACT, 'clé de la ligne, refaite à chaque passage et reliée à aucune vente'],
			'week' => [self::FACT, 'semaine ISO du relevé, la 53 rangée en 52 comme pour une composition, et sans année : le prix est saisonnier et non millésimé'],
			'plant' => [self::REFERENCE, 'espèce de référence (`plant.reference`), jamais l\'espèce propre d\'une structure'],
			'unit' => [self::REFERENCE, 'unité du référentiel (structure IS NULL) de même fqn, jamais le clone de la structure'],
			'contributors' => [self::FACT, 'nombre de structures distinctes derrière la ligne : porte le seuil, et c\'est lui que online traduit'],
			'median' => [self::FACT, 'prix médian au détail des structures de la ligne, qui désignerait la seule structure d\'une ligne sous le seuil : c\'est le seuil qui l\'en empêche, comme pour la quantité d\'une ligne de panier'],
			'low' => [self::FACT, '10e centile du même relevé, sous le même seuil'],
			'high' => [self::FACT, '90e centile du même relevé, sous le même seuil'],
			'quantityMedian' => [self::FACT, 'quantité médiane vendue dans l\'unité de la ligne, sous le même seuil'],
			'online' => [self::FACT, 'le seuil de contributeurs, et non l\'état d\'une version comme sur les autres tables : une ligne sous le seuil est écrite et ne passera jamais en ligne'],
		],
		'aggregate\Sale' => [
			'id' => [self::FACT, 'clé de la case, refaite à chaque passage et reliée à aucune vente'],
			'plant' => [self::REFERENCE, 'espèce de référence (`plant.reference`), jamais l\'espèce propre d\'une structure'],
			'month' => [self::FACT, 'mois civil de livraison, sans année : une saison de vente et non un millésime'],
			'contributors' => [self::FACT, 'nombre de structures distinctes derrière la case, jamais sous le seuil : une case qui ne le passe pas n\'est pas écrite'],
			'amount' => [self::FACT, 'chiffre des structures de la case, qui en réunit au moins autant que le seuil'],
		],
		'aggregate\SaleTotal' => [
			'id' => [self::FACT, 'clé du total, refaite à chaque passage et reliée à aucune vente'],
			'plant' => [self::REFERENCE, 'espèce de référence du total d\'une espèce, vide pour le total d\'un mois ou de l\'année'],
			'month' => [self::FACT, 'mois civil du total d\'un mois, vide pour le total d\'une espèce ou de l\'année'],
			'contributors' => [self::FACT, 'nombre de structures distinctes derrière le total : un total n\'est écrit que si son reste, ce qu\'il ajoute aux cases servies, passe lui-même le seuil, donc jamais sous le seuil ni déductible par différence'],
			'amount' => [self::FACT, 'somme de toutes les cases du mois, de l\'espèce ou de l\'année, y compris celles sous le seuil que Sale n\'écrit pas'],
		],
		'aggregate\VegetableHours' => [
			'id' => [self::FACT, 'clé de la ligne, refaite à chaque calcul'],
			'structure' => [self::IDENTIFIER, 'compte les structures de chaque nature de travail pour appliquer le plancher du groupe, jamais rendu à la page'],
			'year' => [self::FACT, 'année civile des feuilles de temps'],
			'fqn' => [self::REFERENCE, 'code de l\'une des catégories que le produit donne à chaque structure, NULL pour une catégorie qu\'elle s\'est créée : jamais son nom'],
			'hours' => [self::FACT, 'somme des heures de la structure dans la nature, rendue à la page seulement sommée sur le groupe, au-dessus du plancher'],
			'online' => [self::FACT, 'version servie ou en préparation'],
		],
		'aggregate\VegetablePlan' => [
			'id' => [self::FACT, 'clé de la ligne, refaite à chaque calcul'],
			'structure' => [self::IDENTIFIER, 'compte les structures du groupe au seuil de séries et au plancher, jamais rendu à la page'],
			'season' => [self::FACT, 'saison des séries'],
			'series' => [self::FACT, 'nombre de séries de la structure, comparé au seuil à la lecture'],
			'cultivations' => [self::FACT, 'nombre de cultures, rendu sommé sur le groupe'],
			'harvested' => [self::FACT, 'nombre de cultures récoltées, rendu sommé sur le groupe'],
			'species' => [self::FACT, 'nombre d\'espèces de référence, rendu en tranches au-dessus du plancher'],
			'area' => [self::FACT, 'surface développée, rendue en tranches au-dessus du plancher'],
			'greenhouse' => [self::FACT, 'surface développée sous abri, rendue en part par tranches au-dessus du plancher'],
			'online' => [self::FACT, 'version servie ou en préparation'],
			'createdAt' => [self::FACT, 'heure du passage qui a écrit la ligne, lue en minimum par saison pour dater l\'écran'],
		],
		'aggregate\VegetableSpecies' => [
			'id' => [self::FACT, 'clé de la ligne, refaite à chaque calcul'],
			'structure' => [self::IDENTIFIER, 'compte les structures qui cultivent une espèce pour appliquer le plancher, jamais rendu à la page'],
			'season' => [self::FACT, 'saison des cultures'],
			'plant' => [self::REFERENCE, 'espèce de référence'],
			'cultivations' => [self::FACT, 'nombre de cultures de l\'espèce, rendu sommé sur le groupe'],
			'months' => [self::FACT, 'mois de récolte prévus dans la saison, comptés par structure au-dessus du plancher'],
			'online' => [self::FACT, 'version servie ou en préparation'],
		],
	];

	/**
	 * Les fichiers hors du package qui nomment une classe de `aggregate`, et pourquoi ils le
	 * peuvent. Un lecteur ajouté ici doit passer par CultivationCropPlanAiLib, qui applique les
	 * seuils, ou dire pourquoi il n'en a pas besoin.
	 */
	const READERS = [
		'commercialisation/selling/lib/CompositionProjectionAi.l.php' => 'projette la nuit les paniers des structures consentantes dans Composition et CompositionItem, sans identifiant de structure, de vente ni de produit, n\'affiche rien : le seuil s\'applique à la lecture, par CompositionSourceAiLib',
		'commercialisation/selling/lib/PriceProjectionAi.l.php' => 'projette la nuit les prix au détail des structures consentantes dans Price, sans identifiant de structure, n\'affiche rien : le seuil de structures s\'applique à la lecture',
		'commercialisation/selling/lib/SaleProjectionAi.l.php' => 'projette la nuit les saisons de vente des structures consentantes dans Sale et SaleTotal, sans identifiant de structure, n\'affiche rien : le seuil s\'applique à la lecture',
		'commercialisation/selling/test/CompositionProjectionAi.t.php' => 'rejoue la projection des compositions dans sa transaction et la compare aux ventes, n\'affiche rien',
		'commercialisation/selling/test/PriceProjectionAi.t.php' => 'rejoue la projection des prix dans sa transaction et la compare aux ventes, n\'affiche rien',
		'commercialisation/selling/test/SaleProjectionAi.t.php' => 'rejoue la projection des saisons de vente dans sa transaction et la compare aux ventes, n\'affiche rien',
		'ouvretaferme/presentation/ui/DataAi.u.php' => 'la page /presentation/data : n\'interroge rien, elle écrit les seuils de CultivationAiSetting en lisant les constantes, pour que ce qu\'elle promet suive ce que le code applique',
		'production/plant/page/communityAi.p.php' => 'la bibliothèque de cultures : le plan par CultivationCropPlanAiLib, qui applique les seuils ; la conduite majoritaire de l\'espèce, un compte sur toutes les structures ; et la recherche de variétés, qui ne rend que des noms du référentiel groupés par variété, sans structure',
		'production/plant/ui/CommunityAi.u.php' => 'n\'interroge rien : rend ce que la page lui passe, déjà passé par les seuils de CultivationCropPlanAiLib et de CultivationConductAiLib. Elle ne nomme le package que pour trois choses — les libellés du mode de culture, leur ordre (CultivationConductAiLib::MODES, une constante), et les seuils eux-mêmes, qu\'elle écrit sous le bloc des conduites au lieu de les cacher (arbitré le 2026-09-20 : « le seuil d\'anonymat s\'affiche au lieu d\'être caché »)',
		'production/plant/page/cron/communityAi.p.php' => 'lance les projections nocturnes des cultures, des compositions, des prix, des saisons de vente et de l\'année en cours du tableau de bord maraîchage (et de l\'année écoulée le 1er janvier), n\'affiche rien',
		'production/series/lib/VegetableDashboardAi.l.php' => 'le tableau de bord maraîchage d\'un groupe : applique à la lecture le seuil de séries et le plancher (`VegetableDashboardAiLib::FLOOR`) à toute valeur rendue',
		'production/series/test/VegetableDashboardAi.t.php' => 'refait dans sa transaction les lignes des structures du groupe qu\'il éprouve, n\'affiche rien',
		'production/series/test/VegetableDashboardFixtureAi.t.php' => 'sème le décor du tableau de bord et projette ses seules structures, n\'affiche rien',
		'production/toolbox/lib/Composition.l.php' => 'la règle de composition des paniers : ne lit l\'agrégé que par CompositionSourceAiLib, qui applique le seuil de structures ou de contributeurs à la disponibilité, aux quantités et à la part à la semaine ; la part à l\'année est un rapport sur tous les paniers en ligne, modulée par le poids d\'un mois dans les ventes d\'une espèce, lu sur les seules cases au seuil et sur des totaux qui ne s\'écrivent que derrière une case au seuil ; rien de ce qui est lu ne se rattache à une structure. Le calendrier type se lit par CalendarAiLib::getByClimate() et, pour la liste des espèces qu\'il date, par CalendarAiLib::getPlantIds(), qui ne servent que les lignes au seuil, et CalendarAiLib::getClimate() ne lit que la structure qui ouvre son propre départ ; CalendarProjectionAiLib::PIECE_MIN, la seule constante qu\'il lit de la projection, est le nombre de semaines sous lequel un morceau de saison se retire (r72)',
		'production/toolbox/page/compositionFormula.p.php' => 'le panneau « Ajouter une formule » : écrit sous le champ prix l\'intervalle du premier au troisième quartile de tous les paniers partagés en ligne (`CompositionSourceAiLib::getBasketPriceQuartiles()`), une position dans la distribution nationale qui ne se rattache à aucune structure, sans rien proposer dans le champ ; c\'est le seul écran qui les dise',
		'production/toolbox/test/CompositionFrequency.t.php' => 'module un relevé construit à la main par CompositionSourceAiLib::applySeasonality(), appelée par réflexion, sans lire aucune table, n\'affiche rien',
		'production/toolbox/test/CompositionRule.t.php' => 'éprouve la règle et « Recommencer » dans sa transaction, n\'affiche rien',
		'production/toolbox/test/CompositionSeasonShort.t.php' => 'n\'interroge rien : compare le seuil des morceaux courts à la constante CalendarProjectionAiLib::PIECE_MIN, n\'affiche rien',
	];

	private function getModules(): array {

		$modules = [];

		foreach(glob(\Package::getPath('aggregate').'/module/*.m.php') as $file) {
			$modules[] = 'aggregate\\'.basename($file, '.m.php');
		}

		sort($modules);

		return $modules;

	}

	public function testColumnsAreDeclared(): void {

		$modules = $this->getModules();

		$this->assertEquals(array_keys(self::COLUMNS), $modules, 'modules du package face à la déclaration');

		foreach($modules as $module) {

			$properties = $module::model()->getProperties();
			$declared = array_keys(self::COLUMNS[$module] ?? []);

			$undeclared = array_diff($properties, $declared);
			$stale = array_diff($declared, $properties);

			$this->assertEquals([], array_values($undeclared), $module.' : colonne non déclarée, dire ce qu\'elle révèle d\'une structure : '.implode(', ', $undeclared));
			$this->assertEquals([], array_values($stale), $module.' : déclaration périmée, la colonne n\'existe plus : '.implode(', ', $stale));

		}

	}

	/**
	 * Une colonne qui mène à une structure est un identifiant, qu'elle la désigne directement ou
	 * par une ligne agrégée qui en porte un : la déclarer en `FACT` la ferait passer pour une
	 * valeur sans identité. Une colonne entière (`seriesCultivation`) ne se relie à rien que le
	 * modèle sache nommer, sa nature reste celle de la déclaration.
	 */
	public function testLinksToStructureAreIdentifiers(): void {

		$found = [];

		foreach($this->getModules() as $module) {

			$mModule = $module::model();

			foreach($mModule->getProperties() as $property) {

				if(str_starts_with($mModule->getPropertyType($property), 'element') === FALSE) {
					continue;
				}

				$target = $mModule->getProperty($property)[1];

				if(
					$target === 'structure\Structure' or
					in_array(self::IDENTIFIER, array_column(self::COLUMNS[$target] ?? [], 0), TRUE)
				) {
					$found[$module.'.'.$property] = self::COLUMNS[$module][$property][0] ?? NULL;
				}

			}

		}

		$this->assertTrue(count($found) >= 3, 'le relevé doit trouver les deux `structure` et `CultivationVariety.cultivation`');

		$notIdentifier = array_keys(array_filter($found, fn($nature) => $nature !== self::IDENTIFIER));

		$this->assertEquals([], $notIdentifier, 'colonne qui mène à une structure sans être déclarée identifiant : '.implode(', ', $notIdentifier));

	}

	public function testAnonymousModulesHaveNoIdentifier(): void {

		$modules = $this->getModules();

		$missing = array_diff(self::ANONYMOUS, $modules);

		$this->assertEquals([], array_values($missing), 'module anonymisé déclaré qui n\'existe plus : '.implode(', ', $missing));

		$found = [];

		foreach(array_intersect(self::ANONYMOUS, $modules) as $module) {

			foreach(self::COLUMNS[$module] ?? [] as $property => [$nature]) {

				if($nature === self::IDENTIFIER) {
					$found[] = $module.'.'.$property;
				}

			}

		}

		$this->assertEquals([], $found, 'identifiant dans une table anonymisée, aucun seuil ne le protège : '.implode(', ', $found));

	}

	public function testTextColumnsComeFromReference(): void {

		$found = [];

		foreach($this->getModules() as $module) {

			$mModule = $module::model();

			foreach($mModule->getProperties() as $property) {

				$type = $mModule->getPropertyType($property);

				if(str_starts_with($type, 'text') or $type === 'editor') {
					$found[$module.'.'.$property] = self::COLUMNS[$module][$property][0] ?? NULL;
				}

			}

		}

		$this->assertTrue($found !== [], 'le relevé doit trouver les colonnes texte de CultivationVariety');

		$free = array_keys(array_filter($found, fn($nature) => $nature !== self::REFERENCE));

		$this->assertEquals([], $free, 'colonne texte qui ne recopie pas le référentiel : '.implode(', ', $free));

	}

	public function testReadersAreDeclared(): void {

		$package = substr(\Package::getPath('aggregate'), strlen(\dev\SentinelLib::getRoot()) + 1).'/';
		$found = [];

		foreach(\dev\SentinelLib::getFiles('.php') as $file => $path) {

			if(str_starts_with($file, $package)) {
				continue;
			}

			foreach(\dev\SentinelLib::getTokens(file_get_contents($path)) as [$type, $text]) {

				if(
					($type === T_NAME_FULLY_QUALIFIED or $type === T_NAME_QUALIFIED) and
					str_starts_with(ltrim($text, '\\'), 'aggregate\\')
				) {
					$found[] = $file;
					break;
				}

			}

		}

		$undeclared = array_diff($found, array_keys(self::READERS));
		$stale = array_diff(array_keys(self::READERS), $found);

		$this->assertEquals([], array_values($undeclared), 'nouveau lecteur des tables agrégées, dire comment il applique les seuils : '.implode(', ', $undeclared));
		$this->assertEquals([], array_values($stale), 'lecteur déclaré qui ne lit plus les tables agrégées : '.implode(', ', $stale));

	}

}
?>
