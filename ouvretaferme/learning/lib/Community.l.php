<?php
namespace learning;

/**
 * Les réglages que la formation demande au réseau, rangés sur `structure\Community` avec ceux des
 * relevés de prix, et écrits sur le modèle de `quotation\CommunityLib::updateSettings()`.
 */
class CommunityLib {

	use \Notifiable;

	/**
	 * Le fond des pages publiques sans réglage, `--background-light` que `template-base.css` pose sur
	 * le gabarit `base` : c'est sur lui que se juge la couleur en texte tant que le réseau n'a pas
	 * choisi de fond.
	 */
	const BACKGROUND_DEFAULT = '#FAFAFA';

	/**
	 * La part de blanc mêlée au fond choisi dans « Arrière-plan » avant qu'il ne se pose sur les pages
	 * de formations : les teintes de la palette (`\main\CustomizeUi::CUSTOM_BACKGROUNDS`), surtout
	 * celles de la seconde rangée, sont faites pour une boutique ou un site et couvriraient toute la page
	 * d'une couleur trop soutenue (retour de Vincent, lot A3m). La pastille de la barre garde la teinte
	 * choisie : seul le fond posé est adouci, et les encres se calculent sur lui (`getSettings()`).
	 * Le fond du produit, quand le réseau n'en a pas choisi, n'est pas une teinte et reste tel quel.
	 */
	const BACKGROUND_SOFTENING = 0.5;

	/**
	 * La couleur sur laquelle un gabarit calcule ses encres quand le réseau n'en a pas choisi :
	 * `--primary` du produit (`reboot.css`), que la page garde alors.
	 */
	const COLOR_DEFAULT = '#373737';

	/**
	 * La part de la couleur dans la teinte des bandeaux et des aplats d'un gabarit, posée sur le fond
	 * adouci (`getTint()`) : assez pour que la couleur se reconnaisse, assez peu pour que le bandeau
	 * reste clair sous l'encre du texte, même sous le noir.
	 */
	const TINT_PART = 0.18;

	/**
	 * La saturation, `(max − min) / max` des trois canaux, au-dessous de laquelle une couleur de réseau
	 * trop claire pour le texte garde l'encre sombre du produit plutôt que d'être foncée
	 * (`getTextInkDarkened()`). Foncer rapproche chaque canal du noir dans la même proportion et garde
	 * donc cette saturation : une couleur sans teinte, blanc ou gris, devient un gris moyen qui ne
	 * rappelle plus le réseau et se confond avec le gris des annotations, là où l'encre sombre s'en
	 * sépare. À 0,3, quelle que soit la teinte, l'encre foncée reste à un écart CIELAB (ΔE76) de 9,7
	 * au moins de `\structure\Community::INK_MUTED`, et de 8,8 de `INK_MUTED_DARK` sur un fond
	 * choisi ; à 0,2, un bleu en est à 6 environ, et l'œil ne le sépare plus du gris bleuté des
	 * annotations.
	 */
	const TEXT_INK_SATURATION = 0.3;

	/**
	 * L'inscription à une date, puis à une formation en ligne, est-elle acquise tout de suite, ou
	 * attend-elle que le réseau la confirme ? Ne vaut que pour les inscriptions suivantes : les
	 * demandes en cours restent à confirmer, et le réseau les confirme une à une depuis leur liste.
	 */
	public static function updateEnrollment(\structure\Structure $eStructureCommunity, array $input): void {

		$eCommunity = \structure\CommunityLib::getByStructure($eStructureCommunity);

		$fw = new \FailWatch();

		// La liste est explicite : une colonne absente d'ici n'est jamais construite, et le
		// panneau n'en poste pas d'autre.
		$properties = ['learningConfirm', 'learningOnlineConfirm', 'learningConditions'];

		$eCommunity->build($properties, $input);

		$fw->validate();

		\structure\CommunityLib::update($eCommunity, $properties);

	}

	/**
	 * Vrai quand le réseau a un compte de reversement actif, sur lequel arrive ce que ses stagiaires
	 * paient : l'offre « Payée par le stagiaire » ne se choisit pas avant (`Course::offer.account`).
	 * Le compte vit dans `payment`, que l'export public retire : sans lui, aucun réseau n'en a.
	 */
	public static function hasPayoutAccount(\structure\Structure $eStructureCommunity): bool {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return FALSE;
		}

		return self::notify('hasPayoutAccount', $eStructureCommunity)[0];

	}

	/**
	 * Le réseau a-t-il des pages publiques, sur lesquelles son thème se pose et se règle : ses
	 * formations, ou ses relevés de prix. C'est la seule règle de la carte « Personnaliser
	 * l'apparence » du paramétrage et des routes de la barre ; un réseau qui ne tient que ses relevés
	 * règle donc son apparence au même endroit qu'un réseau qui forme.
	 */
	public static function hasPublic(\structure\Structure $eStructure): bool {

		return (
			$eStructure->isNetwork() and
			(
				$eStructure->acceptLearning() or
				($eStructure->acceptGroupQuotationSetting() and $eStructure->acceptGroupQuotation())
			)
		);

	}

	/**
	 * La fiche sur laquelle la carte « Personnaliser l'apparence » ouvre la barre, pour le seul réseau
	 * des tutoriels : son accueil et sa liste de formations n'existent pas pour le public
	 * (`public.p.php`), et le sommaire `/tutoriels` ne porte pas le thème du réseau. La fiche d'un
	 * tutoriel le porte, avec la barre, et son essai y ramène (`/formation/{id}/:doPreview`). C'est
	 * le premier tutoriel du sommaire. Un autre réseau ouvre la barre sur son accueil : rien ici.
	 */
	public static function getCustomizeCourse(\structure\Structure $eStructureCommunity): Course {

		if($eStructureCommunity['id'] !== LearningSetting::$structureTutorial) {
			return new Course();
		}

		$cCourse = CourseLib::getPublicOnlineByCommunity($eStructureCommunity);

		return $cCourse->empty() ? new Course() : $cCourse->first();

	}

	/**
	 * La carte « Personnaliser l'apparence » paraît-elle : le réseau a des pages publiques, et une
	 * page où la barre s'ouvre. Le réseau des tutoriels n'en a qu'une sorte, la fiche d'un tutoriel
	 * publié (`getCustomizeCourse()`) : sans tutoriel, la barre ne s'ouvrirait que sur un 404.
	 */
	public static function hasCustomize(\structure\Structure $eStructureCommunity, Course $eCourseCustomize): bool {

		if(self::hasPublic($eStructureCommunity) === FALSE) {
			return FALSE;
		}

		return (
			$eStructureCommunity['id'] !== LearningSetting::$structureTutorial or
			$eCourseCustomize->notEmpty()
		);

	}

	/**
	 * Le gabarit, la couleur, le fond et les polices des pages publiques du réseau, réglés depuis la
	 * barre de l'une d'elles. La couleur est celle du groupe : la barre la change aussi pour le
	 * bandeau de l'espace membres. Un réglage refusé fait échouer l'ensemble, comme la barre de la
	 * boutique.
	 */
	public static function updateCustomize(\structure\Structure $eStructureCommunity, array $input): void {

		$eCommunity = \structure\CommunityLib::getByStructure($eStructureCommunity);

		$fw = new \FailWatch();

		$properties = self::getPropertiesCustomize();

		$eCommunity->build($properties, $input);

		$fw->validate();

		\structure\CommunityLib::update($eCommunity, $properties);

	}

	public static function getPropertiesCustomize(): array {
		return ['customDesign', 'color', 'customBackground', 'customTitleFont', 'customFont'];
	}

	/**
	 * Les réglages essayés dans la barre, contrôlés comme à l'enregistrement avant d'être montrés : un
	 * fond ou une couleur refusés remontent par la chaîne d'erreur, et l'aperçu ne montre jamais ce
	 * qui ne pourra pas être gardé. Seuls les réglages présents dans `$input` sont lus. Le contrôle se
	 * fait sur une copie qui porte la couleur enregistrée, que `Community::color.contrast` laisse passer.
	 */
	public static function checkCustomize(\structure\Community $eCommunity, array $input, ?\Closure $onKo = NULL): array {

		$fw = new \FailWatch();

		$properties = array_values(array_intersect(self::getPropertiesCustomize(), array_keys($input)));

		$eCommunityPreview = new \structure\Community($eCommunity->extracts(['color']));
		$eCommunityPreview->build($properties, $input);

		$fw->validate(onKo: $onKo);

		return $eCommunityPreview->extracts($properties);

	}

	/**
	 * Les réglages essayés portés par l'adresse d'une page en mode personnalisation, posés sur le
	 * réseau en mémoire sans rien écrire : la page les montre comme s'ils étaient enregistrés. Le droit
	 * est jugé par la page avant l'appel. Une valeur qui n'est pas du texte compte comme absente.
	 */
	public static function applyPreview(\structure\Community $eCommunity, array $input, \Closure $onKo): void {

		$input = array_filter($input, 'is_string');

		$eCommunity->merge(self::checkCustomize($eCommunity, $input, $onKo));

	}

	/**
	 * Les réglages tels que le thème les pose : le fond choisi, que les pastilles montrent, et le fond
	 * posé sur la page (`backgroundSoftened`, `getBackgroundSoftened()`), sur lequel se calculent
	 * toutes les encres ; l'encre sur la couleur, la couleur quand elle sert de texte, foncée jusqu'à
	 * se lire sur le fond et sur le blanc des cartes (`getTextInkDarkened()`), et le gris des
	 * annotations sur un fond choisi, assombri là où celui du produit ne tient pas 4,5:1. Sous
	 * `input`, les réglages que la barre rejoue à l'enregistrement.
	 *
	 * Un gabarit lit ces encres sans repli : dès qu'il est choisi, `inks` les donne pour chacune de
	 * ses deux portées, calculées sur les couleurs qu'il fixe sous le texte (`DESIGN_MATTERS`), sur la
	 * couleur du produit quand le réseau n'en a pas.
	 *
	 * Sous `fonts`, les deux polices que la page pose : celle que le réseau a choisie, sinon celle du
	 * gabarit (`LearningSetting::DESIGN_FONTS`), que `designFonts` rend seule pour que la barre la
	 * nomme dans son option vide.
	 */
	public static function getSettings(\structure\Community $eCommunity): array {

		$color = $eCommunity['color'];
		$background = $eCommunity['customBackground'];
		$backgroundSoftened = ($background === NULL) ? self::BACKGROUND_DEFAULT : self::getBackgroundSoftened($background);
		$design = $eCommunity['customDesign'];
		$designFonts = LearningSetting::DESIGN_FONTS[$design] ?? ['title' => NULL, 'text' => NULL];

		$input = $eCommunity->extracts(self::getPropertiesCustomize());

		$inks = [];

		if($design !== NULL) {

			foreach(['public', 'doc'] as $scope) {
				$inks[$scope] = self::getInks($color ?? self::COLOR_DEFAULT, $backgroundSoftened, LearningSetting::DESIGN_MATTERS[$design][$scope] ?? NULL);
			}

		}

		// Le blanc des cartes se passe en clair : `getTextInk()` ne juge que les fonds qu'on lui donne,
		// et la matière d'une portée (`DESIGN_MATTERS`) peut ne pas contenir le blanc.
		return $input + [
			'input' => $input,
			'background' => $background ?? self::BACKGROUND_DEFAULT,
			'backgroundSoftened' => $backgroundSoftened,
			'ink' => ($color === NULL) ? NULL : \structure\Community::getInk($color),
			'textInk' => ($color === NULL) ? NULL : self::getTextInkDarkened($color, $backgroundSoftened, \structure\Community::INK_LIGHT),
			'whiteInk' => ($color === NULL) ? NULL : \structure\Community::getTextInk($color, \structure\Community::INK_LIGHT),
			'mutedInk' => ($background === NULL) ? NULL : \structure\Community::getMutedInk($backgroundSoftened),
			'inks' => $inks,
			'designFonts' => $designFonts,
			'fonts' => [
				'title' => $eCommunity['customTitleFont'] ?? $designFonts['title'],
				'text' => $eCommunity['customFont'] ?? $designFonts['text'],
			],
			'tint' => ($design === NULL) ? NULL : self::getTint($color ?? self::COLOR_DEFAULT, $backgroundSoftened)
		];

	}

	/**
	 * Le fond choisi tel qu'il se pose sur la page, mêlé de `BACKGROUND_SOFTENING` de blanc.
	 */
	public static function getBackgroundSoftened(string $background): string {
		return self::mix($background, '#ffffff', self::BACKGROUND_SOFTENING);
	}

	/**
	 * Sans matière déclarée, le texte se pose sur le fond et sur le blanc des cartes, comme sans
	 * gabarit ; avec une matière, sur chacune de ses couleurs, `{background}` valant le fond posé,
	 * c'est-à-dire le fond choisi adouci.
	 */
	private static function getInks(string $color, string $background, ?array $matters): array {

		if($matters === NULL) {
			// Le blanc des cartes en clair, comme sans gabarit : `getTextInk()` ne l'ajoute pas.
			$text = [$background, \structure\Community::INK_LIGHT];
			$muted = [$background];
		} else {
			$text = array_map(fn(string $matter) => ($matter === '{background}') ? $background : $matter, $matters);
			$muted = $text;
		}

		return [
			'ink' => \structure\Community::getInk($color),
			'textInk' => self::getTextInkDarkened($color, ...$text),
			'mutedInk' => \structure\Community::getMutedInk(...$muted)
		];

	}

	/**
	 * La couleur du réseau quand elle sert de texte : telle quelle si elle se lit sur chaque fond,
	 * sinon foncée pas à pas jusqu'à se lire sur le plus sombre, pour que le texte garde la teinte du
	 * réseau plutôt que l'encre sombre du produit. Un fond choisi est mêlé de moitié de blanc
	 * (`BACKGROUND_SOFTENING`), donc clair : l'encre y fonce vers le noir, et son contraste croît
	 * avec la clarté du fond, si bien que 4,5:1 sur le plus sombre vaut 4,5:1 sur tous.
	 *
	 * Une couleur sans teinte (`TEXT_INK_SATURATION`), ou qui n'est pas un hexadécimal, n'est pas
	 * foncée et garde l'encre sombre.
	 *
	 * La couleur lisible est rendue telle qu'elle a été choisie, parce que `getInkOnBackground()` la
	 * réécrit en majuscules même sans la foncer. La couleur foncée repasse par `getTextInk()`, qui
	 * rend son repli quand elle ne se lit toujours pas sur chaque fond : foncer n'a pas suffi, ou une
	 * matière sombre l'a fait éclaircir.
	 */
	private static function getTextInkDarkened(string $color, string ...$backgrounds): string {

		$ink = \structure\Community::getTextInk($color, ...$backgrounds);

		if(
			$ink === $color or
			self::getSaturation($color) < self::TEXT_INK_SATURATION
		) {
			return $ink;
		}

		// Le contraste avec le noir range les fonds comme leur luminance.
		usort($backgrounds, fn(string $a, string $b): int => (\util\ColorUi::getContrastWith($a, '#000000') ?? 0.0) <=> (\util\ColorUi::getContrastWith($b, '#000000') ?? 0.0));

		return \structure\Community::getTextInk(\util\ColorUi::getInkOnBackground($color, $backgrounds[0]), ...$backgrounds);

	}

	/**
	 * La saturation que juge `TEXT_INK_SATURATION`, `(max − min) / max` des trois canaux : 0 pour le
	 * blanc, le noir et les gris, 1 pour une couleur pure. Lue comme `\util\ColorUi` lit une couleur,
	 * sur ses trois premiers octets, pour que le seuil juge la même couleur que le contraste ; une
	 * chaîne qui n'en porte pas trois n'a pas de teinte et vaut 0.
	 */
	private static function getSaturation(string $color): float {

		$rgb = sscanf($color, '#%02x%02x%02x');

		if($rgb === NULL or in_array(NULL, $rgb, TRUE)) {
			return 0.0;
		}

		// Le noir n'a pas de teinte, et son maximum nul ne divise pas.
		if(max($rgb) === 0) {
			return 0.0;
		}

		return (max($rgb) - min($rgb)) / max($rgb);

	}

	/**
	 * La teinte claire d'un gabarit, `color-mix(in srgb, <couleur> 18%, <fond adouci>)`. Le texte
	 * plein y est l'encre du texte (`--text`), sombre, que la teinte laisse lisible sous toutes les
	 * couleurs ; le gris des annotations se juge ici sur la teinte et sur le fond, entre lesquels un
	 * gabarit peut fondre son bandeau, et que la teinte dépasse en clarté sous une couleur claire.
	 * Le fond est celui que la page pose, pour qu'un bandeau teinté reste de la famille de la page
	 * plutôt que d'un papier propre au gabarit.
	 *
	 * @return array{background: string, muted: string}
	 */
	public static function getTint(string $color, string $background): array {

		$tint = self::mix($background, $color, self::TINT_PART);

		return [
			'background' => $tint,
			'muted' => \structure\Community::getMutedInk($tint, $background),
		];

	}

	/**
	 * Le mélange sRGB de `color-mix(in srgb, …)`, `$part` comptant pour la seconde couleur.
	 */
	private static function mix(string $color, string $other, float $part): string {

		$a = sscanf($color, '#%02x%02x%02x');
		$b = sscanf($other, '#%02x%02x%02x');

		return vsprintf('#%02x%02x%02x', array_map(fn(int $x, int $y): int => (int)round($x * (1 - $part) + $y * $part), $a, $b));

	}

	/**
	 * Le thème de la fiche et des leçons d'une formation : celui de son réseau, et aucun pour une
	 * formation de l'équipe ou d'un groupe de formation, qui restent aux couleurs du catalogue.
	 */
	public static function getSettingsByCourse(Course $eCourse): ?array {

		if($eCourse->isNetwork() === FALSE) {
			return NULL;
		}

		return self::getSettings(\structure\CommunityLib::getByStructure($eCourse['community']));

	}

}
?>
