<?php
namespace util;

class StepsUi {

	public function __construct() {
		\Asset::css('util', 'steps.css');
	}

	public function display(array $steps): string {

		$h = '<div class="util-steps">';

			foreach($steps as $i => $step) {
				$h .= '<div class="util-steps-step '.$step['class'].'">';
					$h .= '<div class="util-steps-label">'.$step['label'].'</div>';
					$h .= '<div class="util-steps-who">'.$step['who'].'</div>';
					$h .= '<div class="util-steps-circle">';
						if($step['date']) {
							$h .= DateUi::numeric($step['date'], DateUi::DAY_MONTH);
						} else {
							$h .= '?';
						}
					$h .= '</div>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

}
?>
