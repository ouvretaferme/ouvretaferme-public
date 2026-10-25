<?php
namespace structure;

/**
 * Les réglages commerciaux (documents, factures, caisse, cagnottes, TVA par défaut des produits)
 * vivent dans `selling\Configuration`, absente de la version publique.
 *
 * Les réglages de production (saisons, rotations, planches par défaut) vivent dans `map\Configuration`.
 */
class ConfigurationLib extends ConfigurationCrud {

	use ConfLib;

	/**
	 * L'organisme certificateur s'imprime sur les documents de vente : seule une structure qui en
	 * émet le renseigne, depuis son profil.
	 */
	public static function getPropertiesAdministrative(Structure $eStructure): array {

		if(
			$eStructure->isLegal() and
			$eStructure->acceptDocuments()
		) {
			return ['organicCertifier'];
		}

		return [];

	}

	public static function createForStructure(\structure\Structure $eStructure): void {

		$eStructure->expects(['type', 'legalCountry']);

		$e = new Configuration([
			'structure' => $eStructure,
			'hasVat' => match($eStructure['type']) {
				Structure::SHOP => TRUE,
				Structure::AMAP => FALSE,
				Structure::PRODUCER, Structure::COMMUNITY => NULL
			},
		]);

		self::create($e);

	}

	public static function getConfigurationForDate(Structure $eStructure, string $field, string $date): ?bool {
		return self::getConfigurationForDates($eStructure, $field, [$date])[$date];
	}

	public static function getConfigurationForDates(Structure $eStructure, string $field, array $dates): array {

		// L'exigibilité a son propre historique, qui n'est pas un booléen : getVatChargeabilityByDates()
		if($field !== 'hasVat') {
			return array_fill_keys($dates, $eStructure->conf()[$field]);
		}

		$cConfigurationHistory = ConfigurationHistoryLib::getByField($eStructure, $field);

		$values = [];

		foreach($dates as $date) {

			$eConfigurationHistory = $cConfigurationHistory->find(fn(ConfigurationHistory $e) => $e['effectiveAt'] <= $date, limit: 1, default: new ConfigurationHistory());

			if($eConfigurationHistory->notEmpty()) {
				$values[$date] = $eConfigurationHistory['value'][$field];
			} else if($cConfigurationHistory->notEmpty()) {
				$values[$date] = NULL;
			} else {
				$values[$date] = $eStructure->conf()[$field];
			}

		}

		return $values;

	}

	/**
	 * L'assujettissement à la TVA à une date, pour qui a besoin d'un booléen. `hasVat` est nullable
	 * depuis le 2026-09-13 et NULL dit que la structure n'a pas encore répondu à la question : elle
	 * n'est donc pas assujettie tant qu'elle n'a pas dit oui. Les écrans qui distinguent l'absence de
	 * réponse passent par getConfigurationForDate(), qui la leur rend.
	 */
	public static function getHasVatByDate(Structure $eStructure, string $date): bool {

		return (self::getConfigurationForDate($eStructure, 'hasVat', $date) === TRUE);

	}

	/**
	 * La TVA d'une livraison est-elle exigible dès la livraison, à cette date ? Assujettie et sur les débits.
	 */
	public static function getVatOnDebitsByDate(Structure $eStructure, string $date): bool {

		return (
			self::getHasVatByDate($eStructure, $date) and
			self::getVatChargeabilityByDate($eStructure, $date) === Configuration::DEBIT
		);

	}

	public static function getVatChargeabilityByDate(Structure $eStructure, string $date, ?\Collection $cConfigurationHistory = NULL): string {
		return self::getVatChargeabilityByDates($eStructure, [$date], $cConfigurationHistory)[$date];
	}

	/**
	 * L'exigibilité de la TVA des ventes à chaque date. L'historique l'emporte sur le réglage du jour,
	 * comme pour hasVat, et se lit dans l'ordre des dates d'effet, pas dans celui de la saisie.
	 *
	 * Une entrée ne s'écrit que sur un changement depuis une valeur déjà posée, et porte la valeur
	 * nouvelle (update()) : avant la première entrée valait donc l'autre valeur, celle qu'elle a
	 * remplacée. Sans historique, le réglage du jour vaut pour toutes les dates.
	 *
	 * Une entrée supprimée depuis le panneau d'historique ne réaligne pas les autres entrées : la lecture
	 * ne rend que ce que celles qui restent disent (ConfigurationHistoryLib::cancel()).
	 *
	 * Un appelant qui lit beaucoup de dates passe l'historique qu'il a lu une fois
	 * (ConfigurationHistoryLib::getByField()), sinon il est lu ici.
	 */
	public static function getVatChargeabilityByDates(Structure $eStructure, array $dates, ?\Collection $cConfigurationHistory = NULL): array {

		$cConfigurationHistory ??= ConfigurationHistoryLib::getByField($eStructure, 'vatChargeability');

		if($cConfigurationHistory->empty()) {
			return array_fill_keys($dates, $eStructure->conf()['vatChargeability']);
		}

		// getByField() rend l'historique de la plus récente à la plus ancienne date d'effet, et un appelant passe le
		// sien tel qu'il l'a lu de là : la dernière entrée est la plus ancienne, et la première trouvée la plus récente
		$vatChargeabilityBefore = self::getVatChargeabilityBefore($cConfigurationHistory);

		$values = [];

		foreach($dates as $date) {

			$eConfigurationHistory = $cConfigurationHistory->find(fn(ConfigurationHistory $e) => $e['effectiveAt'] <= $date, limit: 1, default: new ConfigurationHistory());

			$values[$date] = $eConfigurationHistory->notEmpty() ? $eConfigurationHistory['value']['vatChargeability'] : $vatChargeabilityBefore;

		}

		return $values;

	}

	/**
	 * L'exigibilité d'avant le premier changement de son historique, celle qu'il a remplacée : une entrée ne s'écrit que
	 * sur un changement depuis une valeur déjà posée (getVatChargeabilityByDates()).
	 *
	 * @param \Collection $cConfigurationHistory Les choix en vigueur, du plus récent au plus ancien, au moins un
	 */
	public static function getVatChargeabilityBefore(\Collection $cConfigurationHistory): string {

		return match($cConfigurationHistory->last()['value']['vatChargeability']) {
			Configuration::CASH => Configuration::DEBIT,
			Configuration::DEBIT => Configuration::CASH,
		};

	}

	/**
	 * L'exigibilité vaut-elle cette valeur un jour au moins entre deux dates, bornes comprises ? Elle
	 * ne change qu'aux dates d'effet de son historique : il suffit de la lire au début et à chacune.
	 */
	public static function hasVatChargeabilityDuring(Structure $eStructure, string $vatChargeability, string $startDate, string $endDate): bool {

		$cConfigurationHistory = ConfigurationHistoryLib::getByField($eStructure, 'vatChargeability');

		$dates = [$startDate];

		foreach($cConfigurationHistory as $eConfigurationHistory) {

			if($eConfigurationHistory['effectiveAt'] > $startDate and $eConfigurationHistory['effectiveAt'] <= $endDate) {
				$dates[] = $eConfigurationHistory['effectiveAt'];
			}

		}

		return in_array($vatChargeability, self::getVatChargeabilityByDates($eStructure, $dates, $cConfigurationHistory), TRUE);

	}

	public static function getHasVatByDates(Structure $eStructure, array $dates): array {

		return array_map(fn(?bool $value) => ($value === TRUE), self::getConfigurationForDates($eStructure, 'hasVat', $dates));

	}

	/**
	 * Les périodes assujetties à la TVA entre deux dates, bornes comprises, chacune en [début, fin].
	 * L'assujettissement ne change qu'aux dates d'effet de son historique : il suffit de le lire au
	 * début de la plage et à chacune de ces dates.
	 */
	public static function getHasVatPeriods(Structure $eStructure, string $startDate, string $endDate): array {

		$dates = [$startDate];

		foreach(ConfigurationHistoryLib::getByField($eStructure, 'hasVat') as $eConfigurationHistory) {

			if($eConfigurationHistory['effectiveAt'] > $startDate and $eConfigurationHistory['effectiveAt'] <= $endDate) {
				$dates[] = $eConfigurationHistory['effectiveAt'];
			}

		}

		$dates = array_unique($dates);
		sort($dates);

		$hasVatByDate = self::getHasVatByDates($eStructure, $dates);

		$periods = [];

		foreach($dates as $position => $date) {

			if($hasVatByDate[$date] === FALSE) {
				continue;
			}

			$periodEnd = isset($dates[$position + 1]) ? date('Y-m-d', strtotime($dates[$position + 1].' - 1 day')) : $endDate;

			if($periods !== [] and $periods[array_key_last($periods)][1] === date('Y-m-d', strtotime($date.' - 1 day'))) {
				$periods[array_key_last($periods)][1] = $periodEnd;
			} else {
				$periods[] = [$date, $periodEnd];
			}

		}

		return $periods;

	}

	public static function updateEmail(Configuration $e, array $writes): void {

		Configuration::model()->beginTransaction();

			self::update($e, ['emailFooter']);

			foreach($writes as $write) {
				$write();
			}

		Configuration::model()->commit();

	}

	public static function update(Configuration $e, array $properties): void {

		Configuration::model()->beginTransaction();

			parent::update($e, $properties);
			self::forget($e['structure']);

			foreach(['hasVat', 'vatChargeability'] as $property) {

				if(
					in_array($property, $properties) and
					Configuration::isHistoryChange($property, $e[$property], $e[$property.'Old'], post_exists($property.'EffectiveAt'))
				) {

					$fw = new \FailWatch();

					$eConfigurationHistory = new ConfigurationHistory([
						'structure' => $e['structure'],
						'field' => $property,
						'value' => $e->extracts([$property]),
					]);

					$eConfigurationHistory->build(['effectiveAt'], ['effectiveAt' => POST($property.'EffectiveAt')]);

					$fw->validate();

					// Un changement daté sur un choix de l'historique ou avant lui l'écrase, et écrase ceux d'après : l'écran
					// les a montrés, et le build() de la configuration a exigé de le confirmer. La ligne de configuration
					// écrite au-dessus tient le verrou sous lequel l'historique s'écrit (ConfigurationHistoryLib::put())
					ConfigurationHistoryLib::overwriteReplaced($e['structure'], $property, $eConfigurationHistory['effectiveAt']);

					ConfigurationHistoryLib::put($eConfigurationHistory);
				}

			}


		Configuration::model()->commit();

	}

	public static function updateHasVatForPeriod(Configuration $e, string $startDate, string $endDate): void {

		$isCurrent = (
			$startDate <= \Clock::date() and
			$endDate >= \Clock::date()
		);

		Configuration::model()->beginTransaction();

			ConfigurationHistoryLib::createForPeriod($e['structure'], 'hasVat', $e['hasVat'], $startDate, $isCurrent ? NULL : $endDate);

			if(
				$isCurrent and
				$e['hasVatOld'] === NULL
			) {
				self::update($e, ['hasVat']);
			}

		Configuration::model()->commit();

	}

	public static function updateProperty(\structure\Structure $eStructure, string $property, mixed $value): void {
		self::updateProperties($eStructure, [$property => $value]);
	}

	/**
	 * Écrit des valeurs connues d'avance dans la ligne d'une structure, sans `build()` : réservé aux
	 * scripts qui posent une configuration recopiée ou calculée (démonstration, ferme école, duplication).
	 */
	public static function updateProperties(\structure\Structure $eStructure, array $values): void {

		// La ligne se désigne par sa structure, unique : l'appelant n'a pas l'identifiant de la ligne.
		Configuration::model()
			->whereStructure($eStructure)
			->update($values);

		self::forget($eStructure);

	}

}
?>
