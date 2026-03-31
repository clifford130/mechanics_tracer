<?php
// ai_extract.php - Not called directly from browser, only internally by ai_recommend.php
require_once(__DIR__ . "/ai_config.php");

function extractServiceIDs($problem_text, $services_list) {
    if (empty(AI_API_KEY)) {
        return ['success' => false, 'message' => 'API Key is missing'];
    }

    // Google Gemini API endpoint
    $url = "https://generativelanguage.googleapis.com/v1/models/" . AI_MODEL . ":generateContent?key=" . AI_API_KEY;
    
    // Format the services list for the prompt
    $services_json = json_encode($services_list);
    
    $prompt = "You are an AI trained to diagnose vehicle issues and map them to standard services.
    
    DRIVER PROBLEM: \"$problem_text\"
    
    AVAILABLE SERVICES:
    $services_json
    
    INSTRUCTIONS:
    1. Read the DRIVER PROBLEM.
    2. Identify all possible car problems described.
    3. Find the most relevant services from the AVAILABLE SERVICES list.
    4. Return ONLY a JSON array of the matching service IDs (integers).
    5. Ensure every ID returned exists in the AVAILABLE SERVICES list.
    6. If NO services match, return an empty array [].
    7. Do not return any extra text, markdown, or explanation. Only a raw JSON array of integers.";

    // Gemini API request format
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
            "maxOutputTokens" => 1000
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'message' => "cURL Error: $error"];
    }
    
    curl_close($ch);
    
    if ($http_code !== 200) {
        $err_body = json_decode($response, true);
        $err_msg = isset($err_body['error']['message']) ? $err_body['error']['message'] : $response;
        return ['success' => false, 'message' => "Gemini API Error ($http_code): $err_msg"];
    }
    
    $decoded = json_decode($response, true);
    
    // Gemini response structure: candidates[0].content.parts[0].text
    if (!isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        return ['success' => false, 'message' => "Invalid response format from Gemini"];
    }

    $content = trim($decoded['candidates'][0]['content']['parts'][0]['text']);
    
    // Instead of relying on a perfect JSON parse which Gemini is truncating,
    // let's robustly extract all integers found inside the brackets explicitly.
    preg_match_all('/\d+/', $content, $matches);
    $result_ids = isset($matches[0]) ? $matches[0] : [];

    if (empty($result_ids)) {
        return ['success' => false, 'message' => "Failed to find any service IDs in AI output. Raw: " . substr($content, 0, 200)];
    }

    // Validate the IDs
    $valid_ids = array_column($services_list, 'id');
    $filtered_ids = [];
    foreach ($result_ids as $id) {
        $int_id = (int)$id;
        if (in_array($int_id, $valid_ids) && $int_id > 0) {
            $filtered_ids[] = $int_id;
        }
    }

    // Ensure unique IDs
    $filtered_ids = array_values(array_unique($filtered_ids));
    
    return ['success' => true, 'service_ids' => $filtered_ids];
}
?>
