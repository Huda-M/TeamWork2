<?php

namespace App\Http\Controllers\Company\Programmer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProgrammerResource;
use App\Models\Programmer;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Resources\ProgrammerDetailsResource;

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
            ->with(['skills', 'tracks', 'user'])
            ->allowedFilters([
                'skills.name',
                'track',
                AllowedFilter::exact('stars'),
                'experience_level',
            ])
            ->paginate(10);

        $resource = ProgrammerResource::collection($programmers)->response()->getData(true);

        return response()->json([
            'message' => 'Programmers fetched successfully',
            'status' => 200,
            'programmers' => $resource['data'],
            'pagination' => [
                'meta' => $resource['meta'] ?? null,
                'links' => $resource['links'] ?? null,
            ],
        ]);
    }

    public function show($id)
    {
        if (auth()->user()->role !== 'company') {
            return response()->json([
                'message' => 'You are not authorized to access this page',
            ], 403);
        }

        $programmer = Programmer::with(['skills', 'tracks', 'teams.project', 'user'])->find($id);

        return response()->json([
            'message' => 'Programmer fetched successfully',
            'status' => 200,
            'programmer' => new ProgrammerDetailsResource($programmer),
        ]);
    }
}
