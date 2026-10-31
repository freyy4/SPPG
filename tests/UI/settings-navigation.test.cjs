const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
function context(hash = '#ringkasan') {
    const events = {};
    const c = {window:{}, innerWidth:1366, innerHeight:768, location:{hash}, document:{body:{dataset:{accountId:'42'}},addEventListener(){},querySelectorAll(){return []}}, sessionStorage:{getItem(){return '{"sidebarCollapsed":true}'},setItem(){}}, addEventListener(name,fn){events[name]=fn}, requestAnimationFrame(fn){fn()}, console};
    vm.createContext(c);
    const file=path.join(__dirname,'../../public/js/sbg-navigation.js');
    if(fs.existsSync(file)) vm.runInContext(fs.readFileSync(file,'utf8'),c);
    vm.runInContext(fs.readFileSync(path.join(__dirname,'../../public/js/sbg-v23.js'),'utf8'),c);
    c.events=events; return c;
}
test('native dashboard fragments resolve correct view and active navigation', () => {
    for(const [hash,view,tab,page] of [['#ringkasan','dashboard',null,'home'],['#data-po','dashboard',null,'orders'],['#master-data','dashboard',null,'admin'],['#katalog-buah','fruit','master',null],['#arsip-keputusan','fruit','archive',null],['#kalkulator-buah','fruit','calculate',null]]) {
        const c=context(hash); const state={...c.window.sbgV23(),activeView:'dashboard',mobilePage:'home',navOpen:true,openFruitTab(value){this.tab=value;this.activeView='fruit';this.navOpen=false}};
        state.syncNavigation(); assert.equal(state.activeView,view); if(tab) assert.equal(state.tab,tab);if(page)assert.equal(state.mobilePage,page);assert.equal(state.navOpen,false);assert.equal(state.navigationActive(hash.slice(1)),true);
    }
});
test('clicking the current fragment still restores its view',()=>{
    const c=context('#ringkasan');c.history={pushState(_a,_b,hash){c.location.hash=hash}};const state={...c.window.sbgV23(),activeView:'fruit',mobilePage:'orders',navOpen:true};state.navigateFragment('ringkasan');assert.equal(state.activeView,'dashboard');assert.equal(state.mobilePage,'home');assert.equal(state.navOpen,false);
});
test('settings and dashboard restore same account collapse preference',()=>{
    const c=context();const watched=[];const state={...c.window.sbgV23(),$watch(field,fn){watched.push(field)},$nextTick(){},desktopNav:true};state.init();assert.equal(state.sidebarCollapsed,true);assert.ok(watched.includes('sidebarCollapsed'));
});
test('account dropdown is clamped inside short viewport',()=>{
    const c=context();c.innerWidth=390;c.innerHeight=320;
    const props={};const state={...c.window.sbgAccountMenu(),$refs:{accountTrigger:{getBoundingClientRect(){return {left:10,right:54,top:270,bottom:314}}},accountPanel:{offsetWidth:300,offsetHeight:450,style:{setProperty(k,v){props[k]=v}}}}};
    state.positionAccount();assert.equal(props['--sbg-menu-left'],'10px');assert.equal(props['--sbg-menu-top'],'8px');
});
