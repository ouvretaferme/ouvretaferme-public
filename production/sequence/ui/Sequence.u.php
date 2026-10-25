<?php
namespace sequence;

class SequenceUi {

	public function __construct() {
		\Asset::css('sequence', 'sequence.css');
		\Asset::js('sequence', 'sequence.js');
	}

	public static function link(Sequence $eSequence, bool $newTab = FALSE): string {

		$eSequence->expects(['id', 'name', 'status']);
		return '<a href="'.self::url($eSequence).'" '.($newTab ? 'target="_blank"' : '').' class="plant-link">'.self::name($eSequence).'</a>';

	}

	public function getDuplicateName(Sequence $eSequence): string {
		return $eSequence['name'].' '.s("(copie)");
	}

	public static function name(Sequence $eSequence): string {

		$eSequence->expects(['id', 'name', 'mode']);

		$name = encode($eSequence['name']);

		if($eSequence['mode'] === Sequence::GREENHOUSE) {
			$name .= \Asset::icon('greenhouse', ['style' => 'margin-left: 0.5rem']);
		} else if($eSequence['mode'] === Sequence::MIX) {
			$name .= \Asset::icon('mix', ['style' => 'margin-left: 0.5rem']);
		}

		return $name;

	}

	public static function url(Sequence $eSequence): string {

		$eSequence->expects(['id']);

		return '/itineraire/'.$eSequence['id'];

	}

	public function getSearch(\structure\Structure $eStructure, \Search $search, bool $emptySearch): string {

		$h = '<div id="sequence-search" class="util-block-search '.($emptySearch ? 'hide' : '').'">';

			$form = new \util\FormUi();
			$url = \structure\StructureUi::urlCultivationSequences($eStructure);

			$h .= $form->openAjax($url, ['method' => 'get', 'class' => 'util-search']);

				$h .= '<fieldset>';
					$h .= '<legend>'.s("Nom").'</legend>';
					$h .= $form->text('name', $search->get('name'), ['placeholder' => s("Nom de l'itinéraire")]);
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Espèce").'</legend>';
					$h .= $form->dynamicField(new \series\Cultivation(['structure' => $eStructure]), 'plant', function($d) use($search) {
						$d->autocompleteDefault = $search->get('plant');
						$d->attributes = [
							'data-autocomplete-select' => 'submit',
							'style' => 'width: 20rem'
						];
					});
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Utilisation du sol").'</legend>';
					$h .= $form->select('use', SequenceUi::p('use')->values, $search->get('use'));
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Matériel").'</legend>';
					$h .= $form->dynamicField(new \work\Tool(['structure' => $eStructure]), 'id', function($d) use($search) {

						$d->name = 'tool';

						if($search->get('tool')->notEmpty()) {
							$d->autocompleteDefault = $search->get('tool');
						}

						$d->attributes = [
							'data-autocomplete-select' => 'submit',
							'style' => 'width: 20rem'
						];
					});
				$h .= '</fieldset>';

				$h .= '<div class="util-search-submit">';
					$h .= $form->submit(s("Chercher"));
					$h .= '<a href="'.$url.'" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';
				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	public function getTabs(\structure\Structure $eStructure, \Search $search, array $sequences): string {

		if($sequences[Sequence::CLOSED] === 0) {
			return '';
		}

		$tabs = '<a href="'.\structure\StructureUi::urlCultivationSequences($eStructure).'" class="tab-item '.($search->get('status') === Sequence::ACTIVE ? 'selected' : '').'"><span><span class="tab-item-name">'.s("Itinéraires actifs").'</span> <span class="util-counter util-counter-solid">'.$sequences[Sequence::ACTIVE].'</span></span></a>';
		$tabs .= '<a href="'.\structure\StructureUi::urlCultivationSequences($eStructure).'/'.Sequence::CLOSED.'" class="tab-item '.($search->get('status') === Sequence::CLOSED ? 'selected' : '').'"><span><span class="tab-item-name">'.s("Itinéraires archivés").'</span> <span class="util-counter util-counter-solid">'.$sequences[Sequence::CLOSED].'</span></span></a>';

		return \main\DesignUi::getPageTabs($tabs, fold: TRUE);

	}

	public function getListByPlants(\structure\Structure $eStructure, \Collection $ccCrop, \Collection $cActionMain, ?\Search $search = NULL) {

		if($ccCrop->empty()) {
			return '<div class="util-block util-block-empty">'.s("Il n'y a aucun itinéraire technique à afficher...").'</div>';
		}

		return $this->getPlan($eStructure, $ccCrop, $cActionMain, $search);

	}

	// Un itinéraire est une série relative — année n, n-1, n+1. Il se lit dans le
	// même diagramme que le plan de culture, posé sur l'année en cours : même
	// grille hebdomadaire, mêmes pastilles, même campagne de récolte, mêmes
	// repères au survol. Le markup et le CSS viennent de series ; seule la
	// géométrie des colonnes est propre à cette page.
	public function getPlan(\structure\Structure $eStructure, \Collection $ccCrop, \Collection $cActionMain, ?\Search $search = NULL): string {

		\Asset::css('series', 'series.css');
		\Asset::js('series', 'series.js');

		$eConfiguration = $eStructure->conf(\map\Configuration::class);
		$referenceYear = \Clock::year();
		$active = ($search?->get('status', Sequence::ACTIVE) === Sequence::ACTIVE);

		$uiCultivation = new \series\CultivationUi();
		$uiPlace = new \series\PlaceUi();

		[$startTs, $stopTs] = $uiPlace->getBounds($eConfiguration, $referenceYear);
		$weeks = $uiPlace->getWeeksInBounds($eConfiguration, $referenceYear);
		$startWeekTs = strtotime(week_date_starts($weeks[0]));
		$gridDays = (int)round(($stopTs - $startTs) / 86400);

		$h = '<script type="text/javascript">Series.labels = '.json_encode([
			'week' => s("s"),
			'weeksShort' => s("sem."),
			'withFollowing' => s("avec les suivantes"),
			'alone' => s("seule"),
		]).';</script>';

		$h .= '<div id="series-wrapper" class="series-item-wrapper series-item-planning-wrapper sequence-plan util-overflow-md stick-md" data-start="'.$startWeekTs.'" data-duration="'.($stopTs - $startTs).'" data-offset="'.($startWeekTs - $startTs).'" data-days="'.$gridDays.'" onrender="Series.plugRuler()">';

			$h .= '<div class="series-item-header series-item-planning">';
				$h .= '<div class="util-table-header">'.s("Itinéraire").'</div>';
				$h .= '<div class="util-table-header text-end">'.s("Rendement<br/>par m²").'</div>';
				$h .= '<div class="util-table-header series-item-planning-timeline">';
					$h .= $uiCultivation->getListSeason($eConfiguration, $referenceYear, hasWeeks: TRUE);
				$h .= '</div>';
				$h .= '<div class="util-table-header"></div>';
			$h .= '</div>';

			$h .= '<div class="series-item-body">';

				$h .= $uiCultivation->getListGrid($eConfiguration, $referenceYear, hasWeeks: TRUE);

				foreach($ccCrop as $cCrop) {

					$ePlant = $cCrop->first()['plant'];

					$h .= '<div class="series-item series-item-planning series-item-title">';
						$h .= '<div class="series-item-title-plant">';
							$h .= \plant\PlantUi::getVignette($ePlant, '2rem');
							$h .= '<span class="series-item-title-plant-name">'.encode($ePlant['name']).'</span>';
						$h .= '</div>';
						$h .= '<div class="series-item-planning-summary"></div>';
						$h .= '<div class="series-item-planning-timeline"></div>';
						$h .= '<div></div>';
					$h .= '</div>';

					foreach($cCrop as $eCrop) {

						$eSequence = $eCrop['sequence'];

						$h .= '<div class="series-item series-item-planning">';

							$h .= '<div class="series-item-planning-details">';
								$h .= '<a href="'.self::url($eSequence).'" class="series-item-planning-details-link">';
									$h .= '<span class="series-item-planning-details-title">'.encode($eSequence['name']).'</span>';
									$h .= '<span class="series-item-planning-details-info">';
										$h .= match($eSequence['use']) {
											Sequence::BED => s("Planche de {bedWidth} cm", $eSequence),
											Sequence::BLOCK => s("Bloc", $eSequence),
										};
										if($eSequence['cycle'] === Sequence::PERENNIAL) {
											$h .= ' · '.s("pérenne");
										}
									$h .= '</span>';
								$h .= '</a>';
							$h .= '</div>';

							$h .= '<div class="series-item-planning-summary">';
								$h .= $eCrop['yieldExpected'] ? $eCrop->format('yieldExpected', ['short' => TRUE]) : '';
							$h .= '</div>';

							$h .= '<div class="series-item-planning-timeline">';
								$h .= $this->getPlanTimeline($eCrop, $cActionMain, $referenceYear, $startTs, $stopTs, $uiPlace);
							$h .= '</div>';

							$h .= '<div class="sequence-plan-actions">';
								if($active) {
									$h .= $this->createSeries($eStructure, $eSequence, 'btn-sm btn-secondary', TRUE);
								}
							$h .= '</div>';

						$h .= '</div>';

					}

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	// La piste d'un itinéraire : les étapes semaine par semaine, la campagne de
	// récolte en période, un ruban qui les enveloppe. Une étape hors de l'année n
	// (n-1, n+1) se pose à sa semaine dans l'année de référence, en pointillé,
	// avec son année dans l'infobulle : 4 % des étapes seulement.
	protected function getPlanTimeline(Crop $eCrop, \Collection $cActionMain, int $referenceYear, int $startTs, int $stopTs, \series\PlaceUi $uiPlace): string {

		// Les mêmes glissés que sur le plan : une étape se déplace, une période se
		// déplace en bloc — l'endpoint flow:doIncrementWeek fait déjà les deux, avec
		// le passage d'une année à l'autre et le refus au-delà de ±26 semaines.
		$movable = $eCrop['sequence']->canWrite();

		// Une étape en n-1 ou n+1 sort de l'axe et reste à sa vraie place : la piste
		// la rogne, le ruban part du bord, et l'infobulle comme les repères du
		// survol disent sa semaine. La rabattre sur le bord la faisait lire à la
		// semaine 1, en contradiction avec le « 28 sem. » du survol.
		$weekTs = fn(int $week, ?int $year) => strtotime(($referenceYear + ($year ?? 0)).'-W'.sprintf('%02d', $week));

		$years = [-1 => s("n-1"), 0 => '', 1 => s("n+1")];

		$items = [];
		$minTs = NULL;
		$maxTs = NULL;

		foreach($eCrop['cFlow'] as $cFlow) {

			foreach($cFlow as $eFlow) {

				$eAction = $eFlow['action'];
				$isHarvest = ($eAction['fqn'] === ACTION_RECOLTE);

				$season = '';
				if($eFlow['seasonOnly'] !== NULL) {
					$season = ' · '.s("saison {value}", $eFlow['seasonOnly']);
				} else if($eFlow['seasonStart'] !== NULL or $eFlow['seasonStop'] !== NULL) {
					$season = ' · '.s("saisons {from} à {to}", ['from' => $eFlow['seasonStart'] ?? 1, 'to' => $eFlow['seasonStop'] ?? '…']);
				}

				if($eFlow['weekOnly'] !== NULL) {

					$year = $eFlow['yearOnly'] ?? 0;
					$ts = $weekTs($eFlow['weekOnly'], $year);
					$trueWeek = ($referenceYear + $year).'-W'.sprintf('%02d', $eFlow['weekOnly']);

					$title = s("S{week}{year} - {action}", ['week' => $eFlow['weekOnly'], 'year' => $year ? ' '.$years[$year] : '', 'action' => encode($eAction['name'])]).$season;

					$css = 'color: '.$eAction['color'].'; border-color: '.$eAction['color'].';'.$uiPlace->getPositionStyle($startTs, $stopTs, $ts, $ts, width: '1.2rem');

					// Une récolte d'une seule semaine s'étire et devient une période. Un
					// semis ou une plantation, non : ils se font un jour donné.
					$item = '<div class="util-timeline-event series-item-timeline-event series-item-timeline-event-expected'.($year ? ' sequence-plan-event-shifted' : '').($movable ? ' series-item-timeline-event-movable' : '').'" '.($movable ? 'data-flow="'.$eFlow['id'].'" ' : '').'data-week="'.$trueWeek.'" data-first="'.$ts.'" data-last="'.$ts.'" style="'.$css.'" title="'.$title.'">';
						if($movable and $isHarvest) {
							$item .= '<div class="series-item-timeline-bound series-item-timeline-bound-first" title="'.encode(s("Glisser pour étendre la récolte vers la gauche")).'"></div>';
						}
						$item .= \work\ActionUi::getShort($eAction);
						if($movable and $isHarvest) {
							$item .= '<div class="series-item-timeline-bound series-item-timeline-bound-last" title="'.encode(s("Glisser pour étendre la récolte vers la droite")).'"></div>';
						}
					$item .= '</div>';

					$items[] = [$ts, $item];
					$minTs = ($minTs === NULL) ? $ts : min($minTs, $ts);
					$maxTs = ($maxTs === NULL) ? $ts : max($maxTs, $ts);

				} else if($eFlow['weekStart'] !== NULL and $eFlow['weekStop'] !== NULL) {

					$first = $weekTs($eFlow['weekStart'], $eFlow['yearStart'] ?? 0);
					$last = $weekTs($eFlow['weekStop'], $eFlow['yearStop'] ?? 0);

					$title = s("S{from} à S{to} - {action}", ['from' => $eFlow['weekStart'], 'to' => $eFlow['weekStop'], 'action' => encode($eAction['name'])]).$season;

					if($isHarvest) {
						$class = 'series-item-timeline-event-expected series-item-timeline-event-period';
						$css = 'color: var(--harvest); border-color: var(--harvest);';
					} else {
						$class = 'series-item-timeline-event-expected sequence-plan-event-span';
						$css = 'color: '.$eAction['color'].'; border-color: '.$eAction['color'].';';
					}

					$css .= $uiPlace->getPositionStyle($startTs, $stopTs, $first, $last + 7 * 86400);

					$item = '<div class="util-timeline-event series-item-timeline-event '.$class.'" '.($movable ? 'data-flow="'.$eFlow['id'].'" ' : '').'data-first="'.$first.'" data-last="'.$last.'" style="'.$css.'" title="'.$title.'">';
						if($movable and $isHarvest) {
							$item .= '<div class="series-item-timeline-bound series-item-timeline-bound-first" title="'.encode(s("Glisser pour avancer ou retarder le début de la récolte")).'"></div>';
						}
						$item .= '<span>'.\work\ActionUi::getShort($eAction).'</span>';
						if($movable and $isHarvest) {
							$item .= '<div class="series-item-timeline-bound series-item-timeline-bound-last" title="'.encode(s("Glisser pour allonger ou raccourcir la récolte")).'"></div>';
						}
					$item .= '</div>';

					$items[] = [$first, $item];
					$minTs = ($minTs === NULL) ? $first : min($minTs, $first);
					$maxTs = ($maxTs === NULL) ? $last + 7 * 86400 : max($maxTs, $last + 7 * 86400);

				}

			}

		}

		if($items === []) {
			return '';
		}

		usort($items, fn($a, $b) => $a[0] <=> $b[0]);

		$h = '<div class="series-timeline-season series-item-timeline series-item-timeline-full">';

			if($minTs !== $maxTs) {

				// Le ruban se saisit pour décaler l'itinéraire entier, comme celui d'une
				// série sur le plan de culture.
				$h .= '<div class="util-timeline-event util-timeline-event-frame series-item-timeline-event series-item-timeline-event-full'.($movable ? ' series-item-timeline-event-shiftable' : '').'"'.($movable ? ' data-sequence="'.$eCrop['sequence']['id'].'"' : '').' data-first="'.$minTs.'" data-last="'.$maxTs.'" style="'.$uiPlace->getPositionStyle($startTs, $stopTs, $minTs, $maxTs).'"></div>';

			}

			foreach($items as [, $item]) {
				$h .= $item;
			}

		$h .= '</div>';

		return $h;

	}

	protected function createSeries(\structure\Structure $eStructure, Sequence $eSequence, string $btn, bool $short = FALSE): string {

		$h = '';

		if($eStructure->canManage()) {

			$seasons = $eStructure->conf(\map\Configuration::class)->getSeasons();

			$label = $short ?
				s("Créer une série") :
				\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Créer une série").'</span>';

			if(count($seasons) === 1) {

				$season = first($seasons);

				$h .= '<a href="/series/series:createFromSequence?structure='.$eStructure['id'].'&season='.$season.'&sequence='.$eSequence['id'].'" class="btn '.$btn.'">';
					$h .= $label;
				$h .= '</a>';


			} else {

				$h .= '<a class="dropdown-toggle btn '.$btn.'" data-dropdown="bottom-end">';
					if($short) {
						$h .= '<span class="hide-sm-up">'.\Asset::icon('plus-circle').'</span>';
						$h .= '<span class="hide-xs-down">'.s("Créer une série").'</span>';
					} else {
						$h .= $label;
					}
				$h .= '</a>';
				$h .= '<div class="dropdown-list">';
					$h .= '<div class="dropdown-title hide-sm-up">'.s("Créer une série").'</div>';

					foreach($eStructure->conf(\map\Configuration::class)->getSeasons() as $season) {

						$h .= '<a href="/series/series:createFromSequence?structure='.$eStructure['id'].'&season='.$season.'&sequence='.$eSequence['id'].'" class="dropdown-item">';
							$h .= s("Saison {value}", $season);
						$h .= '</a>';

					}

				$h .= '</div>';

			}
		}

		return $h;

	}

	public static function getPanelHeader(Sequence $eSequence): string {

		$eSequence->expects(['name']);

		$h = '<div class="panel-header-subtitle">';
			$h .= '<span class="hide-xs-down">'.s("Itinéraire technique").'</span> ';
			$h .= self::link($eSequence).'</div>';
		$h .= '</div>';

		return $h;

	}

	public function getTextualWeek(Flow $eFlow, \work\Action $eAction): string {

		$year = self::getTextualYear();

		$h = '<div class="sequence-item-period">';

			if($eFlow['seasonOnly'] !== NULL) {
				$h .= '<span class="sequence-item-season">'.s("en saison {seasonOnly}", $eFlow).'</span>';
			} else if($eFlow['seasonStart'] !== NULL and $eFlow['seasonStop'] !== NULL) {
				$h .= '<span class="sequence-item-season">'.s("en saison {seasonStart} à {seasonStop}", $eFlow).'</span>';
			} else if($eFlow['seasonStart'] !== NULL) {
				$h .= '<span class="sequence-item-season">'.s("dès saison {seasonStart}", $eFlow).'</span>';
			} else if($eFlow['seasonStop'] !== NULL) {
				$h .= '<span class="sequence-item-season">'.s("jusqu'à saison {seasonStop}", $eFlow).'</span>';
			}
	

			if($eFlow['weekOnly'] !== NULL) {
				if($eFlow['yearOnly'] === 0 or $eFlow['yearOnly'] === NULL) {
					$label = s("{week}", ['week' => $eFlow['weekOnly']]);
				} else {
					$label = s("{week} {year}", ['week' => $eFlow['weekOnly'], 'year' => $year[$eFlow['yearOnly']]]);
				}
				$title = $this->getIntervalWeek($eFlow['weekOnly']);
			} else if($eFlow['weekStart'] !== NULL) {

				if($eFlow['yearStart'] === $eFlow['yearStop']) {

					$week = s("{weekStart} à {weekStop}", $eFlow);

					if($eFlow['yearStart'] === 0 or $eFlow['yearStart'] === NULL) {
						$label = $week;
					} else {
						$label = s("{week} {year}", ['week' => $week, 'year' => $year[$eFlow['yearStart']]]);
					}

				} else {

					if($eFlow['yearStart'] === 0 or $eFlow['yearStart'] === NULL) {
						$from = s("{week}", ['week' => $eFlow['weekStart']]);
					} else {
						$from = s("{week} {year}", ['week' => $eFlow['weekStart'], 'year' => $year[$eFlow['yearStart']]]);
					}

					if($eFlow['yearStop'] === 0 or $eFlow['yearStop'] === NULL) {
						$to = s("{week}", ['week' => $eFlow['weekStop']]);
					} else {
						$to = s("{week} {year}", ['week' => $eFlow['weekStop'], 'year' => $year[$eFlow['yearStop']]]);
					}

					$label = s("{from} à {to}", ['from' => $from, 'to' => $to]);

				}

				$title = s("{from} au {to}", ['from' => $this->getBeginWeek($eFlow['weekStart']), 'to' => $this->getEndWeek($eFlow['weekStop'])]);

			}

	
			$h .= '<span class="plant-start plant-start-background" style="background-color: '.$eAction['color'].'; color: white" title="'.$title.'">';
				$h .= $label;
			$h .= '</span>';

		$h .= '</div>';

		return $h;

	}

	public static function getTextualYear(): array {

		return [
			-1 => '<span class="sequence-item-season" title="'.s("Année précédente").'">'.s("n-1").'</span>',
			1 => '<span class="sequence-item-season" title="'.s("Année suivante").'">'.s("n+1").'</span>'
		];

	}

	protected function getIntervalWeek(string $week): string {
		return \util\DateUi::weekToDays(\Clock::year().'-W'.sprintf('%02d', $week), withYear: FALSE);
	}

	protected function getBeginWeek(string $week): string {
		$from = date('Y-m-d', \Clock::shift(\Clock::year().'-W'.sprintf('%02d', $week)));
		return \util\DateUi::textual($from, \util\DateUi::DAY_MONTH);
	}

	protected function getEndWeek(string $week): string {
		$from = date('Y-m-d', \Clock::shift(\Clock::year().'-W'.sprintf('%02d', $week).' + 6 DAYS'));
		return \util\DateUi::textual($from, \util\DateUi::DAY_MONTH);
	}

	public function query(\PropertyDescriber $d) {

		$d->prepend = \Asset::icon('list-task');
		$d->field = 'autocomplete';

		$d->placeholder = s("Tapez un nom d'itinéraire technique...");

		$d->autocompleteUrl = '/sequence/sequence:query';
		$d->autocompleteResults = function(Sequence $e) {
			return [
				'value' => $e['id'],
				'itemText' => $e['name'],
				'itemHtml' => $e['name']
			];
		};

	}

	public static function getAutocomplete(\Collection $ccCrop, \Collection $cActionMain = new \Collection()): array {

		\Asset::css('media', 'media.css');

		$results = [];

		foreach($ccCrop as $cCrop) {

			$ePlant = $cCrop->first()['plant'];

			$item = \plant\PlantUi::getVignette($ePlant, '1.5rem');
			$item .= '<div>'.encode($ePlant['name']).'</div>';

			$results[] = [
				'type' => 'title',
				'itemHtml' => $item
			];

			foreach($cCrop as $eCrop) {

				$eSequence = $eCrop['sequence'];

				$label = '<div>';
					$label .= self::name($eSequence).'<br/>';
					$label .= '<small class="color-muted">';
						$label .= implode(' / ', $eCrop['cCrop']->makeArray(fn($eCrop) => $eCrop['plant']['name'].' '.CropUi::start($eCrop, $cActionMain)));
					$label .= '</small>';
				$label .= '</div>';

				$results[] = [
					'value' => $eSequence['id'],
					'itemText' => $eSequence['name'],
					'itemHtml' => $label
				];

			}

		}


		return $results;

	}

	public function getHeader(\structure\Structure $eStructure, Sequence $eSequence, \Collection $cFlow): string {

		$create = '';
		$settings = '';

		if($eSequence->canWrite()) {

			if($cFlow->count() > 0) {
				$create = $this->createSeries($eStructure, $eSequence, 'btn-secondary');
			}

			$settings .= '<a data-dropdown="bottom-end" class="btn btn-color-primary dropdown-toggle">'.\Asset::icon('gear-fill').'</a>';
			$settings .= '<div class="dropdown-list">';
				$settings .= '<div class="dropdown-title">'.encode($eSequence['name']).'</div>';
				$settings .= '<a href="/sequence/sequence:update?id='.$eSequence['id'].'" class="dropdown-item">'.s("Modifier l'itinéraire").'</a>';
				if($eSequence['comment'] === NULL) {
					$settings .= '<a href="/sequence/sequence:updateComment?id='.$eSequence['id'].'" class="dropdown-item">'.s("Ajouter un commentaire").'</a>';
				}
				$settings .= '<a href="/sequence/crop:create?sequence='.$eSequence['id'].'" class="dropdown-item">'.s("Ajouter une autre production").'</a>';
				$settings .= '<div class="dropdown-divider"></div>';
				$settings .= '<a data-ajax="/sequence/sequence:doDuplicate" post-id="'.$eSequence['id'].'" data-confirm="'.s("Voulez-vous vraiment dupliquer tel quel cet itinéraire technique ?").'" class="dropdown-item">'.s("Dupliquer l'itinéraire").'</a>';
				$settings .= '<div class="dropdown-divider"></div>';

				$settings .= match($eSequence['status']) {

					Sequence::ACTIVE => '<a data-ajax="/sequence/sequence:doUpdateStatus" post-id="'.$eSequence['id'].'" post-status="'.Sequence::CLOSED.'" class="dropdown-item">'.s("Archiver l'itinéraire").'</a>',
					Sequence::CLOSED => '<a data-ajax="/sequence/sequence:doUpdateStatus" post-id="'.$eSequence['id'].'" post-status="'.Sequence::ACTIVE.'" class="dropdown-item">'.s("Désarchiver l'itinéraire").'</a>'

				};

				$settings .= '<a data-ajax="/sequence/sequence:doDelete" post-id="'.$eSequence['id'].'" data-confirm="'.s("Voulez-vous réellement supprimer cet itinéraire technique ?").'" class="dropdown-item">'.s("Supprimer l'itinéraire").'</a>';
			$settings .= '</div>';

		}

		$infos = [];

		if($eSequence['cycle'] === Sequence::PERENNIAL) {
			if($eSequence['perennialLifetime'] !== NULL) {
				$infos[] = s("Culture pérenne programmée pour {value} saisons", $eSequence['perennialLifetime']);
			} else {
				$infos[] = s("Culture pérenne");
			}
		} else {
			$infos[] = s("Culture annuelle");
		}

		switch($eSequence['use']) {

			case Sequence::BLOCK :
				$infos[] = s("Culture sur surface libre");
				break;

			case Sequence::BED :
				if($eSequence['alleyWidth'] !== NULL) {
					$infos[] = $eSequence->quick('bedWidth', s("Culture sur planche de {bed} cm et passe-pieds de {alley} cm", ['bed' => $eSequence['bedWidth'], 'alley' => $eSequence['alleyWidth']]));
				} else {
					$infos[] = $eSequence->quick('bedWidth', s("Culture sur planche de {bed} cm", ['bed' => $eSequence['bedWidth']]));
				}
				break;

		}

		$context = '<div class="badge badge-solid bg-secondary" style="margin-right: 0.5rem">'.s("Itinéraire technique").'</div>';
		$context .= implode('&nbsp;· ', $infos);

		$urlSequences = match($eSequence['status']) {
			Sequence::ACTIVE => \structure\StructureUi::urlCultivationSequences($eStructure),
			Sequence::CLOSED => \structure\StructureUi::urlCultivationSequences($eStructure).'/'.Sequence::CLOSED,
		};

		return \main\DesignUi::getPageHeader(
			$eSequence->quick('name', SequenceUi::name($eSequence)),
			context: $context,
			back: $urlSequences,
			create: $create,
			settings: $settings
		);

	}

	public function getDetails(Sequence $eSequence): string {

		$h = '';

		if($eSequence['status'] === Sequence::CLOSED) {

			$h .= '<div class="util-block mt-1">';

				$h .= \Asset::icon('lock-fill');
				$h .= ' '.s("Cet itinéraire technique est archivé, il ne peut plus être utilisé sur de nouvelles séries.");

			$h .= '</div>';

		}

		if($eSequence['description'] !== NULL) {

			$h .= '<div class="sequence-header-description util-block">';

				$description = new \editor\EditorUi()->value($eSequence['description']);

				$h .= '<span class="sequence-header-description-icon">'.\Asset::icon('chat-right-text').'</span>';
				$h .= '<span>'.$description.'</span>';

			$h .= '</div>';

		}

		return $h;

	}

	public function getPhotos(Sequence $eSequence, \Collection $cPhoto): string {

		if($cPhoto->empty() and $eSequence->canWrite() === FALSE) {
			return '';
		}

		$h = '';

		$h .= '<h3 id="scroll-photos">'.s("Photos").'</h3>';

		if($eSequence->canWrite()) {

			$h .= '<p data-media="gallery" post-sequence="'.$eSequence['id'].'">';
				$h .= \Asset::icon('plus-circle').' '.new \media\GalleryUi()->getBothLinks();
			$h .= '</p>';

		}

		if($cPhoto->notEmpty()) {
			$h .= new \gallery\PhotoUi()->getList($cPhoto, NULL, 4);
		}

		return $h;

	}

	public function getSeries(Sequence $eSequence, \Collection $ccSeries): string {

		if($eSequence->canWrite() === FALSE or $ccSeries->empty()) {
			return '';
		}

		$template = 'grid-template-columns: 50px minmax(200px, 1fr) 75px'.str_repeat(' 75px 100px', $eSequence['cCrop']->count());

		$h = '';

		$h .= '<h3>'.s("Séries créées").'</h3>';

		$h .= '<div class="sequence-series-wrapper sequence-series-wrapper-'.$eSequence['cCrop']->count().'">';

			$h .= '<div class="sequence-series-header">';

				$h .= '<div class="sequence-series-item" style="'.$template.'">';

					$h .= '<div></div>';
					$h .= '<div></div>';
					$h .= '<div></div>';

					foreach($eSequence['cCrop'] as $eCrop) {

						$h .= '<div class="util-table-header text-center sequence-series-item-crop" style="grid-column: span 2">';
							$h .= \plant\PlantUi::getVignette($eCrop['plant'], '2rem').' ';
							$h .= encode($eCrop['plant']['name']);
						$h .= '</div>';

					}

				$h .= '</div>';

				$h .= '<div class="sequence-series-item" style="'.$template.'">';

					$h .= '<div class="util-table-header">'.s("Saison").'</div>';
					$h .= '<div class="util-table-header">'.s("Série").'</div>';
					$h .= '<div class="util-table-header">'.s("Surface").'</div>';

					foreach($eSequence['cCrop'] as $eCrop) {

						$h .= '<div class="util-table-header text-end">';
							$h .= s("Récolte");
						$h .= '</div>';

						$h .= '<div class="util-table-header text-end sequence-series-item-crop">';
							$h .= s("Rendement");
						$h .= '</div>';

					}

				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="sequence-series-body">';

			foreach($ccSeries as $season => $cSeries) {

				foreach($cSeries as $eSeries) {

					$h .= '<div class="sequence-series-item" style="'.$template.'">';

						$h .= '<div class="sequence-series-item-season">';
							$h .= $season;
						$h .= '</div>';

						$h .= \series\SeriesUi::link($eSeries);

						$h .= '<div>';
							$h .= s("{area} m²", ['area' => $eSeries['area'] ?: '?']);
						$h .= '</div>';

						foreach($eSequence['cCrop'] as $eCrop) {

							if($eSeries['ccCultivation']->offsetExists($eCrop['plant']['id'])) {

								$cCultivation = $eSeries['ccCultivation'][$eCrop['plant']['id']];

								$h .= '<div class="text-end">';
									foreach($cCultivation as $eCultivation) {
										$h .= new \series\CultivationUi()->getHarvestedByUnits($eCultivation);
									}
								$h .= '</div>';

								$h .= '<div class="text-end sequence-series-item-crop">';
									foreach($cCultivation as $eCultivation) {
										$h .= '<span class="annotation">'.new \series\CultivationUi()->getYieldByUnits($eSeries, $eCultivation).'</span><br/>';
									}
								$h .= '</div>';

							} else {

								$h .= '<div class="text-end">';
									$h .= '-';
								$h .= '</div>';

								$h .= '<div class="text-end sequence-series-item-crop">';
									$h .= '-';
								$h .= '</div>';

							}

						}

					$h .= '</div>';

				}

			}

			$h .= '</div>';

		$h .= '</div>';


		return $h;

	}

	public function getComment(Sequence $eSequence): string {

		if($eSequence['comment'] === NULL) {
			return '';
		}

		$h = '<div class="util-note">';

			$h .= '<span class="util-note-pin">'.\Asset::icon('pin-angle-fill').'</span>';

			$h .= '<div class="util-title">';
				$h .= '<h3>'.s("Commentaire").'</h3>';
				$h .= '<a href="/sequence/sequence:updateComment?id='.$eSequence['id'].'" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('pencil-fill').'</a>';
			$h .= '</div>';

			$h .= new \editor\EditorUi()->value($eSequence['comment']);

		$h .= '</div>';

		return $h;

	}

	public function create(Sequence $eSequence): \Panel {

		$eSequence->expects(['structure']);

		$form = new \util\FormUi();

		$h = '';

		$h .= '<div class="util-block-info">';
			$h .= '<h4>'.s("Un itinéraire technique est un modèle").'</h4>';
			$h .= '<p>'.s("Il liste les interventions d'une culture, de la préparation à la récolte. Chaque série créée avec cet itinéraire reçoit une copie de ses interventions, placées à partir de la semaine de démarrage de la série.").'</p>';
		$h .= '</div>';

		$h .= $form->openAjax('/sequence/sequence:doCreate', ['id' => 'sequence-create', 'autocomplete' => 'off']);

			$h .= $form->asteriskInfo();

			$h .= $form->hidden('structure', $eSequence['structure']['id']);

			$h .= $form->dynamicGroup($eSequence, 'name*');

			$h .= $form->dynamicGroups($eSequence, ['plantsList*', 'cycle', 'perennialLifetime', 'description', 'use*', 'bedWidth*', 'alleyWidth']);

			$h .= $form->group(
				content: $form->submit(s("Créer l'itinéraire technique"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-sequence-create',
			title: s("Créer un itinéraire technique"),
			body: $h
		);

	}

	public function update(Sequence $eSequence): \Panel {

		$form = new \util\FormUi();

		$h = '';

		$h .= $form->openAjax('/sequence/sequence:doUpdate', ['id' => 'sequence-update', 'autocomplete' => 'off']);

			$h .= $form->hidden('id', $eSequence['id']);

			$h .= $form->dynamicGroup($eSequence, 'name');

			$h .= $form->group(
				self::p('cycle')->label,
				$form->fake(self::p('cycle')->values[$eSequence['cycle']])
			);

			if($eSequence['cycle'] === Sequence::PERENNIAL) {
				$h .= $form->dynamicGroup($eSequence, 'perennialLifetime');
			}

			$h .= $form->dynamicGroups($eSequence, ['description', 'use', 'bedWidth', 'alleyWidth']);

			$h .= $form->group(
				'',
				'<h4>'.s("Informations optionnelles et complémentaires").'</h4>'
			);

			$h .= $form->dynamicGroups(
				$eSequence,
				['mode']
			);

			$h .= $form->group(
				s("Visibilité"),
				$form->radio('visibility', Sequence::PRIVATE, s("<b>Privé</b> - uniquement accessible pour les utilisateurs de la ferme {structure}", ['structure' => \structure\StructureUi::link($eSequence['structure'])]), $eSequence['visibility']).
				$form->radio('visibility', Sequence::PUBLIC, s("<b>Partagé</b> - accessible librement sur {siteName}"), $eSequence['visibility'])
			);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-sequence-update',
			title: s("Modifier un itinéraire technique"),
			body: $h
		);

	}

	public function updateComment(Sequence $eSequence): \Panel {

		$form = new \util\FormUi();

		$h = '';


		$h .= $form->openAjax('/sequence/sequence:doUpdateComment');

			$h .= $form->hidden('id', $eSequence['id']);
			$h .= $form->dynamicField($eSequence, 'comment');
			$h .= '<div class="util-action mt-1">';
				$h .= $form->submit(s("Enregistrer"));
				if($eSequence['comment'] !== NULL) {
					$h .= '<a data-ajax="/sequence/sequence:doUpdateComment" post-id="'.$eSequence['id'].'" post-comment="" data-confirm="'.s("Supprimer le commentaire ?").'" class="btn btn-outline-danger">'.s("Supprimer").'</a>';
				}
			$h .= '</div>';

		$h .= $form->close();

		return new \Panel(
			id: 'panel-sequence-update',
			title: s("Commentaire de l'itinéraire"),
			body: $h,
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Sequence::model()->describer($property, [
			'name' => s("Nom de l'itinéraire technique"),
			'description' => s("Description de l'itinéraire technique"),
			'author' => s("Auteur"),
			'structure' => s("Structure"),
			'plantsList' => s("Espèces"),
			'duplicateOf' => s("Crée à partir de"),
			'use' => s("Utilisation du sol"),
			'bedWidth' => s("Largeur de planche"),
			'alleyWidth' => s("Largeur de passe-pieds entre les planches"),
			'mode' => s("Mode de culture"),
			'visibility' => s("Visibilité"),
			'cycle' => s("Cycle de culture"),
			'createdAt' => s("Créé le"),
		]);

		switch($property) {

			case 'name' :
				$d->attributes = [
					'placeholder' => s("Ex. : Épinard de printemps")
				];
				break;

			case 'cycle' :
				$d->values = [
					Sequence::ANNUAL => s("Culture annuelle"),
					Sequence::PERENNIAL => s("Culture pérenne"),
				];
				$d->attributes = [
					'data-action' => 'sequence-cycle-change',
					'columns' => 2,
				];
				$d->required = TRUE;
				break;

			case 'perennialLifetime' :
				$d->groupLabel = FALSE;
				$d->prepend = s("Durée de vie de la culture").'&nbsp;&nbsp;'.\Asset::icon('arrow-right');
				$d->append = s("saison(s)");
				$d->after = '<small>'.s("Vous pouvez laisser vide si la durée de vie n'est pas connue à ce jour.").'</small>';
				$d->group = function(Sequence $e) {

					$e->expects(['cycle']);

					return [
						'id' => 'sequence-write-perennial-lifetime',
						'style' => ($e['cycle'] === \sequence\Sequence::PERENNIAL) ? '' : 'display: none'
					];

				};
				break;

			case 'use' :
				$d->values = [
					Sequence::BED => s("Culture sur planches"),
					Sequence::BLOCK => s("Culture sur surface libre"),
				];
				$d->attributes = [
					'data-action' => 'sequence-use-change',
					'columns' => 2,
				];
				$d->required = TRUE;
				break;

			case 'bedWidth' :
				$d->append = s("cm");
				$d->group = function(Sequence $e) {

					$use = $e['use'] ?? NULL;

					return [
						'style' => ($use === Sequence::BED) ? '' : 'display: none'
					];

				};
				break;

			case 'alleyWidth' :
				$d->append = s("cm");
				$d->labelAfter = \structure\StructureUi::getAlleyWarning();
				$d->group = function(Sequence $e) {

					$use = $e['use'] ?? NULL;

					return [
						'style' => ($use === Sequence::BED) ? '' : 'display: none'
					];

				};
				break;

			case 'mode' :
				$d->values = [
					Sequence::GREENHOUSE => s("Sous abri"),
					Sequence::OPEN_FIELD => s("Plein champ"),
					Sequence::MIX => s("Mixte"),
				];
				$d->attributes = [
					'columns' => 3,
				];
				$d->required = TRUE;
				break;

			case 'plantsList' :
				$d->after = \util\FormUi::info(s("Il est possible d'intégrer plusieurs espèces pour réaliser des associations de culture."));
				$d->autocompleteBody = function(\util\FormUi $form, Sequence $e) {
					$e->expects(['structure']);
					return [
						'structure' => $e['structure']['id'],
						'new' => TRUE
					];
				};
				new \plant\PlantUi()->query($d, TRUE);
				$d->wrapper = 'plantsList';
				break;

			case 'visibility' :
				$d->values = [
					Sequence::PRIVATE => s("Privé"),
					Sequence::PUBLIC => s("Public"),
				];
				break;

		}

		return $d;

	}

}
?>
