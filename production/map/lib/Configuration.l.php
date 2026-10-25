<?php
namespace map;

/**
 * Le réglage de production d'une structure, une ligne par structure : saisons, rotations, planches par
 * défaut, période des diagrammes, retard du planning, et le terrain de l'onglet 3D.
 */
class ConfigurationLib extends ConfigurationCrud {

	use \structure\ConfLib;

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['decorGrass', 'decorSoil', 'decorMeadow'];
	}

	public static function getPropertiesCrop(): array {
		return ['defaultBedLength', 'defaultBedWidth', 'defaultAlleyWidth', 'calendarMonthStart', 'calendarMonthStop', 'rotationYears', 'rotationExclude'];
	}

	/**
	 * La ligne d'une structure neuve : l'année en cours, et la suivante dès le mois qui précède le
	 * changement de saison.
	 */
	public static function createForStructure(\structure\Structure $eStructure): void {

		$eConfiguration = new Configuration([
			'structure' => $eStructure,
		] + self::getSeasonsNew());

		Configuration::model()->insert($eConfiguration);

		self::forget($eStructure);

	}

	private static function getSeasonsNew(): array {

		return [
			'seasonFirst' => \Clock::year(),
			'seasonLast' => \Clock::format('n') >= (\structure\StructureSetting::NEW_SEASON - 1) ? \Clock::year() + 1 : \Clock::year()
		];

	}

	/**
	 * Écrit des valeurs connues d'avance dans la ligne d'une structure, sans `build()` : réservé aux
	 * scripts qui posent un réglage recopié ou calculé (ferme école, test).
	 */
	public static function updateProperties(\structure\Structure $eStructure, array $values): void {

		// La ligne se désigne par sa structure, unique : l'appelant n'a pas l'identifiant de la ligne.
		Configuration::model()
			->whereStructure($eStructure)
			->update($values);

		self::forget($eStructure);

	}

	public static function updateSeasonFirst(Configuration $e, int $increment): void {

		if($increment !== -1 and $increment !== 1) {
			Configuration::fail('seasonFirst.check');
			return;
		}

		Configuration::model()
			->where('seasonFirst + '.$increment.' <= seasonLast')
			->update($e, [
				'seasonFirst' => new \Sql('seasonFirst + '.$increment)
			]);

		self::forget($e['structure']);

	}

	public static function updateSeasonLast(Configuration $e, int $increment): void {

		if($increment !== -1 and $increment !== 1) {
			Configuration::fail('seasonLast.check');
			return;
		}

		Configuration::model()
			->where('seasonLast + '.$increment.' BETWEEN seasonFirst AND '.(\Clock::year() + 10))
			->update($e, [
				'seasonLast' => new \Sql('seasonLast + '.$increment)
			]);

		self::forget($e['structure']);

	}

	/**
	 * Le changement d'année, sur toutes les structures : une écriture de masse, qui garde donc un
	 * tableau de valeurs.
	 */
	public static function createNextSeason(): void {

		$newSeason = \Clock::year() + 1;

		Configuration::model()
			->where('seasonLast < '.$newSeason)
			->update([
				'seasonLast' => $newSeason
			]);

		self::forgetAll();

	}

	/**
	 * Retire la ligne de la structure, pour une duplication qui la remplace par celle de sa source.
	 */
	public static function deleteByStructure(\structure\Structure $eStructure): void {

		$eConfiguration = Configuration::model()
			->select(['id', 'structure'])
			->whereStructure($eStructure)
			->get();

		if($eConfiguration->notEmpty()) {
			self::delete($eConfiguration);
		}

	}

	/**
	 * Une structure insérée hors de `StructureLib::create()` n'a pas sa ligne : elle s'insère alors avec
	 * les saisons d'une structure neuve, et l'unicité par structure écarte l'insertion dans tous les
	 * autres cas.
	 */
	private static function ensure(\structure\Structure $eStructure): void {

		$eConfiguration = new Configuration(['structure' => $eStructure] + self::getSeasonsNew());

		Configuration::model()
			->option('add-ignore')
			->insert($eConfiguration);

		self::forget($eStructure);

	}

	/**
	 * Le terrain posé depuis la 3D. Une structure n'a qu'un réglage : le poser une seconde fois le
	 * remplace.
	 */
	public static function create(Configuration $e): void {

		$e->expects(['structure', 'decorGrass', 'decorSoil', 'decorMeadow']);

		Configuration::model()->beginTransaction();

			self::ensure($e['structure']);

			// La ligne de la structure n'est pas `$e`, qui n'a pas d'id : l'écriture se désigne par la
			// structure, et garde donc un tableau de valeurs.
			Configuration::model()
				->whereStructure($e['structure'])
				->update($e->extracts(['decorGrass', 'decorSoil', 'decorMeadow']));

			self::forget($e['structure']);

		Configuration::model()->commit();

	}

	/**
	 * La structure a désormais son propre décor : un plan qu'elle vide reste vide, le décor par défaut ne revient plus.
	 */
	public static function adoptDecor(\structure\Structure $eStructure): void {

		Configuration::model()->beginTransaction();

			self::ensure($eStructure);
			self::markDecor($eStructure, TRUE);

		Configuration::model()->commit();

	}

	/**
	 * L'adoption défaite par Annuler : le plan retrouve le décor par défaut. Le réglage du terrain reste tel qu'il est.
	 */
	public static function unadoptDecor(\structure\Structure $eStructure): void {
		self::markDecor($eStructure, FALSE);
	}

	private static function markDecor(\structure\Structure $eStructure, bool $adopted): void {

		// La ligne se désigne par la structure, qui n'en a qu'une, et non par un élément chargé : l'écriture garde donc un
		// tableau de valeurs.
		Configuration::model()
			->whereStructure($eStructure)
			->whereDecorAdopted($adopted === FALSE)
			->update(['decorAdopted' => $adopted]);

		self::forget($eStructure);

	}

}
?>
