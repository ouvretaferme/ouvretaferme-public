<?php
namespace series;

/**
 * La fiche d'une série, rendue par `series/static/js/seriesSheet.js` dans le plan 3D (`\map\SceneUi::getSheet()`) et
 * sur le plan d'assolement (`PlaceSheetUi`) : les deux écrans posent sa tête et son corps, le script le remplit au
 * jour qu'ils montrent.
 */
class SeriesSheetUi {

	public function __construct() {

		\Asset::css('series', 'seriesSheet.css');
		\Asset::js('series', 'seriesSheet.js');

	}

	/** Le nom de la série, après la pastille de sa première culture, comme le pose le script (`SeriesSheet.mark()`). */
	public function getTitle(Series $eSeries, ?string $color): string {

		return '<span class="util-dot" style="--util-dot: '.encode($color ?? 'var(--muted)').'"></span>'.encode($eSeries['name']);

	}

	/** Les libellés que lit le script, que le plan 3D complète de ceux de la planche, du jardin et de la parcelle. */
	public static function getTexts(): array {

		return [
			'kind' => [
				'series' => s("Série {value}", '{season}')
			],
			'stage' => [
				'todo' => s("À venir"),
				'nursery' => s("En pépinière"),
				'lateSowing' => s("Semis en retard"),
				'latePlanting' => s("Plantation en retard"),
				'done' => s("Terminée"),
				'over' => s("Récolte finie"),
				'harvest' => s("En récolte"),
				'sown' => s("Semée"),
				'planted' => s("Plantée"),
				'growing' => s("En croissance")
			],
			'unit' => [
				Harvest::KG => s("kg"),
				Harvest::UNIT => s("pièces"),
				Harvest::BUNCH => s("bottes")
			],
			'today' => s("Ce jour"),
			'plannedToday' => s("Prévu ce jour"),
			'sowing' => s("Semis"),
			'planting' => s("Plantation"),
			'harvest' => s("Récolte"),
			'noVariety' => s("Variété non renseignée"),
			'harvested' => s("Récolté en tout"),
			'yield' => s("Rendement"),
			'yieldValue' => s("{value} / m²", ['value' => '{value}']),
			'beds' => s("Planches"),
			'tasks' => s("Interventions"),
			'weeks' => s("Semaines"),
			'noTask' => s("Aucune intervention"),
			'taskTodo' => s("À planifier"),
			'taskWeeks' => s("{from} → {to}", ['from' => '{from}', 'to' => '{to}']),
			'week' => s("s{week}", ['week' => '{week}']),
			'weekYear' => s("s{week}, {year}", ['week' => '{week}', 'year' => '{year}'])
		];

	}

}
?>
