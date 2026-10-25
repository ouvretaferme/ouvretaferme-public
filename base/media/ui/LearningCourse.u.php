<?php
namespace media;

class LearningCourseUi extends MediaUi {

	protected bool $crop = TRUE;

	protected function getCameraContent(\Element $eElement, int|string|null $size = NULL, int|string|null $width = NULL, int|string|null $height = NULL): string {
		return \learning\CourseUi::getCover($eElement, 'learning-cover-lg');
	}

}
?>
