<?php
namespace learning;

/**
 * La feuille signée d'une date sur place (lot L8e), sous la liste de ses inscrits : le bouton qui
 * dépose des photos ou des PDF, la table des fichiers, la règle en annotation. La forme des fichiers
 * d'une leçon (`AttachmentUi::getEditor()`), sans l'ordre, que personne n'a à ranger, avec le jour
 * du dépôt et qui l'a fait.
 */
class DateSheetUi {

	public function __construct() {

		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');

	}

	public static function url(DateSheet $eDateSheet): string {
		return '/formation/date/feuille/'.$eDateSheet['id'];
	}

	/**
	 * Le bouton disparaît au dixième fichier, où le dépôt serait refusé ; plusieurs fichiers choisis
	 * d'un coup partent l'un après l'autre (`LearningDateSheet.upload()`).
	 */
	public function getSection(Date $eDate, \Collection $cDateSheet): string {

		$eDate->expects(['sheets']);

		$form = new \util\FormUi();

		$h = '<div id="learning-date-sheets">';

			$h .= '<div class="util-title mt-2">';
				$h .= '<h3>'.s("Feuille signée").'</h3>';
				if($eDate->acceptSheetUpload()) {
					$h .= '<div>';
						$h .= $form->fileButton('file', \Asset::icon('paperclip').' '.s("Déposer un fichier"), [
							'accept' => 'image/jpeg,image/png,application/pdf',
							'multiple' => 'multiple',
							'data-url' => '/learning/dateSheet:doUpload',
							'data-date' => $eDate['id'],
							'data-size-max' => \media\MediaSetting::MAX_ATTACHMENT_SIZE * 1024 * 1024,
							'data-size-error' => \media\AlertUi::getError('attachmentSize'),
							'onchange' => 'LearningDateSheet.upload(this)',
						]);
					$h .= '</div>';
				}
			$h .= '</div>';

			if($cDateSheet->notEmpty()) {

				$h .= '<div class="util-table util-table-bordered learning-table-sheet">';

					$h .= '<div class="util-table-head">';
						$h .= '<div>'.s("Fichier").'</div>';
						$h .= '<div>'.s("Type").'</div>';
						$h .= '<div>'.s("Déposé le").'</div>';
						$h .= '<div></div>';
					$h .= '</div>';

					foreach($cDateSheet as $eDateSheet) {

						$h .= '<div class="util-table-row">';
							$h .= '<div><a href="'.self::url($eDateSheet).'" data-ajax-download data-waiter>'.self::getIcon($eDateSheet).' '.encode($eDateSheet['name']).'</a></div>';
							$h .= '<div class="color-muted">'.self::getType($eDateSheet).'</div>';
							$h .= '<div>';
								$h .= \util\DateUi::numeric($eDateSheet['createdAt'], \util\DateUi::DATE);
								$h .= '<div class="util-annotation">'.\user\UserUi::name($eDateSheet['createdBy']).'</div>';
							$h .= '</div>';
							$h .= '<div>'.$this->getActions($eDateSheet).'</div>';
						$h .= '</div>';

					}

				$h .= '</div>';

			}

			$h .= '<p class="util-annotation mt-1 mb-2">'.s("Des photos et des PDF, {value} au plus.", DateSheet::MAX_BY_DATE).'</p>';

		$h .= '</div>';

		return $h;

	}

	protected function getActions(DateSheet $eDateSheet): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.encode($eDateSheet['name']).'</div>';
			$h .= '<a data-ajax="/learning/dateSheet:doDelete" post-id="'.$eDateSheet['id'].'" class="dropdown-item" data-confirm="'.s("Retirer ce fichier de la date ?").'" data-waiter>'.s("Supprimer le fichier").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'icône et le libellé disent ce que l'équipe va ouvrir, le hash portant le type réel du fichier
	 * déposé.
	 */
	protected static function getIcon(DateSheet $eDateSheet): string {

		return \Asset::icon(match(\media\MediaUi::isImage($eDateSheet['hash'])) {
			TRUE => 'file-earmark-image',
			FALSE => 'file-earmark-pdf',
		});

	}

	protected static function getType(DateSheet $eDateSheet): string {

		return match(\media\MediaUi::isImage($eDateSheet['hash'])) {
			TRUE => s("Photo"),
			FALSE => s("PDF"),
		};

	}

}
?>
