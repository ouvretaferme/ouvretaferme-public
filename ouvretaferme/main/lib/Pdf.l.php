<?php
namespace main;

/**
 * Le générateur de PDF du produit : Puppeteer (`ouvretaferme/main/nodejs/pdf.js`) imprime une route
 * `->remote()` dont l'adresse est signée ici.
 *
 * Il vit dans `main`, publié, pour que les packages publiés l'appellent sans passer par `selling`, qui
 * est fermé (`ClosedBoundarySentinel`). `selling\PdfLib::build()` lui délègue, ce qui laisse ses
 * appelants tels quels. La clé de signature reste `'selling'` : c'est celle que portent les routes
 * distantes de tout le parc et le secret de production, qu'un autre nom obligerait à changer.
 */
class PdfLib {

	/**
	 * `$footer` est un gabarit Puppeteer, répété en bas de chaque page hors du flux
	 * du document : c'est le seul endroit d'où une pagination peut être juste, le
	 * document HTML ignorant où il sera coupé. Les documents comptables passent le
	 * leur depuis `overview\PdfLib`, les autres n'en avaient aucun et écrivaient
	 * « Page 1 » en dur, faux dès la deuxième page.
	 */
	public static function build($url, ?string $filename = NULL, ?string $width = NULL, ?string $footer = NULL): ?string {

		if(OTF_DEMO) {
			return NULL;
		}

		return \Cache::redis()->lock('pdf-'.$url, function() use($filename, $url, $width, $footer) {

			$file = tempnam('/tmp', 'pdf-').'.pdf';

			$url = \Lime::signRemote('selling', $url);

			$args = escapeshellarg('--url='.\Lime::getUrl().$url);
			$args .= ' '.escapeshellarg('--destination='.$file);
			$args .= ' '.escapeshellarg('--filename='.$filename);

			if($width !== NULL) {
				$args .= ' '.escapeshellarg('--width='.$width);
			}

			if($footer !== NULL) {
				$args .= ' '.escapeshellarg('--footer='.rawurlencode($footer));
			}

			exec('node '.LIME_DIRECTORY.'/ouvretaferme/main/nodejs/pdf.js '.$args.' 2>&1', $output, $code);

			// Sans fichier, file_get_contents() rend FALSE, que le type de retour convertit en chaîne vide :
			// le document passerait pour généré.
			if($code !== 0) {
				throw new \Exception('PDF not generated, pdf.js exited with '.$code.': '.implode("\n", array_slice($output, 0, 5)));
			}

			$content = file_get_contents($file);

			unlink($file);

			return $content;

		}, fn() => throw new \FailAction('main\Pdf::fileLocked'), 5);

	}

}
?>
