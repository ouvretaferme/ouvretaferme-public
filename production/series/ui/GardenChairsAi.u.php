<?php
namespace series;

/**
 * La rotation par jardin : un onglet par groupe (le groupe seul quand il n'y en a qu'un), un deck
 * qui chiffre le jardin type du groupe, puis un deck par jardin avec son menu « Séries vers ». Un menu
 * vidé affiche « À définir » : le brouillon ne se complète pas tout seul (gardenChairsAi.js), la pesée
 * et l'état de chaque jardin se recalculent à chaque choix depuis les données posées ici.
 */
class GardenChairsAiUi {

	public function __construct() {

		\Asset::css('series', 'gardenChairsAi.css');
		\Asset::css('map', 'bed.css');
		\Asset::js('series', 'gardenChairsAi.js');

	}

	public static function url(\structure\Structure $eStructure, ?int $season = NULL): string {
		return '/series/gardenChairsAi?structure='.$eStructure['id'].($season ? '&season='.$season : '');
	}

	public function getTitle(\structure\Structure $eStructure, int $season): string {

		return \main\DesignUi::getPageHeader(
			s("Faire une rotation par jardin"),
			season: new \structure\StructureUi()->getSeason($eStructure, fn(int $selectedSeason) => self::url($eStructure, $selectedSeason), $season),
			back: \structure\StructureUi::urlCultivationSoil($eStructure, season: $season)
		);

	}

	public function getGroups(\structure\Structure $eStructure, int $season, array $groups): string {

		if($groups === []) {
			return '<div class="util-empty">'.s("Aucun jardin n'a le même nombre de planches et les mêmes dimensions qu'un autre en {value}.", $season).'</div>';
		}

		$h = '<div class="util-block-info">';
			$h .= '<p>'.s("Les jardins qui ont le même nombre de planches, aux mêmes dimensions et avec le même mode de culture, peuvent échanger leurs séries de {value}. Choisissez pour chaque jardin où vont ses séries. Les cultures pérennes et les séries clôturées ne bougent pas.", $season).'</p>';
		$h .= '</div>';

		if(count($groups) === 1) {

			$group = $groups[array_key_first($groups)];

			$h .= $this->getGroup($eStructure, $season, $group);

			return $h;

		}

		// L'onglet se retrouve par le premier jardin de son groupe : le rechargement qui suit un
		// déplacement rouvre le groupe sur lequel on travaillait.
		$tabSelected = 'group-'.$groups[array_key_first($groups)]['cPlot']->first()['id'];

		$h .= '<div class="tabs tabs-h" id="garden-chairs-'.$eStructure['id'].'" onrender="'.encode('Lime.Tab.restore(this, "'.$tabSelected.'", null)').'">';

			$h .= '<div class="tabs-item">';

				foreach($groups as $group) {

					$beds = $group['cPlot']->first()['cBedGarden']->count();

					$tab = 'group-'.$group['cPlot']->first()['id'];

					$h .= '<a class="tab-item '.($tab === $tabSelected ? 'selected' : '').'" data-tab="'.$tab.'" onclick="Lime.Tab.select(this)">';
						$h .= match($group['cPlot']->first()['mode']) {
							\map\Plot::GREENHOUSE => s("Abris de {value} planches", $beds),
							\map\Plot::OPEN_FIELD => s("Jardins de {value} planches", $beds),
						};
						$h .= ' <span class="util-counter util-counter-solid">'.$group['cPlot']->count().'</span>';
					$h .= '</a>';

				}

			$h .= '</div>';

			foreach($groups as $group) {

				$tab = 'group-'.$group['cPlot']->first()['id'];

				$h .= '<div class="tab-panel '.($tab === $tabSelected ? 'selected' : '').'" data-tab="'.$tab.'">';
					$h .= $this->getGroup($eStructure, $season, $group);
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	protected function getGroup(\structure\Structure $eStructure, int $season, array $group): string {

		$cPlot = $group['cPlot'];
		$severalZones = $group['severalZones'];

		$form = new \util\FormUi();

		$h = $form->openAjax('/series/gardenChairsAi:doMove', ['class' => 'garden-chairs-form', 'onrender' => 'SeriesGardenChairs.init(this)']);

			$h .= $form->hidden('structure', $eStructure);
			$h .= $form->hidden('season', $season);

			$h .= $this->getIdentity($cPlot, $group['dimensions'], $severalZones);

			$h .= '<div class="garden-chairs">';

				$h .= $this->getStates();

				$destinations = self::getDestinations($cPlot);

				foreach($destinations as $zoneId => $destination) {

					if($severalZones) {
						$h .= '<div class="util-title garden-chairs-zone">';
							$h .= '<h3>'.encode($destination['label']).' <span class="util-counter">'.count($destination['values']).'</span></h3>';
							$h .= self::getShift();
						$h .= '</div>';
					}

					$h .= '<div class="garden-chairs-list" data-zone="'.$zoneId.'">';

						foreach($cPlot as $ePlot) {

							if($ePlot['zone']['id'] === $zoneId) {
								$h .= $this->getGarden($form, $ePlot, $destinations, $severalZones);
							}

						}

					$h .= '</div>';

				}

			$h .= '</div>';

			$h .= '<div class="garden-chairs-draft hide">';

				$h .= \main\DesignUi::getWeigh('info', 'arrow-left-right', s("Si vous déplacez ces séries en {value}", $season), NULL, [
					['icon' => 'exclamation-circle', 'text' => self::getCounter('missing', s("{value} jardin reste à définir", ['value' => '{value}']), s("{value} jardins restent à définir", ['value' => '{value}']))],
					['icon' => 'arrow-right', 'text' => self::getCounter('series', s("{value} série de {season} change de jardin", ['value' => '{value}', 'season' => $season]), s("{value} séries de {season} changent de jardin", ['value' => '{value}', 'season' => $season]))],
					['icon' => 'pin-angle', 'text' => self::getCounter('perennial', s("{value} série de plante pérenne reste en place ({list})", ['value' => '{value}', 'list' => '{list}']), s("{value} séries de plantes pérennes restent en place ({list})", ['value' => '{value}', 'list' => '{list}']))],
					['icon' => 'exclamation-circle', 'text' => self::getCounter('watch', s("{value} planche à surveiller", ['value' => '{value}']), s("{value} planches à surveiller", ['value' => '{value}']))],
				], [
					self::getCounter('still', s("{value} jardin garde ses séries", ['value' => '{value}']), s("{value} jardins gardent leurs séries", ['value' => '{value}'])),
				], TRUE);

				$h .= '<div class="garden-chairs-footer">';
					$h .= $form->submit(s("Déplacer"), ['data-label-blocked' => s("Déplacer"), 'data-label' => s("Déplacer les {value} jardins", ['value' => '{value}']), 'data-waiter' => s("Déplacement en cours...")]);
					$h .= ' <a data-action="garden-chairs-cancel" class="btn btn-outline-primary">'.s("Annuler").'</a>';
				$h .= '</div>';

			$h .= '</div>';

		$h .= $form->close();

		return $h;

	}

	/**
	 * Le deck du jardin type d'un groupe : ce qu'il est en mots, puis ses chiffres.
	 */
	protected function getIdentity(\Collection $cPlot, array $dimensions, bool $severalZones): string {

		$ePlot = $cPlot->first();
		$beds = $ePlot['cBedGarden']->count();

		$widths = [];

		foreach($dimensions['widths'] as $width => $number) {
			$widths[] = s("{number} × {width}", ['number' => $number, 'width' => self::getWidth($width)]);
		}

		$h = '<div class="util-deck garden-chairs-group">';
			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-icon util-icon-round util-icon-production">'.\Asset::icon(match($ePlot['mode']) {
					\map\Plot::GREENHOUSE => 'greenhouse',
					\map\Plot::OPEN_FIELD => 'sun',
				}).'</div>';
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.match($ePlot['mode']) {
						\map\Plot::GREENHOUSE => p("{value} abri de {beds} planches", "{value} abris de {beds} planches", $cPlot->count(), ['beds' => $beds]),
						\map\Plot::OPEN_FIELD => p("{value} jardin de {beds} planches", "{value} jardins de {beds} planches", $cPlot->count(), ['beds' => $beds]),
					}.'</div>';
					$h .= '<div class="util-deck-figures">';
						$h .= '<div class="util-deck-figure">';
							$h .= '<span class="util-deck-key">'.match($ePlot['mode']) {
								\map\Plot::GREENHOUSE => s("Abris"),
								\map\Plot::OPEN_FIELD => s("Jardins"),
							}.'</span>';
							$h .= '<span class="util-deck-value">'.$cPlot->count().'</span>';
						$h .= '</div>';
						$h .= '<div class="util-deck-figure">';
							$h .= '<span class="util-deck-key">'.s("Longueur").'</span>';
							$h .= '<span class="util-deck-value">'.implode(' · ', array_map(fn($length) => s("{value} m", $length), $dimensions['lengths'])).'</span>';
						$h .= '</div>';
						$h .= '<div class="util-deck-figure">';
							$h .= '<span class="util-deck-key">'.s("Largeurs").'</span>';
							$h .= '<span class="util-deck-value">'.implode(' · ', $widths).'</span>';
						$h .= '</div>';
						$h .= '<div class="util-deck-figure">';
							$h .= '<span class="util-deck-key">'.s("Surface").'</span>';
							$h .= '<span class="util-deck-value">'.s("{value} m²", \util\TextUi::number($dimensions['surface'], 1)).'</span>';
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';
				if($severalZones === FALSE) {
					$h .= '<div class="util-deck-aside">'.self::getShift().'</div>';
				}
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le geste qui envoie chaque jardin de la parcelle vers le suivant, le dernier vers le premier.
	 * Il ne remplit que les menus : Déplacer reste le seul geste qui écrit.
	 */
	protected static function getShift(): string {
		return '<a data-action="garden-chairs-shift" class="btn btn-outline-primary btn-sm garden-chairs-shift">'.s("Décaler d'un jardin").'</a>';
	}

	/**
	 * « 1 m » pour une largeur en mètres ronds, « 50 cm » sinon.
	 */
	protected static function getWidth(int $width): string {
		return ($width % 100 === 0) ? s("{value} m", $width / 100) : s("{value} cm", $width);
	}

	/**
	 * Le deck d'un jardin. Sa bande d'état est posée par le JS, qui la copie depuis les modèles de
	 * getStates() : aucune tant que le jardin reste où il est.
	 */
	protected function getGarden(\util\FormUi $form, \map\Plot $ePlot, array $destinations, bool $severalZones): string {

		$h = '<div class="util-deck" data-plot="'.$ePlot['id'].'" data-watch="'.encode(json_encode($ePlot['watch'])).'" data-returns="'.encode(json_encode(self::getReturns($ePlot['returns']))).'" data-series-moving="'.implode(',', $ePlot['seriesMoving']).'" data-series-perennial="'.encode(json_encode(array_map(fn($name) => s("{series} sur {plot}", ['series' => $name, 'plot' => $ePlot['name']]), $ePlot['seriesPerennial']), JSON_FORCE_OBJECT)).'">';
			$h .= '<div class="util-deck-head garden-chairs-deck-head">';
				$h .= '<div class="util-deck-title garden-chairs-deck-name">'.encode(\map\PlotUi::getName($ePlot)).'</div>';
				$h .= '<div class="util-deck-body">'.$this->getStrip($ePlot['cBedGarden']).'</div>';
				$h .= '<div class="util-deck-aside garden-chairs-deck-to">';
					$h .= $form->inputGroup(
						$form->addon(s("Séries vers")).
						$form->select('to['.$ePlot['id'].']', self::getDestinationsFrom($destinations, $ePlot, $severalZones), $ePlot['id'], ['placeholder' => s("< À définir >"), 'group' => $severalZones, 'data-plot' => $ePlot['id']])
					);
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Pour chaque jardin d'arrivée, la phrase qui nomme les familles de retour trop tôt
	 * (« Solanacées de retour après 1 an sur 3 planches ») et, rang par rang, ce que le survol
	 * ajoute à la vignette d'une espèce en cause.
	 */
	protected static function getReturns(array $returns): array {

		$texts = [];

		foreach($returns as $plotTo => $return) {

			$families = [];

			foreach($return['families'] as $family) {
				$families[] = s("{family} de retour après {gap} sur {beds}", [
					'family' => $family['name'],
					'gap' => p("{value} an", "{value} ans", $family['gap']),
					'beds' => p("{value} planche", "{value} planches", $family['beds']),
				]);
			}

			$plants = [];

			foreach($return['plants'] as $rank => $plantsGap) {

				foreach($plantsGap as $plantId => $gap) {
					$plants[$rank][$plantId] = p("de retour après {value} an", "de retour après {value} ans", $gap);
				}

			}

			$texts[$plotTo] = [
				'text' => implode(' · ', $families),
				'plants' => $plants,
			];

		}

		return $texts;

	}

	/**
	 * Les bandes d'état d'un jardin, en modèles : une bande cachée dans le deck resterait son dernier
	 * enfant, et le deck n'arrondirait plus ce qui est vraiment affiché en bas.
	 */
	protected function getStates(): string {

		$h = '<template class="garden-chairs-states">';
			$h .= '<div class="util-deck-band util-deck-band-state" data-state="watch">'.\Asset::icon('exclamation-circle').' <span></span></div>';
			$h .= '<div class="util-deck-band util-deck-band-state" data-state="none">'.\Asset::icon('check-lg').' '.s("Rotation respectée").'</div>';
			$h .= '<div class="util-deck-band util-deck-band-state" data-state="missing">'.s("À définir").'</div>';
		$h .= '</template>';

		return $h;

	}

	/**
	 * Un compteur que la page réécrit à chaque choix, avec ses deux formes : le singulier et le
	 * pluriel ne se décident qu'au chiffre.
	 */
	protected static function getCounter(string $name, string $singular, string $plural): string {
		return '<span data-counter="'.$name.'" data-singular="'.encode($singular).'" data-plural="'.encode($plural).'"></span>';
	}

	protected function getStrip(\Collection $cBed): string {

		$h = '<div class="garden-chairs-strip" style="--beds: '.$cBed->count().'">';

			foreach($cBed as $eBed) {

				$plants = $eBed['cPlant']->empty() ? s("Aucune série") : implode(', ', $eBed['cPlant']->getColumn('name'));

				$h .= '<div class="garden-chairs-bed" title="'.encode($eBed['name'].' · '.$plants).'">';
					$h .= '<div class="garden-chairs-bed-name">'.encode(mb_substr($eBed['name'], 0, 6)).'</div>';
					$h .= '<div class="garden-chairs-bed-plants">';

						// Un carré de quatre places : au-delà de quatre espèces, la quatrième dit combien il en manque.
						$cPlant = ($eBed['cPlant']->count() > 4) ? $eBed['cPlant']->slice(0, 3) : $eBed['cPlant'];

						foreach($cPlant as $ePlant) {
							$h .= '<span class="garden-chairs-plant" data-plant="'.$ePlant['id'].'" title="'.encode($ePlant['name']).'">'.\plant\PlantUi::getVignette($ePlant, '1.1rem').'</span>';
						}

						if($eBed['cPlant']->count() > 4) {
							$h .= '<span class="garden-chairs-bed-count">+'.($eBed['cPlant']->count() - 3).'</span>';
						}

					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les jardins du groupe rangés par parcelle, dans l'ordre du plan.
	 */
	protected static function getDestinations(\Collection $cPlot): array {

		$destinations = [];

		foreach($cPlot as $ePlot) {
			$destinations[$ePlot['zone']['id']] ??= ['label' => $ePlot['zone']['name'], 'values' => []];
			$destinations[$ePlot['zone']['id']]['values'][$ePlot['id']] = \map\PlotUi::getName($ePlot);
		}

		return $destinations;

	}

	/**
	 * Le menu « Séries vers » d'un jardin, en groupes de parcelle quand le groupe en couvre plusieurs.
	 * Le jardin lui-même garde son nom nu, précédé de « Restent sur ».
	 */
	protected static function getDestinationsFrom(array $destinations, \map\Plot $ePlot, bool $severalZones): array {

		$zoneId = $ePlot['zone']['id'];

		$destinations[$zoneId]['values'][$ePlot['id']] = s("Restent sur {value}", $destinations[$zoneId]['values'][$ePlot['id']]);

		return $severalZones ? array_values($destinations) : $destinations[$zoneId]['values'];

	}

}
?>
