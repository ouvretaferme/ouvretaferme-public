<?php
namespace data;

class PdpHistorySetting extends \Settings {

	const PDP_HISTORY_NOT_REDOABLE_ACTIONS = [
		PdpHistory::INVOICES,
		PdpHistory::INVOICE_DOWNLOAD,
		PdpHistory::COMPANY,
		PdpHistory::COMPANY_RETRIEVE,
		PdpHistory::SESSION,
	];

	const RETENTION_EMPTY = 'empty';
	const RETENTION_CONTENT = 'content';
	const RETENTION_ERROR = 'error';

	// Durée de conservation en jours, par action et par nature du retour de l'API.
	// RETENTION_EMPTY n'est déclaré que pour les actions qui peuvent réussir sans rien ramener,
	// et exige alors une condition dans PdpHistoryLib::getEmptyCondition().
	const PDP_HISTORY_RETENTION = [
		PdpHistory::INVOICE => [self::RETENTION_CONTENT => 365, self::RETENTION_ERROR => 60],
		PdpHistory::INVOICES => [self::RETENTION_EMPTY => 7, self::RETENTION_CONTENT => 180, self::RETENTION_ERROR => 180],
		PdpHistory::INVOICE_DETAILS => [self::RETENTION_CONTENT => 90, self::RETENTION_ERROR => 90],
		PdpHistory::INVOICE_SEND => [self::RETENTION_CONTENT => 730, self::RETENTION_ERROR => 730],
		PdpHistory::INVOICE_DOWNLOAD => [self::RETENTION_CONTENT => 90, self::RETENTION_ERROR => 90],
		PdpHistory::EVENT => [self::RETENTION_EMPTY => 7, self::RETENTION_CONTENT => 180, self::RETENTION_ERROR => 180],
		PdpHistory::EVENT_SEND => [self::RETENTION_CONTENT => 730, self::RETENTION_ERROR => 730],
		PdpHistory::ADDRESS => [self::RETENTION_EMPTY => 7, self::RETENTION_CONTENT => 30, self::RETENTION_ERROR => 180],
		PdpHistory::ADDRESS_CREATE => [self::RETENTION_CONTENT => 730, self::RETENTION_ERROR => 730],
		PdpHistory::ADDRESS_DELETE => [self::RETENTION_CONTENT => 730, self::RETENTION_ERROR => 730],
		PdpHistory::COMPANY_RETRIEVE => [self::RETENTION_CONTENT => 90, self::RETENTION_ERROR => 180],
		PdpHistory::COMPANY => [self::RETENTION_CONTENT => 90, self::RETENTION_ERROR => 180],
		PdpHistory::SESSION => [self::RETENTION_CONTENT => 90, self::RETENTION_ERROR => 180],
		PdpHistory::TOKEN_REFRESH => [self::RETENTION_EMPTY => 7, self::RETENTION_CONTENT => 365, self::RETENTION_ERROR => 365],
		PdpHistory::VALIDATION_REPORT => [self::RETENTION_CONTENT => 365, self::RETENTION_ERROR => 365],
	];

	// Une ligne rejouée depuis l'admin a fait la preuve de son utilité
	const PDP_HISTORY_RETENTION_RELAUNCHED = 730;

	const PDP_HISTORY_CLEAN_BATCH = 5000;

}
