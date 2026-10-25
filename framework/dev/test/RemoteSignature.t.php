<?php
namespace dev;

/**
 * La signature qui ouvre une route ->remote(), celle que le navigateur sans tête emprunte pour
 * rendre un PDF.
 *
 * Le contrôle était une égalité entre la clé du paquet et l'argument remoteKey. Trois choses en
 * découlaient : une clé absente ouvrait la route, NULL !== NULL étant faux ; la clé voyageait dans
 * la chaîne de requête, donc dans les journaux d'accès du serveur ; et une fois lue elle valait
 * pour tout le parc, les routes lisant un identifiant de document sans borne de structure.
 *
 * La signature ferme les trois : elle porte les arguments demandés et l'instant, la clé ne quitte
 * plus le serveur, et changer un identifiant change la signature attendue.
 *
 * Chaque refus se double du succès correspondant, sinon il ne prouverait rien : la forme fautive
 * refusait elle aussi, simplement les mauvaises requêtes.
 *
 * Lancement : dev/test package=dev file=RemoteSignature
 */
class RemoteSignatureTest extends \Test {

	const NAME = 'zzTestRemote';
	const KEY = 'zzTestCleDeSignature42';
	const PATH = '/selling/pdf:getInvoice';

	public function setUp(): void {
		\Lime::setRemoteKey(self::NAME, self::KEY);
	}

	public function tearDown(): void {
		\Lime::setRemoteKey(self::NAME, NULL);
	}

	/** Les arguments que le serveur verrait dans $_GET pour l'URL signée. */
	private function getArguments(string $url): array {

		$get = [];
		parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $get);

		return $get;

	}

	private function sign(string $url): string {
		return \Lime::signRemote(self::NAME, $url);
	}

	private function verify(string $path, array $get): bool {
		return \Lime::verifyRemote(self::NAME, $path, $get);
	}

	public function testASignedUrlOpensItsOwnRoute(): void {

		$url = $this->sign(self::PATH.'?id=42');

		$this->assertTrue($this->verify(self::PATH, $this->getArguments($url)), 'l\'URL signée ouvre le chemin qu\'elle nomme');

	}

	/** Ce que la clé statique ne savait pas faire : un autre document ne s'ouvre pas. */
	public function testAnotherDocumentIsRefused(): void {

		$get = $this->getArguments($this->sign(self::PATH.'?id=42'));

		$this->assertTrue($this->verify(self::PATH, $get), 'le document signé s\'ouvre');

		$get['id'] = '43';

		$this->assertFalse($this->verify(self::PATH, $get), 'celui d\'à côté ne s\'ouvre pas avec la même signature');

	}

	public function testAnotherPathIsRefused(): void {

		$get = $this->getArguments($this->sign(self::PATH.'?id=42'));

		$this->assertFalse($this->verify('/selling/pdf:getProof', $get), 'la signature d\'un document n\'ouvre pas une autre route');

	}

	public function testAnAddedArgumentIsRefused(): void {

		$get = $this->getArguments($this->sign(self::PATH.'?id=42'));
		$get['structure'] = '7';

		$this->assertFalse($this->verify(self::PATH, $get), 'un argument ajouté après coup invalide la signature');

	}

	/**
	 * L'ordre des arguments n'est pas celui de la signature : celui qui signe part d'une chaîne
	 * d'URL, celui qui vérifie part de $_GET, et le navigateur sans tête n'est pas tenu de les
	 * rendre dans le même ordre.
	 */
	public function testTheOrderOfTheArgumentsDoesNotMatter(): void {

		$get = $this->getArguments($this->sign(self::PATH.'?id=42&type=invoice&structure=7'));

		$this->assertTrue($this->verify(self::PATH, array_reverse($get, TRUE)), 'les mêmes arguments dans un autre ordre ouvrent la route');

	}

	public function testAnExpiredSignatureIsRefused(): void {

		$get = $this->getArguments($this->sign(self::PATH.'?id=42'));

		[$time, $signature] = explode('-', $get['remoteKey'], 2);

		$get['remoteKey'] = ((int)$time - \Lime::REMOTE_LIFETIME - 60).'-'.$signature;

		$this->assertFalse($this->verify(self::PATH, $get), 'une signature hors de sa fenêtre ne vaut plus');

	}

	/** L'horodatage entre dans la signature, donc le reculer ne suffit pas à se donner du temps. */
	public function testTheTimestampCannotBeMoved(): void {

		$get = $this->getArguments($this->sign(self::PATH.'?id=42'));

		[$time, $signature] = explode('-', $get['remoteKey'], 2);

		$get['remoteKey'] = ((int)$time + 60).'-'.$signature;

		$this->assertFalse($this->verify(self::PATH, $get), 'un horodatage déplacé invalide la signature');

	}

	public function testAMalformedSignatureIsRefused(): void {

		$this->assertFalse($this->verify(self::PATH, ['id' => '42']), 'une requête sans signature est refusée');
		$this->assertFalse($this->verify(self::PATH, ['id' => '42', 'remoteKey' => '']), 'une signature vide est refusée');
		$this->assertFalse($this->verify(self::PATH, ['id' => '42', 'remoteKey' => self::KEY]), 'la clé elle-même, postée comme avant, est refusée');
		$this->assertFalse($this->verify(self::PATH, ['id' => '42', 'remoteKey' => 'abc-def']), 'un horodatage qui n\'est pas fait que de chiffres est refusé');
		$this->assertFalse($this->verify(self::PATH, ['id' => '42', 'remoteKey' => ['x']]), 'un argument qui n\'est pas une chaîne est refusé');

	}

	/**
	 * La clé non configurée, c'est-à-dire l'installation neuve.
	 *
	 * C'est par là que les 27 routes ->remote() du dépôt s'ouvraient sans rien demander :
	 * `getRemoteKey()` rendait NULL, `GET('remoteKey', '?string')` rendait NULL sur une requête qui
	 * n'en portait pas, et l'égalité qui refusait n'était jamais vraie.
	 */
	public function testAMissingKeyClosesTheRouteInsteadOfOpeningIt(): void {

		\Lime::setRemoteKey(self::NAME, NULL);

		$this->assertFalse($this->verify(self::PATH, ['id' => '42']), 'sans clé configurée, une requête sans signature est refusée');
		$this->assertFalse($this->verify(self::PATH, ['id' => '42', 'remoteKey' => '']), 'et une signature vide aussi');

	}

	public function testSigningWithoutAKeyIsRefused(): void {

		\Lime::setRemoteKey(self::NAME, NULL);

		$this->assertError(fn() => \Lime::signRemote(self::NAME, self::PATH.'?id=42'), message: 'Missing remote key \''.self::NAME.'\'');

	}

	/** Une URL absolue et une URL relative se signent pareil : seuls le chemin et les arguments comptent. */
	public function testAnAbsoluteUrlSignsLikeARelativeOne(): void {

		$url = $this->sign('https://www.example.org'.self::PATH.'?id=42');

		$this->assertTrue($this->verify(self::PATH, $this->getArguments($url)), 'l\'URL absolue ouvre le même chemin');

	}

	/** Une URL sans aucun argument porte quand même sa signature. */
	public function testAnUrlWithoutArgumentIsSigned(): void {

		$url = $this->sign(self::PATH);

		$this->assertTrue(str_contains($url, '?remoteKey='), 'la signature ouvre la chaîne de requête');
		$this->assertTrue($this->verify(self::PATH, $this->getArguments($url)), 'et elle ouvre la route');

	}

}
?>
