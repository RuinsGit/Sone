<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SearchController extends Controller
{
    private $searchEngines = [
        'google' => [
            'url' => 'https://www.googleapis.com/customsearch/v1',
            'params' => [
                'key' => '', // Google API key - .env'den alınacak
                'cx' => ''   // Google Custom Search Engine ID - .env'den alınacak
            ]
        ],
        'bing' => [
            'url' => 'https://api.bing.microsoft.com/v7.0/search',
            'headers' => [
                'Ocp-Apim-Subscription-Key' => '' // Bing API key - .env'den alınacak
            ]
        ],
        'fallback' => [
            'enabled' => true
        ]
    ];
    
    private $maxResults = 5; // Varsayılan maksimum sonuç sayısı
    private $cacheTime = 60; // Dakika cinsinden önbellek süresi
    
    public function __construct()
    {
        // API anahtarlarını yükle
        $this->searchEngines['google']['params']['key'] = env('GOOGLE_SEARCH_API_KEY', '');
        $this->searchEngines['google']['params']['cx'] = env('GOOGLE_SEARCH_ENGINE_ID', '');
        $this->searchEngines['bing']['headers']['Ocp-Apim-Subscription-Key'] = env('BING_SEARCH_API_KEY', '');
    }
    
    /**
     * Web'de arama yapar
     */
    public function search(Request $request)
    {
        try {
            $query = $request->input('query');
            
            if (empty($query)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arama sorgusu boş olamaz'
                ], 400);
            }
            
            // Yanıt formatını kontrol et (json veya html)
            $responseFormat = $request->input('format', 'json');
            
            // Önceden belirlenmiş maksimum sonuç sayısını aşmamak koşuluyla, istenen sonuç sayısını belirle
            $limit = min($this->maxResults, $request->input('limit', 3));
            
            // Arama sonuçlarını al
            $results = $this->performSearch($query, $limit);
            
            // Sonuçları güvenilirlik ve alakaya göre filtrele/sırala
            $results = $this->filterResults($results);
            
            // Yanıt formatını kontrol et
            if ($responseFormat === 'html') {
                return view('ai.search-results', ['results' => $results, 'query' => $query]);
            }
            
            return response()->json([
                'success' => true,
                'query' => $query,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Arama hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Arama sırasında bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Farklı arama motorlarından sonuçları toplar
     */
    private function performSearch($query, $limit = 3)
    {
        // Önbellekte bu sorgu var mı kontrol et
        $cacheKey = 'search_' . md5($query . '_' . $limit);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        $results = [];
        $hasValidApiKey = false;
        
        // Google API ile ara (API anahtarı varsa)
        if (!empty($this->searchEngines['google']['params']['key']) && 
            !empty($this->searchEngines['google']['params']['cx'])) {
            $hasValidApiKey = true;
            try {
                $googleResults = $this->searchWithGoogle($query, $limit);
                $results = array_merge($results, $googleResults);
            } catch (\Exception $e) {
                Log::error('Google arama hatası: ' . $e->getMessage());
            }
        }
        
        // Eğer Google'dan yeterli sonuç gelmediyse ve Bing API anahtarı varsa Bing ile ara
        if (count($results) < $limit && !empty($this->searchEngines['bing']['headers']['Ocp-Apim-Subscription-Key'])) {
            $hasValidApiKey = true;
            try {
                $remainingLimit = $limit - count($results);
                $bingResults = $this->searchWithBing($query, $remainingLimit);
                $results = array_merge($results, $bingResults);
            } catch (\Exception $e) {
                Log::error('Bing arama hatası: ' . $e->getMessage());
            }
        }
        
        // Hiçbir API anahtarı yoksa veya yeterli sonuç bulunamadıysa ve fallback etkinse
        if ((!$hasValidApiKey || count($results) < $limit) && $this->searchEngines['fallback']['enabled']) {
            try {
                $remainingLimit = $limit - count($results);
                $fallbackResults = $this->fallbackSearch($query, $remainingLimit);
                $results = array_merge($results, $fallbackResults);
            } catch (\Exception $e) {
                Log::error('Fallback arama hatası: ' . $e->getMessage());
            }
        }
        
        // Sonuçları önbelleğe al
        Cache::put($cacheKey, $results, $this->cacheTime * 60);
        
        return $results;
    }
    
    /**
     * Google Custom Search API ile arama yapar
     */
    private function searchWithGoogle($query, $limit)
    {
        $response = Http::get($this->searchEngines['google']['url'], [
            'key' => $this->searchEngines['google']['params']['key'],
            'cx' => $this->searchEngines['google']['params']['cx'],
            'q' => $query,
            'num' => $limit
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            
            $results = [];
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $results[] = [
                        'title' => $item['title'] ?? '',
                        'link' => $item['link'] ?? '',
                        'snippet' => $item['snippet'] ?? '',
                        'source' => 'Google',
                        'confidence' => 0.9 // Google sonuçlarına yüksek güven
                    ];
                }
            }
            
            return $results;
        }
        
        return [];
    }
    
    /**
     * Bing Search API ile arama yapar
     */
    private function searchWithBing($query, $limit)
    {
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->searchEngines['bing']['headers']['Ocp-Apim-Subscription-Key']
        ])->get($this->searchEngines['bing']['url'], [
            'q' => $query,
            'count' => $limit
        ]);
        
        if ($response->successful()) {
            $data = $response->json();
            
            $results = [];
            if (isset($data['webPages']) && isset($data['webPages']['value']) && is_array($data['webPages']['value'])) {
                foreach ($data['webPages']['value'] as $item) {
                    $results[] = [
                        'title' => $item['name'] ?? '',
                        'link' => $item['url'] ?? '',
                        'snippet' => $item['snippet'] ?? '',
                        'source' => 'Bing',
                        'confidence' => 0.85 // Bing sonuçlarına biraz daha az güven
                    ];
                }
            }
            
            return $results;
        }
        
        return [];
    }
    
    /**
     * Yedek web araması (API anahtarları yoksa veya çalışmazsa)
     */
    private function fallbackSearch($query, $limit)
    {
        // NOT: Gerçek bir uygulamada burası web scraping veya başka bir servis olabilir
        // Bu sadece örnek bir uygulamadır
        
        $results = [];
        
        // Sabit bazı sonuçlar dön (gerçek bir uygulamada burada gerçek arama yapılır)
        for ($i = 0; $i < min(3, $limit); $i++) {
            $results[] = [
                'title' => 'Örnek sonuç ' . ($i + 1) . ' için "' . $query . '"',
                'link' => 'https://example.com/result' . ($i + 1),
                'snippet' => 'Bu bir örnek arama sonucudur. Gerçek bir API anahtarı kullanmak için lütfen .env dosyasını yapılandırın.',
                'source' => 'Fallback',
                'confidence' => 0.5 // Yedek sonuçlara daha düşük güven
            ];
        }
        
        return $results;
    }
    
    /**
     * Arama sonuçlarını güvenilirlik ve alakaya göre filtrele/sırala
     */
    private function filterResults($results)
    {
        // Güvenilir olmayan siteleri filtrele
        $results = array_filter($results, function($result) {
            // URL'i kontrol et, uygunsuz içerik barındıran siteleri filtrele
            $url = $result['link'] ?? '';
            $blockedDomains = ['spam.com', 'malware.com']; // Örnek engellenen site listesi
            
            foreach ($blockedDomains as $domain) {
                if (strpos($url, $domain) !== false) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Sonuçları güvenilirliğe göre sırala (yüksekten düşüğe)
        usort($results, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return array_values($results); // Array anahtarlarını sıfırla
    }
    
    /**
     * AI için kullanıma uygun arama yapar ve sonuçları döndürür
     */
    public function aiSearch(Request $request)
    {
        try {
            $query = $request->input('query');
            
            if (empty($query)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arama sorgusu boş olamaz'
                ], 400);
            }
            
            // Arama sonuçlarını al
            $limit = min($this->maxResults, $request->input('limit', 3));
            $results = $this->performSearch($query, $limit);
            
            // Sonuçları güvenilirlik ve alakaya göre filtrele/sırala
            $results = $this->filterResults($results);
            
            // AI için okunabilir formata dönüştür
            $formattedResults = $this->formatResultsForAI($results);
            
            return response()->json([
                'success' => true,
                'query' => $query,
                'formatted_result' => $formattedResults,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('AI arama hatası: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'AI arama sırasında bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sonuçları AI için okunabilir bir formata dönüştürür
     */
    private function formatResultsForAI($results)
    {
        if (empty($results)) {
            return "Bu konu hakkında güvenilir bilgi bulunamadı.";
        }
        
        $output = "İnternet aramasından elde edilen bilgiler:\n\n";
        
        foreach ($results as $index => $result) {
            $output .= ($index + 1) . ". " . $result['title'] . "\n";
            $output .= $result['snippet'] . "\n";
            $output .= "Kaynak: " . $result['link'] . "\n\n";
        }
        
        return $output;
    }
}
