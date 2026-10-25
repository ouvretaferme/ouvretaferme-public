<?php
namespace util;

class PaginationTest extends \Test {

	/**
	 * Les pages proposées autour de la page courante, flèches de début et de fin comprises, l'adresse de chacune et le
	 * champ « Aller à » que lit `Lime.Pagination.form()`.
	 */
	public function testWithoutAjax() {

		// Page courante, nombre de pages, pages des liens dans l'ordre, flèches comprises
		$renders = [
			[0, 3.0, [0, 1, 2, 2]],
			[4, 20.5, [0, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 20]],
			[2, 3.0, [0, 0, 1, 2]],
			[19, 20.0, [0, 14, 15, 16, 17, 18, 19]],
		];

		$url = HttpUi::setArgument(LIME_REQUEST, 'page', '{page}', FALSE);

		foreach($renders as [$page, $nPage, $pages]) {

			$h = TextUi::pagination($page, $nPage);

			preg_match_all('#href="([^"]*)" data-page="(\d+)"#', $h, $links, PREG_SET_ORDER);

			$this->assertEquals($pages, array_map('intval', array_column($links, 2)), 'pages proposées, page '.$page.' sur '.$nPage);

			foreach($links as [, $href, $linkPage]) {
				$this->assertEquals(str_replace('{page}', $linkPage, $url), $href, 'adresse de la page '.$linkPage);
			}

			$this->assertTrue(str_contains($h, 'onsubmit="'.encode("Lime.Pagination.form('".$url."', this);").'"'), 'le champ « Aller à » envoie à la pagination, page '.$page.' sur '.$nPage);
			$this->assertTrue(preg_match('#<input [^>]*max="'.(int)ceil($nPage).'"[^>]*name="page"#', $h) === 1, 'le champ « Aller à » s\'arrête à la dernière page, page '.$page.' sur '.$nPage);

		}

		$this->assertEquals('', TextUi::pagination(0, 1.0), 'une seule page, pas de pagination');

	}

	public function testAjax() {

		$url = '/liste?filtre=1';

		$h = TextUi::pagination(4, 20.5, $url, 'Ajax.Query');

		preg_match_all('#href="([^"]*)" data-ajax="([^"]*)" data-ajax-method="get" data-ajax-class="Ajax\.Query" data-page="(\d+)"#', $h, $links, PREG_SET_ORDER);

		$this->assertEquals(substr_count($h, 'data-page="'), count($links));
		$this->assertTrue($links !== [], 'les liens de pages sont lus');

		foreach($links as [, $href, $ajax, $page]) {
			$this->assertEquals($url.'&page='.$page, $href);
			$this->assertEquals($href, $ajax);
		}

		$this->assertTrue(str_contains($h, encode("Lime.Pagination.form('".$url."&page={page}', this, 'Ajax.Query');")));
		$this->assertFalse(str_contains($h, LIME_REQUEST));

		$plain = preg_replace('# data-ajax="[^"]*" data-ajax-method="get" data-ajax-class="Ajax\.Query"#', '', $h);
		$plain = str_replace(encode(", 'Ajax.Query'"), '', $plain);

		$this->assertEquals($this->neutralize(TextUi::pagination(4, 20.5, $url)), $this->neutralize($plain));

	}

	private function neutralize(string $h): string {
		return preg_replace(['#id="field-[a-z0-9]+"#', '# [a-z]+-label="[^"]*"#'], ['id="field"', ' {label}'], $h);
	}

}
?>
