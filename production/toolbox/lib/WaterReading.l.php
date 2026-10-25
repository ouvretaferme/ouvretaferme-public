<?php
namespace toolbox;

/**
 * Les relevés de compteur d'eau, et ce que l'écran Consommation d'eau en lit.
 *
 * Un relevé dit ce que le compteur affiche un jour : la table n'en porte qu'un par compteur et par
 * jour, et un second relevé du même jour remplace le premier. Ce qui a été consommé n'est écrit
 * nulle part : c'est l'écart entre deux relevés qui se suivent, relu à chaque affichage, si bien
 * qu'un relevé corrigé, ajouté dans le passé ou supprimé redresse seul ceux qui l'entourent.
 *
 * Un compteur remplacé garde son onglet, son historique et ses courbes : le relevé du jour du
 * changement (`changed`) porte ce que l'ancien affichait à la dépose (`changedValue`) et ce que le
 * nouveau affiche à la pose (`value`), d'où les relevés repartent. Chaque changement garde le nombre
 * de chiffres du compteur qu'il remplace (`changedDigits`), `WaterMeter.digits` étant celui du
 * compteur en place : le passé se compte et se borne avec les chiffres qu'il avait (`getDigits()`).
 */
class WaterReadingLib extends WaterReadingCrud {

	// Un changement de compteur se déclare depuis la roue du compteur, qui est donc connu (origine
	// `change`) : il reste le jour, la dépose de l'ancien et la pose du nouveau.
	public static function getPropertiesCreate(?string $origin = NULL): array {

		if($origin === 'change') {
			return ['date', 'changedValue', 'value'];
		}

		return ['meter', 'date', 'value'];

	}

	// Un relevé se corrige depuis la liste : son jour reste, seul le compteur change. Le relevé d'un
	// changement de compteur en porte deux, la dépose et la pose ; l'API ne déclare pas de changement,
	// donc elle n'en corrige pas la dépose.
	public static function getPropertiesUpdate(?string $origin = NULL): array|\Closure {

		if($origin === 'api') {
			return ['value'];
		}

		return fn(WaterReading $e) => $e['changed'] ? ['changedValue', 'value'] : ['value'];

	}

	/**
	 * Pose le relevé du jour, ou remplace celui qui y est. L'`INSERT IGNORE` bute sur l'unicité
	 * (meter, date), ce qui rend l'écriture sûre même quand deux personnes relèvent le même compteur
	 * au même instant.
	 */
	public static function create(WaterReading $e): void {

		$e->expects(['structure', 'meter', 'date', 'value']);

		$affected = WaterReading::model()
			->option('add-ignore')
			->insert($e);

		if($affected === 0) {

			// La ligne du jour n'est pas `$e`, qui n'a pas d'id : l'écriture se désigne par le jour, et
			// garde donc un tableau de valeurs.
			WaterReading::model()
				->whereMeter($e['meter'])
				->whereDate($e['date'])
				->update([
					'value' => $e['value'],
					'updatedAt' => new \Sql('NOW()')
				]);

			// Un relevé qui en remplace un autre ne pose pas d'id sur `$e` : celui de la ligne du jour
			// se relit ici, l'API relisant ce qu'elle vient d'écrire par son id.
			$e['id'] = WaterReading::model()
				->whereMeter($e['meter'])
				->whereDate($e['date'])
				->getValue('id');

		}

		\planning\DueLib::refresh($e['structure'], 'toolbox');

	}

	/**
	 * Déclare le remplacement du compteur au jour de `$e` : `$e['value']` est ce que le nouveau affiche
	 * à la pose, `$e['changedValue']` ce que l'ancien affichait à la dépose ou NULL, et `$e['meter']`
	 * porte le nombre de chiffres du nouveau. Le relevé garde celui de l'ancien, lu avant que le
	 * nouveau ne s'écrive. Un jour déjà relevé devient le jour du changement, comme un second relevé
	 * remplace le premier.
	 */
	public static function createChange(WaterReading $e): void {

		$e->expects(['structure', 'meter' => ['digits'], 'date', 'value', 'changedValue']);

		WaterReading::model()->beginTransaction();

			$digits = $e['meter']['digits'];

			$e['changed'] = TRUE;
			$e['changedDigits'] = self::getDigitsReplaced($e['meter'], $e['date']);

			$affected = WaterReading::model()
				->option('add-ignore')
				->insert($e);

			if($affected === 0) {

				$e['id'] = WaterReading::model()
					->whereMeter($e['meter'])
					->whereDate($e['date'])
					->getValue('id');

				self::update($e, ['value', 'changed', 'changedValue', 'changedDigits']);

			}

			self::updateDigits($e['meter'], $e['date'], $digits);

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		WaterReading::model()->commit();

	}

	// Une correction ne touche jamais la date du relevé, seule à décider du jour où le planning rappelle
	// le compteur : contrairement à la création et à la suppression, elle n'a rien à recalculer.
	public static function update(WaterReading $e, array $properties): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	/**
	 * Supprimer le relevé d'un changement rend les relevés suivants au cycle d'avant : les deux cycles
	 * n'en font plus qu'un, qui prend le plus grand de leurs deux nombres de chiffres pour qu'aucun de
	 * ses relevés ne dépasse sa borne.
	 */
	public static function delete(WaterReading $e): void {

		$e->expects(['structure', 'meter', 'date', 'changed', 'changedDigits']);

		WaterReading::model()->beginTransaction();

			parent::delete($e);

			if($e['changed']) {
				self::updateDigits($e['meter'], $e['date'], max($e['changedDigits'], self::getDigits($e['meter'], $e['date'])));
			}

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		WaterReading::model()->commit();

	}

	/**
	 * Le changement de compteur qui suit `$date`, ou un élément vide : c'est lui qui garde le nombre
	 * de chiffres du compteur en place ce jour-là.
	 */
	protected static function getChangeAfter(WaterMeter $eWaterMeter, string $date): WaterReading {

		return WaterReading::model()
			->select(['id', 'changedDigits'])
			->whereMeter($eWaterMeter)
			->whereChanged(TRUE)
			->whereDate('>', $date)
			->sort(['date' => SORT_ASC])
			->get();

	}

	/**
	 * Le nombre de chiffres du compteur en place au jour `$date`, pour un relevé posé ce jour-là :
	 * celui que garde le premier changement qui suit, sinon celui du compteur, relu en base parce que
	 * l'élément peut déjà porter les chiffres d'un compteur qu'on est en train de poser.
	 */
	public static function getDigits(WaterMeter $eWaterMeter, string $date): int {

		$eWaterReadingChange = self::getChangeAfter($eWaterMeter, $date);

		if($eWaterReadingChange->notEmpty()) {
			return $eWaterReadingChange['changedDigits'];
		}

		return WaterMeter::model()
			->whereId($eWaterMeter)
			->getValue('digits');

	}

	/**
	 * Le nombre de chiffres du compteur que remplace un changement déclaré au jour `$date` : celui du
	 * compteur en place ce jour-là, ou, quand le jour porte déjà un changement qu'on redéclare, celui
	 * que ce changement garde.
	 */
	public static function getDigitsReplaced(WaterMeter $eWaterMeter, string $date): int {

		$digits = WaterReading::model()
			->whereMeter($eWaterMeter)
			->whereDate($date)
			->whereChanged(TRUE)
			->getValue('changedDigits');

		return $digits ?? self::getDigits($eWaterMeter, $date);

	}

	/**
	 * Écrit le nombre de chiffres du compteur en place à partir du jour `$date`, là où `getDigits()`
	 * le lit.
	 */
	protected static function updateDigits(WaterMeter $eWaterMeter, string $date, int $digits): void {

		$eWaterReadingChange = self::getChangeAfter($eWaterMeter, $date);

		if($eWaterReadingChange->notEmpty()) {

			$eWaterReadingChange['changedDigits'] = $digits;
			self::update($eWaterReadingChange, ['changedDigits']);

		} else {

			$eWaterMeter['digits'] = $digits;
			WaterMeterLib::update($eWaterMeter, ['digits']);

		}

	}

	/**
	 * Le plus haut relevé du compteur en place, 0 sans relevé : ce sous quoi son nombre de chiffres ne
	 * descend pas (étape `readings` de `WaterMeter::properties()`). Les relevés d'avant son dernier
	 * changement sont ceux d'un autre compteur, qui avait ses propres chiffres.
	 */
	public static function getHighest(WaterMeter $eWaterMeter): float {

		$date = WaterReading::model()
			->whereMeter($eWaterMeter)
			->whereChanged(TRUE)
			->getValue(new \Sql('MAX(date)'));

		return (float)WaterReading::model()
			->whereMeter($eWaterMeter)
			->whereDate('>=', $date, if: $date !== NULL)
			->getValue(new \Sql('MAX(value)', 'float'));

	}

	/**
	 * Le relevé `$id` du compteur `$eWaterMeter` que l'on propose de déclarer comme la pose d'un nouveau
	 * compteur, ou un élément vide : un id d'un autre compteur, ou d'un jour qui est déjà un changement,
	 * ne préremplit rien.
	 */
	public static function getAlertable(WaterMeter $eWaterMeter, int $id): WaterReading {

		if($id === 0) {
			return new WaterReading();
		}

		return WaterReading::model()
			->select(['id', 'date', 'value'])
			->whereMeter($eWaterMeter)
			->whereId($id)
			->whereChanged(FALSE)
			->get();

	}

	/**
	 * Le dernier relevé du compteur au jour `$date`, ce jour compris, ou un élément vide.
	 */
	public static function getByMeterAndDate(WaterMeter $eWaterMeter, string $date): WaterReading {

		return WaterReading::model()
			->select(['id', 'date', 'value'])
			->whereMeter($eWaterMeter)
			->whereDate('<=', $date)
			->sort(['date' => SORT_DESC])
			->get();

	}

	/**
	 * Le dernier relevé de chacun des compteurs `$cWaterMeter`, indexé par compteur, ceux qui n'ont
	 * jamais été relevés absents. La citation part de `new WaterReadingModel()` : le modèle est un
	 * singleton, et la requête citée viderait celle qui la cite.
	 */
	public static function getLastByMeters(\Collection $cWaterMeter): \Collection {

		if($cWaterMeter->empty()) {
			return new \Collection();
		}

		return WaterReading::model()
			->select(['id', 'meter', 'date', 'value'])
			->where(
				new \Sql('(m1.meter, m1.date)'),
				'IN',
				new WaterReadingModel()
					->select(['meter', 'date' => new \Sql('MAX(date)', 'date')])
					->whereMeter('IN', $cWaterMeter)
					->group('meter')
					->getSql()
			)
			->getCollection(index: 'meter');

	}

	/**
	 * Le relevé qui précède `$date` sur ce compteur, ou un élément vide.
	 */
	public static function getPrevious(WaterMeter $eWaterMeter, string $date): WaterReading {

		return WaterReading::model()
			->select(['id', 'date', 'value'])
			->whereMeter($eWaterMeter)
			->whereDate('<', $date)
			->sort(['date' => SORT_DESC])
			->get();

	}

	/**
	 * Ce qui a été consommé entre deux relevés qui se suivent. Un relevé plus bas que le précédent est
	 * un compteur qui a fait le tour (arbitrage Q2 C de la planche de l'eau) : il a compté jusqu'au
	 * bout de ses `$digits` chiffres, puis de zéro jusqu'au relevé.
	 */
	public static function getConsumption(int $digits, float $valuePrevious, float $value): float {

		if($value < $valuePrevious) {
			return round(10 ** $digits - $valuePrevious + $value, 1);
		} else {
			return round($value - $valuePrevious, 1);
		}

	}

	/**
	 * Ce que le relevé `$e` qui vient d'être écrit a compté depuis le relevé précédent : les m³
	 * (`consumption`), le jour d'où ils se comptent (`date`), si le compteur a fait le tour pour y
	 * arriver (`turned`), et si l'écart dépasse le seuil d'alerte (`alert`, `isAlert()`). Un tableau vide pour le premier relevé d'un compteur, et un
	 * jour de changement de compteur : le relevé y dit ce que le nouveau compteur affiche, que rien ne
	 * relie à l'ancien.
	 */
	public static function getGap(WaterReading $e): array {

		$e->expects(['meter', 'date', 'value']);

		$eWaterReadingPrevious = self::getPrevious($e['meter'], $e['date']);

		if($eWaterReadingPrevious->empty()) {
			return [];
		}

		$changed = WaterReading::model()
			->whereMeter($e['meter'])
			->whereDate($e['date'])
			->getValue('changed');

		if($changed) {
			return [];
		}

		$digits = self::getDigits($e['meter'], $e['date']);
		$consumption = self::getConsumption($digits, $eWaterReadingPrevious['value'], $e['value']);

		return [
			'consumption' => $consumption,
			'date' => $eWaterReadingPrevious['date'],
			'turned' => ($e['value'] < $eWaterReadingPrevious['value']),
			'alert' => self::isAlert($digits, $consumption)
		];

	}

	/**
	 * Un écart entre deux relevés trop grand pour être vraisemblable, que l'écran signale.
	 */
	public static function isAlert(int $digits, ?float $consumption): bool {
		return ($consumption !== NULL and $consumption > self::getAlertLimit($digits));
	}

	/**
	 * L'écart au-delà duquel un relevé se signale : la moitié de ce que le compteur peut afficher
	 * (retour du 03/10, « alerte si l'écart > taille maximale du compteur / 2 », 5 000 m³ sur
	 * quatre chiffres). Un seuil fixe ne valait que pour une taille : 1 000 m³ signalait chaque saison
	 * d'un gros compteur, et laissait passer le tour presque complet d'un petit. Au-delà de la moitié,
	 * un compteur a plus sûrement été remplacé sans être déclaré, ou mal relevé, qu'il n'a tourné.
	 */
	public static function getAlertLimit(int $digits): float {
		return 10 ** $digits / 2;
	}

	/**
	 * Les compteurs actifs de la structure, dans l'ordre de leur création, ceux qui n'ont encore aucun
	 * relevé compris. Chacun porte :
	 *
	 * - `cWaterReading` : tous ses relevés, du plus ancien au plus récent, chacun avec `consumption`
	 *   (ce qui a été consommé depuis le relevé d'avant, NULL pour le premier), `turned` (le compteur
	 *   a fait le tour pour y arriver), `alert` (l'écart dépasse le seuil d'alerte, `isAlert()`), `alertLimit` (ce seuil, qui suit les chiffres du compteur en place) et `cumulative` (les m³ consommés depuis le premier relevé du
	 *   compteur, qui ne retombent ni au tour du compteur ni à son remplacement, et sur lesquels tout
	 *   le reste se lit). Le relevé d'un changement de compteur compte ce que l'ancien a consommé
	 *   jusqu'à sa dépose, 0 quand elle n'a pas été relevée, et le relevé suivant part de la pose ;
	 * - `eWaterReadingLast` : son dernier relevé, vide avant le premier, et `eWaterReadingPrevious` :
	 *   celui d'avant, vide tant qu'il n'y en a qu'un ;
	 * - `year` : les m³ consommés cette année jusqu'au dernier relevé, NULL quand rien ne permet de le
	 *   dire (aucun relevé cette année, ou un seul relevé en tout), et `yearSince` : le jour d'où ils
	 *   se comptent. Le compteur au 1er janvier se lit sur la droite entre le dernier relevé de
	 *   l'année d'avant et le premier de celle-ci, règle du carnet du matériel
	 *   (`EquipmentReadingLib::getYear()`) ; un compteur relevé pour la première fois en cours d'année
	 *   ne sait pas ce qu'il affichait au 1er janvier, et compte depuis son premier relevé de l'année,
	 *   ce que `yearSince` dit ;
	 * - `yearPrevious` : les m³ de l'année d'avant, du 1er janvier au même jour que le dernier relevé,
	 *   lus sur la même droite, NULL quand les relevés ne couvrent pas la période ;
	 * - `years` : ses courbes, une par année (`getYears()`).
	 */
	public static function getMeters(\structure\Structure $eStructure): \Collection {

		$cWaterMeter = WaterMeterLib::getByStructure($eStructure);

		if($cWaterMeter->empty()) {
			return $cWaterMeter;
		}

		foreach($cWaterMeter as $eWaterMeter) {
			$eWaterMeter['cWaterReading'] = new \Collection();
		}

		$cWaterReading = WaterReading::model()
			->select(['id', 'structure', 'meter', 'date', 'value', 'changed', 'changedValue', 'changedDigits'])
			->whereMeter('IN', $cWaterMeter)
			->sort(['date' => SORT_ASC])
			->getCollection();

		foreach($cWaterReading as $eWaterReading) {

			$eWaterMeter = $cWaterMeter[$eWaterReading['meter']['id']];

			// Le relevé porte son compteur chargé : c'est lui que son droit et sa modification lisent.
			$eWaterReading['meter'] = $eWaterMeter;

			$eWaterMeter['cWaterReading'][] = $eWaterReading;

		}

		foreach($cWaterMeter as $eWaterMeter) {
			self::fillMeter($eWaterMeter);
		}

		return $cWaterMeter;

	}

	/**
	 * Tous les compteurs de la structure réunis, pour l'onglet « Total » de l'écran et la carte de la
	 * Boîte à outils. Les compteurs sont indépendants, leurs m³ s'additionnent :
	 *
	 * - `cWaterMeter` : les compteurs de `getMeters()`, chacun avec `share`, sa part en % de ce qui a
	 *   été consommé cette année, NULL quand il n'a pas de chiffre de l'année ou que rien n'a été
	 *   consommé ;
	 * - `year` : la somme des compteurs qui ont un chiffre de l'année, NULL quand aucun n'en a, et
	 *   `yearSince` : le plus ancien des jours d'où ils comptent, le 1er janvier dès qu'un compteur
	 *   était déjà relevé l'an passé ;
	 * - `yearPrevious` : la somme des compteurs qui ont un chiffre de l'an passé, NULL quand aucun
	 *   n'en a. Un compteur posé cette année n'y entre pas : il n'a rien compté l'an passé ;
	 * - `eWaterReadingLast` : le relevé le plus récent, tous compteurs confondus, vide avant le premier ;
	 * - `years` : les courbes de tous les compteurs réunis, une par année (`getYearsTotal()`).
	 */
	public static function getSummary(\structure\Structure $eStructure): array {

		$cWaterMeter = self::getMeters($eStructure);

		$year = NULL;
		$yearSince = NULL;
		$yearPrevious = NULL;
		$eWaterReadingLast = new WaterReading();

		foreach($cWaterMeter as $eWaterMeter) {

			if($eWaterMeter['year'] !== NULL) {
				$year = ($year ?? 0.0) + $eWaterMeter['year'];
				$yearSince = min($yearSince ?? $eWaterMeter['yearSince'], $eWaterMeter['yearSince']);
			}

			if($eWaterMeter['yearPrevious'] !== NULL) {
				$yearPrevious = ($yearPrevious ?? 0.0) + $eWaterMeter['yearPrevious'];
			}

			if(
				$eWaterMeter['eWaterReadingLast']->notEmpty() and
				($eWaterReadingLast->empty() or $eWaterMeter['eWaterReadingLast']['date'] > $eWaterReadingLast['date'])
			) {
				$eWaterReadingLast = $eWaterMeter['eWaterReadingLast'];
			}

		}

		foreach($cWaterMeter as $eWaterMeter) {
			$eWaterMeter['share'] = ($eWaterMeter['year'] !== NULL and $year > 0) ? (int)round($eWaterMeter['year'] / $year * 100) : NULL;
		}

		return [
			'cWaterMeter' => $cWaterMeter,
			'year' => $year,
			'yearSince' => $yearSince,
			'yearPrevious' => $yearPrevious,
			'eWaterReadingLast' => $eWaterReadingLast,
			'years' => self::getYearsTotal($cWaterMeter)
		];

	}

	/**
	 * Les relevés en CSV : une ligne par relevé des compteurs `$cWaterMeter` de `getMeters()`, compteur
	 * après compteur dans l'ordre de leurs onglets, du plus ancien au plus récent. La consommation est
	 * celle que l'écran affiche sur la ligne du relevé, tour du compteur compris, vide sur le premier
	 * relevé d'un compteur, qui n'a rien à quoi se comparer. Les deux dernières colonnes ne sont
	 * remplies que le jour d'un changement de compteur : ce que l'ancien affichait à la dépose, quand
	 * on le sait, le relevé étant alors ce que le nouveau affiche à la pose.
	 */
	public static function getExport(\Collection $cWaterMeter): array {

		$output = [];

		foreach($cWaterMeter as $eWaterMeter) {

			foreach($eWaterMeter['cWaterReading'] as $eWaterReading) {

				$output[] = [
					$eWaterMeter['name'],
					$eWaterReading['date'],
					\util\TextUi::csvNumber($eWaterReading['value'], 1),
					\util\TextUi::csvNumber($eWaterReading['consumption'], 1),
					$eWaterReading['changed'] ? 'yes' : 'no',
					\util\TextUi::csvNumber($eWaterReading['changedValue'], 1),
				];

			}

		}

		return $output;

	}

	/**
	 * Pose sur le compteur et sur ses relevés `cWaterReading`, triés par date, ce que `getMeters()`
	 * annonce.
	 */
	protected static function fillMeter(WaterMeter $eWaterMeter): void {

		// Le nombre de chiffres avec lequel chaque relevé se compare au précédent : celui du compteur en
		// place la veille, que garde le premier changement venu depuis, le relevé du changement compris
		// puisque sa dépose se lit sur l'ancien compteur.
		$digits = $eWaterMeter['digits'];
		$digitsByDate = [];

		foreach($eWaterMeter['cWaterReading']->reverse() as $eWaterReading) {

			if($eWaterReading['changed']) {
				$digits = $eWaterReading['changedDigits'];
			}

			$digitsByDate[$eWaterReading['date']] = $digits;

		}

		$eWaterReadingPrevious = new WaterReading();
		$eWaterReadingLast = new WaterReading();

		foreach($eWaterMeter['cWaterReading'] as $eWaterReading) {

			$eWaterReading['alertLimit'] = self::getAlertLimit($digitsByDate[$eWaterReading['date']]);

			if($eWaterReadingLast->empty()) {

				$eWaterReading['consumption'] = NULL;
				$eWaterReading['turned'] = FALSE;
				$eWaterReading['alert'] = FALSE;
				$eWaterReading['cumulative'] = 0.0;

			} else {

				// Le jour d'un changement, c'est la dépose de l'ancien compteur qui se compare au relevé
				// d'avant : sans elle, rien n'a pu être compté.
				$value = $eWaterReading['changed'] ? $eWaterReading['changedValue'] : $eWaterReading['value'];

				if($value === NULL) {
					$eWaterReading['consumption'] = 0.0;
					$eWaterReading['turned'] = FALSE;
				} else {
					$eWaterReading['consumption'] = self::getConsumption($digitsByDate[$eWaterReading['date']], $eWaterReadingLast['value'], $value);
					$eWaterReading['turned'] = ($value < $eWaterReadingLast['value']);
				}

				// L'écart signalé reste compté tant qu'il n'est pas corrigé : on le suggère, on ne réécrit
				// pas les chiffres à la place de celui qui a relevé.
				$eWaterReading['alert'] = self::isAlert($digitsByDate[$eWaterReading['date']], $eWaterReading['consumption']);
				$eWaterReading['cumulative'] = round($eWaterReadingLast['cumulative'] + $eWaterReading['consumption'], 1);

			}

			$eWaterReadingPrevious = $eWaterReadingLast;
			$eWaterReadingLast = $eWaterReading;

		}

		$eWaterMeter['eWaterReadingLast'] = $eWaterReadingLast;
		$eWaterMeter['eWaterReadingPrevious'] = $eWaterReadingPrevious;

		[$eWaterMeter['year'], $eWaterMeter['yearSince'], $eWaterMeter['yearPrevious']] = self::getYear($eWaterMeter['cWaterReading']);

		$eWaterMeter['years'] = self::getYears($eWaterMeter['cWaterReading']);

	}

	/**
	 * Les m³ consommés au jour `$date`, comptés depuis le premier relevé, lus dans les relevés
	 * `$cWaterReading` triés par date : le relevé du jour, ou la droite entre celui d'avant et celui
	 * d'après, l'eau se consommant à peu près régulièrement entre deux relevés. NULL avant le premier
	 * relevé et après le dernier, où rien ne permet de le savoir.
	 */
	public static function getCumulativeAt(\Collection $cWaterReading, string $date): ?float {

		$eWaterReadingBefore = new WaterReading();

		foreach($cWaterReading as $eWaterReading) {

			if($eWaterReading['date'] === $date) {
				return $eWaterReading['cumulative'];
			}

			if($eWaterReading['date'] > $date) {

				if($eWaterReadingBefore->empty()) {
					return NULL;
				}

				$days = (strtotime($eWaterReading['date']) - strtotime($eWaterReadingBefore['date'])) / 86400;
				$elapsed = (strtotime($date) - strtotime($eWaterReadingBefore['date'])) / 86400;

				return $eWaterReadingBefore['cumulative'] + ($eWaterReading['cumulative'] - $eWaterReadingBefore['cumulative']) * $elapsed / $days;

			}

			$eWaterReadingBefore = $eWaterReading;

		}

		return NULL;

	}

	/**
	 * `[year, yearSince, yearPrevious]` d'un compteur, depuis ses relevés triés par date et déjà
	 * cumulés (`getMeters()`), les deux chiffres en m³ entiers.
	 */
	protected static function getYear(\Collection $cWaterReading): array {

		if($cWaterReading->count() < 2) {
			return [NULL, NULL, NULL];
		}

		$eWaterReadingLast = $cWaterReading->last();

		$year = \Clock::year();
		$yearStart = $year.'-01-01';
		$yearPreviousStart = ($year - 1).'-01-01';

		if($eWaterReadingLast['date'] < $yearStart) {
			return [NULL, NULL, NULL];
		}

		$start = self::getCumulativeAt($cWaterReading, $yearStart);
		$since = $yearStart;

		if($start === NULL) {

			// Aucun relevé avant le 1er janvier : le premier relevé du compteur est de cette année,
			// et son cumul part de zéro.
			$start = 0.0;
			$since = $cWaterReading->first()['date'];

		}

		$startPrevious = self::getCumulativeAt($cWaterReading, $yearPreviousStart);
		$endPrevious = self::getCumulativeAt($cWaterReading, ($year - 1).substr($eWaterReadingLast['date'], 4));

		// Des m³ entiers : le compteur au 1er janvier est lu sur une droite, et un dixième de m³ dirait
		// une précision que cette lecture n'a pas.
		return [
			round($eWaterReadingLast['cumulative'] - $start),
			$since,
			($startPrevious === NULL or $endPrevious === NULL) ? NULL : round($endPrevious - $startPrevious)
		];

	}

	/**
	 * Les courbes d'un compteur, depuis ses relevés triés par date et déjà cumulés (`getMeters()`) :
	 * `[année => [jour => m³]]`, un point par relevé, les m³ entiers consommés depuis le début de
	 * l'année. La règle est celle du chiffre de l'année (`getYear()`), donc le dernier point de
	 * l'année en cours vaut ce chiffre :
	 *
	 * - quand un relevé précède le 1er janvier, la courbe part de 0 ce jour-là, le compteur y étant lu
	 *   sur la droite entre les deux relevés qui l'entourent ; sinon elle part du premier relevé ;
	 * - une année que d'autres relevés suivent se ferme au 31 décembre, lu sur la même droite, pour
	 *   que son dernier point dise l'année entière ;
	 * - une année qui n'a qu'un point avant cette fermeture n'a pas d'entrée : un compteur posé fin
	 *   décembre ne fait pas de ses derniers jours une année à comparer, ni une année sans relevé.
	 */
	protected static function getYears(\Collection $cWaterReading): array {

		if($cWaterReading->count() < 2) {
			return [];
		}

		$cumulatives = [];

		foreach($cWaterReading as $eWaterReading) {
			$cumulatives[(int)substr($eWaterReading['date'], 0, 4)][$eWaterReading['date']] = $eWaterReading['cumulative'];
		}

		$yearFirst = array_key_first($cumulatives);
		$yearLast = array_key_last($cumulatives);

		$years = [];

		for($year = $yearFirst; $year <= $yearLast; $year++) {

			$points = [];

			$start = self::getCumulativeAt($cWaterReading, $year.'-01-01');

			if($start === NULL) {
				// Seule l'année du premier relevé n'a rien avant son 1er janvier, et son cumul part de zéro.
				$start = 0.0;
			} else {
				$points[$year.'-01-01'] = 0;
			}

			foreach($cumulatives[$year] ?? [] as $date => $cumulative) {
				$points[$date] = (int)round($cumulative - $start);
			}

			if(count($points) < 2) {
				continue;
			}

			if($year < $yearLast) {
				$points[$year.'-12-31'] = (int)round(self::getCumulativeAt($cWaterReading, $year.'-12-31') - $start);
			}

			$years[$year] = $points;

		}

		return $years;

	}

	/**
	 * Les courbes de tous les compteurs réunis, `[année => [jour => m³]]`, un point à chaque jour où
	 * l'un d'eux en porte un. Les compteurs ne sont pas relevés les mêmes jours : chacun est lu sur sa
	 * propre courbe (`getYearAt()`), puis ils s'additionnent.
	 */
	protected static function getYearsTotal(\Collection $cWaterMeter): array {

		$dates = [];

		foreach($cWaterMeter as $eWaterMeter) {
			foreach($eWaterMeter['years'] as $year => $points) {
				$dates[$year] = ($dates[$year] ?? []) + $points;
			}
		}

		ksort($dates);

		$years = [];

		foreach($dates as $year => $points) {

			ksort($points);

			foreach(array_keys($points) as $date) {

				$years[$year][$date] = 0;

				foreach($cWaterMeter as $eWaterMeter) {
					$years[$year][$date] += self::getYearAt($eWaterMeter['years'][$year] ?? [], $date);
				}

			}

		}

		return $years;

	}

	/**
	 * Ce qu'une courbe `[jour => m³]` d'une année vaut au jour `$date` : rien avant son premier point,
	 * la droite entre les deux points qui entourent le jour, et sa dernière valeur ensuite, un compteur
	 * qui n'a plus été relevé n'ayant pas rendu ce qu'il a compté.
	 */
	protected static function getYearAt(array $points, string $date): int {

		$dateBefore = NULL;
		$value = 0;

		foreach($points as $datePoint => $valuePoint) {

			if($datePoint === $date) {
				return $valuePoint;
			}

			if($datePoint > $date) {

				if($dateBefore === NULL) {
					return 0;
				}

				$days = (strtotime($datePoint) - strtotime($dateBefore)) / 86400;
				$elapsed = (strtotime($date) - strtotime($dateBefore)) / 86400;

				return (int)round($value + ($valuePoint - $value) * $elapsed / $days);

			}

			$dateBefore = $datePoint;
			$value = $valuePoint;

		}

		return $value;

	}

}
?>
