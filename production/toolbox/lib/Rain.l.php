<?php
namespace toolbox;

/**
 * Les relevés de pluviomètre, et ce que l'écran Pluviométrie en lit.
 *
 * Un relevé ajoute des mm à un jour (arbitrage Q2 A et Q7 B des planches pf-l8) : la table ne
 * porte qu'un total par pluviomètre et par jour, et un second relevé du même jour l'augmente au lieu
 * de créer une seconde ligne. La roue crantée d'un jour modifie ce total, ou le supprime.
 */
class RainLib extends RainCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['gauge', 'date', 'rain'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['rain'];
	}

	/**
	 * Ajoute le relevé au jour : une ligne neuve si le jour n'a encore rien, le total du jour augmenté
	 * sinon. L'`INSERT IGNORE` bute sur l'unicité (gauge, date), ce qui rend l'ajout sûr même quand
	 * deux personnes relèvent le même jour au même instant.
	 *
	 * Un relevé négatif retire des mm au jour, qui en porte déjà au moins autant (`Rain::rain.exceeds`) :
	 * il ne crée jamais de ligne, et le jour ramené à zéro est supprimé.
	 */
	public static function create(Rain $e): void {

		$e->expects(['structure', 'gauge', 'date', 'rain']);

		$affected = ($e['rain'] > 0) ? Rain::model()
			->option('add-ignore')
			->insert($e) : 0;

		if($affected === 0) {

			// La ligne du jour n'est pas `$e`, qui n'a pas d'id : l'écriture se désigne par le jour, et
			// garde donc un tableau de valeurs.
			Rain::model()
				->whereGauge($e['gauge'])
				->whereDate($e['date'])
				->update([
					'rain' => new \Sql('rain + '.(float)$e['rain']),
					'updatedAt' => new \Sql('NOW()')
				]);

			$eRain = Rain::model()
				->select(['id', 'rain'])
				->whereGauge($e['gauge'])
				->whereDate($e['date'])
				->get();

			if($eRain->notEmpty()) {

				// Un ajout qui n'insère rien ne pose pas d'id sur `$e` : celui de la ligne du jour se
				// relit ici, sans quoi l'élément sort de create() sans identifiant et l'appelant ne
				// peut plus désigner ce qu'il vient d'écrire. L'API en a besoin, sa réponse de
				// création relisant l'élément par son id.
				$e['id'] = $eRain['id'];

				if($e['rain'] < 0 and $eRain['rain'] <= 0) {
					self::delete($eRain);
				}

			}

		}

	}

	public static function update(Rain $e, array $properties): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	/**
	 * Ce que le jour porte déjà sur ce pluviomètre, lu par « Déjà ce jour-là » du formulaire de relevé.
	 */
	public static function getDay(Gauge $eGauge, string $date): float {

		return (float)Rain::model()
			->whereGauge($eGauge)
			->whereDate($date)
			->getValue('rain');

	}

	/**
	 * L'import d'un fichier CSV de relevés sur un pluviomètre (planche pf-l8-v9, arbitrée
	 * le 24/09). Le fichier lu attend en cache entre l'aperçu et l'import, comme pour les autres
	 * imports (`main\CsvLib::upload()`), sous une clé propre au pluviomètre : deux personnes qui
	 * importent sur deux pluviomètres de la même ferme ne se marchent pas dessus.
	 */
	protected static function getImportKey(Gauge $eGauge): string {
		return 'import-rain-'.$eGauge['id'];
	}

	public static function resetImport(Gauge $eGauge): void {
		\Cache::redis()->delete(self::getImportKey($eGauge));
	}

	/**
	 * Garde du fichier les deux colonnes `date` et `quantity`, dans l'ordre où elles viennent : un
	 * export de station météo porte souvent d'autres colonnes, qui ne gênent pas. La date passe par
	 * la lecture commune des imports, qui ramène JJ/MM/AAAA à AAAA-MM-JJ ; tout le reste se juge à
	 * l'aperçu, ligne par ligne.
	 */
	public static function uploadImport(Gauge $eGauge): bool {

		return \main\CsvLib::upload(self::getImportKey($eGauge), function(array $csv): ?array {

			$head = array_shift($csv);

			$date = array_search('date', $head, TRUE);
			$quantity = array_search('quantity', $head, TRUE);

			if($date === FALSE or $quantity === FALSE) {
				\Fail::log('main\csvSource');
				return NULL;
			}

			$lines = [];

			foreach($csv as $line) {

				$lineDate = trim($line[$date] ?? '');
				$lineQuantity = trim($line[$quantity] ?? '');

				// Une ligne vide en fin de fichier n'est pas une ligne fausse.
				if($lineDate === '' and $lineQuantity === '') {
					continue;
				}

				$lines[] = [
					'date' => \main\CsvLib::formatDateField($lineDate),
					'quantity' => $lineQuantity
				];

			}

			return $lines;

		});

	}

	/**
	 * L'aperçu du fichier en attente, NULL s'il n'y en a pas. Chaque ligne porte :
	 *
	 * - `isDate` : le jour lu est une date, que l'aperçu peut écrire à la française ;
	 * - `rain` : la quantité lue, NULL si elle n'est pas un nombre ;
	 * - `eRain` : le relevé que le jour porte déjà, vide sinon ;
	 * - `error` : la clé du refus (`Rain::…`), NULL pour une ligne qui s'importe ou qui est ignorée ;
	 * - `ignored` : une ligne à 0 mm, écartée sans erreur, un relevé ne valant jamais 0.
	 *
	 * Les règles de la saisie valent pour chaque ligne, et c'est build() qui les porte (jour valide,
	 * pas à venir, quantité dans les bornes du schéma). Deux restent propres au fichier : une quantité
	 * négative, que la saisie admet pour retirer des mm mais qu'un fichier ne peut qu'importer
	 * (arbitrage Q23 A, le fichier donne le total du jour), et un jour que le fichier porte deux fois,
	 * dont on ne saurait pas quelle ligne retenir.
	 */
	public static function getImport(Gauge $eGauge): ?array {

		$lines = \Cache::redis()->get(self::getImportKey($eGauge));

		if($lines === FALSE) {
			return NULL;
		}

		$dates = array_filter(array_column($lines, 'date'), fn($date) => \util\DateLib::isValid($date));

		$cRain = Rain::model()
			->select(['id', 'structure', 'date', 'rain'])
			->whereGauge($eGauge)
			->whereDate('IN', $dates)
			->getCollection(index: 'date');

		$import = [
			'lines' => [],
			'added' => 0,
			'replaced' => 0,
			'ignored' => 0,
			'errors' => 0,
			'first' => NULL,
			'last' => NULL
		];

		$seen = [];

		foreach($lines as $line) {

			$normalized = str_replace(',', '.', $line['quantity']);

			$line['isDate'] = \util\DateLib::isValid($line['date']);
			$line['rain'] = is_numeric($normalized) ? \main\CsvLib::formatFloat($normalized, 1) : NULL;
			$line['eRain'] = $cRain->offsetExists($line['date']) ? $cRain[$line['date']] : new Rain();
			$line['error'] = NULL;
			$line['ignored'] = FALSE;

			if($line['rain'] !== NULL and $line['rain'] < 0) {
				$line['error'] = 'Rain::rain.negative';
			} else if(in_array($line['date'], $seen, TRUE)) {
				$line['error'] = 'Rain::date.duplicate';
			} else if($line['rain'] === 0.0 and $line['isDate']) {
				$line['ignored'] = TRUE;
			} else {

				$eRainImported = new Rain([
					'structure' => $eGauge['structure'],
					'gauge' => $eGauge
				]);

				// Fail::log() compte un refus pour tous les FailWatch vivants : sans cette coupure, la ligne
				// que l'aperçu écarte ferait échouer l'import des autres. La PropertyList garde le refus.
				$l = new \PropertyList();

				\Fail::disable();

				try {
					$eRainImported->build(['date', 'rain'], ['date' => $line['date'], 'rain' => $line['rain']], $l);
				} finally {
					\Fail::enable();
				}

				// build() nomme son refus avec le package (`toolbox\Rain::date.future`), que Fail::log()
				// retire avant de le remettre à AlertUi : l'aperçu fait de même.
				$messages = $l->getInvalidMessages();
				$line['error'] = ($messages === []) ? NULL : preg_replace('/^[a-z]+\\\\/', '', $messages[0]);

			}

			$seen[] = $line['date'];

			if($line['error'] !== NULL) {
				$import['errors']++;
			} else if($line['ignored']) {
				$import['ignored']++;
			} else {

				if($line['eRain']->notEmpty()) {
					$import['replaced']++;
				} else {
					$import['added']++;
				}

				$import['first'] = min($import['first'] ?? $line['date'], $line['date']);
				$import['last'] = max($import['last'] ?? $line['date'], $line['date']);

			}

			$import['lines'][] = $line;

		}

		return $import;

	}

	/**
	 * Écrit les lignes justes du fichier en attente (arbitrage Q24 B, les fausses sont écartées) :
	 * la quantité du fichier devient le total du jour (arbitrage Q23 A), si bien que réimporter le
	 * même fichier ne change rien. C'est pourquoi l'import n'emprunte pas create(), qui ajoute au jour.
	 * L'aperçu est relu au moment d'écrire : un relevé noté entre l'aperçu et l'import est remplacé,
	 * pas doublé.
	 */
	public static function import(Gauge $eGauge): bool {

		$import = self::getImport($eGauge);

		if($import === NULL) {
			\Fail::log('Rain::importMissing');
			return FALSE;
		}

		Rain::model()->beginTransaction();

			foreach($import['lines'] as $line) {

				if($line['error'] !== NULL or $line['ignored']) {
					continue;
				}

				if($line['eRain']->notEmpty()) {

					$line['eRain']['rain'] = $line['rain'];
					self::update($line['eRain'], ['rain']);

				} else {

					$eRain = new Rain([
						'structure' => $eGauge['structure'],
						'gauge' => $eGauge,
						'date' => $line['date'],
						'rain' => $line['rain']
					]);

					Rain::model()->insert($eRain);

				}

			}

		Rain::model()->commit();

		self::resetImport($eGauge);

		return TRUE;

	}

	/**
	 * Les pluviomètres actifs de la structure, ceux qui n'ont encore aucun relevé compris : la ferme
	 * en tête (retour du 23/09), puis le plus récemment relevé, puis ceux qui attendent
	 * leur premier relevé. Chacun porte :
	 *
	 * - `lastDate` : le jour de son dernier relevé, NULL avant le premier, et `lastRain` : ses mm ;
	 * - `cumulative` : la pluie depuis le 1er janvier de l'année en cours, et `cumulativeYear` : cette
	 *   année, que la carte de la Boîte à outils donne en libellé du cumul (retour du 02/10) ;
	 * - `cumulativePrevious` : celle de l'année d'avant, du 1er janvier au même jour, pour comparer
	 *   deux cumuls à la même date et non un cumul partiel à une année entière ;
	 * - `cumulativeRecent` : la pluie des trente derniers jours, aujourd'hui compris (arbitrage Q15 B,
	 *   « remplace en 2025 par 30 derniers jours » sur la carte de la Boîte à outils) ;
	 * - `years` : pour chaque année qui porte un relevé, la plus récente en tête, `months` (la pluie
	 *   de chaque mois, de janvier à décembre), `days` (les jours relevés) et `total` (recette du
	 *   22/09, « comparer toutes les années renseignées » ; le nombre de jours dit ce qu'une année
	 *   peu notée doit à ses trous, arbitrage Q14 B) ;
	 * - `cRain` : ses relevés de l'année en cours et de celle d'avant, du plus récent au plus ancien.
	 */
	public static function getGauges(\structure\Structure $eStructure): \Collection {

		$cGauge = GaugeLib::getByStructure($eStructure);

		if($cGauge->empty()) {
			return $cGauge;
		}

		$year = \Clock::year();

		foreach($cGauge as $eGauge) {
			$eGauge['lastDate'] = NULL;
			$eGauge['lastRain'] = 0.0;
			$eGauge['cumulative'] = 0.0;
			$eGauge['cumulativeYear'] = $year;
			$eGauge['cumulativePrevious'] = 0.0;
			$eGauge['cumulativeRecent'] = 0.0;
			$eGauge['years'] = [];
			$eGauge['cRain'] = new \Collection();
		}

		$sameDayPrevious = ($year - 1).substr(\Clock::date(), 4);
		$recentSince = date('Y-m-d', strtotime(\Clock::date().' -29 days'));

		$cRain = Rain::model()
			->select(['id', 'gauge', 'date', 'rain'])
			->whereGauge('IN', $cGauge)
			->sort(['date' => SORT_DESC])
			->getCollection();

		foreach($cRain as $eRain) {

			$eGauge = $cGauge[$eRain['gauge']['id']];
			$rainYear = (int)substr($eRain['date'], 0, 4);
			$month = (int)substr($eRain['date'], 5, 2);

			// Les relevés arrivent du plus récent au plus ancien : le premier lu est le dernier relevé,
			// et les années se posent dans cet ordre.
			if($eGauge['lastDate'] === NULL) {
				$eGauge['lastDate'] = $eRain['date'];
				$eGauge['lastRain'] = $eRain['rain'];
			}

			if(array_key_exists($rainYear, $eGauge['years']) === FALSE) {
				$eGauge['years'][$rainYear] = ['months' => array_fill(1, 12, 0.0), 'days' => 0, 'total' => 0.0];
			}

			$eGauge['years'][$rainYear]['months'][$month] += $eRain['rain'];
			$eGauge['years'][$rainYear]['days']++;
			$eGauge['years'][$rainYear]['total'] += $eRain['rain'];

			if($rainYear === $year) {
				$eGauge['cumulative'] += $eRain['rain'];
			} else if($rainYear === $year - 1 and $eRain['date'] <= $sameDayPrevious) {
				$eGauge['cumulativePrevious'] += $eRain['rain'];
			}

			if($eRain['date'] >= $recentSince) {
				$eGauge['cumulativeRecent'] += $eRain['rain'];
			}

			if($rainYear >= $year - 1) {
				$eGauge['cRain'][] = $eRain;
			}

		}

		// Un jour ne se compare pas à NULL : un pluviomètre sans relevé passe après ceux qui en ont.
		return $cGauge->sort([
			fn(Gauge $eGauge1, Gauge $eGauge2) => (int)$eGauge1['zone']->notEmpty() <=> (int)$eGauge2['zone']->notEmpty(),
			fn(Gauge $eGauge1, Gauge $eGauge2) => ($eGauge2['lastDate'] ?? '') <=> ($eGauge1['lastDate'] ?? '')
		]);

	}


	/**
	 * Les relevés de pluie en CSV : une ligne par jour relevé des pluviomètres actifs de la structure,
	 * pluviomètre après pluviomètre dans l'ordre de leur pose, du plus ancien au plus récent. Un
	 * pluviomètre se reconnaît à son numéro, son lieu pouvant changer (« Changer de lieu ») ; le lieu
	 * est le nom de sa parcelle, vide pour celui de la ferme. Les mm sont le total du jour, celui que
	 * l'écran affiche.
	 */
	public static function getExport(\structure\Structure $eStructure): array {

		$cGauge = GaugeLib::getByStructure($eStructure);

		if($cGauge->empty()) {
			return [];
		}

		$cRain = Rain::model()
			->select(['gauge', 'date', 'rain'])
			->whereGauge('IN', $cGauge)
			->sort(['gauge' => SORT_ASC, 'date' => SORT_ASC])
			->getCollection();

		$output = [];

		foreach($cRain as $eRain) {

			$eGauge = $cGauge[$eRain['gauge']['id']];

			$output[] = [
				$eGauge['id'],
				$eGauge['zone']->empty() ? '' : $eGauge['zone']['name'],
				$eRain['date'],
				\util\TextUi::csvNumber($eRain['rain'], 1),
			];

		}

		return $output;

	}

}
?>
