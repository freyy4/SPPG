/* v2.4 presentation helpers. No financial calculation or persistence lives here. */
window.sbgFruitSymbol = name => {
    const value=String(name||'').toLowerCase();
    return /pisang|banana/.test(value)?'banana':/mangga|mango/.test(value)?'mango':/semangka|melon/.test(value)?'watermelon':/pear|pir/.test(value)?'pear':/alpukat|avocado/.test(value)?'avocado':/apel|apple/.test(value)?'apple':'produce';
};
window.sbgPlanningUI = () => ({
    candidateSearch:'', catalogSearch:'', catalogSort:'name', catalogPage:1,
    archiveSearch:'', archiveRequest:0,
    get catalogMatches() {
        const search=this.catalogSearch.trim().toLocaleLowerCase('id');
        const rows=this.fruits.filter(f=>`${f.name} ${f.source_region} ${f.unit}`.toLocaleLowerCase('id').includes(search));
        return rows.sort((a,b)=>this.catalogSort==='price' ? Number(a.price)-Number(b.price) || a.name.localeCompare(b.name,'id') : this.catalogSort==='price-desc' ? Number(b.price)-Number(a.price) || a.name.localeCompare(b.name,'id') : a.name.localeCompare(b.name,'id'));
    },
    get catalogPages() {return Math.max(1,Math.ceil(this.catalogMatches.length/10));},
    get catalogRows() {return this.catalogMatches.slice((Math.min(this.catalogPage,this.catalogPages)-1)*10,Math.min(this.catalogPage,this.catalogPages)*10);},
    get heroTitle() {return this.tab==='master'?'Katalog Buah':this.tab==='archive'?'Arsip Keputusan':'Kalkulator Buah Harian';},
    get heroCopy() {return this.tab==='master'?'Kelola buah, harga, dan standar porsi dalam satu tempat.':this.tab==='archive'?'Setiap pilihan tercatat. Temukan kembali keputusan menu harian Anda.':'Rencanakan menu buah yang tepat, segar, dan sesuai anggaran.';},
    fruitSymbol(name) {return window.sbgFruitSymbol(name);},
});
window.SbgReceipt = (() => {
    const safe=value=>String(value??'—').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const money=value=>'Rp '+Number(value||0).toLocaleString('id-ID');
    function markup(data) {
        const rows=data.items.map((item,i)=>`<tr><td>${i+1}</td><td><div class="sbg-receipt-fruit"><svg viewBox="0 0 80 80" aria-hidden="true"><use href="${location.origin}/images/sbg-fruits.svg#${window.sbgFruitSymbol(item.item_name)}"></use></svg><span><strong>${safe(item.item_name)}</strong><small>${safe(item.po_number)}</small></span></div></td><td>${safe(item.quantity)} ${safe(item.unit||'')}</td><td>${money(item.maker_price)}</td><td>${money(item.note_subtotal)}</td></tr>`).join('');
        return `<article class="sbg-receipt"><header><img src="${location.origin}/favicon.svg" alt="" width="52" height="52"><div><p>SEGAR • BERKUALITAS • TERPERCAYA</p><h1>SENTRA BUAH GARUT</h1><address>Jl. Ahmad Yani Timur Karangpawitan Garut</address></div></header><div class="sbg-receipt-meta"><strong>NOTA BUAH MITRA</strong><span>${safe(data.date)} · ${data.items.length} barang</span></div><table><thead><tr><th>No.</th><th>Item / Nomor PO</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>${rows}</tbody></table><div class="sbg-receipt-total"><span>TOTAL HARGA</span><strong>${money(data.total)}</strong></div><footer>${data.qr?`<img class="sbg-receipt-qr" src="${safe(data.qr)}" alt="QR verifikasi daftar barang"><strong>VERIFIKASI NOTA</strong><p>Scan untuk melihat daftar barang terpilih</p>`:'<p>QR verifikasi belum tersedia. Tutup pratinjau dan coba lagi.</p>'}<small>Dokumen dibuat melalui sistem Purchase Order Sentra Buah Garut.</small></footer></article>`;
    }
    function documentHtml(data) {return `<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nota Buah Mitra</title><link rel="stylesheet" href="${location.origin}/css/sbg-v24-receipt.css"></head><body>${markup(data)}</body></html>`;}
    return {markup,documentHtml};
})();
