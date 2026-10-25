<?php
namespace plant;

class VarietyUi {

	/**
	 * 'value' porte le nom et non l'identifiant : faute de conteneur d'items, le
	 * widget recopie 'value' dans le champ visible. L'identifiant voyage dans 'id',
	 * que le widget transmet tel quel dans l'événement de sélection.
	 */
	public static function getAutocomplete(Variety $eVariety): array {

		return [
			'value' => $eVariety['name'],
			'itemHtml' => '<div>'.encode($eVariety['name']).'</div>',
			'itemText' => $eVariety['name'],
			'id' => $eVariety['id'],
		];

	}

	/**
	 * 'value' porte le nom tapé : c'est lui que le widget recopie dans le champ, et lui que le
	 * javascript envoie à la création. L'identifiant 'new' dit au formulaire qu'il n'existe pas.
	 */
	public static function getAutocompleteValidate(string $name): array {

		return [
			'class' => 'autocomplete-link',
			'value' => $name,
			'itemHtml' => \Asset::icon('check-lg').'<div>'.s("Valider « {value} »", encode($name)).'</div>',
			'itemText' => $name,
			'id' => 'new',
		];

	}

	// Une ligne non cliquable : le widget rend un « title » sans identifiant, et un clic dessus ne fait rien.
	public static function getAutocompleteHint(string $text): array {

		return [
			'type' => 'title',
			'itemHtml' => '<div class="autocomplete-hint">'.encode($text).'</div>',
			'itemText' => $text,
		];

	}

	public static function getAutocompleteTitle(string $title): array {

		return [
			'type' => 'title',
			'itemHtml' => '<div>'.encode($title).'</div>',
			'itemText' => $title,
		];

	}

	public static function getSeedsWeight1000(Variety $e, int $seeds): ?string {

		if($e['weightSeed1000'] === NULL) {
			return NULL;
		}

		$weight = ($seeds / 1000 * $e['weightSeed1000']);

		if($weight > 1000) {
			return \product\UnitUi::getValue(ceil($weight / 100) / 10, 'kg', short: TRUE);
		} else if($weight >= 10) {
			return \product\UnitUi::getValue(ceil($weight), 'gram', short: TRUE);
		} else if($weight >= 1) {
			return \product\UnitUi::getValue(ceil($weight * 10) / 10, 'gram', short: TRUE);
		} else {
			return \product\UnitUi::getValue(ceil($weight * 100) / 100, 'gram', short: TRUE);
		}

	}

	public static function getPlantsWeight(Variety $e, int $plants): ?string {

		if($e['plant'] === NULL) {
			return NULL;
		}

		$weight = ($plants / $e['numberPlantKilogram']);

		return \product\UnitUi::getValue(ceil($weight * 10) / 10, 'kg', short: TRUE);

	}

	public function displayByPlant(\structure\Structure $eStructure, Plant $ePlant, \Collection $cVariety, \Collection $cSupplier): \Panel {

		$h = '';

		if($cVariety->empty()) {
			$h .= '<div class="util-empty">';
				$h .= s("Vous n'avez pas encore ajouté de variété pour cette espèce.");
			$h .= '</div>';
			if($eStructure->canManage()) {

				$h .= '<h4>'.s("Ajouter une variété").'</h4>';


				$eVariety = new Variety([
					'structure' => $eStructure,
					'plant' => $ePlant,
					'cSupplier' => $cSupplier
				]);


				$h .= $this->createForm($eVariety, 'inline');

			}
		} else {

			if($eStructure->canManage()) {
				$h .= '<div class="text-end">';
					$h .= '<a href="/plant/variety:create?structure='.$eStructure['id'].'&plant='.$ePlant['id'].'" class="btn btn-primary">'.\Asset::icon('plus-circle').' '.s("Nouvelle variété").'</a>';
				$h .= '</div>';
			}

			$h .= '<div class="util-overflow-sm stick-xs">';

				$h .= '<table class="tr-even">';
					$h .= '<thead>';
						$h .= '<tr>';
							$h .= '<th>'.s("Nom").'</th>';
							$h .= '<th>'.s("Fournisseur<br/>de semences").'</th>';
							$h .= '<th>'.s("Fournisseur<br/>de plants").'</th>';
							$h .= '<th>'.s("Semences<br/>Poids pour 1000").'</th>';
							$h .= '<th>'.s("Plants<br/>Nombre pour 1 kg").'</th>';
							$h .= '<th></th>';
						$h .= '</tr>';
					$h .= '</thead>';

					$h .= '<tbody>';

					foreach($cVariety as $eVariety) {

						$supplierSeed = $eVariety['supplierSeed']->empty() ? '-' : encode($eVariety['supplierSeed']['name']);
						$supplierPlant = $eVariety['supplierPlant']->empty() ? '-' : encode($eVariety['supplierPlant']['name']);

						$h .= '<tr>';
							$h .= '<td>';
								$h .= encode($eVariety['name']);
							$h .= '</td>';
							$h .= '<td>';
								if($cSupplier->notEmpty()) {
									$h .= $eVariety->quick('supplierSeed', $supplierSeed);
								} else {
									$h .= $supplierSeed;
								}
							$h .= '</td>';
							$h .= '<td>';
								if($cSupplier->notEmpty()) {
									$h .= $eVariety->quick('supplierPlant', $supplierPlant);
								} else {
									$h .= $supplierPlant;
								}
							$h .= '</td>';
							$h .= '<td>';
								$h .= $eVariety->quick('weightSeed1000', $eVariety['weightSeed1000'] ? \product\UnitUi::getValue($eVariety['weightSeed1000'], 'gram') : '-');
							$h .= '</td>';
							$h .= '<td>';
								$h .= $eVariety->quick('numberPlantKilogram', $eVariety['numberPlantKilogram'] ? $eVariety['numberPlantKilogram'] : '-');
							$h .= '</td>';
							$h .= '<td class="text-end">';
								if($eStructure->canManage()) {
									$h .= '<a href="/plant/variety:update?id='.$eVariety['id'].'" class="btn btn-only-icon btn-color-primary">';
										$h .= \Asset::icon('gear-fill');
									$h .= '</a> ';
									$h .= '<a data-ajax="/plant/variety:doDelete" data-confirm="'.s("Supprimer cette variété ?").'" post-id="'.$eVariety['id'].'" class="btn btn-only-icon btn-outline-primary">';
										$h .= \Asset::icon('trash-fill');
									$h .= '</a>';
								}
							$h .= '</td>';
						$h .= '</tr>';
					}
					$h .= '</tbody>';
				$h .= '</table>';

			$h .= '</div>';

		}

		return new \Panel(
			id: 'panel-variety-list',
			title: s("Les variétés"),
			subTitle: PlantUi::getPanelHeader($ePlant),
			body: $h,
			close: 'reload'
		);

	}

	public function create(Variety $eVariety): \Panel {

		return new \Panel(
			id: 'panel-variety-create',
			title: s("Ajouter une variété"),
			body: $this->createForm($eVariety, 'panel'),
			close: 'reload'
		);

	}

	protected function createForm(Variety $eVariety, string $origin): string {

		$form = new \util\FormUi();

		$h = $form->openAjax('/plant/variety:doCreate', ['data-ajax-origin' => $origin]);

			$h .= $form->hidden('structure', $eVariety['structure']);
			$h .= $form->hidden('plant', $eVariety['plant']);

			$h .= $form->group(
				s("Espèce"),
				$form->fake($eVariety['plant']['name'])
			);
			$h .= $form->dynamicGroups($eVariety, ['name']);
			$h .= self::getSuppliers($form, $eVariety);
			$h .= $form->dynamicGroups($eVariety, ['weightSeed1000', 'numberPlantKilogram']);

			$h .= $form->group(
				content: $form->submit(s("Ajouter"))
			);

		$h .= $form->close();

		return $h;

	}

	public function update(Variety $eVariety): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/plant/variety:doUpdate');

			$h .= $form->hidden('id', $eVariety['id']);

			$h .= $form->group(
				s("Espèce"),
				$form->fake($eVariety['plant']['name'])
			);

			$h .= $form->dynamicGroups($eVariety, ['name']);
			$h .= self::getSuppliers($form, $eVariety);
			$h .= $form->dynamicGroups($eVariety, ['weightSeed1000', 'numberPlantKilogram', 'stock']);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-variety-update',
			title: s("Modifier une variété de la ferme"),
			body: $h,
			close: 'reload'
		);

	}

	protected static function getSuppliers(\util\FormUi $form, Variety $eVariety): string {

		$eVariety->expects(['structure']);

		if($eVariety['cSupplier']->notEmpty()) {
			return $form->dynamicGroups($eVariety, ['supplierSeed', 'supplierPlant']);
		} else {
			return $form->group(
				s("Fournisseurs de semences et plants pour cette variété"),
				'<div class="util-info">'.s("Pour associer une variété à un fournisseur, <link>créez d'abord une liste de vos fournisseurs habituels</link> !", ['link' => '<a href="/work/supplier:manage?structure='.$eVariety['structure']['id'].'" target="_blank">']).'</div>'
			);
		}

	}

	/**
	 * Describe properties
	 */
	public static function p(string $property): \PropertyDescriber {

		$d = Variety::model()->describer($property, [
			'fqn' => s("Nom qualifié"),
			'name' => s("Nom"),
			'plant' => s("Espèce"),
			'structure' => s("Ferme"),
			'supplierSeed' => s("Fournisseur de semences pour cette variété"),
			'supplierPlant' => s("Fournisseur de plants pour cette variété"),
			'weightSeed1000' => s("Poids pour 1000 graines"),
			'numberPlantKilogram' => s("Nombre de plants pour 1 kg"),
			'stock' => s("Semences en stock"),
			'ratingOverall' => s("Note globale"),
			'ratingComment' => s("Commentaire"),
		]);


		switch($property) {

			case 'ratingComment' :
				$d->attributes = ['placeholder' => s("Les observations que vous voulez partager sur cette variété")];
				break;

			case 'supplierSeed' :
			case 'supplierPlant' :
				$d->values = fn(Variety $e) => $e['cSupplier'] ?? $e->expects(['cSupplier']);
				break;

			case 'weightSeed1000' :
				$d->append = s("gramme(s)");
				$d->after = \util\FormUi::info(s("Adapté pour les graines"));
				break;

			case 'numberPlantKilogram' :
				$d->append = s("plant(s)");
				$d->after = \util\FormUi::info(s("Adapté pour les tubercules ou bulbes"));
				break;

			// Le stock se range en graines. Le choix de l'unité n'apparaît que si la
			// variété porte un poids de mille graines, faute de quoi les grammes ne
			// se convertiraient pas.
			case 'stock' :

				\Asset::js('plant', 'variety.js');

				// Le champ porte tantôt des graines, tantôt des grammes : un pas entier,
				// déduit de la colonne, ferait refuser un demi-gramme au navigateur, avant
				// même que la conversion ait lieu.
				$d->attributes = ['step' => 'any'];

				$d->append = fn(\util\FormUi $form, Variety $e) => (
					$e['weightSeed1000'] === NULL ?
						$form->addon(s("graine(s)")) :
						$form->select('unit', [
							Variety::UNIT_SEED => s("graine(s)"),
							Variety::UNIT_GRAM => s("gramme(s)")
						], Variety::UNIT_SEED, [
							'required' => TRUE,
							'data-unit' => Variety::UNIT_SEED,
							'data-weight' => $e['weightSeed1000'],
							'onchange' => 'PlantVariety.convertStock(this)'
						])
				);

				$d->after = fn(\util\FormUi $form, Variety $e) => (
					$e['weightSeed1000'] === NULL ?
						'' :
						\util\FormUi::info(s("1 000 graines de cette variété pèsent {weight}.", ['weight' => self::getSeedsWeight1000($e, 1000)]))
				);
				break;

		}

		return $d;

	}

}
?>
