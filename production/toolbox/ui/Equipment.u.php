<?php
namespace toolbox;

/**
 * Le carnet du matériel de la Boîte à outils (planches pf-t4-v2 et pf-t4-v3).
 *
 * Sans matériel suivi, l'écran accueille. Ensuite, le bandeau « Entretiens à faire » au-dessus des
 * onglets, puis un onglet par matériel (arbitrage Q1 A de la v3) : un bloc dont la tête dit le
 * compteur et porte la roue, le relevé en bande, les entretiens en rangées, et l'historique replié
 * dans un second bloc.
 */
class EquipmentUi {

	public function __construct() {

		\Asset::css('main', 'chart.css');
		\Asset::css('toolbox', 'toolbox.css');
		\Asset::css('toolbox', 'maintenance.css');
		\Asset::js('toolbox', 'maintenance.js');
		\Asset::js('main', 'chart.js');

	}

	public static function url(\structure\Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/outils/materiel';
	}

	/**
	 * Un matériel sur la page, son onglet ouvert.
	 */
	public static function urlEquipment(\structure\Structure $eStructure, Equipment $eEquipment): string {
		return self::url($eStructure).'?equipment='.$eEquipment['id'];
	}

	public static function urlCreate(\structure\Structure $eStructure): string {
		return '/toolbox/equipment:create?structure='.$eStructure['id'];
	}

	/**
	 * « Suivre un matériel » vit dans le menu de l'en-tête, comme « Nouveau pluviomètre » : sans
	 * matériel suivi, c'est l'accueil qui le propose. Dans le menu, un matériel est donc toujours déjà
	 * suivi, et le geste en suit un autre (`getFollow()`).
	 */
	public function getHeader(\structure\Structure $eStructure, \Collection $cEquipment, \Collection $cFavorite): string {

		if($eStructure->canPlanning() and $cEquipment->notEmpty()) {
			$menu = '<a href="'.self::urlCreate($eStructure).'" class="dropdown-item">'.self::getFollow(TRUE).'</a>';
			// Le réglage s'ouvre dès qu'un matériel est suivi, avant même le premier « Fait », qui le
			// demande sinon (`MaintenanceConfigurationUi`).
			$menu .= '<a href="'.MaintenanceConfigurationUi::urlCreate($eStructure).'" class="dropdown-item">'.s("Paramétrer le carnet d'entretien").'</a>';
		} else {
			$menu = '';
		}

		return new ToolUi()->getHeader($eStructure, Favorite::MAINTENANCE, $cFavorite, menu: $menu);

	}

	/**
	 * « Suivre un autre matériel » dès qu'un matériel est suivi (retour du 30/09).
	 */
	public static function getFollow(bool $hasFollowed): string {
		return $hasFollowed ? s("Suivre un autre matériel") : s("Suivre un matériel");
	}

	public function getStart(\structure\Structure $eStructure): string {

		$promises = [
			['calendar-check', s("Les entretiens à faire"), s("en retard ou bientôt, pour tout le matériel")],
			['speedometer2', s("Le compteur de chaque matériel"), s("en heures ou en kilomètres, dans l'année et comparé à l'an passé")],
			['clock-history', s("L'historique de l'entretien"), s("avec la date, le compteur et le coût")],
		];

		$content = '<p>'.s("Notez le compteur de votre matériel et les entretiens que vous faites, et voyez venir la prochaine vidange ou le prochain contrôle.").'</p>';
		$content .= new ToolUi()->getPromises($promises);

		return new \main\DesignUi()->getStart(
			\Asset::icon('wrench'),
			s("Le carnet d'entretien du matériel"),
			$content,
			image: \Asset::image('main', 'friends/luciole-classique.svg'),
			buttons: $eStructure->canPlanning() ? '<a href="'.self::urlCreate($eStructure).'" class="btn btn-primary btn-xl">'.self::getFollow(FALSE).'</a>' : NULL
		);

	}

	/**
	 * Le bandeau des entretiens en retard ou bientôt dus, au-dessus des onglets (arbitrage Q2 B de la
	 * v3) : la tête dit combien, la bande rouge nomme ce qui est en retard ; sans retard, ni bande ni
	 * teinte, et la tête nomme le prochain. Il arrive replié, comme sur la planche : la tête et la
	 * bande disent déjà l'essentiel, et déplié, le même retard se lirait trois fois.
	 */
	public function getDue(\structure\Structure $eStructure, \Collection $cMaintenanceDue, \Collection $cEquipment, Equipment $eEquipmentSelected): string {

		if($cMaintenanceDue->empty()) {
			return '';
		}

		$cMaintenanceOverdue = $cMaintenanceDue->find(fn($eMaintenance) => $eMaintenance['due'] === MaintenanceLib::OVERDUE);
		$cMaintenanceSoon = $cMaintenanceDue->find(fn($eMaintenance) => $eMaintenance['due'] === MaintenanceLib::SOON);

		// Le nombre est dans le titre (retours du 01/10) : des entretiens « planifiés » tant que
		// rien n'est en retard, « à réaliser » sinon. La tête ne nomme plus que le prochain, et seulement sans
		// retard, la bande rouge nommant sinon ce qui presse.
		if($cMaintenanceOverdue->empty()) {

			$title = p("{value} entretien planifié", "{value} entretiens planifiés", $cMaintenanceDue->count());
			$summary = self::getDueName($cMaintenanceSoon->first());
			$band = '';

		} else {

			$title = p("{value} entretien à réaliser", "{value} entretiens à réaliser", $cMaintenanceDue->count());
			$summary = '';

			if($cMaintenanceOverdue->count() === 1) {
				$band = self::getDueName($cMaintenanceOverdue->first());
			} else {
				$band = p("{name} et {value} autre en retard", "{name} et {value} autres en retard", $cMaintenanceOverdue->count() - 1, ['name' => self::getDueName($cMaintenanceOverdue->first(), FALSE)]);
			}

		}

		$toggle = attr('onclick', 'ToolboxMaintenance.toggle(this)');

		$h = '<div id="maintenance-due" class="util-deck maintenance-due'.($band === '' ? '' : ' util-deck-accent-danger').'">';
			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon('calendar-check').'</div>';
				$h .= '<a class="util-deck-body" '.$toggle.'>';
					$h .= '<div class="util-deck-title">'.$title.'</div>';
					if($summary !== '') {
						$h .= '<p>'.$summary.'</p>';
					}
				$h .= '</a>';
				$h .= '<a class="util-deck-chevron" '.$toggle.'>'.\Asset::icon('chevron-down').'</a>';
			$h .= '</div>';
			if($band !== '') {
				// La bande est un conteneur flex : sans l'enveloppe, la valeur (`util-unit`) y devient un
				// élément à part, séparé du texte par l'écart de la bande.
				$h .= '<div class="util-deck-band util-deck-band-state"><span>'.$band.'</span></div>';
			}
			$h .= '<div class="util-deck-fold">';
				$h .= new MaintenanceUi()->getDueRows($eStructure, $cMaintenanceDue, $eStructure->canPlanning(), $cEquipment->count() > 1, $eEquipmentSelected);
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * « Vidange moteur · Tracteur Massey 158 · dépassée de 24 h », ou sans l'échéance quand d'autres
	 * suivent dans la même phrase. La planche écrivait « du Tracteur » : un nom de matériel ne dit ni
	 * son genre ni s'il s'élide (« du Utilitaire », « du Bineuse »), le point médian des rangées le
	 * relie sans article, et relie de même l'échéance, comme les rangées.
	 */
	protected static function getDueName(Maintenance $eMaintenance, bool $withDue = TRUE): string {

		$name = s("{maintenance} · {tool}", ['maintenance' => encode($eMaintenance['name']), 'tool' => encode($eMaintenance['equipment']['tool']['name'])]);

		if($withDue === FALSE) {
			return $name;
		}

		return s("{name} · {due}", ['name' => $name, 'due' => MaintenanceUi::getDueText($eMaintenance)]);

	}

	/**
	 * Un onglet par matériel à partir de deux, dans l'ordre où ils sont entrés au carnet
	 * (`EquipmentLib::getByStructure()`).
	 * L'onglet ouvert est celui que désigne l'adresse (`$eEquipmentSelected`), sinon le dernier ouvert,
	 * que `Lime.Tab` retient par l'id de la barre ; `equipment` sort ensuite de l'adresse, pour que le
	 * rechargement qui suit un geste ne rouvre pas l'onglet de l'adresse.
	 * Une rangée trop large pour l'écran se replie en un onglet-menu (`tabs-h-fold`, `Lime.TabFold`), qui
	 * porte le matériel ouvert et la somme des retards des autres.
	 */
	public function getEquipments(\structure\Structure $eStructure, \Collection $cEquipment, Equipment $eEquipmentSelected): string {

		// Une barre d'un seul onglet n'offre aucun choix : le matériel s'affiche sous son nom.
		if($cEquipment->count() === 1) {

			$eEquipment = $cEquipment->first();

			$h = '<div class="util-title"><h2>'.encode($eEquipment['tool']['name']).'</h2></div>';
			$h .= $this->getContent($eStructure, $eEquipment);

			return $h;

		}

		$name = function(Equipment $eEquipment): string {

			// La vignette du matériel devant son nom (arbitrage Q1 C de la planche pf-t4-photo). Un
			// matériel sans vignette ne porte rien : l'icône de remplacement ne le distinguerait pas de
			// son voisin.
			$h = '';

			if($eEquipment['tool']['vignette'] !== NULL) {
				$h .= '<span class="tab-item-icon">'.\work\ToolUi::getVignette($eEquipment['tool'], '2.6667rem', '2rem').'</span>';
			}

			$h .= encode($eEquipment['tool']['name']);

			if($eEquipment['overdue'] > 0) {
				$h .= '<span class="util-counter util-counter-danger">'.$eEquipment['overdue'].'</span>';
			}

			return $h;

		};

		$forceTab = $eEquipmentSelected->notEmpty() ? 'maintenance-equipment-'.$eEquipmentSelected['id'] : NULL;

		$tabs = '';
		$panels = '';

		foreach($cEquipment as $eEquipment) {

			$tabs .= '<a class="tab-item" data-tab="maintenance-equipment-'.$eEquipment['id'].'" onclick="Lime.Tab.select(this)">'.$name($eEquipment).'</a>';

			$panels .= '<div class="tab-panel" data-tab="maintenance-equipment-'.$eEquipment['id'].'">';
				$panels .= $this->getContent($eStructure, $eEquipment);
			$panels .= '</div>';

		}

		return \main\DesignUi::getPageTabs($tabs, panels: $panels, attributes: [
			'id' => 'maintenance-equipments',
			'onrender' => 'Lime.Tab.restore(this, "maintenance-equipment-'.$cEquipment->first()['id'].'", '.json_encode($forceTab).'); history.removeArgument("equipment")',
		], fold: TRUE);

	}

	/**
	 * Le bloc d'un matériel (arbitrage Q1 A de la v3) : le compteur en tête avec la roue, le relevé en
	 * bande, les entretiens en rangées et « + Entretien » en bande (dans le bloc vide tant qu'il n'y en
	 * a aucun), puis l'historique, déroulé sous le bloc comme celui d'une vente. Sans le droit du planning, ni roue, ni relevé, ni « + Entretien » : l'observateur
	 * lit le compteur, les entretiens et leur historique (arbitrage Q8 A de la v2), et les emporte en PDF
	 * par le bouton qui prend la place de la roue (`getDownload()`).
	 */
	protected function getContent(\structure\Structure $eStructure, Equipment $eEquipment): string {

		$write = $eEquipment->assertUpdate();

		$h = '<div class="util-deck util-deck-open mb-2">';

			$h .= '<div class="util-deck-head">';
				// La vignette prend la place de l'icône ronde, qui reste au matériel sans vignette (Q1 C).
				// Elle en prend aussi la hauteur, celle de `util-icon` : la tête ne change pas de hauteur
				// d'un matériel à l'autre, et la bande du relevé ne bouge pas au changement d'onglet.
				if($eEquipment['tool']['vignette'] !== NULL) {
					$h .= \work\ToolUi::getVignette($eEquipment['tool'], '3.3333rem', '2.5rem');
				} else {
					$h .= '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon($eEquipment->hasMeter() ? 'speedometer2' : 'calendar-check').'</div>';
				}

				$h .= '<div class="util-deck-body">'.$this->getSummary($eEquipment).'</div>';

				// Un observateur dont le matériel n'a rien à télécharger n'a ni roue ni bouton : la place
				// ne reste pas vide (production/toolbox/CLAUDE.md, « L'écran de l'observateur »).
				$aside = $write ? $this->getActions($eStructure, $eEquipment) : $this->getDownload($eEquipment);

				if($aside !== '') {
					$h .= '<div class="util-deck-aside">'.$aside.'</div>';
				}

			$h .= '</div>';

			if($write and $eEquipment->hasMeter()) {
				$h .= $this->getQuick($eStructure, $eEquipment);
			}

			$h .= new MaintenanceUi()->getList($eEquipment, $write);

			// Sans entretien, le bloc vide porte déjà « Nouvelle saisie ». Ici le même bouton, calé à gauche,
			// remplace les deux de la planche pf-t4-v4 (retour du 01/10).
			if($write and $eEquipment['cMaintenanceScheduled']->notEmpty()) {
				$h .= '<div class="util-deck-band">';
					$h .= MaintenanceUi::getCreateMenu($eEquipment, 'btn btn-sm btn-outline-primary', 'bottom-start');
				$h .= '</div>';
			}

		$h .= '</div>';

		$h .= new MaintenanceDoneUi()->getHistory($eEquipment, $write);
		$h .= $this->getChart($eEquipment);

		return $h;

	}

	/**
	 * Le compteur dans le temps, un point par relevé, du premier au dernier (retour du 01/10,
	 * « un graphique linéaire depuis le début de la saisie serait plus pertinent car on ne sait pas si les
	 * utilisateurs vont faire une saisie régulière ») : la courbe montre ce qui a été saisi, sans rien
	 * calculer par mois. L'écart entre deux points suit le temps qui les sépare, et le graphique prend
	 * 5rem par relevé, jusqu'à la largeur de l'écran (second retour du 01/10,
	 * `UtilChart.createTimeLine()`). Rien avec un seul relevé, qui ne trace aucune courbe ; la table n'en
	 * porte qu'un par jour, deux relevés tiennent donc sur deux jours.
	 */
	protected function getChart(Equipment $eEquipment): string {

		if($eEquipment->hasMeter() === FALSE or $eEquipment['cEquipmentReading']->count() < 2) {
			return '';
		}

		\Asset::lib('main', 'chart-4.5.1.umd.min.js');

		$values = array_values($eEquipment['cEquipmentReading']->getColumn('value'));
		$dates = array_values($eEquipment['cEquipmentReading']->getColumn('date'));

		$h = '<h3>'.s("Évolution du compteur").'</h3>';
		$h .= '<div class="analyze-bar mb-2">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createTimeLine(this, '.json_encode($values).', '.json_encode($dates).', '.json_encode(self::getUnit($eEquipment['meter'])).')').'</canvas>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La tête du bloc : le compteur en titre, le jour du relevé et ce qu'il a tourné dans l'année et
	 * dans l'an passé à la même date, chaque cumul quand les relevés couvrent son début
	 * (`EquipmentReadingLib::getYear()`). Sans compteur, le prochain entretien à la date en titre, sans
	 * ligne dessous.
	 */
	protected function getSummary(Equipment $eEquipment): string {

		$details = [];

		if($eEquipment->hasMeter() === FALSE) {

			// L'absence de compteur ne s'annonce pas (retour du 30/09) : la prochaine échéance
			// monte en titre. Sans échéance, le titre le dit plutôt que de redire le nom du matériel, que
			// l'onglet ou le menu porte déjà, et le bloc vide dessous ne le répète pas
			// (`MaintenanceUi::getList()`).
			$eMaintenanceNext = $eEquipment['eMaintenanceNext'];

			if($eMaintenanceNext->empty()) {
				$title = s("Aucun entretien planifié");
			} else if($eMaintenanceNext['due'] === MaintenanceLib::OVERDUE) {
				$title = s("Entretien en retard depuis le {value}", self::getDate($eMaintenanceNext['nextDate']));
			} else {
				$title = s("Prochain entretien le {value}", self::getDate($eMaintenanceNext['nextDate']));
			}

		} else if($eEquipment['eEquipmentReadingLast']->empty()) {

			$title = s("Aucun relevé");
			$details[] = s("Compteur en {value}", self::getUnit($eEquipment['meter'], long: TRUE));

		} else {

			$title = self::getValue($eEquipment, $eEquipment['eEquipmentReadingLast']['value']);
			$details[] = s("Relevé le {value}", self::getDate($eEquipment['eEquipmentReadingLast']['date']));

			if($eEquipment['valueYear'] !== NULL) {
				$details[] = s("{value} depuis le 1er janvier", self::getValue($eEquipment, $eEquipment['valueYear']));
			}

			if($eEquipment['valueYearPrevious'] !== NULL) {
				$details[] = s("{value} en {year} à la même date", ['value' => self::getValue($eEquipment, $eEquipment['valueYearPrevious']), 'year' => \Clock::year() - 1]);
			}

		}

		// L'immatriculation précède le compteur (demande du 01/10), et ne se montre qu'avec lui.
		// Elle se lit en contexte, atténuée : le compteur garde seul le gras (recette du 01/10).
		if($eEquipment->hasMeter() and $eEquipment['registration'] !== NULL) {
			$title = '<span class="maintenance-head-registration">'.encode($eEquipment['registration']).' · </span>'.$title;
		}

		// Un seul enfant : `util-deck-title` est un flex à gouttière, qui écarterait le texte de sa date.
		$h = '<div class="util-deck-title"><span>'.$title.'</span></div>';

		if($details) {
			$h .= '<p>'.implode(' · ', $details).'</p>';
		}

		return $h;

	}

	/**
	 * Le relevé, en bande sous la tête (arbitrage Q1 A de la v3) : le matériel est celui du bloc, il
	 * reste le jour et le compteur.
	 */
	protected function getQuick(\structure\Structure $eStructure, Equipment $eEquipment): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/equipmentReading:doCreate', ['id' => 'maintenance-quick-'.$eEquipment['id'], 'class' => 'util-deck-band maintenance-quick']);
			$h .= $form->hidden('structure', $eStructure);
			$h .= $form->hidden('equipment', $eEquipment);
			$h .= $form->hidden('meterConfirmed', 0);
			$h .= $form->date('date', \Clock::date(), ['max' => \Clock::date()]);
			$h .= $form->inputGroup($form->number('value', NULL, ['min' => 0]).$form->addon(self::getUnit($eEquipment['meter'])));
			$h .= $form->submit(s("Relever"), ['class' => 'btn btn-sm btn-primary']);
			// Les champs de la bande n'ont pas de groupe, où le refus viendrait se ranger : sans cette
			// case, `Lime.Form` ne trouve aucun `data-wrapper` et le message part dans le bandeau.
			$h .= '<div class="maintenance-quick-error" data-wrapper="date value"></div>';
		$h .= $form->close();

		return $h;

	}

	/**
	 * La roue d'un matériel. « Modifier le matériel » ouvre sa fiche de « Mon matériel », que seul un
	 * gestionnaire règle (`work\Tool::canWrite()`), et où se règle son compteur (arbitrage Q3 A de la v3).
	 * Le carnet se télécharge en PDF, et son historique en CSV pour qui gère la structure : sortir des
	 * données est le droit d'un export (production/toolbox/CLAUDE.md, « Qui écrit dans la Boîte »).
	 * Ni l'un ni l'autre tant que le matériel n'a rien à sortir (`Equipment::acceptDownload()`,
	 * `acceptExport()`).
	 */
	protected function getActions(\structure\Structure $eStructure, Equipment $eEquipment): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end" title="'.s("Réglages du matériel").'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.encode($eEquipment['tool']['name']).'</div>';

			$items = '';

			if($eStructure->canManage()) {
				$items .= '<a href="/work/tool:update?id='.$eEquipment['tool']['id'].'" class="dropdown-item">'.s("Modifier le matériel").'</a>';
			}
			if($eEquipment->acceptDownload()) {
				$items .= '<a '.self::getPdfAttributes($eEquipment).' class="dropdown-item">'.s("Télécharger le carnet en PDF").'</a>';
			}
			if($eStructure->canManage() and $eEquipment->acceptExport()) {
				$items .= '<a href="/toolbox/equipment:exportCsv?id='.$eEquipment['id'].'" class="dropdown-item" data-ajax-download data-waiter>'.s("Exporter l'historique en CSV").'</a>';
			}

			// Sans rien au-dessus, le trait séparerait « Ne plus suivre » du seul titre.
			if($items !== '') {
				$h .= $items;
				$h .= '<div class="dropdown-divider"></div>';
			}

			$h .= '<a data-ajax="/toolbox/equipment:doUpdateStatus" post-id="'.$eEquipment['id'].'" post-status="'.Equipment::INACTIVE.'" class="dropdown-item" '.attr('data-confirm', s("{value} sort du carnet. Son historique reste.", $eEquipment['tool']['name'])).'>'.s("Ne plus suivre").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Sans le droit du planning, le bloc n'a pas de roue : le PDF, que tout membre télécharge, y prend
	 * sa place en bouton, sous la forme du registre de traitement (`TreatmentUi::getDownload()`).
	 */
	protected function getDownload(Equipment $eEquipment): string {

		if($eEquipment->acceptDownload() === FALSE) {
			return '';
		}

		return '<a '.self::getPdfAttributes($eEquipment).' class="btn btn-outline-primary">'.\Asset::icon('file-pdf').' '.s("PDF").'</a>';

	}

	protected static function getPdfAttributes(Equipment $eEquipment): string {
		return 'href="/toolbox/equipment:downloadPdf?id='.$eEquipment['id'].'" data-ajax-download="'.encode(EquipmentPdfUi::getFilename($eEquipment)).'" data-waiter="'.s("Création en cours").'" data-waiter-timeout="8"';
	}

	public function getExportHeader(): array {

		return [
			'date',
			'type',
			'maintenance',
			'comment',
			'meter',
			'meter_unit',
			'cost',
		];

	}

	public static function getExportFilename(Equipment $eEquipment): string {
		return 'carnet-entretien-'.toFqn($eEquipment['tool']['name']).'-'.\Clock::date().'.csv';
	}

	/**
	 * Le compteur du matériel, sous un champ où l'on saisit une valeur du compteur
	 * (`EquipmentLib::fillReadingLast()`). Rien sans compteur ou sans relevé.
	 */
	public static function getReadingLast(Equipment $eEquipment): string {

		$eEquipmentReading = $eEquipment['eEquipmentReadingLast'];

		if($eEquipmentReading->empty()) {
			return '';
		}

		return \util\FormUi::info(s("Compteur actuel {value}, relevé le {date}", [
			'value' => self::getValue($eEquipment, $eEquipmentReading['value']),
			'date' => self::getDate($eEquipmentReading['date']),
		]));

	}

	/**
	 * L'unité du compteur, abrégée après un nombre ou un champ (« 1 250 h », « toutes les [400] h »), en
	 * toutes lettres dans une phrase (« Compteur en heures »). Un matériel sans compteur n'a aucune
	 * valeur à dire.
	 */
	public static function getUnit(string $meter, bool $long = FALSE): string {

		return match($meter) {
			Equipment::HOURS => $long ? s("heures") : s("h"),
			Equipment::KILOMETERS => $long ? s("kilomètres") : s("km"),
		};

	}

	public static function getValue(Equipment $eEquipment, int $value): string {
		return '<span class="util-unit">'.\util\TextUi::number($value, 0).' '.self::getUnit($eEquipment['meter']).'</span>';
	}

	/**
	 * Le jour et le mois dans l'année en cours, l'année en plus au-delà, comme les relevés de pluie.
	 * `$year` la donne toujours : un document qu'on archive ne se lit pas dans l'année où il est sorti.
	 */
	public static function getDate(string $date, bool $year = FALSE): string {

		$format = ($year === FALSE and substr($date, 0, 4) === (string)\Clock::year()) ? \util\DateUi::DAY_MONTH : \util\DateUi::DATE;

		return '<span class="util-unit">'.\util\DateUi::textual($date, $format).'</span>';

	}

	/**
	 * Le matériel se choisit dans « Mon matériel », et un compteur donné part comme premier relevé
	 * (arbitrage Q5 A).
	 */
	public function create(Equipment $eEquipment, EquipmentReading $eEquipmentReading, bool $hasFollowed): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/equipment:doFollow', ['id' => 'equipment-create']);

			$h .= $form->hidden('structure', $eEquipment['structure']);
			$h .= $form->hidden('toolName', '');
			$h .= $form->hidden('meterConfirmed', 0);

			$h .= $form->dynamicGroup($eEquipment, 'tool');
			$h .= $form->dynamicGroup($eEquipment, 'meter');
			// Le premier relevé et l'immatriculation n'apparaissent qu'avec le compteur choisi au-dessus,
			// dont le relevé suit l'unité (`ToolboxMaintenance.changeMeter()`) : le panneau s'ouvre sur
			// « Aucun » (retour du 01/10).
			$h .= $form->dynamicGroup($eEquipmentReading, 'value', function(\PropertyDescriber $d) use($eEquipment) {
				$d->label = s("Compteur aujourd'hui");
				$d->group = [
					'data-meter' => 'value',
					'class' => $eEquipment->hasMeter() ? '' : 'hide'
				];
				$d->append = fn(\util\FormUi $form) => $form->addon($eEquipment->hasMeter() ? self::getUnit($eEquipment['meter']) : '', [
					'data-unit-'.Equipment::HOURS => self::getUnit(Equipment::HOURS),
					'data-unit-'.Equipment::KILOMETERS => self::getUnit(Equipment::KILOMETERS),
				]);
			});
			$h .= $form->dynamicGroup($eEquipment, 'registration');

			$h .= $form->group(
				content: $form->submit(s("Suivre ce matériel"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-equipment-create',
			title: self::getFollow($hasFollowed),
			body: $h
		);

	}

	/**
	 * `value` porte le nom tapé, que le widget recopie dans le champ et que `ToolboxMaintenance`
	 * recopie dans `toolName` ; « new » dit à `Equipment::properties()` que le matériel est à créer.
	 */
	public static function getToolAutocompleteValidate(string $name): array {

		return [
			'class' => 'autocomplete-link',
			'value' => 'new',
			'itemHtml' => \Asset::icon('check-lg').'<div>'.s("Valider « {value} »", encode($name)).'</div>',
			'itemText' => $name
		];

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Equipment::model()->describer($property, [
			'tool' => s("Matériel"),
			'meter' => s("Compteur"),
			'registration' => s("Immatriculation"),
		]);

		switch($property) {

			case 'tool' :
				new \work\ToolUi()->query($d);
				$d->placeholder = s("Tapez le nom du matériel");
				$d->autocompleteUrl = '/toolbox/equipment:query';
				$d->autocompleteBody = fn(\util\FormUi $form, Equipment $e) => [
					'structure' => $e['structure']['id']
				];
				$d->attributes = [
					'data-autocomplete-id' => 'equipment-tool'
				];
				break;

			case 'meter' :
				$values = [
					Equipment::HOURS => s("Heures"),
					Equipment::KILOMETERS => s("Kilomètres"),
				];
				$d->values = $values;
				$d->field = 'radio';
				$d->placeholder = s("Aucun");
				// À l'entrée au carnet, l'unité du premier relevé suit le choix. Ensuite, l'autre unité
				// que celle des valeurs déjà saisies ne s'offre plus (`meterLocked`, étape `locked`), et son
				// libellé dit pourquoi, par le message du refus. `callbackRadioContent` ne reçoit que le
				// libellé de l'option : l'unité se retrouve dans `$values`.
				$d->attributes = function(\util\FormUi $form, Equipment $e) use($values) {

					$attributes = [
						'onchange' => 'ToolboxMaintenance.changeMeter(this)'
					];

					if($e->exists() === FALSE) {
						return $attributes;
					}

					$isLocked = fn(?string $meter): bool => ($meter !== NULL and $e['meterLocked'] !== NULL and $meter !== $e['meterLocked']);

					return $attributes + [
						'callbackRadioAttributes' => fn($option, $meter) => $isLocked($meter) ? ['disabled' => 'disabled'] : [],
						'callbackRadioContent' => fn(string $label) => $isLocked(array_search($label, $values)) ? '<span '.attr('title', AlertUi::getError('Equipment::meter.locked')).'>'.$label.'</span>' : $label
					];

				};
				break;

			// Le champ suit le compteur, à l'entrée au carnet comme dans « Modifier le matériel »
			// (`ToolboxMaintenance.changeMeter()`). Caché, il garde sa valeur, qui revient avec le compteur.
			case 'registration' :
				$d->group = fn(Equipment $e) => [
					'data-meter' => 'registration',
					'class' => 'maintenance-registration'.($e->hasMeter() ? '' : ' hide')
				];
				break;

		}

		return $d;

	}

}
?>
