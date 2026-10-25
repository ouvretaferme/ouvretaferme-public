<?php
namespace media;

/**
 * Les fichiers déposés pour importer une vente (`external\File`), photos ou PDF.
 *
 * Le type n'écrit dans aucun champ d'élément (`field` à NULL) : la ligne `externalFile` vit dans la
 * base de sa structure et ne reçoit le hash qu'après l'envoi. L'application ne publie jamais l'adresse
 * du fichier dans le stockage : la route `external/file:content` le lit sur le disque après avoir
 * vérifié les droits. L'adresse publique du stockage le sert pourtant à qui connaît son hash, et
 * c'est l'imprévisibilité de celui-ci qui le protège, comme pour `invoicing-event`.
 */
class ExternalFileAiLib extends MediaLib {

	/**
	 * `MediaLib::__construct()` déduit le type du nom de la classe, où le suffixe Ai des fichiers
	 * écrits par IA donnerait `external-file-ai`, que rien ne déclare.
	 */
	public function __construct() {

		$this->type = 'external-file';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/**
	 * Le fichier est écrit tel qu'il a été déposé, photo comprise : la lecture et l'aperçu relisent
	 * exactement ce que l'utilisateur a envoyé. La branche image de `MediaLib::send()` confie à Imagick
	 * un fichier temporaire déjà fermé, qu'il lit vide (mesuré pour `learning-attachment`).
	 */
	public function send(\Element $eElement, &$hash, string $binary, int|string|null $fileType = NULL): bool {

		$this->addMedia($eElement, $hash, $fileType);

		MediaSetting::$mediaDriver->sendBinary($binary, $this->type.'/'.$hash.'.'.MediaUi::getExtension($hash));

		return TRUE;

	}

	/**
	 * Les hashes des médias actifs envoyés avant `$date`, candidats au ménage des documents déposés
	 * (`external\FileLib::cleanMedia()`). `updatedAt` est la date où le fichier est arrivé sur le
	 * disque : son envoi, ou la migration do178 pour un fichier déposé avant elle. Ni `clean()` ni
	 * `CleanLib` ne le touchent ensuite sur un média qui reste actif.
	 */
	public function getHashesActiveBefore(string $date): array {

		return Media::model()
			->select('hash')
			->whereType($this->type)
			->whereStatus(Media::ACTIVE)
			->whereUpdatedAt('<', $date)
			->getColumn('hash');

	}

	/**
	 * Les hashes sont cités depuis les bases `structure_<id>`, que le nettoyage de nuit ne parcourt
	 * pas : sans cette surcharge, `MediaLib::clean()` remettrait le type entier en ACTIVE, `field` étant
	 * NULL, et le résultat serait le même. Seuls les médias passés en SEARCHING par cette passe sont
	 * remis en ACTIVE, pour qu'un média qu'un autre geste aurait passé en DELETED le reste.
	 */
	public function clean(): bool {

		Media::model()
			->whereType($this->type)
			->whereStatus(Media::SEARCHING)
			->update([
				'status' => Media::ACTIVE
			]);

		return TRUE;

	}

}
?>
