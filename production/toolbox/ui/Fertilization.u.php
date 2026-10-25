<?php
namespace toolbox;

/**
 * Le calculateur des besoins en fertilisation (contrat production-finir T2, validé le
 * 03/10/2026), frère de celui des densités : la carte de saisie en deux colonnes sous ses onglets, la
 * culture et la planche à gauche, l'apport à droite, puis le résultat dans la zone teintée. Le
 * résultat revient du serveur à chaque saisie (`ToolboxFertilization.refresh`). Une espèce ou une
 * matière choisie revient aussi du serveur avec ce qu'elle porte, ses rendements ou sa composition.
 */
class FertilizationUi {

	public function __construct() {

		\Asset::css('toolbox', 'calculator.css');
		\Asset::css('toolbox', 'fertilization.css');
		\Asset::js('toolbox', 'calculator.js');
		\Asset::js('toolbox', 'fertilization.js');

	}

	public static function url(\structure\Structure $eStructure): string {
		return ToolUi::url($eStructure).'/fertilisation';
	}

	/**
	 * La notice du calculateur, que la roue de l'en-tête ouvre en panneau, comme celle des paniers.
	 */
	public static function urlNotice(\structure\Structure $eStructure): string {
		return '/toolbox/fertilization:notice?id='.$eStructure['id'];
	}

	/**
	 * Le calculateur n'a pas de réglage : la roue porte ce que `ToolUi` donne à tout outil, et la
	 * notice en première ligne, avant l'étoile, comme celle des paniers (`CompositionUi::getHeader()`).
	 * Elle se lit comme l'écran, observateur compris.
	 */
	public function getHeader(\structure\Structure $eStructure, \Collection $cFavorite): string {

		$menu = '<a href="'.self::urlNotice($eStructure).'" class="dropdown-item">'.\Asset::icon('info-circle').' '.s("Comment se calculent les besoins").'</a>';

		return new ToolUi()->getHeader($eStructure, Favorite::FERTILIZATION, $cFavorite, menu: $menu);

	}

	/**
	 * La notice (« pour décrire ce qu'elle fait et surtout inciter à la prudence quant à la
	 * manipulation des chiffres », le 03/10), sur le modèle de celle des paniers
	 * (`CompositionUi::getNoticePanel()`) : la promesse sous la mascotte de l'accueil, puis quatre
	 * étapes, les trois premières montrées par un morceau de l'écran sur l'exemple de
	 * `FertilizationLib::getNotice()`, la dernière en trois paragraphes de prudence. Textes de M-Prod
	 * sous mandat, recettés depuis.
	 *
	 * Chaque illustration est `inert` : les liens vers les sources y sont ceux de l'écran, qu'on ne doit
	 * pas pouvoir suivre depuis une image. Une étape dont l'exemple manque garde sa phrase seule.
	 * Les documents du référentiel suivent les étapes, eux en vrais liens (`getNoticeSources()`).
	 */
	public function getNoticePanel(array $notice, array $sources): \Panel {

		[
			'eFertilizationCrop' => $eFertilizationCrop,
			'eFertilizationYield' => $eFertilizationYield,
			'eFertilizationMaterial' => $eFertilizationMaterial,
			'materials' => $materials,
			'result' => $result,
		] = $notice;

		$figure = fn(string $content) => '<div class="fertilization-notice-figure" inert>'.$content.'</div>';

		$figures = [
			'crop' => $eFertilizationYield->empty() ? '' : $figure(
				'<div class="util-label">'.encode($eFertilizationCrop['plant']['name']).', '.self::getYieldName($eFertilizationYield).'</div>'.
				$this->getCropValues($eFertilizationCrop, $eFertilizationYield)
			),
			'material' => $eFertilizationMaterial->empty() ? '' : $figure(
				'<div class="util-label">'.encode($eFertilizationMaterial['name']).'</div>'.
				$this->getMaterialValues($eFertilizationMaterial)
			),
			'result' => ($result === NULL) ? '' : $figure(
				$this->getFigures($materials, $result).
				$this->getBalance($result['balance'])
			),
			'care' => '',
		];

		$steps = [
			'crop' => [s("Vous choisissez une culture"), [
				s("L'outil lit ce que la culture mobilise en azote, phosphore et potassium pour le rendement choisi, et ce que sa récolte exporte. Chaque valeur vient d'un document publié, que vous pouvez ouvrir."),
			]],
			'material' => [s("Vous choisissez vos matières"), [
				s("Une matière du référentiel, un de vos intrants ou une composition saisie à la main. L'outil compte sa teneur totale en azote, phosphore et potassium."),
			]],
			'result' => [s("L'outil calcule les doses"), [
				s("L'intrant couvre l'azote mobilisé, vos compléments s'y ajoutent, et le bilan compare ce que la culture mobilise à ce que vous apportez."),
			]],
			'care' => [s("Vous gardez un regard critique"), self::getCautions()],
		];

		$h = \main\DesignUi::getWelcome(
			s("Une estimation pour raisonner vos apports"),
			'<p>'.s("Des ordres de grandeur tirés de références publiées, à confronter à votre sol et à ce que vous observez.").'</p>',
			\Asset::svg('main', 'friends/ver-rose.svg'),
			class: 'mb-2'
		);

		$h .= '<ol class="util-procedure">';
			foreach($steps as $key => [$title, $paragraphs]) {
				$h .= '<li class="util-procedure-step">';
					$h .= '<h4 class="util-procedure-title">'.$title.'</h4>';
					foreach($paragraphs as $paragraph) {
						$h .= '<p>'.$paragraph.'</p>';
					}
					$h .= $figures[$key];
				$h .= '</li>';
			}
		$h .= '</ol>';

		$h .= $this->getNoticeSources($sources);

		return new \Panel(
			id: 'toolbox-fertilization-notice-panel',
			title: s("Comment se calculent les besoins"),
			body: $h
		);

	}

	public function getStart(\structure\Structure $eStructure): string {

		$promises = [
			['calculator', s("La quantité de chaque matière"), s("pour toute la planche et au m²")],
			['clipboard-data', s("Le bilan par élément"), s("ce que la culture mobilise, ce que l'apport couvre, ce que la récolte exporte")],
			['book', s("Les besoins de chaque culture"), s("mobilisé et exporté par rendement, chaque valeur avec le document qui la publie")],
		];

		$content = '<p>'.s("Choisissez une culture, sa planche et les matières que vous apportez, et voyez la quantité de chacune pour couvrir les besoins de la culture en azote, phosphore et potassium.").' '.s("Les besoins des cultures et la composition des matières se consultent aussi, avec leurs sources.").'</p>';
		$content .= new ToolUi()->getPromises($promises);

		return new \main\DesignUi()->getStart(
			\Asset::icon('calculator'),
			s("Les besoins en fertilisation"),
			$content,
			image: \Asset::image('main', 'friends/ver-rose.svg'),
			buttons: '<div class="flex-buttons"><a href="'.self::url($eStructure).'?open" class="btn btn-primary btn-xl">'.s("Ouvrir le calculateur").'</a><a href="'.self::url($eStructure).'?open&tab=fertilization-crops" class="btn btn-outline-primary btn-xl">'.s("Consulter les besoins des cultures").'</a></div>'
		);

	}

	/**
	 * Les documents du référentiel (`FertilizationLib::getSources()`), un par ligne : son libellé en
	 * lien, comme sous les tableaux du calculateur, puis ce qu'il donne.
	 */
	protected function getNoticeSources(array $sources): string {

		if($sources === []) {
			return '';
		}

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Les sources").'</h3>';
		$h .= '</div>';

		$h .= '<ul>';

			foreach($sources as ['eFertilizationSource' => $eFertilizationSource, 'uses' => $uses]) {

				$labels = array_map(fn(string $use) => match($use) {
					'mobilized' => s("mobilisé"),
					'exported' => s("exporté"),
					'materials' => s("teneurs des matières"),
				}, $uses);

				$h .= '<li>';
					$h .= '<a '.attr('href', $eFertilizationSource['url']).' target="_blank">'.encode($eFertilizationSource['name']).' '.\Asset::icon('box-arrow-up-right').'</a>';
					if($labels !== []) {
						$h .= ' <span class="font-sm color-muted">'.implode(', ', $labels).'</span>';
					}
				$h .= '</li>';

			}

		$h .= '</ul>';

		return $h;

	}

	/**
	 * Le calculateur, puis le référentiel qu'il emploie en deux tableaux à lire d'un coup, sous des
	 * onglets (contrat du tableau des références NPK, validé le 03/10/2026). L'onglet ouvert
	 * est le dernier ouvert, que `Lime.Tab` retient par l'id de la barre, sauf quand l'adresse en nomme
	 * un (`?tab=`, que `Lime.Tab.restore()` retire ensuite de l'adresse) : le second bouton de l'accueil
	 * ouvre ainsi les besoins des cultures.
	 */
	public function get(\structure\Structure $eStructure, \Collection $cFertilizationCrop, \Collection $cFertilizationMaterial, \Collection $cTool, \series\Series $eSeries, FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield, array $materials, ?array $result, ?string $tab = NULL): string {

		$h = $this->getCaution();

		$tabs = \main\DesignUi::getTabItem('fertilization-calculate', \Asset::icon('calculator', ['class' => 'tab-item-icon']).s("Calculer"));
		$tabs .= \main\DesignUi::getTabItem('fertilization-crops', s("Les cultures"));
		$tabs .= \main\DesignUi::getTabItem('fertilization-materials', s("Les matières organiques"));

		$panels = '<div class="tab-panel" data-tab="fertilization-calculate">';
			$panels .= $this->getForm($eStructure, $cFertilizationMaterial, $cTool, $eSeries, $eFertilizationCrop, $eFertilizationYield, $materials);
			$panels .= $this->getResult($eSeries, $materials, $result);
		$panels .= '</div>';

		$panels .= '<div class="tab-panel" data-tab="fertilization-crops">';
			$panels .= $this->getCropReference($cFertilizationCrop);
		$panels .= '</div>';

		$panels .= '<div class="tab-panel" data-tab="fertilization-materials">';
			$panels .= $this->getMaterialReference($cFertilizationMaterial);
		$panels .= '</div>';

		$h .= \main\DesignUi::getPageTabs($tabs, panels: $panels, attributes: [
			'id' => 'fertilization-tabs',
			'onrender' => 'Lime.Tab.restore(this, "fertilization-calculate", '.json_encode($tab).')',
		], fold: TRUE);

		return $h;

	}

	/**
	 * L'avertissement au-dessus des onglets, toujours affiché et sans croix (« affiche "…" en alerte
	 * permanente au dessus des onglets », le 03/10) : le calcul, les cultures et les matières
	 * reposent sur les mêmes moyennes. Bloc d'information titré et non alerte (« affreux utilise un truc
	 * plus neutre », le 03/10), titré « Avertissement » (03/10).
	 */
	protected function getCaution(): string {

		$h = '<div class="util-block-info">';
			$h .= '<h4>'.s("Avertissement").'</h4>';
			$h .= '<p>'.implode(' ', self::getCautions()).'</p>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La prudence sur les chiffres, que disent à la fois l'avertissement de l'écran et la dernière étape
	 * de la notice : les deux lisent ce texte ici, pour ne jamais diverger. L'avertissement le pose en un
	 * seul paragraphe (« tout ça sur la mm ligne », le 03/10), la notice en trois.
	 */
	protected static function getCautions(): array {

		return [
			s("Pour une même culture ou un même fumier, les sources varient souvent de moitié, parfois du simple au triple. Une analyse de votre matière donne une teneur plus juste que la référence."),
			s("L'outil ne connait ni votre sol, ni ses réserves, ni vos apports des années passées, ni la météo de l'année. Il ne remplace pas une analyse de sol ni le conseil d'un technicien."),
			s("Les doses autorisées par la réglementation, en zone vulnérable ou en agriculture biologique, restent à vérifier de votre côté."),
		];

	}

	/**
	 * Tout le référentiel des cultures, une ligne par rendement de référence : le mobilisé puis l'exporté
	 * à l'hectare, en deux groupes coiffés de leur nom (planche production-finir-npk-hierarchie, Q1 A,
	 * le 03/10). L'espèce et le lien vers ses documents ne s'écrivent que sur sa première ligne,
	 * qu'un filet sépare de l'espèce précédente : sans intertitre, le voile de l'exporté court d'un trait
	 * de la tête à la dernière ligne.
	 */
	protected function getCropReference(\Collection $cFertilizationCrop): string {

		$h = $this->getConfidenceLevels();

		$h .= '<div class="util-overflow-sm">';
			$h .= '<div class="util-table npk-reference-crops">';

				$h .= '<div class="util-table-head util-table-groups">';
					$h .= '<div></div>';
					$h .= '<div></div>';
					$h .= '<div></div>';
					$h .= '<div class="npk-reference-group npk-reference-group-4">'.s("Mobilisé, kg / ha").'</div>';
					$h .= '<div class="npk-reference-group npk-reference-group-4 util-table-veil">'.s("Exporté, kg / ha").'</div>';
					$h .= '<div></div>';
				$h .= '</div>';

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Espèce").'</div>';
					$h .= '<div>'.s("Conduite").'</div>';
					$h .= '<div class="text-end">'.s("Rendement").'</div>';
					$h .= $this->getElementHeads(FALSE);
					$h .= '<div></div>';
					$h .= $this->getElementHeads(TRUE);
					$h .= '<div class="util-table-veil"></div>';
					$h .= '<div>'.s("Sources").'</div>';
				$h .= '</div>';

				foreach($cFertilizationCrop as $eFertilizationCrop) {

					$first = TRUE;

					foreach($eFertilizationCrop['cYield'] as $eFertilizationYield) {

						[$mobilized, $exported] = $this->getCropColumns($eFertilizationCrop, $eFertilizationYield);
						['mobilized' => $mobilizedConfidence, 'exported' => $exportedConfidence, 'exceeded' => $exceeded] = $eFertilizationYield['confidence'];

						$h .= '<div class="util-table-row'.($first ? ' npk-reference-crop-first' : '').'">';

							if($first) {
								$h .= '<div class="npk-reference-plant">'.\plant\PlantUi::getVignette($eFertilizationCrop['plant'], '1.25rem').' '.encode($eFertilizationCrop['plant']['name']).'</div>';
							} else {
								$h .= '<div></div>';
							}

							$h .= '<div class="color-muted">'.self::getYieldLabel($eFertilizationYield).'</div>';
							$h .= '<div class="text-end">'.($eFertilizationYield['yield'] === NULL ? '' : '<span class="util-unit">'.s("{value} t/ha", \util\TextUi::number((float)$eFertilizationYield['yield'], NULL)).'</span>').'</div>';
							$h .= $this->getElementCells($mobilized, FALSE);
							$h .= '<div class="npk-reference-mark">'.self::getConfidence($mobilizedConfidence, $exceeded).'</div>';
							$h .= $this->getElementCells($exported, TRUE);
							$h .= '<div class="npk-reference-mark util-table-veil">'.($exportedConfidence === NULL ? '' : self::getConfidence($exportedConfidence, $exceeded)).'</div>';
							$h .= '<div class="npk-reference-sources font-sm">'.self::getSourceList($this->getYieldSources($eFertilizationCrop, $eFertilizationYield)).'</div>';

						$h .= '</div>';

						$first = FALSE;

					}

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La légende des niveaux de fiabilité, en tête du tableau des cultures : ce que chaque marque veut
	 * dire, sans phrase autour (planche production-finir-fiabilite, le 03/10).
	 */
	protected function getConfidenceLevels(): string {

		$h = '<div class="util-block-info">';
			$h .= '<h4>'.s("La fiabilité des valeurs").'</h4>';
			$h .= '<div class="util-confidence-levels">';

				foreach([FertilizationLib::CONFIDENCE_HIGH, FertilizationLib::CONFIDENCE_MEDIUM, FertilizationLib::CONFIDENCE_LOW] as $level) {
					$h .= '<div class="util-confidence-level">';
						$h .= self::getConfidence($level);
						$h .= '<b>'.self::getConfidenceName($level).'</b>';
						$h .= '<span>'.match($level) {
							FertilizationLib::CONFIDENCE_HIGH => s("Plusieurs sources cohérentes entre elles"),
							FertilizationLib::CONFIDENCE_MEDIUM => s("Une seule source ou agrégé à partir de sources différentes qui ne se recouvrent pas"),
							FertilizationLib::CONFIDENCE_LOW => s("Sources différentes et l'exporté dépasse le mobilisé"),
						}.'</span>';
					$h .= '</div>';
				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La marque de fiabilité (`util-confidence`), son nom au survol. Un niveau peu fiable nomme les
	 * éléments dont l'exporté dépasse le mobilisé : « Peu fiable, l'exporté dépasse le mobilisé en P₂O₅
	 * et K₂O ».
	 */
	protected static function getConfidence(int $level, array $exceeded = []): string {

		$title = ($level === FertilizationLib::CONFIDENCE_LOW and $exceeded !== []) ?
			s("{level}, l'exporté dépasse le mobilisé en {elements}", ['level' => self::getConfidenceName($level), 'elements' => self::getElementList($exceeded)]) :
			self::getConfidenceName($level);

		$h = '<span class="util-confidence is-'.$level.'" '.attr('title', $title).'>';

			for($bar = 1; $bar <= 3; $bar++) {
				$h .= '<span'.(($bar <= $level) ? ' class="is-on"' : '').'></span>';
			}

		$h .= '</span>';

		return $h;

	}

	protected static function getConfidenceName(int $level): string {

		return match($level) {
			FertilizationLib::CONFIDENCE_HIGH => s("Assez fiable"),
			FertilizationLib::CONFIDENCE_MEDIUM => s("Moyennement fiable"),
			FertilizationLib::CONFIDENCE_LOW => s("Peu fiable"),
		};

	}

	/**
	 * Les documents d'un rendement de référence, en entrées de `getSourceEntries()` : celui du
	 * mobilisé, puis ceux de l'exporté de l'espèce.
	 */
	protected function getYieldSources(FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield): array {

		return [
			[s("Mobilisé"), $eFertilizationYield['source']],
			...$this->getExportedSources($eFertilizationCrop, s("Exporté")),
		];

	}

	/**
	 * Les documents de l'exporté, en entrées de `getSourceEntries()`. Quand l'exporté vient de plusieurs
	 * documents, chaque entrée dit les éléments qu'il donne, après `$prefix` s'il y en a un :
	 * « Exporté, N » dans le tableau, « N » dans le calculateur, où la ligne dit déjà « Exporté ».
	 */
	protected function getExportedSources(FertilizationCrop $eFertilizationCrop, ?string $prefix): array {

		$several = (count($eFertilizationCrop['exportedSources']) > 1);

		$sources = [];

		foreach($eFertilizationCrop['exportedSources'] as ['eFertilizationSource' => $eFertilizationSource, 'elements' => $elements]) {

			if($several) {
				$label = self::getElementList($elements);
				$label = ($prefix === NULL) ? $label : $prefix.', '.$label;
			} else {
				$label = $prefix;
			}

			$sources[] = [$label, $eFertilizationSource];

		}

		return $sources;

	}

	/**
	 * « N », « P₂O₅ et K₂O », « N, P₂O₅ et K₂O ».
	 */
	protected static function getElementList(array $elements): string {

		$names = array_map(fn(string $element) => self::getElement($element), $elements);
		$last = array_pop($names);

		return ($names === []) ? $last : s("{list} et {last}", ['list' => implode(', ', $names), 'last' => $last]);

	}

	/**
	 * Le bouton du document d'une matière, dans la dernière colonne du tableau des matières.
	 */
	protected static function getSource(FertilizationSource $eFertilizationSource): string {

		return '<a '.attr('href', $eFertilizationSource['url']).' target="_blank" '.attr('title', $eFertilizationSource['name']).' class="btn btn-sm btn-outline-primary">'.s("Source").' '.\Asset::icon('box-arrow-up-right').'</a>';

	}

	/**
	 * Les documents d'un rendement dans la colonne « Sources » du tableau des cultures, un par ligne,
	 * chacun précédé de ce qu'il donne (planche production-finir-fiabilite, Q3 B, le 03/10).
	 */
	protected static function getSourceList(array $sources): string {

		return implode('<br/>', self::getSourceLinks($sources));

	}

	/**
	 * Les documents d'un tableau du calculateur, en une ligne sous lui, chacun précédé de ce qu'il
	 * donne : « Mobilisé Agroscope 2017 · Exporté, N Ministère 2026 » (planche
	 * production-finir-npk-hierarchie, Q2 b, le 03/10). Les tableaux gardent leurs chiffres seuls.
	 */
	protected static function getSourceLine(array $sources): string {

		return '<div class="font-sm mt-1">'.implode(' · ', self::getSourceLinks($sources)).'</div>';

	}

	protected static function getSourceLinks(array $sources): array {

		$links = [];

		foreach(self::getSourceEntries($sources) as [$label, $eFertilizationSource]) {
			$links[] = '<span class="color-muted">'.$label.'</span> <a '.attr('href', $eFertilizationSource['url']).' target="_blank">'.encode($eFertilizationSource['name']).' '.\Asset::icon('box-arrow-up-right').'</a>';
		}

		return $links;

	}

	/**
	 * `$sources` est une liste de [libellé ou NULL, document]. Deux entrées qui citent le même document
	 * n'en font qu'une, qui réunit leurs libellés : un document qui donne le mobilisé et l'exporté d'un
	 * rendement s'écrit une fois, sous « Mobilisé, Exporté ».
	 */
	protected static function getSourceEntries(array $sources): array {

		$entries = [];

		foreach($sources as [$label, $eFertilizationSource]) {

			$id = $eFertilizationSource['id'];

			if(array_key_exists($id, $entries) === FALSE) {
				$entries[$id] = [$label, $eFertilizationSource];
			} else if($entries[$id][0] === NULL or $label === NULL) {
				$entries[$id][0] = NULL;
			} else if($entries[$id][0] !== $label) {
				$entries[$id][0] .= ', '.$label;
			}

		}

		return $entries;

	}

	/**
	 * Le nom de la conduite de référence, le rendement ayant sa colonne (03/10).
	 */
	protected static function getYieldLabel(FertilizationYield $eFertilizationYield): string {

		if($eFertilizationYield['name'] !== NULL) {
			return encode($eFertilizationYield['name']);
		}

		return ($eFertilizationYield['yield'] === NULL) ? s("Rendement non publié") : '';

	}

	/**
	 * Tout le référentiel des matières : la teneur, puis la part disponible la première année, puis le
	 * lien vers le document.
	 */
	protected function getMaterialReference(\Collection $cFertilizationMaterial): string {

		$h = '<div class="util-overflow-sm">';
			$h .= '<div class="util-table util-table-even npk-reference-materials">';

				$h .= '<div class="util-table-head util-table-groups">';
					$h .= '<div></div>';
					$h .= '<div class="npk-reference-group">'.s("Teneur").'</div>';
					$h .= '<div class="npk-reference-group npk-reference-group-2 util-table-veil">'.s("Disponible la première année").'</div>';
					$h .= '<div></div>';
				$h .= '</div>';

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Matière").'</div>';
					$h .= $this->getElementHeads(FALSE);
					$h .= '<div class="text-end util-table-veil">'.self::getElementHead('nitrogen').'</div>';
					$h .= '<div class="text-end util-table-veil">'.self::getElementHead('phosphate').'</div>';
					$h .= '<div></div>';
				$h .= '</div>';

				foreach($cFertilizationMaterial as $eFertilizationMaterial) {

					[$contents, $availables] = $this->getMaterialColumns($eFertilizationMaterial);

					$h .= '<div class="util-table-row">';
						$h .= '<div>'.encode($eFertilizationMaterial['name']).'</div>';
						$h .= $this->getElementCells($contents, FALSE);
						$h .= '<div class="text-end util-table-veil">'.$availables[0].'</div>';
						$h .= '<div class="text-end util-table-veil">'.$availables[1].'</div>';
						$h .= '<div>'.self::getSource($eFertilizationMaterial['source']).'</div>';
					$h .= '</div>';

				}

			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * N, P₂O₅, K₂O en tête d'un groupe de trois colonnes. `$veil` voile le groupe, de la tête à la
	 * dernière ligne : c'est lui qui sépare deux groupes de chiffres qui se ressemblent (03/10).
	 */
	protected function getElementHeads(bool $veil): string {

		$h = '';

		foreach(FertilizationLib::ELEMENTS as $element) {
			$h .= '<div class="text-end'.($veil ? ' util-table-veil' : '').'">'.self::getElementHead($element).'</div>';
		}

		return $h;

	}

	/**
	 * Des quantités d'élément N, P₂O₅, K₂O, dans l'ordre de `FertilizationLib::ELEMENTS`, chacune en
	 * badge de son élément.
	 */
	protected function getElementCells(array $values, bool $veil): string {

		$h = '';

		foreach(FertilizationLib::ELEMENTS as $position => $element) {
			$h .= '<div class="text-end'.($veil ? ' util-table-veil' : '').'">'.self::getBadge($element, $values[$position]).'</div>';
		}

		return $h;

	}

	/**
	 * La surface est celle du calculateur de densités (`DensityUi::getSurface()`), seul le panneau
	 * choisi poste ses champs.
	 */
	protected function getForm(\structure\Structure $eStructure, \Collection $cFertilizationMaterial, \Collection $cTool, \series\Series $eSeries, FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield, array $materials): string {

		$form = new \util\FormUi(['columns' => 1]);

		$h = $form->open('fertilization-form', ['oninput' => 'ToolboxFertilization.input(this, event)']);

			$h .= $form->hidden('id', $eStructure);

			$h .= '<ol class="util-block util-procedure calculator-form-groups">';

				$h .= '<li class="util-procedure-step">';
					$h .= '<h4 class="util-procedure-title">'.s("La culture").'</h4>';
					$h .= $this->getCropModes($form, $eStructure, $eFertilizationCrop, $eFertilizationYield);
					$h .= '<div class="util-label mt-2">'.s("La surface").'</div>';
					$h .= DensityUi::getSurface($form, $eSeries, 'ToolboxCalculator.changeUse(this)');
				$h .= '</li>';

				$h .= '<li class="util-procedure-step">';
					$h .= '<h4 class="util-procedure-title">'.s("L'apport").'</h4>';
					$h .= $this->getMaterials($form, $cFertilizationMaterial, $cTool, $materials);
				$h .= '</li>';

			$h .= '</ol>';

		$h .= $form->close();

		return $h;

	}

	/**
	 * La culture se choisit dans le référentiel ou se saisit à la main, en deux onglets (planche
	 * production-finir-fiabilite, Q4 B, le 03/10), « Espèce » d'abord. Comme pour la surface,
	 * seul l'onglet choisi poste ses champs : `tabs-exclusive` désactive le `fieldset` de l'autre.
	 */
	protected function getCropModes(\util\FormUi $form, \structure\Structure $eStructure, FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield): string {

		$h = '<div class="tabs tabs-exclusive">';

			$h .= '<div class="tabs-item fertilization-crop-tabs">';
				$h .= '<a class="tab-item selected" data-tab="crop" onclick="ToolboxFertilization.changeMode(this)">'.s("Espèce").'</a>';
				$h .= '<a class="tab-item" data-tab="manual" onclick="ToolboxFertilization.changeMode(this)">'.s("Saisie manuelle").'</a>';
			$h .= '</div>';

			$h .= '<div class="tab-panel selected" data-tab="crop">';
				$h .= '<fieldset>';
					$h .= $this->getCrop($form, $eStructure, $eFertilizationCrop, $eFertilizationYield);
				$h .= '</fieldset>';
			$h .= '</div>';

			$h .= '<div class="tab-panel" data-tab="manual">';
				$h .= '<fieldset disabled>';
					$h .= $this->getMobilized($form);
				$h .= '</fieldset>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que la culture mobilise, saisi à la main en kg/ha (Q5 A, le 03/10) : sans espèce,
	 * l'exporté ne se saisit pas, et le bilan n'en dit rien. Le champ de l'espèce y vaut
	 * `FertilizationCrop::MANUAL`, ce qui dit au calcul de lire ces trois champs. L'écran s'ouvre sur
	 * l'onglet « Espèce » et ne se redessine pas ensuite : les champs naissent donc vides.
	 */
	protected function getMobilized(\util\FormUi $form): string {

		$fields = '';
		$wrappers = [];

		foreach(FertilizationLib::ELEMENTS as $element) {

			$name = $element.'['.FertilizationCrop::MANUAL.']';
			$wrappers[] = $name;

			$fields .= $form->inputGroup(
				$form->addon(self::getElementHead($element)).
				$form->number($name, NULL, ['step' => 1, 'min' => 0])
			);

		}

		$h = $form->hidden('crop', FertilizationCrop::MANUAL);
		$h .= $form->group(
			s("Mobilisé par la culture, en kg/ha"),
			'<div class="fertilization-composition">'.$fields.'</div>',
			['data-wrapper' => implode(' ', $wrappers)]
		);

		return $h;

	}

	/**
	 * L'espèce et son rendement de référence, avec sous le rendement ce que la culture mobilise et ce
	 * que la récolte exporte par hectare (03/10). Le bloc revient entier du serveur quand
	 * l'espèce change, ses rendements avec elle.
	 */
	public function getCrop(\util\FormUi $form, \structure\Structure $eStructure, FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield): string {

		['query' => $query, 'results' => $results] = $form->autocomplete(
			'crop',
			'/toolbox/fertilization:query',
			['id' => $eStructure['id']],
			NULL,
			$eFertilizationCrop->empty() ? [] : [self::getCropAutocomplete($eFertilizationCrop)],
			FALSE,
			FALSE,
			[
				'id' => 'fertilization-crop-query',
				'placeholder' => s("Tapez un nom d'espèce"),
			]
		);

		$h = '<div id="fertilization-crop">';

			$h .= $form->group(
				content: $form->inputGroup($form->addon(\Asset::icon('flower2')).$query).$results,
				attributes: ['data-wrapper' => 'crop']
			);

			if($eFertilizationCrop->notEmpty()) {

				$yields = [];

				foreach($eFertilizationCrop['cYield'] as $eFertilizationYieldOption) {
					$yields[$eFertilizationYieldOption['id']] = self::getYieldName($eFertilizationYieldOption);
				}

				$h .= $form->group(
					s("Rendement de référence"),
					$form->select('yield', $yields, $eFertilizationYield['id'], ['required' => TRUE, 'onchange' => 'ToolboxFertilization.changeCrop(this)']).
					$this->getCropValues($eFertilizationCrop, $eFertilizationYield),
					['data-wrapper' => 'yield']
				);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une espèce du référentiel dans le champ de l'espèce, comme `PlantUi::getAutocomplete()` : la
	 * valeur est la culture du référentiel, et non l'espèce, que le calculateur ne connait pas.
	 */
	public static function getCropAutocomplete(FertilizationCrop $eFertilizationCrop): array {

		\Asset::css('media', 'media.css');

		return [
			'value' => $eFertilizationCrop['id'],
			'itemHtml' => \plant\PlantUi::getVignette($eFertilizationCrop['plant'], '2.5rem').'<div>'.encode($eFertilizationCrop['plant']['name']).'</div>',
			'itemText' => $eFertilizationCrop['plant']['name']
		];

	}

	/**
	 * « Garde, 60 t/ha » : le libellé de la source et le rendement, l'un ou l'autre quand la source ne
	 * publie que lui.
	 */
	public static function getYieldName(FertilizationYield $eFertilizationYield): string {

		$parts = [];

		if($eFertilizationYield['name'] !== NULL) {
			$parts[] = encode($eFertilizationYield['name']);
		}

		if($eFertilizationYield['yield'] !== NULL) {
			$parts[] = s("{value} t/ha", \util\TextUi::number((float)$eFertilizationYield['yield'], NULL));
		}

		return $parts ? implode(', ', $parts) : s("Rendement non publié");

	}

	/**
	 * L'exporté par hectare est l'exporté par tonne au rendement de référence. Une valeur que la
	 * source ne publie pas laisse sa case vide, jamais 0.
	 */
	protected function getCropValues(FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield): string {

		[$mobilized, $exported] = $this->getCropColumns($eFertilizationCrop, $eFertilizationYield);
		['mobilized' => $mobilizedConfidence, 'exported' => $exportedConfidence, 'exceeded' => $exceeded] = $eFertilizationYield['confidence'];

		$rows = [
			[s("Mobilisé").self::getConfidence($mobilizedConfidence, $exceeded), $mobilized, TRUE],
		];

		if($eFertilizationCrop['exportedSources'] !== []) {
			$rows[] = [s("Exporté").($exportedConfidence === NULL ? '' : self::getConfidence($exportedConfidence, $exceeded)), $exported, TRUE];
		}

		return $this->getValues(s("kg / ha"), $rows).self::getSourceLine($this->getYieldSources($eFertilizationCrop, $eFertilizationYield));

	}

	/**
	 * Le mobilisé et l'exporté par hectare, N, P₂O₅, K₂O, déjà écrits. L'exporté par hectare est
	 * l'exporté par tonne au rendement de référence, au kilo comme le mobilisé (une décimale y serait
	 * une précision que les sources n'ont pas), vide quand l'un des deux manque.
	 */
	protected function getCropColumns(FertilizationCrop $eFertilizationCrop, FertilizationYield $eFertilizationYield): array {

		$mobilized = [];
		$exported = [];

		foreach(FertilizationLib::ELEMENTS as $element) {

			$mobilized[] = $eFertilizationYield[$element] === NULL ? '' : self::getValue($eFertilizationYield[$element]);

			$exportedPerTon = $eFertilizationCrop['exported'.ucfirst($element)];
			$exported[] = ($exportedPerTon === NULL or $eFertilizationYield['yield'] === NULL) ? '' : self::getValue(round($exportedPerTon * $eFertilizationYield['yield']));

		}

		return [$mobilized, $exported];

	}

	/**
	 * Les valeurs N, P₂O₅, K₂O en colonnes, une ligne par valeur : une phrase de chiffres ne se lit pas
	 * (03/10). Les documents se citent sous le tableau (`getSourceLine()`). Une ligne de
	 * quantités se lit en badges (`$badge`), une ligne de coefficients en texte.
	 */
	protected function getValues(string $unit, array $rows): string {

		$h = '<div class="util-table util-table-even fertilization-values mt-1">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.$unit.'</div>';
				foreach(FertilizationLib::ELEMENTS as $element) {
					$h .= '<div class="text-end">'.self::getElementHead($element).'</div>';
				}
			$h .= '</div>';

			foreach($rows as [$label, $values, $badge]) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>'.$label.'</div>';
					foreach(FertilizationLib::ELEMENTS as $position => $element) {
						$h .= '<div class="text-end">'.($badge ? self::getBadge($element, $values[$position]) : $values[$position]).'</div>';
					}
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'intrant, puis les compléments, que l'on ajoute soi-même et qui peuvent manquer
	 * (arbitrage Q5 B). L'ajout se fait dans le formulaire, sans panneau : c'est un calcul.
	 */
	protected function getMaterials(\util\FormUi $form, \Collection $cFertilizationMaterial, \Collection $cTool, array $materials): string {

		$h = '';
		$complements = '';

		foreach($materials as $index => $material) {

			if($index === array_key_first($materials)) {
				$h .= $this->getMaterial($form, $cFertilizationMaterial, $cTool, $index, $material);
			} else {
				$complements .= $this->getMaterial($form, $cFertilizationMaterial, $cTool, $index, $material);
			}

		}

		$h .= '<div class="util-title mt-2">';
			$h .= '<div class="util-label">'.s("Les compléments").'</div>';
			$h .= '<div>';
				$h .= '<a class="btn btn-sm btn-outline-primary" onclick="ToolboxFertilization.add(this)">'.\Asset::icon('plus-circle').' '.s("Ajouter un complément").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div id="fertilization-complements">'.$complements.'</div>';

		return $h;

	}

	/**
	 * Une matière à son index : le sélecteur, puis la composition lue dans le référentiel, ou les champs
	 * de la composition saisie. L'index 0 est la principale, les autres des compléments, que la
	 * corbeille retire.
	 *
	 * La saisie manuelle est la première option du sélecteur (« ajoute dans le <select> une option
	 * Saisie manuelle en tête », le 03/10, qui remplace l'interrupteur). Quand la structure a
	 * des intrants, le sélecteur passe en groupes, et `FormUi::select()` n'y admet que des groupes :
	 * la saisie manuelle a donc le sien, en tête, puis « Mes intrants » et le référentiel. Un intrant
	 * choisi revient en saisie manuelle pré-remplie (`FertilizationLib::getMaterialFromTool()`).
	 */
	public function getMaterial(\util\FormUi $form, \Collection $cFertilizationMaterial, \Collection $cTool, int $index, array $material): string {

		$main = ($index === 0);
		$composition = ($material['value'] === FertilizationMaterial::COMPOSITION);
		$eFertilizationMaterial = $material['eFertilizationMaterial'];

		$manual = [FertilizationMaterial::COMPOSITION => s("Saisie manuelle")];
		$references = [];

		foreach($cFertilizationMaterial as $eFertilizationMaterialReference) {
			$references[$eFertilizationMaterialReference['id']] = $eFertilizationMaterialReference['name'];
		}

		if($cTool->notEmpty()) {

			$tools = [];

			foreach($cTool as $eTool) {
				$tools[FertilizationLib::TOOL.$eTool['id']] = $eTool['name'];
			}

			$values = [
				['label' => s("Composition"), 'values' => $manual],
				['label' => s("Mes intrants"), 'values' => $tools],
				['label' => s("Matières de référence"), 'values' => $references],
			];

		} else {
			$values = $manual + $references;
		}

		$field = $form->select('material['.$index.']', $values, $material['value'] === '' ? NULL : $material['value'], [
			'required' => $main,
			'group' => $cTool->notEmpty(),
			'onchange' => 'ToolboxFertilization.changeMaterial(this)',
		]);

		if($main === FALSE) {
			$field = $form->inputGroup($field.'<a class="btn btn-only-icon btn-outline-primary" onclick="ToolboxFertilization.remove(this)" title="'.s("Retirer ce complément").'">'.\Asset::icon('trash').'</a>');
		}

		$h = '<div id="fertilization-material-'.$index.'" data-index="'.$index.'">';

			if($composition === FALSE and $eFertilizationMaterial->notEmpty()) {
				$field .= $this->getMaterialValues($eFertilizationMaterial);
			}

			$h .= $form->group($main ? s("Intrant") : s("Complément"), $field, ['data-wrapper' => 'material['.$index.']']);

			if($composition) {
				$h .= $this->getComposition($form, $index, $eFertilizationMaterial);
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les teneurs en % du produit brut, et la part de chacune que la culture trouve la première année,
	 * donnée pour information : le calcul dose sur la teneur totale (`FertilizationLib::getContents()`).
	 */
	protected function getMaterialValues(FertilizationMaterial $eFertilizationMaterial): string {

		[$contents, $availables] = $this->getMaterialColumns($eFertilizationMaterial);

		return $this->getValues('', [
			[s("Teneur"), $contents, TRUE],
			[s("Disponible la première année"), $availables, FALSE],
		]).self::getSourceLine([
			[s("Teneur et disponible"), $eFertilizationMaterial['source']],
		]);

	}

	/**
	 * Les teneurs et les parts disponibles, N, P₂O₅, K₂O, déjà écrites. Un élément que la matière
	 * n'apporte pas laisse ses deux cases vides.
	 * Les teneurs se lisent en % du produit brut, comme la composition saisie et les intrants : « affiche
	 * les pourcentages je me suis trompé » (03/10), qui revient sur les unités par tonne.
	 * Une part disponible s'écrit à la précision de sa source, sans zéro de traîne : 25 % pour le RMT,
	 * 57,5 % pour la corne de l'IGZ.
	 * Un Keq P que la source ne publie pas laisse sa case vide : l'écran n'affiche pas une valeur que
	 * personne n'a publiée, et le calcul ne le lit pas, dosant sur la teneur totale.
	 */
	protected function getMaterialColumns(FertilizationMaterial $eFertilizationMaterial): array {

		$contents = [];
		$availables = [];

		$parts = [
			'nitrogen' => $eFertilizationMaterial['availableNitrogen'],
			'phosphate' => $eFertilizationMaterial['availablePhosphate'],
			'potash' => 1,
		];

		foreach(FertilizationLib::ELEMENTS as $element) {

			if($eFertilizationMaterial[$element] === NULL) {
				$contents[] = '';
				$availables[] = '';
			} else {
				$contents[] = self::getContent($eFertilizationMaterial[$element]);
				$availables[] = $parts[$element] === NULL ? '' : self::getPercent($parts[$element] * 100);
			}

		}

		return [$contents, $availables];

	}

	/**
	 * La composition saisie à la main, en % comme sur un sac du commerce : les trois teneurs sur une
	 * ligne, sans part disponible, le calcul lisant la teneur totale (03/10). Rien ne
	 * s'enregistre. Pré-remplie depuis un intrant, elle porte son nom dans son libellé, qui dit de quel
	 * produit il s'agit.
	 */
	protected function getComposition(\util\FormUi $form, int $index, FertilizationMaterial $eFertilizationMaterial): string {

		$percent = fn(?float $value, float $factor) => $value === NULL ? NULL : round($value / $factor, 2);

		$fields = '';

		foreach(FertilizationLib::ELEMENTS as $element) {
			$fields .= $form->inputGroup(
				$form->addon(self::getElement($element)).
				$form->number($element.'['.$index.']', $percent($eFertilizationMaterial[$element] ?? NULL, 10), ['step' => 0.1, 'min' => 0]).
				$form->addon('%')
			);
		}

		$name = $eFertilizationMaterial['name'] ?? NULL;

		return $form->group(
			$name === NULL ? s("Composition") : s("Composition de {value}", encode($name)),
			'<div class="fertilization-composition">'.$fields.'</div>',
			['data-wrapper' => 'nitrogen['.$index.'] phosphate['.$index.'] potash['.$index.']']
		);

	}

	/**
	 * Le résultat, dans la zone teintée que partagent les calculateurs : la quantité de chaque matière,
	 * puis le bilan par élément. Tant que la saisie ne permet pas de calculer, la zone le dit.
	 */
	public function getResult(\series\Series $eSeries, array $materials, ?array $result): string {

		$h = '<div id="fertilization-result" class="calculator-result">';

			if($result === NULL) {
				$h .= '<div class="util-empty">'.s("Choisissez une culture et une matière pour voir les quantités à apporter.").'</div>';
			} else {

				$h .= '<div class="util-title">';
					$h .= '<h2>'.match($eSeries['use']) {
						\series\Series::BED => s("Votre planche"),
						\series\Series::BLOCK => s("Votre surface")
					}.'</h2>';
				$h .= '</div>';

				$h .= $this->getFigures($materials, $result);
				$h .= $this->getBalance($result['balance']);

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La surface d'abord, tuile de même rang que les quantités qu'elle explique (« la surface dans un
	 * bloc à côté de 3.1kg de votre engrais, c'est une info clé », le 03/10), comme les densités
	 * posent leurs chiffres. Puis une tuile par matière : la quantité pour toute la surface, une décimale
	 * sous 100 kg, puis la dose au m², en grammes sous le kilo. Au-delà de 1 000 kg, la quantité passe en
	 * tonnes et la dose en t/ha (« au dela de 1000 kg passer à la tonne et en tonne/ha », le
	 * 03/10), avec une décimale sous 100. Un complément qui ne comble aucun manque se dose à 0.
	 */
	protected function getFigures(array $materials, array $result): string {

		$h = '<div class="util-figures">';

			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.\util\TextUi::number($result['area'], NULL).' <small>'.s("m²").'</small></div>';
				$h .= '<div class="util-figure-label">'.s("Surface").'</div>';
			$h .= '</div>';

			foreach($result['quantities'] as $index => $quantity) {

				$name = ($materials[$index]['value'] === FertilizationMaterial::COMPOSITION) ?
					s("Votre engrais") :
					encode($materials[$index]['eFertilizationMaterial']['name']);

				$h .= '<div class="util-figure">';
					if($quantity >= 1000) {
						$value = self::getNumber($quantity / 1000).' <small>'.s("t").'</small>';
						$dose = s("{value} t / ha", self::getNumber($quantity / $result['area'] * 10));
					} else {
						$value = self::getNumber($quantity).' <small>'.s("kg").'</small>';
						$dose = s("{value} / m²", self::getWeight($quantity / $result['area']));
					}

					$h .= '<div class="util-figure-value">'.$value.'</div>';
					$h .= '<div class="util-figure-label">'.$name.'<br/><span class="util-unit">'.$dose.'</span></div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bilan, un élément par ligne. Les quantités sont en badges de leur élément, l'écart en texte : il
	 * se lit au regard du mobilisé, un manque de plus de 10 % est en alerte, un excès n'a pas de couleur.
	 * Une valeur que la source ne publie pas est un « ? », et l'écart aussi quand c'est le mobilisé qui
	 * manque : il ne se calcule pas. Une culture saisie à la main n'a pas d'exporté : sa colonne
	 * disparait, plutôt que d'aligner trois cases vides (Q5 A, le 03/10).
	 */
	protected function getBalance(array $balance): string {

		$exported = (array_filter(array_column($balance, 'exported'), fn(?float $value) => $value !== NULL) !== []);

		$h = '<div class="util-table util-table-even fertilization-balance'.($exported ? '' : ' fertilization-balance-3').'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Élément").'</div>';
				$h .= '<div class="text-end">'.s("Mobilisé").'</div>';
				$h .= '<div class="text-end">'.s("Apporté").'</div>';
				$h .= '<div class="text-end">'.s("Écart").'</div>';
				if($exported) {
					$h .= '<div class="text-end">'.s("Exporté").'</div>';
				}
			$h .= '</div>';

			foreach($balance as $element => ['mobilized' => $mobilized, 'brought' => $brought, 'gap' => $gap, 'exported' => $value]) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>'.self::getElementHead($element).'</div>';
					$h .= '<div class="text-end">'.self::getBadge($element, $mobilized === NULL ? '' : self::getWeight($mobilized)).'</div>';
					$h .= '<div class="text-end">'.self::getBadge($element, self::getWeight($brought)).'</div>';
					$h .= '<div class="text-end">'.self::getGapCell($mobilized, $gap).'</div>';
					if($exported) {
						$h .= '<div class="text-end">'.self::getBadge($element, $value === NULL ? '' : self::getWeight($value)).'</div>';
					}
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'écart ne se calcule ni sans mobilisé, inconnu, ni sur un mobilisé nul, qui n'a pas de part à
	 * couvrir.
	 */
	protected static function getGapCell(?float $mobilized, ?int $gap): string {

		if($mobilized === NULL) {
			return self::getUnknown();
		} else if($gap === NULL) {
			return '';
		} else {
			return '<span class="util-unit">'.self::getGap($gap).'</span>';
		}

	}

	public static function getElement(string $element): string {

		return match($element) {
			'nitrogen' => s("N"),
			'phosphate' => s("P₂O₅"),
			'potash' => s("K₂O"),
		};

	}

	/**
	 * Le nom d'un élément en tête de colonne ou de ligne, dans la teinte de ses badges (planche
	 * production-finir-npk-couleurs, le 03/10).
	 */
	protected static function getElementHead(string $element): string {
		return '<span class="fertilization-element fertilization-'.$element.'">'.self::getElement($element).'</span>';
	}

	/**
	 * Une quantité d'élément, en badge de sa teinte : « quand on parle d'unités » (03/10). Une
	 * valeur que la source ne publie pas devient un « ? » dans la teinte neutre du badge, pour que la
	 * colonne n'ait pas de trou : « si info pas connue ajouter un '?' dans un badge de couleur neutre »
	 * (03/10). Une valeur publiée à 0 reste 0.
	 */
	protected static function getBadge(string $element, string $value): string {
		return $value === '' ? self::getUnknown() : '<span class="badge fertilization-'.$element.'">'.$value.'</span>';
	}

	protected static function getUnknown(): string {
		return '<span class="badge">?</span>';
	}

	/**
	 * Un poids en kg, en grammes sous le kilo et en tonnes au-delà de 1 000 kg (03/10), pour
	 * que l'écart se relise sur les valeurs affichées.
	 */
	public static function getWeight(float $kg): string {

		if($kg < 1) {
			return s("{value} g", \util\TextUi::number(round($kg * 1000), 0));
		} else if($kg >= 1000) {
			return s("{value} t", self::getNumber($kg / 1000));
		} else {
			return s("{value} kg", self::getNumber($kg));
		}

	}

	/**
	 * Une quantité de la tuile ou du bilan, en kg comme en t : une décimale sous 100, aucune au-dessus
	 * (M-Prod le 03/10, quand le passage à la tonne a rendu visibles les « 773,73 kg » du bilan).
	 */
	protected static function getNumber(float $value): string {
		return \util\TextUi::number($value, $value < 100 ? 1 : 0);
	}

	/**
	 * L'écart en %, avec le signe moins typographique.
	 */
	public static function getGap(int $gap): string {

		if($gap > 0) {
			$text = s("+{value} %", $gap);
		} else if($gap < 0) {
			$text = s("−{value} %", abs($gap));
		} else {
			$text = s("{value} %", 0);
		}

		return $gap < -10 ? '<span class="color-warning">'.$text.'</span>' : $text;

	}

	/**
	 * Une teneur du référentiel, en kg par tonne, écrite en % du produit brut à une décimale toujours
	 * affichée, 2,0 % comme 2,2 % (« +1 chiffre après la virgule sur le % », le 03/10) : le
	 * référentiel garde sa précision, le calcul la lit telle quelle.
	 */
	protected static function getContent(float $value): string {
		return '<span class="util-unit">'.s("{value} %", \util\TextUi::number($value / 10, 1)).'</span>';
	}

	/**
	 * Un pourcentage à la précision de sa source : les teneurs ont au plus deux décimales en kg/t, donc
	 * trois en %.
	 */
	protected static function getPercent(float $value): string {
		return '<span class="util-unit">'.s("{value} %", \util\TextUi::number(round($value, 3), NULL)).'</span>';
	}

	/**
	 * Une valeur du référentiel, sans décimale quand elle est entière.
	 */
	protected static function getValue(float|string $value): string {

		$value = (float)$value;

		return \util\TextUi::number($value, floor($value) === $value ? 0 : 1);

	}

}
?>
