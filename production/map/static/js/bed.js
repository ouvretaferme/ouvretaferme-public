document.delegateEventListener('input', '#bed-create-number', function() {
	MapBed.changeNumber(this.form);
});

document.delegateEventListener('click', '#bed-create-auto', function() {

	const prefix = qs('#bed-create-prefix').value;
	const suffix = qs('#bed-create-suffix').value;
	let start = parseInt(qs('#bed-create-start').value);

	qsa('#bed-create-names input[name="name[]"]', input => input.value = prefix + start++ + suffix);

	MapBed.changeBeds(this.form);

});

document.delegateEventListener('input', '#bed-create-names input, #bed-create [name="length"], #bed-create [name="width"], #bed-create [name="alleyWidth"]', function() {
	MapBed.changeBeds(this.form);
});

document.delegateEventListener('input', 'div.bed-write-size [name="length"], div.bed-write-size [name="width"]', function(e) {

	const wrapper = this.firstParent('div.bed-write-size');

	const length = parseInt(wrapper.qs('[name="length"]').value);
	const width = parseInt(wrapper.qs('[name="width"]').value);

	wrapper.qs('div.bed-write-size-area', node => {

		if(isNaN(length) || isNaN(width)) {
			node.setAttribute('data-area', 0);
			return;
		}

		const area = length * width / 100;

		node.setAttribute('data-area', area);
		node.qs('span').textContent = area;

	});

});

document.delegateEventListener('input', '#panel-bed-size div.bed-write-size input', function() {
	this.form.qs('[name="changed['+ this.name +']"]').value = (this.value === this.defaultValue) ? 0 : 1;
});

class MapBed {

	/* Un nom par planche, et un de plus au-delà du plafond : il suffit à ce que le serveur refuse le nombre, sans en fabriquer
	   autant qu'on en a tapé */
	static changeNumber(form) {

		const field = form.qs('#bed-create-number');
		const nodeNames = form.qs('#bed-create-names');

		const typed = parseInt(field.value);
		const number = isNaN(typed) ? 0 : Math.max(0, Math.min(typed, parseInt(field.max) + 1));

		let already = nodeNames.childElementCount;

		while(already < number) {

			already++;

			nodeNames.insertAdjacentHTML('beforeend', nodeNames.dataset.input);
			nodeNames.qs('.bed-create-one:last-child', node => {

				// buildIndex() nomme ses échecs name[i] : c'est ce wrapper qui les pose sur la bonne planche
				node.setAttribute('data-wrapper', 'name[' + (already - 1) + ']');

				node.qs('input', input => {
					input.removeAttribute('id');
					input.value = MapBed.buildName(nodeNames, already);
				});

			});

		}

		while(already > number) {
			nodeNames.lastElementChild.remove();
			already--;
		}

		const submit = form.qs('#bed-create-submit');

		submit.disabled = (number === 0);

		if(number > 0) {
			submit.textContent = (typed === 1) ? submit.dataset.one : submit.dataset.many.replace('{value}', typed);
		}

		MapBed.changeBeds(form);

	}

	/* Le rang dessiné sur la carte du panneau suit les planches que le formulaire décrit */
	static changeBeds(form) {
		form.dispatchEvent(new CustomEvent('bedsChange', {bubbles: true}));
	}

	static buildName(nodeNames, position) {

		const start = parseInt(nodeNames.dataset.start ?? 1);
		const pad = parseInt(nodeNames.dataset.pad ?? 0);

		let number = String(start + position - 1);

		if(pad > 0) {
			number = number.padStart(pad, '0');
		}

		return (nodeNames.dataset.prefix ?? '') + number + (nodeNames.dataset.suffix ?? '');

	}

	static startCustomizeNumbering() {

		qs('#bed-create-customize-label').hide();
		qs('#bed-create-customize').removeHide();

	}

}
