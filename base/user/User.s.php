<?php
namespace user;

class UserSetting extends \Settings {

	const PASSWORD_SIZE_MIN = 8;
	const NAME_SIZE_MAX = 50;

	const EMOJI = [
		'admin' => '👑',
		'producer' => '👩‍🌾',
		'teacher' => '👩‍🏫',
		'consultant' => '👨‍💼'
	];

	public static array $admin = [];

	public static bool $checkTos = FALSE;

	// Le code de connexion des administrateurs, coupé en dev où le mail ne part qu'aux adresses de MailSetting::$devSendOnly
	public static bool $challenge = (LIME_ENV !== 'dev');

	// Versions des CGU et leur date d'entrée en vigueur : celle de la mise en ligne de la fenêtre qui les
	// annonce, augmentée du préavis que les CGU prévoient. La version 1 est le PDF « Version du 12/06/2026 ».
	public static array $tos = [
		1 => '2026-06-12',
		2 => '2026-11-01',
	];

	// Utilisateur attribué aux actions effectuées via l'API (clé personnelle/OAuth2)
	public static ?int $apiUserId = NULL;

	// Number of days for saving logs
	const KEEP_LOGS = 365;

	// Number of minutes after login to close its account
	const CLOSE_TIME_LIMIT = 3;

	// Number of days to cancel account closing
	const CLOSE_TIMEOUT = 90;

	// Authorized authentication
	const AUTH = ['basic'];

	// Maximum allowed people on the same IP to allow banishment by IP
	const MAX_BAN_ON_SAME_IP = 1000;

	// Maximum ban displayed per page on ban admin page
	const MAX_BY_PAGE = 50;
	const LOG_SPLIT = 1;

	const FR = 75;
	const BE = 20;

	const COUNTRY_FRANCE = [75, 80, 87, 151, 181, 189, 217, 247];
	const COUNTRY_BELGIUM = [20];

	// La clarté d'une couleur libre de l'avatar, en pour cent : les bornes que le modèle du personnage tient lui-même
	const AVATAR_LIGHTNESS_MIN = 4;
	const AVATAR_LIGHTNESS_MAX = 95;

	/**
	 * Le modèle 3D téléversé à la place de l'avatar composé, ouvert en dev et, en production, à qui
	 * appartient à une structure de `FEATURE_UPLOAD_MODEL`.
	 *
	 * ⚠️ Une closure et non une lecture directe de la constante : la constante se définit une fois par
	 * processus (`ouvretaferme/Lime.s.php`), et seule une closure laisse un test éprouver le drapeau
	 * fermé. Même forme que `EntrySetting::$enabled`.
	 */
	public static \Closure $avatarUpload;

	// L'adresse où l'auteur d'un modèle 3D retiré conteste le retrait (DSA, art. 17), choisie par l'équipe : l'e-mail du
	// retrait l'invite à répondre et y adresse sa réponse quand elle est posée, et ne propose aucune voie de réponse sinon
	public static ?string $avatarUploadContest = NULL;

	public static function isAvatarUploadEnabled(\structure\Structure $eStructure): bool {
		return (self::$avatarUpload)($eStructure);
	}

}

UserSetting::$avatarUpload = fn(\structure\Structure $eStructure): bool => (FEATURE_UPLOAD_MODEL === TRUE or (is_array(FEATURE_UPLOAD_MODEL) and in_array($eStructure['id'], FEATURE_UPLOAD_MODEL, TRUE)));
?>
