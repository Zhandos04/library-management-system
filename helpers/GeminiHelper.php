<?php
/**
 * Google Gemini API Helper Class
 * Generates book descriptions using Google's Gemini AI
 */
class GeminiHelper {
    private $apiKey;
    private $apiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    private $apiAvailable = false;
    
    public function __construct() {
        error_log("[GeminiHelper] Initializing...");
        $this->apiKey = getenv('GEMINI_API_KEY');
        error_log("[GeminiHelper] API Key retrieved: " . (!empty($this->apiKey) ? 'Yes' : 'No'));
        
        if (!empty($this->apiKey)) {
            $this->apiAvailable = true;
            error_log("[GeminiHelper] API marked as available");
        } else {
            error_log("[GeminiHelper] WARNING: API Key is empty or not set!");
        }
    }
    
    /**
     * Generate a book description using Gemini AI
     * 
     * @param string $title Book title
     * @param string $author Book author
     * @param string $category Book category/genre
     * @return string|false Generated description or false on failure
     */
    public function generateBookDescription($title, $author, $category) {
        error_log("[GeminiHelper] Generating description for Book: \"$title\", Author: \"$author\", Category: \"$category\"");
        
        if (!$this->apiAvailable) {
            error_log("[GeminiHelper] ERROR: API not available, returning fallback message");
            return "Описание для книги \"$title\" автора $author в жанре $category пока не доступно. " .
                   "Для автоматической генерации описаний необходимо настроить Gemini API.";
        }
        
        $prompt = "Создай краткое описание книги \"{$title}\" автора {$author} в жанре {$category}. " .
                 "Сделай описание увлекательным, передающим суть книги, 3-4 предложения, " .
                 "без спойлеров. Возврати только текст описания.";
        
        error_log("[GeminiHelper] Generated prompt: $prompt");
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 200,
            ]
        ];
        
        $jsonData = json_encode($data);
        error_log("[GeminiHelper] Request data: " . $jsonData);
        
        $fullUrl = $this->apiEndpoint . '?key=' . $this->apiKey;
        error_log("[GeminiHelper] API URL (without key): " . $this->apiEndpoint . '?key=xxxxx');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        error_log("[GeminiHelper] Sending request to Gemini API...");
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        if ($curlError) {
            error_log("[GeminiHelper] CURL ERROR: " . $curlError);
        }
        
        curl_close($ch);
        
        error_log("[GeminiHelper] Response HTTP Code: $httpCode");
        error_log("[GeminiHelper] Raw Response: " . substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));
        
        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[GeminiHelper] JSON decode error: " . json_last_error_msg());
            }
            
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $description = $responseData['candidates'][0]['content']['parts'][0]['text'];
                error_log("[GeminiHelper] Successfully generated description: " . substr($description, 0, 100) . "...");
                return $description;
            } else {
                error_log("[GeminiHelper] Response format not as expected. Response structure: " . print_r($responseData, true));
            }
        }
        
        error_log("[GeminiHelper] API Error: " . $response);
        return "Не удалось сгенерировать описание для книги \"{$title}\". Попробуйте добавить описание вручную.";
    }
}