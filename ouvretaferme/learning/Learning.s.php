<?php
namespace learning;

class LearningSetting extends \Settings {

	/**
	 * Les formations ouvertes à tous, formations de l'équipe, catalogue public et administration
	 * compris. Ouvert en dev, fermé en production. Une propriété et non une constante : un test pose
	 * `FALSE` pour jouer la production en dev, où tout est ouvert, et remet la valeur en fin de méthode.
	 */
	public static bool $active = (LIME_ENV === 'dev');

	/**
	 * Les structures dont les formations s'ouvrent quand `$active` est faux, lues par
	 * `Structure::acceptLearning()`. Tout ce qui passe par elles s'ouvre avec : leurs formations,
	 * leurs dates, leurs pages publiques, et ce que leurs fermes membres en lisent.
	 * En dev, le réseau des tests (`LearningNetworkFixtureTest::COMMUNITY_NETWORK`), que le test de la
	 * production simulée oppose au groupe de formation absent de la liste, et le réseau des tutoriels
	 * du décor (`LearningTutorialFixtureTest::COMMUNITY_TUTORIAL`), qui tient le rôle de `$structureTutorial`.
	 * En production, le réseau 6659 (demande du 2026-09-29) et le réseau des tutoriels
	 * (`$structureTutorial`).
	 *
	 * Retirer une structure de la liste lui coupe l'accès sans rien effacer : ses formations, ses
	 * dates et ses inscriptions restent en base, ses pages répondent comme une adresse inconnue, ses
	 * inscrits ne lisent plus ses formations, et les crons cessent de leur écrire. La remettre rend
	 * tout tel quel.
	 */
	const STRUCTURE_BETA = (LIME_ENV === 'dev') ? [\structure\FixtureAiLib::G3, \structure\FixtureAiLib::G7] : [6659, 6987];

	/**
	 * Le réseau qui publie les tutoriels de Ouvretaferme, que tout visiteur lit sans compte
	 * (`Course::acceptTutorial()`), et que l'équipe écrit par l'API. Créé en production le 2026-10-16
	 * par le script de `migration/2026-10-15-e-learning-tutoriels-t0.md`, et posé aussi dans la liste de production de
	 * `STRUCTURE_BETA`. Le même identifiant vaut en dev, sans branche : la base de dev est une copie
	 * de la production, qui retrouve ce réseau à chaque rechargement, alors qu'un réseau créé en dev
	 * perd son numéro au rechargement suivant au profit d'une structure de production.
	 * `STRUCTURE_BETA` suffit à lui ouvrir l'écriture des formations par l'API
	 * (`Structure::acceptApiScope()`) : le poser aussi dans `StructureSetting::API_STRUCTURE_BETA`
	 * lui ouvrirait l'écriture de tous les autres domaines. NULL dit qu'aucun réseau ne publie de
	 * tutoriels : `acceptTutorial()` ne reconnaît alors aucune formation.
	 * Une propriété et non une constante, comme `$active` : un test y pose un réseau du décor le
	 * temps d'une méthode.
	 */
	public static ?int $structureTutorial = 6987;

	/**
	 * Le nombre d'avis affichés à partir duquel la note moyenne paraît, sur la carte comme sur la
	 * fiche (lot L15c, Q15 sous mandat) : un ou deux avis ne font pas une moyenne.
	 */
	const REVIEWS_THRESHOLD = 3;

	/**
	 * Les gabarits qu'un réseau peut choisir pour ses pages de formations, chacun nommé comme sa
	 * feuille `static/css/design-<nom>.css`. Le gabarit classique n'a pas de feuille et vaut NULL
	 * en base. `DesignSentinel` tient la liste, les feuilles et les libellés du describer ensemble.
	 */
	const DESIGNS = ['ami', 'bucolique', 'bulles', 'classeur', 'craie', 'diplome', 'futur', 'kraft', 'parcours', 'profil'];

	/**
	 * Par gabarit et par portée (`public` pour la page du réseau, ses étapes et ses leçons, `doc` pour
	 * la fiche), les couleurs que la feuille fixe sous le texte : son fond `--learning-background`
	 * et ses surfaces `--background-surface`. `{background}` nomme le fond choisi par le réseau tel
	 * qu'il se pose sur la page, adouci (`CommunityLib::getBackgroundSoftened()`). Les encres se
	 * calculent sur toutes les couleurs de la liste à la fois (`CommunityLib::getSettings()`), et un
	 * gabarit absent de la liste calcule sur ce fond adouci et le blanc des cartes. Un gabarit n'a pas
	 * de fond à lui (`DesignSentinel::testBackgroundsDerived()`) : le blanc est la seule couleur qu'il
	 * puisse fixer. `DesignSentinel` compare la liste aux déclarations de la feuille.
	 */
	const DESIGN_MATTERS = [
		'parcours' => [
			'doc' => ['#ffffff'],
		],
	];

	/**
	 * Les polices d'un gabarit, celle des titres et celle du texte, quand le réseau n'en a pas choisi :
	 * un choix fait dans la barre l'emporte sur elles (`CommunityLib::getSettings()`). Chaque valeur est
	 * celle d'une entrée des listes de `\main\CustomizeUi`, sur laquelle `getFont()` et `getTitleFont()`
	 * la retrouvent : une valeur qui n'y est pas ne serait jamais posée.
	 */
	const DESIGN_FONTS = [
		'classeur' => ['title' => "'Acme', sans-serif", 'text' => "'Source Sans Pro', sans-serif"],
		'craie' => ['title' => "'Cabin Sketch', cursive", 'text' => "'Kodchasan', sans-serif"],
		'diplome' => ['title' => "'Kurale', serif", 'text' => "'Lora', serif"],
		'parcours' => ['title' => "'Comfortaa', cursive", 'text' => "'Figtree', serif"],
	];

	/**
	 * Ce qui tourne sans structure sous la main (crons, observateur de connexion, « Mes
	 * formations », menus) tourne dès qu'une structure au moins est ouverte : chacun borne ensuite
	 * ce qu'il lit aux formations ouvertes.
	 */
	public static function isActiveAnywhere(): bool {

		return (
			self::$active or
			self::STRUCTURE_BETA !== []
		);

	}

}
?>
