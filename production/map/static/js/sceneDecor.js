/**
 * Le décor posé d'une structure dans la scène 3D : les bâtiments, l'eau, les arbres et les petits objets de `map\Decor`, chacun
 * sur son point, son angle et sa taille.
 *
 * Quand la page propose la pose (`[data-scene-decor-sheet]`), un bouton ouvre un catalogue par familles à la place de la frise.
 * Une vignette se clique, l'élément suit alors le pointeur et un clic sur le terrain le pose, ou se glisse jusqu'au terrain. Un
 * élément posé se déplace d'un glissé et se tourne, se retaille ou se retire par les outils rangés sous lui. Un geste ne mène
 * jamais un élément dans l'interdit, hors du terrain, sur une planche ou sur un autre élément : il s'arrête au bord et glisse
 * le long. Chaque geste achevé s'écrit aussitôt par les routes unitaires de `page/decor.p.php`, l'une après l'autre, et
 * « Annuler » rejoue à l'envers ce que la séance a écrit.
 *
 * Un tracé, chemin, cours d'eau, haie, bande fleurie ou clôture, se dessine d'un glissé, que le relâché simplifie et termine, ou se
 * pose point par point, d'un clic chacun, et se termine d'un double-clic, d'Entrée ou de « Terminer ». Échap retire le dernier
 * geste, glissé entier ou point, puis le tracé. Un tuyau et une rampe ne se posent qu'au clic. Posé, un tracé ne se déplace pas
 * d'un bloc : sa largeur ou son modèle se changent par les outils, et sa ligne par ses poignées : un point se glisse, un milieu de
 * segment en insère un, la pastille du point choisi le retire. « Continuer » le reprend depuis le bout choisi, premier ou dernier
 * point.
 *
 * Un clic sur le terrain choisit la parcelle ou le jardin qu'il touche, et la feuille ne propose plus que de le clôturer : une
 * clôture parmi ses modèles, une haie, ou les deux, chacune posée d'un clic sur sa vignette et retirée d'un second. Choisir une
 * emprise déjà close sélectionne sa clôture, que la même feuille change.
 *
 * La famille « Terrain » ne pose rien. L'herbe et le sol qu'on y choisit repeignent la scène et s'écrivent par
 * `page/configuration.p.php`.
 *
 * Un clic sur un personnage ne fait rien : il marche sur le terrain, il n'est pas du décor.
 *
 * Le plan se lit en mètres, `{ x, n }` vers l'est et le nord, comme dans `ScenePlanGeometry` ; la scène pose `n` sur `-z`. L'avant
 * d'un modèle est son `+z`, côté porte.
 */
class MapSceneDecor {

	// La grille du terrain pendant la pose, et celle, plus fine, sous l'élément qu'on porte, où il se cale
	static CELL = 5;
	static SNAP = 1;

	// La grille du terrain à pleine force, et le côté d'une case à l'écran, en pixels, sous lequel elle faiblit puis disparait :
	// des traits plus serrés ne se séparent plus et voilent le pré d'un aplat. La règle ne lit que la case, jamais la largeur de
	// l'écran : la même case donne la même grille partout
	static GRID_OPACITY = .22;
	static GRID_FADE = { full: 8, gone: 3 };

	// Le cerne d'encre sous le trait du geste déborde du trait comme celui de la Carte, dont le trait de 3 pixels passe sur un cerne
	// de 5 (`MapCartography.GESTURE`) : en mètres, la scène n'a pas de largeur fixe à l'écran, seulement ce rapport
	static STROKE_CASING = 5 / 3;

	// L'écart gardé entre le mot dit au-dessus d'un élément et le bord de la scène, en pixels
	static SAY_EDGE = 4;

	// La hauteur dont l'emprise de l'enseigne au nom de la structure se lève pour que le mot ne la couvre pas. Une lettre de SIZE
	// mètres monte sur ses piquets à (1.6 + .45 + 6) × 6 / 6 = 8.05 mètres (`MapSceneSign.letters()`), un accent plus haut ; l'emprise
	// porte déjà l'ombre des lettres, si bien que la zone évitée déborde le nom d'une hauteur de lettre au plus, accents compris
	static SIGN_HEIGHT = 8.1;

	// Un élément tourne par crans de 15°
	static STEP = Math.PI / 12;

	// Un plant ne réserve que ce qui touche le sol : ce que son modèle a sous FOOT_HEIGHT mètre, tronc ou pied, et FOOT_MARGIN mètre
	// autour. Sa cime déborde sur ses voisins
	static FOOT_HEIGHT = .25;
	static FOOT_MARGIN = .3;

	// Un tracé à plat, chemin ou cours d'eau, passe sous la cime d'un plant et ne bute que sur son tronc : un carré de TRUNK_SHARE
	// de la largeur du plant, de TRUNK_MIN mètre de côté au moins pour qu'un petit plant ne laisse pas un chemin le frôler
	static TRUNK_SHARE = .15;
	static TRUNK_MIN = .5;

	// Une pierre ne se fauche ni ne s'arrache : son tronc est tout ce qu'elle pose au sol, et un tracé à plat la contourne. Une souche,
	// un roncier, des fougères ou des roseaux se dégagent pour ouvrir un chemin, qui ne bute alors que sur leur tronc
	static STONES = ['rocher', 'pierrier'];

	// Ce qui se marche et mène quelque part, à ras du sol : les paysans le traversent, le portail d'un enclos se tourne vers lui, et
	// l'enseigne se voit par-dessus
	static WALKED = ['chemin', 'route'];

	// L'arrêt au bord glisse aussi le long des planches à moins de SLIDE_NEAR mètres, et repart de RETREATS mètres en retrait
	// du bord, les têtes d'une rangée de planches n'étant pas alignées au centimètre près
	static SLIDE_NEAR = 6;
	static RETREATS = [0, .1, .3];

	static THUMB = 128;

	// Un tracé court jusqu'à TRACE_LENGTH mètres, et ne se termine pas plus court que TRACE_MIN. Ses virages s'arrondissent sur
	// TRACE_STEP / 4 de part et d'autre de chaque point
	static TRACE_STEP = 1.5;
	static TRACE_MIN = 2;
	static TRACE_LENGTH = 1000;

	// Un glissé relève un point par FREEHAND_STEP mètres au plus, ce qui garde un tracé dessiné sous le point par mètre que le poids
	// des tracés compte (`Map.s.php`). Au relâché, la ligne ne s'écarte de la main que de FREEHAND_TOLERANCE, sous la demi-largeur
	// de la clôture, le plus fin des tracés : l'écart reste sous le trait
	static FREEHAND_STEP = 1;
	static FREEHAND_TOLERANCE = .25;

	// Une clôture ou une haie se pose par travées, d'un piquet au suivant : un piquet au plus coudé de chaque virage qui tourne de
	// BEND au moins, en comptant ensemble les coudes à moins de BEND_REACH mètres l'un de l'autre, et d'autres entre eux au pas du modèle
	static BEND = Math.PI / 9;
	static BEND_REACH = 1.5;

	// Le tracé des vignettes, en écarts au centre
	static SAMPLE = [{ x: -4, n: -1.5 }, { x: -1.5, n: 1 }, { x: 1.5, n: -.5 }, { x: 4, n: 1.5 }];
	// L'enclos carré d'une vignette
	static ENCLOSURE_SAMPLE = [{ x: -5, n: -5 }, { x: 5, n: -5 }, { x: 5, n: 5 }, { x: -5, n: 5 }];

	// Les clôtures sous la valeur que `map\Decor.model` enregistre : la travée cuite que la scène répète, tirée selon +Z, son pas, et
	// le passage que laisse son portail dans le tour d'un enclos
	static FENCES = {
		sheep: { slug: 'cloture.mouton', pitch: 3, gate: 3.6 },
		paling: { slug: 'cloture.ganivelle', pitch: 2.5, gate: 3.6 },
		panel: { slug: 'cloture.rigide', pitch: 2.5, gate: 3.6 },
		electric: { slug: 'cloture.electrique', pitch: 5, gate: 4 }
	};

	// Le tour d'un enclos passe à FENCE_CLEAR mètre des planches et des serres de ce qu'il clôt, et jamais à moins de FENCE_EDGE mètre
	// hors de son contour ; une planche qui en sort de plus de FENCE_REACH mètres n'est plus tenue pour la sienne
	static FENCE_CLEAR = .5;
	static FENCE_EDGE = .3;
	static FENCE_REACH = 8;

	// Le portail d'un enclos se pose face au chemin le plus proche, s'il passe à moins de GATE_REACH mètres, à GATE_ROOM mètres au
	// moins de chaque coin. Il glisse le long du tour et ne se rebâtit qu'à chaque GATE_STEP mètre : un enclos avec sa haie se refait
	// en plusieurs millisecondes
	static GATE_REACH = 25;
	static GATE_ROOM = 2.3;
	static GATE_STEP = .4;
	static GATE = 'portail';
	static GATE_LENGTH = 3.6;
	// Un portail posé seul ne barre que par ses deux poteaux, carrés de GATE_POST mètres : on passe entre eux
	static GATE_POST = .3;
	// Une clôture ne barre que de FENCE_SOLID mètres de part et d'autre de son tour, et l'on fait le tour d'un enclos par dedans, à
	// ROUND_INSET mètres du tour : entre la clôture et une planche qui la touche presque
	static FENCE_SOLID = .05;
	static ROUND_INSET = .1;
	// Par dehors, on le contourne à ROUND_OUTSET mètres de ce qu'il dresse
	static ROUND_OUTSET = .5;

	// La haie d'un enclos double sa clôture côté extérieur, son feuillage de HEDGE_WIDTH mètres à HEDGE_GAP mètre d'elle, et s'ouvre
	// devant le portail. Seule, elle part du tour et laisse le passage d'un portail
	static HEDGE_WIDTH = 2.2;
	static HEDGE_GAP = .35;

	// Une haie mêle ses formes : elle en change toutes les HEDGE_RUN travées, parmi HEDGE_POOL et jamais deux fois la même de suite,
	// et dresse un arbre, HEDGE_TREE, toutes les HEDGE_TREE_EVERY travées, jamais à un bout. La charmille, HEDGE_WALL, est plus
	// étroite que ses voisines : HEDGE_WALL_END l'évase à la première et à la dernière travée de sa série, qui en compte donc deux
	// au moins et que l'arbre ne coupe pas. Chaque travée tire sa hauteur à HEDGE_HEIGHT près. Un arbre ne se pose que sur une
	// travée étirée dans HEDGE_STRETCH, la plage pour laquelle son tronçon est dessiné
	static HEDGE_POOL = ['haie', 'haie.baies', 'haie.charmille'];
	static HEDGE_WALL = 'haie.charmille';
	static HEDGE_WALL_END = 'haie.charmille.bout';
	static HEDGE_TREE = 'haie.bocage';
	static HEDGE_RUN = [2, 4];
	static HEDGE_TREE_EVERY = [6, 9];
	static HEDGE_HEIGHT = .2;
	static HEDGE_STRETCH = [.7, 1.3];

	// Les couches d'une bande à plat, du dessous au-dessus, en [débord sur la largeur, hauteur au-dessus du sol, couleur], couleurs
	// prises à la palette des modèles (`base/scene/model/helpers.js`). Le chemin passe au-dessus de l'eau : leur croisement se lit
	// comme un gué
	static STREAM = [[1.2, .04, '#d5c6ab'], [0, .07, '#9fd0dc']];
	static TRACK = [[.3, .09, '#d5c6ab'], [0, .11, '#dccfae']];
	// La route, un cran au-dessus du chemin, passe sur lui à leur croisement : une chaussée d'asphalte lisse et son bas-côté clair.
	// Bombée, elle demanderait des sommets en travers que son plafond de triangles n'admet pas
	static ROAD = [[1, .1, '#d5c6ab'], [0, .12, '#6f7a80']];
	// Le marquage du milieu d'une route, encore un cran au-dessus de sa chaussée, en tirets de ROAD_DASH mètres tous les ROAD_PITCH
	// mètres. Il ne fait qu'une géométrie pour toutes les routes, comme les ponts, parce qu'il s'interrompt aux carrefours et monte
	// sur les ponts, qui dépendent des autres tracés
	static ROAD_MARK = .14;
	static ROAD_DASH = 3;
	static ROAD_PITCH = 6;
	static ROAD_LINE = .15;
	static ROAD_PAINT = '#f2efe6';
	// Un carrefour très rasant écarterait le marquage sans fin, en 1 / sinus : au-dessous de JUNCTION_SINE, environ onze degrés, la
	// coupure garde la longueur qu'elle a à cet angle, cinq fois celle d'un carrefour à angle droit
	static JUNCTION_SINE = .2;

	// Le pont de pierre qu'un chemin ou une route pose sur un cours d'eau qu'il croise, sans que personne le pose. Sous BRIDGE_SINE,
	// trente degrés, l'arche s'allongerait sans fin et le gué reste. L'arche couvre l'eau et BRIDGE_BANK de berge, le tablier le
	// tracé et sa marge, BRIDGE_SIDE en plus pour les parapets. La grille des croisements est en cases de BRIDGE_CELL mètres : un
	// segment de chemin ne se compare qu'à l'eau de ses cases
	static BRIDGED = ['chemin', 'route', 'cours-eau'];
	static BRIDGE_SINE = .5;
	static BRIDGE_BANK = .4;
	static BRIDGE_SIDE = .6;
	static BRIDGE_CELL = 4;
	// La largeur du modèle `pont` parapets compris (tablier ±1, parapets à ±1,3), que la couronne de l'arche dépasse jusqu'à ±1,34 : la boite cuite ne la donne pas
	static BRIDGE_ACROSS = 2.6;
	// Le tablier du modèle, qui prend la couleur de la chaussée qui passe dessus. Sur une route, les rampes s'allongent de
	// BRIDGE_ROAD_RAMP : à cinq mètres de large, un pont aussi court que celui d'un chemin se lit comme une dalle posée sur l'eau
	static BRIDGE_DECK = '#dccfae';
	static BRIDGE_ROAD_RAMP = 3;

	// Les deux verts d'un feuillage cuit, en clair et en ombre. Chaque arbre prend l'un des trois verts de GREENS, pour que deux
	// arbres voisins ne se fondent pas l'un dans l'autre : le premier garde ceux du modèle, les deux autres les tirent vers un
	// vert plus clair ou plus sombre, chacun en [vert du modèle, vert vers lequel il va, part du chemin]
	static FOLIAGE = ['#4d9a4a', '#2f7d3d'];
	static GREENS = [
		null,
		[['#4d9a4a', '#9ccc65', .28], ['#2f7d3d', '#4d9a4a', .3]],
		[['#4d9a4a', '#2f7d3d', .35], ['#2f7d3d', '#1d4a2c', .3]]
	];

	// Les essences d'un arbre, par leur modèle cuit, à leur taille de l'échelle 1
	static ESSENCES = {
		rond: ['arbre'],
		sapin: ['arbre.sapin'],
		peuplier: ['arbre.peuplier'],
		fruitier: ['fruitier', 'fruitier.rouge', 'fruitier.or']
	};

	// Ce que la saison fait d'un sommet, dans l'attribut `season` de chaque géométrie du décor, en [sorte, retard, rang de chute] : 0
	// rien, 1 la feuille d'un arbre caduc, 2 le feuillage persistant d'une haie ou d'un buisson, 3 la bande d'un chemin, 4 le fruit
	// d'un arbre caduc, qui tombe avec ses feuilles sans en prendre la teinte. Une feuille se reconnait à son vert, plus vert que rouge
	// de LEAF_GREEN fois, un fruit à la pièce `fruit` de son modèle, et un tronc garde sa couleur en toute saison
	static SEASON_LEAF = 1;
	static SEASON_HEDGE = 2;
	static SEASON_PATH = 3;
	static SEASON_FRUIT = 4;
	static LEAF_GREEN = 1.3;
	static DECIDUOUS = ['arbre', 'arbre.peuplier', 'fruitier', 'fruitier.rouge', 'fruitier.or', 'chene', 'saule', 'bouleau'];

	// Les dates de la scène de la comptabilité, que les deux scènes vivent ensemble. Le feuillage d'un arbre caduc se compte en jours de
	// l'année : vert tendre au printemps, vert d'été dès août. L'automne se compte en jours depuis le 1er
	// octobre : la feuille vire en LEAF_TURN_DAYS jours, de l'orangé au brun roux en AUTUMN_DAYS jours, puis tombe facette par facette
	// en LEAF_FALL_DAYS jours à partir du LEAF_FALL_START, le 21 octobre. Chaque arbre suit avec un retard de zéro à LEAF_LATE_DAYS - 1
	// jours, pour qu'un bosquet ne vire ni ne se dépouille d'un seul bloc : le dernier est nu le 24 novembre. Une facette ne disparait
	// pas d'un coup : elle se rétrécit vers son centre en LEAF_FADE_DAYS jours, pris dans les LEAF_FALL_DAYS, sans quoi la lecture,
	// qui avance d'un jour à la fois, arracherait à chaque pas un paquet de feuilles. Au printemps, les feuilles d'un arbre regrandissent
	// depuis leur centre en autant de jours, à partir du jour où pousse un fruitier des cultures (`MapSceneCrops.LEAF_ON`, la mi-mars),
	// avec le même retard qu'à l'automne : les deux arbres voisins reverdissent ensemble. Les fruits ne viennent qu'au FRUIT_ON, le
	// 25 juin, et grandissent comme une feuille, en autant de jours et avec le même retard. La teinte de saison multiplie la couleur de
	// chaque feuille par son rapport au vert d'été, compté en couleurs linéaires, où un écart de teinte se creuse. Le vert tendre
	// relève le vert et baisse le rouge : relever le rouge tirerait le vert gris d'un saule vers le jaune pâle, plus clair que l'herbe
	static FOLIAGE_SEASON = [[0, null], [95, '#4b9030'], [120, '#4d8d33'], [230, '#4f8a35'], [366, '#4f8a35']];
	static FOLIAGE_SUMMER = '#4f8a35';
	static AUTUMN = ['#c9893a', '#a8552e'];
	static AUTUMN_DAYS = 37;
	static LEAF_TURN_DAYS = 21;
	static LEAF_FALL_START = 20;
	static LEAF_FALL_DAYS = 27;
	static LEAF_LATE_DAYS = 8;
	static LEAF_FADE_DAYS = 4;
	static FRUIT_ON = 175;

	/**
	 * Le calendrier des arbres, en jours, lu dans les statiques ci-dessus à chaque appel : c'est celui du plan, et une scène qui suit
	 * une autre année (le bourg de la comptabilité) passe le sien au constructeur. `late` est le nombre de retards d'un arbre à
	 * l'autre. La pousse compte depuis le 1er janvier, la chute depuis le 1er octobre, et `fade` est la part de la chute que met une
	 * facette à se rétrécir. `turn.share` est la part du retard que prend le virage, `fruit.share` celle que prennent les fruits, et
	 * `fruit.fall`, quand il est posé, les fait tomber avant les feuilles. `tint`, quand il est posé, est une table [jour, [r, g, b]]
	 * qui multiplie la couleur de la feuille à la place des teintes du plan. `crown` rétrécit le houppier d'un bloc vers son centre au
	 * lieu de chaque facette vers la sienne.
	 */
	static calendar() {

		const D = MapSceneDecor;

		return {
			late: D.LEAF_LATE_DAYS,
			grow: { from: MapSceneCrops.LEAF_ON, days: D.LEAF_FADE_DAYS },
			fall: { from: D.LEAF_FALL_START, days: D.LEAF_FALL_DAYS, fade: D.LEAF_FADE_DAYS },
			turn: { days: D.LEAF_TURN_DAYS, share: 1 },
			fruit: { from: D.FRUIT_ON, days: D.LEAF_FADE_DAYS, share: 1, fall: null },
			tint: null,
			crown: false
		};

	}

	// Un nombre du calendrier en flottant GLSL, écrit en entier : un entier prend son point, une fraction l'a déjà. Un arrondi
	// écarterait le nuanceur des lectures JS qui reprennent les mêmes nombres (`leaves()`, `tintAt()`)
	static float(x) {
		return Number.isInteger(x) ? x +'.' : String(x);
	}

	// L'habillage d'hiver, du 1er janvier au 15 février : la haie ternit vers HEDGE_WINTER et la feuille d'automne s'assagit de
	// AUTUMN_SOBER vers son gris. Il arrive en WINTER_FADE jours à partir du 1er janvier et se retire en autant de jours avant le 15
	// février : posé d'un coup, il faisait sauter la haie du 31 décembre au 1er janvier, et rien n'en déborde sur le printemps
	static HEDGE_SUMMER = '#4f8a37';
	static HEDGE_WINTER = '#66704a';
	static AUTUMN_SOBER = .35;
	static WINTER_LAST = 45;
	static WINTER_FADE = 14;

	// Le blanc d'un chemin, auquel la saison de la scène rapporte le sien
	static PATH_SUMMER = '#dccfae';

	// Des ondes sur l'eau d'une mare : RIPPLES cercles qui s'élargissent l'un après l'autre en 1 / RIPPLE_PACE seconde, à la taille 1
	// de RIPPLE_FROM à RIPPLE_TO mètres de rayon, posés au hasard dans RIPPLE_SPREAD mètres de part et d'autre du centre, à la hauteur
	// de l'eau du modèle
	static RIPPLES = 4;
	static RIPPLE_PACE = .3;
	static RIPPLE_FROM = .1;
	static RIPPLE_TO = .9;
	static RIPPLE_SPREAD = [1.7, 1.15];
	static RIPPLE_LIFT = .09;

	// Un bosquet tire sa nature avec lui : feuillus, conifères, mêlé, ou bouquet serré de trois ou quatre arbres
	static GROVES = [
		{ essences: ['rond'], count: [4, 8], apart: 1.7 },
		{ essences: ['sapin', 'sapin', 'sapin', 'peuplier'], count: [4, 7], apart: 1.5 },
		{ essences: ['rond', 'rond', 'rond', 'sapin', 'peuplier'], count: [5, 8], apart: 1.7 },
		{ essences: ['rond', 'rond', 'sapin'], count: [3, 4], apart: 1.1 }
	];

	// Les centres des trois massifs d'un bosquet, en mètres : chaque massif tient dans une ellipse de 6,8 sur 6 m (`grove()`), ceux
	// du bas se touchent, celui du haut chevauche les deux
	static WOOD = [[-3.5, -2], [3.5, -2], [0, 3.2]];

	// Une cuve de 1 000 litres, 1,2 m sur 1 et 1,25 m de haut, ne se lit pas à la vue par défaut au pied d'un hangar de sept mètres :
	// elle se montre TANK_SCALE fois plus grande, comme le réseau d'irrigation
	static TANK_SCALE = 2.5;

	// Un véhicule garé au pied d'un hangar ne s'y reconnait pas à sa taille réelle : tous se montrent VEHICLE_SCALE fois
	// plus grands, du même facteur pour garder entre eux leurs proportions
	static VEHICLE_SCALE = 2;

	// Un objet importé de la rangée Matériel s'incline sur la pente comme un banc ou une table tant que sa hauteur ne dépasse pas
	// TILT_TALLEST fois le plus petit côté de son emprise : au-delà, un épouvantail ou une échelle, il se tient debout comme un
	// poteau
	static TILT_TALLEST = 2;

	/**
	 * Les kits sous la clé que `map\Decor.kit` enregistre. `model` est le modèle cuit d'un kit d'une pièce, `plants` compose un kit
	 * d'arbres d'après un tirage qui suit l'id de l'élément, et son emprise est le pied de chacun. `grown` agrandit le modèle, emprise
	 * comprise, quand il ne se lirait pas à sa taille réelle. `size` borne l'outil de taille, qui multiplie ce rendu : un élément à
	 * la taille 1 garde celui du kit ; un kit sans `size` garde sa taille. Sur le relief, un kit `tilts` à large base posée au sol,
	 * véhicule, caisse, rocher, s'incline sur la pente (`tilt()`), là où ce qui pousse ou se plante reste debout.
	 * Le kit `building` pose l'un des bâtiments que la structure a composés (`plan.buildings`), que sa pose cite par `building`. Un kit
	 * `object` pose l'un des éléments que la structure a importés dans sa rangée (`plan.objects`), que sa pose cite par `object`.
	 *
	 * Un kit `traced` se dessine : une bande à plat de couches `layers`, à l'une des largeurs `widths` ou à la largeur fixe `wide`
	 * sur `across` bandes en travers, ou des travées cuites répétées le long de la ligne, que `pieces` donne pour le modèle choisi
	 * parmi `models`, sur une emprise de `thick` mètres. Une travée `varied` se retourne une fois sur deux et change de vert, et
	 * `overlap` la prolonge à chaque bout pour fermer les coudes. Un kit `joins` se raccorde aux tracés comme eux entre eux. Un kit
	 * `barrier` ne se laisse traverser que par un tracé qui se marche (`WALKED`), par un tracé d'eau et par un portail.
	 *
	 * Un kit `enclosure` n'a pas de vignette : il se pose en choisissant sur la scène la parcelle ou le jardin qu'il clôt.
	 *
	 * Un kit `layer` ne se pose que dans un calque de ce type (`map\DecorLayer.type`), et sa vignette `icon` est dessinée : un tube
	 * de quelques centimètres ne se verrait pas sur un rendu. `shape` est une forme bâtie ici en attendant son modèle cuit, `pipe` une
	 * conduite tracée, sur laquelle une vanne `seats` se cale, et `longest` borne en mètres la longueur d'un tracé. Cette borne
	 * est un refus de la pose, comme les autres : le serveur ne confronte pas un élément au catalogue de son kit. Un kit `overBeds`
	 * passe sur les planches et les serres.
	 */
	static KITS = {
		hangar: { family: 'building', model: 'hangar', size: [.5, 2] },
		bureau: { family: 'building', model: 'bureau', size: [.6, 1.6] },
		abri: { family: 'building', model: 'abri', size: [.6, 2] },
		stabulation: { family: 'building', model: 'stabulation', size: [.5, 1.5] },
		bergerie: { family: 'building', model: 'bergerie', size: [.5, 1.5] },
		fenil: { family: 'building', model: 'fenil', size: [.5, 1.5] },
		'hangar-solaire': { family: 'building', model: 'hangar-solaire', size: [.5, 1.5] },
		laverie: { family: 'building', model: 'laverie', size: [.5, 2] },
		'chambre-froide': { family: 'building', model: 'chambre-froide', size: [.6, 2] },
		magasin: { family: 'building', model: 'magasin', size: [.6, 2] },
		'poulailler-mobile': { family: 'building', model: 'poulailler-mobile', size: [.6, 2] },
		sechoir: { family: 'building', model: 'sechoir', size: [.5, 1.6] },
		batiment: { family: 'building', building: true, size: [.5, 1.5] },
		mare: { family: 'water', model: 'mare', size: [.4, 3], water: true },
		'bassin-bache': { family: 'water', model: 'bassin-bache', size: [.4, 2] },
		cuve: { family: 'water', model: 'cuve', grown: MapSceneDecor.TANK_SCALE },
		puits: { family: 'water', model: 'puits', size: [.6, 1.6] },
		'cours-eau': { family: 'water', water: true, traced: true, widths: [1, 2, 3], layers: MapSceneDecor.STREAM },
		arbre: { family: 'tree', size: [.5, 2.5], plants: r => [MapSceneDecor.tree(r, [0, 0], 1.1, ['rond', 'rond', 'sapin', 'peuplier'])] },
		fruitier: { family: 'tree', size: [.5, 2.5], plants: r => [MapSceneDecor.tree(r, [0, 0], 1.1, ['fruitier'])] },
		bosquet: { family: 'tree', size: [.5, 2.5], plants: r => MapSceneDecor.wood(r) },
		// Le bosquet d'un seul massif, que seul le décor par défaut pose : sans vignette, il ne s'enregistre jamais
		massif: { family: 'tree', size: [.5, 2.5], plants: r => MapSceneDecor.grove(r) },
		verger: { family: 'tree', size: [.5, 2.5], plants: r => MapSceneDecor.orchard(r) },
		buissons: { family: 'tree', size: [.5, 2.5], plants: r => MapSceneDecor.thicket(r) },
		alignement: { family: 'tree', size: [.5, 2.5], plants: r => MapSceneDecor.alignment(r) },
		chene: MapSceneDecor.wild('chene', [.6, 1.6]),
		saule: MapSceneDecor.wild('saule', [.6, 1.6]),
		bouleau: MapSceneDecor.wild('bouleau', [.6, 1.6]),
		'arbre-mort': MapSceneDecor.wild('arbre-mort', [.6, 1.6]),
		souche: MapSceneDecor.wild('souche', [.6, 2]),
		roncier: MapSceneDecor.wild('roncier', [.5, 2]),
		fougeres: MapSceneDecor.wild('fougeres', [.6, 2]),
		roseaux: MapSceneDecor.wild('roseaux', [.6, 2]),
		rocher: { ...MapSceneDecor.wild('rocher', [.5, 2.5]), tilts: true },
		pierrier: { ...MapSceneDecor.wild('pierrier', [.6, 2]), tilts: true },
		'bande-fleurie': { family: 'tree', traced: true, thick: 1.4, pieces: () => MapSceneDecor.strip() },
		haie: { family: 'tree', traced: true, thick: 1.6, pieces: () => MapSceneDecor.hedge() },
		chemin: { family: 'fence', traced: true, widths: [1, 2, 3], layers: MapSceneDecor.TRACK },
		// Une seule bande en travers : rien ne varie sur la largeur d'une chaussée lisse, et la route coûte 4 triangles au mètre en ligne
		// droite, le double coudée à chaque mètre (4 036 et 8 012 sur TRACE_LENGTH mètres, mesurés dans Chrome le 2026-10-22), ce que
		// `DecorLib::TRACE_WEIGHT` compte sous le plafond de la scène
		route: { family: 'fence', traced: true, wide: 5, across: 1, barrier: true, layers: MapSceneDecor.ROAD, ramp: MapSceneDecor.BRIDGE_ROAD_RAMP, marked: true },
		cloture: { family: 'fence', traced: true, thick: .6, models: Object.keys(MapSceneDecor.FENCES), pieces: model => MapSceneDecor.fence(model) },
		portail: { family: 'fence', model: 'portail', joins: true },
		enclos: { family: 'fence', enclosure: true, models: [...Object.keys(MapSceneDecor.FENCES), null] },
		panneau: { family: 'other', model: 'panneau', size: [.5, 2] },
		compost: { family: 'other', model: 'compost', size: [.5, 2], tilts: true },
		pompe: { family: 'irrigation', layer: 'irrigation', shape: 'pump', joins: true, icon: MapSceneDecor.drawing('<rect x="8" y="44" width="48" height="6" rx="2" fill="#7d8a96"/><rect x="10" y="26" width="26" height="18" rx="4" fill="#5e6b78"/><circle cx="44" cy="35" r="10" fill="#1c64b8"/><rect x="40" y="14" width="8" height="12" fill="#164f92"/><rect x="54" y="32" width="8" height="6" fill="#164f92"/>') },
		vanne: { family: 'irrigation', layer: 'irrigation', shape: 'valve', joins: true, overBeds: true, seats: true, icon: MapSceneDecor.drawing('<path d="M6 40 h52" stroke="#1c64b8" stroke-width="6" stroke-linecap="round"/><rect x="24" y="30" width="16" height="20" rx="3" fill="#fff" stroke="#1c64b8" stroke-width="3"/><path d="M32 30 v-12" stroke="#7d8a96" stroke-width="4"/><ellipse cx="32" cy="16" rx="11" ry="4" fill="none" stroke="#c0392b" stroke-width="4"/>') },
		electrovanne: { family: 'irrigation', layer: 'irrigation', model: 'electrovanne', joins: true, overBeds: true, seats: true, icon: MapSceneDecor.drawing('<path d="M6 40 h52" stroke="#1c64b8" stroke-width="6" stroke-linecap="round"/><path d="M36 22 q4 14 14 18 t10 16" fill="none" stroke="#f0a020" stroke-width="3" stroke-linecap="round"/><rect x="22" y="30" width="20" height="20" rx="3" fill="#fff" stroke="#1c64b8" stroke-width="3"/><rect x="25" y="12" width="14" height="15" rx="2" fill="#26282b"/>') },
		tuyau: { family: 'irrigation', layer: 'irrigation', traced: true, pipe: true, joins: true, overBeds: true, icon: MapSceneDecor.drawing('<path d="M8 46 L26 40 L38 24 L56 18" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 46 L26 40 L38 24 L56 18" fill="none" stroke="#1c64b8" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>') },
		rampe: { family: 'irrigation', layer: 'irrigation', traced: true, pipe: true, ramp: true, longest: 60, joins: true, overBeds: true, icon: MapSceneDecor.drawing('<path d="M6 28 h52" stroke="#1c64b8" stroke-width="5" stroke-linecap="round"/><path d="M12 30 v20 M32 30 v20 M52 30 v20" stroke="#7d8a96" stroke-width="3"/><path d="M22 26 v-6 M42 26 v-6" stroke="#164f92" stroke-width="4"/><path d="M14 14 q8 -8 16 0 M34 14 q8 -8 16 0" fill="none" stroke="#1c64b8" stroke-width="2" stroke-dasharray="3 3"/>') },
		caisses: { family: 'other', model: 'caisses', size: [.5, 2], tilts: true },
		tracteur: { family: 'other', model: 'tracteur', grown: MapSceneDecor.VEHICLE_SCALE, size: [.5, 2], tilts: true },
		utilitaire: { family: 'other', model: 'utilitaire', grown: MapSceneDecor.VEHICLE_SCALE, size: [.5, 2], tilts: true },
		'pick-up': { family: 'other', model: 'pick-up', grown: MapSceneDecor.VEHICLE_SCALE, size: [.5, 2], tilts: true },
		remorque: { family: 'other', model: 'remorque', grown: MapSceneDecor.VEHICLE_SCALE, size: [.5, 2], tilts: true },
		'objet-eau': { family: 'water', object: true },
		'objet-nature': { family: 'tree', object: true },
		'objet-materiel': { family: 'other', object: true, tilts: true }
	};

	// Un élément importé prend l'outil de taille des kits de sa rangée, qui changent de bornes sans qu'on ait à le suivre ici
	static {
		for(const def of Object.values(MapSceneDecor.KITS).filter(def => def.object === true)) {

			const size = MapSceneDecor.familySize(def.family);

			if(size !== null) {
				def.size = size;
			}

		}
	}

	// La plus petite borne basse et la plus grande borne haute des kits d'une rangée qui ont un outil de taille, null si aucun n'en a
	static familySize(family) {

		const sizes = Object.values(MapSceneDecor.KITS)
			.filter(def => def.family === family && def.object !== true && def.size !== undefined)
			.map(def => def.size);

		return (sizes.length === 0) ? null : [Math.min(...sizes.map(size => size[0])), Math.max(...sizes.map(size => size[1]))];

	}

	// Un élément importé que la modération a retiré, ou dont le binaire n'est pas encore lu, se pose en cube gris de OBJECT_BOX mètres
	static OBJECT_BOX = 2;
	static OBJECT_COLOR = '#9aa0a6';

	// Le réseau d'irrigation, en formes bâties ici en attendant les modèles cuits : sa couleur, le diamètre d'un tuyau neuf en mm,
	// l'écart entre le sol et un tube posé dessus, et la portée en mètres à laquelle une vanne se cale sur la conduite la plus proche
	static PIPE_COLOR = '#1c64b8';
	static PIPE_DEFAULT = 50;
	static PIPE_LIFT = .02;
	static GRID_LIFT = .04;
	static LIMIT_LIFT = .05;
	static SEAT_REACH = 3;

	// Les places d'une pastille de diamètre le long de son tracé, en part de sa longueur, de la première essayée à la dernière
	// (spreadDiameters())
	static DIAMETER_SHARES = [.5, .35, .65, .2, .8, .05, .95];

	// Un tube de quelques centimètres ne se verrait pas à la vue par défaut : un tuyau se dessine plus large que son diamètre, en
	// mètres, de 0,15 pour le plus fin des diamètres normalisés (`MapSetting::DECOR_PIPE_SIZES`, en mm) à 0,6 pour le plus gros, sur
	// une échelle logarithmique où deux diamètres voisins gardent le même rapport de largeur. Un manchon tous les SLEEVE mètres dit
	// que c'est un tube
	static PIPE_WIDTHS = [[16, .15], [110, .6]];
	static SLEEVE = 10;

	// La pompe, les vannes et la rampe sont bâties à leur taille réelle et montrées IRRIGATION_SCALE fois plus grandes, pour se lire à
	// la vue par défaut à côté d'un tuyau élargi
	static IRRIGATION_SCALE = 2.5;

	// Une vanne calée sur un tuyau grandit avec lui : à l'échelle commune, un tuyau de 110 mm l'avalerait. Son corps, VALVE_BODY
	// mètres en travers de la conduite pour les deux vannes (electrovanne.reference.md), y garde au moins VALVE_OVER fois la
	// largeur montrée du tuyau. Elle se dit calée à moins de VALVE_SNUG mètres du tracé, l'écart que laisse l'aller-retour de ses
	// coordonnées enregistrées
	static VALVE_BODY = .16;
	static VALVE_OVER = 1.4;
	static VALVE_SNUG = .05;

	// Un tuyau enterré se dessine en tirets au sol, DASH mètres pleins puis GAP vides, et deux fois : pleins là où on les voit, puis à
	// THROUGH d'opacité par-dessus ce qui les cache, pour se lire à travers les cultures sans les masquer
	static DASH = .8;
	static GAP = .6;
	static THROUGH = .4;

	// Une rampe d'aspersion : un tube de 32 mm à 60 cm du sol, un support tous les 3 m et une buse tous les 2 m
	static RAMP = { diameter: 32, height: .6, post: 3, nozzle: 2 };

	// La largeur montrée d'un tuyau de `diameter` mm, en mètres (PIPE_WIDTHS)
	static pipeWidth(diameter) {

		const [[d0, w0], [d1, w1]] = MapSceneDecor.PIPE_WIDTHS;

		return w0 * (w1 / w0) ** (Math.log(diameter / d0) / Math.log(d1 / d0));

	}

	static drawing(body) {
		return 'data:image/svg+xml,'+ encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'+ body +'</svg>');
	}

	static between(r, a, b) {
		return a + (b - a) * r();
	}

	static pick(r, list) {
		return list[Math.floor(r() * list.length)];
	}

	/**
	 * Un arbre d'une des essences, en (x, z) depuis le centre de l'emprise, tourné au hasard et à l'un des trois verts.
	 */
	static tree(r, [x, z], scale, essences) {

		const slug = MapSceneDecor.pick(r, MapSceneDecor.ESSENCES[MapSceneDecor.pick(r, essences)]);

		return { slug, x, z, scale, turn: r() * Math.PI * 2, green: Math.floor(r() * MapSceneDecor.GREENS.length) };

	}

	/**
	 * Des places tirées de proche en proche, chacune à côté d'une place déjà prise et plutôt le long d'un axe tiré au hasard,
	 * dans l'ellipse `reach` : la tache est allongée, coudée ou ramassée selon le tirage, jamais un cercle ni une grille.
	 */
	static spots(r, reach, apart, count) {

		const between = (a, b) => MapSceneDecor.between(r, a, b);
		const axis = r() * Math.PI;
		const list = [[between(-1, 1), between(-1, 1)]];

		for(let tries = 0; list.length < count && tries < count * 30; tries++) {

			const [x0, z0] = MapSceneDecor.pick(r, list);
			const a = axis + (r() < .7 ? between(-.6, .6) : between(0, Math.PI)) + (r() < .5 ? Math.PI : 0);
			const d = between(apart, apart * 2);
			const x = x0 + Math.cos(a) * d;
			const z = z0 + Math.sin(a) * d;

			if((x / reach[0]) ** 2 + (z / reach[1]) ** 2 > 1 || list.some(([sx, sz]) => Math.hypot(x - sx, z - sz) < apart)) {
				continue;
			}

			list.push([x, z]);

		}

		return list;

	}

	static grove(r, nature = MapSceneDecor.pick(r, MapSceneDecor.GROVES)) {

		const count = nature.count[0] + Math.floor(r() * (nature.count[1] - nature.count[0] + 1));

		return MapSceneDecor.spots(r, [3.4, 3], nature.apart, count).map(at => MapSceneDecor.tree(r, at, MapSceneDecor.between(r, .7, 1.3), nature.essences));

	}

	// Trois massifs d'une même nature, posés en triangle aux centres de WOOD et qui se touchent : un bois de neuf à vingt-quatre
	// arbres sur 17 m
	static wood(r) {

		const nature = MapSceneDecor.pick(r, MapSceneDecor.GROVES);

		return MapSceneDecor.WOOD.flatMap(([x, z]) => MapSceneDecor.grove(r, nature).map(plant => ({ ...plant, x: plant.x + x, z: plant.z + z })));

	}

	/**
	 * Un élément de la nature, chêne, rocher ou roncier : son modèle cuit seul, à l'échelle 1, tel que `base/scene/model` le décrit. Il n'est
	 * ni tourné au hasard ni reteint, et réserve comme un arbre ce qui touche le sol, pas sa cime. `wild` nomme son modèle.
	 */
	static wild(slug, size) {
		return { family: 'tree', size, wild: slug, plants: () => [{ slug, x: 0, z: 0, scale: 1, turn: 0, green: 0 }] };
	}

	// Deux rangs de trois fruitiers, chacun sorti de son rang d'un demi-mètre au plus, un manquant de temps en temps
	static orchard(r) {

		const plants = [];

		for(let row = 0; row < 2; row++) {
			for(let k = 0; k < 3; k++) {

				if(r() < .12) {
					continue;
				}

				plants.push(MapSceneDecor.tree(r, [(k - 1) * 3.4 + MapSceneDecor.between(r, -.5, .5), (row - .5) * 3.4 + MapSceneDecor.between(r, -.5, .5)], MapSceneDecor.between(r, .7, 1.3), ['fruitier']));

			}
		}

		return plants;

	}

	// Cinq à neuf buissons bas, de tailles inégales
	static thicket(r) {

		return MapSceneDecor.spots(r, [2.6, 2], 1, 5 + Math.floor(r() * 5)).map(([x, z]) => ({ slug: 'buisson', x, z, scale: MapSceneDecor.between(r, .55, 1.15), turn: r() * Math.PI * 2, green: Math.floor(r() * MapSceneDecor.GREENS.length) }));

	}

	// Quatre arbres d'une même essence, de l'avant à l'arrière, à pas un peu inégal
	static alignment(r) {

		const essence = MapSceneDecor.pick(r, ['peuplier', 'peuplier', 'rond']);

		return [0, 1, 2, 3].map(k => MapSceneDecor.tree(r, [MapSceneDecor.between(r, -.35, .35), (k - 1.5) * 3.2 + MapSceneDecor.between(r, -.3, .3)], MapSceneDecor.between(r, .75, 1.3), [essence]));

	}

	// La travée d'une clôture et le piquet qui la ferme
	static fence(model) {
		return { ...MapSceneDecor.FENCES[model], end: MapSceneDecor.FENCES[model].slug +'.bout', varied: false, overlap: 0 };
	}

	// La travée de haie, répétée sans piquet. `mixed` : ses travées se tirent de la graine de l'élément (`hedgeSpans()`), au lieu
	// d'alterner comme celles d'une bande fleurie
	static hedge() {
		return { slug: 'haie', pitch: 3, end: null, varied: false, overlap: .3, mixed: true };
	}

	/**
	 * Ce que chaque travée d'une haie reçoit, d'après sa graine et l'étirement de chacune : sa forme, `slug`, sa place dans une série
	 * de charmille, `end` (-1 la première, 1 la dernière, 0 ailleurs), son sens, son vert parmi GREENS et sa hauteur. Chaque travée
	 * tire six valeurs, qu'elle s'en serve ou non : la travée de rang k reçoit les mêmes quelle que soit la suite de la haie.
	 */
	static hedgeSpans(stretches, seed) {

		const D = MapSceneDecor;
		const r = MapSceneCrops.random(seed);
		const among = (draw, [from, to]) => from + Math.floor(draw * (to - from + 1));
		const count = stretches.length;
		const spans = [];
		let run = null;
		let left = 0;
		let tree = null;

		for(let k = 0; k < count; k++) {

			const [form, length, gap, flip, green, height] = Array.from({ length: 6 }, () => r());

			if(tree === null) {
				tree = among(gap, D.HEDGE_TREE_EVERY);
			}

			if(left === 0) {
				// Une charmille qui commencerait sur la dernière travée n'aurait qu'un bout
				const pool = D.HEDGE_POOL.filter(slug => slug !== run && (slug !== D.HEDGE_WALL || k < count - 1));
				run = pool[Math.floor(form * pool.length)];
				left = among(length, D.HEDGE_RUN);
			}

			left--;
			tree--;

			const planted = (
				tree <= 0 && k > 0 && k < count - 1 && run !== D.HEDGE_WALL &&
				stretches[k] >= D.HEDGE_STRETCH[0] && stretches[k] <= D.HEDGE_STRETCH[1]
			);

			if(planted) {
				tree = among(gap, D.HEDGE_TREE_EVERY);
			}

			spans.push({
				slug: planted ? D.HEDGE_TREE : run,
				end: 0,
				flip: flip < .5,
				green: Math.floor(green * D.GREENS.length),
				height: 1 + (height * 2 - 1) * D.HEDGE_HEIGHT
			});

		}

		// Le bout de charmille tourne sa section étroite vers sa série : la première travée se pose à rebours, la dernière à l'endroit
		spans.forEach((span, k) => {

			if(span.slug !== D.HEDGE_WALL) {
				return;
			}

			if(spans[k - 1]?.slug !== D.HEDGE_WALL) {
				span.end = -1;
				span.flip = true;
			} else if(spans[k + 1]?.slug !== D.HEDGE_WALL) {
				span.end = 1;
				span.flip = false;
			}

		});

		return spans;

	}

	// Un élément dont la haie se tire de sa graine : une haie tracée, ou un enclos qui en porte une
	static hedged(e) {
		return (e.def.enclosure === true) ? (e.hedge || e.model === null) : (e.def.pieces !== undefined && e.def.pieces(e.model).mixed === true);
	}

	// La graine d'une haie neuve, tirée du point où elle commence et de son rang dans le décor : elle part avec la pose, avant que
	// l'élément ait un id, et la haie qu'on a vue se tracer est celle qu'on garde. Un entier positif de 31 bits, ce que la base retient
	static hedgeSeed(p, rank) {
		return (Math.imul(Math.round(p.x * 100), 73856093) + Math.imul(Math.round(p.n * 100), 19349663) + Math.imul(rank, 83492791)) >>> 1;
	}

	// La travée de bande fleurie, répétée comme la haie. Son modèle est couché sur X et centré (`across`) : `spans()` le redresse
	// selon +Z. Le recouvrement de 15 cm à chaque bout ferme le jour entre deux travées
	static strip() {
		return { slug: 'bande-fleurie', pitch: 4, end: null, varied: true, overlap: .15, across: true };
	}

	// Les modèles cuits sans lesquels un kit ne se montre pas. Le portail d'un enclos n'en est pas : sans lui, le passage reste ouvert
	static slugs(def) {

		if(def.model !== undefined) {
			return [def.model];
		}

		if(def.enclosure === true) {
			return Object.keys(MapSceneDecor.FENCES).flatMap(model => [MapSceneDecor.fence(model).slug, MapSceneDecor.fence(model).end]).concat(['haie']);
		}

		if(def.pieces !== undefined) {
			return (def.models ?? [null]).map(model => def.pieces(model)).flatMap(piece => (piece.end === null) ? [piece.slug] : [piece.slug, piece.end]);
		}

		if(def.wild !== undefined) {
			return [def.wild];
		}

		if(def.plants !== undefined) {
			return Object.values(MapSceneDecor.ESSENCES).flat().concat(['buisson']);
		}

		return [];

	}

	// Un élément neuf d'un kit, à sa largeur et à son modèle par défaut ; un tracé n'a pas de ligne tant qu'on n'en a pas posé deux
	// points, ni un enclos de tour tant qu'il ne clôt rien. Ni l'un ni l'autre ne se monte alors dans la scène. `weighed` est ce que
	// sa ligne pèse sous le plafond du décor (`cost()`), rien tant qu'elle n'est pas partie
	static element(kit, p) {

		const def = MapSceneDecor.KITS[kit];

		return {
			layer: MapSceneLayer.BASE, diameter: null, laying: null, seated: null,
			id: null, seed: null, kit, def, p, angle: 0, size: 1, building: null, object: null,
			wide: (def.widths === undefined) ? null : def.widths[1],
			model: (def.models === undefined) ? null : def.models[0],
			trace: null, root: null, pad: null, label: null,
			area: null, ring: null, hedge: false, gate: null, ripples: null, flat: false, weighed: 0
		};

	}

	/**
	 * Les contours fermés, sans répéter leur premier point : leur longueur, et l'abscisse du point le plus proche de `q`.
	 */
	static loopLength(points) {
		return points.reduce((total, q, i) => total + ScenePlanGeometry.length(ScenePlanGeometry.sub(points[(i + 1) % points.length], q)), 0);
	}

	static loopProject(points, q) {

		const G = ScenePlanGeometry;
		let best = { distance: Infinity, s: 0 };
		let s = 0;

		points.forEach((a, i) => {

			const b = points[(i + 1) % points.length];
			const d = G.sub(b, a);
			const length = G.length(d);
			const k = (length < 1e-9) ? 0 : Math.max(0, Math.min(1, G.dot(G.sub(q, a), d) / (length * length)));
			const distance = G.length(G.sub(q, G.lerp(a, b, k)));

			if(distance < best.distance) {
				best = { distance, s: s + k * length };
			}

			s += length;

		});

		return best;

	}

	// L'abscisse du portail ramenée sur un côté qui le tient, à GATE_ROOM mètres des coins comme `gateAt()` la pose : celui sous
	// elle, ou le plus proche le long du tour quand le sien est trop court, sans quoi le passage prendrait le coin. Une emprise
	// dont aucun côté ne le tient le garde au milieu du plus long
	static gateBound(points, s) {

		const total = MapSceneDecor.loopLength(points);
		const at = ((s % total) + total) % total;
		let start = 0;
		let best = null;
		let longest = null;

		for(let i = 0; i < points.length; i++) {

			const length = ScenePlanGeometry.length(ScenePlanGeometry.sub(points[(i + 1) % points.length], points[i]));

			if(longest === null || length > longest.length) {
				longest = { length, s: start + length / 2 };
			}

			if(length >= 2 * MapSceneDecor.GATE_ROOM) {

				const bound = Math.min(Math.max(at, start + MapSceneDecor.GATE_ROOM), start + length - MapSceneDecor.GATE_ROOM);
				const gap = Math.min(Math.abs(bound - at), total - Math.abs(bound - at));

				if(best === null || gap < best.gap) {
					best = { gap, s: bound };
				}

			}

			start += length;

		}

		return (best ?? longest).s;

	}

	// La ligne ouverte du contour de l'abscisse `s0` à `s1`, sommets compris : `s1` au-delà de la longueur fait le tour
	static loopCut(points, s0, s1) {

		const G = ScenePlanGeometry;
		const total = MapSceneDecor.loopLength(points);
		const out = [];
		let s = 0;

		for(let turn = 0; turn < 3 && s < s1; turn++) {

			points.forEach((a, i) => {

				const b = points[(i + 1) % points.length];
				const length = G.length(G.sub(b, a));

				if(length > 1e-9 && s + length >= s0 && s <= s1) {

					if(out.length === 0) {
						out.push(G.lerp(a, b, Math.max(0, (s0 - s) / length)));
					}

					out.push((s + length <= s1) ? b : G.lerp(a, b, (s1 - s) / length));

				}

				s += length;

			});

		}

		return (total > 0) ? out : [];

	}

	/**
	 * Un contour fermé écarté vers l'extérieur de `distance` mètres, ou côté par côté quand `distance` est une liste, le côté i allant
	 * du sommet i au suivant. Chaque sommet est la rencontre des deux côtés écartés ; un coin trop aigu, qui partirait au loin, se
	 * coupe en deux sommets. L'extérieur se lit sur le signe de l'aire, le sens du contour ne compte donc pas.
	 */
	static offsetLoop(points, distance) {

		const G = ScenePlanGeometry;
		const count = points.length;
		const at = i => points[(i + count) % count];
		const gap = i => Array.isArray(distance) ? distance[(i + count) % count] : distance;
		const sign = (MapSceneDecor.area(points) >= 0) ? 1 : -1;

		const side = i => {
			const d = G.norm(G.sub(at(i + 1), at(i)));
			return { d, out: { x: d.n * sign, n: -d.x * sign } };
		};

		const out = [];

		for(let i = 0; i < count; i++) {

			const p = at(i);
			const a = side(i - 1);
			const b = side(i);
			const pa = G.add(p, G.mul(a.out, gap(i - 1)));
			const pb = G.add(p, G.mul(b.out, gap(i)));
			const cross = a.d.x * b.d.n - a.d.n * b.d.x;

			if(Math.abs(cross) < 1e-3) {
				out.push(...((Math.abs(gap(i - 1) - gap(i)) < .05) ? [pb] : [pa, pb]));
				continue;
			}

			const t = ((pb.x - pa.x) * b.d.n - (pb.n - pa.n) * b.d.x) / cross;
			const q = G.add(pa, G.mul(a.d, t));

			if(G.length(G.sub(q, p)) > 3 * Math.max(gap(i - 1), gap(i), .3)) {
				out.push(pa, pb);
			} else {
				out.push(q);
			}

		}

		return out.filter((q, i) => G.length(G.sub(out[(i + 1) % out.length], q)) > .05);

	}

	// L'aire signée d'un contour fermé, positive quand il tourne dans le sens direct du plan
	static area(points) {
		return points.reduce((sum, p, i) => sum + p.x * points[(i + 1) % points.length].n - points[(i + 1) % points.length].x * p.n, 0) / 2;
	}

	static lengthOf(line) {
		return line.slice(1).reduce((total, q, i) => total + ScenePlanGeometry.length(ScenePlanGeometry.sub(q, line[i])), 0);
	}

	// La ligne d'un tracé en coordonnées du plan, ses points étant gardés en écarts à sa place
	static lineOf(e) {
		return e.trace.map(q => ScenePlanGeometry.add(q, e.p));
	}

	// Deux points, l'un ou l'autre absent, ou bien qui se confondent
	static samePoint(a, b) {
		return (a === null || b === null) ? (a === b) : (Math.abs(a.x - b.x) < 1e-6 && Math.abs(a.n - b.n) < 1e-6);
	}

	// Un kit qui se dessine d'un glissé et franchit la portée du terrain. Un tuyau et une rampe se posent au clic, d'une vanne à
	// l'autre, et restent à portée comme les objets
	static freehand(def) {
		return (def.traced === true && def.pipe !== true);
	}

	/**
	 * La ligne d'un glissé réduite aux points dont elle s'écarte de plus de `tolerance` mètres (Ramer-Douglas-Peucker) : les deux
	 * bouts restent, et chaque point retiré est à moins de `tolerance` du segment qui le remplace. La pile tient lieu de récursion.
	 */
	static simplify(line, tolerance) {

		const kept = new Array(line.length).fill(false);
		const spans = [[0, line.length - 1]];

		kept[0] = true;
		kept[line.length - 1] = true;

		while(spans.length > 0) {

			const [from, to] = spans.pop();
			let farthest = { index: null, distance: tolerance };

			for(let i = from + 1; i < to; i++) {

				const distance = ScenePlanGeometry.distanceToSegment(line[i], line[from], line[to]);

				if(distance > farthest.distance) {
					farthest = { index: i, distance };
				}

			}

			if(farthest.index !== null) {
				kept[farthest.index] = true;
				spans.push([from, farthest.index], [farthest.index, to]);
			}

		}

		return line.filter((p, index) => kept[index]);

	}

	/**
	 * Les rangs des points d'une ligne à l'écran qui portent un rond : les bouts, le point choisi, et chaque point à une cible au
	 * moins du dernier rond posé. Un tracé dessiné garde un point par mètre dans ses courbes, dont les ronds se recouvriraient en
	 * vue large : ils s'éclaircissent, et reviennent au zoom.
	 */
	static sparse(screen, size, chosen) {

		let last = null;

		return screen.map((p, index) => index).filter(index => {

			const p = screen[index];
			const shown = (index === 0 || index === screen.length - 1 || index === chosen || Math.hypot(p.x - last.x, p.y - last.y) >= size);

			if(shown) {
				last = p;
			}

			return shown;

		});

	}

	// Deux passes de coupe de part et d'autre de chaque sommet, au quart du segment et à TRACE_STEP / 4 au plus : les virages
	// s'arrondissent sans que les deux bouts bougent, ni qu'un long côté droit se courbe
	static smooth(line) {

		const G = ScenePlanGeometry;

		for(let pass = 0; pass < 2 && line.length > 2; pass++) {

			const next = [line[0]];

			for(let i = 0; i < line.length - 1; i++) {

				const a = line[i];
				const b = line[i + 1];
				const cut = Math.min(.25, MapSceneDecor.TRACE_STEP / 4 / Math.max(1e-6, G.length(G.sub(b, a))));

				if(i > 0) {
					next.push(G.lerp(a, b, cut));
				}

				if(i < line.length - 2) {
					next.push(G.lerp(a, b, 1 - cut));
				}

			}

			next.push(line.at(-1));
			line = next;

		}

		return line;

	}

	/**
	 * Le point où le segment [a, b] coupe le segment [c, d], avec sa place `t` sur le premier et `u` sur le second, de 0 à 1 ; null
	 * quand ils ne se coupent pas ou sont parallèles.
	 */
	static intersection(a, b, c, d) {

		const G = ScenePlanGeometry;
		const r = G.sub(b, a);
		const s = G.sub(d, c);
		const cross = r.x * s.n - r.n * s.x;

		if(Math.abs(cross) < 1e-9) {
			return null;
		}

		const q = G.sub(c, a);
		const t = (q.x * s.n - q.n * s.x) / cross;
		const u = (q.x * r.n - q.n * r.x) / cross;

		return (t >= 0 && t <= 1 && u >= 0 && u <= 1) ? { point: G.lerp(a, b, t), t, u } : null;

	}

	/**
	 * Les rencontres de chaque ligne de `lines` avec les lignes de `others`, toutes en coordonnées du plan : pour chacune, le point,
	 * l'axe de la ligne et celui de l'autre, le sinus de leur angle, l'abscisse `at` sur la ligne et le rang `other` de l'autre, dans
	 * l'ordre de la ligne, avec la longueur `total` de la ligne. La ligne de rang `index` ne rencontre pas l'autre de rang
	 * `own[index]`, elle-même quand elle est des deux listes : chacun de ses segments toucherait ses voisins. La grille est en cases
	 * de BRIDGE_CELL mètres : un segment ne se compare qu'aux segments de ses cases.
	 */
	static meetings(lines, others, own = []) {

		const D = MapSceneDecor;
		const G = ScenePlanGeometry;
		const cell = D.BRIDGE_CELL;
		const cells = new Map();

		// Une case se nomme par un nombre, sa colonne et sa rangée sur une grille plus large que tout plan, et chaque segment la
		// parcourt sans rien allouer : sur 2 700 m de tracés lissés, une clé en texte, un tableau ou un ensemble par segment coûtent
		// plus que les comparaisons qu'ils évitent
		const visit = (a, b, each) => {
			const toX = Math.floor(Math.max(a.x, b.x) / cell);
			const toN = Math.floor(Math.max(a.n, b.n) / cell);
			for(let x = Math.floor(Math.min(a.x, b.x) / cell); x <= toX; x++) {
				for(let n = Math.floor(Math.min(a.n, b.n) / cell); n <= toN; n++) {
					each(x * 1e6 + n);
				}
			}
		};

		others.forEach((line, other) => {

			for(let i = 1; i < line.length; i++) {

				const segment = { a: line[i - 1], b: line[i], other };

				visit(segment.a, segment.b, key => {
					if(cells.has(key)) {
						cells.get(key).push(segment);
					} else {
						cells.set(key, [segment]);
					}
				});

			}

		});

		return lines.map((line, index) => {

			const found = [];
			let along = 0;

			for(let i = 1; i < line.length; i++) {

				const a = line[i - 1];
				const b = line[i];
				const span = Math.hypot(b.x - a.x, b.n - a.n);

				visit(a, b, key => {

					const segments = cells.get(key);

					if(segments === undefined) {
						return;
					}

					for(const segment of segments) {

						if(segment.other === own[index]) {
							continue;
						}

						const met = D.intersection(a, b, segment.a, segment.b);

						if(met !== null) {
							const axis = G.norm(G.sub(b, a));
							const flow = G.norm(G.sub(segment.b, segment.a));
							const sine = Math.abs(axis.x * flow.n - axis.n * flow.x);
							found.push({ point: met.point, axis, flow, sine, at: along + met.t * span, other: segment.other });
						}

					}

				});

				along += span;

			}

			return { found: found.sort((first, second) => first.at - second.at), total: along };

		});

	}

	/**
	 * Les ponts que les tracés marchés `walked` posent sur les cours d'eau `streams`, en coordonnées du plan. Chaque pont est long de
	 * son arche et de `ramps` mètres de rampes, allongées du `ramp` du kit, et prend la couleur `color` de la chaussée. Un croisement
	 * trop rasant n'en fait pas, ni un pont qui dépasserait un bout du chemin, ni un second croisement du même chemin à moins d'une
	 * longueur de pont du précédent, quand il serpente le long de l'eau ou que deux eaux se touchent : celui-là passe sur le premier
	 * pont.
	 */
	static crossings(walked, streams, ramps) {

		const D = MapSceneDecor;
		const G = ScenePlanGeometry;
		const lines = walked.map(e => e.path.map(q => G.add(e.p, q)));
		const waters = streams.map(stream => stream.path.map(q => G.add(stream.p, q)));
		const met = D.meetings(lines, waters);
		const bridges = [];

		walked.forEach((e, index) => {

			const track = (e.wide ?? e.def.wide) + Math.max(...e.def.layers.map(([margin]) => margin));
			const lift = Math.max(...e.def.layers.map(([, height]) => height));
			const ramp = e.def.ramp ?? 1;
			const color = e.def.layers.at(-1)[2];
			const { found, total } = met[index];

			// Un segment d'eau à cheval sur deux cases du segment de chemin y donne deux fois le même croisement, que cette règle
			// réduit à un, comme le second croisement d'un méandre
			let last = null;

			for(const meeting of found) {

				const stream = streams[meeting.other];
				const water = stream.wide ?? stream.def.wide;
				const length = (water + D.BRIDGE_BANK) / Math.max(meeting.sine, D.BRIDGE_SINE) + ramps * ramp;

				if(last !== null && meeting.at - last < length) {
					continue;
				}

				last = meeting.at;

				if(meeting.sine >= D.BRIDGE_SINE && meeting.at >= length / 2 && total - meeting.at >= length / 2) {
					bridges.push({ point: meeting.point, axis: meeting.axis, flow: meeting.flow, sine: meeting.sine, at: meeting.at, water, track, lift, element: e, ramp, length, color });
				}

			}

		});

		return bridges;

	}

	/**
	 * Les carrefours des routes `roads` avec les tracés marchés `walked`, en intervalles [début, fin] d'abscisse sur chaque route où
	 * son marquage s'interrompt. Chaque ligne est prolongée d'une demi-largeur à ses deux bouts : une route qui s'arrête au bord
	 * d'une autre en coupe le milieu, comme un T. Deux tracés qui se touchent bout à bout, et eux seuls, se continuent l'un l'autre
	 * et ne font pas de carrefour ; trois ou quatre tracés partis du même point en font un. Un croisement oblique écarte le marquage
	 * de la largeur de l'autre tracé, en long, et du coin que la chaussée de la route y fait, sinus borné à JUNCTION_SINE.
	 */
	static junctions(roads, walked) {

		const D = MapSceneDecor;
		const G = ScenePlanGeometry;
		const thick = e => (e.wide ?? e.def.wide) + Math.max(...e.def.layers.map(([margin]) => margin));

		const extend = e => {

			const line = e.path.map(q => G.add(e.p, q));
			const reach = thick(e) / 2;
			const start = G.norm(G.sub(line[0], line[1]));
			const end = G.norm(G.sub(line.at(-1), line.at(-2)));

			return [G.add(line[0], { x: start.x * reach, n: start.n * reach }), ...line, G.add(line.at(-1), { x: end.x * reach, n: end.n * reach })];

		};

		// Un tracé finit au point quand l'un de ses bouts, avant prolongement, y est à moins d'une demi-case de la grille de pose, où
		// les clics se calent : deux tracés tirés l'un au bout de l'autre partagent le même point. Le bout d'une route qui dépasse
		// d'une case celle qui la rejoint n'y est plus, et le carrefour reste
		const ends = (e, point) => [e.path[0], e.path.at(-1)].some(q => G.length(G.sub(G.add(e.p, q), point)) < D.SNAP / 2);
		// Deux tracés seuls à finir au même point se continuent ; un troisième qui en part fait un T, ou une croix
		const continued = point => walked.filter(e => ends(e, point)).length === 2;

		const lines = walked.map(extend);
		const ranks = roads.map(e => walked.indexOf(e));
		const met = D.meetings(ranks.map(rank => lines[rank]), lines, ranks);

		return roads.map((e, index) => {

			const own = thick(e);
			const reach = own / 2;

			return met[index].found
				.filter(meeting => ends(e, meeting.point) === false || ends(walked[meeting.other], meeting.point) === false || continued(meeting.point) === false)
				.map(meeting => {
					const cosine = Math.abs(meeting.axis.x * meeting.flow.x + meeting.axis.n * meeting.flow.n);
					const clear = (thick(walked[meeting.other]) / 2 + cosine * own / 2) / Math.max(meeting.sine, D.JUNCTION_SINE);
					return [meeting.at - reach - clear, meeting.at - reach + clear];
				});

		});

	}

	/**
	 * Étire par tranches les sommets `positions` du pont, le long de son axe en x et en travers en z : ce qui est entre les pieds de
	 * l'arche, à ±`foot`, s'ouvre jusqu'à `chord`, et le reste, rampes et bornes, s'allonge de `ramp` à partir des pieds. Les deux
	 * moitiés en travers s'écartent de `shift` chacune. Une mise à l'échelle épaissirait les parapets d'une route et pincerait l'arche
	 * d'un chemin étroit en ogive.
	 */
	static shapeBridge(positions, foot, chord, shift, ramp = 1) {

		const open = chord / (2 * foot);

		for(let i = 0; i < positions.length; i += 3) {

			const x = positions[i];
			const z = positions[i + 2];

			positions[i] = (Math.abs(x) <= foot) ? x * open : Math.sign(x) * (chord / 2 + (Math.abs(x) - foot) * ramp);
			positions[i + 2] = z + Math.sign(z) * shift;

		}

		return positions;

	}

	// Les piquets d'une ligne : ses deux bouts, le sommet le plus coudé de chaque virage marqué, et d'autres entre eux au plus près du pas
	static posts(line, pitch) {

		const G = ScenePlanGeometry;
		const lengths = [0];

		for(let i = 1; i < line.length; i++) {
			lengths.push(lengths[i - 1] + G.length(G.sub(line[i], line[i - 1])));
		}

		const turn = i => {
			const a = G.mul(G.sub(line[i], line[i - 1]), 1 / Math.max(1e-6, lengths[i] - lengths[i - 1]));
			const b = G.mul(G.sub(line[i + 1], line[i]), 1 / Math.max(1e-6, lengths[i + 1] - lengths[i]));
			return Math.acos(Math.max(-1, Math.min(1, G.dot(a, b))));
		};

		const bends = [];

		for(let i = 1; i < line.length - 1; i++) {

			if(turn(i) < .02) {
				continue;
			}

			const last = bends.at(-1);

			if(last !== undefined && lengths[i] - lengths[last.at(-1)] < MapSceneDecor.BEND_REACH) {
				last.push(i);
			} else {
				bends.push([i]);
			}

		}

		const keys = [0];

		for(const bend of bends) {
			if(bend.reduce((sum, i) => sum + turn(i), 0) >= MapSceneDecor.BEND) {
				keys.push(bend.reduce((best, i) => (turn(i) > turn(best)) ? i : best));
			}
		}

		keys.push(line.length - 1);

		const at = s => {
			const i = Math.max(1, lengths.findIndex(l => l >= s));
			return G.lerp(line[i - 1], line[i], (s - lengths[i - 1]) / Math.max(1e-6, lengths[i] - lengths[i - 1]));
		};

		const posts = [line[0]];

		for(let k = 1; k < keys.length; k++) {

			const s0 = lengths[keys[k - 1]];
			const s1 = lengths[keys[k]];
			const count = Math.max(1, Math.ceil((s1 - s0) / pitch - .15));

			for(let j = 1; j < count; j++) {
				posts.push(at(s0 + (s1 - s0) * j / count));
			}

			posts.push(line[keys[k]]);

		}

		return posts;

	}

	/**
	 * Le tour fermé d'une ligne, à `half` de part et d'autre et arrondi à chaque bout comme la bande de `strip()` : le côté gauche
	 * à l'aller, le bout d'arrivée, le côté droit au retour, puis le bout de départ. Le contour revient à son premier point.
	 */
	static around(line, half) {

		const G = ScenePlanGeometry;
		const last = line.length - 1;
		const left = d => ({ x: -d.n, n: d.x });

		const side = sign => line.map((q, i) => {

			const into = G.norm(G.sub(line[Math.max(1, i)], line[Math.max(0, i - 1)]));
			const out = G.norm(G.sub(line[Math.min(i + 1, last)], line[Math.min(i, last - 1)]));
			const tangent = G.norm(G.add(into, out));
			const scale = 1 / Math.max(.5, tangent.x * into.x + tangent.n * into.n);

			return G.add(q, G.mul(left(tangent), sign * half * scale));

		});

		// Un demi-tour autour du bout `q`, d'un côté à l'autre en passant devant lui dans la direction `d`
		const cap = (q, d) => Array.from({ length: 7 }, (_, k) => {
			const angle = Math.PI * (k + 1) / 8;
			return G.add(q, G.add(G.mul(left(d), half * Math.cos(angle)), G.mul(d, half * Math.sin(angle))));
		});

		const loop = [
			...side(1),
			...cap(line[last], G.norm(G.sub(line[last], line[last - 1]))),
			...side(-1).reverse(),
			...cap(line[0], G.norm(G.sub(line[0], line[1])))
		];

		return [...loop, loop[0]];

	}

	// L'emprise d'une ligne, un rectangle par segment, de `half` de part et d'autre et prolongé de `half` à chaque bout pour couvrir
	// le raccord arrondi
	static segments(line, half) {

		const G = ScenePlanGeometry;

		return line.slice(1).map((b, i) => {

			const a = line[i];
			const length = G.length(G.sub(b, a));
			const u = (length > 1e-6) ? G.mul(G.sub(b, a), 1 / length) : { x: 0, n: 1 };
			const w = { x: -u.n, n: u.x };

			return { origin: G.sub(a, G.add(G.mul(u, half), G.mul(w, half))), w, u, width: 2 * half, length: length + 2 * half };

		});

	}

	/**
	 * L'emprise d'un élément en rectangles, chacun avec sa boite, et la boite de l'ensemble : le rectangle de l'élément, un par
	 * segment de la ligne d'un tracé ou du tour d'un enclos, ou un par pied d'un kit d'arbres, réduit à son tronc avec `trunk`. Les
	 * boites écartent sans le test des quatre axes ce qui ne touche pas un long tracé.
	 */
	static footprint(e, grow = 0, trunk = false) {

		let rectangles;

		if(e.path !== undefined) {
			rectangles = MapSceneDecor.segments(e.path.map(q => ScenePlanGeometry.add(e.p, q)), e.thick / 2 + grow);
		} else if(e.feet !== undefined && trunk) {

			// Un massif de buissons n'a pas de tronc : le centre de ses cimes en tient lieu
			const trunks = e.feet.filter(foot => foot.trunk !== null);

			if(trunks.length > 0) {
				rectangles = trunks.map(foot => MapSceneDecor.trunk(e, foot));
			} else {
				rectangles = [MapSceneDecor.trunk(e, { x: 0, z: 0, trunk: Math.max(MapSceneDecor.TRUNK_MIN / 2, MapSceneDecor.TRUNK_SHARE * e.width / e.size / 2) })];
			}

		} else if(e.feet !== undefined) {
			rectangles = e.feet.map(foot => MapSceneDecor.square(e, foot, grow));
		} else {
			rectangles = [MapSceneDecor.rectangle(e, grow)];
		}

		return MapSceneDecor.spread(rectangles);

	}

	// Une emprise faite de rectangles du plan, chacun avec sa boite, et la boite de l'ensemble
	static spread(rectangles) {

		const pieces = rectangles.map(rectangle => ({ rectangle, box: MapSceneDecor.extent(MapSceneDecor.corners(rectangle)) }));

		return {
			pieces,
			box: {
				x0: Math.min(...pieces.map(piece => piece.box.x0)),
				x1: Math.max(...pieces.map(piece => piece.box.x1)),
				n0: Math.min(...pieces.map(piece => piece.box.n0)),
				n1: Math.max(...pieces.map(piece => piece.box.n1))
			}
		};

	}

	/**
	 * Un enclos et une route, en rectangles du plan : la clôture et la haie de l'un, ouvertes devant le portail, et la chaussée de
	 * l'autre. Une route bute sur ce que l'enclos dresse, et non sur son emprise `path`, qui couvre aussi ce qu'il clôt. Celle qui
	 * finit devant le portail y arrive comme à une barrière, plus large que lui : son bout s'arrête au portail au lieu de le
	 * dépasser de sa demi-largeur, et le tour s'ouvre pour elle de toute sa largeur. Partout ailleurs, elle bute.
	 */
	static meeting(e, road) {

		const D = MapSceneDecor;
		const G = ScenePlanGeometry;
		const half = road.thick / 2;
		const line = road.path.map(q => G.add(road.p, q));

		// La question se pose à chaque mouvement du pointeur : une route loin du tour n'a pas à faire découper ses lignes. La haie
		// s'écarte du tour de sa largeur et de son jeu, et deux fois cela couvre l'onglet d'un coin aigu
		const reach = 2 * (D.HEDGE_WIDTH + D.HEDGE_GAP);
		const ring = D.extent(e.ring.map(q => G.add(e.p, q)));
		const far = D.spread(D.segments(line, half)).box;

		if(D.apart(far, { x0: ring.x0 - reach, x1: ring.x1 + reach, n0: ring.n0 - reach, n1: ring.n1 + reach })) {
			return { enclosed: D.spread([]), road: D.spread([]) };
		}

		const total = D.loopLength(e.ring);
		const gate = G.add(e.p, D.loopCut(e.ring, e.gate * total, e.gate * total + .01)[0]);

		let opening = D.opening(e);

		if(G.length(G.sub(line[0], gate)) < G.length(G.sub(line[line.length - 1], gate))) {
			line.reverse();
		}

		// Un bout tracé à la main ne tombe pas pile sur le portail : il y arrive à un mètre près, en long comme en travers
		const slack = 1;

		// La ligne lissée se découpe en pas courts : le bout recule de sa demi-largeur le long d'elle, pas sur son dernier pas
		if(G.length(G.sub(line[line.length - 1], gate)) <= half + slack && D.lengthOf(line) > half) {

			let rest = half;

			while(G.length(G.sub(line[line.length - 1], line[line.length - 2])) <= rest) {
				rest -= G.length(G.sub(line[line.length - 1], line[line.length - 2]));
				line.pop();
			}

			const along = G.sub(line[line.length - 1], line[line.length - 2]);
			line[line.length - 1] = G.sub(line[line.length - 1], G.mul(along, rest / G.length(along)));

			// `segments()` prolonge chaque ligne coupée de sa demi-épaisseur dans l'ouverture : le passage en perd une épaisseur entière
			const cap = Math.max(e.hedge ? D.HEDGE_WIDTH : 0, (e.model === null) ? 0 : D.KITS.cloture.thick);
			opening = Math.max(opening, road.thick + cap + 2 * slack);

		}

		const lines = D.enclosureLines(e, opening);
		const absolute = points => points.map(q => G.add(e.p, q));

		return {
			enclosed: D.spread([
				...(lines.fence.length >= 2) ? D.segments(absolute(lines.fence), D.KITS.cloture.thick / 2) : [],
				...(lines.hedge.length >= 2) ? D.segments(absolute(lines.hedge), D.HEDGE_WIDTH / 2) : []
			]),
			road: D.spread(D.segments(line, half))
		};

	}

	static touches(a, b) {
		return MapSceneDecor.apart(a.box, b.box) === false && a.pieces.some(x => b.pieces.some(y => MapSceneDecor.apart(x.box, y.box) === false && MapSceneDecor.overlaps(x.rectangle, y.rectangle)));
	}

	/**
	 * Le dessin du décor, sans écran : les éléments enregistrés, posés sous `parent`, puis le décor par défaut (`byDefault()`).
	 * `diameterLabel` est le gabarit de la pastille d'un tuyau, ou null pour une scène qui ne les montre pas. `changed()` dit que
	 * l'ombre est à refaire, `walked()` que le réseau de marche des paysans a changé. `calendar` est l'année des arbres (`calendar()`).
	 * La carte des calques, la feuille de pose et les gestes s'y ajoutent par `attach()`.
	 */
	constructor({ socle, plan, geometry, parent, gradientMap, diameterLabel, changed, walked, calendar = MapSceneDecor.calendar() }) {

		const { THREE, addons } = socle;

		this.THREE = THREE;
		this.addons = addons;
		this.figure = socle.figure;
		this.baked = socle.baked;
		this.geometry = geometry;
		this.parent = parent;
		this.diameterLabel = diameterLabel;
		this.changed = changed;
		this.walked = walked;
		this.calendar = calendar;
		this.attached = false;
		// Le sol se creuse déjà sous les étendues d'eau au montage, avant qu'aucun écran n'écoute
		this.carved = () => {};
		this.boards = [];
		this.sign = [];
		this.removals = [];
		this.walkQueued = false;
		this.bridgesQueued = false;
		this.bridges = null;
		this.stripes = null;
		this.grid = null;
		this.spreadQueued = true;
		this.spreadRetried = false;
		this.spreadSize = null;
		this.tracing = null;
		this.closed = false;
		// Le message que redit la pose d'une page qui n'écrit plus, faute de savoir ce que la base porte : null tant qu'elle écrit
		this.frozen = null;

		// `MapScene.draw()` lit le terrain du plan par `readTerrain()` avant de monter le décor : chaque valeur a sa couleur
		this.terrain = { ...plan.terrain };

		// La saison du plan accompagne l'écriture du terrain d'une parcelle : toutes les actions de la page de la parcelle la lisent
		// (`SeasonLib::getOnline()`), qui refuse une saison absente
		this.planSeason = plan.season;

		// Le terrain de chaque parcelle, dont une valeur null reprend celle de la structure
		this.zoneTerrains = new Map(plan.zones.map(zone => [zone.id, { grass: zone.decorGrass, soil: zone.decorSoil, meadow: zone.decorMeadow }]));

		this.material = new THREE.MeshToonMaterial({ vertexColors: true, gradientMap });
		this.depthMaterial = new THREE.MeshDepthMaterial({ depthPacking: THREE.RGBADepthPacking });
		this.xray = new THREE.MeshBasicMaterial({ color: MapSceneDecor.PIPE_COLOR, transparent: true, opacity: MapSceneDecor.THROUGH, depthTest: false, depthWrite: false });
		this.seasonize();
		this.rippleGeometry = new THREE.RingGeometry(.85, 1, 24).rotateX(-Math.PI / 2);
		this.geometries = new Map();
		this.elements = [];
		this.fallback = [];
		// Les éléments que la scène masque le temps d'un effet (`MapSceneRuins`) : leur copie se montre à leur place, et leur calque
		// ne les rallume pas
		this.ruined = new Set();

		// Les bâtiments composés par la structure et leur fabrique (`building.js`) : une scène qui ne montre pas une structure,
		// celle d'une commune, n'a ni les uns ni l'autre. Chaque bâtiment se monte une fois, partagé par ses poses
		this.buildings = new Map((plan.buildings ?? []).map(building => [building.id, building]));
		this.buildingBuild = socle.building?.build ?? null;
		this.buildingGeometries = new Map();

		// Un bâtiment qui montre un modèle 3D téléversé (`upload`) se monte par sa composition le temps de lire le binaire, puis
		// par le modèle (`readUpload()`). Les binaires se gardent par adresse, null pendant la lecture
		this.buildingUploaded = socle.building?.uploaded ?? null;
		this.uploadBuffers = new Map();
		this.buildings.forEach(building => this.readUpload(building));

		// Les éléments importés par la structure, montés comme un bâtiment téléversé, sous la famille de leur rangée, et partagés par
		// leurs poses
		this.objects = new Map((plan.objects ?? []).map(object => [object.id, object]));
		this.objectGeometries = new Map();
		this.objects.forEach(object => this.readObjectUpload(object));

		// La projection inverse de `ScenePlanGeometry` : de la place dans la scène aux coordonnées enregistrées
		const [lng0, lat0] = plan.center;
		this.origin = { lng0, lat0, kx: 111320 * Math.cos(lat0 * Math.PI / 180), ky: 110540 };

		// Ce que rien ne recouvre : les planches, et les serres entières, couverture comprise. Le tour d'un enclos s'en écarte dès le
		// chargement
		this.feetBySlug = new Map();
		this.blockers = geometry.beds
			.concat([...geometry.plots.values()].filter(({ plot }) => plot.mode === 'greenhouse').map(({ rectangle }) => rectangle))
			.map(rectangle => ({ rectangle: { origin: rectangle.origin, w: rectangle.w, u: rectangle.u, width: rectangle.width, length: rectangle.length }, box: MapSceneDecor.extent(MapSceneDecor.corners(rectangle)) }));

		this.areas = this.getAreas(plan);

		this.layers = new MapSceneLayer({ plan });
		this.shownLayer = MapSceneLayer.BASE;

		for(const row of plan.decor) {

			const def = MapSceneDecor.KITS[row.kit];

			// Un kit inconnu, ou dont un modèle n'est pas cuit, se garde sans se montrer, comme une ligne dont la forme n'est pas celle
			// du kit : un tracé sans ses points, un enclos sans son emprise, une largeur sur ce qui n'en a pas. Le serveur ne pèse pas une
			// ligne qui a une largeur sous le plafond de longueur : une haie qui en porterait une se dessinerait sans compter
			if(
				def === undefined || this.available(def) === false ||
				(def.building === true && this.buildingGeometry(row.building) === null) ||
				(def.object === true && this.objects.has(row.object) === false) ||
				(def.traced === true) !== (row.trace !== null) ||
				(def.enclosure === true) !== (row.zone !== null || row.plot !== null) ||
				(row.width !== null && def.widths === undefined)
			) {
				continue;
			}

			// Le serveur ne confronte pas `kit` au catalogue : une largeur qui n'est pas celle du kit prend celle par défaut
			const e = {
				...MapSceneDecor.element(row.kit, null),
				id: row.id,
				seed: row.seed,
				angle: row.angle,
				size: row.size,
				building: row.building,
				object: row.object
			};

			// Un élément dont le calque a disparu revient au calque de base. Un tuyau sans diamètre ni pose prend ceux par défaut
			e.layer = this.layers.of(row.layer);
			e.diameter = row.diameter;
			e.laying = row.laying;

			if(def.widths !== undefined && def.widths.includes(row.width)) {
				e.wide = row.width;
			}

			// Un modèle étranger au kit ne prend pas celui par défaut : l'écran montrerait une clôture que la base ne porte pas
			if(def.models !== undefined) {

				if(def.models.includes(row.model) === false) {
					continue;
				}

				e.model = row.model;

			}

			if(def.enclosure === true) {

				// Le tour se tire de l'emprise à chaque lecture : une emprise retracée emmène son enclos
				const area = this.areas.find(item => item.kind === ((row.zone !== null) ? 'zone' : 'plot') && item.id === (row.zone ?? row.plot));

				if(area === undefined) {
					continue;
				}

				e.hedge = row.hedge;
				e.gate = row.gate;
				this.enclose(e, area);

			} else if(row.trace === null) {
				e.p = geometry.project(row.coordinates);
			} else {
				// Chaque point se projette avec le décalage de son îlot : le centre se recalcule sur eux
				this.lay(e, row.trace.map(geometry.project));
			}

			this.build(e);
			this.elements.push(e);

		}

		// Une vanne se lit avant le tuyau qui la porte quand elle a été posée la première
		this.fitValves();
		this.changed();

	}

	/**
	 * Le décor dans l'écran du plan : la carte des calques et ce que l'utilisateur y a masqué, les pastilles qui suivent la vue, et
	 * la feuille de pose pour qui peut poser. `crew` est le groupe des paysans, qu'un clic choisit comme un élément ; `switched()`
	 * dit que la pose s'ouvre ou se ferme, `terrain(terrain)` qu'elle a changé l'herbe ou la terre, `zoneTerrain(zone, terrain)`
	 * qu'elle a changé celles d'une parcelle, `carved()` qu'un creux a changé le sol.
	 */
	attach({ container, canvas, camera, controls, crew, switched, terrain, zoneTerrain, carved }) {

		this.container = container;
		this.canvas = canvas;
		this.camera = camera;
		this.controls = controls;
		this.crew = crew;
		this.switched = switched;
		this.paintTerrain = terrain;
		this.paintZoneTerrain = zoneTerrain;
		this.carved = carved;
		this.attached = true;

		this.layers.attach({
			container,
			changed: () => this.layersChanged(),
			removed: id => this.removeLayer(id)
		});

		// Les éléments se dessinent tous affichés : ce que l'utilisateur a masqué se lit avec la carte
		this.layersChanged();

		// Les pastilles des diamètres se lisent sans la feuille de pose : elles se replacent pour tous quand la vue bouge
		this.onSpread = () => {
			this.spreadQueued = true;
		};

		controls.addEventListener('change', this.onSpread);

		this.sheet = container.qs('[data-scene-decor-sheet]');

		if(this.sheet !== null) {
			this.bind();
		}

	}

	/**
	 * La géométrie d'un modèle cuit, fondue en une pièce, au vert `green` quand c'est un arbre. Un modèle d'une pièce se recentre
	 * sur son emprise, un arbre garde son tronc à l'origine. Une géométrie sert tous les éléments qui la demandent.
	 */
	base(slug, green, centered) {

		const key = slug +'/'+ green +'/'+ centered;

		if(this.geometries.has(key) === false) {

			const geometry = this.figure.prop(this.baked, slug, this.material).geometry;

			if(centered) {
				const [min, max] = this.baked.index.models[slug].box;
				geometry.translate(-(min[0] + max[0]) / 2, 0, -(min[2] + max[2]) / 2);
			}

			if(green > 0) {
				this.tint(geometry, green);
			}

			// La fusion garde l'ordre et le nombre de sommets des pièces cuites : une pièce `fruit` y occupe une plage, qu'un clone
			// emporte dans `userData`. De même le bois et la tête d'un arbre de haie, pièces `arbre` et `feuillage`, que `spans()`
			// pose à part autour de l'ancre `arbre` du modèle : un modèle sans cette ancre n'est pas un tronçon, et garde ses pièces
			const root = this.baked.index.models[slug].anchors?.arbre ?? null;
			let from = 0;

			geometry.userData.fruits = [];
			geometry.userData.trees = [];
			geometry.userData.root = root;

			for(const part of this.baked.index.models[slug].parts) {

				if(part.name === 'fruit') {
					geometry.userData.fruits.push([from, from + part.count]);
				}

				if(root !== null && (part.name === 'arbre' || part.name === 'feuillage')) {
					geometry.userData.trees.push([from, from + part.count]);
				}

				from += part.count;

			}

			this.mark(geometry, 0);

			this.geometries.set(key, geometry);

		}

		return this.geometries.get(key);

	}

	tint(geometry, green) {

		const THREE = this.THREE;
		const color = geometry.attributes.color;
		const from = MapSceneDecor.FOLIAGE.map(hex => new THREE.Color(hex));
		const to = MapSceneDecor.GREENS[green].map(([a, b, k]) => new THREE.Color(a).lerp(new THREE.Color(b), k));
		const value = new THREE.Color();

		for(let i = 0; i < color.count; i++) {

			value.fromBufferAttribute(color, i);

			const found = from.findIndex(foliage => Math.abs(foliage.r - value.r) + Math.abs(foliage.g - value.g) + Math.abs(foliage.b - value.b) < 1e-4);

			if(found !== -1) {
				color.setXYZ(i, to[found].r, to[found].g, to[found].b);
			}

		}

	}

	/**
	 * Ce que la saison fait du décor, sur son matériau et sur celui de son ombre, d'après l'attribut `season` que `mark()` pose sur
	 * chaque sommet. La teinte d'automne remplace celle de la feuille au lieu de la multiplier : un vert clair multiplié par un roux
	 * donnerait un orange vif, un vert sombre un brun. La feuille n'y garde qu'une part de sa clarté, rapportée à celle du vert d'été,
	 * et un écart de teinte borné : un vert jaune donne un brun plus ocre, un vert profond un brun plus rouge. L'ombre se rétrécit
	 * avec les facettes du houppier, et les autres teintes de saison se rapportent à celles du plein été, où elles valent un blanc.
	 */
	seasonize() {

		const THREE = this.THREE;
		const D = MapSceneDecor;

		// `decorAutumn` porte la teinte d'automne du jour et, en w, les jours écoulés depuis le 1er octobre, négatifs avant, que `glide()`
		// avance d'une fraction de jour pendant la lecture à partir de `turn`, ceux du jour montré. `decorSpring` compte de même les jours
		// depuis la pousse, à partir de `spring`
		this.turn = -366;
		this.spring = 366;
		this.seasonUniforms = {
			decorAutumn: { value: new THREE.Vector4(0, 0, 0, -366) },
			decorFoliage: { value: new THREE.Color(1, 1, 1) },
			decorHedge: { value: new THREE.Color(1, 1, 1) },
			decorPath: { value: new THREE.Color(1, 1, 1) },
			decorSpring: { value: 366 }
		};

		const calendar = this.calendar;
		const float = MapSceneDecor.float;
		const tinted = (calendar.tint !== null);

		const head = 'uniform vec4 decorAutumn;\nuniform vec3 decorFoliage;\nuniform vec3 decorHedge;\nuniform vec3 decorPath;\nvarying vec3 vSeason;\nvarying float vDecorLeft;\n';
		const tintHead = tinted ? 'uniform float decorSpring;\n'+ this.tintShader() : '';
		const leaf = 'abs(vSeason.x - '+ D.SEASON_LEAF +'.) < .5';
		const fruit = 'abs(vSeason.x - '+ D.SEASON_FRUIT +'.) < .5';
		const shed = 'vDecorLeft <= 0.';

		// Chaque écart du calendrier à celui du plan s'écrit à part, pour que le plan garde son nuanceur à l'octet près
		const isFruit = `(abs(season.x - ${D.SEASON_FRUIT}.) < .5)`;
		const { grow, fall, fruit: fruits } = calendar;
		const fade = (fall.fade / fall.days).toFixed(6);
		const late = (fruits.share === 1) ? 'season.y' : `(season.y * (${isFruit} ? ${float(fruits.share)} : 1.))`;
		const falling = (start, days) => `(decorAutumn.w - ${late} - ${float(start)}) / ${float(days)}`;
		const fallen = (fruits.fall === null) ? falling(fall.from, fall.days) : `${isFruit} ? ${falling(fruits.fall.from, fruits.fall.days)} : ${falling(fall.from, fall.days)}`;
		const growing = (fruits.days === grow.days) ? float(grow.days) : `(${isFruit} ? ${float(fruits.days)} : ${float(grow.days)})`;

		// Ce qui reste d'une feuille ou d'un fruit, de 0 à 1 au printemps puis de 1 à 0 à l'automne, et la forme qui grandit ou se
		// rétrécit autour de son point d'attache. Le rang de chute place le début du rétrécissement sur la part des LEAF_FALL_DAYS qui
		// précède le fondu : la première facette commence le premier jour de la chute, la dernière finit le dernier, comme quand elles
		// tombaient d'un coup. Au printemps, un arbre pousse d'un bloc, et ses fruits de même, FRUIT_ON - LEAF_ON jours plus tard
		const shrink = `vDecorLeft = 1.;
			if(abs(season.x - ${D.SEASON_LEAF}.) < .5 || abs(season.x - ${D.SEASON_FRUIT}.) < .5) {
				float fall = ${fallen};
				float from = season.z * (1. - ${fade});
				float spring = decorSpring - (${isFruit} ? ${float(fruits.from - MapSceneCrops.LEAF_ON)} : ${float(grow.from - MapSceneCrops.LEAF_ON)});
				float grow = smoothstep(${late}, ${late} + ${growing}, spring);
				vDecorLeft = grow * (1. - smoothstep(from, from + ${fade}, fall));
				transformed = anchor + (transformed - anchor) * vDecorLeft;
			}`;

		// La teinte d'un calendrier qui en porte une multiplie la feuille au jour de l'année, moins la part du retard que prend le virage
		const tint = `diffuseColor.rgb *= decorTint(clamp(decorSpring + ${float(MapSceneCrops.LEAF_ON)} - vSeason.y * ${float(calendar.turn.share)}, 0., 365.));`;
		const season = tinted ? tint : `float warm = clamp((vColor.r / max(vColor.g, .001) - .2) / .16, -1., 1.);
					vec3 autumn = decorAutumn.rgb * mix(1., dot(vColor.rgb, vec3(.3, .6, .1)) / .18, .55) * vec3(1., 1. + .12 * warm, 1. - .1 * warm);
					// La teinte de saison est celle d'une feuille verte : ce qui tire sur le bleu autant que sur le vert garde sa couleur
					vec3 foliage = (vColor.g > vColor.r * ${D.LEAF_GREEN} && vColor.g > vColor.b) ? decorFoliage : vec3(1.);
					diffuseColor.rgb = mix(diffuseColor.rgb * foliage, autumn, smoothstep(0., ${float(calendar.turn.days)}, decorAutumn.w - vSeason.y));`;

		// Deux décors aux calendriers différents ne partagent pas un programme : celui du plan garde la clé qu'il a toujours eue
		const print = JSON.stringify(calendar);
		const key = (print === JSON.stringify(MapSceneDecor.calendar())) ? '' : ':'+ print;

		const vertex = shader => {
			Object.assign(shader.uniforms, this.seasonUniforms);
			shader.vertexShader = 'uniform vec4 decorAutumn;\nuniform float decorSpring;\nattribute vec3 season;\nattribute vec3 anchor;\nvarying vec3 vSeason;\nvarying float vDecorLeft;\n'+ shader.vertexShader.replace('#include <begin_vertex>', '#include <begin_vertex>\nvSeason = season;\n'+ shrink);
		};

		this.material.onBeforeCompile = shader => {

			vertex(shader);

			shader.fragmentShader = head + tintHead + shader.fragmentShader.replace('#include <color_fragment>', `#include <color_fragment>
				if(${leaf}) {
					if(${shed}) discard;
					${season}
				} else if(${fruit}) {
					if(${shed}) discard;
				} else if(abs(vSeason.x - ${D.SEASON_HEDGE}.) < .5) {
					diffuseColor.rgb *= decorHedge;
				} else if(abs(vSeason.x - ${D.SEASON_PATH}.) < .5) {
					diffuseColor.rgb *= decorPath;
				}`);

		};

		this.material.customProgramCacheKey = () => 'map-scene-decor'+ key;

		this.depthMaterial.onBeforeCompile = shader => {

			vertex(shader);

			shader.fragmentShader = head + shader.fragmentShader.replace('void main() {', `void main() {
				if((${leaf} || ${fruit}) && ${shed}) discard;`);

		};

		this.depthMaterial.customProgramCacheKey = () => 'map-scene-decor-depth'+ key;

	}

	/**
	 * Pose sur chaque sommet ce que la saison en fait, en [sorte, retard, rang de chute] : `kind` sur les sommets d'une feuille et 0
	 * ailleurs, sauf pour un chemin, teint en entier. Les fruits d'un arbre caduc, dont `base()` a noté les sommets, tombent avec lui, et
	 * ceux d'une haie aux mêmes dates, sur un feuillage qui reste. `late` est le retard de l'arbre, en jours, et le rang de chute se tire de `seed`
	 * une fois par triangle. Les sommets d'une géométrie indexée appartiennent à plusieurs triangles et tirent chacun le leur.
	 *
	 * Pose aussi, dans `anchor`, le point vers lequel chacun se rétrécit en tombant : le centre de sa facette pour une feuille, le centre
	 * du fruit pour un fruit, qui prend le rang de sa première facette et tombe d'un bloc. Toute géométrie marquée le porte, pour que
	 * `mergeGeometries()` trouve partout les mêmes attributs. Une géométrie indexée n'a pas de facette à elle : chaque sommet y est son
	 * propre point d'attache, et une feuille qui s'y trouverait disparaitrait d'un coup, à la fin de son fondu. `crown` donne à toutes
	 * les feuilles le centre de leur boite pour point d'attache (`calendar().crown`).
	 */
	mark(geometry, kind, late = 0, seed = 0, crown = false) {

		const color = geometry.attributes.color;
		const position = geometry.attributes.position;
		const count = position.count;
		const season = new Float32Array(count * 3);
		const anchor = new Float32Array(count * 3);
		const draw = MapSceneCrops.random(seed);
		const whole = (kind === MapSceneDecor.SEASON_PATH);
		const fruits = (kind === MapSceneDecor.SEASON_LEAF || kind === MapSceneDecor.SEASON_HEDGE) ? geometry.userData.fruits : [];
		const indexed = (geometry.index !== null);
		let drop = 0;
		let fruitDrop = 0;

		for(let i = 0; i < count; i++) {

			if(indexed || i % 3 === 0) {
				drop = draw();
			}

			const fruit = fruits.find(([from, to]) => i >= from && i < to);

			if(fruit !== undefined && i === fruit[0]) {
				fruitDrop = drop;
			}

			const leafy = (whole || (kind !== 0 && color.getY(i) > color.getX(i) * MapSceneDecor.LEAF_GREEN));

			season.set([fruit !== undefined ? MapSceneDecor.SEASON_FRUIT : (leafy ? kind : 0), late, (fruit !== undefined) ? fruitDrop : drop], i * 3);

		}

		const center = (from, to) => {

			let x = 0;
			let y = 0;
			let z = 0;

			for(let i = from; i < to; i++) {
				x += position.getX(i);
				y += position.getY(i);
				z += position.getZ(i);
			}

			for(let i = from; i < to; i++) {
				anchor.set([x / (to - from), y / (to - from), z / (to - from)], i * 3);
			}

		};

		// Seuls une feuille et un fruit lisent leur point d'attache : le reste garde un zéro que le nuanceur ne lit pas
		if(indexed && kind === MapSceneDecor.SEASON_LEAF) {
			anchor.set(position.array);
		} else if(indexed === false) {

			for(let i = 0; i < count; i += 3) {
				if(season[i * 3] === MapSceneDecor.SEASON_LEAF) {
					center(i, i + 3);
				}
			}

			for(const [from, to] of fruits) {
				center(from, to);
			}

		}

		// Un houppier qui fond d'un bloc se rétrécit vers le centre de ses feuilles, comme une pièce réduite autour de son coeur
		if(crown) {

			const box = new this.THREE.Box3();
			const point = new this.THREE.Vector3();

			for(let i = 0; i < count; i++) {
				if(season[i * 3] === MapSceneDecor.SEASON_LEAF) {
					box.expandByPoint(point.fromBufferAttribute(position, i));
				}
			}

			box.getCenter(point);

			for(let i = 0; i < count; i++) {
				if(season[i * 3] === MapSceneDecor.SEASON_LEAF) {
					anchor.set([point.x, point.y, point.z], i * 3);
				}
			}

		}

		geometry.setAttribute('season', new this.THREE.BufferAttribute(season, 3));
		geometry.setAttribute('anchor', new this.THREE.BufferAttribute(anchor, 3));

		return geometry;

	}

	// La couleur d'une table de saison au jour `day` de l'année : entre deux couleurs, la teinte se fond de l'une à l'autre ; à côté
	// d'un null, la plus proche des deux l'emporte
	static seasonColor(THREE, table, day) {

		for(let i = 0; i < table.length - 1; i++) {

			const [d0, c0] = table[i];
			const [d1, c1] = table[i + 1];

			if(day >= d0 && day <= d1) {

				if(c0 === null || c1 === null) {
					const near = (day - d0 < (d1 - d0) / 2) ? c0 : c1;
					return (near === null) ? null : new THREE.Color(near);
				}

				return new THREE.Color(c0).lerp(new THREE.Color(c1), (day - d0) / (d1 - d0));

			}

		}

		return null;

	}

	/**
	 * Les teintes du décor au jour `date`, en 'AAAA-MM-JJ', avec `path`, la couleur que la saison de la scène donne aux chemins.
	 */
	season(date, path) {

		const THREE = this.THREE;
		const D = MapSceneDecor;
		const DAY = 86400000;

		const year = Number(date.slice(0, 4));
		const at = Date.UTC(year, Number(date.slice(5, 7)) - 1, Number(date.slice(8, 10)));
		const day = (at - Date.UTC(year, 0, 1)) / DAY;
		const turn = (at - Date.UTC(year, 9, 1)) / DAY;
		const dress = (day > D.WINTER_LAST) ? 0 : Math.min(MapScene.smooth(0, D.WINTER_FADE, day), (D.WINTER_LAST + 1 - day) / (D.WINTER_FADE + 1));
		const ratio = (color, summer) => {
			const reference = new THREE.Color(summer);
			return color.setRGB(color.r / reference.r, color.g / reference.g, color.b / reference.b);
		};

		const foliage = D.seasonColor(THREE, D.FOLIAGE_SEASON, day);
		const autumn = new THREE.Color(D.AUTUMN[0]).lerp(new THREE.Color(D.AUTUMN[1]), Math.max(0, Math.min(1, turn / D.AUTUMN_DAYS)));
		const grey = autumn.r * .3 + autumn.g * .6 + autumn.b * .1;
		autumn.lerp(new THREE.Color(grey, grey, grey), D.AUTUMN_SOBER * dress);

		const uniforms = this.seasonUniforms;

		this.turn = turn;
		this.spring = day - MapSceneCrops.LEAF_ON;
		uniforms.decorAutumn.value.set(autumn.r, autumn.g, autumn.b, turn);
		uniforms.decorSpring.value = this.spring;

		if(foliage !== null) {
			ratio(uniforms.decorFoliage.value.copy(foliage), D.FOLIAGE_SUMMER);
		}

		// La haie ternit le jour où les feuillus ont perdu la moitié de leurs feuilles
		const fallen = (foliage === null || turn >= D.LEAF_FALL_START + D.LEAF_FALL_DAYS / 2);
		const hedge = new THREE.Color(D.HEDGE_SUMMER);

		if(fallen) {
			hedge.lerp(new THREE.Color(D.HEDGE_WINTER), dress);
		}

		ratio(uniforms.decorHedge.value.copy(hedge), D.HEDGE_SUMMER);
		ratio(uniforms.decorPath.value.setRGB(...path), D.PATH_SUMMER);

		this.changed();

	}

	/**
	 * La pousse et la chute des feuilles à `days` jours après le jour montré, de 0 à 1, à chaque image : la lecture montre un jour à
	 * la fois, et les feuilles grandissent ou se rétrécissent entre deux. Le virage de la feuille, compté sur les mêmes jours, glisse
	 * avec elles.
	 */
	glide(days) {
		this.seasonUniforms.decorAutumn.value.w = this.turn + days;
		this.seasonUniforms.decorSpring.value = this.spring + days;
	}

	/**
	 * Ce qui reste des feuilles d'un arbre de retard `late`, de 0 à 1, au jour que montrent les uniformes, tel que le nuanceur le
	 * calcule pour la première facette à tomber : une scène y lit ce que fait un arbre sans relire ses sommets.
	 */
	leaves(late) {
		return this.left(late, false);
	}

	/**
	 * Ce qui reste des fruits d'un arbre de retard `late`, de 0 à 1, comme `leaves()`.
	 */
	fruits(late) {
		return this.left(late, true);
	}

	left(late, fruit) {

		const { grow, fall, fruit: fruits } = this.calendar;
		const { decorAutumn, decorSpring } = this.seasonUniforms;
		const delay = fruit ? late * fruits.share : late;
		const falling = (fruit && fruits.fall !== null) ? fruits.fall : fall;
		const days = fruit ? fruits.days : grow.days;
		const spring = decorSpring.value - ((fruit ? fruits.from : grow.from) - MapSceneCrops.LEAF_ON);
		const fade = fall.fade / fall.days;

		return MapScene.smooth(delay, delay + days, spring) * (1 - MapScene.smooth(0, fade, (decorAutumn.value.w - delay - falling.from) / falling.days));

	}

	/**
	 * La teinte de la feuille au jour `day` de l'année, d'après la table `tint` du calendrier, en [r, g, b] : la même interpolation que
	 * `decorTint()` dans le nuanceur.
	 */
	tintAt(day) {

		const keys = this.calendar.tint;
		let i = 0;

		while(i < keys.length - 2 && day >= keys[i + 1][0]) {
			i++;
		}

		const [[from, a], [to, b]] = [keys[i], keys[i + 1]];
		const k = MapScene.smooth(from, to, day);

		return a.map((value, j) => value + (b[j] - value) * k);

	}

	// `decorTint()` en GLSL, tirée de la table `tint` : entre deux clés, la teinte se fond de l'une à l'autre comme dans `tintAt()`
	tintShader() {

		const float = MapSceneDecor.float;
		const keys = this.calendar.tint;
		const vector = rgb => 'vec3('+ rgb.map(float).join(', ') +')';
		const lines = keys.slice(0, -1).map(([from, a], i) => {
			const [to, b] = keys[i + 1];
			const mix = 'return mix('+ vector(a) +', '+ vector(b) +', smoothstep('+ float(from) +', '+ float(to) +', day));';
			return (i < keys.length - 2) ? 'if(day < '+ float(to) +') '+ mix : mix;
		});

		return 'vec3 decorTint(float day) {\n'+ lines.join('\n') +'\n}\n';

	}

	/**
	 * Les ondes des mares, à chaque image, `time` en millisecondes.
	 */
	animate(time) {

		const D = MapSceneDecor;

		// Les pastilles se placent d'après le canevas du plan, qu'un décor dessiné sans écran n'a pas
		if(this.attached) {
			this.spreadDiameters();
		}

		for(const e of this.elements) {

			if(e.ripples === null) {
				continue;
			}

			for(const { ring, phase } of e.ripples) {

				const k = (time / 1000 * D.RIPPLE_PACE + phase) % 1;

				ring.scale.setScalar((D.RIPPLE_FROM + k * (D.RIPPLE_TO - D.RIPPLE_FROM)) * e.size);
				ring.material.opacity = .5 * (1 - k);

			}

		}

	}

	/**
	 * La hauteur de la pastille d'un tuyau au-dessus de sa racine, au point `q` de son tracé : son gabarit, plus ce que le sol sous ce
	 * point dépasse le sol sous la racine, dont le tuyau monte sur le relief (`MapSceneRelief.conform()`). `vector` sert au calcul.
	 */
	pipeLift(e, q, vector) {

		vector.set(q.x, 0, -q.n);
		e.root.localToWorld(vector);

		return e.label.userData.height + this.geometry.height(vector.x, -vector.z) - e.root.position.y;

	}

	/**
	 * Un tuyau se voit quand un bout de son tracé, à la hauteur de sa pastille, passe dans le canevas : chaque segment projeté
	 * se rogne au cadre (Liang-Barsky), et il suffit d'un reste. Un segment qui passe derrière la caméra n'a pas de projection
	 * fiable, il compte comme vu, et le tuyau garde sa pastille, que la scène ne montre de toute façon pas derrière elle.
	 */
	pipeInView(e, width, height) {

		const vector = new this.THREE.Vector3();

		const screen = e.path.map(q => {
			vector.set(q.x, this.pipeLift(e, q, vector), -q.n);
			e.root.localToWorld(vector).project(this.camera);
			return { x: (vector.x + 1) / 2 * width, y: (1 - vector.y) / 2 * height, behind: vector.z > 1 };
		});

		// Un tracé d'un seul point, au premier clic, se voit si ce point est dans le cadre
		if(screen.length === 1) {
			const [point] = screen;
			return (point.behind || (point.x >= 0 && point.x <= width && point.y >= 0 && point.y <= height));
		}

		return screen.slice(1).some((b, index) => {

			const a = screen[index];

			if(a.behind || b.behind) {
				return true;
			}

			const dx = b.x - a.x;
			const dy = b.y - a.y;
			let enter = 0;
			let leave = 1;

			for(const [p, q] of [[-dx, a.x], [dx, width - a.x], [-dy, a.y], [dy, height - a.y]]) {

				if(p === 0) {
					if(q < 0) {
						return false;
					}
					continue;
				}

				const t = q / p;

				if(p < 0) {
					enter = Math.max(enter, t);
				} else {
					leave = Math.min(leave, t);
				}

			}

			return enter <= leave;

		});

	}

	/**
	 * Les pastilles des diamètres de deux tuyaux voisins s'empileraient à mi-longueur, et celle d'un tuyau qu'on trace sous la
	 * cote de sa longueur, posée au même milieu : chacune glisse le long de son tracé (DIAMETER_SHARES) jusqu'à la première
	 * place où elle ne recouvre à l'écran ni la cote ni aucune pastille posée avant elle, ou reste à mi-longueur. Une place
	 * ne vaut que dans la scène, sous la rangée de boutons posée sur son haut : une pastille qui reste à mi-longueur hors de la
	 * scène y est ramenée, le long de son axe de vue.
	 *
	 * Comme `MapScene.declutter()`, la taille d'une pastille se lit une fois, son texte ne changeant qu'avec elle. La passe ne
	 * suit pas son rythme de 150 ms : une pastille doit rester sur son tuyau pendant qu'on tourne la vue. Elle tourne donc en tête
	 * d'image (`animate()`), avant que la scène ne déplace ses étiquettes, où la mise en page lue est celle de l'image d'avant,
	 * déjà faite, et seulement quand quelque chose a bougé.
	 */
	spreadDiameters() {

		const THREE = this.THREE;
		const width = this.canvas.clientWidth;
		const height = this.canvas.clientHeight;
		const size = width +'x'+ height;

		if(this.spreadQueued === false && size === this.spreadSize) {
			return;
		}

		// Le tuyau qu'on trace n'est pas encore parmi les éléments, et un tuyau repris par « Continuer » y est déjà
		const pipes = [...new Set([...this.elements, this.tracing?.e ?? null])].filter(e => e !== null && e.label !== null && e.root.visible);

		// Un tuyau qu'on ne voit pas du tout n'a rien à montrer : sa pastille se tait, et revient dès qu'un bout du tuyau entre dans la scène
		const shown = pipes.filter(e => this.pipeInView(e, width, height));

		pipes.forEach(e => {
			e.label.visible = shown.includes(e);
		});

		const unsized = shown.filter(e => e.label.userData.size === undefined);
		const sizes = unsized.map(e => [e.label.element.offsetWidth, e.label.element.offsetHeight]);

		unsized.forEach((e, index) => {
			if(sizes[index][0] > 0) {
				e.label.userData.size = sizes[index];
			}
		});

		const measured = shown
			.filter(e => e.label.userData.size !== undefined)
			.map(e => ({
				e,
				width: e.label.userData.size[0],
				height: e.label.userData.size[1],
				lift: parseFloat(getComputedStyle(e.label.element).marginTop)
			}));

		// Une pastille tout juste refaite n'est posée dans la page qu'au rendu qui suit : la place se refait une fois, à l'image
		// suivante. Une pastille encore sans taille après ce second essai est masquée (mode photo), et le restera jusqu'à un geste
		const late = (measured.length < shown.length);

		this.spreadQueued = (late && this.spreadRetried === false);
		this.spreadRetried = this.spreadQueued;
		this.spreadSize = size;

		// La grille du terrain n'existe qu'avec la feuille de pose, que seul reçoit qui peut poser
		if(this.sheet !== null) {
			this.lines.grid.opacity = MapSceneDecor.gridOpacity(MapSceneDecor.CELL * height * this.camera.zoom / (this.camera.top - this.camera.bottom));
		}

		// La rangée du haut se mesure une fois par passe : ses boutons changent avec la pose, et posée au-dessus de la scène sur un
		// téléphone, elle n'en couvre rien
		const box = this.canvas.getBoundingClientRect();
		const top = Math.max(0, this.container.qs('.map-scene-bar').getBoundingClientRect().bottom - box.top);
		// La feuille de pose couvre le bas de la scène tant qu'elle est ouverte
		const bottom = this.active ? Math.min(height, this.sheet.getBoundingClientRect().top - box.top) : height;

		const said = (this.sheet !== null && this.say.visible) ? {
			width: this.say.element.offsetWidth,
			height: this.say.element.offsetHeight,
			lift: parseFloat(getComputedStyle(this.say.element).marginTop)
		} : null;

		const vector = new THREE.Vector3();
		const placed = [];

		if(said !== null) {

			this.say.getWorldPosition(vector).project(this.camera);

			const x = (vector.x + 1) / 2 * width;
			const y = (1 - vector.y) / 2 * height + said.lift;

			// Le mot reste entier dans la scène : près d'un bord, il glisse le long de son point d'attache au lieu d'y être rogné. Il
			// ne couvre pas non plus le nom de la structure, que l'enseigne dresse à l'écran
			const frame = { left: 0, right: width, top, bottom };
			const sign = this.sign.flatMap(q => [0, MapSceneDecor.SIGN_HEIGHT].map(lift => {
				vector.set(q.x, lift, -q.n).project(this.camera);
				return { x: (vector.x + 1) / 2 * width, y: (1 - vector.y) / 2 * height };
			}));
			const held = MapSceneDecor.clear(MapSceneDecor.held({ x, y, width: said.width, height: said.height }, frame), said, sign, frame);

			// Un mot qui vient de se montrer n'a de taille qu'une fois rendu : il attend l'image suivante, où la passe se refait, pour
			// ne pas paraitre une image durant à cheval sur le bord
			this.say.element.style.visibility = (said.width === 0) ? 'hidden' : '';

			if(said.width === 0) {
				this.spreadQueued = true;
			} else {
				this.say.center.set((x - held.left) / said.width, (y - held.top) / said.height);
			}

			placed.push({ left: held.left - 2, right: held.left + said.width + 2, top: held.top - 2, bottom: held.top + said.height + 2 });

		}

		const rectAt = (item, q) => {

			vector.set(q.x, this.pipeLift(item.e, q, vector), -q.n);
			item.e.root.localToWorld(vector).project(this.camera);

			const x = (vector.x + 1) / 2 * width;
			const y = (1 - vector.y) / 2 * height + item.lift;

			return { left: x - item.width / 2 - 2, right: x + item.width / 2 + 2, top: y - item.height / 2 - 2, bottom: y + item.height / 2 + 2, depth: vector.z };

		};

		const inside = rect => (rect.left >= 0 && rect.right <= width && rect.top >= top && rect.bottom <= height);
		const clear = rect => inside(rect) && placed.every(other => other.right <= rect.left || other.left >= rect.right || other.bottom <= rect.top || other.top >= rect.bottom);

		// Une pastille ramenée dans la scène se pose après toutes les autres, pour ne couvrir aucune de celles qui tiennent sur leur tracé
		const brought = [];

		for(const item of measured) {

			const places = MapSceneDecor.DIAMETER_SHARES.map(share => MapSceneDecor.pointAlong(item.e.path, share));
			const rects = places.map(q => rectAt(item, q));
			const index = rects.findIndex(clear);

			// Une mi-longueur derrière la caméra ne se montre pas, et sa projection n'a pas de place à ramener
			if(index !== -1 || rects[0].depth > 1) {
				const at = Math.max(0, index);
				item.e.label.position.set(places[at].x, this.pipeLift(item.e, places[at], vector), -places[at].n);
				placed.push(rects[at]);
			} else {
				brought.push({ item, rect: rects[0] });
			}

		}

		for(const { item, rect } of brought) {

			const sizeX = rect.right - rect.left;
			const sizeY = rect.bottom - rect.top;
			const left = Math.min(Math.max(rect.left, 0), width - sizeX);
			const up = Math.min(Math.max(rect.top, top), height - sizeY);

			// Au bord, elle glisse le long de lui, d'une pastille à la fois et de part et d'autre, jusqu'à une place libre
			const alongX = (left !== rect.left);
			const candidates = [0, 1, -1, 2, -2, 3, -3, 4, -4]
				.map(step => alongX ? { left, top: up + step * sizeY } : { left: left + step * sizeX, top: up })
				.map(at => ({ left: at.left, right: at.left + sizeX, top: at.top, bottom: at.top + sizeY, depth: rect.depth }));

			const moved = candidates.find(clear) ?? candidates[0];

			// Le point de la scène qui se projette au centre de la place ramenée, à la profondeur de la mi-longueur
			vector.set(
				(moved.left + moved.right) / width - 1,
				1 - ((moved.top + moved.bottom) / 2 - item.lift) / height * 2,
				rect.depth
			).unproject(this.camera);

			item.e.label.position.copy(item.e.root.worldToLocal(vector));
			placed.push(moved);

		}

	}

	/**
	 * Les arbres du décor posé, pied par pied, et le long de chaque haie un point tous les quelques mètres : `{ p, radius, hedge }`.
	 */
	trees() {

		const G = ScenePlanGeometry;

		// Une bande fleurie n'a ni pied ni ombre où dormir
		return this.elements.filter(e => e.def.family === 'tree' && e.kit !== 'bande-fleurie').flatMap(e => {

			if(e.kit === 'haie') {
				return e.path.map(q => ({ p: G.add(e.p, q), radius: e.thick / 2, hedge: true }));
			}

			return e.feet.map(foot => ({ p: MapSceneBoards.center(MapSceneDecor.square(e, foot)), radius: foot.half * e.size, hedge: false }));

		});

	}

	/**
	 * Ce que les paysans contournent, en rectangles du plan : tout le décor posé sauf le chemin, qui se marche. Un tracé compte
	 * segment par segment et un kit d'arbres pied par pied ; le tour d'un enclos laisse ouvert le passage de son portail, et un
	 * portail seul ne compte que ses poteaux. La route se marche comme le chemin.
	 */
	solids() {

		const G = ScenePlanGeometry;
		const D = MapSceneDecor;

		return this.elements.filter(e => e.root !== null && MapSceneDecor.WALKED.includes(e.kit) === false).flatMap(e => {

			// Ce qui marche dans la scène ne voit que le décor : une pompe ou un tuyau ne se contourne pas
			if(e.layer !== MapSceneLayer.BASE) {
				return [];
			}

			if(e.kit === D.GATE) {

				const { along, across } = D.gateAxes(e);
				const span = Math.max(e.width, e.depth) / 2 - D.GATE_POST / 2;
				const half = G.add(G.mul(along, D.GATE_POST / 2), G.mul(across, D.GATE_POST / 2));

				return [-span, span].map(k => ({ origin: G.sub(G.add(e.p, G.mul(along, k)), half), w: along, u: across, width: D.GATE_POST, length: D.GATE_POST }));

			}

			if(e.def.enclosure !== true) {
				return D.footprint(e).pieces.map(piece => piece.rectangle);
			}

			// Ce qui se dresse, et non la bande qu'on vise : la clôture sur le tour et la haie au-delà, chacune ouverte au portail.
			// La bande déborde le tour vers l'intérieur, où une planche serrée contre la clôture laisse encore passer
			const lines = D.enclosureLines(e);
			const absolute = line => line.map(q => G.add(e.p, q));

			return [
				...((lines.fence.length >= 2) ? D.segments(absolute(lines.fence), D.FENCE_SOLID) : []),
				...((lines.hedge.length >= 2) ? D.segments(absolute(lines.hedge), D.HEDGE_WIDTH / 2) : [])
			];

		});

	}

	/**
	 * Les passages des portails, un par enclos et un par portail posé seul : le milieu de l'ouverture, en mètres du plan, la
	 * direction dans laquelle on la franchit, celle de l'ouverture et sa demi-largeur libre, et pour un enclos son tour et les
	 * coins du tour qu'on en fait par dedans et par dehors.
	 */
	gates() {

		const G = ScenePlanGeometry;
		const D = MapSceneDecor;

		return this.elements.filter(e => e.root !== null && (e.kit === D.GATE || e.def.enclosure === true)).map(e => {

			if(e.kit === D.GATE) {
				const { along, across } = D.gateAxes(e);
				return { p: e.p, across, along, half: Math.max(e.width, e.depth) / 2 - D.GATE_POST, ring: [], round: [] };
			}

			// La tangente au tour se prend sur un mètre de part et d'autre du portail, `loopCut()` faisant le tour au-delà de la longueur
			const total = D.loopLength(e.ring);
			const s = e.gate * total;
			const point = at => D.loopCut(e.ring, at, at + .01)[0];
			const along = G.norm(G.sub(point(s + .5), point(s - .5 + total)));

			// On en fait le tour par dedans, et par dehors au-delà de sa haie : un portail qui s'ouvre vers une autre haie y mène encore
			const hedged = (e.hedge || e.model === null);
			const outside = (hedged ? D.HEDGE_WIDTH + ((e.model !== null) ? D.HEDGE_GAP : 0) : D.FENCE_SOLID) + D.ROUND_OUTSET;
			const round = [...D.offsetLoop(e.ring, -D.ROUND_INSET), ...D.offsetLoop(e.ring, outside)];

			return { p: G.add(e.p, point(s)), across: { x: -along.n, n: along.x }, along, half: D.opening(e) / 2, ring: e.ring.map(q => G.add(e.p, q)), round: round.map(q => G.add(e.p, q)) };

		});

	}

	// Un portail s'ouvre sur sa plus grande dimension, et se franchit sur l'autre
	static gateAxes(e) {

		const { right, front } = MapSceneDecor.axes(e);

		return (e.width >= e.depth) ? { along: right, across: front } : { along: front, across: right };

	}

	available(def) {
		return MapSceneDecor.slugs(def).every(slug => this.baked.index.models[slug] !== undefined);
	}

	// Un tracé prend sa ligne : posé au centre de sa boite, sans rotation, ses points rapportés à ce centre
	lay(e, line) {

		const box = MapSceneDecor.extent(line);

		e.p = { x: (box.x0 + box.x1) / 2, n: (box.n0 + box.n1) / 2 };
		e.angle = 0;
		e.trace = line.map(q => ScenePlanGeometry.sub(q, e.p));

	}

	/**
	 * Le trait du geste, dans `material`, posé sur son cerne d'encre : un enfant du trait, que sa visibilité emporte, dessiné juste
	 * avant lui. Les deux attendent leur ligne de `strokeAlong()`.
	 */
	stroke(material) {

		const THREE = this.THREE;

		const mesh = new THREE.Mesh(new THREE.BufferGeometry(), material);
		mesh.renderOrder = 5;

		const casing = new THREE.Mesh(new THREE.BufferGeometry(), this.pads.casing);
		casing.renderOrder = 4;
		mesh.add(casing);

		return mesh;

	}

	/**
	 * Le trait d'un geste de `wide` mètres le long d'une ligne du plan, et son cerne, `STROKE_CASING` fois plus large et un peu
	 * plus bas.
	 */
	strokeAlong(mesh, line, wide) {

		const [casing] = mesh.children;

		mesh.geometry.dispose();
		casing.geometry.dispose();

		mesh.geometry = this.strip(line, wide, .12, '#ffffff');
		casing.geometry = this.strip(line, wide * MapSceneDecor.STROKE_CASING, .11, '#ffffff');

	}

	/**
	 * Une bande à plat de `wide` mètres le long d'une ligne du plan, à `lift` du sol, d'un seul tenant et aux bouts arrondis. Elle
	 * est découpée au mètre en long et en `across` bandes en travers, au mètre par défaut, et chaque sommet s'écarte de la ligne
	 * sur la bissectrice de ses deux segments, d'autant plus que le coude est serré : la bande garde sa largeur dans le virage,
	 * sans jour ni pli. Elle se calcule en coordonnées de la scène, où le côté droit d'une direction (X, Z) est (Z, -X) et où
	 * l'ordre des sommets tourne la face vers le haut.
	 */
	strip(line, wide, lift, color, across = Math.max(1, Math.round(wide))) {

		const THREE = this.THREE;
		const G = ScenePlanGeometry;
		const dense = [line[0]];

		for(let i = 1; i < line.length; i++) {

			const cuts = Math.max(1, Math.ceil(G.length(G.sub(line[i], line[i - 1]))));

			for(let k = 1; k <= cuts; k++) {
				dense.push(G.lerp(line[i - 1], line[i], k / cuts));
			}

		}

		const heading = (a, b) => {
			const X = b.x - a.x;
			const Z = a.n - b.n;
			const length = Math.hypot(X, Z) || 1;
			return { X: X / length, Z: Z / length };
		};

		const half = wide / 2;
		const last = dense.length - 1;
		const positions = [];
		const index = [];

		dense.forEach((q, i) => {

			const into = heading(dense[Math.max(0, i - 1)], dense[Math.max(1, i)]);
			const out = heading(dense[Math.min(i, last - 1)], dense[Math.min(i + 1, last)]);
			const tangent = heading({ x: 0, n: 0 }, { x: into.X + out.X, n: -(into.Z + out.Z) });
			const scale = 1 / Math.max(.5, tangent.X * into.X + tangent.Z * into.Z);

			for(let j = 0; j <= across; j++) {
				const offset = (j / across - .5) * wide * scale;
				positions.push(q.x + tangent.Z * offset, lift, -q.n - tangent.X * offset);
			}

		});

		for(let i = 0; i < last; i++) {
			for(let j = 0; j < across; j++) {
				const a = i * (across + 1) + j;
				const c = a + across + 1;
				index.push(a, c, a + 1, a + 1, c, c + 1);
			}
		}

		// Un demi-disque à chaque bout, en éventail : vers l'arrière au départ, vers l'avant à l'arrivée
		const cap = (q, side, toward) => {

			const center = positions.length / 3;
			positions.push(q.x, lift, -q.n);

			for(let k = 0; k <= 8; k++) {
				const angle = Math.PI * k / 8;
				positions.push(q.x + half * (side.X * Math.cos(angle) + toward.X * Math.sin(angle)), lift, -q.n + half * (side.Z * Math.cos(angle) + toward.Z * Math.sin(angle)));
			}

			for(let k = 0; k < 8; k++) {
				index.push(center, center + 1 + k, center + 2 + k);
			}

		};

		const first = heading(dense[0], dense[1]);
		const end = heading(dense[last - 1], dense[last]);

		cap(dense[0], { X: first.Z, Z: -first.X }, { X: -first.X, Z: -first.Z });
		cap(dense[last], { X: -end.Z, Z: end.X }, end);

		const count = positions.length / 3;
		const tint = new THREE.Color(color);
		const geometry = new THREE.BufferGeometry();

		geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
		geometry.setAttribute('normal', new THREE.Float32BufferAttribute(Array.from({ length: count }, () => [0, 1, 0]).flat(), 3));
		geometry.setAttribute('color', new THREE.Float32BufferAttribute(Array.from({ length: count }, () => [tint.r, tint.g, tint.b]).flat(), 3));
		geometry.setIndex(index);

		return geometry;

	}

	/**
	 * Les travées d'une clôture ou d'une haie, d'un piquet au suivant, chacune étirée en Z jusqu'au piquet d'après, et le piquet seul
	 * qui ferme la dernière. Une haie tire les siennes de `seed` : la même graine redonne la même haie. Sur le relief, `origin` est le
	 * point de la scène où se pose leur repère, et chaque travée suit le sol avant la fusion, qui perd la plage de ses arbres.
	 */
	spans(piece, posts, seed = 0, origin = null) {

		const THREE = this.THREE;
		const D = MapSceneDecor;
		const G = ScenePlanGeometry;
		const models = this.baked.index.models;
		const matrix = new THREE.Matrix4();
		const turn = new THREE.Quaternion();
		const up = new THREE.Vector3(0, 1, 0);
		const root = new THREE.Vector3();
		const foot = new THREE.Vector3();
		const point = new THREE.Vector3();
		const parts = [];
		const heights = [];

		// Une travée part de `from` vers `to`, prolongée de `extend` à chaque bout, étirée de `stretch` en Z et haute de `height` fois
		// son modèle
		const lay = (slug, green, from, to, extend, stretch, height = 1) => {

			const length = G.length(G.sub(to, from));
			const X = (to.x - from.x) / length;
			const Z = (from.n - to.n) / length;
			const start = new THREE.Vector3(from.x - X * extend, 0, -from.n - Z * extend);

			const base = this.base(slug, green, false);
			const geometry = base.clone();

			if(slug === piece.slug && piece.across === true) {
				geometry.rotateY(-Math.PI / 2).translate(0, 0, piece.pitch / 2);
			}

			turn.setFromAxisAngle(up, Math.atan2(X, Z));
			geometry.applyMatrix4(matrix.compose(start, turn, new THREE.Vector3(1, height, stretch)));

			// Un arbre étiré avec sa travée aurait un houppier ovale : il garde sa forme, planté où la travée a mené son pied et grandi
			// d'un bloc. Ses sommets repartent du modèle, normales comprises, que la matrice d'une travée étirée aurait penchées
			if(base.userData.trees.length > 0) {

				root.fromArray(base.userData.root);
				foot.copy(root).applyMatrix4(matrix);
				matrix.compose(foot, turn, new THREE.Vector3(height, height, height));

				for(const [first, last] of base.userData.trees) {

					for(let i = first; i < last; i++) {

						point.fromBufferAttribute(base.attributes.position, i).sub(root).applyMatrix4(matrix);
						geometry.attributes.position.setXYZ(i, point.x, point.y, point.z);

						point.fromBufferAttribute(base.attributes.normal, i).applyQuaternion(turn);
						geometry.attributes.normal.setXYZ(i, point.x, point.y, point.z);

					}

				}

			}

			if(origin !== null) {
				this.geometry.relief.conform(geometry, origin, base.userData.trees.map(([first, last]) => ({ first, last, x: foot.x, z: foot.z })));
			}

			parts.push(geometry);
			heights.push(models[slug].h * height);

			return geometry;

		};

		const stretches = posts.slice(1).map((post, k) => (G.length(G.sub(post, posts[k])) + 2 * piece.overlap) / piece.pitch);
		const mixed = (piece.mixed === true) ? D.hedgeSpans(stretches, seed) : null;

		for(let k = 1; k < posts.length; k++) {

			if(mixed === null) {

				const [from, to] = (piece.varied && k % 2 === 0) ? [posts[k], posts[k - 1]] : [posts[k - 1], posts[k]];

				lay(piece.slug, piece.varied ? k % D.GREENS.length : 0, from, to, piece.overlap, stretches[k - 1]);

				continue;

			}

			const span = mixed[k - 1];
			const [from, to] = span.flip ? [posts[k], posts[k - 1]] : [posts[k - 1], posts[k]];

			// Une forme qui n'est pas cuite laisse sa travée au tronçon de la pièce, et une charmille sans son bout garde le sien
			let slug = piece.slug;

			if(models[span.slug] !== undefined) {
				slug = (span.end !== 0 && models[D.HEDGE_WALL_END] !== undefined) ? D.HEDGE_WALL_END : span.slug;
			}

			// Seule la haie suit la saison, et une bande fleurie garde ses fleurs toute l'année. La saison se pose sur la travée en
			// place, avant la fusion : une grappe se réduit vers un point de la haie, et la fusion ne garde pas la plage des fruits
			this.mark(lay(slug, span.green, from, to, piece.overlap, stretches[k - 1], span.height), D.SEASON_HEDGE, (k * 5 + seed) % D.LEAF_LATE_DAYS, seed * 31 + k);

		}

		if(piece.end !== null) {
			lay(piece.end, 0, posts.at(-1), G.add(posts.at(-1), G.sub(posts.at(-1), posts.at(-2))), 0, 1);
		}

		const geometry = this.addons.mergeGeometries(parts);
		parts.forEach(part => part.dispose());

		return { geometry, height: Math.max(...heights) };

	}

	/**
	 * Le volume d'un tracé, dans le repère de son centre : sa ligne montrée, `path`, celle des piquets pour une clôture ou une haie, et
	 * l'épaisseur de son emprise, `thick`.
	 */
	makeTrace(e) {

		if(e.def.pipe === true) {
			return this.makePipe(e);
		}

		const smooth = MapSceneDecor.smooth(e.trace);
		let made;

		if(e.def.layers !== undefined) {

			// Une largeur fixe est celle du kit : l'élément n'en porte pas, `map\Decor.width` n'en enregistrant que de un à trois mètres
			const wide = e.wide ?? e.def.wide;
			const layers = e.def.layers.map(([margin, lift, color]) => this.mark(this.onGround(this.strip(smooth, wide + margin, lift, color, e.def.across), e), (e.kit === 'chemin') ? MapSceneDecor.SEASON_PATH : 0));

			made = { geometry: this.addons.mergeGeometries(layers), path: smooth, thick: wide + Math.max(...e.def.layers.map(([margin]) => margin)), height: .15, flat: true };
			layers.forEach(layer => layer.dispose());

		} else {

			const posts = MapSceneDecor.posts(smooth, e.def.pieces(e.model).pitch);

			made = { ...this.spans(e.def.pieces(e.model), posts, e.seed ?? e.id ?? 0, this.groundOrigin(e)), path: posts, thick: e.def.thick, flat: false };

		}

		// L'emprise de l'élément entoure sa ligne de part et d'autre de son centre : les outils et la grille fine s'y calent
		made.owned = true;
		made.width = 2 * Math.max(...made.path.map(q => Math.abs(q.x))) + made.thick;
		made.depth = 2 * Math.max(...made.path.map(q => Math.abs(q.n))) + made.thick;

		return made;

	}

	// L'ouverture du portail d'un enclos, en mètres le long de son tour
	static opening(e) {
		return (e.model === null) ? MapSceneDecor.GATE_LENGTH : MapSceneDecor.FENCES[e.model].gate;
	}

	/**
	 * Les lignes qu'un enclos dresse, dans le repère de son centre, chacune ouverte devant le portail sur `opening` mètres : la clôture
	 * sur le tour, et la haie qui la double côté extérieur ou fait le tour seule. Une ligne absente est vide.
	 */
	static enclosureLines(e, opening = MapSceneDecor.opening(e)) {

		const D = MapSceneDecor;
		const ring = e.ring;
		const total = D.loopLength(ring);
		const centre = e.gate * total;

		const fence = (e.model === null) ? [] : D.loopCut(ring, centre + opening / 2, centre - opening / 2 + total);

		if(e.hedge === false && e.model !== null) {
			return { fence, hedge: [] };
		}

		const hedgeRing = D.offsetLoop(ring, D.HEDGE_WIDTH / 2 + ((e.model !== null) ? D.HEDGE_GAP : 0));
		const hedgeTotal = D.loopLength(hedgeRing);
		const hedgeCentre = D.loopProject(hedgeRing, D.loopCut(ring, centre, centre + .01)[0]).s;
		const half = (opening + ((e.model !== null) ? 1.4 : 0)) / 2;

		return { fence, hedge: D.loopCut(hedgeRing, hedgeCentre + half, hedgeCentre - half + hedgeTotal) };

	}

	/**
	 * Le volume d'un enclos, dans le repère de son centre. La clôture fait le tour en laissant le passage du portail, fermé par la
	 * pièce `portail` quand elle est cuite ; la haie la double côté extérieur et s'ouvre devant lui, ou fait le tour seule. Son
	 * emprise, `path`, est une bande au milieu de ce qu'il dresse : on la vise, et les paysans en marche contournent ses lignes, pas elle.
	 */
	makeEnclosure(e) {

		const G = ScenePlanGeometry;

		const ring = e.ring;
		const total = MapSceneDecor.loopLength(ring);
		const opening = MapSceneDecor.opening(e);
		const centre = e.gate * total;
		const lines = MapSceneDecor.enclosureLines(e);
		const parts = [];
		const heights = [];

		if(e.model !== null) {

			const piece = MapSceneDecor.fence(e.model);

			if(lines.fence.length >= 2) {
				parts.push(this.spans(piece, MapSceneDecor.posts(lines.fence, piece.pitch), 0, this.groundOrigin(e)).geometry);
				heights.push(this.baked.index.models[piece.slug].h);
			}

			if(this.baked.index.models[MapSceneDecor.GATE] !== undefined) {
				// `loopCut()` ne remonte pas avant l'abscisse 0 : le passage se coupe un tour plus loin, où il ne la croise jamais
				const [from, to] = MapSceneDecor.loopCut(ring, centre - opening / 2 + total, centre + opening / 2 + total).filter((q, i, list) => i === 0 || i === list.length - 1);
				parts.push(this.onGround(this.lean(MapSceneDecor.GATE, from, to, G.length(G.sub(to, from)) / MapSceneDecor.GATE_LENGTH), e));
			}

		}

		const hedged = (e.hedge || e.model === null);

		if(hedged && lines.hedge.length >= 2) {
			const hedge = this.spans(MapSceneDecor.hedge(), MapSceneDecor.posts(lines.hedge, MapSceneDecor.hedge().pitch), e.seed ?? e.id ?? 0, this.groundOrigin(e));

			parts.push(hedge.geometry);
			heights.push(hedge.height);
		}

		const geometry = (parts.length > 0) ? this.addons.mergeGeometries(parts) : new this.THREE.BufferGeometry();
		parts.forEach(part => part.dispose());

		// La bande qu'on vise couvre la clôture et sa haie : elle court entre les deux
		const reach = (e.model === null) ? MapSceneDecor.HEDGE_WIDTH : (hedged ? MapSceneDecor.HEDGE_GAP + MapSceneDecor.HEDGE_WIDTH + .6 : .6);
		const middle = (e.model === null) ? MapSceneDecor.HEDGE_WIDTH / 2 : (hedged ? (MapSceneDecor.HEDGE_GAP + MapSceneDecor.HEDGE_WIDTH) / 2 : 0);
		const band = (middle > 0) ? MapSceneDecor.offsetLoop(ring, middle) : ring;
		const path = [...band, band[0]];

		return {
			geometry, owned: true, path, thick: reach, flat: false,
			width: 2 * Math.max(...path.map(q => Math.abs(q.x))) + reach,
			depth: 2 * Math.max(...path.map(q => Math.abs(q.n))) + reach,
			height: Math.max(0, ...heights)
		};

	}

	// Une pièce cuite tirée selon +Z, posée de `from` vers `to` et étirée de `stretch` en Z
	lean(slug, from, to, stretch) {

		const THREE = this.THREE;
		const length = ScenePlanGeometry.length(ScenePlanGeometry.sub(to, from));
		const X = (to.x - from.x) / length;
		const Z = (from.n - to.n) / length;

		return this.base(slug, 0, false).clone().applyMatrix4(new THREE.Matrix4().compose(new THREE.Vector3(from.x, 0, -from.n), new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), Math.atan2(X, Z)), new THREE.Vector3(1, 1, stretch)));

	}

	/**
	 * Le point de la scène où se pose le repère d'un tracé ou d'un enclos, que ses pièces suivent sur le relief, ou null sans relief.
	 * Ce repère garde les axes de la scène : un tracé et un enclos se posent au centre de leur boite, sans rotation (`lay()`,
	 * `enclose()`), et sans outil de taille.
	 */
	groundOrigin(e) {
		return (this.geometry.relief === null) ? null : e.p;
	}

	// Une pièce d'un tracé ou d'un enclos, dans le repère de l'élément, qui suit le sol sur le relief (`MapSceneRelief.conform()`)
	onGround(geometry, e) {

		if(this.geometry.relief !== null) {
			this.geometry.relief.conform(geometry, e.p);
		}

		return geometry;

	}

	/**
	 * Le volume d'un élément à la taille 1 : sa géométrie, à libérer avec lui quand `owned`, et son emprise.
	 */
	make(e) {

		const def = e.def;

		if(def.traced === true) {
			return this.makeTrace(e);
		}

		if(def.enclosure === true) {
			return this.makeEnclosure(e);
		}

		if(def.shape !== undefined) {
			return this.makeShape(e);
		}

		if(def.building === true) {
			return this.makeBuilding(e);
		}

		if(def.object === true) {
			return this.makeObject(e);
		}

		if(def.model !== undefined) {

			const [min, max] = this.baked.index.models[def.model].box;

			// Un modèle du réseau d'irrigation grandit comme ses formes bâties (makeShape), un modèle `grown` de son facteur, chacun sur
			// sa propre copie
			if(def.layer === 'irrigation' || def.grown !== undefined) {
				const scale = (def.layer === 'irrigation') ? MapSceneDecor.irrigationScale(e) : def.grown;
				return { geometry: this.base(def.model, 0, true).clone().scale(scale, scale, scale), owned: true, width: (max[0] - min[0]) * scale, depth: (max[2] - min[2]) * scale, height: max[1] * scale };
			}

			return { geometry: this.base(def.model, 0, true), owned: false, width: max[0] - min[0], depth: max[2] - min[2], height: max[1] };

		}

		const THREE = this.THREE;
		const plants = def.plants(MapSceneCrops.random(e.seed ?? e.id ?? 0));
		const matrix = new THREE.Matrix4();
		const seed = e.seed ?? e.id ?? 0;

		// Un feuillu suit l'automne avec un retard qui change d'un arbre à l'autre, un buisson ternit comme une haie, un sapin ne
		// change pas
		const season = slug => MapSceneDecor.DECIDUOUS.includes(slug) ? MapSceneDecor.SEASON_LEAF : ((slug === 'buisson') ? MapSceneDecor.SEASON_HEDGE : 0);

		const pieces = plants.map((plant, index) => this.mark(
			this.base(plant.slug, plant.green, false).clone().applyMatrix4(matrix.compose(new THREE.Vector3(plant.x, 0, plant.z), new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), plant.turn), new THREE.Vector3(plant.scale, plant.scale, plant.scale))),
			season(plant.slug),
			(index * 5 + seed) % this.calendar.late,
			seed * 31 + index,
			this.calendar.crown
		));
		const geometry = this.addons.mergeGeometries(pieces);

		// La plage de sommets de chaque plant dans le volume fusionné, que le relief monte d'un seul tenant (`fitGround()`)
		const starts = pieces.reduce((ends, piece) => [...ends, ends.at(-1) + piece.attributes.position.count], [0]);

		pieces.forEach(piece => piece.dispose());

		// Les plants sont tirés au hasard dans un cadre que leurs cimes ne remplissent jamais tout à fait ni au centre : le kit se
		// recentre sur ce qu'il dessine, et son étendue, que mesure l'outil de taille, est celle de ses cimes
		geometry.computeBoundingBox();

		const box = geometry.boundingBox;
		const centre = { x: (box.min.x + box.max.x) / 2, z: (box.min.z + box.max.z) / 2 };

		geometry.translate(-centre.x, 0, -centre.z);

		// `translate()` ne déplace que les positions et les normales : le point d'attache que `mark()` a posé sur chaque feuille suit à
		// la main, sans quoi elle se rétrécirait vers un point resté à l'ancienne place
		geometry.attributes.anchor.applyMatrix4(new THREE.Matrix4().makeTranslation(-centre.x, 0, -centre.z));

		// La demi-largeur de la cime
		const reach = plant => Math.max(...this.baked.index.models[plant.slug].box.flatMap(corner => [Math.abs(corner[0]), Math.abs(corner[2])])) * plant.scale;

		// Le pied de chaque plant tourne avec lui, son tronc aussi ; un buisson n'a pas de tronc
		const feet = plants.map((plant, k) => {

			const foot = this.foot(plant.slug);
			const cos = Math.cos(plant.turn);
			const sin = Math.sin(plant.turn);

			return {
				first: starts[k],
				last: starts[k + 1],
				x: plant.x - centre.x + (foot.x * cos + foot.z * sin) * plant.scale,
				z: plant.z - centre.z + (foot.z * cos - foot.x * sin) * plant.scale,
				half: foot.half * plant.scale,
				trunk: (plant.slug === 'buisson') ? null : Math.max(MapSceneDecor.TRUNK_MIN / 2, MapSceneDecor.STONES.includes(plant.slug) ? foot.half * plant.scale : MapSceneDecor.TRUNK_SHARE * reach(plant))
			};

		});

		return {
			geometry, owned: true, feet,
			width: box.max.x - box.min.x,
			depth: box.max.z - box.min.z,
			height: Math.max(...plants.map(plant => this.baked.index.models[plant.slug].h * plant.scale))
		};

	}

	// L'échelle d'un élément du réseau d'irrigation : IRRIGATION_SCALE, ou plus pour une vanne calée sur un gros tuyau (VALVE_OVER)
	static irrigationScale(e) {

		const D = MapSceneDecor;

		if(e.seated === null) {
			return D.IRRIGATION_SCALE;
		}

		return Math.max(D.IRRIGATION_SCALE, D.VALVE_OVER * D.pipeWidth(e.seated) / D.VALVE_BODY);

	}

	/**
	 * Une pompe ou une vanne, en formes simples à leur taille réelle puis grandies de irrigationScale(), la vanne couchée sur l'axe X
	 * où passe son tuyau. Couleurs reprises du POC, en attendant les modèles de 3D-Image.
	 */
	makeShape(e) {

		const THREE = this.THREE;
		const pipe = MapSceneDecor.PIPE_COLOR;

		const pieces = (e.def.shape === 'pump') ? [
			[new THREE.BoxGeometry(1.1, .1, .5).translate(0, .05, 0), '#7d8a96'],
			[new THREE.CylinderGeometry(.18, .18, .5, 10).rotateZ(Math.PI / 2).translate(-.22, .3, 0), '#5e6b78'],
			[new THREE.CylinderGeometry(.22, .22, .2, 12).rotateZ(Math.PI / 2).translate(.18, .32, 0), pipe],
			[new THREE.CylinderGeometry(.05, .05, .32, 8).translate(.18, .66, 0), '#164f92'],
			[new THREE.CylinderGeometry(.05, .05, .3, 8).rotateZ(Math.PI / 2).translate(.43, .32, 0), '#164f92']
		] : [
			[new THREE.CylinderGeometry(.06, .06, .5, 8).rotateZ(Math.PI / 2).translate(0, .08, 0), pipe],
			[new THREE.BoxGeometry(.16, .18, .16).translate(0, .1, 0), '#5e6b78'],
			[new THREE.CylinderGeometry(.02, .02, .2, 6).translate(0, .28, 0), '#7d8a96'],
			[new THREE.TorusGeometry(.1, .02, 4, 12).rotateX(Math.PI / 2).translate(0, .38, 0), '#c0392b']
		];

		const scale = MapSceneDecor.irrigationScale(e);
		const geometry = this.fuse(pieces.map(([piece, color]) => this.paint(piece.scale(scale, scale, scale), color)));

		geometry.computeBoundingBox();

		const box = geometry.boundingBox;

		return { geometry, owned: true, width: box.max.x - box.min.x, depth: box.max.z - box.min.z, height: box.max.y };

	}

	/**
	 * Un bâtiment de la structure, monté par `building.js` à ses cotes réelles, ou par son modèle téléversé dès que le binaire est
	 * lu, et partagé par ses poses : null s'il ne se compose pas, ou si la scène n'a pas sa fabrique. Un bâtiment repris dans
	 * l'éditeur se remonte (`saved()`).
	 */
	buildingGeometry(id) {

		if(this.buildingGeometries.has(id) === false) {

			const building = this.buildings.get(id);
			const model = (building === undefined) ? null : this.buildingModel(building);

			if(model === null) {
				this.buildingGeometries.set(id, null);
			} else {

				const geometry = this.fuse(model.parts.map(part => this.paint(part.geometry, part.color)));
				geometry.computeBoundingBox();

				this.buildingGeometries.set(id, geometry);

			}

		}

		return this.buildingGeometries.get(id);

	}

	buildingModel(building) {

		const buffer = (building.upload === null) ? null : (this.uploadBuffers.get(building.upload.url) ?? null);

		if(buffer !== null) {

			// Une couleur cuite s'écrit sans dièse
			const model = this.buildingUploaded(building.upload.index, buffer);

			return { ...model, parts: model.parts.map(part => ({ ...part, color: '#'+ part.color })) };

		}

		return (this.buildingBuild === null) ? null : this.buildingBuild(building);

	}

	/**
	 * Le binaire du modèle téléversé d'un bâtiment, lu une fois par adresse, après quoi ses poses se remontent. Un binaire que la
	 * route refuse laisse la composition. Le bâtiment a pu changer
	 * pendant la lecture : seul celui qui montre encore ce modèle se remonte.
	 */
	readUpload(building) {

		if(building.upload === null || this.buildingUploaded === null || this.uploadBuffers.has(building.upload.url)) {
			return;
		}

		const url = building.upload.url;

		this.uploadBuffers.set(url, null);

		SceneLoader.read(url, 'arrayBuffer').then(
			buffer => {

				this.uploadBuffers.set(url, buffer);

				if(this.closed === false && this.buildings.get(building.id)?.upload?.url === url) {
					this.remount(building.id);
				}

			},
			() => this.uploadBuffers.delete(url)
		);

	}

	makeBuilding(e) {

		const geometry = this.buildingGeometry(e.building);
		const box = geometry.boundingBox;

		return { geometry, owned: false, width: box.max.x - box.min.x, depth: box.max.z - box.min.z, height: box.max.y };

	}

	/**
	 * Un élément importé, centré sur son emprise au sol, ou le cube gris qui le remplace sans modèle lu. Il se monte une fois, partagé
	 * par ses poses, et se remonte quand son binaire arrive (`objectRemount()`).
	 */
	objectGeometry(id) {

		if(this.objectGeometries.has(id) === false) {

			const THREE = this.THREE;
			const object = this.objects.get(id);
			const buffer = (object.upload === null || this.buildingUploaded === null) ? null : (this.uploadBuffers.get(object.upload.url) ?? null);

			let pieces;
			let anchor = null;
			let trunk = false;

			if(buffer === null) {
				pieces = [this.paint(new THREE.BoxGeometry(MapSceneDecor.OBJECT_BOX, MapSceneDecor.OBJECT_BOX, MapSceneDecor.OBJECT_BOX).toNonIndexed().translate(0, MapSceneDecor.OBJECT_BOX / 2, 0), MapSceneDecor.OBJECT_COLOR)];
			} else {

				// Une couleur cuite s'écrit sans dièse
				const model = this.buildingUploaded(object.upload.index, buffer, object.family);

				pieces = model.parts.map(part => this.paint(part.geometry, '#'+ part.color));
				anchor = model.anchors?.arbre ?? null;
				trunk = model.parts.some(part => part.name === 'arbre');

			}

			// Le modèle se recentre avant la fusion, qui pose le point d'attache de chaque sommet (`mark()`) là où il est
			const box = new THREE.Box3();
			pieces.forEach(piece => {
				piece.computeBoundingBox();
				box.union(piece.boundingBox);
			});

			const centre = { x: (box.min.x + box.max.x) / 2, z: (box.min.z + box.max.z) / 2 };
			pieces.forEach(piece => piece.translate(-centre.x, 0, -centre.z));

			const geometry = this.fuse(pieces);
			geometry.computeBoundingBox();

			geometry.userData.foot = (anchor === null) ? { x: 0, z: 0 } : { x: anchor[0] - centre.x, z: anchor[2] - centre.z };
			geometry.userData.trunk = trunk;

			this.objectGeometries.set(id, geometry);

		}

		return this.objectGeometries.get(id);

	}

	/**
	 * Un élément de Nature rend un pied, comme un arbre du catalogue, que l'emprise, la sieste et les ombres lisent : posé sur son
	 * ancre `arbre`, ou au centre, et sans tronc quand le modèle n'a pas de pièce `arbre`, comme un buisson.
	 */
	makeObject(e) {

		const geometry = this.objectGeometry(e.object);
		const box = geometry.boundingBox;
		const width = box.max.x - box.min.x;
		const depth = box.max.z - box.min.z;

		const made = { geometry, owned: false, width, depth, height: box.max.y };

		if(e.def.family === 'tree') {

			const { x, z } = geometry.userData.foot;
			const half = Math.max(width, depth) / 2;

			made.feet = [{
				first: 0,
				last: geometry.attributes.position.count,
				x, z,
				half: this.footHalf(geometry, half),
				trunk: geometry.userData.trunk ? Math.max(MapSceneDecor.TRUNK_MIN / 2, MapSceneDecor.TRUNK_SHARE * half) : null
			}];

		}

		return made;

	}

	// La demi-largeur de ce qu'une géométrie pose au sol, ses sommets sous FOOT_HEIGHT, comme `foot()` le mesure d'un modèle cuit
	footHalf(geometry, fallback) {

		const position = geometry.attributes.position;
		let [x0, x1, z0, z1] = [Infinity, -Infinity, Infinity, -Infinity];

		for(let i = 0; i < position.count; i++) {
			if(position.getY(i) < MapSceneDecor.FOOT_HEIGHT) {
				x0 = Math.min(x0, position.getX(i));
				x1 = Math.max(x1, position.getX(i));
				z0 = Math.min(z0, position.getZ(i));
				z1 = Math.max(z1, position.getZ(i));
			}
		}

		return (x0 === Infinity) ? fallback : Math.max(x1 - x0, z1 - z0) / 2;

	}

	/**
	 * Le binaire du modèle d'un élément importé, lu une fois par adresse comme celui d'un bâtiment (`readUpload()`), après quoi ses
	 * poses se remontent. Seul un élément qui montre encore ce modèle se remonte.
	 */
	readObjectUpload(object) {

		if(object.upload === null || this.buildingUploaded === null) {
			return;
		}

		const url = object.upload.url;

		if(this.uploadBuffers.has(url)) {
			return;
		}

		this.uploadBuffers.set(url, null);

		SceneLoader.read(url, 'arrayBuffer').then(
			buffer => {

				this.uploadBuffers.set(url, buffer);

				if(this.closed === false && this.objects.get(object.id)?.upload?.url === url) {
					this.objectRemount(object.id);
				}

			},
			() => this.uploadBuffers.delete(url)
		);

	}

	/**
	 * Une conduite le long de son tracé. Un tuyau en surface est un tube à son diamètre, posé au sol ; enterré, des tirets au sol qui
	 * se redessinent en transparence (`through`). Une rampe est un tube de 32 mm sur ses supports, avec ses buses.
	 */
	makePipe(e) {

		const THREE = this.THREE;
		const D = MapSceneDecor;
		const path = D.smooth(e.trace);
		const diameter = e.diameter ?? D.PIPE_DEFAULT;
		// Un tube ne se coude qu'aux points de sa ligne : sur le relief, elle se recoupe au pas du sol pour le suivre
		const line = (this.geometry.relief === null) ? path : D.cut(path, MapSceneRelief.STEP);
		const ground = pieces => pieces.map(piece => this.onGround(piece, e));
		let made;

		if(e.def.ramp === true) {

			// Grandie comme la pompe et les vannes, et son tube élargi comme un tuyau. Chaque buse porte un asperseur blanc, qui se
			// détache du bleu du tube et du vert des cultures
			const scale = D.IRRIGATION_SCALE;
			const height = D.RAMP.height * scale;
			const radius = D.pipeWidth(D.RAMP.diameter) / 2;

			made = {
				geometry: this.fuse(ground([
					this.paint(this.tube(line, radius, height), D.PIPE_COLOR),
					...D.along(path, D.RAMP.post).map(q => this.paint(new THREE.CylinderGeometry(.012 * scale, .012 * scale, height, 4, 1, true).translate(q.x, height / 2, -q.n), '#7d8a96')),
					...D.along(path, D.RAMP.nozzle).slice(1).flatMap(q => [
						this.paint(new THREE.ConeGeometry(.03 * scale, .1 * scale, 4).translate(q.x, height + radius + .05 * scale, -q.n), '#164f92'),
						this.paint(new THREE.SphereGeometry(.035 * scale, 4, 2).translate(q.x, height + radius + .12 * scale, -q.n), '#ffffff')
					])
				])),
				thick: 2 * radius,
				height: height + radius + .16 * scale,
				flat: false
			};

		} else if(e.laying === 'buried') {

			const wide = D.pipeWidth(diameter);

			made = { geometry: this.fuse(ground([this.paint(this.dashes(path, wide), D.PIPE_COLOR)])), thick: wide, height: .1, flat: true, through: true };

		} else {

			const radius = D.pipeWidth(diameter) / 2;
			const lift = radius + D.PIPE_LIFT;

			made = {
				geometry: this.fuse(ground([
					this.paint(this.tube(line, radius, lift), D.PIPE_COLOR),
					...D.sleeves(path, D.SLEEVE).map(([a, b]) => this.paint(this.tube([a, b], radius * 1.3, lift), D.PIPE_COLOR))
				])),
				thick: 2 * radius,
				height: 2 * radius + D.PIPE_LIFT,
				flat: false
			};

		}

		// Le diamètre d'un tuyau se lit sur une pastille qui se pose d'abord au milieu de son tracé, d'où spreadDiameters() la fait glisser
		if(e.def.ramp !== true) {
			made.middle = D.pointAlong(path, .5);
		}

		made.path = path;
		made.owned = true;
		made.width = 2 * Math.max(...path.map(q => Math.abs(q.x))) + made.thick;
		made.depth = 2 * Math.max(...path.map(q => Math.abs(q.n))) + made.thick;

		return made;

	}

	// Une ligne du plan dont aucun segment ne dépasse `step` mètres, ses points gardés
	static cut(line, step) {

		const G = ScenePlanGeometry;
		const cut = [line[0]];

		for(let i = 1; i < line.length; i++) {

			const cuts = Math.max(1, Math.ceil(G.length(G.sub(line[i], line[i - 1])) / step));

			for(let k = 1; k <= cuts; k++) {
				cut.push(G.lerp(line[i - 1], line[i], k / cuts));
			}

		}

		return cut;

	}

	// Les points d'une ligne du plan tous les `step` mètres, le premier compris
	static along(line, step) {

		const G = ScenePlanGeometry;
		const points = [];
		let walked = 0;
		let next = 0;

		for(let i = 1; i < line.length; i++) {

			const length = G.length(G.sub(line[i], line[i - 1]));

			while(next <= walked + length) {
				points.push(G.lerp(line[i - 1], line[i], (length === 0) ? 0 : (next - walked) / length));
				next += step;
			}

			walked += length;

		}

		return points;

	}

	// Le point d'une ligne du plan à la part `share` de sa longueur, son départ quand elle n'en a aucune : le geste qui la plie peut
	// en confondre les points
	static pointAlong(line, share) {

		const G = ScenePlanGeometry;
		let left = MapSceneDecor.lengthOf(line) * share;

		for(let i = 1; i < line.length; i++) {

			const length = G.length(G.sub(line[i], line[i - 1]));

			if(length > 0 && left <= length) {
				return G.lerp(line[i - 1], line[i], left / length);
			}

			left -= length;

		}

		return line[0];

	}

	// Les manchons d'une ligne du plan, tous les `step` mètres sans ses deux bouts : chacun en deux points, à 15 cm de part et d'autre
	static sleeves(line, step) {

		const G = ScenePlanGeometry;
		const sleeves = [];
		let walked = 0;
		let next = step;
		const total = MapSceneDecor.lengthOf(line);

		for(let i = 1; i < line.length; i++) {

			const segment = G.sub(line[i], line[i - 1]);
			const length = G.length(segment);

			while(length > 0 && next <= walked + length && next < total - step / 2) {
				const at = G.lerp(line[i - 1], line[i], (next - walked) / length);
				const half = G.mul(segment, .15 / length);
				sleeves.push([G.sub(at, half), G.add(at, half)]);
				next += step;
			}

			walked += length;

		}

		return sleeves;

	}

	// Un tube de `radius` mètres le long d'une ligne du plan, son axe à `height` au-dessus du sol, en six pans et sans fond
	tube(line, radius, height) {

		const sides = 6;
		const points = line.map(q => ({ x: q.x, z: -q.n }));
		const last = points.length - 1;
		const positions = [];
		const index = [];

		points.forEach((p, i) => {

			const a = points[Math.max(0, i - 1)];
			const b = points[Math.min(last, i + 1)];
			const d = Math.hypot(b.x - a.x, b.z - a.z) || 1;
			const side = { x: (b.z - a.z) / d, z: -(b.x - a.x) / d };

			for(let k = 0; k < sides; k++) {
				const turn = k / sides * Math.PI * 2;
				positions.push(p.x + side.x * Math.cos(turn) * radius, height + Math.sin(turn) * radius, p.z + side.z * Math.cos(turn) * radius);
			}

		});

		for(let i = 0; i < last; i++) {
			for(let k = 0; k < sides; k++) {
				const a = i * sides + k;
				const b = i * sides + (k + 1) % sides;
				index.push(a, b, a + sides, b, b + sides, a + sides);
			}
		}

		return this.indexed(positions, index);

	}

	// Les tirets d'une ligne du plan, en bandes à plat de `wide` mètres posées juste au-dessus du sol
	dashes(line, wide) {

		const { DASH, GAP } = MapSceneDecor;
		const points = line.map(q => ({ x: q.x, z: -q.n }));
		const lift = .03;
		const positions = [];
		const index = [];
		let on = 0;

		const quad = (a, b) => {

			const d = Math.hypot(b.x - a.x, b.z - a.z) || 1;
			const w = { x: -(b.z - a.z) / d * wide / 2, z: (b.x - a.x) / d * wide / 2 };
			const base = positions.length / 3;

			positions.push(a.x + w.x, lift, a.z + w.z, a.x - w.x, lift, a.z - w.z, b.x + w.x, lift, b.z + w.z, b.x - w.x, lift, b.z - w.z);
			index.push(base, base + 2, base + 1, base + 1, base + 2, base + 3);

		};

		for(let i = 1; i < points.length; i++) {

			const a = points[i - 1];
			const b = points[i];
			const length = Math.hypot(b.x - a.x, b.z - a.z);
			const at = k => ({ x: a.x + (b.x - a.x) * k / length, z: a.z + (b.z - a.z) * k / length });
			let t = 0;

			while(t < length - 1e-6) {

				const step = Math.min((on < DASH) ? DASH - on : DASH + GAP - on, length - t);

				if(on < DASH) {
					quad(at(t), at(t + step));
				}

				t += step;
				on = (on + step) % (DASH + GAP);

			}

		}

		return this.indexed(positions, index);

	}

	indexed(positions, index) {

		const geometry = new this.THREE.BufferGeometry();

		geometry.setAttribute('position', new this.THREE.Float32BufferAttribute(positions, 3));
		geometry.setIndex(index);
		geometry.computeVertexNormals();

		return geometry;

	}

	// Une pièce bâtie ici prend les attributs d'un modèle cuit : une couleur par sommet, et pas de coordonnées de texture
	paint(geometry, color) {

		const tint = new this.THREE.Color(color);

		geometry.deleteAttribute('uv');
		geometry.setAttribute('color', new this.THREE.Float32BufferAttribute(Array.from({ length: geometry.attributes.position.count }, () => [tint.r, tint.g, tint.b]).flat(), 3));

		return geometry;

	}

	// Les pièces fondues en une géométrie, que la saison ne change pas
	fuse(pieces) {

		const geometry = this.addons.mergeGeometries(pieces);

		pieces.forEach(piece => piece.dispose());

		return this.mark(geometry, 0);

	}

	// Ce qu'un modèle cuit pose au sol, mesuré une fois sur sa géométrie : le carré qui contient ses sommets sous FOOT_HEIGHT
	foot(slug) {

		if(this.feetBySlug.has(slug) === false) {

			const position = this.base(slug, 0, false).attributes.position;
			let [x0, x1, z0, z1] = [Infinity, -Infinity, Infinity, -Infinity];

			for(let i = 0; i < position.count; i++) {
				if(position.getY(i) < MapSceneDecor.FOOT_HEIGHT) {
					x0 = Math.min(x0, position.getX(i));
					x1 = Math.max(x1, position.getX(i));
					z0 = Math.min(z0, position.getZ(i));
					z1 = Math.max(z1, position.getZ(i));
				}
			}

			this.feetBySlug.set(slug, (x0 === Infinity) ? { x: 0, z: 0, half: 0 } : { x: (x0 + x1) / 2, z: (z0 + z1) / 2, half: Math.max(x1 - x0, z1 - z0) / 2 });

		}

		return this.feetBySlug.get(slug);

	}

	build(e) {

		const THREE = this.THREE;

		this.drop(e);

		const made = this.make(e);

		const mesh = new THREE.Mesh(made.geometry, this.material);
		mesh.scale.setScalar(e.size);
		mesh.castShadow = (made.flat !== true);
		mesh.receiveShadow = true;
		mesh.customDepthMaterial = this.depthMaterial;

		e.root = new THREE.Group();
		e.root.add(mesh);

		// Un tuyau enterré se redessine en transparence par-dessus ce qui le cache
		if(made.through === true) {
			const through = new THREE.Mesh(made.geometry, this.xray);
			through.renderOrder = 6;
			e.root.add(through);
		}

		// Enfant du groupe, la pastille se masque avec son calque. Son gabarit est sur la scène et non sur la feuille de pose, que
		// seul reçoit celui qui peut poser, quand tous lisent la pastille. Une scène qui montre la structure en petit n'a pas de gabarit
		if(made.middle !== undefined && this.diameterLabel !== null) {
			const label = document.createElement('div');
			label.className = 'map-scene-decor-diameter';
			label.textContent = this.diameterLabel.replace('{value}', e.diameter ?? MapSceneDecor.PIPE_DEFAULT);
			e.label = new this.addons.CSS2DObject(label);
			e.label.position.set(made.middle.x, made.height, -made.middle.n);
			// spreadDiameters() la fait glisser à cette hauteur, qu'une pastille ramenée dans la scène quitte
			e.label.userData.height = made.height;
			e.root.add(e.label);
			this.spreadQueued = true;
		}

		e.root.visible = this.shows(e);

		if(e.kit === 'mare') {

			const r = MapSceneCrops.random(e.seed ?? e.id ?? 0);
			const spread = MapSceneDecor.RIPPLE_SPREAD;

			e.ripples = Array.from({ length: MapSceneDecor.RIPPLES }, (_, i) => {

				const ring = new THREE.Mesh(this.rippleGeometry, new THREE.MeshBasicMaterial({ color: '#ffffff', transparent: true, opacity: 0, depthWrite: false }));
				ring.position.set((r() * 2 - 1) * spread[0] * e.size, MapSceneDecor.RIPPLE_LIFT * e.size, (r() * 2 - 1) * spread[1] * e.size);
				e.root.add(ring);

				return { ring, phase: i / MapSceneDecor.RIPPLES };

			});

		}
		e.owned = made.owned;
		e.width = made.width * e.size;
		e.depth = made.depth * e.size;
		e.height = made.height * e.size;
		e.path = made.path;
		e.thick = made.thick;
		e.flat = (made.flat === true);
		e.feet = made.feet;

		if(this.pads !== undefined) {

			// L'emprise colorée d'un tracé en fait le tour, celle d'un enclos suit son tour, celle d'un kit d'arbres le pied de chacun.
			// Un tracé couvert d'une bande se confondrait avec un chemin de la même couleur. Le tour d'un tracé est un trait du geste,
			// cerné comme sur la Carte ; les autres emprises sont des aplats
			if(e.path !== undefined) {

				e.pad = this.stroke(this.pads.allowedLine);
				this.strokeAlong(e.pad, MapSceneDecor.around(e.path, (e.thick + 1) / 2), .8);
				this.onGround(e.pad.geometry, e);
				this.onGround(e.pad.children[0].geometry, e);

			} else {

				let pad;

				if(e.feet !== undefined) {
					const squares = e.feet.map(foot => new THREE.PlaneGeometry(1, 1).scale(2 * (foot.half * e.size + MapSceneDecor.FOOT_MARGIN) + .4, 2 * (foot.half * e.size + MapSceneDecor.FOOT_MARGIN) + .4, 1).rotateX(-Math.PI / 2).translate(foot.x * e.size, .12, foot.z * e.size));
					pad = this.addons.mergeGeometries(squares);
					squares.forEach(square => square.dispose());
				} else {
					// Sur le relief, le carreau se coupe au pas de la grille du sol pour le suivre (`fitGround()`)
					const cuts = size => (this.geometry.relief === null) ? 1 : Math.ceil(size / MapSceneRelief.STEP);
					pad = new THREE.PlaneGeometry(e.width + 1, e.depth + 1, cuts(e.width + 1), cuts(e.depth + 1)).rotateX(-Math.PI / 2).translate(0, .12, 0);
				}

				e.pad = new THREE.Mesh(pad, this.pads.allowed);
				e.pad.renderOrder = 5;

			}

			e.pad.visible = false;
			e.root.add(e.pad);
		}

		// Ce que fitGround() repart de poser à chaque settle(), à plat
		e.plain = null;

		if(this.geometry.relief !== null) {

			const kit = (e.feet !== undefined);

			e.plain = {
				kit: kit ? mesh.geometry.attributes.position.array.slice() : null,
				anchor: kit ? mesh.geometry.attributes.anchor.array.slice() : null,
				pad: (this.pads !== undefined && e.path === undefined) ? e.pad.geometry.attributes.position.array.slice() : null
			};

		}

		this.parent.add(e.root);
		this.settle(e);

	}

	drop(e) {

		if(e.root === undefined || e.root === null) {
			return;
		}

		const [mesh] = e.root.children;

		if(e.owned) {
			mesh.geometry.dispose();
		}

		e.pad?.geometry.dispose();
		e.pad?.children.forEach(casing => casing.geometry.dispose());
		e.ripples?.forEach(({ ring }) => ring.material.dispose());
		e.plinth?.geometry.dispose();

		if(this.geometry.relief !== null && MapSceneDecor.sunk(e)) {
			this.recarve(this.geometry.relief.carve(e, null), e);
		}

		// Retirer le groupe ne retire pas la pastille de la page, que CSS2DRenderer y a posée
		e.label?.element.remove();

		this.parent.remove(e.root);

		e.root = null;
		e.pad = null;
		e.ripples = null;
		e.label = null;
		e.plinth = null;
		e.plain = null;

		if(MapSceneDecor.BRIDGED.includes(e.kit)) {
			this.queueBridges();
		}

		this.changed();

	}

	settle(e) {

		// Sur le relief, un élément se pose à la hauteur du sol sous son centre, droit comme un arbre sur une pente. Un objet à large
		// base posée au sol s'incline sur elle. Un bâtiment reste de niveau, posé au plus haut du sol sous lui sur son soubassement.
		// Une étendue d'eau descend au plus bas, et le sol se creuse sous elle : l'eau ne se tient pas au-dessus d'une pente
		const founded = (this.geometry.relief !== null && MapSceneDecor.founded(e));
		const sunk = (this.geometry.relief !== null && MapSceneDecor.sunk(e));
		const tilted = (this.geometry.relief !== null && MapSceneDecor.tilts(e));
		let y = this.geometry.height(e.p.x, e.p.n);
		let basis = null;

		if(founded) {
			y = this.geometry.relief.highest(MapSceneDecor.rectangle(e));
		} else if(sunk) {
			y = this.sink(e);
		} else if(tilted) {
			({ y, basis } = this.tilt(e, y));
		}

		e.root.position.set(e.p.x, y, -e.p.n);
		// Repartir de la seule rotation de l'élément : celle de la pose précédente garderait sa pente
		e.root.rotation.x = 0;
		e.root.rotation.z = 0;
		e.root.rotation.y = e.angle;

		if(basis !== null) {
			e.root.quaternion.setFromRotationMatrix(basis);
		}

		// Le pointeur vise le volume par ses matrices, qui ne se recalculent qu'au rendu : un geste arrivé avant l'image suivante
		// viserait l'ancienne place
		e.root.updateMatrixWorld(true);

		if(founded) {
			this.found(e, y);
		}

		this.fitGround(e, y);

		e.bad = this.active ? this.refusal(e) : null;

		if(e.pad !== null && e.pad !== undefined) {
			if(e.path !== undefined) {
				e.pad.material = (e.bad === null) ? this.pads.allowedLine : this.pads.refusedLine;
			} else {
				e.pad.material = (e.bad === null) ? this.pads.allowed : this.pads.refused;
			}
		}

		// L'emprise d'un tracé n'en fait que le tour, d'une largeur fixe en mètres qui tombe à deux pixels au recul de la pose : le
		// tracé refusé rougit lui-même. Seule la pose refuse, et elle seule crée `refusedMaterial`
		if(e.def.traced === true) {
			e.root.children[0].material = (e.bad === null) ? this.material : this.refusedMaterial;
		}

		if(MapSceneDecor.BRIDGED.includes(e.kit)) {
			this.queueBridges();
		}

		this.changed();

	}

	// Un bâtiment, que le relief ne penche pas
	static founded(e) {
		return e.def.family === 'building';
	}

	// Un objet à large base posée au sol, que le relief penche (TILT_TALLEST)
	static tilts(e) {
		return e.def.tilts === true && (e.def.object !== true || e.height <= MapSceneDecor.TILT_TALLEST * Math.min(e.width, e.depth));
	}

	/**
	 * La hauteur et l'inclinaison d'un objet à large base sur le relief, d'après le sol aux quatre coins de son emprise : sa pente
	 * vers sa droite et vers son avant est celle de ses côtés, sa hauteur celle du milieu des quatre. Le sol n'est plan que par
	 * triangle, si bien qu'un coin ou le sol sous le centre, `centre`, peut passer au-dessus de ce plan : l'objet monte du plus grand
	 * de ces écarts, plutôt que d'y enfoncer une roue ou d'être traversé par une crête. L'axe de sa droite reste dans le plan
	 * vertical qu'il avait, son orientation ne change donc pas (`MapScene.yaw()`).
	 */
	tilt(e, centre) {

		const G = ScenePlanGeometry;
		const relief = this.geometry.relief;
		const { origin, w, u, width, length } = MapSceneDecor.rectangle(e);
		const at = (a, b) => {
			const corner = G.add(origin, G.add(G.mul(w, a * width), G.mul(u, b * length)));
			return relief.height(corner.x, corner.n);
		};
		const heights = [[at(0, 0), at(1, 0)], [at(0, 1), at(1, 1)]];

		const right = (heights[0][1] + heights[1][1] - heights[0][0] - heights[1][0]) / (2 * width);
		const front = (heights[1][0] + heights[1][1] - heights[0][0] - heights[0][1]) / (2 * length);
		const middle = (heights[0][0] + heights[0][1] + heights[1][0] + heights[1][1]) / 4;

		let lift = Math.max(0, centre - middle);

		for(const b of [0, 1]) {
			for(const a of [0, 1]) {
				lift = Math.max(lift, heights[b][a] - (middle + right * (a - .5) * width + front * (b - .5) * length));
			}
		}

		// Les axes de la scène portent le nord en z négatif
		const THREE = this.THREE;
		const across = new THREE.Vector3(w.x, right, -w.n).normalize();
		const up = new THREE.Vector3(u.x, front, -u.n).cross(across).normalize();
		const along = new THREE.Vector3().crossVectors(across, up);

		return { y: middle + lift, basis: new THREE.Matrix4().makeBasis(across, up, along) };

	}

	// Une étendue d'eau, une mare, un bassin, un puits. Un cours d'eau se trace et suit le sol
	static sunk(e) {
		return e.def.family === 'water' && e.def.traced !== true;
	}

	/**
	 * La hauteur d'une étendue d'eau, le plus bas du sol que le terrain porte sous son emprise (`MapSceneRelief.lowest()`), ou celui
	 * de son groupe quand son creux touche celui d'une autre, où le sol se creuse ensuite, le creux de sa place précédente rendu
	 * (`MapSceneRelief.carve()`).
	 */
	sink(e) {

		const relief = this.geometry.relief;

		this.recarve(relief.carve(e, MapSceneDecor.rectangle(e)), e);

		return relief.carves.get(e).top;

	}

	/**
	 * Après le creux de `e` (`MapSceneRelief.carve()`), les autres étendues dont le groupe a changé de niveau s'y mettent, sans se
	 * recreuser puisque le creux les a déjà refaites, puis ce qui est posé sur le sol changé s'y repose.
	 */
	recarve(box, e) {

		if(box === null) {
			return;
		}

		box.moved
			.filter(other => other.root !== null && other.root !== undefined)
			.forEach(other => {
				other.root.position.y = this.geometry.relief.carves.get(other).top;
				other.root.updateMatrixWorld(true);
			});

		this.resettle([box], e);
		this.regrid(box);
		this.carved();

	}

	/**
	 * La grille, la limite et le pli au-dessus du sol que `box` vient de creuser ou de rendre (`MapSceneRelief.carve()`). Leurs
	 * segments sont coupés aux axes et aux diagonales du sol (`groundLine()`), qu'un creux ne déplace pas : chaque point de la
	 * boite reprend sa levée sur la hauteur neuve, sans que rien se retrace.
	 */
	regrid(box) {

		if(this.grid === null) {
			return;
		}

		const relief = this.geometry.relief;

		for(const [line, lift] of [[this.grid, MapSceneDecor.GRID_LIFT], ...this.grid.children.map(child => [child, MapSceneDecor.LIMIT_LIFT])]) {

			const position = line.geometry.attributes.position;
			let changed = false;

			for(let i = 0; i < position.count; i++) {

				const x = position.getX(i);
				const n = -position.getZ(i);

				if(x >= box.x0 && x <= box.x1 && n >= box.n0 && n <= box.n1) {
					position.setY(i, relief.height(x, n) + lift);
					changed = true;
				}

			}

			if(changed) {
				position.needsUpdate = true;
				line.geometry.boundingSphere = null;
			}

		}

	}

	// Un bâtiment touche une boite du sol dès que son emprise la mord, puisqu'il se pose sur tout le sol sous lui, comme un objet
	// incliné sur les coins de la sienne. Un autre élément se pose sous son centre, et ne la touche que si son centre y tombe
	static rests(e, box) {

		const points = (MapSceneDecor.founded(e) || MapSceneDecor.tilts(e)) ? MapSceneDecor.corners(MapSceneDecor.rectangle(e)) : [e.p];

		return MapSceneDecor.apart(MapSceneDecor.extent(points), box) === false;

	}

	/**
	 * Les éléments posés dans les boites du sol qui vient d'être creusé ou rendu (`MapSceneRelief.carve()`), sauf `e` qui l'a
	 * changé : chacun se repose sur le sol neuf. Un tracé, un enclos ou un tuyau garde le drapé de sa pose, refait seulement quand
	 * il se refait.
	 */
	resettle(boxes, e) {

		const changed = boxes.filter(box => box !== null);

		if(changed.length === 0) {
			return;
		}

		this.elements
			.filter(other => other !== e && other.root !== null && other.root !== undefined && MapSceneDecor.sunk(other) === false && other.def.traced !== true && other.def.enclosure !== true && other.def.pipe !== true)
			.filter(other => changed.some(box => MapSceneDecor.rests(other, box)))
			.forEach(other => this.settle(other));

		this.queueBridges();

	}

	/**
	 * Le soubassement d'un objet posé de niveau à la hauteur `top`, refait à chaque pose puisque l'objet a pu bouger ou tourner :
	 * un enfant de sa racine, après le volume que `drop()` libère en premier, ramené dans le repère de la racine.
	 */
	found(e, top) {

		const THREE = this.THREE;

		if(e.plinth !== undefined && e.plinth !== null) {
			e.root.remove(e.plinth);
			e.plinth.geometry.dispose();
		}

		const geometry = this.geometry.relief.plinth(THREE, MapSceneDecor.rectangle(e), top).applyMatrix4(new THREE.Matrix4().copy(e.root.matrix).invert());

		// La teinte de la marge d'un chemin, de la terre battue
		e.plinth = new THREE.Mesh(this.fuse([this.paint(geometry, MapSceneDecor.TRACK[0][2])]), this.material);
		e.plinth.castShadow = true;
		e.plinth.receiveShadow = true;
		e.plinth.customDepthMaterial = this.depthMaterial;
		e.root.add(e.plinth);
		e.plinth.updateMatrixWorld(true);

	}

	/**
	 * Sur le relief, les plants d'un kit et l'emprise d'un élément suivent le sol sous eux, refaits à chaque pose depuis leur copie à
	 * plat (`e.plain`) : repartir de la pose précédente lui ajouterait la pente de la suivante. Chaque plant monte d'un seul tenant
	 * avec le sol sous son pied, comme un arbre de haie (`MapSceneRelief.conform()`) ; son volume est à la taille 1 sous un maillage
	 * qui grandit de `e.size`, l'écart s'y divise donc, et le point d'attache de ses feuilles monte avec lui, sans quoi elles se
	 * rétréciraient vers l'ancienne place. L'emprise suit le sol sommet par sommet, tournée dans les axes de la scène le temps du
	 * drapé, et descend de ce que la racine, posée à `y`, dépasse le sol sous le centre, d'où `conform()` part : sous un bâtiment
	 * posé au plus haut du sol, elle flotterait de cet écart. Un objet incliné (`tilt()`) suit déjà le sol d'un bloc : son plant ne
	 * monte pas une seconde fois, et son emprise se pose dans la scène avant de revenir dans son repère penché.
	 */
	fitGround(e, y) {

		if(e.plain === null) {
			return;
		}

		const relief = this.geometry.relief;
		const cos = Math.cos(e.angle);
		const sin = Math.sin(e.angle);

		if(e.plain.kit !== null && MapSceneDecor.tilts(e) === false) {

			const geometry = e.root.children[0].geometry;
			const position = geometry.attributes.position;
			const anchor = geometry.attributes.anchor;
			const blocks = e.feet.map(foot => ({ first: foot.first, last: foot.last, x: (foot.x * cos + foot.z * sin) * e.size, z: (foot.z * cos - foot.x * sin) * e.size }));

			position.array.set(e.plain.kit);
			relief.conform(geometry, e.p, blocks);

			for(let i = 0; i < position.count; i++) {
				const lift = (position.getY(i) - e.plain.kit[i * 3 + 1]) / e.size;
				position.setY(i, e.plain.kit[i * 3 + 1] + lift);
				anchor.setY(i, e.plain.anchor[i * 3 + 1] + lift);
			}

			anchor.needsUpdate = true;
			geometry.computeBoundingSphere();

		}

		if(e.plain.pad !== null) {

			const geometry = e.pad.geometry;
			const position = geometry.attributes.position;

			position.array.set(e.plain.pad);

			if(MapSceneDecor.tilts(e)) {

				// Comme le soubassement d'un bâtiment (`found()`), sous une racine posée à même la scène
				const matrix = e.root.matrix;

				geometry.applyMatrix4(matrix);

				for(let i = 0; i < position.count; i++) {
					position.setY(i, relief.height(position.getX(i), -position.getZ(i)) + e.plain.pad[i * 3 + 1]);
				}

				geometry.applyMatrix4(new this.THREE.Matrix4().copy(matrix).invert());

			} else {

				const above = y - relief.height(e.p.x, e.p.n);

				geometry.rotateY(e.angle);
				relief.conform(geometry, e.p);
				geometry.rotateY(-e.angle);

				for(let i = 0; i < position.count; i++) {
					position.setY(i, position.getY(i) - above);
				}

			}

			geometry.computeBoundingSphere();

		}

	}

	/**
	 * Le rectangle d'un élément au sol, `w` vers sa droite et `u` vers son avant, sous la forme des planches de `ScenePlanGeometry`.
	 */
	static axes(e) {
		return { right: { x: Math.cos(e.angle), n: Math.sin(e.angle) }, front: { x: Math.sin(e.angle), n: -Math.cos(e.angle) } };
	}

	static rectangle(e, grow = 0) {

		const G = ScenePlanGeometry;
		const { right, front } = MapSceneDecor.axes(e);
		const width = Math.max(.01, e.width + 2 * grow);
		const depth = Math.max(.01, e.depth + 2 * grow);

		return { origin: G.sub(e.p, G.add(G.mul(right, width / 2), G.mul(front, depth / 2))), w: right, u: front, width, length: depth };

	}

	// Le carré au sol d'un pied, `foot` en mètres de l'élément à la taille 1 vers sa droite (`x`) et son avant (`z`)
	static square(e, foot, grow = 0) {

		const G = ScenePlanGeometry;
		const { right, front } = MapSceneDecor.axes(e);
		const half = Math.max(.005, foot.half * e.size + MapSceneDecor.FOOT_MARGIN + grow);
		const centre = G.add(e.p, G.add(G.mul(right, foot.x * e.size), G.mul(front, foot.z * e.size)));

		return { origin: G.sub(centre, G.add(G.mul(right, half), G.mul(front, half))), w: right, u: front, width: 2 * half, length: 2 * half };

	}

	// Le carré au sol du tronc d'un pied, au même centre que son pied
	static trunk(e, foot) {

		const G = ScenePlanGeometry;
		const { right, front } = MapSceneDecor.axes(e);
		const half = foot.trunk * e.size;
		const centre = G.add(e.p, G.add(G.mul(right, foot.x * e.size), G.mul(front, foot.z * e.size)));

		return { origin: G.sub(centre, G.add(G.mul(right, half), G.mul(front, half))), w: right, u: front, width: 2 * half, length: 2 * half };

	}

	static corners(r) {

		const G = ScenePlanGeometry;

		return [r.origin, G.add(r.origin, G.mul(r.w, r.width)), G.add(r.origin, G.add(G.mul(r.w, r.width), G.mul(r.u, r.length))), G.add(r.origin, G.mul(r.u, r.length))];

	}

	// Deux rectangles orientés se recouvrent si aucun de leurs quatre axes ne les sépare
	static overlaps(a, b) {

		const A = MapSceneDecor.corners(a);
		const B = MapSceneDecor.corners(b);

		for(const axis of [a.w, a.u, b.w, b.u]) {

			const pa = A.map(p => ScenePlanGeometry.dot(p, axis));
			const pb = B.map(p => ScenePlanGeometry.dot(p, axis));

			if(Math.max(...pa) <= Math.min(...pb) + 1e-6 || Math.max(...pb) <= Math.min(...pa) + 1e-6) {
				return false;
			}

		}

		return true;

	}

	static extent(points) {
		return { x0: Math.min(...points.map(p => p.x)), x1: Math.max(...points.map(p => p.x)), n0: Math.min(...points.map(p => p.n)), n1: Math.max(...points.map(p => p.n)) };
	}

	static apart(a, b) {
		return a.x1 < b.x0 || b.x1 < a.x0 || a.n1 < b.n0 || b.n1 < a.n0;
	}

	/**
	 * Ce qu'un enclos peut clore : chaque parcelle tracée et chaque jardin tracé, avec son nom et sa surface. Un jardin sans tracé
	 * occupe sa parcelle, qui se clôt pour lui.
	 */
	getAreas(plan) {

		const areas = [];
		const zones = new Map(plan.zones.map(zone => [zone.id, zone.name]));

		for(const [id, points] of this.geometry.zones) {
			areas.push({ kind: 'zone', id, name: zones.get(id), points });
		}

		for(const [id, { plot, points, drawn }] of this.geometry.plots) {
			if(drawn) {
				areas.push({ kind: 'plot', id, name: plot.name, points });
			}
		}

		return areas.map(area => ({ ...area, surface: Math.abs(MapSceneDecor.area(area.points)) }));

	}

	// La plus petite emprise sous un point : un jardin avant la parcelle qui le porte
	areaAt(p) {
		return this.areas.filter(area => ScenePlanGeometry.inside(p, area.points)).sort((a, b) => a.surface - b.surface)[0] ?? null;
	}

	/**
	 * Le tour d'un enclos : chaque côté du contour écarté de FENCE_EDGE, ou davantage quand une planche ou une serre de l'emprise
	 * passe à moins de FENCE_CLEAR de lui ou le dépasse de moins de FENCE_REACH. Une planche voisine, ou une planche de l'emprise qui
	 * en sort de plus loin, ramène le côté vers l'intérieur, tant que celles de l'emprise gardent leur distance.
	 */
	loopOf(area) {

		const G = ScenePlanGeometry;
		const points = area.points;
		const count = points.length;
		const sign = (MapSceneDecor.area(points) >= 0) ? 1 : -1;
		const blocks = this.blockers.map(blocker => {
			const corners = MapSceneDecor.corners(blocker.rectangle);
			return { corners, mine: G.inside(G.mul(corners.reduce((sum, q) => G.add(sum, q), { x: 0, n: 0 }), 1 / 4), points) };
		});

		const gaps = points.map((a, i) => {

			const b = points[(i + 1) % count];
			const length = G.length(G.sub(b, a)) || 1;
			const d = G.mul(G.sub(b, a), 1 / length);
			let need = MapSceneDecor.FENCE_EDGE;
			let limit = Infinity;
			let pushed = false;

			for(const block of blocks) {

				const alongs = block.corners.map(q => G.dot(G.sub(q, a), d));
				const outs = block.corners.map(q => ((q.x - a.x) * d.n - (q.n - a.n) * d.x) * sign);
				const low = Math.min(...outs);
				const high = Math.max(...outs);

				if(Math.max(...alongs) < -MapSceneDecor.FENCE_CLEAR || Math.min(...alongs) > length + MapSceneDecor.FENCE_CLEAR || high < -MapSceneDecor.FENCE_CLEAR) {
					continue;
				}

				if(block.mine && high < MapSceneDecor.FENCE_REACH) {
					need = Math.max(need, high + MapSceneDecor.FENCE_CLEAR);
					pushed = true;
				} else if(low > -MapSceneDecor.FENCE_REACH) {
					limit = Math.min(limit, low - MapSceneDecor.FENCE_CLEAR);
				}

			}

			// Coincé entre les deux, le côté passe au ras des planches de l'emprise ; sans planche à elle, l'emprise cède la place aux voisines
			if(limit >= need) {
				return need;
			}

			return pushed ? Math.max(limit, need - MapSceneDecor.FENCE_CLEAR) : limit;

		});

		return MapSceneDecor.offsetLoop(points, gaps);

	}

	// Le portail en part du tour : face au chemin ou à la route posés les plus proches, sinon au milieu du plus long côté
	gateAt(loop) {

		const G = ScenePlanGeometry;
		const lines = this.elements.filter(e => MapSceneDecor.WALKED.includes(e.kit)).map(e => e.path.map(q => G.add(e.p, q)));
		const toLines = p => Math.min(Infinity, ...lines.flatMap(line => line.slice(1).map((q, j) => G.distanceToSegment(p, line[j], q))));
		let s = 0;
		let best = null;
		let longest = null;

		loop.forEach((a, i) => {

			const b = loop[(i + 1) % loop.length];
			const length = G.length(G.sub(b, a));

			if(longest === null || length > longest.length) {
				longest = { length, s: s + length / 2 };
			}

			for(let k = MapSceneDecor.GATE_ROOM; k <= length - MapSceneDecor.GATE_ROOM; k++) {

				const distance = toLines(G.lerp(a, b, k / length));

				if(distance < MapSceneDecor.GATE_REACH && (best === null || distance < best.distance)) {
					best = { distance, s: s + k };
				}

			}

			s += length;

		});

		return Math.round((best ?? longest).s / s * 1e4) / 1e4;

	}

	// Un enclos prend le tour de son emprise : posé au centre de sa boite, ses sommets rapportés à ce centre, son portail placé d'office
	enclose(e, area) {

		const loop = this.loopOf(area);
		const box = MapSceneDecor.extent(loop);

		e.area = { kind: area.kind, id: area.id, name: area.name };
		e.p = { x: (box.x0 + box.x1) / 2, n: (box.n0 + box.n1) / 2 };
		e.angle = 0;
		e.ring = loop.map(q => ScenePlanGeometry.sub(q, e.p));
		e.gate ??= this.gateAt(loop);

	}

	/**
	 * L'îlot où un point de la scène se pose, ou null quand aucun ne le prend : le plus proche de ceux dont la portée le couvre
	 * et qui le retrouvent. Un point s'enregistre sans le décalage de son îlot et se relit par la zone la plus proche
	 * (`ScenePlanLand.at()`) : posé sur un îlot qui ne le retrouverait pas ainsi, il changerait de place au rechargement. Un tracé
	 * qui se dessine (`MapSceneDecor.freehand()`) franchit la portée, au glissé comme au clic (`bounded` à faux) : seul ce
	 * relogement le borne, et il garde l'îlot le plus proche.
	 */
	islandOf(p, bounded = true) {

		const land = this.geometry.land;
		const islands = land.islands.filter(island => (bounded === false || ScenePlanGeometry.inside(p, island.reach)) && land.at(ScenePlanGeometry.sub(p, island.offset)) === island);

		// Deux portées se recouvrent dès que deux îlots sont rapprochés : l'écart à l'enveloppe ne se mesure que là
		if(islands.length < 2) {
			return islands[0] ?? null;
		}

		return islands.map(island => ({ island, away: land.away(p, island) })).sort((a, b) => a.away - b.away)[0].island;

	}

	coordinates(p) {

		// Un point de tracé au-delà de la portée s'enregistre sur l'îlot qui le relira, comme un point en deçà
		const island = this.islandOf(p, false) ?? this.geometry.land.islands[0];
		const real = ScenePlanGeometry.sub(p, island.offset);
		const round = value => Math.round(value * 1e7) / 1e7;

		return [round(this.origin.lng0 + real.x / this.origin.kx), round(this.origin.lat0 + real.n / this.origin.ky)];

	}

	// La force de la grille du terrain pour une case de `side` pixels de côté à l'écran
	static gridOpacity(side) {

		const { full, gone } = MapSceneDecor.GRID_FADE;

		return MapSceneDecor.GRID_OPACITY * Math.min(1, Math.max(0, (side - gone) / (full - gone)));

	}

	// Le coin haut gauche d'une étiquette centrée sur un point de l'écran, ramenée à SAY_EDGE pixels au moins des bords d'un cadre
	static held(label, frame) {

		const edge = MapSceneDecor.SAY_EDGE;
		const hold = (from, size, low, high) => Math.max(low + edge, Math.min(from - size / 2, high - edge - size));

		return { left: hold(label.x, label.width, frame.left, frame.right), top: hold(label.y, label.height, frame.top, frame.bottom) };

	}

	// La matière du décor teintée de `color`, qui garde son dessin, teinte de saison et rétrécissement compris : clone() ne recopie
	// ni l'un ni l'autre
	static tinted(material, color) {

		const tinted = material.clone();
		tinted.color.set(color);
		tinted.onBeforeCompile = material.onBeforeCompile;
		tinted.customProgramCacheKey = material.customProgramCacheKey;

		return tinted;

	}

	/**
	 * Le coin haut gauche `at` d'une étiquette de taille `size`, sortie de la boite des points `points` de l'écran : juste au-dessus,
	 * ou juste en dessous quand le haut du cadre manque. Sans points à éviter, hors de leur boite, ou sans place ni dessus ni dessous,
	 * elle reste en `at`.
	 */
	static clear(at, size, points, frame) {

		if(points.length === 0) {
			return at;
		}

		const edge = MapSceneDecor.SAY_EDGE;
		const xs = points.map(p => p.x);
		const ys = points.map(p => p.y);
		const box = { left: Math.min(...xs), right: Math.max(...xs), top: Math.min(...ys), bottom: Math.max(...ys) };

		if(at.left >= box.right || at.left + size.width <= box.left || at.top >= box.bottom || at.top + size.height <= box.top) {
			return at;
		}

		const above = box.top - edge - size.height;
		const below = box.bottom + edge;

		if(above >= frame.top + edge) {
			return { left: at.left, top: above };
		} else if(below + size.height <= frame.bottom - edge) {
			return { left: at.left, top: below };
		} else {
			return at;
		}

	}

	/**
	 * Ce que le terrain refuse à une emprise, ou null. `outside` : un coin n'est à portée d'aucun îlot. Un objet ne s'enregistre que
	 * par son point d'ancrage : son emprise peut couvrir deux îlots, il se rattache à celui de ce point. Un tracé enregistre chacun
	 * de ses points sans le décalage du sien : tous tiennent sur un même îlot, puisqu'une ligne qui enjamberait le pli entre deux
	 * îlots sauterait dans la réalité tout l'écart que la scène a resserré, d'où `apart`. Un tracé qui se dessine
	 * (`MapSceneDecor.freehand()`) franchit la portée, au glissé comme au clic ; un objet, un tuyau et une rampe la gardent.
	 */
	landRefusal(e, footprint) {

		const bounded = (MapSceneDecor.freehand(e.def) === false);
		const islands = new Set(footprint.pieces.flatMap(piece => MapSceneDecor.corners(piece.rectangle).map(p => this.islandOf(p, bounded))));

		if(islands.has(null)) {
			return 'outside';
		}

		if(e.def.traced === true) {
			return (islands.size === 1) ? null : 'apart';
		}

		return (this.islandOf(e.p) === null) ? 'outside' : null;

	}

	/**
	 * Ce qui interdit la pose : un coin hors du terrain, une planche ou une serre sous l'emprise, ou un autre élément chevauché. Un
	 * arbre ne gêne que par son pied, par son seul tronc un tracé à plat qui passe sous sa cime, et se plante dans une haie ;
	 * l'eau touche l'eau, les tracés se croisent et se raccordent entre eux, portail compris, et le tour d'un enclos ne gêne rien,
	 * sauf une route, que seuls le chemin, une autre route, le cours d'eau et le portail croisent.
	 * L'arrêt au bord pose la question des dizaines de fois par mouvement du pointeur : un obstacle dont la boite ne touche pas
	 * celle de l'emprise est écarté sans le test des quatre axes.
	 */
	refusal(e) {

		// Le tour d'un enclos se tire de son emprise, qu'il n'a pas d'autre place où quitter : il ne naît que d'une emprise choisie
		// sans clôture, et choisir celle qui en a une la sélectionne. Il ne bute que sur une route, qui ne se franchit pas
		if(e.def.enclosure === true) {
			const blocked = this.elements.some(other => {

				if(other === e || other.layer !== e.layer || other.def.barrier !== true) {
					return false;
				}

				const { enclosed, road } = MapSceneDecor.meeting(e, other);

				return MapSceneDecor.touches(enclosed, road);

			});

			if(blocked) {
				return 'element';
			}

			return this.beyondLimit(e) ? 'limit' : null;
		}

		const footprint = MapSceneDecor.footprint(e);

		const land = this.landRefusal(e, footprint);

		if(land !== null) {
			return land;
		}

		// Un tracé se borne à TRACE_LENGTH mètres, une rampe à la longueur de son kit
		if(e.def.traced === true && MapSceneDecor.lengthOf(e.path.map(q => ScenePlanGeometry.add(e.p, q))) > (e.def.longest ?? MapSceneDecor.TRACE_LENGTH)) {
			return 'long';
		}

		// Le glissé en cours relève un point par mètre, que la simplification du relâché réduit : le nombre de points et le poids ne
		// se jugent qu'après elle
		const drawing = (this.tracing !== null && this.tracing.e === e && this.tracing.freehand !== null);

		if(e.def.traced === true && drawing === false && e.trace.length > this.tracePoints) {
			return 'points';
		}

		if(drawing === false && this.beyondLimit(e)) {
			return 'limit';
		}

		if(this.onBed(e, footprint)) {
			return 'bed';
		}

		// L'emprise, et celle des troncs d'un kit d'arbres, se calculent une fois pour tous les autres éléments
		const trunks = (e.feet !== undefined) ? MapSceneDecor.footprint(e, 0, true) : null;

		if(this.elements.some(other => other !== e && this.clash(e, other, footprint, trunks))) {
			return 'element';
		}

		return null;

	}

	/**
	 * Ce qu'un élément pèse sous le plafond du décor, compté comme `DecorLib::getTraceCost()` le compte : un enclos sur le contour de
	 * son emprise, non sur son tour, que `loopOf()` en écarte. Un élément qui ne pèse rien ne passe jamais le plafond.
	 */
	cost(e) {

		const limit = this.traceLimit;

		if(e.wide !== null || this.layers.typeOf(e.layer) === limit.irrigation) {
			return 0;
		}

		if(e.def.traced === true) {
			return (MapSceneDecor.lengthOf(e.trace) + limit.corner * (e.trace.length - 2)) * (limit.weights[e.kit] ?? 1) + (limit.starts[e.kit] ?? 0);
		}

		const area = (e.area === null) ? undefined : this.areas.find(item => item.kind === e.area.kind && item.id === e.area.id);

		if(e.def.enclosure === true && area !== undefined) {
			return MapSceneDecor.loopLength(area.points) * (Number(e.model !== null) + limit.hedgeWeight * Number(e.hedge)) + limit.hedgeStart * Number(e.hedge);
		}

		return 0;

	}

	/**
	 * Le serveur compte les lignes enregistrées, retraits de la séance compris, qui ne partent qu'à « Terminer », et ne refuse que ce
	 * qui alourdit le décor au-delà du plafond : raccourcir un tracé reste permis à une structure qui le dépasse déjà.
	 */
	beyondLimit(e) {

		const cost = this.cost(e);

		if(cost <= e.weighed) {
			return false;
		}

		return [...this.elements, ...this.removals].reduce((total, other) => total + ((other === e) ? 0 : other.weighed), cost) > this.traceLimit.length;

	}

	clash(e, other, footprint, trunks) {

		// Deux calques se superposent : un élément ne gêne que ceux du sien
		if(e.layer !== other.layer) {
			return false;
		}

		const tree = item => (item.def.family === 'tree');
		const joins = item => (item.def.traced === true || item.def.joins === true);
		const hedged = (tree(e) && tree(other) && (e.def.traced === true || other.def.traced === true));

		// Une route ne se croise que par ce qui se marche, par un cours d'eau et par un portail : une haie, une bande fleurie ou une
		// clôture butent sur elle comme sur un objet
		const barred = (item, by) => (by.def.barrier === true && item.def.traced === true && item.def.water !== true && MapSceneDecor.WALKED.includes(item.kit) === false);
		const crossed = (joins(e) && joins(other) && barred(e, other) === false && barred(other, e) === false);

		// Ce qu'un enclos clôt reste libre, et son tour se franchit : il ne gêne rien, sauf une route, qui bute sur sa clôture et sa haie
		if((e.def.water && other.def.water) || hedged || crossed || (other.def.enclosure === true && e.def.barrier !== true)) {
			return false;
		}

		if(other.def.enclosure === true) {
			const { enclosed, road } = MapSceneDecor.meeting(other, e);
			return MapSceneDecor.touches(road, enclosed);
		}

		// Un tracé à plat et un plant ne se heurtent que par le tronc du plant, quel que soit celui des deux qu'on pose
		if(e.flat && other.feet !== undefined) {
			return MapSceneDecor.touches(footprint, MapSceneDecor.footprint(other, 0, true));
		}

		if(other.flat && e.feet !== undefined) {
			return MapSceneDecor.touches(trunks, MapSceneDecor.footprint(other));
		}

		return MapSceneDecor.touches(footprint, MapSceneDecor.footprint(other));

	}

	/**
	 * La place la plus proche de `to` qu'on atteigne depuis la place permise `from` sans entrer dans l'interdit : `to` elle-même si
	 * elle est permise, sinon le plus loin qu'on aille vers elle, tout droit ou un axe après l'autre, ce qui fait glisser l'élément le
	 * long du bord qu'il touche. Les axes sont ceux du plan et ceux des planches proches. Un pas fait au plus SNAP mètre, moins qu'une
	 * planche : aucun ne la saute.
	 */
	approach(e, from, to) {

		const G = ScenePlanGeometry;
		const open = p => {
			e.p = p;
			return this.refusal(e) === null;
		};

		if(open(to)) {
			return to;
		}

		const walk = (a, b) => {

			const steps = Math.ceil(G.length(G.sub(b, a)) / MapSceneDecor.SNAP);
			let last = a;

			for(let k = 1; k <= steps; k++) {

				const q = G.lerp(a, b, k / steps);

				if(open(q) === false) {

					// Au contact, la dernière place permise et la première refusée se resserrent à trois centimètres
					let low = 0;
					let high = 1;

					for(let pass = 0; pass < 5; pass++) {

						const mid = (low + high) / 2;

						if(open(G.lerp(last, q, mid))) {
							low = mid;
						} else {
							high = mid;
						}

					}

					return G.lerp(last, q, low);

				}

				last = q;

			}

			return last;

		};

		const near = MapSceneDecor.SLIDE_NEAR;
		const around = { x0: from.x - near, x1: from.x + near, n0: from.n - near, n1: from.n + near };
		const quarter = axis => ((Math.atan2(axis.n, axis.x) % (Math.PI / 2)) + Math.PI / 2) % (Math.PI / 2);
		const bases = [{ x: 1, n: 0 }];

		for(const blocker of this.blockers) {

			if(MapSceneDecor.apart(around, blocker.box)) {
				continue;
			}

			// Deux axes à moins de 2° l'un de l'autre, au quart de tour près, font le même glissé
			const turn = quarter(blocker.rectangle.u);

			if(bases.every(axis => {
				const gap = Math.abs(turn - quarter(axis));
				return Math.min(gap, Math.PI / 2 - gap) > Math.PI / 90;
			})) {
				bases.push(blocker.rectangle.u);
			}

		}

		const toward = (p, axis) => walk(p, G.add(p, G.mul(axis, G.dot(G.sub(to, p), axis))));
		const reached = [walk(from, to)];

		for(const a of bases) {
			for(const [along, across] of [[a, { x: -a.n, n: a.x }], [{ x: -a.n, n: a.x }, a]]) {

				const away = G.mul(across, -Math.sign(G.dot(G.sub(to, from), across)));

				for(const retreat of MapSceneDecor.RETREATS) {

					const start = G.add(from, G.mul(away, retreat));

					if(retreat > 0 && open(start) === false) {
						continue;
					}

					reached.push(toward(toward(start, along), across));

				}

			}
		}

		return reached.sort((p, q) => G.length(G.sub(p, to)) - G.length(G.sub(q, to)))[0];

	}

	/**
	 * La pose : le catalogue, les outils et les gestes. Rien de tout cela n'existe quand la page ne propose pas la pose.
	 */
	bind() {

		const THREE = this.THREE;

		this.active = false;
		this.selected = null;
		this.armed = null;
		this.held = null;
		this.picking = null;
		this.tracing = null;
		this.point = null;
		this.downOnTrace = null;
		this.allowed = null;
		this.journal = [];
		this.queue = Promise.resolve();
		this.family = null;
		this.thumbnails = false;
		this.chosen = null;

		this.refusals = JSON.parse(this.sheet.dataset.refusals);
		this.tracePoints = Number(this.sheet.dataset.tracePoints);
		this.traceLimit = JSON.parse(this.sheet.dataset.traceLimit);
		this.models = JSON.parse(this.sheet.dataset.models);
		this.notice = 0;
		this.button = this.container.qs('[data-scene-decor]');
		this.timeline = this.container.qs('.map-scene-timeline');
		this.tools = this.container.qs('[data-scene-decor-tools]');
		this.tiles = this.sheet.qsa('[data-kit]');
		this.createTile = this.sheet.qs('[data-scene-decor-create]');
		this.buildingTemplate = this.sheet.qs('template[data-scene-decor-building]');
		this.objectTemplate = this.sheet.qs('template[data-scene-decor-object]');
		this.importTiles = this.sheet.qsa('[data-scene-decor-import]');
		this.terrainGroups = this.sheet.qsa('[data-terrain]');
		this.terrainTiles = this.sheet.qsa('[data-terrain] > .map-scene-decor-tile');
		this.terrainOpening = null;
		this.zoneTerrainOpening = null;
		this.families = this.sheet.qs('.map-scene-decor-families');
		this.kits = this.sheet.qs('[data-scene-decor-kits]');
		this.place = this.sheet.qs('[data-scene-decor-area]');
		this.enclosure = this.sheet.qs('[data-scene-decor-enclosure]');
		this.fences = this.enclosure.qsa('[data-fence]');
		this.hedgeTile = this.enclosure.qs('[data-hedge]');
		this.areaTerrainGroups = this.enclosure.qsa('[data-area-terrain]');
		this.areaTerrainTiles = this.enclosure.qsa('[data-area-terrain] > .map-scene-decor-tile');
		this.cancelButton = this.container.qs('[data-scene-decor-cancel]');
		this.doneButton = this.container.qs('[data-scene-decor-done]');
		this.layerHead = this.sheet.qs('[data-scene-decor-layer]');
		this.masked = this.sheet.qs('[data-scene-decor-masked]');
		this.pipe = this.sheet.qs('[data-scene-decor-pipe]');
		this.pipeOther = this.pipe.qs('[data-scene-decor-pipe-other]');
		this.pipeInput = this.pipeOther.qs('input');
		this.pipeDraft = { diameter: MapSceneDecor.PIPE_DEFAULT, laying: 'surface' };
		this.pipeCustom = false;
		this.buildingBand = this.sheet.qs('[data-scene-decor-building-band]');
		this.buildingName = this.buildingBand.qs('[data-scene-decor-building-name]');
		this.buildingEdit = this.buildingBand.qs('[data-scene-decor-building-edit]');

		// Le geste a la couleur de celui de la Carte (--maptool-ring, reboot.css) : l'aplat d'une emprise, et le trait qui fait le
		// tour d'un tracé ou d'une parcelle choisie, plein sur son cerne d'encre (--maptool-ink), dont un trait translucide
		// laisserait voir l'encre au travers. Le refus garde son rouge, que la Carte n'a pas. Tous restent dans la liste
		// translucide de three, où ils se rangent par `renderOrder` avec les autres couches translucides au lieu de passer avant
		// toutes, et où le cerne se dessine avant son trait (`stroke()`)
		const style = getComputedStyle(this.container);
		const ring = style.getPropertyValue('--maptool-ring').trim();
		const pad = (color, opacity) => new THREE.MeshBasicMaterial({ color, transparent: true, opacity, depthWrite: false });

		this.pads = {
			allowed: pad(ring, .45),
			refused: pad('#d0563c', .6),
			allowedLine: pad(ring, 1),
			refusedLine: pad('#d0563c', 1),
			casing: pad(style.getPropertyValue('--maptool-ink').trim(), 1)
		};

		this.refusedMaterial = MapSceneDecor.tinted(this.material, '#d0563c');

		// Les éléments déjà posés prennent leur emprise colorée
		this.elements.forEach(e => this.build(e));

		const lines = opacity => new THREE.LineBasicMaterial({ color: '#ffffff', transparent: true, opacity, depthWrite: false });

		this.lines = { grid: lines(MapSceneDecor.GRID_OPACITY), limit: lines(.75), fine: lines(.5) };
		this.grid = null;
		this.limit = null;
		this.fold = null;
		this.fine = new THREE.LineSegments(new THREE.BufferGeometry(), this.lines.fine);
		this.fine.visible = false;
		this.fine.frustumCulled = false;
		this.parent.add(this.fine);

		// Le tour d'une emprise choisie, avant qu'un modèle la clôture
		this.outline = this.stroke(this.pads.allowedLine);
		this.outline.visible = false;
		this.outline.frustumCulled = false;
		this.outline.children[0].frustumCulled = false;
		this.outlined = null;
		this.parent.add(this.outline);

		const say = document.createElement('div');
		say.className = 'map-scene-decor-say';
		this.say = new this.addons.CSS2DObject(say);
		this.say.visible = false;
		this.parent.add(this.say);

		// Les poignées d'un tracé, que `showHandles()` réemploie d'un tracé à l'autre, et la pastille qui retire le point choisi
		this.handles = [];

		const unpoint = document.createElement('a');
		unpoint.className = 'map-scene-decor-tool map-scene-decor-unpoint';
		unpoint.innerHTML = '<span>'+ Lime.Asset.icon('x-lg') +'</span>';
		unpoint.addEventListener('click', () => this.unbend());
		this.unpoint = new this.addons.CSS2DObject(unpoint);
		this.unpoint.visible = false;
		this.parent.add(this.unpoint);
		this.unpointAt = null;
		this.handlesAt = [];

		// L'étiquette du diamètre que les poignées d'un tuyau écartent
		this.asideLabel = null;

		this.ray = new THREE.Raycaster();

		this.button.addEventListener('click', () => this.setMode(true));
		this.cancelButton.addEventListener('click', () => this.cancel());
		this.doneButton.addEventListener('click', () => this.done());
		this.sheet.qs('[data-scene-decor-finish]').addEventListener('click', () => this.done());

		// Sous 768 px, la scène s'allonge de la hauteur de la feuille posée sur son bas, que la largeur de l'écran fait varier. La
		// feuille réduite d'un tracé au téléphone (scene.css) ne la raccourcit pas : le canevas rapetissé déplacerait la vue sous le
		// doigt qui trace, et la place que la feuille rend revient à la scène
		this.phone = matchMedia('(max-width: 575px)');

		new ResizeObserver(([entry]) => {

			if(this.phone.matches && this.container.classList.contains('map-scene-tracing')) {
				return;
			}

			this.container.style.setProperty('--map-scene-decor-height', entry.borderBoxSize[0].blockSize +'px');

		}).observe(this.sheet);

		this.sheet.qsa('[data-family]', button => button.addEventListener('click', () => this.showFamily(button.dataset.family)));

		// Un kit qui se dessine porte un crayon sur sa vignette : on sait avant de le prendre qu'on le tracera au pointeur
		for(const tile of this.tiles) {
			this.bindTile(tile);
		}

		this.sheet.qsa('.map-scene-decor-tile-delete', link => this.bindDelete(link));

		for(const tile of this.terrainTiles) {
			const key = MapSceneDecor.terrainKey(tile);
			tile.addEventListener('click', () => this.chooseTerrain({ [key]: tile.dataset[key] }));
		}

		// La vignette « Comme la ferme » porte une valeur vide : la parcelle reprend le terrain de la structure
		for(const tile of this.areaTerrainTiles) {
			const key = MapSceneDecor.terrainKey(tile);
			tile.addEventListener('click', () => this.chooseAreaTerrain(this.enclosing(), { [key]: (tile.dataset[key] === '') ? null : tile.dataset[key] }));
		}

		for(const tile of this.fences) {
			tile.addEventListener('click', () => this.fence(current => ({ model: (current.model === tile.dataset.fence) ? null : tile.dataset.fence })));
		}

		this.hedgeTile.addEventListener('click', () => this.fence(current => ({ hedge: current.hedge === false })));

		// Comme Échap, sans letGo() : la feuille des clôtures n'est ouverte que les mains vides, aucun modèle ne suit le pointeur.
		// L'emprise choisie et la clôture choisie se lâchent, et le catalogue revient
		this.place.qs('[data-scene-decor-area-close]').addEventListener('click', () => {
			this.choose(null);
			this.select(null);
		});

		const rows = { families: this.families, kits: this.kits, diameters: this.pipe.qs('[data-scene-decor-diameters]'), enclosure: this.enclosure };

		this.scroll = new MapSceneScroll(rows, this.sheet.qsa('[data-scene-scroll]'));

		this.tools.qs('[data-tool="rotate"]').addEventListener('pointerdown', event => this.gesture(event, (e, p, from, before) => {

			const G = ScenePlanGeometry;
			const bearing = d => Math.atan2(d.x, -d.n);

			e.angle = Math.round((before.angle + bearing(G.sub(p, e.p)) - bearing(G.sub(from, e.p))) / MapSceneDecor.STEP) * MapSceneDecor.STEP;
			this.settle(e);

			return () => Math.round(((e.angle * 180 / Math.PI) % 360 + 360) % 360) +'°';

		}));

		this.tools.qs('[data-tool="size"]').addEventListener('pointerdown', event => this.gesture(event, (e, p, from, before) => {

			// La taille suit l'éloignement du pointeur, rapporté à celui du départ du geste
			const G = ScenePlanGeometry;
			const [min, max] = e.def.size;
			const ratio = G.length(G.sub(p, e.p)) / Math.max(.5, G.length(G.sub(from, e.p)));

			// Par pas de 5 %, comptés en vingtièmes pour garder une valeur ronde
			e.size = Math.max(min, Math.min(max, Math.round(before.size * ratio * 20) / 20));
			this.build(e);

			const format = value => value.toLocaleString('fr-FR', { maximumFractionDigits: 1 });

			return () => this.sheet.dataset.measure.replace('{width}', format(e.width)).replace('{depth}', format(e.depth));

		}));

		this.tools.qs('[data-tool="wide"]').addEventListener('click', () => this.cycle('wide', wide => this.sheet.dataset.wide.replace('{value}', wide)));
		this.tools.qs('[data-tool="model"]').addEventListener('click', () => this.cycle('model', model => this.models[model]));
		this.tools.qs('[data-tool="add"]').addEventListener('click', () => this.extend());

		this.tools.qs('[data-tool="remove"]').addEventListener('click', () => this.remove());

		// Un diamètre normalisé s'applique d'un clic, « Autre » ouvre la saisie, qui s'applique dès qu'elle est dans les bornes
		this.pipe.qsa('[data-diameter]', button => button.addEventListener('click', () => {

			this.pipeCustom = (button.dataset.diameter === '');

			if(this.pipeCustom) {
				this.showPipe();
				this.pipeInput.focus();
			} else {
				this.setPipe({ diameter: Number(button.dataset.diameter) });
			}

		}));

		this.pipeInput.addEventListener('input', () => {

			const value = Number(this.pipeInput.value);

			if(Number.isInteger(value) && value >= Number(this.pipeInput.min) && value <= Number(this.pipeInput.max)) {
				this.setPipe({ diameter: value });
			}

		});

		this.pipe.qsa('[data-laying]', button => button.addEventListener('click', () => this.setPipe({ laying: button.dataset.laying })));

		// Le geste sur un élément passe avant la caméra, qui ne le voit pas : l'élément se déplace, la vue ne bouge pas
		this.canvas.addEventListener('pointerdown', event => this.down(event), { capture: true });
		this.canvas.addEventListener('pointermove', event => this.hover(event));
		this.canvas.addEventListener('pointerup', event => this.up(event));
		this.canvas.addEventListener('dblclick', () => {
			if(this.active && this.tracing !== null) {
				this.endTrace();
			}
		});
		// Pendant un tracé, le bouton droit tourne la vue, et le menu part à l'appui sur certains systèmes : il ne lâche rien, Échap et
		// « Annuler » abandonnent le tracé
		this.canvas.addEventListener('contextmenu', event => {
			if(this.active && this.tracing !== null) {
				event.preventDefault();
			} else if(this.active && this.letGo()) {
				event.preventDefault();
			}
		});

		this.onKey = event => {

			if(this.active === false) {
				return;
			}

			// Le tracé qu'on pose répond d'abord : Échap y annule le glissé en cours, sinon retire le dernier geste, Entrée le termine
			// hors d'un glissé, que le relâché termine
			if(this.tracing !== null) {

				if(event.key === 'Escape' && this.tracing.freehand !== null) {
					this.cancelFreehand();
				} else if(event.key === 'Escape') {
					this.unplot();
				} else if(event.key === 'Enter' && this.tracing.freehand === null) {
					event.preventDefault();
					this.endTrace();
				}

				return;

			}

			if(event.key === 'Escape') {
				this.letGo();
				this.choose(null);
				this.select(null);
			}

			if((event.key === 'Delete' || event.key === 'Backspace') && ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) === false) {
				this.erase();
			}

		};

		window.addEventListener('keydown', this.onKey);

		// Les outils suivent l'élément choisi quand la vue bouge
		this.onView = () => {

			// Un milieu de segment ne porte sa poignée qu'assez long à l'écran, ce qu'un zoom change. Les poignées passent avant les
			// outils, qui évitent la pastille du point choisi
			if(this.active && (this.selected !== null || this.tracing !== null)) {
				this.showHandles();
			}

			if(this.active && this.selected !== null) {
				this.placeTools();
			}

		};

		this.controls.addEventListener('change', this.onView);

	}

	bindTile(tile) {
		tile.classList.toggle('map-scene-decor-tile-traced', MapSceneDecor.KITS[tile.dataset.kit]?.traced === true);
		tile.addEventListener('pointerdown', event => this.fromTile(tile, event));
	}

	/**
	 * La poubelle d'un bâtiment de la structure ou d'un élément importé confirme sous son nom, et dit qu'il quittera aussi le plan
	 * quand une pose le cite, retrait pas encore envoyé (`sendRemovals()`) compris, qui le cite encore en base. La confirmation du
	 * cadriciel écoute le clic sur le document en capture, avant la poubelle : elle se pose donc avant le clic, quand le doigt ou
	 * la souris se pose, ou quand le clavier y arrive.
	 */
	bindDelete(link) {

		const prepare = () => {

			const id = Number(link.getAttribute('post-id'));
			const object = (MapSceneDecor.objectOf(link.parentElement.qs('[data-kit]')) !== null);
			const posed = [...this.elements, ...this.removals].some(e => (object ? e.object : e.building) === id);
			const name = object ? this.objects.get(id).name : this.buildings.get(id).name;

			// Une fonction, pour que le nom passe tel quel : une chaîne de remplacement lirait `$&` comme un motif
			link.dataset.confirm = (posed ? link.dataset.confirmPosed : link.dataset.confirmFree).replace('{value}', () => name);

		};

		link.addEventListener('pointerdown', prepare);
		link.addEventListener('focus', prepare);

	}

	// Le bâtiment de la structure qu'une vignette pose, null pour un kit du catalogue
	static buildingOf(tile) {
		return (tile.dataset.building === undefined) ? null : Number(tile.dataset.building);
	}

	// L'élément importé qu'une vignette pose, null pour un kit du catalogue
	static objectOf(tile) {
		return (tile.dataset.object === undefined) ? null : Number(tile.dataset.object);
	}

	// Une vignette se propose si la scène sait monter ce qu'elle pose : les modèles cuits de son kit, ou le bâtiment qu'elle cite
	offers(tile) {

		const def = MapSceneDecor.KITS[tile.dataset.kit];

		return (
			def !== undefined &&
			this.available(def) &&
			(def.building !== true || this.buildingGeometry(MapSceneDecor.buildingOf(tile)) !== null) &&
			(def.object !== true || this.objects.has(MapSceneDecor.objectOf(tile)))
		);

	}

	setMode(on) {

		if(on === this.active) {
			return;
		}

		if(on && this.frozen !== null) {
			Lime.Alert.showStaticError(this.frozen);
			return;
		}

		this.letGo();
		this.choose(null);
		this.select(null);

		this.active = on;

		// La feuille se montre avant de se remplir : la famille choisie ne se fait voir dans sa rangée qu'une fois mesurable
		this.sheet.classList.toggle('hide', on === false);

		// Ce qui suit le remplissage découle de `active` et se pose quoi qu'il arrive : une erreur en remplissant la feuille laisserait
		// sinon la pose ouverte sans Annuler ni Terminer, et la scène avec le plancher et la bande d'avant
		try {

			if(on) {

				// La frise laisse sa place au catalogue : une lecture en cours s'arrête
				this.container.qs('[data-scene-pause]:not(.hide)')?.click();

				this.journal = [];
				this.terrainOpening = { ...this.terrain };
				this.zoneTerrainOpening = new Map([...this.zoneTerrains].map(([id, terrain]) => [id, { ...terrain }]));

				// Le plafond du décor ne se juge que pendant la pose, que seule la feuille ouvre : le bourg monte le même décor sans elle.
				// Chaque élément y entre avec ce que pèse sa ligne, celles du défaut comprises, que le premier geste adopte
				this.elements.forEach(e => {
					e.weighed = this.cost(e);
				});
				this.showThumbnails();
				this.showTerrain();
				this.showGrid();
				this.showFamily(this.family ?? this.sheet.qs('[data-family]').dataset.family);

			} else {
				this.canvas.style.cursor = '';
			}

		} finally {

			if(this.grid !== null) {
				this.grid.visible = on;
			}

			if(on) {
				MapScene.swap([this.button], [this.cancelButton, this.doneButton]);
			} else {
				MapScene.swap([this.cancelButton, this.doneButton], [this.button]);
			}
			this.timeline.classList.toggle('hide', on);
			this.container.classList.toggle('map-scene-posing', on);

			// Après la frise et la feuille, dont la place prise dans la scène décide de ce qu'un cadrage peut montrer
			this.switched();

		}

		this.elements.forEach(e => this.settle(e));
		this.refresh();

	}

	showFamily(family) {

		if(this.release() === false) {
			return;
		}

		this.disarm();
		this.family = family;

		this.sheet.qsa('[data-family]', button => button.classList.toggle('btn-selected', button.dataset.family === family));

		for(const tile of this.tiles) {
			tile.classList.toggle('hide', MapSceneDecor.KITS[tile.dataset.kit]?.family !== family || this.offers(tile) === false);
		}

		this.createTile.classList.toggle('hide', family !== 'building');
		this.importTiles.forEach(tile => tile.classList.toggle('hide', tile.dataset.sceneDecorImport !== family));

		for(const group of this.terrainGroups) {
			group.classList.toggle('hide', family !== 'terrain');
		}

		this.showLayer();
		this.showPipe();
		this.showBuilding();

		this.scroll.shade();

	}

	/**
	 * Les vignettes du terrain, peintes des couleurs de la scène : un sol se montre en deux planches et un pré en touffes et en
	 * fleurs sur l'herbe choisie, ils se repeignent donc quand l'herbe change.
	 */
	showTerrain() {

		const svg = body => 'data:image/svg+xml,'+ encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'+ body +'</svg>');
		const grass = MapScenePlanSeason.grassColor(this.terrain.grass);

		// Les clôtures se montrent sur l'herbe de la scène : sur le fond clair de la feuille, un grillage ou un ruban ne se voit pas
		this.enclosure.style.setProperty('--map-scene-decor-ground', grass);

		for(const tile of this.terrainTiles) {

			const key = MapSceneDecor.terrainKey(tile);
			const value = tile.dataset[key];

			tile.qs('img').src = svg(MapSceneDecor.terrainArt(key, value, grass));
			tile.classList.toggle('selected', value === this.terrain[key]);

		}

	}

	/**
	 * Le dessin d'une vignette du terrain : une herbe, un sol en deux planches ou un pré en touffes et en fleurs, sur l'herbe `grass`.
	 */
	static terrainArt(key, value, grass) {

		// Le pré d'une vignette est cerné d'un trait léger : la presque blanche se perdrait sans lui sur le fond clair de la feuille
		const meadow = color => '<rect x=".5" y=".5" width="63" height="63" rx="6" fill="'+ color +'" stroke="#000" stroke-opacity=".12"/>';

		if(key === 'grass') {
			return meadow(MapScenePlanSeason.grassColor(value)) +'<path d="M6 20h18M30 40h22M10 52h14M38 14h18" stroke="#000" stroke-opacity=".06" stroke-width="5" stroke-linecap="round"/>';
		} else if(key === 'soil') {
			const color = MapScene.SOILS[value];
			return meadow(grass) +'<rect x="8" y="14" width="48" height="13" rx="2" fill="'+ color +'"/><rect x="8" y="37" width="48" height="13" rx="2" fill="'+ color +'"/>';
		} else {
			return MapSceneMeadow.art(value, grass);
		}

	}

	/**
	 * Les vignettes du terrain de la parcelle `zone`, sur l'herbe qu'elle montre dans la scène, la sienne ou celle de la structure :
	 * « Comme la ferme » se peint du terrain de la structure, et la vignette choisie est celle de la valeur de la parcelle, ou
	 * « Comme la ferme » quand elle n'en a pas.
	 */
	showAreaTerrain(zone) {

		const svg = body => 'data:image/svg+xml,'+ encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'+ body +'</svg>');
		const terrain = this.zoneTerrains.get(zone.id);
		const grass = MapScenePlanSeason.grassColor(MapScene.readGrass(terrain.grass) ?? this.terrain.grass);

		for(const tile of this.areaTerrainTiles) {

			const key = MapSceneDecor.terrainKey(tile);
			const value = (tile.dataset[key] === '') ? null : tile.dataset[key];

			tile.qs('img').src = svg(MapSceneDecor.terrainArt(key, value ?? this.terrain[key], grass));
			tile.classList.toggle('selected', value === terrain[key]);

		}

	}

	/**
	 * Une herbe, un sol ou un pré choisi pour la parcelle `area` se voit aussitôt dans la scène et s'enregistre, les trois valeurs
	 * ensemble : une valeur absente de l'envoi y deviendrait null (`zone:doUpdateDecor`). Comme le terrain de la structure, il
	 * n'entre pas au journal des gestes : « Annuler » rend celui de l'ouverture.
	 */
	chooseAreaTerrain(area, change) {

		const before = this.zoneTerrains.get(area.id);
		const terrain = { ...before, ...change };

		if(terrain.grass === before.grass && terrain.soil === before.soil && terrain.meadow === before.meadow) {
			return;
		}

		this.zoneTerrains.set(area.id, terrain);
		this.paintZoneTerrain(area.id, terrain);
		this.showAreaTerrain(area);

		const values = { id: area.id, season: this.planSeason, decorGrass: terrain.grass ?? '', decorSoil: terrain.soil ?? '', decorMeadow: terrain.meadow ?? '' };

		// Dans la file des écritures du décor, avec celles de la structure : deux choix rapprochés arrivent dans l'ordre où ils ont été faits
		this.queue = this.queue
			.then(() => new Ajax.Query(this.sheet).url('/map/zone:doUpdateDecor').body(values).fetch())
			.catch(() => null);

	}

	/**
	 * Ce qu'une vignette du terrain choisit : l'herbe, le sol ou le pré, d'après l'attribut que `SceneUi` lui pose.
	 */
	static terrainKey(tile) {
		return ['grass', 'soil', 'meadow'].find(key => tile.dataset[key] !== undefined);
	}

	/**
	 * Une herbe, un sol ou un pré choisi se voit aussitôt dans la scène et s'enregistre. Le terrain n'entre pas au journal des gestes :
	 * « Annuler » le ramène à celui de l'ouverture.
	 */
	chooseTerrain(change) {

		const terrain = { ...this.terrain, ...change };

		if(terrain.grass === this.terrain.grass && terrain.soil === this.terrain.soil && terrain.meadow === this.terrain.meadow) {
			return;
		}

		this.terrain = terrain;
		this.paintTerrain(terrain);
		this.showTerrain();

		const values = { structure: this.sheet.dataset.structure, decorGrass: terrain.grass, decorSoil: terrain.soil, decorMeadow: terrain.meadow };

		// Dans la file des écritures du décor : deux choix rapprochés arrivent dans l'ordre où ils ont été faits
		this.queue = this.queue
			.then(() => new Ajax.Query(this.sheet).url('/map/configuration:doCreate').body(values).fetch())
			.catch(() => null);

	}

	/**
	 * Chaque kit rendu une fois, hors de la scène, à la première ouverture de la pose, à la graine 1.
	 */
	showThumbnails() {

		if(this.thumbnails) {
			return;
		}

		this.thumbnails = true;

		// Une vignette du catalogue montre son kit ou le bâtiment qu'elle cite, une vignette de clôture l'enclos carré de son modèle
		this.paintThumbnails([
			...[...this.tiles].filter(tile => this.offers(tile)).map(tile => [tile, { kit: tile.dataset.kit, building: MapSceneDecor.buildingOf(tile), object: MapSceneDecor.objectOf(tile) }]),
			...[...this.fences].map(tile => [tile, { kit: 'enclos', model: tile.dataset.fence }]),
			[this.hedgeTile, { kit: 'enclos', model: null, hedge: true }]
		]);

		// Une vignette dessinée remplace le rendu, où un tube de quelques centimètres ne se verrait pas
		for(const tile of this.tiles) {

			const icon = MapSceneDecor.KITS[tile.dataset.kit]?.icon;

			if(icon !== undefined) {
				tile.qs('img').src = icon;
			}

		}

	}

	paintThumbnails(samples) {

		const THREE = this.THREE;
		const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
		renderer.setSize(MapSceneDecor.THUMB, MapSceneDecor.THUMB, false);

		const stage = new THREE.Scene();
		stage.add(new THREE.HemisphereLight('#dcefff', '#93a06c', 1.6));

		const sun = new THREE.DirectionalLight('#fff1d6', 2.2);
		sun.position.set(3, 5, 4);
		stage.add(sun);

		const lens = new THREE.OrthographicCamera(-1, 1, 1, -1, -500, 500);
		const material = new THREE.MeshToonMaterial({ vertexColors: true });

		for(const [tile, { kit, ...sample }] of samples) {

			const made = this.make({ ...MapSceneDecor.element(kit, { x: 0, n: 0 }), id: 1, trace: MapSceneDecor.SAMPLE, ring: MapSceneDecor.ENCLOSURE_SAMPLE, gate: .9, ...sample });
			const mesh = new THREE.Mesh(made.geometry, material);
			stage.add(mesh);

			const box = new THREE.Box3().setFromObject(mesh);
			const center = box.getCenter(new THREE.Vector3());
			const radius = box.getSize(new THREE.Vector3()).length() / 2 * .92;

			Object.assign(lens, { left: -radius, right: radius, top: radius, bottom: -radius });
			lens.position.copy(center).add(new THREE.Vector3(1, .8, 1.3));
			lens.lookAt(center);
			lens.updateProjectionMatrix();

			renderer.render(stage, lens);
			tile.qs('img').src = renderer.domElement.toDataURL('image/png');

			stage.remove(mesh);

			if(made.owned) {
				made.geometry.dispose();
			}

		}

		material.dispose();
		renderer.dispose();
		renderer.forceContextLoss();

	}

	/**
	 * La grille de 5 m, tracée seulement là où la pose est permise, et le contour de ce terrain : son étendue montre la limite avant
	 * tout refus.
	 */
	showGrid() {

		if(this.grid !== null) {
			return;
		}

		const THREE = this.THREE;
		const cell = MapSceneDecor.CELL;
		const all = this.geometry.land.islands.flatMap(island => island.reach);

		this.bounds = {
			x0: Math.floor(Math.min(...all.map(p => p.x)) / cell) * cell,
			x1: Math.ceil(Math.max(...all.map(p => p.x)) / cell) * cell,
			n0: Math.floor(Math.min(...all.map(p => p.n)) / cell) * cell,
			n1: Math.ceil(Math.max(...all.map(p => p.n)) / cell) * cell
		};

		this.grid = new THREE.LineSegments(this.gridLines(this.bounds, cell), this.lines.grid);
		this.grid.frustumCulled = false;

		const { limit, fold } = this.limitLines();

		this.limit = new THREE.LineSegments(limit, this.lines.limit);
		this.fold = new THREE.LineSegments(fold, this.lines.limit);

		for(const line of [this.limit, this.fold]) {
			line.frustumCulled = false;
			this.grid.add(line);
		}

		this.parent.add(this.grid);

	}

	static lineGeometry(THREE, positions) {

		const geometry = new THREE.BufferGeometry();
		geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));

		return geometry;

	}

	/**
	 * Une droite du plan de `a` à `b`, posée à `lift` au-dessus du sol en segments de `positions`, deux points chacun. Sur le relief,
	 * elle se coupe là où elle passe d'un triangle du sol à un autre (`MapSceneRelief.section()`) : chaque segment reste dans un
	 * triangle, plan comme lui, et ne passe donc jamais sous le sol entre ses deux bouts.
	 */
	groundLine(positions, a, b, lift) {

		const relief = this.geometry.relief;

		if(relief === null) {
			positions.push(a.x, lift, -a.n, b.x, lift, -b.n);
			return;
		}

		const points = relief.section(a, b).map(t => ScenePlanGeometry.lerp(a, b, t));

		for(let k = 1; k < points.length; k++) {
			for(const p of [points[k - 1], points[k]]) {
				positions.push(p.x, relief.height(p.x, p.n) + lift, -p.n);
			}
		}

	}

	gridLines({ x0, x1, n0, n1 }, step) {

		const G = ScenePlanGeometry;
		const positions = [];

		const segment = (a, b) => {

			const cuts = Math.max(1, Math.round(G.length(G.sub(b, a)) / step));

			for(let k = 0; k < cuts; k++) {

				if(this.islandOf(G.lerp(a, b, (k + .5) / cuts)) === null) {
					continue;
				}

				this.groundLine(positions, G.lerp(a, b, k / cuts), G.lerp(a, b, (k + 1) / cuts), MapSceneDecor.GRID_LIFT);

			}

		};

		for(let x = x0; x <= x1 + 1e-6; x += step) {
			segment({ x, n: n0 }, { x, n: n1 });
		}

		for(let n = n0; n <= n1 + 1e-6; n += step) {
			segment({ x: x0, n }, { x: x1, n });
		}

		return MapSceneDecor.lineGeometry(this.THREE, positions);

	}

	// Le contour du terrain permis, suivi sur la trame de la grille : le bord de chaque case permise dont la voisine ne l'est pas.
	// Et le pli entre deux îlots, le bord de chaque case dont la voisine va à un autre îlot, que seul un tracé ne franchit pas
	limitLines() {

		const cell = MapSceneDecor.CELL;
		const { x0, x1, n0, n1 } = this.bounds;
		const nx = Math.round((x1 - x0) / cell);
		const nn = Math.round((n1 - n0) / cell);
		const opened = [];

		for(let i = 0; i < nx; i++) {
			opened.push(Array.from({ length: nn }, (_, j) => this.islandOf({ x: x0 + (i + .5) * cell, n: n0 + (j + .5) * cell })));
		}

		const open = (i, j) => (i >= 0 && j >= 0 && i < nx && j < nn && opened[i][j] !== null);
		const folded = (i, j, k, l) => (open(k, l) && opened[k][l] !== opened[i][j]);
		const positions = [];
		const folds = [];
		const edge = (ax, an, bx, bn) => this.groundLine(positions, { x: ax, n: an }, { x: bx, n: bn }, MapSceneDecor.LIMIT_LIFT);

		for(let i = 0; i < nx; i++) {
			for(let j = 0; j < nn; j++) {

				if(open(i, j) === false) {
					continue;
				}

				const x = x0 + i * cell;
				const n = n0 + j * cell;

				if(open(i - 1, j) === false) {
					edge(x, n, x, n + cell);
				}

				if(open(i + 1, j) === false) {
					edge(x + cell, n, x + cell, n + cell);
				}

				if(open(i, j - 1) === false) {
					edge(x, n, x + cell, n);
				}

				if(open(i, j + 1) === false) {
					edge(x, n + cell, x + cell, n + cell);
				}

				if(folded(i, j, i + 1, j)) {
					this.groundLine(folds, { x: x + cell, n }, { x: x + cell, n: n + cell }, MapSceneDecor.LIMIT_LIFT);
				}

				if(folded(i, j, i, j + 1)) {
					this.groundLine(folds, { x, n: n + cell }, { x: x + cell, n: n + cell }, MapSceneDecor.LIMIT_LIFT);
				}

			}
		}

		return { limit: MapSceneDecor.lineGeometry(this.THREE, positions), fold: MapSceneDecor.lineGeometry(this.THREE, folds) };

	}

	// La grille d'un mètre autour de l'élément qu'on porte
	showFine(e) {

		const half = Math.ceil(Math.max(e.width, e.depth) / 2) + 3;
		const { x0, x1, n0, n1 } = this.bounds;

		this.fine.geometry.dispose();
		this.fine.geometry = this.gridLines({
			x0: Math.max(x0, Math.round(e.p.x) - half),
			x1: Math.min(x1, Math.round(e.p.x) + half),
			n0: Math.max(n0, Math.round(e.p.n) - half),
			n1: Math.min(n1, Math.round(e.p.n) + half)
		}, MapSceneDecor.SNAP);
		this.fine.visible = true;

	}

	static snap(p) {
		return { x: Math.round(p.x / MapSceneDecor.SNAP) * MapSceneDecor.SNAP, n: Math.round(p.n / MapSceneDecor.SNAP) * MapSceneDecor.SNAP };
	}

	aim(event) {

		const box = this.canvas.getBoundingClientRect();

		this.camera.updateMatrixWorld();
		this.ray.setFromCamera(new this.THREE.Vector2((event.clientX - box.left) / box.width * 2 - 1, 1 - (event.clientY - box.top) / box.height * 2), this.camera);

	}

	// Le point du sol sous le pointeur. Sur le relief, celui du sol rendu : sur le plan y = 0, il tomberait d'autant plus loin du sol
	// visé que la vue est rasante et le sol haut
	pick(event) {

		this.aim(event);

		if(this.geometry.relief !== null) {
			const point = this.geometry.relief.onRelief(this.ray.ray, 0);
			return (point === null) ? null : { x: point.x, n: point.n };
		}

		const { origin, direction } = this.ray.ray;

		if(Math.abs(direction.y) < 1e-6) {
			return null;
		}

		const t = -origin.y / direction.y;

		return { x: origin.x + direction.x * t, n: -(origin.z + direction.z * t) };

	}

	/**
	 * Ce que le pointeur vise, le volume d'abord puis l'emprise au sol, élargie de quelques pixels pour un petit objet. Un personnage
	 * devant un élément le cache : on ne vise pas à travers lui, et lui-même ne se prend pas.
	 */
	target(event) {

		this.aim(event);

		const shown = object => {
			for(let o = object; o !== null; o = o.parent) {
				if(o.visible === false) {
					return false;
				}
			}
			return true;
		};

		let found = null;

		const hit = this.ray.intersectObjects([this.crew, ...this.elements.map(e => e.root)], true).find(intersection => intersection.object.isMesh && shown(intersection.object));

		if(hit !== undefined) {

			let o = hit.object;

			while(o !== null && o !== this.crew && this.elements.some(e => e.root === o) === false) {
				o = o.parent;
			}

			if(o === this.crew) {
				return { person: true, e: null };
			}

			found = this.elements.find(e => e.root === o) ?? null;

			// Le calque actif répond avant les autres : devant l'un de ses éléments, celui d'un autre calque laisse l'emprise répondre
			if(found === null || found.layer === this.layers.active) {
				return { person: false, e: found };
			}

		}

		const p = this.pick(event);

		if(p === null) {
			return { person: false, e: null };
		}

		const reach = Math.max(.4, 12 * (this.camera.top - this.camera.bottom) / this.camera.zoom / this.canvas.clientHeight);
		const under = [...this.elements].reverse().filter(item => item.root.visible && MapSceneDecor.footprint(item, reach).pieces.some(piece => ScenePlanGeometry.inside(p, MapSceneDecor.corners(piece.rectangle))));

		return { person: false, e: under.find(item => item.layer === this.layers.active) ?? found ?? under[0] ?? null };

	}

	keep(e) {
		return (e.bad === null) ? this.describe(e) : null;
	}

	describe(e) {
		return { p: e.p, trace: e.trace, angle: e.angle, size: e.size, wide: e.wide, model: e.model, hedge: e.hedge, gate: e.gate, diameter: e.diameter, laying: e.laying };
	}

	// Un élément revient à un état décrit, et se refait si son tracé, sa taille, sa largeur, son modèle, sa haie ou son portail change.
	// Un tracé ne se modifie qu'en prenant une ligne neuve (`lay()`) : l'état décrit garde la sienne
	restore(e, state) {

		const rebuilt = (e.trace !== state.trace || e.size !== state.size || e.wide !== state.wide || e.model !== state.model || e.hedge !== state.hedge || e.gate !== state.gate || e.diameter !== state.diameter || e.laying !== state.laying);

		Object.assign(e, state);

		if(rebuilt) {
			this.build(e);
		} else {
			this.settle(e);
		}

	}

	fromTile(tile, event) {

		if(event.button !== 0) {
			return;
		}

		event.preventDefault();

		// Le relâché d'un glisser parti d'une vignette atteint aussi la scène (`up()`), qui n'a pas vu l'appui : il n'y est pas un clic
		this.downAt = null;

		if(this.release() === false) {
			return;
		}

		const kit = tile.dataset.kit;
		const building = MapSceneDecor.buildingOf(tile);
		const object = MapSceneDecor.objectOf(tile);
		const was = (this.armed !== null && this.armed.kit === kit && this.armed.building === building && this.armed.object === object);
		const from = { x: event.clientX, y: event.clientY };
		const over = ev => document.elementFromPoint(ev.clientX, ev.clientY) === this.canvas;
		let dragged = false;

		const end = () => {
			window.removeEventListener('pointermove', move);
			window.removeEventListener('pointerup', up);
			window.removeEventListener('pointercancel', up);
			this.picking = null;
		};

		const move = ev => {

			const dx = ev.clientX - from.x;
			const dy = ev.clientY - from.y;

			if(dragged === false && Math.hypot(dx, dy) < 8) {
				return;
			}

			if(dragged === false) {

				// Au doigt, un glissé de côté fait défiler la rangée des vignettes : seul un glissé vers la scène emporte l'élément
				if(event.pointerType !== 'mouse' && Math.abs(dx) > Math.abs(dy)) {
					end();
					return;
				}

				dragged = true;
				this.arm(kit, ev, building, object);

			}

			ev.preventDefault();

			if(over(ev)) {
				this.carry(ev);
			}

		};

		const up = ev => {

			end();

			if(dragged) {

				if(ev.type === 'pointerup' && over(ev)) {

					this.carry(ev);

					// Un tracé glissé depuis sa vignette pose son premier point là où on le lâche
					if(this.tracing !== null) {
						this.plot(ev);
					} else if(this.land() === false) {
						this.disarm();
					}

				} else {
					this.disarm();
				}

			} else if(ev.type === 'pointercancel') {
				return;
			} else if(was) {
				this.disarm();
			} else {
				this.arm(kit, null, building, object);
			}

		};

		this.picking = end;

		window.addEventListener('pointermove', move);
		window.addEventListener('pointerup', up);
		window.addEventListener('pointercancel', up);

	}

	/**
	 * Un élément choisi au catalogue est « en main » : il suit le pointeur, un clic sur le terrain le pose. Il part du pointeur, ou
	 * du point regardé quand il vient d'un clic sur sa vignette.
	 */
	arm(kit, event, building = null, object = null) {

		// Un tracé repris par « Continuer » n'est pas en main : il revient à sa ligne d'avant
		if(this.tracing !== null) {
			this.dropTrace();
		}

		this.disarm();
		this.select(null);

		const p = (event === null) ? null : this.pick(event);
		const e = MapSceneDecor.element(kit, MapSceneDecor.snap(p ?? { x: this.controls.target.x, n: -this.controls.target.z }));

		// Un tuyau reprend le diamètre et la pose du dernier qu'on a réglé
		e.layer = this.layers.active;
		e.building = building;
		e.object = object;

		if(kit === 'tuyau') {
			Object.assign(e, this.pipeDraft);
		}

		this.armed = e;

		for(const tile of this.tiles) {
			tile.classList.toggle('selected', tile.dataset.kit === kit && MapSceneDecor.buildingOf(tile) === building && MapSceneDecor.objectOf(tile) === object);
		}

		// Un tracé se pose point par point, le premier au prochain clic
		if(e.def.traced === true) {

			this.tracing = { e, points: [], kept: 0, cursor: null, before: null, reversed: false, freehand: null, strokes: [] };
			this.aimTrace(p);

			// La boite d'un tuyau se règle dès la prise en main, avant le premier point
			this.refresh();
			return;

		}

		this.build(e);
		this.allowed = this.keep(e);

		this.showFine(e);
		this.refresh();

	}

	disarm() {

		if(this.armed === null) {
			return;
		}

		this.drop(this.armed);
		this.armed = null;
		this.tracing = null;
		this.allowed = null;
		this.fine.visible = false;
		this.hush();

		for(const tile of this.tiles) {
			tile.classList.remove('selected');
		}

		this.refresh();

	}

	carry(event) {

		const p = this.pick(event);
		const e = this.armed;

		if(this.tracing !== null) {
			this.aimTrace(p);
			return;
		}

		if(p === null || e === null) {
			return;
		}

		e.p = (this.allowed !== null) ? this.approach(e, this.allowed.p, MapSceneDecor.snap(p)) : MapSceneDecor.snap(p);
		this.seat(e);
		this.settle(e);

		if(e.bad === null) {
			this.allowed = this.keep(e);
			this.hush();
		} else {
			this.tell(e, this.refusalText(e), true);
		}

		this.showFine(e);
		this.refresh();

	}

	// Pose l'élément en main là où il est, s'il n'y est pas refusé
	land() {

		const e = this.armed;

		if(e === null || e.bad !== null) {
			return false;
		}

		this.armed = null;
		this.allowed = null;
		this.fine.visible = false;
		this.hush();

		for(const tile of this.tiles) {
			tile.classList.remove('selected');
		}

		this.elements.push(e);
		this.select(e);
		this.create(e, true);

		return true;

	}

	/**
	 * Un tracé se pose au clic, point par point : un clic pose un point au mètre, et la ligne va du dernier au pointeur. Le
	 * double-clic, Entrée ou « Terminer », seul des trois qu'ait le doigt, le termine. Il se dessine aussi d'un glissé
	 * (`startFreehand()`), que le relâché termine. Échap retire le dernier geste, puis abandonne le tracé. « Continuer » reprend de
	 * même un tracé posé depuis son premier ou son dernier point. `this.tracing` porte l'élément, ses points en coordonnées du plan,
	 * le pointeur, l'état d'avant d'un tracé repris (null pour un tracé neuf, l'élément en main), `kept`, le nombre de points
	 * qu'Échap ne retire pas, ceux du tracé repris, `reversed`, vrai pour un tracé repris par son premier point, dont les points sont
	 * alors tenus à l'envers, `freehand`, le glissé en cours ou null, et `strokes`, la place de chaque glissé gardé dans les points,
	 * qu'Échap retire d'un coup.
	 */
	aimTrace(p) {

		const tracing = this.tracing;
		const cursor = (p === null) ? null : MapSceneDecor.snap(p);

		// Le pointeur envoie bien plus d'un mouvement par mètre : le tracé ne se refait qu'à chaque point de la grille
		if(MapSceneDecor.samePoint(cursor, tracing.cursor)) {
			return;
		}

		tracing.cursor = cursor;
		this.shapeTrace();

	}

	// La ligne du tracé qu'on pose, jusqu'au pointeur ou au bout du glissé en cours, avec sa longueur à son bout ou le refus du point
	// visé. Un tracé neuf ne se monte qu'à partir de deux points
	shapeTrace() {

		const { e, cursor, freehand } = this.tracing;
		const points = (freehand === null) ? this.tracing.points : [...this.tracing.points, ...freehand.line];
		const line = (cursor === null || MapSceneDecor.samePoint(cursor, points.at(-1) ?? null)) ? points : [...points, cursor];

		if(line.length < 2) {

			this.drop(e);
			e.bad = (line.length === 1) ? this.startRefusal(e, line[0]) : null;

			if(e.bad === null) {
				this.hush();
			} else {
				this.tellPoint(line[0], this.refusalText(e), true);
			}

		} else {

			if(e.id === null && e.seed === null && MapSceneDecor.hedged(e)) {
				e.seed = MapSceneDecor.hedgeSeed(line[0], this.elements.length);
			}

			// Le geste ajoute toujours au bout de sa liste : un tracé repris par son premier point s'y tient à l'envers, et reprend
			// son sens en prenant sa ligne, qui garde ainsi en base l'ordre de ses points
			this.lay(e, this.tracing.reversed ? line.slice().reverse() : line);
			this.build(e);
			this.tell(e, (e.bad === null) ? this.sheet.dataset.length.replace('{value}', Math.round(MapSceneDecor.lengthOf(line))) : this.refusalText(e), e.bad !== null);

		}

		this.refresh();

		return line;

	}

	// Une emprise qui touche une planche ou une serre, pour un kit qui n'y passe pas : le premier point et la ligne entière se
	// jugent par la même règle
	onBed(e, footprint) {
		return (e.def.overBeds !== true && this.blockers.some(blocker => MapSceneDecor.touches(footprint, { pieces: [blocker], box: blocker.box })));
	}

	// Le premier point d'un tracé n'a pas encore de ligne que `refusal()` juge : il se refuse seul hors du terrain, ou sur une planche
	// ou une serre pour un kit qui n'y passe pas, puisque aucune ligne qui en part n'y échapperait
	startRefusal(e, p) {

		if(this.islandOf(p, MapSceneDecor.freehand(e.def) === false) === null) {
			return 'outside';
		}

		const box = MapSceneDecor.extent([p]);
		const point = { origin: p, w: { x: 1, n: 0 }, u: { x: 0, n: 1 }, width: 0, length: 0 };
		const footprint = { pieces: [{ rectangle: point, box }], box };

		if(this.onBed(e, footprint)) {
			return 'bed';
		}

		return null;

	}

	// Un tracé d'un seul point n'a pas encore de corps au-dessus duquel parler : le mot se pose au-dessus de son point
	tellPoint(at, text, refused = false) {

		this.sayRefused(refused);
		this.say.element.textContent = text;
		this.say.element.classList.add('map-scene-decor-say-trace');
		this.say.position.set(at.x, this.geometry.height(at.x, at.n), -at.n);
		this.say.visible = true;
		this.spreadQueued = true;

	}

	// Un clic pose le point visé, s'il ne fait pas refuser la ligne
	plot(event) {

		const tracing = this.tracing;
		const p = this.pick(event);

		if(p === null) {
			return;
		}

		tracing.cursor = MapSceneDecor.snap(p);

		// Le second clic d'un double-clic retombe sur le point que le premier a posé
		if(MapSceneDecor.samePoint(tracing.cursor, tracing.points.at(-1) ?? null)) {
			return;
		}

		const line = this.shapeTrace();

		if(tracing.e.bad === null) {
			tracing.points = line;
			this.showTracing();
		}

	}

	/**
	 * Un glissé dessine le tracé qu'on pose quand son kit le permet (`freehand()`) : au bouton gauche ou au doigt, sans les touches
	 * qui font tourner la vue (celles de MapControls), sur un tracé qui n'a encore aucun point à lui, ou depuis le rond de son bout
	 * libre. Ailleurs, l'appui reste à la vue. La vue n'est pas coupée de l'appui, elle cesse seulement de se déplacer : un second
	 * doigt la reprend pour zoomer et tourner, et annule le glissé. Sous 4 px au relâché, l'appui reste un clic (`up()`).
	 */
	startFreehand(event) {

		const tracing = this.tracing;

		if(MapSceneDecor.freehand(tracing.e.def) === false || event.shiftKey || event.ctrlKey || event.metaKey) {
			return;
		}

		const fromEnd = this.onFreeEnd(event);

		if(tracing.points.length > tracing.kept && fromEnd === false) {
			return;
		}

		const p = this.pick(event);

		if(p === null) {
			return;
		}

		const round = MapSceneDecor.centimetre;
		const start = { x: event.clientX, y: event.clientY };
		const freehand = { pointer: event.pointerId, line: fromEnd ? [] : [round(p)], joined: fromEnd, drawn: false, end: null };

		const move = ev => {

			if(ev.pointerId !== freehand.pointer || (freehand.drawn === false && Math.hypot(ev.clientX - start.x, ev.clientY - start.y) < 4)) {
				return;
			}

			freehand.drawn = true;

			const q = this.pick(ev);
			const last = freehand.line.at(-1) ?? tracing.points.at(-1);

			if(q === null || ScenePlanGeometry.length(ScenePlanGeometry.sub(q, last)) < MapSceneDecor.FREEHAND_STEP) {
				return;
			}

			freehand.line.push(round(q));
			this.shapeTrace();

		};

		const cancel = ev => {
			if(ev.pointerId === freehand.pointer) {
				this.cancelFreehand();
			}
		};

		freehand.end = () => {
			this.canvas.removeEventListener('pointermove', move);
			this.canvas.removeEventListener('pointercancel', cancel);
			this.controls.enablePan = true;
		};

		this.controls.enablePan = false;
		this.canvas.addEventListener('pointermove', move);
		this.canvas.addEventListener('pointercancel', cancel);

		tracing.cursor = null;
		tracing.freehand = freehand;

	}

	// Les points d'un glissé se gardent au centimètre, comme les coordonnées qui les enregistrent
	static centimetre(p) {
		return { x: Math.round(p.x * 100) / 100, n: Math.round(p.n * 100) / 100 };
	}

	// L'appui tombe sur le rond du bout libre du tracé qu'on pose, à une demi-cible au plus de son centre
	onFreeEnd(event) {

		const end = this.tracing.points.at(-1);

		if(end === undefined) {
			return false;
		}

		const box = this.canvas.getBoundingClientRect();
		const size = parseFloat(getComputedStyle(this.tools).getPropertyValue('--scene-control'));
		const [at] = this.project([end], box);

		return (Math.hypot(event.clientX - at.x, event.clientY - at.y) <= size / 2);

	}

	/**
	 * Le relâché d'un glissé : rend faux quand l'appui n'a pas bougé de 4 px, qui reste un clic. Une main de moins de FREEHAND_STEP
	 * pose un point comme un clic, pour qu'une main qui tremble sur un clic ne dessine pas un bout de ligne trop court pour tenir.
	 * Le point se pose ici par `plot()` : au-delà de 4 px, `up()` jetterait le geste sans rien poser. Sinon la main se simplifie, à une
	 * tolérance qui double jusqu'à ce que le tracé tienne sous le plafond de points et sous le poids du décor, ou jusqu'à ses deux
	 * bouts, qui bornent la boucle. Le point de départ d'un glissé parti du bout libre est ce bout, compté une fois. Le relâché
	 * termine le tracé, et refusé, le tracé reste en main avec le glissé, qu'Échap retire d'un coup. Le point du relâché finit la
	 * ligne, à moins d'un mètre du dernier relevé s'il le faut : c'est là que la main a voulu s'arrêter.
	 */
	finishFreehand(event) {

		const tracing = this.tracing;
		const freehand = tracing.freehand;

		this.dropFreehand();

		if(freehand.drawn === false) {
			return false;
		}

		const from = tracing.points.length;
		const head = freehand.joined ? [tracing.points.at(-1)] : [];
		const hand = [...head, ...freehand.line];
		const p = this.pick(event);

		if(p !== null && hand.length > 0 && MapSceneDecor.samePoint(MapSceneDecor.centimetre(p), hand.at(-1)) === false) {
			hand.push(MapSceneDecor.centimetre(p));
		}

		if(hand.length < 2 || MapSceneDecor.lengthOf(hand) < MapSceneDecor.FREEHAND_STEP) {
			this.plot(event);
			return true;
		}

		const fits = added => {
			this.lay(tracing.e, [...tracing.points, ...added]);
			return (from + added.length <= this.tracePoints && this.beyondLimit(tracing.e) === false);
		};

		let tolerance = MapSceneDecor.FREEHAND_TOLERANCE;
		let kept = MapSceneDecor.simplify(hand, tolerance);

		while(kept.length > 2 && fits(kept.slice(head.length)) === false) {
			tolerance *= 2;
			kept = MapSceneDecor.simplify(hand, tolerance);
		}

		const added = kept.slice(head.length);

		tracing.strokes.push({ from, to: from + added.length });
		tracing.points = [...tracing.points, ...added];
		this.endTrace();

		return true;

	}

	// Le glissé en cours cesse : la vue se déplace de nouveau
	dropFreehand() {

		const freehand = this.tracing.freehand;

		if(freehand !== null) {
			freehand.end();
			this.tracing.freehand = null;
		}

	}

	// Échap, un second doigt ou un pointeur perdu annulent le glissé en cours : le tracé revient à ses points d'avant
	cancelFreehand() {
		this.dropFreehand();
		this.shapeTrace();
	}

	/**
	 * Une vignette ou une famille ne fait pas perdre les points d'un tracé commencé : il se termine d'abord, et trop court ou
	 * refusé, il le dit et reste en main. Rend vrai quand la main est libre.
	 */
	release() {

		if(this.tracing === null || this.tracing.points.length === this.tracing.kept) {
			return true;
		}

		this.endTrace();

		return (this.tracing === null);

	}

	endTrace() {

		const tracing = this.tracing;
		const e = tracing.e;

		this.dropFreehand();

		if(tracing.points.length === tracing.kept) {
			this.dropTrace();
			return;
		}

		if(tracing.points.length < 2) {
			this.tellPoint(tracing.points[0], this.refusalText(e, 'short'), true);
			return;
		}

		if(MapSceneDecor.lengthOf(tracing.points) < MapSceneDecor.TRACE_MIN) {
			this.tell(e, this.refusalText(e, 'short'), true);
			return;
		}

		tracing.cursor = null;
		this.shapeTrace();

		if(e.bad !== null) {
			return;
		}

		this.tracing = null;
		this.hush();

		if(tracing.before === null) {
			this.land();
		} else {
			this.update(e, tracing.before, this.describe(e), true);
			this.refresh();
		}

	}

	// Échap retire le dernier geste : un glissé gardé en main d'un coup, un point posé au clic seul
	unplot() {

		const tracing = this.tracing;
		const stroke = tracing.strokes.at(-1);

		if(stroke !== undefined && stroke.to === tracing.points.length) {
			tracing.strokes.pop();
			tracing.points = tracing.points.slice(0, stroke.from);
			this.shapeTrace();
		} else if(tracing.points.length > tracing.kept) {
			tracing.points = tracing.points.slice(0, -1);
			this.shapeTrace();
		} else {
			this.dropTrace();
		}

	}

	// Le tracé qu'on pose s'abandonne : neuf, il retourne au catalogue ; repris, il revient à sa ligne d'avant
	dropTrace() {

		const tracing = this.tracing;

		this.dropFreehand();

		if(tracing.before === null) {
			this.disarm();
			return;
		}

		this.tracing = null;
		this.hush();
		this.restore(tracing.e, tracing.before);
		this.refresh();

	}

	// « Terminer » termine d'abord le tracé qu'on pose, le doigt n'ayant ni double-clic ni Entrée ; sans point posé, il clôt la pose
	done() {

		if(this.tracing !== null && this.tracing.points.length > this.tracing.kept) {
			this.endTrace();
		} else {
			this.sendRemovals();
			this.setMode(false);
		}

	}

	/**
	 * Les poignées du tracé choisi, ou de celui qu'on pose : un rond sur chaque point, et au milieu de chaque segment assez long à
	 * l'écran une poignée qui y insère un point. Celles du tracé qu'on pose ne se prennent pas : un clic sur l'un de ses points y
	 * pose le suivant, ce qui referme une clôture sur son départ. La pastille qui retire le point choisi se montre à côté de lui,
	 * tant que le tracé en garde plus de deux, et l'étiquette du diamètre d'un tuyau s'écarte de ses poignées. Toutes les places se
	 * mesurent, puis toutes s'écrivent ; les poignées et la pastille ne gagnent leur place qu'au rendu suivant.
	 */
	showHandles() {

		const tracing = this.tracing;
		let traced = null;
		let points = [];

		if(this.active && tracing !== null) {
			traced = tracing.e;
			// Le point visé n'a son rond que là où il peut se poser : sous un refus, le rond le ferait croire posé
			points = (tracing.cursor === null || traced.bad !== null) ? tracing.points : [...tracing.points, tracing.cursor];
		} else if(this.active && this.selected?.def.traced === true) {
			traced = this.selected;
			points = MapSceneDecor.lineOf(this.selected);
		}

		// Un tracé défait par « Annuler » ou par un refus du serveur a pu perdre le point choisi
		const chosen = (tracing === null && this.point !== null && this.point < points.length) ? this.point : null;

		const box = this.canvas.getBoundingClientRect();
		const size = parseFloat(getComputedStyle(this.tools).getPropertyValue('--scene-control'));
		const screen = this.project(points, box);
		const shown = MapSceneDecor.sparse(screen, size, chosen);
		const middles = (tracing === null) ? this.middles(screen, size) : [];
		const handlesAt = [...shown.map(index => screen[index]), ...middles.map(index => ({ x: (screen[index].x + screen[index + 1].x) / 2, y: (screen[index].y + screen[index + 1].y) / 2 }))];

		let aside = null;

		if(chosen !== null && points.length > 2) {

			const bar = this.container.qs('.map-scene-bar').getBoundingClientRect();
			const sheet = this.sheet.getBoundingClientRect();
			const margin = parseFloat(getComputedStyle(this.tools).left);

			aside = this.aside(screen[chosen], handlesAt, { ...this.free(box, bar, sheet, margin), left: box.left + margin, right: box.right - margin }, size);

		}

		const wanted = [
			...shown.map(index => ({ kind: 'point', index, p: points[index] })),
			...middles.map(index => ({ kind: 'middle', index, p: ScenePlanGeometry.lerp(points[index], points[index + 1], .5) }))
		];

		while(this.handles.length < wanted.length) {
			this.handles.push(this.handle());
		}

		this.handles.forEach((handle, k) => {

			const want = wanted[k];

			handle.visible = (want !== undefined);

			if(want !== undefined) {
				handle.position.set(want.p.x, this.geometry.height(want.p.x, want.p.n), -want.p.n);
				handle.element.dataset.handle = want.kind;
				handle.element.dataset.index = want.index;
				handle.element.classList.toggle('selected', want.kind === 'point' && want.index === chosen);
				handle.element.classList.toggle('map-scene-decor-handle-still', tracing !== null);
			}

		});

		this.unpoint.visible = (aside !== null);
		this.unpointAt = aside;
		this.handlesAt = handlesAt;

		if(aside !== null) {
			this.unpoint.position.copy(this.unproject(aside, box));
		}

		const label = (traced?.label ?? null);

		if(label !== this.asideLabel) {
			this.asideLabel?.element.classList.remove('map-scene-decor-diameter-aside');
			label?.element.classList.add('map-scene-decor-diameter-aside');
			this.asideLabel = label;
			this.spreadQueued = true;
		}

	}

	// La place à l'écran des points d'une ligne du plan, dans le repère de la fenêtre, avec leur profondeur pour y revenir
	project(points, box) {

		const vector = new this.THREE.Vector3();

		this.camera.updateMatrixWorld();

		return points.map(p => {
			vector.set(p.x, this.geometry.height(p.x, p.n), -p.n).project(this.camera);
			return { x: box.left + (vector.x + 1) / 2 * box.width, y: box.top + (1 - vector.y) / 2 * box.height, z: vector.z };
		});

	}

	// Le point de la scène qui se voit à une place de l'écran, à la profondeur qu'elle porte
	unproject(at, box) {
		return new this.THREE.Vector3((at.x - box.left) / box.width * 2 - 1, 1 - (at.y - box.top) / box.height * 2, at.z).unproject(this.camera);
	}

	// Les segments d'une ligne assez longs à l'écran pour porter une poignée en leur milieu, par le rang de leur premier point. Il y
	// faut deux cibles de long : la zone d'une poignée de milieu ne recouvre alors ni celle d'un bout ni celle de l'autre
	middles(screen, size) {

		return screen.slice(1)
			.map((p, index) => index)
			.filter(index => Math.hypot(screen[index + 1].x - screen[index].x, screen[index + 1].y - screen[index].y) >= 2 * size);

	}

	/**
	 * La place à l'écran de la pastille qui retire le point choisi : la première des quatre diagonales, à une cible du point sur
	 * chaque axe, qui tient dans la place libre de la scène sans que sa cible chevauche celle d'une poignée. Une diagonale qui
	 * sortirait de la place libre y est ramenée, pour que la pastille reste toujours à portée, et ne passe qu'après celles qui y
	 * tiennent sans chevaucher.
	 */
	aside(from, handles, area, size) {

		const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

		const places = [[1, -1], [-1, -1], [1, 1], [-1, 1]].map(([x, y]) => {

			const wanted = { x: from.x + x * size, y: from.y + y * size };
			const at = {
				x: clamp(wanted.x, area.left + size / 2, area.right - size / 2),
				y: clamp(wanted.y, area.top + size / 2, area.bottom - size / 2),
				z: from.z
			};
			const overlaps = handles.filter(handle => Math.abs(handle.x - at.x) < size && Math.abs(handle.y - at.y) < size).length;
			const moved = (at.x !== wanted.x || at.y !== wanted.y);

			return { at, cost: overlaps * 2 + (moved ? 1 : 0) };

		});

		return places.reduce((best, place) => (place.cost < best.cost) ? place : best).at;

	}

	handle() {

		const element = document.createElement('div');
		element.className = 'map-scene-decor-handle';
		element.innerHTML = '<span></span>';
		element.addEventListener('pointerdown', event => this.bend(event, element));

		const handle = new this.addons.CSS2DObject(element);
		this.parent.add(handle);

		return handle;

	}

	/**
	 * Un point du tracé choisi suit le pointeur depuis sa poignée, au mètre ; la poignée d'un milieu y insère d'abord un point. Un pas
	 * refusé laisse le tracé à son dernier état permis. Sans glissé, le point touché se choisit, ou se lâche s'il l'était, et le point
	 * inséré reste choisi. Les poignées se redistribuent à chaque pas : le geste suit la fenêtre, pas la poignée qu'on a prise.
	 */
	bend(event, handle) {

		const e = this.selected;

		if(this.active === false || this.tracing !== null || e?.def.traced !== true || event.button !== 0 || event.isPrimary === false) {
			return;
		}

		event.preventDefault();

		const before = this.describe(e);
		const start = { x: event.clientX, y: event.clientY };
		const inserted = (handle.dataset.handle === 'middle');

		let index = Number(handle.dataset.index);
		let allowed = MapSceneDecor.lineOf(e);
		let moved = false;

		if(inserted) {

			index++;

			const line = allowed.slice();
			line.splice(index, 0, ScenePlanGeometry.lerp(line[index - 1], line[index], .5));

			this.reshape(e, line);

			// Le point inséré part au relâcher sans que la ligne soit rejugée : le point de trop, ou le coude qui passe le plafond du
			// décor, se refuse ici, avant d'être posé. Les autres refus étaient là avant lui, que le point inséré aide à contourner
			if(e.bad === 'points' || e.bad === 'limit') {

				const bad = e.bad;

				this.reshape(e, allowed);
				this.tell(e, this.refusalText(e, bad), true);

				clearTimeout(this.notice);
				this.notice = setTimeout(() => this.hush(), 1500);

				return;

			}

			allowed = line;
			this.point = index;

		}

		const move = ev => {

			const q = this.pick(ev);

			if(q === null || (moved === false && Math.hypot(ev.clientX - start.x, ev.clientY - start.y) < 4)) {
				return;
			}

			moved = true;

			const p = MapSceneDecor.snap(q);

			if(MapSceneDecor.samePoint(p, allowed[index])) {
				return;
			}

			const line = allowed.slice();
			line[index] = p;

			this.reshape(e, line);

			if(e.bad === null) {
				allowed = line;
				this.hush();
			} else {
				const bad = e.bad;
				this.reshape(e, allowed);
				this.tell(e, this.refusalText(e, bad), true);
			}

		};

		const end = () => {
			window.removeEventListener('pointermove', move);
			window.removeEventListener('pointerup', up);
			window.removeEventListener('pointercancel', up);
			this.held = null;
		};

		const up = () => {

			end();
			this.hush();

			// Un point se choisit pour être retiré, ou, à un bout, pour reprendre le tracé : sur un tracé de deux points, seuls ses
			// bouts ont quelque chose à offrir, « Continuer »
			const line = MapSceneDecor.lineOf(e);
			const choosable = (line.length > 2 || index === 0 || index === line.length - 1);

			if(moved || inserted) {
				this.point = choosable ? index : null;
				this.update(e, before, this.describe(e), true);
			} else {
				this.point = (choosable && this.point !== index) ? index : null;
			}

			this.refresh();

		};

		this.held = { e, before, end };

		window.addEventListener('pointermove', move);
		window.addEventListener('pointerup', up);
		window.addEventListener('pointercancel', up);

	}

	// Suppr retire le point choisi, ou l'élément choisi. Un bout d'un tracé de deux points se choisit pour « Continuer » mais ne se
	// retire pas : Suppr y retire le tracé
	erase() {

		if(this.point !== null && this.selected.trace.length > 2) {
			this.unbend();
		} else {
			this.remove();
		}

	}

	// Le point choisi se retire, tant que le tracé en garde deux ; refusé, le tracé revient à sa ligne d'avant
	unbend() {

		const e = this.selected;
		const index = this.point;

		if(this.active === false || this.tracing !== null || e?.def.traced !== true || index === null || e.trace.length <= 2) {
			return;
		}

		const before = this.describe(e);
		const line = MapSceneDecor.lineOf(e);

		line.splice(index, 1);

		this.point = null;
		this.reshape(e, line);

		if(e.bad === null) {
			this.update(e, before, this.describe(e), true);
			return;
		}

		this.tell(e, this.refusalText(e), true);
		this.restore(e, before);

		clearTimeout(this.notice);
		this.notice = setTimeout(() => this.hush(), 1500);

		this.refresh();

	}

	// Un tracé prend une ligne neuve, refaite et jugée à sa nouvelle place
	reshape(e, line) {

		this.lay(e, line);
		this.build(e);
		this.refresh();

	}

	/**
	 * Une vanne se cale sur la conduite la plus proche de son calque, à moins de SEAT_REACH mètres, et se tourne dans son axe ; plus
	 * loin, elle reste où on la pose.
	 */
	seat(e) {

		if(e.def.seats !== true) {
			return;
		}

		const near = this.pipeNear(e);

		if(near !== null && near.distance <= MapSceneDecor.SEAT_REACH) {
			e.p = near.p;
			e.angle = near.angle;
		}

		this.fitValve(e);

	}

	/**
	 * Le point de la conduite de son calque le plus proche d'une vanne, avec sa distance, l'angle du segment et la conduite.
	 */
	pipeNear(e) {

		const G = ScenePlanGeometry;
		let best = null;

		for(const pipe of this.elements) {

			if(pipe.layer !== e.layer || pipe.def.pipe !== true || pipe.root === null) {
				continue;
			}

			const line = pipe.path.map(q => G.add(pipe.p, q));

			for(let i = 1; i < line.length; i++) {

				const segment = G.sub(line[i], line[i - 1]);
				const length = G.length(segment);

				if(length === 0) {
					continue;
				}

				const k = Math.max(0, Math.min(1, G.dot(G.sub(e.p, line[i - 1]), segment) / (length * length)));
				const p = G.lerp(line[i - 1], line[i], k);
				const distance = G.length(G.sub(e.p, p));

				if(best === null || distance < best.distance) {
					best = { p, distance, angle: Math.atan2(segment.n, segment.x), pipe };
				}

			}

		}

		return best;

	}

	/**
	 * Une vanne prend le diamètre de la conduite qui la porte (`irrigationScale()`) et se refait quand il change : elle s'y cale,
	 * en part, le tuyau change de diamètre ou un geste s'annule.
	 */
	fitValve(e) {

		const D = MapSceneDecor;
		const near = this.pipeNear(e);
		const pipe = (near !== null && near.distance <= D.VALVE_SNUG) ? near.pipe : null;
		const seated = (pipe === null) ? null : ((pipe.def.ramp === true) ? D.RAMP.diameter : (pipe.diameter ?? D.PIPE_DEFAULT));

		if(seated === e.seated) {
			return;
		}

		e.seated = seated;

		if(e.root !== null) {
			this.build(e);
		}

	}

	fitValves() {

		for(const e of this.elements) {
			if(e.def.seats === true) {
				this.fitValve(e);
			}
		}

	}

	/**
	 * La bulle qui nomme le calque d'un élément qu'on a touché hors du calque actif, là où on l'a touché. Un élément se touche
	 * jusqu'au bord de la scène : la bulle s'y recale à la marge de la feuille, et c'est la sienne, pour que ce décalage ne
	 * reste pas sur les bulles des refus.
	 */
	sayLayer(e, at) {

		this.hush();

		if(this.sayAway === undefined) {
			const say = document.createElement('div');
			say.className = 'map-scene-decor-say';
			this.sayAway = new this.addons.CSS2DObject(say);
			this.parent.add(this.sayAway);
		}

		const say = this.sayAway.element;

		say.textContent = this.sheet.dataset.layer.replace('{value}', this.layers.nameOf(e.layer));
		say.style.marginLeft = '';
		const where = at ?? e.p;
		this.sayAway.position.set(where.x, this.geometry.height(where.x, where.n) + 2, -where.n);
		this.sayAway.visible = true;

		// La scène place la bulle à son prochain rendu : elle se mesure après
		requestAnimationFrame(() => requestAnimationFrame(() => {

			const box = say.getBoundingClientRect();
			const frame = this.container.getBoundingClientRect();
			const margin = this.sheet.getBoundingClientRect().left - frame.left;

			say.style.marginLeft = (Math.min(0, frame.right - margin - box.right) + Math.max(0, frame.left + margin - box.left)) +'px';

		}));

		clearTimeout(this.noticeAway);
		this.noticeAway = setTimeout(() => this.sayAway.visible = false, 2500);

	}

	/**
	 * Un calque ajouté n'a pas de familles : la feuille porte son nom et le catalogue de son type. Masqué, rien ne s'y pose, et la
	 * feuille le dit à la place des vignettes. Le calque de base garde ses familles, que `showFamily()` vient de montrer.
	 */
	showLayer() {

		const active = this.layers.active;
		const type = this.layers.typeOf(active);
		const masked = (this.layers.isShown(active) === false);
		const named = (type !== null || masked);

		// Un calque ajouté n'a jamais de familles et en rend la place ; le décor masqué garde la leur, qui reviennent à son affichage
		this.families.classList.toggle('hide', type !== null);
		this.families.classList.toggle('map-scene-decor-away', type === null && masked);
		this.layerHead.classList.toggle('hide', named === false);
		this.layerHead.qs('[data-scene-decor-layer-name]').textContent = this.layers.nameOf(active);
		this.masked.classList.toggle('hide', masked === false);

		if(named === false) {
			return;
		}

		for(const tile of this.tiles) {
			tile.classList.toggle('hide', masked || MapSceneDecor.KITS[tile.dataset.kit]?.layer !== type || this.offers(tile) === false);
		}

		this.createTile.classList.add('hide');
		this.importTiles.forEach(tile => tile.classList.add('hide'));

		for(const group of this.terrainGroups) {
			group.classList.add('hide');
		}

	}

	// Le tuyau qu'on tient, en main ou choisi, dont la feuille règle le diamètre et la pose. Un tuyau repris par « Continuer » ne
	// se règle qu'une fois terminé : `update()` n'envoie qu'une route par geste, et son tracé partirait sans ses points neufs
	pipeHeld() {
		return [this.armed, (this.tracing === null) ? this.selected : null].find(e => e?.kit === 'tuyau') ?? null;
	}

	/**
	 * Le bâtiment de la structure qu'on a en main, dans sa bande sous la rangée : « Modifier » le reprend dans l'éditeur, sauf
	 * s'il a été importé, ce qui ne se modifie pas.
	 */
	showBuilding() {

		const id = this.active ? (this.armed?.building ?? null) : null;

		// La bande garde sa place tant que la rangée montre un bâtiment de la structure, comme celle du tuyau, là où la feuille
		// la réserve : sous 768 px seulement (scene.css)
		const tile = [...this.tiles].find(tile => MapSceneDecor.buildingOf(tile) !== null && tile.classList.contains('hide') === false);

		const idleChanged = (this.buildingBand.classList.contains('map-scene-decor-pipe-idle') !== (id === null));

		this.buildingBand.classList.toggle('hide', id === null && (this.active === false || tile === undefined));
		this.buildingBand.classList.toggle('map-scene-decor-pipe-idle', id === null);

		if(idleChanged) {
			this.scroll.shade();
		}

		if(id === null) {
			return;
		}

		const building = this.buildings.get(id);

		this.buildingName.textContent = building.name;
		this.buildingEdit.href = '/map/decorBuilding:update?id='+ id;
		this.buildingEdit.classList.toggle('hide', building.imported);

	}

	showPipe() {

		const e = this.active ? this.pipeHeld() : null;

		// La boite garde sa place tant que la vignette du tuyau se montre, là où la feuille la réserve, sous 768 px (scene.css) : la
		// feuille y a la même hauteur avant qu'on le prenne et pendant qu'on le pose, sans grandir sous le premier point
		const tile = [...this.tiles].find(tile => tile.dataset.kit === 'tuyau' && tile.classList.contains('hide') === false);

		// Les diamètres qui se montrent ou se cachent sans changer de taille ne réveillent pas l'observateur des rangées : leurs
		// flèches se reposent ici
		const idleChanged = (this.pipe.classList.contains('map-scene-decor-pipe-idle') !== (e === null));

		this.pipe.classList.toggle('hide', e === null && (this.active === false || tile === undefined));
		this.pipe.classList.toggle('map-scene-decor-pipe-idle', e === null);

		if(idleChanged) {
			this.scroll.shade();
		}

		if(e === null) {
			this.pipeCustom = false;
			return;
		}

		const diameter = e.diameter ?? MapSceneDecor.PIPE_DEFAULT;
		const laying = e.laying ?? 'surface';
		const custom = (this.pipeCustom || this.pipe.qs('[data-diameter="'+ diameter +'"]') === null);

		this.pipe.qsa('[data-diameter]', button => {
			const on = custom ? (button.dataset.diameter === '') : (Number(button.dataset.diameter) === diameter);
			button.classList.toggle('btn-selected', on);
		});

		this.pipe.qsa('[data-laying]', button => button.classList.toggle('btn-selected', button.dataset.laying === laying));

		// « Autre » allonge ce qui défile sans changer la taille de la rangée : l'observateur ne joue pas non plus
		const otherChanged = (this.pipeOther.classList.contains('hide') === custom);

		this.pipeOther.classList.toggle('hide', custom === false);

		if(otherChanged) {
			this.scroll.shade();
		}

		if(document.activeElement !== this.pipeInput) {
			this.pipeInput.value = diameter;
		}

	}

	/**
	 * Le diamètre ou la pose du tuyau qu'on tient change, et vaut pour le suivant. Choisi, il se refait et s'enregistre aussitôt.
	 */
	setPipe(change) {

		const e = this.pipeHeld();

		if(e === null) {
			return;
		}

		const before = this.describe(e);

		Object.assign(e, { diameter: e.diameter ?? MapSceneDecor.PIPE_DEFAULT, laying: e.laying ?? 'surface' }, change);
		Object.assign(this.pipeDraft, { diameter: e.diameter, laying: e.laying });

		// Un tuyau qu'on pose ne se monte qu'à partir de son second point
		if(e.root !== null) {
			this.build(e);
		}

		if(e === this.selected) {
			this.update(e, before, this.describe(e), true);
		}

		this.refresh();

	}

	shows(e) {
		return this.layers.isShown(e.layer) && this.ruined.has(e) === false;
	}

	mask(e, masked) {

		if(masked) {
			this.ruined.add(e);
		} else {
			this.ruined.delete(e);
		}

		e.root.visible = this.shows(e);

		if(MapSceneDecor.BRIDGED.includes(e.kit)) {
			this.queueBridges();
		}

		this.changed();

	}

	/**
	 * Le calque actif ou un affichage a changé : changer de calque lâche ce qu'on tenait, chaque élément suit l'affichage du sien, et
	 * un élément choisi qui disparait se lâche.
	 */
	layersChanged() {

		const active = this.layers.active;

		for(const e of this.elements) {
			e.root.visible = this.shows(e);
		}

		this.spreadQueued = true;
		this.queueBridges();

		if(this.active === true) {

			if(active !== this.shownLayer || this.selected?.root.visible === false) {
				this.letGo();
				this.choose(null);
				this.select(null);
			}

			this.showFamily(this.family);

		}

		this.shownLayer = active;
		this.changed();

	}

	/**
	 * Les éléments d'un calque supprimé quittent la scène. Le serveur les a supprimés avec lui, et sa suppression ne s'annule pas :
	 * « Annuler » ne les fait pas revenir, et « Terminer » n'a plus à retirer ceux que la séance avait retirés.
	 */
	removeLayer(id) {

		const removed = this.elements.filter(e => e.layer === id);

		removed.forEach(e => this.drop(e));
		this.elements = this.elements.filter(e => e.layer !== id);

		if(this.active !== undefined) {

			this.journal = this.journal.filter(operation => operation.e?.layer !== id);
			this.removals = this.removals.filter(e => e.layer !== id);

			if(removed.includes(this.selected)) {
				this.select(null);
			}

		}

	}

	/**
	 * La largeur ou le modèle de l'élément choisi passe au suivant de son kit, à chaque clic, et s'enregistre aussitôt ; refusé, il
	 * revient à celui d'avant.
	 */
	cycle(property, label) {

		const e = this.selected;
		const list = (property === 'wide') ? e?.def.widths : e?.def.models;

		if(this.active === false || list === undefined) {
			return;
		}

		const before = this.describe(e);

		e[property] = list[(list.indexOf(e[property]) + 1) % list.length];

		this.build(e);

		if(e.bad === null) {
			this.tell(e, label(e[property]));
			this.update(e, before, this.describe(e), true);
		} else {
			this.tell(e, this.refusalText(e), true);
			this.restore(e, before);
		}

		clearTimeout(this.notice);
		this.notice = setTimeout(() => this.hush(), 1500);

		this.refresh();

	}

	/**
	 * Une vignette de la feuille pose ce qu'elle désigne autour de l'emprise choisie, ou le retire quand l'emprise le porte déjà, et
	 * le geste s'enregistre aussitôt. `change` rend ce que le clic change à ce que l'emprise porte : un modèle de clôture, qui
	 * prend la place de celui d'avant, ou la haie, qui double la clôture ou fait le tour seule. Un enclos qui ne porte plus ni
	 * l'un ni l'autre se retire, le serveur n'en garde pas de vide, et son emprise reste choisie.
	 */
	fence(change) {

		const area = this.enclosing();

		if(this.active === false || area === null) {
			return;
		}

		const e = this.selected;
		const current = (e === null) ? { model: null, hedge: false } : { model: e.model, hedge: e.hedge };
		const next = { ...current, ...change(current) };

		if(e !== null && next.model === null && next.hedge === false) {

			this.remove();

			// Un enclos ne garde de son emprise que son nom : le tour à montrer se tire de celle du plan, dont il est né
			this.choose(this.areas.find(item => item.kind === area.kind && item.id === area.id));

		} else if(e !== null) {

			const before = this.describe(e);

			e.model = next.model;
			e.hedge = next.hedge;
			this.build(e);

			if(e.bad === null) {
				this.update(e, before, this.describe(e), true);
			} else {
				this.refuseFence(e);
				this.restore(e, before);
			}

		} else if(this.removals.some(item => item.area?.kind === area.kind && item.area.id === area.id)) {

			this.takeBack(this.removals.find(item => item.area?.kind === area.kind && item.area.id === area.id), next);

		} else {

			const created = MapSceneDecor.element('enclos', null);

			created.model = next.model;
			created.hedge = next.hedge;

			this.enclose(created, area);

			// Dès la pose, que le tour porte une haie ou la reçoive plus tard
			created.seed = MapSceneDecor.hedgeSeed(created.p, this.elements.length);

			this.build(created);

			if(created.bad === null) {
				this.elements.push(created);
				this.select(created);
				this.create(created, true);
			} else {
				this.refuseFence(created);
				this.drop(created);
			}

		}

		this.refresh();

	}

	/**
	 * L'enclos retiré de l'emprise pendant la séance y revient avec ce qu'on vient de choisir, sous son id : son retrait n'est
	 * pas parti, et un second enclos sur la même emprise serait refusé (`Decor::plot.alone`, `Decor::zone.alone`). Le geste se
	 * journalise comme un changement depuis l'état de la base, qu'« Annuler » rend.
	 */
	takeBack(e, next) {

		const operation = this.journal.find(item => item.type === 'delete' && item.e === e);

		e.model = next.model;
		e.hedge = next.hedge;
		this.build(e);

		if(e.bad !== null) {
			this.refuseFence(e);
			this.drop(e);
			Object.assign(e, operation.before);
			return;
		}

		this.removals.splice(this.removals.indexOf(e), 1);
		this.journal.splice(this.journal.indexOf(operation), 1);
		this.elements.push(e);
		this.select(e);

		// Rechoisir ce que l'enclos portait le rend tel qu'il est en base : rien à écrire ni à défaire
		if(e.model !== operation.before.model || e.hedge !== operation.before.hedge) {
			this.update(e, operation.before, this.describe(e), true);
		}

	}

	// Un tour refusé se dit au centre de l'emprise le temps de le lire : il n'est jamais tenu en main, aucun geste ne viendrait l'effacer
	refuseFence(e) {

		this.tellPoint(e.p, this.refusalText(e), true);

		clearTimeout(this.notice);
		this.notice = setTimeout(() => this.hush(), 1500);

	}

	// Le bout choisi du tracé choisi, 'first' ou 'last', ou null quand le point choisi est au milieu ou qu'aucun ne l'est
	chosenEnd() {

		const e = this.selected;

		if(e?.def.traced !== true || this.point === null) {
			return null;
		}

		if(this.point === 0) {
			return 'first';
		}

		return (this.point === e.trace.length - 1) ? 'last' : null;

	}

	// Le tracé choisi se reprend depuis son bout choisi, au clic comme un tracé neuf : ses points posés restent, Échap n'en retire
	// aucun
	extend() {

		const e = this.selected;
		const end = this.chosenEnd();

		if(this.active === false || end === null || this.tracing !== null) {
			return;
		}

		const line = MapSceneDecor.lineOf(e);
		const reversed = (end === 'first');
		const points = reversed ? line.reverse() : line;

		this.point = null;
		this.tracing = { e, points, kept: points.length, cursor: null, before: this.describe(e), reversed, freehand: null, strokes: [] };
		this.refresh();

	}

	/**
	 * Une parcelle ou un jardin choisi sur la scène, qu'un modèle de la feuille clôturera : son tour à venir se montre au sol.
	 */
	choose(area) {

		this.chosen = area;

		if(area !== null && area !== this.outlined) {

			const loop = this.loopOf(area);

			this.strokeAlong(this.outline, [...loop, loop[0]], 1.2);

			if(this.geometry.relief !== null) {
				for(const mesh of [this.outline, this.outline.children[0]]) {
					const flat = mesh.geometry;
					mesh.geometry = this.geometry.relief.drape(this.THREE, flat);
					flat.dispose();
				}
			}

			this.outlined = area;

		}

		this.refresh();

		if(area !== null) {
			this.reveal(area);
		}

	}

	// L'emprise que la feuille propose de clôturer : celle de la clôture choisie, ou celle qu'on a choisie sur la scène
	enclosing() {

		if(this.selected?.def.enclosure === true) {
			return this.selected.area;
		}

		return this.chosen;

	}

	select(e) {

		if(e !== null) {
			this.chosen = null;
		}

		if(e !== this.selected) {
			this.point = null;
		}

		this.selected = e;
		this.refresh();

	}

	refresh() {

		// Un tuyau qui a changé refait ses vannes avant qu'on montre leurs emprises
		this.fitValves();

		for(const e of this.elements) {
			if(e.pad !== null) {
				e.pad.visible = this.active && (e === this.selected || e.bad !== null);
			}
		}

		// Un tracé en main n'a d'emprise qu'à partir de son second point
		if(this.armed !== null && this.armed.pad !== null) {
			this.armed.pad.visible = true;
		}

		this.outline.visible = (this.active && this.selected === null && this.chosen !== null);

		// Le pli entre deux îlots ne se montre qu'avec un tracé, en cours ou choisi : lui seul bute dessus. Le contour du terrain se
		// cache pendant un tracé qui se dessine, puisqu'il le franchit, et borne tout le reste
		if(this.fold !== null) {
			this.fold.visible = (this.tracing !== null || (this.selected !== null && this.selected.def.traced === true));
			this.limit.visible = (this.tracing === null || MapSceneDecor.freehand(this.tracing.e.def) === false);
		}

		this.showPipe();
		this.showBuilding();
		this.showTracing();
		this.showEnclosure();
		this.showHandles();
		this.placeTools();
		this.changed();

	}

	/**
	 * Un tracé commencé : au téléphone, la feuille ne garde que la boite de son kit et son « Terminer », qui remplace celui de la
	 * rangée du haut (scene.css). Il commence au premier point, avant lequel les vignettes restent là pour changer de kit,
	 * « Terminer » n'ayant alors rien à terminer.
	 */
	showTracing() {
		this.container.classList.toggle('map-scene-tracing', this.active && this.tracing !== null && this.tracing.points.length > this.tracing.kept);
	}

	/**
	 * Une emprise à clôturer, ou une clôture choisie : la feuille la nomme et ne propose que les clôtures et la haie, à la place des
	 * familles et des vignettes, en marquant ce que l'emprise porte.
	 */
	showEnclosure() {

		const area = this.active ? this.enclosing() : null;
		const e = (this.selected?.def.enclosure === true) ? this.selected : null;

		// Les rangées ne changent qu'en passant d'une emprise choisie à aucune : refresh(), qui suit chaque mouvement d'un geste, ne
		// les remesure pas
		const swapped = (this.kits.classList.contains('hide') !== (area !== null));

		this.families.classList.toggle('map-scene-decor-behind', area !== null);
		this.kits.classList.toggle('hide', area !== null);
		this.place.classList.toggle('map-scene-decor-behind', area === null);
		this.enclosure.classList.toggle('hide', area === null);

		if(swapped) {
			this.scroll.shade();
		}

		if(area === null) {
			return;
		}

		this.place.qs('[data-scene-decor-area-name]').textContent = area.name;

		for(const tile of this.fences) {
			tile.classList.toggle('selected', e !== null && tile.dataset.fence === e.model);
		}

		this.hedgeTile.classList.toggle('selected', e !== null && e.hedge);

		// Une parcelle choisie sur la scène, sans clôture en main, choisit aussi son sol et son pré
		const zone = (e === null && area.kind === 'zone');

		for(const group of this.areaTerrainGroups) {
			group.classList.toggle('hide', zone === false);
		}

		if(zone) {
			this.showAreaTerrain(area);
		}

	}

	/**
	 * Les outils sous l'élément choisi, hors de son volume, ou au-dessus quand le bas de la scène manque ; sous un tracé, hors de
	 * ses points et de leurs poignées. Ils gardent du bord de la scène, de la rangée du haut et de la feuille la marge des autres
	 * surcouches, celle que leur feuille de style leur donne en `left`.
	 * Les outils se montrent d'abord, puis tout se mesure, leur taille comprise, et leur place s'écrit en dernier.
	 */
	placeTools() {

		// Un tracé repris par « Continuer » cache les outils jusqu'à ce qu'il se termine
		const e = (this.active && this.tracing === null) ? this.selected : null;

		if(e === null) {
			this.tools.classList.add('hide');
			return;
		}

		this.tools.qsa('[data-tool="rotate"]', tool => tool.classList.toggle('hide', e.def.traced === true || e.def.enclosure === true));
		this.tools.qsa('[data-tool="add"]', tool => tool.classList.toggle('hide', this.chosenEnd() === null));
		this.tools.qsa('[data-tool="size"]', tool => tool.classList.toggle('hide', e.def.size === undefined));
		this.tools.qsa('[data-tool="wide"]', tool => tool.classList.toggle('hide', e.def.widths === undefined));
		this.tools.qsa('[data-tool="model"]', tool => tool.classList.toggle('hide', e.def.models === undefined || e.def.enclosure === true));
		this.tools.qsa('[data-tool="edit"]', tool => {

			// Un élément pas encore enregistré, celui du décor par défaut avant son adoption, n'a pas de ligne à personnaliser, et un
			// bâtiment importé ne se modifie pas
			const editable = (e.id !== null && (e.kit === 'hangar' || (e.def.building === true && this.buildings.get(e.building).imported === false)));

			tool.classList.toggle('hide', editable === false);

			if(editable) {
				tool.href = (e.def.building === true) ? '/map/decorBuilding:update?id='+ e.building : '/map/decorBuilding:create?structure='+ this.sheet.dataset.structure +'&decor='+ e.id;
			}

		});
		this.tools.classList.remove('hide');

		const box = this.canvas.getBoundingClientRect();
		const frame = this.container.getBoundingClientRect();
		const sheet = this.sheet.getBoundingClientRect();
		const bar = this.container.qs('.map-scene-bar').getBoundingClientRect();
		const style = getComputedStyle(this.tools);
		const margin = parseFloat(style.left);
		const half = parseFloat(style.getPropertyValue('--scene-control')) / 2;

		const screen = { left: Infinity, right: -Infinity, top: Infinity, bottom: -Infinity };
		const point = new this.THREE.Vector3();

		let anchor;

		// Un tracé porte une poignée sur chacun de ses points : les outils se tiennent hors de toutes, à une demi-cible de plus
		let reach = 0;

		if(e.def.traced === true) {
			anchor = MapSceneDecor.lineOf(e);
			reach = half;
		} else {
			anchor = MapSceneDecor.corners(MapSceneDecor.rectangle(e));
		}

		for(const corner of anchor) {
			for(const lift of [0, e.height]) {

				point.set(corner.x, lift, -corner.n).project(this.camera);

				const x = box.left + (point.x + 1) / 2 * box.width;
				const y = box.top + (1 - point.y) / 2 * box.height;

				screen.left = Math.min(screen.left, x);
				screen.right = Math.max(screen.right, x);
				screen.top = Math.min(screen.top, y);
				screen.bottom = Math.max(screen.bottom, y);

			}
		}

		// La pastille qui retire le point choisi d'un tracé compte dans son emprise
		if(e.def.traced === true && this.unpointAt !== null) {
			screen.left = Math.min(screen.left, this.unpointAt.x);
			screen.right = Math.max(screen.right, this.unpointAt.x);
			screen.top = Math.min(screen.top, this.unpointAt.y);
			screen.bottom = Math.max(screen.bottom, this.unpointAt.y);
		}

		// Ce que les outils ne couvrent pas : les poignées du tracé choisi et la pastille de son point, puis ce qui se lit sur la scène,
		// les pancartes des jardins, le nom des parcelles et le diamètre des tuyaux
		const targets = (e.def.traced === true) ? [...this.handlesAt, ...(this.unpointAt === null ? [] : [this.unpointAt])] : [];

		const obstacles = [
			...targets.map(at => ({ left: at.x - reach, right: at.x + reach, top: at.y - reach, bottom: at.y + reach })),
			...this.boards.map(({ board }) => this.screenOf(board, box)),
			...this.labelsOf(['map-scene-label-plot', 'map-scene-decor-diameter'], box)
		].filter(rect => rect.right > rect.left && rect.right > box.left && rect.left < box.right && rect.bottom > box.top && rect.top < box.bottom);

		const { top, bottom } = this.free(box, bar, sheet, margin);

		const width = this.tools.offsetWidth;
		const height = this.tools.offsetHeight;
		const gap = 8 + reach;

		const left = box.left + margin;
		const right = box.right - margin - width;
		const along = x => Math.max(left, Math.min(right, x));
		const center = (screen.left + screen.right) / 2 - width / 2;
		const below = screen.bottom + gap;
		const above = screen.top - height - gap;

		// Sous l'élément, sinon au-dessus quand la feuille prendrait la place, centrés puis alignés sur l'un de ses bords. Un élément
		// qui remplit la scène ne laisse ni dessous ni dessus : les outils prennent alors la place libre la plus proche du dessous
		const places = [below, above]
			.filter(y => y >= top && y + height <= bottom)
			.flatMap(y => [center, screen.left, screen.right - width].map(x => ({ x: along(x), y })));

		const clear = place => obstacles.every(rect => rect.right <= place.x || rect.left >= place.x + width || rect.bottom <= place.y || rect.top >= place.y + height);

		// La place libre ne se balaie qu'à défaut, placeTools() tournant à chaque image d'une rotation de la vue
		const nearest = () => {

			const grid = [];

			for(let y = top; y <= bottom - height; y += half) {
				for(let x = left; x <= right; x += half) {
					grid.push({ x, y });
				}
			}

			return grid
				.sort((p, q) => Math.hypot(p.x - center, p.y - below) - Math.hypot(q.x - center, q.y - below))
				.find(clear);

		};

		const { x, y } = places.find(clear) ?? nearest() ?? {
			x: along(center),
			y: Math.max(top, Math.min(bottom - height, (below + height > bottom) ? above : below))
		};

		// La translation part de la place que la feuille de style donne aux outils, à `margin` du coin du cadre
		this.tools.style.transform = 'translate('+ Math.round(x - frame.left - margin) +'px, '+ Math.round(y - frame.top - margin) +'px)';

	}

	/**
	 * L'emprise à l'écran des étiquettes visibles qui portent l'une de ces classes, dans le repère de la fenêtre. Elle se calcule
	 * depuis leur place dans la scène et non depuis le DOM, que CSS2DRenderer ne replace qu'au rendu suivant : lue après un zoom,
	 * l'étiquette serait encore à sa place d'avant.
	 */
	labelsOf(classes, box) {

		const rects = [];
		const vector = new this.THREE.Vector3();

		this.parent.parent.traverseVisible(object => {

			if(object.isCSS2DObject !== true || classes.some(name => object.element.classList.contains(name)) === false) {
				return;
			}

			object.getWorldPosition(vector).project(this.camera);

			const element = object.element;
			const x = box.left + (vector.x + 1) / 2 * box.width;
			const y = box.top + (1 - vector.y) / 2 * box.height + parseFloat(getComputedStyle(element).marginTop);

			rects.push({ left: x - element.offsetWidth / 2, right: x + element.offsetWidth / 2, top: y - element.offsetHeight / 2, bottom: y + element.offsetHeight / 2 });

		});

		return rects;

	}

	// L'emprise à l'écran d'un objet de la scène, dans le repère de la fenêtre
	screenOf(object, box) {

		const bounds = new this.THREE.Box3().setFromObject(object);
		const corners = [];

		for(const x of [bounds.min.x, bounds.max.x]) {
			for(const y of [bounds.min.y, bounds.max.y]) {
				for(const z of [bounds.min.z, bounds.max.z]) {
					corners.push(new this.THREE.Vector3(x, y, z).project(this.camera));
				}
			}
		}

		const xs = corners.map(corner => box.left + (corner.x + 1) / 2 * box.width);
		const ys = corners.map(corner => box.top + (1 - corner.y) / 2 * box.height);

		return { left: Math.min(...xs), right: Math.max(...xs), top: Math.min(...ys), bottom: Math.max(...ys) };

	}

	// La hauteur que laissent à la scène la rangée du haut et la feuille quand elles sont posées dessus, marge comprise
	free(box, bar, sheet, margin) {

		return {
			top: ((bar.bottom > box.top) ? bar.bottom : box.top) + margin,
			bottom: ((sheet.top > box.top && sheet.top < box.bottom) ? sheet.top : box.bottom) - margin
		};

	}

	/**
	 * L'emprise choisie se cadre dans la place que laissent la rangée du haut et la feuille : la vue recule si elle n'y tient pas,
	 * puis se déplace de ce qui manque, sans tourner. La vue ne se rapproche jamais : celui qui a reculé pour choisir garde son
	 * recul. Tout se mesure avant que la caméra bouge.
	 */
	reveal(area) {

		const THREE = this.THREE;
		const camera = this.camera;

		const box = this.canvas.getBoundingClientRect();
		const bar = this.container.qs('.map-scene-bar').getBoundingClientRect();
		const sheet = this.sheet.getBoundingClientRect();
		const margin = parseFloat(getComputedStyle(this.tools).left);
		const { top, bottom } = this.free(box, bar, sheet, margin);
		const left = box.left + margin;
		const right = box.right - margin;

		camera.updateMatrixWorld();

		const point = new THREE.Vector3();
		const view = { x0: Infinity, x1: -Infinity, y0: Infinity, y1: -Infinity };

		// Le tour que la clôture prendra, qui déborde l'emprise
		for(const p of this.loopOf(area)) {

			point.set(p.x, 0, -p.n).applyMatrix4(camera.matrixWorldInverse);

			view.x0 = Math.min(view.x0, point.x);
			view.x1 = Math.max(view.x1, point.x);
			view.y0 = Math.min(view.y0, point.y);
			view.y1 = Math.max(view.y1, point.y);

		}

		// Pixels par unité de la vue au zoom 1, sur chaque axe : le cadre de la caméra est centré
		const kx = box.width / (camera.right - camera.left);
		const ky = box.height / (camera.top - camera.bottom);
		const fit = Math.min((right - left) / ((view.x1 - view.x0) * kx), (bottom - top) / ((view.y1 - view.y0) * ky));
		const zoom = Math.max(this.controls.minZoom, Math.min(camera.zoom, fit));

		const along = (low, high, from, to) => {

			if(high - low > to - from) {
				return (from + to) / 2 - (low + high) / 2;
			} else if(low < from) {
				return from - low;
			} else if(high > to) {
				return to - high;
			} else {
				return 0;
			}

		};

		const centerX = box.left + box.width / 2;
		const centerY = box.top + box.height / 2;
		const dx = along(centerX + view.x0 * kx * zoom, centerX + view.x1 * kx * zoom, left, right);
		const dy = along(centerY - view.y1 * ky * zoom, centerY - view.y0 * ky * zoom, top, bottom);

		if(zoom === camera.zoom && dx === 0 && dy === 0) {
			return;
		}

		// L'emprise glisse de (dx, dy) à l'écran quand la vue glisse d'autant à l'opposé
		const move = new THREE.Vector3(1, 0, 0).applyQuaternion(camera.quaternion).multiplyScalar(-dx / (kx * zoom))
			.add(new THREE.Vector3(0, 1, 0).applyQuaternion(camera.quaternion).multiplyScalar(dy / (ky * zoom)));

		camera.zoom = zoom;
		camera.position.add(move);
		this.controls.target.add(move);
		camera.updateProjectionMatrix();
		this.controls.update();

	}

	// Un mot au-dessus de l'élément : le refus du geste, ou la cote qu'il change
	tell(e, text, refused = false) {

		this.sayRefused(refused);
		this.say.element.textContent = text;
		this.say.element.classList.toggle('map-scene-decor-say-trace', e.def.traced === true);
		// Un bâtiment se pose au plus haut du sol sous lui (settle()) : sa bulle part de son pied, celle de tout autre élément, tracé
		// compris, du sol sous son centre
		const foot = (this.geometry.relief !== null && MapSceneDecor.founded(e)) ? e.root.position.y : this.geometry.height(e.p.x, e.p.n);
		this.say.position.set(e.p.x, foot + e.height + 1, -e.p.n);
		this.say.visible = (text !== '');
		this.spreadQueued = true;

	}

	hush() {
		this.say.visible = false;
		this.spreadQueued = true;
	}

	sayRefused(refused) {
		this.say.userData.refused = refused;
		this.say.element.classList.toggle('map-scene-decor-say-refused', refused);
	}

	// Le refus `bad` de l'élément. `long` porte la longueur que son kit ne dépasse pas, `points` le nombre de points d'un tracé
	refusalText(e, bad = e.bad) {

		if(bad === 'long') {
			return this.refusals.long.replace('{value}', e.def.longest ?? MapSceneDecor.TRACE_LENGTH);
		} else if(bad === 'points') {
			return this.refusals.points.replace('{value}', this.tracePoints);
		} else {
			return this.refusals[bad];
		}

	}

	down(event) {

		this.downAt = null;
		this.downOnPerson = false;
		this.downOnTrace = null;

		// Un second doigt rend la main à la vue, et le glissé en cours s'annule
		if(this.tracing !== null && this.tracing.freehand !== null) {
			this.cancelFreehand();
		}

		if(this.active === false || event.button !== 0 || event.isPrimary === false) {
			return;
		}

		this.downAt = { x: event.clientX, y: event.clientY };

		// Ce qu'on a en main se pose au relâché, et un glissé fait bouger la vue, sauf celui qui dessine le tracé qu'on pose
		if(this.tracing !== null) {
			this.startFreehand(event);
			return;
		}

		if(this.armed !== null) {
			return;
		}

		const { person, e } = this.target(event);

		this.downOnPerson = person;

		if(e === null) {
			return;
		}

		// Un élément d'un autre calque ne se choisit pas : la bulle dit où il est, et le relâché ne choisit rien
		if(e.layer !== this.layers.active) {
			this.sayLayer(e, this.pick(event));
			this.downAt = null;
			return;
		}

		// Un tracé ne se déplace pas d'un bloc, il se modifie par ses poignées : il se choisit au relâché, et un glissé fait bouger la vue
		if(e.def.traced === true) {
			this.downOnTrace = e;
			return;
		}

		const p = this.pick(event);

		event.stopImmediatePropagation();
		event.preventDefault();

		try {
			this.canvas.setPointerCapture(event.pointerId);
		} catch(error) {
			// Un pointeur déjà relâché ne se capture plus : le geste se suit alors sur le canevas seul
		}

		this.select(e);

		if(e.def.enclosure === true) {
			this.slideGate(e);
			return;
		}

		const before = this.describe(e);
		const offset = ScenePlanGeometry.sub(p, e.p);
		const start = this.downAt;
		let moved = false;

		this.allowed = this.keep(e);

		const move = ev => {

			const q = this.pick(ev);

			if(q === null || (moved === false && Math.hypot(ev.clientX - start.x, ev.clientY - start.y) < 4)) {
				return;
			}

			moved = true;

			const wanted = MapSceneDecor.snap(ScenePlanGeometry.sub(q, offset));
			e.p = (this.allowed !== null) ? this.approach(e, this.allowed.p, wanted) : wanted;
			this.seat(e);
			this.settle(e);

			if(e.bad === null) {
				this.allowed = this.keep(e);
				this.hush();
			} else {
				this.tell(e, this.refusalText(e), true);
			}

			this.showFine(e);
			this.refresh();

		};

		const end = () => {
			this.canvas.removeEventListener('pointermove', move);
			this.canvas.removeEventListener('pointerup', up);
			this.canvas.removeEventListener('pointercancel', up);
			this.held = null;
		};

		const up = () => {
			end();
			this.finish(e, before, moved);
		};

		this.held = { e, before, end };

		this.canvas.addEventListener('pointermove', move);
		this.canvas.addEventListener('pointerup', up);
		this.canvas.addEventListener('pointercancel', up);

	}

	/**
	 * Un enclos ne se déplace pas, il suit son emprise : le glissé parti de lui mène son portail le long du tour, au plus près du
	 * pointeur.
	 */
	slideGate(e) {

		const G = ScenePlanGeometry;
		const before = this.describe(e);
		const ring = e.ring.map(q => G.add(e.p, q));
		const total = MapSceneDecor.loopLength(ring);
		const start = this.downAt;
		let moved = false;

		const move = ev => {

			const q = this.pick(ev);

			if(q === null || (moved === false && Math.hypot(ev.clientX - start.x, ev.clientY - start.y) < 4)) {
				return;
			}

			moved = true;

			const s = MapSceneDecor.gateBound(ring, MapSceneDecor.loopProject(ring, q).s);

			if(Math.abs(s - e.gate * total) < MapSceneDecor.GATE_STEP) {
				return;
			}

			e.gate = Math.round(s / total * 1e4) / 1e4;
			this.build(e);

			if(e.bad === null) {
				this.hush();
			} else {
				this.tellPoint(e.p, this.refusalText(e), true);
			}

			this.refresh();

		};

		const end = () => {
			this.canvas.removeEventListener('pointermove', move);
			this.canvas.removeEventListener('pointerup', up);
			this.canvas.removeEventListener('pointercancel', up);
			this.held = null;
		};

		const up = () => {
			end();
			this.finish(e, before, moved);
		};

		this.held = { e, before, end };

		this.canvas.addEventListener('pointermove', move);
		this.canvas.addEventListener('pointerup', up);
		this.canvas.addEventListener('pointercancel', up);

	}

	hover(event) {

		if(this.active === false || event.buttons !== 0) {
			return;
		}

		if(this.tracing !== null) {
			this.carry(event);
			this.canvas.style.cursor = 'crosshair';
		} else if(this.armed !== null) {
			this.carry(event);
		} else {
			const e = this.target(event).e;
			this.canvas.style.cursor = (e?.layer !== this.layers.active) ? '' : ((e.def.traced === true) ? 'pointer' : 'grab');
		}

	}

	up(event) {

		const start = this.downAt;

		this.downAt = null;

		// Un glissé qui a dessiné se termine au relâché, à toute distance de l'appui
		if(this.tracing !== null && this.tracing.freehand !== null && this.finishFreehand(event)) {
			return;
		}

		if(this.active === false || this.held !== null || start === null || Math.hypot(event.clientX - start.x, event.clientY - start.y) > 4) {
			return;
		}

		if(this.tracing !== null) {
			this.plot(event);
			return;
		}

		// Au doigt, il n'y a pas de survol : le toucher dit où poser
		if(this.armed !== null) {
			this.carry(event);
			this.land();
			return;
		}

		if(this.downOnPerson) {
			return;
		}

		if(this.downOnTrace !== null) {
			this.select(this.downOnTrace);
			return;
		}

		// Un calque ajouté ne clôt rien : un clic à côté lâche seulement l'élément choisi
		if(this.layers.active !== MapSceneLayer.BASE) {
			this.choose(null);
			this.select(null);
			return;
		}

		// Un clic choisit l'emprise sous lui, ou la clôture qu'elle a déjà ; un second clic sur la même la lâche
		const p = this.pick(event);
		const area = (p === null) ? null : this.areaAt(p);
		const current = this.enclosing();
		const same = (a, b) => (a.kind === b.kind && a.id === b.id);

		this.choose(null);
		this.select(null);

		if(area === null || (current !== null && same(current, area))) {
			return;
		}

		const fence = this.elements.find(e => e.def.enclosure === true && same(e.area, area));

		if(fence === undefined) {

			// Une emprise ne se choisit que si la scène sait monter sa clôture
			if(this.available(MapSceneDecor.KITS.enclos)) {
				this.choose(area);
			}

		} else {
			this.select(fence);
		}

	}

	/**
	 * Un geste parti d'un outil : il lit le point du sol sous le pointeur jusqu'au relâché. `change` modifie l'élément et rend la
	 * cote à montrer, lue après qu'un pas refusé l'a ramené à son dernier état permis.
	 */
	gesture(event, change) {

		const e = this.selected;

		if(event.button !== 0 || e === null) {
			return;
		}

		event.preventDefault();

		const before = this.describe(e);
		const from = this.pick(event);
		let moved = false;

		this.allowed = this.keep(e);

		const move = ev => {

			const p = this.pick(ev);

			if(p === null || from === null) {
				return;
			}

			moved = true;

			const label = change(e, p, from, before);

			if(e.bad === null) {
				this.allowed = this.keep(e);
			} else if(this.allowed !== null) {
				this.restore(e, this.allowed);
			}

			this.tell(e, label());
			this.refresh();

		};

		const end = () => {
			window.removeEventListener('pointermove', move);
			window.removeEventListener('pointerup', up);
			window.removeEventListener('pointercancel', up);
			this.held = null;
		};

		const up = () => {
			end();
			this.finish(e, before, moved);
		};

		this.held = { e, before, end };

		window.addEventListener('pointermove', move);
		window.addEventListener('pointerup', up);
		window.addEventListener('pointercancel', up);

	}

	// Fin d'un geste : l'élément reste à son dernier état permis, ou revient à celui d'avant le geste quand il n'en a eu aucun
	finish(e, before, moved) {

		this.hush();
		this.fine.visible = false;
		this.allowed = null;

		if(moved && e.bad !== null) {
			this.restore(e, before);
		}

		this.refresh();

		const after = this.describe(e);

		if(moved && (after.p.x !== before.p.x || after.p.n !== before.p.n || after.angle !== before.angle || after.size !== before.size || after.gate !== before.gate)) {
			this.update(e, before, after, true);
		}

	}

	// Lâche ce qu'on tient : le geste en cours revient à son départ, l'élément en main retourne au catalogue
	letGo() {

		const held = this.held;

		held?.end();

		if(this.tracing !== null) {
			this.dropTrace();
			return true;
		}

		if(held !== null) {

			this.allowed = null;
			this.restore(held.e, held.before);
			this.hush();
			this.fine.visible = false;
			this.refresh();

		}

		if(this.armed !== null) {
			this.disarm();
			return true;
		}

		return (held !== null);

	}

	remove() {

		const e = this.selected;

		if(this.active === false || e === null) {
			return;
		}

		this.drop(e);
		this.elements.splice(this.elements.indexOf(e), 1);
		this.select(null);
		this.delete(e, true);

	}

	/**
	 * « Annuler » lâche d'abord ce qu'on tient. Sans rien en main, il défait la séance, du dernier geste au premier, chacun par la
	 * route qui le défait : un élément posé se retire, un élément déplacé revient. Un élément retiré revient sous son id sans rien
	 * écrire, son retrait n'étant parti qu'à « Terminer ».
	 *
	 * Le décor par défaut enregistré au premier geste de la séance se défait en dernier, ligne par ligne, et redevient le défaut :
	 * ses éléments déplacés ou retirés reviennent dans la scène sans rien écrire, puisque leurs lignes s'en vont avec lui.
	 */
	cancel() {

		if(this.letGo()) {
			return;
		}

		this.select(null);

		const adopted = this.journal.find(operation => operation.type === 'adopt')?.elements ?? [];

		for(const operation of this.journal.reverse()) {

			const e = operation.e;

			if(operation.type === 'adopt') {

				this.unadopt(operation.elements);

			} else if(operation.type === 'create') {

				this.drop(e);
				this.elements.splice(this.elements.indexOf(e), 1);
				this.delete(e, false);

			} else if(operation.type === 'update') {

				this.restore(e, operation.before);

				if(adopted.includes(e) === false) {
					this.update(e, operation.after, operation.before, false);
				}

			} else {

				this.build(e);
				this.elements.push(e);

			}

		}

		this.journal = [];
		this.removals = [];
		this.chooseTerrain(this.terrainOpening);

		for(const [id, terrain] of this.zoneTerrainOpening) {
			this.chooseAreaTerrain({ id }, terrain);
		}

		this.setMode(false);

	}

	/**
	 * Les écritures partent l'une après l'autre, dans l'ordre des gestes : un déplacement attend l'id de l'élément que sa pose
	 * attend encore. `body` se lit au départ de la requête ; il rend null quand l'élément n'a jamais eu d'id, et rien ne part.
	 * Un refus du serveur se résout sans id, comme une requête qui n'aboutit pas : la promesse rend alors null. `read` tire de la
	 * réponse ce que la route rend, l'id d'un élément par défaut.
	 */
	send(route, body, read = json => (typeof json?.id === 'number') ? json.id : null) {

		const sent = this.queue.then(() => {

			// Une page figée n'écrit plus : les gestes encore dans la file partent pour rien
			if(this.frozen !== null) {
				return null;
			}

			const values = body();

			if(values === null) {
				return null;
			}

			return new Ajax.Query(this.sheet)
				.url('/map/decor:'+ route)
				.body(values)
				.fetch()
				.then(read, () => null);

		});

		// Un maillon rejeté ne doit pas couper la file : les écritures suivantes de la séance partiraient pour rien
		this.queue = sent.catch(() => null);

		return sent;

	}

	// Un tracé enregistre chacun de ses points en coordonnées, chacun ôté du décalage de son îlot, et son centre pour la règle `near`
	position(e, state) {

		return {
			coordinates: JSON.stringify(this.coordinates(state.p)),
			angle: ((state.angle % (Math.PI * 2)) + Math.PI * 2) % (Math.PI * 2),
			size: state.size,
			width: state.wide ?? '',
			trace: (e.def.traced === true) ? JSON.stringify(state.trace.map(q => this.coordinates(ScenePlanGeometry.add(state.p, q)))) : ''
		};

	}

	// Ce que `doUpdatePosition` construit (`DecorLib::getPropertiesPosition()`) : un tracé ses points et sa largeur, un objet sa place
	placed(e, state) {

		const { coordinates, angle, size, width, trace } = this.position(e, state);

		return (e.def.traced === true) ? { trace, width } : { coordinates, angle, size };

	}

	/**
	 * Le décor a bougé là où l'on marche : les paysans retracent leur réseau. Les gestes d'un même instant, ceux qu'une annulation
	 * défait d'un coup par exemple, ne le font retracer qu'une fois.
	 */
	queueWalk() {

		if(this.walkQueued) {
			return;
		}

		this.walkQueued = true;

		queueMicrotask(() => {

			this.walkQueued = false;

			if(this.closed === false) {
				this.walked();
			}

		});

	}

	/**
	 * Un chemin, une route ou un cours d'eau a bougé, ou s'est montré, ou caché : les ponts se reposent, une fois pour tous les gestes
	 * d'un même instant.
	 */
	queueBridges() {

		if(this.bridgesQueued) {
			return;
		}

		this.bridgesQueued = true;

		// Une promesse et non `queueMicrotask()`, que le décor monté sans navigateur, dans un contexte `vm` de node, n'a pas : le
		// constructeur y pose ses tracés, et la file part de là
		Promise.resolve().then(() => {

			this.bridgesQueued = false;

			if(this.closed === false) {
				this.stripe(this.bridge());
			}

		});

	}

	/**
	 * Les ponts, déduits des tracés montrés et refaits en entier, d'une seule géométrie pour tous. Chacun est le modèle `pont` étiré
	 * sur son croisement et tourné sur l'axe du chemin, avec sous l'arche une nappe de l'eau du cours, posée juste au-dessus du tracé :
	 * sans elle, la bande du chemin se verrait sous l'arche, par-dessus l'eau. Ils ne sont pas des éléments : le pointeur ne les vise
	 * pas, la pose ne les enregistre pas. Rend les croisements posés, chacun avec le profil `deck` de son tablier et son repère
	 * `place`, que le marquage suit.
	 */
	bridge() {

		const THREE = this.THREE;
		const D = MapSceneDecor;
		const had = (this.bridges !== null);

		if(had) {
			this.parent.remove(this.bridges);
			this.bridges.geometry.dispose();
			this.bridges = null;
		}

		const shown = this.elements.filter(e => e.path !== undefined && this.shows(e));
		const streams = shown.filter(e => e.kit === 'cours-eau');
		const walked = shown.filter(e => D.WALKED.includes(e.kit));

		if(streams.length === 0 || walked.length === 0) {

			if(had) {
				this.changed();
			}

			return [];

		}

		const model = this.baked.index.models.pont;
		const foot = model.anchors.arche[0];
		const ramps = (model.box[1][0] - model.box[0][0]) - 2 * foot;
		const crossings = D.crossings(walked, streams, ramps);

		if(crossings.length === 0) {

			if(had) {
				this.changed();
			}

			return [];

		}

		const water = new THREE.Color(D.STREAM.at(-1)[2]);
		const deck = new THREE.Color(D.BRIDGE_DECK);
		const value = new THREE.Color();

		const pieces = crossings.flatMap(crossing => {

			const chord = (crossing.water + D.BRIDGE_BANK) / crossing.sine;
			const width = crossing.track + D.BRIDGE_SIDE;
			const piece = this.base('pont', 0, false).clone();

			D.shapeBridge(piece.attributes.position.array, foot, chord, (width - D.BRIDGE_ACROSS) / 2, crossing.ramp);
			piece.computeVertexNormals();

			// Le tablier, reconnu à sa couleur comme le feuillage d'un arbre, prend celle de la chaussée, et son dessus donne au
			// marquage la hauteur du pont tout du long
			const color = piece.attributes.color;
			const position = piece.attributes.position;
			const paint = new THREE.Color(crossing.color);
			const heights = new Map();

			for(let i = 0; i < color.count; i++) {

				value.fromBufferAttribute(color, i);

				if(Math.abs(deck.r - value.r) + Math.abs(deck.g - value.g) + Math.abs(deck.b - value.b) < 1e-4) {
					color.setXYZ(i, paint.r, paint.g, paint.b);
					heights.set(position.getX(i), Math.max(heights.get(position.getX(i)) ?? -Infinity, position.getY(i)));
				}

			}

			crossing.deck = [...heights].sort(([first], [second]) => first - second);

			// La nappe suit l'eau sous le tablier : à chaque bord du pont, à z de l'axe, le milieu du cours s'est décalé en x de z / tan de l'angle
			const half = crossing.water / 2 / crossing.sine;
			const slant = (crossing.axis.x * crossing.flow.x + crossing.axis.n * crossing.flow.n) / (crossing.flow.x * crossing.axis.n - crossing.flow.n * crossing.axis.x);
			const lift = crossing.lift + .005;
			const corner = (z, side) => [z * slant + side * half, lift, z];
			const [a, b, c, d] = [corner(-width / 2, -1), corner(width / 2, -1), corner(width / 2, 1), corner(-width / 2, 1)];
			const sheet = new THREE.BufferGeometry();

			sheet.setAttribute('position', new THREE.Float32BufferAttribute([...a, ...b, ...c, ...a, ...c, ...d], 3));
			sheet.setAttribute('normal', new THREE.Float32BufferAttribute(Array.from({ length: 6 }, () => [0, 1, 0]).flat(), 3));
			sheet.setAttribute('color', new THREE.Float32BufferAttribute(Array.from({ length: 6 }, () => [water.r, water.g, water.b]).flat(), 3));
			this.mark(sheet, 0);

			// Le repère du pont : x le long du chemin, que la scène porte en (x, -n). Sur le relief, il se pose à la plus haute des deux
			// berges, pour que l'arche ne s'enfonce pas d'un côté
			const banks = [-1, 1].map(side => ScenePlanGeometry.add(crossing.point, ScenePlanGeometry.mul(crossing.axis, side * chord / 2)));
			const bank = Math.max(...banks.map(q => this.geometry.height(q.x, q.n)));
			const place = new THREE.Matrix4().makeRotationY(Math.atan2(crossing.axis.n, crossing.axis.x)).setPosition(crossing.point.x, bank, -crossing.point.n);

			crossing.place = place;

			return [piece, sheet].map(geometry => geometry.applyMatrix4(place));

		});

		this.bridges = new THREE.Mesh(this.addons.mergeGeometries(pieces), this.material);
		this.bridges.castShadow = true;
		this.bridges.receiveShadow = true;
		this.bridges.customDepthMaterial = this.depthMaterial;
		pieces.forEach(piece => piece.dispose());

		this.parent.add(this.bridges);
		this.changed();

		return crossings;

	}

	/**
	 * Le marquage du milieu des routes montrées, refait en entier avec les ponts `crossings` que `bridge()` vient de poser, d'une
	 * seule géométrie pour toutes : une bande de deux triangles par tiret et par coude du tracé lissé, à ROAD_MARK du sol. Il
	 * s'interrompt aux carrefours et, sur un pont, quitte le sol pour suivre le dessus du tablier, au même pas. Comme les ponts, il
	 * n'est pas un élément.
	 */
	stripe(crossings) {

		const THREE = this.THREE;
		const D = MapSceneDecor;
		const G = ScenePlanGeometry;
		const had = (this.stripes !== null);

		if(had) {
			this.parent.remove(this.stripes);
			this.stripes.geometry.dispose();
			this.stripes = null;
		}

		const walked = this.elements.filter(e => e.path !== undefined && this.shows(e) && D.WALKED.includes(e.kit));
		const roads = walked.filter(e => e.def.marked === true);

		if(roads.length === 0) {

			if(had) {
				this.changed();
			}

			return;

		}

		const half = D.ROAD_LINE / 2;
		const positions = [];
		const point = new THREE.Vector3();

		// Une bande de `points`, chacun en [x, y, z] de la scène et avec sa direction (X, Z), qui s'écarte de half de chaque côté
		const ribbon = points => {
			for(let i = 1; i < points.length; i++) {
				const a = points[i - 1];
				const b = points[i];
				positions.push(
					a.x - a.Z * half, a.y, a.z + a.X * half, b.x - b.Z * half, b.y, b.z + b.X * half, a.x + a.Z * half, a.y, a.z - a.X * half,
					a.x + a.Z * half, a.y, a.z - a.X * half, b.x - b.Z * half, b.y, b.z + b.X * half, b.x + b.Z * half, b.y, b.z - b.X * half
				);
			}
		};

		const junctions = D.junctions(roads, walked);

		roads.forEach((e, index) => {

			const line = e.path.map(q => G.add(e.p, q));
			const lengths = [0];

			for(let i = 1; i < line.length; i++) {
				lengths.push(lengths[i - 1] + G.length(G.sub(line[i], line[i - 1])));
			}

			const total = lengths.at(-1);
			const spans = crossings.filter(crossing => crossing.element === e);

			// Le point de la ligne à l'abscisse `s`, avec la direction du segment qui le porte, en coordonnées de la scène
			const at = (s, i) => {
				const a = line[i - 1];
				const b = line[i];
				const length = Math.max(1e-9, lengths[i] - lengths[i - 1]);
				const k = (s - lengths[i - 1]) / length;
				const x = a.x + (b.x - a.x) * k;
				const n = a.n + (b.n - a.n) * k;
				return { x, y: D.ROAD_MARK + this.geometry.height(x, n), z: -n, X: (b.x - a.x) / length, Z: (a.n - b.n) / length };
			};

			// Les tirets se posent dans l'ordre de la route : le segment du début de l'un se cherche à partir de celui du précédent
			let segment = 1;

			const ground = (from, to) => {

				while(segment < line.length - 1 && lengths[segment] <= from) {
					segment++;
				}

				let i = segment;
				const points = [at(from, i)];

				while(i < line.length - 1 && lengths[i] < to) {
					points.push(at(lengths[i], i));
					i++;
				}

				points.push(at(to, i));
				ribbon(points);

			};

			// Sur le pont, l'abscisse du tablier est celle de la route moins celle du croisement, et chaque sommet du dessus du
			// tablier donne un coude au tiret
			const deck = (crossing, from, to) => {

				const height = x => {
					const k = crossing.deck.findIndex(([vertex]) => vertex >= x);
					if(k === -1) {
						return crossing.deck.at(-1)[1];
					}
					if(k === 0) {
						return crossing.deck[0][1];
					}
					const [[x0, y0], [x1, y1]] = [crossing.deck[k - 1], crossing.deck[k]];
					return y0 + (y1 - y0) * (x - x0) / Math.max(1e-9, x1 - x0);
				};

				const xs = [from - crossing.at, ...crossing.deck.map(([x]) => x).filter(x => x > from - crossing.at && x < to - crossing.at), to - crossing.at];

				ribbon(xs.map(x => {
					point.set(x, height(x) + D.ROAD_MARK - D.ROAD.at(-1)[1], 0).applyMatrix4(crossing.place);
					return { x: point.x, y: point.y, z: point.z, X: crossing.axis.x, Z: -crossing.axis.n };
				}));

			};

			for(let start = (D.ROAD_PITCH - D.ROAD_DASH) / 2; start < total; start += D.ROAD_PITCH) {

				let pieces = [[start, Math.min(total, start + D.ROAD_DASH)]];

				for(const [from, to] of junctions[index]) {
					pieces = pieces.flatMap(([a, b]) => [[a, Math.min(b, from)], [Math.max(a, to), b]]).filter(([a, b]) => b - a > 1e-3);
				}

				for(const [a, b] of pieces) {

					let cursor = a;

					for(const crossing of spans) {

						const [from, to] = [crossing.at - crossing.length / 2, crossing.at + crossing.length / 2];

						if(to <= cursor || from >= b) {
							continue;
						}

						if(cursor < from) {
							ground(cursor, from);
						}

						deck(crossing, Math.max(cursor, from), Math.min(b, to));
						cursor = Math.min(b, to);

					}

					if(b - cursor > 1e-3) {
						ground(cursor, b);
					}

				}

			}

		});

		if(positions.length === 0) {

			if(had) {
				this.changed();
			}

			return;

		}

		const paint = new THREE.Color(D.ROAD_PAINT);
		const geometry = new THREE.BufferGeometry();

		// Remplis en place, sans tableau de tableaux par sommet : le marquage se refait à chaque geste sur un tracé, et en porte près
		// de 15 000 sur le pire cas de la scène
		const normals = new Float32Array(positions.length);
		const colors = new Float32Array(positions.length);

		for(let i = 0; i < positions.length; i += 3) {
			normals[i + 1] = 1;
			colors[i] = paint.r;
			colors[i + 1] = paint.g;
			colors[i + 2] = paint.b;
		}

		geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
		geometry.setAttribute('normal', new THREE.BufferAttribute(normals, 3));
		geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
		this.mark(geometry, 0);

		this.stripes = new THREE.Mesh(geometry, this.material);
		this.stripes.receiveShadow = true;

		this.parent.add(this.stripes);
		this.changed();

	}

	create(e, journaled) {

		const operation = { type: 'create', e };
		const state = this.describe(e);

		if(journaled) {
			this.adopt();
			this.journal.push(operation);
		}

		this.queueWalk();

		const enclosure = (e.area === null) ? {} : { [e.area.kind]: e.area.id, hedge: state.hedge ? 1 : 0, gate: state.gate };

		// Les écritures partent dans l'ordre des gestes : chacune trouve en base ce que la précédente y a laissé
		e.weighed = this.cost(e);

		// Une pose qui suit une adoption échouée ne part pas : elle ferait du plan celui de la structure, sans le défaut
		this.send('doCreate', () => (this.fallback.length > 0) ? null : ({ structure: this.sheet.dataset.structure, layer: this.layers.value(e.layer), building: e.building ?? '', object: e.object ?? '', kit: e.kit, model: state.model ?? '', diameter: state.diameter ?? '', laying: state.laying ?? '', seed: e.seed ?? '', ...enclosure, ...this.position(e, state) })).then(id => {

			// La scène a pu se fermer pendant la requête
			if(this.closed) {
				return;
			}

			if(id === null) {

				// Refusé : l'élément quitte la scène et la séance
				if(this.elements.includes(e)) {
					this.drop(e);
					this.elements.splice(this.elements.indexOf(e), 1);
					this.select(this.selected === e ? null : this.selected);
					this.queueWalk();
				}

				// Retiré avant le refus, l'élément n'a plus de ligne à retirer ni de place où revenir à « Annuler »
				this.journal = this.journal.filter(item => item !== operation && item.e !== e);
				this.removals = this.removals.filter(item => item !== e);
				return;

			}

			e.id = id;

			// « Modifier » attend l'id du hangar ou du bâtiment posé, qui arrive seulement maintenant
			if(this.selected === e) {
				this.placeTools();
			}

			// Une composition d'arbres sans graine suit l'id de son élément, qu'elle reçoit seulement maintenant
			if(e.seed === null && e.def.plants !== undefined && e.root !== null) {
				this.build(e);
				this.refresh();
			}

		});

	}

	update(e, before, after, journaled) {

		const operation = { type: 'update', e, before, after };

		if(journaled) {
			this.adopt();
			this.journal.push(operation);
		}

		this.queueWalk();

		// Un geste change la place ou la largeur, ou bien le modèle, la haie ou le portail, qui ont leur route : jamais les deux à la fois
		const route = (after.model !== before.model || after.hedge !== before.hedge || after.gate !== before.gate) ? 'doUpdateEnclosure' : ((after.diameter !== before.diameter || after.laying !== before.laying) ? 'doUpdatePipe' : 'doUpdatePosition');
		// `doUpdateEnclosure` construit ses trois propriétés : une haie ou un portail non postés retomberaient à leur défaut
		const values = () => (route === 'doUpdateEnclosure') ? { model: after.model, hedge: after.hedge ? 1 : 0, gate: after.gate ?? '' } : ((route === 'doUpdatePipe') ? { diameter: after.diameter ?? '', laying: after.laying ?? '' } : this.placed(e, after));

		e.weighed = this.cost(e);

		this.send(route, () => (e.id === null) ? null : { id: e.id, ...values() }).then(id => {

			// La scène a pu se fermer pendant la requête
			if(this.closed) {
				return;
			}

			if(id === null && e.root !== null && journaled) {
				this.restore(e, before);
				e.weighed = this.cost(e);
				this.journal = this.journal.filter(item => item !== operation);
				this.refresh();
				this.queueWalk();
			}

		});

	}

	/**
	 * Un élément retiré pendant la séance quitte la scène, mais sa ligne ne part qu'à « Terminer » (`sendRemovals()`) : « Annuler »
	 * le remet en scène sous son id, sans rien écrire. L'état noté au retrait est celui de la base, d'où repart un enclos que
	 * `fence()` reprend. Le retrait d'un élément posé, quand « Annuler » défait la séance, part aussitôt.
	 */
	delete(e, journaled) {

		this.queueWalk();

		if(journaled) {
			this.adopt();
			this.journal.push({ type: 'delete', e, before: this.describe(e) });
			this.removals.push(e);
			return;
		}

		this.send('doDelete', () => (e.id === null) ? null : { id: e.id });

	}

	/**
	 * « Terminer » envoie les retraits de la séance, dans l'ordre des gestes, derrière les écritures encore en file. Un retrait
	 * refusé remet l'élément à sa place. Un élément du défaut dont l'adoption a échoué n'a pas encore de ligne, mais il reste à sa
	 * place dans le défaut.
	 */
	sendRemovals() {

		const removals = this.removals;

		this.removals = [];

		for(const e of removals) {

			this.send('doDelete', () => (e.id === null) ? null : { id: e.id }).then(id => {

				// La scène a pu se fermer pendant la requête
				if(this.closed) {
					return;
				}

				if(id === null && (e.id !== null || this.fallback.includes(e))) {
					this.build(e);
					this.elements.push(e);
					this.refresh();
					this.queueWalk();
				}

			});

		}

	}

	/**
	 * Le décor par défaut (`MapSceneDecorDefault`), tant que la structure n'a pas son propre décor, même vidé depuis. Il se tire une
	 * fois, après les pancartes et l'enseigne qu'il évite et face à l'œil d'ouverture `eye`, et reste en scène pendant la pose.
	 * `boards` est la liste des pancartes (`MapSceneBoards.boards`), `sign` l'emprise de l'enseigne, vides quand la scène n'en pose
	 * pas.
	 */
	byDefault(plan, { boards, sign, eye }) {

		// Les pancartes des jardins, que les outils de l'élément choisi ne couvrent pas, et l'emprise de l'enseigne, que le mot ne
		// couvre pas
		this.boards = boards;
		this.sign = sign;

		if(plan.decorAdopted) {
			return;
		}

		const drawn = new MapSceneDecorDefault({ decor: this, plan, geometry: this.geometry, boards, sign, eye }).draw();

		this.fallback = drawn.filter(e => this.reachable(plan.decorReach, e));
		drawn.filter(e => this.fallback.includes(e) === false).forEach(e => this.drop(e));

		this.elements.push(...this.fallback);
		this.walked();

	}

	/**
	 * L'adoption refusée : le décor enregistré ailleurs n'est pas celui de la scène, qui ne le connait pas. La séance se défait
	 * comme par Annuler, le défaut reste tel qu'il était, et plus rien ne s'écrit depuis cette page : la pose ne se rouvre pas, et
	 * redit de recharger la page.
	 */
	freeze() {

		this.frozen = this.sheet.dataset.adopted;

		this.letGo();
		this.cancel();

	}

	/**
	 * Ce que la règle `near` du serveur accepte (`DecorLib::isNear()`, même projection) : un îlot éloigné des autres est rapproché
	 * dans la scène, et un élément tiré à sa lisière peut tomber hors de portée une fois rendu à ses vraies coordonnées.
	 */
	reachable(reach, e) {

		if(reach.center === null) {
			return false;
		}

		const [lng0, lat0] = reach.center;
		const [lng, lat] = this.coordinates(e.p);

		return Math.hypot((lng - lng0) * 111320 * Math.cos(lat0 * Math.PI / 180), (lat - lat0) * 110540) <= reach.distance;

	}

	/**
	 * Au premier geste sur un décor par défaut, il devient celui de la structure : tous ses éléments s'enregistrent d'un bloc, tels
	 * qu'ils sont posés, avant le geste qui attend leurs ids dans la file. Le serveur refuse si une autre fenêtre a déjà donné son
	 * décor à la structure : la séance se défait sans rien écrire (`freeze()`).
	 */
	adopt() {

		if(this.fallback.length === 0) {
			return;
		}

		const elements = this.fallback;
		const operation = { type: 'adopt', elements };

		this.fallback = [];
		this.journal.push(operation);

		// Les places se lisent au départ de la requête, comme celles d'une pose : le geste qui suit a déjà pu en déplacer une
		const values = () => {

			const positions = elements.map(e => this.position(e, this.describe(e)));

			return {
				structure: this.sheet.dataset.structure,
				kit: elements.map(e => e.kit),
				coordinates: positions.map(position => position.coordinates),
				angle: positions.map(position => position.angle),
				size: positions.map(position => position.size),
				seed: elements.map(e => e.seed)
			};

		};

		const read = json => Array.isArray(json?.ids) ? json.ids : (MapSceneDecor.failNames(json).includes('Decor::structure.adopted') ? 'adopted' : null);

		this.send('doAdopt', values, read).then(ids => {

			// La scène a pu se fermer pendant la requête
			if(this.closed) {
				return;
			}

			if(ids === 'adopted') {
				this.freeze();
				return;
			}

			// Tout autre échec, déjà affiché par le cadriciel, laisse le défaut en place : le geste qui attendait ses ids se défait
			// sans rien écrire, et le suivant retente l'adoption
			if(ids === null) {
				this.journal = this.journal.filter(item => item !== operation);
				this.fallback = elements;
				return;
			}

			elements.forEach((e, index) => e.id = ids[index]);

		});

	}

	/**
	 * Annuler défait l'adoption de la séance : la structure retrouve le décor par défaut, ses éléments perdent leur ligne et
	 * leur id. Une adoption qui n'a rien écrit, refusée ou partie d'une page figée, se défait sans requête. Le serveur refuse si
	 * une autre fenêtre a posé un élément entre-temps, et un échec laisse la base inconnue : la page se fige dans les deux cas,
	 * puisque la scène vient de rendre au défaut des places que la base n'a peut-être pas suivies.
	 */
	unadopt(elements) {

		const values = () => elements.some(e => e.id !== null) ? { structure: this.sheet.dataset.structure, id: elements.filter(e => e.id !== null).map(e => e.id) } : null;
		const read = json => MapSceneDecor.failNames(json).includes('Decor::structure.unadopted') ? 'refused' : ((MapSceneDecor.failNames(json).length === 0) ? 'done' : null);

		this.send('doUnadopt', values, read).then(result => {

			// La scène a pu se fermer pendant la requête
			if(this.closed) {
				return;
			}

			if(result === 'refused') {
				this.frozen = this.sheet.dataset.unadopted;
				return;
			}

			if(result === null && elements.some(e => e.id !== null)) {
				this.frozen = this.sheet.dataset.unrestored;
				Lime.Alert.showStaticError(this.frozen);
				return;
			}

			elements.forEach(e => e.id = null);

			this.fallback = elements;
			this.queueWalk();

		});

	}

	/**
	 * Les noms des refus d'une réponse, que le cadriciel porte en instructions `errors` (`Instruction::errors()`).
	 */
	static failNames(json) {

		return (json?.__instructions ?? [])
			.filter(([name]) => name === 'js')
			.flatMap(([, instructions]) => instructions)
			.filter(([name]) => name === 'errors')
			.flatMap(([, [errors]]) => errors)
			.map(([name]) => name);

	}

	/**
	 * Un bâtiment enregistré depuis l'éditeur (`MapSceneBuilding`) : il entre dans la rangée ou y change, ses poses se remontent,
	 * et le hangar posé qu'il personnalise (`decor`, null sinon) devient sa pose, comme le serveur l'a écrit. Un bâtiment neuf
	 * s'éclaire à son arrivée, ce qui dit où il est entré.
	 */
	saved(building, decor) {

		this.buildings.set(building.id, building);

		let tile = [...this.tiles].find(item => MapSceneDecor.buildingOf(item) === building.id);

		const created = (tile === undefined);

		if(created) {

			const own = this.buildingTemplate.content.firstElementChild.cloneNode(true);
			const link = own.qs('.map-scene-decor-tile-delete');

			tile = own.qs('[data-kit]');
			tile.dataset.building = building.id;
			link.setAttribute('post-id', building.id);

			this.createTile.before(own);
			this.bindTile(tile);
			this.bindDelete(link);

			this.tiles = this.sheet.qsa('[data-kit]');

		}

		tile.qs('span').textContent = building.name;

		const hangar = this.elements.find(e => e.id === decor && e.kit === 'hangar');

		if(hangar !== undefined) {
			hangar.kit = 'batiment';
			hangar.def = MapSceneDecor.KITS.batiment;
			hangar.building = building.id;
		}

		this.readUpload(building);
		this.remount(building.id);

		if(created) {
			MainJuiciness.flash(tile.parentElement);
		}

	}

	/**
	 * Un bâtiment dont le modèle a changé : sa vignette et ses poses se remontent. Un bâtiment qui ne se compose plus garde ses
	 * poses en base, sans les montrer, comme à la lecture du plan.
	 */
	remount(id) {

		this.buildingGeometries.get(id)?.dispose();
		this.buildingGeometries.delete(id);

		this.elements
			.filter(e => e.building === id)
			.forEach(e => {
				if(this.buildingGeometry(id) === null) {
					this.drop(e);
				} else {
					this.build(e);
				}
			});

		// La feuille de pose, ses vignettes et les emprises que montre `refresh()` n'existent que pour une scène liée à ses gestes
		// (`bind()`, d'où `onKey`), et les vignettes qu'à la première ouverture de la feuille
		if(this.onKey !== undefined) {

			const tile = [...this.tiles].find(item => MapSceneDecor.buildingOf(item) === id);

			tile.classList.toggle('hide', this.family !== 'building' || this.layers.typeOf(this.layers.active) !== null || this.offers(tile) === false);

			if(this.thumbnails && this.offers(tile)) {
				this.paintThumbnails([[tile, { kit: 'batiment', building: id }]]);
			}

			this.refresh();

		}

		this.queueWalk();
		this.changed();

	}

	/**
	 * Un bâtiment retiré de la rangée, et ses poses de la scène, que le serveur a supprimées avec lui, comme les éléments d'un
	 * calque supprimé (`removeLayer()`) : « Annuler » ne les fait pas revenir, et « Terminer » n'a plus à les retirer.
	 */
	deleted(id) {

		if(this.armed?.building === id) {
			this.disarm();
		}

		const removed = this.elements.filter(e => e.building === id);

		removed.forEach(e => this.drop(e));
		this.elements = this.elements.filter(e => e.building !== id);

		if(this.active !== undefined) {

			this.journal = this.journal.filter(operation => operation.e?.building !== id);
			this.removals = this.removals.filter(e => e.building !== id);

			if(removed.includes(this.selected)) {
				this.select(null);
			}

		}

		this.buildings.delete(id);
		this.buildingGeometries.get(id)?.dispose();
		this.buildingGeometries.delete(id);

		[...this.tiles].find(tile => MapSceneDecor.buildingOf(tile) === id)?.parentElement.remove();
		this.tiles = this.sheet.qsa('[data-kit]');

		this.refresh();
		this.queueWalk();
		this.changed();

	}

	/**
	 * Un élément importé depuis son panneau (`MapSceneObject.saved()`) entre dans sa rangée par le gabarit, avant la tuile qui en
	 * importe un, et s'éclaire à son arrivée. Son kit est celui de sa rangée.
	 */
	objectSaved(object) {

		this.objects.set(object.id, object);

		const kit = Object.keys(MapSceneDecor.KITS).find(key => MapSceneDecor.KITS[key].object === true && MapSceneDecor.KITS[key].family === object.family);
		const own = this.objectTemplate.content.firstElementChild.cloneNode(true);
		const link = own.qs('.map-scene-decor-tile-delete');
		const tile = own.qs('[data-kit]');

		tile.dataset.kit = kit;
		tile.dataset.object = object.id;
		tile.qs('span').textContent = object.name;
		link.setAttribute('post-id', object.id);

		[...this.importTiles].find(item => item.dataset.sceneDecorImport === object.family).before(own);
		this.bindTile(tile);
		this.bindDelete(link);

		this.tiles = this.sheet.qsa('[data-kit]');

		this.readObjectUpload(object);
		this.objectRemount(object.id);

		MainJuiciness.flash(own);

	}

	// Les poses d'un élément dont le modèle vient d'être lu, ou d'un élément neuf, et sa vignette
	objectRemount(id) {

		this.objectGeometries.get(id)?.dispose();
		this.objectGeometries.delete(id);

		this.elements
			.filter(e => e.object === id)
			.forEach(e => this.build(e));

		// La feuille de pose et ses vignettes n'existent que pour une scène liée à ses gestes (`bind()`, d'où `onKey`), et les
		// vignettes peintes qu'à la première ouverture de la feuille
		if(this.onKey !== undefined) {

			const tile = [...this.tiles].find(item => MapSceneDecor.objectOf(item) === id);

			tile.classList.toggle('hide', MapSceneDecor.KITS[tile.dataset.kit].family !== this.family || this.layers.typeOf(this.layers.active) !== null || this.offers(tile) === false);

			if(this.thumbnails) {
				this.paintThumbnails([[tile, { kit: tile.dataset.kit, object: id }]]);
			}

			this.refresh();

		}

		this.queueWalk();
		this.changed();

	}

	/**
	 * Un élément importé retiré de sa rangée, et ses poses de la scène, que le serveur a supprimées avec lui, comme celles d'un
	 * bâtiment (`deleted()`).
	 */
	objectDeleted(id) {

		if(this.armed?.object === id) {
			this.disarm();
		}

		const removed = this.elements.filter(e => e.object === id);

		removed.forEach(e => this.drop(e));
		this.elements = this.elements.filter(e => e.object !== id);

		if(this.active !== undefined) {

			this.journal = this.journal.filter(operation => operation.e?.object !== id);
			this.removals = this.removals.filter(e => e.object !== id);

			if(removed.includes(this.selected)) {
				this.select(null);
			}

		}

		this.objects.delete(id);
		this.objectGeometries.get(id)?.dispose();
		this.objectGeometries.delete(id);

		[...this.tiles].find(tile => MapSceneDecor.objectOf(tile) === id)?.parentElement.remove();
		this.tiles = this.sheet.qsa('[data-kit]');

		this.refresh();
		this.queueWalk();
		this.changed();

	}

	dispose() {

		// Un geste en cours écoute la fenêtre : son relâché, après la scène, n'aurait plus de rendu où s'écrire
		this.held?.end();
		this.picking?.();

		clearTimeout(this.notice);

		// Quitter la page en pose vaut « Terminer » pour les retraits : les autres gestes de la séance sont déjà écrits
		this.sendRemovals();

		this.closed = true;

		// Seul `attach()` écoute les contrôles de la vue
		if(this.attached) {
			this.controls.removeEventListener('change', this.onSpread);
		}

		if(this.onKey !== undefined) {

			window.removeEventListener('keydown', this.onKey);
			this.controls.removeEventListener('change', this.onView);
			this.scroll.dispose();

			Object.values(this.pads).forEach(material => material.dispose());
			Object.values(this.lines).forEach(material => material.dispose());
			this.refusedMaterial.dispose();

			this.fine.geometry.dispose();
			this.say.element.remove();
			this.handles.forEach(handle => handle.element.remove());
			this.unpoint.element.remove();

			if(this.grid !== null) {
				this.grid.geometry.dispose();
				this.grid.children.forEach(limit => limit.geometry.dispose());
			}

			this.outline.geometry.dispose();
			this.outline.children[0].geometry.dispose();

		}

		this.elements.forEach(e => this.drop(e));
		this.bridges?.geometry.dispose();
		this.stripes?.geometry.dispose();
		this.geometries.forEach(geometry => geometry.dispose());
		this.buildingGeometries.forEach(geometry => geometry?.dispose());
		this.objectGeometries.forEach(geometry => geometry.dispose());
		this.rippleGeometry.dispose();
		this.xray.dispose();
		this.layers.dispose();
		this.sayAway?.element.remove();
		clearTimeout(this.noticeAway);
		this.material.dispose();
		this.depthMaterial.dispose();

	}

}
