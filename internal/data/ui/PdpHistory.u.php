<?php
namespace data;

Class PdpHistoryUi {

	public static function p(string $property): \PropertyDescriber {

		$d = PdpHistory::model()->describer($property, [
			'structure' => s("Structure"),
			'action' => s("Élément"),
			'arg' => s("Arguments"),
			'request' => s("URL"),
			'doneBy' => s("Fait par"),
			'date' => s("Fait le"),
			'relaunchedDate' => s("Relancé le"),
			'relaunchedBy' => s("Relancé par"),
		]);

		switch($property) {

			case 'action' :
				$d->values = [
					PdpHistory::INVOICE => s("Facture - synchronisation"),
					PdpHistory::INVOICES => s("Factures - synchronisation"),
					PdpHistory::INVOICE_DETAILS => s("Facture - détaillée"),
					PdpHistory::INVOICE_SEND => s("Facture - envoi"),
					PdpHistory::INVOICE_DOWNLOAD => s("Facture - téléchargement"),
					PdpHistory::EVENT => s("Statut - synchronisation"),
					PdpHistory::EVENT_SEND => s("Statut - envoi"),
					PdpHistory::ADDRESS => s("Adresses - synchronisation"),
					PdpHistory::ADDRESS_CREATE => s("Adresse - création"),
					PdpHistory::ADDRESS_DELETE => s("Adresse - suppression"),
					PdpHistory::COMPANY => s("Entreprise - informations"),
					PdpHistory::COMPANY_RETRIEVE => s("Entreprise - connexion"),
					PdpHistory::SESSION => s("Entreprise - vérification"),
					PdpHistory::TOKEN_REFRESH => s("Entreprise - renouvellement du jeton"),
					PdpHistory::VALIDATION_REPORT => s("Facture - test de validité"),
				];
				break;

			case 'duration' :
				$d->append = s("jours");
				break;

			case 'user' :
				$d->values = fn(PdpHistory $e) => $e['cUser'] ?? $e->expects(['cUser']);
				break;


		}

		return $d;

	}

	public static function getRetentionCase(string $case): string {

		return match($case) {
			PdpHistorySetting::RETENTION_EMPTY => s("Sans contenu"),
			PdpHistorySetting::RETENTION_CONTENT => s("Avec contenu"),
			PdpHistorySetting::RETENTION_ERROR => s("En erreur"),
		};

	}

	public static function getRetentionDuration(int $days): string {

		if($days % 365 === 0) {
			return p("{value} an", "{value} ans", $days / 365);
		} else if($days % 30 === 0) {
			return s("{value} mois", $days / 30);
		} else {
			return p("{value} jour", "{value} jours", $days);
		}

	}
}
