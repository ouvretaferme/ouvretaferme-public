<?php
namespace website;

class DesignUi {

	public function __construct() {

	}

	public static function getCSSFile(Website $eWebsite): string {

		$eDesign = $eWebsite['customDesign'];

		$fqn = ($eDesign->notEmpty() and $eDesign->offsetExists('fqn')) ? $eDesign['fqn'] : NULL;

		return 'design-'.($fqn ?? WebsiteSetting::DESIGN_DEFAULT_FQN).'.css';

	}

	public static function getStyles(Website $eWebsite, string $context = ':root'): string {

		$get = fn(string $property) => ($eWebsite->canWrite() and GET($property)) ? GET($property) : $eWebsite[$property];

		$fontLabel = $get('customFont');
		$titleFontLabel = $get('customTitleFont');

		$font = \main\CustomizeUi::getFont($fontLabel);
		$titleFont = \main\CustomizeUi::getTitleFont($titleFontLabel);

		$families = [];

		if($font !== NULL) {
			$families[] = 'family='.($font['link'] ?? $font['label']);
		}

		if($titleFont !== NULL) {
			$families[] = 'family='.($titleFont['link'] ?? $titleFont['label']);
		}

		$containerMaxWidth = $eWebsite->canWrite() ? Website::GET('customWidth', 'customWidth', $eWebsite['customWidth']) : $eWebsite['customWidth'];
		$text = $eWebsite->canWrite() ? Website::GET('customText', 'customText', $eWebsite['customText']) : $eWebsite['customText'];

		$h = '';

		if($families) {
			$h = '<link rel="preconnect" href="https://fonts.googleapis.com">';
			$h .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
			$h .= '<link href="https://fonts.googleapis.com/css2?'.implode('&', $families).'" rel="stylesheet">';
		}

		$color = $get('customColor');
		$background = $get('customBackground');

		$linkColor = ($eWebsite->canWrite() and get_exists('customLinkColor')) ? GET('customLinkColor') : $eWebsite['customLinkColor'];

		$h .= '<style>';
			$h .= $context.' {
				--background: '.$background.';
				--primary: '.$color.';
				--primaryText: '.\util\ColorUi::getTextOnColor($color).';
				--primaryInk: '.\util\ColorUi::getInkOnBackground($color, $background).';
				--primaryDark: '.\util\ColorUi::getInkOnLight($color).';
				--containerMaxWidth: '.$containerMaxWidth.'px;
				--customFont: '.($font ? $font['value'] : "'Open Sans', sans-serif").';
				--customTitleFont: '.($titleFont ? $titleFont['value'] : "'Open Sans', sans-serif").';
				--customFontAdjust: '.($font['adjust'] ?? 'none').';
				--customTitleFontAdjust: '.($titleFont['adjust'] ?? 'none').';
				--border: '.($text === Website::BLACK ? '#8883' : '#8883').';
				--textColor: '.($text === Website::BLACK ? 'var(--text)' : 'white').';
				--defaultLinkColor: '.($text === Website::BLACK ? 'black' : 'white').';
				--linkColor: '.($linkColor ?: 'var(--defaultLinkColor)').';
				--muted: '.\util\ColorUi::getMutedOnBackground($background, $text === Website::BLACK).';
				--transparent: '.($text === Website::BLACK ? '#FFF8' : '#0002').';
			}';
		$h .= '</style>';

		return $h;
	}

}
?>
