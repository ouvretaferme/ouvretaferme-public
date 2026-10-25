<?php
namespace toolbox;

/**
 * L'écran Pluviométrie de la Boîte à outils (planches pf-l8-v2, pf-l8-v3 et pf-l8-v4).
 *
 * Trois états : aucun pluviomètre, et l'écran accueille (arbitrage Q18 A) ; un pluviomètre,
 * affiché directement (Q1 B) ; plusieurs, une carte `util-deck` par pluviomètre, qui s'étend sur
 * place avec sa porte « Nouveau relevé » et ses onglets Cumul et Relevés (Q9 B). Un pluviomètre qui
 * attend son premier relevé ne montre que sa ligne « Il a plu ? » (réponse (c) de M-Prod du 23/09).
 */
class RainUi {

	public function __construct() {

		\Asset::css('main', 'chart.css');
		\Asset::css('toolbox', 'toolbox.css');
		\Asset::js('main', 'chart.js');
		\Asset::js('toolbox', 'rain.js');

	}

	public static function url(\structure\Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/outils/pluviometrie';
	}

	/**
	 * Un pluviomètre sur la page, son onglet ouvert (ligne du tableau de la Boîte à outils, arbitrage
	 * Q19 A de la planche pf-l8-v7).
	 */
	public static function urlGauge(\structure\Structure $eStructure, Gauge $eGauge): string {
		return self::url($eStructure).'?gauge='.$eGauge['id'];
	}

	/**
	 * Sans pluviomètre désigné, le formulaire propose de le choisir.
	 */
	public static function urlCreate(\structure\Structure $eStructure, Gauge $eGauge = new Gauge()): string {
		return '/toolbox/rain:create?structure='.$eStructure['id'].($eGauge->notEmpty() ? '&gauge='.$eGauge['id'] : '');
	}

	/**
	 * « Nouveau pluviomètre » pose un pluviomètre ailleurs (retour du 23/09), le relevé du
	 * jour restant la ligne « Il a plu ? » ou la porte de chaque carte. Il reste dans la roue quand la
	 * ferme et chaque parcelle de la saison ont déjà le leur (`$cZoneFree` vide, retour du
	 * 02/10) : son panneau le dit alors et mène à la cartographie (`GaugeUi::create()`). Sans
	 * pluviomètre, l'accueil porte déjà le bouton.
	 *
	 * L'export des relevés y vit aussi, pour qui gère la structure et dès qu'il y a un relevé (retour
	 * du 02/10, « Ajouter Télécharger aussi dans la pluvio »). Comme l'eau, la pluviométrie
	 * fait exception à la ligne de gestes sous les onglets du `CLAUDE.md` du package.
	 */
	public function getHeader(\structure\Structure $eStructure, \Collection $cGauge, \Collection $cZoneFree, \Collection $cFavorite): string {

		$menu = '';

		if($eStructure->canPlanning() and $cGauge->notEmpty()) {
			$menu .= GaugeUi::getCreate($eStructure, $cZoneFree, s("Nouveau pluviomètre"), 'dropdown-item');
		}

		if($eStructure->canManage() and $cGauge->find(fn(Gauge $eGauge) => $eGauge['lastDate'] !== NULL)->notEmpty()) {
			$menu .= '<a href="/toolbox/rain:exportCsv?id='.$eStructure['id'].'" class="dropdown-item" data-ajax-download data-waiter>'.\Asset::icon('download').' '.s("Télécharger les relevés").'</a>';
		}

		return new ToolUi()->getHeader($eStructure, Favorite::RAIN, $cFavorite, menu: $menu);

	}

	public function getExportHeader(): array {

		return [
			'gauge',
			'place',
			'date',
			'rain',
		];

	}

	public static function getExportFilename(): string {
		return 'pluviometrie-'.\Clock::date().'.csv';
	}

	/**
	 * La saisie du jour, sur la page (arbitrage Q13 B) : le pluviomètre est connu, il ne reste que la
	 * date et les mm. Le panneau « Nouveau relevé » garde le choix du pluviomètre et la relecture du
	 * jour.
	 */
	public function getQuick(\structure\Structure $eStructure, Gauge $eGauge, string $actions = ''): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/rain:doCreate', ['id' => 'rain-quick-'.$eGauge['id'], 'class' => 'rain-quick']);
			$h .= $form->hidden('structure', $eStructure);
			$h .= $form->hidden('gauge', $eGauge);
			$h .= '<div class="rain-quick-label">'.\Asset::icon('cloud-rain').' '.s("Il a plu ?").'</div>';
			$h .= $form->date('date', \Clock::date(), ['max' => \Clock::date()]);
			$h .= $form->inputGroup($form->number('rain', NULL, ['step' => 0.1, 'placeholder' => '0']).$form->addon(s("mm")));
			// Le bouton et la roue crantée passent à la ligne ensemble : sur un écran étroit, la roue ne
			// reste jamais seule sur la sienne.
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
	 * L'accueil d'une structure sans pluviomètre (arbitrage Q18 A de la planche pf-l8-v6) : une vue
	 * d'ensemble de l'outil, puis ce qu'il donnera une fois des relevés notés (« utilise les éléments
	 * de vous y trouverez du B »), puis le geste, qui pose le pluviomètre (réponse (a) de
	 * M-Prod du 23/09).
	 */
	public function getStart(\structure\Structure $eStructure, \Collection $cZoneFree): string {

		$promises = [
			['droplet-fill', s("Le cumul de l'année"), s("et sur les 30 derniers jours")],
			['graph-up', s("Comparer à l'année précédente"), s("dès la deuxième année")],
		];

		$content = '<p>'.s("Notez la pluviométrie sur la ferme ou sur une parcelle, et suivez vos relevés jour après jour.").'</p>';
		$content .= new ToolUi()->getPromises($promises);

		return new \main\DesignUi()->getStart(
			\Asset::icon('cloud-rain'),
			s("La pluie sur la ferme"),
			$content,
			image: \Asset::image('main', 'friends/grenouille-verte.svg'),
			buttons: $eStructure->canPlanning() ? GaugeUi::getCreate($eStructure, $cZoneFree, s("Poser un pluviomètre"), 'btn btn-primary btn-xl') : NULL
		);

	}

	/**
	 * Un seul pluviomètre : l'écran le montre tel quel, ses relevés sous le cumul pour que la roue
	 * crantée d'un jour reste à portée.
	 */
	public function getOne(\structure\Structure $eStructure, Gauge $eGauge): string {

		$h = '';

		// Seul, un pluviomètre de la ferme n'a pas à le dire : « La ferme » en titre ne se lit pas
		// comme un lieu (retour du 23/09). Une parcelle, si.
		if($eGauge['zone']->notEmpty()) {
			$h .= '<p class="color-muted">'.GaugeUi::getName($eGauge).'</p>';
		}

		$h .= $this->getContent($eStructure, $eGauge);

		return $h;

	}

	/**
	 * Un onglet par pluviomètre (retour du 24/09), toujours dans le même ordre, celui de
	 * leur création (`GaugeLib::getByStructure()`) : l'onglet d'un pluviomètre ne change pas de place
	 * d'une visite à l'autre. L'onglet ouvert est celui que la Boîte désigne (`$eGaugeSelected`), sinon
	 * le dernier ouvert, que `Lime.Tab` retient par l'id de la barre. `gauge` sort de l'adresse une fois
	 * l'onglet ouvert : le rechargement qui suit un relevé rouvrirait sinon celui de la Boîte.
	 */
	public function getSeveral(\structure\Structure $eStructure, \Collection $cGauge, Gauge $eGaugeSelected): string {

		$forceTab = $eGaugeSelected->notEmpty() ? 'rain-gauge-'.$eGaugeSelected['id'] : NULL;

		$tabs = '';
		$panels = '';

		foreach($cGauge as $eGauge) {

			$tabs .= '<a class="tab-item" data-tab="rain-gauge-'.$eGauge['id'].'" onclick="Lime.Tab.select(this)">'.GaugeUi::getName($eGauge).'</a>';

			$panels .= '<div class="tab-panel" data-tab="rain-gauge-'.$eGauge['id'].'">';
				$panels .= $this->getContent($eStructure, $eGauge);
			$panels .= '</div>';

		}

		return \main\DesignUi::getPageTabs($tabs, panels: $panels, attributes: [
			'id' => 'rain-gauges',
			'onrender' => 'Lime.Tab.restore(this, "rain-gauge-'.$cGauge->first()['id'].'", '.json_encode($forceTab).'); history.removeArgument("gauge")',
		], fold: TRUE);

	}

	/**
	 * La roue crantée du pluviomètre ferme la ligne « Il a plu ? » plutôt que d'occuper la sienne
	 * (retour du 24/09). Sans le droit d'écrire (`Gauge::assertUpdate()`, le droit du
	 * planning), ni l'une ni l'autre : un observateur lit les cumuls et les relevés (retour
	 * du 29/09, « un observateur peur agir en écriture »).
	 */
	protected function getContent(\structure\Structure $eStructure, Gauge $eGauge): string {

		if($eGauge->assertUpdate()) {
			$h = $this->getQuick($eStructure, $eGauge, new GaugeUi()->getActions($eGauge));
		} else {
			$h = '';
		}

		// Avant le premier relevé, l'écran n'a rien d'autre à montrer que la ligne « Il a plu ? » : le
		// constat prend la place des cumuls et des relevés (retour du 23/09, « util block
		// empty ? »).
		if($eGauge['lastDate'] === NULL) {

			$h .= '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucun relevé pour l'instant").'</h3>';
				$h .= '<p>'.s("Le cumul de l'année et celui des 30 derniers jours s'afficheront dès le premier relevé.").'</p>';
			$h .= '</div>';

		} else {

			$h .= $this->getCumulative($eGauge);

			$h .= '<h3>'.s("Relevés").'</h3>';
			$h .= '<div class="util-deck util-deck-open">';
				$h .= '<div class="util-deck-fold">'.$this->getRows($eGauge).'</div>';
			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * L'âge se compte en jours et non en heures, comme le ferait DateUi::ago() : un relevé est daté
	 * d'un jour, et celui du matin n'a pas « 14 heures » le soir.
	 */
	protected static function getAge(string $date): string {

		$days = (int)round((strtotime(\Clock::date()) - strtotime($date)) / 86400);

		if($days === 0) {
			return s("aujourd'hui");
		} else if($days === 1) {
			return s("hier");
		} else {
			return s("il y a {value} jours", $days);
		}

	}

	protected function getCumulative(Gauge $eGauge): string {

		$year = \Clock::year();

		// Le dernier relevé ouvre les chiffres (retour du 23/09, « les mm du jour dans les
		// panneaux bleus ») : son âge lui sert de libellé, « Aujourd'hui » le jour même.
		$h = '<div class="util-figures rain-figures">';
			$h .= '<div class="util-figure" title="'.\util\DateUi::textual($eGauge['lastDate'], \util\DateUi::DATE).'">';
				$h .= '<div class="util-figure-value">'.self::getFigure($eGauge['lastRain']).'</div>';
				$h .= '<div class="util-figure-label">'.ucfirst(self::getAge($eGauge['lastDate'])).'</div>';
			$h .= '</div>';
			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.self::getFigure($eGauge['cumulative']).'</div>';
				$h .= '<div class="util-figure-label">'.s("Depuis le 1er janvier").'</div>';
			$h .= '</div>';
			if($eGauge['cumulativePrevious'] > 0) {
				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-value">'.self::getFigure($eGauge['cumulativePrevious']).'</div>';
					$h .= '<div class="util-figure-label">'.s("En {value} à la même date", $year - 1).'</div>';
				$h .= '</div>';
			}
		$h .= '</div>';

		$h .= $this->getChart($eGauge);

		return $h;

	}

	/**
	 * Deux lectures du même relevé (retours du 23/09) : l'année en cours contre la précédente, mois
	 * par mois, puis toutes les années en cumul. Le wrapper n'a pas d'id : chaque pluviomètre d'une
	 * ferme à plusieurs jauges rouvre sur « Cette année », plutôt que sur l'onglet retenu par un autre.
	 */
	protected function getChart(Gauge $eGauge): string {

		\Asset::lib('main', 'chart-4.5.1.umd.min.js');

		// Une seule année relevée n'a rien à comparer : « Toutes les années » redirait « Cette année »
		// (retour du 23/09).
		if(count($eGauge['years']) <= 1) {
			return $this->getChartYear($eGauge);
		}

		// Une bascule entre deux vues du graphique, et non un second niveau d'onglets : `tabs-sm`, pour
		// ne pas se lire comme la barre des pluviomètres au-dessus (design system, `tabs-sub`).
		$h = '<div class="tabs tabs-h" '.attr('onrender', 'Lime.Tab.restore(this, "rain-year")').'>';

			$h .= '<div class="tabs-item">';
				$h .= '<a class="tab-item selected" data-tab="rain-year" onclick="Lime.Tab.select(this)">'.s("Cette année").'</a>';
				$h .= '<a class="tab-item" data-tab="rain-years" onclick="Lime.Tab.select(this)">'.s("Toutes les années").'</a>';
			$h .= '</div>';

			$h .= '<div class="tab-panel selected" data-tab="rain-year">'.$this->getChartYear($eGauge).'</div>';
			$h .= '<div class="tab-panel" data-tab="rain-years">'.$this->getChartYears($eGauge).'</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getChartYear(Gauge $eGauge): string {

		$year = \Clock::year();

		$months = self::getMonths($eGauge, $year);
		$monthsPrevious = self::getMonths($eGauge, $year - 1);

		$h = '<div class="analyze-bar">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createDoubleBar(this, '.json_encode(s("Pluie {value}", $year)).', '.json_encode($months).', '.json_encode(s("Pluie {value}", $year - 1)).', '.json_encode($monthsPrevious).', '.json_encode(self::getMonthLabels()).', "mm")').'</canvas>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une courbe de cumul par année relevée (arbitrage Q14 B) : l'année en cours s'arrête au mois
	 * courant, et chaque légende porte les jours relevés, pour qu'une année peu notée ne se lise pas
	 * comme une année sèche.
	 */
	protected function getChartYears(Gauge $eGauge): string {

		$currentMonth = (int)substr(\Clock::date(), 5, 2);

		$series = [];

		foreach($eGauge['years'] as $year => $data) {

			$values = [];
			$sum = 0.0;

			foreach($data['months'] as $month => $rain) {
				$sum += $rain;
				$values[] = ($year === \Clock::year() and $month > $currentMonth) ? NULL : round($sum, 1);
			}

			$series[] = [
				'label' => p("{year} · {total} mm sur {value} jour", "{year} · {total} mm sur {value} jours", $data['days'], ['year' => $year, 'total' => self::getNumber($data['total'])]),
				'values' => $values
			];

		}

		$h = '<div class="analyze-bar">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createLines(this, '.json_encode($series).', '.json_encode(self::getMonthLabels()).', "mm")').'</canvas>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une année sans relevé n'a pas d'entrée dans years, pour ne pas devenir une courbe plate sur
	 * « Toutes les années » ; l'histogramme, lui, compare toujours deux années, donc la remplit de zéros.
	 */
	protected static function getMonths(Gauge $eGauge, int $year): array {

		if(array_key_exists($year, $eGauge['years'])) {
			return array_map(fn(float $rain) => round($rain, 1), array_values($eGauge['years'][$year]['months']));
		} else {
			return array_fill(0, 12, 0.0);
		}

	}

	protected static function getMonthLabels(): array {
		return array_map(fn(int $month) => \util\DateUi::getMonthName($month, type: 'short'), range(1, 12));
	}

	protected static function getFigure(float $value): string {
		return self::getNumber($value).' <small>'.s("mm").'</small>';
	}

	/**
	 * Les rangées partagent les colonnes de la liste (retours du 23/09, « des colonnes bien
	 * séparées ») : chacune prend la largeur de son contenu le plus large, d'une rangée à l'autre.
	 */
	protected function getRows(Gauge $eGauge): string {

		$h = '<div class="rain-rows">';

			foreach($eGauge['cRain'] as $eRain) {

				$format = (substr($eRain['date'], 0, 4) === (string)\Clock::year()) ? \util\DateUi::DAY_MONTH : \util\DateUi::DATE;

				$h .= '<div class="util-deck-row rain-row">';
					$h .= '<span class="rain-row-day">'.\util\DateUi::textual($eRain['date'], $format).'</span>';
					$h .= self::getDrops($eRain['rain']);
					$h .= self::getMm($eRain['rain']);
					if($eGauge->assertUpdate()) {
						$h .= '<a href="/toolbox/rain:update?id='.$eRain['id'].'" class="btn btn-only-icon btn-sm btn-color-primary rain-row-action" title="'.s("Modifier ce relevé").'">'.\Asset::icon('gear-fill').'</a>';
					}
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une, deux ou trois gouttes selon la quantité (arbitrage Q16 A). Les paliers viennent de la
	 * distribution réelle d'une ferme (122 / 102 / 59 jours sur 283) et de ce que la pluie fait à la
	 * culture : sous 5 mm elle mouille la surface, de 5 à 15 elle arrose, au-delà elle abreuve.
	 */
	public static function getDrops(float $rain): string {

		if($rain < 5) {
			$drops = 1;
		} else if($rain < 15) {
			$drops = 2;
		} else {
			$drops = 3;
		}

		return '<span class="rain-row-drops rain-row-drops-'.$drops.'">'.str_repeat(\Asset::icon('droplet-fill'), $drops).'</span>';

	}

	public static function getMm(float $value): string {
		return '<span class="util-unit">'.self::getNumber($value).' '.s("mm").'</span>';
	}

	/**
	 * Des mm entiers quand la pluie est ronde (« des entiers pour les relevés », 23/09), le
	 * dixième sinon : la saisie l'accepte, et 8,5 mm relevés ne doivent pas faire un cumul de 9 mm.
	 * La même règle partout, pour qu'un chiffre ne change pas d'écriture d'un écran à l'autre.
	 */
	protected static function getNumber(float $value): string {

		$value = round($value, 1);

		return \util\TextUi::number($value, ($value === round($value)) ? 0 : 1);

	}

	/**
	 * Le relevé, sur l'un des pluviomètres actifs `$cGauge`. La porte d'une carte désigne le sien ;
	 * une structure qui n'en a qu'un y relève sans rien choisir.
	 */
	public function create(Rain $eRain, \Collection $cGauge, float $day): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/rain:doCreate', ['id' => 'rain-create']);

			$h .= $form->hidden('structure', $eRain['structure']);

			if($cGauge->count() === 1) {
				$h .= $form->hidden('gauge', $eRain['gauge']);
				$h .= $form->group(s("Pluviomètre"), GaugeUi::getName($eRain['gauge']));
			} else {
				$eRain['cGauge'] = $cGauge;
				$h .= $form->dynamicGroup($eRain, 'gauge');
			}

			$h .= $form->dynamicGroup($eRain, 'date');
			$h .= $this->getAmount($eRain, $day);

			$h .= $form->group(
				content: $form->submit(s("Ajouter la pluie"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-rain-create',
			title: s("Nouveau relevé"),
			body: $h
		);

	}

	/**
	 * Ce que le jour porte déjà, puis la pluie à y ajouter : relu quand la date ou le pluviomètre
	 * change (`ToolboxRain.refreshDay()`), la pluie déjà saisie comprise.
	 */
	public function getAmount(Rain $eRain, float $day): string {

		$form = new \util\FormUi();

		$h = '<div id="rain-create-amount">';

			if($day > 0) {

				$h .= $form->group(s("Déjà ce jour-là"), self::getMm($day));
				$h .= $form->dynamicGroup($eRain, 'rain', function(\PropertyDescriber $d) use($day) {
					$d->label = s("Pluie à ajouter");
					$d->attributes['min'] = -$day;
				});

			} else {
				$h .= $form->dynamicGroup($eRain, 'rain');
			}

		$h .= '</div>';

		return $h;

	}

	public function update(Rain $eRain): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/rain:doUpdate', ['id' => 'rain-update']);

			$h .= $form->hidden('id', $eRain);

			$h .= $form->group(s("Pluviomètre"), GaugeUi::getName($eRain['gauge']));
			$h .= $form->group(s("Date"), \util\DateUi::textual($eRain['date'], \util\DateUi::DATE));
			$h .= $form->dynamicGroup($eRain, 'rain', function(\PropertyDescriber $d) {
				$d->label = s("Pluie ce jour-là");
			});

			$h .= $form->group(
				content: $form->submit(s("Enregistrer")).' '.
					'<a data-ajax="/toolbox/rain:doDelete" post-id="'.$eRain['id'].'" class="btn btn-outline-danger" data-confirm="'.s("Supprimer la pluie de ce jour ?").'">'.s("Supprimer").'</a>'
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-rain-update',
			title: s("Modifier un relevé"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Rain::model()->describer($property, [
			'gauge' => s("Pluviomètre"),
			'date' => s("Date"),
			'rain' => s("Pluie"),
		]);

		switch($property) {

			case 'gauge' :
				$d->field = function(\util\FormUi $form, Rain $e) {

					$values = [];

					foreach($e['cGauge'] as $eGauge) {
						$values[$eGauge['id']] = GaugeUi::getName($eGauge);
					}

					return $form->select('gauge', $values, $e['gauge'], [
						'required' => TRUE,
						'onchange' => 'ToolboxRain.refreshDay(this)'
					]);

				};
				break;

			case 'date' :
				$d->attributes = [
					'max' => \Clock::date(),
					'onchange' => 'ToolboxRain.refreshDay(this)'
				];
				break;

			case 'rain' :
				$d->append = s("mm");
				$d->attributes = [
					'step' => 0.1,
					'placeholder' => '0'
				];
				break;

		}

		return $d;

	}

}
?>
