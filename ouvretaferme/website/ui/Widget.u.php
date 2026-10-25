<?php
namespace website;

class WidgetUi {

	public function replace(Webpage $eWebpage, string $content): string {

		$eWebpage->expects(['widgets']);

		foreach($eWebpage['widgets'] as $original => $new) {
			if($new !== NULL) {
				$content = str_ireplace($original, $new(), $content);
			}
		}

		return $content;
	}

}
?>
