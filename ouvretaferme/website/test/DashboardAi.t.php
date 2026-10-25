<?php
namespace website;

/**
 * Les nouvelles du blog de la page « Du nouveau » (L40, L50) : ce que la contribution `website-news` garde d'un
 * article, et quand la dernière perd sa carte.
 *
 * Les articles sont construits à la main, sans base : `DashboardLib::getNews()` ne lit que ce qu'on lui
 * passe, et le blog réel change chaque semaine, ce qui rendrait tout attendu daté. L'horloge est figée
 * pour la même raison, l'âge de la carte se comptant depuis maintenant.
 *
 * Lancement : dev/test package=website file=DashboardAi
 */
class DashboardAiTest extends \Test {

	private const NOW = '2026-10-12 10:00:00';

	private const CONTENT = '<main><header size="2">Un en-tête</header><p> </p><p>Le chapeau de l\'article.</p><figure><image xyz="abcdefghijklmnopqrst" xyz-version="3" width="1200" height="800"/></figure><p>La suite.</p></main>';

	public function testOnlyTheLatestNewsHasACard(): void {

		$this->setClock(self::NOW);

		$news = DashboardLib::getNews($this->getCollection([
			558 => '2026-10-11 14:00:00',
			555 => '2026-10-10 15:35:00',
			550 => '2026-10-09 23:00:00'
		]));

		$this->assertEquals([558, 555, 550], array_column($news, 'id'));
		$this->assertEquals([TRUE, FALSE, FALSE], array_column($news, 'card'));

		// Seule la carte lit le contenu : les lignes n'en montrent rien.
		$this->assertEquals('abcdefghijklmnopqrst', $news[0]['xyz']);
		$this->assertEquals(3, $news[0]['xyzVersion']);
		$this->assertEquals("Le chapeau de l'article.", $news[0]['intro']);
		$this->assertFalse(array_key_exists('xyz', $news[1]));

	}

	/** La carte vit quatorze jours pleins, et pas un de plus. */
	public function testTheCardFadesAfterFourteenDays(): void {

		$this->setClock(self::NOW);

		$news = DashboardLib::getNews($this->getCollection([1 => '2026-09-28 10:00:00']));
		$this->assertTrue($news[0]['card']);

		$news = DashboardLib::getNews($this->getCollection([1 => '2026-09-27 10:00:00']));
		$this->assertFalse($news[0]['card']);

	}

	public function testNoNewsMeansNoBand(): void {

		$this->setClock(self::NOW);

		$this->assertEquals(NULL, DashboardLib::getNews(new \Collection()));

	}

	/**
	 * Un article sans image ni texte garde sa carte sans rien y mettre, et un contenu illisible ne fait
	 * pas tomber l'accueil.
	 */
	public function testCoverMayBeMissing(): void {

		$empty = ['xyz' => NULL, 'xyzVersion' => NULL, 'intro' => NULL];

		$this->assertEquals($empty, DashboardLib::getCover('<main><p></p></main>'));
		$this->assertEquals($empty, DashboardLib::getCover('<main><p>non fermé</main>'));

		// Une figure sans média de l'éditeur ne compte pas : c'est la suivante qui donne l'image.
		$cover = DashboardLib::getCover('<main><figure><image url="/photo/a.jpg"/></figure><figure><image xyz="zyxwvutsrqponmlkjihg" xyz-version="1"/></figure></main>');
		$this->assertEquals('zyxwvutsrqponmlkjihg', $cover['xyz']);
		$this->assertEquals(NULL, $cover['intro']);

	}

	/** Seuls des scalaires partent au cache de l'accueil. */
	public function testNewsAreScalars(): void {

		$this->setClock(self::NOW);

		$news = DashboardLib::getNews($this->getCollection([1 => '2026-10-11 14:00:00', 2 => '2026-10-01 14:00:00']));

		array_walk_recursive($news, fn($value) => $this->assertFalse(is_object($value)));

	}

	private function getCollection(array $dates): \Collection {

		$cNews = new \Collection();

		foreach($dates as $id => $publishedAt) {
			$cNews[$id] = new News([
				'id' => $id,
				'title' => 'Article '.$id,
				'content' => self::CONTENT,
				'publishedAt' => $publishedAt
			]);
		}

		return $cNews;

	}

}
?>
