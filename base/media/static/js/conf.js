class ImageConf {
	static sizeMax = 20;
	static imageCropRequiredFactor = 1.1;
	static imagesRequiredSize = {
		'user-vignette': {
			width: 512,
			height: 512,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 512x512 pixels)"
		},
		'editor': {
			width: 50,
			height: 50,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 50x50 pixels)"
		},
		'plant-vignette': {
			width: 512,
			height: 512,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 512x512 pixels)"
		},
		'gallery': {
			width: 200,
			height: 200,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 200x200 pixels)"
		},
		'structure-logo': {
			width: 512,
			height: 512,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 512x512 pixels)"
		},
		'structure-email-banner': {
			width: 500,
			height: 100,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 500x100 pixels)"
		},
		'structure-banner': {
			width: 1600,
			height: 400,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 1600x400 pixels)"
		},
		'product-vignette': {
			width: 512,
			height: 512,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 512x512 pixels)"
		},
		'tool-vignette': {
			width: 256,
			height: 256,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 256x256 pixels)"
		},
		'website-logo': {
			width: 512,
			height: 512,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 512x512 pixels)"
		},
		'website-favicon': {
			width: 256,
			height: 256,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 256x256 pixels)"
		},
		'website-banner': {
			width: 1800,
			height: 600,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 1800x600 pixels)"
		},
		'webpage-banner': {
			width: 1800,
			height: 600,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 1800x600 pixels)"
		},
		'shop-logo': {
			width: 512,
			height: 512,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 512x512 pixels)"
		},
		'shop-banner': {
			width: 1800,
			height: 600,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 1800x600 pixels)"
		},
		'learning-course': {
			width: 1600,
			height: 900,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 1600x900 pixels)"
		},
		'learning-quiz': {
			width: 200,
			height: 200,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 200x200 pixels)"
		},
		'maintenance-done': {
			width: 200,
			height: 200,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 200x200 pixels)"
		},
		'amap-producer-photo': {
			width: 1600,
			height: 800,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 1600x800 pixels)"
		},
		'block-item': {
			width: 200,
			height: 200,
			error: "La résolution de votre image n'est pas suffisante (le minimum est 200x200 pixels)"
		}
	}
	static imageSizes = {
		'user-vignette': ["64x64","256x256","512x512"],
		'editor': [100,500,750,1000],
		'plant-vignette': ["64x64","256x256","512x512"],
		'gallery': [500],
		'structure-logo': [64,256,512],
		'structure-email-banner': ["500x100"],
		'structure-banner': ["1600x400"],
		'product-vignette': ["64x64","256x256","512x512"],
		'tool-vignette': [64,256,1024],
		'website-logo': [64,256,512],
		'website-favicon': ["64x64","128x128","256x256"],
		'website-banner': ["240x80","1800x600"],
		'webpage-banner': ["240x80","1800x600"],
		'shop-logo': [64,256,512],
		'shop-banner': ["240x80","1800x600"],
		'learning-course': ["480x270","1600x900"],
		'learning-quiz': [750],
		'maintenance-done': [400,1600],
		'amap-producer-photo': ["600x300","1600x800"],
		'block-item': [750]
	}
};

class ImageMessage {
	static treatment = "Traitement...";
	static sendOne = "Envoi de l'image...";
	static resizeAndSend = "Redimensionnement et envoi des images...";
	static internalPreupload = "Une erreur est survenue pendant le téléchargement de votre image, veuillez vérifier votre connexion internet ou réessayez ultérieurement !";
	static internalUpload = "Une erreur est survenue pendant l'enregistrement de votre image, veuillez vérifier votre connexion internet ou réessayez ultérieurement !";
	static internalCrop = "Une erreur est survenue pendant le redimensionnement de votre image, veuillez vérifier votre connexion internet ou réessayez ultérieurement !";
	static imageInvalid = "L'image fournie est invalide ou le téléchargement a échoué.";
	static imageProgressUpload = "Téléchargement de la photo <span id=\"upload-progress-current\"></span> de <span id=\"upload-progress-count\"></span>... <span id=\"upload-progress-kill\">(<a data-action=\"upload-kill\">arrêter</a>)</span>";
	static imageUploadOne = "Vous ne pouvez envoyer qu'une seule image à la fois.";
	static imageUploadLimit = "Veuillez sélectionner un nombre limité d'images (maximum <number>)";
	static imageTypeSeveral = "Certaines des images sélectionnées n'étaient pas dans un format reconnu et n'ont pas été téléchargées.";
	static imageTypeOne = "Veuillez sélectionner une image valide.";
	static imageTypeAll = "Veuillez sélectionner des images valides.";
	static imageSize = "L'image sélectionnée est trop grosse, elle doit peser moins de 20Mo.";
	static imageNoZoom = "La qualité de l'image est insuffisante pour proposer le zoom";
	static imageNoResize = "La qualité de l'image est insuffisante pour permettre un recadrage"
};
