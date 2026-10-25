<?php
namespace toolbox;

/**
 * Les entretiens faits : l'historique d'un matériel, replié dans son bloc (planche pf-t4-v3, Q1 A), et
 * le panneau de « Fait » (arbitrage Q7 de la planche pf-t4-v2), prérempli du jour, que « Modifier »
 * rouvre depuis l'historique.
 */
class MaintenanceDoneUi {

	public function __construct() {

		\Asset::css('toolbox', 'maintenance.css');
		\Asset::js('toolbox', 'maintenance.js');

	}

	/**
	 * « Consigner un entretien déjà réalisé » (retours du 30/09 et du 01/10) : ce qui a déjà
	 * été fait entre à l'historique, sans périodicité, là où « Planifier un futur entretien » demande de
	 * dire quand il revient. Le panneau qu'il ouvre s'intitule « Consigner un entretien ».
	 */
	public static function getCreateIntervention(Equipment $eEquipment, string $class): string {
		return '<a href="/toolbox/maintenanceDone:create?equipment='.$eEquipment['id'].'" class="'.$class.'">'.\Asset::icon('plus-circle').' '.s("Consigner un entretien déjà réalisé").'</a>';
	}

	// `$origin` est celle d'un geste fait au planning (`planning\DueUi::getOrigin()`), que le panneau réémet.
	public static function getCreate(Maintenance $eMaintenance, string $class, string $origin = ''): string {
		return '<a href="/toolbox/maintenanceDone:create?maintenance='.$eMaintenance['id'].($origin === '' ? '' : '&'.$origin).'" class="'.$class.'">'.\Asset::icon('check-lg').' '.s("Fait").'</a>';
	}

	/**
	 * L'historique d'un matériel, toujours déroulé, sous la forme de celui d'une vente
	 * (`selling\HistoryUi::getList()`, retour du 30/09) : la date d'abord, l'entretien ou
	 * l'intervention et sa note, ou le relevé du compteur, le compteur sur un matériel qui en a un, le
	 * coût, puis la roue, que l'observateur n'a pas. Au téléphone, la date passe au-dessus de
	 * l'entretien et le compteur au-dessus du coût, sans intitulés : les unités les disent. Rien quand l'historique est vide
	 * (`EquipmentLib::fillHistory()`). Un entretien supprimé garde ses lignes, ce qui a été fait reste fait.
	 * Au-delà d'une page, la pagination change le seul historique (`view/equipment.v.php`, `history`).
	 */
	public function getHistory(Equipment $eEquipment, bool $write): string {

		if($eEquipment['history'] === []) {
			return '';
		}

		$meter = $eEquipment->hasMeter();

		$h = '<div id="maintenance-history-'.$eEquipment['id'].'">';

		$h .= '<h3>'.s("Historique de l'entretien").'</h3>';

		// Les colonnes se déclarent dans `maintenance.css`, une grille par combinaison de compteur et
		// de roue : c'est elle qui redistribue la ligne au téléphone.
		$h .= '<div class="util-table util-table-bordered maintenance-history'.($meter ? ' maintenance-history-meter' : '').($write ? '' : ' maintenance-history-read').'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Date").'</div>';
				$h .= '<div>'.s("Entretien").'</div>';
				if($meter) {
					$h .= '<div class="text-end">'.s("Compteur").'</div>';
				}
				$h .= '<div class="text-end">'.s("Coût").'</div>';
				if($write) {
					$h .= '<div></div>';
				}
			$h .= '</div>';

			foreach($eEquipment['history'] as $eHistory) {

				$reading = ($eHistory instanceof EquipmentReading);

				$h .= '<div class="util-table-row">';

					$h .= '<div>'.\util\DateUi::numeric($eHistory['date'], \util\DateUi::DATE).'</div>';

					$h .= '<div>';
						if($reading) {
							$h .= s("Relevé du compteur");
						} else {
							$h .= encode($eHistory->getName());
							if($eHistory['comment'] !== NULL) {
								$h .= '<div class="util-annotation color-muted">';
									$h .= encode($eHistory['comment']);
								$h .= '</div>';
							}
							// La photo sous la note, assez grande pour se lire sans l'ouvrir (arbitrage Q2 C de la
							// planche pf-t4-photo) ; un clic l'ouvre en grand.
							if($eHistory['photo'] !== NULL) {
								$h .= '<a href="/toolbox/maintenanceDone:photo?id='.$eHistory['id'].'" class="maintenance-history-photo-link mt-1" title="'.s("Voir la photo").'">';
									$h .= \Asset::image(new \media\MaintenanceDoneAiUi()->getUrlByHash($eHistory['photo'], 'm'), attributes: ['class' => 'maintenance-history-photo']);
								$h .= '</a>';
							}
						}
					$h .= '</div>';

					if($meter) {
						$h .= '<div class="text-end">';
							$h .= ($eHistory['value'] === NULL) ? '' : EquipmentUi::getValue($eEquipment, $eHistory['value']);
						$h .= '</div>';
					}

					$h .= '<div class="text-end">';
						if($reading === FALSE and $eHistory['cost'] !== NULL) {
							$h .= '<span class="util-unit">'.self::getCost($eHistory['cost']).'</span>';
						}
					$h .= '</div>';

					if($write) {
						$h .= '<div>';
							$h .= $reading ? new EquipmentReadingUi()->getActions($eHistory) : $this->getActions($eHistory);
						$h .= '</div>';
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= \util\TextUi::pagination($eEquipment['historyPage'], $eEquipment['historyPages'], '/toolbox/equipment:history?id='.$eEquipment['id'], 'Ajax.Query');

		$h .= '</div>';

		return $h;

	}

	/**
	 * La roue d'une ligne de l'historique, comme celle d'un entretien (retour du 30/09) : la
	 * suppression y vit sous un séparateur, et non dans le panneau de modification.
	 */
	protected function getActions(MaintenanceDone $eMaintenanceDone): string {

		$h = '<a class="btn btn-sm btn-color-primary dropdown-toggle" data-dropdown="bottom-end" title="'.s("Réglages de l'entretien fait").'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.encode($eMaintenanceDone->getName()).'</div>';
			$h .= '<a href="/toolbox/maintenanceDone:update?id='.$eMaintenanceDone['id'].'" class="dropdown-item">'.($eMaintenanceDone['maintenance']->empty() ? s("Modifier l'entretien") : s("Modifier l'entretien fait")).'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/toolbox/maintenanceDone:doDelete" post-id="'.$eMaintenanceDone['id'].'" class="dropdown-item" '.attr('data-confirm', s("Retirer cet entretien de l'historique ?")).'>'.s("Retirer de l'historique").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Des euros ronds s'écrivent sans centimes, comme une facture de garage qui tombe juste.
	 */
	public static function getCost(float $cost): string {
		return \util\TextUi::money($cost, precision: ($cost === round($cost)) ? 0 : 2);
	}

	/**
	 * `$next` est la prochaine échéance, `[compteur, date]` (`MaintenanceLib::getPlanned()`), `NULL`
	 * pour une intervention ou un entretien « Une seule fois », qui n'en ont pas.
	 */
	public function create(MaintenanceDone $eMaintenanceDone, ?array $next): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/maintenanceDone:doCreate', ['id' => 'maintenance-done-create']);

			if($eMaintenanceDone['maintenance']->empty()) {
				$h .= $form->hidden('equipment', $eMaintenanceDone['equipment']);
			} else {
				$h .= $form->hidden('maintenance', $eMaintenanceDone['maintenance']);
			}

			$h .= $form->hidden('meterConfirmed', 0);
			$h .= $form->hidden('origin', GET('origin'));
			$h .= $form->hidden('week', GET('week'));

			$h .= $this->getFields($form, $eMaintenanceDone, TRUE, $next !== NULL);

			if($next !== NULL) {
				$h .= $this->getNext($eMaintenanceDone['equipment'], $next);
			}

			// « Fait » comme « Consigner un entretien » se concluent par le verbe du bouton de la page (retours du 01/10).
			$h .= $form->group(
				content: $form->submit(s("Consigner"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-maintenance-done-create',
			title: $eMaintenanceDone['maintenance']->empty() ? s("Consigner un entretien") : s("Entretien fait"),
			body: $h
		);

	}

	/**
	 * « Prochaine fois », relu quand la date change (`ToolboxMaintenance.refreshNext()`), dit comme la
	 * personne l'a planifiée : « le <date> ou à <compteur> » (arbitrage Q2 de la planche pf-t4-v4).
	 */
	public function getNext(Equipment $eEquipment, array $next): string {

		[$nextValue, $nextDate] = $next;

		$parts = [];

		if($nextDate !== NULL) {
			$parts[] = s("le {value}", EquipmentUi::getDate($nextDate));
		}

		if($nextValue !== NULL) {
			$parts[] = s("à {value}", EquipmentUi::getValue($eEquipment, $nextValue));
		}

		// Sans échéance à dire, la ligne ne s'affiche pas : c'est le cas tant que le compteur d'où elle
		// repart n'est pas saisi. Son conteneur reste, la relecture à la frappe le remplace.
		if($parts === []) {
			return '<div id="maintenance-done-next"></div>';
		}

		$form = new \util\FormUi();

		return '<div id="maintenance-done-next">'.$form->group(s("Prochaine fois"), implode(' '.s("ou").' ', $parts)).'</div>';

	}

	/**
	 * La photo d'une ligne de l'historique, en grand, sous le nom de ce qui a été fait.
	 */
	public function getPhoto(MaintenanceDone $eMaintenanceDone): \Panel {

		$h = \Asset::image(new \media\MaintenanceDoneAiUi()->getUrlByHash($eMaintenanceDone['photo'], 'l'), attributes: ['class' => 'maintenance-photo']);

		return new \Panel(
			id: 'panel-maintenance-done-photo',
			title: encode($eMaintenanceDone->getName()),
			subTitle: \util\DateUi::numeric($eMaintenanceDone['date'], \util\DateUi::DATE),
			body: $h
		);

	}

	public function update(MaintenanceDone $eMaintenanceDone): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/maintenanceDone:doUpdate', ['id' => 'maintenance-done-update']);

			$h .= $form->hidden('id', $eMaintenanceDone);
			$h .= $this->getFields($form, $eMaintenanceDone, FALSE, FALSE);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-maintenance-done-update',
			title: $eMaintenanceDone['maintenance']->empty() ? s("Entretien") : s("Entretien fait"),
			body: $h
		);

	}

	/**
	 * `$create` : le panneau de « Fait » ou de « Consigner un entretien », les seuls qui mettent le
	 * compteur à jour. `$refresh` : celui de « Fait » sur un entretien régulier, qui relit « Prochaine
	 * fois » à chaque changement de la date ou du compteur.
	 *
	 * Les deux se saisissent dans l'ordre de la planche pf-t4-v4 (Q2) : la date, ce qui a été fait, le
	 * coût et la note, puis le compteur en dernier. « Prochaine fois », qui dépend de la date et du
	 * compteur, vient sous eux (`create()`).
	 */
	protected function getFields(\util\FormUi $form, MaintenanceDone $eMaintenanceDone, bool $create, bool $refresh): string {

		// Le compteur relit « Prochaine fois » à la frappe (retour du 01/10, « en temps réel ») :
		// `change` n'arrive qu'à la sortie du champ. La date garde `change`, qu'un sélecteur de date émet
		// à chaque jour choisi, là où `input` passerait par des dates à moitié saisies.
		$attributes = $refresh ? ['onchange' => 'ToolboxMaintenance.refreshNext(this)'] : [];
		$attributesValue = $refresh ? ['oninput' => 'ToolboxMaintenance.refreshNext(this)'] : [];
		$intervention = $eMaintenanceDone['maintenance']->empty();

		// Le nom de l'entretien n'entre pas dans le titre du panneau : « faite » ou « fait » suivrait son
		// genre, que rien ne dit. Une intervention porte le sien, qui se saisit.
		$h = $form->group(s("Matériel"), encode($eMaintenanceDone['equipment']['tool']['name']));

		if($intervention) {
			$h .= $form->dynamicGroup($eMaintenanceDone, 'date');
			$h .= $form->dynamicGroup($eMaintenanceDone, 'name');
		} else {
			$h .= $form->group(s("Entretien"), encode($eMaintenanceDone['maintenance']['name']));
			$h .= $form->dynamicGroup($eMaintenanceDone, 'date', fn(\PropertyDescriber $d) => $d->attributes += $attributes);
		}

		$h .= $form->dynamicGroup($eMaintenanceDone, 'cost');
		$h .= $form->dynamicGroup($eMaintenanceDone, 'comment');
		$h .= $form->dynamicGroup($eMaintenanceDone, 'photo');

		if($create) {
			$h .= $this->getTask($form, $eMaintenanceDone);
			$h .= $this->getTime($form, $eMaintenanceDone);
			$h .= $this->getMeterUpdate($form, $eMaintenanceDone, $attributesValue);
		}

		return $h;

	}

	/**
	 * Ce que « Fait » écrit au planning (planche production-finir-carnet-planning, Q3 A et Q4 C) : la
	 * tâche est créée à la validation, sans temps, sur l'intervention que la ferme a réglée. Tant
	 * qu'elle ne l'a pas réglée, le champ est là, obligatoire, et son choix devient le réglage
	 * (`MaintenanceTaskLib::fillPanel()`). Le texte sous le libellé dit ici la portée du choix, là où
	 * le panneau du réglage dit ce que le champ règle (`MaintenanceConfigurationUi::p()`).
	 */
	protected function getTask(\util\FormUi $form, MaintenanceDone $eMaintenanceDone): string {

		if($eMaintenanceDone['eMaintenanceConfiguration']->empty()) {
			return '';
		}

		return $form->dynamicGroup($eMaintenanceDone['eMaintenanceConfiguration'], 'action*', function(\PropertyDescriber $d) {
			$d->labelAfter = \util\FormUi::info(s("Pour toute la ferme, modifiable depuis le menu du carnet."));
		});

	}

	/**
	 * Le temps passé, au jour de l'entretien, sur la tâche que « Fait » crée (planche
	 * production-finir-temps-bloc) : seulement là où une tâche se crée et quand la structure suit le
	 * temps, `cUserTime` étant vide sinon (`MaintenanceTaskLib::fillPanel()`).
	 */
	protected function getTime(\util\FormUi $form, MaintenanceDone $eMaintenanceDone): string {

		if($eMaintenanceDone['cUserTime']->empty()) {
			return '';
		}

		return new \planning\TimeTeamUi()->getField($form, $eMaintenanceDone['cUserTime']);

	}

	/**
	 * Le compteur se met à jour au même geste, sur la création seule (retour du 30/09) : une
	 * ligne modifiée garde le sien. Non par défaut, et alors il est lu dans les relevés
	 * (`MaintenanceDone::properties()`) ; Oui montre le champ, vide, le compteur actuel rappelé dessous.
	 *
	 * La question ne se pose pas quand l'entretien ne se note qu'avec le compteur (`valueRequired`,
	 * `MaintenanceDoneLib::isValueRequired()`) : le champ est ouvert d'emblée.
	 */
	protected function getMeterUpdate(\util\FormUi $form, MaintenanceDone $eMaintenanceDone, array $attributes): string {

		if($eMaintenanceDone['equipment']->hasMeter() === FALSE) {
			return '';
		}

		$meter = $eMaintenanceDone['equipment']['meter'];

		if($eMaintenanceDone['valueRequired']) {

			$h = $form->hidden('meterUpdate', 1);

		} else {

			$h = $form->group(
				s("Mettre à jour le compteur"),
				$form->yesNo('meterUpdate', $eMaintenanceDone['meterUpdate'] ? 1 : 0, [
					'yes' => s("Oui"),
					'no' => s("Non"),
					'onchange' => 'ToolboxMaintenance.changeMeterUpdate(this)'
				]),
				['data-wrapper' => 'meterUpdate']
			);

		}

		$h .= $form->dynamicGroup($eMaintenanceDone, 'value', function(\PropertyDescriber $d) use($eMaintenanceDone, $meter, $attributes) {
			$d->group = [
				'data-meter-update' => 'value',
				'class' => $eMaintenanceDone['meterUpdate'] ? '' : 'hide'
			];
			$d->attributes += $attributes;
			$d->asterisk = $eMaintenanceDone['valueRequired'];
			$d->append = fn(\util\FormUi $form) => $form->addon(EquipmentUi::getUnit($meter));
			$d->after = EquipmentUi::getReadingLast($eMaintenanceDone['equipment']);
		});

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = MaintenanceDone::model()->describer($property, [
			'name' => s("Entretien"),
			'date' => s("Date"),
			'value' => s("Compteur"),
			'cost' => s("Coût"),
			'comment' => s("Note"),
			'photo' => s("Photo"),
		]);

		switch($property) {

			case 'date' :
				$d->attributes = [
					'max' => \Clock::date()
				];
				break;

			case 'name' :
				$d->placeholder = s("Changement de courroie, réparation…");
				break;

			case 'value' :
				$d->attributes = [
					'min' => 0
				];
				break;

			case 'cost' :
				$d->append = s("€");
				$d->placeholder = '0';
				break;

			// Le cadre poste le hash dans un champ caché : la photo n'est enregistrée qu'avec l'entretien.
			case 'photo' :
				$d->field = fn(\util\FormUi $form, MaintenanceDone $e) => new \media\MaintenanceDoneAiUi()->getHashCamera('photo', 'maintenance-done-photo', $e['photo'], '7rem', '7rem');
				break;

			case 'comment' :
				$d->field = 'textarea';
				$d->placeholder = s("Huile, pièces, garage…");
				$d->attributes = [
					'rows' => 2
				];
				break;

		}

		return $d;

	}

}
?>
