<?php
namespace toolbox;

/**
 * L'écran Consommation d'eau de la Boîte à outils (planche production-finir-eau, bancs `a-total`,
 * `a-compteur`, `saisie-ligne` et `tour-c`). Son analogue est la pluviométrie, écran par écran, et son
 * balisage vient de `RainUi`.
 *
 * Trois états : aucun compteur, et l'écran accueille ; un compteur, affiché directement ; plusieurs,
 * un onglet « Total » devant un onglet par compteur (arbitrage Q1 A).
 *
 * On relève ce que le compteur affiche, jamais des m³ consommés : la consommation est l'écart entre
 * deux relevés, que `WaterReadingLib::getMeters()` a déjà posé sur chaque relevé.
 */
class WaterReadingUi {

	public function __construct() {

		\Asset::css('main', 'chart.css');
		\Asset::css('toolbox', 'toolbox.css');
		\Asset::js('main', 'chart.js');

	}

	public static function url(\structure\Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/outils/eau';
	}

	/**
	 * Un compteur sur la page, son onglet ouvert.
	 */
	public static function urlMeter(\structure\Structure $eStructure, WaterMeter $eWaterMeter): string {
		return self::url($eStructure).'?meter='.$eWaterMeter['id'];
	}

	/**
	 * La roue de l'outil porte l'ajout d'un compteur et l'export des relevés (retour du 02/10,
	 * « déplacer ajouter un compteur dans le menu », « Télécharger va aussi dans le menu »), comme
	 * « Nouveau pluviomètre » sur la pluviométrie : l'eau et la pluie font exception à la ligne de gestes
	 * sous les onglets du `CLAUDE.md` du package.
	 *
	 * Elle porte aussi le réglage du rappel des compteurs à relever, comme le menu du carnet d'entretien
	 * (demande du 03/10), au même droit que la pose d'un compteur.
	 *
	 * Sans compteur, l'accueil porte déjà le bouton qui l'ajoute, et il n'y a rien à rappeler. L'export
	 * demande de gérer la structure et un relevé à sortir.
	 */
	public function getHeader(\structure\Structure $eStructure, \Collection $cFavorite, array $waters): string {

		$menu = '';

		if($eStructure->canPlanning() and $waters['cWaterMeter']->notEmpty()) {
			$menu .= WaterMeterUi::getCreate($eStructure, s("Nouveau compteur"), 'dropdown-item');
			$menu .= '<a href="'.WaterConfigurationUi::urlCreate($eStructure).'" class="dropdown-item">'.\Asset::icon('bell').' '.s("Paramétrer la consommation d'eau").'</a>';
		}

		if($eStructure->canManage() and $waters['eWaterReadingLast']->notEmpty()) {
			$menu .= '<a href="/toolbox/waterReading:exportCsv?id='.$eStructure['id'].'" class="dropdown-item" data-ajax-download data-waiter>'.\Asset::icon('download').' '.s("Télécharger les relevés").'</a>';
		}

		return new ToolUi()->getHeader($eStructure, Favorite::WATER, $cFavorite, menu: $menu);

	}

	/**
	 * L'accueil d'une structure sans compteur, sur le modèle de `RainUi::getStart()` : ce que l'outil
	 * fait, ce qu'il donnera une fois des relevés notés, puis le geste, qui ajoute le compteur.
	 */
	public function getStart(\structure\Structure $eStructure): string {

		$promises = [
			['droplet-half', s("Les m³ consommés sur la ferme"), s("entre deux relevés, par mois et depuis le 1er janvier")],
			['graph-up', s("Comparer à l'année précédente"), s("dès la deuxième année")],
		];

		$content = '<p>'.s("Notez ce qu'affichent vos compteurs d'eau quand vous passez devant, et suivez votre consommation d'eau sur la ferme relevé après relevé.").'</p>';
		$content .= new ToolUi()->getPromises($promises);

		return new \main\DesignUi()->getStart(
			\Asset::icon('droplet-half'),
			s("L'eau que vous consommez"),
			$content,
			image: \Asset::image('main', 'friends/libellule.svg'),
			buttons: $eStructure->canPlanning() ? WaterMeterUi::getCreate($eStructure, s("Ajouter un compteur"), 'btn btn-primary btn-xl') : NULL
		);

	}

	public function getExportHeader(): array {

		return [
			'meter',
			'date',
			'value',
			'consumption',
			'changed',
			'changed_value',
		];

	}

	public static function getExportFilename(): string {
		return 'consommation-eau-'.\Clock::date().'.csv';
	}

	/**
	 * Un seul compteur : ni onglets ni « Total », qui rediraient le compteur, ni son nom, comme la
	 * pluviométrie à un seul pluviomètre.
	 */
	public function getOne(\structure\Structure $eStructure, WaterMeter $eWaterMeter): string {

		return $this->getContent($eStructure, $eWaterMeter);

	}

	/**
	 * « Total » devant un onglet par compteur, toujours dans l'ordre de leur création
	 * (`WaterMeterLib::getByStructure()`). L'onglet ouvert est celui que l'adresse désigne
	 * (`$eWaterMeterSelected`), sinon le dernier ouvert, que `Lime.Tab` retient par l'id de la barre.
	 * `meter` sort de l'adresse une fois l'onglet ouvert : le rechargement qui suit un relevé rouvrirait
	 * sinon toujours le même.
	 *
	 * `$waters` est ce que rend `WaterReadingLib::getSummary()`.
	 */
	public function getSeveral(\structure\Structure $eStructure, array $waters, WaterMeter $eWaterMeterSelected): string {

		$forceTab = $eWaterMeterSelected->notEmpty() ? 'water-meter-'.$eWaterMeterSelected['id'] : NULL;

		$tabs = '<a class="tab-item" data-tab="water-total" onclick="Lime.Tab.select(this)">'.s("Total").'</a>';

		$panels = '<div class="tab-panel" data-tab="water-total">';
			$panels .= $this->getTotal($eStructure, $waters);
		$panels .= '</div>';

		foreach($waters['cWaterMeter'] as $eWaterMeter) {

			$tabs .= '<a class="tab-item" data-tab="water-meter-'.$eWaterMeter['id'].'" onclick="Lime.Tab.select(this)">'.WaterMeterUi::getName($eWaterMeter).'</a>';

			$panels .= '<div class="tab-panel" data-tab="water-meter-'.$eWaterMeter['id'].'">';
				$panels .= $this->getContent($eStructure, $eWaterMeter);
			$panels .= '</div>';

		}

		return \main\DesignUi::getPageTabs($tabs, panels: $panels, attributes: [
			'id' => 'water-tabs',
			'onrender' => 'Lime.Tab.restore(this, "water-total", '.json_encode($forceTab).'); history.removeArgument("meter")',
		], fold: TRUE);

	}

	/**
	 * L'onglet « Total » : ce que tous les compteurs ont consommé cette année, puis un rang par
	 * compteur.
	 */
	protected function getTotal(\structure\Structure $eStructure, array $waters): string {

		if($waters['year'] === NULL) {

			$h = \main\DesignUi::getBlockEmpty(s("Pas encore de consommation"), s("Elle s'affichera dès qu'un compteur aura deux relevés."));

		} else {

			$figures = [self::getFigure($waters['year'], self::getSince($waters['yearSince']))];
			if($waters['yearPrevious'] !== NULL) {
				$figures[] = self::getFigure($waters['yearPrevious'], s("En {value} à la même date", \Clock::year() - 1));
			}

			$h = \main\DesignUi::getFigures($figures, 'rain-figures');

			$h .= $this->getChart($this->getChartTotal($waters), $waters['years']);

		}

		$h .= '<h3>'.s("Par compteur").'</h3>';
		$h .= $this->getMeters($eStructure, $waters['cWaterMeter']);

		return $h;

	}

	/**
	 * Un rang par compteur, son nom menant à son onglet. La dernière colonne relève le compteur sans
	 * quitter le total (commentaire sur Q1 A, « dans "Par compteur", permettre de relevé ») :
	 * le relevé y est daté du jour, ce que l'en-tête dit, et un autre jour se relève dans l'onglet du
	 * compteur. Celui qui ne peut pas relever n'a pas la colonne (`water-meters-read`).
	 *
	 * La colonne de l'année porte l'année en intitulé, comme la carte de la Boîte : chaque compteur
	 * compte depuis son propre jour la première année, qu'un seul « Depuis le… » ne dirait pas.
	 */
	protected function getMeters(\structure\Structure $eStructure, \Collection $cWaterMeter): string {

		$write = $eStructure->canPlanning();

		$h = '<div class="util-table util-table-bordered water-meters'.($write ? '' : ' water-meters-read').'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Compteur").'</div>';
				$h .= '<div>'.s("Dernier relevé").'</div>';
				$h .= '<div class="text-end">'.\Clock::year().'</div>';
				$h .= '<div class="text-end water-meters-share">'.s("Part").'</div>';
				if($write) {
					$h .= '<div class="water-meters-today">'.s("Relevé d'aujourd'hui").'</div>';
				}
			$h .= '</div>';

			foreach($cWaterMeter as $eWaterMeter) {

				$h .= '<div class="util-table-row">';
					$h .= '<div><a href="'.self::urlMeter($eStructure, $eWaterMeter).'">'.WaterMeterUi::getName($eWaterMeter).'</a></div>';
					if($eWaterMeter['eWaterReadingLast']->empty()) {
						$h .= '<div class="color-muted">'.s("pas encore de relevé").'</div>';
					} else {
						$h .= '<div class="color-muted">'.s("le {value}", self::getDay($eWaterMeter['eWaterReadingLast']['date'])).'</div>';
					}
					$h .= '<div class="text-end">'.($eWaterMeter['year'] === NULL ? '' : self::getM3($eWaterMeter['year'])).'</div>';
					$h .= '<div class="text-end water-meters-share">'.($eWaterMeter['share'] === NULL ? '' : s("{value} %", $eWaterMeter['share'])).'</div>';
					if($write) {
						$h .= '<div class="water-meters-today">'.$this->getToday($eStructure, $eWaterMeter).'</div>';
					}
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le relevé du jour d'un compteur, dans son rang du total : le jour part avec le formulaire, il ne
	 * reste que ce que le compteur affiche.
	 */
	protected function getToday(\structure\Structure $eStructure, WaterMeter $eWaterMeter): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/waterReading:doCreate', ['id' => 'water-today-'.$eWaterMeter['id'], 'class' => 'water-meters-form']);
			$h .= $form->hidden('structure', $eStructure);
			$h .= $form->hidden('meter', $eWaterMeter);
			$h .= $form->hidden('date', \Clock::date());
			$h .= $form->inputGroup($form->number('value', NULL, ['step' => 0.1, 'min' => 0, 'placeholder' => s("Relevé")]).$form->addon(s("m³")));
			// Un « Ajouter » par rang : aucun n'est le geste de l'écran, donc aucun n'est plein.
			$h .= $form->submit(s("Ajouter"), ['class' => 'btn btn-outline-primary']);
		$h .= $form->close();

		return $h;

	}

	/**
	 * La saisie d'un relevé, une ligne par compteur (arbitrage Q3 A), celle de « Il a plu ? » : le
	 * compteur est connu, il ne reste que le jour et ce qu'il affiche. Un second relevé du même jour
	 * remplace le premier (`WaterReadingLib::create()`).
	 */
	public function getQuick(\structure\Structure $eStructure, WaterMeter $eWaterMeter, string $actions = ''): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/waterReading:doCreate', ['id' => 'water-quick-'.$eWaterMeter['id'], 'class' => 'rain-quick water-quick']);
			$h .= $form->hidden('structure', $eStructure);
			$h .= $form->hidden('meter', $eWaterMeter);
			$h .= '<div class="rain-quick-label">'.\Asset::icon('droplet-half').' '.s("Relever le compteur").'</div>';
			$h .= $form->date('date', \Clock::date(), ['max' => \Clock::date()]);
			$h .= $form->inputGroup($form->number('value', NULL, ['step' => 0.1, 'min' => 0, 'placeholder' => s("Relevé")]).$form->addon(s("m³")));
			// Le bouton et la roue crantée passent à la ligne ensemble, comme sur « Il a plu ? ».
			if($actions !== '') {
				$h .= '<div class="rain-quick-submit">';
					$h .= $form->submit(s("Ajouter"));
					$h .= '<div class="rain-quick-actions">'.$actions.'</div>';
				$h .= '</div>';
			} else {
				$h .= $form->submit(s("Ajouter"));
			}
		$h .= $form->close();

		return $h;

	}

	/**
	 * Un compteur : sa ligne de saisie, que sa roue crantée ferme, ses chiffres, puis ses relevés.
	 * Sans le droit de relever (`WaterMeter::assertUpdate()`, le droit du planning), ni ligne ni roue.
	 */
	protected function getContent(\structure\Structure $eStructure, WaterMeter $eWaterMeter): string {

		if($eWaterMeter->assertUpdate()) {
			$h = $this->getQuick($eStructure, $eWaterMeter, new WaterMeterUi()->getActions($eWaterMeter));
		} else {
			$h = '';
		}

		if($eWaterMeter['eWaterReadingLast']->empty()) {

			$h .= '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucun relevé pour l'instant").'</h3>';
				$h .= '<p>'.s("La consommation se compte entre deux relevés, elle s'affichera dès le deuxième.").'</p>';
			$h .= '</div>';

			return $h;

		}

		if($eWaterMeter['eWaterReadingPrevious']->empty()) {

			$h .= '<div class="util-block-empty">';
				$h .= '<h3>'.s("Un seul relevé pour l'instant").'</h3>';
				$h .= '<p>'.s("La consommation s'affichera dès le prochain relevé.").'</p>';
			$h .= '</div>';

		} else {
			$h .= $this->getFigures($eWaterMeter);
			$h .= $this->getChart($this->getChartYear($eWaterMeter['years'][\Clock::year()] ?? []), $eWaterMeter['years']);
		}

		$h .= '<h3>'.s("Relevés").'</h3>';
		$h .= '<div class="util-deck util-deck-open">';
			$h .= '<div class="util-deck-fold">'.$this->getRows($eWaterMeter).'</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les chiffres d'un compteur qui a au moins deux relevés : ce qui a été consommé depuis le relevé
	 * d'avant, depuis le début de l'année, et l'an passé à la même date.
	 *
	 * Un compteur dont tous les relevés sont de cette année compte depuis le premier : avec deux
	 * relevés, ce chiffre est celui du dernier relevé, et ne se dit qu'une fois.
	 */
	protected function getFigures(WaterMeter $eWaterMeter): string {

		$figures = [self::getFigure($eWaterMeter['eWaterReadingLast']['consumption'], self::getSince($eWaterMeter['eWaterReadingPrevious']['date']))];

		if($eWaterMeter['year'] !== NULL and $eWaterMeter['yearSince'] !== $eWaterMeter['eWaterReadingPrevious']['date']) {
			$figures[] = self::getFigure($eWaterMeter['year'], self::getSince($eWaterMeter['yearSince']));
		}

		if($eWaterMeter['yearPrevious'] !== NULL) {
			$figures[] = self::getFigure($eWaterMeter['yearPrevious'], s("En {value} à la même date", \Clock::year() - 1));
		}

		return \main\DesignUi::getFigures($figures, 'rain-figures');

	}

	protected static function getFigure(float $value, string $label): string {

		return \main\DesignUi::getFigure(self::getNumber($value).' <small>'.s("m³").'</small>', $label);

	}

	/**
	 * Les relevés du plus récent au plus ancien, dans les colonnes des relevés de pluie (`rain-rows`) :
	 * le jour, ce qui a été consommé depuis le relevé d'avant, puis ce que le compteur affichait. Le
	 * premier relevé n'a rien consommé. Un relevé plus bas que le précédent est un compteur qui a fait
	 * le tour, et le dit (arbitrage Q2 C).
	 *
	 * Le jour d'un changement de compteur le dit à la même place, avec ce que l'ancien a consommé
	 * jusqu'à sa dépose quand elle a été relevée, puis les deux compteurs : où l'ancien s'est arrêté, d'où
	 * le nouveau repart.
	 *
	 * Un écart au-delà du seuil d'alerte (`WaterReadingLib::isAlert()`) se signale à qui peut le corriger,
	 * avec les deux corrections sous la ligne (retour du 02/10). L'observateur, qui ne peut
	 * rien corriger, lit la ligne telle qu'elle est comptée.
	 */
	protected function getRows(WaterMeter $eWaterMeter): string {

		$h = '<div class="rain-rows water-rows">';

			foreach($eWaterMeter['cWaterReading']->reverse() as $eWaterReading) {

				$write = $eWaterReading->assertUpdate();
				$alert = ($write and $eWaterReading['alert']);

				$h .= '<div class="util-deck-row rain-row">';
					$h .= '<span class="rain-row-day">'.self::getDay($eWaterReading['date']).'</span>';
					// Le premier relevé n'a pas de consommation, et pas de cellule vide non plus : sur un
					// téléphone, où le compteur passe sous la consommation, il prend sa place.
					if($eWaterReading['changed']) {

						$h .= '<span class="water-row-consumption">';
							if($eWaterReading['consumption'] !== NULL and $eWaterReading['changedValue'] !== NULL) {
								$h .= '<span class="util-unit">'.s("+ {value} m³", self::getNumber($eWaterReading['consumption'])).'</span>';
							}
							$h .= '<span class="badge badge-neutral">'.s("Changement de compteur").'</span>';
							if($alert) {
								$h .= self::getAlert($eWaterReading);
							}
						$h .= '</span>';

						// Deux mesures insécables et non une : à 360 px la seconde passe sous la première, là
						// où une seule élargirait la grille au-delà de l'écran.
						$h .= '<span class="color-muted water-row-value water-row-change">';
							if($eWaterReading['changedValue'] !== NULL) {
								$h .= '<span class="util-unit">'.s("ancien compteur à {value}", self::getNumber($eWaterReading['changedValue'])).'</span>';
							}
							$h .= '<span class="util-unit">'.s("nouveau compteur à {value}", self::getNumber($eWaterReading['value'])).'</span>';
						$h .= '</span>';

					} else {

						if($eWaterReading['consumption'] !== NULL) {
							$h .= '<span class="water-row-consumption">';
								$h .= '<span class="util-unit">'.s("+ {value} m³", self::getNumber($eWaterReading['consumption'])).'</span>';
								// Un écart signalé n'est pas présenté comme un tour : c'est ce dont on doute.
								if($alert) {
									$h .= self::getAlert($eWaterReading);
								} else if($eWaterReading['turned']) {
									$h .= '<span class="badge badge-neutral">'.s("Tour du compteur").'</span>';
								}
							$h .= '</span>';
						}
						$h .= '<span class="util-unit color-muted water-row-value">'.s("compteur à {value}", self::getNumber($eWaterReading['value'])).'</span>';

					}
					if($write) {
						$h .= '<a href="/toolbox/waterReading:update?id='.$eWaterReading['id'].'" class="btn btn-only-icon btn-sm btn-color-primary rain-row-action" title="'.s("Modifier ce relevé").'">'.\Asset::icon('gear-fill').'</a>';
					}
					if($alert) {
						$h .= $this->getAlertActions($eWaterReading);
					}
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected static function getAlert(WaterReading $eWaterReading): string {
		return '<span class="badge badge-warning">'.s("Écart de plus de {value} m³", self::getNumber($eWaterReading['alertLimit'])).'</span>';
	}

	/**
	 * Les deux corrections d'un écart signalé. « Nouveau compteur installé » déclare le changement au
	 * jour du relevé, ce que le compteur affichait devenant le premier relevé du nouveau ; un jour de
	 * changement, seule la correction des valeurs a un sens.
	 */
	protected function getAlertActions(WaterReading $eWaterReading): string {

		$h = '<div class="water-row-alert">';
			if($eWaterReading['changed'] === FALSE) {
				$h .= '<a href="/toolbox/waterMeter:change?id='.$eWaterReading['meter']['id'].'&reading='.$eWaterReading['id'].'" class="btn btn-sm btn-outline-primary">'.s("Nouveau compteur installé").'</a>';
			}
			$h .= '<a href="/toolbox/waterReading:update?id='.$eWaterReading['id'].'" class="btn btn-sm btn-outline-primary">'.s("Corriger le relevé").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les courbes, sous les chiffres : les m³ consommés depuis le début de l'année, un point par relevé
	 * et jamais de barres par mois, rien ne garantissant une saisie régulière (retour du
	 * 01/10). `$chart` est la courbe de l'année en cours, vide quand elle n'a pas deux points, et
	 * `$years` les courbes de `WaterReadingLib::getYears()`, d'un compteur ou de tous.
	 *
	 * Dès la deuxième année relevée, une bascule ajoute la comparaison entre les années, comme la
	 * pluviométrie (`RainUi::getChart()`) : sans id, pour que chaque compteur rouvre sur « Cette année ».
	 */
	protected function getChart(string $chart, array $years): string {

		if($years === []) {
			return '';
		}

		\Asset::lib('main', 'chart-4.5.1.umd.min.js');

		$h = '<h3>'.s("Évolution de la consommation").'</h3>';

		// Un compteur qui n'a pas été relevé cette année n'a que ses années passées à montrer.
		if($chart === '') {
			return $h.$this->getChartYears($years);
		}

		if(count($years) === 1) {
			return $h.$chart;
		}

		$h .= '<div class="tabs tabs-h" '.attr('onrender', 'Lime.Tab.restore(this, "water-year")').'>';

			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item selected" data-tab="water-year" onclick="Lime.Tab.select(this)">'.s("Cette année").'</a>';
				$h .= '<a class="tab-item" data-tab="water-years" onclick="Lime.Tab.select(this)">'.s("Toutes les années").'</a>';
			$h .= '</div>';

			$h .= '<div class="tab-panel selected" data-tab="water-year">'.$chart.'</div>';
			$h .= '<div class="tab-panel" data-tab="water-years">'.$this->getChartYears($years).'</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'année en cours d'un compteur, sur la courbe du carnet du matériel (`UtilChart.createTimeLine()`),
	 * mais sur un axe du 1er janvier au 31 décembre, comme celui du total.
	 */
	protected function getChartYear(array $points): string {

		if($points === []) {
			return '';
		}

		$h = '<div class="analyze-bar mb-2">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createTimeLine(this, '.json_encode(array_values($points)).', '.json_encode(array_keys($points)).', '.json_encode(s("m³")).', '.json_encode(self::getYearPeriod()).')').'</canvas>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'année en cours de l'onglet « Total » : la somme, puis chaque compteur sur le même axe de temps,
	 * du 1er janvier au 31 décembre même quand les relevés s'arrêtent avant (retour du 03/10).
	 */
	protected function getChartTotal(array $waters): string {

		$year = \Clock::year();

		if(array_key_exists($year, $waters['years']) === FALSE) {
			return '';
		}

		$series = [self::getSerie(s("Total"), $waters['years'][$year])];

		foreach($waters['cWaterMeter'] as $eWaterMeter) {

			if(array_key_exists($year, $eWaterMeter['years'])) {
				$series[] = self::getSerie($eWaterMeter['name'], $eWaterMeter['years'][$year]);
			}

		}

		$h = '<div class="analyze-bar mb-2">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createTimeLines(this, '.json_encode($series).', '.json_encode(s("m³")).', false, '.json_encode(self::getYearPeriod()).')').'</canvas>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une courbe par année, la plus récente d'abord, chacune nommée par son année et par ce qu'elle a
	 * compté à son dernier point, toutes sur un même axe du 1er janvier au 31 décembre.
	 */
	protected function getChartYears(array $years): string {

		krsort($years);

		$series = [];

		foreach($years as $year => $points) {

			$series[] = self::getSerie(s("{year} · {value} m³", ['year' => $year, 'value' => self::getNumber(end($points))]), $points);
		}

		$h = '<div class="analyze-bar mb-2">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createTimeLines(this, '.json_encode($series).', '.json_encode(s("m³")).', true)').'</canvas>';
		$h .= '</div>';

		return $h;

	}

	protected static function getYearPeriod(): array {

		$year = \Clock::year();

		return [$year.'-01-01', $year.'-12-31'];

	}

	protected static function getSerie(string $label, array $points): array {

		return [
			'label' => $label,
			'dates' => array_keys($points),
			'values' => array_values($points)
		];

	}

	/**
	 * Un jour sans son année tant qu'il est de l'année en cours, comme les relevés de pluie.
	 */
	public static function getDay(string $date): string {

		$format = (substr($date, 0, 4) === (string)\Clock::year()) ? \util\DateUi::DAY_MONTH : \util\DateUi::DATE;

		return \util\DateUi::textual($date, $format);

	}

	protected static function getSince(string $date): string {
		return s("Depuis le {value}", self::getDay($date));
	}

	public static function getM3(float $value): string {
		return '<span class="util-unit">'.s("{value} m³", self::getNumber($value)).'</span>';
	}

	/**
	 * Des m³ entiers quand le chiffre est rond, le dixième sinon, la règle des mm de pluie
	 * (`RainUi::getNumber()`) : la même écriture d'un écran à l'autre.
	 */
	public static function getNumber(float $value): string {

		$value = round($value, 1);

		return \util\TextUi::number($value, ($value === round($value)) ? 0 : 1);

	}

	/**
	 * La rangée d'un compteur d'eau à relever au planning (alertes lot 2, Q1 B), sœur de celle d'un
	 * compteur du carnet (`EquipmentReadingUi::getDue()`) : le badge, le compteur vers son onglet et
	 * son dernier relevé, puis « Relever », le décalage à la semaine suivante et la croix qui passe ce
	 * relevé (`WaterMeterLib::skipReading()`), que l'observateur n'a pas. `$week` est la semaine
	 * affichée, `$origin` la forme du planning.
	 */
	public function getDue(\structure\Structure $eStructure, WaterMeter $eWaterMeter, string $week, string $origin, bool $write): string {

		$eWaterReadingLast = $eWaterMeter['eWaterReadingLast'];

		if($eWaterReadingLast->empty()) {
			$last = s("Jamais relevé");
		} else {
			$last = s("Dernier relevé {value} le {date}", [
				'value' => self::getM3($eWaterReadingLast['value']),
				'date' => self::getDay($eWaterReadingLast['date'])
			]);
		}

		$gestures = [];

		if($write) {
			$gestures[] = '<a href="/toolbox/waterReading:create?structure='.$eStructure['id'].'&meter='.$eWaterMeter['id'].'&'.\planning\DueUi::getOrigin($week, $origin).'" class="'.\planning\DueUi::getGestureClass($origin, TRUE).'">'.\Asset::icon('pencil').' '.s("Relever").'</a>';
			$gestures[] = \planning\DueUi::getPostpone('/toolbox/waterMeter:doPostponeReading', $eWaterMeter['id'], $week, $origin);
			$gestures[] = \planning\DueUi::getSkip('/toolbox/waterMeter:doSkipReading', $eWaterMeter['id'], $week, $origin, s("Passer ce relevé"));
		}

		return MaintenanceUi::getDueItem(
			$origin,
			'<span class="badge badge-neutral">'.\Asset::icon('droplet-half').' '.s("Eau").'</span>',
			'<a href="'.self::urlMeter($eStructure, $eWaterMeter).'">'.WaterMeterUi::getName($eWaterMeter).'</a>',
			$last,
			$gestures
		);

	}

	/**
	 * Le relevé d'un compteur d'eau hors de son écran, depuis sa rangée du planning : le compteur est
	 * connu, il reste le jour et ce qu'il affiche, comme sur la ligne de saisie (`getQuick()`).
	 */
	public function create(WaterReading $eWaterReading): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/waterReading:doCreate', ['id' => 'water-reading-create']);

			$h .= $form->hidden('structure', $eWaterReading['structure']);
			$h .= $form->hidden('meter', $eWaterReading['meter']);
			$h .= $form->hidden('origin', GET('origin'));
			$h .= $form->hidden('week', GET('week'));
			$h .= $form->group(s("Compteur"), WaterMeterUi::getName($eWaterReading['meter']));
			$h .= $form->dynamicGroup($eWaterReading, 'date');
			$h .= $form->dynamicGroup($eWaterReading, 'value');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-water-reading-create',
			title: s("Relever le compteur"),
			body: $h
		);

	}

	/**
	 * Un relevé se corrige sur son jour : seul ce que le compteur affichait change, et la consommation
	 * des relevés qui l'entourent, comme la marque du tour du compteur, suivent d'elles-mêmes.
	 *
	 * Le relevé d'un changement de compteur corrige ses deux valeurs, la dépose et la pose. Le
	 * supprimer retire le changement : les relevés suivants se comptent de nouveau depuis l'ancien.
	 */
	public function update(WaterReading $eWaterReading): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/waterReading:doUpdate', ['id' => 'water-reading-update']);

			$h .= $form->hidden('id', $eWaterReading);

			$h .= $form->group(s("Compteur"), WaterMeterUi::getName($eWaterReading['meter']));
			$h .= $form->group(s("Date"), \util\DateUi::textual($eWaterReading['date'], \util\DateUi::DATE));

			if($eWaterReading['changed']) {
				$h .= $form->dynamicGroup($eWaterReading, 'changedValue');
				$h .= $form->dynamicGroup($eWaterReading, 'value', self::describeChangeValue(...));
				$confirm = s("Supprimer ce changement de compteur ? Les relevés suivants se compteront depuis l'ancien compteur.");
			} else {
				$h .= $form->dynamicGroup($eWaterReading, 'value');
				$confirm = s("Supprimer ce relevé ?");
			}

			$h .= $form->group(
				content: $form->submit(s("Enregistrer")).' '.
					'<a data-ajax="/toolbox/waterReading:doDelete" post-id="'.$eWaterReading['id'].'" class="btn btn-outline-danger" data-confirm="'.$confirm.'">'.s("Supprimer").'</a>'
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-water-reading-update',
			title: $eWaterReading['changed'] ? s("Modifier un changement de compteur") : s("Modifier un relevé"),
			body: $h
		);

	}

	/**
	 * Déclarer un nouveau compteur installé, depuis la roue du compteur ou depuis un écart signalé : le jour, ce que l'ancien affichait à la dépose
	 * si on l'a relevé, ce que le nouveau affiche à la pose, et son nombre de chiffres, qui est celui
	 * de l'ancien tant qu'on ne le change pas.
	 */
	public function createChange(WaterMeter $eWaterMeter, WaterReading $eWaterReading): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/waterMeter:doChange', ['id' => 'water-meter-change']);

			$h .= $form->hidden('id', $eWaterMeter);

			$h .= $form->dynamicGroup($eWaterReading, 'date', function(\PropertyDescriber $d) {
				$d->label = s("Date du changement");
			});
			$h .= $form->dynamicGroup($eWaterReading, 'changedValue');
			$h .= $form->dynamicGroup($eWaterReading, 'value', self::describeChangeValue(...));
			$h .= $form->dynamicGroup($eWaterMeter, 'digits');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer le nouveau compteur"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-water-meter-change',
			title: s("Nouveau compteur installé"),
			body: $h
		);

	}

	// Le relevé d'un jour de changement est ce que le nouveau compteur affiche à sa pose.
	protected static function describeChangeValue(\PropertyDescriber $d): void {
		$d->label = s("Premier relevé du nouveau compteur");
	}

	public static function p(string $property): \PropertyDescriber {

		$d = WaterReading::model()->describer($property, [
			'meter' => s("Compteur"),
			'date' => s("Date"),
			'value' => s("Relevé du compteur"),
			'changedValue' => s("Dernier relevé de l'ancien compteur"),
		]);

		switch($property) {

			case 'date' :
				$d->attributes = [
					'max' => \Clock::date()
				];
				break;

			case 'value' :
			case 'changedValue' :
				$d->append = s("m³");
				$d->attributes = [
					'step' => 0.1,
					'min' => 0
				];
				break;

		}

		return $d;

	}

}
?>
