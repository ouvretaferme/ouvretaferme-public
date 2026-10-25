'use strict';

/**
 * Fait d'un PDF de facture un Factur-X : PDF/A-3b auquel est attaché le XML CII de la facture.
 *
 * Ce qu'ajoute le script, et pourquoi :
 * - le XML, attaché sous le nom imposé par Factur-X (factur-x.xml), référencé à la fois dans
 *   l'arbre des fichiers joints et dans /AF du catalogue, que PDF/A-3 exige pour tout fichier associé ;
 * - un profil de sortie sRGB (/OutputIntents), sans lequel PDF/A refuse toute couleur RVB du document ;
 * - les métadonnées XMP : identification PDF/A-3b, schéma d'extension fx qui déclare le document
 *   Factur-X, et les mêmes titre, auteur, producteur et dates que le dictionnaire /Info, PDF/A
 *   refusant toute divergence entre les deux ;
 * - un identifiant de fichier (/ID), obligatoire en PDF/A.
 *
 * Usage : node facturxAi.js --source=in.pdf --xml=factur-x.xml --icc=srgb.icc --destination=out.pdf
 *         --title=<numéro> --author=<vendeur>
 */

const argv = require('minimist')(process.argv.slice(2));
const fs = require('fs');
const crypto = require('crypto');
const { PDFDocument, PDFName, PDFString, PDFHexString, PDFDict } = require('pdf-lib');

const FILENAME = 'factur-x.xml';
const NAMESPACE = 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#';

const escapeXml = (value) => String(value)
	.replace(/&/g, '&amp;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;')
	.replace(/"/g, '&quot;');

// XMP et /Info doivent porter la même date à la seconde, fuseau compris : les millisecondes
// s'écartent, et les deux se forment depuis le même objet.
const now = new Date(Math.floor(Date.now() / 1000) * 1000);
const xmpDate = now.toISOString().replace('.000Z', 'Z');

const title = argv.title || '';
const author = argv.author || '';
const producer = 'Ouvretaferme';

const extensionProperty = (name, description) => `
						<rdf:li rdf:parseType="Resource">
							<pdfaProperty:name>${name}</pdfaProperty:name>
							<pdfaProperty:valueType>Text</pdfaProperty:valueType>
							<pdfaProperty:category>external</pdfaProperty:category>
							<pdfaProperty:description>${description}</pdfaProperty:description>
						</rdf:li>`;

const xmp = `<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/">
	<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
		<rdf:Description rdf:about="" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">
			<pdfaid:part>3</pdfaid:part>
			<pdfaid:conformance>B</pdfaid:conformance>
		</rdf:Description>
		<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">
			<dc:title><rdf:Alt><rdf:li xml:lang="x-default">${escapeXml(title)}</rdf:li></rdf:Alt></dc:title>
			<dc:creator><rdf:Seq><rdf:li>${escapeXml(author)}</rdf:li></rdf:Seq></dc:creator>
		</rdf:Description>
		<rdf:Description rdf:about="" xmlns:pdf="http://ns.adobe.com/pdf/1.3/">
			<pdf:Producer>${producer}</pdf:Producer>
		</rdf:Description>
		<rdf:Description rdf:about="" xmlns:xmp="http://ns.adobe.com/xap/1.0/">
			<xmp:CreatorTool>${producer}</xmp:CreatorTool>
			<xmp:CreateDate>${xmpDate}</xmp:CreateDate>
			<xmp:ModifyDate>${xmpDate}</xmp:ModifyDate>
		</rdf:Description>
		<rdf:Description rdf:about="" xmlns:fx="${NAMESPACE}">
			<fx:DocumentType>INVOICE</fx:DocumentType>
			<fx:DocumentFileName>${FILENAME}</fx:DocumentFileName>
			<fx:Version>1.0</fx:Version>
			<fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>
		</rdf:Description>
		<rdf:Description rdf:about=""
			xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/"
			xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#"
			xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">
			<pdfaExtension:schemas>
				<rdf:Bag>
					<rdf:li rdf:parseType="Resource">
						<pdfaSchema:schema>Factur-X PDFA Extension Schema</pdfaSchema:schema>
						<pdfaSchema:namespaceURI>${NAMESPACE}</pdfaSchema:namespaceURI>
						<pdfaSchema:prefix>fx</pdfaSchema:prefix>
						<pdfaSchema:property>
							<rdf:Seq>${[
								extensionProperty('DocumentFileName', 'The name of the embedded XML document'),
								extensionProperty('DocumentType', 'The type of the hybrid document in capital letters, e.g. INVOICE or ORDER'),
								extensionProperty('Version', 'The actual version of the standard applying to the embedded XML document'),
								extensionProperty('ConformanceLevel', 'The conformance level of the embedded XML document')
							].join('')}
							</rdf:Seq>
						</pdfaSchema:property>
					</rdf:li>
				</rdf:Bag>
			</pdfaExtension:schemas>
		</rdf:Description>
	</rdf:RDF>
</x:xmpmeta>
<?xpacket end="w"?>`;

(async() => {

	const source = fs.readFileSync(argv.source);
	const xml = fs.readFileSync(argv.xml);
	const icc = fs.readFileSync(argv.icc);

	const doc = await PDFDocument.load(source, {updateMetadata: false});
	const context = doc.context;
	const catalog = doc.catalog;

	// Fichier joint
	const fileRef = context.register(context.flateStream(xml, {
		Type: 'EmbeddedFile',
		Subtype: 'text/xml',
		Params: {
			Size: xml.length,
			ModDate: PDFString.fromDate(now)
		}
	}));

	const fileSpecRef = context.register(context.obj({
		Type: 'Filespec',
		F: PDFString.of(FILENAME),
		UF: PDFHexString.fromText(FILENAME),
		EF: {F: fileRef, UF: fileRef},
		Desc: PDFHexString.fromText('Factur-X'),
		AFRelationship: 'Data'
	}));

	catalog.set(PDFName.of('AF'), context.obj([fileSpecRef]));

	const names = catalog.lookupMaybe(PDFName.of('Names'), PDFDict) || context.obj({});
	names.set(PDFName.of('EmbeddedFiles'), context.obj({
		Names: [PDFHexString.fromText(FILENAME), fileSpecRef]
	}));
	catalog.set(PDFName.of('Names'), names);

	// Profil de sortie
	const iccRef = context.register(context.flateStream(icc, {N: 3}));

	catalog.set(PDFName.of('OutputIntents'), context.obj([
		context.register(context.obj({
			Type: 'OutputIntent',
			S: 'GTS_PDFA1',
			OutputConditionIdentifier: PDFString.of('sRGB IEC61966-2.1'),
			Info: PDFString.of('sRGB IEC61966-2.1'),
			DestOutputProfile: iccRef
		}))
	]));

	// Métadonnées
	const metadataRef = context.register(context.stream(xmp, {
		Type: 'Metadata',
		Subtype: 'XML'
	}));

	catalog.set(PDFName.of('Metadata'), metadataRef);

	// Le dictionnaire /Info est refait plutôt que complété : une seule entrée laissée par Chrome
	// sans équivalent dans le XMP suffirait à faire échouer la validation PDF/A.
	context.trailerInfo.Info = context.register(context.obj({
		Title: PDFHexString.fromText(title),
		Author: PDFHexString.fromText(author),
		Creator: PDFHexString.fromText(producer),
		Producer: PDFHexString.fromText(producer),
		CreationDate: PDFString.fromDate(now),
		ModDate: PDFString.fromDate(now)
	}));

	const id = PDFHexString.of(crypto.createHash('md5').update(source).update(xml).digest('hex'));
	context.trailerInfo.ID = context.obj([id, id]);

	fs.writeFileSync(argv.destination, await doc.save({useObjectStreams: false, updateFieldAppearances: false}));

})().catch((error) => {
	console.error(error);
	process.exit(1);
});
