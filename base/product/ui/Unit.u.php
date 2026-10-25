<?php
namespace product;

class UnitUi {

	public static function urlManage(\structure\Structure $eStructure): string {

		return '/product/unit:manage?structure='.$eStructure['id'];

	}

	public static function getValue(?float $value, string|\product\Unit|null $unit, bool $short = FALSE, bool $noWrap = TRUE, ?\Closure $callback = NULL): string {

		if($value === NULL) {
			$value = '?';
		} else {
			$value = round($value, 4);
		}

		if($unit === NULL) {
			return $value;
		}

		$callback ??= fn($value, $unit) => $value.' '.$unit;

		if(is_string($unit)) {

			$text = match($unit) {
				'kg' => $callback($value, s('kg')),
				'gram' => $short ? $callback($value, s("g")) : p("{value} gramme", "{value} grammes", $value, ['value' => $value]),
				'bunch' => $short ? $callback($value, s("bte")) : p("{value} botte", "{value} bottes", $value, ['value' => $value]),
				'unit' => $short ? $callback($value, s("p.")) : ($value < 2 ? $callback($value, s("pièce")) : $callback($value, s("pièces"))),
				'empty' => $short ? $callback($value, s("u.")) : ($value < 2 ? $callback($value, s("unité")) : $callback($value, s("unités"))),
				'liter' => $short ? $callback($value, s("L")) : p("{value} litre", "{value} litres", $value, ['value' => $value]),
				'centiliter' => $short ? $callback($value, s("cl")) : p("{value} centilitre", "{value} centilitres", $value, ['value' => $value]),
				'milliliter' => $short ? $callback($value, s("ml")) : p("{value} millilitre", "{value} millilitres", $value, ['value' => $value]),
				'ton' => $short ? $callback($value, s("T")) : p("{value} tonne", "{value} tonnes", $value, ['value' => $value]),
				'hour' => $short ? $callback($value, s("h")) : p("{value} heure", "{value} heures", $value, ['value' => $value]),
				'box' => $short ? $callback($value, s("bte")) : p("{value} boite", "{value} boites", $value, ['value' => $value]),
				'tray' => $short ? $callback($value, s("bar")) : p("{value} barquette", "{value} barquettes", $value, ['value' => $value]),
				'bottle' => $short ? $callback($value, s("btl")) : p("{value} bouteille", "{value} bouteilles", $value, ['value' => $value]),
				'parcel' => $short ? $callback($value, s("colis")) : p("{value} colis", "{value} colis", $value, ['value' => $value]),
				'bag' => $short ? $callback($value, s("sac")) : p("{value} sachet", "{value} sachets", $value, ['value' => $value]),
				'pot' => $short ? $callback($value, s("pot")) : p("{value} pot", "{value} pots", $value, ['value' => $value]),
				'package' => $short ? $callback($value, s("ft")) : p("{value} forfait", "{value} forfaits", $value, ['value' => $value]),
				default => $callback($value, $unit)
			};

		} else {

			if($unit->empty()) {
				$text = $value;
			} else {

				$text = $value.' ';
				$text .= $short ? $unit['short'] : (\L::getType(\L::getLang(), $value) === 0 ? $unit['singular'] : $unit['plural']);

			}

		}

		return $noWrap ? str_replace(' ', ' ', $text) : $text;

	}

	public static function getBy(string|\product\Unit|null $unit, bool $short = FALSE): string {

		if(
			is_string($unit) or
			$unit->notEmpty()
		) {

			$text = ' / ';
			$text .= self::getSingular($unit, $short);

		} else {
			$text = '';
		}

		return $text;

	}

	public static function getSingular(string|\product\Unit $unit, bool $short = FALSE, bool $noWrap = TRUE): string {

		if(is_string($unit)) {

			$text = match($unit) {
				'kg' => s("kg"),
				'gram' => $short ? s("g") : s("gramme"),
				'bunch' => $short ? s("bte") : s("botte"),
				'unit' => $short ? s("p.") : s("pièce"),
				'empty' => $short ? s("u.") : s("unité"),
				'liter' => $short ? s("L") : s("litre"),
				'centiliter' => $short ? s("cl") : s("centilitre"),
				'milliliter' => $short ? s("ml") : s("millilitre"),
				'ton' => $short ? s("T") : s("tonne"),
				'hour' => $short ? s("h") : s("heure"),
				'box' => $short ? s("bte") : s("boite"),
				'tray' => $short ? s("bar") : s("barquette"),
				'bottle' => $short ? s("btl") : s("bouteille"),
				'parcel' => s("colis"),
				'bag' => $short ? s("sac") : s("sachet"),
				'pot' => s("pot"),
				'package' => $short ? s("ft") : s("forfait"),
				default => $unit
			};

		} else {

			if($unit->empty()) {
				return '';
			} else {

				$text = $short ? $unit['short'] : $unit['singular'];

			}

		}

		return $noWrap ? str_replace(' ', ' ', $text) : $text;

	}

	public static function getList(): array {

		return [
			'kg' => self::getSingular('kg'),
			'gram' => self::getSingular('gram'),
			'gram-100' => self::getSingular('gram-100'),
			'gram-250' => self::getSingular('gram-250'),
			'gram-500' => self::getSingular('gram-500'),
			'box' => self::getSingular('box'),
			'bunch' => self::getSingular('bunch'),
			'unit' => self::getSingular('unit'),
			'plant' => self::getSingular('plant'),
		];

	}

	public static function getBasicList(): array {

		return [
			'kg' => self::getSingular('kg'),
			'bunch' => self::getSingular('bunch'),
			'unit' => self::getSingular('unit'),
		];

	}

	public function getManageTitle(\structure\Structure $eStructure): string {

		return \main\DesignUi::getPageHeader(
			s("Unités de vente"),
			back: \structure\StructureUi::urlSettingsCommercialisation($eStructure),
			create: '<a href="/product/unit:create?structure='.$eStructure['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').' '.s("Nouvelle unité").'</a>'
		);

	}

	public function getManage(\Collection $cUnit): string {

		$h = '';

		if($cUnit->contains(fn($eUnit) => $eUnit['structure']->notEmpty()) === FALSE) {

			$h .= '<div class="util-block-info"">';
				$h .= s("Des unités de vente par défaut sont déjà fournies avec {siteName} et ne peuvent pas être modifiées. Cependant, vous avez la possibilité de créer de nouvelles unités adaptées à votre contexte de production.");
			$h .= '</div>';

		}

		$h .= '<div class="util-overflow-sm">';

			$h .= '<table class="tr-even">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Singulier").'</th>';
						$h .= '<th>'.s("Pluriel").'</th>';
						$h .= '<th>'.s("Version courte").'</th>';
						$h .= '<th></th>';
						$h .= '<th></th>';
					$h .= '</tr>';
				$h .= '</thead>';

				$h .= '<tbody>';

				foreach($cUnit as $eUnit) {

					$h .= '<tr class="'.($eUnit['structure']->empty() ? 'color-muted' : '').'">';
						$h .= '<td>';
							$h .= $eUnit->acceptUpdate() ? $eUnit->quick('singular', encode($eUnit['singular'])) : encode($eUnit['singular']);
						$h .= '</td>';
						$h .= '<td>';
							$h .= $eUnit->acceptUpdate() ? $eUnit->quick('plural', encode($eUnit['plural'])) : encode($eUnit['plural']);
						$h .= '</td>';
						$h .= '<td>';
							$h .= $eUnit->acceptUpdate() ? $eUnit->quick('short', encode($eUnit['short'])) : encode($eUnit['short']);
						$h .= '</td>';
						$h .= '<td class="color-muted">';

						if($eUnit['fqn'] !== NULL) {
							$h .= s("Fournie par défaut");
						} else {
							$h .= s("Personnalisée");
						}

						$h .= '</td>';
						$h .= '<td class="text-end">';

							if($eUnit->acceptUpdate()) {

								$h .= '<a href="/product/unit:update?id='.$eUnit['id'].'" class="btn btn-only-icon btn-color-primary">';
									$h .= \Asset::icon('gear-fill');
								$h .= '</a> ';

							}

							if($eUnit->acceptDelete()) {

								$h .= '<a data-ajax="/product/unit:doDelete" data-confirm="'.s("Supprimer cette unité de vente ?").'" post-id="'.$eUnit['id'].'" class="btn btn-only-icon btn-outline-primary">';
									$h .= \Asset::icon('trash-fill');
								$h .= '</a>';

							}

						$h .= '</td>';
					$h .= '</tr>';
				}
				$h .= '</tbody>';
			$h .= '</table>';

		$h .= '</div>';

		return $h;

	}

	public function create(Unit $eUnit): \Panel {

		$eUnit->expects(['structure']);

		$form = new \util\FormUi();

		$h = $form->openAjax('/product/unit:doCreate');

			$h .= $form->asteriskInfo();

			$h .= $this->getWarning();

			$h .= $form->hidden('structure', $eUnit['structure']['id']);
			$h .= $form->dynamicGroups($eUnit, ['singular*', 'plural*', 'short*', 'type*', 'approximate*']);
			$h .= $form->group(
				content: $form->submit(s("Ajouter"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-unit-create',
			title: s("Ajouter une nouvelle unité"),
			body: $h
		);

	}

	public function update(Unit $eUnit): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/product/unit:doUpdate');

			$h .= $this->getWarning();

			$h .= $form->hidden('id', $eUnit['id']);
			$h .= $form->dynamicGroups($eUnit, ['singular', 'plural', 'short', 'type', 'approximate']);
			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-unit-update',
			title: s("Modifier l'unité"),
			body: $h
		);

	}

	public function getWarning(): string {

		$h = '<div class="util-block-info mb-2">';
			$h .= '<h4>'.s("Utilisation des unités personnalisées").'</h4>';
			$h .= '<ul>';
				$h .= '<li>'.s("Elles sont bien prises en compte sur toutes les pages et documents générés par {siteName}").'</li>';
				$h .= '<li>'.s("Elles sont remplacées par &laquo; Unité &raquo; dans les factures électroniques qui sont envoyées à une plateforme agréée car <b>la facture électronique ne gère pas les unités personnalisées</b>").'</li>';
			$h .= '</ul>';
			$h .= '<p>'.s("Si vous avez des unités très spécifiques dont vous souhaitez garder la trace sur vos factures électroniques, nous vous suggérons plutôt d'utiliser le champ <i>Conditionnement</i> dans vos produits.").'</p>';
		$h .= '</div>';

		return $h;

	}

	public static function getField(\Collection $cUnit): array {

		$cUnitApproximate = new \Collection();
		$cUnitOthers = new \Collection();

		foreach($cUnit as $eUnit) {

			if($eUnit['approximate']) {
				$cUnitApproximate[] = $eUnit;
			} else {
				$cUnitOthers[] = $eUnit;
			}

		}

		$values = [];

		if($cUnitApproximate->notEmpty()) {

			$values[] = [
				'label' => s("Vente à la quantité"),
				'values' => $cUnitApproximate
			];

		}

		if($cUnitOthers->notEmpty()) {

			$values[] = [
				'label' => s("Vente à l'unité"),
				'values' => $cUnitOthers
			];

		}

		return $values;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Unit::model()->describer($property, [
			'singular' => s("Nom de l'unité au singulier"),
			'plural' => s("Nom de l'unité au pluriel"),
			'short' => s("Version courte"),
			'type' => s("Avec quelles quantités est utilisée l'unité ?"),
			'approximate' => s("Cette unité est-elle une unité de poids ?"),
		]);

		switch($property) {

			case 'singular' :
				$d->placeholder = s("bouquet");
				break;

			case 'plural' :
				$d->placeholder = s("bouquets");
				break;

			case 'short' :
				$d->placeholder = s("b.");
				$d->after = \util\FormUi::info(s("Maximum {value} caractères", Unit::model()->getPropertyRange('short')[1]));
				break;

			case 'approximate' :
				$d->field = 'yesNo';
				break;

			case 'type' :
				$d->values = [
					Unit::DECIMAL => s("Quantités décimales (0.00)").'  <span class="color-muted"><small>'.s("Exemple : 2.25 litres, 1.41 mètre...").'</small></span>',
					Unit::INTEGER => s("Quantités entières (0)").'  <span class="color-muted"><small>'.s("Exemple : 5 pots, 2 bouquets...").'</small></span>'
				];
				$d->default = Unit::INTEGER;
				break;

		}

		return $d;

	}


}
?>
