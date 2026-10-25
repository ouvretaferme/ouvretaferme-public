<?php
namespace aid;

class AidSetting extends \Settings {

	/**
	 * L'outil s'ouvre à la bêta jusqu'à ce que le semis de production (lot L4) ait rempli le
	 * catalogue (charte, Q4 A). Le drapeau garde l'entrée de la Boîte à outils et les pages de l'outil,
	 * jamais l'admin ni la veille, qui remplissent et surveillent avant l'ouverture.
	 */
	const IS_ACTIVE = FALSE;

	// En production, l'outil n'ouvre qu'avec son catalogue repris, sans quoi il s'ouvrirait vide. En dev,
	// F1 porte les tests et la 77 la recette, jamais la 7 : la première visite écrit `hasToolbox` (open()).
	const AID_STRUCTURE_BETA = (LIME_ENV === 'dev') ? [\structure\FixtureAiLib::F1, 77] : [];

	// Les aides d'une page de liste.
	const PER_LIST = 20;

	// Les aides d'une page de la liste de l'administration, une ligne chacune.
	const PER_ADMIN = 100;

	// Une aide est à revoir quand sa relecture a plus de six mois, ou quand une édition ouverte ferme
	// dans les quinze jours : son montant ou sa suite peuvent changer à la clôture (décidé par V-Sub).
	const REVIEW_STALE_MONTHS = 6;
	const REVIEW_CLOSING_DAYS = 15;

	// La veille nomme le robot et donne une adresse où le joindre, sans jamais se faire passer pour un
	// navigateur (cadrage de L5, R15). Une source qu'elle ne lit pas depuis sept jours entre dans la file
	// (arbitrage Q2 a de L5).
	const WATCH_USER_AGENT = 'Ouvretaferme-veille (+contact@ouvretaferme.org)';
	const WATCH_UNREAD_DAYS = 7;

	// La plus longue proposition de taux qu'une carte montre : 40 caractères tiennent sur une ligne
	// dans la colonne la plus étroite, 273 px à 360 de large, à 5,9 px le caractère mesuré dans Chrome.
	const RATE_SHORT_LENGTH = 40;

	// Les productions qu'une carte nomme avant « et N autres » : au-delà, la ligne grise ne dit plus
	// que la liste est restreinte, elle la recopie (treize productions sur la 362 pour dire « toutes »).
	const PRODUCTIONS_CARD = 3;

	public static function isActive(\structure\Structure $eStructure): bool {
		return (self::IS_ACTIVE or self::isBeta($eStructure));
	}

	public static function isBeta(\structure\Structure $eStructure): bool {
		return in_array($eStructure['id'], self::AID_STRUCTURE_BETA);
	}

	/**
	 * Les régions administratives, par leur code INSEE, dans l'ordre de ce code. Les cinq régions
	 * d'outre-mer sont aussi départements, chacune sous son code de région et son code de département.
	 */
	const REGIONS = [
		'01' => 'Guadeloupe',
		'02' => 'Martinique',
		'03' => 'Guyane',
		'04' => 'La Réunion',
		'06' => 'Mayotte',
		'11' => 'Île-de-France',
		'24' => 'Centre-Val de Loire',
		'27' => 'Bourgogne-Franche-Comté',
		'28' => 'Normandie',
		'32' => 'Hauts-de-France',
		'44' => 'Grand Est',
		'52' => 'Pays de la Loire',
		'53' => 'Bretagne',
		'75' => 'Nouvelle-Aquitaine',
		'76' => 'Occitanie',
		'84' => 'Auvergne-Rhône-Alpes',
		'93' => 'Provence-Alpes-Côte d\'Azur',
		'94' => 'Corse',
	];

	const REGIONS_OVERSEAS = ['01', '02', '03', '04', '06'];

	/**
	 * Les 101 départements, par leur code INSEE, avec leur nom et le code de leur région (REGIONS). Le
	 * département est le seul niveau de lieu qu'une structure connaît (charte, décision 3) : le
	 * périmètre d'une aide s'y ramène toujours (aid\Area), et la région s'en déduit, sans table. Les
	 * codes sont des chaînes, 2A et 2B comme 01 : un entier perdrait le zéro de tête.
	 */
	const DEPARTMENTS = [
		'01' => ['Ain', '84'],
		'02' => ['Aisne', '32'],
		'03' => ['Allier', '84'],
		'04' => ['Alpes-de-Haute-Provence', '93'],
		'05' => ['Hautes-Alpes', '93'],
		'06' => ['Alpes-Maritimes', '93'],
		'07' => ['Ardèche', '84'],
		'08' => ['Ardennes', '44'],
		'09' => ['Ariège', '76'],
		'10' => ['Aube', '44'],
		'11' => ['Aude', '76'],
		'12' => ['Aveyron', '76'],
		'13' => ['Bouches-du-Rhône', '93'],
		'14' => ['Calvados', '28'],
		'15' => ['Cantal', '84'],
		'16' => ['Charente', '75'],
		'17' => ['Charente-Maritime', '75'],
		'18' => ['Cher', '24'],
		'19' => ['Corrèze', '75'],
		'2A' => ['Corse-du-Sud', '94'],
		'2B' => ['Haute-Corse', '94'],
		'21' => ['Côte-d\'Or', '27'],
		'22' => ['Côtes-d\'Armor', '53'],
		'23' => ['Creuse', '75'],
		'24' => ['Dordogne', '75'],
		'25' => ['Doubs', '27'],
		'26' => ['Drôme', '84'],
		'27' => ['Eure', '28'],
		'28' => ['Eure-et-Loir', '24'],
		'29' => ['Finistère', '53'],
		'30' => ['Gard', '76'],
		'31' => ['Haute-Garonne', '76'],
		'32' => ['Gers', '76'],
		'33' => ['Gironde', '75'],
		'34' => ['Hérault', '76'],
		'35' => ['Ille-et-Vilaine', '53'],
		'36' => ['Indre', '24'],
		'37' => ['Indre-et-Loire', '24'],
		'38' => ['Isère', '84'],
		'39' => ['Jura', '27'],
		'40' => ['Landes', '75'],
		'41' => ['Loir-et-Cher', '24'],
		'42' => ['Loire', '84'],
		'43' => ['Haute-Loire', '84'],
		'44' => ['Loire-Atlantique', '52'],
		'45' => ['Loiret', '24'],
		'46' => ['Lot', '76'],
		'47' => ['Lot-et-Garonne', '75'],
		'48' => ['Lozère', '76'],
		'49' => ['Maine-et-Loire', '52'],
		'50' => ['Manche', '28'],
		'51' => ['Marne', '44'],
		'52' => ['Haute-Marne', '44'],
		'53' => ['Mayenne', '52'],
		'54' => ['Meurthe-et-Moselle', '44'],
		'55' => ['Meuse', '44'],
		'56' => ['Morbihan', '53'],
		'57' => ['Moselle', '44'],
		'58' => ['Nièvre', '27'],
		'59' => ['Nord', '32'],
		'60' => ['Oise', '32'],
		'61' => ['Orne', '28'],
		'62' => ['Pas-de-Calais', '32'],
		'63' => ['Puy-de-Dôme', '84'],
		'64' => ['Pyrénées-Atlantiques', '75'],
		'65' => ['Hautes-Pyrénées', '76'],
		'66' => ['Pyrénées-Orientales', '76'],
		'67' => ['Bas-Rhin', '44'],
		'68' => ['Haut-Rhin', '44'],
		'69' => ['Rhône', '84'],
		'70' => ['Haute-Saône', '27'],
		'71' => ['Saône-et-Loire', '27'],
		'72' => ['Sarthe', '52'],
		'73' => ['Savoie', '84'],
		'74' => ['Haute-Savoie', '84'],
		'75' => ['Paris', '11'],
		'76' => ['Seine-Maritime', '28'],
		'77' => ['Seine-et-Marne', '11'],
		'78' => ['Yvelines', '11'],
		'79' => ['Deux-Sèvres', '75'],
		'80' => ['Somme', '32'],
		'81' => ['Tarn', '76'],
		'82' => ['Tarn-et-Garonne', '76'],
		'83' => ['Var', '93'],
		'84' => ['Vaucluse', '93'],
		'85' => ['Vendée', '52'],
		'86' => ['Vienne', '75'],
		'87' => ['Haute-Vienne', '75'],
		'88' => ['Vosges', '44'],
		'89' => ['Yonne', '27'],
		'90' => ['Territoire de Belfort', '27'],
		'91' => ['Essonne', '11'],
		'92' => ['Hauts-de-Seine', '11'],
		'93' => ['Seine-Saint-Denis', '11'],
		'94' => ['Val-de-Marne', '11'],
		'95' => ['Val-d\'Oise', '11'],
		'971' => ['Guadeloupe', '01'],
		'972' => ['Martinique', '02'],
		'973' => ['Guyane', '03'],
		'974' => ['La Réunion', '04'],
		'976' => ['Mayotte', '06'],
	];

	/**
	 * Les circonscriptions de bassin, par leur numéro Sandre, avec leur nom et les départements dont
	 * au moins une commune y est rattachée : un département partagé entre deux bassins est dans les
	 * deux. Source : référentiel des communes du Sandre (NumCircAdminBassin), qui découpe les bassins
	 * de l'arrêté du 16 mai 2005 portant délimitation des bassins ou groupements de bassins, lu le
	 * 22/10/2026 sur https://api.sandre.eaufrance.fr/referentiels/v1/com.csv. L'agence Rhône
	 * Méditerranée Corse intervient sur deux circonscriptions, 06 et 12.
	 */
	const BASINS = [
		'01' => ['Artois-Picardie', ['02', '59', '60', '62', '80']],
		'02' => ['Rhin-Meuse', ['08', '52', '54', '55', '57', '67', '68', '88']],
		'03' => ['Seine-Normandie', ['02', '08', '10', '14', '21', '27', '28', '35', '45', '50', '51', '52', '53', '55', '58', '60', '61', '75', '76', '77', '78', '80', '89', '91', '92', '93', '94', '95']],
		'04' => ['Loire-Bretagne', ['03', '07', '15', '16', '17', '18', '19', '21', '22', '23', '28', '29', '35', '36', '37', '41', '42', '43', '44', '45', '48', '49', '50', '53', '56', '58', '61', '63', '69', '71', '72', '79', '85', '86', '87', '89']],
		'05' => ['Adour-Garonne', ['09', '11', '12', '15', '16', '17', '19', '23', '24', '30', '31', '32', '33', '34', '40', '46', '47', '48', '63', '64', '65', '79', '81', '82', '86', '87']],
		'06' => ['Rhône-Méditerranée', ['01', '04', '05', '06', '07', '09', '11', '12', '13', '21', '25', '26', '30', '34', '38', '39', '42', '48', '52', '66', '69', '70', '71', '73', '74', '83', '84', '88', '90']],
		'07' => ['Guadeloupe', ['971']],
		'08' => ['Martinique', ['972']],
		'09' => ['Guyane', ['973']],
		'10' => ['La Réunion', ['974']],
		'11' => ['Mayotte', ['976']],
		'12' => ['Corse', ['2A', '2B']],
	];

	/**
	 * Les codes des départements d'une région. PHP range une clé comme '31' en entier, et non '01'
	 * ni '2A' : les codes se rendent donc en chaînes, comme aid\Area les stocke.
	 */
	public static function getDepartmentsByRegion(string $region): array {

		$departments = array_filter(self::DEPARTMENTS, fn(array $department) => $department[1] === $region);

		return array_map(strval(...), array_keys($departments));

	}

	/**
	 * Les codes des départements d'une ou plusieurs circonscriptions de bassin (BASINS), chacun une
	 * fois et dans l'ordre de son code.
	 */
	public static function getDepartmentsByBasins(array $basins): array {

		$departments = array_merge(...array_map(fn(string $basin) => self::BASINS[$basin][1], $basins));
		$departments = array_values(array_unique($departments));

		sort($departments, SORT_STRING);

		return $departments;

	}

	/**
	 * Le département d'un code postal français, ou NULL quand il n'en désigne aucun. La Corse se
	 * partage à 20200 : en dessous la Corse-du-Sud, au-dessus la Haute-Corse. Outre-mer, le
	 * département tient sur les trois premiers chiffres. Le code se lit en tête de la saisie, qui
	 * traîne parfois un caractère après les cinq chiffres.
	 */
	public static function getDepartmentByPostcode(?string $postcode): ?string {

		if($postcode === NULL or preg_match('/^\s*([0-9]{5})/', $postcode, $match) === 0) {
			return NULL;
		}

		$code = $match[1];

		$department = match(substr($code, 0, 2)) {
			'20' => ((int)$code < 20200) ? '2A' : '2B',
			'97' => substr($code, 0, 3),
			default => substr($code, 0, 2)
		};

		return array_key_exists($department, self::DEPARTMENTS) ? $department : NULL;

	}

}
?>
