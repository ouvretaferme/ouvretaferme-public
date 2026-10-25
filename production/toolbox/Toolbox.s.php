<?php
namespace toolbox;

class ToolboxSetting extends \Settings {

	/**
	 * Les produits autorisés par page (retour du 29/09, « ajoute une pagination à 100 ») :
	 * sans critère, la liste porte tout le catalogue, plus de mille produits.
	 */
	const CATALOG_PER_PAGE = 100;

	/**
	 * Les lignes de l'historique d'un matériel par page (retour du 01/10, « pagination à 20
	 * entrées sur la table d'historique des interventions ») : un relevé par jour l'allonge vite.
	 */
	const MAINTENANCE_HISTORY_PER_PAGE = 20;

	/**
	 * Le poids courant d'une unité du panier, en kilos, par espèce du référentiel partagé
	 * (`plant\Plant.fqn`) puis par unité (`product\Unit.fqn`) : le dernier recours du poids d'une
	 * pièce ou d'une botte (`CompositionLib::getWeightReference()`), quand ni la structure ni ses
	 * cultures n'en disent un. Les chiffres sont posés par Ouvretaferme pour ne pas demander ce poids
	 * à chacun (arbitrage L3 du 24/09, validés le même jour, tels que proposés).
	 *
	 * Ils partent des poids que les cultures de la communauté portent (relevé du 24/09, 15 structures,
	 * la médiane arrondie quand plusieurs structures pèsent la même espèce), et d'une valeur courante
	 * pour les espèces que la communauté sert à la botte ou à la pièce sans jamais les peser (herbes,
	 * choux, courge, radis rave). La tomate à la pièce n'y est pas : son poids varie trop d'une
	 * variété à l'autre pour qu'un chiffre commun veuille dire quelque chose.
	 */
	const COMPOSITION_WEIGHTS = [
		'ail' => ['bunch' => 0.15, 'one' => 0.05],
		'basilic' => ['bunch' => 0.1],
		'betterave' => ['bunch' => 0.5],
		'blette' => ['bunch' => 0.4],
		'carotte' => ['bunch' => 0.5],
		'chou-blanc' => ['one' => 1.5],
		'chou-pak-choi' => ['one' => 0.3],
		'chou-pe-tsai' => ['one' => 1.0],
		'chou-rave' => ['one' => 0.4],
		'ciboule-de-chine' => ['bunch' => 0.1],
		'ciboulette' => ['bunch' => 0.1],
		'concombre' => ['one' => 0.4],
		'coriandre' => ['bunch' => 0.1],
		'courge' => ['one' => 1.5],
		'fenouil' => ['one' => 0.25],
		'laitue' => ['one' => 0.3],
		'melon' => ['one' => 1.5],
		'navet' => ['bunch' => 0.5],
		'oignon' => ['bunch' => 0.3],
		'persil' => ['bunch' => 0.1],
		'poireau' => ['bunch' => 0.5],
		'radis-botte' => ['bunch' => 0.3],
		'radis-rave' => ['one' => 0.4],
	];

}
?>
