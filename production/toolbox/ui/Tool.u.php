<?php
namespace toolbox;

/**
 * La Boîte à outils : les outils que la ferme utilise, puis ceux à essayer (arbitrage Q12 A de la
 * planche pf-l8-v4, qui remplace la grille unique aux favoris en tête de pf-outils). Un outil est
 * une valeur de `Favorite.tool` : l'enum en est l'inventaire, et chaque outil ajouté y entre avec
 * son nom, sa description, son icône, son adresse, et ce qui dit qu'il est utilisé.
 */
class ToolUi {

	public function __construct() {

		\Asset::css('toolbox', 'toolbox.css');

	}

	public static function url(\structure\Structure $eStructure): string {
		return '/ferme/'.$eStructure['id'].'/outils';
	}

	public static function urlTool(\structure\Structure $eStructure, string $tool): string {

		return match($tool) {
			Favorite::RAIN => RainUi::url($eStructure),
			Favorite::BASKET => CompositionUi::url($eStructure),
			Favorite::TREATMENT => TreatmentUi::url($eStructure),
			Favorite::DENSITY => DensityUi::url($eStructure),
			Favorite::CATALOG => CatalogUi::url($eStructure),
			Favorite::MAINTENANCE => EquipmentUi::url($eStructure),
			Favorite::WATER => WaterReadingUi::url($eStructure),
			Favorite::FERTILIZATION => FertilizationUi::url($eStructure),
			Favorite::AID => \aid\AidUi::url($eStructure)
		};

	}

	public static function getName(string $tool): string {

		return match($tool) {
			Favorite::RAIN => s("Pluviométrie"),
			Favorite::BASKET => s("Paniers de l'année"),
			Favorite::TREATMENT => s("Registre phytosanitaire"),
			Favorite::DENSITY => s("Densités"),
			Favorite::CATALOG => s("Catalogue E-Phy"),
			Favorite::MAINTENANCE => s("Carnet d'entretien du matériel"),
			Favorite::WATER => s("Consommation d'eau"),
			Favorite::FERTILIZATION => s("Besoins en fertilisation"),
			Favorite::AID => s("Aides agricoles")
		};

	}

	protected static function getDescription(string $tool): string {

		return match($tool) {
			Favorite::RAIN => s("La pluie relevée au pluviomètre, cumulée et comparée à l'année précédente"),
			Favorite::BASKET => s("Composer les paniers semaine par semaine, avec ce que vous cultivez"),
			Favorite::TREATMENT => s("Vos traitements rangés par produit, avec la quantité employée sur chaque culture"),
			Favorite::DENSITY => s("Le nombre de plants au m² et par planche en images et en chiffres"),
			Favorite::CATALOG => s("Les produits de traitement autorisés sur une espèce contre une maladie ou un ravageur"),
			Favorite::MAINTENANCE => s("L'entretien et les heures d'utilisation du matériel"),
			Favorite::WATER => s("Suivi la consommation d'eau sur la ferme, cumulée et comparée à l'année précédente"),
			Favorite::FERTILIZATION => s("Les apports à prévoir pour une culture, depuis ses besoins"),
			Favorite::AID => s("Les subventions, prêts et exonérations qui valent pour votre structure, rangés par projet")
		};

	}

	protected static function getIcon(string $tool): string {

		return match($tool) {
			Favorite::RAIN => 'cloud-rain',
			Favorite::BASKET => 'basket',
			Favorite::TREATMENT => 'capsule',
			Favorite::DENSITY => 'grid-3x3-gap',
			Favorite::CATALOG => 'search',
			Favorite::MAINTENANCE => 'wrench',
			Favorite::WATER => 'droplet-half',
			Favorite::FERTILIZATION => 'calculator',
			Favorite::AID => 'piggy-bank'
		};

	}

	/**
	 * Les outils prévus, montrés sous « Bientôt » (arbitrage Q20 A de la planche pf-l8-v7), chacun
	 * `[icône, nom, phrase]`. Ils ne sont pas des valeurs de `Favorite.tool`, qui reste l'inventaire
	 * des outils qui existent : un outil prévu n'a ni adresse, ni étoile, ni usage à lire. Il sort
	 * d'ici le jour où il entre dans l'enum.
	 *
	 * Les paniers n'y sont pas : une structure qui n'a pas la production ne les voit nulle part
	 * (`getList()`), et les ranger ici lui promettrait un outil qu'elle n'aura pas.
	 */
	protected static function getPlanned(): array {

		// Les besoins en fertilisation en sont sortis le 03/10/2026, entrés dans l'enum : aucun outil
		// n'est annoncé, et la section ne s'affiche pas.
		return [];

	}

	// Le retour mène à la Boîte, sauf quand l'outil a son propre accueil à retrouver ($back). `$season`
	// est ce qui borne tout l'écran, à côté du titre, comme la saison d'un plan de culture.
	public function getHeader(\structure\Structure $eStructure, string $tool, \Collection $cFavorite, string $menu, ?string $back = NULL, string $season = ''): string {

		return \main\DesignUi::getPageHeader(
			self::getName($tool),
			back: $back ?? self::url($eStructure),
			settings: $this->getHeaderMenu($eStructure, $tool, $cFavorite, $menu),
			season: $season
		);

	}

	protected function getHeaderMenu(\structure\Structure $eStructure, string $tool, \Collection $cFavorite, string $menu): string {

		$star = $this->getStar($eStructure, $tool, $cFavorite->offsetExists($tool), menu: TRUE);

		if($menu === '' and $star === '') {
			return '';
		}

		$items = $menu;
		if($menu !== '' and $star !== '') {
			$items .= '<div class="dropdown-divider"></div>';
		}
		$items .= $star;
		if($eStructure->canPlanning() and in_array($tool, $eStructure->conf(\structure\Feature::class)['hasToolbox'])) {

			// Quitter les paniers efface leur plan (`ToolLib::forget()`), quitter un autre outil ne
			// perd rien : la confirmation le dit avant le geste.
			$confirm = match($tool) {
				Favorite::BASKET => s("L'outil retournera parmi les outils à essayer. Vos paniers et tous leurs réglages seront supprimés, et vous repartirez de zéro en le reprenant."),
				default => s("L'outil retournera parmi les outils à essayer. Ce que vous y avez saisi est conservé.")
			};

			$items .= '<a data-ajax="/toolbox/tool:doForget" post-id="'.$eStructure['id'].'" post-tool="'.$tool.'" class="dropdown-item" '.attr('data-confirm', $confirm).'>'.\Asset::icon('x-circle').' '.s("Ne plus utiliser cet outil").'</a>';

		}

		return \main\DesignUi::getGear($items, self::getName($tool));

	}

	/**
	 * Deux sections (arbitrage Q12 A) : les outils que la ferme utilise déjà, avec leurs chiffres et
	 * leurs raccourcis, puis ceux à essayer, avec leur description. L'usage se lit dans
	 * `Feature.hasToolbox`, que chaque outil remplit à son premier geste, jamais dans les favoris, qui
	 * sont à la personne : l'étoile reste sur chaque carte. Les outils prévus suivent, sous « Bientôt »
	 * (arbitrage Q20 A de la planche pf-l8-v7).
	 *
	 * `$treatments` est le résumé du carnet de traitement (`TreatmentLib::getSummary()`), `$maintenances`
	 * celui du carnet du matériel (`EquipmentLib::getSummary()`), `$waters` celui de la consommation
	 * d'eau (`WaterReadingLib::getSummary()`), qui n'est lu que pour une structure qui a posé un compteur.
	 */
	public function getList(\structure\Structure $eStructure, \Collection $cFavorite, \Collection $cGauge, \Collection $cCompositionFormula, array $treatments, array $maintenances, array $waters = []): string {

		$tools = Favorite::model()->getPropertyEnum('tool');

		// Les paniers ne s'ouvrent qu'aux structures productrices, et se règlent à la main d'un gestionnaire
		// (`Favorite::isOpen()`, `page/composition.p.php`) : une carte qui ouvrirait une page refusée ne
		// se montre pas.
		if(Favorite::isOpen($eStructure, Favorite::BASKET) === FALSE) {
			array_delete($tools, Favorite::BASKET);
		}

		// Les aides agricoles ne s'ouvrent qu'aux structures de leur bêta (`AidSetting::isActive()`).
		if(Favorite::isOpen($eStructure, Favorite::AID) === FALSE) {
			array_delete($tools, Favorite::AID);
		}

		$toolsUsed = array_intersect($tools, $eStructure->conf(\structure\Feature::class)['hasToolbox']);
		$toolsNew = array_diff($tools, $toolsUsed);

		$h = '<div id="toolbox-list" class="util-start-wrapper">';

			// La présentation de la Boîte (arbitrage Q17 B de la planche pf-l8-v6) ne sert qu'avant le
			// premier outil : elle disparaît dès qu'un outil a servi (23/09).
			if($toolsUsed === []) {
				$h .= new \main\DesignUi()->getStart(
					\Asset::icon('tools'),
					s("Votre boite à outils"),
					'<p>'.s("Des outils simples et indépendants. Ouvrez celui qui vous parle, il vous dit ce qu'il fait et comment commencer.").'</p>',
					image: \Asset::image('main', 'friends/abeille.svg')
				);
			}

			if($toolsUsed) {

				$h .= '<h2>'.s("Vos outils").'</h2>';
				$h .= $this->getCards($eStructure, $toolsUsed, $cFavorite, $cGauge, $cCompositionFormula, $treatments, $maintenances, $waters);

				if($toolsNew) {
					$h .= '<h2 class="mt-2">'.s("À essayer").'</h2>';
					$h .= $this->getCards($eStructure, $toolsNew, $cFavorite, $cGauge, $cCompositionFormula, $treatments, $maintenances, $waters);
				}

			} else {
				$h .= '<h2 class="mt-2">'.s("Déjà disponible").'</h2>';
				$h .= $this->getCards($eStructure, $toolsNew, $cFavorite, $cGauge, $cCompositionFormula, $treatments, $maintenances, $waters);
			}

			if(self::getPlanned() !== []) {
				$h .= '<h2 class="mt-2">'.s("Bientôt").'</h2>';
				$h .= $this->getPlannedCards();
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * « Ce que vous y trouverez » sur l'accueil d'un outil jamais utilisé : ce que l'outil montrera une
	 * fois nourri, une tuile par élément, chacune `[icône, titre, précision]`. Tout y est en retrait, le
	 * texte de présentation au-dessus et le bouton restant ce qui se lit d'abord (28/09).
	 */
	public function getPromises(array $promises): string {

		$h = '<div class="util-block-title">'.s("Ce que vous y trouverez").'</div>';
		$h .= '<div class="toolbox-promises">';

			foreach($promises as [$icon, $title, $text]) {

				$h .= '<div class="toolbox-promise">';
					$h .= '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon($icon).'</div>';
					$h .= '<div>';
						$h .= '<div class="toolbox-promise-title">'.$title.'</div>';
						$h .= '<div class="toolbox-promise-text">'.$text.'</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un outil prévu est une carte grisée qui ne mène nulle part : pas de lien, pas d'étoile.
	 */
	protected function getPlannedCards(): string {

		$h = '<div class="util-cards util-cards-2">';

			foreach(self::getPlanned() as [$icon, $name, $text]) {

				$h .= '<div class="util-deck toolbox-planned">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-icon util-icon-round util-icon-muted">'.\Asset::icon($icon).'</div>';
						$h .= '<div class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.$name.'</div>';
							$h .= '<p>'.$text.'</p>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getCards(\structure\Structure $eStructure, array $tools, \Collection $cFavorite, \Collection $cGauge, \Collection $cCompositionFormula, array $treatments, array $maintenances, array $waters): string {

		$h = '<div class="util-cards util-cards-2">';

			foreach($tools as $tool) {

				$used = in_array($tool, $eStructure->conf(\structure\Feature::class)['hasToolbox']);
				$figures = $used ? $this->getFigures($tool, $cGauge, $cCompositionFormula, $treatments, $maintenances, $waters) : '';
				$band = $used ? $this->getBand($tool, $maintenances) : '';

				$h .= '<div class="util-deck'.($used ? ' util-deck-open' : '').($band !== '' ? ' util-deck-accent-danger' : '').'">';
					$h .= '<div class="util-deck-head">';
						$h .= '<div class="util-icon util-icon-round '.($used ? 'util-icon-production' : 'util-icon-muted').'">'.\Asset::icon(self::getIcon($tool)).'</div>';
						$h .= '<a href="'.self::urlTool($eStructure, $tool).'" class="util-deck-body">';
							$h .= '<div class="util-deck-title">'.self::getName($tool).'</div>';
							// Un outil utilisé dont on a retiré toutes les données n'a plus de chiffres à montrer.
							if($figures !== '') {
								$h .= $figures;
							} else {
								$h .= '<p>'.self::getDescription($tool).'</p>';
							}
						$h .= '</a>';
						$star = $this->getStar($eStructure, $tool, $cFavorite->offsetExists($tool));
						if($star !== '') {
							$h .= '<div class="util-deck-aside">'.$star.'</div>';
						}
						// La tête mène à l'outil sans que rien ne le dise, l'étoile et les portes la faisant lire
						// comme un bloc d'information (retour du 23/09) : le chevron du deck lien le
						// montre, et mène au même endroit.
						$h .= '<a href="'.self::urlTool($eStructure, $tool).'" class="util-deck-arrow">'.\Asset::icon('chevron-right').'</a>';
					$h .= '</div>';
					$h .= $band;
					if($used) {
						$h .= $this->getDetail($eStructure, $tool, $cGauge);
						$h .= $this->getDoors($eStructure, $tool, $cGauge, $waters);
					}
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getFigures(string $tool, \Collection $cGauge, \Collection $cCompositionFormula, array $treatments, array $maintenances, array $waters): string {

		switch($tool) {

			case Favorite::BASKET :

				// Des paniers recommencés puis abandonnés au départ n'ont plus de composition à chiffrer.
				if($cCompositionFormula->empty()) {
					return '';
				}

				// Ce que la composition prévoit, jamais une vente (verdict sur la planche r2) : la
				// carte montre l'outil, pas l'activité de la ferme.
				$h = '<div class="util-deck-figures">';
					$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.s("Prix prévu").'</span><span class="util-deck-value">'.CompositionFormulaUi::getEnumeration(array_map(fn($price) => \util\TextUi::money((float)$price, precision: 0), $cCompositionFormula->getColumn('price'))).'</span></div>';
					$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.s("Paniers prévus par semaine").'</span><span class="util-deck-value">'.array_sum($cCompositionFormula->getColumn('baskets')).'</span></div>';
				$h .= '</div>';

				return $h;

			case Favorite::RAIN :

				// Un mm ne s'additionne pas d'un pluviomètre à l'autre : à plusieurs, chacun a sa ligne
				// dans le tableau sous la tête (arbitrage Q19 A de la planche pf-l8-v7).
				if($cGauge->count() > 1) {
					return '<p>'.p("{value} pluviomètre", "{value} pluviomètres", $cGauge->count()).'</p>';
				}

				// Un pluviomètre qui attend son premier relevé n'a pas de chiffres à montrer.
				if($cGauge->empty() or $cGauge->first()['lastDate'] === NULL) {
					return '';
				}

				$eGauge = $cGauge->first();

				$h = '<div class="util-deck-figures">';
					$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.$eGauge['cumulativeYear'].'</span><span class="util-deck-value">'.RainUi::getMm($eGauge['cumulative']).'</span></div>';
					$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.s("30 jours").'</span><span class="util-deck-value">'.RainUi::getMm($eGauge['cumulativeRecent']).'</span></div>';
					// Le dernier relevé se lit comme la liste de la page : son jour en libellé, ses gouttes et ses mm
					// en valeur (retour du 23/09).
					$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.\util\DateUi::textual($eGauge['lastDate'], \util\DateUi::DAY_MONTH).'</span><span class="util-deck-value">'.RainUi::getDrops($eGauge['lastRain']).' '.RainUi::getMm($eGauge['lastRain']).'</span></div>';
				$h .= '</div>';

				return $h;

			case Favorite::TREATMENT :

				// Une année qui commence n'a pas encore de traitement à chiffrer : la carte garde sa
				// description.
				if($treatments['count'] === 0) {
					return '';
				}

				$h = '<div class="util-deck-figures">';
					$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.s("Traitements en {value}", $treatments['year']).'</span><span class="util-deck-value">'.$treatments['count'].'</span></div>';
					$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.s("Dernier").'</span><span class="util-deck-value">'.TreatmentUi::getDone($treatments['eTaskLast']).'</span></div>';
				$h .= '</div>';

				return $h;

			// Les chiffres du carnet dans la même forme que ceux du registre et de la pluviométrie (retour de
			// du 01/10). Ce qui est dû n'y entre pas : la carte n'en garde que le rouge, en une ligne
			// (`getBand()`, arbitrage Q4 A de la planche pf-t4-v2, « pour éviter d'agrandir le bloc à côté »).
			// Le dernier entretien n'apparaît qu'une fois quelque chose fait.
			case Favorite::MAINTENANCE :

				if($maintenances['equipments'] === 0) {
					return '';
				}

				$h = '<div class="util-deck-figures">';
					$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.p("Matériel suivi", "Matériels suivis", $maintenances['equipments']).'</span><span class="util-deck-value">'.$maintenances['equipments'].'</span></div>';
					if($maintenances['lastDone'] !== NULL) {
						$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.s("Dernier entretien").'</span><span class="util-deck-value">'.EquipmentUi::getDate($maintenances['lastDone']).'</span></div>';
					}
				$h .= '</div>';

				return $h;

			// Les compteurs sont indépendants, leurs m³ s'additionnent : la carte dit l'année, tous compteurs
			// réunis, sous les intitulés courts de la pluviométrie. Avec un seul compteur, le dernier
			// relevé se lit comme dans sa liste, son jour puis ce qu'il a compté ; à plusieurs, ce chiffre
			// ne serait que celui d'un compteur, et la carte ne garde que le jour.
			case Favorite::WATER :

				if($waters['year'] === NULL) {
					return '';
				}

				$eWaterReadingLast = $waters['eWaterReadingLast'];

				$h = '<div class="util-deck-figures">';
					$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.\Clock::year().'</span><span class="util-deck-value">'.WaterReadingUi::getM3($waters['year']).'</span></div>';
					if($waters['cWaterMeter']->count() === 1) {
						$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.WaterReadingUi::getDay($eWaterReadingLast['date']).'</span><span class="util-deck-value">'.WaterReadingUi::getM3($eWaterReadingLast['consumption']).'</span></div>';
					} else {
						$h .= '<div class="util-deck-figure"><span class="util-deck-key">'.s("Dernier relevé").'</span><span class="util-deck-value">'.WaterReadingUi::getDay($eWaterReadingLast['date']).'</span></div>';
					}
				$h .= '</div>';

				return $h;

			case Favorite::DENSITY :
			case Favorite::FERTILIZATION :
			case Favorite::CATALOG :
			case Favorite::AID :
				return '';

		}

	}

	/**
	 * La bande d'état d'une carte, sous sa tête : les entretiens en retard du carnet du matériel, et
	 * rien quand aucun ne l'est. Elle teinte la carte en rouge (`util-deck-accent-danger`).
	 */
	protected function getBand(string $tool, array $maintenances): string {

		if($tool !== Favorite::MAINTENANCE or $maintenances['overdue'] === 0) {
			return '';
		}

		return '<div class="util-deck-band util-deck-band-state">'.p("{value} entretien en retard", "{value} entretiens en retard", $maintenances['overdue']).'</div>';

	}

	/**
	 * Le tableau des pluviomètres, quand la ferme en a plusieurs (arbitrage Q19 A de la planche
	 * pf-l8-v7) : il remplace les portes. Chaque ligne porte un geste, le +, donc c'est son nom qui
	 * mène au pluviomètre, et la ligne ne réagit pas au survol (convention des util-deck-row du design
	 * system). Le nom ouvre sa carte sur la page, le + ouvre « Nouveau relevé » sur lui.
	 */
	protected function getDetail(\structure\Structure $eStructure, string $tool, \Collection $cGauge): string {

		switch($tool) {

			case Favorite::BASKET :
			case Favorite::TREATMENT :
			case Favorite::DENSITY :
			case Favorite::FERTILIZATION :
			case Favorite::CATALOG :
			case Favorite::AID :
			case Favorite::MAINTENANCE :
			case Favorite::WATER :
				return '';

			case Favorite::RAIN :

				if($cGauge->count() < 2) {
					return '';
				}

				$h = '<div class="util-deck-fold">';
					$h .= '<div class="toolbox-gauges">';

						$h .= '<div class="toolbox-gauges-head">';
							$h .= '<div></div>';
							$h .= '<div class="util-table-header text-end">'.$cGauge->first()['cumulativeYear'].'</div>';
							$h .= '<div class="util-table-header text-end toolbox-gauges-recent">'.s("30 jours").'</div>';
							$h .= '<div class="util-table-header">'.s("Dernier relevé").'</div>';
							$h .= '<div></div>';
						$h .= '</div>';

						foreach($cGauge as $eGauge) {

							$h .= '<div class="util-deck-row">';
								$h .= '<a href="'.RainUi::urlGauge($eStructure, $eGauge).'" class="util-deck-row-name">'.GaugeUi::getName($eGauge).'</a>';
								if($eGauge['lastDate'] === NULL) {
									$h .= '<div class="toolbox-gauges-empty color-muted">'.s("pas encore de relevé").'</div>';
								} else {
									$h .= '<div class="text-end">'.RainUi::getMm($eGauge['cumulative']).'</div>';
									$h .= '<div class="text-end toolbox-gauges-recent">'.RainUi::getMm($eGauge['cumulativeRecent']).'</div>';
									$h .= '<div>'.\util\DateUi::textual($eGauge['lastDate'], \util\DateUi::DAY_MONTH).'</div>';
								}
								if($eGauge->assertUpdate()) {
									$h .= '<a href="'.RainUi::urlCreate($eStructure, $eGauge).'" class="btn btn-only-icon btn-sm btn-outline-primary" title="'.s("Nouveau relevé").'">'.\Asset::icon('plus-lg').'</a>';
								} else {
									$h .= '<div></div>';
								}
							$h .= '</div>';

						}

					$h .= '</div>';
				$h .= '</div>';

				return $h;

		}

	}

	protected function getDoors(\structure\Structure $eStructure, string $tool, \Collection $cGauge, array $waters): string {

		switch($tool) {

			// La tête de la carte mène à l'outil, qui porte lui-même ses onglets Composition et Synthèse :
			// les deux portes qui les répétaient sont retirées (retour du 29/09, r29).
			case Favorite::BASKET :
				return '';

			case Favorite::RAIN :
				// Sans pluviomètre actif, il n'y a ni relevé à lire ni pluviomètre à viser : la carte mène
				// à la page, qui propose d'en poser un (recette de M-Prod du 23/09). À plusieurs, le
				// tableau de getDetail() porte les gestes de chacun.
				if($cGauge->count() !== 1 or $cGauge->first()->assertUpdate() === FALSE) {
					return '';
				}

				// Un seul pluviomètre : la saisie rapide se fait sur la carte même, comme dans le pli d'un
				// pluviomètre de la page (retour du 23/09), et non derrière un bouton.
				$h = '<div class="util-deck-fold">';
					$h .= '<div class="util-deck-content">'.new RainUi()->getQuick($eStructure, $cGauge->first()).'</div>';
				$h .= '</div>';

				return $h;

			// Les produits s'ouvrent par la roue du carnet et par les paramètres de production, la carte
			// n'a pas de pied (retour du 27/09).
			case Favorite::TREATMENT :
				return '';

			// Les calculateurs et les recherches n'enregistrent rien : ils n'ont ni chiffres, ni détail, ni porte.
			case Favorite::DENSITY :
			case Favorite::FERTILIZATION :
			case Favorite::CATALOG :
			case Favorite::AID :
				return '';

			// Relever un compteur ou noter un entretien fait demande d'ouvrir le carnet (arbitrage Q4 A).
			case Favorite::MAINTENANCE :
				return '';

			// Un seul compteur se relève sur la carte, comme un seul pluviomètre. À plusieurs, la saisie
			// est sur la page, dans le rang de chaque compteur.
			case Favorite::WATER :

				if($waters['cWaterMeter']->count() !== 1 or $waters['cWaterMeter']->first()->assertUpdate() === FALSE) {
					return '';
				}

				$h = '<div class="util-deck-fold">';
					$h .= '<div class="util-deck-content">'.new WaterReadingUi()->getQuick($eStructure, $waters['cWaterMeter']->first()).'</div>';
				$h .= '</div>';

				return $h;

		}

	}

	/**
	 * Pas d'étoile sur un outil qui n'a pas encore servi (23/09) : il n'y a rien à mettre à
	 * portée de main. Un favori posé avant cette règle garde la sienne, sans quoi il ne s'enlèverait plus.
	 * Dans le menu de l'en-tête (`$menu`), l'étoile est une ligne du menu, `#toolbox-header-star`, que
	 * `view/favorite.v.php` rend à nouveau.
	 */
	public function getStar(\structure\Structure $eStructure, string $tool, bool $favorite, bool $menu = FALSE): string {

		if($favorite === FALSE and in_array($tool, $eStructure->conf(\structure\Feature::class)['hasToolbox']) === FALSE) {
			return '';
		}

		$attributes = 'data-ajax="/toolbox/favorite:doToggle" post-structure="'.$eStructure['id'].'" post-tool="'.$tool.'"';

		if($menu) {
			return '<a id="toolbox-header-star" '.$attributes.' class="dropdown-item">'.($favorite ? \Asset::icon('star-fill').' '.s("Retirer du menu") : \Asset::icon('star').' '.s("Ajouter au menu")).'</a>';
		}

		if($favorite) {
			return '<a '.$attributes.' class="btn btn-only-icon btn-sm btn-secondary" title="'.s("Retirer du menu").'">'.\Asset::icon('star-fill').'</a>';
		} else {
			return '<a '.$attributes.' class="btn btn-only-icon btn-sm btn-outline-primary" title="'.s("Ajouter au menu").'">'.\Asset::icon('star').'</a>';
		}

	}

}
?>
