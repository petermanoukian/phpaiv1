<?php

namespace Operations\Web;

class ProcessExternalUrlContentOperation {

    public static function handle(string $url): ?string {
        $cleanUrl = filter_var(trim($url), FILTER_VALIDATE_URL);
        if (!$cleanUrl) {
            return null;
        }

        try {
            // Set up a stream context with a user-agent and timeout
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) PHPAPI/1.0\r\n",
                    'timeout' => 8
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ];

            $context = stream_context_create($options);

            // Attempt to fetch website contents
            $htmlContent = @file_get_contents($cleanUrl, false, $context);

            if ($htmlContent === false) {
                return null;
            }

            // Strip HTML tags and scripts/styles to extract clean readable text
            $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $htmlContent);
            $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);
            $cleanText = strip_tags($text);
            
            // Normalize whitespace and newlines
            $cleanText = preg_replace('/\s+/', ' ', $cleanText);
            $cleanText = trim($cleanText);

            if (empty($cleanText)) {
                return null;
            }

            // Return safe truncated chunk (first 5000 characters)
            return mb_substr($cleanText, 0, 5000);

        } catch (\Throwable $e) {
            return null;
        }
    }
}