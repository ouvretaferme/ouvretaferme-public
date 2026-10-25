<?php
namespace learning;

class ThemeUi {

	const FRIENDS = [
		'laptop' => ['luciole-classique', 'lucane'],
		'leaf' => ['petit-pois', 'feuille-vert'],
		'basket3' => ['abeille', 'papillon-jaune'],
		'piggy-bank' => ['fourmi-ardoise', 'grillon'],
		'compass' => ['libellule', 'sauterelle'],
		'signpost-2' => ['graine-corail', 'poussin'],
		'egg' => ['poule', 'vache'],
		'tree' => ['ver-rose', 'carabe-dore'],
		'award' => ['pomme-vert-fonce', 'fleur-lavande'],
		'house-door' => ['mouton', 'chien'],
		'people' => ['fourmi-ailee', 'osmie'],
		'heart-pulse' => ['coccinelle-rouge', 'escargot'],
	];

	public function __construct() {
		\Asset::lib('util', 'Sortable-1.11.0.js');
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	/**
	 * La couleur d'une pastille tirée d'une thématique, et l'aplat neutre quand il n'y en a pas :
	 * tout ce qui pose une pastille de thématique passe par là, pour n'avoir qu'une façon de dire
	 * « pas de thématique ».
	 */
	public static function getIconClass(Theme $eTheme): string {

		if($eTheme->empty()) {
			return 'learning-icon-neutral';
		}

		return 'util-icon-'.$eTheme->getSection();

	}

	public static function getIcon(Theme $eTheme): string {

		$icon = $eTheme->empty() ? 'mortarboard-fill' : $eTheme['icon'];

		return '<span class="util-icon util-icon-round '.self::getIconClass($eTheme).'">'.\Asset::icon($icon).'</span>';

	}

	/**
	 * L'aplat qui tient lieu de visuel. Sans thématique, il porte l'icône des formations et aucun
	 * libellé : il n'y a pas de nom à écrire, et le gris du socle dit « rien encore », là où une
	 * couleur de section dirait une thématique.
	 */
	public static function getCover(Theme $eTheme, string $class = '', string $badges = '', ?int $friend = NULL): string {

		if($eTheme->empty()) {
			return '<div class="learning-cover learning-cover-neutral '.$class.'">'.$badges.\Asset::icon('mortarboard-fill').'</div>';
		}

		if($friend !== NULL) {
			return '<div class="learning-cover learning-cover-'.$eTheme->getSection().' learning-cover-friend '.$class.'">'.$badges.self::getFriend($eTheme, $friend).'</div>';
		}

		$h = '<div class="learning-cover learning-cover-'.$eTheme->getSection().' '.$class.'">';
			$h .= $badges;
			$h .= \Asset::icon($eTheme['icon']);
			$h .= '<span>'.encode($eTheme['name']).'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'ami d'une thématique (planche catalogue-attractif v3), tiré de son icône : l'icône se choisit
	 * dans `Theme::ICONS` et ne change pas au renommage, là où le nom et l'identifiant varient d'une
	 * base à l'autre. Deux amis par icône, que `$position` alterne pour qu'une rangée de cartes ne
	 * répète pas le même dessin. Aucun oiseau : il est réservé aux fonds de marque.
	 */
	public static function getFriend(Theme $eTheme, int $position): string {

		$friends = self::FRIENDS[$eTheme['icon']];

		return \Asset::svg('main', 'friends/'.$friends[$position % count($friends)].'.svg');

	}

	public function getList(\Collection $cTheme, \Collection $cLevel): string {

		$h = '<div class="util-title">';
			$h .= '<h3>'.s("Thématiques").' <span class="util-counter">'.$cTheme->count().'</span></h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/admin/theme:create" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter une thématique").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= '<div class="util-table util-table-hover learning-table-themes" '.attr('onrender', 'LearningSort.init(this)').' data-sort-url="/learning/admin/theme:doUpdatePositions">';

			$h .= '<div class="util-table-head">';
				$h .= '<div></div>';
				$h .= '<div>'.s("Thématique").'</div>';
				$h .= '<div class="text-end hide-xs-down"></div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($cTheme as $eThemeRow) {

				$h .= '<div class="util-table-row" data-sort-id="'.$eThemeRow['id'].'">';
					$h .= '<div>'.self::getIcon($eThemeRow).'</div>';
					$h .= '<div><b>'.encode($eThemeRow['name']).'</b></div>';
					$h .= '<div class="text-end hide-xs-down color-muted">'.p("{value} formation", "{value} formations", $eThemeRow['courses']).'</div>';
					$h .= '<div class="text-end learning-row-actions">';
						$h .= \main\DesignUi::getDragHandle();
						$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
						$h .= '<div class="dropdown-list">';
							$h .= '<div class="dropdown-title">'.encode($eThemeRow['name']).'</div>';
							$h .= '<a href="/learning/admin/theme:update?id='.$eThemeRow['id'].'" class="dropdown-item">'.s("Modifier la thématique").'</a>';
							$h .= '<a href="/learning/admin/theme:subthemes?id='.$eThemeRow['id'].'" class="dropdown-item">'.s("Sous-thématiques").'</a>';
							if($eThemeRow->assertDelete()) {
								$h .= '<div class="dropdown-divider"></div>';
								$h .= '<a data-ajax="/learning/admin/theme:doDelete" post-id="'.$eThemeRow['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer cette thématique ?").'" data-waiter>'.s("Supprimer la thématique").'</a>';
							} else if($cTheme->count() > 1) {
								$h .= '<a href="/learning/admin/theme:merge?id='.$eThemeRow['id'].'" class="dropdown-item">'.s("Fusionner dans...").'</a>';
							}
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Niveaux").' <span class="util-counter">'.$cLevel->count().'</span></h3>';
			$h .= '<div>';
				$h .= '<a href="/learning/admin/theme:createLevel" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter un niveau").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		$h .= $this->getLevels($cLevel);

		return $h;

	}

	public function create(Theme $eTheme): \Panel {
		return $this->getThemePanel($eTheme, '/learning/admin/theme:doCreate', s("Ajouter une thématique"), s("Ajouter la thématique"));
	}

	public function update(Theme $eTheme): \Panel {
		return $this->getThemePanel($eTheme, '/learning/admin/theme:doUpdate', s("Modifier la thématique"), s("Enregistrer"));
	}

	public function merge(Theme $eTheme): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/admin/theme:doMerge');

			$h .= $form->hidden('id', $eTheme);

			$h .= $form->group(
				s("Thématique à fusionner"),
				'<b>'.encode($eTheme['name']).'</b>'
			);

			$h .= $form->group(
				s("Thématique qui la reçoit"),
				$form->select('target', $eTheme['cTheme'])
			);

			$h .= $form->group(
				content: $form->submit(s("Fusionner"), ['data-confirm' => s("Les formations de cette thématique passeront dans celle choisie, et cette thématique sera supprimée. Continuer ?")])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-theme-merge',
			title: s("Fusionner une thématique"),
			body: $h
		);

	}

	protected function getThemePanel(Theme $eTheme, string $action, string $title, string $submit): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax($action);

			if($eTheme->exists()) {
				$h .= $form->hidden('id', $eTheme);
			}

			$h .= $form->dynamicGroups($eTheme, ['name*', 'icon*', 'color*']);

			$h .= $form->group(
				content: $form->submit($submit)
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-theme',
			title: $title,
			body: $h
		);

	}

	public function getLevels(\Collection $cLevel): string {

		$h = '<div class="util-table util-table-hover learning-table-levels" '.attr('onrender', 'LearningSort.init(this)').' data-sort-url="/learning/admin/theme:doUpdateLevelPositions">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Niveau").'</div>';
				$h .= '<div class="text-end hide-xs-down"></div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($cLevel as $eLevel) {

				$h .= '<div class="util-table-row" data-sort-id="'.$eLevel['id'].'">';
					$h .= '<div><b>'.encode($eLevel['name']).'</b></div>';
					$h .= '<div class="text-end hide-xs-down color-muted">'.p("{value} formation", "{value} formations", $eLevel['courses']).'</div>';
					$h .= '<div class="text-end learning-row-actions">';
						$h .= \main\DesignUi::getDragHandle();
						$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
						$h .= '<div class="dropdown-list">';
							$h .= '<div class="dropdown-title">'.encode($eLevel['name']).'</div>';
							$h .= '<a href="/learning/admin/theme:updateLevel?id='.$eLevel['id'].'" class="dropdown-item">'.s("Renommer le niveau").'</a>';
							if($eLevel->assertDelete()) {
								$h .= '<div class="dropdown-divider"></div>';
								$h .= '<a data-ajax="/learning/admin/theme:doDeleteLevel" post-id="'.$eLevel['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer ce niveau ?").'" data-waiter>'.s("Supprimer le niveau").'</a>';
							} else if($cLevel->count() > 1) {
								$h .= '<a href="/learning/admin/theme:mergeLevel?id='.$eLevel['id'].'" class="dropdown-item">'.s("Fusionner dans...").'</a>';
							}
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	public function createLevel(Level $eLevel): \Panel {
		return $this->getLevelPanel($eLevel, '/learning/admin/theme:doCreateLevel', s("Ajouter un niveau"), s("Ajouter le niveau"));
	}

	public function updateLevel(Level $eLevel): \Panel {
		return $this->getLevelPanel($eLevel, '/learning/admin/theme:doUpdateLevel', s("Renommer le niveau"), s("Enregistrer"));
	}

	public function mergeLevel(Level $eLevel): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/admin/theme:doMergeLevel');

			$h .= $form->hidden('id', $eLevel);

			$h .= $form->group(
				s("Niveau à fusionner"),
				'<b>'.encode($eLevel['name']).'</b>'
			);

			$h .= $form->group(
				s("Niveau qui le reçoit"),
				$form->select('target', $eLevel['cLevel'])
			);

			$h .= $form->group(
				content: $form->submit(s("Fusionner"), ['data-confirm' => s("Les formations de ce niveau passeront dans celui choisi, et ce niveau sera supprimé. Continuer ?")])
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-level-merge',
			title: s("Fusionner un niveau"),
			body: $h
		);

	}

	protected function getLevelPanel(Level $eLevel, string $action, string $title, string $submit): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax($action);

			if($eLevel->exists()) {
				$h .= $form->hidden('id', $eLevel);
			}

			$h .= $form->dynamicGroup($eLevel, 'name*');

			$h .= $form->group(
				content: $form->submit($submit)
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-level',
			title: $title,
			body: $h
		);

	}

	/**
	 * Le panneau des sous-thématiques d'une thématique (planche T1 admin, E1) : la forme de la liste
	 * des niveaux, triable, sous le nom de la thématique. Vide, le panneau entier l'est, et le bloc
	 * qui le dit porte le bouton d'ajout (V2).
	 */
	public function getSubthemes(Theme $eTheme, \Collection $cSubtheme): \Panel {

		$create = '/learning/admin/theme:createSubtheme?theme='.$eTheme['id'];

		if($cSubtheme->empty()) {

			$h = '<div class="util-block-empty">';
				$h .= '<h3>'.s("Aucune sous-thématique").'</h3>';
				$h .= '<div class="util-buttons">';
					$h .= '<a href="'.$create.'" class="btn btn-primary">'.s("Ajouter une sous-thématique").'</a>';
				$h .= '</div>';
			$h .= '</div>';

		} else {

			$h = '<div class="util-title">';
				$h .= '<h3>'.s("Sous-thématiques").' <span class="util-counter">'.$cSubtheme->count().'</span></h3>';
				$h .= '<div>';
					$h .= '<a href="'.$create.'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter une sous-thématique").'</a>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-table util-table-hover learning-table-levels" '.attr('onrender', 'LearningSort.init(this)').' data-sort-url="/learning/admin/theme:doUpdateSubthemePositions" post-id="'.$eTheme['id'].'">';

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Sous-thématique").'</div>';
					$h .= '<div class="text-end hide-xs-down"></div>';
					$h .= '<div></div>';
				$h .= '</div>';

				foreach($cSubtheme as $eSubtheme) {

					$h .= '<div class="util-table-row" data-sort-id="'.$eSubtheme['id'].'">';
						$h .= '<div><b>'.encode($eSubtheme['name']).'</b></div>';
						$h .= '<div class="text-end hide-xs-down color-muted">'.p("{value} formation", "{value} formations", $eSubtheme['courses']).'</div>';
						$h .= '<div class="text-end learning-row-actions">';
							$h .= \main\DesignUi::getDragHandle();
							$h .= '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
							$h .= '<div class="dropdown-list">';
								$h .= '<div class="dropdown-title">'.encode($eSubtheme['name']).'</div>';
								$h .= '<a href="/learning/admin/theme:updateSubtheme?id='.$eSubtheme['id'].'" class="dropdown-item">'.s("Renommer la sous-thématique").'</a>';
								$h .= '<div class="dropdown-divider"></div>';
								// L'entrée reste à sa place, désactivée, quand des formations portent la
								// sous-thématique : elle dit pourquoi le geste n'est pas offert (S1).
								if($eSubtheme->assertDelete()) {
									$h .= '<a data-ajax="/learning/admin/theme:doDeleteSubtheme" post-id="'.$eSubtheme['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer cette sous-thématique ?").'" data-waiter>'.s("Supprimer la sous-thématique").'</a>';
								} else {
									$h .= '<a class="dropdown-item disabled">';
										$h .= '<div class="util-item-label">'.s("Supprimer la sous-thématique").'</div>';
										$h .= '<div class="util-item-more">'.p("{value} formation la porte encore.", "{value} formations la portent encore.", $eSubtheme['courses']).'</div>';
									$h .= '</a>';
								}
							$h .= '</div>';
						$h .= '</div>';
					$h .= '</div>';

				}

			$h .= '</div>';

		}

		return new \Panel(
			id: 'panel-learning-subthemes',
			title: encode($eTheme['name']),
			body: $h
		);

	}

	public function createSubtheme(Subtheme $eSubtheme): \Panel {
		return $this->getSubthemePanel($eSubtheme, '/learning/admin/theme:doCreateSubtheme', s("Ajouter une sous-thématique"), s("Ajouter la sous-thématique"));
	}

	public function updateSubtheme(Subtheme $eSubtheme): \Panel {
		return $this->getSubthemePanel($eSubtheme, '/learning/admin/theme:doUpdateSubtheme', s("Renommer la sous-thématique"), s("Enregistrer"));
	}

	/**
	 * La thématique se lit dans le formulaire sans se choisir : une sous-thématique ne change pas
	 * de thématique (`SubthemeLib::getPropertiesUpdate()`), et l'ajout se fait depuis le panneau de
	 * sa thématique, qui la poste.
	 */
	protected function getSubthemePanel(Subtheme $eSubtheme, string $action, string $title, string $submit): \Panel {

		$eSubtheme->expects(['theme' => ['name']]);

		$form = new \util\FormUi();

		$h = $form->openAjax($action);

			if($eSubtheme->exists()) {
				$h .= $form->hidden('id', $eSubtheme);
			} else {
				$h .= $form->hidden('theme', $eSubtheme['theme']);
			}

			$h .= $form->group(
				s("Thématique"),
				'<b>'.encode($eSubtheme['theme']['name']).'</b>'
			);

			$h .= $form->dynamicGroup($eSubtheme, 'name*');

			$h .= $form->group(
				content: $form->submit($submit)
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-subtheme',
			title: $title,
			body: $h,
			close: 'reload'
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Theme::model()->describer($property, [
			'name' => s("Nom"),
			'icon' => s("Icône"),
			'color' => s("Couleur"),
		]);

		switch($property) {

			case 'icon' :
				$d->field = 'select';
				$d->values = [];
				foreach(Theme::ICONS as $icon) {
					$d->values[$icon] = match($icon) {
						'laptop' => s("Ordinateur"),
						'leaf' => s("Feuille"),
						'basket3' => s("Panier"),
						'piggy-bank' => s("Tirelire"),
						'compass' => s("Boussole"),
						'signpost-2' => s("Panneau"),
						'egg' => s("Œuf"),
						'tree' => s("Arbre"),
						'award' => s("Médaille"),
						'house-door' => s("Maison"),
						'people' => s("Personnes"),
						'heart-pulse' => s("Cœur"),
					};
				}
				$d->required = TRUE;
				break;

			case 'color' :
				$d->field = 'select';
				$d->values = [
					Theme::COLORS['production'] => s("Production"),
					Theme::COLORS['commercialisation'] => s("Commercialisation"),
					Theme::COLORS['accounting'] => s("Comptabilité"),
					Theme::COLORS['settings'] => s("Réglages"),
				];
				$d->required = TRUE;
				break;

		}

		return $d;

	}

}
?>
