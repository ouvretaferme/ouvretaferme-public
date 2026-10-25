<?php
namespace note;

class NotebookUi {

	public function __construct() {
		\Asset::css('note', 'note.css');
	}

	/**
	 * Un carnet n'a pas d'écran à lui : c'est l'onglet de la liste des notes qui l'ouvre.
	 */
	public static function url(Notebook $eNotebook): string {
		return '/ferme/'.$eNotebook['structure']['id'].'/notes?notebook='.$eNotebook['id'];
	}

	private static function urlCreate(Notebook $eNotebook): string {
		return '/note/notebook:create?structure='.$eNotebook['structure']['id'];
	}

	/**
	 * Le premier carnet, à côté de « Nouvelle note » : sans onglet, il n'y a pas de menu des carnets où
	 * le ranger.
	 */
	public function getCreate(Notebook $eNotebook): string {
		return '<a href="'.self::urlCreate($eNotebook).'" class="btn btn-outline-primary">'.s("Ranger les notes dans des carnets").'</a>';
	}

	/**
	 * Le menu des carnets, onglet au bout de la rangée des onglets (`getPageTabs()`, qui ne le replie
	 * jamais) : les gestes du carnet ouvert, puis « Nouveau carnet ». Il se déplace d'un onglet à la
	 * fois, vers celui de son voisin, que le libellé nomme : la rangée repliée d'un téléphone ne montre
	 * pas toujours qui est à côté.
	 */
	public function getMenu(\Collection $cNotebook, Notebook $eNotebookSelected, Notebook $eNotebookCreate): string {

		if($eNotebookCreate->assertCreate() === FALSE) {
			return '';
		}

		$h = '<a data-dropdown="bottom-end" class="tab-item">'.\Asset::icon('three-dots-vertical').'</a>';

		$h .= '<div class="dropdown-list">';

			if($eNotebookSelected->notEmpty() and $eNotebookSelected->assertUpdate()) {
				$h .= $this->getActions($cNotebook, $eNotebookSelected);
				$h .= '<div class="dropdown-divider"></div>';
			}

			$h .= '<a href="'.self::urlCreate($eNotebookCreate).'" class="dropdown-item">'.s("Nouveau carnet").'</a>';

		$h .= '</div>';

		return $h;

	}

	private function getActions(\Collection $cNotebook, Notebook $eNotebook): string {

		$eNotebookBefore = new Notebook();
		$eNotebookAfter = new Notebook();

		foreach($cNotebook as $eNotebookRow) {

			if($eNotebookRow['position'] === $eNotebook['position'] - 1) {
				$eNotebookBefore = $eNotebookRow;
			} else if($eNotebookRow['position'] === $eNotebook['position'] + 1) {
				$eNotebookAfter = $eNotebookRow;
			}

		}

		$h = '<div class="dropdown-title">'.encode($eNotebook['name']).'</div>';
		$h .= '<a href="/note/notebook:update?id='.$eNotebook['id'].'" class="dropdown-item">'.s("Modifier le carnet").'</a>';

		if($eNotebookBefore->notEmpty()) {
			$h .= '<a data-ajax="/note/notebook:doIncrementPosition" post-id="'.$eNotebook['id'].'" post-increment="-1" class="dropdown-item">'.s("Placer avant {value}", encode($eNotebookBefore['name'])).'</a>';
		}

		if($eNotebookAfter->notEmpty()) {
			$h .= '<a data-ajax="/note/notebook:doIncrementPosition" post-id="'.$eNotebook['id'].'" post-increment="1" class="dropdown-item">'.s("Placer après {value}", encode($eNotebookAfter['name'])).'</a>';
		}

		if($eNotebook->assertDelete()) {
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/note/notebook:doDelete" post-id="'.$eNotebook['id'].'" data-confirm="'.s("Supprimer ce carnet ? Ses notes seront conservées dans Non classé.").'" class="dropdown-item">'.s("Supprimer le carnet").'</a>';
		}

		return $h;

	}

	public function create(Notebook $eNotebook): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/note/notebook:doCreate');

			$h .= $form->hidden('structure', $eNotebook['structure']);
			$h .= $form->dynamicGroups($eNotebook, ['name']);
			$h .= $form->group(content: $form->submit(s("Créer le carnet")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-notebook-create',
			title: s("Nouveau carnet"),
			body: $h
		);

	}

	public function update(Notebook $eNotebook): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/note/notebook:doUpdate');

			$h .= $form->hidden('id', $eNotebook);
			$h .= $form->dynamicGroups($eNotebook, ['name']);
			$h .= $form->group(content: $form->submit(s("Enregistrer")));

		$h .= $form->close();

		return new \Panel(
			id: 'panel-notebook-update',
			title: s("Modifier le carnet"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		return Notebook::model()->describer($property, [
			'name' => s("Nom"),
		]);

	}

}
?>
