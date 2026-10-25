const APP_LABELS = {
	all: 'Tout',
	accounting: 'Comptabilité',
	commercialisation: 'Commercialisation',
	production: 'Production',
	base: 'Base',
	ouvretaferme: 'Ouvretaferme',
	other: 'Autre',
};
const APP_ORDER = ['all', 'accounting', 'commercialisation', 'production', 'base', 'ouvretaferme', 'other'];

let fullSpec      = null;
let currentApp    = 'all';
let currentPkg    = 'all';
let ui            = null;
let moduleTags    = new Set();
let standaloneTags = new Set();

document.addEventListener('navigation.wakeup', function() {

	const swaggerEl = document.getElementById('swagger-ui');
	if (!swaggerEl) return; // page sans swagger

	const jsonUrl = swaggerEl.dataset.jsonUrl;

	loadSwaggerAssets(() => fetch(jsonUrl)
		.then(r => {
			if (!r.ok) throw new Error('HTTP ' + r.status + ' — vérifiez que openapi.json existe (php tools/swagger-gen/generate.php)');
			return r.json();
		})
		.then(spec => {
			fullSpec = spec;
			buildAppTabs(spec);
			buildPkgTabs(spec, 'all');
			extractTagTypes(spec);
			initSwagger(spec);
		})
		.catch(err => {
			document.getElementById('doc-status').innerHTML = '<span style="color:red">⚠ ' + err.message + '</span>';
		})
	);

});

function loadSwaggerAssets(callback) {
	const CSS_URL    = 'https://unpkg.com/swagger-ui-dist@5/swagger-ui.css';
	const BUNDLE_URL = 'https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js';

	// CSS — ajouter une seule fois
	if (!document.querySelector(`link[href="${CSS_URL}"]`)) {
		const link = document.createElement('link');
		link.rel  = 'stylesheet';
		link.href = CSS_URL;
		document.head.appendChild(link);
	}

	// JS — charger si pas encore disponible
	if (typeof SwaggerUIBundle !== 'undefined') {
		callback();
		return;
	}

	const script  = document.createElement('script');
	script.src    = BUNDLE_URL;
	script.onload = callback;
	document.head.appendChild(script);
}

// ─── Niveau 1 : onglets par app ───────────────────────────────────────────────

function buildAppTabs(spec) {
	const tagsByApp = { all: new Set() };
	const apps      = new Set();

	for (const methods of Object.values(spec.paths || {})) {
		for (const [method, op] of Object.entries(methods)) {
			if (!['get','post','put','delete','patch'].includes(method)) continue;
			const app = op['x-app'] || 'other';
			apps.add(app);
			if (!tagsByApp[app]) tagsByApp[app] = new Set();
			(op.tags || []).forEach(t => { tagsByApp[app].add(t); tagsByApp.all.add(t); });
		}
	}

	const counts  = Object.fromEntries(Object.entries(tagsByApp).map(([a, s]) => [a, s.size]));
	const ordered = APP_ORDER.filter(a => a === 'all' || apps.has(a));
	document.getElementById('app-tabs').innerHTML = ordered.map(app => `
		<button class="app-tab${app === currentApp ? ' active' : ''}" data-app="${app}" onclick="switchApp('${app}')">
			${APP_LABELS[app] || app}<span class="count">${counts[app] || 0}</span>
		</button>
	`).join('');
}

function switchApp(app) {
	currentApp = app;
	currentPkg = 'all';
	document.querySelectorAll('.app-tab').forEach(b => b.classList.toggle('active', b.dataset.app === app));
	buildPkgTabs(fullSpec, app);
	applyFilter();
}

// ─── Niveau 2 : onglets par package ───────────────────────────────────────────

function buildPkgTabs(spec, app) {
	const tagsByPkg = { all: new Set() };
	const pkgs      = new Set();

	for (const methods of Object.values(spec.paths || {})) {
		for (const [method, op] of Object.entries(methods)) {
			if (!['get','post','put','delete','patch'].includes(method)) continue;
			if (app !== 'all' && (op['x-app'] || 'other') !== app) continue;
			const pkg = op['x-package'] || 'other';
			pkgs.add(pkg);
			if (!tagsByPkg[pkg]) tagsByPkg[pkg] = new Set();
			(op.tags || []).forEach(t => { tagsByPkg[pkg].add(t); tagsByPkg.all.add(t); });
		}
	}

	const counts    = Object.fromEntries(Object.entries(tagsByPkg).map(([p, s]) => [p, s.size]));
	const container = document.getElementById('pkg-tabs');

	if (pkgs.size <= 1) {
		container.innerHTML = '';
		return;
	}

	const ordered = ['all', ...[...pkgs].sort()];
	container.innerHTML = ordered.map(pkg => `
		<button class="pkg-tab${pkg === currentPkg ? ' active' : ''}" data-pkg="${pkg}" onclick="switchPkg('${pkg}')">
			${pkg}<span class="count">${counts[pkg] || 0}</span>
		</button>
	`).join('');
}

function switchPkg(pkg) {
	currentPkg = pkg;
	document.querySelectorAll('.pkg-tab').forEach(b => b.classList.toggle('active', b.dataset.pkg === pkg));
	applyFilter();
}

// ─── Application du filtre combiné ────────────────────────────────────────────

function applyFilter() {
	const spec  = filterSpec(fullSpec, currentApp, currentPkg);
	extractTagTypes(spec);
	const count = (spec.tags || []).length;
	document.getElementById('doc-status').textContent = count + ' module' + (count !== 1 ? 's' : '');
	ui.specActions.updateSpec(JSON.stringify(spec));
	setTimeout(() => { injectSectionHeaders(); renameSchemas(); }, 150);
}

// ─── Filtrage du spec ─────────────────────────────────────────────────────────

function filterSpec(spec, app, pkg) {
	// On supprime servers dans tous les cas
	const base = Object.assign({}, spec);
	delete base.servers;

	if (app === 'all' && pkg === 'all') return base;

	const filtered = Object.assign({}, base, { paths: {} });
	const usedTags = new Set();

	for (const [path, methods] of Object.entries(spec.paths || {})) {
		const kept = {};
		for (const [method, op] of Object.entries(methods)) {
			if (!['get','post','put','delete','patch'].includes(method)) continue;
			if (app !== 'all' && (op['x-app'] || 'other') !== app) continue;
			if (pkg !== 'all' && (op['x-package'] || 'other') !== pkg) continue;
			kept[method] = op;
			(op.tags || []).forEach(t => usedTags.add(t));
		}
		if (Object.keys(kept).length > 0) filtered.paths[path] = kept;
	}

	if (spec.tags) {
		filtered.tags = spec.tags.filter(t => usedTags.has(t.name));
	}

	if (spec.components?.schemas) {
		// Schémas primaires : ceux qui correspondent au filtre app/package courant
		const primary = new Set(
			Object.entries(spec.components.schemas)
				.filter(([, s]) => {
					if (app !== 'all' && (s['x-app'] || 'other') !== app) return false;
					if (pkg !== 'all' && (s['x-package'] || 'other') !== pkg) return false;
					return true;
				})
				.map(([name]) => name)
		);

		// Dépendances transitives (refs depuis les paths + depuis les schémas primaires)
		const all = new Set([...primary]);
		collectTransitiveRefs(filtered.paths, spec.components.schemas, all);
		for (const name of [...primary]) {
			collectTransitiveRefs(spec.components.schemas[name], spec.components.schemas, all);
		}

		// Ordre : primaires d'abord, dépendances ensuite
		const schemas = {};
		for (const name of primary) {
			if (spec.components.schemas[name]) schemas[name] = spec.components.schemas[name];
		}
		for (const name of all) {
			if (!primary.has(name) && spec.components.schemas[name]) schemas[name] = spec.components.schemas[name];
		}
		filtered.components = Object.assign({}, spec.components, { schemas });
	}

	return filtered;
}

// ─── Collecte récursive des $ref ──────────────────────────────────────────────

function collectTransitiveRefs(obj, allSchemas, visited) {
	if (!obj || typeof obj !== 'object') return;
	if (Array.isArray(obj)) { obj.forEach(v => collectTransitiveRefs(v, allSchemas, visited)); return; }
	for (const [key, val] of Object.entries(obj)) {
		if (key === '$ref' && typeof val === 'string') {
			const m = val.match(/^#\/components\/schemas\/(.+)$/);
			if (m && !visited.has(m[1])) {
				visited.add(m[1]);
				collectTransitiveRefs(allSchemas[m[1]], allSchemas, visited);
			}
		} else {
			collectTransitiveRefs(val, allSchemas, visited);
		}
	}
}

// ─── Section headers dans le DOM ─────────────────────────────────────────────

function extractTagTypes(spec) {
	moduleTags     = new Set();
	standaloneTags = new Set();
	for (const tag of spec.tags || []) {
		if (tag['x-pageType'] === 'module') moduleTags.add(tag.name);
		else standaloneTags.add(tag.name);
	}
}

function injectSectionHeaders() {
	document.querySelectorAll('.doc-section-header').forEach(el => el.remove());

	const sections = document.querySelectorAll('.opblock-tag-section');
	let firstModuleDone     = false;
	let firstStandaloneDone = false;

	for (const section of sections) {
		const tagEl = section.querySelector('[data-tag]');
		if (!tagEl) continue;
		const tag = tagEl.getAttribute('data-tag');

		if (!firstModuleDone && moduleTags.has(tag)) {
			firstModuleDone = true;
			insertHeader(section, 'Routes des modules');
		} else if (!firstStandaloneDone && standaloneTags.has(tag)) {
			firstStandaloneDone = true;
			insertHeader(section, 'Autres routes');
		}
	}
}

function insertHeader(beforeEl, text) {
	const div = document.createElement('div');
	div.className = 'doc-section-header';
	div.textContent = text;
	beforeEl.parentNode.insertBefore(div, beforeEl);
}

function renameSchemas() {
	document.querySelectorAll('.swagger-ui .models h4 span').forEach(el => {
		if (el.textContent.trim() === 'Schemas') el.textContent = 'Modules';
	});
}

// ─── Init Swagger UI ──────────────────────────────────────────────────────────

function initSwagger(spec) {
	const clean = Object.assign({}, spec);
	delete clean.servers;

	const count = (clean.tags || []).length;
	document.getElementById('doc-status').textContent = count + ' modules';

	ui = SwaggerUIBundle({
		spec: clean,
		dom_id: '#swagger-ui',
		presets: [SwaggerUIBundle.presets.apis],
		layout: 'BaseLayout',
		deepLinking: false,
		docExpansion: 'none',
		defaultModelsExpandDepth: 0,
		tryItOutEnabled: false,
		supportedSubmitMethods: [],
		onComplete: () => { setTimeout(() => { injectSectionHeaders(); renameSchemas(); }, 100); },
	});
}