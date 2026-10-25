<?php
namespace series;

class Task_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	public function __construct() {

		$this->list(
			'taskList',
			[
				'?series' => Task::getValidator('series'),
				'?cultivation' => Task::getValidator('cultivation'),
				'?plant' => Task::getValidator('plant'),
				'?plannedWeek' => new \FilterValidator('text8'),
				'?doneWeek' => new \FilterValidator('text8')
			]
		);

		/**
		 * Ce que l'écran tient de sa page avant de construire (task.p.php) se lit ici dans le corps,
		 * par load() : le statut, la catégorie, la série et sa mise en culture décident de ce que
		 * TaskLib::getPropertiesCreate() construit. La répétition n'est pas ouverte à l'API.
		 */
		$this->create('task');
		$this->read('task/{id}');
		$this->update('task/{id}');
		$this->delete('task/{id}');

		/**
		 * Saisir une récolte. La quantité s'ajoute à celle déjà portée par l'intervention plutôt que de
		 * la remplacer, et elle est datée : deux passages sur la même intervention se distinguent dans
		 * le journal des récoltes (ressource harvest), là où `harvest` n'en porte que le total. C'est
		 * pourquoi la colonne n'est pas modifiable par PUT — TaskLib::getPropertiesWrite() ne la
		 * construit pas — et que le geste passe par ici, comme dans le produit.
		 *
		 * Une quantité négative corrige une saisie de trop : elle est acceptée tant qu'elle ne rend
		 * négatif ni le total de l'intervention, ni ce qui a été récolté ce jour-là.
		 *
		 * L'unité ne se pose qu'une fois. Une intervention qui n'en a pas encore prend celle du corps ;
		 * ensuite elle s'impose, une même intervention ne mélangeant pas les kilos et les bottes.
		 *
		 * L'écran propose en plus d'entrer la quantité au stock d'un produit (task.p.php,
		 * doUpdateHarvestCollection). L'API ne le fait pas : un mouvement de stock est une écriture qui
		 * regarde le catalogue, et elle aura sa ressource plutôt qu'un effet de bord ici.
		 */
		$this->requestPatch('task/{id}/harvest', [], function(\ApiData $data) {

			if($data->e->acceptUpdate() === FALSE) {
				throw new \ApiException('Can not update', 'accept_update', 412);
			}

			if($data->e['action']['fqn'] !== ACTION_RECOLTE) {
				throw new \ApiException('Task is not a harvest', 'task_not_harvest', 412);
			}

			foreach(['quantity', 'date'] as $property) {
				if(array_key_exists($property, $data->body) === FALSE) {
					throw new \ApiException('Missing '.$property, 'missing_'.$property, 400);
				}
			}

			$fw = new \FailWatch();

			// buildHarvests() lit les deux sur chaque intervention : ce qui a déjà été récolté par date,
			// pour refuser un retrait plus grand que la journée, et la part qui revient à celle-ci.
			$cTask = new \Collection([$data->e]);

			TaskLib::fillHarvestDates($cTask);
			TaskLib::fillDistribution($cTask);

			// 'fair' sur une intervention seule lui donne toute la quantité : la répartition sur
			// plusieurs interventions est un geste de tableau, que l'API laisse à l'appelant.
			TaskLib::buildHarvests(
				$cTask,
				(float)$data->body['quantity'],
				(string)$data->body['date'],
				(string)($data->body['unit'] ?? $data->e['harvestUnit'] ?? ''),
				'fair'
			);

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			TaskLib::update($data->e, ['harvest', 'harvestUnit']);

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			$data->pushObject(NULL, $this->getById($data));

		});

		$this->element(
			'series\Task',
			fn() => [
				'id',
				'cultivation', 'series', 'plant', 'variety', 'action', 'category',
				'methods', 'tools', 'products', 'description', 'time', 'timeExpected',
				'harvest', 'harvestUnit', 'harvestSize', 'fertilizer', 'treatment',
				'plannedWeek', 'plannedDate', 'plannedUsers', 'doneWeek', 'doneDate',
				'status', 'createdAt'
				// repeat* n'est pas exposé pour cause de trop grande complexité à gérer
			]
		);

	}

	public function load(\ApiData $data, array $routeInfo): void {

		parent::load($data, $routeInfo);

		if($routeInfo['requestMethod'] !== 'POST' or $routeInfo['requestRoute'] !== 'task') {
			return;
		}

		foreach(['status', 'category'] as $property) {
			if(array_key_exists($property, $data->body) === FALSE) {
				throw new \ApiException('Missing '.$property, 'missing_'.$property, 400);
			}
		}

		if(in_array($data->body['status'], [Task::TODO, Task::DONE], TRUE) === FALSE) {
			throw new \ApiException('Unknown status', 'unknown_status', 400);
		}

		$eCategory = \work\CategoryLib::getByStructure($data->eStructure, id: $data->body['category']);

		if($eCategory->empty()) {
			throw new \ApiException('Unknown category', 'unknown_category', 404);
		}

		$eSeries = new Series();
		$eCultivation = new Cultivation();

		if(($data->body['series'] ?? NULL) !== NULL) {

			// La sélection complète : TaskLib::create() recalcule la série, et ce recalcul lit son cycle et
			// ses dimensions.
			$eSeries = new SeriesModel()
				->select(Series::getSelection())
				->whereStructure($data->eStructure)
				->whereId($data->body['series'])
				->get();

			if($eSeries->empty()) {
				throw new \ApiException('Unknown series', 'unknown_series', 404);
			}

		}

		if(($data->body['cultivation'] ?? NULL) !== NULL) {

			if($eSeries->empty() or $eCategory['fqn'] !== CATEGORIE_CULTURE) {
				Task::fail('cultivation.check');
				return;
			}

			$eCultivation = new CultivationModel()
				->select(Cultivation::getSelection())
				->whereSeries($eSeries)
				->whereId($data->body['cultivation'])
				->get();

			if($eCultivation->empty()) {
				throw new \ApiException('Unknown cultivation', 'unknown_cultivation', 404);
			}

		} else if(
			$eSeries->notEmpty() and
			$eSeries['plants'] === 1 and
			$eCategory['fqn'] === CATEGORIE_CULTURE
		) {
			// Comme l'écran (doCreateFromSeriesCollection) : une série d'une seule espèce rattache ses
			// travaux de culture à sa mise en culture.
			Task::fail('cultivation.check');
			return;
		}

		$data->e->merge([
			'status' => $data->body['status'],
			'category' => $eCategory,
			'series' => $eSeries,
			'season' => $eSeries->empty() ? NULL : $eSeries['season'],
			'cultivation' => $eCultivation,
			'plant' => $eCultivation->empty() ? new \plant\Plant() : $eCultivation['plant'],
			'repeatMaster' => new Repeat()
		]);

	}

	public function getList(\ApiData $data): TaskModel {

		return $this->getNewModel($data)
			->whereSeries(fn() => $data->get('series'), if: $data->exists('series'))
			->whereCultivation(fn() => $data->get('cultivation'), if: $data->exists('cultivation'))
			->wherePlant(fn() => $data->get('plant'), if: $data->exists('plant'))
			->wherePlannedWeek(fn() => $data->get('plannedWeek'), if: $data->exists('plannedWeek'))
			->whereDoneWeek(fn() => $data->get('doneWeek'), if: $data->exists('doneWeek'));

	}

	public function getNewModel(\ApiData $data): TaskModel {

		return new \series\TaskModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Task {

		return new \series\Task([
			'structure' => $data->eStructure
		]);

	}

}
?>
