<?php

namespace App\Http\Controllers\Company\Programmer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgrammerResource;
use App\Models\Programmer;
use Spatie\QueryBuilder\QueryBuilder;

class ProgrammerController extends Controller
{
    public function index()
    {
        if (auth()->user()->role !== 'company') {
            return response()->json([
                'message' => 'You are not authorized to access this page',
            ], 403);
        }
        $programmers = QueryBuilder::for(Programmer::class)
            ->allowedFilters(['skills.name', 'tracks.name', 'total_score', 'experience_level'])
            ->with(['skills', 'tracks', 'user'])
            ->paginate(10);

        return response()->json([
            'message' => 'Programmers fetched successfully',
            'status' => 200,
            'programmers' => ProgrammerResource::collection($programmers),
        ]);
    }

    public function show($id)
    {
        if (auth()->user()->role !== 'company') {
            return response()->json([
                'message' => 'You are not authorized to access this page',
            ], 403);
        }

        $programmer = Programmer::with(['skills', 'tracks', 'user.projects'])->find($id);

        return response()->json([
            'message' => 'Programmer fetched successfully',
            'status' => 200,
            'programmer' => $programmer,
        ]);
    }
}
