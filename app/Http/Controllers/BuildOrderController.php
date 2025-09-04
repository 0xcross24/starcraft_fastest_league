<?php

namespace App\Http\Controllers;

use App\Models\BuildOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BuildOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $race = $request->query('race');
        $query = BuildOrder::query();
        if (in_array($race, ['Protoss', 'Terran', 'Zerg'])) {
            $query->where('race', $race);
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
        return view('build_orders.create');
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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'race' => 'required|string|max:32',
            'matchup' => 'required|array',
            'matchup.*' => 'string',
            'steps' => 'required|string',
            'youtube_url' => 'nullable|string|max:255',
        ]);
        $validated['matchup'] = array_values($validated['matchup']);
        BuildOrder::create($validated);
        return redirect()->route('builds.index')->with('success', 'Build Order created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $buildOrder = BuildOrder::findOrFail($id);
        return view('build_orders.show', compact('buildOrder'));
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
        return view('build_orders.edit', compact('buildOrder'));
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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'race' => 'required|string|max:32',
            'matchup' => 'required|array',
            'matchup.*' => 'string',
            'steps' => 'required|string',
            'youtube_url' => 'nullable|string|max:255',
        ]);
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
        $buildOrder->delete();
        return redirect()->route('builds.index')->with('success', 'Build Order deleted successfully.');
    }
}
