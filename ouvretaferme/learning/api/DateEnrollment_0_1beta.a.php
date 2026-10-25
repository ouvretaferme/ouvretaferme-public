<?php
namespace learning;

/**
 * Les inscriptions aux dates des formations d'un réseau, en lecture seule avec la clé de ce réseau.
 * Les champs sont ceux de l'export que le réseau télécharge déjà à l'écran (`DateEnrollmentUi::getExport()`) :
 * l'API ne donne rien de plus que ce qu'il lit.
 *
 * Une inscription faite sans compte porte l'identité saisie, une inscription de membre la lit sur
 * son compte, et ses colonnes restent vides. Le prénom, le nom et l'adresse passent donc par les
 * accesseurs de l'élément, jamais par la colonne du même nom : une corrélation nommée comme une
 * colonne passe devant elle.
 */
class DateEnrollment_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'learning';

	public function __construct() {

		$this->read('dateEnrollment/{id}');

		// Jamais `token` : c'est l'adresse porteuse qui relit et annule la ligne depuis l'e-mail, et la
		// rendre donnerait ce geste à tout détenteur de la clé. Ni `user` ni `structure`, des ids
		// Ouvretaferme dont le réseau n'a pas l'usage, et que l'export ne porte pas non plus. Pour la même
		// raison, la présence se lit sans `attendedBy`, le compte qui l'a pointée.
		$this->element(
			'learning\DateEnrollment',
			fn() => [
				'id', 'date',
				'firstName', 'lastName', 'email', 'structureName', 'trainee',
				'status', 'requestedAt', 'confirmedAt', 'canceledAt', 'canceledBy',
				'attendedAt', 'attendedMinutes'
			],
			[
				'firstName' => fn(\ApiData $data, DateEnrollment $e) => $e->getFirstName(),
				'lastName' => fn(\ApiData $data, DateEnrollment $e) => $e->getLastName(),
				'email' => fn(\ApiData $data, DateEnrollment $e) => $e->getEmail(),
				// La structure du membre quand il en a choisi une, sinon la ferme saisie sans compte, sinon
				// rien : une inscription à titre personnel.
				'structureName' => fn(\ApiData $data, DateEnrollment $e) => $e['structure']->notEmpty() ? $e['structure']['name'] : $e['structureName'],
			]
		);

	}

	public function getNewModel(\ApiData $data): DateEnrollmentModel {

		Course_0_1betaApi::validateStructure($data);

		// Deux niveaux de citation : celle des formations prend le préfixe `t`, celle des dates garde `s`.
		return new DateEnrollmentModel()
			->whereDate('IN', new DateModel()
				->select('id')
				->whereCourse('IN', new CourseModel()->select('id')->whereCommunity($data->eStructure)->getSql('t'))
				->getSql());

	}

	public function getNewElement(\ApiData $data): DateEnrollment {

		Course_0_1betaApi::validateStructure($data);

		return new DateEnrollment();

	}

}
?>
