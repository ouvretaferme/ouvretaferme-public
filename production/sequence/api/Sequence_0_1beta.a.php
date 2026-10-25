<?php
namespace sequence;

/**
 * Les itinéraires techniques : le modèle dont on déroule une série. Un itinéraire réunit des espèces
 * (sequenceCrop) et les interventions à mener sur chacune (sequenceFlow), datées en semaines relatives
 * plutôt qu'en dates. Le plan de culture s'en sert pour engendrer une série entière d'un coup, et
 * series\Cultivation garde le lien par ses colonnes `sequence` et `crop` — 26 955 mises en culture en
 * base de développement.
 *
 * Les espèces s'envoient à la création, sous `plantsList` : un itinéraire naît avec ses cultures, et
 * SequenceLib::create() n'accepte pas d'en manquer. Elles ne se modifient pas ensuite par ici, chacune
 * ayant sa propre ressource.
 *
 * Seuls les itinéraires de la structure sont rendus. Sequence::canRead() ouvre pourtant les publics de
 * toute la communauté — 258 en base contre 11 788 privés — mais les lire par l'API demande une liste
 * séparée, comme plantList et plantListGlobal en ont une chacune, et un cadrage qui ne laisse pas
 * écrire ce qu'on ne fait que consulter.
 *
 * Ni duplication, ni décalage global des semaines : ce sont des gestes d'écran (sequence.p.php,
 * doDuplicate et doIncrementWeek) qui agissent sur tout l'itinéraire à la fois, et un appelant qui
 * veut la même chose compose ses propres appels.
 */
class Sequence_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'sequenceList',
			[
				'?cycle' => Sequence::getValidator('cycle'),
				'?status' => Sequence::getValidator('status'),
				'?use' => Sequence::getValidator('use'),
				'?mode' => Sequence::getValidator('mode')
			]
		);

		/**
		 * Les espèces s'envoient sous `plantsList`, mais la propriété n'est pas construite : son callback
		 * `plantsList.check` appelle Plant::canRead(), qui pèse le membre connecté sur la structure. L'API
		 * n'en a pas — elle travaille sous l'utilisateur technique UserSetting::$apiUserId, membre
		 * d'aucune structure — et tout itinéraire serait refusé. Les espèces sont donc chargées ici,
		 * bornées à la structure, ce qui est le contrôle que le callback cherchait à faire.
		 */
		$this->create('sequence', function(\ApiData $data, array $properties) {

			if(array_key_exists('plantsList', $data->body) === FALSE) {
				throw new \ApiException('Missing plantsList', 'missing_plantsList', 400);
			}

			array_delete($properties, 'plantsList');

			$fw = new \FailWatch();

			$l = new \PropertyList('create', 'api');

			$data->e->build($properties, $this->convertProperties($data, $properties, $l), $l);

			if($fw->ko()) {
				return;
			}

			$cPlant = new \plant\PlantModel()
				->select(['id'])
				->whereStructure($data->eStructure)
				->whereId('IN', (array)$data->body['plantsList'])
				->getCollection();

			if($cPlant->count() !== count(array_unique((array)$data->body['plantsList']))) {
				throw new \ApiException('Unknown plant', 'unknown_plant', 404);
			}

			if($cPlant->empty()) {
				Sequence::fail('plantsList.check');
				return;
			}

			$data->e['cPlant'] = $cPlant;

		});
		$this->read('sequence/{id}');
		$this->update('sequence/{id}');

		/**
		 * Supprimer un itinéraire emporte ses cultures et ses interventions, et détache les séries qui
		 * en sont issues sans les défaire (SequenceLib::delete()).
		 */
		$this->delete('sequence/{id}');

		$this->listBy('sequence/{id}/cropsList', fn(\ApiData $data) => new CropModel()
			->whereStructure($data->eStructure)
			->whereSequence($data->e));

		$this->listBy('sequence/{id}/flowsList', fn(\ApiData $data) => new FlowModel()
			->whereStructure($data->eStructure)
			->whereSequence($data->e));

		$this->element(
			'sequence\Sequence',
			fn() => [
				'id',
				'name', 'description', 'comment',
				'cycle', 'perennialLifetime',
				'plants', 'use', 'bedWidth', 'alleyWidth', 'mode',
				'visibility', 'status'
			]
		);

	}

	public function getList(\ApiData $data): SequenceModel {

		return $this->getNewModel($data)
			->whereCycle(fn() => $data->get('cycle'), if: $data->exists('cycle'))
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'))
			->whereUse(fn() => $data->get('use'), if: $data->exists('use'))
			->whereMode(fn() => $data->get('mode'), if: $data->exists('mode'));

	}

	public function getNewModel(\ApiData $data): SequenceModel {

		return new SequenceModel()
			->whereStructure($data->eStructure);

	}

	/**
	 * Le même élément que SequenceLib::getCreateElement(), y compris son 'id' nul : le callback
	 * 'perennialLifetime.prepare' fait expects(['id', 'cycle']), et un élément neuf qui ne porte pas
	 * la clé échoue avant d'être construit.
	 */
	public function getNewElement(\ApiData $data): Sequence {

		return new Sequence([
			'id' => NULL,
			'structure' => $data->eStructure,
			'cycle' => Sequence::ANNUAL
		]);

	}

}
?>
