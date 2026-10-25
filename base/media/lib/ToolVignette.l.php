<?php
namespace media;

class ToolVignetteLib extends MediaLib {

	public function buildElement(): \work\Tool {

		$eTool = POST('id', 'work\Tool');

		if(
			$eTool->empty() or
			\work\Tool::model()
				->select(['vignette', 'structure', 'routineName'])
				->get($eTool) === FALSE
		) {
			throw new \NotExistsAction('Tool');
		}

		if($eTool->canWrite() === FALSE) {
			throw new \NotAllowedAction();
		}

		return $eTool;

	}

}
?>
