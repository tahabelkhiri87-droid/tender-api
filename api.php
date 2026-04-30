<?php
// api.php - خدمة تحليل الصفقات العمومية المغربية
// معدلة للعمل على Render.com

// إعدادات CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET request - فحص صحة الخادم
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    echo json_encode([
        'status' => 'online',
        'message' => 'Maroc Tenders API - Running on Render',
        'service' => 'Prix de Référence des Appels d\'Offres',
        'server_time' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// POST request - تحليل الصفقة
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = isset($input['url']) ? trim($input['url']) : '';
    
    if (empty($url)) {
        echo json_encode(['error' => 'الرجاء إدخال رابط الصفقة', 'success' => false]);
        exit();
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'الرابط غير صالح', 'success' => false]);
        exit();
    }
    
    // التحقق من أن الرابط من بوابة الصفقات العمومية
    if (strpos($url, 'marchespublics.gov.ma') === false) {
        echo json_encode(['error' => 'الرابط يجب أن يكون من بوابة الصفقات العمومية المغربية', 'success' => false]);
        exit();
    }
    
    $result = analyzeTender($url);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit();
}

function analyzeTender($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($html)) {
        return [
            'success' => false,
            'error' => 'تعذر الوصول إلى الصفقة. تأكد من الرابط أو حاول لاحقاً.'
        ];
    }
    
    // استخراج البيانات من HTML
    $data = extractTenderData($html, $url);
    return $data;
}

function extractTenderData($html, $url) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    $result = ['success' => true, 'analyzed_url' => $url];
    
    // محاولة استخراج الثمن المرجعي
    $pricePatterns = [
        "//*[contains(text(),'الثمن المرجعي')]/following::*[1]",
        "//*[contains(text(),'Prix de référence')]/following::*[1]",
        "//*[contains(text(),'Montant')]/following::*[1]",
        "//td[contains(text(),'Prix')]/following-sibling::td[1]"
    ];
    
    foreach ($pricePatterns as $pattern) {
        $nodes = $xpath->query($pattern);
        if ($nodes->length > 0) {
            $priceText = trim($nodes->item(0)->textContent);
            preg_match('/(\d{1,3}(?:[\s,\.]\d{3})*)/', $priceText, $matches);
            if (isset($matches[1])) {
                $price = (int) preg_replace('/[^\d]/', '', $matches[1]);
                if ($price > 0) {
                    $result['reference_price'] = $price;
                    break;
                }
            }
        }
    }
    
    // استخراج الجهة المنظمة
    $orgPatterns = [
        "//*[contains(text(),'جهة طلب العروض')]/following::*[1]",
        "//*[contains(text(),'Maître d'ouvrage')]/following::*[1]"
    ];
    
    foreach ($orgPatterns as $pattern) {
        $nodes = $xpath->query($pattern);
        if ($nodes->length > 0) {
            $result['contracting_authority'] = cleanText($nodes->item(0)->textContent);
            break;
        }
    }
    
    // استخراج العنوان
    $titleNodes = $xpath->query("//h1 | //title");
    if ($titleNodes->length > 0) {
        $result['tender_title'] = cleanText($titleNodes->item(0)->textContent);
    }
    
    // استخراج رقم الصفقة من الرابط
    preg_match('/(\d{7,})/', $url, $matches);
    $result['tender_id'] = isset($matches[1]) ? $matches[1] : 'غير محدد';
    
    $result['analysis_date'] = date('Y-m-d H:i:s');
    
    // إذا لم يتم العثور على الثمن
    if (!isset($result['reference_price'])) {
        $result['reference_price'] = null;
        $result['price_not_found'] = true;
        $result['message'] = 'لم يتم العثور على الثمن المرجعي في صفحة الصفقة';
    }
    
    return $result;
}

function cleanText($text) {
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}
?>