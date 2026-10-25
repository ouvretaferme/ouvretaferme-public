<?php
namespace map;

class Plot_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'plotList',
			[
				'season' => Plot::getValidator('seasonFirst'),
				'?zone' => Plot::getValidator('zone'),
			]
		);

		$this->create('plot');
		$this->read('plot/{id}');

		// `zone` se pose à la création, ignorée en modification, comme `mode` : aucun écran ne déplace un jardin. Le jardin
		// des planches posées à même la parcelle (`zoneFill`) ne se modifie ni ne se supprime (Plot::acceptUpdate(), acceptDelete())
		$this->update('plot/{id}');
		$this->delete('plot/{id}');

		$this->element(
			'map\Plot',
			fn() => [
				'id', 'zone', 'zoneFill', 'name', 'mode',
				'length', 'width', 'area', 'coordinates',
				'seasonFirst', 'seasonLast'
			],
			[
				'coordinates' => fn(\ApiData $data, mixed $value, string $direction) => match($direction) {
					'out' => $value['coordinates'],
					'in' => is_array($value) ? json_encode($value) : $value
				}
			]
		);

	}

	public function getList(\ApiData $data): PlotModel {

		$mPlot = $this->getNewModel($data)
			->whereZone(fn() => $data->get('zone'), if: $data->exists('zone'));

		SeasonLib::whereSeason($mPlot, $data->get('season'));

		return $mPlot;

	}

	public function getNewModel(\ApiData $data): PlotModel {

		return new PlotModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Plot {

		return new Plot([
			'structure' => $data->eStructure
		]);

	}

	/**
	 * La surface se modifie avec ce qui la donne : les dimensions d'un abri, le contour d'un jardin en plein champ. Le contour d'un
	 * abri se poste seul, et ses dimensions se lisent dessus (Plot::properties(), étape `drawn`) : il les construit donc avec lui,
	 * et PlotLib::update() les écrit. Des dimensions postées sur un abri tracé y sont remplacées par celles du contour. Le mode ne
	 * se modifie pas par l'API, il départage donc sans risque. La documentation lit ces règles sans jardin chargé
	 * (`ApiDocLib::getStandaloneRules()`) : elle reçoit les deux groupes.
	 * Les saisons vont ensemble : leur cohérence n'est jugée que sur `seasonLast` (Plot::properties(), étape `consistency`).
	 */
	public function getStandalonePropertiesUpdate(\ApiData $data): array {

		$groupGreenhouse = ['length', 'width', 'area'];
		$groupOpenField = ['area', 'coordinates'];

		if(isset($data->e)) {
			$groupsArea = [
				match($data->e['mode']) {
					Plot::GREENHOUSE => $groupGreenhouse,
					Plot::OPEN_FIELD => $groupOpenField
				}
			];
		} else {
			$groupsArea = [$groupGreenhouse, $groupOpenField];
		}

		return [
			'group' => [
				['seasonFirst', 'seasonLast'],
				...$groupsArea
			],
			// Le plein champ poste son contour avec sa surface (`$groupOpenField`) : seul un contour d'abri arrive ici sans elle
			'addBuildOnly' => function(&$properties) use($groupGreenhouse) {

				if(in_array('coordinates', $properties) and in_array('area', $properties) === FALSE) {
					$properties = array_merge($properties, $groupGreenhouse);
				}

			}
		] + parent::getStandalonePropertiesUpdate($data);

	}

}
?>
