<?php
namespace toolbox;

/**
 * L'écran Carnet de traitement de la Boîte à outils (planche pf-l4-v3, contrat du lot L4').
 *
 * Deux états : aucun produit de traitement, et l'écran accueille ; sinon le carnet d'une année
 * civile, mois par mois, une année par onglet. Au-dessus, une fois, la carte qui propose de faire
 * d'un matériel noté sur des traitements le produit qu'il était.
 *
 * Le haut de l'écran ne porte que la roue et « Nouveau traitement » (règle de la Boîte à outils,
 * retour du 27/09) : la recherche et les téléchargements se tiennent au bout de la barre
 * des onglets, et valent pour l'année ouverte.
 */
class TreatmentUi {

	public function __construct() {

		\Asset::css('toolbox', 'toolbox.css');
		\Asset::css('toolbox', 'treatment.css');
		\Asset::js('toolbox', 'treatment.js');

	}

	public static function url(\structure\Structure $eStructure, array $state = []): string {
		return '/ferme/'.$eStructure['id'].'/outils/traitement'.($state === [] ? '' : '?'.http_build_query($state));
	}

	/**
	 * L'état du carnet (année, recherche), celui que ses liens gardent et que le panneau « + Produit »
	 * réémet pour que le carnet revienne tel qu'il était.
	 */
	public static function getState(int $year, \Search $search): array {

		return array_filter([
			'year' => $year,
			'plant' => $search->get('plant')->empty() ? NULL : $search->get('plant')['id'],
			'tool' => $search->get('tool')->empty() ? NULL : $search->get('tool')['id'],
			'without' => $search->get('without', FALSE) ? 1 : NULL,
		], fn($value) => $value !== NULL);

	}

	public static function urlUpdateProduct(\structure\Structure $eStructure, array $state, \series\Task $eTask): string {
		return '/toolbox/treatment:updateProduct?'.http_build_query(['id' => $eStructure['id']] + $state + ['ids' => [$eTask['id']]]);
	}

	public static function urlCreateProduct(\structure\Structure $eStructure): string {
		return '/work/tool:create?structure='.$eStructure['id'].'&routineName=phyto';
	}

	/**
	 * L'en-tête d'un outil ne porte que sa roue, qui range l'ajout d'un produit et la liste où ses
	 * doses se modifient : l'ajout d'un traitement vit dans la page, sur la ligne sous les onglets
	 * (`getYears()`).
	 */
	public function getHeader(\structure\Structure $eStructure, \Collection $cFavorite): string {

		// Un produit est un matériel, que seul `work\Tool::canWrite()` crée.
		if($eStructure->canManage()) {
			$menu = '<a href="'.self::urlCreateProduct($eStructure).'" class="dropdown-item">'.\Asset::icon('capsule').' '.s("Ajouter un produit de traitement").'</a>';
			$menu .= '<a href="'.\work\ToolUi::urlManage($eStructure, 'phyto').'" class="dropdown-item">'.\Asset::icon('pencil-fill').' '.s("Modifier les produits de traitement").'</a>';
		} else {
			$menu = '';
		}

		return new ToolUi()->getHeader($eStructure, Favorite::TREATMENT, $cFavorite, menu: $menu);

	}

	/**
	 * « Nouveau traitement » ouvre une intervention faite, notée aujourd'hui : dans une série d'une
	 * saison proche (`$seasons`, `SeriesLib::getSeasonsAround()`) ou hors série, avec le même menu que
	 * le planning ; à son enregistrement, le carnet se recharge derrière le panneau de l'intervention.
	 */
	protected function getCreate(\structure\Structure $eStructure, \work\Action $eAction, array $seasons): string {

		$create = [
			'structure' => $eStructure['id'],
			'action' => $eAction['id'],
			'status' => \series\Task::DONE,
			'doneWeek' => \Clock::week(),
			'doneDate' => \Clock::date(),
		];

		$label = \Asset::icon('plus-circle').' '.s("Saisir un traitement");
		$urlScratch = '/series/task:createFromScratch?'.http_build_query(['category' => $eAction['categories'][0]] + $create);

		if($seasons === []) {
			return '<a href="'.$urlScratch.'" class="btn btn-primary">'.$label.'</a>';
		}

		$h = '<a data-dropdown="bottom-start" class="btn btn-primary dropdown-toggle">'.$label.'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.s("Dans une série").'</div>';
			foreach($seasons as $season) {
				$h .= '<a href="/series/task:createFromSeries?'.http_build_query(['season' => $season] + $create).'" class="dropdown-item">'.s("Saison {value}", $season).'</a>';
			}
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a href="'.$urlScratch.'" class="dropdown-item">'.s("Hors série").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'accueil d'une structure sans produit de traitement (planche pf-l4-v2, `accueil`). Son bouton
	 * ouvre le carnet de l'année, vide : les produits s'ajoutent depuis la roue ou depuis un traitement.
	 */
	public function getStart(\structure\Structure $eStructure): string {

		$promises = [
			['calendar-check', s("Chaque traitement, jour par jour"), s("la culture, le produit, la surface")],
			['calculator', s("La quantité à préparer"), s("planche par planche, depuis la dose")],
		];

		$content = '<p>'.s("Chaque traitement enregistré au planning entre dans le registre, avec son produit et sa dose.").'</p>';
		$content .= new ToolUi()->getPromises($promises);

		return new \main\DesignUi()->getStart(
			\Asset::icon('capsule'),
			s("Le registre phytosanitaire"),
			$content,
			image: \Asset::image('main', 'friends/coccinelle-rouge.svg'),
			buttons: '<a href="'.self::url($eStructure, ['year' => \Clock::year()]).'" class="btn btn-primary btn-xl">'.s("Ouvrir le registre").'</a>'
		);

	}

	/**
	 * Un onglet par année qui porte des traitements, la plus récente à gauche, sur toute la largeur.
	 * Dessous, une ligne à elle : l'ajout d'un traitement à gauche, la loupe et les téléchargements à
	 * droite, qui valent pour l'année ouverte. Changer d'année garde la recherche.
	 */
	public function getYears(\structure\Structure $eStructure, \work\Action $eAction, array $seasons, array $years, int $year, \Search $search): string {

		$state = self::getState($year, $search);

		$h = '<div class="tabs tabs-h tabs-h-fold">';
			$h .= '<div class="tabs-item">';
				foreach($years as $yearListed) {
					$h .= '<a href="'.self::url($eStructure, ['year' => $yearListed] + $state).'" class="tab-item '.($yearListed === $year ? 'selected' : '').'">'.$yearListed.'</a>';
				}
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-action treatment-tools">';
			$h .= '<div>'.(($eAction->empty() or $eStructure->canPlanning() === FALSE) ? '' : $this->getCreate($eStructure, $eAction, $seasons)).'</div>';
			$h .= '<div class="treatment-tools-aside">';
				$h .= '<a '.attr('onclick', 'Lime.Search.toggle("#treatment-search")').' class="btn btn-only-icon btn-outline-primary" title="'.s("Chercher").'">'.\Asset::icon('search').'</a>';
				$h .= $this->getDownload($eStructure, $state, $year);
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le registre en PDF et le carnet en CSV, du plus ancien au plus récent, les traitements notés à la
	 * semaine à la place de leur semaine. Les deux suivent l'année et la recherche.
	 */
	protected function getDownload(\structure\Structure $eStructure, array $state, int $year): string {

		$arguments = http_build_query(['id' => $eStructure['id']] + $state);

		$urlPdf = 'href="/toolbox/treatment:downloadPdf?'.$arguments.'" data-ajax-download="'.encode(TreatmentPdfUi::getFilename($year)).'" data-waiter="'.s("Création en cours").'" data-waiter-timeout="8"';

		// Un export de données demande de gérer la structure : sans ce droit, le PDF reste, seul.
		if($eStructure->canManage() === FALSE) {
			return '<a '.$urlPdf.' class="btn btn-outline-primary">'.\Asset::icon('file-pdf').' '.s("PDF").'</a>';
		}

		$h = '<a data-dropdown="bottom-end" class="btn btn-outline-primary dropdown-toggle">'.s("Télécharger").'</a>';
		$h .= '<div class="dropdown-list">';

			$h .= '<a '.$urlPdf.' class="dropdown-item">';
				$h .= \Asset::icon('file-pdf').' '.s("Format PDF");
			$h .= '</a>';

			$h .= '<a href="/toolbox/treatment:exportCsv?'.$arguments.'" class="dropdown-item" data-ajax-download data-waiter>';
				$h .= \Asset::icon('filetype-csv').' '.s("Format CSV");
			$h .= '</a>';

		$h .= '</div>';

		return $h;

	}

	public function getExportHeader(): array {

		return [
			'date',
			'week',
			'crop',
			'places',
			'area',
			'product',
			'amm',
			'dose',
			'dose_unit',
			'water',
			'quantity',
			'tools',
			'description',
		];

	}

	/**
	 * La recherche par espèce et par produit, repliée derrière la loupe tant qu'aucun filtre n'est posé,
	 * comme au planning.
	 */
	public function getSearch(\structure\Structure $eStructure, \work\Action $eAction, int $year, \Search $search): string {

		$form = new \util\FormUi();
		$url = self::url($eStructure);

		$h = '<div id="treatment-search" class="util-block-search '.($search->empty() ? 'hide' : '').'">';

			$h .= $form->openAjax($url, ['method' => 'get', 'class' => 'util-search']);

				$h .= $form->hidden('year', $year);

				$h .= '<fieldset>';
					$h .= '<legend>'.s("Espèce").'</legend>';
					$h .= $form->dynamicField(new \series\Cultivation(['structure' => $eStructure]), 'plant', function($d) use($search) {
						$d->autocompleteDefault = $search->get('plant');
						$d->attributes = [
							'data-autocomplete-select' => 'submit',
						];
					});
				$h .= '</fieldset>';

				if($eAction->notEmpty()) {
					$h .= '<fieldset>';
						$h .= '<legend>'.s("Produit").'</legend>';
						$eTask = new \series\Task(['structure' => $eStructure, 'action' => $eAction]);
						$h .= $form->dynamicField($eTask, 'products', function($d) use($search) {
							$d->name = 'tool';
							$d->multiple = FALSE;
							$d->placeholder = s("Nom commercial ou numéro d'AMM");
							$d->autocompleteDefault = $search->get('tool');
							// La recherche ne porte que sur les produits de la structure, qui sont les seuls à avoir des traitements.
							$d->autocompleteBody = fn(\util\FormUi $form, \series\Task $e) => [
								'action' => $e['action']['id'],
								'structure' => $e['structure']['id'],
							];
							$d->attributes = [
								'data-autocomplete-select' => 'submit',
							];
						});
					$h .= '</fieldset>';
				}

				if($search->get('without', FALSE)) {
					$h .= $form->hidden('without', 1);
				}

				$h .= '<div class="util-search-submit">';
					$h .= $form->submit(s("Chercher"));
					$h .= '<a href="'.self::url($eStructure, ['year' => $year]).'" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	/**
	 * La carte de reprise (arbitrage Q4 A de la planche pf-l4-v2, dessinée en v3 `b-reprise`) :
	 * « Oui » ouvre le matériel en produit de traitement, la recherche dans le catalogue déjà tapée à
	 * son nom ; « Non » retire la carte, qui ne revient plus pour lui.
	 */
	public function getReprise(\work\Tool $eTool): string {

		if($eTool->empty()) {
			return '';
		}

		$content = '<p>'.p("Vous l'avez noté comme matériel sur {value} traitement. Retrouvez-le dans le catalogue E-Phy, et ce traitement aura son produit et sa dose.", "Vous l'avez noté comme matériel sur {value} traitements. Retrouvez-le dans le catalogue E-Phy, et ces traitements auront leur produit et leur dose.", $eTool['tasks']).'</p>';
		$content .= '<div class="treatment-reprise-buttons">';
			$content .= '<a href="/work/tool:update?id='.$eTool['id'].'&routineName=phyto&origin=treatment" class="btn btn-primary">'.s("Oui, c'est un produit").'</a>';
			$content .= '<a data-ajax="/work/tool:doRefusePhyto" post-id="'.$eTool['id'].'" post-phyto-refused="1" class="btn btn-outline-primary">'.s("Non").'</a>';
		$content .= '</div>';

		return '<div id="treatment-reprise">'.\main\DesignUi::getAccentCard('todo', NULL, s("{value} est-il un produit de traitement ?", encode($eTool['name'])), $content).'</div>';

	}

	/**
	 * Le bloc du carnet : les chiffres et les traitements mois par mois, avec la barre qui agit sur les
	 * traitements cochés. C'est lui que l'enregistrement d'un produit remplace.
	 */
	public function getBook(\structure\Structure $eStructure, int $year, \Search $search, \Collection $cTask, array $tasksByMonth, \Collection $cTaskWithout): string {

		$state = self::getState($year, $search);

		$h = '<div id="treatment-book"'.($eStructure->canPlanning() ? ' data-batch="#batch-treatment"' : '').'>';

			$h .= $this->getFigures($eStructure, $state, $year, $cTask, $cTaskWithout);
			$h .= $this->getMonths($eStructure, $state, $year, $tasksByMonth);
			$h .= $this->getBatch($eStructure, $state);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les traitements cochés reçoivent le même produit et le même traitement, par le panneau de « + Produit »,
	 * ou se suppriment comme au planning, dont l'action recharge la page et garde donc l'onglet d'année.
	 */
	protected function getBatch(\structure\Structure $eStructure, array $state): string {

		if($eStructure->canPlanning() === FALSE) {
			return '';
		}

		$form = new \util\FormUi();

		$menu = $form->hidden('id', $eStructure);

		foreach($state as $name => $value) {
			$menu .= $form->hidden($name, $value);
		}

		$menu .= '<a data-ajax-submit="/toolbox/treatment:updateProduct" data-ajax-method="get" class="batch-item">';
			$menu .= \Asset::icon('capsule');
			$menu .= '<span>'.s("Choisir le produit").'</span>';
		$menu .= '</a>';

		$danger = '<a data-ajax-submit="/series/task:doDeleteCollection" data-confirm="'.s("Confirmer la suppression de ces interventions ?").'" class="batch-item batch-item-danger">';
			$danger .= \Asset::icon('trash');
			$danger .= '<span>'.s("Supprimer").'</span>';
		$danger .= '</a>';

		return \util\BatchUi::group('batch-treatment', $menu, $danger, title: s("Pour les traitements sélectionnés"));

	}

	/**
	 * Chaque chiffre mène à la liste qu'il compte : le total aux traitements de l'année, l'autre à ceux
	 * dont le produit reste à renseigner, le reste de la recherche gardé.
	 */
	public function getFigures(\structure\Structure $eStructure, array $state, int $year, \Collection $cTask, \Collection $cTaskWithout): string {

		$stateAll = array_diff_key($state, ['without' => TRUE]);

		$h = '<div class="util-figures util-figures-2">';
			$h .= '<a href="'.self::url($eStructure, $stateAll).'" class="util-figure"><div class="util-figure-value">'.$cTask->count().'</div><div class="util-figure-label">'.p("Traitement en {year}", "Traitements en {year}", $cTask->count(), ['year' => $year]).'</div></a>';
			if($cTaskWithout->notEmpty()) {
				$h .= '<a href="'.self::url($eStructure, $stateAll + ['without' => 1]).'" class="util-figure"><div class="util-figure-value">'.$cTaskWithout->count().'</div><div class="util-figure-label">'.p("Produit à renseigner", "Produits à renseigner", $cTaskWithout->count()).'</div></a>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Tous les traitements de l'année, du plus récent au plus ancien, un bloc par mois comme les mois du
	 * tableau de la banque. Les cases à cocher de tous les mois restent dans le même `#treatment-book`,
	 * que la barre de sélection lit en entier.
	 */
	public function getMonths(\structure\Structure $eStructure, array $state, int $year, array $tasksByMonth): string {

		if($tasksByMonth === []) {

			if(isset($state['without'])) {
				return '<div class="util-empty">'.s("Tous les traitements de {value} ont leur produit.", $year).'</div>';
			} else {
				return '<div class="util-empty">'.s("Aucun traitement noté en {value}.", $year).'</div>';
			}

		}

		$h = '<div class="treatment-decks">';

			foreach($tasksByMonth as $month => $cTask) {

				$h .= '<div class="util-deck util-deck-open">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.mb_ucfirst(\util\DateUi::textual($month.'-01', \util\DateUi::MONTH_YEAR)).'</div>';
							$h .= '<p>'.p("{value} traitement", "{value} traitements", $cTask->count()).'</p>';
						$h .= '</div>';
					$h .= '</div>';
					$h .= $this->getRows($eStructure, $state, $cTask);
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une ligne par traitement. La ligne porte des gestes (la case, le jour, le produit, la roue),
	 * donc elle ne mène nulle part elle-même (convention des util-deck-row du design system). Le
	 * produit vient avant la surface, pour que sa colonne s'aligne d'un bloc à l'autre. Sans le droit
	 * du planning, que demandent les actions de la barre, la ligne perd sa case (`treatment-row-read`).
	 */
	protected function getRows(\structure\Structure $eStructure, array $state, \Collection $cTask): string {

		$form = new \util\FormUi();
		$write = $eStructure->canPlanning();

		$h = '<div class="util-deck-fold">';

			foreach($cTask as $eTask) {

				// Sur une série qui n'est pas placée, le produit, la quantité et la surface ne sont qu'un ordre de grandeur.
				$muted = $eTask['areaPlanned'] ? 'color-muted' : '';

				$h .= '<div id="treatment-task-'.$eTask['id'].'" class="util-deck-row treatment-row'.($write ? '' : ' treatment-row-read').'">';
					if($write) {
						$h .= '<label class="batch-checkbox treatment-row-select">'.$form->inputCheckbox('batch[]', $eTask['id'], ['oninput' => 'ToolboxTreatment.changeSelection()']).'</label>';
					}
					$h .= '<div class="treatment-row-date">'.$this->getDate($eTask).'</div>';
					$h .= '<div class="treatment-row-culture">'.$this->getCulture($eTask).'</div>';
					$h .= '<div class="treatment-row-product '.$muted.'">'.$this->getProduct($eStructure, $state, $eTask).'</div>';
					$h .= '<div class="treatment-row-quantity text-end '.$muted.'">'.$this->getQuantity($eTask).'</div>';
					$h .= '<div class="treatment-row-area text-end '.$muted.'">'.$this->getArea($eTask).'</div>';
					$h .= '<div class="treatment-row-actions">'.$this->getActions($eTask).'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La surface de la série, ou sa surface prévue tant qu'elle n'est pas placée, atténuée comme la quantité qui en découle.
	 */
	protected function getArea(\series\Task $eTask): string {

		if($eTask['area'] === NULL) {
			return '';
		}

		if($eTask['areaPlanned']) {
			$h = '<div>'.s("{value} m²", \util\TextUi::number($eTask['area'])).'</div>';
			$h .= '<div class="util-annotation">'.s("prévus").'</div>';

			return $h;
		}

		return s("{value} m²", \util\TextUi::number($eTask['area']));

	}

	/**
	 * Le produit, et sous lui la dose et la bouillie qui donnent la quantité. Sans produit, le geste
	 * qui en choisit un.
	 */
	protected function getProduct(\structure\Structure $eStructure, array $state, \series\Task $eTask): string {

		if($eTask['eToolPhyto']->empty()) {

			if($eTask->assertUpdate() === FALSE) {
				return '';
			}

			return '<a href="'.self::urlUpdateProduct($eStructure, $state, $eTask).'" class="btn btn-sm btn-outline-primary treatment-row-add">'.\Asset::icon('plus-circle').' '.s("Produit").'</a>';
		}

		$h = '<div class="treatment-row-name">'.\work\ToolUi::link($eTask['eToolPhyto']).'</div>';

		$dose = $this->getDose($eTask);

		if($dose !== '') {
			$h .= '<div class="util-annotation">'.$dose.'</div>';
		}

		return $h;

	}

	protected function getQuantity(\series\Task $eTask): string {

		if($eTask['quantity'] === NULL) {
			return '';
		}

		return \series\TaskUi::getTreatmentQuantity($eTask['quantity'], $eTask['unit']);

	}

	/**
	 * « 2 kg/ha · 20 L/ha », sans bouillie pour un produit prêt à l'emploi, et rien sans dose.
	 */
	protected function getDose(\series\Task $eTask): string {

		$dose = $eTask['treatment']['dose'] ?? NULL;

		if($dose === NULL) {
			return '';
		}

		$h = \work\RoutineUi::getPhytoDose($dose, $eTask['unit']);

		$water = $eTask['treatment']['water'] ?? NULL;

		if($eTask['eToolPhyto']['routineValue']['readyToUse'] === FALSE and $water !== NULL) {
			$h .= ' · '.s("{value} L/ha", $water);
		}

		return $h;

	}

	/**
	 * Le panneau de « + Produit » et de « Choisir le produit » : le produit et la ligne de traitement
	 * du formulaire complet, rien d'autre. La roue garde « Modifier l'intervention » pour le reste.
	 */
	public function updateProduct(\structure\Structure $eStructure, array $state, \Collection $cTask, \series\Task $eTask): \Panel {

		$uiTask = new \series\TaskUi();

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/treatment:doUpdateProduct', ['autocomplete' => 'off']);

			$h .= $form->hidden('id', $eStructure);

			foreach($state as $name => $value) {
				$h .= $form->hidden($name, $value);
			}

			foreach($cTask as $eTaskSelected) {
				$h .= $form->hidden('ids[]', $eTaskSelected);
			}

			if($cTask->count() > 1) {
				$h .= '<div class="util-block-info">'.p("{value} traitement recevra ce produit.", "{value} traitements recevront ce produit.", $cTask->count()).'</div>';
			}

			$h .= $uiTask->getProductsGroup($form, $eTask);
			$h .= $uiTask->getTreatmentField($form, $eTask);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-treatment-product',
			title: s("Produit du traitement"),
			body: $h
		);

	}

	/**
	 * Un traitement noté à la semaine se précise d'un geste : la semaine elle-même ouvre le choix du
	 * jour, sans rien ajouter à la ligne. Le jour que `TaskLib::fillDoneProposed()` a déduit y est mis
	 * en avant, avec sa raison.
	 */
	protected function getDate(\series\Task $eTask): string {

		if($eTask['doneDate'] !== NULL or $eTask->assertUpdate() === FALSE) {
			return self::getDone($eTask);
		}

		$eTask->expects(['doneProposed']);

		$proposed = $eTask['doneProposed']['date'] ?? NULL;
		$url = \util\FormUi::getQuickUrl($eTask);

		$h = '<a data-dropdown="bottom-start" class="util-quick treatment-row-week">'.self::getDone($eTask).'</a>';
		$h .= '<div class="dropdown-list dropdown-list-minimalist util-quick-dropdown">';
			$h .= '<div class="util-quick-form">';
				$h .= '<h4>'.s("Jour du traitement").'</h4>';
				$h .= '<div class="treatment-days">';

					for($day = 1; $day <= 7; $day++) {

						$date = week_date_day($eTask['doneWeek'], $day);

						$h .= '<a data-ajax="'.$url.'" post-id="'.$eTask['id'].'" post-property="doneDate" post-done[date]="'.$date.'" class="btn '.($date === $proposed ? 'btn-white' : 'btn-outline-white').'">';
							$h .= '<span>'.\util\DateUi::getDayNameShort($day).'</span>';
							$h .= '<span>'.(int)substr($date, 8, 2).'</span>';
						$h .= '</a>';

					}

				$h .= '</div>';

				if($proposed !== NULL) {
					$h .= '<div class="treatment-days-reason">'.$this->getDoneProposed($eTask).'</div>';
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	protected function getDoneProposed(\series\Task $eTask): string {

		return match($eTask['doneProposed']['source']) {
			\series\TaskLib::PROPOSED_TIMESHEET => s("D'après le temps de travail"),
			\series\TaskLib::PROPOSED_TIMESHEETS => s("Temps de travail du {from} au {to}", [
				'from' => \util\DateUi::textual($eTask['timesheetStart'], \util\DateUi::DAY_MONTH),
				'to' => \util\DateUi::textual($eTask['timesheetStop'], \util\DateUi::DAY_MONTH)
			]),
			\series\TaskLib::PROPOSED_DONE => s("Comme les autres traitements de la semaine"),
			\series\TaskLib::PROPOSED_UPDATED => s("Modifié ce jour-là"),
			\series\TaskLib::PROPOSED_CREATED => s("Noté ce jour-là"),
			\series\TaskLib::PROPOSED_COMMENT => s("Commenté ce jour-là")
		};

	}

	/**
	 * Le carnet se lit année par année : un traitement y est toujours de l'année de son onglet, qui n'a
	 * donc pas à s'écrire.
	 */
	public static function getDone(\series\Task $eTask): string {

		if($eTask['doneDate'] !== NULL) {
			return \util\DateUi::textual($eTask['doneDate'], \util\DateUi::DAY_MONTH);
		}

		return s("semaine {value}", week_number($eTask['doneWeek']));

	}

	protected function getCulture(\series\Task $eTask): string {

		$h = '<div class="treatment-culture">';

			if($eTask['plant']->notEmpty()) {
				$h .= \plant\PlantUi::getVignette($eTask['plant'], '2rem');
			}

			$h .= '<div>';
				if($eTask['series']->notEmpty()) {
					$h .= '<div>'.\series\SeriesUi::link($eTask['series']).'</div>';
					if($eTask['series']['cccPlace']->notEmpty()) {
						$h .= '<div class="util-annotation">'.new \series\CultivationUi()->displayPlaces($eTask['series']['use'], $eTask['series']['cccPlace']).'</div>';
					}
				} else if($eTask['plant']->notEmpty()) {
					$h .= '<div>'.encode($eTask['plant']['name']).'</div>';
				} else {
					$h .= '<div>'.s("Hors série").'</div>';
				}
				$h .= $this->getDescription($eTask);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qui distingue deux traitements d'une même culture : la description de l'intervention, sur une
	 * ligne, puis le matériel employé. Rien quand l'intervention n'a ni l'un ni l'autre.
	 */
	protected function getDescription(\series\Task $eTask): string {

		$h = '';

		if($eTask['description'] !== NULL) {
			$h .= '<div class="treatment-row-description util-annotation" title="'.encode($eTask['description']).'">'.encode($eTask['description']).'</div>';
		}

		if($eTask['cTool']->notEmpty()) {
			$h .= '<div class="treatment-row-tools">';
				foreach($eTask['cTool'] as $eTool) {
					$h .= '<span class="treatment-row-tool">'.\work\ToolUi::getVignette($eTool, '1.25rem', '1.25rem').' '.encode($eTool['name']).'</span>';
				}
			$h .= '</div>';
		}

		return $h;

	}

	protected function getActions(\series\Task $eTask): string {

		$h = '<a data-dropdown="bottom-end" class="btn btn-sm btn-color-primary dropdown-toggle">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<a href="'.\series\TaskUi::url($eTask).'" class="dropdown-item">'.s("Voir l'intervention").'</a>';
			if($eTask->assertUpdate()) {
				$h .= '<a href="/series/task:update?id='.$eTask['id'].'" class="dropdown-item">'.s("Modifier l'intervention").'</a>';
			}
		$h .= '</div>';

		return $h;

	}

}
?>
