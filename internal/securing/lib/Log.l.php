<?php
namespace securing;

class LogLib extends LogCrud {

	use \Notifiable;

	public static function get(int $page): array {

		$number = LogSetting::LOG_PER_PAGE;
		$offset = $page * $number;

		Log::model()
			->select(Log::getSelection())
			->whereLevel(Log::USER)
			->sort(['doneAt' => SORT_DESC, 'id' => SORT_DESC])
			->option('count');

		return [Log::model()->getCollection($offset, LogSetting::LOG_PER_PAGE), Log::model()->found()];

	}

	public static function getForAdmin(int $page, \Search $search): array {

		$number = LogSetting::ADMIN_LOG_PER_PAGE;
		$offset = $page * $number;

		Log::model()
			->select(Log::getSelection())
			->whereLevel($search->get('level'), if: $search->get('level'))
			->whereChannel($search->get('channel'), if: $search->get('channel'))
			->whereEvent($search->get('event'), if: $search->get('event'))
			->sort(['doneAt' => SORT_DESC, 'id' => SORT_DESC])
			->option('count');

		return [Log::model()->getCollection($offset, $number), Log::model()->found()];

	}

	public static function createByValues(string $event, mixed $args): Log {

		$eStructure = \structure\Structure::getConnected();
		\structure\StructureLib::connectDatabase($eStructure);

		[$module, $level] = LogSetting::EVENTS[$event] ?? throw new \UnsupportedException('Unknown event \''.$event.'\' for Log');

		$origin = \ApiRoute::getOrigin();

		$values = [
			'module' => $module,
			'event' => $event,
			'data' => self::getData($event, $args),
			'level' => self::getLevel($args, $level),
			'channel' => self::getChannel($args, $origin),
			'apiLog' => ($origin === 'api') ? \ApiRoute::getLog()['id'] : NULL,
		];

		$eLog = new Log();
		$eLog->build(array_keys($values), $values);

		parent::create($eLog);

		return $eLog;

	}

	public static function clean(): void {

		// Une requête par durée, et le défaut pour tous les événements qui n'en ont pas
		$eventsByDays = [];

		foreach(LogSetting::EVENTS as $event => $settings) {

			if(isset($settings[2])) {
				$eventsByDays[$settings[2]][] = $event;
			}

		}

		foreach($eventsByDays as $days => $events) {
			self::deleteOlderThan($days, 'IN', $events);
		}

		self::deleteOlderThan(LogSetting::LOG_RETENTION_DEFAULT, 'NOT IN', array_merge(...array_values($eventsByDays)));

	}

	private static function deleteOlderThan(int $days, string $condition, array $events): void {

		do {

			$deleted = new LogModel()
				->where('doneAt < NOW() - INTERVAL '.$days.' DAY')
				->whereEvent($condition, $events, if: empty($events) === FALSE)
				->limit(LogSetting::LOG_CLEAN_BATCH)
				->delete();

		} while($deleted === LogSetting::LOG_CLEAN_BATCH);

	}

	private static function getLevel(mixed $args, string $level): string {

		if(in_array($args['level'] ?? NULL, Log::model()->getPropertyEnum('level'))) {
			return $args['level'];
		}

		return $level;

	}

	private static function getChannel(mixed $args, ?string $origin): string {

		if(in_array($args['channel'] ?? NULL, Log::model()->getPropertyEnum('channel'))) {
			return $args['channel'];
		}

		if($origin === 'api') {
			return Log::API;
		}

		return \Route::getRequestedWith() === 'cli' ? Log::CRON : Log::WEB;

	}

	public static function getData(string $event, mixed $args): array {

		return match($event) {

			LogSetting::EVENT_ACCOUNT_CREATE => [
				'account' => self::refAccount($args),
			],

			LogSetting::EVENT_ACCOUNT_UPDATE => [
				'account' => self::refAccount($args['account']),
				'properties' => $args['properties'],
			],

			LogSetting::EVENT_ACCOUNT_STATUS => [
				'account' => self::refAccount($args),
				'status' => $args['status'],
			],

			LogSetting::EVENT_ACCOUNT_JOURNAL_CODE => [
				'journalCode' => self::refJournalCode($args['journalCode']),
				'nAccount' => $args['nAccount'],
			],

			LogSetting::EVENT_ACCOUNT_DELETE => [
				'class' => $args['class'],
				'financialYear' => self::refFinancialYear($args['financialYear']),
			],

			LogSetting::EVENT_ACCOUNT_ANCILLARY_MOVE,
			LogSetting::EVENT_ACCOUNT_ANCILLARY_CANCEL => [
				'account' => self::refAccount($args['account']),
				'parentClass' => $args['parentClass'],
				'nOperation' => $args['nOperation'],
				'nCash' => $args['nCash'],
				'nProduct' => $args['nProduct'],
			],

			LogSetting::EVENT_THIRD_PARTY_CREATE,
			LogSetting::EVENT_THIRD_PARTY_DELETE => [
				'thirdParty' => self::refThirdParty($args),
			],

			LogSetting::EVENT_THIRD_PARTY_UPDATE => [
				'thirdParty' => self::refThirdParty($args['thirdParty']),
				'properties' => $args['properties'],
			],

			LogSetting::EVENT_THIRD_PARTY_ADDRESS_ADD,
			LogSetting::EVENT_THIRD_PARTY_ADDRESS_DELETE => [
				'thirdParty' => self::refThirdParty($args['thirdParty']),
				'address' => $args['address'],
			],

			LogSetting::EVENT_ACCOUNTING_RESET => [
				'nFinancialYear' => $args['nFinancialYear'],
				'nOperation' => $args['nOperation'],
				'keepConfiguration' => $args['keepConfiguration'],
				'nRegisterKept' => $args['nRegisterKept'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT,
			LogSetting::EVENT_FINANCIAL_YEAR_CLOSE,
			LogSetting::EVENT_FINANCIAL_YEAR_RECLOSE,
			LogSetting::EVENT_FINANCIAL_YEAR_OPEN,
			LogSetting::EVENT_FINANCIAL_YEAR_REOPEN,
			LogSetting::EVENT_FINANCIAL_YEAR_CREATE,
			LogSetting::EVENT_FINANCIAL_YEAR_DELETE,
			LogSetting::EVENT_FINANCIAL_YEAR_FEC_GENERATION => [
				'financialYear' => self::refFinancialYear($args),
			],

			LogSetting::EVENT_FINANCIAL_YEAR_UPDATE => [
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'changes' => array_map(
					fn($values) => ['old' => self::scalar($values['old']), 'new' => self::scalar($values['new'])],
					$args['changes']
				),
			],

			LogSetting::EVENT_FINANCIAL_YEAR_LOCK => [
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'to' => $args['to'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT_CREATE,
			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT_CANCEL => [
				'import' => self::refImport($args),
			],

			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT_RULES => [
				'import' => self::refImport($args['import']),
				'complete' => $args['complete'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT_WARNING => [
				'import' => self::refImport($args['import']),
				'type' => $args['type'],
				'confirmed' => $args['confirmed'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_RECONCILE => [
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'checkKey' => $args['checkKey'],
				'nAccount' => $args['nAccount'],
				'source' => $args['source'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_RECONCILE_ENTRY => [
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'checkKey' => $args['checkKey'],
				'nOperation' => $args['nOperation'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_FILE_STORE => [
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'type' => $args['type'],
				'reference' => $args['reference'],
				'name' => $args['name'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_FILE_DELETE => [
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'type' => $args['type'],
				'reference' => $args['reference'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_ALLOCATION => [
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'result' => $args['result'],
				'nAccount' => $args['nAccount'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_INVENTORY => [
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'nAccount' => $args['nAccount'],
				'value' => $args['value'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_INVENTORY_DELETE => [
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'accountLabel' => $args['accountLabel'],
			],

			LogSetting::EVENT_FINANCIAL_YEAR_INCOME => [
				'year' => $args['year'],
				'amounts' => $args['cIncomeSaved']->makeArray(fn(\financialYear\Income $eIncome) => [
					'excludingVat' => $eIncome['amountExcludingVat'],
					'includingVat' => $eIncome['amountIncludingVat'],
				], preserveKeys: TRUE),
				'deleted' => $args['cIncomeDeleted']->getKeys(),
			],

			LogSetting::EVENT_BANK_ACCOUNT_UPDATE => [
				'bankAccount' => self::refBankAccount($args['bankAccount']),
				'properties' => array_keys($args['properties'] ?? []),
			],

			LogSetting::EVENT_BANK_ACCOUNT_DELETE,
			LogSetting::EVENT_BANK_ACCOUNT_IMPORT,
			LogSetting::EVENT_ENABLE_BANKING_CONNECTED,
			LogSetting::EVENT_ENABLE_BANKING_REVOKED => [
				'bankAccount' => self::refBankAccount($args),
			],

			LogSetting::EVENT_ENABLE_BANKING_BALANCE => [
				'bankAccount' => self::refBankAccount($args['bankAccount']),
			],

			LogSetting::EVENT_ENABLE_BANKING_IMPORT => [
				'bankAccount' => self::refBankAccount($args['bankAccount']),
				'imported' => count($args['import']['result']['imported'] ?? []),
				'automatic' => (bool)$args['import']['automatic'],
				'startDate' => $args['import']['startDate'],
				'endDate' => $args['import']['endDate'],
			],

			LogSetting::EVENT_CASHFLOW_ATTACH => [
				'cashflow' => self::refCashflow($args['cashflow']),
				'nOperation' => $args['nOperation'],
			],

			LogSetting::EVENT_CASHFLOW_UNLINK => [
				'cashflow' => self::refCashflow($args['cashflow']),
				'action' => $args['action'],
			],

			LogSetting::EVENT_CASHFLOW_IMPORT_CASH => [
				'cashflow' => self::refCashflow($args['cashflow']),
				'register' => self::refRegister($args['register']),
			],

			LogSetting::EVENT_CASHFLOW_DELETE,
			LogSetting::EVENT_CASHFLOW_UNDELETE => [
				'cashflow' => self::refCashflow($args),
			],

			LogSetting::EVENT_CASHFLOW_WALLET => [
				'cashflow' => self::refCashflow($args['cashflow']),
				'amount' => $args['amount'],
			],

			LogSetting::EVENT_CASHFLOW_ANNOTATION_UPDATE,
			LogSetting::EVENT_CASHFLOW_ANNOTATION_DELETE => [
				'cashflow' => self::refCashflow($args['cashflow']),
				'author' => $args['createdBy']->extracts(['firstName', 'lastName']),
				'text' => $args['text'],
			],

			LogSetting::EVENT_PROOF_CREATE,
			LogSetting::EVENT_PROOF_DISCARD,
			LogSetting::EVENT_PROOF_UNDISCARD,
			LogSetting::EVENT_PROOF_TRASH,
			LogSetting::EVENT_PROOF_UNTRASH,
			LogSetting::EVENT_PROOF_DELETE,
			LogSetting::EVENT_PROOF_PURGE,
			LogSetting::EVENT_PROOF_ERASE => [
				'proof' => self::refProof($args),
			],

			LogSetting::EVENT_PROOF_ATTACH,
			LogSetting::EVENT_PROOF_DETACH => [
				'proof' => self::refProof($args['proof']),
			] + match($args::class) {
				\proof\Cashflow::class => ['target' => 'cashflow', 'cashflow' => self::refCashflow($args['cashflow'])],
				\proof\Cash::class => ['target' => 'cash', 'cash' => self::refCash($args['cash'])],
				\proof\Operation::class => ['target' => 'operationGroup', 'operationGroup' => self::refProofOperationGroup($args['hash'])],
				\proof\Asset::class => ['target' => 'asset', 'asset' => self::refAsset($args['asset'])],
			},

			LogSetting::EVENT_JOURNAL_CODE_CREATE,
			LogSetting::EVENT_JOURNAL_CODE_DELETE => [
				'journalCode' => self::refJournalCode($args),
			],

			LogSetting::EVENT_JOURNAL_CODE_UPDATE => [
				'journalCode' => self::refJournalCode($args['journalCode']),
				'properties' => $args['properties'],
			],

			LogSetting::EVENT_PATTERN_CREATE => [
				'pattern' => self::refPattern($args['pattern']),
				'fromOperations' => $args['fromOperations'],
			],

			LogSetting::EVENT_PATTERN_UPDATE => [
				'pattern' => self::refPattern($args['pattern']),
				'properties' => $args['properties'],
			],

			LogSetting::EVENT_PATTERN_STATUS => [
				'pattern' => self::refPattern($args),
				'status' => $args['status'],
			],

			LogSetting::EVENT_PATTERN_DELETE => [
				'pattern' => self::refPattern($args),
			],

			LogSetting::EVENT_RECONCILIATE_DO,
			LogSetting::EVENT_RECONCILIATION_CANCEL => [
				'cashflow' => self::refCashflow($args['cashflow']),
				'reconciliation' => self::refReconciliation($args['reconciliation']),
			],

			LogSetting::EVENT_PREACCOUNTING_IMPORT => [
				'month' => $args,
			],

			LogSetting::EVENT_PREACCOUNTING_IGNORE => [
				'type' => $args['type'],
				'label' => $args['label'],
				'amount' => $args['amount'],
				'date' => $args['date'],
			],

			LogSetting::EVENT_PREACCOUNTING_IGNORE_ALL => [
				'from' => $args['from'],
				'to' => $args['to'],
				'nPayment' => $args['nPayment'],
				'nSale' => $args['nSale'],
			],

			LogSetting::EVENT_PREACCOUNTING_SUGGESTION_IGNORE => [
				'cashflow' => self::refCashflow($args['cashflow']),
			],

			LogSetting::EVENT_PREACCOUNTING_SUGGESTION_METHOD => [
				'cashflow' => self::refCashflow($args['cashflow']),
				'method' => self::refMethod($args['method']),
			],

			LogSetting::EVENT_DEFERRAL_CREATE => [
				'deferral' => [
					'id' => $args['deferral']['id'],
					'amount' => $args['deferral']['amount'],
					'operation' => self::refOperation($args['deferral']['operation']),
				],
				'type' => $args['type'],
			],

			LogSetting::EVENT_DEFERRAL_DELETE => [
				'deferral' => ['id' => $args['id']],
			],

			LogSetting::EVENT_INVOICING_SEND => [
				'number' => $args['number'],
			],

			LogSetting::EVENT_INVOICING_EVENT_SENT,
			LogSetting::EVENT_INVOICING_EVENT_WAITING => [
				'invoicing' => ['id' => $args['invoicing']['id'], 'number' => $args['invoicing']['number']],
				'status' => $args['status'],
			],

			LogSetting::EVENT_INVOICING_ACCOUNT => [
				'nLine' => $args['nLine'],
				'accountNumber' => $args['accountNumber'],
			],

			LogSetting::EVENT_INVOICING_PAYMENT => [
				'invoicing' => self::refInvoicing($args['invoicing']),
				'nPayment' => $args['nPayment'],
				'amount' => $args['amount'],
			],

			LogSetting::EVENT_INVOICING_COMMENT => [
				'invoicing' => self::refInvoicing($args),
			],

			LogSetting::EVENT_PDP_GET_ACCESS_TOKEN,
			LogSetting::EVENT_PDP_GET_REFRESH_TOKEN => [
				'partner' => $args['id'] ?? NULL,
			],

			LogSetting::EVENT_PDP_PARTNER_OPT_IN => [
				'optIn' => (bool)$args['optIn'],
			],

			LogSetting::EVENT_PDP_ADDRESS_CREATE,
			LogSetting::EVENT_PDP_ADDRESS_DELETE => [
				'address' => self::refAddress($args),
			],

			LogSetting::EVENT_PDP_INVOICING_RECEIVED => [
				'nInvoicing' => $args['nInvoicing'],
			],

			LogSetting::EVENT_PDP_EVENT_RECEIVED => [
				'nEvent' => $args['nEvent'],
			],

			LogSetting::EVENT_PDP_PAYMENT_CREATE => [
				'invoicing' => self::refInvoicing($args['invoicing']),
				'paid' => (bool)$args['paid'],
			],

			LogSetting::EVENT_PDP_ADDRESS_UPDATE => [
				'address' => self::refAddress($args['address']),
				'properties' => $args['properties'],
			],

			LogSetting::EVENT_OPERATION_CREATE,
			LogSetting::EVENT_OPERATION_UPDATE => [
				'nOperation' => $args['nOperation'],
				'cashflow' => self::refCashflow($args['cashflow']),
			],

			LogSetting::EVENT_OPERATION_DELETE => [
				'operation' => self::refOperation($args),
			],

			LogSetting::EVENT_OPERATION_LETTER,
			LogSetting::EVENT_OPERATION_UNLETTER => [
				'lettering' => $args['lettering'],
				'nOperation' => $args['nOperation'],
			],

			LogSetting::EVENT_OPERATION_QUICK => [
				'operation' => self::refOperation($args['operation']),
				'property' => $args['property'],
			],

			LogSetting::EVENT_OPERATION_LETTER_AUTO => [
				'letterings' => $args['letterings'],
			],

			LogSetting::EVENT_OPERATION_UNLETTER_AUTO => [
				'letterings' => $args['letterings'],
				'nOperation' => $args['nOperation'],
			],

			LogSetting::EVENT_OPERATION_UPDATE_COLLECTION => [
				'property' => $args['property'],
				'value' => $args['value'],
				'nOperation' => $args['nOperation'],
			],

			LogSetting::EVENT_VAT_CERFA_SAVE,
			LogSetting::EVENT_VAT_CERFA_STATUS,
			LogSetting::EVENT_VAT_CERFA_RESET,
			LogSetting::EVENT_VAT_CERFA_OPERATIONS => [
				'declaration' => $args['id'],
				'from' => $args['from'],
				'to' => $args['to'],
				'status' => $args['status'],
			],

			LogSetting::EVENT_VAT_REFUND_REQUEST => [
				'declaration' => $args['declaration']['id'],
				'from' => $args['declaration']['from'],
				'to' => $args['declaration']['to'],
				'amount' => $args['amount'],
				'method' => $args['method'],
			],

			LogSetting::EVENT_VAT_REFUND_RECEIVED => [
				'declaration' => $args['id'],
				'from' => $args['from'],
				'to' => $args['to'],
				'refundReceived' => $args['refundReceived'],
			],

			LogSetting::EVENT_VAT_RECTIFIED => [
				'from' => $args['from'],
				'to' => $args['to'],
			],

			// Le compte se désigne par son identifiant et son nom, jamais par son IBAN
			LogSetting::EVENT_VAT_PAYMENT_UPDATE => [
				'method' => $args['vatPaymentMethod'],
				'bankAccount' => self::refBankAccount($args['vatPaymentBankAccount']),
			],

			LogSetting::EVENT_EDI_MANDATE_CREATE,
			LogSetting::EVENT_EDI_MANDATE_REVOKE => [
				'mandate' => $args['id'],
				'version' => $args['version'],
			],

			LogSetting::EVENT_EDI_TRANSMISSION_CREATE => [
				'transmission' => $args['id'],
				'type' => $args['type'],
				'declaration' => $args['declaration']['id'],
				'from' => $args['declaration']['from'],
				'to' => $args['declaration']['to'],
			],

			LogSetting::EVENT_TAX_RETURN_DEPOSIT,
			LogSetting::EVENT_TAX_RETURN_DRAFT => [
				'deposit' => $args['id'],
				'financialYear' => self::refFinancialYear($args['financialYear']),
				'depositedOn' => $args['depositedOn'],
			],

			LogSetting::EVENT_TAX_RETURN_DEPOSIT_DATE => [
				'deposit' => $args['deposit']['id'],
				'financialYear' => self::refFinancialYear($args['deposit']['financialYear']),
				'old' => $args['old'],
				'new' => $args['deposit']['depositedOn'],
			],

			LogSetting::EVENT_ARCHIVE_GENERATION => [
				'archive' => $args['id'],
				'financialYear' => self::refFinancialYear($args['financialYear']),
			],

			LogSetting::EVENT_CASH_REGISTER_CREATE => [
				'register' => self::refRegister($args),
			],

			LogSetting::EVENT_CASH_REGISTER_UPDATE => [
				'register' => self::refRegister($args['register']),
				'properties' => $args['properties'],
			],

			LogSetting::EVENT_CASH_REGISTER_DELETE => [
				'register' => self::refRegister($args['register']),
				'nCash' => $args['nCash'],
			],

			LogSetting::EVENT_CASH_REGISTER_CLOSE => [
				'register' => self::refRegister($args['register']),
				'to' => $args['to'],
			],

			LogSetting::EVENT_CASH_REGISTER_LINK,
			LogSetting::EVENT_CASH_REGISTER_UNLINK => [
				'register' => self::refRegister($args['register']),
				'partner' => $args['partner'],
			],

			LogSetting::EVENT_CASH_INITIAL,
			LogSetting::EVENT_CASH_BALANCE,
			LogSetting::EVENT_CASH_CREATE,
			LogSetting::EVENT_CASH_UPDATE,
			LogSetting::EVENT_CASH_DELETE => [
				'cash' => self::refCash($args),
			],

			LogSetting::EVENT_CASH_CASHFLOW => [
				'cash' => self::refCash($args['cash']),
				'before' => self::refCashflow($args['before']),
				'after' => self::refCashflow($args['after']),
				'sourceBefore' => $args['sourceBefore'],
				'sourceAfter' => $args['sourceAfter'],
			],

			LogSetting::EVENT_CASH_VALIDATE => [
				'register' => self::refRegister($args['register']),
				'to' => $args['to'],
				'nCash' => $args['nCash'],
			],

			LogSetting::EVENT_CASH_IMPORT => [
				'register' => self::refRegister($args['register']),
				'partner' => $args['partner'],
				'imported' => $args['imported'],
				'automatic' => (bool)$args['automatic'],
			],

			LogSetting::EVENT_CASH_IMPORT_RECONCILIATE => [
				'register' => self::refRegister($args['register']),
				'partner' => $args['partner'],
				'nReconciled' => $args['nReconciled'],
			],

			LogSetting::EVENT_CASH_TRANSACTION_RECONCILIATE,
			LogSetting::EVENT_CASH_TRANSACTION_CANCEL => [
				'transaction' => self::refTransaction($args),
			],

			LogSetting::EVENT_CASH_SUGGESTION_IGNORE,
			LogSetting::EVENT_CASH_SUGGESTION_IMPORT => [
				'register' => self::refRegister($args['register']),
				'nSuggestion' => $args['nSuggestion'],
			],

			LogSetting::EVENT_CASH_SUGGESTION_RESTORE => [
				'register' => self::refRegister($args['register']),
			],

			// signed distingue une archive signée à sa création d'une archive d'avant la signature des archives
			LogSetting::EVENT_CASH_ARCHIVE,
			LogSetting::EVENT_SELLING_ARCHIVE => [
				'archive' => $args['id'],
				'from' => $args['from'],
				'to' => $args['to'],
				'signed' => TRUE,
			],

			LogSetting::EVENT_CASH_ARCHIVE_INTEGRITY,
			LogSetting::EVENT_SELLING_ARCHIVE_INTEGRITY => [
				'archive' => $args['id'],
			],

			// Le numéro de vente ne désigne une vente qu'avec la clé du poste qui l'a numérotée
			LogSetting::EVENT_SELLING_MARKET_DEVICE_TICKET_REFUSED => [
				'market' => $args['market']['id'],
				'key' => $args['key'],
				'sequence' => $args['sequence'],
				'fail' => $args['fail'],
			],

			LogSetting::ASSET_CREATE,
			LogSetting::ASSET_UPDATE,
			LogSetting::ASSET_DELETE => [
				'asset' => self::refAsset($args['asset']),
				'grant' => \asset\AssetLib::isGrant($args['asset']['accountLabel']),
			],

			LogSetting::ASSET_ATTACH => [
				'operation' => self::refOperation($args['operation']),
				'grant' => \asset\AssetLib::isGrant($args['operation']['accountLabel']),
			],

			LogSetting::ASSET_UNATTACH => [
				'asset' => self::refAsset($args['asset']),
				'operation' => self::refOperation($args['operation']),
				'grant' => \asset\AssetLib::isGrant($args['asset']['accountLabel']),
			],

			LogSetting::ASSET_DISPOSE => [
				'asset' => self::refAsset($args['asset']),
				'grant' => \asset\AssetLib::isGrant($args['asset']['accountLabel']),
				'reason' => $args['asset']['disposalReason'],
				'amount' => $args['asset']['amountSold'],
				'partial' => $args['partial'],
			],

			LogSetting::ASSET_SALE => [
				'asset' => self::refAsset($args['asset']),
				'amount' => $args['asset']['amountSold'],
				'vatRate' => $args['asset']['vatRate'],
			],

			LogSetting::EVENT_API_KEY_CREATE => [
				'name' => $args['name'],
				'scopes' => $args['scopes'],
			],

			LogSetting::EVENT_API_KEY_REVOKE => [
				'accessToken' => ['id' => $args['id'], 'label' => $args['name']],
			],

			LogSetting::EVENT_API_APPLICATION_AUTHORIZE => [
				'client' => self::refClient($args['client']),
				'scopes' => $args['scopes'],
			],

			LogSetting::EVENT_API_APPLICATION_REVOKE => [
				'client' => self::refClient($args['client']),
				'user' => $args['user']->extracts(['id', 'firstName', 'lastName']),
			],

		};

	}

	/**
	 * Les propriétés du geste dont la valeur a bougé, comparées entre la copie prise avant le build
	 * et l'élément écrit. La liste est donnée par l'appelant plutôt que déduite de l'élément : une
	 * sélection porte aussi ses alias et ses propriétés calculées, qui n'ont pas d'avant à comparer.
	 */
	public static function getChangedProperties(\Element $eElementOld, \Element $eElement, array $properties): array {

		return array_values(array_filter(
			$properties,
			fn($property) => self::scalar($eElementOld[$property]) !== self::scalar($eElement[$property])
		));

	}

	private static function scalar(mixed $value): string|int|float|bool|null {

		if($value instanceof \Element) {
			return $value->empty() ? NULL : $value['id'];
		}

		return is_scalar($value) || $value === NULL ? $value : (string)json_encode($value);

	}

	private static function refAccount(\account\Account $eAccount): ?array {

		if($eAccount->empty()) {
			return NULL;
		}

		$id = $eAccount['id'];

		if($eAccount->offsetExists('description') === FALSE) {
			$eAccount = \account\AccountLib::getById($id);
		}

		if($eAccount->empty()) {
			return ['id' => $id];
		}

		return [
			'id' => $id,
			'class' => $eAccount['class'],
			'label' => $eAccount['description'],
			'financialYear' => self::refFinancialYear($eAccount['financialYear']),
		];

	}

	private static function refJournalCode(\journal\JournalCode $eJournalCode): ?array {

		if($eJournalCode->empty()) {
			return NULL;
		}

		$id = $eJournalCode['id'];

		if($eJournalCode->offsetExists('name') === FALSE) {
			$eJournalCode = \journal\JournalCodeLib::getById($id);
		}

		return ['id' => $id, 'label' => $eJournalCode->empty() ? NULL : $eJournalCode['name']];

	}

	private static function refPattern(\journal\Pattern $ePattern): ?array {

		if($ePattern->empty()) {
			return NULL;
		}

		$id = $ePattern['id'];

		if($ePattern->offsetExists('name') === FALSE) {
			$ePattern = \journal\PatternLib::getById($id);
		}

		return ['id' => $id, 'label' => $ePattern->empty() ? NULL : $ePattern['name']];

	}

	private static function refThirdParty(\thirdParty\ThirdParty $eThirdParty): ?array {

		if($eThirdParty->empty()) {
			return NULL;
		}

		$id = $eThirdParty['id'];

		if($eThirdParty->offsetExists('name') === FALSE) {
			$eThirdParty = \thirdParty\ThirdPartyLib::getById($id);
		}

		return ['id' => $id, 'label' => $eThirdParty->empty() ? NULL : $eThirdParty['name']];

	}

	private static function refFinancialYear(\financialYear\FinancialYear $eFinancialYear): ?array {

		if($eFinancialYear->empty()) {
			return NULL;
		}

		$id = $eFinancialYear['id'];

		if($eFinancialYear->offsetExists('startDate') === FALSE) {
			$eFinancialYear = \financialYear\FinancialYearLib::getById($id);
		}

		return ['id' => $id, 'label' => $eFinancialYear->getLabel()];

	}

	private static function refCashflow(\bank\Cashflow $eCashflow): ?array {

		if($eCashflow->empty()) {
			return NULL;
		}

		$id = $eCashflow['id'];

		if($eCashflow->offsetExists('memo') === FALSE) {
			$eCashflow = \bank\CashflowLib::getById($id);
		}

		if($eCashflow->empty()) {
			return ['id' => $id];
		}

		return [
			'id' => $id,
			'label' => $eCashflow['memo'] ?: $eCashflow['name'],
			'date' => $eCashflow['date'],
			'amount' => $eCashflow['amount'],
		];

	}

	private static function refProof(\proof\Proof $eProof): ?array {

		if($eProof->empty()) {
			return NULL;
		}

		$id = $eProof['id'];

		if($eProof->offsetExists('name') === FALSE or $eProof->offsetExists('fileName') === FALSE) {
			$eProof = \proof\ProofLib::getById($id, ['name', 'fileName']);
		}

		if($eProof->empty()) {
			return ['id' => $id];
		}

		return ['id' => $id, 'label' => $eProof['name'] ?? $eProof['fileName']];

	}

	private static function refProofOperationGroup(string $hash): array {

		$cOperation = \proof\OperationLib::getGroupsByHash([$hash]);

		if($cOperation->offsetExists($hash) === FALSE) {
			return ['hash' => $hash];
		}

		$eOperation = $cOperation[$hash];

		return [
			'hash' => $hash,
			'label' => $eOperation['description'],
			'date' => $eOperation['date'],
			'amount' => $eOperation['amount'],
		];

	}

	private static function refReconciliation(\bank\Reconciliation $eReconciliation): ?array {

		if($eReconciliation->empty()) {
			return NULL;
		}

		return [
			'source' => $eReconciliation['source'],
			'document' => self::refReconciliationDocument($eReconciliation),
			'amount' => $eReconciliation['amount'],
		];

	}

	/**
	 * Le numéro du document réglé par le rapprochement. Chaque source le range sur une colonne qui lui
	 * est propre, et le rapprochement n'arrive du panneau qu'avec l'id de son document : le Lib du
	 * module le relit alors, et rend un élément vide si le document a disparu depuis. Une vente ou une
	 * facture se relit par l'événement des ventes, absentes de la version publique, où aucun
	 * rapprochement ne peut en citer une.
	 */
	private static function refReconciliationDocument(\bank\Reconciliation $eReconciliation): ?string {

		return match($eReconciliation['source']) {

			\bank\Reconciliation::SALE => self::documentNumber($eReconciliation['sale'], 'document', fn(int $id) => self::notify('getLogSale', $id)[0]),
			\bank\Reconciliation::INVOICE => self::documentNumber($eReconciliation['invoice'], 'number', fn(int $id) => self::notify('getLogInvoice', $id)[0]),
			\bank\Reconciliation::INVOICING => self::documentNumber($eReconciliation['invoicing'], 'number', \invoicing\InvoicingLib::getById(...)),
			\bank\Reconciliation::CASH, \bank\Reconciliation::WALLET => NULL,

		};

	}

	private static function documentNumber(\Element $eElement, string $property, \Closure $getById): ?string {

		if($eElement->empty()) {
			return NULL;
		}

		if($eElement->offsetExists($property) === FALSE) {
			$eElement = $getById($eElement['id']);
		}

		if($eElement->empty() or $eElement[$property] === NULL) {
			return NULL;
		}

		// Le numéro d'une vente est un entier, celui d'une facture un texte : la charge les range sous
		// la même forme, pour que le rendu n'ait pas à savoir de quelle source il vient.
		return (string)$eElement[$property];

	}

	private static function refClient(\api\Client $eClient): array {

		return ['id' => $eClient['id'], 'label' => $eClient['name']];

	}

	private static function refBankAccount(\bank\BankAccount $eBankAccount): ?array {

		if($eBankAccount->empty()) {
			return NULL;
		}

		$id = $eBankAccount['id'];

		if($eBankAccount->offsetExists('description') === FALSE) {
			$eBankAccount = \bank\BankAccountLib::getById($id);
		}

		return ['id' => $id, 'label' => $eBankAccount->empty() ? NULL : $eBankAccount['description']];

	}

	private static function refOperation(\journal\Operation $eOperation): ?array {

		if($eOperation->empty()) {
			return NULL;
		}

		$id = $eOperation['id'];

		if($eOperation->offsetExists('description') === FALSE) {
			$eOperation = \journal\OperationLib::getById($id);
		}

		if($eOperation->empty()) {
			return ['id' => $id];
		}

		return [
			'id' => $id,
			'label' => $eOperation['description'],
			'date' => $eOperation['date'],
			'amount' => $eOperation['amount'],
		];

	}

	private static function refAsset(\asset\Asset $eAsset): ?array {

		if($eAsset->empty()) {
			return NULL;
		}

		$id = $eAsset['id'];

		if($eAsset->offsetExists('description') === FALSE) {
			$eAsset = \asset\AssetLib::getById($id);
		}

		return ['id' => $id, 'label' => $eAsset->empty() ? NULL : $eAsset['description']];

	}

	private static function refImport(\financialYear\Import $eImport): ?array {

		if($eImport->empty()) {
			return NULL;
		}

		$id = $eImport['id'];

		if($eImport->offsetExists('filename') === FALSE) {
			$eImport = \financialYear\ImportLib::getById($id);
		}

		return ['id' => $id, 'label' => $eImport->empty() ? NULL : $eImport['filename']];

	}

	private static function refInvoicing(\invoicing\Invoicing $eInvoicing): ?array {

		if($eInvoicing->empty()) {
			return NULL;
		}

		$id = $eInvoicing['id'];

		if($eInvoicing->offsetExists('number') === FALSE) {
			$eInvoicing = \invoicing\InvoicingLib::getById($id);
		}

		return ['id' => $id, 'label' => $eInvoicing->empty() ? NULL : $eInvoicing['number']];

	}

	private static function refAddress(\pdp\Address $eAddress): ?array {

		if($eAddress->empty()) {
			return NULL;
		}

		$id = $eAddress['id'];

		if($eAddress->offsetExists('identifier') === FALSE) {
			$eAddress = \pdp\AddressLib::getById($id);
		}

		if($eAddress->empty()) {
			return ['id' => $id];
		}

		return ['id' => $id, 'label' => $eAddress['name'], 'identifier' => $eAddress['identifier']];

	}

	private static function refCash(\cash\Cash $eCash): ?array {

		if($eCash->empty()) {
			return NULL;
		}

		$id = $eCash['id'];

		if($eCash->offsetExists('description') === FALSE) {
			$eCash = \cash\CashLib::getById($id);
		}

		if($eCash->empty()) {
			return ['id' => $id];
		}

		return [
			'id' => $id,
			'label' => $eCash['description'],
			'date' => $eCash['date'],
			'amount' => $eCash['amountIncludingVat'],
			'type' => $eCash['type'],
			'register' => self::refRegister($eCash['register']),
		];

	}

	private static function refTransaction(\cash\Transaction $eTransaction): ?array {

		if($eTransaction->empty()) {
			return NULL;
		}

		$id = $eTransaction['id'];

		if($eTransaction->offsetExists('description') === FALSE) {
			$eTransaction = \cash\TransactionLib::getById($id);
		}

		if($eTransaction->empty()) {
			return ['id' => $id];
		}

		return [
			'id' => $id,
			'label' => $eTransaction['description'],
			'date' => $eTransaction['date'],
			'amount' => $eTransaction['amount'],
			'partner' => $eTransaction['partner'],
			'register' => self::refRegister($eTransaction['register']),
		];

	}

	/**
	 * Un moyen de paiement se relit par l'événement des règlements, absents de la version publique, où
	 * aucune écriture n'en cite un.
	 */
	private static function refMethod(\Element $eMethod): ?array {

		if($eMethod->empty()) {
			return NULL;
		}

		$id = $eMethod['id'];

		if($eMethod->offsetExists('name') === FALSE) {
			$eMethod = self::notify('getLogMethod', $id)[0];
		}

		return ['id' => $id, 'label' => $eMethod->empty() ? NULL : $eMethod['name']];

	}

	private static function refRegister(\cash\Register $eRegister): ?array {

		if($eRegister->empty()) {
			return NULL;
		}

		$id = $eRegister['id'];

		if(
			$eRegister->offsetExists('color') === FALSE or
			$eRegister['paymentMethod']->offsetExists('name') === FALSE
		) {
			$eRegister = \cash\RegisterLib::getById($id);
		}

		if($eRegister->empty()) {
			return ['id' => $id];
		}

		return [
			'id' => $id,
			'label' => $eRegister['paymentMethod']['name'] ?? NULL,
			'color' => $eRegister['color'],
		];

	}

}
