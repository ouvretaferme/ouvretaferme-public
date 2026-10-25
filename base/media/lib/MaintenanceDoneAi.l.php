<?php
namespace media;

/**
 * La photo d'un entretien fait du carnet d'entretien (`toolbox\MaintenanceDone.photo`).
 *
 * Le type n'écrit dans aucun champ d'élément (`field` à NULL) : l'envoi par `/media/:put` rend un
 * hash, que le formulaire de l'entretien poste avec le reste. Un entretien que l'on consigne n'a pas
 * d'id avant son enregistrement, d'où un type sans élément. La copie d'une structure vers une ferme
 * école ne reprend pas la photo (`SchoolAiLib::PROPERTIES_BLANK`), une ligne porte donc seule son
 * média ; il n'est pourtant jamais supprimé depuis la ligne, le nettoyage de nuit (`CleanLib`) le
 * laisse partir quand plus aucune ne le porte.
 */
class MaintenanceDoneAiLib extends MediaLib {

	/**
	 * `MediaLib::__construct()` déduit le type du nom de la classe, où le suffixe Ai des fichiers
	 * écrits par IA donnerait `maintenance-done-ai`, que rien ne déclare.
	 */
	public function __construct() {

		$this->type = 'maintenance-done';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/**
	 * Sans cette surcharge, `MediaLib::clean()` remettrait ACTIVE tout le type, `field` étant NULL :
	 * aucune photo retirée d'un entretien ne quitterait le stockage.
	 */
	public function clean(): bool {

		$hashes = \toolbox\MaintenanceDone::model()
			->wherePhoto('!=', NULL)
			->getColumn('photo');

		foreach(array_chunk(array_unique($hashes), 100) as $hashesChunk) {
			$this->activeMedias($hashesChunk);
		}

		return TRUE;

	}

}
?>
