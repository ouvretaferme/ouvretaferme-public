<?php
namespace aid;

class EditionUi {

	public function create(Edition $eEdition): \Panel {
		return $this->getPanel($eEdition, '/aid/admin/edition:doCreate', s("Nouvelle édition"), s("Ajouter l'édition"));
	}

	public function update(Edition $eEdition): \Panel {
		return $this->getPanel($eEdition, '/aid/admin/edition:doUpdate', s("Modifier l'édition"), s("Enregistrer"));
	}

	protected function getPanel(Edition $eEdition, string $action, string $title, string $submit): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax($action);

			if($eEdition->exists()) {
				$h .= $form->hidden('id', $eEdition);
			} else {
				$h .= $form->hidden('aid', $eEdition['aid']);
			}

			$h .= $form->dynamicGroups($eEdition, ['name', 'startDate', 'endDate', 'state*', 'rate', 'ceiling', 'url']);

			$h .= $form->group(
				content: $form->submit($submit)
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-aid-edition',
			title: $title,
			body: $h
		);

	}

	// « À venir » ne se saisit pas : c'est une édition ouverte dont la date d'ouverture n'est pas atteinte,
	// que la carte et la fiche des structures ne comptent pas comme ouverte (AidLib::applyEditionOpen()).
	public static function getState(Edition $eEdition): string {

		if($eEdition->isUpcoming()) {
			return '<span class="badge badge-info">'.s("À venir").'</span>';
		}

		$class = match($eEdition['state']) {
			Edition::OPEN => 'badge-success',
			Edition::SUSPENDED, Edition::EXHAUSTED => 'badge-warning',
			Edition::CLOSED => 'badge-off',
		};

		return '<span class="badge '.$class.'">'.self::p('state')->values[$eEdition['state']].'</span>';

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Edition::model()->describer($property, [
			'name' => s("Nom"),
			'startDate' => s("Ouverture"),
			'endDate' => s("Clôture"),
			'state' => s("État"),
			'rate' => s("Taux ou montant"),
			'ceiling' => s("Plafond"),
			'url' => s("Adresse de l'appel"),
		]);

		switch($property) {

			case 'name' :
				$d->placeholder = s("Appel 2026");
				break;

			case 'state' :
				$d->values = [
					Edition::OPEN => s("Ouverte"),
					Edition::SUSPENDED => s("Suspendue"),
					Edition::EXHAUSTED => s("Enveloppe épuisée"),
					Edition::CLOSED => s("Close"),
				];
				break;

			case 'rate' :
				$d->field = 'textarea';
				$d->labelAfter = \util\FormUi::info(s("Remplace celui de l'aide le temps de l'édition"));
				break;

			case 'ceiling' :
				$d->append = s("€");
				$d->labelAfter = \util\FormUi::info(s("Remplace celui de l'aide le temps de l'édition"));
				break;

		}

		return $d;

	}

}
?>
