import fs from 'node:fs/promises';

const origin = 'https://harmat22.hu';
const sitemapUrl = `${origin}/sitemap_index.xml`;
const reportPath = process.argv[2] || '';
const userAgent = 'Harmat22 maintenance regression scanner';
const pageIssues = [];
const assetIssues = [];
const propertyIssues = [];
const sitemapIssues = [];
const pageUrls = new Set();
const assetUrls = new Set();

function decodeXml(value) {
    return value
        .replaceAll('&amp;', '&')
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .replaceAll('&quot;', '"')
        .replaceAll('&#39;', "'");
}

function visibleText(html) {
    return decodeXml(
        html
            .replace(/<!--[\s\S]*?-->/g, ' ')
            .replace(/<(script|style|noscript|template)\b[\s\S]*?<\/\1>/gi, ' ')
            .replace(/<[^>]+>/g, ' ')
            .replace(/&#(\d+);/g, (_, code) => String.fromCodePoint(Number(code)))
            .replace(/&#x([0-9a-f]+);/gi, (_, code) => String.fromCodePoint(parseInt(code, 16)))
    ).replace(/\s+/g, ' ').trim();
}

async function fetchWithTimeout(url, options = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 30000);
    try {
        return await fetch(url, {
            redirect: 'follow',
            ...options,
            headers: {
                'User-Agent': userAgent,
                ...(options.headers || {}),
            },
            signal: controller.signal,
        });
    } finally {
        clearTimeout(timer);
    }
}

async function collectSitemap(url, seen = new Set()) {
    if (seen.has(url)) {
        return;
    }
    seen.add(url);

    try {
        const response = await fetchWithTimeout(url);
        if (!response.ok) {
            sitemapIssues.push({ url, status: response.status });
            return;
        }

        const xml = await response.text();
        const locations = [...xml.matchAll(/<loc>([\s\S]*?)<\/loc>/gi)]
            .map((match) => decodeXml(match[1].trim()));

        if (/<sitemapindex\b/i.test(xml)) {
            for (const location of locations) {
                await collectSitemap(location, seen);
            }
            return;
        }

        for (const location of locations) {
            const pageUrl = new URL(location, origin);
            if (pageUrl.origin === origin) {
                pageUrl.hash = '';
                pageUrls.add(pageUrl.href);
            }
        }
    } catch (error) {
        sitemapIssues.push({ url, error: String(error) });
    }
}

async function mapLimit(items, limit, worker) {
    const results = new Array(items.length);
    let nextIndex = 0;

    async function run() {
        while (true) {
            const index = nextIndex;
            nextIndex += 1;
            if (index >= items.length) {
                return;
            }
            results[index] = await worker(items[index], index);
        }
    }

    await Promise.all(Array.from({ length: Math.min(limit, items.length) }, run));
    return results;
}

function collectAssets(html, pageUrl) {
    const rawUrls = [];
    for (const match of html.matchAll(
        /\b(?:src|href|poster|data-src|data-lazy-src)=["']([^"'#]+)["']/gi
    )) {
        rawUrls.push(match[1]);
    }

    for (const match of html.matchAll(/\b(?:srcset|data-srcset)=["']([^"']+)["']/gi)) {
        for (const candidate of match[1].split(',')) {
            rawUrls.push(candidate.trim().split(/\s+/)[0]);
        }
    }

    for (const rawUrl of rawUrls) {
        if (!rawUrl || /^(?:data|javascript|mailto|tel):/i.test(rawUrl)) {
            continue;
        }

        try {
            const assetUrl = new URL(decodeXml(rawUrl), pageUrl);
            if (assetUrl.origin !== origin) {
                continue;
            }
            assetUrl.hash = '';
            if (
                /\.(?:avif|css|eot|gif|jpe?g|js|json|m4v|mp4|pdf|png|svg|ttf|webm|webp|woff2?)$/i
                    .test(assetUrl.pathname)
            ) {
                assetUrls.add(assetUrl.href);
            }
        } catch {
            pageIssues.push({ url: pageUrl, issue: 'invalid_asset_url', value: rawUrl });
        }
    }
}

async function inspectPage(url) {
    try {
        const response = await fetchWithTimeout(url);
        const html = await response.text();
        const text = visibleText(html);
        const contentType = response.headers.get('content-type') || '';

        if (!response.ok) {
            pageIssues.push({ url, issue: 'http_status', status: response.status });
        }
        if (!contentType.includes('text/html')) {
            pageIssues.push({ url, issue: 'content_type', contentType });
        }
        if (/Fatal error|Parse error|There has been a critical error|Kritikus hiba/i.test(text)) {
            pageIssues.push({ url, issue: 'fatal_error_text' });
        }
        if (/[\u4e00-\u9fff]/u.test(text)) {
            pageIssues.push({ url, issue: 'public_chinese_text' });
        }
        if (/[\u00c3\u00c2]\S|\u00e2\u20ac|\u00ef\u00bf\u00bd|\ufffd/u.test(text)) {
            pageIssues.push({ url, issue: 'possible_mojibake' });
        }

        const langMatch = html.match(/<html\b[^>]*\blang=["']([^"']+)["']/i);
        if (!langMatch || !langMatch[1].toLowerCase().startsWith('hu')) {
            pageIssues.push({
                url,
                issue: 'html_lang',
                value: langMatch ? langMatch[1] : '',
            });
        }

        collectAssets(html, url);

        const pathname = new URL(url).pathname;
        if (pathname.startsWith('/property/')) {
            const unit = pathname.split('/').filter(Boolean).at(-1).toUpperCase();
            const expectedImage = `${unit}-cn-floorplan-display.jpg`;
            if (!html.toUpperCase().includes(expectedImage.toUpperCase())) {
                propertyIssues.push({ url, unit, expectedImage });
            }
        }

        return {
            url,
            status: response.status,
            bytes: Buffer.byteLength(html),
            property: pathname.startsWith('/property/'),
        };
    } catch (error) {
        pageIssues.push({ url, issue: 'request_error', error: String(error) });
        return { url, status: 0, bytes: 0, property: false };
    }
}

async function inspectAsset(url) {
    try {
        let response = await fetchWithTimeout(url, { method: 'HEAD' });
        if (!response.ok || response.status === 405) {
            response = await fetchWithTimeout(url, {
                headers: { Range: 'bytes=0-0' },
            });
        }
        if (!response.ok) {
            assetIssues.push({ url, status: response.status });
        }
        return { url, status: response.status };
    } catch (error) {
        assetIssues.push({ url, error: String(error) });
        return { url, status: 0 };
    }
}

await collectSitemap(sitemapUrl);

const sortedPages = [...pageUrls].sort();
const pageResults = await mapLimit(sortedPages, 6, inspectPage);
const sortedAssets = [...assetUrls].sort();
const assetResults = await mapLimit(sortedAssets, 12, inspectAsset);
const propertyCount = pageResults.filter((result) => result.property).length;

if (propertyCount !== 124) {
    propertyIssues.push({
        issue: 'property_count',
        expected: 124,
        actual: propertyCount,
    });
}

const report = {
    generatedAt: new Date().toISOString(),
    origin,
    sitemapUrl,
    summary: {
        pages: pageResults.length,
        properties: propertyCount,
        assets: assetResults.length,
        sitemapIssues: sitemapIssues.length,
        pageIssues: pageIssues.length,
        propertyIssues: propertyIssues.length,
        assetIssues: assetIssues.length,
    },
    sitemapIssues,
    pageIssues,
    propertyIssues,
    assetIssues,
    pages: pageResults,
    assets: assetResults,
};

if (reportPath) {
    await fs.writeFile(reportPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
}

console.log(JSON.stringify(report.summary));

if (
    sitemapIssues.length
    || pageIssues.length
    || propertyIssues.length
    || assetIssues.length
) {
    process.exitCode = 1;
}
