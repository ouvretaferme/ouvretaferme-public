<?php
namespace aid;

class PieceLib extends PieceCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['fqn', 'name'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['name'];
	}

	// Les pièces courantes, dans l'ordre du semis (aid/configure/piecesAi), que la saisie propose. Une
	// liste d'options prend la deuxième propriété d'un élément pour libellé (FormUi::getOptionValue()),
	// d'où le nom juste après l'id.
	public static function getAll(): \Collection {

		return Piece::model()
			->select(['id', 'name'])
			->sort(['position' => SORT_ASC])
			->getCollection(index: 'id');

	}

	/**
	 * Sème la liste commune, `fqn` => nom, dans l'ordre où la saisie la propose (aid/configure/piecesAi).
	 * Une pièce se retrouve par son `fqn`, se crée si elle manque et reprend son nom sinon : le semis se
	 * rejoue sans doublon. Une pièce retirée de la liste reste en base, des aides pouvant la citer. La
	 * liste est écrite dans le code : un refus de build() est une faute de la liste, qui arrête le semis.
	 */
	public static function seed(array $pieces): array {

		$report = ['created' => 0, 'updated' => 0];

		foreach($pieces as $fqn => $name) {

			$ePiece = self::getByFqn($fqn);
			$isNew = $ePiece->empty();
			$properties = $isNew ? self::getPropertiesCreate() : self::getPropertiesUpdate();

			$fw = new \FailWatch();

			$ePiece->build($properties, [
				'fqn' => $fqn,
				'name' => $name,
			]);

			$fw->validate();

			if($isNew) {
				self::create($ePiece);
				$report['created']++;
			} else {
				self::update($ePiece, $properties);
				$report['updated']++;
			}

		}

		return $report;

	}

	// Une pièce neuve se range après les autres, dans l'ordre où le semis les écrit.
	public static function create(Piece $e): void {

		$e['position'] = Piece::model()->count();

		parent::create($e);

	}

}
?>
