<?php
namespace toolbox;

/**
 * Les compteurs d'eau d'une structure. Un compteur supprimé passe inactif et garde ses relevés en
 * base ; toute lecture part des seuls compteurs actifs.
 */
class WaterMeterLib extends WaterMeterCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'digits'];
	}

	// Le compteur qu'on pose à la place d'un autre (origine `change`) ne change que de chiffres : son
	// nom et son onglet restent.
	public static function getPropertiesUpdate(?string $origin = NULL): array {

		if($origin === 'change') {
			return ['digits'];
		}

		return ['name', 'digits', 'status'];

	}

	/**
	 * Le premier compteur fait de la consommation d'eau un outil de la structure
	 * (`Feature.hasToolbox`), qui le reste même quand tous ses compteurs ont été supprimés, comme la
	 * pluviométrie (`GaugeLib::create()`).
	 */
	public static function create(WaterMeter $e): void {

		$e->expects(['structure']);

		WaterMeter::model()->beginTransaction();

			parent::create($e);

			if(in_array(Favorite::WATER, $e['structure']->conf(\structure\Feature::class)['hasToolbox']) === FALSE) {
				\structure\FeatureLib::addToolbox($e['structure'], Favorite::WATER);
			}

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		WaterMeter::model()->commit();

	}

	/**
	 * Un compteur supprimé quitte le planning, où il attendait peut-être son relevé
	 * (`planning\DueLib::refresh()`), comme un relevé passé par la croix (`skipReading()`) ou décalé
	 * (`postponeReading()`). Seuls `status`, `readingSkippedDate` et `readingPostponedDate` l'y font
	 * entrer ou sortir : ses chiffres, que chaque changement de compteur réécrit
	 * (`WaterReadingLib::updateDigits()`), et son nom ne déplacent pas le jour où il se relève.
	 */
	public static function update(WaterMeter $e, array $properties): void {

		if(array_intersect(['status', 'readingSkippedDate', 'readingPostponedDate'], $properties) === []) {
			parent::update($e, $properties);
			return;
		}

		$e->expects(['structure']);

		WaterMeter::model()->beginTransaction();

			parent::update($e, $properties);

			\planning\DueLib::refresh($e['structure'], 'toolbox');

		WaterMeter::model()->commit();

	}

	/**
	 * La croix du planning sur un compteur d'eau à relever, comme sur celui d'un matériel
	 * (`EquipmentLib::skipReading()`), dont elle retient le jour de la même façon.
	 */
	public static function skipReading(WaterMeter $e): void {

		$e->expects(['id', 'structure', 'createdAt', 'readingSkippedDate']);

		$cWaterReadingLast = WaterReadingLib::getLastByMeters(new \Collection([$e['id'] => $e]));
		$readingLast = $cWaterReadingLast->offsetExists($e['id']) ? $cWaterReadingLast[$e['id']]['date'] : NULL;

		$dueDate = NotificationLib::getReadingDate(WaterConfigurationLib::getReadingEveryMonths($e['structure']), $readingLast, $e['createdAt'], $e['readingSkippedDate']);

		$e['readingSkippedDate'] = max(\Clock::date(), $dueDate);

		self::update($e, ['readingSkippedDate']);

	}

	/**
	 * Le décalage à la semaine suivante d'un compteur d'eau à relever, comme celui d'un matériel
	 * (`EquipmentLib::postponeReading()`).
	 */
	public static function postponeReading(WaterMeter $e): void {

		// Sans `build()`, validé le 04/10 : rien de posté ne devient la propriété, la date se
		// calcule ici depuis la ligne du planning, comme celle de `skipReading()`.
		$e['readingPostponedDate'] = \planning\DueLib::getPostponedDate($e['structure'], PlanningObserverLib::WATER_READING, $e['id']);

		self::update($e, ['readingPostponedDate']);

	}

	/**
	 * Les compteurs actifs qu'aucun relevé n'a touchés depuis `$since`, jamais relevés compris : ceux
	 * que le rappel des compteurs d'eau nomme (`NotificationLib::WATER_READING`). Sur tout le parc pour
	 * le cron, bornés par la structure pour la relecture d'une notification.
	 *
	 * Un anti-join, comme `EquipmentLib::getUnreadSince()` : le compteur à gauche, ses relevés récents
	 * à droite, la borne de date dans le `ON` pour que le compteur sans relevé récent reste une ligne.
	 * La clé unique (meter, date) sert la jointure.
	 */
	public static function getUnreadSince(string $since, \structure\Structure $eStructure = new \structure\Structure()): \Collection {

		return WaterMeter::model()
			->select(['id', 'structure', 'name'])
			->join(WaterReading::model(), 'm1.id = m2.meter AND m2.date >= '.WaterReading::model()->format($since), 'LEFT')
			->where('m2.id IS NULL')
			->where('m1.structure', $eStructure, if: $eStructure->notEmpty())
			->where('m1.status', WaterMeter::ACTIVE)
			->getCollection(index: 'id');

	}

	/**
	 * Les compteurs actifs de la structure, toujours dans l'ordre de leur création : un compteur garde
	 * la place de son onglet d'une visite à l'autre.
	 */
	public static function getByStructure(\structure\Structure $eStructure): \Collection {

		return WaterMeter::model()
			->select(['id', 'structure', 'name', 'digits', 'readingSkippedDate', 'readingPostponedDate', 'status', 'createdAt'])
			->whereStructure($eStructure)
			->whereStatus(WaterMeter::ACTIVE)
			->sort(['id' => SORT_ASC])
			->getCollection(index: 'id');

	}

}
?>
