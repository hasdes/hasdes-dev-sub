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
    // 必要な列数（42列）になるように、空の文字列を追加
    $columnsNeeded = 42;
    while (count($row) < $columnsNeeded) {
        $row[] = ''; // 空の文字列を追加
    }

    // 長さ制限とNULL変換
    $row = array_map(function ($value) {
        return ($value === '' || $value === null) ? null : mb_substr($value, 0, 50);
    }, $row);

    // データが存在するか確認
    $checkSql = "SELECT 1 FROM M得意先 WHERE 得意先CD = ?";
    $stmt = $mysqli->prepare($checkSql);
    $stmt->bind_param('s', $row[1]);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // データが存在する場合は更新
        $updateSql = "UPDATE M得意先 SET
                      官庁区分 = ?,
                      カナ = ?,
                      得意先名 = ?,
                      得意先支店名 = ?,
                      得意先略名 = ?,
                      郵便番号 = ?,
                      住所1 = ?,
                      住所2 = ?,
                      電話番号 = ?,
                      FAX番号 = ?,
                      URL = ?,
                      営業担当者CD = ?,
                      請求先区分 = ?,
                      請求先CD = ?,
                      入金先区分 = ?,
                      入金先CD = ?,
                      納品書パターン = ?,
                      締日 = ?,
                      支払月 = ?,
                      支払日 = ?,
                      グループCD = ?,
                      グループ名 = ?,
                      グループ_都道府県 = ?,
                      請求書_郵便番号 = ?,
                      請求書_住所1 = ?,
                      請求書_住所2 = ?,
                      請求書_名称 = ?,
                      請求書_支店名 = ?,
                      納品書送り先_郵便番号 = ?,
                      納品書送り先_住所1 = ?,
                      納品書送り先_住所2 = ?,
                      納品書送り先_名称 = ?,
                      納品書送り先_支店名 = ?,
                      納品書送り先_電話番号 = ?,
                      納品書送り先_FAX番号 = ?,
                      価格設定重量 = ?,
                      増値_RF設定A = ?,
                      増値_RF設定B = ?,
                      増値_GF設定A = ?,
                      増値_GF設定B = ?,
                      送り先通知メールアドレス = ?
                      WHERE 得意先CD = ?";
        $stmt = $mysqli->prepare($updateSql);
        $stmt->bind_param(
            'ssssssssssssssssssssssssssssssssssssss',
            $row[0], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8],
            $row[9], $row[10], $row[11], $row[12], $row[13], $row[14], $row[15], $row[16],
            $row[17], $row[18], $row[19], $row[20], $row[21], $row[22], $row[23], $row[24],
            $row[25], $row[26], $row[27], $row[28], $row[29], $row[30], $row[31], $row[32],
            $row[33], $row[34], $row[35], $row[36], $row[37], $row[38], $row[39], $row[40],
            $row[41], $row[1]
        );
        $updateCount++;
    } else {
        // データが存在しない場合は挿入
        $insertSql = "INSERT INTO M得意先 (
                        官庁区分,
                        得意先CD,
                        カナ,
                        得意先名,
                        得意先支店名,
                        得意先略名,
                        郵便番号,
                        住所1,
                        住所2,
                        電話番号,
                        FAX番号,
                        URL,
                        営業担当者CD,
                        請求先区分,
                        請求先CD,
                        入金先区分,
                        入金先CD,
                        納品書パターン,
                        締日,
                        支払月,
                        支払日,
                        グループCD,
                        グループ名,
                        グループ_都道府県,
                        請求書_郵便番号,
                        請求書_住所1,
                        請求書_住所2,
                        請求書_名称,
                        請求書_支店名,
                        納品書送り先_郵便番号,
                        納品書送り先_住所1,
                        納品書送り先_住所2,
                        納品書送り先_名称,
                        納品書送り先_支店名,
                        納品書送り先_電話番号,
                        納品書送り先_FAX番号,
                        価格設定重量,
                        増値_RF設定A,
                        増値_RF設定B,
                        増値_GF設定A,
                        増値_GF設定B,
                        送り先通知メールアドレス
                      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insertSql);
        $stmt->bind_param(
            'ssssssssssssssssssssssssssssssssssssss',
            $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8],
            $row[9], $row[10], $row[11], $row[12], $row[13], $row[14], $row[15], $row[16],
            $row[17], $row[18], $row[19], $row[20], $row[21], $row[22], $row[23], $row[24],
            $row[25], $row[26], $row[27], $row[28], $row[29], $row[30], $row[31], $row[32],
            $row[33], $row[34], $row[35], $row[36], $row[37], $row[38], $row[39], $row[40],
            $row[41]
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
