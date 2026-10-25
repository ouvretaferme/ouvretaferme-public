<?php
namespace work;

class PhytoUsageLib extends PhytoUsageCrud {

	/**
	 * Les espèces du référentiel partagé (`plant\Plant.fqn`) que désigne chaque culture du catalogue
	 * E-Phy, ou `all` pour toutes. La clé est la culture passée par `getLabel()` puis en minuscules,
	 * ce qui réunit les graphies d'une même culture d'une publication à l'autre (« Fines Herbes »,
	 * « Fines herbes »). Les cultures désignées par un usage (champignons, fines herbes, cultures
	 * fruitières et légumières, petits fruits) y sont les espèces qui portaient cet usage le 29/09/2026.
	 * « Traitements généraux » vaut pour toutes les espèces : E-Phy y range ce qui ne vise aucune culture
	 * en particulier, les limaces sous un anti-limaces ou le désherbage avant mise en culture.
	 * La table se lit à l'import (`getPlantsByCrop()`), jamais à l'affichage : après une modification,
	 * `work/configure/phytoPlantsAi` recalcule les usages en base.
	 */
	const CROPS = [
		'artichaut' => ['artichaut', 'cardon'],
		'asperge' => ['asperge'],
		'betterave potagère' => ['betterave'],
		'carotte' => ['carotte', 'panais'],
		'cassissier' => ['cassis'],
		'cerisier' => ['cerise'],
		'champignons' => ['champignon-de-paris', 'pleurote', 'shiitake'],
		'chicorées, production de chicons' => ['endive'],
		'chicorées, production de racines' => ['chicoree-sauvage'],
		'choux' => ['chou-blanc', 'chou-brocoli', 'chou-de-bruxelles', 'chou-de-milan', 'chou-fleur', 'chou-kale', 'chou-pak-choi', 'chou-pe-tsai', 'chou-pointu', 'chou-rave', 'chou-romanesco', 'chou-rouge'],
		'choux feuillus' => ['chou-kale', 'chou-pak-choi', 'chou-pe-tsai'],
		'choux pommés' => ['chou-blanc', 'chou-de-bruxelles', 'chou-de-milan', 'chou-pointu', 'chou-rouge'],
		'choux à inflorescence' => ['chou-brocoli', 'chou-fleur', 'chou-romanesco'],
		'choux-raves' => ['chou-rave'],
		'cresson alenois' => ['cresson'],
		'cresson de fontaine' => ['cresson'],
		'cucurbitacées à peau comestible' => ['concombre', 'cornichon', 'courgette', 'patisson'],
		'cucurbitacées à peau non comestible' => ['courge', 'melon', 'pasteque', 'kiwano'],
		'cultures fruitières' => ['abricot', 'cerise', 'coing', 'figue', 'kiwi', 'mirabelle', 'noix', 'peche', 'poire', 'pomme', 'prune', 'vigne'],
		'cultures légumières' => ['ail', 'arroche', 'artichaut', 'asperge', 'aubergine', 'betterave', 'blette', 'cardon', 'carotte', 'celeri-couper', 'celeri-branche', 'celeri-rave', 'chicoree-frisee', 'chicoree-sauvage', 'chicoree-scarole', 'chou-blanc', 'chou-brocoli', 'chou-de-bruxelles', 'chou-de-milan', 'chou-fleur', 'chou-kale', 'chou-pak-choi', 'chou-pe-tsai', 'chou-pointu', 'chou-rave', 'chou-romanesco', 'chou-rouge', 'ciboule-de-chine', 'claytone-cuba', 'concombre', 'cornichon', 'courge', 'courgette', 'cresson', 'echalote', 'endive', 'epinard', 'fenouil', 'feve', 'gingembre', 'gombo', 'haricot', 'kiwano', 'laitue', 'mache', 'mais', 'melon', 'mizuna', 'navet', 'oca-du-perou', 'oignon', 'oseille', 'panais', 'pasteque', 'patate-douce', 'patisson', 'physalis', 'piment', 'poire-de-terre', 'poireau', 'pois', 'poivron', 'pomme-de-terre', 'radis-botte', 'radis-rave', 'raifort', 'rhubarbe', 'roquette-cultivee', 'rutabaga', 'tomate', 'topinambour'],
		'céleri-branche' => ['celeri-branche', 'celeri-couper'],
		'céleris' => ['celeri-branche', 'celeri-couper', 'celeri-rave'],
		'epinard' => ['epinard', 'blette', 'arroche'],
		'figuier' => ['figue'],
		'fines herbes' => ['achillee-millefeuille', 'agastache', 'aneth', 'basilic', 'bourrache', 'celeri-couper', 'cerfeuil', 'chanvre', 'ciboule-de-chine', 'ciboulette', 'consoude', 'coriandre', 'curcuma', 'digitale', 'estragon', 'eucalyptus', 'fenouil', 'gingembre', 'hibiscus', 'hysope', 'lavande', 'liveche', 'marjolaine', 'melisse', 'menthe', 'monarde', 'nigelle', 'origan', 'oseille', 'perilla', 'persil', 'raifort', 'romarin', 'safran', 'sarriette', 'sauge', 'calendula', 'sureau', 'thym', 'verveine-officinale'],
		'fraisier' => ['fraise'],
		'framboisier' => ['framboise', 'mure'],
		'fruits à noyau' => ['abricot', 'cerise', 'mirabelle', 'peche', 'prune'],
		'fruits à pépins' => ['pomme', 'poire', 'coing'],
		'haricots' => ['haricot'],
		'haricots et pois non écossés frais' => ['haricot', 'pois'],
		'haricots et pois écossés frais' => ['haricot', 'pois', 'feve'],
		'kiwi' => ['kiwi'],
		'laitue' => ['laitue', 'chicoree-frisee', 'chicoree-scarole', 'mache', 'mesclun', 'roquette-cultivee', 'mizuna'],
		'maïs doux' => ['mais'],
		'navet' => ['navet', 'radis-botte', 'radis-rave', 'rutabaga', 'raifort'],
		'noyer' => ['noix'],
		'oignon' => ['oignon', 'ail', 'echalote'],
		'petit fruits' => ['cassis', 'fraise', 'framboise', 'groseille', 'mure', 'myrtille', 'sureau'],
		'petits fruits' => ['cassis', 'fraise', 'framboise', 'groseille', 'mure', 'myrtille', 'sureau'],
		'poireau' => ['poireau'],
		'pois' => ['pois'],
		'poivron' => ['poivron', 'piment'],
		'pomme de terre' => ['pomme-de-terre'],
		'prunier' => ['prune', 'mirabelle'],
		'pêcher, abricotier' => ['peche', 'abricot'],
		'tomate, aubergine' => ['tomate', 'aubergine'],
		'traitements généraux' => 'all',
		'vigne' => ['vigne'],
	];

	/**
	 * Les cultures de `CROPS` qui réunissent des espèces sans en désigner une : aucune d'elles n'a
	 * d'espèce de référence (`getCropPlant()`), la première de leur liste n'y étant qu'un ordre
	 * alphabétique.
	 */
	const CROPS_GROUPS = ['champignons', 'cultures fruitières', 'cultures légumières', 'fines herbes', 'petit fruits', 'petits fruits'];

	/**
	 * Les cultures du catalogue qu'aucune espèce du référentiel partagé ne reprend, avec leur raison.
	 * Leurs usages gardent une liste d'espèces vide et ne paraissent sur aucune fiche.
	 */
	const CROPS_OUTSIDE = [
		'agrumes' => 'culture tropicale',
		'amandier' => 'arboriculture sans espèce au référentiel',
		'ananas' => 'culture tropicale',
		'arachide' => 'grande culture',
		'arbres et arbustes' => 'ornement',
		'avocatier' => 'culture tropicale',
		'avoine' => 'grande culture',
		'bananier' => 'culture tropicale',
		'betterave industrielle et fourragère' => 'grande culture',
		'blé' => 'grande culture',
		'bulbes ornementaux' => 'ornement',
		'canne à sucre' => 'culture tropicale',
		'carambole' => 'culture tropicale',
		'chanvre' => 'grande culture, le chanvre du référentiel est aromatique',
		'chataignier' => 'arboriculture sans espèce au référentiel',
		'corossol' => 'culture tropicale',
		'cotonnier' => 'grande culture',
		'crucifères oléagineuses' => 'grande culture',
		'cultures florales et plantes vertes' => 'ornement',
		'cultures ornementales' => 'ornement',
		'cultures tropicales' => 'culture tropicale',
		'céréales' => 'grande culture',
		'céréales à paille' => 'grande culture',
		'epices' => 'plantes à parfum, aromatiques et médicinales, hors des fines herbes',
		'forêt' => 'forêt',
		'fruit de la passion' => 'culture tropicale',
		'fruits à coque' => 'arboriculture, le noyer a sa propre culture',
		'gazons de graminées' => 'ornement',
		'goyavier' => 'culture tropicale',
		'graines protéagineuses' => 'grande culture',
		'graminées fourragères' => 'grande culture',
		'grandes cultures' => 'grande culture',
		'grenadier' => 'arboriculture sans espèce au référentiel',
		'houblon' => 'grande culture',
		'infusions' => 'plantes à parfum, aromatiques et médicinales, hors des fines herbes',
		'jachères et cultures intermédiaires' => 'grande culture',
		'jardin d\'amateur' => 'usages réservés aux jardiniers amateurs',
		'jevi' => 'jardins, espaces végétalisés et infrastructures',
		'kaki' => 'arboriculture sans espèce au référentiel',
		'kiwaï' => 'arboriculture sans espèce au référentiel',
		'lin' => 'grande culture',
		'litchi' => 'culture tropicale',
		'légumes racines et tubercules tropicaux' => 'culture tropicale',
		'légumineuses fourragères' => 'grande culture',
		'légumineuses potagères (sèches)' => 'légumes secs, récoltés en grain',
		'manguier' => 'culture tropicale',
		'maïs' => 'grande culture, le maïs doux a sa propre culture',
		'noisetier' => 'arboriculture sans espèce au référentiel',
		'olivier' => 'arboriculture sans espèce au référentiel',
		'orge' => 'grande culture',
		'palmiers alimentaires' => 'culture tropicale',
		'papayer' => 'culture tropicale',
		'pavot' => 'grande culture',
		'pistachier' => 'arboriculture sans espèce au référentiel',
		'plantes d\'intérieur et balcons' => 'ornement',
		'porte graine' => 'production de semences',
		'porte graine - ppamc, florales et potagères' => 'production de semences',
		'porte graine, betterave industrielle et fourragère' => 'production de semences',
		'porte graine, graminées fourragères et à gazons' => 'production de semences',
		'porte graine, légumineuses fourragères' => 'production de semences',
		'porte graine, maïs' => 'production de semences',
		'porte graine, plantes à fibre' => 'production de semences',
		'ppam - non alimentaires' => 'plantes à parfum, aromatiques et médicinales, hors des fines herbes',
		'ppamc' => 'plantes à parfum, aromatiques et médicinales, hors des fines herbes',
		'prairies' => 'grande culture',
		'riz' => 'grande culture',
		'rosier' => 'ornement',
		'salsifis' => 'aucune espèce au référentiel',
		'sarrasin' => 'grande culture',
		'seigle' => 'grande culture',
		'soja' => 'grande culture',
		'sorgho' => 'grande culture',
		'tabac' => 'grande culture',
		'tournesol' => 'grande culture',
	];

	/**
	 * Les cultures de `CROPS_OUTSIDE` dont l'espèce a pourtant un dessin dans les vignettes
	 * (`plant/static/image/plants.svg`) : elles prennent sa vignette sans couvrir aucune espèce
	 * (retour du 29/09 par M-Prod, « blé et orge prennent leur vignette »). Le chanvre et
	 * la betterave industrielle n'y sont pas, leur dessin étant celui d'une autre plante que la leur.
	 */
	const CROPS_DRAWINGS = [
		'avoine' => 'avoine',
		'blé' => 'ble',
		'kaki' => 'kaki',
		'lin' => 'lin',
		'maïs' => 'mais',
		'orge' => 'orge',
		'sarrasin' => 'sarrasin',
		'seigle' => 'seigle',
		'soja' => 'soja',
		'sorgho' => 'sorgho',
		'tournesol' => 'tournesol',
	];

	public static function getCropKey(string $crop): string {
		return mb_strtolower(self::getLabel($crop));
	}

	/**
	 * L'espèce de référence d'une culture du catalogue, celle dont la vignette la représente
	 * (retour du 29/09, « Partout ») : la première de sa liste dans `CROPS`, qui est
	 * l'espèce que la culture nomme (« Oignon » pour oignon, ail et échalote). NULL pour une culture
	 * qui réunit des espèces sans en désigner une, pour « Traitements généraux » et pour une culture
	 * hors du référentiel, sauf celles dont l'espèce a un dessin (`CROPS_DRAWINGS`).
	 */
	public static function getCropPlant(string $crop): ?string {

		$key = self::getCropKey($crop);

		if(array_key_exists($key, self::CROPS_DRAWINGS)) {
			return self::CROPS_DRAWINGS[$key];
		}

		$fqns = self::CROPS[$key] ?? NULL;

		if(is_array($fqns) === FALSE or in_array($key, self::CROPS_GROUPS, TRUE)) {
			return NULL;
		}

		return $fqns[0];

	}

	/**
	 * Les espèces du référentiel partagé que couvre chaque culture de la table, en ids triés. Une
	 * seule lecture des espèces pour tout un import, et non une par usage.
	 */
	public static function getPlantsByCrop(): array {

		$plants = \plant\Plant::model()
			->select('id', 'fqn')
			->whereStructure(NULL)
			->getColumn('id', index: 'fqn');

		$all = array_values($plants);
		sort($all);

		$plantsByCrop = [];

		foreach(self::CROPS as $key => $fqns) {

			if($fqns === 'all') {
				$plantsByCrop[$key] = $all;
			} else {
				$ids = array_values(array_intersect_key($plants, array_flip($fqns)));
				sort($ids);
				$plantsByCrop[$key] = $ids;
			}

		}

		return $plantsByCrop;

	}

	/**
	 * Réécrit les espèces de tous les usages en base d'après la table, une écriture par culture.
	 * Rend les cultures en base que la table ne déclare pas.
	 */
	public static function updatePlants(): array {

		$plantsByCrop = self::getPlantsByCrop();

		$crops = PhytoUsage::model()
			->select('crop')
			->group('crop')
			->getColumn('crop');

		$unknown = [];

		foreach($crops as $crop) {

			$key = self::getCropKey($crop);

			if(array_key_exists($key, self::CROPS_OUTSIDE) === FALSE and array_key_exists($key, $plantsByCrop) === FALSE) {
				$unknown[] = $crop;
			}

			// Écriture de masse : un tableau de valeurs, qui ne borne pas la requête à une seule ligne.
			PhytoUsage::model()
				->whereCrop($crop)
				->update(['plants' => $plantsByCrop[$key] ?? []]);

		}

		return $unknown;

	}

	/**
	 * Les usages autorisés d'un produit sur les espèces d'une structure, un par culture et par dose,
	 * avec pour chacun les espèces qu'il couvre (`cPlant`). Une espèce est couverte par l'id de son
	 * espèce de référence, ou par le sien si elle est partagée ; sans référence, elle ne l'est que par
	 * « Traitements généraux ». Une culture qui ne couvre aucune espèce de la structure n'est pas rendue.
	 *
	 * E-Phy écrit un usage par cible, par mode de traitement (partie aérienne, sol…) et par délai :
	 * sous une même culture et une même dose, les cibles se réunissent (`targets`) et le délai avant
	 * récolte retenu est le plus court, celui qu'autorise au moins un des usages réunis.
	 */
	public static function getByProductForPlants(PhytoProduct $ePhytoProduct, \Collection $cPlant): \Collection {

		$cPhytoUsage = PhytoUsage::model()
			->select(PhytoUsage::getSelection())
			->whereProduct($ePhytoProduct)
			->sort(['crop' => SORT_ASC, 'target' => SORT_ASC])
			->getCollection();

		$cPhytoUsageKept = new \Collection();

		foreach($cPhytoUsage as $ePhytoUsage) {

			$key = $ePhytoUsage['crop'].'*'.$ePhytoUsage['dose'].'*'.$ePhytoUsage['doseUnit'];
			$target = $ePhytoUsage['target'] ?? $ePhytoUsage['treatment'];

			if($cPhytoUsageKept->offsetExists($key)) {

				$ePhytoUsageKept = $cPhytoUsageKept[$key];

				if($target !== NULL) {
					$ePhytoUsageKept['targets'][] = self::getLabel($target);
				}

				if(
					$ePhytoUsage['harvestDelay'] !== NULL and
					($ePhytoUsageKept['harvestDelay'] === NULL or $ePhytoUsage['harvestDelay'] < $ePhytoUsageKept['harvestDelay'])
				) {
					$ePhytoUsageKept['harvestDelay'] = $ePhytoUsage['harvestDelay'];
				}

				continue;

			}

			if($ePhytoUsage['plants'] === []) {
				continue;
			}

			$general = (self::CROPS[self::getCropKey($ePhytoUsage['crop'])] ?? NULL) === 'all';

			$cPlantCovered = $cPlant->find(function($ePlant) use($ePhytoUsage, $general) {

				if($ePlant['reference']->notEmpty()) {
					return in_array($ePlant['reference']['id'], $ePhytoUsage['plants'], TRUE);
				} else if($ePlant['structure']->empty()) {
					return in_array($ePlant['id'], $ePhytoUsage['plants'], TRUE);
				} else {
					return $general;
				}

			});

			if($cPlantCovered->notEmpty()) {
				$ePhytoUsage['cPlant'] = $cPlantCovered;
				$ePhytoUsage['cropName'] = self::getLabel($ePhytoUsage['crop']);
				$ePhytoUsage['cropPlant'] = self::getCropPlant($ePhytoUsage['crop']);
				$ePhytoUsage['targets'] = ($target === NULL) ? [] : [self::getLabel($target)];
				$cPhytoUsageKept[$key] = $ePhytoUsage;
			}

		}

		foreach($cPhytoUsageKept as $ePhytoUsage) {
			$ePhytoUsage['targets'] = self::getList($ePhytoUsage['targets']);
		}

		return $cPhytoUsageKept;

	}

	/**
	 * Un libellé E-Phy tel qu'il se lit : sans les pluriels et les renvois de note du catalogue
	 * (« Mildiou(s) », « Mousses (1) »), et avec des virgules là où il sépare des cultures par un
	 * tiret (« Tomate - Aubergine » devient « Tomate, aubergine »).
	 */
	public static function getLabel(string $label): string {

		$label = preg_replace('/\((s|x|es)\)|\s*\(\d+\)/u', '', $label);
		$label = preg_replace_callback('/\s+-\s+(\p{Lu})(?!\p{Lu})/u', fn($match) => ', '.mb_strtolower($match[1]), $label);

		return trim($label);

	}

	/**
	 * Plusieurs libellés en une liste, sans doublon, où seul le premier garde sa majuscule
	 * (« Mildiou, bactériose »). Un libellé tout en capitales reste tel quel.
	 */
	private static function getList(array $labels): array {

		$labels = array_values(array_unique($labels));

		foreach($labels as $position => $label) {

			if($position > 0 and mb_strtoupper($label) !== $label) {
				$labels[$position] = mb_strtolower(mb_substr($label, 0, 1)).mb_substr($label, 1);
			}

		}

		return $labels;

	}

	/**
	 * La dose proposée par défaut à un produit choisi dans le catalogue : la dose par hectare la plus
	 * fréquente parmi ses usages sur les cultures de la structure, la plus basse à égalité. NULL
	 * quand aucun usage ne donne de dose par hectare.
	 */
	public static function getDefaultUnit(PhytoProduct $ePhytoProduct): ?string {

		$units = array_count_values(PhytoUsage::model()
			->whereProduct($ePhytoProduct)
			->whereDoseUnit('IN', RoutineLib::PHYTO_UNITS)
			->getColumn('doseUnit'));

		if($units === []) {
			return NULL;
		}

		arsort($units);

		return array_key_first($units);

	}

	public static function getDefaultDose(\Collection $cPhytoUsage): ?array {

		$doses = [];

		foreach($cPhytoUsage as $ePhytoUsage) {

			if(
				$ePhytoUsage['dose'] !== NULL and
				in_array($ePhytoUsage['doseUnit'], RoutineLib::PHYTO_UNITS, TRUE)
			) {
				$key = $ePhytoUsage['dose'].'|'.$ePhytoUsage['doseUnit'];
				$doses[$key] = ($doses[$key] ?? 0) + 1;
			}

		}

		if($doses === []) {
			return NULL;
		}

		uksort($doses, fn($a, $b) => ($doses[$b] <=> $doses[$a]) ?: ((float)$a <=> (float)$b));

		[$dose, $unit] = explode('|', array_key_first($doses));

		return [
			'dose' => (float)$dose,
			'unit' => $unit
		];

	}

}
?>
