<?php
namespace plant;

class ForecastUi {

	public function __construct() {

		\Asset::css('plant', 'forecast.css');
		\Asset::js('plant', 'forecast.js');

	}

	/**
	 * Les chiffres clés du prévisionnel, en tête de la feuille, quand il compte plus d'une espèce. L'intitulé passe
	 * au-dessus de la valeur : les deux totaux sont des montants voisins, que seul l'intitulé distingue.
	 */
	public function getFigures(\Collection $ccForecast, float $salesForecast, float $salesSeries): string {

		if($ccForecast->count() <= 1) {
			return '';
		}

		$figures = [
			s("Cultures") => $ccForecast->count(),
			s("Total prévisionnel") => ($salesForecast > 0) ? \util\TextUi::money($salesForecast, precision: 0) : '/',
			s("Total planifié") => ($salesSeries > 0) ? \util\TextUi::money($salesSeries, precision: 0) : '/',
		];

		$h = '<div class="util-figures mb-2">';

			foreach($figures as $label => $value) {
				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-label util-figure-label-top">'.$label.'</div>';
					$h .= '<div class="util-figure-value">'.$value.'</div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	public function display(\Collection $ccForecast) {

		\Asset::css('series', 'series.css');

		$h = '<div id="series-wrapper" class="series-item-wrapper series-item-forecast-wrapper util-overflow-lg stick-md">';

			$h .= '<div class="series-item-header series-item-forecast">';

				$h .= '<div class="util-table-header" style="grid-row: span 2">'.s("Espèce").'</div>';
				$h .= '<div class="util-table-header text-center color-private" style="grid-row: span 2">'.s("Prix et volume<br/>particuliers").'</div>';
				$h .= '<div class="util-table-header text-center color-pro" style="grid-row: span 2">'.s("Prix et volume<br/>professionnels").'</div>';
				$h .= '<div class="util-table-header series-item-forecast-objective-title" style="grid-column: span 3">'.s("Prévisionnel").'</div>';
				$h .= '<div class="util-table-header series-item-forecast-objective-title" style="grid-column: span 4">'.s("Planifié").'</div>';
				$h .= '<div class="util-table-header" style="grid-row: span 2"></div>';

				$h .= '<div class="util-table-header series-item-forecast-objective series-item-forecast-objective-first" style="grid-column: span 2">'.s("Volume").'</div>';
				$h .= '<div class="util-table-header series-item-forecast-objective series-item-forecast-objective-last">'.s("Ventes").'</div>';
				$h .= '<div class="util-table-header series-item-forecast-objective series-item-forecast-objective-first text-end">'.s("Surface").'</div>';
				$h .= '<div class="util-table-header series-item-forecast-objective" style="grid-column: span 2">'.s("Volume").'</div>';
				$h .= '<div class="util-table-header series-item-forecast-objective series-item-forecast-objective-last">'.s("Ventes").'</div>';

			$h .= '</div>';

			$gap = '<div class="series-item series-item-forecast series-item-gap">';
				$gap .= '<div></div>';
				$gap .= '<div style="grid-column: span 2"></div>';
				$gap .= '<div class="series-item-forecast-objective series-item-forecast-objective-first series-item-forecast-objective-last" style="grid-column: span 3"></div>';
				$gap .= '<div class="series-item-forecast-objective series-item-forecast-objective-first series-item-forecast-objective-last" style="grid-column: span 4"></div>';
				$gap .= '<div></div>';
			$gap .= '</div>';

			$h .= '<div class="series-item-body">';

				foreach($ccForecast as $cForecast) {

					$ePlant = $cForecast->first()['plant'];

					$h .= '<div class="series-item series-item-forecast series-item-title">';
						$h .= '<div class="series-item-title-plant" style="grid-column: span 3">';
							$h .= PlantUi::getVignette($ePlant, '2rem');
							$h .= '<span class="series-item-title-plant-name">'.encode($ePlant['name']).'</span>';
						$h .= '</div>';
						$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-first series-item-forecast-objective-last" style="grid-column: span 3"></div>';
						$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-first series-item-forecast-objective-last" style="grid-column: span 4"></div>';
						$h .= '<div></div>';
					$h .= '</div>';
					$h .= $gap;

					foreach($cForecast as $unit => $eForecast) {

						$cCultivation = $eForecast['cCultivation'];

						$harvestExpected = $eForecast['harvestExpectedOrTarget'];

						$h .= '<div class="series-item series-item-forecast">';
							$h .= '<div class="series-item-forecast-series">';
								$h .= self::p('unit')->values[$eForecast['unit']];
							$h .= '</div>';

							$h .= '<div class="series-item-forecast-price color-private">';
								$h .= '<div>';
									if($eForecast['privatePrice'] === NULL) {
											$h .= $eForecast->quick('privatePrice', \Asset::icon('plus-circle').' '.s("Prix"), class: 'btn btn-sm btn-outline-private');
									} else {
										$h .= $eForecast->quick('privatePrice', \util\TextUi::money($eForecast['privatePrice']));
									}
								$h .= '</div>';
								$h .= '<div>';
									if($eForecast['privatePrice'] !== NULL) {
										$h .= $eForecast->quick('privatePart', s("{value} %", $eForecast['privatePart']));
									}
								$h .= '</div>';
							$h .= '</div>';

							$h .= '<div class="series-item-forecast-price color-pro">';
								$h .= '<div>';
									if($eForecast['proPrice'] === NULL) {
											$h .= $eForecast->quick('proPrice', $eForecast->canUpdate() ? \Asset::icon('plus-circle').' '.s("Prix"): '', class: 'btn btn-sm btn-outline-pro');
									} else {
										$h .= $eForecast->quick('proPrice', \util\TextUi::money($eForecast['proPrice']));
									}
								$h .= '</div>';
								$h .= '<div>';
									if($eForecast['proPrice'] !== NULL) {
										$h .= $eForecast->quick('proPart', s("{value} %", $eForecast['proPart']));
									}
								$h .= '</div>';
							$h .= '</div>';

							if($eForecast['quantity'] === NULL) {
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-first series-item-forecast-objective-last" style="grid-column: span 3; justify-content: center">';
									$h .= $eForecast->quick('quantity', $eForecast->canUpdate() ? \Asset::icon('plus-circle').' '.s("Volume") : '', class: 'btn btn-sm btn-outline-primary');
								$h .= '</div>';
							} else {
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-first" style="padding-right: 0">';
									$h .= $eForecast->quick('quantity', $eForecast['quantity']);
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-unit">';
									$h .= \product\UnitUi::getSingular($unit, short: TRUE);
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-last series-item-forecast-sales">';
									if($eForecast['salesForecast'] > 0) {
										$h .= \util\TextUi::money($eForecast['salesForecast'], precision: 0);
									}
								$h .= '</div>';
							}

							if($cCultivation->count() === 0) {

								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-first series-item-forecast-objective-last text-center color-muted" style="grid-column: span 4">';
									$h .= s("Aucune série");
								$h .= '</div>';

							} else {

								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-first series-item-forecast-area">';
									if($eForecast['area'] > 0) {
										$h .= s("{value} m²", $eForecast['area']);
									}
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-harvest-expected">';
									if($harvestExpected > 0) {
										$h .= $harvestExpected;
									}
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-unit">';
									if($harvestExpected > 0) {
										$h .= \product\UnitUi::getSingular($unit, short: TRUE);
									}
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-last series-item-forecast-sales">';
									if($eForecast['salesSeries'] > 0) {
										$h .= \util\TextUi::money($eForecast['salesSeries'], precision: 0);
									}
								$h .= '</div>';

							}

							$h .= '<div class="series-item-forecast-actions">';
								if($eForecast->canWrite()) {
									$h .= '<a href="/plant/forecast:update?id='.$eForecast['id'].'" class="btn btn-only-icon btn-color-primary">'.\Asset::icon('gear-fill').'</a>';
								}
							$h .= '</div>';

						$h .= '</div>';

						foreach($cCultivation as $eCultivation) {

							$eSeries = $eCultivation['series'];
							$harvestExpected = (int)($eCultivation['harvestExpectedOrTarget'] ?? 0);

							$area = $eSeries['area'] ?? $eSeries['areaTarget'];

							$h .= '<div class="series-item series-item-forecast">';
								$h .= '<div class="series-item-forecast-series series-item-forecast-series-gap" style="grid-column: span 3">';
									$h .= \series\SeriesUi::link($eSeries);
									$h .= \sequence\CropUi::start($eCultivation, \work\WorkSetting::$mainActions);
									if($area === NULL) {
										$h .= '  <a href="/series/series:update?id='.$eSeries['id'].'" class="series-item-forecast-missing">'.\Asset::icon('exclamation-triangle-fill').' '.s("Définir l'objectif de surface").'</a>';
									}
									if($eCultivation['yieldExpected'] === NULL) {
										$h .= '  <a href="/series/cultivation:update?id='.$eCultivation['id'].'" class="series-item-forecast-missing">'.\Asset::icon('exclamation-triangle-fill').' '.s("Définir l'objectif de rendement").'</a>';
									}
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-first series-item-forecast-objective-last" style="grid-column: span 3">';
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-first series-item-forecast-area color-muted">';
									if($area !== NULL) {
										$h .= s("{value} m²", $area);
									} else {
										$h .= '?';
									}
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-harvest-expected color-muted">';
									if($harvestExpected > 0) {
										$h .= $harvestExpected;
									} else {
										$h .= '?';
									}
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-unit color-muted">';
									if($harvestExpected > 0) {
										$h .= \product\UnitUi::getSingular($unit, short: TRUE);
									}
								$h .= '</div>';
								$h .= '<div class="series-item-forecast-objective series-item-forecast-objective-last series-item-forecast-sales color-muted">';
									if($eCultivation['sales'] > 0) {
										$h .= \util\TextUi::money($eCultivation['sales'], precision: 0);
									}
								$h .= '</div>';
								$h .= '<div></div>';

							$h .= '</div>';

						}


						$h .= $gap;

					}

				}

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function create(Forecast $eForecast): \Panel {

		$form = new \util\FormUi();

		$eForecast['unit'] = Forecast::KG;

		$attributes = [
			'data-kg' => \product\UnitUi::getSingular(Forecast::KG, short: TRUE),
			'data-bunch' => \product\UnitUi::getSingular(Forecast::BUNCH, short: TRUE),
			'data-unit' => \product\UnitUi::getSingular(Forecast::UNIT, short: TRUE)
		];

		$h = $form->openAjax('/plant/forecast:doCreate', $attributes);

			$h .= $form->hidden('structure', $eForecast['structure']['id']);
			$h .= $form->hidden('season', $eForecast['season']);

			$h .= $form->dynamicGroups($eForecast, ['plant*', 'unit*']);
			$h .= $this->write($form, $eForecast);

			$h .= $form->group(
				content: $form->submit(s("Ajouter"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-forecast-create',
			title: s("Ajouter une espèce au prévisionnel"),
			body: $h
		);

	}

	public function update(Forecast $eForecast): \Panel {

		$eForecast->expects(['nCultivation']);

		$form = new \util\FormUi();

		$h = $form->openAjax('/plant/forecast:doUpdate');

			$h .= $form->hidden('id', $eForecast['id']);

			$h .= $form->group(
				s("Espèce"),
				PlantUi::link($eForecast['plant'])
			);

			$h .= $this->write($form, $eForecast);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();


		$footer = '<div class="text-end">';
			if($eForecast['nCultivation'] === 0) {
				$footer .= '<a data-ajax="/plant/forecast:doDelete" post-id="'.$eForecast['id'].'" class="btn btn-danger" data-confirm="'.s("Voulez-vous vraiment supprimer cette espèce du prévisionnel ?").'">'.s("Supprimer du prévisionnel").'</a>';
			} else {
				$footer .= '<span title="'.AlertUi::getError('Forecast::deleteUsed').'"><a class="btn btn-danger disabled">'.s("Supprimer du prévisionnel").'</a></span>';
			}
		$footer .= '</div>';


		return new \Panel(
			id: 'panel-forecast-update',
			title: s("Modifier une espèce du prévisionnel"),
			body: $h,
			footer: $footer
		);

	}

	protected function write(\util\FormUi $form, Forecast $eForecast): string {

		$h = $form->dynamicGroups($eForecast, ['quantity', 'privatePrice', 'proPrice']);
		$h .= $form->group(
			s("Répartition des ventes"),
			'<div class="forecast-create-part">'.
				$form->inputGroup(
					$form->dynamicField($eForecast, 'privatePart').$form->addon(s("% aux particuliers"))
				).
				$form->inputGroup(
					$form->dynamicField($eForecast, 'proPart').$form->addon(s("% aux professionnels"))
				).
			'</div>',
			['data-wrapper' => 'privatePart proPart']
		);

		return $h;

	}

	/**
	 * Describe properties
	 */
	public static function p(string $property): \PropertyDescriber {

		$d = Forecast::model()->describer($property, [
			'quantity' => s("Objectif de volume"),
			'privatePrice' => s("Prix moyen pour les particuliers"),
			'proPrice' => s("Prix moyen pour les professionnels"),
			'plant' => s("Espèce"),
			'unit' => s("Unité de vente"),
		]);

		switch($property) {

			case 'plant' :
				$d->autocompleteBody = function(\util\FormUi $form, Forecast $e) {
					$e->expects(['structure']);
					return [
						'structure' => $e['structure']['id']
					];
				};
				new \plant\PlantUi()->query($d);
				break;

			case 'quantity' :
				$d->append = fn(\util\FormUi $form, Forecast $e) => $form->addon('<span data-ref="forecast-unit">'.\product\UnitUi::getSingular($e['unit'], short: TRUE).'</span>');
				break;


			case 'unit' :
				$d->values = [
					Forecast::UNIT => s("À la pièce"),
					Forecast::BUNCH => s("À la botte"),
					Forecast::KG => s("Au kg")
				];
				$d->attributes += [
					'callbackRadioAttributes' => function() {
						return ['oninput' => 'Forecast.changeUnit(this, "forecast-unit")'];
					}
				];
				break;

			case 'proPrice' :
			case 'privatePrice' :
				$d->append = fn(\util\FormUi $form, Forecast $e) => $form->addon(' € / <span data-ref="forecast-unit">'.\product\UnitUi::getSingular($e['unit'], short: TRUE).'</span>');

			case 'proPart' :
			case 'privatePart' :
				$d->attributes += [
					'oninput' => 'Forecast.changePart(this)'
				];
				break;

		}

		return $d;

	}

}
?>
