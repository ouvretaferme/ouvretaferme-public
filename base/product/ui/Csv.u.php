<?php
namespace product;

class CsvUi {

	public function __construct() {

		\Asset::css('main', 'csv.css');

	}

	public function getAlerts(\structure\Structure $eStructure, array $data): string {

		$h = '';

		foreach($data['errorsGlobal'] as $type => $values) {

			if(empty($values)) {
				continue;
			}

			switch($type) {

				case 'vatRates' :

					$vatRates = ProductSetting::getVatRates($eStructure);
					array_walk($vatRates, fn(&$value) => $value .= ' %');

					array_walk($values, fn(&$value) => $value .= ' %');

					$h .= '<div class="util-block">';
						$h .= '<h4 class="color-danger">'.s("Taux de TVA invalides").'</h4>';
						$h .= '<p>'.s("Les taux de TVA suivants ne sont pas reconnus par {siteName}, corrigez votre fichier CSV :", ['values' => '<a href="'.UnitUi::urlManage($eStructure).'" target="_blank">']).'</p>';
						$h .= '<p style="font-style: italic">'.encode(implode(', ', $values)).'</p>';
						$h .= '<p>'.s("Pour votre pays, {siteName} supporte les taux de TVA suivants : {value}", implode(', ', $vatRates)).'</p>';
					$h .= '</div>';
					break;

				case 'units' :
					array_walk($values, 'encode');
					$h .= '<div class="util-block">';
						$h .= '<h4 class="color-danger">'.s("Unités manquantes").'</h4>';
						$h .= '<p>'.s("Les unités suivantes n'existent pas, corrigez votre fichier CSV pour les faire correspondre à une unité existante ou ajoutez-les. Pour rappel, vous devez utiliser <link>le nom des unités au singulier</link> pour qu'elles soient reconnues.", ['link' => '<a href="'.UnitUi::urlManage($eStructure).'" target="_blank">']).'</p>';
						$h .= '<p style="font-style: italic">'.encode(implode(', ', $values)).'</p>';
						$h .= '<a href="'.UnitUi::urlManage($eStructure).'" target="_blank" class="btn btn-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter des unités").'</a>';
					$h .= '</div>';
					break;

				case 'species' :
					array_walk($values, 'encode');
					$h .= '<div class="util-block">';
						$h .= '<h4 class="color-danger">'.s("Espèces manquantes").'</h4>';
						$h .= '<p>'.s("Les espèces suivantes n'existent pas ou sont désactivées, corrigez votre fichier CSV pour les faire correspondre à une espèce existante ou ajoutez-les :", ['link' => '<a href="'.\plant\PlantUi::urlManage($eStructure).'" target="_blank">']).'</p>';
						$h .= '<p style="font-style: italic">'.encode(implode(', ', $values)).'</p>';
						$h .= '<a href="'.\plant\PlantUi::urlManage($eStructure).'" target="_blank" class="btn btn-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter des espèces").'</a>';
					$h .= '</div>';
					break;

				case 'profiles' :
					array_walk($values, 'encode');
					$h .= '<div class="util-block">';
						$h .= '<h4 class="color-danger">'.s("Types de produits non reconnus").'</h4>';
						$h .= '<p>'.s("Les types de produits suivants ne peuvent pas être importés, vous devez les retirer de votre fichier CSV.").'</p>';
						$h .= '<p style="font-style: italic">'.encode(implode(', ', $values)).'</p>';
					$h .= '</div>';
					break;

			}

		}

		foreach($data['infoGlobal'] as $type => $values) {

			if(empty($values)) {
				continue;
			}

			switch($type) {

				case 'references' :
					$h .= '<div class="util-block">';
					$h .= '<h4>'.s("Références déjà connues").'</h4>';
					$h .= '<p>'.s("Les références suivantes ont été reconnues. Les produits concernés ne seront pas ajoutés une deuxième fois mais seront modifiés avec les nouvelles valeurs. <b>Toutes les valeurs, à l'exception de l'unité de vente qui ne peut pas être modifiée par un import, seront mises à jour y compris celles qui sont vides ou ne sont pas présentes dans votre fichier CSV, soyez vigilant pour ne pas perdre des données !</b>").'</p>';
					$h .= '<p style="font-style: italic">'.encode(implode(', ', $values)).'</p>';
					$h .= '</div>';
					break;

			}

		}

		return $h;

	}

	public function getExportProductsHeader(\structure\Structure $eStructure): array {

		return [
			'type',
			'name',
			'reference',
			'unit',
			'price_private',
			'price_pro',
			'vat_rate',
			'distribution',
			'additional',
			'origin',
			'marks',
			'species',
			'variety',
			'frozen',
			'packaging',
			'composition',
			'allergen'
		];

	}

	public function getProducts(\structure\Structure $eStructure, array $data): string {

		['import' => $import, 'errorsCount' => $errorsCount, 'errorsGlobal' => $errorsGlobal, 'infoGlobal' => $infoGlobal] = $data;

		$h = '';

		if($errorsCount > 0) {
			$h .= \main\CsvUi::getGlobalErrors($errorsCount, '/doc/import:products');
		} else {
			$h .= \main\DesignUi::getWeigh(
				'info',
				'upload',
				p("Importer le produit", "Importer les {value} produits", count($import)),
				NULL,
				[
					['icon' => 'box-seam', 'text' => p("<b>Le produit du tableau ci-dessous</b> sera créé dans votre structure.", "<b>Les {value} produits du tableau ci-dessous</b> seront créés dans votre structure.", count($import))]
				],
				[],
				TRUE
			);

			$h .= '<a data-ajax="/product/csv:doCreateProducts" post-id="'.$eStructure['id'].'" class="btn btn-secondary" data-ajax-waiter="'.s("Importation en cours, merci de patienter...").'">'.p("Importer le produit", "Importer les {value} produits", count($import)).'</a>';
		}

		$h .= $this->getAlerts($eStructure, $data);

		$h .= '<div class="util-overflow-lg">';

			$h .= '<table class="tr-even">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th rowspan="2">'.s("Nom").'</th>';
						$h .= '<th rowspan="2">'.s("Unité").'</th>';
						$h .= '<th colspan="2" class="text-center">'.s("Prix").'</th>';
						$h .= '<th rowspan="2" class="text-end">'.s("TVA").'</th>';
						$h .= '<th rowspan="2">'.s("Autres données").'</th>';
					$h .= '</tr>';
					$h .= '<tr>';
						$h .= '<th class="text-end">'.s("Particulier").'</th>';
						$h .= '<th class="text-end">'.s("Pro").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody class="td-vertical-align-top">';

				foreach($import as $product) {

					$h .= '<tr class="'.($product['errors'] ? 'csv-error' : ($product['warnings'] ? 'csv-warning' : '')).'">';

						$h .= '<td>';

							if($product['name'] === NULL) {
								$h .= '<span class="color-danger">'.\Asset::icon('exclamation-triangle').' '.s("Nom manquant").'</span>';
							} else {
								$h .= encode($product['name']);
							}

							if($product['profile'] !== NULL) {
								$h .= '<div class="util-annotation">';
									if(isset(ProductUi::p('profile')->values[$product['profile']])) {
										$h .= ProductUi::p('profile')->values[$product['profile']];
									} else {
										$h .= $product['profile'];
									}
								$h .= '</div>';
							}

							if($product['description'] !== NULL) {
								$h .= '<div class="font-xs" style="max-width: 15rem; margin-top: 0.25rem">';
									$h .= new \editor\EditorUi()->value($product['description']);
								$h .= '</div>';
							}

							if(in_array($product['reference'], $infoGlobal['references'])) {
								$h .= '<div class="color-secondary mt-1">'.\Asset::icon('info-circle').' '.s("Référence {value} déjà connue", encode($product['reference'])).'</div>';
							}

						$h .= '</td>';
						$h .= '<td>';
							if($product['eUnit']->notEmpty()) {
								$h .= encode($product['eUnit']['singular']);
							} else if($product['unit'] !== NULL) {
								$h .= '<span class="color-danger">'.\Asset::icon('exclamation-triangle').' '.encode($product['unit']).'</span>';
							}
						$h .= '</td>';
						$h .= '<td class="t-highlight text-end">';
							if($product['price_private'] !== NULL) {
								$h .= \util\TextUi::money($product['price_private']);
								$h .= $eStructure->conf()['hasVat'] ? ' <span class="util-annotation">'.ProductUi::getTaxes('private').'</span>' : '';
							}
						$h .= '</td>';
						$h .= '<td class="t-highlight text-end">';
							if($product['price_pro'] !== NULL) {
								$h .= \util\TextUi::money($product['price_pro']);
								$h .= $eStructure->conf()['hasVat'] ? ' <span class="util-annotation">'.ProductUi::getTaxes('pro').'</span>' : '';
							}
						$h .= '</td>';
						$h .= '<td class="text-end">';
							if($product['vat'] !== NULL) {
								$h .= s("{value} %", $product['vat_rate']);
							} else {
								$h .= '<span class="color-danger">'.\Asset::icon('exclamation-triangle').' '.encode(s("{value} %", $product['vat_rate'])).'</span>';
							}
						$h .= '</td>';
						$h .= '<td>';
							$h .= '<dl class="util-presentation util-presentation-1" style="grid-row-gap: 0.125rem">';

								if($product['reference']) {
									$h .= '<dt>'.ProductUi::getPropertyLabel('reference', new Product(['variantOf' => new Product()])).'</dt>';
									$h .= '<dd>';
										if(in_array('referenceInvalid', $product['errors'])) {
											$h .= '<span class="color-danger">'.\Asset::icon('exclamation-triangle').' '.encode($product['reference']).'</span>';
										} else {
											$h .= $product['reference'];
										}
									$h .= '</dd>';
								}

								if($product['distribution']) {
									$h .= '<dt>'.s("Distribution").'</dt>';
									$h .= '<dd>';
										if(in_array('distributionInvalid', $product['errors'])) {
											$h .= '<span class="color-danger">'.\Asset::icon('exclamation-triangle').' '.encode($product['distribution']).'</span>';
										} else {
											$h .= ProductUi::p('distribution')->values[$product['distribution']];
										}
									$h .= '</dd>';
								}

								if($product['additional']) {
									$h .= '<dt>'.s("Complément").'</dt>';
									$h .= '<dd>'.encode($product['additional']).'</dd>';
								}

								if($product['marks']) {
									$h .= '<dt>'.s("Signes de qualité").'</dt>';
									$h .= '<dd>';

										if($product['organic'] === Product::YES) {
											$h .= '<div>'.s("Agriculture biologique").'</div>';
										} else if($product['organic'] === Product::CONVERSION) {
											$h .= '<div>'.s("En conversion vers l'agriculture biologique").'</div>';
										}

										foreach($product['marksInvalid'] as $mark) {
											$h .= '<div class="color-danger">'.\Asset::icon('exclamation-triangle').' '.encode($mark).'</div>';
										}

										foreach($product['qualities']->values() as $quality) {
											$h .= '<div>'.ProductUi::getQualitiesLabels()[$quality].'</div>';
										}

									$h .= '</dd>';

								}

								if($product['origin']) {
									$h .= '<dt>'.ProductUi::p('origin')->label.'</dt>';
									$h .= '<dd>'.encode($product['origin']).'</dd>';
								}

								if($product['ePlant']->notEmpty()) {
									$h .= '<dt>'.ProductUi::p('plant')->label.'</dt>';
									$h .= '<dd>'.encode($product['ePlant']['name']).'</dd>';
								} else if($product['species'] !== NULL and in_array($product['profile'], Product::getProfiles('plant'))) {
									$h .= '<dt>'.ProductUi::p('plant')->label.'</dt>';
									$h .= '<dd><span class="color-danger">'.\Asset::icon('exclamation-triangle').' '.encode($product['species']).'</span></dd>';
								}

								if($product['variety']) {
									$h .= '<dt>'.ProductUi::p('variety')->label.'</dt>';
									$h .= '<dd>'.encode($product['variety']).'</dd>';
								}

								if($product['frozen']) {
									$h .= '<dt>'.ProductUi::p('frozen')->label.'</dt>';
									$h .= '<dd>'.s("Oui").'</dd>';
								}

								if($product['allergen']) {
									$h .= '<dt>'.ProductUi::p('allergens')->label.'</dt>';
									$h .= '<dd>'.encode($product['allergen']).'</dd>';
								}

								if($product['composition']) {
									$h .= '<dt>'.ProductUi::p('ingredients')->label.'</dt>';
									$h .= '<dd>'.encode($product['composition']).'</dd>';
								}

								if($product['packaging']) {
									$h .= '<dt>'.ProductUi::p('packaging')->label.'</dt>';
									$h .= '<dd>'.encode($product['packaging']).'</dd>';
								}

							$h .= '</dl>';
						$h .= '</td>';
					$h .= '</tr>';

					if($product['errors'] or $product['warnings']) {

						$profiles = [];

						foreach(Product::getProfiles('import') as $profile) {
							$profiles[] = CsvLib::getProductProfiles()[$profile];
						}

						$messages = [
							'unitInvalid' => s("L'unité n'est pas reconnue"),
							'vatRateInvalid' => s("Le taux de TVA n'est pas reconnu"),
							'profileMissing' => s("Il manque le profil du produit"),
							'profileInvalid' => s("Le profil du produit est incorrect dans le fichier CSV ({value})", implode(', ', $profiles)),
							'referenceError' => s("Une référence de produit ne peut contenir que des lettres, des chiffres ou des tirets"),
							'nameMissing' => s("Il manque le nom du produit"),
							'speciesInvalid' => s("Cette espèce n'existe pas sur votre ferme"),
							'speciesIncompatible' => s("Le choix d'une espèce est incompatible avec ce type de produit et sera ignoré"),
							'varietyIncompatible' => s("Le choix d'une variété est incompatible avec ce type de produit et sera ignoré"),
							'allergenIncompatible' => s("Les allergènes sont incompatibles avec ce type de produit et seront ignorés"),
							'packagingIncompatible' => s("Le choix d'un conditionnement est incompatible avec ce type de produit et sera ignoré"),
							'compositionIncompatible' => s("Le choix d'une composition est incompatible avec ce type de produit et sera ignoré"),
							'frozenIncompatible' => s("La surgélation est incompatible avec ce type de produit et sera ignorée"),
							'distributionInvalid' => s("Le mode de distribution choisi n'est pas reconnu"),
							'distributionIncompatible' => s("Le choix d'un mode de distribution est incompatible avec ce type de produit et sera ignoré"),
							'markInvalid' => s("Un signe de qualité choisi n'est pas disponible"),
							'marksIncompatible' => s("Le choix d'un signe de qualité est incompatible avec ce type de produit et sera ignoré"),
						];

						$h .= '<tr>';
							$h .= '<td colspan="9">';
								$h .= '<ul class="mb-0">';
									foreach($product['errors'] as $error) {
										$h .= '<li class="color-danger">'.$messages[$error].'</li>';
									}
									foreach($product['warnings'] as $warning) {
										$h .= '<li class="color-warning">'.$messages[$warning].'</li>';
									}
								$h .= '</ul>';
							$h .= '</td>';
						$h .= '</tr>';

					}

				}

				$h .= '</tbody>';

			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

}
?>
