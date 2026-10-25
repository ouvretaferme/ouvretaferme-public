<?php
namespace media;

class StructureEmailBannerLib extends MediaLib {

	public function buildElement(): \structure\Configuration {

		$eConfiguration = \POST('id', 'structure\Configuration');

		if(
			$eConfiguration->empty() or
			\structure\Configuration::model()
				->select(\structure\Configuration::getSelection())
				->get($eConfiguration) === FALSE
		) {
			throw new \NotExistsAction('Configuration');
		}

		// L'utilisateur n'est pas le propriétaire de la ferme
		if($eConfiguration->canWrite() === FALSE) {

			// L'utilisateur n'est pas non plus admin
			if(\user\ConnectionLib::getOnline()->isAdmin() === FALSE) {
				throw new \NotAllowedAction();
			}

		}

		return $eConfiguration;

	}

}
?>
