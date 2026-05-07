<?php

header('Content-Type: text/html; charset=UTF-8');

// $mysqli = new mysqli('127.0.0.1', 'root', 'root', 'hasdes');
$mysqli = new mysqli('localhost', 'HASDES', 'has1922-Dev', 'HASDES', 3306);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// CSV ファイルを開く
$csvFile = fopen("/var/www/hasdes-dev/public/firstimport/result.csv", "r");
fgetcsv($csvFile); // ヘッダー行をスキップ
fgetcsv($csvFile); // 2行目をスキップ

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

    // 必要な列数（9列）になるように、空の文字列を追加
    $columnsNeeded = 2;
    while (count($row) < $columnsNeeded) {
        $row[] = ''; // 空の文字列を追加
    }

    // 存在確認のSQL
    $checkSql = "SELECT 1 FROM M品名 
                 WHERE 品名CD = ? ";
    $stmt = $mysqli->prepare($checkSql);
    $stmt->bind_param('s', $row[0]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // データが存在する場合は更新
        $updateSql = "UPDATE M品名 SET
                      名称_正式印刷用 = ?
                      WHERE 品名CD = ?";
        $stmt = $mysqli->prepare($updateSql);
        $stmt->bind_param(
            'ss',
            $row[1],
            $row[0]
        );
        $updateCount++;
    } else {
        // データが存在しない場合は挿入
        $insertSql = "INSERT INTO M品名 (
                        品名CD, 名称_正式印刷用
                      ) VALUES (?, ?)";
        $stmt = $mysqli->prepare($insertSql);
        $stmt->bind_param(
            'ss',
            $row[0], $row[1]
        );
        $insertCount++;
    }

    if (!$stmt->execute()) {
        echo "Error: " . $stmt->error;
        exit;
    }
    $stmt->close();
}


$mysqli->close();

echo "Data processed successfully!";
echo "<br>Updated rows: $updateCount";
echo "<br>Inserted rows: $insertCount";
