<?php
namespace website;

/**
 * Ce que les nouvelles du blog montrent sur la page « Du nouveau » du groupe Bureau : la dernière
 * en carte tant qu'elle est neuve, puis les précédentes en lignes. La page, son titre et le geste de
 * l'en-tête sont au socle (`base/dashboard/ui/Dashboard.u.php`, `getNews()`).
 */
class DashboardUi {

	public function getTitle(string $name, ?array $values = NULL): string {

		return match($name) {
			'website-news' => s("Du nouveau sur {siteName}")
		};

	}

	public function getUrlLabel(string $name): string {

		return match($name) {
			'website-news' => s("Toutes les actualités")
		};

	}

	public function render(string $name, \structure\Structure $eStructure, array $values): string {

		return match($name) {
			'website-news' => $this->getNews($values)
		};

	}

	/**
	 * Une nouvelle sans image garde sa carte, réduite au titre, à la date et au chapeau : un visuel de
	 * remplacement répété d'un article à l'autre ne dirait rien de celui-ci.
	 */
	private function getNews(array $news): string {

		$h = '';
		$rows = [];

		foreach($news as $item) {

			// Seule dans une grille de cartes, la carte garde la largeur d'une carte sur une page qui
			// prend celle de l'écran. Les lignes qui la suivent prennent toute la largeur.
			if($item['card']) {
				$h .= '<div class="util-cards util-cards-fill">'.$this->getCard($item).'</div>';
			} else {
				$rows[] = $item;
			}

		}

		if($rows !== []) {

			$h .= '<div class="util-rows">';

				foreach($rows as $item) {

					$h .= '<div class="util-row">';
						$h .= '<div>'.$this->getLink($item).'</div>';
						$h .= '<div class="color-muted">'.\util\DateUi::textual($item['publishedAt'], \util\DateUi::DATE).'</div>';
					$h .= '</div>';

				}

			$h .= '</div>';

		}

		return $h;

	}

	private function getCard(array $item): string {

		$h = '<div class="util-card">';

			if($item['xyz'] !== NULL) {

				$h .= '<a href="'.self::getUrl($item['id']).'" target="_blank" class="util-card-bleed dashboard-news-cover">';
					$h .= '<img src="'.encode(new \media\EditorUi()->getUrlByHash($item['xyz'], 'm', $item['xyzVersion'])).'" alt="" loading="lazy"/>';
				$h .= '</a>';

			}

			$h .= '<div class="util-card-title">'.$this->getLink($item).'</div>';
			$h .= '<div class="util-card-info">'.\util\DateUi::textual($item['publishedAt'], \util\DateUi::DATE).'</div>';

			if($item['intro'] !== NULL) {
				$h .= '<p class="util-text-clamp mt-1 mb-0">'.encode($item['intro']).'</p>';
			}

		$h .= '</div>';

		return $h;

	}

	private function getLink(array $item): string {
		return '<a href="'.self::getUrl($item['id']).'" target="_blank">'.encode($item['title']).'</a>';
	}

	private static function getUrl(int $id): string {
		return WebsiteSetting::BLOG_URL.'#news-'.$id;
	}

}
?>
