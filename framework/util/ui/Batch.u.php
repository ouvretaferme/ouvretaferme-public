<?php
namespace util;

class BatchUi {

	public static function one(string $id, string $menu) {

		\Asset::css('util', 'batch.css');
		\Asset::js('util', 'batch.js');

		$form = new \util\FormUi();

		$h = '<div id="'.$id.'" class="batch-one-wrapper hide">';

			$h .= $form->open($id.'-form');

				$h .= '<div class="batch-ids hide"></div>';
				$h .= '<div class="batch-one-menu">';
					$h .= $menu;
				$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

	public static function group(string $id, string $menu, ?string $danger = NULL, ?string $title = NULL, ?string $hide = NULL, bool $preview = FALSE) {

		\Asset::css('util', 'batch.css');
		\Asset::js('util', 'batch.js');

		$form = new \util\FormUi();

		$hide ??= 'Batch.hideSelection("#'.$id.'")';
		$title ??= s("Pour la sélection");

		$h = '<div id="'.$id.'" class="batch-group-wrapper '.($preview ? 'batch-group-preview' : 'hide').'">';

			$h .= $form->open($id.'-form');

			$h .= '<div class="batch-ids hide"></div>';

			$h .= '<div class="batch-title">';
				$h .= '<h4>'.$title.' <span class="batch-group-count util-counter"></span></h4>';
				$h .= '<a '.attr('onclick', $hide).' class="batch-hide" title="'.s("Annuler la sélection").'">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';

			$h .= '<div class="batch-menu">';
				$h .= '<div class="batch-main">';
					$h .= $menu;
				$h .= '</div>';
				if($danger !== NULL) {
					$h .= '<div class="batch-danger">';
						$h .= $danger;
					$h .= '</div>';
				}
			$h .= '</div>';

			$h .= $form->close();

		$h .= '</div>';

		return $h;

	}

}
?>
