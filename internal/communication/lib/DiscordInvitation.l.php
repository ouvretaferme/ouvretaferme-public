<?php
namespace communication;

class DiscordInvitationLib extends DiscordInvitationCrud {

	public static function getByStructure(\structure\Structure $eStructure): DiscordInvitation {

		return DiscordInvitation::model()
			->select(DiscordInvitation::getSelection())
			->whereStructure($eStructure)
			->get();

	}

	public static function getByKey(string $key): DiscordInvitation {

		return DiscordInvitation::model()
			->select(DiscordInvitation::getSelection())
			->whereKey($key)
			->get();

	}

	// Deux créations simultanées butent sur UNIQUE(structure) : la seconde n'écrit rien, et sa relecture ne voit pas le lien de la première si elle tourne dans une transaction ouverte avant lui (REPEATABLE READ).
	public static function getOrCreate(\structure\Structure $eStructure): DiscordInvitation {

		$eDiscordInvitation = self::getByStructure($eStructure);

		if($eDiscordInvitation->notEmpty()) {
			return $eDiscordInvitation;
		}

		try {

			$eDiscordInvitation = new DiscordInvitation([
				'structure' => $eStructure,
			]);

			self::create($eDiscordInvitation);

		} catch(\DuplicateException) {
			return self::getByStructure($eStructure);
		}

		return $eDiscordInvitation;

	}

}
?>
