<?php
namespace main;

/**
 * util-drop : la zone où l'on glisse des fichiers, qui les liste et porte le geste qui conclut le
 * dépôt (dropAi.js).
 *
 * Elle s'écrit dans cet ordre : open(), getHead() et getList(), ce que l'écran ajoute, getFoot(), puis
 * la fermeture de open(). Un écran qui refuse un lot entier (un PDF mêlé à des photos, un poids total)
 * entoure getHead() et getList() d'un `div` qui porte le `data-wrapper` de ce refus, et laisse le pied
 * dehors : le message tombe ainsi au-dessus du bouton qui termine, et non dessous.
 *
 * Chaque écran écrit ses lignes, avec leurs cellules. L'état d'une ligne est son `data-state`
 * (`waiting`, `pending`, `done`), et son refus la classe `form-error` que `Lime.Alert` y pose ; chaque
 * texte d'état nomme dans `data-drop-state` ceux où il paraît, `refused` compris, et component.css
 * n'en montre qu'un. L'écran compte ce qu'il a reçu pour le dire à `MainDrop.refresh()` et
 * `MainDrop.count()`.
 */
class DropAiUi {

	/**
	 * @param string $drop Ce que fait l'écran des fichiers lâchés, `ProofDeposit.drop(event, this)` par exemple
	 * @param string $class Un modificateur de la zone
	 */
	public static function open(string $drop, string $class = ''): string {

		\Asset::js('main', 'dropAi.js');

		return '<div class="util-drop'.($class === '' ? '' : ' '.$class).'" ondragover="MainDrop.over(event, this)" ondragleave="MainDrop.leave(this)" ondrop="'.$drop.'">';

	}

	/**
	 * L'appel : ce qu'on glisse, les formats, et le bouton qui ouvre le sélecteur, puisqu'on ne
	 * glisse pas toujours.
	 *
	 * @param string $label Le bouton tant que rien n'est reçu, « Choisir des fichiers » par exemple
	 * @param string $input Le champ fichier, caché, que le bouton ouvre
	 * @param bool $primary Le bouton est plein tant que la zone est vide, et passe en contour dès
	 * qu'elle liste un fichier, le bouton qui termine prenant alors le plein. FALSE quand la zone n'a
	 * pas de pied et que le geste qui termine est ailleurs.
	 */
	public static function getHead(string $text, string $formats, string $label, string $input, bool $primary): string {

		$h = '<div class="util-drop-head">';
			$h .= \Asset::icon('cloud-upload');
			$h .= '<p class="mt-1'.($formats === '' ? '' : ' mb-0').'"><b>'.$text.'</b></p>';
			if($formats !== '') {
				$h .= '<p class="util-annotation">'.$formats.'</p>';
			}
			$h .= '<p class="mb-0">';
				$h .= '<label class="btn '.($primary ? 'btn-primary' : 'btn-outline-primary').'"'.($primary ? ' data-drop-primary' : '').'>'.self::getChoose($label).$input.'</label>';
			$h .= '</p>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le libellé d'un bouton qui choisit des fichiers, que `MainDrop.refresh()` change en « Ajouter
	 * d'autres fichiers » dès qu'un fichier est reçu (R-42). Il sert aussi au grand bouton du
	 * téléphone, qui suit la même règle.
	 */
	public static function getChoose(string $label): string {
		return '<span data-drop-choose="'.encode($label).'" data-drop-more="'.encode(s("Ajouter d'autres fichiers")).'">'.$label.'</span>';
	}

	/**
	 * La liste des fichiers posés, une util-table dont les pistes sont celles de la zone : l'icône,
	 * le nom, l'état, et la croix qui retire un fichier pas encore envoyé.
	 */
	public static function getList(string $rows = ''): string {
		return '<div class="util-table util-drop-list">'.$rows.'</div>';
	}

	/**
	 * Le pied, qui ne paraît qu'avec le premier fichier listé : le compte à gauche, que
	 * `MainDrop.count()` récrit à chaque changement, et le bouton qui termine à droite.
	 *
	 * @param array $counts Les textes du compte par sorte, la sorte `''` d'abord :
	 * `['' => ['none' => …, 'one' => …, 'many' => …]]`, `{value}` devenant le nombre. Sans `none`, le
	 * pluriel dit zéro. Une autre sorte nomme un autre compte du même pied, les pages d'un justificatif
	 * en cours par exemple.
	 * @param string $count Le compte au rendu
	 */
	public static function getFoot(string $button, array $counts = [], string $count = ''): string {

		$h = '<div class="util-drop-foot">';
			$h .= '<span data-drop-count="'.encode(json_encode($counts)).'">'.$count.'</span>';
			$h .= '<div>'.$button.'</div>';
		$h .= '</div>';

		return $h;

	}

}
?>
