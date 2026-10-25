<?php
namespace media;

class WebsiteBannerLib extends MediaLib {

	public function buildElement(): \website\Website {

		$eWebsite = POST('id', 'website\Website');

		if(
			$eWebsite->empty() or
			\website\Website::model()
				->select('banner', 'structure')
				->get($eWebsite) === FALSE
		) {
			throw new \NotExistsAction('Website');
		}

		if($eWebsite->canWrite() === FALSE) {
			throw new \NotAllowedAction();
		}

		return $eWebsite;

	}

}
?>
