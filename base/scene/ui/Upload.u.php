<?php
namespace scene;

/**
 * Ce que partagent les éditeurs qui acceptent un modèle 3D téléversé à la place d'une composition, l'avatar
 * (`user\UserAvatarUi`) et le bâtiment du décor (`map\DecorBuildingUi`) : les deux onglets, le dépôt d'un fichier et
 * l'aide à préparer un modèle avec Claude. Chaque éditeur passe son gestionnaire JS, qui tient l'aperçu et le pied du
 * panneau, et délègue le dépôt à `SceneUploadDrop` (`static/js/upload.js`) ; il passe aussi ses mots, l'avatar parlant de
 * « votre modèle » et le bâtiment « du modèle ».
 */
class UploadUi {

	public function __construct() {

		\Asset::js('scene', 'upload.js');

	}

	/**
	 * Deux chemins exclusifs : `tabs-exclusive` désactive le `fieldset` de l'onglet écarté (`Lime.Tab.select()`), et le fichier
	 * ne part jamais avec une composition, ni l'inverse. Sans `id`, l'onglet ouvert n'est jamais repris de `localStorage` : il
	 * se décide en PHP. `$tabs` porte, par onglet, son libellé et son contenu ; `$handler` est la classe JS dont `selectTab()`
	 * fait suivre l'aperçu et le pied. `$preview` est l'aperçu de `util-composer`, sous les onglets et à côté de leurs
	 * contenus : un seul aperçu sert les deux onglets, que le gestionnaire cache quand l'onglet n'a rien à montrer.
	 */
	public function getTabs(string $selected, array $tabs, string $handler, string $preview = ''): string {

		$h = '<div class="tabs tabs-exclusive">';

			$h .= '<div class="tabs-item tabs-form">';
				foreach($tabs as $tab => [$label]) {
					$h .= '<a class="tab-item '.($tab === $selected ? 'selected' : '').'" data-tab="'.$tab.'" onclick="'.$handler.'.selectTab(this)">'.$label.'</a>';
				}
			$h .= '</div>';

			$panel = '<div class="tabs-panel">';
				foreach($tabs as $tab => [, $content]) {
					$panel .= '<div class="tab-panel '.($tab === $selected ? 'selected' : '').'" data-tab="'.$tab.'">';
						$panel .= '<fieldset '.($tab === $selected ? '' : 'disabled').'>'.$content.'</fieldset>';
					$panel .= '</div>';
				}
			$panel .= '</div>';

			$h .= ($preview === '') ? $panel : '<div class="util-composer">'.$preview.$panel.'</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien qui ramène à l'onglet de la composition, depuis l'onglet du modèle.
	 */
	public function getComposeLink(string $handler, string $label): string {

		return '<a onclick="'.$handler.'.selectTab(this.closest(\'.tabs\').qs(\'.tab-item[data-tab=compose]\'))">'.$label.'</a>';

	}

	/**
	 * Le dépôt d'un seul fichier (util-drop). Un refus du fichier, du serveur ou du poids lu par le navigateur, tombe sur le
	 * champ `file`, que porte la ligne du fichier : la ligne seule rougit, et le refus s'y dit une fois, sous le nom. Le débit
	 * ou l'import indisponible tombent sur le champ `throttled`, à côté de la liste, et rougissent le cadre : c'est l'envoi
	 * qui est en cause, pas le fichier.
	 *
	 * `$url` est l'action qui reçoit le fichier, `$preview` celle qui le vérifie sans rien écrire, `$rules` les règles de
	 * conversion (`maxBytes`, `maxTriangles`), `$fail` le nom du refus de conformité, sous lequel le navigateur dit aussi le poids. `$texts`
	 * porte les mots de l'éditeur : `head` et `headReplace` (l'appel du dépôt, sans puis avec un modèle en place),
	 * `chooseReplace` (le bouton qui choisit un autre fichier avec un modèle en place, aux deux largeurs), `tooHeavy` (le refus du poids, `{size}` à la
	 * place du poids du fichier), `commitment` (la case d'engagement), `add` (le bouton qui ajoute le modèle vérifié) et
	 * `adding` (ce que ce bouton dit pendant l'envoi).
	 * `$skill` est l'aide qui suit le dépôt (`getSkill()`). `$shared` est la case facultative de l'accord de partage, sous
	 * l'engagement, absente sans libellé. `$verified` porte les champs que l'éditeur pose sur le modèle vérifié, au-dessus
	 * des cases, et qui partent avec lui.
	 *
	 * Le geste qui termine est dans le bloc, jamais au pied du panneau : « Valider » poste le fichier à `$preview`, l'éditeur
	 * montre le modèle dans l'aperçu, et le bloc du modèle vérifié remplace le dépôt. On ne s'engage que là, sur un fichier
	 * qui passe : les cases y reviennent décochées à chaque fichier, et `add`, qui reposte le fichier avec elles à `$url`,
	 * attend l'engagement.
	 */
	public function getDrop(\util\FormUi $form, string $handler, string $url, string $preview, array $rules, string $fail, bool $replace, array $texts, string $skill, ?string $shared = NULL, string $verified = ''): string {

		$formats = s("Fichier .glb de {size} et {triangles} triangles au plus", ['size' => \util\TextUi::size($rules['maxBytes']), 'triangles' => \util\TextUi::number($rules['maxTriangles'])]);

		$input = '<input type="file" accept=".glb" class="hide" data-scene-upload-input="true"/>';

		$h = '<div data-scene-upload="'.$url.'" data-scene-upload-preview="'.$preview.'" data-fail="'.encode($fail).'" data-max-bytes="'.$rules['maxBytes'].'" data-too-heavy="'.encode($texts['tooHeavy']).'" data-choose-again="'.encode(s("Choisir un autre fichier")).'">';

			$h .= '<div data-scene-upload-pick="true">';

				// On ne glisse rien au téléphone : un grand bouton remplace l'appel
				$h .= '<div class="util-buttons hide-md-up mb-1" data-scene-upload-phone="true">';
					$h .= '<label class="util-button">';
						$h .= '<div>';
							$h .= '<h4>'.($replace ? $texts['chooseReplace'] : \main\DropAiUi::getChoose(s("Choisir un fichier"))).'</h4>';
							$h .= '<div class="util-button-text">'.$formats.'</div>';
						$h .= '</div>';
						$h .= \Asset::icon('folder2-open');
						$h .= $input;
					$h .= '</label>';
				$h .= '</div>';

				$h .= \main\DropAiUi::open($handler.'.drop(event, this)');

					$h .= \main\DropAiUi::getHead($replace ? $texts['headReplace'] : $texts['head'], $formats, $replace ? $texts['chooseReplace'] : s("Choisir un fichier"), $input, FALSE);
					$h .= \main\DropAiUi::getList();

					// Le refus du débit ou de l'import indisponible vise l'envoi et non le fichier : il a son champ à lui, qui
					// laisse le fichier en attente
					$h .= '<div data-wrapper="throttled"></div>';

					$h .= \main\DropAiUi::getFoot('<button type="button" class="btn btn-primary" data-scene-upload-validate="true" data-waiter="'.s("Vérification...").'">'.s("Valider").'</button>');

				$h .= '</div>';

				$h .= $skill;

			$h .= '</div>';

			$checks = '<div data-wrapper="commitment" data-scene-upload-checks="true">';
				$checks .= $form->checkbox('commitment', '1', ['callbackLabel' => fn($input) => $input.' <span>'.$texts['commitment'].'</span>']);
			$checks .= '</div>';

			if($shared !== NULL) {
				$checks .= '<div data-wrapper="shared" data-scene-upload-checks="true">';
					$checks .= $form->checkbox('shared', '1', ['callbackLabel' => fn($input) => $input.' <span>'.$shared.'</span>']);
				$checks .= '</div>';
			}

			$h .= $this->getVerified($texts['add'], $texts['adding'], $verified, $checks);

			$h .= '<template data-scene-upload-row="true">';
				$h .= '<div class="util-table-row" data-state="waiting">';
					$h .= '<div>'.\Asset::icon('box').'</div>';
					$h .= '<div class="util-drop-name"><span data-scene-upload-name="true"></span> <span class="color-muted" data-scene-upload-size="true" data-byte="'.encode(s("{value}\u{00A0}octets", '{value}')).'" data-kilo="'.encode(s("{value}\u{00A0}Ko", '{value}')).'" data-mega="'.encode(\util\TextUi::size('{value}')).'"></span></div>';
					// Le champ `file` est sur la ligne : son refus remplace l'état et passe sous le nom (`util-drop-state`)
					$h .= '<div class="util-drop-state" data-wrapper="file">';
						$h .= '<span class="color-muted" data-drop-state="waiting">'.s("Non envoyé").'</span>';
						$h .= '<span class="color-muted" data-drop-state="pending">'.s("Vérification...").'</span>';
					$h .= '</div>';
					$h .= '<div><a class="util-previews-remove" data-scene-upload-remove="true">'.\Asset::icon('x-lg').'</a></div>';
				$h .= '</div>';
			$h .= '</template>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le modèle vérifié, à la place du dépôt : son fichier, avec la croix qui l'abandonne et revient au dépôt vide, les
	 * champs de l'éditeur (`$fields`), les cases (`$checks`), puis le bouton qui l'ajoute et celui qui en choisit un autre.
	 * `$waiter` est ce que le premier dit pendant l'envoi. `SceneUploadDrop.showVerified()` y recopie le nom et le poids de
	 * la ligne du fichier.
	 */
	protected function getVerified(string $add, string $waiter, string $fields, string $checks): string {

		$h = '<div class="util-card hide" data-scene-upload-verified="true">';
			$h .= '<div class="flex-align-center flex-justify-space-between mb-1">';
				$h .= '<span>'.\Asset::icon('box').' <span data-scene-upload-name="true"></span> <span class="color-muted" data-scene-upload-size="true"></span> <span class="color-success">'.\Asset::icon('check-lg').' '.s("Vérifié").'</span></span>';
				$h .= '<a class="util-previews-remove" data-scene-upload-remove="true">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';
			if($fields !== '') {
				$h .= '<div data-scene-upload-fields="true">'.$fields.'</div>';
			}
			$h .= $checks;
			$h .= '<div class="form-group mt-1">';
				$h .= '<div class="flex-buttons">';
					$h .= '<button type="button" class="btn btn-primary" data-scene-upload-add="true" data-waiter="'.encode($waiter).'">'.$add.'</button>';
					$h .= '<a class="btn btn-outline-primary" data-scene-upload-again="true">'.s("Choisir un autre fichier").'</a>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * L'aide à préparer un modèle avec Claude, repliée sous le dépôt au chargement de la page : le skill ne se télécharge
	 * qu'une fois dit à quoi il sert. Elle se retire dès qu'un fichier attend l'envoi (`SceneUploadDrop.waitFile()`).
	 * `$download` est l'étape qui nomme le skill, `$ask` celle qui dit quoi demander à Claude.
	 */
	public function getSkill(string $url, string $download, string $ask): string {

		$h = '<div class="util-fold mt-1" data-scene-upload-skill="true">';

			$h .= '<a class="util-fold-head" '.attr('onclick', 'this.closest(".util-fold").classList.toggle("util-fold-open")').'>';
				$h .= '<span>'.s("Préparer un modèle avec l'IA Claude").'</span>';
				$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
			$h .= '</a>';

			$h .= '<div class="util-fold-content">';
				$h .= '<ol class="util-procedure mb-0">';
					$h .= '<li class="util-procedure-step">';
						$h .= '<p class="util-procedure-title">'.s("Installez <blender>Blender</blender>, gratuit, et <claude>Claude Code</claude>.", [
							'blender' => '<a href="https://www.blender.org/download/" target="_blank">',
							'claude' => '<a href="https://code.claude.com/docs/fr/quickstart" target="_blank">',
						]).'</p>';
					$h .= '</li>';
					$h .= '<li class="util-procedure-step">';
						$h .= '<p class="util-procedure-title">'.$download.'</p>';
						$h .= '<div class="mt-1"><a href="'.$url.'" class="btn btn-outline-primary" data-ajax-download>'.\Asset::icon('download').' '.s("Télécharger le skill").'</a></div>';
					$h .= '</li>';
					$h .= '<li class="util-procedure-step">';
						$h .= '<p class="util-procedure-title">'.s("Dans Claude Code, demandez d'installer ce skill.").'</p>';
						$h .= '<p class="color-muted mb-0">'.s("Si Claude Code n'a pas le droit de l'installer, décompressez l'archive dans un dossier de votre choix et donnez-lui le chemin de ce dossier.").'</p>';
					$h .= '</li>';
					$h .= '<li class="util-procedure-step">';
						$h .= '<p class="util-procedure-title">'.$ask.'</p>';
					$h .= '</li>';
					$h .= '<li class="util-procedure-step">';
						$h .= '<p class="util-procedure-title">'.s("Importez ici le fichier .glb que Claude vous remet.").'</p>';
					$h .= '</li>';
				$h .= '</ol>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

}
?>
