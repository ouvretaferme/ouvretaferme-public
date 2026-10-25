/**
 * Le glisser-déposer de l'accueil en réglage, et rien d'autre : les interrupteurs sont des
 * `data-ajax` du cadriciel et n'ont besoin d'aucun JS.
 *
 * Le nom de la classe est préfixé par le package : tous les JS partagent un seul scope global et
 * s'accumulent en navigation ajax, et une `class` déclarée deux fois annule silencieusement tout le
 * second fichier (`framework/dev/test/JsSentinel.t.php`).
 */
class DashboardPreference {

	/**
	 * Chaque étage triable se réarme à son rendu : une action qui renvoie la rangée ou une colonne
	 * remplace l'étage, et le Sortable précédent part avec le nœud qu'il tenait.
	 *
	 * Le garde sur le nœud est obligatoire : une exception dans un `onrender` avorte la boucle de
	 * `Element.prototype.parseRender` et fait tomber tous les `onrender` suivants de la page.
	 */
	static initSections(fold) {

		if(fold === null || fold === undefined) {
			return;
		}

		new Sortable(fold, DashboardPreference.getOptions({
			onEnd: e => {

				if(e.oldIndex === e.newIndex) {
					return;
				}

				DashboardPreference.savePositions(fold, '', DashboardPreference.getNames(fold.children));

			}
		}));

	}

	/**
	 * Une colonne de blocs, un Sortable chacune et sans `group` partagé : un bloc ne change pas de
	 * colonne, sa déclaration la nomme.
	 *
	 * Un bloc ne se range que parmi ceux de son univers, qui se répartissent dans les colonnes :
	 * `onMove` refuse toute cible d'un autre univers, et ce qu'il refuse s'estompe pendant le geste
	 * (`util-drag-out`, que Sortable ne sait pas poser). Le dépôt poste alors tout l'univers, dans l'ordre
	 * du document, parce que `doUpdatePositions` attend la liste entière de ses blocs
	 * allumés et ne garde que leur ordre relatif.
	 */
	static initColumn(fold) {

		if(fold === null || fold === undefined) {
			return;
		}

		new Sortable(fold, DashboardPreference.getOptions({
			onStart: e => {

				qsa('#dashboard .dashboard-columns [data-sort-url] > *', cell => {

					if(cell.parentElement !== fold || cell.dataset.section !== e.item.dataset.section) {
						cell.classList.add('util-drag-out');
					}

				});

			},
			onMove: e => e.related.dataset.section === e.dragged.dataset.section,
			onEnd: e => {

				qsa('#dashboard .util-drag-out', cell => cell.classList.remove('util-drag-out'));

				if(e.oldIndex === e.newIndex) {
					return;
				}

				const section = e.item.dataset.section;
				const cells = Array.from(document.querySelectorAll('#dashboard .dashboard-columns [data-sort-id]'))
					.filter(cell => cell.dataset.section === section);

				DashboardPreference.savePositions(fold, section, DashboardPreference.getNames(cells));

			}
		}));

	}

	/**
	 * `>` borne aux enfants directs (`closest()` de Sortable) : l'intérieur d'une cellule est rendu par
	 * le package du bloc, que le socle ne connaît pas, et un `data-sort-id` qu'il y poserait ferait
	 * viser ce nœud au lieu de la cellule.
	 */
	static getOptions(options) {

		return Object.assign({
			handle: '.util-drag-handle',
			draggable: '>[data-sort-id]',
			ghostClass: 'util-drag-ghost',
			chosenClass: 'util-drag-chosen',
			forceFallback: true,
			fallbackOnBody: true
		}, options);

	}

	static getNames(nodes) {

		return Array.from(nodes)
			.filter(node => node.dataset.sortId !== undefined)
			.map(node => node.dataset.sortId);

	}

	/**
	 * L'ordre tel que l'écran le porte après le dépôt, envoyé par `Ajax.Query` avec l'étage en
	 * contexte : c'est lui qui apporte le verrou anti-double-envoi et l'effacement des erreurs
	 * précédentes. Un appel nu au `fetch` du navigateur perdrait l'en-tête `x-requested-with`, et la
	 * `FailAction` cracherait le `FailWatch` en texte brut.
	 *
	 * Rien à faire ici de la réponse : Sortable a déjà déplacé le rang. Un rang de bloc répond par un
	 * `VoidAction`, réordonner ce qui est déjà en place le ferait sauter sous le doigt ; un rang
	 * d'univers répond par les colonnes, que le cadriciel pose de lui-même.
	 */
	static savePositions(fold, scope, names) {

		new Ajax.Query(fold)
			.url(fold.dataset.sortUrl)
			.body({
				id: fold.getAttribute('post-id'),
				scope: scope,
				names: JSON.stringify(names)
			})
			.fetch();

	}

}
