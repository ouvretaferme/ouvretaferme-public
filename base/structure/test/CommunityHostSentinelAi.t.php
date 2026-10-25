<?php
namespace structure;

/**
 * Les passages directs du domaine des réseaux et les adresses réservées disent-ils la même chose ?
 *
 * Le vhost `reseau.` réécrit tout chemin vers `/reseau$1`, sauf ceux qu'il laisse passer tels quels :
 * la connexion, l'inscription, les fichiers statiques et minifiés. Un groupe dont l'adresse serait le
 * premier segment d'un de ces passages aurait une page masquée par le vhost, sans erreur nulle part.
 * `RESERVED_FQN` (`ouvretaferme/Lime.s.php`) refuse ces adresses. Rien ne rappellera la conf à qui
 * ajoutera un passage dans le vhost, ni le vhost de prod à qui le modifiera en dev : d'où cet inventaire.
 *
 * Quatre contrôles, dans cet ordre :
 * 1. les blocs `reseau.` existent : en dev, un bloc avec `location /` ; en prod, un bloc 443 avec
 *    `location /` et un bloc 80 qui ne fait qu'un `return 301` vers https. Il **arrête les suivants** :
 *    sans bloc, les passages seraient vides et tous les contrôles échoueraient ensemble ;
 * 2. les passages de `dev.cfg` sont exactement les adresses réservées, dans les deux sens ;
 * 3. les passages de `prod.cfg` sont ceux de `dev.cfg`, dans les deux sens ;
 * 4. la réécriture finale de chaque bloc mène à `/reseau$1`.
 *
 * Un passage est le premier segment de chaque `rewrite ^/x/` du `location /`, la réécriture finale
 * exceptée, et de chaque `location ~ ^/x/` du bloc et des fichiers qu'il inclut depuis le dépôt. Les
 * inclusions hors du dépôt (`/etc/letsencrypt/…`) ne portent que des réglages TLS.
 *
 * La sentinelle garde le dépôt cohérent, pas le nginx réellement chargé.
 *
 * Planche : https://claude.ai/code/artifact/6cfbb53a-b71e-45d3-8727-440f1e1f5694
 *
 * Lancement : dev/test package=structure file=CommunityHostSentinelAi
 */
class CommunityHostSentinelAiTest extends \Test {

	/** Les fichiers lus. Les preuves d'échec pointent une copie modifiée le temps d'un lancement. */
	const FILES = [
		'dev.cfg' => LIME_DIRECTORY.'/ouvretaferme/main/conf/rewrite/dev.cfg',
		'prod.cfg' => LIME_DIRECTORY.'/ouvretaferme/main/conf/rewrite/prod.cfg',
	];

	/** La racine du dépôt telle que les `include` du vhost l'écrivent. */
	const NGINX_ROOT = '/var/www/otf/';

	/** Les `location` qui ne sont pas des passages, chacune avec sa raison. */
	const LOCATIONS_IGNORED = [
		'location ~* \.ico$' => 'une extension et non un premier segment : un fqn ne peut pas contenir de point',
		'location = /_lime' => 'interne au vhost, et le tiret bas est refusé par le format',
		'location @minify' => 'une location nommée, qu\'aucune requête n\'atteint directement',
	];

	public function testHosts(): void {

		$dev = $this->read('dev.cfg');
		$prod = $this->read('prod.cfg');

		$blockDev = $this->findBlock($dev, fn(string $block) => $this->hasServerName($block) and $this->getLocationRoot($block) !== NULL);
		$blockProd = $this->findBlock($prod, fn(string $block) => $this->hasServerName($block) and preg_match('/^\s*listen 443\b/m', $block) and $this->getLocationRoot($block) !== NULL);
		$blockProdHttp = $this->findBlock($prod, fn(string $block) => $this->hasServerName($block) and preg_match('/^\s*listen 80\b/m', $block));

		// 1. Les blocs, qui arrêtent les contrôles suivants.
		$missing = [];

		if($blockDev === NULL) {
			$missing[] = 'dev.cfg : aucun bloc server_name reseau. avec location /';
		}

		if($blockProd === NULL) {
			$missing[] = 'prod.cfg : aucun bloc listen 443 server_name reseau. avec location /';
		}

		if($blockProdHttp === NULL or $this->isRedirectOnly($blockProdHttp) === FALSE) {
			$missing[] = 'prod.cfg : le bloc listen 80 server_name reseau. ne fait pas qu\'un return 301 https://reseau.';
		}

		if($missing !== []) {
			$this->assertFail(implode(' · ', $missing));
			return;
		}

		$this->assertPass();

		$passagesDev = $this->getPassages('dev.cfg', $blockDev);
		$passagesProd = $this->getPassages('prod.cfg', $blockProd);

		// 2. Les passages de dev et les adresses réservées.
		$reserved = RESERVED_FQN;

		$unreserved = array_values(array_diff($passagesDev, $reserved));
		$this->assertEquals([], $unreserved, 'dev.cfg : passage sans réservation : '.implode(', ', $unreserved));

		$unused = array_values(array_diff($reserved, $passagesDev));
		$this->assertEquals([], $unused, 'RESERVED_FQN : réservation sans passage dans dev.cfg : '.implode(', ', $unused));

		// 3. Les passages de prod et ceux de dev.
		$absentProd = array_values(array_diff($passagesDev, $passagesProd));
		$this->assertEquals([], $absentProd, 'prod.cfg : passage de dev.cfg absent : '.implode(', ', $absentProd));

		$absentDev = array_values(array_diff($passagesProd, $passagesDev));
		$this->assertEquals([], $absentDev, 'dev.cfg : passage de prod.cfg absent : '.implode(', ', $absentDev));

		// 4. La réécriture finale.
		foreach(['dev.cfg' => $blockDev, 'prod.cfg' => $blockProd] as $file => $block) {

			$rewrites = $this->getRewrites($block);
			$target = preg_match('/limeName=([^?&\s]*)/', (string)last($rewrites), $match) ? $match[1] : '';

			$this->assertEquals('/reseau$1', $target, $file.' : cible finale hors /reseau : limeName='.$target);

		}

	}

	private function read(string $file): string {

		$content = file_get_contents(self::FILES[$file]);

		if($content === FALSE) {
			throw new \Exception(self::FILES[$file].' illisible');
		}

		return $content;

	}

	/** Le premier bloc `server { … }` qui répond au critère, découpé en suivant les accolades. */
	private function findBlock(string $content, \Closure $match): ?string {

		$offset = 0;

		while(preg_match('/^\s*server\s*\{/m', $content, $found, PREG_OFFSET_CAPTURE, $offset)) {

			$start = $found[0][1] + strlen($found[0][0]);
			$block = $this->readBraces($content, $start);

			if($match($block)) {
				return $block;
			}

			$offset = $start + strlen($block);

		}

		return NULL;

	}

	/** Le contenu d'un bloc dont l'accolade ouvrante précède `$start`, sans l'accolade fermante. */
	private function readBraces(string $content, int $start): string {

		$depth = 1;
		$length = strlen($content);

		for($position = $start; $position < $length; $position++) {

			if($content[$position] === '{') {
				$depth++;
			} else if($content[$position] === '}') {

				$depth--;

				if($depth === 0) {
					return substr($content, $start, $position - $start);
				}

			}

		}

		return substr($content, $start);

	}

	private function hasServerName(string $block): bool {
		return (preg_match('/^\s*server_name reseau\./m', $block) === 1);
	}

	private function getLocationRoot(string $block): ?string {

		if(preg_match('/^\s*location \/\s*\{/m', $block, $found, PREG_OFFSET_CAPTURE) === 0) {
			return NULL;
		}

		return $this->readBraces($block, $found[0][1] + strlen($found[0][0]));

	}

	/** Un `return 301 https://reseau.…` et, à côté, rien d'autre que `listen` et `server_name`. */
	private function isRedirectOnly(string $block): bool {

		$lines = array_values(array_filter(
			array_map('trim', explode("\n", $block)),
			fn(string $line) => ($line !== '' and preg_match('/^(listen|server_name) /', $line) === 0 and preg_match('/^include .*\/acme\.cfg;$/', $line) === 0)
		));

		if(count($lines) === 3 and $lines[0] === 'location / {' and $lines[2] === '}') {
			$lines = [$lines[1]];
		}

		return (
			count($lines) === 1 and
			preg_match('/^return 301 https:\/\/reseau\./', $lines[0]) === 1
		);

	}

	/** @return string[] */
	private function getRewrites(string $block): array {

		preg_match_all('/^\s*(rewrite .*)$/m', (string)$this->getLocationRoot($block), $found);

		return $found[1];

	}

	/** @return string[] Les premiers segments laissés passer, triés. */
	private function getPassages(string $file, string $block): array {

		$passages = [];

		$rewrites = $this->getRewrites($block);
		array_pop($rewrites);

		foreach($rewrites as $rewrite) {

			if(preg_match('/^rewrite \^\/([^\/(]+)\//', $rewrite, $match)) {
				$passages[] = $match[1];
			} else {
				$this->assertFail($file.' : réécriture sans premier segment littéral : '.$rewrite);
			}

		}

		$sources = [$file => $this->removeLocationRoot($block)];

		preg_match_all('/^\s*include '.preg_quote(self::NGINX_ROOT, '/').'(\S+);/m', $block, $includes);

		foreach($includes[1] as $include) {
			$sources[$include] = file_get_contents(LIME_DIRECTORY.'/'.$include);
		}

		$ignored = [];

		foreach($sources as $source => $content) {

			preg_match_all('/^\s*(location\s[^{]*?)\s*\{/m', $content, $locations);

			foreach($locations[1] as $location) {

				if($location === 'location /') {
					continue;
				} else if(array_key_exists($location, self::LOCATIONS_IGNORED)) {
					$ignored[] = $location;
				} else if(preg_match('/^location ~ \^\/([a-z0-9\-]+)\//', $location, $match)) {
					$passages[] = $match[1];
				} else {
					$this->assertFail($file.' : location ni lue ni écartée dans '.$source.' : '.$location);
				}

			}

		}

		$declared = array_values(array_diff(array_keys(self::LOCATIONS_IGNORED), $ignored));
		$this->assertEquals([], $declared, $file.' : location écartée qui n\'existe plus : '.implode(', ', $declared));

		$passages = array_values(array_unique($passages));
		sort($passages);

		return $passages;

	}

	/** Le bloc sans son `location /`, dont les réécritures sont lues à part. */
	private function removeLocationRoot(string $block): string {

		$root = $this->getLocationRoot($block);

		return str_replace((string)$root, '', $block);

	}

}
?>
