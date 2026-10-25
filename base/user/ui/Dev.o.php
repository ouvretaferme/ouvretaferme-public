<?php
namespace user;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'user\User' => [
				'title' => s('Utilisateurs'),
				'plural' => s('utilisateurs'),
				'singular' => s('utilisateur'),
				'gender' => 'm',
				'description' => s('L\'identité d\'un utilisateur de Ouvretaferme. Objet en lecture seule, sans route propre, qui apparaît à l\'intérieur des ressources qui le référencent, comme les membres de votre structure.')
			]
		];

	}

	/** La photo de profil sort en adresse, pas sous la forme stockée en base, et l'adresse e-mail selon la visibilité du compte. */
	public static function apiFields(): array {

		return [
			'user\User' => [
				'email' => [
					'type' => 'string',
					'format' => 'email',
					'nullable' => TRUE,
					'description' => s("Adresse e-mail de l'utilisateur, ou null si son compte est privé"),
				],
				'vignette' => [
					'type' => 'string',
					'format' => 'uri',
					'nullable' => TRUE,
					'description' => s("Adresse de la photo de profil, ou null si l'utilisateur n'en a pas"),
				],
			],
		];

	}

}
?>
