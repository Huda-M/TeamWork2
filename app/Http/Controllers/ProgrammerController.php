<?php

namespace App\Http\Controllers;

use App\Models\Programmer;
use App\Http\Requests\StoreProgrammerRequest;
use App\Http\Requests\UpdateProgrammerRequest;

class ProgrammerController extends Controller
{
    public function index()
    {
        $programmers = Programmer::with('user')->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Programmer list fetched successfully',
            'data' => $programmers
        ]);
    }

    public function store(StoreProgrammerRequest $request)
    {
        $validated = $request->validated();
        $programmer = Programmer::create($validated);
        return response()->json([
            'status' => 'success',
            'message' => 'Programmer created successfully',
            'data' => $programmer
        ]);
    }

    public function show($id)
    {
        $programmer = Programmer::with('user')->find($id);

        if (!$programmer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Programmer not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Programmer fetched successfully',
            'data' => [
                'programmer' => $programmer
            ]
        ]);
    }
}
