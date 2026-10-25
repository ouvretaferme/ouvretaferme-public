<?php
namespace editor;

/**
 * Les URL que l'éditeur laisse écrire à un rédacteur, et que le lecteur reçoit ensuite.
 *
 * Le filtre du lien de texte existait déjà (`isSafeUrl()`), mais il ne couvrait que le `href` d'un
 * `<a>` : l'adresse d'une image, le lien posé dessus et l'adresse d'une vidéo traversaient le
 * nettoyage sans être regardées, et le formateur les repose en `src` et en `href`. Le `src` d'une
 * `<iframe>` est le plus grave des trois : un schéma exécutable y part sans aucun geste du lecteur,
 * dans l'origine de la page qui la porte, et ces contenus s'affichent sur la boutique publique.
 *
 * Deux règles, parce que ces adresses ne sont pas de même nature : une adresse que le navigateur
 * charge seul (`<img>`, `<iframe>`) ne peut être que http(s) ; une adresse que le lecteur suit d'un
 * clic garde la liste noire du lien de texte, qui laisse passer `mailto:`, `tel:`, une ancre et une
 * adresse relative. Mesure du parc au 22/09/2026, sur les 40 propriétés de type `editor` : 3 073
 * images et 21 vidéos, toutes en http(s), aucun schéma exécutable.
 *
 * Le schéma ne suffit pas pour une vidéo : une `<iframe>` en https charge une page entière, que
 * le site public de la structure affiche sous son adresse. L'adresse d'une vidéo est donc
 * recomposée depuis la liste des hébergeurs (`EditorSetting::VIDEO`), à l'enregistrement et de
 * nouveau au rendu, pour qu'un contenu déjà en base ne produise pas d'iframe hors de la liste.
 *
 * Chaque refus se double de la forme légitime correspondante, sinon il ne prouverait rien : un
 * nettoyage qui jetterait tout passerait tous les cas de refus.
 *
 * Lancement : dev/test package=editor file=XmlUrl
 */
class XmlUrlTest extends \Test {

	const URL = 'https://media.exemple.org/photo.jpg';

	/** Le XML que la base recevrait pour le HTML que l'éditeur poste. */
	private function toXml(string $html): string {
		return (string)new \editor\XmlLib()->fromHtml($html, ['acceptFigure' => TRUE]);
	}

	/** Le HTML que le lecteur recevrait pour ce même contenu. */
	private function toHtml(string $html): string {

		$xml = $this->toXml($html);

		if($xml === '') {
			return '';
		}

		return new \editor\ReadorFormatterUi()->getFromXml($xml, ['domain' => 'exemple.org']);

	}

	/** Une figure telle que l'éditeur la poste, avec les attributs que le nettoyage exige. */
	private function getFigure(string $type, array $attributes): string {

		$h = '<figure data-interactive="false"><div data-type="'.$type.'" data-w="100" data-h="80"';

		foreach($attributes as $name => $value) {
			$h .= ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES).'"';
		}

		return $h.'></div></figure>';

	}

	private function getImage(array $attributes = []): string {
		return $this->getFigure('image', $attributes + ['data-url' => self::URL, 'data-color' => '#ffffff']);
	}

	private function getVideo(string $url, string $source = 'youtube'): string {
		return $this->getFigure('video', ['data-source' => $source, 'data-url' => $url]);
	}

	/** L'adresse que la base retient pour la vidéo postée, vide si la vidéo a été retirée. */
	private function getStoredVideoUrl(string $html): string {

		if(preg_match('/<video [^>]*url="([^"]*)"/', $this->toXml($html), $match) !== 1) {
			return '';
		}

		return html_entity_decode($match[1]);

	}

	public function testALegitimateImageIsKept(): void {

		$xml = $this->toXml($this->getImage());

		$this->assertTrue(str_contains($xml, '<image '), 'l\'image en https reste');
		$this->assertTrue(str_contains($xml, 'url="'.self::URL.'"'), 'et garde son adresse');

	}

	public function testAnExecutableImageUrlLosesTheImage(): void {

		$this->assertFalse(str_contains($this->toXml($this->getImage(['data-url' => 'javascript:alert(1)'])), '<image'), 'une adresse javascript: emporte l\'image');
		$this->assertFalse(str_contains($this->toXml($this->getImage(['data-url' => 'data:text/html;base64,PHN2Zz4='])), '<image'), 'une adresse data: aussi');

	}

	/**
	 * Une adresse sans schéma passait la liste noire du lien de texte, où elle désigne une page du
	 * site. En `src`, elle ne désigne rien que le rédacteur maîtrise : les images arrivent toutes du
	 * stockage, en absolu.
	 */
	public function testARelativeImageUrlLosesTheImage(): void {

		$this->assertFalse(str_contains($this->toXml($this->getImage(['data-url' => '/media/photo.jpg'])), '<image'), 'une adresse relative n\'est pas une source d\'image');
		$this->assertFalse(str_contains($this->toXml($this->getImage(['data-url' => '//media.exemple.org/photo.jpg'])), '<image'), 'une adresse sans schéma non plus');

	}

	public function testALegitimateImageLinkIsKept(): void {

		$xml = $this->toXml($this->getImage(['data-link' => 'https://exemple.org/article']));

		$this->assertTrue(str_contains($xml, 'link="https://exemple.org/article"'), 'le lien en https reste');

	}

	/** Le lien est facultatif : c'est lui qui part, pas l'image qui le porte. */
	public function testAnExecutableImageLinkLosesTheLinkOnly(): void {

		$xml = $this->toXml($this->getImage(['data-link' => 'javascript:alert(document.cookie)']));

		$this->assertTrue(str_contains($xml, '<image '), 'l\'image reste');
		$this->assertFalse(str_contains($xml, 'link='), 'son lien javascript: part');

		$this->assertFalse(str_contains($this->toHtml($this->getImage(['data-link' => 'javascript:alert(document.cookie)'])), 'javascript:'), 'et le lecteur n\'en voit rien');

	}

	/** La liste noire du lien de texte : un lien d'image y répond, il se suit d'un clic comme lui. */
	public function testAMailtoImageLinkIsKept(): void {

		$this->assertTrue(str_contains($this->toXml($this->getImage(['data-link' => 'mailto:contact@exemple.org'])), 'link="mailto:contact@exemple.org"'), 'un lien mailto: reste');
		$this->assertTrue(str_contains($this->toXml($this->getImage(['data-link' => '/boutique'])), 'link="/boutique"'), 'un lien relatif aussi');

	}

	public function testALegitimateVideoIsKept(): void {

		$this->assertTrue(str_contains($this->toXml($this->getVideo('https://www.youtube.com/embed/abc')), '<video '), 'la vidéo en https reste');

	}

	/**
	 * Le cas le plus grave : le formateur pose cette adresse en `src` d'une `<iframe>`, qu'un schéma
	 * exécutable fait tourner dans l'origine de la page, sans clic.
	 */
	public function testAnExecutableVideoUrlLosesTheVideo(): void {

		$html = $this->getVideo('javascript:alert(document.cookie)');

		$this->assertFalse(str_contains($this->toXml($html), '<video'), 'une adresse javascript: emporte la vidéo');
		$this->assertFalse(str_contains($this->toHtml($html), 'javascript:'), 'et le lecteur ne reçoit pas d\'iframe');

		$this->assertFalse(str_contains($this->toXml($this->getVideo('data:text/html,<script>alert(1)</script>')), '<video'), 'une adresse data: aussi');

	}

	/**
	 * Les deux filtres normalisent la valeur avant de lire son schéma : le navigateur ignore les
	 * espaces, les tabulations et les caractères de contrôle qu'on glisse dans un schéma.
	 */
	public function testAnObfuscatedSchemeIsRefused(): void {

		$this->assertFalse(str_contains($this->toXml($this->getVideo("java\tscript:alert(1)")), '<video'), 'une tabulation dans le schéma ne le déguise pas');
		$this->assertFalse(str_contains($this->toXml($this->getVideo(' JaVaScRiPt:alert(1)')), '<video'), 'ni la casse et l\'espace de tête');
		$this->assertFalse(str_contains($this->toXml($this->getImage(['data-link' => "java\nscript:alert(1)"])), 'link='), 'le lien d\'image suit la même normalisation');

	}

	/** Une adresse en https hors de la liste des hébergeurs, sous la source d'un hébergeur connu. */
	public function testAnUnlistedVideoHostLosesTheVideo(): void {

		$html = $this->getVideo('https://piege.exemple.org/embed/abc');

		$this->assertFalse(str_contains($this->toXml($html), '<video'), 'un hôte hors liste emporte la vidéo');
		$this->assertFalse(str_contains($this->toHtml($html), '<iframe'), 'et le lecteur ne reçoit pas d\'iframe');

	}

	/** Les déguisements d'hôte que laisserait passer un contrôle sur le début ou le milieu de l'adresse. */
	public function testADisguisedVideoHostLosesTheVideo(): void {

		$urls = [
			'https://www.youtube.com.piege.exemple.org/embed/abc' => 'un hôte qui commence comme celui de la liste',
			'https://youtube.piege.exemple.org/embed/abc' => 'un sous-domaine d\'un hôte étranger',
			'https://www.youtube.com@piege.exemple.org/embed/abc' => 'un hôte de la liste en identifiant de connexion',
			'https://piege.exemple.org/www.youtube.com/embed/abc' => 'un hôte de la liste dans le chemin',
			'https://piege.exemple.org/?v=https://www.youtube.com/embed/abc' => 'un hôte de la liste dans la requête',
		];

		foreach($urls as $url => $case) {

			$html = $this->getVideo($url);

			$this->assertFalse(str_contains($this->toXml($html), '<video'), $case.' emporte la vidéo');
			$this->assertFalse(str_contains($this->toHtml($html), 'piege'), $case.' n\'atteint pas le lecteur');

		}

	}

	/** Une source que la liste ne connaît pas, même avec l'adresse d'un hébergeur connu. */
	public function testAnUnknownVideoSourceLosesTheVideo(): void {

		$this->assertFalse(str_contains($this->toXml($this->getVideo('https://www.youtube.com/embed/abc', 'autre')), '<video'), 'une source inconnue emporte la vidéo');
		$this->assertFalse(str_contains($this->toXml($this->getVideo('https://www.youtube.com/embed/abc', '')), '<video'), 'une source vide aussi');
		$this->assertFalse(str_contains($this->toXml($this->getVideo('https://www.youtube.com/embed/abc', 'vimeo')), '<video'), 'une source à laquelle l\'adresse ne répond pas aussi');

	}

	/** La base ne retient que l'adresse que le serveur recompose, jamais celle qui a été postée. */
	public function testAVideoUrlIsRewrittenCanonical(): void {

		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', $this->getStoredVideoUrl($this->getVideo('https://youtu.be/abc')), 'youtu.be devient l\'adresse d\'embed');
		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', $this->getStoredVideoUrl($this->getVideo('https://www.youtube.com/watch?v=abc&t=12')), 'une page YouTube aussi');
		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', $this->getStoredVideoUrl($this->getVideo('https://www.youtube.com/embed/abc?autoplay=1&next=https://piege.exemple.org')), 'seul l\'identifiant de la vidéo est repris');
		$this->assertEquals('https://player.vimeo.com/video/123456?dnt=1', $this->getStoredVideoUrl($this->getVideo('https://vimeo.com/123456', 'vimeo')), 'vimeo.com devient l\'adresse d\'embed');
		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', $this->getStoredVideoUrl($this->getVideo('http://www.youtube.com/watch?v=abc')), 'une adresse collée en http donne un lecteur en https');
		$this->assertEquals('https://player.vimeo.com/video/123456?dnt=1', $this->getStoredVideoUrl($this->getVideo('http://vimeo.com/123456', 'vimeo')), 'chez Vimeo aussi');

	}

	/**
	 * Une adresse de l'hébergeur qui ne désigne pas une vidéo ne pose aucun lecteur : celui qu'on
	 * poserait sur un mot de son chemin n'afficherait qu'une erreur.
	 */
	public function testAnAddressWithoutAVideoLosesTheVideo(): void {

		$urls = [
			['youtube', 'https://www.youtube.com/', 'l\'accueil de YouTube'],
			['youtube', 'https://www.youtube.com/@chaine', 'une chaîne YouTube'],
			['youtube', 'https://www.youtube.com/user/chaine', 'une chaîne YouTube à l\'ancienne adresse'],
			['youtube', 'https://www.youtube.com/playlist?list=PL123', 'une playlist YouTube'],
			['youtube', 'https://www.youtube.com/watch?list=PL123', 'une page de lecture sans vidéo'],
			['youtube', 'https://www.youtube.com/embed/', 'une adresse d\'embed sans identifiant'],
			['youtube', 'https://youtu.be/', 'une adresse courte sans identifiant'],
			['youtube', 'https://www.youtube.com/embed/videoseries?list=PL123', 'le lecteur d\'une playlist'],
			['youtube', 'https://www.youtube.com/embed/live_stream?channel=UC123', 'le lecteur du direct d\'une chaîne'],
			['youtube', 'https://www.youtube.com/watch?v=videoseries&list=PL123', 'le lecteur d\'une playlist en page de lecture'],
			['youtube', 'https://www.youtube.com/watch?v=live_stream&channel=UC123', 'le lecteur du direct d\'une chaîne en page de lecture'],
			['youtube', 'https://youtu.be/videoseries?list=PL123', 'le lecteur d\'une playlist en adresse courte'],
			['youtube', 'https://youtu.be/live_stream', 'le lecteur du direct d\'une chaîne en adresse courte'],
		];

		foreach($urls as [$source, $url, $case]) {
			$this->assertFalse(str_contains($this->toXml($this->getVideo($url, $source)), '<video'), $case.' emporte la vidéo');
		}

	}

	/** Les adresses de partage qui désignent bien une vidéo, dont l'identifiant n'est pas au premier segment. */
	public function testAVideoIdIsReadBehindItsPath(): void {

		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', $this->getStoredVideoUrl($this->getVideo('https://www.youtube.com/shorts/abc?si=x')), 'un Short est lu par son identifiant');
		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', $this->getStoredVideoUrl($this->getVideo('https://www.youtube.com/live/abc')), 'un direct aussi');
		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', $this->getStoredVideoUrl($this->getVideo('https://www.youtube.com/watch?list=PL123&v=abc')), 'une vidéo ouverte depuis une playlist aussi');
		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', $this->getStoredVideoUrl($this->getVideo('https://www.youtube.com/embed/abc/')), 'une barre finale ne cache pas l\'identifiant');

	}

	/** Un contenu enregistré repasse par le nettoyage à chaque enregistrement : son adresse d'embed ne bouge pas. */
	public function testACanonicalVideoUrlIsKept(): void {

		$urls = [
			'youtube' => 'https://www.youtube-nocookie.com/embed/abc?rel=0',
			'vimeo' => 'https://player.vimeo.com/video/123456?dnt=1',
		];

		foreach($urls as $source => $url) {
			$this->assertEquals($url, $this->getStoredVideoUrl($this->getVideo($url, $source)), 'l\'adresse d\'embed de '.$source.' se relit à l\'identique');
		}

	}

	/**
	 * Une vidéo enregistrée avant le suivi réduit porte l'adresse d'embed d'alors. Le rendu et chaque
	 * nouvel enregistrement la recomposent : elle passe au suivi réduit sans reprise de la base.
	 */
	public function testAStoredVideoGetsReducedTracking(): void {

		$urls = [
			'youtube' => ['https://www.youtube.com/embed/abc?rel=0', 'https://www.youtube-nocookie.com/embed/abc?rel=0'],
			'vimeo' => ['https://player.vimeo.com/video/123456', 'https://player.vimeo.com/video/123456?dnt=1'],
		];

		foreach($urls as $source => [$stored, $expected]) {

			$this->assertEquals($expected, $this->getStoredVideoUrl($this->getVideo($stored, $source)), 'un nouvel enregistrement passe '.$source.' au suivi réduit');

			$xml = '<video source="'.$source.'" url="'.$stored.'" width="100" height="80"/>';
			$xml = preg_replace('/<video [^>]*\/>/', $xml, $this->toXml($this->getVideo($stored, $source)));
			$html = new \editor\ReadorFormatterUi()->getFromXml($xml, ['domain' => 'exemple.org']);

			$this->assertTrue(str_contains($xml, 'url="'.$stored.'"'), 'le XML de départ porte l\'adresse d\'avant le suivi réduit pour '.$source);
			$this->assertTrue(str_contains($html, 'src="'.htmlspecialchars($expected).'"'), 'le rendu passe '.$source.' au suivi réduit');

		}

	}

	/** Le nom de marque de chaque hébergeur, que cite le panneau d'insertion et que lit le bloc Vidéo. */
	public function testAVideoHostHasItsName(): void {

		$this->assertEquals(['YouTube', 'Vimeo'], array_column(\editor\EditorSetting::VIDEO, 'name'), 'chaque hébergeur porte son nom de marque');
		$this->assertEquals('YouTube ou Vimeo', new \editor\EditorUi()->getVideoNames(), 'le panneau d\'insertion les cite dans l\'ordre de la liste');
		$this->assertTrue(new \editor\EditorUi()->getVideoConfigure()->body !== '', 'le panneau d\'insertion se rend');

	}

	/**
	 * La liste n'a pas de valeur par défaut : `getVideoUrl()`, le script de l'éditeur et le bloc Vidéo
	 * lisent chaque clé de chaque entrée, paramètres de démarrage et de départ à l'instant compris.
	 */
	public function testEveryVideoHostDeclaresItsEntry(): void {

		foreach(\editor\EditorSetting::VIDEO as $source => $video) {

			foreach(['name', 'pattern', 'embed', 'autoplay', 'start'] as $key) {
				$this->assertTrue(array_key_exists($key, $video) and is_string($video[$key]) and $video[$key] !== '', $source.' déclare '.$key);
			}

			$this->assertTrue(str_contains($video['embed'], '{id}'), 'le gabarit de '.$source.' reçoit l\'identifiant');
			$this->assertTrue(str_contains($video['start'], '{s}'), 'le départ de '.$source.' reçoit l\'instant');

		}

	}

	/**
	 * Le départ à l'instant d'une étape (`$start`) : un paramètre de plus chez YouTube, un fragment
	 * chez Vimeo, posé après toute la requête, démarrage compris. Le gabarit garde `{s}`, que seul le
	 * script remplace.
	 */
	public function testVideoUrlStartsAtAnInstant(): void {

		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0&autoplay=1&start={s}', \editor\EditorSetting::getVideoUrl('https://www.youtube.com/watch?v=abc', 'youtube', TRUE, TRUE), 'YouTube part de l\'instant par sa requête');
		$this->assertEquals('https://player.vimeo.com/video/123?dnt=1&autoplay=1#t={s}s', \editor\EditorSetting::getVideoUrl('https://vimeo.com/123', 'vimeo', TRUE, TRUE), 'Vimeo par un fragment, après la requête');
		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0&autoplay=1', \editor\EditorSetting::getVideoUrl('https://www.youtube.com/watch?v=abc', 'youtube', TRUE), 'sans départ demandé, l\'adresse ne change pas');

	}

	/**
	 * Un hébergeur sorti de la liste, Dailymotion depuis le 10/10/2026 : son adresse collée ne pose plus
	 * de vidéo, et une vidéo déjà enregistrée ne pose plus d'iframe, ni chez le lecteur ni dans l'éditeur
	 * rouvert, puis disparaît au prochain enregistrement du texte.
	 */
	public function testARemovedVideoHostLosesItsVideos(): void {

		foreach(['https://dai.ly/x8abc', 'https://www.dailymotion.com/video/x8abc', 'https://www.dailymotion.com/embed/video/x8abc'] as $url) {
			$this->assertFalse(str_contains($this->toXml($this->getVideo($url, 'dailymotion')), '<video'), $url.' ne pose plus de vidéo');
			$this->assertFalse(str_contains($this->toXml($this->getVideo($url, 'youtube')), '<video'), $url.' sous la source d\'un autre hébergeur non plus');
		}

		$kept = '<figure interactive="true" size="compressed"><video source="youtube" url="https://www.youtube.com/embed/abc?rel=0" width="854" height="480"/></figure>';
		$removed = '<figure interactive="true" size="compressed"><video source="dailymotion" url="https://www.dailymotion.com/embed/video/x8abc" width="854" height="480"/><figcaption>Paillage</figcaption></figure>';
		$xml = '<main>'.$kept.$removed.'<p>Texte</p></main>';

		$html = new \editor\ReadorFormatterUi()->getFromXml($xml, ['domain' => 'exemple.org']);

		$this->assertEquals(1, substr_count($html, '<iframe'), 'le lecteur ne garde que l\'iframe de la vidéo encore listée');
		$this->assertEquals(1, substr_count($html, '<figure'), 'la figure de la vidéo retirée part');
		$this->assertEquals(0, substr_count($html, '<figcaption'), 'avec sa légende');
		$this->assertFalse(str_contains($html, 'Paillage'), 'dont le texte ne reste pas seul');
		$this->assertFalse(str_contains($html, 'dailymotion'), 'et son adresse n\'atteint pas le lecteur');
		$this->assertTrue(str_contains($html, 'Texte'), 'le reste du texte est rendu');

		$editor = new \editor\EditorFormatterUi()->getFromXml($xml);
		$saved = $this->toXml($editor);

		$this->assertFalse(str_contains($editor, 'dailymotion'), 'l\'éditeur rouvert ne la montre plus');
		$this->assertFalse(str_contains($saved, 'dailymotion'), 'et le prochain enregistrement l\'efface');
		$this->assertTrue(str_contains($saved, 'url="https://www.youtube-nocookie.com/embed/abc?rel=0"'), 'sans toucher à la vidéo encore listée');

	}

	/**
	 * Le démarrage n'est demandé que par l'appelant qui pose le lecteur au clic, et n'est jamais
	 * enregistré, même quand l'adresse collée le porte.
	 */
	public function testAutoplayIsAddedOnDemandOnly(): void {

		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0&autoplay=1', \editor\EditorSetting::getVideoUrl('https://youtu.be/abc', 'youtube', TRUE), 'YouTube démarre au clic');
		$this->assertEquals('https://player.vimeo.com/video/123456?dnt=1&autoplay=1', \editor\EditorSetting::getVideoUrl('https://vimeo.com/123456', 'vimeo', TRUE), 'Vimeo aussi');
		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', \editor\EditorSetting::getVideoUrl('https://youtu.be/abc', 'youtube'), 'sans demande, aucun démarrage');
		$this->assertEquals('https://www.youtube-nocookie.com/embed/abc?rel=0', $this->getStoredVideoUrl($this->getVideo('https://www.youtube.com/watch?v=abc&autoplay=1')), 'un démarrage collé n\'est pas enregistré');

	}

	/**
	 * Un contenu déjà en base ne repasse pas par le nettoyage pour être lu : c'est le rendu qui le
	 * recompose. Le XML est celui d'une vidéo légitime, dont seule l'adresse ou la source est forgée.
	 */
	public function testAForgedStoredVideoRendersNoIframe(): void {

		$xml = $this->toXml($this->getVideo('https://www.youtube.com/embed/abc?rel=0'));
		$this->assertTrue(str_contains($xml, '<video '), 'le XML de départ porte une vidéo');

		$html = new \editor\ReadorFormatterUi()->getFromXml($xml, ['domain' => 'exemple.org']);
		$this->assertTrue(str_contains($html, 'src="https://www.youtube-nocookie.com/embed/abc?rel=0"'), 'la vidéo légitime garde son iframe');

		$forged = str_replace('https://www.youtube-nocookie.com/embed/abc?rel=0', 'https://piege.exemple.org/embed/abc', $xml);
		$html = new \editor\ReadorFormatterUi()->getFromXml($forged, ['domain' => 'exemple.org']);

		$this->assertFalse(str_contains($html, '<iframe'), 'une vidéo hors liste déjà en base ne produit pas d\'iframe');
		$this->assertFalse(str_contains($html, 'piege'), 'et son adresse n\'atteint pas le lecteur');

		// Une figure sans légende part déjà comme figure vide : seule la légende éprouve le retrait de la figure
		$forged = str_replace('</figure>', '<figcaption>Légende</figcaption></figure>', $forged);
		$html = new \editor\ReadorFormatterUi()->getFromXml($forged, ['domain' => 'exemple.org']);

		$this->assertTrue(str_contains($forged, '<figcaption>'), 'le XML forgé porte une légende');
		$this->assertFalse(str_contains($html, '<figure'), 'la figure vidée de sa vidéo part avec sa légende, comme à l\'écriture');

		$forged = str_replace('source="youtube"', 'source="autre"', $xml);
		$this->assertFalse(str_contains(new \editor\ReadorFormatterUi()->getFromXml($forged, ['domain' => 'exemple.org']), '<iframe'), 'une source inconnue déjà en base non plus');

	}

	/** Le chemin du produit : le contenu d'une page de site passe par `build()`, que l'API emprunte aussi. */
	public function testAWebpageBuildDropsAnUnlistedVideo(): void {

		$eWebpage = new \website\Webpage();
		$eWebpage->build(['content'], ['content' => $this->getVideo('https://piege.exemple.org/embed/abc')]);

		$this->assertEquals(NULL, $eWebpage['content'], 'la page enregistrerait un contenu vide, sans la vidéo');

		$eWebpage = new \website\Webpage();
		$eWebpage->build(['content'], ['content' => $this->getVideo('https://youtu.be/abc')]);

		$this->assertTrue(str_contains($eWebpage['content'], 'url="https://www.youtube-nocookie.com/embed/abc?rel=0"'), 'et la vidéo légitime sous son adresse d\'embed');

	}

	/** Non-régression du lien de texte, dont la liste noire ne doit pas se durcir. */
	public function testATextLinkKeepsItsSchemes(): void {

		$this->assertTrue(str_contains($this->toXml('<p><a href="mailto:contact@exemple.org">écrire</a></p>'), 'url="mailto:contact@exemple.org"'), 'mailto: passe');
		$this->assertTrue(str_contains($this->toXml('<p><a href="tel:+33123456789">appeler</a></p>'), 'url="tel:+33123456789"'), 'tel: passe');
		$this->assertTrue(str_contains($this->toXml('<p><a href="/boutique">la boutique</a></p>'), 'url="/boutique"'), 'une adresse relative passe');
		$this->assertFalse(str_contains($this->toXml('<p><a href="javascript:alert(1)">piège</a></p>'), '<a'), 'javascript: ne passe pas');

	}

}
?>
