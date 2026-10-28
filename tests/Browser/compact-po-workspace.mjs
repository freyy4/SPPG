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
        response.end(fs.readFileSync(path.join(fixtures, url.searchParams.has('empty') ? 'empty.html' : 'dashboard.html'), 'utf8').replaceAll('https://sbg.my.id', origin).replaceAll('http://localhost', origin));
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
        const dashboardMetrics = await page.evaluate(() => ({
            fontLoaded:[...document.fonts].some(font=>font.family.includes('Plus Jakarta Sans')&&font.status==='loaded'),
            kpis:[...document.querySelectorAll('.sbg-kpi')].filter(e=>e.getClientRects().length).map(card=>({height:card.getBoundingClientRect().height,width:card.getBoundingClientRect().width})),
            exportsInKpis:[...document.querySelectorAll('.sbg-kpi')].some(card=>/Print|Download/.test(card.textContent)),
            kpiValueOverflow:[...document.querySelectorAll('.sbg-kpi-value')].filter(e=>e.getClientRects().length).some(e=>e.scrollWidth>e.clientWidth+1),
            filterHeight:document.querySelector('.sbg-filter')?.getBoundingClientRect().height,
            listTop:document.querySelector('.sbg-po-list').getBoundingClientRect().top,
            rowHeights:[...document.querySelectorAll('.sbg-po-table tbody tr')].map(e=>e.getBoundingClientRect().height),
        }));
        await page.screenshot({path:path.join(output, `dashboard-${width}.png`), fullPage:true});
        await page.screenshot({path:path.join(output, `dashboard-viewport-${width}.png`)});
        if (phase === 'after' && width < 768) {
            await page.locator('.sbg-bottom-nav a[href="#data-po"]').click();
            await page.evaluate(() => scrollTo(0,0));
            await page.screenshot({path:path.join(output, `orders-${width}.png`), fullPage:true});
            const cards = await page.locator('.sbg-po-card').evaluateAll(cards=>cards.map(e=>e.getBoundingClientRect().height));
            assert.ok(cards.every(height=>height>=108&&height<=132), `Mobile PO cards: ${width} ${cards}`);
            const targets=await page.locator('.sbg-bottom-nav a, .sbg-bottom-nav button, .sbg-topbar button, .sbg-view-toggle button, .sbg-filter-toggle, .sbg-po-card>.sbg-po-overflow>button').evaluateAll(items=>items.filter(e=>e.getClientRects().length).map(e=>{const r=e.getBoundingClientRect();return {label:e.getAttribute('aria-label')||e.textContent.trim(),width:r.width,height:r.height};}));
            assert.ok(targets.every(target=>target.width>=44&&target.height>=44), `Mobile targets: ${width} ${JSON.stringify(targets)}`);
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
        results.push({width,height,...metrics,...dashboardMetrics}); save();
        if (phase === 'after') {
            assert.equal(metrics.overflow, false, `Page overflow at ${width}`);
            assert.equal(dashboardMetrics.fontLoaded,true,'Plus Jakarta Sans is loaded');
            assert.equal(dashboardMetrics.exportsInKpis, false, 'Exports belong in payment detail');
            assert.equal(dashboardMetrics.kpiValueOverflow, false, 'KPI values fit without wrapping');
            assert.ok(metrics.panel.top >= 0 && metrics.panel.bottom <= height+1, `Modal outside viewport at ${width}`);
            if (width >= 1280) {
                assert.equal(metrics.panel.scroll, false, `Common PO needs scroll at ${width}`);
                assert.ok(metrics.panel.width <= (width===1280||width===1366?780:820), `Modal too wide at ${width}`);
                assert.ok(dashboardMetrics.kpis.every(card=>card.height>=116&&card.height<=132), `KPI height at ${width}`);
                assert.ok(dashboardMetrics.filterHeight<=112, `Filter too tall at ${width}`);
                assert.ok(dashboardMetrics.listTop+130<height, `PO list must begin above fold at ${width}`);
                assert.ok(dashboardMetrics.rowHeights.every(h=>h>=52&&h<=60), `PO table row rhythm at ${width}`);
            }
            if (width < 768) assert.ok(metrics.panel.height<=height*.92+1, `Sheet exceeds 92dvh at ${width}`);
            await page.keyboard.press('Escape');
            await page.locator('#po-detail').waitFor({state:'hidden',timeout:3000}).catch(async error => { console.log(await page.evaluate(() => ({open:Alpine.$data(document.body).isDetailModalOpen,style:document.getElementById('po-detail').getAttribute('style'),active:document.activeElement.outerHTML.slice(0,200)}))); throw error; });
            assert.equal(await page.locator('#po-detail').isVisible(), false);
        }
    }
    save();
    if (phase === 'after') {
        const behaviors = {};
        await page.setViewportSize({width:1366,height:768});
        await page.goto(origin+'/dashboard');
        const firstRow = page.locator('.sbg-po-table tbody tr').first();
        await firstRow.locator('.sbg-po-overflow>button').click();
        assert.equal(await page.locator('#po-detail').isVisible(),false,'Overflow does not open row');
        await page.keyboard.press('Escape');
        await page.locator('#po-filter-search').fill('Semangka');
        await page.locator('#po-filter-status').selectOption('Selesai');
        await firstRow.click();
        await page.waitForFunction(()=>!Alpine.$data(document.body).detailLoading);
        await page.keyboard.press('Shift+Tab');
        assert.equal(await page.evaluate(()=>document.activeElement.closest('#po-detail')!==null),true,'Focus trapped backwards');
        await page.keyboard.press('Tab');
        assert.equal(await page.locator('.sbg-detail-close').evaluate(e=>e===document.activeElement),true,'Tab wraps to modal close');
        await page.keyboard.press('Escape');
        await page.locator('#po-detail').waitFor({state:'hidden'});
        assert.equal(await firstRow.evaluate(e=>e===document.activeElement),true,'Focus returns to row');
        assert.equal(await page.locator('#po-filter-search').inputValue(),'Semangka');
        assert.equal(await page.locator('#po-filter-status').inputValue(),'Selesai');
        behaviors.filterStateAndFocus=true;

        for (const card of await page.locator('.sbg-kpi-payment').all()) {
            await card.click();
            const modal = page.locator('.sbg-modal:visible');
            await modal.getByRole('button',{name:'Print',exact:true}).click();
            await modal.getByRole('button',{name:'Print Nota',exact:true}).waitFor({state:'visible'});
            await modal.getByRole('button',{name:'Download',exact:true}).click();
            await modal.getByRole('button',{name:'Download PDF',exact:true}).waitFor({state:'visible'});
            await page.keyboard.press('Escape');
            await modal.waitFor({state:'hidden'});
        }
        behaviors.exportsRelocated=true;

        await firstRow.click();
        await page.waitForFunction(()=>!Alpine.$data(document.body).detailLoading);
        page.once('dialog',dialog=>dialog.dismiss());
        await page.locator('.sbg-button-danger').click();
        assert.equal(await page.locator('#po-detail').isVisible(),true,'Delete cancel leaves PO intact');
        await page.locator('.sbg-management-actions').getByRole('button',{name:'Edit PO',exact:true}).click();
        const edit = page.locator('.sbg-modal:visible').filter({hasText:'Edit Purchase Order'});
        await edit.waitFor();
        assert.equal(await edit.locator('[name=item_name]').inputValue(),'Semangka');
        await page.keyboard.press('Escape');
        await edit.waitFor({state:'hidden'});
        behaviors.editAndDeleteSafety=true;

        await firstRow.click();
        await page.waitForFunction(()=>!Alpine.$data(document.body).detailLoading);
        await page.locator('.sbg-management-actions').getByRole('button',{name:'Buat Nota Dapur'}).click();
        const receipt = page.locator('#printable-kitchen-note');
        await receipt.waitFor();
        assert.ok((await receipt.textContent()).includes('PO-20261028-50HS'));
        await page.screenshot({path:path.join(output,'receipt-1366.png')});
        await page.keyboard.press('Escape');
        await receipt.waitFor({state:'hidden'});
        behaviors.kitchenReceipt=true;

        const detailRoutes=[];
        await page.route(/\/dashboard\/po\/\d+$/,route=>{detailRoutes.push(route);});
        await page.evaluate(order=>{Alpine.$data(document.body).openDetailModal(order);},orders[1].data);
        await page.evaluate(order=>{Alpine.$data(document.body).openDetailModal(order);},orders[2].data);
        for(let i=0;i<100&&detailRoutes.length<2;i++) await new Promise(resolve=>setTimeout(resolve,20));
        assert.equal(detailRoutes.length,2);
        await detailRoutes[1].fulfill({contentType:'application/json',body:JSON.stringify(orders[2])});
        await page.waitForFunction(()=>!Alpine.$data(document.body).detailLoading);
        await detailRoutes[0].fulfill({contentType:'application/json',body:JSON.stringify(orders[1])});
        await page.waitForTimeout(100);
        assert.equal(await page.evaluate(()=>Alpine.$data(document.body).selectedPo.id),2,'Slow earlier response must not replace current PO');
        await page.keyboard.press('Escape');
        await page.locator('#po-detail').waitFor({state:'hidden'});
        await page.unroute(/\/dashboard\/po\/\d+$/);
        await page.route(/\/dashboard\/po\/\d+$/,route=>route.fulfill({status:503,contentType:'application/json',body:'{}'}),{times:1});
        await firstRow.click();
        await page.locator('.sbg-detail-error').waitFor({state:'visible'});
        assert.equal(await page.locator('.sbg-payment-actions button').first().isDisabled(),true,'Failed detail disables payment');
        await page.locator('.sbg-detail-error button').click();
        await page.waitForFunction(()=>!Alpine.$data(document.body).detailLoading&&!Alpine.$data(document.body).detailError);
        assert.equal(await page.locator('.sbg-payment-actions button').first().isDisabled(),false);
        await page.evaluate(()=>{Alpine.$data(document.body).selectedPo.supplier_name='Koperasi Tani Buah Segar Sejahtera Cabang Garut Selatan';});
        await page.waitForTimeout(50);
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Long supplier wraps safely');
        await page.evaluate(()=>{Alpine.$data(document.body).selectedPo.notes='Catatan pengiriman tambahan. '.repeat(120);});
        await page.locator('.sbg-po-notes summary').click();
        assert.equal(await page.locator('.sbg-detail-panel').evaluate(e=>e.scrollHeight>e.clientHeight),true,'Exceptional notes can scroll');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
        await page.keyboard.press('Escape');
        await page.locator('#po-detail').waitFor({state:'hidden'});
        behaviors.detailRaceRetryAndLongNotes=true;

        // Count accepted submit events and prevent navigation on the isolated fixture server.
        await firstRow.click();
        await page.waitForFunction(()=>!Alpine.$data(document.body).detailLoading);
        await page.evaluate(()=>{
            window.sbgAcceptedPayments=0;
            document.querySelector('.sbg-payment-actions form').addEventListener('submit',event=>{
                if (!event.defaultPrevented) window.sbgAcceptedPayments++;
                event.preventDefault();
            });
        });
        page.once('dialog',dialog=>dialog.accept());
        await page.evaluate(()=>document.querySelector('.sbg-payment-actions form').requestSubmit());
        await page.evaluate(()=>document.querySelector('.sbg-payment-actions form').requestSubmit());
        assert.equal(await page.evaluate(()=>window.sbgAcceptedPayments),1);
        assert.equal(await page.locator('.sbg-payment-actions form').first().locator('button').isDisabled(),true);
        const payload=await page.evaluate(()=>Object.fromEntries(new FormData(document.querySelector('.sbg-payment-actions form'))));
        assert.equal(payload.payment_type,'partner_fee');
        assert.equal(payload._method,'PATCH');
        await page.keyboard.press('Escape');
        await page.locator('#po-detail').waitFor({state:'hidden'});
        behaviors.paymentReviewAndDoubleSubmit=true;

        await page.setViewportSize({width:390,height:844});
        await page.goto(origin+'/dashboard');
        await page.locator('.sbg-bottom-nav a[href="#data-po"]').click();
        await page.locator('.sbg-filter-toggle').click();
        await page.locator('#po-filter-options').waitFor();
        await page.screenshot({path:path.join(output,'filter-390.png')});
        await page.keyboard.press('Shift+Tab');
        assert.equal(await page.evaluate(()=>document.activeElement.closest('#po-filter-options')!==null),true,'Filter traps focus');
        await page.keyboard.press('Escape');
        await page.locator('#po-filter-options').waitFor({state:'hidden'});
        assert.equal(await page.locator('.sbg-filter-toggle').evaluate(e=>e===document.activeElement),true);
        await page.locator('.sbg-bottom-nav button').click();
        await page.waitForTimeout(260);
        await page.screenshot({path:path.join(output,'drawer-390.png')});
        assert.equal(await page.locator('#sbg-sidebar').evaluate(e=>e.inert),false);
        await page.keyboard.press('Escape');
        await page.waitForFunction(()=>!Alpine.$data(document.body).navOpen);
        assert.equal(await page.locator('#sbg-sidebar').evaluate(e=>e.inert),true,'Closed mobile drawer is not tabbable');
        behaviors.mobileFilterAndDrawer=true;

        for(const tab of ['calculate','master','archive']) {
            await page.evaluate(tab=>Alpine.$data(document.body).openFruitTab(tab),tab);
            await page.waitForFunction(()=>Alpine.$data(document.querySelector('.fruit-tool')).loaded);
            await page.screenshot({path:path.join(output,`calculator-${tab}-390.png`),fullPage:true});
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Calculator overflow '+tab);
        }
        behaviors.calculatorCatalogArchive=true;
        await page.emulateMedia({reducedMotion:'reduce'});
        assert.equal(await page.locator('.sbg-button').first().evaluate(e=>getComputedStyle(e).transitionDuration),'0s');
        behaviors.reducedMotion=true;
        await page.emulateMedia({reducedMotion:'no-preference'});
        await page.setViewportSize({width:1366,height:768});
        await page.goto(origin+'/dashboard?empty=1');
        assert.ok((await page.locator('.sbg-empty').first().textContent()).includes('Belum ada Purchase Order'));
        assert.equal(await page.locator('.sbg-kpi').count(),6);
        await page.screenshot({path:path.join(output,'empty-1366.png')});
        behaviors.zeroAndEmptyState=true;
        fs.writeFileSync(path.join(evidence,'behavior-results.json'),JSON.stringify(behaviors,null,2));
        save();
        assert.deepEqual(errors, []);
        console.log(`PASS ${results.length} viewports; ${Object.keys(behaviors).length} interaction/state checks; no browser errors.`);
    }
} finally {
    await browser.close(); server.close();
}
