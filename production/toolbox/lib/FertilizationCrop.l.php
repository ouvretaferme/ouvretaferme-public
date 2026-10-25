<?php
namespace toolbox;

/**
 * Le référentiel des cultures du calculateur des besoins en fertilisation. Il ne s'écrit que par le
 * script `toolbox/configure/fertilizationReference`, qui le vide et le repose.
 */
class FertilizationCropLib extends FertilizationCropCrud {

	/**
	 * Les espèces du référentiel, indexées par id, chacune avec ses rendements de référence, chacun
	 * avec sa fiabilité (`confidence`, cf. `FertilizationLib::getConfidence()`), et les sources de son
	 * exporté (`exportedSources`, cf. `getExportedSources()`), dans l'ordre alphabétique
	 * français du nom de l'espèce, accents compris, comme `plant\PlantLib` (un tri sur l'octet range
	 * Échalote après Tomate).
	 */
	public static function getList(): \Collection {

		$cFertilizationCrop = FertilizationCrop::model()
			->select(FertilizationCrop::getSelection() + [
				'plant' => ['name', 'vignette', 'fqn'],
				'exportedNitrogenSource' => FertilizationSource::getSelection(),
				'exportedPhosphateSource' => FertilizationSource::getSelection(),
				'exportedPotashSource' => FertilizationSource::getSelection(),
				'cYield' => new FertilizationYieldModel()
					->select(FertilizationYield::getSelection() + [
						'source' => FertilizationSource::getSelection(),
					])
					->sort('position')
					->delegateCollection('crop'),
			])
			->getCollection(index: 'id')
			->sort(fn(FertilizationCrop $eFertilizationCrop1, FertilizationCrop $eFertilizationCrop2) => \L::getCollator()->compare($eFertilizationCrop1['plant']['name'], $eFertilizationCrop2['plant']['name']));

		foreach($cFertilizationCrop as $eFertilizationCrop) {

			$eFertilizationCrop['exportedSources'] = self::getExportedSources($eFertilizationCrop);

			foreach($eFertilizationCrop['cYield'] as $eFertilizationYield) {
				$eFertilizationYield['confidence'] = FertilizationLib::getConfidence($eFertilizationCrop, $eFertilizationYield);
			}

		}

		return $cFertilizationCrop;

	}

	/**
	 * Les espèces de `$cFertilizationCrop` dont le nom contient `$query`, sans égard aux accents ni à la
	 * casse (« eche » trouve Échalote), celles dont le nom commence par la saisie d'abord, chaque
	 * groupe dans l'ordre de la liste. Une saisie vide les rend toutes. Le référentiel tient en une
	 * cinquantaine d'espèces déjà chargées par la page : les filtrer ici évite une requête par frappe.
	 */
	public static function getFromQuery(\Collection $cFertilizationCrop, string $query): \Collection {

		$query = toFqn($query, ' ');

		if($query === '') {
			return $cFertilizationCrop;
		}

		$cFertilizationCropStart = new \Collection();
		$cFertilizationCropInside = new \Collection();

		foreach($cFertilizationCrop as $eFertilizationCrop) {

			$position = mb_strpos(toFqn($eFertilizationCrop['plant']['name'], ' '), $query);

			if($position === 0) {
				$cFertilizationCropStart[] = $eFertilizationCrop;
			} else if($position !== FALSE) {
				$cFertilizationCropInside[] = $eFertilizationCrop;
			}

		}

		return $cFertilizationCropStart->mergeCollection($cFertilizationCropInside);

	}

	/**
	 * Les documents qui publient l'exporté, chacun une fois avec les éléments qu'il donne, dans l'ordre
	 * N, P₂O₅, K₂O : l'azote et les oxydes viennent souvent de deux documents, et un document qui en
	 * donne deux ne s'ouvre pas par deux liens. Vide quand aucun ne publie l'exporté.
	 *
	 * @return array<int, array{eFertilizationSource: FertilizationSource, elements: string[]}>
	 */
	public static function getExportedSources(FertilizationCrop $eFertilizationCrop): array {

		$sources = [];

		foreach(FertilizationLib::ELEMENTS as $element) {

			$eFertilizationSource = $eFertilizationCrop['exported'.ucfirst($element).'Source'];

			if($eFertilizationSource->empty()) {
				continue;
			}

			$sources[$eFertilizationSource['id']] ??= [
				'eFertilizationSource' => $eFertilizationSource,
				'elements' => [],
			];

			$sources[$eFertilizationSource['id']]['elements'][] = $element;

		}

		return array_values($sources);

	}

}
?>
