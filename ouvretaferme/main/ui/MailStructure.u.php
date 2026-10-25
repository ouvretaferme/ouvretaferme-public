<?php
namespace main;

class MailStructureUi extends MailBlockAiUi {

	const ACCENT = '#1d4b53';
	const TINT = '#f1f4f5';
	const TINT_RULE = '#b7c5c8';
	const LABEL = '#52757b';

	const PAD = '0 24px 18px 24px';

	/**
	 * `$footerText` remplace la version texte du pied quand celle que `toText()` en tire perdrait
	 * quelque chose : un lien y garde son libellé et perd son adresse.
	 */
	public static function formatTemplate(\structure\Structure $eStructure, string $title, string $template, array $variables, array $blocks = [], ?string $name = NULL, ?string $footer = NULL, ?string $footerText = NULL): array {

		$content = self::convertTemplate($template, $variables);

		$text = self::toText($content);
		$rows = self::getBody($template, $variables, $blocks);

		if($footer !== NULL) {
			$text .= "\n\n".($footerText ?? self::toText($footer));
			$rows .= self::getSpace(6).self::getFootnote($footer);
		}

		return self::formatRows($eStructure, $title, $text, $rows, $name);

	}

	public static function formatRows(\structure\Structure $eStructure, string $title, string $text, string $rows, ?string $name = NULL, ?string $after = NULL): array {

		$html = self::getHeader($eStructure, $name);
		$html .= self::getSpace(24);
		$html .= $rows;
		$html .= self::getSpace(6);

		if($after !== NULL) {
			$html .= self::getFootnote($after);
		}

		$html .= self::getSignature($eStructure);

		return [$title, $text, self::getHtml($html)];

	}

	public static function format(\structure\Structure $eStructure, string $title, string $content, ?string $footer = NULL, bool $encapsulate = TRUE): array {

		$html = fn($value) => nl2br($value);
		$text = fn($value) => self::toText($value);

		$contentHtml = $html($content);
		$contentText = $text($content);

		return [
			$title,
			($encapsulate ? self::encapsulateText($eStructure, $contentText) : $contentText).($footer ? "\n\n".$text($footer) : ''),
			($encapsulate ? self::encapsulateHtml($eStructure, $contentHtml, $footer ? $html($footer) : NULL) : $contentHtml.($footer ? $html($footer) : ''))
		];

	}

	// decode() ne rend que les cinq entités de htmlspecialchars() : un &nbsp; sortirait tel quel dans le texte brut.
	public static function toText(string $content): string {
		return html_entity_decode(strip_tags(str_replace(['</p>', '<br/>'], ["\n", "\n"], $content)), ENT_QUOTES | ENT_HTML5);
	}

	public static function encapsulateText(\structure\Structure $eStructure, string $content): string {

		return $content;

	}

	public static function encapsulateHtml(\structure\Structure $eStructure, string $content, ?string $after = NULL): string {

		$rows = self::getHeader($eStructure);
		$rows .= self::getSpace(24);
		$rows .= self::getText($content);

		$rows .= self::getSpace(6);

		if($after !== NULL) {
			$rows .= self::getFootnote($after);
		}

		$rows .= self::getSignature($eStructure);

		return self::getHtml($rows);

	}

	public static function getItems(array $items, ?string $label = NULL): string {
		return self::getText(self::getItemsTable($items, $label));
	}

	public static function getItemsText(array $items): string {

		$text = '';

		foreach(self::getItemsLines($items) as $line) {
			$text .= $line."\n";
		}

		return $text;

	}

	public static function getItemsLines(array $items): array {
		return array_map(fn(array $item) => $item[0].' : '.self::toText($item[1]), $items);
	}

	public static function getPanel(string $label, string $content): string {

		$table = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid '.self::TINT_RULE.'; border-radius: 8px; background-color: '.self::TINT.';">';
			$table .= '<tr><td style="padding: 16px 18px;">';
				$table .= '<div style="font-family: '.self::FONT.'; font-size: 11px; line-height: 16px; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase; color: '.self::LABEL.'; padding-bottom: 6px;">'.$label.'</div>';
				$table .= '<div style="font-family: '.self::FONT.'; font-size: 15px; line-height: 22px; color: #4c6461; word-break: break-word;">'.$content.'</div>';
			$table .= '</td></tr>';
		$table .= '</table>';

		return self::getText($table);

	}

	public static function getQuote(string $quote): string {
		return parent::getQuote($quote);
	}

	public static function getAction(string $label, string $url): string {

		$table = '<table cellpadding="0" cellspacing="0" border="0"><tr>';
			$table .= '<td align="center" bgcolor="'.self::ACCENT.'" style="background-color: '.self::ACCENT.'; border-radius: 8px;">';
				$table .= '<a href="'.encode($url).'" style="display: inline-block; padding: 14px 28px; font-family: '.self::FONT.'; font-size: 16px; line-height: 19px; font-weight: bold; color: #ffffff; text-decoration: none;">'.$label.'</a>';
			$table .= '</td>';
		$table .= '</tr></table>';

		return self::getText($table);

	}

	public static function getFooter(\structure\Structure $eStructure): string {

		if($eStructure->conf()['emailFooter'] !== NULL) {
			return new \editor\ReadorFormatterUi()->getFromXml($eStructure->conf()['emailFooter'], ['isEmail' => TRUE]);
		} else {
			return '';
		}

	}

	public static function getBanner(\structure\Structure $eStructure): string {

		$html = '';

		if($eStructure->conf()['emailBanner'] !== NULL) {

			$html .= '<div>';
				$html .= \Asset::image(self::getBannerUrl($eStructure), attributes: ['style' => 'width: 100%; max-width: 500px; height: auto; aspect-ratio: 5']);
			$html .= '</div>';
			$html .= '<br/>';

		}

		return $html;

	}

	protected static function getBannerUrl(\structure\Structure $eStructure): string {

		if(\LIME_ENV === 'dev') {
			// Le répertoire de production s'appelle encore `structure-banner` : cette adresse suit
			// les fichiers, pas le type. À changer en `structure-email-banner` le jour où le
			// répertoire du serveur de médias est renommé, pas avant.
			return 'https://media.ouvretaferme.org/structure-banner/500x100/659ff8c45b5dfde6eacp.png?6';
		}

		return new \media\StructureEmailBannerUi()->getUrlByElement($eStructure->conf(), 'm');

	}

	protected static function getHeader(\structure\Structure $eStructure, ?string $name = NULL): string {

		if($name !== NULL) {
			return self::getHeaderName($name);
		}

		if($eStructure->conf()['emailBanner'] !== NULL) {

			$h = '<tr><td style="padding: 0; border-bottom: 1px solid '.self::RULE.';">';
				$h .= '<img src="'.encode(self::getBannerUrl($eStructure)).'" width="'.self::WIDTH.'" alt="'.encode($eStructure['name']).'" style="display: block; border: 0; width: 100%; max-width: '.self::WIDTH.'px; height: auto; border-radius: 8px 8px 0 0;"/>';
			$h .= '</td></tr>';

			return $h;

		}

		if($eStructure['logo'] === NULL) {
			return self::getHeaderName($eStructure['name']);
		}

		$h = '<tr><td style="padding: 16px 24px; border-bottom: 1px solid '.self::RULE.';">';

			$h .= '<table cellpadding="0" cellspacing="0" border="0"><tr>';
				$h .= '<td width="32" valign="middle" style="width: 32px; padding-right: 10px;">';
					$h .= '<img src="'.encode(new \media\StructureLogoUi()->getUrlByHash($eStructure['logo'], 's')).'" width="32" height="32" alt="" style="display: block; border: 0; width: 32px; height: 32px; border-radius: 4px;"/>';
				$h .= '</td>';
				$h .= '<td valign="middle">'.self::getHeaderLabel($eStructure['name']).'</td>';
			$h .= '</tr></table>';

		$h .= '</td></tr>';

		return $h;

	}

	protected static function getHeaderName(string $name): string {

		$h = '<tr><td style="padding: 16px 24px; border-bottom: 1px solid '.self::RULE.';">';
			$h .= self::getHeaderLabel($name);
		$h .= '</td></tr>';

		return $h;

	}

	protected static function getHeaderLabel(string $name): string {
		return '<span style="font-family: '.self::FONT.'; font-size: 16px; line-height: 24px; font-weight: bold; color: '.self::ACCENT.';">'.encode($name).'</span>';
	}

	protected static function getFootnote(string $content): string {
		return '<tr><td style="padding: 16px 24px 20px 24px; border-top: 1px solid '.self::RULE.'; font-family: '.self::FONT.'; font-size: 13px; line-height: 20px; color: '.self::MUTED.';">'.$content.'</td></tr>';
	}

	protected static function getSignature(\structure\Structure $eStructure): string {

		$footer = self::getFooter($eStructure);

		if($footer === '') {
			return '';
		}

		$h = '<tr><td style="padding: 16px 24px 20px 24px; border-top: 1px solid '.self::RULE.'; font-family: '.self::FONT.'; font-size: 13px; line-height: 20px; color: '.self::MUTED.';">';
			$h .= $footer;
		$h .= '</td></tr>';

		return $h;

	}

	protected static function getBody(string $template, array $variables, array $blocks): string {

		$rows = '';
		$pending = [];

		$flush = function() use (&$rows, &$pending, $variables) {

			$content = trim(implode("\n", $pending));
			$pending = [];

			if($content === '') {
				return;
			}

			foreach($variables as $key => $value) {
				$content = str_ireplace('@'.$key, (string)($value ?? ''), $content);
			}

			$rows .= self::getText(nl2br($content));

		};

		foreach(explode("\n", str_replace("\r\n", "\n", encode($template))) as $line) {

			$key = self::getBlockKey($line, $blocks);

			if($key === NULL) {
				$pending[] = $line;
				continue;
			}

			$flush();
			$rows .= $blocks[$key];

		}

		$flush();

		return $rows;

	}

	protected static function getBlockKey(string $line, array $blocks): ?string {

		$line = trim($line);

		if($line === '' or $line[0] !== '@') {
			return NULL;
		}

		foreach($blocks as $key => $block) {

			if($block !== '' and strcasecmp($line, '@'.$key) === 0) {
				return $key;
			}

		}

		return NULL;

	}

	public static function getText(string $content): string {
		return '<tr><td style="padding: '.self::PAD.'; font-family: '.self::FONT.'; font-size: 15px; line-height: 23px; color: '.self::TEXT.'; word-break: break-word;">'.$content.'</td></tr>';
	}

	protected static function getSpace(int $height): string {
		return '<tr><td style="height: '.$height.'px; line-height: '.$height.'px; font-size: 0;">&nbsp;</td></tr>';
	}

	public static function convertTemplate(string $template, array $variables): string {

		$template = encode($template);

		foreach($variables as $key => $value) {
			$template = str_ireplace('@'.$key, $value ?? '', $template);
		}

		return $template;

	}

}
?>
