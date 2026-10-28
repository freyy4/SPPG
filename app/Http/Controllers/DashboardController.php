<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\LoginPopupSetting;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function payAllPartnerFees(Request $request)
    {
        return $this->payAll($request, 'is_partner_fee_paid', 'fee mitra');
    }

    public function payAllSupplierPayments(Request $request)
    {
        return $this->payAll($request, 'is_supplier_paid', 'pembayaran supplier');
    }

    public function payAllKsppgFees(Request $request)
    {
        return $this->payAll($request, 'is_ksppg_fee_paid', 'fee KSPPG');
    }

    private function payAll(Request $request, string $column, string $label)
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Hanya superadmin yang dapat membayar '.$label.'.');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:purchase_orders,id'],
        ]);

        PurchaseOrder::whereIn('id', $validated['ids'])
            ->when($column === 'is_ksppg_fee_paid', fn ($query) => $query->where('supplier_price', '>', 0))
            ->where($column, false)
            ->update([$column => true]);

        return redirect()->back()->with('success', ucfirst($label).' yang dipilih berhasil dilunasi.');
    }

    /**
     * Tampilkan Halaman Utama Dashboard (List PO & Ringkasan Ringkas)
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // 1. Jika dikirim via POST (Filter di-submit), simpan nilai input ke Session
        if ($request->isMethod('post')) {
            $request->validate([
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            ], [
                'date_from.date_format' => 'Tanggal mulai tidak valid.',
                'date_to.date_format' => 'Tanggal akhir tidak valid.',
                'date_to.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
            ]);

            session([
                'filter_region'    => $request->input('region'),
                'filter_status'    => $request->input('status'),
                'filter_priority'  => $request->input('priority'),
                'filter_category'  => $request->input('category'),
                'filter_search'    => $request->input('search'),
                'filter_date_from' => $request->input('date_from'),
                'filter_date_to'   => $request->input('date_to'),
            ]);
        }

        // 2. Ambil parameter filter dari Session
        $sessionRegion   = session('filter_region');
        $sessionStatus   = session('filter_status');
        $sessionPriority = session('filter_priority');
        $sessionCategory = session('filter_category');
        $sessionSearch   = session('filter_search');
        $sessionDateFrom = session('filter_date_from');
        $sessionDateTo   = session('filter_date_to');

        // 3. Base Query
        $query = PurchaseOrder::with('creator');
        $regions = Region::query()
            ->when(!$user->isSuperAdmin(), fn ($regionQuery) => $regionQuery->where('name', $user->region))
            ->orderBy('name', 'asc')
            ->get();
        $admins = User::query()
            ->whereIn('role', ['admin', 'kepala_dapur'])
            ->when(!$user->isSuperAdmin(), fn ($adminQuery) => $adminQuery->where('region', $user->region))
            ->orderBy('name')
            ->get(['id', 'name', 'region']);

        // 4. Pembatasan Akses & Filter Wilayah
        if (!$user->isSuperAdmin()) {
            // Admin biasa terkunci ke wilayahnya sendiri
            $query->where('region', $user->region);
        } else {
            // Super Admin dapat menggunakan filter wilayah dari session
            if (!empty($sessionRegion)) {
                $query->where('region', $sessionRegion);
            }
        }

        // 5. Apply Filter Lainnya dari Session
        if (!empty($sessionStatus)) {
            $query->where('status_stage', $sessionStatus);
        }

        if (!empty($sessionPriority)) {
            $query->where('priority', $sessionPriority);
        }

        if (!empty($sessionCategory)) {
            $query->where('category', $sessionCategory);
        }

        if (!empty($sessionSearch)) {
            $query->where(function ($q) use ($sessionSearch) {
                $q->where('po_number', 'like', "%{$sessionSearch}%")
                ->orWhere('item_name', 'like', "%{$sessionSearch}%")
                ->orWhere('supplier_name', 'like', "%{$sessionSearch}%");
            });
        }

        if (!empty($sessionDateFrom)) {
            $query->whereDate('transaction_date', '>=', $sessionDateFrom);
        }

        if (!empty($sessionDateTo)) {
            $query->whereDate('transaction_date', '<=', $sessionDateTo);
        }

        // 6. Data Purchase Orders dengan Pagination (Hapus withQueryString agar URL tetap bersih)
        $purchaseOrders = (clone $query)->latest('transaction_date')->paginate(15);

        // 7. Data Ringkasan Financial (Untuk Widget Dashboard)
        $allFetchedPO = (clone $query)->get();
        $summary = [
            'total_po'             => $allFetchedPO->count(),
            'po_pending'           => $allFetchedPO->where('status_stage', 'Pending')->count(),
            'po_selesai'           => $allFetchedPO->where('status_stage', 'Selesai')->count(),
            'total_nilai_po'       => $allFetchedPO->sum(function($po) {
                                        return $po->quantity * $po->maker_price; // kalkulasi total nilai PO
                                    }),
            // Ringkasan keuangan menampilkan sisa yang belum dibayarkan.
            'total_bayar_supplier' => $allFetchedPO->where('is_supplier_paid', false)->sum('total_supplier_amount'),
            'total_laba_mitra'     => $allFetchedPO->where('is_partner_fee_paid', false)->sum('total_partner_fee'),
            'total_laba_ksppg'     => $allFetchedPO->where('is_ksppg_fee_paid', false)->sum('total_ksppg_fee'),
        ];

        $popupPerUserReady = Schema::hasColumn('login_popup_settings', 'user_id');
        $loginPopup = $popupPerUserReady
            ? LoginPopupSetting::where('user_id', $user->id)->first()
            : null;
        $loginPopupSettings = $popupPerUserReady && $user->isSuperAdmin()
            ? LoginPopupSetting::whereNotNull('user_id')->get()->keyBy('user_id')
            : collect();
        $superAdmins = $user->isSuperAdmin()
            ? User::where('role', 'super_admin')->orderBy('name')->get()
            : collect();
        $showLoginPopup = $request->session()->pull('show_login_popup', false) && $loginPopup?->enabled;
        $popupFeedback = $request->session()->pull('popup_feedback');

        return view('dashboard', compact('purchaseOrders', 'summary', 'regions', 'admins', 'loginPopup', 'loginPopupSettings', 'popupPerUserReady', 'superAdmins', 'showLoginPopup', 'popupFeedback'));
    }

    // Method tambahan untuk mereset filter
    public function resetFilter()
    {
        session()->forget([
            'filter_region',
            'filter_status',
            'filter_priority',
            'filter_category',
            'filter_search',
            'filter_date_from',
            'filter_date_to',
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Simpan Data Purchase Order Baru dari Dashboard
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->isRegularAdmin()) {
            $request->merge(['created_by' => $user->id]);
        }

        $this->normalizeCurrencyInputs($request);
        $this->normalizeWarehouseQuantities($request);

        $validated = $request->validate([
            'item_name'        => 'required|string|max:255',
            'category'         => 'required|string|in:Karbo,Protein Nabati,Protein Hewani,Sayur,Buah',
            'quantity'         => 'required|numeric|min:0.01|max:99999999.99',
            'quantity_sbg'     => 'sometimes|required|numeric|min:0|max:99999999.99',
            'quantity_b'       => 'sometimes|required|numeric|min:0|max:99999999.99',
            'unit'             => 'required|string|max:50',
            'transaction_date' => 'required|date',
            'created_by'       => 'required|integer|exists:users,id',
            'priority'         => 'required|string|in:Urgent,High,Normal,Low,Tinggi,Rendah',
            'status_stage'     => 'required|string|in:Cari Supplier,Sortir,Pengiriman,Selesai,Retur,Kekurangan,Perubahan Item',
            'supplier_name'    => 'nullable|string|max:255',
            'supplier_price'   => 'nullable|numeric|min:0',
            'supplier_price_b' => 'nullable|numeric|min:0',
            'maker_price'      => 'nullable|numeric|min:0',
            'partner_margin'   => 'nullable|numeric|min:0',
            'region'           => 'nullable|exists:regions,name',
            'notes'            => 'nullable|string',
        ]);

        $managedAdmin = $this->resolveManagedAdmin($validated['created_by'], $user);

        $validated['po_number'] = 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        $validated['region'] = $managedAdmin->region;

        // Harga supplier hanya boleh ditentukan oleh super admin. Jangan percaya
        // atribut readonly di browser karena request masih dapat dimanipulasi.
        if ($user->isRegularAdmin()) {
            unset($validated['supplier_price'], $validated['supplier_price_b']);
            unset($validated['supplier_name']);
        }

        PurchaseOrder::create($validated);

        return redirect()->back()->with('success', 'Purchase Order berhasil dibuat.');
    }

    /**
     * Tampilkan Detail PO (Untuk Modal Detail via AJAX)
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAccess($purchaseOrder);

        return response()->json([
            'success' => true,
            'data'    => array_merge($purchaseOrder->toArray(), [
                'total_supplier_amount' => $purchaseOrder->total_supplier_amount,
                'total_partner_fee'     => $purchaseOrder->total_partner_fee,
                'ksppg_fee_per_unit'    => $purchaseOrder->ksppg_fee_per_unit,
                'total_ksppg_fee'       => $purchaseOrder->total_ksppg_fee,
                'formatted_date'        => $purchaseOrder->transaction_date ? $purchaseOrder->transaction_date->translatedFormat('l, d F Y') : '-',
            ]),
        ]);
    }

    /**
     * Halaman publik yang hanya dapat dibuka melalui signed URL pada QR nota.
     */
    public function verifyPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        return view('purchase_orders.verify', compact('purchaseOrder'));
    }

    public function createPartnerFeeVerificationLink(Request $request)
    {
        $request->merge(['type' => 'partner_fee']);

        return $this->createPaymentVerificationLink($request);
    }

    public function createPaymentVerificationLink(Request $request)
    {
        $validated = $request->validate([
            'items'   => 'required|array|min:1',
            'items.*' => 'required|integer|distinct|exists:purchase_orders,id',
            'type' => 'required|in:partner_fee,supplier,ksppg_fee',
        ]);

        abort_if($validated['type'] !== 'partner_fee' && !Auth::user()->canManageFinancials(), 403);

        $query = PurchaseOrder::query()->whereIn('id', $validated['items']);

        if (!Auth::user()->isSuperAdmin()) {
            $query->where('region', Auth::user()->region);
        }

        $ids = $query->pluck('id')->sort()->values();

        if ($ids->count() !== count($validated['items'])) {
            abort(403, 'Terdapat Purchase Order yang tidak dapat diakses.');
        }

        return response()->json([
            'url' => URL::signedRoute('payment.verify', [
                'type' => $validated['type'],
                'items' => $ids->implode(','),
            ], null, false),
        ]);
    }

    public function verifyPayment(Request $request, string $type)
    {
        abort_unless(in_array($type, ['partner_fee', 'supplier', 'ksppg_fee'], true), 404);

        $ids = collect(explode(',', (string) $request->query('items')))
            ->filter(fn ($id) => ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 404);

        $purchaseOrders = PurchaseOrder::query()
            ->whereIn('id', $ids)
            ->orderBy('transaction_date')
            ->get();

        abort_if($purchaseOrders->count() !== $ids->count(), 404);

        return view('purchase_orders.verify-partner-fee', compact('purchaseOrders', 'type'));
    }

    public function verifyPartnerFee(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('items')))
            ->filter(fn ($id) => ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 404);

        $purchaseOrders = PurchaseOrder::query()
            ->whereIn('id', $ids)
            ->orderBy('transaction_date')
            ->get();

        abort_if($purchaseOrders->isEmpty(), 404);

        return view('purchase_orders.verify-partner-fee', compact('purchaseOrders'));
    }

    /**
     * Update Data Purchase Order
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAccess($purchaseOrder);

        $user = Auth::user();

        if ($user->isRegularAdmin()) {
            $request->merge(['created_by' => $user->id]);
        }

        $this->normalizeCurrencyInputs($request);
        $this->normalizeWarehouseQuantities($request);

        $validated = $request->validate([
            'item_name'        => 'required|string|max:255',
            'category'         => 'required|string|in:Karbo,Protein Nabati,Protein Hewani,Sayur,Buah',
            'quantity'         => 'required|numeric|min:0.01|max:99999999.99',
            'quantity_sbg'     => 'sometimes|required|numeric|min:0|max:99999999.99',
            'quantity_b'       => 'sometimes|required|numeric|min:0|max:99999999.99',
            'unit'             => 'required|string|max:50',
            'transaction_date' => 'required|date',
            'created_by'       => 'required|integer|exists:users,id',
            'priority'         => 'required|string|in:Urgent,High,Normal,Low,Tinggi,Rendah',
            'status_stage'     => 'required|string|in:Cari Supplier,Sortir,Pengiriman,Selesai,Retur,Kekurangan,Perubahan Item',
            'supplier_name'    => 'nullable|string|max:255',
            'supplier_price'   => 'nullable|numeric|min:0',
            'supplier_price_b' => 'nullable|numeric|min:0',
            'maker_price'      => 'nullable|numeric|min:0',
            'partner_margin'   => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);

        $managedAdmin = $this->resolveManagedAdmin($validated['created_by'], $user);
        $validated['region'] = $managedAdmin->region;

        // Pertahankan harga lama ketika perubahan dilakukan oleh admin biasa,
        // termasuk jika request dikirim langsung di luar form dashboard.
        if ($user->isRegularAdmin()) {
            unset($validated['supplier_price'], $validated['supplier_price_b']);
            unset($validated['supplier_name']);
        }

        $purchaseOrder->update($validated);

        return redirect()->back()->with('success', 'Purchase Order berhasil diperbarui.');
    }

    /**
     * Toggle Status Pembayaran (Tombol di Modal UI)
     */
    public function togglePaymentStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAccess($purchaseOrder);

        abort_unless(Auth::user()->canManageFinancials(), 403, 'Anda tidak memiliki akses untuk mengelola pembayaran.');

        $request->validate([
            'payment_type' => 'required|in:partner_fee,supplier,ksppg_fee',
        ]);

        $type = $request->payment_type;

        if ($type === 'partner_fee') {
            $purchaseOrder->is_partner_fee_paid = !$purchaseOrder->is_partner_fee_paid;
        } elseif ($type === 'supplier') {
            $purchaseOrder->is_supplier_paid = !$purchaseOrder->is_supplier_paid;
        } elseif ($type === 'ksppg_fee') {
            $purchaseOrder->is_ksppg_fee_paid = !$purchaseOrder->is_ksppg_fee_paid;
        }

        $purchaseOrder->save();

        return redirect()->back()->with('success', 'Status pembayaran berhasil diperbarui.');
    }

    public function storeAdminAndRegion(Request $request)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && !$user->isKepalaDapur()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah akun.');
        }

        // Kepala Dapur hanya boleh membuat Admin pada wilayahnya sendiri.
        if ($user->isKepalaDapur()) {
            $request->merge([
                'create_new_region' => 0,
                'region'            => $user->region,
                'account_role'      => 'admin',
            ]);
        }

        $validated = $request->validate([
            'create_new_region' => 'nullable|boolean',
            'region'            => 'required_unless:create_new_region,1|nullable|exists:regions,name',
            'region_name'       => 'required_if:create_new_region,1|nullable|string|max:255|unique:regions,name',
            'region_code'       => 'nullable|string|max:50|unique:regions,code',
            'admin_name'        => 'required|string|max:255',
            'account_role'      => 'required|in:admin,kepala_dapur',
            'email'             => 'required|email|unique:users,email',
            'password'          => 'required|string|min:6',
        ]);

        // DB Transaction agar jika salah satu gagal, keduanya dibatalkan (atomic)
        DB::transaction(function () use ($validated) {
            if (!empty($validated['create_new_region'])) {
                $region = Region::create([
                    'name' => $validated['region_name'],
                    'code' => $validated['region_code'] ?? strtoupper(substr($validated['region_name'], 0, 4)),
                ]);
            } else {
                $region = Region::where('name', $validated['region'])->firstOrFail();
            }

            // Buat akun baru dan hubungkan ke daerah yang dipilih/dibuat.
            User::create([
                'name'     => $validated['admin_name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role'     => $validated['account_role'],
                'region'   => $region->name,
            ]);
        });

        return redirect()->back()->with('success', 'Daerah dan akun penanggung jawab berhasil ditambahkan!');
    }

    public function updateRegion(Request $request, $id)
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403, 'Anda tidak memiliki akses untuk mengubah daerah atau akun penanggung jawab.');

        $request->validate([
            'account_role' => 'required|in:admin,kepala_dapur',
            'account_id'   => 'required|integer|exists:users,id',
        ]);

        $region = Region::findOrFail($id);
        $oldName = $region->name;
        $admin = User::whereKey($request->account_id)
            ->where('region', $oldName)
            ->whereIn('role', ['admin', 'kepala_dapur'])->firstOrFail();
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:regions,code,'.$region->id,
            'name' => 'required|string|max:255|unique:regions,name,'.$region->id,
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email,'.$admin->id,
            'password' => 'nullable|string|min:6',
        ]);
        DB::transaction(function () use ($request, $region, $admin, $validated) {
            $region->update(['code' => $validated['code'], 'name' => $validated['name']]);
            $userData = [
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'role' => $request->account_role,
                'region' => $validated['name'],
            ];
            if ($request->filled('password')) $userData['password'] = Hash::make($validated['password']);
            $admin->update($userData);
        });
        if ($request->expectsJson()) return response()->json(['success' => true]);
        return back()->with('success', 'Data region dan admin berhasil diperbarui');
    }

    public function updateManagedAccount(Request $request, User $account)
    {
        $user = Auth::user();

        abort_unless($user->isSuperAdmin() || $user->isKepalaDapur(), 403);

        if ($user->isKepalaDapur()) {
            abort_unless(
                $account->role === 'admin' && $account->region === $user->region,
                403,
                'Anda hanya dapat mengubah akun Admin di wilayah sendiri.'
            );
        }

        $validated = $request->validate([
            'admin_name'  => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email,' . $account->id,
            'password'    => 'nullable|string|min:6',
        ]);

        $accountData = [
            'name'  => $validated['admin_name'],
            'email' => $validated['admin_email'],
        ];

        if (!empty($validated['password'])) {
            $accountData['password'] = Hash::make($validated['password']);
        }

        $account->update($accountData);

        if ($request->expectsJson()) return response()->json(['success' => true]);
        return back()->with('success', 'Akun Admin berhasil diperbarui.');
    }

    public function destroyManagedAccount(User $account)
    {
        $user = Auth::user();

        abort_unless($user->isSuperAdmin() || $user->isKepalaDapur(), 403);
        abort_if($account->is($user), 403, 'Anda tidak dapat menghapus akun sendiri.');

        if ($user->isKepalaDapur()) {
            abort_unless(
                $account->role === 'admin' && $account->region === $user->region,
                403,
                'Anda hanya dapat menghapus akun Admin di wilayah sendiri.'
            );
        }

        $account->delete();

        return back()->with('success', 'Akun Admin berhasil dihapus.');
    }

    public function updateOwnPassword(Request $request)
    {
        $user = Auth::user();

        abort_unless($user->isKepalaDapur(), 403, 'Fitur ini hanya tersedia untuk Kepala Dapur.');

        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password Kepala Dapur berhasil diperbarui.');
    }

    // Delete Region
    public function destroyRegion($id)
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403, 'Anda tidak memiliki akses untuk menghapus daerah atau akun penanggung jawab.');

        $region = Region::findOrFail($id);
        User::where('region', $region->name)->delete();
        $region->delete();

        return back()->with('success', 'Data region dan admin berhasil dihapus');
    }

    /**
     * Hapus Data Purchase Order (Soft Delete)
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAccess($purchaseOrder);

        $purchaseOrder->delete();

        return redirect()->back()->with('success', 'Purchase Order berhasil dihapus.');
    }

    /**
     * Fitur Cetak / Buat Nota Dapur
     */
    public function printKitchenReceipt(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAccess($purchaseOrder);

        return view('purchase_orders.receipt', compact('purchaseOrder'));
    }

    /**
     * Helper Privasi: Menjaga akses Admin Wilayah
     */
    private function authorizeAccess(PurchaseOrder $purchaseOrder)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && $purchaseOrder->region !== $user->region) {
            abort(403, 'Anda tidak memiliki akses ke data Purchase Order wilayah ini.');
        }
    }

    private function resolveManagedAdmin(int $adminId, User $user): User
    {
        $adminQuery = User::query()
            ->whereKey($adminId)
            ->whereIn('role', ['admin', 'kepala_dapur']);

        if (!$user->isSuperAdmin()) {
            $adminQuery->where('region', $user->region);
        }

        $admin = $adminQuery->first();

        if (!$admin) {
            throw ValidationException::withMessages([
                'created_by' => 'Penanggung jawab yang dipilih tidak valid untuk wilayah ini.',
            ]);
        }

        return $admin;
    }

    private function normalizeWarehouseQuantities(Request $request): void
    {
        if (!$request->hasAny(['quantity_sbg', 'quantity_b'])) {
            return;
        }

        $quantities = $request->validate([
            'quantity_sbg' => 'required|numeric|min:0|max:99999999.99|decimal:0,2',
            'quantity_b' => 'required|numeric|min:0|max:99999999.99|decimal:0,2',
        ]);

        $request->merge(['quantity' => round($quantities['quantity_sbg'] + $quantities['quantity_b'], 2)]);
    }

    private function normalizeCurrencyInputs(Request $request): void
    {
        $normalized = [];

        foreach (['supplier_price', 'supplier_price_b', 'maker_price', 'partner_margin'] as $field) {
            $value = $request->input($field);

            if (is_string($value) && $value !== '') {
                $normalized[$field] = str_replace(',', '.', str_replace('.', '', $value));
            }
        }

        $request->merge($normalized);
    }

    public function getPoAnalysis(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $filters = $request->validate([
            'year' => 'sometimes|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'category' => 'nullable|string|in:Karbo,Protein Nabati,Protein Hewani,Sayur,Buah',
            'source' => 'sometimes|in:supplier_sbg,supplier_b,maker',
            'region' => 'nullable|string|max:255',
            'item' => 'nullable|string|max:255',
        ]);
        $year = (int) ($filters['year'] ?? today()->year);
        $source = $filters['source'] ?? 'supplier_sbg';
        $query = PurchaseOrder::query()
            ->whereBetween('transaction_date', [$year.'-01-01', $year.'-12-31'])
            ->whereDate('transaction_date', '<=', today());
        foreach (['category', 'region'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $items = (clone $query)->distinct()->orderBy('item_name')->pluck('item_name');
        if (!empty($filters['item'])) {
            $query->where('item_name', $filters['item']);
        }
        $groups = [];
        $excluded = 0;
        foreach ($query->select(['item_name', 'category', 'unit', 'transaction_date', 'quantity', 'quantity_sbg', 'quantity_b', 'supplier_price', 'supplier_price_b', 'maker_price'])->cursor() as $po) {
            $quantity = match ($source) {
                'supplier_sbg' => $po->quantity_sbg ?? $po->quantity,
                'supplier_b' => $po->quantity_b ?? 0,
                default => $po->quantity,
            };
            $price = match ($source) {
                'supplier_sbg' => $po->supplier_price,
                'supplier_b' => $po->supplier_price_b ?? $po->supplier_price,
                default => $po->maker_price,
            };
            if ($quantity <= 0 || $price <= 0) {
                $excluded++;
                continue;
            }
            $key = json_encode([$po->item_name, $po->category, $po->unit]);
            if (!isset($groups[$key])) {
                $groups[$key] = ['item' => $po->item_name, 'category' => $po->category, 'unit' => $po->unit, 'buckets' => []];
            }
            $month = (int) $po->transaction_date->month;
            $bucket = $groups[$key]['buckets'][$month] ?? ['value' => 0, 'quantity' => 0, 'count' => 0, 'min' => $price, 'max' => $price];
            $bucket['value'] += $price * $quantity;
            $bucket['quantity'] += $quantity;
            $bucket['count']++;
            $bucket['min'] = min($bucket['min'], $price);
            $bucket['max'] = max($bucket['max'], $price);
            $groups[$key]['buckets'][$month] = $bucket;
        }
        $rows = [];
        foreach ($groups as $group) {
            $months = [];
            for ($month = 1; $month <= 12; $month++) {
                $bucket = $group['buckets'][$month] ?? null;
                $average = $bucket ? $bucket['value'] / $bucket['quantity'] : null;
                $previous = $months[$month - 2]['average'] ?? null;
                $months[] = [
                    'month' => $month, 'average' => $average === null ? null : round($average, 2),
                    'change' => $average !== null && $previous !== null && $previous > 0 ? round(($average - $previous) / $previous * 100, 2) : null,
                    'quantity' => $bucket['quantity'] ?? 0, 'count' => $bucket['count'] ?? 0,
                    'min' => $bucket['min'] ?? null, 'max' => $bucket['max'] ?? null,
                ];
            }
            $available = array_values(array_filter($months, fn ($month) => $month['average'] !== null));
            $lowest = min(array_column($available, 'average'));
            $highest = max(array_column($available, 'average'));
            $selectedMonth = (int) ($filters['month'] ?? 0);
            if ($selectedMonth && $months[$selectedMonth - 1]['average'] === null) {
                continue;
            }
            $rows[] = [
                'item' => $group['item'], 'category' => $group['category'], 'unit' => $group['unit'],
                'months' => $months, 'observed_months' => count($available),
                'lowest' => $lowest, 'highest' => $highest,
                'lowest_months' => array_column(array_filter($available, fn ($month) => $month['average'] === $lowest), 'month'),
                'count' => array_sum(array_column($available, 'count')),
            ];
        }
        usort($rows, fn ($a, $b) => [$a['item'], $a['unit']] <=> [$b['item'], $b['unit']]);
        return response()->json([
            'rows' => $rows, 'items' => $items, 'year' => $year, 'source' => $source,
            'excluded' => $excluded,
            'regions' => PurchaseOrder::query()->distinct()->orderBy('region')->pluck('region'),
        ]);
    }

    public function getAnalisaKeuntungan(Request $request)
    {
        $user = Auth::user();
        $filter = $request->validate([
            'filter' => ['sometimes', 'in:week,month,3months,all'],
        ])['filter'] ?? 'week';

        // Rentang tanggal memakai tanggal PO, termasuk hari ini dan tanpa PO masa depan.
        $startDate = match ($filter) {
            'week'     => today()->startOfWeek(),
            'month'    => today()->startOfMonth(),
            '3months'  => today()->subMonths(3)->startOfDay(),
            'all'      => null,
        };

        $query = PurchaseOrder::query();

        // Otorisasi Akses Wilayah (Sama seperti logika index)
        if (!$user->isSuperAdmin()) {
            $query->where('region', $user->region);
        } else {
            $sessionRegion = session('filter_region');
            if (!empty($sessionRegion)) {
                $query->where('region', $sessionRegion);
            }
        }

        // Filter Rentang Tanggal
        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate->toDateString())
                ->whereDate('transaction_date', '<=', today()->toDateString());
        }

        // Gunakan sumber fee dan status belum lunas yang sama dengan kartu dashboard.
        $isAdmin = $user->isRegularAdmin();
        $query->where($isAdmin ? 'is_partner_fee_paid' : 'is_ksppg_fee_paid', false);
        $totalFee = $isAdmin
            ? 'SUM(COALESCE(quantity_sbg, quantity) * COALESCE(partner_margin, 0) + COALESCE(quantity_b, 0) * COALESCE(maker_price, 0))'
            : 'SUM(CASE WHEN COALESCE(supplier_price, 0) = 0 THEN 0 ELSE COALESCE(quantity_sbg, quantity) * (COALESCE(maker_price, 0) - supplier_price - COALESCE(partner_margin, 0)) END)';

        $topItems = (clone $query)
            ->select('item_name', DB::raw($totalFee.' as total_fee'))
            ->groupBy('item_name')
            ->orderByDesc('total_fee')
            ->orderBy('item_name')
            ->limit(3)
            ->get();

        $topCategories = (clone $query)
            ->select('category', DB::raw($totalFee.' as total_fee'))
            ->groupBy('category')
            ->orderByDesc('total_fee')
            ->orderBy('category')
            ->limit(3)
            ->get();

        $bestItem = $topItems->first();

        $worstItem = (clone $query)
            ->select('item_name', DB::raw($totalFee.' as total_fee'))
            ->groupBy('item_name')
            ->orderByRaw($totalFee.' ASC')
            ->orderBy('item_name')
            ->first();

        // Presentation-only grand total: reuse the exact existing fee expression,
        // scoped query and payment filters, without the ranking's three-item limit.
        $totalProfit = (float) ((clone $query)->selectRaw($totalFee.' as total_fee')->value('total_fee') ?? 0);

        // 6. Return Format JSON untuk UI Modal
        return response()->json([
            'totalProfit' => $totalProfit,
            'topItems' => $topItems->map(fn ($item) => [
                'name' => $item->item_name,
                'total' => (float) $item->total_fee,
            ])->values(),
            'topCategories' => $topCategories->map(fn ($category) => [
                'name' => $category->category ?: 'Tanpa Kategori',
                'total' => (float) $category->total_fee,
            ])->values(),
            'topItem' => [
                'name'  => $bestItem->item_name ?? '-',
                'total' => (float) ($bestItem->total_fee ?? 0),
            ],
            'topCategory' => [
                'name'  => $topCategories->first()?->category ?: '-',
                'total' => (float) ($topCategories->first()?->total_fee ?? 0),
            ],
            'bestItem' => [
                'name'  => $bestItem->item_name ?? '-',
                'total' => (float) ($bestItem->total_fee ?? 0),
            ],
            'worstItem' => [
                'name'  => $worstItem->item_name ?? '-',
                'total' => (float) ($worstItem->total_fee ?? 0),
            ],
        ]);
    }
}
