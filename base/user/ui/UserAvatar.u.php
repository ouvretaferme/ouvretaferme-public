<?php
namespace user;

class UserAvatarUi {

	public function __construct() {

		\Asset::css('user', 'avatar.css');
		\Asset::js('user', 'avatar.js');

	}

	/**
	 * L'éditeur : l'avatar qu'on fait tourner à gauche, les choix du catalogue fermé à droite, de la
	 * composition que porte `$eUserAvatar`, enregistrée ou par défaut. Le catalogue va tel quel à l'éditeur, qui n'offre
	 * que les familles rendues ici. Une palette partagée (`UserAvatarLib::getSharedPalettes()`) a sa propre rangée, après
	 * la dernière famille qui la prend, et aucune de ses familles n'a la sienne.
	 *
	 * Un modèle 3D téléversé se choisit dans un second onglet, en tête du panneau comme pour le bâtiment du décor, quand
	 * l'envoi est ouvert ou qu'un modèle est en place. L'onglet ouvert se décide ici, sur le modèle en place. Le pied
	 * n'enregistre qu'une composition : sur l'onglet du modèle, les gestes sont dans son contenu, et l'éditeur cache le pied
	 * (`UserAvatarEditor.refreshFooter()`).
	 */
	public function update(array $catalog, array $families, array $sharedPalettes, UserAvatar $eUserAvatar): \Panel {

		$eUserAvatarUpload = $eUserAvatar['eUserAvatarUpload'];
		$tab = $eUserAvatarUpload->exists() ? 'upload' : 'compose';
		$tabbed = ($eUserAvatar['uploadOpen'] or $eUserAvatarUpload->exists());

		// Les choix et l'aperçu font leur propre grille (util-composer)
		$form = new \util\FormUi(['columns' => 1]);

		// Le modèle en place voyage avec l'aperçu, dans le corps du panneau : Lime.Panel.dialog() garde le formulaire d'un
		// panneau qu'on recharge, et ce qu'il porterait resterait celui de la première ouverture
		$preview = '<div class="util-composer-preview" '.$this->getModelAttributes($eUserAvatarUpload).'>';
			$preview .= new \scene\SceneUi()->getContainer('user-avatar-scene', []);
			$preview .= '<a class="btn btn-outline-primary '.($tab === 'compose' ? '' : 'hide').'" data-avatar-random="true">'.\Asset::icon('shuffle').' '.s("Au hasard").'</a>';
		$preview .= '</div>';

		if($tabbed) {
			$h = new \scene\UploadUi()->getTabs($tab, [
				'compose' => [s("Composer en ligne"), $this->getComposition($form, $catalog, $families, $sharedPalettes, $eUserAvatar)],
				'upload' => [s("Importer un modèle 3D"), $this->getUpload($form, $eUserAvatar)],
			], 'UserAvatarEditor', $preview);
		} else {
			$h = '<div class="util-composer">'.$preview.'<div>'.$this->getComposition($form, $catalog, $families, $sharedPalettes, $eUserAvatar).'</div></div>';
		}

		// Lime.Panel.paint() peint le pied après le corps, et dialogOpen ne joue pas de onrender : l'éditeur, qui a
		// besoin des deux, démarre du pied
		$footer = '<div class="util-action" onrender="UserAvatarEditor.start(this.closest(\'form\'))">';
			$footer .= $form->submit(s("Enregistrer mon avatar"), [
				'data-confirm' => $eUserAvatarUpload->exists() ? $this->getSaveConfirm($eUserAvatar) : NULL,
			]);
			$footer .= $this->getDeleteButton($eUserAvatar);
		$footer .= '</div>';

		// Pas de data-ajax-form : l'image de l'avatar se rend dans la page au moment d'enregistrer, et c'est
		// UserAvatarEditor qui poste, la composition et l'image ensemble, ou le modèle choisi et l'image du modèle
		return new \Panel(
			id: 'panel-user-avatar',
			title: s("Mon avatar"),
			dialogOpen: $form->open(NULL, [
				'class' => 'panel-dialog',
				'data-catalog' => json_encode($catalog),
				'data-url' => '/user/avatar:doSave',
				'data-headless' => $eUserAvatar['headless'] ? 'true' : NULL,
			]),
			dialogClose: $form->close(),
			body: $h,
			footer: $footer
		);

	}

	/**
	 * Ce que l'éditeur lit du modèle en place, son adresse et son index, rien sans modèle.
	 */
	protected function getModelAttributes(UserAvatarUpload $eUserAvatarUpload): string {

		if($eUserAvatarUpload->exists() === FALSE) {
			return '';
		}

		return attr('data-upload-url', UserAvatarUploadUi::url($eUserAvatarUpload)).' '.attr('data-upload-index', json_encode($eUserAvatarUpload['bakedIndex']));

	}

	// Enregistrer une composition retire le modèle en place
	public function getSaveConfirm(UserAvatar $eUserAvatar): string {

		if($eUserAvatar['composed']) {
			return s("Revenir à votre avatar composé ? Votre modèle 3D sera retiré.");
		} else {
			return s("Enregistrer cet avatar ? Votre modèle 3D sera retiré.");
		}

	}

	/**
	 * Toujours au pied, masquée tant qu'il n'y a pas d'avatar : un premier import crée l'avatar sans repeindre le pied, que
	 * l'éditeur a démarré (`avatarUpload.v.php`), et la suppression n'a alors qu'à se montrer.
	 */
	public function getDeleteButton(UserAvatar $eUserAvatar): string {

		if($eUserAvatar->exists()) {
			return '<a data-ajax="/user/avatar:doDelete" class="btn btn-outline-primary user-avatar-delete" data-confirm="'.$this->getDeleteConfirm($eUserAvatar).'">'.s("Supprimer mon avatar").'</a>';
		} else {
			return '<a data-ajax="/user/avatar:doDelete" class="btn btn-outline-primary user-avatar-delete hide">'.s("Supprimer mon avatar").'</a>';
		}

	}

	public function getDeleteConfirm(UserAvatar $eUserAvatar): string {

		if($eUserAvatar['photo'] === NULL) {
			return s("Supprimer votre avatar ?");
		} else {
			return s("Supprimer votre avatar ? Votre photo de profil reviendra à sa place.");
		}

	}

	protected function getComposition(\util\FormUi $form, array $catalog, array $families, array $sharedPalettes, UserAvatar $eUserAvatar): string {

		$composition = $eUserAvatar['composition'];

		$h = $form->group(s("Peau"), $this->getSkin($form, $catalog['palettes']['skin'], $composition['peau']));

		foreach($families as $family => ['entries' => $entries, 'optional' => $optional, 'palette' => $palette]) {

			$choices = $form->choices($family, $this->getEntries($family, $optional ? [NULL, ...$entries] : $entries), $composition[$family]);

			// Les teintes d'une famille vide, ou d'une entrée qui n'a rien à colorer (`untinted`), sont sans effet : leur
			// rangée est retirée tant que la famille porte l'une ou l'autre
			if($palette !== NULL and array_key_exists($palette, $sharedPalettes) === FALSE) {
				$untinted = ($composition[$family] === NULL or in_array($composition[$family], $catalog['untinted'], TRUE));
				$choices .= $this->getTints($form, $family.'-teinte', $catalog['palettes'][$palette], $composition[$family.'-teinte'], $untinted);
			}

			$h .= $form->group(self::p('family')->values[$family], $choices);

			foreach($sharedPalettes as $paletteShared => ['after' => $after, 'worn' => $worn]) {

				if($after === $family) {
					$h .= $this->getSharedTints($form, $paletteShared, $catalog['palettes'][$paletteShared], $composition[$paletteShared], $worn);
				}

			}

		}

		$h .= $this->getPhotoChoice($form, $eUserAvatar);

		return $h;

	}

	/**
	 * Le choix de la photo de profil quand on enregistre : une composition, ou un modèle vérifié sans modèle en place.
	 */
	public function getPhotoChoice(\util\FormUi $form, UserAvatar $eUserAvatar): string {
		return $form->dynamicGroup($eUserAvatar, 'replacesPhoto');
	}

	/**
	 * L'onglet du modèle 3D, ouvert à tous, avec ou sans composition enregistrée. Supprimé, le modèle laisse la place à la
	 * composition, ou à l'avatar par défaut des scènes pour qui n'en a pas. Un modèle en place se montre même envoi fermé,
	 * pour qu'on puisse toujours le supprimer, et reste au-dessus du dépôt pendant qu'un autre fichier se vérifie : sa carte
	 * porte l'interrupteur de la photo de profil, appliqué tout de suite à ce modèle-là.
	 */
	public function getUpload(\util\FormUi $form, UserAvatar $eUserAvatar): string {

		$h = '';

		if($eUserAvatar['eUserAvatarUpload']->exists()) {

			// Le modèle laisse la place à la composition, dont l'éditeur rend l'image avant de poster quand elle remplace
			// la photo, ou à rien : l'avatar part avec lui, et la photo revient sans image à rendre
			$h .= '<div class="util-card mb-1" data-avatar-upload-placed="true">';
				$h .= '<p class="mb-1">'.\Asset::icon('box').' '.s("Modèle 3D en place depuis le {value}", \util\DateUi::numeric($eUserAvatar['eUserAvatarUpload']['createdAt'], \util\DateUi::DATE)).'</p>';
				$h .= $this->getPhotoSwitch($eUserAvatar);
				$h .= '<a class="btn btn-outline-primary mt-1" data-avatar-upload-clear="'.($eUserAvatar['composed'] ? 'composition' : 'default').'" data-waiter="'.s("Suppression...").'" data-confirm="'.s("Supprimer ce modèle 3D ?").'">'.\Asset::icon('trash').' '.s("Supprimer ce modèle").'</a>';
			$h .= '</div>';

		}

		if($eUserAvatar['uploadOpen']) {
			$h .= $this->getUploadDrop($form, $eUserAvatar);
		}

		return $h;

	}

	/**
	 * L'interrupteur de la photo de profil dans la carte du modèle en place. L'éditeur poste la valeur qu'il porte, avec l'image du
	 * modèle quand il se met à remplacer la photo (`avatarUpload:doPhoto`), et le laisse inactif jusqu'à la réponse, qui le
	 * repeint basculé : il ne bascule pas avant que la photo ait changé.
	 */
	public function getPhotoSwitch(UserAvatar $eUserAvatar): string {

		return $this->getPhotoLine(\util\TextUi::switch([
			'data-avatar-photo' => $eUserAvatar['replacesPhoto'] ? '0' : '1',
			'data-waiter' => ''
		], $eUserAvatar['replacesPhoto'], s("Oui"), s("Non")), 'mb-0', 'user-avatar-photo');

	}

	/**
	 * Le même choix sur un modèle vérifié qui n'est pas encore en place : l'interrupteur ne poste rien, sa valeur part avec
	 * le modèle. Un nom à lui, parce que le choix de « Composer en ligne » porte déjà `replacesPhoto` dans le même formulaire ;
	 * l'éditeur le lit et le poste sous `replacesPhoto`.
	 */
	protected function getPhotoSwitchVerified(\util\FormUi $form, UserAvatar $eUserAvatar): string {

		return $this->getPhotoLine($form->switch('replacesPhotoUpload', $eUserAvatar['replacesPhoto'] ? '1' : '0', [
			'valueOn' => '1',
			'valueOff' => '0',
			'labelOn' => s("Oui"),
			'labelOff' => s("Non")
		]), 'mb-1');

	}

	// Les deux interrupteurs se présentent de la même façon, que le choix s'applique tout de suite ou avec le modèle. Sur
	// la carte du modèle en place, la ligne n'a pas de marge : c'est la suppression qui s'écarte d'elle, et la carte garde
	// son assise quand la suppression se cache derrière un modèle vérifié (`UserAvatarEditor.refreshDrop()`)
	protected function getPhotoLine(string $switch, string $margin, ?string $id = NULL): string {

		$h = '<div class="form-group '.$margin.'" '.($id === NULL ? '' : attr('id', $id)).'>';
			$h .= $switch.' <span>'.self::p('replacesPhoto')->label.'</span>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le dépôt d'un seul fichier et l'aide du skill, partagés avec le bâtiment du décor (`\scene\UploadUi`). Le choix de la
	 * photo de profil se pose sur le modèle vérifié tant qu'aucun modèle n'est en place ; ensuite, c'est l'interrupteur de la
	 * carte du modèle en place (`getPhotoSwitch()`) qui le dit, et un nouveau modèle le garde.
	 */
	protected function getUploadDrop(\util\FormUi $form, UserAvatar $eUserAvatar): string {

		$rules = $eUserAvatar['uploadRules'];
		$placed = $eUserAvatar['eUserAvatarUpload']->exists();

		$uploadUi = new \scene\UploadUi();

		return $uploadUi->getDrop(
			$form,
			'UserAvatarEditor',
			'/user/avatarUpload:doCreate',
			'/user/avatarUpload:doPreview',
			$rules,
			'user\\UserAvatarUpload::file.conformity',
			$placed,
			[
				'head' => s("Glissez votre modèle 3D ici"),
				'headReplace' => s("Glissez un autre fichier ici"),
				'chooseReplace' => s("Choisir un autre fichier"),
				'tooHeavy' => AlertUi::getAvatarUploadWeight(\util\TextUi::size('{size}'), \util\TextUi::size($rules['maxBytes'])),
				'commitment' => s("Ce modèle me représente ou ne représente personne, il est habillé, ne reprend aucune marque et respecte la réglementation en vigueur."),
				'add' => s("Choisir comme avatar"),
				'adding' => s("Mise en place..."),
			],
			$uploadUi->getSkill(
				'/user/avatarUpload:skill',
				s("Téléchargez le skill de l'avatar."),
				s("Demandez-lui votre avatar, d'après une photo ou une description de vous.")
			),
			verified: $placed ? '' : $this->getPhotoSwitchVerified($form, $eUserAvatar)
		);

	}

	/**
	 * La peau va d'un bout à l'autre d'une seule échelle : une rampe, un cran par teinte de la palette, dans l'ordre
	 * où le catalogue les donne, du plus clair au plus foncé. Le curseur porte le rang de la teinte.
	 */
	protected function getSkin(\util\FormUi $form, array $colors, string $selected): string {

		$ramp = 'linear-gradient(to right, '.implode(', ', $colors).')';

		$h = '<div class="user-avatar-ramp">';
			$h .= $form->range('peau', 0, count($colors) - 1, 1, array_search($selected, $colors, TRUE), [
				'style' => '--user-avatar-ramp: '.$ramp
			]);
			$h .= '<div class="user-avatar-ramp-ticks">'.str_repeat('<span></span>', count($colors)).'</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les teintes d'une famille : les pastilles de sa palette, puis celle de la couleur libre, qui ouvre ses deux
	 * curseurs dans un menu. Une couleur enregistrée hors de la palette est la couleur libre de la famille, que sa
	 * pastille porte, cochée.
	 */
	protected function getTints(\util\FormUi $form, string $name, array $colors, string $selected, bool $hidden): string {

		$free = in_array($selected, $colors, TRUE) ? NULL : $selected;

		// FormUi::colorChoices() ferme sa rangée : la couleur libre a la sienne, que util-composer-tints met au bout de la première
		$h = '<div class="util-composer-tints '.($hidden ? 'hide' : '').'" data-avatar-tints="'.$name.'">';

			$h .= $form->colorChoices($name, $colors, $selected);

			$h .= '<div class="util-choices util-choices-color">';

				// Sans couleur libre, la pastille n'a rien à cocher : son bouton radio attend, caché, et elle prend
				// elle-même le clavier
				$h .= '<label class="util-choice user-avatar-free '.($free === NULL ? '' : 'user-avatar-free-filled').'" data-dropdown="bottom-start" data-avatar-free="true" '.($free === NULL ? 'tabindex="0"' : '').'>';
					$h .= $form->inputRadio($name, $free, NULL, $selected, ['hidden' => ($free === NULL) ? 'hidden' : NULL]);
					$h .= '<span '.($free === NULL ? '' : 'style="--choice-color: '.encode($free).'"').'>'.\Asset::icon('plus-lg').\Asset::icon('eyedropper').'</span>';
				$h .= '</label>';

				$h .= '<div class="dropdown-list dropdown-list-light user-avatar-tune" data-dropdown-keep="true">';

					$hue = $form->range($name.'Hue', 0, 359, 1, 0, ['data-avatar-hue' => 'true']);
					$h .= '<label for="'.$form->getLastFieldId().'">'.s("Teinte").'</label>'.$hue;

					$lightness = $form->range($name.'Lightness', UserSetting::AVATAR_LIGHTNESS_MIN, UserSetting::AVATAR_LIGHTNESS_MAX, 1, 50, ['data-avatar-lightness' => 'true']);
					$h .= '<label for="'.$form->getLastFieldId().'">'.s("Clarté").'</label>'.$lightness;

				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les teintes d'une palette partagée, sans couleur libre : le métal des bijoux est l'acier, l'or ou le noir. Le groupe
	 * entier se retire tant qu'aucune de ses familles n'est portée.
	 */
	protected function getSharedTints(\util\FormUi $form, string $palette, array $colors, string $selected, bool $worn): string {

		return $form->group(self::p('palette')->values[$palette], $form->colorChoices($palette, $colors, $selected), [
			'class' => $worn ? '' : 'hide',
			'data-avatar-tints' => $palette,
		]);

	}

	/**
	 * Une vignette par entrée, rendue d'avance sur l'avatar par défaut : monter un avatar par vignette figerait la page.
	 */
	protected function getEntries(string $family, array $entries): array {

		$values = [];

		foreach($entries as $entry) {

			if($entry === NULL) {
				$values[''] = ['label' => self::p('none')->values[$family], 'image' => NULL];
			} else {
				$values[$entry] = ['label' => self::p('entry')->values[$entry], 'image' => \Asset::image('user', 'avatar/'.$family.'-'.$entry.'.png')];
			}

		}

		return $values;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = UserAvatar::model()->describer($property, [
			'replacesPhoto' => s("Utiliser mon avatar comme photo de profil"),
			'family' => s("Famille"),
			'none' => s("Aucun"),
			'entry' => s("Choix"),
			'palette' => s("Teinte"),
		]);

		switch($property) {

			case 'replacesPhoto' :
				$d->field = 'yesNo';
				break;

			case 'family' :
				$d->values = [
					'yeux' => s("Yeux"),
					'bouche' => s("Bouche"),
					'aspect' => s("Aspect de la peau"),
					'taches' => s("Taches de rousseur"),
					'coiffure' => s("Coiffure"),
					'haut' => s("Haut"),
					'motif' => s("Motif"),
					'veste' => s("Veste"),
					'bas' => s("Bas"),
					'chaussures' => s("Chaussures"),
					'couvre-chef' => s("Couvre-chef"),
					'lunettes' => s("Lunettes"),
					'barbe' => s("Pilosité"),
					'oreilles' => s("Bijoux d'oreilles"),
					'cou' => s("Au cou"),
					'poignet' => s("Au poignet"),
				];
				break;

			case 'none' :
				$d->values = [
					'taches' => s("Aucune"),
					'motif' => s("Aucun"),
					'veste' => s("Aucune"),
					'couvre-chef' => s("Aucun"),
					'lunettes' => s("Aucune"),
					'barbe' => s("Aucune"),
					'oreilles' => s("Aucun"),
					'cou' => s("Rien"),
					'poignet' => s("Rien"),
				];
				break;

			case 'entry' :
				$d->values = [
					'yeux-rond' => s("Ronds"),
					'yeux-amande' => s("En amande"),
					'yeux-tombant' => s("Tombants"),
					'yeux-rieur' => s("Rieurs"),
					'yeux-petit' => s("Petits"),
					'yeux-allonge' => s("Allongés"),
					'bouche-moyenne' => s("Moyenne"),
					'bouche-fine' => s("Fine"),
					'bouche-pleine' => s("Pleine"),
					'bouche-large' => s("Large"),
					'bouche-petite' => s("Petite"),
					'peau-lisse' => s("Lisse"),
					'peau-burinee' => s("Burinée"),
					'peau-rosee' => s("Rosée"),
					'peau-marquee' => s("Marquée"),
					'rousseur-legere' => s("Légères"),
					'rousseur-dense' => s("Denses"),
					'rase' => s("Rasée"),
					'boule-a-zero' => s("Boule à zéro"),
					'courte' => s("Courte"),
					'epis-courts' => s("Courte en épis"),
					'degarnie' => s("Dégarnie"),
					'nattes-collees' => s("Nattes collées"),
					'tresses-dessus' => s("Tresses sur le dessus"),
					'carre' => s("Carré court"),
					'mi-longue' => s("Mi-longue"),
					'bataille' => s("En bataille"),
					'frisee' => s("Frisée"),
					'afro' => s("Afro"),
					'locks' => s("Locks"),
					'cheveux-longs' => s("Longue"),
					'longs-raie' => s("Longue avec raie"),
					'longs-ondules' => s("Longue ondulée"),
					'longs-degarnis' => s("Longue dégarnie"),
					'tresses-africaines' => s("Tresses africaines"),
					'queue-de-cheval' => s("Queue de cheval"),
					'queue-tressee' => s("Queue tressée"),
					'tresse' => s("Tresse"),
					't-shirt' => s("T-shirt"),
					'polo' => s("Polo"),
					'pull' => s("Pull"),
					'polaire-zip' => s("Polaire"),
					'sweat-capuche' => s("Sweat à capuche"),
					'debardeur' => s("Débardeur"),
					'chemise' => s("Chemise"),
					'motif-rayures' => s("Rayures"),
					'motif-carreaux' => s("Carreaux"),
					'motif-camouflage' => s("Camouflage"),
					'motif-pois' => s("Pois"),
					'motif-fleurs' => s("Fleurs"),
					'gilet' => s("Gilet"),
					'doudoune-longue' => s("Doudoune"),
					'veste-pluie' => s("Veste de pluie"),
					'veste-travail' => s("Veste de travail"),
					'pantalon' => s("Pantalon"),
					'salopette' => s("Salopette"),
					'short-allege' => s("Short"),
					'bermuda' => s("Bermuda"),
					'jean' => s("Jean"),
					'pantalon-travail' => s("Pantalon de travail"),
					'legging' => s("Legging"),
					'jupe-allegee' => s("Jupe"),
					'chaussures' => s("Chaussures basses"),
					'bottes' => s("Bottes"),
					'chaussures-ville' => s("Chaussures de ville"),
					'chaussures-marche' => s("Chaussures de marche"),
					'chapeau-paille' => s("Chapeau de paille"),
					'bob' => s("Bob"),
					'chapeau-feutre' => s("Chapeau de feutre"),
					'bonnet' => s("Bonnet"),
					'casquette' => s("Casquette"),
					'cow-boy' => s("Chapeau de cow-boy"),
					'chapeau-conique' => s("Chapeau conique"),
					'beret' => s("Béret"),
					'bandeau-frontal' => s("Bandeau"),
					'fichu' => s("Fichu"),
					'hijab-travail' => s("Hijab"),
					'chapeau-brousse' => s("Chapeau de brousse"),
					'casquette-plate' => s("Casquette plate"),
					'turban' => s("Turban"),
					'charlotte' => s("Charlotte"),
					'cagoule' => s("Cagoule"),
					'lunettes-rondes' => s("Rondes"),
					'lunettes-rectangle' => s("Rectangulaires"),
					'lunettes-demi-lune' => s("Demi-lune"),
					'lunettes-chat' => s("Œil-de-chat"),
					'lunettes-soleil' => s("De soleil"),
					'lunettes-aviateur' => s("Aviateur"),
					'lunettes-coeur' => s("En cœur"),
					'lunettes-etoile' => s("En étoile"),
					'lunettes-securite' => s("De sécurité"),
					'barbe-courte' => s("Barbe courte"),
					'moustache' => s("Moustache"),
					'moustache-fournie' => s("Moustache fournie"),
					'bouc' => s("Bouc"),
					'favoris' => s("Favoris"),
					'barbe-longue' => s("Barbe longue"),
					'clous-oreilles' => s("Clous"),
					'anneaux-oreilles' => s("Anneaux"),
					'creoles-oreilles' => s("Créoles"),
					'pendants-oreilles' => s("Pendants"),
					'plumes-oreilles' => s("Plumes"),
					'triangles-oreilles' => s("Triangles"),
					'etoiles-oreilles' => s("Étoiles"),
					'carottes-oreilles' => s("Carottes"),
					'cerises-oreilles' => s("Cerises"),
					'chaine' => s("Chaîne"),
					'pendentif' => s("Pendentif"),
					'bracelet' => s("Bracelet"),
					'montre' => s("Montre"),
					'montre-sport' => s("Montre de sport"),
					'manchette' => s("Manchette"),
					'bracelet-perles' => s("Perles"),
					'bracelet-bresilien' => s("Brésilien"),
					'bracelet-cuir' => s("Cuir"),
				];
				break;

			case 'palette' :
				$d->values = [
					'metal' => s("Métal des bijoux"),
				];
				break;

		}

		return $d;

	}

}
?>
