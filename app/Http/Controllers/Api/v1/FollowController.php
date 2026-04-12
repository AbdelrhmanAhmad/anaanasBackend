<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryFollow;
use App\Models\Section;
use App\Models\SectionFollow;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function sectionStatus(Request $request, Section $section)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $isFollowing = SectionFollow::query()
            ->where('user_id', (int) $user->id)
            ->where('section_id', (int) $section->id)
            ->exists();

        return response()->json(['success' => true, 'data' => ['is_following' => $isFollowing]]);
    }

    public function toggleSection(Request $request, Section $section)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $row = SectionFollow::query()
            ->where('user_id', (int) $user->id)
            ->where('section_id', (int) $section->id)
            ->first();

        $isFollowing = false;
        if ($row) {
            $row->delete();
        } else {
            SectionFollow::create([
                'user_id' => (int) $user->id,
                'section_id' => (int) $section->id,
            ]);
            $isFollowing = true;
        }

        return response()->json(['success' => true, 'data' => ['is_following' => $isFollowing]]);
    }

    public function categoryStatus(Request $request, Category $category)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $isFollowing = CategoryFollow::query()
            ->where('user_id', (int) $user->id)
            ->where('category_id', (int) $category->id)
            ->exists();

        return response()->json(['success' => true, 'data' => ['is_following' => $isFollowing]]);
    }

    public function toggleCategory(Request $request, Category $category)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $row = CategoryFollow::query()
            ->where('user_id', (int) $user->id)
            ->where('category_id', (int) $category->id)
            ->first();

        $isFollowing = false;
        if ($row) {
            $row->delete();
        } else {
            CategoryFollow::create([
                'user_id' => (int) $user->id,
                'category_id' => (int) $category->id,
            ]);
            $isFollowing = true;
        }

        return response()->json(['success' => true, 'data' => ['is_following' => $isFollowing]]);
    }

    public function myFollows(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $sections = Section::query()
            ->whereIn('id', SectionFollow::query()->where('user_id', (int) $user->id)->pluck('section_id'))
            ->get(['id', 'slug', 'name']);

        $categories = Category::query()
            ->whereIn('id', CategoryFollow::query()->where('user_id', (int) $user->id)->pluck('category_id'))
            ->with(['section:id,slug'])
            ->get(['id', 'section_id', 'slug', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'sections' => $sections->values(),
                'categories' => $categories->values(),
            ],
        ]);
    }
}
