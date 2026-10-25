<?php
namespace learning;

/**
 * Les séances d'une date (lot date-mixte). Elles ne s'écrivent qu'avec leur date, sous sa
 * transaction : `DateLib::create()` et `update()` les remplacent toutes à la fois par celles que le
 * formulaire a postées, `DateLib::delete()` et `deleteByCourse()` les effacent avec elle.
 */
class DateSequenceLib extends DateSequenceCrud {

	/**
	 * `startAt` avant `endAt`, qui se juge sur lui ; `mode` avant `place`, que le présentiel exige.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['startAt', 'endAt', 'mode', 'place'];
	}

	/**
	 * Une séance ne se modifie pas : la date les remplace toutes (`replaceForDate()`).
	 */
	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return [];
	}

	/**
	 * Le lieu d'une séance en visio ne veut rien dire : il ne s'écrit pas, quoi que la ligne ait
	 * posté avant de changer de mode.
	 */
	public static function create(DateSequence $e): void {

		$e->expects(['date', 'mode']);

		if($e['mode'] === DateSequence::REMOTE) {
			$e['place'] = NULL;
		}

		parent::create($e);

	}

	/**
	 * Remplace les séances de la date par celles de la liste, construites et contrôlées par
	 * `Date::properties()`. Les séances ne portent rien qui leur appartienne en propre (ni
	 * inscription, ni pointage) : les effacer pour les recréer ne perd rien. Appelé sous la
	 * transaction de `DateLib`.
	 */
	public static function replaceForDate(Date $eDate, \Collection $cDateSequence): void {

		self::deleteByDate($eDate);

		foreach($cDateSequence as $eDateSequence) {

			$eDateSequence['date'] = $eDate;

			self::create($eDateSequence);

		}

	}

	public static function deleteByDate(Date $eDate): void {

		$cDateSequence = DateSequence::model()
			->select(['id', 'date'])
			->whereDate($eDate)
			->getCollection();

		foreach($cDateSequence as $eDateSequence) {
			self::delete($eDateSequence);
		}

	}

}
?>
