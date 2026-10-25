<?php
namespace hr;

class Contract extends ContractElement {

	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Le jour de semaine de `$date` est l'un des jours cochés du contrat. Des jours variables n'en cochent aucun : à
	 * chaque lecture de décider ce qu'elle attend alors.
	 */
	public function isDayWorked(string $date): bool {

		$this->expects(['days']);

		return $this['days']->position((int)date('N', strtotime($date)) - 1);

	}

	public static function properties(\PropertyList $l): void {

		// Les deux champs d'heures restent postés quand l'un est masqué : celui de l'autre période est écarté
		// plutôt que gardé en base, où le compteur de la semaine lirait un horaire laissé sur un contrat annualisé.
		$l->property('weeklyHours')
			->addAfterCast('period', function(?float &$weeklyHours, \PropertyList $l): bool {

				$l->skipIfInvalid('hoursPeriod');
				$l->expectsBuilt('hoursPeriod');

				if($this['hoursPeriod'] === Contract::YEAR) {
					$weeklyHours = NULL;
				}

				return TRUE;

			});

		// Un contrat annualisé se compte sur son volume : sans lui, rien à attendre sur la période de référence.
		$l->property('annualHours')
			->required(fn(\PropertyList $l): bool => (
				$l->isBuilt('hoursPeriod') and
				$this['hoursPeriod'] === Contract::YEAR
			))
			->addAfterCast('period', function(?float &$annualHours, \PropertyList $l): bool {

				$l->skipIfInvalid('hoursPeriod');
				$l->expectsBuilt('hoursPeriod');

				if($this['hoursPeriod'] === Contract::WEEK) {
					$annualHours = NULL;
				}

				return TRUE;

			});

		// Le groupe de cases poste toujours un champ caché vide (`postEmpty`), sans quoi tout décocher ne
		// posterait rien et laisserait les jours précédents en base. Le cast `set` refuse tout bit non
		// numérique : la chaîne vide s'écarte donc avant lui.
		$l->property('days')
			->addBeforeCast('postEmpty', function(mixed &$value): bool {

				if(is_array($value)) {
					$value = array_values(array_diff($value, ['']));
				}

				return TRUE;

			})
			// Des jours variables ne se cochent pas : refuser plutôt que de jeter en silence les jours postés.
			->addAfterCheck('variable', function(?\Set $days, \PropertyList $l): bool {

				if(
					$l->isBuilt('hasVariableDays') === FALSE or
					$this['hasVariableDays'] === FALSE or
					$days === NULL
				) {
					return TRUE;
				}

				return $days->get() === 0;

			});

	}

}
?>
