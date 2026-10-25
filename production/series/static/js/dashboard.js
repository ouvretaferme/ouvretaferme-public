/**
 * La marque « Fait » d'une intervention, dans le bloc des interventions de la semaine : celle d'une carte du
 * planning (`TaskCardUi::getMark()`), qui poste à la route de l'accueil. La ligne en contexte porte le verrou
 * anti-double-envoi d'`Ajax.Query`, et la réponse la redessine.
 */
document.delegateEventListener('click', '#series-dashboard-week [data-planning-task-done]', function(e) {

	e.preventDefault();

	SeriesDashboard.mark(this, 'doDone', this.dataset.planningTaskDone);

});

document.delegateEventListener('click', '#series-dashboard-week [data-planning-task-todo]', function(e) {

	e.preventDefault();

	SeriesDashboard.mark(this, 'doTodo', this.dataset.planningTaskTodo);

});

/**
 * Le panneau « Choisir » du bloc des interventions de la semaine, sur l'accueil.
 *
 * Glisser une ligne et basculer un interrupteur enregistrent la même chose, la liste entière des
 * actions allumées dans l'ordre du panneau : c'est l'ordre du bloc.
 */
class SeriesDashboard {

	/**
	 * Le garde sur le nœud est obligatoire : une exception dans un `onrender` avorte la boucle de
	 * `Element.prototype.parseRender` et fait tomber tous les `onrender` suivants de la page.
	 */
	static initSort(fold) {

		if(fold === null || fold === undefined) {
			return;
		}

		new Sortable(fold, {
			handle: '.util-drag-handle',
			draggable: '[data-action]',
			ghostClass: 'util-drag-ghost',
			chosenClass: 'util-drag-chosen',
			forceFallback: true,
			fallbackOnBody: true,
			onEnd: e => {

				if(e.oldIndex === e.newIndex) {
					return;
				}

				SeriesDashboard.save(fold);

			}
		});

	}

	static toggle(node) {

		node.toggleSwitch();

		SeriesDashboard.save(node.firstParent('.util-deck-fold'));

	}

	static mark(node, action, task) {

		const week = node.closest('[data-structure]');

		new Ajax.Query(node.closest('.series-dashboard-task'))
			.url('/series/dashboard:'+ action)
			.body({
				id: week.dataset.structure,
				task: task
			})
			.fetch();

	}

	/**
	 * `Ajax.Query` avec le panneau en contexte, pour le verrou anti-double-envoi et l'effacement des
	 * erreurs précédentes. La réponse redemande le bloc de l'accueil, le panneau reste tel quel.
	 */
	static save(fold) {

		const actions = Array.from(fold.qsa('[data-action]'))
			.filter(row => row.qs('.field-switch-on') !== null)
			.map(row => parseInt(row.dataset.action));

		new Ajax.Query(fold)
			.url(fold.dataset.saveUrl)
			.body({
				id: fold.getAttribute('post-id'),
				actions: JSON.stringify(actions)
			})
			.fetch();

	}

}
