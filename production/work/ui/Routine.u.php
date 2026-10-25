<?php
namespace work;

class RoutineUi {

	public function __construct() {

		\Asset::css('work', 'routine.css');
		\Asset::js('work', 'routine.js');

	}

	public static function list(): array {

		\Asset::css('work', 'routine.css');

		return [
			'fertilizer' => [
				'name' => s("Intrant"),
				'label' => s("Composition"),
				'summary' => fn() => '',
				'td' => fn(Tool $eTool) => self::getTdFertilizer($eTool),
				'th' => fn() => self::getThFertilizer(),
				'field' => function($form, Tool $eTool) {
					$default = $eTool['routineName'] === 'fertilizer' ? $eTool['routineValue'] : NULL;
					return new RoutineUi()->getFieldFertilizer($form, 'routineValue', $default);
				},
				'nameField' => s("Nom de l'intrant"),
				'nameInField' => FALSE,
				'pageTitle' => fn(\structure\Structure $eStructure) => s("Les intrants de {value}", $eStructure['name']),
				'title' => s("Intrants"),
				'nothing' => s("Vous n'avez pas encore ajouté d'intrant à votre ferme. Ajouter votre gamme d'intrants peut être très utile pour que {siteName} puisse vous indiquer les quantités à épandre sur vos planches !"),
				'createButton' => s("Nouvel intrant"),
				'createTitle' => s("Ajouter un nouvel intrant"),
				'updateTitle' => s("Modifier l'intrant"),
				'deleteConfirm' => s("Supprimer cet intrant ?"),
				'dropdownUpdate' => s("Modifier l'intrant"),
				'dropdownDisable' => s("Ne plus utiliser l'intrant"),
				'dropdownEnable' => s("Réutiliser l'intrant"),
				'dropdownDelete' => s("Supprimer l'intrant"),
				'convertButton' => s("C'est un intrant"),
				'tabActive' => s("Intrant utilisés"),
				'tabInactive' => s("Intrant inutilisés"),
				'empty' => s("Vous n'avez pas encore ajouté d'intrant."),
			],
			'phyto' => [
				'name' => s("Produit de traitement"),
				'label' => NULL,
				'summary' => fn(Tool $eTool) => encode($eTool['routineValue']['functions'] ?? ''),
				'list' => fn(\Collection $cTool) => new RoutineUi()->getListPhyto($cTool),
				'field' => fn($form, Tool $eTool) => new RoutineUi()->getFieldPhyto($form, $eTool),
				'nameField' => s("Nom du produit"),
				'nameInField' => TRUE, // Le nom vient du catalogue, ou se saisit à côté de lui
				'pageTitle' => fn(\structure\Structure $eStructure) => s("Les produits de traitement de {value}", $eStructure['name']),
				'title' => s("Produits de traitement"),
				'nothing' => s("Vous n'avez pas encore ajouté de produit de traitement. Chaque produit a sa dose par défaut, et les traitements notés avec lui entrent au registre phytosanitaire avec la quantité à préparer."),
				'createButton' => s("Nouveau produit"),
				'createTitle' => s("Ajouter un produit de traitement"),
				'updateTitle' => s("Modifier le produit de traitement"),
				'deleteConfirm' => s("Supprimer ce produit de traitement ?"),
				'dropdownUpdate' => s("Modifier le produit"),
				'dropdownDisable' => s("Ne plus utiliser le produit"),
				'dropdownEnable' => s("Réutiliser le produit"),
				'dropdownDelete' => s("Supprimer le produit"),
				'convertButton' => s("C'est un produit de traitement"),
				'tabActive' => s("Produits utilisés"),
				'tabInactive' => s("Produits inutilisés"),
				'empty' => s("Vous n'avez pas encore ajouté de produit de traitement."),
			],
			'sprayer' => [
				'name' => s("Pulvérisateur"),
				'label' => s("Contenance de la cuve"),
				'summary' => fn(Tool $eTool) => s("Cuve de {value} L", \util\TextUi::number($eTool['routineValue']['capacity'], NULL)),
				'field' => function($form, Tool $eTool) {
					$default = $eTool['routineName'] === 'sprayer' ? $eTool['routineValue'] : NULL;
					return new RoutineUi()->getFieldSprayer($form, 'routineValue', $default);
				},
			],
			'tray' => [
				'name' => s("Plateau de semis"),
				'label' => s("Nombre de mottes par plateau"),
				'summary' => fn(Tool $eTool) => new RoutineUi()->getSummaryTray($eTool),
				'field' => function($form, Tool $eTool) {
					$default = $eTool['routineName'] === 'tray' ? $eTool['routineValue'] : NULL;
					return new RoutineUi()->getFieldTray($form, 'routineValue', $default);
				},
			]
		];

	}

	public static function get(string $name): array {

		return self::list()[$name] ?? throw new \Exception('Unknown routine "'.$name.'"');

	}

	public static function getProperty(string $name, string $property): mixed {

		return self::get($name)[$property] ?? throw new \Exception('Unknown property "'.$name.'" for routine "'.$name.'"');

	}

	public function getRoutinesGroup(\util\FormUi $form, Tool $eTool, array $routines): string {

		$h = '<div data-ref="routines" data-structure="'.$eTool['structure']['id'].'">';
			if($eTool['routineName'] !== NULL) {
				$h .= $this->getFields($routines, $eTool);
			}
		$h .= '</div>';

		return $h;

	}

	public function getFields(array $routines, Tool $eTool): string {

		$eTool->expects(['routineName', 'routineValue']);

		$isStandalone = (array_intersect(array_keys(RoutineLib::getByStandalone(TRUE)), $routines) !== []);

		if($isStandalone) {

			if(count($routines) > 1) {
				throw new \Exception('Only one standalone routine expected');
			} else {
				return $this->getField(new \util\FormUi(), first($routines), $eTool);
			}

		} else if($routines) {

			$form = new \util\FormUi();

			$values = [];
			foreach($routines as $routineName) {
				$values[$routineName] = self::getProperty($routineName, 'name');
			}

			$h = $form->group(
				s("Type de matériel"),
				$form->select('routineName', $values, $eTool['routineName'], [
					'onchange' => 'Routine.select(this)'
				]).
				\util\FormUi::info(s("Laissez vide si aucun type ne correspond à votre saisie"))
			);

			foreach($routines as $routineName) {
				$h .= '<div data-ref="routine-'.$routineName.'" class="'.($eTool['routineName'] === $routineName ? '' : 'hide').'">';
					$h .= $this->getField($form, $routineName, $eTool);
				$h .= '</div>';
			}

			return $h;

		} else {
			return '';
		}

	}

	protected function getField(\util\FormUi $form, string $routineName, Tool $eTool): string {

		$properties = self::get($routineName);
		$field = $properties['field']($form, $eTool);

		$h = '';

		if($field) {

			$h .= $form->inputRadio('routineName', $routineName, selectedValue: $eTool['routineName'], attributes: ['class' => 'hide']);

			// Un champ sans libellé pose lui-même ses groupes.
			if($properties['label'] === NULL) {
				$h .= $field;
			} else {
				$h .= $form->group(
					$properties['label'],
					$field
				);
			}

		}

		return $h;

	}

	public function getFieldSprayer(\util\FormUi $form, string $field, ?array $default): string {

		$default ??= [];

		return $form->inputGroup(
			$form->number($field.'[capacity]', $default['capacity'] ?? '', ['min' => 0.1, 'step' => 0.1]).
			$form->addon(s("L"))
		);

	}

	/**
	 * Le produit se cherche dans le catalogue E-Phy. Une fois choisi, ce que dit le catalogue se lit
	 * et ce qui dépend de la structure se saisit. Un produit absent du catalogue se saisit à la main.
	 * Le champ se réécrit en entier à chaque choix (Tool.phytoRefresh).
	 */
	public function getFieldPhyto(\util\FormUi $form, Tool $eTool): string {

		$eTool->expects(['structure', 'name', 'ePhytoProduct', 'cPhytoUsage', 'phytoManual', 'phytoQuery', 'phytoReprise', 'phytoTasks', 'phytoTasksKept', 'eToolPhytoSame']);

		$value = ($eTool['routineName'] === 'phyto') ? $eTool['routineValue'] : [];
		$ePhytoProduct = $eTool['ePhytoProduct'];
		$eToolSame = $eTool['eToolPhytoSame'];

		if($eTool['phytoReprise']) {
			$h = '<div id="tool-phyto" data-structure="'.$eTool['structure']['id'].'" data-tool="'.$eTool['id'].'">';
			if($eTool['phytoTasks'] > 0 and $eTool['phytoTasksKept'] > 0) {
				$h .= '<p>'.p("{value} traitement recevra ce produit.", "{value} traitements recevront ce produit.", $eTool['phytoTasks']).' '.p("{value} autre intervention le gardera dans son matériel.", "{value} autres interventions le garderont dans leur matériel.", $eTool['phytoTasksKept']).'</p>';
			} else if($eTool['phytoTasks'] > 0) {
				$h .= '<p>'.p("{value} traitement recevra ce produit.", "{value} traitements recevront ce produit.", $eTool['phytoTasks']).'</p>';
			} else if($eTool['phytoTasksKept'] > 0) {
				$h .= '<p>'.p("{value} intervention gardera ce produit dans son matériel.", "{value} interventions garderont ce produit dans leur matériel.", $eTool['phytoTasksKept']).'</p>';
			}
		} else {
			$h = '<div id="tool-phyto" data-structure="'.$eTool['structure']['id'].'">';
		}

		if($ePhytoProduct->empty() and $eTool['phytoManual'] === FALSE) {

			$h .= $form->group(
				s("Produit"),
				$this->getPhytoSearch($form, $eTool).
				'<div class="text-end"><a onclick="Routine.phytoRefresh(this, {manual: 1})" class="util-annotation">'.s("Saisir un produit absent du catalogue E-Phy").'</a></div>'
			);

			$h .= '</div>';

			return $h;

		}

		if($ePhytoProduct->notEmpty()) {

			$h .= $form->hidden('name', $eTool['name'] ?: $ePhytoProduct->getDisplayName());
			$h .= $form->hidden('routineValue[amm]', $ePhytoProduct['amm']);

			$picked = '<div>';
				$picked .= '<b>'.encode($ePhytoProduct->getDisplayName()).'</b> ';
				$picked .= '<a onclick="Routine.phytoRefresh(this, {})" class="util-annotation">'.s("changer").'</a>';
			$picked .= '</div>';
			$picked .= '<div class="util-annotation">'.implode(' · ', self::getPhytoFacts($ePhytoProduct['amm'], $ePhytoProduct['functions'], $ePhytoProduct['formulation'], $ePhytoProduct['organic'])).'</div>';

			if($eToolSame->notEmpty()) {
				$picked .= '<div class="mt-1">'.s("{value} est déjà dans vos produits", encode($eToolSame['name'])).'</div>';
			}

			$h .= $form->group(s("Produit"), $picked);

			// Le produit déjà enregistré garde sa dose, que reçoivent les traitements (ToolLib::update()).
			if($eToolSame->notEmpty()) {

				foreach(['dose', 'unit', 'water'] as $key) {
					$h .= $form->hidden('routineValue['.$key.']', $eToolSame['routineValue'][$key]);
				}

				$h .= '</div>';

				return $h;

			}

			if($eTool['cPhytoUsage']->notEmpty()) {
				$h .= $form->group(s("Doses autorisées sur vos cultures"), $this->getPhytoUsages($eTool['cPhytoUsage']));
			}

		} else {

			$h .= $form->group(
				s("Nom du produit").\util\FormUi::asterisk(),
				$form->text('name', $eTool['name']).
				'<div class="text-end"><a onclick="Routine.phytoRefresh(this, {})" class="util-annotation">'.s("Chercher dans le catalogue E-Phy").'</a></div>',
				['data-wrapper' => 'name']
			);

		}

		$units = array_combine(RoutineLib::PHYTO_UNITS, RoutineLib::PHYTO_UNITS);

		$h .= $form->group(
			s("Dose par défaut").\util\FormUi::asterisk(),
			$form->inputGroup(
				$form->number('routineValue[dose]', $value['dose'] ?? '', ['min' => 0.001, 'step' => 0.001]).
				$form->select('routineValue[unit]', $units, $value['unit'] ?? RoutineLib::PHYTO_UNITS[0], ['required' => TRUE]),
				['class' => 'routine-phyto-dose']
			),
			['data-wrapper' => 'routineValue']
		);

		if($ePhytoProduct->empty() or $ePhytoProduct->isReadyToUse() === FALSE) {

			$h .= $form->group(
				s("Volume de bouillie par défaut"),
				$form->inputGroup(
					$form->number('routineValue[water]', $value['water'] ?? '', ['min' => 1, 'step' => 1]).
					$form->addon(s("L/ha"))
				)
			);

		}

		$h .= '</div>';

		return $h;

	}

	protected function getPhytoSearch(\util\FormUi $form, Tool $eTool): string {

		\Asset::js('work', 'routine.js');

		$attributes = [
			'placeholder' => s("Nom commercial ou numéro d'AMM"),
			'data-phyto-search' => 1
		];

		// Une recherche déjà tapée s'ouvre sur ses résultats : l'autocomplétion interroge à la prise de focus.
		if($eTool['phytoQuery'] !== NULL) {
			$attributes['data-phyto-query'] = $eTool['phytoQuery'];
			$attributes['onrender'] = 'AutocompleteField.start(this); Routine.phytoQuery(this);';
		}

		[
			'query' => $query,
			'results' => $results
		] = $form->autocomplete(
			'phyto',
			'/work/tool:queryPhyto',
			['structure' => $eTool['structure']['id']],
			NULL,
			[],
			FALSE,
			FALSE,
			$attributes
		);

		return $form->inputGroup($form->addon(\Asset::icon('search')).$query).$results;

	}

	/**
	 * Une AMM que le catalogue ne porte plus a été retirée, et le délai pour écouler le stock est
	 * passé. Rien pour un produit saisi hors catalogue, qui n'a pas d'AMM.
	 */
	public static function getPhytoWithdrawn(?string $amm, PhytoProduct $ePhytoProduct): ?string {

		if($amm === NULL) {
			return NULL;
		}

		if($ePhytoProduct->empty()) {
			return s("AMM retirée");
		}

		return match($ePhytoProduct['status']) {
			PhytoProduct::AUTHORIZED => NULL,
			PhytoProduct::WITHDRAWN => ($ePhytoProduct['usableUntil'] === NULL) ? s("AMM retirée") : s("AMM retirée, utilisable jusqu'au {value}", \util\DateUi::numeric($ePhytoProduct['usableUntil'], \util\DateUi::DATE))
		};

	}

	public static function getPhytoManualAutocomplete(string $query): array {

		return [
			'class' => 'autocomplete-link',
			'value' => 'manual',
			'itemHtml' => \Asset::icon('pencil').'<div>'.s("Saisir « {value} » hors catalogue", encode($query)).'</div>',
			'itemText' => $query
		];

	}

	public static function getPhytoAutocomplete(PhytoProduct $ePhytoProduct): array {

		$withdrawn = self::getPhytoWithdrawn($ePhytoProduct['amm'], $ePhytoProduct);

		$item = '<div>';
			$item .= '<div>'.encode($ePhytoProduct->getDisplayName()).'</div>';
			$item .= '<div class="util-annotation">'.implode(' · ', self::getPhytoFacts($ePhytoProduct['amm'], $ePhytoProduct['functions'], $ePhytoProduct['formulation'], $ePhytoProduct['organic'])).'</div>';
			if($ePhytoProduct['otherNames']) {
				$item .= '<div class="util-annotation">'.s("Aussi vendu sous {value}", encode(implode(', ', $ePhytoProduct['otherNames']))).'</div>';
			}
			if($withdrawn !== NULL) {
				$item .= '<div class="util-annotation color-warning">'.$withdrawn.'</div>';
			}
		$item .= '</div>';

		return [
			'value' => $ePhytoProduct['amm'],
			'itemHtml' => $item,
			'itemText' => $ePhytoProduct->getDisplayName()
		];

	}

	public static function getPhytoFacts(?string $amm, ?string $functions, ?string $formulation, bool $organic): array {

		$facts = [];

		if($amm !== NULL) {
			$facts[] = s("AMM {value}", $amm);
		}

		if($functions !== NULL) {
			$facts[] = encode($functions);
		}

		if($formulation !== NULL) {
			$facts[] = encode($formulation);
		}

		if($organic) {
			$facts[] = \structure\StructureUi::getOrganicLogo(\structure\Structure::YES, '1rem');
		}

		return $facts;

	}

	public function getPhytoUsages(\Collection $cPhytoUsage, bool $plants = FALSE): string {

		$h = '<div class="util-table util-table-even routine-phyto-usages">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Culture").'</div>';
				$h .= '<div>'.s("Contre").'</div>';
				$h .= '<div class="text-end">'.s("Dose").'</div>';
				$h .= '<div class="text-end">'.s("Avant récolte").'</div>';
			$h .= '</div>';

			$cropName = NULL;

			foreach($cPhytoUsage as $ePhytoUsage) {

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						if($ePhytoUsage['cropName'] !== $cropName) {
							if($ePhytoUsage['cropPlant'] !== NULL and \plant\PlantUi::hasVignette($ePhytoUsage['cropPlant'])) {
								$h .= \plant\PlantUi::getVignette(new \plant\Plant(['fqn' => $ePhytoUsage['cropPlant']]), '1.25rem').' ';
							}
							$h .= encode($ePhytoUsage['cropName']);
							if($plants) {
								$h .= '<div class="util-annotation">'.encode($this->getPhytoPlants($ePhytoUsage['cPlant'])).'</div>';
							}
						}
					$h .= '</div>';
					$h .= '<div>'.encode(implode(', ', $ePhytoUsage['targets'])).'</div>';
					$h .= '<div class="text-end">'.($ePhytoUsage['dose'] === NULL ? '' : \util\TextUi::number($ePhytoUsage['dose'], NULL).' '.encode($ePhytoUsage['doseUnit'] ?? '')).'</div>';
					$h .= '<div class="text-end">'.($ePhytoUsage['harvestDelay'] === NULL ? '' : p("{value} jour", "{value} jours", $ePhytoUsage['harvestDelay'])).'</div>';
				$h .= '</div>';

				$cropName = $ePhytoUsage['cropName'];

			}

		$h .= '</div>';

		return $h;

	}

	protected function getPhytoPlants(\Collection $cPlant): string {

		$names = array_values(array_unique($cPlant->getColumn('name')));
		usort($names, fn($a, $b) => strcmp(toFqn($a), toFqn($b)));

		foreach($names as $position => $name) {
			if($position > 0) {
				$names[$position] = mb_strtolower(mb_substr($name, 0, 1)).mb_substr($name, 1);
			}
		}

		return implode(', ', $names);

	}

	public function getListPhyto(\Collection $cTool): string {

		$h = '<div class="util-table util-table-even util-table-hover routine-phyto-list">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Nom").'</div>';
				$h .= '<div class="hide-xs-down">'.s("AMM").'</div>';
				$h .= '<div class="text-end">'.s("Dose par défaut").'</div>';
				$h .= '<div class="text-end hide-xs-down">'.s("Bouillie").'</div>';
				$h .= '<div class="hide-xs-down">'.s("Agriculture biologique").'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($cTool as $eTool) {

				$value = $eTool['routineValue'];

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						$h .= ToolUi::link($eTool);
						if($value['functions'] !== NULL) {
							$h .= '<div class="util-annotation">'.encode($value['functions']).'</div>';
						}
						$withdrawn = self::getPhytoWithdrawn($value['amm'], $eTool['ePhytoProduct']);
						if($withdrawn !== NULL) {
							$h .= '<div class="util-annotation color-warning">'.$withdrawn.'</div>';
						}
					$h .= '</div>';
					$h .= '<div class="hide-xs-down">'.encode($value['amm'] ?? '').'</div>';
					$h .= '<div class="text-end">'.self::getPhytoDose($value['dose'], $value['unit']).'</div>';
					$h .= '<div class="text-end hide-xs-down">';
						if($value['water'] !== NULL) {
							$h .= s("{value} L/ha", \util\TextUi::number($value['water'], NULL));
						} else if($value['readyToUse']) {
							$h .= '<span class="color-muted">'.s("Prêt à l'emploi").'</span>';
						}
					$h .= '</div>';
					$h .= '<div class="hide-xs-down">'.($value['organic'] ? \structure\StructureUi::getOrganicLogo(\structure\Structure::YES, '1.5rem') : '').'</div>';
					$h .= '<div class="text-end">'.new ToolUi()->getDropdown($eTool, 'phyto').'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public static function getPhytoDose(float $dose, string $unit): string {
		return \util\TextUi::number($dose, NULL).' '.encode($unit);
	}

	public function getSummaryTray(Tool $eTool): string {
		return p("{value} motte par plateau", "{value} mottes par plateau", $eTool['routineValue']['value']);
	}

	public function getFieldTray(\util\FormUi $form, string $field, ?array $default): string {

		$default ??= [];

		$h = $form->inputGroup(
			$form->number($field.'[value]', $default['value'] ?? '', ['min' => 1]).
			$form->addon('mottes')
		);

		return $h;

	}

	public function getFieldFertilizer(\util\FormUi $form, string $field, ?array $default): string {

		$default ??= [];

		$h = '<div class="routine-fertilizer-wrapper routine-fertilizer-wrapper-field">';

			foreach([
				'n' => 'N',
				'p' => 'P',
				'k' => 'K',
				s("+"),
				'mgo' => 'MgO',
        ] as $key => $value) {

				if(is_int($key)) {

					$h .= $value;

				} else {

					$h .= '<div class="routine-fertilizer-element">';
						$h .= '<h5>'.$value.'</h5>';
						$h .= '<div class="routine-fertilizer-value">';
							$h .= $form->number($field.'['.$key.']', $default[$key] ?? '', ['min' => 0.0, 'step' => 0.1]);
						$h .= '</div>';
					$h .= '</div>';

				}

			}

		$h .= '</div>';

		return $h;

	}

	public static function getTdFertilizer(Tool $eTool): string {
		return '<td>'.self::getFertilizerComposition($eTool).'</td>';
	}

	public static function getFertilizerComposition(Tool $eTool): string {

		$h = '<div class="routine-fertilizer-wrapper routine-fertilizer-wrapper-field">';

			foreach([
				'n' => 'N',
				'p' => 'P',
				'k' => 'K',
				s("+"),
				'mgo' => 'MgO',
			] as $key => $value) {

				if(is_int($key)) {

					$h .= $value;

				} else {

					$h .= '<div class="routine-fertilizer-element">';
						$h .= '<h5>'.$value.'</h5>';
						$h .= '<div class="routine-fertilizer-value">';
							$h .= $eTool['routineValue'][$key] ?? '-';
						$h .= '</div>';
					$h .= '</div>';

				}

			}

		$h .= '</div>';

		return $h;

	}

	public static function getThFertilizer(): string {

		$h = '<th>'.s("Composition").'</th>';

		return $h;

	}
	
	
	public function getSeparateFertilizer(array $elements, string $separator = '&nbsp;'): array {

		$major = [];
		$minor = [];

		if(array_filter($elements)) {

			foreach(['n' => 'N', 'p' => 'P', 'k' => 'K'] as $key => $label) {

				if($elements[$key]) {
					$major[] = $elements[$key].$separator.$label;
				}

			}

			foreach(['mgo' => 'MgO'] as $key => $label) {

				if($elements[$key]) {
					$minor[] = $elements[$key].$separator.$label;
				}

			}

		}

		return [$major, $minor];

	}

	public static function getListFertilizer(): array {

		return [
			'n' => 'N',
			'p' => 'P',
			'k' => 'K',
			'mgo' => 'MgO',
		];

	}

}
?>
