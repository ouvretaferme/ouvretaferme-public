<?php
namespace media;

/**
 * L'appareil photo d'une image de quiz. Le type n'a pas de champ d'élément : le cadre se rend par
 * un hash et porte le champ caché que le formulaire de la question poste, et c'est
 * `learningQuizAi.js` qui le remplit à l'envoi ou le vide au retrait.
 */
class LearningQuizAiUi extends MediaUi {

	/**
	 * Même raison que `LearningQuizAiLib::__construct()`. Le parent n'est pas appelé : il chargerait
	 * `learning-quiz.js`, déduit du type, que rien ne fournit ; les scripts sont donc chargés ici.
	 */
	public function __construct() {

		$this->type = 'learning-quiz';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

		\Asset::js('media', 'media.js');
		\Asset::css('media', 'media.css');

		\Asset::js('media', 'learningQuizAi.js');

		\Asset::css('media', 'upload.css');
		\Asset::js('media', 'upload.js');

		\Asset::js('media', 'conf.js');

	}

	/**
	 * `MediaUi::getCamera()` rend un cadre vide sans champ d'élément : celui-ci part du hash et du
	 * nom du champ posté. `$id` distingue les cadres d'un même formulaire, `Media` retrouvant le sien
	 * par `data-id`.
	 */
	public function getHashCamera(string $name, string $id, ?string $hash, string $width, string $height): string {

		$has = ($hash !== NULL);

		$h = '<div class="media-image-upload '.($has ? 'media-image-yes' : 'media-image-upload-empty media-image-no').' dropdown" data-type="'.$this->type.'" data-id="'.encode($id).'" style="'.$this->getRectangleCss($width, $height).' font-size: '.self::getFactorSize($height, 0.35).'; border-radius: var(--radius); background-color: var(--background-even);">';

			$h .= new \util\FormUi()->hidden($name, $hash, ['id' => $id.'-hash']);

			$h .= '<div class="media-image-content" style="border-radius: inherit; background-size: cover; background-position: center;'.($has ? ' background-image: url('.$this->getUrlByHash($hash, 'm').');' : '').'"></div>';

			$h .= '<div class="media-image-action">';

				$h .= '<a class="media-image-dropdown" data-dropdown="bottom-start" title="'.($has ? s("Modifier la photo") : s("Ajouter une photo")).'">'.\Asset::icon('camera-fill').'</a>';

				$h .= '<ul class="dropdown-list bg-primary">';

					$h .= '<a data-action="learning-quiz-remove" class="dropdown-item'.($has ? '' : ' hide').'">'.s("Retirer cette photo").'</a>';
					$h .= '<div class="dropdown-divider'.($has ? '' : ' hide').'"></div>';

					$linkAttributes = ['class' => 'dropdown-item'];
					$inputAttributes = ['data-id' => $id, 'accept' => 'image/jpeg,image/png'];

					$h .= $this->getUploadLink(NULL, $linkAttributes, $inputAttributes);
					$h .= $this->getCaptureLink(NULL, $linkAttributes, $inputAttributes);

				$h .= '</ul>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

}
?>
