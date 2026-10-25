<?php
namespace main;

/**
 * L'omnibox : un champ unique où l'on cherche, et où ce qu'on a choisi reste sous les
 * yeux en jetons.
 *
 * Un seul point d'entrée pour tout le site : le markup, le clavier et l'apparence sont
 * ici, la page ne fournit que ce qu'elle sait — ses suggestions, ses jetons actifs. Le
 * cadre est un `form-control` du framework, pas un châssis refait : une recherche doit
 * se lire comme un champ de saisie, et elle vieillit avec les autres.
 *
 * Deux modes, qui ne se distinguent pas à l'usage — dans les deux, un critère actif est
 * un jeton et le texte tapé ne fait que réduire les suggestions :
 *
 * - **navigation** : la suggestion porte une `url`, la choisir change de page, et le
 *   jeton est un lien vers la page sans lui. L'état reste dans la requête, donc le lien
 *   partagé et le bouton retour restent justes, et rien ne dépend du JS pour s'effacer.
 * - **local** : la suggestion n'a pas d'url, la choisir pose un jeton et émet
 *   l'événement `omnibox` sur la boîte ; la page filtre alors ce qu'elle affiche déjà,
 *   sans rien recharger. À réserver aux pages qui portent déjà toutes leurs lignes.
 */
class OmniboxUi {

	/**
	 * @param array $suggestions Ce que le déroulant propose : `['group' => …, 'label' => …, 'value' => …, 'url' => …,
	 *                           'icon' => …]`, groupées dans l'ordre du tableau. `terms` porte des mots que la recherche
	 *                           lit en plus du libellé, sans les afficher. `url` bascule en mode navigation,
	 *                           `value` distingue deux libellés identiques de groupes différents, `icon` est l'adresse
	 *                           complète d'un symbole SVG, `cursor` désigne d'avance ce qu'Entrée prendra, `notice`
	 *                           fait de la suggestion une ligne à lire, ni lien, ni cible des flèches, ni survolable :
	 *                           TRUE pour un refus, en rouge, 'muted' pour dire qu'il n'y a rien à proposer. Un
	 *                           tableau vide laisse la page les fournir en JS, sur
	 *                           l'événement `omniboxsuggest`.
	 * @param ?string $suggestionsUrl L'adresse qui rend les suggestions, quand la liste est trop grande ou trop
	 *                           mouvante pour voyager avec la page. Elle reçoit `query` et répond `{results: […]}`,
	 *                           comme les autocomplétions du framework, et c'est alors **elle** qui filtre : le
	 *                           composant n'y retouche pas.
	 * @param array $suggestionsBody Ce que la page doit rappeler à chaque appel — le contexte sans lequel le serveur
	 *                           ne saurait pas de quoi on parle.
	 * @param array $tokens Les critères déjà posés : `['label' => …, 'group' => …, 'value' => …, 'url' => …]`.
	 * @param ?string $count Le décompte de ce que la recherche laisse. Vide, il ne s'affiche pas.
	 * @param ?string $countLabel Le mot que le JS accole au nombre quand c'est lui qui compte (mode local).
	 * @param ?string $clear L'URL du bouton d'effacement en mode navigation. Sans elle, il efface les jetons en JS.
	 * @param bool $fullscreen Au doigt, la boîte part en plein écran au focus (`UtilOmnibox.openFullscreen()`). Sans
	 *                           cela (`data-inline`), elle reste à sa place, liste dessous, et le clavier du téléphone
	 *                           monte sous l'écran : un champ dont la phrase décrit ce que l'écran montre autour de lui.
	 * @param bool $fullscreenBottom Le plein écran d'un champ posé en bas de l'écran, celui du menu hors du bureau : il
	 *                           part dès que la nav passe en bas (`UtilOmnibox.isFullscreen()`), et non au doigt
	 *                           seul, garde le champ en bas, au-dessus du clavier, et pose la liste au-dessus de lui.
	 *                           Une flèche remplace la loupe pour en sortir. Incompatible avec `fullscreen: FALSE`.
	 * @param ?string $name Fait de la boîte un champ de formulaire : chaque jeton poste sa valeur dans `<name>[]`.
	 *                           Les champs cachés suivent la boîte au lieu d'y vivre, parce qu'au doigt la boîte part
	 *                           sous `document.body`, hors du formulaire, et un envoi à ce moment perdrait les jetons.
	 * @param array $suggestionsIdle Avec `$suggestionsUrl`, ce que la liste propose tant que le champ est vide et sans
	 *                           jeton, au format de `$suggestions` : rien n'est lu au serveur avant la première frappe.
	 * @param bool $folded La recherche repliée d'un en-tête d'écran (`DesignUi::getPageHeader()`, `search`) : une loupe
	 *                           tant que rien n'est cherché, et les jetons sur leur propre rangée sous le titre, où chacun se
	 *                           lit en entier quel qu'en soit le nombre. À réserver aux écrans où chercher est un geste parmi
	 *                           d'autres : là où la recherche est l'objet de l'écran, la replier la cacherait.
	 * @param array $chips Les familles, par leur `group`, dont les choix sont assez courts pour se poser en puces sur une
	 *                           ou deux lignes au lieu d'une ligne chacun : des mois, des moyens de paiement, débit et crédit.
	 * @param bool $searchIcon La loupe en tête du champ. Sans elle (`util-omnibox-plain`), le champ ne se lit plus comme
	 *                           une recherche : celui d'une case où l'on tape une quantité, qui n'a rien à chercher.
	 * @param bool $scroll Le champ garde sa largeur quels que soient ses jetons (`util-omnibox-scroll`) : une seule ligne,
	 *                           les jetons entiers et le champ qui défile, le texte tapé toujours entier. Pour un champ
	 *                           logé dans une piste qu'il ne doit pas élargir.
	 * @param bool $chipsExplicit Entrée sans suggestion visée ne prend jamais une puce, qui se choisit au clic ou à la
	 *                           flèche : là où les puces précisent une phrase au lieu de filtrer une liste.
	 * @param bool $restart La croix recommence la saisie (`util-omnibox-restart`) : elle se montre dès qu'il y a du
	 *                           texte tapé, pas seulement des jetons. Pour un champ où l'on écrit une phrase, et non des
	 *                           critères. `$scroll` le fait déjà.
	 */
	public static function get(
		array $suggestions = [],
		?string $suggestionsUrl = NULL,
		array $suggestionsBody = [],
		array $tokens = [],
		?string $placeholder = NULL,
		?string $count = NULL,
		?string $countLabel = NULL,
		?string $clear = NULL,
		?string $id = NULL,
		string $class = '',
		bool $singleByGroup = FALSE,
		bool $fullscreen = TRUE,
		bool $fullscreenBottom = FALSE,
		?string $name = NULL,
		array $suggestionsIdle = [],
		bool $folded = FALSE,
		array $chips = [],
		bool $searchIcon = TRUE,
		bool $chipsExplicit = FALSE,
		bool $scroll = FALSE,
		bool $restart = FALSE
	): string {

		if($fullscreenBottom and $fullscreen === FALSE) {
			throw new \Exception('fullscreenBottom expects fullscreen');
		}

		// Le cadre est un .form-control : une page sans le moindre formulaire n'aurait
		// pas chargé la feuille qui le dessine.
		\Asset::css('util', 'form.css');
		\Asset::js('main', 'omnibox.js');

		// is-filled porte l'affichage du bouton d'effacement, ici comme en JS : sans
		// jeton, il n'y a rien à effacer et un bouton mort dans un champ vide se lit
		// comme une panne.
		$h = '<div class="util-omnibox '.($folded ? 'util-omnibox-folded' : '').' '.($tokens === [] ? '' : 'is-filled').' '.($searchIcon ? '' : 'util-omnibox-plain').' '.($scroll ? 'util-omnibox-scroll' : '').' '.($restart ? 'util-omnibox-restart' : '').' '.$class.'"';
		if($id !== NULL) {
			$h .= ' id="'.$id.'"';
		}
		if($singleByGroup) {
			$h .= ' data-single-by-group';
		}
		if($name !== NULL) {
			$h .= ' data-name="'.encode($name).'"';
		}
		if($chips !== []) {
			$h .= ' data-chips="'.encode(json_encode($chips)).'"';
		}
		if($chipsExplicit) {
			$h .= ' data-chips-explicit';
		}
		if($fullscreen === FALSE) {
			$h .= ' data-inline';
		}
		if($fullscreenBottom) {
			$h .= ' data-fullscreen="bottom"';
		}
		if($suggestions !== []) {
			$h .= ' data-suggestions="'.encode(json_encode($suggestions)).'"';
		}

		if($suggestionsUrl !== NULL) {
			$h .= ' data-suggestions-url="'.encode($suggestionsUrl).'"';
			$h .= ' data-suggestions-body="'.encode(json_encode($suggestionsBody)).'"';

			if($suggestionsIdle !== []) {
				$h .= ' data-suggestions-idle="'.encode(json_encode($suggestionsIdle)).'"';
			}

		}
		$h .= '>';

			if($folded) {
				$h .= self::getFoldedTokens($tokens, $count, $clear);
				$h .= '<div class="util-omnibox-main">';
				$h .= '<button type="button" class="btn btn-only-icon btn-outline-secondary util-omnibox-unfold">'.\Asset::icon('search').'</button>';
			}

			$h .= '<div class="util-omnibox-field form-control">';

				// La flèche ne se montre qu'en plein écran, à la place de la loupe : le panneau couvre la page, et
				// le retour du téléphone n'est pas un geste que tout le monde connaît. Un lien et non un bouton, comme
				// la croix : le clic dans le champ qui n'atteint ni lien ni saisie lui rend le focus.
				if($fullscreenBottom) {
					$h .= '<a class="util-omnibox-back">'.\Asset::icon('arrow-left').'</a>';
				}

				if($searchIcon) {
					$h .= \Asset::icon('search');
				}

				// Sur une ligne, les jetons et la queue défilent dans leur piste, et la croix reste hors d'elle, au
				// bout du champ : elle se voit et se touche quelle que soit la place que prennent les jetons.
				if($scroll) {
					$h .= '<div class="util-omnibox-track">';
				}

				// Repliée, la boîte pose ses jetons sur leur rangée et non dans le champ.
				foreach($folded ? [] : $tokens as $token) {

					$h .= self::getToken($token);

				}

				// Le champ, le décompte et l'effacement dans un même bloc : ils passent à la
				// ligne ensemble quand les jetons remplissent la précédente. Posés à plat,
				// le champ tenait sur la ligne et la croix tombait seule sous lui.
				$h .= '<span class="util-omnibox-tail">';

					// Rempli par le JS, seul à savoir si les jetons débordent du champ.
					$h .= '<span class="badge util-omnibox-filters" data-label="'.encode(s("{value} filtres", '{value}')).'"></span>';

					$placeholder ??= s("Chercher...");

					$h .= '<input type="text" class="util-omnibox-input" autocomplete="off" placeholder="'.encode($placeholder).'"';

					// Sous Firefox, sans `field-sizing`, la largeur du champ vient de `size`, que le JS recalcule
					// (`UtilOmnibox.sizeInput()`) seulement quand le texte change, jamais au premier affichage : sans lui,
					// la case s'afficherait à vingt caractères.
					if($scroll) {
						$h .= ' size="'.max(mb_strlen($placeholder) + 1, 2).'"';
					}

					$h .= '/>';

					$h .= '<span class="util-omnibox-count"';
					if($countLabel !== NULL) {
						$h .= ' data-label="'.encode($countLabel).'"';
					}
					$h .= '>'.encode($count ?? '').'</span>';

					if($scroll === FALSE) {
						$h .= self::getClear($clear);
					}

				$h .= '</span>';

				if($scroll) {
					$h .= '</div>';
					$h .= self::getClear($clear);
				}

			$h .= '</div>';

			// Ce qu'une famille garde en réserve, que le JS écrit en fin de liste. Le nom de la famille
			// vient de l'appelant, au singulier et sans pluriel connu : la phrase n'a pas à l'accorder.
			$h .= '<div class="util-omnibox-list" data-rest="'.encode(s("Encore {value} dans {group}", ['value' => '{value}', 'group' => '{group}'])).'"></div>';

			if($folded) {
				$h .= '</div>';
			}

			// Le gabarit du jeton posé en JS : son icône est celle du thème, que le JS
			// ne sait pas fabriquer.
			$h .= '<template class="util-omnibox-token-template"><a class="util-omnibox-token">'.\Asset::icon('x-lg').'</a></template>';

		$h .= '</div>';

		// Les mêmes valeurs que UtilOmnibox.synchronizeFields(), qui les refait à chaque changement de jeton.
		if($name !== NULL) {

			$form = new \util\FormUi();

			foreach($tokens as $token) {
				$h .= $form->hidden($name.'[]', $token['value'] ?? $token['label'], ['data-omnibox-field' => TRUE]);
			}

		}

		return $h;

	}

	private static function getToken(array $token): string {

		$h = '<a class="util-omnibox-token"';
		$h .= ' data-group="'.encode($token['group'] ?? '').'"';
		$h .= ' data-value="'.encode($token['value'] ?? $token['label']).'"';
		$h .= ' data-label="'.encode($token['label']).'"';
		if(isset($token['url'])) {
			$h .= ' href="'.$token['url'].'"';
		}
		$h .= '><span class="util-omnibox-token-label">'.encode($token['label']).'</span>'.\Asset::icon('x-lg').'</a>';

		return $h;

	}

	/**
	 * La rangée des jetons d'une boîte repliée. Elle reste dans la boîte, que le JS lit pour savoir ce qui est
	 * posé, et c'est l'en-tête qui la place sous le titre.
	 *
	 * Le décompte y est recopié pour le téléphone, où le champ reste une loupe même filtré : sans lui, le
	 * nombre de lignes que laisse un filtre ne se lirait nulle part.
	 */
	private static function getFoldedTokens(array $tokens, ?string $count, ?string $clear): string {

		$h = '<div class="util-omnibox-tokens">';

			foreach($tokens as $token) {
				$h .= self::getToken($token);
			}

			$h .= '<a class="util-omnibox-clear-all"';
			if($clear !== NULL) {
				$h .= ' href="'.$clear.'"';
			}
			$h .= '>'.s("Tout effacer").'</a>';

			if($count !== NULL and $count !== '') {
				$h .= '<span class="util-omnibox-tokens-count">'.encode($count).'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	private static function getClear(?string $clear): string {

		$h = '<a class="util-omnibox-clear"';
		if($clear !== NULL) {
			$h .= ' href="'.$clear.'"';
		}
		$h .= '>'.\Asset::icon('x-lg').'</a>';

		return $h;

	}

}
