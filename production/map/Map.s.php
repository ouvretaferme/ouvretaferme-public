<?php
namespace map;

class MapSetting extends \Settings {

	public static $mapboxToken = NULL;

	public static int $planFormatsMax = 4;

	/* Plafonds du démarrage guidé : au-delà, le formulaire de démarrage n'est plus le bon outil */
	public static int $onboardingPlotsMax = 1;
	public static int $onboardingBedsMax = 200;

	/* Version du format que rend SceneLib::getByStructure(), qui dit quand elle monte */
	const int SCENE_VERSION = 2;

	/* Les modèles importés que la file de l'administration montre par page (`DecorUploadLib::getWaiting()`, `getExamined()`), et
	   au plus ce que « Tout valider » garde d'un geste (`keepAll()`) */
	const int MAX_BY_PAGE = 50;

	/**
	 * Le décor d'une structure tient dans ce que la scène laisse sous une cible de 1,21 M triangles. La pire journée de la
	 * structure 7, décor masqué et relief éteint, en prend 870 788 (mesurée le 2026-10-11), le relief allumé jusqu'à 60 000 (sol,
	 * soubassements, tuyaux drapés), les tracés DECOR_TRACE_LENGTH mètres à 29 triangles, chaque tracé y comptant à son poids
	 * (`DecorLib::TRACE_WEIGHT`, `DecorLib::TRACE_START`), et la haie 1 455 de plus au pire, le zigzag mesuré au-dessus de
	 * `DecorLib::HEDGE_WEIGHT`. Le pré autour des cultures en prend 10 000 (`MapSceneMeadow.TRIANGLES`, relief ou non) et les
	 * insectes 1 538 de plus que dans cette pire journée, au plus 12 papillons de 190 triangles et 14 abeilles de 42 contre 7
	 * papillons (mesurés le 2026-10-25). Restent DECOR_TRIANGLES = 1 210 000 - 870 788 - 60 000 - 2 700 × 29 - 1 455 - 11 538 aux
	 * objets posés, chacun à son plus lourd tirage (`DecorLib::KIT_TRIANGLES`). DECOR_MAX borne la liste, pas le poids.
	 */
	const int DECOR_MAX = 120;
	const int DECOR_TRACE_LENGTH = 2700;
	const int DECOR_TRIANGLES = 187919;

	/* Un tracé au clic se pose au mètre près (MapSceneDecor.snap()). Un tracé dessiné relève au plus un point par mètre
	   (MapSceneDecor.FREEHAND_STEP), puis le relâché le simplifie jusqu'à ce qu'il tienne sous ce plafond. Le plafond borne ce qu'un
	   POST fait écrire, et le navigateur le lit pour refuser le point de trop avant l'enregistrement */
	const int DECOR_TRACE_POINTS = 500;

	/* Les calques se partagent DECOR_MAX, DECOR_TRACE_LENGTH et DECOR_TRIANGLES : ce plafond borne la carte des calques, pas
	   le poids de la scène */
	const int DECOR_LAYER_MAX = 10;

	/* Chaque hangar personnalisé ajoute un bâtiment à la rangée sans qu'aucun ne s'en retire seul : ce plafond borne la rangée
	   et ce que la structure garde en base, pas le poids de la scène, que DECOR_TRIANGLES tient déjà */
	const int DECOR_BUILDING_MAX = 30;

	/* Les éléments importés dans Eau, Nature et Matériel, toutes rangées confondues, suivent la même règle : ce plafond borne
	   ce que la structure garde en base, et DECOR_TRIANGLES le poids de leurs poses */
	const int DECOR_OBJECT_MAX = 30;

	/**
	 * Le décor par défaut que la scène tire pour une structure sans décor (`sceneDecorDefault.js`), et qu'elle enregistre en bloc au
	 * premier geste : ses kits et son plus grand nombre d'éléments, GROVE_COUNT bosquets et TREE_COUNT arbres au plus.
	 */
	const array DECOR_DEFAULT_KITS = ['massif', 'verger', 'buissons', 'alignement', 'arbre', 'fruitier'];
	const int DECOR_DEFAULT_MAX = 20;

	/* Les diamètres extérieurs normalisés des tubes PE, en mm, que la pose d'un tuyau propose ; « Autre » en saisit un hors de la liste */
	const array DECOR_PIPE_SIZES = [16, 20, 25, 32, 40, 50, 63, 75, 90, 110];

	/* Rayon autour du centre du plan où un décor se pose, en mètres : bien au-delà de la plus grande
	   structure, assez court pour refuser une position qui n'a rien à voir avec son plan */
	const int DECOR_NEAR = 5000;

	/* Plafond de sommets d'un contour de parcelle ou de jardin : cinq fois le plus grand contour connu
	   (94 sommets), assez bas pour qu'un POST ne fasse pas écrire un polygone démesuré */
	const int SHAPE_POINTS_MAX = 500;

	/* Les modèles téléversés que l'équipe de la scène monte à la fois, aux plus présents de la saison : un binaire pèse jusqu'à
	   1 Mo, que la page lit avant d'ouvrir la scène. Au-delà, l'avatar montre sa composition (`SceneLib::getTeam()`), comme sur
	   l'accueil sous DashboardSetting::TEAM_UPLOADS_MAX */
	const int CREW_UPLOADS_MAX = 6;

	/**
	 * Le Registre parcellaire graphique que l'outil « Parcelle PAC » lit au clic (PacLib) : le WFS public de la Géoplateforme,
	 * sa dernière déclaration publiée. Un clic charge les parcelles d'un carré de PAC_AROUND mètres de demi-côté, PAC_COUNT au
	 * plus : 31 parcelles et 26 Ko autour de Tallende, mesurés le 22/10. Chaque appel au service attend PAC_TIMEOUT secondes au
	 * plus, et un clic en fait deux quand l'année n'est pas en cache (PacLib::getYear()). Un utilisateur l'appelle PAC_LIMIT fois
	 * par PAC_WINDOW secondes, ce que le geste, qui ne recharge que hors du carré, n'atteint pas
	 */
	const string PAC_URL = 'https://data.geopf.fr/wfs/ows';
	const string PAC_LAYER = 'RPG.LATEST:parcelles_graphiques';
	const int PAC_AROUND = 500;
	const int PAC_COUNT = 300;
	const int PAC_TIMEOUT = 5;
	const int PAC_LIMIT = 60;
	const int PAC_WINDOW = 600;

	/* La table des cultures du registre, que le survol d'une parcelle PAC lit (PacLib::getCrops()) : 144 cultures le 22/10, lues
	   PAC_CROPS_COUNT au plus */
	const string PAC_CROPS_LAYER = 'RPG.LATEST:codes_cultures';
	const int PAC_CROPS_COUNT = 1000;

	/* Deux sommets d'une parcelle PAC plus proches que cette distance, en mètres, n'en font qu'un : le registre en double souvent à
	   quelques centimètres, ce qu'aucune poignée ne saurait séparer */
	const float PAC_MERGE = 1.0;

	/**
	 * Le relief sous le plan 3D (ReliefLib) : le RGE ALTI de l'IGN, lu par le WMS raster de la Géoplateforme en tuiles de
	 * RELIEF_SIZE points de côté. Les tuiles suivent une maille nationale, RELIEF_DIVISIONS_LAT points par degré de latitude et
	 * RELIEF_DIVISIONS_LNG par degré de longitude, quatre mètres environ en métropole : l'IGN ne reçoit que des emprises de cette
	 * maille, jamais une boite tracée autour d'une structure. Les tuiles enregistrées n'ont de sens que dans cette maille, et changer
	 * l'une des deux divisions oblige à vider la table.
	 *
	 * Seul le cron interroge le service, RELIEF_TIMEOUT secondes au plus par tuile, RELIEF_TILES_MAX tuiles au plus par structure
	 * (39 au 99e centile le 24/10), RELIEF_CALLS_MAX appels au plus par passage. Une tuile en échec attend RELIEF_RETRY secondes
	 * avant d'être redemandée, une tuile que l'IGN ne couvre pas RELIEF_UNCOVERED_RETRY : la réponse ne changerait pas d'ici là,
	 * sauf une panne qui l'aurait rendue hors du territoire.
	 */
	const string RELIEF_URL = 'https://data.geopf.fr/wms-r/wms';
	const string RELIEF_LAYER = 'ELEVATION.ELEVATIONGRIDCOVERAGE.HIGHRES';
	const int RELIEF_SIZE = 64;
	const int RELIEF_DIVISIONS_LAT = 27000;
	const int RELIEF_DIVISIONS_LNG = 19000;
	const int RELIEF_TIMEOUT = 10;
	const int RELIEF_TILES_MAX = 40;
	const int RELIEF_CALLS_MAX = 300;
	const int RELIEF_RETRY = 86400;
	const int RELIEF_UNCOVERED_RETRY = 86400 * 90;

	/* Les tuiles couvrent les zones élargies de cette distance, en mètres : la portée du décor autour des îlots (ScenePlanLand.REACH) */
	const int RELIEF_REACH = 100;

	/* Une altitude du service hors de ces bornes, en mètres, refuse sa tuile : -99 999 dit un point hors du territoire couvert, et
	   les décimètres d'un int16 ne vont pas au-delà de 3 276,7 m */
	const float RELIEF_ALTITUDE_MIN = -100.0;
	const float RELIEF_ALTITUDE_MAX = 3276.7;

	/* Sous ce dénivelé entre les sommets des zones, en mètres, le relief ne se verrait pas : la scène reste plate */
	const int RELIEF_THRESHOLD = 3;

	/**
	 * L'appel au service, url et paramètres vers la réponse, ou NULL quand il ne répond pas : une closure pour la même raison
	 * qu'`$irrigationEnabled`, un test la repose par un faux service.
	 */
	public static \Closure $reliefTransport;

	/* L'écart toléré entre un sommet déplacé et la place que le mouvement de son contour lui donne, en mètres (MapLib::getMotion()).
	   MapDrawingCopy.MOTION_TOLERANCE porte la même valeur dans le navigateur */
	const float MOTION_TOLERANCE = 0.01;

	/* L'écart à l'angle droit qu'un contour d'abri tolère pour se mesurer comme un rectangle, en cosinus : un peu plus d'un
	   demi-degré, la marge d'un rectangle tracé sur la carte puis enregistré en degrés (MapLib::getDimensions()) */
	const float RECTANGLE_SKEW = 0.01;

	/* L'écart relatif sous lequel deux rectangles englobants ont la même aire : les arrondis du serveur et du navigateur ne
	   tombent pas toujours du même côté (MapLib::getBoundingSides()) */
	const float DIMENSIONS_TIE = 0.000001;

	/* Les mètres d'un degré, sur un méridien comme sur l'équateur, du plan local où se tourne une forme (MapDrawingRectangle.METERS) */
	const int PLANE_METERS = 111320;

	/* Le plus grand pas, en mètres sur chaque axe, dont une copie de jardin s'éloigne de l'original : une parcelle tient dans un kilomètre */
	const int COPY_SHIFT_MAX = 1000;

	/* L'écart à droite de l'original où une copie de jardin se pose d'abord, en mètres */
	const int COPY_GAP = 4;

	/**
	 * Le réseau d'irrigation n'est ouvert qu'en dev et aux structures de cette liste : ailleurs, ni son
	 * catalogue, ni ses éléments, ni ses calques, et le serveur refuse d'en créer.
	 *
	 * Une constante du package et non un `FEATURE_*` de `ouvretaferme/Lime.s.php` : seul `map` la lit, et
	 * elle n'ouvre qu'une partie de l'écran de la 3D, comme les listes
	 * `*_STRUCTURE_BETA` d'autres packages (`FinancialYearSetting::TAX_RETURN_STRUCTURE_BETA`).
	 */
	const array|bool DECOR_IRRIGATION = (LIME_ENV === 'dev') ? TRUE : [7, 17];

	/* Les kits du réseau d'irrigation, ceux que `MapSceneDecor.KITS` range dans le calque `irrigation` (`sceneDecor.js`) */
	const array DECOR_IRRIGATION_KITS = ['pompe', 'vanne', 'electrovanne', 'tuyau', 'rampe'];

	/**
	 * Une closure et non une lecture directe de `DECOR_IRRIGATION` : un test ne peut pas réaffecter une
	 * constante, alors qu'il repose une closure pour éprouver une structure dans la liste puis hors d'elle.
	 * Même forme que `NotificationSetting::$enabled` (`base/notification/Notification.s.php`).
	 */
	public static \Closure $irrigationEnabled;

	public static function isIrrigationEnabled(\structure\Structure $eStructure): bool {
		return (self::$irrigationEnabled)($eStructure);
	}

	/**
	 * Le modèle 3D téléversé qui fait un bâtiment neuf de la rangée suit le drapeau de l'avatar téléversé, `FEATURE_UPLOAD_MODEL`
	 * (`ouvretaferme/Lime.s.php`) : une closure pour la même raison qu'`$irrigationEnabled`. Il ne garde que l'envoi et le
	 * skill : drapeau refermé, un bâtiment importé se montre encore, et se supprime par la poubelle de la rangée.
	 */
	public static \Closure $decorUploadEnabled;

	public static function isDecorUploadEnabled(\structure\Structure $eStructure): bool {
		return (self::$decorUploadEnabled)($eStructure);
	}

	/* Un utilisateur fait convertir DECOR_UPLOAD_LIMIT modèles du décor par DECOR_UPLOAD_WINDOW secondes, refus compris
	   (`\user\ThrottleAiLib::increment()`) : node tourne pour chacun, jusqu'à son plafond de durée (`\scene\UploadLib::TIMEOUT`) */
	const int DECOR_UPLOAD_LIMIT = 50;
	const int DECOR_UPLOAD_WINDOW = 86400;

	/**
	 * La règle d'un drapeau, séparée de sa valeur : `TRUE` ouvre toutes les structures, une liste
	 * n'ouvre que les siennes. Même forme que `NotificationSetting::accepts()`.
	 */
	public static function accepts(\structure\Structure $eStructure, bool|array $flag): bool {
		return ($flag === TRUE or (is_array($flag) and in_array($eStructure['id'], $flag, TRUE)));
	}

}

MapSetting::$mapboxToken = fn() => throw new \Exception('Missing mapbox token');
MapSetting::$irrigationEnabled = fn(\structure\Structure $eStructure): bool => MapSetting::accepts($eStructure, MapSetting::DECOR_IRRIGATION);
MapSetting::$decorUploadEnabled = fn(\structure\Structure $eStructure): bool => MapSetting::accepts($eStructure, FEATURE_UPLOAD_MODEL);
// Hors de la production, le service n'est jamais appelé : la base de dev est une copie de la production, et ses zones situent
// des structures réelles
MapSetting::$reliefTransport = (LIME_ENV === 'prod') ?
	fn(string $url, array $arguments): ?string => \map\ReliefLib::call($url, $arguments) :
	fn(string $url, array $arguments): ?string => NULL;
?>
