<?php
namespace plant;

class PlantSetting extends \Settings {

	const VARIETY_UNKNOWN = 2;
	const VARIETY_MIX = 1;

	/**
	 * Nombre de variétés de référence proposées par l'autocomplétion, et nombre de
	 * graphies à lire par variété espérée — la recherche porte sur les graphies, dont
	 * une variété peut en aligner une dizaine.
	 */
	const VARIETY_QUERY = 100;
	const VARIETY_QUERY_GRAPHIES = 12;

	/**
	 * Nombre de saisons prises en compte dans la synthèse d'usage affichée à la
	 * notation : au-delà, les pratiques ont changé et la donnée éclaire mal.
	 */
	const RATING_SEASONS = 5;

	/**
	 * Nombre de variétés montrées d'emblée pour une espèce, les plus employées
	 * d'abord. Les suivantes restent à un clic : la médiane est de quatre variétés
	 * par espèce, et seule une poignée d'espèces dépasse ce seuil.
	 */
	const RATING_VARIETIES = 10;

	/**
	 * Les espèces plantées à partir d'un bulbe ou d'un tubercule. On ne leur demande
	 * pas le poids de mille graines, qui n'a pas de sens pour elles : le produit leur
	 * réserve déjà « Nombre de plants pour 1 kg » (Variety.numberPlantKilogram).
	 */
	const RATING_NO_SEED_WEIGHT = ['ail', 'pomme-de-terre'];

	/**
	 * Nombre maximum de critères qu'une structure peut retenir pour une espèce :
	 * au-delà, noter chaque variété devient un questionnaire et plus personne ne
	 * le remplit.
	 */
	const PLANT_CRITERIA_MAX = 5;

	/**
	 * Les critères que la communauté retient le plus souvent pour une espèce, portés
	 * par l'espèce de référence (structure NULL) et écrits par `plant/configure/plantCriteriaAi`.
	 *
	 * Ils sont rangés là par popularité décroissante, et non recalculés à l'affichage :
	 * l'espèce mère est le seul endroit que toutes les structures partagent, et un
	 * classement figé donne la même carte à deux personnes qui notent le même jour.
	 *
	 * L'effectif minimal est une garde ajoutée au cadrage : sans elle, une espèce que
	 * deux structures cultivent verrait leurs choix présentés comme « les critères
	 * préférés des producteurs et productrices ».
	 */
	const PLANT_CRITERIA_REFERENCE = 5;
	const PLANT_CRITERIA_REFERENCE_STRUCTURES_MIN = 5;

	/**
	 * Le nombre de critères notés sur une carte de variété : les siens, complétés par
	 * les plus populaires de l'espèce mère.
	 *
	 * Un de plus que ce qu'une structure peut retenir pour elle-même. Celle qui a
	 * rempli ses cinq critères en note donc toujours un sixième, venu de la
	 * communauté : c'est le socle sans lequel deux structures n'ont aucun axe commun,
	 * et la page de résultats rien à moyenner.
	 */
	const PLANT_CRITERIA_RATING_MAX = 6;

	/**
	 * Le mot du vocabulaire qui dit qu'une variété est hybride.
	 *
	 * Il se précoche d'après le nom, qui est la seule source : rien d'autre dans le
	 * référentiel ne porte l'information.
	 */
	const TAG_F1 = 'f1';

	/**
	 * La date à laquelle les résultats de la campagne d'évaluation s'ouvrent.
	 *
	 * Seul temps de la campagne qui attende une date : choisir ses critères et noter
	 * ses variétés sont ouverts en permanence, les résultats non, faute d'être
	 * calculés avant.
	 *
	 * En jour/mois seulement, l'année étant celle en cours : la campagne suit la
	 * saison culturale et se rejoue chaque année.
	 */
	const CAMPAIGN_RESULTS_FROM = '11-05';

	/**
	 * Le nombre de votes que vise la campagne, toutes structures réunies : c'est l'échelle
	 * de la barre du bandeau des variétés préférées.
	 */
	const CAMPAIGN_VOTES_GOAL = 2000;

	/**
	 * L'espèce sur laquelle la bibliothèque de cultures est ouverte en avant-première.
	 *
	 * La page traitera toutes les espèces à l'ouverture de la campagne ; d'ici là
	 * elle n'en sert qu'une, choisie pour la taille de son échantillon.
	 */
	const COMMUNITY_PLANT_PREVIEW = 'carotte';

	/**
	 * Les comptes qui disposent du sélecteur d'espèce avant l'ouverture, en plus du
	 * développement où il est servi à tout le monde.
	 *
	 * Des comptes et non des structures : ces trois-là suivent chacun plusieurs fermes
	 * — dix pour l'une, huit pour l'autre — et c'est à la personne que l'avant-première
	 * est ouverte, pas à une ferme en particulier. Les deux verrous de la page,
	 * l'adhésion et le consentement, restent posés : cette liste ouvre le choix de
	 * l'espèce, elle n'ouvre pas la bibliothèque.
	 *
	 * 13817 et 21950 sont les deux comptes d'une même personne.
	 *
	 * Liste temporaire : elle n'a plus d'objet à l'ouverture de la campagne, la page
	 * traitant alors toutes les espèces pour tout le monde.
	 */
	const COMMUNITY_PLANT_PREVIEW_USERS = [1, 196, 13817, 21950];

	/**
	 * Les deux vues du bloc des créneaux, et celle qui est servie par défaut.
	 *
	 * La vue simplifiée répond à la question qu'on se pose en posant un plan de
	 * culture — quand est-ce que ça se passe, et à quel point les fermes s'accordent
	 * — avec un point et un intervalle par étape. La vue experte montre le relief
	 * hebdomadaire complet, qui dit la même chose en plus fin mais demande à être lu.
	 */
	const COMMUNITY_VIEW_SIMPLE = 'simple';
	const COMMUNITY_VIEW_EXPERT = 'expert';
	const COMMUNITY_VIEW_DEFAULT = self::COMMUNITY_VIEW_SIMPLE;

	/**
	 * Hauteur minimale du pic d'une étape dans le relief d'un créneau, en pourcents
	 * du pic de l'étape la plus haute.
	 *
	 * Les étapes sont mises à l'échelle les unes des autres pour que leurs surfaces
	 * restent comparables, mais une étape très étalée y devient un liseré illisible :
	 * la pépinière d'une aubergine de juin, répartie sur dix-sept semaines, tombait à
	 * un quart de la bande. Le plancher lui rend une hauteur lisible.
	 *
	 * C'est un compromis assumé : au-delà de ce seuil les surfaces ne sont plus
	 * strictement proportionnelles aux effectifs. À 100 % chaque étape retrouve sa
	 * propre échelle et plus aucune comparaison n'est possible ; à 0 % les surfaces
	 * sont exactes et les étapes étalées deviennent invisibles.
	 */
	const COMMUNITY_RELIEF_PEAK_MIN_SHARE = 33;

}

?>
