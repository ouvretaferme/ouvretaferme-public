<?php
namespace media;

class LearningCourseLib extends MediaLib {

	public function buildElement(): \learning\Course {

		$eCourse = POST('id', 'learning\Course');

		if(
			$eCourse->empty() or
			\learning\Course::model()
				->select(\learning\Course::getSelection())
				->get($eCourse) === FALSE
		) {
			throw new \NotExistsAction('Course');
		}

		if($eCourse->assertWrite() === FALSE) {
			throw new \NotAllowedAction();
		}

		return $eCourse;

	}

}
?>
