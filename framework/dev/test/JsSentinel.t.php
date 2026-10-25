<?php
namespace dev;

/**
 * Règles du CLAUDE.md sur le JavaScript, des sections « Architecture », « Gestion des erreurs » et
 * « Style PHP » pour aria.
 *
 * Lancement : dev/test package=dev file=JsSentinel
 */
class JsSentinelTest extends SentinelTest {

	const DOM_CONTENT_LOADED = [
		'commercialisation/selling/static/js/marketFamilyAi.js' => 1,
		'commercialisation/shop/static/js/customizeAi.js' => 1,
		'commercialisation/shop/static/js/shop.js' => 1,
		// Le cadriciel écoute le premier chargement pour émettre navigation.wakeup
		'framework/util/static/js/ajax.js' => 1,
		'framework/util/static/js/lime.js' => 3,
		'ouvretaferme/website/static/js/customizeAi.js' => 1,
		'production/map/static/js/zone.js' => 1,
		'production/series/static/js/place.js' => 1,
	];
	const RAW_FETCH = [
		// Surcharge de Ajax.Query.fetch()
		'accounting/bank/static/js/annotationAi.js' => 1,
		'accounting/vat/static/js/vat-cerfa.js' => 1,
		'commercialisation/shop/static/js/auditAi.js' => 1,
		'framework/dev/static/js/doc.js' => 1,
		// Ajax.Query elle-même
		'framework/util/static/js/ajax.js' => 2,
		// SceneLoader.read(), seule lecture des fichiers statiques de la scène (models.json, models.bin, plants.svg) :
		// aucune instruction ajax à rendre, aucun envoi
		'base/scene/static/js/loader.js' => 1,
		// Service worker de la caisse : Ajax.Query n'existe pas dans un worker, et il ne fait que lire des fichiers
		// statiques sans cookie ou relayer au réseau une requête telle quelle, sans instruction ajax à rendre
		'commercialisation/selling/static/js/marketDeviceWorkerAi.js' => 2,
		'commercialisation/selling/static/js/marketDeviceWorkerDevAi.js' => 2,
	];
	const CLASS_PREFIX = [
		'accounting/asset/static/js/asset.js' => 1,
		'accounting/bank/static/js/cashflow.js' => 3,
		'accounting/bank/static/js/reconciliation.js' => 1,
		'accounting/journal/static/js/amount.js' => 1,
		'accounting/journal/static/js/operation.js' => 1,
		'accounting/preaccounting/static/js/reconciliate.js' => 1,
		'accounting/thirdParty/static/js/address.js' => 1,
		'base/mail/static/js/contact.js' => 1,
		'base/media/static/js/conf.js' => 2,
		'base/media/static/js/editor.js' => 1,
		'base/media/static/js/gallery.js' => 1,
		'base/media/static/js/plant-vignette.js' => 1,
		'base/media/static/js/product-vignette.js' => 1,
		'base/media/static/js/shop-banner.js' => 1,
		'base/media/static/js/shop-logo.js' => 1,
		'base/media/static/js/structure-banner.js' => 1,
		'base/media/static/js/structure-email-banner.js' => 1,
		'base/media/static/js/structure-logo.js' => 1,
		'base/media/static/js/tool-vignette.js' => 1,
		'base/media/static/js/upload.js' => 4,
		'base/media/static/js/user-vignette.js' => 1,
		'base/media/static/js/webpage-banner.js' => 1,
		'base/media/static/js/website-banner.js' => 1,
		'base/media/static/js/website-favicon.js' => 1,
		'base/media/static/js/website-logo.js' => 1,
		'base/product/static/js/priceInitial.js' => 1,
		'base/structure/static/js/production.js' => 1,
		'commercialisation/payment/static/js/stripe.js' => 1,
		'commercialisation/payment/static/js/wallet.js' => 1,
		'commercialisation/selling/static/js/customer.js' => 1,
		'commercialisation/selling/static/js/grid.js' => 1,
		'commercialisation/selling/static/js/invoice.js' => 1,
		'commercialisation/selling/static/js/item.js' => 1,
		'commercialisation/selling/static/js/market.js' => 1,
		'commercialisation/selling/static/js/merchant.js' => 1,
		'commercialisation/selling/static/js/sale.js' => 1,
		'commercialisation/shop/static/js/basket.js' => 1,
		'commercialisation/shop/static/js/manage.js' => 1,
		'framework/editor/static/js/gallery.js' => 1,
		'framework/editor/static/js/readitor.js' => 1,
		'framework/editor/static/js/reador.js' => 3,
		'framework/util/static/js/batch.js' => 1,
		'framework/util/static/js/column.js' => 1,
		'framework/util/static/js/form.js' => 11,
		'ouvretaferme/analyze/static/js/report.js' => 1,
		'ouvretaferme/main/static/js/chart.js' => 1,
		'ouvretaferme/main/static/js/customize.js' => 1,
		'ouvretaferme/main/static/js/doc.js' => 1,
		'ouvretaferme/main/static/js/omnibox.js' => 1,
		'production/plant/static/js/forecast.js' => 1,
		'production/plant/static/js/varietyRating.js' => 1,
		'production/sequence/static/js/crop.js' => 1,
		'production/sequence/static/js/flow.js' => 1,
		'production/sequence/static/js/slice.js' => 1,
		'production/series/static/js/cultivation.js' => 1,
		'production/series/static/js/task.js' => 1,
		'production/series/static/js/timesheet.js' => 1,
		'production/work/static/js/routine.js' => 1,
	];

	/**
	 * Les classes de premier niveau, par nom, avec leur emplacement.
	 * @var array<string, string[]>
	 */
	private array $classes = [];

	/**
	 * Les membres de chaque classe de premier niveau, et la classe qu'elle étend.
	 * @var array<string, array{parent: ?string, members: array<string, true>}>
	 */
	private array $members = [];

	public function init() {

		foreach(SentinelLib::getFiles('.js') as $file => $path) {

			if(str_contains($file, '/static/js/') === FALSE) {
				continue;
			}

			$lines = explode("\n", file_get_contents($path));

			[$app, $package] = explode('/', $file);

			$this->addFound('domContentLoaded', $file, $this->findPattern($lines, '/DOMContentLoaded/'));
			$this->addFound('rawFetch', $file, $this->findPattern($lines, '/(^|[^.\w])fetch\s*\(/'));
			$this->addFound('aria', $file, $this->findPattern($lines, '/\baria-[a-z]/'));
			$this->addFound('classPrefix', $file, $this->findClassWithoutPrefix($lines, [$app, $package]));
			$this->addFound('duplicateMember', $file, $this->findDuplicateMembers($lines));

			foreach($this->getClasses($lines) as $line => $class) {
				$this->classes[$class][] = $file.':'.$line;
			}

			$this->members += $this->getMembers($lines);

		}

		foreach(SentinelLib::getFiles('.php') as $file => $path) {

			if(self::isPresentation($file)) {
				$this->addFound('unknownMember', $file, $this->findUnknownMembers(SentinelLib::getTokens(file_get_contents($path))));
			}

		}

	}

	/**
	 * @param string[] $lines
	 * @return int[]
	 */
	private function findPattern(array $lines, string $pattern): array {

		$found = [];

		foreach($lines as $position => $line) {

			if(preg_match($pattern, $line) === 1) {
				$found[] = $position + 1;
			}

		}

		return $found;

	}

	/**
	 * @param string[] $lines
	 * @return array<int, string> Nom de chaque classe de premier niveau, par ligne
	 */
	private function getClasses(array $lines): array {

		$classes = [];

		foreach($lines as $position => $line) {

			if(preg_match('/^class ([A-Za-z0-9_]+)/', $line, $match) === 1) {
				$classes[$position + 1] = $match[1];
			}

		}

		return $classes;

	}

	/**
	 * Un membre se reconnaît à sa place, un niveau sous la classe, que le fichier soit indenté par
	 * tabulation ou par espaces : `static changeMode(target) {`, `static running = false;`,
	 * `get value() {`. Une ligne plus profonde est du corps de méthode.
	 *
	 * @param string[] $lines
	 * @return array<string, array{parent: ?string, members: array<string, true>}>
	 */
	private function getMembers(array $lines): array {

		$members = [];
		$current = NULL;

		foreach($lines as $line) {

			if(preg_match('/^class ([A-Za-z0-9_]+)(?:\s+extends\s+([A-Za-z0-9_.]+))?/', $line, $match) === 1) {
				$current = $match[1];
				$members[$current] = ['parent' => $match[2] ?? NULL, 'members' => []];
			} else if(preg_match('/^[^\s}]/', $line) === 1) {
				$current = NULL;
			} else if($current !== NULL and preg_match('/^(?:\t| {2}| {4})(?:static\s+)?(?:async\s+)?(?:get\s+|set\s+)?\*?([A-Za-z0-9_$]+)\s*[(=;]/', $line, $match) === 1) {
				$members[$current]['members'][$match[1]] = TRUE;
			}

		}

		return $members;

	}

	/**
	 * Un membre déclaré une seconde fois dans la même classe remplace le premier sans erreur, et les appelants
	 * du premier reçoivent le second (`PlanningTask.press()`, retour 105 de Répartir). Les membres sont lus à
	 * l'indentation du premier d'entre eux, pour ne pas prendre un `if(` de méthode pour un membre dans un
	 * fichier indenté par espaces. Un `get` et un `set` du même nom vont ensemble, mais l'un ou l'autre
	 * remplace une méthode ou un champ du même nom, et réciproquement. Un membre statique et un membre
	 * d'instance du même nom ne se remplacent pas.
	 *
	 * @param string[] $lines
	 * @return int[] La ligne de chaque seconde déclaration
	 */
	private function findDuplicateMembers(array $lines): array {

		$found = [];
		$indent = NULL;
		$declared = NULL;

		foreach($lines as $position => $line) {

			if(preg_match('/^class [A-Za-z0-9_]+/', $line) === 1) {
				$indent = NULL;
				$declared = [];
			} else if(preg_match('/^[^\s}]/', $line) === 1) {
				$declared = NULL;
			} else if($declared !== NULL and preg_match('/^(\s+)(?!\/\/|\/\*|\*)(\S)/', $line, $match) === 1) {

				$indent ??= $match[1];

				if($match[1] === $indent and preg_match('/^\s+(static\s+)?(?:async\s+)?(get\s+|set\s+)?\*?(#?[A-Za-z0-9_$]+)\s*[(=;]/', $line, $member) === 1) {

					$name = $member[1].$member[3];
					$kind = trim($member[2]);
					$kinds = $declared[$name] ?? [];

					if(in_array($kind, $kinds, TRUE) or ($kinds !== [] and ($kind === '' or in_array('', $kinds, TRUE)))) {
						$found[] = $position + 1;
					}

					$declared[$name][] = $kind;

				}

			}

		}

		return $found;

	}

	/**
	 * Les appels `Classe.membre(` écrits dans les chaînes d'une vue (`onchange`, `onclick`,
	 * `onrender`…) dont la classe est une classe JS du dépôt qui ne porte pas ce membre, ni elle ni
	 * ses parents. Une classe étendue hors du dépôt (`HTMLElement`) peut le porter : elle l'excuse.
	 * Une classe inconnue n'est pas jugée, le texte d'une vue nomme aussi des classes PHP.
	 *
	 * @return int[]
	 */
	private function findUnknownMembers(array $tokens): array {

		$found = [];

		foreach($tokens as $token) {

			$string = self::getString($token);

			if($string === NULL) {
				continue;
			}

			preg_match_all('/(?<![\w.$\\\\])([A-Z][A-Za-z0-9_]*)\.([a-z_][A-Za-z0-9_]*)\(/', $string, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

			foreach($matches as $match) {

				if($this->hasMember($match[1][0], $match[2][0]) === FALSE) {
					$found[] = $token[2] + substr_count(substr($string, 0, $match[0][1]), "\n");
				}

			}

		}

		return $found;

	}

	private function hasMember(string $class, string $member): bool {

		while(isset($this->members[$class])) {

			if(isset($this->members[$class]['members'][$member])) {
				return TRUE;
			}

			$class = $this->members[$class]['parent'];

			if($class === NULL) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * @param string[] $lines
	 * @param string[] $prefixes
	 * @return int[]
	 */
	private function findClassWithoutPrefix(array $lines, array $prefixes): array {

		$found = [];

		foreach($this->getClasses($lines) as $line => $class) {

			$prefixed = array_filter($prefixes, fn(string $prefix) => str_starts_with($class, ucfirst($prefix)));

			if($prefixed === []) {
				$found[] = $line;
			}

		}

		return $found;

	}

	public function testDetectors(): void {

		$lines = explode("\n", <<<'JS'
			class Analyze {
			}
			class AccountingAnalyze {
				static run() {
					document.addEventListener('DOMContentLoaded', () => {});
					fetch('/x');
					new Ajax.Query(this).fetch();
					node.setAttribute('aria-hidden', 'true');
				}
			}
			JS);

		$this->assertEquals([5], $this->findPattern($lines, '/DOMContentLoaded/'), 'domContentLoaded');
		$this->assertEquals([6], $this->findPattern($lines, '/(^|[^.\w])fetch\s*\(/'), 'rawFetch');
		$this->assertEquals([8], $this->findPattern($lines, '/\baria-[a-z]/'), 'aria');
		$this->assertEquals([1], $this->findClassWithoutPrefix($lines, ['accounting', 'overview']), 'classPrefix');
		$this->assertEquals([], $this->findDuplicateMembers($lines), 'duplicateMember, une méthode par nom');

		$duplicates = explode("\n", <<<'JS'
			class AccountingClose {
			  static pressed = null;
			  static press(stamp) {
			    if(stamp) {
			    }
			    if(stamp) {
			    }
			  }
			  press(e) {
			  }
			  get value() {
			  }
			  set value(value) {
			  }
			  static press(e) {
			  }
			  static pressed() {
			  }
			  get press() {
			  }
			}
			class AccountingOpen {
			  static press(e) {
			  }
			}
			JS);

		$this->assertEquals([15, 17, 19], $this->findDuplicateMembers($duplicates), 'duplicateMember');
		$this->assertEquals([1 => 'Analyze', 3 => 'AccountingAnalyze'], $this->getClasses($lines), 'classes de premier niveau');
		$this->assertEquals(['Analyze' => ['parent' => NULL, 'members' => []], 'AccountingAnalyze' => ['parent' => NULL, 'members' => ['run' => TRUE]]], $this->getMembers($lines), 'membres des classes');

		$members = $this->members;
		$this->members = $this->getMembers($lines) + ['AccountingOverview' => ['parent' => 'AccountingAnalyze', 'members' => []]];

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			// AccountingAnalyze.stop(this)
			$a = ['onclick' => 'AccountingAnalyze.run(this)'];
			$b = ['onclick' => 'AccountingAnalyze.stop(this)'];
			$c = '<a onclick="AccountingOverview.run(this)"></a><a onclick="AccountingOverview.stop(this)"></a>';
			$d = ['onclick' => 'Unknown.stop(this)'];
			PHP);

		$this->assertEquals([4, 5], $this->findUnknownMembers($tokens), 'unknownMember');

		$this->members = $members;

	}

	public function testDuplicateClass(): void {

		$duplicates = [];

		foreach($this->classes as $class => $locations) {

			if(count($locations) > 1) {
				$duplicates[] = $class.' ('.implode(', ', $locations).')';
			}

		}

		$this->assertEquals([], $duplicates, 'classe JS déclarée deux fois, le second fichier est ignoré en navigation ajax, donner à l\'une un nom distinct, préfixé par le package ou l\'app de son fichier (BankImport, pas Import) : '.implode(' · ', $duplicates));

	}

	public function testDuplicateMember(): void {
		$this->assertRatchet('duplicateMember', [], 'membre déclaré deux fois dans la même classe JS, le second remplace le premier pour tous ses appelants : donner à chacun un nom qui dit ce qu\'il fait');
	}

	public function testDomContentLoaded(): void {
		$this->assertRatchet('domContentLoaded', self::DOM_CONTENT_LOADED, 'DOMContentLoaded ne se redéclenche pas en navigation ajax, écouter navigation.wakeup');
	}

	public function testRawFetch(): void {
		$this->assertRatchet('rawFetch', self::RAW_FETCH, 'fetch() brut, poster avec Ajax.Query');
	}

	public function testClassPrefix(): void {
		$this->assertRatchet('classPrefix', self::CLASS_PREFIX, 'classe JS de premier niveau sans préfixe de package ou d\'app, la nommer d\'après le package ou l\'app de son fichier (BankImport, pas Import)');
	}

	public function testUnknownMember(): void {
		$this->assertRatchet('unknownMember', [], 'appel depuis une vue à un membre absent de la classe JS, qui lève une erreur au geste : ajouter le membre à la classe, ou corriger l\'appel');
	}

	public function testAria(): void {
		$this->assertRatchet('aria', [], 'attribut aria, à retirer du code');
	}

}
?>
