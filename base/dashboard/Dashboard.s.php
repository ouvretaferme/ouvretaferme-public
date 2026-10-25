<?php
namespace dashboard;

/**
 * L'accueil d'un membre dans une structure : un tableau fait de blocs qu'apportent les packages, et un
 * poste du haut fait de contributions qu'ils apportent de la même façon.
 *
 * Le moteur ne nomme aucun bloc. Un package en apporte par deux classes au nom fixe,
 * `\<package>\DashboardLib` et `\<package>\DashboardUi`, que la convention résout depuis le segment
 * précédant le premier tiret du nom du bloc, comme `Fail::format()` résout les `AlertUi`. Il n'y a
 * donc ni registre, ni table, ni liste à tenir à jour. Le contrat est écrit dans
 * `base/dashboard/CLAUDE.md` et tenu par `test/DashboardBlockSentinel.t.php`.
 */
class DashboardSetting extends \Settings {

	/**
	 * Les sections sous lesquelles l'écran range les blocs, dans l'ordre où elles s'y suivent.
	 *
	 * C'est tout ce que le moteur sait du rangement : un bloc désigne sa section, le moteur en tient
	 * l'ordre et le libellé (`DashboardUi::getSectionName()`). L'ordre est celui des onglets du produit
	 * (`ouvretaferme/main/view/Structure.php:72-92`, « Production · AMAP · Vente · Gestion ·
	 * Paramètres ») : c'est l'ordre fixe du rail et de la barre du téléphone, et celui de l'accueil tant
	 * que le membre n'y a pas rangé ses univers (`DashboardLib::sortSections()`, L39).
	 *
	 * La liste est comparée aux cas de `structure\Structure::canSection()` par la sentinelle : c'est
	 * cette méthode qui fait foi, une section qu'elle ne connaît pas n'étant ouvrable par personne.
	 */
	const array SECTIONS = ['production', 'amap', 'commercialisation', 'accounting', 'settings'];

	/**
	 * Les trois colonnes de l'accueil, dans l'ordre où l'écran les lit : ce qui demande un geste, ce qui
	 * se surveille, puis le bilan de la semaine passée (direction « Agir à gauche, suivre à droite »,
	 * retenue le 2026-10-14, L45 ; bilan sorti de « À suivre » à sa demande le 2026-10-19,
	 * L69).
	 *
	 * Un bloc désigne sa colonne comme il désigne sa section, et le moteur n'en tient que l'ordre et le
	 * titre (`DashboardUi::getColumnName()`) : une table qui rangerait les blocs ici serait le `match`
	 * par bloc que le cadre a retiré. La section, elle, reste dans la déclaration. Elle ne range plus
	 * l'accueil, elle teinte la cellule et ordonne les blocs à l'intérieur de chaque colonne.
	 *
	 * Les emplacements ne déclarent pas de colonne, le socle les pose par leur mot : la file ouvre
	 * « À faire », le bilan occupe seul « Bilan » (`DashboardUi::getColumns()`).
	 *
	 * Un bloc ne déclare qu'une colonne de `COLUMNS_BLOCK`. « Bilan » est réservée à l'emplacement : il
	 * compare la semaine passée à l'an passé, et un bloc posé à côté de lui en ferait une colonne de plus
	 * à suivre.
	 */
	const string TODO = 'todo';
	const string FOLLOW = 'follow';
	const string REVIEW = 'review';

	const array COLUMNS = [self::TODO, self::FOLLOW, self::REVIEW];

	const array COLUMNS_BLOCK = [self::TODO, self::FOLLOW];

	/**
	 * Les formes d'un bloc dans sa colonne (L46). `TILE` est une tuile de la grille, `WIDE` une tuile sur
	 * le rang entier, pour un bloc dont la liste ou la bande de jours a besoin de la largeur, et `NOTE` la
	 * feuille épinglée du planning sur le rang entier, pour un bloc qui porte ce que le membre s'est écrit.
	 * `OPEN` sort le bloc des colonnes : titré comme une colonne, posé sur le fond de la page sous les
	 * trois, sur toute la largeur, pour un bloc qui propose plutôt qu'il ne compte (L55, « sors à essayer
	 * de son bloc rose » ; sous les colonnes depuis L69).
	 *
	 * Dans une bande d'univers (le tableau de bord d'une AMAP, `DashboardUi::getSection()`), `WIDE` pose
	 * la cellule avant la grille de son univers, sous son titre, quel que soit son rang de déclaration ;
	 * les autres formes y sont des cellules de la grille.
	 *
	 * Le bloc déclare sa forme comme sa colonne, et le socle la rend sans savoir quel bloc la porte : le
	 * package ne dessine ni tuile ni feuille, sans quoi il ne pourrait plus être déplacé.
	 */
	const string TILE = 'tile';
	const string WIDE = 'wide';
	const string NOTE = 'note';
	const string OPEN = 'open';

	const array SHAPES = [self::TILE, self::WIDE, self::NOTE, self::OPEN];

	/**
	 * Les emplacements du poste du haut, dans l'ordre où l'écran les lit.
	 *
	 * Un bloc est une carte rangée sous la bande de son univers ; le poste, lui, est une composition
	 * unique qui mêle les univers, et le contrat de bloc ne sait pas l'exprimer. Un package y apporte
	 * donc une **contribution** plutôt qu'un bloc, et désigne l'emplacement qu'elle occupe. Le moteur
	 * nomme les emplacements, jamais leurs occupants, exactement comme il nomme les sections et jamais
	 * les blocs qu'elles rangent.
	 *
	 * - `FIGURE` · le chiffre de la semaine en cours, avec celui de la semaine précédente, en une phrase
	 *   sous la date (L62, demandé le 2026-10-18). Il ne se règle pas (`SLOTS_FIXED`).
	 * - `QUEUE` · une ligne de ce qui attend un geste, rangée par ce qu'elle attend
	 *   (`DashboardLib::compareQueue()`) et non par univers. La file ouvre la colonne de gauche (L45).
	 * - `BILAN` · le bilan de la semaine précédente, seul dans la colonne « Bilan »
	 *   (`DashboardUi::getColumns()`, L69), qui la compare à l'an passé (planche C du L9, retenue le 2026-10-05). Son occupant déclare la `section`
	 *   dont la cellule prend la couleur, et décide seul du jour où il parle : le socle ne connaît pas le
	 *   lundi. Il ne se règle pas (`SLOTS_FIXED`).
	 *
	 * - `WEATHER` · la météo du moment et des trois jours qui viennent, dans le groupe Bureau du menu,
	 *   sous ses entrées (L52, demandé le 2026-10-17). Elle ne se règle pas (`SLOTS_FIXED`).
	 *
	 * - `NEWS` · les dernières nouvelles du blog de Ouvretaferme (L40). Elles parlent du produit et non
	 *   de la structure : elles ne vivent dans aucun univers, et l'accueil ne les range dans aucune
	 *   colonne. Elles ont leur page (L50), que l'entrée « Du nouveau » du groupe Bureau ouvre, et
	 *   l'entrée paraît dès qu'il y en a une (L46, `DashboardLib::loadNews()`). Elles ne se règlent pas (`SLOTS_FIXED`).
	 *
	 * Un emplacement de plus est un mot ajouté ici et une méthode de rendu dans le socle. Une affiche
	 * (un nombre, une phrase, un lien) a été prévue puis retirée le 2026-09-29 : son nombre
	 * paraissait déjà dans la file et dans le bloc des commandes du jour.
	 */
	const string FIGURE = 'figure';
	const string QUEUE = 'queue';
	const string BILAN = 'bilan';
	const string WEATHER = 'weather';
	const string NEWS = 'news';

	const array SLOTS = [self::WEATHER, self::BILAN, self::FIGURE, self::QUEUE, self::NEWS];

	/**
	 * Les emplacements que le membre ne règle pas : ni interrupteur dans le panneau, ni ligne de
	 * préférence lue. Leur occupant paraît dès que la structure et le rôle l'offrent et qu'il a quelque
	 * chose à dire, et le refus d'un réglage sur son nom passe par la vérification du nom de
	 * `Preference` (arbitré par V-Dashboard le 2026-10-05).
	 */
	const array SLOTS_FIXED = [self::WEATHER, self::BILAN, self::FIGURE, self::NEWS];

	/**
	 * Ce que coûte un bloc, et donc quand il est rendu.
	 *
	 * `LIGHT` ne lit que la base commune et se rend dans la page. `HEAVY` ouvre une base de structure
	 * ou paie une lecture qu'on ne veut pas dans le temps de la page : le socle rend un emplacement,
	 * que la route du bloc remplit par ajax une fois l'écran affiché.
	 */
	const string LIGHT = 'light';
	const string HEAVY = 'heavy';

	/**
	 * Le drapeau qui ouvre l'écran d'accueil à une structure, lu par la route de l'écran, par la route
	 * d'un bloc, par celle du panneau de personnalisation et par le réglage qu'il écrit, chacun pour la
	 * structure qu'il touche.
	 *
	 * Il ne dit que ceci : l'adresse répond. Que le membre y soit **mené** sans l'avoir demandée est
	 * l'affaire de `$landing`, et les deux se règlent séparément pour qu'une structure puisse visiter
	 * l'écran par son adresse avant qu'il devienne son accueil.
	 *
	 * ⚠️ Une closure et non une lecture directe de `FEATURE_DASHBOARD` : la constante se définit une
	 * fois par processus (`ouvretaferme/Lime.s.php`), et seule une closure laisse un test éprouver une
	 * structure hors liste.
	 */
	public static \Closure $enabled;

	/**
	 * La barrière tient ici et non dans la closure : les tests remplacent `$enabled` pour ouvrir les
	 * structures de leur décor, et une barrière posée dans la closure tomberait avec chacun d'eux.
	 *
	 * - Une AMAP n'a jamais l'accueil, quels que soient les drapeaux. Son tableau de bord est
	 *   `/ferme/<id>/tableau-de-bord` (`amap/dashboard`), qui reprend ses blocs par `loadSection()`.
	 *   La navigation, elle, ne passe pas par ici : elle est la même pour toutes les structures.
	 * - Un producteur n'y entre qu'avec l'offre Pilotage (`Structure::hasPilotage()`).
	 */
	public static function isEnabled(\structure\Structure $eStructure): bool {

		return (
			$eStructure->isAmap() === FALSE and
			$eStructure->hasPilotage() and
			(self::$enabled)($eStructure)
		);

	}

	/**
	 * Le drapeau qui fait de l'accueil le point d'arrivée du membre dans cette structure, lu par
	 * `structure\Structure::getAccessSection()`, par `getMenuSection()`, par l'entrée « Accueil de la
	 * structure » du menu de compte et par l'onglet Bureau du gabarit de structure
	 * (`StructureTemplate::hasHome()`).
	 *
	 * Ce qu'il commande n'est pas un écran de plus mais un déplacement : il change la section où la
	 * structure fait arriver et le rang où le menu de compte mène. Fermé, ces quatre lecteurs retombent
	 * sur ce que le produit faisait avant l'accueil, sans que l'adresse de l'écran cesse pour autant de
	 * répondre.
	 *
	 * L'onglet Bureau est de ce côté-ci et non du côté de `$enabled` parce qu'il **mène** à l'accueil :
	 * sous le seul premier drapeau, il resterait posé sur tous les écrans de la structure alors que plus
	 * rien n'y conduit. Le prix de ce rangement est l'autre moitié du cas : une structure ouverte à
	 * l'adresse mais hors point d'arrivée voit l'accueil sans onglet pour y revenir.
	 *
	 * ⚠️ Il ne s'ouvre jamais plus largement que `$enabled`, et c'est `isLanding()` qui le tient : une
	 * structure menée vers une adresse que la route refuse n'a aucun écran où atterrir, et les deux
	 * valeurs ne se posent pas au même endroit, celle-ci dans le `secret.c.php` d'un poste, l'autre dans
	 * `ouvretaferme/Lime.s.php`. Toute lecture du second drapeau passe donc par `isLanding()`, jamais
	 * par la closure.
	 *
	 * Sa valeur est `FEATURE_DASHBOARD_LANDING`, TRUE par défaut : le point d'arrivée suit l'écran. Le
	 * `secret.c.php` d'un poste peut le refermer pour retrouver l'arrivée d'avant.
	 *
	 * ⚠️ Une closure et non une constante, pour la raison écrite sur `$enabled`.
	 */
	public static \Closure $landing;

	public static function isLanding(\structure\Structure $eStructure): bool {
		return (self::isEnabled($eStructure) and (self::$landing)($eStructure));
	}

	/**
	 * Le drapeau qui pose l'équipe en 3D dans la tête de l'accueil (A8 de 3d-avatars), lu par la route de l'écran.
	 *
	 * L'équipe ne vit que sur l'accueil : elle ne s'ouvre jamais plus largement que `$enabled`, et `isTeam()` le tient
	 * comme `isLanding()` tient le point d'arrivée. Sa valeur est `FEATURE_DASHBOARD_TEAM`, TRUE par défaut (plus bas),
	 * qui peut se refermer sans fermer l'accueil le jour où celui-ci s'ouvre à tout le parc.
	 *
	 * L'équipe n'est montrée qu'à un membre : `canRead()` ouvre aussi l'accueil d'une école et d'une structure partagée
	 * en communauté, dont le visiteur n'a pas à voir les membres.
	 *
	 * ⚠️ Une closure et non une constante, pour la raison écrite sur `$enabled`.
	 */
	public static \Closure $team;

	public static function isTeam(\structure\Structure $eStructure): bool {
		return (
			self::isEnabled($eStructure) and
			$eStructure->isMember() and
			(self::$team)($eStructure)
		);
	}

	/**
	 * Le plus grand nombre de membres dont l'accueil envoie le personnage. La tête n'en montre que ce que sa largeur
	 * tient, six à 1854 px (planche v3 d'A8) : au-delà, une fiche de personnage ne servirait qu'à alourdir la page.
	 */
	const int TEAM_MAX = 12;

	/**
	 * Le plus grand nombre d'absences que la liste de la tête nomme : quatre lignes tiennent dans la hauteur du rang
	 * (planche v6, arbitrage 26). Au-delà, une dernière ligne compte les autres et mène à l'onglet Absences du planning.
	 */
	const int TEAM_ABSENCES_MAX = 4;

	/**
	 * Le plus grand nombre de modèles téléversés que la tête monte. Un modèle pèse jusqu'à 1 Mo et se lit avant que
	 * la scène s'ouvre : au-delà, les membres suivants montent leur composition, qui ne coûte aucune lecture.
	 */
	const int TEAM_UPLOADS_MAX = 6;

	/**
	 * La part d'un jour d'absence dans la liste de la tête (`DashboardLib::getAbsences()`) : toute la journée, le matin
	 * seul, l'après-midi seul, ou quelques heures. La liste dit le jour seul pour une journée entière, suivi de ses
	 * plages pour quelques heures, et seule une journée entière met la personne au second plan.
	 */
	const string ABSENCE_DAY = 'day';
	const string ABSENCE_MORNING = 'morning';
	const string ABSENCE_AFTERNOON = 'afternoon';
	const string ABSENCE_HOURS = 'hours';

	/**
	 * La règle d'un drapeau, séparée de sa valeur : `TRUE` ouvre toutes les structures, une liste
	 * n'ouvre que les siennes, le nom d'une fonctionnalité ouvre d'un coup toutes celles qui l'ont
	 * (`Structure::conf()`). Un test l'éprouve sur la valeur de son choix.
	 */
	public static function accepts(\structure\Structure $eStructure, bool|array|string $flag): bool {

		if(is_string($flag)) {
			return (bool)$eStructure->conf(\structure\Feature::class)[$flag];
		}

		return ($flag === TRUE or (is_array($flag) and in_array($eStructure['id'], $flag, TRUE)));

	}

	/**
	 * Le package qui apporte un bloc ou une contribution, dérivé du segment qui précède le premier
	 * tiret de son nom.
	 *
	 * C'est l'unique dérivation du cadre, et elle sert des deux côtés : le `DashboardLib` du package
	 * pour ce qu'il sait du bloc, son `DashboardUi` pour ce qu'il en montre. Les deux formes de
	 * déclaration se résolvent par la même convention, et le nom s'écrit de la même façon.
	 */
	public static function getPackage(string $block): string {
		return explode('-', $block)[0];
	}

	/**
	 * Le `DashboardLib` qui apporte un bloc ou une contribution : sa déclaration, sa donnée, et où mène
	 * son geste de pied.
	 *
	 * ⚠️ Une closure et non un appel direct, pour la même raison que `$enabled` : elle laisse un test
	 * éprouver le moteur sans fabriquer en base la donnée dont le bloc a besoin.
	 */
	public static \Closure $resolve;

	public static function resolve(string $block): string {
		return (self::$resolve)($block);
	}

}

// Le fichier de secrets est chargé par `ouvretaferme/Lime.s.php` avant les réglages des packages : une
// valeur qu'il pose est déjà là, et seul un poste qui n'en pose aucune reçoit ce défaut.
if(defined('FEATURE_DASHBOARD_LANDING') === FALSE) {
	define('FEATURE_DASHBOARD_LANDING', TRUE);
}

// L'équipe vaut TRUE par défaut, comme le point d'arrivée : `isTeam()` la borne par `isEnabled()`, donc par
// `FEATURE_DASHBOARD`, ce qui l'ouvre à toutes les structures en dev et à la structure 7 en production. Le
// `secret.c.php` d'un poste peut poser `FEATURE_DASHBOARD_TEAM` pour la refermer sans fermer l'accueil.
if(defined('FEATURE_DASHBOARD_TEAM') === FALSE) {
	define('FEATURE_DASHBOARD_TEAM', TRUE);
}

DashboardSetting::$enabled = fn(\structure\Structure $eStructure): bool => DashboardSetting::accepts($eStructure, FEATURE_DASHBOARD);

/**
 * Le second drapeau vaut TRUE par défaut : borné par `isLanding()`, il ouvre l'arrivée sur l'accueil
 * exactement là où l'écran est ouvert, toutes les structures en dev et la structure 7 en
 * production (`FEATURE_DASHBOARD`). Le `secret.c.php` d'un poste peut poser
 * `FEATURE_DASHBOARD_LANDING` pour le refermer (`ouvretaferme/secret-example.c.php`). La constante
 * prend une valeur d'`accepts()`, et elle est toujours définie (plus haut), donc lue sans garde.
 */
DashboardSetting::$landing = fn(\structure\Structure $eStructure): bool => DashboardSetting::accepts($eStructure, FEATURE_DASHBOARD_LANDING);

DashboardSetting::$team = fn(\structure\Structure $eStructure): bool => DashboardSetting::accepts($eStructure, FEATURE_DASHBOARD_TEAM);

DashboardSetting::$resolve = fn(string $block): string => '\\'.DashboardSetting::getPackage($block).'\\DashboardLib';
?>
