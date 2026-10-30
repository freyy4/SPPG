const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../../public/js/sbg-v25-po.js'), 'utf8');

function fixture() {
    let confirmations = 0, accepted = false;
    const context = { formatCurrencyValue: value => Number(value || 0).toLocaleString('de-DE'), confirm() { confirmations++; return accepted; } };
    vm.runInNewContext(source, context);
    const seed = { item_name: 'Seed fruit', notes: 'Seed notes', transaction_date: '2026-10-30', quantity_sbg: 3, quantity_b: 4, supplier_price: 1250, supplier_price_b: 1500, maker_price: 2250, partner_margin: 250 };
    const form = context.sbgPoForm('create', seed, { quantity: ['Initial validation'] });
    form.$el = { querySelector: () => ({ removeAttribute() {} }) };
    form.isCreateModalOpen = true;
    form.init();
    return { form, seed, accept() { accepted = true; }, confirmations: () => confirmations };
}
const plain = value => JSON.parse(JSON.stringify(value));
function edit(form) {
    Object.assign(form.draft, { item_name: 'Discard fruit', notes: 'Discard notes', quantity_sbg: 99, quantity_b: 77, supplier_price: '9.999', supplier_price_b: '8.888', maker_price: '7.777', partner_margin: '6.666' });
    form.errors = { maker_price: ['Invalid price'] };
    form.message = 'Fix invalid price';
}

test('create rejected close preserves draft; accepted discard restores initial seed and clears UI before reopening', () => {
    const f = fixture(), original = plain(f.form.draft);
    edit(f.form);
    const changed = plain(f.form.draft), errors = plain(f.form.errors), baseline = f.form.baseline;
    f.form.requestClose();
    assert.equal(f.form.isCreateModalOpen, true);
    assert.deepEqual(plain(f.form.draft), changed);
    assert.deepEqual(plain(f.form.errors), errors);
    assert.equal(f.form.message, 'Fix invalid price');
    assert.equal(f.form.baseline, baseline);
    assert.equal(f.confirmations(), 1);
    f.accept();
    f.form.requestClose();
    assert.equal(f.form.isCreateModalOpen, false);
    assert.deepEqual(plain(f.form.draft), original);
    assert.deepEqual(plain(f.form.errors), {});
    assert.equal(f.form.message, '');
    assert.equal(f.form.dirty(), false);
    assert.deepEqual(f.seed, { item_name: 'Seed fruit', notes: 'Seed notes', transaction_date: '2026-10-30', quantity_sbg: 3, quantity_b: 4, supplier_price: 1250, supplier_price_b: 1500, maker_price: 2250, partner_margin: 250 });
    f.form.isCreateModalOpen = true;
    f.form.requestClose();
    assert.equal(f.confirmations(), 2, 'reopening without changes must not prompt again');
});

test('busy create close never confirms, resets, or hides the draft', () => {
    const f = fixture();
    edit(f.form);
    f.accept();
    f.form.busy = true;
    const draft = plain(f.form.draft), errors = plain(f.form.errors), baseline = f.form.baseline;
    f.form.requestClose();
    assert.equal(f.confirmations(), 0);
    assert.equal(f.form.isCreateModalOpen, true);
    assert.deepEqual(plain(f.form.draft), draft);
    assert.deepEqual(plain(f.form.errors), errors);
    assert.equal(f.form.message, 'Fix invalid price');
    assert.equal(f.form.baseline, baseline);
});

test('X, Cancel, Escape, and backdrop bindings all call the exercised close handler', () => {
    const template = fs.readFileSync(path.resolve(__dirname, '../../resources/views/components/sbg/po-form-modal.blade.php'), 'utf8');
    assert.match(template, /@click\.self="requestClose\(\)"/);
    assert.match(template, /@keydown\.escape\.stop\.prevent="requestClose\(\)"/);
    assert.match(template, /@click="requestClose\(\)"[^>]*data-dialog-close/);
    assert.match(template, /@click="requestClose\(\)"[^>]*sbg-po25-cancel/);
});
