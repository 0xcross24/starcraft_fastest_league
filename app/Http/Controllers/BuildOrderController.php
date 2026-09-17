<?php

namespace App\Http\Controllers;

use App\Models\BuildOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BuildOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $race = $request->query('race');

        // Only openers are listed. Transitions are reached from the build they
        // continue from, which is what keeps the list readable once a single
        // opener has several follow-ups.
        $query = BuildOrder::query()->openers()->with('transitions');

        if (in_array($race, ['Protoss', 'Terran', 'Zerg'])) {
            $query->where('race', $race);
        } elseif ($race === 'Pub') {
            $query->whereJsonContains('matchup', 'PUB');
        }
        $builds = $query->latest()->paginate(20);
        return view('build_orders.index', compact('builds', 'race'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
        $parents = BuildOrder::possibleParentsFor(null);
        $phases = BuildOrder::PHASES;
        return view('build_orders.create', compact('parents', 'phases'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
        $validated = $request->validate($this->rules());
        $validated['matchup'] = array_values($validated['matchup']);
        BuildOrder::create($validated);
        return redirect()->route('builds.index')->with('success', 'Build Order created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $buildOrder = BuildOrder::with('transitions', 'parent')->findOrFail($id);
        $trail = $buildOrder->ancestors()->reverse();
        return view('build_orders.show', compact('buildOrder', 'trail'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
        $buildOrder = BuildOrder::findOrFail($id);
        $parents = BuildOrder::possibleParentsFor($buildOrder);
        $phases = BuildOrder::PHASES;
        return view('build_orders.edit', compact('buildOrder', 'parents', 'phases'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
        $buildOrder = BuildOrder::findOrFail($id);
        $validated = $request->validate($this->rules($buildOrder));
        $validated['matchup'] = array_values($validated['matchup']);
        $buildOrder->update($validated);
        return redirect()->route('builds.index')->with('success', 'Build Order updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }
        $buildOrder = BuildOrder::findOrFail($id);

        // Deleting a build with transitions would leave them unreachable: they
        // are hidden from the listing and only linked from their parent. Make
        // the admin deal with them explicitly rather than silently orphaning.
        if ($buildOrder->transitions()->exists()) {
            return redirect()
                ->route('builds.show', ['id' => $buildOrder->id])
                ->with('error', 'This build has transitions continuing from it. Delete or re-parent those first.');
        }

        $buildOrder->delete();
        return redirect()->route('builds.index')->with('success', 'Build Order deleted successfully.');
    }

    /**
     * @param  BuildOrder|null  $buildOrder  the build being edited, excluded from
     *                                       its own parent options along with its
     *                                       descendants so no cycle can be saved
     */
    private function rules(?BuildOrder $buildOrder = null): array
    {
        $forbidden = $buildOrder && $buildOrder->exists
            ? array_merge([$buildOrder->id], $buildOrder->descendantIds())
            : [];

        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'race' => 'required|string|max:32',
            'matchup' => 'required|array',
            'matchup.*' => 'string',
            'steps' => 'required|string',
            'youtube_url' => 'nullable|string|max:255',
            'phase' => ['nullable', Rule::in(BuildOrder::PHASES)],
            'position' => 'nullable|integer|min:0',
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('build_orders', 'id')->whereNull('deleted_at'),
                Rule::notIn($forbidden),
            ],
        ];
    }
}
