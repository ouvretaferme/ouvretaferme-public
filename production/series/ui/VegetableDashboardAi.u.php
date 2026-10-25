<?php
namespace series;

/**
 * Le tableau de bord maraîchage d'un groupe : ce que le réseau cultive, place et travaille.
 *
 * ⚠️ **Aucune valeur n'est rattachée à une ferme, jamais.** L'écran ne montre que des sommes, des
 * parts, des médianes et des nombres de fermes par tranche : c'est la consigne d'arbitrage du
 * 16/09/2026, et c'est aussi ce qui rend l'écran lisible par l'équipe d'un réseau dont les fermes
 * ne partagent pas toutes leur plan de culture. Le Lib rend des agrégats déjà masqués sous le
 * plancher ; cette UI ne recompose rien qui puisse les défaire.
 *
 * ⚠️ **Chaque bloc porte sa population.** Trois populations différentes cohabitent sur le même
 * écran — les fermes qui ont au moins `SERIES_MINIMUM` séries, celles d'entre elles qui ont placé
 * des cultures, celles qui saisissent du temps — et un lecteur qui les confond lit le même chiffre de trois façons. La
 * ligne `util-card-info` sous chaque titre n'est pas une légende de l'interface, c'est la borne de
 * ce que la carte mesure.
 *
 * ⚠️ Toutes les pistes de l'écran sont en `--accounting` : elles mesurent des populations de fermes
 * sur trois cartes voisines, et deux teintes proches pour la même unité se liraient comme deux
 * mesures. L'écran ne porte aucun canevas, donc aucune feuille ni aucun script de graphique.
 */
class VegetableDashboardAiUi {

	public function __construct() {

		\Asset::css('series', 'vegetableDashboardAi.css');

	}

	/**
	 * La saison regardée, en déroulant.
	 *
	 * Même forme que l'écran de comparaison, dont il est le voisin de rangée : deux sélecteurs de
	 * saison à deux onglets d'intervalle doivent se manœuvrer pareil.
	 *
	 * ⚠️ Les saisons proposées sont déjà bornées par le plancher : une saison qu'une seule ferme
	 * porte n'ouvrirait qu'un écran entièrement masqué.
	 *
	 * Le filtre se pose sur la rangée des onglets, dans la bande de l'écran
	 * (`CommunityTemplate::$mainFilter`).
	 */
	public function getSeason(\structure\Structure $eStructure, array $seasons, int $season, \structure\Section $eSection = new \structure\Section()): string {

		// Une seule saison au-dessus du plancher — ou aucune — ne se choisit pas : le déroulant
		// serait une commande sans effet, et la saison se lit de toute façon sous chaque carte.
		if(count($seasons) < 2) {
			return '';
		}

		$h = '<div class="util-filter">';

			$h .= '<div class="util-filter-label">'.s("Saison").'</div>';

			$h .= '<div>';

				// util-chip sans dropdown-toggle : le jeton porte déjà son chevron en
				// background-image, quand dropdown-toggle en poserait un second en :after.
				$h .= '<a class="util-chip is-active" data-dropdown="bottom-start">'.$season.'</a>';

				$h .= '<div class="dropdown-list">';

					foreach($seasons as $seasonOption) {

						$url = \structure\StructureUi::urlCommunityVegetable($eStructure, $eSection).'?season='.$seasonOption;

						$h .= '<a href="'.$url.'" class="dropdown-item'.(($seasonOption === $season) ? ' selected' : '').'">'.$seasonOption.'</a>';

					}

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'écran entier, dans son ordre de lecture.
	 *
	 * `$hasSharing` dit si au moins une ferme du groupe ouvre son plan de culture. Il ne change
	 * aucun chiffre — le tableau de bord lit les fermes rattachées, partageuses ou non — mais il décide des
	 * liens vers la comparaison : sans une seule ferme partageuse, ils mèneraient tous à un écran
	 * vide, et une commande qui ne mène qu'à du vide est un défaut.
	 */
	public function get(\structure\Structure $eStructure, array $dashboard, bool $hasSharing, bool $hasCompare, \structure\Section $eSection = new \structure\Section()): string {

		$hFigures = $this->getFigures($dashboard);
		$hNetworkRing = $this->getNetworkRing($eStructure, $dashboard);

		// Deux cartes d'une rangée prennent la hauteur de la plus haute : les paires associent des
		// cartes de même hauteur, sans quoi un vide se creuse sous la plus courte.
		$hNetworkPlan = $this->getCardsRow([
			$this->getBin($dashboard, VegetableDashboardAiLib::AREA),
			$this->getBin($dashboard, VegetableDashboardAiLib::GREENHOUSE)
		], 'mb-2');

		$hNetworkPlan .= $this->getCardsRow([
			$this->getBin($dashboard, VegetableDashboardAiLib::BED_AREA),
			$this->getBin($dashboard, VegetableDashboardAiLib::BED_GREENHOUSE)
		], 'mb-2');

		$hNetworkPlan .= $this->getCardsRow([
			$this->getBin($dashboard, VegetableDashboardAiLib::SPECIES),
			$this->getBin($dashboard, VegetableDashboardAiLib::HARVESTED)
		], 'mb-2');

		$hNetworkPlan .= $this->getCardsRow([
			$this->getNetworkMonths($dashboard),
			$this->getSpecies($eStructure, $dashboard, $hasSharing, $eSection)
		], 'mb-2');

		$hNetworkPlan .= $this->getCalendar($eStructure, $dashboard, $hasSharing, $eSection);
		$hNetworkPlan .= $this->getHoursByCategory($dashboard);

		// Un groupe peut passer le plancher par ses seules fiches système et ses ateliers : aucun
		// plan de culture ne rend alors de chiffre, et l'écran le dit en tête au lieu de laisser une
		// carte de SAU seule sous un titre (planche production-finir-statistiques-mince, direction C).
		if($hFigures === '' and $hNetworkRing === '' and $hNetworkPlan === '') {
			return $this->getWithoutPlan($eStructure, $dashboard, $hasCompare, $eSection);
		}

		$h = $hFigures;

		$hNetwork = $this->getCardsRow([
			$hNetworkRing,
			$this->getNetworkTotalArea($dashboard)
		], 'mb-2');

		$hNetwork .= $hNetworkPlan;

		// Chaque bloc de la section peut se taire sous le plancher : son titre ne reste pas seul
		// au-dessus du vide.
		if($hNetwork !== '') {

			$h .= '<div class="util-title mt-2">';
				$h .= '<h3>'.s("Ce qui compose {the}", ['the' => \structure\StructureUi::getText($eStructure, 'the')]).'</h3>';
			$h .= '</div>';

			$h .= $hNetwork;

		}

		$h .= '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Les systèmes déclarés").'</h3>';
		$h .= '</div>';

		$h .= $this->getDeclared($dashboard, FALSE);
		$h .= $this->getLastDay($dashboard);

		return $h;

	}

	/**
	 * Le tableau de bord d'un groupe dont aucun plan de culture ne rend de chiffre : le constat en
	 * tête, au balisage du bloc vide du même onglet (`getBelowFloor()`), puis ce que les fermes ont
	 * déclaré, SAU comprise.
	 *
	 * ⚠️ Le jour où s'arrêtent les chiffres (`getLastDay()`) ne suit pas : il daterait des plans de
	 * culture dont l'écran ne montre rien.
	 */
	protected function getWithoutPlan(\structure\Structure $eStructure, array $dashboard, bool $hasCompare, \structure\Section $eSection): string {

		$farms = $dashboard['plan']['farms'];

		$text = p("Les statistiques de culture s'affichent dès que {floor} fermes ont un plan de culture d'au moins {value} série.", "Les statistiques de culture s'affichent dès que {floor} fermes ont un plan de culture d'au moins {value} séries.", VegetableDashboardAiLib::SERIES_MINIMUM, [
			'floor' => VegetableDashboardAiLib::FLOOR
		]);

		$text .= ' ';

		if($farms === 0) {
			$text .= s("En {season}, aucune ferme n'en a.", ['season' => $dashboard['season']]);
		} else {
			$text .= p("En {season}, {value} ferme en a un.", "En {season}, {value} fermes en ont un.", $farms, ['season' => $dashboard['season']]);
		}

		$h = '<div class="util-block util-block-empty">';

			$h .= '<h3>'.s("Pas encore de statistiques de culture").'</h3>';
			$h .= '<p>'.$text.'</p>';

			if($hasCompare) {
				$h .= '<div class="util-buttons"><a href="'.\structure\StructureUi::urlCommunityCompare($eStructure, $eSection).'" class="btn btn-primary">'.s("Comparer les cultures").'</a></div>';
			}

		$h .= '</div>';

		$h .= '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Les systèmes déclarés").'</h3>';
		$h .= '</div>';

		$h .= $this->getDeclared($dashboard, TRUE);

		return $h;

	}

	/**
	 * Une rangée de cartes, qui se tait quand le plancher les a toutes masquées.
	 *
	 * ⚠️ Une carte masquée ne laisse pas de trou : sa voisine prend seule la rangée, en demi-largeur
	 * comme toute carte de `util-cards-2`.
	 */
	protected function getCardsRow(array $cards, string $class): string {

		$cards = array_filter($cards, fn($card) => $card !== '');

		if($cards === []) {
			return '';
		}

		return '<div class="util-cards util-cards-2 '.$class.'">'.implode('', $cards).'</div>';

	}

	/** L'anneau plein champ / abri et sa légende chiffrée : `DesignUi::getRing()` ne donne jamais les valeurs. */
	protected function getNetworkRing(\structure\Structure $eStructure, array $dashboard): string {

		$plan = $dashboard['plan'];

		if(
			$plan['placedFarms'] < VegetableDashboardAiLib::FLOOR or
			$plan['greenhouseFarms'] < VegetableDashboardAiLib::FLOOR
		) {
			return '';
		}

		$share = (int)round($plan['greenhouseShare']);

		$parts = [
			[s("Plein champ"), $plan['openFieldArea'], 100 - $share, 'open-field'],
			[s("Abri"), $plan['greenhouseArea'], $share, 'greenhouse']
		];

		$h = '<div class="util-card">';

			$h .= '<div class="util-card-title">'.s("Surface cultivée {of}", ['of' => \structure\StructureUi::getText($eStructure, 'of')]).'</div>';
			$h .= '<div class="util-card-info mb-1">'.$this->getFarmsOnTotal($plan['placedFarms'], $dashboard).'</div>';

			// ⚠️ Un flex qui revient à la ligne et non `util-cards-2` : une grille imbriquée garde ses
			// deux colonnes jusqu'à 768 px sans rien savoir de la largeur de la carte qui la porte, et
			// la légende s'y serrait entre 768 et 900 px. C'est la forme de la page design system
			// autour d'un anneau (`design/ui/Design.u.php:2089`).
			$h .= '<div class="flex-align-center flex-wrap">';

				$h .= \main\DesignUi::getRing([[100 - $share, 'open-field'], [$share, 'greenhouse']], '8rem');

				$h .= '<div>';

					foreach($parts as [$name, $area, $percent, $color]) {

						$h .= '<div class="mb-1">';
							$h .= '<div class="util-figure-label util-figure-label-top"><span class="badge badge-solid bg-'.$color.'">'.$name.'</span></div>';
							$h .= '<div class="util-figure-value">'.s("{area} · {percent} %", ['area' => $this->getUnit(...$this->getAreaParts($area)), 'percent' => $percent]).'</div>';
						$h .= '</div>';

					}

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La SAU que les fermes déclarent sur leur fiche système.
	 *
	 * ⚠️ `System.totalArea`, toutes productions confondues, et non `Production.area`, la surface en
	 * plein champ du seul atelier maraîchage, que la carte « Les systèmes déclarés » du tableau de
	 * bord lit sous un autre nom. Sous le plancher, la médiane se tait et le nombre de fermes
	 * renseignées reste : un compte ne désigne personne. Sans aucune ferme renseignée, la carte se
	 * tait : elle ne dirait que « 0 », et le titre de la section resterait au-dessus du vide.
	 */
	protected function getNetworkTotalArea(array $dashboard): string {

		$declared = $dashboard['declared'];

		if($declared['totalAreaFarms'] === 0) {
			return '';
		}

		$h = '<div class="util-card">';

			$h .= '<div class="util-card-title">'.s("SAU déclarée").'</div>';
			$h .= '<div class="util-card-info mb-1">'.s("fiche système, champ « Surface agricole utile (SAU) »").'</div>';

			$h .= '<div class="util-figures">';

				if($declared['totalAreaMedian'] !== NULL) {
					$h .= '<div class="util-figure">';
						$h .= '<div class="util-figure-value">'.s("{value} ha", \util\TextUi::number($declared['totalAreaMedian'], 1)).'</div>';
						$h .= '<div class="util-figure-label">'.s("médiane sur {farms} fermes", ['farms' => $declared['totalAreaFarms']]).'</div>';
					$h .= '</div>';
				}

				$h .= $this->getFarmsFigure($declared['totalAreaFarms'], $dashboard['farms']);

			$h .= '</div>';

			$h .= '<div class="mt-1">'.$this->getFarmsProgress($declared['totalAreaFarms'], $dashboard['farms']).'</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getFarmsFigure(int $population, int $total): string {

		$h = '<div class="util-figure">';
			$h .= '<div class="util-figure-value">'.s("{population}/{total}", ['population' => $population, 'total' => $total]).'</div>';
			$h .= '<div class="util-figure-label">'.s("fermes renseignées").'</div>';
		$h .= '</div>';

		return $h;

	}

	protected function getFarmsProgress(int $population, int $total): string {

		$width = ($total > 0) ? round($population / $total * 100, 1) : 0;

		return '<div class="util-progress"><div class="util-progress-part bg-accounting" style="width: '.$width.'%"></div></div>';

	}

	/**
	 * La diversité cultivée, une barre par mois.
	 *
	 * ⚠️ Des lignes au markup des tranches et non un histogramme vertical : le design system n'en a
	 * pas, et `util-graduation` est une règle graduée. **Aucun total mensuel de fermes** : le
	 * décompte est celui des espèces dont la case passe déjà le plancher (`species.byMonth`).
	 */
	protected function getNetworkMonths(array $dashboard): string {

		$byMonth = $dashboard['species']['byMonth'];
		$maximum = max($byMonth);

		if($maximum === 0) {
			return '';
		}

		$h = '<div class="util-card">';

			$h .= '<div class="util-card-title">'.s("Diversité cultivée, mois par mois").'</div>';
			$h .= '<div class="util-card-info mb-1">'.s("espèces récoltées par {floor} fermes ou plus", ['floor' => VegetableDashboardAiLib::FLOOR]).'</div>';

			$h .= '<table class="table-unstyled mb-0">';

				foreach($byMonth as $month => $count) {

					$h .= '<tr>';
						$h .= '<td class="td-min-content text-end">'.\util\DateUi::getMonthName($month).'</td>';
						$h .= '<td><div class="util-progress"><div class="util-progress-part bg-accounting" style="width: '.round($count / $maximum * 100, 1).'%"></div></div></td>';
						$h .= '<td class="td-min-content text-end">'.$count.'</td>';
					$h .= '</tr>';

				}

			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le groupe dont aucune valeur n'atteint le plancher (planche production-finir-statistiques-vide,
	 * Q1 A, « le constat et sa sortie », validée le 02/10/2026).
	 *
	 * ⚠️ Il remplace l'écran entier et n'en garde aucun morceau : afficher deux cartes sur cinq
	 * laisserait croire que les trois autres sont vides, quand elles sont masquées.
	 *
	 * Le bloc est le pendant de `StructureCommunityUi::getSectionEmpty()`, l'écran des fermes du même
	 * sous-groupe : un titre, une phrase, au plus un bouton. `$structures` compte les fermes
	 * rattachées dans la portée, le nombre que la liste des fermes affiche à côté, quand
	 * `$dashboard['farms']` ne compte que celles que le tableau de bord retient
	 * (`VegetableDashboardAiLib::getPopulation()`).
	 *
	 * Le bouton mène là où il y a quelque chose à lire : la comparaison ferme par ferme quand elle a
	 * une espèce à proposer (`$hasCompare`), sinon les statistiques de tout le groupe quand elles
	 * s'affichent (`$hasFiguresCommunity`, que la page ne calcule que dans un sous-groupe). Sinon
	 * aucun. Le groupe se dit avec le mot de son type, « ce réseau » comme partout sur ses écrans.
	 *
	 * ⚠️ `util-block-empty` et non `util-block-cta` : il n'y a rien à créer ici, et le gris-bleu du
	 * design system vaut « il n'y a rien ici » même quand le constat s'accompagne d'un bouton. Le
	 * balisage est celui des deux écrans voisins du même sous-groupe, les fermes et « Cultures ».
	 *
	 * ⚠️ Le jour où s'arrêtent les chiffres (`getLastDay()`) ne suit pas ce bloc : il daterait des
	 * chiffres que l'écran ne montre pas.
	 */
	public function getBelowFloor(\structure\Structure $eStructure, array $dashboard, int $structures, bool $hasCompare, bool $hasFiguresCommunity, \structure\Section $eSection = new \structure\Section()): string {

		if($structures === 0) {
			[$title, $text] = self::getNoFarm($eStructure, $eSection);
		} else {
			$title = s("Pas encore de statistiques");
			$text = $this->getBelowFloorText($eStructure, $dashboard, $structures, $eSection);
		}

		if($hasCompare) {
			$button = '<a href="'.\structure\StructureUi::urlCommunityCompare($eStructure, $eSection).'" class="btn btn-primary">'.s("Comparer les cultures").'</a>';
		} else if($hasFiguresCommunity) {
			$button = '<a href="'.\structure\StructureUi::urlCommunityVegetable($eStructure).'" class="btn btn-primary">'.s("Voir les statistiques de tout {the}", ['the' => \structure\StructureUi::getText($eStructure, 'the')]).'</a>';
		} else {
			$button = '';
		}

		$h = '<div class="util-block util-block-empty">';

			$h .= '<h3>'.$title.'</h3>';
			$h .= '<p>'.$text.'</p>';

			if($button !== '') {
				$h .= '<div class="util-buttons">'.$button.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le titre et la phrase d'une portée sans aucune ferme rattachée. Dans un sous-groupe, ce sont
	 * mot pour mot ceux de l'écran des fermes (`StructureCommunityUi::getSectionEmpty()`), et
	 * l'onglet « Cultures » les reprend (`CompareAiUi::getNoCultivation()`).
	 */
	public static function getNoFarm(\structure\Structure $eStructure, \structure\Section $eSection): array {

		$community = \structure\StructureUi::getText($eStructure, 'this');

		if($eSection->notEmpty()) {

			return [
				s("Aucune ferme"),
				s("Aucun membre de {value} n'a partagé de ferme avec {this}.", ['value' => '<b>'.encode($eSection['name']).'</b>', 'this' => $community])
			];

		}

		return [
			s("Aucune ferme dans {this}", ['this' => $community]),
			self::getFloorText()
		];

	}

	/**
	 * La première phrase compte les fermes rattachées, la seconde dit ce qui manque. Sous le
	 * plancher, c'est le nombre de fermes ; au plancher, c'est une donnée que trois d'entre elles
	 * partagent, et le plan de culture est celle qui ouvre le plus de l'écran.
	 *
	 * Un réseau ne compte que ses fermes en production (`VegetableDashboardAiLib::getPopulation()`).
	 * Quand c'est ce tri qui laisse la portée sous le plancher, la première phrase dit les deux
	 * nombres : « 3 fermes » suivi de « à partir de 3 fermes » se contredirait, et le lecteur ne
	 * saurait pas combien comptent.
	 */
	protected function getBelowFloorText(\structure\Structure $eStructure, array $dashboard, int $structures, \structure\Section $eSection): string {

		$community = \structure\StructureUi::getText($eStructure, 'this');
		$farms = $dashboard['farms'];

		$hasFloor = ($farms >= VegetableDashboardAiLib::FLOOR);
		$hasExcluded = ($hasFloor === FALSE and $structures > $farms);

		if($eSection->notEmpty()) {

			$arguments = [
				'section' => '<b>'.encode($eSection['name']).'</b>',
				'this' => $community,
				'kept' => self::getKeptText($farms)
			];

			$text = $hasExcluded
				? p("Les membres de {section} ont partagé {value} ferme avec {this}, {kept}.", "Les membres de {section} ont partagé {value} fermes avec {this}, {kept}.", $structures, $arguments)
				: p("Les membres de {section} ont partagé {value} ferme avec {this}.", "Les membres de {section} ont partagé {value} fermes avec {this}.", $structures, $arguments);

		} else {

			$arguments = [
				'this' => mb_ucfirst($community),
				'kept' => self::getKeptText($farms)
			];

			$text = $hasExcluded
				? p("{this} compte {value} ferme, {kept}.", "{this} compte {value} fermes, {kept}.", $structures, $arguments)
				: p("{this} compte {value} ferme.", "{this} compte {value} fermes.", $structures, $arguments);

		}

		$text .= ' ';

		if($hasFloor) {

			$text .= p("Les statistiques s'affichent dès que {floor} d'entre elles ont un plan de culture d'au moins {value} série.", "Les statistiques s'affichent dès que {floor} d'entre elles ont un plan de culture d'au moins {value} séries.", VegetableDashboardAiLib::SERIES_MINIMUM, [
				'floor' => VegetableDashboardAiLib::FLOOR
			]);

		} else {
			$text .= self::getFloorText();
		}

		return $text;

	}

	/**
	 * Les fermes que le tableau de bord retient parmi celles de la portée, en incise de la phrase
	 * qui les compte toutes.
	 */
	protected static function getKeptText(int $farms): string {

		if($farms === 0) {
			return s("dont aucune hors fermes de test et projets d'installation");
		}

		return s("dont {value} hors fermes de test et projets d'installation", $farms);

	}

	protected static function getFloorText(): string {
		return p("Les statistiques s'affichent à partir de {value} ferme.", "Les statistiques s'affichent à partir de {value} fermes.", VegetableDashboardAiLib::FLOOR);
	}

	/**
	 * Le jour où s'arrêtent les chiffres tirés des plans de culture, en pied d'écran.
	 *
	 * Le libellé nomme les plans et non l'écran : la surface agricole, les planches et le déclaré se
	 * lisent en direct. Rien avant la première projection, que la migration joue au déploiement.
	 */
	protected function getLastDay(array $dashboard): string {

		if($dashboard['lastDay'] === NULL) {
			return '';
		}

		return '<p class="util-annotation mt-2">'.s("Chiffres des plans de culture arrêtés au {date}", ['date' => \util\DateUi::numeric($dashboard['lastDay'], \util\DateUi::DATE)]).'</p>';

	}

	/**
	 * Les chiffres du réseau.
	 *
	 * ⚠️ Chaque tuile porte **sa** population et disparaît sous le plancher : elles ne se lisent
	 * pas toutes sur le même nombre de fermes — en 2026 sur 6388, 43 ont au moins dix séries et 33
	 * saisissent du temps —, et
	 * la tuile qui reste dit sur combien elle porte.
	 */
	protected function getFigures(array $dashboard): string {

		$plan = $dashboard['plan'];
		$farms = $dashboard['farms'];

		$figures = [
			[$plan['farms'], \util\TextUi::number($plan['farms'], 0), s("fermes sur {total} ont au moins {minimum} séries en {season}", ['total' => $farms, 'minimum' => VegetableDashboardAiLib::SERIES_MINIMUM, 'season' => $dashboard['season']])],
			[$plan['placedFarms'], $this->getUnit(...$this->getAreaParts($plan['area'])), s("placés en planches ou en parcelles, sur {farms} fermes", ['farms' => $plan['placedFarms']])],
			[$plan['greenhouseFarms'], s("{value} %", \util\TextUi::number($plan['greenhouseShare'], 0)), s("de cette surface sous abri, sur {farms} fermes", ['farms' => $plan['greenhouseFarms']])],
			[$plan['farms'], \util\TextUi::number($dashboard['species']['count'], 0), s("espèces, dont {above} cultivées par {floor} fermes ou plus", ['above' => $dashboard['species']['aboveFloor'], 'floor' => VegetableDashboardAiLib::FLOOR])]
		];

		$h = '';

		foreach($figures as [$population, $value, $label]) {

			if($population < VegetableDashboardAiLib::FLOOR) {
				continue;
			}

			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.$value.'</div>';
				$h .= '<div class="util-figure-label">'.$label.'</div>';
			$h .= '</div>';

		}

		if($h === '') {
			return '';
		}

		return '<div class="util-figures">'.$h.'</div>';

	}

	/** Une répartition par tranche, en carte. */
	protected function getBin(array $dashboard, string $indicator): string {

		$bins = $dashboard['bins'][$indicator];

		if($bins['bins'] === []) {
			return '';
		}

		$percent = fn(float $value) => [\util\TextUi::number($value, 0), s("%")];

		[$title, $parts, $zero, $median, $mean] = match($indicator) {
			VegetableDashboardAiLib::AREA => [
				s("Surface développée par ferme"),
				fn(float $value) => $this->getAreaParts($value),
				s("aucune surface"),
				NULL,
				FALSE
			],
			VegetableDashboardAiLib::GREENHOUSE => [
				s("Part de la surface sous abri"),
				$percent,
				s("aucun abri"),
				NULL,
				FALSE
			],
			VegetableDashboardAiLib::SPECIES => [
				s("Espèces cultivées par ferme"),
				fn(float $value) => [\util\TextUi::number($value, 0), ''],
				s("aucune"),
				fn(float $value) => p("médiane {value} espèce", "médiane {value} espèces", (int)round($value)),
				FALSE
			],
			VegetableDashboardAiLib::HARVESTED => [
				s("Part des cultures récoltées sur le logiciel"),
				$percent,
				s("aucune"),
				NULL,
				FALSE
			],
			VegetableDashboardAiLib::BED_AREA => [
				s("Surface de planches par ferme"),
				fn(float $value) => $this->getAreaParts($value),
				s("aucune surface"),
				NULL,
				TRUE
			],
			VegetableDashboardAiLib::BED_GREENHOUSE => [
				s("Part des planches sous abri"),
				$percent,
				s("aucun abri"),
				NULL,
				TRUE
			]
		};

		$h = '<div class="util-card">';

			$h .= '<div class="util-card-title">'.$title.'</div>';

			$h .= '<div class="util-card-info mb-1">';
				$h .= $this->getFarmsOnTotal($bins['farms'], $dashboard);
				$h .= ' &middot; '.(($median === NULL) ? s("médiane {value}", $this->getUnit(...$parts($bins['median']))) : $median($bins['median']));

				if($mean) {
					$h .= ' &middot; '.s("moyenne {value}", $this->getUnit(...$parts($bins['mean'])));
				}
			$h .= '</div>';

			$h .= $this->getBinTable($bins['bins'], $parts, $zero);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une répartition, une ligne par tranche.
	 *
	 * ⚠️ La tranche de la médiane est en gras, et les autres gardent la couleur du texte : le gris
	 * atténué du produit veut dire « inactif », et ces tranches-là ne le sont pas. C'est l'écart
	 * assumé avec la maquette, qui les grisait.
	 *
	 * ⚠️ La barre se rapporte à la tranche la plus fournie et non au total des fermes : les
	 * tranches fusionnées sous le plancher rendent le total inexploitable comme échelle, et c'est
	 * la comparaison entre tranches qui se lit.
	 */
	protected function getBinTable(array $bins, \Closure $parts, string $zero): string {

		$maximum = max(array_column($bins, 'farms'));

		$h = '<table class="table-unstyled mb-0">';
			$h .= '<tbody>';

				foreach($bins as $bin) {

					$label = $this->getBinLabel($bin, $parts, $zero);

					$h .= '<tr>';

						$h .= '<td class="text-end td-min-content">'.($bin['median'] ? '<b>'.$label.'</b>' : $label).'</td>';

						$h .= '<td>';
							$h .= '<div class="util-progress">';
								$h .= '<div class="util-progress-part bg-accounting" style="width: '.round($bin['farms'] / $maximum * 100, 1).'%"></div>';
							$h .= '</div>';
						$h .= '</td>';

						$h .= '<td class="text-end td-min-content">'.p("{value} ferme", "{value} fermes", $bin['farms']).'</td>';

					$h .= '</tr>';

				}

			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	/**
	 * Le libellé d'une tranche, composé depuis ses bornes.
	 *
	 * ⚠️ Composé et non lu dans une constante : `applyFloor()` fusionne deux tranches sous le
	 * plancher et fabrique une borne que rien ne porte. Un libellé figé par indicateur mentirait
	 * dès la première fusion.
	 */
	protected function getBinLabel(array $bin, \Closure $parts, string $zero): string {

		if($bin['low'] === $bin['high']) {
			return $zero;
		}

		if($bin['high'] === NULL) {
			return s("{value} et plus", $this->getUnit(...$parts((float)$bin['low'])));
		}

		if($bin['low'] === 0) {
			return s("moins de {value}", $this->getUnit(...$parts((float)$bin['high'])));
		}

		[$from, $unitFrom] = $parts((float)$bin['low']);
		[$to, $unitTo] = $parts((float)$bin['high']);

		// La borne basse perd son unité quand les deux la partagent : « 1 000 à 5 000 m² » se lit,
		// « 1 000 m² à 5 000 m² » se déchiffre. Elle la garde quand elles diffèrent, l'échelle
		// changeant alors au milieu de la tranche — « 5 000 m² à 1 ha ».
		return s("{from} à {to}", [
			'from' => ($unitFrom === $unitTo) ? $from : $this->getUnit($from, $unitFrom),
			'to' => $this->getUnit($to, $unitTo)
		]);

	}

	/**
	 * Une surface, en mètres carrés jusqu'à l'hectare et en hectares au-delà.
	 *
	 * Rend la valeur et son unité séparément : un libellé de tranche compose les deux bornes et
	 * doit pouvoir retirer l'unité de la première quand elles la partagent.
	 */
	protected function getAreaParts(float $area): array {

		// Précision NULL et non 1 : la valeur est déjà arrondie, et forcer une décimale écrirait
		// « 1,0 ha » là où la donnée vaut un hectare rond.
		return ($area < 10000)
			? [\util\TextUi::number($area, 0), s("m²")]
			: [\util\TextUi::number(round($area / 10000, 1), NULL), s("ha")];

	}

	protected function getUnit(string $value, string $unit): string {
		return ($unit === '') ? $value : s("{value} {unit}", ['value' => $value, 'unit' => $unit]);
	}

	/**
	 * Les espèces que le plus de fermes cultivent, une ligne par espèce.
	 *
	 * ⚠️ Des barres HTML et non un canevas : l'icône de l'espèce est un symbole du sprite
	 * `plants.svg`, qu'un canevas ne sait pas dessiner (17/09/2026, « Barres
	 * horizontales »). Sous 576 px, la piste passe sous le nom.
	 *
	 * Douze espèces et non les quinze du calendrier : la carte partage sa rangée avec les douze
	 * mois, et une rangée prend la hauteur de sa carte la plus haute.
	 */
	protected function getSpecies(\structure\Structure $eStructure, array $dashboard, bool $hasSharing, \structure\Section $eSection): string {

		$list = array_slice($dashboard['species']['list'], 0, 12);

		if($list === []) {
			return '';
		}

		$maximum = max(array_column($list, 'farms'));

		$h = '<div class="util-card">';

			$h .= '<div class="util-card-title">'.s("Les espèces cultivées par le plus de fermes").'</div>';

			$h .= '<div class="util-card-info mb-1">'.$this->getPlanPopulation($dashboard['plan']['farms'], $dashboard).'</div>';

			$h .= '<div class="vegetable-dashboard-species">';

				foreach($list as $species) {

					$h .= '<div class="vegetable-dashboard-species-line">';

						$h .= '<div class="vegetable-dashboard-species-name">';
							$h .= \plant\PlantUi::getVignette($species['ePlant'], '2rem');
							$h .= $this->getSpeciesName($eStructure, $species['ePlant'], $dashboard['season'], $hasSharing, $eSection);
						$h .= '</div>';

						$h .= '<div class="util-progress">';
							$h .= '<div class="util-progress-part bg-accounting" style="width: '.round($species['farms'] / $maximum * 100, 1).'%"></div>';
						$h .= '</div>';

						$h .= '<div class="vegetable-dashboard-species-farms">'.p("{value} ferme", "{value} fermes", $species['farms']).'</div>';

					$h .= '</div>';

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le calendrier de récolte du réseau, espèce par mois.
	 *
	 * ⚠️ **Aucun total par mois**, et c'est une règle de masquage et non un choix de mise en page :
	 * le total d'une colonne redonnerait par soustraction la case que le plancher vient de vider.
	 * Le total par espèce, lui, est affiché — il porte sur la ligne entière, qui passe le plancher.
	 */
	protected function getCalendar(\structure\Structure $eStructure, array $dashboard, bool $hasSharing, \structure\Section $eSection): string {

		$list = array_slice($dashboard['species']['list'], 0, 15);

		if($list === []) {
			return '';
		}

		$maximum = 0;

		foreach($list as $species) {
			$maximum = max($maximum, ...array_map(fn($farms) => (int)$farms, $species['months']));
		}

		$h = '<div class="util-card mb-2">';

			$h .= '<div class="util-card-title">'.s("Les récoltes prévues, mois par mois").'</div>';

			$h .= '<div class="util-card-info mb-1">';
				$h .= $this->getPlanPopulation($dashboard['plan']['farms'], $dashboard);
			$h .= '</div>';

			// util-overflow-md et non util-overflow : sous 768 px, il donne au tableau une largeur
			// plancher (767 px moins les marges du conteneur), qui garde aux douze mois une colonne
			// lisible et le fait défiler seul ; au-dessus, les noms d'espèce et les douze mois
			// tiennent dans la carte (mesuré sur 6388 de 768 à 1199 px, sans débordement).
			$h .= '<div class="util-overflow-md mb-1">';

				$h .= '<table class="table-unstyled util-heat-table">';

					$h .= '<thead>';
						$h .= '<tr>';
							$h .= '<th>'.s("Espèce").'</th>';
							$h .= '<th class="text-end">'.s("Fermes").'</th>';

							for($month = 1; $month <= 12; $month++) {
								$h .= '<th class="text-end">'.\util\DateUi::getMonthName($month, 'short').'</th>';
							}

						$h .= '</tr>';
					$h .= '</thead>';

					$h .= '<tbody>';

						foreach($list as $species) {

							$h .= '<tr>';

								$h .= '<td>'.$this->getSpeciesName($eStructure, $species['ePlant'], $dashboard['season'], $hasSharing, $eSection).'</td>';
								$h .= '<td class="text-end"><b>'.$species['farms'].'</b></td>';

								for($month = 1; $month <= 12; $month++) {

									$farms = $species['months'][$month];

									// Une case sous le plancher est vide et jamais à zéro : zéro
									// est une mesure, le vide dit qu'il n'y a rien à dire.
									if($farms === NULL) {
										$h .= '<td></td>';
										continue;
									}

									$heat = round($farms / $maximum, 2);

									$h .= '<td class="text-end util-heat'.(($heat > 0.95) ? ' util-heat-strong' : '').'" style="--heat: '.$heat.'">'.$farms.'</td>';

								}

							$h .= '</tr>';

						}

					$h .= '</tbody>';

				$h .= '</table>';

			$h .= '</div>';

			$h .= '<p class="util-card-info mb-0">';

				$h .= s("Une case vide : moins de {floor} fermes ce mois-là.", ['floor' => VegetableDashboardAiLib::FLOOR]);

				$remaining = $dashboard['species']['aboveFloor'] - count($list);

				if($remaining > 0) {
					$h .= ' '.p("{value} autre espèce est cultivée par {floor} fermes ou plus.", "{value} autres espèces sont cultivées par {floor} fermes ou plus.", $remaining, ['floor' => VegetableDashboardAiLib::FLOOR]);
				}

				$below = $dashboard['species']['count'] - $dashboard['species']['aboveFloor'];

				if($below > 0) {
					$h .= ' '.p("{value} espèce est cultivée par moins de {floor} fermes.", "{value} espèces sont cultivées par moins de {floor} fermes.", $below, ['floor' => VegetableDashboardAiLib::FLOOR]);
				}

				if($hasSharing) {
					$h .= ' <a href="'.\structure\StructureUi::urlCommunityCompare($eStructure, $eSection).'">'.s("Voir dans Cultures").'</a>';
				}

			$h .= '</p>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le nom d'une espèce, lien vers la comparaison quand elle a quelque chose à montrer.
	 *
	 * ⚠️ Sans une seule ferme partageuse, la comparaison s'ouvre vide : le nom reste du texte. Le
	 * lien n'est pas retiré parce que le lecteur n'y aurait pas droit, mais parce qu'il ne mènerait
	 * nulle part.
	 */
	protected function getSpeciesName(\structure\Structure $eStructure, \plant\Plant $ePlant, int $season, bool $hasSharing, \structure\Section $eSection): string {

		if($hasSharing === FALSE) {
			return encode($ePlant['name']);
		}

		$url = \structure\StructureUi::urlCommunityCompare($eStructure, $eSection).'?season='.$season.'&plant='.$ePlant['id'];

		return '<a href="'.$url.'">'.encode($ePlant['name']).'</a>';

	}


	/**
	 * Le temps de travail par nature d'intervention : les quatre natures du produit, puis « Autres ».
	 *
	 * ⚠️ **Une ligne par nature connue, même sans heures à montrer.** Une nature que personne n'a
	 * saisie s'écrit « non renseigné » et jamais 0 h : une ferme qui a supprimé la catégorie
	 * « Commercialisation » vend quand même, et range ce temps ailleurs. Une nature sous le plancher
	 * est comptée dans « Autres », et la ligne le dit plutôt que de disparaître.
	 *
	 * Le tableau et l'anneau sont ceux des analyses du temps de travail d'une ferme
	 * (`work\AnalyzeUi::getMethodTable()`), demandés le 19/09/2026. L'anneau ne porte que
	 * les natures qui ont des heures à montrer.
	 */
	protected function getHoursByCategory(array $dashboard): string {

		$categories = $dashboard['hoursByCategory'];

		if($categories === []) {
			return '';
		}

		$rows = [];

		foreach($categories['natures'] as $nature) {
			$rows[] = [$this->getNatureName($nature['fqn']), $nature];
		}

		if($categories['others'] !== NULL) {
			$rows[] = [s("Autres"), ['state' => VegetableDashboardAiLib::NATURE_SHOWN] + $categories['others']];
		}

		$total = array_sum(array_map(fn(array $row) => ($row[1]['state'] === VegetableDashboardAiLib::NATURE_SHOWN) ? $row[1]['hours'] : 0, $rows));

		$h = '<div class="util-card mb-2">';

			$h .= '<div class="util-card-title">'.s("Le temps de travail saisi, par nature").'</div>';

			$h .= '<div class="util-card-info mb-1">'.s("jusqu'à {farms} fermes sur {total} selon la nature", ['farms' => $categories['farms'], 'total' => $dashboard['farms']]).'</div>';

			$h .= '<div class="analyze-chart-table">';
				$h .= $this->getNatureTable($rows, $total);
				$h .= $this->getNaturePie($rows);
			$h .= '</div>';

			$h .= '<p class="util-card-info mb-0">';

				if($categories['others'] !== NULL) {
					$h .= $this->getOthersContent($categories['others']).' ';
				}

				$h .= $this->getSeasonLimit();

			$h .= '</p>';

		$h .= '</div>';

		return $h;

	}

	protected function getNatureTable(array $rows, float $total): string {

		$h = '<table class="tr-even analyze-values">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Nature").'</th>';
					$h .= '<th class="text-end">'.s("Temps passé").'</th>';
					$h .= '<th></th>';
					$h .= '<th class="text-end">'.s("Fermes").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';
			$h .= '<tbody>';

				foreach($rows as [$name, $nature]) {

					$h .= '<tr>';
						$h .= '<td>'.$name.'</td>';

						if($nature['state'] === VegetableDashboardAiLib::NATURE_SHOWN) {
							$h .= '<td class="text-end">'.s("{value} h", \util\TextUi::number($nature['hours'], 0)).'</td>';
							$h .= '<td><div class="util-annotation">'.\util\TextUi::pc($nature['hours'] / $total * 100, 0).'</div></td>';
							$h .= '<td class="text-end">'.$nature['farms'].'</td>';
						} else {
							$h .= '<td colspan="3" class="text-end color-muted">';
								$h .= match($nature['state']) {
									VegetableDashboardAiLib::NATURE_MERGED => s("comptée dans « Autres »"),
									VegetableDashboardAiLib::NATURE_ABSENT => s("non renseigné")
								};
							$h .= '</td>';
						}

					$h .= '</tr>';

				}

			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	protected function getNaturePie(array $rows): string {

		$parts = new \Collection();

		// Les libellés se résolvent ici : `buildPie()` appelle ses closures avec `Closure::call()`,
		// et `$this` y désigne le `ChartUi`.
		foreach($rows as [$name, $nature]) {
			if($nature['state'] === VegetableDashboardAiLib::NATURE_SHOWN) {
				$parts->append(['hours' => $nature['hours'], 'label' => $name]);
			}
		}

		return new \main\ChartUi()->buildPie(s("Répartition par nature"), $parts, 'hours', fn(array $part) => $part['label']);

	}

	protected function getNatureName(string $fqn): string {

		return match($fqn) {
			CATEGORIE_CULTURE => s("Sur une culture"),
			CATEGORIE_PRODUCTION => s("Sur l'outil de production"),
			CATEGORIE_COMMERCIALISATION => s("Commercialisation"),
			CATEGORIE_ADMINISTRATION => s("Administration")
		};

	}

	/**
	 * Ce que « Autres » contient, composé depuis ce qu'il contient vraiment : les natures versées,
	 * puis les catégories que les fermes se sont créées.
	 *
	 * ⚠️ Une nature versée n'est pas forcément sous le plancher : elle peut l'avoir été pour que
	 * « Autres » le passe. La phrase donne les deux raisons plutôt que d'en affirmer une.
	 */
	protected function getOthersContent(array $others): string {

		$parts = array_map(fn(string $fqn) => s("« {value} »", $this->getNatureName($fqn)), $others['merged']);

		if($others['own']) {
			$parts[] = s("les catégories que chaque ferme s'est créées");
		}

		$last = array_pop($parts);

		$list = ($parts === []) ? $last : s("{list} et {last}", ['list' => implode(', ', $parts), 'last' => $last]);

		$h = s("« Autres » réunit {value}.", $list);

		if($others['merged'] !== []) {
			$h .= ' '.s("Une nature y est comptée quand moins de {floor} fermes l'ont saisie, ou pour que « Autres » en réunisse au moins {floor}.", ['floor' => VegetableDashboardAiLib::FLOOR]);
		}

		return $h;

	}

	/**
	 * ⚠️ La borne du temps de travail est l'année civile et non la saison : une feuille de temps
	 * n'en porte pas, et l'intervention dont elle pend n'en porte pas toujours. Le lecteur doit le
	 * savoir pour ne pas lire la préparation d'hiver sur la mauvaise saison — c'est une limite de
	 * la donnée, pas une explication de l'écran.
	 */
	protected function getSeasonLimit(): string {
		return s("Le temps est compté sur l'année civile : la préparation d'une saison faite en fin d'année précédente compte dans celle-là.");
	}

	/**
	 * Les ateliers et la fiche système, toujours partagés avec le groupe : un chiffre par tuile, et
	 * en une phrase ce qui manque pour afficher les autres.
	 *
	 * La SAU a sa carte à côté de l'anneau quand les plans de culture rendent des chiffres : elle
	 * n'entre dans les tuiles (`$hasTotalArea`) que lorsque cette carte ne s'affiche pas.
	 *
	 * ⚠️ La population n'est pas celle des blocs au-dessus : ces valeurs ne dépendent d'aucun plan
	 * de culture et se lisent sur toute la population du tableau de bord, y compris les fermes qui n'ont jamais
	 * ouvert le logiciel de production.
	 */
	protected function getDeclared(array $dashboard, bool $hasTotalArea): string {

		$declared = $dashboard['declared'];
		$workshop = $declared['workshopFarms'];

		// Le libellé d'une tuile, le nom dans la phrase de ce qui manque, la médiane, sa population.
		$metrics = [];

		if($hasTotalArea) {

			$metrics[] = [
				s("surface agricole utile"),
				s("la surface agricole utile"),
				($declared['totalAreaMedian'] === NULL) ? NULL : s("{value} ha", \util\TextUi::number($declared['totalAreaMedian'], 1)),
				$declared['totalAreaFarms']
			];

		}

		$metrics[] = [
			s("surface en plein champ"),
			s("la surface en plein champ"),
			// Arrondie avant d'être écrite, sans décimale forcée : « 1 ha » et non « 1,0 ha ».
			($declared['areaMedian'] === NULL) ? NULL : s("{value} ha", \util\TextUi::number(round($declared['areaMedian'], 1), NULL)),
			$declared['areaFarms']
		];

		$metrics[] = [
			s("début de l'activité"),
			s("le début de l'activité"),
			($declared['startedMedian'] === NULL) ? NULL : (string)(int)$declared['startedMedian'],
			$declared['startedFarms']
		];

		$metrics[] = [
			s("main-d'œuvre"),
			s("la main-d'œuvre"),
			($declared['workforceMedian'] === NULL) ? NULL : p("{value} équivalent temps plein", "{value} équivalents temps plein", $declared['workforceMedian'], ['value' => \util\TextUi::number($declared['workforceMedian'], 1)]),
			$declared['workforceFarms']
		];

		$figures = $this->getOrganic($declared, $workshop);

		// Les noms de ce qui ne s'affiche pas, rangés par nombre de fermes qui manquent.
		$missing = [];

		foreach($metrics as [$label, $name, $value, $population]) {

			if($value === NULL) {
				$missing[VegetableDashboardAiLib::FLOOR - $population][] = $name;
				continue;
			}

			$figures .= '<div class="util-figure">';
				$figures .= '<div class="util-figure-value">'.$value.'</div>';
				$figures .= '<div class="util-figure-label">'.s("{label}, médiane sur {farms}", ['label' => $label, 'farms' => p("{value} ferme", "{value} fermes", $population)]).'</div>';
			$figures .= '</div>';

		}

		ksort($missing);

		$h = '<div class="util-card">';

			$h .= '<div class="util-card-title">'.s("Les ateliers maraîchage").'</div>';

			$h .= '<div class="util-card-info mb-1">';
				$h .= p("{value} atelier sur {farms} fermes", "{value} ateliers sur {farms} fermes", $workshop, ['farms' => $dashboard['farms']]);
				$h .= ' &middot; '.s("fiche système et ateliers, toujours partagés");
			$h .= '</div>';

			if($figures !== '') {
				$h .= '<div class="util-figures mb-0">'.$figures.'</div>';
			}

			$h .= $this->getMissing($workshop, $missing);
			$h .= $this->getWorkshops($declared);

		$h .= '</div>';

		return $h;

	}

	/**
	 * La part des ateliers en bio, la conversion dans l'intitulé. Sous le plancher la tuile se tait,
	 * et `getMissing()` dit combien d'ateliers manquent.
	 */
	protected function getOrganic(array $declared, int $workshop): string {

		if($workshop < VegetableDashboardAiLib::FLOOR) {
			return '';
		}

		$conversion = $declared['organic'][\structure\Production::CONVERSION];

		$h = '<div class="util-figure">';

			$h .= '<div class="util-figure-value">'.$declared['organic'][\structure\Production::YES].'<small> '.s("sur {value}", $workshop).'</small></div>';

			if($conversion > 0) {
				$h .= '<div class="util-figure-label">'.s("ateliers en bio, dont {value} en conversion", $conversion).'</div>';
			} else {
				$h .= '<div class="util-figure-label">'.s("ateliers en bio").'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce qui manque pour afficher les chiffres absents, en une phrase : des ateliers pour la part en
	 * bio, le label étant obligatoire sur la fiche d'atelier, puis des réponses de fermes, regroupées
	 * par nombre de fermes qui manquent.
	 *
	 * ⚠️ L'écran dit **combien** il en manque : un blanc laisserait croire que personne n'a
	 * répondu, et c'est le contraire qui pousse une ferme à remplir sa fiche.
	 */
	protected function getMissing(int $workshop, array $missing): string {

		$parts = [];

		if($workshop < VegetableDashboardAiLib::FLOOR) {
			$parts[] = p("un atelier maraîchage pour afficher la part en bio", "{value} ateliers maraîchage pour afficher la part en bio", VegetableDashboardAiLib::FLOOR - $workshop);
		}

		$isFirst = TRUE;

		foreach($missing as $answers => $names) {

			$arguments = [
				'answers' => $answers,
				'list' => $this->getEnumeration($names)
			];

			// La première proposition porte le nom et le verbe, les suivantes les sous-entendent.
			if($isFirst) {
				$parts[] = p("une réponse pour afficher {list}", "{answers} réponses pour afficher {list}", $answers, $arguments);
			} else {
				$parts[] = p("une pour {list}", "{answers} pour {list}", $answers, $arguments);
			}

			$isFirst = FALSE;

		}

		if($parts === []) {
			return '';
		}

		// Le seuil ferme la phrase : « il manque 2 réponses » seul laisserait croire, sur un grand
		// groupe, que toutes les autres fermes ont répondu.
		return '<p class="util-card-info mt-1 mb-0">'.s("Il manque {list}. Il en faut {floor}.", ['list' => implode(s(", et "), $parts), 'floor' => VegetableDashboardAiLib::FLOOR]).'</p>';

	}

	/** « a », « a et b », « a, b et c ». */
	protected function getEnumeration(array $names): string {

		$last = array_pop($names);

		if($names === []) {
			return $last;
		}

		return s("{list} et {last}", ['list' => implode(', ', $names), 'last' => $last]);

	}

	/**
	 * Les autres ateliers du groupe, nommés seulement au-dessus du plancher.
	 *
	 * ⚠️ Même règle que les espèces : un atelier déclaré par deux fermes les désignerait presque
	 * dans un réseau que son équipe connaît. Ceux qu'il masque se comptent sans se nommer.
	 */
	protected function getWorkshops(array $declared): string {

		$workshops = $declared['workshops'];

		unset($workshops[VegetableDashboardAiLib::WORKSHOP]);

		$h = '';

		if($workshops !== []) {

			$names = [];

			foreach($workshops as $fqn => $farms) {
				$names[] = s("{name} ({farms})", ['name' => \structure\ProductionUi::get($fqn), 'farms' => $farms]);
			}

			$h .= s("Autres ateliers du groupe : {list}.", ['list' => implode(', ', $names)]);

		}

		if($declared['workshopsBelowFloor'] > 0) {
			$h .= ($h === '' ? '' : ' ').p("{value} autre atelier est déclaré par moins de {floor} fermes.", "{value} autres ateliers sont déclarés par moins de {floor} fermes.", $declared['workshopsBelowFloor'], ['value' => $declared['workshopsBelowFloor'], 'floor' => VegetableDashboardAiLib::FLOOR]);
		}

		if($h === '') {
			return '';
		}

		return '<p class="util-card-info mt-1 mb-0">'.$h.'</p>';

	}

	/** La population des blocs qui se lisent sur le plan de culture. */
	protected function getFarmsOnTotal(int $population, array $dashboard): string {
		return s("{population} sur {total}", ['population' => p("{value} ferme", "{value} fermes", $population), 'total' => $dashboard['farms']]);
	}

	protected function getPlanPopulation(int $population, array $dashboard): string {
		return $this->getFarmsOnTotal($population, $dashboard).' &middot; '.p("au moins {value} série", "au moins {value} séries", VegetableDashboardAiLib::SERIES_MINIMUM);
	}

}
?>
