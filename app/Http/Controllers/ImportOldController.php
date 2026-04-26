<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Comment;
use App\Models\Country;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportOldController extends Controller
{
    public function syncData()
    {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1024M');
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        // Prevent memory growth in long-running import loops.
        DB::connection('mysql')->disableQueryLog();
        DB::connection('mysql2')->disableQueryLog();

        //\Artisan::call('migrate');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table("users")->truncate();
        DB::table("posts")->truncate();
        DB::table("post_images")->truncate();
        DB::table("comments")->truncate();
        DB::connection('mongodb')->table("posts")->truncate();
        DB::connection('mongodb')->table("post_reactions")->truncate();
        DB::connection('mongodb')->table("comment_reactions")->truncate();

        $stats = [
            'users_imported' => 0,
            'posts_imported' => 0,
            'comments_imported' => 0,
            'post_reactions_imported' => 0,
            'comment_reactions_imported' => 0,
            'posts_skipped' => 0,
            'comments_skipped' => 0,
        ];

        $fallbackEmailCounter = 1;

        // Import users with fixed old IDs
        DB::connection("mysql2")->table("users")
            ->orderBy('user_id')
            ->chunk(1000, function ($users) use (&$fallbackEmailCounter) {
                $data = [];
                foreach ($users as $user) {
                    $legacyUserId = (int) ($user->user_id ?? 0);
                    if ($legacyUserId <= 0) {
                        continue;
                    }

                    $rawEmail = trim((string) ($user->user_email ?? ''));
                    $isValidEmail = $rawEmail !== '' && filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
                    $email = $isValidEmail ? strtolower($rawEmail) : ('legacy_' . $legacyUserId . '_' . $fallbackEmailCounter . '@legacy.local');
                    $fallbackEmailCounter++;

                    $name = trim((string) ($user->user_name ?? ''));
                    if ($name === '') {
                        $name = 'Legacy User #' . $legacyUserId;
                    }

                    $mobile = trim((string) ($user->user_phone ?? ''));

                    $data[] = [
                        'id' => $legacyUserId,
                        'name' => $name,
                        'email' => mb_substr($email, 0, 255),
                        'mobile' => $mobile !== '' ? mb_substr($mobile, 0, 20) : null,
                        'old_system_password' => $user->user_password,
                        'try_login_in_new_system' => false,
                        'created_at' => $user->time ?? now(),
                        'updated_at' => $user->time ?? now(),
                    ];
                }

                if (!empty($data)) {
                    DB::connection("mysql")->table("users")->insertOrIgnore($data);
                }
            });

        $stats['users_imported'] = (int) DB::table('users')->count();

        // Import only clean posts:
        // - not deleted
        // - has section
        // - has valid existing user
        // - has content (title or text)
        DB::connection("mysql2")->table("posts")
            ->whereNull("deleted_at")
            ->whereNotNull("section_id")
            ->orderBy('post_id', 'asc')
            ->chunk(500, function ($posts) use (&$stats) {
                foreach ($posts as $legacyPost) {
                    $legacyPostId = (int) ($legacyPost->post_id ?? 0);
                    $legacyUserId = (int) ($legacyPost->user_id ?? 0);

                    if ($legacyPostId <= 0 || $legacyUserId <= 0) {
                        $stats['posts_skipped']++;
                        continue;
                    }

                    $authorExists = User::query()->where('id', $legacyUserId)->exists();
                    if (!$authorExists) {
                        $stats['posts_skipped']++;
                        continue;
                    }

                    $rawTitle = trim((string) ($legacyPost->post_title ?? ''));
                    $rawText = trim((string) ($legacyPost->text ?? ''));
                    $hasAnyContent = $rawTitle !== '' || $rawText !== '';
                    if (!$hasAnyContent) {
                        $stats['posts_skipped']++;
                        continue;
                    }

                    $sanitizedTitle = trim((string) \str($rawTitle !== '' ? $rawTitle : $rawText)->stripTags()->limit(200));
                    $sanitizedBody = trim((string) \str(strip_tags($rawText !== '' ? $rawText : $rawTitle))->limit(4000));
                    if ($sanitizedTitle === '' && $sanitizedBody === '') {
                        $stats['posts_skipped']++;
                        continue;
                    }

                    $postRow = [
                        'id' => $legacyPostId, // preserve legacy ID
                        'user_id' => $legacyUserId,
                        'section_id' => $legacyPost->section_id,
                        'category_id' => $legacyPost->category_id,
                        'country_id' => $legacyPost->country_id,
                        'city_id' => $legacyPost->city_id,
                        'title' => $sanitizedTitle,
                        'description' => $sanitizedBody,
                        'price' => $legacyPost->post_price,
                        'created_at' => $legacyPost->time ?? now(),
                        'updated_at' => $legacyPost->time ?? now(),
                    ];

                    $inserted = DB::table("posts")->insertOrIgnore($postRow);
                    if (!$inserted) {
                        $stats['posts_skipped']++;
                        continue;
                    }

                    $stats['posts_imported']++;
                    $postDB = Post::query()->find($legacyPostId);
                    if (!$postDB) {
                        continue;
                    }

                    $legacyImages = DB::connection("mysql2")->table("posts_photos")
                        ->where("post_id", $legacyPostId)
                        ->get();
                    foreach ($legacyImages as $image) {
                        $src = trim((string) ($image->source ?? ''));
                        if ($src === '') {
                            continue;
                        }
                        $postDB->postImages()->create(['image' => "upload/" . ltrim($src, '/')]);
                    }

                    $attributeObjects = $legacyPost->attributeObjects ? json_decode($legacyPost->attributeObjects, true) : [];
                    $attributes = $attributeObjects['attributes'] ?? [];
                    $attrCollect = collect();
                    foreach ($attributes as $attribute) {
                        if (!is_array($attribute) || !isset($attribute['attribute']['id'])) {
                            continue;
                        }
                        $attr = Attribute::select('id', 'name', 'slug')->find($attribute['attribute']['id']);
                        if (!$attr) {
                            continue;
                        }

                        $mapped = ['attribute' => $attr->toArray(false)];
                        if (isset($attribute['option']) && is_array($attribute['option']) && isset($attribute['option']['id'])) {
                            $option = AttributeOption::select('id', 'name', 'attribute_id', 'slug')
                                ->find($attribute['option']['id']);
                            if ($option) {
                                $mapped['option'] = $option->toArray(false);
                            }
                        }
                        $attrCollect->push($mapped);
                    }

                    /** @var User|null $authUser */
                    $authUser = User::find($postDB->user_id);
                    $postDB->postData()->create([
                        'post_id' => $legacyPostId,
                        'user_id' => $legacyUserId,
                        'title' => $sanitizedTitle,
                        'description' => $sanitizedBody,
                        'price' => $legacyPost->post_price,
                        'country_id' => $legacyPost->country_id,
                        'city_id' => $legacyPost->city_id,
                        'section_id' => $legacyPost->section_id,
                        'category_id' => $legacyPost->category_id,
                        'user' => $authUser?->toArray(),
                        'attributes_and_options' => $attrCollect->toArray(),
                    ]);
                }
            });

        // Import comments only when:
        // - valid comment user
        // - linked post exists (already filtered)
        // - linked post has valid user
        // - comment body not empty
        DB::connection("mysql2")->table("posts_comments")
            ->whereIn("node_type", ["post", "comment"])
            ->orderBy("comment_id", "asc")
            ->chunk(1000, function ($comments) use (&$stats) {
                $rows = [];
                foreach ($comments as $comment) {
                    $legacyCommentId = (int) ($comment->comment_id ?? 0);
                    $commentUserId = (int) ($comment->user_id ?? 0);
                    if ($legacyCommentId <= 0 || $commentUserId <= 0) {
                        $stats['comments_skipped']++;
                        continue;
                    }

                    if (!User::query()->where("id", $commentUserId)->exists()) {
                        $stats['comments_skipped']++;
                        continue;
                    }

                    $postId = null;
                    $parentId = null;
                    if ($comment->node_type === "post") {
                        $postId = (int) $comment->node_id;
                    } elseif ($comment->node_type === "comment") {
                        $parent = DB::table("comments")
                            ->where("id", (int) $comment->node_id)
                            ->first(["id", "post_id"]);
                        if (!$parent) {
                            $stats['comments_skipped']++;
                            continue;
                        }
                        $postId = (int) $parent->post_id;
                        $parentId = (int) $parent->id;
                    }

                    if (!$postId) {
                        $stats['comments_skipped']++;
                        continue;
                    }

                    $post = Post::query()->where("id", $postId)->first(["id", "user_id"]);
                    if (!$post || (int) $post->user_id <= 0 || !User::query()->where("id", (int) $post->user_id)->exists()) {
                        $stats['comments_skipped']++;
                        continue;
                    }

                    $body = trim((string) \str(strip_tags((string) ($comment->text ?? '')))->limit(4000));
                    if ($body === '') {
                        $stats['comments_skipped']++;
                        continue;
                    }

                    $rows[] = [
                        "id" => $legacyCommentId, // preserve legacy ID
                        "post_id" => (int) $post->id,
                        "user_id" => $commentUserId,
                        "parent_id" => $parentId,
                        "body" => $body,
                        "created_at" => $comment->time ?? now(),
                        "updated_at" => $comment->time ?? now(),
                    ];
                }

                if (!empty($rows)) {
                    $inserted = DB::table("comments")->insertOrIgnore($rows);
                    if ($inserted) {
                        $stats['comments_imported'] += count($rows);
                    }
                }
            });

        // Import post reactions to MongoDB (only for valid imported entities)
        DB::connection("mysql2")->table("posts_reactions")
            ->orderBy("id", "asc")
            ->chunk(2000, function ($reactions) use (&$stats) {
                $rows = [];
                foreach ($reactions as $reaction) {
                    $postId = (int) $reaction->post_id;
                    $userId = (int) $reaction->user_id;
                    if ($postId <= 0 || $userId <= 0) {
                        continue;
                    }
                    if (!Post::query()->where("id", $postId)->exists()) {
                        continue;
                    }
                    if (!User::query()->where("id", $userId)->exists()) {
                        continue;
                    }

                    $type = strtolower((string) ($reaction->reaction ?? "like"));
                    if ($type === "yay") {
                        $type = "care";
                    }
                    if (!in_array($type, PostReaction::allowedTypes(), true)) {
                        $type = PostReaction::TYPE_LIKE;
                    }

                    $rows[] = [
                        "post_id" => $postId,
                        "user_id" => $userId,
                        "type" => $type,
                        "created_at" => $reaction->reaction_time ?? now(),
                        "updated_at" => $reaction->reaction_time ?? now(),
                    ];
                }

                if (!empty($rows)) {
                    DB::connection("mongodb")->table("post_reactions")->insert($rows);
                    $stats['post_reactions_imported'] += count($rows);
                }
            });

        // Import comment reactions to MongoDB (only for valid imported entities)
        DB::connection("mysql2")->table("posts_comments_reactions")
            ->orderBy("id", "asc")
            ->chunk(2000, function ($reactions) use (&$stats) {
                $rows = [];
                foreach ($reactions as $reaction) {
                    $commentId = (int) $reaction->comment_id;
                    $userId = (int) $reaction->user_id;
                    if ($commentId <= 0 || $userId <= 0) {
                        continue;
                    }
                    if (!User::query()->where("id", $userId)->exists()) {
                        continue;
                    }

                    $comment = Comment::query()->where("id", $commentId)->first(["id", "post_id"]);
                    if (!$comment) {
                        continue;
                    }

                    $type = strtolower((string) ($reaction->reaction ?? "like"));
                    if ($type === "yay") {
                        $type = "care";
                    }
                    if (!in_array($type, PostReaction::allowedTypes(), true)) {
                        $type = PostReaction::TYPE_LIKE;
                    }

                    $rows[] = [
                        "comment_id" => (int) $comment->id,
                        "post_id" => (int) $comment->post_id,
                        "user_id" => $userId,
                        "type" => $type,
                        "created_at" => $reaction->reaction_time ?? now(),
                        "updated_at" => $reaction->reaction_time ?? now(),
                    ];
                }

                if (!empty($rows)) {
                    DB::connection("mongodb")->table("comment_reactions")->insert($rows);
                    $stats['comment_reactions_imported'] += count($rows);
                }
            });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Align next auto-increment with old IDs
        $maxUserId = (int) DB::table('users')->max('id');
        $maxPostId = (int) DB::table('posts')->max('id');
        $maxCommentId = (int) DB::table('comments')->max('id');
        DB::statement('ALTER TABLE users AUTO_INCREMENT = ' . max(1, $maxUserId + 1));
        DB::statement('ALTER TABLE posts AUTO_INCREMENT = ' . max(1, $maxPostId + 1));
        DB::statement('ALTER TABLE comments AUTO_INCREMENT = ' . max(1, $maxCommentId + 1));

        return response()->json([
            'success' => true,
            'message' => 'Sync completed with clean filtering.',
            'stats' => $stats,
            'ids' => [
                'users_next_auto_increment' => max(1, $maxUserId + 1),
                'posts_next_auto_increment' => max(1, $maxPostId + 1),
                'comments_next_auto_increment' => max(1, $maxCommentId + 1),
            ],
        ]);
    }
    public function index()
    {
        ini_set('max_execution_time', 3000);

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        City::truncate();
        Country::truncate();
        Section::truncate();
        Category::truncate();
        Attribute::truncate();
        AttributeOption::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->index2();

        $path = storage_path('gatDATAOLD.json');
        if (!file_exists($path)) {
            return response()->json(['error' => 'JSON file not found'], 404);
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (!$data) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }
        foreach ($data as $s) {
            $sectionStored = Section::create(
                [
                    'id' => $s['id'],
                    'name' => ['ar' => $s['ar_name'], 'en' => $s['en_name']],
                    'icon' => $s['icon'],
                    'is_active' => 1,
                    'sort_order' => $s['id'],
                    'slug' => $s['slug'],
                ]
            );
            foreach ($s['categories'] as $c) {
                $categoryStored = Category::create(
                    [
                        'id' => $c['id'],
                        'section_id' => $c['section_id'],
                        'name' => ['ar' => $c['ar_name'], 'en' => $c['en_name']],
                        'icon' => $c['icon'],
                        'slug' => $c['slug'],
                        'sort_order' => $c['id'],
                        'is_active' => 1
                    ]
                );

                $this->importAttributes($c['attributes'], $categoryStored->id, $sectionStored->id);

            }


        }


        return response()->json([
            'message' => 'Import completed successfully'
        ]);
    }
    public function index2()
    {

        $path = storage_path('data2.json');
        if (!file_exists($path)) {
            return response()->json(['error' => 'JSON file not found'], 404);
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (!$data) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        foreach ($data as $s) {
            $cities = $s['cities'];
            Country::create($s);
            foreach ($cities as $c) {
                City::create($c);
            }
        }

    }


    public function importAttributes($attributes, $category_id, $section_id, $parent_id = null, $parent_option_id = null)
    {
        foreach ($attributes as $attr) {
            $attributeStored = Attribute::create(
                [
                    'id' => $attr['id'],
                    'name' => ['ar' => $attr['ar_name'], 'en' => $attr['en_name']],
                    'required' => 1,
                    'filterable' => 1,
                    'key_name' => $attr['key_name'],
                    'section_id' => $section_id,
                    'category_id' => $category_id,
                    'sort' => $attr['id'],
                    'multiselect' => $attr['multiselect'],
                    'input_type' => $attr['input_type'],
                    'parent_option_id' => $attr['parent_option_id'],
                    'parent_id' => $attr['parent_id'],
                    'slug' => 55,
                    'multi_level',
                ]);




            if ($attr['options']) {

                foreach ($attr['options'] as $option) {
                    $options = AttributeOption::create([
                        'section_id' => $section_id,
                        'category_id' => $category_id,
                        'attribute_id' => $attributeStored->id,
                        'name' => ['ar' => $option['ar_name'], 'en' => $option['en_name']],
                        'slug'=>"151",
                    ]);

                    $option_id = $options->id;
                    if (isset($option['sub']) && count($option['sub']) > 0) {
                        $this->importAttributes(
                            $option['sub'], $category_id, $section_id, $attributeStored->id, $option_id);
                    }
                }
            }
        }
    }
}
