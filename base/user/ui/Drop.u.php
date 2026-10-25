<?php
namespace user;

/**
 * Handle user signup and profile edition
 */
class DropUi {

	/**
	 * Display button to close its account
	 */
	public function close(User $eUser, bool $canCloseDelay): string {

		$eUser->expects(['deletedAt', 'cStructureOpen']);

		$h = '';

		if($eUser['deletedAt']) {

			$h .= '<div class="util-danger">'.$this->getCloseMessage($eUser['deletedAt']).'</div>';

			// Mis au planning pendant le délai, le compte attend d'en être sorti (`DropLib::closeExpired()`).
			if($eUser['cStructureOpen']->notEmpty()) {
				$h .= '<div class="util-info">'.p("Votre compte sera clôturé quand {list} vous aura sorti de son planning.", "Votre compte sera clôturé quand {list} vous auront sorti de leur planning.", $eUser['cStructureOpen']->count(), ['list' => self::getStructures($eUser['cStructureOpen'])]).'</div>';
			}

			$h .= '<a data-ajax="/user/close:do" class="btn btn-primary">'.s("Annuler la suppresssion de mon compte").'</a>';

		} else if($eUser['cStructureOpen']->notEmpty()) {

			$h .= '<div class="util-info">'.$this->getClosePresence($eUser['cStructureOpen']).'</div>';

		} else {

			if($canCloseDelay) {

				$h .= \main\DesignUi::getWeigh(
					'danger',
					'person-x',
					s("Supprimer mon compte"),
					s("Vous disposez d'un délai de réflexion de {value} jours pour revenir sur votre décision.", UserSetting::CLOSE_TIMEOUT),
					[
						['icon' => 'box-arrow-right', 'text' => s("<b>Votre compte</b> sera désactivé au bout de {value} jours, et vous ne pourrez plus vous y connecter.", UserSetting::CLOSE_TIMEOUT)],
						['icon' => 'trash', 'text' => s("<b>Vos données</b> seront supprimées au bout de {value} jours.", UserSetting::CLOSE_TIMEOUT)]
					],
					[s("Vous pouvez utiliser votre compte jusqu'à la fin du délai.")],
					TRUE
				);

				$h .= '<a data-ajax="/user/close:do" class="btn btn-danger">'.s("Supprimer mon compte").'</a>';

			} else {
				$h .= '<div class="util-info">'.s("Par sécurité, vous ne pouvez supprimer votre compte que {value} minutes après vous être connecté. Vous devez donc vous déconnecter et vous reconnecter sur cette page pour effectuer cette action.", UserSetting::CLOSE_TIME_LIMIT).'</div>';
			}

		}

		return $h;

	}

	/**
	 * Le refus de fermer un compte qui est au planning d'une structure (`DropLib::changeClose()`).
	 */
	public function getClosePresence(\Collection $cStructure): string {

		return p("Vous êtes au planning de {list}. Pour supprimer votre compte, demandez d'abord à être sorti de ce planning.", "Vous êtes au planning de {list}. Pour supprimer votre compte, demandez d'abord à être sorti de ces plannings.", $cStructure->count(), ['list' => self::getStructures($cStructure)]);

	}

	/**
	 * « a », « a et b », « a, b et c ».
	 */
	protected static function getStructures(\Collection $cStructure): string {

		$names = $cStructure->makeArray(fn(\structure\Structure $eStructure) => '<b>'.encode($eStructure['name']).'</b>');
		$last = array_pop($names);

		return ($names === []) ? $last : implode(', ', $names).' '.s("et").' '.$last;

	}

	/**
	 * Returns a message when a user has closed is account
	 */
	public function getCloseMessage($deletedAt): string {

		return s("Vous avez choisi de fermer votre compte. Il sera définitivement clôturé le {date} et vous ne pourrez plus vous y connecter. Vous pouvez continuer à l'utiliser librement avant cette date.", ['date' => \util\DateUi::numeric($deletedAt, \util\DateUi::DATE)]);

	}

}
?>
