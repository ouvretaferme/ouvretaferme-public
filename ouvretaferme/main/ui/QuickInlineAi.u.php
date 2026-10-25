<?php
namespace main;

/**
 * util-quick-inline : une valeur qui devient champ sur place, dans sa cellule, au clic sur la valeur
 * ou sur ce qui la nomme (quickInlineAi.js).
 *
 * À préférer à util-quick quand la saisie doit laisser voir ce qui l'entoure : util-quick ouvre un
 * formulaire en plein écran qui floute la page. Le champ est un formulaire ajax du cadriciel, fourni
 * par l'appelant : Entrée l'envoie, et la réponse remplace ce qui a changé.
 */
class QuickInlineAiUi {

	/**
	 * @param string $id L'identifiant de la boîte, que nomment open() et linked()
	 * @param string $view Ce qu'on voit au repos ; ce qu'il porte d'open() ouvre la boîte
	 * @param string $url L'action qui reçoit le champ
	 * @param array $hidden Ce que l'action doit recevoir en plus du champ
	 * @param string $field Le champ, avec son nom
	 * @param bool $binary Le champ est un fichier
	 * @param bool $submitOnBlur La sortie du champ enregistre au lieu de refermer, comme Entrée, quand la
	 * valeur a changé : pour une saisie qu'on corrige en passant, une ligne de liste par exemple, où
	 * perdre ce qu'on vient de taper en cliquant ailleurs surprendrait
	 * @param string $class Un modificateur de la boîte, util-quick-inline-wrap par exemple
	 */
	public static function get(string $id, string $view, string $url, array $hidden, string $field, bool $binary = FALSE, bool $submitOnBlur = FALSE, string $class = ''): string {

		\Asset::js('main', 'quickInlineAi.js');

		$form = new \util\FormUi();

		$h = '<div class="util-quick-inline'.($class === '' ? '' : ' '.$class).'" id="'.$id.'"'.($submitOnBlur ? ' data-quick-inline-submit-on-blur' : '').'>';
			$h .= '<span>'.$view.'</span>';
			$h .= $form->openAjax($url, ['class' => 'util-quick-inline-field'] + ($binary ? ['binary' => TRUE] : []));
				foreach($hidden as $name => $value) {
					$h .= $form->hidden($name, $value);
				}
				$h .= $field;
				$h .= $form->submit(\Asset::icon('check-lg'), ['class' => 'btn btn-only-icon btn-primary']);
			$h .= $form->close();
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien qui ouvre une boîte : sans identifiant, celle qui le contient.
	 */
	public static function open(string $html, string $id = ''): string {
		return '<a class="util-quick" data-quick-inline-open="'.$id.'">'.$html.'</a>';
	}

	/**
	 * Un geste qui ouvre la boîte, posé à côté de la valeur (« Remplacer ») : il a la couleur d'un
	 * lien, là où open() fait de la valeur elle-même ce qu'on clique, dans la couleur du texte.
	 */
	public static function action(string $html, string $id = ''): string {
		return '<a data-quick-inline-open="'.$id.'">'.$html.'</a>';
	}

	/**
	 * Ce qui ne se montre que pendant la saisie de la boîte, ailleurs dans la page.
	 */
	public static function linked(string $id, string $html, string $tag = 'div', string $class = ''): string {
		return '<'.$tag.' class="hide '.$class.'" data-quick-inline-for="'.$id.'">'.$html.'</'.$tag.'>';
	}

}
?>
