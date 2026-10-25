<?php
namespace notification;

/**
 * Les notifications que le produit dépose dans le centre de chaque membre concerné.
 *
 * Une notification vit dans la base de sa structure (`structure_<id>`), à côté des données dont elle
 * parle, et le compteur de non vues vit sur l'adhésion (`structure\Member.notificationsUnseen`), en
 * base commune : c'est lui que la barre lit à chaque page, sans ouvrir aucune base de structure.
 */
class NotificationSetting extends \Settings {

	/**
	 * Les familles sous lesquelles l'écran des réglages range les types, dans l'ordre où elles s'y
	 * suivent.
	 *
	 * C'est tout ce que le moteur sait des types : un package désigne sa famille, le moteur en tient
	 * la liste, l'ordre et le libellé (`NotificationUi::getFamily()`). L'ordre compte, l'écran
	 * n'émettant un intertitre que lorsque la famille change : deux types d'une même famille rendus
	 * de part et d'autre d'un troisième donneraient deux intertitres identiques.
	 *
	 * Chaque famille porte aussi l'ordre de ses packages, qui est celui de l'écran : la commande avant
	 * son règlement, le règlement avant le suivi du client, les prix du marché en dernier (recette du
	 * lot 3b, M-Notif le 21/09). Le balayage des packages suit l'ordre alphabétique et le dirait à sa
	 * place. La production vient en tête, comme dans la navigation du produit (Production, Vente,
	 * Gestion). Ce sont des packages et non des types : le moteur ne nomme toujours aucun type, et l'ordre
	 * des types d'un même package reste celui de son `getTypes()`.
	 */
	const string PRODUCTION = 'production';
	const string COMMERCIALISATION = 'commercialisation';
	const string ACCOUNTING = 'accounting';
	const string STRUCTURE = 'structure';
	const string LEARNING = 'learning';
	const string SUBSCRIPTION = 'subscription';

	const array FAMILIES = [
		self::PRODUCTION => ['toolbox'],
		self::COMMERCIALISATION => ['shop', 'payment', 'selling', 'quotation'],
		self::ACCOUNTING => ['invoicing', 'preaccounting', 'bank', 'edi', 'help'],
		self::STRUCTURE => ['structure', 'hr'],
		self::LEARNING => ['learning'],
		self::SUBSCRIPTION => ['subscription'],
	];

	/**
	 * Les deux valeurs que le moteur connaît. Un type binaire déclare `[OFF, ON]` ; un type à choix
	 * déclare `OFF` suivi de ses propres valeurs, que seul son package sait lire (`isWanted()`).
	 *
	 * OFF est la seule valeur que le moteur interprète : il écarte le membre avant même de relire
	 * l'objet. Toute autre valeur que ON est une question posée au package, une fois l'objet relu.
	 */
	const string ON = 'on';
	const string OFF = 'off';

	/**
	 * La durée de vie d'une notification, lue ou non : le cron de nettoyage efface au-delà, et le
	 * centre le dit en fin de liste.
	 */
	const int DAYS = 30;

	/** Le nombre de lignes du menu de la cloche, un groupe comptant pour une seule. */
	const int MENU = 6;

	/**
	 * Le dernier jour où les notifications sont offertes à tous, avant de passer dans une offre
	 * payante. La cloche le dit jusqu'à cette date incluse, puis se tait d'elle-même : la mention ne
	 * survit pas à la gratuité qu'elle annonce (décidé le 23/09/2026).
	 */
	const string FREE_UNTIL = '2026-10-31';

	/**
	 * La valeur de `structure.version` à partir de laquelle la base d'une structure porte la table
	 * `notification` : `do115`, retirée depuis au plancher de `data\MigrateLib`, l'y a créée, et la colonne stocke la version
	 * multipliée par dix.
	 *
	 * ⚠️ Elle date un événement passé, elle est donc figée, et c'est tout l'intérêt : la question
	 * est « cette structure a-t-elle la table », pas « cette structure est-elle à jour ».
	 * `MigrateLib::VERSION` date le présent et monte au premier `do116` venu, sur un sujet sans
	 * rapport avec les notifications : borné sur lui, tout le parc serait d'un coup tenu pour sans
	 * table, les centres se videraient, les cloches resteraient allumées et l'émission reporterait
	 * des demandes qui n'avaient aucune raison d'attendre.
	 */
	const int VERSION_TABLE = 1150;

	/**
	 * Le drapeau qui ouvre les notifications à une structure, lu partout où elles se montrent ou
	 * s'enregistrent : la cloche, les routes du centre et l'émission, chacune pour la structure
	 * qu'elle touche. Le nettoyage ne le lit pas, sinon des lignes resteraient au-delà de trente
	 * jours le jour où une structure sortirait de la liste. Le compteur de la barre ne le lit pas non
	 * plus : une structure hors liste n'émet rien, son compteur reste donc à 0.
	 *
	 * ⚠️ Une closure et non une lecture directe de `FEATURE_NOTIFICATION` : la constante se définit
	 * une fois par processus (`ouvretaferme/Lime.s.php`), et seule une closure laisse un test éprouver
	 * une structure hors liste.
	 */
	public static \Closure $enabled;

	public static function isEnabled(\structure\Structure $eStructure): bool {
		return (self::$enabled)($eStructure);
	}

	/**
	 * Le package qui émet un type, dérivé du segment qui précède le premier tiret de la valeur
	 * stockée.
	 *
	 * C'est l'unique dérivation du cadre, et elle sert des deux côtés — le `NotificationLib` du
	 * package pour ce qu'il sait du type, son `NotificationUi` pour ce qu'il en montre. Même
	 * convention que les `AlertUi` (`framework/core/Fail.php`), à ceci près que le package est lu dans
	 * la valeur stockée et non dans le nom d'un fail.
	 */
	public static function getPackage(string $type): string {
		return explode('-', $type)[0];
	}

	/**
	 * Le `NotificationLib` qui porte un type : ce que le moteur lui demande hors affichage (sa
	 * description, le montant d'une occurrence, où mène un clic).
	 *
	 * ⚠️ Une closure et non un appel direct, pour la même raison que `$enabled` : elle laisse un test
	 * éprouver l'émission, les destinataires et la déduplication sans fabriquer dans la base commune
	 * la vente dont le descripteur du produit a besoin.
	 */
	public static \Closure $resolve;

	public static function resolve(string $type): string {
		return (self::$resolve)($type);
	}

	/**
	 * La règle du drapeau, séparée de sa valeur : `TRUE` ouvre toutes les structures, une liste
	 * n'ouvre que les siennes. Un test l'éprouve sur une liste de son choix.
	 */
	public static function accepts(\structure\Structure $eStructure, bool|array $flag): bool {
		return ($flag === TRUE or (is_array($flag) and in_array($eStructure['id'], $flag, TRUE)));
	}

}

NotificationSetting::$enabled = fn(\structure\Structure $eStructure): bool => NotificationSetting::accepts($eStructure, FEATURE_NOTIFICATION);

NotificationSetting::$resolve = fn(string $type): string => '\\'.NotificationSetting::getPackage($type).'\\NotificationLib';
?>
