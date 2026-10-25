<?php
namespace toolbox;

class CompositionPlan extends CompositionPlanElement {

	/**
	 * Les onglets de l'écran du plan (`CompositionPlanLib::getForScreen()`,
	 * `CompositionUi::getProposal()`) : une taille, la synthèse de l'année, et les espèces livrées
	 * (r48a). Ce sont aussi les valeurs de `origin`, par lequel un geste dit l'onglet d'où il part
	 * (`CompositionPlanLib::getTab()`).
	 */
	const TAB_FORMULA = 'formula';
	const TAB_SYNTHESIS = 'synthesis';
	const TAB_SPECIES = 'species';

	public static function getSelection(): array {

		return parent::getSelection() + [
			'structure' => ['name'],
		];

	}

	/**
	 * Un plan n'accepte d'être écrit que si sa structure l'accepte.
	 *
	 * La règle est posée en `accept*` et non en `can*` parce qu'elle décrit l'état de la structure
	 * et non le rôle du lecteur : `ModuleApi` n'appelle que les `accept*`
	 * (`framework/core/Api.php:446`, `572`, `640`).
	 */
	public function acceptWrite(): bool {

		$this->expects(['structure']);

		return $this['structure']->acceptWrite();

	}

	public function canRead(): bool {

		$this->expects(['structure']);

		return $this['structure']->canAnalyzeProduction();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);

		return $this['structure']->canManage();

	}

	/**
	 * « Mettre en vente » une semaine (r37b) enregistre le produit que retient chaque espèce, ce qui
	 * écrit le plan, puis ouvre le panneau qui compose une vente : il faut les deux droits.
	 */
	public function canSell(): bool {

		$this->expects(['structure']);

		return ($this->canWrite() and $this['structure']->canWriteSelling());

	}

	/**
	 * Une composition se prépare dans un panneau de selling, qu'une installation sans les ventes n'a
	 * pas. Une structure qui ne peut pas encore créer de vente n'en a pas non plus à préparer : la
	 * condition est celle de `selling\Sale::acceptCreate()`, structure légale et TVA réglée, que le
	 * panneau repose avant d'ouvrir.
	 */
	public function acceptSell(): bool {

		$this->expects(['structure']);

		return (
			\structure\StructureSetting::hasCommercialisationApp() and
			$this->acceptWrite() and
			$this['structure']->isLegal() and
			$this['structure']->isVatAccountingConfigured()
		);

	}

	public function assertSell(): bool {
		return ($this->acceptSell() and $this->canSell());
	}

	public static function properties(\PropertyList $l): void {

		// La saison est celle du plan de culture que le plan suit, choisie dans la roue crantée : une
		// saison que la structure n'a pas n'a pas de plan de culture.
		$l->property('season')
			->addAfterCheck('configured', function(int $season): bool {

				return $this['structure']->conf(\map\Configuration::class)->checkSeason($season);

			});

		// D'où partent les saisons (r48b) : le départ propose le calendrier type ou le plan de culture.
		// La communauté reste dans l'enum pour les plans écrits avant le calendrier, et aucun départ
		// ne la pose plus.
		$l->property('reference')
			->addAfterCheck('start', function(string $reference): bool {

				return ($reference !== CompositionPlan::COMMUNITY);

			});

		// Le climat est celui du calendrier : il se choisit sous lui, et un plan parti du calendrier
		// sans climat n'aurait aucune saison à lire. Sous le plan de culture, le champ part quand même
		// avec le formulaire du départ, caché sous sa tuile : il ne s'écrit pas. Après le départ,
		// « Ajouter une espèce » (r48c) l'écrit sur un plan qui suit son plan de culture, pour dater sur
		// le calendrier une espèce qu'il ne cultive pas : la page le construit alors pour une
		// modification (`new \PropertyList('update')`), et il se garde.
		$l->property('climate')
			->addAfterCast('calendar', function(?string &$climate, \PropertyList $l): bool {

				if($l->for !== 'update' and $this['reference'] !== CompositionPlan::CALENDAR) {
					$climate = NULL;
				}

				return TRUE;

			})
			->addAfterCheck('required', function(?string $climate, \PropertyList $l): bool {

				return (
					$climate !== NULL or
					($l->for !== 'update' and $this['reference'] !== CompositionPlan::CALENDAR)
				);

			});

		// Une colonne json n'a pas de borne que le schéma puisse vérifier : sans cette étape, un plan
		// s'enregistre sur zéro semaine, et la règle de composition boucle sur rien sans rien dire.
		// Le contrôle remplace celui du schéma, qui ne sait que refuser l'absence de valeur.
		$l->property('weeks')
			// Un formulaire poste des chaînes, et le contrôle qui suit n'admet que des entiers. Seuls
			// les chiffres se convertissent : « 12a » reste une chaîne, et le contrôle la refuse.
			->addAfterCast('integers', function(?array &$weeks): bool {

				foreach($weeks ?? [] as $key => $week) {

					if(is_string($week) and ctype_digit($week)) {
						$weeks[$key] = (int)$week;
					}

				}

				return TRUE;

			})
			->replaceCheck(function(?array $weeks): bool {

				return ($weeks !== NULL and CompositionLib::checkWeeks($weeks));

			});

	}

}
?>
