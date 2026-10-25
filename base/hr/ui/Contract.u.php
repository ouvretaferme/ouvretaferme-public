<?php
namespace hr;

class ContractUi {

	public static function getText(Contract $eContract): string {

		if($eContract->empty()) {
			return '';
		}

		$h = '<div>'.self::p('type')->values[$eContract['type']].'</div>';

		$hours = match($eContract['hoursPeriod']) {
			Contract::WEEK => $eContract['weeklyHours'],
			Contract::YEAR => $eContract['annualHours']
		};

		if($hours !== NULL) {

			$value = \util\TextUi::number($hours, NULL);

			$h .= '<div class="color-muted font-sm">'.match($eContract['hoursPeriod']) {
				Contract::WEEK => s("{value} h par semaine", $value),
				Contract::YEAR => s("{value} h par an, annualisé", $value)
			}.'</div>';

		}

		return $h;

	}

	/**
	 * Les champs du contrat, dans le formulaire d'une période. Le type vide veut dire « pas de contrat ».
	 */
	/**
	 * Un contrat vide est la RH éteinte : la page ne le propose pas, et celui de la période reste en base.
	 */
	public static function getFields(\util\FormUi $form, Contract $eContract): string {

		if($eContract->empty()) {
			return '';
		}

		return $form->dynamicGroups($eContract, ['type', 'hoursPeriod', 'weeklyHours', 'annualHours', 'days']);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Contract::model()->describer($property, [
			'type' => s("Type de contrat"),
			'weeklyHours' => s("Heures par semaine"),
			'annualHours' => s("Heures par an"),
			'hoursPeriod' => s("Heures comptées"),
			'days' => s("Jours travaillés"),
			'hasVariableDays' => s("Jours variables"),
		]);

		switch($property) {

			case 'type' :
				$d->field = 'select';
				$d->values = [
					Contract::CDI => s("CDI"),
					Contract::CDD => s("CDD"),
					Contract::SEASONAL => s("Saisonnier"),
					Contract::APPRENTICESHIP => s("Apprentissage"),
					Contract::PROFESSIONALIZATION => s("Professionnalisation"),
					Contract::INTERNSHIP => s("Stage"),
					Contract::TEMPORARY => s("Intérim"),
					Contract::EMPLOYER_GROUP => s("Groupement d'employeurs"),
					Contract::OTHER => s("Autre"),
				];
				$d->attributes = ['placeholder' => s("Aucun contrat")];
				break;

			// Les deux champs d'heures se postent toujours, celui de l'autre période masqué : l'étape `period` du
			// contrat l'écarte (`Contract::properties()`).
			case 'weeklyHours' :
				$d->append = s("h");
				$d->group = fn(Contract $e) => ($e['hoursPeriod'] === Contract::WEEK) ? [] : ['class' => 'hide'];
				break;

			case 'annualHours' :
				$d->append = s("h");
				$d->group = fn(Contract $e) => ($e['hoursPeriod'] === Contract::YEAR) ? [] : ['class' => 'hide'];
				$d->labelAfter = fn(Contract $e) => \util\FormUi::info(
					s("Comptées sur la période de référence de la structure, du {start} au {stop}.", [
						'start' => \util\DateUi::textual($e['reference']['start'], \util\DateUi::DAY_MONTH),
						'stop' => \util\DateUi::textual($e['reference']['stop'], \util\DateUi::DAY_MONTH)
					]).' <a href="/structure/configuration:updatePeriod?id='.$e['structure']->conf()['id'].'">'.s("Modifier").'</a>'
				);
				break;

			case 'hoursPeriod' :
				$d->field = 'radio';
				$d->values = [
					Contract::WEEK => s("À la semaine"),
					Contract::YEAR => s("Sur l'année, contrat annualisé"),
				];
				$d->attributes = [
					'columns' => 2,
					'required' => TRUE,
					// Posé sur le groupe des boutons radio, pas sur chacun : la case cochée est la cible de l'événement.
					'onchange' => 'event.target.form.querySelector(\'[data-wrapper~="weeklyHours"]\').classList.toggle(\'hide\', event.target.value !== \''.Contract::WEEK.'\'); event.target.form.querySelector(\'[data-wrapper~="annualHours"]\').classList.toggle(\'hide\', event.target.value !== \''.Contract::YEAR.'\')'
				];
				break;

			case 'days' :
				$d->values = [
					Contract::MONDAY => \util\DateUi::getDayNameShort(1),
					Contract::TUESDAY => \util\DateUi::getDayNameShort(2),
					Contract::WEDNESDAY => \util\DateUi::getDayNameShort(3),
					Contract::THURSDAY => \util\DateUi::getDayNameShort(4),
					Contract::FRIDAY => \util\DateUi::getDayNameShort(5),
					Contract::SATURDAY => \util\DateUi::getDayNameShort(6),
					Contract::SUNDAY => \util\DateUi::getDayNameShort(7),
				];
				// Tout décocher doit pouvoir s'enregistrer : sans le champ caché, aucune case ne posterait la
				// propriété et les jours précédents resteraient en base. Des jours variables désactivent les
				// cases, qui ne postent plus que ce champ caché : l'étape `days.variable` refuse le reste.
				// Une case décochée ne poste rien, d'où le zéro caché devant celle des jours variables. La closure est
				// liée au describer par le formulaire : son libellé se lit par ContractUi et non par self.
				$d->field = function(\util\FormUi $form, Contract $e) use($d): string {

					$h = $form->checkboxes('days[]', $d->values, $e['days'], [
						'columns' => 4,
						'all' => FALSE,
						'postEmpty' => TRUE,
						'callbackCheckboxAttributes' => fn() => $e['hasVariableDays'] ? ['disabled' => 'disabled'] : []
					]);

					$h .= $form->hidden('hasVariableDays', 0);
					$h .= $form->checkbox('hasVariableDays', 1, [
						'checked' => $e['hasVariableDays'],
						'onchange' => 'this.form.querySelectorAll(\'.field-radio-group-item > [name="days[]"]\').forEach(input => input.disabled = this.checked)',
						'callbackLabel' => fn(string $input) => $input.' '.ContractUi::p('hasVariableDays')->label,
						'callbackFieldAttributes' => ['class' => 'mt-1']
					]);

					return $h;

				};
				break;

		}

		return $d;

	}

}
?>
