<?php
namespace series;

/**
 * Copier le plan de culture d'une ferme vers une autre, ou le sortir en CSV.
 *
 * ⚠️ L'écran de conflits n'est pas ici : c'est celui de l'import d'un fichier, `CsvUi::getImportFile()`,
 * auquel cette copie fournit une autre source et une autre action. Deux écrans qui montrent les
 * mêmes cinq familles de conflits ne se ressemblent pas par accident, ce sont le même écran — et
 * le jour où l'un des conflits change, il n'y a qu'un endroit à reprendre.
 */
class CopyAiUi {

	/**
	 * La barre d'emport, sur le plan de culture d'un pair.
	 *
	 * ⚠️ **`BatchUi::group()`, et sous l'identifiant de la barre de lot.** Les deux ne coexistent
	 * jamais — qui a le planning sur cette ferme voit le lot, qui la lit par un groupe voit
	 * l'emport —, `Series.changeSelection()` cherche la barre à ce nom, et un second identifiant
	 * aurait demandé un second chemin JS pour dire la même chose. Un jumeau dessiné à côté aurait
	 * divergé au premier changement de la barre.
	 *
	 * ⚠️ Pas de `BatchUi::one()` : un seul geste n'a pas de menu compact à poser contre une case.
	 * `Batch.changeSelection()` accepte que ce second identifiant ne réponde pas.
	 *
	 * La saison voyage dès l'ouverture du panneau : elle est celle du plan qu'on regarde, il n'y a
	 * rien à en demander.
	 */
	public static function getBatchExport(\structure\Structure $eStructureSource, \structure\Structure $eStructureCommunity, int $season): string {

		$url = '/series/copyAi:create?structure='.$eStructureSource['id']
			.'&community='.$eStructureCommunity['id']
			.'&mode='.CopyAiLib::MINE
			.'&season='.$season;

		$menu = '<a data-ajax="'.$url.'" data-ajax-method="get" class="batch-item" data-batch-test="accept-export" data-batch-contains="get" data-batch-not-contains="hide">';
			$menu .= \Asset::icon('copy');
			$menu .= '<span>'.s("Copier vers ma ferme").'</span>';
		$menu .= '</a>';

		return \util\BatchUi::group('batch-series', $menu, title: s("Pour les séries sélectionnées"), hide: 'Series.hideSelection()');

	}

	/**
	 * Le panneau qui demande d'où l'on copie et où l'on atterrit.
	 *
	 * ⚠️ La saison d'arrivée se demande, elle ne se déduit pas de la source. C'est ce qui fait
	 * disparaître une famille entière de conflits : une saison absente de la ferme d'arrivée était
	 * une erreur bloquante que rien ne savait réparer. On ne signale plus le conflit, on demande
	 * où atterrir.
	 */
	public function create(\structure\Structure $eStructureSource, \structure\Structure $eStructureCommunity, string $mode, array $seasonsSource, \Collection $cStructureDestination, array $seasonsDestination, \Collection $cSeriesSelection = new \Collection(), ?int $seasonSelection = NULL): \Panel {

		// ⚠️ Le téléchargement ne passe pas par un formulaire, il passe par des liens — un par
		// saison. Un `openAjax()` attend du JSON en retour et donnait le CSV à `JSON.parse`, qui
		// l'affichait en clair dans le bandeau d'erreur. `data-ajax-download` répare ce cas sur un
		// lien (`ajax.js:1644`) mais **pas** sur un formulaire : `form.js:21` appelle `download()`
		// sur la classe et non sur l'instance, qui n'est construite qu'après, et lève
		// `object.download is not a function`. Les huit exports de `StructureUi::export()` sont
		// tous des liens, pour cette raison.
		// ⚠️ Avant la bascule du téléchargement : sans saison, celui-ci rendrait une liste de zéro
		// lien, c'est-à-dire un panneau vide sans rien dire.
		if($seasonsSource === []) {
			return self::createEmpty($eStructureSource, $mode);
		}

		if($mode === CopyAiLib::CSV) {
			return self::createCsv($eStructureSource, $eStructureCommunity, $seasonsSource);
		}

		$form = new \util\FormUi();

		$h = $form->openAjax('/series/copyAi:doCreate');

			$h .= $form->hidden('structure', $eStructureSource['id']);
			$h .= $form->hidden('community', $eStructureCommunity['id']);
			$h .= $form->hidden('mode', $mode);

			// ⚠️ Une sélection ne se redemande pas sa saison : elle a été cochée sur un plan, donc
			// sur une saison, et rouvrir le choix ici permettrait d'emporter des séries d'une
			// saison en en désignant une autre — ce que la relecture de la sélection refuserait
			// ensuite, sans que rien ne le dise à l'écran.
			if($cSeriesSelection->empty()) {

				$h .= $form->group(
					s("Saison à copier"),
					$form->select('season', array_combine($seasonsSource, $seasonsSource), end($seasonsSource))
				);

			} else {

				$h .= $form->hidden('season', $seasonSelection);
				$h .= $form->hidden('series', implode(',', $cSeriesSelection->getIds()));

				$h .= $form->group(
					s("Séries à copier"),
					'<p class="mb-0">'.p("{value} série sélectionnée en saison {season}", "{value} séries sélectionnées en saison {season}", $cSeriesSelection->count(), ['season' => $seasonSelection]).'</p>'
				);

			}

			if($cStructureDestination->empty()) {

				$h .= $form->group(
					content: '<div class="util-block-info"><p>'.s("Vous n'avez aucune ferme qui puisse recevoir ce plan de culture.").'</p></div>'
				);

				$h .= $form->close();

				return new \Panel(
					id: 'panel-copy-create',
					title: self::getTitle($mode),
					subTitle: encode($eStructureSource['name']),
					body: $h
				);

			}

			$destinations = [];

			foreach($cStructureDestination as $eStructureDestination) {
				$destinations[$eStructureDestination['id']] = $eStructureDestination['name'];
			}

			$h .= $form->group(
				s("Ferme d'arrivée"),
				$form->select('destination', $destinations, $cStructureDestination->first()['id'])
			);

			// Les saisons de la ferme d'arrivée et non celles de la source : c'est là qu'on
			// atterrit, et proposer une saison que la ferme n'ouvre pas recréerait le conflit
			// que ce choix fait disparaître.
			$h .= $form->group(
				s("Saison d'arrivée"),
				'<div id="copy-seasons">'.self::getSeasonsDestination($seasonsDestination).'</div>'
			);

			$h .= $form->group(
				content: $form->submit(s("Analyser la copie"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-copy-create',
			title: self::getTitle($mode),
			subTitle: encode($eStructureSource['name']),
			body: $h
		);

	}

	/**
	 * Le téléchargement : une saison, un lien.
	 *
	 * ⚠️ Un lien par saison plutôt qu'un choix suivi d'un bouton, parce que le geste est le même
	 * dans les deux cas et que le lien est la seule forme que la couche de téléchargement sache
	 * servir. Mesuré sur la base de dev, 1 604 fermes sur 2 300 n'ont qu'une saison de séries et
	 * la plus fournie en porte onze : la liste tient sans jamais devenir un mur.
	 */
	protected static function createCsv(\structure\Structure $eStructureSource, \structure\Structure $eStructureCommunity, array $seasonsSource): \Panel {

		$h = '<div class="util-buttons">';

			foreach(array_reverse($seasonsSource) as $season) {

				$h .= '<a href="'.self::urlCsv($eStructureSource, $eStructureCommunity, $season).'" class="util-button" data-ajax-download data-waiter>';
					$h .= '<div>';
						$h .= '<h4>'.s("Saison {value}", $season).'</h4>';
					$h .= '</div>';
					$h .= \Asset::icon('download');
				$h .= '</a>';

			}

		$h .= '</div>';

		return new \Panel(
			id: 'panel-copy-create',
			title: self::getTitle(CopyAiLib::CSV),
			subTitle: encode($eStructureSource['name']),
			body: $h
		);

	}

	public static function urlCsv(\structure\Structure $eStructureSource, \structure\Structure $eStructureCommunity, int $season): string {

		return '/series/copyAi:csv?structure='.$eStructureSource['id']
			.'&community='.$eStructureCommunity['id']
			.'&mode='.CopyAiLib::CSV
			.'&season='.$season;

	}

	public static function getSeasonsDestination(array $seasons): string {

		$form = new \util\FormUi();

		return $form->select('target', array_combine($seasons, $seasons), end($seasons));

	}

	/**
	 * L'adresse de l'écran de conflits, qui porte tout l'état de la copie.
	 *
	 * ⚠️ Rien en session ni en cache : un lien envoyé montre la même copie, et la page se recharge
	 * sans rien perdre. C'est déjà la façon dont ce produit fait voyager un contexte, avec le
	 * sous-groupe choisi dans l'URL des écrans de groupe.
	 */
	public static function urlAnalyze(\structure\Structure $eStructureSource, \structure\Structure $eStructureCommunity, string $mode, int $season, int $destination, int $target, string $series = ''): string {

		return '/series/copyAi:analyze?structure='.$eStructureSource['id']
			.'&community='.$eStructureCommunity['id']
			.'&mode='.$mode
			.'&season='.$season
			.'&destination='.$destination
			.'&target='.$target
			.($series !== '' ? '&series='.$series : '');

	}

	/**
	 * Le panneau d'une ferme qui n'a rien à copier.
	 *
	 * ⚠️ Un état et non un refus : la route levait `NotAllowedAction`, donc « Vous n'êtes pas
	 * autorisé à afficher cette page », sur une ferme dont le groupe a bien autorisé la copie et
	 * dont le lecteur a bien le droit. Ce que le refus décrivait n'existait pas.
	 *
	 * ⚠️ Et non pas le retrait de l'entrée du menu : la liste des cartes devrait alors lire les
	 * saisons de chaque ferme, soit une lecture par carte pour éviter un panneau qu'on ouvre
	 * rarement. L'état vide est au bon endroit.
	 */
	protected static function createEmpty(\structure\Structure $eStructureSource, string $mode): \Panel {

		$h = '<div class="util-block util-block-empty">';

			$h .= '<h3>'.s("Cette ferme n'a pas encore de plan de culture").'</h3>';

			$h .= '<p>'.s("Il n'y a donc aucune série à copier.").'</p>';

		$h .= '</div>';

		return new \Panel(
			id: 'panel-copy-create',
			title: self::getTitle($mode),
			subTitle: encode($eStructureSource['name']),
			body: $h
		);

	}

	protected static function getTitle(string $mode): string {

		return match($mode) {
			CopyAiLib::CSV => s("Télécharger le plan de culture"),
			default => s("Copier le plan de culture")
		};

	}

	/**
	 * La troisième issue de l'écran de conflits : copier ce qui passe, et le dire.
	 *
	 * ⚠️ Le bouton nomme le nombre de séries, parce que c'est exactement ce qu'on accepte de
	 * perdre. « Copier sans ces données » et jamais « corriger plus tard » : rien ne rappellera de
	 * corriger, et les séries arriveront amputées.
	 */
	public function getPartial(string $arguments, int $series, int $skipped): string {

		if($series === 0) {
			return '';
		}

		$h = '<div class="util-block">';

			$h .= '<h4>'.s("Copier quand même ce qui passe").'</h4>';
			$h .= '<p>'.p(
				"{value} série peut être copiée en écartant ce qui est signalé ci-dessous.",
				"{value} séries peuvent être copiées en écartant ce qui est signalé ci-dessous.",
				$series
			).' '.p(
				"{value} culture sera laissée de côté, et rien ne rappellera de la reprendre.",
				"{value} cultures seront laissées de côté, et rien ne rappellera de les reprendre.",
				$skipped
			).'</p>';

			$h .= '<a data-ajax="/series/copyAi:doCopy" '.$arguments.' post-partial="1" class="btn btn-secondary" data-confirm="'.p(
				"Copier {value} série sans les données écartées ?",
				"Copier {value} séries sans les données écartées ?",
				$series
			).'" data-waiter="'.s("Copie en cours, merci de patienter...").'">'.s("Copier sans ces données").'</a>';

		$h .= '</div>';

		return $h;

	}

}
?>
