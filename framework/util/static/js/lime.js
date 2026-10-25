function evalScope(node, script) {

	(function() {
		eval(script);
	}).call(node);

}

function d(...arguments) {
	console.log(...arguments);
}

function dd(...arguments) {
	throw console.log(...arguments);
}

if('scrollRestoration' in history) { // what an amazing feature!
	history.scrollRestoration = 'manual';
}

// La barre d'outils d'un navigateur de téléphone se replie au défilement et déclenche un resize de hauteur
// seule : réécrire la même valeur relancerait le calcul du style de toute la page à chaque fois.
function calculateScreenWidth() {

	const width = Math.max(320, document.documentElement.clientWidth - 1) + 'px';

	if(document.documentElement.style.getPropertyValue('--screen-width') !== width) {
		document.documentElement.style.setProperty('--screen-width', width);
	}

}

calculateScreenWidth();

window.addEventListener('resize', calculateScreenWidth, false);
document.addEventListener('DOMContentLoaded', calculateScreenWidth, false);
window.addEventListener('load', calculateScreenWidth);

document.addEventListener("keydown", e => {

    if(e.key === 'Backspace' && e.target.matches('input, textarea, [contenteditable="true"]') === false) {
        e.preventDefault();
    }

});

document.addEventListener('DOMContentLoaded', () => {

	/**
	 * Save touch state
	 */
	document.body.dataset.touch = isTouch() ? 'yes' : 'no';

	Lime.History.init();
	Lime.Scroll.init();

	document.body.parseRender();

});

function isTouch() {
	return ("ontouchstart" in window);
}

/**
 * Load browser information
 */
let browser = {

	get: function() {

		return browser.isOpera ? 'Opera' :
			browser.isFirefox ? 'Firefox' :
				browser.isSafari ? 'Safari' :
					browser.isChrome ? 'Chrome' :
						browser.isIE ? 'IE' :
							browser.isEdge ? 'Edge' :
								browser.isBlink ? 'Blink' :
									'';
	}

};

// Opera 8.0+
browser.isOpera = (!!window.opr && !!opr.addons) || !!window.opera || navigator.userAgent.indexOf(' OPR/') >= 0;

// Firefox 1.0+
browser.isFirefox = navigator.userAgent.indexOf("Firefox") !== -1;

// Safari 3.0+ "[object HTMLElementConstructor]"
browser.isSafari = /constructor/i.test(window.HTMLElement) || (function (p) { return p.toString() === "[object SafariRemoteNotification]"; })(!window['safari'] || (typeof safari !== 'undefined' && window['safari'].pushNotification));

// Internet Explorer 6-11
browser.isIE = /*@cc_on!@*/false || !!document.documentMode;

// Edge 20+
browser.isEdge = !browser.isIE && !!window.StyleMedia;

// Chrome 1 - 79
browser.isChrome = !!window.chrome && (!!window.chrome.webstore || !!window.chrome.runtime);

// Blink engine detection
browser.isBlink = (browser.isChrome || browser.isOpera) && !!window.CS;

/**
 * Convert current rem unit in pixels
 */
function rem() {
	return parseFloat(getComputedStyle(document.documentElement).fontSize);
}

function money(value, precision = 2) {
	return formatNumber(value, precision) +' €';
}

function formatNumber(value, precision = 2) {

	const isNegative = value < 0;
	const multiplier = Math.pow(10, precision);

	value *= multiplier;
	value = Math.round(value);

	let output = isNegative ? Math.ceil(value / multiplier) : Math.floor(value / multiplier);

	// La partie entière d'un montant entre -1 et 0 vaut -0, qui s'écrit « 0 » : tout montant de moins
	// d'un euro perdait son signe. Le montant nul, lui, n'en a pas.
	if(isNegative && output === 0 && value !== 0) {
		output = '-0';
	}

	value = value.toString();

	if(precision > 0) {

		const decimal = value.substring(value.length - precision, value.length).padStart(precision, '0');

		output += ',';
		output += decimal;

		for(let i = decimal.length; i < precision; i++) {
			output += '0';
		}

	}

	return output;

}

function round(value, precision = 2) {

	const multiplier = Math.pow(10, precision);

	return Math.round(value * multiplier) / multiplier;

}

function calculateVatFromIncluding(amount, vatRate) {

	return Math.round((amount / (1 + vatRate / 100)) * (vatRate / 100) * 100) / 100;

}

function calculateExcludingFromIncluding(amount, vatRate) {

	return Math.round((amount - calculateVatFromIncluding(amount, vatRate)) * 100) / 100;

}

function doCopy(target) {

	const fromElement = qs(target.dataset.selector);

	// Le texte et non le balisage : un montant porte des &nbsp;, une adresse des &amp;
	if(navigator.clipboard.writeText(fromElement.textContent)) {
		alert(target.dataset.message);
	}

}

function parseBool(value) {

	switch(typeof value) {

		case 'boolean' :
			return value;

		case 'number' :
			return (value > 0 || value < 0);

		case 'string' :
			return (value !== '' && value !== '0');

		default :
			return true;

	}
}

/**
 * Shortcut for document.qs()
 *
 * @return Element
 */
function qs(selector, found, empty) {
	return document.qs(selector, found, empty);
}

/**
 * Shortcut for querySelector()
 *
 * @return Element
 */
Node.prototype.qs = function(selector, found, empty) {

	const node = this.querySelector(selector);

	if(node !== null) {
		if(found) {
			found.call(this, node);
		}
	} else {
		if(empty) {
			empty.call(this);
		}
	}

	return node;

}

/**
 * Shortcut for document.qsa()
 *
 * @return Element
 */
function qsa(selector, each, empty) {
	return document.qsa(selector, each, empty);
}

/**
 * Shortcut for querySelectorAll()
 *
 * @return NodeList
 */
Node.prototype.qsa = function(selector, each, empty) {

	let nodes = this.querySelectorAll(selector);

	if(nodes.length > 0) {
		if(each) {
			nodes.forEach(each);
		}
	} else {
		if(empty) {
			empty.call(this);
		}
	}

	return nodes;

}

/**
 * Shortcut for document.ref()
 *
 * @return Element
 */
function ref(ref, each, empty, cssSelector) {
	return document.ref(ref, each, empty, cssSelector);
}

/**
 * Lookup for a ref
 *
 * @return NodeList
 */
Node.prototype.ref = function(ref, each, empty, cssSelector) {

	if(cssSelector === undefined) {
		cssSelector = '~=';
	}

	if(['~=', '=', '^=', '$=', '|=', '*='].includes(cssSelector) === false) {
		throw 'Invalid selector';
	}

	return this.qsa('[data-ref'+ cssSelector +'"'+ ref +'"]', each, empty);

}

Node.prototype.renameNode = function(newName) {

    const newNode = this.ownerDocument.createElement(newName);
    Array.from(this.attributes).forEach(attribute => newNode.setAttribute(attribute.localName, attribute.value));
    Array.from(this.childNodes).forEach(childNode => newNode.appendChild(childNode));

    this.parentElement.insertBefore(newNode, this);
    this.parentElement.removeChild(this);

    return newNode;

}

/**
 * Search for first parent node that matches the given selector
 */
Element.prototype.firstParent = function(selector) {

	let currentNode = this;

	while((currentNode = currentNode.parentNode) && currentNode !== document) {

		if(currentNode.matches(selector)) {
			return currentNode;
		}

	}

	return null;

};

/**
 * Search for siblings node that matches the given selector
 */
Element.prototype.firstPreviousSiblingMatches = function(selector) {

	let currentNode = this;

	while(currentNode = currentNode.previousElementSibling) {

		if(currentNode.matches(selector)) {
			return currentNode;
		}

	}

	return null;

};

Element.prototype.firstNextSiblingMatches = function(selector) {

	let currentNode = this;

	while(currentNode = currentNode.nextElementSibling) {

		if(currentNode.matches(selector)) {
			return currentNode;
		}

	}

	return null;

};

Element.prototype.parseScripts = function() {

	if(this.tagName === 'SCRIPT') {
		evalScope(document.body, this.innerHTML);
		return;
	}

	const scripts = this.getElementsByTagName('script');
	let currentLength = scripts.length;

	for(let i = 0; i < currentLength; i++) {

		evalScope(document.body, scripts[i].innerHTML);

		const newLength = scripts.length;

		if(newLength !== currentLength) {
			i = i + newLength - currentLength;
			currentLength = newLength;
		}

	}

};

Element.prototype.parseRender = function() {

	Lime.Scroll.restore(this);
	Lime.Tab.reveal(this.qsa('.tabs-item > .tab-item.selected, .util-filter-options-column > .util-filter-option.is-selected'));

	const render = (node) => {
		if(node.hasAttribute('data-render-timeout')) {
			setTimeout(() => evalScope(node, node.getAttribute('onrender')), parseInt(node.dataset.renderTimeout));
		} else {
			evalScope(node, node.getAttribute('onrender'));
		}
	};

	if(this.hasAttribute('onrender')) {
		render(this);
	}

	this.qsa('[onrender]', function(node) {
		render(node);
	});

	// Après les `onrender`, où `Lime.Tab.restore()` choisit l'onglet que le menu nomme.
	Lime.TabFold.refresh([this.closest('.tabs-h-fold'), ...this.querySelectorAll('.tabs-h-fold')].filter(wrapper => wrapper !== null));

};

Element.prototype.display = function(displayed) {
	if(displayed) {
		this.removeHide();
	} else {
		this.hide();
	}
}

Element.prototype.hide = function() {
	this.classList.add('hide');
}

Element.prototype.removeHide = function() {
	this.classList.remove('hide');
}

Element.prototype.toggle = function() {
	if(this.classList.contains('hide')) {
		this.removeHide();
		return 'removeHide';
	} else {
		this.hide();
		return 'hide';
	}
}

Element.prototype.toggleClass = function(className) {
	if(this.classList.contains(className)) {
		this.classList.remove(className);
	} else {
		this.classList.add(className);
	}
}

Element.prototype.toggleSwitch = function() {
	this.classList.toggle('field-switch-off');
	this.classList.toggle('field-switch-on');
}

Element.prototype.switchOn = function() {
	this.classList.remove('field-switch-off');
	this.classList.add('field-switch-on');
}

Element.prototype.switchOff = function() {
	this.classList.add('field-switch-off');
	this.classList.remove('field-switch-on');
}

Element.prototype.slideUp = function(options = {}) {

	const duration = options.duration || 0.5;
	const done = options.done || function() {};

	this.style.overflowY = 'hidden';
	this.style.maxHeight = this.offsetHeight +'px';
	this.style.transition = 'all '+ duration +'s';

	setTimeout(() => this.style.maxHeight = '0px', 10);
	setTimeout(() => done.call(this), 10 + duration * 1000);

};

Element.prototype.slideDown = function(options = {}) {

	const duration = options.duration || 0.5;
	const done = options.done || function() {};

	this.style.transition = 'all '+ duration +'s';
	this.style.removeProperty('overflow-y');

	setTimeout(() => this.style.maxHeight = 'inherit', 10);
	setTimeout(() => done.call(this), 10 + duration * 1000);

};

Element.prototype.fadeOut = function(options = {}) {

	const duration = options.duration || 0.5;
	const done = options.done || function() {};

	this.style.pointerEvents = 'none';
	this.style.transition = 'all '+ duration +'s';

	setTimeout(() => this.style.opacity = 0, 10);
	setTimeout(() => done.call(this), 10 + duration * 1000);

};

Element.prototype.fadeIn = function(options = {}) {

	const duration = options.duration || 0.5;
	const done = options.done || function() {};

	this.style.pointerEvents = 'initial';
	this.style.transition = 'all '+ duration +'s';

	setTimeout(() => this.style.opacity = 1, 10);
	setTimeout(() => done.call(this), 10 + duration * 1000);

};

Element.prototype.isVisible = function() {
	return (this.offsetWidth > 0 || this.offsetHeight > 0);
};

Element.prototype.isHidden = function() {
	return !this.isVisible();
};

Element.prototype.renderInner = function(html) {

	this.innerHTML = html;

	this.childNodes.forEach(node => {
		if(node instanceof Element) {
			node.parseScripts();
			node.parseRender();
		}
	});

}

Element.prototype.renderOuter = function(html) {

	const document = new DOMParser().parseFromString(html, 'text/html');
	const body = document.body;

	if(
		document.head.childNodes.length > 0 ||
		body.childNodes.length > 1
	) {
		throw "HTML must be formed of only a root node";
	}

	const node = body.firstChild;

	if(node === null) {
		this.remove();
	} else {
		this.parentNode.replaceChild(node, this);

		if(node instanceof Element) {
			node.parseScripts();
			node.parseRender();
		}
	}

	return node;

}

Element.prototype.renderAdjacentHTML = function(where, html) {

	if(html === '') {
		return;
	}

	const template = document.createElement('template');
	template.innerHTML = html.trimStart();

	if(template.content.childNodes.length > 1) {
		throw "HTML must be formed of only a root node";
	}

	const node = template.content.firstChild;

	if(node instanceof Text) {
		this.insertAdjacentText(where, node.data);
	} else {
		this.insertAdjacentElement(where, node);
		node.parseScripts();
		node.parseRender();
	}

}

/**
 * Get all post-* attributes as an object key=>value
 *
 * @returns Object
 */
Element.prototype.post = function(formData) {

	if(formData === undefined) {
		formData = new URLSearchParams();
	}

	for(let i = 0; i < this.attributes.length; i++) {

		let attribute = this.attributes.item(i);

		if(attribute.name.indexOf('post-') === 0) {

			let key = attribute.name.substring(5).replace(/\-([a-z])/gi, function(match, letter) {
				return letter.toUpperCase();
			});

			formData.set(key, attribute.value);
		}

	};

	return formData;

};

Element.prototype.form = function() {

	let data = new FormData(this);

	this.qsa('.editor', node => {

		// Un éditeur émule un champ : dans un fieldset désactivé, il ne poste pas,
		// comme les vrais contrôles que FormData exclut nativement.
		if(node.closest('fieldset[disabled]') !== null) {
			return;
		}

		// Can't save if loaded
		if(node.querySelectorAll('[data-type="progress"]').length > 0) {
			throw node.getAttribute('data-progress');
		}

		data.set(
			node.getAttribute('data-name'),
			node.innerHTML
		);

		Editor.resetInstance('#'+ node.id);

	});

	return data;

};

Array.prototype.unique = function() {
	return [...new Set(this)];
}

NodeList.prototype.filter = function(selector) {

	let newList = [];

	this.forEach(node => {
		if(node.matches(selector)) {
			newList.push(node);
		}
	});

	return newList;

};

/**
 * Create a delegated event listener
 */
Node.prototype.delegateEventListener = function(type, selector, listener, options) {

	options = options || {capture: true};

	const delegateTarget = (e) => {

		const target = e.target;

		if(target !== document && target.matches(selector)) {
			e.delegateTarget = target;
			listener.call(target, e);
		}

	}

	const delegateTargetAndParents = (e) => {

		for(let target = e.target; target && target != this; target = target.parentNode) {

			if(target.matches(selector)) {
				e.delegateTarget = target;
				listener.call(target, e);
			}

		}

	}

	if(type === 'mouseenter' || type === 'mouseleave' || type === 'mouseover' || type === 'mouseout') {
		return this.addEventListener(type, delegateTarget, options);
	} else {
		return this.addEventListener(type, delegateTargetAndParents, options);
	}

};

/**
 * Event dispatched on a new page
 */
Document.prototype.ready = function(listener) {
	if(document.readyState === 'interactive' || document.readyState === 'complete') {
		listener();
	} else {
		this.addEventListener("DOMContentLoaded", listener, {once: true});
	}
};

function startWaiter(target) {

	if(
		!target ||
		!target.dataset ||
		target.dataset.waiter === undefined
	) {
		return () => {

		};
	}

	let original = null;

	if(target.dataset.waiter !== '') {
		original = target.innerHTML;
		target.innerHTML = target.dataset.waiter;
	}

	target.matches('[type="submit"]') ?
		target.classList.add('disabled') :
		target.classList.add('click-waiting');

	if(target.dataset.waiterTimeout) {
		setTimeout(() => target?.classList.remove('click-waiting'), parseInt(target.dataset.waiterTimeout) * 1000);
	}

	return () => {

		if(original !== null) {
			target.innerHTML = original;
		}

		target.matches('[type="submit"]') ?
			target.classList.remove('disabled') :
			target.classList.remove('click-waiting');


	};

}

document.delegateEventListener('click', '[data-confirm]', function(e) {

	if(confirm(this.getAttribute('data-confirm')) === false) {

		e.preventDefault();
		e.stopImmediatePropagation();

	}

});

document.delegateEventListener('click', '[data-alert]', function(e) {

	alert(this.getAttribute('data-alert'));


});

URLSearchParams.prototype.importObject = function(start) {

	const browse = (object, prefix) => {

		Object.entries(object).forEach(([key, value]) => {
			const newKey = prefix + (prefix === '' ? key : '['+ key +']');
			if(value instanceof Object) {
				browse(value, newKey)
			} else {
				this.append(newKey, value);
			}
		});

	}
	browse(start, '');

}

/*
 * Object handling
 * Object.prototype.... causes too much problems
 */
function objectMerge(...arguments) {

	// create a new object
	let target = {};

	// deep merge the object into the target object
	const merger = (obj) => {
		for (let prop in obj) {
			if (obj.hasOwnProperty(prop)) {
				if (Object.prototype.toString.call(obj[prop]) === '[object Object]') {
					// if the property is a nested object
					target[prop] = objectMerge(target[prop], obj[prop]);
				} else {
					// for regular property
					target[prop] = obj[prop];
				}
			}
		}
	};

	// iterate through all objects and
	// deep merge them with target
	for (let i = 0; i < arguments.length; i++) {
		merger(arguments[i]);
	}

	return target;
};

/*
 * String handling
 */

String.prototype.encode = function() {

	const map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;'
	};

	return this.replace(/[&<>"]/g, function(m) {
		return map[m];
	});

}

String.prototype.attr = function(value) {
	let encodedValue = value;
	if(typeof value === 'string') {
		encodedValue = value.encode();
	}
	return this +'="'+ encodedValue +'"';
}

String.prototype.toFqn = function() {

	return this
		.normalize("NFD")
		.replace(/\p{Diacritic}/gu, '')
		.replace(/\s+/g, '-')
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^\-+|\-+$/g, '');

}

String.prototype.removeArgument = function(name) {

	let location = this;

	const regex = new RegExp('([\&\?])'+ name.replace('[', '\\[').replace(']', '\\]') +'(=[a-z0-9/,\.\%\:\\-\\\\+\{\}]*)*[&]?', 'gi');
	location = location.replace(regex, '$1');
	location = location.replace('?&', '?');
	location = location.replace('&&', '&');

	if(
		location.charAt(location.length - 1) === '?' ||
		location.charAt(location.length - 1) === '&'
	) {
		location = location.substring(0, location.length - 1);
	}

	return location;

};

String.prototype.setArgument = function(name, value) {

	let location = this;

	const regex = new RegExp('([\&\?])'+ name +'(=[^\&]*|$|\&)', 'i');

	const newArgument = (typeof value !== 'undefined') ? name +'='+ encodeURIComponent(value) : name;

	if(location.match(regex)) {
		location = location.replace(regex, '$1'+ newArgument);
	} else {
		location = location + (location .indexOf('?') === -1 ? '?' : '&') + newArgument;
	}

	return location;

};

/*
 * History handling
 */
history.removeArgument = function(name, state) {

	const location = document.location.href.removeArgument(name);

	if(document.location.href === document.body.dataset.context) {
		document.body.dataset.context = location;
	}

	if(state === false) {
		Lime.History.pushState(location);
	} else {
		Lime.History.replaceState(location);
	}

}

history.updateArgument = function(name, value, state) {

	const location = document.location.href.setArgument(name, value);

	if(document.location.href === document.body.dataset.context) {
		document.body.dataset.context = location;
	}

	if(state === 'push') {
		Lime.History.pushState(location);
	} else {
		Lime.History.replaceState(location);
	}

	return location;

}

let isMouseDown = false;

document.addEventListener('mousedown', function() {
	isMouseDown = true;
})

document.addEventListener('mouseup', function() {
	isMouseDown = false;
});

let isTouchDown = false;

document.addEventListener('touchstart', function() {
	isTouchDown = true;
})

document.addEventListener('touchend', function() {
	isTouchDown = false;
});

const Lime = {

	counter: 1,
	zIndex: 1100,

	getZIndex: function() {
		Lime.zIndex += 10;
		return Lime.zIndex;
	}

};

Lime.Pagination = class {

	static form(url, target, ajaxClass) {

		url = url.replace('{page}', parseInt(target.qs('[name="page"]').value) - 1);

		if(ajaxClass) {

			new (eval(ajaxClass))(target)
				.url(url)
				.method('get')
				.fetch();

		} else {

			new Ajax.Navigation()
				.url(url)
				.fetch();

		}

	}

}

Lime.Alert = class {

	static showStaticErrors(errors) {

		let message = Lime.Alert.getInlineErrors(errors);

		if(message !== '') {
			Lime.Alert.showStaticError(message);
		}

	};

	static getInlineErrors(errors) {

		let messages = [];

		errors.forEach(([, message]) => {

			if(message !== null) {
				messages[messages.length] = message;
			}

		});

		switch(messages.length) {

			case 0 :
				return '';

			case 1 :
				return messages[0];

			default :

				let h = '<ul>';

				messages.forEach(message => {
					h += '<li>'+ message +'</li>';
				});

				h += '</ul>';

				return h;

		}

	};

	// Un bandeau d'erreur reste jusqu'à sa croix, une navigation, la fermeture d'un panneau ou une
	// réponse lisible à une requête portée par la page entière : un refus qui se ferme seul laisse
	// croire que le geste n'a rien fait.
	static showStaticError(message) {

		if(message === '') {
			return false;
		}

		if(document.getElementById('alert-danger') === null) {
			document.body.insertAdjacentHTML('beforeend', '<div id="alert-danger" class="util-block-danger util-block-sticked"></div>');
		}

		document.getElementById('alert-danger').innerHTML = message + '<a onclick="Lime.Alert.hideStaticErrors()" class="util-block-close">'+ Lime.Asset.icon('x-circle') +'</a>';
		document.getElementById('alert-danger').style.display = 'block';

		setTimeout(() => history.removeArgument('error'), 2500);

	};

	static hideStaticErrors() {
		this.hideStaticBox('#alert-danger');
	};

	static showStaticSuccess(message) {

		if(message === '') {
			return false;
		}

		if(document.getElementById('alert-success') === null) {
			document.body.insertAdjacentHTML('beforeend', '<div id="alert-success" class="util-block-success util-block-sticked"></div>');
		}

		document.getElementById('alert-success').innerHTML = message + '<a onclick="Lime.Alert.hideStaticSuccess()" class="util-block-close">'+ Lime.Asset.icon('x-circle') +'</a>';
		document.getElementById('alert-success').style.display = 'block';

		setTimeout(() => history.removeArgument('success'), 2500);
		setTimeout(() => this.hideStaticSuccess(), 5000);

	};

	static hideStaticSuccess() {
		this.hideStaticBox('#alert-success');
	};

	/*
	 * `from` est un sélecteur : la pastille part du premier élément visible qu'il désigne. Il se mesure à
	 * l'appel, donc une instruction `toast` se pose avant celles qui remplacent ce qu'elle désigne.
	 * `undo` porte le libellé du lien et ses attributs, que le délégué de l'appelant traite. Au clic, la
	 * pastille se fige et reste en place : l'appelant la retire à la réponse (`hideToast()`), sa requête
	 * portant sur un lien encore dans le document. Un lien `data-ajax` n'a pas besoin de délégué, son
	 * délégué commun la retire (`ajax.js`).
	 */
	static showToast({message, detail = null, icon = null, undo = null, from = null, duration = 6000}) {

		const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
		const origin = (from === null || reduced) ? null : [...qsa(from)]
			.map(node => node.getBoundingClientRect())
			.find(rect => rect.width > 0 && rect.height > 0) ?? null;

		let stack = qs('body > .util-toast-stack');

		if(stack === null) {
			document.body.insertAdjacentHTML('beforeend', '<div class="util-toast-stack"></div>');
			stack = qs('body > .util-toast-stack');
		}

		const toast = document.createElement('div');
		toast.className = 'util-toast';

		let h = '';

		if(icon !== null) {
			h += '<svg class="util-toast-icon"><use xlink:href="'+ icon +'" href="'+ icon +'"></use></svg>';
		}

		h += '<span class="util-toast-text"><span>'+ message +'</span>'+ (detail === null ? '' : '<small>'+ detail +'</small>') +'</span>';

		toast.innerHTML = h;

		if(undo !== null) {

			const link = document.createElement('a');

			Object.entries(undo.attributes ?? {}).forEach(([name, value]) => link.setAttribute(name, value));
			link.textContent = undo.label;
			toast.append(link);

		}

		stack.append(toast);

		let timer = null;

		if(reduced === false && undo !== null) {

			const bar = document.createElement('span');
			bar.className = 'util-toast-timer';
			toast.append(bar);

			timer = bar.animate([{transform: 'scaleX(1)'}, {transform: 'scaleX(0)'}], {duration, fill: 'forwards'});

		}

		// getBoundingClientRect() inclut la transformation d'une animation en cours : la place d'arrivée se lit avant d'animer la pastille.
		const target = toast.getBoundingClientRect();

		if(reduced) {
			toast.animate([{opacity: 0}, {opacity: 1}], {duration: 150, easing: 'ease-out'});
		} else if(origin !== null) {
			toast.animate([
				{transform: 'translate('+ (origin.left - target.left) +'px, '+ (origin.top - target.top) +'px)', opacity: 0.6},
				{transform: 'none', opacity: 1}
			], {duration: 450, easing: 'cubic-bezier(.3, .7, .3, 1)'});
		} else {
			toast.animate([{transform: 'scale(.6)', opacity: 0}, {transform: 'scale(1.04)', opacity: 1, offset: .6}, {transform: 'none', opacity: 1}], {duration: 320, easing: 'ease-out'});
		}

		let remaining = duration;
		let start = Date.now();
		let paused = 0;

		const pause = () => {
			if(paused++ === 0) {
				remaining -= Date.now() - start;
				timer?.pause();
			}
		};

		const resume = () => {
			if(--paused === 0) {
				start = Date.now();
				timer?.play();
			}
		};

		toast.addEventListener('mouseenter', pause);
		toast.addEventListener('mouseleave', resume);
		toast.addEventListener('focusin', pause);
		toast.addEventListener('focusout', resume);

		toast.qs('.util-toast-text + a')?.addEventListener('click', () => {
			pause();
			toast.toastHeld = true;
		});

		const watch = () => {

			if(toast.isConnected === false || toast.toastHeld === true) {
				return;
			}

			if(paused === 0 && Date.now() - start >= remaining) {
				Lime.Alert.hideToast(toast, true);
			} else {
				setTimeout(watch, 100);
			}

		};

		setTimeout(watch, 100);

		return toast;

	};

	static hideToast(toast, done) {

		if(toast.toastLeaving === true) {
			return;
		}

		toast.toastLeaving = true;

		const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

		const leave = () => toast.animate(
			reduced ? [{opacity: 1}, {opacity: 0}] : [{opacity: 1, transform: 'none'}, {opacity: 0, transform: 'scale(.4)'}],
			{duration: reduced ? 150 : 260, easing: 'ease-in', fill: 'forwards'}
		).finished.then(() => toast.remove());

		if(done && reduced === false) {

			toast.classList.add('is-done');
			toast.innerHTML = Lime.Asset.icon('check-lg');
			toast.firstElementChild.classList.add('util-toast-icon');

			toast.animate([{transform: 'scale(1)'}, {transform: 'scale(1.15)', offset: .3}, {transform: 'scale(1)'}], {duration: 400, easing: 'ease-out'})
				.finished
				.then(() => new Promise(resolve => setTimeout(resolve, 500)))
				.then(leave);

		} else {
			leave();
		}

	};

	static hideStaticBox(id) {

		qs(id, node => {

			node.classList.add('util-block-remove');
			setTimeout(() => node.remove(), 500);

		});

	};

	static hideErrors(selector) {

		// Hide previous errors
		selector.qsa('.util-block-danger', node => node.remove());

		selector.qsa('.form-error', node => node.classList.remove('form-error'));
		selector.qsa('.form-error-wrapper', node => node.classList.remove('form-error-wrapper'));
		selector.qsa('p.form-error-message', node => node.remove());

	};

	static showErrors(context, errors) {

		if(context !== null) {
			Lime.Alert.hideErrors(context);
		}

		if(
			typeof errors === 'undefined' ||
			errors.length === 0
		) {
			return;
		}

		let remainingErrors = [];

		errors.forEach(error => {

			let message, errorName, wrapper;

			[errorName, message, wrapper] = error;

			// No custom message
			if(errorName === message) {
				message = null;
			}

			// No wrapper specified
			if(wrapper === null) {

				// If the error is linked to a field, we take the first part of error name
				if(errorName.indexOf('.') !== -1) {
					wrapper = errorName.substring(0, errorName.indexOf('.'));
				}
				// This is a generic error (not linked to a field)
				else {
					wrapper = null;
				}

			}

			if(wrapper === null) {

				remainingErrors.push(error);

			} else {


				if(context !== null) {

					context.qsa('[data-wrapper~="'+ wrapper +'"]', node => {

						node.classList.add('form-error-wrapper');

						if(message !== null) {
							node.qs('.form-control-label:first-child', nodeLabel => nodeLabel.insertAdjacentHTML('beforeend', '<p class="form-error-message">'+ message +'</p>'), () => node.insertAdjacentHTML('beforeend', '<p class="form-error-message">'+ message +'</p>'));
						}


					}, () => {
						remainingErrors.push(error);
					});

				} else {
					remainingErrors.push(error);
				}

			}

		});

		if(remainingErrors.length > 0) {
			Lime.Alert.showStaticErrors(remainingErrors);
		}

		if(context !== null) {
			context.classList.add('form-error');
		}

	};

}

Lime.Panel = class {

	/*
	 * Create a panel on-the-fly
	 */
	static create(id, options) {

		if(document.getElementById(id) === null) {

			const attributes = options?.attributes || {};
			attributes.class = 'panel' + (attributes.class ? ' '+ attributes.class : '');

			let h = '';

			h += '<div class="panel" id="'+ id +'">';
				h += '<div class="panel-backdrop"></div>';
				h += options.dialogOpen ?? '<div class="panel-dialog">';
					h += '<div class="panel-header"></div>';
					h += '<div class="panel-body"></div>';
					h += '<div class="panel-footer"></div>';
				h += options.dialogClose ?? '</div>';
			h += '</div>';

			document.querySelector('body').insertAdjacentHTML('beforeend', h);

			const node = document.getElementById(id);

			if(options.url) {
				node.dataset.url = options.url;
			}

			Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));

		}

		return document.getElementById(id);

	};

	static render(data) {

		if(data.id === undefined) {
			throw 'data.id is missing';
		}

		const panelId = data.id;
		let panel;

		if(document.getElementById(panelId) === null) {

			panel = this.create(panelId, data);

			panel.dataset.new = 'true';

		} else {

			panel = document.getElementById(panelId);
			panel.classList.remove('closing');
			panel.classList.remove('closing-last');
			panel.dataset.new = 'false';

			this.dialog(panel, data);

		}

		// Affiche le panel en premier afin que les éléments affichés ultérieurement aient immédiatement une taille
		Lime.Panel.show(panel);
		Lime.Panel.paint(panel, data);

		return panel;

	};

	static dialog(panel, data) {

		const template = document.createElement('template');
		template.innerHTML = (data.dialogOpen ?? '<div class="panel-dialog">') + (data.dialogClose ?? '</div>');

		const dialogNew = template.content.firstElementChild;
		const dialog = panel.qs('.panel-backdrop').nextElementSibling;

		// Ajax.Query marque le formulaire envoyé (data-lock, action réécrite) : seul le changement de
		// balise dit que le serveur envoie une autre enveloppe
		if(dialog.tagName === dialogNew.tagName) {
			return;
		}

		dialogNew.append(...dialog.childNodes);
		dialog.replaceWith(dialogNew);

	};

	static paint(panel, data) {

		panel.dispatchEvent(new CustomEvent('panelBeforePaint'));

		const title = data.title || '';
		const back = data.back || null;
		const subTitle = data.subTitle || '';
		const body = data.body || '';
		const header = data.header || '';
		const footer = data.footer || '';

		Lime.Panel.header(panel, back, title, subTitle, header);
		Lime.Panel.body(panel, body);
		Lime.Panel.footer(panel, footer);

		panel.dispatchEvent(new CustomEvent('panelAfterPaint'));

	};

	static layer(panel, requestedWith) {

		switch(requestedWith) {

			case 'html' :
				panel.dataset.context = 'new';
				Lime.History.forceRefreshOnBack = true;
				break;

			case 'navigation' :

				panel.dataset.context = 'layer';
				Lime.History.pushLayer(panel, (history) => this.internalClose(panel, history), panel.dataset.url, document.location.href);

				break;

			case 'query' :
				break;

		}

	};

	static show(panel) {

		panel.dispatchEvent(new CustomEvent('panelBeforeShow'));

		Lime.Dropdown.purge('around');

		if(document.body.classList.contains('panel-open') === false) {
			document.body.classList.add('panel-open');
		}

		panel.classList.add('open');

		panel.style.zIndex = Lime.getZIndex();

		panel.dispatchEvent(new CustomEvent('panelAfterShow'));

	};

	static purge() {

		qsa('.panel', panel => panel.remove());
		document.body.classList.remove('panel-open');

	}

	static close(panel) {

		switch(panel.dataset.context) {

			case 'new' :
				Lime.History.forceRefreshOnBack = true;
				history.go(-1);
				break;

			case 'layer' :
				Lime.History.popLayer(panel);
				break;

			default :
				this.internalClose(panel);
				break;


		}

	};

	static closeLast() {

		const nodes = qsa('.panel.open:not(.closing)');

		if(nodes.length > 0) {

			const node = nodes[nodes.length - 1];
			this.close(node);

			return true;

		} else {
			return false;
		}

	};

	static closeEscape(e) {

		if(e.key !== 'Escape') {
			return;
		}

		const nodes = qsa('.panel.open:not(.closing)');

		if(
			nodes.length > 0 &&
			nodes[nodes.length - 1].dataset.close === 'never'
		) {
			return;
		}

		this.closeLast();

	};

	static internalClose(panel, history = false) {

		if(panel.classList.contains('closing')) {
			return false;
		}

		// Toute fermeture passe ici, le retour arrière du navigateur compris. Un bandeau d'erreur ne se
		// ferme pas seul : levé dans le panneau, il part avec lui.
		Lime.Alert.hideStaticErrors();

		// Reload panel
		if(
			panel.dataset.close === 'reload' ||
			panel.dataset.close === 'reloadIgnoreCascade' ||
			(history && panel.dataset.close === 'reloadOnHistory')
		) {

			const query = new Ajax.Query(panel);

			// Restauration du scroll
			Lime.History.saveScroll(Lime.History.currentTimestamp);
			query.headers.set('x-requested-history', Lime.History.currentTimestamp);

			query
				.method('get')
				.waiter(panel.style.zIndex - 1, 333)
				.url(location.href)
				.fetch();

			if(panel.dataset.close !== 'reloadIgnoreCascade') {
				qsa('.panel[data-close="reloadOnCascade"]', panel => panel.dataset.close = 'reload');
			}

		}

		panel.classList.add('closing');
		panel.dispatchEvent(new CustomEvent('panelClose'));

		// No more panel open
		if(qsa('.panel.open:not(.closing)').length === 0) {
			panel.classList.add('closing-last');
			document.body.classList.remove('panel-open');
		}

		// La sortie de .panel.closing (lime.css, juicinessAi.css) ne doit pas durer plus que ce délai, sinon le panneau est retiré avant la fin.
		setTimeout(() => {

			if(panel.classList.contains('closing')) { // May have been reopen
				panel.dispatchEvent(new CustomEvent('panelAfterClose'));
				panel.remove();
			}

		}, 500);

		return true;
			
	};

	static header(panel, back, title, subTitle, header) {

		let h = '';
		h += '<div class="panel-header-content">';
			if(header) {
				h += header;
			} else {
				if(back || title || subTitle) {
					if(back) {
						h += '<div class="panel-back-wrapper">';
							h += '<a href="'+ back +'" class="panel-back">'+ Lime.Asset.icon('arrow-left-short') +'</a>';
							h += '<h2 class="panel-title">'+ title +'</h2>';
						h += '</div>';
					} else {
						h += '<h2 class="panel-title">'+ title +'</h2>';
					}
					if(subTitle) {
						h += subTitle;
					}
				}
			}
		h += '</div>';

		if(panel.dataset.close !== 'never') {
			h += this.removeButton();
		}

		panel.qs('.panel-header').renderInner(h);
		return this;

	};

	static removeButton() {

		let h = '<a class="panel-close-desktop" onclick="Lime.Panel.closeLast()">'+ Lime.Asset.icon('x') +'</a>';
		h += '<a class="panel-close-mobile" onclick="Lime.Panel.closeLast()">'+ Lime.Asset.icon('arrow-left-short') +'</a>';

		return h;

	};

	static body(panel, body) {
		panel.qs('.panel-body').renderInner(body);
		return this;
	};

	static footer(panel, footer) {
		panel.qs('.panel-footer').renderInner(footer);
		return this;
	};

}

Lime.Tab = class {

	static select(target, remember = true) {

		const tab = target.dataset.tab;
		const wrapper = target.firstParent('.tabs');

		wrapper.qsa('[data-tab]', node => {

			if(node.firstParent('.tabs') !== wrapper) {
				return;
			}

			if(node.getAttribute('data-tab') === tab) {
				node.classList.add('selected');
			} else {
				node.classList.remove('selected');
			}

		});

		if(wrapper.classList.contains('tabs-exclusive')) {

			wrapper.qsa('.tab-panel', panel => {

				if(panel.firstParent('.tabs') !== wrapper) {
					return;
				}

				panel.qsa('fieldset', field => field.disabled = (panel.classList.contains('selected') === false));

			});

		}

		if(wrapper.id && remember) {
			localStorage.setItem('tabs-'+ wrapper.id +'-selected', tab);
		}

		if(target.classList.contains('tab-item')) {
			this.reveal([target]);
		}

	}

	// `scrollIntoView()` ferait aussi défiler la page : seule la rangée défile ici. Elle sert aussi à la
	// colonne de filtre, qui devient une rangée défilante sur un écran étroit ; en colonne, rien ne
	// déborde en largeur et la garde ci-dessous la laisse.
	static reveal(items) {

		const moves = [];

		items.forEach(item => {

			const row = item.parentElement;

			if(row.scrollWidth <= row.clientWidth) {
				return;
			}

			const rowBox = row.getBoundingClientRect();
			const itemBox = item.getBoundingClientRect();

			if(itemBox.left < rowBox.left) {
				moves.push([row, row.scrollLeft + itemBox.left - rowBox.left]);
			} else if(itemBox.right > rowBox.left + row.clientWidth) {
				moves.push([row, row.scrollLeft + itemBox.right - rowBox.left - row.clientWidth]);
			}

		});

		moves.forEach(([row, scrollLeft]) => row.scrollLeft = scrollLeft);

	}

	static restore(wrapper, defaultTab, forceTab) {

		let tabName;

		if(wrapper.id) {
			tabName = forceTab || localStorage.getItem('tabs-'+ wrapper.id +'-selected') || defaultTab;
		} else {
			tabName = forceTab || defaultTab;
		}
		
		wrapper.qs(
			'.tab-item[data-tab="'+ tabName +'"]',
			node => this.select(node),
			() => this.select(wrapper.qs('.tab-item:first-child'), false)
		);

		history.removeArgument('tab');

	}

}

/*
 * Une rangée repliée ne déborde plus : sa largeur dépliée reste dans `data-fold-width` pour savoir quand
 * la déplier. Un onglet à `onclick` est rejoué sur l'original, la liste ouverte vivant sous `body`, hors
 * de la rangée que `Lime.Tab.select()` remonte.
 */
Lime.TabFold = class {

	static observer = new ResizeObserver(entries => this.refresh(entries.map(entry => entry.target.parentElement)));

	static refresh(wrappers) {

		// Une rangée remplacée dans une rangée repliée n'a pas encore de largeur dépliée : elle se déplie pour la donner.
		wrappers.forEach(wrapper => {
			if(wrapper.querySelector(':scope > .tabs-item:not([data-fold-width])') !== null) {
				wrapper.classList.remove('tabs-h-folded');
			}
		});

		const folds = [];

		wrappers.forEach(wrapper => {

			const row = wrapper.querySelector(':scope > .tabs-item');

			if(row === null) {
				return;
			}

			// Une rangée encore masquée, dans un panneau qui s'ouvre, se mesure quand elle prend sa largeur.
			if(row.clientWidth === 0) {
				this.observer.observe(row);
				return;
			}

			const folded = wrapper.classList.contains('tabs-h-folded');
			const width = folded ? parseInt(row.dataset.foldWidth) : row.scrollWidth;

			folds.push([wrapper, row, width, width > row.clientWidth, this.getHidden(row)]);

		});

		folds.forEach(([wrapper, row, width, fold, hidden]) => {

			row.dataset.foldWidth = width;
			wrapper.classList.toggle('tabs-h-folded', fold);

			this.build(row, hidden);
			this.observer.observe(row);

		});

	}

	// Ce que la page masque dans un onglet, comme une marque d'erreur qu'elle n'allume que sur la rangée,
	// reste masqué dans la liste, qui vit sous `body` hors de portée des règles de la page. Lu avant toute
	// écriture : la lecture d'un style calculé après un changement de classe force un recalcul.
	static getHidden(row) {
		return Array.from(this.getItems(row), item => Array.from(item.querySelectorAll('*'), node => getComputedStyle(node).display === 'none'));
	}

	// Un onglet qui ouvre son propre menu reste dans la rangée : replié, il ferait un menu dans un menu.
	static getItems(row) {
		return row.querySelectorAll(':scope > .tab-item:not(.tab-item-menu):not([data-dropdown])');
	}

	static build(row, hidden) {

		const items = Array.from(this.getItems(row));

		if(items.length === 0) {
			return;
		}

		const selected = items.find(item => item.classList.contains('selected')) ?? null;

		let menu = row.querySelector(':scope > .tab-item-menu');

		if(menu === null) {

			menu = document.createElement('a');
			menu.className = 'tab-item tab-item-menu';
			menu.setAttribute('data-dropdown', 'bottom-start');

			row.list = document.createElement('div');
			row.list.className = 'dropdown-list tab-item-menu-list';

			// Le repli prend la place des onglets qu'il range, avant ceux qui restent.
			const kept = row.querySelector(':scope > .tab-item[data-dropdown]');
			row.insertBefore(menu, kept);
			row.insertBefore(row.list, kept);

		}

		const overdue = items
			.filter(item => item !== selected)
			.reduce((sum, item) => sum + (parseInt(item.querySelector('.util-counter-danger')?.textContent) || 0), 0);

		// Une rangée de liens peut n'avoir aucun onglet choisi : le menu ne nomme alors rien.
		let label = document.createElement('span');

		if(selected !== null) {
			label = this.flatten(selected.cloneNode(true));
			label.querySelectorAll('.util-counter-danger').forEach(counter => counter.remove());
		}

		// Le menu tient lieu de l'onglet choisi et en prend les classes, pour que les règles que la page pose
		// sur cet onglet, comme la couleur d'un journal, le suivent.
		menu.className = 'tab-item tab-item-menu';

		if(selected !== null) {
			menu.classList.add(...Array.from(selected.classList).filter(name => name !== 'tab-item'));
		}

		menu.innerHTML = Lime.Asset.icon('list') +
			'<span class="tab-item-menu-label">'+ label.innerHTML +'</span>' +
			'<span class="util-counter util-counter-danger">'+ (overdue > 0 ? overdue : '') +'</span>' +
			'<span class="tab-item-caret">'+ Lime.Asset.icon('caret-down-fill') +'</span>';

		row.list.classList.toggle('dropdown-list-2', items.length > 10);
		row.list.replaceChildren(...items.map((item, position) => {

			const entry = this.flatten(item.cloneNode(true));

			Array.from(entry.querySelectorAll('*'))
				.filter((node, index) => hidden[position][index])
				.forEach(node => node.remove());

			// Les classes de la page sont écrites pour la rangée, sur fond clair : sur le fond sombre de la liste,
			// la couleur d'un journal rendait l'entrée choisie illisible.
			entry.className = item.classList.contains('selected') ? 'dropdown-item selected' : 'dropdown-item';
			entry.removeAttribute('id');

			if(entry.hasAttribute('onclick')) {
				entry.removeAttribute('onclick');
				entry.tabFoldItem = item;
			}

			return entry;

		}));

	}

	// Le menu et la liste tiennent sur une ligne, quelle que soit la mise en page de l'onglet. Une icône ou
	// une vignette ne se nomme que par son `title`, qu'aucun des deux ne montre : il s'ajoute au visuel.
	static flatten(node) {

		node.querySelectorAll('br').forEach(br => br.replaceWith(' '));
		node.querySelectorAll('.text-center').forEach(block => block.classList.remove('text-center'));

		if(node.title !== '' && node.textContent.includes(node.title) === false) {
			node.append(' '+ node.title);
		}

		return node;

	}

}

document.delegateEventListener('click', '.tab-item-menu-list .dropdown-item', function(e) {

	if(this.tabFoldItem !== undefined) {
		e.preventDefault();
		this.tabFoldItem.click();
	}

});

// En bulle, après l'`onclick` qui sélectionne l'onglet : le délégué écoute en capture par défaut.
document.delegateEventListener('click', '.tabs-h-fold > .tabs-item > .tab-item:not(.tab-item-menu)', function() {
	Lime.TabFold.refresh([this.firstParent('.tabs-h-fold')]);
}, {capture: false});

Lime.Dropdown = class {

	static mutationObserver = {};
	static clickListener = {};

	// La part de la fenêtre que l'utilisateur voit, en pixels depuis son haut. C'est la fenêtre entière,
	// sauf dans une iframe agrandie à la hauteur de son contenu : elle ne défile jamais et déborde de
	// l'écran, et seule la page qui l'intègre sait ce qu'elle en montre. Elle le lui dit ici.
	static visibleArea = null;

	static getVisibleArea() {
		return this.visibleArea ?? {top: 0, height: window.innerHeight};
	}

	static toggle(button, position) {

		// Fermeture du dropdown actuellement ouvert
		if(position === 'close') {

			const button = qs('[data-dropdown-display]');

			if(button !== null) {
				this.close(button);
			}

		} else {

			if(button.hasAttribute('data-dropdown-display')) {
				this.close(button);
			} else {
				this.open(button, position);
			}

		}

	};

	static open(button, position) {

		if(button.id === '') {
			button.id = 'dropdown-'+ ++Lime.counter;
		}

		const list = Lime.Dropdown.getListFromButton(button);

		this.purge();

		list.classList.add('dropdown-list-open');

		if(list.dataset.dropdownBackground !== 'no') {

			if(
				button.classList.contains('btn-primary') ||
				button.classList.contains('btn-primary-light') ||
				button.classList.contains('btn-outline-primary') ||
				button.classList.contains('btn-color-primary')
			) {
				list.classList.add('bg-primary');
			}

			if(
				button.classList.contains('btn-secondary') ||
				button.classList.contains('btn-secondary-light') ||
				button.classList.contains('btn-outline-secondary') ||
				button.classList.contains('btn-color-secondary')
			) {
				list.classList.add('bg-secondary');
			}

		}

		list.insertAdjacentHTML('beforebegin', '<div data-dropdown-id="'+ button.dataset.dropdownId +'-placeholder" class="dropdown-placeholder"></div>');
		document.body.insertAdjacentElement('beforeend', list);

		button.dispatchEvent(new CustomEvent('dropdownBeforeShow', {
			detail: {button: button, list: list}
		}));

		if(this.isFullscreen(list)) {
			this.openFullscreen(button, list);
		} else {
			this.openAround(button, position, list);
		}

		button.dispatchEvent(new CustomEvent('dropdownAfterShow', {
			detail: {button: button, list: list}
		}));

		this.startCloseListener(button);

	};

	static isFullscreen(list) {

		// dropdown-list-minimalist ouvre en plein écran à toute largeur, au rang du tactile, alors que son
		// nom ne dit que l'aplat qu'elle retire (lime.css). Un panneau sans aplat qui reste près de son
		// bouton prend dropdown-list-unstyled, que ce test ne lit pas.
		if(
			isTouch() || // Mobile
			list.classList.contains('dropdown-list-minimalist')
		) {
			return true;
		} else {

			const windowHeight = window.innerHeight;
			const windowWidth = window.innerWidth;

			if(innerWidth >= 1200) {
				return false;
			}

			// Fullscreen if height is too high
			const listBounding = list.getBoundingClientRect();
			const listHeight = listBounding.height

			return (listHeight > windowHeight - 200);

		}

	};

	static openFullscreen(button, list) {

		Lime.History.pushLayer(button, () => this.internalClose(button), document.location.href, null);

		document.body.classList.add('dropdown-fullscreen-open');

		button.setAttribute('data-dropdown-display', 'fullscreen');

		const windowWidth = window.innerWidth;
		const visibleArea = this.getVisibleArea();

		list.style.position = 'absolute';
		list.style.maxHeight = (visibleArea.height - 3 * rem()) +'px';

		// Laisse le temps pour recalculer la taille de l'élément
		setTimeout(() => {

			const listBounding = list.getBoundingClientRect();

			const positionX = (windowWidth - listBounding.width) / 2;
			const positionY = visibleArea.top + (visibleArea.height - listBounding.height) / 2;

			const translateX = positionX - listBounding.left;
			const translateY = positionY - listBounding.top;

			list.style.transform = 'translate('+ translateX +'px, '+ translateY +'px)';
			list.insertAdjacentHTML('afterend', '<div data-dropdown-id="'+ button.dataset.dropdownId +'-backdrop" class="dropdown-backdrop" style="z-index: '+ Lime.getZIndex() +'"></div>');
			list.style.zIndex = Lime.getZIndex();

		}, 5)

	};

	static isOpen(button) {
		return button.dataset.dropdownDisplay !== undefined;
	}

	static openAround(button, position, list) {

		button.setAttribute('data-dropdown-display', 'around');

		const isTop = position.startsWith('top');

		let offsetX = (button.dataset.dropdownOffsetX === undefined) ? 5 : parseFloat(button.dataset.dropdownOffsetX) * rem();
		let offsetY = (button.dataset.dropdownOffsetY === undefined) ? (isTop ? -5 : 5) : parseFloat(button.dataset.dropdownOffsetY) * rem();

		button.stick = new Lime.Stick(
			button,
			list,
			position,
			{
				x: offsetX,
				y: offsetY
			}
		);

		list.style.zIndex = Lime.getZIndex();
		list.classList.add(isTop ? 'dropdown-list-top' : 'dropdown-list-bottom');

	};

	static startCloseListener(button) {

		// Si on clique quelque part sauf sur le dropdown
		// Eviter que l'événement soit pris dans le clic en cours
		this.clickListener[button.id] = (e) => {

			if(
				isTouch() === false &&
				button.dataset.dropdownHover === 'true'
			) {
				return;
			}

			function keep(target) {
				return target.dataset.dropdownKeep !== undefined;
			}

			let target;
			for(target = e.target; target instanceof Element && keep(target) === false; target = target.parentNode) ;

			if(
				target instanceof Element === false ||
				keep(target) === false
			) {
				this.close(button);
			} else {
				watch();
			}

		};

		const watch = () => setTimeout(() => document.addEventListener('click', this.clickListener[button.id], {once: true}), 0);

		watch();

		// Si le bouton est supprimé en dur
		this.mutationObserver[button.id] = new MutationObserver(mutations => {

			if(document.body.contains(button)) {
				return;
			}

			const list = Lime.Dropdown.getListFromButton(button);

			if(this.isFullscreen(list)) { // Mobile

				Lime.History.getElementsOfLayers()
					.filter(button => button.hasAttribute('data-dropdown-display'))
					.forEach(button => {

						if(document.body.contains(button) === false) {
							this.close(button);
						}

					});

			} else {

				this.close(button);

			}

		});

		// pass in the target node, as well as the observer options
		this.mutationObserver[button.id].observe(document.body, {
			childList: true,
			subtree: true
		});

	};

	static purge(type = null) {

		qsa(
			type === null ?
				'[data-dropdown-display]' :
				'[data-dropdown-display="'+ type +'"]',
			button => this.close(button)
		);

	};

	static close(button) {

		switch(button.getAttribute('data-dropdown-display')) {

			case 'fullscreen' :
				Lime.History.popLayer(button);
				break;

			case 'around' :
				this.internalClose(button);
				break;

		}

	};

	static internalClose(button) {

		if(this.mutationObserver[button.id] === undefined) { // Already closed
			return false;
		}

		this.mutationObserver[button.id].disconnect();
		delete this.mutationObserver[button.id];

		document.removeEventListener('click', this.clickListener[button.id], {once: true})
		delete this.clickListener[button.id];

		if(document.body.contains(button) === false) {

			qs('[data-dropdown-id="'+ button.dataset.dropdownId +'-list"]', list => {

				list.remove();
				document.body.classList.remove('dropdown-fullscreen-open');

			})

			qs('[data-dropdown-id="'+ button.dataset.dropdownId +'-backdrop"]', backdrop => backdrop.remove());

		} else {

			const list = Lime.Dropdown.getListFromButton(button);

			switch(button.getAttribute('data-dropdown-display')) {

				case 'fullscreen' :
					this.internalCloseFullscreen(button, list);
					break;

				case 'around' :
					this.internalCloseAround(button, list);
					break;

			}

			button.removeAttribute('data-dropdown-display');

			list.classList.remove('dropdown-list-open');

		}

		return true;

	};

	static internalCloseFullscreen(button, list) {

		list.style.position = '';
		list.style.maxHeight = '';
		list.style.transform = '';

		list.classList.add('fullscreen-closing');
		qs('[data-dropdown-id="'+ button.dataset.dropdownId +'-backdrop"]', node => node.classList.add('fullscreen-closing'));

		// La sortie de .fullscreen-closing (lime.css, juicinessAi.css) ne doit pas durer plus que ce délai, sinon la liste est coupée avant la fin.
		setTimeout(() => {

			list.classList.remove('fullscreen-closing');
			list.style.zIndex = '';

			this.closePlaceholder(button, list)

		}, 250);

		document.body.classList.remove('dropdown-fullscreen-open');

	};

	static internalCloseAround(button, list) {

		list.style.zIndex = '';

		this.closePlaceholder(button, list);

		button.stick.destroy();

	};

	static closePlaceholder(button, list) {

		qs('[data-dropdown-id="'+ button.dataset.dropdownId +'-placeholder"]', placeholder => {

			placeholder.insertAdjacentElement('afterend', list);
			placeholder.remove();

		}, () => {
			list.remove();
		});

		qs('[data-dropdown-id="'+ button.dataset.dropdownId +'-backdrop"]', node => node.remove());

	}

	static getListFromButton(button) {

		let list = null;

		if(button.dataset.dropdownId) {
			list = qs('[data-dropdown-id="'+ button.dataset.dropdownId +'-list"]');
		}

		if(list === null) {

			list = button.nextElementSibling;

			if(list === null || list.classList.contains('dropdown-list') === false) {
				throw "Missing dropdown target for button #"+ button.id;
			} else {
				button.setAttribute('data-dropdown-id', button.id);
				list.setAttribute('data-dropdown-id', button.id +'-list');
			}

		}

		list.button = button;

		return list;

	};

	static hoverActive = null;
	static hoverTrigger = null;
	static hoverPending = null;
	static hoverActivating = null;

	static hoverEnter(button) {

		if(isTouch()) {
			return;
		}

		if(this.hoverActive !== button && this.hoverTrigger !== null) {
			this.hoverTrigger.call(this.hoverTrigger);
		}

		clearTimeout(this.hoverPending);

		if(Lime.Dropdown.isOpen(button)) {
			return;
		}

		button.addEventListener('dropdownAfterShow', (e) => {

			const list = e.detail.list;

			if(list.dataset.dropdownHoverLoaded === undefined) {

				list.addEventListener('mouseenter', () => {

					clearTimeout(this.hoverPending);

				});

				list.addEventListener('mouseleave', () => {

					this.hoverTrigger = () => {

						if(button.dataset.dropdownDisplay) {
							Lime.Dropdown.close(button);
						}

						this.hoverActive = null;

					};

					this.hoverPending = setTimeout(this.hoverTrigger, parseInt(button.dataset.dropdownLeaveTimeout || 500));

				});

				list.dataset.dropdownHoverLoaded = 'true';

			}

		}, {once: true});

		this.hoverActivating = setTimeout(() => {

			if(Lime.Dropdown.isOpen(button)) {
				this.hoverActivating = null;
				return;
			}

			Lime.Dropdown.open(button, button.dataset.dropdown);
			this.hoverActive = button;
			this.hoverActivating = null;

		}, parseInt(button.dataset.dropdownEnterTimeout || 250));

	};

	static hoverLeave(button) {

		if(isTouch()) {
			return;
		}

		if(this.hoverActivating !== null) {
			clearTimeout(this.hoverActivating);
			this.hoverActivating = null;
			return;
		}

		this.hoverTrigger = () => {

			if(button.dataset.dropdownDisplay) {
				Lime.Dropdown.close(button);
			}

			this.hoverActive = null;

		};

		this.hoverPending = setTimeout(this.hoverTrigger, parseInt(button.dataset.dropdownLeaveTimeout || 500));

	}

}

document.delegateEventListener('mouseenter', '[data-dropdown-hover]', function(e) {

	e.preventDefault();

	Lime.Dropdown.hoverEnter(this);

});

Lime.Stick = class {

	element;
	free;
	placement;
	offset;

	events;

	constructor(element, free, placement, offset) {

		this.element = element;
		this.free = free;
		this.placement = placement;
		this.offset = offset;

		this.free.style.position = 'absolute';

		this.events = () => {
			this.move();
		};

		window.addEventListener('resize', this.events);
		window.addEventListener('scroll', this.events);

		this.move();

	}
	
	move() {

		const elementBounds = this.element.getBoundingClientRect();
		const freeBounds = this.free.getBoundingClientRect();

		let translateX = 0;
		let translateY = 0;

		if(this.free.style.transform) {

			const transform = this.free.style.transform
				.slice(10, -1)
				.split(', ')
				.map(parseFloat);

			translateX += transform[0];
			translateY += transform[1];

		}

		let placement = this.placement;

		if(placement.startsWith('auto')) {

			placement = (elementBounds.bottom + freeBounds.height > window.innerHeight) ?
				'top' :
				'bottom';

		}

		let placementX;

		if(placement === 'right') {

			translateX += elementBounds.right + (this.offset.x ?? 0);
			translateY += elementBounds.top + ((elementBounds.height - freeBounds.height) / 2);

		} else {

			if(placement.endsWith('start') && elementBounds.left + freeBounds.width < window.innerWidth) {
				placementX = 'start';
			} else if(placement.endsWith('center') && elementBounds.left + freeBounds.width / 2 < window.innerWidth) {
				placementX = 'center';
			} else if(placement.endsWith('end') && elementBounds.left - freeBounds.width > 0) {
				placementX = 'end';
			} else {
				placementX = (elementBounds.left < window.innerWidth / 2) ? 'start' : 'end';
			}

			let left;

			if(placementX === 'start') {
				left = elementBounds.left;
			} else if(placementX ==='center') {
				left = (elementBounds.left + elementBounds.width / 2 - freeBounds.width / 2);
			} else {
				left = (elementBounds.right - freeBounds.width);
			}

			left += (this.offset.x ?? 0);

			// clientWidth et non innerWidth, qui compte la barre de défilement
			const viewportWidth = document.documentElement.clientWidth;

			// La marge horizontale ne déplace rien, le calcul partant de la bordure : elle ne sert qu'à tenir l'élément loin des bords de l'écran
			const freeStyle = window.getComputedStyle(this.free);
			const marginLeft = parseFloat(freeStyle.marginLeft);
			const marginRight = parseFloat(freeStyle.marginRight);

			if(freeBounds.width + marginLeft + marginRight <= viewportWidth) {
				left = Math.max(marginLeft, Math.min(left, viewportWidth - freeBounds.width - marginRight));
			} else if(freeBounds.width <= viewportWidth) {
				left = Math.max(0, Math.min(left, viewportWidth - freeBounds.width));
			}

			translateX += left;

			if(placement.startsWith('top')) {
				translateY += (elementBounds.top - freeBounds.height);
			} else if(placement.startsWith('bottom')) {
				translateY += elementBounds.bottom;
			} else if(placement.startsWith('center')) {
				translateY += (window.innerHeight - freeBounds.height) / 2;
			}

		}

		translateX -= freeBounds.left;
		translateY += (this.offset.y ?? 0) - freeBounds.top;

		this.free.style.transform = 'translate('+ translateX +'px, '+ translateY +'px)';

	}

	destroy() {

		this.free.style.transform = '';
		this.free.style.position = '';

		window.removeEventListener('resize', this.events);
		window.removeEventListener('scroll', this.events);

	}

}

Lime.Asset = class {

	static icon(name) {

		return '<svg class="asset-icon asset-icon-'+ name +'" fill="currentColor">'+
			'<use xlink:href="/static/framework/util/lib/bootstrap-icons-1.13.1.svg#'+ name +'"/>'+
			'</svg>';

	}

};

Lime.Search = class {

	static toggle(selector) {

		if(qs(selector).toggle() === 'removeHide') {
			window.scrollTo(0, 0);
		}

	}

}

Lime.Quick = class {

	static start(module, button) {

		if(button.dataset.dropdown === undefined) {

			new Ajax.Query(button)
				.url('/@module/' + module + '/quick')
				.body(button.post())
				.fetch()
				.then((json) => {

					// Gestion de la concurrence en cas de double-click
					if(button.dataset.dropdown) {
						return;
					}

					button.dataset.dropdown = 'bottom-center';
					button.insertAdjacentHTML('afterend', '<div class="dropdown-list dropdown-list-minimalist util-quick-dropdown" data-dropdown-keep></div>');

					const list = button.nextElementSibling;

					button.addEventListener('dropdownAfterShow', () => {

						list.renderInner(json.field);

						setTimeout(() => list.qs(
							'.util-quick-form select',
							(node) => node.focus(),
							() => list.qs(
								'.util-quick-form input',
								(node) => node.select(),
								() => list.qs('.util-quick-form textarea', (node) => {
									node.focus();
									node.setSelectionRange(node.value.length, node.value.length);
								})
							)
						), 50);


					});


					Lime.Dropdown.open(button, 'bottom-center');

				});

		}

	}

}

document.delegateEventListener('mouseleave', '[data-dropdown-hover]', function(e) {

	e.preventDefault();

	Lime.Dropdown.hoverLeave(this);

});

// Le défilement horizontal d'un bloc (un plan, une frise, un tableau large)
// survit à un rendu ajax : il est noté par id, et reposé sur le bloc qui revient.
// Sans ça, la moindre modification renvoie l'utilisateur tout à gauche.
// Le défilement vertical ne survit que sur un bloc marqué data-keep-scroll-top
// (une liste qu'on parcourt en ouvrant ses éléments un à un) : partout ailleurs,
// un bloc qui revient sous le même id, un panneau qu'on rouvre sur un autre
// élément par exemple, doit repartir du haut.
// Un bloc marqué data-own-scroll-left tient lui-même son défilement horizontal :
// le socle ne le repose pas, et le bloc revient à gauche sauf si son script le
// place. C'est le cas d'un bloc qui garde son id quand ce qu'il montre change.
Lime.Scroll = class {

	static positions = {};
	static positionsTop = {};

	static init() {

		const save = (node) => {

			if(node instanceof Element === false || node.id === '') {
				return;
			}

			if(node.scrollLeft > 0) {
				this.positions[node.id] = node.scrollLeft;
			}

			// Le haut se note aussi, sinon un retour en haut serait défait au rendu suivant
			if(node.hasAttribute('data-keep-scroll-top')) {
				this.positionsTop[node.id] = node.scrollTop;
			}

		};

		document.addEventListener('scroll', e => save(e.target), {capture: true, passive: true});

		// Un clic déclenche souvent le rendu : la position lue à ce moment est la
		// bonne, même si l'événement de défilement n'est pas passé.
		document.addEventListener('pointerdown', e => {
			for(let node = e.target; node instanceof Element; node = node.parentElement) {
				save(node);
			}
		}, {capture: true, passive: true});

	}

	static restore(node) {

		// Le bloc n'a pas toujours sa largeur définitive au moment du rendu (styles
		// ou contenu qui arrivent juste après) : on repose la position, puis on
		// revient à la frame suivante et un peu plus tard tant qu'elle n'a pas pris.
		const apply = (element) => {

			const position = element.hasAttribute('data-own-scroll-left') ? 0 : (this.positions[element.id] ?? 0);
			const positionTop = element.hasAttribute('data-keep-scroll-top') ? (this.positionsTop[element.id] ?? 0) : 0;

			if(position === 0 && positionTop === 0) {
				return;
			}

			const set = () => {

				if(element.isConnected === false) {
					return;
				}

				if(position > 0 && element.scrollLeft === 0 && element.scrollWidth > element.clientWidth) {
					element.scrollLeft = position;
				}

				if(positionTop > 0 && element.scrollTop === 0 && element.scrollHeight > element.clientHeight) {
					element.scrollTop = positionTop;
				}

			};

			set();
			requestAnimationFrame(set);
			setTimeout(set, 200);

		};

		if(node.id) {
			apply(node);
		}

		node.qsa('[id]', apply);

	}

}

Lime.History = class {

	static popped = null;
	static forceRefreshOnBack = false;
	static ignoreNextPopstate = 0;
	static popstatePromises = [];

	// La page chargée est déjà une entrée d'historique : elle porte l'horodatage
	// initial, et non 0. Sinon son défilement est enregistré sous la clé 0 alors
	// que le retour arrière le cherche sous l'horodatage initial — et la première
	// page consultée revenait toujours en haut.
	static initialTimestamp = new Date().getTime();
	static currentTimestamp = null;
	static previousTimestamp = null;

	static scrolls = [];

	static layers = [];

	static purgingLayers = false;
	static purgingResolve = null;


	static init() {

		this.currentTimestamp = this.initialTimestamp;
		this.scrolls[this.initialTimestamp] = window.scrollY;

		// Handles the navigation buttons in the browser
		this.popped = (
			'state' in history &&
			history.state !== null
		);

		const poppedInitialURL = location.href;

		window.oldScrollY = window.scrollY;
		window.onscroll = function() {
			this.oldScrollY = this.scrollY;
		}

		window.onpopstate = e => {

			// Handle promise
			if(this.popstatePromises.length > 0) {
				this.popstatePromises.pop()();
			}

			// Ignore inital popstate that some browsers fire on page load
			const initial = (this.popped === false && location.href === poppedInitialURL);

			this.popped = true;

			if(initial) {
				return;
			}

			if(this.ignoreNextPopstate > 0) {
				this.ignoreNextPopstate--;
				return;
			}

			this.previousTimestamp = this.currentTimestamp;
			this.currentTimestamp = e.state ?? this.initialTimestamp;

			const move = (this.previousTimestamp === null || this.currentTimestamp < this.previousTimestamp) ? 'back' : 'forward';

			if(this.forceRefreshOnBack) {

				if(move === 'back') {
					document.location.reload();
				} else {
					this.forceRefreshOnBack = false;
				}

			}

			if(move === 'back' && this.popLayerOnPopstate()) {

				if(this.purgingLayers) {
					this.purgeNextLayer();
				}

			} else {

				if(this.previousTimestamp !== null) {
					this.saveScroll(this.previousTimestamp);
				}

				const url = location.href;
				const query = new Ajax.Navigation(document.body);

				query.headers.set('x-requested-history', this.currentTimestamp);

				query
					.url(url)
					.skipHistory()
					.fetch()
					.then(() => {
						document.body.dataset.context = url;
					});

			}


		};

	};

	static go(number) {

		return new Promise((resolve) => {

			this.popstatePromises.push(resolve);

			history.go(-1);

		});

	}

	static pushState(url) {

		this.previousTimestamp = this.currentTimestamp;
		this.currentTimestamp = new Date().getTime();

		this.saveScroll(this.previousTimestamp);

		history.pushState(this.currentTimestamp, '', url);

	}

	static replaceState(url) {
		history.replaceState(history.state, '', url);
	}

	static saveScroll(state) {
		this.scrolls[state] = window.scrollY;
	}

	static restoreScroll(state) {

		const scroll = this.scrolls[state];

		if(scroll !== undefined) {
			window.scrollTo(0, scroll);
		}

	}

	static getElementsOfLayers() {
		return this.layers.map(layer => layer.element);
	}

	static pushLayer(element, onPop, pushUrl, backReload) {

		this.layers.push({
			id: element.id,
			element: element,
			onPop: onPop,
			isPushHistory: (pushUrl !== undefined),
			scrollY: window.scrollY,
			title: document.title,
			backReload: backReload
		});

		if(pushUrl !== undefined) {
			this.push(pushUrl);
		}

	};

	static popLayer(element) {

		for(let i = this.layers.length - 1; i >= 0; i--) {

			if(this.layers[i].element === element) {

				const layer = this.layers[i];
				this.layers.splice(i, 1);

				if(layer.isPushHistory) {

					this.ignoreNextPopstate++; // Simulate a history change
					return this.go(-1).then(() => this.removeLayer(layer));

				} else {
					this.removeLayer(layer);
				}

			}

		}

		return false;

	};

	static removeLayer(layer) {

		layer.onPop.call(this, false);
		window.scrollTo(0, layer.scrollY);
		document.title = layer.title;

		return true;

	}

	/*
	 * Remove layer after history back
	 */
	static popLayerOnPopstate() {

		if(this.layers.length === 0) {
			return false;
		} else {

			const layer = this.layers.pop();

			layer.onPop.call(this, true);
			window.scrollTo(0, layer.scrollY);
			document.title = layer.title;

			// Div lost, fully reloaded
			if(
				this.layers.length > 0 &&
				this.layers[this.layers.length - 1].backReload &&
				layer.id === this.layers[this.layers.length - 1].id
			) {

				new Ajax.Query()
					.method('get')
					.waiter(layer.element.style.zIndex + 1, 0)
					.url(this.layers[this.layers.length - 1].backReload)
					.fetch();

			}

			return true;

		}

	};

	static purgeLayers() {

		this.purgingLayers = true;

		return new Promise(resolve => {

			this.purgingResolve = resolve;
			this.purgeNextLayer();

		});

	};

	static purgeNextLayer() {

		if(this.layers.length > 0) {

			this.go(-1);

		} else {

			this.purgingLayers = false;
			this.purgingResolve();

		}

	};

	static push(url) {
		Lime.History.pushState(url);
		this.pop();
	};

	static pop() {
		this.popped = true;
	}

};

Lime.Instruction = class {

	package;

	static methods = {};

	constructor(packageName) {

		this.package = packageName;

		if(Lime.Instruction.methods[this.package] === undefined) {
			Lime.Instruction.methods[this.package] = {};
		}

	}

	register(methodName, callback) {
		Lime.Instruction.methods[this.package][methodName] = callback;
		return this;
	}

	call(context, methodName, data) {

		const callback = Lime.Instruction.methods[this.package][methodName];

		if(callback === undefined) {
			throw "Method '"+ methodName +"' does not exist in package '"+ this.package +"'";
		}

		callback.call(context, ...data);

	}

}

document.delegateEventListener('click', '[data-dropdown]', function(e) {

	if(
		isTouch() ||
		this.dataset.dropdownHover === undefined
	) {

		// Pris en capture sur document, ce click ne va pas plus loin : ni les délégués posés après, ni les
		// écouteurs du bouton ou de ses parents. Pour agir à l'ouverture, écouter dropdownAfterShow.
		e.preventDefault();
		e.stopImmediatePropagation();

		Lime.Dropdown.toggle(this, this.dataset.dropdown);

	}

});


document.addEventListener('keyup', (e) => {
	Lime.Panel.closeEscape(e);
});
