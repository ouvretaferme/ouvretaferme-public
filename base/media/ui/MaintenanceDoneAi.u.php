<?php
namespace media;

/**
 * L'appareil photo d'un entretien fait du carnet d'entretien. Le type n'a pas de champ d'élément :
 * le cadre se rend par `MediaUi::getHashCamera()`, et le formulaire de l'entretien poste le hash.
 */
class MaintenanceDoneAiUi extends MediaUi {

	// Une photo d'entretien se garde entière : pas de panneau de recadrage à l'envoi.
	protected bool $crop = FALSE;

	/**
	 * Le parent n'est pas appelé : il déduirait du nom de la classe le type `maintenance-done-ai`,
	 * que rien ne déclare, et chargerait un script à ce nom. Le type et les scripts se posent donc ici.
	 */
	public function __construct() {

		$this->type = 'maintenance-done';
		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

		\Asset::js('media', 'media.js');
		\Asset::css('media', 'media.css');

		\Asset::js('media', 'mediaHashAi.js');
		\Asset::js('media', 'maintenanceDoneAi.js');

		\Asset::css('media', 'upload.css');
		\Asset::js('media', 'upload.js');

		\Asset::js('media', 'conf.js');

	}

}
?>
