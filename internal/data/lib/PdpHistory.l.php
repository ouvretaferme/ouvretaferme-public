<?php
namespace data;

Class PdpHistoryLib extends PdpHistoryCrud {

	/**
	 * Purge l'historique, avec une durée de conservation par action et par nature du retour
	 * de l'API : un appel qui n'a rien ramené est jeté bien plus vite qu'un appel en erreur.
	 */
	public static function clean(): void {

		foreach(PdpHistorySetting::PDP_HISTORY_RETENTION as $action => $retentions) {

			$empty = array_key_exists(PdpHistorySetting::RETENTION_EMPTY, $retentions) ? self::getEmptyCondition($action) : NULL;

			foreach($retentions as $case => $days) {

				$condition = match($case) {
					PdpHistorySetting::RETENTION_ERROR => self::getErrorCondition().' IS TRUE',
					PdpHistorySetting::RETENTION_EMPTY => self::getErrorCondition().' IS NOT TRUE AND '.$empty.' IS TRUE',
					PdpHistorySetting::RETENTION_CONTENT => self::getErrorCondition().' IS NOT TRUE'.($empty === NULL ? '' : ' AND '.$empty.' IS NOT TRUE'),
				};

				self::deleteOlderThan($action, $days, $condition);

			}

		}

	}

	/**
	 * L'API ne renvoie ces clés que lorsque l'appel a échoué : http_status_code pour un code HTTP
	 * en erreur, success pour un renouvellement de jeton refusé (qui répond 200).
	 */
	public static function getErrorCondition(): string {
		return "(JSON_EXTRACT(result, '$.http_status_code') IS NOT NULL OR JSON_EXTRACT(result, '$.success') = FALSE)";
	}

	private static function getEmptyCondition(string $action): string {

		return match($action) {

			PdpHistory::INVOICES,
			PdpHistory::EVENT,
			PdpHistory::ADDRESS => "(JSON_LENGTH(JSON_EXTRACT(result, '$.data')) = 0)",

			// Un renouvellement de jeton réussi ne rapporte que sa propre durée de validité
			PdpHistory::TOKEN_REFRESH => "(JSON_EXTRACT(result, '$.success') = TRUE)",

		};

	}

	private static function deleteOlderThan(string $action, int $days, string $condition): void {

		do {

			$deleted = PdpHistory::model()
				->whereAction($action)
				->where('date < NOW() - INTERVAL '.$days.' DAY')
				->where('relaunchedDate IS NULL OR relaunchedDate < NOW() - INTERVAL '.PdpHistorySetting::PDP_HISTORY_RETENTION_RELAUNCHED.' DAY')
				->where($condition)
				->limit(PdpHistorySetting::PDP_HISTORY_CLEAN_BATCH)
				->delete();

		} while($deleted === PdpHistorySetting::PDP_HISTORY_CLEAN_BATCH);

	}

}
