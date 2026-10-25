<?php
/**
 * Ce que la revue du référentiel a tranché sur le regroupement des noms, espèce
 * par espèce.
 *
 * Lu et écrit par plant\PlantMappingAiLib, jamais en direct.
 *
 * Ces décisions ne portent pas sur les variétés — celles-là vivent en base — mais
 * sur la façon dont l'analyse les fait émerger des noms saisis. Elles n'ont donc
 * de sens que pour plant/configure/variety/arbitrageAi, et rien ne les lit à
 * l'exécution.
 *
 * 'keywords' décrit l'espèce du point de vue de qui cherche une variété : les
 * types, les ports et les couleurs par lesquels on la parcourt, et non les noms
 * de cultivars. Conservés dans l'ordre où ils ont été validés, du plus
 * structurant au plus anecdotique.
 *
 * 'merged' et 'rejected' gardent la trace des rapprochements arbitrés dans un
 * sens et dans l'autre, 'ignored' des grappes écartées du référentiel — sans
 * quoi la revue les reproposerait indéfiniment. Ce sont des clés de grappe, donc
 * des formes normalisées : elles dépendent de l'analyse du jour, et la revue
 * signale celles qu'elle ne produit plus.
 */
return [

	'ail' => [
		'keywords' => [
			'Automne',
			'Printemps',
			'Blanc',
			'Rose',
			'Violet',
		],
		'merged' => [
			'cledor|cledorprintemps',
		],
		'rejected' => [
			'therador|thermidor',
		],
		'ignored' => [
			'automne',
			'blanc',
			'conservation',
			'ours',
			'printemps',
			'rose',
			'vertautoproduction',
			'violet',
		],
	],

	'aneth' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'officinal',
			'varietesenmelange',
		],
	],

	'arroche' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'rouge',
			'verte',
		],
	],

	'artichaut' => [
		'keywords' => [],
		'merged' => [
			'greenglobe|vertglobe',
			'grosvertlaon|vertlaon',
			'imperialstar|imperial',
		],
		'rejected' => [],
		'ignored' => [
			'cynarascolymus',
			'vert',
			'violet',
		],
	],

	'asperge' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'verte',
			'violette',
		],
	],

	'aubergine' => [
		'keywords' => [
			'Long',
			'Ronde',
			'Noire',
			'Violette',
			'Blanche',
			'Striée',
		],
		'merged' => [
			'listadagandia|listadadigandia',
			'monstrueusenewyork|monstrueuseny',
		],
		'rejected' => [
			'melonga|meronda',
		],
		'ignored' => [
			'aubergine',
			'blanche',
			'longueblanche',
			'longueviolette',
			'noire',
			'striee',
			'violette',
		],
	],

	'basilic' => [
		'keywords' => [],
		'merged' => [
			'genovese|genovesegrandvert',
			'genovese|grandvertgenovese',
			'grandefeuille|grandesfeuilles',
			'grandvert|vert',
		],
		'rejected' => [],
		'ignored' => [
			'shisovert',
		],
	],

	'betterave' => [
		'keywords' => [
			'Rouge',
			'Jaune',
			'Blanche',
		],
		'merged' => [
			'detroit2bolivar|bolivar',
			'detroit2bolivar|detroit2racebolivar',
			'detroit2bolivar|detroitbolivar',
			'detroit2bolivar|rougebolivar',
			'noireplateegypte|egypte',
			'noireplateegypte|noireegypte',
			'noireplateegypte|plateegypte',
			'wintersonne|wintersonnesg',
		],
		'rejected' => [],
		'ignored' => [
			'botte',
			'conservation',
			'jaune',
			'primeur',
			'rouge',
		],
	],

	'blette' => [
		'keywords' => [
			'Verte',
			'Blanche',
			'Mini',
			'Rouge',
			'Jaune',
			'Multicolore',
			'À couper',
		],
		'merged' => [
			'ampuis|3ampuis',
			'ampuis|fafampuis',
			'ampuis|raceampuis',
			'cardeampuis|carde3ampuis',
			'rhubarbchard|rougerhubarbe',
			'vertecardeblancheraceberac|cardeblancheberac',
			'vertecarderouge|vertecardesrouges',
		],
		'rejected' => [
			'couleur|couper',
		],
		'ignored' => [
			'blanche',
			'blette',
			'cadreblanc',
			'carde',
			'cardeblanche',
			'cardejaune',
			'cardemelange',
			'carderouge',
			'cardes',
			'cardesenmelange',
			'coloree',
			'couleur',
			'couper',
			'epinard',
			'italienne',
			'jaune',
			'melange5couleurs',
			'melangecolore',
			'melangecouleur',
			'miniblette',
			'multicolore',
			'poireevertecardeblanche',
			'rouge',
			'verte',
			'vertecarde',
		],
	],

	'cardon' => [
		'keywords' => [],
		'merged' => [
			'inerme|blanco',
			'inerme|pleinblanc',
			'inerme|pleinblancblanco',
		],
		'rejected' => [],
		'ignored' => [
			'blanc',
		],
	],

	'carotte' => [
		'keywords' => [
			'Rouge',
			'Orange',
			'Blanche',
			'Longue',
			'Jaune',
			'Violette',
			'Botte',
			'Conservation',
			'Primeur',
		],
		'merged' => [
			'berlicum|berlikumer2',
			'marcheparis|rondehativeparis',
			'marcheparis|rondemarcheparis',
			'marcheparis|rondeparis',
			'nantaiseamelioree5|nantaise5',
			'rougesang|longuerougesang',
		],
		'rejected' => [
			'deliva|dolciva',
			'maestria|maestro',
		],
		'ignored' => [
			'botte',
			'conservation',
			'couleur',
			'jaune',
			'nantaise',
			'nantaiseamelioree',
			'orange',
			'primeur',
			'rouge',
			'varietea',
			'varieteb',
		],
	],

	'cassis' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'caseillejosta',
		],
	],

	'celeri-branche' => [
		'keywords' => [],
		'merged' => [
			'tango|branchbranchetango',
		],
		'rejected' => [],
		'ignored' => [
			'couper',
		],
	],

	'celeri-couper' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'celeri-rave' => [
		'keywords' => [],
		'merged' => [
			'athos|athosks',
		],
		'rejected' => [],
		'ignored' => [
			'monarchmars',
			'tubereuxmars',
		],
	],

	'chicoree-frisee' => [
		'keywords' => [],
		'merged' => [
			'finemeaux|tresfinemeaux',
			'maux|meaux',
		],
		'rejected' => [],
		'ignored' => [
			'frisee',
			'painsucre',
		],
	],

	'chicoree-sauvage' => [
		'keywords' => [],
		'merged' => [
			'fiero|trevisefiero',
			'uranus|painsucreuranus',
			'vespero|painsucrevespero',
		],
		'rejected' => [],
		'ignored' => [
			'painsucre',
			'trevise',
		],
	],

	'chicoree-scarole' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'frisee',
			'painsucre',
			'painsucrevespero',
			'rouge',
		],
	],

	'chou-blanc' => [
		'keywords' => [],
		'merged' => [
			'cabton|choucroutecabton',
			'coeurboeufvertus|coeurboeuf',
			'deadon|pontoisedeadon',
			'dottenfelderdauer|dottenfelder',
			'dottenfelderdauer|dottenfelderdauerks',
			'holsteiner|holsteinerplatter',
			'vaugirard|vaugirardhiver',
		],
		'rejected' => [],
		'ignored' => [
			'blanc',
			'choucroute',
			'milan',
			'pointu',
			'pointucaraflex',
			'pointuchateaurenard',
			'pointucoeurboeuf',
			'rouge',
		],
	],

	'chou-brocoli' => [
		'keywords' => [
			'Violet',
			'Vert',
			'À jets',
		],
		'merged' => [
			'calabrais|calabraishatif',
			'calabrese|calabresenatalino',
			'jetviolet|jetsviolets',
		],
		'rejected' => [],
		'ignored' => [
			'jets',
			'romanesco',
		],
	],

	'chou-de-bruxelles' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'selectionsativa',
		],
	],

	'chou-de-milan' => [
		'keywords' => [],
		'merged' => [
			'deadon|pontoisedeadon',
			'paradiesler|friseparadiesler',
			'stanton|pontoisestanton',
		],
		'rejected' => [],
		'ignored' => [
			'coeurboeuf',
			'frise',
		],
	],

	'chou-fleur' => [
		'keywords' => [
			'Violet',
			'Orange',
			'Blanc',
			'Vert',
		],
		'merged' => [
			'amabile|amabileks',
			'tabiro|tabiroks',
		],
		'rejected' => [],
		'ignored' => [
			'fleur',
			'romanesco',
			'romanescobossaroma',
			'romanesconatalino',
			'romanescoveronica',
			'tardif',
			'vert',
			'violet',
		],
	],

	'chou-kale' => [
		'keywords' => [
			'Vert',
			'Rouge',
		],
		'merged' => [
			'noirtoscane|nero',
			'noirtoscane|neroditoscana',
			'rougerougerussie|redrussian',
			'rougerussie|rougerougerussie',
			'westlandsewinter|westlandse',
			'westlandse|westlander',
		],
		'rejected' => [],
		'ignored' => [
			'frise',
			'friserouge',
			'rouge',
			'vert',
			'violet',
		],
	],

	'chou-pak-choi' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'atsuko',
			'atsukopetsai',
			'bilko',
			'green',
			'greenchoicp',
			'manoko',
			'packchoi',
			'pakchoigreen',
			'petsaimanoko',
			'rouge',
		],
	],

	'chou-pe-tsai' => [
		'keywords' => [],
		'merged' => [
			'atsuko|petsaiatsuko',
			'fidelks|fidel',
		],
		'rejected' => [],
		'ignored' => [
			'pakchoigreen',
		],
	],

	'chou-pointu' => [
		'keywords' => [],
		'merged' => [
			'caraflex|coeurboeufcaraflex',
			'coeurboeufvertus|cbvertus',
			'coeurboeufvertus|coeurboeufvertus2',
		],
		'rejected' => [],
		'ignored' => [
			'coeurboeufchana',
			'primeur',
			'rouge',
			'rougerodynda',
			'rougetetenoire',
			'violet',
		],
	],

	'chou-rave' => [
		'keywords' => [
			'Violet',
			'Vert',
			'Blanc',
		],
		'merged' => [
			'azurstar|azur',
			'blaril|blarilks',
			'delikatesswitte|delikatess',
			'delikatesswitte|delikatesswhite',
			'sat21|enrico',
			'sat21|enricosat',
			'sat21|enricosat21',
			'superschmelz|blancgeantsuperschmelz',
		],
		'rejected' => [],
		'ignored' => [
			'blanc',
			'rouge',
			'vert',
			'violet',
		],
	],

	'chou-romanesco' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'chou-rouge' => [
		'keywords' => [],
		'merged' => [
			'tete3|teten3',
			'tetenoire3|tetenoiren3',
		],
		'rejected' => [],
		'ignored' => [
			'rouge',
		],
	],

	'ciboule-de-chine' => [
		'keywords' => [],
		'merged' => [
			'ishikura|ishikuralongwhite',
		],
		'rejected' => [],
		'ignored' => [
			'rebouillon',
		],
	],

	'ciboulette' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'chine',
			'ciboulechine',
		],
	],

	'claytone-cuba' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'cuba',
			'hiver',
		],
	],

	'concombre' => [
		'keywords' => [
			'Lisse',
			'Épineux',
		],
		'merged' => [
			'akito|ceakito',
			'cleopha|cleophaks',
			'rollisonstelegraph|rollinsons',
			'rollisonstelegraph|rollinsontelegraph',
			'rollisonstelegraph|rollisons',
			'rollisonstelegraph|rollisontelegraph',
			'rollisons|rollinson',
			'vertlongmaraicher|vertlong',
		],
		'rejected' => [],
		'ignored' => [
			'blanchatif',
			'concombre',
			'court',
			'epineux',
			'hollandais',
			'kiwano',
			'lisse',
			'long',
			'longlisse',
			'noa',
			'vert',
		],
	],

	'coriandre' => [
		'keywords' => [],
		'merged' => [
			'petitegraine|petitesgraines',
		],
		'rejected' => [],
		'ignored' => [
			'cp',
			'cultivee',
			'enfeuille',
			'feuille',
			'sativum',
			'vietnamienne',
		],
	],

	'cornichon' => [
		'keywords' => [],
		'merged' => [
			'vertfinmeaux|finmeaux',
			'vertfinmeaux|meaux',
		],
		'rejected' => [],
		'ignored' => [],
	],

	'courge' => [
		'keywords' => [
			'Potimarron',
			'Butternut',
			'Potiron',
			'Orange',
			'Vert',
			'Bleu',
			'Rouge',
			'Noir',
			'Rose',
			'Jaune',
			'Musquée',
			'Citrouille',
		],
		'merged' => [
			'amoro|potimarronamorro',
			'angelique|potimarronangelique',
			'canadamezoides|canada',
			'delicata|delicatasweetpotato',
			'delicata|sweetdelicata',
			'earlybutternut|earlybutter',
			'galeuseeysines|galeuse',
			'kabocha|potironvertkabocha',
			'luffa|lufa',
			'manosk|musqueeprovencemanosk',
			'melonettejaspeevendee|melonette',
			'melonettejaspeevendee|melonnettejaspee',
			'musqueeprovence|moschatamusqueeprovence',
			'musqueeprovence|muscade',
			'musqueeprovence|muscadeprovence',
			'musqueeprovence|musquee',
			'nelson|bleuhongrienelson',
			'noixbeurre|butternutnoixbeurre',
			'patidousweetdumpling|patidou',
			'patidousweetdumpling|sweetdumpling',
			'redcurry|potimarronorange',
			'redkuri|potimarronredcurry',
			'redkuri|potimarronredkurry',
			'redkuri|redcurry',
			'rougevifetampes|rougeetampes',
			'rougevifetampes|rougevif',
			'rougevifetampes|vifetampes',
			'spaghettivegetal|spagetti',
			'spaghettivegetal|spagheti',
			'spaghettivegetal|spaghetti',
			'spaghettivegetal|spaguetti',
			'spaghettivegetal|vegetablespaghetti',
			'spaghettivegetal|vegetal',
			'sucrineberry|musqueesucrineberry',
			'sucrineberry|sucrine',
			'tibello|butternuttibello',
		],
		'rejected' => [
			'buttercupbleue|butternut',
			'buttercup|butternut',
			'butternut|potironbuttercup',
			'potimaronredkuri|potimarronredkury',
			'potimarronredkurry|solor',
			'redcurry|redkury',
			'spagheti|spaguetti',
			'vegetable|vegetal',
		],
		'ignored' => [
			'butternut',
			'butternutmoschata',
			'butternutpop',
			'minibutternut',
			'moschata',
			'moschatabutternut',
			'musqueebutternut',
			'patisson',
			'potimaronuchikikuri',
			'potimarron',
			'potimarronredkury',
			'potimarronvert',
			'potiron',
		],
	],

	'courgette' => [
		'keywords' => [
			'Verte',
			'Longue',
			'Ronde',
			'Jaune',
			'Blanche',
		],
		'merged' => [
			'alberellodisarzana|alberello',
			'cocozellevontripolis|cocozelle',
			'costatesromanesco|costataromanesco',
			'nicefruitrond|nice',
			'petitevertealger|petitealger',
			'vertenoncoureusemaraichers|vertemaraichers',
			'vertenoncoureusemaraichers|vertenoiremaraichere',
		],
		'rejected' => [],
		'ignored' => [
			'allongeejaune',
			'blanche',
			'jaune',
			'jaunepop',
			'jaunepopulation',
			'longuejaune',
			'longueverte',
			'patisson',
			'ronde',
			'rondejaune',
			'rondeverte',
			'selectionsativajaune',
			'serie1',
			'serie2',
			'verte',
			'vertenoncoureuse',
			'vertenoncoureuseitalie',
		],
	],

	'cresson' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'hiver',
		],
	],

	'echalote' => [
		'keywords' => [
			'Echalion',
			'Longue',
			'Ronde',
		],
		'merged' => [
			'cuissepoulet|cuissepouletpoitou',
			'cuissepoulet|echalioncuissepoulet',
		],
		'rejected' => [],
		'ignored' => [
			'echalion',
			'longue',
			'ronde',
		],
	],

	'endive' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'precoce',
			'saison',
			'tardive',
		],
	],

	'epinard' => [
		'keywords' => [],
		'merged' => [
			'geanthiver|geant',
		],
		'rejected' => [],
		'ignored' => [
			'epinard',
			'grand',
		],
	],

	'fenouil' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'commun',
		],
	],

	'feve' => [
		'keywords' => [],
		'merged' => [
			'expresseleonora|express',
			'superaguadulcetreslonguecosse|superaguadulce',
			'superaguadulcetreslonguecosse|superaguadulcetreslonguescosses',
		],
		'rejected' => [],
		'ignored' => [],
	],

	'fraise' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'framboise' => [
		'keywords' => [],
		'merged' => [
			'heritage|heritageremontant',
		],
		'rejected' => [],
		'ignored' => [
			'jaune',
			'nonremontante',
			'nonremontanttulameen',
			'remontants',
		],
	],

	'gingembre' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'commun',
			'perou',
			'zingiberofficinale',
		],
	],

	'gombo' => [
		'keywords' => [],
		'merged' => [
			'clemsonspineless|clemsonspinelessokra',
		],
		'rejected' => [],
		'ignored' => [],
	],

	'haricot' => [
		'keywords' => [
			'Nain',
			'Vert',
			'À rames',
			'Beurre',
			'Violet',
			'À écosser',
			'Sec',
			'Mangetout',
			'Plat',
			'Grimpant',
		],
		'merged' => [
			'compass|mecaniquecompass',
		],
		'rejected' => [],
		'ignored' => [
			'bagnols',
			'beurre',
			'blanc',
			'cobravesperal',
			'contendercodair',
			'ecosser',
			'grimpant',
			'haricotsnains',
			'haricotsrames',
			'mangetout',
			'nain',
			'nainbeurre',
			'nainlingotsuisse',
			'nainpurpleteepee',
			'nains',
			'plat',
			'rame',
			'rames',
			'sec',
			'vert',
			'verts',
			'violet',
		],
	],

	'kiwano' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'laitue' => [
		'keywords' => [
			'Verte',
			'Batavia',
			'Blonde',
			'Feuille de chêne',
			'Rouge',
			'Pommée',
			'Beurre',
			'Sous abri',
			'Romaine',
			'Sucrine',
			'Brune',
			'Rougette',
			'Plein champ',
		],
		'merged' => [
			'beurrepaumee|beurrepommee',
			'godzilla|bvgodzilla',
			'grassemorges|romainegrassemorges',
			'ketale|rougetteketale',
			'kiela|feuillechenevertekiela',
			'liboga|ligoba',
			'lioba|bataviabrunelioba',
			'lioba|bataviarougelioba',
			'lioba|bataviarougeliobaenrobee',
			'lioba|batbrunelioba',
			'lioba|brunelioba',
			'marinski|brmarinski',
			'rougegrenobloise|rougegrenoble',
			'saladbowlrouge|bowlrouge',
			'saladbowlrouge|redsaladbowl',
			'saladbowlverte|bowlverte',
			'saladbowlverte|saladbowl',
		],
		'rejected' => [
			'bowlverte|saladbowlrouge',
			'fdcverteavenir|fdcvertelenir',
			'grenobloise|rougegrenobloise',
			'liboga|lioba',
			'parinice|pastinice',
			'rougettecavale|rougetterafale',
			'saladbowlrouge|saladbowlverte',
		],
		'ignored' => [
			'asperge',
			'aspergeceltuce',
			'batablonde',
			'batavia',
			'bataviaabrivertenaturabella',
			'bataviablonde',
			'bataviabrune',
			'bataviarouge',
			'bataviaverte',
			'beurre',
			'beurrepommee',
			'blonde',
			'brunehiver',
			'cordai',
			'ete',
			'fdc',
			'fdcblonde',
			'fdcrouge',
			'fdcverte',
			'feuillechene',
			'feuillecheneblonde',
			'feuillecheneblondekitsch',
			'feuillechenebrune',
			'feuillechenerouge',
			'feuillecheneverte',
			'frisee',
			'hardy',
			'hiver',
			'iceberg',
			'laitue',
			'lioba',
			'mesclun',
			'mix',
			'painsucre',
			'pc',
			'pommee',
			'pommeerouge',
			'pommeeverte',
			'printemps',
			'romaine',
			'rouge',
			'rougette',
			'sa',
			'saintantoine',
			'scarole',
			'sem',
			'sucrine',
			'sucrineverte',
			'verte',
		],
	],

	'mache' => [
		'keywords' => [],
		'merged' => [
			'baron|coquillebaron',
			'gala|coquillegala',
			'grossegraine|grossesgraines',
			'hollandegrossesgraines|hollande',
			'princess|coquilleprincess',
		],
		'rejected' => [],
		'ignored' => [
			'coquille',
			'verte',
		],
	],

	'mais' => [
		'keywords' => [
			'Doux',
			'Pop-corn',
		],
		'merged' => [
			'damaun|damaunks',
			'goldenbantam|bantam',
			'goldenbantam|douxgoldenbantam',
			'goldenbantam|golden',
		],
		'rejected' => [],
		'ignored' => [
			'biomasse',
			'doux',
			'multicolore',
			'population',
		],
	],

	'melon' => [
		'keywords' => [],
		'merged' => [
			'petitgrisrennes|petitgris',
			'vertolive|vertolivehiver',
		],
		'rejected' => [],
		'ignored' => [
			'charentais',
		],
	],

	'mizuna' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'melangeasiatique',
			'melangerougevert',
			'melangeverterouge',
			'rouge',
			'vert',
			'verte',
		],
	],

	'navet' => [
		'keywords' => [
			'Blanc',
			'Plat',
			'Violet',
			'Rose',
			'Jaune',
			'Rond',
		],
		'merged' => [
			'blancglobecolletviolet|blanccolletviolet',
			'blancglobecolletviolet|blanccolviolet',
			'blancglobecolletviolet|blancglobecolviolet',
			'blancglobecolletviolet|coletviolet',
			'blancglobecolletviolet|globe',
			'blancglobecolletviolet|globecollet',
			'blancglobecolletviolet|globecolletviolet',
			'milanrougeraceaudric|audric',
			'platblanc|platblancmai',
			'platmilan|milan',
			'vertusmarteau|marteau',
		],
		'rejected' => [],
		'ignored' => [
			'audricviolet',
			'blanc',
			'botte',
			'forcer',
			'jaune',
			'rose',
			'rougeplathatif',
			'tokyo',
			'violet',
		],
	],

	'oca-du-perou' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'oignon' => [
		'keywords' => [
			'Jaune',
			'Rouge',
			'Blanc',
			'Rose',
			'Botte',
			'Bulbille',
			'Conservation',
			'Ciboule',
			'Cébette',
		],
		'merged' => [
			'blanclisbonne|blanclisboa',
			'ishikuralongwhite|ishikura',
			'jaunepaillevertus|jaunepaille',
			'moradaamposta|morada',
			'paillevertus|blancrouge',
			'paillevertus|jaunepaillevertus',
			'redbaron|redbaronbulbille',
			'redspark|rougeredspark',
			'rossalungadifirenze|rosalunga',
			'rossalungadifirenze|rossalunga',
			'rougeflorence|florence',
			'rougeflorence|longflorence',
			'stuttgarterriesen|jaunestuttgarter',
			'stuttgarterriesen|stuttgart',
			'stuttgarterriesen|stuttgarterreisen',
			'stuttgart|stuttgarter',
			'tropeatonda|tropea',
		],
		'rejected' => [],
		'ignored' => [
			'blanc',
			'blancbotte',
			'botte',
			'bulbille',
			'bulbilles',
			'cebette',
			'cevennes',
			'ciboule',
			'conservation',
			'cuissepoulet',
			'cuissepouletpoitou',
			'echalion',
			'echalioncuissepoulet',
			'echalioncuissepouletpoitou',
			'echalotte',
			'jaune',
			'jauneconserv',
			'jauneconservation',
			'nouveau',
			'roscoff',
			'rose',
			'rouge',
			'rougefrais',
		],
	],

	'oseille' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'panais' => [
		'keywords' => [],
		'merged' => [
			'turga|turgapregerme',
			'turga|turgas',
		],
		'rejected' => [],
		'ignored' => [],
	],

	'pasteque' => [
		'keywords' => [],
		'merged' => [
			'confire|confiture',
			'earlymoon|earlymoonbeam',
		],
		'rejected' => [],
		'ignored' => [
			'confire',
		],
	],

	'patate-douce' => [
		'keywords' => [
			'Blanche',
			'Violette',
			'Orange',
		],
		'merged' => [
			'sakura|sakurapurple',
		],
		'rejected' => [],
		'ignored' => [
			'blanche',
			'orange',
			'violette',
		],
	],

	'patisson' => [
		'keywords' => [],
		'merged' => [
			'lillesol|jaunelilesol',
			'panachevertblanc|vertblanc',
			'panache|panacheet',
		],
		'rejected' => [
			'panache|panacheet',
		],
		'ignored' => [
			'blanc',
			'jaune',
			'orange',
		],
	],

	'persil' => [
		'keywords' => [],
		'merged' => [
			'geantitalie|geantitalieracesplendid',
			'geantitalie|geantitaliesplendide',
			'geantitalie|platitalie',
			'magnik|simplemagnik',
		],
		'rejected' => [],
		'ignored' => [
			'aneth',
			'coriandre',
			'cp',
			'geant',
			'giganteitalia',
			'plat',
			'platcommun',
			'simple',
			'splendid',
		],
	],

	'physalis' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'ceriseterre',
			'coqueretperou',
			'pubescent',
		],
	],

	'piment' => [
		'keywords' => [
			'Doux',
			'Fort',
		],
		'merged' => [
			'gorria|basquegorria',
			'gorria|espelette',
			'gorria|espelettegorria',
			'habaneroorange|hothabaneroorange',
			'habanerorouge|habanerorot',
			'hungarianhotwax|hungarian',
			'jalapenoruben|jalapeno',
			'jalapenoruben|jalapenorubenrs',
			'vegetarientrapu|vegetarien',
		],
		'rejected' => [],
		'ignored' => [
			'chilimix',
			'doux',
			'habanero',
		],
	],

	'poire' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'poire-de-terre' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'terre',
			'yacon',
		],
	],

	'poireau' => [
		'keywords' => [
			'Hiver',
			'Été',
			'Automne',
		],
		'merged' => [
			'freezo|freezoks',
			'haldor|haldorks',
			'hannibalherbstriesen2|hannibal',
			'hannibalherbstriesen2|herbstriesenhannibal',
		],
		'rejected' => [],
		'ignored' => [
			'automne',
			'ete',
			'hiver',
			'poireau',
		],
	],

	'pois' => [
		'keywords' => [
			'À rames',
			'Mangetout',
			'Croquant',
			'À écosser',
			'Nain',
			'Gourmand',
		],
		'merged' => [
			'merveillekelvedon|petitpoismerveillekelvedon',
			'rondo|demiramerondo',
		],
		'rejected' => [
			'delikata|delikett',
		],
		'ignored' => [
			'croquant',
			'croquetout',
			'ecosser',
			'gourmand',
			'mangetout',
			'nain',
			'petitpois',
			'pois',
			'rame',
		],
	],

	'poivron' => [
		'keywords' => [
			'Rouge',
			'Jaune',
			'Orange',
			'Vert',
			'Long',
			'Chocolat',
			'Violet',
			'Blanc',
			'Allongé',
			'Carré',
			'Cornu',
		],
		'merged' => [
			'astijaune|jaunecarreasti',
			'barkan|barkanrs',
			'chocolat|choco',
			'cornodibuegiallo|cornodibue',
			'cornodibuegiallo|cornoditorojaune',
			'cornodibuegiallo|cornogiallo',
			'cornodibuegiallo|jaunecornoditoro',
			'cornoditorojaune|cornoditorogiallo',
			'cornoditororouge|cornetaureau',
			'cornoditororouge|cornetaureaurouge',
			'cornoditororouge|cornetoro',
			'cornoditororouge|corno',
			'cornoditororouge|cornoditoro',
			'cornoditororouge|cornoditororosso',
			'cornoditororouge|cornoditorro',
			'cornoditororouge|cornoditorrorosso',
			'cornoditororouge|cornorosso',
			'jumbostuff|jumbo',
			'sprinter|carresprinter',
		],
		'rejected' => [],
		'ignored' => [
			'california',
			'carre',
			'carrejaune',
			'carrerouge',
			'cornodibuerosso',
			'cornojaune',
			'cornu',
			'cuboorange',
			'gorria',
			'jaune',
			'long',
			'orange',
			'piment',
			'pimentcayenne',
			'pimentdouxlandes',
			'pimentgorria',
			'pimentvegetarien',
			'poivron',
			'primeur',
			'quadrato',
			'quadratoasti',
			'rouge',
			'yolo',
			'yolowondercarre',
		],
	],

	'pomme' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'pomme-de-terre' => [
		'keywords' => [
			'Chair ferme',
			'Chair tendre',
			'Rouge',
			'Jaune',
			'Blanche',
			'Violette',
			'Primeur',
			'Conservation',
		],
		'merged' => [
			'apollo|appolo',
			'charlotte|charlotteprimeur',
			'ladychristl|ladykristel',
			'sirtema|primeursirtema',
		],
		'rejected' => [
			'twinner|twister',
		],
		'ignored' => [
			'blanche',
			'conservation',
			'f9',
			'primeur',
			'violette',
		],
	],

	'radis-botte' => [
		'keywords' => [
			'Rond',
			'Rouge',
			'Rose',
			'Long',
			'Demi-long',
			'Violet',
		],
		'merged' => [
			'18jours|18j',
			'18jours|dixhuitjours',
			'gaudry2|gaudry',
			'ostergrussrosa|ostergruss',
			'pernotclair|pernot',
		],
		'rejected' => [],
		'ignored' => [
			'botte',
			'demilong',
			'long',
			'radis',
			'rond',
			'rondblanc',
			'rondrouge',
			'rose',
		],
	],

	'radis-rave' => [
		'keywords' => [
			'Noir',
			'Long',
			'Rond',
			'Rose',
			'Blanc',
			'Violet',
			'Rouge',
		],
		'merged' => [
			'blauerherbstundwinter|bleuautomne',
			'blauerherbstundwinter|bleuautomnehiver',
			'bluemeat|bleumeat',
			'deliblue|knbravo',
			'minowase|minowasedaikon',
			'misatoflesh|misato',
			'misatored|misato',
			'noirgrosrondhiver|grosrondhiver',
			'noirgrosrondhiver|noirgrosrond',
			'noirgrosrondhiver|noirrond',
			'noirgrosrondhiver|noirrondhiver',
			'noirgrosrondhiver|rondnoir',
			'noirlongmaraicher|longnoir',
			'noirlongmaraicher|noirlong',
			'poidshorloge|noirhorloge',
		],
		'rejected' => [
			'deliking|delipink',
			'noirlong|noirrond',
		],
		'ignored' => [
			'blanc',
			'blue',
			'bluemeat',
			'chinarose',
			'greenmeat',
			'hiver',
			'long',
			'noir',
			'rond',
			'rose',
			'rosepaques',
			'violet',
		],
	],

	'raifort' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'rhubarbe' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [],
	],

	'roquette-cultivee' => [
		'keywords' => [],
		'merged' => [
			'iris|sauvageiris',
		],
		'rejected' => [],
		'ignored' => [
			'annuelle',
			'sauvage',
			'vivace',
		],
	],

	'rutabaga' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'jaune',
		],
	],

	'sauge' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'officinale',
		],
	],

	'thym' => [
		'keywords' => [],
		'merged' => [
			'serpolet|serpyllum',
		],
		'rejected' => [],
		'ignored' => [
			'officinal',
			'thujanol',
		],
	],

	'tomate' => [
		'keywords' => [
			'Cerise',
			'Coeur de boeuf',
			'Ronde',
			'Orange',
			'Cocktail',
			'Grappe',
			'Rouge',
			'Ancienne',
			'Noire',
			'Rose',
			'Plein champ',
			'Jaune',
			'Blanche',
			'Déterminée',
		],
		'merged' => [
			'ace55|ace',
			'albenga|albengarouge',
			'ananasjaune|ananas',
			'andinecornue|andes',
			'andinecornue|andine',
			'andinecornue|cornue',
			'andinecornue|cornueandes',
			'brandywinerouge|brandywine',
			'calabash|calabashrouge',
			'coeurboeufrose|cuordibueracerose',
			'coeurboeufrouge|coeurboeuf',
			'coeurboeufrouge|coeurrouge',
			'coeurboeufrouge|cuordibue',
			'cookie|cerisecookie',
			'dadssunset|dadsunset',
			'dadssunset|orangedadssunset',
			'kakicoing|kaki',
			'noirerussecharbonneuse|noirerusse',
			'paola|paola2tetes',
			'poirejaune|yellowpearshaped',
			'poirerouge|cerisepoirerouge',
			'poirerouge|redpear',
			'poirerouge|redpearshaped',
			'precocequimper|quimper',
			'roseberne|bernerrose',
			'sanmarzano|sanmarzanotrapue',
			'zuckertraube|cerisezuckertraube',
		],
		'rejected' => [
			'albengarouge|coeurboeufrouge',
			'brandywinerose|brandywinerouge',
			'cdbrose|cdbrouge',
			'coeurboeufrose|coeurboeufrouge',
			'noirerusse|russe',
			'phiabella|primabella',
			'rougerusse|russe',
			'trixi|trixiks',
		],
		'ignored' => [
			1,
			2,
			1884,
			'ancienne',
			'anciennemix',
			'cdb',
			'cdbrouge',
			'cerise',
			'cerisejaune',
			'cerisejauneronde',
			'cerisemelange',
			'cerisemix',
			'cerisenoire',
			'ceriseorange',
			'ceriseronderouge',
			'ceriserouge',
			'cerisesenmelange',
			'cerisesvariees',
			'cocktail',
			'coeurbeauf',
			'coulis',
			'determinee',
			'grappe',
			'jaune',
			'merveille',
			'orange',
			'pleinchamp',
			'precoce',
			'ronde',
			'ronderouge',
			'rose',
			'rouge',
			'tardive',
			'tomate',
			'varietesanciennes',
			'verte',
		],
	],

	'topinambour' => [
		'keywords' => [],
		'merged' => [],
		'rejected' => [],
		'ignored' => [
			'commun',
			'ferme',
			'rose',
		],
	],

];
