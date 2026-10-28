import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {createRequire} from 'node:module';

const require = createRequire(import.meta.url);
const {chromium} = require(process.env.SBG_PLAYWRIGHT_MODULE || 'playwright');
const evidence = process.env.SBG_BROWSER_EVIDENCE;
if (!evidence) throw Error('Set SBG_BROWSER_EVIDENCE to a directory outside the repository.');
const fixtures = path.join(evidence, 'fixtures');
const phase = process.env.SBG_BROWSER_PHASE || 'after';
const output = path.join(evidence, phase);
fs.mkdirSync(output, {recursive: true});
const orders = JSON.parse(fs.readFileSync(path.join(fixtures, 'orders.json')));
let origin;
const server = http.createServer((request, response) => {
    const url = new URL(request.url, origin);
    response.setHeader('Cache-Control', 'no-store');
    if (url.pathname === '/dashboard') {
        response.setHeader('Content-Type', 'text/html');
        response.end(fs.readFileSync(path.join(fixtures, 'dashboard.html'), 'utf8').replaceAll('https://sbg.my.id', origin).replaceAll('http://localhost', origin));
        return;
    }
    const detail = url.pathname.match(/^\/dashboard\/po\/(\d+)$/);
    if (detail || url.pathname.startsWith('/dashboard/fruit-calculator')) {
        response.setHeader('Content-Type', 'application/json');
        response.end(detail ? JSON.stringify(orders[detail[1]]) : url.pathname.endsWith('/decisions') ? '{"data":[],"current_page":1,"last_page":1}' : fs.readFileSync(path.join(fixtures, 'calculator.json')));
        return;
    }
    const file = path.resolve('public', '.' + decodeURIComponent(url.pathname));
    if (file.startsWith(path.resolve('public') + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
        const types = {'.css':'text/css', '.js':'text/javascript', '.svg':'image/svg+xml', '.webp':'image/webp', '.woff2':'font/woff2'};
        response.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');
        response.end(fs.readFileSync(file));
        return;
    }
    response.writeHead(404); response.end();
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({headless:true, args:['--no-sandbox']});
const page = await browser.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const results = [];
const matrix = phase === 'before' ? [[1366,768],[390,844]] : [[1920,1080],[1440,900],[1366,768],[1280,720],[1024,768],[834,1194],[768,1024],[430,932],[390,844],[375,812],[360,800]];
const save = () => fs.writeFileSync(path.join(evidence, `${phase}-results.json`), JSON.stringify({results,errors}, null, 2));
try {
    for (const [width,height] of matrix) {
        await page.setViewportSize({width,height});
        await page.goto(origin + '/dashboard', {waitUntil:'load'});
        await page.waitForFunction(() => !!window.Alpine && document.body._x_dataStack?.length);
        await page.evaluate(() => document.fonts.ready);
        await page.screenshot({path:path.join(output, `dashboard-${width}.png`), fullPage:true});
        if (phase === 'after' && width < 768) {
            await page.locator('.sbg-bottom-nav a[href="#data-po"]').click();
            await page.screenshot({path:path.join(output, `orders-${width}.png`), fullPage:true});
        }
        await page.locator('.sbg-po-table tbody tr, .sbg-po-list tbody tr').first().count().then(async count => {
            if (phase === 'before' || width >= 768) {
                await page.locator('.sbg-po-table tbody tr, .sbg-po-list tbody tr').first().click();
            } else {
                await page.locator('.sbg-po-card .sbg-po-open').first().click();
            }
        });
        await page.waitForTimeout(300);
        await page.screenshot({path:path.join(output, `detail-${width}.png`)});
        const metrics = await page.evaluate(() => {
            const panel = document.querySelector('.sbg-detail-panel');
            const rect = panel.getBoundingClientRect();
            const kpis = [...document.querySelectorAll('.sbg-kpi')].map(card => ({height:card.getBoundingClientRect().height, width:card.getBoundingClientRect().width}));
            return {overflow:document.documentElement.scrollWidth>innerWidth, panel:{width:rect.width,height:rect.height,top:rect.top,bottom:rect.bottom,scroll:panel.scrollHeight>panel.clientHeight+1}, kpis};
        });
        results.push({width,height,...metrics}); save();
        if (phase === 'after') {
            assert.equal(metrics.overflow, false, `Page overflow at ${width}`);
            assert.ok(metrics.panel.top >= 0 && metrics.panel.bottom <= height+1, `Modal outside viewport at ${width}`);
            if (width >= 1280) {
                assert.equal(metrics.panel.scroll, false, `Common PO needs scroll at ${width}`);
                assert.ok(metrics.panel.width <= (width===1280||width===1366?780:820), `Modal too wide at ${width}`);
                assert.ok(metrics.kpis.every(card=>card.height>=116&&card.height<=132), `KPI height at ${width}`);
            }
            if (width < 768) assert.ok(metrics.panel.height<=height*.92+1, `Sheet exceeds 92dvh at ${width}`);
            await page.keyboard.press('Escape');
            await page.locator('#po-detail').waitFor({state:'hidden',timeout:3000}).catch(async error => { console.log(await page.evaluate(() => ({open:Alpine.$data(document.body).isDetailModalOpen,style:document.getElementById('po-detail').getAttribute('style'),active:document.activeElement.outerHTML.slice(0,200)}))); throw error; });
            assert.equal(await page.locator('#po-detail').isVisible(), false);
        }
    }
    save();
    if (phase === 'after') {
        assert.deepEqual(errors, []);
        console.log(`PASS ${results.length} viewports: dashboard, PO list, detail geometry and Escape.`);
    }
} finally {
    await browser.close(); server.close();
}
