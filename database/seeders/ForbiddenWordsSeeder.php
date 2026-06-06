<?php

namespace Database\Seeders;

use App\Models\ForbiddenWord;
use Illuminate\Database\Seeder;

class ForbiddenWordsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{word: string, category: string}> $rows */
        $rows = require database_path('seeders/data/forbidden_words.php');

        foreach ($rows as $row) {
            ForbiddenWord::query()->updateOrCreate(
                ['word' => $row['word']],
                [
                    'category' => $row['category'],
                    'is_active' => true,
                ],
            );
        }
    }
}
