<?php
namespace series;

class Series_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'seriesList',
			[
				'?season' => Series::getValidator('season'),
				'?withCultivations' => new \FilterValidator('bool')
			]
		);

		/**
		 * Créer une série n'est pas un create ordinaire : une série n'existe pas sans ce qu'elle met en
		 * culture. Le corps porte donc, à côté des champs de la série, une liste indexée d'espèces sous
		 * `plant` et la conduite de chacune aux mêmes index — la forme que SeriesLib::prepareCreate()
		 * lit, et que buildIndex() attend. C'est de leur nombre que vient `plants`, colonne obligatoire
		 * qu'aucune propriété ne construit.
		 *
		 * Quand la série naît d'un itinéraire technique, `sequence` le désigne et `crop` dit, à chaque
		 * index, de quelle espèce de l'itinéraire la mise en culture est l'exemplaire. Les interventions
		 * de l'itinéraire sont alors déroulées en tâches, et les récoltes attendues reportées.
		 * `startWeek` décale l'itinéraire entier, `startYear` (0 ou -1) dit s'il commence l'année de la
		 * série ou celle d'avant.
		 */
		$this->requestPost('series', [], function(\ApiData $data) {

			$fw = new \FailWatch();

			// La structure est passée plutôt que relue du corps : prepareCreate() la valide sur
			// canManage(), que l'utilisateur technique de l'API ne satisfait jamais.
			$prepare = SeriesLib::prepareCreate($data->body, $data->eStructure);

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			[$eSeries, $cCultivation] = $prepare;

			$season = ($eSeries['cycle'] === Series::PERENNIAL) ? 1 : NULL;

			$cFlow = new \Collection();
			$referenceYear = NULL;

			if($eSeries['sequence']->notEmpty()) {

				$cFlow = \sequence\FlowLib::getBySequence($eSeries['sequence'], $season, ['plant' => ['name']]);

				if($eSeries['cycle'] === Series::ANNUAL) {

					if($cFlow->notEmpty()) {

						$startYear = (int)($data->body['startYear'] ?? 0);
						$startWeek = (int)($data->body['startWeek'] ?? ($cFlow->first()['weekOnly'] ?? $cFlow->first()['weekStart'] ?? 1));

						\sequence\FlowLib::changeWeekStart($cFlow, $startWeek);

						$firstYear = ($cFlow->first()['yearOnly'] ?? $cFlow->first()['yearStart']);
						$referenceYear = $startYear + $eSeries['season'] - $firstYear;

					}

				} else {
					$referenceYear = $eSeries['season'];
				}

			}

			SeriesLib::createWithCultivations($eSeries, $cCultivation, $cFlow, $referenceYear);

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			// requestPost() et non create() : ModuleApi::create() rappellerait SeriesLib::create() après
			// cette closure, et la série naîtrait deux fois. createWithCultivations() a déjà tout écrit.
			$data->e = $eSeries;

			$data->httpCode = 201;
			$data->set('id', $eSeries['id']);

			$data->pushObject(NULL, $this->getById($data));

		});
		$this->read('series/{id}');
		$this->update('series/{id}');
		$this->delete('series/{id}');

		/**
		 * L'assolement d'une série se modifie par les mêmes gestes que l'écran (page/place.p.php :
		 * doAdd, doRemove, doLength, doRemoveSeries), jamais ligne à ligne : PlaceLib recalcule à chaque
		 * fois la surface et le linéaire de la série et de ses cultures. Chaque action rend la série,
		 * dont area et length disent le résultat ; le détail par planche se lit par placeList.
		 *
		 * Une série clôturée refuse ces gestes comme elle refuse un PUT (Series::acceptUpdate()).
		 */
		$this->requestPatch('series/{id}/addBeds', [
			'beds' => new \ArrayValidator(new \FilterValidator('serial'), max: 100),
			'?size' => new \FilterValidator(['int32', 'min' => 1])
		], function(\ApiData $data) {

			$this->assertOpen($data);

			$fw = new \FailWatch();

			PlaceLib::addBeds($data->e, $data->get('beds'), $data->get('?size'));

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->requestPatch('series/{id}/removeBeds', [
			'beds' => new \ArrayValidator(new \FilterValidator('serial'), max: 100)
		], function(\ApiData $data) {

			$this->assertOpen($data);

			$fw = new \FailWatch();

			PlaceLib::removeBeds($data->e, $data->get('beds'));

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->requestPatch('series/{id}/bedSize', [
			'bed' => \map\Bed::getValidator('id'),
			'size' => new \FilterValidator(['int32', 'min' => 1])
		], function(\ApiData $data) {

			$this->assertOpen($data);

			$eBed = \map\Bed::model()
				->select(['id'])
				->whereId($data->get('bed'))
				->whereStructure($data->eStructure)
				->get();

			if($eBed->empty()) {
				throw new \ApiException('Bed not found', 'not_found', 404);
			}

			$fw = new \FailWatch();

			PlaceLib::setSize($data->e, $eBed, $data->get('size'));

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->requestPatch('series/{id}/clearBeds', [], function(\ApiData $data) {

			$this->assertOpen($data);

			$fw = new \FailWatch();

			PlaceLib::clearSeries($data->e);

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			$data->pushObject(NULL, $this->getById($data));

		});

		/**
		 * Clôturer et rouvrir, comme l'écran (doCloseCollection, doUpdateStatus) : le statut se pose
		 * par la route et non par un PUT, que Series::acceptUpdate() refuse sur une série clôturée.
		 * À la clôture, outcome et comment sont facultatifs, un champ absent gardant sa valeur ;
		 * la réouverture efface le dénouement, réservé à une série clôturée (Series::outcome.closed).
		 */
		$this->requestPatch('series/{id}/close', [], function(\ApiData $data) {

			if($data->e->acceptClose() === FALSE) {
				throw new \ApiException('Can not close this series', 'accept_close', 412);
			}

			$fw = new \FailWatch();

			$data->e['status'] = Series::CLOSED;
			$data->e->build(array_values(array_intersect(['outcome', 'comment'], array_keys($data->body))), $data->body, new \PropertyList('update', 'api'));

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \ResourceApiException($fw));

			SeriesLib::closeCollection(new \Collection([$data->e['id'] => $data->e]));

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->requestPatch('series/{id}/open', [], function(\ApiData $data) {

			if($data->e->acceptOpen() === FALSE) {
				throw new \ApiException('Can not open this series', 'accept_open', 412);
			}

			$fw = new \FailWatch();

			$data->e->build(['status', 'outcome'], ['status' => Series::OPEN], new \PropertyList('update', 'api'));

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \ResourceApiException($fw));

			SeriesLib::update($data->e, ['status', 'outcome']);

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->element(
			'series\Series',
			function(\ApiData $data) {

				$properties = [
					'id',
					'name', 'season', 'use', 'mode',
					'area', 'areaTarget',
					'length', 'lengthTarget',
					'bedWidth', 'alleyWidth',
					'bedStartCalculated', 'bedStopCalculated', 'comment',
					'sequence',
					'cycle',
					'perennialLifetime', 'perennialSeason', 'perennialFirst', 'perennialStatus',
					'duplicateOf',
					'status', 'outcome'
				];

				if($data->get('?withCultivations', FALSE)) {
					$properties[] = 'cultivations';
				}

				return $properties;

			},
			[
				'cultivations' => fn(\ApiData $data, Series $e) => $data->exportCollection($e['cCultivation'])
			]
		);

	}

	protected function assertOpen(\ApiData $data): void {

		if($data->e->acceptUpdate() === FALSE) {
			throw new \ApiException('Can not change the beds of a closed series', 'accept_update', 412);
		}

	}

	public function getList(\ApiData $data): SeriesModel {

		return $this->getNewModel($data)
			->select([
				'cCultivation' => Cultivation::model()
					->select(Cultivation::getSelection())
					->sort('id')
					->delegateCollection('series')
			], if: $data->get('?withCultivations', FALSE))
			->whereSeason(fn() => $data->get('season'), if: $data->exists('season'));

	}

	public function getNewModel(\ApiData $data): SeriesModel {

		return new \series\SeriesModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Series {

		return new \series\Series([
			'structure' => $data->eStructure
		]);

	}

}
?>
