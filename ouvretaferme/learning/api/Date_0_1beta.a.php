<?php
namespace learning;

/**
 * Les dates des formations d'un réseau, et leurs inscrits, en lecture seule avec la clé de ce réseau.
 * Aucune route n'écrit : une date se programme, s'annule et se remplit à l'écran, où vivent les
 * e-mails que chacun de ces gestes envoie.
 *
 * Le lien d'une visio (`link`) est la donnée du réseau, rendue à sa clé dès qu'il est posé. À
 * l'écran, une personne confirmée ne le lit qu'une fois envoyé par l'équipe (`DateLib::sendLink()`,
 * `Date.linkMailedAt`), et l'API ne suit pas cette règle : un site qui afficherait ce champ
 * publierait le lien avant l'envoi, et à d'autres que les personnes confirmées.
 *
 * `read`, `list` et `listBy` n'appellent aucun `accept*` (`ModuleApi`) : tout le contrôle d'accès
 * tient dans la portée de `getNewModel()`, et une date hors de cette portée rend 404 par
 * `getById()`.
 *
 * Les inscrits d'une date se listent ici (`courseDate/{id}/enrollmentList`) et non dans
 * `DateEnrollment_0_1betaApi` : `listBy()` charge `{id}` par le modèle de la classe qui déclare la
 * route, donc celui des dates, déjà borné au réseau de la clé.
 */
class Date_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'learning';

	public function __construct() {

		// Toutes les dates du réseau d'une requête, pour l'agenda d'un site sans une requête par formation.
		$this->list(
			'courseDateList',
			[
				'?course' => Date::getValidator('course'),
				'?status' => Date::getValidator('status')
			]
		);

		$this->read('courseDate/{id}');

		// `?status` doit figurer dans les arguments de la route : un paramètre non déclaré n'est pas lu,
		// `$data->exists('status')` reste faux et la liste sort entière, sans erreur. Les places prises
		// se lisent dans le `count` de `?status=confirmed`.
		$this->listBy(
			'courseDate/{id}/enrollmentList',
			fn(\ApiData $data) => new DateEnrollmentModel()
				->whereDate($data->e)
				->whereStatus(fn() => $data->get('status'), if: $data->exists('status')),
			[
				'?status' => DateEnrollment::getValidator('status')
			]
		);

		// Ni `createdAt`, qui ne dit rien au réseau, ni l'ouverture du quiz, geste de l'écran. L'offre
		// et le prix se lisent (lot L15a) et ne s'écrivent qu'à l'écran, où leurs verrous vivent.
		// `price` est rendu brut : vide, la date reprend celui de sa formation.
		// `link` sort dès qu'il est posé, envoyé aux personnes confirmées ou non : `linkMailedAt`,
		// geste de l'écran comme l'ouverture du quiz, n'est pas rendu et ne retient rien ici.
		// Les séances (lot date-mixte) disent chacune son jour, ses heures, son mode et son lieu ;
		// `startAt` et `endAt` restent les bornes de la date, le début de la première séance et la
		// fin de la dernière. `enrollmentPaused` est la pause de la date seule : celle de sa formation,
		// qui ferme aussi la date, se lit sur la formation.
		$this->element(
			'learning\Date',
			fn() => [
				'id', 'course',
				'startAt', 'endAt', 'sequences', 'minutes', 'link', 'seats',
				'offer', 'price', 'externalUrl', 'enrollmentPaused',
				'status'
			],
			[
				'sequences' => fn(\ApiData $data, Date $e) => $e['cDateSequence']->makeArray(fn(DateSequence $eDateSequence) => [
					'startAt' => $eDateSequence['startAt'],
					'endAt' => $eDateSequence['endAt'],
					'mode' => $eDateSequence['mode'],
					'place' => $eDateSequence['place'],
				])
			]
		);

	}

	public function getList(\ApiData $data): DateModel {

		return $this->getNewModel($data)
			->whereCourse(fn() => $data->get('course'), if: $data->exists('course'))
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'));

	}

	public function getNewModel(\ApiData $data): DateModel {

		Course_0_1betaApi::validateStructure($data);

		return new DateModel()
			->whereCourse('IN', new CourseModel()->select('id')->whereCommunity($data->eStructure)->getSql());

	}

	public function getNewElement(\ApiData $data): Date {

		Course_0_1betaApi::validateStructure($data);

		return new Date();

	}

}
?>
