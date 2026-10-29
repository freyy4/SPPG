<?php

namespace App\Http\Controllers;

use App\Services\FruitCalculator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FruitCalculatorController extends Controller
{
    private const ALL_REGIONS = 'Semua Wilayah';

    private function region(Request $request): string
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->isAdmin(), 403);
        $own = $request->user()->region ?: 'Pusat';
        if (! $request->user()->isSuperAdmin()) {
            abort_if($request->filled('region') && $request->input('region') !== $own, 403);

            return $own;
        }
        $regions = DB::table('regions')->pluck('name')->push('Pusat')->push($own)->push(self::ALL_REGIONS)->unique()->all();
        $data = $request->validate(['region' => ['nullable', 'string', Rule::in($regions)]]);

        return $data['region'] ?? $own;
    }

    private function catalog(string $region): Builder
    {
        return DB::table('fruit_catalog')->when($region !== self::ALL_REGIONS, fn ($query) => $query->where('region', $region));
    }

    private function candidate(object $row): object
    {
        $row->source_region = $row->region;
        $row->source_po_id = null;
        $row->version = hash('sha256', $row->id.':'.$row->version);
        foreach (['price', 'units_per_kg', 'portion_pb', 'portion_pk'] as $field) {
            $row->$field = (float) $row->$field;
        }

        return $row;
    }

    public function index(Request $request)
    {
        $region = $this->region($request);
        $averages = DB::table('fruit_catalog_prices')->where('created_at', '>=', today()->subDays(7))
            ->where('created_at', '<', today())->selectRaw('fruit_id, AVG(price) as average')->groupBy('fruit_id')->pluck('average', 'fruit_id');
        $fruits = $this->catalog($region)->orderBy('name')->orderBy('id')->get()->map(function ($row) use ($averages) {
            $fruit = $this->candidate($row);
            $fruit->previous_week_average = isset($averages[$fruit->id]) ? (float) $averages[$fruit->id] : null;

            return $fruit;
        });

        return response()->json(['fruits' => $fruits, 'region' => $region]);
    }

    public function store(Request $request)
    {
        $region = $this->region($request);
        if ($region === self::ALL_REGIONS) {
            throw ValidationException::withMessages(['region' => 'Pilih satu wilayah untuk menambahkan buah.']);
        }
        $data = $this->profileData($request);
        $id = DB::transaction(function () use ($data, $region, $request) {
            $id = DB::table('fruit_catalog')->insertGetId($data + ['region' => $region, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('fruit_catalog_prices')->insert(['fruit_id' => $id, 'user_id' => $request->user()->id, 'price' => $data['price'], 'created_at' => now()]);

            return $id;
        });

        return response()->json($this->candidate($this->catalog($region)->find($id)), 201);
    }

    private function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::in(['Butir', 'Sisir', 'Potong', 'Buah'])],
            'units_per_kg' => ['required', 'numeric', 'min:0.0001', 'max:100000', 'decimal:0,4'],
            'portion_pb' => ['required', 'numeric', 'min:0.0001', 'max:1000', 'decimal:0,4'],
            'portion_pk' => ['required', 'numeric', 'min:0.0001', 'max:1000', 'decimal:0,4'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:100000000', 'decimal:0,2'],
        ];
    }

    private function profileData(Request $request): array
    {
        return $request->validate($this->profileRules());
    }

    public function storeMany(Request $request)
    {
        $region = $this->region($request);
        if ($region === self::ALL_REGIONS) {
            throw ValidationException::withMessages(['region' => 'Pilih satu SPPG tujuan.']);
        }
        $rules = ['fruits' => ['required', 'array', 'min:1', 'max:100']];
        foreach ($this->profileRules() as $field => $rule) {
            $rules['fruits.*.'.$field] = $rule;
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($data, $region, $request) {
            foreach ($data['fruits'] as $fruit) {
                $profile = array_intersect_key($fruit, $this->profileRules());
                $id = DB::table('fruit_catalog')->insertGetId($profile + ['region' => $region, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('fruit_catalog_prices')->insert(['fruit_id' => $id, 'user_id' => $request->user()->id, 'price' => $profile['price'], 'created_at' => now()]);
            }
        });

        return response()->json(['count' => count($data['fruits'])], 201);
    }

    public function update(Request $request, int $fruit)
    {
        $region = $this->region($request);
        $data = $this->profileData($request);
        $request->validate(['version' => ['required', 'string', 'size:64']]);
        DB::transaction(function () use ($request, $region, $fruit, $data) {
            $row = $this->catalog($region)->where('id', $fruit)->lockForUpdate()->first();
            abort_unless($row, 404);
            $version = $row->version;
            $oldPrice = (float) $row->price;
            abort_unless($this->candidate($row)->version === $request->input('version'), 409, 'Katalog berubah. Muat ulang sebelum menyimpan.');
            $this->catalog($region)->where('id', $fruit)->update($data + ['version' => $version + 1, 'updated_at' => now()]);
            if ($oldPrice !== (float) $data['price']) {
                DB::table('fruit_catalog_prices')->insert(['fruit_id' => $fruit, 'user_id' => $request->user()->id, 'price' => $data['price'], 'created_at' => now()]);
            }
        });

        return response()->json(['message' => 'Katalog buah tersimpan.']);
    }

    public function history(Request $request, int $fruit)
    {
        $region = $this->region($request);
        $request->validate(['days' => ['required', Rule::in([7, 30, 90])]]);
        abort_unless($this->catalog($region)->find($fruit), 404);

        return response()->json(DB::table('fruit_catalog_prices')->where('fruit_id', $fruit)
            ->where('created_at', '>=', today()->subDays($request->integer('days') - 1))->where('created_at', '<=', now())
            ->orderBy('created_at')->orderBy('id')->get(['price', 'created_at'])->map(function ($point) {
                $point->created_at = Carbon::parse($point->created_at)->toIso8601String();

                return $point;
            }));
    }

    public function decide(Request $request, FruitCalculator $calculator)
    {
        $region = $this->region($request);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'decision_date' => ['required', 'date_format:Y-m-d'],
            'budget' => ['required', 'numeric', 'min:0', 'max:1000000000000', 'decimal:0,2'],
            'pb' => ['required', 'integer', 'min:0', 'max:1000000'],
            'pk' => ['required', 'integer', 'min:0', 'max:1000000'],
            'selected_id' => ['required', 'integer'],
            'candidates' => ['required', 'array', 'min:1', 'max:100'],
            'candidates.*.id' => ['required', 'integer', 'distinct'],
            'candidates.*.version' => ['required', 'string', 'size:64'],
        ]);
        if ($data['pb'] + $data['pk'] === 0) {
            throw ValidationException::withMessages(['pb' => 'Isi minimal satu penerima manfaat.']);
        }
        $decision = DB::transaction(function () use ($request, $data, $region, $calculator) {
            // Serializing per author makes retried submissions idempotent.
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $existing = DB::table('fruit_decisions')->where('user_id', $request->user()->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->region === $region, 409);

                return $existing;
            }
            $fruits = $this->catalog($region)
                ->whereIn('id', array_column($data['candidates'], 'id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $results = [];
            foreach ($data['candidates'] as $candidate) {
                $po = $fruits->get($candidate['id']);
                abort_unless($po, 409, 'Katalog berubah. Muat ulang dan pilih kembali.');
                $fruit = $this->candidate($po);
                abort_unless($fruit->version === $candidate['version'], 409, 'Katalog berubah. Muat ulang dan pilih kembali.');
                if (! $fruit->units_per_kg || ! $fruit->portion_pb || ! $fruit->portion_pk || ! $fruit->price) {
                    throw ValidationException::withMessages(['candidates' => 'Lengkapi isi per kg, porsi, dan harga kandidat.']);
                }
                $results[] = $calculator->calculate($fruit, $data['pb'], $data['pk'], (float) $data['budget']);
            }
            $selected = collect($results)->firstWhere('id', $data['selected_id']);
            if (! $selected || ! $selected['within_budget']) {
                throw ValidationException::withMessages(['selected_id' => 'Pilih kandidat yang masuk anggaran.']);
            }
            $snapshot = [
                'author' => $request->user()->name, 'region' => $region,
                'decision_date' => $data['decision_date'], 'recorded_at' => now()->toIso8601String(),
                'budget' => (float) $data['budget'], 'pb' => $data['pb'], 'pk' => $data['pk'],
                'selected' => $selected, 'candidates' => $results,
            ];
            $id = DB::table('fruit_decisions')->insertGetId([
                'user_id' => $request->user()->id, 'region' => $region, 'request_key' => $data['request_key'],
                'decision_date' => $data['decision_date'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);

            return DB::table('fruit_decisions')->find($id);
        });
        $decision->snapshot = json_decode($decision->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return response()->json($decision, 201);
    }

    public function decisions(Request $request)
    {
        $region = $this->region($request);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:100']]);
        $search = trim($request->input('search', ''));
        $page = DB::table('fruit_decisions')
            ->when($region !== self::ALL_REGIONS, fn ($query) => $query->where('region', $region))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('decision_date', 'like', '%'.$search.'%')
                    ->orWhere('snapshot->selected->name', 'like', '%'.$search.'%')
                    ->orWhere('snapshot->author', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('id')->paginate(15)->withQueryString();
        $page->getCollection()->transform(function ($row) {
            $row->snapshot = json_decode($row->snapshot, true, 512, JSON_THROW_ON_ERROR);

            return $row;
        });

        return response()->json($page);
    }

    public function showDecision(Request $request, int $decision)
    {
        $region = $this->region($request);
        $row = DB::table('fruit_decisions')->when($region !== self::ALL_REGIONS, fn ($query) => $query->where('region', $region))->where('id', $decision)->first();
        abort_unless($row, 404);
        $row->snapshot = json_decode($row->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return response()->json($row);
    }
}
