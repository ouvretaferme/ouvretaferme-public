<?php
namespace structure;

class ConfigurationUi {

	public function __construct() {

		\Asset::css('structure', 'configuration.css');
		\Asset::js('structure', 'configuration.js');

	}

	public function updateVatNumberPanel(\structure\Structure $eStructure): \Panel {

		$eConfiguration = $eStructure->conf();

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/configuration:doUpdateVatNumber', ['autocomplete' => 'off', 'class' => 'structure-vat-number-form']);

			$h .= '<div class="util-info">';
				$h .= s("Votre numéro de TVA intracommunautaire est nécessaire pour accéder à cette page.");
			$h .= '</div>';

			$h .= $form->hidden('id', $eConfiguration['id']);
			$h .= $form->hidden('isFromInvoicing', '1');

			$h .= $form->dynamicGroup($eConfiguration, 'vatNumber');

			$h .= $form->group(
				content: $form->submit(s("Valider"))
			);

		$h .= $form->close();


		return new \Panel(
			id: 'panel-vat-number-update',
			title: s("Paramétrer le numéro de TVA intracommunautaire"),
			body: $h
		);

	}

	/**
	 * @param array $periods ConfigurationHistoryLib::getPeriods()
	 */
	public function updateVatHistory(\structure\Structure $eStructure, string $field, array $periods): \Panel {

		$eConfiguration = $eStructure->conf();

		$h = '<div class="util-block-info">';
			$h .= s("Chaque écriture et chaque déclaration de TVA prend le réglage en vigueur à sa date.");
		$h .= '</div>';

		// La valeur se lit sous sa période et non dans une colonne, pour que la table tienne sur un téléphone
		$h .= '<div class="util-table" style="grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) auto">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Période").'</div>';
				$h .= '<div>'.s("Changement").'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($periods as $period) {

				$title = $this->getVatHistoryPeriod($period);

				// Avant le premier changement, la redevabilité n'a pas de valeur : elle n'est pas connue
				if($period['from'] === NULL and $period['value'] === NULL) {
					$value = '<div class="color-muted">'.s("non connue").'</div>';
				} else {
					$value = '<div>'.$this->getVatValue($field, $period['value']).'</div>';
				}

				if($period['changes'] === []) {

					$h .= '<div class="util-table-row util-table-break">';
						$h .= '<div>'.$title.$value.'</div>';
						$h .= '<div class="color-muted">'.($field === 'hasVat' ? s("Aucun changement enregistré avant cette date") : s("Le réglage d'avant le premier changement")).'</div>';
						$h .= '<div></div>';
					$h .= '</div>';

					continue;

				}

				foreach($period['changes'] as $position => $change) {

					$eConfigurationHistory = $change['eConfigurationHistory'];
					$effectiveAt = \util\DateUi::numeric($eConfigurationHistory['effectiveAt'], \util\DateUi::DATE);

					$h .= '<div class="util-table-row'.($position === 0 ? ' util-table-break' : '').'">';

						$h .= '<div>';
							if($position === 0) {
								$h .= $title.$value;
							}
						$h .= '</div>';

						$h .= '<div>';
							if($change['type'] === 'overwritten') {
								// `<s>` et non util-strikethrough : le libellé du régime passe à la ligne sur un
								// petit écran, et le trait unique du composant passerait entre les deux lignes.
								$h .= '<s class="color-muted">'.s("Au {date}, {value}", ['date' => $effectiveAt, 'value' => $this->getVatValue($field, $eConfigurationHistory['value'][$field])]).'</s>';
								$h .= '<div class="color-muted">'.s("Écrasé par la déclaration du {date}", ['date' => \util\DateUi::numeric($eConfigurationHistory['overwrittenBy'], \util\DateUi::DATE)]).'</div>';
							} else {
								$h .= ($change['type'] === 'first') ? s("Au {date}", ['date' => $effectiveAt]) : s("Confirmé au {date}", ['date' => $effectiveAt]);
								$h .= '<div class="color-muted">'.$this->getVatHistoryRecorded($eConfigurationHistory).'</div>';
							}
						$h .= '</div>';

						$h .= '<div>';
							if($change['type'] !== 'overwritten') {
								$h .= '<a data-ajax="/structure/configuration:doDeleteVatHistory" post-field="'.$field.'" post-id="'.$eStructure['id'].'" post-date="'.$eConfigurationHistory['effectiveAt'].'" class="btn btn-only-icon btn-outline-primary" data-confirm="'.$this->getVatHistoryCancel($field, $eConfigurationHistory, $eConfiguration[$field], $change['valueAfterCancel']).'">'.\Asset::icon('trash').'</a>';
							}
						$h .= '</div>';

					$h .= '</div>';

				}

			}

		$h .= '</div>';

		return new \Panel(
			id: 'panel-vat-history',
			title: match($field) {
				'hasVat' => s("Historique de la redevabilité de la TVA"),
				'vatChargeability' => s("Historique de l'exigibilité de la TVA"),
			},
			body: $h
		);

	}

	private function getVatHistoryPeriod(array $period): string {

		$from = ($period['from'] === NULL) ? NULL : \util\DateUi::numeric($period['from'], \util\DateUi::DATE);
		$until = ($period['until'] === NULL) ? NULL : \util\DateUi::numeric($period['until'], \util\DateUi::DATE);

		if($from === NULL and $until === NULL) {
			return s("Toujours");
		} else if($from === NULL) {
			return s("Jusqu'au {date}", ['date' => $until]);
		} else if($until !== NULL) {
			return s("Du {from} au {until}", ['from' => $from, 'until' => $until]);
		} else if($period['isFuture']) {
			return s("À partir du {date}", ['date' => $from]);
		} else {
			return s("Depuis le {date}", ['date' => $from]);
		}

	}

	private function getVatHistoryRecorded(ConfigurationHistory $eConfigurationHistory): string {

		$recordedAt = \util\DateUi::numeric($eConfigurationHistory['createdAt'], \util\DateUi::DATE);

		if($eConfigurationHistory['createdBy']->empty()) {
			return s("Enregistré le {date}, à la création d'un exercice", ['date' => $recordedAt]);
		} else {
			return s("Enregistré le {date} par {user}", ['date' => $recordedAt, 'user' => $eConfigurationHistory['createdBy']->getName()]);
		}

	}

	/**
	 * La confirmation d'une annulation dit ce que devient le réglage, qui ne bouge que lorsque part le dernier changement.
	 */
	private function getVatHistoryCancel(string $field, ConfigurationHistory $eConfigurationHistory, mixed $valueCurrent, mixed $valueAfterCancel): string {

		$kept = ($valueAfterCancel === $valueCurrent);

		$consequence = match($field) {
			'hasVat' => match($valueAfterCancel) {
				TRUE => $kept ? s("Votre structure reste redevable de la TVA.") : s("Votre structure redevient redevable de la TVA."),
				FALSE => $kept ? s("Votre structure reste non redevable de la TVA.") : s("Votre structure redevient non redevable de la TVA."),
				NULL => $kept ? s("La redevabilité de votre structure reste non renseignée.") : s("La redevabilité de votre structure redevient non renseignée."),
			},
			'vatChargeability' => match($valueAfterCancel) {
				Configuration::CASH => $kept ? s("Votre structure reste à la TVA sur les encaissements.") : s("Votre structure revient à la TVA sur les encaissements."),
				Configuration::DEBIT => $kept ? s("Votre structure reste à la TVA sur les débits.") : s("Votre structure revient à la TVA sur les débits."),
			},
		};

		return s("Supprimer le changement du {date} ?", ['date' => \util\DateUi::numeric($eConfigurationHistory['effectiveAt'], \util\DateUi::DATE)]).' '.$consequence;

	}

	public function updateVat(\structure\Structure $eStructure, \Collection $ccConfigurationHistory): string {

		$eConfiguration = $eStructure->conf();

		if($eStructure->hasAccounting() and $eStructure['eFinancialYear']->notEmpty()) {
			$date = $eStructure['eFinancialYear']['startDate'];
		} else {
			$date = \Clock::format('Y-01-01');
		}

		$form = new \util\FormUi();

		$h = '<div class="mb-2">';
			$h .= $form->openAjax('/structure/configuration:doUpdateVat', ['id' => 'configuration-update-vat', 'autocomplete' => 'off']);

				$h .= $form->hidden('id', $eConfiguration['id']);

				$h .= $form->dynamicGroup($eConfiguration, 'hasVat', function(\PropertyDescriber $d) use ($eConfiguration, $ccConfigurationHistory) {

					if(($ccConfigurationHistory['hasVat'] ?? new \Collection())->notEmpty()) {
						$d->labelAfter = \util\FormUi::info('<a href="/structure/configuration:updateVatHistory?field=hasVat&id='.$eConfiguration['structure']['id'].'">'.s("Voir l'historique des changements").'</a>');
					}

					$d->attributes['callbackRadioAttributes'] = fn() => ['onclick' => 'StructureConfiguration.toggleEffectiveAt("hasVat", this, "'.match($eConfiguration['hasVat']) {
						TRUE => '1',
						FALSE => '0',
						NULL => '',
					}.'"); StructureConfiguration.weighEffectiveAt("hasVat", this, '.$eConfiguration['structure']['id'].')'];
				});

				$eConfigurationHistory = new ConfigurationHistory();
				$h .= $form->dynamicGroup($eConfigurationHistory, 'effectiveAt', function(\PropertyDescriber $d) use($date, $eConfiguration) {
					$d->group['class'] = 'hide';
					$d->name = 'hasVatEffectiveAt';
					$d->default = $date;
					$d->attributes['onchange'] = 'StructureConfiguration.weighEffectiveAt("hasVat", this, '.$eConfiguration['structure']['id'].')';
				});

				$h .= $this->getVatWeighGroup($form, 'hasVat');

				$h .= $form->dynamicGroup($eConfiguration, 'vatNumber');

				$h .= $form->dynamicGroup($eConfiguration, 'vatChargeability', function($d) use($eConfiguration, $ccConfigurationHistory) {

					$d->attributes['onchange'] = 'StructureConfiguration.changeVatChargeability(this); StructureConfiguration.toggleEffectiveAt("vatChargeability", this, "'.($eConfiguration['vatChargeability']).'"); StructureConfiguration.weighEffectiveAt("vatChargeability", this, '.$eConfiguration['structure']['id'].')';

					if(($ccConfigurationHistory['vatChargeability'] ?? new \Collection())->notEmpty()) {
						$d->labelAfter = \util\FormUi::info('<a href="/structure/configuration:updateVatHistory?field=vatChargeability&id='.$eConfiguration['structure']['id'].'">'.s("Voir l'historique des changements").'</a>');
					}

				});

				$eConfigurationHistory = new ConfigurationHistory();
				$h .= $form->dynamicGroup($eConfigurationHistory, 'effectiveAt', function(\PropertyDescriber $d) use($date, $eConfiguration) {
					$d->group['class'] = 'hide';
					$d->name = 'vatChargeabilityEffectiveAt';
					$d->label = s("Quand ce changement sur l'exigibilité de la TVA a-t-il eu lieu ?");
					$d->default = $date;
					$d->attributes['onchange'] = 'StructureConfiguration.weighEffectiveAt("vatChargeability", this, '.$eConfiguration['structure']['id'].')';
				});

				$h .= $this->getVatWeighGroup($form, 'vatChargeability');

				$h .= $form->dynamicGroup($eConfiguration, 'vatDebitsOption', function($d) use($eConfiguration) {
					if($eConfiguration['vatChargeability'] === Configuration::CASH) {
						$d->group['class'] = 'hide';
					}
				});

				$confirm = $eStructure->isVatAccountingConfigured() ? [] : ['data-confirm' => s("La configuration de la TVA a un impact sur les ventes, les factures et la comptabilité. Validez-vous cette configuration ?") ];

				$h .= $form->group(
					content: $form->submit(s("Valider"), $confirm)
				);

			$h .= $form->close();
		$h .= '</div>';

		return $h;

	}

	// Rempli par la page vatWeigh, qui pèse le changement à chaque valeur ou date saisie
	private function getVatWeighGroup(\util\FormUi $form, string $field): string {

		return $form->group(
			content: '<div id="configuration-vat-weigh-'.$field.'"></div>',
			attributes: ['data-wrapper' => $field.'HistoryConfirmed', 'class' => 'hide']
		);

	}

	/**
	 * Ce que supprime un changement daté sur un choix de l'historique ou avant lui : une ligne par choix, et une
	 * seule pour ceux qu'une reprise a enregistrés le même jour sans auteur. La case qui suit, le serveur l'exige.
	 */
	public function getVatWeigh(string $field, \Collection $cConfigurationHistory, \Collection $cDeclaration): string {

		if($cConfigurationHistory->empty()) {
			return '';
		}

		$number = $cConfigurationHistory->count();

		$groups = [];

		foreach($cConfigurationHistory as $eConfigurationHistory) {

			$key = $eConfigurationHistory['createdBy']->empty() ?
				'day-'.substr($eConfigurationHistory['createdAt'], 0, 10) :
				'entry-'.$eConfigurationHistory['effectiveAt'];

			$groups[$key][] = $eConfigurationHistory;

		}

		$consequences = [];

		foreach($groups as $entries) {
			$consequences[] = ['icon' => 'x-lg', 'text' => $this->getVatWeighReplaced($field, $entries)];
		}

		$consequences = array_merge($consequences, StructureUi::getObserverEntries('getVatWeighDeclarations', $cDeclaration));

		$title = match($field) {
			'hasVat' => p("Écraser un choix de redevabilité", "Écraser {value} choix de redevabilité", $number),
			'vatChargeability' => p("Écraser un choix d'exigibilité", "Écraser {value} choix d'exigibilité", $number),
		};

		$h = \main\DesignUi::getWeigh('warning', 'type-strikethrough', $title, NULL, $consequences, [s("Les ventes déjà créées gardent leur TVA.")], NULL);

		$h .= new \util\FormUi()->checkbox($field.'HistoryConfirmed', attributes: [
			'callbackLabel' => fn($input) => $input.' '.p("Je confirme écraser ce choix", "Je confirme écraser ces {value} choix", $number),
			'callbackFieldAttributes' => ['class' => 'mt-1'],
		]);

		return $h;

	}

	private function getVatWeighReplaced(string $field, array $entries): string {

		$eConfigurationHistoryFirst = $entries[0];

		$recordedAt = \util\DateUi::numeric($eConfigurationHistoryFirst['createdAt'], \util\DateUi::DATE);

		if(count($entries) === 1) {

			$choice = s("« {value} » au {date}", ['value' => $this->getVatValue($field, $eConfigurationHistoryFirst['value'][$field]), 'date' => \util\DateUi::numeric($eConfigurationHistoryFirst['effectiveAt'], \util\DateUi::DATE)]);

			if($eConfigurationHistoryFirst['createdBy']->empty()) {
				return s("<b>Le choix {choice}</b>, enregistré le {date}, sera écrasé et restera barré dans l'historique.", ['choice' => $choice, 'date' => $recordedAt]);
			} else {
				return s("<b>Le choix {choice}</b>, enregistré le {date} par {user}, sera écrasé et restera barré dans l'historique.", ['choice' => $choice, 'date' => $recordedAt, 'user' => $eConfigurationHistoryFirst['createdBy']->getName()]);
			}

		}

		$values = array_unique(array_map(fn(ConfigurationHistory $eConfigurationHistory) => $this->getVatValue($field, $eConfigurationHistory['value'][$field]), $entries));

		// Une seule valeur se dit une fois, devant ses dates
		if(count($values) === 1) {
			$choices = s("« {value} » {dates}", [
				'value' => $values[0],
				'dates' => self::getEnumeration(array_map(fn(ConfigurationHistory $eConfigurationHistory) => s("au {date}", ['date' => \util\DateUi::numeric($eConfigurationHistory['effectiveAt'], \util\DateUi::DATE)]), $entries)),
			]);
		} else {
			$choices = self::getEnumeration(array_map(fn(ConfigurationHistory $eConfigurationHistory) => s("« {value} » au {date}", [
				'value' => $this->getVatValue($field, $eConfigurationHistory['value'][$field]),
				'date' => \util\DateUi::numeric($eConfigurationHistory['effectiveAt'], \util\DateUi::DATE),
			]), $entries));
		}

		return s("<b>Les choix {choices}</b>, enregistrés le {date}, seront écrasés et resteront barrés dans l'historique.", ['choices' => $choices, 'date' => $recordedAt]);

	}

	private function getVatValue(string $field, mixed $value): string {

		return match($field) {
			'hasVat' => match($value) {
				TRUE => s("redevable"),
				FALSE => s("non redevable"),
				NULL => s("non renseigné"),
			},
			'vatChargeability' => match($value) {
				Configuration::CASH => s("TVA sur les encaissements"),
				Configuration::DEBIT => s("TVA sur les débits"),
			},
		};

	}

	public static function getEnumeration(array $values): string {

		$last = array_pop($values);

		return ($values === []) ? $last : s("{list} et {last}", ['list' => implode(', ', $values), 'last' => $last]);

	}

	public static function getBanner(Configuration $eConfiguration, string $width): string {

		$eConfiguration->expects(['id', 'emailBanner']);

		$ui = new \media\StructureEmailBannerUi();

		$class = 'structure-email-banner-view media-rectangle-view'.' ';
		$style = '';

		if($eConfiguration['emailBanner'] !== NULL) {
			$style .= 'background-image: url('.$ui->getUrlByElement($eConfiguration, 'm').');';
		}

		return '<div class="'.$class.'" style="width: '.$width.'; max-width: 100%; height: auto; aspect-ratio: 5; '.$style.'"></div>';

	}

	public function updateEmail(Configuration $eConfiguration): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/configuration:doUpdateEmail');

			$h .= $form->hidden('id', $eConfiguration);

			$h .= $form->group(
				(ConfigurationUi::p('emailBanner')->label)($eConfiguration),
				new \media\StructureEmailBannerUi()->getCamera($eConfiguration, width: '500px', height: 'auto')
			);
			$h .= '<br/>';

			$h .= $form->dynamicGroup($eConfiguration, 'emailFooter');
			$h .= implode('', StructureUi::getObserverEntries('getEmailFields', $form, $eConfiguration['structure']));

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return $h;

	}

	/**
	 * Le premier jour du récapitulatif mensuel et la période de référence des contrats annualisés, ouverts depuis le
	 * récapitulatif (`\hr\RecapUi`) et depuis le formulaire d'un contrat (`\hr\ContractUi`).
	 */
	public function updatePeriod(Configuration $eConfiguration): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/configuration:doUpdatePeriod');

			$h .= $form->hidden('id', $eConfiguration);
			$h .= $form->dynamicGroups($eConfiguration, ['timePeriodDay', 'timeReferenceMonth']);
			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-structure-update-period',
			title: s("Paramétrer la synthèse mensuelle"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Configuration::model()->describer($property, [
			'hasVat' => s("La structure est-elle redevable de la TVA ?"),
			'vatChargeability' => s("Exigibilité de la TVA"),
			'vatNumber' => s("Numéro de TVA intracommunautaire"),
			'organicCertifier' => s("Organisme de certification pour l'Agriculture Biologique"),
			'vatDebitsOption' => s("Avez-vous pris l'option pour le paiement de la TVA d'après les débits ?"),
			'time' => s("Activer le suivi du temps de travail"),
			'timeRange' => s("Saisir les horaires de la journée"),
			'timeReferenceMonth' => s("Début de la période de référence des contrats annualisés"),
			'timePeriodDay' => s("Premier jour de la synthèse mensuelle"),
		]);

		switch($property) {

			case 'vatNumber' :
				$d->palceholder = s("Ex. : {value}", 'FR01234567890');
				$d->after = fn(\util\FormUi $form, Configuration $e) => ($e->exists() === FALSE or $e['structure']->acceptInvoicing() === FALSE) ? \util\FormUi::info(s("Indiquez ici un numéro de TVA intracommunautaire si vous souhaitez le voir apparaître sur les factures.")) : NULL;
				break;

			case 'hasVat' :
				$d->field = 'yesNo';
				$d->after = \util\FormUi::info(s("Le changement dans la redevabilité de la TVA n'est pris en compte que pour les ventes créées ultérieurement."));
				break;

			case 'organicCertifier' :
				$d->placeholder = s("Ex. : {value}", 'FR-BIO-01');
				$d->after = \util\FormUi::info(s("Indiquez ici le code ISO de votre organisme certificateur pour le voir apparaître sur les bons de commande, bons de livraison et factures."));
				break;

			case 'vatDebitsOption':
				$d->field = 'yesNo';
				break;

			case 'vatChargeability' :
				$cash = '<h4>'.s("TVA sur les encaissements").'</h4>';
				$cash .= '<ul>';
					$cash .= '<li>'.s("La TVA est due dès l'encaissement.").'</li>';
					$cash .= '<li>'.s("Exigibilité par défaut pour les Régimes Simplifiés (Micro-BA, RSA...).").'</li>';
					$cash .= '<li>'.s("Les prestations de service entrent dans cette règle d'office.").'</li>';
				$cash .= '</ul>';

				$debit = '<h4>'.s("TVA sur les débits").'</h4>';
				$debit .= '<ul>';
					$debit .= '<li>'.s("La TVA est due dès l'émission d'une facture, sa date faisant foi.").'</li>';
				$debit .= '</ul>';

				$d->values = [
					Configuration::CASH => $cash,
					Configuration::DEBIT => $debit,
				];
				$d->required = TRUE;
				break;

			case 'timeRange' :
				$d->field = 'yesNo';
				$d->labelAfter = \util\FormUi::info(s("La durée de chaque journée est alors calculée à partir des horaires saisis dans le planning."));
				break;

			case 'timePeriodDay' :
				$d->field = 'select';
				$d->values = array_combine(range(1, 28), array_map(fn(int $day) => ($day === 1) ? s("1er du mois") : s("le {value}", $day), range(1, 28)));
				$d->attributes = ['required' => TRUE];
				$d->labelAfter = \util\FormUi::info(s("La synthèse mensuelle démarre avec le jour choisi jusqu'à la veille du mois suivant. Avec le 1<sup>er</sup>, elle couvre le mois entier."));
				break;

			case 'timeReferenceMonth' :
				$d->field = 'select';
				$d->values = \util\DateUi::months();
				$d->attributes = ['required' => TRUE];
				$d->labelAfter = \util\FormUi::info(s("Les heures d'un contrat annualisé se comptent sur douze mois, à partir du premier jour de ce mois."));
				break;

			case 'emailBanner' :
				$d->label = fn(Configuration $e) => $e['structure']->isAmap() ?
					s("Bandeau à afficher en haut des e-mails envoyés à vos adhérents") :
					s("Bandeau à afficher en haut des e-mails envoyés à vos clients");
				break;

			case 'emailFooter' :
				$d->label = fn(Configuration $e) => $e['structure']->isAmap() ?
					s("Signature à afficher en bas des e-mails envoyés à vos adhérents") :
					s("Signature à afficher en bas des e-mails envoyés à vos clients");
				break;

		}

		return $d;

	}

}
?>
