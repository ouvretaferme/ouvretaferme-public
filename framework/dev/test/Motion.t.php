<?php
namespace dev;

/**
 * Lancement : dev/test package=dev file=Motion
 */
class MotionTest extends \Test {

	const CSS = <<<'CSS'
		.a {
			transition: opacity 150ms ease-out, transform .3s cubic-bezier(0.2, 0.8, 0.3, 1.15) 0.1s;
		}
		.a:hover {
			opacity: 0.5;
		}
		/* .b { transition: color 1s; } */
		.c {
			transition-duration: 0.2s;
		}
		.d {
			transition:
				left 0.25s,
				height 0.5s linear;
		}
		.e {
			transition: background-color var(--duration-sm) var(--ease-out);
		}
		.f {
			animation: spin 1s linear infinite;
		}
		@keyframes spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); opacity: 1; }
		}
		.g {
			content: "transition: color 2s;";
			transition: none;
		}
		.h { transition: opacity var(--speed); animation: spin 1s; }
		CSS;

	public function testLines(): void {

		$rows = MotionLib::parse(self::CSS, 'test.css');

		$this->assertEquals(
			['2:0', '2:1', '9:0', '12:0', '12:1', '17:0', '20:0', '22:0', '28:0', '30:0', '30:1'],
			array_map(fn($row) => $row['line'].':'.$row['segment'], $rows),
			'une ligne par segment, au numéro de la première ligne de sa déclaration, sans le commentaire ni le texte entre guillemets'
		);

	}

	public function testTransition(): void {

		$rows = MotionLib::parse(self::CSS, 'test.css');

		$this->assertEquals(['transition', 'opacity', '0.15', '', 'ease-out', 'composite', 'survol', 1], $this->extract($rows[0]));
		$this->assertEquals(['transition', 'transform', '0.3', '0.1', 'cubic-bezier(0.2, 0.8, 0.3, 1.15)', 'composite', 'survol', 1], $this->extract($rows[1]));
		$this->assertEquals(['transition', '', '0.2', '', '', '', 'non classé', 1], $this->extract($rows[2]));
		$this->assertEquals(['transition', 'left', '0.25', '', '', 'mise en page', 'non classé', 1], $this->extract($rows[3]));
		$this->assertEquals(['transition', 'height', '0.5', '', 'linear', 'mise en page', 'non classé', 1], $this->extract($rows[4]));
		$this->assertEquals(['transition', 'background-color', 'var(--duration-sm)', '', 'var(--ease-out)', 'peinture', 'non classé', 0], $this->extract($rows[5]), 'une variable n\'est pas une valeur littérale');
		$this->assertEquals(['transition', 'none', '', '', '', '', 'sans mouvement', 0], $this->extract($rows[8]));
		$this->assertEquals(['transition', 'opacity', '', '', '', 'composite', 'non classé', 0], $this->extract($rows[9]), 'une variable qu\'on ne sait pas placer ne prend pas la place de la propriété');

	}

	public function testAnimation(): void {

		$rows = MotionLib::parse(self::CSS, 'test.css');

		$this->assertEquals(['animation', 'transform,opacity', '1', '', 'linear', 'composite', 'boucle', 1], $this->extract($rows[6]), 'l\'animation prend les propriétés de ses images clés');
		$this->assertEquals('spin', $rows[6]['name']);
		$this->assertEquals('infinite', $rows[6]['iteration']);

		$this->assertEquals(['keyframes', 'transform,opacity', '', '', '', 'composite', '', 0], $this->extract($rows[7]));
		$this->assertEquals('spin', $rows[7]['selector']);

	}

	public function testUnique(): void {

		$keys = array_map(fn($row) => $row['line'].':'.$row['segment'], MotionLib::parse(self::CSS, 'test.css'));

		$this->assertEquals([], array_keys(array_filter(array_count_values($keys), fn($count) => $count > 1)), 'aucune ligne comptée deux fois');

	}

	public function testCalc(): void {

		$css = <<<'CSS'
			.a { animation-delay: calc(0.1s * 2); }
			.b { animation-delay: calc(var(--duration-step) * 2); }
			.c { transition: opacity var(--duration-sm) calc(100ms * 3); }
			.d { animation: slide calc(var(--duration-step) * 4) var(--ease-out); }
			.e { animation-delay: calc(var(--duration-step, 0.2s) * 2); }
			.f { animation-delay: calc(var(--index) * -0.1s); }
			.g { animation-delay: calc(var(--d-2s, var(--x)) * 2); }
			CSS;

		$rows = MotionLib::parse($css, 'test.css');

		$this->assertEquals(
			['calc(0.1s * 2):1', 'calc(var(--duration-step) * 2):0', 'opacity:calc(100ms * 3):1', 'slide:calc(var(--duration-step) * 4):0', 0, 1, 0],
			[
				$rows[0]['delay'].':'.$rows[0]['literal'],
				$rows[1]['delay'].':'.$rows[1]['literal'],
				$rows[2]['property'].':'.$rows[2]['delay'].':'.$rows[2]['literal'],
				$rows[3]['name'].':'.$rows[3]['duration'].':'.$rows[3]['literal'],
				$rows[4]['literal'],
				$rows[5]['literal'],
				$rows[6]['literal'],
			],
			'un temps écrit en dur dans un calc est littéral, un calc de tokens ne l\'est pas, même avec un repli en dur dans son var(), un temps négatif l\'est, le reste d\'un var() imbriqué ne l\'est pas, et un calc de raccourci se lit comme un temps'
		);

	}

	public function testMedia(): void {

		$css = <<<'CSS'
			.a { transition: opacity 0.2s; }
			@supports (display: grid) {
				@media (prefers-reduced-motion: reduce) {
					.a { transition-duration: 0.01ms; }
				}
			}
			@media (max-width: 575px) {
				@keyframes pulse {
					to { opacity: 0; }
				}
			}
			CSS;

		$rows = MotionLib::parse($css, 'test.css');

		$this->assertEquals(
			['1::0.2', '4:@supports (display: grid) · @media (prefers-reduced-motion: reduce):0.00001', '8:@media (max-width: 575px):'],
			array_map(fn($row) => $row['line'].':'.$row['media'].':'.$row['duration'], $rows),
			'les règles conditionnelles de la plus extérieure à la plus intérieure, et 0.01ms lu sans l\'arrondir à zéro'
		);

	}

	public function testScript(): void {

		$js = <<<'JS'
			setTimeout(() => save(), 1000);
			const a = 1;
			const b = 2;
			const c = 3;
			element.style.transition = 'all 0.5s';
			element.animate([{opacity: 0}], {duration: 300});
			setTimeout(() => element.classList.remove('fade'), 400);
			const d = 4;
			const e = 5;
			const f = 6;
			requestAnimationFrame(() => {});
			setTimeout(() => save(), 50);
			JS;

		$this->assertEquals(
			['5:style:', '6:animate:', '6:duration:', '7:timeout:0.4'],
			array_map(fn($row) => $row['line'].':'.$row['declaration'].':'.$row['duration'], MotionLib::parseScript($js, 'test.js')),
			'un délai ne compte que près d\'un mot de mouvement'
		);

		$php = <<<'PHP'
			$h = '<div style="opacity: 0; transition: opacity 0.2s">';
			$h = '<div style="color: red">';
			PHP;

		$this->assertEquals(
			['1:style:0.2'],
			array_map(fn($row) => $row['line'].':'.$row['declaration'].':'.$row['duration'], MotionLib::parseScript($php, 'test.php'))
		);

	}

	private function extract(array $row): array {
		return [$row['kind'], $row['property'], $row['duration'], $row['delay'], $row['curve'], $row['cost'], $row['gesture'], $row['literal']];
	}

}
?>
