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
    /**
     * Public endpoint. Returns follower count for the section and — when the
     * caller is authenticated — whether that user already follows it.
     * Guests receive `is_following = false` without an auth error, so the UI
     * can render a public "Follow" call-to-action without needing a login.
     */
    public function sectionStatus(Request $request, Section $section)
    {
        $user = $request->user();

        $followersCount = (int) SectionFollow::query()
            ->where('section_id', (int) $section->id)
            ->count();

        $isFollowing = false;
        if ($user) {
            $isFollowing = SectionFollow::query()
                ->where('user_id', (int) $user->id)
                ->where('section_id', (int) $section->id)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'is_following' => $isFollowing,
                'followers_count' => $followersCount,
                'authenticated' => (bool) $user,
            ],
        ]);
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

        $followersCount = (int) SectionFollow::query()
            ->where('section_id', (int) $section->id)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'is_following' => $isFollowing,
                'followers_count' => $followersCount,
                'authenticated' => true,
            ],
        ]);
    }

    /**
     * Public endpoint. Mirrors `sectionStatus` for a category.
     */
    public function categoryStatus(Request $request, Category $category)
    {
        $user = $request->user();

        $followersCount = (int) CategoryFollow::query()
            ->where('category_id', (int) $category->id)
            ->count();

        $isFollowing = false;
        if ($user) {
            $isFollowing = CategoryFollow::query()
                ->where('user_id', (int) $user->id)
                ->where('category_id', (int) $category->id)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'is_following' => $isFollowing,
                'followers_count' => $followersCount,
                'authenticated' => (bool) $user,
            ],
        ]);
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

        $followersCount = (int) CategoryFollow::query()
            ->where('category_id', (int) $category->id)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'is_following' => $isFollowing,
                'followers_count' => $followersCount,
                'authenticated' => true,
            ],
        ]);
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
