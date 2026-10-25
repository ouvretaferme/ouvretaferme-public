<?php
namespace learning;

/**
 * Les modules d'une formation, dans son éditeur : leurs panneaux et leur menu.
 */
class ChapterUi {

	public function getActions(Chapter $eChapter): string {

		$items = '<a href="/learning/chapter:update?id='.$eChapter['id'].'" class="dropdown-item">'.s("Modifier le module").'</a>';

		if($eChapter->assertDelete()) {
			$items .= '<div class="dropdown-divider"></div>';
			$items .= '<a data-ajax="/learning/chapter:doDelete" post-id="'.$eChapter['id'].'" class="dropdown-item" data-confirm="'.s("Supprimer ce module et toutes ses leçons ?").'" data-waiter>'.s("Supprimer le module").'</a>';
		} else if($eChapter['holdsCompleted']) {
			// La dernière leçon d'une formation suivie en entier se garde (`acceptDelete()`) : le
			// geste se montre fermé, avec ce qui le ferme, comme « Supprimer la formation ».
			$items .= '<div class="dropdown-divider"></div>';
			$items .= '<a class="dropdown-item disabled">';
				$items .= '<div class="util-item-label">'.s("Supprimer le module").'</div>';
				$items .= '<div class="util-item-more">'.s("Un inscrit a suivi toute la formation. Dépubliez-la d'abord.").'</div>';
			$items .= '</a>';
		}

		return \main\DesignUi::getGear($items, encode($eChapter['name']));

	}

	public function create(Chapter $eChapter): \Panel {
		return $this->getPanel($eChapter, '/learning/chapter:doCreate', s("Ajouter un module"), s("Ajouter le module"));
	}

	public function update(Chapter $eChapter): \Panel {
		return $this->getPanel($eChapter, '/learning/chapter:doUpdate', s("Modifier le module"), s("Enregistrer"));
	}

	protected function getPanel(Chapter $eChapter, string $action, string $title, string $submit): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax($action);

			if($eChapter->exists()) {
				$h .= $form->hidden('id', $eChapter);
			} else {
				$h .= $form->hidden('course', $eChapter['course']);
			}

			$h .= $form->dynamicGroups($eChapter, ['name*', 'summary']);

			$h .= $form->group(
				content: $form->submit($submit)
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-chapter',
			title: $title,
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Chapter::model()->describer($property, [
			'name' => s("Nom du module"),
			'summary' => s("Présentation"),
		]);

		switch($property) {

			// Le numéro est celui que l'écran écrit devant le nom partout ailleurs (« Module 2 · … ») :
			// le montrer dans le champ évite que l'auteur le tape une seconde fois. Il ne se stocke
			// pas, la page le pose sur l'élément d'après la place du module dans le programme.
			case 'name' :
				$d->prepend = fn(\util\FormUi $form, Chapter $e) => $form->addon(s("Module {value}&nbsp;·", $e['number']));
				break;

			case 'summary' :
				$d->field = 'textarea';
				break;

		}

		return $d;

	}

}
?>
