<?php
namespace sequence;

/**
 * Une intervention modèle dans un itinéraire technique : ce qu'il faudra faire, et quand, exprimé en
 * semaines relatives plutôt qu'en dates. C'est ce que le plan de culture déroule en series\Task le
 * jour où une série naît de l'itinéraire.
 *
 * La fenêtre se dit de trois façons, une seule à la fois : un moment unique (weekOnly, yearOnly), un
 * intervalle (weekStart/weekStop et leurs années), ou une répétition dans cet intervalle (frequency,
 * toutes les une à quatre semaines ou tous les mois). L'année vaut -1, 0 ou 1 : une intervention peut
 * précéder ou suivre l'année de la série, ce qui sert aux cultures d'hiver.
 *
 * Une intervention se rattache à une culture de l'itinéraire (`crop`) ou à l'itinéraire seul, quand
 * elle vaut pour toutes ses espèces — un travail du sol avant plantation, par exemple.
 *
 * Le décalage des semaines et le repositionnement ne sont pas exposés : ce sont des gestes de tableau
 * (flow.p.php, doIncrementWeek et doPosition) qui déplacent tout un itinéraire d'un coup, et
 * `position*` n'est pas construit par le Lib.
 */
class Flow_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'sequenceFlowList',
			[
				'?sequence' => Flow::getValidator('sequence'),
				'?crop' => Flow::getValidator('crop'),
				'?action' => Flow::getValidator('action')
			]
		);

		/**
		 * L'itinéraire se désigne par 'sequence' dans le corps. La culture, si elle est donnée, se
		 * construit par `crop` et son appartenance à l'itinéraire est vérifiée : sans quoi une
		 * intervention pendrait d'une espèce d'un autre itinéraire.
		 */
		$this->create('sequenceFlow');
		$this->read('sequenceFlow/{id}');
		$this->update('sequenceFlow/{id}');
		$this->delete('sequenceFlow/{id}');

		$this->element(
			'sequence\Flow',
			fn() => [
				'id',
				'sequence', 'crop', 'plant', 'action',
				'methods', 'tools', 'products', 'description', 'fertilizer',
				'weekOnly', 'weekStart', 'weekStop',
				'yearOnly', 'yearStart', 'yearStop',
				'seasonOnly', 'seasonStart', 'seasonStop',
				'frequency'
			]
		);

	}

	public function load(\ApiData $data, array $routeInfo): void {

		parent::load($data, $routeInfo);

		if($routeInfo['requestRoute'] !== 'sequenceFlow') {
			return;
		}

		if(array_key_exists('sequence', $data->body) === FALSE) {
			throw new \ApiException('Missing sequence', 'missing_sequence', 400);
		}

		$eSequence = new SequenceModel()
			->select(Sequence::getSelection())
			->whereStructure($data->eStructure)
			->whereId($data->body['sequence'])
			->get();

		if($eSequence->empty()) {
			throw new \ApiException('Unknown sequence', 'unknown_sequence', 404);
		}

		$data->e['sequence'] = $eSequence;

		// La culture est facultative : une intervention peut valoir pour tout l'itinéraire.
		if(($data->body['crop'] ?? NULL) === NULL) {
			return;
		}

		$exists = new CropModel()
			->whereStructure($data->eStructure)
			->whereSequence($eSequence)
			->whereId($data->body['crop'])
			->exists();

		if($exists === FALSE) {
			throw new \ApiException('Unknown crop', 'unknown_crop', 404);
		}

	}

	public function getList(\ApiData $data): FlowModel {

		return $this->getNewModel($data)
			->whereSequence(fn() => $data->get('sequence'), if: $data->exists('sequence'))
			->whereCrop(fn() => $data->get('crop'), if: $data->exists('crop'))
			->whereAction(fn() => $data->get('action'), if: $data->exists('action'));

	}

	public function getNewModel(\ApiData $data): FlowModel {

		return new FlowModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Flow {

		return new Flow([
			'structure' => $data->eStructure
		]);

	}

}
?>
