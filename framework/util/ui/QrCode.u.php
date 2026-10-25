<?php
namespace util;

class QrCodeUi {

	public function getPanel(string $title, string $url, string $urlPng, string $urlSvg): \Panel {

		$h = '<div class="text-center">';
			$h .= '<img src="'.$urlSvg.'" width="240" height="240" alt=""/>';
			$h .= '<p>'.s("Ce QR code mène à {value}", '<b>'.encode($url).'</b>').'</p>';
			$h .= '<p class="color-muted">'.s("Il reste valable tant que vous conservez cette adresse.").'</p>';
			$h .= '<div>';
				$h .= '<a href="'.$urlPng.'" data-ajax-download data-waiter class="btn btn-primary">'.\Asset::icon('download').' '.s("Télécharger en PNG").'</a>';
				$h .= ' <a href="'.$urlSvg.'" data-ajax-download data-waiter class="btn btn-outline-primary">'.\Asset::icon('download').' '.s("Télécharger en SVG").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return new \Panel(
			id: 'panel-qrcode',
			title: $title,
			body: $h
		);

	}

}
?>
