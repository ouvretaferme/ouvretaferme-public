<?php
namespace work;

class ToolUi {

	public function __construct() {

		\Asset::css('structure', 'tool.css');

	}

	public static function link(Tool $eTool, bool $newTab = FALSE): string {

		$eTool->expects(['id', 'name']);

		return '<a href="'.self::url($eTool).'" '.($newTab ? 'target="_blank"' : '').'>'.encode($eTool['name']).'</a>';

	}

	public static function url(Tool $eTool): string {

		$eTool->expects(['id']);

		return '/outil/'.$eTool['id'];

	}

	public static function urlManage(\structure\Structure $eStructure, ?string $routineName = NULL): string {

		return '/work/tool:manage?structure='.$eStructure['id'].''.($routineName ? '&routineName='.$routineName : '');

	}

	public function query(\PropertyDescriber $d, bool $multiple = FALSE) {

		$d->prepend = \Asset::icon('tools');
		$d->field = 'autocomplete';

		$d->placeholder ??= s("Ajoutez du matériel");
		$d->multiple = $multiple;
		$d->wrapper = 'tool';

		$d->autocompleteUrl = '/work/tool:query';
		$d->autocompleteResults = function(Tool|\Collection $e) {
			return self::getAutocomplete($e);
		};

		$d->attributes = [
			'data-autocomplete-id' => 'tool'
		];

	}

	public function queryProduct(\PropertyDescriber $d) {

		\Asset::js('work', 'routine.js');

		$d->prepend = \Asset::icon('search');
		$d->field = 'autocomplete';

		$d->autocompleteUrl = '/work/tool:queryProduct';
		$d->autocompleteResults = function(Tool $e) {
			return self::getProductAutocomplete($e);
		};

		$d->attributes = [
			'data-autocomplete-id' => 'product'
		];

	}

	public static function getAutocompleteTitle(string $title): array {

		return [
			'type' => 'title',
			'itemHtml' => '<div>'.encode($title).'</div>',
			'itemText' => $title,
		];

	}

	public static function getAutocompleteOthers(int $others): array {

		$text = p("+ {value} autre", "+ {value} autres", $others);

		return [
			'type' => 'disabled',
			'itemHtml' => '<div>'.$text.'</div>',
			'itemText' => $text,
		];

	}

	public static function getProductAutocomplete(Tool $eTool, ?string $withdrawn = NULL): array {

		if($eTool['routineName'] !== 'phyto') {
			return self::getAutocomplete($eTool);
		}

		$value = $eTool['routineValue'];

		$item = '<div>';
			$item .= '<div>'.encode($eTool['name']).'</div>';
			$item .= '<div class="util-annotation">'.implode(' · ', RoutineUi::getPhytoFacts($value['amm'], $value['functions'], $value['formulation'], $value['organic'])).'</div>';
			if($withdrawn !== NULL) {
				$item .= '<div class="util-annotation color-warning">'.$withdrawn.'</div>';
			}
		$item .= '</div>';

		return [
			'value' => $eTool['id'],
			'itemHtml' => $item,
			'itemText' => $eTool['name'],
			'treatment' => [
				'dose' => $value['dose'],
				'unit' => $value['unit'],
				'water' => $value['water'],
				'readyToUse' => $value['readyToUse']
			]
		];

	}

	public static function getProductCreateAutocomplete(string $query): array {

		return [
			'class' => 'autocomplete-link',
			'value' => 'new',
			'itemHtml' => \Asset::icon('plus-circle').'<div>'.s("Créer « {value} »", encode($query)).'</div>',
			'itemText' => $query
		];

	}

	public static function getCatalogAutocomplete(PhytoProduct $ePhytoProduct): array {

		return [
			'value' => 'amm-'.$ePhytoProduct['amm'],
			'treatment' => $ePhytoProduct['treatment']
		] + RoutineUi::getPhytoAutocomplete($ePhytoProduct);

	}

	public static function getAutocomplete(Tool $eTool): array {

		\Asset::css('media', 'media.css');

		$item = self::getVignette($eTool, '3.5rem', '2.5rem');
		$item .= '<div>'.encode($eTool['name']).'</div>';

		return [
			'value' => $eTool['id'],
			'itemHtml' => $item,
			'itemText' => $eTool['name']
		];

	}

	public static function getVignette(Tool $eTool, string $width, string $height): string {

		$eTool->expects(['id', 'vignette', 'routineName']);

		$ui = new \media\ToolVignetteUi();

		// min(…, 100%) ne se résout pas dans une piste auto ou une boîte flexible : la vignette y compte pour la largeur de son icône, puis s'écrase.
		$style = str_ends_with($width, '%') ? '' : 'width: '.$width.'; max-width: 100%; ';
		$style .= 'border-radius: var(--radius); ';

		if($eTool['vignette'] === NULL) {
			$style .= 'background-color: var(--background-light); color: var(--muted); font-size: '.new \media\ToolVignetteUi()->getFactorSize(str_ends_with($width, '%') ? $height : $width, 0.45);
			$content = \Asset::icon($eTool['routineName'] === 'phyto' ? 'capsule' : 'tools');
		} else {

			$format = $ui->convertToFormat(str_ends_with($width, '%') ? $height : $width);
			$style .= 'background-color: white; background-image: url('.$ui->getUrlByElement($eTool, $format).');';
			$content = '';

		}

		return '<div class="media-rectangle-view" style="'.$ui->getRectangleCss($width, $height).'; '.$style.'">'.$content.'</div>';

	}

	public function getList(\Collection $cTool): string {

		$h = '<div class="tool-list">';
			foreach($cTool as $eTool) {
				$h .= '<a href="'.\work\ToolUi::url($eTool).'" class="tool-list-item">';
					$h .= \work\ToolUi::getVignette($eTool, '2.7rem', '1.8rem');
					$h .= encode($eTool['name']);
				$h .= '</a>';
			}
		$h .= '</div>';

		return $h;

	}

	public function display(\structure\Structure $eStructure, Tool $eTool): \Panel {

		\Asset::css('work', 'routine.css');

		$h = '<div class="util-vignette">';

			$h .= self::getVignette($eTool, width: '8rem', height: '6rem');

			$h .= '<dl class="util-presentation util-presentation-1">';

				if($eTool['action']->notEmpty()) {
					$h .= '<dt>'.s("Intervention").'</dt>';
					$h .= '<dd>'.encode($eTool['action']['name']).'</dd>';
				}

				if($eTool['routineName'] === 'phyto') {
					$h .= $this->getPhytoPresentation($eTool);
				}

				if(
					$eTool['stock'] and
					$eTool['followed'] === FALSE
				) {
					$h .= '<dt>'.s("En stock").'</dt>';
					$h .= '<dd>'.$eTool['stock'].'</dd>';
				}

				if($eTool['comment'] !== NULL) {
					$h .= '<dt>'.s("Observations").'</dt>';
					$h .= '<dd>'.encode($eTool['comment']).'</dd>';
				}

			$h .= '</dl>';

		$h .= '</div>';

		if($eTool['routineName'] === 'phyto' and $eTool['cPhytoUsage']->notEmpty()) {
			$h .= '<h3 class="mt-2">'.s("Doses autorisées").'</h3>';
			$h .= new RoutineUi()->getPhytoUsages($eTool['cPhytoUsage']);
		}

		if($eTool['routineName'] === 'fertilizer' and array_filter($eTool['routineValue'])) {
			$h .= '<h3 class="mt-2">'.s("Composition").'</h3>';
			$h .= RoutineUi::getFertilizerComposition($eTool);
		}

		return new \Panel(
			id: 'panel-tool-display',
			title: encode($eTool['name']),
			body: $h
		);

	}

	protected function getPhytoPresentation(Tool $eTool): string {

		$value = $eTool['routineValue'];

		$h = '';

		if($value['amm'] !== NULL) {

			$withdrawn = RoutineUi::getPhytoWithdrawn($value['amm'], $eTool['ePhytoProduct']);

			$h .= '<dt>'.s("AMM").'</dt>';
			$h .= '<dd>';
				$h .= encode($value['amm']);
				if($withdrawn !== NULL) {
					$h .= '<div class="util-annotation color-warning">'.$withdrawn.'</div>';
				}
			$h .= '</dd>';

		}

		if($value['functions'] !== NULL) {
			$h .= '<dt>'.s("Fonction").'</dt>';
			$h .= '<dd>'.encode($value['functions']).'</dd>';
		}

		if($value['formulation'] !== NULL) {
			$h .= '<dt>'.s("Formulation").'</dt>';
			$h .= '<dd>'.encode($value['formulation']).'</dd>';
		}

		if($value['organic']) {
			$h .= '<dt>'.s("Agriculture biologique").'</dt>';
			$h .= '<dd>'.\structure\StructureUi::getOrganicLogo(\structure\Structure::YES, '1.5rem').'</dd>';
		}

		$h .= '<dt>'.s("Dose par défaut").'</dt>';
		$h .= '<dd>'.RoutineUi::getPhytoDose($value['dose'], $value['unit']).'</dd>';

		if($value['water'] !== NULL) {
			$h .= '<dt>'.s("Bouillie").'</dt>';
			$h .= '<dd>'.s("{value} L/ha", \util\TextUi::number($value['water'], NULL)).'</dd>';
		} else if($value['readyToUse']) {
			$h .= '<dt>'.s("Bouillie").'</dt>';
			$h .= '<dd><span class="color-muted">'.s("Prêt à l'emploi").'</span></dd>';
		}

		return $h;

	}

	public function getManageTitle(\structure\Structure $eStructure, ?string $routineName, array $tools, \Collection $cTool, \Search $search): string {

		$title = $routineName ? RoutineUi::getProperty($routineName, 'title') : s("Matériel");

		$back = \structure\StructureUi::urlSettingsProduction($eStructure);

		if($this->isEmpty($tools, $cTool, $search)) {
			return \main\DesignUi::getPageHeader($title, back: $back);
		}

		$searchTool = '';

		if($routineName === NULL) {
			$searchTool = '<a '.attr('onclick', 'Lime.Search.toggle("#tool-search")').' class="btn btn-only-icon btn-outline-secondary">'.\Asset::icon('search').'</a>';
		}

		return \main\DesignUi::getPageHeader(
			$title,
			back: $back,
			tools: $searchTool,
			create: '<a href="/work/tool:create?structure='.$eStructure['id'].($routineName ? '&routineName='.$routineName : '').'" class="btn btn-secondary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.($routineName ? RoutineUi::getProperty($routineName, 'createButton') : s("Nouveau matériel")).'</span></a>'
		);

	}

	public function getManage(\structure\Structure $eStructure, ?string $routineName, array $tools, \Collection $cTool, Tool $eToolNew, \Collection $cActionUsed, \Search $search): string {

		if($this->isEmpty($tools, $cTool, $search)) {

			$h = '<div class="util-block-info">';
				$h .= ($routineName ? RoutineUi::getProperty($routineName, 'nothing') : s("Vous n'avez pas encore ajouté de matériel. Ajouter du matériel peut être très utile pour suivre les stocks et indiquer le matériel à utiliser pour les interventions !"));
			$h .= '</div>';

			$h .= '<h4>'.($routineName ? RoutineUi::getProperty($routineName, 'createTitle') : s("Ajouter un matériel")).'</h4>';

			$h .= $this->createForm($eToolNew, 'inline');

		} else {

			$h = '';

			if($tools[Tool::INACTIVE] > 0) {

				$tabs = '<a href="'.self::urlManage($eStructure, $routineName).$search->toQuery(['status'], '&').'" class="tab-item '.($search->get('status') === Tool::ACTIVE ? 'selected' : '').'"><span>'.($routineName ? RoutineUi::getProperty($routineName, 'tabActive') : s("Matériel activé")).' <span class="util-counter util-counter-solid">'.$tools[Tool::ACTIVE].'</span></span></a>';
				$tabs .= '<a href="'.self::urlManage($eStructure, $routineName).$search->toQuery(['status'], '&').'&status='.Tool::INACTIVE.'" class="tab-item '.($search->get('status') === Tool::INACTIVE ? 'selected' : '').'"><span>'.($routineName ? RoutineUi::getProperty($routineName, 'tabInactive') : s("Matériel désactivé")).' <span class="util-counter util-counter-solid">'.$tools[Tool::INACTIVE].'</span></span></a>';

				$h .= \main\DesignUi::getPageTabs($tabs);

			}

			if($routineName === NULL) {
				$h .= $this->getSearch($eStructure, $cActionUsed, $search);
			}

			if($cTool->empty()) {

				$h .= '<div class="util-info">';
					$h .= ($routineName ? RoutineUi::getProperty($routineName, 'empty') : s("Vous n'avez pas encore ajouté de matériel."));
				$h .= '</div>';

			} else {

				if($routineName !== NULL and array_key_exists('list', RoutineUi::get($routineName))) {
					return $h.RoutineUi::getProperty($routineName, 'list')($cTool);
				}

				$h .= '<div class="util-overflow-sm">';

					$h .= '<table class="tr-even">';
						$h .= '<thead>';
							$h .= '<tr>';
								$h .= '<th></th>';
								$h .= '<th>'.s("Nom").'</th>';

								if($routineName) {
									$h .= RoutineUi::getProperty($routineName, 'th')();
								} else {
									$h .= '<th>'.s("Intervention").'</th>';
								}

								$h .= '<th class="text-center">'.s("En stock").'</th>';
								$h .= '<th></th>';
							$h .= '</tr>';
						$h .= '</thead>';

						$h .= '<tbody>';

						foreach($cTool as $eTool) {

							$h .= '<tr>';
								$h .= '<td class="td-min-content">';
									$h .= new \media\ToolVignetteUi()->getCamera($eTool, width: '4.8rem', height: '3.6rem');
								$h .= '</td>';
								$h .= '<td>';
									$h .= ToolUi::link($eTool);
									if($eTool['routineName'] !== NULL) {
										$summary = RoutineUi::getProperty($eTool['routineName'], 'summary')($eTool);
										if($summary) {
											$h .= '<div class="color-muted">';
												$h .= $summary;
											$h .= '</div>';
										}
									}
								$h .= '</td>';

								if($routineName) {
									$h .= RoutineUi::getProperty($routineName, 'td')($eTool);
								} else {

									$h .= '<td>';
										if($eTool['action']->empty()) {
										   $action = '<i>'.s("Matériel polyvalent").'</i>';
										} else {
											$action = encode($eTool['action']['name']);
										}
										$h .= $eTool->quick('action', $action);
									$h .= '</td>';

								}
								$h .= '<td class="text-center">';
									if($eTool['followed']) {
										$h .= '-';
									} else {
										$h .= $eTool->quick('stock', $eTool['stock'] ?? '-');
									}
								$h .= '</td>';
								$h .= '<td class="text-end">';

									$h .= $this->getDropdown($eTool, $routineName);

								$h .= '</td>';
							$h .= '</tr>';
						}
						$h .= '</tbody>';
					$h .= '</table>';

				$h .= '</div>';

			}

		}

		return $h;

	}

	public function getDropdown(Tool $eTool, ?string $routineName): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.encode($eTool['name']).'</div>';

				$h .= '<a href="/work/tool:update?id='.$eTool['id'].'" class="dropdown-item">';
					$h .= ($routineName ? RoutineUi::getProperty($routineName, 'dropdownUpdate') : s("Modifier le matériel"));
				$h .= '</a> ';

				$h .= match($eTool['status']) {

					Tool::ACTIVE => '<a data-ajax="/work/tool:doUpdateStatus" post-id="'.$eTool['id'].'" post-status="'.Tool::INACTIVE.'" class="dropdown-item">'.($routineName ? RoutineUi::getProperty($routineName, 'dropdownDisable') : s("Désactiver le matériel")).'</a>',
					Tool::INACTIVE => '<a data-ajax="/work/tool:doUpdateStatus" post-id="'.$eTool['id'].'" post-status="'.Tool::ACTIVE.'" class="dropdown-item">'.($routineName ? RoutineUi::getProperty($routineName, 'dropdownEnable') : s("Réactiver le matériel")).'</a>'

				};

				$h .= '<div class="dropdown-divider"></div>';

				$h .= '<a data-ajax="/work/tool:doDelete" data-confirm="'.($routineName ? RoutineUi::getProperty($routineName, 'deleteConfirm') : s("Supprimer ce matériel ?")).'" post-id="'.$eTool['id'].'" class="dropdown-item">';
					$h .= ($routineName ? RoutineUi::getProperty($routineName, 'dropdownDelete') : s("Supprimer le matériel"));
				$h .= '</a>';

		$h .= '</div>';

		return $h;

	}

	protected function isEmpty(array $tools, \Collection $cTool, \Search $search): string {

		return (
			$cTool->empty() and
			$search->empty(['status']) and
			$tools[Tool::INACTIVE] === 0
		);

	}

	public function getSearch(\structure\Structure $eStructure, \Collection $cActionUsed, \Search $search): string {

		$h = '<div id="tool-search" class="util-block-search '.($search->empty(['status']) ? 'hide' : '').'">';

			$form = new \util\FormUi();

			$h .= $form->openAjax('/work/tool:manage', ['method' => 'get', 'class' => 'util-search']);
				$h .= $form->hidden('structure', $eStructure['id']);

				$h .= '<fieldset>';
					$h .= '<legend>'.s("Nom").'</legend>';
					$h .= $form->text('name', $search->get('name'), ['placeholder' => s("Nom du matériel")]);
				$h .= '</fieldset>';
				$h .= '<fieldset>';
					$h .= '<legend>'.s("Intervention").'</legend>';
					$h .= $form->select('action', $cActionUsed, $search->get('action'));
				$h .= '</fieldset>';
				$h .= '<div class="util-search-submit">';

					$h .= $form->submit(s("Chercher"));
					$h .= '<a href="'.self::urlManage($eStructure).'" class="btn btn-only-icon">'.\Asset::icon('x-lg').'</a>';

				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	public function create(Tool $eTool): \Panel {

		return new \Panel(
			id: 'panel-tool-create',
			title: $eTool->getStandaloneRoutine('createTitle') ?? s("Ajouter un nouveau matériel"),
			body: $this->createForm($eTool, 'panel'),
			close: 'reload'
		);

	}

	public function createForm(Tool $eTool, string $origin): string {

		$eTool->expects([
			'structure',
			'routineName'
		]);

		$form = new \util\FormUi();

		$h = $form->openAjax('/work/tool:doCreate', ['data-ajax-origin' => $origin]);

			$h .= $form->asteriskInfo();

			$h .= $form->hidden('structure', $eTool['structure']['id']);

			if($eTool->getStandaloneRoutine('nameInField') !== TRUE) {
				$h .= $form->dynamicGroup($eTool, 'name*', function(\PropertyDescriber $d) use($eTool) {
					$d->label = $eTool->getStandaloneRoutine('nameField') ?? $d->label;
				});
			}

			if($eTool->isStandaloneRoutine()) {
				$h .= $form->hidden('action', $eTool['action']);
			} else {
				$h .= $form->dynamicGroup($eTool, 'action');
			}

			$h .= new RoutineUi()->getRoutinesGroup($form, $eTool, [$eTool['routineName']]);
			$h .= $form->dynamicGroups($eTool, ['stock', 'comment']);

			$h .= $form->group(
				content: $form->submit(s("Ajouter"))
			);

		$h .= $form->close();

		return $h;

	}

	public function update(Tool $eTool, array $routines): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/work/tool:doUpdate');

			$h .= $form->hidden('id', $eTool['id']);
			$h .= $form->hidden('origin', GET('origin'));

			if($eTool->getStandaloneRoutine('nameInField') !== TRUE) {
				$h .= $form->dynamicGroup($eTool, 'name', function(\PropertyDescriber $d) use($eTool) {
					$d->label = $eTool->getStandaloneRoutine('nameField') ?? $d->label;
				});
			}

			// La photo ne passe pas par ce formulaire : l'appareil photo poste pour son compte.
			$h .= $form->group(
				s("Photo"),
				new \media\ToolVignetteUi()->getCamera($eTool, width: '8rem', height: '6rem')
			);

			// Un matériel qui devient un produit construit encore son action (ToolLib::getPropertiesUpdate()).
			if($eTool->isStandaloneRoutine()) {
				$h .= $form->hidden('action', $eTool['action']);
			} else {
				$h .= $form->dynamicGroup($eTool, 'action');
			}

			if(
				$eTool['routineName'] === NULL and
				$eTool['productRoutine'] !== NULL
			) {
				$h .= $form->group(
					s("Type de matériel"),
					'<a href="/work/tool:update?id='.$eTool['id'].'&routineName='.$eTool['productRoutine'].'" class="btn btn-outline-primary">'.RoutineUi::getProperty($eTool['productRoutine'], 'convertButton').'</a>'
				);
			}

			$h .= new RoutineUi()->getRoutinesGroup($form, $eTool, array_keys($routines));
			if($eTool['followed']) {
				// L'immatriculation suit le compteur coché (`ToolboxMaintenance.changeMeter()`).
				\Asset::css('toolbox', 'maintenance.css');
				\Asset::js('toolbox', 'maintenance.js');
				$h .= $form->dynamicGroup($eTool['eEquipment'], 'meter');
				$h .= $form->dynamicGroup($eTool['eEquipment'], 'registration');
			} else {
				$h .= $form->dynamicGroup($eTool, 'stock');
			}

			$h .= $form->dynamicGroup($eTool, 'comment');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-tool-update',
			title: $eTool->getStandaloneRoutine('updateTitle') ?? s("Modifier le matériel"),
			body: $h,
			close: 'reload'
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Tool::model()->describer($property, [
			'name' => s("Nom du matériel"),
			'action' => s("Lier ce matériel à une intervention"),
			'stock' => s("Quantité en stock à la ferme"),
			'comment' => s("Observations"),
		]);

		switch($property) {

			case 'id' :
				$d->autocompleteBody = function(\util\FormUi $form, Tool $e) {
					$e->expects(['structure']);
					return [
						'structure' => $e['structure']->empty() ? NULL : $e['structure']['id']
					];
				};
				new ToolUi()->query($d);
				break;

			case 'action' :
				\Asset::js('work', 'routine.js');
				$d->placeholder = s("Non, matériel polyvalent");
				$d->values = fn(Tool $e) => $e['cAction'] ?? $e->expects(['cAction']);
				$d->attributes = [
					'onchange' => 'Routine.refresh(this)'
				];
				break;

			case 'stock' :
				$d->append = s("unité(s)");
				break;

		}

		return $d;

	}

}
?>
