class WebsiteCustomizeAi {

	static get scrollKey() {
		return 'website-customize-scroll';
	}

	static get panelKey() {
		return 'website-customize-folded';
	}

	static set(property, value) {

		const url = new URL(window.location.href);

		url.searchParams.set('customize', '1');

		if(value === '') {
			url.searchParams.delete(property);
		} else {
			url.searchParams.set(property, value);
		}

		this.reload(url);

	}

	static magic() {

		const bar = qs('#website-customize-bar');
		const settings = JSON.parse(bar.dataset.settings);
		const palette = Customize.randomPalette(settings.customText === 'black');

		const url = new URL(window.location.href);

		url.searchParams.set('customize', '1');

		for(const [key, value] of Object.entries(palette)) {
			url.searchParams.set(key, value);
		}

		this.reload(url);

	}

	static toggle() {

		const bar = qs('#website-customize-bar');

		bar.classList.toggle('customize-bar-folded');

		sessionStorage.setItem(this.panelKey, bar.classList.contains('customize-bar-folded') ? '1' : '0');

	}

	static cancel() {

		sessionStorage.removeItem(this.scrollKey);

		window.location.href = qs('#website-customize-bar').dataset.exit;

	}

	// L'enregistrement passe par la couche ajax du framework : les réglages
	// refusés reviennent en instructions et s'affichent comme partout ailleurs,
	// et le rechargement en place est celui de la ReloadAction.
	static save() {

		const bar = qs('#website-customize-bar');

		const body = new FormData();

		const settings = JSON.parse(bar.dataset.settings);

		for(const [key, value] of Object.entries(settings)) {
			body.append(key, value === null ? '' : String(value));
		}

		sessionStorage.setItem(this.scrollKey, String(window.scrollY));

		new Ajax.Query(bar)
			.method('post')
			.url(bar.dataset.url)
			.body(body)
			.fetch();

	}

	static reload(url, keepScroll = true, replace = false) {

		if(keepScroll) {
			sessionStorage.setItem(this.scrollKey, String(window.scrollY));
		} else {
			sessionStorage.removeItem(this.scrollKey);
		}

		if(replace) {
			window.location.replace(url.toString());
		} else {
			window.location.href = url.toString();
		}

	}

	static restore() {

		const bar = qs('#website-customize-bar');

		if(bar === null) {
			return;
		}

		if(sessionStorage.getItem(this.panelKey) === '1') {
			bar.classList.add('customize-bar-folded');
		}

		Customize.restore(bar);

		this.measure(bar);

		const scroll = sessionStorage.getItem(this.scrollKey);

		if(scroll !== null) {
			window.scrollTo(0, parseInt(scroll));
			sessionStorage.removeItem(this.scrollKey);
		}

	}

	static measure(bar) {

		const apply = () => document.body.style.setProperty('--customize-height', bar.offsetHeight + 'px');

		apply();

		if(this.observer !== undefined) {
			this.observer.disconnect();
		}

		this.observer = new ResizeObserver(apply);
		this.observer.observe(bar);

	}

}

document.addEventListener('navigation.wakeup', () => WebsiteCustomizeAi.restore());
document.addEventListener('DOMContentLoaded', () => WebsiteCustomizeAi.restore());
