<?php
namespace media;

function getMediaVignette() {

	return [
		'imageFormat' => [
			's' => [64, 64],
			'm' => [256, 256],
			'l' => [512, 512]
		],
		'imageResizeReference' => ['l'],
		// Une photo conservée en PNG pèse 5 à 10 fois son équivalent JPEG (mesuré :
		// 500 Ko contre 75 Ko en 512x512). Le cercle du recadrage est fait en CSS,
		// aucune transparence n'est nécessaire ici.
		'imageOutputType' => [IMAGETYPE_JPEG],
		'imageMaxLength' => 1024,
		'imageRequiredSize' => 'l',
		'imageCropShape' => 'circle'
	];

}

function getMediaLogo() {

	return [
		'imageFormat' => [
			's' => 64,
			'm' => 256,
			'l' => 512
		],
		'imageResizeReference' => ['l'],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
		'imageMaxLength' => 1024,
		'imageRequiredSize' => 'l'
	];

}

function getMediaBanner() {

	return [
		'imageFormat' => [
			's' => [240, 80],
			'l' => [1800, 600]
		],
		'imageResizeReference' => ['l'],
		'imageOutputType' => [IMAGETYPE_JPEG],
		'imageMaxLength' => 1800,
		'imageRequiredSize' => 'l'
	];

}

class MediaSetting extends \Settings {

	// Max size of an image in Mo (change also rewrite.cfg if needed : client_max_body_size 20m;)
	const MAX_IMAGE_SIZE = 20;

	// Plafond du dépôt par l'API, plus bas que celui du web : le corps arrive en base64, qui pèse un
	// tiers de plus que le fichier, et il est chargé trois fois en mémoire (php://input, la chaîne
	// décodée du JSON, puis le binaire). 10 Mo de fichier font 13,4 Mo de corps, sous le plafond
	// nginx de 20 Mo.
	const MAX_API_IMAGE_SIZE = 10;

	const MAX_ATTACHMENT_SIZE = 10;

	public static $images = ['user-vignette', 'editor', 'plant-vignette', 'gallery', 'structure-logo', 'structure-email-banner', 'structure-banner', 'product-vignette', 'tool-vignette', 'website-logo', 'website-favicon', 'website-banner', 'webpage-banner', 'shop-logo', 'shop-banner', 'pdf-content', 'association-document', 'invoicing-event', 'learning-course', 'learning-attachment', 'learning-quiz', 'learning-date-sheet', 'maintenance-done', 'amap-producer-photo', 'external-file', 'proof-file', 'bank-statement', 'invoicing-pdf', 'financial-year-pdf', 'amap-charter', 'block-item', 'user-avatar-upload', 'map-decor-upload'];

	public static $types;

	const IMAGE_CROP_REQUIRED_FACTOR = 1.1;

	const IMAGE_RESIZE_REQUIRED_FACTOR = 1.15;

	const DEFAULT_QUALITY = [
		IMAGETYPE_JPEG => 85,
		IMAGETYPE_PNG => 9,
		IMAGETYPE_GIF => NULL
	];

	// Le WEBP est accepté en entrée mais n'est jamais stocké : aucun type ne le liste
	// dans son imageOutputType, il est donc converti en JPEG ou PNG à l'enregistrement.
	const IMAGES_INPUT_TYPE = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_BMP, IMAGETYPE_WEBP];

	const IMAGES_EXTENSIONS = [
		'p' => 'png',
		'j' => 'jpg',
		'g' => 'gif',
		'f' => 'pdf',
		'b' => 'bin',
	];

	const BASE_PATH = '/var/www/storage';

	public static $mediaDriver;

	/**
	 * Les types d'images déclarés, dans l'ordre de l'enum de `media.type`. `$images` porte les valeurs
	 * de la colonne, qu'un type absent de cette installation ne quitte pas sans migration.
	 */
	public static function getImages(): array {
		return array_values(array_intersect(self::$images, array_keys(self::$types)));
	}

	/**
	 * La classe d'un type, sans son suffixe Lib ou Ui. Un type déclaré par un autre package la nomme avec
	 * son espace de noms (`shop\ShopLogo`), un type de base/media par son seul nom.
	 */
	public static function getClass(string $type): ?string {

		$class = self::$types[$type]['class'] ?? NULL;

		if($class === NULL or str_contains($class, '\\')) {
			return $class;
		} else {
			return 'media\\'.$class;
		}

	}

}

MediaSetting::$mediaDriver = new \storage\DriverLib();
function mediaUrl () {

	$driver = MediaSetting::$mediaDriver;

	if($driver instanceof \storage\DriverLib) {

		if(LIME_ENV === 'dev') {
			return 'http://media.dev-ouvretaferme.org';
		} else {
			return 'https://media.ouvretaferme.org';
		}

	}

};

MediaSetting::$types = [

	'user-vignette' => [
		'class' => 'UserVignette',
		'element' => 'user\User',
		'field' => 'vignette',
		// Un avatar enregistré en JPEG perd son fond transparent, qui devient noir. Le format se choisit sur cette
		// déclaration, que MediaLib lit à sa construction : hors du développement, celle de framework/storage, qui admet
		// aussi le PNG, ne se charge qu'après, au premier appel de ServerLib
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
	] + getMediaVignette(),

	'invoicing-event' => [
		'class' => 'InvoicingEvent',
		'element' => 'invoicing\Event',
		'field' => 'attachmentHash'
	],

	'invoicing-pdf' => [
		'class' => 'InvoicingPdfAi',
		'element' => 'invoicing\PdfContent',
		'field' => 'hash'
	],

	'financial-year-pdf' => [
		'class' => 'FinancialYearPdfAi',
		'element' => 'financialYear\PdfContent',
		'field' => 'hash'
	],

	'editor' => [
		'class' => 'Editor',
		'element' => NULL,
		'field' => NULL,
		'imageFormat' => [
			'xs' => 100,
			's' => 500,
			'm' => 750,
			'l' => 1000,
		],
		'imageCropReference' => (int)(2000 / 1.1),
		'imageResizeReference' => ['l', 's'],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF],
		'imageMaxLength' => 5000,
		'imageMinPixels' => 10000000,
		'imageRequiredSize' => 50,
	],

	'plant-vignette' => [
		'class' => 'PlantVignette',
		'element' => 'plant\Plant',
		'field' => 'vignette',
	] + getMediaVignette(),

	'product-vignette' => [
		'class' => 'ProductVignette',
		'element' => 'product\Product',
		'field' => 'vignette',
	] + getMediaVignette(),

	'learning-course' => [
		'class' => 'LearningCourse',
		'element' => 'learning\Course',
		'field' => 'vignette',
		'imageFormat' => [
			's' => [480, 270],
			'l' => [1600, 900]
		],
		'imageResizeReference' => ['l'],
		'imageOutputType' => [IMAGETYPE_JPEG],
		'imageMaxLength' => 1600,
		'imageRequiredSize' => 'l'
	],

	'learning-attachment' => [
		'class' => 'LearningAttachmentAi',
		'element' => NULL,
		'field' => NULL
	],

	'learning-date-sheet' => [
		'class' => 'LearningDateSheetAi',
		'element' => NULL,
		'field' => NULL
	],

	'learning-quiz' => [
		'class' => 'LearningQuizAi',
		'element' => NULL,
		'field' => NULL,
		'imageFormat' => [
			'm' => 750
		],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
		'imageMaxLength' => 1600,
		'imageRequiredSize' => 200,
	],

	// L'image d'un enfant d'un bloc parent (une étape, un volet, un onglet, un chiffre). Elle se lit
	// dans la colonne de texte de l'objet qui porte les blocs, d'où la largeur de l'image d'un quiz,
	// qui se lit dans la même.
	'block-item' => [
		'class' => 'BlockItem',
		'element' => 'block\Block',
		'field' => 'media',
		'imageFormat' => [
			'm' => 750
		],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
		'imageMaxLength' => 1600,
		'imageRequiredSize' => 200,
	],

	// Le modèle 3D qu'un utilisateur a téléversé pour son avatar, tel que le serveur l'a reconstruit
	// (`base/scene/upload/upload.mjs`) : le fichier envoyé n'est jamais gardé
	'user-avatar-upload' => [
		'class' => 'UserAvatarUpload',
		'element' => 'user\UserAvatarUpload',
		'field' => 'hash'
	],

	// Le modèle 3D qu'une structure a téléversé à la place d'un élément de son décor, reconstruit de même : le fichier envoyé
	// n'est jamais gardé
	'map-decor-upload' => [
		'class' => 'MapDecorUpload',
		'element' => 'map\DecorUpload',
		'field' => 'hash'
	],

	'external-file' => [
		'class' => 'ExternalFileAi',
		'element' => NULL,
		'field' => NULL
	],

	'proof-file' => [
		'class' => 'ProofFileAi',
		'element' => NULL,
		'field' => NULL
	],

	'bank-statement' => [
		'class' => 'BankStatementAi',
		'element' => NULL,
		'field' => NULL
	],

	'maintenance-done' => [
		'class' => 'MaintenanceDoneAi',
		'element' => NULL,
		'field' => NULL,
		'imageFormat' => [
			'm' => 400,
			'l' => 1600
		],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
		'imageMaxLength' => 2000,
		'imageRequiredSize' => 200,
	],

	'tool-vignette' => [
		'class' => 'ToolVignette',
		'element' => 'work\Tool',
		'field' => 'vignette',
		'imageFormat' => [
			's' => 64,
			'm' => 256,
			'l' => 1024
		],
		'imageResizeReference' => ['l'],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
		'imageMaxLength' => 2000,
		'imageRequiredSize' => 'm',
	],

	'gallery' => [
		'class' => 'Gallery',
		'element' => NULL,
		'field' => NULL,
		'imageFormat' => [
			'm' => 500
		],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
		'imageMaxLength' => 1000,
		'imageRequiredSize' => 200,
	],

	'structure-email-banner' => [
		'class' => 'StructureEmailBanner',
		'element' => 'structure\Configuration',
		'field' => 'emailBanner',
		'imageFormat' => [
			'm' => [500, 100]
		],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
		'imageMaxLength' => 500,
		'imageRequiredSize' => 'm',
	],

	'structure-banner' => [
		'class' => 'StructureBanner',
		'element' => 'structure\\Structure',
		'field' => 'banner',
		// 1600 px et non les 500 de structure-email-banner : cette image occupe toute la largeur de la
		// fenêtre, la réemployer l'étirerait quatre fois. La hauteur de sortie n'a pas à valoir
		// celle de la bande, `cover` recadre — c'est la largeur qui doit suivre l'écran.
		'imageFormat' => [
			'l' => [1600, 400]
		],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
		'imageMaxLength' => 1600,
		'imageRequiredSize' => 'l',
	],

	'structure-logo' => [
		'class' => 'StructureLogo',
		'element' => 'structure\Structure',
		'field' => 'logo'
	] + getMediaLogo(),

	'website-favicon' => [
		'class' => 'WebsiteFavicon',
		'element' => 'website\Website',
		'field' => 'favicon',
		'imageFormat' => [
			's' => [64, 64],
			'm' => [128, 128],
			'l' => [256, 256]
		],
		'imageResizeReference' => ['l'],
		'imageOutputType' => [IMAGETYPE_JPEG, IMAGETYPE_PNG],
		'imageMaxLength' => 1024,
		'imageRequiredSize' => 'l'
	],

	'website-logo' => [
		'class' => 'WebsiteLogo',
		'element' => 'website\Website',
		'field' => 'logo'
	] + getMediaLogo(),

	'website-banner' => [
		'class' => 'WebsiteBanner',
		'element' => 'website\Website',
		'field' => 'banner'
	] + getMediaBanner(),

	'webpage-banner' => [
		'class' => 'WebpageBanner',
		'element' => 'website\Webpage',
		'field' => 'banner'
	] + getMediaBanner(),


];

// La boutique, l'AMAP, la vente et l'association déclarent leurs types dans leur package, que l'export public retire.
// Les observateurs sont posés au démarrage (package.c.php), avant tout chargement d'un réglage.
if(
	\structure\StructureSetting::hasCommercialisationApp() or
	\structure\StructureSetting::hasAssociationPackage()
) {
	MediaSetting::$types += MediaLib::getObserverTypes();
}

?>
