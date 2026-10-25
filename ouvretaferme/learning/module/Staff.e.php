<?php
namespace learning;

class Staff extends StaffElement {

	public static function getSelection(): array {

		return self::getSelectionWithoutCourse() + [
			'course' => Course::getSelectionWithCommunity(),
		];

	}

	/**
	 * Sans la formation, que l'appelant qui la tient déjà accroche à chaque ligne. La ligne ne porte
	 * plus que le rôle, le contact et le rang : le nom, l'adresse, la présentation et la marque
	 * « Référent handicap » se lisent sur sa personne, chargée avec elle.
	 */
	public static function getSelectionWithoutCourse(): array {

		return parent::getSelection() + [
			'supervisor' => Supervisor::getSelection(),
		];

	}

	public function canRead(): bool {

		$this->expects(['course']);

		return $this['course']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['course']);

		return $this['course']->canWrite();

	}

	public function acceptRead(): bool {

		$this->expects(['course']);

		return $this['course']->acceptRead();

	}

	public function acceptWrite(): bool {

		$this->expects(['course']);

		return $this['course']->acceptWrite();

	}

	public function assertRead(): bool {

		$this->expects(['course']);

		return $this['course']->assertRead();

	}

	/**
	 * `supervisor` ne se construit qu'à l'ajout d'un encadrant que le réseau connait déjà. La page
	 * pose sur la ligne les encadrants de la formation (`cStaff`) et les personnes de son réseau qui
	 * ne l'encadrent pas encore (`cSupervisor`, indexées par leur identifiant, rendues par
	 * `SupervisorLib::getForCourse()`) :
	 * - `duplicate` refuse une personne qui encadre déjà la formation. `owned` la refuserait aussi,
	 *   `cSupervisor` ne la portant pas : l'étape ne sert qu'à donner à ce refus son propre message ;
	 * - `owned` refuse une personne d'un autre réseau, l'équipe Ouvretaferme comptant pour un réseau :
	 *   une personne qui n'encadre pas la formation et ne figure pas parmi celles de son réseau n'en
	 *   est pas. L'étape remplace la valeur construite, qui ne porte qu'un identifiant, par la
	 *   personne chargée, dont l'étape `contact.noEmail` lit ensuite l'adresse.
	 *
	 * Le contact donne son adresse sur la fiche et dans les e-mails : sans adresse, il n'aurait rien
	 * à y donner. L'adresse est celle de la personne, que la page charge ou construit avant `contact`
	 * (`StaffLib::getPropertiesCreate()` range `supervisor` devant) : l'étape ne juge rien quand la
	 * personne a été refusée.
	 */
	public static function properties(\PropertyList $l): void {

		$l->property('supervisor')
			->addAfterCheck('duplicate', function(Supervisor $eSupervisor): bool {

				$this->expects(['cStaff']);

				return in_array($eSupervisor['id'], $this['cStaff']->getColumnCollection('supervisor')->getIds(), TRUE) === FALSE;

			})
			->addAfterCheck('owned', function(Supervisor &$eSupervisor): bool {

				$this->expects(['cSupervisor']);

				if($this['cSupervisor']->offsetExists($eSupervisor['id']) === FALSE) {
					return FALSE;
				}

				$eSupervisor = $this['cSupervisor'][$eSupervisor['id']];

				return TRUE;

			});

		$l->property('contact')
			->addAfterCheck('noEmail', function(bool $contact, \PropertyList $l): bool {

				$l->skipIfInvalid('supervisor');

				$this->expects([
					'supervisor' => ['email']
				]);

				return (
					$contact === FALSE or
					$this['supervisor']['email'] !== NULL
				);

			});

	}

}
?>
