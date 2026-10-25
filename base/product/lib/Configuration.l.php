<?php
namespace product;

/**
 * Le réglage des produits d'une structure, une ligne par structure : les notes de la page des stocks.
 */
class ConfigurationLib extends ConfigurationCrud {

	use \structure\ConfLib;

	public static function createForStructure(\structure\Structure $eStructure): void {

		$eConfiguration = new Configuration([
			'structure' => $eStructure,
		]);

		Configuration::model()->insert($eConfiguration);

		self::forget($eStructure);

	}

	public static function update(Configuration $e, array $properties): void {

		// Des notes laissées vides restent actives : '' les montre vides, NULL les désactiverait.
		if(in_array('stockNotes', $properties)) {

			if($e['stockNotes'] === NULL) {
				$e['stockNotes'] = '';
			}

			$e['stockNotesUpdatedAt'] = new \Sql('NOW()');
			$e['stockNotesUpdatedBy'] = \user\ConnectionLib::getOnline();

			$properties[] = 'stockNotesUpdatedAt';
			$properties[] = 'stockNotesUpdatedBy';

		}

		parent::update($e, $properties);

		self::forget($e['structure']);

	}

	/**
	 * Écrit des valeurs connues d'avance dans la ligne d'une structure, sans `build()` : réservé aux
	 * scripts qui posent un réglage recopié (ferme école, test).
	 */
	public static function updateProperties(\structure\Structure $eStructure, array $values): void {

		// La ligne se désigne par sa structure, unique : l'appelant n'a pas l'identifiant de la ligne.
		Configuration::model()
			->whereStructure($eStructure)
			->update($values);

		self::forget($eStructure);

	}

	public static function updateStockNotesStatus(Configuration $e, bool $enable): void {

		Configuration::model()
			->update($e, [
				'stockNotes' => $enable ? '' : NULL,
				'stockNotesUpdatedAt' => NULL,
				'stockNotesUpdatedBy' => new \user\User()
			]);

		self::forget($e['structure']);

	}

}
?>
