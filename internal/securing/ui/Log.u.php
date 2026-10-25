<?php
namespace securing;

class LogUi {

	public function getTitle(\structure\Structure $eStructure): string {

		return \main\DesignUi::getPageHeader(
			s("Historique"),
			back: \structure\StructureUi::urlStructureGeneral($eStructure),
			backLabel: $eStructure->isAmap() ? \structure\StructureUi::getSettingsGeneralName($eStructure) : ''
		);

	}

	public function getList(\Collection $cLog, int $page, int $nLog): string {

		if($cLog->empty()) {
			return '<div class="util-empty"><h4>'.s("Il n'y a rien pour le moment !").'</h4>'.s("Vous retrouverez ici toutes les actions enregistrées sur votre structure dès qu'elles auront été réalisées.").'</div>';
		}

		$h = '<table class="tr-hover tr-even">';

			$h .= '<thead>';

				$h .= '<tr>';
					$h .= '<th class="td-min-content text-center">';
						$h .= s("Module");
					$h .= '</th>';
					$h .= '<th class="text-center">';
						$h .= s("Date");
					$h .= '</th>';
					$h .= '<th>';
						$h .= s("Par");
					$h .= '</th>';
					$h .= '<th>';
						$h .= s("Action");
					$h .= '</th>';
				$h .= '</tr>';

			$h .= '</thead>';
			$h .= '<tbody>';

				foreach($cLog as $eLog) {

					$h .= '<tr>';
						$h .= '<td class="td-min-content text-center">';

							$h .= '<span class="badge badge-solid bg-'.match($eLog['module']) {
								Log::PRODUCTION => 'production',
								Log::SELLING => 'commercialisation',
								Log::ACCOUNTING => 'accounting',
								Log::SETTINGS => 'settings',
							}.'">';
							$h .= match($eLog['module']) {
								Log::PRODUCTION => s("Production"),
								Log::SELLING => s("Commercialisation"),
								Log::ACCOUNTING => s("Gestion"),
								Log::SETTINGS => s("Paramètres"),
							};
							$h .= '</span>';
						$h .= '</td>';
						$h .= '<td class="text-center">';
							$h .= \util\DateUi::numeric($eLog['doneAt']);
						$h .= '</td>';
						$h .= '<td>';
							$h .= $eLog['doneBy']->notEmpty() ? $eLog['doneBy']->getName() : s("Automatique");
						$h .= '</td>';
						$h .= '<td>';
							$h .= $this->getAction($eLog);
						$h .= '</td>';
					$h .= '</tr>';
				}

			$h .= '</tbody>';

		$h .= '</table>';

		$h .= \util\TextUi::pagination($page, $nLog / LogSetting::LOG_PER_PAGE);

		return $h;

	}

	public function getAction(Log $eLog): string {

		if($eLog['data'] === NULL) {
			return self::getLegacyComment($eLog['comment']);
		}

		return $this->getComment($eLog['event'], $eLog['data']);

	}

	private static function getLegacyComment(?string $comment): string {

		return strip_tags((string)$comment, '<b>');

	}

	public function getComment(string $event, array $data): string {

		return match($event) {

			LogSetting::EVENT_ACCOUNT_CREATE => s("Création du numéro de compte {account} pour l'exercice {financialYear}", [
				'account' => $this->account($data['account']),
				'financialYear' => $this->name($data['account']['financialYear'] ?? NULL),
			]),
			LogSetting::EVENT_ACCOUNT_UPDATE => s("Modification du numéro de compte {account} pour l'exercice {financialYear} ({properties})", [
				'account' => $this->account($data['account']),
				'financialYear' => $this->name($data['account']['financialYear'] ?? NULL),
				'properties' => $this->properties($data['properties'], \account\AccountUi::getPropertyLabel(...)),
			]),
			// Le troisième statut, DELETED, ne se poste pas : Account::properties() le refuse à la
			// construction, si bien qu'aucune charge n'en porte et que l'absence de branche est ici
			// un refus volontaire, et non un oubli.
			LogSetting::EVENT_ACCOUNT_STATUS => match($data['status']) {
				\account\Account::ACTIVE => s("Activation du numéro de compte {account} pour l'exercice {financialYear}", [
					'account' => $this->account($data['account']),
					'financialYear' => $this->name($data['account']['financialYear'] ?? NULL),
				]),
				\account\Account::INACTIVE => s("Désactivation du numéro de compte {account} pour l'exercice {financialYear}", [
					'account' => $this->account($data['account']),
					'financialYear' => $this->name($data['account']['financialYear'] ?? NULL),
				]),
			},
			LogSetting::EVENT_ACCOUNT_JOURNAL_CODE => $data['journalCode'] === NULL ?
				p(
					"Retrait du code journal de {value} numéro de compte",
					"Retrait du code journal de {value} numéros de compte",
					$data['nAccount']
				) :
				p(
					"Affectation du code journal {journalCode} à {value} numéro de compte",
					"Affectation du code journal {journalCode} à {value} numéros de compte",
					$data['nAccount'],
					['journalCode' => $this->name($data['journalCode'])]
				),
			LogSetting::EVENT_ACCOUNT_DELETE => s("Suppression du numéro de compte {class} pour l'exercice {financialYear}", [
				'class' => encode($data['class']),
				'financialYear' => $this->name($data['financialYear']),
			]),
			LogSetting::EVENT_ACCOUNT_ANCILLARY_MOVE => s("Déplacement vers le numéro de compte {account} de l'activité annexe, depuis le {parentClass} ({operations}, {cashes}, {products})", [
				'account' => $this->account($data['account']),
				'parentClass' => encode($data['parentClass']),
				'operations' => p("{value} écriture", "{value} écritures", $data['nOperation']),
				'cashes' => p("{value} ligne de cahier", "{value} lignes de cahier", $data['nCash']),
				'products' => p("{value} produit", "{value} produits", $data['nProduct']),
			]),
			LogSetting::EVENT_ACCOUNT_ANCILLARY_CANCEL => s("Annulation du déplacement vers le numéro de compte {account} de l'activité annexe, retour sur le {parentClass} ({operations}, {cashes}, {products})", [
				'account' => $this->account($data['account']),
				'parentClass' => encode($data['parentClass']),
				'operations' => p("{value} écriture", "{value} écritures", $data['nOperation']),
				'cashes' => p("{value} ligne de cahier", "{value} lignes de cahier", $data['nCash']),
				'products' => p("{value} produit", "{value} produits", $data['nProduct']),
			]),

			LogSetting::EVENT_THIRD_PARTY_CREATE => s("Création du tiers {value}", $this->name($data['thirdParty'])),
			LogSetting::EVENT_THIRD_PARTY_UPDATE => s("Modification du tiers {thirdParty} ({properties})", [
				'thirdParty' => $this->name($data['thirdParty']),
				'properties' => $this->properties($data['properties'], \thirdParty\ThirdPartyUi::getPropertyLabel(...)),
			]),
			LogSetting::EVENT_THIRD_PARTY_DELETE => s("Suppression du tiers {value}", $this->name($data['thirdParty'])),
			LogSetting::EVENT_THIRD_PARTY_ADDRESS_ADD => s("Ajout de l'adresse de facturation électronique <b>{address}</b> au tiers {thirdParty}", [
				'address' => encode($data['address']),
				'thirdParty' => $this->name($data['thirdParty']),
			]),
			LogSetting::EVENT_THIRD_PARTY_ADDRESS_DELETE => s("Suppression de l'adresse de facturation électronique <b>{address}</b> du tiers {thirdParty}", [
				'address' => encode($data['address']),
				'thirdParty' => $this->name($data['thirdParty']),
			]),

			LogSetting::EVENT_ACCOUNTING_RESET => $this->accountingReset($data['nFinancialYear'], $data['nOperation'], $data['keepConfiguration'], $data['nRegisterKept']),

			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT => s("Import FEC de l'exercice {value}", $this->name($data['financialYear'])),
			LogSetting::EVENT_FINANCIAL_YEAR_CLOSE => s("Clôture de l'exercice {value}", $this->name($data['financialYear'])),
			LogSetting::EVENT_FINANCIAL_YEAR_RECLOSE => s("Refermeture de l'exercice {value}", $this->name($data['financialYear'])),
			LogSetting::EVENT_FINANCIAL_YEAR_OPEN => s("Ouverture de l'exercice {value}", $this->name($data['financialYear'])),
			LogSetting::EVENT_FINANCIAL_YEAR_REOPEN => s("Réouverture de l'exercice {value}", $this->name($data['financialYear'])),
			LogSetting::EVENT_FINANCIAL_YEAR_CREATE => s("Création de l'exercice {value}", $this->name($data['financialYear'])),
			LogSetting::EVENT_FINANCIAL_YEAR_DELETE => s("Suppression de l'exercice {value}", $this->name($data['financialYear'])),
			LogSetting::EVENT_FINANCIAL_YEAR_FEC_GENERATION => s("Génération du fichier FEC de l'exercice {value}", $this->name($data['financialYear'])),

			LogSetting::EVENT_FINANCIAL_YEAR_UPDATE => s("Modification de l'exercice {financialYear} ({details})", [
				'financialYear' => $this->name($data['financialYear']),
				'details' => join(', ', array_map(
					fn($values, $field) => \financialYear\FinancialYearUi::p($field).' : '.encode((string)$values['old']).' → '.encode((string)$values['new']),
					$data['changes'],
					array_keys($data['changes'])
				)),
			]),

			LogSetting::EVENT_FINANCIAL_YEAR_LOCK => s("Verrouillage des écritures comptables de l'exercice {financialYear} jusqu'au {date}", [
				'financialYear' => $this->name($data['financialYear']),
				'date' => \util\DateUi::numeric($data['to'], \util\DateUi::DATE),
			]),

			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT_CREATE => s("Dépôt du fichier FEC {value} en vue d'une reprise", $this->name($data['import'])),
			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT_CANCEL => s("Abandon de la reprise du fichier FEC {value}", $this->name($data['import'])),
			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT_RULES => $data['complete'] ?
				s("Validation des correspondances de reprise du fichier FEC {value}", $this->name($data['import'])) :
				s("Correspondances de reprise du fichier FEC {value} enregistrées, certaines restent à renseigner", $this->name($data['import'])),
			LogSetting::EVENT_FINANCIAL_YEAR_IMPORT_WARNING => $this->importWarning(
				$this->name($data['import']), $data['type'], $data['confirmed']
			),

			LogSetting::EVENT_FINANCIAL_YEAR_RECONCILE => match($data['source']) {

				\financialYear\Reconciliation::MANUAL => p(
					"Rapprochement de clôture sur {check} pour l'exercice {financialYear} : {value} montant relevé sur la pièce",
					"Rapprochement de clôture sur {check} pour l'exercice {financialYear} : {value} montants relevés sur la pièce",
					$data['nAccount'],
					[
						'check' => \financialYear\FinancialYearUi::getReconciliationLabel($data['checkKey']),
						'financialYear' => $this->name($data['financialYear']),
					]
				),

				\financialYear\Reconciliation::EXTRACTED => s("Rapprochement de clôture sur {check} pour l'exercice {financialYear} : montant lu dans la pièce déposée", [
					'check' => \financialYear\FinancialYearUi::getReconciliationLabel($data['checkKey']),
					'financialYear' => $this->name($data['financialYear']),
				]),

			},

			LogSetting::EVENT_FINANCIAL_YEAR_RECONCILE_ENTRY => p(
				"Écriture de régularisation sur {check} pour la clôture de l'exercice {financialYear} ({value} écriture comptable)",
				"Écriture de régularisation sur {check} pour la clôture de l'exercice {financialYear} ({value} écritures comptables)",
				$data['nOperation'],
				[
					'check' => \financialYear\FinancialYearUi::getReconciliationLabel($data['checkKey']),
					'financialYear' => $this->name($data['financialYear']),
				]
			),

			LogSetting::EVENT_FINANCIAL_YEAR_FILE_STORE => s("Pièce déposée sur l'exercice {financialYear} : {file} ({name})", [
				'file' => $this->file($data['type'], $data['reference']),
				'financialYear' => $this->name($data['financialYear']),
				'name' => encode($data['name']),
			]),

			LogSetting::EVENT_FINANCIAL_YEAR_FILE_DELETE => s("Pièce supprimée sur l'exercice {financialYear} : {file}", [
				'file' => $this->file($data['type'], $data['reference']),
				'financialYear' => $this->name($data['financialYear']),
			]),

			LogSetting::EVENT_FINANCIAL_YEAR_ALLOCATION => p(
				"Affectation du résultat de l'exercice {financialYear} ({result}) sur {value} compte",
				"Affectation du résultat de l'exercice {financialYear} ({result}) sur {value} comptes",
				$data['nAccount'],
				[
					'financialYear' => $this->name($data['financialYear']),
					'result' => \util\TextUi::money($data['result']),
				]
			),

			LogSetting::EVENT_FINANCIAL_YEAR_INVENTORY => p(
				"Inventaire de clôture de l'exercice {financialYear} : {value} compte de stock pour {total}",
				"Inventaire de clôture de l'exercice {financialYear} : {value} comptes de stock pour {total}",
				$data['nAccount'],
				[
					'financialYear' => $this->name($data['financialYear']),
					'total' => \util\TextUi::money($data['value']),
				]
			),

			LogSetting::EVENT_FINANCIAL_YEAR_INVENTORY_DELETE => s("Ligne d'inventaire supprimée sur l'exercice {financialYear} : compte {account}", [
				'financialYear' => $this->name($data['financialYear']),
				'account' => encode($data['accountLabel']),
			]),

			LogSetting::EVENT_FINANCIAL_YEAR_INCOME => $this->income($data['year'], $data['amounts'], $data['deleted']),

			LogSetting::EVENT_BANK_ACCOUNT_UPDATE => s("Mise à jour du compte bancaire {value}", $this->name($data['bankAccount'])),
			LogSetting::EVENT_BANK_ACCOUNT_DELETE => s("Suppression du compte bancaire {value} (et des opérations et imports liés)", $this->name($data['bankAccount'])),
			LogSetting::EVENT_BANK_ACCOUNT_IMPORT => s("Import d'un relevé bancaire pour le compte bancaire {value}", $this->name($data['bankAccount'])),

			LogSetting::EVENT_CASHFLOW_ATTACH => s("Rattachement de l'opération bancaire {cashflow} {detail}", [
				'cashflow' => $this->cashflow($data['cashflow']),
				'detail' => $data['nOperation'] ? p("avec {value} écriture comptable", "avec {value} écritures comptables", $data['nOperation']) : '',
			]),
			LogSetting::EVENT_CASHFLOW_UNLINK => $data['action'] === 'delete' ?
				s("Dissociation de l'opération bancaire {value} de ses écritures comptables (avec suppression des écritures)", $this->cashflow($data['cashflow'])) :
				s("Dissociation de l'opération bancaire {value} de ses écritures comptables", $this->cashflow($data['cashflow'])),
			LogSetting::EVENT_CASHFLOW_IMPORT_CASH => s("Import de l'opération bancaire {cashflow} dans le {register}", [
				'cashflow' => $this->cashflow($data['cashflow']),
				'register' => $this->register($data['register']),
			]),

			LogSetting::EVENT_CASHFLOW_WALLET => s("Création d'une cagnotte de {amount} rattachée à l'opération bancaire {cashflow}", [
				'amount' => \util\TextUi::money($data['amount']),
				'cashflow' => $this->cashflow($data['cashflow']),
			]),
			LogSetting::EVENT_CASHFLOW_ANNOTATION_UPDATE => s("Modification d'une annotation de l'opération bancaire {cashflow}, dont le texte était « {text} »", [
				'cashflow' => $this->cashflow($data['cashflow']),
				'text' => encode($data['text']),
			]),
			LogSetting::EVENT_CASHFLOW_ANNOTATION_DELETE => s("Suppression de l'annotation de {author} sur l'opération bancaire {cashflow}, dont le texte était « {text} »", [
				'author' => new \user\User($data['author'])->getName(),
				'cashflow' => $this->cashflow($data['cashflow']),
				'text' => encode($data['text']),
			]),

			LogSetting::EVENT_PROOF_CREATE => s("Dépôt du justificatif {value}", $this->name($data['proof'])),
			LogSetting::EVENT_PROOF_ATTACH => match($data['target']) {
				'cashflow' => s("Rattachement du justificatif {proof} à l'opération bancaire {cashflow}", [
					'proof' => $this->name($data['proof']),
					'cashflow' => $this->cashflow($data['cashflow']),
				]),
				'cash' => s("Rattachement du justificatif {proof} à l'opération de caisse {cash} du cahier de caisse {register}", [
					'proof' => $this->name($data['proof']),
					'cash' => $this->cash($data['cash']),
					'register' => $this->registerLabel($data['cash']['register'] ?? NULL),
				]),
				'operationGroup' => s("Rattachement du justificatif {proof} au groupe d'écritures {operation}", [
					'proof' => $this->name($data['proof']),
					'operation' => $this->operationGroup($data['operationGroup']),
				]),
				'asset' => s("Rattachement du justificatif {proof} à l'immobilisation {asset}", [
					'proof' => $this->name($data['proof']),
					'asset' => $this->name($data['asset']),
				]),
			},
			LogSetting::EVENT_PROOF_DETACH => match($data['target']) {
				'cashflow' => s("Détachement du justificatif {proof} de l'opération bancaire {cashflow}", [
					'proof' => $this->name($data['proof']),
					'cashflow' => $this->cashflow($data['cashflow']),
				]),
				'cash' => s("Détachement du justificatif {proof} de l'opération de caisse {cash} du cahier de caisse {register}", [
					'proof' => $this->name($data['proof']),
					'cash' => $this->cash($data['cash']),
					'register' => $this->registerLabel($data['cash']['register'] ?? NULL),
				]),
				'operationGroup' => s("Détachement du justificatif {proof} du groupe d'écritures {operation}", [
					'proof' => $this->name($data['proof']),
					'operation' => $this->operationGroup($data['operationGroup']),
				]),
				'asset' => s("Détachement du justificatif {proof} de l'immobilisation {asset}", [
					'proof' => $this->name($data['proof']),
					'asset' => $this->name($data['asset']),
				]),
			},
			LogSetting::EVENT_PROOF_DISCARD => s("Mise à l'écart du justificatif {value}", $this->name($data['proof'])),
			LogSetting::EVENT_PROOF_UNDISCARD => s("Retour du justificatif {value} parmi les justificatifs à rattacher", $this->name($data['proof'])),
			LogSetting::EVENT_PROOF_TRASH => s("Mise à la corbeille du justificatif {value}", $this->name($data['proof'])),
			LogSetting::EVENT_PROOF_UNTRASH => s("Sortie de la corbeille du justificatif {value}, de retour parmi les justificatifs", $this->name($data['proof'])),
			LogSetting::EVENT_PROOF_DELETE => s("Suppression définitive du justificatif {value}", $this->name($data['proof'])),
			LogSetting::EVENT_PROOF_PURGE => s("Suppression définitive du justificatif {value} au vidage de la corbeille", $this->name($data['proof'])),
			LogSetting::EVENT_PROOF_ERASE => s("Effacement du fichier du justificatif {value} en fin de conservation", $this->name($data['proof'])),

			LogSetting::EVENT_INVOICING_EVENT_SENT => s("Envoi d'un événement (statut {status}) pour la facture électronique {number} sur la plateforme agréée", [
				'number' => '<b>'.encode($data['invoicing']['number']).'</b>',
				'status' => new \invoicing\EventUi()->getShortStatusText($data['status']),
			]),
			LogSetting::EVENT_INVOICING_EVENT_WAITING => s("Demande d'envoi d'un événement (statut {status}) pour la facture électronique {number}, en attente de la plateforme agréée", [
				'number' => '<b>'.encode($data['invoicing']['number']).'</b>',
				'status' => new \invoicing\EventUi()->getShortStatusText($data['status']),
			]),
			LogSetting::EVENT_INVOICING_SEND => s("Envoi de la facture électronique <b>{value}</b> sur la plateforme agréée", encode($data['number'])),

			LogSetting::EVENT_INVOICING_ACCOUNT => $data['accountNumber'] === NULL ? p(
				"Retrait du compte comptable sur {value} ligne de facture reçue",
				"Retrait du compte comptable sur {value} lignes de facture reçue",
				$data['nLine']
			) : p(
				"Affectation du compte {account} à {value} ligne de facture reçue",
				"Affectation du compte {account} à {value} lignes de facture reçue",
				$data['nLine'],
				['account' => '<b>'.encode($data['accountNumber']).'</b>']
			),
			LogSetting::EVENT_INVOICING_PAYMENT => $data['nPayment'] === 0 ?
				s("Retrait de tous les règlements de la facture reçue {value}", $this->name($data['invoicing'])) :
				p(
					"Mise à jour des règlements de la facture reçue {invoicing} : {value} règlement pour {amount}",
					"Mise à jour des règlements de la facture reçue {invoicing} : {value} règlements pour {amount}",
					$data['nPayment'],
					['amount' => \util\TextUi::money($data['amount']), 'invoicing' => $this->name($data['invoicing'])]
				),
			LogSetting::EVENT_INVOICING_COMMENT => s("Modification du commentaire de la facture reçue {value}", $this->name($data['invoicing'])),

			LogSetting::EVENT_DEFERRAL_CREATE => $data['type'] === 'charge' ?
				s("Création d'une Charge constatée d'avance #{deferral} pour l'écriture comptable {operation} ({amount})", [
					'deferral' => $data['deferral']['id'],
					'amount' => \util\TextUi::money($data['deferral']['amount']),
					'operation' => $this->name($data['deferral']['operation']),
				]) :
				s("Création d'un Produit constaté d'avance #{deferral} pour l'écriture comptable {operation} ({amount})", [
					'deferral' => $data['deferral']['id'],
					'amount' => \util\TextUi::money($data['deferral']['amount']),
					'operation' => $this->name($data['deferral']['operation']),
				]),
			LogSetting::EVENT_DEFERRAL_DELETE => s("Suppression d'une charge constatée d'avance ou d'un produit constaté d'avance"),

			LogSetting::EVENT_PREACCOUNTING_IMPORT => s("Import en comptabilité du mois de <b>{month} {year}</b>", [
				'month' => \util\DateUi::getMonthName(mb_substr($data['month'], 5, 2)),
				'year' => mb_substr($data['month'], 0, 4),
			]),

			LogSetting::EVENT_PREACCOUNTING_IGNORE => $this->preaccountingIgnore(
				$data['type'], $data['label'], $data['amount'], $data['date']
			),
			LogSetting::EVENT_PREACCOUNTING_IGNORE_ALL => s("Mise à l'écart de la comptabilité du {from} au {to} : {payments}, {sales}", $this->period($data['from'], $data['to']) + [
				'payments' => p("{value} règlement", "{value} règlements", $data['nPayment']),
				'sales' => p("{value} vente", "{value} ventes", $data['nSale']),
			]),
			LogSetting::EVENT_PREACCOUNTING_SUGGESTION_IGNORE => s("Suggestion de rapprochement écartée pour l'opération bancaire {value}", $this->cashflow($data['cashflow'])),
			LogSetting::EVENT_PREACCOUNTING_SUGGESTION_METHOD => s("Mode de règlement d'une suggestion de rapprochement modifié pour l'opération bancaire {cashflow} : {method}", [
				'cashflow' => $this->cashflow($data['cashflow']),
				'method' => $data['method'] === NULL ? s("aucun") : $this->name($data['method']),
			]),

			LogSetting::EVENT_PDP_GET_ACCESS_TOKEN => s("Récupération d'un jeton d'accès pour la plateforme agréée"),
			LogSetting::EVENT_PDP_GET_REFRESH_TOKEN => s("Rafraîchissement du jeton d'accès pour la plateforme agréée"),

			LogSetting::EVENT_PDP_PARTNER_OPT_IN => $data['optIn'] ?
				s("Réception des factures électroniques activée sur la plateforme agréée") :
				s("Réception des factures électroniques désactivée sur la plateforme agréée"),
			LogSetting::EVENT_PDP_ADDRESS_CREATE => s("Création de l'adresse de facturation électronique {value}", $this->address($data['address'])),
			LogSetting::EVENT_PDP_ADDRESS_UPDATE => s("Modification de l'adresse de facturation électronique {address} ({properties})", [
				'address' => $this->address($data['address']),
				'properties' => $this->properties($data['properties'], \pdp\AddressUi::getPropertyLabel(...)),
			]),
			LogSetting::EVENT_PDP_ADDRESS_DELETE => s("Suppression de l'adresse de facturation électronique {value}", $this->address($data['address'])),
			LogSetting::EVENT_PDP_INVOICING_RECEIVED => p(
				"Réception de {value} facture électronique depuis la plateforme agréée",
				"Réception de {value} factures électroniques depuis la plateforme agréée",
				$data['nInvoicing']
			),
			LogSetting::EVENT_PDP_EVENT_RECEIVED => p(
				"Réception de {value} statut de facture électronique depuis la plateforme agréée",
				"Réception de {value} statuts de factures électroniques depuis la plateforme agréée",
				$data['nEvent']
			),
			LogSetting::EVENT_PDP_PAYMENT_CREATE => $data['paid'] ?
				s("Enregistrement du règlement de la facture reçue {value}, que son émetteur annonce encaissé sur la plateforme agréée", $this->name($data['invoicing'])) :
				s("Enregistrement du règlement prévu de la facture reçue {value}, d'après le moyen de paiement que porte la facture", $this->name($data['invoicing'])),

			LogSetting::EVENT_OPERATION_DELETE => s("Suppression d'un groupe d'écritures comptables"),
			LogSetting::EVENT_OPERATION_LETTER => p(
				"Lettrage de {value} écriture comptable sous la lettre {lettering}",
				"Lettrage de {value} écritures comptables sous la lettre {lettering}",
				$data['nOperation'],
				['lettering' => encode($data['lettering'])]
			),
			LogSetting::EVENT_OPERATION_UNLETTER => p(
				"Délettrage de {value} écriture comptable, lettre {lettering}",
				"Délettrage de {value} écritures comptables, lettre {lettering}",
				$data['nOperation'],
				['lettering' => encode($data['lettering'])]
			),
			LogSetting::EVENT_OPERATION_QUICK => s("Modification du champ {property} de l'écriture comptable {operation}", [
				'property' => \journal\OperationUi::p($data['property']),
				'operation' => $this->name($data['operation']),
			]),
			LogSetting::EVENT_OPERATION_LETTER_AUTO => p(
				"Lettrage automatique d'un groupe d'écritures comptables sous la lettre {letterings}",
				"Lettrage automatique de {value} groupes d'écritures comptables sous les lettres {letterings}",
				count($data['letterings']),
				['letterings' => encode(implode(', ', $data['letterings']))]
			),
			LogSetting::EVENT_OPERATION_UNLETTER_AUTO => p(
				"Délettrage de {value} écriture comptable, lettre {letterings}, après la modification ou la suppression d'une écriture du groupe",
				"Délettrage de {value} écritures comptables, lettres {letterings}, après la modification ou la suppression d'une écriture du groupe",
				$data['nOperation'],
				['letterings' => encode(implode(', ', $data['letterings']))]
			),
			LogSetting::EVENT_OPERATION_UPDATE_COLLECTION => p(
				"Modification en masse de {value} écriture comptable ({property} : {newValue})",
				"Modification en masse de {value} écritures comptables ({property} : {newValue})",
				$data['nOperation'],
				['property' => \journal\OperationUi::p($data['property']), 'newValue' => encode((string)$data['value'])]
			),
			LogSetting::EVENT_OPERATION_CREATE => $this->operationWrite($data, ($data['for'] ?? NULL) === 'update'),
			LogSetting::EVENT_OPERATION_UPDATE => $this->operationWrite($data, TRUE),

			LogSetting::EVENT_JOURNAL_CODE_CREATE => s("Création du code journal {value}", $this->name($data['journalCode'])),
			LogSetting::EVENT_JOURNAL_CODE_UPDATE => s("Modification du code journal {journalCode} ({properties})", [
				'journalCode' => $this->name($data['journalCode']),
				'properties' => $this->properties($data['properties'], \journal\JournalCodeUi::getPropertyLabel(...)),
			]),
			LogSetting::EVENT_JOURNAL_CODE_DELETE => s("Suppression du code journal {value} (les numéros de compte qui y étaient affectés n'en ont plus)", $this->name($data['journalCode'])),

			LogSetting::EVENT_PATTERN_CREATE => $data['fromOperations'] ?
				s("Création de la trame {value} à partir d'un groupe d'écritures comptables", $this->name($data['pattern'])) :
				s("Création de la trame {value}", $this->name($data['pattern'])),
			LogSetting::EVENT_PATTERN_UPDATE => s("Modification de la trame {pattern} ({properties})", [
				'pattern' => $this->name($data['pattern']),
				'properties' => $this->properties($data['properties'], \journal\PatternUi::getPropertyLabel(...)),
			]),
			LogSetting::EVENT_PATTERN_STATUS => match($data['status']) {
				\journal\Pattern::ACTIVE => s("Activation de la trame {value}", $this->name($data['pattern'])),
				\journal\Pattern::INACTIVE => s("Désactivation de la trame {value}", $this->name($data['pattern'])),
			},
			LogSetting::EVENT_PATTERN_DELETE => s("Suppression de la trame {value}", $this->name($data['pattern'])),

			// Les lignes écrites avant que la contrepartie ne soit journalisée n'en portent pas : leur
			// phrase se limite à l'opération bancaire, la charge d'un journal ne se réécrivant pas.
			LogSetting::EVENT_RECONCILIATE_DO => ($data['reconciliation'] ?? NULL) === NULL ?
				s("Rapprochement bancaire de l'opération bancaire {value}", $this->cashflow($data['cashflow'])) :
				s("Rapprochement bancaire de l'opération bancaire {cashflow} avec {document} pour {amount}", [
					'cashflow' => $this->cashflow($data['cashflow']),
					'document' => $this->document($data['reconciliation']),
					'amount' => \util\TextUi::money($data['reconciliation']['amount']),
				]),
			LogSetting::EVENT_RECONCILIATION_CANCEL => ($data['reconciliation'] ?? NULL) === NULL ?
				s("Annulation du rapprochement de l'opération bancaire {value}", $this->cashflow($data['cashflow'])) :
				s("Annulation du rapprochement de l'opération bancaire {cashflow} avec {document} pour {amount}", [
					'cashflow' => $this->cashflow($data['cashflow']),
					'document' => $this->document($data['reconciliation']),
					'amount' => \util\TextUi::money($data['reconciliation']['amount']),
				]),

			LogSetting::EVENT_CASHFLOW_DELETE => s("Suppression de l'opération bancaire {value}", $this->cashflow($data['cashflow'])),
			LogSetting::EVENT_CASHFLOW_UNDELETE => s("Annulation de la suppression de l'opération bancaire {value}", $this->cashflow($data['cashflow'])),

			LogSetting::EVENT_VAT_CERFA_SAVE => s("Enregistrement de la déclaration de TVA (période du {from} au {to})", $this->period($data['from'], $data['to'])),
			LogSetting::EVENT_VAT_CERFA_STATUS => s("Mise à jour de la déclaration de TVA (période du {from} au {to}), nouveau statut : {status}", $this->period($data['from'], $data['to']) + [
				'status' => \vat\DeclarationUi::p('status')->values[$data['status']],
			]),
			LogSetting::EVENT_VAT_CERFA_OPERATIONS => s("Comptabilisation automatique de la déclaration de TVA (période du {from} au {to})", $this->period($data['from'], $data['to'])),
			LogSetting::EVENT_VAT_CERFA_RESET => s("Remise à zéro de la déclaration de TVA (période du {from} au {to})", $this->period($data['from'], $data['to'])),
			LogSetting::EVENT_VAT_REFUND_REQUEST => s("Demande de remboursement de TVA de {amount} (période du {from} au {to}) : {method}", $this->period($data['from'], $data['to']) + [
				'amount' => \util\TextUi::money($data['amount']),
				'method' => \vat\DeclarationUi::p('refundMethod')->values[$data['method']],
			]),
			LogSetting::EVENT_VAT_REFUND_RECEIVED => $data['refundReceived']
				? s("Remboursement de TVA de la période du {from} au {to} indiqué comme reçu", $this->period($data['from'], $data['to']))
				: s("Remboursement de TVA de la période du {from} au {to} indiqué comme pas encore reçu", $this->period($data['from'], $data['to'])),
			LogSetting::EVENT_VAT_RECTIFIED => s("Ventes rattrapées par la déclaration de TVA de la période du {from} au {to} indiquées comme déclarées par une rectificative", $this->period($data['from'], $data['to'])),
			LogSetting::EVENT_VAT_PAYMENT_UPDATE => $this->vatPayment($data['method'], $data['bankAccount']),
			LogSetting::EVENT_EDI_MANDATE_CREATE => s("Mandat de télédéclaration de la TVA donné à SAS OTF (texte en version {value})", $data['version']),
			LogSetting::EVENT_EDI_MANDATE_REVOKE => s("Mandat de télédéclaration de la TVA révoqué (texte en version {value})", $data['version']),
			LogSetting::EVENT_EDI_TRANSMISSION_CREATE => $this->ediTransmission($data['type'], $data['from'], $data['to']),

			LogSetting::EVENT_TAX_RETURN_DEPOSIT => s("Liasse fiscale de l'exercice {financialYear} marquée déposée le {date}", [
				'financialYear' => $this->name($data['financialYear']),
				'date' => \util\DateUi::numeric($data['depositedOn'], \util\DateUi::DATE),
			]),
			LogSetting::EVENT_TAX_RETURN_DEPOSIT_DATE => s("Date de dépôt de la liasse fiscale de l'exercice {financialYear} corrigée du {old} au {new}", [
				'financialYear' => $this->name($data['financialYear']),
				'old' => \util\DateUi::numeric($data['old'], \util\DateUi::DATE),
				'new' => \util\DateUi::numeric($data['new'], \util\DateUi::DATE),
			]),
			LogSetting::EVENT_TAX_RETURN_DRAFT => s("Liasse fiscale de l'exercice {financialYear} revenue au brouillon, le dépôt du {date} restant dans l'historique", [
				'financialYear' => $this->name($data['financialYear']),
				'date' => \util\DateUi::numeric($data['depositedOn'], \util\DateUi::DATE),
			]),

			LogSetting::EVENT_ARCHIVE_GENERATION => s("Génération des documents de l'archive {id} pour l'exercice {year}", [
				'id' => $data['archive'],
				'year' => $this->name($data['financialYear']),
			]),

			LogSetting::EVENT_CASH_REGISTER_CREATE => s("Création du cahier de caisse {value}", $this->registerLabel($data['register'])),
			LogSetting::EVENT_CASH_REGISTER_UPDATE => s("Modification du cahier de caisse {value}", $this->registerLabel($data['register'])),
			LogSetting::EVENT_CASH_REGISTER_DELETE => $data['nCash'] === 0 ?
				s("Suppression du cahier de caisse {value}", $this->registerLabel($data['register'])) :
				p(
					"Suppression du cahier de caisse {register} et de son opération",
					"Suppression du cahier de caisse {register} et de ses {value} opérations",
					$data['nCash'],
					['register' => $this->registerLabel($data['register'])]
				),
			LogSetting::EVENT_CASH_REGISTER_CLOSE => s("Clôture du cahier de caisse {register} au {date}", [
				'register' => $this->registerLabel($data['register']),
				'date' => \util\DateUi::numeric($data['to'], \util\DateUi::DATE),
			]),
			LogSetting::EVENT_CASH_REGISTER_LINK => s("Connexion du cahier de caisse {register} au compte {partner}", [
				'register' => $this->registerLabel($data['register']),
				'partner' => \cash\TransactionUi::getPartnerName($data['partner']),
			]),
			LogSetting::EVENT_CASH_REGISTER_UNLINK => s("Déconnexion du cahier de caisse {register} du compte {partner}", [
				'register' => $this->registerLabel($data['register']),
				'partner' => \cash\TransactionUi::getPartnerName($data['partner']),
			]),

			// Une ligne de solde initial n'a pas de description (les 4 de la structure 7 sont à NULL) :
			// la phrase nomme le montant et la date, et non l'opération, qui n'aurait que son numéro
			// à montrer. Elle s'en passe quand la charge ne les porte pas, plutôt que d'en inventer.
			LogSetting::EVENT_CASH_INITIAL => ($data['cash']['date'] ?? NULL) === NULL ?
				s("Solde initial du cahier de caisse {value}", $this->registerLabel($data['cash']['register'] ?? NULL)) :
				s("Solde initial du cahier de caisse {register} : {amount} au {date}", [
					'register' => $this->registerLabel($data['cash']['register'] ?? NULL),
					'amount' => \util\TextUi::money($data['cash']['amount']),
					'date' => \util\DateUi::numeric($data['cash']['date'], \util\DateUi::DATE),
				]),
			LogSetting::EVENT_CASH_BALANCE => s("Régularisation du solde du cahier de caisse {register} : {value}", [
				'register' => $this->registerLabel($data['cash']['register'] ?? NULL),
				'value' => $this->cash($data['cash']),
			]),
			LogSetting::EVENT_CASH_CREATE => s("Saisie de l'opération de caisse {cash} dans le cahier de caisse {register}", [
				'cash' => $this->cash($data['cash']),
				'register' => $this->registerLabel($data['cash']['register'] ?? NULL),
			]),
			LogSetting::EVENT_CASH_UPDATE => s("Modification de l'opération de caisse {cash} dans le cahier de caisse {register}", [
				'cash' => $this->cash($data['cash']),
				'register' => $this->registerLabel($data['cash']['register'] ?? NULL),
			]),
			LogSetting::EVENT_CASH_CASHFLOW => $this->cashCashflow($data['cash'], $data['before'], $data['after']),
			LogSetting::EVENT_CASH_DELETE => s("Suppression de l'opération de caisse {cash} du cahier de caisse {register}", [
				'cash' => $this->cash($data['cash']),
				'register' => $this->registerLabel($data['cash']['register'] ?? NULL),
			]),
			LogSetting::EVENT_CASH_VALIDATE => p(
				"Validation d'une opération du cahier de caisse {register}, clos jusqu'au {date}",
				"Validation de {value} opérations du cahier de caisse {register}, clos jusqu'au {date}",
				$data['nCash'],
				[
					'register' => $this->registerLabel($data['register']),
					'date' => \util\DateUi::numeric($data['to'], \util\DateUi::DATE),
				]
			),

			LogSetting::EVENT_CASH_IMPORT => $data['automatic'] ? p(
				"{value} transaction {partner} importée automatiquement dans le cahier de caisse {register}",
				"{value} transactions {partner} importées automatiquement dans le cahier de caisse {register}",
				$data['imported'],
				[
					'partner' => \cash\TransactionUi::getPartnerName($data['partner']),
					'register' => $this->registerLabel($data['register']),
				]
			) : p(
				"{value} transaction {partner} importée dans le cahier de caisse {register}",
				"{value} transactions {partner} importées dans le cahier de caisse {register}",
				$data['imported'],
				[
					'partner' => \cash\TransactionUi::getPartnerName($data['partner']),
					'register' => $this->registerLabel($data['register']),
				]
			),
			LogSetting::EVENT_CASH_IMPORT_RECONCILIATE => p(
				"{value} rapprochement fait dans le cahier de caisse {register} à la synchronisation {partner}",
				"{value} rapprochements faits dans le cahier de caisse {register} à la synchronisation {partner}",
				$data['nReconciled'],
				[
					'partner' => \cash\TransactionUi::getPartnerName($data['partner']),
					'register' => $this->registerLabel($data['register']),
				]
			),
			LogSetting::EVENT_CASH_TRANSACTION_RECONCILIATE => s("Rapprochement de la transaction {value}", $this->transaction($data['transaction'])),
			LogSetting::EVENT_CASH_TRANSACTION_CANCEL => s("Annulation du rapprochement de la transaction {value}", $this->transaction($data['transaction'])),
			LogSetting::EVENT_CASH_SUGGESTION_IGNORE => $data['register'] === NULL ? p(
				"{value} règlement écarté des cahiers de caisse",
				"{value} règlements écartés des cahiers de caisse",
				$data['nSuggestion']
			) : p(
				"{value} règlement écarté du cahier de caisse {register}",
				"{value} règlements écartés du cahier de caisse {register}",
				$data['nSuggestion'],
				['register' => $this->registerLabel($data['register'])]
			),
			LogSetting::EVENT_CASH_SUGGESTION_IMPORT => p(
				"{value} règlement importé dans le cahier de caisse {register}",
				"{value} règlements importés dans le cahier de caisse {register}",
				$data['nSuggestion'],
				['register' => $this->registerLabel($data['register'])]
			),
			LogSetting::EVENT_CASH_SUGGESTION_RESTORE => $data['register'] === NULL ?
				s("Règlement écarté remis à traiter dans les cahiers de caisse") :
				s("Règlement écarté remis à traiter dans le cahier de caisse {register}", ['register' => $this->registerLabel($data['register'])]),
			LogSetting::EVENT_CASH_ARCHIVE => s("Génération de l'archive {id} du cahier de caisse (du {from} au {to})", [
				'id' => $data['archive'],
			] + $this->period($data['from'], $data['to'])),
			LogSetting::EVENT_CASH_ARCHIVE_INTEGRITY => s("L'archive {value} du cahier de caisse ne correspond plus à la signature posée à sa création", $data['archive']),
			LogSetting::EVENT_SELLING_ARCHIVE => s("Génération de l'archive {id} des ventes (du {from} au {to})", [
				'id' => $data['archive'],
			] + $this->period($data['from'], $data['to'])),
			LogSetting::EVENT_SELLING_ARCHIVE_INTEGRITY => s("L'archive {value} des ventes ne correspond plus à la signature posée à sa création", $data['archive']),
			LogSetting::EVENT_SELLING_MARKET_DEVICE_TICKET_REFUSED => s("Ticket de la vente {sequence} refusé pour le poste {key} du marché {market} ({fail})", [
				'sequence' => $data['sequence'],
				'key' => encode($data['key']),
				'market' => $data['market'],
				'fail' => encode($data['fail']),
			]),

			LogSetting::EVENT_ENABLE_BANKING_CONNECTED => s("Connexion du compte bancaire {value} avec la banque", $this->name($data['bankAccount'])),
			LogSetting::EVENT_ENABLE_BANKING_IMPORT => $data['automatic'] ? p(
				"{value} opération bancaire synchronisée automatiquement pour le compte {account} ({dates})",
				"{value} opérations bancaires synchronisées automatiquement pour le compte {account} ({dates})",
				$data['imported'],
				['account' => $this->name($data['bankAccount']), 'dates' => $this->dates($data)]
			) : p(
				"{value} opération bancaire synchronisée pour le compte {account} ({dates})",
				"{value} opérations bancaires synchronisées pour le compte {account} ({dates})",
				$data['imported'],
				['account' => $this->name($data['bankAccount']), 'dates' => $this->dates($data)]
			),
			LogSetting::EVENT_ENABLE_BANKING_BALANCE => s("Mise à jour du solde du compte bancaire {value}", $this->name($data['bankAccount'])),
			LogSetting::EVENT_ENABLE_BANKING_REVOKED => s("Suppression de la connexion avec le compte bancaire {value}", $this->name($data['bankAccount'])),

			LogSetting::ASSET_CREATE => $data['grant'] ?
				s("Création de la subvention {value}", $this->name($data['asset'])) :
				s("Création de l'immobilisation {value}", $this->name($data['asset'])),
			LogSetting::ASSET_UPDATE => $data['grant'] ?
				s("Modification de la subvention {value}", $this->name($data['asset'])) :
				s("Modification de l'immobilisation {value}", $this->name($data['asset'])),
			LogSetting::ASSET_DELETE => $data['grant'] ?
				s("Suppression de la subvention {value}", $this->name($data['asset'])) :
				s("Suppression de l'immobilisation {value}", $this->name($data['asset'])),
			LogSetting::ASSET_ATTACH => $data['grant'] ?
				s("Rattachement de subvention à l'écriture comptable {value}", $this->name($data['operation'])) :
				s("Rattachement d'immobilisation à l'écriture comptable {value}", $this->name($data['operation'])),
			LogSetting::ASSET_UNATTACH => $data['grant'] ?
				s("Dissociation de la subvention {asset} de l'écriture comptable {operation}", [
					'asset' => $this->name($data['asset']), 'operation' => $this->name($data['operation']),
				]) :
				s("Dissociation de l'immobilisation {asset} de l'écriture comptable {operation}", [
					'asset' => $this->name($data['asset']), 'operation' => $this->name($data['operation']),
				]),

			LogSetting::ASSET_DISPOSE => $this->assetDispose(
				$this->name($data['asset']), $data['grant'], $data['reason'], $data['amount'], $data['partial']
			),

			LogSetting::ASSET_SALE => ($data['vatRate'] === NULL) ?
				s("Écriture de vente de l'immobilisation {asset} ({amount} HT)", [
					'asset' => $this->name($data['asset']), 'amount' => \util\TextUi::money($data['amount']),
				]) :
				s("Écriture de vente de l'immobilisation {asset} ({amount} HT, TVA à {vatRate} %)", [
					'asset' => $this->name($data['asset']), 'amount' => \util\TextUi::money($data['amount']), 'vatRate' => \util\TextUi::number($data['vatRate']),
				]),

			LogSetting::EVENT_API_KEY_CREATE => s("Création de la clé d'API {value}", '<b>'.encode($data['name']).'</b>'),
			LogSetting::EVENT_API_KEY_REVOKE => s("Révocation de la clé d'API {value}", $this->name($data['accessToken'])),
			LogSetting::EVENT_API_APPLICATION_AUTHORIZE => s("Autorisation de l'application {value} à accéder à l'API", $this->name($data['client'])),
			LogSetting::EVENT_API_APPLICATION_REVOKE => s("Révocation de l'accès à l'API donné par {user} à l'application {client}", [
				'user' => \user\UserUi::name(new \user\User($data['user'])),
				'client' => $this->name($data['client']),
			]),

			default => throw new \UnsupportedException('Unknown event for log comment'),

		};

	}

	/**
	 * Les journaux antérieurs à EVENT_OPERATION_UPDATE portent tous l'événement de création, la
	 * modification n'étant marquée que par leur clé 'for' : elle est encore lue à l'appel pour qu'ils
	 * gardent le libellé sous lequel ils ont été écrits.
	 */
	private function operationWrite(array $data, bool $isUpdate): string {

		if($data['cashflow'] !== NULL) {

			if($isUpdate) {
				return p(
					"Mise à jour de {value} écriture comptable pour l'opération bancaire {cashflow}",
					"Mise à jour de {value} écritures comptables pour l'opération bancaire {cashflow}",
					$data['nOperation'],
					['cashflow' => $this->cashflow($data['cashflow'])]
				);
			}

			return p(
				"Création de {value} écriture comptable pour l'opération bancaire {cashflow}",
				"Création de {value} écritures comptables pour l'opération bancaire {cashflow}",
				$data['nOperation'],
				['cashflow' => $this->cashflow($data['cashflow'])]
			);

		}

		if($isUpdate) {
			return p("Mise à jour de {value} écriture comptable", "Mise à jour de {value} écritures comptables", $data['nOperation']);
		}

		return p("Création de {value} écriture comptable", "Création de {value} écritures comptables", $data['nOperation']);

	}

	private function accountingReset(int $nFinancialYear, int $nOperation, bool $keepConfiguration, int $nRegisterKept): string {

		$financialYears = p("{value} exercice", "{value} exercices", $nFinancialYear);
		$operations = p("{value} écriture", "{value} écritures", $nOperation);
		$registers = p("{value} cahier de caisse", "{value} cahiers de caisse", $nRegisterKept);

		if($keepConfiguration) {

			return ($nRegisterKept > 0) ?
				s("Réinitialisation de la comptabilité, avec suppression de {financialYears} et de {operations}, réglages et {registers} conservés", ['financialYears' => $financialYears, 'operations' => $operations, 'registers' => $registers]) :
				s("Réinitialisation de la comptabilité, avec suppression de {financialYears} et de {operations}, réglages conservés", ['financialYears' => $financialYears, 'operations' => $operations]);

		} else {

			return ($nRegisterKept > 0) ?
				p("Réinitialisation de la comptabilité, avec suppression de {financialYears}, de {operations} et des réglages, {registers} conservé", "Réinitialisation de la comptabilité, avec suppression de {financialYears}, de {operations} et des réglages, {registers} conservés", $nRegisterKept, ['financialYears' => $financialYears, 'operations' => $operations, 'registers' => $registers]) :
				s("Réinitialisation de la comptabilité, avec suppression de {financialYears}, de {operations} et des réglages", ['financialYears' => $financialYears, 'operations' => $operations]);

		}

	}

	private function preaccountingIgnore(string $type, ?string $label, ?float $amount, ?string $date): string {

		if($type === 'customer') {
			return s("Client {value} écarté de la comptabilité", '<b>'.encode((string)$label).'</b>');
		}

		$money = \util\TextUi::money($amount);
		$day = \util\DateUi::numeric((string)$date, \util\DateUi::DATE);

		return match($type) {

			'payment' => s("Règlement de {amount} du {date} écarté de la comptabilité", [
				'amount' => $money,
				'date' => $day,
			]),

			'sale' => $label === NULL ?
				s("Vente de {amount} du {date} écartée de la comptabilité", [
					'amount' => $money,
					'date' => $day,
				]) :
				s("Vente {label} de {amount} du {date} écartée de la comptabilité", [
					'label' => '<b>'.encode($label).'</b>',
					'amount' => $money,
					'date' => $day,
				]),

		};

	}

	private function income(int $year, array $amounts, array $deleted): string {

		$details = [];

		foreach($amounts as $category => $amount) {
			$details[] = s("{amount} TTC en « {category} »", [
				'amount' => \util\TextUi::money($amount['includingVat']),
				'category' => \journal\IncomeBookUi::getCategoryName($category),
			]);
		}

		foreach($deleted as $category) {
			$details[] = s("« {category} » retirée", ['category' => \journal\IncomeBookUi::getCategoryName($category)]);
		}

		if($amounts === []) {
			return s("Retrait des recettes de {year} saisies à la main ({details})", ['year' => $year, 'details' => join(', ', $details)]);
		}

		return s("Saisie à la main des recettes de {year} ({details})", ['year' => $year, 'details' => join(', ', $details)]);

	}

	private function importWarning(string $import, string $type, bool $confirmed): string {

		return match($type) {

			'opening' => $confirmed ?
				s("Reprise du fichier FEC {value} confirmée malgré les écritures d'à-nouveau qu'il porte", $import) :
				s("Reprise du fichier FEC {value} abandonnée devant les écritures d'à-nouveau qu'il porte", $import),

			'resume' => $confirmed ?
				s("Reprise du fichier FEC {value} confirmée malgré l'écart sur les amortissements repris", $import) :
				s("Reprise du fichier FEC {value} abandonnée devant l'écart sur les amortissements repris", $import),

		};

	}

	private function assetDispose(string $asset, bool $grant, ?string $reason, ?float $amount, bool $partial): string {

		$reasonLabel = $reason === NULL ? s("motif non précisé") : \asset\AssetUi::p('disposalReason')->values[$reason];

		$detail = $amount === NULL ? $reasonLabel : s("{reason}, {amount}", [
			'reason' => $reasonLabel,
			'amount' => \util\TextUi::money($amount),
		]);

		if($grant) {

			return $partial ?
				s("Sortie partielle de la subvention {asset} ({detail})", ['asset' => $asset, 'detail' => $detail]) :
				s("Sortie de la subvention {asset} ({detail})", ['asset' => $asset, 'detail' => $detail]);

		}

		return $partial ?
			s("Sortie partielle de l'immobilisation {asset} ({detail})", ['asset' => $asset, 'detail' => $detail]) :
			s("Sortie de l'immobilisation {asset} ({detail})", ['asset' => $asset, 'detail' => $detail]);

	}

	private function address(?array $ref): string {

		$name = $this->name($ref);

		if($ref === NULL or ($ref['identifier'] ?? NULL) === NULL) {
			return $name;
		}

		return $name.' <span class="color-muted">'.encode($ref['identifier']).'</span>';

	}

	private function account(?array $ref): string {

		if($ref === NULL) {
			return '<span class="color-muted">'.s("(supprimé)").'</span>';
		}

		if(($ref['class'] ?? NULL) === NULL) {
			return s("n°{value}", $ref['id']);
		}

		return '<b>'.encode($ref['class']).'</b> '.encode($ref['label']);

	}

	private function properties(array $properties, \Closure $getLabel): string {

		return join(', ', array_map($getLabel, $properties));

	}

	private function file(string $type, string $reference): string {

		$label = \financialYear\FinancialYearUi::getFileLabel($type);

		if($reference === '') {
			return $label;
		}

		return $label.' '.s("du compte {value}", encode($reference));

	}

	private function name(?array $ref): string {

		if($ref === NULL) {
			return '<span class="color-muted">'.s("(supprimé)").'</span>';
		}

		if(($ref['label'] ?? NULL) === NULL) {
			return s("n°{value}", $ref['id']);
		}

		return '<b>'.encode($ref['label']).'</b>';

	}

	private function cashflow(?array $ref): string {

		$name = $this->name($ref);

		if($ref === NULL or ($ref['label'] ?? NULL) === NULL) {
			return $name;
		}

		return $name.' <span class="color-muted">'.s("{amount} du {date}", [
			'amount' => \util\TextUi::money($ref['amount']),
			'date' => \util\DateUi::numeric($ref['date'], \util\DateUi::DATE),
		]).'</span>';

	}

	private function operationGroup(array $ref): string {

		if(($ref['label'] ?? NULL) === NULL) {
			return '<span class="color-muted">'.s("(supprimé)").'</span>';
		}

		return '<b>'.encode($ref['label']).'</b> <span class="color-muted">'.s("{amount} du {date}", [
			'amount' => \util\TextUi::money($ref['amount']),
			'date' => \util\DateUi::numeric($ref['date'], \util\DateUi::DATE),
		]).'</span>';

	}

	private function document(array $ref): string {

		return match($ref['source']) {

			\bank\Reconciliation::SALE => s("la vente {value}", $this->documentNumber($ref)),
			\bank\Reconciliation::INVOICE => s("la facture {value}", $this->documentNumber($ref)),
			\bank\Reconciliation::INVOICING => s("la facture électronique {value}", $this->documentNumber($ref)),
			\bank\Reconciliation::CASH => s("le cahier de caisse"),
			\bank\Reconciliation::WALLET => s("la cagnotte"),

		};

	}

	private function documentNumber(array $ref): string {

		if($ref['document'] === NULL) {
			return '<span class="color-muted">'.s("(supprimée)").'</span>';
		}

		return '<b>'.encode($ref['document']).'</b>';

	}

	/** Une ligne ne se rapproche que si elle ne l'a jamais été (`Cash::acceptReconcile()`) : un changement la rattache ou la détache, jamais les deux. */
	private function cashCashflow(?array $cash, ?array $before, ?array $after): string {

		if($before === NULL) {
			return s("Rapprochement de l'opération de caisse validée {cash} avec l'opération bancaire {after}", [
				'cash' => $this->cash($cash),
				'after' => $this->cashflow($after),
			]);
		} else {
			return s("Détachement de l'opération de caisse validée {cash} de l'opération bancaire {before}", [
				'cash' => $this->cash($cash),
				'before' => $this->cashflow($before),
			]);
		}

	}

	private function cash(?array $ref): string {

		$name = $this->name($ref);

		if($ref === NULL or ($ref['label'] ?? NULL) === NULL) {
			return $name;
		}

		return $name.' <span class="color-muted">'.s("{amount} du {date}", [
			'amount' => \util\TextUi::money($ref['amount']),
			'date' => \util\DateUi::numeric($ref['date'], \util\DateUi::DATE),
		]).'</span>';

	}

	/**
	 * Le partenaire est nommé par la transaction elle-même, et non par la phrase : une charge réduite
	 * à son identifiant — la transaction supprimée entre l'action et sa relecture — ne le porte pas,
	 * et la phrase dirait alors Stripe d'une transaction SumUp au lieu de se taire.
	 */
	private function transaction(?array $ref): string {

		if($ref === NULL) {
			return '<span class="color-muted">'.s("(supprimée)").'</span>';
		}

		$h = ($ref['partner'] ?? NULL) === NULL ?
			s("n°{value}", $ref['id']) :
			s("{partner} n°{id}", [
				'partner' => \cash\TransactionUi::getPartnerName($ref['partner']),
				'id' => $ref['id'],
			]);

		if(($ref['amount'] ?? NULL) !== NULL) {
			$h .= ' <span class="color-muted">'.s("{amount} du {date}", [
				'amount' => \util\TextUi::money($ref['amount']),
				'date' => \util\DateUi::numeric($ref['date'], \util\DateUi::DATE),
			]).'</span>';
		}

		return $h;

	}

	/**
	 * Le cahier nommé dans une phrase qui dit déjà « cahier de caisse » : son badge suffit, là où
	 * register() rappelle le mot « Journal » pour les phrases qui ne le portent pas.
	 */
	private function registerLabel(?array $ref): string {

		if($ref === NULL) {
			return '<span class="color-muted">'.s("(supprimé)").'</span>';
		}

		if(($ref['label'] ?? NULL) === NULL) {
			return s("n°{value}", $ref['id']);
		}

		return '<span class="badge badge-solid" style="background-color: '.encode($ref['color']).'">'.encode($ref['label']).'</span>';

	}

	private function register(?array $ref): string {

		if($ref === NULL) {
			return '<span class="color-muted">'.s("(supprimé)").'</span>';
		}

		$h = s("Journal n°{value}", $ref['id']);

		if(($ref['label'] ?? NULL) !== NULL) {
			$h .= ' <span class="badge badge-solid" style="background-color: '.encode($ref['color']).'">'.encode($ref['label']).'</span>';
		}

		return $h;

	}

	private function period(string $from, string $to): array {

		return [
			'from' => \util\DateUi::numeric($from, \util\DateUi::DATE),
			'to' => \util\DateUi::numeric($to, \util\DateUi::DATE),
		];

	}

	/**
	 * Le prélèvement désigne son compte, et un paiement retiré n'a ni méthode ni compte, ce qui suffit à distinguer les
	 * trois cas sans nommer les valeurs de `account\Configuration`, que ce fichier publié ne cite pas.
	 */
	private function vatPayment(?string $method, ?array $bankAccount): string {

		if($method === NULL) {
			return s("Paiement des déclarations de TVA retiré");
		} else if($bankAccount === NULL) {
			return s("Paiement des déclarations de TVA par la structure dans son espace professionnel");
		} else {
			return s("Paiement des déclarations de TVA par prélèvement sur le compte bancaire {value}", $this->name($bankAccount));
		}

	}

	/**
	 * Le type de l'envoi se lit sur sa valeur en base, `edi\Transmission` étant hors de ce fichier publié.
	 */
	private function ediTransmission(string $type, string $from, string $to): string {

		return match($type) {
			'declaration' => s("Envoi aux impôts de la déclaration de TVA (période du {from} au {to})", $this->period($from, $to)),
			'refund' => s("Envoi aux impôts de la demande de remboursement de TVA (période du {from} au {to})", $this->period($from, $to)),
		};

	}

	private function dates(array $data): string {

		if(substr($data['startDate'], 0, 10) === substr($data['endDate'], 0, 10)) {
			return \util\DateUi::numeric($data['startDate'], \util\DateUi::DATE);
		}

		return s("du {from} au {to}", [
			'from' => \util\DateUi::numeric($data['startDate'], \util\DateUi::DATE),
			'to' => \util\DateUi::numeric($data['endDate'], \util\DateUi::DATE),
		]);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Log::model()->describer($property, [
			'level' => s("Niveau"),
			'channel' => s("Canal"),
			'event' => s("Événement"),
			'doneAt' => s("Fait le"),
			'doneBy' => s("Fait par"),
		]);

		switch($property) {

			case 'level' :
				$d->values = [
					Log::USER => s("Utilisateur"),
					Log::SYSTEM => s("Système"),
					Log::TECHNICAL => s("Technique"),
				];
				break;

			case 'channel' :
				$d->values = [
					Log::WEB => s("Application"),
					Log::API => s("API"),
					Log::CRON => s("Tâche planifiée"),
				];
				break;

		}

		return $d;

	}

}
