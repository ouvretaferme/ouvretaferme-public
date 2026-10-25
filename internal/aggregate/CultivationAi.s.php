<?php
namespace aggregate;

/**
 * Les réglages du plan de culture agrégé de la bibliothèque de cultures.
 *
 * Ils décident de ce qui est montré et de ce qui est écarté d'un échantillon
 * saisi à la main par des centaines de fermes. Ils vivent ici et non dans
 * `CultivationCropPlanAiLib` parce qu'ils s'ajustent à l'usage, sans rapport avec
 * le calcul qui les applique.
 *
 * Aucun n'est cuit dans la projection : ils s'appliquent tous à la lecture, sur les
 * colonnes brutes de `aggregate\Cultivation`, et se déplacent donc sans rien
 * reconstruire. Les réglages de pure présentation (vues, hauteur du relief) restent
 * dans `plant\PlantSetting`, avec la page qui les lit.
 */
class CultivationAiSetting extends \Settings {

	/**
	 * Les paliers d'altitude sur lesquels l'échantillon peut être restreint.
	 *
	 * Un plan de culture ne se compare qu'entre altitudes voisines : à 900 m, les
	 * dates d'une plaine ne disent rien. Les bornes sont ouvertes à droite — une
	 * structure à 400 m est en plaine haute, pas en plaine —, ce qui compte parce
	 * que les altitudes sont arrondies à la dizaine et tombent donc souvent pile
	 * sur une borne.
	 *
	 * Une structure qui n'a pas renseigné son altitude n'entre dans aucun palier :
	 * elle disparaît de l'échantillon dès qu'un palier est demandé.
	 *
	 * Aucune clé n'est purement numérique : PHP les convertirait en entier, et la
	 * comparaison avec le paramètre d'URL, qui est une chaîne, échouerait.
	 */
	const ALTITUDES = [
		'0-400' => [0, 400],
		'400-700' => [400, 700],
		'700-1000' => [700, 1000],
		'1000-plus' => [1000, NULL],
	];

	/**
	 * La part des fermes que l'intervalle de la vue simplifiée doit contenir, étape
	 * par étape.
	 *
	 * L'intervalle est centré sur la médiane et laisse de côté autant de fermes de
	 * chaque bord : les plus précoces d'un côté, les plus tardives de l'autre, en
	 * nombre égal — à 50 %, l'intervalle interquartile. Ce n'est pas un intervalle de
	 * confiance sur la médiane : il ne dit pas où se trouve la vraie moyenne, mais où
	 * se trouve la part annoncée des fermes, qui est la question qu'on se pose en
	 * choisissant une date.
	 *
	 * Trois réglages parce que les trois étapes ne se lisent pas de la même façon.
	 * L'**implantation** définit le créneau : ses valeurs tiennent par construction
	 * dans les semaines du mois, il n'y a donc rien à écarter et tout à montrer. La
	 * **pépinière** est une conséquence de la date d'implantation, resserrée, et
	 * supporte un intervalle large. La **récolte** est la plus dispersée des trois et
	 * la moins bien saisie : au-delà de la moitié des fermes, l'intervalle s'étire sur
	 * un trimestre et ne dit plus rien d'utile.
	 */
	const RANGE_SHARE_IMPLANTATION = 100;
	const RANGE_SHARE_SEEDLING = 75;
	const RANGE_SHARE_HARVEST = 50;

	/**
	 * Nombre de séries au-delà duquel une ferme entre dans l'échantillon.
	 *
	 * En deçà, la ferme essaie le logiciel : elle a monté deux ou trois séries pour
	 * voir, avec des dates qui sont des valeurs par défaut jamais reprises. Mesuré au
	 * 6 août 2026, le seuil écarte 5 à 15 % des fermes selon l'espèce mais seulement
	 * 1 à 5 % des cultures — c'est bien le profil d'un essai. L'écart entre les deux
	 * chiffres est la raison de ce réglage : la page compte des fermes, elle affiche
	 * leur nombre et seuille ses créneaux dessus.
	 */
	const STRUCTURE_SERIES_MIN = 10;

	/**
	 * Les largeurs de planche sur lesquelles la bibliothèque sait grouper des conduites,
	 * en centimètres. Liste fermée, écrite ici et non déduite de la base (décidé par
	 * le 2026-09-20 : « établir une liste fixe de largeur de planche qu'on gère,
	 * c'est en dur », puis le 2026-09-21 : « on ne conserve que 75, 80, 100, 120 »).
	 *
	 * Sans elle, l'écran proposerait autant de groupes que la base porte de valeurs :
	 * mesuré le 2026-09-21 sur le périmètre servi, **56 largeurs distinctes** sur
	 * 67 340 séries en planche, dont une bonne part sont des saisies isolées ou de
	 * contournement (la largeur 1, la largeur 400).
	 *
	 * Elle ne ferme pas ce qu'une structure peut saisir : le réglage de
	 * `mapConfiguration.defaultBedWidth` reste libre (décidé le
	 * 2026-09-20). Une largeur hors liste, réglée ou demandée par l'adresse, ouvre la
	 * largeur de la liste la plus proche (2026-09-21 : « on sélectionne par
	 * défaut la largeur qu'on gère la plus proche ») ; ses propres séries ne sont
	 * rangées sous aucune, leurs densités ne se transposant pas d'une largeur à l'autre.
	 *
	 * Chaque entrée est un groupe de largeurs dont les conduites se mettent en commun,
	 * rangé sous la plus étroite, qui est la valeur que porte l'adresse. 75 et 80 ne font
	 * qu'un (2026-09-21 : « regroupe 75 cm et 80 cm en 75 - 80 cm, et donc
	 * mutualise les données ») : cinq centimètres ne changent pas la façon de monter une
	 * planche.
	 */
	const BED_WIDTHS = [
		75 => [75, 80],
		100 => [100],
		120 => [120],
	];

	/**
	 * Toutes les largeurs de la liste à la fois, que l'adresse porte comme une largeur
	 * (2026-09-21 : « un onglet Toutes qui ne porte que le diagramme des
	 * densités »). Une densité se compte au m², elle se compare donc d'une largeur à
	 * l'autre, ce que ne font pas les rangs et l'écartement des cartes.
	 */
	const BED_WIDTH_ALL = 0;

	/**
	 * Nombre de conduites montrées en cartes pour chaque mode, les plus pratiquées
	 * (2026-09-21 : « le top 3 PC et le top 3 sous abri »). Toutes restent
	 * dans la vue d'ensemble.
	 */
	const CONDUCT_TOP = 3;

	/**
	 * Nombre de structures au-delà duquel une conduite — une largeur de planche, un
	 * nombre de rangs, un écartement sur le rang, un mode — s'affiche.
	 *
	 * Même raison que le seuil des créneaux, et même protection : la table porte la
	 * structure et sa culture (cf. `AnonymitySentinelAiTest`), c'est ce seuil appliqué
	 * à la lecture qui empêche d'isoler une ferme derrière une pratique rare. En deçà,
	 * une conduite ne décrit pas une pratique partagée mais l'essai de quelques-uns.
	 */
	const CONDUCT_STRUCTURES_MIN = 5;

	/**
	 * Nombre de cultures qui doivent annoncer un objectif de rendement pour que la
	 * médiane d'une conduite s'affiche.
	 *
	 * « Silence sous 5 cultures » (direction B du bloc 3, arbitrée le
	 * 2026-09-20), relevé à 20 par le 2026-09-21. L'objectif est une saisie facultative et souvent laissée au défaut :
	 * une médiane prise sur deux cultures se lirait comme les autres, alors qu'elle ne
	 * dit rien de plus que ce que deux personnes ont tapé. Le seuil est compté sur les
	 * cultures et non sur les structures, l'objectif étant une propriété de la culture.
	 */
	const CONDUCT_YIELD_CULTIVATIONS_MIN = 20;

	/**
	 * Part des objectifs de rendement écartée de chaque côté avant la médiane : le
	 * dixième le plus bas et le dixième le plus haut (2026-09-21 : « écarter
	 * les 10/90 comme ailleurs »). L'objectif est une saisie libre, et une faute de
	 * frappe d'un ordre de grandeur (60 pour 6) se glisse dans une conduite.
	 *
	 * Le nombre écarté est `floor(part × n)`, le même des deux côtés : rien sous dix
	 * objectifs, un de chaque côté de dix à dix-neuf. Sous dix, la médiane encaisse
	 * seule une valeur aberrante, ce qu'elle fait tant qu'elle n'en a pas plusieurs.
	 * Le seuil de cultures se compte avant l'écartement, sur les cultures qui
	 * annoncent un objectif.
	 */
	const CONDUCT_YIELD_TRIM = 0.1;

	/**
	 * Nombre de graduations au plus de l'échelle verticale d'un histogramme de densités,
	 * chacune portant une ligne de grille : au-delà, le quadrillage devient une hachure
	 * sur une bande de 6rem.
	 */
	const CONDUCT_SCALE_TICKS = 4;

	/**
	 * Nombre de graduations au plus de l'axe des densités (2026-09-21 : « mets
	 * plus de labels sur l'axe des abscisses »). Sur une bande étroite, l'écran n'en
	 * écrit qu'une sur deux, sur cinq ou sur dix, celles qui tiennent sans se toucher.
	 */
	const CONDUCT_AXIS_TICKS = 10;

	/**
	 * Nombre d'intervalles de la grille de fond d'une bande, et donc de barres possibles
	 * de son histogramme, au moins et au plus. La grille partage chaque intervalle de
	 * graduation en parts égales, le plus finement que le maximum permet : elle se lit
	 * comme les semaines du calendrier, sans devenir une hachure (le
	 * 2026-09-21). Le minimum n'est pas une règle appliquée mais ce que cette division
	 * garantit, et que les tests vérifient.
	 */
	const CONDUCT_GRID_MIN = 15;
	const CONDUCT_GRID_MAX = 30;

	/**
	 * Un créneau doit réunir au moins ce nombre de fermes, et peser au moins ce
	 * pourcentage des fermes de l'échantillon. En deçà de l'un ou de l'autre il ne
	 * décrit plus une pratique partagée : dix fermes ne pèsent rien face à mille.
	 */
	const SLOT_STRUCTURES_MIN = 10;
	const SLOT_STRUCTURES_SHARE_MIN = 3;

	/**
	 * Nombre de variétés listées par créneau, et nombre de fermes minimum pour qu'une
	 * variété y figure. Une variété employée par une seule ferme n'est pas un choix
	 * partagé mais un essai, et la page sert à décider d'un choix variétal.
	 */
	const SLOT_VARIETIES = 6;
	const SLOT_VARIETY_STRUCTURES_MIN = 2;

	/**
	 * Nombre de structures sous lequel la cohorte d'une semaine d'implantation ne
	 * s'affiche pas au survol du calendrier. Une cohorte dit quand récoltent les
	 * cultures implantées une semaine donnée : celle d'une ou deux structures serait
	 * leur calendrier de récolte, lisible semaine par semaine. Même seuil que pour
	 * une conduite, qui décrit elle aussi une pratique précise.
	 */
	const COHORT_STRUCTURES_MIN = 5;

	/**
	 * Part écartée en haut de chaque créneau, en pourcents, sur la pépinière et sur la
	 * récolte. Une poignée de fermes très en retard étire le relief sur la moitié de
	 * l'année et fait croire à une pratique étalée là où elle est groupée.
	 *
	 * En haut seulement : le bas est tenu par le plancher d'espèce, qui vaut mieux ici
	 * — un créneau de trente cultures ne sait pas situer son propre centile bas, et
	 * c'est précisément là que les valeurs trop courtes pèsent le plus. Le haut, lui,
	 * doit rester dans le créneau : une mâche de janvier passe légitimement des
	 * semaines en pépinière, et une borne calculée sur l'année entière l'écarterait.
	 *
	 * L'implantation n'est pas élaguée : elle définit le créneau.
	 */
	const CEILING_SHARE = 5;

	/**
	 * Bornes de plausibilité de l'écart entre le semis en pépinière et la plantation,
	 * en semaines. Aucun légume repiqué ne va du semis à la mise en place en une
	 * semaine ou moins ; au-delà d'un semestre, ce n'est plus la même culture. Entre
	 * les deux, une saisie contradictoire tirait les médianes sans qu'on la voie.
	 */
	const SEEDLING_GAP_MIN = 2;
	const SEEDLING_GAP_MAX = 26;

	/**
	 * Centile sous lequel un délai est tenu pour invraisemblable, calculé sur toute
	 * l'espèce puis appliqué à chaque créneau.
	 *
	 * Une borne saisie à la main demanderait 71 espèces × 3 délais, et serait fausse
	 * dès qu'on regarde un mois plutôt qu'un autre. La distribution de l'espèce la
	 * donne toute seule : 2 semaines de pépinière pour une laitue, 5 pour un poivron.
	 *
	 * Calculée sur l'espèce entière et non sur le créneau, parce qu'un créneau de
	 * trente cultures ne suffit pas à situer un centile. Appliquée en plancher
	 * seulement : le haut de la distribution est saisonnier — une mâche de janvier
	 * passe légitimement des semaines en pépinière — et se traite par l'élagage à
	 * l'intérieur du créneau, où janvier n'est comparé qu'à janvier.
	 */
	const GAP_FLOOR_SHARE = 3;

	/**
	 * Noyau de lissage des courbes hebdomadaires, semaine par semaine autour de celle
	 * qu'on calcule. La semaine elle-même pèse le plus, ses voisines un peu moins —
	 * une moyenne plate donnerait un plateau là où il y a un pic.
	 *
	 * Deux réglages, indépendants l'un de l'autre. La **largeur** : sur trois semaines
	 * le noyau se contente d'adoucir, sur cinq il commence à déplacer les pics des
	 * créneaux, qui ne tiennent que sur quatre ou cinq semaines. Le **contraste** entre
	 * le centre et les bords : plus les voisines s'approchent du poids central, plus la
	 * courbe s'aplatit. Ici 0,75 contre 2, soit un lissage léger qui garde le relief —
	 * chaque voisine ne pèse qu'un cinquième, contre un quart pour un triangle strict.
	 *
	 * Le code s'adapte à la taille du tableau, les valeurs peuvent être décimales.
	 */
	const SMOOTHING_KERNEL = [0.75, 2, 0.75];

	/**
	 * Écarter des intervalles de récolte les cultures dont la récolte est déclarée au
	 * mois (`series\Cultivation.harvestPeriodExpected = MONTH`).
	 *
	 * Ces cultures couvrent leurs mois d'un bloc, du premier au dernier jour : leurs
	 * bornes ne datent rien à la semaine près, et les mêler aux cultures datées à la
	 * semaine brouille les médianes et fait des marches dans le relief. Elles pèsent
	 * de 27 % (laitue) à 55 % (poireau) des cultures selon l'espèce, l'échantillon
	 * précis restant partout suffisant.
	 *
	 * Actif, elles ne servent qu'au comptage — leur volume est réparti sur leurs
	 * propres semaines au prorata du profil des cultures précises, si bien que le
	 * relief garde la forme des unes et l'effectif de toutes. Inactif, elles comptent
	 * comme les autres, bornes comprises.
	 */
	const HARVEST_WEEKLY_ONLY = TRUE;

	/**
	 * Seuil sous lequel les semaines de début et de fin d'une étape sont rognées, en
	 * pourcents de la semaine la plus fournie de cette étape.
	 *
	 * Une poignée de fermes très en avance ou très en retard prolonge la récolte d'un
	 * créneau de plusieurs semaines par une traîne de un ou deux, qui se dessine comme
	 * le reste et laisse croire à une période bien plus longue qu'elle n'est. Seules
	 * les extrémités sont rognées : un creux au milieu de la période est un fait, pas
	 * un artefact, et il reste.
	 *
	 * Ne s'applique pas à l'implantation, qui définit le créneau et tient déjà dans
	 * les semaines de son mois.
	 */
	const RELIEF_TAIL_MIN_SHARE = 5;

	/**
	 * Effectif minimum, en cultures, pour qu'une semaine de début ou de fin d'étape
	 * soit conservée. S'ajoute au seuil en pourcents, le plus exigeant des deux
	 * l'emportant.
	 *
	 * La part du pic ne suffit pas sur un petit créneau : à 5 % d'un pic de vingt,
	 * une seule culture tient la période ouverte pendant des semaines. Un plancher en
	 * valeur absolue ferme cette porte sans rien changer aux créneaux fournis, où
	 * 5 % pèse déjà bien plus de trois cultures.
	 */
	const RELIEF_TAIL_MIN_COUNT = 3;

}

?>
