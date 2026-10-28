/* SBG v2.3: only master-data dialogs and the shared navigation shell. */
window.sbgV23 = () => ({
    sidebarCollapsed: innerWidth >= 1024 && innerWidth < 1200,
    masterSearch: '', masterRole: '', masterStatus: '', masterView: 'table',
    v23Errors: {}, v23Error: '', v23Busy: false, v23Saved: false, v23Conflict: null,
    passwordVisible: false, editInfo: {},
    init() {
        const key = `sbg-v23-${document.body.dataset.accountId}`;
        try {
            const saved = JSON.parse(sessionStorage.getItem(key) || '{}');
            for (const name of ['sidebarCollapsed','masterSearch','masterRole','masterStatus','masterView']) if (name in saved) this[name] = saved[name];
        } catch (_) { /* UI preferences are optional. */ }
        const persist = () => { try { sessionStorage.setItem(key,JSON.stringify(Object.fromEntries(['sidebarCollapsed','masterSearch','masterRole','masterStatus','masterView'].map(k=>[k,this[k]])))); } catch (_) {} };
        for (const field of ['sidebarCollapsed','masterSearch','masterRole','masterStatus','masterView']) this.$watch(field,persist);
        this.$watch('desktopNav', () => { this.navOpen = false; });
    },
    masterMatches(data) {
        const normalize = text => String(text || '').toLocaleLowerCase('id').replaceAll('_',' ');
        return normalize(data.search).includes(normalize(this.masterSearch).trim()) && (!this.masterRole || data.role === this.masterRole) && (!this.masterStatus || data.status === this.masterStatus);
    },
    countMaster() {
        // Reading filters here makes the getter reactive even with no rows.
        void [this.masterSearch,this.masterRole,this.masterStatus];
        return [...document.querySelectorAll('[data-master-row]')].filter(e=>this.masterMatches(e.dataset)).length;
    },
    resetV23() { this.v23Errors={}; this.v23Error=''; this.v23Busy=false; this.v23Saved=false; this.v23Conflict=null; },
    v23OpenPopup(account) {
        this.resetV23();
        this.popupTarget = {accountId:account.accountId, accountName:account.accountName,title:account.title||'',message:account.message||'',enabled:account.enabled==='1',version:account.version||''};
        this.isLoginPopupSettingsOpen=true;
    },
    v23OpenEdit(region, account) {
        this.resetV23(); this.passwordVisible=false;
        this.editAdminForm={region_id:region.id,account_id:account?.id,code:region.code||'',name:region.name||'',role:account?.role||'admin',admin_name:account?.name||'',admin_email:account?.email||''};
        this.editInfo={since:account?.created_at,poCount:account?.po_count??0};
        const password=document.querySelector('#v23-edit-password'); if(password) password.value='';
        this.isEditAdminModalOpen=true;
    },
    async submitV23(form, kind) {
        if(this.v23Busy) return;
        this.v23Errors={};this.v23Error='';this.v23Conflict=null;
        for(const field of form.querySelectorAll('input:not([type=hidden]),select,textarea')) {
            if(field.disabled || field.validity.valid) continue;
            this.v23Errors[field.name]=[field.validity.valueMissing?'Kolom ini wajib diisi.':field.validity.typeMismatch?'Masukkan alamat email yang valid.':'Periksa panjang atau format nilai ini.'];
        }
        if(kind==='popup' && this.popupTarget.enabled && !this.popupTarget.title.trim() && !this.popupTarget.message.trim()) this.v23Errors.title=['Isi judul atau pesan sebelum mengaktifkan popup.'];
        if(Object.keys(this.v23Errors).length) { this.$nextTick(()=>form.querySelector(`[name="${Object.keys(this.v23Errors)[0]}"]`)?.focus());return; }
        this.v23Busy=true;
        try {
            const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            const result=await response.json().catch(()=>({message:response.status===419?'Sesi telah berakhir. Salin draf Anda sebelum memuat ulang.':'Respons server tidak dapat dibaca. Coba lagi.'}));
            if(!response.ok) {
                this.v23Errors=result.errors||{};this.v23Error=result.message||'Penyimpanan gagal. Draf Anda tetap tersedia.';
                if(response.status===409) this.v23Conflict=result.current;
                return;
            }
            this.v23Saved=true;
            // Reload authoritative records; session-scoped filters and hash remain.
            setTimeout(()=>location.reload(),180);
        } catch (_) { this.v23Error='Koneksi gagal. Nilai belum disimpan; periksa koneksi dan coba lagi.'; }
        finally { this.v23Busy=false; }
    },
    loadPopupServerVersion() {
        if(!this.v23Conflict) return;
        Object.assign(this.popupTarget,this.v23Conflict);this.v23Conflict=null;this.v23Errors={};this.v23Error='Versi server dimuat. Tinjau kembali sebelum menyimpan.';
    },
    async toggleMasterPopup(form) {
        if (form.dataset.busy) return;
        const button=form.querySelector('button'), error=form.closest('.sbg-v23-popup-actions-wrap').querySelector('[data-toggle-error]');
        form.dataset.busy='1';button.disabled=true;button.setAttribute('aria-busy','true');error.textContent='';
        try {
            const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json'},credentials:'same-origin'});
            const data=await response.json();
            if(!response.ok) {error.textContent=data.message||'Perubahan gagal. Coba lagi melalui Atur.';return;}
            location.hash='master-data';location.reload();
        } catch (_) {error.textContent='Koneksi gagal. Status belum berubah; coba lagi.';}
        finally {delete form.dataset.busy;button.disabled=false;button.removeAttribute('aria-busy');}
    },
    exportMaster() {
        const rows=[...document.querySelectorAll('[data-master-row]')].filter(e=>this.masterMatches(e.dataset));
        const escape=value=>'"'+String(value).replace(/^[=+@\-\t\r]/,"'$&").replaceAll('"','""')+'"';
        const lines=[['Kode','Daerah / Dapur','Penanggung Jawab','Email / Role','Tanggal Dibuat','Status'],...rows.map(r=>[...r.cells].slice(0,6).map(c=>c.innerText.trim().replaceAll('\n',' ')))];
        const url=URL.createObjectURL(new Blob(['\uFEFF'+lines.map(r=>r.map(escape).join(',')).join('\r\n')],{type:'text/csv;charset=utf-8'}));
        const a=document.createElement('a');a.href=url;a.download='master-data-daerah.csv';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
    },
});
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.sbg-sidebar nav a,.sbg-sidebar nav>button,.sbg-sidebar-bottom>button').forEach((item,index)=>{
        const label=item.textContent.trim();item.setAttribute('aria-label',label);
        for(const node of [...item.childNodes]) if(node.nodeType===Node.TEXT_NODE && node.textContent.trim()) {const span=document.createElement('span');span.className='sbg-v23-nav-label';span.textContent=node.textContent;node.replaceWith(span);}
        const tip=document.createElement('span');tip.className='sbg-v23-nav-tip';tip.role='tooltip';tip.id=`sbg-v23-nav-tip-${index}`;tip.textContent=label;item.append(tip);item.setAttribute('aria-describedby',tip.id);
    });
});
