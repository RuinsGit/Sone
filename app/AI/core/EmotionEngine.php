<?php

namespace App\AI\Core;

class EmotionEngine
{
    private $currentEmotion = 'neutral';
    private $emotionIntensity = 0;
    private $emotionHistory = [];
    private $currentMood = 'normal';
    
    public function __construct()
    {
        $this->initializeEmotions();
    }
    
    private function initializeEmotions()
    {
        // Temel duyguları başlat
        $this->emotionHistory = [
            'neutral' => 0,
            'happy' => 0,
            'sad' => 0,
            'angry' => 0,
            'fearful' => 0,
            'surprised' => 0
        ];
    }
    
    public function processEmotion($input)
    {
        // Duygu analizi yap
        $emotion = $this->analyzeEmotion($input);
        $intensity = $this->calculateIntensity($input);
        
        // Duygu durumunu güncelle
        $this->updateEmotionState($emotion, $intensity);
        
        // Ruh halini güncelle
        $this->updateMood();
        
        return [
            'emotion' => $this->currentEmotion,
            'intensity' => $this->emotionIntensity,
            'mood' => $this->currentMood
        ];
    }
    
    private function analyzeEmotion($input)
    {
        // Basit duygu analizi
        $positiveWords = ['iyi', 'güzel', 'harika', 'mükemmel', 'sevgi', 'mutlu'];
        $negativeWords = ['kötü', 'üzgün', 'kızgın', 'korku', 'endişe'];
        
        $input = strtolower($input);
        
        foreach($positiveWords as $word) {
            if(strpos($input, $word) !== false) {
                return 'happy';
            }
        }
        
        foreach($negativeWords as $word) {
            if(strpos($input, $word) !== false) {
                return 'sad';
            }
        }
        
        return 'neutral';
    }
    
    private function calculateIntensity($input)
    {
        // Duygu yoğunluğunu hesapla
        $intensity = 0.5; // Varsayılan değer
        
        // Ünlem işaretleri yoğunluğu artırır
        $intensity += substr_count($input, '!') * 0.1;
        
        // Büyük harfler yoğunluğu artırır
        $intensity += substr_count($input, '!') * 0.05;
        
        return min(1, max(0, $intensity));
    }
    
    private function updateEmotionState($emotion, $intensity)
    {
        $this->currentEmotion = $emotion;
        $this->emotionIntensity = $intensity;
        $this->emotionHistory[$emotion]++;
    }
    
    private function updateMood()
    {
        // Ruh halini güncelle - duygu tarihçesine göre
        $totalEmotions = array_sum($this->emotionHistory);
        
        if ($totalEmotions > 0) {
            $positiveRatio = ($this->emotionHistory['happy'] + $this->emotionHistory['surprised']) / $totalEmotions;
            $negativeRatio = ($this->emotionHistory['sad'] + $this->emotionHistory['angry'] + $this->emotionHistory['fearful']) / $totalEmotions;
            
            if ($positiveRatio > 0.6) {
                $this->currentMood = 'positive';
            } else if ($negativeRatio > 0.6) {
                $this->currentMood = 'negative';
            } else {
                $this->currentMood = 'normal';
            }
        }
    }
    
    public function getCurrentEmotion()
    {
        return $this->currentEmotion;
    }
    
    public function getCurrentIntensity()
    {
        return $this->emotionIntensity;
    }
    
    public function getCurrentMood()
    {
        return $this->currentMood;
    }
    
    public function getEmotionalState()
    {
        return [
            'emotion' => $this->currentEmotion,
            'intensity' => $this->emotionIntensity,
            'mood' => $this->currentMood
        ];
    }
    
    public function getEmotionHistory()
    {
        return $this->emotionHistory;
    }
    
    public function resetEmotions()
    {
        $this->initializeEmotions();
        $this->currentEmotion = 'neutral';
        $this->emotionIntensity = 0;
        $this->currentMood = 'normal';
    }
    
    public function setSensitivity($sensitivity)
    {
        $this->emotionIntensity = max(0, min(1, (float)$sensitivity));
    }
} 