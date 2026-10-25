<?php
namespace structure;

class DevObserverUi {

	public static function apiLabels(): array {

		return [
			'structure' => [
				'title' => s('Structure'),
				'description' => s("Votre structure et son organisation : son identité, ses réglages, ses ateliers et son système de production, et l'équipe qui y accède avec ses rôles et ses invitations.")
			],
			'structure\Configuration' => [
				'title' => s('Configuration'),
				'plural' => s('configurations'),
				'singular' => s('configuration'),
				'gender' => 'f',
				'description' => s('Les réglages de votre structure : régime de TVA, notes de stock, et pour une structure de production les saisons du plan de culture, les dimensions de planche par défaut et la certification bio. Une seule fiche par structure, en lecture seule.')
			],
			'structure\Invite' => [
				'title' => s('Invitations'),
				'plural' => s('invitations'),
				'singular' => s('invitation'),
				'gender' => 'f',
				'description' => s('Les invitations à rejoindre l\'équipe de votre structure : rôle attribué à l\'arrivée, adresse e-mail destinataire, date d\'expiration et nombre d\'utilisations. Créer une invitation avec une adresse e-mail l\'envoie immédiatement.')
			],
			'structure\Member' => [
				'title' => s('Membres'),
				'plural' => s('membres'),
				'singular' => s('membre'),
				'gender' => 'm',
				'description' => s('Les membres de l\'équipe de votre structure et le rôle attribué à chacun, y compris les personnes sans compte, qui figurent au planning sans se connecter. Un membre rejoint la structure en acceptant une invitation : il ne peut pas être créé par l\'API.')
			],
			'structure\Production' => [
				'title' => s('Ateliers'),
				'plural' => s('ateliers'),
				'singular' => s('atelier'),
				'gender' => 'm',
				'description' => s('Les ateliers de production de votre structure (maraîchage, arboriculture, apiculture…), avec leur surface, leur mécanisation et leur certification.')
			],
			'structure\Role' => [
				'title' => s('Rôles'),
				'plural' => s('rôles'),
				'singular' => s('rôle'),
				'gender' => 'm',
				'description' => s('Les rôles que vous attribuez aux membres de votre structure, et les droits que chacun ouvre sur la planification, la vente, les analyses et la comptabilité.')
			],
			'structure\System' => [
				'title' => s('Système de production'),
				'plural' => s('systèmes de production'),
				'singular' => s('système de production'),
				'gender' => 'm',
				'description' => s('Le système de production de votre structure : lieu, surface, sol, main d\'œuvre et répartition des circuits de vente. Une seule fiche par structure.')
			],
			'structure\Structure' => [
				'title' => s('Structure courante'),
				'plural' => s('structures'),
				'singular' => s('structure'),
				'gender' => 'f',
				'description' => s('La structure à laquelle votre clé d\'API est rattachée : identité, statut juridique et abonnement.')
			]
		];

	}

	/** Le lien d'invitation, calculé et non stocké, les réglages de culture, lus dans map\Configuration, et les notes de stock, lues dans product\Configuration. */
	public static function apiFields(): array {

		return [
			'structure\Configuration' => [
				'seasonFirst' => ['type' => 'integer', 'minimum' => 0, 'description' => s("Première saison du plan de culture")],
				'seasonLast' => ['type' => 'integer', 'minimum' => 0, 'description' => s("Dernière saison du plan de culture")],
				'defaultBedLength' => ['type' => 'integer', 'minimum' => 1, 'nullable' => TRUE, 'description' => s("Longueur de planche par défaut, en mètres")],
				'defaultBedWidth' => ['type' => 'integer', 'minimum' => 1, 'nullable' => TRUE, 'description' => s("Largeur de planche par défaut, en centimètres")],
				'defaultAlleyWidth' => ['type' => 'integer', 'minimum' => 1, 'nullable' => TRUE, 'description' => s("Largeur de passe-pied par défaut, en centimètres")],
				'stockNotes' => ['type' => 'string', 'nullable' => TRUE, 'example' => 'exemple', 'description' => s("Notes de la page des stocks, null tant qu'elles sont désactivées")],
				'stockNotesUpdatedAt' => ['type' => 'string', 'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$', 'nullable' => TRUE, 'example' => '2026-04-15 09:30:00', 'description' => s("Date et heure de la dernière modification des notes de stock, au format AAAA-MM-JJ HH:MM:SS")],
				'stockNotesUpdatedBy' => ['type' => 'integer', 'format' => 'int64', 'nullable' => TRUE, 'example' => 1, 'description' => s("Utilisateur qui a modifié les notes de stock en dernier. Identifiant d'un élément `User`.")],
			],
			'structure\Invite' => [
				'link' => ['type' => 'string', 'format' => 'uri', 'description' => s("Lien à transmettre pour utiliser l'invitation sans e-mail")],
			],
			'structure\Structure' => [
				'logo' => [
					'type' => 'string',
					'format' => 'uri',
					'nullable' => TRUE,
					'description' => s("Adresse du logo, ou null si la structure n'en a pas"),
				],
			],
			'structure\Member' => [
				'user' => [
					'description' => s("Identité du membre"),
					'allOf' => [['$ref' => '#/components/schemas/User']],
				],
			],
		];

	}

}
?>
