<?php

header('Content-Type: text/html; charset=UTF-8');

// $mysqli = new mysqli('127.0.0.1', 'root', 'root', 'hasdes');
$mysqli = new mysqli('localhost', 'HASDES', 'has1922-Dev', 'HASDES', 3306);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// CSV ファイルを開く
$csvFile = fopen("/var/www/hasdes-dev/public/firstimport/resultmsyouhin.csv", "r");
fgetcsv($csvFile); // ヘッダー行をスキップ
fgetcsv($csvFile);

// 全行を読み込む
$data = [];
while (($row = fgetcsv($csvFile)) !== FALSE) {
    $data[] = $row;
}
fclose($csvFile);

// 最後の行を削除
array_pop($data);
// 更新件数と挿入件数のカウンター
$updateCount = 0;
$insertCount = 0;



foreach ($data as $row) {
    if (!isset($row[0]) || trim($row[0]) === '') {
        continue;
    }
    // 必要な列数（17列）になるように、空の文字列を追加
    $columnsNeeded = 17;
    while (count($row) < $columnsNeeded) {
        $row[] = null; // 空値をNULLとして扱う
    }

    // 存在確認のSQL
    $checkSql = "SELECT 1 FROM M商品 
                 WHERE 商品CD = ? AND 呼び径1 = ? AND 呼び径2 = ? AND 呼び径3 = ?";
    $stmt = $mysqli->prepare($checkSql);
    $stmt->bind_param('ssss', $row[1], $row[2], $row[3], $row[4]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // データが存在する場合は更新
        $updateSql = "UPDATE M商品 SET
                      商品種別 = ?,
                      品名CD = ?,
                      材質CD = ?,
                      形式CD = ?,
                      操作CD = ?,
                      塗装CD = ?,
                      フランジCD = ?,
                      セットCD = ?,
                      都市CD = ?,
                      商品名_社内用 = ?,
                      単重 = ?,
                      在庫管理区分 = ?,
                      品種区分 = ?
                      WHERE 商品CD = ? AND 呼び径1 = ? AND 呼び径2 = ? AND 呼び径3 = ?";
        $stmt = $mysqli->prepare($updateSql);
        $stmt->bind_param(
            'sssssssssssssssss',
            $row[0],$row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11],
            $row[12], $row[13], $row[14], $row[15], $row[16],
            $row[1], $row[2], $row[3], $row[4]
        );
        $updateCount++;
    } else {
        // データが存在しない場合は挿入
        $insertSql = "INSERT INTO M商品 (
                        商品種別, 商品CD, 呼び径1, 呼び径2, 呼び径3, 品名CD, 材質CD,
                        形式CD, 操作CD, 塗装CD, フランジCD, セットCD, 都市CD,
                        商品名_社内用, 単重, 在庫管理区分, 品種区分
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insertSql);
        $stmt->bind_param(
            'sssssssssssssssss',
            $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6],
            $row[7], $row[8], $row[9], $row[10], $row[11], $row[12],
            $row[13], $row[14], $row[15], $row[16]
        );
        $insertCount++;
    }

    if (!$stmt->execute()) {
        echo "Error: " . $stmt->error;
        exit;
    }
    $stmt->close();
}

$insertSql = "INSERT INTO HDログ (
    担当者CD, ログ種別, 実行内容, SQL種別, エラー, SQL文
  ) VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $mysqli->prepare($insertSql);
// パラメータをバインド
$tantoCd = 'HASDES';
$logType = 4;
$execContent = 'M商品テーブル更新';
$sqlType = 1;
$error = 0;
$sqlText = '省略';

$stmt->bind_param('sisiss', $tantoCd, $logType, $execContent, $sqlType, $error, $sqlText);

$mysqli->close();

echo "Data processed successfully!";
echo "<br>Updated rows: $updateCount";
echo "<br>Inserted rows: $insertCount";
