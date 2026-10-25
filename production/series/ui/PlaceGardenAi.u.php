<?php
namespace series;

/**
 * Vue « Par jardin » du plan d'assolement.
 *
 * Colonnes = planches, axe vertical = temps : la fenêtre calendaire de la
 * structure est projetée en pourcentages sur la hauteur de chaque colonne, en
 * réutilisant le positionnement en semaines de PlaceUi (positionPlace), le
 * même qui sert à la chronologie horizontale. Le flux d'assolement est
 * inchangé : mêmes champs beds[] / sizes[], même formulaire place:doUpdate.
 */
class PlaceGardenAiUi extends PlaceUi {

	private Series|Task $ePlaced;

	private int $firstWeekShown = 0;
	private int $lastWeekShown = 100;

	// Hauteur minimale d'un bloc, en pourcentage de la colonne : en deçà il
	// resterait illisible et incliquable. Le fantôme, le bouton de pose et le
	// cadre d'arrivée s'y tiennent aussi, sinon ils cessent de se superposer.
	const BLOCK_HEIGHT_MIN = 3.5;

	// Voies dessinées au maximum dans une colonne « Surface libre » : au-delà,
	// la dernière porte le compte de ce qui n'y tient pas.
	const FILL_LANES_MAX = 4;

	// Largeur d'une voie de surface libre, en rem (88 px à 16, la maquette).
	const FILL_LANE_WIDTH = 6.05;

	// Nombre de planches à partir duquel un emplacement mérite sa mini-carte

	public function __construct() {

		parent::__construct();

		\Asset::css('series', 'placeGardenAi.css');

		// La grille par jardin emprunte le bandeau de parcelle à map : sans cette
		// feuille il sort nu sur les pages qui ne rendent que le jardin, comme
		// place:update. getGarden() la chargeait par effet de bord, en construisant
		// un ZoneUi dont il ne se servait pas.
		\Asset::css('map', 'zone.css');

		$this->ePlaced = new Series();

	}

	public function setPlaced(Series|Task $e): static {

		$this->ePlaced = $e;

		return $this;

	}

	/**
	 * Les bornes et les réglages internes dont le rendu d'une colonne dépend.
	 * Extrait de getGarden() pour qu'une réponse ciblée puisse rendre une colonne
	 * seule sans repasser par le plan entier.
	 *
	 * @return array{0: int, 1: int} les bornes de la tranche affichée
	 */
	public function prepareGarden(\structure\Structure $eStructure, \Collection $cZone, int $season): array {

		[$startTs, $stopTs] = $this->prepareGardenWindow($eStructure, $season);

		foreach($cZone as $eZone) {
			foreach($eZone['cPlot'] as $ePlot) {
				$this->putGardenMerge($ePlot['cBed'], $season, $startTs, $stopTs);
				$this->putGardenLanes($ePlot['cBed'], $season, $startTs, $stopTs);
				$this->putGardenMergeLength($ePlot['cBed']);
			}
		}

		return [$startTs, $stopTs];

	}

	/**
	 * La fenêtre affichée : ses bornes en secondes, et en semaines pour positionPlace(),
	 * les mêmes que PlaceUi::getTimeline(). Séparée de prepareGarden() parce que le
	 * paquet d'état n'a besoin que d'elle : il ne dessine aucun bloc, donc ni fusion
	 * ni voie.
	 *
	 * @return array{0: int, 1: int}
	 */
	private function prepareGardenWindow(\structure\Structure $eStructure, int $season): array {

		[$startTs, $stopTs] = $this->getBounds($eStructure->conf(\map\Configuration::class), $season);

		[$this->firstWeekShown, $this->lastWeekShown] = $this->getWeeksShown($eStructure, $season);

		return [$startTs, $stopTs];

	}

	/**
	 * La couleur du fantôme : celle de l'espèce qu'on place. Le bac la pose aussi
	 * côté client, mais le panneau d'une série n'a pas de bac.
	 */
	private function getGardenPaint(\structure\Structure $eStructure): ?string {

		if($this->ePlaced->empty() or ($this->ePlaced instanceof Series) === FALSE) {
			return NULL;
		}

		return $this->getGardenColor($eStructure, $this->ePlaced['cCultivation'] ?? new \Collection());

	}

	/**
	 * Ce qu'un placement change sur un plan déjà dessiné, pour SeriesPlacement.applyGarden() :
	 * la série armée et sa couleur, la tranche de temps du fantôme et du bouton de pose,
	 * et par planche les attributs de test, l'alerte de largeur et le bouton de pose.
	 * Le client applique tel quel, il ne décide rien : chaque valeur sort de la méthode
	 * qui l'écrit dans le rendu complet (getGardenSlice(), isGardenTarget(),
	 * getWidthConfirm(), BedUi::getTestAttributes()), et le témoin ?full=1 de
	 * place:update permet de comparer les deux rendus.
	 *
	 * Pourquoi un paquet et non le plan : redessiner le jardin coûtait 722 Ko et 7 770
	 * nœuds pour une série qui arme 272 planches, quand le clic ne change que des
	 * attributs et deux fragments par colonne. Mesuré le 05/09/2026 sur la ferme 7.
	 */
	public function getGardenState(\structure\Structure $eStructure, \Collection $cZone, int $season): array {

		[$startTs, $stopTs] = $this->prepareGardenWindow($eStructure, $season);

		$slice = $this->getGardenSlice($season, $startTs, $stopTs);
		$isPlaceable = ($slice !== NULL and $this->isGardenPlaceable());

		$eSeries = ($this->ePlaced instanceof Series) ? $this->ePlaced : NULL;

		$state = [
			'series' => ($eSeries !== NULL and $eSeries->notEmpty()) ? $eSeries['id'] : NULL,
			'task' => ($this->ePlaced instanceof Task and $this->ePlaced->notEmpty()) ? $this->ePlaced['id'] : NULL,
			'paint' => $this->getGardenPaint($eStructure),
			'slice' => $isPlaceable ? ['top' => round($slice[0], 2), 'height' => round($slice[1], 2)] : NULL,
			'label' => s("+ Ajouter"),
			'alert' => \Asset::icon('exclamation-circle'),
			'beds' => [],
		];

		// Même règle que getGardenBed() : l'alerte de largeur ne concerne qu'une série
		// en planches dont la largeur est connue, et jamais une surface libre, qui n'a
		// pas de largeur à comparer.
		$isBedWidth = (
			$eSeries !== NULL and
			$eSeries->notEmpty() and
			$eSeries['use'] === Series::BED and
			$eSeries['bedWidth'] !== NULL
		);

		foreach($cZone as $eZone) {

			foreach($eZone['cPlot'] as $ePlot) {

				foreach($ePlot['cBed'] as $eBed) {

					$state['beds'][$eBed['id']] = [
						'a' => \map\BedUi::getTestAttributes($eBed),
						'w' => ($isBedWidth and $eBed['plotFill'] === FALSE and $eBed['zoneFill'] === FALSE and $eSeries['bedWidth'] !== $eBed['width']),
						'p' => ($isPlaceable and $this->isGardenTarget($eBed)) ? self::getWidthConfirm($eBed, $eSeries) : NULL,
					];

				}

			}

		}

		return $state;

	}

	public function getGarden(\structure\Structure $eStructure, \Collection $cZone, \map\Zone $eZoneSelected, int $season): string {

		[$startTs, $stopTs] = $this->prepareGarden($eStructure, $cZone, $season);

		$months = $this->getGardenMonths($startTs, $stopTs, $season);

		$paint = $this->getGardenPaint($eStructure);

		// Le plan dit lui-même quelle série il arme. Sans cela, la seule source
		// était la fiche du bac : hors bac, ou bac replié, les boutons Poser
		// restaient affichés sans que rien ne sache quoi poser.
		$armed = '';

		if($this->ePlaced->notEmpty()) {
			$armed = ($this->ePlaced instanceof Task) ?
				' data-task="'.$this->ePlaced['id'].'"' :
				' data-series="'.$this->ePlaced['id'].'" data-use="'.$this->ePlaced['use'].'" data-name="'.encode($this->ePlaced['name']).'"';
		}

		$h = '<div id="zone-container"'.$armed.($paint === NULL ? '' : ' style="--target-paint: '.$paint.'"').'>';

			$h .= '<div id="zone-content-garden" data-start="'.$startTs.'" data-stop="'.$stopTs.'" style="--garden-months: '.count($months).'">';

				$h .= $this->getGardenZones($eStructure, $cZone, $eZoneSelected, $season, $months, $startTs, $stopTs);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La même grille, pour le papier. Elle ne redessine rien : c'est getGardenZones() qui
	 * rend les deux, sans quoi le PDF serait un troisième décalque figé qui prendrait du
	 * retard sur l'écran à la première retouche : le défaut qu'on retire par ailleurs de
	 * ZoneUi::getPrinting().
	 *
	 * Le @page vit ici et non dans la feuille du package : c'est une règle de document,
	 * elle ne se scope pas sous un sélecteur, et placeGardenAi.css est chargée sur l'écran
	 * d'assolement, où elle gouvernerait le Ctrl+P.
	 */
	public function getPrinting(\structure\Structure $eStructure, \Collection $cZone, int $season): string {

		[$startTs, $stopTs] = $this->prepareGarden($eStructure, $cZone, $season);

		$months = $this->getGardenMonths($startTs, $stopTs, $season);

		$h = \main\PdfUi::getPage('0.75cm');

		$h .= '<style>';
			$h .= 'html { font-size: 8px !important; }';
		$h .= '</style>';

		// L'axe est linéaire en jours : une trame de semaines s'y pose donc à pas
		// constant, 7 jours sur la durée totale, et ne dérive pas comme le ferait une
		// division du nombre de mois.
		$days = max(1, (int)round(($stopTs - $startTs) / 86400));

		$h .= '<div id="zone-container" class="garden-printing">';

			$h .= '<div id="zone-content-garden" data-start="'.$startTs.'" data-stop="'.$stopTs.'" style="--garden-months: '.count($months).'; --garden-days: '.$days.'">';

				$h .= $this->getGardenZones($eStructure, $cZone, new \map\Zone(), $season, $months, $startTs, $stopTs, print: TRUE);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les parcelles et leurs jardins, partagés par l'écran et par le papier. Le papier
	 * ne masque aucune parcelle et n'a pas de bandeau vert : celui-ci ne porte que des
	 * gestes.
	 */
	private function getGardenZones(\structure\Structure $eStructure, \Collection $cZone, \map\Zone $eZoneSelected, int $season, array $months, int $startTs, int $stopTs, bool $print = FALSE): string {

		$h = '';

		foreach($cZone as $eZone) {

			$zone = '';

				// Une parcelle qui a des jardins nommés n'a pas besoin de montrer
				// son hors-jardin vide : il n'apprend rien et prend la place
				// d'un vrai jardin. Un bouton du bandeau le rappelle.
				$named = 0;

				foreach($eZone['cPlot'] as $ePlotNamed) {

					if($ePlotNamed['name'] !== NULL) {
						$named++;
					}

				}

				$outside = [];
				$plots = '';

				foreach($eZone['cPlot'] as $ePlot) {

					$isOutside = ($named > 0 and $this->isPlotEmptyOutside($ePlot, $season, $startTs, $stopTs));

					if($isOutside) {
						$outside[] = $ePlot['id'];
					}

					// Un hors-jardin vide est masqué par la feuille dans les deux rendus.
					// Sur le papier, l'écrire quand même ne coûte que du poids : à la
					// structure 4109, 17 cartes sur 19 et 204 colonnes pour rien.
					if($print and $isOutside) {
						continue;
					}

					$plots .= $print ?
						$this->getGardenPlotParts($eStructure, $ePlot, $season, $months, $startTs, $stopTs, $isOutside, $named > 0) :
						$this->getGardenPlot($eStructure, $ePlot, $season, $months, $startTs, $stopTs, $isOutside, $named > 0);

				}

				// Une parcelle sans une seule carte n'apprend rien sur le papier : son
				// en-tête resterait seul dans le vide. 118 parcelles sur 759 étaient
				// dans ce cas.
				if($print and $plots === '') {
					continue;
				}

				if($print) {

					// Les mêmes trois informations que la Liste, mais pas sa géométrie : sa
					// gouttière de 4,5 cm est faite pour le paysage, et le Plan empile ses
					// parcelles en portrait.
					$zone .= '<div class="garden-printing-zone">';
						$zone .= '<span class="color-muted">'.s("Parcelle").'</span>';
						$zone .= '<h2>'.encode($eZone['name']).'</h2>';
						$zone .= '<span class="color-muted">'.$eZone->getArea().'</span>';
					$zone .= '</div>';

				} else {
					$zone .= $this->getGardenZoneBar($eZone, count($outside));
				}

				$zone .= '<div class="garden-plots">'.$plots.'</div>';

			$h .= '<div class="zone-wrapper" data-zone="'.$eZone['id'].'" data-path-zone="'.encode($eZone['name']).'" data-soil-color="'.$eStructure->getView('viewSoilColor').'">'.$zone.'</div>';

		}

		return $h;

	}

	/**
	 * Le bandeau vert d'une parcelle : son nom, sa surface, ce qu'elle contient.
	 */
	protected function getGardenZoneBar(\map\Zone $eZone, int $outside = 0): string {

		$greenhouses = 0;

		foreach($eZone['cPlot'] as $ePlot) {

			if($ePlot['mode'] === \map\Plot::GREENHOUSE) {
				$greenhouses++;
			}

		}

		$h = '<div class="place-zone-bar">';
			$h .= '<b>'.s("Parcelle {value}", encode($eZone['name'])).'</b>';
			$h .= '<span class="place-zone-bar-info">'.$eZone->getArea().'</span>';

			$parts = [];

			if($greenhouses > 0) {
				$parts[] = p("{value} abri", "{value} abris", $greenhouses);
			}

			if($parts !== []) {
				$h .= '<span class="place-zone-bar-info">· '.implode(' · ', $parts).'</span>';
			}

			if($outside > 0) {
				$h .= '<a onclick="SeriesPlace.toggleOutside(this)" class="btn btn-sm btn-transparent place-zone-bar-outside">'.p("Afficher le hors jardin", "Afficher les hors jardin", $outside).'</a>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Un emplacement sans nom et sans culture : ce n'est pas un jardin, c'est ce
	 * qui reste de la parcelle quand on en a nommé.
	 */
	protected function isPlotEmptyOutside(\map\Plot $ePlot, int $season, int $startTs, int $stopTs): bool {

		if($ePlot['name'] !== NULL) {
			return FALSE;
		}

		// Même règle que hasGardenSeries() : ce qui compte est ce que le rendu
		// produit, pas ce que la saison contient. Filtrer sur la saison écartait des
		// emplacements qui dessinent des blocs de saisons voisines.
		foreach($ePlot['cBed'] as $eBed) {

			if($this->hasGardenSeries($eBed, $season, $startTs, $stopTs)) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * D1, arbitré le 24/08 : sur le papier, un jardin large ne se fait plus trancher au
	 * bord de la page. Il se découpe en tranches de GARDEN_PRINT_COLUMNS colonnes, la
	 * première portant le nom du jardin, les suivantes le même nom suivi de « (suite) ».
	 * La largeur de colonne reste donc constante quel que soit le jardin, ce qui est la
	 * raison d'être de cette direction : deux jardins restent comparables à l'œil.
	 * Le prix, accepté avec elle : un grand jardin ne tient plus sous un seul regard.
	 */
	private const GARDEN_PRINT_COLUMNS = 12;

	private function getGardenPlotParts(\structure\Structure $eStructure, \map\Plot $ePlot, int $season, array $months, int $startTs, int $stopTs, bool $isOutside, bool $named): string {

		$cBed = $ePlot['cBed']->find(fn(\map\Bed $eBed) => $eBed['plotFill'] === FALSE and $eBed['zoneFill'] === FALSE);
		$cBedFill = $ePlot['cBed']->find(fn(\map\Bed $eBed) => $eBed['plotFill'] or $eBed['zoneFill']);

		// Une surface libre qui ne porte rien n'apprend rien sur le papier : elle prend
		// une à quatre largeurs de colonne pour dire « vide ». À l'écran elle reste,
		// c'est une cible de pose.
		foreach($cBedFill as $eBedFill) {

			if($this->hasGardenSeries($eBedFill, $season, $startTs, $stopTs)) {
				$cBed[$eBedFill['id']] = $eBedFill;
			}

		}

		if($cBed->empty()) {
			return '';
		}

		// On pèse en largeurs, jamais en planches : une planche à N voies occupe N
		// largeurs de colonne (getGardenColumnWidth()). C'est vrai ici comme dans la
		// boucle, et ne le peser qu'à un des deux endroits laissait passer tous les
		// débordements réels de la base : un jardin de douze planches ou moins dont
		// la surface libre porte plusieurs séries n'était jamais mesuré.
		$width = 0;

		foreach($cBed as $eBedWidth) {
			$width += max(1, $eBedWidth['lanesDrawn'] ?? 1);
		}

		// La collection est passée même sans découpe : c'est elle qui porte le retrait
		// des surfaces libres vides.
		if($width <= self::GARDEN_PRINT_COLUMNS) {
			return $this->getGardenPart($eStructure, $ePlot, $season, $months, $startTs, $stopTs, $isOutside, $named, $cBed, 0);
		}

		$parts = [[]];
		$width = 0;

		foreach($cBed as $bedId => $eBed) {

			$lanes = max(1, $eBed['lanesDrawn'] ?? 1);

			// Le garde sur la tranche courante évite une tranche vide si une planche
			// pesait à elle seule plus que la page. FILL_LANES_MAX la borne à 4, donc
			// le cas ne se produit pas aujourd'hui ; la boucle ne s'appuie pas dessus.
			if($parts[count($parts) - 1] and $width + $lanes > self::GARDEN_PRINT_COLUMNS) {
				$parts[] = [];
				$width = 0;
			}

			$parts[count($parts) - 1][$bedId] = $lanes;
			$width += $lanes;

		}

		$parts = $this->balanceGardenParts($parts, $cBed);

		$h = '';

		foreach($parts as $part => $partLanes) {

			$cBedPart = new \Collection();

			foreach(array_keys($partLanes) as $bedId) {
				$cBedPart[$bedId] = $cBed[$bedId];
			}

			$h .= $this->getGardenPart($eStructure, $ePlot, $season, $months, $startTs, $stopTs, $isOutside, $named, $cBedPart, $part);

		}

		return $h;

	}

	/**
	 * Une surface libre porte-t-elle quelque chose de visible ? La question doit se poser
	 * exactement comme le rendu la pose, sinon on écarte ce que le document aurait montré.
	 * Deux erreurs successives ici, et la même à chaque fois : une condition plus étroite
	 * que ce que le rendu produit.
	 * 1. Filtrer sur la saison était faux, la fenêtre affichée débordant des deux côtés :
	 *    284 blocs et 246 séries disparaissaient sur 93 structures, toutes à 18 mois ou plus.
	 * 2. N'interroger que les blocs était faux aussi : getGardenBed() rend AUSSI la liste
	 *    « Sans dates », dont la condition est le complément exact de la première. 250 lignes
	 *    et 194 séries disparaissaient sur 83 structures. Sur 1 681 colonnes rendues, 151 ne
	 *    portent que cette liste : c'est la population qu'on écartait.
	 */
	private function hasGardenSeries(\map\Bed $eBed, int $season, int $startTs, int $stopTs): bool {

		if($this->getGardenVisibleSeries($eBed, $season, $startTs, $stopTs) !== []) {
			return TRUE;
		}

		// 3. Ne compter que les séries était faux : getGardenBed() rend aussi les blocs
		//    d'intervention hors série, qui ne portent aucune série à compter.
		if($this->hasGardenTask($eBed, $startTs, $stopTs)) {
			return TRUE;
		}

		// getGardenBed() rend deux choses, pas une : des blocs, et la liste « Sans
		// dates » dont la condition est le complément exact de la précédente. Ne
		// tester que les blocs écartait les planches qui ne portent que cette liste.
		// On interroge donc le rendu plutôt que de recopier sa règle, comme
		// getGardenNoDate() le dit lui-même de getGardenBounds().
		return ($this->getGardenNoDate($eBed, $season, $startTs, $stopTs) !== '');

	}

	/**
	 * La surface libre est la dernière colonne d'un jardin et pèse 2 à 4 largeurs :
	 * remplir à 12 puis laisser le reste la fait basculer seule dans une tranche.
	 * Règle tranchée par le maquetteur : elle ne doit jamais s'y retrouver seule.
	 * On recule donc la frontière, la dernière tranche reprenant des planches à celle
	 * qui la précède tant que les deux tiennent sous le budget et que la dernière ne
	 * devient pas la plus grosse. Mesuré sur la base : 105 tranches à surface libre
	 * seule, 0 après.
	 *
	 * Ce que la règle ne fait pas, et qu'il ne faut pas lui prêter : elle ne vide pas
	 * les cartes sans culture. Trois jardins gardent une première carte vide parce que
	 * tout leur contenu est sur la surface libre. C'est voulu, une planche sans culture
	 * étant une information. À l'échelle des 105, les cartes sans bloc passent de 67
	 * à 18.
	 *
	 * Aucune planche n'est masquée et aucune tranche ne dépasse : ce sont les deux
	 * écueils des pistes écartées.
	 */
	private function balanceGardenParts(array $parts, \Collection $cBed): array {

		$last = count($parts) - 1;

		if($last < 1) {
			return $parts;
		}

		// On ne rééquilibre que le cas visé : une dernière tranche qui ne porte que de
		// la surface libre. Une découpe déjà saine ne se touche pas, sans quoi on
		// déshabillerait une tranche pleine pour remplir la dernière.
		foreach(array_keys($parts[$last]) as $bedId) {

			if($cBed[$bedId]['plotFill'] === FALSE and $cBed[$bedId]['zoneFill'] === FALSE) {
				return $parts;
			}

		}

		while(TRUE) {

			$previous = array_keys($parts[$last - 1]);

			// La tranche d'avant garde toujours au moins une planche.
			if(count($previous) <= 1) {
				break;
			}

			$bedId = end($previous);
			$lanes = $parts[$last - 1][$bedId];

			if(array_sum($parts[$last]) + $lanes > self::GARDEN_PRINT_COLUMNS) {
				break;
			}

			// On équilibre, on ne remplit pas : dès que le transfert rendrait la
			// dernière tranche plus grosse que celle d'avant, on s'arrête. Sans ça la
			// frontière reculait jusqu'à ne laisser qu'une colonne devant.
			if(array_sum($parts[$last]) + $lanes > array_sum($parts[$last - 1]) - $lanes) {
				break;
			}

			unset($parts[$last - 1][$bedId]);
			$parts[$last] = [$bedId => $lanes] + $parts[$last];

		}

		return $parts;

	}

	/**
	 * Une tranche se recalcule entièrement sur elle-même. prepareGarden() a posé les
	 * fusions sur le jardin **entier** : un bloc étiré sur 20 planches gardait un span
	 * de 20 dans une grille de 12, sortait de sa carte en absolu, et les planches qu'il
	 * couvrait restaient vides sur la tranche suivante. Rejouer les trois passes sur la
	 * seule tranche redonne à chacune des fusions bornées à ce qu'elle montre, et rend
	 * son bloc à toute planche qui était « couverte » depuis une tranche précédente.
	 */
	private function getGardenPart(\structure\Structure $eStructure, \map\Plot $ePlot, int $season, array $months, int $startTs, int $stopTs, bool $isOutside, bool $named, \Collection $cBedPart, int $part): string {

		$this->putGardenMerge($cBedPart, $season, $startTs, $stopTs);
		$this->putGardenLanes($cBedPart, $season, $startTs, $stopTs);
		$this->putGardenMergeLength($cBedPart);

		return $this->getGardenPlot($eStructure, $ePlot, $season, $months, $startTs, $stopTs, $isOutside, $named, $cBedPart, $part);

	}

	public function getGardenPlot(\structure\Structure $eStructure, \map\Plot $ePlot, int $season, array $months, int $startTs, int $stopTs, bool $isOutside = FALSE, bool $named = FALSE, ?\Collection $cBedPart = NULL, int $part = 0): string {

		// Les planches réelles d'abord, la surface libre en dernière colonne : elle
		// porte les séries posées à la surface plutôt que sur une planche, et les
		// passer sous silence ferait disparaître ces séries du plan.
		$cBed = $ePlot['cBed']->find(fn(\map\Bed $eBed) => $eBed['plotFill'] === FALSE and $eBed['zoneFill'] === FALSE);
		$cBedFill = $ePlot['cBed']->find(fn(\map\Bed $eBed) => $eBed['plotFill'] or $eBed['zoneFill']);

		if($cBed->empty() and $cBedFill->empty()) {
			return '';
		}

		foreach($cBedFill as $eBedFill) {
			$cBed[$eBedFill['id']] = $eBedFill;
		}

		// D1 : une tranche de jardin plutôt que le jardin entier. L'appelant a déjà
		// découpé, on rend la tranche telle quelle.
		if($cBedPart !== NULL) {
			$cBed = $cBedPart;
		}

		$suffix = ($part > 0) ? '-'.$part : '';

		$h = '<div id="'.self::getGardenPlotId($ePlot).$suffix.'" class="plot-wrapper util-card garden-plot '.($isOutside ? 'garden-plot-outside' : '').'" data-plot="'.$ePlot['id'].'" style="--plot-columns: '.self::getGardenColumnUnits($cBed).'" '.self::getPlotPath($ePlot, $cBed->count()).'>';

		$h .= '<div class="plot-folded" onclick="SeriesPlace.unfoldPlot(this)">';

			$h .= match($ePlot['mode']) {
				\map\Plot::GREENHOUSE => '<span class="badge badge-greenhouse">'.s("Abri").'</span>',
				default => '<span class="badge badge-production">'.s("Plein champ").'</span>',
			};

			// Même règle que le titre déplié : un emplacement sans nom s'appelle
			// « Hors jardin » quand la parcelle a de vrais jardins, et ne
			// s'appelle pas du tout quand elle n'en a pas — il n'y a alors que
			// la parcelle, et la nommer deux fois n'apprend rien.
			$foldedName = ($ePlot['name'] === NULL) ?
				($named ? s("Hors jardin") : '') :
				\map\PlotUi::getName($ePlot);

			if($foldedName !== '') {
				$h .= '<div class="plot-folded-name">'.encode($foldedName).'</div>';
			}

			$h .= '<div class="plot-folded-info">'.p("{value} planche", "{value} planches", $cBed->count()).'</div>';

			$h .= '<span class="plot-folded-chevron">'.\Asset::icon('chevron-down').'</span>';
		$h .= '</div>';

			$h .= '<div class="garden-plot-title">';
				$h .= \map\PlotUi::getTitle($ePlot, $named);
				if($part > 0) {
					$h .= '<span class="garden-plot-part">'.s("(suite)").'</span>';
				}
				$h .= match($ePlot['mode']) {
					\map\Plot::GREENHOUSE => '<span class="badge badge-greenhouse">'.s("Abri").'</span>',
					default => '<span class="badge badge-production">'.s("Plein champ").'</span>',
				};

				// Le titre replie deja le jardin au clic, mais rien ne le disait : le
				// bandeau replie porte son chevron, l'etat ouvert n'en avait aucun.
				$h .= '<span class="garden-plot-chevron">'.\Asset::icon('chevron-up').'</span>';
			$h .= '</div>';

			$columns = self::getGardenColumns($cBed);

			$h .= '<div class="garden-scroll">';

				$h .= '<div id="'.self::getGardenGridId($ePlot).$suffix.'" class="garden-grid" style="grid-template-columns: '.$columns.'">';

					$h .= '<div class="garden-rail">';
						$h .= '<div class="garden-col-head"></div>';
						$h .= '<div class="garden-rail-body">';
							foreach($months as $month) {
								$h .= '<div class="garden-month'.($month['first'] ? ' garden-month-first' : '').($month['past'] ? ' garden-month-past' : '').'" style="top: '.$month['top'].'%; height: '.$month['height'].'%">'.$month['label'].'</div>';
							}
						$h .= '</div>';
					$h .= '</div>';

					foreach($cBed as $eBed) {
						$h .= $this->getGardenBed($eStructure, $eBed, $season, $startTs, $stopTs);
					}


				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les séries que cette planche porte sans qu'elles se dessinent nulle part :
	 * un bloc collé en pied de colonne, une ligne par série. Il est plafonné à la
	 * moitié de la colonne et défile en interne — une surface libre en porte
	 * jusqu'à 198, et la liste entière couvrirait dix écrans.
	 */
	/**
	 * Ce que la surface libre annonce : sa taille, ce qu'elle porte déjà, et ce
	 * qui reste. Le compte de séries a été retiré : la surface engagée dit la
	 * même chose dans l'unité qui sert au moment de poser.
	 */
	/**
	 * Ce que la surface libre annonce dans son en-tête : une seule ligne, celle
	 * qui sert au moment de poser. Le reste est en infobulle, et le récapitulatif
	 * complet descend dans le corps quand la colonne est vide.
	 *
	 * Un en-tête plus haut que celui des colonnes voisines décale leur corps : les
	 * lignes de mois cessent alors d'être alignées d'une colonne à l'autre.
	 */
	protected function getGardenFillHead(\map\Bed $eBed): string {

		$posed = isset($eBed['areaPosed']) ? (int)round($eBed['areaPosed']) : 0;

		if($posed === 0 or isset($eBed['areaRemaining']) === FALSE) {
			return '';
		}

		$remaining = (int)round($eBed['areaRemaining']);

		$title = implode(' · ', array_filter([
			$eBed['area'] ? s("{value} m²", $eBed['area']) : NULL,
			p("{value} m² engagé", "{value} m² engagés", $posed, ['value' => $posed]),
		]));

		return '<span title="'.encode($title).'">'.p("{value} m² restant", "{value} m² restants", $remaining, ['value' => $remaining]).'</span>';

	}

	/**
	 * Le récapitulatif d'une surface libre qui ne porte rien : son corps est vide
	 * sur toute la hauteur, autant y mettre ce que l'en-tête ne peut pas dire sans
	 * grandir.
	 */
	protected function getGardenFillBody(\map\Bed $eBed): string {

		if(($eBed['seriesCount'] ?? 0) > 0 or $eBed['area'] === NULL) {
			return '';
		}

		return '<div class="garden-fill-summary">'.s("{value} m²", $eBed['area']).'</div>';

	}

	protected function getGardenNoDate(\map\Bed $eBed, int $season, int $startTs, int $stopTs): string {

		$cSeries = [];

		foreach($eBed['cPlace'] as $ePlace) {

			$eSeries = $ePlace['series'];

			// Seules celles qui ne se dessinent nulle part, et c'est getGardenBounds
			// qui le sait : une pérenne sans dates se place quand même sur l'année
			// entière. Recopier la règle ici la ferait diverger au premier changement.
			if(
				$eSeries->empty() or
				$eSeries['season'] !== $season or
				$this->getGardenBounds($eSeries, $season, $startTs, $stopTs) !== NULL
			) {
				continue;
			}

			$cSeries[] = $eSeries;

		}

		if($cSeries === []) {
			return '';
		}

		$h = '<div class="place-nodate-block">';

			if(count($cSeries) > 1) {
				$h .= '<span class="place-nodate-head">'.s("Sans dates · {value}", count($cSeries)).'</span>';
			}

			$h .= '<div class="place-nodate-list">';

				foreach($cSeries as $eSeries) {
					$h .= '<a class="place-nodate-line" data-series="'.$eSeries['id'].'" data-bed="'.$eBed['id'].'" title="'.encode($eSeries['name']).'">'.encode($eSeries['name']).'</a>';
				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bouton qui pose la série sur cette planche sans avoir à la glisser. Il
	 * vit dans le cadre d'arrivée, donc seulement pendant un placement et sur les
	 * seules planches où la pose a un sens : pas celles que la série occupe déjà.
	 */
	/**
	 * La tranche de temps que la série en cours de placement occupe, en haut et
	 * hauteur exprimés en pourcentage de la colonne, ou NULL si elle ne se dessine
	 * pas. Les mêmes bornes minimales qu'un bloc : sans elles, le fantôme, le
	 * bouton et le cadre d'arrivée d'une série courte ne se superposent plus.
	 *
	 * @return array{0: float, 1: float}|NULL
	 */
	protected function getGardenSlice(int $season, int $startTs, int $stopTs): ?array {

		if($this->ePlaced->empty()) {
			return NULL;
		}

		// Le jardin dessine le temps de haut en bas : une intervention y occupe la
		// bande de sa semaine, comme elle occupe une colonne de semaine en chronologie.
		$bounds = ($this->ePlaced instanceof Task) ?
			$this->getTaskBounds($this->ePlaced, $startTs, $stopTs) :
			$this->getGardenBounds($this->ePlaced, $season, $startTs, $stopTs);

		if($bounds === NULL) {
			return NULL;
		}

		[$top, $bottom] = $bounds;

		$height = max($bottom - $top, self::BLOCK_HEIGHT_MIN);

		return [min($top, 100 - $height), $height];

	}

	/**
	 * Une planche est une cible tant que la série n'y est pas déjà posée.
	 */
	/**
	 * Une série clôturée ne s'assole plus : ni bouton, ni fantôme.
	 */
	// Une intervention hors série s'assole comme une série : la page lui pose déjà
	// use = BED et une saison, et seule une série peut être clôturée.
	protected function isGardenPlaceable(): bool {

		if($this->ePlaced->empty()) {
			return FALSE;
		}

		if($this->ePlaced instanceof Task) {
			return TRUE;
		}

		return ($this->ePlaced['status'] !== Series::CLOSED);

	}

	protected function isGardenTarget(\map\Bed $eBed): bool {

		foreach($eBed['cPlace'] as $ePlace) {

			if(self::isPlaceOf($ePlace, $this->ePlaced)) {
				return FALSE;
			}

		}

		return TRUE;

	}

	// Rien à confirmer hors d'une série en planches, ni quand l'une des deux largeurs
	// est inconnue : on ne compare que ce qu'on connaît des deux côtés.
	public static function getWidthConfirm(\map\Bed $eBed, ?\series\Series $eSeries = NULL): string {

		if(
			$eSeries === NULL or
			$eSeries->empty() or
			$eSeries['use'] !== Series::BED or
			$eSeries['bedWidth'] === NULL or
			$eBed['width'] === NULL or
			$eBed['width'] === $eSeries['bedWidth']
		) {
			return '';
		}

		return ' data-confirm="'.encode(s("Cette planche fait {bed} cm, la série est prévue sur des planches de {series} cm. L'assoler ici quand même ?", ['bed' => $eBed['width'], 'series' => $eSeries['bedWidth']])).'"';

	}

	protected function getGardenPose(\map\Bed $eBed, int $season, int $startTs, int $stopTs): string {

		$slice = $this->getGardenSlice($season, $startTs, $stopTs);

		if($slice === NULL or $this->isGardenPlaceable() === FALSE or $this->isGardenTarget($eBed) === FALSE) {
			return '';
		}

		// Toujours « + Poser » : ce que la planche porte déjà ne change rien au
		// geste, et annoncer un reste laissait croire que le bouton en dépendait.
		// La largeur, elle, se signale : le filtre laisse désormais voir toutes les
		// planches, une pose hors largeur doit donc être un choix et non une méprise.
		return '<a class="place-pose" data-bed="'.$eBed['id'].'"'.self::getWidthConfirm($eBed, ($this->ePlaced instanceof Series) ? $this->ePlaced : NULL).' style="top: '.round($slice[0], 2).'%">'.s("+ Ajouter").'</a>';

	}

	/**
	 * Le fantôme : sur chaque planche candidate, la place que la série prendrait
	 * si on l'y posait. Il ne se clique pas et ne porte pas de nom — on sait ce
	 * qu'on tient, et le bouton posé dessus dit ce qu'il reste.
	 */
	protected function getGardenGhost(\map\Bed $eBed, int $season, int $startTs, int $stopTs): string {

		$slice = $this->getGardenSlice($season, $startTs, $stopTs);

		if($slice === NULL or $this->isGardenPlaceable() === FALSE or $this->isGardenTarget($eBed) === FALSE) {
			return '';
		}

		// Le fantôme ne dépend jamais de ce que la planche porte déjà, pas plus que
		// le bouton Poser : la variante « reste » le réduisait à la moitié droite
		// de la planche, dans une autre couleur, pour la même promesse de geste.
		return '<div class="place-garden-block place-garden-block-placeholder" style="top: '.round($slice[0], 2).'%; height: '.round($slice[1], 2).'%"></div>';

	}

	/**
	 * Deux séries qui se chevauchent dans le temps sur la même planche se
	 * partagent sa largeur, comme la chronologie les répartit sur des lignes :
	 * sans cela la plus tardive recouvre l'autre et la cache tout à fait.
	 */
	protected function putGardenLanes(\Collection $cBed, int $season, int $startTs, int $stopTs): void {

		foreach($cBed as $eBed) {

			$isFill = ($eBed['plotFill'] or $eBed['zoneFill']);

			$blocks = [];

			foreach($eBed['cPlace'] as $ePlace) {

				$bounds = $this->getGardenBounds($ePlace['series'], $season, $startTs, $stopTs);

				if($bounds === NULL) {
					continue;
				}

				// Un bloc étiré sur plusieurs planches ne se partage pas la largeur
				// de l'une d'elles : il la traverse entière. Le compter ici scindait
				// ses voisines en deux pour lui laisser une place qu'il ne prend pas.
				$seriesId = $ePlace['series']['id'];

				if(
					isset($eBed['mergeSpan'][$seriesId]) or
					isset($eBed['mergeSkip'][$seriesId])
				) {
					continue;
				}

				$blocks[] = [
					'series' => $ePlace['series']['id'],
					'top' => $bounds[0],
					'bottom' => $bounds[1],
				];

			}

			// La plus précoce prend la voie de gauche
			usort($blocks, fn($a, $b) => $a['top'] <=> $b['top']);

			$lanes = [];
			$assigned = [];

			foreach($blocks as $block) {

				$placed = FALSE;

				foreach($lanes as $lane => $bottom) {

					if($block['top'] >= $bottom) {
						$lanes[$lane] = $block['bottom'];
						$assigned[$block['series']] = $lane;
						$placed = TRUE;
						break;
					}

				}

				if($placed === FALSE) {
					$lanes[] = $block['bottom'];
					$assigned[$block['series']] = count($lanes) - 1;
				}

			}

			$eBed['lanes'] = max(1, count($lanes));
			$eBed['lane'] = $assigned;

			if($isFill === FALSE) {
				continue;
			}

			// Au-delà du plafond, on ne dessine que les premières voies : la
			// dernière colonne devient le compte de ce qui n'y tient pas. Mesuré
			// sur la base : 12 % des surfaces libres dépassent, jusqu'à 1 936
			// séries simultanées, soit 170 000 px de défilement interne.
			// La colonne s'arrête à quatre voies de large et défile en interne :
			// toutes les séries restent dessinées, aucune n'est comptée à part.
			$eBed['lanesDrawn'] = min($eBed['lanes'], self::FILL_LANES_MAX);
			$eBed['lanesScroll'] = ($eBed['lanes'] > self::FILL_LANES_MAX);

			// Une place existe aussi pour les saisons voisines : les compter faisait
			// annoncer 18 séries là où le panneau de la planche en liste 14. Le
			// compte et la somme des surfaces portent donc sur la même liste.
			$cPlaceSeason = $eBed['cPlace']->find(
				fn(\series\Place $ePlace) => $ePlace['season'] === $season and $ePlace['series']->notEmpty()
			);

			$eBed['seriesCount'] = $cPlaceSeason->count();
			$eBed['seriesHidden'] = $eBed['seriesCount'] - count($eBed['lane']);

			// Le reste ne s'annonce que s'il est vrai : 19 % des séries posées sur
			// une surface libre n'ont pas de surface enregistrée, et les compter
			// pour zéro ferait afficher de la place qui n'existe pas. Une planche
			// qui ne porte rien, elle, n'a rien d'inconnu : elle annonce 0 engagé
			// et sa surface entière restante.
			$posed = 0;
			$known = TRUE;

			foreach($cPlaceSeason as $ePlace) {

				if(($ePlace['area'] ?? 0) <= 0) {
					$known = FALSE;
					break;
				}

				$posed += (float)$ePlace['area'];

			}

			if($known and $eBed['area']) {
				$eBed['areaPosed'] = $posed;
				$eBed['areaRemaining'] = max(0, (float)$eBed['area'] - $posed);
			}

		}

	}

	/**
	 * Le haut et le bas d'un bloc en pourcentage de la hauteur, ou NULL quand la
	 * série ne produit pas de bloc visible.
	 */
	protected function getGardenBounds(Series $eSeries, int $season, int $startTs, int $stopTs): ?array {

		return $this->getSeriesBounds($eSeries, $season, $startTs, $stopTs, $this->firstWeekShown, $this->lastWeekShown);

	}

	/**
	 * Les planches que l'utilisateur a détachées de leur groupe, par série.
	 * L'état vit dans le navigateur et voyage avec la requête : il ne survit
	 * pas au rechargement et rien n'est écrit en base.
	 */
	protected array $ungrouped = [];

	public function setUngrouped(array $ungrouped): static {

		$this->ungrouped = $ungrouped;

		return $this;

	}

	protected function isUngrouped(int $bedId, int $seriesId): bool {

		return in_array($bedId, $this->ungrouped[$seriesId] ?? [], TRUE);

	}

	/**
	 * Une surface libre ne se fusionne jamais avec ses voisines : elle se pose
	 * explicitement, par son propre bouton, et sa colonne a une largeur fixe
	 * qu'un bloc étiré ne saurait pas additionner.
	 */
	/**
	 * Ce qu'un bloc étiré porte réellement : la somme des longueurs assolées
	 * sur les planches qu'il couvre. L'étiquette de sélection décrit le bloc,
	 * pas la série, et un bloc fusionné n'a que la longueur de son ancrage.
	 */
	protected function putGardenMergeLength(\Collection $cBed): void {

		$beds = array_values($cBed->getIds());

		foreach($beds as $index => $bedId) {

			$lengths = [];

			$parts = [];

			foreach(($cBed[$bedId]['mergeSpan'] ?? []) as $seriesId => $span) {

				$total = 0;
				$list = [];

				for($next = $index; $next < min(count($beds), $index + $span); $next++) {

					$length = 0;

					foreach($cBed[$beds[$next]]['cPlace'] as $ePlace) {

						if($ePlace['series']->notEmpty() and $ePlace['series']['id'] === $seriesId) {
							$length += (int)($ePlace['length'] ?? 0);
						}

					}

					$total += $length;
					$list[] = [$beds[$next], $length, (int)($cBed[$beds[$next]]['length'] ?? 0)];

				}

				$lengths[$seriesId] = $total;
				$parts[$seriesId] = $list;

			}

			$cBed[$bedId]['mergeLength'] = $lengths;
			$cBed[$bedId]['mergeParts'] = $parts;

		}

	}

	protected function isFillBed(\map\Bed $eBed): bool {

		return ($eBed['plotFill'] or $eBed['zoneFill']);

	}

	protected function putGardenMerge(\Collection $cBed, int $season, int $startTs, int $stopTs): void {

		$beds = array_values($cBed->getIds());

		// Les séries dont le bloc est réellement rendu sur chaque planche : une
		// place existe aussi pour les saisons voisines, et une série sans dates
		// ne produit aucun bloc — les compter ferait fusionner du vide.
		$visible = [];

		foreach($beds as $bedId) {
			$visible[$bedId] = $this->getGardenVisibleSeries($cBed[$bedId], $season, $startTs, $stopTs);
		}

		$spans = [];
		$skip = [];

		foreach($beds as $index => $bedId) {

			if($this->isFillBed($cBed[$bedId]) or ($cBed[$bedId]['lanesDrawn'] ?? 0) > 1) {
				continue;
			}

			foreach($visible[$bedId] as $seriesId) {

				if(isset($skip[$bedId][$seriesId])) {
					continue;
				}

				$span = 1;

				// Une planche dissociée ne fusionne ni vers l'avant ni vers
				// l'arrière : elle reprend sa place dans le calcul des voies,
				// ce qui est précisément ce que « Dissocier » demande.
				for($next = $index + 1; $next < count($beds) and $this->isUngrouped($bedId, $seriesId) === FALSE; $next++) {

					if(in_array($seriesId, $visible[$beds[$next]], TRUE) === FALSE) {
						break;
					}

					if($this->isFillBed($cBed[$beds[$next]]) or ($cBed[$beds[$next]]['lanesDrawn'] ?? 0) > 1) {
						break;
					}

					if($this->isUngrouped($beds[$next], $seriesId)) {
						break;
					}

					$skip[$beds[$next]][$seriesId] = TRUE;
					$span++;

				}

				if($span > 1) {
					$spans[$bedId][$seriesId] = $span;
				}

			}

		}

		foreach($cBed as $eBed) {
			$eBed['mergeSpan'] = $spans[$eBed['id']] ?? [];
			$eBed['mergeSkip'] = $skip[$eBed['id']] ?? [];
		}

	}

	/**
	 * Les séries qui produisent un bloc visible sur cette planche, aux mêmes
	 * conditions que getGardenBlock().
	 */
	protected function getGardenVisibleSeries(\map\Bed $eBed, int $season, int $startTs, int $stopTs): array {

		$series = [];

		foreach($eBed['cPlace'] as $ePlace) {

			if($ePlace['series']->empty()) {
				continue;
			}

			if($this->getGardenBounds($ePlace['series'], $season, $startTs, $stopTs) !== NULL) {
				$series[] = $ePlace['series']['id'];
			}

		}

		return $series;

	}

	/**
	 * Cette planche porte-t-elle un bloc d'intervention hors série ? La question est
	 * posée à part et ne rejoint pas getGardenVisibleSeries() : un id de tâche et un
	 * id de série viennent de deux tables et peuvent être égaux. Mêlés dans la même
	 * liste, ils se confondaient dans mergeSpan/mergeSkip et dans isUngrouped(), qui
	 * ne connaissent que des séries.
	 */
	private function hasGardenTask(\map\Bed $eBed, int $startTs, int $stopTs): bool {

		foreach($eBed['cPlace'] as $ePlace) {

			if($ePlace['series']->notEmpty()) {
				continue;
			}

			// On interroge le rendu plutôt que de recopier sa règle, comme
			// hasGardenSeries() le fait déjà de getGardenNoDate() : la fenêtre affichée
			// et la semaine de l'intervention n'ont ainsi qu'un seul juge.
			if($this->getGardenTask($ePlace, $eBed, $startTs, $stopTs) !== '') {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Ce que la barre collante affichera quand cet emplacement sera à l'écran.
	 * Posé ici plutôt que relu du DOM : le JS n'a alors rien à deviner.
	 */
	/**
	 * La pastille de verrouillage : blanche, lisible sur toute couleur de bloc,
	 * posée dans le coin d'état. Le coin est une variable pour que la convention
	 * (état à gauche, action à droite) tienne en une déclaration.
	 */
	public static function getLockBadge(Series $eSeries, int $season): string {

		$reason = \series\PlaceUi::getLockReason($eSeries, $season);

		return '<span class="place-lock-badge" title="'.encode($reason ?? '').'">'.\Asset::icon('lock-fill').'</span>';

	}

	public static function getPlotPath(\map\Plot $ePlot, int $beds): string {

		return
			'data-path-plot="'.encode(\map\PlotUi::getName($ePlot)).'" '.
			'data-path-beds="'.$beds.'"';

	}

	/**
	 * La largeur des colonnes d'une parcelle. Elle dépend de **toutes** ses
	 * planches : un geste qui n'en change qu'une doit quand même la renvoyer, sinon
	 * la colonne élargie s'affiche à l'ancienne largeur.
	 */
	public static function getGardenColumnUnits(\Collection $cBed): int {

		$units = 0;

		foreach($cBed as $eBedColumn) {

			$units += ($eBedColumn['plotFill'] === FALSE and $eBedColumn['zoneFill'] === FALSE) ?
				1 :
				max(1, $eBedColumn['lanesDrawn'] ?? 1);

		}

		return $units;

	}

	public static function getGardenColumns(\Collection $cBed): string {

		// Les pistes sont en « auto » et la largeur est portée par la colonne
		// elle-même. Une piste fixe ne rétrécit pas quand son contenu rétrécit :
		// une planche écartée par un filtre gardait ses 112 px de vide alors que
		// la règle de repli lui donne 0,875 rem.
		foreach($cBed as $eBedColumn) {
			$eBedColumn['columnWidth'] = self::getGardenColumnWidth($eBedColumn);
		}

		return 'var(--garden-gutter-width)'.str_repeat(' auto', $cBed->count());

	}

	/**
	 * La largeur d'une colonne. Toutes les planches ont la même, quel que soit le
	 * nombre de séries qu'elles portent : les séries simultanées se partagent
	 * cette largeur au lieu de l'étirer. Un seul réglage, --garden-col-width.
	 */
	protected static function getGardenColumnWidth(\map\Bed $eBed): string {

		if($eBed['plotFill'] === FALSE and $eBed['zoneFill'] === FALSE) {
			return 'var(--garden-col-width)';
		}

		// Une surface libre vaut autant de largeurs de planche qu'elle porte de
		// voies, et une largeur pleine quand elle ne porte rien : une culture
		// posée dessus a la largeur d'une planche comme partout ailleurs.
		return 'calc('.max(1, $eBed['lanesDrawn'] ?? 1).' * var(--garden-col-width))';

	}

	public static function getGardenGridId(\map\Plot $ePlot): string {
		return 'garden-grid-'.$ePlot['id'];
	}

	public static function getGardenPlotId(\map\Plot $ePlot): string {
		return 'garden-plot-'.$ePlot['id'];
	}

	public function getGardenBed(\structure\Structure $eStructure, \map\Bed $eBed, int $season, int $startTs, int $stopTs): string {

		$form = new \util\FormUi();

		$isUpdating = $this->ePlaced->notEmpty();

		if($isUpdating) {
			$ePlaceUpdate = $this->ePlaced['cPlace'][$eBed['id']] ?? new Place();
		} else {
			$ePlaceUpdate = new Place();
		}

		$attributes = new \map\BedUi()->getBedTest($eBed);

		$isBedWidth = (
			$isUpdating and
			$this->ePlaced instanceof Series and
			$this->ePlaced['use'] === Series::BED
		);

		$isFill = ($eBed['plotFill'] or $eBed['zoneFill']);

		$widthDiffers = (
			$isBedWidth and
			$isFill === FALSE and
			$this->ePlaced['bedWidth'] !== NULL and
			$this->ePlaced['bedWidth'] !== $eBed['width']
		);

		$lanesDrawn = $eBed['lanesDrawn'] ?? 0;

		$h = '<div id="'.\map\BedUi::getBedLineId($eBed).'" class="garden-col '.(($eBed['plotFill'] or $eBed['zoneFill']) ? 'garden-col-fill' : '').' '.(($eBed['lanesScroll'] ?? FALSE) ? 'garden-col-scroll' : '').'" style="--col-width: '.($eBed['columnWidth'] ?? 'var(--garden-col-width)').'" '.($lanesDrawn > 1 ? 'data-lanes="'.$lanesDrawn.'" ' : '').$attributes.'>';

			$h .= '<div class="garden-col-head">';

				$h .= '<div class="garden-col-name">';

					if($isFill) {
						$h .= '<b>'.s("Surface libre").'</b>';
					} else {
						// Le chevron est un pseudo-élément de la feuille : 272 icônes svg
						// identiques pesaient 41 Ko et 544 nœuds sur le plan de la ferme 7.
						$h .= '<a class="place-bed-panel" data-bed="'.$eBed['id'].'" data-season="'.$season.'"><b>'.encode($eBed['name']).'</b></a>';
					}

				$h .= '</div>';

				$h .= '<div class="garden-col-width '.($widthDiffers ? 'garden-col-width-alert' : '').'">';
					if($isFill) {
						$h .= $this->getGardenFillHead($eBed);
					} else {
						if($widthDiffers) {
							$h .= \Asset::icon('exclamation-circle').' ';
						}
						$h .= s("{value} cm", $eBed['width']);
					}
				$h .= '</div>';

			$h .= '</div>';

			$h .= '<div class="garden-col-body">';

				if($isFill) {
					$h .= $this->getGardenFillBody($eBed);
				}

				foreach($eBed['cPlace'] as $ePlace) {

					// Une intervention hors série n'a pas de série à indexer : ni voie, ni
					// fusion, ni « Sans dates ». Elle rend son bloc sur sa seule planche,
					// exactement comme getTaskTimeline() le fait à l'horizontale. La sortir
					// de la boucle par un continue était le seul motif pour lequel
					// getGardenBlock() n'était jamais atteint pour elle.
					if($ePlace['series']->empty()) {

						$h .= $this->getGardenBlock($eStructure, $eBed, $season, $ePlace, $startTs, $stopTs);

						continue;

					}

					// La suite d'un groupe fusionné : le bloc est porté par sa première planche
					if(isset($eBed['mergeSkip'][$ePlace['series']['id']])) {
						continue;
					}

					$span = $eBed['mergeSpan'][$ePlace['series']['id']] ?? 1;

					$h .= $this->getGardenBlock($eStructure, $eBed, $season, $ePlace, $startTs, $stopTs, $span);

				}

				$h .= $this->getGardenNoDate($eBed, $season, $startTs, $stopTs);

				$h .= $this->getGardenGhost($eBed, $season, $startTs, $stopTs);
				$h .= $this->getGardenPose($eBed, $season, $startTs, $stopTs);

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	// Le pendant vertical de PlaceUi::getTaskTimeline() : le jardin dessine le temps
	// de haut en bas, l'intervention y occupe donc la bande de sa semaine.
	protected function getGardenTask(Place $ePlace, \map\Bed $eBed, int $startTs, int $stopTs): string {

		$eTask = $ePlace['task'] ?? new Task();

		if($eTask->empty()) {
			return '';
		}

		// Même refus que getTaskTimeline() : une place existe aussi pour les saisons
		// voisines, et une semaine hors de la fenêtre affichée n'a pas de bande où se
		// poser. Sans ce contrôle, getTaskBounds() retombe sur [0, 100] et l'intervention
		// couvre la colonne entière — mesuré : 16 blocs pleine hauteur sur la structure 7,
		// tous des interventions de 2025 vues depuis le plan 2026.
		$week = $eTask['doneWeek'] ?? $eTask['plannedWeek'] ?? NULL;

		if(
			$week === NULL or
			strtotime(week_date_ends($week)) <= $startTs or
			strtotime(week_date_starts($week)) >= $stopTs
		) {
			return '';
		}

		$bounds = $this->getTaskBounds($eTask, $startTs, $stopTs);

		if($bounds === NULL) {
			return '';
		}

		[$top, $bottom] = $bounds;

		$height = max($bottom - $top, self::BLOCK_HEIGHT_MIN);
		$top = min($top, 100 - $height);

		$name = $eTask['action']->notEmpty() ? $eTask['action']['name'] : s("Intervention");

		$isCurrent = PlaceUi::isPlaceOf($ePlace, $this->ePlaced);

		$h = '<div class="place-garden-block place-garden-block-task'.($isCurrent ? ' place-current' : '').'" style="top: '.$top.'%; height: '.$height.'%;" data-task="'.$eTask['id'].'" data-span="1" data-bed="'.$eBed['id'].'">';
			$h .= '<span class="place-garden-block-name">'.encode($name).'</span>';
		$h .= '</div>';

		return $h;

	}

	protected function getGardenBlock(\structure\Structure $eStructure, \map\Bed $eBed, int $season, Place $ePlace, int $startTs, int $stopTs, int $span = 1): string {

		$this->positionPlace($ePlace, $season, $this->firstWeekShown, $this->lastWeekShown);

		if(
			$ePlace['positionStart'] === NULL or
			$ePlace['positionStop'] === NULL
		) {
			return '';
		}

		if($ePlace['series']->empty()) {
			return $this->getGardenTask($ePlace, $eBed, $startTs, $stopTs);
		}

		$eSeries = $ePlace['series'];
		$cCultivation = $eSeries['cCultivation'] ?? new \Collection();

		$minWeek = NULL;
		$maxWeek = NULL;
		$minTs = $this->positionToWeek($eSeries, $ePlace['positionStart'], $season, 0, $minWeek);
		$maxTs = $this->positionToWeek($eSeries, $ePlace['positionStop'], $season, 6, $maxWeek);

		$top = max(0.0, ($minTs - $startTs) / ($stopTs - $startTs) * 100);
		$bottom = min(100.0, ($maxTs - $startTs) / ($stopTs - $startTs) * 100);

		if($bottom <= $top) {
			return '';
		}

		$height = max($bottom - $top, self::BLOCK_HEIGHT_MIN);
		$top = min($top, 100 - $height);

		$style = 'top: '.$top.'%; height: '.$height.'%;';

		// Deux séries qui se chevauchent s'empilent par date de mise en place :
		// la plus récente passe devant, sinon elle disparaît sous la précédente.
		$style .= 'z-index: '.self::getGardenDepth($ePlace, $season).';';

		// Sur une planche, une culture prend toujours la largeur entière : deux
		// séries simultanées se décalent l'une sur l'autre. Sur une surface libre
		// au contraire, elles ne se superposent jamais et se rangent côte à côte,
		// chacune sa voie — c'est le seul endroit où la largeur se partage.
		$lane = $eBed['lane'][$eSeries['id']] ?? 0;
		$lanesDrawn = $eBed['lanesDrawn'] ?? 0;

		// La géométrie de voie et de fusion est la même formule pour tous les blocs :
		// elle vit dans la feuille (.place-garden-block-lane, -merged, -offset) et le
		// bloc ne porte que ses deux nombres. En style, elle pesait 170 caractères par
		// bloc fusionné sur un plan de 722 Ko.
		//
		// Le décalage total est plafonné à 60 % de la colonne, pas le pas : à 34 px
		// d'impression, 12 px par voie sortaient de la planche dès la voie 3, et
		// plafonner le pas laissait encore sortir la voie 4.
		// À l'écran le plafond vaut 67 px et joue donc à partir de la voie 6 :
		// 183 blocs sur 20 structures s'y décalent, dont 74 qui sortaient de leur
		// colonne et y rentrent. Changement d'écran assumé, pas un effet de bord.
		//
		// Un groupe de N planches fait exactement N fois la largeur de colonne, en
		// largeurs de colonne et non en pourcentage : le pourcentage se calcule sur le
		// corps de la colonne, un pixel plus étroit que sa piste, et les jauges des
		// parts, elles posées en largeurs de colonne, sortaient du bloc de trois
		// pixels au bout d'un groupe de trois.
		$laneClass = '';

		if($lanesDrawn > 1) {
			$laneClass = ' place-garden-block-lane';
			$style .= '--lane: '.$lane.';';
		} else if($span > 1) {
			$style .= '--lane: '.$lane.'; --span: '.$span.';';
		} else if($lane > 0) {
			$laneClass = ' place-garden-block-offset';
			$style .= '--lane: '.$lane.';';
		}

		$period = $this->getGardenPeriod($minTs, $maxTs);

		$class = 'place-garden-block '.(($season === $eSeries['season']) ? 'place-garden-block-season' : 'place-garden-block-not-season').$laneClass;

		$isPerennial = ($eSeries['cycle'] === Series::PERENNIAL);

		if($isPerennial) {
			$class .= ' place-garden-block-perennial';
		}

		$color = $this->getGardenColor($eStructure, $cCultivation);

		// Les couleurs claires et sombres sont portées par le CSS, comme dans
		// la chronologie : seules les couleurs d'espèce ou de famille sont
		// posées ici, elles viennent de la donnée. Une pérenne la porte aussi :
		// son hachurage est translucide et la laisse voir, sans quoi toutes les
		// pérennes du plan se ressemblent.
		if($color !== NULL) {
			$style .= 'background-color: '.$color.';';
		}

		$style .= '--block-color: '.$this->getGardenBorderColor($cCultivation).';';

		// L'encre du bloc, choisie sur la luminance de son fond : le chiffre de la
		// jauge s'écrit dessus quand la pastille est transparente, et le blanc
		// disparaissait sur les espèces claires.
		$style .= '--block-ink: '.SeriesUi::getSelectorInk($color ?? '#ffffff').';';

		if($span > 1) {
			$class .= ' place-garden-block-merged';
		}

		// La règle vit dans PlaceUi : elle était en deux exemplaires, et son motif dans un
		// seul, si bien que la chronologie annonçait « Série clôturée » sur une série
		// d'une autre saison — 203 blocs verrouillés sans motif, dont 25 séries ouvertes.
		$isLocked = self::isLocked($eSeries, $season);

		if($isLocked) {
			$class .= ' place-locked';
		}


		if(
			$this->ePlaced->notEmpty() and
			$eSeries->is($this->ePlaced)
		) {
			$class .= ' place-current';
		}

		$h = '<div class="'.$class.'" style="'.$style.'" data-series="'.$eSeries['id'].'" data-span="'.$span.'" data-bed="'.$eBed['id'].'" data-length="'.($ePlace['length'] ?? '').'"'.(($span > 1) ? ' data-block-length="'.($eBed['mergeLength'][$eSeries['id']] ?? 0).'"' : '').' data-bed-length="'.($eBed['length'] ?? 0).'" '.($isLocked ? 'data-locked="1" data-locked-status="'.$eSeries['status'].'"' : '').PlaceUi::getSearchAttributes($eSeries, $cCultivation).'>';

			// Quand la couleur du bloc ne vient pas de l'espèce, plus rien ne dit
			// laquelle c'est au premier coup d'œil : la vignette le dit, comme en
			// chronologie.
			if($isLocked) {
				$h .= self::getLockBadge($eSeries, $season);
			}

			// Vignette et nom sur la même ligne : empilés, ils prenaient deux
			// lignes de haut sur des blocs qui n'en ont parfois que trois.
			$h .= '<div class="place-garden-block-head">';

				if($eStructure->getView('viewSoilColor') !== \structure\Member::PLANT) {

					$h .= '<div class="place-garden-block-images">';

						foreach($cCultivation as $eCultivation) {
							$h .= \plant\PlantUi::getVignette($eCultivation['plant'], '1.1rem');
						}

					$h .= '</div>';

				}

				$h .= '<b>'.encode($eSeries['name']).'</b>';

			$h .= '</div>';

			// La part de planche occupée se lit sur le bloc : une jauge en pied, et
			// le chiffre au-dessus dès qu'il diffère de la longueur de la planche.
			// Un bloc fusionné n'a rien à en dire : la longueur qu'il porte est
			// celle d'une seule de ses planches, pas de l'ensemble.
			$h .= self::getBlockFill($eSeries, $ePlace, $eBed, $span);

			// Les repères de semis, plantation et récolte, comme en chronologie mais
			// sur l'axe vertical : ici le temps descend.
			$h .= self::getSoilActionsBars(
				self::getSoilActions($eStructure, $eSeries, $cCultivation, $minWeek, $maxWeek),
				$minTs, $maxTs,
				vertical: TRUE
			);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que la série occupe sur la planche, quand elle n'y tient pas toute la
	 * place : le chiffre, et une jauge en pied qui le donne d'un coup d'œil sur
	 * toute une parcelle sans avoir à lire.
	 */
	public static function getBlockFill(Series $eSeries, Place $ePlace, \map\Bed $eBed, int $span): string {

		// Sur une surface libre, plusieurs séries se partagent la même surface :
		// un rapport à la surface totale ne voudrait rien dire. On y montre donc
		// ce qui est assolé, seul, et en tout temps. Le test porte sur la planche
		// et non sur l'usage de la série : une série « sur planches » posée sur une
		// surface libre y occupe une surface, pas une longueur, et n'affichait donc
		// rien du tout.
		if($eSeries['use'] !== Series::BED or $eBed['plotFill'] or $eBed['zoneFill']) {

			$length = $ePlace['length'] ?? NULL;
			$area = $ePlace['area'] ?? NULL;

			// Une série qui s'assole en planches dit son linéaire, les autres leur
			// surface. Le linéaire d'abord : c'est dans cette unité que la série est
			// pensée, la surface n'est là que faute de mieux.
			$value = ($eSeries['use'] === Series::BED and $length > 0) ?
				s("{value} mL", $length) :
				(($area > 0) ? s("{value} m²", $area) : NULL);

			if($value === NULL) {
				return '';
			}

			$h = '<span class="place-fill place-fill-area" data-bed="'.$eBed['id'].'">';
				$h .= '<b>'.$value.'</b>';
			$h .= '</span>';

			return $h;

		}

		// Un bloc étiré ne dit pas une moyenne : chaque planche qu'il couvre
		// annonce ce qui y est assolé, sur sa part de la largeur, séparée des
		// voisines par un pointillé rendu ici et non plus à la sélection.
		$parts = $eBed['mergeParts'][$eSeries['id']] ?? [];

		if($span > 1 and $parts !== []) {

			// Les parts se posent en largeurs de colonne, pas en pourcentage du
			// bloc : pendant un redimensionnement le bloc s'étire, et une part
			// exprimée en pourcentage glisserait hors de sa planche. La formule est
			// dans la feuille, la part ne porte que son rang.
			$h = '';

			foreach($parts as $index => [$partBedId, $partLength, $partTotal]) {

				$h .= '<i class="place-bed-part" style="--part: '.$index.'"></i>';

				$bar = self::getFillBar($partBedId, $partLength, $partTotal, 'place-fill-part');

				if($bar !== '') {
					$h .= str_replace('<span class="place-fill', '<span style="--part: '.$index.'" class="place-fill', $bar);
				}

			}

			return $h;

		}

		return self::getBlockFillBar($eSeries, $ePlace, $eBed);

	}

	/**
	 * La jauge, chiffre compris. Une barre et un jeton côte à côte disaient deux
	 * fois la même chose : le chiffre est écrit dans la barre, qui garde en tout
	 * temps la taille qu'elle n'avait qu'au survol. Rendue même à 100 % — il n'y a
	 * alors rien à montrer, mais toujours de quoi réduire.
	 */
	protected static function getBlockFillBar(Series $eSeries, Place $ePlace, \map\Bed $eBed): string {

		if($eSeries['use'] !== Series::BED) {
			return '';
		}

		return self::getFillBar($eBed['id'], $ePlace['length'] ?? NULL, $eBed['length'] ?? NULL);

	}

	/**
	 * La barre elle-même. Une planche assolée en entier n'a rien à annoncer :
	 * sa jauge ne sert plus qu'à réduire, elle n'apparaît donc qu'à la
	 * sélection. Partiellement assolée, elle montre en tout temps ce qu'il y
	 * reste.
	 */
	protected static function getFillBar(int $bedId, ?int $length, ?int $total, string $extra = ''): string {

		if(
			$length === NULL or
			$total === NULL or
			$total <= 0
		) {
			return '';
		}

		$isFull = ($length >= $total);

		$h = '<span class="place-fill '.$extra.' '.($isFull ? 'place-fill-full' : '').'" data-bed="'.$bedId.'" data-length="'.$length.'">';
			$h .= '<i style="width: '.round(min(100, $length / $total * 100)).'%"></i>';
			$h .= '<b>'.s("{value} / {total} mL", ['value' => $length, 'total' => $total]).'</b>';
		$h .= '</span>';

		return $h;

	}

	/**
	 * La profondeur d'un bloc : les séries de la saison passent devant celles des
	 * saisons voisines, et entre elles la plus tardive passe devant.
	 */
	protected static function getGardenDepth(Place $ePlace, int $season): int {

		$start = $ePlace['positionStart'] ?? 0;

		if($ePlace['series']['season'] !== $season) {
			return 1;
		}

		return 10 + max(0, min(200, (int)$start + 60));

	}

	/**
	 * La couleur d'un bloc : celle de la famille de la première culture, comme
	 * sur la carte des rotations. Beaucoup de familles n'ont pas de couleur en
	 * base, d'où le repli sur un gris foncé plutôt qu'un aplat noir.
	 */
	/**
	 * La couleur de l'espèce, quel que soit le réglage d'affichage : en vue
	 * claire et en vue sombre le fond ne la porte pas, la bordure si.
	 */
	protected function getGardenBorderColor(\Collection $cCultivation): string {

		$color = $cCultivation->first()['plant']['color'] ?? NULL;

		// Sans couleur d'espèce, un contour gris plutôt que rien : en vue claire
		// un bloc sans bordure se confond avec le fond de la planche.
		return $color ?: 'var(--muted)';

	}

	protected function getGardenColor(\structure\Structure $eStructure, \Collection $cCultivation): ?string {

		$view = $eStructure->getView('viewSoilColor');

		if($view === \structure\Member::PLANT) {

			$color = $cCultivation->first()['plant']['color'] ?? NULL;

			return $color ?: self::FAMILY_FALLBACK;

		}

		if($view !== \structure\Member::FAMILY) {
			return NULL;
		}

		foreach($cCultivation as $eCultivation) {

			$color = self::getFamilyColor($eCultivation);

			if($color !== self::FAMILY_FALLBACK) {
				return $color;
			}

		}

		return self::FAMILY_FALLBACK;

	}

	protected function getGardenPeriod(int $minTs, int $maxTs): string {

		$start = self::getGardenMonthName((int)date('n', $minTs));
		$stop = self::getGardenMonthName((int)date('n', $maxTs));

		if($start === $stop) {
			return $start;
		}

		return $start.' → '.$stop;

	}

	public function getGardenMonths(int $startTs, int $stopTs, int $season): array {

		$months = [];

		$time = $startTs;

		while($time < $stopTs) {

			$next = strtotime(date('Y-m-01', $time).' first day of next month');

			$months[] = [
				'label' => self::getGardenMonthName((int)date('n', $time)),
				'top' => ($time - $startTs) / ($stopTs - $startTs) * 100,
				'height' => (min($next, $stopTs) - $time) / ($stopTs - $startTs) * 100,
				'first' => (date('n', $time) === '1'),
				'past' => ((int)date('Y', $time) < $season),
			];

			$time = $next;

		}

		return $months;

	}

	protected static function getGardenMonthName(int $month): string {

		return match($month) {
			1 => s("janv."),
			2 => s("févr."),
			3 => s("mars"),
			4 => s("avr."),
			5 => s("mai"),
			6 => s("juin"),
			7 => s("juil."),
			8 => s("août"),
			9 => s("sept."),
			10 => s("oct."),
			11 => s("nov."),
			12 => s("déc."),
		};

	}

}
?>
