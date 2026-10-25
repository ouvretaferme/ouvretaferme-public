<?php
namespace website;

/**
 * Ce que le package `website` apporte à l'accueil : les dernières nouvelles du blog de Ouvretaferme
 * (L40, piste D retenue le 2026-10-12, le reste arbitré par V-Dashboard), que l'entrée
 * « Du nouveau » du groupe Bureau ouvre sur sa page (L50).
 *
 * Elles parlent du produit et non de la structure : elles occupent l'emplacement `NEWS`, hors de tout
 * univers, et se lisent en base commune seulement, sur le site de la structure du blog
 * (`WebsiteSetting::BLOG_STRUCTURE`).
 */
class DashboardLib {

	/**
	 * Combien de nouvelles la page montre : la dernière, puis les deux précédentes.
	 */
	const int NEWS_MAX = 3;

	/**
	 * Combien de jours la dernière nouvelle garde sa carte. Passé ce délai, elle se range en ligne comme
	 * les autres : l'âge dit ce qui est neuf sans rien écrire par membre à la lecture.
	 */
	const int CARD_DAYS = 14;

	public static function getBlocks(): array {
		return [];
	}

	/**
	 * `canRead` est le défaut : les nouvelles ne disent rien de la structure, tout membre les voit,
	 * gestionnaire ou non. Une heure de cache : le blog publie environ une nouvelle par semaine.
	 */
	public static function getContributions(): array {

		return [
			'website-news' => [
				'slot' => \dashboard\DashboardSetting::NEWS,
				'cache' => 3600
			]
		];

	}

	public static function load(string $name, \structure\Structure $eStructure, ?string $focus = NULL): ?array {

		return match($name) {
			'website-news' => self::getNews(NewsLib::getLastForBlog(self::NEWS_MAX))
		};

	}

	public static function getUrl(string $name, \structure\Structure $eStructure, ?array $data): string {

		return match($name) {
			'website-news' => WebsiteSetting::BLOG_URL
		};

	}

	/**
	 * Les nouvelles rendues en scalaires, ou `NULL` quand le blog n'en a publié aucune : l'emplacement est
	 * alors absent, plutôt qu'un cadre vide.
	 *
	 * Seule la première peut porter une carte, et seulement dans ses `CARD_DAYS` premiers jours.
	 */
	public static function getNews(\Collection $cNews): ?array {

		if($cNews->empty()) {
			return NULL;
		}

		$limit = date('Y-m-d H:i:s', \Clock::shift('-'.self::CARD_DAYS.' days'));

		$news = [];

		foreach($cNews as $eNews) {

			$item = [
				'id' => $eNews['id'],
				'title' => $eNews['title'],
				'publishedAt' => $eNews['publishedAt'],
				'card' => ($news === [] and $eNews['publishedAt'] >= $limit)
			];

			if($item['card']) {
				$item += self::getCover($eNews['content']);
			}

			$news[] = $item;

		}

		return $news;

	}

	/**
	 * L'image et le chapeau d'une nouvelle, lus dans son contenu : l'illustration d'un article est celle
	 * que son auteur a posée en l'écrivant, et une image saisie à part pourrait la contredire. L'image est la première `<image>` d'une
	 * `<figure>` qui porte un `xyz`, donc un média de l'éditeur, et le chapeau le premier paragraphe qui
	 * porte du texte. L'un comme l'autre peut manquer.
	 *
	 * La lecture vit ici et non dans `editor` : elle ne sert qu'au blog, et `XmlLib::extractImages()`
	 * attend des adresses `/photo/` qu'un média de l'éditeur ne porte pas.
	 *
	 * @return array ['xyz' => ?string, 'xyzVersion' => ?int, 'intro' => ?string]
	 */
	public static function getCover(string $content): array {

		$cover = [
			'xyz' => NULL,
			'xyzVersion' => NULL,
			'intro' => NULL
		];

		// Un contenu que l'éditeur n'a pas su écrire ne doit pas faire tomber l'accueil : il n'a ni image
		// ni chapeau, comme `XmlLib::extractImages()` le traite. Le réglage de libxml vaut pour tout le
		// processus, d'où sa restauration aussitôt la lecture faite.
		$errors = libxml_use_internal_errors(TRUE);

		$dom = new \DOMDocument();
		$loaded = $dom->loadXML($content, LIBXML_NOERROR | LIBXML_NOBLANKS);

		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		if($loaded === FALSE) {
			return $cover;
		}

		foreach($dom->documentElement->childNodes as $node) {

			if(
				$cover['xyz'] === NULL and
				$node->nodeName === 'figure'
			) {

				foreach($node->childNodes as $mediaNode) {

					if(
						$mediaNode->nodeName === 'image' and
						$mediaNode->getAttribute('xyz') !== ''
					) {
						$cover['xyz'] = $mediaNode->getAttribute('xyz');
						$cover['xyzVersion'] = (int)$mediaNode->getAttribute('xyz-version');
						break;
					}

				}

			}

			if(
				$cover['intro'] === NULL and
				$node->nodeName === 'p' and
				trim($node->textContent) !== ''
			) {
				$cover['intro'] = trim($node->textContent);
			}

		}

		return $cover;

	}

}
?>
