<?php
namespace game;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return match($fqn) {

			'Player::name.duplicate' => s("Un autre joueur utilise déjà ce nom, trouvez un nom plus original !"),
			'Player::time.missing' => s("Vous n'avez plus assez de temps disponible !"),
			'Player::giftSentAt.motivate' => s("Votre ami est épuisé et n'a pas pu être remotivé."),

			'Tile::growing.seed' => s("Semis impossible !"),
			'Tile::growing.water' => s("Arrosage impossible !"),
			'Tile::growing.weed' => s("Désherbage impossible !"),
			'Tile::growing.harvest' => s("Récolte impossible !"),

			'Food::current.cook' => s("Préparation impossible, il vous manquait des légumes !"),
			'Food::current.eat' => s("Il ne s'est rien passé, vous aviez déjà mangé votre dernière soupe !"),

			'Friend::code.unknown' => s("Le code que vous avez saisi correspond à un ami imaginaire !"),
			'Friend::code.throttled' => s("Vous avez essayé beaucoup de codes, réessayez dans une heure."),

			default => null

		};

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'Action::eaten' => s("Bon appétit !<br/>Vous pouvez maintenant travailler {value} heures de plus !", \game\GameSetting::BONUS_SOUP),

			'Action::cooked' => s("Cuisson terminée !"),

			'Action::friendAdded' => s("Vous avez un nouvel ami !"),

			'Action::motivated' => s("Votre ami est remotivé et peut travailler plus longtemps !"),

			'Action::planted' => s("C'est semé !"),

			'Action::watered' => s("C'est arrosé !<br/>La culture est plus productive."),

			'Action::weeded' => s("C'est désherbé !<br/>Vous allez récolter cette parcelle plus tôt."),

			'Action::harvested' => s("C'est récolté !<br/>Vous attirez donc quelques rennes supplémentaires."),

			default => NULL

		};


	}

}
?>
