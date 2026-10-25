<?php
namespace api;

class OauthUi {

	/**
	 * Ce qu'un scope ouvre à l'application, en conséquence de `\main\DesignUi::getWeigh()` : l'icône
	 * est celle du domaine sur l'écran des clés d'API, pour que la personne reconnaisse les mêmes accès.
	 */
	public function getConsequence(string $name, string $client): array {

		$client = '<b>'.encode($client).'</b>';

		return match($name) {
			'general:read' => ['icon' => 'gear-fill', 'text' => s("L'application {client} pourra lire les informations générales de la structure et de son équipe.", ['client' => $client])],
			'general:write' => ['icon' => 'gear-fill', 'text' => s("L'application {client} pourra lire et modifier les informations générales de la structure et de son équipe.", ['client' => $client])],
			'production:read' => ['icon' => 'leaf', 'text' => s("L'application {client} pourra lire le planning de culture, les séries, les interventions et les récoltes.", ['client' => $client])],
			'production:write' => ['icon' => 'leaf', 'text' => s("L'application {client} pourra lire et modifier le planning de culture, les séries, les interventions et les récoltes.", ['client' => $client])],
			'commercialisation:read' => ['icon' => 'basket', 'text' => s("L'application {client} pourra lire les clients, les ventes, les produits et les boutiques.", ['client' => $client])],
			'commercialisation:write' => ['icon' => 'basket', 'text' => s("L'application {client} pourra lire et modifier les clients, les ventes, les produits et les boutiques.", ['client' => $client])],
			'accounting:read' => ['icon' => 'piggy-bank', 'text' => s("L'application {client} pourra lire les écritures, les factures, les immobilisations et la TVA.", ['client' => $client])],
			'accounting:write' => ['icon' => 'piggy-bank', 'text' => s("L'application {client} pourra lire et modifier les écritures, les factures, les immobilisations et la TVA.", ['client' => $client])],
			'learning:read' => ['icon' => 'book', 'text' => s("L'application {client} pourra lire les formations, leurs chapitres, leurs leçons, leurs dates et les inscriptions.", ['client' => $client])],
			'learning:write' => ['icon' => 'book', 'text' => s("L'application {client} pourra lire et modifier les formations, leurs chapitres, leurs leçons, leurs dates et les inscriptions.", ['client' => $client])],
		};

	}

}
