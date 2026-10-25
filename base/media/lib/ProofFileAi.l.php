<?php
namespace media;

/**
 * Les pages des justificatifs (`proof\File`), PDF ou photos (R-04e).
 *
 * Le type n'écrit dans aucun champ d'élément (`field` à NULL) : la ligne `proofFile` vit dans la base
 * de sa structure et ne reçoit le hash qu'après l'envoi. L'application ne publie jamais l'adresse du
 * fichier dans le stockage : les routes `proof/proof:content`, `contentFile` et `downloadFile` le
 * lisent sur le disque après avoir vérifié les droits (R-30). L'adresse publique du stockage le sert
 * pourtant à qui connaît son hash, et c'est l'imprévisibilité de celui-ci qui le protège, comme pour
 * `external-file` et `invoicing-event`. Une page effacée, supprimée ou purgée retire donc son fichier
 * du disque dès la transaction validée, par `MediaLib::deleteByHash()` puis `deleteFiles()`, au lieu
 * d'attendre dix jours le passage de `CleanLib` (R-04e, décidé par CdP-PJ le 2026-10-07).
 */
class ProofFileAiLib extends MediaLib {

	/**
	 * `MediaLib::__construct()` déduit le type du nom de la classe, où le suffixe Ai des fichiers
	 * écrits par IA donnerait `proof-file-ai`, que rien ne déclare.
	 */
	public function __construct() {

		$this->type = 'proof-file';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/**
	 * Le fichier est écrit tel que `build()` l'a laissé : la conversion d'un HEIC et la recompression
	 * d'un PNG ou d'un PDF sont déjà faites, et l'empreinte `sha256` de la page porte sur ces octets.
	 * La branche image de `MediaLib::send()` les réécrirait par Imagick, et le doublon ne se verrait
	 * plus.
	 */
	public function send(\Element $eElement, &$hash, string $binary, int|string|null $fileType = NULL): bool {

		$this->addMedia($eElement, $hash, $fileType);

		MediaSetting::$mediaDriver->sendBinary($binary, $this->type.'/'.$hash.'.'.MediaUi::getExtension($hash));

		return TRUE;

	}

	/**
	 * Retire le fichier d'un envoi que la transaction n'a pas gardé. `send()` l'écrit sur le disque
	 * avant que la ligne `proofFile` ne le cite, et une annulation retire la ligne `media` sans toucher
	 * au disque : `deleteByHash()` ne peut plus rien pour lui, puisqu'il passe par cette ligne, et
	 * `clean()` ne retire jamais un fichier. L'appelant annule donc la transaction d'abord, et le
	 * fichier ne part que si aucune ligne `media` ne le cite plus.
	 */
	public function deleteUnsaved(string $hash): void {

		if(Media::model()->whereType($this->type)->whereHash($hash)->exists()) {
			return;
		}

		MediaSetting::$mediaDriver->delete($this->type.'/'.$hash.'.'.MediaUi::getExtension($hash));

	}

	/**
	 * Les hashes sont cités depuis les bases `structure_<id>`, que le nettoyage de nuit ne parcourt
	 * pas : sans cette surcharge, `MediaLib::clean()` remettrait le type entier en ACTIVE, `field` étant
	 * NULL, y compris les fichiers que `MediaLib::deleteByHash()` vient de passer en DELETED. Seuls les médias
	 * passés en SEARCHING par cette passe sont remis en ACTIVE.
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
