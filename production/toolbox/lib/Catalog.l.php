<?php
namespace toolbox;

/**
 * Les produits autorisés (planche pf-catalogue-phyto, arbitrée le 29/09, Q1 B et Q2 B) :
 * le catalogue E-Phy cherché par espèce, par cible et par produit, pour trouver ce qui convient à une
 * culture. Rien ne s'y écrit, sauf l'inscription de l'outil à sa première ouverture.
 *
 * Ne s'y lisent que les produits autorisés à ce jour, hors de la gamme réservée aux jardins
 * (`work\PhytoProduct.amateur`) : un produit retiré ou amateur n'est pas une réponse à « que puis-je
 * employer ». Un critère au plus par famille, chacun porté par l'adresse de la page (`getFilters()`).
 * Sans critère, la liste porte tout le catalogue, pour que l'écran ne s'ouvre pas vide (retour de
 * du 29/09).
 */
class CatalogLib {

	/**
	 * Comme le calculateur des densités (`DensityLib::open()`), la recherche n'enregistre rien : c'est
	 * sa première ouverture qui en fait un outil de la structure, et lui donne sa roue et son étoile.
	 */
	public static function open(\structure\Structure $eStructure): void {

		if(in_array(Favorite::CATALOG, $eStructure->conf(\structure\Feature::class)['hasToolbox'])) {
			return;
		}

		\structure\FeatureLib::addToolbox($eStructure, Favorite::CATALOG);

		\structure\FeatureLib::forget($eStructure);

	}

	/**
	 * Les critères lus dans la requête. Un critère qui ne désigne rien (une espèce inconnue, une cible
	 * que le catalogue ne porte plus, un produit retiré depuis que le lien a été copié) est ignoré,
	 * comme si le jeton n'avait pas été posé. « Utilisables en bio » est posé d'office pour une
	 * structure en bio ou en conversion, et se retire par `organic=0`.
	 */
	public static function getFilters(\structure\Structure $eStructure, array $input): array {

		$fqn = $input['plant'] ?? NULL;

		$ePlant = is_string($fqn) ?
			\plant\Plant::model()
				->select('id', 'fqn', 'name', 'reference', 'structure')
				->whereStructure(NULL)
				->whereFqn($fqn)
				->whereStatus(\plant\Plant::ACTIVE)
				->get() :
			new \plant\Plant();

		$amm = $input['product'] ?? NULL;

		$ePhytoProduct = is_string($amm) ?
			\work\PhytoProduct::model()
				->select(\work\PhytoProduct::getSelection())
				->whereAmm($amm)
				->whereStatus(\work\PhytoProduct::AUTHORIZED)
				->whereAmateur(FALSE)
				->get() :
			new \work\PhytoProduct();

		$target = $input['target'] ?? NULL;

		if(is_string($target) === FALSE or self::getTargetsByLabel($target) === []) {
			$target = NULL;
		}

		$function = $input['function'] ?? NULL;

		if(in_array($function, self::getFunctions(), TRUE) === FALSE) {
			$function = NULL;
		}

		$organic = $input['organic'] ?? NULL;

		return [
			'plant' => $ePlant,
			'target' => $target,
			'function' => $function,
			'product' => $ePhytoProduct,
			'organic' => ($organic === NULL) ? \work\ToolLib::isOrganicOnly($eStructure) : ($organic === '1'),
		];

	}

	/**
	 * Les produits que laissent les critères, rangés par substance (arbitrage Q2 B) : un groupe par nom
	 * international, du plus fourni au moins fourni, les produits par nom dans chacun. Un produit à
	 * deux substances paraît dans les deux groupes, et ne compte qu'une fois dans `count`.
	 *
	 * Chaque produit porte ses cultures E-Phy (piste C de pf-catalogue-usage, « Vos cultures d'abord »,
	 * validée le 29/09) : dans `crops`, une par culture qui couvre au moins une espèce
	 * active de la structure, ou l'espèce cherchée, avec son espèce de référence, ses cibles, ses
	 * doses par unité et son délai avant récolte, chacun en plus bas et plus haut ; dans `cropsOther`,
	 * les noms des autres, avec leurs doses et leurs délais réunis. Quand une cible est posée, les
	 * cibles d'une culture sont les autres que la sienne. Et l'id du produit de la structure qui porte
	 * son AMM (`tool`, NULL sinon).
	 *
	 * La page compte des produits et non des lignes (`ToolboxSetting::CATALOG_PER_PAGE`) : les produits
	 * sont numérotés dans l'ordre où ils paraissent pour la première fois, et une page porte les
	 * groupes de ses produits. Un groupe coupé par la page reprend son intertitre en tête de la
	 * suivante, avec son compte entier (`count` de chaque groupe).
	 */
	public static function getProducts(\structure\Structure $eStructure, array $filters, int $page = 0): array {

		// La cible posée ne borne pas la lecture : les autres cibles d'une culture retenue se lisent à
		// côté d'elle (« aussi contre bactériose »). Seuls les usages qu'elle retient font entrer un
		// produit et une culture dans la liste, et donnent leur dose et leur délai.
		$cPhytoUsage = self::applyUsages(['target' => NULL] + $filters)
			->select('product', 'crop', 'target', 'dose', 'doseUnit', 'harvestDelay', 'plants')
			->getCollection();

		$targets = ($filters['target'] === NULL) ? NULL : self::getTargetsByLabel($filters['target']);
		$grown = self::getGrown($eStructure);

		$usages = [];

		foreach($cPhytoUsage as $ePhytoUsage) {

			$product = $ePhytoUsage['product']['id'];
			$crop = \work\PhytoUsageLib::getLabel($ePhytoUsage['crop']);

			$usages[$product][$crop] ??= [
				'plant' => \work\PhytoUsageLib::getCropPlant($ePhytoUsage['crop']),
				// Tous les usages retenus couvrent l'espèce cherchée : elle vaut culture de la structure.
				'mine' => $filters['plant']->notEmpty(),
				'retained' => FALSE,
				'targets' => [],
				'doses' => [],
				'harvestDelays' => [],
			];

			$entry = &$usages[$product][$crop];

			if(array_intersect($ePhytoUsage['plants'], $grown) !== []) {
				$entry['mine'] = TRUE;
			}

			$retained = ($targets === NULL or in_array($ePhytoUsage['target'], $targets, TRUE));

			// Sans cible posée, la culture porte toutes ses cibles ; avec, seulement les autres.
			if($ePhytoUsage['target'] !== NULL and ($targets === NULL or $retained === FALSE)) {
				$entry['targets'][\work\PhytoUsageLib::getLabel($ePhytoUsage['target'])] = TRUE;
			}

			if($retained) {

				$entry['retained'] = TRUE;

				if($ePhytoUsage['dose'] !== NULL and $ePhytoUsage['doseUnit'] !== NULL) {
					$entry['doses'][$ePhytoUsage['doseUnit']][] = $ePhytoUsage['dose'];
				}

				if($ePhytoUsage['harvestDelay'] !== NULL) {
					$entry['harvestDelays'][] = $ePhytoUsage['harvestDelay'];
				}

			}

			unset($entry);

		}

		$usages = array_filter(array_map(fn($crops) => array_filter($crops, fn($crop) => $crop['retained']), $usages));

		if($usages === []) {
			return [
				'count' => 0,
				'pages' => 0,
				'groups' => [],
			];
		}

		$cPhytoProduct = \work\PhytoProduct::model()
			->select('id', 'amm', 'name', 'organic', 'substances')
			->whereId('IN', array_keys($usages))
			->sort(['name' => SORT_ASC])
			->getCollection();

		$tools = \work\ToolLib::getPhytoIdsByAmm($eStructure);

		$groups = [];

		foreach($cPhytoProduct as $ePhytoProduct) {

			$crops = $usages[$ePhytoProduct['id']];
			ksort($crops);

			$ePhytoProduct['crops'] = [];
			$others = [];

			foreach($crops as $crop => $entry) {
				if($entry['mine']) {
					$ePhytoProduct['crops'][] = [
						'name' => $crop,
						'plant' => $entry['plant'],
						'targets' => array_keys($entry['targets']),
						'doses' => self::getRanges($entry['doses']),
						'harvestDelays' => self::getRange($entry['harvestDelays']),
					];
				} else {
					$others[$crop] = $entry;
				}
			}

			$ePhytoProduct['cropsOther'] = [
				'names' => array_keys($others),
				'doses' => self::getRanges(array_merge_recursive(...array_column($others, 'doses'))),
				'harvestDelays' => self::getRange(array_merge(...array_column($others, 'harvestDelays'))),
			];

			$ePhytoProduct['tool'] = $tools[$ePhytoProduct['amm']] ?? NULL;

			// Un produit que le catalogue publie sans substance se range à part, en fin de liste.
			$substances = $ePhytoProduct['substances'] ?: [['international' => '', 'name' => '']];

			foreach($substances as $substance) {
				$groups[$substance['international']] ??= [
					'name' => $substance['name'],
					'cPhytoProduct' => new \Collection(),
				];
				$groups[$substance['international']]['cPhytoProduct'][] = $ePhytoProduct;
			}

		}

		uasort($groups, fn($a, $b) => (($a['name'] === '') <=> ($b['name'] === '')) ?: ($b['cPhytoProduct']->count() <=> $a['cPhytoProduct']->count()) ?: strcmp($a['name'], $b['name']));

		$ordered = [];

		foreach($groups as $group) {
			foreach($group['cPhytoProduct'] as $ePhytoProduct) {
				$ordered[$ePhytoProduct['id']] = TRUE;
			}
		}

		$ids = array_flip(array_slice(array_keys($ordered), $page * ToolboxSetting::CATALOG_PER_PAGE, ToolboxSetting::CATALOG_PER_PAGE));

		$groupsPage = [];

		foreach($groups as $group) {

			$cPhytoProductPage = $group['cPhytoProduct']->find(fn($ePhytoProduct) => array_key_exists($ePhytoProduct['id'], $ids));

			if($cPhytoProductPage->notEmpty()) {
				$groupsPage[] = [
					'name' => $group['name'],
					'count' => $group['cPhytoProduct']->count(),
					'cPhytoProduct' => $cPhytoProductPage,
				];
			}

		}

		return [
			'count' => $cPhytoProduct->count(),
			'pages' => (int)ceil($cPhytoProduct->count() / ToolboxSetting::CATALOG_PER_PAGE),
			'groups' => $groupsPage,
		];

	}

	/**
	 * Les espèces actives de la structure, par leur id au référentiel partagé, celui que portent les
	 * usages (`work\PhytoUsage.plants`).
	 */
	protected static function getGrown(\structure\Structure $eStructure): array {

		$grown = [];

		foreach(\plant\PlantLib::getByStructure($eStructure, properties: ['id', 'reference', 'structure']) as $ePlant) {
			$grown[] = $ePlant['reference']->notEmpty() ? $ePlant['reference']['id'] : $ePlant['id'];
		}

		return $grown;

	}

	// La plus basse et la plus haute de chaque unité.
	protected static function getRanges(array $valuesByUnit): array {
		return array_map(fn($values) => self::getRange($values), $valuesByUnit);
	}

	// La plus basse et la plus haute, ou rien quand aucun usage n'en donne.
	protected static function getRange(array $values): array {
		return ($values === []) ? [] : [min($values), max($values)];
	}

	/**
	 * Ce que propose la recherche, en quatre familles (arbitrage Q1 B, et les fonctions ajoutées sur le
	 * retour du 29/09, « permet de chercher par fongicide ») : les espèces du référentiel
	 * partagé, celles de la structure d'abord ; les cibles que portent les usages ; les fonctions dont
	 * le nom contient la saisie ; les produits dont le nom, un second nom ou une substance contient la
	 * saisie. L'espèce posée restreint les cibles et les produits à ceux de ses usages. Le déroulant montre huit entrées par famille et annonce le
	 * reste : cinquante suffisent à ce que ce reste soit juste.
	 */
	public static function getSuggestions(\structure\Structure $eStructure, array $filters, string $query, int $limit = 50): array {

		$needle = toFqn($query);

		$cPlant = new \Collection();

		if($filters['plant']->empty()) {

			$grown = self::getGrown($eStructure);

			$cPlant = \plant\Plant::model()
				->select('id', 'fqn', 'name')
				->whereStructure(NULL)
				->whereStatus(\plant\Plant::ACTIVE)
				->getCollection()
				->find(fn($ePlant) => ($needle === '' or str_contains(toFqn($ePlant['name']), $needle)))
				->sort(fn($a, $b) => (in_array($b['id'], $grown) <=> in_array($a['id'], $grown)) ?: strcmp(toFqn($a['name']), toFqn($b['name'])));

			if($cPlant->count() > $limit) {
				$cPlant = $cPlant->slice(0, $limit);
			}

		}

		$targets = [];

		if($filters['target'] === NULL) {

			$labels = [];

			foreach(self::applyUsages(['target' => NULL, 'product' => new \work\PhytoProduct()] + $filters)->select('target')->whereTarget('!=', NULL)->group('target')->getColumn('target') as $target) {
				$labels[\work\PhytoUsageLib::getLabel($target)] = TRUE;
			}

			$targets = array_keys($labels);

			if($needle !== '') {
				$targets = array_filter($targets, fn($target) => str_contains(toFqn($target), $needle));
			}

			usort($targets, fn($a, $b) => strcmp(toFqn($a), toFqn($b)));

			$targets = array_slice($targets, 0, $limit);

		}

		$functions = [];

		if($filters['function'] === NULL) {
			$functions = array_values(array_filter(self::getFunctions(), fn($function) => ($needle === '' or str_contains(toFqn($function), $needle))));
		}

		$cPhytoProduct = new \Collection();

		// Au-dessous de deux caractères, la saisie trouverait presque tout le catalogue.
		if($filters['product']->empty() and mb_strlen(trim($query)) >= 2) {

			$query = trim($query);
			$escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($query));

			// Une substance se trouve par le début d'un mot de son nom français ou international : « cuivre »
			// trouve « hydroxyde de cuivre », « ch » ne trouve pas toutes les souches de Bacillus. La
			// désignation de la souche n'est pas le nom, et « sou » trouverait toutes les souches
			// (« Ampelomyces quisqualis souche AQ10 ») : elle est coupée avant la comparaison, qu'elle
			// s'écrive « souche », « SOUCHE: » ou « strain » dans le nom international. Les colonnes de
			// JSON_TABLE sont binaires, d'où LOWER() avant la coupe.
			$model = \work\PhytoProduct::model();

			$words = [];

			foreach(['substance.name', 'substance.international'] as $column) {
				$name = 'SUBSTRING_INDEX(SUBSTRING_INDEX(LOWER('.$column.'), " souche", 1), " strain", 1)';
				$words[] = $name.' LIKE '.$model->format($escaped.'%');
				$words[] = $name.' LIKE '.$model->format('% '.$escaped.'%');
			}

			$conditions = \work\PhytoProductLib::getNameConditions($query);
			$conditions[] = fn() => $this->where('EXISTS (SELECT 1 FROM JSON_TABLE(m1.substances, "$[*]" COLUMNS(name VARCHAR(255) PATH "$.name", international VARCHAR(255) PATH "$.international")) substance WHERE '.implode(' OR ', $words).')');

			// Les usages de l'espèce sans les conditions sur leurs produits, que la requête citante pose déjà :
			// les reprendre imbriquerait une seconde citation sous le même préfixe d'alias.
			$cPhytoProduct = self::applyProducts($filters)
				->select('id', 'amm', 'name')
				->whereId('IN', fn() => new \work\PhytoUsageModel()->select('product')->where(self::getPlantCondition($filters['plant']))->getSql(), if: $filters['plant']->notEmpty())
				->or(...$conditions)
				->sort(['name' => SORT_ASC])
				->getCollection(0, $limit);

		}

		return [
			'cPlant' => $cPlant,
			'targets' => $targets,
			'functions' => $functions,
			'cPhytoProduct' => $cPhytoProduct,
		];

	}

	/**
	 * Le produit d'une fiche ouverte depuis la liste, avec ses usages sur les espèces de la structure
	 * et sur l'espèce cherchée, que la structure ne cultive pas toujours. Une espèce que la structure
	 * cultive déjà n'est pas reprise, sans quoi la fiche la nommerait deux fois.
	 */
	public static function getProduct(\structure\Structure $eStructure, string $amm, \plant\Plant $ePlant): \work\PhytoProduct {

		$ePhytoProduct = \work\PhytoProductLib::getByAmm($amm);

		if($ePhytoProduct->empty()) {
			return $ePhytoProduct;
		}

		$cPlant = \plant\PlantLib::getByStructure($eStructure);

		$grown = fn(\plant\Plant $ePlantGrown) => ($ePlantGrown['reference']->notEmpty() ? $ePlantGrown['reference']['id'] : $ePlantGrown['id']) === $ePlant['id'];

		if($ePlant->notEmpty() and $cPlant->find($grown)->empty()) {
			$cPlant[] = $ePlant;
		}

		$ePhytoProduct['cPhytoUsage'] = \work\PhytoUsageLib::getByProductForPlants($ePhytoProduct, $cPlant);

		return $ePhytoProduct;

	}

	/**
	 * Les fonctions des produits où chercher, telles que le catalogue les écrit (« Fongicide »,
	 * « Insecticide »). Un produit en porte parfois plusieurs dans la même colonne, séparées par une
	 * virgule (« Insecticide, Acaricide ») : chacune compte à part. Une quarantaine de valeurs
	 * distinctes, d'où la lecture groupée plutôt que produit par produit.
	 */
	public static function getFunctions(): array {

		$functions = [];

		foreach(self::applyProducts(['organic' => FALSE, 'function' => NULL])->select('functions')->whereFunctions('!=', NULL)->group('functions')->getColumn('functions') as $value) {
			foreach(explode(', ', $value) as $function) {
				if($function !== '') {
					$functions[$function] = TRUE;
				}
			}
		}

		$functions = array_keys($functions);
		usort($functions, fn($a, $b) => strcmp(toFqn($a), toFqn($b)));

		return $functions;

	}

	protected static function getPlantCondition(\plant\Plant $ePlant): string {
		return (int)$ePlant['id'].' MEMBER OF(plants)';
	}

	/**
	 * Les intitulés E-Phy d'une cible telle qu'elle se lit : `getLabel()` réunit « Oïdium(s) » et
	 * « Oïdium (1) », que le catalogue sépare.
	 */
	protected static function getTargetsByLabel(string $label): array {

		$targets = \work\PhytoUsage::model()
			->select('target')
			->whereTarget('!=', NULL)
			->group('target')
			->getColumn('target');

		return array_values(array_filter($targets, fn($target) => \work\PhytoUsageLib::getLabel($target) === $label));

	}

	/**
	 * Les produits où chercher : autorisés, hors gamme amateur, utilisables en bio si le filtre est
	 * posé, et qui portent la fonction choisie parmi celles de leur colonne. Une requête neuve et non le
	 * modèle partagé, qu'elle sert à citer depuis celle des usages.
	 */
	protected static function applyProducts(array $filters): \work\PhytoProductModel {

		$mPhytoProduct = new \work\PhytoProductModel();

		return $mPhytoProduct
			->whereStatus(\work\PhytoProduct::AUTHORIZED)
			->whereAmateur(FALSE)
			->whereOrganic(TRUE, if: $filters['organic'])
			->where(fn() => 'CONCAT(", ", functions, ", ") LIKE '.$mPhytoProduct->format('%, '.$filters['function'].', %'), if: $filters['function'] !== NULL);

	}

	/**
	 * Les usages que retiennent les critères, sur les produits où chercher. Une espèce est couverte par
	 * un usage dont la culture la désigne (`work\PhytoUsage.plants`, rempli à l'import), y compris
	 * « Traitements généraux », qui les désigne toutes.
	 */
	protected static function applyUsages(array $filters): \work\PhytoUsageModel {

		return new \work\PhytoUsageModel()
			->whereProduct('IN', self::applyProducts($filters)->select('id')->getSql())
			->whereProduct($filters['product'], if: $filters['product']->notEmpty())
			->where(fn() => self::getPlantCondition($filters['plant']), if: $filters['plant']->notEmpty())
			->whereTarget('IN', fn() => self::getTargetsByLabel($filters['target']), if: $filters['target'] !== NULL);

	}

}
?>
