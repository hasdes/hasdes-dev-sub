<script setup>
defineProps({
  authItems: Array
})

const historyRows = [
  ['2026/11/10 14時', '後藤', '作成'],
  ['2026/11/10 14時30分', '後藤', '送信'],
  ['2026/11/10 15時', '天野', '手配中'],
  ['2026/11/10 15時', '後藤', '11月21日可能でしょうか？'],
  ['2026/11/10 15時10分', '天野', '配車不可です 別工場に確認してください'],
  ['2026/11/10 15時15分', '後藤', '承知しました 東北工場手配可能ですか？'],
  ['2026/11/10 15時20分', '加藤', '手配可能です'],
  ['2026/11/10 15時25分', '後藤', '手配お願いします'],
  ['2026/11/10 15時30分', '加藤', '平車となります 荷受確認してください'],
  ['2026/11/10 15時35分', '後藤', '確認します'],
  ['2026/11/10 15時40分', '後藤', '北関東が難しい点は？'],
  ['2026/11/10 15時50分', '加藤', '荷受とめがありました']
]
</script>

<template>
  <section class="section dashboard direct-page">
    <ol class="breadcrumb">
      <li v-if="authItems?.[0]?.ホーム == 0">
        <router-link to="/home">ホーム</router-link>
      </li>
      <li>直送指示</li>
      <li>直送指示確認（物流）</li>
    </ol>

    <div class="card">
      <div class="contents_head">
        <h5 class="card-title">直送指示登録システム（確認画面）</h5>
      </div>

      <div class="confirm-top">
        <div class="top-status">
          <div>
            <span>申請ステータス</span>
            <b>更新・申請済み</b>
          </div>
          <div>
            <span>配車ステータス</span>
            <b>更新</b>
          </div>
          <div>
            <span>ドライバー情報</span>
            <b>未定</b>
          </div>
        </div>
        <button type="button" class="button_r none search od_a">印刷</button>
      </div>

      <div class="confirm-layout">
        <aside class="history-panel">
          <h6>伝言板＆変更履歴表</h6>
          <table class="table_w history-table">
            <thead>
              <tr>
                <th>日時</th>
                <th>更新者</th>
                <th>内容</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in historyRows" :key="row[0] + row[1] + row[2]">
                <td>{{ row[0] }}</td>
                <td>{{ row[1] }}</td>
                <td>{{ row[2] }}</td>
              </tr>
            </tbody>
          </table>
        </aside>

        <div class="detail-panel">
          <table class="table_w detail-table">
            <tbody>
              <tr>
                <th>依頼先</th>
                <td>● 本社　○東北　○九州　○デポ</td>
              </tr>
              <tr>
                <th>入力日</th>
                <td>2026年11月1日</td>
              </tr>
              <tr>
                <th>営業所</th>
                <td>名古屋</td>
              </tr>
              <tr>
                <th>入力者</th>
                <td>後藤　／　営業担当：岡安</td>
              </tr>
              <tr>
                <th>受注NO</th>
                <td>26000123　① / 26000123　②</td>
              </tr>
              <tr>
                <th>得意先</th>
                <td>富士機材 中部</td>
              </tr>
              <tr>
                <th>希望出荷日</th>
                <td>11月11日</td>
              </tr>
              <tr>
                <th>納入期限</th>
                <td>11月21日</td>
              </tr>
              <tr>
                <th>着時間指定</th>
                <td>●あり　○なし　午前 9時 30分</td>
              </tr>
              <tr>
                <th>送付先</th>
                <td>371-0055 群馬県前橋市北代田町613</td>
              </tr>
              <tr>
                <th>荷受人</th>
                <td>佐藤（仮）</td>
              </tr>
              <tr>
                <th>運送方法</th>
                <td>指定　●あり　○なし　4t 平</td>
              </tr>
              <tr>
                <th>地図</th>
                <td>●あり　○なし　Googleマップコード：R2X7+95 西尾市、愛知県</td>
              </tr>
              <tr>
                <th>総重量</th>
                <td>1500kg　／　想定パレット枚数 10枚</td>
              </tr>
            </tbody>
          </table>

          <div class="upload-area">
            地図アップロード<br />
            PDFアップロードできること<br />
            ドラックアンドドロップ
          </div>

          <div class="detail-actions">
            <button type="button" class="button_r back none od_b">一時保存</button>
            <button type="button" class="button_r back none od_b">削除</button>
            <button type="button" class="button_r none search od_a">確認済み</button>
            <button type="button" class="button_r none search od_a">配車中</button>
            <button type="button" class="button_r none search od_a">確定</button>
            <button type="button" class="button_r none search od_a">登録</button>
          </div>
        </div>

        <aside class="check-panel">
          <h6>日報ON/OFF</h6>
          <ul>
            <li v-for="idx in 18" :key="idx">
              <span>{{ idx }}</span>
              <input type="checkbox" />
            </li>
          </ul>
        </aside>
      </div>
    </div>

    <div class="card">
      <div class="contents_head">
        <h5 class="card-title">受注引当明細イメージ</h5>
      </div>
      <div class="report-wrap">
        <div class="report-title">受注引当明細　　　　　　PAGE : 1 / 1</div>
        <table class="table_w report-table">
          <tbody>
            <tr>
              <th>受注No.</th><td>26000003</td>
              <th>受注日付</th><td>26/01/05</td>
              <th>希望出荷日</th><td>26/02/02</td>
            </tr>
            <tr>
              <th>得意先</th><td>1401 チヨダ一商工</td>
              <th>エンドユーザー</th><td colspan="3">9002166 志木市上下水道部</td>
            </tr>
            <tr>
              <th>出荷先</th><td>9000797 (株)小泉北関東 本社</td>
              <th>営業担当</th><td>0526 野村和弘</td>
              <th>営業所</th><td>0010 東京</td>
            </tr>
          </tbody>
        </table>

        <table class="table_w report-lines">
          <thead>
            <tr>
              <th>商品CD</th><th>呼び径</th><th>数量</th><th>重量</th><th>単価</th><th>金額</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>KS2ER000</td><td>150</td><td>1</td><td>28.9</td><td>26,300</td><td>26,300</td></tr>
            <tr><td>K 短管2号 RF</td><td>996</td><td>1</td><td>0.0</td><td>1,000</td><td>1,000</td></tr>
            <tr><td colspan="5">合計</td><td>27,300</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="contents_head">
        <h5 class="card-title">管理データイメージ（19P）</h5>
      </div>
      <div class="master-note">※ 資料19Pは管理データイメージ（HASDES上では参考表示）</div>
      <div class="scroll-box s c">
        <table class="table_w master-table">
          <thead>
            <tr>
              <th>工場CD</th>
              <th>商品CD</th>
              <th>呼び径1</th>
              <th>呼び径2</th>
              <th>呼び径3</th>
              <th>ロット</th>
              <th>GSフラグ</th>
              <th>NBフラグ</th>
              <th>LGフラグ</th>
              <th>外注フラグ</th>
              <th>加工2</th>
              <th>グリッド</th>
              <th>素材あり納期</th>
              <th>素材なし納期</th>
              <th>問合数量上限</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="idx in 10" :key="idx">
              <td>1</td>
              <td>G05E 000</td>
              <td>75</td>
              <td></td>
              <td></td>
              <td>24</td>
              <td>1</td>
              <td>0</td>
              <td>0</td>
              <td>0</td>
              <td>2</td>
              <td>0</td>
              <td>25</td>
              <td>7</td>
              <td>8</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<style scoped>
.direct-page :deep(.card) {
  margin-bottom: 24px;
}

.confirm-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 0 18px 12px;
}

.top-status {
  display: flex;
  gap: 30px;
  font-size: 13px;
}

.top-status span {
  display: block;
  color: #4e5768;
}

.top-status b {
  font-size: 15px;
  color: #1f2f46;
}

.confirm-layout {
  display: grid;
  grid-template-columns: 300px minmax(0, 1fr) 110px;
  gap: 12px;
  padding: 0 18px 18px;
}

.confirm-layout > * {
  min-width: 0;
}

.history-table,
.detail-table,
.report-table,
.report-lines {
  width: 100%;
  min-width: 0 !important;
}

.history-panel,
.detail-panel,
.check-panel {
  border: 1px solid #cfd4dd;
  background: #fff;
}

.history-panel h6,
.check-panel h6 {
  margin: 0;
  padding: 10px;
  border-bottom: 1px solid #d9deea;
  font-weight: 700;
  color: #2f3440;
}

.history-table th,
.history-table td {
  border: 1px solid #d7dbe3;
  font-size: 12px;
  padding: 4px 6px;
  white-space: normal;
  word-break: break-word;
}

.detail-table th,
.detail-table td {
  border: 1px solid #aab2c0;
  padding: 6px 8px;
  white-space: normal;
  word-break: break-word;
}

.detail-table th {
  width: 150px;
  background: #f4f7fb;
  font-weight: 700;
  color: #2c3444;
}

.upload-area {
  margin: 10px;
  border: 1px dashed #2f3440;
  padding: 14px;
  text-align: center;
  font-weight: 700;
  color: #273142;
  line-height: 1.7;
}

.detail-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 0 10px 10px;
}

.check-panel ul {
  list-style: none;
  margin: 0;
  padding: 6px 10px 10px;
  max-height: 640px;
  overflow-y: auto;
}

.check-panel li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  border-bottom: 1px solid #eceff5;
  font-size: 13px;
}

.report-wrap {
  padding: 0 18px 18px;
}

.report-title {
  font-family: "Courier New", monospace;
  color: #2f3440;
  margin-bottom: 10px;
  font-weight: 700;
}

.report-table th,
.report-table td,
.report-lines th,
.report-lines td {
  border: 1px solid #97a0b1;
  padding: 6px 8px;
  font-family: "Courier New", monospace;
  font-size: 12px;
}

.report-lines {
  margin-top: 10px;
}

.master-note {
  padding: 0 18px 8px;
  color: #4f5a6e;
  font-size: 12px;
}

.master-table th,
.master-table td {
  border: 1px solid #aab2c0;
  padding: 6px 8px;
  white-space: nowrap;
  font-size: 12px;
}

.master-table th {
  background: #eef2f8;
  font-weight: 700;
}

@media (max-width: 1300px) {
  .confirm-layout {
    grid-template-columns: 1fr;
  }

  .check-panel ul {
    max-height: 240px;
  }
}

@media (max-width: 767px) {
  .confirm-top,
  .confirm-layout,
  .report-wrap {
    padding-left: 10px;
    padding-right: 10px;
  }

  .top-status {
    display: grid;
    gap: 8px;
  }

  .detail-actions .button_r {
    width: 100%;
  }
}
</style>
