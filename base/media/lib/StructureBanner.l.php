<?php
namespace media;

class StructureBannerLib extends MediaLib {

	public function buildElement(): \structure\Structure {

		$eStructure = \POST('id', 'structure\Structure');

		if(
			$eStructure->empty() or
			\structure\Structure::model()
				->select(\structure\Structure::getSelection())
				->get($eStructure) === FALSE
		) {
			throw new \NotExistsAction('Structure');
		}

		if($eStructure->acceptBanner() === FALSE) {
			throw new \NotAllowedAction();
		}

		if($eStructure->canManage() === FALSE) {

			if(\user\ConnectionLib::getOnline()->isAdmin() === FALSE) {
				throw new \NotAllowedAction();
			}

		}

		return $eStructure;

	}

}
?>
