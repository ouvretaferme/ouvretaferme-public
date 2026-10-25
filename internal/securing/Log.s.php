<?php
namespace securing;

class LogSetting extends \Settings {

	const LOG_PER_PAGE = 50;

	const ADMIN_LOG_PER_PAGE = 100;


	const EVENT_ACCOUNT_CREATE = 'account-create';
	const EVENT_ACCOUNT_UPDATE = 'account-update';
	const EVENT_ACCOUNT_STATUS = 'account-status';
	const EVENT_ACCOUNT_JOURNAL_CODE = 'account-journal-code';
	const EVENT_ACCOUNT_DELETE = 'account-delete';
	const EVENT_ACCOUNT_ANCILLARY_MOVE = 'account-ancillary-move';
	const EVENT_ACCOUNT_ANCILLARY_CANCEL = 'account-ancillary-cancel';

	const EVENT_THIRD_PARTY_CREATE = 'third-party-create';
	const EVENT_THIRD_PARTY_UPDATE = 'third-party-update';
	const EVENT_THIRD_PARTY_DELETE = 'third-party-delete';
	const EVENT_THIRD_PARTY_ADDRESS_ADD = 'third-party-address-add';
	const EVENT_THIRD_PARTY_ADDRESS_DELETE = 'third-party-address-delete';

	const EVENT_ACCOUNTING_RESET = 'accounting-reset';

	const EVENT_FINANCIAL_YEAR_IMPORT = 'financial-year-import';
	const EVENT_FINANCIAL_YEAR_IMPORT_CREATE = 'financial-year-import-create';
	const EVENT_FINANCIAL_YEAR_IMPORT_CANCEL = 'financial-year-import-cancel';
	const EVENT_FINANCIAL_YEAR_IMPORT_RULES = 'financial-year-import-rules';
	const EVENT_FINANCIAL_YEAR_IMPORT_WARNING = 'financial-year-import-warning';
	const EVENT_FINANCIAL_YEAR_CLOSE = 'financial-year-close';
	const EVENT_FINANCIAL_YEAR_RECLOSE = 'financial-year-reclose';
	const EVENT_FINANCIAL_YEAR_OPEN = 'financial-year-open';
	const EVENT_FINANCIAL_YEAR_REOPEN = 'financial-year-reopen';
	const EVENT_FINANCIAL_YEAR_CREATE = 'financial-year-create';
	const EVENT_FINANCIAL_YEAR_DELETE = 'financial-year-delete';
	const EVENT_FINANCIAL_YEAR_UPDATE = 'financial-year-update';
	const EVENT_FINANCIAL_YEAR_FEC_GENERATION = 'financial-year-fec-generation';
	const EVENT_FINANCIAL_YEAR_LOCK = 'financial-year-lock';
	const EVENT_FINANCIAL_YEAR_RECONCILE = 'financial-year-reconcile';
	const EVENT_FINANCIAL_YEAR_RECONCILE_ENTRY = 'financial-year-reconcile-entry';
	const EVENT_FINANCIAL_YEAR_FILE_STORE = 'financial-year-file-store';
	const EVENT_FINANCIAL_YEAR_FILE_DELETE = 'financial-year-file-delete';
	const EVENT_FINANCIAL_YEAR_ALLOCATION = 'financial-year-allocation';
	const EVENT_FINANCIAL_YEAR_INVENTORY = 'financial-year-inventory';
	const EVENT_FINANCIAL_YEAR_INVENTORY_DELETE = 'financial-year-inventory-delete';
	const EVENT_FINANCIAL_YEAR_INCOME = 'financial-year-income';
	const EVENT_BANK_ACCOUNT_UPDATE = 'bank-account-update';
	const EVENT_BANK_ACCOUNT_DELETE = 'bank-account-delete';
	const EVENT_BANK_ACCOUNT_IMPORT = 'bank-account-import';
	const EVENT_CASHFLOW_ATTACH = 'cashflow-attach';
	const EVENT_CASHFLOW_UNLINK = 'cashflow-unlink';
	const EVENT_CASHFLOW_IMPORT_CASH = 'cashflow-import-cash';
	const EVENT_CASHFLOW_DELETE = 'cashflow-delete';
	const EVENT_CASHFLOW_UNDELETE = 'cashflow-undelete';
	const EVENT_CASHFLOW_WALLET = 'cashflow-wallet';
	const EVENT_CASHFLOW_ANNOTATION_UPDATE = 'cashflow-annotation-update';
	const EVENT_CASHFLOW_ANNOTATION_DELETE = 'cashflow-annotation-delete';
	const EVENT_PROOF_CREATE = 'proof-create';
	const EVENT_PROOF_ATTACH = 'proof-attach';
	const EVENT_PROOF_DETACH = 'proof-detach';
	const EVENT_PROOF_DISCARD = 'proof-discard';
	const EVENT_PROOF_UNDISCARD = 'proof-undiscard';
	const EVENT_PROOF_TRASH = 'proof-trash';
	const EVENT_PROOF_UNTRASH = 'proof-untrash';
	const EVENT_PROOF_DELETE = 'proof-delete';
	const EVENT_PROOF_PURGE = 'proof-purge';
	const EVENT_PROOF_ERASE = 'proof-erase';
	const EVENT_INVOICING_SEND = 'invoicing-sent';
	const EVENT_INVOICING_EVENT_SENT = 'invoicing-event-sent';
	const EVENT_INVOICING_EVENT_WAITING = 'invoicing-event-waiting';
	const EVENT_INVOICING_ACCOUNT = 'invoicing-account';
	const EVENT_INVOICING_PAYMENT = 'invoicing-payment';
	const EVENT_INVOICING_COMMENT = 'invoicing-comment';
	const EVENT_DEFERRAL_CREATE = 'deferral-create';
	const EVENT_DEFERRAL_DELETE = 'deferral-delete';
	const EVENT_PREACCOUNTING_IMPORT = 'preaccounting-import';
	const EVENT_PREACCOUNTING_IGNORE = 'preaccounting-ignore';
	const EVENT_PREACCOUNTING_IGNORE_ALL = 'preaccounting-ignore-all';
	const EVENT_PREACCOUNTING_SUGGESTION_IGNORE = 'preaccounting-suggestion-ignore';
	const EVENT_PREACCOUNTING_SUGGESTION_METHOD = 'preaccounting-suggestion-method';
	const EVENT_PDP_GET_ACCESS_TOKEN = 'pdp-get-access-token';
	const EVENT_PDP_GET_REFRESH_TOKEN = 'pdp-get-refresh-token';
	const EVENT_PDP_PARTNER_OPT_IN = 'pdp-partner-opt-in';
	const EVENT_PDP_ADDRESS_CREATE = 'pdp-address-create';
	const EVENT_PDP_ADDRESS_UPDATE = 'pdp-address-update';
	const EVENT_PDP_ADDRESS_DELETE = 'pdp-address-delete';
	const EVENT_PDP_INVOICING_RECEIVED = 'pdp-invoicing-received';
	const EVENT_PDP_EVENT_RECEIVED = 'pdp-event-received';
	const EVENT_PDP_PAYMENT_CREATE = 'pdp-payment-create';
	const EVENT_OPERATION_UPDATE_COLLECTION = 'operation-update-collection';
	const EVENT_OPERATION_DELETE = 'operation-delete';
	const EVENT_OPERATION_CREATE = 'operation-create';
	const EVENT_OPERATION_UPDATE = 'operation-update';
	const EVENT_OPERATION_LETTER = 'operation-letter';
	const EVENT_OPERATION_UNLETTER = 'operation-unletter';
	const EVENT_OPERATION_QUICK = 'operation-quick';
	const EVENT_OPERATION_LETTER_AUTO = 'operation-letter-auto';
	const EVENT_OPERATION_UNLETTER_AUTO = 'operation-unletter-auto';
	const EVENT_JOURNAL_CODE_CREATE = 'journal-code-create';
	const EVENT_JOURNAL_CODE_UPDATE = 'journal-code-update';
	const EVENT_JOURNAL_CODE_DELETE = 'journal-code-delete';
	const EVENT_PATTERN_CREATE = 'pattern-create';
	const EVENT_PATTERN_UPDATE = 'pattern-update';
	const EVENT_PATTERN_STATUS = 'pattern-status';
	const EVENT_PATTERN_DELETE = 'pattern-delete';
	const EVENT_RECONCILIATE_DO = 'reconciliation-do';
	const EVENT_RECONCILIATION_CANCEL = 'reconciliation-cancel';
	const EVENT_VAT_CERFA_SAVE = 'vat-cerfa-save';
	const EVENT_VAT_CERFA_STATUS = 'vat-cerfa-status';
	const EVENT_VAT_CERFA_OPERATIONS = 'vat-cerfa-operations';
	const EVENT_VAT_CERFA_RESET = 'vat-cerfa-reset';
	const EVENT_VAT_REFUND_REQUEST = 'vat-refund-request';
	const EVENT_VAT_REFUND_RECEIVED = 'vat-refund-received';
	const EVENT_VAT_RECTIFIED = 'vat-rectified';
	const EVENT_VAT_PAYMENT_UPDATE = 'vat-payment-update';
	const EVENT_EDI_MANDATE_CREATE = 'edi-mandate-create';
	const EVENT_EDI_MANDATE_REVOKE = 'edi-mandate-revoke';
	const EVENT_EDI_TRANSMISSION_CREATE = 'edi-transmission-create';
	const EVENT_TAX_RETURN_DEPOSIT = 'tax-return-deposit';
	const EVENT_TAX_RETURN_DEPOSIT_DATE = 'tax-return-deposit-date';
	const EVENT_TAX_RETURN_DRAFT = 'tax-return-draft';
	const EVENT_ARCHIVE_GENERATION = 'archive-generation';

	const EVENT_CASH_REGISTER_CREATE = 'cash-register-create';
	const EVENT_CASH_REGISTER_UPDATE = 'cash-register-update';
	const EVENT_CASH_REGISTER_DELETE = 'cash-register-delete';
	const EVENT_CASH_REGISTER_CLOSE = 'cash-register-close';
	const EVENT_CASH_REGISTER_LINK = 'cash-register-link';
	const EVENT_CASH_REGISTER_UNLINK = 'cash-register-unlink';
	const EVENT_CASH_INITIAL = 'cash-initial';
	const EVENT_CASH_BALANCE = 'cash-balance';
	const EVENT_CASH_CREATE = 'cash-create';
	const EVENT_CASH_UPDATE = 'cash-update';
	const EVENT_CASH_CASHFLOW = 'cash-cashflow';
	const EVENT_CASH_DELETE = 'cash-delete';
	const EVENT_CASH_VALIDATE = 'cash-validate';
	const EVENT_CASH_IMPORT = 'cash-import';
	const EVENT_CASH_IMPORT_RECONCILIATE = 'cash-import-reconciliate';
	const EVENT_CASH_TRANSACTION_RECONCILIATE = 'cash-transaction-reconciliate';
	const EVENT_CASH_TRANSACTION_CANCEL = 'cash-transaction-cancel';
	const EVENT_CASH_SUGGESTION_IGNORE = 'cash-suggestion-ignore';
	const EVENT_CASH_SUGGESTION_RESTORE = 'cash-suggestion-restore';
	const EVENT_CASH_SUGGESTION_IMPORT = 'cash-suggestion-import';
	const EVENT_CASH_ARCHIVE = 'cash-archive';
	const EVENT_CASH_ARCHIVE_INTEGRITY = 'cash-archive-integrity';

	const EVENT_SELLING_ARCHIVE = 'selling-archive';
	const EVENT_SELLING_ARCHIVE_INTEGRITY = 'selling-archive-integrity';
	const EVENT_SELLING_MARKET_DEVICE_TICKET_REFUSED = 'selling-market-device-ticket-refused';

	const EVENT_ENABLE_BANKING_CONNECTED = 'enable-banking-connected';
	const EVENT_ENABLE_BANKING_REVOKED = 'enable-banking-revoked';
	const EVENT_ENABLE_BANKING_IMPORT = 'enable-banking-import';
	const EVENT_ENABLE_BANKING_BALANCE = 'enable-banking-balance';

	const ASSET_CREATE = 'asset-create';
	const ASSET_UPDATE = 'asset-update';
	const ASSET_DELETE = 'asset-delete';
	const ASSET_ATTACH = 'asset-attach';
	const ASSET_UNATTACH = 'asset-unattach';
	const ASSET_DISPOSE = 'asset-dispose';
	const ASSET_SALE = 'asset-sale';

	const EVENT_API_KEY_CREATE = 'api-key-create';
	const EVENT_API_KEY_REVOKE = 'api-key-revoke';
	const EVENT_API_APPLICATION_AUTHORIZE = 'api-application-authorize';
	const EVENT_API_APPLICATION_REVOKE = 'api-application-revoke';


	// Durée de conservation en jours, à défaut d'une durée propre à l'événement dans EVENTS
	const LOG_RETENTION_DEFAULT = 730;

	// Dix ans, années bissextiles comprises : le délai de l'article L102 B du LPF dans sa version du 01/01/2027, que
	// BOI-TVA-DECLA-30-10-30 § 155 impose au logiciel de caisse pour ses traces, retenu le 18/10/2026 pour toutes les
	// traces d'un geste de conformité
	const LOG_RETENTION_LEGAL = 3653;

	const LOG_CLEAN_BATCH = 5000;

	/**
	 * Tout ce qui dépend de l'événement, en un seul endroit : le module dont il relève, le niveau
	 * de lecture qu'il appelle, et sa durée de conservation quand elle diffère du défaut.
	 *
	 * Un événement absent de cette table n'est pas journalisable — l'oubli se voit à l'ajout de
	 * l'événement, et non plus au premier enregistrement, en pleine transaction métier.
	 *
	 * Le niveau donné ici est un défaut : un appelant qui sait que son geste est automatique passe
	 * 'level' dans ses arguments (cf. EnableBankingLib, qui distingue la synchronisation manuelle
	 * de celle du cron).
	 *
	 * Une trace de conformité (clôture, réouverture, FEC, archive, suppression, caisse validée) porte la durée
	 * LOG_RETENTION_LEGAL : la purge des 730 jours l'effacerait avant la fin du délai pendant lequel
	 * l'administration peut la demander.
	 *
	 * event => [module, niveau, conservation en jours]
	 */
	const EVENTS = [

		self::EVENT_ACCOUNT_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_ACCOUNT_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_ACCOUNT_STATUS => [Log::ACCOUNTING, Log::USER],
		self::EVENT_ACCOUNT_JOURNAL_CODE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_ACCOUNT_DELETE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_ACCOUNT_ANCILLARY_MOVE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_ACCOUNT_ANCILLARY_CANCEL => [Log::ACCOUNTING, Log::USER],

		self::EVENT_THIRD_PARTY_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_THIRD_PARTY_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_THIRD_PARTY_DELETE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_THIRD_PARTY_ADDRESS_ADD => [Log::ACCOUNTING, Log::USER],
		self::EVENT_THIRD_PARTY_ADDRESS_DELETE => [Log::ACCOUNTING, Log::USER],

		self::EVENT_ACCOUNTING_RESET => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],

		self::EVENT_FINANCIAL_YEAR_IMPORT => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_IMPORT_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_IMPORT_CANCEL => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_IMPORT_RULES => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_IMPORT_WARNING => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_CLOSE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_FINANCIAL_YEAR_RECLOSE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_FINANCIAL_YEAR_OPEN => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_FINANCIAL_YEAR_REOPEN => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_FINANCIAL_YEAR_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_DELETE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_FINANCIAL_YEAR_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_FEC_GENERATION => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_FINANCIAL_YEAR_LOCK => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_FINANCIAL_YEAR_RECONCILE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_RECONCILE_ENTRY => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_FILE_STORE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_FINANCIAL_YEAR_FILE_DELETE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_FINANCIAL_YEAR_ALLOCATION => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_INVENTORY => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_INVENTORY_DELETE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_FINANCIAL_YEAR_INCOME => [Log::ACCOUNTING, Log::USER],

		self::EVENT_BANK_ACCOUNT_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_BANK_ACCOUNT_DELETE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_BANK_ACCOUNT_IMPORT => [Log::ACCOUNTING, Log::USER],

		self::EVENT_CASHFLOW_ATTACH => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASHFLOW_UNLINK => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASHFLOW_IMPORT_CASH => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASHFLOW_DELETE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASHFLOW_UNDELETE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASHFLOW_WALLET => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASHFLOW_ANNOTATION_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASHFLOW_ANNOTATION_DELETE => [Log::ACCOUNTING, Log::USER],

		self::EVENT_PROOF_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PROOF_ATTACH => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PROOF_DETACH => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PROOF_DISCARD => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PROOF_UNDISCARD => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PROOF_TRASH => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PROOF_UNTRASH => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PROOF_DELETE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_PROOF_PURGE => [Log::ACCOUNTING, Log::SYSTEM, self::LOG_RETENTION_LEGAL],
		self::EVENT_PROOF_ERASE => [Log::ACCOUNTING, Log::SYSTEM, self::LOG_RETENTION_LEGAL],

		self::EVENT_PREACCOUNTING_IMPORT => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PREACCOUNTING_IGNORE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PREACCOUNTING_IGNORE_ALL => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PREACCOUNTING_SUGGESTION_IGNORE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PREACCOUNTING_SUGGESTION_METHOD => [Log::ACCOUNTING, Log::USER],

		self::EVENT_DEFERRAL_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_DEFERRAL_DELETE => [Log::ACCOUNTING, Log::USER],

		self::EVENT_INVOICING_SEND => [Log::ACCOUNTING, Log::USER],
		self::EVENT_INVOICING_EVENT_SENT => [Log::ACCOUNTING, Log::USER],
		self::EVENT_INVOICING_EVENT_WAITING => [Log::ACCOUNTING, Log::USER],
		self::EVENT_INVOICING_ACCOUNT => [Log::ACCOUNTING, Log::USER],
		self::EVENT_INVOICING_PAYMENT => [Log::ACCOUNTING, Log::USER],
		self::EVENT_INVOICING_COMMENT => [Log::ACCOUNTING, Log::USER],

		// Un jeton se renouvelle plusieurs fois par jour et ne dit rien d'une opération comptable :
		// il n'a d'intérêt que pour le diagnostic d'une panne, sur quelques semaines.
		self::EVENT_PDP_GET_ACCESS_TOKEN => [Log::ACCOUNTING, Log::TECHNICAL, 90],
		self::EVENT_PDP_GET_REFRESH_TOKEN => [Log::ACCOUNTING, Log::TECHNICAL, 90],

		self::EVENT_PDP_PARTNER_OPT_IN => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PDP_ADDRESS_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PDP_ADDRESS_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PDP_ADDRESS_DELETE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PDP_INVOICING_RECEIVED => [Log::ACCOUNTING, Log::SYSTEM],
		self::EVENT_PDP_EVENT_RECEIVED => [Log::ACCOUNTING, Log::SYSTEM],
		self::EVENT_PDP_PAYMENT_CREATE => [Log::ACCOUNTING, Log::SYSTEM],

		self::EVENT_OPERATION_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_OPERATION_UPDATE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_OPERATION_DELETE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_OPERATION_LETTER => [Log::ACCOUNTING, Log::USER],
		self::EVENT_OPERATION_UNLETTER => [Log::ACCOUNTING, Log::USER],
		self::EVENT_OPERATION_QUICK => [Log::ACCOUNTING, Log::USER],
		self::EVENT_OPERATION_LETTER_AUTO => [Log::ACCOUNTING, Log::SYSTEM],
		self::EVENT_OPERATION_UNLETTER_AUTO => [Log::ACCOUNTING, Log::SYSTEM],
		// Geste d'utilisateur, mais masqué jusqu'ici (display INTERNAL) : le niveau reprend cet
		// état plutôt que d'ajouter des lignes à l'écran sans que personne l'ait demandé.
		self::EVENT_OPERATION_UPDATE_COLLECTION => [Log::ACCOUNTING, Log::TECHNICAL],

		self::EVENT_JOURNAL_CODE_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_JOURNAL_CODE_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_JOURNAL_CODE_DELETE => [Log::ACCOUNTING, Log::USER],

		self::EVENT_PATTERN_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PATTERN_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PATTERN_STATUS => [Log::ACCOUNTING, Log::USER],
		self::EVENT_PATTERN_DELETE => [Log::ACCOUNTING, Log::USER],

		self::EVENT_RECONCILIATE_DO => [Log::ACCOUNTING, Log::USER],
		self::EVENT_RECONCILIATION_CANCEL => [Log::ACCOUNTING, Log::USER],

		self::EVENT_VAT_CERFA_SAVE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_VAT_CERFA_STATUS => [Log::ACCOUNTING, Log::USER],
		self::EVENT_VAT_CERFA_OPERATIONS => [Log::ACCOUNTING, Log::USER],
		self::EVENT_VAT_CERFA_RESET => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_VAT_REFUND_REQUEST => [Log::ACCOUNTING, Log::USER],
		self::EVENT_VAT_REFUND_RECEIVED => [Log::ACCOUNTING, Log::USER],
		self::EVENT_VAT_RECTIFIED => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_VAT_PAYMENT_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_EDI_MANDATE_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_EDI_MANDATE_REVOKE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_EDI_TRANSMISSION_CREATE => [Log::ACCOUNTING, Log::USER],

		self::EVENT_TAX_RETURN_DEPOSIT => [Log::ACCOUNTING, Log::USER],
		self::EVENT_TAX_RETURN_DEPOSIT_DATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_TAX_RETURN_DRAFT => [Log::ACCOUNTING, Log::USER],

		self::EVENT_ARCHIVE_GENERATION => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],

		// Le cahier de caisse n'enregistre pas la saisie courante : une opération vit dans le cahier,
		// à sa date, et sa chaîne de signature (securing\Signature) en garantit l'intégrité. Ce qui
		// entre ici est ce que le cahier ne retient pas de lui-même — le geste posé après coup sur une
		// opération déjà écrite, et tout ce qui touche le cahier lui-même.
		self::EVENT_CASH_REGISTER_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_REGISTER_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_REGISTER_DELETE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_CASH_REGISTER_CLOSE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_CASH_REGISTER_LINK => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_CASH_REGISTER_UNLINK => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],

		self::EVENT_CASH_INITIAL => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_BALANCE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_CREATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_UPDATE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_CASH_CASHFLOW => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_CASH_DELETE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_CASH_VALIDATE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],

		self::EVENT_CASH_IMPORT => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_IMPORT_RECONCILIATE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_TRANSACTION_RECONCILIATE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_CASH_TRANSACTION_CANCEL => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_CASH_SUGGESTION_IGNORE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_SUGGESTION_RESTORE => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_SUGGESTION_IMPORT => [Log::ACCOUNTING, Log::USER],
		self::EVENT_CASH_ARCHIVE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_CASH_ARCHIVE_INTEGRITY => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],

		self::EVENT_SELLING_ARCHIVE => [Log::SELLING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_SELLING_ARCHIVE_INTEGRITY => [Log::SELLING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::EVENT_SELLING_MARKET_DEVICE_TICKET_REFUSED => [Log::SELLING, Log::TECHNICAL, self::LOG_RETENTION_LEGAL],

		self::EVENT_ENABLE_BANKING_CONNECTED => [Log::ACCOUNTING, Log::USER],
		self::EVENT_ENABLE_BANKING_REVOKED => [Log::ACCOUNTING, Log::USER],
		self::EVENT_ENABLE_BANKING_IMPORT => [Log::ACCOUNTING, Log::USER],
		self::EVENT_ENABLE_BANKING_BALANCE => [Log::ACCOUNTING, Log::USER],

		self::ASSET_CREATE => [Log::ACCOUNTING, Log::USER],
		self::ASSET_UPDATE => [Log::ACCOUNTING, Log::USER],
		self::ASSET_DELETE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::ASSET_ATTACH => [Log::ACCOUNTING, Log::USER],
		self::ASSET_UNATTACH => [Log::ACCOUNTING, Log::USER],
		self::ASSET_DISPOSE => [Log::ACCOUNTING, Log::USER, self::LOG_RETENTION_LEGAL],
		self::ASSET_SALE => [Log::ACCOUNTING, Log::USER],

		self::EVENT_API_KEY_CREATE => [Log::SETTINGS, Log::USER],
		self::EVENT_API_KEY_REVOKE => [Log::SETTINGS, Log::USER],
		self::EVENT_API_APPLICATION_AUTHORIZE => [Log::SETTINGS, Log::USER],
		self::EVENT_API_APPLICATION_REVOKE => [Log::SETTINGS, Log::USER],

	];

}
