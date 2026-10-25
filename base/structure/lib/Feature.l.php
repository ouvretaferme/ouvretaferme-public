<?php
namespace structure;

class FeatureLib extends FeatureCrud {

	use ConfLib;

	/**
	 * ⚠️ Les défauts se lisent **ici et nulle part ailleurs**. Un groupe de formation ouvre avec ses
	 * promotions et ses fermes école allumées ; relire la déclaration plus tard écraserait ce que
	 * l'administrateur a décidé depuis. Passé cette ligne, c'est la colonne qui fait foi.
	 */
	public static function createForStructure(\structure\Structure $eStructure): void {

		$eStructure->expects(['legalCountry']);

		$e = new Feature([
			'structure' => $eStructure,
			'hasStart' => $eStructure->acceptCommunityStart(),
		]);

		foreach(CommunityFeatureAiLib::getDefaults($eStructure) as $feature) {
			$e[$feature] = TRUE;
		}

		self::create($e);

	}

	/**
	 * La condition porte sur la colonne et non sur une relecture : deux premiers gestes simultanés
	 * n'inscrivent l'outil qu'une fois.
	 */
	public static function addToolbox(Structure $eStructure, string $tool): void {

		$field = Feature::model()->field('hasToolbox');
		$value = Feature::model()->format($tool);

		Feature::model()
			->whereStructure($eStructure)
			->where('JSON_CONTAINS('.$field.', JSON_QUOTE('.$value.')) = 0')
			->update([
				'hasToolbox' => new \Sql('JSON_ARRAY_APPEND('.$field.', \'$\', '.$value.')')
			]);

		self::forget($eStructure);

	}

	public static function removeToolbox(Structure $eStructure, string $tool): void {

		$field = Feature::model()->field('hasToolbox');
		$value = Feature::model()->format($tool);

		Feature::model()
			->whereStructure($eStructure)
			->where('JSON_CONTAINS('.$field.', JSON_QUOTE('.$value.')) = 1')
			->update([
				'hasToolbox' => new \Sql('JSON_REMOVE('.$field.', JSON_UNQUOTE(JSON_SEARCH('.$field.', \'one\', '.$value.')))')
			]);

		self::forget($eStructure);

	}

	public static function updateFeature(Structure $eStructure, string $feature, bool $value): void {

		\structure\Feature::model()
			->whereStructure($eStructure)
			->update([$feature => $value]);

		self::forget($eStructure);

	}

}
