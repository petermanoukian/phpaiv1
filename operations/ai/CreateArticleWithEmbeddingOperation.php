<?php

namespace Operations\AI;

use Central\Database;
use Operations\Web\ProcessUploadedDocumentOperation;
use Operations\Web\ProcessExternalUrlContentOperation;
use Central\GeminiAccess;

class CreateArticleWithEmbeddingOperation {
    
    public static function handleForm(array $postData, ?array $fileData): array {
        $title = trim($postData['title'] ?? '');
        $content = trim($postData['content'] ?? '');
        $language = trim($postData['language'] ?? 'English');
        
        $url1 = trim($postData['external_url_1'] ?? '');
        $url2 = trim($postData['external_url_2'] ?? '');
        $url3 = trim($postData['external_url_3'] ?? '');

        if (empty($title) || empty($content)) {
            throw new \Exception("Please fill in both title and content.");
        }

        $newId = self::executeSave(
            $title, 
            $content, 
            $fileData, 
            $language, 
            $url1 !== '' ? $url1 : null, 
            $url2 !== '' ? $url2 : null, 
            $url3 !== '' ? $url3 : null
        );

        return [
            'id' => $newId,
            'message' => "Success! Record #{$newId} created with automated Gemini AI embeddings, file storage, and scraped external URL contents and individual vectors."
        ];
    }

    private static function executeSave(string $title, string $content, ?array $file, string $language, ?string $url1, ?string $url2, ?string $url3): int {
        $db = Database::getConnection();
        
        $client = GeminiAccess::getClient();
        $filePath = null;
        $textToEmbed = "Title: {$title}\n\nContent:\n{$content}";

        // 1. Process File Upload if provided (Named after article title + 4 random digits)
        if ($file && isset($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $fileResult = ProcessUploadedDocumentOperation::handle($file, $title);
            $filePath = $fileResult['file_path'] ?? null;
            
            if (!empty($fileResult['extracted_content'])) {
                $textToEmbed .= "\n\nFile Content / Details:\n" . $fileResult['extracted_content'];
            }
        }

        // 2. Scrape External URLs, get content, generate individual URL embeddings, and append to main payload
        $cleanUrl1 = !empty($url1) ? trim($url1) : null;
        $cleanUrl2 = !empty($url2) ? trim($url2) : null;
        $cleanUrl3 = !empty($url3) ? trim($url3) : null;

        $url1Content = null; $url1Embedding = null;
        $url2Content = null; $url2Embedding = null;
        $url3Content = null; $url3Embedding = null;

        

        if ($cleanUrl1) {
            $url1Content = ProcessExternalUrlContentOperation::handle($cleanUrl1);
            if ($url1Content !== null) {
                $textToEmbed .= "\n\nExternal URL 1 ({$cleanUrl1}) Content:\n" . $url1Content;
                // Generate individual embedding for URL 1
                $res1 = $client->embeddingModel('gemini-embedding-001')->embedContent($url1Content);
                $url1Embedding = json_encode($res1->embedding->values);
            }
        }

        if ($cleanUrl2) {
            $url2Content = ProcessExternalUrlContentOperation::handle($cleanUrl2);
            if ($url2Content !== null) {
                $textToEmbed .= "\n\nExternal URL 2 ({$cleanUrl2}) Content:\n" . $url2Content;
                // Generate individual embedding for URL 2
                $res2 = $client->embeddingModel('gemini-embedding-001')->embedContent($url2Content);
                $url2Embedding = json_encode($res2->embedding->values);
            }
        }

        if ($cleanUrl3) {
            $url3Content = ProcessExternalUrlContentOperation::handle($cleanUrl3);
            if ($url3Content !== null) {
                $textToEmbed .= "\n\nExternal URL 3 ({$cleanUrl3}) Content:\n" . $url3Content;
                // Generate individual embedding for URL 3
                $res3 = $client->embeddingModel('gemini-embedding-001')->embedContent($url3Content);
                $url3Embedding = json_encode($res3->embedding->values);
            }
        }

        // 3. Generate primary unified vector embedding from complete payload
        $response = $client->embeddingModel('gemini-embedding-001')->embedContent($textToEmbed);
        $vectorValues = $response->embedding->values;

        // 4. Insert into SQLite (storing URLs, scraped texts, individual URL embeddings, and main embedding)
        $stmt = $db->prepare("
            INSERT INTO articles (
                title, content, language, file_path, 
                external_url_1, external_url_2, external_url_3, 
                external_url_1_content, external_url_2_content, external_url_3_content, 
                external_url_1_embedding, external_url_2_embedding, external_url_3_embedding, 
                embedding
            ) 
            VALUES (
                :title, :content, :language, :file_path, 
                :url1, :url2, :url3, 
                :url1_content, :url2_content, :url3_content, 
                :url1_embedding, :url2_embedding, :url3_embedding, 
                :embedding
            )
        ");

        $stmt->execute([
            'title' => $title,
            'content' => $content,
            'language' => $language,
            'file_path' => $filePath,
            'url1' => $cleanUrl1,
            'url2' => $cleanUrl2,
            'url3' => $cleanUrl3,
            'url1_content' => $url1Content,
            'url2_content' => $url2Content,
            'url3_content' => $url3Content,
            'url1_embedding' => $url1Embedding,
            'url2_embedding' => $url2Embedding,
            'url3_embedding' => $url3Embedding,
            'embedding' => json_encode($vectorValues)
        ]);

        return (int)$db->lastInsertId();
    }
}