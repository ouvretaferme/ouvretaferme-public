<?php
namespace media;

/**
 * Les images d'un quiz de leçon, sur une question (`learning\Question.image`) ou sur un choix
 * (`learning\Choice.image`).
 *
 * Le type n'écrit dans aucun champ d'élément (`field` à NULL) : l'envoi par `/media/:put` rend un
 * hash, que le formulaire de la question poste avec le reste. Question et choix se saisissent dans
 * le même panneau et n'ont pas d'id avant son enregistrement, d'où un type sans élément. Une copie
 * de formation garde le hash, et plusieurs lignes peuvent donc porter le même média : il n'est
 * jamais supprimé depuis une ligne, le nettoyage de nuit (`CleanLib`) le laisse partir quand plus
 * aucune ne le porte.
 *
 * `MediaLib::send()` n'est ni appelé ni surchargé : sa branche image confie à Imagick un temporaire
 * déjà fermé (mesuré le 21/09/2026, cf. `LearningAttachmentAiLib::send()`), et l'envoi web passe
 * par `putFile()`.
 */
class LearningQuizAiLib extends MediaLib {

	/**
	 * `MediaLib::__construct()` déduit le type du nom de la classe, où le suffixe Ai des fichiers
	 * écrits par IA donnerait `learning-quiz-ai`, que rien ne déclare.
	 */
	public function __construct() {

		$this->type = 'learning-quiz';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/**
	 * Sans cette surcharge, `MediaLib::clean()` remettrait ACTIVE tout le type, `field` étant NULL :
	 * aucune image retirée d'un quiz ne quitterait le stockage.
	 */
	public function clean(): bool {

		$hashes = array_merge(
			\learning\Question::model()
				->whereImage('!=', NULL)
				->getColumn('image'),
			\learning\Choice::model()
				->whereImage('!=', NULL)
				->getColumn('image')
		);

		foreach(array_chunk(array_unique($hashes), 100) as $hashesChunk) {
			$this->activeMedias($hashesChunk);
		}

		return TRUE;

	}

}
?>
