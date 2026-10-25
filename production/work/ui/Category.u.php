<?php
namespace work;

class CategoryUi {

	public static function text(\sequence\Flow|series\Task $e): string {

		if($e instanceof \sequence\Flow) {

			$e['variety'] = new \plant\Variety();

			$e->expects(['sequence']);

		} else {
			$e->expects(['series']);
		}

		$e->expects([
			'plant',
			'category' => ['name'],
			'variety',
			'description'
		]);

		$ePlant = $e['plant'];

		$eCategory = $e['category'];
		$eCategory->expects(['name']);

		$h = '<span class="category-text">';

		if($ePlant->empty()) {

			$h .= encode($eCategory['name']);

			if(
				($e instanceof series\Task and $e['series']->notEmpty()) or
				($e instanceof \sequence\Flow and $e['sequence']->notEmpty())
			) {
				$h .= ' <span class="category-name">'.s("PARTAGÉ").'</span>';
			}

		} else {

			$ePlant->expects(['name']);

			$plant = '<span class="category-name">'.encode($ePlant['name']).'</span>';

			$arguments = [
				'category' => encode($eCategory['name']),
				'plant' => $plant
			];

			if($eCategory['fqn'] === 'other') {

				if($e['description'] === NULL) {
					$h .= s("{category} de {plant}", $arguments);
				} else {
					$h .= $arguments['plant'];
				}

			} else {
				$h .= s("{category} de {plant}", $arguments);
			}

		}

		$h .= '</span>';

		return $h;

	}

	public function getManageTitle(\structure\Structure $eStructure, \Collection $cCategory): string {

		$create = '';

		if($cCategory->count() < WorkSetting::CATEGORIES_LIMIT) {
			$create = '<a href="/work/category:create?structure='.$eStructure['id'].'" class="btn btn-secondary">'.\Asset::icon('plus-circle').'<span class="hide-xs-down"> '.s("Nouvelle catégorie").'</span></a>';
		}

		return \main\DesignUi::getPageHeader(
			s("Catégories"),
			back: '/work/action:manage?structure='.$eStructure['id'],
			create: $create
		);

	}

	public function getManage(\structure\Structure $eStructure, \Collection $cCategory): string {

		$h = '<p class="util-info">';
			$h .= p("Vous avez actuellement configuré {value} catégorie sur {max} possibles.", "Vous avez actuellement configuré {value} catégories sur {max} possibles.", $cCategory->count(), ['max' => WorkSetting::CATEGORIES_LIMIT]);
		$h .= '</p>';

		$h .= '<br/>';

		$h .= '<table class="tr-even">';
			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th colspan="2">'.s("Position").'</th>';
					$h .= '<th>'.self::p('name')->label.'</th>';
					$h .= '<th></th>';
				$h .= '</tr>';
			$h .= '</thead>';

			$h .= '<tbody>';

			foreach($cCategory as $eCategory) {

				$h .= '<tr>';
					$h .= '<td class="td-min-content">';
						$h .= '<b>'.$eCategory['position'].'.</b>';
					$h .= '</td>';
					$h .= '<td class="td-min-content">';

						if($eCategory['position'] > 1) {
							$h .= '<a data-ajax="/work/category:doIncrementPosition" post-id="'.$eCategory['id'].'" post-increment="-1" class="btn btn-only-icon btn-sm btn-secondary">'.\Asset::icon('arrow-up').'</a> ';
						} else {
							$h .= '<a class="btn btn-only-icon btn-sm disabled">'.\Asset::icon('arrow-up').'</a> ';
						}

						if($eCategory['position'] !== $cCategory->count()) {
							$h .= '<a data-ajax="/work/category:doIncrementPosition" post-id="'.$eCategory['id'].'" post-increment="1" class="btn btn-only-icon btn-sm btn-secondary">'.\Asset::icon('arrow-down').'</a> ';
						} else {
							$h .= '<a class="btn btn-only-icon btn-sm disabled">'.\Asset::icon('arrow-down').'</a> ';
						}
					$h .= '</td>';
					$h .= '<td>';
						$h .= encode($eCategory['name']);
					$h .= '</td>';
					$h .= '<td class="text-end" style="white-space: nowrap">';

						$h .= '<a href="/work/category:update?id='.$eCategory['id'].'" class="btn btn-only-icon btn-color-primary">';
							$h .= \Asset::icon('gear-fill');
						$h .= '</a> ';

						if(in_array($eCategory['fqn'], WorkSetting::CATEGORIES_STRUCTURELLES, TRUE) === FALSE) {
							$h .= '<a data-ajax="/work/category:doDelete" data-confirm="'.s("Supprimer cette catégorie ?").'" post-id="'.$eCategory['id'].'" class="btn btn-only-icon btn-outline-primary">';
								$h .= \Asset::icon('trash-fill');
							$h .= '</a>';
						} else {
							$h .= '<div class="btn btn-outline-primary disabled" title="'.s("Catégorie indispensable au bon fonctionnement de {siteName}").'">';
								$h .= \Asset::icon('trash-fill');
							$h .= '</div>';
						}

					$h .= '</td>';
				$h .= '</tr>';
			}
			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

	public function create(\structure\Structure $eStructure): \Panel {

		$eCategory = new Category();

		$form = new \util\FormUi();

		$h = $form->openAjax('/work/category:doCreate');

			$h .= $form->asteriskInfo();

			$h .= $form->hidden('structure', $eStructure['id']);
			$h .= $form->dynamicGroups($eCategory, ['name*']);
			$h .= $form->group(
				content: $form->submit(s("Ajouter"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-category-create',
			title: s("Ajouter une nouvelle catégorie"),
			body: $h,
			close: 'reload'
		);

	}

	public function update(Category $eCategory): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/work/category:doUpdate');

			$h .= $form->hidden('id', $eCategory['id']);
			$h .= $form->dynamicGroups($eCategory, ['name']);
			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-category-update',
			title: s("Modifier une catégorie"),
			body: $h,
			close: 'reload'
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Category::model()->describer($property, [
			'name' => s("Nom de la catégorie"),
			'fqn' => s("Nom qualifié")
		]);

		switch($property) {

		}

		return $d;

	}


}
?>
