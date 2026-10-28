<div class="sbg-po-overflow" x-data="{ menuOpen: false }" @click.stop @keydown.escape.stop.prevent="menuOpen = false; $refs.poMenu.focus()" @click.outside="menuOpen = false">
    <button type="button" class="sbg-icon-button" x-ref="poMenu" @click="menuOpen = !menuOpen; if (menuOpen) $nextTick(() => $refs.firstAction.focus())" :aria-expanded="menuOpen" aria-label="Aksi PO {{ $po->po_number }}"><x-sbg.icon name="more" :size="18"/></button>
    <div x-cloak x-show="menuOpen" class="sbg-po-menu"><button type="button" x-ref="firstAction" @click="menuOpen = false; openDetailModal({{ json_encode($po) }})">Lihat detail</button><button type="button" @click="menuOpen = false; openEditModal({{ json_encode($po) }})">Edit PO</button></div>
</div>
