<?php
namespace map;

class BedUi {

	public function __construct() {

		\Asset::css('map', 'bed.css');
		\Asset::js('map', 'bed.js');

	}

	public function displayBedsFromPlot(\structure\Structure $eStructure, Plot $ePlot, int $season, \series\Series|\series\Task $ePlaced, bool $print, bool $withForm = FALSE, ?array $target = NULL): string {

		$h = '';

		foreach($ePlot['cBed'] as $eBed) {
			$h .= $this->displayBedFromPlot($eStructure, $ePlot, $eBed, $season, $ePlaced, $print, $withForm, $target);
		}

		return $h;

	}

	public function displayBedFromPlot(\structure\Structure $eStructure, Plot $ePlot, Bed $eBed, int $season, \series\Series|\series\Task $ePlaced, bool $print, bool $withForm = FALSE, ?array $target = NULL): string {

		$form = new \util\FormUi();

		$h = '';


		$place = $this->displayPlaceBySeason($eStructure, $eBed, $eBed['cPlace'], $season, $ePlaced, $print, $this->getBedPose($eBed, $ePlaced, $target));

		$hidden = self::isBedHidden($eBed, $ePlaced, $place);

		if($eBed['zoneFill']) {
			$class = 'bed-item-fill bed-item-fill-zone';
		} else if($eBed['plotFill']) {
			$class = 'bed-item-fill bed-item-fill-plot';
		} else {
			$class = '';
		}

		if($ePlaced->notEmpty()) {
			$ePlace = $ePlaced['cPlace'][$eBed['id']] ?? new \series\Place();
		} else {
			$ePlace = new \series\Place();
		}

		$emptyFill = ($eBed['plotFill'] and $place === '');

		$h .= '<div id="'.self::getBedLineId($eBed).'" class="bed-item-grid bed-item-grid-plan '.$class.' '.($hidden ? 'hide' : '').'" '.($emptyFill ? 'data-empty-fill="1" ' : '').$this->getTest($eBed).'>';

			if($eBed['plotFill']) {

				$h .= '<div class="bed-item-bed">';

					$h .= '<div class="bed-item-content">';

						// Le seul libellé qu'une ligne repliée par un filtre garde visible
						$h .= '<div class="bed-item-name bed-item-name-fill"><b>'.s("Surface libre").'</b></div>';

						if($withForm and $ePlaced->notEmpty()) {

							$h .= '<div class="bed-write">';

								switch($ePlaced['use']) {

									case \series\Series::BED :

										$h .= '<b>'.s("Planche temporaire").'</b>';
										$h .= '<div class="bed-item-size bed-item-size-fill">';
											$h .= $form->inputGroup(
												$form->number('sizes['.$eBed['id'].']', $ePlace->notEmpty() ? $ePlace['length'] : $eBed['length'], [
													'min' => 0,
													'max' => $eBed['length'],
													'onfocus' => 'this.select()',
													'oninput' => 'SeriesPlace.selectBed(this)'
												]).
												$form->addon(s("mL"))
											);
										$h .= '</div>';
										break;

									case \series\Series::BLOCK :

										$h .= s("Surface libre");
										$h .= '<div class="bed-item-size bed-item-size-fill">';
											$h .= $form->inputGroup(
												$form->number('sizes['.$eBed['id'].']', $ePlace->notEmpty() ? $ePlace['area'] : $eBed['area'], [
													'min' => 0,
													'max' => $eBed['area'],
													'onfocus' => 'this.select()',
													'oninput' => 'SeriesPlace.selectBed(this)'
												]).
												$form->addon(s("m²"))
											);
										$h .= '</div>';
										break;

								}

							$h .= '</div>';
							$h .= '<div class="bed-read">'.s("Surface libre").'</div>';

						} else {
							$h .= '<div>'.s("Surface libre").'</div>';
						}

					$h .= '</div>';
				$h .= '</div>';

			} else {

				$h .= '<div class="bed-item-bed">';

					if($withForm and $ePlaced->notEmpty()) {
						$h .= '<label class="bed-item-select bed-write">'.$form->inputCheckbox('beds[]', $eBed['id'], ['checked' => $ePlace->notEmpty()]).'</label>';
					}

					$name = '<b>'.encode($eBed['name']).'</b>';
					if(
						$eBed['plotFill'] === FALSE and
						$eBed['zoneFill'] === FALSE and
						$ePlot['mode'] === Plot::GREENHOUSE
					) {
						$name .= '  '.\Asset::icon('greenhouse');
					}

					$h .= '<div class="bed-item-content">';
						$h .= '<div class="bed-item-name">';

							$h .= '<div>';
								if($print) {
									$h .= $name;
								} else {
									$h .= '<a class="place-bed-panel" data-bed="'.$eBed['id'].'" data-season="'.$season.'">'.$name.' '.\Asset::icon('chevron-down').'</a>';
								}
							$h .= '</div>';

							if(($eBed['test']['rotation'] ?? NULL) > 0) {
								$h .= '<div class="bed-item-rotation" title="'.s("Rotation sur la même famille").'">';
									$h .= \Asset::icon('arrow-clockwise').' '.p("{value} an", "{value} ans", $eBed['test']['rotation']);
								$h .= '</div>';
							}

						$h .= '</div>';

						if(
							$withForm and
							$ePlaced->notEmpty() and
							$ePlaced instanceof \series\Series
						) {

							if(
								$ePlaced['use'] === \series\Series::BED and
								$ePlaced['bedWidth'] !== NULL and
								$ePlaced['bedWidth'] !== $eBed['width']
							) {
								$width = '<span class="color-danger" style="font-weight: bold">'.\Asset::icon('exclamation-circle').' '.s("{width} cm", $eBed).'</span>';
							} else {
								$width = s("{width} cm", $eBed);
							}

							$h .= '<div class="bed-item-size bed-write">';
								$h .= '<div class="bed-item-size-write">';
									$h .= $form->inputGroup(
										$form->number('sizes['.$eBed['id'].']', $ePlace->notEmpty() ? min($ePlace['length'], $eBed['length']) : $eBed['length'], [
											'min' => 0,
											'max' => $eBed['length'],
											'onfocus' => 'this.select()'
										]).
										$form->addon(s("mL x {width}", ['width' => $width]))
									);
								$h .= '</div>';
								$h .= '<div class="bed-item-size-read">';
									$h .= s("{length} mL x {width}", ['length' => $eBed['length'], 'width' => $width]);
								$h .= '</div>';
							$h .= '</div>';

						}

						$h .= '<div class="bed-item-size '.($withForm ? 'bed-read' : '').'">';
							$h .= '<span title="'.s("{area} m²", $eBed).'">'.s("{length} m × {width}", $eBed).'</span>';
						$h .= '</div>';


					$h .= '</div>';
				$h .= '</div>';

			}

			$h .= $place;

			if($ePlaced->empty() and $print === FALSE) {
				$noDate = new \series\PlaceNoDateAiUi();
				$h .= $noDate->getBlock($noDate->getByBed($eBed, $season), $eBed);
			}



		$h .= '</div>';


		return $h;

	}

	public static function isBedHidden(Bed $eBed, \series\Series|\series\Task $ePlaced, string $place): bool {

		if($ePlaced->empty()) {
			return ($eBed['plotFill'] and $place === '');
		}

		if($eBed['plotFill'] === FALSE and $ePlaced['use'] === \series\Series::BLOCK) {
			return TRUE;
		}

		return ($ePlaced instanceof \series\Task and $eBed['plotFill']);

	}

	public static function getBedLineId(Bed $eBed): string {
		return 'bed-line-'.$eBed['id'];
	}

	protected function getBedPose(Bed $eBed, \series\Series|\series\Task $ePlaced, ?array $target): string {

		$pose = self::getBedPoseParts($eBed, $ePlaced, $target);

		if($pose === NULL) {
			return '';
		}

		return '<a class="place-pose place-pose-timeline'.($pose['faint'] ? ' place-pose-faint' : '').'" data-bed="'.$eBed['id'].'"'.$pose['confirm'].' style="left: '.$pose['left'].'%">'.$pose['label'].'</a>';

	}

	public static function getBedPoseParts(Bed $eBed, \series\Series|\series\Task $ePlaced, ?array $target): ?array {

		if(
			$target === NULL or
			$ePlaced->empty() or
			($ePlaced instanceof \series\Series and $ePlaced['status'] === \series\Series::CLOSED)
		) {
			return NULL;
		}

		foreach($eBed['cPlace'] as $ePlace) {

			if(\series\PlaceUi::isPlaceOf($ePlace, $ePlaced)) {
				return NULL;
			}

		}

		// Toujours « + Poser » : ce que la planche porte déjà ne change rien au
		// geste, et annoncer un reste laissait croire que le bouton en dépendait.
		return [
			'label' => s("+ Ajouter"),
			'left' => round($target[0], 2),
			'faint' => FALSE,
			'confirm' => \series\PlaceGardenAiUi::getWidthConfirm($eBed, ($ePlaced instanceof \series\Series) ? $ePlaced : NULL),
		];

	}

	protected function getBedTarget(Bed $eBed): string {

		if($eBed['plotFill'] or $eBed['zoneFill']) {
			return 'data-bed="'.$eBed['id'].'" data-length="0" data-fill="1" data-area="'.($eBed['area'] ?? 0).'"';
		}

		return 'data-bed="'.$eBed['id'].'" data-length="'.($eBed['length'] ?? 0).'"';

	}

	public function getBedTest(Bed $eBed): string {
		return $this->getTest($eBed);
	}

	protected function getTest(Bed $eBed): string {

		$h = $this->getBedTarget($eBed);

		foreach(self::getTestAttributes($eBed) as $name => $value) {
			$h .= ' '.$name.'="'.$value.'"';
		}

		return $h;

	}

	public static function getTestAttributes(Bed $eBed): array {

		if(isset($eBed['test']) === FALSE) {
			return [];
		}

		if($eBed['plotFill'] or $eBed['zoneFill']) {
			return [
				'data-greenhouse' => $eBed['test']['hasGreenhouse'] ? '1' : '0',
			];
		}

		return [
			'data-same-width' => $eBed['test']['sameWidth'] ? '1' : '0',
			'data-greenhouse' => $eBed['test']['hasGreenhouse'] ? '1' : '0',
			'data-rotation' => (string)$eBed['test']['rotation'],
			'data-free' => (string)$eBed['test']['free'],
			'data-rest' => (string)($eBed['test']['rest'] ?? ''),
			'data-reason' => (string)($eBed['test']['reason'] ?? ''),
			'data-hide' => $eBed['test']['hide'] ? '1' : '0',
			'data-mismatch' => ($eBed['test']['mismatch'] ?? FALSE) ? '1' : '0',
		];

	}

	protected function displayPlaceBySeason(\structure\Structure $eStructure, Bed $eBed, \Collection $cPlace, int $season, \series\Series|\series\Task $ePlaceholder, bool $print, string $pose = ''): string {

		$timeline = new \series\PlaceUi()->getTimeline($eStructure, $eBed, $cPlace, $season, $ePlaceholder, $print);

		if($timeline !== '' or $pose !== '') {
			return '<div class="bed-item-places">'.$timeline.$pose.'</div>';
		} else {
			return '';
		}

	}

	public function getRotations(\structure\Structure $eStructure, \Collection $cBed, int $season): string {

		$h = '';

		foreach($cBed as $eBed) {

			$isFill = ($eBed['plotFill'] or $eBed['zoneFill']);

			if($isFill and $eBed['cPlace']->empty()) {
				continue;
			}

			$h .= '<div class="bed-item-grid bed-item-grid-rotation-'.$eStructure->conf(Configuration::class)['rotationYears'].' '.($isFill ? 'bed-item-rotation-fill' : '').'">';

				$h .= '<div class="bed-item-bed">';

					$h .= '<div class="bed-item-content">';
						$h .= '<div class="bed-item-name">';
							$h .= '<b>'.($isFill ? s("Surface libre") : encode($eBed['name'])).'</b>';
						$h .= '</div>';

					$h .= '</div>';
				$h .= '</div>';

				$h .= new BedUi()->displayPlaceByHistory($eBed['cPlace'], $eBed['familyReturns'], $season, $eStructure);

			$h .= '</div>';

		}

		return $h;

	}

	const ROTATION_FAMILIES_MAX = 3;

	public function displayPlaceByHistory(\Collection $cPlace, array $returns, int $season, \structure\Structure $eStructure): string {

		$cCultivationBySeason = new \Collection();
		$familiesBySeason = [];

		foreach($cPlace as $ePlace) {

			foreach($ePlace['series']['cCultivation'] as $eCultivation) {

				$cultivationSeason = $eCultivation['season'];
				$cCultivationBySeason[$cultivationSeason][] = $eCultivation;

				if($eCultivation['plant']['family']->empty()) {
					$familiesBySeason[$cultivationSeason][0] = $eCultivation['plant']['family'];
				} else {
					$familiesBySeason[$cultivationSeason][$eCultivation['plant']['family']['id']] = $eCultivation['plant']['family'];
				}

			}

		}

		$h = '';

		$years = $eStructure->conf(Configuration::class)['rotationYears'];

		for($i = $season; $i > $season - $years; $i--) {

			$return = $returns[$i] ?? NULL;
			$gap = $return['gap'] ?? NULL;
			$familiesReturn = $return['families'] ?? [];

			if($gap === NULL) {
				$class = '';
			} else if($gap === 1) {
				$class = ' bed-item-cultivation-return bed-item-cultivation-return-close';
			} else {
				$class = ' bed-item-cultivation-return bed-item-cultivation-return-near';
			}

			$h .= '<div class="bed-item-cultivation '.($i === $season ? 'ml-1' : '').$class.'">';

			if(empty($cCultivationBySeason[$i])) {
				$h .= '<span class="bed-item-cultivation-empty">–</span>';
			} else {

				$h .= '<div class="bed-item-cultivation-family">';

					$families = array_values($familiesBySeason[$i]);
					$hidden = array_slice($families, self::ROTATION_FAMILIES_MAX);

					foreach(array_slice($families, 0, self::ROTATION_FAMILIES_MAX) as $eFamily) {

						$isReturn = ($eFamily->notEmpty() and isset($familiesReturn[$eFamily['id']]));

						$h .= '<div class="'.($isReturn ? 'bed-item-cultivation-family-return' : '').'">'.\plant\FamilyUi::getLetterVignette($eFamily).'</div>';

					}

					if($hidden !== []) {

						$names = implode(' · ', array_map(
							fn(\plant\Family $eFamily) => $eFamily->empty() ? s("non renseignée") : $eFamily['name'],
							$hidden
						));

						$hasReturn = FALSE;

						foreach($hidden as $eFamilyHidden) {
							if($eFamilyHidden->notEmpty() and isset($familiesReturn[$eFamilyHidden['id']])) {
								$hasReturn = TRUE;
							}
						}

						$h .= '<div class="'.($hasReturn ? 'bed-item-cultivation-family-return' : '').'"><span class="family-vignette-letter family-vignette-more" title="'.encode($names).'">+'.count($hidden).'</span></div>';

					}

					if($gap !== NULL) {
						$h .= '<span class="bed-item-cultivation-gap">'.p("{value} an", "{value} ans", $gap).'</span>';
					}

				$h .= '</div>';

				$h .= '<div class="bed-item-cultivation-plant" title="'.encode(implode(' · ', array_map(fn(\series\Cultivation $e) => $e['plant']['name'], $cCultivationBySeason[$i]))).'">';

					$h .= implode(' · ', array_map(function(\series\Cultivation $e) {

						$plant = '<a href="'.\series\SeriesUi::url($e['series']).'">'.encode($e['plant']['name']).'</a>';

						return $plant;

					}, $cCultivationBySeason[$i]));

				$h .= '</div>';

			}

			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Des planches à ajouter à un jardin, ouvertes depuis le Plan ou depuis la Carte (`$origin`). Sur un jardin qui a sa forme, la
	 * carte du jardin suit les dimensions : le rang neuf s'y pose après le dernier trait, se redessine à chaque saisie (MapBed,
	 * MapDrawingLine) et part avec le formulaire, sous les noms des planches qu'elle dessine. Sans forme, ou quand on choisit de les
	 * dessiner plus tard, les planches se créent seules et restent à dessiner.
	 */
	public function createCollection(\structure\Structure $eStructure, int $season, Zone $eZone, Plot $ePlot, ?string $origin): \Panel {

		$ePlot->expects(['numbering', 'lineNext']);

		$drawn = $ePlot->canBedLine();
		$numbering = $ePlot['numbering'];

		$h = '';

		if($eStructure->conf(Configuration::class)['defaultBedWidth'] === NULL) {
			$h .= '<div class="util-block-info">';
				$h .= '<p>'.s("Vous pouvez gagner du temps en indiquant les longueur et largeur de planche par défaut sur votre ferme.").'</p>';
				$h .= '<a href="/map/configuration:updateCrop?id='.$eStructure->conf(Configuration::class)['id'].'" class="btn btn-secondary" target="_blank">'.s("Configurer les dimensions").'</a>';
			$h .= '</div>';
		}

		$form = new \util\FormUi();

		$h .= $form->openAjax('/map/bed:doCreate', ['id' => 'bed-create', 'autocomplete' => 'off']);

			$h .= $form->hidden('plot', $ePlot);
			$h .= $form->hidden('season', $season);
			$h .= $form->hidden('origin', $origin);

			$h .= $form->group(
				s("Emplacement"),
				'<b>'.($ePlot['zoneFill'] ? s("Parcelle {value}", encode($eZone['name'])) : encode($ePlot['name'])).'</b>'
			);

			$h .= $form->group(
				s("Nombre de planches à ajouter"),
				$form->number('number', attributes: ['id' => 'bed-create-number', 'min' => 1, 'max' => MapSetting::$onboardingBedsMax]),
				['data-wrapper' => 'names']
			);

			$h .= $form->group(
				s("Dimensions"),
				$this->getSizeField($form, new Bed(), $eStructure->conf(Configuration::class)['defaultBedLength'], $eStructure->conf(Configuration::class)['defaultBedWidth'], $eStructure->conf(Configuration::class)['defaultAlleyWidth']),
				['data-wrapper' => 'width length alleyWidth']
			);

			$customize = '<div id="bed-create-customize-label">';
				$customize .= '<a onclick="MapBed.startCustomizeNumbering()" class="btn btn-outline-primary">'.\Asset::icon('plus').' '.s("Personnaliser la numérotation").'</a>';
			$customize .= '</div>';
			$customize .= '<div id="bed-create-customize" class="util-block bg-background hide">';
				$customize .= '<div class="bed-create-fill">';
					$customize .= $form->inputGroup($form->addon(s("Préfixe")).$form->text(value: $numbering['prefix'], attributes: ['id' => 'bed-create-prefix', 'placeholder' => s("XXX-")]), ['class' => 'input-group-sm']);
					$customize .= $form->inputGroup($form->addon(s("Début de numérotation")).$form->number(value: $numbering['start'], attributes: ['id' => 'bed-create-start', 'onfocus' => 'this.select()']), ['class' => 'input-group-sm']);
					$customize .= $form->inputGroup($form->addon(s("Suffixe")).$form->text(value: $numbering['suffix'], attributes: ['id' => 'bed-create-suffix', 'placeholder' => s("-XXX")]), ['class' => 'input-group-sm']);
					$customize .= '<div>';
						$customize .= $form->button(s("Appliquer"), ['id' => 'bed-create-auto']);
					$customize .= '</div>';
				$customize .= '</div>';
			$customize .= '</div>';
			// Un nom par planche, que MapBed.changeNumber() ajoute ou retire à mesure que le nombre change
			$customize .= '<div id="bed-create-names" data-prefix="'.encode($numbering['prefix']).'" data-start="'.$numbering['start'].'" data-suffix="'.encode($numbering['suffix']).'" data-pad="'.$numbering['pad'].'" data-input="'.encode('<div class="bed-create-one"><h5>'.s("Planche").'</h5>'.$form->text('name[]', '', ['placeholder' => s("Donnez un nom")]).'</div>').'"></div>';

			$h .= $form->group(
				s("Numérotation"),
				$customize
			);

			if($drawn) {

				$contour = json_encode($ePlot['zoneFill'] ? $eZone['coordinates'] : $ePlot['coordinates']);

				// Le rang lit les planches dans le formulaire (MapDrawingLine.getCreated()), à défaut de cases cochées. L'étiquette du
				// jardin quitte la carte le temps du rang, dont elle cacherait les noms. La cote d'un côté et celle de l'allée voisine du
				// bord qu'on tire se lisent sur le lien
				$drawing = 'data-drawing-create data-drawing-id="'.$ePlot['id'].'" data-drawing-mask="plotLabels" data-drawing-coordinates="null"';
				$drawing .= ' '.attr('data-drawing-contour', $contour);
				$drawing .= ' '.attr('data-drawing-last', json_encode($ePlot['lineNext']));
				$drawing .= ' data-length="'.s("{value} m", '{value}').'" data-alley="'.s("{value} cm", '{value}').'"';

				// Sans tracé, les planches se créent à dessiner, comme sur un jardin sans forme (MapDrawingEmbedded.changeLater())
				$map = new MapUi()->getDrawingEmbedded($form, 'bed-create', $season, $eZone, $ePlot, 'line', 'draw', $drawing, [
					'flip' => ['symmetry-horizontal', s("Inverser")]
				]);

				$h .= new MapUi()->getDrawingGroup($form, $map, 'draw', s("Dessiner maintenant les planches"));

			}

			$h .= $form->group(
				content: $form->submit(s("Ajouter des planches"), ['id' => 'bed-create-submit', 'disabled' => TRUE, 'data-one' => s("Ajouter la planche"), 'data-many' => s("Ajouter {value} planches", '{value}')])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-bed-create',
			title: s("Ajouter des planches"),
			body: $h
		);

	}

	public function update(int $season, Bed $eBed, \Collection $cZone): \Panel {

		MapboxUi::loadCartography();

		$form = new \util\FormUi();

		$h = $form->openAjax('/map/bed:doUpdate', ['id' => 'bed-update', 'autocomplete' => 'off', 'data-ajax-origin' => \Route::getRequestedOrigin()]);

			$h .= $form->hidden('id', $eBed);
			$h .= $form->hidden('season', $season);

				$eBed['cZone'] = $cZone;
				$h .= $form->dynamicGroup($eBed, 'plot');

				$h .= $form->dynamicGroup($eBed, 'name');

				$h .= $form->group(
					s("Dimensions de la planche"),
					$this->getSizeField($form, $eBed),
					['data-wrapper' => 'width length alleyWidth']
				);

				$h .= $form->group(
					s("Exploitée"),
					new SeasonUi()->getField($form, $eBed),
					['data-wrapper' => 'seasonFirst seasonLast']
				);

				$h .= $form->group(
					content: $form->submit(s("Enregistrer"))
				);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-bed-update',
			title: s("Modifier une planche"),
			body: $h
		);

	}

	public function updateSizeCollection(int $season, Plot $ePlot, \Collection $cBed, Bed $eBedSize, ?string $origin): \Panel {

		MapboxUi::loadCartography();

		$form = new \util\FormUi();

		$h = $form->openAjax('/map/bed:doUpdateSizeCollection', ['autocomplete' => 'off']);

			$h .= $form->hidden('plot', $ePlot);
			$h .= $form->hidden('season', $season);
			$h .= $form->hidden('origin', $origin);

			$h .= $this->updateCollection($form, $ePlot, $cBed);

			// Un champ se déclare modifié quand on l'écarte de sa valeur d'ouverture (bed.js) : seul le serveur distingue alors un
			// passe-pied vidé, qui se retire, d'un passe-pied « Plusieurs valeurs » laissé vide
			foreach(['length', 'width', 'alleyWidth'] as $property) {
				$h .= $form->hidden('changed['.$property.']', 0);
			}

			$h .= $form->group(
				s("Nouvelles dimensions"),
				$this->getSizeField($form, $eBedSize, propertiesMixed: $eBedSize['propertiesMixed']),
				['data-wrapper' => 'width length alleyWidth size']
			);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"), ['class' => 'btn btn-secondary'])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-bed-size',
			title: s("Modifier des planches"),
			body: $h
		);

	}

	public function getSizeField(\util\FormUi $form, Bed $eBed, ?int $defaultBedLength = NULL, ?int $defaultBedWidth = NULL, ?int $defaultAlleyWidth = NULL, array $propertiesMixed = []): string {

		if($eBed->empty()) {
			$area = 0;
			$eBed['length'] = $defaultBedLength;
			$eBed['width'] = $defaultBedWidth;
			$eBed['alleyWidth'] = $defaultAlleyWidth;
		} else {
			$area = $eBed['area'];
		}

		// Une dimension qui diffère d'une planche à l'autre, dans la modification de plusieurs planches, reste vide et ne change pas
		$dMixed = function(\PropertyDescriber $d) use($propertiesMixed) {
			if(in_array($d->property, $propertiesMixed)) {
				$d->attributes = ['placeholder' => s("Plusieurs valeurs")];
			}
		};

		$h = '<div class="bed-write-size">';
			$h .= $form->dynamicField($eBed, 'length', $dMixed);
			$h .= '<div class="bed-write-size-form">';
				$h .= $form->dynamicField($eBed, 'width', $dMixed);
				$h .= $form->dynamicField($eBed, 'alleyWidth', $dMixed);
			$h .= '</div>';
			$h .= $this->getSizeArea($area);
		$h .= '</div>';

		return $h;

	}

	// Les mêmes dimensions en trois groupes, le libellé au-dessus de chaque champ, pour une colonne trop étroite pour leurs libellés en préfixe
	public function getSizeGroups(\util\FormUi $form, Bed $eBed): string {

		$h = '<div class="bed-write-size">';
			foreach(['length', 'width', 'alleyWidth'] as $property) {
				$h .= $form->dynamicGroup($eBed, $property, function(\PropertyDescriber $d) {
					$d->prepend = NULL;
				});
			}
			$h .= $this->getSizeArea(0);
		$h .= '</div>';

		return $h;

	}

	protected function getSizeArea(float $area): string {

		$h = '<div class="bed-write-size-area" data-area="'.$area.'">';
			$h .= s("Surface de planche : {value} m²", '<span>'.$area.'</span>');
		$h .= '</div>';

		return $h;

	}

	public function updatePlotCollection(int $season, Plot $ePlot, \Collection $cBed, \Collection $cZone): \Panel {

		MapboxUi::loadCartography();

		$form = new \util\FormUi();

		$h = $form->openAjax('/map/bed:doUpdatePlotCollection', ['autocomplete' => 'off']);

			$h .= $form->hidden('plotOrigin', $ePlot);
			$h .= $form->hidden('season', $season);

			$h .= $this->updateCollection($form, $ePlot, $cBed);

			$eBedField = new Bed([
				'plot' => $ePlot,
				'zone' => $ePlot['zone'],
				'structure' => $ePlot['structure'],
				'cZone' => $cZone
			]);

			$h .= $form->dynamicGroup($eBedField, 'plot', fn(\PropertyDescriber $d) => $d->label = s("Déplacer vers"));

			$h .= $form->group(
				content: $form->submit(s("Déplacer"), ['class' => 'btn btn-secondary'])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-bed-plot',
			title: s("Déplacer des planches"),
			body: $h
		);

	}

	public function updateSeasonCollection(int $season, Plot $ePlot, \Collection $cBed, ?string $origin): \Panel {

		MapboxUi::loadCartography();

		$form = new \util\FormUi();

		$h = $form->openAjax('/map/bed:doUpdateSeasonCollection', ['autocomplete' => 'off']);

			$h .= $form->hidden('plot', $ePlot);
			$h .= $form->hidden('season', $season);
			$h .= $form->hidden('origin', $origin);

			$h .= $this->updateCollection($form, $ePlot, $cBed);

			$eBedField = new Bed([
				'plot' => $ePlot,
				'zone' => $ePlot['zone'],
				'structure' => $ePlot['structure'],
			]);

			$h .= $form->group(
				s("Exploité"),
				new SeasonUi()->getField($form, $eBedField),
				['data-wrapper' => 'seasonFirst seasonLast']
			);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"), ['class' => 'btn btn-secondary'])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-bed-season',
			title: s("Modifier des planches"),
			body: $h
		);

	}

	public function swapSeries(Bed $eBed, int $season, \Collection $cZone): \Panel {

		$form = new \util\FormUi();

		$beds = [];

		foreach($cZone as $eZone) {

			if($eZone['cPlot']->empty()) {
				continue;
			}

			$zoneBeds = [];

			foreach($eZone['cPlot'] as $ePlot) {

				if($ePlot['cBed']->empty()) {
					continue;
				}

				if($ePlot['zoneFill'] === FALSE) {

					$zoneBeds[] = [
						'label' => $ePlot->getLabel(),
						'attributes' => ['disabled', 'style' => 'font-weight: bold; background-color: var(--background)']
					];

					$prefix = $ePlot['name'];

				} else {
					$prefix = $eZone['name'];
				}

				$ePlot['cBed']->sort('name', natural: TRUE);

				foreach($ePlot['cBed'] as $eBedSwap) {
					if($eBedSwap['plotFill'] === FALSE) {
						$zoneBeds[] = [
							'value' => $eBedSwap['id'],
							'label' => $prefix.' > '.$eBedSwap['name']
						];
					}
				}

			}

			$beds[] = [
				'label' => s("Zone {value}", $eZone['name']),
				'attributes' => ['disabled', 'style' => 'font-weight: bold; color: white; background-color: var(--text)']
			];

			$beds = array_merge($beds, $zoneBeds);

		}

		$h = $form->openAjax('/map/bed:doSwapSeries', ['id' => 'bed-update', 'autocomplete' => 'off', 'data-ajax-origin' => \Route::getRequestedOrigin()]);

			$h .= $form->hidden('id', $eBed);
			$h .= $form->hidden('season', $season);

			$h .= '<div class="util-block-info">';
				$h .= '<h3>'.s("Échanger les séries entre deux planches").'</h3>';
				$h .= '<p>'.s("Vous pouvez choisissez une deuxième planche avec laquelle toutes les séries de la saison {value} de la première planche seront échangées.", $season).'</p>';
			$h .= '</div>';

			$bed1 = s("Zone {value}", '<b>'.encode($eBed['zone']['name'])).'</b>';
			if($eBed['plot']['zoneFill'] === FALSE) {
				$bed1 .= ' &gt; <b>'.encode($eBed['plot']['name']).'</b> ';
			}
			$bed1 .= ' &gt; <b>'.encode($eBed['name']).'</b>';

			$h .= $form->group(
				s("Planche 1"),
				$bed1
			);

			$h .= $form->group(
				s("Planche 2"),
				$form->select('swapId', $beds, $eBed)
			);

			$h .= $form->group(
				content: $form->submit(s("Échanger les séries"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-bed-swap',
			title: s("Échanger les séries"),
			body: $h
		);

	}

	protected function updateCollection(\util\FormUi $form, Plot $ePlot, \Collection $cBed): string {

		$beds = [];

		foreach($cBed as $eBed) {
			$beds[] = $form->hidden('ids[]', $eBed['id']).'<b>'.encode($eBed['name']).'</b>';
		}

		$h = '';

		if($ePlot['zoneFill']) {

			$h .= $form->group(
				s("Parcelle"),
				'<b>'.encode($ePlot['zone']['name']).'</b>'
			);

		} else {

			$h .= $form->group(
				s("Jardin"),
				'<b>'.encode($ePlot['name']).'</b>'
			);

		}

		$h .= $form->group(
			s("Planches"),
			implode(', ', $beds)
		);

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Bed::model()->describer($property, [
			'name' => s("Nom de la planche"),
			'structure' => s("Ferme"),
			'zone' => s("Parcelle"),
			'plot' => s("Emplacement"),
			'length' => s("Longueur de planche"),
			'width' => s("Largeur travaillée de planche"),
			'alleyWidth' => s("Largeur de passe-pied"),
			'seasonFirst' => s("Exploité depuis"),
			'seasonLast' => s("Exploité jusqu'à"),
			'area' => s("Surface"),
			'createdAt' => s("Créée le"),
		]);

		switch($property) {

			case 'name' :
				$d->attributes = [
					'placeholder' => s("Ex. : A1")
				];
				break;

			case 'plot' :
				$d->required = TRUE;
				$d->values = function(Bed $e) {

					$e->expects(['cZone']);

					$values = [];

					foreach($e['cZone'] as $eZone) {

						foreach($eZone['cPlot'] as $ePlot) {

							if($ePlot['zoneFill']) {
								$values[$ePlot['id']] = s("Parcelle {value}", $eZone['name']);
							} else {
								$values[$ePlot['id']] = ">\u{a0}\u{a0}".$ePlot->getLabel();
							}

						}

					}

					return $values;

				};
				break;

			case 'length' :
				$d->prepend = s("Longueur");
				$d->append = s("m");
				break;

			case 'width' :
				$d->prepend = s("Largeur travaillée");
				$d->append = s("cm");
				break;

			case 'alleyWidth' :
				$d->prepend = s("Passe-pied");
				$d->append = s("cm");
				break;

			case 'seasonFirst' :
			case 'seasonLast' :
				$d->field = function(\util\FormUi $form, \Element $e, $property) {

					$e->expects(['plot', 'zone', 'structure']);

					$placeholders = [
						'seasonFirst' => s("la création du jardin"),
						'seasonLast' => s("la disparition du jardin")
					];

					$placeholder = $placeholders[$property];

					return new SeasonUi()->getDescriberField($form, $e, $e['structure'], $e['zone'], $e['plot'], $property, $placeholder);
				};
				break;

		}

		return $d;

	}

}
?>
