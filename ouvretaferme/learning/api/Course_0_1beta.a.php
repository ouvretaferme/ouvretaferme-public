<?php
namespace learning;

/**
 * Les formations d'un groupe de formation ou d'un réseau, écrites par l'API avec la clé de ce
 * groupe. Celles de l'équipe Ouvretaferme ne s'écrivent qu'à l'écran : aucune clé ne les porte.
 *
 * Les routes se déclarent avant toute clé, donc sans structure : c'est `validateStructure()`, posée
 * par `getNewModel()` et `getNewElement()` de chaque ressource du package, qui ferme l'API à une
 * structure que `Structure::acceptLearning()` n'ouvre pas aux formations.
 *
 * Les modules et les phases d'une formation se listent ici (`course/{id}/chapterList`,
 * `course/{id}/phasesList`) et non dans `Chapter_0_1betaApi` ou `Phase_0_1betaApi` : `listBy()`
 * charge `{id}` par le modèle de la classe qui déclare la route, donc celle de la formation. L'usage d'une formation se lit ici aussi, pour la même raison.
 */
class Course_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'learning';

	public function __construct() {

		$this->list(
			'courseList',
			[
				'?status' => Course::getValidator('status')
			]
		);

		$this->create('course');
		$this->read('course/{id}');
		// « Formation payante » à non gouverne les quatre champs du prix, comme dans son panneau, où
		// ils sont toujours postés : ceux qu'un corps ne nomme pas sont construits à leur tour, avec la
		// même `PropertyList`, pour que leur étape `free` les ramène à 0 ou les vide.
		$this->update('course/{id}', function(\ApiData $data, array $properties) {

			$l = new \PropertyList('update', 'api');
			$body = $this->convertProperties($data, $properties, $l);

			$data->e->build($properties, $body, $l);

			if(
				$l->isBuilt('paid') and
				$data->e['paid'] === FALSE
			) {
				$data->e->build(array_values(array_diff(CourseLib::getPropertiesPrice(), $properties)), $body, $l);
			}

		});

		$this->delete('course/{id}');

		$this->listBy('course/{id}/chapterList', fn(\ApiData $data) => new ChapterModel()
			->whereCourse($data->e));

		$this->listBy('course/{id}/phasesList', fn(\ApiData $data) => new PhaseModel()
			->whereCourse($data->e));

		// Les thématiques et les niveaux sont ceux de l'équipe, communs à toutes les formations : une
		// poignée de lignes, rendues d'un bloc et sans pagination. Chaque thématique rend ses
		// sous-thématiques dans l'ordre (`subthemes`).
		$this->requestGet('themeList', [], function(\ApiData $data) {
			$data->pushCollection('list', ThemeLib::getAll());
		});

		$this->requestGet('levelList', [], function(\ApiData $data) {
			$data->pushCollection('list', LevelLib::getAll());
		});

		/**
		 * Le Bilan d'une formation en ligne, celui de l'onglet Bilan de son éditeur
		 * (`/formation/{id}/bilan`) : des comptes et aucun lecteur nommé, un groupe voit ce que sa
		 * formation rassemble, pas qui la suit. La période (`period`) et le pas de la série (`step`) sont
		 * ceux de l'écran, 90 jours par semaine par défaut, et la réponse est la préparation de l'écran,
		 * `UsageLib::get()`, avec les mêmes définitions. Une formation à dates n'en a pas, l'écran
		 * n'ouvre pas l'onglet et l'API le dit.
		 *
		 * Le nombre d'inscrits ne se lit que sur cette route : posé sur l'élément de `getById()`, il
		 * coûterait une requête à la lecture, à l'écriture et à la suppression, et deux à un `PUT`, que
		 * `ModuleApi` relit après avoir écrit.
		 */
		$this->requestGet('course/{id}/usage', [
			'?period' => new \FilterValidator(['enum', UsageLib::PERIODS]),
			'?step' => new \FilterValidator(['enum', UsageLib::STEPS])
		], function(\ApiData $data) {

			$period = $data->get('?period', UsageLib::PERIOD_DEFAULT);
			$step = $data->get('?step', UsageLib::STEP_DEFAULT);

			// Un pas que la période n'admet pas (le mois sur 30 jours) est refusé comme un argument
			// hors de son masque, par le même `step.check` : chaque argument se valide seul, avant la
			// route, et seule la route connaît les deux.
			$fw = new \FailWatch();

			if(in_array($step, UsageLib::getSteps($period), TRUE) === FALSE) {
				\Fail::log('step.check');
			}

			$fw->validate(onKo: fn(\FailWatch $fw) => throw new \ResourceApiException($fw));

			if($data->e->acceptUsage() === FALSE) {
				throw new \ApiException('A dated course has no usage', 'accept_usage', 412);
			}

			EnrollmentLib::fillReaders(new \Collection([$data->e]));

			$usage = UsageLib::get($data->e, ChapterLib::getByCourse($data->e), $period, $step);

			// L'écran lit les leçons par leur id, l'API les rend en liste, dans l'ordre du programme.
			$usage['lessons'] = array_values($usage['lessons']);

			foreach($usage as $name => $value) {
				$data->push($name, $value);
			}

		});

		// Ni `catalog` ni `open` ne sont rendus : la place au catalogue se décide côté équipe, et la
		// lecture sans compte n'existe que pour ses formations. L'offre d'une formation en ligne se lit
		// (lot L15a) et ne s'écrit qu'à l'écran, comme celle d'une date. Le prix d'une formation de
		// réseau s'écrit aussi par l'API (`CourseLib::getPropertiesUpdate()`), sauf `funding` et
		// `fundingNote`, et `paid` ne se relit pas : il n'est pas une colonne, les montants le disent.
		// Le réglage de l'inscription et l'adresse de l'organisme ne s'écrivent qu'à l'écran (lot
		// annuaire), comme la sous-thématique, qu'un changement de thématique vide (`CourseLib::update()`).
		// La pause des inscriptions se lit, et ne se règle qu'à l'écran, sous `acceptUpdateEnrollmentPaused()`.
		$this->element(
			'learning\Course',
			fn() => [
				'id',
				'name', 'type', 'summary', 'status', 'theme', 'subtheme', 'level',
				'offer', 'priceFunded', 'price',
				'externalOnly', 'externalUrl', 'enrollmentPaused',
				'description', 'objectives', 'audience', 'prerequisites',
				'access', 'accessibility', 'recognition',
				'publishedAt', 'updatedAt'
			]
		);

		$this->element(
			'learning\Theme',
			fn() => [
				'id', 'name', 'icon', 'color', 'position', 'subthemes'
			],
			[
				'subthemes' => fn(\ApiData $data, Theme $e) => $data->exportCollection($e['cSubtheme'])
			]
		);

		$this->element(
			'learning\Subtheme',
			fn() => [
				'id', 'name', 'position'
			]
		);

		$this->element(
			'learning\Level',
			fn() => [
				'id', 'name', 'position'
			]
		);

	}

	/**
	 * `type` s'ajoute au contrat de création après ses premiers clients : `convertProperties()` exige
	 * toutes les clés de la liste (`Course::type.missing`), et un client qui ne la poste pas crée donc
	 * en ligne, comme avant. Seule la création la construit, la modification ne la change pas.
	 *
	 * `paid` de même dans la modification d'une formation de réseau : la lecture ne le rend pas, un
	 * client qui renvoie ce qu'il a lu ne le porte donc pas, et NULL ne change rien au prix.
	 */
	public function convertProperties(\ApiData $data, array $properties, \PropertyList $l): array {

		$data->body['type'] ??= Course::ONLINE;
		$data->body['paid'] ??= NULL;

		return parent::convertProperties($data, $properties, $l);

	}

	public function getList(\ApiData $data): CourseModel {

		return $this->getNewModel($data)
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'));

	}

	public function getNewModel(\ApiData $data): CourseModel {

		self::validateStructure($data);

		return new CourseModel()
			->whereCommunity($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Course {

		self::validateStructure($data);

		return CourseLib::getCreateElement($data->eStructure);

	}

	/**
	 * Seuls un groupe de formation et un réseau écrivent des formations (`Course::acceptWrite()`).
	 * Une ferme, ou un groupe d'un autre type, reçoit la réponse d'une ressource qui n'existe pas :
	 * l'API ne lui dit pas qu'elle existe ailleurs. Un groupe qui a éteint ses formations reçoit la
	 * même réponse que l'onglet qu'il a fermé.
	 */
	public static function validateStructure(\ApiData $data): void {

		if(
			(
				$data->eStructure->isEducation() === FALSE and
				$data->eStructure->isNetwork() === FALSE
			) or
			$data->eStructure->acceptLearning() === FALSE
		) {
			throw new \ApiException('Not found', 'not_found', 404);
		}

	}

}
?>
