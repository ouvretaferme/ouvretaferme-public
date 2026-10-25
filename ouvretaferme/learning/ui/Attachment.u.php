<?php
namespace learning;

/**
 * Les fichiers à télécharger d'une leçon : la liste que l'auteur range sous le contenu, et la
 * rangée de boutons que le lecteur trouve après la leçon.
 */
class AttachmentUi {

	public function __construct() {

		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');

	}

	public static function url(Attachment $eAttachment): string {
		return '/formation/fichier/'.$eAttachment['id'];
	}

	/**
	 * L'icône et le libellé disent ce que le lecteur va ouvrir, le hash portant le type réel du
	 * fichier déposé.
	 */
	protected static function getIcon(Attachment $eAttachment): string {

		return \Asset::icon(match(\media\MediaUi::isImage($eAttachment['hash'])) {
			TRUE => 'file-earmark-image',
			FALSE => 'file-earmark-pdf',
		});

	}

	protected static function getType(Attachment $eAttachment): string {

		return match(\media\MediaUi::isImage($eAttachment['hash'])) {
			TRUE => s("Image"),
			FALSE => s("PDF"),
		};

	}

	/**
	 * La liste, dans son onglet de l'écran de la leçon (`LessonUi::getEdition()`). Le fichier choisi
	 * part aussitôt par sa propre requête (`LearningAttachment.upload()`).
	 */
	public function getEditor(Lesson $eLesson): string {

		$eLesson->expects(['cAttachment']);

		$form = new \util\FormUi();

		$h = '<div id="lesson-attachments">';

			$h .= '<div class="util-title mt-2">';
				$h .= '<h3>'.LessonUi::getEditionPanelTitle('attachments').'</h3>';
				if($eLesson['cAttachment']->count() < Attachment::MAX_BY_LESSON) {
					$h .= '<div>';
						$h .= '<label class="btn btn-outline-primary">';
							$h .= \Asset::icon('paperclip').' '.s("Ajouter un fichier");
							$h .= $form->file('file', [
								'class' => 'hide',
								'accept' => 'image/jpeg,image/png,application/pdf',
								'data-url' => '/learning/attachment:doUpload',
								'data-lesson' => $eLesson['id'],
								'data-size-max' => \media\MediaSetting::MAX_ATTACHMENT_SIZE * 1024 * 1024,
								'data-size-error' => \media\AlertUi::getError('attachmentSize'),
								'onchange' => 'LearningAttachment.upload(this)',
							]);
						$h .= '</label>';
					$h .= '</div>';
				}
			$h .= '</div>';

			if($eLesson['cAttachment']->notEmpty()) {

				$h .= '<div class="util-table util-table-bordered learning-table-attachment" '.attr('onrender', 'LearningSort.init(this)').' data-sort-url="/learning/attachment:doUpdatePositions" post-lesson="'.$eLesson['id'].'">';

					$h .= '<div class="util-table-head">';
						$h .= '<div></div>';
						$h .= '<div>'.s("Fichier").'</div>';
						$h .= '<div>'.s("Type").'</div>';
						$h .= '<div></div>';
					$h .= '</div>';

					foreach($eLesson['cAttachment'] as $eAttachment) {

						$h .= '<div class="util-table-row" data-sort-id="'.$eAttachment['id'].'">';
							$h .= '<div>'.\main\DesignUi::getDragHandle().'</div>';
							$h .= '<div><a href="'.self::url($eAttachment).'" data-ajax-download data-waiter>'.self::getIcon($eAttachment).' '.encode($eAttachment['name']).'</a></div>';
							$h .= '<div class="color-muted">'.self::getType($eAttachment).'</div>';
							$h .= '<div>'.$this->getActions($eAttachment).'</div>';
						$h .= '</div>';

					}

				$h .= '</div>';

			} else {

				$h .= '<div class="util-block util-block-empty">';
					$h .= '<h3>'.s("Aucun fichier").'</h3>';
				$h .= '</div>';

			}

			$h .= '<p class="util-annotation mt-1 mb-2">'.s("Des images et des PDF, {value} au plus.", Attachment::MAX_BY_LESSON).'</p>';

		$h .= '</div>';

		return $h;

	}

	protected function getActions(Attachment $eAttachment): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.encode($eAttachment['name']).'</div>';
			$h .= '<a href="/learning/attachment:update?id='.$eAttachment['id'].'" class="dropdown-item">'.s("Renommer le fichier").'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/learning/attachment:doDelete" post-id="'.$eAttachment['id'].'" class="dropdown-item" data-confirm="'.s("Retirer ce fichier de la leçon ?").'" data-waiter>'.s("Supprimer le fichier").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Rien quand la leçon n'a pas de fichier : le lecteur n'a pas à apprendre qu'il n'y a rien.
	 */
	public function getReader(Lesson $eLesson): string {

		$eLesson->expects(['cAttachment']);

		if($eLesson['cAttachment']->empty()) {
			return '';
		}

		$h = '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Fichiers à télécharger").'</h3>';
		$h .= '</div>';

		$h .= '<div class="learning-attachments">';

			foreach($eLesson['cAttachment'] as $eAttachment) {
				$h .= '<a href="'.self::url($eAttachment).'" class="btn btn-outline-primary" data-ajax-download data-waiter>';
					$h .= self::getIcon($eAttachment).' '.encode($eAttachment['name']).' <span class="color-muted">'.self::getType($eAttachment).'</span>';
				$h .= '</a>';
			}

		$h .= '</div>';

		return $h;

	}

	public function update(Attachment $eAttachment): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/attachment:doUpdate');

			$h .= $form->hidden('id', $eAttachment);

			$h .= $form->dynamicGroups($eAttachment, ['name*']);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-attachment',
			title: s("Renommer le fichier"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Attachment::model()->describer($property, [
			'name' => s("Nom affiché au lecteur"),
		]);

		return $d;

	}

}
?>
