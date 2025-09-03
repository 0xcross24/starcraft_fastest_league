<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\BuildOrder;

class BuildOrderController extends Controller
{
    // Display a listing of build orders
    public function index()
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized');
        }
        $buildOrders = BuildOrder::all();
        return view('build_orders.index', compact('buildOrders'));
    }

    // Show the form for creating a new build order
    public function create()
    {
        return view('build_orders.create');
    }

    // Store a newly created build order in storage
    public function store(Request $request)
    {
        $buildOrder = BuildOrder::create($request->all());
        return redirect()->route('builds.index');
    }

    // Display the specified build order
    public function show($id)
    {
        $buildOrder = BuildOrder::findOrFail($id);
        return view('build_orders.show', compact('buildOrder'));
    }

    // Show the form for editing the specified build order
    public function edit($id)
    {
        $buildOrder = BuildOrder::findOrFail($id);
        return view('build_orders.edit', compact('buildOrder'));
    }

    // Update the specified build order in storage
    public function update(Request $request, $id)
    {
        $buildOrder = BuildOrder::findOrFail($id);
        $buildOrder->update($request->all());
        return redirect()->route('builds.index');
    }

    // Remove the specified build order from storage
    public function destroy($id)
    {
        $buildOrder = BuildOrder::findOrFail($id);
        $buildOrder->delete();
        return redirect()->route('builds.index');
    }
}
