<?php
namespace util;

require_once __DIR__.'/../vendor/autoload.php';

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRImagick;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeLib {

	public static function getPng(string $url): string {

		return self::render($url, [
			'outputInterface' => QRImagick::class,
			'imagickFormat' => 'png32',
			'scale' => 20,
		]);

	}

	public static function getSvg(string $url): string {

		return self::render($url, [
			'outputInterface' => QRMarkupSVG::class,
			'svgAddXmlHeader' => TRUE,
			'connectPaths' => TRUE,
			'drawLightModules' => TRUE,
		]);

	}

	private static function render(string $url, array $options): string {

		return new QRCode(new QROptions($options + [
			'outputBase64' => FALSE,
			'eccLevel' => EccLevel::M,
			'addQuietzone' => TRUE,
			'quietzoneSize' => 4,
		]))->render($url);

	}

}
?>
