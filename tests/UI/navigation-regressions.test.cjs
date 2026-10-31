const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '../..');
const source = file => fs.readFileSync(path.join(root, file), 'utf8');
// Execute real factories and Blade expressions. Only browser/Alpine scheduling,
// disabled-click semantics and transport are modeled; no browser claim is made.
function harness(hash) {
    const dashboard = source('resources/views/dashboard.blade.php');
    const template = source('resources/views/components/fruit-calculator.blade.php');
    const ticks = [], watchers = [], calls = [], pushes = [], scrolls = [];
    let factory, parent, fruit, visiblePage;
    const c = {window:{}, innerWidth:1366, location:{hash,origin:'http://test.invalid'}, console,
        formatCurrencyValue:value => String(value), crypto:require('node:crypto').webcrypto,
        ResizeObserver:class {observe(){} disconnect(){}}, IntersectionObserver:class {observe(){} disconnect(){}},
        document:{body:{style:{}},addEventListener(event,fn){if(event === 'alpine:init') fn();},
            getElementById(id){return {click(){if(!fruit.loading && !fruit.busy) run(clickExpression(id),fruit);}};}},
        Alpine:{data(_name,fn){factory=fn;}},
        history:{pushState(_a,_b,value){c.location.hash=value;pushes.push(value);},replaceState(_a,_b,value){c.location.hash=value;}}};
    c.window.scrollTo = options => {c.scrollY=options.top;scrolls.push({top:options.top,visiblePage});};
    vm.createContext(c);
    function run(expression,scope,event) {c.scope=scope;c.event=event;return vm.runInContext('with(scope) { '+expression+' }', c);}
    function attribute(text,name) {return text.match(new RegExp(name+'="([^"]*)"'))?.[1];}
    function clickExpression(id) {return attribute(template.match(new RegExp('<button id="'+id+'"[^>]*>'))[0], '@click');}
    function dispatch(name,detail) {
        const text = name === 'fruit-navigation-request' ? template : dashboard;
        const expression = attribute(text, '@'+name+'\\.window');
        if(expression) run(expression,name === 'fruit-navigation-request' ? fruit : parent,{detail});
    }
    function reactive(raw,fallback) {return new Proxy(raw,{
        get(target,key,receiver){if(key === '$event')return c.event;return key in target ? Reflect.get(target,key,receiver) : fallback?.[key];},
        has(target,key){return key in target || (fallback && key in fallback) || key === '$event';},
        set(target,key,value){const old=target[key];target[key]=value;if(old!==value) for(const w of watchers.filter(w=>w.key===key)) ticks.push(()=>w.fn(value,old));return true;}
    });}
    for(const file of ['sbg-navigation.js','sbg-v24.js','fruit-calculator.js']) vm.runInContext(source('public/js/'+file),c);
    const open = dashboard.match(/            openFruitTab\(tab\) \{[\s\S]*?\n            \},/)[0].trim().slice(0,-1);
    parent=reactive({...c.window.sbgNavigation(),...vm.runInContext('({'+open+'})',c),activeView:'dashboard',mobilePage:'home',navOpen:true,
        $nextTick(fn){ticks.push(fn);},$dispatch:dispatch});
    const raw=factory({region:'Pusat',base:'/fruit',csrf:'test'});
    Object.assign(raw,{$nextTick(fn){ticks.push(fn);},$dispatch:dispatch,$watch(key,fn){watchers.push({key,fn});},
        $refs:{priceChart:{parentElement:{}},comparisonPanel:{},parameterDialog:{showModal(){},querySelector(){return {focus(){}};}}},
        api(url=''){calls.push(url);return new Promise(resolve=>pending.push({url,resolve}));}});
    const pending=[];
    fruit=reactive(raw,parent);
    fruit.init();
    run(attribute(template,'x-init'),fruit);
    let charts=0;fruit.drawChart=()=>{charts++;};
    async function flush(){while(ticks.length){visiblePage=parent.activeView+':'+parent.mobilePage;ticks.shift()();}await Promise.resolve();}
    async function resolvePending(){for(const request of pending.splice(0)) request.resolve(request.url === '/decisions' ? {id:99} : request.url ? {data:[],current_page:1,last_page:1} : {fruits:[]});await Promise.resolve();await flush();}
    return {c,parent,fruit,calls,pushes,scrolls,flush,resolvePending,get charts(){return charts;},click(tab){if(!fruit.loading&&!fruit.busy)run(clickExpression('fruit-tab-'+tab),fruit);}};
}

test('NAV-03 inner tabs and edit synchronize sidebar/hash once; history replay avoids loops and duplicate fetches', async()=>{
    const h=harness('#katalog-buah');h.parent.syncNavigation();await h.flush();await h.resolvePending();
    h.click('archive');await h.flush();
    assert.equal(h.c.location.hash,'#arsip-keputusan','real internal archive tab updates URL');
    assert.equal(h.parent.fruitNavigationTab,h.fruit.tab);
    assert.equal(h.parent.navigationActive('arsip-keputusan'),true);
    assert.equal(h.parent.navigationActive('katalog-buah'),false);
    assert.deepEqual(h.pushes,['#arsip-keputusan']);
    assert.equal(h.calls.filter(url=>url.startsWith('/decisions')).length,1);
    await h.resolvePending();
    const before=h.calls.length;
    h.parent.syncNavigation();await h.flush();
    assert.equal(h.calls.length,before,'echoed sync must not reload archive');
    assert.equal(h.pushes.length,1,'echoed sync must not grow history');
    h.c.location.hash='#katalog-buah';h.parent.syncNavigation();await h.flush();
    assert.equal(h.fruit.tab,'master');assert.equal(h.pushes.length,1,'back replay adds no history');
    assert.equal(h.parent.navigationActive('katalog-buah'),true);
    h.fruit.edit({id:7,name:'Apel',price:100});await h.flush();
    assert.equal(h.fruit.tab,'calculate');assert.equal(h.c.location.hash,'#kalkulator-buah','edit-triggered transition is observed too');
    assert.equal(h.parent.navigationActive('kalkulator-buah'),true);
    assert.equal(h.fruit.editor.id,7);
    assert.deepEqual(h.pushes,['#arsip-keputusan','#kalkulator-buah']);
    assert.equal(h.calls.length,before,'state synchronization never calls APIs');
    h.c.location.hash='#arsip-keputusan';h.parent.syncNavigation();await h.flush();
    assert.equal(h.fruit.tab,'archive');assert.equal(h.pushes.length,2,'forward replay adds no history');
    assert.equal(h.calls.filter(url=>url.startsWith('/decisions')).length,2,'one fetch on actual archive re-entry');
    await h.resolvePending();
});

test('NAV-03 decision completion reflects archive without replaying its existing data operation', async()=>{
    const h=harness('#kalkulator-buah');h.parent.syncNavigation();await h.flush();await h.resolvePending();
    Object.assign(h.fruit,{budget:'100',pb:1,pk:0});
    const choosing=h.fruit.choose({id:7,within_budget:true});
    await h.resolvePending();await h.resolvePending();await choosing;await h.flush();
    assert.equal(h.fruit.tab,'archive');assert.equal(h.c.location.hash,'#arsip-keputusan');
    assert.equal(h.parent.navigationActive('arsip-keputusan'),true);
    assert.deepEqual(h.pushes,['#arsip-keputusan']);
    assert.equal(h.calls.filter(url=>url==='/decisions').length,1,'one existing decision request in transport model');
    assert.equal(h.calls.filter(url=>url.startsWith('/decisions?')).length,1,'one existing archive request, not duplicated by synchronization');
});

test('NAV-02 mobile switches reset nonzero scroll only after destination visibility update', async()=>{
    const h=harness('#data-po');h.c.innerWidth=390;
    for(const [fragment,page] of [['ringkasan','home'],['data-po','orders']]) {
        h.c.scrollY=900;h.parent.navigateFragment(fragment);
        assert.equal(h.scrolls.length,fragment==='ringkasan'?0:1,'no synchronous scroll before render tick');
        await h.flush();
        assert.deepEqual(h.scrolls.at(-1),{top:0,visiblePage:'dashboard:'+page});
        assert.equal(h.c.scrollY,0);
    }
    assert.equal(h.scrolls.length,2);
});

for(const [hash,tab] of [['#katalog-buah','master'],['#arsip-keputusan','archive']]) {
    test(`NAV-01 cold ${tab} deep link selects real tab while initial data is pending`, async()=>{
        const h=harness(hash);h.parent.syncNavigation();await h.flush();
        assert.equal(h.fruit.loading,true,'initial transport deliberately pending');
        assert.equal(h.fruit.tab,tab,'disabled tabs must not drop deep-link request');
        assert.equal(h.parent.navigationActive(hash.slice(1)),true);
        await h.resolvePending();assert.equal(h.fruit.tab,tab);
        assert.equal(h.calls.filter(url=>url==='').length,1,'one initial catalog fetch');
        assert.equal(h.calls.filter(url=>url.startsWith('/decisions')).length,tab==='archive'?1:0);
        assert.equal(h.charts,tab==='master'?1:0,'catalog keeps its drawChart hook');
        assert.equal(h.pushes.length,0,'cold sync must not add history entries');
    });
}
