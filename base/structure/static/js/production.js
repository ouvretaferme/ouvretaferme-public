let productionSpare;

document.delegateEventListener('click', 'a[data-action="production-add"]', function(e) {

	const wrapper = this.firstParent('.production-wrapper');

	const template = document.createElement("template");
	template.innerHTML = productionSpare.trim();
	const clone = template.content.firstElementChild;
	const position = wrapper.qsa('.production-item').length;

	clone.qsa('[name*="[]"]', node => node.setAttribute('name', node.getAttribute('name').replace('[]', '['+ position +']')));

	wrapper.qs('.production-items').insertAdjacentElement('beforeend', clone);

	Production.reorder(wrapper);

});

document.delegateEventListener('click', 'a[data-action="production-remove"]', function(e) {

	const wrapper = this.firstParent('.production-wrapper');

	this.firstParent('.production-item').remove();

	Production.reorder(wrapper);

});

class Production {

	static reorder(wrapper) {

		let order = 0;

		wrapper.qsa('.production-item', item => {
			item.qs('.production-item-position').innerHTML = ++order;
		});

	}

}