<?php
namespace map;

/**
 * Les planches auxquelles une note se rattache (`base/note`, contrat tenu par `NoteTargetSentinel`).
 *
 * Une planche se rattache quelle que soit sa saison : une note sur la planche 5 parle du même sol d'une
 * année sur l'autre. Les planches qui tiennent lieu d'une parcelle ou d'un jardin entiers (`plotFill`,
 * `zoneFill`) n'ont pas de nom à elles, et ne se rattachent pas : une planche saisie ne s'enregistre
 * pas sans nom (`Bed::name.empty`), donc toute planche rattachée en porte un.
 */
class NoteTargetLib {

	public static function getType(): string {
		return 'map-bed';
	}

	public static function getPosition(): int {
		return 3;
	}

	/**
	 * Les planches qui répondent à la recherche par leur nom, par celui de leur parcelle ou par celui de
	 * leur jardin, dans l'ordre naturel des noms, et parmi `$ids` quand l'appelant les borne. Une
	 * structure peut nommer « 1 » une planche de chaque parcelle : c'est alors la parcelle ou le jardin,
	 * que la suggestion affiche, qu'on tape pour les départager.
	 *
	 * Les planches qui répondent par leur propre nom prennent les places avant celles que leur parcelle
	 * ou leur jardin amène : la liste est coupée à `$limit`, et une parcelle « Serre » ne doit pas chasser
	 * les planches qu'on a nommées « Serre ».
	 */
	public static function search(\structure\Structure $eStructure, string $query, int $limit, ?array $ids = NULL): \Collection {

		$cBed = self::getSearchModel($eStructure, $ids)
			->whereName('LIKE', '%'.$query.'%', if: $query !== '')
			->getCollection(0, $limit, 'id');

		if($query !== '' and $cBed->count() < $limit) {

			// Seule une parcelle qui a un nom à elle se cherche : celle qui tient lieu de jardin entier
			// (`zoneFill`) prend le nom du jardin, que la recherche du jardin couvre.
			$mPlot = new PlotModel()
				->select('id')
				->whereStructure($eStructure)
				->whereZoneFill(FALSE)
				->whereName('LIKE', '%'.$query.'%');

			$mZone = new ZoneModel()
				->select('id')
				->whereStructure($eStructure)
				->whereName('LIKE', '%'.$query.'%');

			$cBed->appendCollection(
				self::getSearchModel($eStructure, $ids)
					->or(
						fn() => $this->wherePlot('IN', $mPlot->getSql()),
						fn() => $this->whereZone('IN', $mZone->getSql())
					)
					->whereId('NOT IN', $cBed->getIds(), if: $cBed->notEmpty())
					->getCollection(0, $limit - $cBed->count(), 'id')
			);

		}

		return $cBed->sort('name', natural: TRUE);

	}

	private static function getSearchModel(\structure\Structure $eStructure, ?array $ids): BedModel {

		return Bed::model()
			->select(self::getSelection())
			->whereStructure($eStructure)
			->whereStatus(Bed::ACTIVE)
			->wherePlotFill(FALSE)
			->whereZoneFill(FALSE)
			->whereId('IN', $ids, if: $ids !== NULL)
			->sort('name');

	}

	/**
	 * Les planches de la structure parmi `$ids`, rangées par id. Une planche supprimée (`DELETED`) n'en est
	 * plus une : elle garde sa ligne pour l'historique des séries, pas pour les notes.
	 */
	public static function getByIds(\structure\Structure $eStructure, array $ids): \Collection {

		return Bed::model()
			->select(self::getSelection())
			->whereId('IN', $ids)
			->whereStructure($eStructure)
			->whereStatus(Bed::ACTIVE)
			->wherePlotFill(FALSE)
			->whereZoneFill(FALSE)
			->getCollection(index: 'id');

	}

	private static function getSelection(): array {

		return [
			'id', 'name',
			'zone' => ['name'],
			'plot' => ['name', 'zoneFill']
		];

	}

}
?>
