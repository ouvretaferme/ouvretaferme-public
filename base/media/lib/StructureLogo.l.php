<?php
namespace media;

class StructureLogoLib extends MediaLib {

	public function buildElement(): \structure\Structure {

		$eStructure = POST('id', 'structure\Structure');

		if(
			$eStructure->empty() or
			\structure\Structure::model()
				->select(['logo', 'type', 'communityType'])
				->get($eStructure) === FALSE
		) {
			throw new \NotExistsAction('Structure');
		}

		if($eStructure->acceptLogo() === FALSE) {
			throw new \NotAllowedAction();
		}

		// L'utilisateur n'est pas le propriétaire de la ferme
		if($eStructure->canManage() === FALSE) {

			// L'utilisateur n'est pas non plus admin
			if(\user\ConnectionLib::getOnline()->isAdmin() === FALSE) {
				throw new \NotAllowedAction();
			}

		}

		return $eStructure;

	}

}
?>
