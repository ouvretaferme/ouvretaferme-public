<?php
namespace planning;

/**
 * L'écran des récoltes d'une semaine : une ligne par culture, les jours de lundi à dimanche, et la somme
 * de la semaine à droite.
 *
 * Les sept jours sont les plis d'un `util-bellows` en colonnes (piste D de la planche des jours) : un jour
 * fermé est une bande, son nom écrit à la verticale en tête et une marque sur chaque ligne où il porte une
 * récolte ; le jour ouvert (`open`, `HarvestWeekLib::getDays()`) n'a plus de bande, et son panneau montre
 * le contenu du jour. Un clic sur une bande y fait glisser le panneau (`MainBellows`, `bellowsAi.js`),
 * sans aller-retour : chaque cellule porte déjà sa marque et son contenu. Les jours à venir sont des
 * bandes inactives.
 *
 * Qui peut saisir (`$canHarvest`) trouve dans le jour ouvert ses gestes : une quantité qui se remplace en
 * `util-quick` et son « + » qui ajoute, le même « + » seul sur une case vide, et la ligne qui ouvre au clavier la saisie du jour ouvert,
 * à défaut du jour proposé sur une semaine passée qu'on lit sans jour ouvert (`proposed`, `HarvestWeekLib::getDays()`).
 *
 * La saisie rapide (`$quick`, une préférence du membre, R-Retours-1210 V35) remplace ces gestes par un
 * champ dans chaque case vide du jour ouvert (`getCell()`) ; une case déjà récoltée garde sa quantité, qui
 * se remplace, et son « + », comme sans la saisie rapide (V52).
 *
 * Les ventes à venir et le stock (`$sales`, une autre préférence du membre, V47) prennent deux colonnes entre
 * la série et lundi pour qui la structure ouvre la vente. Toutes les séries d'une espèce partagent ces
 * chiffres (`HarvestWeekLib::prepareSales()`) : seul l'intertitre de l'espèce les dit (`getSection()`), et
 * chaque ligne garde ses deux cases vides, pour que les plis gardent leur rang d'une ligne à l'autre
 * (`bellowsAi.js`).
 *
 * Une culture récoltée par variété ou par calibre range ses parts sous sa ligne (`getPart()`), une
 * sous-ligne par couple, chacune avec ses plis et ses gestes ; la ligne de la culture n'en garde que les
 * totaux, en texte : remplacer un total réparti entre plusieurs couples ne dirait pas lequel change.
 *
 * Une saisie répond par `getRow()` sur les seules lignes touchées, `#planning-harvest-<id>` : la ligne
 * se rend seule, à partir de ce que la page a préparé et de rien d'autre, et le tiroir ouvert
 * (`HarvestEntryUi::getDrawer()`) s'y ajoute après la ligne ou la part qui l'a ouvert, sans rien changer
 * au reste.
 */
class HarvestWeekUi {

	public function __construct() {

		\Asset::css('planning', 'planning.css');
		\Asset::css('series', 'task.css');
		\Asset::js('planning', 'planning.js');
		\Asset::css('series', 'timesheet.css');
		\Asset::js('series', 'timesheet.js');
		\Asset::js('main', 'bellowsAi.js');

	}

	/**
	 * Sans `$open`, l'écran s'ouvre sur aujourd'hui dans la semaine courante ; une semaine passée s'ouvre sans
	 * jour ouvert, sauf en saisie rapide, qui ouvre le jour proposé (`harvestWeek.p.php`).
	 */
	public static function url(\structure\Structure $eStructure, ?string $week = NULL, ?string $open = NULL): string {

		$url = \structure\StructureUi::urlStructure($eStructure).'/planning-v2/recoltes';

		if($week !== NULL and $week !== \Clock::week()) {
			$url .= '/'.$week;
		}

		if($open !== NULL) {
			$url .= '?'.http_build_query(['open' => $open]);
		}

		return $url;

	}

	/**
	 * Au tactile, `planning.js` ajoute `sheet=1`, qui ouvre la feuille à pavé.
	 * Une part (`$part`, `HarvestWeekLib::getPart()`) ouvre la saisie sur son couple, posé même vide :
	 * c'est ce qui distingue « Non spécifiée » de la culture entière. Une intervention (`$eTask`) ouvre la
	 * saisie dans son unité (`HarvestWeekLib::getTaskChosen()`).
	 */
	public static function urlOpen(\structure\Structure $eStructure, string $week, \series\Cultivation $eCultivation, string $date, string $mode, array $part = [], \series\Task $eTask = new \series\Task()): string {

		$arguments = [
			'id' => $eStructure['id'],
			'week' => $week,
			'cultivation' => $eCultivation['id'],
			'date' => $date,
			'mode' => $mode,
		];

		if($part !== []) {
			$arguments += self::getCoupleArguments($part);
		}

		if($eTask->notEmpty()) {
			$arguments['task'] = $eTask['id'];
		}

		return '/planning/harvestWeek:open?'.http_build_query($arguments);

	}

	public static function getCoupleArguments(array $part): array {

		return [
			'variety' => $part['eVariety']->empty() ? '' : $part['eVariety']['id'],
			'harvestSize' => $part['eSize']->empty() ? '' : $part['eSize']['id'],
		];

	}

	public static function urlCreate(\structure\Structure $eStructure, string $week, ?string $origin = NULL): string {

		$arguments = [
			'id' => $eStructure['id'],
			'week' => $week,
		];

		if($origin !== NULL) {
			$arguments['origin'] = $origin;
		}

		return '/planning/harvestWeek:create?'.http_build_query($arguments);

	}

	/**
	 * L'en-tête de semaine est celui du planning actuel (`\series\PlanningUi::getCalendar()`), pour que
	 * passer d'un planning à l'autre ne change pas la façon de changer de semaine. La saisie rapide se garde
	 * d'une semaine à l'autre par le membre, sans voyager dans l'adresse.
	 */
	public function getCalendar(\structure\Structure $eStructure, string $week): string {

		return new \series\PlanningUi()->getCalendar(
			$eStructure,
			$week,
			fn($week) => self::url($eStructure, $week)
		);

	}

	/**
	 * La liste entière, sous un identifiant stable : c'est elle que remplace une saisie qui fait passer
	 * l'écran du bloc vide à la grille, ou l'inverse. Elle porte les onglets, puis la rangée de l'écran pour
	 * qui saisit (`$canHarvest`) ou que la structure ouvre à la vente (`$canSales`) : les interrupteurs de la
	 * saisie rapide et des ventes et du stock à gauche, actifs sur toute semaine, et « Nouvelle récolte » à droite (arbitrage V-Planning du 2026-09-30, P4), qui n'y est que devant une
	 * grille : le bloc vide garde la sienne. Une semaine à venir n'a pas de « Nouvelle récolte »
	 * (`$canCreate`) : la saisie appartient à la semaine affichée.
	 *
	 * `data-quick` et `data-sales` disent à `harvestWeek.v.php` la forme des lignes à l'écran : une réponse ne
	 * remplace que des lignes de la même forme, un interrupteur pouvant avoir basculé dans un autre onglet.
	 */
	public function get(\structure\Structure $eStructure, string $week, array $days, \Collection $cCultivation, \structure\Member $eMember, bool $canHarvest = FALSE, bool $canCreate = FALSE, bool $quick = FALSE, bool $canSales = FALSE, bool $sales = FALSE): string {

		$uiTab = new TabUi();

		// Les cases des séries nourrissent la barre « Récolter » (`getBatch()`), posée hors de la liste.
		$h = '<div id="planning-harvest-list" data-structure="'.$eStructure['id'].'" data-week="'.$week.'" data-quick="'.($quick ? 1 : 0).'" data-sales="'.($sales ? 1 : 0).'"'.($canCreate ? ' data-batch="#planning-harvest-batch"' : '').'>';

			if($cCultivation->empty()) {

				$h .= $uiTab->get($eStructure, $week, 'recoltes', $eMember);

				$h .= '<div class="util-block-empty">';
					$h .= '<h3>'.s("Aucune culture en récolte cette semaine").'</h3>';
					if($canCreate) {
						$h .= $this->getEmptyButton($eStructure, $week);
					}
				$h .= '</div>';

			} else {

				$h .= $uiTab->get($eStructure, $week, 'recoltes', $eMember);

				if($canHarvest or $canSales) {
					$h .= $uiTab->getTools(
						($canHarvest ? $uiTab->getHarvestQuickSwitch($eMember) : '').($canSales ? $uiTab->getHarvestSalesSwitch($eMember) : ''),
						$canCreate ? $this->getCreateButton($eStructure, $week) : ''
					);
				}

				$h .= $this->getGrid($eStructure, $week, $days, $cCultivation, $eMember, $canHarvest, $quick, $canCreate, $sales);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La barre « Pour la sélection » des séries cochées (DG-2), qui ne porte que « Récolter », le panneau du
	 * planning (`TaskBatchUi::getHarvest()`). Elle se range hors de la liste, qu'une réponse peut remplacer
	 * entière. Le jour ouvert voyage avec le geste (`post-open`, `PlanningHarvestSelection.change()`).
	 */
	public function getBatch(\structure\Structure $eStructure, string $week, array $days): string {

		$attributes = [
			'post-id' => $eStructure['id'],
			'post-week' => $week,
			'post-open' => array_find_key($days, fn($day) => $day['open']) ?? '',
		];

		$h = '<a data-ajax-submit="/planning/harvestWeek:batchHarvest" data-ajax-method="get" '.attrs($attributes).' class="batch-item">'.\Asset::icon('basket').'<span>'.s("Récolter").'</span></a>';

		return \util\BatchUi::group('planning-harvest-batch', $h, hide: 'PlanningHarvestSelection.clear()');

	}

	/**
	 * Sous 450, le bouton ne garde que son +, pour que la rangée tienne sur une ligne avec les deux
	 * interrupteurs (V55).
	 */
	protected function getCreateButton(\structure\Structure $eStructure, string $week): string {

		return '<a href="'.encode(self::urlCreate($eStructure, $week)).'" class="btn btn-outline-primary" data-planning-harvest-create>'.\Asset::icon('plus-lg').'<span class="hide-xxs-down"> '.s("Nouvelle récolte").'</span></a>';

	}

	/**
	 * Le même geste dans l'état vide, qui n'a pas de rangée : le bouton principal de `util-block-empty`,
	 * comme « + À faire » et « + Fait » (`TaskCreateUi::getEmptyButton()`), pour que les onglets vides se
	 * ressemblent (retours 272 et 273).
	 */
	protected function getEmptyButton(\structure\Structure $eStructure, string $week): string {

		$h = '<div class="util-buttons">';
			$h .= '<a href="'.encode(self::urlCreate($eStructure, $week)).'" class="btn btn-primary">'.s("Nouvelle récolte").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La grille porte la variable du jour ouvert (`--util-bellows-<n>`), que les pistes des plis lisent
	 * (`planning.css`) : la première peinture est juste sans attendre le script. Une semaine à venir n'a aucun
	 * jour ouvert, donc aucune variable.
	 *
	 * Les lignes se séparent d'un trait (`util-table-bordered`), en lecture comme en saisie : le fond de
	 * la grille est uni, pour que la colonne du jour s'en soulève (retour 181), et rien d'autre ne
	 * sépare deux séries (retour 216).
	 *
	 * Entre la semaine et la période prévue, la colonne de la semaine précédente.
	 */
	protected function getGrid(\structure\Structure $eStructure, string $week, array $days, \Collection $cCultivation, \structure\Member $eMember, bool $canHarvest, bool $quick, bool $canSelect, bool $sales): string {

		$openDate = array_find_key($days, fn($day) => $day['open']);
		$style = ($openDate === NULL) ? '' : ' style="--util-bellows-'.$days[$openDate]['day'].': 1"';

		$h = '<div class="util-overflow-md planning-harvest-overflow">';
			$h .= '<div class="util-table util-table-bordered util-table-sticky planning-harvest-grid util-bellows-columns planning-harvest-grid-week'.($sales ? ' planning-harvest-grid-sales' : '').'"'.$style.'>';

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Série").'</div>';
					if($sales) {
						$h .= $this->getSalesHead($eStructure, $week, $eMember);
						$h .= '<div class="planning-harvest-stock text-end hide-sm-down">'.s("Stock").'</div>';
					}
					foreach($days as $date => $day) {
						$h .= $this->getHeadDay($date, $day);
					}
					$h .= '<div class="text-end hide-xs-down">'.s("Semaine").'</div>';
					$h .= '<div class="text-end planning-harvest-previous hide-lg-down">'.s("Semaine précédente").'</div>';
					$h .= '<div class="planning-harvest-expected hide-sm-down">'.s("Prévue").'</div>';
				$h .= '</div>';

				$plant = NULL;

				foreach($cCultivation as $eCultivation) {

					if($eCultivation['plant']['id'] !== $plant) {
						$plant = $eCultivation['plant']['id'];
						$h .= $this->getSection($eCultivation['plant'], $days, $canSelect, $sales, $eCultivation['sales']);
					}

					$h .= $this->getRow($eStructure, $week, $days, $eCultivation, $canHarvest, $quick, $canSelect, $sales);

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'en-tête d'un jour le dit deux fois : son nom à la verticale dans la bande, et en titre du panneau la
	 * pastille du planning (`DateUi::calendarWeekday()`, comme `util-day` dans la fiche d'une intervention ;
	 * contre-recette du lot I, I6). La feuille du socle montre l'un ou l'autre selon `util-bellows-titled`. Un
	 * jour à venir ne s'ouvre pas et n'a pas de titre. Le jour ouvert porte la grande pastille, pleine
	 * (`util-calendar-lg`, `util-calendar-selected`), celle de la colonne qui se détache du tableau (retour 181,
	 * puis R-Retours-1210 V39 : la pastille pleine dit le jour ouvert, plus aujourd'hui), et
	 * `PlanningHarvest.followBellows()` la déplace quand un autre jour s'ouvre.
	 * Les chevrons qui encadrent la pastille ouvrent le jour d'avant ou d'après (`previous`, `next` de
	 * `HarvestWeekLib::getDays()`), et s'éteignent au bout de la semaine ou devant un jour à venir. Chaque titre
	 * porte les siens : seul celui du pli ouvert se voit. Ils n'ont ni fond ni bordure (`btn-bare`, V38).
	 * `data-date` dit à `planning.js` quel jour est ouvert à l'écran.
	 */
	protected function getHeadDay(string $date, array $day): string {

		$name = \util\DateUi::getDayName($day['day']).' '.$day['number'];

		$h = '<div class="'.$this->getDayClass($date, $day, head: TRUE).'" data-date="'.$date.'">';

			$h .= '<div class="util-bellows-band"'.($day['future'] ? '' : ' tabindex="0"').'>';
				$h .= '<span class="util-bellows-name">'.$name.'</span>';
			$h .= '</div>';

			$h .= '<div class="util-bellows-panel">';
				if($day['future'] === FALSE) {
					$h .= '<div class="util-bellows-title">';
						$h .= '<span class="planning-harvest-day-step">';
							$h .= $this->getHeadDayStep($day['previous'], 'chevron-left');
							$h .= \util\DateUi::calendarWeekday($date, selected: $day['open'], large: $day['open']);
							$h .= $this->getHeadDayStep($day['next'], 'chevron-right');
						$h .= '</span>';
					$h .= '</div>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getHeadDayStep(?string $date, string $icon): string {

		if($date === NULL) {
			return '<a class="btn btn-only-icon btn-bare disabled">'.\Asset::icon($icon).'</a>';
		} else {
			return '<a class="btn btn-only-icon btn-bare" data-planning-harvest-step="'.$date.'">'.\Asset::icon($icon).'</a>';
		}

	}

	/**
	 * La ligne ouvre au clavier la saisie du jour proposé au rendu, le jour ouvert ou à défaut celui de « Nouvelle
	 * récolte » ; `planning.js` y substitue le jour ouvert à l'écran quand on en a ouvert un depuis. En saisie
	 * rapide (`$quick`), le champ de la case tient ce rôle, et la ligne n'ouvre rien. Les parts suivent les
	 * totaux, dans le rang de la ligne.
	 *
	 * La case de la série (`$canSelect`, DG-2) la coche pour « Récolter » (`getBatch()`), dans la colonne de la
	 * vignette de son espèce.
	 */
	public function getRow(\structure\Structure $eStructure, string $week, array $days, \series\Cultivation $eCultivation, bool $canHarvest = FALSE, bool $quick = FALSE, bool $canSelect = FALSE, bool $sales = FALSE): string {

		$proposedDate = array_find_key($days, fn($day) => $day['proposed']);

		if($canHarvest and $quick === FALSE and $proposedDate !== NULL) {
			$open = ' tabindex="0" data-planning-harvest-open="'.encode(self::urlOpen($eStructure, $week, $eCultivation, $proposedDate, 'add')).'"';
		} else {
			$open = '';
		}

		// Une culture ventilée additionne ses parts : la ligne le dit (retour 433).
		$sum = ($eCultivation['harvestedParts'] === []) ? '' : ' planning-harvest-sum';

		$h = '<div class="util-table-row'.$sum.'" id="planning-harvest-'.$eCultivation['id'].'" data-plant="'.$eCultivation['plant']['id'].'"'.$open.'>';

			$h .= '<div class="planning-harvest-crop">';
				if($canSelect) {
					$h .= $this->getCheckbox($eCultivation);
				}
				$h .= '<div>';
					$h .= '<div class="planning-harvest-crop-line">';
						$h .= '<span>';
							$h .= '<a href="'.\series\SeriesUi::url($eCultivation['series']).'" class="planning-harvest-name">'.$this->getName($eCultivation, places: FALSE, startWeek: FALSE).'</a>';
							$h .= \sequence\CropUi::start($eCultivation, \work\WorkSetting::$mainActions);
							// Sans coupure possible, le nom et « Année 1 » ne font qu'un mot, qui déborde de la colonne de la
							// série à son plancher.
							$h .= '<wbr>'.\series\CultivationUi::getPerennialSeason($eCultivation['series']);
						$h .= '</span>';
						$h .= $this->getPlaces($eCultivation);
					$h .= '</div>';
					// Sous 576, la période de routine s'efface pour laisser la carte au nom et aux planches ; « hors
					// période prévue » reste, c'est le signal d'une exception et non une information répétée.
					$expectedHidden = ($eCultivation['expectedFirst'] === NULL) ? '' : ' hide-xs-down';
					$h .= '<div class="util-annotation'.$expectedHidden.' hide-md-up">'.$this->getExpected($eCultivation).'</div>';
				$h .= '</div>';
			$h .= '</div>';

			if($sales) {
				$h .= $this->getSalesCells();
			}

			// Une semaine à venir pose ses sept plis comme une autre, sans en ouvrir aucun : chaque jour y marque
			// les récoltes qu'on y a prévues (retour 410).
			foreach($days as $date => $day) {
				$h .= '<div class="'.$this->getDayClass($date, $day).'">';
					$h .= '<div class="util-bellows-band">'.$this->getMark(($eCultivation['harvestedParts'] === []) ? ($eCultivation['harvestedDays'][$date] ?? []) : []).$this->getPlanned($eCultivation['plannedDays'][$date] ?? 0).'</div>';
					$h .= '<div class="util-bellows-panel">'.$this->getDay($eStructure, $week, $eCultivation, $date, $day, $canHarvest, $quick).'</div>';
				$h .= '</div>';
			}

			// Une culture ventilée ne porte ni le total de sa semaine ni celui de la précédente, que chaque part dit
			// pour elle : sa ligne ne sert qu'à saisir (« La série contient uniquement le formulaire de saisie »).
			$h .= '<div class="text-end planning-harvest-total hide-xs-down">'.(($eCultivation['harvestedParts'] === []) ? $this->getHarvested($eCultivation['harvestedWeek'], $eCultivation['harvestColor']) : '').'</div>';
			$h .= '<div class="text-end planning-harvest-previous hide-lg-down">'.(($eCultivation['harvestedParts'] === []) ? $this->getQuantities($eCultivation['harvestedWeekPrevious']) : '').'</div>';
			$h .= '<div class="planning-harvest-expected hide-sm-down">'.$this->getFrieze($eCultivation).'</div>';

			foreach($eCultivation['harvestedParts'] as $part) {
				$h .= $this->getPart($eStructure, $week, $days, $eCultivation, $part, $canHarvest, $quick, $canSelect, $sales);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La case d'une série. Sans part, elle désigne la culture entière : « Récolter » écrit dans son intervention
	 * de récolte de la semaine, ou en crée une (`HarvestWeekLib::getTasksForBatch()`). Avec des parts, ce sont
	 * elles qui se récoltent (FD, retour 405) : la case de la série ne poste rien, elle coche ou décoche celles
	 * de ses variétés et calibres et dit si tous, une partie ou aucun le sont (`PlanningHarvestSelection.syncSeries()`).
	 * « Non spécifiée » se coche à part : avec une variété de la même série, « Récolter » la refuse
	 * (`TaskBatchLib::validateHarvestsCouples()`), et la case de la série doit mener à un envoi possible. Une série
	 * dont aucune variété ni aucun calibre ne se saisit n'a donc pas de case.
	 */
	protected function getCheckbox(\series\Cultivation $eCultivation): string {

		if($eCultivation['harvestedParts'] === []) {
			return $this->getBatchCheckbox($eCultivation, $eCultivation['batch']);
		}

		if(array_any($eCultivation['harvestedParts'], fn($part) => $part['writable'] and ($part['eVariety']->notEmpty() or $part['eSize']->notEmpty())) === FALSE) {
			return '';
		}

		$form = new \util\FormUi();

		$h = '<label class="planning-harvest-select">';
			$h .= $form->inputCheckbox(NULL, attributes: [
				'data-planning-harvest-series' => $eCultivation['id'],
				'onrender' => 'PlanningHarvestSelection.apply()',
			]);
		$h .= '</label>';

		return $h;

	}

	/**
	 * La case d'une ligne que « Récolter » écrit, une série sans part ou l'une des parts d'une série, qui porte
	 * sa culture et son couple (`HarvestWeekLib::getBatchValue()`). `PlanningHarvestSelection` retient les lignes
	 * cochées quand une réponse redessine leur série. La part « Non spécifiée » d'une série ventilée se marque
	 * (`$unspecified`) : ni la case de la série ni la vignette de l'espèce ne la cochent.
	 */
	protected function getBatchCheckbox(\series\Cultivation $eCultivation, string $value, bool $unspecified = FALSE): string {

		$form = new \util\FormUi();

		$attributes = [
			'data-plant' => $eCultivation['plant']['id'],
			'data-cultivation' => $eCultivation['id'],
			'onrender' => 'PlanningHarvestSelection.restore(this)',
		];

		if($unspecified) {
			$attributes['data-unspecified'] = '1';
		}

		$h = '<label class="planning-harvest-select">';
			$h .= $form->inputCheckbox('batch[]', $value, $attributes);
		$h .= '</label>';

		return $h;

	}

	/**
	 * Une part : le couple, ses jours et sa semaine, en sous-ligne qui reprend les colonnes du tableau.
	 * Elle se manie comme une ligne, sur son couple : la toucher ouvre l'ajout du jour proposé, une
	 * quantité se remplace, un jour vide ajoute, ou porte le champ en saisie rapide. Une part qui ne se
	 * saisit plus (`writable`) se lit, sans case de « Récolter » (`$canSelect`).
	 */
	protected function getPart(\structure\Structure $eStructure, string $week, array $days, \series\Cultivation $eCultivation, array $part, bool $canHarvest, bool $quick, bool $canSelect, bool $sales): string {

		$canHarvestPart = ($canHarvest and $part['writable']);
		$proposedDate = array_find_key($days, fn($day) => $day['proposed']);

		if($canHarvestPart and $quick === FALSE and $proposedDate !== NULL) {
			$open = ' tabindex="0" data-planning-harvest-open="'.encode(self::urlOpen($eStructure, $week, $eCultivation, $proposedDate, 'add', part: $part)).'"';
		} else {
			$open = '';
		}

		$h = '<div class="planning-harvest-row-sub" id="planning-harvest-'.$eCultivation['id'].'-'.$part['key'].'"'.$open.'>';

			$h .= '<div class="planning-harvest-crop">';
				if($canSelect and $part['writable']) {
					$h .= $this->getBatchCheckbox($eCultivation, $part['batch'], unspecified: ($part['eVariety']->empty() and $part['eSize']->empty()));
				}
				$h .= self::getCoupleName($part['eVariety'], $part['eSize']);
			$h .= '</div>';

			if($sales) {
				$h .= $this->getSalesCells();
			}

			foreach($days as $date => $day) {
				$h .= '<div class="'.$this->getDayClass($date, $day).'">';
					$h .= '<div class="util-bellows-band">'.$this->getMark($part['days'][$date] ?? []).'</div>';
					$h .= '<div class="util-bellows-panel">'.$this->getPartDay($eStructure, $week, $eCultivation, $part, $date, $day, $canHarvestPart, $quick).'</div>';
				$h .= '</div>';
			}

			$h .= '<div class="text-end planning-harvest-total hide-xs-down">'.$this->getHarvested($part['week'], $eCultivation['harvestColor']).'</div>';
			$h .= '<div class="text-end planning-harvest-previous hide-lg-down">'.$this->getQuantities($part['weekPrevious']).'</div>';
			$h .= '<div class="planning-harvest-expected hide-sm-down"></div>';

		$h .= '</div>';

		return $h;

	}

	protected function getPartDay(\structure\Structure $eStructure, string $week, \series\Cultivation $eCultivation, array $part, string $date, array $day, bool $canHarvest, bool $quick): string {

		$quantities = $part['days'][$date] ?? [];

		if($canHarvest === FALSE or $day['future']) {
			return $this->getQuantities($quantities);
		}

		$time = $this->getDayTime($part['cUserTimeDays'][$date] ?? new \Collection(), $part['timeDays'][$date] ?? 0.0);

		$harvested = $this->getDayHarvested($quantities, $time, self::urlOpen($eStructure, $week, $eCultivation, $date, 'replace', part: $part));

		if($harvested !== '') {
			return $this->getQuantitiesWithAdd($harvested, self::urlOpen($eStructure, $week, $eCultivation, $date, 'add', part: $part), $date);
		}

		if($quick) {
			return $this->getCell($eStructure, $eCultivation, $date, $part);
		}

		return $this->getHarvestButton(self::urlOpen($eStructure, $week, $eCultivation, $date, 'add', part: $part));

	}

	/**
	 * Le nom d'une part, dans les deux styles de la variété et du calibre des interventions
	 * (`\series\TaskUi`). Sans l'un ni l'autre, la part est « Non spécifiée ».
	 */
	public static function getCoupleName(\plant\Variety $eVariety, \plant\Size $eSize): string {

		if($eVariety->empty() and $eSize->empty()) {
			return '<i>'.s("Non spécifiée").'</i>';
		}

		$names = [];

		if($eVariety->notEmpty()) {
			$names[] = '<span class="task-variety-name">'.encode($eVariety['name']).'</span>';
		}

		if($eSize->notEmpty()) {
			$names[] = '<span class="task-size-name">'.encode($eSize['name']).'</span>';
		}

		return implode(' ', $names);

	}

	/**
	 * La marque d'un jour fermé qui porte une récolte. Le panneau d'un jour ouvert montre les quantités,
	 * dans toutes les unités : la feuille du socle y efface la marque. Les quantités suivent la marque :
	 * quand la grille a la place d'élargir les bandes (retour 102), `planning.css` les montre à la place du
	 * point. Sans cette place, elles se lisent en ouvrant le jour, aucun survol n'existant au doigt.
	 */
	protected function getMark(array $quantities): string {

		if($quantities === []) {
			return '';
		}

		$h = '<div class="planning-harvest-band-content">';
			$h .= '<span class="util-bellows-mark"></span>';
			$h .= '<div class="text-center planning-harvest-band-value">'.$this->getQuantities($quantities).'</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Un point par récolte prévue sur un jour à venir (`plannedDays`), dans la bande de ce jour : la marque d'un
	 * jour récolté, sur la hachure du jour qui n'est pas arrivé, sans quantité à montrer à sa place.
	 */
	protected function getPlanned(int $planned): string {
		return str_repeat('<span class="util-bellows-mark"></span>', $planned);
	}

	/**
	 * Le contenu d'un jour, dans son panneau. Sans droit de saisir, ou un jour à venir, il ne porte que les
	 * quantités. Sinon, une quantité se remplace et garde un « + » qui ajoute, une case vide ajoute par le
	 * même « + », aujourd'hui comme un jour passé ouvert, et la saisie rapide pose sa case (`getCell()`). Dans les
	 * deux cas, le temps de la récolte du jour se lit sous la quantité (`getDayTime()`). Une culture qui a des
	 * parts ne dit rien de ses jours : chaque part dit sa quantité et son temps.
	 */
	protected function getDay(\structure\Structure $eStructure, string $week, \series\Cultivation $eCultivation, string $date, array $day, bool $canHarvest, bool $quick): string {

		// La somme des parts se lisait comme une récolte de la série en plus des leurs (21/10, « n'affiche
		// pas la somme c'est confus ») : la case ne garde que de quoi écrire une variété ou un calibre qui n'a pas
		// encore de ligne, le champ sans couple en saisie rapide (HN-B, scène 3), le « + » de la culture sinon.
		if($eCultivation['harvestedParts'] !== []) {

			if($canHarvest === FALSE or $day['future']) {
				return '';
			}

			if($quick) {
				return $this->getCell($eStructure, $eCultivation, $date);
			}

			return $this->getHarvestButton(self::urlOpen($eStructure, $week, $eCultivation, $date, 'add'));

		}

		$quantities = $eCultivation['harvestedDays'][$date] ?? [];

		if($canHarvest === FALSE or $day['future']) {
			return $this->getQuantities($quantities);
		}

		$time = $this->getDayTime($eCultivation['cUserTimeDays'][$date] ?? new \Collection(), $eCultivation['harvestTimeDays'][$date] ?? 0.0);

		// Un jour récolté dans une autre unité que celle de la semaine remplace et ajoute dans la sienne
		// (`HarvestWeekLib::prepareTask()`).
		if($quantities !== []) {
			$eTask = $eCultivation['harvestTaskDays'][$date];
			$urlReplace = self::urlOpen($eStructure, $week, $eCultivation, $date, 'replace', eTask: $eTask);
			$urlAdd = self::urlOpen($eStructure, $week, $eCultivation, $date, 'add', eTask: $eTask);
		} else {
			$urlReplace = self::urlOpen($eStructure, $week, $eCultivation, $date, 'replace');
			$urlAdd = self::urlOpen($eStructure, $week, $eCultivation, $date, 'add');
		}

		$harvested = $this->getDayHarvested($quantities, $time, $urlReplace);

		if($harvested !== '') {
			return $this->getQuantitiesWithAdd($harvested, $urlAdd, $date);
		}

		if($quick) {
			return $this->getCell($eStructure, $eCultivation, $date);
		}

		return $this->getHarvestButton($urlAdd);

	}

	/**
	 * Le « + » qui ajoute une récolte, seul sur une case vide, celle d'une culture comme celle d'une part
	 * (retour 3 du 04/10), ou à côté d'une quantité : le même partout (R-Retours-0810, 217).
	 */
	protected function getHarvestButton(string $urlAdd): string {

		return '<a class="btn btn-only-icon btn-sm btn-outline-primary planning-harvest-button" data-planning-harvest-open="'.encode($urlAdd).'">'.\Asset::icon('plus-lg').'</a>';

	}

	/**
	 * Une case qui porte déjà une récolte garde l'ajout à côté de sa quantité (retour 116), en saisie rapide
	 * comme sans (V52) : la quantité se remplace, le « + » ajoute une pesée. La quantité, avec le temps écrit
	 * dessous, porte son jour : la réponse d'une saisie la nomme pour l'éclairer (`harvestWeek.v.php`).
	 */
	protected function getQuantitiesWithAdd(string $quantities, string $urlAdd, string $date): string {

		$h = '<div class="planning-harvest-day-total">';
			$h .= '<div class="planning-harvest-day-quantities" data-date="'.$date.'">';
				$h .= $quantities;
			$h .= '</div>';
			$h .= $this->getHarvestButton($urlAdd);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qu'un jour a récolté, la quantité en gras, une unité par ligne, puis le temps que la récolte a pris
	 * (`getDayTime()`, retour 303), sur une ligne à lui dans le pli du jour, le tout rouvrant le tiroir en remplacement, où chaque personne corrige le
	 * sien. Sans quantité, le temps se lit seul : posé avant la pesée, il ne se lirait nulle part ailleurs sur
	 * l'écran.
	 */
	protected function getDayHarvested(array $quantities, string $time, string $urlReplace): string {

		if($quantities === [] and $time === '') {
			return '';
		}

		$h = '<a class="util-quick" data-planning-harvest-open="'.encode($urlReplace).'">';

			if($quantities !== []) {

				$values = [];

				foreach($quantities as $unit => $quantity) {
					$values[] = \product\UnitUi::getValue($quantity, $unit, short: TRUE);
				}

				$h .= '<b>'.implode('<br/>', $values).'</b>';

			}

			$h .= $time;

		$h .= '</a>';

		return $h;

	}

	/**
	 * La ligne du temps d'un jour, la même avec et sans saisie rapide (retours 302 et 303) : les personnes qui ont
	 * passé du temps sur la récolte du jour, en vignettes comme sur une carte de la Semaine
	 * (`TaskCardUi::getTop()`), puis le temps de toutes, une seule somme (`HarvestWeekLib::prepareTime()`). Une
	 * personne partie de la structure compte dans la somme et n'a pas de vignette : la ligne peut ne porter que
	 * la durée.
	 */
	protected function getDayTime(\Collection $cUser, float $time): string {

		if($time <= 0.0) {
			return '';
		}

		$h = '<span class="planning-harvest-day-time">';

			if($cUser->notEmpty()) {

				$h .= '<span class="planning-task-users">';

					foreach($cUser as $eUser) {
						$h .= \user\UserUi::getVignette($eUser, '1.25rem');
					}

				$h .= '</span>';

			}

			$h .= DurationUi::formatDuration($time);

		$h .= '</span>';

		return $h;

	}

	/**
	 * La case d'un jour que la réponse d'une saisie éclaire (`HarvestWeekLib::getWritten()`), dans le panneau
	 * de son pli : sa quantité et son temps (`getQuantitiesWithAdd()`), ou en saisie rapide le champ d'une
	 * case restée vide (`getCell()`), une ligne ne rendant que l'une des deux.
	 * Les parts sont rendues dans la ligne de leur culture : le chemin part des enfants directs de la ligne
	 * visée, pour ne pas éclairer la même journée de chaque part avec celle de la culture.
	 */
	public static function getWrittenSelectors(array $written): array {

		$line = '#planning-harvest-'.$written['cultivation'].(($written['part'] === NULL) ? '' : '-'.$written['part']);

		return [
			$line.' > * > .util-bellows-panel .planning-harvest-day-quantities[data-date="'.$written['date'].'"]',
			$line.' > * > .util-bellows-panel > .planning-harvest-cell[data-date="'.$written['date'].'"]',
		];

	}

	/**
	 * La ligne nommée d'un jour dans la grille (`planning.css`), où le tiroir pose sa pointe : son rang change
	 * avec les colonnes que la largeur d'écran retire devant les jours.
	 */
	public static function getDayColumn(array $day): string {
		return 'day-'.$day['day'];
	}

	/**
	 * La case d'un jour en saisie rapide qui n'a encore rien récolté (HN-B), un jour récolté rendant sa quantité
	 * et son « + » comme sans saisie rapide (`getQuantitiesWithAdd()`) : le champ de saisie, qui ne lit que la
	 * récolte de cette culture, ce jour-là et, pour une part, sur son couple, même vide : « Non spécifiée »
	 * refuse une variété ou un calibre dits (`\entry\EntryUi::getHarvestCell()`). Le champ porte tout ce
	 * contexte : la case n'a ni nombre, ni unité, ni horloge, que la phrase dit.
	 *
	 * Chaque pli de la semaine porte ses champs, ouvert ou non : un pli s'ouvre dans le navigateur, sans requête
	 * (`MainBellows`), et le champ du jour qu'on vient d'ouvrir doit y être aussitôt.
	 */
	protected function getCell(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, array $part = []): string {

		$h = '<div class="planning-harvest-cell" data-date="'.$date.'">';

			if($part === []) {
				$h .= new \entry\EntryUi()->getHarvestCell($eStructure, $eCultivation, $date);
			} else {
				$h .= new \entry\EntryUi()->getHarvestCell($eStructure, $eCultivation, $date, $part['eVariety'], $part['eSize'], part: TRUE);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * `util-bellows-titled` ne se pose qu'en tête : `MainBellows.title()` ne mesure que les cellules qui
	 * portent un nom, et une ligne n'en porte pas. Aujourd'hui prend la teinte de la grille des Interventions
	 * (retour 388), par la même règle.
	 */
	protected function getDayClass(string $date, array $day, bool $head = FALSE): string {

		$class = 'planning-harvest-day util-bellows-item'.TaskWeekGridUi::getTodayClass($date);

		if($day['future']) {
			$class .= ' util-bellows-inactive';
		}

		if($day['open']) {
			$class .= $head ? ' util-bellows-open util-bellows-titled' : ' util-bellows-open';
		}

		return $class;

	}

	/**
	 * L'assolement de la ligne, à la suite de son nom (retour 243), avec les mots des cartes de la vue
	 * Interventions (`TaskCardUi::getPlace()`).
	 */
	protected function getPlaces(\series\Cultivation $eCultivation): string {

		$eSeries = $eCultivation['series'];

		$places = new TaskCardUi()->getPlaces($eSeries['use'], $eSeries['cccPlace']);

		if($places === '') {
			return '';
		}

		return '<div class="planning-task-place">'.$places.'</div>';

	}

	/**
	 * L'intertitre d'une espèce, au-dessus de ses séries : sa vignette et son nom, que les séries ne répètent
	 * pas (R-Retours-0810, 225).
	 *
	 * L'intertitre n'a rien à saisir, mais il porte les plis des jours comme une ligne, vides, pour que chaque
	 * colonne de jour coure sans coupure du haut au bas de la grille (retours 238 et 239) : son nom s'arrête à
	 * la colonne de la série. Ses plis ne dépendent pas d'un jour ouvert, qu'un lecteur ouvre aussi par sa tête
	 * sur une semaine passée qui n'en ouvre aucun (`proposed`, `HarvestWeekLib::getDays()`). Une semaine à
	 * venir ne propose aucun jour : rien ne s'y lit d'un jour sur l'autre au niveau de l'espèce, et son
	 * intertitre court sur toute la ligne, au-dessus des plis de ses séries, sauf quand il porte les ventes et
	 * le stock de l'espèce (`$sales`, V47) : il pose alors toutes les cases d'une ligne, pour que ces deux
	 * colonnes tombent sous leur en-tête.
	 */
	public function getSection(\plant\Plant $ePlant, array $days, bool $canSelect = FALSE, bool $sales = FALSE, array $salesPlant = ['sold' => [], 'stock' => []]): string {

		$proposedDate = array_find_key($days, fn($day) => $day['proposed']);
		$full = ($proposedDate === NULL and $sales === FALSE);

		$h = '<div class="util-table-row planning-harvest-section" id="planning-harvest-plant-'.$ePlant['id'].'">';
			$h .= '<div class="'.($full ? 'util-table-full ' : '').'planning-harvest-crop">';
				// Quand les séries se cochent, la vignette coche ou décoche toutes celles de l'espèce (DG-2).
				if($canSelect) {
					$h .= '<a class="planning-harvest-select-plant" data-planning-harvest-select-plant="'.$ePlant['id'].'">'.\plant\PlantUi::getVignette($ePlant, '2rem').'</a>';
				} else {
					$h .= \plant\PlantUi::getVignette($ePlant, '2rem');
				}
				$h .= '<span class="planning-harvest-section-name">'.encode($ePlant['name']).'</span>';
			$h .= '</div>';

			if($sales) {
				$h .= $this->getSalesCells($salesPlant['sold'], $salesPlant['stock']);
			}

			if($full === FALSE) {

				foreach($days as $date => $day) {
					$h .= '<div class="'.$this->getDayClass($date, $day).'">';
						$h .= '<div class="util-bellows-band"></div>';
						$h .= '<div class="util-bellows-panel"></div>';
					$h .= '</div>';
				}

				$h .= '<div class="planning-harvest-total hide-xs-down"></div>';
				$h .= '<div class="planning-harvest-previous hide-lg-down"></div>';
				$h .= '<div class="planning-harvest-expected hide-sm-down"></div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'en-tête des ventes à venir, dont la fenêtre se choisit sur place (V51) : sa fin, soulignée et suivie
	 * du chevron d'un menu (V53), ouvre le menu des fenêtres de la roue du planning
	 * (`TaskWeekUi::getSalesDaysLabel()`). Le menu suit l'en-tête plutôt que son lien, que `util-annotation`
	 * habille : les entrées n'en prennent pas le dessin.
	 */
	public function getSalesHead(\structure\Structure $eStructure, string $week, \structure\Member $eMember): string {

		$h = '<div class="planning-harvest-sales text-end">';
			$h .= s("Ventes à&nbsp;venir");
			$h .= '<div class="util-annotation">';
				$h .= '<a class="dropdown-toggle" data-dropdown="bottom-end" data-dropdown-id="planning-harvest-sales-days">'.$this->getSalesWindow($eMember['viewPlanningSalesDays'], $eMember['salesDate']).'</a>';
			$h .= '</div>';
			$h .= '<div class="dropdown-list" data-dropdown-id="planning-harvest-sales-days-list">';
				foreach(PlanningSetting::SALES_DAYS as $salesDays) {
					$h .= '<a data-ajax="/planning/harvestWeek:doSalesDays" post-id="'.$eStructure['id'].'" post-week="'.$week.'" post-view-planning-sales-days="'.$salesDays.'" class="dropdown-item'.($salesDays === $eMember['viewPlanningSalesDays'] ? ' selected' : '').'">'.TaskWeekUi::getSalesDaysLabel($salesDays).'</a>';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La fin de la fenêtre des ventes à venir, sous l'en-tête de leur colonne : « jusqu'à dimanche 11 », ou
	 * « aujourd'hui » pour la seule journée en cours. Le stock, lui, n'a pas de fenêtre.
	 */
	protected function getSalesWindow(int $salesDays, string $date): string {

		return match($salesDays) {
			0 => s("aujourd'hui"),
			// Le lien ne se coupe qu'après « jusqu'à », et seulement sous 992 (`planning.css`).
			default => s("<until>jusqu'à </until>{day}", ['until' => '<span class="planning-harvest-sales-until">', 'day' => mb_strtolower(TimeDayUi::getPanelDay($date))])
		};

	}

	/**
	 * Les ventes à venir et le stock d'une espèce, une valeur par unité. Les lignes des séries posent les deux
	 * cases vides (`getRow()`, `getPart()`). Sous 768, la colonne du stock est retirée et l'intertitre
	 * passe les ventes sous son nom, où elles portent le libellé de leur en-tête.
	 */
	protected function getSalesCells(array $sold = [], array $stock = []): string {
		return $this->getSalesCell($sold).'<div class="planning-harvest-stock text-end hide-sm-down">'.$this->getSalesValue($stock).'</div>';
	}

	/**
	 * La case des ventes seule, que le choix de la fenêtre redessine sans le stock (`harvestWeek:doSalesDays`).
	 */
	public function getSalesCell(array $sold): string {

		$label = ($sold === []) ? '' : '<span class="util-annotation hide-md-up">'.s("Ventes à venir").'</span>';

		return '<div class="planning-harvest-sales text-end">'.$label.$this->getSalesValue($sold).'</div>';

	}

	protected function getSalesValue(array $entries): string {

		$h = '';

		foreach($entries as $entry) {
			$h .= '<div>'.\product\UnitUi::getValue($entry['value'], $entry['unit'], short: TRUE).'</div>';
		}

		return $h;

	}

	/**
	 * Le nom d'une ligne : la série, puis sa saison, son
	 * lieu, la semaine de départ et les variétés quand `HarvestWeekLib::prepareNames()` a jugé qu'il
	 * fallait les dire. L'autocomplétion du panneau le reprend pour ses résultats. La ligne de la grille dit
	 * ses lieux et sa semaine de départ à côté du nom (`getRow()`), d'où `$places` et `$startWeek`.
	 */
	public function getName(\series\Cultivation $eCultivation, bool $places = TRUE, bool $startWeek = TRUE): string {

		$eSeries = $eCultivation['series'];

		$parts = [encode($eSeries['name'])];

		if($eCultivation['nameWithSeason']) {
			$parts[] = s("Saison {value}", $eSeries['season']);
		}

		$placesDisplayed = $places ? new TaskCardUi()->getPlaces($eSeries['use'], $eSeries['cccPlace']) : '';

		if($placesDisplayed !== '') {
			$parts[] = $placesDisplayed;
		}

		if($startWeek and $eCultivation['nameWithStartWeek']) {
			$parts[] = s("s{value}", week_number($eCultivation->getStartWeek($eCultivation['season'])));
		}

		if($eCultivation['nameWithVarieties']) {
			$parts[] = encode(implode(', ', $eCultivation['cSlice']->makeArray(fn($eSlice) => $eSlice['variety']['name'])));
		}

		return implode(' · ', $parts);

	}

	protected function getFrieze(\series\Cultivation $eCultivation): string {

		if($eCultivation['expectedFrieze'] === []) {
			return '';
		}

		$frieze = $eCultivation['expectedFrieze'];

		$h = '<div class="planning-harvest-frieze planning-harvest-frieze-'.$frieze['state'].'">';
			if($frieze['width'] > 0) {
				$h .= '<span class="planning-harvest-frieze-range'.($frieze['cutStart'] ? ' planning-harvest-frieze-cut-start' : '').($frieze['cutEnd'] ? ' planning-harvest-frieze-cut-end' : '').'" style="left: '.$frieze['left'].'%; width: '.$frieze['width'].'%"></span>';
			}
			$h .= '<span class="planning-harvest-frieze-mark" style="left: '.$frieze['mark'].'%"></span>';
		$h .= '</div>';
		$h .= '<div class="planning-harvest-frieze-label">'.$this->getExpectedWeeks($eCultivation).'</div>';

		return $h;

	}

	protected function getExpectedWeeks(\series\Cultivation $eCultivation): string {

		if($eCultivation['expectedFirst'] === $eCultivation['expectedLast']) {
			return s("s{value}", week_number($eCultivation['expectedFirst']));
		}

		return s("s{first} à s{last}", [
			'first' => week_number($eCultivation['expectedFirst']),
			'last' => week_number($eCultivation['expectedLast'])
		]);

	}

	protected function getExpected(\series\Cultivation $eCultivation): string {

		if($eCultivation['expectedFirst'] === NULL) {
			return s("hors période prévue");
		}

		if($eCultivation['expectedFirst'] === $eCultivation['expectedLast']) {
			return s("prévue s{value}", week_number($eCultivation['expectedFirst']));
		}

		return s("prévue s{first} à s{last}", [
			'first' => week_number($eCultivation['expectedFirst']),
			'last' => week_number($eCultivation['expectedLast'])
		]);

	}

	/**
	 * Les quantités d'une semaine, dans la forme de la récolte, à la couleur de l'action Récolte.
	 */
	protected function getHarvested(array $quantities, string $color): string {

		$h = '';

		foreach($quantities as $unit => $quantity) {
			$h .= '<div><span class="annotation" style="color: '.$color.'">'.\product\UnitUi::getValue($quantity, $unit, short: TRUE).'</span></div>';
		}

		return $h;

	}

	/**
	 * Une quantité par unité, chacune sur sa ligne : deux unités d'une même période ne s'additionnent pas.
	 */
	public function getQuantities(array $quantities): string {

		$h = '';

		foreach($quantities as $unit => $quantity) {
			$h .= '<div>'.\product\UnitUi::getValue($quantity, $unit, short: TRUE).'</div>';
		}

		return $h;

	}

}
?>
