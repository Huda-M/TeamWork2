<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SkillController extends Controller
{

    public function index(Request $request)
    {
        try {
            $query = Skill::query();

            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $query->orderBy('name');

            $skills = $query->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Skills fetched successfully',
                'data' => $skills
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching skills: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch skills'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $skill = Skill::withCount(['programmers', 'projects'])->find($id);

            if (!$skill) {
                return response()->json([
                    'success' => false,
                    'message' => 'Skill not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Skill fetched successfully',
                'data' => $skill
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing skill: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to show skill'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:skills,name|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $skill = Skill::create([
                'name' => $request->name
            ]);

            Log::info('Skill created', [
                'skill_id' => $skill->id,
                'name' => $skill->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Skill created successfully',
                'data' => $skill
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating skill: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create skill'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $skill = Skill::find($id);

        if (!$skill) {
            return response()->json([
                'success' => false,
                'message' => 'Skill not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:skills,name,' . $id . '|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $skill->update([
                'name' => $request->name
            ]);

            Log::info('Skill updated', [
                'skill_id' => $skill->id,
                'name' => $skill->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Skill updated successfully',
                'data' => $skill
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating skill: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update skill'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $skill = Skill::find($id);

            if (!$skill) {
                return response()->json([
                    'success' => false,
                    'message' => 'Skill not found'
                ], 404);
            }

            $programmersCount = $skill->programmers()->count();
            $projectsCount = $skill->projects()->count();

            if ($programmersCount > 0 || $projectsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete skill that is in use',
                    'usage' => [
                        'programmers' => $programmersCount,
                        'projects' => $projectsCount
                    ]
                ], 400);
            }

            $skill->delete();

            Log::info('Skill deleted', [
                'skill_id' => $id,
                'name' => $skill->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Skill deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting skill: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete skill'
            ], 500);
        }
    }

    public function popular()
    {
        try {
            $skills = Skill::withCount(['programmers', 'projects'])
                ->orderBy('programmers_count', 'desc')
                ->orderBy('projects_count', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Popular skills fetched successfully',
                'data' => $skills
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching popular skills: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular skills'
            ], 500);
        }
    }
}
