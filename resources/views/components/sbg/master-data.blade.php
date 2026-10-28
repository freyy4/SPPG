@php
    // Existing non-deleted accounts only. No invented activation state.
    $scopedAccounts = \App\Models\User::whereIn('region', $regions->pluck('name'))->whereIn('role', ['admin', 'kepala_dapur'])->orderBy('name')->get();
    $accountsByRegion = $scopedAccounts->groupBy('region');
    $masterRows = collect();
    foreach ($superAdmins as $account) $masterRows->push(['region' => null, 'account' => $account]);
    foreach ($regions as $reg) {
        $accounts = $accountsByRegion->get($reg->name, collect());
        if ($accounts->isEmpty()) $masterRows->push(['region' => $reg, 'account' => null]);
        foreach ($accounts as $account) $masterRows->push(['region' => $reg, 'account' => $account]);
    }
    $poCounts = \App\Models\PurchaseOrder::whereIn('created_by', $scopedAccounts->pluck('id'))->selectRaw('created_by, count(*) as total')->groupBy('created_by')->pluck('total', 'created_by');
    $emptyRegions = $regions->filter(fn($r) => $accountsByRegion->get($r->name, collect())->isEmpty())->count();
@endphp
<section id="master-data" x-show="mobilePage === 'admin'" class="sbg-v23-master sbg-admin-panel" :class="masterView === 'cards' ? 'is-cards' : ''">
    <div class="sbg-v23-hero">
        <div><p class="sbg-v23-breadcrumb">Master Data <span aria-hidden="true">›</span> Daerah & Penanggung Jawab</p><h1>Daftar Daerah & Penanggung Jawab</h1><p>Kelola seluruh akun Admin dan Kepala Dapur {{ auth()->user()->isSuperAdmin() ? 'di semua wilayah' : 'di wilayah Anda' }}.</p></div>
        <img src="{{ asset('images/sbg-v23/hero.webp') }}" alt="" width="340" height="140" class="sbg-v23-hero-art">
        <button type="button" @click="isAddAdminModalOpen = true" class="sbg-button sbg-button-primary"><x-sbg.icon name="plus" :size="18"/>Tambah Baru</button>
    </div>
    <div class="sbg-v23-kpis" aria-label="Ringkasan master data">
        @foreach([
            ['Total Daerah', $regions->count(), 'Wilayah terdaftar', 'users', 'green'],
            ['Akun Terdaftar', $scopedAccounts->count() + $superAdmins->count(), 'Akun yang tidak dihapus', 'users', 'orange'],
            ['Kepala Dapur', $scopedAccounts->where('role','kepala_dapur')->count(), 'Sesuai role akun', 'home', 'blue'],
            ['Belum Ada Akun', $emptyRegions, 'Daerah tanpa penanggung jawab', 'shield', 'red'],
        ] as [$label,$value,$hint,$icon,$tone])
        <article class="sbg-v23-kpi" data-tone="{{ $tone }}"><span class="sbg-v23-kpi-icon"><x-sbg.icon :name="$icon" :size="24"/></span><div><p>{{ $label }}</p><strong>{{ $value }}</strong><small>{{ $hint }}</small></div></article>
        @endforeach
    </div>
    <div class="sbg-v23-data-surface">
        <div class="sbg-v23-filters" role="search" aria-label="Filter daerah dan akun">
            <label class="sbg-v23-search"><x-sbg.icon name="search" :size="18"/><input id="master-search" type="search" x-model="masterSearch" placeholder="Cari daerah, nama, email, atau role…" aria-label="Cari daerah atau penanggung jawab"></label>
            <select x-model="masterRole" aria-label="Filter role"><option value="">Semua Role</option>@if(auth()->user()->isSuperAdmin())<option value="super_admin">Super Admin</option>@endif<option value="admin">Admin</option><option value="kepala_dapur">Kepala Dapur</option></select>
            <select x-model="masterStatus" aria-label="Filter status akun"><option value="">Semua Status</option><option value="registered">Terdaftar</option><option value="empty">Belum ada akun</option></select>
            <div class="sbg-v23-filter-actions"><button type="button" class="sbg-button sbg-button-outline" @click="masterSearch = ''; masterRole = ''; masterStatus = ''">Reset</button><div class="sbg-v23-view"><button type="button" @click="masterView = 'table'" :aria-pressed="masterView === 'table'" aria-label="Tampilan tabel"><x-sbg.icon name="po" :size="17"/></button><button type="button" @click="masterView = 'cards'" :aria-pressed="masterView === 'cards'" aria-label="Tampilan kartu"><x-sbg.icon name="grid" :size="17"/></button></div><button type="button" class="sbg-button sbg-button-outline" @click="exportMaster()"><x-sbg.icon name="download" :size="16"/>Export</button></div>
        </div>
        <p class="sbg-v23-status-note">Status “Terdaftar” berarti akun belum dihapus, bukan status aktivasi.</p>
        <div class="sbg-v23-table-scroll"><table class="sbg-v23-table"><thead><tr><th>Kode</th><th>Nama Daerah / Dapur</th><th>Penanggung Jawab</th><th>Email / Role</th><th>Tanggal Dibuat</th><th>Status</th>@if(auth()->user()->isSuperAdmin())<th>Popup Login</th>@endif<th>Aksi</th></tr></thead><tbody>
        @foreach($masterRows as $row)
            @php
                $reg = $row['region']; $account = $row['account'];
                $code = $reg ? ($reg->code ?: '—') : 'SUPER'; $name = $reg?->name ?? ($account?->region ?? 'Pusat');
                $roleLabel = match($account?->role) {'super_admin'=>'Super Admin', 'kepala_dapur'=>'Kepala Dapur', 'admin'=>'Admin', default=>'Belum ada akun'};
                $searchText = implode(' ', [$code,$name,$account?->name,$account?->email,$roleLabel,$account?->role]);
                $safeAccount = $account?->only(['id','name','email','role','created_at']);
                if ($safeAccount) $safeAccount['po_count'] = $poCounts->get($account->id, 0);
            @endphp
            <tr data-master-row data-search="{{ $searchText }}" data-role="{{ $account?->role }}" data-status="{{ $account ? 'registered' : 'empty' }}" x-show="masterMatches($el.dataset)">
                <td data-label="Kode"><span class="sbg-v23-code">{{ $code }}</span></td>
                <td data-label="Daerah / Dapur"><strong>{{ $name }}</strong></td>
                <td data-label="Penanggung Jawab">@if($account)<span class="sbg-v23-person"><span class="sbg-v23-avatar" aria-hidden="true">{{ mb_substr($account->name,0,1) }}</span>{{ $account->name }}</span>@else<span class="sbg-v23-muted">Belum ada penanggung jawab</span>@endif</td>
                <td data-label="Email / Role">@if($account)<span class="sbg-v23-email">{{ $account->email }}</span><span class="sbg-v23-role" data-role="{{ $account->role }}">{{ $roleLabel }}</span>@else<span class="sbg-v23-muted">Belum ada akun pada wilayah ini.</span>@endif</td>
                <td data-label="Tanggal Dibuat">{{ $account?->created_at?->format('d/m/Y') ?? '—' }}</td>
                <td data-label="Status"><span class="sbg-v23-badge {{ $account ? 'is-registered' : '' }}">{{ $account ? 'Terdaftar' : 'Belum ada akun' }}</span></td>
                @if(auth()->user()->isSuperAdmin())<td data-label="Popup Login">@if($account)@include('components.sbg.popup-controls-v23', ['account'=>$account,'accountPopup'=>$loginPopupSettings->get($account->id)])@else<span class="sbg-v23-muted">Perlu akun</span>@endif</td>@endif
                <td data-label="Aksi"><div class="sbg-v23-row-actions">
                    @if($account && $reg)
                        @if(auth()->user()->isKepalaDapur() && $account->is(auth()->user()))
                            <button type="button" class="sbg-button sbg-button-outline" @click="isResetOwnPasswordModalOpen = true">Lupa Password</button>
                        @elseif(auth()->user()->isSuperAdmin() || $account->role === 'admin')
                            <button type="button" class="sbg-v23-action is-edit" aria-label="Edit {{ $account->name }}" @click='openEditAdminModal(@json($reg->only(["id","name","code"])), @json($safeAccount))'><x-sbg.icon name="edit" :size="16"/></button>
                            @if(!$account->is(auth()->user()))<form method="POST" action="{{ route('admin.account.destroy',$account) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini?')">@csrf @method('DELETE')<button type="submit" class="sbg-v23-action is-delete" aria-label="Hapus {{ $account->name }}"><x-sbg.icon name="trash" :size="16"/></button></form>@endif
                        @else<span class="sbg-v23-muted">Tidak ada aksi</span>@endif
                    @elseif(!$account)
                        <button type="button" class="sbg-v23-action is-edit" @click="isAddAdminModalOpen = true" aria-label="Tambah akun di {{ $name }}"><x-sbg.icon name="plus" :size="16"/></button>
                        @if(auth()->user()->isSuperAdmin())<form method="POST" action="{{ route('admin.region.destroy',$reg) }}" onsubmit="return confirm('Hapus daerah ini beserta akun wilayahnya?')">@csrf @method('DELETE')<button class="sbg-v23-action is-delete" aria-label="Hapus daerah {{ $name }}"><x-sbg.icon name="trash" :size="16"/></button></form>@endif
                    @else<span class="sbg-v23-muted">—</span>@endif
                </div></td>
            </tr>
        @endforeach
        </tbody></table></div>
        <div class="sbg-v23-empty" x-cloak x-show="masterResultCount === 0"><x-sbg.icon name="search" :size="28"/><h2>Tidak ada hasil yang sesuai</h2><p>Coba kata kunci lain atau reset filter.</p><button type="button" class="sbg-button sbg-button-outline" @click="masterSearch = ''; masterRole = ''; masterStatus = ''">Reset filter</button></div>
        <p class="sbg-v23-result" aria-live="polite"><span x-text="masterResultCount"></span> baris ditampilkan · Data akun sesuai wilayah akses Anda</p>
    </div>
</section>
