import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.SBG_PLAYWRIGHT_MODULE||'playwright');
const root=process.env.SBG_BROWSER_EVIDENCE||'/var/www/data/sbg-v24-qa';
const phase=process.env.SBG_BROWSER_PHASE||'after';
const fixtures=path.join(root,'fixtures'), output=path.join(root,phase);
fs.mkdirSync(output,{recursive:true});
let origin;
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,origin); res.setHeader('Cache-Control','no-store');
 if(url.pathname==='/dashboard'){res.setHeader('Content-Type','text/html'); return res.end(fs.readFileSync(path.join(fixtures,'dashboard.html'),'utf8').replaceAll('https://sbg.my.id',origin).replaceAll('http://localhost',origin));}
 if(url.pathname.includes('verification-link')){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({url:origin+'/verification/synthetic?signature=fixture-only'}));}
 if(url.pathname.startsWith('/dashboard/fruit-calculator')){res.setHeader('Content-Type','application/json'); return res.end(fs.readFileSync(path.join(fixtures,url.pathname.endsWith('/decisions')?'archive.json':'calculator.json')));}
 const file=path.resolve('public','.'+decodeURIComponent(url.pathname));
 if(file.startsWith(path.resolve('public')+path.sep)&&fs.existsSync(file)&&fs.statSync(file).isFile()){res.setHeader('Content-Type',({'.css':'text/css','.js':'text/javascript','.svg':'image/svg+xml','.webp':'image/webp','.png':'image/png'})[path.extname(file)]||'application/octet-stream');return res.end(fs.readFileSync(file));}
 res.writeHead(404);res.end();
});
await new Promise(r=>server.listen(0,'127.0.0.1',r)); origin=`http://127.0.0.1:${server.address().port}`;
const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
const page=await browser.newPage(); const errors=[],results=[];
page.on('pageerror',e=>errors.push(e.message));
const matrix=phase==='before'?[[1440,900],[1366,768],[390,844]]:[[1920,1080],[1440,900],[1366,768],[1280,720],[1024,768],[834,1194],[430,932],[390,844],[360,800]];
async function capture(surface,width,height){await page.waitForTimeout(260);await page.screenshot({path:path.join(output,`${surface}-${width}.png`)});const metric=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth+1,dialogs:[...document.querySelectorAll('.sbg-modal')].filter(e=>e.getClientRects().length).map(e=>{const p=e.querySelector('.sbg-modal-panel');const r=p?.getBoundingClientRect();return r?{top:r.top,bottom:r.bottom,width:r.width}:null}).filter(Boolean)}));results.push({surface,width,height,...metric});if(phase==='after'){assert.equal(metric.overflow,false,`${surface} page overflow ${width}`);for(const d of metric.dialogs)assert.ok(d.top>=-1&&d.bottom<=height+1,`${surface} dialog outside ${width}: ${JSON.stringify(d)}`);}}
try{
for(const [width,height]of matrix){
 await page.setViewportSize({width,height});await page.goto(origin+'/dashboard');await page.waitForFunction(()=>window.Alpine&&document.body._x_dataStack?.length);await page.evaluate(()=>document.fonts.ready);
 await capture('announcement',width,height);await page.keyboard.press('Escape');
 await page.locator('.sbg-topbar .sbg-account-toggle').click();await capture('account',width,height);await page.keyboard.press('Escape');await page.mouse.click(1,1);
 for(const [type,surface]of [['Total Nilai PO','total-po'],['PO Selesai','completed-po']]){await page.evaluate(type=>Alpine.$data(document.body).openSummaryModal(type),type);await capture(surface,width,height);await page.keyboard.press('Escape');}
 await page.evaluate(()=>Alpine.$data(document.body).openFruitTab('calculate'));await page.waitForTimeout(400);
 await page.evaluate(()=>{const f=Alpine.$data(document.querySelector('.fruit-tool'));f.budget=10000000;f.budgetInput='10.000.000';f.pb=100;f.pk=50;f.selectedIds=f.fruits.slice(0,3).map(f=>f.id);});
 await capture('calculator',width,height);
 for(const [tab,surface]of [['master','catalog'],['archive','archive']]){await page.evaluate(async tab=>{const f=Alpine.$data(document.querySelector('.fruit-tool'));f.tab=tab;if(tab==='archive')await f.loadArchive(1);},tab);await capture(surface,width,height);}
 if(phase==='after'){
 console.log('State',width,await page.evaluate(()=>({rootKeys:Object.keys(Alpine.$data(document.body)),fruitReady:Alpine.$data(document.querySelector('.fruit-tool')).loaded})));
 await page.evaluate(()=>{const data=Alpine.$data(document.body);const items=data.summaryPoData.slice(0,10);window.dispatchEvent(new CustomEvent('open-partner-receipt',{detail:{items:items.map(i=>({...i,note_subtotal:i.ui_po_value})),total:items.reduce((s,i)=>s+i.ui_po_value,0),qr:'',verificationUrl:'',date:'29/10/2026'}}));});await capture('receipt',width,height);await page.keyboard.press('Escape');
 }
}
assert.deepEqual(errors,[],'No runtime errors');
}finally{fs.writeFileSync(path.join(root,`${phase}-results.json`),JSON.stringify({results,errors},null,2));await browser.close();server.close();}
