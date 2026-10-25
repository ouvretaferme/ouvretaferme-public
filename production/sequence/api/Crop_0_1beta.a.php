<?php
namespace sequence;

/**
 * Une espèce dans un itinéraire technique, avec sa conduite : écartements ou densité, mode de
 * semis, rendement attendu, et la répartition des variétés qu'on y met. C'est le modèle dont
 * series\Cultivation est l'exemplaire daté, et les deux portent les mêmes colonnes de conduite.
 *
 * L'itinéraire ne se change pas : une culture naît dans le sien, et une même espèce n'y figure
 * qu'une fois (UNIQUE sur sequence, plant). Déplacer une espèce d'un itinéraire à l'autre reviendrait
 * à la recréer, ce que l'appelant fait en deux appels s'il le veut.
 *
 * La répartition des variétés se poste sous `varieties`, dans la forme que SliceLib::prepare() lit :
 * trois listes parallèles, `variety` (les références), `varietyCreate` (les noms à créer, pour les
 * entrées valant "new") et `varietyPartPercent` (les parts). C'est la forme du formulaire, et c'est
 * elle que le Lib attend — une entrée vide vaut la variété inconnue du référentiel partagé.
 */
class Crop_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'sequenceCropList',
			[
				'?sequence' => Crop::getValidator('sequence'),
				'?plant' => Crop::getValidator('plant')
			]
		);

		/**
		 * L'itinéraire se désigne par 'sequence' dans le corps et se charge avant la construction :
		 * CropLib::create() l'attend sur l'élément pour y rattacher la culture et recompter les espèces
		 * de l'itinéraire.
		 */
		$this->create('sequenceCrop');
		$this->read('sequenceCrop/{id}');
		$this->update('sequenceCrop/{id}');
		$this->delete('sequenceCrop/{id}');

		$this->listBy('sequenceCrop/{id}/flowsList', fn(\ApiData $data) => new FlowModel()
			->whereStructure($data->eStructure)
			->whereCrop($data->e));

		$this->element(
			'sequence\Crop',
			fn() => [
				'id',
				'sequence', 'plant',
				'startWeek', 'startAction',
				'distance', 'rows', 'rowSpacing', 'plantSpacing', 'density',
				'mainUnit', 'seedling', 'seedlingSeeds', 'yieldExpected',
				'varieties'
			],
			[
				// La correlation sert les deux sens : en sortie le second argument est l'élément, en
				// entrée la valeur brute du corps. Une closure qui ne typerait que l'élément ferait
				// une erreur fatale dès qu'un POST porte la propriété, `varieties` étant écrivable.
				'varieties' => fn(\ApiData $data, mixed $value, string $direction) => $direction === 'out'
					? $data->exportCollection($value['cSlice'])
					: $value
			]
		);

	}

	public function load(\ApiData $data, array $routeInfo): void {

		parent::load($data, $routeInfo);

		if($routeInfo['requestRoute'] !== 'sequenceCrop') {
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

	}

	public function getList(\ApiData $data): CropModel {

		return $this->getNewModel($data)
			->whereSequence(fn() => $data->get('sequence'), if: $data->exists('sequence'))
			->wherePlant(fn() => $data->get('plant'), if: $data->exists('plant'));

	}

	public function getNewModel(\ApiData $data): CropModel {

		return new CropModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Crop {

		return new Crop([
			'structure' => $data->eStructure,
			'cSlice' => new \Collection()
		]);

	}

}
?>
