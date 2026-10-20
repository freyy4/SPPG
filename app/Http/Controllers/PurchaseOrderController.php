<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = PurchaseOrder::query();

        // -----------------------------------------------------------------
        // 1. QUERY FILTER UNTUK TABEL PO
        // -----------------------------------------------------------------
        // Search Keyword
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('supplier_name', 'like', "%{$search}%");
            });
        }

        // Filter Tanggal (Menggunakan created_at)
        if ($request->filled('tanggal') && $request->tanggal !== 'Semua') {
            switch ($request->tanggal) {
                case 'Hari Ini':
                    $query->whereDate('created_at', now()->today());
                    break;
                case 'Minggu Ini':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'Bulan Ini':
                    $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                    break;
                case 'Tahun Ini':
                    $query->whereYear('created_at', now()->year);
                    break;
            }
        }

        // Filter Status
        if ($request->filled('status') && $request->status !== 'Semua') {
            $query->where('status_stage', $request->status);
        }

        // Filter Prioritas
        if ($request->filled('prioritas') && $request->prioritas !== 'Semua') {
            $query->where('priority', $request->prioritas);
        }

        $purchaseOrders = $query->latest('created_at')->get();

        // -----------------------------------------------------------------
        // 2. RINGKASAN METRIK GENERAL & FINANCIAL FEE
        // -----------------------------------------------------------------
        $totalNilaiPO   = $purchaseOrders->sum(fn($po) => $po->quantity * ($po->maker_price ?? 0));
        $poPendingCount = $purchaseOrders->where('status_stage', '!=', 'Selesai')->count();
        $poSelesaiCount = $purchaseOrders->where('status_stage', 'Selesai')->count();

        // Fee Mitra, Supplier, dan KSPPG
        $totalFeeMitra      = $purchaseOrders->sum('total_partner_fee');
        $totalBayarSupplier = $purchaseOrders->sum('total_supplier_amount');
        $totalFeeKSPPG      = $purchaseOrders->sum('total_ksppg_fee');

        // -----------------------------------------------------------------
        // 3. QUERY ANALISA KEUNTUNGAN (POP-UP GAMBAR 2)
        // -----------------------------------------------------------------
        $timeRangeAnalisa = $request->get('range_analisa', 'Minggu Ini');
        $analisaQuery = PurchaseOrder::query();

        // Filter Rentang Waktu Analisa (Menggunakan created_at)
        if ($timeRangeAnalisa === 'Minggu Ini') {
            $analisaQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($timeRangeAnalisa === 'Bulan Ini') {
            $analisaQuery->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        } elseif ($timeRangeAnalisa === '3 Bulan Terakhir') {
            $analisaQuery->whereBetween('created_at', [now()->subMonths(3), now()]);
        }

        // Top Item (#1)
        $topItem = (clone $analisaQuery)
            ->select('item_name', DB::raw('SUM(quantity * COALESCE(maker_price, 0)) as total_ksppg'))
            ->groupBy('item_name')
            ->orderByDesc('total_ksppg')
            ->first();

        // Top Kategori (#1)
        $topCategory = (clone $analisaQuery)
            ->select('category', DB::raw('SUM(quantity * COALESCE(maker_price, 0)) as total_ksppg'))
            ->groupBy('category')
            ->orderByDesc('total_ksppg')
            ->first();

        // Top Supplier (#1)
        $topSupplier = (clone $analisaQuery)
            ->select('supplier_name', DB::raw('SUM(quantity * COALESCE(maker_price, 0)) as total_ksppg'))
            ->groupBy('supplier_name')
            ->orderByDesc('total_ksppg')
            ->first();

        // Item Terbaik & Terendah
        $itemTerbaik = $topItem;
        $itemTerendah = (clone $analisaQuery)
            ->select('item_name', DB::raw('SUM(quantity * COALESCE(maker_price, 0)) as total_ksppg'))
            ->groupBy('item_name')
            ->orderBy('total_ksppg', 'asc')
            ->first();

        return view('po.index', compact(
            'purchaseOrders', 
            'totalNilaiPO', 
            'poPendingCount', 
            'poSelesaiCount',
            'totalFeeMitra', 
            'totalBayarSupplier', 
            'totalFeeKSPPG',
            'topItem',
            'topCategory',
            'topSupplier',
            'itemTerbaik',
            'itemTerendah',
            'timeRangeAnalisa'
        ));
    }

    // 2. Menampilkan form tambah PO
    public function create()
    {
        return view('po.create');
    }

    // 3. Menyimpan data PO baru
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'quantity'  => 'required|numeric|min:0.1',
            'unit'      => 'required|string|max:50',
        ]);

        $poData = [
            'po_number'  => 'PO-' . time(),
            'item_name'  => $validated['item_name'],
            'quantity'   => $validated['quantity'],
            'unit'       => $validated['unit'],
            'region'     => $user->isSuperAdmin() ? $request->input('region', 'Pusat') : $user->region,
            'created_by' => $user->id,
        ];

        if ($user->isSuperAdmin()) {
            $poData['supplier_fee'] = $request->input('supplier_fee', 0);
            $poData['ksppg_fee']    = $request->input('ksppg_fee', 0);
        }

        PurchaseOrder::create($poData);

        return redirect()->route('po.index')->with('success', 'PO Berhasil Ditambahkan');
    }

    // 4. Form Edit (Khusus Super Admin)
    public function edit(PurchaseOrder $po)
    {
        if (Auth::user()->isRegularAdmin()) {
            abort(403, 'Akses ditolak. Admin/Akuntan tidak memiliki hak untuk mengedit PO.');
        }

        return view('po.edit', compact('po'));
    }

    // 5. Cetak Nota
    public function printNota(PurchaseOrder $po)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin() && $po->region !== $user->region) {
            abort(403, 'Anda tidak memiliki akses ke nota wilayah ini.');
        }

        return view('po.nota', compact('po'));
    }
}
