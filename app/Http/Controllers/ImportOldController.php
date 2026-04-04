<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Post;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportOldController extends Controller
{
    public function syncData()
    {
        ini_set('max_execution_time', 3000);

        \Artisan::call('migrate');
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
//        DB::table("users") ->truncate();
//        DB::table("posts") ->truncate();
//        DB::table("post_images") ->truncate();
//        DB::connection('mongodb')->table("posts") ->truncate();
        $loop = 0 ;
  /*      DB::connection("mysql2")->table("users")
            ->orderBy('user_id')
            ->chunk(1000, function ($users) use (&$loop) {
                $data = [] ;
            foreach ($users as $user) {

                $item['id'] = $user->user_id;
                $item['name'] = $user->user_name;
//                $item['first_name'] = $user->user_name;
//                $item['last_name'] = $user->user_name;
                $item['email'] =$loop .  $user->user_email;
//                $item['email_verified'] = $user->user_email_verified;
//                $item['email_verification_code'] = $user->user_email_verification_code;
                $item['mobile'] = $user->user_phone;
//                $item['phone_verified'] = $user->user_phone_verified;
//                $item['phone_verification_code'] = $user->user_phone_verification_code;
                $item['password'] = $user->user_password;

                $loop ++ ;

                $data[] = $item;
            }


                DB::connection("mysql")->table("users")  ->insert($data);

        });
*/


        DB::connection("mysql2")->table("posts")
            ->whereNull("deleted_at")
            ->whereNotNull("section_id")
            ->where('post_id' , ">" , 126077)
//            ->where("attributeObjects" , "LIKE" , "%attributes%")
            ->orderBy('post_id' , 'asc')
            ->chunk(1000, function ($posts) use (&$loop) {
                foreach ($posts as $post) {
                    $images =   DB::connection("mysql2")->table("posts_photos")->where("post_id" , $post->post_id)->get();


                    $attributeObjects = $post->attributeObjects ? json_decode($post->attributeObjects, true) : [];
                 $attributes =  $attributeObjects['attributes'] ?? [] ;
                 $title = $post->post_title ?? $post->text  ;
                    $item['id'] = $post->post_id;
                    $item['user_id'] = $post->user_id;
                    $item['created_at'] = $post->time;
                    $item['updated_at'] = $post->time;
                    $item['section_id'] = $post->section_id;
                    $item['category_id'] = $post->category_id;
                    $item['country_id'] = $post->country_id;
                    $item['city_id'] = $post->city_id;
                    $item['title'] = \str( $title )->stripTags()->limit(200);
                    $item['description'] = strip_tags($post->text ?? $post->post_title) ;
                    $item['description'] = \str($item['description'] )->limit(4000) ;
                    $item['price'] = $post->post_price;


                    $postDB = Post::create($item);
                    foreach ($images as $image)
                    $postDB->postImages()->create(['image' => "upload/".$image->source]);
                    $attr_collect = collect();
                    foreach ($attributes  as $attribute) {
                        $item = [];

                        $attr = Attribute::  select('id' , 'name','slug')-> find($attribute ['attribute']['id']);;
                        if ($attr) {
                            $item['attribute'] = $attr->toArray(false);

                            if (isset($attribute['option']) && !empty($attribute['option'])) {
                                $Option = AttributeOption::
                                select('id' , 'name' ,'attribute_id' ,'slug')->
                                find($attribute['option']['id']);;
                                if ($Option){

                                $item['option'] = $Option->toArray(false);
                                $attr_collect->push($item);
                                }

//                                dd($attribute);
                            }
//                            if (is_array($attribute['option'])) {
//                                $Option = AttributeOption::
//                                select('id' , 'name' ,'attribute_id' ,'slug')->
//                                whereIn("id", $attribute['optionId'])->get();;
//                                $item['options'] = $Option->toArray(false);
//                            } else {
//                                $Option = AttributeOption::
//                                select('id' , 'name' ,'attribute_id' ,'slug')->
//                                find($attribute['optionId']);;
//                                $item['option'] = $Option->toArray(false);
//                            }
                        }
                    }
                    /** @var User|null $authUser */
                    $authUser = User::find($postDB->user_id);
//                    $item['post_id'] = $post->post_id;
                    $item['user'] = $authUser?->toArray();
                    $item["attributes_and_options"] = $attr_collect  ->toArray() ;
//                    DB::connection("mongodb")->table("post_data")  ->insert($item);
                    $postDB->postData()->create($item);

                }

//                DB::connection("mysql")->table("users")  ->insert($data);

            });




        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        dd($loop);




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
