class Customize {

	static setFont(select, property) {

		const bar = select.closest('.customize-bar');
		const option = select.selectedOptions[0];
		const value = select.value;

		if(option && option.dataset.family) {
			this.loadFont(option.dataset.family);
		}

		if(value === '') {
			document.documentElement.style.removeProperty('--' + property);
			document.documentElement.style.removeProperty('--' + property + 'Adjust');
		} else {
			document.documentElement.style.setProperty('--' + property, value);
			document.documentElement.style.setProperty('--' + property + 'Adjust', option.dataset.adjust || 'none');
		}

		const settings = JSON.parse(bar.dataset.settings);
		settings[property] = value === '' ? null : value;
		bar.dataset.settings = JSON.stringify(settings);

		const url = new URL(window.location.href);

		if(value === '') {
			url.searchParams.delete(property);
		} else {
			url.searchParams.set(property, value);
		}

		window.history.replaceState(null, '', url.toString());

	}

	static get colorsKey() {
		return 'customize-colors';
	}

	static toggleColors(button) {

		const colors = button.closest('.customize-colors');

		this.setColors(colors, colors.classList.contains('open') === false);

	}

	static setColors(colors, open) {

		colors.classList.toggle('open', open);

		sessionStorage.setItem(this.colorsKey, open ? '1' : '0');

	}

	// Chaque réglage recharge la page : sans cela le panneau se refermerait à
	// chaque couleur essayée.
	static restore(bar) {

		const colors = bar.qs('.customize-colors');

		if(colors !== null && sessionStorage.getItem(this.colorsKey) === '1') {
			colors.classList.add('open');
		}

		this.showDefaults(bar);

	}

	// Un réglage jamais touché vaut la valeur que la feuille de style donne par
	// défaut : la barre montre celle-là, et non un noir qui ne correspond à rien.
	static showDefaults(bar) {

		const styles = getComputedStyle(document.body);

		bar.qsa('[data-customize-default]', node => {

			const hex = this.toHex(styles.getPropertyValue(node.dataset.customizeDefault).trim());

			if(hex === null) {
				return;
			}

			if(node.tagName === 'INPUT') {
				node.value = hex;
			} else {
				node.style.backgroundColor = hex;
			}

		});

	}

	static toHex(color) {

		if(color === '') {
			return null;
		}

		const probe = document.createElement('span');

		probe.style.display = 'none';
		probe.style.color = color;

		document.body.appendChild(probe);

		const computed = getComputedStyle(probe).color;

		probe.remove();

		const values = computed.match(/[\d.]+/g);

		if(values === null) {
			return null;
		}

		const scale = computed.startsWith('color(') ? 255 : 1;

		return '#' + values.slice(0, 3).map(value => Math.round(parseFloat(value) * scale).toString(16).padStart(2, '0')).join('');

	}

	static loadFont(family) {

		const href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(family).replace(/%3A/g, ':').replace(/%40/g, '@').replace(/%3B/g, ';') + '&display=swap';

		if(document.head.querySelector('link[href="' + href + '"]') !== null) {
			return;
		}

		const link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = href;
		document.head.appendChild(link);

	}

}

document.addEventListener('click', function(e) {

	const colors = qs('.customize-colors.open');

	if(colors !== null && colors.contains(e.target) === false) {
		Customize.setColors(colors, false);
	}

});

Customize.randomPalette = function(isTextBlack) {

	const hue = Math.floor(Math.random() * 360);

	const color = Customize.hsl(
		hue,
		62 + Math.random() * 20,
		isTextBlack ? 34 + Math.random() * 10 : 56 + Math.random() * 12
	);

	const background = Customize.hsl(
		Math.floor(Math.random() * 360),
		4 + Math.random() * 16,
		isTextBlack ? 90 + Math.random() * 8 : 8 + Math.random() * 12
	);

	const linkColor = Customize.hsl(
		(hue + 150 + Math.random() * 60) % 360,
		55 + Math.random() * 25,
		isTextBlack ? 34 + Math.random() * 8 : 58 + Math.random() * 10
	);

	return {customColor: color, customBackground: background, customLinkColor: linkColor};

};

Customize.hsl = function(h, s, l) {

	s /= 100;
	l /= 100;

	const k = n => (n + h / 30) % 12;
	const a = s * Math.min(l, 1 - l);
	const f = n => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));

	const octet = n => Math.round(f(n) * 255).toString(16).padStart(2, '0');

	return '#' + octet(0) + octet(8) + octet(4);

};
