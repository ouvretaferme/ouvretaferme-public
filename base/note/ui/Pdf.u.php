<?php
namespace note;

/**
 * Les PDF des notes, à la charte des documents (`main\PdfUi`) : une note, ou un carnet entier, une
 * note par page.
 *
 * Le pied ne porte pas la page : elle se numérote sur chaque page par le gabarit de Puppeteer
 * (`main\PdfUi::getPagingFooter()`), et un numéro écrit dans le flux se répéterait.
 */
class PdfUi {

	public function __construct() {
		\Asset::css('note', 'note.css');
		\Asset::css('note', 'pdf.css');
	}

	public static function urlNote(Note $eNote): string {
		return '/note/pdf:downloadNote?id='.$eNote['id'];
	}

	public static function urlNotebook(Notebook $eNotebook): string {
		return '/note/pdf:downloadNotebook?id='.$eNotebook['id'];
	}

	public static function getNoteFilename(Note $eNote): string {
		return $eNote['title'].'.pdf';
	}

	public static function getNotebookFilename(Notebook $eNotebook): string {
		return s("Carnet {value}", $eNotebook['name']).'.pdf';
	}

	/**
	 * Le bouton de l'en-tête de la note, ou de la rangée du carnet ouvert.
	 */
	public static function getDownload(string $url): string {
		return '<a href="'.$url.'" data-ajax-download data-waiter class="btn btn-outline-primary">'.\Asset::icon('file-pdf').'<span class="hide-xs-down"> '.s("PDF").'</span></a>';
	}

	public function getNote(\structure\Structure $eStructure, Note $eNote): string {

		// Les marges des documents de `learning`. `pdf.js` ne réserve que le bas de la page, pour le pied
		// paginé : chaque document pose les siennes, et la règle `@page` l'emporte sur Puppeteer
		// (`preferCSSPageSize`).
		$h = \main\PdfUi::getPage('15mm 18mm');

		$h .= \main\PdfUi::getHeader();
		$h .= \main\PdfUi::getStructure($eStructure, $eNote['title'], NoteUi::getAnnotation($eNote));

		$h .= $this->getBody($eNote);

		$h .= \main\PdfUi::getFooter($eStructure);

		return $h;

	}

	public function getNotebook(\structure\Structure $eStructure, Notebook $eNotebook, \Collection $cNote): string {

		// Les marges de la note (`getNote()`).
		$h = \main\PdfUi::getPage('15mm 18mm');

		$h .= \main\PdfUi::getHeader();
		$h .= \main\PdfUi::getStructure($eStructure, s("Carnet {value}", $eNotebook['name']), p("{value} note", "{value} notes", $cNote->count()));

		foreach($cNote as $eNote) {

			$h .= '<div class="note-pdf-note">';
				$h .= '<h2 class="note-pdf-title">'.encode($eNote['title']).'</h2>';
				$h .= '<p class="util-annotation">'.NoteUi::getAnnotation($eNote, withNotebook: FALSE).'</p>';
				$h .= $this->getBody($eNote);
			$h .= '</div>';

		}

		$h .= \main\PdfUi::getFooter($eStructure);

		return $h;

	}

	/**
	 * Ce à quoi la note est rattachée, puis son texte. Sur papier, un rattachement se lit et ne mène
	 * nulle part.
	 */
	private function getBody(Note $eNote): string {

		$h = '';

		if($eNote['cTarget']->notEmpty()) {

			$h .= '<div class="note-marks note-pdf-marks">';

				foreach($eNote['cTarget'] as $eTarget) {
					$ui = NoteSetting::resolve($eTarget['targetType'], 'NoteTargetUi');
					$h .= '<span class="badge badge-outline badge-info">'.encode($ui::getLabel($eTarget['eElement'])).'</span>';
				}

			$h .= '</div>';

		}

		if($eNote['content'] !== NULL) {
			$h .= new \editor\EditorUi()->value($eNote['content']);
		}

		return $h;

	}

}
?>
