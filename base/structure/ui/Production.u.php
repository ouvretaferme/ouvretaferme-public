<?php
namespace structure;

class ProductionUi {

	public function __construct() {

		\Asset::css('structure', 'production.css');
		\Asset::js('structure', 'production.js');

	}

	public static function getCategories(): array {

		static $categories = [
			'cultures' => [
				'label' => s("Culture"),
				'productions' => Production::CROP
			],
			'elevage' => [
				'label' => s("Élevage"),
				'productions' => Production::ANIMAL
			],
			'artisanat' => [
				'label' => s("Artisanat"),
				'productions' => Production::CRAFT
			]
		];

		return $categories;

	}

	public static function getCategory(string $category): array {
		return self::getCategories()[$category] ?? throw new \UnsupportedException('Unknown '.$category);
	}

	public function getDetails(Production $eProduction, Structure $eStructure): string {

		$acceptGreenhouse = $eProduction->acceptOption('areaGreenhouse');

		$list = [];

		if(
			$eProduction['startedAt'] !== NULL and
			$eProduction['startedAt'] !== $eStructure['startedAt']
		) {
			$list[] = '<span>'.s("Depuis {value}", $eProduction['startedAt']).'</span>';
		}

		if($eProduction['organic'] !== $eStructure['organic']) {

			$content = '<span>';
				$content .= match($eProduction['organic']) {

					Production::YES => s("Agriculture biologique"),
					Production::CONVERSION => s("En conversion vers l'agriculture biologique"),
					Production::NO => s("Pas en agriculture biologique")

				};
			$content .= '</span>';

			$list[] = $content;

		}

		if($acceptGreenhouse) {

			if($eProduction['area'] !== NULL) {
				$list[] = '<span>'.s("Surface en plein champ <b>{value} ha</b>", $eProduction['area']).'</span>';
			}

			if($eProduction['areaGreenhouse'] !== NULL) {
				$list[] = '<span>'.s("Surface sous tunnel <b>{value} m²</b>", $eProduction['areaGreenhouse']).'</span>';
			}

		} else {

			if($eProduction['area'] !== NULL) {
				$list[] = '<span>'.s("Surface <b>{value} ha</b>", $eProduction['area']).'</span>';
			}

		}

		return implode(' &middot; ', $list);

	}

	public static function getList(): array {

		static $productions = [

			'maraichage' => s("Maraichage diversifié"),
			'legume' => s("Légume de plein champ"),
			'arboriculture' => s("Arboriculture"),
			'grande-culture' => s("Grande culture"),
			'vigne' => s("Vigne"),
			'petit-fruit' => s("Petit fruit"),
			'horticulture' => s("Horticulture"),
			'fleur' => s("Fleur"),
			'semence' => s("Semence"),
			'ppam' => s("Plante aromatique et médicinale"),
			'champignon' => s("Champignon"),

			'bovin' => s("Bovin"),
			'caprin' => s("Caprin"),
			'ovin' => s("Ovin"),
			'porcin' => s("Porcin"),
			'volaille' => s("Volaille"),
			'apiculture' => s("Apiculture"),

			'boulangerie' => s("Boulangerie"),
			'brasserie' => s("Brasserie"),
			'conserverie' => s("Conserverie"),
			'cosmetique' => s("Cosmétique"),
			'restauration' => s("Restauration"),
			'transformation' => s("Transformation"),

			'other' => s("Autre production"),

		];

		return $productions;

	}

	public static function get(string $fqn): string {
		return self::getList()[$fqn] ?? throw new \UnsupportedException('Unknown '.$fqn);
	}

	/**
	 * @param bool $collapse Replier les ateliers déjà renseignés, quand il y en a plusieurs. Le repli
	 *                       est conditionné à l'écran appelant et non à la donnée : l'étendre à la
	 *                       configuration de la ferme serait un changement que personne n'a demandé.
	 */
	public function choose(\util\FormUi $form, Structure $eStructure, \Collection $cProduction, bool $options, bool $collapse = FALSE): string {

		$eProductionNew = new Production([
			'fqn' => NULL,
			'startedAt' => $eStructure['startedAt'],
			'organic' => $eStructure['organic']
		]);

		if($cProduction->empty()) {
			$cProduction->append($eProductionNew);
		}

		$h = '<style>';

			foreach(self::getList() as $fqn => $label) {

				$h .= 'form.system-update-form div.production-item:has(option[value="'.$fqn.'"]:checked) [data-for~="'.$fqn.'"] { display: initial; }'."\n";
				$h .= 'form.system-update-form:has(div.production-item option[value="'.$fqn.'"]:checked) [data-for-global~="'.$fqn.'"] { display: initial; }'."\n";

			}

		$h .= '</style>';

		$h .= '<div class="production-wrapper">';

			$h .= '<div class="production-items">';

				$position = 0;

				// Un atelier sans métier ne se replie pas : le replier cacherait la seule chose
				// qu'il y ait à y faire.
				$collapsed = ($collapse and $cProduction->count() > 1);

				foreach($cProduction as $eProduction) {
					$h .= $this->select($form, $eProduction, $options, $position++, $collapsed ? ($eProduction['fqn'] !== NULL) : NULL);
				}

			$h .= '</div>';

			$h .= '<div class="text-end">';
				$h .= '<a data-action="production-add">'.s("Ajouter un autre atelier").'</a> ';
			$h .= '</div>';

			$h .= '<script>';
				$h .= 'productionSpare = `'."\n";
				$h .= $this->select($form, $eProductionNew, $options, NULL, $collapsed ? FALSE : NULL);
				$h .= '`;';
			$h .= '</script>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * @param ?bool $collapsed NULL pour un atelier sans repli ; sinon son état de départ.
	 */
	protected function select(\util\FormUi $form, Production $eProduction, bool $options, ?int $position, ?bool $collapsed = NULL): string {

		$content = '<div class="util-block mb-0">';

			$content .= '<div class="production-item-fqn">';

				$content .= $form->dynamicField($eProduction, 'fqn['.$position.']');
				$content .= '<a class="btn btn-only-icon production-item-remove" data-action="production-remove">'.\Asset::icon('trash').'</a>';

			$content .= '</div>';

			if($options) {

				$content .= '<div class="production-item-options form-fieldsets">';
					$content .= $this->getAreaOption($form, $eProduction, $position);
					$content .= $this->getAreaGreenhouseOption($form, $eProduction, $position);
					$content .= $this->getStartedAtOption($form, $eProduction, $position);
					$content .= $this->getMechanisationOption($form, $eProduction, $position);
					$content .= $this->getDescriptionOption($form, $eProduction, $position);
					$content .= $this->getOrganicOption($form, $eProduction, $position);
				$content .= '</div>';


			} else {
				$content .= $form->hidden('organic['.$position.']', $eProduction['organic']);
			}

		$content .= '</div>';

		$label = s("Atelier<span> n°{value}</span>", ['value' => '<span class="production-item-position">'.($position !== NULL ? $position + 1 : '').'</span>', 'span'  => '<span class="production-item-numbering">']);

		if($position === 0) {
			$label = '<div data-wrapper="general">'.$label.'</div>';
		}

		if($collapsed === NULL) {

			$h = '<div class="production-item">';
				$h .= $form->group(
					$label,
					$content
				);
			$h .= '</div>';

			return $h;

		}

		$empty = s("Nouvel atelier");

		// L'en-tête et le détail entrent dans le même groupe de formulaire : ils prennent ainsi la
		// colonne des champs, alors que l'en-tête s'étalerait sinon sur toute la largeur et
		// poserait son chevron à mille pixels du libellé qu'il ouvre.
		$toggle = '<div class="production-item-toggle'.($collapsed ? '' : ' selected').'" data-action="production-toggle">';
			$toggle .= '<span class="production-item-toggle-label" data-empty="'.$empty.'">'.($eProduction['fqn'] ? encode(self::get($eProduction['fqn'])) : $empty).'</span>';
			$toggle .= '<span class="production-item-toggle-chevron">'.\Asset::icon('chevron-down').'</span>';
		$toggle .= '</div>';

		// La gouttière reste vide : le rang ne distingue pas un atelier d'un autre, le métier si, et
		// il est déjà dans la ligne. Le wrapper subsiste seul, il porte le message d'une liste vide.
		$labelCollapsed = ($position === 0) ? '<div data-wrapper="general"></div>' : '';

		$h = '<div class="production-item production-item-collapsible'.($collapsed ? ' production-item-collapsed' : '').'">';
			$h .= $form->group(
				$labelCollapsed,
				$toggle.'<div class="production-item-detail">'.$content.'</div>'
			);
		$h .= '</div>';

		return $h;

	}

	protected function getAreaOption(\util\FormUi $form, Production $eProduction, ?int $position) {

		return $form->dynamicFieldset($eProduction, 'area['.$position.']', function($d) {

			$d->label = '<div data-for="'.implode(' ', array_diff(Production::getOptions('area'), Production::getOptions('areaGreenhouse'))).'">'.s("Surface cultivée").'</div>';
			$d->label .= '<div data-for="'.implode(' ', Production::getOptions('areaGreenhouse')).'">'.s("Surface en plein champ").'</div>';

			$d->fieldset = [
				'data-for' => implode(' ', Production::getOptions('area')),
				'class' => 'production-item-option'
			];

		});

	}

	protected function getAreaGreenhouseOption(\util\FormUi $form, Production $eProduction, ?int $position) {

		return $form->dynamicFieldset($eProduction, 'areaGreenhouse['.$position.']', function($d) {
			$d->fieldset = [
				'data-for' => implode(' ', Production::getOptions('areaGreenhouse')),
				'class' => 'production-item-option'
			];
		});

	}

	protected function getMechanisationOption(\util\FormUi $form, Production $eProduction, ?int $position) {

		return $form->dynamicFieldset($eProduction, 'mechanisation['.$position.']', function($d) {
			$d->fieldset = [
				'data-for' => implode(' ', Production::getOptions('mechanisation')),
				'class' => 'production-item-option'
			];
		});

	}

	protected function getStartedAtOption(\util\FormUi $form, Production $eProduction, ?int $position) {

		return $form->dynamicFieldset($eProduction, 'startedAt['.$position.']', function($d) {
			$d->fieldset = ['class' => 'production-item-option'];
		});

	}

	protected function getDescriptionOption(\util\FormUi $form, Production $eProduction, ?int $position) {

		return $form->dynamicFieldset($eProduction, 'description['.$position.']', function($d) {
			$d->fieldset = ['class' => 'production-item-option'];
		});

	}

	protected function getOrganicOption(\util\FormUi $form, Production $eProduction, ?int $position) {

		return $form->dynamicFieldset($eProduction, 'organic['.$position.']', function($d) {
			$d->fieldset = ['class' => 'production-item-option'];
		});

	}
	
	public function update(Production $eProduction): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/production:doUpdate');

			$h .= $form->hidden('id', $eProduction['id']);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-production-update',
			title: s("Modifier les productions"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Production::model()->describer($property, [
			'place' => s("Lieu de production"),
			'startedAt' => s("Année de démarrage"),
			'description' => s("Description"),
			'area' => s("Surface en plein champ"),
			'areaGreenhouse' => s("Surface sous tunnel"),
			'organic' => s("Agriculture biologique"),
			'mechanisation' => s("Mécanisation"),
		]);

		switch($property) {

			case 'fqn' :

				$d->field = 'select';
				$d->attributes = ['group' => TRUE];

				$d->values = function() {

					$values = [];

					foreach(self::getCategories() as ['label' => $label, 'productions' => $productions]) {

						$productionsValues = [];

						foreach($productions as $production) {
							$productionsValues[$production] = self::get($production);
						}

						asort($productionsValues);

						$values[] = [
							'label' => $label,
							'values' => $productionsValues
						];

					}

					$otherValues = [];

					foreach(Production::OTHER as $production) {
						$otherValues[$production] = self::get($production);
					}

					$values[] = [
						'label' => '* * *',
						'values' => $otherValues
					];

					return $values;

				};

				break;

			case 'place' :
				$d->autocompleteBody = function(\util\FormUi $form, Structure $e) {
					return [
						'structure' => $e['id']
					];
				};
				new \main\PlaceUi()->query($d);
				break;

			case 'placeLngLat' :
				$d->field = NULL;
				break;

			case 'area' :
				$d->append = s("ha");
				break;

			case 'areaGreenhouse' :
				$d->append = s("m²");
				break;

			case 'description' :
				$d->placeholder = s("Description de la production");
				$d->attributes['style'] = 'width: 20rem';
				break;

			case 'organic' :
				$d->field = 'select';
				$d->values = StructureUi::getOrganic();
				$d->required = TRUE;
				break;

			case 'mechanisation' :
				$d->field = 'select';
				$d->values = [
					Production::MANUAL => 'Travail manuel',
					Production::WALKING_TRACTOR => 'Motoculteur',
					Production::TRACTOR => 'Tracteur'
				];
				break;

		}

		return $d;

	}

}
