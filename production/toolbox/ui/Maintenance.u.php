<?php
namespace toolbox;

/**
 * Les entretiens qui reviennent sur un matériel : les rangées du bloc d'un matériel (planche
 * pf-t4-v3, Q1 A), leur échéance telle que le bandeau et la Boîte la disent, et le panneau d'un
 * entretien (arbitrage Q6 A de la planche pf-t4-v2).
 */
class MaintenanceUi {

	public function __construct() {

		\Asset::css('toolbox', 'maintenance.css');

	}

	public static function getCreate(Equipment $eEquipment, string $class): string {
		return '<a href="/toolbox/maintenance:create?equipment='.$eEquipment['id'].'" class="'.$class.'">'.\Asset::icon('plus-circle').' '.s("Planifier un futur entretien").'</a>';
	}

	/**
	 * « Nouvelle saisie » : les deux gestes du carnet dans un seul bouton, qui ouvre un menu (retours de
	 * du 01/10). Le bloc vide et la bande du bas d'un matériel le partagent, chacun avec la
	 * classe de bouton et le calage du menu de sa place.
	 */
	public static function getCreateMenu(Equipment $eEquipment, string $class, string $position): string {

		$h = '<a class="'.$class.' dropdown-toggle" data-dropdown="'.$position.'">'.\Asset::icon('plus-circle').' '.s("Nouvelle saisie").'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= MaintenanceDoneUi::getCreateIntervention($eEquipment, 'dropdown-item');
			$h .= self::getCreate($eEquipment, 'dropdown-item');
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une rangée par entretien dû : celles du bandeau « Entretiens à faire » du carnet
	 * (`EquipmentUi::getDue()`), que le planning de la semaine reprend une à une (`getPlanning()`,
	 * `getDueRow()`). Sans le droit
	 * de noter un entretien fait, la colonne de « Fait » disparaît avec lui
	 * (`maintenance-due-rows-read`, arbitrage Q8 A).
	 *
	 * Le nom du matériel mène à son onglet du carnet (`$link`). Dans le carnet, il ne le fait pas quand
	 * il n'y a qu'un matériel ou que l'adresse vient d'ouvrir le sien (`$eEquipmentSelected`) : le lien
	 * ramènerait là où l'on est. L'onglet rouvert de mémoire par `Lime.Tab.restore()` ne se connaît que
	 * du navigateur, le lien y reste.
	 *
	 * Un entretien daté d'une semaine lointaine du planning n'est ni en retard ni bientôt dû : sa
	 * rangée n'a pas de marque, et la colonne garde sa place.
	 */
	public function getDueRows(\structure\Structure $eStructure, \Collection $cMaintenanceDue, bool $write, bool $link = TRUE, Equipment $eEquipmentSelected = new Equipment()): string {

		$h = '<div class="maintenance-due-rows'.($write ? '' : ' maintenance-due-rows-read').'">';

			foreach($cMaintenanceDue as $eMaintenance) {
				$h .= $this->getDueRow($eStructure, $eMaintenance, $write, $link === FALSE or $eMaintenance['equipment']->is($eEquipmentSelected));
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une rangée de `getDueRows()`, qui sert aussi le planning de la semaine `$week` (`getPlanning()`), où
	 * « Fait » porte l'origine du geste pour que la réponse ne remplace que ce qui est dû. Au planning
	 * seulement, « Fait » est suivi du décalage à la semaine suivante, puis de la croix qui passe
	 * l'échéance (`acceptSkip()`) ; le carnet garde « Ignorer ce retard » dans la roue.
	 */
	protected function getDueRow(\structure\Structure $eStructure, Maintenance $eMaintenance, bool $write, bool $unlinked, ?string $week = NULL, ?string $origin = NULL): string {

		$eEquipment = $eMaintenance['equipment'];

		if($unlinked) {
			$tool = encode($eEquipment['tool']['name']);
		} else {
			$tool = '<a href="'.EquipmentUi::urlEquipment($eStructure, $eEquipment).'">'.encode($eEquipment['tool']['name']).'</a>';
		}

		$gestures = [];

		if($write) {

			if($week === NULL) {
				$gestures[] = MaintenanceDoneUi::getCreate($eMaintenance, 'btn btn-sm btn-outline-primary');
			} else {

				$gestures[] = MaintenanceDoneUi::getCreate($eMaintenance, \planning\DueUi::getGestureClass($origin, TRUE), \planning\DueUi::getOrigin($week, $origin));
				$gestures[] = \planning\DueUi::getPostpone('/toolbox/maintenance:doPostpone', $eMaintenance['id'], $week, $origin);

				if($eMaintenance->assertSkip()) {
					$gestures[] = \planning\DueUi::getSkip('/toolbox/maintenance:doSkip', $eMaintenance['id'], $week, $origin, s("Passer cette échéance"));
				}

			}

		}

		return self::getDueItem(
			$origin,
			($eMaintenance['due'] === NULL) ? '' : self::getBadge($eMaintenance),
			encode($eMaintenance['name']),
			$tool.' · '.self::getDueText($eMaintenance),
			$gestures
		);

	}

	/**
	 * Ce qui est dû, sous la forme d'où on le regarde : une rangée au carnet et à l'ancien planning, une carte
	 * à la zone « Rappels » du nouveau (`planning\DueLib::ORIGIN_ZONE`, retours du 05/10), dont les
	 * gestes deviennent les portes. Trois pistes même pour deux gestes : une porte garde la largeur d'un tiers
	 * quand la carte n'en a que deux. Sert aussi les relevés de compteur (`EquipmentReadingUi`, `WaterReadingUi`).
	 */
	public static function getDueItem(?string $origin, string $badge, string $name, string $detail, array $gestures): string {

		if($origin === \planning\DueLib::ORIGIN_ZONE) {

			$h = '<div class="util-deck">';
				$h .= '<div class="util-deck-head">';
					$h .= '<div class="util-deck-body">';
						$h .= '<div class="util-deck-title">'.$badge.$name.'</div>';
						$h .= '<p>'.$detail.'</p>';
					$h .= '</div>';
				$h .= '</div>';
				if($gestures !== []) {
					$h .= '<div class="util-deck-band util-deck-band-doors util-deck-band-doors-3">'.implode('', $gestures).'</div>';
				}
			$h .= '</div>';

			return $h;

		}

		$h = '<div class="util-deck-row">';
			$h .= '<div>'.$badge.'</div>';
			$h .= '<div class="util-deck-row-name">';
				$h .= $name;
				// Pas util-annotation : le détail porte le lien du matériel, que .util-annotation a
				// repeindrait en gris souligné.
				$h .= '<div class="font-sm color-muted">'.$detail.'</div>';
			$h .= '</div>';
			if($gestures !== []) {
				$h .= '<div class="maintenance-actions">'.implode(' ', $gestures).'</div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bloc « Rappels » en tête de la colonne « À faire » de l'ancien planning (planche
	 * production-finir-carnet-planning, Q1 A et Q2 B ; alertes lot 2, Q1 B) : les entretiens dus avec
	 * les rangées du carnet et leur « Fait », qui ouvre le même panneau, puis les compteurs du carnet et
	 * ceux de l'eau à relever, dans les mêmes colonnes. Le titre est posé au-dessus par l'observateur
	 * (`PlanningObserverUi`). Le bloc se tient sous ce titre comme un groupe d'interventions de la
	 * même colonne (`maintenance-planning`), et non comme un bloc du carnet. Le nouveau planning pose
	 * une carte par ligne dans sa zone « Rappels » (`planning\DueUi::getZones()`), sur deux colonnes quand
	 * la largeur le permet, et `$origin` dit laquelle des deux formes la réponse d'un geste redessine.
	 *
	 * `$cDue` sort de `PlanningObserverLib::describeDue()`, dans l'ordre de l'écran. `$week` est la
	 * semaine affichée, que chaque geste emporte pour que sa réponse rende le bloc de cette semaine.
	 */
	public function getPlanning(\structure\Structure $eStructure, string $week, \Collection $cDue, string $origin): string {

		// Le panneau de « Fait » s'ouvre ici hors du carnet : ses gestes viennent avec lui.
		\Asset::js('toolbox', 'maintenance.js');

		$write = $eStructure->canPlanning();

		$items = '';

		foreach($cDue as $eDue) {

			$items .= match($eDue['type']) {
				PlanningObserverLib::MAINTENANCE => $this->getDueRow($eStructure, $eDue['eMaintenance'], $write, FALSE, $week, $origin),
				PlanningObserverLib::READING => new EquipmentReadingUi()->getDue($eStructure, $eDue['eEquipment'], $week, $origin, $write),
				PlanningObserverLib::WATER_READING => new WaterReadingUi()->getDue($eStructure, $eDue['eWaterMeter'], $week, $origin, $write)
			};

		}

		return match($origin) {
			\planning\DueLib::ORIGIN_BLOCK => '<div class="maintenance-planning"><div class="maintenance-due-rows'.($write ? '' : ' maintenance-due-rows-read').'">'.$items.'</div></div>',
			\planning\DueLib::ORIGIN_ZONE => '<div class="util-cards util-cards-2 util-cards-start">'.$items.'</div>',
		};

	}

	/**
	 * L'étage déplié du bloc d'un matériel : une rangée par entretien qui a une échéance
	 * (`cMaintenanceScheduled`), son nom, sa fréquence et sa dernière fois en atténué dessous, le
	 * prochain à droite, puis « Fait » et sa roue. Les rangées partagent les colonnes
	 * (`maintenance-rows`) ; au téléphone, le prochain passe sous le nom.
	 */
	public function getList(Equipment $eEquipment, bool $write): string {

		if($eEquipment['cMaintenanceScheduled']->empty()) {
			return $this->getEmpty($eEquipment, $write);
		}

		$h = '<div class="util-deck-fold">';
			$h .= '<div class="maintenance-rows'.($write ? '' : ' maintenance-rows-read').'">';

				foreach($eEquipment['cMaintenanceScheduled'] as $eMaintenance) {

					$next = $this->getNext($eMaintenance);

					$h .= '<div class="util-deck-row'.($eMaintenance['due'] === MaintenanceLib::OVERDUE ? ' util-deck-row-state util-deck-accent-danger' : '').'">';
						$h .= '<div class="util-deck-row-name">';
							$h .= encode($eMaintenance['name']);
							$h .= '<p class="font-sm color-muted">'.implode(' · ', array_filter([self::getFrequencyText($eMaintenance), $this->getSince($eEquipment, $eMaintenance)])).'</p>';
							$h .= '<div class="hide-sm-up">'.$next.'</div>';
						$h .= '</div>';
						$h .= '<div class="hide-xs-down text-end">'.$next.'</div>';
						if($write) {
							$h .= '<div class="maintenance-actions">';
								$h .= MaintenanceDoneUi::getCreate($eMaintenance, 'btn btn-sm btn-outline-primary').' ';
								$h .= $this->getActions($eMaintenance);
							$h .= '</div>';
						}
					$h .= '</div>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'étage d'un matériel sans entretien planifié. Sans compteur, la tête du bloc le dit déjà
	 * (`EquipmentUi::getSummary()`) : l'étage n'en garde que les gestes, et n'a plus rien à montrer à
	 * qui ne peut pas écrire. Le geste est « Nouvelle saisie » (`getCreateMenu()`), sous la phrase qui
	 * le motive, à la place que `util-block-cta` donne à la sienne (retour du 01/10).
	 */
	protected function getEmpty(Equipment $eEquipment, bool $write): string {

		$title = $eEquipment->hasMeter() ? '<h3>'.s("Aucun entretien planifié").'</h3>' : '';

		if($write === FALSE and $title === '') {
			return '';
		}

		$h = '<div class="util-deck-fold">';

			if($write) {
				$h .= '<div class="util-block-cta">';
					$h .= $title;
					$h .= '<p>'.s("Planifiez un entretien, ou notez-en un déjà fait.").'</p>';
					$h .= '<div class="util-buttons">';
						$h .= self::getCreateMenu($eEquipment, 'btn btn-primary', 'bottom-center');
					$h .= '</div>';
				$h .= '</div>';
			} else {
				$h .= '<div class="util-block-empty">'.$title.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La roue d'une rangée : modifier l'entretien, et sur un retard, l'ignorer jusqu'à la prochaine
	 * échéance (`MaintenanceLib::ignore()`), qui ne s'écrit pas dans l'historique.
	 */
	protected function getActions(Maintenance $eMaintenance): string {

		$h = '<a class="btn btn-sm btn-color-primary dropdown-toggle" data-dropdown="bottom-end" title="'.s("Réglages de l'entretien").'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.encode($eMaintenance['name']).'</div>';
			$h .= '<a href="/toolbox/maintenance:update?id='.$eMaintenance['id'].'" class="dropdown-item">'.s("Modifier l'entretien").'</a>';
			if($eMaintenance->assertIgnore()) {
				$h .= '<a data-ajax="/toolbox/maintenance:doIgnore" post-id="'.$eMaintenance['id'].'" class="dropdown-item" '.attr('data-confirm', s("L'alerte disparaît jusqu'à la prochaine échéance, qui repart d'aujourd'hui. Rien n'est noté dans l'historique.")).'>'.s("Ignorer ce retard").'</a>';
			}
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/toolbox/maintenance:doUpdateStatus" post-id="'.$eMaintenance['id'].'" post-status="'.Maintenance::DELETED.'" class="dropdown-item" '.attr('data-confirm', s("Supprimer cet entretien ? Ce qui a été fait reste dans l'historique.")).'>'.s("Supprimer l'entretien").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La dernière fois : le dernier entretien fait, avec le compteur quand le matériel en a un et qu'il
	 * est connu, rien tant qu'aucun n'est noté. Le genre de l'entretien n'étant dit nulle part,
	 * « dernière fois » remplace « fait » ou « faite ».
	 */
	protected function getSince(Equipment $eEquipment, Maintenance $eMaintenance): ?string {

		$eMaintenanceDone = $eMaintenance['done'];
		$parts = [];

		if($eMaintenanceDone->notEmpty()) {

			if($eEquipment->hasMeter() and $eMaintenanceDone['value'] !== NULL) {
				$parts[] = s("dernière fois le {date} à {value}", ['date' => EquipmentUi::getDate($eMaintenanceDone['date']), 'value' => EquipmentUi::getValue($eEquipment, $eMaintenanceDone['value'])]);
			} else {
				$parts[] = s("dernière fois le {value}", EquipmentUi::getDate($eMaintenanceDone['date']));
			}

		}

		// L'échéance repart d'un retard ignoré ou d'une échéance passée au planning, que la dernière fois
		// seule n'expliquerait pas.
		if($eMaintenance['ignoredDate'] !== NULL) {
			$parts[] = s("échéance ignorée le {value}", EquipmentUi::getDate($eMaintenance['ignoredDate']));
		}

		return $parts ? implode(' · ', $parts) : NULL;

	}

	/**
	 * Le prochain entretien : l'échéance qui décide (`dueBy`), son état, et l'autre en dessous
	 * quand l'entretien revient aux deux.
	 */
	protected function getNext(Maintenance $eMaintenance): string {

		$h = self::getDueText($eMaintenance, near: FALSE);

		if($eMaintenance['due'] !== NULL) {
			$h .= ' '.self::getBadge($eMaintenance, solid: $eMaintenance['due'] === MaintenanceLib::OVERDUE);
		}

		$other = self::getDueOther($eMaintenance);

		if($other !== NULL) {
			$h .= '<div class="util-annotation">'.$other.'</div>';
		}

		return $h;

	}

	/**
	 * L'autre échéance d'un entretien qui revient au compteur et à la date, celle qui ne décide pas :
	 * « au plus tard le 15 mars 2027 ». `NULL` pour un entretien à une seule échéance, et pour une
	 * échéance dépassée, qui n'a plus de « au plus tard » : l'autre borne ne dit rien de plus urgent.
	 */
	public static function getDueOther(Maintenance $eMaintenance, bool $year = FALSE): ?string {

		if(
			$eMaintenance['remainingValue'] === NULL or $eMaintenance['remainingDays'] === NULL or
			$eMaintenance['due'] === MaintenanceLib::OVERDUE
		) {
			return NULL;
		}

		return match($eMaintenance['dueBy']) {
			'value' => s("au plus tard le {value}", EquipmentUi::getDate($eMaintenance['nextDate'], $year)),
			'date' => s("au plus tard à {value}", EquipmentUi::getValue($eMaintenance['equipment'], $eMaintenance['nextValue']))
		};

	}

	/**
	 * L'échéance qui décide, dite comme on la lit : « dans 28 h », « dépassée de 24 h », « le 14
	 * novembre ». Loin de la date, le mois suffit (« en mars 2027 ») : le jour exact d'un entretien
	 * annuel n'a pas d'importance six mois avant (`$near` à FALSE, les rangées du bloc d'un matériel).
	 */
	public static function getDueText(Maintenance $eMaintenance, bool $near = TRUE, bool $year = FALSE): string {

		// Au compteur seul, sur un matériel sans relevé (`EquipmentLib::fillSummary()`) : l'échéance se
		// dit, son reste ne se calcule pas encore.
		if($eMaintenance['dueBy'] === NULL) {
			return s("à {value}", EquipmentUi::getValue($eMaintenance['equipment'], $eMaintenance['plannedValue']));
		}

		if($eMaintenance['dueBy'] === 'value') {

			$remaining = $eMaintenance['remainingValue'];

			if($remaining < 0) {
				return s("dépassée de {value}", EquipmentUi::getValue($eMaintenance['equipment'], -$remaining));
			} else {
				return s("dans {value}", EquipmentUi::getValue($eMaintenance['equipment'], $remaining));
			}

		}

		if($eMaintenance['remainingDays'] < 0) {
			return s("dépassée depuis le {value}", EquipmentUi::getDate($eMaintenance['nextDate'], $year));
		} else if($near or $eMaintenance['due'] !== NULL) {
			return s("le {value}", EquipmentUi::getDate($eMaintenance['nextDate'], $year));
		} else {
			return s("en {value}", '<span class="util-unit">'.\util\DateUi::textual($eMaintenance['nextDate'], \util\DateUi::MONTH_YEAR).'</span>');
		}

	}

	/**
	 * `$solid` pour une marque posée sur la rangée teintée d'un retard (`util-deck-accent-danger`) : la
	 * marque diluée y prend le fond même de la rangée et ne s'en détache plus.
	 */
	public static function getBadge(Maintenance $eMaintenance, bool $solid = FALSE): string {

		$weight = $solid ? ' badge-solid' : '';

		$class = match($eMaintenance['due']) {
			MaintenanceLib::OVERDUE => 'badge-danger',
			MaintenanceLib::SOON => 'badge-info'
		};

		return '<span class="badge'.$weight.' '.$class.'">'.self::getDueLabel($eMaintenance).'</span>';

	}

	/**
	 * L'état d'un entretien qui approche ou qui a passé son échéance, en mots : dans la marque de
	 * l'écran, et tel quel dans le PDF du carnet, qui n'a pas de marque.
	 */
	public static function getDueLabel(Maintenance $eMaintenance): string {

		return match($eMaintenance['due']) {
			MaintenanceLib::OVERDUE => s("En retard"),
			MaintenanceLib::SOON => s("Bientôt")
		};

	}

	/**
	 * « toutes les 400 h ou chaque année », ou « une seule fois » : la fréquence en phrase, sous le nom
	 * de l'entretien.
	 */
	public static function getFrequencyText(Maintenance $eMaintenance): string {

		if($eMaintenance->isRegular() === FALSE) {
			return s("une seule fois");
		}

		$parts = [];

		// Compteur coupé, la fréquence au compteur se tait : elle revient avec lui.
		if($eMaintenance['everyValue'] !== NULL and $eMaintenance['equipment']->hasMeter()) {
			$parts[] = s("toutes les {value}", EquipmentUi::getValue($eMaintenance['equipment'], $eMaintenance['everyValue']));
		}

		if($eMaintenance['everyMonths'] !== NULL) {

			$months = $eMaintenance['everyMonths'];

			if($months === 12) {
				$parts[] = s("chaque année");
			} else if($months % 12 === 0) {
				$parts[] = s("tous les {value} ans", $months / 12);
			} else if($months === 1) {
				$parts[] = s("chaque mois");
			} else {
				$parts[] = s("tous les {value} mois", $months);
			}

		}

		return implode(' '.s("ou").' ', $parts);

	}

	public function create(Maintenance $eMaintenance): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/maintenance:doCreate', ['id' => 'maintenance-create']);

			$h .= $form->hidden('equipment', $eMaintenance['equipment']);
			$h .= $this->getFields($form, $eMaintenance);

			$h .= $form->group(
				content: $form->submit(s("Planifier"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-maintenance-create',
			title: s("Planifier un entretien"),
			body: $h
		);

	}

	public function update(Maintenance $eMaintenance): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/maintenance:doUpdate', ['id' => 'maintenance-update']);

			$h .= $form->hidden('id', $eMaintenance);
			$h .= $this->getFields($form, $eMaintenance);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-maintenance-update',
			title: s("Modifier un entretien"),
			body: $h
		);

	}

	/**
	 * Planifier un entretien, c'est dire quand le faire, puis s'il revient (arbitrage Q2 de la planche
	 * pf-t4-v4) : « Prochaine fois », le <date> ou à <compteur>, puis « Une seule fois » ou
	 * « Régulièrement », qui ouvre « toutes les [400] heures ou tous les [12] mois » (arbitrage Q6 A de la
	 * planche pf-t4-v2). Sans compteur, le compteur disparaît des deux.
	 */
	protected function getFields(\util\FormUi $form, Maintenance $eMaintenance): string {

		$meter = $eMaintenance['equipment']->hasMeter();

		$h = $form->group(s("Matériel"), encode($eMaintenance['equipment']['tool']['name']));
		$h .= $form->dynamicGroup($eMaintenance, 'name');

		// `form-info-ancestor` donne au rappel du compteur le retrait et le filet que le cadriciel pose sous
		// un champ seul, comme sous celui du panneau « Fait ».
		$planned = '<div class="maintenance-every form-info-ancestor">';
			$planned .= '<div class="maintenance-every-part">'.s("le").' '.$form->dynamicField($eMaintenance, 'plannedDate').'</div>';
			if($meter) {
				$planned .= '<div class="maintenance-every-part">'.s("ou à").' '.$form->dynamicField($eMaintenance, 'plannedValue').'</div>';
			}
		$planned .= '</div>';

		$planned .= EquipmentUi::getReadingLast($eMaintenance['equipment']);

		$h .= $form->group(s("Prochaine fois"), $planned, ['data-wrapper' => 'plannedDate plannedValue']);

		$regular = ($eMaintenance['frequency'] === 'regular');

		$h .= $form->group(
			s("Fréquence"),
			$form->radios('frequency', [
				'once' => s("Une seule fois"),
				'regular' => s("Régulièrement")
			], $regular ? 'regular' : 'once', [
				'required' => TRUE,
				'columns' => 2,
				'onchange' => 'ToolboxMaintenance.changeFrequency(this)'
			])
		);

		$every = '<div class="maintenance-every">';
			if($meter) {
				$every .= '<div class="maintenance-every-part">'.s("toutes les").' '.$form->dynamicField($eMaintenance, 'everyValue').'</div>';
				$every .= '<div class="maintenance-every-part">'.s("ou tous les").' '.$form->dynamicField($eMaintenance, 'everyMonths').'</div>';
			} else {
				$every .= '<div class="maintenance-every-part">'.s("tous les").' '.$form->dynamicField($eMaintenance, 'everyMonths').'</div>';
			}
		$every .= '</div>';

		$h .= $form->group(
			content: $every,
			attributes: [
				'data-wrapper' => 'everyValue everyMonths',
				'data-frequency' => 'regular',
				'class' => $regular ? '' : 'hide'
			]
		);

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Maintenance::model()->describer($property, [
			'name' => s("Entretien"),
			'everyValue' => s("Compteur"),
			'everyMonths' => s("Mois"),
			'plannedDate' => s("Date"),
			'plannedValue' => s("Compteur"),
		]);

		switch($property) {

			case 'name' :
				$d->placeholder = s("Vidange, graissage, contrôle…");
				break;

			case 'everyValue' :
				$d->append = fn(\util\FormUi $form, Maintenance $e) => $form->addon(EquipmentUi::getUnit($e['equipment']['meter']));
				break;

			case 'everyMonths' :
				$d->append = s("mois");
				break;

			case 'plannedValue' :
				$d->append = fn(\util\FormUi $form, Maintenance $e) => $form->addon(EquipmentUi::getUnit($e['equipment']['meter']));
				break;

		}

		return $d;

	}

}
?>
