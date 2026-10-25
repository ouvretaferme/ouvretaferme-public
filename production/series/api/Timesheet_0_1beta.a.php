<?php
namespace series;

/**
 * Le temps passé : une ligne par intervention, par personne et par jour. L'intervention porte le
 * cumul (Task.time) que TaskLib::recalculateTime() refait à chaque écriture d'ici ; ce cumul ne dit
 * ni qui a travaillé, ni quand.
 *
 * L'écran de saisie répartit un temps global sur plusieurs interventions à la fois, selon une clé
 * (surface, plants, part égale, récolte) que TimesheetLib::writeByTasks() applique. L'API écrit à
 * l'unité, une intervention à la fois : la répartition est un geste de tableau, pas un contrat, et
 * un appelant qui veut la même chose pose ses lignes lui-même. Deux appels pour la même intervention,
 * la même personne et le même jour font deux lignes, là où l'écran additionne dans la sienne ; rien
 * ne s'en trouve faux, le cumul de l'intervention étant une somme, mais le détail est plus fin.
 *
 * La période est obligatoire, comme la saison l'est sur placeList : 257 665 lignes en base de
 * développement, et le tri par identifiant qu'impose la pagination fait balayer la clé primaire dès
 * qu'aucune borne de date ne cadre la requête. Avec elle, l'index (structure, date) rend `range`, et
 * les autres filtres ne s'appliquent plus qu'à ce qu'elle a déjà réduit.
 *
 * Une clé écrit le temps de n'importe quel membre de l'équipe. Timesheet::canWrite() réserve pourtant
 * cette écriture au gestionnaire, un membre au planning limité ne pouvant saisir que le sien : cette
 * règle est un droit, et ModuleApi n'appelle que les accept* (framework/core/Api.php), n'ayant aucun
 * membre connecté à interroger — l'API travaille sous l'utilisateur dédié UserSetting::$apiUserId. La
 * clé vaut donc pour la structure entière, comme un gestionnaire, ce qui est cohérent avec l'écran qui
 * l'émet (apiKey.p.php valide canManage). Ce qui reste à contrôler ici, et que build() ne peut pas
 * savoir, c'est que le membre désigné appartienne bien à la structure : cf assertMember().
 *
 * Le fichier ne porte pas le suffixe Ai : le cadriciel dérive le nom de la classe de celui du module
 * exposé (\ApiRoute::expose('series\Timesheet') → series\Timesheet_0_1betaApi).
 */
class Timesheet_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'production';

	/** Les clés de répartition d'un temps, celles que TaskLib::distribute() sait appliquer. */
	const DISTRIBUTIONS = ['fair', 'area', 'plant', 'harvest'];

	public function __construct() {

		$this->list(
			'timesheetList',
			[
				'dateFrom' => new \FilterValidator('date'),
				'dateTo' => new \FilterValidator('date'),
				'?task' => Timesheet::getValidator('task'),
				'?series' => Timesheet::getValidator('series'),
				'?cultivation' => Timesheet::getValidator('cultivation'),
				'?user' => Timesheet::getValidator('user')
			]
		);

		/**
		 * L'intervention se désigne par 'task' dans le corps et se charge avant la construction, comme
		 * saleItem désigne sa vente : elle donne à la ligne sa série, sa mise en culture et son espèce,
		 * que TimesheetLib::writeByTasks() recopie de la même façon.
		 */
		$this->create('timesheet', function(\ApiData $data, array $properties) {

			$fw = new \FailWatch();

			$l = new \PropertyList('create', 'api');

			$data->e->build($properties, $this->convertProperties($data, $properties, $l), $l);

			if($fw->ko()) {
				return;
			}

			// La semaine n'est pas construite : elle se dérive de la date, et c'est ainsi que
			// TimesheetLib::writeByTasks() la pose avant d'écrire.
			$data->e['week'] = toWeek($data->e['date']);

		});

		/**
		 * Saisir un temps sur plusieurs interventions à la fois, en le répartissant selon une clé —
		 * c'est le geste de l'écran de saisie (timesheet.p.php, TimesheetLib::writeByTasks()), qu'une
		 * ligne à la fois ne remplace pas : répartir demande de connaître la surface de chaque série,
		 * ses plants ou ce qu'elle a donné ce jour-là, et l'appelant ne les a pas.
		 *
		 * Contrairement à la création unitaire, une intervention qui a déjà un temps de cette personne
		 * ce jour-là le voit augmenté plutôt que doublé. La répartition ne fait qu'ajouter une durée
		 * positive, et retirer du temps passe par PUT timesheet/{id}.
		 *
		 * Les quatre clés sont celles du produit. `fair` donne à chacune la même part ; `area` et
		 * `plant` pèsent selon la série, et exigent donc que chaque intervention en ait une, dimensionnée ;
		 * `harvest` pèse selon ce qui a été récolté ce jour-là, et refuse si le total est nul.
		 */
		$this->requestPost('timesheet/distribution', [], function(\ApiData $data) {

			// Une valeur nulle vaut absence : sans durée, sans personne ou sans intervention, rien ne se répartit.
			foreach(['tasks', 'user', 'date', 'time'] as $property) {
				if(($data->body[$property] ?? NULL) === NULL) {
					throw new \ApiException('Missing '.$property, 'missing_'.$property, 400);
				}
			}

			$distribution = $data->body['distribution'] ?? 'fair';

			if(in_array($distribution, self::DISTRIBUTIONS, TRUE) === FALSE) {
				throw new \ApiException('Unknown distribution', 'unknown_distribution', 400);
			}

			$this->assertMember($data, $data->body['user']);

			// La sélection complète : writeByTasks() lit la série, la mise en culture et l'espèce de
			// chaque intervention pour les reporter sur la ligne, et les clés de répartition pèsent sur
			// la surface de la série.
			$cTask = new TaskModel()
				->select(Task::getSelection())
				->whereStructure($data->eStructure)
				->whereId('IN', (array)$data->body['tasks'])
				->getCollection();

			if($cTask->count() !== count((array)$data->body['tasks'])) {
				throw new \ApiException('Unknown task', 'unknown_task', 404);
			}

			$fw = new \FailWatch();

			$eTimesheet = new Timesheet([
				'structure' => $data->eStructure,
				'user' => new \user\User(['id' => $data->body['user']])
			]);

			// La durée passe par `build()` comme en création unitaire : elle y prend le refus d'une structure
			// qui ne suit pas le temps (`Timesheet::time.untracked`).
			$eTimesheet->build(['date', 'time'], [
				'date' => $data->body['date'],
				'time' => $data->body['time']
			], new \PropertyList('create', 'api'));

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			TimesheetLib::writeByTasks($cTask, $eTimesheet, $eTimesheet['time'], $distribution);

			if($fw->ko()) {
				throw new \ResourceApiException($fw);
			}

			$data->httpCode = 201;

			$data->pushCollection('list', $this->getNewModel($data)
				->select($data->e::getSelection())
				->whereTask('IN', $cTask)
				->whereUser($eTimesheet['user'])
				->whereDate($eTimesheet['date'])
				->sort(['id' => SORT_ASC])
				->getCollection());

		});

		$this->read('timesheet/{id}');

		/**
		 * Seule la durée se modifie (TimesheetLib::getPropertiesUpdate()) : changer de jour, de personne
		 * ou d'intervention revient à saisir un autre temps, et laisserait deux cumuls d'intervention à
		 * refaire au lieu d'un.
		 */
		$this->update('timesheet/{id}');
		$this->delete('timesheet/{id}');

		$this->element(
			'series\Timesheet',
			fn() => [
				'id',
				'task', 'series', 'cultivation', 'plant', 'user',
				'time', 'date', 'week'
			]
		);

	}

	/** L'intervention désignée par le corps est chargée et cadrée avant que build() ne construise. */
	public function load(\ApiData $data, array $routeInfo): void {

		parent::load($data, $routeInfo);

		// La répartition désigne plusieurs interventions et charge les siennes : elle ne passe pas ici.
		if($routeInfo['requestRoute'] !== 'timesheet') {
			return;
		}

		if(array_key_exists('task', $data->body) === FALSE) {
			throw new \ApiException('Missing task', 'missing_task', 400);
		}

		// `structure` est de la sélection : TimesheetLib::create() la passe à
		// TaskLib::recalculateTime(), qui l'exige pour recompter le cumul de la tâche.
		$eTask = new TaskModel()
			->select(['id', 'structure', 'series', 'cultivation', 'plant'])
			->whereStructure($data->eStructure)
			->whereId($data->body['task'])
			->get();

		if($eTask->empty()) {
			throw new \ApiException('Unknown task', 'unknown_task', 404);
		}

		$data->e->merge([
			'task' => $eTask,
			'series' => $eTask['series'],
			'cultivation' => $eTask['cultivation'],
			'plant' => $eTask['plant']
		]);

		if(array_key_exists('user', $data->body) === FALSE) {
			throw new \ApiException('Missing user', 'missing_user', 400);
		}

		$this->assertMember($data, $data->body['user']);

	}

	/**
	 * Le temps d'une personne qui n'est pas de l'équipe ne se saisit pas. build() ne le verrait pas :
	 * il ne connaît que le type d'une propriété déléguée, et un identifiant d'une autre structure y
	 * passerait sans rien signaler.
	 */
	private function assertMember(\ApiData $data, mixed $user): void {

		$isMember = new \structure\MemberModel()
			->whereStructure($data->eStructure)
			->whereUser($user)
			->whereStatus(\structure\Member::IN)
			->exists();

		if($isMember === FALSE) {
			throw new \ApiException('Unknown user', 'unknown_user', 404);
		}

	}

	public function getList(\ApiData $data): TimesheetModel {

		return $this->getNewModel($data)
			->whereDate('>=', $data->get('dateFrom'))
			->whereDate('<=', $data->get('dateTo'))
			->whereTask(fn() => $data->get('task'), if: $data->exists('task'))
			->whereSeries(fn() => $data->get('series'), if: $data->exists('series'))
			->whereCultivation(fn() => $data->get('cultivation'), if: $data->exists('cultivation'))
			->whereUser(fn() => $data->get('user'), if: $data->exists('user'));

	}

	public function getNewModel(\ApiData $data): TimesheetModel {

		return new TimesheetModel()
			->whereStructure($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Timesheet {

		return new Timesheet([
			'structure' => $data->eStructure
		]);

	}

}
?>
