<?php

header('Content-Type: text/html; charset=UTF-8');

$mysqli = new mysqli('localhost', 'root', 'root', 'hasdes');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// CSV ファイルを開く
$csvFile = fopen("csv/0mtokuisaki.csv", "r");
fgetcsv($csvFile); // ヘッダー行をスキップ

// 全行を読み込む
$data = [];
while (($row = fgetcsv($csvFile)) !== FALSE) {
    $data[] = $row;
}
fclose($csvFile);

// 更新件数と挿入件数のカウンター
$updateCount = 0;
$insertCount = 0;

foreach ($data as $row) {
    // 必要な列数（5列）になるように、空の文字列を追加
    $columnsNeeded = 5;
    while (count($row) < $columnsNeeded) {
        $row[] = ''; // 空の文字列を追加
    }

    // 長さ制限とNULL変換
    $row = array_map(function ($value) {
        return ($value === '' || $value === null) ? null : mb_substr($value, 0, 50);
    }, $row);

    // データが存在するか確認
    $checkSql = "SELECT 1 FROM M担当者 WHERE 担当者CD = ?";
    $stmt = $mysqli->prepare($checkSql);
    $stmt->bind_param('s', $row[0]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // データが存在する場合は更新
        $updateSql = "UPDATE M担当者 SET
                      担当者名 = ?,
                      所属部門CD = ?,
                      所属CD = ?,
                      EMAIL = ?
                      WHERE 担当者CD = ?";
        $stmt = $mysqli->prepare($updateSql);
        $stmt->bind_param('sssss', $row[1], $row[2], $row[3], $row[4], $row[0]);
        $updateCount++;
    } else {
        // データが存在しない場合は挿入
        $insertSql = "INSERT INTO M担当者 (
                        担当者CD,
                        担当者名,
                        所属部門CD,
                        所属CD,
                        EMAIL
                      ) VALUES (?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insertSql);
        $stmt->bind_param('sssss', $row[0], $row[1], $row[2], $row[3], $row[4]);
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
