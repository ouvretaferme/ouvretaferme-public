<?php
namespace toolbox;

/**
 * Un relevé de compteur : la ligne « Compteur » d'un matériel (`EquipmentUi::getQuick()`) et le
 * premier relevé de « Suivre un matériel » le saisissent, les chiffres du matériel le lisent, et
 * l'historique le montre, d'où il se corrige ou se supprime.
 */
class EquipmentReadingUi {

	/**
	 * La roue d'un relevé dans l'historique, comme celle d'un entretien fait.
	 */
	public function getActions(EquipmentReading $eEquipmentReading): string {

		$h = '<a class="btn btn-sm btn-color-primary dropdown-toggle" data-dropdown="bottom-end" title="'.s("Réglages du relevé").'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.s("Relevé du compteur").'</div>';
			$h .= '<a href="/toolbox/equipmentReading:update?id='.$eEquipmentReading['id'].'" class="dropdown-item">'.s("Corriger le relevé").'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/toolbox/equipmentReading:doDelete" post-id="'.$eEquipmentReading['id'].'" class="dropdown-item" '.attr('data-confirm', s("Supprimer ce relevé du compteur ?")).'>'.s("Supprimer le relevé").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La rangée d'un compteur à relever au planning (alertes lot 2, Q1 B), sous les entretiens du bloc
	 * « Rappels » et dans leurs colonnes (`maintenance-due-rows`) : le badge, le matériel vers
	 * son onglet du carnet et son dernier relevé, puis « Relever », le décalage à la semaine suivante
	 * (`EquipmentLib::postponeReading()`) et la croix qui passe ce relevé (`EquipmentLib::skipReading()`),
	 * que l'observateur n'a pas. `$week` est la semaine affichée, `$origin` la forme du planning.
	 */
	public function getDue(\structure\Structure $eStructure, Equipment $eEquipment, string $week, string $origin, bool $write): string {

		$eEquipmentReadingLast = $eEquipment['eEquipmentReadingLast'];

		if($eEquipmentReadingLast->empty()) {
			$last = s("Jamais relevé");
		} else {
			$last = s("Dernier relevé {value} le {date}", [
				'value' => EquipmentUi::getValue($eEquipment, $eEquipmentReadingLast['value']),
				'date' => $this->getDate($eEquipmentReadingLast['date'])
			]);
		}

		$gestures = [];

		if($write) {
			$gestures[] = '<a href="/toolbox/equipmentReading:create?structure='.$eStructure['id'].'&equipment='.$eEquipment['id'].'&'.\planning\DueUi::getOrigin($week, $origin).'" class="'.\planning\DueUi::getGestureClass($origin, TRUE).'">'.\Asset::icon('pencil').' '.s("Relever").'</a>';
			$gestures[] = \planning\DueUi::getPostpone('/toolbox/equipment:doPostponeReading', $eEquipment['id'], $week, $origin);
			$gestures[] = \planning\DueUi::getSkip('/toolbox/equipment:doSkipReading', $eEquipment['id'], $week, $origin, s("Passer ce relevé"));
		}

		return MaintenanceUi::getDueItem(
			$origin,
			'<span class="badge badge-neutral">'.\Asset::icon('speedometer2').' '.s("Compteur").'</span>',
			'<a href="'.EquipmentUi::urlEquipment($eStructure, $eEquipment).'">'.encode($eEquipment['tool']['name']).'</a>',
			$last,
			$gestures
		);

	}

	/**
	 * Le relevé d'un compteur hors du carnet, depuis sa rangée du planning : le carnet n'a que la bande
	 * en ligne (`EquipmentUi::getQuick()`), qui suppose l'onglet du matériel ouvert. Le matériel est
	 * connu, il reste le jour et ce que le compteur affiche. La confirmation d'un relevé qui sort de
	 * l'ordre du compteur est celle de la bande (`meterConfirmed`, `confirmReading`).
	 */
	public function create(EquipmentReading $eEquipmentReading): \Panel {

		$eEquipment = $eEquipmentReading['equipment'];

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/equipmentReading:doCreate', ['id' => 'equipment-reading-create']);

			$h .= $form->hidden('structure', $eEquipmentReading['structure']);
			$h .= $form->hidden('equipment', $eEquipment);
			$h .= $form->hidden('meterConfirmed', 0);
			$h .= $form->hidden('origin', GET('origin'));
			$h .= $form->hidden('week', GET('week'));
			$h .= $form->group(s("Matériel"), encode($eEquipment['tool']['name']));
			$h .= $form->dynamicGroup($eEquipmentReading, 'date');
			$h .= $form->dynamicGroup($eEquipmentReading, 'value', function(\PropertyDescriber $d) use($eEquipment) {
				$d->append = fn(\util\FormUi $form) => $form->addon(EquipmentUi::getUnit($eEquipment['meter']));
			});

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-equipment-reading-create',
			title: s("Relever le compteur"),
			body: $h
		);

	}

	/**
	 * Le jour reste celui du relevé : seul le compteur se corrige, entre les relevés voisins
	 * (`EquipmentReading::properties()`).
	 */
	public function update(EquipmentReading $eEquipmentReading): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/equipmentReading:doUpdate', ['id' => 'equipment-reading-update']);

			$h .= $form->hidden('id', $eEquipmentReading);
			$h .= $form->hidden('meterConfirmed', 0);
			$h .= $form->group(s("Matériel"), encode($eEquipmentReading['equipment']['tool']['name']));
			$h .= $form->group(s("Date"), \util\DateUi::numeric($eEquipmentReading['date'], \util\DateUi::DATE));
			$h .= $form->dynamicGroup($eEquipmentReading, 'value', function(\PropertyDescriber $d) use($eEquipmentReading) {
				$d->append = fn(\util\FormUi $form) => $form->addon(EquipmentUi::getUnit($eEquipmentReading['equipment']['meter']));
			});

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-equipment-reading-update',
			title: s("Corriger le relevé"),
			body: $h
		);

	}

	/**
	 * La question d'un relevé qui sort de l'ordre du compteur (`EquipmentReadingLib::getConflictFromFail()`),
	 * posée par `confirm()` : du texte, sans les `util-unit` de `EquipmentUi::getValue()`.
	 *
	 * Elle pousse à corriger le relevé contredit plutôt qu'à enregistrer par-dessus (retour du
	 * 01/10) : un compteur ne recule pas, donc l'un des deux relevés est faux, et c'est souvent l'ancien.
	 * Le relevé contredit est celui d'avant quand le compteur baisse, celui d'après quand un relevé daté
	 * dans le passé le dépasse.
	 */
	public function getConfirm(EquipmentReading $eEquipmentReading): string {

		$eEquipmentReadingNew = $eEquipmentReading['eEquipmentReadingNew'];
		$unit = EquipmentUi::getUnit($eEquipmentReadingNew['equipment']['meter']);

		$arguments = [
			'value' => \util\TextUi::number($eEquipmentReading['value'], 0).' '.$unit,
			'date' => $this->getDate($eEquipmentReading['date']),
			'valueNew' => \util\TextUi::number($eEquipmentReadingNew['value'], 0).' '.$unit,
			'dateNew' => $this->getDate($eEquipmentReadingNew['date'])
		];

		if($eEquipmentReading['date'] < $eEquipmentReadingNew['date']) {
			return s("Le compteur était à {value} le {date}. Si ce relevé était faux, corrigez-le dans l'historique. Enregistrer quand même {valueNew} le {dateNew} ?", $arguments);
		} else {
			return s("Le compteur n'était qu'à {value} le {date}, après ce relevé. Si ce relevé du {date} était faux, corrigez-le dans l'historique. Enregistrer quand même {valueNew} le {dateNew} ?", $arguments);
		}

	}

	// Comme `EquipmentUi::getDate()`, l'année en plus au-delà de l'année en cours.
	protected function getDate(string $date): string {

		$format = (substr($date, 0, 4) === (string)\Clock::year()) ? \util\DateUi::DAY_MONTH : \util\DateUi::DATE;

		return \util\DateUi::textual($date, $format);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = EquipmentReading::model()->describer($property, [
			'date' => s("Date"),
			'value' => s("Compteur"),
		]);

		switch($property) {

			case 'date' :
				$d->attributes = [
					'max' => \Clock::date()
				];
				break;

			// Sans exemple : un 0 en attente laisserait croire qu'un compteur vide s'enregistre à 0 (retour de
			// du 30/09).
			case 'value' :
				$d->attributes = [
					'min' => 0
				];
				break;

		}

		return $d;

	}

}
?>
