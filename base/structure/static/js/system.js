class StructureSystem {

	static properties = ['sellingDirect', 'sellingSemi', 'sellingLong'];

	static init(wrapper) {

		if(wrapper.dataset.sellingReady) {
			return;
		}

		wrapper.dataset.sellingReady = '1';
		wrapper.dataset.sellingOrder = '0,1,2';

		const form = wrapper.firstParent('form');

		this.properties.forEach((property, position) => {

			const field = form.qs('[name="'+ property +'"]');

			if(field === null) {
				return;
			}

			field.addEventListener('input', () => this.changeField(wrapper, position, field.value, false));
			field.addEventListener('change', () => this.changeField(wrapper, position, field.value, true));

		});

		wrapper.qsa('.system-selling-handle', handle => handle.addEventListener('pointerdown', e => this.startDrag(wrapper, handle, e)));

		wrapper.qs('.system-selling-bar').addEventListener('pointerdown', e => this.clickBar(wrapper, e));

		wrapper.qs('[data-action="selling-reset"]').addEventListener('click', () => this.reset(wrapper));

	}

	static reset(wrapper) {

		this.setValues(wrapper, [0, 0, 0], true);

		this.properties.forEach((property, position) => this.getField(wrapper, position).value = '');

		wrapper.dataset.sellingOrder = '0,1,2';
		wrapper.classList.add('system-selling-unset');

	}

	static getField(wrapper, position) {
		return wrapper.firstParent('form').qs('[name="'+ this.properties[position] +'"]');
	}

	static getValues(wrapper) {

		return this.properties.map((property, position) => {

			const value = parseInt(this.getField(wrapper, position).value);

			return isNaN(value) ? 0 : Math.min(100, Math.max(0, value));

		});

	}

	static getPercent(wrapper, clientX) {

		const bounding = wrapper.qs('.system-selling-bar').getBoundingClientRect();

		return Math.round(Math.min(100, Math.max(0, (clientX - bounding.left) / bounding.width * 100)));

	}

	static setValues(wrapper, values, force) {

		let offset = 0;

		this.properties.forEach((property, position) => {

			const part = wrapper.qs('[data-selling="'+ this.getMode(position) +'"]');

			part.style.left = offset +'%';
			part.style.width = values[position] +'%';
			part.innerHTML = values[position] >= 10 ? values[position] +'&nbsp;%' : '';

			offset += values[position];

			const field = this.getField(wrapper, position);

			// On ne réécrit pas le champ en cours de saisie, sauf sur un change() qui le fige
			if(force || field !== document.activeElement) {
				field.value = values[position];
			}

		});

		wrapper.qs('[data-selling-handle="0"]').style.left = values[0] +'%';
		wrapper.qs('[data-selling-handle="1"]').style.left = (values[0] + values[1]) +'%';

		wrapper.classList.remove('system-selling-unset');

	}

	static getMode(position) {
		return ['direct', 'semi', 'long'][position];
	}

	static isUnset(wrapper) {
		return wrapper.classList.contains('system-selling-unset');
	}

	/**
	 * Répartition initiale : le reste part sur la vente directe, ou sur le demi-gros si c'est elle qu'on saisit
	 */
	static initValues(position, value) {

		const values = [0, 0, 0];

		values[position] = value;
		values[position === 0 ? 1 : 0] = 100 - value;

		return values;

	}

	static changeField(wrapper, position, raw, force) {

		if(raw === '' && this.isUnset(wrapper)) {
			return;
		}

		const parsed = parseInt(raw);
		const value = isNaN(parsed) ? 0 : Math.min(100, Math.max(0, parsed));

		if(this.isUnset(wrapper)) {

			this.setOrder(wrapper, position);
			this.setValues(wrapper, this.initValues(position, value), force);

			return;

		}

		const values = this.getValues(wrapper);

		values[position] = value;

		// Le champ le plus anciennement modifié absorbe le reste, celui d'avant garde sa valeur
		const others = this.getOrder(wrapper).filter(other => other !== position);

		const remaining = 100 - value;
		const kept = Math.min(values[others[0]], remaining);

		values[others[0]] = kept;
		values[others[1]] = remaining - kept;

		this.setOrder(wrapper, position);
		this.setValues(wrapper, values, force);

	}

	static getOrder(wrapper) {
		return wrapper.dataset.sellingOrder.split(',').map(position => parseInt(position));
	}

	static setOrder(wrapper, position) {

		const order = [position, ...this.getOrder(wrapper).filter(other => other !== position)];

		wrapper.dataset.sellingOrder = order.join(',');

	}

	/**
	 * Les deux poignées délimitent les trois segments : les déplacer garde le total à 100 par construction
	 */
	static moveHandle(wrapper, handle, percent) {

		const values = this.getValues(wrapper);

		let first = values[0];
		let second = values[0] + values[1];

		if(handle === 0) {
			first = Math.min(percent, second);
		} else {
			second = Math.max(percent, first);
		}

		this.setValues(wrapper, [first, second - first, 100 - second], true);

	}

	static clickBar(wrapper, e) {

		if(e.target.closest('.system-selling-handle') !== null) {
			return;
		}

		e.preventDefault();

		const percent = this.getPercent(wrapper, e.clientX);

		if(this.isUnset(wrapper)) {
			this.setValues(wrapper, [percent, 100 - percent, 0], true);
			return;
		}

		const values = this.getValues(wrapper);
		const second = values[0] + values[1];

		this.moveHandle(wrapper, Math.abs(percent - values[0]) <= Math.abs(percent - second) ? 0 : 1, percent);

	}

	static startDrag(wrapper, handle, e) {

		e.preventDefault();

		const position = parseInt(handle.dataset.sellingHandle);

		handle.setPointerCapture(e.pointerId);

		const move = event => this.moveHandle(wrapper, position, this.getPercent(wrapper, event.clientX));

		const up = () => {
			handle.removeEventListener('pointermove', move);
			handle.removeEventListener('pointerup', up);
		};

		handle.addEventListener('pointermove', move);
		handle.addEventListener('pointerup', up);

	}

}
