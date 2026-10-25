<?php
namespace series;

class SeriesUi {

	public function __construct() {

		\Asset::css('series', 'series.css');
		\Asset::js('series', 'series.js');

	}

	public static function name(Series $eSeries): string {

		$eSeries->expects(['id', 'name', 'mode']);

		$name = encode($eSeries['name']);

		if($eSeries['mode'] === Series::GREENHOUSE) {
			$name .= \Asset::icon('greenhouse', ['style' => 'margin-left: 0.5rem']);
		} else if($eSeries['mode'] === Series::MIX) {
			$name .= \Asset::icon('mix', ['style' => 'margin-left: 0.5rem']);
		}

		return $name;

	}

	public static function link(Series $eSeries, bool $newTab = FALSE): string {

		$eSeries->expects(['id', 'name']);

		return '<a href="'.self::url($eSeries).'" '.($newTab ? 'target="_blank"' : '').'>'.self::name($eSeries).'</a>';

	}

	public static function url(Series $eSeries): string {

		$eSeries->expects(['id']);

		return '/serie/'.$eSeries['id'];

	}

	public static function getPanelHeader(Series $eSeries): string {

		$eSeries->expects(['name', 'season']);

		$h = '<div>';
			$h .= '<div class="badge badge-solid bg-secondary" style="margin-right: 0.5rem">'.s("Saison {value}", $eSeries['season']).'</div>';
			$h .= s("Série {name}", ['name' => self::link($eSeries)]);
		$h .= '</div>';

		return $h;

	}

	public static function getOutcomeIcon(string $outcome): string {

		[$icon, $color] = match($outcome) {
			Series::SUCCESS => ['emoji-smile-fill', 'color-success'],
			Series::MIXED => ['emoji-neutral-fill', 'color-warning'],
			Series::FAILURE => ['emoji-frown-fill', 'color-danger'],
			Series::CANCELED => ['slash-circle', 'color-muted'],
		};

		return \Asset::icon($icon, ['class' => $color]);

	}

	public function getHeader(Series $eSeries): string {

		$state = '';

		if($eSeries['status'] === Series::CLOSED) {
			$state .= '<span class="badge badge-neutral">'.\Asset::icon('lock-fill').' '.s("Clôturée").'</span>';
			if($eSeries['outcome'] !== NULL) {
				$state .= '<span class="badge badge-neutral">'.self::getOutcomeIcon($eSeries['outcome']).' '.self::p('outcome')->values[$eSeries['outcome']].'</span>';
			}
		}

		$infos = [];

		if($eSeries['cycle'] === Series::PERENNIAL) {
			$start = $eSeries['season'] - $eSeries['perennialSeason'] + 1;
			if($eSeries['perennialLifetime'] !== NULL) {
				$perennial = p("Culture pérenne en place depuis la saison {start} pour {value} an", "Culture pérenne en place depuis la saison {start} pour {value} ans", $eSeries['perennialLifetime'], ['start' => $start]);
			} else {
				$perennial = s("Culture pérenne en place depuis la saison {start}", ['start' => $start]);
			}
		} else {
			$perennial = s("Culture annuelle");
		}

		$infos[] = $perennial;

		if($eSeries['sequence']->notEmpty()) {
			$infos[] = s("Itinéraire technique {value}", '<u>'.\sequence\SequenceUi::link($eSeries['sequence']).'</u>');
		}

		$context = '<div class="badge badge-solid bg-secondary" style="margin-right: 0.5rem">'.s("Saison {value}", $eSeries['season']).'</div>';
		$context .= implode(' | ', $infos);

		$settings = '';

		if($eSeries->canWrite()) {

			$settings .= '<a data-dropdown="bottom-end" class="btn btn-color-primary dropdown-toggle">'.\Asset::icon('gear-fill').'</a>';
			$settings .= '<div class="dropdown-list">';
				$settings .= '<div class="dropdown-title">'.encode($eSeries['name']).'</div>';
				if($eSeries['status'] === Series::OPEN) {
					$settings .= '<a href="/series/series:update?id='.$eSeries['id'].'" class="dropdown-item">'.s("Modifier la série").'</a>';
					if($eSeries['comment'] === NULL) {
						$settings .= '<a href="/series/series:updateComment?id='.$eSeries['id'].'" class="dropdown-item">'.s("Ajouter un commentaire").'</a>';
					}
					$settings .= '<a href="/series/cultivation:create?series='.$eSeries['id'].'" class="dropdown-item">'.s("Ajouter une autre production").'</a>';
					$settings .= '<div class="dropdown-divider"></div>';
				}

				if($eSeries->acceptOpen()) {
					$settings .= '<a data-ajax="/series/series:doUpdateStatus" post-id="'.$eSeries['id'].'" post-status="'.Series::OPEN.'" class="dropdown-item">'.s("Réouvrir la série").'</a>';
				}

				if($eSeries->acceptClose()) {
					$settings .= '<a href="/series/series:closeCollection?ids[]='.$eSeries['id'].'" class="dropdown-item">'.s("Clôturer la série").'</a>';
				}
				if($eSeries->acceptDuplicate()) {
					$settings .= '<a href="/series/series:duplicate?ids[]='.$eSeries['id'].'" class="dropdown-item">'.s("Dupliquer la série").'</a>';
					$settings .= '<a href="/series/series:createSequence?id='.$eSeries['id'].'" class="dropdown-item">'.s("Créer un itinéraire technique").'</a>';
					$settings .= '<div class="dropdown-divider"></div>';
				}
				$settings .= '<a data-ajax="/series/series:doDelete" post-id="'.$eSeries['id'].'" data-confirm="'.s("Souhaitez-vous réellement supprimer cette série de votre plan de culture ? Les interventions seront également supprimées dans le planning.").'" class="dropdown-item">'.s("Supprimer la série").'</a>';
			$settings .= '</div>';

		}

		return \main\DesignUi::getPageHeader(
			$eSeries->quick('name', SeriesUi::name($eSeries)),
			context: $context,
			state: $state,
			back: \structure\StructureUi::urlCultivationSeries($eSeries['structure']),
			settings: $settings
		);

	}

	/**
	 * La coque du bac : le contenu arrive en ajax au premier clic sur « Assoler
	 * mes séries ». Une carte par culture, c'est le plus gros du HTML de la page,
	 * et il ne s'ouvre pas toujours.
	 */
	public static function getSelectorShell(\structure\Structure $eStructure, int $season): string {

		\Asset::js('series', 'seriesSelector.js');

		return '<div id="series-selector" class="hide" data-structure="'.$eStructure['id'].'" data-season="'.$season.'"></div>';

	}

	public function getSelector(\structure\Structure $eStructure, \Collection $ccCultivation): string {

		$cCultivation = $this->getSelectorBySeries($this->getSelectorFlat($ccCultivation));
		[$zero, $gap] = $this->getSelectorCounts($cCultivation);

		$h = '<div id="series-selector">';

			$h .= '<div class="series-selector-head">';
				$h .= '<h3>'.s("Séries").'</h3>';
				$h .= new \util\FormUi()->select(
					NULL,
					[
						'all' => s("Toutes"),
						'zero' => s("Non assolées ({value})", $zero),
						'gap' => s("Partiellement assolées ({value})", $gap),
					],
					attributes: [
						'id' => 'series-selector-filter',
						'onchange' => 'SeriesSelector.filter()',
						'required' => TRUE,
					]);
				$h .= '<a onclick="SeriesPlace.stopEditing()" class="btn btn-only-icon btn-lg">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';

			$h .= $this->getSelectorSeries($eStructure, $ccCultivation);

		$h .= '</div>';

		return $h;

	}

	protected function getSelectorFlat(\Collection $ccCultivation): \Collection {

		$cCultivation = new \Collection();

		foreach($ccCultivation as $cCultivationPlant) {

			foreach($cCultivationPlant as $eCultivation) {
				$cCultivation[] = $eCultivation;
			}

		}

		return $cCultivation;

	}

	protected static function getSelectorDates(Cultivation $eCultivation, Series $eSeries): string {

		$parts = [];

		$start = ($eCultivation['startWeek'] !== NULL and $eCultivation['startAction'] !== NULL) ?
			(($eCultivation['startWeek'] + 100) % 100) :
			NULL;

		$harvests = array_merge($eCultivation['harvestWeeksExpected'] ?? [], $eCultivation['harvestWeeks'] ?? []);
		$stop = ($harvests === []) ? NULL : (int)substr((string)max($harvests), -2);

		if($start !== NULL) {

			$parts[] = s("implanté").' '.(($stop === NULL or $stop === $start) ? 's'.$start : 's'.$start.' - s'.$stop);

		} else if($stop !== NULL) {

			$from = (int)substr((string)min($harvests), -2);

			$parts[] = s("récolté").' '.(($from === $stop) ? 's'.$from : 's'.$from.' - s'.$stop);

		}

		$mode = match($eSeries['mode']) {
			Series::GREENHOUSE => s("sous abri"),
			Series::OPEN_FIELD => s("plein champ"),
			default => NULL,
		};

		if($mode !== NULL) {
			$parts[] = $mode;
		}

		if($eSeries['bedWidth'] !== NULL) {
			$parts[] = s("planches de {value} cm", $eSeries['bedWidth']);
		}

		return ($parts === []) ? '' : '<div class="series-selector-dates">'.implode(' · ', $parts).'</div>';

	}

	protected function getSelectorBySeries(\Collection $cCultivation): \Collection {

		$cCultivationSeries = new \Collection();
		$kept = [];

		foreach($cCultivation as $eCultivation) {

			$seriesId = $eCultivation['series']['id'];

			if(
				isset($kept[$seriesId]) === FALSE or
				$eCultivation['id'] < $kept[$seriesId]['id']
			) {
				$kept[$seriesId] = $eCultivation;
			}

		}

		foreach($cCultivation as $eCultivation) {

			if($kept[$eCultivation['series']['id']]['id'] === $eCultivation['id']) {
				$cCultivationSeries[] = $eCultivation;
			}

		}

		return $cCultivationSeries;

	}

	protected function getSelectorCounts(\Collection $cCultivation): array {

		$zero = 0;
		$gap = 0;

		foreach($cCultivation as $eCultivation) {

			$eSeries = $eCultivation['series'];

			if($eSeries['status'] === Series::CLOSED) {
				continue;
			}

			[$value, $target] = self::getSelectorValues($eSeries);

			if($target === NULL or $value >= $target) {
				continue;
			}

			if($value > 0) {
				$gap++;
			} else {
				$zero++;
			}

		}

		return [$zero, $gap];

	}

	public function getSelectorSeries(\structure\Structure $eStructure, \Collection $ccCultivation, ?Series $eSeriesContext = NULL, ?\Collection $cZone = NULL, ?\Search $search = NULL) {

		\Asset::js('series', 'seriesSelector.js');

		$cCultivation = $this->getSelectorFlat($ccCultivation);
		$paints = $this->getSelectorPaints($eStructure, $cCultivation);

		$h = '<div id="series-selector-list">';

			foreach($this->getSelectorBySeries($cCultivation) as $eCultivation) {
				$h .= $this->getSelectorCultivation($eStructure, $eCultivation, $eSeriesContext, $cZone, $search, $paints[$eCultivation['series']['id']] ?? PlaceGardenAiUi::FAMILY_FALLBACK);
			}

		$h .= '</div>';

		return $h;

	}

	public function getSelectorCards(\structure\Structure $eStructure, \Collection $ccCultivation, Series $eSeries, ?Series $eSeriesContext = NULL, ?\Collection $cZone = NULL, ?\Search $search = NULL, string $panel = ''): array {

		$cCultivation = $this->getSelectorFlat($ccCultivation);
		$paints = $this->getSelectorPaints($eStructure, $cCultivation);

		$cards = [];

		foreach($this->getSelectorBySeries($cCultivation) as $eCultivation) {

			if($eCultivation['series']['id'] !== $eSeries['id']) {
				continue;
			}

			$cards[$eCultivation['id']] = $this->getSelectorCultivation($eStructure, $eCultivation, $eSeriesContext, $cZone, $search, $paints[$eCultivation['series']['id']] ?? PlaceGardenAiUi::FAMILY_FALLBACK, $panel);

		}

		return $cards;

	}

	protected static function getSelectorValues(Series $eSeries): array {

		if($eSeries['use'] === Series::BED) {
			return [$eSeries['length'] ?? 0, $eSeries['lengthTarget'], s("mL")];
		} else {
			return [$eSeries['area'] ?? 0, $eSeries['areaTarget'], s("m²")];
		}

	}

	public function getSelectorCultivation(\structure\Structure $eStructure, Cultivation $eCultivation, ?Series $eSeriesContext = NULL, ?\Collection $cZone = NULL, ?\Search $search = NULL, string $paint = PlaceGardenAiUi::FAMILY_FALLBACK, string $panel = ''): string {

		$paintShadow = str_starts_with($paint, 'linear-gradient') ?
			trim(explode(' ', explode('(to right, ', $paint)[1])[0]) :
			$paint;

		$paintInk = self::getSelectorInk($paintShadow);

		$paintPlant = $eCultivation['plant']['color'] ?: PlaceGardenAiUi::FAMILY_FALLBACK;
		$eFamily = $eCultivation['plant']['family'];
		$paintFamily = ($eFamily->notEmpty() and $eFamily['color'] and strtolower($eFamily['color']) !== '#000000') ?
			$eFamily['color'] :
			PlaceGardenAiUi::FAMILY_FALLBACK;

		$eSeries = $eCultivation['series'];

		$startTs = $eSeries->getBedStart() ? strtotime($eSeries->getBedStart().' 00:00:00') : NULL;
		$stopTs = $eSeries->getBedStop() ? strtotime($eSeries->getBedStop().' 23:59:59') : NULL;

		[$value, $target, $unit] = self::getSelectorValues($eSeries);

		$color = '';

		if($target !== NULL) {

			if($value === 0) {
				$color = 'zero';
			} else if($value > $target) {
				$color = 'over';
			} else if($value < $target) {
				$color = 'gap';
			} else {
				$color = 'done';
			}

		}

		$isClosed = ($eSeries['status'] === Series::CLOSED);

		$isContext = (
			$eSeriesContext !== NULL and
			$eSeriesContext->notEmpty() and
			$eSeries->is($eSeriesContext)
		);

		$startWeek = '';
		$startColor = '';

		if($eCultivation['startAction'] !== NULL and $eCultivation['startWeek'] !== NULL) {
			$startWeek = s("s{value}", week_number($eCultivation->getStartWeek($eCultivation['season'])));
			$startColor = (string)($eCultivation['startColor'] ?? '');
		}

		$h = '<div id="series-selector-'.$eCultivation['id'].'" class="series-selector-cultivation '.($isContext ? 'selected ' : '').($isClosed ? 'series-selector-closed' : '').' '.($color === 'done' ? 'series-selector-full' : '').'" data-series="'.$eSeries['id'].'" data-cultivation="'.$eCultivation['id'].'" data-plant="'.encode($eCultivation['plant']['name'] ?? '').'" data-start="'.$startTs.'" data-stop="'.$stopTs.'" data-status="'.$eSeries['status'].'" data-start-week="'.$startWeek.'" data-start-color="'.encode($startColor).'" style="--series-paint: '.$paint.'; --series-paint-shadow: '.$paintShadow.'; --series-ink: '.$paintInk.'; --series-paint-plant: '.$paintPlant.'; --series-paint-family: '.$paintFamily.';">';

			$h .= '<div class="series-selector-header" onclick="SeriesSelector.select('.$eCultivation['id'].')">';

				$h .= '<span class="series-selector-dot" style="background-color: '.self::getSelectorColor($eStructure, $eCultivation).'"></span>';

				$h .= '<span class="series-selector-title">';

					$h .= '<span class="series-selector-name">';
						$h .= SeriesUi::name($eSeries);
						if($isClosed) {
							$h .= ' '.\Asset::icon('lock-fill');
						}
					$h .= '</span>';

					$h .= \sequence\CropUi::start($eCultivation, \work\WorkSetting::$mainActions, fontSize: '0.7em');

				$h .= '</span>';

				$h .= '<a href="'.SeriesUi::url($eSeries).'" onclick="event.stopPropagation()" class="series-selector-open" title="'.s("Ouvrir la série").'">'.\Asset::icon('box-arrow-up-right').'</a>';

				// Les deux sont rendus et le CSS bascule sur .selected : le repli se fait
				// côté client, sans reconstruire le markup, et n'aurait donc pas su
				// rendre la longueur si le serveur ne l'avait pas écrite.
				// L'en-tête entier ouvre la série : sans arrêter la propagation, le
				// bouton la refermait et le clic la rouvrait dans la foulée.
				$h .= '<a onclick="event.stopPropagation(); SeriesSelector.deselect()" class="btn btn-sm btn-transparent place-context-close">'.s("Fermer").'</a>';

				$h .= '<span class="series-selector-place" data-color="'.$color.'" data-value-target="'.$target.'">';
					$h .= '<span class="series-selector-value">'.$value.'</span>';
					if($target !== NULL) {
						$h .= '<span class="series-selector-target"> / '.$target.'</span>';
					}
					$h .= ' '.$unit;
				$h .= '</span>';

			$h .= '</div>';

			if($isContext) {
				$h .= self::getSelectorDates($eCultivation, $eSeries);
			}

			$actions = '';
			$remove = '';
			$footer = $panel;

			if(
				$isClosed === FALSE and
				$eCultivation->canWrite()
			) {

				if($value > 0) {
					$remove = '<a class="place-context-remove series-selector-remove" onclick="SeriesPlace.removeCultivation('.$eCultivation['id'].')" data-confirm="'.s("Tout l'assolement de cette série sera effacé. Continuer ?").'">'.s("Supprimer l'assolement").'</a>';
				}

			}

			if($isContext) {

				// Une série clôturée ne se pose plus : les filtres, qui servent à
				// choisir où, n'ont rien à filtrer pour elle.
				$h .= new PlaceUi()->getPlaceContext($eSeriesContext, $eSeriesContext['cCultivation'], $cZone, $actions, $isClosed ? NULL : $search, $footer, $remove);

			}

		$h .= '</div>';

		return $h;

	}

	public static function getSelectorInk(string $color): string {

		if(preg_match('/^#([0-9a-f]{6})$/i', $color, $match) === 0) {
			return '#ffffff';
		}

		[$red, $green, $blue] = sscanf($match[1], '%2x%2x%2x');

		$channel = fn(int $value) => ($value / 255 <= 0.04045) ?
			$value / 255 / 12.92 :
			(($value / 255 + 0.055) / 1.055) ** 2.4;

		$luminance = 0.2126 * $channel($red) + 0.7152 * $channel($green) + 0.0722 * $channel($blue);

		$onWhite = 1.05 / ($luminance + 0.05);
		$onDark = ($luminance + 0.05) / 0.0637;

		return ($onDark > $onWhite) ? '#1f1f1f' : '#ffffff';

	}

	protected function getSelectorPaints(\structure\Structure $eStructure, \Collection $cCultivation): array {

		$colors = [];

		foreach($cCultivation as $eCultivation) {

			$seriesId = $eCultivation['series']['id'];
			$color = self::getSelectorColor($eStructure, $eCultivation);

			if(in_array($color, $colors[$seriesId] ?? [], TRUE) === FALSE) {
				$colors[$seriesId][] = $color;
			}

		}

		$paints = [];

		foreach($colors as $seriesId => $list) {

			if(count($list) === 1) {
				$paints[$seriesId] = $list[0];
				continue;
			}

			$stops = [];

			foreach($list as $position => $color) {
				$stops[] = $color.' '.round(100 * $position / (count($list) - 1)).'%';
			}

			$paints[$seriesId] = 'linear-gradient(to right, '.implode(', ', $stops).')';

		}

		return $paints;

	}

	protected static function getSelectorColor(\structure\Structure $eStructure, Cultivation $eCultivation): string {

		if($eStructure->getView('viewSoilColor') === \structure\Member::PLANT) {
			return $eCultivation['plant']['color'] ?: PlaceGardenAiUi::FAMILY_FALLBACK;
		}

		return PlaceUi::getFamilyColor($eCultivation);

	}

	public function getComment(Series $eSeries): string {

		if($eSeries['comment'] === NULL) {
			return '';
		}

		$h = '<div class="util-note">';

			$h .= '<span class="util-note-pin">'.\Asset::icon('pin-angle-fill').'</span>';

			$h .= '<div class="util-title">';
				$h .= '<h3>'.s("Commentaire").'</h3>';
				$h .= '<a href="/series/series:updateComment?id='.$eSeries['id'].'" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('pencil-fill').'</a>';
			$h .= '</div>';

			$h .= new \editor\EditorUi()->value($eSeries['comment']);

		$h .= '</div>';

		return $h;

	}

	public function updatePlace(Series $eSeries, \Collection $cPlace): string {

		$h = '<div class="crop-item" id="series-soil">';

			$h .= '<div class="crop-item-header">';
				$h .= '<div class="crop-item-title">';
					$h .= '<h2 class="series-soil-title">';
						$h .= s("Assolement");
					$h .= '</h2>';
					if(
						$eSeries->canWrite() and
						$eSeries['status'] === Series::OPEN and
						$cPlace->notEmpty()
					) {
						$h .= '<div>';
							$h .= '<a href="/series/place:update?series='.$eSeries['id'].($eSeries['mode'] === Series::GREENHOUSE ? '&mode='.Series::GREENHOUSE : '').'&origin=series" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
						$h .= '</div>';
					}
				$h .= '</div>';
				$h .= '<div class="crop-item-soil-infos">';
				$h .= [
					Series::BED => s("Planches {value} cm", $eSeries['bedWidth']),
					Series::BLOCK => s("Surface libre"),
				][$eSeries['use']];

				if($eSeries['alleyWidth'] !== NULL) {
					$h .= ' / '.s("Passe-pieds {value} cm", $eSeries['alleyWidth']);
				}

				switch($eSeries['use']) {

					case Series::BED;
						if($eSeries['lengthTarget']) {
							$h .= ' / '.$eSeries->quick('lengthTarget', s("Objectif de {lengthTarget} mL", $eSeries));
						}
						if($eSeries['length']) {
							$h .= ' / '.s("Actuellement {length} mL", $eSeries);
						}
						break;

					case Series::BLOCK;
						if($eSeries['areaTarget']) {
							$h .= ' / '.$eSeries->quick('areaTarget', s("Objectif de {areaTarget} m²", $eSeries));
						}
						if($eSeries['area']) {
							$h .= ' / '.s("Actuellement {area} m²", $eSeries);
						}
						break;

				}

				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="crop-item-body">';
				if($cPlace->empty()) {

					if(
						$eSeries->canWrite() and
						$eSeries['status'] === Series::OPEN
					) {
						$h .= '<div class="series-soil-empty">';
							$h .= '<a href="/series/place:update?series='.$eSeries['id'].($eSeries['mode'] === Series::GREENHOUSE ? '&mode='.Series::GREENHOUSE : '').'&origin=series" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Définir l'assolement pour cette série").'</a>';
						$h .= '</div>';
					} else {
						$h .= '<div class="series-soil-empty">';
							$h .= s("L'assolement n'a pas été défini.");
						$h .= '</div>';
					}

				} else {
					$h .= $this->getPlace('series', $eSeries, $cPlace);
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function displayImport(\structure\Structure $eStructure, int $nSeries, \Collection $cSeriesPerennial, bool $firstSeries, int $currentSeason): string {

		if($eStructure->canManage() === FALSE) {
			return '';
		}

		$previousSeason = $currentSeason - 1;

		$h = '';

		if($nSeries === 0) {

			if($firstSeries) {

				$start = '<p>'.s("Vous êtes sur la page qui permet de créer des séries pour votre plan de culture.").'</p>';
				$start .= '<p>'.s("Vous pouvez dès maintenant créer une première série pour la saison {value}. Si vous souhaitez créer une série pour une autre année, utilisez le menu déroulant ci-dessus pour changer de saison.", $currentSeason).'</p>';

				$h .= new \main\DesignUi()->getStart(
					\Asset::icon('journals'),
					s("Le plan de culture de votre ferme"),
					$start
				);

				$h .= '<br/>';
				$h .= '<h3>'.s("Ajouter une première série dans le plan de culture").'</h3>';

				$h .= '<div class="util-block">';
					$h .= $this->createFrom($eStructure, $currentSeason)->body;
				$h .= '</div>';

			} else {

				$h .= '<div class="util-block-info">';
					$h .= '<h4>'.s("Vous n'avez pas encore créé de série sur la saison {current} !", ['current' => $currentSeason]).'</h4>';
					$h .= '<p>'.s("Pour démarrer, vous pouvez <link>créer une nouvelle série</link> dès maintenant pour cette saison.", ['link' => '<a data-get="/series/series:createFrom?structure='.$eStructure['id'].'&season='.$currentSeason.'" data-ajax-class="Ajax.Query">']).'</p>';

					if($eStructure->conf(\map\Configuration::class)['seasonFirst'] < $currentSeason) {
						$h .= '<p>'.s("Vous pouvez également créer facilement votre plan de culture {current} en dupliquant les séries de productions annuelles qui ont bien fonctionné lors des saisons précédentes. Pour cela, retournez simplement sur la planification de la saison de votre choix, puis cochez vos séries préférées et enfin dupliquez-les !", ['current' => $currentSeason]).'</p>';
						if($previousSeason === $eStructure->conf(\map\Configuration::class)['seasonFirst']) {
							$h .= '<a href="'.\structure\StructureUi::urlCultivationSeries($eStructure, \structure\Member::AREA, season: $previousSeason).'" class="btn btn-secondary">'.s("Revenir sur la planification {previous}",  ['previous' => $previousSeason]).'</a>';
						} else {
							$h .= '<a data-dropdown="bottom-start" class="dropdown-toggle btn btn-secondary">'.s("Revenir sur la planification d'une autre saison").'</a>';
							$h .= '<div class="dropdown-list">';
								for($season = $currentSeason - 1, $count = 1; $season >= $eStructure->conf(\map\Configuration::class)['seasonFirst'], $count <= 3; $season--, $count++) {
									$h .= '<a href="'.\structure\StructureUi::urlCultivationSeries($eStructure, \structure\Member::AREA, season: $season).'" class="btn btn-secondary">'.s("Saison {value}", $season).'</a>';
								}
							$h .= '</div>';
						}
					}
				$h .= '</div>';

			}

		}

		if($cSeriesPerennial->notEmpty()) {

			$h .= '<div class="util-block">';

				$h .= '<h3>'.s("Que voulez-vous faire des productions pérennes de la saison {previous} ?", ['previous' => $previousSeason]).'</h3>';

				if($cSeriesPerennial->notEmpty()) {

					$h .= '<div class="util-overflow-md">';
						$h .= '<table class="tr-even">';

							$h .= '<thead>';
								$h .= '<tr>';
									$h .= '<th colspan="2">'.s("Série").'</th>';
									$h .= '<th>'.s("Démarrée").'</th>';
									$h .= '<th colspan="2">'.s("Actions").'</th>';
								$h .= '</tr>';
							$h .= '</thead>';
							$h .= '<tbody>';

								foreach($cSeriesPerennial as $eSeries) {

									$h .= '<tr>';

										$h .= '<td>'.SeriesUi::link($eSeries).'</td>';
										$h .= '<td>';
											$h .= '<div class="series-import-cultivation">';

												foreach($eSeries['cCultivation'] as $eCultivation) {

													$h .= '<div>';
														$h .= \plant\PlantUi::getVignette($eCultivation['plant'], '2rem').' '.encode($eCultivation['plant']['name']);
													$h .= '</div>';

												}

											$h .= '</div>';
										$h .= '</td>';
										$h .= '<td>';
											$h .= s("Saison {value}", $eSeries['season'] - $eSeries['perennialSeason'] + 1);
											$h .= ' &bull; ';
											$h .= s("{value} saison", \util\TextUi::th($eSeries['perennialSeason'] + 1));
										$h .= '</td>';
										$h .= '<td class="td-min-content">';
											$h .= '<a data-ajax="/series/series:perennialContinued" post-id="'.$eSeries['id'].'" class="btn btn-success">'.s("Continuer cette saison").'</a>';
										$h .= '</td>';
										$h .= '<td>';
											$h .= '<a data-ajax="/series/series:perennialFinished" post-id="'.$eSeries['id'].'" class="btn btn-danger" data-confirm="'.s("Confirmez-vous que cette série ne sera pas cultivée pour la saison {value} ?", $eSeries['season'] + 1).'">'.s("Arrêter").'</a>';
										$h .= '</td>';

									$h .= '</tr>';

								}

							$h .= '</tbody>';

						$h .= '</table>';
					$h .= '</div>';

				}

			$h .= '</div>';

		}

		return $h;

	}

	public function getPlace(string $source, Series|Task $e, \Collection $cPlace): string {

		$use = ($source === 'task') ? Series::BED : $e['use'];

		$hasTotal = ($source === 'series' and $cPlace->count() > 1);
		$hasTarget = ($hasTotal and match($use) {
			Series::BED => (bool)$e['lengthTarget'],
			Series::BLOCK => (bool)$e['areaTarget']
		});

		$h = '<div class="series-soil-grid series-soil-grid-'.$source.($hasTarget ? ' series-soil-grid-target' : '').'">';

			$h .= '<div class="util-table-header">'.s("Parcelle").'</div>';
			$h .= '<div class="util-table-header">'.s("Jardin").'</div>';
			$h .= '<div class="util-table-header">';
				if($use === Series::BED) {
					$h .= s("Planche");
				}
			$h .= '</div>';
			if($source === 'series') {
				$h .= '<div class="util-table-header text-end">'.s("Utilisation").'</div>';
				$h .= '<div class="util-table-header text-end" style="grid-column: span 2">'.s("Surface").'</div>';
			}

			// La colonne de « de l'objectif » n'existe que si le total la remplit : vide, l'écart de grille
			// posé devant elle laisserait plus de blanc à droite du tableau qu'à gauche.
			if($hasTarget) {
				$h .= '<div class="util-table-header"></div>';
			}

			foreach($cPlace as $ePlace) {

				$h .= '<div class="series-soil-grid-zone">'.encode($ePlace['zone']['name']).'</div>';
				$h .= '<div class="series-soil-grid-plot">';
					$h .= encode($ePlace['plot']['name']);
					if($ePlace['plot']['mode'] === \map\Plot::GREENHOUSE) {
						$h .= '  '.\Asset::icon('greenhouse');
					}
				$h .= '</div>';
				$h .= '<div class="series-soil-grid-bed">';
					if($use === Series::BED) {

						if($ePlace['bed']['name'] !== NULL) {

							$h .= encode($ePlace['bed']['name']);

							if($ePlace['bed']['status'] === \map\Bed::DELETED) {
								$h .= '<span class="ml-1 color-danger">'.\Asset::icon('exclamation-triangle').' '.s("Planche supprimée").'</span>';
							}

						} else {
							$h .= s("Temporaire");
						}

					}
				$h .= '</div>';

				if($source === 'series') {

					$h .= '<div class="series-soil-grid-use">';
						$h .= match($use) {
							Series::BED => ($ePlace['bed']['length'] !== NULL) ? s("{value} %", round($ePlace['length'] / $ePlace['bed']['length'] * 100)) : '',
							Series::BLOCK => s("{value} %", round($ePlace['area'] / $ePlace['bed']['area'] * 100))
						};
					$h .= '</div>';
					$h .= '<div class="series-soil-grid-area">';

						if($use === Series::BED) {
							$h .= '<b class="util-unit">'.s("{length} mL x {width} cm", $ePlace).'</b>';
						}

					$h .= '</div>';
					$h .= '<div class="series-soil-grid-size util-unit">';
						$h .= match($use) {
							Series::BED => s("({value} m²)", round($ePlace['area'])),
							Series::BLOCK => '<b>'.s("({value} m²)", round($ePlace['area'])).'</b>'
						};
					$h .= '</div>';

				}

				if($hasTarget) {
					$h .= '<div></div>';
				}

			}

			if($hasTotal) {

				$h .= '<div style="grid-column: span 4"></div>';

					if($use === Series::BED) {
						$h .= '<div class="series-soil-grid-area series-soil-grid-total">';
							$h .= '<b class="util-unit">'.s("{length} mL", $e).'</b>';
						$h .= '</div>';
					} else {
						$h .= '<div></div>';
					}

				$h .= '<div class="series-soil-grid-size util-unit series-soil-grid-total">';
					$h .= match($use) {
						Series::BED => s("({value} m²)", round($e['area'])),
						Series::BLOCK => '<b>'.s("({value} m²)", round($e['area'])).'</b>'
					};
				$h .= '</div>';

				switch($use) {

					case Series::BED;
						if($hasTarget) {
							$h .= '<div class="series-soil-grid-total">';
								$h .= s("{value} de l'objectif", \util\TextUi::pc($e['length'] / $e['lengthTarget'] * 100));
							$h .= '</div>';
						}
						break;

					case Series::BLOCK;
						if($hasTarget) {
							$h .= '<div class="series-soil-grid-total">';
								$h .= s("{value} de l'objectif", \util\TextUi::pc($e['area'] / $e['areaTarget'] * 100));
							$h .= '</div>';
						}
						break;

				}

			}

		$h .= '</div>';

		return $h;

	}

	public function createFrom(\structure\Structure $eStructure, int $season): \Panel {

		$form = new \util\FormUi();

		$eCultivation = new Cultivation([
			'structure' => $eStructure
		]);

		$eSeries = new Series([
			'structure' => $eStructure,
			'season' => $season
		]);

		$h = '<div id="series-create-from">';

			$h .= $form->openAjax('/series/series:createFromPlant', ['method' => 'get', 'data-ajax-class' => 'Ajax.Query']);

				$h .= $form->hidden('structure', $eStructure['id']);

				$h .= $form->dynamicGroup($eSeries, 'season', function(\PropertyDescriber $d) use($eStructure, $season) {

					$d->label = s("Pour la saison");

					if($season < \Clock::year()) {

						if(\Clock::format('m') >= \structure\StructureSetting::NEW_SEASON) {
							$nextSeason = s("{value1} ou {value2}", ['value1' => \Clock::year(), 'value2' => \Clock::year() + 1]);
						} else {
							$nextSeason = \Clock::year();
						}

						$d->after = '<div class="util-danger mt-1">'.s("Vous vous apprêtez à créer une série pour une saison déjà passée. Vous pouvez corriger votre choix si vous souhaitez créer une série pour la saison {value}.", $nextSeason).'</div>';

					} else if($season === \Clock::year() and \Clock::format('m') >= \structure\StructureSetting::NEW_SEASON) {

						$nextSeason = $season + 1;

						$after = s("Vous vous apprêtez à créer une série pour la saison en cours alors que l'année est presque terminée.").' ';

						if($nextSeason > $eStructure->conf(\map\Configuration::class)['seasonLast']) {

							$after .= s("Vous pouvez créer la saison {value} dès maintenant pour ajouter des séries sur la saison à venir.", $nextSeason);

							$after .= '<br/><a data-ajax="/map/configuration:doSeasonLast" post-id="'.$eStructure->conf(\map\Configuration::class)['id'].'" post-increment="1" class="btn btn-warning mt-1">';
								$after .= s("Ajouter la saison {year}", ['year' => $nextSeason]);
							$after .= '</a> ';

						} else {
							$after .= s("Corrigez votre choix si vous souhaitez créer une série pour la saison {value}.", $nextSeason);
						}

						$d->after = '<div class="util-warning mt-1">'.$after.'</div>';

					}

					$d->attributes['onchange'] = 'Series.selectCreateSeason(this)';

				});
				$h .= $form->group(content: '<h3 class="mb-0 mt-1">'.s("Créer la série").'</h3>');

				$h .= $form->group(
					s("À partir d'une espèce"),
					$form->dynamicField($eCultivation, 'plant', function($d) use($eStructure) {
						$d->autocompleteDispatch = '#series-create-from-plant';
						$d->attributes = [
							'data-autocomplete-select' => 'submit'
						];
					})
				);

			$h .= $form->close();

			$h .= $form->openAjax('/series/series:createFromSequence', ['method' => 'get', 'data-ajax-class' => 'Ajax.Query']);

				$h .= $form->hidden('structure', $eStructure['id']);
				$h .= $form->hidden('season', $season);
				$h .= $form->group(
					s("À partir d'un itinéraire technique"),
					$form->dynamicField($eCultivation, 'sequence', function($d) use($eStructure) {
						$d->autocompleteBody = ['structure' => $eStructure['id']];
						$d->attributes = [
							'data-autocomplete-select' => 'submit'
						];
					})
				);

			$h .= $form->close();

		$h .= '</div>';

		return new \Panel(
			id: 'panel-series-create',
			title: s("Ajouter une série"),
			body: $h,
			attributes: ['class' => 'panel-series-create']
		);

	}

	public function createFromPlant(\structure\Structure $eStructure, int $season, Series $eSeries, Cultivation $eCultivation, \plant\Plant $ePlant, \Collection $cAction): \Panel {

		$form = new \util\FormUi([
			'firstColumnSize' => 40
		]);

		$index = 0;

		$h = $form->openAjax('/series/series:doCreate?season='.$season.'&structure='.$eStructure['id'], ['id' => 'series-create-plant', 'data-cycle' => $eSeries['cycle']]);

		$h .= $form->hidden('structure', $eStructure['id']);
		$h .= $form->hidden('index', $index);

		$h .= '<div class="series-create-use">';

			$h .= $this->getSeasonField($form, $eSeries);
			$h .= $this->getNameField($form, $eSeries);

			$h .= $form->dynamicGroups($eSeries, ['cycle', 'perennialLifetime', 'mode', 'use']);
			$h .= $this->getBlockFields($form, $eSeries);
		$h .= '</div>';

		$h .= '<div id="series-create-plant-list">';
			$h .= $this->addFromPlant($eSeries, $eCultivation, $ePlant, $index, $cAction, $form);
		$h .= '</div>';

		$h .= '<div id="series-create-add-plant" class="util-block-optional">';
			$h .= $form->group(
				s("Ajouter une autre production").
				'<div class="util-helper">'.s("Ajoutez une autre production à cette série si vous souhaitez associer plusieurs cultures ensemble.").'</div>',
				$form->dynamicField($eCultivation, 'plant', function($d) {
					$d->placeholder = s("Ajouter une autre plante");
					$d->name = 'newPlant';
					$d->autocompleteDispatch = '#series-create-add-plant';
					$d->autocompleteDefault = new \plant\Plant();
				})
			);
		$h .= '</div>';

		$h .= '<div class="series-submit">';

		$h .= $form->group(
			content: $form->submit(s("Créer la série"))
		);

		$h .= '</div>';

		$h .= $form->close();

		return new \Panel(
			id: 'panel-series-create',
			title: s("Ajouter une série"),
			documentTitle: s("Ajouter une série pour {name}", ['name' => $eStructure['name']]),
			body: $h,
			attributes: ['class' => 'panel-series-create']
		);

	}

	protected function getBlockFields(\util\FormUi $form, Series $eSeries): string {

		$h = $form->dynamicGroup($eSeries, 'areaTarget', function($d) use($eSeries) {
			$d->group = ($eSeries['use'] !== Series::BLOCK ? ['class' => 'hide'] : []);
		});

		$h .= $form->dynamicGroup($eSeries, 'lengthTarget', function($d) use($eSeries) {
			$d->group = ($eSeries['use'] !== Series::BED ? ['class' => 'hide'] : []);
		});
		$h .= $form->dynamicGroup($eSeries, 'bedWidth', function($d) use($eSeries) {
			$d->group = ($eSeries['use'] !== Series::BED ? ['class' => 'hide'] : []);
		});
		$h .= $form->dynamicGroup($eSeries, 'alleyWidth', function($d) use($eSeries) {
			$d->group = ($eSeries['use'] !== Series::BED ? ['class' => 'hide'] : []);
		});

		return $h;

	}

	public function addFromPlant(Series $eSeries, Cultivation $eCultivation, \plant\Plant $ePlant, int $index, \Collection $cAction, ?\util\FormUi $form = NULL): string {

		$eSeries->expects(['use', 'cycle', 'season']);

		if($form === NULL) {

			$form = new \util\FormUi([
				'firstColumnSize' => 50
			]);

		}

		$suffix = '['.$index.']';

		$h = '<div class="series-create-plant series-write-plant">';

			$h .= $form->hidden('plant'.$suffix, $ePlant['id']);

			$h .= '<div class="util-title">';

				$h .= '<div class="series-create-plant-title" data-plant-name="'.encode($ePlant['name']).'">';
					$h .= \plant\PlantUi::getVignette($ePlant, '3rem');
					$h .= '<h4>'.\plant\PlantUi::link($ePlant).'</h4>';
				$h .= '</div>';

				$h .= '<div class="series-create-plant-delete hide">';
					$h .= '<a onclick="Series.deletePlant(this)" class="btn btn-only-icon btn-outline-primary">'.\Asset::icon('trash').'</a>';
				$h .= '</div>';

			$h .= '</div>';

			$h .= new CultivationUi()->getFieldsCreate($form, $eSeries['use'], $eCultivation, $cAction, $suffix);

		$h .= '</div>';

		return $h;

	}

	public function createFromSequence(\structure\Structure $eStructure, int $season, \sequence\Sequence $eSequence, \Collection $cCultivation, \Collection $cFlow, array $events): \Panel {

		$eSeries = $cCultivation->first()['series']->merge([
			'name' => $eSequence['name'],
			'nameDefault' => $eSequence['name'],
			'nameAuto' => TRUE
		]);

		$form = new \util\FormUi([
			'firstColumnSize' => 40
		]);

		$h = '';

		$h .= $form->openAjax('/series/series:doCreate?structure='.$eStructure['id'], ['id' => 'series-create-sequence']);

		$h .= $form->hidden('structure', $eStructure['id']);
		$h .= $form->hidden('sequence', $eSequence['id']);

		$h .= '<p class="util-info">'.s("Cette série est initialisée à partir de l'itinéraire technique {name}, dont elle copie les interventions. Modifier ensuite l'itinéraire technique ne changera pas cette série.", ['name' => \sequence\SequenceUi::link($eSequence)]).'</p>';

		$h .= '<div class="series-create-use">';

		$h .= $this->getSeasonField($form, $eSeries);
		$h .= $this->getNameField($form, $eSeries);

		$h .= $form->group(
			SeriesUi::p('cycle')->label,
			$form->fake(SeriesUi::p('cycle')->values[$eSequence['cycle']])
		);
		$h .= $form->dynamicGroup($eSequence, 'perennialLifetime');
		$h .= $form->dynamicGroup($eSequence, 'mode');
		$h .= $form->dynamicGroup($eSequence, 'use');

		$h .= $this->getBlockFields($form, $eSeries);

		$h .= '</div>';

		$h .= '<h3>'.p("Espèce", "Espèces", $cCultivation->count()).'</h3>';

		$h .= '<div id="series-create-plant-list">';

		$index = 0;
		$indexes = [];

		foreach($cCultivation as $eCultivation) {

			$ePlant = $eCultivation['plant'];
			$eCrop = $eCultivation['crop'];

			$indexes[$eCrop['id']] = $index;
			$suffix = '['.$index.']';

			$h .= $form->hidden('plant'.$suffix, $ePlant);
			$h .= $form->hidden('crop'.$suffix, $eCrop);

			$h .= '<div class="series-create-plant series-write-plant">';
				$h .= new CultivationUi()->getCropTitle($eStructure, $ePlant);
				$h .= new CultivationUi()->getFieldsCreate($form, $eSequence['use'], $eCultivation, new \Collection(), $suffix);
			$h .= '</div>';

			$index++;

		}

		$h .= '</div>';

		if($cFlow->notEmpty()) {

			$h .= '<h3>'.s("Rappel des interventions").'</h3>';

			if($eSequence['cycle'] === \sequence\Sequence::ANNUAL) {

				$eFlowFirst = $cFlow->first();
				$startYear = ($eFlowFirst['yearOnly'] ?? $eFlowFirst['yearStart']);
				$startWeek = ($eFlowFirst['weekOnly'] ?? $eFlowFirst['weekStart']);

				$h .= '<div id="series-create-tasks" data-structure="'.$eStructure['id'].'" data-season="'.$season.'" data-sequence="'.$eSequence['id'].'">';
					$h .= $this->getTasksFromSequence($season, $eSequence, $events, $startYear, $startWeek);
				$h .= '</div>';

			} else {
				$h .= new \sequence\FlowUi()->getTimeline($eSequence, $events, FALSE);
			}

		}

		$h .= '<div class="series-submit">';
			$h .= $form->submit(s("Créer la série"));
		$h .= '</div>';

		$h .= $form->close();

		return new \Panel(
			id: 'panel-series-create',
			title: s("Ajouter une série"),
			documentTitle: s("Ajouter une série pour {name}", ['name' => $eStructure['name']]),
			body: $h,
			attributes: ['class' => 'panel-series-create']
		);

	}

	public function getTasksFromSequence(int $season, \sequence\Sequence $eSequence, array $events, int $startYear, int $startWeek): string {

		$form = new \util\FormUi();

		$h = '';

		if($eSequence['cycle'] === \sequence\Sequence::ANNUAL) {

			$h .= '<div class="task-select-start">';

				$h .= $form->group(
					s("Année de démarrage"),
					$form->select('startYear', [0 => $season, -1 => $season - 1], $startYear, ['required' => TRUE])
				);

				$weeks = [];
				for($week = 1; $week <= 52; $week++) {
					$weeks[$week] = s("Semaine {value} → {interval}", ['value' => $week, 'interval' => \util\DateUi::weekToDays($season + $startYear.'-W'.sprintf('%02d', $week), withYear: FALSE)]);
				}

				$h .= $form->group(
					s("Semaine de démarrage"),
					$form->select('startWeek', $weeks, $startWeek, ['required' => TRUE])
				);

			$h .= '</div>';

		}

		$h .= new \sequence\FlowUi()->getTimeline($eSequence, $events, FALSE, $startYear + $season);

		return $h;

	}

	public function update(Series $eSeries): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/series/series:doUpdate', ['id' => 'series-update']);

			$h .= $form->hidden('id', $eSeries['id']);

			if($eSeries['cycle'] === Series::ANNUAL) {
				$h .= $form->dynamicGroup($eSeries, 'season');
			}

			$h .= $form->dynamicGroup($eSeries, 'name');

			if($eSeries['cSequence']->notEmpty()) {

				$h .= $form->dynamicGroup(new Cultivation([
					'sequence' => $eSeries['sequence']
				]), 'sequence', function($d) use($eSeries) {

					$body = [
						'structure' => $eSeries['structure']['id']
					];

					foreach($eSeries['cSequence'] as $position => $eSequence) {
						$body['ids['.$position.']'] = $eSequence['id'];
					}

					$d->autocompleteBody = $body;

				});

			}

			$h .= $form->dynamicGroup($eSeries, 'mode');

			$h .= $form->dynamicGroup($eSeries, 'use', function(\PropertyDescriber $d) use($eSeries) {

				$infos = [];

				if($eSeries['cPlace']->notEmpty()) {
					$infos[] = s("l'assolement de la série sera remis à zéro");
				}

				$reset = $eSeries['cCultivation']->reduce(fn($eCultivation, $n) => (
					(
						($eSeries['use'] === Series::BED and $eCultivation['rows'] !== NULL) or
						($eSeries['use'] === Series::BLOCK and $eCultivation['rowSpacing'] !== NULL)
					)
						? 1 : 0) + $n, 0);

				if($reset) {
					$infos[] = p("les informations concernant la densité de la culture seront réinitialisées et vous devrez les saisir à nouveau", "les informations concernant la densité des cultures seront réinitialisées et vous devrez les saisir à nouveau", $eSeries['plants']);
				}

				if($infos) {

					$text = s("Si vous changez l'utilisation du sol, {value}.", implode(', ', $infos));
					$d->after = \util\FormUi::info($text);

				}

				$d->attributes['callbackRadioAttributes'] = function() {
					return [
						'onclick' => 'Series.refreshUpdateUse(this)'
					];
				};

			});

			if($eSeries['cycle'] === Series::PERENNIAL and $eSeries['perennialStatus'] !== Series::CONTINUED) {
				$h .= $form->dynamicGroup($eSeries, 'perennialLifetime');
			}

			$h .= '<div class="series-update-block '.($eSeries['use'] === Series::BLOCK ? '' : 'hide').'">';
				$h .= $form->dynamicGroup($eSeries, 'areaTarget');
			$h .= '</div>';

			$h .= '<div class="series-update-bed '.($eSeries['use'] === Series::BED ? '' : 'hide').'">';
				$h .= $form->dynamicGroups($eSeries, ['lengthTarget', 'bedWidth', 'alleyWidth']);
			$h .= '</div>';

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-series-update',
			title: s("Modifier une série"),
			body: $h,
		);

	}

	public function updateComment(Series $eSeries): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/series/series:doUpdateComment');

			$h .= $form->hidden('id', $eSeries['id']);
			$h .= $form->dynamicField($eSeries, 'comment');
			$h .= '<div class="util-action mt-1">';
				$h .= $form->submit(s("Enregistrer"));
				if($eSeries['comment'] !== NULL) {
					$h .= '<a data-ajax="/series/series:doUpdateComment" post-id="'.$eSeries['id'].'" post-comment="" data-confirm="'.s("Supprimer le commentaire ?").'" class="btn btn-outline-danger">'.s("Supprimer").'</a>';
				}
			$h .= '</div>';

		$h .= $form->close();

		return new \Panel(
			id: 'panel-series-update',
			title: s("Commentaire de la série"),
			body: $h,
		);

	}

	public function updateSeason(\structure\Structure $eStructure, \Collection $cSeries): \Panel {

		$form = new \util\FormUi();

		$eSeriesFirst = $cSeries->first();
		$eSeriesFirst['structure'] = $eStructure; // Pour avoir la première et la dernière saison de la ferme

		$h = \main\DesignUi::getWeigh(
			'info',
			'calendar-range',
			p("Modifier la saison de la série", "Modifier la saison des {value} séries", $cSeries->count()),
			NULL,
			[
				['icon' => 'arrow-left-right', 'text' => p("<b>La série</b> sera déplacée sur la nouvelle saison, sans être dupliquée.", "<b>Les {value} séries</b> seront déplacées sur la nouvelle saison, sans être dupliquées.", $cSeries->count())]
			],
			[s("Les dates des interventions ne changent pas, et se décalent à la main si besoin.")],
			TRUE
		);

		$h .= $this->getSeriesField($form, $cSeries);
		$h .= $form->dynamicGroup($eSeriesFirst, 'season', function($d) {
			$d->label = s("Nouvelle saison");
		});

		return new \Panel(
			id: 'panel-series-update',
			title: s("Déplacer les séries sur une autre saison"),
			dialogOpen: $form->openAjax('/series/series:doUpdateSeasonCollection', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h,
			footer: $form->submit(s("Modifier la saison"), ['data-waiter' => s("Modification en cours...")])
		);

	}

	public function closeCollection(\Collection $cSeries): \Panel {

		$form = new \util\FormUi();

		$h = ($cSeries->count() === 1) ?
			$this->closeOne($form, $cSeries->first()) :
			$this->closeLot($form, $cSeries);

		return new \Panel(
			id: 'panel-series-close',
			title: p("Clôturer la série", "Clôturer {value} séries", $cSeries->count()),
			dialogOpen: $form->openAjax('/series/series:doCloseCollection', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h,
			footer: $form->submit(p("Clôturer la série", "Clôturer les {value} séries", $cSeries->count()), ['data-waiter' => s("Clôture en cours...")])
		);

	}

	public function numberCollection(\Collection $cSeries, array $numbering): \Panel {

		\Asset::lib('util', 'Sortable-1.11.0.js');

		$form = new \util\FormUi();

		$h = '<table class="tbody-even series-number" onrender="Series.initNumber(this)">';

			$h .= '<thead><tr>';
				$h .= '<th class="td-min-content"></th>';
				$h .= '<th class="td-min-content">'.s("Semaine").'</th>';
				$h .= '<th class="hide-xs-down">'.s("Nom actuel").'</th>';
				$h .= '<th>'.s("Nouveau nom").'</th>';
			$h .= '</tr></thead>';

			foreach($numbering as $bareName => $cSeriesGroup) {

				$h .= '<tbody class="tbody-group">';
					$h .= '<tr class="tr-title"><td colspan="4">'.encode($bareName).' <span class="util-counter">'.$cSeriesGroup->count().'</span></td></tr>';
				$h .= '</tbody>';

				$h .= '<tbody class="tbody-group-sub" data-number-group>';

					$rank = 0;

					foreach($cSeriesGroup as $eSeries) {

						$rank++;

						$eCultivationFirst = $eSeries['eCultivationFirst'];

						$h .= '<tr class="tr-sub">';
							$h .= '<td class="td-min-content">'.$form->hidden('ids[]', $eSeries).\main\DesignUi::getDragHandle().'</td>';
							$h .= '<td class="td-min-content util-annotation">'.($eCultivationFirst->empty() ? s("Sans semaine") : s("s{value}", week_number($eCultivationFirst->getStartWeek($eSeries['season'])))).'</td>';
							$h .= '<td class="hide-xs-down">'.encode($eSeries['name']).'</td>';
							$h .= '<td data-wrapper="name['.$eSeries['id'].']">'.$form->text('name['.$eSeries['id'].']', $bareName.' '.$rank, ['data-bare' => $bareName]).'</td>';
						$h .= '</tr>';

					}

				$h .= '</tbody>';

			}

		$h .= '</table>';

		$footer = $form->submit(s("Numéroter ces {value} séries", $cSeries->count()), [
			'data-waiter' => s("Enregistrement en cours..."),
			'data-label-number' => s("Numéroter ces {value} séries", $cSeries->count()),
			'data-label-rename' => s("Renommer ces {value} séries", $cSeries->count()),
		]);

		$footer .= ' '.$form->button(s("Enlever la numérotation"), [
			'class' => 'btn btn-outline-primary',
			'data-action' => 'series-number-toggle',
			'data-label-remove' => s("Enlever la numérotation"),
			'data-label-restore' => s("Remettre la numérotation"),
		]);

		return new \Panel(
			id: 'panel-series-number',
			title: s("Numéroter {value} séries", $cSeries->count()),
			dialogOpen: $form->openAjax('/series/series:doNumberCollection', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h,
			footer: $footer
		);

	}

	protected function closeOne(\util\FormUi $form, Series $eSeries): string {

		$harvested = array_filter($eSeries['cCultivation']->makeArray(fn($eCultivation) => new CultivationUi()->getHarvestedByUnits($eCultivation)));

		$h = $form->hidden('ids[]', $eSeries);

		$h .= '<div class="util-title">';
			$h .= '<h3>'.self::name($eSeries);
				foreach($eSeries['cCultivation'] as $eCultivation) {
					$h .= ' '.\sequence\CropUi::start($eCultivation, \work\WorkSetting::$mainActions);
				}
			$h .= '</h3>';
		$h .= '</div>';

		if($harvested) {
			$h .= $form->group(s("Récolté"), implode('<br/>', $harvested));
		}

		$h .= $form->group(
			s("Comment s'est passée la série ?"),
			'<div class="series-close-outcome util-filter-options-field">'.$this->getOutcomeRadios($form, $eSeries).'</div>',
			['data-wrapper' => 'outcome['.$eSeries['id'].']']
		);

		$h .= $form->dynamicGroup($eSeries, 'comment['.$eSeries['id'].']', function(\PropertyDescriber $d) {
			$d->label = s("Commentaire sur la série");
			$d->placeholder = s("Ce qui a marché, ce qui a manqué");
		});

		return $h;

	}

	protected function getOutcomeRadios(\util\FormUi $form, Series $eSeries): string {

		$outcomes = [];

		foreach(self::p('outcome')->values as $outcome => $label) {
			$outcomes[$outcome] = '<span class="series-close-choice">'.self::getOutcomeIcon($outcome).'<small>'.$label.'</small></span>';
		}

		return $form->radios('outcome['.$eSeries['id'].']', $outcomes, NULL, [
			'placeholder' => '<span class="series-close-choice">'.\Asset::icon('dash-lg').'<small>'.s("Sans avis").'</small></span>',
		]);

	}

	protected function closeLot(\util\FormUi $form, \Collection $cSeries): string {

		$filled = 0;
		$harvested = [];

		foreach($cSeries as $eSeries) {
			$harvested[$eSeries['id']] = [];
			foreach($eSeries['cCultivation'] as $eCultivation) {
				$harvested[$eSeries['id']][] = new CultivationUi()->getHarvestedByUnits($eCultivation, $filled);
			}
			$harvested[$eSeries['id']] = array_filter($harvested[$eSeries['id']]);
		}

		$h = '<div class="util-table util-table-bordered series-close-lot'.($filled > 0 ? ' series-close-lot-harvested' : '').'">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Série").'</div>';
				$h .= '<div></div>';
				if($filled > 0) {
					$h .= '<div class="text-end">'.s("Récolté").'</div>';
				}
				$h .= '<div class="text-center series-close-lot-outcome">'.self::p('outcome')->label.'</div>';
			$h .= '</div>';

			foreach($cSeries as $eSeries) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						$h .= $form->hidden('ids[]', $eSeries);
						$h .= self::name($eSeries);
						foreach($eSeries['cCultivation'] as $eCultivation) {
							$h .= ' '.\sequence\CropUi::start($eCultivation, \work\WorkSetting::$mainActions);
						}
					$h .= '</div>';
					$h .= '<div class="series-close-lot-note">';
						$h .= '<button type="button" class="btn btn-sm btn-outline-primary" data-action="series-close-note">'.\Asset::icon('pencil').' '.s("Ajouter une note").'</button>';
					$h .= '</div>';
					if($filled > 0) {
						$h .= '<div class="text-end">'.implode('<br/>', $harvested[$eSeries['id']]).'</div>';
					}
					$h .= '<div class="series-close-lot-outcome series-close-outcome util-filter-options-field" data-wrapper="outcome['.$eSeries['id'].']">';
						$h .= $this->getOutcomeRadios($form, $eSeries);
					$h .= '</div>';
					$h .= '<div class="series-close-lot-comment hide">';
						$h .= $form->dynamicField($eSeries, 'comment['.$eSeries['id'].']', function(\PropertyDescriber $d) {
							$d->placeholder = s("Commentaire sur la série");
						});
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public function createSequence(Series $eSeries, \Collection $cCultivation, \Collection $cTaskMetadata): \Panel {

		$form = new \util\FormUi();

		$eSequence = new \sequence\Sequence([
			'name' => $eSeries['name']
		]);

		$h = '<div class="util-block-info">';
			$h .= '<p>'.s("Vous vous apprêtez à créer un itinéraire technique à partir de la série {name}.", ['name' => '<b>'.encode($eSeries['name']).'</b>']).'</p>';
		$h .= '</div>';

		$h .= $form->openAjax('/series/series:doCreateSequence');
			$h .= $form->hidden('id', $eSeries['id']);
			$h .= $form->dynamicGroup($eSequence, 'name');
			
			$cultivations = '';

			foreach($cCultivation as $eCultivation) {

				$ePlant = $eCultivation['plant'];

				$cultivations .= '<div class="crop-item mb-1">';
					$cultivations .= '<div class="crop-item-header">';

						$cultivations .= '<div class="crop-item-title">';
							$cultivations .= \plant\PlantUi::getVignette($ePlant, '3rem').' ';
							$cultivations .= '<h2>';
								$cultivations .= \plant\PlantUi::link($ePlant);
								$cultivations .= \sequence\CropUi::start($eCultivation, \work\WorkSetting::$mainActions, fontSize: '0.7em');
							$cultivations .= '</h2>';
						$cultivations .= '</div>';

						$cultivations .= new \sequence\CropUi()->getVarieties($eCultivation, $eCultivation['cSlice']);

					$cultivations .= '</div>';

					$cultivations .= '<div class="crop-item-presentation">';
						$cultivations .= new CultivationUi()->getPresentation($eSeries, $eCultivation, withYields: FALSE);
					$cultivations .= '</div>';

				$cultivations .= '</div>';

			}

			$h .= $form->group(
				p("Production", "Productions", $cCultivation->count()),
				$cultivations
			);

			if($cTaskMetadata->notEmpty()) {

				$cAction = $cTaskMetadata
					->getColumnCollection('action')
					->sort('name');

				$h .= $form->group(
					s("Interventions à conserver dans l'itinéraire technique").\util\FormUi::info(s("Les interventions de semis, plantation et récolte sont toujours conservées.")),
					$form->checkboxes('actions[]', $cAction, $cAction)
				);

			}

			$h .= $form->group(
				content: $form->submit(s("Créer un itinéraire technique"), ['data-waiter' => s("Création en cours"), 'data-confirm' => s("Vous allez créer un itinéraire technique à partir d'une série, voulez-vous continuer ?")])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-series-create-sequence',
			title: s("Créer un itinéraire technique à partir d'une série"),
			body: $h,
		);

	}

	public function duplicate(\structure\Structure $eStructure, \Collection $cSeries, \Collection $cTaskMetadata, bool $hasPlaces): \Panel {

		$form = new \util\FormUi();

		$eSeriesFirst = $cSeries->first();
		$eSeriesFirst['structure'] = $eStructure; // Pour avoir la première et la dernière saison de la ferme

		$h = '';

		if($cSeries->count() > 5) {
			$h = '<div class="util-info">';
				$h .= s("Vous allez agir sur un grand nombre de séries simultanément, soyez vigilant avant de valider votre modification !");
			$h .= '</div>';
		}

		$h .= $form->openAjax('/series/series:doDuplicate', ['id' => 'series-duplicate', 'data-season' => $eSeriesFirst['season']]);

			// Depuis le plan de culture, la duplication y rend la main : le plan se
			// recharge en ajax au lieu d'ouvrir la nouvelle série. L'origine vient du
			// lien qui a ouvert le panneau, jamais du Referer.
			$h .= $form->hidden('origin', GET('origin'));

			$input = '<div class="series-duplicate-copies" data-limit="'.SeriesSetting::DUPLICATE_LIMIT.'">';
				$input .= '<a onclick="Series.changeDuplicateCopies(this, -1)" class="series-duplicate-copies-minus series-duplicate-copies-disabled">'.\Asset::icon('dash-circle').'</a>';
				$input .= '<span class="series-duplicate-copies-label">'.s("{value}", '<span class="series-duplicate-copies-value">1</span>').'</span>';
				$input .= '<a onclick="Series.changeDuplicateCopies(this, 1)" class="series-duplicate-copies-plus">'.\Asset::icon('plus-circle').'</a>';
				$input .= $form->hidden('copies', 1);
			$input .= '</div>';

			$h .= $form->group(
				$cSeries->count() === 1 ? s("Nombre de copies") : s("Nombre de copies par série"),
				$input
			);

			$h .= $form->dynamicGroup($eSeriesFirst, 'season', function(\PropertyDescriber $d) use($eSeriesFirst) {
				$d->label = s("Dupliquer pour la saison");
				$d->attributes['onclick'] = 'Series.changeDuplicateSeason(this)';
				$d->after = \util\FormUi::info(s("Lorsque vous dupliquez une série sur une saison différente, les interventions <i>Fait</i> sont replacées en <i>À faire</i> et les récoltes sont remises à zéro."), class: 'series-duplicate-season hide');
			});

			$h .= '<br/>';

			$h .= '<div class="series-duplicate-list" data-interval="0" data-copies="1">';

				$title = '<div class="util-title">';
					$title .= '<h3>'.($cSeries->count() === 1 ? s("Série") : s("Séries")).'</h3>';
					$title .= '<a onclick="Series.toggleDuplicateInterval(this)" class="btn btn-outline-primary">'.\Asset::icon('chevron-expand').' '.s("Décaler les interventions").'</a>';
				$title .= '</div>';

				$h .= $form->group(content: $title);

				foreach($cSeries as $eSeries) {

					$name = str_contains($eSeries['name'], '@copy') ? $eSeries['name'] : s("{value} (@copy)", $eSeries['name']);

					$h .= '<div class="series-duplicate-one util-block" data-name="'.encode($name).'" data-series="'.$eSeries['id'].'">';
						$h .= $form->hidden('ids[]', $eSeries['id']);
						$h .= $this->getCopyField($form, $eSeries);
					$h .= '</div>';

				}

			$h .= '</div>';

			if($cTaskMetadata->notEmpty() or $hasPlaces) {
				$h .= $form->group(content: '<h3>'.s("Contenu").'</h3>');
			}

			$h .= '<div class="util-block">';

				if($cTaskMetadata->notEmpty()) {

					$cAction = $cTaskMetadata
						->getColumnCollection('action')
						->sort('name');

					$h .= $form->group(
						s("Dupliquer les interventions"),
						$form->checkboxes('copyActions[]', $cAction, $cAction->find(fn($eAction) => $eAction['fqn'] !== ACTION_RECOLTE), attributes: [
							'callbackCheckboxAttributes' => fn($eAction) => [
								'data-fqn' => $eAction['fqn'],
							],
							'callbackCheckboxContent' => function($eAction) {
								$action = encode($eAction['name']);
								if($eAction['fqn'] === ACTION_RECOLTE) {
									$action .= '<span class="color-secondary ml-1">'.\Asset::icon('exclamation-circle').' '.s("Les quantités récoltées seront également dupliquées").'</span>';
								}
								return $action;
							}
						])
					);

					if($cTaskMetadata
						->filter(fn($eTask) => $eTask['time'] > 0)
						->notEmpty()) {

						$h .= $form->group(
							s("Dupliquer le temps de travail"),
							$form->yesNo('copyTimesheet', FALSE)
						);

					}

				}

				if($hasPlaces) {

					$h .= $form->group(
						s("Dupliquer l'assolement"),
						$form->yesNo('copyPlaces', FALSE)
					);

				} else {
					$h .= $form->hidden('copyPlaces', FALSE);
				}

			$h .= '</div>';

			$h .= $form->group(
				content: $form->submit(s("Dupliquer"), ['data-waiter' => s("Duplication en cours..."), 'data-confirm' => p("Vous allez dupliquer une série, voulez-vous continuer ?", "Vous allez dupliquer {value} séries, assurez-vous d'avoir bien vérifié votre formulaire. En dupliquant un grand nombre de séries par erreur, vous risquez de perdre le fil de votre plan de culture. Lancer la duplication ?", $cSeries->count())])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-series-duplicate',
			title: $cSeries->count() === 1 ? s("Dupliquer une série") : s("Dupliquer des séries"),
			body: $h,
		);

	}

	protected function getCopyField(\util\FormUi $form, Series $eSeries, int $copy = 1): string {

		$field = '<div data-wrapper="series-'.$eSeries['id'].'-'.$copy.'" class="series-duplicate-copy">';

			$field .= '<h5>'.s("Copie n°{value}", '<span class="series-duplicate-copy-number">'.$copy.'</span>').'</h5>';

			$field .= '<div>';
				$field .= $form->inputGroup(
					$form->addon(s("Nom de la copie")).
					$form->text('name['.$eSeries['id'].']['.($copy - 1).']', str_replace('@copy', 1, $eSeries['name']), ['data-copy' => $copy])
				);
			$field .= '</div>';

			$field .= '<div class="series-duplicate-interval">';
				$field .= $form->inputGroup(
					$form->addon(s("Décaler les interventions de")).
					$form->number('taskInterval['.$eSeries['id'].']['.($copy - 1).']', attributes: SeriesSetting::DUPLICATE_INTERVAL).
					$form->addon(s("semaine(s)"))
				);
			$field .= '</div>';

		$field .= '</div>';

		return $form->group(
			SeriesUi::link($eSeries),
			$field
		);

	}

	protected function getSeriesField(\util\FormUi $form, \Collection $cSeries): string {

		if($cSeries->count() === 1) {

			$eSeries = $cSeries->first();

			$group = $form->hidden('ids[]', $eSeries['id']);
			$group .= SeriesUi::link($eSeries);

		} else {

			$group = '<ul>';

			foreach($cSeries as $eSeries) {

				$group .= '<li>';
					$group .= $form->hidden('ids[]', $eSeries['id']);
					$group .= SeriesUi::link($eSeries);
				$group .= '</li>';

			}

			$group .= '</ul>';

		}

		return $form->group(
			$cSeries->count() === 1 ? s("Série") : s("Séries"),
			$group
		);

	}

	protected function getSeasonField(\util\FormUi $form, Series $eSeries): string {

		$h = $form->hidden('season', $eSeries['season']);
		$h .= $form->group(
			self::p('season')->label,
			$form->fake($eSeries['season'])
		);

		return $h;

	}

	protected function getNameField(\util\FormUi $form, Series $eSeries): string {

		$eSeries->expects(['nameAuto', 'nameDefault']);

		return $form->group(
			self::p('name')->label,
			$form->inputGroup(
				$form->dynamicField($eSeries, 'name', function($d) use($eSeries) {
					$d->attributes['data-auto'] = 'true';
					$d->attributes['oninput'] = 'Series.changeNameAuto(this)';
				})
			),
			['data-wrapper' => 'name']
		);

	}

	public function deleteSeason(\structure\Structure $eStructure, int $season): string {

		$eStructure->expects(['createdAt']);

		if($eStructure->canManage() === FALSE) {
			return '';
		}

		if($eStructure->conf(\map\Configuration::class)['seasonFirst'] === $eStructure->conf(\map\Configuration::class)['seasonLast']) {
			return '';
		}

		// Il n'est possible de supprimer que la première ou la dernière saison
		if($eStructure->conf(\map\Configuration::class)['seasonFirst'] === $season) {
			$link = 'data-ajax="/map/configuration:doSeasonFirst" post-id="'.$eStructure->conf(\map\Configuration::class)['id'].'" post-increment="1"';
		} else if($eStructure->conf(\map\Configuration::class)['seasonLast'] === $season) {
			$link = 'data-ajax="/map/configuration:doSeasonLast" post-id="'.$eStructure->conf(\map\Configuration::class)['id'].'" post-increment="-1"';
		} else {
			return '';
		}

		$h = '<div class="util-block">';

			$h .= '<h4>'.s("Supprimer cette saison").'</h4>';

			$h .= '<p>'.s("Vous n'avez pas encore ajouté de série sur cette saison. Il est toujours possible de supprimer cette saison {value} si vous ne comptez pas travailler dessus pour le moment et de la recréer plus tard !", $season).'</p>';
			$h .= '<a '.$link.' class="btn btn-danger">'.s("Supprimer la saison {value}", $season).'</a>';

		$h .= '</div>';

		return $h;

	}

	public function getWorkingTime(Series $eSeries, \Collection $cCultivation, \Collection $ccTask, \Collection $ccTaskHarvested): string {

		if($ccTask->empty()) {
			return '';
		}

		$seriesTime = 0;

		$h = '<div id="series-timesheet-wrapper">';

			$h .= '<h3>'.s("Temps de travail").'</h3>';

			$h .= '<div class="series-timesheet">';

			foreach($ccTask as $cultivation => $cTask) {

				if($cultivation) {
					$eCultivation = $cCultivation[$cultivation];
				} else {
					$eCultivation = new Cultivation();
				}

				$cTaskHarvested = $ccTaskHarvested[$cultivation] ?? new \Collection();

				$cultivationTime = $cTask->sum('totalTime');
				$seriesTime += $cultivationTime;

				$h .= '<div class="series-timesheet-image">';
					if($cultivation) {
						$h .= \plant\PlantUi::getVignette($eCultivation['plant'], '3rem').' ';
					}
				$h .= '</div>';

				$h .= '<div class="series-timesheet-cultivation">';
					if($cultivation) {
						$ePlant = $eCultivation['plant'];
						$h .= '<h4>';
							$h .= encode($ePlant['name']);
						$h .= '</h4>';
					} else {
						$h .= '<h4>'.s("Partagé").'</h4>';
					}
				$h .= '</div>';

				$h .= '<div class="series-timesheet-total">';
					$h .= TaskUi::convertTime($cultivationTime);
				$h .= '</div>';

				$h .= '<div class="series-timesheet-content">';

					foreach($cTask as $eTask) {

						$eAction = $eTask['action'];

						$h .= $this->getWorkingTimeBox($eSeries, $eCultivation, $eAction, $eTask['totalTime'], $cTaskHarvested);

					}

				$h .= '</div>';

			}

			if($ccTask->count() > 1) {

				$h .= '<div class="series-timesheet-sum" style="grid-column: span 2">';
					$h .= '<span class="btn btn-readonly btn-outline-primary">'.TaskUi::convertTime($seriesTime).'</span>';
				$h .= '</div>';

				$h .= '<div>';
				$h .= '</div>';

			}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getWorkingTimeBox(Series $eSeries, Cultivation $eCultivation, \work\Action $eAction, float $time, \Collection $cTaskHarvested): string {

		$h = '<div class="series-item-working-time-task">';
			$h .= '<div class="series-item-working-time-task-content" style="background-color: '.$eAction['color'].'">';
				$h .= '<h5>'.encode($eAction['name']).'</h5>';
				$h .= '<span class="series-item-working-time-task-value">'.TaskUi::convertTime($time).'</span>';
			$h .= '</div>';

			if($eAction['pace'] and $time > 0) {

				$h .= '<div class="series-item-working-time-task-pace" style="color: '.$eAction['color'].'">';
					$h .= $this->getPace($eSeries['area'], $eCultivation->empty() ? NULL : $eCultivation['density'] * $eSeries['area'], $eAction, $time, $cTaskHarvested);
				$h .= '</div>';

			}
		$h .= '</div>';

		return $h;

	}

	public function getPace(?int $area, ?float $plants, \work\Action $eAction, float $time, \Collection $cTaskHarvested): string {

		if($area === NULL) {
			return '';
		}

		$pace = '';

		switch($eAction['pace']) {

			case \work\Action::BY_HARVEST :
				foreach($cTaskHarvested as $eTaskHarvested) {
					if($eTaskHarvested['totalTime'] > 0 and $eTaskHarvested['totalHarvested'] > 0) {
						$pace .= s("{value} / h", \product\UnitUi::getValue(round($eTaskHarvested['totalHarvested'] / $eTaskHarvested['totalTime'], 1), $eTaskHarvested['harvestUnit'], short: TRUE)).'<br/>';
					}
				}
				break;

			case \work\Action::BY_AREA :
				$pace .= s("{value} m² / h", round($area / $time));
				break;

			case \work\Action::BY_PLANT :
				$pace .= s("{value} plants / h", round($plants / $time));
				break;

		}

		return $pace;

	}

	public function getPhotos(Series $eSeries, \Collection $cPhoto): string {

		$h = '';

		$h .= '<div class="util-title">';
			$h .= '<h3 id="scroll-photos">'.s("Photos").'</h3>';
			if($eSeries->canWrite()) {
				$h .= '<div data-media="gallery" post-series="'.$eSeries['id'].'">';
					$h .= new \media\GalleryUi()->getDropdownLinks(
						\Asset::icon('plus-circle').' <span>'.s("Ajouter une photo").'</span>',
						'btn-outline-primary',
						uploadInputAttributes: ['multiple' => 'multiple']
					);
				$h .= '</div>';
			}
		$h .= '</div>';

		if($cPhoto->notEmpty()) {
			$h .= new \gallery\PhotoUi()->getList($cPhoto, NULL, 4);
		}

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Series::model()->describer($property, [
			'season' => s("Saison"),
			'sequence' => s("Itinéraire technique"),
			'name' => s("Nom de la série"),
			'use' => s("Utilisation du sol"),
			'mode' => s("Mode de culture"),
			'cycle' => s("Cycle de culture"),
			'areaTarget' => s("Objectif de surface"),
			'lengthTarget' => s("Objectif de surface"),
			'bedWidth' => s("Largeur travaillée de planche"),
			'alleyWidth' => s("Largeur de passe-pieds"),
			'outcome' => s("Dénouement"),
		]);

		switch($property) {

			case 'season' :
				$d->field = function(\util\FormUi $form, Series $e, string $property, \PropertyDescriber $d) {

					return $form->rangeSelect('season', $e['structure']->conf(\map\Configuration::class)['seasonLast'], $e['structure']->conf(\map\Configuration::class)['seasonFirst'], -1, $e['season'], ['required' => TRUE] + $d->attributes);

				};
				break;

			case 'use' :
				$d->values = [
					Series::BED => s("Culture sur planches"),
					Series::BLOCK => s("Culture sur surface libre"),
				];
				$d->attributes = [
					'columns' => 2,
					'onchange' => 'Series.updateArea(this)'
				];
				$d->required = TRUE;
				break;

			case 'mode' :
				$d->values = [
					Series::GREENHOUSE => s("Sous abri"),
					Series::OPEN_FIELD => s("Plein champ"),
					Series::MIX => s("Mixte"),
				];
				$d->attributes = [
					'columns' => 3,
				];
				$d->required = TRUE;
				break;

			case 'cycle' :
				$d->values = [
					Series::ANNUAL => s("Culture annuelle"),
					Series::PERENNIAL => s("Culture pérenne"),
				];
				$d->attributes = [
					'data-action' => 'series-cycle-change',
					'columns' => 2,
				];
				$d->required = TRUE;
				break;

			case 'perennialLifetime' :
				$d->groupLabel = FALSE;
				$d->prepend = s("Durée de vie de la culture").'&nbsp;&nbsp;'.\Asset::icon('arrow-right');
				$d->append = s("saison(s)");
				$d->after = '<small>'.s("Vous pouvez laisser vide si la durée de vie n'est pas connue à ce jour.").'</small>';
				$d->group = function(Series $e) {

					$e->expects(['cycle']);

					return [
						'id' => 'series-write-perennial-lifetime',
						'style' => ($e['cycle'] === \sequence\Sequence::PERENNIAL) ? '' : 'display: none'
					];

				};
				break;

			case 'lengthTarget' :
				$d->append = s("mL de planches");
				$d->attributes['oninput'] = 'Series.updateArea(this)';
				break;

			case 'bedWidth' :
				$d->append = s("cm");
				$d->attributes['oninput'] = 'Series.updateArea(this)';
				break;

			case 'alleyWidth' :
				$d->labelAfter = \structure\StructureUi::getAlleyWarning();
				$d->append = s("cm");
				$d->attributes['oninput'] = 'Series.updateArea(this)';
				break;

			case 'areaTarget' :
				$d->append = s("m²");
				$d->attributes['oninput'] = 'Series.updateArea(this)';
				break;

			case 'outcome' :
				$d->values = [
					Series::SUCCESS => s("Réussie"),
					Series::MIXED => s("Mitigée"),
					Series::FAILURE => s("Ratée"),
					Series::CANCELED => s("Annulée"),
				];
				break;
		}

		return $d;

	}

}
?>
