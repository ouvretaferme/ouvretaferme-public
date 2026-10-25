<?php
namespace media;

/**
 * Les fichiers de la feuille signée d'une date en présentiel (`learning\DateSheet`, lot L8e).
 *
 * Même forme que `LearningAttachmentAiLib`, dont un type à part : chaque nettoyage de nuit efface
 * les hashes que sa propre table ne porte plus, et sous `learning-attachment` les feuilles
 * partiraient avec les pièces jointes retirées. Le média ne se supprime jamais depuis la ligne.
 */
class LearningDateSheetAiLib extends MediaLib {

	/**
	 * `MediaLib::__construct()` déduit le type du nom de la classe, où le suffixe Ai des fichiers
	 * écrits par IA donnerait `learning-date-sheet-ai`, que rien ne déclare.
	 */
	public function __construct() {

		$this->type = 'learning-date-sheet';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/**
	 * Le fichier est écrit tel qu'il a été déposé, pour la même raison que
	 * `LearningAttachmentAiLib::send()` : la branche image de `MediaLib::send()` lit vide.
	 */
	public function send(\Element $eElement, &$hash, string $binary, int|string|null $fileType = NULL): bool {

		$this->addMedia($eElement, $hash, $fileType);

		MediaSetting::$mediaDriver->sendBinary($binary, $this->type.'/'.$hash.'.'.MediaUi::getExtension($hash));

		return TRUE;

	}

	/**
	 * Sans cette surcharge, `MediaLib::clean()` remettrait ACTIVE tout le type, `field` étant NULL :
	 * aucun fichier retiré d'une date ne quitterait le stockage.
	 */
	public function clean(): bool {

		$hashes = \learning\DateSheet::model()
			->getColumn('hash');

		foreach(array_chunk(array_unique($hashes), 100) as $hashesChunk) {
			$this->activeMedias($hashesChunk);
		}

		return TRUE;

	}

}
?>
