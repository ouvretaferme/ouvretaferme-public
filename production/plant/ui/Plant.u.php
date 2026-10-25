<?php
namespace plant;

/**
 * Ui class for plant forms
 *
 */
class PlantUi {

	use \Notifiable;

	public function __construct() {

		\Asset::css('plant', 'plant.css');

	}

	public static function link(Plant $ePlant, bool $newTab = FALSE): string {
		return '<a href="'.self::url($ePlant).'" '.($newTab ? 'target="_blank"' : '').'>'.encode($ePlant['name']).'</a>';
	}

	public static function url(Plant $ePlant): string {

		$ePlant->expects(['id']);

		return '/espece/'.$ePlant['id'];

	}

	public static function urlManage(\structure\Structure $eStructure): string {
		return \structure\StructureUi::url($eStructure).'/especes';
	}

	public static function getCircle(Plant $ePlant): string {

		$ePlant->expects(['color']);

		return '<div class="plant-circle" style="background-color: '.$ePlant['color'].'"></div>';

	}

	public function query(\PropertyDescriber $d, bool $multiple = FALSE) {

		$d->prepend ??= \Asset::icon('flower2');
		$d->field = 'autocomplete';

		$d->placeholder ??= s("Tapez un nom d'espèce");
		$d->multiple = $multiple;
		$d->wrapper = 'plant';

		$d->autocompleteUrl = '/plant/:query';
		$d->autocompleteResults = function(Plant $e) {
			return self::getAutocomplete($e);
		};

	}

	public static function getAutocomplete(Plant $ePlant): array {

		\Asset::css('media', 'media.css');

		$item = self::getVignette($ePlant, '2.5rem');
		$item .= '<div>'.encode($ePlant['name']).'</div>';

		return [
			'value' => $ePlant['id'],
			'itemHtml' => $item,
			'itemText' => $ePlant['name']
		];

	}

	public static function getAutocompleteCreate(\structure\Structure $eStructure): array {

		$item = \Asset::icon('plus-circle');
		$item .= '<div>'.s("Ajouter une espèce manquante").'</div>';

		return [
			'type' => 'link',
			'link' => '/plant/plant:create?structure='.$eStructure['id'],
			'itemHtml' => $item
		];

	}

	/*
	 * Les vignettes réellement présentes dans le sprite.
	 *
	 * Un <use> qui vise un symbole absent ne rend rien et ne signale rien : le trou se
	 * voit à l'écran et jamais dans les journaux. La liste ne dépend pas de la structure
	 * connectée, un statique simple suffit ; le fichier fait 1,2 Mo et son balayage
	 * 1,7 ms, fait une fois par requête.
	 */
	private static ?array $vignettes = NULL;

	public static function hasVignette(?string $fqn): bool {

		if($fqn === NULL) {
			return FALSE;
		}

		if(self::$vignettes === NULL) {
			preg_match_all('/<symbol[^>]+id="([^"]+)"/', file_get_contents(\Package::getPath('plant').'/static/image/plants.svg'), $matches);
			self::$vignettes = array_flip($matches[1]);
		}

		return isset(self::$vignettes[strtolower($fqn)]);

	}

	// L'adresse du symbole d'une espèce, à donner tel quel à un <use>.
	public static function getVignetteHref(string $fqn): string {
		return \Asset::getPath('plant', 'plants.svg', 'image').'#'.strtolower($fqn);
	}

	public static function getVignette(Plant $ePlant, string $size): string {

		\Asset::css('plant', 'plant.css');

		$ePlant->expects(['fqn']);

		$ui = new \media\PlantVignetteUi();

		if($ePlant['fqn'] !== NULL) {

			$h = '<div class="plant-vignette" style="'.$ui->getSquareCss($size).'">';
				$h .= '<svg width="'.$size.'" height="'.$size.'"><use xlink:href="'.\Asset::getPath('plant', 'plants.svg', 'image').'#'.strtolower($ePlant['fqn']).'"/></svg>';
			$h .= '</div>';

			return $h;

		} else {

			$ePlant->expects(['id', 'vignette']);

			$class = 'media-circle-view ';
			$style = '';

			if($ePlant['vignette'] === NULL) {

				$class .= ' media-vignette-default';
				$content = mb_substr($ePlant['name'], 0, 2);

			} else {

				$format = $ui->convertToFormat($size);

				$style .= 'background-image: url('.$ui->getUrlByElement($ePlant, $format).');';
				$content = '';

			}

			return '<div class="'.$class.'" style="'.$ui->getSquareCss($size).'; '.$style.'">'.encode($content).'</div>';

		}


	}

	public function getSearch(\structure\Structure $eStructure, \Search $search): string {

		$h = '<div id="plant-search" class="util-block-search '.($search->empty(['status', 'cFamily']) ? 'hide' : '').'">';

			$form = new \util\FormUi();

			$h .= $form->openAjax(self::urlManage($eStructure), ['method' => 'get', 'class' => 'util-search']);

				$h .= $form->hidden('structure', $eStructure['id']);

				$h .= '<fieldset>';
					$h .= '<legend>'.s("Espèce").'</legend>';
					$h .= $form->dynamicField(new Plant([
						'structure' => $eStructure
					]), 'id', function($d) use($search) {
						$d->name = 'plantId';
						$d->autocompleteDefault = $search->get('id');
						$d->attributes = [
							'data-autocomplete-select' => 'submit'
						];
					});
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Famille").'</legend>';
					$h .= $form->select('family', $search->get('cFamily'), $search->get('family'));
				$h .= '</fieldset>';

				$h .= '<div class="util-search-submit">';

					$h .= $form->submit(s("Chercher"));
					$h .= '<a href="'.self::urlManage($eStructure).'" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';

				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	public function getList(\Collection $cPlant) {

		\Asset::css('plant', 'plant.css');

		$h = '';

		foreach($cPlant as $ePlant) {

			$h .= '<a href="'.PlantUi::url($ePlant).'" class="plant-item">';
				$h .= '<div class="plant-item-image">';
					$h .= self::getVignette($ePlant, '4rem');
				$h .= '</div>';
				$h .= '<div class="plant-item-presentation">';
					$h .= '<div class="plant-item-name">';
						$h .= $ePlant['name'];
						if($ePlant['aliases']) {
							$h .= '<small> '.s("ou {aliases}", ['aliases' => encode($ePlant['aliases'])]).'</small>';
						}
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</a>';

		}

		return $h;

	}

	public function display(Plant $ePlant, \structure\Structure $eStructure, \Collection $ccCrop, \Collection $cActionMain, \Search $search, \Collection $cNoteLinked): \Panel {

		$h = '<div class="util-vignette">';

			$h .= self::getVignette($ePlant, size: '10rem');

			$h .= '<div>';

				$h .= '<h1>'.encode($ePlant['name']).'</h1>';

				$h .= '<dl class="util-presentation util-presentation-1">';

					if($ePlant['aliases']) {
						$h .= '<dt>'.s("Autres noms").'</dt>';
						$h .= '<dd>'.encode($ePlant['aliases']).'</dd>';
					}

					if($ePlant['family']->notEmpty()) {
						$h .= '<dt>'.s("Famille").'</dt>';
						$h .= '<dd>'.$ePlant['family']['name'].'</dd>';
					}

				$h .= '</dl>';

			$h .= '</div>';


		$h .= '</div>';
		$h .= '<br/>';
		$h .= '<br/>';

		if(\structure\StructureSetting::hasCommercialisationApp()) {
			$h .= implode('', self::notify('getAnalyze', $ePlant));
		}

		$h .= new \note\NoteUi()->getLinked($eStructure, $cNoteLinked, withTargets: FALSE);

		if($ccCrop->notEmpty()) {
			$h .= '<h3>'.s("Itinéraires techniques").'</h3>';
			$h .= new \sequence\SequenceUi()->getListByPlants($eStructure, $ccCrop, $cActionMain, $search);
		}

		return new \Panel(
			id: 'panel-plant-display',
			title: s("Espèce"),
			body: $h
		);

	}

	public function getTabs(\structure\Structure $eStructure, array $plants, \Search $search): string {

		if($plants[Plant::INACTIVE] === 0) {
			return '';
		}

		$tabs = '<a href="'.self::urlManage($eStructure).'" class="tab-item '.($search->get('status') === Plant::ACTIVE ? 'selected' : '').'"><span><span class="tab-item-name">'.s("Espèces actives").'</span> <span class="util-counter util-counter-solid">'.$plants[Plant::ACTIVE].'</span></span></a>';
		$tabs .= '<a href="'.self::urlManage($eStructure).'/'.Plant::INACTIVE.'" class="tab-item '.($search->get('status') === Plant::INACTIVE ? 'selected' : '').'"><span><span class="tab-item-name">'.s("Espèces désactivées").'</span> <small class="util-counter util-counter-solid">'.$plants[Plant::INACTIVE].'</small></span></a>';

		return \main\DesignUi::getPageTabs($tabs, fold: TRUE);

	}

	public function getManage(\structure\Structure $eStructure, \Collection $cPlant): string {

		if($cPlant->empty()) {
			return '<div class="util-empty">'.s("Il n'y a aucune espèce à afficher...").'</div>';
		}

		\Asset::css('plant', 'plant.css');

		$h = '<div id="plant-manage">';

			$h .= $this->getStructurePlants($eStructure, $cPlant);

		$h .= '</div>';

		return $h;

	}

	protected function getStructurePlants(\structure\Structure $eStructure, \Collection $cPlant): string {

		$hasSafety = $cPlant->match(fn($ePlant) => $ePlant['plantsSafetyMargin'] !== NULL or $ePlant['seedsSafetyMargin'] !== NULL);

		$h = '<div class="util-overflow-xs stick-xs">';

			$h .= '<table class="tr-even">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th></th>';
						$h .= '<th>'.s("Nom").'</th>';
						$h .= '<th class="text-center">'.s("Variétés").'</th>';
						$h .= '<th class="text-center">'.s("Calibres").'</th>';
						if($hasSafety) {
							$h .= '<th>'.s("Marges de sécurité").'</th>';
						}
						$h .= '<th class="hide-xs-down">'.s("Famille").'</th>';
						$h .= '<th>'.s("Activé").'</th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

				foreach($cPlant as $ePlant) {

					$h .= '<tr>';
						$h .= '<td class="td-min-content">';
							if($ePlant['fqn'] === NULL) {
								$h .= new \media\PlantVignetteUi()->getCamera($ePlant, size: '3rem');
							} else {
								$h .= PlantUi::getVignette($ePlant, size: '3rem');
							}
						$h .= '</td>';
						$h .= '<td>';
							$h .= self::getCircle($ePlant);
							$h .= self::link($ePlant);
							if($ePlant->isOwner() === FALSE) {
								$h .= ' <span class="plant-manage-locked">'.\Asset::icon('lock-fill').'</span>';
							}
						$h .= '</td>';
						$h .= '<td class="text-center">';
							$h .= '<a href="/plant/variety?id='.$eStructure['id'].'&plant='.$ePlant['id'].'" class="btn btn-outline-primary opacity-'.($ePlant['varieties'] ? '100' : '25').'">'.($ePlant['varieties'] ?? '0').'</a>';
						$h .= '</td>';
						$h .= '<td class="text-center">';
							$h .= '<a href="/plant/size?id='.$eStructure['id'].'&plant='.$ePlant['id'].'" class="btn btn-outline-primary opacity-'.($ePlant['sizes'] ? '100' : '25').'">'.($ePlant['sizes'] ?? '0').'</a>';
						$h .= '</td>';

						if($hasSafety) {

							$h .= '<td>';

								$list = [];

								if($ePlant['plantsSafetyMargin'] !== NULL) {
									$list[] = '<div>'.s("Plants <muted>{value} %</muted>", ['value' => $ePlant['plantsSafetyMargin'], 'muted' => '<span class="color-muted">']).'</div>';
								}

								if($ePlant['seedsSafetyMargin'] !== NULL) {
									$list[] = '<div>'.s("Semences <muted>{value} %</muted>", ['value' => $ePlant['seedsSafetyMargin'], 'muted' => '<span class="color-muted">']).'</div>';
								}

								if($list) {
									$h .= implode('', $list);
								} else {
									$h .= '-';
								}

							$h .= '</td>';

						}

						$h .= '<td class="hide-xs-down">';
							if($ePlant['family']->empty()) {
								$h .= '-';
							} else {
								$h .= encode($ePlant['family']['name']);
							}
						$h .= '</td>';
						$h .= '<td class="text-center td-min-content">';
							$h .= \util\TextUi::switch([
								'id' => 'plant-switch-'.$ePlant['id'],
								'data-ajax' => '/plant/plant:doUpdateStatus',
								'post-id' => $ePlant['id'],
								'post-status' => ($ePlant['status'] === Plant::ACTIVE) ? Plant::INACTIVE : Plant::ACTIVE
							], $ePlant['status'] === Plant::ACTIVE);
						$h .= '</td>';
						$h .= '<td class="text-end">';

							if($eStructure->canManage()) {

								$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
								$h .= '<div class="dropdown-list">';
									$h .= '<div class="dropdown-title">'.encode($ePlant['name']).'</div>';

										$h .= '<a href="/plant/plant:update?id='.$ePlant['id'].'" class="dropdown-item">';
											$h .= s("Modifier l'espèce");
										$h .= '</a>';
										$h .= '<a href="/plant/variety?id='.$eStructure['id'].'&plant='.$ePlant['id'].'" class="dropdown-item">'.s("Gérer les variétés").'</a>';
										$h .= '<a href="/plant/size?id='.$eStructure['id'].'&plant='.$ePlant['id'].'" class="dropdown-item">'.s("Gérer les calibres").'</a>';

										if($ePlant->assertDelete()) {

											$h .= '<div class="dropdown-divider"></div>';

											$h .= '<a data-ajax="/plant/plant:doDelete" data-confirm="'.s("Supprimer cette espèce ?").'" post-id="'.$ePlant['id'].'" class="dropdown-item">';
												$h .= s("Supprimer l'espèce");
											$h .= '</a>';

										}

								$h .= '</div>';

							}

						$h .= '</td>';
					$h .= '</tr>';
				}
				$h .= '</tbody>';
			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function create(\structure\Structure $eStructure, \Collection $cFamily): \Panel {

		return new \Panel(
			id: 'panel-plant-create',
			title: s("Ajouter une espèce pour la ferme"),
			body: $this->createForm($eStructure, $cFamily, 'panel'),
			close: 'reload'
		);

	}

	protected function createForm(\structure\Structure $eStructure, \Collection $cFamily, string $origin) {

		$form = new \util\FormUi();

		$ePlant = new Plant([
			'cFamily' => $cFamily,
		]);

		$h = $form->openAjax('/plant/plant:doCreate', ['data-ajax-origin' => $origin]);

			$h .= $form->asteriskInfo();

			$h .= $form->hidden('structure', $eStructure['id']);

			$h .= $form->dynamicGroups($ePlant, ['name*', 'color*', 'family', 'cycle*']);

			$h .= $form->group(content: '<h3>'.s("Marges de sécurité").'</h3>');
			$h .= $form->dynamicGroups($ePlant, ['plantsSafetyMargin', 'seedsSafetyMargin']);

			$h .= $form->group(
				content: $form->submit(s("Ajouter"))
			);

		$h .= $form->close();
		
		return $h;

	}

	public function update(Plant $ePlant): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/plant/plant:doUpdate');

			$h .= $form->hidden('id', $ePlant['id']);
			$h .= $form->hidden('origin', GET('origin'));
			$h .= $form->dynamicGroups($ePlant, ['name', 'color']);

			if($ePlant->isOwner()) {
				$h .= $form->dynamicGroups($ePlant, ['family', 'cycle']);
			}

			$h .= '<br/>';
			$h .= $form->group(content: '<h3>'.s("Marges de sécurité").'</h3>');
			$h .= $form->dynamicGroups($ePlant, ['plantsSafetyMargin', 'seedsSafetyMargin']);

			$h .= '<br/>';
			$h .= $form->dynamicGroup($ePlant, 'storage');

			if($ePlant->acceptRating()) {

				\Asset::css('plant', 'criterion.css');
				\Asset::js('plant', 'criterion.js');

				$h .= '<br/>';
				$h .= $form->group(content: '<h3>'.s("Ce qui m'intéresse pour cette espèce").'</h3>');
				$h .= $form->group(
					content: $form->dynamicField($ePlant, 'criteria', function(\PropertyDescriber $d) {

						$d->after = \util\FormUi::info(p(
							"Choisissez jusqu'à {value} critère majeur, celui sur lequel vous jugez vos variétés.",
							"Choisissez jusqu'à {value} critères majeurs, ceux sur lesquels vous jugez vos variétés.",
							PlantSetting::PLANT_CRITERIA_MAX
						));

					}),
					// Le plafond est tenu par le même script que la campagne de notation :
					// il désactive les cases restantes une fois la limite atteinte.
					attributes: [
						'data-criteria-max' => PlantSetting::PLANT_CRITERIA_MAX,
						'onrender' => 'PlantCriterion.refresh(this)',
					]
				);

				$h .= '<br/>';

			}

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-plant-update',
			title: s("Modifier une espèce"),
			subTitle: self::getPanelHeader($ePlant),
			body: $h,
			close: 'reload'
		);

	}

	public static function getPanelHeader(Plant $ePlant): string {

		return '<div class="panel-header-subtitle">'.self::getVignette($ePlant, '2rem').'  '.encode($ePlant['name']).'</div>';

	}

	/**
	 * Choix des critères de notation d'une plante.
	 *
	 * Le bloc s'affiche sur plusieurs pages : $url est celle qui l'accueille, et les
	 * flèches y renvoient. Sans elle, naviguer dans l'historique depuis les variétés
	 * préférées éjecterait vers la page des critères.
	 *
	 * La feuille et le script sont chargés ici et pas dans le constructeur : ils ne
	 * servent qu'à ce bloc, alors que PlantUi est instanciée un peu partout.
	 */
	/**
	 * La matrice des critères : une ligne par espèce évaluable, une colonne par
	 * critère du référentiel.
	 *
	 * Le bloc est servi sur deux écrans, d'où l'url de retour en argument.
	 *
	 * Le châssis est un util-heat-table : un tableau dense a déjà son fond, son rayon
	 * et son filet d'en-tête dans le design system, rien à réécrire ici.
	 */
	public function updateCriterion(\structure\Structure $eStructure, \Collection $cPlant, array $cCriterionByType, string $url, ?string $filter): string {

		if($cPlant->empty()) {

			$h = '<div class="util-block-info">';
				$h .= \Asset::icon('calendar3', ['class' => 'util-block-icon']);
				$h .= '<p>'.s("Les critères se choisissent espèce par espèce, à partir de ce que vous cultivez : créez vos premières séries dans le plan de culture et vos espèces à évaluer apparaîtront ici.").'</p>';
				$h .= '<a href="'.\structure\StructureUi::urlCultivationSeries($eStructure).'" class="btn btn-primary">'.s("Créer une série").'</a>';
			$h .= '</div>';

			return $h;

		}

		\Asset::css('plant', 'criterion.css');
		\Asset::js('plant', 'criterion.js');

		$h = '<div id="plant-criterion">';

			$h .= $this->getCriterionFilters($cPlant, $url, $filter);

			$h .= '<div class="util-overflow-xl stick-xs">';

				$h .= '<table class="util-heat-table td-padding-xs td-vertical-align-middle tr-even plant-criterion-matrix">';

					$h .= $this->getCriterionHead($cCriterionByType);

					$h .= '<tbody>';

						foreach($cPlant as $ePlant) {

							if($filter === 'none' and $ePlant['criteria'] !== []) {
								continue;
							}

							$h .= $this->getCriterionRow($ePlant, $cCriterionByType);

						}

					$h .= '</tbody>';

				$h .= '</table>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le filtre vit dans l'url et non dans le navigateur : après une écriture, la page
	 * rechargée montre la même liste, et le lien se partage.
	 */
	protected function getCriterionFilters(\Collection $cPlant, string $url, ?string $filter): string {

		$none = $cPlant->find(fn(Plant $ePlant) => $ePlant['criteria'] === [])->count();

		$separator = str_contains($url, '?') ? '&' : '?';

		$options = [
			[NULL, s("Toutes"), $cPlant->count()],
			['none', s("À renseigner"), $none],
		];

		$h = '<div class="util-filters mb-1">';

			$h .= '<div>';

				$h .= '<div class="util-filter-label">'.s("Espèces").'</div>';

				$h .= '<div class="util-filter-options">';

					foreach($options as [$value, $label, $count]) {

						$h .= '<a href="'.$url.($value === NULL ? '' : $separator.'filter='.$value).'" class="util-filter-option'.($filter === $value ? ' is-selected' : '').'">';
							$h .= $label.' <span class="util-counter">'.$count.'</span>';
						$h .= '</a>';

					}

				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div>';
				$h .= '<div class="util-filter-label">'.s("Légende").'</div>';
				$h .= $this->getCriterionLegend();
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les libellés sont inclinés à quarante-cinq degrés : écrits en entier, ils
	 * tiennent dans une colonne de deux caractères de large.
	 *
	 * Le libellé d'une colonne déborde sur celles de sa droite, dont le fond est
	 * opaque : sans un empilement qui décroît vers la droite, la voisine le mange dès
	 * le troisième caractère.
	 */
	protected function getCriterionHead(array $cCriterionByType): string {

		$total = 0;

		foreach($cCriterionByType as $cCriterion) {
			$total += $cCriterion->count();
		}

		// Les largeurs sont déclarées ici et non sur les cellules : en table-layout
		// fixe, seule la première rangée les porte, et c'est celle des familles, qui
		// fusionne ses colonnes.
		$h = '<colgroup>';

			$h .= '<col class="plant-criterion-col-species"/>';
			$h .= str_repeat('<col/>', $total);
			$h .= '<col class="plant-criterion-col-spacer"/>';

		$h .= '</colgroup>';

		$h .= '<thead class="thead-sticky">';

			$h .= '<tr>';

				$h .= '<th class="plant-criterion-corner plant-criterion-family"></th>';

				foreach($cCriterionByType as $type => $cCriterion) {

					if($cCriterion->empty()) {
						continue;
					}

					$h .= '<th class="plant-criterion-family is-'.$type.'" colspan="'.$cCriterion->count().'">'.CriterionUi::p('type')->values[$type].'</th>';

				}

				$h .= '<th class="plant-criterion-family plant-criterion-spacer"></th>';

			$h .= '</tr>';

			$h .= '<tr>';

				$h .= '<th class="plant-criterion-corner"></th>';

				$position = 0;

				foreach($cCriterionByType as $type => $cCriterion) {

					foreach($cCriterion as $eCriterion) {

						$position++;

						$h .= '<th class="plant-criterion-head is-'.$type.'" style="z-index: '.($total - $position + 2).'">';
							$h .= '<span>'.encode($eCriterion['name']).'</span>';
						$h .= '</th>';

					}

				}

				$h .= '<th class="plant-criterion-head plant-criterion-spacer"></th>';

			$h .= '</tr>';

		$h .= '</thead>';

		return $h;

	}

	/**
	 * Une espèce et ses cases.
	 *
	 * Rendue seule en réponse à un clic : c'est la ligne entière qui change, la case
	 * cliquée mais aussi le compteur et le plafond des cases encore libres.
	 */
	public function getCriterionRow(Plant $ePlant, array $cCriterionByType): string {

		$h = '<tr id="plant-criterion-row-'.$ePlant['id'].'">';
			$h .= $this->getCriterionCells($ePlant, $cCriterionByType);
		$h .= '</tr>';

		return $h;

	}

	/**
	 * Le contenu d'une ligne, rendu seul en réponse à un clic.
	 *
	 * Tout l'état de la ligne vit dans ses cellules, y compris la liste des critères
	 * retenus et la marque des espèces vides : `renderOuter()` passe par DOMParser,
	 * qui jette un `tr` hors de sa table, et la réponse ne peut donc remplacer que
	 * l'intérieur de la ligne.
	 */
	public function getCriterionCells(Plant $ePlant, array $cCriterionByType): string {

		$ePlant->expects(['criteria', 'criteriaReference']);

		$none = ($ePlant['criteria'] === []);
		$full = (count($ePlant['criteria']) >= PlantSetting::PLANT_CRITERIA_MAX);

		$h = '';

			$h .= '<td class="plant-criterion-species'.($none ? ' is-none' : '').'" data-plant="'.$ePlant['id'].'" data-criteria="'.encode(json_encode($ePlant['criteria'])).'">';
				$h .= '<span>';
					$h .= self::getVignette($ePlant, '1.4rem');
					$h .= '<span class="plant-criterion-name">'.encode($ePlant['name']).'</span>';

					if($none) {
						$h .= '<span class="plant-criterion-count plant-criterion-todo" title="'.s("À renseigner").'">'.\Asset::icon('hourglass-split').'</span>';
					} else {
						$h .= '<span class="plant-criterion-count">'.count($ePlant['criteria']).'/'.PlantSetting::PLANT_CRITERIA_MAX.'</span>';
					}

				$h .= '</span>';
			$h .= '</td>';

			foreach($cCriterionByType as $type => $cCriterion) {

				foreach($cCriterion as $eCriterion) {

					$mine = in_array($eCriterion['id'], $ePlant['criteria']);
					$community = in_array($eCriterion['id'], $ePlant['criteriaReference']);

					$class = 'plant-criterion-cell is-'.$type;
					$class .= $mine ? ' is-mine' : '';
					$class .= $community ? ' is-community' : '';
					$class .= ($mine === FALSE and $full) ? ' is-full' : '';

					$h .= '<td class="text-center">';
						$h .= '<a class="'.$class.'" data-action="plant-criterion-toggle" data-criterion="'.$eCriterion['id'].'" title="'.encode($ePlant['name'].' · '.$eCriterion['name']).'">';
							$h .= self::getCriterionMark();
						$h .= '</a>';
					$h .= '</td>';

				}

			}

			$h .= '<td class="plant-criterion-spacer"></td>';

		return $h;

	}

	/**
	 * Deux motifs qui s'emboîtent : le cercle pour la communauté, le carré pour la
	 * structure, et le carré dans le cercle quand les deux retiennent le critère.
	 *
	 * L'étoile est réservée à la notation des variétés, sur l'écran suivant.
	 */
	public static function getCriterionMark(): string {

		$h = '<svg class="plant-criterion-mark" viewBox="0 0 20 20">';
			$h .= '<circle class="plant-criterion-ring" cx="10" cy="10" r="8"></circle>';
			$h .= '<rect class="plant-criterion-square" x="6.1" y="6.1" width="7.8" height="7.8" rx="1.6"></rect>';
		$h .= '</svg>';

		return $h;

	}

	protected function getCriterionLegend(): string {

		$states = [
			'is-mine' => s("Vous"),
			'is-community' => s("La communauté"),
			'is-mine is-community' => s("Les deux"),
		];

		$h = '<div class="plant-criterion-legend">';

			foreach($states as $class => $label) {
				$h .= '<span class="is-production '.$class.'">'.self::getCriterionMark().$label.'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Plant::model()->describer($property, [
			'fqn' => s("Nom qualifié"),
			'name' => s("Nom"),
			'color' => s("Couleur"),
			'aliases' => s("Autres noms"),
			'plantsSafetyMargin' => s("Pour le calcul des plants à produire"),
			'seedsSafetyMargin' => s("Pour le calcul des semences à acheter"),
			'storage' => s("Durée de conservation après récolte"),
			'crateWeight' => s("Poids d'une caisse"),
			'family' => s("Famille"),
			'cycle' => s("Cycle de culture"),
			'criteria' => s("Critères de notation"),
		]);

		switch($property) {

			case 'criteria' :

				// Une liste de cases par famille : vingt critères à plat ne se lisent
				// pas, et chaque famille répond à une question distincte.
				$d->field = function(\util\FormUi $form, Plant $e) {

					$h = '';

					foreach($e['cCriterionByType'] as $type => $cCriterion) {

						if($cCriterion->empty()) {
							continue;
						}

						$h .= '<div class="plant-criterion-group">';

							$h .= '<h5 class="plant-criterion-group-title">'.CriterionUi::p('type')->values[$type].'</h5>';

							// Le champ porte le nom de la propriété, crochets compris : c'est ce
							// que build() attend pour valider la colonne contre le schéma.
							$h .= $form->checkboxes('criteria[]', $cCriterion, $e['criteria'], [
								'all' => FALSE,
								'columns' => 2,
							]);

						$h .= '</div>';

					}

					return $h;

				};
				break;

			case 'id' :
				$d->autocompleteBody = function(\util\FormUi $form, Plant $e) {
					$e->expects(['structure']);
					return [
						'structure' => $e['structure']->empty() ? NULL : $e['structure']['id']
					];
				};
				new \plant\PlantUi()->query($d);
				break;

			case 'color' :
				$d->after = \util\FormUi::info(s("Une couleur sombre utilisée pour l'assolement"));
				break;

			case 'aliases' :
				$d->append = '<small>'.s("(séparés par des virgules)").'</small>';
				break;

			case 'plantsSafetyMargin' :
				$d->prepend = \Asset::icon('plus');
				$d->append = '%';
				$d->after = \util\FormUi::info(s("Cette marge de sécurité sera intégrée dans le calcul du nombre de plants nécessaires pour les cultures sur lesquelles vous autoproduisez vos plants."));
				break;

			case 'seedsSafetyMargin' :
				$d->prepend = \Asset::icon('plus');
				$d->append = '%';
				$d->after = \util\FormUi::info(s("Cette marge de sécurité sera intégrée dans le calcul de la quantité de semences à acheter pour vos cultures que vous implantez en semis direct."));
				break;

			case 'storage' :
				$d->append = s("semaines");
				$d->placeholder = fn(Plant $e) => $e['storageDefault'];
				break;

			case 'crateWeight' :
				$d->append = s("kg / caisse");
				break;

			case 'family' :
				$d->values = fn(Plant $e) => $e['cFamily'] ?? $e->expects(['cFamily']);
				break;

			case 'cycle' :
				$d->values = [
					Plant::ANNUAL => s("culture annuelle"),
					Plant::PERENNIAL => s("culture pérenne"),
				];
				$d->required = TRUE;
				break;

		}

		return $d;

	}

}
?>
