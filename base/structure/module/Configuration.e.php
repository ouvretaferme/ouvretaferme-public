<?php
namespace structure;

class Configuration extends ConfigurationElement {

	public static function getSelection(): array {

		return Configuration::model()->getProperties();

	}

	public function acceptUpdateVat(): bool {

		return (
			$this['structure']->acceptVat() and
			$this['structure']->isCountryVatAllowed()
		);

	}

	/**
	 * L'assujettissement à la TVA est-il hors de portée de la validation en cours ?
	 *
	 * À la création il arrive forcément dans le même POST. À la mise à jour, un formulaire ciblé
	 * peut ne poster que la fréquence et l'exigibilité — c'est alors la valeur déjà enregistrée qui
	 * fait foi. S'en tenir à isBuilt() rendait les contrôles inertes dès que hasVat n'était pas
	 * reposté, et une fréquence nulle passait.
	 */
	private static function hasNoVat(\PropertyList $l, Configuration $e): bool {

		return ($l->isBuilt('hasVat') === FALSE and $e->exists() === FALSE);

	}

	/**
	 * Un changement de redevabilité ou d'exigibilité s'inscrit dans l'historique quand sa valeur change et
	 * qu'une date d'effet l'accompagne : la déclaration de résultat, le régime de TVA de l'exercice et l'écran
	 * des déclarations changent le réglage sans poster de date, et ne le datent pas. Une exigibilité qui
	 * n'avait encore jamais été choisie n'ouvre pas d'historique.
	 */
	public static function isHistoryChange(string $field, mixed $value, mixed $valueOld, bool $dated): bool {

		return (
			$dated and
			$value !== $valueOld and
			($field === 'hasVat' or $valueOld !== NULL)
		);

	}

	/**
	 * Le premier jour du récapitulatif mensuel ne sert qu'au récapitulatif, qui n'existe qu'avec le suivi RH.
	 */
	public function acceptUpdatePeriod(): bool {

		$this->expects(['structure']);
		return $this['structure']->acceptHr();

	}

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Un changement daté sur une entrée de l'historique ou avant elle la remplace (ConfigurationLib::update()),
	 * et l'écran a montré ce qui disparaît avec une case à cocher : l'envoi se refuse sans elle, le POST étant
	 * contournable. L'entrée est le POST brut, comme dans update() qui la lit par POST() : une date absente ou
	 * illisible laisse passer, update() la refusant ensuite.
	 */
	private static function checkHistoryConfirmed(\PropertyList $l, Configuration $e, string $field, mixed $value): bool {

		// L'historique ne s'écrit qu'à la mise à jour d'une configuration
		if($e->exists() === FALSE) {
			return TRUE;
		}

		$effectiveAt = $l->input[$field.'EffectiveAt'] ?? NULL;

		if(
			self::isHistoryChange($field, $value, $e[$field.'Old'], $effectiveAt !== NULL) === FALSE or
			is_string($effectiveAt) === FALSE or // Un POST forgé peut l'envoyer en tableau
			\util\DateLib::isValid($effectiveAt) === FALSE
		) {
			return TRUE;
		}

		if(
			ConfigurationHistoryLib::getReplaced($e['structure'], $field, $effectiveAt)->notEmpty() and
			($l->input[$field.'HistoryConfirmed'] ?? NULL) !== '1'
		) {
			throw new \FailException('structure\Configuration::'.$field.'.historyConfirmed', [], $field.'HistoryConfirmed');
		}

		return TRUE;

	}

	public static function properties(\PropertyList $l): void {

		// Le temps de travail se saisit dans le planning : il ne s'allume qu'avec la production, et s'éteint toujours.
		$l->property('time')
			->addAfterCheck('production', function(bool $value): bool {

				$this->expects(['structure']);

				return (
					$value === FALSE or
					($this['structure']->acceptProduction() and $this['structure']->conf(Feature::class)['production'])
				);

			});

		$l->property('vatNumber')
			->replaceCheck(fn(?string &$vat) => \structure\Structure::checkVatNumber('structure\Configuration', $this['structure'], $vat, POST('isFromInvoicing', 'bool', FALSE) === FALSE));

		$l->property('hasVat')
			->addAfterCast('old', function(): bool {

				if($this->exists()) {
					$this['hasVatOld'] = $this['hasVat'];
				}

				return TRUE;

			})
			->addAfterCheck('required', fn(?bool $hasVat): bool => $hasVat !== NULL)
			->addAfterCheck('historyConfirmed', fn(?bool $hasVat, \PropertyList $l): bool => self::checkHistoryConfirmed($l, $this, 'hasVat', $hasVat));

		$l->property('vatChargeability')
			->addAfterCast('old', function(): bool {

				if($this->exists()) {
					$this['vatChargeabilityOld'] = $this['vatChargeability'];
				}

				return TRUE;

			})
			->addAfterCheck('required', function(?string $vatChargeability, \PropertyList $l): bool {

				if(self::hasNoVat($l, $this) or ($this['hasVat'] ?? NULL) === FALSE) {
					return TRUE;
				}

				return in_array($vatChargeability, Configuration::model()->getPropertyEnum('vatChargeability'));
			})
			->addAfterCheck('historyConfirmed', fn(?string $vatChargeability, \PropertyList $l): bool => self::checkHistoryConfirmed($l, $this, 'vatChargeability', $vatChargeability));

		$l->property('electronicAddress')
			->addAfterCast('composed', function(?string &$electronicAddress, \PropertyList $l) {

				$scheme = $l->input['scheme'] ?? NULL;
				$value = $l->input['value'] ?? NULL;

				$eStructure = $this['structure'];

				if($scheme === NULL or $value === NULL or $eStructure->empty()) {
					return TRUE;
				}

				if(
					StructureSetting::hasAccountingApp() and
					\pdp\AddressCheckLib::checkScheme($scheme, $eStructure['legalCountry']) and
					\pdp\AddressCheckLib::checkValue($value, $eStructure['siret'])
				) {
					$electronicAddress = $scheme.':'.$value;
					return TRUE;
				}

				return FALSE;

			});

		$l->property('vatDebitsOption')
			->addAfterCheck('checkConsistency', function(?bool $vatDebitsOption): bool {

				if(($this['vatChargeability'] ?? NULL) === NULL) {
					return TRUE;
				}

				if($this['vatChargeability'] === Configuration::CASH) {
					return $vatDebitsOption === FALSE;
				}

				return is_bool($vatDebitsOption);

			});

		$l->property('reconciliationDifferenceAmountMax')
			->addAfterCheck('value', function(?int $amount) {

				return ($amount === NULL or ($amount >= 0 and $amount <= \bank\BankSetting::MAX_AMOUNT_DIFFERENCE_RECONCILIATION_AUTOMATIC));

			});

	}

}
?>
