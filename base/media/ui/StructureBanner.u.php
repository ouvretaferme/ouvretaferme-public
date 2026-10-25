<?php
namespace media;

class StructureBannerUi extends MediaUi {

	protected bool $crop = TRUE;

	protected function getCameraContent(\Element $eElement, int|string|null $size = NULL, int|string|null $width = NULL, int|string|null $height = NULL): string {
		return \structure\StructureUi::getBanner($eElement, $width);
	}

}
?>
