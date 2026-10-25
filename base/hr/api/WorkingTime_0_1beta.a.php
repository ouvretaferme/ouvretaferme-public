<?php
namespace hr;

class WorkingTime_0_1betaApi extends \ModuleApi {

	// Le temps de travail se saisit depuis le planning, mais il reste dans le scope de son package :
	// un package réparti sur deux scopes n'a qu'un seul parent dans la doc, et les présences y
	// tomberaient sous Production. Surtout, les scopes production ne sont proposés qu'à une structure
	// qui produit, et une boutique dont le temps de travail est allumé suit encore celui de son équipe : le
	// refus d'une structure sans temps de travail ni suivi RH est porté par `WorkingTime::date.untracked`.
	public string $scopeName = 'general';

	public function __construct() {

		$this->list(
			'workingTimeList',
			[
				'?user' => WorkingTime::getValidator('user')
			]
		);

		// Le web n'expose qu'une écriture sur ce module (workingTime:doCreate), et WorkingTimeLib n'a
		// ni update ni delete propres : l'API s'en tient au même geste. create() est un upsert sur
		// (structure, user, date), renvoyer un temps pour une journée déjà saisie l'écrase.
		$this->create('workingTime', function(\ApiData $data, array $properties) {

			$l = new \PropertyList('create', 'api');

			$data->e->build($properties, $this->convertProperties($data, $properties, $l), $l);

			// Un temps négatif est déjà refusé par build(), la borne basse valant 0. Reste zéro, que le
			// schéma autorise et qui fait supprimer la journée par le Lib : l'API n'exposant aucune
			// suppression, ce serait effacer une journée par un POST qui répondrait 201 sur une
			// ressource disparue. Sous horaires, `time` n'est pas construit : le départ après l'arrivée et
			// la pause plus courte que la plage (WorkingTime::properties()) rendent la durée calculée positive.
			if(
				$data->e->offsetExists('time') and
				$data->e['time'] === 0.0
			) {
				throw new \ApiException('A working time can not be zero', 'time_zero', 400);
			}

		});

		$this->read('workingTime/{id}');

		$this->element(
			'hr\WorkingTime',
			fn() => [
				'id', 'user', 'date', 'time', 'start', 'end', 'pause'
			]
		);

	}

	// Sous horaires, la journée se poste par ses horaires et la durée s'en calcule : la liste dépend
	// de la structure du jeton, que le cadriciel ne passe pas au Lib.
	public function getPropertiesCreate(\ApiData $data): array {
		return WorkingTimeLib::getPropertiesCreate('api', $data->eStructure);
	}

	public function getList(\ApiData $data): WorkingTimeModel {

		return $this->getNewModel($data)
			->whereUser(fn() => $data->get('user'), if: $data->exists('user'));

	}

	/**
	 * La liste et la lecture restent ouvertes après l'arrêt du suivi du temps (`Structure::hasWorkingTime()`
	 * faux), et seule l'écriture est alors refusée, par l'étape `untracked` de `WorkingTime::properties()`.
	 * Les journées saisies restent l'historique de la structure, que ses écrans montrent de nouveau dès
	 * que le suivi est rallumé.
	 */
	public function getNewModel(\ApiData $data): WorkingTimeModel {

		return new \hr\WorkingTimeModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): WorkingTime {

		return new \hr\WorkingTime([
			'structure' => $data->eStructure
		]);

	}

}
?>
