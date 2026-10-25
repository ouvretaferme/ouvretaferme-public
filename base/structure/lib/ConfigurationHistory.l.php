<?php
namespace structure;

Class ConfigurationHistoryLib extends ConfigurationHistoryCrud {

	/**
	 * Tout l'historique, choix écrasés compris : l'écran les montre barrés. Toute autre lecture ne prend que les choix
	 * en vigueur (`overwrittenBy` vide).
	 */
	public static function getHistory(Structure $eStructure): \Collection {

		return ConfigurationHistory::model()
			->select(ConfigurationHistory::getSelection() + ['createdBy' => ['firstName', 'lastName']])
			->whereStructure($eStructure)
			->sort(['effectiveAt' => SORT_DESC])
			->getCollection(index: ['field', NULL]);

	}

	public static function getForDate(Structure $eStructure, string $field, string $date): ConfigurationHistory {

		return ConfigurationHistory::model()
			->select('value')
			->whereStructure($eStructure)
			->whereField($field)
			->whereOverwrittenBy(NULL)
			->whereEffectiveAt('<=', $date)
			->sort(['effectiveAt' => SORT_DESC])
			->get();

	}


	public static function getByField(Structure $eStructure, string $field): \Collection {

		return ConfigurationHistory::model()
			->select(['value', 'effectiveAt'])
			->whereStructure($eStructure)
			->whereField($field)
			->whereOverwrittenBy(NULL)
			->sort(['effectiveAt' => SORT_DESC])
			->getCollection();

	}

	/**
	 * Les choix qu'un changement daté du jour donné remplace : celui de la même date et tous ceux d'après.
	 */
	public static function getReplaced(Structure $eStructure, string $field, string $effectiveAt): \Collection {

		return ConfigurationHistory::model()
			->select(ConfigurationHistory::getSelection() + ['createdBy' => ['firstName', 'lastName']])
			->whereStructure($eStructure)
			->whereField($field)
			->whereOverwrittenBy(NULL)
			->whereEffectiveAt('>=', $effectiveAt)
			->sort(['effectiveAt' => SORT_ASC])
			->getCollection();

	}

	public static function hasHistory(Structure $eStructure, string $field): bool {

		return ConfigurationHistory::model()
			->whereStructure($eStructure)
			->whereField($field)
			->whereOverwrittenBy(NULL)
			->exists();

	}

	public static function createForPeriod(Structure $eStructure, string $field, bool $value, string $from, ?string $until = NULL): void {

		ConfigurationHistory::model()->beginTransaction();

		self::lock($eStructure);

		$cConfigurationHistory = new \Collection([
			new ConfigurationHistory(['structure' => $eStructure, 'field' => $field, 'value' => [$field => $value], 'effectiveAt' => $from]),
		]);

		if($until !== NULL) {

			$next = date('Y-m-d', strtotime($until.' + 1 day'));

			$hasNext = ConfigurationHistory::model()
				->whereStructure($eStructure)
				->whereField($field)
				->whereOverwrittenBy(NULL)
				->whereEffectiveAt('BETWEEN', new \Sql(ConfigurationHistory::model()->format(date('Y-m-d', strtotime($from.' + 1 day'))).' AND '.ConfigurationHistory::model()->format($next)))
				->exists();

			if($hasNext === FALSE) {
				$cConfigurationHistory[] = new ConfigurationHistory(['structure' => $eStructure, 'field' => $field, 'value' => [$field => NULL], 'effectiveAt' => $next]);
			}

		}

		foreach($cConfigurationHistory as $eConfigurationHistory) {
			self::put($eConfigurationHistory);
		}

		ConfigurationHistory::model()->commit();

	}

	/**
	 * Déplace une entrée posée sur une borne d'exercice quand cette borne bouge. Celle qui occupait
	 * déjà la nouvelle date est remplacée, comme le fait createForPeriod() : la valeur choisie pour
	 * l'exercice l'emporte sur la sienne.
	 */
	public static function move(Structure $eStructure, string $field, string $from, string $to): void {

		if($from === $to) {
			return;
		}

		ConfigurationHistory::model()->beginTransaction();

		self::lock($eStructure);

		$eConfigurationHistory = ConfigurationHistory::model()
			->select(ConfigurationHistory::getSelection())
			->whereStructure($eStructure)
			->whereField($field)
			->whereOverwrittenBy(NULL)
			->whereEffectiveAt($from)
			->get();

		if($eConfigurationHistory->notEmpty()) {

			self::deleteHistory($eStructure, $from, $field);

			$eConfigurationHistory['effectiveAt'] = $to;

			self::put($eConfigurationHistory);

			// Les choix que l'entrée avait écrasés désignent la date d'effet qui l'a écrasée : ils la suivent. Écriture
			// de masse, d'où le tableau de valeurs
			ConfigurationHistory::model()
				->whereStructure($eStructure)
				->whereField($field)
				->whereOverwrittenBy($from)
				->update(['overwrittenBy' => $to]);

		}

		ConfigurationHistory::model()->commit();

	}

	/**
	 * Écrit une entrée en vigueur, à la place de celle qui occupait déjà sa date. L'appelant tient le verrou de lock() :
	 * aucune contrainte de la table ne garde l'unicité des choix en vigueur, un choix écrasé pouvant partager sa date.
	 */
	public static function put(ConfigurationHistory $eConfigurationHistory): void {

		self::deleteHistory($eConfigurationHistory['structure'], $eConfigurationHistory['effectiveAt'], $eConfigurationHistory['field']);

		ConfigurationHistory::model()->insert($eConfigurationHistory);

	}

	/**
	 * Sérialise les écritures de l'historique d'une structure sur sa ligne de configuration, dans l'ordre où
	 * ConfigurationLib::update() les prend : la ligne de configuration d'abord, l'historique ensuite.
	 */
	private static function lock(Structure $eStructure): void {

		Configuration::model()
			->whereStructure($eStructure)
			->getForUpdate();

	}

	/**
	 * Un changement daté remplace le choix de sa date et ceux d'après, qui restent dans l'historique, écrasés par lui.
	 * Par critère et en tableau de valeurs : une entrée n'a pas d'id, la date d'effet la désigne avec la structure et
	 * le réglage, et l'écriture en touche plusieurs.
	 */
	public static function overwriteReplaced(Structure $eStructure, string $field, string $effectiveAt): void {

		ConfigurationHistory::model()
			->whereStructure($eStructure)
			->whereField($field)
			->whereOverwrittenBy(NULL)
			->whereEffectiveAt('>=', $effectiveAt)
			->update(['overwrittenBy' => $effectiveAt]);

	}

	/**
	 * Annule un changement depuis le panneau d'historique. Les choix qu'il avait écrasés restent écrasés : ils ont été
	 * remplacés par une déclaration, et la supprimer ne les redéclare pas.
	 */
	public static function cancel(Structure $eStructure, string $field, string $effectiveAt): void {

		ConfigurationHistory::model()->beginTransaction();

			// Relue sous verrou : le réglage comparé au dernier changement est celui que l'écriture remplacera, et
			// il se verrouille avant l'historique, dans l'ordre où ConfigurationLib::update() les écrit
			$eConfiguration = Configuration::model()
				->select(Configuration::getSelection())
				->whereStructure($eStructure)
				->option('for-update')
				->get();

			$eConfiguration['structure'] = $eStructure;

			$value = self::getValueAfterCancel($eConfiguration, $field, self::getByField($eStructure, $field), $effectiveAt);

			self::deleteHistory($eStructure, $effectiveAt, $field);

			if($value !== $eConfiguration[$field]) {

				// Sans date d'effet postée, update() n'inscrit rien dans l'historique
				$eConfiguration[$field.'Old'] = $eConfiguration[$field];
				$eConfiguration[$field] = $value;

				ConfigurationLib::update($eConfiguration, [$field]);

			}

		ConfigurationHistory::model()->commit();

	}

	/**
	 * La valeur du réglage une fois le changement de cette date annulé, la valeur actuelle quand il n'en change pas.
	 * Le réglage porte la valeur du dernier changement daté (ConfigurationLib::update()) : quand c'est lui qui part, le
	 * réglage reprend la valeur d'avant, sans quoi les ventes liraient le réglage et la comptabilité l'historique,
	 * chacune une valeur. L'exigibilité qui n'a plus aucun changement reprend celle que le premier avait remplacée. La
	 * redevabilité n'a pas de valeur d'avant son premier changement : le réglage seul vaut alors pour toutes les dates,
	 * et le remettre à « non renseigné » rouvrirait une question à laquelle la structure a répondu.
	 *
	 * @param \Collection $cConfigurationHistory Les choix en vigueur, du plus récent au plus ancien (getByField())
	 */
	public static function getValueAfterCancel(Configuration $eConfiguration, string $field, \Collection $cConfigurationHistory, string $effectiveAt): mixed {

		if($cConfigurationHistory->empty()) {
			return $eConfiguration[$field];
		}

		$eConfigurationHistoryLast = $cConfigurationHistory->first();
		$cConfigurationHistoryKept = $cConfigurationHistory->find(fn(ConfigurationHistory $e) => $e['effectiveAt'] !== $effectiveAt);

		// Un réglage qui ne porte pas la valeur du dernier changement n'en vient pas, et n'a rien à reprendre
		if(
			$eConfigurationHistoryLast['effectiveAt'] !== $effectiveAt or
			$eConfigurationHistoryLast['value'][$field] !== $eConfiguration[$field] or
			($cConfigurationHistoryKept->empty() and $field !== 'vatChargeability')
		) {
			return $eConfiguration[$field];
		}

		if($cConfigurationHistoryKept->notEmpty()) {
			return $cConfigurationHistoryKept->first()['value'][$field];
		} else {
			return ConfigurationLib::getVatChargeabilityBefore($cConfigurationHistory);
		}

	}

	/**
	 * Les périodes de l'historique d'un réglage, de la plus récente à la plus ancienne, pour l'écran d'historique.
	 * Deux changements de même valeur à la suite font une seule période. Chaque période porte ses lignes, du plus
	 * récent au plus ancien : un changement en vigueur est `first` s'il ouvre la période, `confirmed` sinon, et porte la
	 * valeur du réglage si on l'annule ; un choix écrasé (`overwritten`) se range dans la période qui couvre sa date.
	 * Avant le premier changement, une période sans début porte la valeur qu'il a remplacée, NULL pour la
	 * redevabilité, qui n'en a pas. Une période qui n'a pas encore commencé est `isFuture`. Sans changement en vigueur,
	 * une seule période sans bornes porte le réglage et les choix écrasés.
	 *
	 * @param \Collection $cConfigurationHistory Tout l'historique du réglage, écrasés compris, du plus récent au plus
	 * ancien (getHistory())
	 * @return list<array{from: ?string, until: ?string, value: mixed, isFuture: bool, changes: list<array{eConfigurationHistory: ConfigurationHistory, type: string, valueAfterCancel: mixed}>}>
	 */
	public static function getPeriods(Configuration $eConfiguration, string $field, \Collection $cConfigurationHistory): array {

		$cConfigurationHistoryLive = $cConfigurationHistory->find(fn(ConfigurationHistory $e) => $e['overwrittenBy'] === NULL);
		$cConfigurationHistoryOverwritten = $cConfigurationHistory->find(fn(ConfigurationHistory $e) => $e['overwrittenBy'] !== NULL);

		$entries = $cConfigurationHistoryLive->makeArray(fn(ConfigurationHistory $e) => $e);
		$overwritten = $cConfigurationHistoryOverwritten->makeArray(fn(ConfigurationHistory $e) => $e);

		// Sans changement en vigueur, le réglage vaut pour toutes les dates : les choix écrasés qui restent se rangent sous lui
		if($entries === []) {

			if($overwritten === []) {
				return [];
			}

			usort($overwritten, fn(ConfigurationHistory $e1, ConfigurationHistory $e2) => strcmp($e2['effectiveAt'], $e1['effectiveAt']));

			return [[
				'from' => NULL,
				'until' => NULL,
				'value' => $eConfiguration[$field],
				'isFuture' => FALSE,
				'changes' => array_map(fn(ConfigurationHistory $eConfigurationHistory) => [
					'eConfigurationHistory' => $eConfigurationHistory,
					'type' => 'overwritten',
					'valueAfterCancel' => NULL,
				], $overwritten),
			]];

		}

		$valueBefore = ($field === 'vatChargeability') ? ConfigurationLib::getVatChargeabilityBefore($cConfigurationHistoryLive) : NULL;

		// Du plus ancien au plus récent, pour savoir quel changement ouvre sa période
		$periods = [];
		$value = $valueBefore;

		foreach(array_reverse($entries) as $eConfigurationHistory) {

			$valueNew = $eConfigurationHistory['value'][$field];

			$change = [
				'eConfigurationHistory' => $eConfigurationHistory,
				'type' => ($periods !== [] and $valueNew === $value) ? 'confirmed' : 'first',
				'valueAfterCancel' => self::getValueAfterCancel($eConfiguration, $field, $cConfigurationHistoryLive, $eConfigurationHistory['effectiveAt']),
			];

			if($change['type'] === 'first') {

				if($periods !== []) {
					$periods[array_key_last($periods)]['until'] = date('Y-m-d', strtotime($eConfigurationHistory['effectiveAt'].' - 1 day'));
				}

				$periods[] = [
					'from' => $eConfigurationHistory['effectiveAt'],
					'until' => NULL,
					'value' => $valueNew,
					'changes' => [],
				];

			}

			$periods[array_key_last($periods)]['changes'][] = $change;
			$value = $valueNew;

		}

		array_unshift($periods, [
			'from' => NULL,
			'until' => date('Y-m-d', strtotime($entries[array_key_last($entries)]['effectiveAt'].' - 1 day')),
			'value' => $valueBefore,
			'changes' => [],
		]);

		foreach($overwritten as $eConfigurationHistory) {

			foreach($periods as $key => $period) {

				if(
					($period['from'] === NULL or $period['from'] <= $eConfigurationHistory['effectiveAt']) and
					($period['until'] === NULL or $period['until'] >= $eConfigurationHistory['effectiveAt'])
				) {

					$periods[$key]['changes'][] = [
						'eConfigurationHistory' => $eConfigurationHistory,
						'type' => 'overwritten',
						'valueAfterCancel' => NULL,
					];

				}

			}

		}

		foreach($periods as $key => $period) {
			usort($periods[$key]['changes'], fn(array $change1, array $change2) => strcmp($change2['eConfigurationHistory']['effectiveAt'], $change1['eConfigurationHistory']['effectiveAt']));
			$periods[$key]['isFuture'] = ($period['from'] !== NULL and $period['from'] > \Clock::date());
		}

		return array_reverse($periods);

	}

	public static function deleteHistory(Structure $eStructure, string $effectiveAt, string $field): void {

		ConfigurationHistory::model()
			->whereStructure($eStructure)
			->whereEffectiveAt($effectiveAt)
			->whereField($field)
			->whereOverwrittenBy(NULL)
			->delete();

	}

}
