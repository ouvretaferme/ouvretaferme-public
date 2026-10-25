<?php
namespace editor;

class EditorSetting extends \Settings {

	const CAPTION_LIMIT = 130;

	const MEDIA_TITLE_LIMIT = 80;

	// Nombre de niveaux d'imbrication autorisés dans une liste
	const LIST_DEPTH_LIMIT = 3;

	const QUOTE_BORDER = ['solid-grey', 'solid-primary', 'solid-text', 'dashed-grey', 'dashed-primary', 'dashed-text'];

	const TEXT_SIZE = ['small', 'large'];

	/**
	 * Les hébergeurs dont une vidéo s'insère, seule liste du serveur et du script de l'éditeur.
	 *
	 * Le motif lit l'adresse que colle le rédacteur et aussi l'adresse d'embed déjà enregistrée, qui
	 * se relit à chaque nouvel enregistrement. Son seul groupe capturant est l'identifiant de la
	 * vidéo, et lui seul passe dans le gabarit : le schéma et l'hôte de l'iframe sont toujours ceux du
	 * gabarit, d'où une adresse collée en http admise sans risque.
	 * Ce groupe n'est jamais vide et ne suit que les chemins qui désignent une vidéo, parce qu'un
	 * lecteur posé sur une adresse d'accueil, de chaîne ou de playlist n'affiche qu'une erreur.
	 * `videoseries` et `live_stream` sont des lecteurs YouTube de playlist et de chaîne, qu'aucun
	 * identifiant ne suit, d'où leur exclusion à la place de l'identifiant, quel que soit le chemin
	 * qui y mène. Ce qui suit celui-ci, barre finale comprise, ne passe jamais dans le gabarit et
	 * peut donc être admis.
	 * Le motif est lu par PCRE et par `RegExp`, d'où le sous-ensemble commun (ni groupe nommé, ni
	 * lookbehind ; le lookahead est commun aux deux).
	 *
	 * Le gabarit demande à l'hébergeur le suivi réduit qu'il documente : le domaine sans cookie de
	 * YouTube, `dnt=1` de Vimeo. Le motif de YouTube lit aussi le domaine sans cookie, que porte
	 * désormais l'adresse enregistrée. `autoplay` est le paramètre de démarrage de l'hébergeur, que
	 * chaque entrée déclare : un hébergeur dont le lecteur n'en reçoit aucun n'entre pas dans la liste
	 * sans que `getVideoUrl()` apprenne à s'en passer. `start` fait partir la vidéo d'un instant, en
	 * secondes, à la place de `{s}` : un paramètre de la requête chez YouTube, un fragment chez Vimeo,
	 * qui le lit après `#` (`getVideoUrl()`). Un hébergeur sans départ à l'instant n'entre pas non plus.
	 *
	 * Un hébergeur qui sort de la liste emporte ses vidéos déjà enregistrées : le rendu ne pose plus
	 * leur iframe, et le prochain enregistrement du texte les retire. Dailymotion en est sorti le
	 * 10/10/2026 (décidé), son lecteur ne se laissant plus intégrer sans compte.
	 */
	const VIDEO = [
		'youtube' => [
			'name' => 'YouTube',
			'pattern' => '^https?://(?:(?:www\.)?youtube(?:-nocookie)?\.com/(?:(?:embed|v|shorts|live)/|watch\?(?:[^#&\s]*&)*v=)|youtu\.be/)(?!(?:videoseries|live_stream)(?:[/?&#]|$))([a-zA-Z0-9_-]+)(?:[/?&#][^\s\[\\\\]*)?$',
			'embed' => 'https://www.youtube-nocookie.com/embed/{id}?rel=0',
			'autoplay' => 'autoplay=1',
			'start' => 'start={s}',
		],
		'vimeo' => [
			'name' => 'Vimeo',
			'pattern' => '^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?([0-9]+)',
			'embed' => 'https://player.vimeo.com/video/{id}?dnt=1',
			'autoplay' => 'autoplay=1',
			'start' => '#t={s}s',
		],
	];

	/**
	 * L'adresse d'embed d'une vidéo, recalculée depuis la liste des hébergeurs, ou NULL si la source
	 * n'y est pas ou si l'adresse ne répond pas à son motif. `$autoplay` sert à une iframe posée au
	 * clic de l'utilisateur : l'éditeur n'en passe jamais, puisqu'une page qui porte plusieurs
	 * vidéos les démarrerait toutes à son ouverture.
	 *
	 * `$start` rend un gabarit et non une adresse : `{s}` y attend l'instant, que le script remplace
	 * par un entier au clic sur l'instant d'une étape (`BlockVideo.jump()`, `read.js`). Un fragment se
	 * pose après toute la requête, démarrage compris, faute de quoi il couperait la requête.
	 */
	public static function getVideoUrl(string $url, string $source, bool $autoplay = FALSE, bool $start = FALSE): ?string {

		if(array_key_exists($source, self::VIDEO) === FALSE) {
			return NULL;
		}

		$video = self::VIDEO[$source];

		if(preg_match('~'.$video['pattern'].'~D', $url, $match) !== 1) {
			return NULL;
		}

		$embed = str_replace('{id}', $match[1], $video['embed']);

		if($autoplay) {
			$embed .= (str_contains($embed, '?') ? '&' : '?').$video['autoplay'];
		}

		if($start) {

			if(str_starts_with($video['start'], '#')) {
				$embed .= $video['start'];
			} else {
				$embed .= (str_contains($embed, '?') ? '&' : '?').$video['start'];
			}

		}

		return $embed;

	}

}
?>
