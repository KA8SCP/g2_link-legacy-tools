'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync(__dirname + '/../dashboard/theme.js', 'utf8');
function page(saved, blocked) {
    const attrs = {}, handlers = {}, windowHandlers = {};
    const toggle = { hidden: true, setAttribute: (k,v) => { attrs[k] = v; }, addEventListener: (k,v) => { handlers[k] = v; } };
    const storage = { value: saved, getItem() { if (blocked) throw Error('blocked'); return this.value; }, setItem(k,v) { if (blocked) throw Error('blocked'); this.value = v; } };
    const root = {};
    const context = { window: {localStorage: storage, addEventListener: (k,v) => { windowHandlers[k] = v; }},
        document: {readyState:'complete',documentElement:{setAttribute:(k,v)=>{root[k]=v;}},getElementById:()=>toggle} };
    vm.runInNewContext(script, context);
    return {root,attrs,toggle,storage,click:handlers.click,sync:windowHandlers.storage};
}
let p = page(null,false);
assert.equal(p.root['data-theme'],'light');
assert.equal(p.toggle.hidden,false);
p.click(); assert.equal(p.root['data-theme'],'dark'); assert.equal(p.attrs['aria-pressed'],'true');
assert.equal(page(p.storage.value,false).root['data-theme'],'dark');
p.click(); assert.equal(p.storage.value,'light');
p.sync({key:'g2-dashboard-theme',newValue:'dark'}); assert.equal(p.root['data-theme'],'dark');
assert.equal(page('unexpected',false).root['data-theme'],'light');
p=page(null,true); p.click(); assert.equal(p.root['data-theme'],'dark');
console.log('Theme defaults, toggle, persistence, tab synchronization and blocked-storage checks passed.');
