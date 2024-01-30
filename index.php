<?php
// 从文件中获取JSON数据并解析为PHP数组
$jsonData = file_get_contents('sentences/version.json');
$data = json_decode($jsonData, true);

// 获取所有句子并随机选择一个句子获取对应的JSON路径
$sentences = $data['sentences'];
$randomSentence = $sentences[array_rand($sentences)];
$jsonPath = $randomSentence['path'];

// 从路径中获取JSON数据并解析为PHP数组
$jsonData = file_get_contents($jsonPath);
$jsonArray = json_decode($jsonData, true);

// 随机选择一个hitokoto
$randomHitokoto = $jsonArray[array_rand($jsonArray)];

// 如果请求参数为"text"，则输出随机hitokoto的文本，否则以JSON格式输出随机hitokoto
if ($_GET['do'] == "text") {
    echo $randomHitokoto['hitokoto'];
} else {
    header('Content-Type: application/json');
    $r = $randomHitokoto;
    echo json_encode($r, JSON_PRETTY_PRINT);
}

