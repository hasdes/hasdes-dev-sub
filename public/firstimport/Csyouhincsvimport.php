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

// 行を1つずつ処理
foreach ($data as $row) {
    if (!isset($row[0]) || trim($row[0]) === '') {
        continue;
    }

    // 必要な列数（9列）になるように、空の文字列を追加
    $columnsNeeded = 9;
    while (count($row) < $columnsNeeded) {
        $row[] = null; // 空値をNULLとして扱う
    }

    // 存在確認のSQL
    $checkSql = "SELECT 1 FROM C商品月間 
                 WHERE 商品CD = ? AND 呼び径1 = ? AND 呼び径2 = ? AND 呼び径3 = ? AND 年号 = ? AND 倉庫部門CD = ?";
    $stmt = $mysqli->prepare($checkSql);
    $stmt->bind_param('ssssss', $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // データが存在する場合は更新
        $updateSql = "UPDATE C商品月間 SET
                      年号 = ?, 倉庫部門CD = ?, 年月度 = ?, 現在庫_完成品数 = ?, 現在庫_出荷予定数 = ?
                      WHERE 商品CD = ? AND 呼び径1 = ? AND 呼び径2 = ? AND 呼び径3 = ? AND 年号 = ? AND 倉庫部門CD = ?";
        $stmt = $mysqli->prepare($updateSql);
        $stmt->bind_param(
            'sssssssssss',
            $row[4], $row[5], $row[6], $row[7], $row[8],
            $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]
        );
        $updateCount++;
    } else {
        // データが存在しない場合は挿入
        $insertSql = "INSERT INTO C商品月間 (
                        商品CD, 呼び径1, 呼び径2, 呼び径3, 年号, 倉庫部門CD, 年月度, 現在庫_完成品数, 現在庫_出荷予定数
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insertSql);
        $stmt->bind_param(
            'sssssssss',
            $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8]
        );
        $insertCount++;
    }

    if (!$stmt->execute()) {
        echo "Error: " . $stmt->error;
        exit;
    }
    $stmt->close();
}

// 一番新しいレコードを取得
$newestSql = "SELECT 商品CD, 呼び径1, 呼び径2, 呼び径3, 年号, 倉庫部門CD 
              FROM C商品月間 
              ORDER BY 更新日時 DESC 
              LIMIT 1";
$stmt = $mysqli->prepare($newestSql);
$stmt->execute();
$newestResult = $stmt->get_result();
$newestRow = $newestResult->fetch_assoc();
$stmt->close();

if ($newestRow) {
    // 新しいレコードが見つかった場合に更新
    $updateNewestSql = "UPDATE C商品月間 SET 
                         更新日時 = NOW()
                         WHERE 商品CD = ? AND 呼び径1 = ? AND 呼び径2 = ? AND 呼び径3 = ? AND 年号 = ? AND 倉庫部門CD = ?";
    $stmt = $mysqli->prepare($updateNewestSql);

    $stmt->bind_param(
        'ssssss',
        $newestRow['商品CD'], $newestRow['呼び径1'], $newestRow['呼び径2'], $newestRow['呼び径3'], 
        $newestRow['年号'], $newestRow['倉庫部門CD']
    );

    if (!$stmt->execute()) {
        echo "Error updating timestamp: " . $stmt->error;
        exit;
    }
    $stmt->close();
    echo "The timestamp of the newest record has been successfully updated.";
} else {
    echo "No records found to update.";
}

$insertSql = "INSERT INTO HDログ (
    担当者CD, ログ種別, 実行内容, SQL種別, エラー, SQL文
  ) VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $mysqli->prepare($insertSql);
// パラメータをバインド
$tantoCd = 'HASDES';
$logType = 4;
$execContent = 'C商品月間テーブル更新';
$sqlType = 1;
$error = 0;
$sqlText = '省略';

$stmt->bind_param('sisiss', $tantoCd, $logType, $execContent, $sqlType, $error, $sqlText);


$mysqli->close();

echo "Data processed successfully!";
echo "<br>Updated rows: $updateCount";
echo "<br>Inserted rows: $insertCount";
