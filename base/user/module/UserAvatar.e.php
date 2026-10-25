<?php
namespace user;

class UserAvatar extends UserAvatarElement {

	public function canRead(): bool {

		$this->expects(['user']);
		return $this['user']->is(ConnectionLib::getOnline());

	}

	/**
	 * L'avatar tient lieu de photo de profil : son image est `User.vignette`, et la photo attend sur `photo`.
	 */
	public function isReplacingPhoto(): bool {

		if($this->empty()) {
			return FALSE;
		}

		$this->expects(['replacesPhoto']);
		return $this['replacesPhoto'];

	}

	/**
	 * Un avatar créé par l'import d'un modèle 3D, sans passer par l'éditeur, n'a pas de composition : il n'existe que tant
	 * que le modèle est en place, et part avec lui (`UserAvatarUploadLib::clear()`).
	 */
	public function hasComposition(): bool {

		$this->expects(['composition']);
		return ($this['composition'] !== NULL);

	}

	/**
	 * L'image de l'avatar se rend de nouveau quand elle est un JPEG : le JPEG n'a pas de transparence, et une image
	 * enregistrée avant que la vignette d'utilisateur n'admette le PNG en production a perdu son fond, devenu noir ou
	 * gris. Seul un avatar qui remplace la photo a son image sur `User.vignette`, et la photo mise de côté sur `photo`
	 * n'est jamais celle qu'on remplace.
	 */
	public function acceptRerender(): bool {

		$this->expects(['replacesPhoto', 'photo', 'user' => ['vignette']]);

		$vignette = $this['user']['vignette'];

		return (
			$this['replacesPhoto'] and
			$vignette !== NULL and
			\media\MediaUi::getExtension($vignette) === 'jpg' and
			($this['photo'] === NULL or substr($this['photo'], 0, 20) !== substr($vignette, 0, 20))
		);

	}

	/**
	 * Une composition de l'éditeur porte une valeur par famille du catalogue fermé (`UserAvatarLib::getFamilies()`),
	 * la teinte de chaque famille qui en a une et la peau : ni clé de plus, ni clé de moins. La teinte d'une famille
	 * laissée vide reste posée, pour que l'éditeur la retrouve si la famille revient. Une teinte est une couleur de la
	 * palette de sa famille ou une couleur libre ; la peau n'a que sa palette. Les familles d'une palette partagée
	 * (`UserAvatarLib::getTintKey()`) ont une seule teinte, sous le nom de la palette, prise dans la palette seule.
	 */
	public static function properties(\PropertyList $l): void {

		// La composition se poste en JSON, comme le tracé d'un décor (`map\Decor`) : un `json` est converti en
		// tableau par le cadriciel sans être décodé
		$l->property('composition')
			->replaceCast(function(mixed &$composition): bool {

				if(is_string($composition)) {
					$composition = json_decode($composition, TRUE);
				}

				return TRUE;

			})
			// La forme se juge ici et non dans la conversion, pour que tout refus porte `composition.check`
			->replaceCheck(function(mixed $composition): bool {

				// Le JSON posté se décode en n'importe quelle valeur, un nombre ou une chaîne compris, et en NULL s'il est invalide
				if(is_array($composition) === FALSE) {
					return FALSE;
				}

				$catalog = UserAvatarLib::getCatalog();
				$families = UserAvatarLib::getFamilies();

				$expected = UserAvatarLib::getCompositionKeys();

				$keys = array_keys($composition);
				sort($keys);
				sort($expected);

				if(
					$keys !== $expected or
					in_array($composition['peau'], $catalog['palettes']['skin'], TRUE) === FALSE
				) {
					return FALSE;
				}

				foreach($families as $family => ['entries' => $entries, 'optional' => $optional]) {

					if(in_array($composition[$family], $entries, TRUE) === FALSE and ($optional === FALSE or $composition[$family] !== NULL)) {
						return FALSE;
					}

				}

				return TRUE;

			})
			->addAfterCheck('tint', function(array $composition): bool {

				$palettes = UserAvatarLib::getCatalog()['palettes'];

				foreach(UserAvatarLib::getFamilies() as $family => ['palette' => $palette]) {

					$tintKey = UserAvatarLib::getTintKey($family);

					if($tintKey !== NULL and UserAvatarLib::isTint($composition[$tintKey], $palettes[$palette], free: ($tintKey !== $palette)) === FALSE) {
						return FALSE;
					}

				}

				return TRUE;

			})
			->addAfterCheck('excluded', function(array $composition): bool {
				return (UserAvatarLib::isExcluded($composition, UserAvatarLib::getCatalog()['excluded']) === FALSE);
			});

	}

}
?>
