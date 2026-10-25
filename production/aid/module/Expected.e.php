<?php
namespace aid;

class Expected extends ExpectedElement {

	const FOUND = 'found';
	const JUSTIFIED = 'justified';
	const OPEN = 'open';

	// La couverture du catalogue est un outil de l'équipe, qui le remplit : elle ne se montre à personne d'autre.
	public function canRead(): bool {
		return \user\ConnectionLib::getOnline()->isAdmin();
	}

	/**
	 * Ce que la ligne est devenue, lu sur les aides qui peuvent la résoudre (`cAid`, posée par
	 * ExpectedLib::fillAids()) : l'aide liée, ou les aides publiées de l'organisme lié. Trouvée quand
	 * l'une se montre aux structures ; absente, justifiée quand une adresse le dit, ou quand l'aide
	 * publiée est close pour cette campagne (décidé par V-Sub, son adresse vaut justification) ;
	 * ouverte sinon, brouillon et aide masquée par la veille compris. Seule écriture de la règle, que
	 * la carte compte et que le détail affiche.
	 */
	public function getState(): string {

		$this->expects(['justification', 'cAid']);

		if($this->getAidsFound()->notEmpty()) {
			return Expected::FOUND;
		}

		if(
			$this['justification'] !== NULL or
			$this->getAidClosed()->notEmpty()
		) {
			return Expected::JUSTIFIED;
		}

		return Expected::OPEN;

	}

	// Les aides qui résolvent la ligne parce qu'elles se montrent aux structures, dont le détail donne les fiches.
	public function getAidsFound(): \Collection {

		$this->expects(['cAid']);

		return $this['cAid']->find(fn(Aid $eAid) => $eAid['status'] === Aid::PUBLISHED and $eAid->getHiddenReason() === NULL);

	}

	// L'aide liée qui attend encore d'être publiée, que le détail signale sur une ligne ouverte.
	public function getAidDraft(): Aid {

		$this->expects(['aid', 'cAid']);

		if($this['aid']->empty()) {
			return new Aid();
		}

		return $this['cAid']->find(fn(Aid $eAid) => $eAid['status'] === Aid::DRAFT)->first() ?? new Aid();

	}

	// L'aide publiée qui justifie la ligne parce qu'elle est close, dont le détail donne l'adresse.
	public function getAidClosed(): Aid {

		$this->expects(['cAid']);

		return $this['cAid']->find(fn(Aid $eAid) => $eAid->getHiddenReason() === Aid::HIDDEN_CLOSED)->first() ?? new Aid();

	}

	/**
	 * La famille dit sur quel territoire la ligne se compte, et la couverture le lit sans garde : une
	 * ligne nationale n'a ni région ni département, toute autre a sa région, et une ligne d'organisme
	 * son département, d'où sa région se déduit.
	 */
	public static function properties(\PropertyList $l): void {

		// La liste officielle couvre tout ce que le schéma vérifie, une longueur de 2 ou 3.
		$l->property('department')
			->replaceCheck(fn(?string $department): bool => (
				$department === NULL or
				array_key_exists($department, AidSetting::DEPARTMENTS)
			))
			->addAfterCheck('family', function(?string $department, \PropertyList $l): bool {

				$l->skipIfInvalid(['family']);

				return match($this['family']) {
					Expected::NATIONAL => ($department === NULL),
					Expected::FEADER => TRUE,
					Expected::DEPARTMENT, Expected::GAL, Expected::PARK, Expected::EPCI => ($department !== NULL),
				};

			});

		// Une ligne d'organisme tient sa région de son département, qui la donne déjà : le semis ne
		// la recopie pas, et elle ne peut pas le contredire.
		$l->property('region')
			->addBeforeCast('deduce', function(?string &$region, \PropertyList $l): bool {

				$l->skipIfInvalid(['department']);

				if($l->isBuilt('department') and $this['department'] !== NULL) {
					$region = AidSetting::DEPARTMENTS[$this['department']][1];
				}

				return TRUE;

			})
			->replaceCheck(fn(?string $region): bool => (
				$region === NULL or
				array_key_exists($region, AidSetting::REGIONS)
			))
			->addAfterCheck('family', function(?string $region, \PropertyList $l): bool {

				$l->skipIfInvalid(['family']);

				return match($this['family']) {
					Expected::NATIONAL => ($region === NULL),
					Expected::FEADER, Expected::DEPARTMENT, Expected::GAL, Expected::PARK, Expected::EPCI => ($region !== NULL),
				};

			});

		// Une ligne se résout par une aide (une mesure) ou par un organisme, jamais les deux : l'état
		// lirait sinon deux réponses à la même ligne.
		$l->property('organization')
			->addAfterCheck('exclusive', fn(Organization $eOrganization, \PropertyList $l): bool => (
				$eOrganization->empty() or
				$l->isBuilt('aid') === FALSE or
				$this['aid']->empty()
			));

	}

}
?>
