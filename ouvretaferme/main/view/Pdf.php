<?php
/**
 * Template pour PDF
 */
class PdfTemplate extends Template {

	protected $lang = 'fr';

	/**
	 * Page title
	 */
	public ?string $title = NULL;

	public ?string $landscape = NULL;

	public function __construct() {

		parent::__construct();

		\Asset::css('util', 'font-open-sans.css');
		\Asset::css('util', 'font-roboto-mono.css');
		// Sans elle, tout `font-family: 'Bricolage Grotesque'` d'un document tombe sur
		// le sans-serif du système, sans erreur et sans que rien ne le signale : la
		// feuille n'était chargée que par Root.php, le gabarit du site.
		\Asset::css('main', 'font-bricolage-grotesque.css');
		\Asset::css('main', 'root.css');
		\Asset::css('util', 'lime.css');
		\Asset::css('main', 'reboot.css');
		\Asset::css('main', 'component.css');
		\Asset::css('main', 'pdf.css');

	}

	public function build(Closure $callback): mixed {

		ob_start();
		$callback->call($this);
		$stream = ob_get_clean();

		return $this->buildHtml($stream);

	}

	protected function buildHtml(string $stream): string {

		$h = '<!DOCTYPE html>';
		$h .= '<html lang="'.$this->lang.'">';

		$h .= '<head>';
			$h .= '<title>'.encode($this->title).'</title>';
			$h .= '<base href="'.\Lime::getProtocol().'://'.SERVER('HTTP_HOST').'">';
			$h .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
			$h .= '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
			$h .= Asset::importHtml();
		$h .= '</head>';

			$h .= '<body class="template-pdf '.(($this->landscape ?? NULL) === NULL ? '' : 'landscape').'">';
				$h .= $stream;
			$h .= '</body>';

		$h .= '</html>';

		return $h;

	}

}

?>
