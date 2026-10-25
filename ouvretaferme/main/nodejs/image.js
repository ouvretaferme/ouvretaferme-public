'use strict';

/**
 * Ouvre une page dans Chrome sans écran, attend qu'elle lève window.imageReady, puis écrit dans --destination l'image
 * PNG que porte window.image, en adresse data:. C'est le chemin d'une image que le serveur ne sait pas rendre, comme
 * celle d'un avatar en 3D (`user\UserAvatarLib::capture()`).
 *
 * --check lance Chrome et vérifie qu'il ouvre un contexte WebGL 2, celui de three.js, sans ouvrir de page du site.
 *
 * Sort en erreur sans rien écrire si la page lève une exception ou ne rend pas son image dans le délai.
 */

const argv = require('minimist')(process.argv.slice(2));
const puppeteer = require('puppeteer-core');
const fs = require('fs');

const PREFIX = 'data:image/png;base64,';
const TIMEOUT = 30000;

(async() => {

	// Le tas de V8 est borné : Chrome réserve un espace d'adressage que `ulimit -v` refuse, et ne se borne pas autrement
	const browser = await puppeteer.launch({
		executablePath: '/usr/bin/google-chrome',
		args: ['--no-sandbox', '--js-flags=--max-old-space-size=256']
	});

	try {

		const page = await browser.newPage();

		if(argv.check) {

			const version = await page.evaluate(() => document.createElement('canvas').getContext('webgl2')?.getParameter(WebGL2RenderingContext.VERSION) ?? null);

			if(version === null) {
				throw new Error('Chrome n\'ouvre pas de contexte WebGL 2');
			}

			console.log(await browser.version() +' · '+ version);
			return;

		}

		const errors = [];
		page.on('pageerror', error => errors.push(error.message));

		await page.goto(argv.url, { waitUntil: 'load', timeout: TIMEOUT });

		await page.waitForFunction(() => window.imageReady === true, { timeout: TIMEOUT })
			.catch(() => {
				throw new Error('aucune image après '+ (TIMEOUT / 1000) +' s'+ (errors.length > 0 ? ' : '+ errors.join(' · ') : ''));
			});

		const image = await page.evaluate(() => window.image);

		if(typeof image !== 'string' || image.startsWith(PREFIX) === false) {
			throw new Error('image hors PNG');
		}

		fs.writeFileSync(argv.destination, Buffer.from(image.slice(PREFIX.length), 'base64'));

	} finally {
		await browser.close();
	}

})().catch(error => {
	console.error(error.message);
	process.exit(1);
});
