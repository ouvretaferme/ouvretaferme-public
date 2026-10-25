document.delegateEventListener('autocompleteBeforeQuery', '[data-autocomplete-id="product"]', function(e) {

	qsa('#'+ e.detail.input.dataset.autocompleteItems +' input[type="hidden"]', input => e.detail.body.append('exclude[]', input.value));

});

document.delegateEventListener('autocompleteSelect', '[data-phyto-search]', function(e) {

	if(e.detail.value === 'manual') {
		Routine.phytoRefresh(e.target, {manual: 1, name: e.detail.itemText});
	} else {
		Routine.phytoRefresh(e.target, {amm: e.detail.value});
	}

});

class Routine {

	static phytoQuery(input) {

		input.value = input.dataset.phytoQuery;
		input.focus();

	}

	static phytoRefresh(target, body) {

		const wrapper = target.firstParent('#tool-phyto');

		new Ajax.Query(target)
			.url('/work/tool:getPhytoField')
			.body({
				structure: wrapper.dataset.structure,
				...(wrapper.dataset.tool ? {tool: wrapper.dataset.tool} : {}),
				...body
			})
			.fetch()
			.then((json) => wrapper.renderOuter(json.field));

	}

	static refresh(target) {

		const form = target.firstParent('form');

		form.ref('routines', wrapper => {

			new Ajax.Query(target)
				.url('/work/tool:getRoutinesField')
				.body({
					structure: wrapper.dataset.structure,
					action: target.value
				})
				.fetch()
				.then((json) => {

					wrapper.renderInner(json.field);

				});

		});

	}

	static select(target) {

		const form = target.firstParent('form');

		const routineName = target.value;

		form.ref('routine-', node => node.classList.add('hide'), () => null, '^=');

		if(routineName) {
			form.ref('routine-'+ routineName, node => node.classList.remove('hide'))
		}

	}

}