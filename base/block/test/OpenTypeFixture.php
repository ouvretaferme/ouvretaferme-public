<?php
namespace blockFixture;

/**
 * Un type de bloc apporté par un package qui n'est pas le moteur, comme un package fermé apporte les
 * siens : le moteur ne le connaît que par la résolution de son nom (`\block\BlockSetting::resolve()`).
 * Le package `blockFixture` n'existe pas, et n'entre donc ni dans `\Package::getList()` ni dans la
 * production. Ce fichier est chargé par `require_once` depuis `BlockOpenType.t.php`, le lanceur ne
 * prenant que les `.t.php`.
 *
 * Le type a deux formes fixées (`isVariantFixed()`), choisies au « + » et jamais changées ensuite : la
 * forme `a` range un libellé dans `settings`, qu'il met en forme et borne lui-même, et nomme son refus
 * dans son package (`Block::settings.label`) ; la forme `b` y range une grille, qu'il écrit avec ses
 * propres champs. Il n'est proposé qu'aux leçons des formations de l'équipe, par l'observateur de
 * `learning` (`LearningObserverLib`), que le test inscrit seul le temps de son cas.
 */
class BlockTypeLib {

	const ENTRIES = 'blockFixture-entries';

	const A = 'a';
	const B = 'b';

	const LABEL_LIMIT = 20;

	/**
	 * La forme ne se change pas : elle ne s'écrit qu'à la création, avant le réglage, qui se lit selon
	 * elle.
	 */
	public static function getProperties(string $type): array {

		return match($type) {
			self::ENTRIES => ['settings'],
		};

	}

	public static function getPropertiesCreate(string $type): array {

		return match($type) {
			self::ENTRIES => ['variant', 'settings'],
		};

	}

	public static function getPropertiesRequired(string $type): array {

		return match($type) {
			self::ENTRIES => [],
		};

	}

	public static function getChildren(string $type): array {

		return match($type) {
			self::ENTRIES => [],
		};

	}

	public static function getChildrenMax(string $type): ?int {

		return match($type) {
			self::ENTRIES => NULL,
		};

	}

	public static function getVariants(string $type): array {

		return match($type) {
			self::ENTRIES => [self::A, self::B],
		};

	}

	public static function isVariantFixed(string $type): bool {

		return match($type) {
			self::ENTRIES => TRUE,
		};

	}

	public static function getVariantUnique(string $type): ?string {

		return match($type) {
			self::ENTRIES => NULL,
		};

	}

	public static function getDefaultSettings(string $type, ?string $variant = NULL): array {

		return match($type) {
			self::ENTRIES => match($variant) {
				self::A => ['label' => NULL],
				self::B => ['rows' => array_fill(0, 2, array_fill(0, 2, ''))],
			},
		};

	}

	/**
	 * Le type n'écrit pas de texte : aucune figure n'y entre.
	 */
	public static function getEditorOptions(string $type, string $owner): array {

		return match($type) {
			self::ENTRIES => ['acceptFigure' => FALSE],
		};

	}

	/**
	 * Le réglage se lit selon la forme. Le libellé arrive du champ `settings[label]`, bords retirés,
	 * vide pour aucun libellé. Trop long, il est refusé sous le nom que le type lui donne, rattaché au
	 * champ par `$wrapper`. La grille arrive des champs `settings[rows][<ligne>][<colonne>]` que pose
	 * `BlockTypeUi::getEditable()`.
	 */
	public static function castSettings(\block\Block $eBlock, mixed &$settings, string $wrapper): bool {

		return match($eBlock['variant']) {
			self::A => self::castLabel($eBlock, $settings, $wrapper),
			self::B => self::castRows($settings),
		};

	}

	private static function castLabel(\block\Block $eBlock, mixed &$settings, string $wrapper): bool {

		if(
			is_array($settings) === FALSE or
			array_key_exists('label', $settings) === FALSE or (
				$settings['label'] !== NULL and
				is_string($settings['label']) === FALSE
			)
		) {
			return FALSE;
		}

		$label = $settings['label'];

		if($label !== NULL) {
			$label = trim($label);
		}

		if($label === '') {
			$label = NULL;
		}

		if(
			$label !== NULL and
			mb_strlen($label) > self::LABEL_LIMIT
		) {
			throw new \FailException('blockFixture\Block::settings.label', [$eBlock, $settings], $wrapper);
		}

		$settings = ['label' => $label];

		return TRUE;

	}

	private static function castRows(mixed &$settings): bool {

		if(
			is_array($settings) === FALSE or
			isset($settings['rows']) === FALSE or
			is_array($settings['rows']) === FALSE or
			$settings['rows'] === []
		) {
			return FALSE;
		}

		$rows = [];

		foreach($settings['rows'] as $cells) {

			if(
				is_array($cells) === FALSE or
				array_filter($cells, 'is_string') !== $cells
			) {
				return FALSE;
			}

			$rows[] = array_values(array_map('trim', $cells));

		}

		$settings = ['rows' => $rows];

		return TRUE;

	}

	public static function isEmpty(\block\Block $eBlock): bool {

		return match($eBlock['type']) {
			self::ENTRIES => match($eBlock['variant']) {
				self::A => ($eBlock['settings']['label'] === NULL),
				self::B => (implode('', array_merge(...$eBlock['settings']['rows'])) === ''),
			},
		};

	}

}

/**
 * Les mots et les rendus du type. Chaque forme a son nom et sa ligne de tuile ; l'icône est celle du
 * type. La grille s'écrit case par case, un champ par case du réglage.
 */
class BlockTypeUi {

	public function getName(string $type, ?string $parentType = NULL, ?string $variant = NULL): string {

		return match($type) {
			BlockTypeLib::ENTRIES => match($variant) {
				BlockTypeLib::A => 'Forme a',
				BlockTypeLib::B => 'Forme b',
			},
		};

	}

	public function getDescription(string $type, ?string $variant = NULL): string {

		return match($type) {
			BlockTypeLib::ENTRIES => match($variant) {
				BlockTypeLib::A => 'Un libellé',
				BlockTypeLib::B => 'Une grille',
			},
		};

	}

	public function getIcon(string $type): string {

		return match($type) {
			BlockTypeLib::ENTRIES => 'journal-text',
		};

	}

	public function render(\block\Block $eBlock, array $attributes = [], ?\Closure $wrap = NULL, bool $insert = FALSE): string {

		return match($eBlock['variant']) {
			BlockTypeLib::A => '<p>'.encode($eBlock['settings']['label']).'</p>',
			BlockTypeLib::B => '<p>'.count($eBlock['settings']['rows']).'</p>',
		};

	}

	public function getEditable(\block\Block $eBlock, \util\FormUi $form, ?string $draft): string {

		return match($eBlock['variant']) {
			BlockTypeLib::A => '<div data-wrapper="settings">'.$form->text('settings[label]', $eBlock['settings']['label']).'</div>',
			BlockTypeLib::B => $this->getRowsEditable($eBlock, $form),
		};

	}

	private function getRowsEditable(\block\Block $eBlock, \util\FormUi $form): string {

		$h = '<div data-wrapper="settings">';

		foreach($eBlock['settings']['rows'] as $row => $cells) {
			foreach($cells as $column => $cell) {
				$h .= $form->text('settings[rows]['.$row.']['.$column.']', $cell);
			}
		}

		$h .= '</div>';

		return $h;

	}

}

/**
 * Ce que le type ajoute aux types d'une leçon (`\learning\BlockOwnerLib::getTypesByOwner()`) : lui-même,
 * dans une formation de l'équipe seulement.
 */
class LearningObserverLib {

	public static function getBlockTypes(\learning\Lesson $eLesson): array {

		if($eLesson['course']->isTeam() === FALSE) {
			return [];
		}

		return [BlockTypeLib::ENTRIES];

	}

}

/**
 * Les mots des refus du type : ceux qu'il nomme lui-même, et ceux du moteur dont les mots dépendent du
 * type (`\block\AlertUi::ERRORS_BY_TYPE`), qu'il dit sous la même clé.
 */
class AlertUi {

	public static function getError(string $fqn, array $options = []): mixed {

		return match($fqn) {

			'Block::variant.check', 'Block::variant.allowed' => "Choisissez la forme a ou la forme b.",
			'Block::settings.check', 'Block::settings.format' => "Ce libellé n'a pas pu être enregistré.",
			'Block::settings.label' => "Un libellé contient au plus ".BlockTypeLib::LABEL_LIMIT." caractères.",

			default => NULL

		};

	}

}
?>
