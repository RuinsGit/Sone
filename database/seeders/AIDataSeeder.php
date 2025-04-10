<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AIData;

class AIDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'word' => 'merhaba',
                'sentence' => 'Merhaba, nasılsınız?',
                'category' => 'greeting',
                'frequency' => 10,
                'language' => 'tr'
            ],
            [
                'word' => 'teşekkür',
                'sentence' => 'Size teşekkür ederim.',
                'category' => 'gratitude',
                'frequency' => 8,
                'language' => 'tr'
            ],
            [
                'word' => 'yardım',
                'sentence' => 'Size nasıl yardımcı olabilirim?',
                'category' => 'assistance',
                'frequency' => 12,
                'language' => 'tr'
            ],
            [
                'word' => 'anlamak',
                'sentence' => 'Sizi çok iyi anlıyorum.',
                'category' => 'empathy',
                'frequency' => 6,
                'language' => 'tr'
            ],
            [
                'word' => 'özür',
                'sentence' => 'Özür dilerim, bir hata oldu.',
                'category' => 'apology',
                'frequency' => 5,
                'language' => 'tr'
            ]
        ];

        foreach ($data as $item) {
            AIData::create($item);
        }
    }
}
